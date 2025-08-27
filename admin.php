<?php
// 会话安全配置（放在session_start()之前）
ini_set('session.cookie_secure', 'On'); // 仅通过HTTPS传输Cookie（需服务器支持HTTPS）
ini_set('session.cookie_httponly', 'On'); // 禁止JS读取Cookie，防止XSS窃取
ini_set('session.cookie_samesite', 'Strict'); // 限制跨站请求携带Cookie，防CSRF
ini_set('session.cookie_lifetime', 0); // 会话Cookie随浏览器关闭失效
ini_set('session.gc_maxlifetime', 3600); // 会话有效期1小时（无操作自动失效）
ini_set('session.regenerate_id', 'On'); // 每次请求刷新Session ID，防止固定攻击

session_start();
// 检查是否已登录
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: index.php");
    exit;
}
// 获取当前管理员角色
$admin_role = $_SESSION['admin_role'] ?? 'admin';
require_once 'db_connect.php';
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=0.5">
    <title>广播站点歌系统 - 管理后台</title>
    <!-- <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="css/font-awesome.min.css"> -->

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">

    <script>
        // 简易提示框容器（用于 showSuccessToast）
        (function ensureToastContainer() {
            const existing = document.getElementById('toast-container');
            if (existing) return;
            const create = () => {
                if (document.getElementById('toast-container')) return;
                const tc = document.createElement('div');
                tc.id = 'toast-container';
                tc.style.position = 'fixed';
                tc.style.right = '20px';
                tc.style.bottom = '20px';
                tc.style.zIndex = 60;
                document.body.appendChild(tc);
            };
            if (document.body) create(); else document.addEventListener('DOMContentLoaded', create);
        })();

        // 显示成功/失败提示（Tailwind 风格），自动消失
        function showSuccessToast(message = '', success = true, timeout = 2500) {
            const container = document.getElementById('toast-container');
            if (!container) return;
            const div = document.createElement('div');
            // Tailwind-like classes (页面可能未包含这些类时也不会报错，因为使用 className)
            div.className = `px-4 py-2 rounded-lg shadow-md mb-2 text-sm transform transition-all duration-300 ${success ? 'bg-green-50 text-green-800 border border-green-100' : 'bg-red-50 text-red-800 border border-red-100'}`;
            div.style.padding = '0.5rem 1rem';
            div.style.marginBottom = '0.5rem';
            div.style.opacity = '1';
            div.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
            div.textContent = message || (success ? '操作成功' : '操作失败');
            container.appendChild(div);
            // 进场动画
            requestAnimationFrame(() => { div.style.transform = 'translateY(0)'; });
            // 出场
            setTimeout(() => { div.style.opacity = '0'; div.style.transform = 'translateY(8px)'; }, timeout - 300);
            setTimeout(() => { try { container.removeChild(div); } catch(e){} }, timeout);
        }

        // 交互式确认 toast（返回 Promise<boolean>）
        function showConfirmToast(message = '确认执行该操作？', {
            confirmText = '确认', cancelText = '取消', tone = 'warning', timeout = 8000
        } = {}) {
            const container = document.getElementById('toast-container');
            if (!container) return Promise.resolve(false);
            return new Promise(resolve => {
                const wrap = document.createElement('div');
                const toneClass = tone === 'danger' ? 'bg-red-50 text-red-800 border-red-200' : (tone === 'success' ? 'bg-green-50 text-green-800 border-green-200' : 'bg-yellow-50 text-yellow-800 border-yellow-200');
                wrap.className = `px-4 py-3 rounded-lg shadow-md mb-2 text-sm border ${toneClass}`;
                wrap.style.display = 'flex';
                wrap.style.alignItems = 'center';
                wrap.style.justifyContent = 'space-between';
                wrap.style.gap = '12px';
                const span = document.createElement('span');
                span.textContent = message;
                const btns = document.createElement('div');
                btns.style.display = 'flex';
                btns.style.gap = '8px';
                const ok = document.createElement('button');
                ok.textContent = confirmText;
                ok.className = 'px-3 py-1 rounded bg-red-500 text-white hover:bg-red-600';
                const cancel = document.createElement('button');
                cancel.textContent = cancelText;
                cancel.className = 'px-3 py-1 rounded bg-gray-200 text-gray-800 hover:bg-gray-300';
                btns.appendChild(cancel); btns.appendChild(ok);
                wrap.appendChild(span); wrap.appendChild(btns);
                container.appendChild(wrap);
                let settled = false;
                const done = (val) => { if (settled) return; settled = true; try { container.removeChild(wrap); } catch(e){} resolve(val); };
                ok.addEventListener('click', () => done(true));
                cancel.addEventListener('click', () => done(false));
                setTimeout(() => done(false), timeout);
            });
        }
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#a78bfa', // 柔和紫
                        secondary: '#f472b6', // 甜美粉
                        accent: '#60a5fa', // 天空蓝
                        light: '#e0f2fe', // 天空浅蓝
                        dark: '#334155', // 深蓝灰
                        danger: '#f87171', // 珊瑚红 (用于错误提示)
                    },
                    fontFamily: {
                        sans: ['Nunito', 'system-ui', 'sans-serif'],
                        display: ['Nunito', 'system-ui', 'sans-serif'],
                    },
                    boxShadow: {
                        'cute': '0 4px 14px 0 rgba(0, 0, 0, 0.05)',
                        'cute-hover': '0 6px 20px 0 rgba(0, 0, 0, 0.08)',
                    }
                }
            }
        }
    </script>
    <style type="text/tailwindcss">
        @layer utilities {
            .card-hover {
                transition: all 0.3s ease-in-out;
            }
            .card-hover:hover {
                transform: translateY(-6px);
                box-shadow: theme('boxShadow.cute-hover');
            }
            .kawaii-pattern {
                background-color: theme('colors.light');
                background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='28' height='28' viewBox='0 0 28 28'%3E%3Cpath fill='%23a78bfa' fill-opacity='0.08' d='M14 0 L15.75 6.25 L22 7 L17 11.25 L18.5 17.5 L14 14 L9.5 17.5 L11 11.25 L6 7 L12.25 6.25 Z'%3E%3C/path%3E%3C/svg%3E");
            }
            .hidden-btn {
                display: none !important; /* 强制隐藏元素 */
            }
        }
    </style>

