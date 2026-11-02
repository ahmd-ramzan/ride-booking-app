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
        Schema::create('rides', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('rider_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('drivers')->restrictOnDelete();
            $table->string('category', 16);
            $table->string('status', 16)->default('requested');

            $table->string('pickup_address');
            $table->decimal('pickup_latitude', 10, 7);
            $table->decimal('pickup_longitude', 10, 7);
            $table->string('dropoff_address');
            $table->decimal('dropoff_latitude', 10, 7);
            $table->decimal('dropoff_longitude', 10, 7);

            $table->unsignedInteger('distance_meters');
            $table->unsignedInteger('duration_seconds');

            $table->char('currency', 3);
            $table->unsignedInteger('estimated_fare');
            $table->unsignedInteger('final_fare')->nullable();
            $table->jsonb('fare_breakdown');

            $table->timestamp('requested_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancelled_by', 16)->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->timestamps();

            $table->index(['rider_id', 'requested_at']);
            $table->index(['driver_id', 'requested_at']);
            $table->index(['status', 'category', 'requested_at']);
        });

        DB::statement("ALTER TABLE rides ADD CONSTRAINT rides_status_check CHECK (status IN ('requested', 'accepted', 'in_progress', 'completed', 'cancelled'))");
        DB::statement("ALTER TABLE rides ADD CONSTRAINT rides_category_check CHECK (category IN ('economy', 'comfort'))");
        DB::statement("ALTER TABLE rides ADD CONSTRAINT rides_assigned_driver_check CHECK (status NOT IN ('accepted', 'in_progress', 'completed') OR driver_id IS NOT NULL)");
        DB::statement("ALTER TABLE rides ADD CONSTRAINT rides_completed_fare_check CHECK (status <> 'completed' OR final_fare IS NOT NULL)");

        /*
         * Database-level backstops for the core invariants: PostgreSQL refuses a
         * second active ride for the same rider or for the same driver, even if
         * application-level checks were bypassed or regressed.
         */
        DB::statement("CREATE UNIQUE INDEX rides_one_active_per_rider ON rides (rider_id) WHERE status IN ('requested', 'accepted', 'in_progress')");
        DB::statement("CREATE UNIQUE INDEX rides_one_active_per_driver ON rides (driver_id) WHERE status IN ('accepted', 'in_progress')");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rides');
    }
};
