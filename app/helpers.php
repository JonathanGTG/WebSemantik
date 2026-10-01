<?php

if (!function_exists('pilemFormatDurasi')) {
    /**
     * Konversi ISO 8601 duration (e.g. "PT2H30M") ke string human-readable (e.g. "2h 30m").
     */
    function pilemFormatDurasi(?string $iso): ?string
    {
        if (!$iso) return null;
        preg_match('/PT(?:(\d+)H)?(?:(\d+)M)?/', $iso, $m);
        $h   = $m[1] ?? 0;
        $mnt = $m[2] ?? 0;
        $parts = [];
        if ($h)   $parts[] = $h . 'h';
        if ($mnt) $parts[] = $mnt . 'm';
        return $parts ? implode(' ', $parts) : null;
    }
}

if (!function_exists('pilemStudioBadge')) {
    /**
     * Ambil label badge singkat dari nama studio (CSV pertama).
     */
    function pilemStudioBadge(?string $studioCsv): ?string
    {
        if (!$studioCsv) return null;
        $first = trim(explode(',', $studioCsv)[0]);
        $map = [
            'Marvel Studios'      => 'MCU',
            'Pixar'               => 'PIXAR',
            'Walt Disney Pictures'=> 'DISNEY',
        ];
        return $map[$first] ?? strtoupper($first);
    }
}
