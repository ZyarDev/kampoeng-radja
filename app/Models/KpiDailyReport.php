<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KpiDailyReport extends Model
{
    protected $guarded = [];

    protected $casts = [
        'tanggal' => 'date:Y-m-d',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function participant(): BelongsTo
    {
        return $this->belongsTo(KpiParticipant::class, 'kpi_participant_id');
    }

    /**
     * Daily reports are keyed directly to karyawan.  Keep this relation
     * separate from the optional KPI participant relation so historical
     * reports remain readable even when a participant snapshot is absent.
     */
    public function karyawan(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class, 'karyawan_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(KpiDailyActivity::class, 'daily_report_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function atasanSnapshot(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class, 'atasan_snapshot_id');
    }
}
