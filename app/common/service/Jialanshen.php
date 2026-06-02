<?php
declare (strict_types = 1);

namespace app\common\service;
use think\facade\Db;
use think\facade\Request;
use app\common\model\YpayOrder as M;
use app\common\model\YpayUser as S;
use app\common\service\YpayUser;
use app\common\model\YpayUserbasic as basic;
use app\common\model\YpayVip as vip;
use app\common\service\Notice as notice;
use app\common\core\core;


class Jialanshen
{
    protected static function normalizeChannelMode(&$basic)
    {
        if (empty($basic)) {
            return;
        }
        $mode = (string) ($basic['channelMode'] ?? '');
        if (!in_array($mode, ['1', '2', '3', '4'], true)) {
            $basic['channelMode'] = 2;
        }
    }
    
    //支付异步同调方法
    public static function creat_callback($data)
    {
        $userinfo = S::where('id',$data['user_id'])->find();//获取用户信息
        $basic = basic::where('user_id',$data['user_id'])->find();//获取用户配置参数
        self::normalizeChannelMode($basic);
        $order = Db::name('ypay_order')->where('id',$data['id'])->find();
        
        //判断是否开启回调去除商品名称
        if($basic['callback_hiddenName'] == 1){
            $sign = MD5("money=".$data['money']."&out_trade_no=".$data['out_trade_no']."&pid=".$data['user_id']."&trade_no=".$data['trade_no']."&trade_status=TRADE_SUCCESS&type=".$data['type'].$userinfo['user_key']);
            $array=array('pid'=>$data['user_id'],'trade_no'=>$data['trade_no'],'out_trade_no'=>$data['out_trade_no'],'type'=>$data['type'],'money'=>$data['money'],'trade_status'=>'TRADE_SUCCESS'); 
        }else{
            $sign = MD5("money=".$data['money']."&name=".$data['name']."&out_trade_no=".$data['out_trade_no']."&pid=".$data['user_id']."&trade_no=".$data['trade_no']."&trade_status=TRADE_SUCCESS&type=".$data['type'].$userinfo['user_key']);
            $array=array('pid'=>$data['user_id'],'trade_no'=>$data['trade_no'],'out_trade_no'=>$data['out_trade_no'],'type'=>$data['type'],'name'=>$data['name'],'money'=>$data['money'],'trade_status'=>'TRADE_SUCCESS');
        }
        

        if($data['status']==0)
        {
            if($order['is_order_tips'] == 0 && $basic['order_tips'] != 'close' && !empty($basic['order_tips'])){
                Db::name('ypay_order')->where('id', $data['id'])->update(['status' =>1,'is_order_tips'=>1,'end_time'=>date('Y-m-d H:i:s', time())]);
                $order = Db::name('ypay_order')->where('id', $data['id'])->find();
                //调用通知方法
                notice::order_tips($userinfo,$order,$basic);
            }else{
                Db::name('ypay_order')->where('id', $data['id'])->update(['status' =>1,'end_time'=>date('Y-m-d H:i:s', time())]);
            }
            S::money("-".$data['feilvmoney'],$data['user_id'], '商户费率扣除');
            if($basic['money_tips'] >= $userinfo['money'] && $basic['is_money_tips'] != 'close' && !empty($basic['is_money_tips'])){
                //调用通知方法
                notice::money_tips($userinfo,$basic);
            }
        }
        $urlstr=http_build_query($array);
        //更改订单状态,商户单号、结束时间
        if(strpos($data['notify_url'],'?'))
        {
            $url['notify']=$data['notify_url'].'&'.$urlstr.'&sign='.$sign.'&sign_type=MD5';
        }
        else
        {
            $url['notify']=$data['notify_url'].'?'.$urlstr.'&sign='.$sign.'&sign_type=MD5';
        }
        if(strpos($data['return_url'],'?'))
        {
            $url['return']=$data['return_url'].'&'.$urlstr.'&sign='.$sign.'&sign_type=MD5';
        }
        else
        {
            $url['return']=$data['return_url'].'?'.$urlstr.'&sign='.$sign.'&sign_type=MD5';
        }
		return $url;
    }
    
