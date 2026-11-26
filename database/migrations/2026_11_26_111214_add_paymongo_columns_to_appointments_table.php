<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->string('paymongo_checkout_id', 60)->nullable()->index()->after('official_receipt_no');
            $table->string('paymongo_payment_id', 60)->nullable()->unique()->after('paymongo_checkout_id');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn(['paymongo_checkout_id', 'paymongo_payment_id']);
        });
    }
};
