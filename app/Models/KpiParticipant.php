<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
class KpiParticipant extends Model
{
    protected $guarded = [];

    public function period(): BelongsTo
    {
        return $this->belongsTo(KpiPeriod::class, 'kpi_period_id');
    }

    public function karyawan(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class);
    }

    public function atasanLangsung(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class, 'atasan_langsung_id');
    }

    public function atasanKedua(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class, 'atasan_kedua_id');
    }

    public function opsItems(): HasMany
    {
        return $this->hasMany(KpiOpsItem::class, 'kpi_participant_id');
    }

    public function individualScore(): HasOne
    {
        return $this->hasOne(KpiIndividualScore::class, 'kpi_participant_id');
    }

    public function monthly(): HasOne
    {
        return $this->hasOne(KpiMonthly::class, 'kpi_participant_id');
    }

    public function signatures(): MorphMany
    {
        return $this->morphMany(KpiSignature::class, 'signable');
    }
}
