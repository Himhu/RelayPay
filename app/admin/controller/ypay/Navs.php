<?php
declare (strict_types = 1);

namespace app\admin\controller\ypay;

use think\facade\Request;
use app\common\service\YpayNavs as S;
use app\common\model\YpayNavs as M;

class Navs extends  \app\admin\controller\Base
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
        return $this->getJson(S::goStatus(Request::post('status'),input('id')));
        }
    
    // 排序
    public function sort(){
        return $this->getJson(S::goSort(input('data'),input('sort_new'),input('sort_old')));
    }
    
    //是否跳转
    public function is_target(){
        return $this->getJson(S::goIsTarget(Request::post('is_target'),input('id')));
        }

    // 删除
    public function remove(){
        return $this->getJson(S::goRemove(input('id')));
        }

    // 批量删除
    public function batchRemove(){
        return $this->getJson(S::goBatchRemove(Request::post('ids')));
        }

    // 回收站
    public function recycle(){
        if (Request::isAjax()) {
            return $this->getJson(S::goRecycle());
        }
        return $this->fetch();
    }

}
