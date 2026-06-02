<?php
declare (strict_types = 1);

namespace app\common\model;

use think\Model;
class YpayCloud extends Model
{
    // 获取列表
    public static function getList()
    {
        $where = [];
        $limit = input('get.limit');
        //按云端名称查找
        if ($name = input("name")) {
            $where[] = ["name", "like", "%" . $name . "%"];
        }
        
        //根据云端类别
        if ($address = input("address")) {
            $where[] = ["address", "like", "%" . $address . "%"];
        }
        
        //根据云端类别
        if ($type = input("type")) {
            $where[] = ["type", "like", "%" . $type . "%"];
        }
        
        //根据云端类型
        if ($cloud_type = input("cloud_type")) {
            $where[] = ["cloud_type", "like", "%" . $cloud_type . "%"];
        }
        $list = self::order('sort','asc')->where($where)->paginate((int)$limit);
        return ['code'=>0,'data'=>$list->items(),'extend'=>['count' => $list->total(), 'limit' => $limit]];
    }
}
