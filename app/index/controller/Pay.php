<?php


namespace app\index\controller;

use think\facade\Request;
use think\facade\View;
use app\common\service\YiPay as epay;
use app\common\service\Jialanshen;
use app\common\model\YpayRisk as Risk;
use app\common\model\YpayUser;
use app\common\model\YpayVip;
use app\common\model\YpayUserbasic;
use app\common\model\YpayRecharge as recharge;
use app\common\model\YpayPaylist;
use app\common\model\YpayAccount;
use app\common\model\YpayOrder;
use app\common\model\YpayDomain as domain;
use app\common\service\Paylist as payList;
use app\common\service\YpayRecharge;
use app\common\service\YpayUser as S;
use app\index\job\Order;

class Pay extends \app\BaseController
{
    /**
     * 发起支付
     */
    public function submit()
    {
        $data = Request::param('', '', 'strip_tags');

        if (empty($data['notify_url'])) {
            View::assign('error_tips', "异步通知不可为空");
            View::assign('error_url', '/');
            return $this->fetch('error/errorPage');
        }
        if (empty($data['return_url'])) {
            View::assign('error_tips', "同步通知不可为空");
            View::assign('error_url', '/');
            return $this->fetch('error/errorPage');
        }

        //如果异步回调地址为空 则进入或者同步回调地址
        if (empty($data['notify_url'])) {
            preg_match('/(https?:\/\/)?((?:[a-zA-Z0-9-]+\.)+[a-zA-Z]{2,6}|[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+):?(\d+)?(\/\S*)?/', $data['return_url'], $matches);
            $protocol = $matches[1] ? $matches[1] : 'http://'; // 添加默认值 http://
            $host = $protocol . $matches[2];
        } else if (empty($data['return_url']) && empty($data['notify_url'])) {
            $host = '/';
        } else {
            preg_match('/(https?:\/\/)?((?:[a-zA-Z0-9-]+\.)+[a-zA-Z]{2,6}|[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+):?(\d+)?(\/\S*)?/', $data['notify_url'], $matches);
            $protocol = $matches[1] ? $matches[1] : 'http://'; // 添加默认值 http://
            $host = $protocol . $matches[2];
        }

        if (empty($data['pid'])) {
            View::assign('error_tips', "PID不可为空");
            View::assign('error_url', $host);
            return $this->fetch('error/errorPage');
        }

        if (empty($data['out_trade_no'])) {
            View::assign('error_tips', "订单号不可为空");
            View::assign('error_url', $host);
            return $this->fetch('error/errorPage');
        }
        if (empty($data['type'])) {
            View::assign('error_tips', "支付类型不可为空");
            View::assign('error_url', $host);
            return $this->fetch('error/errorPage');
        }
        if (empty($data['name'])) {
            View::assign('error_tips', "商品名称不可为空");
            View::assign('error_url', $host);
            return $this->fetch('error/errorPage');
        }
        if (empty($data['money'])) {
            View::assign('error_tips', "金额不可为空");
            View::assign('error_url', $host);
            return $this->fetch('error/errorPage');
        }
        //判断是否开启域名审核
        if (getConfig()['is_domain'] == 1) {
            $arr = parse_url($data['notify_url']);
            $host_check = $arr['host'];
            if (substr_count($host_check, '.') > 1) {
                $host_check = substr($host_check, strpos($host_check, '.') + 1);
            }
            $domain = domain::whereRaw("user_id=:user_id AND (siteurl=:siteurl OR siteurl=:domain) AND status=1", ['user_id' => $data['pid'], 'siteurl' => $arr['host'], 'domain' => '*.' . $host_check])->find();
            if (!$domain) {
                View::assign('error_tips', "该域名不可发起支付，原因：域名没过白，请前往平台授权域名");
                View::assign('error_url', $host);
                return $this->fetch('error/errorPage');
            }
        }


        $user = YpayUser::where('id', $data['pid'])->find();
        if (empty($user)) {
            View::assign('error_tips', "商户不存在");
            View::assign('error_url', $host);
            return $this->fetch('error/errorPage');
        }
        if ($user['is_frozen'] != 0) {
            View::assign('error_tips', "该用户已被冻结，请联系站长");
            View::assign('error_url', $host);
            return $this->fetch('error/errorPage');
        }

        if (isset($user['vip_time'])) {
            $time = strtotime($user['vip_time']);
            if ($time < time()) {
                View::assign('error_tips', "套餐已过期");
                View::assign('error_url', $host);
                return $this->fetch('error/errorPage');
            }
        } else {
            View::assign('error_tips', "未开通套餐");
            View::assign('error_url', $host);
            return $this->fetch('error/errorPage');
        }

        if ($data['money'] <= 0) {
            View::assign('error_tips', "金额错误");
            View::assign('error_url', $host);
            return $this->fetch('error/errorPage');
        }
        if ($data['money'] < getConfig()['min_orderprice']) {
            View::assign('error_tips', "订单金额低于最低发起金额--" . getConfig()['min_orderprice'] . "元");
            View::assign('error_url', $host);
            return $this->fetch('error/errorPage');
        }
        if ($data['money'] > getConfig()['max_orderprice']) {
            View::assign('error_tips', "订单金额高于最高发起金额--" . getConfig()['max_orderprice'] . "元");
            View::assign('error_url', $host);
            return $this->fetch('error/errorPage');
        }
        if (strpos($data['name'], "=") !== false) {
            View::assign('error_tips', "商品名称违规");
            View::assign('error_url', $host);
            return $this->fetch('error/errorPage');
        }
        $shield_key = getConfig()['shield_key'];
        if (isset($shield_key)) {
            $weigui = explode('|', $shield_key);
            for ($index = 0; $index < count($weigui); $index++) {
                if (empty($weigui[$index])) {
                    continue;
                }
                if (strpos($data['name'], $weigui[$index]) !== false) {
                    $risk_data = [
                        'user_id' => $data['pid'],
                        'name' => $data['name'],
                        'url' => $data['return_url']
                    ];
                    try {
                        Risk::create($risk_data);
                    } catch (\Exception $e) {
                        View::assign('error_tips', getConfig()['shield_tips']);
                        View::assign('error_url', $host);
                        return $this->fetch('error/errorPage');
                    }
                    View::assign('error_tips', getConfig()['shield_tips']);
                    View::assign('error_url', $host);
                    return $this->fetch('error/errorPage');
                }
            }
        }

        //如果没开通会员套餐/或者老版本VIP体系则不进入限额
        if ($user['vip_id'] != 0) {

            $vip = YpayVip::where('id', $user['vip_id'])->find();

            //判断是否开启收款限额
            if (isset($vip)) {
                if ($vip['is_quota']) {
                    $today_money = YpayOrder::where(['status' => 1, 'user_id' => $user['id']])->whereDay('create_time')->sum('money');
                    if ($today_money > $vip['today_quota']) {
                        View::assign('error_tips', "今日收款累计超过" . $vip['today_quota'] . "的收款限额");
                        View::assign('error_url', $host);
                        return $this->fetch('error/errorPage');
                    }

                    if (!empty($vip['moon_quota'])) {
                        $moon_money = YpayOrder::where(['status' => 1, 'user_id' => $user['id']])->whereMonth('create_time')->sum('money');
                        if ($moon_money > $vip['moon_quota']) {
                            View::assign('error_tips', "本月收款累计超过" . $vip['moon_quota'] . "的收款限额");
                            View::assign('error_url', $host);
                            return $this->fetch('error/errorPage');
                        }
                    }
                }
            }
        }

        $feilv_money = $data['money'] * $user['feilv'] / 100;
        if ($user['money'] < $feilv_money) {
            View::assign('error_tips', "账户余额不足,无法发起支付");
            View::assign('error_url', $host);
            return $this->fetch('error/errorPage');
        }

        //是否支持余额为负数或者0可以发起支付
        if (getConfig()['is_pay_money'] == 0 && ($user['money'] == 0 || $user['money'] < 0) && $user['money'] < $feilv_money) {
            View::assign('error_tips', "账户余额不足,无法发起支付");
            View::assign('error_url', $host);
            return $this->fetch('error/errorPage');
        }

        $epay = new epay();

        $isSign = $epay->verifySign($data, $user['user_key']); //生成签名结果
        if (!$isSign) {
            View::assign('error_tips', "验签失败,请检查PID或者Key是否正确");
            View::assign('error_url', $host);
            return $this->fetch('error/errorPage');
        }
        $is_orderNo = YpayOrder::where('out_trade_no', $data['out_trade_no'])->find();
        if ($is_orderNo && $is_orderNo['account_id'] != 0) {
            View::assign('error_tips', "订单号重复,请重新发起");
            View::assign('error_url', $host);
            return $this->fetch('error/errorPage');
        }
        if (getConfig()['isDiy_orderNo'] == 1) {
            $trade_no = getConfig()['diy_orderNo'] . date("YmdHis") . rand(11111, 99999);
        } else {
            $trade_no = 'Y' . date("YmdHis") . rand(11111, 99999);
        }

        $QR_row =  YpayAccount::where('type', $data['type'])->where('user_id', $data['pid'])->where('status', 1)->where('is_status', 1)->orderRaw('rand()')->find(); //随机获取通道
        if (empty($QR_row)) {
            $paylist = YpayPaylist::where('user_id', $user['id'])->where('status', 1)->order('id', 'desc')->find();

            if (!empty($paylist) && $paylist['type'] == 'epay') {
                //转接订单
                $orderdata = [
                    "pid"         => $data['pid'], //商户ID
                    "type"       => $data['type'], //支付方式
                    "out_trade_no"     => $data['out_trade_no'], //商户订单号
                    "notify_url" =>  $data['notify_url'], //异步通知地址
                    "return_url" =>  $data['return_url'], //同步通知地址
                    "name" => $data['name'], //商品名称
                    "money"      => $data['money'], //订单金额
                ];
                $res = Jialanshen::epay_zj($trade_no, $orderdata, $user,$paylist);
                //转接订单创建完毕
                if ($res) //进入转接流程
                {
                    $request = \think\facade\Request::instance();
                    $notify_url = str_replace('/submit.php', '', $request->root(true)) . '/Notify/epay_notifyzj';
                    $return_url = str_replace('/submit.php', '', $request->root(true)) . '/Notify/epay_returnzj';
                    $datas = [
                        "pid"         => $paylist['pid'], //商户ID
                        "type"       => $data['type'], //支付方式
                        "out_trade_no"     => $data['out_trade_no'], //商户订单号
                        "notify_url" =>  $notify_url, //异步通知地址
                        "return_url" =>  $return_url, //同步通知地址
                        "name" => $data['name'], //商品名称
                        "money"      => $data['money'], //订单金额
                    ];
                    $epayzj = new epay($paylist['pid'], $paylist['key'], $paylist['url']);
                    $res = $epayzj->pagePay($datas);
                    echo ($res);
                    die;
                } else {
                    View::assign('error_tips', "订单创建失败请重试");
                    View::assign('error_url', $host);
                    return $this->fetch('error/errorPage');
                }
            }
 
            View::assign('error_tips', "暂无收款账号在线");
            View::assign('error_url', $host);
            return $this->fetch('error/errorPage');
        }
        $action = $QR_row['code'];
        $res = Jialanshen::$action($trade_no, $QR_row, $data, $user);
        if ($res) {
            exit("<script>window.location.href='/Pay/console?trade_no={$trade_no}';</script>");
        } else {
            View::assign('error_tips', "订单生成错误,请重新发起支付");
            View::assign('error_url', $host);
            return $this->fetch('error/errorPage');
        }
    }

