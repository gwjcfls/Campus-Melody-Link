<?php
// 会话安全配置（放在session_start()之前）
ini_set('session.cookie_secure', 'On'); // 仅通过HTTPS传输Cookie（需服务器支持HTTPS）
ini_set('session.cookie_httponly', 'On'); // 禁止JS读取Cookie，防止XSS窃取
ini_set('session.cookie_samesite', 'Strict'); // 限制跨站请求携带Cookie，防CSRF
ini_set('session.cookie_lifetime', 0); // 会话Cookie随浏览器关闭失效
ini_set('session.gc_maxlifetime', 3600); // 会话有效期1小时（无操作自动失效）
ini_set('session.regenerate_id', 'On'); // 每次请求刷新Session ID，防止固定攻击

session_start();
require_once 'db_connect.php';

// 检查是否已登录
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => '未授权访问']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

// 仅支持 GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => '方法不允许']);
    exit;
}

try {
    // 如果提供 id，则返回单条记录（保持向后兼容）
    if (isset($_GET['id']) && is_numeric($_GET['id'])) {
        $song_id = (int)$_GET['id'];
        $stmt = $pdo->prepare("SELECT * FROM song_requests WHERE id = :id");
        $stmt->execute(['id' => $song_id]);
        $song = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$song) {
            echo json_encode(['success' => false, 'message' => '歌曲不存在']);
        } else {
            echo json_encode(['success' => true, 'song' => $song]);
        }
        exit;
    }

    // 列表查询：支持 q（模糊匹配）和 filter（all|pending|played）
    $q = trim((string)($_GET['q'] ?? ''));
    $filter = (string)($_GET['filter'] ?? 'all');

    $conditions = [];
    $params = [];

    if ($q !== '') {
        // 在多个字段上进行模糊匹配
        $conditions[] = "(song_name LIKE :q OR artist LIKE :q OR requestor LIKE :q OR `class` LIKE :q)";
        $params['q'] = "%{$q}%";
    }

    if ($filter === 'pending') {
        $conditions[] = "played = 0";
    } elseif ($filter === 'played') {
        $conditions[] = "played = 1";
    }

    $where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';
    // 限制返回量以防一次性拉取过多数据
    $limit = 1000;
    $sql = "SELECT * FROM song_requests {$where} ORDER BY played ASC, votes DESC, created_at ASC LIMIT {$limit}";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $songs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'songs' => $songs]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>    