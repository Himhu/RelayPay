<?php

declare(strict_types=1);

namespace app\index\controller;

use think\facade\Request;
use think\captcha\facade\Captcha;
use app\common\service\YpayUser as S;
use app\common\model\YpayPlug as Plug;
use think\facade\Session;
use think\facade\View;
use app\common\service\Third;

class User extends \app\BaseController
{
    protected $middleware = ['Domain', 'Mtce'];
    /**
     * 首页
     */
    public function index()
    {
        // 如果未登录进入用户中心界面则跳转至登录界面
        if (!S::isLogin()) {
            return redirect(Request::root() . '/User/Login');
        }
        $user = S::getUser();

        $users['head'] = $user['head'];
        $users['username'] = $user['username'];

        View::assign(
            [
                'user' => $users,
                'vip' => S::getVip(),
                'quotations' => S::getQuotations(),
                'totalRevenue' => S::getUser_totalRevenue(),
                'comparison' => S::getUser_ComparisonData(),
                'rightSide' => S::getUser_rightSide(),
                'bottom' => S::getUser_bottomInfo()
            ]
        );
        // 改变当前操作的模板路径
        getUserTemplate();
        return $this->fetch();
    }

    //登录
    public function login()
    {
        //如果已登录则进入用户中心
        if (S::isLogin()) {
            return redirect(Request::root() . '/User/Index');
        }
        //获取页面提交的数据传值
        if (Request::isAjax()) {
            $this->getJson(S::login(Request::param('', '', 'strip_tags')));
        }
        //调用清除缓存方法
        S::clear_captcha_session();
        // 改变当前操作的模板路径
        getUserTemplate();
        return View::fetch('', ['config' => getConfig(), 'Quicklogin' => S::quick_login(), 'pop' => 'no']);
    }


    // 注册
    public function reg()
    {
        // 如果已登录则进入用户中心
        if (S::isLogin()) {
            return redirect(Request::root() . '/User/Index');
        }
        // 获取页面提交的数据传值
        if (Request::isAjax()) {
            // 根据是否开启付费注册利用不同方法返回参数
            if (getConfig()['paid_reg'] == 1 && getConfig()['paid_reg_price'] != 0) {
                return json(S::register(Request::param('', '', 'strip_tags')));
            } else {
                $this->getJson(S::register(Request::param('', '', 'strip_tags')));
            }
        }
        // 调用清除验证码缓存方法
        S::clear_captcha_session();
        // 改变当前操作的模板路径
        getUserTemplate();
        return $this->fetch('', ['config' => getConfig(), 'Quicklogin' => S::quick_login(), 'pop' => 'yes']);
    }


    // 登录 - 获取短信
    public function getLoginCode()
    {
        $data = Request::param('', '', 'strip_tags');
        return $this->getJson(S::getCode('login', isset($data['mobile']) ? $data['mobile'] : '', isset($data['email']) ? $data['email'] : ''));
    }

    // 注册 - 获取短信
    public function getRegCode()
    {
        $data = Request::param('', '', 'strip_tags');
        return $this->getJson(S::getCode('register', isset($data['mobile']) ? $data['mobile'] : '', isset($data['email']) ? $data['email'] : ''));
    }


    // 找回 - 获取短信
    public function getLostCode()
    {
        $data = Request::param('', '', 'strip_tags');
        return $this->getJson(S::getCode('retrieve', isset($data['mobile']) ? $data['mobile'] : '', isset($data['email']) ? $data['email'] : ''));
    }

    //绑定快捷登录
    public function bind()
    {
        if (S::isLogin()) {
            return redirect(Request::root() . '/User/Index');
        }
        if (Request::isAjax()) {
            $this->getJson(S::bind(Request::param('', '', 'strip_tags')));
        }
        $data = array(
            'type' => session('type'),
            'username' => session('username'),
            'sid'   => session(session('type') . '_sid'),
            'is_bind' => session('is_bind' . session('type'))
        );
        if (empty($data['type']) || empty($data['username']) || empty($data['sid']) || empty($data['is_bind'])) {
            exit('对应参数为空,请重新使用快捷登录');
        }
        //调用清除缓存方法
        S::clear_captcha_session();
        View::assign('data', $data);
        View::assign('config', getConfig());
        View::assign('Quicklogin', S::quick_login());
        View::assign('pop', 'yes');
        // 改变当前操作的模板路径
        getUserTemplate();
        return $this->fetch();
    }

    //找回密码
    public function lostpwd()
    {
        if (Request::isAjax()) {
            $this->getJson(S::golostpwd(Request::param('', '', 'strip_tags')));
        }

        //调用清除验证码缓存方法
        S::clear_captcha_session();
        // 改变当前操作的模板路径
        getUserTemplate();
        return $this->fetch('', ['config' => getConfig()]);
    }

    //验证码
    public function verify()
    {
        ob_clean();
        return Captcha::create();
    }

    //退出登录
    public function logout()
    {
        S::logout();
        return redirect(Request::root() . '/User/Login');
    }

    //发起聚合登录
    public function OAuthAccountLogin($type)
    {
        $loginurl = Third::OAuthAccountLogin($type);
        if ($loginurl['code'] != 0) {
            exit($loginurl['msg']);
        }
        View::assign('url', $loginurl['url']);
        // 改变当前操作的模板路径
        getUserTemplate();
        return $this->fetch();
    }

    //发起QQ互联登录
    public function qqlogin()
    {
        $qqOAuth = Third::QQ();
        $url = $qqOAuth->getAuthUrl();
        Session::set('YURUN_QQ_STATE', $qqOAuth->state);
        header('location:' . $url);
        die;
    }

    //二次验证验证码
    public function Captcha()
    {
        //获取提交数据
        $data = Request::param('', '', 'strip_tags');

        return json(S::goCaptcha($data));
    }

    // 登录/注册成功通知
    public function notice()
    {
        // 分别获取 type 和 data 参数
        $type = Request::param('type', '', 'strip_tags');
        $data = Request::param('data', '', 'strip_tags');
        if ($type === 'login') {
            // 处理登录通知
            S::login_tips();
        } elseif ($type === 'register') {

            // 处理返回的 data 数据
            $processedData = [];
            foreach ($data as $item) {
                $processedData[$item['name']] = $item['value'];
            }
            // 处理注册通知
            S::register_tips($processedData);
        } else {
            // 未知类型通知
            return json(['status' => 0, 'message' => '未知通知类型']);
        }
    }

    //插件下载
    public function PlugDownload()
    {
        if (Request::isAjax()) {
            $plug = Plug::getPlugList();
            json_encode($plug, JSON_FORCE_OBJECT);
            return $plug;
        }
        View::assign(
            [
                'user' => S::getUser(),
                'vip' => S::getVip(),
            ]
        );
        // 改变当前操作的模板路径
        getUserTemplate();
        return $this->fetch();
    }
}
