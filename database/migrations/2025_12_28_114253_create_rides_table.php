<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
     public function up(): void
    {
        Schema::create('rides', function (Blueprint $table) {
            $table->id();

            // Passenger
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->cascadeOnDelete();

            // Assigned driver (nullable till assigned)
            $table->foreignId('driver_id')
                  ->nullable()
                  ->constrained('drivers')
                  ->nullOnDelete();

            $table->integer('passenger_count');

            // Ride state machine
            $table->string('status');
            // pending | assigned | accepted | started | completed | cancelled

            // Reassignment support
            $table->unsignedTinyInteger('assignment_attempts')
                  ->default(0);

            // Cancel / reject reason
            $table->text('cancel_reason')->nullable();

            $table->timestamps();
        });

        // 📍 PostGIS locations
        DB::statement("
            ALTER TABLE rides
            ADD pickup_location geography(Point, 4326)
        ");

        DB::statement("
            ALTER TABLE rides
            ADD dropoff_location geography(Point, 4326)
        ");

        // 🔥 Optional but good index
        DB::statement("
            CREATE INDEX rides_status_idx ON rides(status)
        ");
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rides');
    }
};
