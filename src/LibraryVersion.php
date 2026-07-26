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

use Webrtc\AVCodec\Exception\AvCodecException;

/**
 * Checks that a loaded FFmpeg shared library matches the headers this package binds against.
 *
 * The headers under src/libav/include describe one specific major version of FFmpeg. Binding
 * them against a different major means every struct offset is wrong, which does not fail
 * loudly: it silently corrupts the heap the first time a frame or context is written. The
 * major version therefore has to match exactly, not merely be "new enough".
 */
final class LibraryVersion
{
    /**
     * Extract the major version from what av_version_info() reports.
     *
     * FFmpeg reports either a release ("n7.1.1", "7.1.1") or a git description
     * ("N-109103-g1c0b0f2b1a"), the latter carrying no usable version number.
     *
     * @param string $info The value returned by av_version_info().
     * @return int|null The major version, or null if it could not be determined.
     */
    public static function major(string $info): ?int
    {
        return preg_match('/^n?(\d+)\./', $info, $m) ? (int) $m[1] : null;
    }

    /**
     * Assert that a loaded library matches the headers, or explain why it does not.
     *
     * @param string $info The value returned by av_version_info().
     * @param int $supported The major version the bundled headers describe.
     * @param string $library The library being loaded, for the error message.
     * @return void
     * @throws AvCodecException If the version does not match or cannot be determined.
     */
    public static function assertSupported(string $info, int $supported, string $library): void
    {
        $major = self::major($info);

        if ($major === $supported) {
            return;
        }

        throw new AvCodecException(sprintf(
            $major === null
                ? "%s reports the unrecognised version \"%s\", so it cannot be checked against the "
                    . "FFmpeg %d headers this package binds against. Set the library path explicitly "
                    . "to an FFmpeg %d build."
                : "%s is FFmpeg %s, but this package binds against the FFmpeg %d headers. Binding "
                    . "mismatched majors corrupts memory rather than failing cleanly, so loading is "
                    . "refused. Install FFmpeg %d, or leave the FFI extension disabled to play back "
                    . "pre-encoded media without transcoding.",
            $library,
            $major === null ? $info : (string) $major,
            $supported,
            $supported
        ));
    }
}
