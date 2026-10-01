<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChatbotController extends Controller
{
    /**
     * Batas jumlah pesan USER per sesi, supaya kuota API tidak habis
     * kalau ada yang spam chat saat demo (PRD FR-3).
     */
    protected int $maxMessagesPerSession = 20;

    protected string $systemPrompt = <<<PROMPT
        Kamu adalah asisten CineGraph, sebuah search engine film. Kamu boleh
        menjawab pertanyaan seputar film secara umum (sutradara, aktor, tahun
        rilis, rekomendasi film mirip, dsb) menggunakan pengetahuanmu sendiri,
        TIDAK dibatasi hanya ke dataset internal CineGraph. Jawab singkat,
        ramah, dan dalam Bahasa Indonesia kecuali user bertanya dalam bahasa lain.
        PROMPT;

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

        $history = session('chatbot_history', []);
        $userMessageCount = count(array_filter($history, fn ($m) => $m['role'] === 'user'));

        if ($userMessageCount >= $this->maxMessagesPerSession) {
            return response()->json([
                'error' => "Kamu sudah mencapai batas {$this->maxMessagesPerSession} pesan untuk sesi ini. Refresh halaman untuk mulai sesi baru.",
            ], 429);
        }

        $apiKey = config('services.gemini.key');
        $model = config('services.gemini.model', 'gemini-3.5-flash');

        if (!$apiKey) {
            Log::warning('Chatbot: GEMINI_API_KEY belum di-set di .env');

            return response()->json([
                'error' => 'Chatbot belum dikonfigurasi (API key belum di-set). Hubungi admin.',
            ], 500);
        }

        $history[] = ['role' => 'user', 'content' => $request->input('message')];

        // Format Gemini: role cuma "user" atau "model" (bukan "assistant"),
        // isi pesan dibungkus parts[].text, system prompt lewat systemInstruction.
        $contents = array_map(fn ($m) => [
            'role'  => $m['role'] === 'assistant' ? 'model' : 'user',
            'parts' => [['text' => $m['content']]],
        ], $history);

        try {
            $response = Http::withHeaders(['x-goog-api-key' => $apiKey])
                ->timeout(20)
                ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", [
                    'systemInstruction' => ['parts' => [['text' => $this->systemPrompt]]],
                    'contents'          => $contents,
                    // Model Gemini terbaru punya "thinking" yang ikut makan jatah
                    // token output, jadi jangan diset terlalu kecil.
                    'generationConfig'  => ['maxOutputTokens' => 1024],
                ]);
        } catch (\Throwable $e) {
            // Timeout / koneksi gagal -- jangan biarkan UI hang (edge case PRD §9)
            Log::warning('Chatbot: panggilan Gemini API gagal - ' . $e->getMessage());

            return response()->json([
                'error' => 'Chatbot sedang tidak bisa merespon (timeout). Coba lagi sebentar lagi.',
            ], 503);
        }

        if ($response->failed()) {
            $status = $response->status();
            Log::warning('Chatbot: Gemini API respon gagal - ' . $status . ' ' . $response->body());

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

        if (!$reply) {
            // Bisa karena jawaban diblokir filter keamanan atau terpotong.
            Log::warning('Chatbot: Gemini tidak mengembalikan teks - ' . $response->body());

            return response()->json([
                'error' => 'Chatbot tidak memberi jawaban. Coba pertanyaan lain.',
            ], 502);
        }

        $history[] = ['role' => 'assistant', 'content' => $reply];
        session(['chatbot_history' => $history]);

        return response()->json([
            'reply'         => $reply,
            'messagesUsed'  => $userMessageCount + 1,
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