<?php

use App\Enums\TrackingStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only audit trail of everything that happened to a parcel.
     *
     * Rows are never updated or deleted while the parcel lives, which is what
     * makes the public tracking page trustworthy.
     */
    public function up(): void
    {
        Schema::create('parcel_trackings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parcel_id')->constrained('parcels')->cascadeOnDelete();

            $table->enum('status', TrackingStatus::values());
            $table->string('location')->nullable();
            $table->text('remarks')->nullable();

            // Who logged the event. Nullable because system-generated events
            // (parcel created via API, scheduled status sweeps) have no user.
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();

            // Recorded separately from created_at so historical data can be
            // imported with its real timestamp.
            $table->timestamp('happened_at')->useCurrent();

            $table->timestamps();

            // The timeline is always read as "this parcel, newest first".
            $table->index(['parcel_id', 'happened_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parcel_trackings');
    }
};