</head>
<body class="bg-gray-50 font-sans">
    <!-- 导航栏 -->
    <nav class="bg-white shadow-md fixed w-full z-50">
        <div class="container mx-auto px-4 py-3 flex justify-between items-center">
            <div class="flex items-center space-x-2">
                <i class="fa fa-music text-primary text-2xl"></i>
                <h1 class="text-xl font-bold text-dark">广播站点歌系统 - 管理后台</h1>
            </div>
            <div class="flex items-center space-x-4">
                <span class="text-gray-600">欢迎，<?php echo $_SESSION['admin_username'] ?>（<?php echo $admin_role === 'super_admin' ? '超级管理员' : '管理员' ?>）</span>
                <a href="admin_logout.php" class="px-4 py-2 rounded-md bg-red-500 text-white hover:bg-red-600 transition-all">
                    <i class="fa fa-sign-out mr-1"></i>退出登录
                </a>
            </div>
        </div>
    </nav>

    <!-- 主内容区 -->
    <main class="container mx-auto px-4 pt-24 pb-16">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- 左侧菜单 -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-xl shadow-lg p-6 sticky top-24">
                    <h2 class="text-xl font-bold text-dark mb-6 flex items-center">
                        <i class="fa fa-cog text-primary mr-2"></i>管理菜单
                    </h2>
                    <div class="space-y-2">
                        <button class="admin-tab w-full text-left px-4 py-3 rounded-lg bg-primary text-white flex items-center" data-target="song-management">
                            <i class="fa fa-music mr-2"></i>歌曲管理
                        </button>
                        <button class="admin-tab w-full text-left px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100 flex items-center" data-target="announcement-management">
                            <i class="fa fa-bullhorn mr-2"></i>通知管理
                        </button>
                        <button class="admin-tab w-full text-left px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100 flex items-center" data-target="rule-management">
                            <i class="fa fa-book mr-2"></i>点歌规则管理
                        </button>
                        <button class="admin-tab w-full text-left px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100 flex items-center" data-target="time-management">
                            <i class="fa fa-clock-o mr-2"></i>点歌时间
                        </button>
                        <?php if ($admin_role === 'super_admin'): ?>
                        <button class="admin-tab w-full text-left px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100 flex items-center" data-target="log-management">
                            <i class="fa fa-file-text-o mr-2"></i>操作日志
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- 右侧内容 -->
            <div class="lg:col-span-2">
                <!-- 内容容器：按需加载 -->
                <div id="admin-content-container">
                    <!-- 初始会加载歌曲管理片段 -->
                </div>
            </div>
        </div>
    </main>

    <!-- 编辑歌曲模态框（仅超级管理员可见） -->
    <?php if ($admin_role === 'super_admin'): ?>
    <div id="edit-song-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center hidden">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4 transform transition-all">
            <div class="flex justify-between items-center p-4 border-b">
                <h3 class="text-xl font-bold text-dark">编辑歌曲</h3>
                <button id="close-edit-modal" class="text-gray-500 hover:text-gray-700">
                    <i class="fa fa-times text-xl"></i>
                </button>
            </div>
            <div class="p-6">
                <form id="edit-song-form" method="POST" action="update_song.php">
                    <input type="hidden" id="edit-song-id" name="song_id">
                    <div class="space-y-4">
                        <div>
                            <label for="edit-song-name" class="block text-sm font-medium text-gray-700 mb-1">歌曲名称</label>
                            <input type="text" id="edit-song-name" name="song_name" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary transition-all" required>
                        </div>
                        <div>
                            <label for="edit-artist" class="block text-sm font-medium text-gray-700 mb-1">歌手</label>
                            <input type="text" id="edit-artist" name="artist" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary transition-all" required>
                        </div>
                        <div>
                            <label for="edit-requestor" class="block text-sm font-medium text-gray-700 mb-1">点歌人</label>
                            <input type="text" id="edit-requestor" name="requestor" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary transition-all" required>
                        </div>
                        <div>
                            <label for="edit-class" class="block text-sm font-medium text-gray-700 mb-1">班级</label>
                            <input type="text" id="edit-class" name="class" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary transition-all" required>
                        </div>
                        <div>
                            <label for="edit-message" class="block text-sm font-medium text-gray-700 mb-1">留言</label>
                            <textarea id="edit-message" name="message" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary transition-all"></textarea>
                        </div>
                        <div>
                            <label for="edit-votes" class="block text-sm font-medium text-gray-700 mb-1">投票数</label>
                            <input type="number" id="edit-votes" name="votes" min="0" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary transition-all" required>
                        </div>
                        <div class="pt-2">
                            <button type="submit" class="w-full py-3 bg-primary text-white rounded-md hover:bg-primary/90 transition-all flex items-center justify-center">
                                <i class="fa fa-save mr-2"></i>保存修改
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
        // 管理菜单切换（按需加载）
        const adminTabs = document.querySelectorAll('.admin-tab');
        const contentContainer = document.getElementById('admin-content-container');
        let currentSection = null;
        let ongoingFetch = null;

        // 编辑歌曲模态与选择计数相关工具（防止未定义错误）
        const editSongModal = document.getElementById('edit-song-modal');
        const closeEditModalBtn = document.getElementById('close-edit-modal');
        if (closeEditModalBtn && editSongModal) {
            closeEditModalBtn.addEventListener('click', () => editSongModal.classList.add('hidden'));
            // 点击模态外部也关闭
            editSongModal.addEventListener('click', (e) => { if (e.target === editSongModal) editSongModal.classList.add('hidden'); });
        }

        // 更新已选择歌曲计数并同步全选状态
        function updateSelectedCount() {
            const count = document.querySelectorAll('.song-checkbox:checked').length;
            const el = document.getElementById('selected-count');
            if (el) el.textContent = `已选择 ${count} 首歌曲`;
            const selectAll = document.getElementById('select-all');
            const allCheckboxes = document.querySelectorAll('.song-checkbox');
            if (selectAll) {
                selectAll.checked = allCheckboxes.length > 0 && count === allCheckboxes.length;
            }
        }

        function setActiveTab(target) {
            adminTabs.forEach(t => {
                t.classList.remove('bg-primary', 'text-white');
                t.classList.add('text-gray-700', 'hover:bg-gray-100');
            });
            const tab = Array.from(adminTabs).find(t => t.getAttribute('data-target') === target);
            if (tab) {
                tab.classList.remove('text-gray-700', 'hover:bg-gray-100');
                tab.classList.add('bg-primary', 'text-white');
            }
        }

        async function loadSection(section, force = false) {
            if (currentSection === section && !force) return;
            if (ongoingFetch && typeof ongoingFetch.abort === 'function') ongoingFetch.abort();
            // 保存当前页面滚动位置，防止刷新后跳到页面顶部
            const savedScrollY = window.scrollY || window.pageYOffset || 0;
            // 清空内容前记录当前 section，并清空内容
            contentContainer.innerHTML = '';
            currentSection = section;
            setActiveTab(section);
            const controller = new AbortController();
            ongoingFetch = controller;
            try {
                const res = await fetch(`admin_fragment.php?section=${encodeURIComponent(section)}`, { signal: controller.signal });
                if (!res.ok) throw new Error('加载失败');
                const html = await res.text();
                contentContainer.innerHTML = html;
                initSectionBindings(section);
                // 尝试恢复之前的滚动位置（若页面内元素未改变太多）
                try { window.scrollTo({ top: savedScrollY, behavior: 'auto' }); } catch (e) { /* ignore */ }
            } catch (err) {
                if (err.name === 'AbortError') return;
                console.error('加载片段出错:', err);
                contentContainer.innerHTML = `<div class="bg-white rounded-xl shadow-lg p-6 mb-8 text-red-500">加载失败，请重试</div>`;
            } finally {
                if (ongoingFetch === controller) ongoingFetch = null;
            }
        }

        adminTabs.forEach(tab => {
            tab.addEventListener('click', () => loadSection(tab.getAttribute('data-target')));
        });

        // 初始加载默认面板
        loadSection('song-management');

            // 通知和规则表单提交（在绑定函数中处理）

            // 搜索和过滤功能
            // 绑定并初始化加载后面板的事件
            function initSectionBindings(section) {
                // 搜索与过滤（歌曲管理）
                if (section === 'song-management') {
                    const songSearch = document.getElementById('song-search');
                    const songFilter = document.getElementById('song-filter');
                    const songTableBody = document.getElementById('song-table-body');

                    // 绑定全选与复选、计数
                    document.getElementById('select-all')?.addEventListener('change', function() {
                        const isChecked = this.checked;
                        document.querySelectorAll('.song-checkbox').forEach(cb => cb.checked = isChecked);
                        updateSelectedCount();
                    });
                    document.querySelectorAll('.song-checkbox').forEach(cb => cb.addEventListener('change', updateSelectedCount));

                    // 过滤发送到后端更准确（避免在前端保存全部数据）
                    async function fetchFiltered() {
                        const q = songSearch?.value || '';
                        const filter = songFilter?.value || 'all';
                        const params = new URLSearchParams({ q, filter });
                        try {
                            const res = await fetch('get_song.php?'+params.toString());
                            const data = await res.json();
                            if (!data.success) {
                                songTableBody.innerHTML = '<tr><td colspan="6" class="px-6 py-12 text-center"><div class="text-gray-500">加载失败</div></td></tr>';
                                return;
                            }
                            // 渲染表格行与卡片列表（移动端）
                            songTableBody.innerHTML = '';
                            const cardList = document.getElementById('song-card-list');
                            if (cardList) cardList.innerHTML = '';
                            if (!data.songs || data.songs.length === 0) {
                                songTableBody.innerHTML = '<tr><td colspan="6" class="px-6 py-12 text-center"><div class="text-gray-500">没有找到匹配的歌曲</div></td></tr>';
                                return;
                            }
                            data.songs.forEach(song => {
                                // 表格行（桌面）
                                const tr = document.createElement('tr');
                                tr.className = 'hover:bg-gray-50 transition-all';
                                tr.innerHTML = `\
                                    <td class="px-3 py-2 whitespace-normal break-words">\
                                        <input type=\"checkbox\" class=\"song-checkbox w-4 h-4 text-primary rounded border-gray-300 focus:ring-primary\" data-id=\"${song.id}\">\
                                    </td>\
                                    <td class="px-3 py-2 whitespace-normal break-words">\
                                        <div class=\"font-medium text-gray-900\">${escapeHtml(song.song_name)}</div>\
                                        <div class=\"text-sm text-gray-500\">${escapeHtml(song.artist)}</div>\
                                    </td>\
                                    <td class="px-3 py-2 whitespace-normal break-words">\
                                        <div class=\"text-sm text-gray-900\">${escapeHtml(song.requestor)}</div>\
                                    </td>\
                                    <td class="px-3 py-2 whitespace-normal break-words">\
                                        <div class=\"text-sm text-gray-900\">${escapeHtml(song.class)}</div>\
                                    </td>\
                                    <td class=\"px-3 py-2 whitespace-normal break-words\">\
                                        <div class=\"text-xs text-gray-500 song-time\" title=\"提交时间\">${escapeHtml(song.created_at || '')}</div>\
                                    </td>\
                                    <td class="px-3 py-2 whitespace-normal break-words">\
                                        <div class=\"text-sm text-gray-900 flex items-center\">\
                                            <i class=\"fa fa-thumbs-up text-primary mr-1\"></i>\
                                            <span>${song.votes}</span>\
                                        </div>\
                                    </td>\
                                    <td class=\"px-3 py-2 whitespace-normal break-words\">\
                                        <div class=\"text-sm text-gray-900 song-message max-w-xs truncate\" title=\"${escapeHtml(song.message || '')}\">${escapeHtml(song.message || '')}</div>\
                                    </td>\
                                    <td class="px-3 py-2 whitespace-normal break-words">\
                                        ${Number(song.played) ? '<span class=\"px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800\">已播放</span>' : '<span class=\"px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800\">待播放</span>'}\
                                    </td>\
                                    <td class="px-3 py-2 whitespace-normal break-words text-sm text-gray-500">\
                                        <div class=\"flex flex-wrap items-center gap-2\">\
                                            ${<?php echo $admin_role === 'super_admin' ? 'true' : 'false'; ?> ? `<button class=\"edit-song p-1.5 rounded bg-blue-100 text-blue-600 hover:bg-blue-200 transition-all\" data-id=\"${song.id}\"><i class=\"fa fa-pencil\"></i></button><button class=\"delete-song p-1.5 rounded bg-red-100 text-red-600 hover:bg-red-200 transition-all\" data-id=\"${song.id}\"><i class=\"fa fa-trash\"></i></button>` : ''} \                                         
                                            ${Number(song.played) ? `<button class=\"mark-unplayed p-1.5 rounded bg-yellow-100 text-yellow-600 hover:bg-yellow-200 transition-all\" data-id=\"${song.id}\"><i class=\"fa fa-undo\"></i></button>` : `<button class=\"mark-played p-1.5 rounded bg-green-100 text-green-600 hover:bg-green-200 transition-all\" data-id=\"${song.id}\"><i class=\"fa fa-check\"></i></button>`} \                                       
                                        </div>\
                                    </td>`;
                                songTableBody.appendChild(tr);

                                // 卡片项（移动）
                                if (cardList) {
                                    const card = document.createElement('div');
                                    card.className = 'bg-white rounded-lg shadow p-4';
                                    card.innerHTML = `\
                                        <div class=\"flex items-start space-x-3\">\
                                            <div class=\"flex-shrink-0\">\
                                                <input type=\"checkbox\" class=\"song-checkbox w-4 h-4 text-primary rounded border-gray-300 focus:ring-primary\" data-id=\"${song.id}\">\
                                            </div>\
                                            <div class=\"flex-1 min-w-0\">\
                                                <div class=\"text-base font-medium text-gray-900 mb-1 max-h-16 overflow-y-auto\">${escapeHtml(song.song_name)}</div>\
                                                <div class=\"text-sm text-gray-500 max-h-12 overflow-y-auto\">歌手：${escapeHtml(song.artist)}</div>\
                                                <div class=\"text-sm text-gray-700 mt-2 grid grid-cols-2 gap-2\">\
                                                    <div class=\"text-sm text-gray-900 max-h-12 overflow-y-auto\">点歌人：${escapeHtml(song.requestor)}</div>\
                                                    <div class=\"text-sm text-gray-900 max-h-12 overflow-y-auto\">班级：${escapeHtml(song.class)}</div>\
                                                </div>\
                                                <div class=\"text-xs text-gray-500 mt-2 song-time\">时间：${escapeHtml(song.created_at || '')}</div>\
                                                <div class=\"text-sm text-gray-900 mt-1 song-message\">留言：${escapeHtml(song.message || '')}</div>\
                                            </div>\
                                        </div>\
                                        <div class=\"mt-3 flex items-center justify-between\">\
                                            <div class=\"flex items-center space-x-3\">\
                                                <div class=\"text-sm text-gray-700 flex items-center\">\
                                                    <i class=\"fa fa-thumbs-up text-primary mr-1\"></i>\
                                                    <span>${song.votes}</span>\
                                                </div>\
                                                <div class=\"text-sm\">\
                                                    ${Number(song.played) ? '<span class=\"px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800\">已播放</span>' : '<span class=\"px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800\">待播放</span>'}\
                                                </div>\
                                            </div>\
                                            <div class=\"flex items-center space-x-2\">\
                                                ${<?php echo $admin_role === 'super_admin' ? 'true' : 'false'; ?> ? `<button class=\"edit-song p-1.5 rounded bg-blue-100 text-blue-600 hover:bg-blue-200 transition-all\" data-id=\"${song.id}\"><i class=\"fa fa-pencil\"></i></button><button class=\"delete-song p-1.5 rounded bg-red-100 text-red-600 hover:bg-red-200 transition-all\" data-id=\"${song.id}\"><i class=\"fa fa-trash\"></i></button>` : ''} \                                                
                                                ${Number(song.played) ? `<button class=\"mark-unplayed p-1.5 rounded bg-yellow-100 text-yellow-600 hover:bg-yellow-200 transition-all\" data-id=\"${song.id}\"><i class=\"fa fa-undo\"></i></button>` : `<button class=\"mark-played p-1.5 rounded bg-green-100 text-green-600 hover:bg-green-200 transition-all\" data-id=\"${song.id}\"><i class=\"fa fa-check\"></i></button>`} \                                          
                                            </div>\
                                        </div>`;
                                    cardList.appendChild(card);
                                }
                            });
                            // 重新绑定事件到新元素
                            bindSongEvents();
                        } catch (err) {
                            console.error('获取歌曲失败', err);
                        }
                    }

                    songSearch?.addEventListener('input', debounce(fetchFiltered, 250));
                    songFilter?.addEventListener('change', fetchFiltered);
                    // 首次渲染
                    fetchFiltered();
                }

                // 通知与规则表单提交（提交后使用 toast 提示，不再刷新面板）
                if (section === 'announcement-management') {
                    document.getElementById('announcement-form')?.addEventListener('submit', function(e) {
                        e.preventDefault();
                        const formData = new FormData(this);
                        fetch('update_announcement.php', { method: 'POST', body: formData })
                            .then(response => response.json())
                            .then(data => { showSuccessToast(data.message || (data.success ? '通知已保存' : '保存失败'), data.success); });
                    });
                }
                if (section === 'rule-management') {
                    document.getElementById('rule-form')?.addEventListener('submit', function(e) {
                        e.preventDefault();
                        const formData = new FormData(this);
                        fetch('update_rule.php', { method: 'POST', body: formData })
                            .then(response => response.json())
                            .then(data => { showSuccessToast(data.message || (data.success ? '规则已保存' : '保存失败'), data.success); });
                    });
                }
                // 时间管理：片段通过 innerHTML 注入，内联 <script> 不会自动执行，这里主动绑定事件
                if (section === 'time-management') {
                    const settingsForm = document.getElementById('time-settings-form');
                    if (settingsForm) {
                        settingsForm.addEventListener('submit', function(e) {
                            e.preventDefault();
                            const fd = new FormData(this);
                            // 未勾选的 checkbox 不会提交，确保有值
                            if (!fd.has('manual_request_enabled')) fd.set('manual_request_enabled', '0');
                            if (!fd.has('manual_vote_enabled')) fd.set('manual_vote_enabled', '0');
                            fd.set('action','update_settings');
                            fetch('time_config.php', { method: 'POST', body: fd })
                                .then(r => r.json())
                                .then(d => { if (d.success) showSuccessToast('设置已保存'); else showSuccessToast(d.message||'保存失败', false); })
                                .catch(() => showSuccessToast('网络错误', false));
                        });
                    }

                    // 绑定“强制刷新用户本地次数”按钮
                    const forceBtn = document.getElementById('force-reset-btn');
                    if (forceBtn) {
                        forceBtn.addEventListener('click', async function() {
                            const btn = this;
                            const ok = await showConfirmToast('将强制所有在线/离线用户在下一次状态刷新时清空本地“已点赞”和次数计数，确定执行？', { confirmText: '执行', cancelText: '取消', tone: 'warning' });
                            if (!ok) return;
                            btn.disabled = true; btn.classList.add('opacity-70', 'cursor-not-allowed');
                            try {
                                const fd = new FormData();
                                fd.set('action','force_reset');
                                const res = await fetch('time_config.php', { method: 'POST', body: fd });
                                const d = await res.json();
                                if (d && d.success) {
                                    const seq = d.data?.reset_seq;
                                    showSuccessToast(seq !== undefined ? `已触发强制刷新（序列号=${seq}）` : '已触发强制刷新');
                                } else {
                                    showSuccessToast((d && d.message) ? d.message : '执行失败', false);
                                }
                            } catch (e) {
                                showSuccessToast('网络错误', false);
                            } finally {
                                btn.disabled = false; btn.classList.remove('opacity-70', 'cursor-not-allowed');
                            }
                        });
                    }

                    const addRuleForm = document.getElementById('add-rule-form');
                    if (addRuleForm) {
                        addRuleForm.addEventListener('submit', function(e) {
                            e.preventDefault();
                            const fd = new FormData(this);
                            fd.set('action','add_rule');
                            fetch('time_config.php', { method: 'POST', body: fd })
                                .then(r => r.json())
                                .then(d => {
                                    if (d.success) {
                                        showSuccessToast('已新增规则');
                                        try {
                                            const id = d.data?.id || d.id || null;
                                            const tbody = document.getElementById('rule-table-body');
                                            if (id && tbody) {
                                                const tr = document.createElement('tr');
                                                const feature = fd.get('feature') || 'both';
                                                const type = fd.get('type') || '1';
                                                const sw = fd.get('start_weekday') || '';
                                                const ew = fd.get('end_weekday') || '';
                                                const st = fd.get('start_time') || '';
                                                const et = fd.get('end_time') || '';
                                                const dsw = (type === '1') ? '/' : (sw || '/');
                                                const dew = (type === '1') ? '/' : (ew || '/');
                                                const dst = (type === '3') ? '/' : (st || '/');
                                                const det = (type === '3') ? '/' : (et || '/');
                                                const desc = (fd.get('description') || '').toString();
                                                tr.innerHTML = `
                                                    <td class="px-2 py-2">${id}</td>
                                                    <td class="px-2 py-2">${escapeHtml(feature)}</td>
                                                    <td class="px-2 py-2">${escapeHtml(type)}</td>
                                                    <td class="px-2 py-2">${escapeHtml(String(dsw))}</td>
                                                    <td class="px-2 py-2">${escapeHtml(String(dew))}</td>
                                                    <td class="px-2 py-2">${escapeHtml(String(dst))}</td>
                                                    <td class="px-2 py-2">${escapeHtml(String(det))}</td>
                                                    <td class="px-2 py-2"><span class="text-green-600">启用</span></td>
                                                    <td class="px-2 py-2 max-w-xs truncate" title="${escapeHtml(desc)}">${escapeHtml(desc)}</td>
                                                    <td class="px-2 py-2 whitespace-nowrap">
                                                        <div class="flex items-center gap-2 flex-nowrap">
                                                            <button class="edit-rule px-2 py-1 text-xs rounded bg-purple-50 text-purple-600" data-id="${id}" data-feature="${escapeHtml(feature)}" data-type="${escapeHtml(type)}" data-sw="${escapeHtml(sw)}" data-ew="${escapeHtml(ew)}" data-st="${escapeHtml(st)}" data-et="${escapeHtml(et)}" data-desc="${escapeHtml(desc)}">编辑</button>
                                                            <button class="toggle-rule px-2 py-1 text-xs rounded bg-blue-50 text-blue-600" data-id="${id}" data-active="0">停用</button>
                                                            <button class="delete-rule px-2 py-1 text-xs rounded bg-red-50 text-red-600" data-id="${id}">删除</button>
                                                        </div>
                                                    </td>`;
                                                tbody.appendChild(tr);
                                                // 绑定新按钮事件
                                                const editBtn = tr.querySelector('.edit-rule');
                                                const toggleBtn = tr.querySelector('.toggle-rule');
                                                const deleteBtn = tr.querySelector('.delete-rule');
                                                if (editBtn) bindEditRule(editBtn);
                                                if (toggleBtn) bindToggleRule(toggleBtn);
                                                if (deleteBtn) bindDeleteRule(deleteBtn);
                                                // 重置表单
                                                try { (this instanceof HTMLFormElement) && this.reset(); } catch(e){}
                                                // 重置后刷新一次 UI（根据默认类型）
                                                try { if (typeof applyTypeDrivenUI === 'function') applyTypeDrivenUI(addRuleForm); } catch(e){}
                                            }
                                        } catch(e) { /* ignore */ }
                                    } else { showSuccessToast(d.message||'操作失败', false); }
                                })
                                .catch(() => showSuccessToast('网络错误', false));
                        });
                    }

                    // 表单动态显隐/必填：根据类型控制 周几 与 时间 字段
                    const applyTypeDrivenUI = (form) => {
                        if (!form) return;
                        const typeSel = form.querySelector('[name="type"]');
                        if (!typeSel) return;
                        const t = String(typeSel.value || '1');
                        const sw = form.querySelector('[name="start_weekday"]');
                        const ew = form.querySelector('[name="end_weekday"]');
                        const st = form.querySelector('[name="start_time"]');
                        const et = form.querySelector('[name="end_time"]');
                        const swWrap = sw ? sw.closest('div') : null;
                        const ewWrap = ew ? ew.closest('div') : null;
                        const stWrap = st ? st.closest('div') : null;
                        const etWrap = et ? et.closest('div') : null;

                        const showWeekdays = (t === '2' || t === '3');
                        const showTimes = (t === '1' || t === '2');

                        const setWrap = (wrap, show) => {
                            if (!wrap) return;
                            wrap.style.display = show ? '' : 'none';
                        };
                        const setField = (el, enabled, required) => {
                            if (!el) return;
                            el.disabled = !enabled;
                            if (required) el.setAttribute('required', 'required'); else el.removeAttribute('required');
                            if (!enabled) {
                                // 禁用时用 '/' 占位，避免视觉空白
                                el.value = '/';
                            } else {
                                // 重新启用时清理占位符
                                if (el.value === '/') el.value = '';
                            }
                        };

                        setWrap(swWrap, showWeekdays);
                        setWrap(ewWrap, showWeekdays);
                        setWrap(stWrap, showTimes);
                        setWrap(etWrap, showTimes);

                        // 必填策略：
                        // - 类型1（每日固定时间）：仅时间必填
                        // - 类型2（每周跨天）：周几与时间均必填
                        // - 类型3（每周周几范围）：仅周几必填，时间不需要
                        if (t === '1') {
                            setField(sw, false, false);
                            setField(ew, false, false);
                            setField(st, true, true);
                            setField(et, true, true);
                        } else if (t === '2') {
                            setField(sw, true, true);
                            setField(ew, true, true);
                            setField(st, true, true);
                            setField(et, true, true);
                        } else { // '3'
                            setField(sw, true, true);
                            setField(ew, true, true);
                            setField(st, false, false);
                            setField(et, false, false);
                        }
                    };

                    // 监听 add 表单类型切换
                    if (addRuleForm) {
                        const typeSel = addRuleForm.querySelector('[name="type"]');
                        if (typeSel) {
                            typeSel.addEventListener('change', () => applyTypeDrivenUI(addRuleForm));
                        }
                        // 初始应用一次
                        applyTypeDrivenUI(addRuleForm);
                    }

                    const bindToggleRule = (btn) => {
                        btn.addEventListener('click', () => {
                            const fd = new FormData();
                            fd.set('action','toggle_rule');
                            const id = btn.getAttribute('data-id');
                            const nextActive = parseInt(btn.getAttribute('data-active') || '0', 10) || 0; // 0/1
                            fd.set('id', id);
                            fd.set('active', String(nextActive));
                            fetch('time_config.php', { method: 'POST', body: fd })
                                .then(r => r.json())
                                .then(d => {
                                    if (d.success) {
                                        const tr = btn.closest('tr');
                                        if (tr) {
                                            const statusTd = tr.querySelector('td:nth-child(8)');
                                            if (statusTd) statusTd.innerHTML = nextActive ? '<span class="text-green-600">启用</span>' : '<span class="text-gray-400">停用</span>';
                                            btn.textContent = nextActive ? '停用' : '启用';
                                            btn.setAttribute('data-active', nextActive ? '0' : '1');
                                        }
                                        showSuccessToast('已更新');
                                    } else showSuccessToast(d.message||'失败', false);
                                })
                                .catch(() => showSuccessToast('网络错误', false));
                        });
                    };
                    const bindDeleteRule = (btn) => {
                        btn.addEventListener('click', async () => {
                            const ok = await showConfirmToast('确定删除该规则？此操作不可恢复。', { confirmText: '删除', cancelText: '取消', tone: 'danger' });
                            if (!ok) return;
                            const fd = new FormData();
                            fd.set('action','delete_rule');
                            fd.set('id', btn.getAttribute('data-id'));
                            fetch('time_config.php', { method: 'POST', body: fd })
                                .then(r => r.json())
                                .then(d => { if (d.success) { const tr = btn.closest('tr'); if (tr) tr.remove(); showSuccessToast('规则已删除'); } else showSuccessToast(d.message||'失败', false); })
                                .catch(() => showSuccessToast('网络错误', false));
                        });
                    };
                    // 绑定现有的启用/删除按钮（初次加载）
                    document.querySelectorAll('.toggle-rule').forEach(btn => bindToggleRule(btn));
                    document.querySelectorAll('.delete-rule').forEach(btn => bindDeleteRule(btn));
                    // 规则编辑弹窗（惰性创建一次）

                        // 编辑规则弹窗（创建一次，挂 body）
                        let ruleModal = document.getElementById('rule-edit-overlay');
                        if (!ruleModal) {
                            ruleModal = document.createElement('div');
                            ruleModal.id = 'rule-edit-overlay';
                            ruleModal.className = 'fixed inset-0 bg-black bg-opacity-40 z-50 hidden grid place-items-center p-4';
                            ruleModal.innerHTML = `
                                <div class="bg-white rounded-lg shadow-xl w-full max-w-lg max-h-[90vh] overflow-auto">
                                    <div class="flex justify-between items-center p-4 border-b"><h3 class="font-bold">编辑规则</h3><button id="close-rule-modal" class="text-gray-500 hover:text-gray-700">×</button></div>
                                    <div class="p-4">
                                        <form id="edit-rule-form" class="space-y-3">
                                            <input type="hidden" name="id" id="er-id" />
                                            <div>
                                                <label class="block text-xs mb-1">功能</label>
                                                <select name="feature" id="er-feature" class="px-2 py-2 border rounded-md w-full">
                                                    <option value="both">点歌+投票</option>
                                                    <option value="request">仅点歌</option>
                                                    <option value="vote">仅投票</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="block text-xs mb-1">类型</label>
                                                <select name="type" id="er-type" class="px-2 py-2 border rounded-md w-full">
                                                    <option value="1">每日固定时间</option>
                                                    <option value="2">每周跨天</option>
                                                    <option value="3">每周周几范围</option>
                                                </select>
                                            </div>
                                            <div class="grid grid-cols-2 gap-2">
                                                <div>
                                                    <label class="block text-xs mb-1">起始周(1-7)</label>
                                                    <input type="number" min="1" max="7" name="start_weekday" id="er-sw" class="px-2 py-2 border rounded-md w-full" />
                                                </div>
                                                <div>
                                                    <label class="block text-xs mb-1">结束周(1-7)</label>
                                                    <input type="number" min="1" max="7" name="end_weekday" id="er-ew" class="px-2 py-2 border rounded-md w-full" />
                                                </div>
                                            </div>
                                            <div class="grid grid-cols-2 gap-2">
                                                <div>
                                                    <label class="block text-xs mb-1">开始时间</label>
                                                    <input type="time" name="start_time" id="er-st" class="px-2 py-2 border rounded-md w-full" />
                                                </div>
                                                <div>
                                                    <label class="block text-xs mb-1">结束时间</label>
                                                    <input type="time" name="end_time" id="er-et" class="px-2 py-2 border rounded-md w-full" />
                                                </div>
                                            </div>
                                            <div>
                                                <label class="block text-xs mb-1">备注</label>
                                                <input type="text" name="description" id="er-desc" class="px-2 py-2 border rounded-md w-full" />
                                            </div>
                                            <div class="pt-2 flex justify-end gap-2">
                                                <button type="button" id="er-cancel" class="px-4 py-2 rounded bg-gray-100">取消</button>
                                                <button type="submit" class="px-4 py-2 rounded bg-primary text-white">保存</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>`;
                            document.body.appendChild(ruleModal);
                        }
                        const closeRuleModal = () => { ruleModal.classList.add('hidden'); ruleModal.classList.remove('grid'); };
                        const openRuleModalFromBtn = (btn) => {
                            ruleModal.classList.remove('hidden');
                            ruleModal.classList.add('grid');
                            document.getElementById('er-id').value = btn.getAttribute('data-id');
                            document.getElementById('er-feature').value = btn.getAttribute('data-feature') || 'both';
                            document.getElementById('er-type').value = btn.getAttribute('data-type') || '1';
                            document.getElementById('er-sw').value = btn.getAttribute('data-sw') || '';
                            document.getElementById('er-ew').value = btn.getAttribute('data-ew') || '';
                            document.getElementById('er-st').value = (btn.getAttribute('data-st') || '').slice(0,5);
                            document.getElementById('er-et').value = (btn.getAttribute('data-et') || '').slice(0,5);
                            document.getElementById('er-desc').value = btn.getAttribute('data-desc') || '';
                            // 打开时根据类型刷新 UI
                            try { const form = document.getElementById('edit-rule-form'); applyTypeDrivenUI(form); } catch(e){}
                        };
                        // 背景和按钮关闭
                        ruleModal.addEventListener('click', (e) => { if (e.target === ruleModal) closeRuleModal(); });
                        ruleModal.querySelector('#close-rule-modal')?.addEventListener('click', closeRuleModal);
                        ruleModal.querySelector('#er-cancel')?.addEventListener('click', closeRuleModal);
                        document.addEventListener('keydown', (e) => { if (!ruleModal.classList.contains('hidden') && e.key === 'Escape') closeRuleModal(); });

                        // 绑定编辑按钮
                        const bindEditRule = (btn) => { btn.addEventListener('click', () => openRuleModalFromBtn(btn)); };
                        document.querySelectorAll('.edit-rule').forEach(btn => bindEditRule(btn));

                        // 编辑表单类型切换监听与初始 UI
                        (function(){
                            const form = document.getElementById('edit-rule-form');
                            if (form) {
                                const typeSel = form.querySelector('[name="type"]');
                                if (typeSel) typeSel.addEventListener('change', () => applyTypeDrivenUI(form));
                                // 初始不显示，等打开时也会应用；这里做一次兜底
                                applyTypeDrivenUI(form);
                            }
                        })();

                        // 提交编辑
                        const editForm = document.getElementById('edit-rule-form');
                        if (editForm) {
                            editForm.addEventListener('submit', function(e) {
                                e.preventDefault();
                                const fd = new FormData(this);
                                fd.set('action','update_rule');
                                fetch('time_config.php', { method: 'POST', body: fd })
                                    .then(r => r.json())
                                    .then(d => {
                                        if (!d.success) { showSuccessToast(d.message||'保存失败', false); return; }
                                        const rule = d.data?.rule || d.rule || null;
                                        if (rule) {
                                            const id = String(rule.id);
                                            const tr = document.querySelector(`#rule-table-body tr td button.edit-rule[data-id="${id}"]`)?.closest('tr');
                                            if (tr) {
                                                tr.querySelector('td:nth-child(2)').textContent = rule.feature;
                                                tr.querySelector('td:nth-child(3)').textContent = String(rule.type);
                                                const t = String(rule.type);
                                                const rsw = (t === '1') ? '/' : (rule.start_weekday || '/');
                                                const rew = (t === '1') ? '/' : (rule.end_weekday || '/');
                                                const rst = (t === '3') ? '/' : (rule.start_time || '/');
                                                const ret = (t === '3') ? '/' : (rule.end_time || '/');
                                                tr.querySelector('td:nth-child(4)').textContent = String(rsw);
                                                tr.querySelector('td:nth-child(5)').textContent = String(rew);
                                                tr.querySelector('td:nth-child(6)').textContent = String(rst);
                                                tr.querySelector('td:nth-child(7)').textContent = String(ret);
                                                const descTd = tr.querySelector('td:nth-child(9)');
                                                if (descTd) { descTd.textContent = rule.description || ''; descTd.title = rule.description || ''; }
                                                const editBtn = tr.querySelector('button.edit-rule');
                                                if (editBtn) {
                                                    editBtn.setAttribute('data-feature', rule.feature);
                                                    editBtn.setAttribute('data-type', String(rule.type));
                                                    editBtn.setAttribute('data-sw', rule.start_weekday ?? '');
                                                    editBtn.setAttribute('data-ew', rule.end_weekday ?? '');
                                                    editBtn.setAttribute('data-st', rule.start_time ?? '');
                                                    editBtn.setAttribute('data-et', rule.end_time ?? '');
                                                    editBtn.setAttribute('data-desc', rule.description || '');
                                                }
                                            }
                                        }
                                        showSuccessToast('已保存');
                                        closeRuleModal();
                                    })
                                    .catch(() => showSuccessToast('网络错误', false));
                            });
                        }
                    // 去重：toggle/delete 已在上方绑定，编辑按钮也已绑定
                }

                // 日志表：绑定“展开/收起详情”
                if (section === 'log-management') {
            document.querySelectorAll('.toggle-log-details').forEach((btn) => {
                        btn.addEventListener('click', () => {
                            const tr = btn.closest('tr');
                            if (!tr) return;
                            const detailsRow = tr.nextElementSibling;
                            if (!detailsRow || !detailsRow.classList.contains('log-details-row')) return;
                            const hidden = detailsRow.classList.toggle('hidden');
                            const expanded = !hidden;
                btn.textContent = expanded ? '收起' : '展开';
                            btn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
                btn.setAttribute('aria-label', expanded ? '收起' : '展开');
                        });
                    });
                }
            }

            // 绑定歌曲相关操作（编辑/删除/标记/批量）
            function bindSongEvents() {
                // 编辑（弹窗）
                document.querySelectorAll('.edit-song').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const songId = this.getAttribute('data-id');
                        fetch(`get_song.php?id=${songId}`)
                            .then(r => r.json())
                            .then(data => {
                                if (data.success) {
                                    document.getElementById('edit-song-id').value = data.song.id;
                                    document.getElementById('edit-song-name').value = data.song.song_name;
                                    document.getElementById('edit-artist').value = data.song.artist;
                                    document.getElementById('edit-requestor').value = data.song.requestor;
                                    document.getElementById('edit-class').value = data.song.class;
                                    document.getElementById('edit-message').value = data.song.message;
                                    document.getElementById('edit-votes').value = data.song.votes;
                                    editSongModal.classList.remove('hidden');
                                }
                            });
                    });
                });

                // 删除
                document.querySelectorAll('.delete-song').forEach(btn => {
                    btn.addEventListener('click', async function() {
                        const songId = this.getAttribute('data-id');
                        const ok = await showConfirmToast('要删除这首歌曲吗？删除后不可恢复。', { confirmText: '删除', cancelText: '取消', tone: 'danger' });
                        if (!ok) return;
                        fetch('delete_song.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `song_id=${encodeURIComponent(songId)}` })
                            .then(r => r.json())
                            .then(data => {
                                if (data.success) {
                                    removeSongElements(songId);
                                    updateSelectedCount();
                                    showSuccessToast(data.message || '删除成功，已从列表移除');
                                } else {
                                    showSuccessToast(data.message || '删除失败，请稍后重试', false);
                                }
                            })
                            .catch(() => showSuccessToast('网络错误', false));
                    });
                });

                // 标记播放/未播放
                document.querySelectorAll('.mark-played').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const songId = this.getAttribute('data-id');
                        fetch('mark_played.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `song_id=${encodeURIComponent(songId)}&played=1` })
                            .then(r => r.json())
                            .then(data => {
                                if (data.success) {
                                    applySongPlayedUI(songId, true);
                                    showSuccessToast(data.message || '已标记为已播放');
                                } else {
                                    showSuccessToast(data.message || '操作失败', false);
                                }
                            });
                    });
                });
                document.querySelectorAll('.mark-unplayed').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const songId = this.getAttribute('data-id');
                        fetch('mark_played.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `song_id=${encodeURIComponent(songId)}&played=0` })
                            .then(r => r.json())
                            .then(data => {
                                if (data.success) {
                                    applySongPlayedUI(songId, false);
                                    showSuccessToast(data.message || '已标记为待播放');
                                } else {
                                    showSuccessToast(data.message || '操作失败', false);
                                }
                            });
                    });
                });

                // 批量操作
                document.getElementById('execute-batch')?.addEventListener('click', function() {
                    const selectedIds = Array.from(document.querySelectorAll('.song-checkbox:checked')).map(cb => cb.getAttribute('data-id'));
                    if (selectedIds.length === 0) { showSuccessToast('请至少选择一首歌曲', false); return; }
                    const action = document.getElementById('batch-action').value;
                    const doRun = async () => {
                        if (action === 'delete') {
                            const ok = await showConfirmToast(`将删除 ${selectedIds.length} 首歌曲，且不可恢复。继续吗？`, { confirmText: '删除', cancelText: '取消', tone: 'danger' });
                            if (!ok) return;
                        }
                        fetch('batch_operation.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `action=${encodeURIComponent(action)}&song_ids=${selectedIds.join(',')}` })
                            .then(r => r.json())
                            .then(data => {
                                if (data.success) {
                                    // 按动作更新界面
                                    if (action === 'delete') {
                                        selectedIds.forEach(id => removeSongElements(id));
                                    } else if (action === 'mark-played') {
                                        selectedIds.forEach(id => applySongPlayedUI(id, true));
                                    } else if (action === 'mark-unplayed') {
                                        selectedIds.forEach(id => applySongPlayedUI(id, false));
                                    } else if (action === 'reset-votes') {
                                        selectedIds.forEach(id => updateSongVotesUI(id, 0));
                                    }
                                    // 清空选择
                                    document.querySelectorAll('.song-checkbox:checked').forEach(cb => cb.checked = false);
                                    updateSelectedCount();
                                    showSuccessToast(data.message || '批量操作已完成');
                                } else {
                                    showSuccessToast(data.message || '批量操作失败', false);
                                }
                            })
                            .catch(err => { showSuccessToast('网络错误', false); console.error(err); });
                    };
                    doRun();
                });

                // 编辑歌曲表单 AJAX 提交（如果存在）
                const editForm = document.getElementById('edit-song-form');
                if (editForm) {
                    editForm.addEventListener('submit', function(e) {
                        e.preventDefault();
                        const formData = new FormData(this);
                        fetch(this.action || 'update_song.php', { method: 'POST', body: formData })
                            .then(r => r.json())
                            .then(data => {
                                if (data.success) {
                                    showSuccessToast(data.message || '保存成功');
                                    if (editSongModal) editSongModal.classList.add('hidden');
                                    // 使用表单值就地更新列表展示
                                    try {
                                        const payload = Object.fromEntries(formData.entries());
                                        updateSongRowUI({
                                            id: payload.song_id,
                                            song_name: payload.song_name,
                                            artist: payload.artist,
                                            requestor: payload.requestor,
                                            class: payload.class,
                                            message: payload.message,
                                            votes: Number(payload.votes)
                                        });
                                    } catch (e) { /* ignore */ }
                                } else {
                                    showSuccessToast(data.message || '保存失败', false);
                                }
                            })
                            .catch(err => { showSuccessToast('保存失败', false); console.error(err); });
                    });
                }
            }

            // 简单工具函数
            function escapeHtml(str) { return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
            function debounce(fn, ms) { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; }

            // 局部 DOM 更新辅助
            function removeSongElements(id) {
                // 表格行
                document.querySelectorAll(`.song-checkbox[data-id="${id}"]`).forEach(cb => {
                    const tr = cb.closest('tr'); if (tr) tr.remove();
                });
                // 卡片
                document.querySelectorAll(`.delete-song[data-id="${id}"], .mark-played[data-id="${id}"], .mark-unplayed[data-id="${id}"]`).forEach(btn => {
                    const card = btn.closest('.bg-white.rounded-lg.shadow.p-4'); if (card) card.remove();
                });
            }

        function updateSongVotesUI(id, votes) {
                // 表格
                document.querySelectorAll(`tr .song-checkbox[data-id="${id}"]`).forEach(cb => {
            const td = cb.closest('tr')?.querySelector('td:nth-child(6) span');
                    if (td) td.textContent = votes;
                });
                // 卡片
                document.querySelectorAll(`#song-card-list .song-checkbox[data-id="${id}"]`).forEach(cb => {
                    const span = cb.closest('.bg-white')?.querySelector('.fa-thumbs-up')?.nextElementSibling;
                    if (span) span.textContent = votes;
                });
            }

            function applySongPlayedUI(id, played) {
                const filter = document.getElementById('song-filter')?.value || 'all';
                // 表格
                document.querySelectorAll(`tr .song-checkbox[data-id="${id}"]`).forEach(cb => {
                    const tr = cb.closest('tr');
                    if (!tr) return;
                    const statusTd = tr.querySelector('td:nth-child(8)');
                    if (statusTd) statusTd.innerHTML = played
                        ? '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">已播放</span>'
                        : '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">待播放</span>';
                    const opTd = tr.querySelector('td:nth-child(9) .flex');
                    if (opTd) {
                        const old = opTd.querySelector('.mark-played, .mark-unplayed');
                        if (old) old.remove();
                        const btn = document.createElement('button');
                        if (played) {
                            btn.className = 'mark-unplayed p-1.5 rounded bg-yellow-100 text-yellow-600 hover:bg-yellow-200 transition-all';
                            btn.setAttribute('data-id', id);
                            btn.innerHTML = '<i class="fa fa-undo"></i>';
                            btn.addEventListener('click', function() { fetchMark(id, 0); });
                        } else {
                            btn.className = 'mark-played p-1.5 rounded bg-green-100 text-green-600 hover:bg-green-200 transition-all';
                            btn.setAttribute('data-id', id);
                            btn.innerHTML = '<i class="fa fa-check"></i>';
                            btn.addEventListener('click', function() { fetchMark(id, 1); });
                        }
                        opTd.appendChild(btn);
                    }
                    // 当前筛选下需隐藏不匹配的
                    if ((filter === 'pending' && played) || (filter === 'played' && !played)) {
                        tr.remove();
                    }
                });
                // 卡片
                document.querySelectorAll(`#song-card-list .song-checkbox[data-id="${id}"]`).forEach(cb => {
                    const card = cb.closest('.bg-white.rounded-lg.shadow.p-4');
                    if (!card) return;
                    // 精确替换状态徽章，避免误改票数 span
                    let badge = card.querySelector('span.rounded-full');
                    const newBadgeHtml = played
                        ? '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">已播放</span>'
                        : '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">待播放</span>';
                    if (badge) {
                        badge.outerHTML = newBadgeHtml;
                    } else {
                        // 找到状态容器（在右侧 text-sm），插入新徽章
                        const statusWrap = card.querySelector('.mt-3 .flex.items-center.space-x-3 .text-sm:last-child');
                        if (statusWrap) statusWrap.innerHTML = newBadgeHtml;
                    }
                    const ops = card.querySelector('.flex.items-center.space-x-2');
                    if (ops) {
                        const old = ops.querySelector('.mark-played, .mark-unplayed');
                        if (old) old.remove();
                        const btn = document.createElement('button');
                        if (played) {
                            btn.className = 'mark-unplayed p-1.5 rounded bg-yellow-100 text-yellow-600 hover:bg-yellow-200 transition-all';
                            btn.setAttribute('data-id', id);
                            btn.innerHTML = '<i class="fa fa-undo"></i>';
                            btn.addEventListener('click', function() { fetchMark(id, 0); });
                        } else {
                            btn.className = 'mark-played p-1.5 rounded bg-green-100 text-green-600 hover:bg-green-200 transition-all';
                            btn.setAttribute('data-id', id);
                            btn.innerHTML = '<i class="fa fa-check"></i>';
                            btn.addEventListener('click', function() { fetchMark(id, 1); });
                        }
                        ops.appendChild(btn);
                    }
                    if ((filter === 'pending' && played) || (filter === 'played' && !played)) {
                        card.remove();
                    }
                });
            }

            function fetchMark(id, played) {
                fetch('mark_played.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `song_id=${encodeURIComponent(id)}&played=${played?1:0}` })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            applySongPlayedUI(id, !!played);
                            showSuccessToast(data.message || (played ? '已标记为已播放' : '已标记为待播放'));
                        } else {
                            showSuccessToast(data.message || '操作失败', false);
                        }
                    })
                    .catch(() => showSuccessToast('网络错误', false));
            }

            // 编辑保存后更新行/卡片展示
            function updateSongRowUI(song) {
                const id = song.id;
                if (!id) return;
                // 更新表格
                document.querySelectorAll(`tr .song-checkbox[data-id="${id}"]`).forEach(cb => {
                    const tr = cb.closest('tr');
                    if (!tr) return;
                    // 歌名/歌手
                    const songCell = tr.querySelector('td:nth-child(2)');
                    if (songCell) {
                        const nameEl = songCell.querySelector('.font-medium');
                        const artistEl = songCell.querySelector('.text-sm.text-gray-500');
                        if (nameEl && song.song_name != null) nameEl.textContent = song.song_name;
                        if (artistEl && song.artist != null) artistEl.textContent = song.artist;
                    }
                    // 点歌人
                    const reqCell = tr.querySelector('td:nth-child(3) .text-sm');
                    if (reqCell && song.requestor != null) reqCell.textContent = song.requestor;
                    // 班级
                    const classCell = tr.querySelector('td:nth-child(4) .text-sm');
                    if (classCell && song.class != null) classCell.textContent = song.class;
                    // 点歌时间
                    const timeCell = tr.querySelector('td:nth-child(5) .song-time');
                    if (timeCell && song.created_at != null) timeCell.textContent = song.created_at;
                    // 票数
                    if (Number.isFinite(song.votes)) updateSongVotesUI(id, Number(song.votes));
                    // 留言
                    const msgCell = tr.querySelector('td:nth-child(7) .song-message');
                    if (msgCell && song.message != null) {
                        msgCell.textContent = song.message;
                        msgCell.setAttribute('title', song.message);
                    }
                });
                // 更新卡片
                document.querySelectorAll(`#song-card-list .song-checkbox[data-id="${id}"]`).forEach(cb => {
                    const card = cb.closest('.bg-white.rounded-lg.shadow.p-4');
                    if (!card) return;
                    const titleEl = card.querySelector('.text-base.font-medium');
                    if (titleEl && song.song_name != null) titleEl.textContent = song.song_name;
                    const singerEl = card.querySelector('.text-sm.text-gray-500');
                    if (singerEl && song.artist != null) singerEl.textContent = `歌手：${song.artist}`;
                    const infoEls = card.querySelectorAll('.text-sm.text-gray-900');
                    if (infoEls && infoEls.length >= 2) {
                        if (song.requestor != null) infoEls[0].textContent = `点歌人：${song.requestor}`;
                        if (song.class != null) infoEls[1].textContent = `班级：${song.class}`;
                    }
                    const timeEl = card.querySelector('.song-time');
                    if (timeEl && song.created_at != null) timeEl.textContent = `时间：${song.created_at}`;
                    const msgEl = card.querySelector('.song-message');
                    if (msgEl && song.message != null) msgEl.textContent = `留言：${song.message}`;
                    if (Number.isFinite(song.votes)) updateSongVotesUI(id, Number(song.votes));
                });
            }
    </script>
</body>
</html>