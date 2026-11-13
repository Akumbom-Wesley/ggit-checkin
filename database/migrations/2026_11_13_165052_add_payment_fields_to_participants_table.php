<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->unsignedInteger('amount')->default(0)->after('paid');   // in FCFA
            $table->timestamp('paid_at')->nullable()->after('amount');      // when they paid
        });
    }

    public function down(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->dropColumn(['amount', 'paid_at']);
        });
    }
};