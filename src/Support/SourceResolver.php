<?php

namespace Dunn\LaravelOcr\Support;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Filesystem\FilesystemManager;

final class SourceResolver
{
    public function __construct(
        private readonly FilesystemManager $disks,
        private readonly TempPathFactory $tempPaths,
    ) {}

    /**
     * @return array{absolutePath: string, isStreamed: bool, cleanup: \Closure}
     */
    public function resolve(BuilderState $state, string $extensionHint): array
    {
        if ($state->disk === null) {
            return [
                'absolutePath' => $state->source,
                'isStreamed' => false,
                'cleanup' => static function (): void {},
            ];
        }

        /** @var FilesystemAdapter $disk */
        $disk = $this->disks->disk($state->disk);
        $config = $disk->getConfig();
        $driver = $config['driver'] ?? null;

        if (! $disk->exists($state->source)) {
            throw new FileNotFoundException(
                "File [{$state->disk}://{$state->source}] not found"
            );
        }

        if ($driver === 'local') {
            return [
                'absolutePath' => $disk->path($state->source),
                'isStreamed' => false,
                'cleanup' => static function (): void {},
            ];
        }

        // Non-local disk: stream to temp
        $tempRelPath = $this->tempPaths->unique($extensionHint, 'src');
        /** @var FilesystemAdapter $tempDisk */
        $tempDisk = $this->disks->disk($this->tempPaths->getDiskName());
        $tempAbsPath = $tempDisk->path($tempRelPath);

        // Ensure directory exists
        $dir = dirname($tempAbsPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $stream = $disk->readStream($state->source);
        if ($stream === null) {
            throw new FileNotFoundException(
                "File [{$state->disk}://{$state->source}] not found"
            );
        }

        $dst = fopen($tempAbsPath, 'wb');
        if ($dst === false) {
            fclose($stream);
            throw new \RuntimeException("Could not open temp file for writing: {$tempAbsPath}");
        }

        try {
            stream_copy_to_stream($stream, $dst);
            fclose($dst);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        $cleanupPath = $tempAbsPath;
        $cleanup = static function () use (&$cleanupPath): void {
            if ($cleanupPath !== null && is_file($cleanupPath)) {
                @unlink($cleanupPath);
                $cleanupPath = null;
            }
        };

        return [
            'absolutePath' => $tempAbsPath,
            'isStreamed' => true,
            'cleanup' => $cleanup,
        ];
    }
}
