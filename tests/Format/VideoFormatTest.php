<?php

namespace Tests\Webrtc\AVCodec\Format;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Webrtc\AVCodec\AVCodec;
use Webrtc\AVCodec\Context\Context;
use Webrtc\AVCodec\Context\Dictionary;
use Webrtc\AVCodec\Data\Packet;
use Webrtc\AVCodec\Format\VideoFormat;
use PHPUnit\Framework\TestCase;
use Webrtc\AVCodec\Format\VideoFormatComponent;
use Webrtc\AVCodec\Frame\AudioFrame;
use Webrtc\Exception\InvalidArgumentException;

#[UsesClass(AVCodec::class)]
#[UsesClass(VideoFormatComponent::class)]
#[UsesClass(Packet::class)]
#[UsesClass(AudioFrame::class)]
#[UsesClass(Context::class)]
#[UsesClass(Dictionary::class)]
#[CoversClass(VideoFormat::class)]
class VideoFormatTest extends TestCase
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
    }

    public function testInvalidPixelFormat()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Not a valid pixel format: __unknown_pix_fmt");
        new VideoFormat("__unknown_pix_fmt", 640, 480);
    }

    public function testRgb24Inspection()
    {
        $fmt = new VideoFormat("rgb24", 640, 480);
        $this->assertEquals("rgb24", $fmt->getName());
        $this->assertCount(3, $fmt->getComponents());
        $this->assertFalse($fmt->isPlanar());
        $this->assertFalse($fmt->hasPalette());
        $this->assertTrue($fmt->isRgb());
        $this->assertEquals(640, $fmt->chromaWidth());
        $this->assertEquals(480, $fmt->chromaHeight());
        $this->assertEquals(1024, $fmt->chromaWidth(1024));
        $this->assertEquals(1024, $fmt->chromaHeight(1024));

        for ($i = 0; $i < 3; $i++) {
            $comp = $fmt->getComponents()[$i];
            $this->assertEquals(0, $comp->getPlane());
            $this->assertEquals(8, $comp->getBits());
            $this->assertFalse($comp->isLuma());
            $this->assertFalse($comp->isChroma());
            $this->assertFalse($comp->isAlpha());
            $this->assertEquals(640, $comp->getWidth());
            $this->assertEquals(480, $comp->getHeight());
        }
    }

    public function testYuv420pInspection()
    {
        $fmt = new VideoFormat("yuv420p", 640, 480);
        $this->assertEquals("yuv420p", $fmt->getName());
        $this->assertCount(3, $fmt->getComponents());
        $this->testYuv420($fmt);
    }

    public function testYuva420pInspection()
    {
        $fmt = new VideoFormat("yuva420p", 640, 480);
        $this->assertCount(4, $fmt->getComponents());
        $this->testYuv420($fmt);
        $this->assertFalse($fmt->getComponents()[3]->isChroma());
        $this->assertEquals(640, $fmt->getComponents()[3]->getWidth());
    }

    private function testYuv420(VideoFormat $fmt)
    {
        $this->assertTrue($fmt->isPlanar());
        $this->assertFalse($fmt->hasPalette());
        $this->assertFalse($fmt->isRgb());
        $this->assertEquals(320, $fmt->chromaWidth());
        $this->assertEquals(240, $fmt->chromaHeight());
        $this->assertEquals(512, $fmt->chromaWidth(1024));
        $this->assertEquals(512, $fmt->chromaHeight(1024));

        for ($i = 0; $i < count($fmt->getComponents()); $i++) {
            $comp = $fmt->getComponents()[$i];
            $this->assertEquals($i, $comp->getPlane());
            $this->assertEquals(8, $comp->getBits());
        }

        $this->assertFalse($fmt->getComponents()[0]->isChroma());
        $this->assertTrue($fmt->getComponents()[1]->isChroma());
        $this->assertTrue($fmt->getComponents()[2]->isChroma());
        $this->assertTrue($fmt->getComponents()[0]->isLuma());
        $this->assertFalse($fmt->getComponents()[1]->isLuma());
        $this->assertFalse($fmt->getComponents()[2]->isLuma());
        $this->assertFalse($fmt->getComponents()[0]->isAlpha());
        $this->assertFalse($fmt->getComponents()[1]->isAlpha());
        $this->assertFalse($fmt->getComponents()[2]->isAlpha());
        $this->assertEquals(640, $fmt->getComponents()[0]->getWidth());
        $this->assertEquals(320, $fmt->getComponents()[1]->getWidth());
        $this->assertEquals(320, $fmt->getComponents()[2]->getWidth());
    }

    public function testGray16beInspection()
    {
        $fmt = new VideoFormat("gray16be", 640, 480);
        $this->assertEquals("gray16be", $fmt->getName());
        $this->assertCount(1, $fmt->getComponents());
        $this->assertFalse($fmt->isPlanar());
        $this->assertFalse($fmt->hasPalette());
        $this->assertFalse($fmt->isRgb());
        $this->assertEquals(640, $fmt->chromaWidth());
        $this->assertEquals(480, $fmt->chromaHeight());
        $this->assertEquals(1024, $fmt->chromaWidth(1024));
        $this->assertEquals(1024, $fmt->chromaHeight(1024));

        $comp = $fmt->getComponents()[0];
        $this->assertEquals(0, $comp->getPlane());
        $this->assertEquals(16, $comp->getBits());
        $this->assertTrue($comp->isLuma());
        $this->assertFalse($comp->isChroma());
        $this->assertEquals(640, $comp->getWidth());
        $this->assertEquals(480, $comp->getHeight());
        $this->assertFalse($comp->isAlpha());
    }

    public function testPal8Inspection()
    {
        $fmt = new VideoFormat("pal8", 640, 480);
        $this->assertCount(1, $fmt->getComponents());
        $this->assertTrue($fmt->hasPalette());
    }
}
