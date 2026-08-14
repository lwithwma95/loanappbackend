<?php

use Illuminate\Database\Migrations\Migration;

// vill_id and gp_id columns are already BIGINT in the database.
// The truncation to 2147483647 was caused by PHP running in 32-bit mode.
// Fix is applied in the Gp/Village models ($keyType = 'string') and GeoController.
return new class extends Migration
{
    public function up(): void {}
    public function down(): void {}
};
