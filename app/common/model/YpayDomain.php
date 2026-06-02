<?php
declare (strict_types = 1);

namespace app\common\model;

use think\Model;
use think\model\concern\SoftDelete;
class YpayDomain extends Model
{
    use SoftDelete;
    protected $deleteTime = 'delete_time';
    // 获取列表
    public static function getList()
    {
        $where = [];
        $limit = input('get.limit');
        //按用户ID查找
        if ($user_id = input("user_id")) {
            $where[] = ["user_id", "like", "%" . $user_id . "%"];
        }
        //按站点名称查找
        if ($sitename = input("sitename")) {
            $where[] = ["sitename", "like", "%" . $sitename . "%"];
        }
        
        $status = input("status");
        
        //按审核状态查找
        if (!empty($status) ||  $status == '0') {
            if($status != -1){
                $where[] = ["status", "=", $status];
                $where[] = ["delete_time", "=", null];
            }else{
                $where[] = ["delete_time", "<>",  '' ];
            }
            
        }

        $list = self::order('id','desc')->withTrashed()->where($where)->paginate((int)$limit);
        return ['code'=>0,'data'=>$list->items(),'extend'=>['count' => $list->total(), 'limit' => $limit]];
    }
    
    // 获取列表
    public static function getUserList($user_id)
    {
        $where = [];
        $limit = input('get.limit');
        $where[] = ["user_id",'=',$user_id];
        $list = self::order('id','asc')->where($where)->paginate((int)$limit);
        return ['code'=>0,'data'=>$list->items(),'extend'=>['count' => $list->total(), 'limit' => $limit]];
    }
}
