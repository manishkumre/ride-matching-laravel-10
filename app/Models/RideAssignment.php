<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RideAssignment extends Model
{
    use HasFactory;
     protected $fillable = [
        'ride_id',
        'driver_id',
        'status',
        'reason',
    ];

    public function ride()
    {
        return $this->belongsTo(Ride::class);
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }
}
