<?php
declare (strict_types = 1);

namespace app\index\controller;
use think\facade\Cache;
use think\facade\Session;
use think\facade\Request;
use think\facade\View;
use app\common\service\YpayUser as S;
use app\common\model\YpayUser as M;
use app\common\validate\YpayUser as V;
use think\facade\Db;
use think\api\Client;
use app\common\model\AdminFrontLog as Log;
use app\common\util\Wxpusher as wxpusher;
use app\common\model\YpayDomain as domain;
use app\common\model\YpayTicket as ticket;
use app\common\model\YpayTicketCategory as ticket_category;
use app\common\service\Notice as notice;
use system\GoogleAuthenticator;

class My extends \app\BaseController
{
    protected $middleware = ['FrontCheck','Domain','ForceRealName','Mtce'];
    
    //控制台页面
    public function userpro()
    {
        View::assign(
            [
                'user' => S::getUser(),
                'vip' => S::getVip(),
            ]);
        if (Request::isAjax()){
            $data = Request::param('','','strip_tags');
            $validate = new V;
            if(!$validate->scene('edit')->check($data))
            return ['msg'=>$validate->getError(),'code'=>201];
            Db::name('ypay_user')->where('id', S::getUserId())->update(['mobile'=>$data['mobile'],'email'=>$data['email']]);
            return json(['code'=>1,'msg'=>'个人信息修改成功!']);
        }
        // 改变当前操作的模板路径
        getUserTemplate();
        return $this->fetch();
    }
    
    //我的工单页面
    public function ticket()
    {
        //判断是否开启工单功能
        if(getConfig()['isTicket'] != 1){
            // 重定向到index方法
             return redirect('/user/index');
        }
        if (Request::isAjax()) {
            $ticket = ticket::getUserList(S::getUserId());
            json_encode($ticket, JSON_FORCE_OBJECT);
            return $ticket;
        }
        View::assign(
            [
                'user' => S::getUser(),
                'vip' => S::getVip(),
                'ticket_category' => ticket_category::getTicketCategory()
            ]);
        // 改变当前操作的模板路径
        getUserTemplate();
        return $this->fetch();
    }
    
    //新增工单
    public function addTicket(){
        $data = Request::param('','','strip_tags');
        if(empty($data['title'])){
            return json(['code'=>201,'msg'=>'请输入标题!']);
        }
        if(empty($data['content'])){
            return json(['code'=>201,'msg'=>'请输入内容!']);
        }
        $data['creator_id'] = S::getUserId();
        try {
            notice::ticket_tips($data['creator_id'],'user');
            ticket::create($data);
            return json(['code'=>200,'msg'=>'新增成功!']);
        }catch (\Exception $e){
            return json(['msg'=>'操作失败'.$e->getMessage(),'code'=>201]);
        }
    }
    
    //删除工单
    public function delTicket(){
        $data = Request::param('','','strip_tags');
        try {
            ticket::where('id',$data['id'])->delete();
            return json(['code'=>200,'msg'=>'删除成功!']);
        }catch (\Exception $e){
            return json(['msg'=>'操作失败'.$e->getMessage(),'code'=>201]);
        }
    }
    
    //安全设置页面
    public function Security()
    {
        View::assign(
            [
                'user' => S::getUser(),
                'vip' => S::getVip(),
            ]);
        // 改变当前操作的模板路径
        getUserTemplate();
        return $this->fetch();
    }
    
    //通知设置页面
    public function Notifications()
    {
        if (Request::isAjax()){
            $this->getJson(S::saveNotifications(Request::param('','','strip_tags')));
        }
        View::assign(
            [
                'user' => S::getUser(),
                'vip' => S::getVip(),
                'basic' => S::getBasic(),
                'notice' => S::getNotice(),
                'noticeType' => S::getNoticeType()
            ]);
        // 改变当前操作的模板路径
        getUserTemplate();
        return $this->fetch();
    }
    
