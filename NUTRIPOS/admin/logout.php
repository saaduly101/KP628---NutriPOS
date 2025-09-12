<?php
require_once __DIR__.'/../backend/auth.php';
auth_logout();
header('Location: login.php');
