<?php


namespace app\index\job;


use think\facade\Db;
//use think\queue\Job;
use app\common\service\Jialanshen;
use app\common\service\Notice as notice;
use app\common\model\YpayUserbasic as basic;
use app\common\util\Mail;
use app\common\core\core;


class Order
{

    /**
     * 在宝塔里的  Supervisord管理器==添加手护进程
     * 名称就是 队列名称，启动命令是：php think queue:listen --queue +队列名
     * 启动用户：root
     * @队列执行
     * @param Job $job
     * @param [type] $param
     *
     * @return void
     */
    public function fire($param)
    {
        try {
            //参数
            $data = $param;
            //根据参数筛选执行对应方法
            switch ($data['code']) {
                case 'vip_expire':
                    $res = $this->vip_expire(); //会员到期提醒
                    break;
                case 'dataClear':
                    $res = $this->dataClear(); //自定义订单数据清理
                    break;
                case 'disconnect':
                    $res = $this->disconnect(); //通道掉线检测
                    break;
                default:
                    $res = $this->handleOrder($data['code']);
                    break;
            }
        } catch (\Exception $exception) {
            record_log($exception->getMessage(), "exception");
        }
    }


    /**
     * @通道掉线检测
     * @param [type] $id
     *
     * @return void
     */
    public function disconnect()
    {
        $core = new Core();

        // 将排除列表提升为类属性（便于后续插件配置）
        $excludedCodes = [
            'lkl_alipay', 'alipay_dmf',
            'alipay_software', 'wxpay_dy', 'lkl_wxpay',
            'wxpay_software', 'qqpay_software', 'usdt'
        ];
        // 构建动态查询条件
        $query = Db::name('ypay_account')
            ->where('status', 1);

        foreach ($excludedCodes as $code) {
            $query->where('code', '<>', $code);
        }
        // 分块处理避免内存溢出
        $query->chunk(100, function ($accounts) use ($core) {
            $this->processAccounts($core, $accounts);
        });
    }

    /**
     * 账户处理入口（后续可拆分为独立处理器）
     */
    protected function processAccounts($core, $accounts)
    {
        foreach ($accounts as $row) {
            try {
                // 根据类型分发处理逻辑
                $this->routeAccountHandler($core, $row);
            } catch (\Exception $e) {
                // 异常日志记录（后续可插件化）
                echo ("账户处理异常: {$row['id']}" . $e->getMessage());
                continue;
            }
        }
    }

    /**
     * 逻辑路由方法（后续可扩展为策略模式）
     */
    protected function routeAccountHandler($core, $row)
    {

        switch (true) {
            case in_array($row['code'], ['qqpay_wzq', 'qqpay_cloud']):
                $this->handleQQPayment($core, $row);
                break;

            case $row['code'] === 'wxpay_cloud':
            case $row['code'] === 'wxpay_cloudzs':
            case $row['code'] === 'wxpay_jym_cloud':
            case $row['code'] === 'wxpay_skd':
                $this->handleWechatPayment($core, $row);
                break;
        }
    }

    /**
     * QQ支付处理（后续可拆分为独立类）
     */
    protected function handleQQPayment($core, $row)
    {

        // 获取当前时间
        $now = time();
        // 将 update_time 转换为时间戳
        $lastUpdateTime = strtotime($row['update_time']);
        if ($now - $lastUpdateTime >= getConfig()['disconnect_minute'] * 60) {
            if ($row['status'] == 0 && !empty($row['cloud_id'])) {
                $this->handleCloudQQ($core, $row);
            } elseif ($row['code'] == 'qqpay_wzq') {
                $this->handleLocalQQ($core, $row);
            }
            // 更新 update_time 字段
            $this->safeUpdateAccountTime($row['id']);
        }
    }

    /**
     * 云QQ处理逻辑
     */
    protected function handleCloudQQ($core, $row)
    {
        $onlist = $core->Api_GetOnlineQQlist($row['cloud_id']);

        foreach ($onlist['data']['bots'] ?? [] as $bot) {
            if ($bot['id'] == $row['qq'] && in_array($bot['status'], ["登录完毕", "登录成功"])) {
                $this->safeUpdateAccountStatus($row['id'], 1);
            }
        }
    }

    /**
     * 本地QQ处理逻辑
     */
    protected function handleLocalQQ($core, $row)
    {

        $cookie = base64_decode($row['cookie'] ?? '');
        $result = $core->qqpay($cookie, $row['qq']);

        if ($result['code'] == 201) {
            self::lose_expire($row['user_id'], 'wxpay', $row['id']);
        }
    }

