<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Daily reports: snapshot supervisor at daily date
        Schema::table('kpi_daily_reports', function (Blueprint $t) {
            $t->foreignId('atasan_snapshot_id')->nullable()->after('approver_id')
                ->constrained('karyawan')->nullOnDelete();
        });

        // Individual scores: optional notes per component (per PNG)
        Schema::table('kpi_individual_scores', function (Blueprint $t) {
            $t->text('keterangan_capaian')->nullable()->after('kebersihan_kerapihan');
            $t->text('keterangan_aset')->nullable()->after('keterangan_capaian');
            $t->text('keterangan_kebersihan')->nullable()->after('keterangan_aset');
        });

        // OPS items: ordering, status, submit_type for auto-submit
        Schema::table('kpi_ops_items', function (Blueprint $t) {
            $t->unsignedSmallInteger('urutan')->default(0)->after('kpi_participant_id');
            $t->string('status')->default('draft')->after('nilai_item');
            $t->string('submit_type')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('kpi_daily_reports', function (Blueprint $t) {
            $t->dropForeign(['atasan_snapshot_id']);
            $t->dropColumn('atasan_snapshot_id');
        });

        Schema::table('kpi_individual_scores', function (Blueprint $t) {
            $t->dropColumn(['keterangan_capaian', 'keterangan_aset', 'keterangan_kebersihan']);
        });

        Schema::table('kpi_ops_items', function (Blueprint $t) {
            $t->dropColumn(['urutan', 'status', 'submit_type']);
        });
    }
};
