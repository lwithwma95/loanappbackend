<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank', function (Blueprint $table) {
            // Drop global unique — same IFSC must be allowed across different districts
            $table->dropUnique('bank_ifsc_unique');

            // Replace with composite: IFSC is unique only within a district
            $table->unique(['ifsc', 'district_id'], 'bank_ifsc_district_unique');
        });
    }

    public function down(): void
    {
        Schema::table('bank', function (Blueprint $table) {
            $table->dropUnique('bank_ifsc_district_unique');
            $table->unique('ifsc', 'bank_ifsc_unique');
        });
    }
};
