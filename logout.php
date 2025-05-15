<?php
session_start();
session_destroy();
setcookie('taiKhoan', '', time() - 3600, '/');
header('Location: login.php');
exit;
?>