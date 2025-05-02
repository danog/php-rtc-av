<?php

namespace Tests\Webrtc\AVCodec\Filter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Webrtc\AVCodec\Audio\AudioLayout;
use Webrtc\AVCodec\AVCodec;
use Webrtc\AVCodec\AVFilter;
use Webrtc\AVCodec\Context\Dictionary;
use Webrtc\AVCodec\Data\Buffer;
use Webrtc\AVCodec\Data\VideoPlane;
use Webrtc\AVCodec\Filter\Filter;
use Webrtc\AVCodec\Filter\FilterContext;
use Webrtc\AVCodec\Filter\Graph;
use Webrtc\AVCodec\Format\AudioFormat;
use Webrtc\AVCodec\Format\VideoFormat;
use Webrtc\AVCodec\Format\VideoFormatComponent;
use Webrtc\AVCodec\Frame\AudioFrame;
use Webrtc\AVCodec\Frame\Frame;
use Webrtc\AVCodec\Frame\VideoFrame;
use Webrtc\AVCodec\SWScale;

#[UsesClass(AVCodec::class)]
#[UsesClass(AVFilter::class)]
#[UsesClass(AudioLayout::class)]
#[UsesClass(Dictionary::class)]
#[UsesClass(FilterContext::class)]
#[UsesClass(Graph::class)]
#[UsesClass(AudioFormat::class)]
#[UsesClass(AudioFrame::class)]
#[UsesClass(Frame::class)]
#[UsesClass(Buffer::class)]
#[UsesClass(VideoPlane::class)]
#[UsesClass(VideoFormat::class)]
#[UsesClass(VideoFormatComponent::class)]
#[UsesClass(VideoFrame::class)]
#[UsesClass(SWScale::class)]
#[CoversClass(Filter::class)]
class FilterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        AVFilter::init();
        AVCodec::init();
    }

    public function testFilterDescriptor()
    {
        $f = new Filter("testsrc");
        $this->assertEquals("testsrc", $f->getName());
        $this->assertEquals("Generate test pattern.", $f->getDescription());
        $this->assertFalse($f->hasDynamicInputs());
        $this->assertFalse($f->hasDynamicOutputs());
    }

    public function testDynamicFilterDescriptor()
    {
        $f = new Filter("split");
        $this->assertFalse($f->hasDynamicInputs());
        $this->assertTrue($f->hasDynamicOutputs());
    }

    public function testGeneratorGraph()
    {
        $graph = new Graph();
        $src = $graph->add(new Filter("testsrc"), args: [null]);
        $lutrgb = $graph->add(
            new Filter("lutrgb"),
            args: ["r=maxval+minval-val:g=maxval+minval-val:b=maxval+minval-val"],
            options: ["name" => "invert"]
        );
        $sink = $graph->add(new Filter("buffersink"), args: [null]);
        $src->linkTo($lutrgb);
        $lutrgb->linkTo($sink);

        $frame = $sink->pull();
        $this->assertInstanceOf(VideoFrame::class, $frame);
    }

    public function testAudioBufferResample()
    {
        $graph = new Graph();
        $aBuffer = $graph->add(new Filter("abuffer"), options: [
            "sample_fmt" => "fltp",
            "sample_rate" => 48000,
            "channel_layout" => "stereo",
            "time_base" => "1/48000"
        ]);
        $aFormat = $graph->add(new Filter("aformat"), args: ["sample_fmts=s16:sample_rates=44100:channel_layouts=stereo"]);
        $aBufferSink = $graph->add(new Filter("abuffersink"), args: [null]);
        $aBuffer->linkTo($aFormat);
        $aFormat->linkTo($aBufferSink);

        $graph->configure();

        $inFrame = $this->getSampleAudioFrame();
        $graph->push($inFrame);
        $outFrame = $graph->pull();

        $this->assertInstanceOf(AudioFrame::class, $outFrame);
        $this->assertEquals("s16", $outFrame->getFormat()->getName());
        $this->assertEquals("stereo", $outFrame->getLayout()->getName());
        $this->assertEquals(44100, $outFrame->getSampleRate());
    }

    private function getSampleAudioFrame(): AudioFrame
    {
        $frame = new AudioFrame("fltp", "stereo", 1024);
        $frame->setSampleRate(48000);
        $frame->setPts(0);

        for ($i = 0; $i < $frame->getLayout()->getNbChannels(); $i++) {
            $data = "";
            for ($j = 0; $j < 1024; $j++) {
                $sample = sin(2 * M_PI * $j * ($i + 1) / floatval(1024));
                $data .= pack("f", $sample);
            }
            $frame->putData($data, $i);
        }

        return $frame;
    }

}
