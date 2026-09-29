<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kpi_monthlies', function (Blueprint $table): void {
            if (! Schema::hasColumn('kpi_monthlies', 'hrd_initial_completed_at')) {
                $table->timestamp('hrd_initial_completed_at')->nullable()->after('completed_at');
            }
            if (! Schema::hasColumn('kpi_monthlies', 'hrd_initial_completed_by')) {
                $table->foreignId('hrd_initial_completed_by')->nullable()->after('hrd_initial_completed_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('kpi_monthlies', 'evaluator_started_at')) {
                $table->timestamp('evaluator_started_at')->nullable()->after('hrd_initial_completed_by');
            }
            if (! Schema::hasColumn('kpi_monthlies', 'evaluator_completed_at')) {
                $table->timestamp('evaluator_completed_at')->nullable()->after('evaluator_started_at');
            }
            if (! Schema::hasColumn('kpi_monthlies', 'evaluator_completed_by')) {
                $table->foreignId('evaluator_completed_by')->nullable()->after('evaluator_completed_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('kpi_monthlies', 'hrd_finalized_at')) {
                $table->timestamp('hrd_finalized_at')->nullable()->after('evaluator_completed_by');
            }
            if (! Schema::hasColumn('kpi_monthlies', 'hrd_finalized_by')) {
                $table->foreignId('hrd_finalized_by')->nullable()->after('hrd_finalized_at')->constrained('users')->nullOnDelete();
            }
        });

        // Preserve the meaning of existing records while making the new
        // stage markers explicit for records created before this migration.
        Schema::table('kpi_monthlies', function (Blueprint $table): void {
            // Backfill is performed with SQL below so this migration remains
            // safe when the table already contains historical KPI records.
        });
        \DB::statement("UPDATE kpi_monthlies SET hrd_initial_completed_at = completed_at WHERE hrd_initial_completed_at IS NULL AND completed_at IS NOT NULL AND attendance_score IS NOT NULL AND reward_punishment_score IS NOT NULL");
        \DB::statement("UPDATE kpi_monthlies SET evaluator_completed_at = completed_at, evaluator_completed_by = completed_by WHERE evaluator_completed_at IS NULL AND completed_at IS NOT NULL AND status IN ('completed','published')");
        \DB::statement("UPDATE kpi_monthlies SET hrd_finalized_at = completed_at, hrd_finalized_by = completed_by WHERE hrd_finalized_at IS NULL AND completed_at IS NOT NULL AND status IN ('completed','published')");
    }

    public function down(): void
    {
        // Stage columns are part of the consolidated kpi_monthlies base
        // schema. This migration now only performs historical backfill.
    }
};
