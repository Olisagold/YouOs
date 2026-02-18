<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class DailyCheckin extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'checkin_date',
        'energy',
        'mood',
        'missions_json',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'checkin_date' => 'date',
            'energy' => 'integer',
            'mood' => 'integer',
            'missions_json' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
