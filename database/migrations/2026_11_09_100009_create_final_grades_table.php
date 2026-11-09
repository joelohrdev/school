<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('final_grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reporting_term_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('ps_id');
            $table->decimal('percent', 6, 2)->nullable();
            $table->string('letter_grade')->nullable();
            $table->date('as_of')->nullable();
            $table->string('store_type')->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'ps_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('final_grades');
    }
};
