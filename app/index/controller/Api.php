<?php


namespace app\index\controller;

use think\facade\Db;
use app\common\model\YpayUserbasic as basic;
use app\common\service\APIInterface as APIInterface;
use app\common\service\Jialanshen;
use think\facade\Request;

class Api extends \app\BaseController
{

    //API接口下单
    public static function payment()
    {
        return json(APIInterface::payment(Request::param('', '', 'strip_tags'), 'scan'));
    }

    //MAPI接口下单
    public static function mapi()
    {
        return json(APIInterface::payment(Request::param('', '', 'strip_tags'), 'mapi'));
    }

    /**
     ** 获取软件配置信息 [PC][APP]
     **/

    //获取软件基本信息
    public static function getSoftwareConfig()
    {
        return json(APIInterface::getSoftwareConfig());
    }


    //登录
    public static function login()
    {
        //获取页面提交的数据传值 账户:username 密码:password 邮箱:email 手机号:mobile 验证码:ordinary_captcha 短信验证码:captcha
        return json(APIInterface::login(Request::param('', '', 'strip_tags')));
    }

    //获取/更新验证码
    public static function getCaptcha()
    {
        $res = APIInterface::getCaptcha();
        cache('captcha', session('captcha'));
        return $res;
    }

    //获取短信验证码
    public static function getCode()
    {
        //获取页面提交的数据传值 类型:type - login/register 手机号:mobile 邮箱:email
        return json(APIInterface::getCode(Request::param('', '', 'strip_tags')));
    }

    //注册
    public static function register()
    {
        //获取页面提交的数据传值 账户:username 密码:password 确认密码:password2 邮箱:email 手机号:mobile 验证码:ordinary_captcha 短信验证码:captcha 类型:type - reg bind
        return  json(APIInterface::register(Request::param('', '', 'strip_tags')));
    }

    //验证授权
    public static function getAuth()
    {
        return json(APIInterface::getAuth());
    }

    public static function getAESDecrypt()
    {
        //获取页面提交的数据传值 
        return json(APIInterface::getAuth());
    }

    //获取更新
    public static function getUpdate()
    {
        //获取页面提交的数据传值 版本号:version
        return json(APIInterface::getUpdate(Request::param('', '', 'strip_tags')));
    }

    //获取控制台展示数据
    public static function getHome()
    {
        //获取页面提交的数据传值 Token:token 用户ID:user_id
        return json(APIInterface::getHome(Request::param('', '', 'strip_tags')));
    }

    //获取会员套餐
    public static function getVip()
    {
    }

    //获取默认通道列表
    public static function getChannel()
    {
        return json(APIInterface::getChannel());
    }

    //通过类型筛选获取通道
    public static function filter_channel()
    {
        //获取页面提交的数据传值 类型:type
        return json(APIInterface::filter_channel(Request::param('', '', 'strip_tags')));
    }

    //通过类型筛选获取云端
    public static function filter_cloud()
    {
        //获取页面提交的数据传值 类别:type-1:微信云端 2:QQ云端  云端类型cloud_type-微信云端:1 MacV3 2 MacV2 3 Ipad QQ云端:1
        return json(APIInterface::filter_cloud(Request::param('', '', 'strip_tags')));
    }

    //获取通道账户列表
    public static function getAccount()
    {
        //获取页面提交的数据传值 Token:token 用户ID:user_id
        return json(APIInterface::getAccount(Request::param('', '', 'strip_tags')));
    }

    //新增通道账户
    public static function addAccount()
    {
        //获取页面提交的数据传值 Token:token 用户ID:user_id 
        return json(APIInterface::addAccount(Request::param('', '', 'strip_tags')));
    }

    //修改通道账户
    public static function editAccount()
    {
        //获取页面提交的数据传值 Token:token 用户ID:user_id 通道ID:account_id
        return json(APIInterface::editAccount(Request::param('', '', 'strip_tags')));
    }

    //删除通道账户
    public static function delAccount()
    {
        //获取页面提交的数据传值 Token:token 用户ID:user_id 通道ID:account_id
        return json(APIInterface::delAccount(Request::param('', '', 'strip_tags')));
    }

