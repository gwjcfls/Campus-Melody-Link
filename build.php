<?php
// 引入现有词库（直接加载PHP数组）
require_once 'badwords.php';
$trie = [];

// 遍历词库数组构建前缀树
foreach ($badword as $word) {
    $word = trim($word);
    if (empty($word)) continue; // 跳过空值
    $node = &$trie;
    // 按UTF-8字符拆分（支持中文等多字节字符）
    $chars = preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY);
    foreach ($chars as $char) {
        if (!isset($node[$char])) {
            $node[$char] = [];
        }
        $node = &$node[$char];
    }
    $node['end'] = true; // 标记敏感词结束
}

// 保存前缀树缓存（后续直接加载，无需重复解析原词库）
file_put_contents('trie_cache.php', '<?php return ' . var_export($trie, true) . ';');
echo "前缀树生成完成，共处理 " . count($badword) . " 个敏感词";
?>
