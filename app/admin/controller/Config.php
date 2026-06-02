<?php

declare(strict_types=1);

namespace app\admin\controller;

use think\facade\Db;
use think\facade\Request;
use app\common\model\YpayVip;
use app\common\model\YpayUser;
use app\common\model\YpayUserbasic;
use app\common\model\YpayAccount;
use app\common\model\YpayOrder;
use app\common\model\YpayRecharge;
use app\common\model\MoneyLog;
use app\common\model\YpayTicket;
use app\common\model\YpayPaylist;
use app\common\model\AdminFrontLog;
use app\common\model\YpayQuicklogin;
use system\GoogleAuthenticator;
use think\facade\Session;

class Config extends Base
{
    protected $middleware = ['AdminCheck', 'AdminPermission'];

    // 系统配置
    public function index()
    {
        if (Request::isPost()) {
            $data = Request::post('', '', '');

            if (isset($data['auth_id'])) {
                return $this->getConfig($data);
            }

            if (!empty($data['domain_white'])) {
                // 将字符串根据英文逗号切割成数组
                $white1 = array_filter(explode(',', $data['domain_white']), 'strlen');
                $black2 = array_filter(explode(',', getConfig()['domain_black'] ?: ''), 'strlen');
                //判断新增白名单内容是否在黑名单存在
                $is_black = array_intersect($white1, $black2);

                //根据判断返回内容
                if (!empty($is_black) && !empty($data['domain_black'])) {
                    return json(['msg' => '新增的白名单域名在黑名单存在', 'code' => 201]);
                }
            }

            if (!empty($data['domain_black'])) {
                // 将字符串根据英文逗号切割成数组
                $white2 = array_filter(explode(',', getConfig()['domain_white'] ?: ''), 'strlen');;
                $black1 = array_filter(explode(',', $data['domain_black']), 'strlen');
                //判断新增黑名单内容是否在白名单存在
                $is_white = array_intersect($black1, $white2);

                //根据判断返回内容
                if (!empty($is_white) && !empty($data['domain_white'])) {
                    return json(['msg' => '新增的黑名单域名在白名单存在', 'code' => 201]);
                }
            }

            // 判断自定义用户 ID 是否开启
            if ($data['is_diyUserId']) {
                // 获取全部用户并按用户 ID 顺序排序
                $users = YpayUser::order('id', 'asc')->select();
                // 判断集合是否不为空
                if ($users && count($users) > 0) {
                    // 获取第一个用户的 ID
                    $firstUserId = $users[0]['id'];
                    // 判断第一个用户的 ID 是否等于自定义的用户 ID
                    if ($firstUserId != $data['diy_userId']) {
                        // 计算差值
                        $diff = $data['diy_userId'] - $firstUserId;
                        Db::startTrans(); // 开启事务
                        try {
                            // 遍历所有用户，更新用户 ID
                            foreach ($users as &$user) {
                                $newUserId = $user['id'] + $diff;
                                // 更新 YpayUser 表中的用户 ID
                                YpayUser::where('id', $user['id'])->update(['id' => $newUserId]);
                                // 更新 YpayUserbasic 表中的 user_id
                                YpayUserbasic::where('user_id', $user['id'])->update(['user_id' => $newUserId]);
                                // 更新 YpayAccount 表中的 user_id
                                YpayAccount::where('user_id', $user['id'])->update(['user_id' => $newUserId]);
                                // 更新 YpayOrder 表中的 user_id
                                YpayOrder::where('user_id', $user['id'])->update(['user_id' => $newUserId]);
                                // 更新 YpayRecharge 表中的 user_id
                                YpayRecharge::where('user_id', $user['id'])->update(['user_id' => $newUserId]);
                                // 更新 YpayTicket 表中的 creator_id
                                YpayTicket::where('creator_id', $user['id'])->update(['creator_id' => $newUserId]);
                                // 更新 MoneyLog 表中的 user_id
                                MoneyLog::where('user_id', $user['id'])->update(['user_id' => $newUserId]);
                                // 更新 AdminFrontLog 表中的 uid
                                AdminFrontLog::where('uid', $user['id'])->update(['uid' => $newUserId]);
                                $user['id'] = $newUserId; // 更新当前循环中用户的 ID
                            }
                            // 获取更新后 YpayUser 表中的最大 ID
                            $maxId = YpayUser::max('id');
                            // 设置自增计数器的起始值为最大 ID 加 1
                            Db::execute("ALTER TABLE ypay_user AUTO_INCREMENT = ". ($maxId + 1));
                            Db::commit(); // 提交事务
                        } catch (\Exception $e) {
                            Db::rollback(); // 回滚事务
                            // 可以在这里添加错误日志记录，方便排查问题
                            // \think\facade\Log::error('更新用户 ID 时出错：'.$e->getMessage());
                            throw $e;
                        }
                    }
                }
            }

            $info = $this->getConfig($data);

            if (isset($info['url'])) {
                return json($info);
            }
            return $this->getJson($info);
        }


        $data = $this->getConfig();
        foreach ($data as $key => $value) {
            if ($key == 'diy_recharge' && !empty($value)) {
                $array = explode(",", $value);
                for ($i = 0; $i < count($array); $i++) {
                    $array[$i] = '"' . $array[$i] . '"';
                }
                $data['diy_recharge'] = implode(",", $array);
            } elseif ($key == 'diy_dataClear' && !empty($value)) {
                $array = explode(",", $value);
                for ($i = 0; $i < count($array); $i++) {
                    $array[$i] = '"' . $array[$i] . '"';
                }
                $data['diy_dataClear'] = implode(",", $array);
            } elseif ($key == 'diy_demoPay' && !empty($value)) {
                $array = explode(",", $value);
                for ($i = 0; $i < count($array); $i++) {
                    $array[$i] = '"' . $array[$i] . '"';
                }
                $data['diy_demoPay'] = implode(",", $array);
            }
        }

        return $this->fetch('', [
            'data' => $data,
            'vip' => YpayVip::select(),
            'domain' => Request::domain(),
            'pay' => YpayPaylist::where(['status' => 1, 'user_id' => 0])->select(),
            'login' => YpayQuicklogin::where('status', 1)->select(),
        ]);
    }

