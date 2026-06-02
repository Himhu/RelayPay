<?php

declare(strict_types=1);

namespace app\index\controller;

use think\facade\Session;
use think\facade\Request;
use think\facade\View;
use app\common\util\Upload as Up;
use app\common\service\YpayUser as S;
use think\facade\Db;
use app\common\model\AdminChannel;
use app\common\model\YpayPayment;
use app\common\model\YpayAccount as Yaccount;
use app\common\model\YpayPaylist as paylist;
use app\common\service\YpayAccount;
use app\common\service\YpayPaylist as s_paylist;
use app\common\model\YpayUserbasic as basic;
use app\common\service\Jialanshen;
use app\common\core\core;

class Channel extends \app\BaseController
{
    protected $middleware = ['FrontCheck', 'FrontAuth', 'Domain', 'ForceRealName', 'Mtce', 'GoogleAuth'];

    public function upload()
    {
        $res = Up::qrputFile(Request::file(), Request::post('path'), Request::post('channel_code'),Request::post('qr_type'));
        return $this->getJson($res);
    }

    //通道列表
    public function index()
    {
        if (Request::isAjax()) {
            $account = Yaccount::getUserList(S::getUserId());
            json_encode($account, JSON_FORCE_OBJECT);
            return $account;
        }

        $payment = YpayPayment::where(['status' => 1])->order('sort', 'aes')->select()->toArray();

        if (!empty($payment)) {
            $channel = AdminChannel::where(['status' => 1, 'type' => $payment[0]['type']])->order('sort', 'desc')->select();

            //获取微信云端
            $cloud = Db::table('ypay_cloud')->order('sort', 'asc')->where(['status' => 1, 'type' => 1])->select()->toArray();
            $cloudType = array();
            $xy = array();
            $macV3 = null;
            $uos = null;
            $fiveAndOne = null;
            $new_mac = null;
            $i = 1;
            //循环遍历
            foreach ($cloud as $key => $value) {
                switch ($value['cloud_type']) {
                    case 1:
                        $macV3 = ['id' => 1, 'name' => 'Mac - V3'];
                        $xy[$i]['id'] = $value['id'];
                        $xy[$i]['name'] = $value['name'];
                        $i++;
                        break;
                    case 2:
                        $uos = ['id' => 2, 'name' => 'Uos'];
                        break;
                    case 3:
                        $fiveAndOne = ['id' => 3, 'name' => '五合一'];
                        break;
                    case 4:
                        $new_mac = ['id' => 4, 'name' => '新版Mac'];
                        break;
                }
            }

            $cloudType = [$macV3, $uos, $fiveAndOne, $new_mac];
            $cloudType = array_filter($cloudType);

            if (empty($cloudType)) {
                $cloudType =
                    [
                        ['id' => 0, 'name' => '未有可用云端']
                    ];
                $xy = [['id' => '未有可用云端', 'name' => '未有可用云端']];
            }

            $cloud_login_type =
                [
                    ['id' => 1, 'name' => "车载"],
                    ['id' => 2, 'name' => "Windows"],
                    ['id' => 3, 'name' => "APad"],
                    ['id' => 4, 'name' => "Mac"],
                    ['id' => 5, 'name' => "IPad"],
                ];
        } else {
            $payment = [['type' => 'null', "name" => "暂未配置渠道"]];
            $cloud_login_type = [['id' => 'null', 'name' => "暂未配置渠道"],];
            $cloudType = [['id' => 'null', 'name' => '暂未配置渠道']];
            $xy = [['id' => 'null', 'name' => '暂未配置渠道']];
            $channel = [['code' => 'null', 'name' => '暂未配置渠道']];
        }

        View::assign(
            [
                'user' => S::getUser(),
                'vip' => S::getVip(),
                'xy' => $xy,
                'payment' => $payment,
                'channel' => $channel,
                'cloud_login_type' => $cloud_login_type,
                'cloudType' => $cloudType
            ]
        );
        // 改变当前操作的模板路径
        getUserTemplate();
        return $this->fetch();
    }

