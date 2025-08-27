<?php
// 会话安全配置（放在session_start()之前）
ini_set('session.cookie_secure', 'On');
ini_set('session.cookie_httponly', 'On');
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.cookie_lifetime', 0);
ini_set('session.gc_maxlifetime', 3600);
ini_set('session.regenerate_id', 'On');

session_start();
require_once 'db_connect.php';

header('Content-Type: application/json; charset=utf-8');
// ---------- schema ensure ----------
function ensure_schema(PDO $pdo) {
    try {
        // 1) combined_limit 列
        $stmt = $pdo->query("SHOW COLUMNS FROM time_settings LIKE 'combined_limit'");
        if ($stmt->rowCount() === 0) {
            $pdo->exec("ALTER TABLE time_settings ADD COLUMN combined_limit INT NULL DEFAULT NULL");
        } else {
            $col = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($col && strtoupper($col['Null'] ?? '') === 'NO') {
                try { $pdo->exec("ALTER TABLE time_settings MODIFY COLUMN combined_limit INT NULL DEFAULT NULL"); } catch (Exception $e) { /* ignore */ }
            }
        }
        // 1.1) reset_seq 列（用于强制刷新本地次数的序列号）
        $stmt = $pdo->query("SHOW COLUMNS FROM time_settings LIKE 'reset_seq'");
        if ($stmt->rowCount() === 0) {
            $pdo->exec("ALTER TABLE time_settings ADD COLUMN reset_seq INT NOT NULL DEFAULT 0");
        }
        // 2) 将 id 改为自增主键（若还不是）
        $stmt2 = $pdo->query("SHOW COLUMNS FROM time_settings LIKE 'id'");
        $idCol = $stmt2 ? $stmt2->fetch(PDO::FETCH_ASSOC) : null;
        $extra = $idCol['Extra'] ?? '';
        if (stripos($extra, 'auto_increment') === false) {
            // 修改为 INT AUTO_INCREMENT PRIMARY KEY
            try {
                $pdo->exec("ALTER TABLE time_settings MODIFY COLUMN id INT NOT NULL AUTO_INCREMENT PRIMARY KEY");
            } catch (Exception $e) { /* ignore */ }
        }
    } catch (Exception $e) { /* ignore */ }
}
ensure_schema($pdo);


function json_ok($data = [], $message = 'ok') { echo json_encode(['success' => true, 'message' => $message, 'data' => $data], JSON_UNESCAPED_UNICODE); exit; }
function json_err($message = 'error', $code = 400) { http_response_code($code); echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE); exit; }

// ---------- helpers ----------
function get_settings(PDO $pdo) {
    // 取最新一条（id 最大）
    $stmt = $pdo->prepare('SELECT * FROM time_settings ORDER BY id DESC LIMIT 1');
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        // 初始化一条默认配置（自增）
    $pdo->prepare("INSERT INTO time_settings (mode, manual_request_enabled, manual_vote_enabled, request_limit, vote_limit, combined_limit, reset_seq, updated_at) VALUES ('manual', 1, 1, 1, 3, NULL, 0, NOW())")->execute();
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    // 规范布尔
    $row['manual_request_enabled'] = (bool)$row['manual_request_enabled'];
    $row['manual_vote_enabled'] = (bool)$row['manual_vote_enabled'];
    $row['request_limit'] = (int)$row['request_limit'];
    $row['vote_limit'] = (int)$row['vote_limit'];
    // combined_limit 允许为 NULL（未启用）。仅在非 NULL 时转换为 int。
    if (array_key_exists('combined_limit', $row) && $row['combined_limit'] !== null) {
        $row['combined_limit'] = (int)$row['combined_limit'];
    } else {
        $row['combined_limit'] = null;
    }
    $row['reset_seq'] = isset($row['reset_seq']) ? (int)$row['reset_seq'] : 0;
    return $row;
}

