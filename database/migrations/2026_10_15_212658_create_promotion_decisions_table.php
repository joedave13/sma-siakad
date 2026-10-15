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
        Schema::create('promotion_decisions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('promotion_batch_id');
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('from_classroom_id');
            $table->unsignedBigInteger('to_classroom_id')->nullable();
            $table->string('outcome', 20);
            $table->boolean('is_overridden')->default(false);
            $table->text('override_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['promotion_batch_id', 'student_id']);
            $table->index('from_classroom_id');
            $table->index('to_classroom_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promotion_decisions');
    }
};
