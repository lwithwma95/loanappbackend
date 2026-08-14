<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('emp_attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->tinyInteger('month')->unsigned();
            $table->smallInteger('year')->unsigned();
            $table->tinyInteger('total_working_days')->default(26);
            $table->tinyInteger('days_present')->default(0);
            $table->tinyInteger('casual_leave_taken')->default(0);
            $table->tinyInteger('medical_leave_taken')->default(0);
            $table->tinyInteger('maternity_leave_taken')->default(0);
            $table->tinyInteger('paternity_leave_taken')->default(0);
            $table->string('remarks', 500)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'month', 'year']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('emp_attendance');
    }
};
