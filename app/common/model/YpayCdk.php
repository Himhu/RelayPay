<?php
declare (strict_types = 1);

namespace app\common\model;

use think\Model;
class YpayCdk extends Model
{
    
    // 获取列表
    public static function getList()
    {
        $where = [];
        $limit = input('get.limit');
        //按卡券代码查找
        if ($code = input("code")) {
            $where[] = ["code", "like", "%" . $code . "%"];
        }
        //按卡券类型查找
        if ($type = input("type")) {
            $where[] = ["type", "like", "%" . $type . "%"];
        }
        $list = self::order('id','desc')->where($where)->paginate((int)$limit)->each(function($item, $key){
                switch ($item['type']) {
                    case '2':
                        $vip = YpayVip::where('id',$item['value'])->find();
                        $item['value'] = $vip['name'];
                        break;
                }

                return $item;
        });;
        return ['code'=>0,'data'=>$list->items(),'extend'=>['count' => $list->total(), 'limit' => $limit]];
    }
}
