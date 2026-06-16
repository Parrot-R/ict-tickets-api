<?php

declare(strict_types=1);

/**
 * Run database seed from browser or CLI:
 *   php seed.php
 *   http://localhost/IT-Task-Management/backend/seed.php
 */

require_once __DIR__ . '/includes/helpers.php';

$config = require __DIR__ . '/config/database.php';
$seedFile = __DIR__ . '/database/seed.sql';

if (!file_exists($seedFile)) {
    json_error('Seed file not found', 500);
}

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%d;charset=%s', $config['host'], $config['port'], $config['charset']),
        $config['username'],
        $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $schema = file_get_contents(__DIR__ . '/database/schema.sql');
    if ($schema === false) {
        json_error('Schema file not found', 500);
    }

    foreach (array_filter(array_map('trim', explode(';', $schema))) as $statement) {
        if ($statement !== '') {
            $pdo->exec($statement);
        }
    }

    $seed = file_get_contents($seedFile);
    if ($seed === false) {
        json_error('Could not read seed file', 500);
    }

    foreach (array_filter(array_map('trim', explode(';', $seed))) as $statement) {
        if ($statement !== '') {
            $pdo->exec($statement);
        }
    }

    json_response([
        'success' => true,
        'message' => 'Database schema created and seed data loaded successfully.',
    ]);
} catch (PDOException $e) {
    json_error('Seed failed: ' . $e->getMessage(), 500);
}
