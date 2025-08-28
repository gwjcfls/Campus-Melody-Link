# 时间配置模糊测试

此脚本会不断随机生成 time_settings 与 time_rules（在 auto 模式下），并调用后端 `compute_status()` 做一致性断言；若发现问题，会在 `tests/failures/` 下保存复现用 JSON。

- 脚本：`tests/fuzz_time_config.php`
- 依赖：MySQL 表 `time_settings`、`time_rules` 已存在（项目会自动迁移列）。

## 运行

- 固定次数：

```bash
php tests/fuzz_time_config.php 500
```

- 无限迭代直到失败（或手动停止）：

```bash
php tests/fuzz_time_config.php infinite
# 或
php tests/fuzz_time_config.php 0
```

- 输出：每 50 次打印一次进度；失败时打印错误摘要并生成 `tests/failures/fail_*.json` 记录当次设置与规则、以及状态快照。

## 断言覆盖

- 模式一致性：`status.mode` 与生成的 `mode` 匹配。
- 合并上限：当我们设置 `combined_limit` 时，`limits.total` 必须存在；否则不应返回。
- 手动模式：`request.open`、`vote.open` 分别与 `manual_*_enabled` 一致，且 `next_open/next_close` 应为 `null`。
- 自动模式：针对 request/vote 各造一条规则，`open` 应与我们设定的“当前时间是否落在生成窗口内”的期望一致；打开时应给出 `next_close`，关闭时应给出 `next_open`。
- reset_seq：随机触发一次“强制刷新序列自增”，校验 `reset_seq` 递增正确。

## 失败样例

失败时，`tests/failures/*.json` 会包含：
- `error`：失败原因
- `ctx`：
  - `status`：`compute_status()` 返回值
  - `cfg`：当次的 `time_settings` 输入摘要
  - `rules`：当次自动模式下生成的规则列表（含期望 open 标记）

可用这些数据手动在数据库中复现问题或编写固定单元测试。