    //获取二维码
    public static function getQrCode()
    {
        //获取页面提交的数据传值 Token:token 用户ID:user_id 通道ID:account_id
        return json(APIInterface::getQrCode(Request::param('', '', 'strip_tags')));
    }

    //获取扫码状态
    public static function getScanningStatus()
    {
        //获取页面提交的数据传值 Token:token 用户ID:user_id 通道ID:account_id
        return json(APIInterface::getScanningStatus(Request::param('', '', 'strip_tags')));
    }

    //获取订单日志
    public static function getOrderLog()
    {
        //获取页面提交的数据传值 Token:token 用户ID:user_id
        return json(APIInterface::getOrderLog(Request::param('', '', 'strip_tags')));
    }

    //更改通道状态
    public static function getUpdateStatus()
    {
        //获取页面提交的数据传值 Token:token 用户ID:user_id 通道ID:account_id 通道类型:type 通道状态:status 支付Pid:pid
        return json(APIInterface::getUpdateStatus(Request::param('', '', 'strip_tags')));
    }

    //获取通道订单
    public static function getCheckOrder()
    {
        //获取页面提交的数据传值 Token:token 用户ID:user_id 通道ID:account_id 通道类型:type
        return json(APIInterface::getCheckOrder(Request::param('', '', 'strip_tags')));
    }

    //执行订单回调
    public static function getNotify()
    {
        //获取页面提交的数据传值 Token:token 用户ID:user_id 通道ID:account_id 通道类型:type 订单金额:money 订单号:orderNo
        return json(APIInterface::getNotify(Request::param('', '', 'strip_tags')));
    }

    //手动补单
    public static function getRebackOrder()
    {
        //获取页面提交的数据传值 Token:token 用户ID:user_id 通道ID:account_id 订单ID:order_id
        return json(APIInterface::getRebackOrder(Request::param('', '', 'strip_tags')));
    }

    //订单查询 $trade_no:订单号 $type:订单号类型
    public static function findorder()
    {
        //获取提交参数
        $data = Request::param('', '', 'strip_tags');
        $order_no = $data['order_no'];
        $type = $data['type'];
        if (empty($order_no)) {
            return json(['code' => 201, 'msg' => '请输入订单号!']);
        }
        if (empty($type)) {
            return json(['code' => 201, 'msg' => '请输入订单号类型!']);
        }

        //判断查询订单类型
        if ($type == 1) {
            $order = Db::name('ypay_order')->where('trade_no', $order_no)->find();
        } else {
            $order = Db::name('ypay_order')->where('out_trade_no', $order_no)->find();
        }

        //判断是否有此订单信息
        if (empty($order)) {
            return json(['code' => 201, 'msg' => '此订单号不是有效订单号',]);
        }
        //整理返回数据集
        $data =
            [
                'id' => $order['user_id'], //商户ID
                'type' => $order['type'], //支付类型
                'trade_no' => $order['trade_no'], //商户订单号
                'out_trade_no' => $order['out_trade_no'], //本地订单号
                'name' => $order['name'], //商品名称
                'money' => $order['money'], //商品金额
                'status' => $order['status'], //商品付款状态
                'notify_url' => $order['notify_url'], //异步回调地址
                'return_url' => $order['return_url'], //同步回调地址
            ];
        return json(['code' => 200, 'msg' => '获取成功!', 'data' => $data]);
    }



    /**
     ** 自挂软件方法 [PC][APP]
     **/
    //验证提交参数 传递参数为: id(商户ID) key(通讯密钥)
    public static function verify($temp = '')
    {

        //判断参数是否为空
        if (empty($temp)) {
            $data = Request::param('', '', 'strip_tags');
        } else {
            $data = $temp;
        }

        if (empty($data['id'])  || empty($data['key'])) {
            if (!empty($temp)) {
                return ['code' => 201, 'msg' => '商户ID和通讯密钥不可为空'];
            } else {
                return json(['code' => 201, 'msg' => '商户ID和通讯密钥不可为空']);
            }
        }

        //查询配对信息
        $user = basic::where('user_id', $data['id'])->where('appkey', $data['key'])->find();

        if (empty($user)) {
            if (!empty($temp)) {
                return ['code' => 201, 'msg' => '商户不存在或密钥错误!'];
            } else {
                return json(['code' => 201, 'msg' => '商户不存在或密钥错误!']);
            }
        }


        if (!empty($temp)) {
            return ['code' => 200, 'msg' => '验证成功!'];
        } else {
            return json(['code' => 200, 'msg' => '验证成功!']);
        }
    }

