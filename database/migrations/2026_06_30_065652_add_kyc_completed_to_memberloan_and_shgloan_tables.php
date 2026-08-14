<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memberloan', function (Blueprint $table) {
            $table->boolean('kyc_completed')->default(false)->after('bank_status');
        });
        Schema::table('shgloan', function (Blueprint $table) {
            $table->boolean('kyc_completed')->default(false)->after('bank_status');
        });
    }

    public function down(): void
    {
        Schema::table('memberloan', function (Blueprint $table) {
            $table->dropColumn('kyc_completed');
        });
        Schema::table('shgloan', function (Blueprint $table) {
            $table->dropColumn('kyc_completed');
        });
    }
};
