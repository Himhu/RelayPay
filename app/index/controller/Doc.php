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
        $this->assignCommon();
        // 改变当前操作的模板路径
        getDocTemplate();
        return $this->fetch('',$this->getSystem());
    }
    
    //API下单接口
    public function api()
    {
        $this->assignCommon();
        // 改变当前操作的模板路径
        getDocTemplate();
        return $this->fetch('',$this->getSystem());
    }
    
    //查询接口接口
    public function result()
    {
        $this->assignCommon();
        // 改变当前操作的模板路径
        getDocTemplate();
        return $this->fetch('',$this->getSystem());
    }
    
    //查询订单接口
    public function findorder()
    {
        $this->assignCommon();
        // 改变当前操作的模板路径
        getDocTemplate();
        return $this->fetch('',$this->getSystem());
    }

    private function assignCommon(): void
    {
        View::assign('domain', Request::domain());
        View::assign('nav', $this->docNav());
    }

    private function docNav(): array
    {
        $items = Db::table('ypay_navs')
            ->field('id,name,url,is_target')
            ->where('status', 1)
            ->order('id', 'asc')
            ->select()
            ->toArray();

        return array_values(array_filter($items, function (array $item) {
            $name = trim((string) ($item['name'] ?? ''));
            $path = parse_url((string) ($item['url'] ?? ''), PHP_URL_PATH) ?: '';

            return $name !== '公告中心' && strcasecmp($path, '/News/Index') !== 0;
        }));
    }
    
}