    //心跳(更新通道状态) 传递参数为:id(商户ID) key(通讯密钥) type(通道类型) channel_id(通道ID) status(登录状态) pid(支付宝PID)
    public static function heartbeat()
    {
        //获取提交参数
        $data = Request::param('', '', 'strip_tags');

        //判断通道ID是否为空
        if (empty($data['channel_id'])) {
            return json(['code' => 201, 'msg' => '通道ID不可为空']);
        }

        //构建临时验证参数
        $temp =
            [
                'id' => $data['id'],
                'key' => $data['key']
            ];

        //传递参数验证
        $result = self::verify($temp);

        // 根据协议模式更新数据
        if ($data['mode'] == "agt") {
            switch ($data['type']) {
                case 'alipay':
                    Db::name('ypay_account')->where('id', $data['channel_id'])->update([
                        'status' => $data['status'],
                        'account' => $data['tempParam']
                    ]);
                    break;
                case 'qqpay':
                    Db::name('ypay_account')->where('id', $data['channel_id'])->update([
                        'status' => $data['status'],
                        'zfb_pid' => $data['tempParam']
                    ]);
                    break;
                    // 默认情况，如果需要可以添加其他支付类型的处理
                default:
                    Db::name('ypay_account')->where('id', $data['channel_id'])->update([
                        'status' => $data['status']
                    ]);
                    break;
            }
        } else {
            Db::name('ypay_account')->where('id', $data['channel_id'])->update([
                'status' => $data['status']
            ]);
        }

        return json($result);
    }

    //检查订单 传递参数为:id(商户ID) key(通讯密钥) type(通道类型) channel_id(通道ID)
    public static function checkOrder()
    {
        //获取提交参数
        $data = Request::param('', '', 'strip_tags');

        //判断通道ID是否为空
        if (empty($data['channel_id'])) {
            return json(['code' => 201, 'msg' => '通道ID不可为空']);
        }

        //构建临时验证参数
        $temp =
            [
                'id' => $data['id'],
                'key' => $data['key']
            ];

        //传递参数验证
        $result = self::verify($temp);

        //如果验证通过即修改状态
        if ($result['code'] == 200) {

            //获取账户信息
            $account = Db::name('ypay_account')->where('id', $data['channel_id'])->find();

            //判断通道是否存在
            if (empty($account)) {
                return json(['code' => 201, 'msg' => '通道不存在']);
            }

            //清空数组
            $where = array();
            //构建查询参数
            $where =
                [
                    ['account_id', '=', $account['id']],
                    ['status', '=', 0],
                    ['out_time', '>', time()]
                ];

            //查询并返回订单数量
            $order = Db::name('ypay_order')->where($where)->order('id desc')->select();

            //声明数组
            $orderArray = [];

            //重新规划订单数组
            foreach ($order as $key => $value) {
                //组装订单信息
                $orderArray[$key] =
                    [
                        'id' => $value['id'], //订单ID
                        'name' => $value['name'], //商品名称
                        'type' => $value['type'], //支付类型
                        'money' => $value['money'], //金额
                        'truemoney' => $value['truemoney'], //实付金额
                        'account_id' => $value['account_id'], //通道ID
                        'trade_no' => $value['trade_no'], //本地订单号
                        'out_trade_no' => $value['out_trade_no'], //商户订单号
                    ];
            }

            //判断数组是否有数据
            if (empty($orderArray)) {
                return json(['code' => 201, 'msg' => '暂未查询到此账户订单信息']);
            }

            return json(['code' => 200, 'msg' => '返回成功', 'data' => $orderArray]);
        }

        return json($result);
    }

