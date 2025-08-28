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

        <!-- 桌面表格（大屏显示） -->
        <div class="hidden lg:block">
                <table class="table-fixed w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">选择</th>
                            <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">歌曲</th>
                            <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">点歌人</th>
                            <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">班级</th>
                            <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">点歌时间</th>
                            <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">投票</th>
                            <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">留言</th>
                            <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">状态</th>
                            <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">操作</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200" id="song-table-body">
                    <?php foreach ($songs as $song): ?>
                        <tr class="hover:bg-gray-50 transition-all">
                            <td class="px-3 py-2 whitespace-normal break-words">
                                <input type="checkbox" class="song-checkbox w-4 h-4 text-primary rounded border-gray-300 focus:ring-primary" 
                                       data-id="<?php echo $song['id']; ?>">
                            </td>
                                                
                            <td class="px-3 py-2 whitespace-normal break-words">
                                <div class="font-medium text-gray-900"><?php echo htmlspecialchars($song['song_name']); ?></div>
                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($song['artist']); ?></div>
                            </td>
                            <td class="px-3 py-2 whitespace-normal break-words">
                                <div class="text-sm text-gray-900"><?php echo htmlspecialchars($song['requestor']); ?></div>
                            </td>
                            <td class="px-3 py-2 whitespace-normal break-words">
                                <div class="text-sm text-gray-900"><?php echo htmlspecialchars($song['class']); ?></div>
                            </td>
                            <td class="px-3 py-2 whitespace-normal break-words">
                                <div class="text-xs text-gray-500 song-time" title="提交时间">
                                    <?php echo htmlspecialchars($song['created_at']); ?>
                                </div>
                            </td>
                            <td class="px-3 py-2 whitespace-normal break-words">
                                <div class="text-sm text-gray-900 flex items-center">
                                    <i class="fa fa-thumbs-up text-primary mr-1"></i>
                                    <span><?php echo $song['votes']; ?></span>
                                </div>
                            </td>
                            <td class="px-3 py-2 whitespace-normal break-words">
                                <div class="text-sm text-gray-900 song-message max-w-xs truncate" title="<?php echo htmlspecialchars($song['message'] ?? ''); ?>">
                                    <?php echo htmlspecialchars($song['message'] ?? ''); ?>
                                </div>
                            </td>
                            <td class="px-3 py-2 whitespace-normal break-words">
                                <?php if ($song['played']): ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">已播放</span>
                                <?php else: ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">待播放</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-2 whitespace-normal break-words text-sm text-gray-500">
                                <div class="flex flex-wrap items-center gap-2">
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

    <!-- 小屏卡片堆叠（移动/窄屏显示） -->
    <div id="song-card-list" class="space-y-4 lg:hidden">
        <?php foreach ($songs as $song): ?>
            <div class="bg-white rounded-lg shadow p-4">
                <div class="flex items-start space-x-3">
                    <div class="flex-shrink-0">
                        <input type="checkbox" class="song-checkbox w-4 h-4 text-primary rounded border-gray-300 focus:ring-primary" data-id="<?php echo $song['id']; ?>">
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-base font-medium text-gray-900 mb-1 max-h-16 overflow-y-auto"><?php echo htmlspecialchars($song['song_name']); ?></div>
                        <div class="text-sm text-gray-500 max-h-12 overflow-y-auto">歌手：<?php echo htmlspecialchars($song['artist']); ?></div>
                        <div class="text-sm text-gray-700 mt-2 grid grid-cols-2 gap-2">
                            <div class="text-sm text-gray-900 max-h-12 overflow-y-auto">点歌人：<?php echo htmlspecialchars($song['requestor']); ?></div>
                            <div class="text-sm text-gray-900 max-h-12 overflow-y-auto">班级：<?php echo htmlspecialchars($song['class']); ?></div>
                        </div>
                        <div class="text-xs text-gray-500 mt-2 song-time">时间：<?php echo htmlspecialchars($song['created_at']); ?></div>
                        <div class="text-sm text-gray-900 mt-1 song-message">留言：<?php echo htmlspecialchars($song['message'] ?? ''); ?></div>
                    </div>
                </div>
                <div class="mt-3 flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <div class="text-sm text-gray-700 flex items-center">
                            <i class="fa fa-thumbs-up text-primary mr-1"></i>
                            <span><?php echo $song['votes']; ?></span>
                        </div>
                        <div class="text-sm">
                            <?php if ($song['played']): ?>
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">已播放</span>
                            <?php else: ?>
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">待播放</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="flex items-center space-x-2">
                        <?php if ($admin_role === 'super_admin'): ?>
                            <button class="edit-song p-1.5 rounded bg-blue-100 text-blue-600 hover:bg-blue-200 transition-all" data-id="<?php echo $song['id']; ?>"><i class="fa fa-pencil"></i></button>
                            <button class="delete-song p-1.5 rounded bg-red-100 text-red-600 hover:bg-red-200 transition-all" data-id="<?php echo $song['id']; ?>"><i class="fa fa-trash"></i></button>
                        <?php endif; ?>
                        <?php if ($song['played']): ?>
                            <button class="mark-unplayed p-1.5 rounded bg-yellow-100 text-yellow-600 hover:bg-yellow-200 transition-all" data-id="<?php echo $song['id']; ?>"><i class="fa fa-undo"></i></button>
                        <?php else: ?>
                            <button class="mark-played p-1.5 rounded bg-green-100 text-green-600 hover:bg-green-200 transition-all" data-id="<?php echo $song['id']; ?>"><i class="fa fa-check"></i></button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
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
} elseif ($section === 'time-management') {
    // 读取当前设置（最新一条，id 最大）
    $stmt = $pdo->prepare("SELECT * FROM time_settings ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$settings) { $settings = ['mode'=>'manual','manual_request_enabled'=>1,'manual_vote_enabled'=>1,'request_limit'=>1,'vote_limit'=>3]; }
    // 读取时间规则
    $rules = $pdo->query("SELECT * FROM time_rules ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-dark flex items-center">
                <i class="fa fa-clock-o text-primary mr-2"></i>点歌时间设置
            </h2>
        </div>

        <!-- 参数说明 -->
        <div class="mb-5 p-4 rounded-lg border border-blue-200 bg-blue-50 text-sm leading-6">
            <div class="font-semibold text-blue-700 mb-1">功能参数说明</div>
            <ul class="list-disc pl-5 text-blue-800 space-y-1">
                <li><span class="font-medium">模式</span>：
                    <span class="font-medium">手动</span> 直接通过“手动：允许点歌/投票”开关控制是否开放；
                    <span class="font-medium">自动</span> 则根据下方“时间规则”自动在指定时段开放。
                </li>
                <li><span class="font-medium">手动：允许点歌/投票</span>：仅在手动模式下生效，对应功能是否开放。</li>
                <li><span class="font-medium">点歌次数上限 / 投票次数上限</span>：限制用户每日分别的次数。</li>
                <li><span class="font-medium">总次数上限（合并）</span>：可选。填写后以“总次数”为准，忽略分别的点歌/投票上限；留空则不启用合并上限。</li>
                <li><span class="font-medium">设置保存方式</span>：采用追加方式保存，系统总是读取最新一条设置，所有修改会记录到操作日志。</li>
                <li><span class="font-medium">次数刷新说明</span>：
                    <ul class="list-disc pl-5 mt-1 space-y-1">
                        <li>
                            合并模式（填写了“总次数上限”）下：当点歌或投票由关闭切换为开启（手动或按规则）时，将统一清空本地“已点赞歌曲”，并将点歌/投票/总次数重置为 0。
                        </li>
                        <li>
                            非合并模式（未填写“总次数上限”）下：
                            <div class="mt-1">
                                <div>• 点歌由关→开：仅重置点歌次数；</div>
                                <div>• 投票由关→开：重置投票次数，并清空本地“已点赞歌曲”。</div>
                            </div>
                        </li>
                        <li>即使用户在开启后才进入页面，也会识别为进入新的开放窗口并按上述规则执行刷新。</li>
                        <li>每进入新一轮开放窗口（例如 open → close → open 的下一次 open）都会按上述规则刷新本地次数。</li>
                        <li>刷新仅影响用户浏览器的本地计数与已点赞标记，不会修改数据库中的歌曲票数。</li>
                    </ul>
                </li>
            </ul>
        </div>

        <!-- 模式与限额设置 -->
        <form id="time-settings-form" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">模式</label>
                <select name="mode" class="px-3 py-2 border rounded-md">
                    <option value="manual" <?php echo ($settings['mode']??'manual')==='manual'?'selected':''; ?>>手动</option>
                    <option value="auto" <?php echo ($settings['mode']??'manual')==='auto'?'selected':''; ?>>自动</option>
                </select>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <label class="flex items-center space-x-2"><input type="checkbox" name="manual_request_enabled" value="1" <?php echo !empty($settings['manual_request_enabled'])?'checked':''; ?>><span>手动：允许点歌</span></label>
                <label class="flex items-center space-x-2"><input type="checkbox" name="manual_vote_enabled" value="1" <?php echo !empty($settings['manual_vote_enabled'])?'checked':''; ?>><span>手动：允许投票</span></label>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm mb-1">点歌次数上限</label>
                    <input type="number" min="0" max="100" name="request_limit" value="<?php echo (int)($settings['request_limit']??1); ?>" class="px-3 py-2 border rounded-md w-40">
                </div>
                <div>
                    <label class="block text-sm mb-1">投票次数上限</label>
                    <input type="number" min="0" max="100" name="vote_limit" value="<?php echo (int)($settings['vote_limit']??3); ?>" class="px-3 py-2 border rounded-md w-40">
                </div>
            </div>
            <div class="mt-2">
                <label class="block text-sm mb-1">总次数上限（点歌+投票合并）</label>
                <?php $cl = isset($settings['combined_limit']) ? $settings['combined_limit'] : null; ?>
                <input type="number" min="0" max="200" name="combined_limit" value="<?php echo ($cl !== null) ? (int)$cl : ''; ?>" placeholder="留空=不启用合并上限" class="px-3 py-2 border rounded-md w-40">
                <p class="text-xs text-gray-500 mt-1">留空则分别使用“点歌/投票”上限；填写后两项将忽略，按总上限计算。</p>
            </div>
            <div class="flex justify-end">
                <div class="flex gap-3">
                    <button type="button" id="force-reset-btn" class="px-4 py-2 bg-secondary text-white rounded-md" title="强制让前端用户刷新本地次数与已点赞标记">强制刷新用户本地次数</button>
                    <button type="submit" class="px-6 py-2 bg-primary text-white rounded-md">保存设置</button>
                </div>
            </div>
            <p class="mt-2 text-xs text-gray-500 leading-6">
                说明：点击“强制刷新”后，所有用户浏览器会在下一次状态刷新或页面刷新时，清空本地记录的“已点赞歌曲”和点歌/投票次数计数，从 0 重新开始计算。
            </p>
        </form>

        <!-- 规则列表与新增 -->
        <div class="mt-8">
            <h3 class="text-lg font-bold mb-3">自动模式规则</h3>
            <div class="text-xs text-gray-600 mb-3 leading-6">
                <div>类型说明：</div>
                <ol class="list-decimal pl-5 space-y-1">
                    <li>每日固定时间：每天在“开始-结束时间”内开放（无需填写周几）。</li>
                    <li>每周跨天：从“起始周(含)+时间”到“结束周(不含)+时间”，可跨周。</li>
                    <li>每周周几范围：选择“起始周-结束周”范围，范围内每天按“开始-结束时间”开放。</li>
                </ol>
                <div class="mt-2">表单会根据类型自动显示/禁用不需要的字段；禁用字段会以“/”占位。</div>
            </div>
            <form id="add-rule-form" class="grid md:grid-cols-6 gap-3 items-end">
                <div>
                    <label class="block text-xs mb-1">功能</label>
                    <select name="feature" class="px-2 py-2 border rounded-md">
                        <option value="both">点歌+投票</option>
                        <option value="request">仅点歌</option>
                        <option value="vote">仅投票</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs mb-1">类型</label>
                    <select name="type" class="px-2 py-2 border rounded-md">
                        <option value="1">每日固定时间</option>
                        <option value="2">每周跨天</option>
                        <option value="3">每周周几范围</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs mb-1">起始周(1-7)</label>
                    <input type="number" name="start_weekday" min="1" max="7" class="px-2 py-2 border rounded-md w-28">
                </div>
                <div>
                    <label class="block text-xs mb-1">结束周(1-7)</label>
                    <input type="number" name="end_weekday" min="1" max="7" class="px-2 py-2 border rounded-md w-28">
                </div>
                <div>
                    <label class="block text-xs mb-1">开始时间(HH:MM)</label>
                    <input type="time" name="start_time" class="px-2 py-2 border rounded-md w-36">
                </div>
                <div>
                    <label class="block text-xs mb-1">结束时间(HH:MM)</label>
                    <input type="time" name="end_time" class="px-2 py-2 border rounded-md w-36">
                </div>
                <div class="md:col-span-5">
                    <input type="text" name="description" placeholder="规则备注（可选）" class="px-3 py-2 border rounded-md w-full">
                </div>
                <div>
                    <button type="submit" class="px-4 py-2 bg-primary text-white rounded-md w-full">新增规则</button>
                </div>
            </form>

            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-xs md:text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-2 py-2 text-left">ID</th>
                            <th class="px-2 py-2 text-left">功能</th>
                            <th class="px-2 py-2 text-left">类型</th>
                            <th class="px-2 py-2 text-left">起始周</th>
                            <th class="px-2 py-2 text-left">结束周</th>
                            <th class="px-2 py-2 text-left">开始</th>
                            <th class="px-2 py-2 text-left">结束</th>
                            <th class="px-2 py-2 text-left">状态</th>
                            <th class="px-2 py-2 text-left">备注</th>
                            <th class="px-2 py-2 text-left">操作</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200" id="rule-table-body">
                        <?php foreach ($rules as $r): ?>
                            <tr>
                                <td class="px-2 py-2"><?php echo $r['id']; ?></td>
                                <td class="px-2 py-2"><?php echo htmlspecialchars($r['feature']); ?></td>
                                <td class="px-2 py-2"><?php echo (int)$r['type']; ?></td>
                                <?php 
                                    $t = strval($r['type']);
                                    $sw = ($t === '1') ? '/' : (trim((string)$r['start_weekday']) !== '' ? $r['start_weekday'] : '/');
                                    $ew = ($t === '1') ? '/' : (trim((string)$r['end_weekday']) !== '' ? $r['end_weekday'] : '/');
                                    $st = ($t === '3') ? '/' : (trim((string)$r['start_time']) !== '' ? $r['start_time'] : '/');
                                    $et = ($t === '3') ? '/' : (trim((string)$r['end_time']) !== '' ? $r['end_time'] : '/');
                                ?>
                                <td class="px-2 py-2"><?php echo htmlspecialchars((string)$sw); ?></td>
                                <td class="px-2 py-2"><?php echo htmlspecialchars((string)$ew); ?></td>
                                <td class="px-2 py-2"><?php echo htmlspecialchars((string)$st); ?></td>
                                <td class="px-2 py-2"><?php echo htmlspecialchars((string)$et); ?></td>
                                <td class="px-2 py-2"><?php echo $r['active'] ? '<span class="text-green-600">启用</span>' : '<span class="text-gray-400">停用</span>'; ?></td>
                                <td class="px-2 py-2 max-w-xs truncate" title="<?php echo htmlspecialchars($r['description']); ?>"><?php echo htmlspecialchars($r['description']); ?></td>
                                <td class="px-2 py-2 whitespace-nowrap">
                                    <div class="flex items-center gap-2 flex-nowrap">
                                        <button class="edit-rule px-2 py-1 text-xs rounded bg-purple-50 text-purple-600" data-id="<?php echo $r['id']; ?>" data-feature="<?php echo htmlspecialchars($r['feature']); ?>" data-type="<?php echo (int)$r['type']; ?>" data-sw="<?php echo htmlspecialchars($r['start_weekday']); ?>" data-ew="<?php echo htmlspecialchars($r['end_weekday']); ?>" data-st="<?php echo htmlspecialchars($r['start_time']); ?>" data-et="<?php echo htmlspecialchars($r['end_time']); ?>" data-desc="<?php echo htmlspecialchars($r['description']); ?>">编辑</button>
                                        <button class="toggle-rule px-2 py-1 text-xs rounded bg-blue-50 text-blue-600" data-id="<?php echo $r['id']; ?>" data-active="<?php echo $r['active']?0:1; ?>"><?php echo $r['active']? '停用':'启用'; ?></button>
                                        <button class="delete-rule px-2 py-1 text-xs rounded bg-red-50 text-red-600" data-id="<?php echo $r['id']; ?>">删除</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($rules)): ?>
                            <tr><td colspan="10" class="px-2 py-6 text-center text-gray-400">暂无规则</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // 规则编辑弹窗
    const ruleModal = document.createElement('div');
    ruleModal.className = 'fixed inset-0 bg-black bg-opacity-40 z-50 hidden flex items-center justify-center';
        ruleModal.innerHTML = `
            <div class="bg-white rounded-lg shadow-xl w-full max-w-lg mx-4">
                <div class="flex justify-between items-center p-4 border-b"><h3 class="font-bold">编辑规则</h3><button id="close-rule-modal" class="text-gray-500">×</button></div>
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
        const closeRuleModal = () => ruleModal.classList.add('hidden');
        ruleModal.addEventListener('click', (e) => { if (e.target === ruleModal) closeRuleModal(); });
        ruleModal.querySelector('#close-rule-modal').addEventListener('click', closeRuleModal);
        ruleModal.querySelector('#er-cancel').addEventListener('click', closeRuleModal);

        function openRuleModalFromBtn(btn) {
            ruleModal.classList.remove('hidden');
            ruleModal.classList.add('flex');
            document.getElementById('er-id').value = btn.getAttribute('data-id');
            document.getElementById('er-feature').value = btn.getAttribute('data-feature') || 'both';
            document.getElementById('er-type').value = btn.getAttribute('data-type') || '1';
            document.getElementById('er-sw').value = btn.getAttribute('data-sw') || '';
            document.getElementById('er-ew').value = btn.getAttribute('data-ew') || '';
            document.getElementById('er-st').value = (btn.getAttribute('data-st') || '').slice(0,5);
            document.getElementById('er-et').value = (btn.getAttribute('data-et') || '').slice(0,5);
            document.getElementById('er-desc').value = btn.getAttribute('data-desc') || '';
        }
        // 保存设置
        document.getElementById('time-settings-form')?.addEventListener('submit', function(e) {
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

        // 强制刷新本地次数按钮
        document.getElementById('force-reset-btn')?.addEventListener('click', async function() {
            const btn = this;
            const ok = await showConfirmToast('将强制清空本地“已点赞”和次数计数，确定执行？', { confirmText: '执行', cancelText: '取消', tone: 'warning' });
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

        // 新增规则
        document.getElementById('add-rule-form')?.addEventListener('submit', function(e) {
            e.preventDefault();
            const fd = new FormData(this);
            fd.set('action','add_rule');
            fetch('time_config.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(d => { if (d.success) { showSuccessToast('已新增规则'); } else { showSuccessToast(d.message||'操作失败', false); } })
                .catch(() => showSuccessToast('网络错误', false));
        });

        // 切换/删除规则
    document.querySelectorAll('.edit-rule').forEach(btn => btn.addEventListener('click', () => openRuleModalFromBtn(btn)));
    document.querySelectorAll('.toggle-rule').forEach(btn => btn.addEventListener('click', () => {
            const fd = new FormData();
            fd.set('action','toggle_rule');
            fd.set('id', btn.getAttribute('data-id'));
            fd.set('active', btn.getAttribute('data-active'));
            fetch('time_config.php', { method: 'POST', body: fd })
                .then(r => r.json()).then(d => { if (d.success) showSuccessToast('已更新'); else showSuccessToast(d.message||'失败', false); });
        }));
        document.querySelectorAll('.delete-rule').forEach(btn => btn.addEventListener('click', async () => {
            const ok = await showConfirmToast('确定删除该规则？此操作不可恢复。', { confirmText: '删除', cancelText: '取消', tone: 'danger' });
            if (!ok) return;
            const fd = new FormData();
            fd.set('action','delete_rule');
            fd.set('id', btn.getAttribute('data-id'));
            fetch('time_config.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(d => { if (d.success) { btn.closest('tr')?.remove(); showSuccessToast('规则已删除'); } else showSuccessToast(d.message||'失败', false); });
        }));

        // 提交编辑规则
        document.getElementById('edit-rule-form')?.addEventListener('submit', function(e) {
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
                            tr.querySelector('td:nth-child(4)').textContent = rule.start_weekday ?? '';
                            tr.querySelector('td:nth-child(5)').textContent = rule.end_weekday ?? '';
                            tr.querySelector('td:nth-child(6)').textContent = rule.start_time ?? '';
                            tr.querySelector('td:nth-child(7)').textContent = rule.end_time ?? '';
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
                        showSuccessToast('已保存');
                        closeRuleModal();
                    } else {
                        showSuccessToast('保存成功');
                        closeRuleModal();
                    }
                })
                .catch(() => showSuccessToast('网络错误', false));
        });
    </script>
    <?php
} elseif ($section === 'log-management') {
    if ($admin_role !== 'super_admin') {
        http_response_code(403);
        echo '未授权';
        exit;
    }
    $log_stmt = $pdo->prepare("SELECT * FROM operation_logs ORDER BY created_at DESC LIMIT 50");
    $log_stmt->execute();
    $logs = $log_stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-dark flex items-center">
                <i class="fa fa-file-text-o text-primary mr-2"></i>操作日志
            </h2>
        </div>
        <!-- 日志字段说明 -->
        <div class="mb-5 p-4 rounded-lg border border-blue-200 bg-blue-50 text-sm leading-6">
            <div class="font-semibold text-blue-700 mb-1">日志字段说明</div>
            <ul class="list-disc pl-5 text-blue-800 space-y-1">
                <li><code>mode</code>：模式（manual=手动，auto=自动）</li>
                <li><code>mre</code>：手动允许点歌（0=否，1=是）</li>
                <li><code>mve</code>：手动允许投票（0=否，1=是）</li>
                <li><code>rl</code>：点歌次数上限（整数）</li>
                <li><code>vl</code>：投票次数上限（整数）</li>
                <li><code>cl</code>：总次数上限（合并；整数；空/未填=未启用）</li>
                <li><code>feature</code>：功能（both=点歌+投票，request=仅点歌，vote=仅投票）</li>
                <li><code>type</code>：规则类型（1=每日固定时间，2=每周跨天，3=每周周几范围）</li>
                <li><code>start_weekday</code> / <code>end_weekday</code>：起始/结束周（1-7，周一=1）</li>
                <li><code>start_time</code> / <code>end_time</code>：开始/结束时间（HH:MM(:SS)）</li>
                <li><code>active</code>：规则状态（0=停用，1=启用）</li>
                <li><code>description</code>：备注</li>
            </ul>
        </div>
        
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-xs md:text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-2 py-2 text-left font-medium text-gray-500">时间</th>
                        <th class="px-2 py-2 text-left font-medium text-gray-500">用户</th>
                        <th class="px-2 py-2 text-left font-medium text-gray-500 hidden sm:table-cell">角色</th>
                        <th class="px-2 py-2 text-left font-medium text-gray-500">操作类型</th>
                        <th class="px-2 py-2 text-left font-medium text-gray-500">对象</th>
                        <th class="px-2 py-2 text-left font-medium text-gray-500">详情</th>
                        <th class="px-2 py-2 text-left font-medium text-gray-500 hidden sm:table-cell">IP</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($logs as $i => $log): ?>
                    <?php $raw = $log['details'] ?? ''; ?>
                    <tr class="log-row">
                        <td class="px-2 py-2 whitespace-normal sm:whitespace-nowrap"><?php echo htmlspecialchars($log['created_at']); ?></td>
                        <td class="px-2 py-2 whitespace-normal sm:whitespace-nowrap"><?php echo htmlspecialchars($log['user']); ?></td>
                        <td class="px-2 py-2 whitespace-normal sm:whitespace-nowrap hidden sm:table-cell"><?php echo htmlspecialchars($log['role']); ?></td>
                        <td class="px-2 py-2 whitespace-normal sm:whitespace-nowrap"><?php echo htmlspecialchars($log['action']); ?></td>
                        <td class="px-2 py-2 whitespace-normal sm:whitespace-nowrap"><?php echo htmlspecialchars($log['target']); ?></td>
                        <td class="px-2 py-2 align-top">
                            <button type="button" class="toggle-log-details text-primary hover:underline" aria-expanded="false" aria-label="展开">展开</button>
                        </td>
                        <td class="px-2 py-2 whitespace-normal sm:whitespace-nowrap hidden sm:table-cell"><?php echo htmlspecialchars($log['ip']); ?></td>
                    </tr>
                    <tr class="log-details-row hidden">
                        <td colspan="7" class="px-2 py-2 bg-gray-50">
                            <pre class="text-xs text-gray-800 whitespace-pre-wrap break-all m-0"><?php echo htmlspecialchars($raw); ?></pre>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if (empty($logs)): ?>
                <div class="text-gray-400 text-center py-8">暂无日志记录</div>
            <?php endif; ?>
        </div>
        <div class="text-gray-400 text-xs mt-2">仅显示最近50条操作日志</div>
    </div>
    <script>
    // 展开/收起日志详情，移动端显示优化
    document.querySelectorAll('.toggle-log-details').forEach((btn) => {
        btn.addEventListener('click', () => {
            const tr = btn.closest('tr');
            if (!tr) return;
            const detailsRow = tr.nextElementSibling;
            if (!detailsRow || !detailsRow.classList.contains('log-details-row')) return;
            const expanded = detailsRow.classList.toggle('hidden') ? false : true;
            btn.textContent = expanded ? '收起' : '展开';
            btn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            btn.setAttribute('aria-label', expanded ? '收起' : '展开');
        });
    });
    </script>
    <?php
} else {
    http_response_code(400);
    echo '无效的section';
}

$html = ob_get_clean();
// 返回HTML
header('Content-Type: text/html; charset=utf-8');
echo $html;
