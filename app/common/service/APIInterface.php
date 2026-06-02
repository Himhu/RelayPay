<?php
declare (strict_types = 1);

namespace app\common\service;
use think\facade\Session;
use think\facade\Cookie;
use think\facade\Cache;
use think\facade\Request;
use think\facade\Db;
use think\facade\Validate;
use think\captcha\facade\Captcha;
use app\common\validate\YpayAccount as validate_account;
use app\common\validate\YpayUser as validate_user;
use app\common\model\YpayUser as model_user;
use app\common\model\YpayOrder as model_order;
use app\common\service\YpayUser as user;
use app\common\model\YpayUserbasic as basic;
use app\common\model\YpayVip;
use app\common\model\AdminChannel; 
use app\common\model\YpayAccount as model_account;
use think\facade\Config;
use app\common\util\Sms;
use app\common\util\Mail;
use app\common\model\AdminFrontLog as Log;
use app\common\service\YiPay as epay;
use app\common\core\core;
use system\GoogleAuthenticator;

class APIInterface
{
    
    //API接口下单
    public static function payment(array $data,$type){
        if(empty($data['pid']))
        {
            return ['code'=>201,'msg'=>'PID不可为空!'];
        }
        if(empty($data['out_trade_no']))
        {
            return ['code'=>201,'msg'=>'订单号不可为空!'];
        }
        if(empty($data['type']))
        {
            return ['code'=>201,'msg'=>'支付类型不可为空!'];
        }
        if(empty($data['notify_url']))
        {
            return ['code'=>201,'msg'=>'异步通知不可为空!'];
        }
        if(empty($data['return_url']))
        {
            return ['code'=>201,'msg'=>'同步通知不可为空!'];
        }
        if(empty($data['name']))
        {
            return ['code'=>201,'msg'=>'商品名称不可为空!'];
        }
        if(empty($data['money']))
        {
            return ['code'=>201,'msg'=>'金额不可为空!'];
        }
        $user = Db::table('ypay_user')->where('id',$data['pid'])->find();
        if(empty($user))
        {
            return ['code'=>201,'msg'=>'商户不存在!'];
        }
        $time = strtotime($user['vip_time']);
        if($time<time())
        {
            return ['code'=>201,'msg'=>'未开通套餐或套餐已过期!'];
        }
        if($data['money']<=0)
        {
            return ['code'=>201,'msg'=>'金额错误!'];
        }
        if($data['money']< getConfig()['min_orderprice'])
        {
            return ['code'=>201,'msg'=>'订单金额低于最低发起金额!'];
        }
        if( $data['money'] > getConfig()['max_orderprice'])
        {
            return ['code'=>201,'msg'=>'订单金额高于最高发起金额!'];
        }
        if(!empty(getConfig()['shield_key']))
        {
            $weigui = explode('|',getConfig()['shield_key']);
            for($index=0;$index<count($weigui);$index++)
            {
                if(empty($weigui[$index]))
                {
                    continue;
                }
                if(strpos($data['name'],$weigui[$index]) !== false)
                {
                    $risk_data = [
                        'user_id' =>$data['pid'], 
                        'name' =>$data['name'],
                        'url' => $data['return_url']
                    ];
                    try {
                        Risk::create($risk_data);
                    }catch (\Exception $e){
                        return ['code'=>201,'msg'=>'商品违规,已记录!'];
                    }
                    return ['code'=>201,'msg'=>'商品违规,已记录!'];
                }
            }
        }
        
        
        if($user['vip_id'] != 0){
        
            $vip = Db::table('ypay_vip')->where('id',$user['vip_id'])->find();
            
            //判断是否开启收款限额
            if(isset($vip)){
                if($vip['is_quota']){
                $today_money = Db::table('ypay_order')->where(['status' => 1,'user_id' => $user['id']])->whereDay('create_time')->sum('money');
                if( $today_money > $vip['today_quota']){
                    return ['code'=>201,'msg'=>"今日收款累计超过".$vip['today_quota']."的收款限额"];
                }
            }
            }
        }
        
        //是否支持余额为负数或者0可以发起支付
        if(getConfig()['is_pay_money'] == 0 && ($user['money'] == 0 || $user['money'] < 0)){
            View::assign('error_tips', "账户余额不足,无法发起支付");
            View::assign('error_url', $host);
            return $this->fetch();
        }
        
        $feilv_money = $data['money'] * $user['feilv'] / 100;
        if($user['money']<$feilv_money)
        {
            return ['code'=>201,'msg'=>'账户余额不足,无法发起支付!'];
        }

        $epay = new epay();
        $isSign = $epay->verifySign($data,$user['user_key']);  //生成签名结果
        if(!$isSign)
        {
            return ['code'=>201,'msg'=>'验签失败,请检查PID或者Key是否正确!'];
        }
        $is_orderNo = Db::table('ypay_order')->where('out_trade_no', $data['out_trade_no'])->find();
        if($is_orderNo)
        {
            return ['code'=>201,'msg'=>'订单号重复,请重新发起!'];
        }
         if(getConfig()['isDiy_orderNo'] == 1){
            $trade_no=getConfig()['diy_orderNo'].date("YmdHis").rand(11111,99999);
        }else{
            $trade_no='Y'.date("YmdHis").rand(11111,99999);
        }
        $QR_row =  Db::name('ypay_account')->where('type',$data['type'])->where('user_id',$data['pid'])->where('status',1)->where('is_status',1)->orderRaw('rand()')->find();//随机获取通道
        if(empty($QR_row))
        {
            return ['code'=>201,'msg'=>'暂无收款账号在线!'];
        }
        $action = $QR_row['code'];
        $res = Jialanshen::$action($trade_no,$QR_row,$data,$user);
        if($res)
        {
            $order = Db::name('ypay_order')->where('trade_no', $trade_no)->find();
            //根据类型返回不同参数
            if($type == 'mapi'){
                
                //根据类型返回QrCode
                if($data['type'] == 'alipay'){
                    $qrcode =  urldecode($order['qrcode']);
                }else if($data['type'] == 'wxpay'){
                    $qrcode =  $order['qrcode'];
                }else if($data['type'] == 'qqpay'){
                    $qrcode =  build_qrcode_url($order['qrcode']);
                }
                
                $data = array(
                    'code'=> 1,
                    'msg'=>'获取成功!',
                    'trade_no'=>$order['trade_no'],
                    'qrcode'=> $qrcode
                );
            }else{
                $data = array(
                    'code'=> 200,
                    'msg'=>'获取成功!',
                    'trade_no'=>$order['trade_no'],
                    'payurl' => $order['h5_qrurl'],
                    'type'=>$order['type'],
                    'out_trade_no'=>$order['out_trade_no'],
                    'money'=>$order['truemoney'],
                    'code_url' =>  build_qrcode_url($order['qrcode']),
                );
            }
            return $data;
        }
        else
        {
            $data = array(
                    'code'=>201,
                    'msg'=>'订单生成错误,请重新发起支付!',
                );
            return $data;
        }
    }
    
