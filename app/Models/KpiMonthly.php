<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KpiMonthly extends Model
{
    protected $guarded = [];

    protected $casts = [
        'kinerja_operasional' => 'float',
        'sikap_kerja' => 'float',
        'team_work' => 'float',
        'inisiatif' => 'float',
        'kepemimpinan' => 'float',
        'mpa_score' => 'float',
        'attendance_score' => 'float',
        'reward_punishment_score' => 'float',
        'takeover_at' => 'datetime',
        'completed_at' => 'datetime',
        'published_at' => 'datetime',
        'late_completed' => 'boolean',
        'late_published' => 'boolean',
    ];

    public function participant(): BelongsTo
    {
        return $this->belongsTo(KpiParticipant::class, 'kpi_participant_id');
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluator_id');
    }

    public function takeoverBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'takeover_by');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function attendanceAdjustments(): HasMany
    {
        return $this->hasMany(KpiAttendanceAdjustment::class, 'monthly_id');
    }

    public function rewardPunishments(): HasMany
    {
        return $this->hasMany(KpiRewardPunishment::class, 'monthly_id');
    }
}