    //快捷绑定页面
    public function Connections()
    {
        View::assign(
            [
                'user' => S::getUser(),
                'vip' => S::getVip(),
                'bind_login' => S::quick_login(),
                'epwModel' =>S::emwModel()
            ]);
        // 改变当前操作的模板路径
        getUserTemplate();
        return $this->fetch();
    }
    
    //API管理界面页面
    public function api()
    {
        // 获取配置参数
        $config = getConfig();
        $request = \think\facade\Request::instance();
        // 判断网关地址
        $url = '';
        $urlArr = array();
        if ($config['is_pay_api'] == 1) {
            // 检查 pay_api 是否包含英文逗号
            if (strpos($config['pay_api'], ',')!== false) {
                // 如果包含英文逗号，将其分割为数组
                $pay_api_values = explode(',', $config['pay_api']);
                $urlArr = array();
                foreach ($pay_api_values as $index => $value) {
                    $line_name = '线路'.($index + 1);
                    $urlArr[] = array(
                        'name' => $line_name,
                        'url' => $value
                    );
                }
                // 取第一个数组的 url
                $url = $urlArr[0]['url'];
            } else {
                // 如果只有一个，将其存储为一个数组元素
                $urlArr = array(
                    array(
                        'name' => '线路1',
                        'url' => $config['pay_api']
                    )
                );
                // 取第一个数组的 url
                $url = $urlArr[0]['url'];
            }
        } else {
            $url = $request->root(true).'/';
        }
        $user = S::getUser();
        $user['appkey'] = S::getBasic()['appkey'];
        View::assign(
            [
                'user' => $user,
                'vip' => S::getVip(),
                'url'=> $url,
                'urlArr' => $urlArr
            ]);
        // 改变当前操作的模板路径
        getUserTemplate();
        return $this->fetch();
    }

    //获取APIID和密钥二维码
    public function getApiQrcode(){
        $data = Request::param('','','strip_tags');
        
        if(empty($data['url'])){
            return json(["code" => 201 , "msg" => "未获取到网址"]);
        }

        if(empty($data['id'])){
            return json(["code" => 201 , "msg" => "未获取到ID"]);
        }

        if(empty($data['key'])){
            return json(["code" => 201 , "msg" => "未获取到密钥"]);
        }

        $info = base64_encode('{"site":"'. $data['url'] .'","pid":'. $data['id'] .',"key":"'. $data['key'] .'"}');

        return json(["code" => 200 , "msg" => "获取成功" , "qrcode" => build_qrcode_url($info, '350x350')]);
    }
    
    //实名认证页面
    public function real_name()
    {
        
        View::assign(
            [
                'user' => S::getUser(),
                'vip' => S::getVip(),
            ]);
        $data = getConfig();
        //判断是否开启实名认证功能
        if($data['isRealName'] != 1){
            // 重定向到index方法
             return redirect('/user/index');
        }
        
        $array = 
        [
           ['id' => 'wechat' ,'name' => '微 信' , 'btnColor' => 'btn-success' , 'display' => 'block'],
           ['id' => 'ali' ,'name' => '支付宝' , 'btnColor' => 'btn-primary' , 'display' => 'block'] 
        ];
        
        if($data['realNameType'] == 2){
            foreach ($array as $key => $value) {
                if($value['id'] == 'wechat'){
                    $array[$key]['display'] = 'none';
                }
            }
        }
        
        View::assign('array',$array);
        // 改变当前操作的模板路径
        getUserTemplate();
        return $this->fetch();
    }
    
