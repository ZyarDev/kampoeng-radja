<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KpiRewardPunishment extends Model
{
    protected $guarded = [];

    protected $casts = [
        'jumlah' => 'integer',
        'nilai' => 'float',
    ];

    public function monthly(): BelongsTo
    {
        return $this->belongsTo(KpiMonthly::class, 'monthly_id');
    }
}
