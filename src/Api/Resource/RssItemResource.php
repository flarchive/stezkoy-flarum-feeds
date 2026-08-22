<?php

namespace Stezkoy\Rss\Api\Resource;

use Carbon\Carbon;
use Flarum\Api\Context as FlarumContext;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Flarum\Bus\Dispatcher as BusDispatcher;
use Flarum\Discussion\Command\ReadDiscussion;
use Flarum\Discussion\Discussion;
use Flarum\Foundation\ValidationException;
use Flarum\Post\CommentPost;
use Flarum\Post\Event\Saving as PostSaving;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\Tags\Tag;
use Flarum\User\User;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Stezkoy\Rss\Models\RssItem;
use Tobyz\JsonApiServer\Context;

class RssItemResource extends AbstractDatabaseResource
{
    public function __construct(
        protected Dispatcher $events,
        protected BusDispatcher $bus,
        protected SettingsRepositoryInterface $settings
    ) {
    }

    public function type(): string
    {
        return 'rss-items';
    }

    public function model(): string
    {
        return RssItem::class;
    }

    public function scope(Builder $query, Context $context): void
    {
        $query
            ->whereHas('feed', fn (Builder $query) => $query->where('status', 'approved'))
            ->with(['feed', 'discussion'])
            ->orderByDesc('published_at')
            ->orderByDesc('id');
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Show::make(),
            Endpoint\Index::make()
                ->paginate(20, 50),
            Endpoint\Endpoint::make('comment')
                ->route('POST', '/{id}/comments')
                ->authenticated()
                ->action(fn (FlarumContext $context): array => $this->comment($context)),
        ];
    }

    public function fields(): array
    {
        return [
            Schema\Str::make('title'),
            Schema\Str::make('link'),
            Schema\Str::make('contentPlain')
                ->visible(fn (RssItem $item, FlarumContext $context) => $context->showing(self::class))
                ->get(fn (RssItem $item) => $this->plainText($item->content ?? '', 2000)),
            Schema\Str::make('contentHtml')
                ->visible(fn (RssItem $item, FlarumContext $context) => $context->showing(self::class))
                ->get(fn (RssItem $item) => $this->sanitizeHtml($item->content ?? '')),
            Schema\Str::make('published_at')
                ->get(fn (RssItem $item) => $item->published_at?->toIso8601String()),
            Schema\Integer::make('discussion_id'),
            Schema\Integer::make('comment_count')
                ->get(fn (RssItem $item) => $this->commentCount($item)),
            Schema\Str::make('site_name')
                ->get(fn (RssItem $item) => $item->feed?->title),
            Schema\Arr::make('feed')
                ->get(fn (RssItem $item) => $item->feed ? [
                    'id' => (string) $item->feed->id,
                    'title' => $item->feed->title,
                    'url' => $item->feed->url,
                ] : null),
        ];
    }

    private function comment(FlarumContext $context): array
    {
        $actor = $context->getActor();
        $actor->assertRegistered();

        $item = $context->model;
        $content = trim((string) Arr::get($context->body(), 'data.attributes.content', ''));

        if ($content === '') {
            throw new ValidationException([
                'content' => app()->translator->trans('stezkoy-rss.forum.comment.validation_required'),
            ]);
        }

        if (mb_strlen($content) > 63000) {
            throw new ValidationException([
                'content' => app()->translator->trans('stezkoy-rss.forum.comment.validation_too_long'),
            ]);
        }

        $post = $item->getConnection()->transaction(function () use ($item, $actor, $content, $context) {
            $lockedItem = RssItem::with(['feed.user', 'discussion'])
                ->whereKey($item->id)
                ->lockForUpdate()
                ->firstOrFail();

            $discussion = $lockedItem->discussion;

            if (! $discussion && $lockedItem->discussion_id) {
                $lockedItem->discussion_id = null;
                $lockedItem->save();
            }

            if (! $discussion) {
                $actor->assertCan('startDiscussion');
                $discussion = $this->createDiscussionForItem($lockedItem, $actor, $context);
            }

            $actor->assertCan('reply', $discussion);

            return $this->createUserComment($discussion, $actor, $content, $context);
        });

        return [
            'data' => [
                'type' => 'rss-item-comments',
                'id' => (string) $item->id,
                'attributes' => [
                    'discussion_id' => (int) $post->discussion_id,
                    'post_number' => (int) $post->number,
                ],
            ],
        ];
    }

    private function createDiscussionForItem(RssItem $item, $actor, FlarumContext $context): Discussion
    {
        $rssAuthor = $this->rssAuthor($item);

        $discussion = new Discussion();
        $discussion->title = $this->discussionTitle($item->title);
        $discussion->created_at = $item->published_at ?: Carbon::now();
        $discussion->user_id = $rssAuthor?->id;
        $discussion->setRelation('user', $rssAuthor);
        $this->setOriginalUrl($discussion, $item->link);
        $discussion->save();

        $articlePost = new CommentPost();
        $articlePost->discussion_id = $discussion->id;
        $articlePost->created_at = $item->published_at ?: Carbon::now();
        $articlePost->user_id = $rssAuthor?->id;
        $articlePost->ip_address = (string) $context->request->getAttribute('ipAddress', '');
        $articlePost->is_private = false;
        $articlePost->setRelation('discussion', $discussion);
        $articlePost->setRelation('user', $rssAuthor);

        $articleContent = $this->articlePostContent($item);

        if ($articleContent === '') {
            $articleContent = $this->plainText($item->content ?? '', 500) ?: $this->discussionTitle($item->title);
        }

        $articlePost->setContentAttribute($articleContent, $actor);
        $articlePost->save();
        $articlePost->releaseEvents();

        $discussion->first_post_id = $articlePost->id;
        $discussion->refreshCommentCount();
        $discussion->refreshLastPost();
        $discussion->refreshParticipantCount();
        $discussion->save();
        $this->assignConfiguredTag($discussion);

        $item->discussion_id = $discussion->id;
        $item->save();
        $item->setRelation('discussion', $discussion);

        return $discussion;
    }

    private function rssAuthor(RssItem $item): ?User
    {
        $feed = $item->feed;

        if (! $feed) {
            return null;
        }

        return $feed->user ?: null;
    }

    private function createUserComment(Discussion $discussion, $actor, string $content, FlarumContext $context): CommentPost
    {
        $post = new CommentPost();
        $post->discussion_id = $discussion->id;
        $post->user_id = $actor->id;
        $post->created_at = Carbon::now();
        $post->ip_address = (string) $context->request->getAttribute('ipAddress', '');
        $post->is_private = false;
        $post->setRelation('discussion', $discussion);
        $post->setRelation('user', $actor);

        $this->events->dispatch(new PostSaving($post, $actor, [
            'attributes' => [
                'content' => $content,
            ],
            'relationships' => [
                'discussion' => [
                    'data' => [
                        'type' => 'discussions',
                        'id' => (string) $discussion->id,
                    ],
                ],
            ],
        ]));

        $post->setContentAttribute($content, $actor);
        $post->save();

        foreach ($post->releaseEvents() as $event) {
            if (property_exists($event, 'actor') && ! $event->actor) {
                $event->actor = $actor;
            }

            $this->events->dispatch($event);
        }

        $discussion
            ->refreshCommentCount()
            ->refreshLastPost()
            ->refreshParticipantCount()
            ->save();

        if ($actor->exists) {
            $this->bus->dispatch(new ReadDiscussion($discussion->id, $actor, $post->number));
        }

        return $post;
    }

    private function discussionTitle(?string $title): string
    {
        $title = trim(preg_replace('/\s+/u', ' ', (string) $title));

        if ($title === '') {
            $title = 'RSS Article';
        }

        if (mb_strlen($title) > 80) {
            $title = rtrim(mb_substr($title, 0, 77)).'...';
        }

        while (mb_strlen($title) < 3) {
            $title .= ' RSS';
        }

        return $title;
    }

    private function articlePostContent(RssItem $item): string
    {
        $body = $this->htmlToPostContent($item->content ?? '');
        $link = trim((string) $item->link);

        if ($link !== '' && $this->showSourceLink()) {
            return $body === ''
                ? $this->sourceLinkLine($link)
                : $this->sourceLinkLine($link)."\n\n".$body;
        }

        return $body;
    }

    private function showSourceLink(): bool
    {
        $value = $this->settings->get('stezkoy-rss.show_source_link');

        if ($value === null || $value === '') {
            return true;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL);
    }

    private function sourceLinkLine(string $link): string
    {
        return sprintf(
            '[url=%s]%s[/url]',
            str_replace(['[', ']'], ['%5B', '%5D'], $link),
            app()->translator->trans('stezkoy-rss.forum.original_article_link')
        );
    }

    private function htmlToPostContent(string $html): string
    {
        $html = trim($html);

        if ($html === '') {
            return '';
        }

        $html = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', '', $html);
        $html = preg_replace('/<(iframe|object|embed|form)\b[^>]*>.*?<\/\1>/is', '', $html);
        $html = preg_replace('/<(iframe|object|embed)\b[^>]*\/?>/is', '', $html);

        $html = preg_replace_callback(
            '/<img\b[^>]*\bsrc\s*=\s*(["\'])(.*?)\1[^>]*>/is',
            function (array $m): string {
                $src = trim(html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));

                if ($src === '' || preg_match('~^(data|blob):~i', $src)) {
                    return ' ';
                }

                return "\n\n[img]".str_replace(['[', ']'], ['%5B', '%5D'], $src)."[/img]\n\n";
            },
            $html
        );

        $html = preg_replace_callback(
            '/<a\b([^>]*)>(.*?)<\/a>/is',
            function (array $m): string {
                $href = '';

                if (preg_match('/\bhref\s*=\s*(["\'])(.*?)\1/is', $m[1], $h)) {
                    $href = trim(html_entity_decode($h[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                }

                $text = trim(preg_replace('/\s+/u', ' ', strip_tags($m[2])) ?? '');

                if ($href === '' || preg_match('~^(javascript|mailto|tel):~i', $href)) {
                    return $text;
                }

                if ($text === '' || $text === $href) {
                    $text = preg_replace('~^https?://~i', '', $href);
                }

                return '[url='.str_replace(['[', ']'], ['%5B', '%5D'], $href).']'.$text.'[/url]';
            },
            $html
        );

        $html = preg_replace('/<li\b[^>]*>/i', "\n- ", $html);
        $html = preg_replace('/<br\s*\/?>/i', "\n", $html);
        $html = preg_replace('/<\/?(p|div|ul|ol|h[1-6]|blockquote|tr|table|thead|tbody|section|article|header|footer|pre|figcaption|figure|dl|dd|dt)\b[^>]*>/i', "\n\n", $html);

        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\r", '', $text);
        $text = preg_replace('/[ \t\x{00A0}]+/u', ' ', $text);
        $text = preg_replace('/ ?\n ?/', "\n", $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        $text = trim($text);

        if ($text === '') {
            return '';
        }

        $text = rtrim(Str::limit($text, 60000, '…'));

        while (mb_strlen($text) < 3) {
            $text .= ' RSS';
        }

        return $text;
    }

    private function setOriginalUrl(Discussion $discussion, ?string $url): void
    {
        if (! $discussion->getConnection()->getSchemaBuilder()->hasColumn('discussions', 'original_url')) {
            return;
        }

        $discussion->original_url = mb_substr(trim((string) $url), 0, 255);
    }

    private function plainText(string $content, int $limit): string
    {
        $text = trim(html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $text = preg_replace('/\s+/u', ' ', $text);

        return Str::limit($text, $limit);
    }

    private function sanitizeHtml(string $html): string
    {
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html);
        $html = preg_replace('/<iframe\b[^>]*>.*?<\/iframe>/is', '', $html);
        $html = preg_replace('/<object\b[^>]*>.*?<\/object>/is', '', $html);
        $html = preg_replace('/<embed\b[^>]*\/?>/is', '', $html);
        $html = preg_replace('/<form\b[^>]*>.*?<\/form>/is', '', $html);
        $html = preg_replace('/on\w+\s*=\s*["\'][^"\']*["\']/i', '', $html);
        $html = preg_replace('/on\w+\s*=\s*\S+/i', '', $html);
        $html = preg_replace('/javascript\s*:/i', '', $html);
        $html = preg_replace('/vbscript\s*:/i', '', $html);
        $html = preg_replace('/data\s*:[^image]/i', '', $html);

        return $html;
    }

    private function commentCount(RssItem $item): int
    {
        $discussion = $item->discussion;

        if (! $discussion) {
            return 0;
        }

        return max(0, (int) $discussion->comment_count - 1);
    }

    private function assignConfiguredTag(Discussion $discussion): void
    {
        $tagId = (int) $this->settings->get('stezkoy-rss.materialized_tag_id');

        if ($tagId <= 0 || ! class_exists(Tag::class)) {
            return;
        }

        $tag = Tag::query()->with('parent')->whereKey($tagId)->first();

        if (! $tag) {
            return;
        }

        $tags = $this->tagsWithAncestors($tag);
        $tagIds = $tags->filter()->pluck('id')->values()->all();

        if (! $tagIds) {
            return;
        }

        try {
            $discussion->tags()->sync($tagIds);
        } catch (\BadMethodCallException) {
            return;
        }

        $discussion->setRelation('tags', $tags->filter()->values());

        foreach ($tags->filter() as $tag) {
            if (! $discussion->is_private) {
                $tag->discussion_count = max(0, (int) $tag->discussion_count) + 1;
            }

            if ($discussion->last_posted_at && (! $tag->last_posted_at || $discussion->last_posted_at >= $tag->last_posted_at)) {
                $tag->setLastPostedDiscussion($discussion);
            }

            $tag->save();
        }
    }

    private function tagsWithAncestors(Tag $tag): EloquentCollection
    {
        $tags = [];
        $current = $tag;

        while ($current) {
            array_unshift($tags, $current);
            $current = $current->parent;
        }

        return (new EloquentCollection($tags))
            ->unique(fn (Tag $tag) => $tag->id)
            ->values();
    }
}
