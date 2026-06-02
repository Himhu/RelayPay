<?php
// 中间件配置
return [
    // 别名或分组
    'alias'    => [
        'AdminCheck' => app\common\middleware\AdminCheck::class,
        'AdminPermission'  => app\common\middleware\AdminPermission::class,
        'FrontCheck'  => app\common\middleware\FrontCheck::class,
        'FrontAuth'  => app\common\middleware\FrontAuth::class,
        'Domain' => app\common\middleware\DomainMiddleware::class,
        'ForceRealName' => app\common\middleware\ForceRealName::class,
        'Mtce' => app\common\middleware\Mtce::class,
        'GoogleAuth' => app\common\middleware\GoogleAuth::class,
    ],
    // 优先级设置，此数组中的中间件会按照数组中的顺序优先执行
    'priority' => [],
    
];
