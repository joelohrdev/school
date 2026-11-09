<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reporting_terms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ps_id')->unique();
            $table->unsignedBigInteger('ps_term_id')->nullable()->index();
            $table->foreignId('term_id')->nullable()->constrained()->nullOnDelete();
            $table->string('abbreviation')->nullable();
            $table->string('title')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->integer('sort_order')->nullable();
            $table->boolean('suppress_grades')->default(false);
            $table->boolean('suppress_percents')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reporting_terms');
    }
};