    //接收实名认证数据
    public function realname()
    {
        if (Request::isPost()){
            $config = getConfig();//获取系统配置参数
            //判断是否开启实名认证费用由用户承担
            $isRealName = false;
            if($config['realNameBear'] == 1){
                $user = M::where('id',S::getUserId())->find();
                $isRealName = true;
                if($config['bearMoney'] > $user['money']){
                    return json(['code' => 201,'msg'=>'实名认证费用为'.$config['bearMoney'].'元,请先充值']);
                }
            }
            $data = Request::param('','','strip_tags')["formData"];
            $name = $data[0]['value'];
            $idCard = $data[1]['value'];
            switch ($config['realNameType']) {
                case 1:
                    $type = Request::param('','','strip_tags')["type"];
                    if($type == 'wechat'){
                        $faceauthMode = 'WECHAT';
                    }else{
                        $faceauthMode = 'ZHIMACREDIT';
                    }
                    $request = \think\facade\Request::instance();
                    if(!empty($name) && !empty($idCard)){
                        $client = new Client($config['thinkCode']);
                        $result = $client->faceDetect()
                            ->withIdcard($idCard)
                            ->withName($name)
                            ->withCallbackUrl($request->root(true) . '/My/Real_name')
                            ->withNotifyUrl($request->root(true) . '/Notify/realName')
                            ->withFaceauthMode($faceauthMode)
                            ->request();
                        $qrCode = build_qrcode_url($result['data']['originalUrl']);
                        if($result['message'] == "Success" && $result['data']['status']){
                            // 开始扣除用户余额
                            if($isRealName){
                                M::money("-".$config['bearMoney'],S::getUserId(), '实名认证费用扣除');
                            }
                            return json(['code' => 200,'msg'=>'获取成功','qrcode' => $qrCode,'orderNumber' => $result['data']['orderNumber']] );
                        }else{
                            return json(['code' => 201,'msg'=>$result['message']]);
                        }
                    }
                    break;
                case 2:
                    if(!empty($name) && !empty($idCard)){
                        M::where('id',S::getUserId())->update(['name' =>$name,'idCard' =>$idCard]);
                        $url = Request::domain();
			            $redirectUrl = urlencode($url.'/Notify/aliRealName');
                        $urlObj["app_id"] = $config['appid'];
                        $urlObj["redirect_uri"] = "$redirectUrl";
                        $urlObj["cert_verify_id"] = S::getUserId();
                        $urlObj["scope"] = 'id_verify';
                        $urlObj["state"] = "STATE";
                        $buff = '';
                        foreach ($urlObj as $k => $v)
                        {
                            if($k != "sign") $buff .= $k . "=" . $v . "&";
                        }
                        $bizString = trim($buff, "&");
                        $qrCode = build_qrcode_url("https://openauth.alipay.com/oauth2/publicAppAuthorize.htm?".$bizString);
                        return json(['code' => 200,'msg'=>'获取成功','qrcode' => $qrCode,'orderNumber' => 'ali'] );
                    }
                    break;
            }

            return json(['msg' => '请填写完整信息' , 'code' => 201]);
        }
    }
    

    
    //判断实名认证是否绑定
    public static function getRealNameStatus(){
        $config = getConfig();//获取系统配置参数
        if(Request::isAjax()){
            $data = Request::param('','','strip_tags');
                if($data['orderNumber'] =='ali'){
                    $user = M::where('id', S::getUserId())->find();
                    if($user['is_realName'] == 1){
                        return json(['code' => 200 ,'msg' => '认证成功']);
                    }
                }else{
                   $client = new Client(getConfig()['thinkCode']);

                    $result = $client->faceQuery()
                        ->withOrderNumber($data['orderNumber'])
                        ->request();
                    if($result['data']['status'] == 1){
                        M::where('id', S::getUserId())->update(['is_realName' => 1,'name' =>$result['data']['name']     ,'idCard' =>$result['data']['idcard']]);
                        return json(['code' => 200 ,'msg' => '认证成功']);
                    } 
                }
                
            return json(['code'=>201 , 'msg' => '请进行实名认证']);
        }
 
    }
    
    
    //修改密码
    public function updatepwd()
    {
        if (Request::isAjax()){
            $this->getJson(S::goPass(Request::param('','','strip_tags')));
        }
        // 改变当前操作的模板路径
        getUserTemplate();
        return $this->fetch('Security');
    }
    
