<?php
// 模糊测试：自动生成时间配置并校验 compute_status 结果，直到出现问题或达到迭代上限
// 用法：php tests/fuzz_time_config.php [maxIters]

declare(strict_types=1);

// 让 time_config.php 仅加载函数而不响应路由
if (!defined('TIME_CONFIG_LIB')) define('TIME_CONFIG_LIB', true);
require_once __DIR__ . '/../time_config.php'; // 提供 $pdo 与 compute_status()

// 工具函数
function rnd_bool(): bool { return (bool)random_int(0, 1); }
function now_ts(): int { return time(); }
function fmt_his(int $ts): string { return date('H:i:s', $ts); }
function todayN(): int { return (int)date('N'); } // 1..7

function set_settings(PDO $pdo, array $cfg): void {
    $cur = get_settings($pdo);
    $mode = $cfg['mode'];
    $mre = (int)$cfg['mre'];
    $mve = (int)$cfg['mve'];
    $rl  = (int)$cfg['rl'];
    $vl  = (int)$cfg['vl'];
    $cl  = $cfg['cl']; // null|int
    if ($cl !== null) {
        $stmt = $pdo->prepare('INSERT INTO time_settings (mode, manual_request_enabled, manual_vote_enabled, combined_limit, request_limit, vote_limit, reset_seq, updated_at) VALUES (:mode,:mre,:mve,:cl,:rl,:vl,:rs,NOW())');
        $stmt->execute(['mode'=>$mode,'mre'=>$mre,'mve'=>$mve,'cl'=>(int)$cl,'rl'=>$rl,'vl'=>$vl,'rs'=>(int)$cur['reset_seq']]);
    } else {
        $stmt = $pdo->prepare('INSERT INTO time_settings (mode, manual_request_enabled, manual_vote_enabled, request_limit, vote_limit, combined_limit, reset_seq, updated_at) VALUES (:mode,:mre,:mve,:rl,:vl,NULL,:rs,NOW())');
        $stmt->execute(['mode'=>$mode,'mre'=>$mre,'mve'=>$mve,'rl'=>$rl,'vl'=>$vl,'rs'=>(int)$cur['reset_seq']]);
    }
}

function clear_rules(PDO $pdo): void {
    $pdo->exec('DELETE FROM time_rules');
}

function insert_rule(PDO $pdo, array $r): int {
    $stmt = $pdo->prepare('INSERT INTO time_rules (feature, type, start_weekday, end_weekday, start_time, end_time, active, description, created_at, updated_at) VALUES (:f,:t,:sw,:ew,:st,:et,:a,:d,NOW(),NOW())');
    $stmt->execute([
        'f'=>$r['feature'],'t'=>$r['type'],'sw'=>$r['start_weekday'],'ew'=>$r['end_weekday'],
        'st'=>$r['start_time'],'et'=>$r['end_time'],'a'=>$r['active'] ? 1 : 0,'d'=>$r['description']
    ]);
    return (int)$pdo->lastInsertId();
}

function gen_rule_params(string $feature): array {
    $now = now_ts();
    $N = todayN();
    $type = [1,2,3][array_rand([1,2,3])];
    $wantOpen = rnd_bool();
    $active = rnd_bool();
    $desc = "auto-$feature-t$type-" . ($wantOpen? 'open':'closed');
    $sw = null; $ew = null; $st = null; $et = null;
    if ($type === 1) {
        // 日内固定，50% 跨午夜
        $cross = rnd_bool();
        if ($wantOpen) {
            if ($cross) {
                // 确保 now < end 或 now >= start
                $st = fmt_his($now + random_int(3600, 6*3600)); // 未来晚些
                $et = fmt_his($now + random_int(60, 1800));      // 未来不远
            } else {
                $st = fmt_his($now - random_int(60, 600));
                $et = fmt_his($now + random_int(60, 1200));
            }
        } else {
            if ($cross) {
                $st = fmt_his($now + random_int(600, 3600));
                $et = fmt_his($now - random_int(60, 600));
            } else {
                $st = fmt_his($now + random_int(600, 1800));
                $et = fmt_his($now + random_int(2400, 4800));
            }
        }
    } elseif ($type === 2) {
        // 每周跨天，50% 跨周 wrap
        $wrap = rnd_bool();
        if ($wrap) {
            $sw = ($N % 7) + 1; // 明天
            $ew = $N;           // 今天
        } else {
            $sw = $N; $ew = $N;
        }
        if ($wantOpen) {
            if ($wrap) {
                // open 条件：now < end 或 now >= start
                $st = fmt_his($now + random_int(3600, 6*3600));
                $et = fmt_his($now + random_int(60, 1800));
            } else {
                $st = fmt_his($now - random_int(60, 600));
                $et = fmt_his($now + random_int(60, 1800));
            }
        } else {
            if ($wrap) {
                $st = fmt_his($now + random_int(600, 3600));
                $et = fmt_his($now - random_int(60, 600));
            } else {
                $st = fmt_his($now + random_int(3600, 7200));
                $et = fmt_his($now + random_int(9000, 14400));
            }
        }
    } else {
        // type=3 周几范围（每日固定），50% 跨午夜
        $sw = $N; $ew = $N; // 覆盖当天（也可扩展范围）
        $cross = rnd_bool();
        if ($wantOpen) {
            if ($cross) {
                $st = fmt_his($now + random_int(3600, 6*3600));
                $et = fmt_his($now + random_int(60, 1800));
            } else {
                $st = fmt_his($now - random_int(60, 600));
                $et = fmt_his($now + random_int(60, 1800));
            }
        } else {
            if ($cross) {
                $st = fmt_his($now + random_int(600, 3600));
                $et = fmt_his($now - random_int(60, 600));
            } else {
                $st = fmt_his($now + random_int(900, 3600));
                $et = fmt_his($now + random_int(4000, 9000));
            }
        }
    }
    return [
        'feature'=>$feature,
        'type'=>$type,
        'start_weekday'=>$sw,
        'end_weekday'=>$ew,
        'start_time'=>$st,
        'end_time'=>$et,
        'active'=>$active,
        'description'=>$desc,
        'want_open'=>$wantOpen,
    ];
}

