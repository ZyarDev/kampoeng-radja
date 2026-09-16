<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KpiOpsItem extends Model
{
    protected $guarded = [];

    protected $casts = [
        'target_unit' => 'decimal:4',
        'target_bulanan' => 'decimal:4',
        'beban_target' => 'decimal:4',
        'hasil' => 'decimal:4',
        'nilai_item' => 'float',
        'submitted_at' => 'datetime',
    ];

    public function participant(): BelongsTo
    {
        return $this->belongsTo(KpiParticipant::class, 'kpi_participant_id');
    }
}
