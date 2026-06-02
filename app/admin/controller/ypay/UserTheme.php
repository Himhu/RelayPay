<?php

declare(strict_types=1);

namespace app\admin\controller\ypay;

use think\facade\Request;
use think\facade\Db;
use app\common\service\YpayUser as S;

class UserTheme extends  \app\admin\controller\Base
{
    protected $middleware = ['AdminCheck', 'AdminPermission'];

    // 模板列表
    public function index()
    {
        if (Request::isAjax()) {
            $data = S::getUserTheme();
            if($data['msg']!= 'success'){
                $data['data'] = ['id' => 'noPage'];
            }
            return $this->getJson($data);
        }
        return $this->fetch();
    }

    //保存模板信息
    public function saveTheme()
    {
        if (Request::post()) {
            $data = Request::post();
            try {
                Db::name('admin_config')->where('config_name', 'user_theme')->update(['config_value' => $data['id']]);
                return json(['msg' => '设置主题成功', 'code' => 200]);
            } catch (\Exception $e) {
                return json(['msg' => '操作失败' . $e->getMessage(), 'code' => 201]);
            }
        }
    }

    //删除模板信息
    public function deleteTheme()
    {
        if (Request::post()) {
            $data = Request::post();
            if ($data['id'] == 'default' || $data['id'] == 'old') {
                return json(['msg' => '请勿删除默认模板', 'code' => 201]);
            }

            $userTheme = Db::name('admin_config')->where('config_name', 'user_theme')->find();

            try {

                if ($userTheme['config_value'] == $data['id']) {
                    Db::name('admin_config')->where('config_name', 'user_theme')->update(['config_value' => 'default']);
                }

                $directory = public_path() . '/user/' . $data['id'];  // 要删除的目录

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
