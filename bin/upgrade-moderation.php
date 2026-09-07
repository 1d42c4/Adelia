<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/bootstrap.php';
$config = Adelia\Config::load('settings.php');
$lock = new Adelia\BoardLock('adelia.lock');
$database = new Adelia\Database($config);
$retiredTable = $argv[1] ?? 'bans';
if (in_array($retiredTable, [$config->dbaccounts, $config->dbposts, $config->dbreports, $config->dblogs, $config->dbkeywords], true)) {
    throw new RuntimeException('The retired table must not be an active application table.');
}
$columns = static function (string $table) use ($database, $config): array {
    return match ($config->dbdriver) {
        'sqlite' => array_column($database->rows('PRAGMA table_info(' . $database->identifier($table) . ')'), 'name'),
        'mysql' => array_column($database->rows('SHOW COLUMNS FROM ' . $database->identifier($table)), 'Field'),
        'pgsql' => array_column($database->rows('SELECT column_name FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = ?', [$table]), 'column_name'),
    };
};
if ($config->dbdriver === 'sqlite') {
    $database->execute('PRAGMA secure_delete = ON');
}
$postTable = $database->identifier($config->dbposts);
if (!in_array('message_source', $columns($config->dbposts), true)) {
    $database->execute("ALTER TABLE $postTable ADD COLUMN message_source TEXT NOT NULL DEFAULT ''");
}
if (!in_array('source_known', $columns($config->dbposts), true)) {
    $database->execute("ALTER TABLE $postTable ADD COLUMN source_known BIGINT NOT NULL DEFAULT 0");
}
foreach ([$config->dbposts, $config->dbreports] as $table) {
    if (in_array('ip', $columns($table), true)) {
        $database->execute('ALTER TABLE ' . $database->identifier($table) . ' DROP COLUMN ip');
    }
}
$database->execute('DROP TABLE IF EXISTS ' . $database->identifier($retiredTable));
// Keep keyword blocking, replacing retired ban actions with rejection of the post.
$database->execute('UPDATE ' . $database->identifier($config->dbkeywords) . " SET action = 'delete' WHERE action LIKE 'ban%'");
foreach ($database->rows('SELECT id, message FROM ' . $database->identifier($config->dblogs)) as $entry) {
    if (preg_match('/^(Banned |Lifted ban on |Added ban message to )/', $entry['message'])) {
        $database->execute('DELETE FROM ' . $database->identifier($config->dblogs) . ' WHERE id = ?', [$entry['id']]);
    } elseif (str_starts_with($entry['message'], 'Deleted ')) {
        $cleaned = preg_replace('/ - hmac:[a-f0-9]{64}/', '', $entry['message']);
        if ($cleaned !== $entry['message']) {
            $database->update($config->dblogs, $entry['id'], ['message' => $cleaned]);
        }
    }
}
if ($config->dbdriver === 'sqlite') {
    $database->execute('VACUUM');
}
echo "Moderation schema upgraded. Retired identifying records removed.\n";
