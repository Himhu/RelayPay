<?php
declare (strict_types = 1);

namespace app\common\model;

use think\Model;
class YpayTicket extends Model
{
    
    // 获取列表
    public static function getList()
    {
        $where = [];
        $limit = input('get.limit');
         //按用户ID查找
        if ($creator_id = input("creator_id")) {
            $where[] = ["creator_id", "like", "%" . $creator_id . "%"];
        }
        //按回复状态拆线呢
        if ($status = input("status")) {
            $where[] = ["status", "like", "%" . $status . "%"];
        }
        //按工单类型查询
        if ($type = input("type")) {
            $where[] = ["type", "like", "%" . $type . "%"];
        }
        
        $list = self::order('id','desc')->where($where)->paginate((int)$limit);
          foreach ($list->items() as $item)
        {
              $item['type_name'] = YpayTicketCategory::where('id',$item['type'])->find()['name'];
            
        }
        return ['code'=>0,'data'=>$list->items(),'extend'=>['count' => $list->total(), 'limit' => $limit]];
    }
    
    //获取信息
    public static function getUserList($user_id){
        $where = [];
        $limit = input('get.limit');
        $where[] = ["creator_id",'=',$user_id];
        $list = self::order('id','asc')->where($where)->paginate((int)$limit);
          foreach ($list->items() as $item)
        {
              $item['type_name'] = YpayTicketCategory::where('id',$item['type'])->find()['name'];
            
        }
        return ['code'=>0,'data'=>$list->items(),'extend'=>['count' => $list->total(), 'limit' => $limit]];
    }
}
