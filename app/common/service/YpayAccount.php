<?php
declare (strict_types = 1);

namespace app\common\service;
use think\facade\Session;
use think\facade\Cookie;
use think\facade\Db;
use think\App;
use think\facade\Request;
use app\common\model\YpayAccount as M;
use app\common\validate\YpayAccount as V;
use app\common\service\YpayUser as S;
use think\facade\Config;
use app\common\core\core;

class YpayAccount
{
    // 添加
    public static function goAdd($data)
    {
        //创建Core对象
        $core  = new Core();
        $user = Db::table('ypay_user')->where('id',Session::get('front.id'))->find();
        if(empty($user['vip_time'])){
            return ['msg'=>"未开通会员套餐",'code'=>202];
        }
        $time = strtotime($user['vip_time']);
        if($time<time())
        {
            return ['msg'=>"会员套餐已过期",'code'=>202];
        }
        $vip = Db::table('ypay_vip')->where('id',$user['vip_id'])->find();
        //判断是否开启了通道绑定功能
        if(!empty($vip['is_passage'])){
            if($vip['is_passage'] && !strstr($vip['passage'],$data['code'])){
                return ['msg'=>"该通道需要开启更高级的套餐才能使用",'code'=>201];
            }
        }
        $data['user_id'] = Session::get('front.id');
        $channel = Db::table('admin_channel')->where('code',$data['code'])->find();
        $data['type'] = $channel['type'];
        $data['succcount'] = 0;
        $data['succprice'] = 0;
        //验证
        $validate = new V;
        if(!$validate->scene('add')->check($data))
        return ['msg'=>$validate->getError(),'code'=>201];
        if(empty($channel))
        {
            return ['msg'=>"通道不存在或标识重复",'code'=>201];
        }
        
        if($data['type']=="wxpay" && ($data['code'] == "wxpay_cloud" || $data['code'] == "wxpay_cloudzs" || $data['code'] == "wxpay_skd" || $data['code'] == "wxpay_jym_cloud"))
        {       
            
            //构建查询参数
            $where = 
            [
                'id' => $data['diyu'],
                'type' => 1
            ];
            
            if($data['code'] == "wxpay_cloudzs"){
                $data['qr_type'] = 'appreciate';
            }

            //获取云端地域
            $cloudArray = Db::table('ypay_cloud')->where($where)->find();
            
            //判断是否为空 , 为空则提示错误
            if(!empty($cloudArray)){
                //根据云端类型返回不同的信息
                try {
                    //创建微信实例 传递参数:云端类别 云端地址
                    $res = $core->getWechatCreate($cloudArray['cloud_type'],$cloudArray['address']);
                } catch (\Exception $e){
                   return ['msg'=>'请检查云端地址是否正确','code'=>201]; 
                }
                
                //如果参数等于success 则进入流程
                if($res['message'] == "success")
                {
                    $data['cloud_id'] = $cloudArray['id'];
                    $data['wx_guid'] = isset($res['guid']) ? $res['guid']: '';
                    $data['wxname'] = $data['cloud_login_type'];
                }else
                {
                    return ['msg'=> $res['message'],'code'=>201];
                }
                
            }else{
               return ['msg'=>"没查询到此云端信息",'code'=>201]; 
            }
            
        }
        else
        {
            if($data['code']=="wxpay_dy")
            {
                $data['status'] = 1;
            }
        }
        if($data['type']=="qqpay" || $data['code'] == 'qqpay_wzq'){
            if(empty($data['qq']))
            {
                return ['msg'=>"qq号不可为空",'code'=>201];
            }
            if($data['code'] == 'qqpay_cloud' || ($data['code'] == 'qqpay_wzq' && $data['wzq_type'] != 'local')){
                $cloud= Db::table('ypay_cloud')->where('id',$data['diyu'])->find();
                try {
                    $core->Api_AddQQ($data['qq'],$cloud['address']);
                }catch (\Exception $e){
                    return ['msg'=>'请检查云端地址是否正确','code'=>201];
                }
                $data['cloud_id'] = $cloud['id'];
            }
        }
        if($data['code']=='wxpay_software' || $data['code'] == "qqpay_software" || $data['code'] == "alipay_software")
        {
            $data['status'] = 1;
        }
        
        if($data["code"] == "usdt"){
            $data["wxname"] = $data['usdt'];
            $data['status'] = 1;
        }

        if(!empty($data['aliappkey'])){
            $data['qr_url'] = $data['aliappkey'];
        }
        if($data['code']=='alipay_mck' || $data['code']=='alipay_dmf' || $data['code']=='lkl_wxpay' || $data['code']=='lkl_alipay' || $data['code']=='dougong_alipay' || $data['code']=='dougong_wxpay' || $data['code']=='lebrush_alipay' || $data['code']=='lebrush_wxpay')
        {
            $data['wxname'] = $data['zfbapppid'];
            $data['status'] = 1;
        }
 
        
        if(!empty($data['remark'])){
            $data['remark'] = strip_tags($data['remark']);
        }
    
        try {
            M::create($data);
        }catch (\Exception $e){
            return ['msg'=>'操作失败'.$e->getMessage(),'code'=>201];
        }
    }
    
