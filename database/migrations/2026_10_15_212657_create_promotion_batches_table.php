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
        Schema::create('promotion_batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('from_academic_year_id');
            $table->unsignedBigInteger('to_academic_year_id');
            $table->string('status', 20)->default('draft');
            $table->unsignedBigInteger('applied_by')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['from_academic_year_id', 'to_academic_year_id'], 'promotion_batches_year_pair_unique');
            $table->index('to_academic_year_id');
            $table->index('applied_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promotion_batches');
    }
};
