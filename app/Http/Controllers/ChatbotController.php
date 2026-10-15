<?php

namespace App\Http\Controllers;

use App\Models\MoviePoster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChatbotController extends Controller
{
    /**
     * Batas jumlah pesan USER per sesi, supaya kuota API tidak habis
     * kalau ada yang spam chat saat demo (PRD FR-3).
     */
    protected int $maxMessagesPerSession = 20;

    /**
     * Buat system prompt dinamis yang menyertakan katalog film dari database.
     * Di-cache 5 menit agar tidak query DB setiap request chatbot.
     */
    protected function buildSystemPrompt(): string
    {
        $catalog = Cache::remember('chatbot_film_catalog', 300, function () {
            return MoviePoster::select('film_id', 'title', 'studio')
                ->orderBy('title')
                ->get()
                ->map(fn ($m) => "- [{$m->film_id}] {$m->title}".($m->studio ? " ({$m->studio})" : ''))
                ->join("\n");
        });

        return <<<PROMPT
        Kamu adalah asisten CineGraph, search engine film Disney, Marvel, dan Pixar.
        Jawab singkat, ramah, dalam Bahasa Indonesia (kecuali user pakai bahasa lain).

        === KATALOG FILM DI DATASET CINEGRAPH ===
        {$catalog}
        =========================================

        ATURAN PENTING:
        1. Untuk pertanyaan umum tentang film, jawab bebas pakai pengetahuanmu.
        2. Jika user meminta REKOMENDASI, SARAN, atau daftar film (termasuk film berdasarkan
           aktor/sutradara tertentu), WAJIB sertakan daftar film dari katalog di atas yang
           relevan. Format blok harus menjadi BARIS PERTAMA dalam responsmu (sebelum teks
           penjelasan), PERSIS seperti ini:
           __FILMS__:["film_id_1","film_id_2","film_id_3"]__FILMS__
           Ganti film_id_1 dst dengan film_id dari katalog (contoh: "tt4154796").
           Sertakan 3-6 film yang paling relevan.
           Setelah blok __FILMS__, baru tulis penjelasan singkat.
        3. Jika tidak ada film relevan di katalog, jawab secara umum tanpa blok __FILMS__.
        4. Jangan tampilkan blok __FILMS__ jika tidak diminta rekomendasi/daftar film.
        PROMPT;
    }

    /**
     * POST /chatbot/send
     * Terima 1 pesan user, balas lewat Google Gemini API (key dari Google AI
     * Studio). Riwayat chat disimpan di session saja (tidak persist ke
     * database - sesuai PRD FR-3).
     */
    public function send(Request $request)
    {
        $request->validate([
            'message' => ['required', 'string', 'max:1000'],
        ]);

        $systemPrompt = $this->buildSystemPrompt();

        $history = session('chatbot_history', []);
        $userMessageCount = count(array_filter($history, fn ($m) => $m['role'] === 'user'));

        if ($userMessageCount >= $this->maxMessagesPerSession) {
            return response()->json([
                'error' => "Kamu sudah mencapai batas {$this->maxMessagesPerSession} pesan untuk sesi ini. Refresh halaman untuk mulai sesi baru.",
            ], 429);
        }

        $apiKey = config('services.gemini.key');
        $model = config('services.gemini.model', 'gemini-3.5-flash');

        if (! $apiKey) {
            Log::warning('Chatbot: GEMINI_API_KEY belum di-set di .env');

            return response()->json([
                'error' => 'Chatbot belum dikonfigurasi (API key belum di-set). Hubungi admin.',
            ], 500);
        }

        $history[] = ['role' => 'user', 'content' => $request->input('message')];

        // Format Gemini: role cuma "user" atau "model" (bukan "assistant"),
        // isi pesan dibungkus parts[].text, system prompt lewat systemInstruction.
        $contents = array_map(fn ($m) => [
            'role' => $m['role'] === 'assistant' ? 'model' : 'user',
            'parts' => [['text' => $m['content']]],
        ], $history);

        try {
            $response = Http::withHeaders(['x-goog-api-key' => $apiKey])
                ->timeout(20)
                ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", [
                    'systemInstruction' => ['parts' => [['text' => $systemPrompt]]],
                    'contents' => $contents,
                    // 2048 supaya respons panjang + blok __FILMS__ tidak terpotong.
                    'generationConfig' => ['maxOutputTokens' => 2048],
                ]);
        } catch (\Throwable $e) {
            // Timeout / koneksi gagal -- jangan biarkan UI hang (edge case PRD §9)
            Log::warning('Chatbot: panggilan Gemini API gagal - '.$e->getMessage());

            return response()->json([
                'error' => 'Chatbot sedang tidak bisa merespon (timeout). Coba lagi sebentar lagi.',
            ], 503);
        }

        if ($response->failed()) {
            $status = $response->status();
            Log::warning('Chatbot: Gemini API respon gagal - '.$status.' '.$response->body());

            if ($status === 429) {
                $message = 'Chatbot sedang sibuk (kuota API tercapai). Coba lagi sebentar lagi.';
            } elseif (in_array($status, [400, 401, 403], true)) {
                $message = 'Chatbot belum bisa dipakai (API key ditolak Google). Hubungi admin.';
            } elseif ($status === 404) {
                $message = 'Chatbot belum bisa dipakai (model tidak ditemukan). Hubungi admin.';
            } else {
                $message = 'Chatbot sedang mengalami gangguan. Coba lagi sebentar lagi.';
            }

            return response()->json(['error' => $message], 503);
        }

        $reply = $response->json('candidates.0.content.parts.0.text');

        if (! $reply) {
            // Bisa karena jawaban diblokir filter keamanan atau terpotong.
            Log::warning('Chatbot: Gemini tidak mengembalikan teks - '.$response->body());

            return response()->json([
                'error' => 'Chatbot tidak memberi jawaban. Coba pertanyaan lain.',
            ], 502);
        }

        // Ekstrak daftar film_id yang disarankan AI (format __FILMS__:[...]__FILMS__)
        $filmSuggestions = [];
        $cleanReply = $reply;
        if (preg_match('/__FILMS__:(\[.*?\])__FILMS__/s', $reply, $matches)) {
            $decoded = json_decode($matches[1], true);
            if (is_array($decoded) && ! empty($decoded)) {
                $filmIds = array_filter($decoded, fn ($id) => is_string($id) && preg_match('/^[a-zA-Z0-9_\-]+$/', $id));
                // Ambil data poster + judul dari DB untuk film yang disarankan
                $posters = MoviePoster::whereIn('film_id', $filmIds)
                    ->select('film_id', 'title', 'poster_url', 'local_path', 'studio')
                    ->get();
                foreach ($posters as $p) {
                    $filmSuggestions[] = [
                        'film_id' => $p->film_id,
                        'title' => $p->title,
                        'poster' => $p->display_poster,
                        'studio' => $p->studio,
                    ];
                }
            }
            // Buang blok __FILMS__ dari teks yang ditampilkan ke user
            $cleanReply = trim(preg_replace('/__FILMS__:\[.*?\]__FILMS__/s', '', $reply));
        }

        $history[] = ['role' => 'assistant', 'content' => $reply];
        session(['chatbot_history' => $history]);

        return response()->json([
            'reply' => $cleanReply,
            'filmSuggestions' => $filmSuggestions,
            'messagesUsed' => $userMessageCount + 1,
            'messagesLimit' => $this->maxMessagesPerSession,
        ]);
    }

    /**
     * POST /chatbot/reset - mulai sesi chat baru (kosongkan riwayat di session).
     */
    public function reset(Request $request)
    {
        $request->session()->forget('chatbot_history');

        return response()->json(['status' => 'ok']);
    }
}