    // 切换云端地域
    public static function goSwitchCloud($data)
    {
        //创建Core对象
        $core  = new Core();
        $user = Db::table('ypay_user')->where('id',Session::get('front.id'))->find();
        if(empty($user['vip_time'])){
            return ['msg'=>"未开通会员套餐",'code'=>201];
        }
        $time = strtotime($user['vip_time']);
        if($time<time())
        {
            return ['msg'=>"会员套餐已过期",'code'=>201];
        }
        $data['user_id'] = Session::get('front.id');
    $cloud= Db::table('ypay_cloud')->where('id',$data['diyu'])->find();
    
    if($data['code'] == 'qqpay_cloud' || $data['code'] == 'qqpay_wzq'){
            $account = M::where('id',$data['id'])->where('user_id',S::getUserId())->find();
            if(empty($account))
            {
                return ['code'=>201,'msg'=>'通道不存在!'];
            }
            $res = $core->Api_GetOnlineQQlist($cloud['address']);
            if(empty($res)){
                return ['msg'=>'请检查云端地址是否正确','code'=>201];
            }
            $temp = in_array($account['qq'], array_column($res['data']['bots'], 'id'));
            
            if(!empty($temp))
            {
                if($temp){
                   return ['msg'=>'该QQ已存在该云端节点','code'=>201]; 
                }
            }
            try {
                $core->Api_AddQQ($account['qq'],$cloud['address']);
            }catch (\Exception $e){
                return ['msg'=>'请检查云端地址是否正确','code'=>201];
            }

        
        $data['cloud_id'] = $cloud['id'];
    }else{
        //构建查询参数
            $where = 
            [
                'id' => $data['diyu'],
                'type' => 1
            ];
            
            //获取云端地域
            $cloudArray = Db::table('ypay_cloud')->where($where)->find();
            
            //判断是否为空 , 为空则提示错误
            if(!empty($cloudArray)){
                //根据云端类型返回不同的信息
                try {
                    //创建微信实例 传递参数:云端类别 云端地址
                    $res = $core->getWechatCreate($cloudArray['cloud_type'],$cloudArray['address']);
                } catch (\Exception $e){
                   return ['msg'=>'请检查云端地址是否正确','code'=>201]; 
                }
                
                //如果参数等于success 则进入流程
                if($res['message'] == "success")
                {
                    $data['cloud_id'] = $cloudArray['id'];
                    $data['wx_guid'] = isset($res['guid']) ? $res['guid']: '';
                }else
                {
                    return ['msg'=> $res['message'],'code'=>201];
                }
                
            }else{
               return ['msg'=>"没查询到此云端信息",'code'=>201]; 
            }
   
    }

        try {
            
            if(!empty($data['remark'])){
                $data['remark'] = strip_tags($data['remark']);
            }
            $data['status'] = 0;
            M::update($data);
        }catch (\Exception $e){
            return ['msg'=>'操作失败'.$e->getMessage(),'code'=>201];
        }
    }
    
