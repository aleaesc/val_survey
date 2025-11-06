<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\SurveyController;

Route::get('/', function () {
    return file_get_contents(public_path('enduser.html'));
});

// Admin dashboard (no browser basic auth; UI login is handled client-side)
Route::get('/admin', function () {
    return response()->file(public_path('adminfrontend.html'));
});

// Temporary bridge: ensure API endpoints are available even if routes/api.php isn't being loaded
Route::prefix('api')->middleware('api')->group(function () {
    Route::post('/surveys', [SurveyController::class, 'store']);
    Route::get('/surveys', [SurveyController::class, 'index']);
    Route::get('/surveys/stats', [SurveyController::class, 'stats']);
    Route::get('/surveys/{survey}', [SurveyController::class, 'show']);
});