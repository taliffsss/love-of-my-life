<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Dates are stored and calculated in UTC; people read them in the product's own time zone (saas.display_timezone, default
 * Asia/Manila, UTC+8). Only what is shown changes: no stored value, expiry or comparison does.
 */
final class Time
{
    public static function zone(): \DateTimeZone
    {
        $name = (string) config('saas.display_timezone', 'Asia/Manila');
        try {
            return new \DateTimeZone($name);
        } catch (\Exception) {
            return new \DateTimeZone('Asia/Manila');
        }
    }

    /** The moment in the display time zone, formatted ("M j, Y g:i A"); '' for none. */
    public static function show(?\DateTimeInterface $moment, string $format = 'M j, Y'): string
    {
        return $moment === null ? '' : \DateTimeImmutable::createFromInterface($moment)->setTimezone(self::zone())->format($format);
    }

    /** "Manila (UTC+8)": what to print beside a time. */
    public static function label(): string
    {
        $zone = self::zone();
        $city = str_replace('_', ' ', (string) substr(strrchr('/' . $zone->getName(), '/') ?: '', 1));
        $offset = (new \DateTimeImmutable('now', $zone))->format('P'); // +08:00
        [$h, $m] = explode(':', $offset);
        $hours = (int) $h;
        return $city . ' (UTC' . ($hours >= 0 ? '+' : '-') . abs($hours) . ($m !== '00' ? ':' . $m : '') . ')';
    }

    /** The UTC "Y-m-d H:i:s" at which this display-zone day starts, $daysAgo days before now. */
    public static function dayStartUtc(int $daysAgo, ?\DateTimeImmutable $now = null): string
    {
        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        return $now->setTimezone(self::zone())->modify("-{$daysAgo} days")->setTime(0, 0)->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
