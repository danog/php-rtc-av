<?php

namespace Tests\Webrtc\AVCodec\Context;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\Webrtc\AVCodec\Fraction;
use Webrtc\AVCodec\Audio\AudioLayout;
use Webrtc\AVCodec\Audio\AudioResampler;
use Webrtc\AVCodec\AVCodec;
use Webrtc\AVCodec\AVFilter;
use Webrtc\AVCodec\AVFormat;
use Webrtc\AVCodec\Codec;
use Webrtc\AVCodec\Context\AudioContext;
use PHPUnit\Framework\TestCase;
use Webrtc\AVCodec\Context\Dictionary;
use Webrtc\AVCodec\Data\Buffer;
use Webrtc\AVCodec\Data\Packet;
use Webrtc\AVCodec\Filter\Filter;
use Webrtc\AVCodec\Filter\FilterContext;
use Webrtc\AVCodec\Filter\Graph;
use Webrtc\AVCodec\Format\AudioFormat;
use Webrtc\AVCodec\Frame\AudioFrame;
use Webrtc\AVCodec\Frame\Frame;
use Webrtc\AVCodec\LibraryVersion;
use Webrtc\AVCodec\TransCoder;
use Webrtc\Exception\InvalidArgumentException;
use Webrtc\Exception\RuntimeException;

#[UsesClass(AVCodec::class)]
#[UsesClass(AVFilter::class)]
#[UsesClass(AVFormat::class)]
#[UsesClass(AudioLayout::class)]
#[UsesClass(AudioResampler::class)]
#[UsesClass(Codec::class)]
#[UsesClass(Dictionary::class)]
#[UsesClass(Buffer::class)]
#[UsesClass(Packet::class)]
#[UsesClass(Filter::class)]
#[UsesClass(FilterContext::class)]
#[UsesClass(Graph::class)]
#[UsesClass(AudioFormat::class)]
#[UsesClass(AudioFrame::class)]
#[UsesClass(Frame::class)]
#[UsesClass(LibraryVersion::class)]
#[UsesClass(TransCoder::class)]
#[CoversClass(AudioContext::class)]
class AudioContextTest extends TestCase
{

    protected function setUp(): void
    {
        if (!AVCodec::isAvailable()) {
            self::markTestSkipped(
                'Transcoding needs the FFI extension and an FFmpeg build matching the bundled headers.'
            );
        }

        parent::setUp();
        AVCodec::init(false);
        AVFilter::init(debug: false);
    }

    public function testEncodingPcmS24le()
    {
        $this->audioEncoding("pcm_s24le");
    }

    public function testEncodingPcmALaw()
    {
        $this->audioEncoding("pcm_alaw");
    }

    public function testEncodingPcmMULaw()
    {
        $this->audioEncoding("pcm_mulaw");
    }

    public function testEncodingMp2()
    {
        $this->audioEncoding("mp2");
    }

    private function audioEncoding(string $codecName)
    {
        try {
            $codec = new Codec($codecName, "w");
        } catch (InvalidArgumentException) {
            $this->markTestSkipped("Unknown codec: $codecName");
        }

        if ($codec->isExperimental()) {
            $this->markTestSkipped("Experimental codec: $codecName");
        }
        $this->assertNotEmpty($codec->getAudioFormats());

        $ctx = AudioContext::create($codec);

        $sampleFmt = $codec->getAudioFormats()[array_key_last($codec->getAudioFormats())]->getName();
        $sampleRate = 48000;
        $layout = 'stereo';

        $ctx->setTimeBase(1, $sampleRate);
        $ctx->setSampleRate($sampleRate);
        $ctx->setFormat($sampleFmt);
        $ctx->setLayout($layout);

        $ctx->open();
        $resampler = new AudioResampler("s32", "stereo", 48000);

        $packetSizes = [];
        $path = sys_get_temp_dir() . "/$codecName.bin";
        $file = fopen($path, "wb");

        $transcoder = new Transcoder($ctx);

        foreach ($this->getSampleFrames() as $frame) {
            $resampledFrames = $resampler->resample($frame);
            foreach ($resampledFrames as $resampledFrame) {
                $this->assertEquals((new Fraction(1, 48000))(), $resampledFrame->getTimeBase());

                foreach ($transcoder->encode($resampledFrame) as $packet) {
                    $this->assertEquals((new Fraction(1, 48000))(), $packet->getTimeBase());
                    $packetSizes[] = $packet->getSize();
                    fwrite($file, $packet->getData());
                }
            }
        }

        foreach ($transcoder->encode(null) as $packet) {
            $this->assertEquals((new Fraction(1, 48000))(), $packet->getTimeBase());
            $packetSizes[] = $packet->getSize();
            fwrite($file, $packet->getData());
        }
        fclose($file);

        $codec = new Codec($codecName, "r");
        $ctx = AudioContext::create($codec);
        $ctx->setSampleRate($sampleRate);
        $ctx->setFormat($sampleFmt);
        $ctx->setLayout($layout);
        $ctx->open();

        foreach ($this->decodeFrames($path, $packetSizes, $ctx) as $frame) {
            $this->assertEquals($sampleRate, $frame->getSampleRate());
            $this->assertEquals(2, $frame->getLayout()->getNbChannels());
        }
    }

    function decodeFrames(string $path, array $packetSizes, AudioContext $ctx): \Generator
    {
        $handle = fopen($path, "rb");
        if (!$handle) {
            throw new RuntimeException("Failed to open file: $path");
        }
        $transcoder = new Transcoder($ctx);

        foreach ($packetSizes as $size) {
            $packet = new Packet();
            $readSize = '';
            while (strlen($readSize) < $size) {
                $chunk = fread($handle, $size - strlen($readSize));
                if ($chunk === false || $chunk === '') {
                    break;
                }
                $readSize .= $chunk;
            }

            if (strlen($readSize) !== $size) {
                throw new RuntimeException("Failed to read expected packet size");
            }
            $packet->putData($readSize);

            foreach ($transcoder->decode($packet) as $frame) {
                yield $frame;
            }
        }

        while (true) {
            try {
                $frames = $transcoder->decode(null);
            } catch (RuntimeException) {
                break;
            }

            foreach ($frames as $frame) {
                yield $frame;
            }

            if (empty($frames)) {
                break;
            }
        }

        fclose($handle);
    }

    private function getSampleFrames()
    {
        $sampleFrames = new FrameExtractor();
        return $sampleFrames->extractFramesFromMediaFile(__DIR__ . '/../fixtures/sample.wav');
    }
}
