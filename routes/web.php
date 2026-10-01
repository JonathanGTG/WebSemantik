<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FilmController;
use App\Http\Controllers\ChatbotController;

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