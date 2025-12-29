<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Ride;

class AutoCancelRideJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public int $rideId;

    public function __construct(int $rideId)
    {
        $this->rideId = $rideId;
    }


    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $ride = Ride::find($this->rideId);

        if (!$ride) {
            return;
        }

        // Only cancel if still pending / assigned
        if (in_array($ride->status, ['pending', 'assigned'])) {
            $ride->update([
                'status' => 'cancelled',
                'cancel_reason' => 'No driver accepted within 15 minutes'
            ]);
        }
    }
}
