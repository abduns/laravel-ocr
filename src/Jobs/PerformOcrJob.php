<?php

namespace Dunn\LaravelOcr\Jobs;

use Dunn\LaravelOcr\OcrManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class PerformOcrJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @param array<string, mixed> $payload */
    public function __construct(public readonly array $payload) {}

    /** @return string|array<int, string> */
    public function handle(OcrManager $manager): string|array
    {
        $kind = $this->payload['kind'] ?? 'image';

        if ($kind === 'image') {
            return $manager->rebuildImageBuilder($this->payload)->run();
        }

        return $manager->rebuildPdfBuilder($this->payload)->runAll();
    }
}
