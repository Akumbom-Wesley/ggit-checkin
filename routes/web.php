<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DataManagementController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\ParticipantController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ReportPdfController;
use App\Http\Controllers\SummaryController;
use Illuminate\Support\Facades\Route;


Route::get('/login', [AuthController::class, 'show'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:40,1');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware('pin')->group(function () {
    Route::get('/', fn () => view('checkin'))->name('checkin');
    Route::get('/export', ExportController::class)->name('export');
    Route::get('/export/pdf', ReportPdfController::class)->name('export.pdf');
    Route::get('/summary', SummaryController::class)->name('summary');
    Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::patch('/payments/{participant}', [PaymentController::class, 'update'])->name('payments.update');

    // Data & Reports page
    Route::prefix('data')->name('data.')->controller(DataManagementController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/import', 'import')->name('import');
        Route::get('/import-template', 'template')->name('template');
        Route::post('/reset-checkins', 'resetCheckins')->name('reset-checkins');
        Route::delete('/participants', 'destroyAll')->name('participants.destroy');
    });

    // JSON API used by the check-in screen
    Route::prefix('api')->group(function () {
        Route::get('/participants', [ParticipantController::class, 'index']);
        Route::get('/participants/changes', [ParticipantController::class, 'changes']);
        Route::post('/participants', [ParticipantController::class, 'store']);
        Route::post('/participants/{participant}/checkin', [ParticipantController::class, 'checkin']);
        Route::post('/participants/{participant}/undo', [ParticipantController::class, 'undo']);
    });
});