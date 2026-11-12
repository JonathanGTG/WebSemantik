<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FilmFilterAndRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_detail_displays_separated_director_and_actors_with_character_role(): void
    {
        $response = $this->get('/film/tt0115433');

        $response->assertStatus(200);
        $response->assertSee('Sutradara');
        $response->assertSee('Stephen Herek');
        $response->assertSee('Pemeran &amp; Karakter', false);
        $response->assertSee('Glenn Close');
        $response->assertSee('Cruella de Vil');
        $response->assertSee('sebagai');
    }

    public function test_filter_navbar_rendered_and_functional(): void
    {
        $response = $this->get('/?huruf=A&genre=Action&rated=PG-13&sort=year_desc');

        $response->assertStatus(200);
        $response->assertSee('btn-toggle-filter');
        $response->assertSee('filter-navbar-panel');
        $response->assertSee('Filter berdasarkan Judul A-Z');
    }

    public function test_now_playing_contains_recent_years_and_no_duplicates(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('Now Playing');
        $response->assertSee('Sedang Tayang (2024–2026)');

        $films = $response->viewData('nowPlaying');
        $this->assertNotEmpty($films);

        $ids = [];
        foreach ($films as $film) {
            $year = (int) substr($film['tahun'], 0, 4);
            $this->assertGreaterThanOrEqual(2024, $year);
            $this->assertLessThanOrEqual(2026, $year);

            $this->assertNotContains($film['id'], $ids, "Film {$film['nama']} is duplicated");
            $ids[] = $film['id'];
        }
    }
}
