<?php

namespace Dunn\LaravelOcr\Events;

final class OcrStarted
{
    public function __construct(
        public readonly string $jobId,
        public readonly string $sourceId,
    ) {}
}
