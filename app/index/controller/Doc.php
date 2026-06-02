<?php


namespace app\index\controller;
use think\facade\Db;
use think\facade\View;
use think\facade\Request;

class Doc extends \app\BaseController
{
    protected $middleware = ['Domain','Mtce'];
    
    //默认首页发起
    public function index()
    {
        $list = Db::table('ypay_navs')->where('status', 1)->order('id','asc')->select();
        View::assign('domain',Request::domain());
        View::assign('nav', $list);
        // 改变当前操作的模板路径
        getDocTemplate();
        return $this->fetch('',$this->getSystem());
    }
    
    //API下单接口
    public function api()
    {
        $list = Db::table('ypay_navs')->where('status', 1)->order('id','asc')->select();
        View::assign('domain',Request::domain());
        View::assign('nav', $list);
        // 改变当前操作的模板路径
        getDocTemplate();
        return $this->fetch('',$this->getSystem());
    }
    
    //查询接口接口
    public function result()
    {
        $list = Db::table('ypay_navs')->where('status', 1)->order('id','asc')->select();
        View::assign('domain',Request::domain());
        View::assign('nav', $list);
        // 改变当前操作的模板路径
        getDocTemplate();
        return $this->fetch('',$this->getSystem());
    }
    
    //查询订单接口
    public function findorder()
    {
        $list = Db::table('ypay_navs')->where('status', 1)->order('id','asc')->select();
        View::assign('domain',Request::domain());
        View::assign('nav', $list);
        // 改变当前操作的模板路径
        getDocTemplate();
        return $this->fetch('',$this->getSystem());
    }
    
}
