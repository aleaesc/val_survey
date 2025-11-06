<?php
// In routes/api.php
use App\Http\Controllers\Api\SurveyController;
use App\Http\Controllers\Api\ChatController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

// Auth endpoints
Route::post('/login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
});

// This is the public endpoint for submitting the survey
Route::post('/surveys', [SurveyController::class, 'store'])->middleware('throttle:20,1');
// The following admin reads are protected
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/surveys', [SurveyController::class, 'index']);
    // Place stats route BEFORE the {survey} route to avoid shadowing and 404s
    Route::get('/surveys/stats', [SurveyController::class, 'stats']);
    // Constrain {survey} to numeric IDs so "stats" won't match this route
    Route::get('/surveys/{survey}', [SurveyController::class, 'show'])->whereNumber('survey');
    // Delete a single survey (soft delete)
    Route::delete('/surveys/{survey}', [SurveyController::class, 'destroy'])->whereNumber('survey');
    // Restore (undo) a soft-deleted survey
    Route::post('/surveys/{id}/restore', [SurveyController::class, 'restore'])->whereNumber('id');
    // Exports
    Route::get('/surveys/export/csv', [SurveyController::class, 'exportCsv']);
    Route::get('/surveys/export/pdf', [SurveyController::class, 'exportPdf']);
    // Geo metrics for map analytics
    Route::get('/surveys/geo/metrics', [SurveyController::class, 'geoMetrics']);
});

// AI Chat endpoint (ValBot)
Route::post('/chat', [ChatController::class, 'handle'])->middleware('throttle:20,1');

// Development-only: quick reset endpoint to clear survey data
if (app()->environment('local')) {
    Route::delete('/surveys', function () {
        return DB::transaction(function () {
            \App\Models\SurveyRating::query()->delete();
            \App\Models\Survey::query()->delete();
            return response()->noContent();
        });
    })->middleware('throttle:5,1');

    // Quick email test endpoint to verify SMTP credentials
    Route::post('/mail/test', function () {
        try {
            $to = config('mail.to.address') ?: env('SURVEY_ADMIN_EMAIL');
            if (!$to) return response()->json(['error' => 'No recipient configured'], 400);
            Mail::raw('Test email from Valenzuela Survey', function ($m) use ($to) {
                $m->to($to)->subject('SMTP Test');
            });
            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    })->middleware('throttle:3,1');
}

// Legacy endpoint kept for basic checks
Route::middleware('auth:sanctum')->get('/user', function (Request $request) { return $request->user(); });