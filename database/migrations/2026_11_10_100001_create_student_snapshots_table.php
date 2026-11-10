<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per kid per district-local day, overwritten by later syncs that
     * day, so the morning email can say whether things are getting better.
     */
    public function up(): void
    {
        Schema::create('student_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->date('snapshot_on');
            $table->unsignedInteger('missing_count')->default(0);
            $table->unsignedInteger('late_count')->default(0);
            $table->decimal('average_percent', 6, 2)->nullable();
            $table->unsignedInteger('graded_classes')->default(0);
            $table->timestamps();

            $table->unique(['student_id', 'snapshot_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_snapshots');
    }
};
