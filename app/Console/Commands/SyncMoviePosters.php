<?php

namespace App\Console\Commands;

use App\Models\MoviePoster;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class SyncMoviePosters extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cinegraph:sync-posters {--download : Unduh file gambar secara lokal ke public/images/posters/} {--source=dataset/film2.ttl : File dataset TTL sumber}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sinkronisasi poster film dari dataset TTL / TMDB ke tabel database movie_posters';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $sourcePath = base_path($this->option('source'));
        $shouldDownload = $this->option('download');

        if (! File::exists($sourcePath)) {
            $this->error("File dataset tidak ditemukan: {$sourcePath}");

            return Command::FAILURE;
        }

        $this->info("Membaca dataset dari: {$sourcePath}...");
        $content = File::get($sourcePath);

        // Tiap blok film dimulai dengan movie: di awal baris dan diakhiri sebelum resource baru berikutnya di awal baris
        $pattern = '/(?:^|\n)(?:movie:|<http:\/\/pilem\.org\/cinegraph\/resource\/movie\/)(tt[a-zA-Z0-9]+|tmdb-\d+)>?\s+a\s+schema:Movie\s*;\s*(.*?)(?=\n(?:movie:|<http:\/\/pilem\.org\/cinegraph\/resource\/movie\/|person:|country:|genre:|$))/s';

        preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);

        $this->info('Menemukan '.count($matches).' blok film di dataset TTL.');

        $downloadDir = public_path('images/posters');
        if ($shouldDownload && ! File::isDirectory($downloadDir)) {
            File::makeDirectory($downloadDir, 0755, true);
        }

        $count = 0;
        $downloaded = 0;

        foreach ($matches as $m) {
            $filmId = $m[1];
            $body = $m[2];

            // Ambil title
            $title = null;
            if (preg_match('/schema:name\s+"([^"]+)"/', $body, $titleMatch)) {
                $title = $titleMatch[1];
            }

            // Ambil tmdbId
            $tmdbId = null;
            if (preg_match('/film:tmdbId\s+(\d+)/', $body, $tmdbMatch)) {
                $tmdbId = (int) $tmdbMatch[1];
            }

            // Ambil imdbId
            $imdbId = null;
            if (preg_match('/film:imdbId\s+"([^"]+)"/', $body, $imdbMatch)) {
                $imdbId = $imdbMatch[1];
            } elseif (str_starts_with($filmId, 'tt')) {
                $imdbId = $filmId;
            }

            // Ambil poster url jika ada di TTL, atau pakai yang sudah ada di database
            $existing = MoviePoster::where('film_id', $filmId)->first();
            $posterUrl = null;
            if (preg_match('/schema:image\s+"([^"]+)"/', $body, $imgMatch)) {
                $posterUrl = $imgMatch[1];
            } elseif ($existing?->poster_url) {
                $posterUrl = $existing->poster_url;
            }

            // Deteksi studio secara heuristik (Marvel / Pixar / Disney)
            $studio = $this->guessStudio($title, $body);

            $localPath = null;
            if ($shouldDownload && $posterUrl) {
                $filename = $filmId.'.jpg';
                $destPath = $downloadDir.'/'.$filename;
                $relPath = 'images/posters/'.$filename;

                if (File::exists($destPath)) {
                    $localPath = $relPath;
                } else {
                    try {
                        $resp = Http::timeout(10)->get($posterUrl);
                        if ($resp->successful()) {
                            File::put($destPath, $resp->body());
                            $localPath = $relPath;
                            $downloaded++;
                        }
                    } catch (\Throwable $e) {
                        $this->warn("Gagal unduh poster {$filmId}: {$e->getMessage()}");
                    }
                }
            }

            MoviePoster::updateOrCreate(
                ['film_id' => $filmId],
                [
                    'tmdb_id' => $tmdbId,
                    'imdb_id' => $imdbId,
                    'title' => $title,
                    'poster_url' => $posterUrl,
                    'local_path' => $localPath,
                    'studio' => $studio,
                ]
            );

            $count++;
        }

        $this->info("Berhasil menyinkronkan {$count} poster film ke database!");
        if ($shouldDownload) {
            $this->info("Berhasil mengunduh {$downloaded} file poster ke lokal ({$downloadDir}).");
        }

        return Command::SUCCESS;
    }

    /**
     * Tebak studio berdasarkan judul atau data film.
     */
    protected function guessStudio(?string $title, string $body): ?string
    {
        if (! $title) {
            return null;
        }

        $marvelKeywords = [
            'iron man', 'thor', 'captain america', 'avengers', 'guardians of the galaxy',
            'ant-man', 'doctor strange', 'spider-man', 'black panther', 'captain marvel',
            'black widow', 'shang-chi', 'eternals', 'hulk', 'deadpool', 'wolverine',
            'thunderbolts', 'marvel',
        ];

        $pixarKeywords = [
            'toy story', 'a bug\'s life', 'monsters', 'finding nemo', 'finding dory',
            'the incredibles', 'cars', 'ratatouille', 'wall-e', 'up', 'brave',
            'inside out', 'the good dinosaur', 'coco', 'onward', 'soul', 'luca',
            'turning red', 'lightyear', 'elemental', 'elio',
        ];

        $disneyKeywords = [
            'mickey', 'black cauldron', 'oliver & company', 'little mermaid', 'beauty and the beast',
            'aladdin', 'lion king', 'pocahontas', 'hunchback of notre dame', 'hercules',
            'mulan', 'tarzan', 'fantasia', 'dumbo', 'bambi', 'cinderella', 'alice in wonderland',
            'peter pan', 'lady and the tramp', 'sleeping beauty', '101 dalmatians', 'sword in the stone',
            'jungle book', 'aristocats', 'robin hood', 'winnie the pooh', 'rescuers',
            'fox and the hound', 'frozen', 'tangled', 'moana', 'zootopia', 'encanto', 'raya',
            'hocus pocus', 'honey, i shrunk the kids', 'mary poppins', 'nightmare before christmas',
            'muppet', 'pirates of the caribbean',
        ];

        $lower = strtolower($title);
        foreach ($marvelKeywords as $kw) {
            if (str_contains($lower, $kw)) {
                return 'Marvel Studios';
            }
        }

        foreach ($pixarKeywords as $kw) {
            if (str_contains($lower, $kw)) {
                return 'Pixar';
            }
        }

        foreach ($disneyKeywords as $kw) {
            if (str_contains($lower, $kw)) {
                return 'Walt Disney Pictures';
            }
        }

        return 'Walt Disney Pictures'; // Default Disney collection
    }
}