    //自挂软件订单回调通知 传递参数为:id(商户ID) key(通讯密钥) type(通道类型) channel_id(通道ID) money(订单金额) orderNo(订单号)
    public static function PCNotify()
    {
        //获取提交参数
        $data = Request::param('', '', 'strip_tags');

        //判断通道ID是否为空
        if (empty($data['channel_id'])) {
            return json(['code' => 201, 'msg' => '通道ID不可为空']);
        }

        //构建临时验证参数
        $temp =
            [
                'id' => $data['id'],
                'key' => $data['key']
            ];

        //传递参数验证
        $result = self::verify($temp);

        //如果验证通过即修改状态
        if ($result['code'] == 200) {

            //获取账户信息
            $account = Db::name('ypay_account')->where('id', $data['channel_id'])->find();

            //判断通道是否存在
            if (empty($account)) {
                return json(['code' => 201, 'msg' => '通道不存在']);
            }
            //清空数组
            $where = array();

            //如果类型为空就默认走余额查询
            if (isset($data['type'])) {
                //根据类型筛选
                switch ($data['type']) {
                    case 'alipay':
                        //获取用户配置信息
                        $basic = basic::where('user_id', $account['user_id'])->find();
                        //构建基础查询
                        $where =
                            [
                                ['account_id', '=', $account['id']],
                                ['status', '=', 0],
                                ['out_time', '>', time()],
                                ['type', '=', 'alipay'],
                                ['truemoney', '=', $data['money']]
                            ];
                        if ($basic['channelMode'] == 1 && !empty($data['orderNo'])) {
                            // 通道开启了严格模式且携带订单号，则增加订单号筛选避免串单
                            $where[] = ['out_trade_no', '=', $data['orderNo']];
                        }
                        break;
                    case 'wxpay':
                        //构建查询参数
                        $where =
                            [
                                ['account_id', '=', $account['id']],
                                ['status', '=', 0],
                                ['out_time', '>', time()],
                                ['type', '=', 'wxpay'],
                                ['truemoney', '=', $data['money']]
                            ];
                        break;
                    case 'qqpay':
                        //构建查询参数
                        $where =
                            [
                                ['account_id', '=', $account['id']],
                                ['status', '=', 0],
                                ['out_time', '>', time()],
                                ['type', '=', 'qqpay'],
                                ['truemoney', '=', $data['money']]
                            ];
                        break;
                }
            } else {
                //构建查询参数
                $where =
                    [
                        ['account_id', '=', $account['id']],
                        ['status', '=', 0],
                        ['out_time', '>', time()],
                        ['truemoney', '=', $data['money']]
                    ];
            }

            //查询订单信息
            $order = Db::name('ypay_order')->where($where)->order('id desc')->find();

            //订单信息存在则执行回调操作
            if (!empty($order)) {
                $url = Jialanshen::creat_callback($order);
                get_curl($url['notify']);
                return json(['code' => 200, 'msg' => '回调成功!']);
            } else {
                return json(['code' => 201, 'msg' => '订单超时或不存在']);
            }
        }

        return json($result);
    }

    //传入qq收款二维码 传递参数为:id(商户ID) key(通讯密钥) orderNo(订单号) qrcode(收款二维码)
    public static function QQCreateQrcode()
    {
        //获取提交参数
        $data = Request::param('', '', 'strip_tags');

        //构建临时验证参数
        $temp =
            [
                'id' => $data['id'],
                'key' => $data['key']
            ];

        //传递参数验证
        $result = self::verify($temp);

        //如果验证通过即修改状态
        if ($result['code'] == 200) {
            //更新状态
            $h5url = base64_encode('https://qun.qq.com/qrcode/index?data=' . urlencode($data['qrcode']));
            $h5url = 'mqqapi://forward/url?version=1&src_type=web&url_prefix=' . $h5url;

            //构建查询参数
            $where =
                [
                    ['user_id', '=', $data['id']],
                    ['out_trade_no', '=', $data['orderNo']],
                    ['out_time', '>', time()]
                ];

            Db::name('ypay_order')->where($where)->update(['qrcode' => $data['qrcode'], 'h5_qrurl' => $h5url]);
            return json(['code' => 200, 'msg' => '传入成功!']);
        }

        return json($result);
    }

