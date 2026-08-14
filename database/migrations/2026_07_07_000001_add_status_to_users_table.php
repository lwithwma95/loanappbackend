<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Default 'active' keeps all existing users accessible.
            // New self-registered users are created as 'pending'.
            if (!Schema::hasColumn('users', 'status')) {
                $table->enum('status', ['pending', 'active', 'inactive'])->default('active')->after('role');
            }
            if (!Schema::hasColumn('users', 'district_id')) {
                $table->unsignedInteger('district_id')->nullable()->after('status');
            }
            if (!Schema::hasColumn('users', 'block_id')) {
                $table->unsignedInteger('block_id')->nullable()->after('district_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(array_filter(
                ['status', 'district_id', 'block_id'],
                fn ($col) => Schema::hasColumn('users', $col)
            ));
        });
    }
};
