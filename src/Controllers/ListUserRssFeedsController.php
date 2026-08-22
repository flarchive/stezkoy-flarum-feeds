<?php

namespace Stezkoy\Rss\Controllers;

use Flarum\Http\RequestUtil;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Stezkoy\Rss\Models\RssFeed;

use function Tobyz\JsonApiServer\json_api_response;

class ListUserRssFeedsController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);

        $actor->assertRegistered();

        $feeds = RssFeed::where('user_id', $actor->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (RssFeed $feed) => [
                'type' => 'rss-feeds',
                'id' => (string) $feed->id,
                'attributes' => [
                    'title' => $feed->title,
                    'url' => $feed->url,
                    'user_id' => $feed->user_id,
                    'status' => $feed->status,
                    'created_at' => $feed->created_at?->toJSON(),
                ],
            ])
            ->all();

        return json_api_response(['data' => $feeds]);
    }
}