    //验证信息是否正确
    public static function verifyUserInfo($token = null , $user_id = null){
        //查询用户信息
        $result = model_user::where(['token'=>$token,'is_frozen'=>0,'id' => $user_id])->find();
        if(!empty($result)){
           return true; 
        }
        return false; 
    }
    
    //验证授权
    public static function getAuth(){
        return core::software_lincence();
    }
    
    //在线更新
    public static function getUpdate(array $data){
        return core::software_update($data);
    }
    
    //获取免费版软件在线更新
    public static function getFreeUpdate(array $data){
        return core::software_freeUpdate($data);
    }
    
    //获取软件基本配置信息
    public static function getSoftwareConfig(){
                
        //获取系统配置参数
        $config = getConfig();
        
        $data = 
        [
            'name' => $config['software_name'],//软件名称
            'login_type' => $config['logincode-type'],//登录方式 0:账户密码  1:短信 2:邮箱 3:社交登录
            'register_type' => $config['regcode-type'],//登录方式 0:账户密码  1:短信 2:邮箱
            'captcha_type' => $config['captcha-type'],//登录方式 0:关闭  1:普通验证码 2:腾讯防水墙 3:极验行为验(第4代)
        ];
        
        return ['code' => 200 , 'msg' => '获取成功' , 'data' => $data];
        
    }
    
    //获取/更新验证码
    public static function getCaptcha(){
        ob_clean();
        return Captcha::create();
    }
    
    //获取邮箱短信验证码
    public static function getCode(array $data){
        return user::getCode($data['type'],$data['mobile'],$data['email']);
    }
    
    //获取首页展示数据
    public static function getHome(){
        
    }
    
