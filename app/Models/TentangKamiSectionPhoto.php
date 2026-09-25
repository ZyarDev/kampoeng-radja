<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TentangKamiSectionPhoto extends Model
{
    protected $table = 'tentang_kami_section_photos';
    protected $fillable = ['section_id', 'foto', 'urutan'];
    public function section(): BelongsTo { return $this->belongsTo(TentangKamiSection::class, 'section_id'); }
}
