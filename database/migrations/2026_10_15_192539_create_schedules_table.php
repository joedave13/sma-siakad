<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teaching_assignment_id')->constrained('teaching_assignments')->restrictOnDelete();
            $table->string('day_of_week', 20);
            $table->foreignId('lesson_period_id')->constrained('lesson_periods')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['teaching_assignment_id', 'day_of_week', 'lesson_period_id'],
                'schedules_assignment_day_period_unique',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
