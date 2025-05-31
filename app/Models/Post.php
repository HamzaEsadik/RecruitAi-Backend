<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Post extends Model
{
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
    
    /**
     * Get the applies for the post.
     */
    public function applies(): HasMany
    {
        return $this->hasMany(Apply::class);
    }
}
