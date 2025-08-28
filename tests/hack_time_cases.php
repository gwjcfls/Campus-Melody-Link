<?php
// Hack 数据专测：构造极端/边界规则组合并校验 compute_status 聚合结果
// 用法：php tests/hack_time_cases.php

declare(strict_types=1);
if (!defined('TIME_CONFIG_LIB')) define('TIME_CONFIG_LIB', true);
require_once __DIR__ . '/../time_config.php';

function assert_true(bool $cond, string $msg, array $ctx = []): void {
    if ($cond) return;
    $dump = json_encode(['error'=>$msg,'ctx'=>$ctx], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    $dir = __DIR__ . '/failures'; if (!is_dir($dir)) @mkdir($dir, 0777, true);
    $file = $dir . '/hack_fail_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.json';
    file_put_contents($file, $dump);
    fwrite(STDERR, "[FAIL] $msg\nSee: $file\n");
    exit(1);
}

function clear_rules(PDO $pdo): void { $pdo->exec('DELETE FROM time_rules'); }
function set_auto_settings(PDO $pdo, ?int $cl = null): void {
    $cur = get_settings($pdo);
    if ($cl !== null) {
        $stmt = $pdo->prepare('INSERT INTO time_settings (mode, manual_request_enabled, manual_vote_enabled, combined_limit, request_limit, vote_limit, reset_seq, updated_at) VALUES ("auto",0,0,:cl,1,1,:rs,NOW())');
        $stmt->execute(['cl'=>$cl,'rs'=>(int)$cur['reset_seq']]);
    } else {
        $stmt = $pdo->prepare('INSERT INTO time_settings (mode, manual_request_enabled, manual_vote_enabled, request_limit, vote_limit, combined_limit, reset_seq, updated_at) VALUES ("auto",0,0,1,1,NULL,:rs,NOW())');
        $stmt->execute(['rs'=>(int)$cur['reset_seq']]);
    }
}

function add(array &$rules, PDO $pdo, string $feature, int $type, $sw, $ew, string $st, string $et, bool $active, string $desc): void {
    $stmt = $pdo->prepare('INSERT INTO time_rules (feature, type, start_weekday, end_weekday, start_time, end_time, active, description, created_at, updated_at) VALUES (:f,:t,:sw,:ew,:st,:et,:a,:d,NOW(),NOW())');
    $stmt->execute(['f'=>$feature,'t'=>$type,'sw'=>$sw,'ew'=>$ew,'st'=>$st,'et'=>$et,'a'=>$active?1:0,'d'=>$desc]);
    $rules[] = compact('feature','type','sw','ew','st','et','active','desc');
}

function aggregate_expected(array $rows, string $feature, int $nowTs): array {
    $open = false; $nextOpen = null; $nextClose = null;
    foreach ($rows as $r) {
        if (!$r['active']) continue;
        if (!in_array($r['feature'], [$feature, 'both'], true)) continue;
        // 转换键名以适配 eval_rule_window
        $R = [
            'feature'=>$r['feature'],
            'type'=>$r['type'],
            'start_weekday'=>$r['sw'],
            'end_weekday'=>$r['ew'],
            'start_time'=>$r['st'],
            'end_time'=>$r['et'],
            'active'=>$r['active'],
        ];
        $res = eval_rule_window($R, $nowTs);
        if ($res['is_open']) {
            $open = true;
            if ($res['end_ts'] !== null) {
                if ($nextClose === null || $res['end_ts'] > $nextClose) $nextClose = $res['end_ts'];
            }
        } else {
            if ($res['start_ts'] !== null) {
                if ($nextOpen === null || $res['start_ts'] < $nextOpen) $nextOpen = $res['start_ts'];
            }
        }
    }
    return ['open'=>$open,'next_open'=>$nextOpen,'next_close'=>$nextClose];
}

$now = time();
$N = (int)date('N', $now);
$today = function(string $t) use ($now) { return date('H:i:s', strtotime(date('Y-m-d ',$now).$t)); };

// Case 1: type=1 start==end（24h全开），feature=both
clear_rules($pdo); set_auto_settings($pdo, 0);
$rules = [];
add($rules, $pdo, 'both', 1, null, null, $today('12:00:00'), $today('12:00:00'), true, 't1-24h-open');
$status = compute_status($pdo);
$expReq = aggregate_expected($rules,'request',$now); $expVote = aggregate_expected($rules,'vote',$now);
assert_true($status['request']['open'] === true && $status['vote']['open'] === true, '24h全开应为 open', ['status'=>$status]);
assert_true(!empty($status['request']['next_close']) && !empty($status['vote']['next_close']), '24h全开应提供 next_close', ['status'=>$status]);

// Case 2: type=1 跨午夜（23:59:59 -> 00:00:00 仅1秒），大概率 closed
clear_rules($pdo); set_auto_settings($pdo, null);
$rules = [];
add($rules, $pdo, 'request', 1, null, null, $today('23:59:59'), $today('00:00:00'), true, 't1-one-second');
$status = compute_status($pdo);
$expReq = aggregate_expected($rules,'request',$now);
assert_true(is_bool($status['request']['open']), 'open 字段应为布尔', ['status'=>$status]);
if (!$expReq['open']) {
    $expOpenIso = $expReq['next_open'] ? date(DATE_ATOM, $expReq['next_open']) : null;
    assert_true($status['request']['next_open'] === $expOpenIso, 'next_open 不一致', ['status'=>$status,'expected'=>$expOpenIso]);
}

// Case 3: type=2 wrap（startW=N+1, endW=N），并设置 open now
clear_rules($pdo); set_auto_settings($pdo, 200);
$rules = [];
$sw = ($N % 7) + 1; $ew = $N;
add($rules, $pdo, 'both', 2, $sw, $ew, $today('08:00:00'), $today('07:59:59'), true, 't2-wrap-open');
$status = compute_status($pdo);
$expReq = aggregate_expected($rules,'request',$now); $expVote = aggregate_expected($rules,'vote',$now);
assert_true((bool)$status['request']['open'] === (bool)$expReq['open'], 't2-wrap request.open 不一致', ['status'=>$status,'expected'=>$expReq]);
assert_true((bool)$status['vote']['open'] === (bool)$expVote['open'], 't2-wrap vote.open 不一致', ['status'=>$status,'expected'=>$expVote]);

// Case 4: type=3 周几范围 wrap + 跨午夜；再叠加一个 active=0 的冲突规则
clear_rules($pdo); set_auto_settings($pdo, null);
$rules = [];
$sw2 = $N; $ew2 = ($N+5)%7 + 1; // 让 startW 可能 > endW
add($rules, $pdo, 'request', 3, $sw2, $ew2, $today('23:00:00'), $today('01:00:00'), true, 't3-wrap-midnight-open');
// inactive 冲突规则（应被忽略）
add($rules, $pdo, 'request', 1, null, null, $today('00:00:00'), $today('00:10:00'), false, 'inactive-ignore');
$status = compute_status($pdo);
$expReq = aggregate_expected($rules,'request',$now);
assert_true((bool)$status['request']['open'] === (bool)$expReq['open'], 't3-wrap-midnight request.open 不一致', ['status'=>$status,'expected'=>$expReq,'rules'=>$rules]);

// Case 5: 多条重叠，选择最晚 next_close（取更晚的结束）
clear_rules($pdo); set_auto_settings($pdo, 10);
$rules = [];
// 两条同时 open：一个 10 分钟后关，另一个 3 分钟后关，应选 10 分钟（最晚）
$soon3 = date('H:i:s', $now + 180);
$soon10 = date('H:i:s', $now + 600);
add($rules, $pdo, 'both', 1, null, null, date('H:i:s', $now - 60), $soon10, true, 'close-10m');
add($rules, $pdo, 'both', 1, null, null, date('H:i:s', $now - 60), $soon3, true, 'close-3m');
$status = compute_status($pdo);
$expReq = aggregate_expected($rules,'request',$now);
$expVote = aggregate_expected($rules,'vote',$now);
$expCloseIso = $expReq['next_close'] ? date(DATE_ATOM, $expReq['next_close']) : null;
assert_true($status['request']['open'] && $status['vote']['open'], '应当 open', ['status'=>$status]);
assert_true($status['request']['next_close'] === $expCloseIso && $status['vote']['next_close'] === $expCloseIso, '最晚 next_close 应一致且为更晚者', ['status'=>$status,'expected'=>$expCloseIso]);

// Case 6: both + request 特性混合，验证聚合来源
clear_rules($pdo); set_auto_settings($pdo, null);
$rules = [];
// both 提供 open 窗口，但 request 专属规则提供不同的 next_close；按新的规则应取更晚的结束
add($rules, $pdo, 'both', 1, null, null, date('H:i:s', $now - 60), date('H:i:s', $now + 600), true, 'both-10m');
add($rules, $pdo, 'request', 1, null, null, date('H:i:s', $now - 60), date('H:i:s', $now + 120), true, 'req-2m');
$status = compute_status($pdo);
$expReq = aggregate_expected($rules,'request',$now);
$expCloseIso = $expReq['next_close'] ? date(DATE_ATOM, $expReq['next_close']) : null;
assert_true($status['request']['open'] === true, 'request 应 open', ['status'=>$status]);
assert_true($status['request']['next_close'] === $expCloseIso, 'request.next_close 应取更晚的结束', ['status'=>$status,'expected'=>$expCloseIso]);

fwrite(STDOUT, "HACK CASES: ALL PASS\n");
exit(0);
