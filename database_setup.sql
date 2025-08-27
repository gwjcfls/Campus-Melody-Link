-- 创建歌曲请求表
CREATE TABLE IF NOT EXISTS song_requests (
    id SERIAL PRIMARY KEY,
    song_name VARCHAR(255) NOT NULL,
    artist VARCHAR(255) NOT NULL,
    requestor VARCHAR(255) NOT NULL,
    class VARCHAR(255) NOT NULL,
    message TEXT,
    votes INTEGER DEFAULT 0,
    played BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    played_at TIMESTAMP
);

-- 创建通知表
CREATE TABLE IF NOT EXISTS announcements (
    id SERIAL PRIMARY KEY,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 创建点歌规则表
CREATE TABLE IF NOT EXISTS rules (
    id SERIAL PRIMARY KEY,
    content TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 创建操作日志表
CREATE TABLE IF NOT EXISTS operation_logs (
    id SERIAL PRIMARY KEY,
    user VARCHAR(255), -- 操作用户
    role VARCHAR(50),  -- 用户角色（如admin、user、guest等）
    action VARCHAR(100) NOT NULL, -- 操作类型
    target VARCHAR(255), -- 操作对象（如歌曲id、规则id等）
    details TEXT,        -- 详细描述
    ip VARCHAR(45),     -- 操作IP
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 插入示例通知
INSERT INTO announcements (content) VALUES ('欢迎使用校园广播站点歌系统！');

-- 插入示例规则
INSERT INTO rules (content) VALUES ('校园广播站点歌规则：
1. 请遵守校园规章制度，不点播含有暴力、色情、反动等不良内容的歌曲；
2. 每天每位同学限点一首歌；
3. 广播时间为每天中午12:30-13:00和下午17:30-18:00；
4. 我们会优先播放投票数高的歌曲；
5. 如遇特殊情况，广播时间可能会调整，请以实际情况为准。');    

-- =============================
-- 点歌/投票时间限制相关表
-- =============================

-- 全局时间设置（最新 id 最大的即当前生效）
CREATE TABLE IF NOT EXISTS time_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mode ENUM('manual','auto') NOT NULL DEFAULT 'manual', -- 模式：手动/自动
    manual_request_enabled BOOLEAN NOT NULL DEFAULT TRUE,  -- 手动模式下点歌开关
    manual_vote_enabled BOOLEAN NOT NULL DEFAULT TRUE,     -- 手动模式下投票开关
    request_limit INT NOT NULL DEFAULT 1,                  -- 点歌次数上限（本地计数）
    vote_limit INT NOT NULL DEFAULT 3,                     -- 投票次数上限（本地计数）
    combined_limit INT NULL DEFAULT NULL,                  -- 合并总次数上限（NULL 表示未启用）
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 规则表
-- feature: request | vote | both
-- type: 1=每日固定时间; 2=每周跨天(起始周几+时间 至 结束周几+时间); 3=每周周几范围(每日固定开始/结束时间)
-- 周几约定：1=周一, 2=周二, ..., 7=周日（与PHP date('N')一致）
CREATE TABLE IF NOT EXISTS time_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    feature ENUM('request','vote','both') NOT NULL DEFAULT 'both',
    type TINYINT NOT NULL,
    start_weekday TINYINT NULL,
    end_weekday TINYINT NULL,
    start_time TIME NULL,
    end_time TIME NULL,
    active BOOLEAN NOT NULL DEFAULT TRUE,
    description VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 初始化 settings（若表为空则插入默认记录）
INSERT INTO time_settings (mode, manual_request_enabled, manual_vote_enabled, request_limit, vote_limit, combined_limit)
SELECT 'manual', TRUE, TRUE, 1, 3, NULL
WHERE NOT EXISTS (SELECT 1 FROM time_settings);