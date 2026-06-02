<?php


namespace app\index\controller;
use think\facade\Db;
use think\facade\Session;
use app\common\service\YpayUser as S;
use think\facade\View;
use think\facade\Request;

class Error extends \app\BaseController
{
    protected $middleware = ['Domain','Mtce'];
    
    public function errorPage()
    {
        return $this->fetch();
    }
    
}
