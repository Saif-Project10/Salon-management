<?php
require_once dirname(__DIR__) . '/includes/db.php';

try {
    $created = salonCheckAndGenerateAutoPO($pdo);
    echo "Automatic purchase order check complete. Created: {$created}" . PHP_EOL;
} catch (Throwable $exception) {
    echo 'Automatic purchase order check failed: ' . $exception->getMessage() . PHP_EOL;
    exit(1);
}
