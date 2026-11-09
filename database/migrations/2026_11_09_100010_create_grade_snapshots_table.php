<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grade_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->date('snapshot_on');
            $table->decimal('percent', 6, 2)->nullable();
            $table->string('letter_grade')->nullable();
            $table->timestamps();

            $table->unique(['section_id', 'snapshot_on']);
            $table->index(['student_id', 'snapshot_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grade_snapshots');
    }
};
