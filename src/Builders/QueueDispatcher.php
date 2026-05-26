<?php

namespace Dunn\LaravelOcr\Builders;

use Dunn\LaravelOcr\Jobs\PerformOcrJob;
use Dunn\LaravelOcr\Support\BuilderState;
use Illuminate\Foundation\Bus\PendingDispatch;

final class QueueDispatcher
{
    public function __construct(
        private readonly BuilderState $state,
        private readonly ?string $queueOverride = null,
    ) {
        if ($queueOverride !== null && trim($queueOverride) === '') {
            throw new \InvalidArgumentException("Queue name must be a non-empty string; got '{$queueOverride}'");
        }
    }

    public function dispatch(): PendingDispatch
    {
        $payload = $this->state->toPayload();
        $this->assertScalarPayload($payload);

        $payload['queueName'] = $this->queueOverride;

        $job = new PerformOcrJob($payload);

        $connection = config('ocr.queue.connection');
        $queue = $this->queueOverride ?? config('ocr.queue.name');

        if ($connection) {
            $job->onConnection($connection);
        }
        if ($queue) {
            $job->onQueue($queue);
        }

        return dispatch($job);
    }

    /** @param array<string|int, mixed> $data */
    private function assertScalarPayload(array $data, string $path = ''): void
    {
        foreach ($data as $key => $value) {
            $currentPath = $path === '' ? (string) $key : "{$path}.{$key}";
            if (is_array($value)) {
                $this->assertScalarPayload($value, $currentPath);
            } elseif (! is_scalar($value) && $value !== null) {
                $type = get_debug_type($value);
                throw new \InvalidArgumentException(
                    "Job payload must contain only scalars; encountered '{$type}' at path '{$currentPath}'"
                );
            }
        }
    }
}
