<?php
// 会话安全配置（放在session_start()之前）
ini_set('session.cookie_secure', 'On');
ini_set('session.cookie_httponly', 'On');
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.cookie_lifetime', 0);
ini_set('session.gc_maxlifetime', 3600);
ini_set('session.regenerate_id', 'On');
session_start();
require_once 'db_connect.php';

// 处理点歌请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // 获取表单数据
        $song_name = htmlspecialchars(trim($_POST['song_name']));
        $artist = htmlspecialchars(trim($_POST['artist']));
        $requestor = htmlspecialchars(trim($_POST['requestor']));
        $class = htmlspecialchars(trim($_POST['class']));
        $message = htmlspecialchars(trim($_POST['message']));
        
        // 验证数据
        if (empty($song_name) || empty($artist) || empty($requestor) || empty($class)) {
            throw new Exception("请填写必填字段");
        }

        // ##########################
        // 新增：敏感词检测逻辑
        // ##########################
        // 加载前缀树缓存（替代直接加载200万词数组）
        $trie = require 'trie_cache.php';
        
        // 敏感词检测函数（支持多字节字符）
        function hasBadWord($content, $trie) {
            $content = trim($content);
            if (empty($content)) return false; // 空留言不检测
            $length = mb_strlen($content, 'UTF-8');
            
            for ($i = 0; $i < $length; $i++) {
                $node = $trie;
                $j = $i;
                while (true) {
                    $currentChar = mb_substr($content, $j, 1, 'UTF-8');
                    if (!isset($node[$currentChar])) break; // 不匹配当前分支
                    $node = $node[$currentChar];
                    if (isset($node['end'])) {
                        return true; // 检测到敏感词
                    }
                    $j++;
                    if ($j >= $length) break; // 超出文本长度
                }
            }
            return false;
        }
        
        // 检查留言是否包含敏感词
        if (hasBadWord($message, $trie)) {
            throw new Exception("留言包含不适当内容，请修改后提交");
        }
        // ##########################
        // 敏感词检测逻辑结束
        // ##########################

        // 插入数据到数据库
        $stmt = $pdo->prepare("INSERT INTO song_requests (song_name, artist, requestor, class, message, votes, created_at) 
                              VALUES (:song_name, :artist, :requestor, :class, :message, 0, NOW())");
        
        $stmt->execute([
            'song_name' => $song_name,
            'artist' => $artist,
            'requestor' => $requestor,
            'class' => $class,
            'message' => $message
        ]);
        // 日志记录
        log_operation(
            $pdo,
            $requestor,
            'user',
            '点歌',
            $song_name,
            json_encode([
                'artist' => $artist,
                'class' => $class,
                'message' => $message
            ], JSON_UNESCAPED_UNICODE)
        );
        
        // 返回成功信息
        echo json_encode(['success' => true, 'message' => '点歌请求已提交，等待播放']);
    } catch (Exception $e) {
        // 返回错误信息（包含敏感词时会在这里返回提示）
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    // 非法请求
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => '方法不允许']);
}
?>