function list_rules(PDO $pdo, $onlyActive = false) {
    $sql = 'SELECT * FROM time_rules';
    if ($onlyActive) $sql .= ' WHERE active = 1';
    $sql .= ' ORDER BY id ASC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) {
        $r['active'] = (bool)$r['active'];
        $r['type'] = (int)$r['type'];
        $r['start_weekday'] = is_null($r['start_weekday']) ? null : (int)$r['start_weekday'];
        $r['end_weekday'] = is_null($r['end_weekday']) ? null : (int)$r['end_weekday'];
    }
    return $rows;
}

// 计算当前是否开放与下一次开关时间
function compute_status(PDO $pdo) {
    $settings = get_settings($pdo);
    $rules = list_rules($pdo, true);

    $nowTs = time();
    $now = new DateTime('now', new DateTimeZone(date_default_timezone_get()));
    $serverNowIso = $now->format(DateTime::ATOM);

    // 手动模式优先
    if ($settings['mode'] === 'manual') {
        $total = $settings['combined_limit']; // 可能为 null
        return [
            'now' => $serverNowIso,
            'mode' => 'manual',
            'reset_seq' => (int)$settings['reset_seq'],
            'request' => [
                'open' => (bool)$settings['manual_request_enabled'],
                'next_open' => null,
                'next_close' => null,
            ],
            'vote' => [
                'open' => (bool)$settings['manual_vote_enabled'],
                'next_open' => null,
                'next_close' => null,
            ],
            'limits' => array_filter([
                'request' => (int)$settings['request_limit'],
                'vote' => (int)$settings['vote_limit'],
                // 仅在启用合并上限时返回 total（过滤掉 NULL）
                'total' => $total,
            ], function($v) { return $v !== null; }),
        ];
    }

    // 自动模式：根据规则计算
    $calcFeature = function(string $feature) use ($rules, $nowTs, $now) {
        $open = false;
        $nextOpenTs = null;
        $nextCloseTs = null;
        foreach ($rules as $r) {
            if (!in_array($r['feature'], [$feature, 'both'], true)) continue;
            $res = eval_rule_window($r, $nowTs);
            if ($res['is_open']) {
                $open = true;
                // 取最近的关闭时间（更近的）
                if ($res['end_ts'] !== null) {
                    if ($nextCloseTs === null || $res['end_ts'] < $nextCloseTs) $nextCloseTs = $res['end_ts'];
                }
            } else {
                // 取最近的开启时间（更近的）
                if ($res['start_ts'] !== null) {
                    if ($nextOpenTs === null || $res['start_ts'] < $nextOpenTs) $nextOpenTs = $res['start_ts'];
                }
            }
        }
        return [
            'open' => $open,
            'next_open' => $nextOpenTs ? dt_atom($nextOpenTs) : null,
            'next_close' => $nextCloseTs ? dt_atom($nextCloseTs) : null,
        ];
    };

    $request = $calcFeature('request');
    $vote = $calcFeature('vote');

    $total = $settings['combined_limit']; // 可能为 null
    return [
        'now' => $serverNowIso,
        'mode' => 'auto',
    'reset_seq' => (int)$settings['reset_seq'],
        'request' => $request,
        'vote' => $vote,
        'limits' => array_filter([
            'request' => (int)$settings['request_limit'],
            'vote' => (int)$settings['vote_limit'],
            // 仅在启用合并上限时返回 total（过滤掉 NULL）
            'total' => $total,
        ], function($v) { return $v !== null; }),
    ];
}

