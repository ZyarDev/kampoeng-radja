<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class KpiSignature extends Model
{
    protected $guarded = [];

    protected $casts = [
        'signed_at' => 'datetime',
    ];
}
