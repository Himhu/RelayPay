<?php

declare(strict_types=1);

namespace app\common\model;

use think\Model;

class YpayOrder extends Model
{

    // 获取列表
    public static function getList()
    {
        $where = [];
        $limit = input('get.limit');
        $limit = max(1, min((int)($limit ?: 20), 100));

        //按状态查询
        $status = input("status");
        if ($status != "" && $status != null) {
            $where[] = ["status", "=", $status];
        }

        //按商品名查找
        if ($name = input("name")) {
            $where[] = ["name", "like", "%" . $name . "%"];
        }

        //按账号ID查找
        if ($account_id = input("account_id")) {
            $where[] = ["account_id", "like", "%" . $account_id . "%"];
        }
        //按商户单号查找
        if ($trade_no = input("trade_no")) {
            $where[] = ["trade_no", "like", "%" . $trade_no . "%"];
        }
        //按本地单号查找
        if ($out_trade_no = input("out_trade_no")) {
            $where[] = ["out_trade_no", "like", "%" . $out_trade_no . "%"];
        }
        //按会员ID查找
        if ($user_id = input("user_id")) {
            $where[] = ["user_id", "like", "%" . $user_id . "%"];
        }

        //按商品名查找
        if ($truemoney = input("truemoney")) {
            $where[] = ["truemoney", "like", "%" . $truemoney . "%"];
        }

        //按创建时间查找
        $start = input("get.create_time-start");
        $end = input("get.create_time-end");
        if ($start && $end) {
            $where[] = ["create_time", "between", [$start, date("Y-m-d", strtotime("$end +1 day"))]];
        }
        $list = self::field('id,out_trade_no,trade_no,user_id,name,money,truemoney,feilvmoney,create_time,type,status,end_time,ip')
            ->order('id', 'desc')
            ->where($where)
            ->paginate($limit);
        $items = $list->items();
        foreach ($items as $item) {
            $item['truemoney'] = $item['type'] == "usdt" ?  $item['truemoney'] . "Usdt" : $item['truemoney'];
        }
        return ['code' => 0, 'data' => $items, 'extend' => ['count' => $list->total(), 'limit' => $limit]];
    }

    public static function getUserList($user_id)
    {
        $where = [];
        //按会员ID查找
        $where[] = ["user_id", "=", $user_id];
        $list = self::order('id', 'desc')->where($where)->paginate((int)getConfig()['orderDisplay']);
        foreach ($list->items() as $item) {
            $item['truemoney'] = $item['type'] == "usdt" ?  $item['truemoney'] . "Usdt" : $item['truemoney'];
            //判断是转接通道订单还是本地通道订单
            if ($item['pay_type'] == 1) {
                $account = YpayAccount::where('id', $item['account_id'])->find();
                if (isset($account['code']) && !empty($account)) {
                    $channel = AdminChannel::where('code', $account['code'])->find();
                    $type = $channel['type'];
                    if ($channel !== null) {
                        $item['channel_name'] = $channel['name'];
                    } else {
                        $item['channel_name'] = '未知渠道';
                    }
                } else {
                    $item['channel_name'] = '未知渠道';
                    $type = 'other';
                }

                switch ($type) {
                    case 'wxpay':
                        $color = 'bg-label-success';
                        break;
                    case 'alipay':
                        $color = 'bg-label-info';
                        break;
                    case 'qqpay':
                        $color = 'bg-label-danger';
                        break;
                    case 'usdt':
                        $color = 'bg-label-success';
                        break;
                    default:
                        $color = 'bg-label-info';
                        break;
                }

                $item['type_name'] = '<span class="badge rounded-pill ' . $color . '">' . $item['channel_name'] . '</span>';
            } else {
                $account = YpayPaylist::where('id', $item['account_id'])->find();
                if (isset($account['type'])) {
                    $item['channel_name'] = '易支付';
                } else {
                    $item['channel_name'] = '未知渠道';
                }

                $item['type_name'] = '<span class="badge rounded-pill bg-label-info">' . $item['channel_name'] . '</span>';
            }
        }
        return ['code' => 0, 'data' => $list->items(), 'extend' => ['count' => $list->total(), 'limit' => 10]];
    }
}
