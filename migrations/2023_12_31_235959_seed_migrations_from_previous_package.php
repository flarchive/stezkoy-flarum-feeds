<?php

use Illuminate\Database\Schema\Builder;

/*
 * Marks all migrations of the previous extension package
 * (shebaoting/flarum-feeds) as already executed for the renamed
 * stezkoy/flarum-feeds package, so they are never re-run on
 * upgraded installs.
 */

return [
    'up' => function (Builder $schema) {
        if (! $schema->hasTable('migrations') || ! $schema->hasColumn('migrations', 'filename')) {
            return;
        }

        $connection = $schema->getConnection();
        $oldExtension = 'shebaoting-flarum-feeds';
        $newExtension = 'stezkoy-flarum-feeds';

        $hasExtensionColumn = $schema->hasColumn('migrations', 'extension');

        $query = $connection->table('migrations');

        if ($hasExtensionColumn) {
            $query->where('extension', $oldExtension);
        } else {
            $query->where('filename', 'like', $oldExtension.'_%');
        }

        $oldMigrations = $query->orderBy('id')->get();

        foreach ($oldMigrations as $oldMigration) {
            $alreadySeeded = $connection->table('migrations')
                ->where('filename', $oldMigration->filename);

            if ($hasExtensionColumn) {
                $alreadySeeded->where('extension', $newExtension);
            }

            if ($alreadySeeded->exists()) {
                continue;
            }

            $data = (array) $oldMigration;
            unset($data['id']);

            if ($hasExtensionColumn) {
                $data['extension'] = $newExtension;
            }

            $connection->table('migrations')->insert($data);
        }
    },

    'down' => function (Builder $schema) {
        // Seeded migration records intentionally stay.
    },
];
