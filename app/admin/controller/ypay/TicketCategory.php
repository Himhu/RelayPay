<?php
declare (strict_types = 1);

namespace app\admin\controller\ypay;

use think\facade\Request;
use app\common\service\YpayTicketCategory as S;
use app\common\model\YpayTicketCategory as M;

class TicketCategory extends  \app\admin\controller\Base
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

    // 状态
    public function status(){
        return $this->getJson(S::goStatus(Request::post('status'),Request::post('id')));
        }

    // 删除
    public function remove(){
        return $this->getJson(S::goRemove(input('id')));
        }

    // 批量删除
    public function batchRemove(){
        return $this->getJson(S::goBatchRemove(input('ids')));
        }

    // 回收站
    public function recycle(){
        if (Request::isAjax()) {
            return $this->getJson(S::goRecycle());
        }
        return $this->fetch();
    }

}
