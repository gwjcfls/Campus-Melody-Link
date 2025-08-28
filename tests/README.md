# 时间配置与次数重置 自动化验证

本套测试用于验证：

- 手动/自动模式下的开放与关闭状态是否正确返回（`time_config.php?action=status`）。
- 进入新一轮开放窗口时，前端是否清空本地计数（点歌/投票/合并）与本地已点赞列表。
- 管理员触发的“强制刷新”（`reset_seq` 递增）是否能让前端在下一次状态刷新时重置本地计数。
- 首次进入的用户不应看到“次数已刷新（管理员触发）”提示。

## 目录

- `tools/force_reset_cli.php`：命令行触发一次强制刷新（无需管理员登录）。
- `browser/`：可选的浏览器端自动化脚本（建议使用 Playwright 或 Puppeteer）。
- `server/`：服务端 API 的最小探针脚本（curl 或 PHP）。

## 前置条件

- 站点已部署并可通过浏览器访问（例如 http://localhost/）。
- PHP CLI 可用，命令行下能访问与站点相同的数据库（通过 `db_connect.php`）。

## 快速检查脚本

1) 校验后端状态 API

```bash
curl -s 'http://localhost/time_config.php?action=status' | jq
```

期望：返回 JSON，包含 `mode`、`request.open`、`vote.open`、`limits`、`reset_seq` 等字段。

2) 触发强制刷新（测试用 CLI，不走登录）

```bash
php tests/tools/force_reset_cli.php
```

期望：输出 `{ "success": true, "reset_seq": <N> }`，刷新页面后前端应在下一次状态轮询中清空本地计数。

## 浏览器自动化（建议 Playwright）

以下为 Playwright 的伪步骤（也可用 Puppeteer）：

1. 场景：首次访问不弹“管理员触发”

- 打开新无痕页面，访问首页。
- 截获 toast 文本，断言“不包含”`次数已刷新（管理员触发）`。

2. 场景：关闭->开启触发自动刷新

- 记录本地计数（先手动加 1）。
- 通过后端切换规则或手动开关使状态变为关闭，再变为开启。
- 轮询 `time_config.php?action=status` 直到 `open=true`。
- 断言本地 `requestCount`/`voteCount`/`actionCount` 均为 0，且 `votedSongs` 被清空。

3. 场景：管理员强制刷新

- 在非首次的同一会话中，运行 `php tests/tools/force_reset_cli.php`。
- 前端下一次轮询后，断言本地计数清零，并出现一次“次数已刷新（管理员触发）”的 toast。

> 提示：前端默认会定期刷新状态或在交互后刷新；也可在测试脚本中直接 fetch 状态 API 回写到页面环境以触发流程。

## 断言点清单

- `status.mode` 为 `manual` 或 `auto` 时，`request.open` 与 `vote.open` 符合后台开关或规则计算。
- 关闭->开启后，本地：
  - `localStorage.requestCount === '0'`
  - `localStorage.voteCount === '0'`
  - `localStorage.actionCount === '0'`
  - `localStorage.votedSongs` 为空数组或不存在
- 首次访问：有 `lastResetSeq` 初始化，但无“管理员触发”提示。
- 强制刷新后：出现“管理员触发”提示，且各计数清零。

## 进阶：可扩展测试

- 自动模式：给定规则（类型1/2/3）下，构造时间，验证 `next_open`/`next_close` 计算。
- 合并上限 total 与分别上限的互斥逻辑：切换 combined_limit 的值，检查前端 `combinedMode` 分支行为。
