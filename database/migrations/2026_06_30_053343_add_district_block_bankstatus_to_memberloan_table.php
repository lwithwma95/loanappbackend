<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memberloan', function (Blueprint $table) {
            $table->integer('district_id')->nullable()->after('shg_code');
            $table->integer('block_id')->nullable()->after('district_id');
            // Bank-reported status, used to compare against our own `status` column.
            $table->string('bank_status', 20)->nullable()->after('status');

            $table->foreign('district_id')->references('district_id')->on('district')->nullOnDelete();
            $table->foreign('block_id')->references('block_id')->on('block')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('memberloan', function (Blueprint $table) {
            $table->dropForeign(['district_id']);
            $table->dropForeign(['block_id']);
            $table->dropColumn(['district_id', 'block_id', 'bank_status']);
        });
    }
};
