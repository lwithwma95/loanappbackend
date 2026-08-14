<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memberloan', function (Blueprint $table) {
            $table->string('mobile_no', 15)->nullable()->after('shg_code');
            $table->string('member_name', 255)->nullable()->after('mobile_no');
        });
    }

    public function down(): void
    {
        Schema::table('memberloan', function (Blueprint $table) {
            $table->dropColumn(['mobile_no', 'member_name']);
        });
    }
};
