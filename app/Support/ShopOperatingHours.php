<?php

namespace App\Support;

use Carbon\CarbonImmutable;

final class ShopOperatingHours
{
    public const TIMEZONE = 'Asia/Manila';
    public const DEFAULT_OPEN = '06:00';
    public const DEFAULT_CLOSE = '20:00';

    public static function details(?CarbonImmutable $now = null): array
    {
        $savedOpening = Settings::get('shop_opening_time');
        $savedClosing = Settings::get('shop_closing_time');
        $configured = filled($savedOpening) && filled($savedClosing);
        $opening = (string) ($savedOpening ?? self::DEFAULT_OPEN);
        $closing = (string) ($savedClosing ?? self::DEFAULT_CLOSE);
        $now ??= CarbonImmutable::now(self::TIMEZONE);
        $time = $now->setTimezone(self::TIMEZONE)->format('H:i');
        $open = !$configured || ($opening < $closing
            ? $time >= $opening && $time < $closing
            : ($opening === $closing || $time >= $opening || $time < $closing));

        return [
            'opening_time' => $opening,
            'closing_time' => $closing,
            'timezone' => self::TIMEZONE,
            'is_configured' => $configured,
            'is_open' => $open,
            'status' => $open ? 'open' : 'closed',
        ];
    }

    public static function isOpen(?CarbonImmutable $now = null): bool
    {
        return self::details($now)['is_open'];
    }
}
