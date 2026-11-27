<?php

use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\FilmController;
use App\Http\Controllers\SpotifyAuthController;
use Illuminate\Support\Facades\Route;

// Landing page = desain "Now Playing" ala Pilem (hero + grid + trailer +
// chatbot inline + preview streaming). Beda dari /search yang tetap
// halaman pencarian polos.
Route::get('/', [FilmController::class, 'home'])->name('home');
Route::get('/search', [FilmController::class, 'search'])->name('film.search');
Route::get('/film/{id}', [FilmController::class, 'detail'])->name('film.detail');
Route::get('/statistik', [FilmController::class, 'statistik'])->name('film.statistik');
Route::get('/person/{id}', [FilmController::class, 'person'])->name('person.detail');

Route::post('/chatbot/send', [ChatbotController::class, 'send'])->name('chatbot.send');
Route::post('/chatbot/reset', [ChatbotController::class, 'reset'])->name('chatbot.reset');

// Spotify OAuth & Web Playback SDK
Route::get('/auth/spotify/login', [SpotifyAuthController::class, 'login'])->name('spotify.login');
Route::get('/auth/spotify/callback', [SpotifyAuthController::class, 'callback'])->name('spotify.callback');
Route::get('/auth/spotify/logout', [SpotifyAuthController::class, 'logout'])->name('spotify.logout');
Route::get('/auth/spotify/token', [SpotifyAuthController::class, 'token'])->name('spotify.token');
Route::post('/auth/spotify/play', [SpotifyAuthController::class, 'play'])->name('spotify.play');
