<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

if (! function_exists('rss_items_discussion_id_foreign_exists')) {
    function rss_items_discussion_id_foreign_exists(Builder $schema): bool
    {
        $connection = $schema->getConnection();
        $table = $connection->getTablePrefix().'rss_items';

        if ($connection->getDriverName() === 'mysql') {
            return (bool) $connection->selectOne(
                'SELECT COUNT(*) AS aggregate FROM information_schema.KEY_COLUMN_USAGE'
                .' WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
                .' AND REFERENCED_TABLE_NAME = ?',
                [$table, 'discussion_id', $connection->getTablePrefix().'discussions']
            )->aggregate;
        }

        if ($connection->getDriverName() === 'sqlite') {
            foreach ($connection->select('PRAGMA foreign_key_list('.$table.')') as $foreignKey) {
                if (($foreignKey->from ?? null) === 'discussion_id' && ($foreignKey->table ?? null) === 'discussions') {
                    return true;
                }
            }
        }

        return false;
    }
}

return [
    'up' => function (Builder $schema) {
        if (! $schema->hasTable('rss_items')) {
            return;
        }

        $connection = $schema->getConnection();

        if (! $schema->hasColumn('rss_items', 'discussion_id')) {
            $schema->table('rss_items', function (Blueprint $table) {
                $table->unsignedInteger('discussion_id')->nullable()->after('link');
            });
        } elseif ($connection->getDriverName() === 'mysql') {
            $connection->statement(
                'ALTER TABLE `'.$connection->getTablePrefix().'rss_items` MODIFY `discussion_id` INT UNSIGNED NULL'
            );
        }

        if (rss_items_discussion_id_foreign_exists($schema)) {
            return;
        }

        $schema->table('rss_items', function (Blueprint $table) {
            $table->foreign('discussion_id')->references('id')->on('discussions')->nullOnDelete();
        });
    },

    'down' => function (Builder $schema) {
        if (
            ! $schema->hasTable('rss_items')
            || ! $schema->hasColumn('rss_items', 'discussion_id')
            || ! rss_items_discussion_id_foreign_exists($schema)
        ) {
            return;
        }

        $schema->table('rss_items', function (Blueprint $table) {
            $table->dropForeign(['discussion_id']);
            $table->dropColumn('discussion_id');
        });
    },
];
