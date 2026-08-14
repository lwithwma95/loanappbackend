<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('aadhaar_number', 16)->nullable()->after('emp_qualification');
        });

        Schema::table('block_cadres', function (Blueprint $table) {
            $table->string('aadhaar_number', 16)->nullable()->after('mobile');
            $table->date('date_of_birth')->nullable()->after('aadhaar_number');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('aadhaar_number');
        });

        Schema::table('block_cadres', function (Blueprint $table) {
            $table->dropColumn(['aadhaar_number', 'date_of_birth']);
        });
    }
};
