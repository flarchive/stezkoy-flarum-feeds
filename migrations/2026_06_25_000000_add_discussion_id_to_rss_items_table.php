<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        if (! $schema->hasTable('rss_items')) {
            return;
        }

        if (! $schema->hasColumn('rss_items', 'discussion_id')) {
            $schema->table('rss_items', function (Blueprint $table) {
                $table->unsignedInteger('discussion_id')->nullable()->after('link');
            });
        } elseif ($schema->getConnection()->getDriverName() === 'mysql') {
            $schema->getConnection()->statement('ALTER TABLE `rss_items` MODIFY `discussion_id` INT UNSIGNED NULL');
        }

        $schema->table('rss_items', function (Blueprint $table) {
            $table->foreign('discussion_id')->references('id')->on('discussions')->nullOnDelete();
        });
    },
    'down' => function (Builder $schema) {
        if (! $schema->hasTable('rss_items') || ! $schema->hasColumn('rss_items', 'discussion_id')) {
            return;
        }

        $schema->table('rss_items', function (Blueprint $table) {
            $table->dropForeign(['discussion_id']);
            $table->dropColumn('discussion_id');
        });
    },
];
