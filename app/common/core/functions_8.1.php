<?php
declare (strict_types=1);

namespace app\common\core;

use think\facade\Cache;
use think\facade\Db;
use think\facade\Request;
use think\facade\Config;
use think\facade\Session;

class Core
{
     //检测更新
    public static function UpCheck($ver)
    {
        return json_encode([
            'code' => 403,
            'vername' => $ver,
            'version' => $ver,
            'vertime' => time(),
            'msg' => '在线更新已关闭，禁止从外部下载未知升级包'
        ]);
    }
    
        //检测更新
    public static function updateInfo()
    {
        return json_encode([
            'code' => 403,
            'updateMsg' => '在线更新已关闭'
        ]);
    }
    
    
    //获取商城总览广告位
    // public static function getShopAd(){
        
    //     //获取对应数据
    //     $result = self::getAdCron('1752944621708677121','1752944525415845889');
        
    //     $data = [];
        
    //     //判断并且返回对应参数
    //     if($result['success'] && $result['code'] == 200){
    //         foreach ($result['result'] as $key => $value) {
    //             $data[] = ['title' => $value['title'],'url' => $value['url'],'images'=>'https://api.yfx.top/sys/common/static/'.$value['images'],'expireDate'=>$value['expireDate']];
    //         }
    //         return $data;
    //     }
    // }
    
    //获取支付推荐
    public static function getEPayAd(){
        return [];
    }
    
    //获取聚合登录推荐
    public static function getLoginAd(){
        return [];
    }
    
    //获取控制台左边推荐
    public static function getHomeLeftAd(){
        return [];
    }
    
        //获取控制台右边推荐
    public static function getHomeRightAd(){
        return [];
    }
    
    //获取广告核心
    public static function getAdCron($positionId,$typeId){
        return [
            'success' => false,
            'code' => 403,
            'result' => []
        ];
    }
 
    /**
    ** 微信通道类
    **/
    
    //创建微信云端实例 参数:云端类型 云端地址
    public static function getWechatCreate($type,$address){
        //申明返回参数
        $array = array();
       
        //根据云端类型筛选 1:Mac - V3 2:Mac - V2 3:五合一 4:新版Mac
        switch ($type) {
            case 1:
                //请求资源
                $res = cloud_get_curl($address . '/api/Client/WXCreate', [ "Terminal" => 3,"WxData" => "data","Brand"=> "mac","Name"=> "mac-tegic","Imei"=> "string","Mac"=> "mac"]);
                
                //构建返回参数
                $array = 
                [
                    'message' => $res->message,
                    'guid' => $res->data->Guid
                ];
                break;
            case 2:
                $res = cloud_get_curl($address . '/api/Guid', ['ip' => get_client_ip()]);
                if($res->code == 200){
                    $message = 'success';
                }
                //构建返回参数
                $array = 
                [
                    'message' => $message,
                    'guid' => $res->Guid
                ];
                break;
            case 3: // 新版五合一云端
            case 4 : // 微信云端—mac新版
                return [
                    'message' => 'success'
                ];
            break;
        }
        
        return $array;
    }
    
    //获取微信云端登录二维码 参数:云端类型 云端地址
    public static function getWechatQrCode($guid,$cloud_id,$channel_info){
         //查询是否存在该云端地域
        $cloudArr = Db::name('ypay_cloud')->where('id',$cloud_id)->find();
        if(!empty($cloudArr)){
            //如果存在,则筛选云端类型 1:Mac - V3 2:Uos
            switch ($cloudArr['cloud_type']) {
                case 2:
                    $res = cloud_get_curl($cloudArr['address'] . '/api/Loginqrcode', [ 'Guid' => $guid,'ip' => get_client_ip()]);
                    if ($res->code != 200) {
                        return [];
                    } else {
                        $res = [
                            'data' => [
                                'qrcode' => $res->qrcode,
                                'uuid' => '',
                                'guid' => $guid
                            ],
                            'is_base64' => 0
                        ];
                    }
                    break;
                case 3: // 新版五合一云端
                case 4 : // 微信云端—mac新版
                    // OSModel映射数组
                    $OSModelList = [1 => 'car', 2 => 'windows', 3 => 'pad', 4 => 'mac', 5 => 'ipad'];
                    $post_data   = ['DeviceID' => $guid ?? '', "DeviceName" => '', 'OSModel' => $OSModelList[$channel_info['wxname']]];
                    if ($cloudArr['cloud_type'] == 4) {
                        $post_data = ['DeviceID' => $guid ?? '', "DeviceName" => 'MacBook Pro', 'OSModel' => 'mac'];
                    }
                    if ($cloudArr['cloud_proxy'] == "open") {
                        $post_data['Proxy'] = [
                            'ProxyIp'       => $cloudArr['proxy_address'],
                            'ProxyPassword' => $cloudArr['proxy_password'],
                            'ProxyUser'     => $cloudArr['proxy_account'],
                        ];
                    }
                    
                    $res = cloud_get_curl($cloudArr['address'] . '/VXAPI/Login/YPayGetQR', $post_data);
                    if (empty($res) || $res->Code != 1) {
                        $res = [];
                        break;
                    }
                    // 解析一下URL
                    preg_match('/data=([^&]*)/', $res->Data->QrUrl, $matches);
                    $res = [
                        'data'      => [
                            'qrcode' => $matches[1] ?? '',
                            'uuid'   => $res->Data->Uuid,
                            'guid'   => $res->DeviceId
                        ],
                        // 'is_url' => 1,
                        'is_base64' => 0
                    ];
                    break;
                default:
                    $res = cloud_get_curl($cloudArr['address'] . '/api/Login/WXGetLoginQrcode', [ 'Guid' => $guid]);
                    // 如果参数为空则进行二次获取二维码信息
                    if(empty($res)){
                      break;  
                    }
                    $res = [
                            'data' => [
                                'qrcode' => $res->data->qrcode,
                                'uuid' => $res->data->uuid,
                                'guid' => $guid
                            ],
                            'is_base64' => 1
                        ];
                    break;
            }
            return $res;
        }
    }
    

    // 二次登录
    public static function weChatSecondLogin($wxid, $cloudInfo, $channel)
    {
        $OSModelList = [1 => 'car', 2 => 'windows', 3 => 'pad', 4 => 'mac', 5 => 'ipad'];
        $post_data = [
            'Wxid'=>$wxid,
            'OSModel'=>$OSModelList[$channel['wxname']],
        ];
        $res = cloud_get_curl($cloudInfo['address'] . '/VXAPI/Login/YPayTwiceAutoAuth',$post_data);
        if (empty($res)) {
            $res = [];
            return false;
        }

        if ($res->Message == '登陆成功' && $res->Code == 0) {
            return true;
        }
        return false;
    }

    //检查微信登录状态 参数:Guid Uuid 云端地址
    public static function getWechatLoginStatus($guid, $uuid,$cloud_id)
    {
          //查询是否存在该云端地域
        $cloudArr = Db::name('ypay_cloud')->where('id',$cloud_id)->find();
        if(!empty($cloudArr)){
            
            
            //如果存在,则筛选云端类型 1:Mac - V3 2:Uos
            switch ($cloudArr['cloud_type']) {
                case 2:
                            
        $res = cloud_get_curl($cloudArr['address'] . '/api/Logincodeqrcode', ['Guid' => $guid, 'ip' => get_client_ip()]);

                $res = (array)$res;
                    if (empty($res)) {
                        $tmp_res = [
                            'data' => [
                                'state' => 0
                            ],
                            'type' => 'default'
                        ];
                    } else {
                        if (empty($res['code'])) {
                            $tmp_res = [
                                'data' => [
                                    'state' => 0
                                ],
                                'type' => 'default'
                            ];
                            return $tmp_res;
                        }
                        $tmp_res = match ($res['code']) {
                            -1, 0 => [
                                'data' => [
                                    'state' => 0
                                ],
                                'type' => 'default'
                            ],
                            201 => [
                                'data' => [
                                    'state' => 1
                                ],
                                'type' => 'default'
                            ],
                            200 => [
                                'data' => [
                                    'state' => 2,
                                    'wxid' => '',
                                    'wxnewpass' => '',
                                ],
                                'type' => 'default'
                            ],
                            default => [
                                'data' => [
                                    'state' => 400
                                ],
                                'type' => 'default'
                            ],
                        };
                    }

                    $res = $tmp_res;
                    break;
                case 3: // 新版五合一云端
                case 4 : // 微信云端—mac新版
                	$post =1;
	                $Header =  array('Content-Type: application/json');
                    $response = http_post_yun($cloudArr['address'] . '/VXAPI/Login/YPayCheckQR?uuid='.$uuid,$Header, $post);
                    
                    $res = json_decode($response, true);
                    if ($res['Code'] == 0) {
                        if (isset($res['Data']['status'])) {
                            return [
                                'data' => [
                                    'state' => $res['Data']['status']
                                ],
                                'type' => 'default'
                            ];
                        } elseif ($res['Message'] == '登陆成功' || $res['Message'] == '\u767b\u9646\u6210\u529f') {
                            return [
                                'data' => [
                                    'state'     => 2,
                                    'wxid'      => $res['Data']['acctSectResp']['userName'],
                                    'wxnewpass' => '',
                                ],
                                'type' => 'default'
                            ];
                        }
                    } elseif ($res['Code'] == -3) {
                        return [
                            'data' => [
                                'state'  => -4,
                                'data'   => $res['Data'],
                                'uuid'   => $uuid,
                                'Data62' => $res['Data62']
                            ],
                            'type' => 'default'
                        ];
                    } else {
                        return [
                            'data' => [
                                'state' => 402,
                            ],
                            'type' => 'default'
                        ];
                    }
                    break;
                default:
                    $res = cloud_get_curl($cloudArr['address'] . '/api/Login/WXCheckLoginQrcode', [ 'Guid' => $guid, "Uuid" => $uuid]);
                    $res = (array)$res;
                    $res['data'] = (array)$res['data'];
                    break;
            }
            return $res;
        }
    }
    
