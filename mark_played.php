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

// 处理歌曲状态更新
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // 获取数据
        //$song_id = $_POST['song_id'];
        //$played = (bool)$_POST['played']; // 转换为布尔值
        
        // 验证数据
        //if (empty($song_id) || !is_bool($played)) {
          //  throw new Exception("无效的数据");
        //}
        
        // 开始事务
        $pdo->beginTransaction();
        
        // 更新数据
        //$stmt = $pdo->prepare("UPDATE song_requests 
          //                    SET played = :played, 
          //                        played_at = CASE WHEN :played THEN NOW() ELSE NULL END 
          //                    WHERE id = :id");
        
        //$stmt->execute([
           // 'id' => $song_id,
            //'played' => $played
        //]);
        
        
        // 获取歌曲ID和播放状态
$songId = isset($_POST['song_id']) ? (int)$_POST['song_id'] : 0;
$played = isset($_POST['played']) ? filter_var($_POST['played'], FILTER_VALIDATE_BOOLEAN) : false;

if ($songId <= 0) {
    echo json_encode(['success' => false, 'message' => '无效的歌曲ID']);
    exit;
}

    if ($played) {
        // 标记为已播放
        $stmt = $pdo->prepare("
            UPDATE song_requests 
            SET played = true, played_at = CURRENT_TIMESTAMP 
            WHERE id = :id
        ");
    } else {
        // 标记为未播放
        $stmt = $pdo->prepare("
            UPDATE song_requests 
            SET played = false, played_at = NULL 
            WHERE id = :id
        ");
    }
    
    $stmt->execute(['id' => $songId]);
    // 日志记录
    $user = $_SESSION['admin_username'] ?? '';
    $role = $_SESSION['admin_role'] ?? '';
    log_operation(
        $pdo,
        $user,
        $role,
        $played ? '标记为已播放' : '标记为待播放',
        $songId,
        null
    );
        
        // 提交事务
        $pdo->commit();
        
        // 返回成功信息
        $message = $played ? '歌曲已标记为已播放' : '歌曲已标记为待播放';
        echo json_encode(['success' => true, 'message' => $message]);
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