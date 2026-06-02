<?php
declare (strict_types = 1);

namespace app\admin\controller\admin;

use think\facade\Request;
use app\common\service\AdminRole as S;
use app\common\model\AdminRole as M;

class Role extends \app\admin\controller\Base
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
        return $this->fetch();
    }

    // 编辑
    public function edit(){
        if (Request::isAjax()) {
            return $this->getJson(S::goEdit(Request::post(),input('id')));
            
        }
        return $this->fetch('',['model' => M::find(input('id'))]);
    }

    // 删除
    public function remove(){
        return $this->getJson(S::goRemove(input('id')));
    }

    // 用户分配直接权限
    public function permission(){
        if (Request::isAjax()) {
            return $this->getJson(S::goPermission(Request::post('permissions'),input('id')));
        }
        return $this->fetch('',M::getPermission(input('id')));
    }

    // 回收站
    public function recycle(){
        if (Request::isAjax()) {
            return $this->getJson(S::goRecycle());
        }
        return $this->fetch();
    }
    
}