    //修改支付宝当面付/商家账单通道
    public static function goEditAliPay($data){
        $update = [];
        try {
            $account = M::where('id', $data['id'])->where('user_id', S::getUserId())->find();
            if (empty($account)) {
                return ['msg' => '通道不存在或无权限', 'code' => 201];
            }
            if($data['code'] == 'alipay_dmf'){
                $update = 
                [
                    'wxname' => $data['appId'],
                    'cookie' => $data['publicKey'],
                    'qr_url' => $data['privateKey'],
                    'memo' => $data['memo']
                    ];
                M::where('id',$data['id'])->update($update);
            }else if($data['code'] == 'alipay_mck'){
                $update = 
                [
                    'zfb_pid' => $data['pid'],
                    'wxname' => $data['appId'],
                    'cookie' => $data['publicKey'],
                    'qr_url' => $data['privateKey'],
                    'memo' => $data['memo'],
                    'status' => 1
                    ];
                M::where('id',$data['id'])->update($update);
            }else if($data['code'] == 'lkl_alipay' || $data['code'] == 'lkl_wxpay'){
                $update = 
                [
                    'zfb_pid' => $data['pid'],
                    'wxname' => $data['appId'],
                    'qr_url' => $data['privateKey'],
                    'remark' => $data['remark'],
                    'memo' => $data['memo'],
                    'status' => 1
                    ];
                M::where('id',$data['id'])->update($update);
            }else if($data['code'] == 'dougong_alipay' || $data['code'] == 'dougong_wxpay'){
                $update = 
                [
                    'zfb_pid' => $data['pid'],
                    'wxname' => $data['appId'],
                    'cookie' => $data['publicKey'],
                    'qr_url' => $data['privateKey'],
                    'remark' => $data['remark'],
                    'memo' => $data['memo']
                    ];
                M::where('id',$data['id'])->update($update);
            }else if($data['code'] == 'lebrush_alipay' || $data['code'] == 'lebrush_wxpay'){
                $update = 
                [
                    'zfb_pid' => $data['pid'],
                    'wxname' => $data['appId'],
                    'memo' => $data['memo']
                    ];
                M::where('id',$data['id'])->update($update);
            }
        }catch (\Exception $e){
            return ['msg'=>'操作失败'.$e->getMessage(),'code'=>201];
        }
    }
    
    //修改微信APP挂机/自挂/店员通道
    public static function goEditWxPay($data){
        $update = [];
        try {
            $account = M::where('id', $data['id'])->where('user_id', S::getUserId())->find();
            if (empty($account)) {
                return ['msg' => '通道不存在或无权限', 'code' => 201];
            }
            if($data['code'] == 'wxpay_dy'){
                $update = 
                [
                    'wxname' => $data['wxname'],
                    'qr_url' => $data['qr_url'],
                    'memo' => $data['memo']
                    ];
                M::where('id',$data['id'])->update($update);
            }else{
                $update = 
                [
                    'qr_url' => $data['qr_url'],
                    'memo' => $data['memo']
                    ];
                M::where('id',$data['id'])->update($update);
            }
        }catch (\Exception $e){
            return ['msg'=>'操作失败'.$e->getMessage(),'code'=>201];
        }
    }

    //修改Usdt通道
    public static function goEditUsdt($data){
        $update = [];
        try {
                $account = M::where('id', $data['id'])->where('user_id', S::getUserId())->find();
                if (empty($account)) {
                    return ['msg' => '通道不存在或无权限', 'code' => 201];
                }
                $update = 
                [
                    'wxname' => $data['wxname'],
                    'memo' => $data['memo']
                    ];
                M::where('id',$data['id'])->update($update);
        }catch (\Exception $e){
            return ['msg'=>'操作失败'.$e->getMessage(),'code'=>201];
        }
    }
    
