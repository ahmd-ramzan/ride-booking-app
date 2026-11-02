<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('vehicle_category', 16);
            $table->string('vehicle_model', 64);
            $table->string('vehicle_plate', 16)->unique();
            $table->string('status', 16)->default('offline');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamp('location_updated_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'vehicle_category', 'latitude', 'longitude'], 'drivers_matching_index');
        });

        DB::statement("ALTER TABLE drivers ADD CONSTRAINT drivers_status_check CHECK (status IN ('offline', 'available', 'on_trip'))");
        DB::statement("ALTER TABLE drivers ADD CONSTRAINT drivers_vehicle_category_check CHECK (vehicle_category IN ('economy', 'comfort'))");
        DB::statement("ALTER TABLE drivers ADD CONSTRAINT drivers_available_requires_location_check CHECK (status <> 'available' OR (latitude IS NOT NULL AND longitude IS NOT NULL))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('drivers');
    }
};
