<?php
declare (strict_types = 1);

namespace app\common\middleware;

use app\common\service\YpayUser as S;

class FrontAuth
{
    /**
     * 处理请求
     */
    public function handle($request, \Closure $next)
    {
        if(S::isAuth() == false){
            return redirect($request->root().'/My/GoogleAuth');
        }

        return $next($request);
    }
}