function aggregate_expected(array $rules, string $feature, int $nowTs): array {
    // 仅考虑 active 且 feature 命中（feature 或 both）
    $open = false; $nextOpen = null; $nextClose = null;
    foreach ($rules as $r) {
        if (!$r['active']) continue;
        if (!in_array($r['feature'], [$feature, 'both'], true)) continue;
        $res = eval_rule_window($r, $nowTs);
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
    return [
        'open' => $open,
        'next_open' => $nextOpen,
        'next_close' => $nextClose,
    ];
}

function assert_true(bool $cond, string $msg, array $ctx = []): void {
    if ($cond) return;
    $dump = json_encode(['error'=>$msg,'ctx'=>$ctx], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    $dir = __DIR__ . '/failures'; if (!is_dir($dir)) @mkdir($dir, 0777, true);
    $file = $dir . '/fail_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.json';
    file_put_contents($file, $dump);
    fwrite(STDERR, "[FAIL] $msg\nSee: $file\n");
    exit(1);
}

function bump_reset_seq_cli(PDO $pdo): int {
    // 等价于 tests/tools/force_reset_cli.php 行为，但复用当前连接，返回新 seq
    $row = $pdo->query('SELECT * FROM time_settings ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    if (!$row) return 0;
    $newSeq = (int)($row['reset_seq'] ?? 0) + 1;
    if ($row['combined_limit'] !== null) {
        $stmt = $pdo->prepare('INSERT INTO time_settings (mode, manual_request_enabled, manual_vote_enabled, combined_limit, request_limit, vote_limit, reset_seq, updated_at) VALUES (:mode,:mre,:mve,:cl,:rl,:vl,:rs,NOW())');
        $stmt->execute(['mode'=>$row['mode'],'mre'=>(int)$row['manual_request_enabled'],'mve'=>(int)$row['manual_vote_enabled'],'cl'=>(int)$row['combined_limit'],'rl'=>(int)$row['request_limit'],'vl'=>(int)$row['vote_limit'],'rs'=>$newSeq]);
    } else {
        $stmt = $pdo->prepare('INSERT INTO time_settings (mode, manual_request_enabled, manual_vote_enabled, request_limit, vote_limit, combined_limit, reset_seq, updated_at) VALUES (:mode,:mre,:mve,:rl,:vl,NULL,:rs,NOW())');
        $stmt->execute(['mode'=>$row['mode'],'mre'=>(int)$row['manual_request_enabled'],'mve'=>(int)$row['manual_vote_enabled'],'rl'=>(int)$row['request_limit'],'vl'=>(int)$row['vote_limit'],'rs'=>$newSeq]);
    }
    return $newSeq;
}

// 主循环
$arg = $argv[1] ?? null;
$infinite = false;
if ($arg === null) { $max = 500; }
else if (is_numeric($arg)) { $max = (int)$arg; $infinite = ($max === 0); if ($max < 0) $max = 1; }
else { $infinite = (strtolower((string)$arg) === 'infinite'); $max = $infinite ? 1 : 500; }

$i = 0;
while (true) {
    $i++;
    // 随机生成设置
    $mode = rnd_bool() ? 'manual' : 'auto';
    $combined = rnd_bool();
    $cfg = [
        'mode' => $mode,
        'mre'  => rnd_bool(),
        'mve'  => rnd_bool(),
        'rl'   => random_int(0, 100),
        'vl'   => random_int(0, 100),
        'cl'   => $combined ? random_int(0, 200) : null,
    ];
    set_settings($pdo, $cfg);

    $rules = [];
    if ($mode === 'auto') {
        clear_rules($pdo);
        // 随机生成 0..8 条规则，feature 覆盖 request/vote/both，含 active 与重叠
        $count = random_int(0, 8);
        $features = ['request','vote','both'];
        for ($k=0; $k<$count; $k++) {
            $f = $features[array_rand($features)];
            $r = gen_rule_params($f);
            insert_rule($pdo, $r);
            $rules[] = $r;
        }
    }

    // 获取状态（直接调用被测代码的 compute_status）
    $status = compute_status($pdo);

    // 断言：模式与上限
    assert_true($status['mode'] === $mode, 'mode不一致', ['status'=>$status,'cfg'=>$cfg,'rules'=>$rules]);
    $totalPresent = array_key_exists('total', $status['limits']);
    assert_true($totalPresent === ($cfg['cl'] !== null), '合并上限 total 显示不一致', ['status'=>$status,'cfg'=>$cfg]);

    if ($mode === 'manual') {
        assert_true((bool)$status['request']['open'] === (bool)$cfg['mre'], '手动模式 request.open 错误', ['status'=>$status,'cfg'=>$cfg]);
        assert_true((bool)$status['vote']['open'] === (bool)$cfg['mve'], '手动模式 vote.open 错误', ['status'=>$status,'cfg'=>$cfg]);
        assert_true($status['request']['next_open'] === null && $status['request']['next_close'] === null, '手动模式 request next_* 应为 null', ['status'=>$status]);
        assert_true($status['vote']['next_open'] === null && $status['vote']['next_close'] === null, '手动模式 vote next_* 应为 null', ['status'=>$status]);
    } else {
        // 自动模式：聚合所有规则（含 both & active=1），用 eval_rule_window 得到期望
        $now = now_ts();
        $expReq = aggregate_expected($rules, 'request', $now);
        $expVote = aggregate_expected($rules, 'vote', $now);

        assert_true((bool)$status['request']['open'] === (bool)$expReq['open'], '自动模式 request.open 与期望不符', ['status'=>$status,'rules'=>$rules,'expected'=>$expReq]);
        assert_true((bool)$status['vote']['open'] === (bool)$expVote['open'], '自动模式 vote.open 与期望不符', ['status'=>$status,'rules'=>$rules,'expected'=>$expVote]);

        if ($expReq['open']) {
            assert_true(!empty($status['request']['next_close']), '自动模式 request.open 时应提供 next_close', ['status'=>$status]);
            if ($expReq['next_close'] !== null) {
                $expCloseIso = date(DATE_ATOM, $expReq['next_close']);
                assert_true($status['request']['next_close'] === $expCloseIso, 'request.next_close 最近关闭时间不一致', ['status'=>$status,'expected'=>$expCloseIso]);
            }
        } else {
            if ($expReq['next_open'] !== null) {
                $expOpenIso = date(DATE_ATOM, $expReq['next_open']);
                assert_true($status['request']['next_open'] === $expOpenIso, 'request.next_open 最近开启时间不一致', ['status'=>$status,'expected'=>$expOpenIso]);
            } else {
                assert_true($status['request']['next_open'] === null, 'request.next_open 预期为 null', ['status'=>$status]);
            }
        }

        if ($expVote['open']) {
            assert_true(!empty($status['vote']['next_close']), '自动模式 vote.open 时应提供 next_close', ['status'=>$status]);
            if ($expVote['next_close'] !== null) {
                $expCloseIso = date(DATE_ATOM, $expVote['next_close']);
                assert_true($status['vote']['next_close'] === $expCloseIso, 'vote.next_close 最近关闭时间不一致', ['status'=>$status,'expected'=>$expCloseIso]);
            }
        } else {
            if ($expVote['next_open'] !== null) {
                $expOpenIso = date(DATE_ATOM, $expVote['next_open']);
                assert_true($status['vote']['next_open'] === $expOpenIso, 'vote.next_open 最近开启时间不一致', ['status'=>$status,'expected'=>$expOpenIso]);
            } else {
                assert_true($status['vote']['next_open'] === null, 'vote.next_open 预期为 null', ['status'=>$status]);
            }
        }
    }

    // 随机测试：强制刷新序列递增
    if (rnd_bool()) {
        $beforeSeq = (int)($status['reset_seq'] ?? 0);
        $newSeq = bump_reset_seq_cli($pdo);
        $status2 = compute_status($pdo);
        assert_true((int)$status2['reset_seq'] === $newSeq && $newSeq === $beforeSeq + 1, 'reset_seq 递增异常', ['before'=>$status,'after'=>$status2]);
    }

    if ($i % 50 === 0) {
        fwrite(STDOUT, "[PASS] $i iters\n");
    }
    if (!$infinite && $i >= $max) break;
}

if ($infinite) {
    fwrite(STDOUT, "STOPPED manually after $i iterations, no issues found so far.\n");
} else {
    fwrite(STDOUT, "DONE: $max iterations, no issues found.\n");
}
exit(0);
