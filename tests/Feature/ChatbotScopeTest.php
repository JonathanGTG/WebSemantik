<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChatbotScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.gemini.key' => 'test-key']);
        Cache::forget('chatbot_film_catalog_rdf');

        Http::fake([
            'localhost:3030/*' => Http::response([
                'results' => ['bindings' => [
                    [
                        'film' => ['value' => 'http://pilem.org/cinegraph/resource/movie/tt2380307'],
                        'nama' => ['value' => 'Coco'],
                        'tahun' => ['value' => '2017-10-27'],
                        'genres' => ['value' => 'Animation, Family'],
                        'sutradara' => ['value' => 'Lee Unkrich'],
                        'aktor' => ['value' => 'Anthony Gonzalez|Gael García Bernal'],
                    ],
                ]],
            ]),
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [[
                    'text' => '__FILMS__:["tt2380307","tt9999999"]__FILMS__ Coco sangat cocok.',
                ]]]]],
            ]),
        ]);
    }

    public function test_system_prompt_restricts_to_project_catalog(): void
    {
        $response = $this->postJson('/chatbot/send', ['message' => 'Rekomendasikan film animasi']);

        $response->assertOk();

        Http::assertSent(function (Request $request) {
            if (! str_contains($request->url(), 'generativelanguage')) {
                return false;
            }

            $prompt = $request['systemInstruction']['parts'][0]['text'];

            return str_contains($prompt, '[tt2380307] Coco')
                && str_contains($prompt, 'Sutradara: Lee Unkrich')
                && str_contains($prompt, 'HANYA boleh membahas film yang ada di katalog');
        });
    }

    public function test_suggestions_outside_catalog_are_dropped(): void
    {
        $response = $this->postJson('/chatbot/send', ['message' => 'Rekomendasikan film animasi']);

        $response->assertOk()
            ->assertJsonCount(1, 'filmSuggestions')
            ->assertJsonPath('filmSuggestions.0.film_id', 'tt2380307')
            ->assertJsonPath('filmSuggestions.0.title', 'Coco')
            ->assertJsonPath('reply', 'Coco sangat cocok.');
    }
}

