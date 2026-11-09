<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignment_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('ps_id');
            // Raw score as PowerSchool shows it; can be non-numeric (e.g. a letter or "--").
            $table->string('score')->nullable();
            $table->decimal('percent', 6, 2)->nullable();
            $table->string('letter_grade')->nullable();
            $table->boolean('missing')->default(false)->index();
            $table->boolean('late')->default(false);
            $table->boolean('exempt')->default(false);
            $table->boolean('collected')->default(false);
            $table->boolean('incomplete')->default(false);
            $table->text('comment')->nullable();
            // PowerSchool sends no score date: this is the first sync that saw a score.
            $table->timestamp('scored_at')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'ps_id']);
            $table->unique(['student_id', 'assignment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_scores');
    }
};
