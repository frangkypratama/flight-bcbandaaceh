<?php

use App\Http\Controllers\Api\ManifestImportController;
use App\Http\Controllers\Api\ManifestSyncController;
use Illuminate\Support\Facades\Route;

Route::middleware('manifest.auth')->post('/manifest/sync', [ManifestSyncController::class, 'sync']);
Route::post('/manifest/import', [ManifestImportController::class, 'import']);
