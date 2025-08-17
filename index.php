<?php
// 会话安全配置（放在session_start()之前）
ini_set('session.cookie_secure', 'On'); // 仅通过HTTPS传输Cookie（需服务器支持HTTPS）
ini_set('session.cookie_httponly', 'On'); // 禁止JS读取Cookie，防止XSS窃取
ini_set('session.cookie_samesite', 'Strict'); // 限制跨站请求携带Cookie，防CSRF
ini_set('session.cookie_lifetime', 0); // 会话Cookie随浏览器关闭失效
ini_set('session.gc_maxlifetime', 3600); // 会话有效期1小时（无操作自动失效）
ini_set('session.regenerate_id', 'On'); // 每次请求刷新Session ID，防止固定攻击


session_start();
require_once 'db_connect.php';

// 获取通知
$stmt = $pdo->prepare("SELECT content FROM announcements ORDER BY created_at DESC LIMIT 1");
$stmt->execute();
$announcement = $stmt->fetchColumn();

// 获取点歌规则
$stmt = $pdo->prepare("SELECT content FROM rules ORDER BY updated_at DESC LIMIT 1");
$stmt->execute();
$rules = $stmt->fetchColumn();

// 未播放歌曲
$stmt = $pdo->prepare("SELECT * FROM song_requests WHERE played = false ORDER BY votes DESC, created_at ASC");
$stmt->execute();
$pending_songs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 已播放歌曲
$stmt = $pdo->prepare("SELECT * FROM song_requests WHERE played = true ORDER BY played_at DESC");
$stmt->execute();
$played_songs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 增加/取消投票（替换原投票逻辑）
if (isset($_GET['vote']) && is_numeric($_GET['vote']) && isset($_GET['action'])) {
    $song_id = $_GET['vote'];
    $action = $_GET['action'];
    
    if ($action === 'add') {
        // 点赞：票数+1
        $stmt = $pdo->prepare("UPDATE song_requests SET votes = votes + 1 WHERE id = ?");
        $log_action = '点赞';
    } elseif ($action === 'cancel') {
        // 取消点赞：票数-1（确保不小于0）
        $stmt = $pdo->prepare("UPDATE song_requests SET votes = GREATEST(votes - 1, 0) WHERE id = ?");
        $log_action = '取消点赞';
    } else {
        // 无效操作
        http_response_code(400);
        exit;
    }
    
    $stmt->execute([$song_id]);
    // 日志记录
    $user = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] ? ($_SESSION['admin_username'] ?? 'admin') : ($_SERVER['REMOTE_ADDR'] ?? 'guest');
    $role = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] ? ($_SESSION['admin_role'] ?? 'admin') : 'user';
    log_operation(
        $pdo,
        $user,
        $role,
        $log_action,
        $song_id,
        null
    );
    header("Location: index.php");
    exit;
}
// 增加投票
// if (isset($_GET['vote']) && is_numeric($_GET['vote'])) {
    // $song_id = $_GET['vote'];
    // $stmt = $pdo->prepare("UPDATE song_requests SET votes = votes + 1 WHERE id = ?");
    // $stmt->execute([$song_id]);
    // header("Location: index.php");
    // exit;
// }
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>语之声·校园点歌站</title>
    
    <!-- <script src="https://cdn.tailwindcss.com"></script> -->
    <!-- <link rel="stylesheet" href="css/tailwind.css"> -->
    <script src="js/tailwindcss.js"></script>
    <!-- <link. rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css"> -->
    <link rel="stylesheet" href="css/font-awesome.min.css">
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
            .animate-pulse {
                animation: pulse 1s cubic-bezier(0.4, 0, 0.6, 1) infinite;
            }
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
    </style>