// 规则窗口计算：返回当前是否在窗口内、最近开始/结束时间戳
function eval_rule_window(array $r, int $nowTs): array {
    // helpers
    $N = (int)date('N', $nowTs); // 1..7
    $HMS = date('H:i:s', $nowTs);

    $type = (int)$r['type'];
    $startW = $r['start_weekday'] ? (int)$r['start_weekday'] : null;
    $endW = $r['end_weekday'] ? (int)$r['end_weekday'] : null;
    $startT = $r['start_time'] ?? null;
    $endT = $r['end_time'] ?? null;

    // convert week+time to minutes since week start
    $toIndex = function($w, $t) {
        if ($w === null || $t === null) return null;
        [$h,$m,$s] = array_map('intval', explode(':', $t));
        return ($w - 1) * 24 * 60 + $h * 60 + $m + ($s > 0 ? 1 : 0);
    };
    $nowIdx = $toIndex($N, $HMS);

    $isOpen = false; $startTs = null; $endTs = null;

    if ($type === 1) {
        // 每日固定开始/结束
        if (!$startT || !$endT) return ['is_open'=>false,'start_ts'=>null,'end_ts'=>null];
        $todayStart = strtotime(date('Y-m-d ', $nowTs) . $startT);
        $todayEnd = strtotime(date('Y-m-d ', $nowTs) . $endT);
        $cross = $todayEnd <= $todayStart; // 跨天
        if ($cross) $todayEnd += 86400; // 明天结束
        $isOpen = ($nowTs >= $todayStart && $nowTs < $todayEnd);
        if ($isOpen) {
            $endTs = $todayEnd;
        } else {
            $startTs = ($nowTs < $todayStart) ? $todayStart : $todayStart + 86400; // 下一次开始是今天或明天
        }
    } elseif ($type === 2) {
        // 每周跨天：startW+startT 至 endW+endT（包裹一周）
        if ($startW === null || $endW === null || !$startT || !$endT) return ['is_open'=>false,'start_ts'=>null,'end_ts'=>null];
        $startIdx = $toIndex($startW, $startT);
        $endIdx = $toIndex($endW, $endT);
        $wrap = $endIdx <= $startIdx; // 跨周
        $in = $wrap ? ($nowIdx >= $startIdx || $nowIdx < $endIdx) : ($nowIdx >= $startIdx && $nowIdx < $endIdx);
        $isOpen = $in;
        // 计算下一节点
        $weekStartTs = strtotime('last monday 00:00:00', $nowTs + 86400); // ensure forward
        // 以当周一为起点，计算开始/结束时刻（可能在下周）
        $startTs0 = $weekStartTs + $startIdx * 60;
        $endTs0 = $weekStartTs + $endIdx * 60 + ($wrap ? 7*86400 : 0);
        // 让节点落在“下一次即将发生”的时间
        while ($endTs0 <= $nowTs) { $startTs0 += 7*86400; $endTs0 += 7*86400; }
        while ($startTs0 <= $nowTs && !$isOpen) { $startTs0 += 7*86400; }
        if ($isOpen) {
            $endTs = $endTs0;
        } else {
            $startTs = $startTs0;
        }
    } elseif ($type === 3) {
        // 每周周几范围（每日固定开始/结束）
        if ($startW === null || $endW === null || !$startT || !$endT) return ['is_open'=>false,'start_ts'=>null,'end_ts'=>null];
        // 判断今天是否在范围
        $inRange = $startW <= $endW ? ($N >= $startW && $N <= $endW) : ($N >= $startW || $N <= $endW);
        $startToday = strtotime(date('Y-m-d ', $nowTs) . $startT);
        $endToday = strtotime(date('Y-m-d ', $nowTs) . $endT);
        $cross = $endToday <= $startToday; // 当日跨天
        if ($cross) $endToday += 86400;
        $isOpen = $inRange && ($nowTs >= $startToday && $nowTs < $endToday);
        if ($isOpen) {
            $endTs = $endToday;
        } else {
            // 下一次开始：
            $next = next_day_in_range($nowTs, $startW, $endW, $startT);
            $startTs = $next;
        }
    }
    return ['is_open'=>$isOpen,'start_ts'=>$startTs,'end_ts'=>$endTs];
}

