<?php

namespace Tests\Webrtc\AVCodec\Context;

use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Webrtc\AVCodec\Fraction;
use Webrtc\AVCodec\AVCodec;
use Webrtc\AVCodec\AVFormat;
use Webrtc\AVCodec\Codec;
use Webrtc\AVCodec\Context\Dictionary;
use Webrtc\AVCodec\Context\VideoContext;
use Webrtc\AVCodec\Data\Buffer;
use Webrtc\AVCodec\Data\Packet;
use Webrtc\AVCodec\Data\VideoPlane;
use Webrtc\AVCodec\Enum\PictureType;
use Webrtc\AVCodec\Filter\Graph;
use Webrtc\AVCodec\Format\VideoFormat;
use Webrtc\AVCodec\Format\VideoFormatComponent;
use Webrtc\AVCodec\Frame\AudioFrame;
use Webrtc\AVCodec\Frame\Frame;
use Webrtc\AVCodec\Frame\VideoFrame;
use Webrtc\AVCodec\Frame\VideoFrameReformater;
use Webrtc\AVCodec\LibraryVersion;
use Webrtc\AVCodec\SWScale;
use Webrtc\AVCodec\TransCoder;
use Webrtc\Exception\InvalidArgumentException;
use Webrtc\Exception\RuntimeException;

#[UsesClass(AVCodec::class)]
#[UsesClass(Codec::class)]
#[UsesClass(SWScale::class)]
#[UsesClass(AVFormat::class)]
#[UsesClass(Dictionary::class)]
#[UsesClass(Buffer::class)]
#[UsesClass(Packet::class)]
#[UsesClass(VideoPlane::class)]
#[UsesClass(VideoFormat::class)]
#[UsesClass(VideoFormatComponent::class)]
#[UsesClass(Frame::class)]
#[UsesClass(AudioFrame::class)]
#[UsesClass(VideoFrame::class)]
#[UsesClass(VideoFrameReformater::class)]
#[UsesClass(LibraryVersion::class)]
#[UsesClass(SWScale::class)]
#[UsesClass(TransCoder::class)]
#[UsesClass(Graph::class)]
#[CoversClass(VideoContext::class)]
#[UsesClass(\Webrtc\AVCodec\AVFilter::class)]
class VideoContextTest extends TestCase
{
    protected function setUp(): void
    {
        if (!AVCodec::isAvailable()) {
            self::markTestSkipped(
                'Transcoding needs the FFI extension and an FFmpeg build matching the bundled headers.'
            );
        }

        parent::setUp();
        AVCodec::init();
        SWScale::init();
    }

    public function testEncodingH264()
    {
        $this->videoEncoding("h264", ["crf" => "19"]);
    }

    public function testEncodingMpeg4()
    {
        $this->videoEncoding("mpeg4");
    }

    public function testEncodingXvid()
    {
        $this->videoEncoding("mpeg4", [], "xvid");
    }

    public function testEncodingMpeg1video()
    {
        $this->videoEncoding("mpeg1video");
    }