    // 编辑
    public static function goEdit($data,$id)
    {
        //获取通道ID
        $new_data['id'] = $id;
        //获取更改后的微信昵称
        $new_data['wxname'] = $data['wxname'];
        $account = M::where('id', $id)->where('user_id', S::getUserId())->find();
        if (empty($account)) {
            return ['msg' => '通道不存在或无权限', 'code' => 201];
        }
        //验证
        // $validate = new V;
        // if(!$validate->scene('edit')->check($data))
        // return ['msg'=>$validate->getError(),'code'=>201];
        try {
             M::update($new_data);
        }catch (\Exception $e){
            return ['msg'=>'操作失败'.$e->getMessage(),'code'=>201];
        }
    }

    // 更改在线状态
    public static function goStatus($data,$id)
    {
        $model =  M::where('id', $id)->where('user_id', S::getUserId())->find();
        if ($model->isEmpty())  return ['msg'=>'数据不存在或无权限','code'=>201];
        try{
            $model->save([
                'status' => $data,
            ]);
        }catch (\Exception $e){
            return ['msg'=>'操作失败'.$e->getMessage(),'code'=>201];
        }
    }
    
    //更改使用状态
    public static function goIsStatus($data,$id)
    {
        $model =  M::where('id', $id)->where('user_id', S::getUserId())->find();
        if ($model->isEmpty())  return ['msg'=>'数据不存在或无权限','code'=>201];
        try{
            $model->save([
                'is_status' => $data,
            ]);
        }catch (\Exception $e){
            return ['msg'=>'操作失败'.$e->getMessage(),'code'=>201];
        }
    }

    // 删除
    public static function goRemove($id)
    {
        $model = M::where('id', $id)->where('user_id', S::getUserId())->find();
        if ($model->isEmpty()) return ['msg'=>'数据不存在或无权限','code'=>201];
        try{
           $model->delete();
        }catch (\Exception $e){
            return ['msg'=>'操作失败'.$e->getMessage(),'code'=>201];
        }
    }

    // 批量删除
    public static function goBatchRemove($ids)
    {
        if (!is_array($ids)) return ['msg'=>'数据不存在','code'=>201];
        try{
            M::whereIn('id', $ids)->where('user_id', S::getUserId())->delete();
        }catch (\Exception $e){
            return ['msg'=>'操作失败'.$e->getMessage(),'code'=>201];
        }
    }
    
    // 一键清理所有离线通道
    public static function goRemove_line($data)
    {   
        if(empty($data['type'])){
            return ['code' => 201,'msg' =>'请先选择类型'];
        }
         $where = ['status' => 0,'type'=>$data['type']];
        try{
            M::where($where)->delete();
        }catch (\Exception $e){
            return ['msg'=>'操作失败'.$e->getMessage(),'code'=>201];
        }
    }
    
