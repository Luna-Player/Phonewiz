<?php
session_start();
unset($_SESSION['admin_authenticated'], $_SESSION['admin_username']);
session_regenerate_id(true);
header('Location: index.php');
exit;
?>
