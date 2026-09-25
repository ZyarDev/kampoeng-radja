<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'role';

    protected $fillable = ['nama_role'];

    public function jabatan(): HasMany
    {
        return $this->hasMany(Jabatan::class);
    }
}
