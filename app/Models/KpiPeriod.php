<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class KpiPeriod extends Model { protected $guarded=[]; protected function casts(): array{return ['mpa_assigned_at'=>'datetime','published_at'=>'datetime'];} public function participants(): HasMany{return $this->hasMany(KpiParticipant::class);} }