    /**
     * 微信支付处理（后续可拆分为独立类）
     */
    protected function handleWechatPayment($core, $row)
    {

        // 获取当前时间
        $now = time();
        // 将 update_time 转换为时间戳
        $lastUpdateTime = strtotime($row['update_time']);
        // 计算距离上次更新时间是否超过后台设置时间
        if ($now - $lastUpdateTime >= getConfig()['disconnect_minute'] * 60) {
            $result = $core->getWechatStatus(
                $row['wx_guid'],
                $row['cloud_id'],
                $row
            );

            // 初始化二次登录失败计数器
            $secondLoginFailCount = 0;

            // 如果返回状态码为201 且状态值为0则表示离线 
            if ($result['code'] == 201 && $result['status'] == 0) {
                // 心跳失败
                if ($result['status'] == 0) {
                    while ($secondLoginFailCount < 3) {
                        $cloud = Db::name("ypay_cloud")->where('id', $row['cloud_id'])->find();
                        if ($cloud['cloud_type'] == '3' || $cloud['cloud_type'] == '4') {
                            $res = $core->weChatSecondLogin($row['wx_guid'], $cloud, $row);
                            // 二次登录成功
                            if ($res) {
                                break;
                            }
                            // 二次登录失败
                            $secondLoginFailCount++;
                            if ($secondLoginFailCount >= 3) {
                                self::lose_expire($row['user_id'], 'wxpay', $row['id']);
                            }
                        } else {
                            self::lose_expire($row['user_id'], 'wxpay', $row['id']);
                            break;
                        }
                    }
                }
            }
            // 更新 update_time 字段
            $this->safeUpdateAccountTime($row['id']);
        }
    }

    /**
     * 安全更新账户更新时间方法
     */
    protected function safeUpdateAccountTime($accountId)
    {
        Db::name('ypay_account')
            ->where('id', $accountId)
            ->update(['update_time' => date('Y-m-d H:i:s', time() + getConfig()['disconnect_minute'] * 60)]);
    }

    /**
     * 安全更新方法（后续可添加缓存机制）
     */
    protected function safeUpdateAccountStatus($accountId, $status)
    {
        Db::name('ypay_account')
            ->where('id', $accountId)
            ->update(['status' => $status]);
    }

    /**
     * @订单检测
     * @param [type] $id
     *
     * @return void
     */
    public function handleOrder($code)
    {
        $core = new Core();
        //根据标识来进行处理心跳
        switch ($code) {
            case 'alipay_cron': //支付宝监控

                //账户查询参数
                $where =
                    [
                        ['type', '=', 'alipay'],
                        ['status', '=', 1],
                        ['is_status', '=', 1],
                        ['code', '<>', 'alipay_dmf'],
                        ['code', '<>', 'alipay_software'],
                        ['update_time', '<', date('Y-m-d H:i:s', time())]
                    ];

                //获取账户信息
                $account = Db::name('ypay_account')->where($where)->orderRaw("rand()")->select();

                //如果没有账户 则退出 避免浪费资源
                if (empty($account)) {
                    break;
                }
                $count = count($account);
                //开始循环
                foreach ($account as $row) {
                    //记录账户数量
                    $row['count'] = $count;
                    //执行参数
                    $this->callback($row);
                }
                break;
            case 'wxpay_cron': //微信通道

                //账户查询参数
                $where =
                    [
                        ['type', '=', 'wxpay'],
                        ['status', '=', 1],
                        ['is_status', '=', 1],
                        ['code', '<>', 'wxpay_dy'],
                        ['code', '<>', 'wxpay_software'],
                    ];

                //获取账户信息
                $account = Db::name('ypay_account')->where($where)->orderRaw("rand()")->select();


                //如果没有账户 则退出 避免浪费资源
                if (empty($account)) {
                    break;
                }

                //开始循环as
                foreach ($account as $row) {

                    //每次构建前先清空
                    $where = array();
                    //订单查询参数
                    $where =
                        [
                            ['status', '=', 0],
                            ['account_id', '=', $row['id']],
                            ['out_time', '>', time()],
                        ];

                    //获取订单数量
                    $count = Db::name('ypay_order')->where($where)->count();

                    //如果数量大于0 则表示有未失效待支付订单
                    if ($count > 0) {
                        //执行参数
                        $this->callback($row);
                    }
                }
                break;
            case 'qqpay_cron': //QQ通道监控

                //账户查询参数
                $where =
                    [
                        ['type', '=', 'qqpay'],
                        ['status', '=', 1],
                        ['is_status', '=', 1],
                        ['code', '<>', 'qqpay_software']
                    ];

                //获取账户信息
                $account = Db::name('ypay_account')->where($where)->orderRaw("rand()")->select();

                //如果没有账户 则退出 避免浪费资源
                if (empty($account)) {
                    break;
                }
                $count = count($account);
                //开始循环
                foreach ($account as $row) {
                    //记录账户数量
                    $row['count'] = $count;
                    //执行参数
                    $this->callback($row);
                }
                break;
            case 'usdt_cron': //usdt监控

                //账户查询参数
                $where =
                    [
                        ['type', '=', 'usdt'],
                        ['status', '=', 1],
                        ['is_status', '=', 1],
                    ];

                //获取账户信息
                $account = Db::name('ypay_account')->where($where)->orderRaw("rand()")->select();

                //如果没有账户 则退出 避免浪费资源
                if (empty($account)) {
                    break;
                }
                $count = count($account);
                //开始循环
                foreach ($account as $row) {
                    //记录账户数量
                    $row['count'] = $count;
                    //执行参数
                    $this->callback($row);
                }
                break;
        }


        return true;
    }


