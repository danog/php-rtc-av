<?php

namespace Tests\Webrtc\AVCodec\Frame;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\Webrtc\AVCodec\Fraction;
use Webrtc\AVCodec\Audio\AudioChannel;
use Webrtc\AVCodec\AVCodec;
use Webrtc\AVCodec\Context\Context;
use Webrtc\AVCodec\Context\Dictionary;
use Webrtc\AVCodec\Data\Buffer;
use Webrtc\AVCodec\Data\VideoPlane;
use Webrtc\AVCodec\Exception\AvCodecException;
use Webrtc\AVCodec\Format\VideoFormat;
use Webrtc\AVCodec\Format\VideoFormatComponent;
use Webrtc\AVCodec\Frame\VideoFrame;
use PHPUnit\Framework\TestCase;
use Webrtc\AVCodec\Frame\VideoFrameReformater;
use Webrtc\AVCodec\SWScale;

#[UsesClass(AVCodec::class)]
#[UsesClass(SWScale::class)]
#[UsesClass(VideoFormat::class)]
#[UsesClass(VideoFormatComponent::class)]
#[UsesClass(SWScale::class)]
#[UsesClass(VideoFrameReformater::class)]
#[UsesClass(AudioChannel::class)]
#[UsesClass(Buffer::class)]
#[UsesClass(VideoPlane::class)]
#[UsesClass(Context::class)]
#[UsesClass(Dictionary::class)]
#[CoversClass(VideoFrame::class)]
class VideoFrameTest extends TestCase
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

    public function testInvalidPixelFormat()
    {
        $this->expectException(AvCodecException::class);
        $this->expectExceptionMessage("Invalid format: __unknown_pix_fmt");
        new VideoFrame(640, 480, "__unknown_pix_fmt");
    }

    public function testNullConstructor()
    {
        $frame = new VideoFrame();
        $this->assertEquals(0, $frame->getVideoFormat()->getWidth());
        $this->assertEquals(0, $frame->getVideoFormat()->getHeight());
        $this->assertEquals("yuv420p", $frame->getVideoFormat()->getName());
    }

    public function testManualYuvConstructor()
    {
        $frame = new VideoFrame(640, 480, "yuv420p");
        $this->assertEquals(640, $frame->getVideoFormat()->getWidth());
        $this->assertEquals(480, $frame->getVideoFormat()->getHeight());
        $this->assertEquals("yuv420p", $frame->getVideoFormat()->getName());
    }

    public function testManualRgbConstructor()
    {
        $frame = new VideoFrame(640, 480, "rgb24");
        $this->assertEquals(640, $frame->getVideoFormat()->getWidth());
        $this->assertEquals(480, $frame->getVideoFormat()->getHeight());
        $this->assertEquals("rgb24", $frame->getVideoFormat()->getName());
    }

    public function testNullPlanes()
    {
        $frame = new VideoFrame();
        $this->assertCount(0, iterator_to_array($frame->planes()));
    }

    public function testYuv420pPlanes()
    {
        $frame = new VideoFrame(640, 480, "yuv420p");
        $planes = iterator_to_array($frame->planes());
        $this->assertCount(3, $planes);
        $this->assertEquals(640, $planes[0]->getLineSize());
        $this->assertEquals(640 * 480, $planes[0]->getSize());

        for ($i = 1; $i < 3; $i++) {
            $this->assertEquals(320, $planes[$i]->getLineSize());
            $this->assertEquals(320 * 240, $planes[$i]->getSize());
        }
    }

    public function testYuv420pPlanesAlign()
    {
        $frame = new VideoFrame(318, 238, "yuv420p");
        $planes = iterator_to_array($frame->planes());
        $this->assertCount(3, $planes);
        $this->assertEquals(320, $planes[0]->getLineSize());
        $this->assertEquals(320 * 238, $planes[0]->getSize());

        for ($i = 1; $i < 3; $i++) {
            $this->assertEquals(160, $planes[$i]->getLineSize());
            $this->assertEquals(160 * 119, $planes[$i]->getSize());
        }
    }

    public function testRgb24Planes()
    {
        $frame = new VideoFrame(640, 480, "rgb24");
        $planes = iterator_to_array($frame->planes());
        $this->assertCount(1, $planes);
        $this->assertEquals(640 * 3, $planes[0]->getLineSize());
        $this->assertEquals(640 * 480 * 3, $planes[0]->getSize());
    }

    public function testDataRead()
    {
        $frame = new VideoFrame(640, 480, "rgb24");
        $planes = iterator_to_array($frame->planes());
        $planes[0]->putData("01234" . str_repeat("x", 640 * 480 * 3 - 5));
        $data = $planes[0]->getData();
        $this->assertEquals("01234xx", substr($data, 0, 7));
    }

    public function testReformatPts()
    {
        $frame = new VideoFrame(640, 480, "rgb24");
        $frame->setPts(123);
        $frame->setTimeBase(456, 1);
        $reformattedFrame = $frame->reformat(320, 240);

        $this->assertEquals(123, $reformattedFrame->getPts());
        $this->assertEquals((new Fraction(456, 1))(), $reformattedFrame->getTimeBase());
    }

    public function testReformatIdentity()
    {
        $frame1 = new VideoFrame(640, 480, "rgb24");
        $frame2 = $frame1->reformat(640, 480, "rgb24");

        $this->assertSame($frame1, $frame2);
    }
}
