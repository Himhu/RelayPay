<?php


namespace app\index\controller;
use think\facade\Config;
use think\facade\Db;
use think\facade\Session;
use app\common\service\YpayUser as S;
use think\facade\View;
use think\facade\Request;

class Index extends \app\BaseController
{
    protected $middleware = ['Domain','Mtce'];
    
    public function index()
    {
        $config = getConfig();//获取系统配置参数

        if($config['is_aff']){
            $aff = Request::param('aff');
            if(!empty($aff)){
              Session::set('aff_id',$aff);
            }
        }
        if(!$config['is_weboff'])
        {
            return redirect(Request::root().'/User/Login');
        }else if($config['is_weboff'] == 2){
            if (filter_var($config['home_url'], FILTER_VALIDATE_URL)) {
                            return '<html><frameset framespacing="0" border="0" rows="0" frameborder="0">
        <frame name="main" src="'. $config['home_url'] .'" scrolling="auto" noresize>
    </frameset></html>';
            } else {
               return redirect(Request::root().'/User/Login');
            }

        }
        $list = Db::table('ypay_navs')->where('status', 1)->order('sort','asc')->select();
        $news1 = Db::name('ypay_news')->where('type',1)->where('status',1)->order('id desc')->paginate(5);
        $news2 = Db::name('ypay_news')->where('type',2)->where('status',1)->order('id desc')->paginate(5);
        $news3 = Db::name('ypay_news')->where('type',3)->where('status',1)->order('id desc')->paginate(5);
        $is_login = 0;
        if (S::isLogin()){
            $is_login = 1;
        }
        $homeTemp = $config['home_temp'];

        //首页信息数组
        $index_array = [
            'news1'  => $news1, //公告一
            'news2' => $news2, //公告二
            'news3' => $news3,//公告三
            'nav' => $list, //顶部导航
            'is_login' => $is_login, //判断是否登录
        ];

        // 获取 public/pay 目录的绝对路径（确保路径正确性）
        $homeDir = app()->getRootPath() . 'public/web/home/';

        // 构建完整模板路径
        $templatePath = $homeDir . '/' . $homeTemp;

        // 有效性验证（目录存在且包含index.html文件）
        if (!is_dir($templatePath) || !file_exists($templatePath . '/index.html')) {
            // 尝试获取第一个有效主题
            if (!empty(S::getHomeTheme()['data'])) {
                $homeTemp = S::getHomeTheme()['data'][0]['id'];
            }
            // 完全无可用模板的兜底处理
            else {
                // 跳转到指定的错误页面
                View::assign('error_tips', "请先配置首页界面");
                View::assign('error_url','/');
                return $this->fetch('error/errorPage');
            }
        }

        View::assign([
            'index_array' =>$index_array,
            'resource' => $homeTemp
        ]);
        View::config(['view_path' => app()->getRootPath() . 'public/web/home/'.$homeTemp]);
        return $this->fetch('/index.html',$this->getSystem());
    }
    
}
