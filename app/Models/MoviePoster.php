<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MoviePoster extends Model
{
    protected $fillable = [
        'film_id',
        'tmdb_id',
        'imdb_id',
        'title',
        'poster_url',
        'local_path',
        'studio',
    ];

    /**
     * Dapatkan URL poster untuk ditampilkan.
     * Mengutamakan file lokal di public/ jika tersedia, fallback ke poster_url.
     */
    public function getDisplayPosterAttribute(): ?string
    {
        if ($this->local_path && file_exists(public_path($this->local_path))) {
            return asset($this->local_path);
        }

        return $this->poster_url;
    }

    /**
     * Cari poster untuk 1 film ID.
     */
    public static function getPoster(string $filmId, ?int $tmdbId = null): ?string
    {
        try {
            $record = static::where('film_id', $filmId)
                ->when($tmdbId, fn ($q) => $q->orWhere('tmdb_id', $tmdbId))
                ->first();

            return $record?->display_poster;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Ambil poster dalam batch untuk daftar film ID (agar efisien, 1 query database).
     *
     * @param  array<string>  $filmIds
     * @return array<string, string>
     */
    public static function getPosters(array $filmIds): array
    {
        if (empty($filmIds)) {
            return [];
        }

        try {
            $records = static::whereIn('film_id', $filmIds)->get();

            $map = [];
            foreach ($records as $record) {
                $map[$record->film_id] = $record->display_poster;
                if ($record->imdb_id) {
                    $map[$record->imdb_id] = $record->display_poster;
                }
                if ($record->tmdb_id) {
                    $map['tmdb-'.$record->tmdb_id] = $record->display_poster;
                }
            }

            return $map;
        } catch (\Throwable) {
            return [];
        }
    }
}
