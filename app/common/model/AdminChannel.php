<?php
declare (strict_types = 1);

namespace app\common\model;

use think\Model;
use think\model\concern\SoftDelete;
class AdminChannel extends Model
{
    use SoftDelete;
     protected $deleteTime = false;
    // 获取列表
    public static function getList()
    {
        $where = [];
        $limit = input('get.limit');
        
               //按通道名称查找
               if ($name = input("name")) {
                   $where[] = ["name", "like", "%" . $name . "%"];
               }
               //按通道标识查找
               if ($code = input("code")) {
                   $where[] = ["code", "like", "%" . $code . "%"];
               }
               //按通道标识查找
               if ($create_type = input("create_type")) {
                   $where[] = ["create_type", "like", "%" . $create_type . "%"];
               }
        $list = self::order('sort','desc')->where($where)->paginate((int)$limit);
        foreach ($list->items() as $item)
        {

            $item['type_name'] = YpayPayment::where('type',$item['type'])->find()['name']  ;
            
        }
        return ['code'=>0,'data'=>$list->items(),'extend'=>['count' => $list->total(), 'limit' => $limit]];
    }
}