    // 用户登录
    public static function login(array $data)
    {
       
        //getConfig() 获取所有系统配置参数
        // getConfig()['captcha-type'] 获取验证码类型 Tips: 0:关闭验证码/1:普通验证码/2:腾讯防水墙
        // getConfig()['logincode-type'] 获取登录方式类型 Tips: 0:用户名+密码登录/1:手机号登录/2:邮箱登录
        $config = getConfig();
        $captcha_type = getConfig()['captcha-type'];
        $logincode_type = getConfig()['logincode-type'];
        $validate = new validate_user;
        $user = null;
        
        //缓存验证码信息
        session('captcha',cache('captcha'));
        
         //调用验证方法数据方法/传入验证码类型和验证码参数
        $ordinary_captcha = empty($data['ordinary_captcha']) ? "":$data['ordinary_captcha'];
        
        //不走极验和腾讯007
        if($captcha_type > 1){
            $captcha_type = 1;
        }
        
        //获取验证验证码信息
        $is_captcha = user::is_captcha($captcha_type,$ordinary_captcha);
        //判断是否有值返回
        if(!empty($is_captcha)){
           return  $is_captcha;
        }
        
        // 判断登录类型切获取用户数据
        switch ($logincode_type) {
            case 0:
                //验证数据是否填写
                if(!$validate->scene('login')->check($data))return ['msg'=>$validate->getError(),'code'=>201];
                //验证是否存在此用户
                $user = model_user::where([
                    'username' => trim($data['username']),
                    'password' => set_password(trim($data['password']))
                ])->find();
                break;
            case 1:
                //验证数据是否填写
                if(!$validate->scene('mobile')->check($data))return ['msg'=>$validate->getError(),'code'=>201];
                $code = Cache::get('captcha'); ;
                if($data['captcha']==$code)//验证通过
                {
                    $user = model_user::where([
                    'mobile' => trim($data['mobile'])
                    ])->find();
                }else
                {
                    return ['msg'=>'验证码错误','code'=>201];
                }
                break;
                
            default:
                //验证数据是否填写
                if(!$validate->scene('email')->check($data))return ['msg'=>$validate->getError(),'code'=>201];
                $code = Cache::get('captcha');
                if($data['captcha']==$code)//验证通过
                {
                    $user = model_user::where([
                    'email' => trim($data['email'])
                    ])->find();
                }
                else
                {
                    return ['msg'=>'验证码错误','code'=>201];
                }
                break;
        }
        
        //判断账户密码是否正确
        if(!$user){return ['msg'=>'用户名/密码错误','code'=>201];} 
        
        //判断该账户是否被冻结
        if($user['is_frozen']) return ['msg'=>$user['frozen_reason'],'code'=>201];
        
        //判断是否开启了登录安全验证
        if($config['isSecurity'] == 1 && $config['isSecurityLogin'] == 1 && !empty($user['googlekey'])){
            //获取用户的密钥信息
            $google =new GoogleAuthenticator();
            //$google_secret 存入的谷歌秘钥  ，$code 谷歌动态验证码
            $checkResult = $google->verifyCode($user['googlekey'], $data['securityCode'], 4);
            if ($checkResult)
            {
                $info = [
                    'id' => $user['id'],
                    'isAuth' => true
                ];
                Session::set('front_auth', $info);
            }
            else
            {
                return ['code'=>201,'msg'=>'安全验证码错误'];
            }
        }
        
        //调用清除缓存方法
        user::clear_captcha_session();
        $user->token = rand_string().$user->id.microtime(true);
        $user->save();
        //是否记住密码
        $time = 3600;
        if (isset($data['remember'])) $time = 7 * 86400;
        //缓存登录信息
        $login_info = [
            'id' => $user->id,
            'token' => $user->token
        ];

        //记录登录日志
        $info = [
           'uid'       => $user->id,
           'url'      => Request::url(),
           'desc'    => '商户登录成功', 
           'ip'       => get_client_ip(),
           'user_agent'=> Request::server('HTTP_USER_AGENT')
        ];
        Log::create($info);

        return ['msg'=>'登录成功','code'=>200 ,'data' => $login_info];
    }
    
