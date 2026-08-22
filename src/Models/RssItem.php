<?php

namespace Stezkoy\Rss\Models;

use Flarum\Database\AbstractModel;
use Flarum\Discussion\Discussion;

class RssItem extends AbstractModel
{
    protected $table = 'rss_items';
    protected $fillable = ['rss_feed_id', 'title', 'content', 'link', 'discussion_id', 'published_at'];

    protected $casts = [
        'discussion_id' => 'integer',
        'published_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function feed()
    {
        return $this->belongsTo(RssFeed::class, 'rss_feed_id');
    }

    public function discussion()
    {
        return $this->belongsTo(Discussion::class, 'discussion_id');
    }
}
