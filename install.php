<?php
// One-time database installer for hosts where the DB is not reachable
// from outside (e.g. pxxl.pro). Visit /install.php?key=setup-2026 once
// after deploying, then DELETE THIS FILE from the server.
require_once __DIR__ . '/config/db.php';

const INSTALL_KEY = 'setup-2026';

if (($_GET['key'] ?? '') !== INSTALL_KEY) {
    http_response_code(403);
    exit('Forbidden. Append ?key=... with the install key to run the installer.');
}

$sqlFile = __DIR__ . '/database_remote.sql';
if (!is_file($sqlFile)) {
    exit('database_remote.sql not found next to install.php.');
}

// Split on semicolons at end-of-line so semicolons inside text values are safe.
$statements = preg_split('/;\s*\r?\n/', file_get_contents($sqlFile));

$ran = 0;
$errors = [];
foreach ($statements as $statement) {
    $statement = trim($statement);
    if ($statement === '') {
        continue;
    }
    try {
        $pdo->exec($statement);
        $ran++;
    } catch (PDOException $ex) {
        $errors[] = $ex->getMessage();
    }
}

header('Content-Type: text/plain; charset=utf-8');
echo "Statements executed: {$ran}\n";
if ($errors) {
    echo "Errors:\n - " . implode("\n - ", $errors) . "\n";
} else {
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables now in database: " . implode(', ', $tables) . "\n";
    echo "Done. Admin login: admin / admin123\n";
    echo "IMPORTANT: delete install.php (and database_remote.sql) from the server now.\n";
}