    //创建订单
    public static function create_order($trade_no,$QR_row,$data,$user,$type){
        //查询用户配置信息
        $basic = basic::where('user_id',$user['id'])->find();
        self::normalizeChannelMode($basic);
        //获取用户VIP信息
        $vip = vip::where('id',$user['vip_id'])->find();
        
        //判断站点名称是否填写 未填写即默认为空
        if(empty($data['sitename'])){
            $data['sitename'] = "";
        }
        $cashierMode = (string)($basic['cashierMode'] ?? '2');
        $isQrCashierMode = ($cashierMode === '3');
        
        //判断是否开启了订单加费
        if(!empty($vip)){
            //需要会员组开启此功能和判断用户是否把费率承担改为他的客户
            if($vip['is_profiteer'] == 1 && $basic['is_rate'] == 1){
                $money = $data['money'] + ($data['money'] * $user['feilv'] / 100);
                $feilv_money = $data['money'] * $user['feilv'] / 100;
            }else{
                $money = $data['money'];
                $feilv_money = $data['money'] * $user['feilv'] / 100;
            }
        }else{
            $money = $data['money'];
            $feilv_money = $data['money'] * $user['feilv'] / 100;
        }
        
        //转换金额类型,且最多保留2位小数
        $money = floatval($money);
        $feilv_money = floatval($feilv_money);
        $money = round($money, 2);
        $feilv_money = round($feilv_money, 2); 

        $i = 1;
        
        //判断是否有相同金额的订单,有则加0.01 - 0.1
        while(true)
        {
            $ods = M::where('truemoney',$money)->where('status',0)->where('account_id',$QR_row['id'])->where('out_time','>',time())->order('id desc')->find();
            if(empty($ods))
            {
                break;
            }
            else
            {
                if($type != 'lkl' && $type != 'lebrush' && $type != 'dougong'){
                    
                    //没设置浮动金额则默认调用官方提供
                    if(!empty($basic['floating_amount'])){
                        $arr = explode(",", $basic['floating_amount']);
                        $rand_keys=array_rand($arr,1);
                        $number=$arr[$rand_keys];
                        
                        //保留初始金额
                        if($i == 1){
                            $temp_money = $money;
                        }
                        $money = sprintf("%.2f",$money + floatval($number));
                        //适配递减规则 如果金额小于0 则进入官方定义付款组
                        if(0 > $money){
                            $arr=['0.01','0.02','0.03','0.04','0.05','0.06','0.07','0.08','0.09','0.1'];
                            $rand_keys=array_rand($arr,1);
                            $number=$arr[$rand_keys];
                            $money = $temp_money + floatval($number);
                            break;
                        }
                    }else{
                        $arr=['0.01','0.02','0.03','0.04','0.05','0.06','0.07','0.08','0.09','0.1'];
                        $rand_keys=array_rand($arr,1);
                        $number=$arr[$rand_keys];
                        $money = $money + floatval($number);
                        break;
                    }
                }else{
                    break;
                }
            }
            
            $i++;
        }
        //筛选类型
        switch ($type) {
            case 'alipay':
                    if ($isQrCashierMode) {
                        $qrLink = trim((string)($QR_row['qr_url'] ?? ''));
                        if ($qrLink === '') {
                            return ['code' => 201, 'msg' => '支付宝收款码未配置'];
                        }
                        if (!preg_match('/^https?:\\/\\//i', $qrLink)) {
                            $qrLink = rtrim(Request::domain(), '/') . '/' . ltrim($qrLink, '/');
                        }
                        $qrcode = urlencode($qrLink);
                        $h5url = 'alipayqr://platformapi/startapp?saId=10000007&qrcode=' . urlencode($qrLink);
                    } else {
                        if($basic['channelMode'] == 1){
                            $qrcode = urlencode('https://ds.alipay.com/?from=pc&appId=20000116&actionType=toAccount&goBack=NO&amount=' . $money . '&userId='.$QR_row['zfb_pid'].'&memo=' . $data['out_trade_no']);
                            $h5url = 'alipayqr://platformapi/startapp?saId=10000007&qrcode='.urlencode('https://ds.alipay.com/?from=pc&appId=20000116&actionType=toAccount&goBack=NO&amount=' . $money . '&userId='.$QR_row['zfb_pid'].'&memo=' . $data['out_trade_no'] . '');
                        }
                        if($basic['channelMode'] == 2){
                            $qrcode = urlencode('https://ds.alipay.com/?from=pc&appId=20000116&actionType=toAccount&goBack=NO&amount=' . $money . '&userId='.$QR_row['zfb_pid']. '');
                            $h5url = 'alipayqr://platformapi/startapp?saId=10000007&qrcode='.urlencode('https://ds.alipay.com/?from=pc&appId=20000116&actionType=toAccount&goBack=NO&amount=' . $money . '&userId='.$QR_row['zfb_pid'].'');
                        }
                        
                        if($basic['channelMode'] == 3){
                            $qrcode = urlencode('https://ds.alipay.com/?from=pc&appId=20000116&actionType=toAccount&goBack=NO&userId='.$QR_row['zfb_pid']. '');
                            $h5url = 'alipayqr://platformapi/startapp?saId=10000007&qrcode='.urlencode('https://ds.alipay.com/?from=pc&appId=20000116&actionType=toAccount&goBack=NO&userId='.$QR_row['zfb_pid'].'');
                        }
                        
                        if($basic['channelMode'] == 4){
    
                            $qrcode = urlencode(Request::domain().'/url.php?user_id='.$QR_row['zfb_pid'].'&price='.$money.'&trade_no='.$data['out_trade_no']);
                            $h5url = 'alipayqr://platformapi/startapp?saId=20000032&url='.urlencode('alipayqr://platformapi/startapp?appId=20000123&actionType=scan&biz_data=%7B%22s%22%3A%22money%22%2C%22u%22%3A%22'.$QR_row['zfb_pid'].'%22%2C%22a%22%3A%22'.$money.'%22%2C%22m%22%3A%22'.$data['out_trade_no'].'%22%7D');
    
                        }
                    }
                break;
            case 'wxpay':
                // 创建core对象
                $core = new Core();
                //获取账户信息
                $account = Db::name('ypay_account')->where('id', $QR_row['id'])->find();
                if($QR_row['code'] == 'wxpay_dy' || $QR_row['code'] == 'wxpay_software' || $QR_row['code'] == 'wxpay_cloudzs'){//判断是否是微信店员通道
                    if($QR_row['code'] == 'wxpay_cloudzs'){
                        $key =  empty($QR_row['qr_url'])? '':json_decode($QR_row['qr_url'], true);
                        
                        $qrcode = empty($key) ? $QR_row['qr_url']:$key['qr_url'];
                    }else{
                        $qrcode = $QR_row['qr_url'];
                    }
                    
                    $h5url = 'weixin://'; 
                }elseif($QR_row['code'] == 'wxpay_cloud'){//判断是否是微信云端通道
                    //生成微信支付金额
                    $wx_fen = intval(strval($money*100));
                    $cloud = Db::name('ypay_cloud')->where('id',$account['cloud_id'])->find();
                    // 判断是uos还是mac云端
                    if($cloud['cloud_type'] == 2){
                        $qrcode = $QR_row['qr_url'];
                    }else{
                        //生成付款二维码
                        $res = $core->getWechatTransferSet($QR_row['wx_guid'],$wx_fen,$data['out_trade_no'],$account['cloud_id'],$account);
                        //判断信息是否为空
                    if(isset($res->data->reqText->buffer))
                    {
                        $wxres = json_decode($res->data->reqText->buffer,true);
                        if(!empty($wxres))
                        {
                            if($wxres['retcode']==0)
                            {
                                $qrcode = $wxres['pay_url'];
                            }
                            else
                            {
                                $qrcode = "账号被风控!";
                                Db::name('ypay_account')->where('id', $QR_row['id'])->update(['status' =>0]);
                                $notes = '你好！，通道ID为：'.$QR_row['id'].'的微信通道风控,已强制下线，请勿继续登录';
                                self::lose_expire($user,$basic,$QR_row['id'],'wxpay',$notes);
                                return false;
                            }
                        }
                        else
                        {
                            $qrcode = '生成错误,请稍后再试!';
                            Db::name('ypay_account')->where('id', $QR_row['id'])->update(['status' =>0]);
                            $notes = '生成错误,请稍后再试!';
                            self::lose_expire($user,$basic,$QR_row['id'],'wxpay',$notes);
                            return false;
                        }
                    }else if(isset($res->Data->retText->buffer)){
                        $wxres = (array)json_decode(base64_decode($res->Data->retText->buffer,true));
                        if(!empty($wxres))
                        {
                            if($wxres['retcode']==0)
                            {
                                $qrcode = $wxres['pay_url'];
                            }
                            else
                            {
                                $qrcode = "账号被风控!";
                                Db::name('ypay_account')->where('id', $QR_row['id'])->update(['status' =>0]);
                                $notes = '你好！，通道ID为：'.$QR_row['id'].'的微信通道风控,已强制下线，请勿继续登录';
                                self::lose_expire($user,$basic,$QR_row['id'],'wxpay',$notes);
                                return false;
                            }
                        }
                        else
                        {
                            $qrcode = '生成错误,请稍后再试!';
                            Db::name('ypay_account')->where('id', $QR_row['id'])->update(['status' =>0]);
                            $notes = '生成错误,请稍后再试!';
                            self::lose_expire($user,$basic,$QR_row['id'],'wxpay',$notes);
                            return false;
                        }
                    }
                    else
                    {
                        $wx_url = '网络错误,请稍后再试!'; 
                        Db::name('ypay_account')->where('id', $QR_row['id'])->update(['status' =>0]);
                        $notes = '你好！，通道ID为：'.$QR_row['id'].'的微信通道云端连接异常，已下线';
                        self::lose_expire($user,$basic,$QR_row['id'],'wxpay',$notes);
                        return false;
                    }
                    }
                    $h5url = 'weixin://'; 
                }else if($QR_row['code'] == 'wxpay_jym_cloud'){
                               //生成微信支付金额
                    $wx_fen = intval(strval($money*100));
                    $cloud = Db::name('ypay_cloud')->where('id',$account['cloud_id'])->find();
                    // 判断是uos还是mac云端
                    if($cloud['cloud_type'] == 2){
                        $qrcode = $QR_row['qr_url'];
                    }else{
                        //生成付款二维码
                        $res = $core->getWechatTransferSet($QR_row['wx_guid'],$wx_fen,$data['out_trade_no'],$account['cloud_id'],$account);
                        //判断信息是否为空
                    if(isset($res->Data))
                    {

                        $qrcode = $res->Data->qrcode;
                        $h5url = $res->Data->urllink; 
                    }
                    else
                    {
                        $wx_url = '网络错误,请稍后再试!'; 
                        Db::name('ypay_account')->where('id', $QR_row['id'])->update(['status' =>0]);
                        $notes = '你好！，通道ID为：'.$QR_row['id'].'的微信通道云端连接异常，已下线';
                        self::lose_expire($user,$basic,$QR_row['id'],'wxpay',$notes);
                        return false;
                    }
                    }
                }else if($QR_row['code'] == 'wxpay_skd'){
                    //执行生成二维码
                    $mch = $core->WXJSLogin_mch($account['cloud_id'],$QR_row['wx_guid']);
                    $mch = $mch['mch'];
                    $smxx = $core->WXJSLogin_shop($account['cloud_id'],$QR_row['wx_guid'],$mch);
                    $shop_id = $smxx['shop_id'];
                    $sid = $smxx['sid'];
                    $screceipt = $core->WXJSLogin_receipt($sid,$money,$trade_no,$shop_id,$mch);
                    $ewm = $core->WXJSLogin_qrcode($sid,$screceipt['receipt_id'],$mch);
                    $ew = $ewm['qrcode'];
                    file_put_contents('./ewm/'.$trade_no.'.jpg', base64_decode($ew));
                    $qrcode = '/ewm/'.$trade_no.'.jpg';
                    $h5url = 'weixin://'; 
                }

                
                break;
            case 'qqpay':
                // 创建core对象
                $core = new Core();
                //获取账户信息
                $account = Db::name('ypay_account')->where('id', $QR_row['id'])->find();
                if($QR_row['code'] == 'qqpay_cloud'){
                    $cookie = $core->Api_GetCookies($QR_row['qq'],$account['cloud_id']);
                    //$cookie = json_encode($cookie,true);
                    $skey = getSubstr($cookie,"skey=",";");
                    $pskey = getSubstr($cookie,"p_skey=",";");
                    $qrcode = urlencode($core->QTransferSet($QR_row['qq'],$trade_no,$money,$skey,$pskey));
                    if($qrcode=="生成失败")
                    {
                        $qrcode = urlencode($QR_row['qr_url']);
                    }
                }else if($QR_row['code'] == 'qqpay_mg'){
                    //金额确定后获取免输入二维码
                    $cookie = base64_decode($QR_row['cookie']);
                    $skey = getSubstr($cookie,"skey=",";");
                    $pskey = getSubstr($cookie,"p_skey=",";");
                    $qrcode = urlencode($core->QTransferSet($QR_row['qq'],$trade_no,$money,$skey,$pskey));
                    if($qrcode=="生成失败")
                    {
                        $qrcode = urlencode($QR_row['qr_url']);
                    }
                }else{
                    $qrcode = "ewmLoading";
                    $qrcode = urlencode($qrcode);
                }
                if($QR_row['code'] == 'qqpay_wzq'){
                    if(empty($account['cloud_id'])){
                        $cookie = base64_decode($QR_row['cookie']);
                    }else{
                        $cookie = $core->Api_GetCookies($QR_row['qq'],$account['cloud_id']);
                    }
                    
                    $h5url = $core->getWZQH5Url($cookie,$QR_row['qq'],$money,$money);
                    
                    if(isset($h5url['code']) && $h5url['code'] == 201){
                        return $h5url;
                    }
                    
                    //判断是否请求的是IP + 端口 或者 域名拼接端口访问
                    $port = Request::port();
                    if($port != 443 && $port != 80){
                        $qrcode = Request::domain().':'.Request::port().'/Pay/console?trade_no='.$trade_no;
                    }else{
                        $qrcode = Request::domain().'/Pay/console?trade_no='.$trade_no;
                    }
                    $type = 'wxpay';
                }else{
                    $h5url = base64_encode('https://qun.qq.com/qrcode/index?data='.$qrcode);
                    $h5url = 'mqqapi://forward/url?version=1&src_type=web&url_prefix='.$h5url;
                }
                break;
            case 'dougong':
                    
                    if($QR_row['code'] == 'dougong_alipay'){
                        $type = "alipay";
                        
                        $dougong_temp = 
                        [
                            'appid' => $QR_row['zfb_pid'],
                            'product_id' => $QR_row['wxname'],
                            'huifu_public_key' => $QR_row['cookie'],
                            'merchant_private_key' => $QR_row['qr_url'],
                            'appmchid' =>null,
                            'trade_no' => $trade_no,
                            'money' => $money,
                            'req_date' => date('Ymd',time()),
                            'time_expire' =>  $dtime = date("YmdHis", time() + $basic['timeout_time']),
                            'name' => $data['name'],
                            'client_ip' =>get_client_ip()
                        ];

                        $result = \app\plugins\dougong\dougong_plugin::alipay($dougong_temp);
                        
                        if($result['type'] = 'qrcode'){
                            $qrcode = $result['url'];
                            $h5url = $result['url'];
                        }else{
                            echo $result['msg'];
                            exit;
                        }
                    }else{
                        $type = "wxpay";
                        
                        $dougong_temp = 
                        [
                            'appid' => $QR_row['zfb_pid'],
                            'product_id' => $QR_row['wxname'],
                            'huifu_public_key' => $QR_row['cookie'],
                            'merchant_private_key' => $QR_row['qr_url'],
                            'appmchid' =>null,
                            'trade_no' => $trade_no,
                            'money' => $money,
                            'name' => $data['name'],
                            'project_id' => $QR_row['remark'],
                            'time_expire' =>  $dtime = date("YmdHis", time() + $basic['timeout_time']),
                            'req_date' => date('Ymd',time()),
                            'client_ip' =>get_client_ip()
                        ];

                        $result = \app\plugins\dougong\dougong_plugin::hostingOrder($dougong_temp);
                        
                        if($result['code'] == 200){
                            $qrcode = $result['url'];
                            $h5url = 'weixin://';
                        }else{
                            echo $result['msg'];
                            exit;
                        }
                        
                        
                    }
                break;
            case 'lebrush':
                    $lebrush_temp = 
                    [
                        'appid' => $QR_row['zfb_pid'],
                        'private_key' => $QR_row['wxname'],
                        'trade_no' => $trade_no,
                        'money' => $money,
                        'name' => $data['name'],
                    ];
                    $result = \app\plugins\lebrush\lebrush_plugin::pay($lebrush_temp);
                    
                 if($QR_row['code'] == 'lebrush_alipay'){
                     $type = "alipay";
                 }else{
                     $type = "wxpay";
                 }
                $qrcode = $result;
                $h5url = $result;  
                break;
            case 'usdt':
                $ret = get_curl("https://sp0.baidu.com/5LMDcjW6BwF3otqbppnN2DJv/finance.pae.baidu.com/vapi/async/v1?from_money=%E4%BA%BA%E6%B0%91%E5%B8%81&to_money=%E7%BE%8E%E5%85%83&from_money_num=". $data['money'] ."&srcid=5293&sid=282626_284830_110085_287513_287067_287700_287836_287168_280169_288370_283782_288270_287981_288710_288713_288717_288742_288747_288748_284553_287634_281879_288152_284820_289082_265881_289541_289948_289955_282932_290205_290178_290365_286491_290555_290562_282553_282805_287977_290976_291233_290521_277936_290424_256739_290666_288253_291481_290056_288559_286862_291710_291726_290567_283016_291948_282228_292167_292082_292247_292250_292251_292355_287174_287718_282466_292508_292345_292710_292773_292786_292413_292460_292454_292822_289739&cb=jsonp_1705301850137_11480");
                $json = jsonp_decode($ret, true);
                if ($json['ResultCode'] == 0) {
                    // 去掉money2_num中的逗号
                    $money2_num = str_replace(',', '', $json['Result'][0]['DisplayData']['resultData']['tplData']['money2_num']);
                    // 转换成浮点数并保留两位小数
                    $money = round(floatval($money2_num), 2);
                }
                       //判断是否有相同金额的订单,有则加0.01 - 0.1
          
        while(true)
        {
            $ods = M::where('truemoney',$money)->where('status',0)->where('account_id',$QR_row['id'])->where('out_time','>',time())->order('id desc')->find();
            if(empty($ods))
            {
                break;
            }
            else
            {
                if($type != 'lkl' && $type != 'lebrush' && $type != 'dougong'){
                    
                    //没设置浮动金额则默认调用官方提供
                    if(!empty($basic['floating_amount'])){
                        $arr = explode(",", $basic['floating_amount']);
                        $rand_keys=array_rand($arr,1);
                        $number=$arr[$rand_keys];
                        
                        //保留初始金额
                        if($i == 1){
                            $temp_money = $money;
                        }
                        $money = sprintf("%.2f",$money + floatval($number));
                        //适配递减规则 如果金额小于0 则进入官方定义付款组
                        if(0 > $money){
                            $arr=['0.01','0.02','0.03','0.04','0.05','0.06','0.07','0.08','0.09','0.1'];
                            $rand_keys=array_rand($arr,1);
                            $number=$arr[$rand_keys];
                            $money = $temp_money + floatval($number);
                            break;
                        }
                    }else{
                        $arr=['0.01','0.02','0.03','0.04','0.05','0.06','0.07','0.08','0.09','0.1'];
                        $rand_keys=array_rand($arr,1);
                        $number=$arr[$rand_keys];
                        $money = $money + floatval($number);
                        break;
                    }
                }else{
                    break;
                }
            }
            
            $i++;
        }
                $qrcode = $QR_row['wxname'];
                $h5url = $QR_row['wxname'];
                break;
            default:
                if($QR_row['code'] == 'lkl_wxpay' || $QR_row['code'] == 'lkl_alipay'){//判断是否是拉卡拉通道
                        if($QR_row['code'] == 'lkl_wxpay'){
                            $type = "wxpay";
                        }else{
                            $type = "alipay";
                        }
                         // 订单发起接口
                        $apiurl = 'https://wallet.lakala.com/m/a/code/generate';
                        // 当前时间
                        $atime = time();
                        // 订单过期时间
                        $btime = '180';
                        // 格式化当前时间
                        $dtime = date("YmdHis", $atime);

                        // 构造订单发起数据
                        $lklData = array(
                            "reqData" => array(
                                "shopNo" => $QR_row['wxname'],
                                "termNo" => $QR_row['zfb_pid'],
                                "shopName" => $QR_row['qr_url'],
                                "type" => "MICROCODE",
                                "expireTime" => $btime,
                                "orderField" => array(
                                    "amount" => $money * 100,
                                    "exterMerOrderNo" => "",
                                    "exterOrderSource" => "",
                                    "subject" => "",
                                    "description" => "",
                                    "orderRemark" => $data['out_trade_no']
                                ),
                                "txnField" => array(
                                    "outTradeNo" => $data['out_trade_no'],
                                    "operatorId" => "",
                                    "amount" => $money * 100,
                                    "remark" => $data['out_trade_no']
                                ),
                                "snAutoExpireFlag" => ""
                            ),
                        "ver" => "1.0.0",
                        "sign" => "",
                        "timestamp" => $dtime,
                        "reqId" => "",
                        "rnd" => ""
                    );

                        $body = json_encode($lklData);

                        // 设置请求头
                        $headers = array(
                            'Content-Type: application/json;charset=utf-8',
                            'Content-Length: ' . strlen($body),
                            'Authorization: ' . $QR_row['remark'],
                            "Host: wallet.lakala.com"
                        );

                        // 向上游发起订单创建请求
                        $ch = curl_init($apiurl);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_POST, true);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
                        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                        $response = curl_exec($ch);
                        curl_close($ch);
                
                        if (empty($response)) {
                            echo "上游接口无响应，请重试或联系管理员";
                            exit;
                        }
                        // 处理返回响应
                        $response_data = json_decode($response, true);
                        
                        if(empty($response_data)){
                            echo "很抱歉，由于您访问的URL有可能对拉卡拉网站造成安全威胁，您的访问被阻断";
                            exit;
                        }
                        
                        if(array_key_exists('retCode',$response_data)){
                                
                                if ($response_data['retCode'] === "000000") {
                                    $skmurl = $response_data['respData']['url'];
                                    $qrcode = $skmurl;
                                    $h5url = $skmurl;
                                } else {
                                    $notes = "你好！，通道ID为：".$QR_row['id']."的拉卡拉通道异常错误" . $response_data['retCode'] . "：" . $response_data['retMsg'];
                                    self::lose_expire($user,$basic,$QR_row['id'],'lkl',$notes);
                                    echo "异常错误" . $response_data['retCode'] . "：" . $response_data['retMsg'];
                                    exit;
                                }
                        }else{
                            $notes = "你好！，通道ID为：".$QR_row['id']."的拉卡拉通：登录信息错误,请重新抓取";
                            self::lose_expire($user,$basic,$QR_row['id'],'lkl',$notes);
                            echo "你好！，通道ID为：".$QR_row['id']."的拉卡拉通：登录信息错误,请重新抓取";
                            exit;
                        }
                        
                break;
        }
    
        }
        