    /**
     * @订单请求回调
     * @param [type] $id
     *
     * @return void
     */
    public function callback($row)
    {
        //创建core对象
        $core = new Core();

        switch ($row['code']) {
            case 'alipay_mck':
                //每次构建前先清空
                $where = array();
                //订单查询参数
                $where =
                    [
                        ['status', '=', 0],
                        ['account_id', '=', $row['id']],
                        ['out_time', '>', time()],
                    ];
                //获取订单数量
                $count = Db::name('ypay_order')->where($where)->count();
                //订单大于0则进行查询流程 避免无效订单
                if ($count > 0) {

                    $basic = basic::where('user_id', $row['user_id'])->find(); //获取用户配置信息
                    //获取账单结果 传递的参数分别为 支付宝PID APPID 支付宝公钥 应用私钥
                    $result = $core->getAliMckAccountMoney($row['zfb_pid'], $row['wxname'], $row['qr_url'], $row['cookie']);

                    //获取状态码
                    $resultCode = $result->code;

                    //结果不为空 并且 状态码为 10000则进入流程
                    if (!empty($resultCode) && $resultCode == 10000) {
                        //循环打印 detail_list 参数
                        foreach ($result->detail_list as $order) {
                            //当收款标识等于 收入 并且 备注信息不等于 转出到余额 则进入流程
                            if ($order->direction == "收入" &&  $order->trans_memo != "转出到余额") {
                                //如果最后更新时间大于支付时间则退出
                                if (strtotime($row['create_time']) > strtotime($order->trans_dt)) {
                                    break;
                                }

                                //获取支付宝订单号
                                $alipayOrderNo = $order->alipay_order_no;

                                //查询是否存在该订单 存在即退出
                                $temp = Db::name('ypay_order')->where(['alipay_order_no' => $alipayOrderNo])->find();
                                if (!empty($temp)) {
                                    break;
                                }

                                //如果订单备注不是转账则进入流程
                                if ($order->trans_memo != "转账" && $basic['channelMode'] == 1) {
                                    //获取备注信息
                                    $orderNo = $order->trans_memo;
                                    //获取订单金额
                                    $orderMoney = $order->trans_amount;
                                    //每次构建前先清空
                                    $where = array();
                                    //构建订单查询条件
                                    $where =
                                        [
                                            ['out_trade_no', '=', $orderNo], //订单号
                                            ['account_id', '=', $row['id']], //账户ID
                                            ['status', '=', 0], //订单状态
                                            ['truemoney', '=', sprintf("%.2f", $orderMoney)], //付款金额
                                            ['out_time', '>', time()],
                                        ];

                                    //查询是否存在该订单
                                    $orderrow = Db::name('ypay_order')->where($where)->order('id desc')->find();
                                } else {
                                    //获取支付宝订单号
                                    $alipayOrderNo = $order->alipay_order_no;
                                    //获取订单金额
                                    $orderMoney = $order->trans_amount;

                                    //每次构建前先清空
                                    $where = array();
                                    // 构建订单查询条件
                                    $where =
                                        [
                                            ['account_id', '=', $row['id']], //账户ID
                                            ['status', '=', 0], //订单状态
                                            ['truemoney', '=', sprintf("%.2f", $orderMoney)], //付款金额
                                            ['out_time', '>', time()],
                                        ];
                                    //查询是否存在该订单
                                    $orderrow = Db::name('ypay_order')->where($where)->order('id desc')->find();
                                }


                                //如果该订单存在则执行回调操作
                                if (!empty($orderrow)) {
                                    //更新账户更新时间
                                    Db::name('ypay_account')->where('id', $row['id'])->update(['update_time' => date('Y-m-d H:i:s', time())]);
                                    //存入该笔订单 支付宝订单号
                                    Db::name('ypay_order')->where('out_trade_no', $orderrow['out_trade_no'])->update(['alipay_order_no' => $order->alipay_order_no]);
                                    $url = Jialanshen::creat_callback($orderrow);
                                    get_curl($url['notify']);
                                }
                            }
                        }
                    }
                }
                break;
            case 'alipay_grmg':
                $cookie = base64_decode($row['cookie']);
                $BeatMoney = $core->getAlipayMoney($cookie);
                if ($BeatMoney == -1) {
                    $BeatMoney = $core->getAlipayMoney2($cookie);
                }

                // 如果不等于 -1则进入流程 等于-1则为掉线
                if ($BeatMoney != -1) {
                    //大于或者等于10个则进行区分
                    if ($row['count'] >= 10) {
                        //更新账户更新时间
                        Db::name('ypay_account')->where('id', $row['id'])->update(['update_time' => date('Y-m-d H:i:s', time() + rand(10, 25))]);
                    } else {
                        //更新账户更新时间
                        Db::name('ypay_account')->where('id', $row['id'])->update(['update_time' => date('Y-m-d H:i:s', time())]);
                    }

                    //金额不相同则进入流程
                    if ($row['money'] != $BeatMoney) {
                        Db::name('ypay_account')->where('id', $row['id'])->update(['money' => $BeatMoney]); //更新账户金额
                        $od_money = bcsub($BeatMoney, $row['money'], 2);
                        //每次构建前先清空
                        $where = array();
                        // 构建订单查询条件
                        $where =
                            [
                                ['status', '=', 0],
                                ['account_id', '=', $row['id']],
                                ['truemoney', '=', $od_money],
                                ['out_time', '>', time()],
                            ];

                        $order = Db::name('ypay_order')->where($where)->order('id desc')->lock(true)->find();
                        //如果该订单存在则执行回调操作
                        if (!empty($order)) {
                            $url = Jialanshen::creat_callback($order);
                            get_curl($url['notify']);
                        }
                    }
                } else {
                    self::lose_expire($row['user_id'], 'alipay', $row['id']);
                }
                break;
            case 'qqpay_wzq':
                //获取账户Coockie用来获取订单列表
                if (empty($row['cloud_id'])) {
                    $cookie = base64_decode($row['cookie']);
                } else {
                    $cookie = $core->Api_GetCookies($row['qq'], $row['cloud_id']);
                }
                $odlist = $core->GetOrder($row['qq'], $cookie);
                $odlist = preg_replace('/[\x00-\x1F\x80-\x9F]/u', '', trim($odlist));
                $arr = json_decode($odlist, true);
                //如果参数不为空则进入流程
                if (!empty($arr['records'][0])) {
                    //获取订单号/金额/支付时间
                    $param = $arr['records'][0];
                    $money = $param['price'] / 100; //金额
                    $sp_billno = $param['sp_billno']; //订单号
                    $pay_time = $param['pay_time'];

                    //订单号不相同则进入流程       
                    if ($sp_billno != $row['remark']) {
                        //更新金额和订单号
                        Db::name('ypay_account')->where('id', $row['id'])->update(['money' => $money, 'remark' => $sp_billno]);
                        //每次构建前先清空
                        $where = array();
                        // 构建订单查询条件
                        $where =
                            [
                                ['status', '=', 0],
                                ['account_id', '=', $row['id']],
                                ['truemoney', '=', $money],
                                ['out_time', '>', time()],
                            ];
                        $order = Db::name('ypay_order')->where($where)->order('id desc')->find();

                        //如果有订单且支付时间 大于 订单创建时间则回调
                        if (!empty($order) && (strtotime($pay_time) > strtotime($order['create_time']))) {
                            $url = Jialanshen::creat_callback($order);
                            get_curl($url['notify']);
                        }
                    }
                } else {
                    self::lose_expire($row['user_id'], 'QQ', $row['id']);
                }
                break;
            case 'wxpay_cloud':
                //获取订单信息  传递的参数分别为 GUid 和 云端地域 和通道标识
                $res = $core->getWechatOrder($row['wx_guid'], $row['cloud_id'], $row['code'], $row);
                //判断状态为200 type为1-MacV3 type为2-Uos
                if ($res['code'] == 200 && $res['type'] == 1) {
                    if ($res['getType'] == 'message') {
                        $msgArray = $res['data'];
                        if (!empty($msgArray->data->Result->AddMsgs)) {
                            foreach ($msgArray->data->Result->AddMsgs as $item) {
                                if ($item->MsgType == 49) {
                                    //获取收款金额
                                    $je = getSubstr($item->Content->String, "收款金额￥", "\n");
                                    //获取收款订单号
                                    $bz = getSubstr($item->Content->String, "收款方备注", "\n");
                                    //每次构建前先清空
                                    $where = array();
                                    //构建查询参数
                                    $where =
                                        [
                                            ['status', '=', 0],
                                            ['account_id', '=', $row['id']],
                                            ['out_trade_no', '=', $bz],
                                            ['truemoney', '=', $je],
                                            ['out_time', '>', time()],
                                        ];
                                    //查询订单
                                    $order = Db::name('ypay_order')->where($where)->find();
                                    if (!empty($order)) {
                                        $url = Jialanshen::creat_callback($order);
                                        get_curl($url['notify']);
                                    }
                                }
                            }
                        }
                    } else if ($res['getType'] == 'bill') {
                        //获取账单参数
                        $res = json_decode($res['data'], true);
                        $billArray = $res['data']['data_list'];
                        //每次构建前先清空
                        $where = array();
                        //账单参数不为空则执行流程
                        if (!empty($billArray)) {
                            foreach ($billArray as $item) {
                                $money = $item['fee'] / 100;
                                //构建查询参数
                                $where =
                                    [
                                        ['out_trade_no', '=', $item['remark']],
                                        ['status', '=', 0],
                                        ['account_id', '=', $row['id']],
                                        ['truemoney', '=', $money],
                                        ['out_time', '>', time()],
                                    ];
                                //查询是否存在该订单
                                $order = Db::name('ypay_order')->where($where)->find();

                                //存在则进行回调操作
                                if (!empty($order)) {
                                    $url = Jialanshen::creat_callback($order);
                                    get_curl($url['notify']);
                                }
                            }
                        }
                    }
                } else if ($res['code'] == 200 && $res['type'] == "Uos") {
                    if ($res['status'] == 0) {
                        self::lose_expire($row['user_id'], 'wxpay', $row['id']);
                    }
                    if (!empty($res['data'])) {
                        //每次构建前先清空
                        $where = array();
                        // 构建订单查询条件
                        $where =
                            [
                                ['status', '=', 0],
                                ['account_id', '=', $row['id']],
                                ['truemoney', '=', $res['data']['money']],
                                ['out_time', '>', time()],
                            ];
                        $order = Db::name('ypay_order')->where($where)->order('id desc')->find();
                        //存在则进行回调操作
                        if (!empty($order)) {
                            $url = Jialanshen::creat_callback($order);
                            get_curl($url['notify']);
                        }
                    }
                } else if ($res['code'] == 200 && $res['type'] == "clouds") {
                    $msgArray = $res['data'];
                    if (isset($msgArray->Data->AddMsgs)) {

                        foreach ($msgArray->Data->AddMsgs as $item) {
                            if ($item->MsgType == 49) {

                                //获取收款金额
                                $je = getSubstr($item->Content->string, "收款金额￥", "\n");
                                //获取收款订单号
                                $bz = getSubstr($item->Content->string, "收款方备注", "\n");
                                //每次构建前先清空
                                $where = array();
                                //构建查询参数
                                $where =
                                    [
                                        ['status', '=', 0],
                                        ['account_id', '=', $row['id']],
                                        ['out_trade_no', '=', $bz],
                                        ['truemoney', '=', $je],
                                        ['out_time', '>', time()],
                                    ];
                                //查询订单
                                $order = Db::name('ypay_order')->where($where)->find();
                                if (!empty($order)) {
                                    $url = Jialanshen::creat_callback($order);
                                    get_curl($url['notify']);
                                }
                            }
                        }
                    }
                }
                break;
            case 'wxpay_cloudzs':
                //获取订单信息  传递的参数分别为 GUid 和 云端地域 和通道标识
                $res = $core->getWechatOrder($row['wx_guid'], $row['cloud_id'], $row['code'], $row);
                //判断状态为200 type为1-MacV3 type为2-Uos
                if ($res['code'] == 200 && $res['type'] == 1) {
                    $msgArray = $res['data'];
                    if (!empty($msgArray->data->Result->AddMsgs)) {
                        foreach ($msgArray->data->Result->AddMsgs as $item) {
                            if ($item->MsgType == 49) {
                                $je = getSubstr($item->Content->String, "收款金额￥", "\n");
                                //每次构建前先清空
                                $where = array();
                                //构建查询参数
                                $where =
                                    [
                                        ['status', '=', 0],
                                        ['account_id', '=', $row['id']],
                                        ['truemoney', '=', $je],
                                        ['out_time', '>', time()],
                                    ];
                                //查询订单
                                $order = Db::name('ypay_order')->where($where)->find();
                                if (!empty($order)) {
                                    $url = Jialanshen::creat_callback($order);
                                    get_curl($url['notify']);
                                }
                            }
                        }
                    }
                } else if ($res['code'] == 200 && $res['type'] == "Uos") {
                    if ($res['status'] == 0) {
                        self::lose_expire($row['user_id'], 'wxpay', $row['id']);
                    }
                    if (!empty($res['data'])) {
                        //每次构建前先清空
                        $where = array();
                        // 构建订单查询条件
                        $where =
                            [
                                ['status', '=', 0],
                                ['account_id', '=', $row['id']],
                                ['truemoney', '=', $res['data']['money']],
                                ['out_time', '>', time()],
                            ];
                        $order = Db::name('ypay_order')->where($where)->order('id desc')->find();
                        //存在则进行回调操作
                        if (!empty($order)) {
                            $url = Jialanshen::creat_callback($order);
                            get_curl($url['notify']);
                        }
                    }
                }else if ($res['code'] == 200 && $res['type'] == "clouds") {
                    $msgArray = $res['data'];
                    if (isset($msgArray->Data->AddMsgs)) {

                        foreach ($msgArray->Data->AddMsgs as $item) {
                            if ($item->MsgType == 49) {

                                //获取收款金额
                                $je = getSubstr($item->Content->string, "收款金额￥", "\n");
                                //每次构建前先清空
                                $where = array();
                                //构建查询参数
                                $where =
                                    [
                                        ['status', '=', 0],
                                        ['account_id', '=', $row['id']],
                                        ['truemoney', '=', $je],
                                        ['out_time', '>', time()],
                                    ];
                                //查询订单
                                $order = Db::name('ypay_order')->where($where)->find();
                                if (!empty($order)) {
                                    $url = Jialanshen::creat_callback($order);
                                    get_curl($url['notify']);
                                }
                            }
                        }
                    }
                }
                //更新账户更新时间
                Db::name('ypay_account')->where('id', $row['id'])->update(['update_time' => date('Y-m-d H:i:s', time())]);
                break;
            case 'wxpay_jym_cloud':
                //获取订单信息  传递的参数分别为 GUid 和 云端地域 和通道标识
                $res = $core->getWechatOrder($row['wx_guid'], $row['cloud_id'], $row['code'], $row);
                if ($res['code'] == 200 && $res['type'] == "clouds") {
                    $msgArray = $res['data'];
                    if (isset($msgArray->Data->AddMsgs)) {

                        foreach ($msgArray->Data->AddMsgs as $item) {
                            if ($item->MsgType == 49) {

                                //获取收款金额
                                $je = trim(getSubstr($item->Content->string, "收款金额￥", "\n"));
                                //获取收款订单号
                                $bz1 = getSubstr($item->Content->string, "收款项", " x1");
                                $bz2 = getSubstr($item->Content->string, "收款说明", "收款项");
                                $bz = trim($bz1 . $bz2);
                                //每次构建前先清空
                                $where = array();
                                //构建查询参数
                                $where =
                                    [
                                        ['status', '=', 0],
                                        ['account_id', '=', $row['id']],
                                        ['out_trade_no', '=', $bz],
                                        ['truemoney', '=', $je],
                                        ['out_time', '>', time()],
                                    ];
                                //查询订单
                                $order = Db::name('ypay_order')->where($where)->find();
                                if (!empty($order)) {
                                    $url = Jialanshen::creat_callback($order);
                                    get_curl($url['notify']);
                                }
                            }
                        }
                    }
                }
                break;
            case 'wxpay_skd':
                $mch = $core->WXJSLogin_mch($row['cloud_id'], $row['wx_guid']);
                $mch = $mch['mch'];
                $smxx = $core->WXJSLogin_shop($row['cloud_id'], $row['wx_guid'], $mch);
                $sid = $smxx['sid'];
                $account_id = $smxx['account_id'];
                $res = $core->getSkdBill($sid, $account_id);
                $data = json_decode($res, true);
                //账单参数不为空则执行流程
                if (!empty($data["data"]['receipt'])) {
                    foreach ($data["data"]['receipt'] as $item) {
                        //订单状态成功才支持回调
                        if ($item['state'] == "success") {
                            //每次构建前先清空
                            $where = array();
                            $where =
                                [
                                    ['trade_no', '=', $item['remark']],
                                    ['status', '=', 0],
                                    ['account_id', '=', $row['id']],
                                    ['out_time', '>', time()],
                                ];
                            //查询是否存在该订单
                            $order = Db::name('ypay_order')->where($where)->find();
                            //存在则进行回调操作
                            if (!empty($order)) {
                                $url = Jialanshen::creat_callback($order);
                                get_curl($url['notify']);
                            }
                        }
                    }
                }
                //更新账户更新时间
                Db::name('ypay_account')->where('id', $row['id'])->update(['update_time' => date('Y-m-d H:i:s', time())]);
                break;
            case 'qqpay_cloud':

                //每次构建前先清空
                $where = array();
                //订单查询参数
                $where =
                    [
                        ['status', '=', 0],
                        ['account_id', '=', $row['id']],
                        ['out_time', '>', time()],
                    ];
                //获取订单数量
                $count = Db::name('ypay_order')->where($where)->count();

                //有订单则执行
                if ($count > 0) {
                    //获取账户Coockie用来获取订单列表
                    $cookie = $core->Api_GetCookies($row['qq'], $row['cloud_id']);
                    $odlist = $core->GetOrder($row['qq'], $cookie);
                    $odlist = preg_replace('/[\x00-\x1F\x80-\x9F]/u', '', trim($odlist));
                    $arr = json_decode($odlist, true);

                    //如果参数不为空则进入流程
                    if (!empty($arr['records'][0])) {
                        //获取订单号/金额/支付时间
                        $param = $arr['records'][0];
                        $money = $param['price'] / 100; //金额
                        $sp_billno = $param['sp_billno']; //订单号
                        $pay_time = $param['pay_time'];

                        //订单号不相同则进入流程       
                        if ($sp_billno != $row['remark']) {
                            //更新金额和订单号
                            Db::name('ypay_account')->where('id', $row['id'])->update(['money' => $money, 'remark' => $sp_billno]);
                            //每次构建前先清空
                            $where = array();
                            // 构建订单查询条件
                            $where =
                                [
                                    ['status', '=', 0],
                                    ['account_id', '=', $row['id']],
                                    ['truemoney', '=', $money],
                                    ['out_time', '>', time()],
                                ];
                            $order = Db::name('ypay_order')->where($where)->order('id desc')->find();

                            //如果有订单且支付时间 大于 订单创建时间则回调
                            if (!empty($order) && (strtotime($pay_time) > strtotime($order['create_time']))) {
                                $url = Jialanshen::creat_callback($order);
                                get_curl($url['notify']);
                            }
                        }
                    } else {
                        self::lose_expire($row['user_id'], 'QQ', $row['id']);
                    }
                }
                break;
            case 'qqpay_mg':
                //获取账户Coockie用来获取订单列表
                $cookie = base64_decode($row['cookie']);

                $BeatMoney = $core->getQQPayMoney($cookie, $row['qq']);

                // 如果不等于 -1则进入流程 等于-1则为掉线
                if ($BeatMoney != -1) {
                    //大于10个则进行区分
                    if ($row['count'] > 10) {
                        //更新账户更新时间
                        Db::name('ypay_account')->where('id', $row['id'])->update(['update_time' => date('Y-m-d H:i:s', time() + rand(10, 25))]);
                    } else {
                        //更新账户更新时间
                        Db::name('ypay_account')->where('id', $row['id'])->update(['update_time' => date('Y-m-d H:i:s', time())]);
                    }
                    //金额不相同则进入流程
                    if ($row['money'] != $BeatMoney) {
                        Db::name('ypay_account')->where('id', $row['id'])->update(['money' => $BeatMoney]); //更新账户金额
                        $od_money = bcsub($BeatMoney, $row['money'], 2);
                        //每次构建前先清空
                        $where = array();
                        // 构建订单查询条件
                        $where =
                            [
                                ['status', '=', 0],
                                ['account_id', '=', $row['id']],
                                ['truemoney', '=', $od_money],
                                ['out_time', '>', time()],
                            ];

                        $order = Db::name('ypay_order')->where($where)->order('id desc')->lock(true)->find();

                        //如果该订单存在则执行回调操作
                        if (!empty($order)) {
                            $url = Jialanshen::creat_callback($order);
                            get_curl($url['notify']);
                        }
                    }
                } else {
                    self::lose_expire($row['user_id'], 'QQ', $row['id']);
                }
                break;
            case 'lkl_wxpay':
                //每次构建前先清空
                $where = array();
                // 构建订单查询条件
                $where =
                    [
                        ['status', '=', 0],
                        ['account_id', '=', $row['id']],
                        ['out_time', '>', time()],
                    ];

                //获取订单并进行循环监听
                $order = Db::name('ypay_order')->where($where)->order('id desc')->select();

                foreach ($order as $key => $value) {
                    self::lkl($value['out_trade_no'], $row['id']);
                }

                break;
            case 'lkl_alipay':
                //每次构建前先清空
                $where = array();
                // 构建订单查询条件
                $where =
                    [
                        ['status', '=', 0],
                        ['account_id', '=', $row['id']],
                        ['out_time', '>', time()],
                    ];

                //获取订单并进行循环监听
                $order = Db::name('ypay_order')->where($where)->order('id desc')->select();

                foreach ($order as $key => $value) {
                    self::lkl($value['out_trade_no'], $row['id']);
                }
                break;
            case 'usdt':
                $ret = get_curl('https://apilist.tronscan.org/api/contract/events?address=' . $row['wxname'] . '&start=0&limit=50');
                $json = json_decode($ret, true);
                foreach ($json['data'] as $la) {
                    if ($la['transferToAddress'] == $row['wxname'] && $la['amount'] > 1000000) {
                        $result[] = [
                            'addtime' => date("Y-m-d H:i:s", $la['timestamp'] / 1000),
                            'time' => $la['timestamp'] / 1000,
                            'money' => round($la['amount'] / 1000000, 2),
                            't_url' => $la['transferFromAddress']
                        ];
                    }
                }
                foreach ($result as $item) {
                    //每次构建前先清空
                    $where = array();
                    // 构建订单查询条件
                    $where =
                        [
                            ['status', '=', 0],
                            ['type', '=', 'usdt'],
                            ['account_id', '=', $row['id']],
                            ['truemoney', '=', $item['money']],
                            ['out_time', '>', $item['time']],
                            ['create_time', '<', $item['addtime']],
                        ];

                    $order = Db::name('ypay_order')->where($where)->order('id desc')->lock(true)->find();

                    //如果该订单存在则执行回调操作
                    if (!empty($order)) {
                        $url = Jialanshen::creat_callback($order);
                        get_curl($url['notify']);
                    }
                }

                break;
        }
    }

