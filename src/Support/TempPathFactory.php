<?php

namespace Dunn\LaravelOcr\Support;

final class TempPathFactory
{
    public function __construct(
        private readonly string $diskName,
        private readonly string $tempPath,
    ) {}

    public function unique(string $extension, ?string $prefix = null): string
    {
        $prefix = $prefix ?? 'ocr';
        $microhex = dechex((int) (microtime(true) * 1e6));
        $rand8hex = bin2hex(random_bytes(8));

        return rtrim($this->tempPath, '/')."/{$prefix}-{$microhex}-{$rand8hex}.{$extension}";
    }

    public function getDiskName(): string
    {
        return $this->diskName;
    }
}
