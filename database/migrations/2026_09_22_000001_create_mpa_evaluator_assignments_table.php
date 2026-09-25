<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mpa_evaluator_assignments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->foreignId('evaluator_id')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['year', 'month']);
        });

        DB::table('kpi_periods')
            ->whereNotNull('mpa_evaluator_id')
            ->orderBy('id')
            ->get(['tahun', 'bulan', 'mpa_evaluator_id', 'created_at', 'updated_at'])
            ->each(function ($period): void {
                DB::table('mpa_evaluator_assignments')->updateOrInsert(
                    ['year' => $period->tahun, 'month' => $period->bulan],
                    [
                        'evaluator_id' => $period->mpa_evaluator_id,
                        'created_at' => $period->created_at ?? now(),
                        'updated_at' => $period->updated_at ?? now(),
                    ]
                );
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('mpa_evaluator_assignments');
    }
};
