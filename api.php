<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\EventController;

// バージョニングを意識したURL設計
Route::prefix('v1')->group(function () {
    Route::post('/events', [EventController::class, 'store']);
    // 今後必要に応じてPUTやDELETEもここに並びます
});