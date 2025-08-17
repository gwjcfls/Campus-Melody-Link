<?php
// 会话安全配置（复用现有配置）
ini_set('session.cookie_secure', 'On');
ini_set('session.cookie_httponly', 'On');
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.cookie_lifetime', 0);
ini_set('session.gc_maxlifetime', 3600);
ini_set('session.regenerate_id', 'On');
session_start();
require_once 'db_connect.php';

// 基础权限校验：必须登录
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => '未授权访问（请登录）']);
    exit;
}

// 获取请求参数
$action = $_POST['action'] ?? '';
$songIds = isset($_POST['song_ids']) ? explode(',', $_POST['song_ids']) : [];

// 验证参数有效性
if (empty($action) || empty($songIds)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '参数错误（操作类型或歌曲ID不能为空）']);
    exit;
}

// 权限细分校验（超级管理员专属操作）
$superAdminOnlyActions = ['delete', 'reset-votes'];
if (in_array($action, $superAdminOnlyActions) && $_SESSION['admin_role'] !== 'super_admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => '无权限执行此操作（需超级管理员权限）']);
    exit;
}

// 验证歌曲ID格式（必须为数字）
foreach ($songIds as $id) {
    if (!is_numeric($id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '无效的歌曲ID']);
        exit;
    }
}
$songIds = array_map('intval', $songIds); // 转换为整数

try {
    // 根据操作类型执行对应逻辑
    switch ($action) {
        // 批量标记为已播放
        case 'mark-played':
            $stmt = $pdo->prepare("
                UPDATE song_requests 
                SET played = 1, played_at = NOW() 
                WHERE id IN (" . implode(',', array_fill(0, count($songIds), '?')) . ")
            ");
            $stmt->execute($songIds);
            $message = "已成功将" . count($songIds) . "首歌曲标记为已播放";
            break;
        
        // 批量标记为待播放
        case 'mark-unplayed':
            $stmt = $pdo->prepare("
                UPDATE song_requests 
                SET played = 0, played_at = NULL 
                WHERE id IN (" . implode(',', array_fill(0, count($songIds), '?')) . ")
            ");
            $stmt->execute($songIds);
            $message = "已成功将" . count($songIds) . "首歌曲标记为待播放";
            break;
        
        // 批量删除歌曲（仅超级管理员）
        case 'delete':
            $stmt = $pdo->prepare("
                DELETE FROM song_requests 
                WHERE id IN (" . implode(',', array_fill(0, count($songIds), '?')) . ")
            ");
            $stmt->execute($songIds);
            $message = "已成功删除" . count($songIds) . "首歌曲";
            break;
        
        // 批量重置票数（仅超级管理员）
        case 'reset-votes':
            $stmt = $pdo->prepare("
                UPDATE song_requests 
                SET votes = 0 
                WHERE id IN (" . implode(',', array_fill(0, count($songIds), '?')) . ")
            ");
            $stmt->execute($songIds);
            $message = "已成功将" . count($songIds) . "首歌曲的票数重置为0";
            break;
        
        // 未知操作
        default:
            throw new Exception("无效的操作类型");
    }

    // 日志记录
    $user = $_SESSION['admin_username'] ?? '';
    $role = $_SESSION['admin_role'] ?? '';
    log_operation(
        $pdo,
        $user,
        $role,
        '批量操作-' . $action,
        implode(',', $songIds),
        json_encode(['action' => $action, 'ids' => $songIds], JSON_UNESCAPED_UNICODE)
    );

    // 返回成功结果
    echo json_encode([
        'success' => true,
        'message' => $message
    ]);
} catch (Exception $e) {
    // 错误处理
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>