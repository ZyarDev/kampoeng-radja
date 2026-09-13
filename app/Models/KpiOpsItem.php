<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KpiOpsItem extends Model
{
    protected $guarded = [];

    protected $casts = [
        'target_item' => 'float',
        'realisasi_item' => 'float',
        'pencapaian_persen' => 'float',
        'bobot_item' => 'float',
        'nilai_item' => 'float',
    ];

    public function participant(): BelongsTo
    {
        return $this->belongsTo(KpiParticipant::class, 'kpi_participant_id');
    }
}

