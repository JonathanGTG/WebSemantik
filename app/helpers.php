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

if (!function_exists('pilemFilmIndex')) {
    /**
     * Index ringan semua film dari dataset/film.ttl: id => [judul, poster, studio].
     * Poster diambil dari triple `schema:image` yang SUDAH ada di film.ttl
     * (URL TMDB), jadi poster asli tampil otomatis tanpa input manual dan
     * tanpa bergantung pada tabel movie_posters. Hasil di-cache berdasarkan
     * waktu modifikasi file, jadi parsing hanya terjadi sekali per perubahan dataset.
     *
     * @return array<string, array{title: ?string, poster: ?string, studio: ?string}>
     */
    function pilemFilmIndex(): array
    {
        static $memo = null;
        if ($memo !== null) {
            return $memo;
        }

        $path = base_path('dataset/film.ttl');
        if (!is_file($path)) {
            return $memo = [];
        }

        $build = static function () use ($path): array {
            $index = [];
            $content = (string) file_get_contents($path);
            // Satu blok film = dari "movie:{id} a schema:Movie" sampai titik penutup resource.
            preg_match_all('/^movie:(\S+)\s+a\s+schema:Movie\s*;(.*?)\s\.\s*$/ms', $content, $blocks, PREG_SET_ORDER);

            foreach ($blocks as $b) {
                $title = null;
                if (preg_match('/schema:name\s+"((?:[^"\\\\]|\\\\.)*)"/', $b[2], $m)) {
                    $title = stripcslashes($m[1]);
                }
                $poster = null;
                if (preg_match('/schema:image\s+"([^"]+)"/', $b[2], $m)) {
                    $poster = $m[1];
                }
                $index[$b[1]] = [
                    'title'  => $title,
                    'poster' => $poster,
                    'studio' => pilemGuessStudio($title),
                ];
            }

            return $index;
        };

        try {
            $memo = \Illuminate\Support\Facades\Cache::rememberForever(
                'pilem.film_index.'.filemtime($path),
                $build
            );
        } catch (\Throwable) {
            $memo = $build();
        }

        return $memo;
    }
}

if (!function_exists('pilemPoster')) {
    /**
     * URL poster untuk satu film: pakai nilai dari controller kalau ada,
     * kalau kosong ambil dari film.ttl.
     */
    function pilemPoster(?string $filmId, ?string $existing = null): ?string
    {
        if ($existing) {
            return $existing;
        }

        return $filmId ? (pilemFilmIndex()[$filmId]['poster'] ?? null) : null;
    }
}

if (!function_exists('pilemGuessStudio')) {
    /**
     * Tebak studio dari judul (Marvel / Pixar / Walt Disney Pictures).
     * Heuristik yang sama dengan command cinegraph:sync-posters.
     */
    function pilemGuessStudio(?string $title): ?string
    {
        if (!$title) {
            return null;
        }
        $lower = mb_strtolower($title);

        $marvel = ['iron man', 'thor', 'captain america', 'avengers', 'guardians of the galaxy', 'ant-man',
            'doctor strange', 'spider-man', 'black panther', 'captain marvel', 'the marvels', 'black widow',
            'shang-chi', 'eternals', 'hulk', 'deadpool', 'wolverine', 'thunderbolts', 'marvel', 'fantastic four'];
        foreach ($marvel as $kw) {
            if (str_contains($lower, $kw)) {
                return 'Marvel Studios';
            }
        }

        $pixar = ['toy story', "a bug's life", 'monsters', 'finding nemo', 'finding dory', 'incredibles',
            'cars', 'ratatouille', 'wall-e', 'inside out', 'good dinosaur', 'coco', 'onward', 'soul',
            'luca', 'turning red', 'lightyear', 'elemental', 'elio', 'brave', 'up', 'vs. earth'];
        foreach ($pixar as $kw) {
            // kata pendek ('up', 'cars', 'soul') hanya cocok sebagai kata utuh
            $hit = strlen($kw) <= 5
                ? preg_match('/\b'.preg_quote($kw, '/').'\b/u', $lower)
                : str_contains($lower, $kw);
            if ($hit) {
                return 'Pixar';
            }
        }

        return 'Walt Disney Pictures';
    }
}

if (!function_exists('pilemFilmStudio')) {
    /**
     * Studio untuk satu film: dari controller kalau ada, kalau tidak tebak dari judul.
     */
    function pilemFilmStudio(?string $title, ?string $existing = null): ?string
    {
        return $existing ?: pilemGuessStudio($title);
    }
}

if (!function_exists('pilemYouTubeThumb')) {
    function pilemYouTubeThumb(?string $key): ?string
    {
        return $key ? "https://i.ytimg.com/vi/{$key}/hqdefault.jpg" : null;
    }
}
