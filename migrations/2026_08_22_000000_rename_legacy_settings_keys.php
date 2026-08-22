<?php

use Illuminate\Database\Schema\Builder;

/*
 * Renames leftover database identifiers from the previous
 * shebaoting/flarum-feeds package: settings keys
 * (shebaoting-rss.*) and the per-user index preference.
 */

return [
    'up' => function (Builder $schema) {
        $connection = $schema->getConnection();

        $settingsRows = $connection->table('settings')
            ->where('key', 'like', 'shebaoting-rss.%')
            ->get();

        foreach ($settingsRows as $row) {
            $newKey = 'stezkoy-rss.'.substr($row->key, strlen('shebaoting-rss.'));

            if ($connection->table('settings')->where('key', $newKey)->exists()) {
                $connection->table('settings')->where('key', $row->key)->delete();
            } else {
                $connection->table('settings')->where('key', $row->key)->update(['key' => $newKey]);
            }
        }

        $preferenceUsers = $connection->table('users')
            ->where('preferences', 'like', '%shebaotingRssShowOnIndex%')
            ->orderBy('id')
            ->select(['id', 'preferences']);

        $preferenceUsers->chunkById(100, function ($users) use ($connection) {
            foreach ($users as $user) {
                $connection->table('users')
                    ->where('id', $user->id)
                    ->update([
                        'preferences' => str_replace(
                            'shebaotingRssShowOnIndex',
                            'stezkoyRssShowOnIndex',
                            (string) $user->preferences
                        ),
                    ]);
            }
        }, 'id');
    },

    'down' => function (Builder $schema) {
        // Renamed keys intentionally stay.
    },
];
