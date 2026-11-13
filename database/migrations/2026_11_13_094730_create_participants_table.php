<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('participants', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('email')->nullable()->index();
            $table->string('phone', 32)->nullable();
            $table->string('phone_key', 16)->nullable()->index();   // last 9 digits, for duplicate checks
            $table->string('category', 16)->default('registered')->index(); // registered|partner|invited|walkin
            $table->string('partner_name')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('paid')->default(true);
            $table->string('payment_proof')->nullable();
            $table->timestamp('registered_at')->nullable();
            $table->timestamp('checked_in_at')->nullable()->index();
            $table->timestamps();
            $table->index('updated_at'); // used by live sync between devices
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participants');
    }
};