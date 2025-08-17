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

// 获取所有歌曲
$stmt = $pdo->prepare("SELECT * FROM song_requests ORDER BY played ASC, votes DESC, created_at ASC");
$stmt->execute();
$songs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 获取通知
$stmt = $pdo->prepare("SELECT * FROM announcements ORDER BY created_at DESC LIMIT 1");
$stmt->execute();
$announcement = $stmt->fetch(PDO::FETCH_ASSOC);

// 获取点歌规则
$stmt = $pdo->prepare("SELECT * FROM rules ORDER BY updated_at DESC LIMIT 1");
$stmt->execute();
$rule = $stmt->fetch(PDO::FETCH_ASSOC);

// 获取日志（仅超级管理员可见）
$logs = [];
if ($admin_role === 'super_admin') {
    $log_stmt = $pdo->prepare("SELECT * FROM operation_logs ORDER BY created_at DESC LIMIT 100");
    $log_stmt->execute();
    $logs = $log_stmt->fetchAll(PDO::FETCH_ASSOC);
}
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
                <!-- 歌曲管理 -->
                <div id="song-management" class="admin-content">
                    <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-2xl font-bold text-dark flex items-center">
                                <i class="fa fa-list-ul text-primary mr-2"></i>歌曲管理
                            </h2>
                            <div class="flex space-x-2">
                                <div class="relative">
                                    <input type="text" id="song-search" placeholder="搜索歌曲..." class="pl-10 pr-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary transition-all">
                                    <i class="fa fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                                </div>
                                <select id="song-filter" class="px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary transition-all">
                                    <option value="all">全部歌曲</option>
                                    <option value="pending">待播放</option>
                                    <option value="played">已播放</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- 批量操作工具栏 -->
                        <div class="flex flex-wrap items-center gap-4 mb-6">
                            <div class="flex items-center">
                                <input type="checkbox" id="select-all" class="w-4 h-4 text-primary rounded border-gray-300 focus:ring-primary">
                                <label for="select-all" class="ml-2 text-sm text-gray-700">全选</label>
                            </div>
                            <select id="batch-action" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary">
                                <!-- 普通管理员可见操作 -->
                                <option value="mark-played">批量标记为已播放</option>
                                <option value="mark-unplayed">批量标记为待播放</option>
                                
                                <!-- 仅超级管理员可见操作 -->
                                <?php if ($admin_role === 'super_admin'): ?>
                                    <option value="delete">批量删除歌曲</option>
                                    <option value="reset-votes">批量重置票数为0</option>
                                <?php endif; ?>
                            </select>
                            <button id="execute-batch" class="px-4 py-2 bg-primary text-white rounded-md hover:bg-primary/90 transition-all">
                                执行操作
                            </button>
                            <span id="selected-count" class="text-sm text-gray-500">已选择 0 首歌曲</span>
                        </div>
                                                
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">选择</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">歌曲</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">点歌人</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">班级</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">投票</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">状态</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">操作</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200" id="song-table-body">
                                    <?php foreach ($songs as $song): ?>
                                        <tr class="hover:bg-gray-50 transition-all">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <input type="checkbox" class="song-checkbox w-4 h-4 text-primary rounded border-gray-300 focus:ring-primary" 
                                                       data-id="<?php echo $song['id']; ?>">
                                            </td>
                                                                            
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="font-medium text-gray-900"><?php echo htmlspecialchars($song['song_name']); ?></div>
                                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($song['artist']); ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900"><?php echo htmlspecialchars($song['requestor']); ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900"><?php echo htmlspecialchars($song['class']); ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900 flex items-center">
                                                    <i class="fa fa-thumbs-up text-primary mr-1"></i>
                                                    <span><?php echo $song['votes']; ?></span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php if ($song['played']): ?>
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">已播放</span>
                                                <?php else: ?>
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">待播放</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <div class="flex space-x-2">
                                                    <?php if ($admin_role === 'super_admin'): ?>
                                                        <button class="edit-song p-1.5 rounded bg-blue-100 text-blue-600 hover:bg-blue-200 transition-all" data-id="<?php echo $song['id']; ?>">
                                                            <i class="fa fa-pencil"></i>
                                                        </button>
                                                        <button class="delete-song p-1.5 rounded bg-red-100 text-red-600 hover:bg-red-200 transition-all" data-id="<?php echo $song['id']; ?>">
                                                            <i class="fa fa-trash"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <?php if ($song['played']): ?>
                                                        <button class="mark-unplayed p-1.5 rounded bg-yellow-100 text-yellow-600 hover:bg-yellow-200 transition-all" data-id="<?php echo $song['id']; ?>">
                                                            <i class="fa fa-undo"></i>
                                                        </button>
                                                    <?php else: ?>
                                                        <button class="mark-played p-1.5 rounded bg-green-100 text-green-600 hover:bg-green-200 transition-all" data-id="<?php echo $song['id']; ?>">
                                                            <i class="fa fa-check"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- 通知管理 -->
                <div id="announcement-management" class="admin-content hidden">
                    <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-2xl font-bold text-dark flex items-center">
                                <i class="fa fa-bullhorn text-primary mr-2"></i>通知管理
                            </h2>
                        </div>
                        
                        <form id="announcement-form" method="POST" action="update_announcement.php">
                            <div class="mb-4">
                                <label for="announcement_content" class="block text-sm font-medium text-gray-700 mb-1">通知内容</label>
                                <textarea id="announcement_content" name="announcement_content" rows="5" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary transition-all" placeholder="请输入通知内容..."><?php echo htmlspecialchars($announcement['content'] ?? ''); ?></textarea>
                            </div>
                            <div class="flex justify-end">
                                <button type="submit" class="px-6 py-2 bg-primary text-white rounded-md hover:bg-primary/90 transition-all">
                                    <i class="fa fa-save mr-2"></i>保存通知
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- 点歌规则管理 -->
                <div id="rule-management" class="admin-content hidden">
                    <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-2xl font-bold text-dark flex items-center">
                                <i class="fa fa-book text-primary mr-2"></i>点歌规则管理
                            </h2>
                        </div>
                        
                        <form id="rule-form" method="POST" action="update_rule.php">
                            <div class="mb-4">
                                <label for="rule_content" class="block text-sm font-medium text-gray-700 mb-1">点歌规则内容</label>
                                <textarea id="rule_content" name="rule_content" rows="10" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary transition-all" placeholder="请输入点歌规则内容..."><?php echo htmlspecialchars($rule['content'] ?? ''); ?></textarea>
                            </div>
                            <div class="flex justify-end">
                                <button type="submit" class="px-6 py-2 bg-primary text-white rounded-md hover:bg-primary/90 transition-all">
                                    <i class="fa fa-save mr-2"></i>保存规则
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php if ($admin_role === 'super_admin'): ?>
                <!-- 操作日志管理 -->
                <div id="log-management" class="admin-content hidden">
                    <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-2xl font-bold text-dark flex items-center">
                                <i class="fa fa-file-text-o text-primary mr-2"></i>操作日志
                            </h2>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-xs md:text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-2 py-2 text-left font-medium text-gray-500">时间</th>
                                        <th class="px-2 py-2 text-left font-medium text-gray-500">用户</th>
                                        <th class="px-2 py-2 text-left font-medium text-gray-500">角色</th>
                                        <th class="px-2 py-2 text-left font-medium text-gray-500">操作类型</th>
                                        <th class="px-2 py-2 text-left font-medium text-gray-500">对象</th>
                                        <th class="px-2 py-2 text-left font-medium text-gray-500">详情</th>
                                        <th class="px-2 py-2 text-left font-medium text-gray-500">IP</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td class="px-2 py-2 whitespace-nowrap"><?php echo htmlspecialchars($log['created_at']); ?></td>
                                        <td class="px-2 py-2 whitespace-nowrap"><?php echo htmlspecialchars($log['user']); ?></td>
                                        <td class="px-2 py-2 whitespace-nowrap"><?php echo htmlspecialchars($log['role']); ?></td>
                                        <td class="px-2 py-2 whitespace-nowrap"><?php echo htmlspecialchars($log['action']); ?></td>
                                        <td class="px-2 py-2 whitespace-nowrap"><?php echo htmlspecialchars($log['target']); ?></td>
                                        <td class="px-2 py-2 whitespace-nowrap max-w-xs truncate" title="<?php echo htmlspecialchars($log['details']); ?>"><?php echo htmlspecialchars(mb_strimwidth($log['details'], 0, 60, '...')); ?></td>
                                        <td class="px-2 py-2 whitespace-nowrap"><?php echo htmlspecialchars($log['ip']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php if (empty($logs)): ?>
                                <div class="text-gray-400 text-center py-8">暂无日志记录</div>
                            <?php endif; ?>
                        </div>
                        <div class="text-gray-400 text-xs mt-2">仅显示最近100条操作日志</div>
                    </div>
                </div>
                <?php endif; ?>
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

    <!-- 成功提示框 -->
    <div id="successToast" class="fixed bottom-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg transform translate-y-20 opacity-0 transition-all duration-300 flex items-center">
        <i class="fa fa-check-circle mr-2"></i>
        <span id="successMessage">操作成功</span>
    </div>

    <script>
        // 管理菜单切换
        const adminTabs = document.querySelectorAll('.admin-tab');
        const adminContents = document.querySelectorAll('.admin-content');
        
        adminTabs.forEach(tab => {
            tab.addEventListener('click', function() {
                adminTabs.forEach(t => {
                    t.classList.remove('bg-primary', 'text-white');
                    t.classList.add('text-gray-700', 'hover:bg-gray-100');
                });
                this.classList.remove('text-gray-700', 'hover:bg-gray-100');
                this.classList.add('bg-primary', 'text-white');
                
                adminContents.forEach(content => content.classList.add('hidden'));
                document.getElementById(this.getAttribute('data-target')).classList.remove('hidden');
            });
        });

        // 编辑歌曲模态框（仅超级管理员）
        <?php if ($admin_role === 'super_admin'): ?>
        const editSongModal = document.getElementById('edit-song-modal');
        const closeEditModal = document.getElementById('close-edit-modal');
        const editSongBtns = document.querySelectorAll('.edit-song');
        const editSongForm = document.getElementById('edit-song-form');
        
        editSongBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const songId = this.getAttribute('data-id');
                fetch(`get_song.php?id=${songId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const song = data.song;
                            document.getElementById('edit-song-id').value = song.id;
                            document.getElementById('edit-song-name').value = song.song_name;
                            document.getElementById('edit-artist').value = song.artist;
                            document.getElementById('edit-requestor').value = song.requestor;
                            document.getElementById('edit-class').value = song.class;
                            document.getElementById('edit-message').value = song.message;
                            document.getElementById('edit-votes').value = song.votes;
                            editSongModal.classList.remove('hidden');
                        } else {
                            showSuccessToast('获取歌曲信息失败', false);
                        }
                    });
            });
        });
        
        closeEditModal.addEventListener('click', () => editSongModal.classList.add('hidden'));
        window.addEventListener('click', (e) => {
            if (e.target === editSongModal) editSongModal.classList.add('hidden');
        });
        
        editSongForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            fetch('update_song.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    showSuccessToast(data.message, data.success);
                    if (data.success) {
                        editSongModal.classList.add('hidden');
                        setTimeout(() => location.reload(), 1500);
                    }
                });
        });
        <?php endif; ?>

        // 删除歌曲（仅超级管理员）
        <?php if ($admin_role === 'super_admin'): ?>
        const deleteSongBtns = document.querySelectorAll('.delete-song');
        deleteSongBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                if (confirm('确定要删除这首歌曲吗？')) {
                    const songId = this.getAttribute('data-id');
                    fetch('delete_song.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `song_id=${encodeURIComponent(songId)}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        showSuccessToast(data.message, data.success);
                        if (data.success) setTimeout(() => location.reload(), 1500);
                    });
                }
            });
        });
        <?php endif; ?>

        // 标记播放状态（所有管理员均可操作）
        const markPlayedBtns = document.querySelectorAll('.mark-played');
        markPlayedBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const songId = this.getAttribute('data-id');
                fetch('mark_played.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `song_id=${encodeURIComponent(songId)}&played=1`
                })
                .then(response => response.json())
                .then(data => {
                    showSuccessToast(data.message, data.success);
                    if (data.success) setTimeout(() => location.reload(), 1500);
                });
            });
        });

        const markUnplayedBtns = document.querySelectorAll('.mark-unplayed');
        markUnplayedBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const songId = this.getAttribute('data-id');
                fetch('mark_played.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `song_id=${encodeURIComponent(songId)}&played=0`
                })
                .then(response => response.json())
                .then(data => {
                    showSuccessToast(data.message, data.success);
                    if (data.success) setTimeout(() => location.reload(), 1500);
                });
            });
        });

        // 通知和规则表单提交（所有管理员均可操作）
        document.getElementById('announcement-form')?.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            fetch('update_announcement.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => showSuccessToast(data.message, data.success));
        });

        document.getElementById('rule-form')?.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            fetch('update_rule.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => showSuccessToast(data.message, data.success));
        });

        // 搜索和过滤功能
        const songSearch = document.getElementById('song-search');
        const songFilter = document.getElementById('song-filter');
        const songTableBody = document.getElementById('song-table-body');
        let originalSongs = <?php echo json_encode($songs); ?>;
        
        function filterSongs() {
            const searchTerm = songSearch.value.toLowerCase();
            const filterValue = songFilter.value;
            let filteredSongs = originalSongs.filter(song => {
                const matchesSearch = 
                    song.song_name.toLowerCase().includes(searchTerm) || 
                    song.artist.toLowerCase().includes(searchTerm) || 
                    song.requestor.toLowerCase().includes(searchTerm) || 
                    song.class.toLowerCase().includes(searchTerm);
                const matchesFilter = 
                    filterValue === 'all' || 
                    (filterValue === 'pending' && !song.played) || 
                    (filterValue === 'played' && song.played);
                return matchesSearch && matchesFilter;
            });

            songTableBody.innerHTML = '';
            if (filteredSongs.length === 0) {
                songTableBody.innerHTML = `
                    <tr><td colspan="6" class="px-6 py-12 text-center"><div class="text-gray-500">没有找到匹配的歌曲</div></td></tr>
                `;
                return;
            }

            filteredSongs.forEach(song => {
                const row = document.createElement('tr');
                row.className = 'hover:bg-gray-50 transition-all';
                row.innerHTML = `
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="font-medium text-gray-900">${song.song_name}</div>
                        <div class="text-sm text-gray-500">${song.artist}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-900">${song.requestor}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-900">${song.class}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-900 flex items-center">
                            <i class="fa fa-thumbs-up text-primary mr-1"></i>
                            <span>${song.votes}</span>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        ${song.played ? 
                            '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">已播放</span>' : 
                            '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">待播放</span>'
                        }
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        <div class="flex space-x-2">
                            <?php if ($admin_role === 'super_admin'): ?>
                                <button class="edit-song p-1.5 rounded bg-blue-100 text-blue-600 hover:bg-blue-200 transition-all" data-id="${song.id}">
                                    <i class="fa fa-pencil"></i>
                                </button>
                                <button class="delete-song p-1.5 rounded bg-red-100 text-red-600 hover:bg-red-200 transition-all" data-id="${song.id}">
                                    <i class="fa fa-trash"></i>
                                </button>
                            <?php endif; ?>
                            ${song.played ? 
                                `<button class="mark-unplayed p-1.5 rounded bg-yellow-100 text-yellow-600 hover:bg-yellow-200 transition-all" data-id="${song.id}">
                                    <i class="fa fa-undo"></i>
                                </button>` : 
                                `<button class="mark-played p-1.5 rounded bg-green-100 text-green-600 hover:bg-green-200 transition-all" data-id="${song.id}">
                                    <i class="fa fa-check"></i>
                                </button>`
                            }
                        </div>
                    </td>
                `;
                songTableBody.appendChild(row);
            });
            bindSongEvents();
        }
        
        function bindSongEvents() {
            <?php if ($admin_role === 'super_admin'): ?>
            document.querySelectorAll('.edit-song').forEach(btn => {
                btn.addEventListener('click', function() {
                    // 编辑功能绑定（同前文逻辑）
                    const songId = this.getAttribute('data-id');
                    fetch(`get_song.php?id=${songId}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                const song = data.song;
                                document.getElementById('edit-song-id').value = song.id;
                                document.getElementById('edit-song-name').value = song.song_name;
                                document.getElementById('edit-artist').value = song.artist;
                                document.getElementById('edit-requestor').value = song.requestor;
                                document.getElementById('edit-class').value = song.class;
                                document.getElementById('edit-message').value = song.message;
                                document.getElementById('edit-votes').value = song.votes;
                                editSongModal.classList.remove('hidden');
                            }
                        });
                });
            });

            document.querySelectorAll('.delete-song').forEach(btn => {
                btn.addEventListener('click', function() {
                    // 删除功能绑定（同前文逻辑）
                    if (confirm('确定要删除这首歌曲吗？')) {
                        const songId = this.getAttribute('data-id');
                        fetch('delete_song.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: `song_id=${encodeURIComponent(songId)}`
                        })
                        .then(response => response.json())
                        .then(data => {
                            showSuccessToast(data.message, data.success);
                            if (data.success) setTimeout(() => location.reload(), 1500);
                        });
                    }
                });
            });
            <?php endif; ?>

            // 播放状态修改绑定（所有管理员）
            document.querySelectorAll('.mark-played').forEach(btn => {
                btn.addEventListener('click', function() {
                    const songId = this.getAttribute('data-id');
                    fetch('mark_played.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `song_id=${encodeURIComponent(songId)}&played=1`
                    })
                    .then(response => response.json())
                    .then(data => {
                        showSuccessToast(data.message, data.success);
                        if (data.success) setTimeout(() => location.reload(), 1500);
                    });
                });
            });

            document.querySelectorAll('.mark-unplayed').forEach(btn => {
                btn.addEventListener('click', function() {
                    const songId = this.getAttribute('data-id');
                    fetch('mark_played.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `song_id=${encodeURIComponent(songId)}&played=0`
                    })
                    .then(response => response.json())
                    .then(data => {
                        showSuccessToast(data.message, data.success);
                        if (data.success) setTimeout(() => location.reload(), 1500);
                    });
                });
            });
        }
        
        songSearch.addEventListener('input', filterSongs);
        songFilter.addEventListener('change', filterSongs);

        // 提示框功能
        function showSuccessToast(message, isSuccess = true) {
            const toast = document.getElementById('successToast');
            const messageEl = document.getElementById('successMessage');
            messageEl.textContent = message;
            toast.classList.remove('bg-green-500', 'bg-red-500');
            toast.classList.add(isSuccess ? 'bg-green-500' : 'bg-red-500');
            toast.classList.remove('translate-y-20', 'opacity-0');
            toast.classList.add('translate-y-0', 'opacity-100');
            setTimeout(() => {
                toast.classList.remove('translate-y-0', 'opacity-100');
                toast.classList.add('translate-y-20', 'opacity-0');
            }, 3000);
        }
        
        //批量操作功能，添加前端交互逻辑（ admin.php 中的 <script> 部分）
        // 全选复选框逻辑
        document.getElementById('select-all').addEventListener('change', function() {
            const isChecked = this.checked;
            const checkboxes = document.querySelectorAll('.song-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = isChecked;
            });
            updateSelectedCount(); // 更新选中数量
        });
        // 监听单个复选框变化，更新选中数量
        document.querySelectorAll('.song-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', updateSelectedCount);
        });
        
        // 更新选中数量显示
        function updateSelectedCount() {
            const selectedCount = document.querySelectorAll('.song-checkbox:checked').length;
            document.getElementById('selected-count').textContent = `已选择 ${selectedCount} 首歌曲`;
        }
        // 执行批量操作按钮点击事件
        document.getElementById('execute-batch').addEventListener('click', function() {
            // 1. 获取选中的歌曲ID
            const selectedIds = [];
            document.querySelectorAll('.song-checkbox:checked').forEach(checkbox => {
                selectedIds.push(checkbox.getAttribute('data-id'));
            });
            
            // 2. 验证选中数量
            if (selectedIds.length === 0) {
                showSuccessToast('请至少选择一首歌曲', false);
                return;
            }
            
            // 3. 获取操作类型并确认
            const action = document.getElementById('batch-action').value;
            const actionTextMap = {
                'mark-played': '标记为已播放',
                'mark-unplayed': '标记为待播放',
                'delete': '删除',
                'reset-votes': '重置票数为0'
            };
            const actionText = actionTextMap[action];
            
            // 4. 危险操作二次确认（删除）
            if (action === 'delete' && !confirm(`确定要批量${actionText}选中的${selectedIds.length}首歌曲吗？此操作不可恢复！`)) {
                return;
            }
            
            // 5. 发送请求到后端
            fetch('batch_operation.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=${encodeURIComponent(action)}&song_ids=${selectedIds.join(',')}`
            })
            .then(response => response.json())
            .then(data => {
                showSuccessToast(data.message, data.success);
                if (data.success) {
                    // 操作成功后刷新页面
                    setTimeout(() => window.location.reload(), 1500);
                }
            })
            .catch(error => {
                showSuccessToast('操作失败，请重试', false);
                console.error('批量操作错误:', error);
            });
        });
    </script>
</body>
</html>