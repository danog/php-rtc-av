<?php

namespace Tests\Webrtc\AVCodec\Audio;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\Webrtc\AVCodec\Fraction;
use Webrtc\AVCodec\Audio\AudioLayout;
use Webrtc\AVCodec\Audio\AudioResampler;
use PHPUnit\Framework\TestCase;
use Webrtc\AVCodec\AVCodec;
use Webrtc\AVCodec\AVFilter;
use Webrtc\AVCodec\Context\Context;
use Webrtc\AVCodec\Context\Dictionary;
use Webrtc\AVCodec\Data\Packet;
use Webrtc\AVCodec\Filter\Filter;
use Webrtc\AVCodec\Filter\FilterContext;
use Webrtc\AVCodec\Filter\Graph;
use Webrtc\AVCodec\Format\AudioFormat;
use Webrtc\AVCodec\Frame\AudioFrame;
use Webrtc\AVCodec\Frame\Frame;
use Webrtc\AVCodec\Frame\VideoFrame;
use Webrtc\Exception\InvalidArgumentException;

#[UsesClass(AVCodec::class)]
#[UsesClass(AVFilter::class)]
#[UsesClass(AudioLayout::class)]
#[UsesClass(Dictionary::class)]
#[UsesClass(Filter::class)]
#[UsesClass(FilterContext::class)]
#[UsesClass(Graph::class)]
#[UsesClass(AudioFormat::class)]
#[UsesClass(AudioFrame::class)]
#[UsesClass(Packet::class)]
#[UsesClass(Frame::class)]
#[UsesClass(Context::class)]
#[UsesClass(VideoFrame::class)]
#[CoversClass(AudioResampler::class)]
class AudioResamplerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        AVCodec::init(false);
        AVFilter::init();
    }

    public function testFlushImmediately()
    {
        $resampler = new AudioResampler("s16", "stereo", 48000, 960);

        $oframes = $resampler->resample(null);
        $this->assertCount(0, $oframes);
    }

    public function testIdentityPassthrough()
    {
        $resampler = new AudioResampler("s16", "stereo", 48000);

        $iframe = new AudioFrame("s16", "stereo", 1024);

        $oframes = $resampler->resample($iframe);
        $this->assertCount(1, $oframes);
        $this->assertFrame($iframe, $oframes[0]);

        $iframe->setPts(1024);

        $oframes = $resampler->resample($iframe);
        $this->assertCount(1, $oframes);
        $this->assertFrame($iframe, $oframes[0]);

        $oframes = $resampler->resample(null);
        $this->assertCount(0, $oframes);
    }

    public function testMatchingPassthrough()
    {
        $resampler = new AudioResampler("s16", "stereo", 1024);

        $iframe = new AudioFrame("s16", "stereo", 1024);

        $oframes = $resampler->resample($iframe);
        $this->assertCount(1, $oframes);
        $this->assertFrame($iframe, $oframes[0]);

        $iframe->setPts(1024);

        $oframes = $resampler->resample($iframe);
        $this->assertCount(1, $oframes);
        $this->assertFrame($iframe, $oframes[0]);

        $oframes = $resampler->resample(null);
        $this->assertCount(0, $oframes);
    }

    public function testPtsAssertionSameRate()
    {
        $resampler = new AudioResampler("s16", "mono");

        // resample one frame
        $iframe = new AudioFrame("s16", "stereo", 1024);
        $iframe->setSampleRate( 48000);
        $iframe->setTimeBase(1, 48000);
        $iframe->setPts(0);

        $oframes = $resampler->resample($iframe);
        $this->assertCount(1, $oframes);

        $oframe = $oframes[0];
        $this->assertEquals(0, $oframe->getPts());
        $this->assertEquals($iframe->getTimeBase(), $oframe->getTimeBase());
        $this->assertEquals($iframe->getSampleRate(), $oframe->getSampleRate());
        $this->assertEquals($iframe->getSamples(), $oframe->getSamples());

        // resample another frame
        $iframe->setPts(1024);

        $oframes = $resampler->resample($iframe);
        $this->assertCount(1, $oframes);

        $oframe = $oframes[0];
        $this->assertEquals(1024, $oframe->getPts());
        $this->assertEquals($iframe->getTimeBase(), $oframe->getTimeBase());
        $this->assertEquals($iframe->getSampleRate(), $oframe->getSampleRate());
        $this->assertEquals($iframe->getSamples(), $oframe->getSamples());

        // resample another frame with a pts gap, do not raise exception
        $iframe->setPts(9999);
        $oframes = $resampler->resample($iframe);
        $this->assertCount(1, $oframes);

        $oframe = $oframes[0];
        $this->assertEquals(9999, $oframe->getPts());
        $this->assertEquals($iframe->getTimeBase(), $oframe->getTimeBase());
        $this->assertEquals($iframe->getSampleRate(), $oframe->getSampleRate());
        $this->assertEquals($iframe->getSamples(), $oframe->getSamples());

        // flush
        $oframes = $resampler->resample(null);
        $this->assertCount(0, $oframes);
    }

    public function testPtsAssertionNewRateUp()
    {
        $resampler = new AudioResampler("s16", "mono", 44100);

        $iframe = new AudioFrame("s16", "stereo", 1024);
        $iframe->setSampleRate(48000);
        $iframe->setTimeBase(1, 48000);
        $iframe->setPts(0);

        $oframes = $resampler->resample($iframe);
        $this->assertCount(1, $oframes);

        $oframe = $oframes[0];
        $this->assertEquals(0, $oframe->getPts());
        $this->assertEquals(new Fraction(1, 44100)(), $oframe->getTimeBase());
        $this->assertEquals(44100, $oframe->getSampleRate());
        $this->assertEquals(925, $oframe->getSamples());

        $iframe = new AudioFrame("s16", "stereo", 1024);
        $iframe->setSampleRate(48000);
        $iframe->setTimeBase(1, 48000);
        $iframe->setPts(1024);


        $oframes = $resampler->resample($iframe);
        $this->assertCount(1, $oframes);

        $oframe = $oframes[0];
        $this->assertEquals(925, $oframe->getPts());
        $this->assertEquals(new Fraction(1, 44100)(), $oframe->getTimeBase());
        $this->assertEquals(44100, $oframe->getSampleRate());
        $this->assertEquals(941, $oframe->getSamples());

        $oframes = $resampler->resample(null);
        $this->assertCount(1, $oframes);

        $oframe = $oframes[0];
        $this->assertEquals(941 + 925, $oframe->getPts());
        $this->assertEquals(new Fraction(1, 44100)(), $oframe->getTimeBase());
        $this->assertEquals(44100, $oframe->getSampleRate());
        $this->assertEquals(15, $oframe->getSamples());
    }

    public function testPtsAssertionNewRateDown()
    {
        $resampler = new AudioResampler("s16", "mono", 48000);

        $iframe = new AudioFrame("s16", "stereo", 1024);
        $iframe->setSampleRate(44100);
        $iframe->setTimeBase(1, 44100);
        $iframe->setPts(0);

        $oframes = $resampler->resample($iframe);
        $this->assertCount(1, $oframes);

        $oframe = $oframes[0];
        $this->assertEquals(0, $oframe->getPts());
        $this->assertEquals(new Fraction(1, 48000)(), $oframe->getTimeBase());
        $this->assertEquals(48000, $oframe->getSampleRate());
        $this->assertEquals(1098, $oframe->getSamples());

        $iframe = new AudioFrame("s16", "stereo", 1024);
        $iframe->setSampleRate(44100);
        $iframe->setTimeBase(1, 44100);
        $iframe->setPts(1024);

        $oframes = $resampler->resample($iframe);
        $this->assertCount(1, $oframes);

        $oframe = $oframes[0];
        $this->assertEquals(1098, $oframe->getPts());
        $this->assertEquals(new Fraction(1, 48000)(), $oframe->getTimeBase());
        $this->assertEquals(48000, $oframe->getSampleRate());
        $this->assertEquals(1114, $oframe->getSamples());

        $oframes = $resampler->resample(null);
        $this->assertCount(1, $oframes);

        $oframe = $oframes[0];
        $this->assertEquals(1114 + 1098, $oframe->getPts());
        $this->assertEquals(new Fraction(1, 48000)(), $oframe->getTimeBase());
        $this->assertEquals(48000, $oframe->getSampleRate());
        $this->assertEquals(18, $oframe->getSamples());
    }

    public function testPtsAssertionNewRateFltp()
    {
        $resampler = new AudioResampler("fltp", "mono", 8000, 1024);

        $iframe = new AudioFrame("s16", "mono", 1024);
        $iframe->setSampleRate(8000);
        $iframe->setTimeBase(1, 1000);
        $iframe->setPts(0);

        $oframes = $resampler->resample($iframe);
        $this->assertCount(1, $oframes);

        $oframe = $oframes[0];
        $this->assertEquals(0, $oframe->getPts());
        $this->assertEquals(new Fraction(1, 8000)(), $oframe->getTimeBase());
        $this->assertEquals(8000, $oframe->getSampleRate());
        $this->assertEquals(1024, $oframe->getSamples());

        $iframe = new AudioFrame("s16", "mono", 1024);
        $iframe->setSampleRate(8000);
        $iframe->setTimeBase(1, 1000);
        $iframe->setPts(8192);

        $oframes = $resampler->resample($iframe);
        $this->assertCount(1, $oframes);

        $oframe = $oframes[0];
        $this->assertEquals(65536, $oframe->getPts());
        $this->assertEquals(new Fraction(1, 8000)(), $oframe->getTimeBase());
        $this->assertEquals(8000, $oframe->getSampleRate());
        $this->assertEquals(1024, $oframe->getSamples());

        // flush
        $oframes = $resampler->resample(null);
        $this->assertCount(0, $oframes);
    }

    public function testPtsMissingTimeBase()
    {
        $resampler = new AudioResampler("s16", "mono", 44100);

        $iframe = new AudioFrame("s16", "stereo", 1024);
        $iframe->setSampleRate(48000);
        $iframe->setPts(0);

        $oframes = $resampler->resample($iframe);
        $this->assertCount(1, $oframes);

        $oframe = $oframes[0];
        $this->assertEquals(0, $oframe->getPts());
        $this->assertEquals(new Fraction(1, 44100)(), $oframe->getTimeBase());
        $this->assertEquals(44100, $oframe->getSampleRate());

        // flush
        $oframes = $resampler->resample(null);
        $this->assertCount(1, $oframes);

        $oframe = $oframes[0];
        $this->assertEquals(925, $oframe->getPts());
        $this->assertEquals(new Fraction(1, 44100)(), $oframe->getTimeBase());
        $this->assertEquals(44100, $oframe->getSampleRate());
        $this->assertEquals(16, $oframe->getSamples());
    }

    public function testMismatchedInput()
    {
        $resampler = new AudioResampler("s16", "mono", 44100);

        // resample one frame
        $iframe = new AudioFrame("s16", "stereo", 1024);
        $iframe->setSampleRate(48000);
        $resampler->resample($iframe);

        // resample another frame with a sample format
        $iframe = new AudioFrame("s16", "mono", 1024);
        $iframe->setSampleRate(48000);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Frame does not match AudioResampler setup.");
        $resampler->resample($iframe);
    }

    private function assertFrame(AudioFrame $frame, AudioFrame $frame2)
    {
        $this->assertEquals($frame->getSampleRate(), $frame2->getSampleRate());
        $this->assertEquals($frame->getSamples(), $frame2->getSamples());
        $this->assertEquals($frame->getPts(), $frame2->getPts());
    }
}
