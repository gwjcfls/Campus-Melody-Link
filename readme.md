# 广播站点歌系统

## 项目简介

本项目是一个校园广播站点歌系统，支持用户在线点歌、留言、管理后台操作，并具备日间/夜间主题切换功能。前端采用 Tailwind CSS 和 Font Awesome，后端使用 PHP，敏感词检测基于高效前缀树算法。

## 目录结构

```
404.html
admin_login.php
admin_logout.php
admin.php
badwords.php
batch_operation.php
build.php
database_setup.sql
db_connect.php
delete_song.php
favicon.ico
florr.php
get_song.php
index.php
mark_played.php
readme.md
submit_request.php
tencent8547067565886776702.txt
test_theme.html
test.php
THEME_README.md
trie_cache.php
update_announcement.php
update_rule.php
update_song.php
css/
    font-awesome.min.css
    tailwind.css
    tailwind.min.css
fonts/
    fontawesome-webfont.eot
    ...
js/
    tailwindcss.js
```

## 主要功能

- 在线点歌与留言
- 管理后台（歌曲管理、公告、规则等）
- 敏感词检测（前缀树缓存，支持中文多字节）
- 日间/夜间主题切换（自动保存用户偏好）
- 响应式设计，适配多种设备

## 快速开始

1. **环境要求**  
   - PHP 7.2+
   - MySQL 数据库
   - 支持 HTTPS 推荐

2. **数据库初始化**  
   导入 [`database_setup.sql`](database_setup.sql) 到你的数据库。

3. **配置数据库连接**  
   编辑 [`db_connect.php`](db_connect.php) 填写数据库信息。

4. **敏感词前缀树生成**  
   运行 [`build.php`](build.php) 自动生成 [`trie_cache.php`](trie_cache.php)。

5. **访问主页面**  
   打开 [`index.php`](index.php) 即可体验点歌功能。

6. **管理后台**  
   访问 [`admin.php`](admin.php)，使用管理员账号登录。

## 主题切换说明

- 主题切换按钮位于页面右上角
- 用户选择会自动保存到浏览器 localStorage
- 支持主页面、后台和测试页面
- 详细说明见 [`THEME_README.md`](THEME_README.md)

## 敏感词检测说明

- 敏感词库见 [`badwords.php`](badwords.php)
- 前缀树缓存见 [`trie_cache.php`](trie_cache.php)
- 检测逻辑见 [`submit_request.php`](submit_request.php)

## 相关页面

- 主页面：[index.php](index.php)
- 管理后台：[admin.php](admin.php)
- 主题测试：[test_theme.html](test_theme.html)

## 许可证

MIT License

---

如需二次开发或功能扩展，请参考 [`THEME_README.md`](THEME_README.md) 及各 PHP 源码