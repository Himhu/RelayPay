<?php

declare(strict_types=1);

namespace app\common\model;

use think\Model;
use think\model\concern\SoftDelete;
use think\facade\Cache;

class YpayAccount extends Model
{
    use SoftDelete;
    protected $deleteTime = false;
    // 获取列表
    public static function getList()
    {
        $where = [];
        $limit = input('get.limit');
        $limit = max(1, min((int)($limit ?: 20), 100));

        //按通道标识查找
        if ($code = input("code")) {
            $where[] = ["code", "like", "%" . $code . "%"];
        }
        //按通道类型查找
        if ($type = input("type")) {
            $where[] = ["type", "like", "%" . $type . "%"];
        }
        //按会员ID查找
        if ($user_id = input("user_id")) {
            $where[] = ["user_id", "like", "%" . $user_id . "%"];
        }
        $list = self::field('id,code,type,user_id,zfb_pid,wxname,wx_guid,qq,status,is_status,memo,cloud_id,create_time')
            ->order('id', 'desc')
            ->where($where)
            ->paginate($limit);

        $items = $list->items();
        if (!empty($items)) {
            $accountIds = array_values(array_unique(array_column($items, 'id')));
            $codes = array_values(array_unique(array_filter(array_column($items, 'code'))));
            $types = array_values(array_unique(array_filter(array_column($items, 'type'))));

            $paymentMap = [];
            if (!empty($types)) {
                $paymentCacheKey = 'admin:payment_map:' . md5(implode(',', $types));
                $paymentMap = Cache::get($paymentCacheKey, []);
                if (empty($paymentMap)) {
                    $paymentMap = YpayPayment::whereIn('type', $types)->column('name', 'type');
                    Cache::set($paymentCacheKey, $paymentMap, 300);
                }
            }

            $channelMap = [];
            if (!empty($codes)) {
                $channelCacheKey = 'admin:channel_map:' . md5(implode(',', $codes));
                $channelMap = Cache::get($channelCacheKey, []);
                if (empty($channelMap)) {
                    $channelMap = AdminChannel::whereIn('code', $codes)->column('name', 'code');
                    Cache::set($channelCacheKey, $channelMap, 300);
                }
            }

            $succCounts = [];
            $succSums = [];
            if (!empty($accountIds)) {
                $statsCacheKey = 'admin:account_stats:' . md5(implode(',', $accountIds));
                $stats = Cache::get($statsCacheKey);
                if (!is_array($stats)) {
                    $stats = [
                        'counts' => YpayOrder::where('status', 1)
                            ->whereIn('account_id', $accountIds)
                            ->group('account_id')
                            ->column('COUNT(*)', 'account_id'),
                        'sums' => YpayOrder::where('status', 1)
                            ->whereIn('account_id', $accountIds)
                            ->group('account_id')
                            ->column('SUM(truemoney)', 'account_id'),
                    ];
                    Cache::set($statsCacheKey, $stats, 60);
                }
                $succCounts = $stats['counts'] ?? [];
                $succSums = $stats['sums'] ?? [];
            }

            foreach ($items as &$item) {
                $item['succcount'] = $succCounts[$item['id']] ?? 0;
                $item['succprice'] = $succSums[$item['id']] ?? 0;
                $item['code_name'] = $channelMap[$item['code']] ?? '';

                switch ($item['type']) {
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
                $typeName = $paymentMap[$item['type']] ?? $item['type'];
                $item['type_name'] = '<span class="badge rounded-pill ' . $color . '">' . $typeName . '</span>';
            }
            unset($item);
        }
        return ['code' => 0, 'data' => $items, 'extend' => ['count' => $list->total(), 'limit' => $limit]];
    }

    public static function getUserList($user_id)
    {
        $where[] = ["user_id", '=', $user_id];
        $limit = self::where($where)->count();
        $list = self::order('id', 'desc')->where($where)->paginate((int)$limit);
        foreach ($list->items() as $item) {

            // 今日统计相关数据
            // 定义今日查询的公共条件，根据账户 ID 进行筛选
            $todayCondition = ['account_id' => $item['id']];
            // 计算今日成交金额，筛选出创建时间为今日且订单状态为已成交（status = 1）的订单，对真实金额进行求和
            $item['today_yes_money'] = YpayOrder::where($todayCondition)->whereDay('create_time')->where('status', 1)->sum('truemoney');
            // 计算今日未成交金额，筛选出创建时间为今日且订单状态为未成交（status = 0）的订单，对真实金额进行求和
            $item['today_no_money'] = YpayOrder::where($todayCondition)->whereDay('create_time')->where('status', 0)->sum('truemoney');
            // 计算今日成交订单数量，筛选出创建时间为今日且订单状态为已成交（status = 1）的订单，统计其数量
            $item['today_yes_order'] = YpayOrder::where($todayCondition)->whereDay('create_time')->where('status', 1)->count();
            // 计算今日未成交订单数量，筛选出创建时间为今日且订单状态为未成交（status = 0）的订单，统计其数量
            $item['today_no_order'] = YpayOrder::where($todayCondition)->whereDay('create_time')->where('status', 0)->count();
            // 计算今日总订单数量，筛选出创建时间为今日的所有订单，统计其数量
            $item['today_all_order'] = YpayOrder::where($todayCondition)->whereDay('create_time')->count();
            // 计算今日成交率，若今日总订单数量不为 0，则计算成交订单数量占总订单数量的百分比并保留两位小数；若为 0，则成交率设为 0
            $item['today_yes'] = $item['today_all_order'] ? round(($item['today_yes_order'] / $item['today_all_order']) * 100, 2) : 0;

            // 昨日统计相关数据
            // 定义昨日查询的公共条件，根据账户 ID 进行筛选
            $yesterdayCondition = ['account_id' => $item['id']];
            // 计算昨日成交金额，筛选出创建时间为昨日且订单状态为已成交（status = 1）的订单，对真实金额进行求和
            $item['yesterday_yes_money'] = YpayOrder::where($yesterdayCondition)->whereDay('create_time', 'yesterday')->where('status', 1)->sum('truemoney');
            // 计算昨日未成交金额，筛选出创建时间为昨日且订单状态为未成交（status = 0）的订单，对真实金额进行求和
            $item['yesterday_no_money'] = YpayOrder::where($yesterdayCondition)->whereDay('create_time', 'yesterday')->where('status', 0)->sum('truemoney');
            // 计算昨日成交订单数量，筛选出创建时间为昨日且订单状态为已成交（status = 1）的订单，统计其数量
            $item['yesterday_yes_order'] = YpayOrder::where($yesterdayCondition)->whereDay('create_time', 'yesterday')->where('status', 1)->count();
            // 计算昨日未成交订单数量，筛选出创建时间为昨日且订单状态为未成交（status = 0）的订单，统计其数量
            $item['yesterday_no_order'] = YpayOrder::where($yesterdayCondition)->whereDay('create_time', 'yesterday')->where('status', 0)->count();
            // 计算昨日总订单数量，筛选出创建时间为昨日的所有订单，统计其数量
            $item['yesterday_all_order'] = YpayOrder::where($yesterdayCondition)->whereDay('create_time', 'yesterday')->count();
            // 计算昨日成交率，若昨日总订单数量不为 0，则计算成交订单数量占总订单数量的百分比并保留两位小数；若为 0，则成交率设为 0
            $item['yesterday_yes'] = $item['yesterday_all_order'] ? round(($item['yesterday_yes_order'] / $item['yesterday_all_order']) * 100, 2) : 0;

            // 总统计相关数据
            // 定义总查询的公共条件，根据账户 ID 进行筛选
            $allCondition = ['account_id' => $item['id']];
            // 计算总成交金额，筛选出订单状态为已成交（status = 1）的所有订单，对真实金额进行求和
            $item['all_yes_money'] = YpayOrder::where($allCondition)->where('status', 1)->sum('truemoney');
            // 计算总未成交金额，筛选出订单状态为未成交（status = 0）的所有订单，对真实金额进行求和
            $item['all_no_money'] = YpayOrder::where($allCondition)->where('status', 0)->sum('truemoney');
            // 计算总成交订单数量，筛选出订单状态为已成交（status = 1）的所有订单，统计其数量
            $item['all_yes_order'] = YpayOrder::where($allCondition)->where('status', 1)->count();
            // 计算总未成交订单数量，筛选出订单状态为未成交（status = 0）的所有订单，统计其数量
            $item['all_no_order'] = YpayOrder::where($allCondition)->where('status', 0)->count();
            // 计算总订单数量，筛选出所有订单，统计其数量
            $item['all_order'] = YpayOrder::where($allCondition)->count();
            // 计算总成交率，若总订单数量不为 0，则计算成交订单数量占总订单数量的百分比并保留两位小数；若为 0，则总成交率设为 0
            $item['all_yes'] = $item['all_order'] ? round(($item['all_yes_order'] / $item['all_order']) * 100, 2) : 0;
            $item['succcount'] = YpayOrder::where('status', 1)->where('account_id', $item['id'])->count();
            $item['code_name'] = AdminChannel::where('code', $item['code'])->field('name')->find()['name'];
            $item['succprice'] = YpayOrder::where('status', 1)->where('account_id', $item['id'])->sum('truemoney');

            switch ($item['type']) {
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
                    # code...
                    break;
            }

            $item['type_name'] = '<span class="badge rounded-pill ' . $color . '">' . YpayPayment::where('type', $item['type'])->find()['name'] . '</span>';

            //判断通道是否在线
            if ($item['status'] == 1) {

                // 定义两个时间戳
                $temp_time = date('Y-m-d H:i:s', time());

                //对部分通道进行无创建时间处理
                if (empty($item['create_time'])) {
                    self::where('id', $item['id'])->update(['create_time' => $temp_time]);
                    $start = strtotime($temp_time);
                } else {
                    $start = strtotime($item['create_time']);
                }

                $end = strtotime(date('Y-m-d H:i:s', time()));

                // 计算时间差
                $diff = $end - $start;

                // 计算天、小时、分钟
                $days = floor($diff / (60 * 60 * 24));
                $hours = floor(($diff - $days * 60 * 60 * 24) / (60 * 60));
                $minutes = floor(($diff - $days * 60 * 60 * 24 - $hours * 60 * 60) / 60);

                $item['online_time'] = '<p style="color:red;">' . $days . "天" . $hours . "小时" . $minutes . "分钟" . '</p>';
            } else {
                $item['online_time'] = '<p style="color:black;">已掉线</p>';
            }

            if ($item['code'] == 'wxpay_cloud' || $item['code'] == 'wxpay_cloudzs' || $item['code'] == 'wxpay_skd' || $item['code'] == 'qqpay_cloud' || $item['code'] == 'qqpay_wzq') {
                $cloud = YpayCloud::where('id', $item['cloud_id'])->find();

                if (!empty($cloud)) {
                    $item['cloud_name'] = $cloud['name'];
                } else {
                    if ($item['code'] == 'qqpay_wzq') {
                        $item['cloud_name'] = '本地';
                    } else {
                        $item['cloud_name'] = '云端已失效';
                    }
                }
            }
        }

        return ['code' => 0, 'data' => $list->items(), 'extend' => ['count' => $list->total(), 'limit' => $limit]];
    }

    public static function getUserInfo($id)
    {
        $item = self::find($id);
        return $item;
    }
}
