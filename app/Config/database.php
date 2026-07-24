<?php

// Timestamps reach the database from two clocks: PHP (BaseRepository::applyTimestamps,
// which formats in APP_TIMEZONE) and SQL NOW(), which runs in the session's zone.
// Left at the server default those two disagree wherever the DB server is not on
// APP_TIMEZONE — the official MariaDB image is UTC, so a Docker install writes rows
// two hours apart into the same column. Pin the session zone to APP_TIMEZONE so both
// clocks agree. Named zones are avoided: they need the mysql tz tables loaded, which
// is not the case on a stock server. The offset is resolved per connection, so a
// long-lived process (scheduler) keeps the offset it started with until it reconnects.
$dbTimezoneOffset = static function (): string {
    $tz     = new DateTimeZone(env('APP_TIMEZONE', 'Europe/Rome'));
    $offset = $tz->getOffset(new DateTime('now', new DateTimeZone('UTC')));
    $sign   = $offset < 0 ? '-' : '+';
    $offset = abs($offset);

    return sprintf('%s%02d:%02d', $sign, intdiv($offset, 3600), intdiv($offset % 3600, 60));
};

return [
    'host'    => env('DB_HOST', 'localhost'),
    'port'    => env('DB_PORT', '3306'),
    'name'    => env('DB_NAME', 'favilla'),
    'user'    => env('DB_USER', 'root'),
    'pass'    => env('DB_PASS', ''),
    'charset' => env('DB_CHARSET', 'utf8mb4'),

    'options' => [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET SESSION sql_mode='STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION',"
            . " time_zone='" . $dbTimezoneOffset() . "'",
    ],
];
