<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\DriverController;
use App\Http\Controllers\ParcelController;
use App\Http\Controllers\ParcelImageController;
use App\Http\Controllers\ParcelTrackingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TrackingController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public routes
|--------------------------------------------------------------------------
|
| Parcel tracking is deliberately open: a customer holding a tracking number
| can follow their shipment without an account, which is what the
| specification asks for.
|
*/

// Route::redirect (not a closure) so `php artisan route:cache` works in production.
Route::redirect('/', '/track')->name('home');

Route::controller(TrackingController::class)->group(function (): void {
    Route::get('/track', 'index')->name('track.index');
    Route::get('/track/lookup', 'lookup')->name('track.lookup');
    Route::get('/track/{parcel:tracking_number}', 'show')
        ->name('track.show')
        ->middleware('throttle:60,1');
});

/*
|--------------------------------------------------------------------------
| Guest routes
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function (): void {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])
        ->name('register.store')
        ->middleware('throttle:10,1');

    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');

    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email')
        ->middleware('throttle:6,1');

    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.update');
});

/*
|--------------------------------------------------------------------------
| Authenticated routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'active'])->group(function (): void {

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    // --- Email verification (bonus) ---------------------------------------
    Route::controller(EmailVerificationController::class)->group(function (): void {
        Route::get('/email/verify', 'notice')->name('verification.notice');
        Route::get('/email/verify/{id}/{hash}', 'verify')
            ->middleware(['signed', 'throttle:6,1'])
            ->name('verification.verify');
        Route::post('/email/verification-notification', 'resend')
            ->middleware('throttle:6,1')
            ->name('verification.send');
    });

    // --- Profile & password ------------------------------------------------
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('/password/change', [PasswordController::class, 'edit'])->name('password.change');
    Route::put('/password/change', [PasswordController::class, 'update'])->name('password.change.update');

    // --- Dashboards --------------------------------------------------------
    Route::controller(DashboardController::class)->group(function (): void {
        // Redirects to whichever dashboard matches the signed-in user's role.
        Route::get('/dashboard', 'index')->name('dashboard');
        Route::get('/dashboard/admin', 'admin')->name('dashboard.admin');
        Route::get('/dashboard/manager', 'manager')->name('dashboard.manager');
        Route::get('/dashboard/dispatcher', 'dispatcher')->name('dashboard.dispatcher');
        Route::get('/dashboard/driver', 'driver')->name('dashboard.driver');
    });

    // --- Parcels -----------------------------------------------------------
    Route::controller(ParcelController::class)->prefix('parcels')->name('parcels.')->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/quote', 'quote')->name('quote');
        Route::get('/{parcel}', 'show')->name('show');
        Route::get('/{parcel}/edit', 'edit')->name('edit');
        Route::put('/{parcel}', 'update')->name('update');
        Route::delete('/{parcel}', 'destroy')->name('destroy');
        Route::get('/{parcel}/label', 'label')->name('label');
        Route::get('/{parcel}/label/pdf', 'labelPdf')->name('label.pdf');
        Route::post('/{parcel}/cancel', 'cancel')->name('cancel');
    });

    // Parcel tracking events and photos.
    Route::post('/parcels/{parcel}/tracking', [ParcelTrackingController::class, 'store'])
        ->name('parcels.tracking.store');

    Route::post('/parcels/{parcel}/images', [ParcelImageController::class, 'store'])
        ->name('parcels.images.store');
    Route::delete('/parcel-images/{image}', [ParcelImageController::class, 'destroy'])
        ->name('parcels.images.destroy');

    // --- Deliveries --------------------------------------------------------
    Route::controller(DeliveryController::class)->prefix('deliveries')->name('deliveries.')->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('/assign', 'assignBoard')->name('assign');
        Route::post('/assign', 'store')->name('store');
        Route::get('/{delivery}', 'show')->name('show');

        // Driver actions.
        Route::post('/{delivery}/accept', 'accept')->name('accept');
        Route::post('/{delivery}/reject', 'reject')->name('reject');
        Route::post('/{delivery}/in-transit', 'markInTransit')->name('in-transit');
        Route::post('/{delivery}/complete', 'complete')->name('complete');
        Route::post('/{delivery}/fail', 'markFailed')->name('fail');

        // Dispatcher / management action.
        Route::post('/{delivery}/cancel', 'cancelAssignment')->name('cancel');
    });

    // --- Customers ---------------------------------------------------------
    Route::resource('customers', CustomerController::class);
    Route::post('/customers/{customer}/restore', [CustomerController::class, 'restore'])
        ->name('customers.restore')
        ->withTrashed();

    // --- Drivers -----------------------------------------------------------
    Route::resource('drivers', DriverController::class);
    Route::post('/drivers/{driver}/toggle-status', [DriverController::class, 'toggleStatus'])
        ->name('drivers.toggle-status');
    Route::post('/drivers/{driver}/restore', [DriverController::class, 'restore'])
        ->name('drivers.restore')
        ->withTrashed();

    // --- Branches ----------------------------------------------------------
    Route::resource('branches', BranchController::class);
    Route::get('/branches/{branch}/shipments', [BranchController::class, 'shipments'])
        ->name('branches.shipments');

    // --- Staff accounts ----------------------------------------------------
    Route::resource('users', UserController::class);
    Route::post('/users/{user}/toggle-status', [UserController::class, 'toggleStatus'])
        ->name('users.toggle-status');
    Route::post('/users/{user}/restore', [UserController::class, 'restore'])
        ->name('users.restore')
        ->withTrashed();

    // --- Reports -----------------------------------------------------------
    Route::controller(ReportController::class)
        ->prefix('reports')
        ->name('reports.')
        ->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('/daily-shipments', 'dailyShipments')->name('daily-shipments');
            Route::get('/monthly-revenue', 'monthlyRevenue')->name('monthly-revenue');
            Route::get('/driver-performance', 'driverPerformance')->name('driver-performance');
            Route::get('/customer-shipments', 'customerShipments')->name('customer-shipments');
            Route::get('/deliveries', 'deliveries')->name('deliveries');

            // One export endpoint for every report, driven by the same filters
            // the on-screen version uses.
            Route::get('/export/{report}/{format}', 'export')
                ->whereIn('report', [
                    'daily-shipments', 'monthly-revenue', 'driver-performance',
                    'customer-shipments', 'deliveries',
                ])
                ->whereIn('format', ['csv', 'xlsx', 'pdf'])
                ->name('export');
        });
});
