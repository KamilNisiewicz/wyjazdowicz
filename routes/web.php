<?php

use App\Http\Controllers\GameMatchController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'team.set'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/team', [TeamController::class, 'edit'])->name('team.edit');
    Route::post('/team/search', [TeamController::class, 'search'])->name('team.search');
    Route::post('/team', [TeamController::class, 'store'])->name('team.store');
});

Route::middleware(['auth', 'team.set'])->group(function () {
    Route::get('/matches', [GameMatchController::class, 'index'])->name('matches.index');
    Route::get('/matches/create', [GameMatchController::class, 'create'])->name('matches.create');
    Route::post('/matches/search', [GameMatchController::class, 'search'])->name('matches.search');
    Route::post('/matches', [GameMatchController::class, 'store'])->name('matches.store');
    Route::get('/matches/{match}/edit', [GameMatchController::class, 'edit'])->name('matches.edit');
    Route::patch('/matches/{match}', [GameMatchController::class, 'update'])->name('matches.update');
    Route::delete('/matches/{match}', [GameMatchController::class, 'destroy'])->name('matches.destroy');
});

require __DIR__.'/auth.php';
