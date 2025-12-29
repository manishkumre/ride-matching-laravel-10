<?php

namespace App\Services;

use App\Models\Ride;
use App\Models\Driver;
use App\Models\RideAssignment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

class RideAssignmentService
{
    /**
     * Assign nearest available driver to ride
     */
    public function assign(Ride $ride): ?Driver
    {
        $drivers = $this->findNearbyDrivers($ride);
        if ($drivers->isEmpty()) {
            Log::info("No nearby drivers found", [
                'ride_id' => $ride->id,
                'pickup' => [
                    $ride->pickup_lat,
                    $ride->pickup_lng
                ]
            ]);
            return null;
        }

        foreach ($drivers as $driver) {

            // Redis lock to prevent double booking
            if (! $this->lockDriver($driver->id)) {
                continue;
            }

            try {
                DB::transaction(function () use ($ride, $driver) {

                    //ALWAYS REFRESH FIRST
                    $ride->refresh();

                    // Double check availability
                    if ($driver->status !== 'available') {
                        throw new \Exception('Driver not available');
                    }

                    // Update ride
                    $ride->update([
                        'driver_id' => $driver->id,
                        'status' => 'assigned',
                        'assignment_attempts' => $ride->assignment_attempts + 1,
                    ]);

                    // Update driver
                    // $driver->update([
                    //     'status' => 'on_trip',
                    // ]);

                    // Save assignment history
                    RideAssignment::create([
                        'ride_id' => $ride->id,
                        'driver_id' => $driver->id,
                        'status' => 'assigned',
                    ]);
                });

                // 🔓 Release lock after assignment
                $this->unlockDriver($driver->id);

                return $driver;

            } catch (\Exception $e) {
                $this->unlockDriver($driver->id);
                continue;
            }
        }

        // No driver found
        // $ride->update(['status' => 'cancelled']);
        return null;
    }

    /**
     * Find nearest drivers using PostGIS
     */
    private function findNearbyDrivers(Ride $ride)
    {

        ###############first flow #####################

        // return Driver::where('status', 'available')
        // ->whereIn('id', function ($q) {
        //     $q->select('id')->from('drivers');
        // })
        // ->limit(5)
        // ->get();

        #################secound flow ######################

        return Driver::where('status', 'available')
        ->whereNotNull('location')
        ->whereRaw(
            "ST_DWithin(
                location,
                (SELECT pickup_location FROM rides WHERE id = ?) ,
                5000
            )",
            [$ride->id]
        )
        ->orderByRaw(
            "ST_Distance(
                location,
                (SELECT pickup_location FROM rides WHERE id = ?)
            )",
            [$ride->id]
        )
        ->limit(3)
        ->get();

    }

    /**
     * Redis lock
     */
    public function lockDriver(int $driverId): bool
    {
        return Redis::set(
            "lock:driver:$driverId",
            1,
            'NX',
            'EX',
            5
        );
    }

    public function unlockDriver(?int $driverId): void
    {
        if (!$driverId) {
            return;
        }

        Redis::del("lock:driver:$driverId");
    }

}