    //获取云端信息
    public function getCloud_type()
    {
        $data = Request::param();
        $cloud = Db::table('ypay_cloud')->field('cloud_type,id,name')->order('sort', 'asc')->where(['status' => 1, 'type' => $data['type'], 'cloud_type' => $data['id']])->select();
        return json(['code' => 1, 'cloud_type' => $cloud]);
    }

    //通道配置
    public function basic()
    {
        if (Request::isAjax()) {
            $paylist = paylist::getUserList(S::getUserId());
            json_encode($paylist, JSON_FORCE_OBJECT);
            return $paylist;
        }
        $cashierMode =
            [
                ['id' => 2, 'name' => '模式①:转账模式(风控制:低)'],
                ['id' => 3, 'name' => '模式②:支付宝二维码模式'],
            ];

        $channelMode =
            [
                ['id' => 1, 'name' => '模式①:带备注跳转模式', 'cashierType' => 'all'],
                ['id' => 2, 'name' => '模式②:无备注跳转模式', 'cashierType' => 'all'],
                ['id' => 3, 'name' => '模式③:手动输入金额跳转模式', 'cashierType' => 'all'],
                ['id' => 4, 'name' => '模式④:锁死金额/订单号跳转模式', 'cashierType' => 'all'],
            ];
        $basicInfo = S::getBasic();
        if (!empty($basicInfo)) {
            $mode = (string) ($basicInfo['channelMode'] ?? '');
            if (!in_array($mode, ['1', '2', '3', '4'], true)) {
                $basicInfo['channelMode'] = 2;
            }
        }
        View::assign(
            [
                'user' => S::getUser(),
                'vip' => S::getVip(),
                'basic' => $basicInfo,
                'cashierMode' => $cashierMode,
                'channelMode' => $channelMode,
                'themes' => S::getPayTheme()
            ]
        );
        // 改变当前操作的模板路径
        getUserTemplate();
        return $this->fetch();
    }

    //筛选支付宝交易模式
    public function cashierMode()
    {
        $data = Request::param();
        $channelMode =
            [
                ['id' => 1, 'name' => '模式①:带备注跳转模式', 'cashierType' => 'all'],
                ['id' => 2, 'name' => '模式②:无备注跳转模式', 'cashierType' => 'all'],
                ['id' => 3, 'name' => '模式③:手动输入金额跳转模式', 'cashierType' => 'all'],
                ['id' => 4, 'name' => '模式④:锁死金额/订单号跳转模式', 'cashierType' => 'all'],
            ];

        $modeId = $data['id'] ?? null;
        if ($modeId === null) {
            return json(['code' => 1, 'channelMode' => $channelMode]);
        }
        if ((string) $modeId === '3') {
            return json(['code' => 1, 'channelMode' => []]);
        }
        $filtered = array_values(array_filter($channelMode, function ($mode) use ($modeId) {
            if ($mode['cashierType'] === 'all') {
                return true;
            }
            return (string) $mode['cashierType'] === (string) $modeId;
        }));

        return json(['code' => 1, 'channelMode' => $filtered]);
    }

    // 修改通道配置信息
    public function edit_basic()
    {
        if (Request::isAjax()) {
            $params = Request::param('', '', 'strip_tags');
            if (($params['cashierMode'] ?? null) == 3) {
                $params['channelMode'] = 2;
            } else {
                $params['channelMode'] = $params['channelMode'] ?? 1;
            }
            return $this->getJson(S::goBasicEdit($params, S::getUserId()));
        }
    }


    // 切换支付界面模板
    public function UpPayPage()
    {
        if (Request::isAjax()) {
            $data = Request::param('', '', 'strip_tags');
            return $this->getJson(S::goUpPayPage(['console_temp' => $data['paypage']], S::getUserId()));
        }
    }


    //添加转接通道
    public function addtransfer()
    {
        if (Request::isAjax()) {
            $data = Request::post();
            $data['user_id'] = Session::get('front.id');
            return $this->getJson(s_paylist::goAdd($data));
        }
    }

