<?php

declare (strict_types = 1);

namespace app\common\middleware;
use think\Response;
use think\facade\Db;
use think\facade\Request;
use app\common\service\YpayUser as S;

class ForceRealName
{
    public function handle($request, \Closure $next)
    {
        $data = getConfig();//获取设置参数
        
        // 获取当前方法名
        $methodName = $request->action();

        // 判断当前方法是否在排除列表中
        if ($methodName === 'Real_name' || $methodName === 'realName' || $methodName === 'getRealNameStatus') {
            // 不需要拦截的方法，直接继续处理请求
            return $next($request);
        }
        
        //判断是否开启了实名认证 和 强制需要实名认证
        if($data['isRealName'] == 1 && $data['forceRealName'] == 1){
            $user = Db::name('ypay_user')->where('id',S::getUserId())->find();
            
            if($user['is_realName'] != 1){
                return redirect('/My/Real_name');
            }
        }

        // 验证通过，继续执行后续请求
        return $next($request);
    }
    
}