    //注销账户
    public function cancellation(){
       $res = Db::name('ypay_user')->where('id',S::getUserId())->delete();
       if($res){
          return json(['code'=>200]); 
       }
    }
    
    //邀请返利界面
    public function aff()
    {
        //判断是否开启邀请返利功能
        if(getConfig()['is_aff'] != 1){
            // 重定向到index方法
             return redirect('/user/index');
        }
        $request = \think\facade\Request::instance();
        $aff_percentage = getConfig()['aff_percentage']*100;
        $aff_people = Db::name('ypay_user')->where('superior_id',S::getUserId())->count();
        $affID = Db::name('ypay_user')->where('id',S::getUserId())->find()['superior_id'];
        $aff_money_array = Db::name('money_log')->where('user_id',S::getUserId())->select();
        $aff_money_today = Db::name('money_log')->where('user_id',S::getUserId())->whereDay('create_time')->select();
        $aff_money = 0;
        foreach ($aff_money_array as $key=>$value){
            if($value['memo'] == "下级购买会员套餐返利" || $value['memo'] == "下级充值返利"){
               $aff_money+= $value['money'];
            }
        }
        
        foreach ($aff_money_today as $key=>$value){
            if($value['memo'] == "下级购买会员套餐返利" || $value['memo'] == "下级充值返利"){
               $aff_today_money+= $value['money'];
            }
        }
        
        if(empty($aff_today_money)){
            $aff_today_money = 0;
        }
        
        if(empty($affID)){
            $affID = '暂无上级';
        }
        $data = [
            'aff_percentage' => $aff_percentage,
            'aff_people' => $aff_people,
            'aff_money' => $aff_money,
            'aff_today_money' => $aff_today_money,
            'affID' => $affID,
            'aff_url' => $request->root(true) . '/?aff=' .S::getUserId(),
            ];
         View::assign([
            'user' => S::getUser(),
            'vip' => S::getVip(),
            'data' => $data,
        ]);
        // 改变当前操作的模板路径
        getUserTemplate();
        return $this->fetch();
    }
    
    //获取信息
    public function affInfo(){
        if (Request::isAjax()){
            $getAffList = M::getAffList(S::getUserId());
            json_encode($getAffList, JSON_FORCE_OBJECT);
            return $getAffList;
        }
    }
    
    //域名审核界面
    public function is_domain()
    {
        //判断是否开启实名认证功能
        if(getConfig()['is_domain'] != 1){
            // 重定向到index方法
             return redirect('/user/index');
        }
        if (Request::isAjax()) {
            $domain = domain::getUserList(S::getUserId());
            json_encode($domain, JSON_FORCE_OBJECT);
            return $domain;
        }
        View::assign([
            'user' => S::getUser(),
            'vip' => S::getVip(),
        ]);
        // 改变当前操作的模板路径
        getUserTemplate();
        return $this->fetch();
    }
    
