<?php
declare (strict_types = 1);

namespace app\admin\controller\admin;

use think\facade\Request;
use app\common\service\AdminPermission as S;
use app\common\model\AdminPermission as M;

class Permission extends \app\admin\controller\Base
{
    protected $middleware = ['AdminCheck','AdminPermission'];
    
    // 列表
    public function index(){
        if (Request::isAjax()) {
            return $this->getJson(M::getList());
        }
        return $this->fetch();
    }

    // 添加
    public function add(){
        if (Request::isAjax()) {
            return $this->getJson(S::goAdd(Request::post()));
        }
        return $this->fetch('',[
            'permissions' => get_tree(M::order('sort','asc')->select()->toArray())
        ]);
    }

    // 编辑
    public function edit(){
        if (Request::isAjax()) {
            return $this->getJson(S::goEdit(Request::post(),input('id')));
            
        }
        return $this->fetch('',M::getFind(input('id')));
    }

    // 状态
    public function status(){
        return $this->getJson(S::goStatus(Request::post('status'),input('id')));
    }

    // 删除
    public function remove(){
        return $this->getJson(S::goRemove(input('id')));
    }
}

