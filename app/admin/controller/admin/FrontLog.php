<?php
declare (strict_types = 1);

namespace app\admin\controller\admin;

use think\facade\Request;
use app\common\service\AdminFrontLog as S;
use app\common\model\AdminFrontLog as M;

class FrontLog extends  \app\admin\controller\Base
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
    // 一键清理
    public function allRemove(){
        return $this->getJson(S::allRemove());
    }
}