        // 提交验证码
    public static function weChatLoginVerify($data, $cloud_id)
    {
        //查询是否存在该云端地域
        $cloudArr = Db::name('ypay_cloud')->where('id',$cloud_id)->find();
        $res = cloud_get_curl($cloudArr['address'] . '/VXAPI/Login/YPayVerifyCode', $data);
        if ($res->Code == 0 && $res->Success == 'true') return true;
        return false;
    }

    
        // 初始化
    public  static function wechatInit($address, $user, $channel)
    {
        
        $key =  empty($channel['qr_url'])? '':json_decode($channel['qr_url'], true);

        if (!empty($key)) {
            $url = "/VXAPI/Login/YPayNewinit?wxid={$user}&MaxSynckey={$key['MaxSynckey']}&CurrentSynckey={$key['CurrentSynckey']}";
        } else {
            $url = "/VXAPI/Login/YPayNewinit?wxid={$user}";
        }
        $post =1;
	   $Header =  array('Content-Type: application/json');
        $res = http_post_yun($address . $url, $Header, $post);
        $res = json_decode($res, true);
        if (isset($res['Code']) && $res['Code'] == 0) {
            $data = '';
            if (!empty($res['Data']['CurrentSynckey']['buffer']) && !empty($res['Data']['MaxSynckey']['buffer'])) {
                if($channel['code'] == 'wxpay_cloudzs'){
                    if(isset($key['qr_url'])){
                        $data = [
                            'qr_url' => $key['qr_url'],
                            'MaxSynckey'     => $res['Data']['MaxSynckey']['buffer'],
                            'CurrentSynckey' => $res['Data']['CurrentSynckey']['buffer']
                        ];
                    }else{
                        $data = [
                            'qr_url' => $channel['qr_url'],
                            'MaxSynckey'     => $res['Data']['MaxSynckey']['buffer'],
                            'CurrentSynckey' => $res['Data']['CurrentSynckey']['buffer']
                        ];
                    }

                }else{
                    $data = [
                        'qr_url' => $channel['qr_url'],
                    ];
                }

            }
            if (!empty($data)) {
                 Db::name('ypay_account')->where('id', $channel['id'])->update(['qr_url' =>json_encode($data),'cookie'=>$user]);
            }
            return true;
        } else {
            return false;
        }
    }
    
    //登录微信 参数:Guid 账户  密码 云端地址
    public static function getWechatLoginManual($guid, $user, $pass,$cloud_id,$channel = null)
    {
        //查询是否存在该云端地域
        $cloudArr = Db::name('ypay_cloud')->where('id',$cloud_id)->find();
        if(!empty($cloudArr)){
            
            //如果存在,则筛选云端类型 1:Mac - V3 2:Uos
            switch ($cloudArr['cloud_type']) {
                case 1:
                    $res = cloud_get_curl($cloudArr['address'] . '/api/Login/WXSecLoginManual', [ 'Channel' => 1, "Guid" => $guid, "UserName" => $user, "Password" => $pass]);
                    $array = 
                    [
                        'code' => $res->data->baseResponse->ret,
                    ];
                    break;
                case 2:
                    $res = cloud_get_curl($cloudArr['address'] . '/api/Logintoken', [ 'ip' => get_client_ip(), 'guid' => $guid]);
                    $res = (array)$res;
                       if (!empty($res) && $res['code'] == 200) {
                        $res = cloud_get_curl($cloudArr['address'] . '/api/Newinit', ['ip' => get_client_ip(), 'guid' => $guid]);
                        $res = (array)$res;
                        if (empty($res['code']) || $res['code'] !== 200) {
                            return [
                                'code' => -2,
                                'data' => []
                            ];
                        }
                        return [
                            'code' => 1,
                            'account' => $guid,
                            'data' => [],
                        ];
                    } else {
                        return [
                            'code' => -1,
                            'data' => []
                        ];
                    }
                    break;
                case 3: // 五合一云端
                case 4: //新版mac
                    $post =1;
	                $Header =  array('Content-Type: application/json');
                    $res = http_post_yun($cloudArr['address'] . '/VXAPI/Login/YPayHeartBeat?wxid=' . $user,  $Header, $post);
                    $res = json_decode($res, true);
                    if ($res['Message'] == '成功' || $res['Message'] == '\u6210\u529f') {
                            $res = self::wechatInit($cloudArr['address'], $user, $channel);
                            if($res){
                                return [
                                    'code'    => 1,
                                    'account' => $guid,
                                    'wxid'    => $user,
                                ];
                            }else{
                                return [
                                    'code'    => 201 ,
                                    'msg' => '云端初始化失败',
                                ];
                            }
                    } else {
                        return [
                            'code' => $res['Code']
                        ];
                    }
                    break;
            }
            
            return $array;
        }
    }
    
    //监听微信是否还在线
    public static function getWechatStatus($guid,$cloud_id,$account)
    {
        //查询是否存在该云端地域
        $cloudArr = Db::name('ypay_cloud')->where('id',$cloud_id)->find();
        if(!empty($cloudArr)){
            
            //如果存在,则筛选云端类型 1:Mac - V3 2:Uos - V2 3/4:新版Mac
            switch ($cloudArr['cloud_type']) {
                case 1:
                    $res = cloud_get_curl($cloudArr['address'] . '/api/User/WXGetProfile', [ 'Guid' => $guid]);
                    
                    //防止第一次获取不到
                    
                    $count = 0;
                    while ($count < 3) {
                        // 执行循环的代码块
                        if(!empty($res->data)){
                            break;
                        }
                        
                        $count++; // 每次循环后增加计数器变量的值
                    }
                    
                    //根据信息返回不同状态
                    if(!empty($res->data)){
                        $array = 
                        [
                            'type' =>'Mac-V3',
                            'code' => 200,
                            'status' => 1,
                            'desc' => '在线'
                        ];
                    }else{
                        $array = 
                        [
                            'type' =>'Mac-V3',
                            'code' => 201,
                            'status' => 0,
                            'desc' =>'离线'
                        ];
                    }

                    break;
                case 2:
                  
                      $array = 
                        [
                            'type' =>'Uos',
                            'code' => 200,
                            'status' => 1,
                            'desc' => '在线'
                        ];
              
                    break;
                case 3:
                case 4:
                    $post =1;
	                $Header =  array('Content-Type: application/json');
                    $res = http_post_yun($cloudArr['address'] . '/VXAPI/Login/YPayHeartBeat?wxid=' . $account['cookie'],  $Header, $post);
                    $res = json_decode($res, true);
                    // 定义返回结果模板
                    $result = [
                        'type'   => 'clouds',
                        'code'   => 201,
                        'status' => 0,
                    ];
                    // 判断条件并设置返回值
                    if ($res['Code'] == 0) {
                        if (in_array($res['Message'], ['成功', '\u6210\u529f'])) {
                            $result['code'] = 200;
                            $result['status'] = 1;
                            $result['desc'] = '在线';
                        } else {
                            $result['desc'] = '异常';
                        }
                    } else {
                        $result['desc'] = '离线';
                    }
                    // 返回结果
                    return $result;
                    break;
            }
            
            return $array;
        }
    }
    
    //删除微信通道
    public static function getDelWechatAccount($guid,$cloud_id)
    {
        //查询是否存在该云端地域
        $cloudArr = Db::name('ypay_cloud')->where('id',$cloud_id)->find();
        
        if(!empty($cloudArr)){
            
            //如果存在,则筛选云端类型 1:Mac - V3 2:Mac - V2 3:IPad
            switch ($cloudArr['cloud_type']) {
                case 1:
                    cloud_get_curl($cloudArr['address']. '/api/Login/WXLogout', [ 'Guid' => $guid]);
                    $res = cloud_get_curl($cloudArr['address']. '/api/Client/WXRelease', [ 'Guid' => $guid]);
                    break;
                case 2:
                    $res = cloud_get_curl($cloudArr['address']. '/api/Client/WXRelease', [ 'Guid' => $guid]);
                    break;
                case 3:

                    break;
            }
        }
    }
    
    //生成微信支付二维码 参数:Guid 支付金额  订单号 云端地域
    public static function getWechatTransferSet($guid, $wx_fen, $trade_no,$cloud_id,$account)
    {
        //查询是否存在该云端地域
        $cloudArr = Db::name('ypay_cloud')->where('id',$cloud_id)->find();
        
        if(!empty($cloudArr)){
            
            //如果存在,则筛选云端类型 1:Mac - V3 2:Uos
            switch ($cloudArr['cloud_type']) {
                case 1:
                     $res = cloud_get_curl($cloudArr['address'] . '/api/Payment/WXTransferSetF2FFee', [ 'Guid' => $guid, 'Fee' => $wx_fen, 'Description' => $trade_no]);
                    break;
                case 2:
                    $res = cloud_get_curl($cloudArr['address']  . '/api/Cloud/WXTransferSet', [ 'Guid' => $guid, 'Fee' => $wx_fen, 'Description' => $trade_no]);
                    break;
                case 3:
                case 4 :
                    $url       = '/VXAPI/TenPay/YPayGMPayQCode';
                    $post_data = [
                        'Wxid'   => $account['cookie'],
                        'Money'  => (string)$wx_fen,
                        'Name'   => $trade_no,
                    ];
                    // 经营码通道
                    if ($account['code'] == 'wxpay_jym_cloud') {
                        $url                 = '/VXAPI/TenPay/YPayGMJyPayQCodeUrl';
                        $post_data['Money']  = bcdiv((string)$wx_fen, (string)100, 2);
                        // name和remark限制长度，所以将订单号分两端发送
                        // 分割订单号
                        $trade_no_part1 = substr($trade_no, 0, 15); // 取前15字符（可以根据接口要求调整）
                        $trade_no_part2 = substr($trade_no, 15);   // 剩余字符
                        // 赋值到Name和Remark
                        $post_data['Name']   = $trade_no_part1;
                        $post_data['Remark'] = $trade_no_part2 ?: '无附加信息';
                    }
                    // 收款单通道
                    if ($account['code'] == 'wxpay_skd') {
                        $url="/VXAPI/TenPay/YPayGMSkdPayQCode";
                        if ($channel_info['h5_url']=='merchant'){
                            $url="/VXAPI/TenPay/YPaySjSkdPayQCode";
                        }
                        $post_data['Money']  = bcdiv($wx_fen, 100, 2);
                        $post_data['Name']=$channel_info['merchant_name'];
                        $post_data['Remark']=$trade_no;
                    }
                    $res = cloud_get_curl($cloudArr['address']  . $url, $post_data);
                    break;
            }
        }
        
        return $res;
    }
    
