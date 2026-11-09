<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('ps_id');
            $table->date('date')->index();
            // Blank code means present in PowerSchool.
            $table->string('code')->nullable();
            $table->string('description')->nullable();
            $table->unsignedBigInteger('ps_period_id')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'ps_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
    }
};
