<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkCalendarOverride extends Model
{
    protected $fillable = ['tanggal', 'is_working_day', 'keterangan', 'created_by'];

    protected $casts = [
        'tanggal' => 'date:Y-m-d',
        'is_working_day' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
