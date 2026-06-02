<?php
declare (strict_types = 1);

namespace app\admin\controller\admin;

use think\facade\Request;
use app\common\service\AdminPhoto as S;
use app\common\model\AdminPhoto as M;

class Photo extends \app\admin\controller\Base
{
    protected $middleware = ['AdminCheck','AdminPermission'];

    // 列表
    public function index(){
        if (Request::isAjax()) {
            return $this->getJson(M::getPath());
        }
        return $this->fetch();
    }

    // 创建文件夹
    public function add(){
        if (Request::isAjax()) {
            return $this->getJson(S::goAdd());
        }
        return $this->fetch();
    }

    // 删除文件夹
    public function del(){
        return $this->getJson(S::goDel(input('name')));
    }

    // 列表
    public function list(){
        return $this->getJson(M::getList(input('name')));
    }

    // 添加单图
    public function addPhoto(){
        return $this->fetch('',['name'=>input('name')]);
    }

    // 添加多图
    public function addPhotos(){
        return $this->fetch('',['name'=>input('name')]);
    }

    // 删除
    public function remove(){
        return $this->getJson(S::goRemove(input('id')));
    }

    // 批量删除
    public function batchRemove(){
        return $this->getJson(S::goBatchRemove(Request::post('ids')));
    }
}
