<?php
// 用于测试环境下在 CLI 中“强制刷新”一次 reset_seq（不需要管理员登录）
// 用法：php tests/tools/force_reset_cli.php

declare(strict_types=1);

require_once __DIR__ . '/../../db_connect.php';

function ensure_schema_cli(PDO $pdo): void {
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM time_settings LIKE 'reset_seq'");
        if ($stmt->rowCount() === 0) {
            $pdo->exec("ALTER TABLE time_settings ADD COLUMN reset_seq INT NOT NULL DEFAULT 0");
        }
        // 确保 id 为自增
        $stmt2 = $pdo->query("SHOW COLUMNS FROM time_settings LIKE 'id'");
        $idCol = $stmt2 ? $stmt2->fetch(PDO::FETCH_ASSOC) : null;
        $extra = $idCol['Extra'] ?? '';
        if (stripos($extra, 'auto_increment') === false) {
            try { $pdo->exec("ALTER TABLE time_settings MODIFY COLUMN id INT NOT NULL AUTO_INCREMENT PRIMARY KEY"); } catch (Exception $e) { /* ignore */ }
        }
    } catch (Exception $e) { /* ignore */ }
}

ensure_schema_cli($pdo);

$row = $pdo->query('SELECT * FROM time_settings ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    // 初始化一条配置
    $pdo->exec("INSERT INTO time_settings (mode, manual_request_enabled, manual_vote_enabled, request_limit, vote_limit, combined_limit, reset_seq, updated_at) VALUES ('manual',1,1,1,3,NULL,0,NOW())");
    $row = $pdo->query('SELECT * FROM time_settings ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
}

$newSeq = (int)($row['reset_seq'] ?? 0) + 1;

if ($row['combined_limit'] !== null) {
    $stmt = $pdo->prepare('INSERT INTO time_settings (mode, manual_request_enabled, manual_vote_enabled, combined_limit, request_limit, vote_limit, reset_seq, updated_at) VALUES (:mode,:mre,:mve,:cl,:rl,:vl,:rs,NOW())');
    $stmt->execute([
        'mode' => $row['mode'],
        'mre'  => (int)$row['manual_request_enabled'],
        'mve'  => (int)$row['manual_vote_enabled'],
        'cl'   => (int)$row['combined_limit'],
        'rl'   => (int)$row['request_limit'],
        'vl'   => (int)$row['vote_limit'],
        'rs'   => $newSeq,
    ]);
} else {
    $stmt = $pdo->prepare('INSERT INTO time_settings (mode, manual_request_enabled, manual_vote_enabled, request_limit, vote_limit, combined_limit, reset_seq, updated_at) VALUES (:mode,:mre,:mve,:rl,:vl,NULL,:rs,NOW())');
    $stmt->execute([
        'mode' => $row['mode'],
        'mre'  => (int)$row['manual_request_enabled'],
        'mve'  => (int)$row['manual_vote_enabled'],
        'rl'   => (int)$row['request_limit'],
        'vl'   => (int)$row['vote_limit'],
        'rs'   => $newSeq,
    ]);
}

fwrite(STDOUT, json_encode(['success' => true, 'reset_seq' => $newSeq], JSON_UNESCAPED_UNICODE) . "\n");
