<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KpiIndividualScore extends Model
{
    protected $guarded = [];

    protected $casts = [
        'submitted_at' => 'datetime',
        'capaian_kinerja' => 'float',
        'pemeliharaan_aset' => 'float',
        'kebersihan_kerapihan' => 'float',
        'total_ki_raw' => 'float',
        'score_ki_final' => 'float',
    ];

    public function participant(): BelongsTo
    {
        return $this->belongsTo(KpiParticipant::class, 'kpi_participant_id');
    }
}