    public function console($trade_no = '')
    {
        if (Request::isAjax()) {
            // 获取请求参数并过滤标签
            $data = Request::param('', '', 'strip_tags');
            // 从请求参数中提取订单号，若不存在则默认为空字符串
            $out_trade_no = $data['TradeNo'] ?? '';

            // 检查订单号是否为空
            if (empty($out_trade_no)) {
                // 若为空，返回错误信息
                return json(['code' => 201, 'msg' => '订单号为空!']);
            }

            // 根据订单号查询订单信息
            $order_row = YpayOrder::where('out_trade_no', $out_trade_no)->find();

            // 若订单信息为空，说明订单不存在
            if (empty($order_row)) {
                // 返回订单不存在的错误信息
                return json(['code' => 201, 'msg' => '订单不存在!']);
            }

            // 根据订单的账户 ID 查询账户信息，避免重复查询
            $account = YpayAccount::where('id', $order_row['account_id'])->find();
            //获取用户配置信息
            $basic = YpayUserbasic::where('user_id', $order_row['user_id'])->find();

            // 检查账户代码是否为特定值，若是则执行相应操作
            if ($account['code'] === 'lkl_wxpay' || $account['code'] === 'lkl_alipay') {
                Order::lkl($out_trade_no, $order_row['account_id']);
            }

            // 获取超时地址
            if ($basic['timeout_method'] == 1) {
                //如果异步回调地址为空 则进入或者同步回调地址
                if (empty($order['notify_url'])) {
                    preg_match('/(https?:\/\/)?((?:[a-zA-Z0-9-]+\.)+[a-zA-Z]{2,6}|[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+):?(\d+)?(\/\S*)?/', $order_row['return_url'], $matches);
                    $protocol = $matches[1] ? $matches[1] : 'http://'; // 添加默认值 http://
                    $timeout_url = $protocol . $matches[2];
                } else if (empty($order_row['return_url']) && empty($order_row['notify_url'])) {
                    $timeout_url = '/';
                } else {
                    preg_match('/(https?:\/\/)?((?:[a-zA-Z0-9-]+\.)+[a-zA-Z]{2,6}|[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+):?(\d+)?(\/\S*)?/', $order_row['notify_url'], $matches);
                    $protocol = $matches[1] ? $matches[1] : 'http://'; // 添加默认值 http://
                    $timeout_url = $protocol . $matches[2];
                }
            } else {
                $timeout_url = $basic['timeout_url'];
            }

            // 检查订单状态是否为已支付
            if ($order_row['status'] === 1) {
                // 若回调次数为 0
                if ($order_row['return_num'] === 0) {
                    try {
                        // 更新订单的回调次数
                        YpayOrder::where('out_trade_no', $out_trade_no)->update([
                            'return_num' => $order_row['return_num'] + 1
                        ]);
                        // 调用创建回调信息的方法
                        $u = Jialanshen::creat_callback($order_row);
                        // 返回订单支付成功的信息和回调 URL
                        return json([
                            'code' => 200,
                            'msg' => '订单支付成功!',
                            'url' => $u['return']
                        ]);
                    } catch (\Exception $e) {
                        // 若创建回调信息时出现异常，返回错误信息
                        return json([
                            'code' => 500,
                            'msg' => '回调操作出错: ' . $e->getMessage()
                        ]);
                    }
                } else {
                    // 若回调次数不为 0，说明订单已付款，提示不要重复支付并返回首页 URL
                    return json([
                        'code' => 201,
                        'msg' => '订单已付款,请勿重复支付!',
                        'url' => $timeout_url
                    ]);
                }
            }

            // 检查订单是否超时
            if ($order_row['out_time'] < time()) {
                // 若超时，返回订单超时的错误信息
                return json([
                    'code' => 201,
                    'msg' => '订单超时!'
                ]);
            }

            // 若前面未返回，复用之前查询的账户信息，无需再次查询
            $qr_row = $account;

            // 若账户信息为空，说明通道不存在
            if (empty($qr_row)) {
                // 返回通道不存在的错误信息
                return json([
                    'code' => 201,
                    'msg' => '通道不存在!'
                ]);
            }

            // 检查账户代码是否不是特定值
            if ($qr_row['code'] !== 'wxpay_cloudzs' && $qr_row['code'] !== 'wxpay_skd') {
                // 若账户代码为 qqpay_software
                if ($qr_row['code'] === 'qqpay_software') {
                    if (!empty($order_row['qrcode']) && $order_row['qrcode'] !== 'ewmLoading') {
                        // 处理二维码 URL
                        $order_row['qrcode'] = build_qrcode_url($order_row['qrcode'], '350x350');
                        // 返回二维码获取成功的信息
                        return json([
                            'code' => 100,
                            'msg' => '二维码获取成功!',
                            'qr_url' => $order_row['qrcode'],
                            'h5_qrurl' => $order_row['h5_qrurl']
                        ]);
                    } elseif ($order_row['qrcode'] === 'ewmLoading') {
                        // 若二维码正在获取中，返回相应信息
                        return json([
                            'code' => 404,
                            'msg' => '二维码获取中!'
                        ]);
                    } else {
                        // 若二维码获取失败，返回相应信息
                        return json([
                            'code' => 201,
                            'msg' => '二维码获取失败!'
                        ]);
                    }
                } else {
                    if($account['qr_type'] != "appreciate" && $qr_row['code'] != 'wxpay_cloudzs'){
                        // 处理其他情况的二维码 URL
                        $order_row['qrcode'] = build_qrcode_url($order_row['qrcode'], '350x350');
                    }

                }
            }

            // 返回二维码获取成功的信息
            return json([
                'code' => 100,
                'msg' => '二维码获取成功!',
                'qr_url' => $order_row['qrcode']
            ]);
        }
        $order = YpayOrder::where('trade_no', $trade_no)->find();
        $user = YpayUser::where('id', $order['user_id'])->find();
        $basic = YpayUserbasic::where('user_id', $order['user_id'])->find();
        $acc = YpayAccount::where('id', $order['account_id'])->find();
        if ($acc['code'] == 'lkl_alipay' ||  $acc['code'] == 'lebrush_alipay') {
            $order['h5_qrurl'] = 'alipayqr://platformapi/startapp?saId=10000007&qrcode=' . $order['h5_qrurl'];
        } else if ($acc['code'] == 'qqpay_wzq') {
            if (self::get_device_type() == "ios") {
                $order['h5_qrurl'] = 'mqqapi://wxminiapp/launch?src_type=internal&version=1&channel_id=1&user_name=gh_b2f9cc238009&app_type=0&ext=extmsgtes&_vacf=qw&path=' . urlencode(urlencode($order['h5_qrurl']));
            } else {
                $order['h5_qrurl'] = 'mqqapi://wxminiapp/launch?src_type=internal&version=1&channel_id=1&user_name=gh_b2f9cc238009&app_type=0&ext=extmsgtes&_vacf=qw&path=' . $order['h5_qrurl'];
            }
        }

        // 监控倒计时
        $ms = $order['out_time'] - time();

        // 获取超时地址
        if ($basic['timeout_method'] == 1) {
            //如果异步回调地址为空 则进入或者同步回调地址
            if (empty($order['notify_url'])) {
                preg_match('/(https?:\/\/)?((?:[a-zA-Z0-9-]+\.)+[a-zA-Z]{2,6}|[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+):?(\d+)?(\/\S*)?/', $order['return_url'], $matches);
                $protocol = $matches[1] ? $matches[1] : 'http://'; // 添加默认值 http://
                $timeout_url = $protocol . $matches[2];
            } else if (empty($order['return_url']) && empty($order['notify_url'])) {
                $timeout_url = '/';
            } else {
                preg_match('/(https?:\/\/)?((?:[a-zA-Z0-9-]+\.)+[a-zA-Z]{2,6}|[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+):?(\d+)?(\/\S*)?/', $order['notify_url'], $matches);
                $protocol = $matches[1] ? $matches[1] : 'http://'; // 添加默认值 http://
                $timeout_url = $protocol . $matches[2];
            }
        } else {
            $timeout_url = $basic['timeout_url'];
        }

        // 检查订单是否超时
        if ($order['out_time'] < time() && $order['status'] != 1) {
            // 若超时，返回订单超时的错误信息
            View::assign('error_tips', "订单已超时,请勿重复支付!");
            View::assign('error_url', $timeout_url);
            return $this->fetch('error/errorPage');
        }


        // 检查订单状态是否为已支付
        if ($order['status'] === 1) {
            // 若回调次数为 0
            if ($order['return_num'] > 0) {
                // 若回调次数不为 0，说明订单已付款，提示不要重复支付并返回超时地址
                View::assign('error_tips', "订单已付款,请勿重复支付!");
                View::assign('error_url', $timeout_url);
                return $this->fetch('error/errorPage');
            }
        }

        if ($user['is_frozen'] != 0) {
            View::assign('error_tips', "该用户已被冻结，请联系站长");
            View::assign('error_url', $timeout_url);
            return $this->fetch('error/errorPage');
        }


        //单独收集订单数组需要信息
        $order_arr = [
            'type' => $order['type'], //订单类型
            'truemoney' => $order['truemoney'], //实际需要付款金额
            'h5_qrurl' => $order['h5_qrurl'], //手机端跳转地址
            'out_trade_no' => $order['out_trade_no'], //订单号
            'qrcode' => $order['qrcode'], //二维码地址
            'name' => $order['name'], //订单名称
            'create_time' => $order['create_time'], //订单创建时间
        ];

        //声明下单返回参数数组
        $pay_array = [
            'order' => $order_arr, //订单信息
            'ms' => $ms, //订单倒计时
            'other_arr' => [
                'hidden_sacnName' => $basic['hidden_sacnName'], //获取是否隐藏商品名称
                'is_jump' => $basic['is_jump'], //获取是否开启手机端跳转
                'code' => $acc['code'], //获取账户类型是WxPay/Alipay/QQPay等
                'console_notity' => $basic['console_notity'], //获取提示备注
                'timeout_url' => $timeout_url, //超时跳转地址
                'is_voice_tips' => $basic['is_voice_tips'], //是否开启语音提示
                'is_payPopUp' => $basic['is_payPopUp'], //是否开启弹窗提示
                'is_realName' => $user['is_realName'] //是否完成实名认证
            ], //其他参数
        ];
        // 获取 public/pay 目录的绝对路径（确保路径正确性）
        $payDir = app()->getRootPath() . 'public/pay';
        // 获取用户选择的模板
        $selectedTemplate = $basic['console_temp'] ?? '';

        // 构建完整模板路径
        $templatePath = $payDir . '/' . $selectedTemplate;

        // 有效性验证（目录存在且包含index.html文件）
        if (!is_dir($templatePath) || !file_exists($templatePath . '/index.html')) {
            // 尝试获取第一个有效主题
            if (!empty(S::getPayTheme())) {
                $selectedTemplate = S::getPayTheme()['data'][0]['id'];
            }
            // 完全无可用模板的兜底处理
            else {
                // 跳转到指定的错误页面
                View::assign('error_tips', "请先配置下单界面");
                View::assign('error_url', $timeout_url);
                return $this->fetch('error/errorPage');
            }
        }
        View::assign('pay_array', $pay_array);
        return $this->fetch('../../public/pay/' . $selectedTemplate . '/index');
    }

