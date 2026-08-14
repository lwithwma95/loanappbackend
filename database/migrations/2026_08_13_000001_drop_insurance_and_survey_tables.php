<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * The insurance module (PMJJBY / PMSBY) and the scheme survey module were removed
 * from the portal. This drops their tables on databases that already have them.
 * Child tables are dropped first so the foreign keys go away cleanly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('pmjjby_pay');
        Schema::dropIfExists('pmjjby');
        Schema::dropIfExists('pmsby_pay');
        Schema::dropIfExists('pmsby');
        Schema::dropIfExists('scheme_surveys');
    }

    public function down(): void
    {
        // Irreversible — the insurance and survey modules no longer exist.
    }
};
