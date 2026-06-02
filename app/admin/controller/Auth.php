<?php

declare (strict_types = 1);

namespace app\admin\controller;
use think\facade\Request;
use app\common\core\Core;

class Auth extends Base
{
    protected $middleware = ['AdminCheck','AdminPermission'];
    
    // 系统配置
    public function index(){
        if(Request::isPost()){
            $data = Request::post('','','');
            $this->getConfig($data);
            $info = Core::check_licence($data);
            if($info['code'] == 200){
                return json($info);
            }else{
                return json($info);
            }
        }

        return $this->fetch();
    }
}
