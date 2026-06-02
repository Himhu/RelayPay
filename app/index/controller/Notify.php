<?php
declare (strict_types = 1);

namespace app\index\controller;
use think\facade\Session;
use think\facade\Request;
use app\common\model\YpayUser as M;
use think\facade\Db;
use app\common\service\Jialanshen;
use app\common\service\YiPay as epay;
use app\common\service\Third;
use app\common\service\YpayUser as S;
use app\common\model\YpayUserbasic as basic;
use app\common\service\Paylist as paylist;
use app\common\model\YpayPaylist as m_paylist;

class Notify extends \app\BaseController
{
    
    //易支付异步通知
    public function notify()
    {
        $data = Request::param('','','strip_tags');
        paylist::core($data,'notify');
    }
    
    //易支付同步通知
    public function return()
    {
        $data = Request::param('','','strip_tags');
        return paylist::core($data,'return');
    }
    
    //付费注册异步通知
    public function regnotify_epay()
    {
        $data = Request::param('','','strip_tags');
        paylist::core($data,'register');
        if($data['type']=='wxpay'){
            $type = 'wx';
            $paylist = 'wechat';
        }else{
            $type = 'ali';
            $paylist = 'alipay';
        }
        
        $temp = Db::name('ypay_paylist')->where(['id' => getConfig()[$paylist]])->find();
        $user_key = $temp['key'];
        $epay = new epay();
        $isSign = $epay->verifySign($data,$user_key); //生成签名结果
        if(!$isSign)
        {
            echo 'fail'; die;
        }
        else
        {
            $ods = Db::name('ypay_recharge')->where('out_trade_no', $data['out_trade_no'])->find();
            if($ods['status']==0)
            {
                //变更订单状态
                Db::name('ypay_recharge')->where('id', $ods['id'])->update(['status' =>1,'end_time'=>date('Y-m-d H:i:s', time())]);
                //创建用户
                try {
                    $m = M::create(json_decode($ods['regdata'],true));
                    basic::create(['user_id' => $m->id,'appkey'=>rand_string()]);
                    }catch (\Exception $e){
                        return ['msg'=>'操作失败'.$e->getMessage(),'code'=>201];
                    }
                
                echo 'success'; die;
            }
            else
            {
                echo 'success'; die;
            }
        }
    }
    
    public function regretify_epay()
    {
        return redirect(Request::root().'/User/Login');
    }
    
    //通道列表测试支付异步通知
    public function testPay(){
        $data = Request::param('','','strip_tags');

        $ods = Db::name('ypay_order')->where('out_trade_no', $data['out_trade_no'])->find();
        if($ods['status'] == 0)
        {
            //变更订单状态
            Db::name('ypay_order')->where('id', $ods['id'])->update(['status' =>1,'end_time'=>date('Y-m-d H:i:s', time())]);
            echo 'success'; die;
        }
        else
        {
            echo 'success'; die;
        }

    }
    
    
        //聚合登录回调
    public function CallBack($type,$code)
    {
        $res = Third::CallBackSid($type,$code);
        if($res['code']!=0)
        {
            exit($res['msg']);
        }
        $sid = $res['social_uid'];
        if($type=='qq')
        {
            $user = Db::name('ypay_user')->where('is_bindqq', 1)->where('qq_sid', $sid)->find();
            if(empty($user))
            {
                if (!S::isLogin())
                {
                    $data = array(
                        'type' => $type,
                        'username' => 'qq_'.rand(1000,100000000),
                        'qq_sid'   => $sid,
                        'is_bindqq'=> 1
                        );
                    return redirect(Request::root().'/User/bind')->with($data);
                }
                else
                {
                    Db::name('ypay_user')->where('id', Session::get('front.id'))->update(['is_bindqq' =>1,'qq_sid'=>$sid]);
                    return redirect(Request::root().'/User/Index');
                }
            }
            else
            {
                
                //执行登录
                S::thirdlogin($user);
                return redirect(Request::root().'/User/Index');
            }
        }
        if($type=='wx')
        {
            $user = Db::name('ypay_user')->where('is_bindwx', 1)->where('wx_sid', $sid)->find();
            if(empty($user))
            {
                if (!S::isLogin())
                {
                    $data = array(
                        'type' => $type,
                        'username' => 'wx_'.rand(1000,100000000),
                        'wx_sid'   => $sid,
                        'is_bindwx'=> 1
                        );
                    return redirect(Request::root().'/User/bind')->with($data);
                }
                else
                {
                    Db::name('ypay_user')->where('id', Session::get('front.id'))->update(['is_bindwx' =>1,'wx_sid'=>$sid]);
                    return redirect(Request::root().'/User/Index');
                }
            }
            else
            {
                //执行登录
                S::thirdlogin($user);
                return redirect(Request::root().'/User/Index');
            }
        }
        
    }

