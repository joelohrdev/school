<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Missing and late work due in the quarter the day fell in, so the
     * morning email can compare within the current quarter only.
     */
    public function up(): void
    {
        Schema::table('student_snapshots', function (Blueprint $table) {
            $table->unsignedInteger('quarter_missing_count')->nullable()->after('late_count');
            $table->unsignedInteger('quarter_late_count')->nullable()->after('quarter_missing_count');
        });
    }

    public function down(): void
    {
        Schema::table('student_snapshots', function (Blueprint $table) {
            $table->dropColumn(['quarter_missing_count', 'quarter_late_count']);
        });
    }
};
