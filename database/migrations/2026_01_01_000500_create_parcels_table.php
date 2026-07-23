<?php

use App\Enums\DeliveryPriority;
use App\Enums\ParcelStatus;
use App\Enums\ParcelType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parcels', function (Blueprint $table) {
            $table->id();

            // Customer-facing tracking number, e.g. SWT-20260722-4F9K2A.
            $table->string('tracking_number', 40)->unique();

            // The sender. Restricted on delete so a customer with shipment
            // history can only ever be soft-deleted, never truly removed.
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();

            $table->string('receiver_name');
            $table->string('receiver_phone', 20);
            $table->text('receiver_address');
            $table->string('receiver_city', 100);
            $table->string('receiver_postal_code', 10)->nullable();
            $table->text('pickup_address');

            $table->enum('parcel_type', ParcelType::values())->default(ParcelType::Package->value);
            $table->decimal('weight', 8, 3);
            $table->decimal('length_cm', 8, 2)->nullable();
            $table->decimal('width_cm', 8, 2)->nullable();
            $table->decimal('height_cm', 8, 2)->nullable();

            $table->decimal('delivery_charge', 10, 2)->default(0);
            $table->decimal('cod_amount', 10, 2)->default(0);
            $table->enum('payment_method', PaymentMethod::values())->default(PaymentMethod::CashOnDelivery->value);
            $table->enum('payment_status', PaymentStatus::values())->default(PaymentStatus::Pending->value);

            $table->enum('priority', DeliveryPriority::values())->default(DeliveryPriority::Normal->value);
            $table->enum('status', ParcelStatus::values())->default(ParcelStatus::Pending->value);

            $table->string('qr_path')->nullable();
            $table->text('special_instructions')->nullable();

            $table->timestamp('expected_delivery_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->unsignedTinyInteger('delivery_attempts')->default(0);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->softDeletes();
            $table->timestamps();

            // Dashboard widgets and the parcel list filter on these combinations.
            $table->index(['status', 'created_at']);
            $table->index(['branch_id', 'status']);
            $table->index(['customer_id', 'status']);
            $table->index(['payment_status', 'created_at']);
            $table->index('priority');
            $table->index('delivered_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parcels');
    }
};