    //QQ互联回调
    public function qqcallback($code='',$state='')
    {
        $qqOAuth = Third::QQNotify();
        // 获取accessToken
        $accessToken = $qqOAuth->getAccessToken($state);
        
        // 调用过getAccessToken方法后也可这么获取
        // $accessToken = $qqOAuth->accessToken;
        // 这是getAccessToken的api请求返回结果
        // $result = $qqOAuth->result;
        
        // 用户资料
        $userInfo = $qqOAuth->getUserInfo();
        
        // 这是getAccessToken的api请求返回结果
        // $result = $qqOAuth->result;
        
        // 用户唯一标识
        $openid = $qqOAuth->openid;
        
        $user = Db::name('ypay_user')->where('is_bindqq', 1)->where('qq_sid', $openid)->find();
        if(empty($user))
            {
                if (!S::isLogin())
                {
                    $data = array(
                        'type' => 'qq',
                        'username' => 'qq_'.rand(1000,100000000),
                        'qq_sid'   => $openid,
                        'is_bindqq'=> 1
                        );
                    return redirect(Request::root().'/User/bind')->with($data);
                }
                else
                {
                    Db::name('ypay_user')->where('id', Session::get('front.id'))->update(['is_bindqq' =>1,'qq_sid'=>$openid]);
                    return redirect(Request::root().'/User/Index');
                }
            }
            else
            {
                //执行登录
                S::thirdlogin($user);
                return redirect(Request::root().'/User/Index');
            }
        die;
    }
    
    public function alipay_dmf()
    {
        $data = Request::param('','','strip_tags');
        if(!$data)
        {
            return json(['code'=>0,'msg'=>'数据不可为空!']);
        }
        $order = Db::name('ypay_order')->where('out_trade_no',$data['out_trade_no'])->find();
        if(empty($order))
        {
            return json(['code'=>0,'msg'=>'当前订单不存在!']);
        }
        $account = Db::name('ypay_account')->where('id',$order['account_id'])->where('code','alipay_dmf')->find();
        if(empty($account))
        {
            return json(['code'=>0,'msg'=>'通道不存在!']);
        }
        
        $priKey = $account['cookie'];
        $res = Jialanshen::rsaCheck($data,$priKey);
        if($res===true && $data['trade_status'] == 'TRADE_SUCCESS')
        {
            $url = Jialanshen::creat_callback($order);
            get_curl($url['notify']);
            return json(['code'=>1,'msg'=>'回调成功!']);
        }
        else
        {
            return json(['code'=>0,'msg'=>'订单超时或不存在']);
        }
    }
    
    //汇付斗拱异步回调
    public function dougong()
    {
        $data['resp_data'] = $_POST['resp_data'];
        $data['sign'] = $_POST['sign'];
        
        if(empty($data))
        {
            return json(['code'=>201,'msg'=>'数据不可为空!']);
        }
        
        $result = \app\plugins\dougong\dougong_plugin::notify($data);

        if($result['code'] == 200){
            $url = Jialanshen::creat_callback($result['data']);
            get_curl($url['notify']);
        }
        
        return json(['code'=>$result['code'],'msg'=>$result['msg']]);
    }
    
