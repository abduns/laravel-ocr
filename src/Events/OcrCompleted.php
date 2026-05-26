<?php

namespace Dunn\LaravelOcr\Events;

final class OcrCompleted
{
    public function __construct(
        public readonly string $jobId,
        public readonly string $text,
    ) {}
}
