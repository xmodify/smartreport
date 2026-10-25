<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BmsHomepage\HomepageController as BmsHomepageController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| - Root (/) : System Admin Login page
| - /admin   : System Admin Dashboard (New clean management panel)
| - /bms-homepage : System 1 (HOSxP Homepage + Reports + HOSxP Admin Poster Manager)
|
*/

// Root & System Admin Authentication Routes
Route::get('/', [AuthController::class, 'showLogin'])->name('home');
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::get('/index.html', [AuthController::class, 'showLogin']);

// System Admin (New Clean Dashboard for Web Admin)
Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
});

// System 1: SmartReport BMS Homepage (HOSxP Split-Screen Portal & Posters)
// Primary route: /bms-homepage (e.g. http://127.0.0.1/smartreport/bms-homepage)
Route::prefix('bms-homepage')->name('homepage.')->group(function () {
    Route::get('/', [BmsHomepageController::class, 'index'])->name('index');
    Route::get('/index.html', [BmsHomepageController::class, 'index']);

    // BMS Session Management (Connect / Disconnect manually when not opened from HOSxP)
    Route::post('/session/connect', [BmsHomepageController::class, 'connectSession'])->name('session.connect');
    Route::post('/session/disconnect', [BmsHomepageController::class, 'disconnectSession'])->name('session.disconnect');

    // Real-Time OPD Queue Congestion Heatmap API
    Route::get('/queue-heatmap', [BmsHomepageController::class, 'getQueueHeatmap'])->name('queue-heatmap');

    // HOSxP Clinic Service Workload Statistics API (Dynamic Thai Calendar Date Selection)
    Route::get('/clinic-stats', [BmsHomepageController::class, 'getClinicStats'])->name('clinic-stats');

    // HOSxP Admin Poster Management Actions (Integrated directly into BMS Homepage)
    Route::post('/posters', [BmsHomepageController::class, 'storePoster'])->name('posters.store');
    Route::post('/posters/{id}/toggle-active', [BmsHomepageController::class, 'togglePoster'])->name('posters.toggle-active');
    Route::delete('/posters/{id}', [BmsHomepageController::class, 'deletePoster'])->name('posters.destroy');
    Route::post('/posters/{id}', [BmsHomepageController::class, 'updatePoster'])->name('posters.update');
});
