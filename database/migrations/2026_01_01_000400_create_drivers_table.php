<?php

use App\Enums\DriverStatus;
use App\Enums\VehicleType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->string('driver_code', 30)->unique();

            // Login account for the driver. Optional so a subcontracted driver
            // can be tracked without being given dashboard access.
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();

            $table->string('full_name');
            $table->string('phone', 20);
            $table->string('email')->nullable();
            $table->string('vehicle_number', 20);
            $table->string('license_number', 30);
            $table->enum('vehicle_type', VehicleType::values())->default(VehicleType::Motorbike->value);

            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();

            $table->string('photo_path')->nullable();
            $table->enum('status', DriverStatus::values())->default(DriverStatus::Available->value);
            $table->date('license_expiry')->nullable();
            $table->text('notes')->nullable();

            $table->softDeletes();
            $table->timestamps();

            // Same soft-delete-aware uniqueness as customers: a retired driver
            // must not permanently reserve a vehicle or licence number.
            $table->unique(['vehicle_number', 'deleted_at'], 'drivers_vehicle_unique');
            $table->unique(['license_number', 'deleted_at'], 'drivers_license_unique');

            $table->index(['branch_id', 'status']);
            $table->index('full_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drivers');
    }
};
