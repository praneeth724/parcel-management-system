<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BranchController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DeliveryController;
use App\Http\Controllers\Api\V1\DriverController;
use App\Http\Controllers\Api\V1\ParcelController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\TrackingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1
|--------------------------------------------------------------------------
|
| Every route is prefixed with /api/v1. Authentication is Laravel Sanctum
| bearer tokens; obtain one from POST /api/v1/auth/login.
|
| See docs/API.md for the full reference.
|
*/

Route::prefix('v1')->name('api.v1.')->group(function (): void {

    /*
    |----------------------------------------------------------------------
    | Public
    |----------------------------------------------------------------------
    */

    Route::post('/auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1')
        ->name('auth.login');

    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword'])
        ->middleware('throttle:6,1')
        ->name('auth.forgot-password');

    // Anyone holding a tracking number can follow their shipment.
    Route::get('/track/{trackingNumber}', [TrackingController::class, 'show'])
        ->middleware('throttle:60,1')
        ->name('track.show');

    /*
    |----------------------------------------------------------------------
    | Authenticated
    |----------------------------------------------------------------------
    */

    Route::middleware(['auth:sanctum', 'active'])->group(function (): void {

        // --- Session ------------------------------------------------------
        Route::controller(AuthController::class)->prefix('auth')->name('auth.')->group(function (): void {
            Route::get('/me', 'me')->name('me');
            Route::post('/logout', 'logout')->name('logout');
            Route::post('/logout-all', 'logoutAll')->name('logout-all');
            Route::put('/password', 'changePassword')->name('change-password');
        });

        // --- Dashboard ----------------------------------------------------
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // --- Parcels ------------------------------------------------------
        Route::apiResource('parcels', ParcelController::class);
        Route::controller(ParcelController::class)->prefix('parcels/{parcel}')->name('parcels.')->group(function (): void {
            Route::post('/cancel', 'cancel')->name('cancel');
            Route::get('/trackings', 'trackings')->name('trackings');
            Route::post('/trackings', 'addTracking')->name('trackings.store');
            Route::get('/qr', 'qrPayload')->name('qr');
        });

        // --- Deliveries ---------------------------------------------------
        Route::apiResource('deliveries', DeliveryController::class)
            ->only(['index', 'show', 'store']);

        Route::controller(DeliveryController::class)
            ->prefix('deliveries/{delivery}')
            ->name('deliveries.')
            ->group(function (): void {
                Route::post('/accept', 'accept')->name('accept');
                Route::post('/reject', 'reject')->name('reject');
                Route::post('/in-transit', 'markInTransit')->name('in-transit');
                Route::post('/complete', 'complete')->name('complete');
                Route::post('/fail', 'markFailed')->name('fail');
                Route::post('/cancel', 'cancelAssignment')->name('cancel');
            });

        // --- Directory ----------------------------------------------------
        Route::apiResource('customers', CustomerController::class);
        Route::get('/customers/{customer}/parcels', [CustomerController::class, 'parcels'])
            ->name('customers.parcels');

        Route::apiResource('drivers', DriverController::class);
        Route::get('/drivers/{driver}/deliveries', [DriverController::class, 'deliveries'])
            ->name('drivers.deliveries');

        Route::apiResource('branches', BranchController::class);
        Route::get('/branches/{branch}/parcels', [BranchController::class, 'parcels'])
            ->name('branches.parcels');

        // --- Reports ------------------------------------------------------
        Route::controller(ReportController::class)->prefix('reports')->name('reports.')->group(function (): void {
            Route::get('/daily-shipments', 'dailyShipments')->name('daily-shipments');
            Route::get('/monthly-revenue', 'monthlyRevenue')->name('monthly-revenue');
            Route::get('/driver-performance', 'driverPerformance')->name('driver-performance');
            Route::get('/customer-shipments', 'customerShipments')->name('customer-shipments');
            Route::get('/deliveries', 'deliveries')->name('deliveries');
        });
    });
});
