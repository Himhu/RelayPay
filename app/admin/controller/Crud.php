<?php
declare (strict_types = 1);

namespace app\admin\controller;

use think\facade\Db;
use think\facade\Request;
use app\common\util\Crud as U;

class Crud extends Base
{
    protected $middleware = ['AdminCheck','AdminPermission'];
    
    // 系统配置
    public function index(){
        if (Request::isAjax()) {
            return $this->getJson(U::getTable());
        }
        return $this->fetch('',[
            'prefix' => config('database.connections.mysql.prefix')
        ]);
    }


    // 列表
    public function list(){
        return $this->getJson(['code'=>0,'data'=>Db::getFields(input('name'))]);
    }

    // 新增
    public function add(){
        if (Request::isAjax()) {
            return $this->getJson(U::goAdd());
        }
        return $this->fetch('',[
            'prefix' => config('database.connections.mysql.prefix')
        ]);
    }

    // 新增
    public function crud(){
        if (Request::isAjax()) {
            return $this->getJson(U::goCrud(input('name')));
        }
        return $this->fetch('',U::getCrud(input('name')));
    }

    // 删除
    public function remove(){
        return $this->getJson(U::goRemove(input('name')));
    }
}
