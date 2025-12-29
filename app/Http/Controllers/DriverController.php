<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use App\Models\User;
use App\Models\Role;
use App\Models\Ride;
use App\Models\Driver;
use Illuminate\Support\Facades\Redis;

class DriverController extends Controller
{


    public function register(Request $request)
{
    $user = auth()->user();

    // prevent duplicate driver
    if ($user->driver) {
        return response()->json(['message' => 'Already a driver'], 400);
    }

    $driver = Driver::create([
        'user_id' => $user->id,
        'vehicle_type' => $request->vehicle_type,
        'capacity' => $request->capacity,
        'status' => 'offline'
    ]);

    // // attach driver role
    // $user->roles()->syncWithoutDetaching([
    //     Role::where('name', 'driver')->value('id')
    // ]);
    auth()->user()->roles()->attach(2); // driver
    return response()->json([
        'message' => 'Driver registered successfully',
        'driver' => $driver
    ]);
}


    public function updateLocation(Request $request)
    {
        $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
        ]);

        // Logged-in user ka driver profile
        $driver = Driver::where('user_id', auth()->id())->first();

        if (! $driver) {
            return response()->json([
                'error' => 'Driver profile not found'
            ], 404);
        }

        // ✅ Save in DB (PostGIS)
        DB::statement(
            "UPDATE drivers
            SET location = ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography
            WHERE id = ?",
            [$request->lng, $request->lat, $driver->id]
        );

        // Redis me live location store (TTL = 2 minutes)
        // ✅ Save in Redis
        Redis::setex(
            "driver:{$driver->id}:location",
            120, // seconds
            json_encode([
                'lat' => $request->lat,
                'lng' => $request->lng,
                'updated_at' => now()->toDateTimeString()
            ])
        );

        return response()->json([
            'message' => 'Driver location updated successfully'
        ]);
    }

    public function rideHistory($id)
    {
        $driver = Driver::findOrFail($id);

        $rides = Ride::where('driver_id', $driver->id)
            ->where('status', 'completed')
            ->orderBy('updated_at', 'desc')
            ->get();

        return response()->json([
            'driver_id' => $driver->id,
            'total_completed_rides' => $rides->count(),
            'rides' => $rides
        ]);
    }



    public function updateStatus(Request $request)
    {
        $request->validate([
            'status' => 'required|in:available,offline'
        ]);

        $driver = Driver::where('user_id', auth()->id())->first();

        if (! $driver) {
            return response()->json([
                'message' => 'Driver profile not found'
            ], 404);
        }

        $driver->update([
            'status' => $request->status
        ]);

        return response()->json([
            'message' => 'Driver status updated',
            'status' => $driver->status
        ]);
    }


}
