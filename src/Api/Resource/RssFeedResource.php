<?php

namespace Stezkoy\Rss\Api\Resource;

use FeedIo\Adapter\Http\Client as FeedIoHttpClient;
use FeedIo\FeedIo;
use Flarum\Api\Context as FlarumContext;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Flarum\Discussion\Discussion;
use Flarum\Foundation\ValidationException;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Psr\Log\NullLogger;
use Stezkoy\Rss\Models\RssFeed;
use Stezkoy\Rss\Models\RssItem;
use Tobyz\JsonApiServer\Context;

class RssFeedResource extends AbstractDatabaseResource
{
    public function type(): string
    {
        return 'rss-feeds';
    }

    public function model(): string
    {
        return RssFeed::class;
    }

    public function scope(Builder $query, Context $context): void
    {
        $query
            ->with('user')
            ->orderByDesc('created_at');
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Show::make()
                ->authenticated()
                ->defaultInclude(['user'])
                ->eagerLoad(['user'])
                ->visible(fn (RssFeed $feed, FlarumContext $context) => $this->canManage($feed, $context)),
            Endpoint\Create::make()
                ->authenticated()
                ->defaultInclude(['user'])
                ->eagerLoad(['user']),
            Endpoint\Update::make()
                ->authenticated()
                ->defaultInclude(['user'])
                ->eagerLoad(['user'])
                ->visible(fn (RssFeed $feed, FlarumContext $context) => $this->canManage($feed, $context)),
            Endpoint\Delete::make()
                ->authenticated()
                ->visible(fn (RssFeed $feed, FlarumContext $context) => $this->canManage($feed, $context)),
            Endpoint\Index::make()
                ->authenticated()
                ->defaultInclude(['user'])
                ->eagerLoad(['user'])
                ->visible(fn (FlarumContext $context) => $context->getActor()->isAdmin()),
            Endpoint\Endpoint::make('preview')
                ->route('GET', '/{id}/preview')
                ->authenticated()
                ->admin()
                ->action(fn (FlarumContext $context): array => $this->preview($context)),
        ];
    }

    public function fields(): array
    {
        return [
            Schema\Str::make('title')
                ->requiredOnCreate()
                ->maxLength(255)
                ->writable(fn (RssFeed $feed, FlarumContext $context) => $this->canManage($feed, $context)),
            Schema\Str::make('url')
                ->requiredOnCreate()
                ->maxLength(2048)
                ->rule('url')
                ->writable(fn (RssFeed $feed, FlarumContext $context) => $this->canManage($feed, $context)),
            Schema\Integer::make('user_id')
                ->nullable()
                ->rule('exists:users,id')
                ->writable(fn (RssFeed $feed, FlarumContext $context) => $context->getActor()->isAdmin()),
            Schema\Str::make('status')
                ->default('pending')
                ->in(['pending', 'approved'])
                ->writable(fn (RssFeed $feed, FlarumContext $context) => $context->getActor()->isAdmin()),
            Schema\DateTime::make('created_at'),
            Schema\Relationship\ToOne::make('user')
                ->type('users')
                ->includable(),
        ];
    }

    public function creating(object $model, Context $context): ?object
    {
        $attributes = $context->body()['data']['attributes'] ?? [];

        if (! array_key_exists('user_id', $attributes)) {
            $model->user_id ??= $context->getActor()->id;
        }

        $model->status ??= 'pending';

        return $model;
    }

    public function updated(object $model, Context $context): ?object
    {
        $this->syncMaterializedDiscussionAuthors($model);

        return $model;
    }

    private function preview(FlarumContext $context): array
    {
        $feed = $context->model;

        try {
            $result = $this->feedIo()->read($feed->url);
            $items = [];

            foreach ($result->getFeed() as $item) {
                $content = $item->getValue('content:encoded') ?: $item->getContent();
                $publishedAt = $item->getLastModified();

                $items[] = [
                    'title' => (string) $item->getTitle(),
                    'link' => (string) $item->getLink(),
                    'published_at' => $publishedAt instanceof \DateTimeInterface ? $publishedAt->format(DATE_ATOM) : null,
                    'excerpt' => $this->plainText((string) $content, 180),
                ];

                if (count($items) >= 20) {
                    break;
                }
            }
        } catch (\Throwable $e) {
            throw new ValidationException([
                'url' => $e->getMessage(),
            ]);
        }

        return [
            'data' => [
                'type' => 'rss-feed-previews',
                'id' => (string) $feed->id,
                'attributes' => [
                    'title' => $feed->title,
                    'url' => $feed->url,
                    'items' => $items,
                ],
            ],
        ];
    }

    private function feedIo(): FeedIo
    {
        $guzzle = new GuzzleClient([
            'timeout' => 15,
            'connect_timeout' => 8,
        ]);

        return new FeedIo(
            new FeedIoHttpClient($guzzle),
            new NullLogger()
        );
    }

    private function plainText(string $content, int $limit): string
    {
        $text = trim(html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $text = preg_replace('/\s+/u', ' ', $text);

        return Str::limit($text, $limit);
    }

    private function syncMaterializedDiscussionAuthors(RssFeed $feed): void
    {
        RssItem::query()
            ->where('rss_feed_id', $feed->id)
            ->whereNotNull('discussion_id')
            ->select(['id', 'discussion_id'])
            ->chunkById(100, function ($items) use ($feed) {
                foreach ($items as $item) {
                    $discussion = Discussion::query()->whereKey($item->discussion_id)->first();

                    if (! $discussion) {
                        continue;
                    }

                    $discussion->user_id = $feed->user_id;

                    if ($discussion->first_post_id) {
                        $discussion->firstPost()->update(['user_id' => $feed->user_id]);
                    }

                    $discussion->refreshParticipantCount()->save();
                }
            });
    }

    private function canManage(RssFeed $feed, FlarumContext $context): bool
    {
        $actor = $context->getActor();

        return $actor->isAdmin() || ! $feed->exists || (int) $feed->user_id === (int) $actor->id;
    }
}
