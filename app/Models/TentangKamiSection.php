<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TentangKamiSection extends Model
{
    protected $table = 'tentang_kami_sections';
    protected $fillable = ['tentang_kami_id', 'section_number', 'judul', 'deskripsi', 'urutan'];

    public function tentangKami(): BelongsTo { return $this->belongsTo(TentangKami::class); }
    public function photos(): HasMany { return $this->hasMany(TentangKamiSectionPhoto::class, 'section_id')->orderBy('urutan')->orderBy('id'); }
}
