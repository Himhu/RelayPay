<?php
declare (strict_types = 1);

namespace app\common\model;

use think\Model;
use think\model\concern\SoftDelete;
use app\common\model\YpayOrder;
use app\common\model\AdminFrontLog;
use app\common\model\YpayVip;
use think\facade\Cache;

class YpayUser extends Model
{
    use SoftDelete;
     protected $deleteTime = false;
    // 获取列表
    public static function getList()
    {
        $where = [];
        $limit = input('get.limit');
        $limit = max(1, min((int)($limit ?: 20), 100));
        
               //按用户ID查找
               if ($id = input("id")) {
                   $where[] = ["id", "like", "%" . $id . "%"];
               }
               //按用户名查找
               if ($username = input("username")) {
                   $where[] = ["username", "like", "%" . $username . "%"];
               }
               //按邮箱查找
               if ($email = input("email")) {
                   $where[] = ["email", "like", "%" . $email . "%"];
               }
               //按手机号查找
               if ($mobile = input("mobile")) {
                   $where[] = ["mobile", "like", "%" . $mobile . "%"];
               }
        $list = self::field('id,username,email,money,vip_id,feilv,is_frozen,create_time,is_realName')
            ->order('id','desc')
            ->where($where)
            ->paginate($limit);
        $items = $list->items();
        if (!empty($items)) {
            $userIds = array_map(function ($item) {
                return $item['id'];
            }, $items);
            $vipIds = array_filter(array_map(function ($item) {
                return $item['vip_id'] ?? null;
            }, $items));

            $totalMoney = [];
            $yesterdayMoney = [];
            $todayMoney = [];
            if (!empty($userIds)) {
                $statsCacheKey = 'admin:user_stats:' . md5(implode(',', $userIds));
                $stats = Cache::get($statsCacheKey);
                if (!is_array($stats)) {
                    $stats = [
                        'total' => YpayOrder::where('status', 1)
                            ->whereIn('user_id', $userIds)
                            ->group('user_id')
                            ->column('SUM(truemoney)', 'user_id'),
                        'yesterday' => YpayOrder::where('status', 1)
                            ->whereIn('user_id', $userIds)
                            ->whereTime('create_time', 'yesterday')
                            ->group('user_id')
                            ->column('SUM(truemoney)', 'user_id'),
                        'today' => YpayOrder::where('status', 1)
                            ->whereIn('user_id', $userIds)
                            ->whereDay('create_time')
                            ->group('user_id')
                            ->column('SUM(truemoney)', 'user_id'),
                    ];
                    Cache::set($statsCacheKey, $stats, 60);
                }
                $totalMoney = $stats['total'] ?? [];
                $yesterdayMoney = $stats['yesterday'] ?? [];
                $todayMoney = $stats['today'] ?? [];
            }

            $vipMap = [];
            if (!empty($vipIds)) {
                $vipCacheKey = 'admin:vip_map:' . md5(implode(',', $vipIds));
                $vipMap = Cache::get($vipCacheKey, []);
                if (empty($vipMap)) {
                    $vipMap = YpayVip::whereIn('id', $vipIds)
                        ->where('status', 1)
                        ->column('name', 'id');
                    Cache::set($vipCacheKey, $vipMap, 300);
                }
            }

            $loginMap = [];
            if (!empty($userIds)) {
                $loginCacheKey = 'admin:user_login:' . md5(implode(',', $userIds));
                $loginMap = Cache::get($loginCacheKey, []);
                if (empty($loginMap)) {
                    $latestLogIds = AdminFrontLog::whereIn('uid', $userIds)
                        ->where('type', 0)
                        ->group('uid')
                        ->field('MAX(id) as id')
                        ->column('id');
                    if (!empty($latestLogIds)) {
                        $logs = AdminFrontLog::field('uid,ip,create_time')
                            ->whereIn('id', $latestLogIds)
                            ->select()
                            ->toArray();
                        foreach ($logs as $log) {
                            $loginMap[$log['uid']] = $log;
                        }
                    }
                    Cache::set($loginCacheKey, $loginMap, 120);
                }
            }

            $ipCache = [];
            $list->each(function ($item) use ($totalMoney, $yesterdayMoney, $todayMoney, $vipMap, $loginMap, &$ipCache) {
                $id = $item['id'];
                $item['total_money'] = $totalMoney[$id] ?? 0;
                $item['yesterday_money'] = $yesterdayMoney[$id] ?? 0;
                $item['today_money'] = $todayMoney[$id] ?? 0;

                if (!empty($item['vip_id'])) {
                    if (isset($vipMap[$item['vip_id']])) {
                        $item['vip'] = $vipMap[$item['vip_id']];
                    } else {
                        $item['vip'] = '该会员套餐已关闭';
                    }
                } else {
                    $item['vip'] = '未开通会员';
                }

                if (isset($loginMap[$id])) {
                    $log = $loginMap[$id];
                    $item['login_time'] = $log['create_time'];
                    $item['login_ip'] = $log['ip'];
                    if (!empty($log['ip'])) {
                        if (!array_key_exists($log['ip'], $ipCache)) {
                            $ipCache[$log['ip']] = get_ip_city($log['ip']);
                        }
                        $item['ip_city'] = $ipCache[$log['ip']];
                    }
                }

                return $item;
            });
        }
        return ['code'=>0,'data'=>$list->items(),'extend'=>['count' => $list->total(), 'limit' => $limit]];
    }
    
    public static function money($money, $user_id, $memo)
    {
        $user = self::find($user_id);
        if ($user && $money != 0) {
            $before = $user->money;
            //$after = $user->money + $money;
            $after = function_exists('bcadd') ? bcadd($user->money, $money, 2) : $user->money + $money;
            //更新会员信息
            $user->save(['money' => $after]);
            //写入日志
            MoneyLog::create([
                'user_id' => $user_id,
                'money'   => $money,
                'beforemoney'  => $before,
                'after'   => $after,
                'memo'    => $memo,
            ]);
        }
    }
    
    //查找下级用户
    public static function getAffList($user_id)
    {
        $user = self::find($user_id);
        $where = ['superior_id' => $user_id];
        $limit = input('get.limit');
        
        $list = self::order('id','desc')->where($where)->paginate($limit)->each(function($item, $key){

                return $item;
        });
        return ['code'=>0,'data'=>$list->items(),'extend'=>['count' => $list->total(), 'limit' => $limit]];
    }
    
}
