<?php

namespace Tests\Webrtc\AVCodec\Format;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Webrtc\AVCodec\AVCodec;
use Webrtc\AVCodec\Format\AudioFormat;
use PHPUnit\Framework\TestCase;

#[UsesClass(AVCodec::class)]
#[CoversClass(AudioFormat::class)]
class AudioFormatTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        AVCodec::init();
    }

    public function testS16Inspection()
    {
        $fmt = new AudioFormat('s16');
        $containerFormatPostfix = (unpack('S', pack('v', 1))[1] === 1) ? "le" : "be";

        $this->assertEquals('s16', $fmt->getName());
        $this->assertFalse($fmt->isPlanar());
        $this->assertEquals(16, $fmt->getBits());
        $this->assertEquals(2, $fmt->getBytes());
        $this->assertEquals('s16' . $containerFormatPostfix, $fmt->getContainerName());
        $this->assertEquals('s16p', $fmt->getPlanar()->getName());
        $this->assertSame($fmt, $fmt->getPacked());
    }
}
