<?php

namespace Tests\Webrtc\AVCodec\Audio;

use FFI\CData;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Webrtc\AVCodec\Audio\AudioChannel;
use Webrtc\AVCodec\Audio\AudioLayout;
use PHPUnit\Framework\TestCase;
use Webrtc\AVCodec\AVCodec;

#[UsesClass(AVCodec::class)]
#[UsesClass(AudioChannel::class)]
#[CoversClass(AudioLayout::class)]
#[UsesClass(\Webrtc\AVCodec\AVFilter::class)]
#[UsesClass(\Webrtc\AVCodec\AVFormat::class)]
#[UsesClass(\Webrtc\AVCodec\LibraryVersion::class)]
class AudioLayoutTest extends TestCase
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

    public function testStereo()
    {
        $layout = new AudioLayout("stereo");
        $this->assertInstanceOf(CData::class, $layout->getLayout());
        $this->assertEquals("stereo", $layout->getName());
        $this->assertCount(2, $layout->getChannels());
        $this->assertEquals("FL", $layout->getChannels()[0]->getName());
        $this->assertEquals("front left", $layout->getChannels()[0]->getDescription());
        $this->assertEquals("FR", $layout->getChannels()[1]->getName());
        $this->assertEquals("front right", $layout->getChannels()[1]->getDescription());
        $this->assertEquals(2, $layout->getNbChannels());
    }

    public function testMono()
    {
        $layout = new AudioLayout("mono");
        $this->assertInstanceOf(CData::class, $layout->getLayout());
        $this->assertEquals("mono", $layout->getName());
        $this->assertCount(1, $layout->getChannels());
        $this->assertEquals("FC", $layout->getChannels()[0]->getName());
        $this->assertEquals("front center", $layout->getChannels()[0]->getDescription());
        $this->assertEquals(1, $layout->getNbChannels());
    }
}
