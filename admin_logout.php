<?php
session_start();

// 日志记录
require_once 'db_connect.php';
$user = $_SESSION['admin_username'] ?? '';
$role = $_SESSION['admin_role'] ?? '';
log_operation($pdo, $user, $role, '管理员登出', null, null);

// 销毁会话
session_destroy();

// 重定向到首页
header("Location: index.php");
exit;
?>    