function next_day_in_range(int $nowTs, int $startW, int $endW, string $startT): ?int {
    for ($i = 0; $i < 14; $i++) { // 至多两周查找
        $ts = $nowTs + $i * 86400;
        $w = (int)date('N', $ts);
        $in = $startW <= $endW ? ($w >= $startW && $w <= $endW) : ($w >= $startW || $w <= $endW);
        if ($in) {
            $candidate = strtotime(date('Y-m-d ', $ts) . $startT);
            if ($candidate > $nowTs) return $candidate;
        }
    }
    return null;
}

function dt_atom($ts) { return date(DATE_ATOM, $ts); }

// ---------- routing ----------
$action = $_GET['action'] ?? $_POST['action'] ?? 'status';

if ($action === 'status') {
    $data = compute_status($pdo);
    json_ok($data);
}

// 需要管理员
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    json_err('未授权访问', 403);
}

if ($action === 'get_settings') {
    json_ok(get_settings($pdo));
}

if ($action === 'list_rules') {
    json_ok(['rules' => list_rules($pdo, false)]);
}

if ($action === 'update_settings' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = get_settings($pdo); // 用于继承 reset_seq
    $mode = $_POST['mode'] ?? 'manual';
    if (!in_array($mode, ['manual','auto'], true)) json_err('mode不合法');
    $manual_request_enabled = isset($_POST['manual_request_enabled']) ? (int)!!$_POST['manual_request_enabled'] : 0;
    $manual_vote_enabled = isset($_POST['manual_vote_enabled']) ? (int)!!$_POST['manual_vote_enabled'] : 0;
    $request_limit = isset($_POST['request_limit']) ? (int)$_POST['request_limit'] : 0;
    $vote_limit = isset($_POST['vote_limit']) ? (int)$_POST['vote_limit'] : 0;
    // 空字符串视为未启用（NULL）
    $combined_limit_raw = $_POST['combined_limit'] ?? null;
    $combined_limit = ($combined_limit_raw === null || $combined_limit_raw === '') ? null : (int)$combined_limit_raw;
    if ($request_limit < 0 || $request_limit > 100) json_err('request_limit范围不合法');
    if ($vote_limit < 0 || $vote_limit > 100) json_err('vote_limit范围不合法');
    if ($combined_limit !== null && ($combined_limit < 0 || $combined_limit > 200)) json_err('combined_limit范围不合法');
    // 新增一条记录（按 id 自增，最新即当前生效）
    if ($combined_limit !== null) {
        $stmt = $pdo->prepare('INSERT INTO time_settings (mode, manual_request_enabled, manual_vote_enabled, combined_limit, request_limit, vote_limit, reset_seq, updated_at) VALUES (:mode,:mre,:mve,:cl,:rl,:vl,:rs,NOW())');
        $stmt->execute(['mode'=>$mode,'mre'=>$manual_request_enabled,'mve'=>$manual_vote_enabled,'cl'=>$combined_limit,'rl'=>$request_limit,'vl'=>$vote_limit,'rs'=>(int)$current['reset_seq']]);
    } else {
        $stmt = $pdo->prepare('INSERT INTO time_settings (mode, manual_request_enabled, manual_vote_enabled, request_limit, vote_limit, combined_limit, reset_seq, updated_at) VALUES (:mode,:mre,:mve,:rl,:vl,NULL,:rs,NOW())');
        $stmt->execute(['mode'=>$mode,'mre'=>$manual_request_enabled,'mve'=>$manual_vote_enabled,'rl'=>$request_limit,'vl'=>$vote_limit,'rs'=>(int)$current['reset_seq']]);
    }
    // 日志
    $user = $_SESSION['admin_username'] ?? '';
    $role = $_SESSION['admin_role'] ?? '';
    $details = ['mode'=>$mode,'mre'=>$manual_request_enabled,'mve'=>$manual_vote_enabled,'rl'=>$request_limit,'vl'=>$vote_limit];
    if ($combined_limit !== null) { $details['cl'] = $combined_limit; }
    log_operation($pdo, $user, $role, '更新时间设置', null, json_encode($details, JSON_UNESCAPED_UNICODE));
    json_ok();
}

