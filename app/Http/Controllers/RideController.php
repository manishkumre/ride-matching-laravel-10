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
use App\Models\Ride;
use App\Models\RideAssignment;
use App\Services\RideAssignmentService;
use App\Jobs\AutoCancelRideJob;

class RideController extends Controller
{

    public function requestRide(Request $request)
    {
        $request->validate([
            'pickup_lat' => 'required|numeric',
            'pickup_lng' => 'required|numeric',
            'drop_lat'   => 'required|numeric',
            'drop_lng'   => 'required|numeric',
            'passenger_count' => 'required|integer|min:1',
        ]);

        DB::beginTransaction();

        try {
            // 1️⃣ Create ride (basic data)
            $ride = Ride::create([
                'user_id' => auth()->id(),
                'status' => 'pending',
                'passenger_count' => $request->passenger_count,
                'assignment_attempts' => 0,
            ]);

            // 2️⃣ Save PostGIS pickup/dropoff
            DB::statement("
                UPDATE rides
                SET pickup_location = ST_SetSRID(ST_MakePoint(?, ?), 4326),
                    dropoff_location = ST_SetSRID(ST_MakePoint(?, ?), 4326)
                WHERE id = ?
            ", [
                $request->pickup_lng,
                $request->pickup_lat,
                $request->drop_lng,
                $request->drop_lat,
                $ride->id
            ]);

            DB::commit();

            // 3️⃣ Try assignment (non-blocking)
            app(RideAssignmentService::class)->assign($ride);

            // 4️⃣ Auto cancel after 15 min (SAFE)
            AutoCancelRideJob::dispatch($ride->id)
                ->delay(now()->addMinutes(15));

            return response()->json([
                'message' => 'Ride requested successfully',
                'ride_id' => $ride->id,
                'status' => 'pending'
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function accept($rideId)
    {
        $ride = Ride::where('id', $rideId)
            ->where('driver_id', auth()->user()->driver->id)
            ->firstOrFail();

        // ✅ ONLY assigned ride can be accepted
        if ($ride->status !== 'assigned') {
            return response()->json([
                'message' => 'Ride is not assignable'
            ], 400);
        }

        DB::transaction(function () use ($ride) {

            // Update ride
            $ride->update([
                'status' => 'accepted'
            ]);

            // Update assignment history
            DB::table('ride_assignments')
                ->where('ride_id', $ride->id)
                ->where('driver_id', $ride->driver_id)
                ->update(['status' => 'accepted']);
        });

        return response()->json([
            'message' => 'Ride accepted successfully',
            'ride_id' => $ride->id,
            'status' => 'accepted'
        ]);
    }

    public function start(Ride $ride)
    {
        $driver = auth()->user()->driver;

        if ($ride->status !== 'accepted' || $ride->driver_id !== $driver->id) {
            return response()->json(['message' => 'Ride cannot be started'], 422);
        }

        $ride->update(['status' => 'started']);

        return response()->json(['message' => 'Ride started']);
    }

    public function complete(Ride $ride)
    {
        $driver = auth()->user()->driver;

        if ($ride->status !== 'started' || $ride->driver_id !== $driver->id) {
            return response()->json(['message' => 'Ride cannot be completed'], 422);
        }

        DB::transaction(function () use ($ride, $driver) {
            $ride->update(['status' => 'completed']);
            $driver->update(['status' => 'available']);
        });

        return response()->json(['message' => 'Ride completed']);
    }




    public function reject($rideId)
    {
        return DB::transaction(function () use ($rideId) {

            $ride = Ride::lockForUpdate()->findOrFail($rideId);

            // ✅ SAFETY CHECK
            if (!$ride->driver_id || $ride->status !== 'assigned') {
                return response()->json([
                    'message' => 'Ride not assignable'
                ], 409);
            }

            $driverId = $ride->driver_id;

            // 1️⃣ Mark assignment rejected
            RideAssignment::where('ride_id', $ride->id)
                ->where('driver_id', $driverId)
                ->update([
                    'status' => 'rejected',
                    'reason' => request('reason')
                ]);

            // 2️⃣ Increment attempts
            $ride->increment('assignment_attempts');

            // 3️⃣ Clear ride assignment
            $ride->update([
                'driver_id' => null,
                'status' => 'pending',
            ]);

            // 4️⃣ Release Redis lock + availability
            app(RideAssignmentService::class)->unlockDriver($driverId);
            app('redis')->set("driver:{$driverId}:status", 'available');

            // 5️⃣ Max attempts check
            if ($ride->assignment_attempts >= 3) {
                $ride->update([
                    'status' => 'cancelled',
                    'cancel_reason' => 'All drivers rejected'
                ]);

                return response()->json([
                    'message' => 'Ride failed after 3 attempts'
                ]);
            }

            // 6️⃣ Reassign
            app(RideAssignmentService::class)->assign($ride);

            return response()->json([
                'message' => 'Driver rejected, reassigning'
            ]);
        });
    }


    public function activeRides()
    {
        $rides = Ride::with(['driver.user', 'user'])
            ->whereIn('status', ['pending', 'assigned', 'started'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'count' => $rides->count(),
            'rides' => $rides
        ]);
    }







}
