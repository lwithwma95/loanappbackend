<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('designation_id')
                  ->constrained('designations')
                  ->onDelete('restrict');
            $table->string('emp_name');
            $table->string('emp_id')->unique();
            $table->date('emp_dob')->nullable();
            $table->date('emp_doj');
            $table->string('emp_qualification')->nullable();
            $table->decimal('salary', 12, 2)->default(0);
            $table->decimal('epf', 12, 2)->default(0);
            $table->decimal('deduction', 12, 2)->default(0);
            $table->decimal('gross_salary', 12, 2)->default(0);
            $table->decimal('net_salary', 12, 2)->default(0);
            $table->string('bank_account', 20)->nullable();
            $table->string('ifsc_code', 11)->nullable();
            $table->string('phone', 15)->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
