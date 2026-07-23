<?php

use App\Http\Controllers\ManifestController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/manifest', [ManifestController::class, 'index'])->name('manifest.index');
    Route::post('/manifest/import', [ManifestController::class, 'import'])->name('manifest.import');
    Route::get('/manifest/search', [ManifestController::class, 'search'])->name('manifest.search');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
