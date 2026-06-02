<?php
declare (strict_types=1);

namespace app\admin\controller;

use think\facade\View;

class Update extends Base
{
    protected $middleware = ['AdminCheck', 'AdminPermission'];

    //进入更新页面
    public function index()
    {
        // 固定写死的数据
        $update = [
            'code' => 201,
            'msg' => '当前已是最新版本!',
            'version' => 'v.9.0.1',
            'vertime' => '2026-01-07'
        ];
        
        $update_info = [
            'title' => '系统更新',
            'content' => '当前系统已是最新版本，无需更新。'
        ];
        
        $ver = 'v.9.0.1';
        
        View::assign([
            'update' => $update,
            'update_info' => $update_info,
            'ver' => $ver
        ]);
        return $this->fetch();
    }

    public function checkver()
    {
        // 固定返回数据
        return $this->getJson([
            'code' => 201, 
            'msg' => '当前已是最新版本!', 
            'data' => [
                'version' => 'v.9.0.1',
                'vertime' => '2026-01-07'
            ]
        ]);
    }

    // 移除所有实际更新功能的代码
    public function update_ver()
    {
        return $this->getJson(['code' => 201, 'msg' => '演示模式，无法执行更新操作']);
    }
    
    // 移除上传功能
    public static function upload(){
        return json(['code' => 403, 'msg' => '演示模式，禁止上传']);
    }
}