    //用户注册
    public static function register(array $data){
         
        //判断是否重复点击注册按钮
        $is_register = Cache::get('is_register');
        
        if($is_register == 'yes'){
            return ['code'=>201,'msg'=>'请勿重复注册,60秒后再尝试!'];
        }
        
        
        // getConfig() 获取系统配置信息
        // getConfig()['is_reg'] 检查是否开启注册 Tips: 0:关闭/1:开启
        // getConfig()['captcha-type'] 获取验证码类型 Tips: 0:关闭验证码/1:普通验证码/2:腾讯防水墙
        // getConfig()['regcode-type'] 获取注册方式类型 Tips: 0:用户名+密码+邮箱注册/1:手机号注册/2:邮箱注册
        $config = getConfig();
        $is_reg = $config['is_reg'];
        $captcha_type = $config['captcha-type'];
        $regcode_type = $config['regcode-type'];
        
        // 判断注册功能是否开启0/1 = 关闭/开启
        if($is_reg !=1 )
        {
            return ['code'=>201,'msg'=>'注册功能已关闭!'];
        }
        
         // 验证
        $validate = new validate_user;
        
        // 判断是否是推广链接注册
        if(!empty(session('aff_id'))){
            $data['superior_id'] = session('aff_id');
        }
        
        // 调用验证方法数据方法/传入验证码类型和验证码参数
        $ordinary_captcha = empty($data['ordinary_captcha']) ? "":$data['ordinary_captcha'];
        
                
        //不走极验和腾讯007
        if($captcha_type > 1){
            $captcha_type = 1;
        }
        
        // 获取验证验证码信息
        $is_captcha = user::is_captcha($captcha_type,$ordinary_captcha);
        
        // 判断是否有值返回
        if(!empty($is_captcha)){
           return  $is_captcha;
        }
        
        //判断验证码是否正确
        if($regcode_type != 0 )
        {
            if($regcode_type == 1){
                if(!$validate->scene('mobile')->check($data))return ['msg'=>$validate->getError(),'code'=>201];
            }else{
                if(!$validate->scene('email')->check($data))return ['msg'=>$validate->getError(),'code'=>201];
            }
            $code = Cache::get('captcha');
            if($data['captcha']!=$code)
            {
                return ['msg'=>'验证码错误','code'=>201];
            }
        }
        
        // 验证提交数据
        if(!$validate->scene('add')->check($data))
        return ['msg'=>$validate->getError(),'code'=>201];
        
        // 调用清除验证码缓存方法
        user::clear_captcha_session();
        
        // 判断是否是快捷登录
        if($data['type'] == 'bind'){
            if($data['type'] == 'qq'){
                $data['is_bindqq'] = $data['is_bind'];
                $data['qq_sid'] = $data['open_id'];
            }else{
                $data['is_bindwx'] = $data['is_bind'];
                $data['wx_sid'] = $data['open_id'];
            }
        }
        $data['password'] = set_password(trim($data['password']));
        $data['user_key'] = rand_string();
        $data['username'] = htmlspecialchars($data['username']);
        
        // 检查是否开启赠送会员功能
        // $config['is_reg_give_vip'] 赠送VIP开关
        // $config['reg_give_vip']  赠送VIP套餐ID
        $is_reg_vip = $config['is_reg_give_vip'];
        if($is_reg_vip == 1){
            $reg_vip_id = $config['reg_give_vip'];
            $vip = YpayVip::where('id',$reg_vip_id)->find(); //根据ID获取套餐
            $data['vip_id'] = $reg_vip_id;
            $data['vip_time'] = date("Y-m-d H:i:s",strtotime("+ ".$vip['viptime']." day"));
            $data['feilv'] = $vip['feilv'];
        }
        //检查是否开启赠送余额功能
        $zsoff = $config['is_reg_give_price'];
        if($zsoff==1)
        {
            $data['money'] = $config['reg_give_price'];
        }
        // 检查是否开启了付费注册
        if($config['paid_reg'] == 1 &&$config['paid_reg_price'] != 0){
            $alipay = $config['alipay'];
            $wxpay  = $config['wechat'];
            if($alipay == 0 && $wxpay == 0)
            {
                return ['msg' => '无收款通道','code'=>201];
            }elseif($alipay != 0 && $wxpay != 0){
                $paytype = [['name'=>'alipay','showname'=>'支付宝'],['name'=>'wxpay','showname'=>'微信']];
            }elseif($alipay != 0){
                $paytype = [['name'=>'alipay','showname'=>'支付宝']];
            }elseif($wxpay != 0){
                $paytype = [['name'=>'wxpay','showname'=>'微信']];
            }
            //付费注册创建订单，返回订单号，在发起支付页面查询并组装数据发起支付
            $order_id = 'Y'.date("YmdHis").rand(11111,99999);
            $reginfo = [
               'type'       => 'default',
               'out_trade_no'      => $order_id,
               'rtype' => 1,
               'user_id'  => 0,
               'money' => $config['paid_reg_price'],
               'status'    => 0, 
               'create_time'       => date('Y-m-d H:i:s', time()),
               'end_time'=> date('Y-m-d H:i:s', time()),
               'regdata'    => json_encode($data), 
            ];
            Recharge::create($reginfo);
            return ['paytype'=>$paytype,'need'=>$config['paid_reg_price'],'code'=>888,'trade_no'=>$order_id];
        }
        
        //记录注册缓存
        Cache::set('is_register','yes',60);
        
        try {
            model_user::create($data);
            return ['msg'=>'注册成功','code'=>200];
        }catch (\Exception $e){
            return ['msg'=>'操作失败'.$e->getMessage(),'code'=>201];
        }
    }
    
    //获取用户账户列表
    public static function getAccount(array $data){
        // 验证信息是否正确
        $result = self::verifyUserInfo($data['token'],$data['user_id']);
        
        //验证通过则获取通道
        if($result){
            $account = model_account::getUserList($data['user_id']);
            json_encode($account, JSON_FORCE_OBJECT);
            return $account;
        }else{
            return ['code' => 201 , 'msg' => '用户信息错误'];
        }
    }
    
