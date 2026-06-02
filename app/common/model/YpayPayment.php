<?php
declare (strict_types = 1);

namespace app\common\model;

use think\Model;
use think\model\concern\SoftDelete;
class YpayPayment extends Model
{
    use SoftDelete;
    protected $deleteTime = false;
    // 获取列表
    public static function getList()
    {
        $where = [];
        $limit = input('get.limit');
        
               //按id查找
               if ($id = input("id")) {
                   $where[] = ["id", "like", "%" . $id . "%"];
               }
               //按支付名称查找
               if ($name = input("name")) {
                   $where[] = ["name", "like", "%" . $name . "%"];
               }
               //按支付类型查找
               if ($type = input("type")) {
                   $where[] = ["type", "like", "%" . $type . "%"];
               }
               $status = input("status");
               //按状态查找
               if (isset($status)) {
                   $where[] = ["status", "=",  $status];
               }
        $list = self::order('id','desc')->where($where)->paginate((int)$limit);
        return ['code'=>0,'data'=>$list->items(),'extend'=>['count' => $list->total(), 'limit' => $limit]];
    }
}
