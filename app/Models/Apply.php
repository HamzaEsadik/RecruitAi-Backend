<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Apply extends Model
{
    protected $fillable = [
        'name',
        'email',
        'phone',
        'resume-path',
        'post_id'
    ];

    protected $casts = [
        'post_id' => 'integer'
    ];

    public function detail()
    {
        return $this->hasOne(Detail::class);
    }

    public function post()
    {
        return $this->belongsTo(Post::class);
    }
}
