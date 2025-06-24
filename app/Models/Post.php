<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'token',
        'title',
        'description',
        'skills',
        'city',
        'min_experience',
        'access_token',
        'share',
        'dashboard',
        'share_route',
        'dashboard_route'
    ];

    protected $casts = [
        'skills' => 'array',
        'min_experience' => 'integer'
    ];

    protected $dates = ['deleted_at'];
    
    /**
     * Get the applies for the post.
     */
    public function applies(): HasMany
    {
        return $this->hasMany(Apply::class);
    }
}
