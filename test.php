<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $servername = $_POST['servername'];
    $username = $_POST['username'];
    $password = $_POST['password'];
    $dbname = $_POST['dbname'];

    // 创建连接
    $conn = new mysqli($servername, $username, $password, $dbname);

    // 检查连接
    if ($conn->connect_error) {
        die("连接失败: ". $conn->connect_error);
    }

    $sql = $_POST['sql_query'];
    $result = $conn->query($sql);

    if ($result === TRUE) {
        echo "执行成功";
    } else {
        echo "执行错误: ". $conn->error;
    }

    $conn->close();
}
?>
<!DOCTYPE html>
<html>

<head>
    <title>执行SQL语句</title>
</head>

<body>
    <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]);?>">
        <label for="servername">数据库服务器地址:</label><br>
        <input type="text" id="servername" name="servername" value="localhost"><br>
        <label for="username">数据库用户名:</label><br>
        <input type="text" id="username" name="username"><br>
        <label for="password">数据库密码:</label><br>
        <input type="password" id="password" name="password"><br>
        <label for="dbname">数据库名:</label><br>
        <input type="text" id="dbname" name="dbname"><br>
        <label for="sql_query">请输入SQL语句:</label><br>
        <textarea id="sql_query" name="sql_query" rows="10" cols="50"></textarea><br>
        <input type="submit" value="执行">
    </form>
</body>

</html>