if ($action === 'force_reset' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // 仅管理员已在上方判断
    $current = get_settings($pdo);
    $newSeq = ((int)$current['reset_seq']) + 1;
    // 复制当前设置，插入新记录但仅 bump reset_seq
    if ($current['combined_limit'] !== null) {
        $stmt = $pdo->prepare('INSERT INTO time_settings (mode, manual_request_enabled, manual_vote_enabled, combined_limit, request_limit, vote_limit, reset_seq, updated_at) VALUES (:mode,:mre,:mve,:cl,:rl,:vl,:rs,NOW())');
        $stmt->execute([
            'mode'=>$current['mode'],
            'mre'=>(int)$current['manual_request_enabled'],
            'mve'=>(int)$current['manual_vote_enabled'],
            'cl'=>(int)$current['combined_limit'],
            'rl'=>(int)$current['request_limit'],
            'vl'=>(int)$current['vote_limit'],
            'rs'=>$newSeq,
        ]);
    } else {
        $stmt = $pdo->prepare('INSERT INTO time_settings (mode, manual_request_enabled, manual_vote_enabled, request_limit, vote_limit, combined_limit, reset_seq, updated_at) VALUES (:mode,:mre,:mve,:rl,:vl,NULL,:rs,NOW())');
        $stmt->execute([
            'mode'=>$current['mode'],
            'mre'=>(int)$current['manual_request_enabled'],
            'mve'=>(int)$current['manual_vote_enabled'],
            'rl'=>(int)$current['request_limit'],
            'vl'=>(int)$current['vote_limit'],
            'rs'=>$newSeq,
        ]);
    }
    $user = $_SESSION['admin_username'] ?? '';
    $role = $_SESSION['admin_role'] ?? '';
    log_operation($pdo, $user, $role, '强制刷新用户本地次数', null, json_encode(['reset_seq'=>$newSeq], JSON_UNESCAPED_UNICODE));
    json_ok(['reset_seq' => $newSeq], '已触发强制刷新');
}

if ($action === 'add_rule' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $feature = $_POST['feature'] ?? 'both';
    if (!in_array($feature, ['request','vote','both'], true)) json_err('feature不合法');
    $type = (int)($_POST['type'] ?? 0);
    if (!in_array($type, [1,2,3], true)) json_err('type不合法');
    $start_weekday = isset($_POST['start_weekday']) && $_POST['start_weekday'] !== '' ? (int)$_POST['start_weekday'] : null;
    $end_weekday = isset($_POST['end_weekday']) && $_POST['end_weekday'] !== '' ? (int)$_POST['end_weekday'] : null;
    $start_time = $_POST['start_time'] ?? null;
    $end_time = $_POST['end_time'] ?? null;
    $description = trim($_POST['description'] ?? '');

    // 校验按类型
    if ($type === 1) { if (!$start_time || !$end_time) json_err('type=1 需提供 start_time/end_time'); }
    if ($type === 2) { if ($start_weekday===null || $end_weekday===null || !$start_time || !$end_time) json_err('type=2 需提供 起止周与时间'); }
    if ($type === 3) { if ($start_weekday===null || $end_weekday===null || !$start_time || !$end_time) json_err('type=3 需提供 起止周与时间'); }
    $stmt = $pdo->prepare('INSERT INTO time_rules (feature, type, start_weekday, end_weekday, start_time, end_time, active, description, created_at, updated_at) VALUES (:f,:t,:sw,:ew,:st,:et,1,:d,NOW(),NOW())');
    $stmt->execute(['f'=>$feature,'t'=>$type,'sw'=>$start_weekday,'ew'=>$end_weekday,'st'=>$start_time,'et'=>$end_time,'d'=>$description]);
    $id = $pdo->lastInsertId();
    $user = $_SESSION['admin_username'] ?? '';
    $role = $_SESSION['admin_role'] ?? '';
    log_operation($pdo, $user, $role, '新增时间规则', (string)$id, json_encode(['feature'=>$feature,'type'=>$type,'start_weekday'=>$start_weekday,'end_weekday'=>$end_weekday,'start_time'=>$start_time,'end_time'=>$end_time,'description'=>$description], JSON_UNESCAPED_UNICODE));
    json_ok(['id' => (int)$id]);
}

