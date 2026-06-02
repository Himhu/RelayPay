<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2019 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

// [ 应用入口文件 ]
namespace think;

//定义分隔符
define('DS', DIRECTORY_SEPARATOR);

require __DIR__ . '/../vendor/autoload.php';
// 执行HTTP应用并响应
$http = (new App())->http;
// 检测程序安装
$lockFile = __DIR__ . '/install.lock';
$installPath = '/install.php'; // 根目录安装路径

if (!file_exists($lockFile)) {
    // 动态构建完整URL
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    
    // 获取当前脚本所在目录（兼容子目录部署）
    $baseDir = str_replace($_SERVER['DOCUMENT_ROOT'], '', __DIR__);
    
    // 拼接绝对路径安装地址
    $redirectUrl = rtrim($protocol . $host . $baseDir, '/') . $installPath;
    
    header("Location: " . $redirectUrl);
    exit;
}


$response = $http->name('admin')->run();

$response->send();

$http->end($response);
