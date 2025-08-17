<?php
// 会话安全配置（放在session_start()之前）
// ini_set('session.cookie_secure', 'On');
// ini_set('session.cookie_httponly', 'On');
// ini_set('session.cookie_samesite', 'Strict');
// ini_set('session.cookie_lifetime', 0);
// ini_set('session.gc_maxlifetime', 3600);
// ini_set('session.regenerate_id', 'On');
session_start();
require_once 'db_connect.php';

// --- 防止反复尝试登录的逻辑 ---
// 获取客户端IP
$clientIp = $_SERVER['REMOTE_ADDR'];
// 失败次数阈值和锁定时间（可自定义）
$maxAttempts = 3; // 最大失败次数
$lockTime = 900; // 锁定时间（秒），15分钟

// 初始化存储失败记录的Session（键名为IP，值为数组：[次数, 首次失败时间]）
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = [];
}

// 检查是否已锁定
if (isset($_SESSION['login_attempts'][$clientIp])) {
    $attemptData = $_SESSION['login_attempts'][$clientIp];
    $attemptCount = $attemptData[0];
    $firstFailTime = $attemptData[1];
    
    // 若失败次数超阈值且未过锁定时间，拒绝请求
    if ($attemptCount >= $maxAttempts && (time() - $firstFailTime) < $lockTime) {
        $remainingTime = $lockTime - (time() - $firstFailTime);
        http_response_code(429); // 太多请求
        echo json_encode([
            'success' => false,
            'message' => "登录失败次数过多，请" . intval($remainingTime / 60) . "分钟后再试"
        ]);
        exit;
    }
    // 若已过锁定时间，重置失败记录
    if ((time() - $firstFailTime) >= $lockTime) {
        unset($_SESSION['login_attempts'][$clientIp]);
    }
}

// 限制请求方法为POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => '仅支持POST方法']);
    exit;
}

// 获取表单数据
$username = trim($_POST['admin_username'] ?? '');
$password = trim($_POST['admin_password'] ?? '');
if (empty($username) || empty($password)) {
    echo json_encode(['success' => false, 'message' => '用户名和密码不能为空']);
    exit;
}

try {
    // 从数据库查询管理员信息
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    // 验证管理员存在性及密码
    if (!$admin || !password_verify($password, $admin['password'])) {
        // 登录失败，记录日志
        log_operation(
            $pdo,
            $username,
            'admin',
            '管理员登录失败',
            null,
            json_encode(['ip'=>$clientIp], JSON_UNESCAPED_UNICODE)
        );
        if (!isset($_SESSION['login_attempts'][$clientIp])) {
            // 首次失败，初始化记录
            $_SESSION['login_attempts'][$clientIp] = [1, time()];
        } else {
            // 非首次失败，次数+1
            $_SESSION['login_attempts'][$clientIp][0]++;
        }
        $remaining = $maxAttempts - $_SESSION['login_attempts'][$clientIp][0];
        $msg = $remaining > 0 ? "用户名或密码错误，还剩{$remaining}次机会" : "即将锁定，请稍后再试";
        throw new Exception($msg);
    }
    // 登录成功，记录日志
    log_operation(
        $pdo,
        $admin['username'],
        $admin['role'],
        '管理员登录成功',
        null,
        json_encode(['ip'=>$clientIp], JSON_UNESCAPED_UNICODE)
    );

    // 登录成功：清除失败记录，存储Session
    unset($_SESSION['login_attempts'][$clientIp]); // 登录成功后重置失败记录
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_username'] = $admin['username'];
    $_SESSION['admin_role'] = $admin['role'];
    session_regenerate_id(true); // 登录成功刷新Session ID

    echo json_encode([
        'success' => true,
        'message' => '登录成功，正在跳转...',
        'role' => $admin['role']
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>