<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Apply extends Model
{
    use SoftDeletes;

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

    protected $dates = ['deleted_at'];

    public function detail(): HasOne
    {
        return $this->hasOne(Detail::class);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
