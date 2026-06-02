<?php

declare (strict_types = 1);

namespace app\common\middleware;
use think\Response;
use think\facade\Request;

class DomainMiddleware
{
    public function handle($request, \Closure $next)
    {
        $data = getConfig(); // 获取设置参数
        if ($data['is_pay_api'] == 1) {
            // 获取当前请求的域名
            $domain = Request::instance()->host();
            
            // 检查 pay_api 是否包含逗号
            if (strpos($data['pay_api'], ',')!== false) {
                // 检查当前请求的域名是否包含在 pay_api 中
                if (strpos($data['pay_api'], $domain)!== false) {
                    // 如果包含，则不允许访问，返回 403 禁止访问的响应
                    return Response::create(self::html(), 'html', 403);
                }
            } else {
                // 使用 parse_url 函数解析 URL
                $parsedUrl = parse_url($data['pay_api']);
                if (isset($parsedUrl['host'])) {
                    // 获取解析后的域名
                    $api = $parsedUrl['host'];
                    // 使用 str_replace 函数去除协议
                    $api = str_replace(['http://', 'https://'], '', $api);
                } elseif (isset($parsedUrl['path'])) {
                    $api = $parsedUrl['path'];
                } else {
                    // 处理解析失败的情况
                    return Response::create(self::html(), 'html', 403);
                }
                
                // 判断域名是否是允许访问的域名
                if ($domain == $api) {
                    // 如果域名匹配，则不允许访问，返回 403 禁止访问的响应
                    return Response::create(self::html(), 'html', 403);
                }
            }
        }

        // 域名验证通过，继续执行后续请求
        return $next($request);
    }
    
    public function html(){
        $data = getConfig();//获取设置参数
        if($data['apiTemp'] == 'default'){
            $html = '
<!DOCTYPE html>
<html>
<head>
<title data-react-helmet="true">'.$data['sitename'].'-API接口</title>
<meta charset="utf-8">

<link rel="stylesheet" href="/static/index/css/api.css">
<style>
    /* 新增样式 */
    .footer {
      position: absolute;
      bottom: 0;
      width: 100%;
      text-align: center;
      padding: 10px;
      background-color: #fff;
    }
    .footer a {
        text-decoration: none;
        color: black;
    }
  </style>
</head>
<body>
<div class="container">
<div class="error">


<h2>'.$data['sitename'].'-API</h2>
<p>本接口为'.$data['sitename'].'API接口,不允许直接访问，具体详情请联系客服！</p>
</div>
<div class="stack-container">
<div class="card-container">
<div class="perspec" style="--spreaddist: 125px; --scaledist: .75; --vertdist: -25px;">
<div class="card">
<div class="writing">
<div class="topbar">
<div class="red"></div>
<div class="yellow"></div>
<div class="green"></div>
</div>
<div class="code">
<ul>
</ul>
</div>
</div>
</div>
</div>
</div>
<div class="card-container">
<div class="perspec" style="--spreaddist: 100px; --scaledist: .8; --vertdist: -20px;">
<div class="card">
<div class="writing">
<div class="topbar">
<div class="red"></div>
<div class="yellow"></div>
<div class="green"></div>
</div>
<div class="code">
<ul>
</ul>
</div>
</div>
</div>
</div>
</div>
<div class="card-container">
<div class="perspec" style="--spreaddist:75px; --scaledist: .85; --vertdist: -15px;">
<div class="card">
<div class="writing">
<div class="topbar">
<div class="red"></div>
<div class="yellow"></div>
<div class="green"></div>
</div>
<div class="code">
<ul>
</ul>
</div>
</div>
</div>
</div>
</div>
<div class="card-container">
<div class="perspec" style="--spreaddist: 50px; --scaledist: .9; --vertdist: -10px;">
<div class="card">
<div class="writing">
<div class="topbar">
<div class="red"></div>
<div class="yellow"></div>
<div class="green"></div>
</div>
<div class="code">
<ul>
</ul>
</div>
</div>
</div>
</div>
</div>
<div class="card-container">
<div class="perspec" style="--spreaddist: 25px; --scaledist: .95; --vertdist: -5px;">
<div class="card">
<div class="writing">
<div class="topbar">
<div class="red"></div>
<div class="yellow"></div>
<div class="green"></div>
</div>
<div class="code">
<ul>
</ul>
</div>
</div>
</div>
</div>
</div>
<div class="card-container">
<div class="perspec" style="--spreaddist: 0px; --scaledist: 1; --vertdist: 0px;">
<div class="card">
<div class="writing">
<div class="topbar">
<div class="red"></div>
<div class="yellow"></div>
<div class="green"></div>
</div>
<div class="code">
<ul>
</ul>
</div>
</div>
</div>
</div>
</div>
</div>
</div>

<div class="footer">
<div>
<span>Copyright © <script>
                    document.write(new Date().getFullYear());
                  </script> '.$data['sitename'].' All Rights Reserved. </span>
</div>
<p></p>
</div>
</body>
<script src="/static/index/js/api.js"></script>
</html>';
        }else{
            $html = $data['diyApiTemp'];
        }
        
        return $html;
    }
}