    //获取二维码信息
    public static function GetQrlistQrcode($id)
    {
        //创建Core对象
        $core  = new Core();
        $acc = Db::table('ypay_account')->where('id', $id)->where('user_id',S::getUserId())->find();
        if(empty($acc))
        {
            return ['code'=>0,'msg'=>'通道不存在!'];
        }
        if($acc['type']=="alipay")
        {
            //获取支付宝登录二维码
            $data = $core->GetAlipayLoginQrcode();
            
            Db::name('ypay_account')->where('id', $id)->update(['remark' => $data['loginid']]);
            return ['code'=>1,'msg'=>'获取成功!','qr_url'=>build_qrcode_url($data['qrcodeurl']),'loginid'=>$data['loginid']];
        }
        if($acc['type']=="wxpay" && $acc['code']!="wxpay_dy" && $acc['code']!="qqpay_wzq")
        {       
                $res = $core->getWechatQrCode($acc['wx_guid'],$acc['cloud_id'],$acc);
                
                //如果数据为空则重新执行生成流程
                if(empty($res)){
                    //查询是否存在该云端地域
                    $cloudArray = Db::name('ypay_cloud')->where('id',$acc['cloud_id'])->find();
                    
                    //数据不为空则执行流程
                    if(!empty($cloudArray)){
                        try {
                            //创建微信实例 传递参数:云端类别 云端地址
                            $res = $core->getWechatCreate($cloudArray['cloud_type'],$cloudArray['address']);
                            //更新Guid
                            Db::name('ypay_account')->where('id', $id)->update(['wx_guid' =>$res['guid']]);
                            //重新获取二维码信息
                            $res = $core->getWechatQrCode($res['guid'],$acc['cloud_id'],$acc);
                        } catch (\Exception $e){
                          return ['msg'=>'请检查云端地址是否正确','code'=>201]; 
                        }
                    }else{
                        return ['msg'=>'未查到此云端地域','code'=>201]; 
                    }
                }
                Db::name('ypay_account')->where('id', $id)->update(['remark' =>$res['data']['uuid'],'wx_guid' =>$res['data']['guid']]);
                $qr_url = $res['is_base64'] ? $res['data']['qrcode'] : build_qrcode_url($res['data']['qrcode']);
                return ['code'=>1,'msg'=>'获取成功!','uuid'=>$res['data']['uuid'],'qr_url'=>$qr_url,'guid'=>empty($acc['wx_guid']) ? $res['data']['guid']:$acc['wx_guid'],'is_base64' => $res['is_base64']];

        }
        if($acc['code']=="qqpay_mg")
        {
            $res = $core->QCreate();
            if($res['code']==1)
            {
                Db::name('ypay_account')->where('id', $id)->update(['remark' => $res['qrsig']]);
            }
            
            return $res;
        }
        
        if($acc['code']=="qqpay_cloud" || $acc['code']=="qqpay_wzq")
        {
            if($acc['code']=="qqpay_wzq" && empty($acc['cloud_id'])){
                $res = $core->QCreate();
                if($res['code']==1)
                {
                    Db::name('ypay_account')->where('id', $id)->update(['remark' => $res['qrsig']]);
                }
                return ['code'=>1,'qrid'=>$acc['id'],'qr_url'=>$res['qr_url']];
            }
            $res = $core->Api_CreatQrCodeInfo($acc['qq'],$acc['cloud_id']);
            return ['code'=>1,'qrid'=>$acc['id'],'qr_url'=>$res];
        }
        
        
        return ['code'=>0,'msg'=>'系统错误!'];
    }
    