    //获取免费版软件更新
    public static function getFreeUpdate()
    {
        //获取页面提交的数据传值 版本号:version
        return json(APIInterface::getFreeUpdate(Request::param('', '', 'strip_tags')));
    }

    //店员密钥验证
    public static function clerkVerify($key = null,$type = null)
    {
        //获取提交参数
        $data = Request::param('', '', 'strip_tags');
        $clerk_key = getConfig()['clerk_key'];

        if (empty($data['key'])) {
            $temp = ['code' => 201, 'msg' => '请填写店员密钥!'];
        }
        if (empty($clerk_key)) {
            $temp = ['code' => 201, 'msg' => '后台未设置店员密钥!'];
        }
        if ($clerk_key != $data['key']) {
            $temp = ['code' => 201, 'msg' => '请检查密钥信息是否正确!'];
        } else {
            $temp = ['code' => 200, 'msg' => '验证成功!'];
        }

        if($type == 'notify'){
            return $temp;
        }else{
            return json($temp);
        }
    }


    /**
     * 店员通道回调
     */
    public static function clerkNotify()
    {
        //获取提交参数
        $data = Request::param('', '', 'strip_tags');

        // 验证参数
        if (empty($data['wxname'])) {
            return json(['code' => 201, 'msg' => '收款账户昵称不可为空!']);
        }
        if (empty($data['money'])) {
            return json(['code' => 201, 'msg' => '金额不可为空!']);
        }

        // 验证店员密钥
        $result = self::clerkVerify($data['key'], 'notify');

        if ($result['code'] == 200) {

            //获取账户信息
            $account = Db::name('ypay_account')->where('wxname', $data['wxname'])->find();

            // 判断通道是否存在
            if (empty($account)) {
                return json(['code' => 201, 'msg' => '通道不存在!']);
            }

            // 构建查询条件
            $where = [
                ['channel_id', '=', $account['id']],
                ['status', '=', 0],
                ['out_time', '>', time()],
                ['truemoney', '=',$data['money']]
            ];


            
            //查询订单信息
            $order = Db::name('ypay_order')->where($where)->order('id desc')->find();

            //订单信息存在则执行回调操作
            if (!empty($order)) {
                $url = Jialanshen::creat_callback($order);
                get_curl($url['notify']);
                return json(['code' => 200, 'msg' => '回调成功!']);
            } else {
                return json(['code' => 201, 'msg' => '订单超时或不存在']);
            }
        } else {
            return json(['code' => 200, 'msg' => $result['msg']]);
        }
    }




    /**
     ** 其他/第三方软件适配接口
     **/

