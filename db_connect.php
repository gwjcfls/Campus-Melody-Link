<?php

date_default_timezone_set('Asia/Shanghai');
setlocale(LC_TIME, 'zh_CN');

// 数据库连接配置
$host = 'localhost';
$dbname = 'test';
$username = 'root';
$password = '123456';

try {
    // 创建PDO连接
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    
    // 设置错误模式为异常
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 设置字符集
    $pdo->exec("SET NAMES 'utf8mb4'");
} catch(PDOException $e) {
    // 输出错误信息
    die("数据库连接失败: " . $e->getMessage());
}

// 通用操作日志写入函数
function log_operation($pdo, $user, $role, $action, $target = null, $details = null) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $stmt = $pdo->prepare("INSERT INTO operation_logs (user, role, action, target, details, ip, created_at) VALUES (:user, :role, :action, :target, :details, :ip, NOW())");
    $stmt->execute([
        'user' => $user,
        'role' => $role,
        'action' => $action,
        'target' => $target,
        'details' => $details,
        'ip' => $ip
    ]);
}
?>    