if ($action === 'delete_rule' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($id <= 0) json_err('无效规则ID');
    $stmt = $pdo->prepare('DELETE FROM time_rules WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $user = $_SESSION['admin_username'] ?? '';
    $role = $_SESSION['admin_role'] ?? '';
    log_operation($pdo, $user, $role, '删除时间规则', (string)$id, null);
    json_ok();
}

if ($action === 'toggle_rule' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $active = isset($_POST['active']) ? (int)!!$_POST['active'] : 0;
    if ($id <= 0) json_err('无效规则ID');
    $stmt = $pdo->prepare('UPDATE time_rules SET active=:a, updated_at=NOW() WHERE id=:id');
    $stmt->execute(['a' => $active, 'id' => $id]);
    $user = $_SESSION['admin_username'] ?? '';
    $role = $_SESSION['admin_role'] ?? '';
    log_operation($pdo, $user, $role, '切换时间规则', (string)$id, json_encode(['active'=>$active], JSON_UNESCAPED_UNICODE));
    json_ok();
}

if ($action === 'update_rule' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($id <= 0) json_err('无效规则ID');
    $feature = $_POST['feature'] ?? 'both';
    if (!in_array($feature, ['request','vote','both'], true)) json_err('feature不合法');
    $type = (int)($_POST['type'] ?? 0);
    if (!in_array($type, [1,2,3], true)) json_err('type不合法');
    $start_weekday = isset($_POST['start_weekday']) && $_POST['start_weekday'] !== '' ? (int)$_POST['start_weekday'] : null;
    $end_weekday = isset($_POST['end_weekday']) && $_POST['end_weekday'] !== '' ? (int)$_POST['end_weekday'] : null;
    $start_time = $_POST['start_time'] ?? null;
    $end_time = $_POST['end_time'] ?? null;
    $description = trim($_POST['description'] ?? '');

    // 校验按类型
    if ($type === 1) { if (!$start_time || !$end_time) json_err('type=1 需提供 start_time/end_time'); }
    if ($type === 2) { if ($start_weekday===null || $end_weekday===null || !$start_time || !$end_time) json_err('type=2 需提供 起止周与时间'); }
    if ($type === 3) { if ($start_weekday===null || $end_weekday===null || !$start_time || !$end_time) json_err('type=3 需提供 起止周与时间'); }

    $stmt = $pdo->prepare('UPDATE time_rules SET feature=:f, type=:t, start_weekday=:sw, end_weekday=:ew, start_time=:st, end_time=:et, description=:d, updated_at=NOW() WHERE id=:id');
    $stmt->execute(['f'=>$feature,'t'=>$type,'sw'=>$start_weekday,'ew'=>$end_weekday,'st'=>$start_time,'et'=>$end_time,'d'=>$description,'id'=>$id]);

    // 查询并返回最新记录
    $q = $pdo->prepare('SELECT * FROM time_rules WHERE id = :id');
    $q->execute(['id'=>$id]);
    $row = $q->fetch(PDO::FETCH_ASSOC);
    $user = $_SESSION['admin_username'] ?? '';
    $role = $_SESSION['admin_role'] ?? '';
    log_operation($pdo, $user, $role, '编辑时间规则', (string)$id, json_encode(['feature'=>$feature,'type'=>$type,'start_weekday'=>$start_weekday,'end_weekday'=>$end_weekday,'start_time'=>$start_time,'end_time'=>$end_time,'description'=>$description], JSON_UNESCAPED_UNICODE));
    json_ok(['rule' => $row]);
}

json_err('未知操作', 400);
?>
