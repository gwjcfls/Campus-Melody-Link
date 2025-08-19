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

            // 自动刷新当前面板（如果操作成功）
            try {
                if (success && typeof loadSection === 'function' && currentSection) {
                    // 小延迟以便用户能看到提示
                    setTimeout(() => { try { loadSection(currentSection, true); } catch(e){} }, 600);
                }
            } catch (e) { /* ignore */ }
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
                                    <td class="px-3 py-2 whitespace-normal break-words">\
                                        <div class=\"text-sm text-gray-900 flex items-center\">\
                                            <i class=\"fa fa-thumbs-up text-primary mr-1\"></i>\
                                            <span>${song.votes}</span>\
                                        </div>\
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

                // 通知与规则表单提交
                if (section === 'announcement-management') {
                    document.getElementById('announcement-form')?.addEventListener('submit', function(e) {
                        e.preventDefault();
                        const formData = new FormData(this);
                        fetch('update_announcement.php', { method: 'POST', body: formData })
                            .then(response => response.json())
                            .then(data => { showSuccessToast(data.message, data.success); if (data.success) setTimeout(() => loadSection('announcement-management', true), 600); });
                    });
                }
                if (section === 'rule-management') {
                    document.getElementById('rule-form')?.addEventListener('submit', function(e) {
                        e.preventDefault();
                        const formData = new FormData(this);
                        fetch('update_rule.php', { method: 'POST', body: formData })
                            .then(response => response.json())
                            .then(data => { showSuccessToast(data.message, data.success); if (data.success) setTimeout(() => loadSection('rule-management', true), 600); });
                    });
                }

                // 日志表无需额外绑定
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
                    btn.addEventListener('click', function() {
                        if (!confirm('确定要删除这首歌曲吗？')) return;
                        const songId = this.getAttribute('data-id');
                        fetch('delete_song.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `song_id=${encodeURIComponent(songId)}` })
                            .then(r => r.json())
                            .then(data => {
                                showSuccessToast(data.message, data.success);
                                if (data.success) setTimeout(() => loadSection('song-management', true), 800);
                            });
                    });
                });

                // 标记播放/未播放
                document.querySelectorAll('.mark-played').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const songId = this.getAttribute('data-id');
                        fetch('mark_played.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `song_id=${encodeURIComponent(songId)}&played=1` })
                            .then(r => r.json())
                            .then(data => {
                                showSuccessToast(data.message, data.success);
                                if (data.success) setTimeout(() => loadSection('song-management', true), 800);
                            });
                    });
                });
                document.querySelectorAll('.mark-unplayed').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const songId = this.getAttribute('data-id');
                        fetch('mark_played.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `song_id=${encodeURIComponent(songId)}&played=0` })
                            .then(r => r.json())
                            .then(data => {
                                showSuccessToast(data.message, data.success);
                                if (data.success) setTimeout(() => loadSection('song-management', true), 800);
                            });
                    });
                });

                // 批量操作
                document.getElementById('execute-batch')?.addEventListener('click', function() {
                    const selectedIds = Array.from(document.querySelectorAll('.song-checkbox:checked')).map(cb => cb.getAttribute('data-id'));
                    if (selectedIds.length === 0) { showSuccessToast('请至少选择一首歌曲', false); return; }
                    const action = document.getElementById('batch-action').value;
                    if (action === 'delete' && !confirm(`确定要批量删除选中的${selectedIds.length}首歌曲吗？此操作不可恢复！`)) return;
                    fetch('batch_operation.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `action=${encodeURIComponent(action)}&song_ids=${selectedIds.join(',')}` })
                        .then(r => r.json())
                        .then(data => {
                            showSuccessToast(data.message, data.success);
                            if (data.success) setTimeout(() => loadSection('song-management', true), 800);
                        })
                        .catch(err => { showSuccessToast('操作失败，请重试', false); console.error(err); });
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
                                showSuccessToast(data.message, data.success);
                                if (data.success) {
                                    // 关闭模态
                                    if (editSongModal) editSongModal.classList.add('hidden');
                                }
                            })
                            .catch(err => { showSuccessToast('保存失败', false); console.error(err); });
                    });
                }
            }

            // 简单工具函数
            function escapeHtml(str) { return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
            function debounce(fn, ms) { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; }
    </script>
</body>
</html>