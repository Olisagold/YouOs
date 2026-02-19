<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Decision extends Model
{
    use HasFactory;

    public const CATEGORIES = ['financial', 'health', 'work', 'social', 'mindset', 'other'];

    protected $fillable = [
        'user_id',
        'category',
        'context_json',
        'ai_response_json',
        'final_choice',
        'outcome_notes',
    ];

    protected function casts(): array
    {
        return [
            'context_json' => 'array',
            'ai_response_json' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function disciplineLogs(): HasMany
    {
        return $this->hasMany(DisciplineLog::class);
    }
}
