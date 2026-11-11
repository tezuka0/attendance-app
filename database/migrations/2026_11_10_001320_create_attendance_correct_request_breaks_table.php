<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('attendance_correct_request_breaks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_correct_request_id')->constrained(indexName: 'acr_breaks_acr_id_foreign')->cascadeOnDelete();
            $table->time('break_start');
            $table->time('break_end');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_correct_request_breaks');
    }
};