    //添加域名
    public function addDomain(){
        $data = Request::param('','','strip_tags');
        
        //获取系统配置参数
        $config = getConfig();
        
        if(empty($data['sitename'])){
            return json(['msg'=>'站点名称不能为空','code'=>201]);
        }
        if(empty($data['siteurl'])){
            return json(['msg'=>'站点域名不能为空','code'=>201]);
        }
        $data['user_id'] = S::getUserId();
        $data['status'] = 0;
        
        $data['siteurl'] = preg_replace('#^https?://#i', '', $data['siteurl']);
        $data['siteurl'] = rtrim($data['siteurl'], '/');
        
        $domainNum = $config['domainNum'];
        $num = domain::where('user_id',$data['user_id'])->withTrashed()->whereDay('create_time')->count();
        if(($domainNum !=0 && !empty($domainNum)) && $num >= $domainNum){
            return json(['msg'=>'当天域名提交审核次数已上限','code'=>201]);
        }
        
        
        //检查域名是否在白名单
        if(!empty($config['domain_white']) && strpos($config['domain_white'], $data['siteurl']) !== false){
           $data['status'] = 1; 
        }
        
        //检查域名是否在黑名单
        if(!empty($config['domain_black']) && strpos($config['domain_black'], $data['siteurl']) !== false){
           return json(['msg'=>'该域名已被平台拉黑','code'=>201]);
        }
        
        
        //判断是否开启了自动过审
        if($config['is_examine'] == 1){
            $data['status'] = 1;
        }
        
        try {
            notice::domain_tips($data['user_id']);
            domain::create($data);
            return json(['code'=>200,'msg'=>'新增成功!']);
        }catch (\Exception $e){
            return json(['msg'=>'操作失败'.$e->getMessage(),'code'=>201]);
        }
    }
    
    //修改域名
    public function editDomain(){
        $data = Request::param('','','strip_tags');
        if(empty($data['sitename'])){
            return json(['msg'=>'站点名称不能为空','code'=>201]);
        }
        if(empty($data['siteurl'])){
            return json(['msg'=>'站点域名不能为空','code'=>201]);
        }
        $domainNum = getConfig()['domainNum'];
        $num = domain::where('user_id',$data['user_id'])->withTrashed()->whereDay('create_time')->select()->count();
        if(($domainNum !=0 && !empty($domainNum)) && $num >= $domainNum){
            return json(['msg'=>'当天域名提交审核次数已上限','code'=>201]);
        }
        $data['status'] = 0;
        try {
            notice::domain_tips($data['user_id']);
            domain::update($data);
            return json(['code'=>200,'msg'=>'提交成功!']);
        }catch (\Exception $e){
            return json(['msg'=>'操作失败'.$e->getMessage(),'code'=>201]);
        }
    }
    
    //删除域名
    public function delDomain(){
        $data = Request::param('','','strip_tags');
        try {
            $domain = domain::find($data['id']);
            // 软删除
            $domain->delete();
            return json(['code'=>200,'msg'=>'删除成功!']);
        }catch (\Exception $e){
            return json(['msg'=>'操作失败'.$e->getMessage(),'code'=>201]);
        }
    }
    
    //快捷登录解绑
    public function Unbinding(){
        if (Request::isAjax()){
            $data = Request::param('','','strip_tags');
            if($data['type'] == 'WxPusher'){
                $update = ['wxpusher_uid'=>''];
            }else{
                $update = ['is_bind'.$data['type'] => 0,$data['type'] . '_sid'=>''];
            }
            
            
            try {
               Db::name('ypay_user')->where('id', S::getUserId())->update($update);
               return json(['code'=>1,'msg'=>'解绑成功!']);
            } catch (\Exception $e) {
              return ['msg'=>'操作失败'.$e->getMessage(),'code'=>201];
            }
            
            
        }
    }
    
    
    public function loginlog()
    {
        $log = Log::getUserList(S::getUserId());
        json_encode($log, JSON_FORCE_OBJECT);
        return $log;
    }
    
    //重置密钥
    public function GeneratingKey()
    {
        return json(['key'=>S::goUserKey(),'code'=>1]);
    }

        
    //重置密钥
    public function goAPPKey()
    {
        return json(['key'=>S::goAPPKey(),'code'=>1]);
    }
    
    //获取WxPusher关注二维码
    public function getWxPusherQrCode(){
        $Token = getConfig()['wxpusher_appToken'];//获取WxPuserToken
        $wxpusher = new wxpusher($Token);
        $qrCode = $wxpusher->Qrcreate(S::getUserId(),1800);//获取二维码信息
        //判断内容是否正确返回
        if(is_array($qrCode)){
            return json(['code'=>1,'msg'=>$qrCode['shortUrl']]);
        }else{
            return json(['code'=>2,'msg'=>$qrCode]);
        }
    }
    
