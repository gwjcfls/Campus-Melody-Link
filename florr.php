<?php
$gameConfig = [
    'playerSpeed' => 5,
    'flowerPoints' => 10,
    'obstacleSpeed' => 3,
    'initialLives' => 3,
    'joystickSize' => 100 // 摇杆大小
];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Florr 游戏（带摇杆）</title>
    <style>
        canvas { border: 2px solid #333; background-color: #e6f7ff; }
        #scoreBoard { font-size: 20px; margin: 10px 0; }
        .joystick-container {
            position: fixed;
            bottom: 30px;
            left: 30px;
            width: <?php echo $gameConfig['joystickSize']; ?>px;
            height: <?php echo $gameConfig['joystickSize']; ?>px;
            z-index: 10;
        }
        .joystick-base {
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.3);
            border-radius: 50%;
            position: absolute;
        }
        .joystick-thumb {
            width: 50%;
            height: 50%;
            background-color: rgba(0,0,0,0.5);
            border-radius: 50%;
            position: absolute;
            top: 25%;
            left: 25%;
            touch-action: none; /* 防止触摸时页面滚动 */
        }
    </style>
</head>
<body>
    <div id="scoreBoard">得分: 0 | 生命: <?php echo $gameConfig['initialLives']; ?></div>
    <canvas id="gameCanvas" width="800" height="500"></canvas>
    
    <!-- 虚拟摇杆 -->
    <div class="joystick-container">
        <div class="joystick-base" id="joystickBase"></div>
        <div class="joystick-thumb" id="joystickThumb"></div>
    </div>

    <script>
        const config = <?php echo json_encode($gameConfig); ?>;
        let score = 0;
        let lives = config.initialLives;
        let gameRunning = true;
        let joystickActive = false;
        let joystickCenter = { x: 0, y: 0 };

        // 获取元素
        const canvas = document.getElementById('gameCanvas');
        const ctx = canvas.getContext('2d');
        const joystickBase = document.getElementById('joystickBase');
        const joystickThumb = document.getElementById('joystickThumb');

        // 玩家设置
        const player = {
            x: 100,
            y: canvas.height / 2,
            width: 40,
            height: 40,
            color: '#ff6b6b'
        };

        // 花朵和障碍物数组
        let flowers = [];
        let obstacles = [];

        // 摇杆初始化
        function initJoystick() {
            const rect = joystickBase.getBoundingClientRect();
            joystickCenter = {
                x: rect.left + rect.width / 2,
                y: rect.top + rect.height / 2
            };

            // 触摸事件
            joystickBase.addEventListener('touchstart', startJoystick);
            joystickThumb.addEventListener('touchstart', startJoystick);
            document.addEventListener('touchmove', moveJoystick);
            document.addEventListener('touchend', endJoystick);

            // 鼠标事件（桌面调试用）
            joystickBase.addEventListener('mousedown', startJoystick);
            joystickThumb.addEventListener('mousedown', startJoystick);
            document.addEventListener('mousemove', moveJoystick);
            document.addEventListener('mouseup', endJoystick);
        }

        // 开始使用摇杆
        function startJoystick(e) {
            e.preventDefault();
            joystickActive = true;
        }

        // 移动摇杆
        function moveJoystick(e) {
            if (!joystickActive) return;
            e.preventDefault();

            // 获取触摸/鼠标位置
            const clientX = e.touches ? e.touches[0].clientX : e.clientX;
            const clientY = e.touches ? e.touches[0].clientY : e.clientY;

            // 计算相对中心的偏移
            let dx = clientX - joystickCenter.x;
            let dy = clientY - joystickCenter.y;
            const distance = Math.sqrt(dx * dx + dy * dy);
            const maxDistance = config.joystickSize / 4; // 最大移动距离

            // 限制在摇杆范围内
            if (distance > maxDistance) {
                dx = (dx / distance) * maxDistance;
                dy = (dy / distance) * maxDistance;
            }

            // 移动摇杆
            joystickThumb.style.transform = `translate(${dx}px, ${dy}px)`;

            // 控制玩家移动（垂直方向）
            if (gameRunning) {
                // 根据Y轴偏移计算移动方向
                if (dy < -10 && player.y > player.height/2) {
                    player.y -= config.playerSpeed;
                } else if (dy > 10 && player.y < canvas.height - player.height/2) {
                    player.y += config.playerSpeed;
                }
            }
        }

        // 结束使用摇杆
        function endJoystick(e) {
            e.preventDefault();
            joystickActive = false;
            joystickThumb.style.transform = 'translate(0, 0)'; // 复位
        }

        // 游戏元素绘制和更新函数（与之前相同）
        function spawnFlower() {
            const x = canvas.width;
            const y = Math.random() * (canvas.height - 30) + 15;
            flowers.push({
                x, y,
                radius: 15,
                color: '#4ecdc4'
            });
        }

        function spawnObstacle() {
            const x = canvas.width;
            const y = Math.random() * (canvas.height - 60) + 30;
            obstacles.push({
                x, y,
                width: 30,
                height: 60,
                color: '#777'
            });
        }

        function drawPlayer() {
            ctx.fillStyle = player.color;
            ctx.beginPath();
            ctx.arc(player.x, player.y, player.width/2, 0, Math.PI*2);
            ctx.fill();
        }

        function drawFlowers() {
            flowers.forEach(flower => {
                ctx.fillStyle = flower.color;
                ctx.beginPath();
                ctx.arc(flower.x, flower.y, flower.radius, 0, Math.PI*2);
                ctx.fill();
            });
        }

        function drawObstacles() {
            obstacles.forEach(obstacle => {
                ctx.fillStyle = obstacle.color;
                ctx.fillRect(obstacle.x, obstacle.y, obstacle.width, obstacle.height);
            });
        }

        function update() {
            if (!gameRunning) return;

            ctx.clearRect(0, 0, canvas.width, canvas.height);

            // 键盘控制（保留键盘支持）
            window.addEventListener('keydown', (e) => {
                if (e.key === 'ArrowUp' && player.y > player.height/2) {
                    player.y -= config.playerSpeed;
                }
                if (e.key === 'ArrowDown' && player.y < canvas.height - player.height/2) {
                    player.y += config.playerSpeed;
                }
            });

            // 移动花朵和障碍物
            flowers.forEach((flower, index) => {
                flower.x -= config.flowerPoints / 5;
                if (flower.x < -flower.radius) flowers.splice(index, 1);
            });

            obstacles.forEach((obstacle, index) => {
                obstacle.x -= config.obstacleSpeed;
                if (obstacle.x < -obstacle.width) obstacles.splice(index, 1);
            });

            // 碰撞检测
            flowers.forEach((flower, index) => {
                const dx = player.x - flower.x;
                const dy = player.y - flower.y;
                if (Math.sqrt(dx*dx + dy*dy) < player.width/2 + flower.radius) {
                    flowers.splice(index, 1);
                    score += config.flowerPoints;
                    document.getElementById('scoreBoard').innerText = 
                        `得分: ${score} | 生命: ${lives}`;
                }
            });

            obstacles.forEach((obstacle, index) => {
                if (player.x + player.width/2 > obstacle.x &&
                    player.x - player.width/2 < obstacle.x + obstacle.width &&
                    player.y + player.width/2 > obstacle.y &&
                    player.y - player.width/2 < obstacle.y + obstacle.height) {
                    obstacles.splice(index, 1);
                    lives--;
                    document.getElementById('scoreBoard').innerText = 
                        `得分: ${score} | 生命: ${lives}`;
                    if (lives <= 0) gameOver();
                }
            });

            // 随机生成元素
            if (Math.random() < 0.02) spawnFlower();
            if (Math.random() < 0.01) spawnObstacle();

            // 绘制
            drawPlayer();
            drawFlowers();
            drawObstacles();

            requestAnimationFrame(update);
        }

        function gameOver() {
            gameRunning = false;
            ctx.fillStyle = '#000';
            ctx.font = '40px Arial';
            ctx.textAlign = 'center';
            ctx.fillText('游戏结束!', canvas.width/2, canvas.height/2);
            ctx.font = '20px Arial';
            ctx.fillText(`最终得分: ${score}`, canvas.width/2, canvas.height/2 + 40);
        }

        // 初始化
        initJoystick();
        update();
    </script>
</body>
</html>