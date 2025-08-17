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

// 处理规则更新
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // 获取规则内容
        // $content = htmlspecialchars(trim($_POST['rule_content']));
        $content = trim($_POST['rule_content']);
        
        // 验证数据
        if (empty($content)) {
            throw new Exception("规则内容不能为空");
        }
        
        // 开始事务
        $pdo->beginTransaction();
        
        // 插入新规则
        $stmt = $pdo->prepare("INSERT INTO rules (content, updated_at) VALUES (:content, NOW())");
        $stmt->execute(['content' => $content]);
        // 日志记录
        $user = $_SESSION['admin_username'] ?? '';
        $role = $_SESSION['admin_role'] ?? '';
        log_operation(
            $pdo,
            $user,
            $role,
            '修改规则',
            null,
            $content
        );
        
        // 提交事务
        $pdo->commit();
        
        // 返回成功信息
        echo json_encode(['success' => true, 'message' => '点歌规则已更新']);
    } catch (Exception $e) {
        // 回滚事务
        $pdo->rollBack();
        
        // 返回错误信息
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    // 非法请求
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => '方法不允许']);
}
?>    