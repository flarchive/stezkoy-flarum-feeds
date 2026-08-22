<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        if ($schema->hasTable('rss_feeds')) {
            return;
        }

        $schema->create('rss_feeds', function (Blueprint $table) {
            $table->id();
            $table->string('url');
            $table->string('title');
            $table->unsignedBigInteger('user_id');
            $table->string('status')->default('pending');
            $table->timestamps();
        });
    },
    'down' => function (Builder $schema) {
        $schema->dropIfExists('rss_feeds');
    }
];