    //获取微信订单信息
    public static function getWechatOrder($guid,$cloud_id,$account_code = '',$account = null){
        //查询是否存在该云端地域
        $cloudArr = Db::name('ypay_cloud')->where('id',$cloud_id)->find();
        
        if(!empty($cloudArr)){
            
            //如果存在,则筛选云端类型 1:Mac - V3 2:Mac - V2 3:IPad
            switch ($cloudArr['cloud_type']) {
                case 1:
                        
                        if($account_code == 'wxpay_cloudzs'){
                            $res = cloud_get_curl($cloudArr['address'] . '/api/Message/WXSyncMsg', [ 'Guid' => $guid]);
                            
                            $array = 
                                [
                                    'code' => 200,
                                    'type' => 1,
                                    'getType' => 'message',
                                    'data' => $res,
                                ]; 
                            return $array;
                        }
                        // switch (rand(1, 2)) {
                        //     case 1:
                                $res = cloud_get_curl($cloudArr['address']  . '/api/Message/WXSyncMsg', [ 'Guid' => $guid]);
                                $array = 
                                [
                                    'code' => 200,
                                    'type' => 1,
                                    'getType' => 'message',
                                    'data' => $res,
                                ]; 
                                return $array;
                                // break;
                        //     case 2:
                        //         $appid = 'wx28be8489b7a36aaa';
                        //         $WXJSlogin = $address . "/api/Common/WXJSLogin";
    	                   //     $Header =  array('Content-Type: application/json-patch+json');
    	                   //     $post = "{\"AppId\":\"{$appid}\",\"Guid\":\"{$guid}\"}";
    	                   //     $res = json_decode(self::http_post_yun($WXJSlogin, $Header ,$post),true);
    	                   //     $code=$res['data']['code'];
    	                   //     $url ="https://payapp.weixin.qq.com/qrapp/user/login?v=5.132.4&js_code=".$code;
                        //         $res=json_decode(get_curl($url),true);
                        //         $sid=$res['data']['sid'];
                        //         $curl = curl_init();
                        //         curl_setopt_array($curl, [
                        //             CURLOPT_URL => "https://payapp.weixin.qq.com/qrappzd/user/incomelist2?sid=".$sid."&v=5.132.4",
                        //             CURLOPT_RETURNTRANSFER => true,
                        //             CURLOPT_ENCODING => "",
                        //             CURLOPT_MAXREDIRS => 10,
                        //             CURLOPT_TIMEOUT => 30,
                        //             CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                        //             CURLOPT_CUSTOMREQUEST => "POST",
                        //             CURLOPT_POSTFIELDS => "{\r\n\"end_time\": ".time().",\r\n\"last_bill_id\": null,\r\n\"sort\": \"desc\",\r\n\"start_time\": 0,\r\n\"last_create_time\": 0,\r\n\"v\": \"5.132.4\",\r\n\"sid\": \"".$sid."\",\r\n\"is_first\": true,\r\n\"last_roll_id\": null,\r\n\"last_id\":\"\",\r\n\"page_size\": 10\r\n}",
                        //             CURLOPT_HTTPHEADER => [
                        //                 "content-type: application/json"
                        //             ],
                        //         ]);
            
                        //         $response = curl_exec($curl);
                        //         $err = curl_error($curl);
                        //         curl_close($curl);
                        //         $array = 
                        //         [
                        //             'code' => 200,
                        //             'type' => 1,
                        //             'getType' => 'bill',
                        //             'data' => $response,
                        //         ]; 
                        //         break;
                        // }

                    break;
                case 2:
                    $res = cloud_get_curl($cloudArr['address']  . '/api/SyncMsg', [
                        'Guid' => $guid,
                        'ip' => get_client_ip(),
                    ]);

                    $res = (array)$res;
                    if (empty($res)) return [
                        'code' => 200,
                        'status' => 0,
                        'type' => 'Uos'

                    ];
                    if ($res['code'] == 200 || $res['code'] == 300) {
                        //构建返回参数
                        $array =
                            [
                                'code' => 200,
                                'status' => 1,
                                'data' => $res['code'] == 300 ? [] : $res,
                                'type' => 'Uos'

                            ];
                    } else if ($res['code'] == -2) {
                        $array =
                            [
                                'code' => 200,
                                'status' => 0,
                                'type' => 'Uos'
                            ];
                    } else {
                        $array =
                            [
                                'code' => 200,
                                'status' => 0,
                                'type' => 'Uos'

                            ];
                    }

                    return $array;
                    break;
                case 3:
                case 4:
                        $res = cloud_get_curl($cloudArr['address']  . '/VXAPI/Msg/YPaySync', ['Wxid' => $account['cookie'], 'Synckey' => "", 'Scene' => 0]);
                    if ($res->Code == 0) {
                        return [
                            'code'    => 200,
                            'type'    => 'clouds',
                            'getType' => 'message',
                            'data'    => $res,
                        ];
                    } else {
                        return [];
                    }
                    break;
            }
            
            return $array;
        }else{
            
            $array = 
            [
                'code' => 201,
                'type' => 0,
                'message' => '云端地域不存在'
            ]; 
            
            return $array;
        }
    }
    
    /**
    ** 支付宝通道类
    **/
    
