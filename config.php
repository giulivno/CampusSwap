<?php
$env = parse_ini_file(__DIR__ . '/.env');

define('GOOGLE_MAPS_API_KEY', $env['GOOGLE_MAPS_API_KEY']);