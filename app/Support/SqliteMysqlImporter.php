<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class SqliteMysqlImporter
{
    /**
     * @return list<string>
     */
    public function importableTables(): array
    {
        return [
            'password_reset_tokens',
            'personal_access_tokens',
            'users',
            'accounts',
            'roles',
            'permissions',
            'permission_role',
            'account_users',
            'plans',
            'subscriptions',
            'shopify_stores',
            'shopify_credentials',
            'shopify_sync_logs',
            'products',
            'collections',
            'existing_shopify_blogs',
            'shopify_pages',
            'store_knowledge_bases',
            'blog_projects',
            'store_analyses',
            'keywords',
            'ai_generations',
            'blog_topics',
            'blogs',
            'blog_revisions',
            'blog_comments',
            'blog_schedules',
            'publishing_logs',
            'usage_logs',
            'activity_logs',
            'feature_modules',
            'search_console_connections',
            'search_console_properties',
            'tracked_keywords',
            'keyword_position_snapshots',
            'aeo_geo_visibility_reports',
            'aeo_geo_prompt_checks',
        ];
    }

    public function import(string $sourcePath, bool $fresh, Closure $output): void
    {
        $target = DB::connection();

        if ($target->getDriverName() !== 'mysql') {
            throw new RuntimeException('The default database connection must be MySQL before running the import.');
        }

        $resolvedPath = $this->resolveSourcePath($sourcePath);

        $this->configureSourceConnection($resolvedPath);

        $source = DB::connection('sqlite_import');
        $sourceTables = $this->sourceTables($source);
        $tables = collect($this->importableTables())
            ->filter(fn (string $table) => in_array($table, $sourceTables, true) && Schema::hasTable($table))
            ->values()
            ->all();

        if ($tables === []) {
            throw new RuntimeException('No matching import tables were found in the SQLite file.');
        }

        if (! $fresh && $this->targetHasRows($target, $tables)) {
            throw new RuntimeException('Target MySQL tables already contain data. Re-run with --fresh to clear them first.');
        }

        $this->withoutForeignKeyChecks($target, function () use ($target, $tables, $fresh, $source, $output): void {
            if ($fresh) {
                $output('Clearing existing MySQL rows before import...');

                foreach (array_reverse($tables) as $table) {
                    $target->table($table)->delete();
                    $this->resetAutoIncrement($table);
                }
            }

            foreach ($tables as $table) {
                $rows = $source->table($table)->get()
                    ->map(fn (object $row) => (array) $row)
                    ->all();

                if ($rows === []) {
                    $output("Skipped {$table}: 0 rows");

                    continue;
                }

                foreach (array_chunk($rows, 250) as $chunk) {
                    $target->table($table)->insert($chunk);
                }

                $output("Imported {$table}: ".count($rows).' rows');
            }
        });
    }

    protected function resolveSourcePath(string $sourcePath): string
    {
        $candidate = str_starts_with($sourcePath, DIRECTORY_SEPARATOR)
            ? $sourcePath
            : base_path($sourcePath);

        if (! is_file($candidate)) {
            throw new RuntimeException("SQLite source file not found: {$candidate}");
        }

        return $candidate;
    }

    protected function configureSourceConnection(string $path): void
    {
        Config::set('database.connections.sqlite_import', [
            'driver' => 'sqlite',
            'url' => null,
            'database' => $path,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        DB::purge('sqlite_import');
    }

    /**
     * @return list<string>
     */
    protected function sourceTables($source): array
    {
        return collect($source->select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'"))
            ->pluck('name')
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $tables
     */
    protected function targetHasRows($target, array $tables): bool
    {
        foreach ($tables as $table) {
            if ($target->table($table)->exists()) {
                return true;
            }
        }

        return false;
    }

    protected function withoutForeignKeyChecks($target, Closure $callback): void
    {
        $target->statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            $callback();
        } finally {
            $target->statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    protected function resetAutoIncrement(string $table): void
    {
        $columns = Schema::getColumnListing($table);

        if (in_array('id', $columns, true)) {
            DB::statement("ALTER TABLE `{$table}` AUTO_INCREMENT = 1");
        }
    }
}
