<?php
declare (strict_types = 1);

namespace app\admin\controller\admin;

use think\facade\Request;
use app\common\service\AdminChannel as S;
use app\common\model\AdminChannel as M;
use app\common\model\YpayPayment as Payment;
use app\common\util\Upload as Up;

class Channel extends  \app\admin\controller\Base
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
        // 获取支付方式
        $payment = Payment::select()->toArray();
        return $this->fetch('',['payment' => $payment]);
    }

    // 编辑
    public function edit(){
        if (Request::isAjax()) {
            return $this->getJson(S::goEdit(Request::post(),input('id')));
        }
        // 获取支付方式
        $payment = Payment::select()->toArray();
        return $this->fetch('',['model' => M::find(input('id')),'payment' => $payment]);
    }

    // 状态
    public function status(){
        return $this->getJson(S::goStatus(Request::post('status'),input('id')));
        }

    // 删除 
    public function remove(){
        return $this->getJson(S::goRemove(input('id')));
        }

    // 排序
    public function sort(){
        $data = input();
        return $this->getJson(S::goSort($data['data'],$data['sort_new'],$data['sort_old']));
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
    
        //上传解压通道
    public function upload(){
        return json(Up::channelPutFile(Request::file('file')));
    }

}
