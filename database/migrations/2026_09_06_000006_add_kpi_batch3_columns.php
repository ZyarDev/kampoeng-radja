<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kpi_final_scores', function (Blueprint $t) {
            if (! Schema::hasColumn('kpi_final_scores', 'late_finalization')) {
                $t->boolean('late_finalization')->default(false)->after('status');
            }
            if (! Schema::hasColumn('kpi_final_scores', 'calculated_at')) {
                $t->timestamp('calculated_at')->nullable()->after('late_finalization');
            }
        });
    }

    public function down(): void
    {
        Schema::table('kpi_final_scores', function (Blueprint $t) {
            $t->dropColumn(['late_finalization', 'calculated_at']);
        });
    }
};
