<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('block_cadres', function (Blueprint $table) {
            $table->id();
            $table->string('cadre_code', 50)->unique();   // = member.mem_code
            $table->string('name');
            $table->string('mobile', 15)->nullable();
            $table->string('email')->nullable()->unique();
            $table->unsignedInteger('block_id')->nullable();
            $table->unsignedInteger('district_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('role', ['block_cadre', 'block_admin'])->default('block_cadre');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('block_cadres');
    }
};
