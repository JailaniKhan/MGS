<?php

use Carbon\CarbonInterface;

if (! function_exists('money_format')) {
    /**
     * Smart money formatting: 2 decimals when the amount has cents
     * (20.7 -> "20.70"), 0 when whole (414 -> "414"). Matches the
     * x-money component's auto-decimal rule for non-Blade surfaces.
     */
    function money_format($amount): string
    {
        $num = (float) $amount;
        $decimals = (((int) round(abs($num) * 100)) % 100 === 0) ? 0 : 2;

        return number_format($num, $decimals);
    }
}

if (! function_exists('local_date')) {
    /**
     * Format a date in the active locale (fa/ps month names) with a safe
     * fallback to the default representation when Carbon lacks the locale.
     *
     * Wrap the output in <bdi> at the call site so RTL flow keeps the
     * day/month/time order stable.
     */
    function local_date(CarbonInterface $date, string $format = 'd M, H:i'): string
    {
        $locale = app()->getLocale();

        if (in_array($locale, ['fa', 'ps'], true) && in_array($locale, Carbon\Carbon::getAvailableLocales(), true)) {
            return $date->locale($locale)->translatedFormat($format);
        }

        return $date->format($format);
    }
}
