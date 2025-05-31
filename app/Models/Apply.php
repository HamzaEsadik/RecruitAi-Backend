<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Apply extends Model
{
    protected $fillable = [
        'name',
        'email',
        'phone',
        'resume-path',
        'post_id',
        'is_favorite'
    ];

    protected $casts = [
        'post_id' => 'integer',
        'is_favorite' => 'boolean'
    ];

    public function detail(): HasOne
    {
        return $this->hasOne(Detail::class);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
