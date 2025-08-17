# 日间/夜间模式切换功能说明

## 功能概述

为您的广播站点歌系统添加了完整的日间/夜间模式切换功能，无需数据库支持，使用浏览器本地存储保存用户偏好。

## 主要特性

- 🌞 **日间模式**: 明亮的浅色主题，适合白天使用
- 🌙 **夜间模式**: 深色主题，保护眼睛，适合夜间使用
- 💾 **自动保存**: 使用localStorage自动记住用户选择
- 🎨 **平滑动画**: 0.3秒的平滑切换动画
- 📱 **响应式设计**: 支持各种屏幕尺寸
- 🔄 **跨页面同步**: 所有页面共享主题设置

## 已添加主题切换的页面

1. **index.php** - 主页面（点歌页面）
2. **admin.php** - 管理后台页面
3. **test_theme.html** - 主题测试页面

## 使用方法

### 用户端
1. 在页面右上角找到主题切换按钮
2. 点击按钮即可在日间/夜间模式之间切换
3. 主题设置会自动保存，下次访问时自动应用

### 开发者端
如需在其他页面添加主题切换功能，请按以下步骤操作：

#### 1. 添加CSS样式
```css
/* 夜间模式样式 */
.dark-mode {
    background-color: #1a1a1a !important;
    color: #e5e5e5 !important;
}

.dark-mode .bg-white {
    background-color: #2d2d2d !important;
    color: #e5e5e5 !important;
    border-color: #404040 !important;
}

/* 主题切换动画 */
body {
    transition: background-color 0.3s ease, color 0.3s ease;
}

body * {
    transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease;
}
```

#### 2. 添加主题切换按钮
```html
<button id="themeToggle" class="px-3 py-2 rounded-full text-primary border border-primary/50 hover:bg-primary/10 transition-all">
    <i class="fa fa-sun-o mr-1" id="themeIcon"></i>
    <span id="themeText">日间模式</span>
</button>
```

#### 3. 添加JavaScript功能
```javascript
function toggleTheme() {
    const body = document.getElementById('body');
    const themeIcon = document.getElementById('themeIcon');
    const themeText = document.getElementById('themeText');
    const isDark = body.classList.contains('dark-mode');
    
    if (isDark) {
        body.classList.remove('dark-mode');
        themeIcon.className = 'fa fa-sun-o mr-1';
        themeText.textContent = '日间模式';
        localStorage.setItem('theme', 'light');
    } else {
        body.classList.add('dark-mode');
        themeIcon.className = 'fa fa-moon-o mr-1';
        themeText.textContent = '夜间模式';
        localStorage.setItem('theme', 'dark');
    }
}

function initTheme() {
    const savedTheme = localStorage.getItem('theme');
    const body = document.getElementById('body');
    const themeIcon = document.getElementById('themeIcon');
    const themeText = document.getElementById('themeText');
    
    if (savedTheme === 'dark') {
        body.classList.add('dark-mode');
        themeIcon.className = 'fa fa-moon-o mr-1';
        themeText.textContent = '夜间模式';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    initTheme();
    document.getElementById('themeToggle').addEventListener('click', toggleTheme);
});
```

## 颜色配置

### 日间模式颜色
- 主色调: `#a78bfa` (柔和紫)
- 背景色: `#e0f2fe` (天空浅蓝)
- 卡片色: `#ffffff` (纯白)
- 文字色: `#334155` (深蓝灰)

### 夜间模式颜色
- 主色调: `#a78bfa` (柔和紫)
- 背景色: `#1a1a1a` (深黑)
- 卡片色: `#2d2d2d` (深灰)
- 文字色: `#e5e5e5` (浅白)
- 边框色: `#404040` (中灰)

## 浏览器兼容性

- ✅ Chrome 60+
- ✅ Firefox 55+
- ✅ Safari 12+
- ✅ Edge 79+
- ✅ 移动端浏览器

## 注意事项

1. 主题设置保存在浏览器的localStorage中
2. 清除浏览器数据会重置主题设置
3. 不同浏览器/设备间的主题设置不会同步
4. 建议在页面加载完成后初始化主题

## 测试方法

1. 访问 `test_theme.html` 页面
2. 点击主题切换按钮测试功能
3. 刷新页面验证设置是否保存
4. 检查不同页面的主题是否同步

## 技术实现

- **存储方式**: localStorage
- **CSS框架**: Tailwind CSS
- **图标库**: Font Awesome
- **动画**: CSS transitions
- **JavaScript**: 原生ES6+

## 更新日志

- **v1.0.0**: 初始版本，支持基本的日间/夜间模式切换
- 支持主页面和管理后台的主题切换
- 添加平滑切换动画
- 自动保存用户偏好设置
