<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('kpi_daily_reports', function (Blueprint $table): void {
            if (! Schema::hasColumn('kpi_daily_reports', 'approval_source')) {
                $table->string('approval_source', 20)->nullable()->after('approved_at');
            }
            if (! Schema::hasColumn('kpi_daily_reports', 'approval_signature_path')) {
                $table->string('approval_signature_path')->nullable()->after('approval_source');
            }
        });
    }

    public function down(): void
    {
        Schema::table('kpi_daily_reports', function (Blueprint $table): void {
            if (Schema::hasColumn('kpi_daily_reports', 'approval_signature_path')) {
                $table->dropColumn('approval_signature_path');
            }
            // approval_source may be owned by the earlier additive migration.
        });
    }
};