    //获取扫码状态
    public static function GetChannelLoginStatus($id='')
    {
        //创建Core对象
        $core  = new Core();
        $acc = Db::table('ypay_account')->where('id',$id)->where('user_id', S::getUserId())->find();
        if(empty($acc))
        {
            return ['code'=>0,'msg'=>'通道不存在!'];
        }
        if($acc['type']=="alipay")
        {
            $data = $core->GetAlipayLoginStatus($acc['remark']);
            if($data['code']==1)
            {
                $pid = getSubstr(base64_decode($data['cookie']),"CLUB_ALIPAY_COM=",";");
                Db::name('ypay_account')->where('id', $id)->update(['cookie' => $data['cookie'],'status'=>1,'zfb_pid'=>$pid]);
                return ['code'=>1,'msg'=>'账号登录成功!','nick'=>"用户PID为：".$pid];
            }
            else
            {
                return $data;
            }
        }
        if($acc['type']=="wxpay" && $acc['code']!="wxpay_dy" && $acc['code']!="qqpay_wzq" )
        {
            //检测微信登录扫码状态
            $res = $core->getWechatLoginStatus($acc['wx_guid'],$acc['remark'],$acc['cloud_id']);
           
            if ($res['data']['state'] == 402){
                return ['code'=>-1,'msg'=>'登录异常!'];
            }
            if ($res['data']['state'] == -4){
                return ['code'=>-4,'data'=>$res['data']];
            }
            if($res['data']['state'] == 400){
                return ['code'=>-1,'msg'=>'二维码已过期!'];
            }
            if($res['data']['state']==0)
            {
                return ['code'=>0,'msg'=>'等待扫码中!'];
            }
            else if($res['data']['state']==1)
            {
                return ['code'=>0,'msg'=>'已扫码待确认!'];
            }
            else
            {
                //登录账户
                $res = $core->getWechatLoginManual($acc['wx_guid'],$res['data']['wxid'],$res['data']['wxnewpass'],$acc['cloud_id'],$acc);
 
                //判断返回信息
                if($res['code'] == -3){
                    return ['code'=>404,'msg'=>'信息错误!','nick'=>"登录环境检测失败,请重新扫码登录!"];
                }else if($res['code'] == -34){
                    return ['code'=>404,'msg'=>'取消登录!','nick'=>"你取消了登录!"];
                }else if($res['code'] == 201){
                    return ['code'=>404,'msg'=>$res['msg'],'nick'=>$res['msg']];
                }else if($res['code'] == 0 || $res['code'] == 1){
                    Db::name('ypay_account')->where('id', $id)->update(['status' =>1]);
                    return ['code'=>1,'msg'=>'账号登录成功!','nick'=>"登录成功,点击更新按钮即可!"];
                }
                
            }
        }
        
        if($acc['code']=="qqpay_mg")
        {   
            $data = $core->GetQLoginStatus($acc['remark']);
            if($data['code']==1)
            {
                Db::name('ypay_account')->where('id', $id)->update(['cookie' => $data['cookie'],'status'=>1,'remark'=>'']);
                return ['code'=>1,'msg'=>'账号登录成功!','nick'=>"登录QQ为：".$acc['qq']];
            }
            else
            {
                return $data;
            }
        }
        
        if($acc['code']=="qqpay_cloud" || $acc['code']=="qqpay_wzq")
        {
            if(empty($acc['cloud_id']) && $acc['code']=="qqpay_wzq"){
                $data = $core->GetQLoginStatus($acc['remark']);
                if($data['code']==1)
                {
                    Db::name('ypay_account')->where('id', $id)->update(['cookie' => $data['cookie'],'status'=>1,'remark'=>'']);
                    return ['code'=>1,'msg'=>'账号登录成功!','nick'=>"登录QQ为：".$acc['qq']];
                }
                else
                {
                    return $data;
                }
            }
            $res = $core->Api_GetOnlineQQlist($acc['cloud_id']);
            if(!empty($res)){
                $array = array_column($res['data']['bots'], 'id');
                $temp = in_array($acc['qq'], $array);
                if($temp){
                    foreach ($res['data']['bots'] as $key => $value){
                        if($value['id'] == $acc['qq']){
                            if($value['status'] == "登录完毕" || $value['status'] == "登录成功"){
                                Db::name('ypay_account')->where('id', $acc['id'])->update(['status' =>1]);
                                return ['code'=>1,'msg'=>'账号登录成功!'];
                            }
                        }
                    }
                }
            }
            $res = $core->Api_GetQrCodeStatus($acc['qq'],$acc['cloud_id']);
            if($res=="0")
            {
                Db::name('ypay_account')->where('id', $acc['id'])->update(['status' =>1]);
                return ['code'=>1,'msg'=>'账号登录成功!'];
            }
            if($res=="53")
            {
                return ['code'=>0,'msg'=>'扫描成功，请在手机上点击确认...'];
            }
            if($res=="4")
            {
                return ['code'=>0,'msg'=>'二维码失效，请重新申请...'];
            }
            return ['code'=>0,'msg'=>'等待扫码中!'];
        }
        return ['code'=>0,'msg'=>'系统错误!'];
    }
    
    //提交验证码
    public static function SubmitVerificationCode($code = "",$id="",$data = null){
         //创建Core对象
        $core  = new Core();
        $acc = Db::table('ypay_account')->where('id',$id)->where('user_id', S::getUserId())->find();
        if(empty($acc))
        {
            return ['code'=>0,'msg'=>'通道不存在!'];
        }
         $res = $core->weChatLoginVerify([
            'Data62' => $acc['wx_guid'],
            'Data'   => $data,
            'Code'   => $code,
            'Uuid'   => $acc['remark'],
        ],$acc['cloud_id']);
        if($res){
            return ['code' => 200 , 'msg' => "提交验证码成功"];
        }else{
            return ['code' => 201 , 'msg' => "提交验证码失败"];
        }
    }
}
