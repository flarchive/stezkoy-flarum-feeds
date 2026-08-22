<?php

use Flarum\Discussion\Discussion;
use Flarum\Tags\Tag;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        if (! $schema->hasTable('rss_items') || ! $schema->hasColumn('rss_items', 'discussion_id')) {
            return;
        }

        $connection = $schema->getConnection();
        $hasOriginalUrl = $schema->hasTable('discussions') && $schema->hasColumn('discussions', 'original_url');
        $canTag = $schema->hasTable('discussion_tag') && class_exists(Tag::class);

        $connection->table('rss_items')
            ->whereNotNull('discussion_id')
            ->orderBy('id')
            ->select(['id', 'discussion_id', 'link'])
            ->chunkById(100, function ($items) use ($connection, $hasOriginalUrl, $canTag) {
                foreach ($items as $item) {
                    if ($hasOriginalUrl) {
                        $connection->table('discussions')
                            ->where('id', $item->discussion_id)
                            ->update(['original_url' => mb_substr(trim((string) $item->link), 0, 255)]);
                    }

                    if (! $canTag) {
                        continue;
                    }

                    /** @var Discussion|null $discussion */
                    $discussion = Discussion::query()->whereKey($item->discussion_id)->first();

                    if (! $discussion) {
                        continue;
                    }

                    $tagIds = $connection->table('discussion_tag')
                        ->where('discussion_id', $discussion->id)
                        ->pluck('tag_id')
                        ->map(fn ($id) => (int) $id)
                        ->all();

                    foreach ($tagIds as $tagId) {
                        /** @var Tag|null $tag */
                        $tag = Tag::query()->with('parent')->whereKey($tagId)->first();

                        while ($tag && $tag->parent_id) {
                            /** @var Tag|null $parent */
                            $parent = $tag->parent;

                            if (! $parent) {
                                break;
                            }

                            $exists = $connection->table('discussion_tag')
                                ->where('discussion_id', $discussion->id)
                                ->where('tag_id', $parent->id)
                                ->exists();

                            if (! $exists) {
                                $connection->table('discussion_tag')->insert([
                                    'discussion_id' => $discussion->id,
                                    'tag_id' => $parent->id,
                                ]);

                                if (! $discussion->is_private) {
                                    $parent->discussion_count = max(0, (int) $parent->discussion_count) + 1;
                                }

                                if ($discussion->last_posted_at && (! $parent->last_posted_at || $discussion->last_posted_at >= $parent->last_posted_at)) {
                                    $parent->setLastPostedDiscussion($discussion);
                                }

                                $parent->save();
                            }

                            $tag = $parent;
                        }
                    }
                }
            }, 'id');
    },
    'down' => function (Builder $schema) {
        // Existing RSS discussions keep their materialized metadata.
    },
];
