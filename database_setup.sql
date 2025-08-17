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