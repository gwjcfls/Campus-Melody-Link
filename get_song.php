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
    echo json_encode(['success' => false, 'message' => '未授权访问']);
    exit;
}

// 处理获取歌曲信息
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // 获取歌曲ID
        $song_id = $_GET['id'];
        
        // 验证数据
        if (empty($song_id)) {
            throw new Exception("无效的歌曲ID");
        }
        
        // 查询数据
        $stmt = $pdo->prepare("SELECT * FROM song_requests WHERE id = :id");
        $stmt->execute(['id' => $song_id]);
        
        // 获取结果
        $song = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$song) {
            throw new Exception("歌曲不存在");
        }
        
        // 返回成功信息和歌曲数据
        echo json_encode(['success' => true, 'song' => $song]);
    } catch (Exception $e) {
        // 返回错误信息
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    // 非法请求
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => '方法不允许']);
}
?>    