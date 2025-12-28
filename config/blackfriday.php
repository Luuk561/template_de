<?php

/**
 * config/blackfriday.php
 * Code-gedreven Black Friday-config (géén database nodig).
 * Gebruik:
 *   $tz      = config('blackfriday.timezone');
 *   $bfFlag  = (bool) config('blackfriday.active'); // default, kan via ?bf overschreven worden
 *   $bfStart = config('blackfriday.start');         // 'YYYY-MM-DD'
 *   $bfUntil = config('blackfriday.until');         // 'YYYY-MM-DD'
 *   $qKey    = config('blackfriday.preview.query_key'); // 'bf'
 */
$timezone = 'Europe/Amsterdam';
$nowNl = new DateTime('now', new DateTimeZone($timezone));
$year = (int) $nowNl->format('Y');

/**
 * Vensters per jaar:
 * - start: maandag van de BF-week
 * - until: dinsdag na Cyber Monday
 * Black Friday is altijd de vrijdag na Thanksgiving (4e donderdag van november in de VS)
 */
$windows = [
    2025 => ['start' => '2025-11-24', 'until' => '2025-12-02'], // BF: 28 nov
    2026 => ['start' => '2026-11-23', 'until' => '2026-12-01'], // BF: 27 nov
    2027 => ['start' => '2027-11-22', 'until' => '2027-11-30'], // BF: 26 nov
    2028 => ['start' => '2028-11-20', 'until' => '2028-11-28'], // BF: 24 nov
    2029 => ['start' => '2029-11-19', 'until' => '2029-11-27'], // BF: 23 nov
    2030 => ['start' => '2030-11-25', 'until' => '2030-12-03'], // BF: 29 nov
    2031 => ['start' => '2031-11-24', 'until' => '2031-12-02'], // BF: 28 nov
    2032 => ['start' => '2032-11-22', 'until' => '2032-11-30'], // BF: 26 nov
    2033 => ['start' => '2033-11-21', 'until' => '2033-11-29'], // BF: 25 nov
    2034 => ['start' => '2034-11-20', 'until' => '2034-11-28'], // BF: 24 nov
];

/**
 * Selectie:
 * - Als huidig jaar aanwezig is → gebruik dat
 * - anders eerstvolgende toekomstige definitie
 * - anders laatste bekende (verleden) definitie
 */
$selected = $windows[$year] ?? (function () use ($windows, $year) {
    if (empty($windows)) {
        return ['start' => $year.'-11-25', 'until' => $year.'-12-01'];
    }
    $future = array_filter($windows, fn ($_, $k) => $k >= $year, ARRAY_FILTER_USE_BOTH);
    if (! empty($future)) {
        $k = min(array_keys($future));

        return $windows[$k];
    }
    $k = max(array_keys($windows));

    return $windows[$k];
})();

return [
    'timezone' => $timezone,

    // Standaard UIT; testen/forceren kan altijd via ?bf=on / ?bf=off
    'active' => false,

    // Gekozen venster (inclusief begin- en einddag, NL-tijd)
    'start' => $selected['start'],
    'until' => $selected['until'],

    // Preview via querystring
    'preview' => [
        'query_key' => 'bf',
        'truthy' => ['1', 'on', 'true'],
        'falsy' => ['0', 'off', 'false'],
    ],

    // Handig als je een countdown toont tot einde van de einddag
    'countdown_end_of_day' => true,
];
