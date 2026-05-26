<?php

namespace Dunn\LaravelOcr\Support;

final class ArgvBuilder
{
    /**
     * @return list<string>
     */
    public static function buildImage(string $binary, BuilderState $state, string $absoluteSourcePath, ?string $resolvedTessdataPath): array
    {
        $languages = self::dedupePreservingOrder($state->languages);
        $joined = implode('+', $languages);

        $argv = [
            $binary,
            $absoluteSourcePath,
            'stdout',
            '-l', $joined,
            '--psm', (string) $state->psm,
            '--oem', (string) $state->oem,
        ];

        if ($resolvedTessdataPath !== null && $resolvedTessdataPath !== '') {
            $argv[] = '--tessdata-dir';
            $argv[] = $resolvedTessdataPath;
        }

        return $argv;
    }

    /**
     * @param  list<string>  $langs
     * @return list<string>
     */
    public static function dedupePreservingOrder(array $langs): array
    {
        $out = [];
        $seen = [];
        foreach ($langs as $l) {
            if (! isset($seen[$l])) {
                $seen[$l] = true;
                $out[] = $l;
            }
        }

        return $out;
    }
}
