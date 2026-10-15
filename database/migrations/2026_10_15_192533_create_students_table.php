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
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('nisn', 20)->unique();
            $table->string('nis', 20)->nullable();
            $table->string('full_name');
            $table->string('gender', 20);
            $table->date('birth_date')->nullable();
            $table->string('birth_place')->nullable();
            $table->string('address')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
