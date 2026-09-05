<?php

namespace Tests\Webrtc\AVCodec;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Webrtc\AVCodec\Audio\AudioLayout;
use Webrtc\AVCodec\AVCodec;
use Webrtc\AVCodec\AVFilter;
use Webrtc\AVCodec\Codec;
use Webrtc\AVCodec\Context\AudioContext;
use Webrtc\AVCodec\Context\Dictionary;
use Webrtc\AVCodec\Data\Packet;
use Webrtc\AVCodec\Filter\Filter;
use Webrtc\AVCodec\Format\AudioFormat;
use Webrtc\AVCodec\Format\VideoFormat;
use Webrtc\AVCodec\Frame\AudioFrame;
use Webrtc\AVCodec\Frame\VideoFrame;

#[CoversNothing]
final class SerializationTest extends TestCase
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
        AVFilter::init();
    }

    /**
     * @template T of object
     * @param T $object
     * @return T
     */
    private function cycle(object $object): object
    {
        $class = $object::class;
        $blob = serialize($object);
        unset($object);
        gc_collect_cycles();
        $restored = unserialize($blob);
        $this->assertInstanceOf($class, $restored);

        return $restored;
    }

    public function testFormatsAndCodecsSurviveSerializeCycle(): void
    {
        $format = $this->cycle(new AudioFormat('s16'));
        $this->assertSame('s16', $format->getName());

        $video = $this->cycle(new VideoFormat('yuv420p', 640, 480));
        $this->assertSame('yuv420p', $video->getName());
        $this->assertSame(640, $video->getWidth());
        $this->assertSame(480, $video->getHeight());

        $layout = $this->cycle(new AudioLayout('stereo'));
        $this->assertSame('stereo', $layout->getName());

        $codec = $this->cycle(new Codec('mpeg4', 'w'));
        $this->assertSame('mpeg4', $codec->getName());
        $this->assertTrue($codec->isEncoder());

        $filter = $this->cycle(new Filter('testsrc'));
        $this->assertSame('testsrc', $filter->getName());
    }

    public function testFramesAndPacketsSurviveSerializeCycle(): void
    {
        $audio = new AudioFrame('s16', 'mono', 160);
        $audio->setSampleRate(8000);
        $audio->setPts(42);
        $payload = str_repeat("\0", 320);
        $audio->putData($payload);
        $restoredAudio = $this->cycle($audio);
        $this->assertSame(160, $restoredAudio->getSamples());
        $this->assertSame(8000, $restoredAudio->getSampleRate());
        $this->assertSame(42, $restoredAudio->getPts());
        $this->assertSame($payload, $restoredAudio->getPlanes()[0]->getData());

        $video = new VideoFrame(16, 16, 'yuv420p');
        $video->setPts(7);
        $restoredVideo = $this->cycle($video);
        $this->assertSame(16, $restoredVideo->getVideoFormat()->getWidth());
        $this->assertSame(16, $restoredVideo->getVideoFormat()->getHeight());
        $this->assertSame(7, $restoredVideo->getPts());

        $packet = new Packet();
        $packet->putData('hello');
        $packet->setPts(3);
        $restoredPacket = $this->cycle($packet);
        $this->assertSame('hello', $restoredPacket->getData());
        $this->assertSame(3, $restoredPacket->getPts());
    }

    public function testDictionaryAndContextSurviveSerializeCycle(): void
    {
        $dict = new Dictionary();
        $dict->update(['foo' => 'bar']);
        $restoredDict = $this->cycle($dict);
        $this->assertSame('bar', $restoredDict->foo);

        $context = AudioContext::create(new Codec('pcm_s16le', 'w'));
        $this->assertInstanceOf(AudioContext::class, $context);
        $context->setFormat('s16');
        $context->setLayout('stereo');
        $context->setSampleRate(48000);
        $restored = $this->cycle($context);
        $this->assertSame(48000, $restored->getSampleRate());
        $this->assertSame('s16', $restored->getFormat()->getName());
        $this->assertSame('stereo', $restored->getLayout()->getName());
    }
}
