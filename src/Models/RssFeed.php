<?php

namespace Stezkoy\Rss\Models;

use Flarum\Database\AbstractModel;
use Flarum\User\User;

class RssFeed extends AbstractModel
{
    protected $table = 'rss_feeds';

    protected $fillable = ['url', 'title', 'user_id', 'status'];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
