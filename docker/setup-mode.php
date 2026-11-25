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

echo $mode;
