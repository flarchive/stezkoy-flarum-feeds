<?php

namespace Stezkoy\Rss\Console;

use FeedIo\FeedIo;
use FeedIo\Adapter\Http\Client as FeedIoHttpClient;
use GuzzleHttp\Client as GuzzleClient;
use Psr\Log\NullLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Stezkoy\Rss\Models\RssFeed;
use Stezkoy\Rss\Models\RssItem;

class FetchRssFeeds extends Command
{
    protected $signature = 'rss:fetch {url?}';
    protected $description = 'Fetch RSS/Atom feeds and store the content';

    public function handle()
    {
        $url = $this->argument('url');

        $guzzle = new GuzzleClient([
            'timeout' => 15,
            'connect_timeout' => 8,
        ]);

        $client = new FeedIoHttpClient($guzzle);

        $logger = new NullLogger();

        $feedIo = new FeedIo($client, $logger);

        if ($url) {
            $this->info("Fetching RSS feed from: $url");
            $this->fetchAndStoreFeed($feedIo, $url);
        } else {
            $this->info("No URL provided, fetching feeds from database...");

            $feeds = RssFeed::where('status', 'approved')->get();

            foreach ($feeds as $feed) {
                $this->info("Fetching RSS feed from database URL: {$feed->url}");
                $this->fetchAndStoreFeed($feedIo, $feed->url, $feed->id);
            }
        }
    }

    protected function fetchAndStoreFeed(FeedIo $feedIo, $url, $rssFeedId = null)
    {
        try {

            $result = $feedIo->read($url);

            $this->info("Successfully fetched feed from: $url");

            foreach ($result->getFeed() as $item) {

                $content = $item->getValue('content:encoded') ?: ($item->getValue('description') ?: $item->getContent());

                $this->info("Processing title: {$item->getTitle()}");
                $this->info("Processing link: {$item->getLink()}");
                if ($rssFeedId) {
                    $title = (string) $item->getTitle();
                    $link = trim((string) $item->getLink());

                    $payload = [
                        'rss_feed_id' => $rssFeedId,
                        'title' => $title,
                        'link' => $link,
                        'content' => $content ?: '',
                        'published_at' => $item->getLastModified() ?: Carbon::now(),
                    ];

                    if ($link !== '') {
                        $existing = RssItem::where('rss_feed_id', $rssFeedId)
                            ->where('link', $link)
                            ->first()
                            ?? RssItem::where('rss_feed_id', $rssFeedId)
                                ->where('title', $title)
                                ->where(fn ($query) => $query->where('link', '')->orWhereNull('link'))
                                ->first();
                    } else {
                        $existing = RssItem::where('rss_feed_id', $rssFeedId)
                            ->where('title', $title)
                            ->first();
                    }

                    if ($existing) {
                        $existing->fill($payload)->save();
                    } else {
                        RssItem::create($payload);
                    }

                    $this->info("Stored item with title: {$title}");
                } else {
                    $this->info("Title: {$item->getTitle()} | Content: " . substr($content, 0, 100) . "...");
                }
            }
        } catch (\Exception $e) {
            $this->error("Failed to fetch feed from: $url - Error: {$e->getMessage()}");
        }
    }
}