    //获取谷歌二维码
    public function getGoogleAuthQrCode()
    {
        //谷歌验证码
        $google = new GoogleAuthenticator();
        //生成验证秘钥
        $secret = $google->createSecret();
        //生成验证二维码 $username 需要绑定的用户名
        $qrCodeUrl = $google->getQRCodeGoogleUrl('Admin', $secret);
        Session::set('secret', $secret);
        return json(['code' => 200, 'msg' => '获取成功' ,'data' => ['qrcode' => $qrCodeUrl , 'text' => $secret]]);
    }

    //绑定谷歌信息
    public function bindGoogleAuth()
    {
        $data = Request::param('', '', 'strip_tags');
        //获取session信息
        $secret = Session::get('secret');
        $google = new GoogleAuthenticator();
        $checkResult = $google->verifyCode($secret, $data['google_captcha'], 4);
        if ($checkResult) {
            Db::table('admin_config')->where("config_name", 'isAdminSecurity')->update(['config_value' => 1]);
            Db::table('admin_config')->where("config_name", 'adminSecurityKey')->update(['config_value' => $secret]);
            return json(['code' => 200, 'msg' => '绑定成功']);
        } else {
            return json(['code' => 201, 'msg' => '谷歌验证码错误或未绑定']);
        }
    }

    //解绑谷歌验证码
    public function uBindGoogleAuth()
    {
        $data = Request::param('', '', 'strip_tags');
        //获取用户的密钥信息
        $google = new GoogleAuthenticator();
        $admin = Db::table('admin_config')->where("config_name", 'adminSecurityKey')->find();
        //$google_secret 存入的谷歌秘钥  ，$code 谷歌动态验证码
        $checkResult = $google->verifyCode($admin['config_value'], $data['google_captcha'], 4);
        if ($checkResult) {
            Db::table('admin_config')->where("config_name", 'isAdminSecurity')->update(['config_value' => 0]);
            Db::table('admin_config')->where("config_name", 'adminSecurityKey')->update(['config_value' => '']);
            return json(['code' => 200, 'msg' => '解绑成功']);
        } else {
            return json(['code' => 201, 'msg' => '谷歌验证码错误']);
        }
    }
}
