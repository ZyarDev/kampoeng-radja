<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TentangKami extends Model
{
    protected $table = 'tentang_kami';
    protected $fillable = ['hero_judul', 'hero_subjudul', 'hero_foto', 'struktur_organisasi_foto', 'updated_by'];

    public function sections(): HasMany
    {
        return $this->hasMany(TentangKamiSection::class)->orderBy('urutan')->orderBy('id');
    }
}
