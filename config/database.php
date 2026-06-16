<?php
/**
 * MySQL connection settings.
 * Reads from environment variables in production (Railway),
 * falls back to XAMPP defaults for local dev.
 */
return [
    'host'     => getenv('MYSQLHOST') ?: '127.0.0.1',
    'port'     => (int)(getenv('MYSQLPORT') ?: 3306),
    'dbname'   => getenv('MYSQLDATABASE') ?: 'tasks_schema',
    'username' => getenv('MYSQLUSER') ?: 'root',
    'password' => getenv('MYSQLPASSWORD') ?: '',
    'charset'  => 'utf8mb4',
];