    //获取支付宝登录二维码
    public static function GetAlipayLoginQrcode()
    {
        error_reporting(0);
        $url = "https://auth.alipay.com/login/index.htm";
		$data = self::Get_curl_header($url, 0, "Accept: image/gif, image/jpeg, image/pjpeg, application/x-ms-application, application/xaml+xml, application/x-ms-xbap, */*
Referer: https://auth.alipay.com/login/index.htm?goto=https%3A%2F%2Fmy.alipay.com%2Fportal%2Fi.htm
Accept-Language: zh-Hans-CN,zh-Hans;q=0.5
Accept-Encoding: gzip, deflate
User-Agent: Mozilla/5.0 (Windows NT 10.0; WOW64; Trident/7.0; rv:11.0) like Gecko
Host: authet2.alipay.com
Connection: Keep-Alive
Cache-Control: no-cache");
		preg_match("/securityId: \"(.*?)\",/", $data["body"], $match);
		$authcenter_qrcode_login = $match[1];
		preg_match("/s.sid = \"(.*?)\"/", $data["body"], $match);
		$authcenter_querypwd_login = $match[1];
		$rds_form_token = getSubstr($data["body"], "<input type=\"hidden\" value=\"", "\" name=\"rds_form_token\"/>");
		$alieditUid = getSubstr($data["body"], "<input type=\"hidden\" id=\"alieditUid\" name=\"alieditUid\" value=\"", "\" />");
		preg_match("/ALIPAYJSESSIONID=(.*?);/", $data["header"], $match);
		$ALIPAYJSESSIONID = $match[1];
		$AliQCode = "https://qr.alipay.com/_d?_b=PAI_LOGIN_DY&amp;securityId=" . urlencode($authcenter_qrcode_login);
        $array['loginid'] = $authcenter_qrcode_login . "YPay" . $authcenter_querypwd_login . "YPay" . $rds_form_token . "YPay" . $alieditUid;
        $array['qrcodeurl'] = urlencode($AliQCode);
        return $array;
    }

    //获取支付宝登录状态
    public static function GetAlipayLoginStatus($id = '')
    {
        error_reporting(0);
        if (empty($id)) {
            $jialan['code'] = 0;
            $jialan['msg'] = "loginid不能为空";
            return $jialan;
        } 
        
        $YPay = explode("YPay", $id);
		$url = "https://securitycore.alipay.com/barcode/barcodeProcessStatus.json?";
		$post_intl["securityId"] = $YPay[0];
		$post_intl["_callback"] = "light.request._callbacks.callback2";
		$post_intl = http_build_query($post_intl);
		$data_intl = get_curl($url . $post_intl, 0, 0, 0, 0, "Accept: image/gif, image/jpeg, image/pjpeg, application/x-ms-application, application/xaml+xml, application/x-ms-xbap, */*
Referer: https://auth.alipay.com/login/index.htm?goto=https%3A%2F%2Fmy.alipay.com%2Fportal%2Fi.htm
Accept-Language: zh-Hans-CN,zh-Hans;q=0.5
Accept-Encoding: gzip, deflate
User-Agent: Mozilla/5.0 (Windows NT 10.0; WOW64; Trident/7.0; rv:11.0) like Gecko
Host: authet2.alipay.com
Connection: Keep-Alive
Cache-Control: no-cache");

        if($data_intl == false){
            $data_intl = get_curl("https://securitycore.alipay.com/barcode/barcodeProcessStatus.json?securityId=" . $YPay[0] . "&_callback=light.request._callbacks.callback1", 0, "https://auth.alipay.com/login/index.htm?goto=https%3A%2F%2Fconsumeprod.alipay.com%2Frecord%2Fstandard.htm", param($id, "cookie"), 0, "Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/55.0.2883.87 UBrowser/6.2.4094.1 Safari/537.36");
        }

		if (strpos($data_intl, "waiting")) {
			$array['code'] = 0;
            $array['msg'] = "二维码还未扫描";
            return $array;
		} elseif (strpos($data_intl, "scanned")) {
			$array['code'] = 0;
            $array['msg'] = "等待确认中";
            return $array;
		} else {
		$url = "https://authet2.alipay.com/login/index.htm";
		$post["support"] = "000001";
		$post["needTransfer"] = "";
		$post["CtrlVersion"] = "1,1,0,1";
		$post["loginScene"] = "index";
		$post["redirectType"] = "";
		$post["personalLoginError"] = "";
		$post["goto"] = "https://www.alipay.com/";
		$post["errorVM"] = "";
		$post["sso_hid"] = "";
		$post["site"] = "";
		$post["errorGoto"] = "";
		$post["rds_form_token"] = $YPay[2];
		$post["json_tk"] = "";
		$post["method"] = "qrCodeLogin";
		$post["logonId"] = "";
		$post["superSwitch"] = "true";
		$post["noActiveX"] = "false";
		$post["passwordSecurityId"] = $YPay[1];
		$post["qrCodeSecurityId"] = $YPay[0];
		$post["scid"] = "";
		$post["password_input"] = "";
		$post["J_aliedit_using"] = "true";
		$post["password"] = "";
		$post["J_aliedit_key_hidn"] = "password";
		$post["J_aliedit_uid_hidn"] = "alieditUid";
		$post["alieditUid"] = $YPay[3];
		$post["REMOTE_PCID_NAME"] = "_seaside_gogo_pcid";
		$post["_seaside_gogo_pcid"] = "";
		$post["_seaside_gogo_"] = "";
		$post["_seaside_gogo_p"] = "";
		$post["J_aliedit_prod_type"] = "";
		$post["security_activeX_enabled"] = "true";
		$post["checkCode"] = "";
		$post["idPrefix"] = "";
		$post["preCheckTimes"] = 5;
		$post["ua"] = "dxlTasyfLePewE7ifH7HyT5wJ5cASsGqMaYnOomEiyTBeHyI6CezVWDWcDouca6W6Y4Svep9ulZ8H0cF1X4Mgi.JZTbQL3NddYS7bCmG.cFh45fZXR3kTmjsMi3xByeTW2V5hnaat0y1OOiv8qoAfKgaUuigtJAp3UL2QgUVrpASMRKdStX0h.hzFfH26FHtMkCmnf1nRcw74yljdFFMC03XWUBNZDhPUI0aL76t.NVxaOJQngu.KFQoPrVjSWYgym6MackvvBhmL37Y0s4H.ROLsAdVrDnLoQR7y07wcwWbUSqq.6AdBebIIVg1RHjyn3K9ahqPk_HOBlXyg6_voFZWwvoFlVAZ9c_ARvTidwDCE.18sT9z2ELtWGaAVClk6HN0HXMQUwOH7Rr7sfpn3zp__eOAe75qTBmYMNXnYnChZmqWOAaVdJAcFpjoUtwtwwqcZvdvoX4_UH.06SpF.Z0i85GWt4jSkki5ijEyvav5KeLQX6Tvj7MziuxQasAOVX6CHZu62D3FhWwj1cesYq9iKyzNmhMcqc.ULS88i3oq2vZko8vOI3BFufd0GcYAMI.YS8a4IqoaE4ydLO4ALR.8WuHvpOZiilHq.hOZogZB2QoQApBuo5smKdhzGlcybdYtsoxPtD_jIRXtf7aRzIWtIlcgEHk6RyOhjsA7bSuWurcukkCAvTZtO05xq6s_lmjMUVNeyS34DpiEXKJlqmbi.amq3hj2Oph0AZtvPb8LvZJy8V.aYdcC4RH9UeAOEzzJgpeiiAAUcMRexpGs5ZDcBFdgn4MIXfq2aTIUFVHf0W3in7tGaeNmx1MUIWHHTL_3SmNYyPVaEo5qZzNLTMrc318MBiIFjcTmaub1IJw7IZefBjspVK9bzzYMwhe0ljkUqoCoshjN9rlXjKyJ.vsXLosDb7KEKmWejetEAPRlw2e49JmWJj5ohGrOGzlsTzyMUtY07nlv3gjXWkaUaE";
		$post = http_build_query($post);
		 $data = self::Get_curl_header($url, $post, 0, "Accept: image/gif, image/jpeg, image/pjpeg, application/x-ms-application, application/xaml+xml, application/x-ms-xbap, */*
Referer: https://auth.alipay.com/login/index.htm?goto=https%3A%2F%2Fmy.alipay.com%2Fportal%2Fi.htm
Accept-Language: zh-Hans-CN,zh-Hans;q=0.5
Accept-Encoding: gzip, deflate
User-Agent: Mozilla/5.0 (Windows NT 10.0; WOW64; Trident/7.0; rv:11.0) like Gecko
Host: authet2.alipay.com
Connection: Keep-Alive
Cache-Control: no-cache");
		$data = $data["header"];
		$ALIPAYJSESSIONID = explode("ALIPAYJSESSIONID=", $data)[2];
		$ALIPAYJSESSIONID = explode("Domain=", $ALIPAYJSESSIONID)[0];
		$ctoken = explode("ctoken=", $data)[2];
		$ctoken = explode("Domain=", $ctoken)[0];
		$CLUB_ALIPAY_COM = explode("CLUB_ALIPAY_COM=", $data)[1];
		$CLUB_ALIPAY_COM = explode("Domain=", $CLUB_ALIPAY_COM)[0];
		$JSESSIONID = explode("JSESSIONID=", $data)[1];
		$JSESSIONID = explode(";", $JSESSIONID)[0];
		$alipay = explode("alipay=", $data)[1];
		$alipay = explode(";", $alipay)[0];
		$spanner = explode("spanner=", $data)[1];
		$spanner = explode(";", $spanner)[0];
		if ($ALIPAYJSESSIONID && $CLUB_ALIPAY_COM) {
			$cookies = "zone=GZ00C; JSESSIONID=" . $JSESSIONID . "; ali_apache_tracktmp=\"uid=" . $CLUB_ALIPAY_COM . "\"; IntroKey=true; session.cookieNameId=ALIPAYJSESSIONID; ALIPAYJSESSIONID=" . $ALIPAYJSESSIONID . " ctoken=" . $ctoken . " CLUB_ALIPAY_COM=" . $CLUB_ALIPAY_COM;
		}
			$array['msg'] = "登录成功";
			$array['code'] = 1;
			$array['cookie'] = base64_encode($cookies);
			return $array;
		}

    }
    
    public static function getAliPayMoney($Cookie){

        switch (rand(1, 5)) {
            case 1:
                $Url = "https://my.alipay.com/portal/i.htm?src=yy_taobao_gl_01&sign_from=3000&sign_account_no=&guide_q_control=top";
                break;
            case 2:
                $Url = "https://zht.alipay.com/asset/newIndex.htm";
                break;
            case 3:
                $Url = "https://shenghuo.alipay.com/send/payment/fill.htm?_pdType=adbhajcaccgejhgdaeih";
                break;
            case 4:
                $Url = "https://custweb.alipay.com/account/index.htm";
                break;
            case 5:
                $Url = "https://personalweb.alipay.com/portal/i.htm";
                break;
            default:
                $Url = "http://egg.alipay.com/egg/advice.htm";
                break;
        }

        //$Bao_Url = 'https://my.alipay.com/portal/i.htm?src=yy_taobao_gl_01&sign_from=3000&sign_account_no=&guide_q_control=top';
        $referer = $Url;
        $ua = 'accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8
        accept-encoding: gzip, deflate, br
        accept-language: zh-CN,zh;q=0.9
        cache-control: max-age=0
        Cookie: ' . @$Cookie . '
        referer: ' . $referer . '
        upgrade-insecure-requests: 1
        user-agent: Mozilla/5.0 (Linux; Android 10.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/45.0.2454.101 Safari/537.36 360Browser/9.2.5584.400';
        //$data = $this->Get_Money_curl_intl($Url, 0,$referer,$Cookie,0,$ua);
        $Money = self::Get_Alipay_Cookie_Beat($Url, $Cookie);
        return $Money;

    }
    
    public static function Get_Alipay_Cookie_Beat($Url_Referer, $Cookie = null)
    {
        //$ctoken = $this->getSubstr($Cookie, "ctoken=", ";");
        $alipay_url = "https://lab.alipay.com/user/balance/index.htm";
        $Url = "https://uemprod.alipay.com/service.json?ctoken=&_input_charset=utf-8&_ksTS=" . microtime(true) . "&operation=mrchcenter.artisan.v2.ext.query&data=%7B%22pageSource%22%3A%22b_site_mrchenter_home_index_route%22%7D";
        $referer = $Url_Referer . "?&t=" . time();
        $referer1 = "https://mrchportalweb.alipay.com/";
        $ua = "accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8
        accept-encoding: gzip, deflate, br
        accept-language: zh-CN,zh;q=0.9
        cache-control: max-age=0
        Cookie: " . $Cookie . "
        referer: " . $Url . "?referer=" . $referer . "
        upgrade-insecure-requests: 1
        user-agent: Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/47.0.2526.80 Safari/537.36 Core/1.47.277.400 QQBrowser/9.4.7658.400";
        $result = self::Get_Money_curl_intl($Url_Referer, 0, $referer, $Cookie, 0, $ua);
        $result = mb_convert_encoding(self::Get_Money_curl_intl($alipay_url, 0, $referer, $Cookie, 0), "UTF-8", "GB2312");
        if (!strstr($result, "可用余额")) {
            $result = "-1";
        } else {
            $result = getSubstr($result, "<span class=\"fm-free\">", "</span>");
        }
        return $result;
    }
    
    
    //获取支付宝余额 参数:cookie
    public static function getAlipayMoney2($Cookie){
        switch(rand(1,9)){
				case 1:
					$data = self::Get_Alipay_Cookie('https://personalweb.alipay.com/portal/i.htm',$Cookie);
				break;
				case 2:
					$data = self::Get_Alipay_Cookie('https://my.alipay.com/wealth/index.html',$Cookie);
 				break;
				case 3:
					$data = self::Get_Alipay_Cookie('https://110.alipay.com/sc/index.htm',$Cookie);
				break;
				case 4:
					$data = self::Get_Alipay_Cookie('https://my.alipay.com/portal/i.htm',$Cookie);
				break;
				case 5:
					$data = self::Get_Alipay_Cookie('https://shanghu.alipay.com/home/switchPersonal.htm',$Cookie);
				break;
				case 6:
					$data = self::Get_Alipay_Cookie('https://cshall.alipay.com/lab/question.htm',$Cookie);
				break;
				case 7:
					$data = self::Get_Alipay_Cookie('https://cshall.alipay.com/lab/cateQuestion.htm',$Cookie);
				break;
				case 8:
					$data = self::Get_Alipay_Cookie('https://cshall.alipay.com/lab/help_detail.htm',$Cookie);
				break;
				case 9:
					$data = self::Get_Alipay_Cookie('https://egg.alipay.com/egg/index.htm',$Cookie);
				break;
				default:
					$data = self::Get_Alipay_Cookie('http://egg.alipay.com/egg/advice.htm',$Cookie);
				break;
			}

			return $data;
    }
    
    //获取支付宝信息
    public static function Get_Alipay_Cookie($Url_Referer, $Cookie = null)
	{ 
		$Url ="https://shanghu.alipay.com/user/myAccount/index.htm";
		$referer = $Url_Referer.'?&t='.time();
		$ua = 'accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8
            accept-encoding: gzip, deflate, br
            accept-language: zh-CN,zh;q=0.9
            cache-control: max-age=0
            Cookie: '.@$Cookie.'
            referer: '.$Url.'?referer='.$referer.'
            upgrade-insecure-requests: 1
            user-agent: Mozilla/5.0 (Linux; Android 10.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/45.0.2454.101 Safari/537.36 360Browser/9.2.5584.400';
		$result = self::Get_Money_curl_intl($Url_Referer,0,$referer,$Cookie,0,$ua);
		$result = mb_convert_encoding(self::Get_Money_curl_intl($Url,0,$referer,$Cookie,0,$ua),"UTF-8","GB2312" );
		if(!strstr($result, '余额'))$result = "-1";else $result = getSubstr($result, '<em class="aside-available-amount">','</em>元</span></li>');
		return $result;
	} 
	
    
    //添加QQ
    public static function Api_AddQQ($qq, $address = '')
    {
        $res = qqyunpost($address . '/addqq',[ "botId" => $qq,"xy"=>"9"]);
        return $res;
    }

    //删除QQ
    public static function Api_DelQQ($qq, $address = '')
    {
        $res = qqyunpost($address . '/deloneqq',[ "botId" => $qq]);
        return true;
    }

    //登录QQ  已完成无返回信息
    public static function Api_Login($qq)
    {
        $res = qqyunpost(getConfig()['myqqurl'] . '/loginoneqq',[ "botId" => $qq]);
        return true;
    }

    //下线QQ 已完成，无返回信息
    public static function Api_Logout($qq)
    {
        $res = qqyunpost(getConfig()['myqqurl'] . '/logoutoneqq',[ "botId" => $qq]);
        return true;
    }

    //获取钱包操作密钥  已完成
    public static function Api_GetTenPayPsKey($qq)
    {
        $res = qqyunpost(getConfig()['myqqurl'] . '/getPskey',[ "botId" => $qq,"domain"=>'vip.qq.com']);
        return $res['data']['pskey'];
    }

    //获取账号Skey    已完成
    public static function Api_GetCookies($qq,$cloud_id = '')
    {
          //查询是否存在该云端地域
        $cloudArr = Db::name('ypay_cloud')->where('id',$cloud_id)->find();
        $res = qqyunpost($cloudArr['address'] . '/getWalletCookie',[ "botId" => $qq]);
        return $res['data']['cookie'];
    }

    //获取账号余额  已完成
    public static function Api_QueryBalance($qq,$cloud_id = '')
    {
          //查询是否存在该云端地域
        $cloudArr = Db::name('ypay_cloud')->where('id',$cloud_id)->find();
        $res = qqyunpost($cloudArr['address']  . '/getqbxx',[ "botId" => $qq]);
        return $res['jl']['money'];
    }

    //获取登录二维码
    public static function Api_CreatQrCodeInfo($qq,$cloud_id = '')
    {
          //查询是否存在该云端地域
        $cloudArr = Db::name('ypay_cloud')->where('id',$cloud_id)->find();
        $res = qqyunpost($cloudArr['address']  . '/getloginqr',[ "botId" => $qq,"xy"=>"9"]);
        return $res['data']['qr'];
    }

    //获取扫码状态
    public static function Api_GetQrCodeStatus($qq,$cloud_id = '')
    {
          //查询是否存在该云端地域
        $cloudArr = Db::name('ypay_cloud')->where('id',$cloud_id)->find();
        $res = qqyunpost($cloudArr['address']  . '/getlqstatus',[ "botId" => $qq]);
        $res = json_decode($res['data'], true);
        return $res['code'];
    }

    //获取所有QQ
    public static function Api_GetOnlineQQlist($cloud_id = '')
    {
          //查询是否存在该云端地域
        $cloudArr = Db::name('ypay_cloud')->where('id',$cloud_id)->find();
        $res = qqyunpost($cloudArr['address']  . '/listBot',[ ]);
        return $res;
    }
    
        //创建通道
    public static function QCreate()
    {
        $url = 'https://ssl.ptlogin2.tenpay.com/ptqrshow?appid=546000248&e=2&l=M&s=3&d=72&v=4&t=0.4080289' . time() . '&daid=120&pt_3rd_aid=0';
        //请求资源
        $result = get_curl2($url, 0, 'https://xui.ptlogin2.tenpay.com/', 0, 0, 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/90.0.4430.212 Safari/537.36', 0, 1);
        preg_match('/qrsig=(.*?);/', $result['header'], $qrsig);
        if ($qrsig[1] == '') {
            return ['msg' => '二维码获取失败', 'code' => 0];
        } else {
            return ['msg' => '获取成功', 'code' => 1, "qr_url" => base64_encode($result['body']), "qrsig" => $qrsig[1]];
        }
    }
    
    public function GetOrder($qq, $cookie)
    {
        $beat = $this->QQpayCurl('https://myun.tenpay.com/cgi-bin/clientv1.0/qwallet_record_list.cgi?limit=10&offset=0&s_time=2019-04-20&ref_param=&source_type=7&time_type=0&bill_type=3&uin=' . $qq, $cookie);

        return $beat;
    }
    
    //获取QQ余额
    public function getQQPayMoney($Cookie,$qq){
        	switch(rand(1,9)){
				case 1:
					$data = self::getQQPayInfo('https://www.tenpay.com/v3/account/charge/charge.shtml',$Cookie,$qq);
				break;
				case 2:
					$data = self::getQQPayInfo('https://www.tenpay.com/v3/trade/account_detail.shtml',$Cookie,$qq);
 				break;
				case 3:
					$data = self::getQQPayInfo('https://www.tenpay.com/v3/account/pay/paycard.shtml',$Cookie,$qq);
				break;
				case 4:
					$data = self::getQQPayInfo('https://www.tenpay.com/v3/account/info/index.shtml',$Cookie,$qq);
				break;
				case 5:
					$data = self::getQQPayInfo('https://www.tenpay.com/v2/safe/v2/index.shtml',$Cookie,$qq);
				break;
				case 6:
					$data = self::getQQPayInfo('https://www.tenpay.com/v2/safe/v2/safe_tool.shtml',$Cookie,$qq);
				break;
				case 7:
					$data = self::getQQPayInfo('https://www.tenpay.com/v2/safe/course.shtml',$Cookie,$qq);
				break;
				case 8:
					$data = self::getQQPayInfo('https://www.tenpay.com/v2/cs/v2/index.shtml',$Cookie,$qq);
				break;
				case 9:
					$data = self::getQQPayInfo('https://www.tenpay.com/v2/safe/course.shtml',$Cookie,$qq);
				break;
				default:
					$data = self::getQQPayInfo('https://www.tenpay.com/v3/account/info/index.shtml',$Cookie,$qq);
				break;
			}
			
			$json = json_decode($data, true);

			if($json['retcode']==0 && $json['retmsg']=="OK"){
				$money  = $json['records'][0]['balance']/100;
			}else{
				$money  = -1;
			}
			
			return $money;
    }
    
    //获取QQ信息
    public static function getQQPayInfo($Url_Referer, $Cookie = null,$qq = null)
	{
		$skey = getSubstr($Cookie, "skey=", ";");
		$Url = "https://myun.tenpay.com/cgi-bin/clientv1.0/qwallet_account_list.cgi?limit=10&offset=0&s_time=" . date("Y-m-d") . "&time_type=0&source_type=7&pay_type=2&ref_param=&skey=" . $skey . "&skey_type=2&uin=" . $qq;
		$p_skey = getSubstr($Cookie, "p_skey=", ";");
		$Cookie = "p_skey=" . $p_skey . ";";
		$referer = $Url_Referer . "?&t=" . time();
		$ua = "accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8
accept-encoding: gzip, deflate, br
accept-language: zh-CN,zh;q=0.9
cache-control: max-age=0
referer: " . $Url . "?referer=" . $referer . "
upgrade-insecure-requests: 1
user-agent: Mozilla/5.0 (Linux; Android 10.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/45.0.2454.101 Safari/537.36 QQBrowser/9.2.5584.400";
		$result = self::Get_Money_curl_intl($Url_Referer, 0, $referer, $Cookie, 0, $ua);
		
		$result = self::Get_Money_curl_intl($Url, 0, $referer, $Cookie, 0, $ua);
		return $result;
	}
    

    public function GetOrderDetail($billno, $qq, $cookie, $pskey, $skey)
    {
        $beat = $this->QQpayCurl('https://mqq.tenpay.com/cgi-bin/qwallet_app/qpayment_trans_detail.cgi?listid=' . $billno . '&_t=' . time() . '&uin=' . $qq . '&pskey=' . $pskey . '&skey_type=2&g_tk=&skey=' . $skey, $cookie);
        return $beat;
    }
    
    //获取QQ余额
    public static function qqpay($qq,$cookie){
        $uin = $qq;
    $skey = getSubstr($cookie,"skey=", ";");
    $p_skey = getSubstr($cookie,"p_skey=", ";");
    //exit($p_skey);
    $url = 'https://myun.tenpay.com/cgi-bin/clientv1.0/qwallet.cgi?ver=2.0&chv=3';
    $data = 'pskey='.$p_skey.'&pskey_scene=client&skey=&skey_type=2&app_info=appid%230%7Cbargainor_id%230%7Cchannel%23wallet&uin='.$uin.'&need_suggest=1&h_net_type=WIFI&h_model=android_mqq&h_edition=86&h_location=CDC7BC237126988EBB4A1F7D53E73429%7C%7CM2012K11C%7C12%2Csdk31%7C1C1612E2C3EF11331C853585BE44D97F%7CD41D8CD98F00B204E9800998ECF8427E%7C0%7C&h_qq_guid=1C1612E2C3EF11331C853585BE44D97F&h_qq_appid=537101852&h_exten=';
    $post = 'req_text='.self::qqEncodeCrypt($data).'&skey_type=2&msgno='.$uin.date("Ymd").time().'&skey=';
    $ret = get_curl($url,$post);
    
    $ret = self::qqDecodeCrypt($ret);
    
    $json = json_decode($ret,true);
    
    if(isset($json['user_info_extend']["realauth_content"])){
        if($json['user_info_extend']["realauth_content"] == "立即去认证"){
            return ['code'=>201,'msg'=>'该账户未实名,无法使用'];
        }
    }

    if($json['retcode']==0 and $json['retmsg']=='ok'){
        $money = $json['balance']/100;
        return ['code'=>1,'money'=>$money,'skey'=>$json['skey'],'name'=>$json['purchaser_true_name'],'qq'=>$json['purchaser_id']];
    }else{
        return ['code'=>201,'msg'=>'Url:1 '.$json['retmsg']];
    }
}
    
    //获取微转Q地址
    public static function getWZQH5Url($cookie,$qq,$price,$money){
        $data = self::qqpay($qq,$cookie);
        if($data['code'] == 201){
            return $data;
        }
  $uin = $qq;
  $skey = getSubstr($cookie,"skey=", ";");
 $p_skey = getSubstr($cookie,"p_skey=", ";");
  $cookie = base64_decode($cookie);
  $price = intval(($price + $data['money'])*100);
  $url = 'https://mqq.tenpay.com/cgi-bin/qwallet_app/qpayment_transaction.cgi?ver=2.0&chv=3';
  $data = 'pskey='.$p_skey.'&payee_nick=&come_from=2&payee_uin=1008611&memo=666&source=3&skey_type=2&total_fee='.$price.'&skey='.$skey.'&uin='.$uin.'&h_net_type=WIFI&h_model=android_mqq&h_edition=86&h_location=CDC7BC237126988EBB4A1F7D53E73429%7C%7CM2012K11C%7C12%2Csdk31%7C1C1612E2C3EF11331C853585BE44D97F%7CD41D8CD98F00B204E9800998ECF8427E%7C0%7C&h_qq_guid=1C1612E2C3EF11331C853585BE44D97F&h_qq_appid=537101852&h_exten=';
  $post = 'req_text='.self::qqEncodeCrypt($data).'&msgno='.$uin.date("Ymd").time().'&skey=&skey_type=2&random=0';
  $ret = get_curl($url,$post);
  $json = json_decode(self::qqDecodeCrypt($ret),true);
  if($json['retcode']!=0){
      return false;
  }else{
      $token_id = $json['token_id'];
      $url = 'https://myun.tenpay.com/cgi-bin/clientv1.0/qpay_gate.cgi?ver=2.0&chv=3';
        $data = 'pskey='.$p_skey.'&pskey_scene=client&skey_type=2&come_from=2&token_id='.$token_id.'&skey='.$skey.'&uin='.$uin.'&model_xml=<deviceinfo><MANUFACTURER name="Xiaomi"><MODEL name="M2012K11C"><VERSION_RELEASE name="12"><VERSION_INCREMENTAL name="V13.0.13.0.SKKCNXM"><DISPLAY name="SKQ1.211006.001 test-keys"></DISPLAY></VERSION_INCREMENTAL></VERSION_RELEASE></MODEL></MANUFACTURER></deviceinfo>&device_id=19944ff4cbed4244&h_net_type=WIFI&h_model=android_mqq&h_edition=86&h_location=CDC7BC237126988EBB4A1F7D53E73429%7C%7CM2012K11C%7C12%2Csdk31%7C1C1612E2C3EF11331C853585BE44D97F%7CD41D8CD98F00B204E9800998ECF8427E%7C0%7C&h_qq_guid=1C1612E2C3EF11331C853585BE44D97F&h_qq_appid=537101852&h_exten=';
        $post = 'req_text='.self::qqEncodeCrypt($data).'&skey_type=2&msgno='.$uin.date("Ymd").time().'&skey=&random=0';
        $ret = self::qqDecodeCrypt(get_curl($url,$post));
        $json = json_decode($ret,true);
        if($json['retcode']!=0){
           return false;
        }

        $sdk_url = $json['balance_info']['miniapp']['url'];
            return explode("path=",$sdk_url)[1];
  }
    }
    

    
    protected function QQpayCurl($api, $cookie)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api);
        curl_setopt($ch, CURLOPT_COOKIE, $cookie);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT,
            "Mozilla/5.0 (Linux; U; Android 4.4.1; zh-cn; R815T Build/JOP40D) AppleWebKit/533.1 (KHTML, like Gecko)Version/4.0 MQQBrowser/4.5 Mobile Safari/533.1");
        $res = curl_exec($ch);
        curl_close($ch);
        return $res;
    }
    

    public static function GetQLoginStatus($qrsig)
    {
        error_reporting(0);
        if ($qrsig == null || $qrsig == '') {
            throw new Exception('qrsig不能为空');
        }
        $url = 'https://ssl.ptlogin2.tenpay.com/ptqrlogin?u1=https%3A%2F%2Fwww.tenpay.com%2Fv2%2Fres%2Fjs%2Fyui%2Fbuild%2Flogin%2Fptlogin.shtml&ptqrtoken=' . getqrtoken($qrsig) . '&ptredirect=0&h=1&t=1&g=1&from_ui=1&ptlang=2052&action=0-0-' . time() . rand(111, 999) . '&js_ver=21050810&js_type=1&login_sig=' . $qrsig . '&pt_uistyle=34&aid=546000248&daid=120&';
        $result = get_curl2($url, 0, 'https://xui.ptlogin2.tenpay.com/', 'qrsig=' . $qrsig . ';', 0, 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/90.0.4430.212 Safari/537.36', 0, 1);
        //取二维码状态
        preg_match("/ptuiCB\(\'(.*?)\'\,/", $result['body'], $state);
        $state = $state[1];
        switch ($state) {
            case '0':
                $ck = getcookie($result['header']);
                preg_match('/\'https:(.*?)\'/', $result['body'], $u2);
                $u2 = 'https:' . $u2[1];
                //准备继续取CK
                $ret = get_curl2($u2, 0, 'https://xui.ptlogin2.tenpay.com/', $ck, 0, 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/90.0.4430.212 Safari/537.36', 0, 1);
                $ck2 = getcookie($ret['header']);
                //组合新的CK
                $ck = $ck . $ck2;
                return ['msg' => '获取成功', 'code' => 1, "cookie" => base64_encode($ck)];
                break;
            case '65':
                //二维码过期
                return ['msg' => '二维码过期', 'code' => -1];
                break;
            case '66':
                //等待扫描
                return ['msg' => '请打开手机QQ扫描下方二维码', 'code' => 0];
                break;
            case '67':
                //正在验证
                return ['msg' => '二维码已扫描但未确认', 'code' => 0];
                break;
            default:
                return ['msg' => '扫描类型错误', 'code' => -1];
        }
    }

    //生成自定义二维码
    public static function QTransferSet($qq, $memo, $je, $skey, $pskey)
    {
        $je = $je *100;
        $str = self::etaencode('channel=0&extend=explain%3D'.$memo.'&msgno='.$qq.date("Ymd").time().'&pskey='.$pskey.'&pskey_scene=client&skey_type=2&trans_fee='.trim((string)$je).'&type=1&uin='.$qq.'&usage=&h_edition=122&h_location=A9F9FA12-E3E0-40FE-BF22-F5CC82EA91AB||iPhone X|18.0.1|||0&h_model=ios_iphone_mqq&h_net_type=WIFI&h_pkg_name=com.tencent.mqq&h_qq_appid=537250566&h_qq_guid=3797D7277BDA97ED23DF6E3E7DE74BEA');
        $str = 'req_text='.$str;
        $url = 'https://mqq.tenpay.com/cgi-bin/qr_code/qr_code_generate.cgi';
        $reult = json_decode(trim(self::etadecode(self::get_qq_curl($url,$str))),true);

        $qr_url = 'https://i.qianbao.qq.com/wallet/sqrcode.htm?m=tenpay&f=wallet&a=1&ac='.self::strFilter($reult['auth_code']).'&u='.$qq.'&n=H5';
        return $qr_url;
    }
    
     // 生成QQ自定义二维码
    public static function CreateQqPayCodeUrl($skey='',$qq='',$je=0,$memo='',$pskey='')
    {
        $je = $je *100;
        $str = 'pskey='.$pskey.'&extend=explain%3D'.$memo.'&skey_type=0&trans_fee='.$je.'&skey=&uin='.$qq.'&type=1&h_net_type=WIFI&h_model=android_mqq&h_edition=95&h_location=68C327592F243273E28B4A871C9B5C46%7C%7CMI%209%7C11%2Csdk30%7C1%7C&h_qq_guid=C63839CDC59839396E48EB4F7E1D8682&h_qq_appid=537124039&h_exten=';
        $enStr =strtoupper(self::encodeCrypt($str));
        $curl = curl_init();
        curl_setopt_array($curl, array(
          CURLOPT_URL => 'https://mqq.tenpay.com/cgi-bin/qr_code/qr_code_generate.cgi?ver=2.0&chv=3',
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => '',
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => 'POST',
          CURLOPT_POSTFIELDS => 'req_text='.$enStr.'&skey=&skey_type=2&random=',
          CURLOPT_HTTPHEADER => array(
            'Content-Type: application/x-www-form-urlencoded',
            'Cookie: x-stgw-ssl-info=470def52a407bb99ce53c34b0444906f_0.108_-_1_N_Y_I_TLSv1.2_ECDHE-RSA-AES128-GCM-SHA256_33000_1:41:0:0:0:0:0'
          ),
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        $result = json_decode(trim(self::decodeCrypt(trim($response))),true);
        $authcode = $result['auth_code'] ?? "";
        if(empty($authcode))
        {
            echo($result['retmsg']);
            //exit($result['retmsg']);
            //此处由于可能会有判断是否成功，所以只要失败不论原因都返回失败
            exit($result['retmsg']);
            
            
            
        }
        $authcode = str_replace('=','%3D',$authcode);
        $url ="https://i.qianbao.qq.com/wallet/sqrcode.htm?m=tenpay&f=wallet&a=1&u={$qq}&ac={$authcode}&n=";
        return $url;
        //$this->success('返回成功', $this->request->param());
    }
    
    
       static function etaencode($data,$i=0){
        $key = ['9973e345', '5dac6cf7', 'f5c88847', 'f02c91bd', '3c0c3ea1', '8b00b67f', 'c28931b2', 'c8510256', 'c42bfdef', '890fe53c', '0d181064', '0ef940b7', '10d75d6d', 'c5d8e9f6', '66c3987e', 'c48cebe3'];
        $privatekey = $key[$i];
        if(strlen($data) %16){
            $data = str_pad($data,strlen($data) + 16 - strlen($data) % 16, '\0');
        }
        $etaencode = openssl_encrypt($data,'DES-ECB',$privatekey,OPENSSL_NO_PADDING);
        return strtoupper(bin2hex($etaencode));
    }

    static function etadecode($string,$i=0){
        $key = ['9973e345', '5dac6cf7', 'f5c88847', 'f02c91bd', '3c0c3ea1', '8b00b67f', 'c28931b2', 'c8510256', 'c42bfdef', '890fe53c', '0d181064', '0ef940b7', '10d75d6d', 'c5d8e9f6', '66c3987e', 'c48cebe3'];
        $privatekey = $key[$i];
        $etadata = hex2bin(trim($string));
        $etadecode = openssl_decrypt($etadata,'DES-ECB',$privatekey,OPENSSL_NO_PADDING);
        return trim($etadecode);
    }

    function jsonp_decode($jsonp, $assoc = false)
    {
        $jsonp = trim($jsonp);
        if (isset($jsonp[0]) && $jsonp[0] !== '[' && $jsonp[0] !== '{') {
            $begin = strpos($jsonp, '(');
            if (false !== $begin) {
                $end = strrpos($jsonp, ')');
                if (false !== $end) {
                    $jsonp = substr($jsonp, $begin + 1, $end - $begin - 1);
                }
            }
        }
        return json_decode(mb_convert_encoding($jsonp, 'UTF-8', 'GBK'), $assoc);
    }

    /****************获取账号余额***************/
    public function GetMoney($cookie, $pid)
    {
        preg_match('/ctoken=(.*?);/', $cookie, $uin);
        $startDateInput = rawurlencode(date("Y-m-d H:i:s", strtotime('now -1')));//获取5分钟之内的订单
        $endDateInput = rawurlencode(date("Y-m-d H:i:s", strtotime('now')));
        $str = 'billUserId=' . $pid . '&pageNum=1&pageSize=20&status=ALL&sortType=0&_input_charset=gbk&startTime=' . $startDateInput . '&endTime' . $endDateInput;
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://mbillexprod.alipay.com/enterprise/accountTotalAssetQuery.json?ctoken=' . $uin[1],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $str,
            CURLOPT_HTTPHEADER => array(
                'authority: mbillexprod.alipay.com',
                'sec-ch-ua: "Google Chrome";v="93", " Not;A Brand";v="99", "Chromium";v="93"',
                'accept: application/json, text/javascript, */*; q=0.01',
                'content-type: application/x-www-form-urlencoded; charset=UTF-8',
                'x-requested-with: XMLHttpRequest',
                'sec-ch-ua-mobile: ?0',
                'user-agent: Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/62.0.3202.89 Mobile Safari/537.36',
                'origin: https://mbillexprod.alipay.com',
                'sec-fetch-site: same-origin',
                'sec-fetch-mode: cors',
                'sec-fetch-dest: empty',
                'referer: https://mbillexprod.alipay.com/enterprise/accountTotalAssetQuery.htm',
                'accept-language: zh-CN,zh;q=0.9,en;q=0.8',
                'cookie: ' . $cookie
            ),
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        $res = json_decode($response, true);
        if (empty($res) || empty($res['result']['availableBalance'])) {
            return array("status" => false, "money" => "-1", "time" => time());
        }
        if ($res['status'] != "succeed") {
            return array("status" => false, "money" => "-1", "time" => time());
        }
        $money = $res['result']['availableBalance'];
        return array("status" => true, "money" => $money, "time" => time());
    }

    public function GetMoneyTwo($cookie)
    {
        preg_match('/ctoken=(.*?);/', $cookie, $uin);
        $res = $this->alipayCurl('https://mrchportalweb.alipay.com/user/asset/query.json?ctoken=' . $uin[1] . '&_input_charset=utf-8&_ksTS=' . time(), $cookie);
        $res = json_decode($res, true);
        if (empty($res) || empty($res['data']['rpc']['assets']['data']['alipayBalanceList'][0]['availableBalance'])) {
            return array("status" => false, "money" => "-1", "time" => time());
        }
        if (empty($res['success'])) {
            return array("status" => false, "money" => "-1", "time" => time());
        }
        $money = $res['data']['rpc']['assets']['data']['alipayBalanceList'][0]['availableBalance'];
        return array("status" => true, "money" => $money, "time" => time());
    }
    
    


    /****************获取会员信息***************/
    protected function alipayCurl($api, $cookie)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api);
        curl_setopt($ch, CURLOPT_COOKIE, $cookie);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_REFERER, 'https://b.alipay.com/page/mbillexprod/alipay/account');
        curl_setopt($ch, CURLOPT_USERAGENT,
            "Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.198 Safari/537.36");
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $res;
    }

    /************获取⽀付宝账单请求*************/
    public function getAliOrder($cookie, $pid)
    {
        $startDateInput = rawurlencode(date("Y-m-d H:i:s", time() - (60 * 5)));//获取5分钟之内的订单
        $endDateInput = rawurlencode(date("Y-m-d H:i:s", strtotime('now')));
        preg_match('/ctoken=(.*?);/', $cookie, $uin);
        $str = 'endDateInput=' . $endDateInput . '0&precisionQueryKey=tradeNo&precisionQueryValue=&showType=1&startDateInput=' . $startDateInput . '&billUserId=' . $pid . '&pageNum=1&pageSize=100&startTime=' . $startDateInput . '&endTime=' . $endDateInput . '&status=1&queryEntrance=1&sortTarget=tradeTime&activeTargetSearchItem=tradeNo&accountType=&sortType=0&startAmount&endAmount&targetMainAccount&precisionValue&goodsTitle&total=0&_input_charset=gbk';
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://mbillexprod.alipay.com/enterprise/fundAccountDetail.json?ctoken=' . $uin[1],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $str,
            CURLOPT_HTTPHEADER => array(
                'authority: mbillexprod.alipay.com',
                'sec-ch-ua: "Google Chrome";v="93", " Not;A Brand";v="99", "Chromium";v="93"',
                'accept: application/json, text/javascript, */*; q=0.01',
                'content-type: application/x-www-form-urlencoded; charset=UTF-8',
                'x-requested-with: XMLHttpRequest',
                'sec-ch-ua-mobile: ?0',
                'user-agent: Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/62.0.3202.89 Mobile Safari/537.36',
                'origin: https://mbillexprod.alipay.com',
                'sec-fetch-site: same-origin',
                'sec-fetch-mode: cors',
                'sec-fetch-dest: empty',
                'referer: https://business.alipay.com/user/mbillexprod/account/detail',
                'accept-language: zh-CN,zh;q=0.9,en;q=0.8',
                'cookie: ' . $cookie
            ),
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        $res = json_decode($response, true);
        return empty($res) ? json_decode(mb_convert_encoding($response, 'UTF-8', 'GBK'), true) : $res;
    }
    
    //免ck
    //时间不需要动,需要传入参数公钥 私钥 应用ID 支付宝PID
    public function getAliMckOrder($pid,$appid,$rsaPrivateKey,$alipayrsaPublicKey)
    {
        $aop = new \AopClient ();
        $aop->gatewayUrl = 'https://openapi.alipay.com/gateway.do';
        $aop->appId = $appid;
        $aop->rsaPrivateKey = $rsaPrivateKey;
        $aop->alipayrsaPublicKey=$alipayrsaPublicKey;
        $aop->apiVersion = '1.0';
        $aop->signType = 'RSA2';
        $aop->postCharset='UTF-8';
        $aop->format='JSON';
        $request = new \AlipayDataBillAccountlogQueryRequest ();
        $request->setBizContent("{" .
        "  \"start_time\":\"".date("Y-m-d H:i:s", time() - (60 * 5))."\"," .
        "  \"end_time\":\"".date("Y-m-d H:i:s", strtotime('now'))."\"," .
        //"  \"alipay_order_no\":\"20190101***\"," .
        //"  \"merchant_order_no\":\"TX***\"," .
        "  \"page_no\":\"1\"," .
        "  \"page_size\":\"20\"," .
        //"  \"trans_code\":\"101101,301101\"," .
        //"  \"agreement_no\":\"20215606000635888888\"," .
        "  \"agreement_product_code\":\"FUND_SIGN_WITHHOLDING\"," .
        "  \"bill_user_id\":\"".$pid."\"" .
        "}");
        $result = $aop->execute ($request); 
        $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
        return $result->$responseNode;
    }
    
    public function getAliMckAccountMoney($pid,$appid,$rsaPrivateKey,$alipayrsaPublicKey)
    {
        $aop = new \AopClient ();
        $aop->gatewayUrl = 'https://openapi.alipay.com/gateway.do';
        $aop->appId = $appid;
        $aop->rsaPrivateKey = $rsaPrivateKey;
        $aop->alipayrsaPublicKey=$alipayrsaPublicKey;
        $aop->apiVersion = '1.0';
        $aop->signType = 'RSA2';
        $aop->postCharset='UTF-8';
        $aop->format='json';
        $request = new \AlipayDataBillAccountlogQueryRequest();
        $request->setBizContent("{" .
       "  \"start_time\":\"".date("Y-m-d H:i:s", time() - (60 * 5))."\"," .
        "  \"end_time\":\"".date("Y-m-d H:i:s", strtotime('now'))."\"," .
        //"  \"alipay_order_no\":\"20190101***\"," .
        //"  \"merchant_order_no\":\"TX***\"," .
        "  \"page_no\":\"1\"," .
        "  \"page_size\":\"20\"," .
        //"  \"trans_code\":\"101101,301101\"," .
        //"  \"agreement_no\":\"20215606000635888888\"," .
        "  \"agreement_product_code\":\"FUND_SIGN_WITHHOLDING\"," .
        "  \"bill_user_id\":\"".$pid."\"" .
        "}");
        $result = $aop->execute ( $request); 
        $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
        return $result->$responseNode;
    }

    public static function Get_Money_curl_intl($url, $post = 0, $referer = 0, $cookie = 0, $header = 0, $ua = 0, $nobaody = 0)
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		$httpheader[] = "Accept:*/*";
		$httpheader[] = "Accept-Encoding:gzip,deflate,sdch";
		$httpheader[] = "Accept-Language:zh-CN,zh;q=0.8";
		$httpheader[] = "Connection:close";
		curl_setopt($ch, CURLOPT_HTTPHEADER, $httpheader);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		if ($post) {
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
		}
		if ($header) {
			curl_setopt($ch, CURLOPT_HEADER, true);
		}
		if ($cookie) {
			curl_setopt($ch, CURLOPT_COOKIE, $cookie);
		}
		if ($referer) {
			if ($referer == 1) {
				curl_setopt($ch, CURLOPT_REFERER, "http://m.qzone.com/infocenter?g_f=");
			} else {
				curl_setopt($ch, CURLOPT_REFERER, $referer);
			}
		}
		if ($ua) {
			curl_setopt($ch, CURLOPT_USERAGENT, $ua);
		} else {
			curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Linux; U; Android 4.4.1; zh-cn; R815T Build/JOP40D) AppleWebKit/533.1 (KHTML, like Gecko)Version/4.0 MQQBrowser/4.5 Mobile Safari/533.1");
		}
		if ($nobaody) {
			curl_setopt($ch, CURLOPT_NOBODY, 1);
		}
		curl_setopt($ch, CURLOPT_ENCODING, "gzip");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$ret = curl_exec($ch);
		curl_close($ch);
		return $ret;
	}

    protected function getSubstr($str, $leftStr, $rightStr)
    {
        $left = strpos($str, $leftStr);
        //echo '左边:'.$left;
        $right = strpos($str, $rightStr, $left);
        //echo '<br>右边:'.$right;
        if ($left < 0 or $right < $left) return '';
        return substr($str, $left + strlen($leftStr), $right - $left - strlen($leftStr));
    }
    
   public static function qqEncodeCrypt($data){
        $privatekey = '9973e345';
        if(strlen($data) %16){
            $data = str_pad($data,strlen($data) + 16 - strlen($data) % 16, '\0');
        }
        $encodeCrypt = openssl_encrypt($data,'DES-ECB',$privatekey,OPENSSL_NO_PADDING);
        return strtoupper(bin2hex($encodeCrypt));
    }
   public static function qqDecodeCrypt($string){
        $privatekey = '9973e345';
        $etadata = hex2bin(trim($string));
        $decodeCrypt = openssl_decrypt($etadata,'DES-ECB',$privatekey,OPENSSL_NO_PADDING);
        return trim($decodeCrypt);
    }
        
    public static function encodeCrypt($data)
    {
        $privateKey = "9973e345";
        if (strlen($data) % 8)
        {
            $data = str_pad($data, strlen($data) + 8 - strlen($data) % 8, "\0");
        }
        $encrypted = openssl_encrypt($data, "DES-ECB", $privateKey, OPENSSL_NO_PADDING);
        return bin2hex($encrypted);
    }
    
     public static function decodeCrypt($string)
    {
        $privateKey = "9973e345";
        $encryptedData = hex2bin($string);
        $decrypted = openssl_decrypt($encryptedData, "DES-ECB", $privateKey, OPENSSL_NO_PADDING);
        return $decrypted;
    }

     //aes解码
    public  static function aesDecrypt($encryptedData) {
        $privateKey = 'a3051c6e815a170af58af3ecb7a839e1';
        $data = base64_decode($encryptedData);

        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        $decrypted = openssl_decrypt($encrypted, 'aes-256-cbc', $privateKey, OPENSSL_RAW_DATA, $iv);
        return json_decode($decrypted, true);
    }
    
    //获取商户号
    public function WXJSLogin_mch($cloud_id,$guid, $appid = "wxc999ec07220acf96")//商业版//获取CODE
    {
                  //查询是否存在该云端地域
        $cloudArr = Db::name('ypay_cloud')->where('id',$cloud_id)->find();
    	$WXJSlogin = $cloudArr['address'] . "/api/Common/WXJSLogin";
    	$Header =  array('Content-Type: application/json-patch+json');
    	$post = "{\"AppId\":\"{$appid}\",\"Guid\":\"{$guid}\"}";
    	$resd = json_decode($this->http_post_yun($WXJSlogin, $Header ,$post),true);
    	$code=$resd['data']['code'];
        $res=get_curl("https://payapp.weixin.qq.com/mdmgr/weapp/login?js_code=".$code);
        $res = json_decode($res,true);
        $mch=$res['msg'];
        if($res['data']['mch'][0]['mch_id']){
            $result=array("code"=>1,"msg"=>"获取成功","mch"=>$res['data']['mch'][0]['mch_id']);
        }else{
            $result=array("code"=>-1,"msg"=>"获取失败");
        }
    	return $result;
    }
    
    //获取微信商户号和SID
    public function WXJSLogin_shop($cloud_id,$guid,$mch,$appid = "wx264e9b6d4d484f51")//获取shop/sid
    {
               //查询是否存在该云端地域
        $cloudArr = Db::name('ypay_cloud')->where('id',$cloud_id)->find();
    	$WXJSlogin = $cloudArr['address'] . "/api/Common/WXJSLogin";
    	$Header =  array('Content-Type: application/json-patch+json');
    	$post = "{\"AppId\":\"{$appid}\",\"Guid\":\"{$guid}\"}";
    	$res = json_decode($this->http_post_yun($WXJSlogin, $Header ,$post),true);
    	$code=$res['data']['code'];
        $url="https://payapp.weixin.qq.com/receiptmdmgr/account/get?miniprogram_version=3.8.5&account_id=".$mch."&account_type=1&js_code=".$code;
        $sid=json_decode(get_curl($url),true);
        if($sid['data']['auth_shop_list'][0]['shop_id']){
            $result=array("code"=>1,"msg"=>"success","shop_id"=>$sid['data']['auth_shop_list'][0]['shop_id'] , "sid"=>$sid['sid'],"account_id" => $sid['data']['account_info']['account_id']);
        }else{
            $result=array("code"=>-1,"msg"=>"err");
        }
    	return $result;
    }
    
    
    
    public function WXJSLogin_receipt($sid,$fee,$remark,$shop_id,$account_id)//获取receipt
    {
            $fee=$fee*100;
            $url="https://payapp.weixin.qq.com/receiptmdmgr/receipt/create?miniprogram_version=3.8.5&fee=".$fee."&remark=".$remark."&remark_pic_urls=&option_list=%5B%5D&shop_id=".$shop_id."&account_id=".$account_id."&account_type=1&sid=".$sid;
            $res=json_decode(get_curl($url),true);
            if($res['data']['receipt']['receipt_id']){
                $result=array("code"=>1,"msg"=>"success","receipt_id"=>$res['data']['receipt']['receipt_id']);
            }else{
                $result=array("code"=>-1,"msg"=>"err");
            }
        return $result;
    }
    
    //生成收款单二维码
    public function WXJSLogin_qrcode($sid,$receipt_id,$account_id)//获取qrcode
    {
        $url="https://payapp.weixin.qq.com/receiptmdmgr/receipt/getwxacode?miniprogram_version=3.8.5&wxacode_path_type=1&receipt_id=".$receipt_id."&account_id=".$account_id."&account_type=1&sid=".$sid;
        $res=json_decode(get_curl($url),true);
        if($res['data']['qrcode']){
            $result=array("code"=>1,"msg"=>"success","qrcode"=>$res['data']['qrcode']);
        }else{
            $result=array("code"=>-1,"msg"=>"err");
        }
        return $result;
    }
    
    //获取收款单账单
    public function getSkdBill($sid,$account_id){
         $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => "https://payapp.wechatpay.cn/receiptmdmgr/receipt/list?account_type=1&account_id=".$account_id."&sid=".$sid,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => "{\r\n\"end_time\" : 0,\r\n\"start_time\" : 0,\r\n\"miniprogram_version\" : \"3.15.3\",\r\n\"shop_id_list\" : [],\r\n\"sid\" : \"".$sid."\",\r\n\"state\" : [],\r\n\"page_size\" : 10}\r\n",
            CURLOPT_HTTPHEADER => [
              "content-type: application/json"
            ],
        ]);
            
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        return $response;
        
    }
    
    public function Order($api,$guid)//
    {
       
        $WXSyncMsg=$api."/api/Message/WXSyncMsg";
        $Header =  array('Content-Type: application/json-patch+json');
        $post="{\"Guid\":\"$guid\"}";
        $results=$this->http_post_yun($WXSyncMsg,$Header,$post);
        $result=stripslashes_deep($results);
        return $result;
    }
    
    public static function http_post_yun($url, $Header, $data)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $Header);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POST, 1); // 发送一个常规的Post请求
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data); // Post提交的数据包
        curl_setopt($ch, CURLOPT_TIMEOUT, 30); // 设置超时限制防止死循环
        curl_setopt($ch, CURLOPT_HEADER, 0); // 显示返回的Header区域内容
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // 获取的信息以文件流的形式返回
        $sResult = curl_exec($ch);
        if($sError=curl_error($ch)){
            die($sError);
        }
        curl_close($ch);
        return $sResult;
    }
	
	public static function Get_curl_header($url, $post, $ua)
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		$httpheader[] = "Accept: */*";
		$httpheader[] = "Accept-Encoding: gzip,deflate,sdch";
		$httpheader[] = "Accept-Language: zh-CN,zh;q=0.8";
		$httpheader[] = "Connection: keep-alive";
		curl_setopt($ch, CURLOPT_HTTPHEADER, $httpheader);
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 10);
		curl_setopt($ch, CURLOPT_ENCODING, "gzip");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		if ($post) {
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
		}
		if ($ua) {
			curl_setopt($ch, CURLOPT_USERAGENT, $ua);
		} else {
			curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/70.0.3538.25 Safari/537.36 Core/1.70.3741.400 QQBrowser/10.5.3863.400");
		}
		$ret = curl_exec($ch);
		$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
		$header = substr($ret, 0, $headerSize);
		$body = substr($ret, $headerSize);
		$ret = [];
		$ret["header"] = $header;
		$ret["body"] = $body;
		curl_close($ch);
		return $ret;
	}
	