    //修改转接通道
    public function editTransfer()
    {
        if (Request::isAjax()) {
            $data = Request::post();
            return $this->getJson(s_paylist::goEdit($data, $data['id']));
        }
    }

    // 删除转接通道
    public function delTransfer()
    {
        $data = Request::param('', '', 'strip_tags');
        return $this->getJson(s_paylist::goRemove($data['id']));
    }

    //更改转接通道状态
    public function editTransferStatus()
    {
        $data = Request::param('', '', 'strip_tags');
        return $this->getJson(s_paylist::goStatus($data['status'], $data['id']));
    }

    public function type()
    {
        $data = Request::param();
        $channel = Db::table('admin_channel')->where(['status' => 1, 'type' => $data['id']])->order('sort', 'desc')->select();
        return json(['code' => 1, 'channel' => $channel]);
    }

    //新增通道
    public function addchannel()
    {
        $data = Request::param('', '', 'strip_tags');
        $vip = S::getVip(); //获取对应套餐配置信息
        //判断是否开启限制添加通道
        if ($vip['is_addChannelNum'] == 1) {
            $count = Yaccount::where('user_id', S::getUserId())->count();
            if ($count >= $vip['addChannelNum']) {
                return ['msg' => "通道添加已上限", 'code' => 201];
            }
        }

        if ($data['code'] == 'wxpay_dy' || $data['code'] == 'wxpay_software') {
            if ($data['code'] == 'wxpay_dy') {
                if (empty($data['wxname'])) {
                    return ['msg' => "收款微信昵称不可为空", 'code' => 201];
                }
                $verywx = Yaccount::where('wxname', $data['wxname'])->find();
                if (!empty($verywx)) {
                    return ['msg' => "收款微信昵称已存在,请检查", 'code' => 201];
                }
            }
        }
        return $this->getJson(YpayAccount::goAdd($data));
    }

    // 切换云端地域
    public function switchCloud()
    {
        $data = Request::param('', '', 'strip_tags');
        return $this->getJson(YpayAccount::goSwitchCloud($data));
    }

    //修改支付宝当面付/商家账单通道
    public function editAliPay()
    {
        $data = Request::param('', '', 'strip_tags');
        return $this->getJson(YpayAccount::goEditAliPay($data));
    }

    //修改微信APP挂机/自挂/店员通道
    public function editWxPay()
    {
        $data = Request::param('', '', 'strip_tags');
        if ($data['code'] == 'wxpay_dy') {
            if (empty($data['wxname'])) {
                return ['msg' => "收款微信昵称不可为空", 'code' => 201];
            }
        }
        return $this->getJson(YpayAccount::goEditWxPay($data));
    }

    //修改Usdt通道
    public function editUsdt()
    {
        $data = Request::param('', '', 'strip_tags');
        return $this->getJson(YpayAccount::goEditUsdt($data));
    }

    //获取通道登录二维码
    public function GetQrlistQrcode()
    {
        //获取ID
        $data  = input();
        return json(YpayAccount::GetQrlistQrcode($data['id']));
    }

    //获取扫码状态
    public function GetChannelLoginStatus()
    {
        //获取ID
        $data  = input();
        return json(YpayAccount::GetChannelLoginStatus($data['id']));
    }

    //提交验证码
    public function SubmitVerificationCode()
    {
        //获取提交数据
        $data = Request::param('', '', 'strip_tags');
        return json(YpayAccount::SubmitVerificationCode($data['code'], $data['id'], $data['data']));
    }

    //删除通道 参数:通道ID
    public function DelChannel()
    {
        //获取ID
        $data  = input();
        //创建Core实例
        $core  = new Core();
        //查询通道信心
        $account =  Db::table('ypay_account')->where('id', $data['id'])->find();

        if ($account['code'] == 'wxpay_cloud') {
            // 执行删除云端内微信
            $core->getDelWechatAccount($account['wx_guid'], $account['cloud_id']);
        }

        try {
            //执行删除该通道
            Db::table('ypay_account')->where('id', $data['id'])->where('user_id', S::getUserId())->delete();
            return json(['code' => 1, 'msg' => '删除成功!']);
        } catch (\Exception $e) {
            return ['msg' => '请检查通道是否存在', 'code' => 201];
        }
    }

