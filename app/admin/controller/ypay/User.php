<?php
declare (strict_types = 1);

namespace app\admin\controller\ypay;

use think\facade\Request;
use app\common\service\YpayUser as S;
use app\common\model\YpayVip as vip;
use app\common\model\YpayUser as M;
use think\facade\Cache;

class User extends  \app\admin\controller\Base
{
    protected $middleware = ['AdminCheck','AdminPermission'];

    // 列表
    public function index(){
        if (Request::isAjax()) {
            return $this->getJson(M::getList());
        }
        return $this->fetch();
    }

    // 添加用户
    public function add(){
        if (Request::isAjax()) {
            return $this->getJson(S::goUserAdd(Request::post()));
        }
        return $this->fetch('',['vip' => vip::select()]);
    }

    // 编辑用户
    public function edit(){
        if (Request::isAjax()) {
            return $this->getJson(S::goUserEdit(Request::post(),input('id')));
        }
        return $this->fetch('',['model' => M::find(input('id')),'vip'=>vip::select()]);
    }

    //登录用户
    public function adminLogin(){
        if (Request::isAjax()) {
            return $this->getJson(S::adminLogin(input('id')));
        }
    }
    
    // 邮件发信
    public function email(){
        if (Request::isAjax()) {
            return $this->getJson(S::goEmail(Request::post()));
        }
        return $this->fetch();
    }
    
    //获取邮箱发信进度
    public function get_email_progress(){
        if (Request::isAjax()) {
            $num = Cache::pull('email_progress');
            return json(['code' => 200,'msg' => $num]);
        }
    }
        
    //是否冻结该账户
    public function frozenstatus(){
        return $this->getJson(S::goFrozenStatus(Request::post()));
    }

    // 删除用户
    public function remove(){
        return $this->getJson(S::goRemove(input('id')));
        }

    // 批量删除用户
    public function batchRemove(){
        return $this->getJson(S::goBatchRemove(Request::post('ids')));
        }
}
