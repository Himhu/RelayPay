<?php
declare (strict_types = 1);

namespace app\common\model;

use think\Model;

class YpayQuicklogin extends Model
{
   // 获取列表
    public static function getList()
    {
        $where = [];
        $limit = input('get.limit');
        
        $list = self::order('id','desc')->where($where)->paginate((int)$limit);
        return ['code'=>0,'data'=>$list->items(),'extend'=>['count' => $list->total(), 'limit' => $limit]];
    }

    
}
