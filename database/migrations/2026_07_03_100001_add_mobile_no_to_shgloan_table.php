<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shgloan', function (Blueprint $table) {
            $table->string('mobile_no', 15)->nullable()->after('shg_code');
        });
    }

    public function down(): void
    {
        Schema::table('shgloan', function (Blueprint $table) {
            $table->dropColumn('mobile_no');
        });
    }
};