    private function videoEncoding(string $codecName, array $options = [], ?string $codecTag = null): void
    {
        try {
            $codec = new Codec($codecName, "w");
        } catch (InvalidArgumentException $e) {
            $this->markTestSkipped("Unknown codec: $codecName");
        }

        $pixFmt = $options["pix_fmt"] ?? "yuv420p";
        $width = $options["width"] ?? 640;
        $height = $options["height"] ?? 480;
        $maxFrames = $options["max_frames"] ?? 50;
        $timeBase = $options["time_base"] ?? new Fraction(1, 25);
        $gopSize = $options["gop_size"] ?? 20;

        unset($options["pix_fmt"],
            $options["width"],
            $options["height"],
            $options["max_frames"],
            $options["time_base"],
            $options["gop_size"]
        );

        $ctx = VideoContext::create($codec);
        $ctx->setWidth($width);
        $ctx->setHeight($height);
        $timeBase = $timeBase();
        $ctx->setTimeBase($timeBase->num, $timeBase->den);
        $ctx->setFramerate($timeBase->den, 1 / $timeBase->num);
        $ctx->setPixFormat($pixFmt);
        $ctx->setGopSize($gopSize);
        $ctx->setOptions($options);
        if ($codecTag) {
            $ctx->setCodecTag($codecTag);
        }
        $ctx->open();

        $path = sys_get_temp_dir() . "/$codecName.bin";
        $file = fopen($path, "wb");
        $packetSizes = [];
        $frameCount = 0;
        $transcoder = new Transcoder($ctx);

        foreach ($this->getSampleFrames() as $frame) {
            $newFrame = $frame->reformat($width, $height, $pixFmt);

            // Reset the picture type
            $newFrame->setPictureType(PictureType::NONE);

            foreach ($transcoder->encode($newFrame) as $packet) {
                $packetSizes[] = $packet->getSize();
                fwrite($file, $packet->getData());
            }

            $frameCount++;
            if ($frameCount >= $maxFrames) {
                break;
            }
        }

        foreach ($transcoder->encode(null) as $packet) {
            $packetSizes[] = $packet->getSize();
            fwrite($file, $packet->getData());
        }
        fclose($file);

        $decCodecName = $codecName === "libx264" ? "h264" : $codecName;

        $decoderCodec = new Codec($decCodecName, "r");
        $ctx = VideoContext::create($decoderCodec);
        $ctx->open();

        $keyframeIndices = [];
        $decodedFrameCount = 0;
        foreach ($this->decodeFrames($path, $packetSizes, $ctx) as $frame) {
            if ($frame instanceof VideoFrame) {
                $decodedFrameCount++;
                $this->assertEquals($width, $frame->getFrame()->width);
                $this->assertEquals($height, $frame->getFrame()->height);
                $this->assertEquals($pixFmt, $frame->getVideoFormat()->getName());
                if ($frame->isKeyFrame()) {
                    $keyframeIndices[] = $decodedFrameCount;
                }
            }
        }

        $this->assertEquals($frameCount, $decodedFrameCount);

        $this->assertIsInt($keyframeIndices[0]);
        $decodedGopSizes = array_map(
            fn($i, $j) => $j - $i,
            array_slice($keyframeIndices, 0, -1),
            array_slice($keyframeIndices, 1)
        );

        if (in_array($codecName, ["dvvideo", "dnxhd"]) && array_reduce($decodedGopSizes, fn($carry, $i) => $carry && $i === 1, true)) {
            $this->markTestSkipped();
        }

        foreach ($decodedGopSizes as $i) {
            $this->assertEquals($gopSize, $i);
        }

        $finalGopSize = $decodedFrameCount - max($keyframeIndices);
        $this->assertLessThan($gopSize, $finalGopSize);
    }

    public function testCodecTag()
    {
        $ctx = $this->createContext("mpeg4", "w");
        $this->assertEquals("\x00\x00\x00\x00", $ctx->getCodecTag());

        $ctx->setCodecTag("xvid");
        $this->assertEquals("xvid", $ctx->getCodecTag());

        // Wrong length
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Codec tag should be a 4-character string.");
        $ctx->setCodecTag("InvalidCodecTag");
    }

    public function testDecoderExtradata()
    {
        $ctx = $this->createContext("h264", "r");
        $this->assertNull($ctx->getExtradata());
        $this->assertEquals(0, $ctx->getExtradataSize());

        $ctx->setExtradata("123");
        $this->assertEquals("123", $ctx->getExtradata());
        $this->assertEquals(3, $ctx->getExtradataSize());

        $ctx->setExtradata("852369");
        $this->assertEquals("852369", $ctx->getExtradata());
        $this->assertEquals(6, $ctx->getExtradataSize());

        $ctx->setExtradata(null);
        $this->assertNull($ctx->getExtradata());
        $this->assertEquals(0, $ctx->getExtradataSize());
    }

    public function testDecoderGopSize()
    {
        $ctx = $this->createContext("h264", "r");

        $this->expectException(RuntimeException::class);
        $ctx->getGopSize();
    }

    public function testDecoderTimebase()
    {
        $ctx = $this->createContext("h264", "r");

        $this->expectException(RuntimeException::class);
        $ctx->getTimebase();
    }

    public function testDecoderSetterTimebase()
    {
        $ctx = $this->createContext("h264", "r");

        $this->expectException(RuntimeException::class);
        $ctx->setTimeBase(1, 25);
    }

    public function testEncoderExtradata()
    {
        $ctx = $this->createContext("h264", "w");
        $this->assertNull($ctx->getExtradata());
        $this->assertEquals(0, $ctx->getExtradataSize());

        $ctx->setExtradata("123");
        $this->assertEquals("123", $ctx->getExtradata());
        $this->assertEquals(3, $ctx->getExtradataSize());
    }

    public function testEncoderPixFmt()
    {
        $ctx = $this->createContext("h264", "w");

        // Valid format
        $ctx->setPixFormat("yuv420p");
        $this->assertEquals("yuv420p", $ctx->getPixFormat());

        // Invalid format
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Not a valid pixel format: InvalidPixFormat");
        $ctx->setPixFormat("InvalidPixFormat");

        $this->assertEquals("yuv420p", $ctx->getPixFormat());
    }

    private function createContext(string $codecName, string $mode): VideoContext
    {
        $codec = new Codec($codecName, $mode);
        return VideoContext::create($codec);
    }

    function decodeFrames(string $path, array $packetSizes, VideoContext $ctx): Generator
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
        return $sampleFrames->extractFramesFromMediaFile(__DIR__ . '/../fixtures/sample.mp4');
    }
}
