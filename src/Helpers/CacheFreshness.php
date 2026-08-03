<?php

namespace VatsimData\Helpers;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\Cache;

/** @internal */
final class CacheFreshness
{
    public static function record(string $cacheKey, int $ttl): void
    {
        Cache::put(
            $cacheKey.'.fetched-at',
            (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DateTimeImmutable::ATOM),
            $ttl,
        );
    }

    public static function get(string $cacheKey): ?DateTimeImmutable
    {
        $value = Cache::get($cacheKey.'.fetched-at');

        if (! is_string($value)) {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
