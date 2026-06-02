<?php

declare(strict_types=1);

namespace app\admin\controller\ypay;

use think\facade\Request;
use app\common\service\YpayUser as S;

class PayTheme extends  \app\admin\controller\Base
{
    protected $middleware = ['AdminCheck', 'AdminPermission'];

    // 模板列表
    public function index()
    {
        if (Request::isAjax()) {
            $data = S::getPayTheme();
            if($data['msg']!= 'success'){
                $data['data'] = ['id' => 'noPage'];
            }
            return $this->getJson($data);
        }
        return $this->fetch();
    }

    //删除模板信息
    public function deleteTheme()
    {
        if (Request::post()) {
            $data = Request::post();


            try {

                $directory = public_path() . 'pay/' . $data['id'];  // 要删除的目录

                if (!file_exists($directory)) {
                    return json(['msg' => '模板不存在', 'code' => 201]);;
                }

                $files = array_diff(scandir($directory), ['.', '..']);

                foreach ($files as $file) {
                    $path = $directory . '/' . $file;

                    if (is_dir($path)) {
                        delete_dir($path);
                    } else {
                        unlink($path);
                    }
                }

                rmdir($directory);

                return json(['msg' => '主题删除成功', 'code' => 200]);
            } catch (\Exception $e) {
                return json(['msg' => '操作失败' . $e->getMessage(), 'code' => 201]);
            }
        }
    }

}
