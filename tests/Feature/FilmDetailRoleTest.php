<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FilmDetailRoleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test halaman detail film menampilkan aktor dan karakter yang diperankan.
     */
    public function test_detail_film_menampilkan_karakter_aktor(): void
    {
        $response = $this->get('/film/tt0115433');

        $response->assertStatus(200);
        $response->assertSee('Glenn Close');
        $response->assertSee('Cruella de Vil');
        $response->assertSee('sebagai');
    }
}
