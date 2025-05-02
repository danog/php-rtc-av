<?php

namespace Tests\Webrtc\AVCodec\Frame;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Webrtc\AVCodec\Audio\AudioLayout;
use Webrtc\AVCodec\AVCodec;
use Webrtc\AVCodec\Data\AudioPlane;
use Webrtc\AVCodec\Data\Buffer;
use Webrtc\AVCodec\Format\AudioFormat;
use Webrtc\AVCodec\Frame\AudioFrame;
use PHPUnit\Framework\TestCase;

#[UsesClass(AVCodec::class)]
#[UsesClass(AudioLayout::class)]
#[UsesClass(AudioPlane::class)]
#[UsesClass(AudioFormat::class)]
#[UsesClass(Buffer::class)]
#[CoversClass(AudioFrame::class)]
class AudioFrameTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        AVCodec::init();
    }

    public function testNullConstructor()
    {
        $frame = new AudioFrame();
        $this->assertEquals('s16', $frame->getFormat()->getName());
        $this->assertEquals('stereo', $frame->getLayout()->getName());
        $this->assertCount(0, $frame->getPlanes());
        $this->assertEquals(0, $frame->getSamples());
    }

    public function testManualS16MonoConstructor()
    {
        $frame = new AudioFrame('s16', 'mono', 160);
        $this->assertEquals('s16', $frame->getFormat()->getName());
        $this->assertEquals('mono', $frame->getLayout()->getName());
        $this->assertCount(1, $frame->getPlanes());
        $this->assertEquals(320, $frame->getPlanes()[0]->getSize());
        $this->assertEquals(160, $frame->getSamples());
    }

    public function testManualS16MonoConstructorAlign8()
    {
        $frame = new AudioFrame('s16', 'mono', 159, 8);
        $this->assertEquals('s16', $frame->getFormat()->getName());
        $this->assertEquals('mono', $frame->getLayout()->getName());
        $this->assertCount(1, $frame->getPlanes());
        $this->assertEquals(320, $frame->getPlanes()[0]->getSize());
        $this->assertEquals(159, $frame->getSamples());
    }

    public function testManualS16StereoConstructor()
    {
        $frame = new AudioFrame('s16', 'stereo', 160);
        $this->assertEquals('s16', $frame->getFormat()->getName());
        $this->assertEquals('stereo', $frame->getLayout()->getName());
        $this->assertCount(1, $frame->getPlanes());
        $this->assertEquals(640, $frame->getPlanes()[0]->getSize());
        $this->assertEquals(160, $frame->getSamples());
    }

    public function testManualS16pStereoConstructor()
    {
        $frame = new AudioFrame('s16p', 'stereo', 160);
        $this->assertEquals('s16p', $frame->getFormat()->getName());
        $this->assertEquals('stereo', $frame->getLayout()->getName());
        $this->assertCount(2, $frame->getPlanes());
        $this->assertEquals(320, $frame->getPlanes()[0]->getSize());
        $this->assertEquals(320, $frame->getPlanes()[1]->getSize());
        $this->assertEquals(160, $frame->getSamples());
    }
}
