<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sections', function (Blueprint $table) {
            // Sections are per student, so siblings in the same class get separate rows.
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('term_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('ps_id');
            $table->unsignedBigInteger('ps_dcid')->nullable();
            $table->unsignedBigInteger('ps_term_id')->nullable();
            $table->string('course_name');
            $table->string('course_code')->nullable();
            $table->string('section_number')->nullable();
            $table->string('period')->nullable();
            $table->integer('period_sort')->nullable();
            $table->string('room')->nullable();
            $table->unsignedInteger('school_number')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'ps_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sections');
    }
};
