<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Detail extends Model
{
    protected $fillable = [
        'skills',
        'experience',
        'skills_match',
        'ai_score',
        'interview',
        'apply_id'
    ];

    protected $casts = [
        'skills' => 'array',
        'experience' => 'integer',
        'skills_match' => 'float',
        'ai_score' => 'float',
        'interview' => 'array',
        'apply_id' => 'integer'
    ];

    public function apply(): BelongsTo
    {
        return $this->belongsTo(Apply::class);
    }
}
