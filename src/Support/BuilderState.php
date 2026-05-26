<?php

namespace Dunn\LaravelOcr\Support;

use Dunn\LaravelOcr\Exceptions\UnsupportedLanguageException;

final class BuilderState
{
    /**
     * @param  list<string>  $languages
     * @param  list<int>|null  $pages
     */
    public function __construct(
        public readonly string $kind,
        public readonly string $source,
        public readonly ?string $disk = null,
        public readonly array $languages = [],
        public readonly int $psm = 3,
        public readonly int $oem = 3,
        public readonly int $timeout = 120,
        public readonly ?string $tessdataPath = null,
        public readonly ?int $dpi = null,
        public readonly ?array $pages = null,
    ) {}

    /** @param array<string, mixed> $changes */
    public function with(array $changes): self
    {
        return new self(
            kind: $changes['kind'] ?? $this->kind,
            source: $changes['source'] ?? $this->source,
            disk: array_key_exists('disk', $changes) ? $changes['disk'] : $this->disk,
            languages: $changes['languages'] ?? $this->languages,
            psm: $changes['psm'] ?? $this->psm,
            oem: $changes['oem'] ?? $this->oem,
            timeout: $changes['timeout'] ?? $this->timeout,
            tessdataPath: array_key_exists('tessdataPath', $changes) ? $changes['tessdataPath'] : $this->tessdataPath,
            dpi: array_key_exists('dpi', $changes) ? $changes['dpi'] : $this->dpi,
            pages: array_key_exists('pages', $changes) ? $changes['pages'] : $this->pages,
        );
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'kind' => $this->kind,
            'source' => $this->source,
            'disk' => $this->disk,
            'languages' => $this->languages,
            'psm' => $this->psm,
            'oem' => $this->oem,
            'timeout' => $this->timeout,
            'tessdataPath' => $this->tessdataPath,
            'dpi' => $this->dpi,
            'pages' => $this->pages,
        ];
    }

    /** @param array<string, mixed> $p */
    public static function fromPayload(array $p): self
    {
        $kind = $p['kind'] ?? 'image';
        $source = $p['source'] ?? '';

        if (strlen($source) < 1 || strlen($source) > 4096) {
            throw new \InvalidArgumentException('Source path must be 1..4096 characters');
        }

        $languages = $p['languages'] ?? [];
        foreach ($languages as $code) {
            if (! is_string($code)) {
                throw new UnsupportedLanguageException('Invalid language code type: '.get_debug_type($code));
            }

            if (! self::isLanguageCode($code)) {
                throw new UnsupportedLanguageException("Invalid language code: '{$code}'");
            }
        }

        $psm = $p['psm'] ?? 3;
        if (! is_int($psm) || $psm < 0 || $psm > 13) {
            throw new \InvalidArgumentException("PSM must be 0..13; got {$psm}");
        }

        $oem = $p['oem'] ?? 3;
        if (! is_int($oem) || $oem < 0 || $oem > 3) {
            throw new \InvalidArgumentException("OEM must be 0..3; got {$oem}");
        }

        $timeout = $p['timeout'] ?? 120;
        if (! is_int($timeout) || $timeout < 1 || $timeout > 3600) {
            throw new \InvalidArgumentException("Timeout must be 1..3600; got {$timeout}");
        }

        $dpi = $p['dpi'] ?? null;
        if ($dpi !== null && (! is_int($dpi) || $dpi < 72 || $dpi > 1200)) {
            throw new \InvalidArgumentException("DPI must be 72..1200; got {$dpi}");
        }

        $pages = $p['pages'] ?? null;
        if ($pages !== null) {
            if (! is_array($pages) || $pages === []) {
                throw new \InvalidArgumentException('Pages must be a non-empty array of positive integers');
            }
            foreach ($pages as $page) {
                if (! is_int($page) || $page < 1) {
                    throw new \InvalidArgumentException("Page numbers must be positive integers; got {$page}");
                }
            }
            if (count($pages) !== count(array_unique($pages))) {
                throw new \InvalidArgumentException('Pages must not contain duplicates');
            }
        }

        return new self(
            kind: $kind,
            source: $source,
            disk: $p['disk'] ?? null,
            languages: $languages,
            psm: $psm,
            oem: $oem,
            timeout: $timeout,
            tessdataPath: $p['tessdataPath'] ?? null,
            dpi: $dpi,
            pages: $pages !== null ? array_values($pages) : null,
        );
    }

    public static function isLanguageCode(string $code): bool
    {
        return preg_match('/^[a-z]{3}(_[A-Za-z]+)?$/', $code) === 1;
    }
}