    //新增用户账户
    public static function addAccount(array $data){
        // 验证信息是否正确
        $result = self::verifyUserInfo($data['token'],$data['user_id']);
        if(!$result){
            return ['code' => 201 ,'msg' =>'用户信息错误'];
        }
        
        if($data['code']=='wxpay_dy' || $data['code']=='wxpay_software')
        {
            if($data['code']=='wxpay_dy'){
                if(empty($data['wxname']))
                {
                    return ['msg'=>"收款微信昵称不可为空",'code'=>201];
                }
                $verywx = model_account::where('wxname',$data['wxname'])->find();
                if(!empty($verywx))
                {
                    return ['msg'=>"收款微信昵称已存在,请检查",'code'=>201];
                }
            }
        }
                //创建Core对象
        $core  = new Core();
        $user = model_user::where('id',$data['user_id'])->find();
        if(empty($user['vip_time'])){
            return ['msg'=>"未开通会员套餐",'code'=>201];
        }
        $time = strtotime($user['vip_time']);
        if($time<time())
        {
            return ['msg'=>"会员套餐已过期",'code'=>201];
        }
        $vip = YpayVip::where('id',$user['vip_id'])->find();
        //判断是否开启了通道绑定功能
        if(!empty($vip['is_passage'])){
            if($vip['is_passage'] && !strstr($vip['passage'],$data['code'])){
                return ['msg'=>"该通道需要开启更高级的套餐才能使用",'code'=>201];
            }
        }
        
        //判断是否开启限制添加通道
        if($vip['is_addChannelNum'] == 1){
            $count = model_account::where('user_id',$data['user_id'])->count();
            if($count > $vip['addChannelNum']){
                return ['msg'=>"通道添加已上限",'code'=>201];
            }
        }
        
        $data['user_id'] = $data['user_id'];
        $channel = AdminChannel::where('code',$data['code'])->find();
        $data['type'] = $channel['type'];
        $data['succcount'] = 0;
        $data['succprice'] = 0;
        //验证
        $validate = new validate_account;
        if(!$validate->scene('add')->check($data))
        return ['msg'=>$validate->getError(),'code'=>201];
        if(empty($channel))
        {
            return ['msg'=>"通道不存在或标识重复",'code'=>201];
        }
        
        if($data['type']=="wxpay" && ($data['code'] == "wxpay_cloud" || $data['code'] == "wxpay_cloudzs" || $data['code'] == "wxpay_skd"))
        {       
            
            //构建查询参数
            $where = 
            [
                'id' => $data['diyu'],
                'type' => 1
            ];
            
            //获取云端地域
            $cloudArray = Db::table('ypay_cloud')->where($where)->find();
            
            //判断是否为空 , 为空则提示错误
            if(!empty($cloudArray)){
                //根据云端类型返回不同的信息
                try {
                    //创建微信实例 传递参数:云端类别 云端地址
                    $res = $core->getWechatCreate($cloudArray['cloud_type'],$cloudArray['address']);
                } catch (\Exception $e){
                   return ['msg'=>'请检查云端地址是否正确','code'=>201]; 
                }
                
                //如果参数等于success 则进入流程
                if($res['message'] == "success")
                {
                    $data['vcloudurl'] = $cloudArray['address'];
                    $data['wx_guid'] = $res['guid'];
                }else
                {
                    return ['msg'=> $res['message'],'code'=>201];
                }
                
            }else{
               return ['msg'=>"没查询到此云端信息",'code'=>201]; 
            }
            
        }
        else
        {
            if($data['code']=="wxpay_dy")
            {
                $data['status'] = 1;
            }
        }
        if($data['type']=="qqpay" || $data['code'] == 'qqpay_wzq'){
            if(empty($data['qq']))
            {
                return ['msg'=>"qq号不可为空",'code'=>201];
            }
            
            if($data['code'] == 'qqpay_cloud' || $data['code'] == 'qqpay_wzq'){
                $cloud= Db::table('ypay_cloud')->where('id',$data['diyu'])->find();
                try {
                    $core->Api_AddQQ($data['qq'],$cloud['address']);
                }catch (\Exception $e){
                    return ['msg'=>'请检查云端地址是否正确','code'=>201];
                }
                $data['vcloudurl'] = $cloud['address'];
            }
        }
        if($data['code']=='wxpay_software' )
        {
            $data['status'] = 1;
        }
        
        
        if(!empty($data['aliappkey'])){
            $data['qr_url'] = $data['aliappkey'];
        }
        if($data['code']=='alipay_mck' || $data['code']=='alipay_dmf' || $data['code']=='lkl_wxpay' || $data['code']=='lkl_alipay' || $data['code']=='dougong_alipay' || $data['code']=='dougong_wxpay' || $data['code']=='lebrush_alipay' || $data['code']=='lebrush_wxpay')
        {
            $data['wxname'] = $data['zfbapppid'];
            $data['status'] = 1;
        }
 
        
        if(!empty($data['remark'])){
            $data['remark'] = strip_tags($data['remark']);
        }
    
        try {
            model_account::create($data);
            return ['msg'=>'创建成功','code'=>200];
        }catch (\Exception $e){
            return ['msg'=>'操作失败'.$e->getMessage(),'code'=>201];
        }
    }
    
    //删除用户账户
    public static function delAccount(array $data){
        // 验证信息是否正确
        $result = self::verifyUserInfo($data['token'],$data['user_id']);
        
        //验证通过则删除账户
        if($result){
            try {
                model_account::where('id',$data['account_id'])
                    ->where('user_id', $data['user_id'])
                    ->delete();
                return ['code' => 200 , 'msg' => '删除成功'];
            } catch (\Exception $e) {
               return ['msg'=>'操作失败'.$e->getMessage(),'code'=>201];
            }
            
        }
    }
    
    //获取通道列表
    public static function getChannel(){
        $channel = Db::table('admin_channel')->where(['status'=>1,'type'=>'wxpay'])->order('sort', 'desc')->select();

        //获取微信云端
        $cloud = Db::table('ypay_cloud')->order('sort','asc')->where(['status'=> 1,'type' => 1])->select()->toArray();
        $cloudType = array();
        $xy = array();
        $macV3 = null;
        $macV2 = null;
        $IPad = null;
        $i = 1;
        //循环遍历
        foreach ($cloud as $key => $value) {
            if($value['cloud_type'] == 1){
                $macV3 = ['id' => 1 , 'name' => 'Mac - V3'];
                $xy[$i]['id'] = $value['id'];
                $xy[$i]['name'] = $value['name'];
                $i++;
            }else if($value['cloud_type'] == 2){
                $macV2 = ['id' => 2 , 'name' => 'Mac - V2'];
            }else if($value['cloud_type'] == 3){
                $IPad = ['id' => 3 , 'name' => 'IPad'];
            }
        }
        
        $cloudType = [$macV3,$macV2,$IPad];
        $cloudType = array_filter($cloudType);
        
        if(empty($cloudType)){
            $cloudType = 
                [
                    ['id' => 0 , 'name' => '未有可用云端']
                ];
            $xy = [['id' => '未有可用云端' , 'name' => '未有可用云端']]; 
        }
        
        return ['code' => 200 , 'msg' => '获取成功' , 'data' =>['channel'=> $channel,'cloud' => $xy ,'cloud_type' =>$cloudType]];
    }
    