    public function x_appNotify()
    {
        //获取提交参数
        $data = Request::param('', '', 'strip_tags');


        // 从 URL 中提取 ID
        $platformUrl = strtok($_SERVER['REQUEST_URI'], '?');
        $id = substr($platformUrl, strpos($platformUrl, '/api/report/') + strlen('/api/report/'));

        if (empty($data['token'])) {
            return $this->buildMonitorResponse(201, 'token 参数缺失');
        }
        if (empty($data['content'])) {
            return $this->buildMonitorResponse(201, 'content 参数缺失');
        }
        $channelId = $data['channel_id'] ?? null;


        //构建临时验证参数
        $temp =
            [
                'id' => $id,
                'key' => $data['token']
            ];


        // 将 content 解析为 JSON 对象
        $contentArr = json_decode($data["content"], true);
        if (!is_array($contentArr)) {
            return $this->buildMonitorResponse(201, 'content 解析失败');
        }

        //传递参数验证
        $result = self::verify($temp);

        //清空数组
        $where = array();


        //如果验证通过即修改状态
        if ($result['code'] == 200) {
            if (!isset($contentArr['msg'], $contentArr['package_name'])) {
                return $this->buildMonitorResponse(201, '消息内容缺失');
            }
            $msg = $contentArr['msg'];
            $package = $contentArr['package_name'];
            // channel_id 存在时校验通道归属
            if (!empty($channelId)) {
                $account = Db::name('ypay_account')
                    ->where('id', $channelId)
                    ->where('user_id', $id)
                    ->find();
                if (empty($account)) {
                    return $this->buildMonitorResponse(201, 'channel_id invalid');
                }
            }

            $money = null;
            $where = [
                ['status', '=', 0],
                ['out_time', '>', time()],
            ];
            if (!empty($channelId)) {
                $where[] = ['account_id', '=', $channelId];
            } else {
                // 未上报 channel_id 时，查询该商户下的全部通道订单
                $where[] = ['user_id', '=', $id];
            }
            switch ($package) {
                case 'com.eg.android.AlipayGphone':
                    $aliPatterns = [
                        '/成功收款\s*(\d+(?:\.\d+)?)\s*元/u',
                        '/付款金额.*?(?:¥|￥)\s*(\d+(?:\.\d+)?)/u',
                        '/收款金额.*?(?:¥|￥)\s*(\d+(?:\.\d+)?)/u',
                        '/(?:到账|收款)(?:通知)?[^¥￥\d]*?(?:¥|￥)?(\d+(?:\.\d+)?)\s*元/u',
                        '/个人收款码到账.*?(?:¥|￥)?(\d+(?:\.\d+)?)\s*元?/u',
                        '/款\s*(\d+(?:\.\d+)?)\s*元/u',
                        '/(?:¥|￥)\s*(\d+(?:\.\d+)?)/u',
                    ];
                    $money = $this->extractAmountFromMessage($msg, $aliPatterns);
                    if ($money === null) {
                        return $this->buildMonitorResponse(201, '未找到金额');
                    }
                    $where[] = ['truemoney', '=', sprintf("%.2f", $money)];
                    break;
                case 'com.tencent.mm':
                    $wxPatterns = [
                        '/成功收款\s*(\d+(?:\.\d+)?)\s*元/u',
                        '/二维码赞赏到账.*?(?:¥|￥)?(\d+(?:\.\d+)?)\s*元/u',
                        '/收款金额.*?(?:¥|￥)\s*(\d+(?:\.\d+)?)/u',
                        '/(?:收款|到账)(?:通知)?[^¥￥\d]*?(?:¥|￥)?(\d+(?:\.\d+)?)\s*元/u',
                        '/个人收款码到账.*?(?:¥|￥)?(\d+(?:\.\d+)?)\s*元?/u',
                        '/款\s*(\d+(?:\.\d+)?)\s*元/u',
                        '/二维码赞赏到账.*?到账金额.*?(?:¥|￥)?(\d+(?:\.\d+)?)\s*元/u',
                        '/(?:¥|￥)\s*(\d+(?:\.\d+)?)/u',
                    ];
                    $money = $this->extractAmountFromMessage($msg, $wxPatterns);
                    if ($money === null) {
                        return $this->buildMonitorResponse(201, '未找到金额');
                    }
                    $where[] = ['truemoney', '=', sprintf("%.2f", $money)];
                    break;
                default:
                    return $this->buildMonitorResponse(400, '不支持的支付包类型');
            }

            $order = Db::name('ypay_order')->where($where)->order('id desc')->find();
            if (empty($order)) {
                return $this->buildMonitorResponse(201, '订单超时或不存在');
            }

            $url = Jialanshen::creat_callback($order);
            get_curl($url['notify']);

            return $this->buildMonitorResponse(200, '处理成功', [
                'order_id' => $order['out_trade_no'],
                'subject' => $order['name']
            ]);
        }

        return $this->buildMonitorResponse($result['code'], $result['msg']);
    }

    /**
     * 从推送内容匹配金额
     */
    protected function extractAmountFromMessage(string $message, array $patterns): ?float
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message, $match)) {
                foreach ($match as $value) {
                    if (is_string($value)) {
                        $value = str_replace([',', '，'], '', trim($value));
                        if ($value !== '' && is_numeric($value)) {
                            return (float)$value;
                        }
                    }
                }
            }
        }
        return null;
    }

    /**
     * 构建监控软件回执
     */
    protected function buildMonitorResponse(int $code, string $message, array $data = [])
    {
        return json([
            'code' => $code,
            'message' => $message,
            'data' => $data,
            'redirect' => ''
        ]);
    }
}
