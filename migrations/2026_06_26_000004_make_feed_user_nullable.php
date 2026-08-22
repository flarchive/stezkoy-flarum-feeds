<?php

use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        if (! $schema->hasTable('rss_feeds') || ! $schema->hasColumn('rss_feeds', 'user_id')) {
            return;
        }

        $connection = $schema->getConnection();

        if ($connection->getDriverName() === 'mysql') {
            $connection->statement('ALTER TABLE `'.$connection->getTablePrefix().'rss_feeds` MODIFY `user_id` BIGINT UNSIGNED NULL');
        }
    },
    'down' => function (Builder $schema) {
        if (! $schema->hasTable('rss_feeds') || ! $schema->hasColumn('rss_feeds', 'user_id')) {
            return;
        }

        $connection = $schema->getConnection();

        if ($connection->getDriverName() === 'mysql') {
            $fallbackUserId = $schema->hasTable('users')
                ? $connection->table('users')->orderBy('id')->value('id')
                : null;

            if ($fallbackUserId) {
                $connection->table('rss_feeds')
                    ->whereNull('user_id')
                    ->update(['user_id' => $fallbackUserId]);
            }

            $connection->statement('ALTER TABLE `'.$connection->getTablePrefix().'rss_feeds` MODIFY `user_id` BIGINT UNSIGNED NOT NULL');
        }
    },
];
