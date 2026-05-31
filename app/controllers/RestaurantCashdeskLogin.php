<?php


// +----------------------------------------------------------------------
// | 餐饮收银台后台登录 custom_file(restaurant_shop_cashdesk)
// +----------------------------------------------------------------------
namespace app\controllers;
use app\BaseController;
use think\facade\View;
use think\facade\Db;

class RestaurantCashdeskLogin extends BaseController
{
	public $webinfo;
    public function initialize(){
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
		$remember = cookie('cashdesk_remember');
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
				Db::name('admin_loginlog')->insert(['aid'=>$rs['aid'],'uid'=>$rs['id'],'logintime'=>time(),'loginip'=>request()->ip(),'logintype'=>'餐饮收银台账号登录']);
				if(input('param.fromurl')){
					return redirect(input('param.fromurl'));
				}else{
                    $cwhere []= ['aid' ,'=',$rs['aid']];
                    if($rs['bid'] > 0){
                        $cwhere []= ['bid' ,'=',$rs['bid']];
                    }
                    $cashier = Db::name('restaurant_cashdesk')->where($cwhere)->find();
					return redirect(PRE_URL.'/cashdesk/index.html#/table/index?id='.$cashier['id']);
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
            Db::name('admin_loginlog')->insert(['aid'=>$rs['aid'],'uid'=>$rs['id'],'logintime'=>time(),'loginip'=>request()->ip(),'logintype'=>'餐饮收银台账号登录']);
            if(input('post.remember')){//记住密码
                cookie('cashdesk_remember',1,30*86400);
                cookie('username',$username,30*86400);
                cookie('password',md5(md5($password)),30*86400);
            }else{
                cookie('remember',null);
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
                $cashier = Db::name('restaurant_cashdesk')->where($cwhere)->find();
                if(empty($cashier)){
                    return  json(['status' =>0,'msg' =>'请创建收银台后再登录']);
                }else{
                    return json(['status'=>1,'msg'=>'登录成功','url'=>'/cashdesk/index.html#/table/index?id='.$cashier['id'].'&logout=1']);
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
            $thispath = 'RestaurantCashdesk/login';
            if(!in_array('RestaurantCashdesk/login',$auth_path) && !in_array($thispath,$auth_path) && !session('BST_ID')){
                return ['status'=>0,'msg'=>'当前账号没有餐饮收银台登录权限'];
            }
        }
        return ['status'=>1,'msg'=>''];
        
    }
    //交班
    public function  jiaoban(){
        if(!session("?ADMIN_LOGIN")){
            $url =  (string)url('RestaurantCashdeskLogin/index');
            echojson(['status'=>-5,'msg'=>'请重新登录','url' => $url]);die();
        }
        $type = input('param.type');
        $uid = session('ADMIN_UID');
        $starttime = input('param.starttime');
        $endtime = input('param.endtime');
        $aid =  session('ADMIN_AID');
        $bid = session('ADMIN_BID');
        $other = [
            'datetype' => $type,
            'starttime' =>$starttime,
            'endtime' => $endtime,
        ];
        //不能小于30天
        if($type =='custom'){
            $min_time = time() - 30*86400;
            if(strtotime($starttime) < $min_time || strtotime($endtime) < $min_time ){
                return json(['status'=>0,'msg'=>'只能查询30内的数据']);
            }
        }
       
        $rdata =\app\models\Payorder::tradeReport($aid,$bid,$uid,1,$other);
        return json(['status'=>1,'msg'=>'成功','data' => $rdata]);
    }
	//退出登录
	public function logout(){
        $is_print = input('param.is_print',0);//1：打印 0不打印
        $type = input('param.type');
        if($is_print){
            $starttime = input('param.starttime');
            $endtime = input('param.endtime');
            $uid = session('ADMIN_UID');
            $aid =  session('ADMIN_AID');
            $bid = session('ADMIN_BID');
            $other = [
                'datetype' => $type,
                'starttime' =>$starttime,
                'endtime' => $endtime,
            ];
            //不能小于30天
            if($type =='custom'){
                $min_time = time() - 30*86400;
                if(strtotime($starttime) < $min_time || strtotime($endtime) < $min_time ){
                    return json(['status'=>0,'msg'=>'只能查询30内的数据']);
                }
            }
            $rdata = \app\models\Payorder::tradeReport($aid,$bid,$uid,1,$other);
            $rdata['title'] ='交接班对账单';
            \app\commons\Wifiprint::jiaobanPrint($rdata);
        }
		session('ADMIN_LOGIN',null);
		session('ADMIN_UID',null);
		session('ADMIN_AID',null);
		session('ADMIN_BID',null);
		session('ADMIN_NAME',null);
		session('IS_ADMIN',null);
		session('BST_ID',null);
		cookie('cashdesk_remember',null);
		cookie('username',null);
		cookie('password',null);
		return json(['status' =>1 ,'msg'=>'退出成功']);
	}
	//报表
    public function baobiao(){
        }
}
