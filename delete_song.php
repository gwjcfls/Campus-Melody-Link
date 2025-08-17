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

// 检查是否为超级管理员
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_role'] !== 'super_admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => '无权限执行此操作（需超级管理员权限）']);
    exit;
}

// 处理歌曲删除
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // 获取歌曲ID
        $song_id = $_POST['song_id'];
        
        // 验证数据
        if (empty($song_id)) {
            throw new Exception("无效的歌曲ID");
        }
        
        // 删除数据
        $stmt = $pdo->prepare("DELETE FROM song_requests WHERE id = :id");
        $stmt->execute(['id' => $song_id]);
        // 日志记录
        $user = $_SESSION['admin_username'] ?? '';
        $role = $_SESSION['admin_role'] ?? '';
        log_operation(
            $pdo,
            $user,
            $role,
            '删除歌曲',
            $song_id,
            null
        );
        
        // 返回成功信息
        echo json_encode(['success' => true, 'message' => '歌曲已删除']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    // 非法请求
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => '方法不允许']);
}
?>