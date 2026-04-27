<?php
$title = isset($pageTitle) ? $pageTitle . ' - ' . APP_NAME : APP_NAME;

$assetUrl = function (string $relPath): string {
    $relPath = ltrim($relPath, '/');
    $fsPath = __DIR__ . '/../' . $relPath;
    $ver = @filemtime($fsPath) ?: time();
    return BASE_URL . '/' . $relPath . '?v=' . $ver;
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="<?= $assetUrl('assets/css/style.css') ?>">
    <link rel="stylesheet" href="<?= $assetUrl('assets/css/layout.css') ?>">
    <link rel="stylesheet" href="<?= $assetUrl('assets/css/dashboard.css') ?>">
</head>
<body>