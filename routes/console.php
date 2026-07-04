<?php

use App\Jobs\PublishScheduledBlogsJob;
use App\Support\SqliteMysqlImporter;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('app:import-sqlite {path} {--fresh}', function (string $path) {
    set_time_limit(0);

    $this->components->info('Starting SQLite to MySQL import...');

    try {
        app(SqliteMysqlImporter::class)->import(
            $path,
            (bool) $this->option('fresh'),
            fn (string $message) => $this->line($message),
        );
    } catch (Throwable $exception) {
        $this->components->error($exception->getMessage());

        return self::FAILURE;
    }

    $this->newLine();
    $this->components->info('Import completed successfully.');

    return self::SUCCESS;
})->purpose('Import application data from a SQLite file into the default MySQL database');

Schedule::job(new PublishScheduledBlogsJob)->everyMinute();
Schedule::command('app:prune-logs --days=90')->daily();
