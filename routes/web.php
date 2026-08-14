<?php

use App\Http\Controllers\Api\Tracking\AdEventController;
use App\Http\Controllers\Api\Tracking\CompletionStatusController;
use App\Http\Controllers\Api\Tracking\EngagementEventController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Public\ArticleController;
use App\Http\Controllers\Public\CategoryController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\PageController;
use App\Http\Controllers\Public\TagController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('/articles', [ArticleController::class, 'index'])->name('articles.index');
Route::get('/articles/{slug}', [ArticleController::class, 'show'])->name('articles.show');
Route::get('/category/{slug}', [CategoryController::class, 'show'])->name('categories.show');
Route::get('/tag/{slug}', [TagController::class, 'show'])->name('tags.show');
Route::get('/pages/{slug}', [PageController::class, 'show'])->name('pages.show');

Route::middleware('throttle:tracking')->prefix('api/track')->name('api.track.')->group(function () {
    Route::post('/events', [EngagementEventController::class, 'store'])->name('events');
    Route::get('/completion', [CompletionStatusController::class, 'show'])->name('completion');
    Route::post('/ad-events', [AdEventController::class, 'store'])->name('ad-events');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
