<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('kpi_individual_scores', 'parameter_snapshot')) {
            Schema::table('kpi_individual_scores', function (Blueprint $table) {
                $table->json('parameter_snapshot')->nullable()->after('keterangan_kebersihan');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('kpi_individual_scores', 'parameter_snapshot')) {
            Schema::table('kpi_individual_scores', fn (Blueprint $table) => $table->dropColumn('parameter_snapshot'));
        }
    }
};
