<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->text('address');
            $table->string('city', 100);
            $table->string('postal_code', 10)->nullable();
            $table->string('contact_number', 20);
            $table->string('email')->nullable();

            // The Branch Manager who runs this branch. Nullable so a branch can
            // exist before its manager account is created; nulled rather than
            // cascaded if that user is removed so branch history survives.
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();

            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->index('is_active');
            $table->index('city');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
