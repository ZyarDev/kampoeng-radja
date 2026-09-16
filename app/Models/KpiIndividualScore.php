<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KpiIndividualScore extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'capaian_departemen' => 'decimal:2',
            'perawatan_aset' => 'decimal:2',
            'kebersihan_kerapihan' => 'decimal:2',
            'score' => 'decimal:2',
            'parameter_snapshot' => 'array',
            'submitted_at' => 'datetime',
        ];
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(KpiParticipant::class, 'kpi_participant_id');
    }

    public function signatures()
    {
        return $this->morphMany(KpiSignature::class, 'signable');
    }
}
