<?php


// +----------------------------------------------------------------------
// | 收银台后台登录 
// +----------------------------------------------------------------------
namespace app\controllers;
use app\BaseController;
use think\facade\View;
use think\facade\Db;

class CashierLogin extends BaseController
{
	public $webinfo;
    public function initialize(){
		defined('aid') or define('aid', 0);
		$request = request();
		$this->webinfo = Db::name('sysset')->where(['name'=>'webinfo'])->value('value');
		$this->webinfo = json_decode($this->webinfo,true);
		
		View::assign('webinfo',$this->webinfo);
		$reg_open = isset($this->webinfo['reg_open']) ? $this->webinfo['reg_open'] : 0;
		View::assign('reg_open',$reg_open);
		View::assign('webname',$this->webinfo['webname']);
	}
    //登录页
	public function index(){
		$remember = cookie('cashier_remember');
		if($remember == 1){//自动登录
			$rs = Db::name('admin_user')->where('un',cookie('username'))->find();
			if($rs && md5($rs['pwd']) == cookie('password')){
				session('ADMIN_LOGIN',1);
				session('ADMIN_UID',$rs['id']);
				session('ADMIN_AID',$rs['aid']);
				session('ADMIN_BID',$rs['bid']);
				session('ADMIN_NAME',$rs['un'] ? $rs['un'] : $rs['nickname']);
				session('IS_ADMIN',$rs['isadmin']);
				if($rs['isadmin'] == 2){ //有控制台权限
					session('BST_ID',$rs['id']);
				}else{
					session('BST_ID',null);
				}
				Db::name('admin_user')->where('id',$rs['id'])->update(['ip'=>request()->ip(),'logintime'=>time()]);
				Db::name('admin_loginlog')->insert(['aid'=>$rs['aid'],'uid'=>$rs['id'],'logintime'=>time(),'loginip'=>request()->ip(),'logintype'=>'收银台账号登录']);
				if(input('param.fromurl')){
					return redirect(input('param.fromurl'));
				}else{
                    $cwhere []= ['aid' ,'=',$rs['aid']];
                    if($rs['bid'] > 0){
                        $cwhere []= ['bid' ,'=',$rs['bid']];
                    }else{
                        $cwhere []= ['bid' ,'=',0];
                    }
                    $cashier = Db::name('cashier')->where($cwhere)->find();
                    if(!empty($cashier)){
                        header("Location:".PRE_URL.'/cashier/index.html#/index/index?id='.$cashier['id'].'&logout=1');
                    }
				}
			}
		}
        if(request()->isAjax()){
            $username = trim(input('post.username'));
            $password = trim(input('post.password'));
            $captcha = trim(input('post.captcha'));
            if($username=='' || $password==''){
                return json(['status'=>0,'msg'=>'用户名和密码不能为空']);
            }elseif($captcha == ''){
                return json(['status'=>0,'msg'=>'验证码不能为空']);
            }elseif(!captcha_check($captcha)){
                return json(['status'=>0,'msg'=>'验证码错误']);
            }
            $rs = Db::name('admin_user')->where('un',$username)->where('pwd',md5($password))->find();
            if(!$rs){
                return json(['status'=>2,'msg'=>'用户名或密码错误']);
            }elseif($rs['status']!=1){
                return json(['status'=>0,'msg'=>'该账号已禁用']);
            }
            if($rs['bid'] > 0){
                $binfo = Db::name('business')->where('id',$rs['bid'])->find();
                if($binfo['status'] != 1){
                    return json(['status'=>0,'msg'=>'该商家尚未审核通过']);
                }

            }
            $auth = $this->checkAuth($rs['id']);
            if($auth['status'] ==0){
                return json(['status'=>0,'msg'=>$auth['msg']]);
            }
            Db::name('admin_user')->where('un',$username)->where('pwd',md5($password))->update(['ip'=>request()->ip(),'logintime'=>time()]);

            session('ADMIN_LOGIN',1);
            session('ADMIN_UID',$rs['id']);
            session('ADMIN_AID',$rs['aid']);
            session('ADMIN_BID',$rs['bid']);
            session('ADMIN_NAME',$rs['un']);
            session('IS_ADMIN',$rs['isadmin']);
            if($rs['isadmin'] == 2){ //有控制台权限
                session('BST_ID',$rs['id']);
            }else{
                session('BST_ID',null);
            }
            Db::name('admin_loginlog')->insert(['aid'=>$rs['aid'],'uid'=>$rs['id'],'logintime'=>time(),'loginip'=>request()->ip(),'logintype'=>'收银台账号登录']);
            if(input('post.cashier_remember')){//记住密码
                cookie('cashier_remember',1,30*86400);
                cookie('username',$username,30*86400);
                cookie('password',md5(md5($password)),30*86400);
            }else{
                cookie('cashier_remember',null);
                cookie('username',null);
                cookie('password',null);
            }
            if(input('param.fromurl')){
                return json(['status'=>1,'msg'=>'登录成功','url'=>input('param.fromurl')]);
            }else{
                $cwhere []= ['aid' ,'=',$rs['aid']];
                if($rs['bid'] > 0){
                    $cwhere []= ['bid' ,'=',$rs['bid']];
                }else{
                    $cwhere []= ['bid' ,'=',0];
                }
                $cashier = Db::name('cashier')->where($cwhere)->find();
                if(empty($cashier)){
                    return  json(['status' =>0,'msg' =>'请创建收银台后再登录']);
                }else{
                    return json(['status'=>1,'msg'=>'登录成功','url'=>'/cashier/index.html#/index/index?id='.$cashier['id'].'&logout=1']);
                }
            }
        }
		$webinfo = Db::name('sysset')->where('name','webinfo')->value('value');
		$webinfo = json_decode($webinfo,true);
		View::assign('webinfo',$webinfo);
		return View::fetch();
    }
    public function login(){
        if(request()->isAjax()){
            $username = trim(input('post.username'));
            $password = trim(input('post.password'));
            $captcha = trim(input('post.captcha'));
            if($username=='' || $password==''){
                return json(['status'=>0,'msg'=>'用户名和密码不能为空']);
            }elseif($captcha == ''){
                return json(['status'=>0,'msg'=>'验证码不能为空']);
            }elseif(!captcha_check($captcha)){
                return json(['status'=>0,'msg'=>'验证码错误']);
            }
            $rs = Db::name('admin_user')->where('un',$username)->where('pwd',md5($password))->find();
            if(!$rs){
                return json(['status'=>2,'msg'=>'用户名或密码错误']);
            }elseif($rs['status']!=1){
                return json(['status'=>0,'msg'=>'该账号已禁用']);
            }
            if($rs['bid'] > 0){
                $binfo = Db::name('business')->where('id',$rs['bid'])->find();
                if($binfo['status'] != 1){
                    return json(['status'=>0,'msg'=>'该商家尚未审核通过']);
                }

            }
            $auth = $this->checkAuth($rs['id']);
            if($auth['status'] ==0){
                return json(['status'=>0,'msg'=>$auth['msg']]);
            }
            Db::name('admin_user')->where('un',$username)->where('pwd',md5($password))->update(['ip'=>request()->ip(),'logintime'=>time()]);

            session('ADMIN_LOGIN',1);
            session('ADMIN_UID',$rs['id']);
            session('ADMIN_AID',$rs['aid']);
            session('ADMIN_BID',$rs['bid']);
            session('ADMIN_NAME',$rs['un']);
            session('IS_ADMIN',$rs['isadmin']);
            if($rs['isadmin'] == 2){ //有控制台权限
                session('BST_ID',$rs['id']);
            }else{
                session('BST_ID',null);
            }
            Db::name('admin_loginlog')->insert(['aid'=>$rs['aid'],'uid'=>$rs['id'],'logintime'=>time(),'loginip'=>request()->ip(),'logintype'=>'收银台账号登录']);
            if(input('post.cashier_remember')){//记住密码
                cookie('cashier_remember',1,30*86400);
                cookie('username',$username,30*86400);
                cookie('password',md5(md5($password)),30*86400);
            }else{
                cookie('cashier_remember',null);
                cookie('username',null);
                cookie('password',null);
            }
            if(input('param.fromurl')){
                return json(['status'=>1,'msg'=>'登录成功','url'=>input('param.fromurl')]);
            }else{
                $cwhere []= ['aid' ,'=',$rs['aid']];
                if($rs['bid'] > 0){
                    $cwhere []= ['bid' ,'=',$rs['bid']];
                }else{
                    $cwhere []= ['bid' ,'=',0];
                }
                $cashier = Db::name('cashier')->where($cwhere)->find();
                if(empty($cashier)){
                    return  json(['status' =>0,'msg' =>'请创建收银台后再登录']);
                }else{
                    return json(['status'=>1,'msg'=>'登录成功','url'=>'/cashier/index.html#/index/index?id='.$cashier['id'].'&logout=1']);
                }
            }
        }
    }
    public function checkAuth($uid=0){
        $user = Db::name('admin_user')->where('id',$uid)->find();
        if($user['auth_type']==0){
            if($user['groupid']){
                $user['auth_data'] = Db::name('admin_user_group')->where('id',$user['groupid'])->value('auth_data');
            }
            $auth_data = json_decode($user['auth_data'],true);
            $auth_path = \app\commons\Menu::blacklist();
            foreach($auth_data as $v){
                $auth_path = array_merge($auth_path,explode(',',$v));
            }
            $thispath = 'Cashier/index';
            if(!in_array('Cashier/*',$auth_path) && !in_array($thispath,$auth_path) && !session('BST_ID')){
                return ['status'=>0,'msg'=>'当前账号没有收银台权限'];
            }
        }
        return ['status'=>1,'msg'=>''];
        
    }
    //交班
    public function  jiaoban(){
        $rdata = $this->tongji();
        return json(['status'=>1,'msg'=>'成功','data' => $rdata]);
    }
    public function tongji(){
        $uid = session('ADMIN_UID');
        $aid =  session('ADMIN_AID');
        $bid = session('ADMIN_BID');
        $jiaobantime = time();
        $logintime = Db::name('admin_loginlog')->where('aid',$aid)->where('uid',$uid)->order('id desc')->where('logintype','收银台账号登录')->value('logintime');
        //收银员账号
        $cwhere []= ['aid' ,'=',$aid];
        if($bid > 0){
            $cwhere []= ['bid' ,'=',$bid];
        }else{
            $cwhere []= ['bid' ,'=',0];
        }
        $cashier = Db::name('cashier')->where($cwhere)->find();
        //订单数量
        $ordercount = Db::name('cashier_order')->where('uid',$uid)->where('paytime','between',[$logintime,$jiaobantime])->count();
        //今日营业总额  分别 现金 随行付 微信  支付宝
        $today_start_time = strtotime(date('Y-m-d 00:00:01'));
        if($logintime > $today_start_time){
            $today_start_time =$logintime;
        }
        $today_total_money = 0+ Db::name('cashier_order')->where('uid',$uid)->where('paytime','between',[$today_start_time,$jiaobantime])->sum('totalprice');
        //现金 0
        $today_cash_money =  0+ Db::name('cashier_order')->where('uid',$uid)->where('paytime','between',[$today_start_time,$jiaobantime])->where('paytypeid','=',0)->sum('totalprice');
        //随行付 5
        $today_sxf_money =  0+ Db::name('cashier_order')->where('uid',$uid)->where('paytime','between',[$today_start_time,$jiaobantime])->where('paytypeid','=',5)->sum('totalprice');
        //微信 2
        $today_wx_money =  0+ Db::name('cashier_order')->where('uid',$uid)->where('paytime','between',[$today_start_time,$jiaobantime])->where('paytypeid','=',2)->sum('totalprice');
        //支付宝
        $today_alipay_money =  0+ Db::name('cashier_order')->where('uid',$uid)->where('paytime','between',[$today_start_time,$jiaobantime])->where('paytypeid','=',3)->sum('totalprice');
        //余额 1
        $today_yue_money =  0+ Db::name('cashier_order')->where('uid',$uid)->where('paytime','between',[$today_start_time,$jiaobantime])->where('paytypeid','=',1)->sum('totalprice');
//今日会员总计 
        $today_member_total_money =0+ Db::name('cashier_order')->where('uid',$uid)->where('paytime','between',[$today_start_time,$jiaobantime])->where('mid','>',0)->sum('totalprice');
        //现金收款
        $today_member_cash_money =0+ Db::name('cashier_order')->where('uid',$uid)->where('paytime','between',[$today_start_time,$jiaobantime])->where('mid','>',0)->where('paytypeid','=',0)->sum('totalprice');
        //随行付
        $today_member_sxf_money =0+ Db::name('cashier_order')->where('uid',$uid)->where('paytime','between',[$today_start_time,$jiaobantime])->where('mid','>',0)->where('paytypeid','=',5)->sum('totalprice');
        //微信
        $today_member_wx_money =0+ Db::name('cashier_order')->where('uid',$uid)->where('paytime','between',[$today_start_time,$jiaobantime])->where('mid','>',0)->where('paytypeid','=',2)->sum('totalprice');
        //支付宝
        $today_member_alipay_money =0+ Db::name('cashier_order')->where('uid',$uid)->where('paytime','between',[$today_start_time,$jiaobantime])->where('mid','>',0)->where('paytypeid','=',3)->sum('totalprice');
        //余额
        $today_member_yue_money =0+ Db::name('cashier_order')->where('uid',$uid)->where('paytime','between',[$today_start_time,$jiaobantime])->where('mid','>',0)->where('paytypeid','=',1)->sum('totalprice');
//退款总额
        $today_refund_total_money=0+ Db::name('cashier_order')->where('uid',$uid)->where('refund_time','between',[$today_start_time,$jiaobantime])->where('status','=',10)->sum('refund_money');
        //退款 现金
        $today_refund_cash_money = 0+ Db::name('cashier_order')->where('uid',$uid)->where('refund_time','between',[$today_start_time,$jiaobantime])->where('status','=',10)->where('paytypeid','=',0)->sum('refund_money');
        //退款 随行付
        $today_refund_sxf_money = 0+ Db::name('cashier_order')->where('uid',$uid)->where('refund_time','between',[$today_start_time,$jiaobantime])->where('status','=',10)->where('paytypeid','=',5)->sum('refund_money');
        //退款 微信
        $today_refund_wx_money = 0+ Db::name('cashier_order')->where('uid',$uid)->where('refund_time','between',[$today_start_time,$jiaobantime])->where('status','=',10)->where('paytypeid','=',2)->sum('refund_money');
        //退款 支付宝
        $today_refund_alipay_money = 0+ Db::name('cashier_order')->where('uid',$uid)->where('refund_time','between',[$today_start_time,$jiaobantime])->where('status','=',10)->where('paytypeid','=',3)->sum('refund_money');
        //充值
        $total_recharge = Db::name('member_moneylog')->where('aid',$aid)->where('remark','收银台充值')->where('createtime','between',[$today_start_time,$jiaobantime])->sum('money');
        $cashdesk_user = Db::name('admin_user')->where('id',$uid)->value('un');

        //根据设置显示不同的支付信息
        $wxpay_show = true;
        $sxfpay_show = true;
        $alipay_show = true;
        $cashpay_show = true;
        $yuepay_show =  true;
        if($cashier['bid'] ==0){
            if(!$cashier['wxpay']){
                $wxpay_show = false;
            }
            if(!$cashier['sxpay']){
                $sxfpay_show = false;
            }
            if(!getcustom('cashdesk_alipay')){
                $alipay_show = false;
            }
            if(!$cashier['cashpay']){
                $cashpay_show = false;
            }
            if(!$cashier['cashpay']){
                $yuepay_show = false;
            }
        }else{//bid>0
            $sysset = Db::name('restaurant_admin_set')->where('aid',aid)->find();
            if(!$sysset['business_cashdesk_wxpay_type']){
                $wxpay_show = false;
            }
            if(!$sysset['business_cashdesk_sxpay_type']){
                $sxfpay_show = false;
            }
            if(!$sysset['business_cashdesk_alipay_type'] ||!getcustom('cashdesk_alipay')){
                $alipay_show = false;
            }
            if(!$sysset['business_cashdesk_cashpay']){
                $cashpay_show = false;
            }
            if(!$sysset['business_cashdesk_yue']){
                $yuepay_show = false;
            }
        }
        $rdata = [];
        $rdata['logintime'] = date('Y-m-d H:i:s',$logintime);
        $rdata['jiaobantime'] = date('Y-m-d H:i:s',$jiaobantime);
        $rdata['ordercount'] = $ordercount;
        $rdata['cashier_info'] =$cashier;
        $rdata['cashdesk_user'] =$cashdesk_user;
        //今日总营业额      
        $rdata['today_total_money'] =dd_money_format($today_total_money);
        $rdata['today_cash_money'] =dd_money_format($today_cash_money); //现金
        $rdata['today_sxf_money'] =dd_money_format($today_sxf_money); //随行付
        $rdata['today_wx_money'] =dd_money_format($today_wx_money); //微信
        $rdata['today_alipay_money'] =dd_money_format($today_alipay_money); //支付宝
        $rdata['today_yue_money'] =dd_money_format($today_yue_money); //支付宝
        //今日会员总计 
        $rdata['today_member_total_money'] =dd_money_format($today_member_total_money); //会员总计
        $rdata['today_member_cash_money'] =dd_money_format($today_member_cash_money); //会员 现金
        $rdata['today_member_sxf_money'] =dd_money_format($today_member_sxf_money); //会员 随行付
        $rdata['today_member_wx_money'] =dd_money_format($today_member_wx_money); //会员 微信
        $rdata['today_member_alipay_money'] =dd_money_format($today_member_alipay_money); //会员 支付宝
        $rdata['today_member_yue_money'] =dd_money_format($today_member_yue_money); //会员 余额
        //退款 
        $rdata['today_refund_total_money'] =dd_money_format($today_refund_total_money); //会员 总退款
        $rdata['today_refund_cash_money'] =dd_money_format($today_refund_cash_money); //会员 现金退款
        $rdata['today_refund_sxf_money'] =dd_money_format($today_refund_sxf_money); //会员 随行付退款
        $rdata['today_refund_wx_money'] =dd_money_format($today_refund_wx_money); //会员 微信退款
        $rdata['today_refund_alipay_money'] =dd_money_format($today_refund_alipay_money); //会员 支付宝退款
        $rdata['total_recharge'] =dd_money_format($total_recharge); //会员充值
        $rdata['total_money'] = dd_money_format($today_total_money - $today_yue_money + $total_recharge -$today_refund_total_money);
      
        $rdata['recharge_show'] =true;
        if($bid>0){
            //如果是商户 不统计 充值金额
            $recharge_total_money = 0;
            $rdata['recharge_show'] =false;
        }
        $rdata['wxpay_show'] =$wxpay_show;
        $rdata['sxfpay_show'] =$sxfpay_show;
        $rdata['alipay_show'] =$alipay_show;
        $rdata['cashpay_show'] =$cashpay_show;
        $rdata['yuepay_show'] =$yuepay_show;
        return  $rdata;
    }
	//退出登录
	public function logout(){
        $is_print = input('param.is_print',0);//1：打印 0不打印
        if($is_print){
            $rdata = $this->tongji();
            \app\commons\Wifiprint::jiaobanPrintCashier($rdata);
        }
        session('ADMIN_LOGIN',null);
		session('ADMIN_UID',null);
		session('ADMIN_AID',null);
		session('ADMIN_BID',null);
		session('ADMIN_NAME',null);
		session('IS_ADMIN',null);
		session('BST_ID',null);
		cookie('cashier_remember',null);
		cookie('usertel',null);
		cookie('password',null);
        return json(['status' =>1 ,'msg'=>'退出成功']);
    }

}
