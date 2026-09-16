<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class KpiParticipant extends Model { protected $guarded=[]; public function period(): BelongsTo{return $this->belongsTo(KpiPeriod::class,'kpi_period_id');} public function karyawan(): BelongsTo{return $this->belongsTo(Karyawan::class);} public function atasanLangsung(): BelongsTo{return $this->belongsTo(Karyawan::class,'atasan_langsung_id');} }
