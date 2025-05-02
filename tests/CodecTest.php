<?php

namespace Tests\Webrtc\AVCodec;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Webrtc\AVCodec\AVCodec;
use Webrtc\AVCodec\Codec;
use Webrtc\AVCodec\Context\Context;
use Webrtc\AVCodec\Data\Packet;
use Webrtc\AVCodec\Format\AudioFormat;
use Webrtc\AVCodec\Format\VideoFormat;
use Webrtc\AVCodec\Format\VideoFormatComponent;
use Webrtc\AVCodec\Frame\AudioFrame;
use Webrtc\AVCodec\Frame\Frame;
use Webrtc\AVCodec\Frame\VideoFrame;
use Webrtc\Exception\InvalidArgumentException;

#[UsesClass(AVCodec::class)]
#[UsesClass(VideoFormat::class)]
#[UsesClass(AudioFormat::class)]
#[UsesClass(VideoFormatComponent::class)]
#[UsesClass(Context::class)]
#[UsesClass(Packet::class)]
#[UsesClass(Frame::class)]
#[UsesClass(AudioFrame::class)]
#[UsesClass(VideoFrame::class)]
#[CoversClass(Codec::class)]
class CodecTest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();
        AVCodec::init();
    }

    public function testInvalidCodecWithoutSecondArgument()
    {
        $this->expectException(InvalidArgumentException::class);
        new Codec("wrong_codec");
    }

    public function testInvalidCodecWithSecondArgument()
    {
        $this->expectException(InvalidArgumentException::class);
        new Codec("wrong_codec", "w");
    }

    public function testCodecMpeg4Decoder()
    {
        $c = new Codec("mpeg4");

        $this->assertEquals("mpeg4", $c->getName());
        $this->assertEquals("MPEG-4 part 2", $c->getLongName());
        $this->assertEquals("video", $c->getType());
        $this->assertContains($c->getId(), [12, 13]);
        $this->assertTrue($c->isDecoder());
        $this->assertFalse($c->isEncoder());
        $this->assertTrue($c->getDelay());

        $this->assertNull($c->getAudioFormats());
        $this->assertNull($c->getAudioRates());

        $formats = $c->getVideoFormats();
        $this->assertNull($formats);

        $this->assertNull($c->getFrameRates());
    }

    public function testCodecMpeg4Encoder()
    {
        $c = new Codec("mpeg4", "w");

        $this->assertEquals("mpeg4", $c->getName());
        $this->assertEquals("MPEG-4 part 2", $c->getLongName());
        $this->assertEquals("video", $c->getType());
        $this->assertContains($c->getId(), [12, 13]);
        $this->assertFalse($c->isDecoder());
        $this->assertTrue($c->isEncoder());
        $this->assertTrue($c->getDelay());

        $this->assertNull($c->getAudioFormats());
        $this->assertNull($c->getAudioRates());

        $formats = $c->getVideoFormats();
        $this->assertNotEmpty($formats);
        $this->assertInstanceOf(VideoFormat::class, $formats[0]);
        $this->assertTrue(in_array("yuv420p", array_map(fn($f) => $f->getName(), $formats)));

        $this->assertNull($c->getFrameRates());
    }

    public function testCodecOpusDecoder()
    {
        $c = new Codec("opus");

        $this->assertEquals("opus", $c->getName());
        $this->assertEquals("Opus", $c->getLongName());
        $this->assertEquals("audio", $c->getType());
        $this->assertContains($c->getId(), [86076]);
        $this->assertTrue($c->isDecoder());
        $this->assertFalse($c->isEncoder());
        $this->assertTrue($c->getDelay());

        $this->assertNull($c->getAudioFormats());
        $this->assertNull($c->getAudioRates());
        $this->assertNull($c->getVideoFormats());
        $this->assertNull($c->getFrameRates());
    }

    public function testCodecOpusEncoder()
    {
        $c = new Codec("opus", "w");

        $this->assertEquals("opus", $c->getName());
        $this->assertEquals("Opus", $c->getLongName());
        $this->assertEquals("audio", $c->getType());
        $this->assertContains($c->getId(), [86076]);
        $this->assertFalse($c->isDecoder());
        $this->assertTrue($c->isEncoder());
        $this->assertTrue($c->getDelay());

        // Uncomment if AudioFormat class is implemented
         $formats = $c->getAudioFormats();
         $this->assertNotEmpty($formats);
         $this->assertInstanceOf(AudioFormat::class, $formats[0]);
         $this->assertTrue(in_array("flt", array_map(fn($f) => $f->getName(), $formats)) ||
             in_array("fltp", array_map(fn($f) => $f->getName(), $formats)));

        $this->assertNotNull($c->getAudioRates());
        $this->assertContains(48000, $c->getAudioRates());

        $this->assertNull($c->getVideoFormats());
        $this->assertNull($c->getFrameRates());
    }

    public function testCodecsAvailable()
    {
        $this->assertIsArray(Codec::getCodecNames());
    }
}