    //根据类型筛选通道
    public static function filter_channel($type){
        $channel = Db::table('admin_channel')->where(['status'=>1,'type'=>$type])->order('sort', 'desc')->select();
        return ['code'=>200,'msg'=> '获取成功','channel'=>$channel];
    }
    
    //根据类型筛选云端
    public static function filter_cloud(array $data){
        $cloud = Db::table('ypay_cloud')->field('cloud_type,id,name')->order('sort', 'asc')->where(['status' => 1, 'type' => $data['type'], 'cloud_type' => $data['cloud_type']])->select();
        return ['code'=>200,'msg'=> '获取成功','cloud'=>$cloud];
    }
    
    //获取登录二维码
    public static function getQrCode(array $qr_data){
        // 验证信息是否正确
        $result = self::verifyUserInfo($qr_data['token'],$qr_data['user_id']);
        if(!$result){
            return ['code' => 201 ,'msg' =>'用户信息错误'];
        }
        //创建Core对象
        $core  = new Core();
        $account = Db::table('ypay_account')->where('id', $qr_data['account_id'])->where('user_id',$qr_data['user_id'])->find();
        if(empty($account))
        {
            return ['code'=>201,'msg'=>'通道不存在!'];
        }
        if($account['type']=="alipay")
        {
            //获取支付宝登录二维码
            $data = $core->GetAlipayLoginQrcode();
            
            Db::name('ypay_account')->where('id', $account['id'])->update(['remark' => $data['loginid']]);
            return ['code'=>200,'msg'=>'获取成功!','qr_url'=>build_qrcode_url($data['qrcodeurl']),'loginid'=>$data['loginid']];
        }
        if($account['type']=="wxpay" && $account['code']!="wxpay_dy" && $account['code']!="qqpay_wzq")
        {       
                $res = $core->getWechatQrCode($account['wx_guid'],$account['vcloudurl']);
                
                //如果数据为空则重新执行生成流程
                if(empty($res)){
                    //查询是否存在该云端地域
                    $cloudArray = Db::name('ypay_cloud')->where('address',$account['vcloudurl'])->find();
                    
                    //数据不为空则执行流程
                    if(!empty($cloudArray)){
                        try {
                            //创建微信实例 传递参数:云端类别 云端地址
                            $res = $core->getWechatCreate($cloudArray['cloud_type'],$cloudArray['address']);
                            //更新Guid
                            Db::name('ypay_account')->where('id', $account['id'])->update(['wx_guid' =>$res['guid']]);
                            //重新获取二维码信息
                            $res = $core->getWechatQrCode($res['guid'],$cloudArray['address']);
                        } catch (\Exception $e){
                          return ['msg'=>'请检查云端地址是否正确','code'=>201]; 
                        }
                    }else{
                        return ['msg'=>'未查到此云端地域','code'=>201]; 
                    }
                }
                Db::name('ypay_account')->where('id', $account['id'])->update(['remark' =>$res->data->uuid]);
                return ['code'=>200,'msg'=>'获取成功!','uuid'=>$res->data->uuid,'qr_url'=>$res->data->qrcode,'guid'=>$account['wx_guid']];

        }
        if($account['code']=="qqpay_mg")
        {
            $res = $core->QCreate();
            if($res['code']==1)
            {
                Db::name('ypay_account')->where('id', $account['id'])->update(['remark' => $res['qrsig']]);
            }
            
            return ['code'=>200,'msg'=>'获取成功!','qr_url'=>$res['qr_url'],'qrsig'=>$res['qrsig']];
        }
        
        if($account['code']=="qqpay_cloud" || $account['code']=="qqpay_wzq")
        {
            $res = $core->Api_CreatQrCodeInfo($account['qq'],$account['vcloudurl']);
            return ['code'=>200,'qrid'=>$account['id'],'qr_url'=>$res];
        }
        
        
        return ['code'=>201,'msg'=>'系统错误!'];
    }
    
