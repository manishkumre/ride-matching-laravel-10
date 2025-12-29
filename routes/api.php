<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DriverController;
use App\Http\Controllers\RideController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::get('/health', fn () => response()->json(['status' => 'ok']));

/*
|--------------------------------------------------------------------------
| Auth Routes
|--------------------------------------------------------------------------
*/
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->get('/me', function (Request $request) {
    return $request->user();
});

Route::get('/rides/active', [RideController::class, 'activeRides'])
    ->middleware(['auth:sanctum']);

Route::get('/drivers/{driver_id}/ride-history', [DriverController::class, 'rideHistory'])
    ->middleware(['auth:sanctum']);


/*
|--------------------------------------------------------------------------
| Passenger Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'role:passenger'])->group(function () {

    // Passenger applies as driver
    Route::post('/drivers/register', [DriverController::class, 'register']);

    // Create ride request
    Route::post('/rides/request', [RideController::class, 'requestRide']);

    // Passenger cancels ride
    Route::patch('/rides/{ride}/cancel', [RideController::class, 'cancel']);
});

/*
|--------------------------------------------------------------------------
| Driver Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'role:driver'])->group(function () {

    // Driver online / offline
    Route::patch('/drivers/status', [DriverController::class, 'updateStatus']);

    // Driver updates live location (Redis)
    Route::patch('/drivers/location', [DriverController::class, 'updateLocation']);

    // Ride lifecycle (driver actions)
    Route::patch('/rides/{ride}/accept', [RideController::class, 'accept']);
    Route::patch('/rides/{ride}/reject', [RideController::class, 'reject']);
    Route::patch('/rides/{ride}/start',  [RideController::class, 'start']);
    Route::patch('/rides/{ride}/complete', [RideController::class, 'complete']);
});

/*
|--------------------------------------------------------------------------
| Shared / Read Routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    // Get ride details
    Route::get('/rides/{ride}', [RideController::class, 'show']);

    // Active rides (admin/debug)
    Route::get('/rides', [RideController::class, 'index']);

    // Driver ride history
    Route::get('/drivers/{driver}/rides', [DriverController::class, 'rideHistory']);
});

