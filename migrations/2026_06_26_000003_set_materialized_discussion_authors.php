<?php

use Flarum\Discussion\Discussion;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        if (
            ! $schema->hasTable('rss_items')
            || ! $schema->hasTable('rss_feeds')
            || ! $schema->hasTable('users')
            || ! $schema->hasTable('discussions')
            || ! $schema->hasTable('posts')
            || ! $schema->hasColumn('rss_items', 'discussion_id')
            || ! $schema->hasColumn('rss_items', 'rss_feed_id')
            || ! $schema->hasColumn('rss_feeds', 'user_id')
        ) {
            return;
        }

        $connection = $schema->getConnection();

        $connection->table('rss_items')
            ->join('rss_feeds', 'rss_feeds.id', '=', 'rss_items.rss_feed_id')
            ->join('users', 'users.id', '=', 'rss_feeds.user_id')
            ->join('discussions', 'discussions.id', '=', 'rss_items.discussion_id')
            ->whereNotNull('rss_items.discussion_id')
            ->orderBy('rss_items.id')
            ->select([
                'rss_items.id as rss_item_id',
                'rss_items.discussion_id',
                'rss_feeds.user_id',
                'discussions.first_post_id',
            ])
            ->chunkById(100, function ($items) use ($connection) {
                foreach ($items as $item) {
                    $userId = (int) $item->user_id;

                    $connection->table('discussions')
                        ->where('id', $item->discussion_id)
                        ->update(['user_id' => $userId]);

                    $firstPostId = $item->first_post_id ?: $connection->table('posts')
                        ->where('discussion_id', $item->discussion_id)
                        ->where('number', 1)
                        ->value('id');

                    if ($firstPostId) {
                        $connection->table('posts')
                            ->where('id', $firstPostId)
                            ->update(['user_id' => $userId]);
                    }

                    /** @var Discussion|null $discussion */
                    $discussion = Discussion::query()->whereKey($item->discussion_id)->first();

                    if ($discussion) {
                        $discussion->refreshParticipantCount()->save();
                    }
                }
            }, 'rss_items.id', 'rss_item_id');
    },
    'down' => function (Builder $schema) {
        // Existing RSS discussions keep their author metadata.
    },
];
