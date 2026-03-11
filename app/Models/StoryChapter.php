<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StoryChapter extends Model
{
    protected $fillable = ['memorial_id', 'title', 'description', 'sort_order'];

    public function memorial(): BelongsTo
    {
        return $this->belongsTo(Memorial::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'story_chapter_id');
    }
}
