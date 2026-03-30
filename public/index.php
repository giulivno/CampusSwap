<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../src/db.php';

$db = get_db();
echo 'Connected successfully!';
?>