    //更改收款状态
    public function SaveStatus()
    {
        $data  = input();
        //查询账户表是否有这个用户数据
        $account = Db::table('ypay_account')->where('id', $data['id'])->where('user_id', S::getUserId())->find();
        if (empty($account)) {
            return json(['code' => 0, 'msg' => '通道不存在!']);
        }
        //更改通道收款状态
        YpayAccount::goIsStatus($data['status'], $data['id']);
        return json(['code' => 1, 'msg' => '操作成功!']);
    }

    //测试支付
    public function testPay()
    {

        $temp = Request::param('', '', 'strip_tags');
        $request = \think\facade\Request::instance();
        
        //验证测试金额是否为空
        if(empty($temp['money'])){
            return json(['code' => 201, 'msg' => '测试金额不能为空!']);
        }

        // 生成订单号
        if (getConfig()['isDiy_orderNo'] == 1) {
            $trade_no = getConfig()['diy_orderNo'] . date("YmdHis") . rand(11111, 99999);
            $out_trade_no = getConfig()['diy_orderNo'] . date("YmdHis") . rand(11111, 99999);
        } else {
            $trade_no = 'Y' . date("YmdHis") . rand(11111, 99999);
            $out_trade_no = 'Y' . date("YmdHis") . rand(11111, 99999);
        }

        //获取通道信息
        $account =  Db::name('ypay_account')->where('id', $temp['id'])->find(); //获取通道

        //检查通道是否掉线
        if ($account['status'] == 0) {
            return json(['code' => 201, 'msg' => '通道处于掉线状态']);
        }

        //检查通道是否关闭
        if ($account['is_status'] == 0) {
            return json(['code' => 201, 'msg' => '通道收款开关关闭']);
        }

        //创建测试数据数组
        $data =
            [
                "type"  => $account['type'],
                "out_trade_no"  => $out_trade_no,
                "pid" => S::getUserId(),
                "money"      => $temp['money'], //订单金额
                'name' => '测试支付',
            ];
        $data["notify_url"] =  $request->root(true) . '/Notify/testPay'; //异步通知地址
        $data["return_url"] =  $request->root(true) . '/Channel/Index'; //同步通知地址
        $action = $account['code'];
        $res = Jialanshen::$action($trade_no, $account, $data, S::getUser());

        if (isset($res['code']) && $res['code'] == 201) {
            return json($res);
        }

        $order = Db::name('ypay_order')->where('trade_no', $trade_no)->find(); //获取订单信息

        if ($res) {
            if($account['qr_type'] != "appreciate" && $account['code'] != 'wxpay_cloudzs'){
                $codeUrl = build_qrcode_url(urldecode($order['qrcode']));
            }else{
                $rawQrcode = urldecode($order['qrcode']);
                $codeUrl = str_starts_with($rawQrcode, 'http')
                    ? $rawQrcode
                    : $request->root(true) . $rawQrcode;
            }

            $actualAmount = $order['truemoney'] ?? $order['money'] ?? $temp['money'];
            $payAmount = is_numeric((string) $actualAmount) ? sprintf('%.2f', (float) $actualAmount) : (string) $actualAmount;

            return json([
                'code' => 200,
                'out_trade_no' => $order['out_trade_no'],
                'code_url' => $codeUrl,
                'pay_url' => $request->root(true) . '/Pay/console?trade_no=' . $trade_no,
                'pay_amount' => $payAmount
            ]);
        } else {
            View::assign('error_tips', "订单生成错误,请重新发起支付");
            View::assign('error_url', '/User');
            // 改变当前操作的模板路径
            getUserTemplate();
            return $this->fetch();
        }
    }
}