    //判断WxPusherUID是否绑定
    public function getWxPusherUID(){
        $data = Request::param('','','strip_tags');//获取提交的数据
        $user = M::where('id',S::getUserId())->find();//获取用户信息
        //判断是绑定还是修改
        if($data['operate'] == 'bind'){
            if(!empty($user['wxpusher_uid'])){
                return json(['code'=>1]);
            } 
        }else{
            if($user['wxpusher_uid'] != $data['uid']){
                return json(['code'=>1]);
            }
        }
        
        return json(['code'=>2]);
    }
    
    //提交WxPusher数据
    public function savaWxPuserUID(){
        $data = Request::param('','','strip_tags');//获取提交的数据
        $user = M::where('id',S::getUserId())->find();//获取用户信息
        //判断是否手动填写了WxPuser_uid
        if(!empty($data['wxpusher_uid'])){
            try {
               M::where('id',S::getUserId())->update(['wxpusher_uid' => $data['wxpusher_uid']]);
               return json(['code'=>1,'msg'=>'绑 定 成 功!']);
            } catch (Exception $e) {
                return ['msg'=>'操作失败'.$e->getMessage(),'code'=>201];
            }
        }
        
        if(!empty($user['wxpusher_uid'])){
            return json(['code'=>1,'msg'=>'绑 定 成 功!']);
        }else{
            return json(['code'=>2,'msg'=>'请 用 微 信 扫 码 关 注']);
        }
        
    }
    
    //获取解绑手机/邮箱验证码
    public function getUBindCode(){
        $data = Request::param('','','strip_tags');
        return $this->getJson(S::getCode('UBind',$data['mobile'],$data['email'],$data['bind']));
    }
    
    //获取绑定手机/邮箱验证码
    public function getBindCode(){
        $data = Request::param('','','strip_tags');
        return $this->getJson(S::getCode('bind',$data['mobile'],$data['email'],$data['bind']));
    }
    
    //执行绑定或者解绑邮箱操作
    public function bindOrUBindEmail(){
        // 获取页面提交的数据传值
        if (Request::isAjax()){
            
            $data = Request::param('','','strip_tags');
            $msg = '绑定成功';
            $update = $data['email'];
            if($data['type'] == 2){
                $update = null;
                $msg = '解绑成功';
            }
            
            //验证验证码是否为空
            $is_captcha =  S::is_captcha(1,$data['captcha']);
            // 判断是否有值返回
            if(!empty($is_captcha)){
               return  $this->getJson($is_captcha);
            }
            //验证
            $validate = new V;
            //验证数据是否填写
            if(!$validate->scene('email')->check($data))return ['msg'=>$validate->getError(),'code'=>201];
            //验证验证码是否正确
            $code = Cache::get('captcha');
            if($data['captcha']==$code)//验证通过
            {
                try {
                    M::where('id',S::getUserId())->update(['email' => $update]);
                    return json(['code'=>1,'msg'=>$msg]);
                } catch (\Exception $e) {
                  return json(['msg'=>'操作失败'.$e->getMessage(),'code'=>201]);
                }
            }
            else
            {
                return json(['msg'=>'验证码错误','code'=>201]);
            }
            
            
        }
    }
    