    //判断是安卓还是Ios
    public static function get_device_type()
    {
        //全部变成小写字母
        $agent = strtolower($_SERVER['HTTP_USER_AGENT']);
        $type = 'other';
        //分别进行判断
        if (strpos($agent, 'iphone') || strpos($agent, 'ipad')) {
            $type = 'ios';
        }
        if (strpos($agent, 'android')) {
            $type = 'android';
        }
        return $type;
    }

    public static function console_dopay($out_trade_no = '')
    {
        if (Request::isAjax()) {
            $data = Request::param('', '', 'strip_tags');
            $out_trade_no = $data['TradeNo'];
            if (empty($out_trade_no)) {
                return json(['code' => 0, 'msg' => '订单号为空!']);
            }
            $order_row = recharge::where('out_trade_no', $out_trade_no)->find();
            if (empty($order_row)) {
                return json(['code' => 0, 'msg' => '订单不存在!']);
            }

            if ($order_row['status'] == 1) {
                return json(['code' => 200, 'msg' => '订单支付成功!', 'url' => Request::domain() . '/Deal/Recharge']);
            }
            if ($order_row['out_time'] < time()) {
                return json(['code' => 0, 'msg' => '订单超时!']);
            }
            $order_row['qrcode'] = build_qrcode_url($order_row['qrcode']);
            return json(['code' => 100, 'msg' => '二维码获取成功!', 'qr_url' => $order_row['qrcode']]);
        }
        $order = recharge::where('out_trade_no', $out_trade_no)->find();
        $basic = YpayUserbasic::where('user_id', $order['user_id'])->find();
        $ms = $order['out_time'] - time();
        $order['name'] = '在线充值';
        $order['truemoney'] = $order['money'];
        $order['type'] = 'alipay';
        View::assign('order', $order);
        View::assign('ms', 180);
        View::assign('code', 'alipay_dmf');
        View::assign('console_notity', $basic['console_notity']);
        View::assign('timeout_url', $basic['timeout_url']);
        View::assign('is_voice_tips', $basic['is_voice_tips']);
        View::assign('is_payPopUp', $basic['is_payPopUp']);
        return $this->fetch('../../public/pay/' . $basic['console_temp'] . '/index');
    }

