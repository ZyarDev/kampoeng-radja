<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('kpi_individual_scores', 'value_locked')) {
            Schema::table('kpi_individual_scores', function (Blueprint $table): void {
                $table->boolean('value_locked')->default(false)->after('status');
                $table->decimal('corrected_score', 8, 2)->nullable()->after('score');
                $table->unsignedInteger('correction_revision')->default(0)->after('corrected_score');
                $table->boolean('correction_pending_reapproval')->default(false)->after('correction_revision');
                $table->foreignId('corrected_by_user_id')->nullable()->after('correction_pending_reapproval')->constrained('users')->nullOnDelete();
                $table->timestamp('corrected_at')->nullable()->after('corrected_by_user_id');
                $table->text('correction_reason')->nullable()->after('corrected_at');
            });
        }

        if (! Schema::hasColumn('kpi_participants', 'ops_correction_revision')) {
            Schema::table('kpi_participants', function (Blueprint $table): void {
                $table->unsignedInteger('ops_correction_revision')->default(0)->after('status');
                $table->boolean('ops_correction_pending_reapproval')->default(false)->after('ops_correction_revision');
                $table->foreignId('ops_corrected_by_user_id')->nullable()->after('ops_correction_pending_reapproval')->constrained('users')->nullOnDelete();
                $table->timestamp('ops_corrected_at')->nullable()->after('ops_corrected_by_user_id');
                $table->text('ops_correction_reason')->nullable()->after('ops_corrected_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('kpi_individual_scores', 'value_locked')) {
            Schema::table('kpi_individual_scores', function (Blueprint $table): void {
                $table->dropForeign(['corrected_by_user_id']);
                $table->dropColumn([
                    'value_locked', 'corrected_score', 'correction_revision',
                    'correction_pending_reapproval', 'corrected_by_user_id',
                    'corrected_at', 'correction_reason',
                ]);
            });
        }

        if (Schema::hasColumn('kpi_participants', 'ops_correction_revision')) {
            Schema::table('kpi_participants', function (Blueprint $table): void {
                $table->dropForeign(['ops_corrected_by_user_id']);
                $table->dropColumn([
                    'ops_correction_revision', 'ops_correction_pending_reapproval',
                    'ops_corrected_by_user_id', 'ops_corrected_at', 'ops_correction_reason',
                ]);
            });
        }
    }
};