public static function xx_get_curl($url, $post=0, $referer=0, $cookie=0, $header=0, $ua=0, $nobaody=0, $addheader=0)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    $httpheader[] = "Accept: */*";
    $httpheader[] = "Accept-Encoding: gzip,deflate,sdch";
    $httpheader[] = "Accept-Language: zh-CN,zh;q=0.8";
    $httpheader[] = "Connection: close";
    if($addheader){
        $httpheader = array_merge($httpheader, $addheader);
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $httpheader);
    if ($post) {
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    }
    if ($header) {
        curl_setopt($ch, CURLOPT_HEADER, true);
    }
    if ($cookie) {
        curl_setopt($ch, CURLOPT_COOKIE, $cookie);
    }
    if($referer){
        curl_setopt($ch, CURLOPT_REFERER, $referer);
    }
    if ($ua) {
        curl_setopt($ch, CURLOPT_USERAGENT, $ua);
    } else {
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Linux; U; Android 4.0.4; es-mx; HTC_One_X Build/IMM76D) AppleWebKit/534.30 (KHTML, like Gecko) Version/4.0");
    }
    if ($nobaody) {
        curl_setopt($ch, CURLOPT_NOBODY, 1);
    }
    curl_setopt($ch, CURLOPT_ENCODING, "gzip");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $ret = curl_exec($ch);
    curl_close($ch);
    return $ret;
}
public static function xx_authcode($string, $operation = 'DECODE', $key = '', $expiry = 0) { 
    $ckey_length = 4;
    $key = md5($key);
    $keya = md5(substr($key, 0, 16));
    $keyb = md5(substr($key, 16, 16));
    $keyc = $ckey_length ? ($operation == 'DECODE' ? substr($string, 0, $ckey_length): substr(md5(microtime()), -$ckey_length)) : '';
    $cryptkey = $keya.md5($keya.$keyc);
    $key_length = strlen($cryptkey);
    $string = $operation == 'DECODE' ? base64_decode(substr($string, $ckey_length)) : sprintf('%010d', $expiry ? $expiry + time() : 0).substr(md5($string.$keyb), 0, 16).$string;
    $string_length = strlen($string);
    $result = '';
    $box = range(0, 255);
    $rndkey = array();
    for($i = 0; $i <= 255; $i++) {
        $rndkey[$i] = ord($cryptkey[$i % $key_length]);
    }
    for($j = $i = 0; $i < 256; $i++) {
        $j = ($j + $box[$i] + $rndkey[$i]) % 256;
        $tmp = $box[$i];
        $box[$i] = $box[$j];
        $box[$j] = $tmp;
    }
    for($a = $j = $i = 0; $i < $string_length; $i++) {
        $a = ($a + 1) % 256;
        $j = ($j + $box[$a]) % 256;
        $tmp = $box[$a];
        $box[$a] = $box[$j];
        $box[$j] = $tmp;
        $result .= chr(ord($string[$i]) ^ ($box[($box[$a] + $box[$j]) % 256]));
    }
    if($operation == 'DECODE') {
        if(((int)substr($result, 0, 10) == 0 || (int)substr($result, 0, 10) - time() > 0) && substr($result, 10, 16) == substr(md5(substr($result, 26).$keyb), 0, 16)) {
            return substr($result, 26);
        } else {
            return '';
        }
    } else {
        return $keyc.str_replace('=', '', base64_encode($result));
    }
}