    public function apisubmit()
    {
        $data = Request::param('', '', 'strip_tags');
        if (empty($data['pid'])) {
            return json(['code' => 201, 'msg' => 'PID不可为空!']);
        }
        if (empty($data['out_trade_no'])) {
            return json(['code' => 201, 'msg' => '订单号不可为空!']);
        }
        if (empty($data['type'])) {
            return json(['code' => 201, 'msg' => '支付类型不可为空!']);
        }
        if (empty($data['notify_url'])) {
            return json(['code' => 201, 'msg' => '异步通知不可为空!']);
        }
        if (empty($data['return_url'])) {
            return json(['code' => 201, 'msg' => '同步通知不可为空!']);
        }
        if (empty($data['name'])) {
            return json(['code' => 201, 'msg' => '商品名称不可为空!']);
        }
        if (empty($data['money'])) {
            return json(['code' => 201, 'msg' => '金额不可为空!']);
        }
        $user = YpayUser::where('id', $data['pid'])->find();
        if (empty($user)) {
            return json(['code' => 201, 'msg' => '商户不存在!']);
        }
        $time = strtotime($user['vip_time']);
        if ($time < time()) {
            return json(['code' => 201, 'msg' => '未开通套餐或套餐已过期!']);
        }
        if ($data['money'] <= 0) {
            return json(['code' => 201, 'msg' => '金额错误!']);
        }
        if ($data['money'] < getConfig()['min_orderprice']) {
            return json(['code' => 201, 'msg' => '订单金额低于最低发起金额!']);
        }
        if ($data['money'] > getConfig()['max_orderprice']) {
            return json(['code' => 201, 'msg' => '订单金额高于最高发起金额!']);
        }
        if (!empty(getConfig()['shield_key'])) {
            $weigui = explode('|', getConfig()['shield_key']);
            for ($index = 0; $index < count($weigui); $index++) {
                if (empty($weigui[$index])) {
                    continue;
                }
                if (strpos($data['name'], $weigui[$index]) !== false) {
                    $risk_data = [
                        'user_id' => $data['pid'],
                        'name' => $data['name'],
                        'url' => $data['return_url']
                    ];
                    try {
                        Risk::create($risk_data);
                    } catch (\Exception $e) {
                        return json(['code' => 201, 'msg' => '商品违规,已记录!']);
                    }
                    return json(['code' => 201, 'msg' => '商品违规,已记录!']);
                }
            }
        }


        if ($user['vip_id'] != 0) {

            $vip = YpayVip::where('id', $user['vip_id'])->find();

            //判断是否开启收款限额
            if (isset($vip)) {
                if ($vip['is_quota']) {
                    $today_money = YpayOrder::where(['status' => 1, 'user_id' => $user['id']])->whereDay('create_time')->sum('money');
                    if ($today_money > $vip['today_quota']) {
                        return json(['code' => 201, 'msg' => "今日收款累计超过" . $vip['today_quota'] . "的收款限额"]);
                    }
                }
            }
        }

        //是否支持余额为负数或者0可以发起支付
        if (getConfig()['is_pay_money'] == 0 && ($user['money'] == 0 || $user['money'] < 0)) {
            View::assign('error_tips', "账户余额不足,无法发起支付");
            View::assign('error_url', $host);
            return $this->fetch();
        }

        $feilv_money = $data['money'] * $user['feilv'] / 100;
        if ($user['money'] < $feilv_money) {
            return json(['code' => 201, 'msg' => '账户余额不足,无法发起支付!']);
        }



        $epay = new epay();
        $isSign = $epay->verifySign($data, $user['user_key']); //生成签名结果
        if (!$isSign) {
            return json(['code' => 201, 'msg' => '验签失败,请检查PID或者Key是否正确!']);
        }
        $is_orderNo = YpayOrder::where('out_trade_no', $data['out_trade_no'])->find();
        if ($is_orderNo) {
            return json(['code' => 201, 'msg' => '订单号重复,请重新发起!']);
        }
        if (getConfig()['isDiy_orderNo'] == 1) {
            $trade_no = getConfig()['diy_orderNo'] . date("YmdHis") . rand(11111, 99999);
        } else {
            $trade_no = 'Y' . date("YmdHis") . rand(11111, 99999);
        }
        $QR_row =  YpayAccount::where('type', $data['type'])->where('user_id', $data['pid'])->where('status', 1)->where('is_status', 1)->orderRaw('rand()')->find(); //随机获取通道
        if (empty($QR_row)) {
            return json(['code' => 201, 'msg' => '暂无收款账号在线!']);
        }
        $action = $QR_row['code'];
        $res = Jialanshen::$action($trade_no, $QR_row, $data, $user);
        if ($res) {
            $order = YpayOrder::where('trade_no', $trade_no)->find();
            $data = array(
                'code' => 200,
                'msg' => '获取成功!',
                'trade_no' => $order['trade_no'],
                'qrcode' => $order['qrcode'],
                'h5_qrurl' => $order['h5_qrurl'],
                'type' => $order['type'],
                'out_trade_no' => $order['out_trade_no'],
                'money' => $order['truemoney'],
                'code_url' =>  build_qrcode_url(urldecode($order['qrcode'])),
            );
            return json($data);
        } else {
            $data = array(
                'code' => 201,
                'msg' => '订单生成错误,请重新发起支付!',
            );
            return json($data);
        }
    }