     //乐刷异步回调
    public function lebrush()
    {
        $data['trade_no'] = input("pg_trade_no");
        $data['out_trade_no'] = input("out_trade_no");
        
        
        if(empty($data))
        {
            return json(['code'=>201,'msg'=>'数据不可为空!']);
        }
        
        $result = \app\plugins\lebrush\lebrush_plugin::notify($data);
    
        if($result['code'] == 200){
            $url = Jialanshen::creat_callback($result['data']);
            get_curl($url['notify']);
        }
        
        return json(['code'=>$result['code'],'msg'=>$result['msg']]);
    }
    
     public function dopay_dmf()
    {
        $data = Request::param('','','strip_tags');
        if(!$data)
        {
            return json(['code'=>0,'msg'=>'数据不可为空!']);
        }
        $order = Db::name('ypay_order')->where('out_trade_no',$data['out_trade_no'])->find();
        if(empty($order))
        {
            return json(['code'=>0,'msg'=>'当前订单不存在!']);
        }
        $priKey = getConfig()['dmf_openid'];
        $res = Jialanshen::rsaCheck($data,$priKey);
        if($res===true)
        {
            $url = Jialanshen::creat_callback($order);
            get_curl($url['notify']);
            return json(['code'=>1,'msg'=>'回调成功!']);
        }
        else
        {
            return json(['code'=>0,'msg'=>'订单超时或不存在']);
        }
    }
    
    //转接异步通知
    public function epay_returnzj()
    {
        $data = Request::param('','','strip_tags');
        //查询订单是否存在
        $order = Db::table('ypay_order')->where('out_trade_no', $data['out_trade_no'])->find();
        if(empty($order))
        {
            echo '该订单不存在'; die;
        }
        //获取配置信息
        $user = Db::table('ypay_user')->where('id', $order['user_id'])->find();
        if(empty($user))
        {
            echo '该商户不存在'; die;
        }
        $paylist = m_paylist::where('user_id',$user['id'])->where('status',1)->order('id','desc')->find();
        //实例化配置信息
        $epayzj = new epay($paylist['pid'],$paylist['key'],$paylist['url']);
        $isSign = $epayzj->verifySign($data,$paylist['key']); //生成签名结果
        if(!$isSign)
        {
            echo '验签失败，请检查配置信息'; die;
        }
        else
        {
            //验证通过
            $url = Jialanshen::creat_callback($order);
            $tj_url = $url['return'];
            //跳转
            header("Location:$tj_url");
        }
    }
    
    //转接易支付同步回调
    public function epay_notifyzj()
    {
        $data = Request::param('','','strip_tags');
        //查询订单是否存在
        $order = Db::table('ypay_order')->where('out_trade_no', $data['out_trade_no'])->find();
        if(empty($order))
        {
            echo 'fail'; die;
        }
        //获取配置信息
        $user = Db::table('ypay_user')->where('id', $order['user_id'])->find();
        if(empty($user))
        {
            echo 'fail'; die;
        }
        $paylist = m_paylist::where('user_id',$user['id'])->where('status',1)->order('id','desc')->find();
        //实例化配置信息
        $epayzj = new epay($paylist['pid'],$paylist['key'],$paylist['url']);
        $isSign = $epayzj->verifySign($data,$paylist['key']); //生成签名结果
        if(!$isSign)
        {
            echo 'fail'; die;
        }
        else
        {
            //验证通过
            $url = Jialanshen::creat_callback($order);
            get_curl($url['notify']);
            echo 'success'; die;
        }
    }
    
    //获取WxPusher回调
    public function wxpusher(){
        $data = Request::param('','','strip_tags');
        if($data['action'] == 'app_subscribe'){
            try {
                Db::table('ypay_user')->where('id', $data['data']['extra'])->update(['wxpusher_uid' => $data['data']['uid']]);
            } catch (\Exception $e) {
                echo $e->getMessage();
            } 
        }
        
    }
    