</head>
<body class="font-sans kawaii-pattern">
    <!-- 导航栏 -->
    <nav class="bg-white/80 backdrop-blur-lg shadow-cute fixed w-full z-50 transition-all duration-300" id="navbar">
        <div class="container mx-auto px-4 py-3 flex justify-between items-center">
            <div class="flex items-center space-x-3">
                <i class="fa fa-music text-primary text-2xl"></i>
                <h1 class="text-xl md:text-2xl font-display font-bold text-dark">
                    <span class="font-extrabold">语之声</span><span id="radio-station-text" class="text-primary opacity-80">点歌站</span>
                </h1>
            </div>
            <div class="flex items-center space-x-2 md:space-x-4">
                <button id="ruleBtn" class="px-4 py-2 rounded-full text-primary border border-primary/50 hover:bg-primary/10 transition-all flex items-center text-sm md:text-base">
                    <i class="fa fa-book mr-2"></i>点歌规则
                </button>
                <button id="adminBtn" class="hidden-btn px-4 py-2 rounded-full bg-primary text-white hover:bg-opacity-90 shadow-cute hover:shadow-cute-hover transition-all flex items-center text-sm md:text-base">
                    <i class="fa fa-lock mr-2"></i>管理
                </button>
            </div>
        </div>
    </nav>
    <!-- 主内容区 -->
    <main class="container mx-auto px-4 pt-24 pb-16">
        
        <!-- 通知区域 -->
        <div class="bg-white border-l-4 border-primary p-5 rounded-2xl mb-8 relative overflow-hidden shadow-cute">
            <div class="flex items-start">
                <div class="flex-shrink-0 bg-primary/10 p-3 rounded-full">
                    <i class="fa fa-bullhorn text-primary text-xl fa-beat"></i>
                </div>
                <div class="ml-4">
                    <h3 class="text-lg font-bold text-primary">校园广播通知</h3>
                    <div class="mt-1 text-sm text-dark/70 leading-relaxed">
                        <?php echo nl2br(($announcement)); ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- 点歌规则模态框 -->
        <div id="ruleModal" class="fixed inset-0 bg-dark/30 backdrop-blur-sm z-50 flex items-center justify-center hidden p-4">
            <div class="bg-white rounded-3xl shadow-xl max-w-2xl w-full mx-4 transform transition-all opacity-0 -translate-y-4">
                <div class="flex justify-between items-center p-4 border-b border-gray-100">
                    <h3 class="text-xl font-display font-bold text-dark ml-2">点歌规则</h3>
                    <button id="closeRuleBtn" class="text-gray-400 hover:text-primary transition-colors w-8 h-8 flex items-center justify-center">
                        <i class="fa fa-times text-xl"></i>
                    </button>
                </div>
                <div class="p-6 max-h-[70vh] overflow-y-auto text-dark/80">
                    <div class="prose max-w-none">
                        <?php echo nl2br($rules); ?>
                    </div>
                </div>
                <div class="p-4 bg-light/50 rounded-b-3xl flex justify-end">
                    <button id="confirmRuleBtn" class="px-6 py-2 bg-primary text-white rounded-full hover:bg-opacity-90 transition-all shadow-cute">
                        我记下啦！
                    </button>
                </div>
            </div>
        </div>
        
        <!-- 管理登录模态框 (similar styling) -->
        <div id="adminModal" class="fixed inset-0 bg-dark/30 backdrop-blur-sm z-50 flex items-center justify-center hidden p-4">
            <div class="bg-white rounded-3xl shadow-xl max-w-md w-full mx-4 transform transition-all opacity-0 -translate-y-4">
                <div class="flex justify-between items-center p-4 border-b border-gray-100">
                    <h3 class="text-xl font-display font-bold text-dark ml-2">管理员登录</h3>
                    <button id="closeAdminBtn" class="text-gray-400 hover:text-primary transition-colors w-8 h-8 flex items-center justify-center">
                        <i class="fa fa-times text-xl"></i>
                    </button>
                </div>
                <div class="p-6">
                    <form id="adminLoginForm" method="POST" action="admin_login.php">
                        <!-- 新增的用户名输入区域 -->
                        <div class="mb-4">
                            <label for="admin_username" class="block text-sm font-medium text-dark/70 mb-1">管理员用户名</label>
                            <input type="text" id="admin_username" name="admin_username" class="w-full px-4 py-2 border border-gray-200 rounded-full focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary transition-all" required>
                        </div>
                        <div class="mb-4">
                            <label for="admin_password" class="block text-sm font-medium text-dark/70 mb-1">管理员密码</label>
                            <input type="password" id="admin_password" name="admin_password" class="w-full px-4 py-2 border border-gray-200 rounded-full focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary transition-all" required>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" class="px-6 py-2 bg-primary text-white rounded-full hover:bg-opacity-90 transition-all shadow-cute">
                                登录
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- 搜索结果模态框 -->
        <div id="searchModal" class="fixed inset-0 bg-dark/30 backdrop-blur-sm z-50 flex items-center justify-center hidden p-4">
            <div class="bg-white rounded-3xl shadow-xl max-w-2xl w-full mx-4 transform transition-all opacity-0 -translate-y-4">
                <div class="flex justify-between items-center p-4 border-b border-gray-100">
                    <h3 class="text-xl font-display font-bold text-dark ml-2">搜索歌曲</h3>
                    <button id="closeSearchBtn" class="text-gray-400 hover:text-primary transition-colors w-8 h-8 flex items-center justify-center">
                        <i class="fa fa-times text-xl"></i>
                    </button>
                </div>
                <div id="searchResults" class="p-2 md:p-6 max-h-[70vh] overflow-y-auto">
                    <!-- 搜索结果将通过JS动态填充 -->
                </div>
                <div class="p-4 bg-light/50 rounded-b-3xl flex justify-end">
                    <button id="cancelSearchBtn" class="px-6 py-2 bg-gray-200 text-gray-700 rounded-full hover:bg-gray-300 transition-all">
                        取消
                    </button>
                </div>
            </div>
        </div>
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- 左侧：点歌表单 -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-3xl shadow-cute p-6 sticky top-24">
                    <h2 class="text-2xl font-display font-bold text-dark mb-6 flex items-center">
                        <i class="fa fa-headphones text-primary mr-3"></i>点歌台
                    </h2>
                    <form id="requestForm" method="POST" action="submit_request.php" class="relative z-10">
                        <div class="space-y-5">
                             <div>
                                <label for="song_name_display" class="block text-sm font-medium text-dark/70 mb-1 ml-2">歌曲名称</label>
                                <div class="relative flex items-center">
                                    <input type="text" id="song_name_display" class="w-full pl-5 pr-16 py-3 border border-gray-200 rounded-full focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary transition-all" placeholder="搜一搜想听的歌" required>
                                    <button id="searchSongBtn" type="button" class="absolute right-1.5 bg-primary text-white rounded-full hover:bg-opacity-90 transition-all flex items-center justify-center h-10 w-10">
                                        <i class="fa fa-search"></i>
                                    </button>
                                </div>
                                <input type="hidden" id="song_name" name="song_name">
                            </div>
                           
                            <div>
                               <label for="artist" class="block text-sm font-medium text-dark/70 mb-1 ml-2">歌手</label>
                               <input type="text" id="artist" name="artist" class="w-full px-5 py-3 border border-gray-200 rounded-full bg-gray-100 cursor-not-allowed" placeholder="会自动填充哦" readonly required>
                            </div>
                            
                            <div>
                                <label for="requestor" class="block text-sm font-medium text-dark/70 mb-1 ml-2">你的名字</label>
                                <input type="text" id="requestor" name="requestor" class="w-full px-5 py-3 border border-gray-200 rounded-full focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary transition-all" placeholder="怎么称呼你呢？" required>
                            </div>
                            
                            <div>
                                <label for="class" class="block text-sm font-medium text-dark/70 mb-1 ml-2">你的班级</label>
                                <input type="text" id="class" name="class" class="w-full px-5 py-3 border border-gray-200 rounded-full focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary transition-all" placeholder="来自哪个班呀？" required>
                            </div>
                            
                            <div>
                                <label for="message" class="block text-sm font-medium text-dark/70 mb-1 ml-2">悄悄话</label>
                                <textarea id="message" name="message" rows="3" class="w-full px-5 py-3 border border-gray-200 rounded-2xl focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary transition-all" placeholder="想对Ta或者大家说点什么吗..."></textarea>
                            </div>
                            
                            <div class="pt-2">
                                <button type="submit" id="submit" class="w-full py-3 bg-gradient-to-r from-primary to-secondary text-white rounded-full hover:shadow-lg hover:-translate-y-0.5 transition-all flex items-center justify-center font-bold text-lg shadow-cute">
                                    <i class="fa fa-paper-plane mr-2"></i>提交点歌
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- 右侧：歌曲列表 -->
            <div class="lg:col-span-2 space-y-8">
                <!-- 待播放歌曲 -->
                <div class="bg-white rounded-3xl shadow-cute p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-2xl font-display font-bold text-dark flex items-center">
                            <i class="fa fa-list-ul text-primary mr-3"></i>待播清单
                        </h2>
                        <span class="px-3 py-1 bg-primary/10 text-primary rounded-full text-sm font-medium flex items-center">
                            <i class="fa fa-music mr-2"></i><?php echo count($pending_songs); ?> 首
                        </span>
                    </div>
                    
                    <!-- 新增：待播放列表搜索框 -->
                    <div class="mb-4">
                        <div class="relative">
                            <input type="text" id="pendingSongSearch" 
                                   class="w-full pl-10 pr-4 py-2 border border-gray-200 rounded-full focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary transition-all" 
                                   placeholder="搜索待播放歌曲...">
                            <i class="fa fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        </div>
                    </div>
                    
                    <div class="space-y-4" id="pendingSongsContainer">
                        <?php if (count($pending_songs) > 0): ?>
                            <?php foreach ($pending_songs as $song): ?>
                                <div class="bg-light/80 rounded-2xl p-4 card-hover border border-white" data-song-id="<?php echo $song['id']; ?>" data-song-name="<?php echo htmlspecialchars($song['song_name']); ?>" data-artist="<?php echo htmlspecialchars($song['artist']); ?>">
                                     <div class="flex justify-between items-start">
                                        <div class="flex-1">
                                            <h3 class="font-bold text-lg text-dark"><?php echo htmlspecialchars($song['song_name']); ?></h3>
                                            <p class="text-dark/70 text-sm mt-1">
                                                <i class="fa fa-microphone mr-1 opacity-60"></i><?php echo htmlspecialchars($song['artist']); ?>
                                            </p>
                                             <p class="text-gray-600 text-sm mt-2 bg-white/80 p-3 rounded-lg">
                                                <span class="font-bold text-primary/80"><?php echo htmlspecialchars($song['class']); ?> <?php echo htmlspecialchars($song['requestor']); ?></span>: <?php echo htmlspecialchars($song['message']); ?>
                                            </p>
                                        </div>
                                        <div class="ml-4 flex flex-col items-center space-y-1">
                                            <button class="vote-btn p-2 rounded-full bg-white text-secondary hover:bg-secondary/10 transition-all transform hover:scale-110 shadow-sm" data-id="<?php echo $song['id']; ?>">
                                                <i class="fa fa-heart"></i>
                                            </button>
                                            <span class="text-secondary font-bold text-sm vote-count-<?php echo $song['id']; ?>"><?php echo $song['votes']; ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-12">
                                <i class="fa fa-music text-primary/20 text-5xl mb-3"></i>
                                <p class="text-dark/60">还没有人点歌哦，快来当第一个！</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- 已播放歌曲 -->
                <div class="bg-white rounded-3xl shadow-cute p-6 opacity-80">
                    <div class="flex justify-between items-center mb-6">
                         <h2 class="text-2xl font-display font-bold text-dark/70 flex items-center">
                            <i class="fa fa-history text-dark/50 mr-3"></i>播放历史
                        </h2>
                        <span class="px-3 py-1 bg-gray-100 text-gray-700 rounded-full text-sm font-medium flex items-center">
                            <i class="fa fa-check-circle mr-2"></i><?php echo count($played_songs); ?> 首
                        </span>
                    </div>
                    
                    <div class="space-y-4" id="playedSongsContainer">
                        <?php if (count($played_songs) > 0): ?>
                            <?php foreach ($played_songs as $song): ?>
                                <div class="bg-gray-50 rounded-2xl p-4" data-song-id="<?php echo $song['id']; ?>" data-song-name="<?php echo htmlspecialchars($song['song_name']); ?>" data-artist="<?php echo htmlspecialchars($song['artist']); ?>">
                                    <div class="flex justify-between items-center">
                                        <div class="flex-1">
                                            <h3 class="font-semibold text-lg text-dark/60"><?php echo htmlspecialchars($song['song_name']); ?></h3>
                                            <p class="text-dark/50 text-sm">
                                                <i class="fa fa-microphone mr-1"></i><?php echo htmlspecialchars($song['artist']); ?>
                                            </p>
                                        </div>
                                        <div class="ml-4 flex items-center text-accent">
                                            <i class="fa fa-check-circle"></i>
                                            <span class="ml-2 text-sm">已播放</span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                             <div class="text-center py-8">
                                <i class="fa fa-history text-dark/20 text-4xl mb-3"></i>
                                <p class="text-dark/50">暂无已播放歌曲</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <!-- 页脚 -->
    <footer class="text-dark/60 py-8 mt-12 text-center text-sm">
        <p>© 2025 语之声点歌站 · Made with <i class="fa fa-heart text-secondary"></i></p>
    </footer>
    
    <!-- 提示框 -->
    <div id="toast" class="fixed bottom-6 right-6 bg-white text-dark px-6 py-4 rounded-xl shadow-cute transform translate-y-20 opacity-0 transition-all duration-300 flex items-center border-l-4 z-50">
        <div id="toast-icon-container" class="mr-3 p-2 rounded-full">
            <i id="toast-icon" class="text-xl"></i>
        </div>
        <span id="toastMessage">操作成功</span>
    </div>
    
    <script>
        // --- MODAL CONTROL ---
        function setupModal(buttonId, modalId, closeButtonIds) {
            const button = document.getElementById(buttonId);
            const modal = document.getElementById(modalId);
            if (!button || !modal) return;
            const modalBox = modal.querySelector('div');

            function openModal() {
                modal.classList.remove('hidden');
                setTimeout(() => {
                    modal.classList.add('bg-opacity-30');
                    if(modalBox) modalBox.classList.remove('opacity-0', '-translate-y-4');
                }, 10);
            }

            function closeModal() {
                modal.classList.remove('bg-opacity-30');
                if(modalBox) modalBox.classList.add('opacity-0', '-translate-y-4');
                setTimeout(() => modal.classList.add('hidden'), 300);
            }

            button.addEventListener('click', openModal);
            closeButtonIds.forEach(id => {
                const closeBtn = document.getElementById(id);
                if (closeBtn) closeBtn.addEventListener('click', closeModal);
            });
            modal.addEventListener('click', (e) => {
                if (e.target === modal) closeModal();
            });
        }
        
        setupModal('ruleBtn', 'ruleModal', ['closeRuleBtn', 'confirmRuleBtn']);
        setupModal('adminBtn', 'adminModal', ['closeAdminBtn']);
        // setupModal('', 'adminModal', ['closeAdminBtn']);
        // 监听“广播站”文本点击，计数达5次弹出管理员登录框
        const adminBtn = document.getElementById('adminBtn');
        const radioText = document.getElementById('radio-station-text');
        let clickCount = 0; // 点击计数器
        if (radioText) {
            radioText.addEventListener('click', function() {
                clickCount++; // 每次点击计数+1
                // 当点击满5次时，重置计数并弹出登录框
                if (clickCount >= 5) {
                    clickCount = 0; // 重置计数，避免重复触发
                    // 触发管理员登录模态框（复用原有模态框逻辑）
                    // const adminModal = document.getElementById('adminModal');
                    // const modalBox = adminModal.querySelector('div');
                    // adminModal.classList.remove('hidden');
                    // setTimeout(() => {
                    //     adminModal.classList.add('bg-opacity-30');
                    //     modalBox.classList.remove('opacity-0', '-translate-y-4');
                    // }, 10);
                    setTimeout(() => adminBtn.click(), 100);
                }
            });
        }

        const searchBtn = document.getElementById('searchSongBtn');
        if (searchBtn) {
            setupModal('searchSongBtn', 'searchModal', ['closeSearchBtn', 'cancelSearchBtn']);
        }


        // --- TOAST NOTIFICATION ---
        function showToast(message, isSuccess = true) {
            const toast = document.getElementById('toast');
            if (!toast) return;
            const messageEl = document.getElementById('toastMessage');
            const iconContainer = document.getElementById('toast-icon-container');
            const icon = document.getElementById('toast-icon');

            messageEl.textContent = message;
            toast.classList.remove('border-primary', 'border-danger');
            iconContainer.classList.remove('bg-primary/10', 'bg-danger/10');
            icon.className = 'fa text-xl'; // Reset classes

            if (isSuccess) {
                toast.classList.add('border-primary');
                iconContainer.classList.add('bg-primary/10');
                icon.classList.add('fa-check-circle', 'text-primary');
            } else {
                toast.classList.add('border-danger');
                iconContainer.classList.add('bg-danger/10');
                icon.classList.add('fa-times-circle', 'text-danger');
            }
            
            toast.classList.remove('translate-y-20', 'opacity-0');
            
            setTimeout(() => {
                toast.classList.add('translate-y-20', 'opacity-0');
            }, 3000);
        }

        // // --- VOTE ACTION ---
        // 投票功能（支持点赞/取消点赞切换）
        document.querySelectorAll('.vote-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const songId = this.getAttribute('data-id');
                const votedSongs = JSON.parse(localStorage.getItem('votedSongs') || '[]');
                const isVoted = votedSongs.includes(songId); // 判断是否已点赞
                
                if (isVoted) {
                    // 已点赞 → 取消点赞
                    fetch(`index.php?vote=${songId}&action=cancel`)
                        .then(response => {
                            if (response.ok) {
                                // 更新投票数（减1）
                                const countEl = document.querySelector(`.vote-count-${songId}`);
                                const currentCount = parseInt(countEl.textContent);
                                countEl.textContent = currentCount - 1;
                                showToast('已取消点赞~');
                                // 从本地存储移除该歌曲ID
                                const index = votedSongs.indexOf(songId);
                                votedSongs.splice(index, 1);
                                localStorage.setItem('votedSongs', JSON.stringify(votedSongs));
                                // 恢复按钮状态
                                this.classList.remove('opacity-50', 'cursor-not-allowed', 'text-secondary');
                                this.classList.add('text-secondary');
                            } else {
                                showToast('取消点赞失败', false);
                            }
                        })
                        .catch(error => {
                            showToast('网络出错了~', false);
                        });
                } else {
                    // 未点赞 → 点赞
                    fetch(`index.php?vote=${songId}&action=add`)
                        .then(response => {
                            if (response.ok) {
                                // 更新投票数（加1）
                                const countEl = document.querySelector(`.vote-count-${songId}`);
                                const currentCount = parseInt(countEl.textContent);
                                countEl.textContent = currentCount + 1;
                                showToast('谢谢你的小心心！');
                                // 记录到本地存储
                                votedSongs.push(songId);
                                localStorage.setItem('votedSongs', JSON.stringify(votedSongs));
                                // 禁用按钮（标记已点赞）
                                this.classList.add('opacity-50', 'cursor-not-allowed');
                            } else {
                                showToast('投票失败了', false);
                            }
                        })
                        .catch(error => {
                            showToast('网络出错了~', false);
                        });
                }
            });
        });
        //ver2
        // document.querySelectorAll('.vote-btn').forEach(btn => {
            // btn.addEventListener('click', function() {
                // const songId = this.getAttribute('data-id');
                // // 检查是否已投票
                // const votedSongs = JSON.parse(localStorage.getItem('votedSongs') || '[]');
                // if (votedSongs.includes(songId)) {
                    // showToast('您已投过票啦，每人只能投一票哦', false);
                    // return;
                // }
                // // 未投票则执行投票操作
                // fetch(`index.php?vote=${songId}`)
                     // .then(response => {
                        // if (response.ok) {
                            // // 更新投票数
                            // const countEl = document.querySelector(`.vote-count-${songId}`);
                            // const currentCount = parseInt(countEl.textContent);
                            // countEl.textContent = currentCount + 1;
                            // showToast('谢谢你的小心心！');
                            // // 记录已投票歌曲ID到本地存储
                            // votedSongs.push(songId);
                            // localStorage.setItem('votedSongs', JSON.stringify(votedSongs));
                            // // 禁用当前投票按钮
                            // //this.disabled = true;
                            // this.classList.add('opacity-50', 'cursor-not-allowed');
                        // } else {
                            // showToast('投票失败了', false);
                        // }
                     // })
                    // .catch(error => {
                        // showToast('网络出错了~', false);
                        // console.error('Error:', error);
                    // });
            // });
        // });
        //ver1
        // document.querySelectorAll('.vote-btn').forEach(btn => {
            // btn.addEventListener('click', function() {
                // this.classList.add('scale-90');
                // setTimeout(() => this.classList.remove('scale-90'), 200);
                
                // const songId = this.getAttribute('data-id');
                // const countEl = document.querySelector(`.vote-count-${songId}`);

                // // Optimistic UI update
                // if (countEl) {
                    // countEl.textContent = parseInt(countEl.textContent) + 1;
                // }

                // fetch(`index.php?vote=${songId}`)
                    // .then(response => {
                        // if (!response.ok) {
                           // showToast('投票失败了', false);
                           // // Revert optimistic update on failure
                           // if (countEl) {
                               // countEl.textContent = parseInt(countEl.textContent) - 1;
                           // }
                        // } else {
                           // showToast('谢谢你的小心心！');
                        // }
                    // })
                    // .catch(() => {
                        // showToast('网络出错了~', false);
                        // if (countEl) {
                           // countEl.textContent = parseInt(countEl.textContent) - 1;
                        // }
                    // });
            // });
        // });
        
        // --- FORM SUBMISSIONS ---
    	document.getElementById('requestForm')?.addEventListener('submit', (e) => {
        	const submitBtn = document.getElementById('submit');
        
        	// 如果按钮不存在或已经被禁用，则直接返回
        	if (!submitBtn || submitBtn.disabled) return;
        
        	e.preventDefault();
        	const form = e.target;
        	const formData = new FormData(form);
        
        	// 禁用提交按钮，防止重复提交
        	submitBtn.disabled = true;
        	// 可以修改按钮文本提示用户正在提交
        	const originalText = submitBtn.textContent;
        	submitBtn.textContent = '提交中...';
        
        	fetch(form.action, { method: 'POST', body: formData })
            	.then(res => res.json())
            	.then(data => {
                	if (data.success) {
                    	showToast(data.message || '点歌成功！');
                    	setTimeout(() => location.reload(), 1500);
               	} else {
                    	showToast(data.message || '点歌失败了', false);
                    	// 失败时恢复按钮状态，允许用户重新尝试
                    	submitBtn.disabled = false;
                    	submitBtn.textContent = originalText;
                	}
            	})
            	.catch(() => {
                	showToast('提交失败，网络出错了~', false);
                	// 出错时恢复按钮状态，允许用户重新尝试
                	submitBtn.disabled = false;
                	submitBtn.textContent = originalText;
            	});
    	});
    
        //admin login
        document.getElementById('adminLoginForm')?.addEventListener('submit', (e) => {
            e.preventDefault();
            const form = e.target;
            const formData = new FormData(form);
            fetch(form.action, { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showToast(data.message || '登录成功！');
                        setTimeout(() => window.location.href = 'admin.php', 1500);
                    } else {
                        showToast(data.message || '登录失败', false);
                    }
                })
                .catch(() => showToast('登录失败，网络出错了~', false));
        });

        // --- SONG SEARCH ---
        function displaySearchResults(songs) {
            const searchResultsEl = document.getElementById('searchResults');
            if (!searchResultsEl) return;
            searchResultsEl.innerHTML = '';
            
            if (!songs || songs.length === 0) {
                 searchResultsEl.innerHTML = `<div class="text-center py-8 text-dark/50">
                    <i class="fa fa-frown-o text-3xl mb-2"></i>
                    <p>没有找到这首歌哦，换个关键词试试？</p>
                </div>`;
                return;
            }

            songs.forEach(song => {
                const songItem = document.createElement('div');
                songItem.className = 'p-3 md:p-4 border-b border-gray-100 hover:bg-light cursor-pointer transition-colors flex items-center space-x-4';
                songItem.innerHTML = `
                    <img src="${song.cover || `https://via.placeholder.com/48/a78bfa/ffffff?text=${song.song.charAt(0)}`}" alt="${song.song}" class="w-12 h-12 object-cover rounded-lg">
                    <div class="flex-1 min-w-0">
                        <h4 class="font-bold text-dark truncate">${song.song}</h4>
                        <p class="text-dark/60 text-sm"><i class="fa fa-microphone mr-1"></i>${song.singer}</p>
                    </div>
                    <button class="select-song-btn px-4 py-2 bg-primary/10 text-primary rounded-full text-sm hover:bg-primary/20 transition-all" 
                            data-name="${song.song}" data-artist="${song.singer}">
                        选择
                    </button>
                `;
                
                searchResultsEl.appendChild(songItem);
            });

            // 修改选择歌曲按钮的点击事件（原搜索结果处理逻辑）+（新增已播放列表重复检测）
            document.querySelectorAll('.select-song-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const selectedSong = this.getAttribute('data-name');
                    const selectedArtist = this.getAttribute('data-artist');
                    let duplicateItem = null;  //存储未播放的重复歌曲信息
                    let duplicateItem2 = null;  //存储已播放的重复歌曲信息
            
                    // 检查待播放列表中是否存在相同歌曲（精确匹配）
                    document.querySelectorAll('#pendingSongsContainer > div').forEach(songElement => {
                        const songName = songElement.getAttribute('data-song-name').trim();
                        const artist = songElement.getAttribute('data-artist').trim();
                        
                        if (songName === selectedSong && artist === selectedArtist) {
                            duplicateItem = songElement; // 记录重复项
                        }
                    });
                    // 检查已播放
                    document.querySelectorAll('#playedSongsContainer > div').forEach(songElement => {
                        const songName = songElement.getAttribute('data-song-name').trim();
                        const artist = songElement.getAttribute('data-artist').trim();
                        
                        if (songName === selectedSong && artist === selectedArtist) {
                            duplicateItem2 = songElement; // 记录重复项
                        }
                    });
                    if (duplicateItem) {
                        // 1. 显示提示
                        showToast(`这首歌有人点过了呦，快去投票吧`, false);
                        // 2. 关闭搜索模态框
                        document.getElementById('closeSearchBtn').click();
                        // 3. 清空已输入的歌手（保持原有逻辑）
                        document.getElementById('artist').value = '';
                        // 4. 自动滚动到重复歌曲位置（平滑滚动）
                        duplicateItem.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        // 5. 高亮闪烁效果（增强视觉提示）
                        duplicateItem.classList.add('ring-2', 'ring-secondary', 'animate-pulse');
                        setTimeout(() => {
                            duplicateItem.classList.remove('ring-2', 'ring-secondary', 'animate-pulse');
                        }, 2000);
                        
                    } 
                    else if (duplicateItem2) {
                        // 1. 显示提示
                        showToast(`这首歌已经放过了，换首歌吧！`, false);
                        // 2. 关闭搜索模态框
                        document.getElementById('closeSearchBtn').click();
                        // 3. 清空已输入的歌手（保持原有逻辑）
                        document.getElementById('artist').value = '';
                        // 4. 自动滚动到重复歌曲位置（平滑滚动）
                        duplicateItem2.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        // 5. 高亮闪烁效果（增强视觉提示）
                        duplicateItem2.classList.add('ring-2', 'ring-danger', 'animate-pulse');
                        setTimeout(() => {
                            duplicateItem2.classList.remove('ring-2', 'ring-danger', 'animate-pulse');
                        }, 2000);
                    }
                    else {
                        // 非重复歌曲，正常选中（原有逻辑）
                        document.getElementById('song_name_display').value = selectedSong;
                        document.getElementById('song_name').value = selectedSong;
                        document.getElementById('artist').value = selectedArtist;
                        document.getElementById('closeSearchBtn').click();
                        showToast('歌曲已选中！');
                    }
                });
            });
        }
        const searchInput = document.getElementById('song_name_display');
        if (searchBtn && searchInput) {
            searchBtn.addEventListener('click', function() {
                const keyword = searchInput.value.trim();
                if (!keyword) {
                    showToast('要先输入想搜的歌名哦', false);
                    return;
                }
                const searchResultsEl = document.getElementById('searchResults');
                searchResultsEl.innerHTML = '<div class="text-center py-8"><i class="fa fa-spinner fa-spin text-2xl text-primary"></i></div>';

                fetch(`https://api.vkeys.cn/v2/music/tencent?word=${encodeURIComponent(keyword)}`)
                    .then(res => res.json())
                    .then(data => {
                         if (data.code === 200) {
                            displaySearchResults(data.data);
                        } else {
                            displaySearchResults([]);
                        }
                    })
                    .catch(() => showToast('搜索失败，网络出错了~', false));
            });
        }
        
        // 待播放列表搜索功能
        document.getElementById('pendingSongSearch')?.addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase().trim();
            const songItems = document.querySelectorAll('#pendingSongsContainer > div');
        
            songItems.forEach(item => {
                const songName = item.getAttribute('data-song-name').toLowerCase();
                const artist = item.getAttribute('data-artist').toLowerCase();
                const isMatch = songName.includes(searchTerm) || artist.includes(searchTerm);
                
                // 显示匹配项，隐藏不匹配项
                item.style.display = isMatch ? 'block' : 'none';
            });
        }); 
        
        // --- INITIALIZATION ---
        document.addEventListener('DOMContentLoaded', () => {
            const ruleBtn = document.getElementById('ruleBtn');
            if (!localStorage.getItem('ruleRead') && ruleBtn) {
                setTimeout(() => ruleBtn.click(), 1000);
            }
            // 初始化投票按钮状态（支持取消点赞）
            const votedSongs = JSON.parse(localStorage.getItem('votedSongs') || '[]');
            votedSongs.forEach(songId => {
                const btn = document.querySelector(`.vote-btn[data-id="${songId}"]`);
                if (btn) {
                    // 已点赞的按钮添加特殊样式（可选，如变色）
                    btn.classList.add('opacity-50', 'cursor-not-allowed', 'text-secondary');
                }
            });
        });
        const confirmBtn = document.getElementById('confirmRuleBtn');
        if (confirmBtn) {
            confirmBtn.addEventListener('click', () => {
                localStorage.setItem('ruleRead', 'true');
            });
        }
    </script>
</body>
</html>