    //付费注册请求
    public function reg()
    {
        $data = Request::param('', '', 'strip_tags');
        $trade_no = $data['trade_no'];
        $typeid = $data['typeid'];
        $config = getConfig(); //系统配置参数
        if (empty($trade_no)) {
            exit('请输入订单号');
        }
        $order = recharge::where('out_trade_no', $trade_no)->find();
        if (empty($order)) {
            exit('订单不存在');
        }
        if ($typeid == "alipay") {
            $paytype = "alipay";
            $paylist = 'alipay';
        } else {
            $paytype = "wxpay";
            $paylist = 'wechat';
        }
        //修改数据库订单支付方式标识
        recharge::where('id', $order['id'])->update(['type' => $paytype]);
        $request = \think\facade\Request::instance();
        $temp = YpayPaylist::where(['id' => $config[$paylist], 'user_id' => $order['user_id']])->find();
        //组装支付数据
        $data = [
            "type"       => $paytype, //支付方式
            "out_trade_no"     => $trade_no, //商户订单号
            "notify_url" =>  $request->root(true) . '/Notify/regnotify_epay', //异步通知地址
            "return_url" =>  $request->root(true) . '/Notify/regretify_epay', //同步通知地址
            "name" => "用户注册", //商品名称
            "money"      => $config['paid_reg_price'], //订单金额
        ];

        //根据支付类型调用不同方法
        //1:支付参数 2:数据 3:订单号
        switch ($temp['type']) {
            case 'epay':
                $res = payList::epay($data, $trade_no);
                break;
            case 'dmf':
                $res = payList::alipay($data, $trade_no);
                $order = recharge::where('out_trade_no', $trade_no)->find();
                $basic = YpayUserbasic::where('user_id', $order['user_id'])->find();
                $data = [
                    'user_id' => $order['user_id'],
                    'status' => 0,
                    'out_time' => time() + 300,
                    'qrcode' => $res['qr_code'],
                ];
                YpayRecharge::goEdit($data, $order['id']);
                $order = recharge::where('out_trade_no', $trade_no)->find();
                $ms = $order['out_time'] - time();
                $order['name'] = '在线充值';
                $order['h5_qrurl'] = $res['qr_code'];
                $order['trade_no'] = $order['out_trade_no'];
                $order['truemoney'] = $order['money'];
                $order['type'] = 'alipay';
                View::assign('order', $order);
                View::assign('ms', 300);
                View::assign('code', 'alipay_dmf');
                View::assign('console_notity', '');
                View::assign('timeout_url', '/');
                View::assign('is_voice_tips', '0');
                View::assign('is_payPopUp', '0');
                return $this->fetch('pay/console_dopay');
                die;
                break;
            case 'alipay':
                $res = payList::alipay($data, $trade_no);
                break;
            case 'wxpay':
                $res = payList::wxpay($data, $trade_no);
                break;
            default:
                // code...
                break;
        }

        echo ($res);
        die;
    }
}
