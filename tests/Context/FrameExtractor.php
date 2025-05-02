<?php

namespace Tests\Webrtc\AVCodec\Context;

use FFI;
use Webrtc\AVCodec\AVFormat;
use Webrtc\AVCodec\Exception\AvCodecException;
use Webrtc\AVCodec\Frame\AudioFrame;
use Webrtc\AVCodec\Frame\VideoFrame;
use Webrtc\Mixin\SharedLibraryInterface;

class FrameExtractor implements SharedLibraryInterface
{
    private FFI $libAVCodec;
    private FFI $libAVFormat;

    public function __construct()
    {
        AVFormat::init();
        $this->initiateSharedLibrary();
    }

    /**
     * Extract frames from the given media file.
     *
     * @param string $filePath The path to the media file.
     * @return array<VideoFrame|AudioFrame> An array of VideoFrame or AudioFrame objects.
     * @throws AvCodecException If an error occurs during the extraction process.
     */
    public function extractFramesFromMediaFile(string $filePath): array
    {
        $formatContext = $this->libAVFormat->avformat_alloc_context();

        if ($this->libAVFormat->avformat_open_input(FFI::addr($formatContext), $filePath, null, null) < 0) {
            throw new AvCodecException("Unable to open file.");
        }

        if ($this->libAVFormat->avformat_find_stream_info($formatContext, null) < 0) {
            throw new AvCodecException("Unable to find stream info.");
        }

        $frames = [];

        for ($i = 0; $i < $formatContext->nb_streams; $i++) {
            $stream = $formatContext->streams[$i];
            $mediaType = $stream->codecpar->codec_type;

            if ($mediaType === $this->libAVCodec->AVMEDIA_TYPE_VIDEO || $mediaType === $this->libAVCodec->AVMEDIA_TYPE_AUDIO) {
                $decoder = $this->libAVCodec->avcodec_find_decoder($stream->codecpar->codec_id);
                $codecContext = $this->libAVCodec->avcodec_alloc_context3($decoder);
                $castedCodeCPar = $this->libAVCodec->cast("AVCodecParameters*", $stream->codecpar);
                $this->libAVCodec->avcodec_parameters_to_context($codecContext, $castedCodeCPar);

                if ($this->libAVCodec->avcodec_open2($codecContext, $decoder, null) < 0) {
                    throw new AvCodecException("Unable to open codec.");
                }

                $packet = $this->libAVCodec->av_packet_alloc();
                $frame = $this->libAVCodec->av_frame_alloc();

                $castedPacket = $this->libAVFormat->cast("AVPacket*", $packet);
                while ($this->libAVFormat->av_read_frame($formatContext, $castedPacket) >= 0) {
                    if ($packet->stream_index == $i) {
                        $ret = $this->libAVCodec->avcodec_send_packet($codecContext, $packet);
                        if ($ret < 0) {
                            throw new AvCodecException("Error sending packet for decoding.");
                        }

                        while ($this->libAVCodec->avcodec_receive_frame($codecContext, $frame) == 0) {
                            if ($mediaType === $this->libAVCodec->AVMEDIA_TYPE_VIDEO) {
                                $frames[] = $this->createVideoFrameFromDecodedData($frame);
                            } elseif ($mediaType === $this->libAVCodec->AVMEDIA_TYPE_AUDIO) {
                                $frames[] = $this->createAudioFrameFromDecodedData($frame);
                            }
                        }
                    }

                    $this->libAVCodec->av_packet_unref($packet);
                }

                // Clean up resources
                $this->libAVCodec->av_frame_free(FFI::addr($frame));
                $this->libAVCodec->av_packet_free(FFI::addr($packet));
                $this->libAVCodec->avcodec_free_context(FFI::addr($codecContext));
            }
        }

        $this->libAVFormat->avformat_close_input(FFI::addr($formatContext));

        return $frames;
    }

    /**
     * Create a VideoFrame from the decoded frame data.
     *
     * @param FFI\CData $frame The decoded frame.
     * @return VideoFrame The created VideoFrame.
     */
    private function createVideoFrameFromDecodedData(FFI\CData $frame): VideoFrame
    {
        $videoFrame = new VideoFrame($frame->width, $frame->height, $frame->format);
        $videoFrame->setPts($frame->pts);
        $videoFrame->setTimeBase(1, 25); // Assuming time base is 1/25th

        foreach ($videoFrame->planes() as $key => $plane) {
            $plane->putData($this->extractPlaneData($frame, $key));
        }

        return $videoFrame;
    }

    /**
     * Create an AudioFrame from the decoded frame data.
     *
     * @param FFI\CData $frame The decoded audio frame.
     * @return AudioFrame The created AudioFrame.
     */
    private function createAudioFrameFromDecodedData(FFI\CData $frame): AudioFrame
    {
        $channels = $frame->ch_layout->nb_channels->cdata ?? 1; // Default to mono
        $sampleRate = $frame->sample_rate;
        $nbSamples = $frame->nb_samples;
        $pts = $frame->pts;
        $format = $frame->format;
        $timeBase = $frame->time_base;

        $audioLayout = $channels === 2 ? "stereo" : "mono";

        $newFrame = new AudioFrame("s16", $audioLayout, $nbSamples);
        $newFrame->setSampleRate($sampleRate);
        $newFrame->setPts($pts);
        $newFrame->setTimeBase(1, 44100);
        $newFrame->putData(FFI::string($frame->data[0], $nbSamples * 2));

        return $newFrame;
    }

    /**
     * Extract the pixel data for a specific plane from a decoded frame.
     *
     * @param FFI\CData $frame The decoded frame.
     * @param int $planeIndex The index of the plane to extract.
     * @return string The extracted plane data.
     */
    private function extractPlaneData(FFI\CData $frame, int $planeIndex): string
    {
        $linesize = $frame->linesize[$planeIndex];
        $height = $frame->height / ($planeIndex == 0 ? 1 : 2);
        return FFI::string($frame->data[$planeIndex], $linesize * $height);
    }

    public function initiateSharedLibrary(): void
    {
        global $libAVCodec, $libAVFormat;

        if ($libAVCodec instanceof FFI && $libAVFormat instanceof FFI) {
            $this->libAVCodec = $libAVCodec;
            $this->libAVFormat = $libAVFormat;
        }
    }
}
