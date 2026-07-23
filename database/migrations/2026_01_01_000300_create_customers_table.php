<?php

use App\Enums\CustomerStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();

            // Human-facing "Customer ID" from the spec, e.g. CUS-2026-00042.
            $table->string('customer_code', 30)->unique();

            $table->string('full_name');
            $table->string('nic_passport', 30);
            $table->string('mobile', 20);
            $table->string('email')->nullable();
            $table->text('address');
            $table->string('city', 100);
            $table->string('postal_code', 10)->nullable();
            $table->string('company_name')->nullable();

            $table->enum('status', CustomerStatus::values())->default(CustomerStatus::Active->value);

            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->softDeletes();
            $table->timestamps();

            // NIC and mobile are unique among live rows only: soft-deleting a
            // customer must free the number for re-registration, which a plain
            // unique index would prevent.
            $table->unique(['nic_passport', 'deleted_at'], 'customers_nic_unique');
            $table->unique(['mobile', 'deleted_at'], 'customers_mobile_unique');

            $table->index('status');
            $table->index('city');
            $table->index('full_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