    //获取扫码登录状态
    public static function getScanningStatus(array $scan_data){
        // 验证信息是否正确
        $result = self::verifyUserInfo($scan_data['token'],$scan_data['user_id']);
        if(!$result){
            return ['code' => 201 ,'msg' =>'用户信息错误'];
        }
        //创建Core对象
        $core  = new Core();
        $account = Db::table('ypay_account')->where('id',$scan_data['account_id'])->where('user_id', $scan_data['user_id'])->find();
        if(empty($account))
        {
            return ['code'=>201,'msg'=>'通道不存在!'];
        }
        if($account['type']=="alipay")
        {
            $data = $core->GetAlipayLoginStatus($account['remark']);
            if($data['code']==1)
            {
                $pid = getSubstr(base64_decode($data['cookie']),"CLUB_ALIPAY_COM=",";");
                Db::name('ypay_account')->where('id', $account['id'])->update(['cookie' => $data['cookie'],'status'=>1,'zfb_pid'=>$pid]);
                return ['code'=>200,'msg'=>'账号登录成功!','nick'=>"用户PID为：".$pid];
            }
            else
            {
                return $data;
            }
        }
        if($account['type']=="wxpay" && $account['code']!="wxpay_dy" && $account['code']!="qqpay_wzq" )
        {
            //检测微信登录扫码状态
            $res = $core->getWechatLoginStatus($account['wx_guid'],$account['remark'],$account['vcloudurl']);
            if($res->data->state==0)
            {
                return ['code'=>201,'msg'=>'等待扫码中!'];
            }
            else if($res->data->state==1)
            {
                return ['code'=>201,'msg'=>'已扫码待确认!'];
            }
            else
            {
                //登录账户
                $res = $core->getWechatLoginManual($account['wx_guid'],$res->data->wxid,$res->data->wxnewpass,$account['vcloudurl']);
                //判断返回信息
                if($res['code'] == -3){
                    return ['code'=>404,'msg'=>'信息错误!','nick'=>"登录环境检测失败,请重新扫码登录!"];
                }else if($res['code'] == -34){
                    return ['code'=>404,'msg'=>'取消登录!','nick'=>"你取消了登录!"];
                }else if($res['code'] == 0){
                    Db::name('ypay_account')->where('id', $account['id'])->update(['status' =>1]);
                    return ['code'=>200,'msg'=>'账号登录成功!','nick'=>"登录成功,点击更新按钮即可!"];
                }
                
            }
        }
        
        if($account['code']=="qqpay_mg")
        {   
            $data = $core->GetQLoginStatus($account['remark']);
            if($data['code']==1)
            {
                Db::name('ypay_account')->where('id', $account['id'])->update(['cookie' => $data['cookie'],'status'=>1,'remark'=>'']);
                return ['code'=>200,'msg'=>'账号登录成功!','nick'=>"登录QQ为：".$account['qq']];
            }
            else
            {
                return $data;
            }
        }
        
        if($account['code']=="qqpay_cloud" || $account['code']=="qqpay_wzq")
        {
            $res = $core->Api_GetOnlineQQlist($account['vcloudurl']);
            if(!empty($res)){
                $array = array_column($res['data']['bots'], 'id');
                $temp = in_array($account['qq'], $array);
                if($temp){
                    foreach ($res['data']['bots'] as $key => $value){
                        if($value['id'] == $account['qq']){
                            if($value['status'] == "登录完毕" || $value['status'] == "登录成功"){
                                Db::name('ypay_account')->where('id', $account['id'])->update(['status' =>1]);
                                return ['code'=>200,'msg'=>'账号登录成功!'];
                            }
                        }
                    }
                }
            }
            $res = $core->Api_GetQrCodeStatus($account['qq'],$account['vcloudurl']);
            if($res=="0")
            {
                Db::name('ypay_account')->where('id', $account['id'])->update(['status' =>1]);
                return ['code'=>200,'msg'=>'账号登录成功!'];
            }
            if($res=="53")
            {
                return ['code'=>201,'msg'=>'扫描成功，请在手机上点击确认...'];
            }
            if($res=="4")
            {
                return ['code'=>201,'msg'=>'二维码失效，请重新申请...'];
            }
            return ['code'=>201,'msg'=>'等待扫码中!'];
        }
        return ['code'=>201,'msg'=>'系统错误!'];
    }
    
    //获取订单日志
    public static function getOrderLog(array $data){
        // 验证信息是否正确
        $result = self::verifyUserInfo($data['token'],$data['user_id']);
        
        //验证通过则获取通道
        if($result){
            $account = model_order::getUserList($data['user_id']);
            json_encode($account, JSON_FORCE_OBJECT);
            return $account;
        }else{
            return ['code' => 201 , 'msg' => '用户信息错误'];
        }
    }
    
    //更新通道状态
    public static function getUpdateStatus(array $data){
        // 验证信息是否正确
        $result = self::verifyUserInfo($data['token'],$data['user_id']);
        
        //验证通过则获取通道
        if($result){
            $account = Db::name('ypay_account')
                ->where('id', $data['account_id'])
                ->where('user_id', $data['user_id'])
                ->find();
            if (empty($account)) {
                return ['code' => 201 , 'msg' => '通道不存在或无权限'];
            }
            //根据类型筛选
            switch ($data['type']) {
                case 'alipay':
                    $upArray = 
                    [
                        'status' => $data['status']
                    ];
                    if(isset($data['pid'])){
                       $upArray = 
                        [
                            'status' => $data['status'],
                            'zfb_pid' => $data['pid']
                        ]; 
                    }
                    Db::name('ypay_account')->where('id',$data['account_id'])->update($upArray);
                    break;
                case 'wxpay':
                    Db::name('ypay_account')->where('id',$data['account_id'])->update(['status' => $data['status']]);
                    break;
                case 'qqpay':
                    Db::name('ypay_account')->where('id',$data['account_id'])->update(['status' => $data['status']]);
                    break;
            }
            return ['code' => 200 , 'msg' => '状态更新成功'];
        }else{
            return ['code' => 201 , 'msg' => '用户信息错误'];
        }
    }
    
