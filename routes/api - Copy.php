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
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/register', [AuthController::class,'register']);
Route::post('/login', [AuthController::class,'login']);

Route::post('/drivers/register',[DriverController::class,'register'])
 ->middleware(['auth:sanctum','role:passenger']);

 Route::post('/rides/request',[RideController::class,'requestRide'])
 ->middleware(['auth:sanctum','role:passenger']);


 Route::patch('/drivers/location', [DriverController::class, 'updateLocation'])
    ->middleware(['auth:sanctum', 'role:driver']);

Route::patch('/drivers/status', [DriverController::class, 'updateStatus'])
    ->middleware(['auth:sanctum', 'role:driver']);

    Route::patch('/rides/{ride_id}/accept', [RideController::class, 'accept'])
    ->middleware(['auth:sanctum', 'role:driver']);



