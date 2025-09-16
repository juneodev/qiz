<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\QuizController;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

// Public quiz routes
Route::get('/quiz', [QuizController::class, 'show'])->name('quiz.show');
Route::post('/quiz', [QuizController::class, 'submit'])->name('quiz.submit');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
