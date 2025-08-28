<?php
// 最小化后端 API 验证脚本：直接请求 status 并做基础断言
// 用法：php tests/time_rules_test.php

function request_status(string $url): array {
	$ctx = stream_context_create(['http' => ['timeout' => 5]]);
	$raw = @file_get_contents($url, false, $ctx);
	if ($raw === false) {
		fwrite(STDERR, "请求失败: $url\n");
		exit(1);
	}
	$json = json_decode($raw, true);
	if (!is_array($json)) {
		fwrite(STDERR, "解析 JSON 失败\n");
		exit(1);
	}
	return $json;
}

$base = getenv('SITE_BASE') ?: 'http://localhost';
$statusUrl = rtrim($base, '/') . '/time_config.php?action=status';
$data = request_status($statusUrl);

if (!($data['success'] ?? false)) {
	fwrite(STDERR, "API 返回失败\n");
	exit(1);
}

$payload = $data['data'] ?? [];
$required = ['mode','request','vote','limits'];
foreach ($required as $k) {
	if (!array_key_exists($k, $payload)) {
		fwrite(STDERR, "缺少字段: $k\n");
		exit(1);
	}
}

echo "OK: status 基本字段存在，mode={$payload['mode']} reset_seq=" . ($payload['reset_seq'] ?? 'N/A') . "\n";
exit(0);

