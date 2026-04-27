<?php
require_once __DIR__ . '/../config/config.php';

// Login form lives on index.php — redirect there
header('Location: ' . BASE_URL . '/');
exit;
