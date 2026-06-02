<?php
declare (strict_types = 1);

namespace app\common\middleware;

use app\common\service\YpayUser as S;

class GoogleAuth
{
    /**
     * 处理请求
     */
    public function handle($request, \Closure $next)
    {
        $config = getConfig();
        if($config['isSecurity'] == 1 && $config['isSecurityForce'] == 1){
            $user = S::getUser();
            // 获取当前方法名
            $methodName = $request->action();
            if(empty($user['googlekey'])){
                return redirect($request->root().'/My/Security');
            }
        }
        //(new \app\common\model\AdminAdminLog)->record();
        return $next($request);
    }
}
