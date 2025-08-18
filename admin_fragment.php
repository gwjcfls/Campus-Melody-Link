<?php
// 返回按需加载的管理面板片段，通过 GET 参数 ?section=song-management|announcement-management|rule-management|log-management
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    echo '未授权';
    exit;
}
$section = $_GET['section'] ?? '';
require_once 'db_connect.php';
$admin_role = $_SESSION['admin_role'] ?? 'admin';

// 输出对应片段的 HTML（注意：仅返回面板内部的 HTML，不包含完整页面）
ob_start();
if ($section === 'song-management') {
    // 获取所有歌曲
    $stmt = $pdo->prepare("SELECT * FROM song_requests ORDER BY played ASC, votes DESC, created_at ASC");
    $stmt->execute();
    $songs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>
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
                <option value="mark-played">批量标记为已播放</option>
                <option value="mark-unplayed">批量标记为待播放</option>
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
    <?php
} elseif ($section === 'announcement-management') {
    $stmt = $pdo->prepare("SELECT * FROM announcements ORDER BY created_at DESC LIMIT 1");
    $stmt->execute();
    $announcement = $stmt->fetch(PDO::FETCH_ASSOC);
    ?>
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
    <?php
} elseif ($section === 'rule-management') {
    $stmt = $pdo->prepare("SELECT * FROM rules ORDER BY updated_at DESC LIMIT 1");
    $stmt->execute();
    $rule = $stmt->fetch(PDO::FETCH_ASSOC);
    ?>
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
    <?php
} elseif ($section === 'log-management') {
    if ($admin_role !== 'super_admin') {
        http_response_code(403);
        echo '未授权';
        exit;
    }
    $log_stmt = $pdo->prepare("SELECT * FROM operation_logs ORDER BY created_at DESC LIMIT 100");
    $log_stmt->execute();
    $logs = $log_stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>
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
    <?php
} else {
    http_response_code(400);
    echo '无效的section';
}

$html = ob_get_clean();
// 返回HTML
header('Content-Type: text/html; charset=utf-8');
echo $html;