static function strFilter($str){
        $str = str_replace('￥', '', $str);
        $str = str_replace('\'', '', $str);
        $str = str_replace('=', '%3D', $str);
        return trim($str);
    }
    static function get_qq_curl($url, $post=0, $referer=0, $cookie=0, $header=0, $ua=0, $nobaody=0, $addheader=0)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $httpheader[] = "Accept: application/json";
        $httpheader[] = "Accept-Encoding: gzip,deflate,sdch";
        $httpheader[] = "Accept-Language: zh-CN,zh;q=0.8";
        $httpheader[] = "Connection: close";
        if($addheader){
            $httpheader = array_merge($httpheader, $addheader);
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $httpheader);
        if ($post) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        }
        if ($header) {
            curl_setopt($ch, CURLOPT_HEADER, true);
        }
        if ($cookie) {
            curl_setopt($ch, CURLOPT_COOKIE, $cookie);
        }
        if($referer){
            if($referer==1){
                curl_setopt($ch, CURLOPT_REFERER, 'https://h5.qzone.qq.com/mqzone/index');
            }else{
                curl_setopt($ch, CURLOPT_REFERER, $referer);
            }
        }
        if ($ua) {
            curl_setopt($ch, CURLOPT_USERAGENT, $ua);
        }
        else {
            curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Linux; U; Android 4.0.4; es-mx; HTC_One_X Build/IMM76D) AppleWebKit/534.30 (KHTML, like Gecko) Version/4.0");
        }
        if ($nobaody) {
            curl_setopt($ch, CURLOPT_NOBODY, 1);
        }
        curl_setopt($ch, CURLOPT_ENCODING, "gzip");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $ret = curl_exec($ch);
        curl_close($ch);
        return $ret;
    }
    
}
