<?php
session_start();
session_unset();
session_destroy();
header('Location: /CampusConnect/auth/login.php');
exit;
