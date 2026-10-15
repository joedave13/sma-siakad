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
        Schema::create('classrooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->string('name');
            $table->unsignedTinyInteger('grade_level');
            $table->string('major', 20)->nullable();
            $table->foreignId('homeroom_teacher_id')->nullable()->constrained('teachers')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['academic_year_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('classrooms');
    }
};
