<?php
declare (strict_types = 1);

namespace app\admin\controller\ypay;

use think\facade\Request;
use app\common\service\YpayVip as S;
use app\common\model\YpayVip as M;
use app\common\model\AdminChannel as Channel;
use app\common\model\YpayPayment as Payment;

class Vip extends  \app\admin\controller\Base
{
    protected $middleware = ['AdminCheck','AdminPermission'];

    // 列表
    public function index(){
        if (Request::isAjax()) {
            return $this->getJson(M::getList());
        }
        return $this->fetch();
    }
    
    //实时获取通道
    public function getChannel(){
        // 获取支付方式
        $payment = Payment::select()->toArray();
        // 获取支付通道
        $temp = Channel::select()->toArray();
            
        // 遍历 $payment 数组，将 type 键改为 value 键，并添加 children 字段
        foreach ($payment as &$payItem) {
            if (array_key_exists('type', $payItem)) {
                $payItem['value'] = $payItem['type'];
                unset($payItem['type']);
            }
            $payItem['children'] = [];
        
            // 遍历 $temp 数组
            foreach ($temp as $tempItem) {
                // 检查 $temp 数组子元素的 type 是否和 $payment 当前元素的 value 相等
                if ($tempItem['type'] === $payItem['value']) {
                    // 如果相等，将 $temp 数组当前元素的 code 和 name 放到 $payment 当前元素的 children 里
                    $payItem['children'][] = [
                        'value' => $tempItem['code'],
                        'name' => $tempItem['name']
                    ];
                }
            }
        }
        // 解除对 $payItem 的引用，避免后续可能的意外修改
        unset($payItem);
        
        return  $this->json('获取成功', 200, $payment);
    }
    
    // 添加
    public function add(){
        if (Request::isAjax()) {
            return $this->getJson(S::goAdd(Request::post()));
        }
        return $this->fetch();
    }

    // 编辑
    public function edit(){
        if (Request::isAjax()) {
            return $this->getJson(S::goEdit(Request::post(),input('id')));
        }
        $model  = M::find(input('id'));
        if($model['is_passage']){
            foreach (explode(',',$model['passage']) as $key => $value){
                $model['passages'] .=  "','".$value;
            }
            //去掉字符
            $model['passages'] = "'".substr($model['passages'],3)."'";
        }
        return $this->fetch('',['model' => $model]);
    }

    // 状态
    public function status(){
        return $this->getJson(S::goStatus(Request::post('status'),input('id')));
    }

    // 排序
    public function sort($data,$sort_new,$sort_old){
        return $this->getJson(S::goSort(input('data'),input('sort_new'),input('sort_old')));
    }
    // 删除
    public function remove(){
        return $this->getJson(S::goRemove(input('id')));
        }

    // 批量删除
    public function batchRemove(){
        return $this->getJson(S::goBatchRemove(Request::post('ids')));
        }

    // 回收站
    public function recycle(){
        if (Request::isAjax()) {
            return $this->getJson(S::goRecycle());
        }
        return $this->fetch();
    }

}
