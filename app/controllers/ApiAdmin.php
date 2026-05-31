<?php


namespace app\controllers;
use think\facade\Db;
class ApiAdmin extends ApiCommon
{
	protected $uid;
	protected $user;
	protected $auth_data = ['member'=>false,'product'=>false,'order'=>false,'finance'=>false,'zixun'=>false,
        'restaurant_product'=>false,'restaurant_table'=>false,'restaurant_tableWaiter'=>false,'restaurant_shop'=>false,'restaurant_takeaway'=>false,'restaurant_booking'=>false,'restaurant_deposit'=>false,'restaurant_queue'=>false,'hotel_order'=>false,'mendian_mdmoneylog'=>false,'mendian_mdwithdraw'=>false,'mendian_mdwithdrawlog'=>false];
	public function initialize(){
		parent::initialize();
		//$this->checklogin();
		if(!$this->member){
			echojson(['status'=>-4,'msg'=>'请先登录管理员绑定的用户','url'=>'/pages/index/login?frompage=/admin/index/index']);
		}

		if(request()->controller() != 'ApiAdminIndex' || request()->action() != 'login'){
			$uid = cache($this->sessionid.'_uid');
			if(!$uid){
				$uid = Db::name('admin_user')->where('aid',aid)->where('mid',mid)->value('id');
				if(!$uid){
					echojson(['status'=>-10,'msg'=>'请先登录']);
				}
			}
			$this->uid = $uid;
			$this->user = Db::name('admin_user')->where('id',$uid)->where('status',1)->find();
			if(!$this->user){
				echojson(['status'=>-10,'msg'=>'请先登录']);
			}
			if($this->user['groupid']){
				$group = Db::name('admin_user_group')->where('id',$this->user['groupid'])->find();
				$this->user['auth_data'] = $group['auth_data'];
				$this->user['wxauth_data'] = $group['wxauth_data'];
				$this->user['notice_auth_data'] = $group['notice_auth_data'];
				$this->user['hexiao_auth_data'] = $group['hexiao_auth_data'];
				$this->user['mdid'] = $group['mdid'];
			}
			define('uid',$this->uid);
			define('bid',$this->user['bid']);

			if($this->user['isadmin']>0){
                $wxauth_data = json_decode($this->user['wxauth_data'],true);
                foreach($this->auth_data as $k=>$v){
                    if(in_array($k,$wxauth_data)){
                        $this->auth_data[$k] = true;
                    }
                }
//				foreach($this->auth_data as $k=>$v){
//					$this->auth_data[$k] = true;
//				}
				if($this->user['bid'] != 0 && $this->user['auth_type'] == 1){
					$ptuser = Db::name('admin_user')->where('aid',aid)->where('isadmin','>',0)->find();
					if($ptuser['auth_type'] == 0){
						$this->user['auth_type'] = 0;
						$this->user['auth_data'] = $ptuser['auth_data'];
					}
				}
				$auth_data = json_decode($this->user['auth_data'],true);
				$auth_path = [];
				foreach($auth_data as $v){
					$auth_path = array_merge($auth_path,explode(',',$v));
				}
				$auth_data = $auth_path;
				if(!getcustom('restaurant') || ($this->user['auth_type'] == 0 && !in_array('Restaurant/*',$auth_data))){
					$this->auth_data['restaurant_product'] = false;
					$this->auth_data['restaurant_table'] = false;
					$this->auth_data['restaurant_tableWaiter'] = false;
					$this->auth_data['restaurant_takeaway'] = false;
					$this->auth_data['restaurant_shop'] = false;
					$this->auth_data['restaurant_booking'] = false;
					$this->auth_data['restaurant_deposit'] = false;
					$this->auth_data['restaurant_queue'] = false;	
				}
                }else{
				$wxauth_data = json_decode($this->user['wxauth_data'],true);
				foreach($this->auth_data as $k=>$v){
					if(in_array($k,$wxauth_data)){
						$this->auth_data[$k] = true;
					}
				}
			}

			if($this->user['bid'] != 0){
				if(false){}else{
		        	$this->auth_data['member'] = false;
		        }
				$binfo = Db::name('business')->where('id',$this->user['bid'])->find();
				if($binfo['status'] != 1){
                    if($binfo['status'] == -1)
                        echojson(['status'=>-4,'msg'=>'已过期，请续费']);
                    else
					    echojson(['status'=>-4,'msg'=>'该商家尚未审核通过']);
				}
			}
			
			$controller = request()->controller();
			$action = request()->action();
            if(!getcustom('business_update_member_score') && !in_array(request()->action(),['searchCode','detail','decscore'])){
                if($controller == 'ApiAdminMember' && !$this->auth_data['member']){
                    echojson(['status'=>-4,'msg'=>'无权限操作']);
                }
            }
			if($controller == 'ApiAdminKefu' && !$this->auth_data['zixun']){
				echojson(['status'=>-4,'msg'=>'无权限操作']);
			}
			if($controller == 'ApiAdminFinance' && !$this->auth_data['finance']){
				$canopt = false;//能否操作
				if(!$canopt){
					echojson(['status'=>-4,'msg'=>'无权限操作']);
				}
			}
			if($controller == 'ApiAdminOrder' && !$this->auth_data['order']){
				echojson(['status'=>-4,'msg'=>'无权限操作']);
			}
			if($controller == 'ApiAdminProduct' && !$this->auth_data['product']){
				echojson(['status'=>-4,'msg'=>'无权限操作']);
			}
			if($controller == 'ApiAdminRestaurantProduct' && !$this->auth_data['restaurant_product']){
				echojson(['status'=>-4,'msg'=>'无权限操作']);
			}
			if(($controller == 'ApiAdminRestaurantProduct' || $controller=='ApiAdminRestaurantCategory') && !$this->auth_data['restaurant_product']){
				echojson(['status'=>-4,'msg'=>'无权限操作']);
			}
			if(($controller == 'ApiAdminRestaurantTable' || $controller=='ApiAdminRestaurantTableCategory') && (!$this->auth_data['restaurant_table'] && !$this->auth_data['restaurant_tableWaiter'])){
				echojson(['status'=>-4,'msg'=>'无权限操作']);
			}
			if($controller == 'ApiAdminRestaurantTakeawayOrder' && !$this->auth_data['restaurant_takeaway']){
				echojson(['status'=>-4,'msg'=>'无权限操作']);
			}
			if($controller == 'ApiAdminRestaurantShopOrder' && !$this->auth_data['restaurant_shop']){
				echojson(['status'=>-4,'msg'=>'无权限操作']);
			}
			if(($controller == 'ApiRestaurantBooking' || $controller=='ApiAdminRestaurantBookingOrder') && !$this->auth_data['restaurant_booking']){
				echojson(['status'=>-4,'msg'=>'无权限操作']);
			}
			if($controller=='ApiAdminRestaurantDepositOrder' && !$this->auth_data['restaurant_deposit']){
				echojson(['status'=>-4,'msg'=>'无权限操作']);
			}
			if($controller=='ApiAdminRestaurantQueue' && !$this->auth_data['restaurant_queue']){
				echojson(['status'=>-4,'msg'=>'无权限操作']);
			}
			
		}
	}
}