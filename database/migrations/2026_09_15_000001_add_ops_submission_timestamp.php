<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('kpi_ops_items', 'submitted_at')) {
            Schema::table('kpi_ops_items', function (Blueprint $table) {
                $table->timestamp('submitted_at')->nullable()->after('submit_type');
            });
        }
    }
    public function down(): void
    {
        if (Schema::hasColumn('kpi_ops_items', 'submitted_at')) {
            Schema::table('kpi_ops_items', fn (Blueprint $table) => $table->dropColumn('submitted_at'));
        }
    }
};