    //执行绑定或者解绑手机号操作
    public function bindOrUBindMobile(){
        // 获取页面提交的数据传值
        if (Request::isAjax()){
            
            $data = Request::param('','','strip_tags');
            $msg = '绑定成功';
            $update = $data['mobile'];
            if($data['type'] == 2){
                $update = null;
                $msg = '解绑成功';
            }
            
            //验证验证码是否为空
            $is_captcha =  S::is_captcha(1,$data['captcha']);
            // 判断是否有值返回
            if(!empty($is_captcha)){
               return  $this->getJson($is_captcha);
            }
            //验证
            $validate = new V;
            //验证数据是否填写
            if(!$validate->scene('mobile')->check($data))return ['msg'=>$validate->getError(),'code'=>201];
            //验证验证码是否正确
            $code = Cache::get('captcha');
            if($data['captcha']==$code)//验证通过
            {
                try {
                    M::where('id',S::getUserId())->update(['mobile' => $update]);
                    return json(['code'=>1,'msg'=>$msg]);
                } catch (\Exception $e) {
                  return json(['msg'=>'操作失败'.$e->getMessage(),'code'=>201]);
                }
            }
            else
            {
                return json(['msg'=>'验证码错误','code'=>201]);
            }
            
            
        }
    }
    
    //谷歌验证界面
    public function GoogleAuth(){
        // 获取页面提交的数据传值
        if (Request::isAjax()){
            
            $data = Request::param('','','strip_tags');
            //获取用户的密钥信息
            $google =new GoogleAuthenticator();
            $user = M::where('id',S::getUserId())->find();
            //$google_secret 存入的谷歌秘钥  ，$code 谷歌动态验证码
            $checkResult = $google->verifyCode($user['googlekey'], $data['code'], 4);
            if ($checkResult)
            {
                $info = [
                    'id' => S::getUserId(),
                    'isAuth' => true
                ];
                Session::set('front_auth', $info);
                return json(['code'=>200,'msg'=>'验证成功']);
            }
            else
            {
                return json(['code'=>201,'msg'=>'谷歌验证码错误']);
            }
        }
        View::assign([
            'user' => S::getUser(),
            'vip' => S::getVip(),
        ]);
        // 改变当前操作的模板路径
        getUserTemplate();
        return $this->fetch();
    }
    
    //请求谷歌验证二维码
    public function getGoogleAuthQrCode(){
        $user = M::where('id',S::getUserId())->find();
        if(!empty($user['googlekey']))
        {
            return json(['code'=> 201 , 'msg'=>'此账号已绑定']);
        }
        //谷歌验证码
        $google=new GoogleAuthenticator();
        //生成验证秘钥
        $secret=$google->createSecret();
        //生成验证二维码 $username 需要绑定的用户名
        $qrCodeUrl = $google->getQRCodeGoogleUrl(S::getUserId(), $secret);
        Session::set('secret', $secret);
        return json(['code'=>200,'msg'=>$qrCodeUrl]);
    }
    
    //绑定谷歌信息
    public function bindGoogleAuth(){
        $data = Request::param('','','strip_tags');
        $user = M::where('id',S::getUserId())->find();
        if(empty($user['googlekey']))
        {
            //获取session信息
            $secret = Session::get('secret');
            $google =new GoogleAuthenticator();
            $checkResult = $google->verifyCode($secret, $data['code'], 4);
            if ($checkResult)
            {
                M::where('id', S::getUserId())->update(['googlekey' =>$secret]);
                return json(['code'=>200,'msg'=>'绑定成功']);
            }
            else
            {
                return json(['code'=>201,'msg'=>'谷歌验证码错误或未绑定']);
            }
        }
        else
        {
            return json(['code'=>201,'msg'=>'此账号已绑定']);
        }
    }
    
    //解绑谷歌验证码
    public function uBindGoogleAuth(){
        $data = Request::param('','','strip_tags');
        //获取用户的密钥信息
        $google =new GoogleAuthenticator();
        $user = M::where('id',S::getUserId())->find();
        //$google_secret 存入的谷歌秘钥  ，$code 谷歌动态验证码
        $checkResult = $google->verifyCode($user['googlekey'], $data['code'], 4);
        if ($checkResult)
        {
            M::where('id', S::getUserId())->update(['googlekey' =>'']);
            return json(['code'=>200,'msg'=>'解绑成功']);
        }
        else
        {
            return json(['code'=>201,'msg'=>'谷歌验证码错误']);
        }
    }
    
}
