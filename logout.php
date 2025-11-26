<?php
// logout.php
session_start();
session_unset();
session_destroy();

// Redirect to the landing page or login page
header("Location: index.html");
exit;
?>