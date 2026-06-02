<?php
declare (strict_types = 1);

namespace app\admin\controller\ypay;

use think\facade\Request;
use app\common\service\YpayUser as S;
use app\common\model\YpayUser as Sm;
use app\common\model\YpayDomain as M;
use app\common\util\Mail;

class Domain extends  \app\admin\controller\Base
{
    protected $middleware = ['AdminCheck','AdminPermission'];

    // 列表
    public function index(){
        if (Request::isAjax()) {
            return $this->getJson(M::getList());
        }
        return $this->fetch();
    }

    // 添加域名信息
    public function add(){
        if (Request::isAjax()) {
            $data = Request::post();
            //查询是否存在改用户
            $user = Sm::where('id',$data['user_id'])->find();
            if(empty($user)){
                return json(['msg'=>'未查到此用户','code'=>201]);
            }
            try {
                
                
                    M::insert($data);
                    return json(['msg'=>'新增成功','code'=>200]); 

            }catch (\Exception $e){
                return json(['msg'=>'操作失败'.$e->getMessage(),'code'=>201]);
            }
        }
        return $this->fetch();
    }

    // 编辑域名信息
    public function edit(){
        if (Request::isAjax()) {
            $data = Request::post();
            $data['id'] = input('id');
            $user = Sm::where('id',$data['user_id'])->find();//获取用户信息
            
            //审核通过则发送通知邮件
            if($data['status'] == 1 && !empty($user['email'])){
                Mail::go($user['email'],'域名审核通过',self::emailTemp($data['siteurl']));
            }
            
            try {
                M::update($data);
                return json(['msg'=>'修改成功','code'=>200]);
            }catch (\Exception $e){
                return json(['msg'=>'操作失败'.$e->getMessage(),'code'=>201]);
            }
        }
        return $this->fetch('',['model' => M::withTrashed()->find(input('id'))]);
    }
        
    // 删除域名信息
    public function remove(){
        $model = M::withTrashed()->find(input('id'));
        if ($model->isEmpty()) return json(['msg'=>'数据不存在','code'=>201]);
        try{
           $model->force()->delete();
           return ['msg'=>'删除成功','code'=>200];
        }catch (\Exception $e){
            return ['msg'=>'操作失败'.$e->getMessage(),'code'=>201];
        }
    }

    // 批量域名信息
    public function batchRemove(){
        if (!is_array(input('ids'))) return ['msg'=>'数据不存在','code'=>201];
        try{
            M::destroy(input('ids'),true);
            return ['msg'=>'批量删除成功','code'=>200];
        }catch (\Exception $e){
            return ['msg'=>'操作失败'.$e->getMessage(),'code'=>201];
        }
     }
    
    //审核通过邮件模板
    public function emailTemp(){
        $html = '<div style="font-size: 14.6667px;" data-mail-from="wemail-pc">
    <br>
    <table
        style="width: 650px;background: white; margin: 12px auto; border-radius: 20px; box-shadow: rgba(0, 0, 0, 0.2) 0px 4px 8px 0px, rgba(0, 0, 0, 0.19) 0px 6px 20px 0px; zoom: normal; border-collapse: collapse; box-sizing: border-box; height: 552px;">
        <tbody>
            <tr>
                <td align="center" style="box-sizing: border-box; width: 650px; height: 552px;">
                    <table style="width: 97.9969%; height: 494px;">
                        <tbody>
                            <tr style="height: 30px;">
                                <td style="width: 97.5531%; height: 30px;" height="30">&nbsp;</td>
                            </tr>
                            <tr style="height: 36px;">
                                <td align="center">
                                    <img src="'.Request::domain().getConfig()['logo'].'"
                                        width="240">
                                </td>
                            </tr>
                            <tr style="height: 30px;">
                                <td style="text-align: center;width: 97.5531%; height: 30px;" height="30">
                                    <div style="line-height: 1.43;"><span style="font-size: 16pt;">—— 域名审核结果通知 ——</span>
                                    </div>
                                </td>
                            </tr>
                            <tr style="height: 200px;">
                                <td style="font-size: 17px; font-family: Avenir, Arial; padding: 0px 30px; width: 97.5531%; height: 200px; position: relative;">
                                    您提交的域名【'.input('siteurl').'】已审核通过，网站可正常发起支付，感谢您对'.getConfig()['sitename'].'的支持。
                                </td>
                            </tr>
                            <tr style="height: 25px;">
                                <td
                                    style="font-size: 13px; font-weight: 100; color: #373f55; padding: 30px 28px 28px; width: 97.5531%; height: 25px;">
                                    <div style="text-align: center;"><a
                                            style="font-size: 18px; text-decoration: none; word-break: break-all; font-weight: 500; color: #fff; background-color: #465df1; border-radius: 10px; padding: 16px 110px 16px 110px;"
                                            href="'.Request::domain().'/User/Index" target="_blank" rel="noopener"><span
                                                style="color: rgb(255, 255, 255); font-size: medium;">前往商户中心</span><span
                                                style="color: #000000; font-size: medium;">
                                            </span></a></div>
                                </td>
                            </tr>
                            <tr style="height: 59px;">
                                <td style="font-size: 12px; color: #afb7c1; line-height: 1.8; padding: 0px 30px; width: 97.5531%; height: 59px;">
                                    <table width="100%">
                                        <tbody>
                                            <tr>
                                                <td align="center">
                                                    <p><span
                                                            style="color: #000000; font-family: -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Oxygen, Ubuntu, Cantarell, Open Sans, Helvetica Neue, sans-serif; font-size: medium;">本邮件由系统自动发出,请勿回复!</span>
                                                    </p>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </td>
            </tr>
        </tbody>
    </table>
</div>';

return $html;
    }
}
