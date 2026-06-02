<?php
declare (strict_types = 1);

namespace app\admin\controller\admin;

use think\facade\Request;
use think\facade\Db;
use app\common\service\AdminAdmin as S;
use app\common\model\AdminAdmin as M;
class Admin extends  \app\admin\controller\Base
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
        $data = input();
        if (Request::isAjax()) {
            return $this->getJson(S::goEdit(Request::post(),$data['id']));
            
        }
        return $this->fetch('',['model' => M::find($data['id'])]);
    }

    // 状态
    public function status(){
        $data = input();
        return $this->getJson(S::goStatus(Request::post('status'),$data['id']));
    }

    // 删除
    public function remove(){
        $data = input();
        return $this->getJson(S::goRemove($data['id']));
    }

    // 批量删除
    public function batchRemove(){
        return $this->getJson(S::goBatchRemove(Request::post('ids')));
    }

    // 用户分配角色
    public function role(){
        $data = input();
        if (Request::isAjax()) {
            return $this->getJson(S::goRole(Request::post('roles'),$data['id']));
        }
        return $this->fetch('',M::getRole($data['id']));
    }

    // 用户分配直接权限
    public function permission(){
        $data = input();
        if (Request::isAjax()) {
            return $this->getJson(S::goPermission(Request::post('permissions'),$data['id']));
        }
        return $this->fetch('',M::getPermission($data['id']));
    }

    // 回收站
    public function recycle(){
        if (Request::isAjax()) {
            return $this->getJson(S::goRecycle());
        }
        return $this->fetch();
    }

    // 用户日志
    public function log(){
        if (Request::isAjax()) {
            return $this->getJson(M::getLog());
        }
        return $this->fetch();
    }

    // 清空日志
    public function removeLog(){
        try{
            Db::query('truncate table admin_admin_log');
           
        }catch (\Exception $e){
            return $this->getJson(['msg'=>'操作失败'.$e->getMessage(),'code'=>201]);
        }
         return $this->getJson(['msg'=>'清空成功','code'=>200]);
    }

}
