<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kpi_monthlies', function (Blueprint $t) {
            if (! Schema::hasColumn('kpi_monthlies', 'completed_at')) {
                $t->timestamp('completed_at')->nullable()->after('status');
            }
            if (! Schema::hasColumn('kpi_monthlies', 'completed_by')) {
                $t->foreignId('completed_by')->nullable()->after('completed_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('kpi_monthlies', 'late_completed')) {
                $t->boolean('late_completed')->default(false)->after('completed_by');
            }
            if (! Schema::hasColumn('kpi_monthlies', 'published_at')) {
                $t->timestamp('published_at')->nullable()->after('late_completed');
            }
            if (! Schema::hasColumn('kpi_monthlies', 'published_by')) {
                $t->foreignId('published_by')->nullable()->after('published_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('kpi_monthlies', 'late_published')) {
                $t->boolean('late_published')->default(false)->after('published_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('kpi_monthlies', function (Blueprint $t) {
            $t->dropForeign(['completed_by']);
            $t->dropForeign(['published_by']);
            $t->dropColumn(['completed_at', 'completed_by', 'late_completed', 'published_at', 'published_by', 'late_published']);
        });
    }
};
