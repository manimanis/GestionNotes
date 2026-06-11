<?php
/**
 * Gestion de Notes - Routeur principal
 * Point d'entrée : http://127.0.0.1/GestionNotes/
 * 
 * Fonctionne sans .htaccess (AllowOverride peut être désactivé)
 * Grâce au dossier api/ (avec index.php), les requêtes /api/* 
 * sont automatiquement traitées par Apache via DirectoryIndex.
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = preg_replace('#^/GestionNotes#', '', $uri);
$path = '/' . trim($path, '/');

// === API : rediriger vers api/index.php ===
if (strpos($path, '/api') === 0) {
    // Passer le relai à api/index.php
    $_SERVER['REQUEST_URI'] = '/api' . substr($path, 4);
    $apiFile = __DIR__ . '/api/index.php';
    if (file_exists($apiFile)) {
        require $apiFile;
        exit;
    }
}

// === FRONTEND ===
$distDir = __DIR__ . '/frontend/dist';

// Route racine ou index.php → servir index.html
if ($path === '/' || $path === '/index.php' || $path === '/index.html') {
    $filePath = $distDir . '/index.html';
    if (file_exists($filePath)) {
        header('Content-Type: text/html; charset=utf-8');
        readfile($filePath);
        exit;
    }
}

// Fichiers statiques (JS, CSS, assets, favicon...)
$filePath = $distDir . $path;
if (file_exists($filePath) && is_file($filePath)) {
    $ext = pathinfo($filePath, PATHINFO_EXTENSION);
    $mimes = [
        'html' => 'text/html', 'css' => 'text/css', 'js' => 'application/javascript',
        'json' => 'application/json', 'png' => 'image/png', 'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon', 'woff' => 'font/woff', 'woff2' => 'font/woff2',
    ];
    header('Content-Type: ' . ($mimes[$ext] ?? 'application/octet-stream') . '; charset=utf-8');
    readfile($filePath);
    exit;
}

// Routes SPA (Vue Router) - servir index.html pour les chemins inconnus
$filePath = $distDir . '/index.html';
if (file_exists($filePath)) {
    header('Content-Type: text/html; charset=utf-8');
    readfile($filePath);
    exit;
}

http_response_code(404);
echo 'Page non trouvée';