    /**
     * @拉卡拉订单检测
     * @param [type] $id
     *
     * @return void
     */
    public static function lkl($out_trade_no, $account_id)
    {
        $account = Db::name('ypay_account')->where('id', $account_id)->where('status', 1)->where('is_status', 1)->find();
        //当前时间
        $atime = time();
        // 格式化当前时间
        $dtime = date("YmdHis", $atime);
        $ch = curl_init();

        $data = [
            "outOrgCode" => "37001010012",
            "outSysCode" => "MOBILE_PLATFORM",
            "reqTime" => $dtime,
            "version" => "3.0",
            "signType" => null,
            "sign" => null,
            "reqData" => [
                "merchantNo" => $account['wxname'],
                "termNo" => $account['zfb_pid'],
                "outTradeNo" => $out_trade_no,
                "tradeNo" => null,
                "outOrderNo" => null,
                "outOrderSource" => null
            ]
        ];

        $body = json_encode($data);

        $headers = [
            "Authorization: " . $account['remark'],
            "X-Client-PV: lKL_APP",
            "Content-Type: application/json;charset=utf-8",
            "Content-Length: " . strlen($body),
            "Host: wallet.lakala.com",
            "Accept: */*"
        ];

        curl_setopt_array($ch, [
            CURLOPT_URL => "https://wallet.lakala.com/m/a/transv3/query",
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        if (empty($response)) {
            exit("异常错误，请联系开发者");
        }

        // 处理返回响应
        $response_data = json_decode($response, true);
        if ($response_data['code'] === "BBS00000") {
            if ($response_data['respData']['tradeStateDesc'] === "交易成功") {
                $order = Db::name('ypay_order')->where('account_id', $account_id)->where('out_trade_no', $out_trade_no)->where('status', 0)->where('out_time', '>', time())->order('id desc')->find();
                if (!empty($order)) {
                    $url = Jialanshen::creat_callback($order);
                    get_curl($url['notify']);
                }
            }
        }
    }

    /**
     * @会员到期
     * @param [type] $id
     *
     * @return void
     */
    public function vip_expire()
    {
        $data = getConfig();
        $user = Db::name('ypay_user')->select(); //获取是会员的用户信息
        foreach ($user as $key => $value) {
            //更改到期用户会员信息
            if (!empty($value['vip_time'])) {
                if (time() > strtotime($value['vip_time'])) {
                    try {
                        Db::name('ypay_user')->where('id', $value['id'])->update(['vip_id' => null, 'vip_time' => null, 'feilv' => null]);
                    } catch (\Exception $e) {
                        echo '操作失败' . $e->getMessage();
                    }
                }
            }
        }

        if ($data['is_vip_expire'] == 1) {
            $user = Db::name('ypay_user')->where('vip_time', '<>', 'null')->select(); //获取是会员的用户信息
            foreach ($user as $key => $value) {
                //判断是否快到期提醒
                $date_1 = explode("-", $value['vip_time']);
                $date_2 = explode("-", date('Y-m-d H:i:s'));
                $d1 = mktime(0, 0, 0, intval($date_1[1]), intval($date_1[2]), intval($date_1[0]));
                $d2 = mktime(0, 0, 0, intval($date_2[1]), intval($date_2[2]), intval($date_2[0]));
                $day = (int)round(($d1 - $d2) / 3600 / 24);
                if ($data['vip_expire'] >= $day) {
                    if ($day == 0) {
                        $temp = '0天';
                    } else {
                        $temp = $day . '天';
                    }
                    $diy = $data['diy_vipTemp']; //获取自定义参数

                    $array = ["[sitename]", "[day]"]; //定义自定义参数数组

                    foreach ($array as $val) {
                        $str = trim($val, '[');
                        $str = rtrim($str, ']');
                        if ($val == '[sitename]') {
                            $data[$str] = $data['sitename'];
                        }
                        if ($val == "[day]") {
                            $data[$str] = $temp;
                        }

                        $diy = str_replace($val, $data[$str], $diy);
                    }

                    //邮箱不能为空
                    if (!empty($value['email'])) {
                        Mail::go($value['email'], $data['sitename'] . '- 会员到期提醒', $diy);
                    }
                }
            }
        }
    }


    /**
     * @数据清理
     * @return void
     */
    public function dataClear()
    {
        $data = getConfig(); //获取系统配置参数
        // 获取当前日期的指定天数前得日期
        $daysAgo = date('Y-m-d', strtotime('-' . $data['dataClearDays'] . ' days'));

        if ($data['is_dataClear']) {
            if (strpos($data['diy_dataClear'], 'order') !== false) {
                $result = Db::name('ypay_order')->whereTime('create_time', '<', $daysAgo)->delete();
            }
            if (strpos($data['diy_dataClear'], 'recharge') !== false) {
                $result = Db::name('ypay_recharge')->whereTime('create_time', '<', $daysAgo)->delete();
            }
            if (strpos($data['diy_dataClear'], 'adminLog') !== false) {
                $result = Db::name('admin_admin_log')->whereTime('create_time', '<', $daysAgo)->delete();
            }
            if (strpos($data['diy_dataClear'], 'userLog') !== false) {
                $result = Db::name('admin_front_log')->whereTime('create_time', '<', $daysAgo)->delete();
            }
        } else {
            echo '请先开启自动清理数据';
        }
    }

    /**
     * @掉线通知
     * @param [type] $id $type $channelID
     *
     * @return void
     */
    public function lose_expire($id, $type, $channelID)
    {
        Db::name('ypay_account')->where('id', $channelID)->update(['status' => 0, 'create_time' => date('Y-m-d H:i:s', time())]); //掉线
        $userinfo = Db::name('ypay_user')->find($id); //获取用户信息
        $basic = basic::where('user_id', $id)->find(); //获取用户配置参数
        //调用通知方法 1.用户信息 2.用户配置参数 3.通道ID 4.通道类型
        notice::lose_tips($userinfo, $basic, $channelID, $type);
    }

    /**
     * @执行失败
     * @param [type] $data
     *
     * @return void
     */
    public function failed($data)
    {
        // 记录日志
        record_log($data, 'job_error');
    }
}
