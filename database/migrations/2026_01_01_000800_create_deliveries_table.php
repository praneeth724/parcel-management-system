<?php

use App\Enums\DeliveryStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per driver assignment attempt.
     *
     * A parcel accumulates several rows over its life — a rejected assignment
     * or a failed doorstep attempt is kept and a new row is created on
     * reassignment — so driver performance can be measured honestly.
     */
    public function up(): void
    {
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parcel_id')->constrained('parcels')->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained('drivers')->restrictOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();

            $table->enum('status', DeliveryStatus::values())->default(DeliveryStatus::Assigned->value);
            $table->unsignedTinyInteger('attempt_number')->default(1);

            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();

            $table->string('rejection_reason')->nullable();
            $table->string('failure_reason')->nullable();

            // Proof of delivery.
            $table->string('delivery_location')->nullable();
            $table->decimal('delivery_latitude', 10, 7)->nullable();
            $table->decimal('delivery_longitude', 10, 7)->nullable();
            $table->string('received_by')->nullable();
            $table->string('receiver_nic', 30)->nullable();
            $table->string('signature_path')->nullable();
            $table->string('proof_image_path')->nullable();
            $table->decimal('cod_collected', 10, 2)->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            // "My deliveries" for a driver, and the open-assignment lookups.
            $table->index(['driver_id', 'status']);
            $table->index(['parcel_id', 'status']);
            $table->index(['status', 'assigned_at']);
            $table->index('completed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};
