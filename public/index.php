<?php
// [ 应用入口文件 ]
namespace think;

require __DIR__ . '/../vendor/autoload.php';

//定义分隔符
define('DS', DIRECTORY_SEPARATOR);

// 扩展检测逻辑（保持原有）
// if (extension_loaded('swoole_loader')) {
//     $php_v = substr(PHP_VERSION, 0, 3);
//     if ($php_v < '8.1') {
//         exit('<small>YPay需要php8.1及以上版本支持</small>');
//     }
// } else {
//     exit("<script>window.location.href='/help/swoole-compiler-loader.php';</script>");
// }

// 安装检测逻辑（保持原有）
$lockFile = __DIR__ . '/install.lock';
$installPath = '/install.php';
if (!file_exists($lockFile)) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    $baseDir = str_replace($_SERVER['DOCUMENT_ROOT'], '', __DIR__);
    $redirectUrl = rtrim($protocol . $host . $baseDir, '/') . $installPath;
    header("Location: " . $redirectUrl);
    exit;
}

$http = (new App())->http;
$response = $http->name('index')->run();
$response->send();
$http->end($response);