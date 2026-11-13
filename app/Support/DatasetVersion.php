<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * A token that changes whenever the participant list is imported, reset or wiped.
 * Every check-in device compares it on each sync and reloads its full list when it changes.
 */
class DatasetVersion
{
    private const CACHE_KEY = 'participants.dataset_version';

    public static function current(): string
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => Str::random(12));
    }

    public static function bump(): string
    {
        $version = Str::random(12);
        Cache::forever(self::CACHE_KEY, $version);

        return $version;
    }
}
