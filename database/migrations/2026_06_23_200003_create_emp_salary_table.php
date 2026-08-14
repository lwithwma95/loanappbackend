<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('emp_salary', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->unsignedBigInteger('attendance_id')->nullable();
            $table->foreign('attendance_id')->references('id')->on('emp_attendance')->nullOnDelete();
            $table->tinyInteger('month')->unsigned();
            $table->smallInteger('year')->unsigned();
            $table->tinyInteger('total_working_days');
            $table->tinyInteger('paid_days');
            $table->decimal('basic_salary', 12, 2);
            $table->decimal('mobile_allowance', 12, 2);
            $table->decimal('professional_dev', 12, 2);
            $table->decimal('gross_salary', 12, 2);
            $table->decimal('epf_deduction', 12, 2);
            $table->decimal('professional_tax', 12, 2);
            $table->decimal('net_salary', 12, 2);
            $table->decimal('actual_paid_salary', 12, 2);
            $table->enum('status', ['draft', 'paid', 'cancelled'])->default('draft');
            $table->date('payment_date')->nullable();
            $table->string('payment_mode')->nullable();
            $table->string('transaction_id', 100)->nullable();
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'month', 'year']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('emp_salary');
    }
};
