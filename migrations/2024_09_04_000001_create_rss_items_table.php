<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        if ($schema->hasTable('rss_items')) {
            return;
        }

        $schema->create('rss_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rss_feed_id'); // 关联到 rss_feeds 表
            $table->string('title');
            $table->text('content'); // 存储抓取的内容
            $table->string('link'); // 原始文章链接
            $table->timestamp('published_at')->nullable(); // 文章发布日期
            $table->timestamps();

            // 外键约束，确保 rss_feed_id 是合法的
            $table->foreign('rss_feed_id')->references('id')->on('rss_feeds')->onDelete('cascade');
        });
    },
    'down' => function (Builder $schema) {
        $schema->dropIfExists('rss_items');
    }
];
