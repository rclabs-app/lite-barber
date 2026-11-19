<?php

use App\Http\Controllers\AdminAppointmentController;
use App\Http\Controllers\AdminBlockedSlotController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminProfessionalController;
use App\Http\Controllers\AdminReviewController;
use App\Http\Controllers\AdminServiceController;
use App\Http\Controllers\AdminShopController;
use App\Http\Controllers\AdminWaitlistController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PublicAppointmentController;
use App\Http\Controllers\PublicReviewController;
use App\Http\Controllers\PublicShopController;
use App\Http\Controllers\PublicWaitlistController;
use Illuminate\Support\Facades\Route;

// Public Endpoints (App Cliente)
Route::middleware('throttle:public-read')->group(function () {
    Route::get('/shops/{slug}', [PublicShopController::class, 'show']);
    Route::get('/shops/{slug}/services', [PublicShopController::class, 'services']);
    Route::get('/shops/{slug}/professionals', [PublicShopController::class, 'professionals']);
    Route::get('/shops/{slug}/appointments/busy', [PublicAppointmentController::class, 'getBusySlots']);
    Route::get('/shops/{slug}/waitlist', [PublicWaitlistController::class, 'status']);
    Route::get('/shops/{slug}/reviews', [PublicReviewController::class, 'index']);
    Route::get('/appointments/{token}', [PublicAppointmentController::class, 'showByToken']);
    Route::get('/reviews/{token}', [PublicReviewController::class, 'showByToken']);
});

Route::middleware('throttle:public-write')->group(function () {
    Route::post('/shops/{slug}/appointments', [PublicAppointmentController::class, 'store']);
    Route::post('/shops/{slug}/waitlist', [PublicWaitlistController::class, 'store']);
    Route::post('/appointments/{token}/cancel', [PublicAppointmentController::class, 'cancelByToken']);
    Route::post('/reviews/{token}', [PublicReviewController::class, 'store']);
});

// Authentication Endpoints
Route::middleware('throttle:auth')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/register', [AuthController::class, 'register']);
});

Route::middleware(['auth:sanctum', 'tenant'])->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Admin Endpoints
    Route::get('/admin/dashboard/metrics', [AdminDashboardController::class, 'metrics']);
    Route::get('/admin/reports', [AdminDashboardController::class, 'reports']);
    Route::get('/admin/appointments', [AdminAppointmentController::class, 'index']);
    Route::patch('/admin/appointments/{id}/status', [AdminAppointmentController::class, 'updateStatus']);

    // Admin Waitlist (Lista de Espera por Horário)
    Route::get('/admin/waitlist', [AdminWaitlistController::class, 'index']);
    Route::post('/admin/waitlist', [AdminWaitlistController::class, 'store']);
    Route::patch('/admin/waitlist/{id}/notify', [AdminWaitlistController::class, 'markNotified']);
    Route::delete('/admin/waitlist/{id}', [AdminWaitlistController::class, 'destroy']);

    // Admin Services CRUD
    Route::get('/admin/services', [AdminServiceController::class, 'index']);
    Route::post('/admin/services', [AdminServiceController::class, 'store']);
    Route::put('/admin/services/{id}', [AdminServiceController::class, 'update']);
    Route::delete('/admin/services/{id}', [AdminServiceController::class, 'destroy']);

    // Admin Shop Config
    Route::get('/admin/shop', [AdminShopController::class, 'show']);
    Route::put('/admin/shop', [AdminShopController::class, 'update']);
    Route::patch('/admin/shop/plan', [AdminShopController::class, 'updatePlan']);
    Route::post('/admin/shop/logo', [AdminShopController::class, 'uploadLogo']);
    Route::get('/admin/audit-logs', [AdminShopController::class, 'auditLogs']);

    // Admin Professionals CRUD
    Route::get('/admin/professionals', [AdminProfessionalController::class, 'index']);
    Route::post('/admin/professionals', [AdminProfessionalController::class, 'store']);
    Route::put('/admin/professionals/{id}', [AdminProfessionalController::class, 'update']);
    Route::delete('/admin/professionals/{id}', [AdminProfessionalController::class, 'destroy']);

    // Admin Blocked Slots (Pausas e Bloqueios)
    Route::get('/admin/blocked-slots', [AdminBlockedSlotController::class, 'index']);
    Route::post('/admin/blocked-slots', [AdminBlockedSlotController::class, 'store']);
    Route::delete('/admin/blocked-slots/{id}', [AdminBlockedSlotController::class, 'destroy']);

    // Admin Reviews (Moderação de Avaliações)
    Route::get('/admin/reviews', [AdminReviewController::class, 'index']);
    Route::patch('/admin/reviews/{id}/toggle-approval', [AdminReviewController::class, 'toggleApproval']);
});
