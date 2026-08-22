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
            $table->unsignedBigInteger('rss_feed_id');
            $table->string('title');
            $table->text('content');
            $table->string('link');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->foreign('rss_feed_id')->references('id')->on('rss_feeds')->onDelete('cascade');
        });
    },
    'down' => function (Builder $schema) {
        $schema->dropIfExists('rss_items');
    }
];
