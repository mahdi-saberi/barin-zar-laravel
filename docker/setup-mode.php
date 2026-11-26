<?php

use Dotenv\Dotenv;
use Illuminate\Support\Env;

require __DIR__.'/../vendor/autoload.php';

$root = dirname(__DIR__);
if ((is_link($root.'/.env') || file_exists($root.'/.env')) && ! is_readable($root.'/.env')) {
    fwrite(STDERR, "The persistent .env must be readable by www-data.\n");
    exit(1);
}

Dotenv::create(Env::getRepository(), $root)->safeLoad();

if (blank(Env::get('APP_KEY'))) {
    fwrite(STDERR, "Set a persistent APP_KEY before starting the container.\n");
    exit(1);
}

$mode = Env::get('BARIN_DB_SETUP', 'off');
if (!in_array($mode, ['off', 'migrate', 'demo'], true)) {
    fwrite(STDERR, "BARIN_DB_SETUP must be off, migrate, or demo.\n");
    exit(1);
}

// Only the web process prepares a database before migrations. Scheduler startup
// and the explicit "off" mode leave database files alone.
if (($argv[1] ?? '') === 'apache2-foreground'
    && $mode !== 'off'
    && Env::get('DB_CONNECTION', 'sqlite') === 'sqlite'
    && blank(Env::get('DB_URL'))) {
    $database = Env::get('DB_DATABASE', $root.'/database/database.sqlite');

    if ($database !== ':memory:') {
        if (! is_string($database) || $database === '') {
            fwrite(STDERR, "Set DB_DATABASE to an absolute SQLite file path.\n");
            exit(1);
        }

        if (! str_starts_with($database, '/')) {
            $database = $root.'/'.$database;
        }

        $directory = dirname($database);
        if (! is_dir($directory) && ! @mkdir($directory, 0700, true) && ! is_dir($directory)) {
            fwrite(STDERR, "Cannot create the SQLite directory as www-data. Use a writable persistent directory.\n");
            exit(1);
        }

        if (! file_exists($database)) {
            $previousUmask = umask(0077);
            $created = @touch($database);
            umask($previousUmask);

            if (! $created) {
                fwrite(STDERR, "Cannot create the SQLite file as www-data. Check DB_DATABASE and directory permissions.\n");
                exit(1);
            }
        }

        clearstatcache(true, $database);
        if (! is_file($database) || ! is_writable($database) || ! is_writable($directory)) {
            fwrite(STDERR, "The SQLite file and its directory must be writable by www-data.\n");
            exit(1);
        }
    }
}

echo $mode;
