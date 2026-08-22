<?php

namespace Stezkoy\Rss;

use Flarum\Extend;
use Flarum\Frontend\Document;
use Illuminate\Console\Scheduling\Event;
use Stezkoy\Rss\Api\Resource\RssFeedResource;
use Stezkoy\Rss\Api\Resource\RssItemResource;
use Stezkoy\Rss\Controllers\ListUserRssFeedsController;
use Stezkoy\Rss\Console\FetchRssFeeds;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__ . '/js/dist/forum.js')
        ->css(__DIR__ . '/less/forum.less')
        ->route('/feeds', 'rss.feed', function (Document $document, $request) {
            $document->title = app('translator')->trans('stezkoy-rss.forum.loading_title');
        })
        ->route('/feeds/item/{id}', 'rss.item', function (Document $document, $request) {
            $document->title = app('translator')->trans('stezkoy-rss.forum.loading_title');
        }),

    (new Extend\Frontend('admin'))
        ->js(__DIR__ . '/js/dist/admin.js')
        ->css(__DIR__ . '/less/admin.less'),

    new Extend\Locales(__DIR__ . '/locale'),

    new Extend\ApiResource(RssFeedResource::class),
    new Extend\ApiResource(RssItemResource::class),

    (new Extend\Settings())
        ->default('stezkoy-rss.show_on_index', false)
        ->default('stezkoy-rss.materialized_tag_id', '')
        ->serializeToForum('stezkoy-rss.show_on_index', 'stezkoy-rss.show_on_index', 'boolval')
        ->serializeToForum('stezkoy-rss.materialized_tag_id', 'stezkoy-rss.materialized_tag_id'),

    (new Extend\User())
        ->registerPreference('stezkoyRssShowOnIndex', 'boolval', true),

    (new Extend\Console())
        ->command(FetchRssFeeds::class)
        ->schedule('rss:fetch', function (Event $event) {
            $event->hourly();
        }),

    (new Extend\Routes('api'))
        ->get('/user-rss-feeds', 'rss.userfeeds.index', ListUserRssFeedsController::class),
];
