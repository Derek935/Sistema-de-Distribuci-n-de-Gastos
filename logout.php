<?php
session_start();
session_destroy();
header("Location: index.php");
echo "Logout funcionando";
exit();
?>