<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assignment_category_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('ps_id');
            $table->unsignedBigInteger('ps_assignment_id')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->date('due_on')->nullable()->index();
            $table->decimal('points_possible', 8, 2)->nullable();
            $table->decimal('weight', 8, 2)->nullable();
            $table->boolean('counts_in_final_grade')->default(true);
            $table->boolean('scores_published')->default(true);
            $table->timestamps();

            $table->unique(['section_id', 'ps_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignments');
    }
};
