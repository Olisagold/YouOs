<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class Doctrine extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'goals_json',
        'rules_json',
        'habits_json',
        'weekly_targets_json',
    ];

    protected function casts(): array
    {
        return [
            'goals_json' => 'array',
            'rules_json' => 'array',
            'habits_json' => 'array',
            'weekly_targets_json' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
