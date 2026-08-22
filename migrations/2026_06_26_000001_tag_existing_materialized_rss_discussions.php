<?php

use Flarum\Discussion\Discussion;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\Tags\Tag;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        if (
            ! $schema->hasTable('rss_items')
            || ! $schema->hasColumn('rss_items', 'discussion_id')
            || ! class_exists(Tag::class)
        ) {
            return;
        }

        $tagId = (int) app(SettingsRepositoryInterface::class)->get('stezkoy-rss.materialized_tag_id');

        if ($tagId <= 0) {
            return;
        }

        /** @var Tag|null $tag */
        $tag = Tag::query()->with('parent')->whereKey($tagId)->first();

        if (! $tag) {
            return;
        }

        $tags = $tag->parent_id ? new EloquentCollection([$tag->parent, $tag]) : new EloquentCollection([$tag]);
        $tags = $tags->filter()->values();
        $tagIds = $tags->pluck('id')->values()->all();

        if (! $tagIds) {
            return;
        }

        $connection = $schema->getConnection();

        $connection->table('rss_items')
            ->whereNotNull('discussion_id')
            ->orderBy('id')
            ->select(['id', 'discussion_id'])
            ->chunkById(100, function ($items) use ($connection, $tagIds, $tags) {
                foreach ($items as $item) {
                    $hasTags = $connection->table('discussion_tag')
                        ->where('discussion_id', $item->discussion_id)
                        ->exists();

                    if ($hasTags) {
                        continue;
                    }

                    /** @var Discussion|null $discussion */
                    $discussion = Discussion::query()->whereKey($item->discussion_id)->first();

                    if (! $discussion) {
                        continue;
                    }

                    try {
                        $discussion->tags()->sync($tagIds);
                    } catch (\BadMethodCallException) {
                        return;
                    }

                    $discussion->setRelation('tags', $tags);

                    foreach ($tags as $tag) {
                        if (! $discussion->is_private) {
                            $tag->discussion_count = max(0, (int) $tag->discussion_count) + 1;
                        }

                        if ($discussion->last_posted_at && (! $tag->last_posted_at || $discussion->last_posted_at >= $tag->last_posted_at)) {
                            $tag->setLastPostedDiscussion($discussion);
                        }

                        $tag->save();
                    }
                }
            }, 'id');
    },
    'down' => function (Builder $schema) {
        // Existing RSS discussions keep whichever tags they currently have.
    },
];