        //如果超时时间为空,则默认为180秒
        if(empty($basic['timeout_time'])){
            $basic['timeout_time'] = 180;
        }
        
        //如果超时时间大于后台设置最大超时时间则调用后台设置最大超时时间
        if($basic['timeout_time'] > getConfig()['timeout']){
            $basic['timeout_time'] = getConfig()['timeout'];
        }
        
        $mbLen = mb_strlen($data['name']);
    
    $strArr = [];
    for ($i = 0; $i < $mbLen; $i++) {
        $mbSubstr = mb_substr($data['name'], $i, 1, 'utf-8');
        if (strlen($mbSubstr) >= 4) {
            continue;
        }
        $strArr[] = $mbSubstr;
    }
        $data['name'] = implode('', $strArr);
        $data['name'] = empty($basic['diy_name']) ? $data['name'] : $basic['diy_name'];
        //创建订单实例
        $odmodels = [
            'name' => $data['name'],
            'sitename' => $data['sitename'],
            'type' => $type,
            'account_id' => $QR_row['id'],
            'trade_no' => $trade_no,
            'out_trade_no' => $data['out_trade_no'],
            'notify_url' => $data['notify_url'],
            'return_url' => $data['return_url'],
            'user_id' => $user['id'],
            'money' => $data['money'],
            'truemoney' => $money,
            'feilvmoney' => $feilv_money,
            'status' => '0',
            'create_time' => date('Y-m-d H:i:s', time()),
            'qrcode' => $qrcode,
            'h5_qrurl' => $h5url,
            'ip' => get_client_ip(),
            'out_time' => time() + $basic['timeout_time'],
        ];
        try {
            M::create($odmodels);
            return true;
        }catch (\Exception $e){
            return false;
        }
    }
    
    //支付宝个人免挂
    public static function alipay_grmg($trade_no,$QR_row,$data,$user)
    {
        $result = self::create_order($trade_no,$QR_row,$data,$user,'alipay');
        return $result;
    }
     //支付宝商家账单
    public static function alipay_mck($trade_no,$QR_row,$data,$user)
    {
        $result = self::create_order($trade_no,$QR_row,$data,$user,'alipay');
        return $result;
    }
    
    //支付宝软件版
    public static function alipay_software($trade_no,$QR_row,$data,$user)
    {
        $result = self::create_order($trade_no,$QR_row,$data,$user,'alipay');
        return $result;
    }
    
    //汇付斗拱 - 支付宝
    public static function dougong_alipay($trade_no,$QR_row,$data,$user)
    {
        $result = self::create_order($trade_no,$QR_row,$data,$user,'dougong');
        return $result;
    }
    
    //拉卡拉-支付宝
    public static function lkl_alipay($trade_no,$QR_row,$data,$user)
    {
        $result = self::create_order($trade_no,$QR_row,$data,$user,'lkl');
        return $result;
    }
    
    //乐刷支付宝
    public static function lebrush_alipay($trade_no,$QR_row,$data,$user){
        $result = self::create_order($trade_no,$QR_row,$data,$user,'lebrush');
        return $result;
    }
    
    //微信店员
    public static function wxpay_dy($trade_no,$QR_row,$data,$user)
    {
        $result = self::create_order($trade_no,$QR_row,$data,$user,'wxpay');
        return $result;
    }
    
    //微信软软件版
    public static function wxpay_software($trade_no,$QR_row,$data,$user)
    {
        $result = self::create_order($trade_no,$QR_row,$data,$user,'wxpay');
        return $result;
        
    }
    
    //微信云端
    public static function wxpay_cloud($trade_no,$QR_row,$data,$user)
    {
        $result = self::create_order($trade_no,$QR_row,$data,$user,'wxpay');
        return $result;
    }
    
    //微信经营码
    public static function wxpay_jym_cloud($trade_no,$QR_row,$data,$user)
    {
        $result = self::create_order($trade_no,$QR_row,$data,$user,'wxpay');
        return $result;
    }
    
    //微信收款单
    public static function wxpay_skd($trade_no,$QR_row,$data,$user)
    {
        $result = self::create_order($trade_no,$QR_row,$data,$user,'wxpay');
        return $result;
    }
    
        //微信赞赏码
    public static function wxpay_cloudzs($trade_no,$QR_row,$data,$user)
    {
        $result = self::create_order($trade_no,$QR_row,$data,$user,'wxpay');
        return $result;
    }
    
    //汇付斗拱 - 支付宝
    public static function dougong_wxpay($trade_no,$QR_row,$data,$user)
    {
        $result = self::create_order($trade_no,$QR_row,$data,$user,'dougong');
        return $result;
    }
    
    //拉卡拉-微信
    public static function lkl_wxpay($trade_no,$QR_row,$data,$user)
    {
        $result = self::create_order($trade_no,$QR_row,$data,$user,'lkl');
        return $result;
    }
    
    //乐刷微信
    public static function lebrush_wxpay($trade_no,$QR_row,$data,$user){
        $result = self::create_order($trade_no,$QR_row,$data,$user,'lebrush');
        return $result;
    }
    
        //QQ免挂版-软件
    public static function qqpay_cloud($trade_no,$QR_row,$data,$user)
    {
        $result = self::create_order($trade_no,$QR_row,$data,$user,'qqpay');
        return $result;
    }
    
        //QQ免挂版-本地
    public static function qqpay_mg($trade_no,$QR_row,$data,$user)
    {
        $result = self::create_order($trade_no,$QR_row,$data,$user,'qqpay');
        return $result;
    }
    
    //QQ软件版
    public static function qqpay_software($trade_no,$QR_row,$data,$user)
    {
        $result = self::create_order($trade_no,$QR_row,$data,$user,'qqpay');
        return $result;
    }
    
    //微转Q
    public static function qqpay_wzq($trade_no,$QR_row,$data,$user){
        $result = self::create_order($trade_no,$QR_row,$data,$user,'qqpay');
        return $result;
    }

    //Usdt通道
    public static function usdt($trade_no,$QR_row,$data,$user){
        $result = self::create_order($trade_no,$QR_row,$data,$user,'usdt');
        return $result;
    }
    
    /**
     * @掉线通知
     * @param [type] $id $type $channelID
     *
     * @return void
     */
     public  static function lose_expire($user,$basic,$channelID,$type,$notes = ''){
        Db::name('ypay_account')->where('id',$channelID)->update(['status' => 0 , 'create_time' => date('Y-m-d H:i:s', time())]);//掉线
        //调用通知方法 1.用户信息 2.用户配置参数 3.通道ID 4.通道类型 5.备注
        notice::lose_tips($user,$basic,$channelID,$type,$notes);
     }
    
    //支付宝当面付
    public static function alipay_dmf($trade_no,$QR_row,$data,$user)
    {
        $basic = basic::where('user_id',$user['id'])->find();
        $request = \think\facade\Request::instance();
        $notifyUrl = str_replace('/submit.php','',$request->root(true)).'/Notify/alipay_dmf';
        $appid = $QR_row['wxname'];//https://open.alipay.com 账户中心->密钥管理->开放平台密钥，填写添加了电脑网站支付的应用的APPID
        $signType = 'RSA2';//签名算法类型，支持RSA2和RSA，推荐使用RSA2
        $rsaPrivateKey=$QR_row['qr_url'];//商户私钥，填写对应签名算法类型的私钥，如何生成密钥参考：https://docs.open.alipay.com/291/105971和https://docs.open.alipay.com/200/105310
        $requestConfigs = array(
            'out_trade_no'=>$data['out_trade_no'],
            'total_amount'=>$data['money'], //单位 元
            'subject'=>$data['name'],  //订单标题
            'timeout_express'=>'3m'       //该笔订单允许的最晚付款时间，逾期将关闭交易。取值范围：1m～15d。m-分钟，h-小时，d-天，1c-当天（1c-当天的情况下，无论交易何时创建，都在0点关闭）。 该参数数值不接受小数点， 如 1.5h，可转换为 90m。
        );
        $commonConfigs = array(
            //公共参数
            'app_id' => $appid,
            'method' => 'alipay.trade.precreate',//接口名称
            'format' => 'JSON',
            'charset'=> 'utf-8',
            'sign_type'=>'RSA2',
            'timestamp'=>date('Y-m-d H:i:s'),
            'version'=>'1.0',
            'notify_url' => $notifyUrl,
            'biz_content'=>json_encode($requestConfigs),
        );
        $sign = Jialanshen::sign($rsaPrivateKey,Jialanshen::getSignContent($commonConfigs), $commonConfigs['sign_type']);
        if(!$sign)
        {
            return '密钥错误';
        }
        $commonConfigs["sign"] = $sign;
        $result = Jialanshen::curlPost('https://openapi.alipay.com/gateway.do?charset=utf-8',$commonConfigs);
        $json = json_decode($result,TRUE);
        $json = $json['alipay_trade_precreate_response'];
        
        if($json['code'] && $json['code']=='10000')
        {
            //生成成功，将订单数据添加到数据库并返回
            if(empty($data['sitename']))
            {
               $data['sitename'] = "";
            }
            $money = $data['money'];
            $qrcode = $json['qr_code'];
            $h5url = "alipayqr://platformapi/startapp?saId=10000007&qrcode=" .$json['qr_code'];
            $feilv_money = $data['money'] * $user['feilv'] / 100;
                    
            //如果超时时间为空,则默认为180秒
            if(empty($basic['timeout_time'])){
                $basic['timeout_time'] = 180;
            }
            
            //如果超时时间大于后台设置最大超时时间则调用后台设置最大超时时间
            if($basic['timeout_time'] > getConfig()['timeout']){
                $basic['timeout_time'] = getConfig()['timeout'];
            }
            $odmodels = [
                'name' => $data['name'],
                'sitename' => $data['sitename'],
                'type' => 'alipay',
                'account_id' => $QR_row['id'],
                'trade_no' => $trade_no,
                'out_trade_no' => $data['out_trade_no'],
                'notify_url' => $data['notify_url'],
                'return_url' => $data['return_url'],
                'user_id' => $user['id'],
                'money' => $data['money'],
                'truemoney' => $money,
                'feilvmoney' => $feilv_money,
                'status' => '0',
                'create_time' => date('Y-m-d H:i:s', time()),
                'qrcode' => $qrcode,
                'h5_qrurl' => $h5url,
                'ip' => get_client_ip(),
                'out_time' => time() + $basic['timeout_time'],
            ];
            try {
                M::create($odmodels);
                return true;
            }catch (\Exception $e){
                return false;
            }
        }
        else
        {
            return false;//返回失败信息
        }
    }

    
    public static function epay_zj($trade_no,$data,$user,$paylist)
    {
         $basic = basic::where('user_id',$user['id'])->find();
        self::normalizeChannelMode($basic);
        if(empty($data['sitename'])){
            $data['sitename'] = "";
        }
        $money = $data['money'];
        $feilv_money = $data['money'] * $user['feilv'] / 100;
        $odmodels = [
            'name' => $data['name'],
            'sitename' => $data['sitename'],
            'type' => $data['type'],
            'account_id' => $paylist['id'],
            'trade_no' => $trade_no,
            'out_trade_no' => $data['out_trade_no'],
            'notify_url' => $data['notify_url'],
            'return_url' => $data['return_url'],
            'user_id' => $user['id'],
            'money' => $data['money'],
            'truemoney' => $money,
            'feilvmoney' => $feilv_money,
            'status' => '0',
            'create_time' => date('Y-m-d H:i:s', time()),
            'qrcode' => '',
            'h5_qrurl' => '',
            'ip' => get_client_ip(),
            'out_time' => time() + $basic['timeout_time'],
            'pay_type'=>2
        ];
        try {
            M::create($odmodels);
            return true;
        }catch (\Exception $e){
            return false;
        }
    }
    
    
    
    public static function sign($priKey,$data, $signType = "RSA") {
        error_reporting(0);
        $res = "-----BEGIN RSA PRIVATE KEY-----\n" .
            wordwrap($priKey, 64, "\n", true) .
            "\n-----END RSA PRIVATE KEY-----";
        ($res) or die('');
        if ("RSA2" == $signType) {
            openssl_sign($data, $sign, $res, version_compare(PHP_VERSION,'5.4.0', '<') ? SHA256 : OPENSSL_ALGO_SHA256); //OPENSSL_ALGO_SHA256是php5.4.8以上版本才支持
        } else {
            openssl_sign($data, $sign, $res);
        }
        $sign = base64_encode($sign);
        return $sign;
    }
    public static function checkEmpty($value) {
        if (!isset($value))
            return true;
        if ($value === null)
            return true;
        if (trim($value) === "")
            return true;
        return false;
    }
    public static function getSignContent($params) {
        ksort($params);
        $stringToBeSigned = "";
        $i = 0;
        foreach ($params as $k => $v) {
            if (false === Jialanshen::checkEmpty($v) && "@" != substr($v, 0, 1)) {
                // 转换成目标字符集
                $v = Jialanshen::characet($v, 'utf-8');
                if ($i == 0) {
                    $stringToBeSigned .= "$k" . "=" . "$v";
                } else {
                    $stringToBeSigned .= "&" . "$k" . "=" . "$v";
                }
                $i++;
            }
        }
        unset ($k, $v);
        return $stringToBeSigned;
    }
    static function characet($data, $targetCharset) {
        if (!empty($data)) {
            $fileType = 'utf-8';
            if (strcasecmp($fileType, $targetCharset) != 0) {
                $data = mb_convert_encoding($data, $targetCharset, $fileType);
                //$data = iconv($fileType, $targetCharset.'//IGNORE', $data);
            }
        }
        return $data;
    }
    public static function curlPost($url = '', $postData = '', $options = array())
    {
        if (is_array($postData)) {
            $postData = http_build_query($postData);
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30); //设置cURL允许执行的最长秒数
        if (!empty($options)) {
            curl_setopt_array($ch, $options);
        }
        //https请求 不验证证书和host
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
    }
    public static function rsaCheck($params,$priKey) {
        $sign = $params['sign'];
        $signType = $params['sign_type'];
        unset($params['sign_type']);
        unset($params['sign']);
        return Jialanshen::verify($priKey,Jialanshen::getSignContent($params),$sign,$signType);
    }
    public static function verify($priKey,$data,$sign,$signType = 'RSA') {
        
        $res = "-----BEGIN PUBLIC KEY-----\n" .
            wordwrap($priKey, 64, "\n", true) .
            "\n-----END PUBLIC KEY-----";
        ($res) or die('');
        //调用openssl内置方法验签，返回bool值
        if ("RSA2" == $signType) {
            $result = (bool)openssl_verify($data, base64_decode($sign), $res, version_compare(PHP_VERSION,'5.4.0', '<') ? SHA256 : OPENSSL_ALGO_SHA256);
        } else {
            $result = (bool)openssl_verify($data, base64_decode($sign), $res);
        }
        return $result;
    }
    
    
    
    
    
}