    //获取实名认证回调
    public function realName(){
        $data = Request::param('','','strip_tags');
        if($data['passed'] == "true"){
            try {
                Db::table('ypay_user')->where('id', S::getUserId())->update(['is_realName' => 1]);
            } catch (\Exception $e) {
                echo $e->getMessage();
            } 
        }
        
    }
    
    //获取支付宝实名信息认证
    public  function aliRealName(){
        
        if(isset($_GET['auth_code'])){
            $code = $_GET['auth_code'];
            $user_id = $_GET['cert_verify_id'];
          
            $config = getConfig();//获取系统配置参数
            $aop = new \AopClient ();
            $aop->gatewayUrl = 'https://openapi.alipay.com/gateway.do';
            $aop->appId = $config['appid'];
            $aop->rsaPrivateKey = $config['rsaPrivateKey'];
            $aop->alipayrsaPublicKey=$config['alipayrsaPublicKey'];
            $aop->apiVersion = '1.0';
            $aop->signType = 'RSA2';
            $aop->postCharset='UTF-8';
            $aop->format='json';
            $request = new \AlipaySystemOauthTokenRequest();
            $request->setCode($code);
            $request->setGrantType("authorization_code");
            $responseResult = $aop->execute($request);
            $responseApiName = str_replace(".","_",$request->getApiMethodName())."_response";
            $response = $responseResult->$responseApiName;
            $token = $response->access_token;//参数1
            
            $user = M::where('id',$user_id)->find();
            $name =$user['name'];
            $idCard = $user['idCard'];
            $request = new \AlipayUserCertdocCertverifyPreconsultRequest ();
            $request->setBizContent("{" .
            "  \"user_name\":\"".$name."\"," .
            "  \"cert_type\":\"IDENTITY_CARD\"," .
            "  \"cert_no\":\"".$idCard."\"," .
            "}");
            $result = $aop->execute($request); 
            $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
            $verify_id = $result->$responseNode->verify_id;//参数2
            
            $request = new \AlipayUserCertdocCertverifyConsultRequest ();
            $request->setBizContent("{" .
            "  \"verify_id\":\"".$verify_id."\"" .
            "}");
            $result = $aop->execute($request,$token); 
            $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
            if(isset($result->$responseNode->passed)){
                if($result->$responseNode->passed == 'T'){
                    M::where('id', $user_id)->update(['is_realName' => 1]);
                    $html = '<link rel="stylesheet" href="/user/default/static/assets/vendor/libs/bs-stepper/bs-stepper.css" /><div  class="bs-stepper mt-2">

                            <div class="bs-stepper-content">
                                <div class="layui-card" style="height:300px">
				<div class="result" style="height: 100%;display: flex;align-items: center;justify-content: center;align-content: center;flex-direction: column;">
					<div class="success" style="color: #32C682;">
					<svg viewBox="64 64 896 896" data-icon="check-circle" width="80px" height="80px" fill="currentColor" aria-hidden="true" focusable="false" class=""><path d="M699 353h-46.9c-10.2 0-19.9 4.9-25.9 13.3L469 584.3l-71.2-98.8c-6-8.3-15.6-13.3-25.9-13.3H325c-6.5 0-10.3 7.4-6.5 12.7l124.6 172.8a31.8 31.8 0 0 0 51.7 0l210.6-292c3.9-5.3.1-12.7-6.4-12.7z"></path><path d="M512 64C264.6 64 64 264.6 64 512s200.6 448 448 448 448-200.6 448-448S759.4 64 512 64zm0 820c-205.4 0-372-166.6-372-372s166.6-372 372-372 372 166.6 372 372-166.6 372-372 372z"></path></svg>
				    </div>
					<h2 class="title" style="margin: 30px;">实 名 认 证 成 功</h2>
				</div>
			</div>
                            </div>
                        </div>';
                    echo $html;
                    exit;
                }
                
            }
        }
        
    }
    
}
