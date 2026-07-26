<?php

namespace Tests\Webrtc\AVCodec\Context;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Webrtc\AVCodec\AVCodec;
use Webrtc\AVCodec\Context\Dictionary;
use Webrtc\AVCodec\Data\Packet;
use Webrtc\AVCodec\Frame\AudioFrame;
use Webrtc\Exception\InvalidArgumentException;

#[UsesClass(AVCodec::class)]
#[UsesClass(AudioFrame::class)]
#[UsesClass(Packet::class)]
#[CoversClass(Dictionary::class)]
class DictionaryTest extends TestCase
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
    }

    public function testDictionary()
    {
        $d = new Dictionary();
        $d->key = "value";

        $this->assertEquals("value", $d->key);
        $this->assertTrue(isset($d->key));
        $this->assertCount(1, $d);
        $this->assertEquals(["key"], array_keys($d->toArray()));
        unset($d->key);
        $this->expectException(InvalidArgumentException::class);
        unset($d->key);
    }
}
