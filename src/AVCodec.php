<?php

/**
 * This file is part of the PHP WebRTC package.
 *
 * (c) Amin Yazdanpanah <https://www.aminyazdanpanah.com/#contact>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webrtc\AVCodec;

use FFI;
use FFI\Exception as FFIException;
use Webrtc\AVCodec\Exception\AvCodecException;

/**
 * Class AVCodec
 *
 * This class provides methods to initialize the AVCodec library and handle
 * AVCodec-related operations.
 */
class AVCodec
{
    /**
     * The minimum supported major version of libavcodec.
     */
    private const int SUPPORTED_VERSION = 7;

    /**
     * The path to the AVCodec C header file.
     */
    private const string HEADER_FILE_PATH = __DIR__ . "/../ffmpeg/include/avcodec.h";

    /**
     * Initializes the AVCodec library.
     *
     * This method attempts to load the libavcodec shared library via FFI, verify its version,
     * and set the necessary constants. If initialization fails, a detailed exception is thrown.
     *
     * @param bool $debug Whether to enable debug logging for AVCodec.
     *
     * @return void
     *
     * @throws AvCodecException If the AVCodec library cannot be loaded or the version is unsupported.
     */
    public static function init(bool $debug = false): void
    {
        global $libAVCodec;

        if (!isset($libAVCodec)) {
            try {
                $lib = getenv("LIB_AVCODEC_PATH") ?: self::getLibPath();
                $libAVCodec = FFI::cdef(file_get_contents(self::HEADER_FILE_PATH), $lib);

                // Verify the loaded library version
                $version = $libAVCodec->av_version_info();

                if ($version < self::SUPPORTED_VERSION) {
                    throw new AvCodecException(sprintf(
                        "The library could not be initialized. Required version: %d or higher. Detected version: %d.",
                        self::SUPPORTED_VERSION,
                        $version
                    ));
                }

                self::setDefinition();

                if ($debug) {
                    $logCallback = function ($avcl, $level, $fmt, $args) {
                        echo "[FFmpeg] $fmt\n";
                    };
                    $libAVCodec->av_log_set_callback($logCallback);
                } else {
                    $libAVCodec->av_log_set_level(-8);
                }

            } catch (FFIException $e) {
                $os = PHP_OS_FAMILY;
                $installHint = match ($os) {
                    'Windows' => <<<EOT
Download and install FFmpeg (version 7.1.1) with development files (DLLs) from https://ffmpeg.org/download.html.
Make sure avcodec-*.dll is in your PATH or specify the LIB_AVCODEC_PATH environment variable.
EOT,
                    'Darwin' => <<<EOT
Install FFmpeg with development headers on macOS:

    brew install ffmpeg

Ensure it is version 7.1.1. You may need to use:

    brew upgrade ffmpeg

If your Homebrew version is too old, you might need to tap a custom formula or build from source.
EOT,
                    'Linux' => <<<EOT
Install FFmpeg development packages on Linux:

For Debian/Ubuntu:

    sudo apt update
    
    sudo apt install -y \
    autoconf automake build-essential cmake git libtool \
    pkg-config texinfo zlib1g-dev libx264-dev libx265-dev \
    libvpx-dev libfdk-aac-dev libmp3lame-dev libopus-dev \
    nasm
    
    git clone https://github.com/FFmpeg/FFmpeg.git
    
    cd FFmpeg
    
    git checkout n7.1.1
    
    ./configure --enable-shared --enable-gpl --enable-nonfree \
    --enable-libx264 --enable-libx265 --enable-libvpx \
    --enable-libfdk-aac --enable-libmp3lame --enable-libopus
    
    make -j$(nproc)
    
    sudo make install
    
    sudo ldconfig

For Fedora/RHEL:

    sudo dnf update -y

    sudo dnf install -y \
    autoconf automake cmake git libtool make gcc gcc-c++ \
    pkgconfig texinfo zlib-devel x264-devel x265-devel \
    libvpx-devel fdk-aac-devel lame-devel opus-devel \
    nasm
    
    git clone https://github.com/FFmpeg/FFmpeg.git
    
    cd FFmpeg
    
    git checkout n7.1.1
    
    ./configure --enable-shared --enable-gpl --enable-nonfree \
    --enable-libx264 --enable-libx265 --enable-libvpx \
    --enable-libfdk-aac --enable-libmp3lame --enable-libopus
    
    make -j$(nproc)
    
    sudo make install
    
    sudo ldconfig

Make sure the installed version is 7.1.1. If your package manager provides an older version, consider building FFmpeg manually from source.
EOT,
                    default => "Please install FFmpeg (version 7.1.1) and ensure the development libraries (headers and shared libs) are available on your system. See https://ffmpeg.org/download.html for instructions."
                };

                throw new AvCodecException(sprintf(
                    "Couldn't load AvCodec library: %s\n\nInstallation instructions:\n%s",
                    $e->getMessage(),
                    $installHint
                ), $e->getCode(), $e);
            }
        }
    }

    /**
     * Defines the necessary constants related to AVCodec if not already defined.
     *
     * @return void
     */
    private static function setDefinition(): void
    {
        define("EAGAIN", 11);
        define("AVERROR_EOF", -541478725);
        define("AV_CODEC_CAP_VARIABLE_FRAME_SIZE", (1 << 16));

        /**
         * AV_ROUND constants for rounding operations
         */
        define('AV_ROUND_ZERO', 0);       // Round toward zero
        define('AV_ROUND_INF', 1);        // Round away from zero
        define('AV_ROUND_DOWN', 2);       // Round toward negative infinity
        define('AV_ROUND_UP', 3);         // Round toward positive infinity
        define('AV_ROUND_NEAR_INF', 5);   // Round to nearest and ties away from zero
        define('AV_ROUND_PASS_MINMAX', 8192); // Flag to pass INT64_MIN/MAX through unchanged

        /**
         * Integer limit constants
         */
        define('INT64_MIN', -9223372036854775808);  // Minimum value for 64-bit signed integer
        define('INT64_MAX', 9223372036854775807);   // Maximum value for 64-bit signed integer
        define('UINT64_C', '0xFFFFFFFFFFFFFFFF');   // Unsigned 64-bit integer constant
    }

    /**
     * Determines and returns the appropriate libavcodec shared library path.
     *
     * This method tries common library locations based on the operating system
     * and returns the most probable path to the libavcodec shared library.
     * If no common path is found, it returns a generic library name.
     *
     * @return string The path or name of the libavcodec shared library.
     */
    private static function getLibPath(): string
    {
        $os = PHP_OS_FAMILY;

        if ($os === 'Windows') {
            $candidates = [
                'avcodec-61.dll',
                'avcodec-60.dll',
                'avcodec.dll',
            ];
        } elseif ($os === 'Darwin') { // macOS
            $candidates = [
                '/usr/local/lib/libavcodec.dylib',
                '/opt/homebrew/lib/libavcodec.dylib',
                'libavcodec.dylib',
            ];
        } elseif ($os === 'Linux') {
            $candidates = [
                '/usr/lib/x86_64-linux-gnu/libavcodec.so',
                '/usr/local/lib/libavcodec.so',
                'libavcodec.so',
            ];
        } else {
            $candidates = [
                'libavcodec',
            ];
        }

        foreach ($candidates as $candidate) {
            if (is_file($candidate) || @file_exists($candidate)) {
                return $candidate;
            }
        }

        return match ($os) {
            'Windows' => 'avcodec.dll',
            'Darwin' => 'libavcodec.dylib',
            'Linux' => 'libavcodec.so',
            default => 'libavcodec',
        };
    }
}
