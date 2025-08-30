<?php
// 安装向导：填写数据库信息 -> 初始化数据库 -> 创建管理员 -> 写入 db_connect.php -> 展示帮助
// 安全建议：成功后删除本文件 install.php

declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');

$rootDir = __DIR__;
$sqlFile = $rootDir . '/database_setup.sql';
$dbConnectFile = $rootDir . '/db_connect.php';
$lockFile = $rootDir . '/install.lock';

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

$errors = [];
$infos = [];
$installed = false;

// 读取已存在的连接配置（若存在）
$existing = [
    'host' => 'localhost',
    'dbname' => 'test',
    'username' => 'root',
    'password' => '',
];
if (is_file($dbConnectFile)) {
    // 尝试简单解析（不执行）
    $code = file_get_contents($dbConnectFile);
    if ($code !== false) {
        foreach (['host','dbname','username','password'] as $k) {
            if (preg_match("/\$${k}\s*=\s*'([^']*)'/", $code, $m)) {
                $existing[$k] = $m[1];
            }
        }
    }
}

$isPost = ($_SERVER['REQUEST_METHOD'] === 'POST');

if ($isPost) {
    $host = trim($_POST['db_host'] ?? '');
    $dbname = trim($_POST['db_name'] ?? '');
    $user = trim($_POST['db_user'] ?? '');
    $pass = (string)($_POST['db_pass'] ?? '');
    $createDb = isset($_POST['create_db']) && $_POST['create_db'] === '1';
    $importSql = isset($_POST['import_sql']) && $_POST['import_sql'] === '1';
    $buildTrie = isset($_POST['build_trie']) && $_POST['build_trie'] === '1';
    $createAdmin = isset($_POST['create_admin']) && $_POST['create_admin'] === '1';

    $adminUser = trim($_POST['admin_user'] ?? '');
    $adminPass = (string)($_POST['admin_pass'] ?? '');
    $adminRole = in_array(($_POST['admin_role'] ?? 'super_admin'), ['admin','super_admin'], true) ? $_POST['admin_role'] : 'super_admin';

    if ($host === '' || $dbname === '' || $user === '') {
        $errors[] = '数据库主机/库名/用户名为必填';
    }
    if ($createAdmin && ($adminUser === '' || $adminPass === '')) {
        $errors[] = '创建管理员需要提供管理员用户名与密码';
    }

    if (!$errors) {
        try {
            // 1) 连接到服务器（不带 db），必要时创建数据库
            $dsnServer = "mysql:host={$host};charset=utf8mb4";
            $pdoServer = new PDO($dsnServer, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $infos[] = '已连接数据库服务器';
            if ($createDb) {
                $pdoServer->exec("CREATE DATABASE IF NOT EXISTS `" . str_replace('`','``',$dbname) . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $infos[] = "数据库 `" . h($dbname) . "` 已确保存在";
            }

            // 2) 连接到目标数据库
            $dsnDb = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
            $pdo = new PDO($dsnDb, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $pdo->exec("SET NAMES 'utf8mb4'");
            $pdo->exec("SET CHARACTER SET utf8mb4");
            $pdo->exec("SET collation_connection = 'utf8mb4_unicode_ci'");
            $infos[] = '已连接到目标数据库并设置字符集（utf8mb4）';

            // 3) 导入 SQL
            if ($importSql) {
                if (!is_file($sqlFile)) {
                    throw new Exception('找不到初始化 SQL 文件：database_setup.sql');
                }
                $sql = file_get_contents($sqlFile);
                if ($sql === false) throw new Exception('读取 database_setup.sql 失败');
                // 移除 UTF-8 BOM 并规范换行
                if (substr($sql, 0, 3) === "\xEF\xBB\xBF") { $sql = substr($sql, 3); }
                $sql = str_replace(["\r\n", "\r"], "\n", $sql);
                // 预处理：去掉以 Unicode 符号开头的注释行（如 — 、– 、· 、• 等）
                $lines = explode("\n", $sql);
                $cleanLines = [];
                foreach ($lines as $ln) {
                    $trimLn = ltrim($ln);
                    if ($trimLn === '') { $cleanLines[] = $ln; continue; }
                    if (preg_match('/^[\x{2014}\x{2013}\x{00B7}\x{2022}]/u', $trimLn)) {
                        // 丢弃以 —(2014)、–(2013)、·(00B7)、•(2022) 开头的 Unicode 注释行
                        continue;
                    }
                    $cleanLines[] = $ln;
                }
                $sql = implode("\n", $cleanLines);

                // 解析器：去除注释，按 ; 在字符串外分割
                $statements = [];
                $buf = '';
                $inString = null; // '\'' or '"'
                $inLineComment = false;
                $inBlockComment = false;
                $len = strlen($sql);
                for ($i = 0; $i < $len; $i++) {
                    $ch = $sql[$i];
                    $next = ($i + 1 < $len) ? $sql[$i + 1] : '';

                    // 结束单行注释
                    if ($inLineComment) {
                        if ($ch === "\n") { $inLineComment = false; }
                        continue;
                    }
                    // 结束块注释
                    if ($inBlockComment) {
                        if ($ch === '*' && $next === '/') { $inBlockComment = false; $i++; }
                        continue;
                    }

                    // 非字符串内，检测注释起始
                    if ($inString === null) {
                        if ($ch === '-' && $next === '-') {
                            // -- 注释（标准：后随空格或制表符，这里放宽处理）
                            $inLineComment = true; $i++; continue;
                        }
                        if ($ch === '#') { $inLineComment = true; continue; }
                        if ($ch === '/' && $next === '*') { $inBlockComment = true; $i++; continue; }
                    }

                    // 处理字符串开闭
                    if ($inString === null && ($ch === "'" || $ch === '"')) {
                        $inString = $ch; $buf .= $ch; continue;
                    } elseif ($inString !== null && $ch === $inString) {
                        // 若前一字符为反斜杠则视为转义
                        $prev = ($i > 0) ? $sql[$i - 1] : '';
                        if ($prev !== '\\') { $inString = null; }
                        $buf .= $ch; continue;
                    }

                    // 语句结束
                    if ($inString === null && $ch === ';') {
                        $trim = trim($buf);
                        if ($trim !== '') { $statements[] = $trim; }
                        $buf = '';
                        continue;
                    }

                    $buf .= $ch;
                }
                $trim = trim($buf);
                if ($trim !== '') { $statements[] = $trim; }

                foreach ($statements as $stmt) {
                    try { $pdo->exec($stmt); } catch (Exception $e) {
                        $errors[] = '执行 SQL 片段失败：' . $e->getMessage();
                    }
                }
                if (!$errors) {
                    $infos[] = '初始化 SQL 导入完成';
                    // 导入后尝试统一转换字符集（处理历史表/不同默认字符集导致的混用）
                    try {
                        $tables = ['song_requests','announcements','rules','operation_logs','time_settings','time_rules'];
                        foreach ($tables as $t) {
                            $pdo->exec("ALTER TABLE `{$t}` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                        }
                        $infos[] = '已确保核心表为 utf8mb4_unicode_ci';
                    } catch (Throwable $e) {
                        $infos[] = '转换字符集时遇到问题（非致命）：' . $e->getMessage();
                    }
                }
            }

            // 4) 确保 admins 表存在，注入管理员（可选）
            $pdo->exec("CREATE TABLE IF NOT EXISTS admins (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(64) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                role ENUM('admin','super_admin') NOT NULL DEFAULT 'admin',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $infos[] = '管理员表已确保存在';

            if ($createAdmin) {
                $hash = password_hash($adminPass, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE username = :u");
                $stmt->execute(['u' => $adminUser]);
                $exists = (int)$stmt->fetchColumn() > 0;
                if ($exists) {
                    $stmt = $pdo->prepare("UPDATE admins SET password=:p, role=:r WHERE username=:u");
                    $stmt->execute(['p' => $hash, 'r' => $adminRole, 'u' => $adminUser]);
                    $infos[] = '已更新已存在的管理员密码/角色';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO admins (username, password, role) VALUES (:u,:p,:r)");
                    $stmt->execute(['u' => $adminUser, 'p' => $hash, 'r' => $adminRole]);
                    $infos[] = '已创建管理员账户';
                }
            }

            // 5) 写入 db_connect.php（若存在则直接覆盖；尝试备份但不阻塞）
            $backupMade = false;
            if (is_file($dbConnectFile)) {
                $bk = $dbConnectFile . '.bak-' . date('Ymd_His');
                if (@copy($dbConnectFile, $bk)) { $backupMade = true; }
            }
            $dbConnectCode = <<<PHP
<?php

date_default_timezone_set('Asia/Shanghai');
setlocale(LC_TIME, 'zh_CN');

// 数据库连接配置（由 install.php 生成）

\$host = '{$host}';
\$dbname = '{$dbname}';
\$username = '{$user}';
\$password = '{$pass}';

try {
    // 创建PDO连接
    \$pdo = new PDO("mysql:host=\$host;dbname=\$dbname;charset=utf8mb4", \$username, \$password);
    // 设置错误模式为异常
    \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // 设置字符集与排序规则
    \$pdo->exec("SET NAMES 'utf8mb4'");
    \$pdo->exec("SET CHARACTER SET utf8mb4");
    \$pdo->exec("SET collation_connection = 'utf8mb4_unicode_ci'");
} catch(PDOException \$e) {
    die("数据库连接失败: " . \$e->getMessage());
}

// 通用操作日志写入函数
function log_operation(\$pdo, \$user, \$role, \$action, \$target = null, \$details = null) {
    \$ip = \$_SERVER['REMOTE_ADDR'] ?? '';
    \$stmt = \$pdo->prepare("INSERT INTO operation_logs (user, role, action, target, details, ip, created_at) VALUES (:user, :role, :action, :target, :details, :ip, NOW())");
    \$stmt->execute([
        'user' => \$user,
        'role' => \$role,
        'action' => \$action,
        'target' => \$target,
        'details' => \$details,
        'ip' => \$ip
    ]);
}
?>
PHP;
            $manualDbConnect = null;
            $writeOk = false;
            // 尝试放宽权限再写入
            if (is_file($dbConnectFile)) { @chmod($dbConnectFile, 0664); }
            if (@file_put_contents($dbConnectFile, $dbConnectCode, LOCK_EX) !== false) {
                $writeOk = true;
            } else {
                // 尝试删除后重建
                @unlink($dbConnectFile);
                if (@file_put_contents($dbConnectFile, $dbConnectCode, LOCK_EX) !== false) {
                    $writeOk = true;
                } else {
                    // 尝试写入临时文件并原子替换
                    $tmp = @tempnam(dirname($dbConnectFile), 'dbc_');
                    if ($tmp && @file_put_contents($tmp, $dbConnectCode, LOCK_EX) !== false) {
                        if (@rename($tmp, $dbConnectFile)) {
                            $writeOk = true;
                        } else {
                            @unlink($tmp);
                        }
                    }
                }
            }

            if (!$writeOk) {
                // 若写入失败，提供手动创建内容
                $manualDbConnect = $dbConnectCode;
                throw new Exception('写入 db_connect.php 失败（请检查目录/文件权限或用下方内容手动替换），手动替换后刷新本页面继续完成后续操作');
            }
            @chmod($dbConnectFile, 0640);
            $infos[] = 'db_connect.php 已' . (is_file($dbConnectFile) ? '覆盖' : '创建') . ($backupMade ? '（原文件已备份）' : '');

            // 6) 触发 time_config.php 的 schema 保障（可选）
            try {
                define('TIME_CONFIG_LIB', true);
                require_once $rootDir . '/time_config.php';
                // 调用一次，若失败也不阻塞安装
                if (function_exists('compute_status')) {
                    compute_status($pdo);
                }
                $infos[] = '已触发时间配置 schema 保障';
            } catch (Throwable $e) {
                $infos[] = '跳过时间配置保障（非致命）: ' . $e->getMessage();
            }

            // 7) （可选）构建敏感词 Trie
            if ($buildTrie) {
                try {
                    if (is_file($rootDir . '/build.php')) {
                        // 直接包含执行
                        require $rootDir . '/build.php';
                        $infos[] = '敏感词 Trie 已尝试构建（若失败请稍后手动运行 build.php）';
                    } else {
                        $infos[] = '未找到 build.php，跳过敏感词 Trie 构建';
                    }
                } catch (Throwable $e) {
                    $infos[] = 'Trie 构建失败（非致命）：' . $e->getMessage();
                }
            }

            // 8) 写锁
            @file_put_contents($lockFile, date('c'));
            $installed = true;
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}

$locked = is_file($lockFile);
$force = isset($_GET['force']) && $_GET['force'] === '1';

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>安装向导 · 广播站点歌系统</title>
    <style>
        :root { --c1:#a78bfa; --c2:#f472b6; --g:#16a34a; --r:#dc2626; }
        body { font-family: system-ui, -apple-system, Segoe UI, Roboto, 'Helvetica Neue', Arial, 'Noto Sans', 'Liberation Sans', 'PingFang SC', 'Hiragino Sans GB', 'Microsoft YaHei', sans-serif; background:#f8fafc; color:#0f172a; }
        .container { max-width: 880px; margin: 32px auto; padding: 0 16px; }
        .card { background:#fff; border-radius:12px; box-shadow: 0 6px 20px rgba(15,23,42,.06); padding:20px; margin-bottom:16px; }
        .title { font-size:20px; font-weight:700; margin:4px 0 12px; color:#0f172a; }
        .muted { color:#475569; font-size:14px; }
        .grid { display:grid; grid-template-columns: 1fr 1fr; gap:16px; }
        .row { margin-bottom:12px; }
        label { font-size:13px; color:#334155; display:block; margin-bottom:6px; }
        input[type=text], input[type=password], select { width:100%; padding:10px 12px; border:1px solid #e5e7eb; border-radius:8px; font-size:14px; outline:none; }
        input[type=text]:focus, input[type=password]:focus, select:focus { border-color: var(--c1); box-shadow: 0 0 0 3px rgba(167,139,250,.2); }
        .actions { display:flex; gap:8px; align-items:center; }
        .btn { padding:10px 14px; border-radius:999px; border:none; cursor:pointer; font-weight:700; font-size:14px; }
        .btn-primary { background: linear-gradient(90deg, var(--c1), var(--c2)); color:#fff; }
        .btn-ghost { background:#fff; border:1px solid #e5e7eb; color:#0f172a; }
        .pill { display:inline-block; padding:6px 10px; border-radius:999px; font-size:12px; }
        .pill-ok { background:#ecfdf5; color:#047857; border:1px solid #a7f3d0; }
        .pill-bad { background:#fef2f2; color:#b91c1c; border:1px solid #fecaca; }
        .list { padding-left:18px; }
        .footer { text-align:center; color:#475569; font-size:12px; margin:24px 0; }
        @media (max-width: 720px) { .grid { grid-template-columns: 1fr; } }
    </style>
    <script>
        function toggleAdminFields(chk){
            var box = document.getElementById('adminFields');
            box.style.display = chk.checked ? '' : 'none';
        }
    </script>
    </head>
<body>
<div class="container">
    <div class="card" style="border-left:6px solid var(--c1);">
        <div class="title">安装向导</div>
        <div class="muted">一键完成数据库初始化、管理员创建与系统配置。</div>
    </div>

    <?php if ($locked && !$installed && !$force && !$isPost): ?>
    <div class="card">
        <div class="row"><span class="pill pill-bad">已检测到安装锁</span></div>
        <p class="muted">系统检测到 <code>install.lock</code>，可能已安装完成。为避免重复初始化，请谨慎继续。</p>
        <div class="actions">
            <a class="btn btn-primary" href="install.php?force=1">我已知悉，继续安装</a>
            <a class="btn btn-ghost" href="index.php">返回首页</a>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($errors): ?>
    <div class="card">
        <div class="title" style="color:var(--r);">安装失败</div>
        <ul class="list">
            <?php foreach ($errors as $e): ?>
            <li>· <?php echo h($e); ?></li>
            <?php endforeach; ?>
        </ul>
        <?php if (isset($manualDbConnect) && $manualDbConnect): ?>
        <div class="row">
            <div class="muted">可手动创建 <code>db_connect.php</code>，将以下内容保存为该文件：</div>
            <textarea style="width:100%;height:220px;border:1px solid #e5e7eb;border-radius:8px;padding:8px;" readonly><?php echo h($manualDbConnect); ?></textarea>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($installed): ?>
    <div class="card">
        <div class="title" style="color:var(--g);">安装完成</div>
        <p class="muted">以下为本次安装的执行结果与后续操作建议。</p>
        <?php if ($infos): ?>
        <ul class="list">
            <?php foreach ($infos as $i): ?>
            <li>· <?php echo h($i); ?></li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
        <div class="row" style="margin-top:10px;">
            <a class="btn btn-primary" href="index.php">进入前台首页</a>
            <a class="btn btn-ghost" href="admin.php">进入后台</a>
        </div>
        <div class="row">
            <div class="muted">安全提示：
                <ul class="list">
                    <li>· 请立即删除 <code>install.php</code> 或移出网站根目录。</li>
                    <li>· 确认 <code>db_connect.php</code> 权限合理（600/640）。</li>
                    <li>· 强烈建议使用 HTTPS，以启用安全的会话 Cookie 策略。</li>
                </ul>
            </div>
        </div>
    </div>
    <div class="footer">© 安装助手 · 若需再次安装请删除 <code>install.lock</code></div>
    </body>
    </html>
    <?php exit; endif; ?>

    <form method="post" class="card" autocomplete="off">
        <div class="title">数据库配置</div>
        <div class="grid">
            <div class="row">
                <label for="db_host">数据库主机</label>
                <input type="text" id="db_host" name="db_host" required value="<?php echo h($existing['host']); ?>">
            </div>
            <div class="row">
                <label for="db_name">数据库名称</label>
                <input type="text" id="db_name" name="db_name" required value="<?php echo h($existing['dbname']); ?>">
            </div>
            <div class="row">
                <label for="db_user">数据库用户</label>
                <input type="text" id="db_user" name="db_user" required value="<?php echo h($existing['username']); ?>">
            </div>
            <div class="row">
                <label for="db_pass">数据库密码</label>
                <input type="password" id="db_pass" name="db_pass" value="<?php echo h($existing['password']); ?>">
            </div>
        </div>
        <div class="row">
            <label><input type="checkbox" name="create_db" value="1"> 若数据库不存在则创建</label>
        </div>
        <div class="row">
            <label><input type="checkbox" name="import_sql" value="1" checked> 导入初始化结构与示例数据（database_setup.sql）</label>
        </div>
        <div class="row">
            <label><input type="checkbox" name="build_trie" value="1" checked> 构建敏感词前缀树（若失败可稍后手动运行 build.php）</label>
        </div>

        <div class="title" style="margin-top:14px;">管理员设置（可选）</div>
        <div class="row">
            <label><input type="checkbox" name="create_admin" value="1" onclick="toggleAdminFields(this)"> 创建/更新初始管理员</label>
        </div>
        <div id="adminFields" style="display:none;">
            <div class="grid">
                <div class="row">
                    <label for="admin_user">管理员用户名</label>
                    <input type="text" id="admin_user" name="admin_user" placeholder="例如：admin">
                </div>
                <div class="row">
                    <label for="admin_pass">管理员密码</label>
                    <input type="password" id="admin_pass" name="admin_pass" placeholder="输入强密码">
                </div>
                <div class="row">
                    <label for="admin_role">管理员角色</label>
                    <select id="admin_role" name="admin_role">
                        <option value="super_admin" selected>super_admin（完全权限）</option>
                        <option value="admin">admin（常规权限）</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="actions" style="margin-top:8px;">
            <button type="submit" class="btn btn-primary">一键部署</button>
            <a href="index.php" class="btn btn-ghost">返回首页</a>
        </div>
        <div class="muted" style="margin-top:10px;">提示：安装成功后，请删除 <code>install.php</code> 并妥善保管数据库凭据。</div>
    </form>

    <div class="card">
        <div class="title">帮助</div>
        <ul class="list">
            <li>· 安装流程会尝试创建数据库（若勾选）与导入 <code>database_setup.sql</code>。</li>
            <li>· 如需后台登录，请创建管理员账户；也可稍后在数据库中新增。</li>
            <li>· 敏感词前缀树依赖 <code>badwords.php</code>，如需自定义后可再次运行 <code>build.php</code>。</li>
            <li>· 时间与配额设置见后台“时间管理”；也可访问 <code>time_config.php?action=status</code> 查看状态。</li>
            <li>· 完整说明见 <code>readme.md</code>。</li>
        </ul>
    </div>

    <div class="footer">© 安装助手</div>
</div>
</body>
</html>
