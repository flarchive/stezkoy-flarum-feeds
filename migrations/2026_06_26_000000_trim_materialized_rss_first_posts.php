<?php

use Flarum\Post\CommentPost;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        if (! $schema->hasTable('rss_items') || ! $schema->hasColumn('rss_items', 'discussion_id')) {
            return;
        }

        $connection = $schema->getConnection();

        $connection->table('rss_items')
            ->whereNotNull('discussion_id')
            ->orderBy('id')
            ->select(['id', 'discussion_id', 'link'])
            ->chunkById(100, function ($items) use ($connection) {
                foreach ($items as $item) {
                    $firstPostId = $connection->table('discussions')
                        ->where('id', $item->discussion_id)
                        ->value('first_post_id');

                    if (! $firstPostId) {
                        continue;
                    }

                    /** @var CommentPost|null $post */
                    $post = CommentPost::query()->whereKey($firstPostId)->first();

                    if (! $post) {
                        continue;
                    }

                    $post->setContentAttribute(trim((string) $item->link));
                    $post->save();
                }
            }, 'id');
    },
    'down' => function (Builder $schema) {
        // This migration intentionally does not restore RSS excerpts into existing posts.
    },
];
