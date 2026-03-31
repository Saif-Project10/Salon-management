<?php
session_start();
session_unset();
session_destroy();
header("Location: /salon-management/index.php");
exit();
?>
