<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    protected $fillable = [
        'token',
        'title',
        'description',
        'skills',
        'city',
        'min_experience',
        'education_level',
        'access_token',
        'share',
        'dashboard',
        'share_route',
        'dashboard_route'
    ];

    protected $casts = [
        'skills' => 'array',
        'min_experience' => 'integer',
        'education_level' => 'integer'
    ];
    
    /**
     * Get the applies for the post.
     */
    public function applies()
    {
        return $this->hasMany(Apply::class);
    }
}