    //获取通道订单
    public static function getCheckOrder(array $data){
        // 验证信息是否正确
        $result = self::verifyUserInfo($data['token'],$data['user_id']);
        
        //验证通过则获取通道
        if($result){
            
            //获取账户信息
            $account = Db::name('ypay_account')
                ->where('id',$data['account_id'])
                ->where('user_id', $data['user_id'])
                ->find();
            
            //判断通道是否存在
            if(empty($account))
            {
                return ['code'=>201,'msg'=>'通道不存在'];
            }
            
            //清空数组
            $where = array();
            //构建查询参数
            $where = 
            [
                ['account_id','=',$account['id']],
                ['status','=',0],
                ['out_time','>',time()]
            ];
            
            //查询并返回订单数量
            $order = Db::name('ypay_order')->where($where)->order('id desc')->select();
            
            //声明数组
            $orderArray = [];
            
            //重新规划订单数组
            foreach ($order as $key => $value){
                //组装订单信息
                $orderArray[$key] = 
                [
                    'id' => $value['id'], //订单ID
                    'name' => $value['name'], //商品名称
                    'type' => $value['type'], //支付类型
                    'money' => $value['money'], //金额
                    'truemoney' => $value['truemoney'], //实付金额
                    'account_id' => $value['account_id'],//通道ID
                    'trade_no' => $value['trade_no'],//本地订单号
                    'out_trade_no' => $value['out_trade_no'], //商户订单号
                ];
            }
            
            //判断数组是否有数据
            if(empty($orderArray)){
                return ['code' => 201 , 'msg' => '暂未查询到此账户订单信息'];
            }
            
            return ['code' => 200,'msg' => '返回成功','data' => $orderArray];
        }else{
            return ['code' => 201 , 'msg' => '用户信息错误'];
        }
    }
    
    //执行订单回调
    public static function getNotify(array $data){
                // 验证信息是否正确
        $result = self::verifyUserInfo($data['token'],$data['user_id']);
        
        //验证通过则获取通道
        if($result){
            
            //获取账户信息
            $account = Db::name('ypay_account')
                ->where('id',$data['account_id'])
                ->where('user_id', $data['user_id'])
                ->find();
            
            //判断通道是否存在
            if(empty($account))
            {
                return ['code'=>201,'msg'=>'通道不存在'];
            }
            //清空数组
            $where = array();
            //根据类型筛选
            switch ($data['type']) {
                case 'alipay':
                    //获取用户配置信息
                    $basic = basic::where('user_id',$account['user_id'])->find();
                    if($basic['channelMode'] == 1){
                        //构建查询参数
                        $where = 
                        [
                            ['account_id','=',$account['id']],
                            ['status','=',0],
                            ['out_time','>',time()],
                            ['out_trade_no','=',$data['orderNo']],
                            ['truemoney','=',$data['money']]
                        ];
                    }else{
                        //构建查询参数
                        $where = 
                        [
                            ['account_id','=',$account['id']],
                            ['status','=',0],
                            ['out_time','>',time()],
                            ['truemoney','=',$data['money']]
                        ];
                    }
                    break;
                case 'wxpay':
                    //构建查询参数
                    $where = 
                    [
                        ['account_id','=',$account['id']],
                        ['status','=',0],
                        ['out_time','>',time()],
                        ['truemoney','=',$data['money']]
                    ];
                    break;
                case 'qqpay':
                    //构建查询参数
                    $where = 
                    [
                        ['account_id','=',$account['id']],
                        ['status','=',0],
                        ['out_time','>',time()],
                        ['truemoney','=',$data['money']]
                    ];
                    break;
            }

            //查询订单信息
            $order = Db::name('ypay_order')->where($where)->order('id desc')->find();
            
            //订单信息存在则执行回调操作
            if(!empty($order))
            {
                $url = Jialanshen::creat_callback($order);
                get_curl($url['notify']);
                return ['code'=>200,'msg'=>'回调成功!'];
            }
            else
            {
                return ['code'=>201,'msg'=>'订单超时或不存在'];
            }
        }else{
            return ['code' => 201 , 'msg' => '用户信息错误'];
        }
    }
    
    //手动补单
    public static function getRebackOrder(array $data){
                // 验证信息是否正确
        $result = self::verifyUserInfo($data['token'],$data['user_id']);
        
        //验证通过则获取通道
        if($result){
            $order = Db::name('ypay_order')->where('user_id',$data['user_id'])->where('id',$data['order_id'])->find();
            if(empty($order))
            {
                return ['code'=>201,'msg'=>'订单不存在!'];
            }
            $url = Jialanshen::creat_callback($order);
            $res = get_curl($url['notify']);
            if($res=='success' || $res =="fail")
            {
                Db::name('ypay_order')->where('id',$data['order_id'])->update(['api_memo' =>$res]);
            }
            else
            {
                Db::name('ypay_order')->where('id',$data['order_id'])->update(['api_memo' =>'error']);
            }
            return ['code'=>200,'msg'=>$res];
        }else{
            return ['code' => 201 , 'msg' => '用户信息错误'];
        }
    }
}
