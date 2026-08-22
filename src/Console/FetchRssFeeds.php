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
    protected $signature = 'rss:fetch {url?}'; // 增加一个可选的 URL 参数
    protected $description = 'Fetch RSS/Atom feeds and store the content';

    public function handle()
    {
        $url = $this->argument('url'); // 获取传入的网址参数

        // 创建 Guzzle 客户端（实现了 PSR-18）
        $guzzle = new GuzzleClient([
            'timeout' => 15,
            'connect_timeout' => 8,
        ]);
        // 使用 FeedIo 的 HTTP 适配器
        $client = new FeedIoHttpClient($guzzle);
        // 使用 NullLogger 或自定义日志记录器
        $logger = new NullLogger();
        // 创建 FeedIo 实例
        $feedIo = new FeedIo($client, $logger);

        // 如果提供了 URL 参数，直接测试该网址
        if ($url) {
            $this->info("Fetching RSS feed from: $url");
            $this->fetchAndStoreFeed($feedIo, $url);
        } else {
            // 否则从数据库获取 URL
            $this->info("No URL provided, fetching feeds from database...");

            $feeds = RssFeed::where('status', 'approved')->get(); // 假设 'approved' 是审核通过的状态

            foreach ($feeds as $feed) {
                $this->info("Fetching RSS feed from database URL: {$feed->url}");
                $this->fetchAndStoreFeed($feedIo, $feed->url, $feed->id);
            }
        }
    }

    // 抓取和存储 feed 的逻辑提取到一个单独的方法
    protected function fetchAndStoreFeed(FeedIo $feedIo, $url, $rssFeedId = null)
    {
        try {
            // 从 URL 抓取并解析 RSS 或 Atom
            $result = $feedIo->read($url);

            $this->info("Successfully fetched feed from: $url");
            // 遍历解析的项目
            foreach ($result->getFeed() as $item) {
                // 获取 `<content:encoded>` 标签内容
                $content = $item->getValue('content:encoded') ?: ($item->getValue('description') ?: $item->getContent());

                // 打印标题调试信息
                $this->info("Processing title: {$item->getTitle()}");
                $this->info("Processing link: {$item->getLink()}");
                if ($rssFeedId) {
                    $title = (string) $item->getTitle();
                    $link = trim((string) $item->getLink());

                    $payload = [
                        'rss_feed_id' => $rssFeedId,
                        'title' => $title,
                        'link' => $link,
                        'content' => $content ?: '', // 如果 content 为空，则设置为空字符串
                        'published_at' => $item->getLastModified() ?: Carbon::now(),
                    ];

                    // 优先按链接匹配；找不到时回退到没有链接的旧记录（按标题），避免重复
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
                    // 只打印信息，不插入数据库
                    $this->info("Title: {$item->getTitle()} | Content: " . substr($content, 0, 100) . "...");
                }
            }
        } catch (\Exception $e) {
            // 处理错误，例如记录日志或通知管理员
            $this->error("Failed to fetch feed from: $url - Error: {$e->getMessage()}");
        }
    }
}
