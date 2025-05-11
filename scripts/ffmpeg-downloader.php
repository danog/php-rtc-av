<?php

function detectPlatform(): string
{
    $os = strtolower(PHP_OS);
    $arch = php_uname('m');

    if (str_contains($os, 'darwin')) {
        return str_contains($arch, 'arm64') ? 'macosx_arm64' : 'macosx_x86_64';
    }

    if (str_contains($os, 'linux')) {
        // Check for musl (Alpine Linux)
        $ldd = @shell_exec('ldd --version 2>&1');
        if (str_contains($ldd ?? '', 'musl')) {
            return match ($arch) {
                'aarch64' => 'musllinux_aarch64',
                'i686' => 'musllinux_i686',
                'x86_64' => 'musllinux_x86_64',
                default => throw new RuntimeException("Unsupported architecture: $arch")
            };
        }

        return match ($arch) {
            'aarch64' => 'manylinux_aarch64',
            'i686' => 'manylinux_i686',
            'x86_64' => 'manylinux_x86_64',
            default => throw new RuntimeException("Unsupported architecture: $arch")
        };
    }

    if (str_contains($os, 'win')) {
        return 'win_amd64';
    }

    throw new RuntimeException("Unsupported operating system: $os");
}

function coloredProgressBar($current, $total, $barLength = 30) {
    static $startTime = null;
    static $lastTime = null;
    static $lastProgress = 0;

    // Initialize timing on first call
    if ($startTime === null) {
        $startTime = time();
        $lastTime = $startTime;
        $lastProgress = $current;
        return;
    }

    $progress = ($current / $total);
    $filled = floor($barLength * $progress);
    $empty = $barLength - $filled;

    // ANSI color codes
    $green = "\033[32m";
    $yellow = "\033[33m";
    $reset = "\033[0m";

    $bar = $green . str_repeat('▓', $filled);
    if ($filled < $barLength) {
        $bar .= $yellow . '░' . str_repeat(' ', $empty - 1);
    }
    $bar .= $reset;

    $percent = number_format($progress * 100, 1);

    // Convert to megabytes with 2 decimal places
    $currentMB = number_format($current / (1024 * 1024), 2);
    $totalMB = number_format($total / (1024 * 1024), 2);
    $status = str_pad("$currentMB/$totalMB MB", 15, ' ', STR_PAD_LEFT);

    // Calculate time remaining
    $now = time();
    $timeElapsed = $now - $startTime;

    if ($current > 0 && $current < $total) {
        $timeRemaining = ($timeElapsed / $current) * ($total - $current);

        // Format time remaining (HH:MM:SS or MM:SS)
        if ($timeRemaining > 3600) {
            $timeStr = gmdate("H:i:s", $timeRemaining);
        } else {
            $timeStr = gmdate("i:s", $timeRemaining);
        }
    } else {
        $timeStr = "00:00";
    }

    echo "\r[$bar] $percent% $status \t ETA: $timeStr";
    if ($current === $total) {
        $timeElapsedStr = gmdate("H:i:s", $timeElapsed);
        echo "\r[$bar] $percent% $status \t Time: $timeElapsedStr" . str_repeat(' ', 10) . PHP_EOL;
    }

    $lastTime = $now;
    $lastProgress = $current;
}
function downloadWithProgress(string $url, string $destination): void
{
    $progress = null;
    $totalBytes = 0;
    $ctx = stream_context_create([], ['notification' => function (
        $notificationCode,
        $severity,
        $message,
        $messageCode,
        $bytesTransferred,
        $bytesMax
    ) use (&$progress, &$totalBytes) {
        if ($bytesMax > 0) {
            $totalBytes = $bytesMax;
        }
        if ($notificationCode === STREAM_NOTIFY_PROGRESS) {
            if ($totalBytes > 0) {
                coloredProgressBar($bytesTransferred, $totalBytes);
            }
        }
    }]);

    $data = @file_get_contents($url, false, $ctx);
    if ($data === false) {
        throw new RuntimeException("Failed to download from $url");
    }

    if (file_put_contents($destination, $data) === false) {
        throw new RuntimeException("Failed to write to $destination");
    }
}

function extractTarGz(string $archivePath, string $extractTo): void
{
    if (!is_dir($extractTo)) {
        mkdir($extractTo, 0755, true);
    }

    if (stripos(PHP_OS, 'WIN') === 0) {
        $phar = new PharData($archivePath);
        $phar->extractTo($extractTo, null, true);
    } else {
        $escapedArchive = escapeshellarg($archivePath);
        $escapedTarget = escapeshellarg($extractTo);
        $cmd = "tar -xzf $escapedArchive -C $escapedTarget";

        exec($cmd, $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException("Failed to extract archive using tar (exit code $exitCode).");
        }
    }
}

function copyLibFiles(string $source, string $destination): void
{
    if (!is_dir($source)) {
        throw new RuntimeException("Source directory $source does not exist");
    }

    if (!is_dir($destination)) {
        mkdir($destination, 0755, true);
    }

    $dir = new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS);
    $iterator = new RecursiveIteratorIterator($dir, RecursiveIteratorIterator::SELF_FIRST);

    foreach ($iterator as $item) {
        $target = $destination . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
        if ($item->isDir()) {
            if (!is_dir($target)) {
                mkdir($target, 0755);
            }
        } else {
            copy($item->getPathname(), $target);
        }
    }
}

function deleteDirectory(string $dir): bool {
    if (!file_exists($dir)) {
        return false;
    }

    if (!is_dir($dir)) {
        return unlink($dir);
    }

    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $path = $dir . DIRECTORY_SEPARATOR . $item;

        if (is_dir($path)) {
            deleteDirectory($path);
        } else {
            unlink($path);
        }
    }

    return rmdir($dir);
}


try {
    $baseUrl = "https://github.com/PHP-WebRTC/ffmpeg-builder/releases/download";
    $ffmpegVersion = "v7.1.1";
    $platform = detectPlatform();
    $fileName = "ffmpeg-$platform.tar.gz";
    $url = "$baseUrl/$ffmpegVersion/$fileName";

    echo "Detected platform: $platform\n";
    echo "Downloading FFmpeg libraries...\n";

    $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ffmpeg-download';
    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0755, true);
    }

    $archivePath = $tempDir . DIRECTORY_SEPARATOR . $fileName;
    downloadWithProgress($url, $archivePath);

    echo "Extracting archive...\n";
    $extractPath = $tempDir . DIRECTORY_SEPARATOR . 'extracted';
    extractTarGz($archivePath, $extractPath);

    echo "Copying libraries...\n";
    $libSource = $extractPath . DIRECTORY_SEPARATOR . 'lib';
    $libDestination = __DIR__ . '/../ffmpeg/lib/';
    copyLibFiles($libSource, $libDestination);

    echo "Cleaning up...\n";
    unlink($archivePath);
    deleteDirectory($extractPath);

    echo "FFmpeg libraries successfully installed!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}