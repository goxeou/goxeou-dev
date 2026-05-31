<?php


// +----------------------------------------------------------------------
// | 后台账号 子账号
// +----------------------------------------------------------------------
namespace app\controllers;
use app\commons\Wechat;
use think\facade\View;
use think\facade\Db;

class WebUser extends Common
{
    public function initialize(){
		parent::initialize();
		$this->uid = session('BST_ID');
		$this->user = db('admin_user')->where(['id'=>$this->uid])->find();
		if(!session('BST_ID') || !$this->user || $this->user['isadmin'] != 2){
			showmsg('无访问权限');
		}
	}
	//账号列表
    public function index(){
        if (getcustom('admin_user_group')){
            $groupArr = Db::name('admin_group')->order('sort desc,id desc')->column('name','id');
            View::assign('groupArr',$groupArr);
        }
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			
			if(input('param.field') && input('param.order')){
				if(input('param.field')=='logintime'){
					$order = 'admin_user.'.input('param.field').' '.input('param.order');
				}else{
					$order = 'admin.'.input('param.field').' '.input('param.order');
				}
			}else{
				$order = 'admin.id desc';
			}
			$where = [];
			if(input('param.aid')) $where[] = ['admin.id','=',input('param.aid')];
			if(input('param.un')) $where[] = ['admin_user.un|admin.linkman|admin.tel','like','%'.input('param.un').'%'];
			if(input('param.tel')) $where[] = ['admin.tel','like','%'.input('param.tel').'%'];
            if(input('param.keyword')){
                $ids1 = Db::name('admin_setapp_mp')->whereLike('nickname','%'.input('param.keyword').'%')->column('aid');
                $ids2 = Db::name('admin_setapp_wx')->whereLike('nickname','%'.input('param.keyword').'%')->column('aid');
                $ids1 = $ids1 ? $ids1 : [];
                $ids2 = $ids2 ? $ids2 : [];
                $ids = array_merge($ids1,$ids2);
                $ids = array_unique($ids);
                if($ids) $where[] = ['admin.id','in',$ids];
            }
			if(input('?param.status') && input('param.status')!=='') $where[] = ['admin.status','=',input('param.status')];
            if(input('param.group_id')) $where[] = ['admin.group_id','=',input('param.group_id')];
			$count = 0 + Db::name('admin')->alias('admin')->join('admin_user admin_user','admin.id=admin_user.aid and admin_user.isadmin>0 and admin_user.bid=0')->where($where)->count();
//            dd(Db::getLastSql());
			$data = Db::name('admin')->alias('admin')->field('admin.*,admin_user.id uid,admin_user.un,admin_user.pwd,admin_user.logintime')->join('admin_user admin_user','admin.id=admin_user.aid and admin_user.bid=0 and admin_user.isadmin in (1,2) and admin_user.bid=0')->where($where)->page($page,$limit)->order($order)->select()->toArray();

			foreach($data as $k=>$v){
				$data[$k]['wxappinfo'] = \app\commons\System::appinfo($v['id'],'wx');
				$data[$k]['mpappinfo'] = \app\commons\System::appinfo($v['id'],'mp');
				$admin_set = db('admin_set')->where('aid',$v['id'])->find();
				if(!$admin_set){
					$color1 = '#FD4A46';
				}else{
					$color1 = $admin_set['color1'];
				}
				$data[$k]['color1'] = $color1;
                if (getcustom('admin_user_group')){
                    $data[$k]['groupName'] = $groupArr[$v['group_id']];
                }
                if (getcustom('consumer_value_add')){
                    $bonus_pool_total = Db::name('consumer_set')->where('aid',$v['id'])->value('bonus_pool_total');
                    $data[$k]['bonus_pool_total'] = $bonus_pool_total?:0;
                }
			}
			return ['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data];
		}
		if(input('param.type')==1){
			$lastDayStart = strtotime(date('Y-m-d',time()-86400));
			$lastDayEnd = $lastDayStart + 86400;
			$thisMonthStart = strtotime(date('Y-m-1'));
			$nowtime = time();
			//会员数
			$memberCount = 0 + Db::name('member')->where([])->count();
			$memberLastDayCount = 0 + Db::name('member')->where('createtime','>=',$lastDayStart)->where('createtime','<',$lastDayEnd)->count();
			$memberThisMonthCount = 0 + Db::name('member')->where('createtime','>=',$thisMonthStart)->where('createtime','<',$nowtime)->count();
			//订单数 订单金额
			$ordernumCount = 0 + Db::name('shop_order')->where('status','in','1,2,3')->count();
			$ordernumLastDayCount = 0 + Db::name('shop_order')->where('status','in','1,2,3')->where('paytime','>=',$lastDayStart)->where('paytime','<',$lastDayEnd)->count();
			$ordernumThisMonthCount = 0 + Db::name('shop_order')->where('status','in','1,2,3')->where('paytime','>=',$thisMonthStart)->where('paytime','<',$nowtime)->count();
			$ordermoneyCount = 0 + Db::name('shop_order')->where('status','in','1,2,3')->sum('totalprice');
			$ordermoneyLastDayCount = 0 + Db::name('shop_order')->where('status','in','1,2,3')->where('paytime','>=',$lastDayStart)->where('paytime','<',$lastDayEnd)->sum('totalprice');
			$ordermoneyThisMonthCount = 0 + Db::name('shop_order')->where('status','in','1,2,3')->where('paytime','>=',$thisMonthStart)->where('paytime','<',$nowtime)->sum('totalprice');
			//商品数量
			$productCount = 0 + Db::name('shop_product')->where([])->count();
			$product0Count = 0 + Db::name('shop_product')->where('status',0)->count();
			$product1Count = 0 + Db::name('shop_product')->where('status',1)->count();
			
			//收款金额
			$payCount = Db::name('payorder')->where('paytypeid','not in','1,4')->where('status',1)->sum('money');
			$payLastDayCount = 0 + Db::name('payorder')->where('paytypeid','not in','1,4')->where('status',1)->where('paytime','>=',$lastDayStart)->where('paytime','<',$lastDayEnd)->sum('money');
			$payThisMonthCount = 0 + Db::name('payorder')->where('paytypeid','not in','1,4')->where('status',1)->where('paytime','>=',$thisMonthStart)->where('paytime','<',$nowtime)->sum('money');
			
			View::assign('memberCount',$memberCount);
			View::assign('memberLastDayCount',$memberLastDayCount);
			View::assign('memberThisMonthCount',$memberThisMonthCount);
			View::assign('ordernumCount',$ordernumCount);
			View::assign('ordernumLastDayCount',$ordernumLastDayCount);
			View::assign('ordernumThisMonthCount',$ordernumThisMonthCount);
			View::assign('ordermoneyCount',$ordermoneyCount);
			View::assign('ordermoneyLastDayCount',$ordermoneyLastDayCount);
			View::assign('ordermoneyThisMonthCount',$ordermoneyThisMonthCount);
			View::assign('productCount',$productCount);
			View::assign('product0Count',$product0Count);
			View::assign('product1Count',$product1Count);
			View::assign('payCount',$payCount);
			View::assign('payLastDayCount',$payLastDayCount);
			View::assign('payThisMonthCount',$payThisMonthCount);

			//是否配置了计划任务
			$autotimes = cache('autotimes');
			if($autotimes){
				$isauto = 1;
			}else{
				$isauto = 0;
			}
			View::assign('isauto',$isauto);

			$config = include('config.php');
			$authkey = $config['authkey'];
			$domain = $_SERVER['HTTP_HOST'];
		//	$rs = request_post('https:///index/upgrade2/getversion',['authkey'=>$authkey,'authdomain'=>$domain]);
		//	$rsdata = json_decode($rs,true);
			$needupgrade = 0;
			$myversion = file_get_contents('version.php');
			if($rsdata && $rsdata['status']==1 &&  $rsdata['version'] != $myversion){
				$needupgrade = 1;
			}
			View::assign('needupgrade',$needupgrade);
			View::assign('myversion',$myversion);
			View::assign('newversion',$rsdata['version']);

		}


		return View::fetch();
    }
	//登录
	public function alogin(){
		$rs = Db::name('admin_user')->where(array('id'=>input('param.uid/d')))->find();
		if($rs){
			session('ADMIN_LOGIN',1);
			session('ADMIN_UID',$rs['id']);
			session('ADMIN_AID',$rs['aid']);
			session('ADMIN_BID',$rs['bid']);
			session('ADMIN_NAME',$rs['un']);
			session('IS_ADMIN',$rs['isadmin']);
			return redirect((string)url('Backstage/index'));
		}
	}
	//编辑
	public function edit(){
		$menudata = \app\commons\Menu::getdata(0,0);
        $disabled_auth_data = [];
		if(input('param.id')){
			$id = input('param.id/d');
			define('aid',$id);
			$ainfo = Db::name('admin')->where('id',$id)->find();
			$asetinfo = Db::name('admin_set')->where('aid',$id)->find();
			$uinfo = Db::name('admin_user')->where('aid',$id)->where('bid',0)->where('isadmin','>',0)->find();
			$auth_data = $uinfo ? json_decode($uinfo['auth_data'],true) : array();
            if(getcustom('user_disabled_auth_data')){
                $disabled_auth_data = ($uinfo && $uinfo['disabled_auth_data']) ? json_decode($uinfo['disabled_auth_data'],true) : array();
            }
		}else{
			$ainfo = ['type'=>1,'status'=>1,'endtime'=>time()+86400*365,'platform'=>'mp,wx'];
			$asetinfo = [];
			$uinfo = [];
			$auth_data = [];
		}

        $wxauth_data = $uinfo ? json_decode($uinfo['wxauth_data'],true) : array();
        if(!$wxauth_data) $wxauth_data = array();
        $notice_auth_data = $uinfo ? json_decode($uinfo['notice_auth_data'],true) : array();
        if(!$notice_auth_data) $notice_auth_data = array();
        $hexiao_auth_data = $uinfo ? json_decode($uinfo['hexiao_auth_data'],true) : array();
        if(!$hexiao_auth_data) $hexiao_auth_data = array();
        $wxauth_data = $uinfo ? json_decode($uinfo['wxauth_data'],true) : array();
        if(!$wxauth_data) $wxauth_data = array();
        if (getcustom('product_supply_chain')){
            $supplierList = Db::name('supplier')->select()->toArray();
            View::assign('supplierList',$supplierList);
        }

		View::assign('auth_data',$auth_data);
		View::assign('disabled_auth_data',$disabled_auth_data);
        View::assign('notice_auth_data',$notice_auth_data);
        View::assign('hexiao_auth_data',$hexiao_auth_data);
        View::assign('wxauth_data',$wxauth_data);
		View::assign('menudata',$menudata);

        if (getcustom('admin_user_group')){
            $groupArr = Db::name('admin_group')->order('sort desc,id desc')->column('name','id');
            View::assign('groupArr',$groupArr);
        }
		
		if(getcustom('hotel')){
			$text = \app\models\Hotel::gettext(aid);
			View::assign('text',$text);
		}

		View::assign('ainfo',$ainfo);
		View::assign('asetinfo',$asetinfo);
		View::assign('uinfo',$uinfo);
		View::assign('rinfo',json_decode($ainfo['remote'],true));
		return View::fetch();
	}
	public function save(){
		$ainfo = input('post.ainfo/a');
		$uinfo = input('post.uinfo/a');
        $uinfo['notice_auth_data'] = jsonEncode(input('post.notice_auth_data/a'));
        $uinfo['hexiao_auth_data'] = jsonEncode(input('post.hexiao_auth_data/a'));
        $uinfo['wxauth_data'] = jsonEncode(input('post.wxauth_data/a'));
		$uinfo['auth_data'] = str_replace('^_^','\/*',jsonEncode(input('post.auth_data/a')));
		$ainfo['platform'] = implode(',',$ainfo['platform']);
        $rinfo = input('post.rinfo/a');
		$ainfo['remote'] = jsonEncode($rinfo);
        if(!in_array($rinfo['type'],$ainfo['remotearr'])){
            return json(['status'=>0,'msg'=>'“可选附件存储类型”必须包含“默认附件存储类型”']);
        }
        $ainfo['remotearr'] = implode(',',$ainfo['remotearr']);
		//$asetinfo = input('post.asetinfo/a');
    if(getcustom('plug_siming')){
      if(!isset($ainfo['pc_index_data'])){
        $ainfo['pc_index_data'] = [];
      }
      if(!isset($ainfo['mobile_index_data'])){
        $ainfo['mobile_index_data'] = [];
      }
      $ainfo['pc_index_data'] = implode(',', $ainfo['pc_index_data']);
      $ainfo['mobile_index_data'] = implode(',', $ainfo['mobile_index_data']);
    }

		$hasun = Db::name('admin_user')->where('id','<>',$uinfo['id'])->where('un',$uinfo['un'])->find();
		if($hasun){
			return json(['status'=>0,'msg'=>'该账号已存在']);
		}
		$ainfo['endtime'] = strtotime($ainfo['endtime']);
		if(getcustom('wx_fws_liuliangzhu')) {
		    if($ainfo['ad_ratio'] > 0){
                $setratio = Wechat::setCustomShareRatio($ainfo['id'],'wx',$ainfo['ad_ratio']);
                if(!$setratio){
                    $ainfo['ad_ratio'] = 0;
                } 
            }
        }
        
		if(getcustom('admin_login_page')){
            $webinfo = Db::name('sysset')->where('name','webinfo')->value('value');
		    $webinfo = json_decode($webinfo,true);
			if($webinfo['open_login_page'] == 1){	
                if(empty($ainfo['login_page_code'])){
                    $ainfo['login_page_code'] = make_rand_code(5,8);
                    $has_login_page_code = Db::name('admin')->where('id','<>',$ainfo['id'])->where('login_page_code',$ainfo['login_page_code'])->find();
                    if($has_login_page_code){
                        $ainfo['login_page_code'] = make_rand_code(5,8);
                    }              
                }
                $has_login_page_code = Db::name('admin')->where('id','<>',$ainfo['id'])->where('login_page_code',$ainfo['login_page_code'])->find();
                if($has_login_page_code){
                    return json(['status'=>0,'msg'=>'该独立登录编码已存在']);
                }
            }
        }
        if(getcustom('user_disabled_auth_data')) {
            $disabled_auth_data = input('param.disabled_auth_data','');
            if($disabled_auth_data){
                //开启了权限禁用【账号下所有非isadmin=1的管理员禁用权限不可操作】
                $ainfo['disabled_auth_data'] = json_encode($disabled_auth_data);
            }else{
                //无权限禁用【账号下所有的管理员可操作】
                $ainfo['disabled_auth_data'] = '';
            }
        }
		if($ainfo['id']){
			Db::name('admin')->where('id',$ainfo['id'])->update($ainfo);
			\app\commons\System::plog('编辑用户'.$ainfo['id'],1);
		}else{
			$ainfo['createtime'] = time();
			$ainfo['token'] = random(10);
			$ainfo['id'] = Db::name('admin')->insertGetId($ainfo);
			\app\commons\System::initaccount($ainfo['id']);
			\app\commons\System::plog('添加用户'.$ainfo['id'],1);
		}
		if($uinfo['id']){
			if($uinfo['pwd']!=''){
				$uinfo['pwd'] = md5($uinfo['pwd']);
			}else{
				unset($uinfo['pwd']);
			}
			Db::name('admin_user')->where('id',$uinfo['id'])->update($uinfo);
		}else{
			$uinfo['pwd'] = md5($uinfo['pwd']);
			$uinfo['aid'] = $ainfo['id'];
			$uinfo['createtime'] = time();
			$uinfo['isadmin'] = 1;
			$uinfo['random_str'] = random(16);
			Db::name('admin_user')->insert($uinfo);
		}
        if(getcustom('erp_wangdiantong')) {
            $wdtset = Db::name('wdt_sysset')->where('aid',$ainfo['id'])->find();
            if($wdtset){
                Db::name('wdt_sysset')->where('id',$wdtset['id'])->update(['status'=>$ainfo['wdt_status']]);
            }else{
                Db::name('wdt_sysset')->insert(['status'=>$ainfo['wdt_status'],'aid'=>$ainfo['id'],'createtime'=>time()]);
            }
        }
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}
	//删除
	public function del(){
		$ids = input('post.ids/a');
		foreach($ids as $id){
			if($id == 1) return json(['status'=>0,'msg'=>'总账号不允许删除']);
			$admin = Db::name('admin')->where('id',intval($id))->find();
			Db::name('admin')->where('id',intval($id))->delete();
			Db::name('admin_user')->where('aid',intval($id))->where('bid',0)->delete();
			Db::name('admin_set')->where('aid',intval($id))->delete();
			Db::name('admin_setapp_mp')->where('aid',intval($id))->delete();
			Db::name('admin_setapp_wx')->where('aid',intval($id))->delete();
			Db::name('admin_setapp_alipay')->where('aid',intval($id))->delete();
			Db::name('admin_setapp_app')->where('aid',intval($id))->delete();
			Db::name('admin_setapp_baidu')->where('aid',intval($id))->delete();
			Db::name('admin_setapp_h5')->where('aid',intval($id))->delete();
			Db::name('admin_setapp_qq')->where('aid',intval($id))->delete();
			Db::name('admin_setapp_toutiao')->where('aid',intval($id))->delete();
//            Db::name('admin_upload')->where('aid',intval($id))->delete();
//            Db::name('admin_upload_group')->where('aid',intval($id))->delete();
//            Db::name('business')->where('aid',intval($id))->delete();
//            Db::name('shop_order')->where('aid',intval($id))->delete();
//            Db::name('shop_order_goods')->where('aid',intval($id))->delete();
//            Db::name('member')->where('aid',intval($id))->delete();
		}
		\app\commons\System::plog('删除用户'.implode(',',$ids),1);
		return json(['status'=>1,'msg'=>'删除成功']);
	}

	//下载小程序uniapp代码包
	public function downloaduniapp(){
		if($_SERVER['HTTP_HOST'] == 'v2.diandashop.com' || $_SERVER['HTTP_HOST'] == 'v2a.diandashop.com'){
			return json(['status'=>0,'msg'=>'演示站无下载权限']);
		}
		$aid = input('post.aid');

		$navigationBarBackgroundColor = input('post.navigationBarBackgroundColor');
		$navigationBarTextStyle = input('post.navigationBarTextStyle');
		$admin = Db::name('admin')->where('id',$aid)->find();
		$sysset = Db::name('admin_set')->where('aid',$aid)->find();
		$appapp = Db::name('admin_setapp_app')->where('aid',$aid)->find();

        //import('file',EXTEND_PATH);
		$wxdir = ROOT_PATH.'uniapp';
		$copydir = ROOT_PATH.'upload/temp/uniapp'.$aid;
		\app\commons\File::clear_dir($copydir);
		\app\commons\File::all_copy($wxdir,$copydir);

		//配置文件 pages.json
		$window = array(
			"navigationBarBackgroundColor"=>$navigationBarBackgroundColor,
			"navigationBarTextStyle"=>$navigationBarTextStyle,
			"navigationBarTitleText"=> '',
			"h5"=>["titleNView"=>false]
		);
		
		//页面 pages
		$pagesjson = file_get_contents(ROOT_PATH.'uniapp/pages.json');
		$pagesjson = str_replace('"navigationBarTextStyle": "black"','"navigationBarTextStyle": "'.$navigationBarTextStyle.'"',$pagesjson);
		$pagesjson = str_replace('"navigationBarBackgroundColor": "#F8F8F8"','"navigationBarBackgroundColor": "'.$navigationBarBackgroundColor.'"',$pagesjson);
		
		$pagesjson = str_replace('"navigationBarTextStyle":"black"','"navigationBarTextStyle":"'.$navigationBarTextStyle.'"',$pagesjson);
		$pagesjson = str_replace('"navigationBarBackgroundColor":"#F8F8F8"','"navigationBarBackgroundColor":"'.$navigationBarBackgroundColor.'"',$pagesjson);

		file_put_contents($copydir.'/pages.json',$pagesjson);
		
		//配置信息
		$uniacid = $aid;
		if($admin['domain']){
			$siteroot = 'https://'.$admin['domain'];
		}else{
			$siteroot = str_replace('http://','https://',request()->domain());
		}
		$siteinfostr = 'var siteinfo = {"uniacid":"'.$uniacid.'","siteroot":"'.$siteroot.'"};module.exports = siteinfo;';
		file_put_contents($copydir.'/siteinfo.js',$siteinfostr);
		
		$zipname = uniqid().'.zip';
		$zippath = ROOT_PATH."upload/temp/".$zipname;
		$myfile = fopen($zippath, "w");
		fclose($myfile);
		\app\commons\File::add_file_to_zip($copydir,$zippath,'uniapp_'.$aid);
		$url = PRE_URL."/upload/temp/".$zipname;
		\app\commons\File::remove_dir($copydir);
		\app\commons\System::plog('下载uniapp代码'.$aid,1);
		return json(['status'=>1,'msg'=>'打包成功','url'=>$url]);
	}

	//复制数据
	public function copydata(){
		set_time_limit(0);
		ini_set('memory_limit','-1');

		$info = input('post.info/a');
		$fromid = $info['fromid'];
		$toid = $info['toid'];
		$delold = $info['delold'];
		$module_data = input('post.module_data');
		if(!$module_data)  return json(['status'=>0,'msg'=>'请选择要复制的数据']);
		$fromadmin = Db::name('admin')->where('id',$fromid)->find();
		if(!$fromadmin) return json(['status'=>0,'msg'=>'来源账号未查找到']);
		$toadmin = Db::name('admin')->where('id',$toid)->find();
		if(!$toadmin) return json(['status'=>0,'msg'=>'要复制到的账号未查找到']);
		
		foreach($module_data as $modulename){
            if($modulename == '会员等级'){
                $member_level = ['-1'=>-1,0=>0];
                if($delold == 1){
                    Db::name('member_level')->where('aid',$toid)->where('isdefault',0)->delete();
                }
                $fromdata = Db::name('member_level')->where('aid',$fromid)->select()->toArray();
                foreach($fromdata as $data){
                    $oldid = $data['id'];
                    unset($data['id']);
                    $data['aid'] = $toid;
                    $data['from_id'] = $oldid;
                    if($data['isdefault'] == 1){
                        $default_level = Db::name('member_level')->where('aid',$toid)->where('isdefault',1)->find();
                        if($default_level){
                            Db::name('member_level')->where('aid',$toid)->where('isdefault',1)->update($data);
                            $member_level[$oldid]=$default_level['id'];
                        } else {
                            $id = Db::name('member_level')->insertGetId($data);
                            $member_level[$oldid]=$id;
                        }
                    }else{
                        $id = Db::name('member_level')->insertGetId($data);
                        $member_level[$oldid]=$id;
                    }
                }
            }
            if($modulename == '优惠券'){
                if($delold == 1) Db::name('coupon')->where('aid',$toid)->where('bid',0)->delete();
                $fromdata = Db::name('coupon')->where('aid',$fromid)->where('bid',0)->select()->toArray();
                foreach($fromdata as $data){
                    $oldid = $data['id'];
                    $data['id'] = '';
                    $data['aid'] = $toid;
                    $id = Db::name('coupon')->insertGetId($data);
                }
            }
			if($modulename == '商城商品'){
				//分类
				if($delold == 1) Db::name('shop_category')->where('aid',$toid)->delete();
				$fromdata = Db::name('shop_category')->where('aid',$fromid)->select()->toArray();
				$shop_category_ids_map = [];
				$shop_category_ids_map['0'] = '0';
				foreach($fromdata as $data){
					$oldid = $data['id'];
					$data['id'] = '';
					$data['aid'] = $toid;
					$data['pid'] = $shop_category_ids_map[$data['pid']];
                    $data['from_id'] = $oldid;
					$shop_category_ids_map[$oldid] = Db::name('shop_category')->insertGetId($data);
				}
				//分组
				if($delold == 1) Db::name('shop_group')->where('aid',$toid)->delete();
				$fromdata = Db::name('shop_group')->where('aid',$fromid)->select()->toArray();
				$shop_group_ids_map = [];
				$shop_group_ids_map['0'] = '0';
				foreach($fromdata as $data){
					$oldid = $data['id'];
					$data['id'] = '';
					$data['aid'] = $toid;
                    $data['from_id'] = $oldid;
					$shop_group_ids_map[$oldid] = Db::name('shop_group')->insertGetId($data);
				}
				//服务
				if($delold == 1) Db::name('shop_fuwu')->where('aid',$toid)->where('bid',0)->delete();
				$fromdata = Db::name('shop_fuwu')->where('aid',$fromid)->where('bid',0)->select()->toArray();
				$shop_fuwu_ids_map = [];
				$shop_fuwu_ids_map['0'] = '0';
				foreach($fromdata as $data){
                    $oldid = $data['id'];
					$data['id'] = '';
					$data['aid'] = $toid;
                    $data['from_id'] = $oldid;
					$shop_fuwu_ids_map[$oldid] = Db::name('shop_fuwu')->insertGetId($data);
				}
				//参数
				if($delold == 1) Db::name('shop_param')->where('aid',$toid)->delete();
				$fromdata = Db::name('shop_param')->where('aid',$fromid)->where('bid',0)->select()->toArray();
				foreach($fromdata as $data){
					$oldid = $data['id'];
					$data['id'] = '';
					$data['aid'] = $toid;
					$data['cid'] = $this->getnewids($shop_category_ids_map,$data['cid']);
                    $data['from_id'] = $oldid;
					Db::name('shop_param')->insertGetId($data);
				}
				//商品
				if($delold == 1){
					$proids = Db::name('shop_product')->where('aid',$toid)->where('bid',0)->column('id');
					Db::name('shop_product')->where('aid',$toid)->where('bid',0)->delete();
					Db::name('shop_guige')->where('aid',$toid)->where('proid','in',$proids)->delete();
				}
				$fromdata = Db::name('shop_product')->where('aid',$fromid)->where('bid',0)->select()->toArray();

				foreach($fromdata as $data){
					$oldid = $data['id'];
					unset($data['wxvideo_product_id']);
					unset($data['wxvideo_edit_status']);
					unset($data['wxvideo_status']);
					unset($data['wxvideo_reject_reason']);
					$data['id'] = '';
					$data['aid'] = $toid;
					$data['cid'] = $this->getnewids($shop_category_ids_map,$data['cid']);
					$data['gid'] = $this->getnewids($shop_group_ids_map,$data['gid']);
					$data['fwid'] = $this->getnewids($shop_fuwu_ids_map,$data['fwid']);
                    $data['from_id'] = $oldid;
					if($data['freighttype'] == 0) $data['freighttype'] = 1;
					//if($data['commissionset'] == 1 || $data['commissionset'] == 2 || $data['commissionset'] == 3) $data['commissionset'] = 0;
                    //会员价
                    if($data['lvprice_data']){
                        $lvprice_data = json_decode($data['lvprice_data'],true);
                        $lvprice_data_new = [];
                        foreach ($lvprice_data as $lvid => $lvprice){
                            $lvprice_data_new[$member_level[$lvid]] = $lvprice;
                        }
                        $data['lvprice_data'] = json_encode($lvprice_data_new);
                    }
                    //分销
                    if($data['commissiondata1']){
                        $cmdata = json_decode($data['commissiondata1'],true);
                        $cmdata_new = [];
                        foreach ($cmdata as $lvid => $cmvalue){
                            $cmdata_new[$member_level[$lvid]] = $cmvalue;
                        }
                        $data['commissiondata1'] = json_encode($cmdata_new);
                    }
                    if($data['commissiondata2']){
                        $cmdata = json_decode($data['commissiondata2'],true);
                        $cmdata_new = [];
                        foreach ($cmdata as $lvid => $cmvalue){
                            $cmdata_new[$member_level[$lvid]] = $cmvalue;
                        }
                        $data['commissiondata2'] = json_encode($cmdata_new);
                    }
                    if($data['commissiondata3']){
                        $cmdata = json_decode($data['commissiondata3'],true);
                        $cmdata_new = [];
                        foreach ($cmdata as $lvid => $cmvalue){
                            $cmdata_new[$member_level[$lvid]] = $cmvalue;
                        }
                        $data['commissiondata3'] = json_encode($cmdata_new);
                    }
                    if($data['gdfenhongdata1']){
                        $cmdata = json_decode($data['gdfenhongdata1'],true);
                        $cmdata_new = [];
                        foreach ($cmdata as $lvid => $cmvalue){
                            $cmdata_new[$member_level[$lvid]] = $cmvalue;
                        }
                        $data['gdfenhongdata1'] = json_encode($cmdata_new);
                    }
                    if($data['gdfenhongdata2']){
                        $cmdata = json_decode($data['gdfenhongdata2'],true);
                        $cmdata_new = [];
                        foreach ($cmdata as $lvid => $cmvalue){
                            $cmdata_new[$member_level[$lvid]] = $cmvalue;
                        }
                        $data['gdfenhongdata2'] = json_encode($cmdata_new);
                    }
                    if($data['teamfenhongdata1']){
                        $cmdata = json_decode($data['teamfenhongdata1'],true);
                        $cmdata_new = [];
                        foreach ($cmdata as $lvid => $cmvalue){
                            $cmdata_new[$member_level[$lvid]] = $cmvalue;
                        }
                        $data['teamfenhongdata1'] = json_encode($cmdata_new);
                    }
                    if($data['teamfenhongdata2']){
                        $cmdata = json_decode($data['teamfenhongdata2'],true);
                        $cmdata_new = [];
                        foreach ($cmdata as $lvid => $cmvalue){
                            $cmdata_new[$member_level[$lvid]] = $cmvalue;
                        }
                        $data['teamfenhongdata2'] = json_encode($cmdata_new);
                    }
                    if($data['teamfenhongdata2']){
                        $cmdata = json_decode($data['teamfenhongdata2'],true);
                        $cmdata_new = [];
                        foreach ($cmdata as $lvid => $cmvalue){
                            $cmdata_new[$member_level[$lvid]] = $cmvalue;
                        }
                        $data['teamfenhongdata2'] = json_encode($cmdata_new);
                    }
                    if($data['areafenhongdata1']){
                        $cmdata = json_decode($data['areafenhongdata1'],true);
                        $cmdata_new = [];
                        foreach ($cmdata as $lvid => $cmvalue){
                            $cmdata_new[$member_level[$lvid]] = $cmvalue;
                        }
                        $data['areafenhongdata1'] = json_encode($cmdata_new);
                    }
                    if($data['areafenhongdata2']){
                        $cmdata = json_decode($data['areafenhongdata2'],true);
                        $cmdata_new = [];
                        foreach ($cmdata as $lvid => $cmvalue){
                            $cmdata_new[$member_level[$lvid]] = $cmvalue;
                        }
                        $data['areafenhongdata2'] = json_encode($cmdata_new);
                    }
                    
                    //显示等级
                    if($data['showtj'] !== '-1'){
                        $data['showtj'] = $this->getnewids($member_level,$data['showtj']);
                        if(!$data['showtj']){
                        	$data['showtj'] = -1;
                        }
                    }

                    //购买等级
                    if($data['gettj'] !== '-1'){
                        $data['gettj'] = $this->getnewids($member_level,$data['gettj']);
                        if(!$data['gettj']){
                        	$data['gettj'] = -1;
                        }
                    }

                    if($data['detail']){
                        //处理tab组件
                        $detail = json_decode($data['detail'],true);
                        if($detail){
                            foreach ($detail as $k => $item){
                                if($item['temp'] == 'tab'){
                                    $detail[$k]['id'] = $item['id'].rand(0,9999);
                                    $tablist = Db::name('designerpage_tab')->where('aid',$fromid)->where('tabid',$item['id'])->select()->toArray();
                                    if($tablist){
                                        foreach ($tablist as $item2){
                                            unset($item2['id']);
                                            $item2['aid'] = $toid;
                                            $item2['tabid'] = $detail[$k]['id'];
                                            Db::name('designerpage_tab')->insert($item2);
                                        }
                                    }
                                }
                            }
                            $data['detail'] = json_encode($detail);
                        }
                    }
					$id = Db::name('shop_product')->insertGetId($data);
					$gglist = Db::name('shop_guige')->where('aid',$fromid)->where('proid',$oldid)->select()->toArray();
					foreach($gglist as $gg){
                        $oldid = $gg['id'];
						$gg['id'] = '';
						$gg['aid'] = $toid;
						$gg['proid'] = $id;
                        $gg['from_id'] = $oldid;
                        //会员价
                        if($gg['lvprice_data']){
                            $lvprice_data = json_decode($gg['lvprice_data'],true);
                            $lvprice_data_new = [];
                            foreach ($lvprice_data as $lvid => $lvprice){
                                $lvprice_data_new[$member_level[$lvid]] = $lvprice;
                            }
                            $gg['lvprice_data'] = json_encode($lvprice_data_new);
                        }
						Db::name('shop_guige')->insert($gg);
					}
				}
			}
			if($modulename == '拼团商品'){
				//分类
				if($delold == 1) Db::name('collage_category')->where('aid',$toid)->delete();
				$fromdata = Db::name('collage_category')->where('aid',$fromid)->select()->toArray();
				$collage_category_ids_map = [];
				$collage_category_ids_map['0'] = '0';
				foreach($fromdata as $data){
					$oldid = $data['id'];
					$data['id'] = '';
					$data['aid'] = $toid;
					$data['pid'] = $collage_category_ids_map[$data['pid']];
					$collage_category_ids_map[$oldid] = Db::name('collage_category')->insertGetId($data);
				}
				//商品
				if($delold == 1){
					$proids = Db::name('collage_product')->where('aid',$toid)->where('bid',0)->column('id');
					Db::name('collage_product')->where('aid',$toid)->where('bid',0)->delete();
					Db::name('collage_guige')->where('aid',$toid)->where('proid','in',$proids)->delete();
				}
				$fromdata = Db::name('collage_product')->where('aid',$fromid)->where('bid',0)->select()->toArray();
				foreach($fromdata as $data){
					$oldid = $data['id'];
					$data['id'] = '';
					$data['aid'] = $toid;
					$data['cid'] = $this->getnewids($collage_category_ids_map,$data['cid']);
					if($data['freighttype'] == 0) $data['freighttype'] = 1;
					//if($data['commissionset'] == 1 || $data['commissionset'] == 2 || $data['commissionset'] == 3) $data['commissionset'] = 0;
					$id = Db::name('collage_product')->insertGetId($data);
					$gglist = Db::name('collage_guige')->where('aid',$fromid)->where('proid',$oldid)->select()->toArray();
					foreach($gglist as $gg){
						$gg['id'] = '';
						$gg['aid'] = $toid;
						$gg['proid'] = $id;
						Db::name('collage_guige')->insert($gg);
					}
				}
			}
			if($modulename == '砍价商品'){
				//商品
				if($delold == 1) Db::name('kanjia_product')->where('aid',$toid)->where('bid',0)->delete();
				$fromdata = Db::name('kanjia_product')->where('aid',$fromid)->where('bid',0)->select()->toArray();
				foreach($fromdata as $data){
					$oldid = $data['id'];
					$data['id'] = '';
					$data['aid'] = $toid;
					if($data['freighttype'] == 0) $data['freighttype'] = 1;
					$id = Db::name('kanjia_product')->insertGetId($data);
				}
			}
			if($modulename == '秒杀商品'){
				if($delold == 1){
					$proids = Db::name('seckill_product')->where('aid',$toid)->where('bid',0)->column('id');
					Db::name('seckill_product')->where('aid',$toid)->where('bid',0)->delete();
					Db::name('seckill_guige')->where('aid',$toid)->where('proid','in',$proids)->delete();
				}
				$fromdata = Db::name('seckill_product')->where('aid',$fromid)->where('bid',0)->select()->toArray();
				foreach($fromdata as $data){
					$oldid = $data['id'];
					$data['id'] = '';
					$data['aid'] = $toid;
					if($data['freighttype'] == 0) $data['freighttype'] = 1;
					//if($data['commissionset'] == 1 || $data['commissionset'] == 2 || $data['commissionset'] == 3) $data['commissionset'] = 0;
					$id = Db::name('seckill_product')->insertGetId($data);
					$gglist = Db::name('seckill_guige')->where('aid',$fromid)->where('proid',$oldid)->select()->toArray();
					foreach($gglist as $gg){
						$gg['id'] = '';
						$gg['aid'] = $toid;
						$gg['proid'] = $id;
						Db::name('seckill_guige')->insert($gg);
					}
				}
			}
			if($modulename == '团购商品'){
				//分类
				if($delold == 1) Db::name('tuangou_category')->where('aid',$toid)->where('bid',0)->delete();
				$fromdata = Db::name('tuangou_category')->where('aid',$fromid)->where('bid',0)->select()->toArray();
				$tuangou_category_ids_map = [];
				$tuangou_category_ids_map['0'] = '0';
				foreach($fromdata as $data){
					$oldid = $data['id'];
					$data['id'] = '';
					$data['aid'] = $toid;
					$data['pid'] = $tuangou_category_ids_map[$data['pid']];
					$tuangou_category_ids_map[$oldid] = Db::name('tuangou_category')->insertGetId($data);
				}
				//商品
				if($delold == 1) Db::name('tuangou_product')->where('aid',$toid)->where('bid',0)->delete();
				$fromdata = Db::name('tuangou_product')->where('aid',$fromid)->where('bid',0)->select()->toArray();
				foreach($fromdata as $data){
					$oldid = $data['id'];
					$data['id'] = '';
					$data['aid'] = $toid;
					$data['cid'] = $this->getnewids($tuangou_category_ids_map,$data['cid']);
					if($data['freighttype'] == 0) $data['freighttype'] = 1;
					//if($data['commissionset'] == 1 || $data['commissionset'] == 2 || $data['commissionset'] == 3) $data['commissionset'] = 0;
					$id = Db::name('tuangou_product')->insertGetId($data);
				}
			}
			if($modulename == '积分兑换商品'){
				//分类
				if($delold == 1) Db::name('scoreshop_category')->where('aid',$toid)->delete();
				$fromdata = Db::name('scoreshop_category')->where('aid',$fromid)->select()->toArray();
				$scoreshop_category_ids_map = [];
				$scoreshop_category_ids_map['0'] = '0';
				foreach($fromdata as $data){
					$oldid = $data['id'];
					$data['id'] = '';
					$data['aid'] = $toid;
					$data['pid'] = $scoreshop_category_ids_map[$data['pid']];
					$scoreshop_category_ids_map[$oldid] = Db::name('scoreshop_category')->insertGetId($data);
				}
				//商品
				if($delold == 1) Db::name('scoreshop_product')->where('aid',$toid)->delete();
				$fromdata = Db::name('scoreshop_product')->where('aid',$fromid)->select()->toArray();
				foreach($fromdata as $data){
					$oldid = $data['id'];
					$data['id'] = '';
					$data['aid'] = $toid;
					$data['cid'] = $this->getnewids($scoreshop_category_ids_map,$data['cid']);
					if($data['freighttype'] == 0) $data['freighttype'] = 1;
					//if($data['commissionset'] == 1 || $data['commissionset'] == 2 || $data['commissionset'] == 3) $data['commissionset'] = 0;
					$id = Db::name('scoreshop_product')->insertGetId($data);
				}
			}
			if($modulename == '幸运拼团商品'){
				//分类
				if($delold == 1) Db::name('lucky_collage_category')->where('aid',$toid)->delete();
				$fromdata = Db::name('lucky_collage_category')->where('aid',$fromid)->select()->toArray();
				$luckycollage_category_ids_map = [];
				$luckycollage_category_ids_map['0'] = '0';
				foreach($fromdata as $data){
					$oldid = $data['id'];
					$data['id'] = '';
					$data['aid'] = $toid;
					$data['pid'] = $luckycollage_category_ids_map[$data['pid']];
					$luckycollage_category_ids_map[$oldid] = Db::name('lucky_collage_category')->insertGetId($data);
				}
				//商品
				if($delold == 1){
					$proids = Db::name('lucky_collage_product')->where('aid',$toid)->where('bid',0)->column('id');
					Db::name('lucky_collage_product')->where('aid',$toid)->where('bid',0)->delete();
					Db::name('lucky_collage_guige')->where('aid',$toid)->where('proid','in',$proids)->delete();
				}
				$fromdata = Db::name('lucky_collage_product')->where('aid',$fromid)->where('bid',0)->select()->toArray();
				foreach($fromdata as $data){
					$oldid = $data['id'];
					$data['id'] = '';
					$data['aid'] = $toid;
					$data['cid'] = $this->getnewids($luckycollage_category_ids_map,$data['cid']);
					if($data['freighttype'] == 0) $data['freighttype'] = 1;
					//if($data['commissionset'] == 1 || $data['commissionset'] == 2 || $data['commissionset'] == 3) $data['commissionset'] = 0;
					$id = Db::name('lucky_collage_product')->insertGetId($data);
					$gglist = Db::name('lucky_collage_guige')->where('aid',$fromid)->where('proid',$oldid)->select()->toArray();
					foreach($gglist as $gg){
						$gg['id'] = '';
						$gg['aid'] = $toid;
						$gg['proid'] = $id;
						Db::name('lucky_collage_guige')->insert($gg);
					}
				}
			}
			if($modulename == '短视频'){
				//分类
				if($delold == 1) Db::name('shortvideo_category')->where('aid',$toid)->where('bid',0)->delete();
				$fromdata = Db::name('shortvideo_category')->where('aid',$fromid)->where('bid',0)->select()->toArray();
				$shortvideo_category_ids_map = [];
				$shortvideo_category_ids_map['0'] = '0';
				foreach($fromdata as $data){
					$oldid = $data['id'];
					$data['id'] = '';
					$data['aid'] = $toid;
					$shortvideo_category_ids_map[$oldid] = Db::name('shortvideo_category')->insertGetId($data);
				}
				//商品
				if($delold == 1) Db::name('shortvideo')->where('aid',$toid)->where('bid',0)->delete();
				$fromdata = Db::name('shortvideo')->where('aid',$fromid)->where('bid',0)->select()->toArray();
				foreach($fromdata as $data){
					$oldid = $data['id'];
					$data['id'] = '';
					$data['aid'] = $toid;
					$data['mid'] = 0;
					$data['cid'] = $this->getnewids($shortvideo_category_ids_map,$data['cid']);
					$id = Db::name('shortvideo')->insertGetId($data);
				}
			}
			if($modulename == '文章列表'){
				//分类
				if($delold == 1) Db::name('article_category')->where('aid',$toid)->where('bid',0)->delete();
				$fromdata = Db::name('article_category')->where('aid',$fromid)->where('bid',0)->select()->toArray();
				$article_category_ids_map = [];
				$article_category_ids_map['0'] = '0';
				foreach($fromdata as $data){
					$oldid = $data['id'];
					$data['id'] = '';
					$data['aid'] = $toid;
					$data['pid'] = $article_category_ids_map[$data['pid']];
					$article_category_ids_map[$oldid] = Db::name('article_category')->insertGetId($data);
				}
				//文章
				if($delold == 1) Db::name('article')->where('aid',$toid)->where('bid',0)->delete();
				$fromdata = Db::name('article')->where('aid',$fromid)->where('bid',0)->select()->toArray();
				foreach($fromdata as $data){
					$oldid = $data['id'];
					$data['id'] = '';
					$data['aid'] = $toid;
					$data['cid'] = $this->getnewids($article_category_ids_map,$data['cid']);
					$id = Db::name('article')->insertGetId($data);
				}
			}
			if($modulename == '用户论坛'){
				//分类
				if($delold == 1) Db::name('luntan_category')->where('aid',$toid)->delete();
				$fromdata = Db::name('luntan_category')->where('aid',$fromid)->select()->toArray();
				$luntan_category_ids_map = [];
				$luntan_category_ids_map['0'] = '0';
				foreach($fromdata as $data){
					$oldid = $data['id'];
					$data['id'] = '';
					$data['aid'] = $toid;
					$data['pid'] = $luntan_category_ids_map[$data['pid']];
					$luntan_category_ids_map[$oldid] = Db::name('luntan_category')->insertGetId($data);
				}
				//帖子
				if($delold == 1) Db::name('luntan')->where('aid',$toid)->delete();
				$fromdata = Db::name('luntan')->where('aid',$fromid)->select()->toArray();
				foreach($fromdata as $data){
					$oldid = $data['id'];
					$data['id'] = '';
					$data['aid'] = $toid;
					$data['cid'] = $this->getnewids($luntan_category_ids_map,$data['cid']);
					$id = Db::name('luntan')->insertGetId($data);
				}
			}
			if($modulename == '预约服务商品'){
				//分类
				if($delold == 1) Db::name('yuyue_category')->where('aid',$toid)->where('bid',0)->delete();
				$fromdata = Db::name('yuyue_category')->where('aid',$fromid)->where('bid',0)->select()->toArray();
				$yuyue_category_ids_map = [];
				$yuyue_category_ids_map['0'] = '0';
				foreach($fromdata as $data){
					$oldid = $data['id'];
					$data['id'] = '';
					$data['aid'] = $toid;
					$data['pid'] = $yuyue_category_ids_map[$data['pid']];
					$yuyue_category_ids_map[$oldid] = Db::name('yuyue_category')->insertGetId($data);
				}
				//服务
				if($delold == 1) Db::name('yuyue_fuwu')->where('aid',$toid)->where('bid',0)->delete();
				$fromdata = Db::name('yuyue_fuwu')->where('aid',$fromid)->where('bid',0)->select()->toArray();
				$yuyue_fuwu_ids_map = [];
				$yuyue_fuwu_ids_map['0'] = '0';
				foreach($fromdata as $data){
					$oldid = $data['id'];
					$data['id'] = '';
					$data['aid'] = $toid;
					$yuyue_fuwu_ids_map[$oldid] = Db::name('yuyue_fuwu')->insertGetId($data);
				}
				//商品
				if($delold == 1){
					$proids = Db::name('yuyue_product')->where('aid',$toid)->where('bid',0)->column('id');
					Db::name('yuyue_product')->where('aid',$toid)->where('bid',0)->delete();
					Db::name('yuyue_guige')->where('aid',$toid)->where('proid','in',$proids)->delete();
				}
				$fromdata = Db::name('yuyue_product')->where('aid',$fromid)->where('bid',0)->select()->toArray();
				foreach($fromdata as $data){
					$oldid = $data['id'];
					$data['id'] = '';
					$data['aid'] = $toid;
					$data['cid'] = $this->getnewids($yuyue_category_ids_map,$data['cid']);
					$data['fwid'] = $this->getnewids($yuyue_fuwu_ids_map,$data['fwid']);
					//if($data['commissionset'] == 1 || $data['commissionset'] == 2 || $data['commissionset'] == 3) $data['commissionset'] = 0;
					$id = Db::name('yuyue_product')->insertGetId($data);
					$gglist = Db::name('yuyue_guige')->where('aid',$fromid)->where('proid',$oldid)->select()->toArray();
					foreach($gglist as $gg){
						$gg['id'] = '';
						$gg['aid'] = $toid;
						$gg['proid'] = $id;
						Db::name('yuyue_guige')->insert($gg);
					}
				}
			}
			if($modulename == '知识付费课程'){
				//分类
				if($delold == 1) Db::name('kecheng_category')->where('aid',$toid)->where('bid',0)->delete();
				$fromdata = Db::name('kecheng_category')->where('aid',$fromid)->where('bid',0)->select()->toArray();
				$kecheng_category_ids_map = [];
				$kecheng_category_ids_map['0'] = '0';
				foreach($fromdata as $data){
					$oldid = $data['id'];
					$data['id'] = '';
					$data['aid'] = $toid;
					$data['pid'] = $kecheng_category_ids_map[$data['pid']];
					$kecheng_category_ids_map[$oldid] = Db::name('kecheng_category')->insertGetId($data);
				}
				$fromdata = Db::name('kecheng_list')->where('aid',$fromid)->where('bid',0)->select()->toArray();
				foreach($fromdata as $data){
					$oldid = $data['id'];
					$data['id'] = '';
					$data['aid'] = $toid;
					$data['cid'] = $this->getnewids($kecheng_category_ids_map,$data['cid']);
					$id = Db::name('kecheng_list')->insertGetId($data);
					$chapterlist = Db::name('kecheng_chapter')->where('aid',$fromid)->where('kcid',$oldid)->select()->toArray();
					foreach($chapterlist as $chapter){
						$chapter['id'] = '';
						$chapter['aid'] = $toid;
						$chapter['kcid'] = $id;
						Db::name('kecheng_chapter')->insert($chapter);
					}
					$tikulist = Db::name('kecheng_tiku')->where('aid',$fromid)->where('kcid',$oldid)->select()->toArray();
					foreach($tikulist as $tiku){
						$tiku['id'] = '';
						$tiku['aid'] = $toid;
						$tiku['kcid'] = $id;
						Db::name('kecheng_tiku')->insert($tiku);
					}
				}
			}
			if($modulename == '餐饮菜品'){
				//分类
				if($delold == 1) Db::name('restaurant_product_category')->where('aid',$toid)->where('bid',0)->delete();
				$fromdata = Db::name('restaurant_product_category')->where('aid',$fromid)->where('bid',0)->select()->toArray();
				$restaurant_category_ids_map = [];
				$restaurant_category_ids_map['0'] = '0';
				foreach($fromdata as $data){
					$oldid = $data['id'];
					$data['id'] = '';
					$data['aid'] = $toid;
					$data['pid'] = $restaurant_category_ids_map[$data['pid']];
					$restaurant_category_ids_map[$oldid] = Db::name('restaurant_product_category')->insertGetId($data);
				}
				//商品
				if($delold == 1){
					$proids = Db::name('restaurant_product')->where('aid',$toid)->where('bid',0)->column('id');
					Db::name('restaurant_product')->where('aid',$toid)->where('bid',0)->delete();
					Db::name('restaurant_product_guige')->where('aid',$toid)->where('product_id','in',$proids)->delete();
				}
				$fromdata = Db::name('restaurant_product')->where('aid',$fromid)->where('bid',0)->select()->toArray();
				foreach($fromdata as $data){
					$oldid = $data['id'];
					$data['id'] = '';
					$data['aid'] = $toid;
					$data['cid'] = $this->getnewids($restaurant_category_ids_map,$data['cid']);
					if($data['freighttype'] == 0) $data['freighttype'] = 1;
					//if($data['commissionset'] == 1 || $data['commissionset'] == 2 || $data['commissionset'] == 3) $data['commissionset'] = 0;
					$id = Db::name('restaurant_product')->insertGetId($data);
					$gglist = Db::name('restaurant_product_guige')->where('aid',$fromid)->where('product_id',$oldid)->select()->toArray();
					foreach($gglist as $gg){
						$gg['id'] = '';
						$gg['aid'] = $toid;
						$gg['product_id'] = $id;
						Db::name('restaurant_product_guige')->insert($gg);
					}
				}
			}
            if($modulename == '设计页面'){
                if($delold == 1){
                    Db::name('designerpage')->where('aid',$toid)->where('bid',0)->delete();
                }else{
                    Db::name('designerpage')->where('aid',$toid)->where('bid',0)->update(['ishome'=>0]);
                }
                $fromdata = Db::name('designerpage')->where('aid',$fromid)->where('bid',0)->select()->toArray();
                foreach($fromdata as $data){
                    $oldid = $data['id'];
                    $data['id'] = '';
                    $data['aid'] = $toid;

                    $p_contents = json_decode($data['content'],true);

                    foreach ($p_contents as $kk => &$vv) {
           
                    	if($vv['temp'] == 'product'){
                    		if(!empty($vv['params']) && !empty($vv['params']['category'])){
                    			$new_category_id = Db::name('shop_category')->where('aid',$toid)->where('from_id',$vv['params']['category'])->order('id desc')->value('id');
                    			if($new_category_id) $vv['params']['category'] = $new_category_id;
                    		}
                    		if(!empty($vv['data'])){
                    			foreach ($vv['data'] as $ko => &$vo) {
                    				$shop_product_l = Db::name('shop_product')->where('aid',$toid)->where('from_id',$vo['proid'])->field('id,cid')->order('id desc')->find();

                    				if(!empty($shop_product_l)){
                    					$vo['proid'] = $shop_product_l['id'];
                    					$vo['cid'] = $shop_product_l['cid'];
                    				}
                    			}
                    		}
                    	}
                    }
                    $p_contents = json_encode($p_contents);
         
                    $data['content'] = $p_contents;;
                    $id = Db::name('designerpage')->insertGetId($data);
                }
                $fromdata = Db::name('designerpage_tab')->where('aid',$fromid)->select()->toArray();
                foreach($fromdata as $data){
                    $oldid = $data['id'];
                    $data['id'] = '';
                    $data['aid'] = $toid;
                    $id = Db::name('designerpage_tab')->insertGetId($data);
                }
            }
            if(getcustom('health_assessment')){
                if($modulename == '评测量表'){
                    if($delold == 1){
                        Db::name('health_assessment')->where('aid',$toid)->delete();
                        Db::name('health_question')->where('aid',$toid)->delete();
                    }
                    $fromdata = Db::name('health_assessment')->where('aid',$fromid)->where('bid',0)->select()->toArray();
                    foreach($fromdata as $data){
                        $newdata = $data;
                        $newdata['id'] = '';
                        $newdata['aid'] = $toid;
                        $newdata['createtime'] = time();
                        $newid = Db::name('health_assessment')->insertGetId($newdata);
                        $questionlist  = Db::name('health_question')->where('aid',$data['aid'])->where('ha_id',$data['id'])->select()->toArray();
                        $newQuestion = [];
                        foreach ($questionlist as $qk=>$qv){
                            $qv['id'] = '';
                            $qv['aid'] = $toid;
                            $qv['ha_id'] = $newid;
                            $qv['createtime'] = time();
                            $newQuestion[] = $qv;
                        }
                        if($newQuestion){
                            Db::name('health_question')->insertAll($newQuestion);
                        }
                    }
                }
            }
            if($modulename == '用户权限'){
                $fromdata = Db::name('admin_user')->where('aid',$fromid)->where('bid',0)->where('isadmin','>',0)->find();
                $data = [];
                $data['auth_type'] = $fromdata['auth_type'];
                $data['auth_data'] = $fromdata['auth_data'];
                $data['wxauth_data'] = $fromdata['wxauth_data'];
                $data['notice_auth_data'] = $fromdata['notice_auth_data'];
                $data['hexiao_auth_data'] = $fromdata['hexiao_auth_data'];
                Db::name('admin_user')->where('aid',$toid)->where('bid',0)->where('isadmin','>',0)->update($data);
            }
		}
        \app\commons\System::plog('复制用户数据'.$fromid.'到'.$toid);
		return json(['status'=>1,'msg'=>'复制完成']);
	}
    //更新数据
    public function updatedata(){
        if(getcustom('update_user_pro')){
            set_time_limit(0);
            ini_set('memory_limit','-1');

            $pagelimit = 100;
            $pagenum = input('post.pagenum',1);
            $info = input('post.info/a');
            $finish = input('post.finish',[]);
            $fromid = $info['fromid'];
            $toid = $info['toid'];
            $delold = $info['delold'];
            $module_data = input('post.module_data');
            if(!$module_data)  return json(['status'=>0,'msg'=>'请选择要更新的数据']);
            $fromadmin = Db::name('admin')->where('id',$fromid)->find();
            if(!$fromadmin) return json(['status'=>0,'msg'=>'来源账号未查找到']);
            $toadmin = Db::name('admin')->where('id',$toid)->find();
            if(!$toadmin) return json(['status'=>0,'msg'=>'要更新到的账号未查找到']);

            $status = 2;
//            return json(['status'=>$status,'msg'=>'【会员等级】，更新10/100，失败xxx','logid'=>$logid,'sendcount'=>$sendscorelog['sendcount'],'successcount'=>$sendscorelog['successcount'],'errorcount'=>$sendscorelog['errorcount']]);

            foreach($module_data as $modulename){
                if($modulename == '会员等级'){
                    $member_level = ['-1'=>-1,0=>0];
                    $fromdata = Db::name('member_level')->where('aid',$fromid)->select()->toArray();
                    $levelids = [];
                    foreach($fromdata as $data){
                        $oldid = $data['id'];
                        if(in_array('member_level',$finish)){
                            $id = Db::name('member_level')->where('aid',$toid)->where('from_id',$oldid)->value('id');
                            $member_level[$oldid]=$id;
                        }else{
                            unset($data['id']);
                            $data['aid'] = $toid;
                            $data['from_id'] = $oldid;
                            $levelids[]=$oldid;
                            if($data['isdefault'] == 1){
                                $default_level = Db::name('member_level')->where('aid',$toid)->where('isdefault',1)->find();
                                if($default_level){
                                    Db::name('member_level')->where('aid',$toid)->where('isdefault',1)->update($data);
                                    $member_level[$oldid]=$default_level['id'];
                                } else {
                                    $id = Db::name('member_level')->insertGetId($data);
                                    $member_level[$oldid]=$id;
                                }
                            }else{
                                $id = Db::name('member_level')->where('aid',$toid)->where('from_id',$oldid)->value('id');
                                if($id) {
                                    $update = Db::name('member_level')->where('aid',$toid)->where('id',$id)->update($data);
                                }else{
                                    $id = Db::name('member_level')->where('aid',$toid)->insertGetId($data);
                                }
                                $member_level[$oldid]=$id;
                            }
                        }
                    }
                    if(!in_array('member_level',$finish)){
                        Db::name('member_level')->where('aid',$toid)->whereNotNull('from_id')->whereNotIn('from_id',$levelids)->delete();
                        $finish[] = 'member_level';
                        return json(['status'=>$status,'msg'=>'【会员等级】，更新'.count($fromdata).'条，失败0','page'=>0,'finish'=>$finish]);
                    }
                }
                if($modulename == '商城商品'){
                    //分类
                    $fromdata = Db::name('shop_category')->where('aid',$fromid)->select()->toArray();
                    $shop_category_ids_map = [];
                    $shop_category_ids_map['0'] = '0';
                    $shop_category_ids = [];
                    foreach($fromdata as $data){
                        $oldid = $data['id'];
                        if(in_array('shop_category',$finish)){
                            $id = Db::name('shop_category')->where('aid',$toid)->where('from_id',$oldid)->value('id');
                        }else{
                            unset($data['id']);
                            $data['aid'] = $toid;
                            $data['pid'] = $shop_category_ids_map[$data['pid']];
                            $data['from_id'] = $oldid;
                            $shop_category_ids[] = $oldid;
                            $id = Db::name('shop_category')->where('aid',$toid)->where('from_id',$oldid)->value('id');
                            if($id) {
                                $update = Db::name('shop_category')->where('aid',$toid)->where('id',$id)->update($data);
                            }else{
                                $id = Db::name('shop_category')->insertGetId($data);
                            }
                        }
                        $shop_category_ids_map[$oldid] = $id;
                    }
                    if(!in_array('shop_category',$finish)){
                        Db::name('shop_category')->where('aid',$toid)->whereNotNull('from_id')->whereNotIn('from_id',$shop_category_ids)->delete();
                        $finish[] = 'shop_category';
                        return json(['status'=>$status,'msg'=>'【商品分类】，更新'.count($fromdata).'条，失败0','page'=>0,'finish'=>$finish]);
                    }
                    //分组
                    $fromdata = Db::name('shop_group')->where('aid',$fromid)->select()->toArray();
                    $shop_group_ids_map = [];
                    $shop_group_ids_map['0'] = '0';
                    $shop_group_ids=[];
                    foreach($fromdata as $data){
                        $oldid = $data['id'];
                        if(in_array('shop_group',$finish)){
                            $id = Db::name('shop_group')->where('aid',$toid)->where('from_id',$oldid)->value('id');
                        }else{
                            unset($data['id']);
                            $data['aid'] = $toid;
                            $data['from_id'] = $oldid;
                            $shop_group_ids[] = $oldid;
                            $id = Db::name('shop_group')->where('aid',$toid)->where('from_id',$oldid)->value('id');
                            if($id) {
                                $update = Db::name('shop_group')->where('aid',$toid)->where('id',$id)->update($data);
                            }else{
                                $id = Db::name('shop_group')->insertGetId($data);
                            }
                        }
                        $shop_group_ids_map[$oldid] = $id;
                    }
                    if(!in_array('shop_group',$finish)){
                        Db::name('shop_group')->where('aid',$toid)->whereNotNull('from_id')->whereNotIn('from_id',$shop_group_ids)->delete();
                        $finish[] = 'shop_group';
                        return json(['status'=>$status,'msg'=>'【商品分组】，更新'.count($fromdata).'条，失败0','page'=>0,'finish'=>$finish]);
                    }
                    //服务
                    $fromdata = Db::name('shop_fuwu')->where('aid',$fromid)->where('bid',0)->select()->toArray();
                    $shop_fuwu_ids_map = [];
                    $shop_fuwu_ids_map['0'] = '0';
                    $shop_fuwu_ids = [];
                    foreach($fromdata as $data){
                        $oldid = $data['id'];
                        if(in_array('shop_fuwu',$finish)){
                            $id = Db::name('shop_fuwu')->where('aid',$toid)->where('from_id',$oldid)->value('id');
                        }else{
                            unset($data['id']);
                            $data['aid'] = $toid;
                            $data['from_id'] = $oldid;
                            $shop_fuwu_ids[] = $oldid;
                            $id = Db::name('shop_fuwu')->where('aid',$toid)->where('from_id',$oldid)->value('id');
                            if($id) {
                                $update = Db::name('shop_fuwu')->where('aid',$toid)->where('id',$id)->update($data);
                            }else{
                                $id = Db::name('shop_fuwu')->insertGetId($data);
                            }
                        }
                        $shop_fuwu_ids_map[$oldid] = $id;
                    }
                    if(!in_array('shop_fuwu',$finish)){
                        Db::name('shop_fuwu')->where('aid',$toid)->whereNotNull('from_id')->whereNotIn('from_id',$shop_fuwu_ids)->delete();
                        $finish[] = 'shop_fuwu';
                        return json(['status'=>$status,'msg'=>'【商品服务】，更新'.count($fromdata).'条，失败0','page'=>0,'finish'=>$finish]);
                    }
                    //参数
                    if(!in_array('shop_param',$finish)){
                        $shop_param_ids = [];
                        $fromdata = Db::name('shop_param')->where('aid',$fromid)->where('bid',0)->select()->toArray();
                        foreach($fromdata as $data){
                            $oldid = $data['id'];
                            unset($data['id']);
                            $data['aid'] = $toid;
                            $data['cid'] = $this->getnewids($shop_category_ids_map,$data['cid']);
                            $data['from_id'] = $oldid;
                            $shop_param_ids[] = $oldid;
                            $id = Db::name('shop_param')->where('aid',$toid)->where('from_id',$oldid)->value('id');
                            if($id) {
                                $update = Db::name('shop_param')->where('aid',$toid)->where('id',$id)->update($data);
                            }else{
                                $id = Db::name('shop_param')->insertGetId($data);
                            }
                        }
                        Db::name('shop_param')->where('aid',$toid)->whereNotNull('from_id')->whereNotIn('from_id',$shop_param_ids)->delete();
                        $finish[] = 'shop_param';
                        return json(['status'=>$status,'msg'=>'【商品参数】，更新'.count($fromdata).'条，失败0','page'=>0,'finish'=>$finish]);
                    }
                    //商品
                    $shop_product_ids = [];
                    $fromdata = Db::name('shop_product')->where('aid',$fromid)->where('bid',0)->page($pagenum,$pagelimit)->select()->toArray();
                    foreach($fromdata as $data){
                        $oldid = $data['id'];
                        unset($data['wxvideo_product_id']);
                        unset($data['wxvideo_edit_status']);
                        unset($data['wxvideo_status']);
                        unset($data['wxvideo_reject_reason']);
                        unset($data['id']);
                        $data['aid'] = $toid;
                        $data['cid'] = $this->getnewids($shop_category_ids_map,$data['cid']);
                        $data['gid'] = $this->getnewids($shop_group_ids_map,$data['gid']);
                        $data['fwid'] = $this->getnewids($shop_fuwu_ids_map,$data['fwid']);
                        if($data['freighttype'] == 0) $data['freighttype'] = 1;
                        //if($data['commissionset'] == 1 || $data['commissionset'] == 2 || $data['commissionset'] == 3) $data['commissionset'] = 0;
                        if($data['lvprice_data']){
                            $lvprice_data = json_decode($data['lvprice_data'],true);
                            $lvprice_data_new = [];
                            foreach ($lvprice_data as $lvid => $lvprice){
                                $lvprice_data_new[$member_level[$lvid]] = $lvprice;
                            }
                            $data['lvprice_data'] = json_encode($lvprice_data_new);
                        }
                        $data['from_id'] = $oldid;
//                        $shop_product_ids[] = $oldid;
                        //分销
                        if($data['commissiondata1']){
                            $cmdata = json_decode($data['commissiondata1'],true);
                            $cmdata_new = [];
                            foreach ($cmdata as $lvid => $cmvalue){
                                $cmdata_new[$member_level[$lvid]] = $cmvalue;
                            }
                            $data['commissiondata1'] = json_encode($cmdata_new);
                        }
                        if($data['commissiondata2']){
                            $cmdata = json_decode($data['commissiondata2'],true);
                            $cmdata_new = [];
                            foreach ($cmdata as $lvid => $cmvalue){
                                $cmdata_new[$member_level[$lvid]] = $cmvalue;
                            }
                            $data['commissiondata2'] = json_encode($cmdata_new);
                        }
                        if($data['commissiondata3']){
                            $cmdata = json_decode($data['commissiondata3'],true);
                            $cmdata_new = [];
                            foreach ($cmdata as $lvid => $cmvalue){
                                $cmdata_new[$member_level[$lvid]] = $cmvalue;
                            }
                            $data['commissiondata3'] = json_encode($cmdata_new);
                        }
                        if($data['gdfenhongdata1']){
                            $cmdata = json_decode($data['gdfenhongdata1'],true);
                            $cmdata_new = [];
                            foreach ($cmdata as $lvid => $cmvalue){
                                $cmdata_new[$member_level[$lvid]] = $cmvalue;
                            }
                            $data['gdfenhongdata1'] = json_encode($cmdata_new);
                        }
                        if($data['gdfenhongdata2']){
                            $cmdata = json_decode($data['gdfenhongdata2'],true);
                            $cmdata_new = [];
                            foreach ($cmdata as $lvid => $cmvalue){
                                $cmdata_new[$member_level[$lvid]] = $cmvalue;
                            }
                            $data['gdfenhongdata2'] = json_encode($cmdata_new);
                        }
                        if($data['teamfenhongdata1']){
                            $cmdata = json_decode($data['teamfenhongdata1'],true);
                            $cmdata_new = [];
                            foreach ($cmdata as $lvid => $cmvalue){
                                $cmdata_new[$member_level[$lvid]] = $cmvalue;
                            }
                            $data['teamfenhongdata1'] = json_encode($cmdata_new);
                        }
                        if($data['teamfenhongdata2']){
                            $cmdata = json_decode($data['teamfenhongdata2'],true);
                            $cmdata_new = [];
                            foreach ($cmdata as $lvid => $cmvalue){
                                $cmdata_new[$member_level[$lvid]] = $cmvalue;
                            }
                            $data['teamfenhongdata2'] = json_encode($cmdata_new);
                        }
                        if($data['teamfenhongdata2']){
                            $cmdata = json_decode($data['teamfenhongdata2'],true);
                            $cmdata_new = [];
                            foreach ($cmdata as $lvid => $cmvalue){
                                $cmdata_new[$member_level[$lvid]] = $cmvalue;
                            }
                            $data['teamfenhongdata2'] = json_encode($cmdata_new);
                        }
                        if($data['areafenhongdata1']){
                            $cmdata = json_decode($data['areafenhongdata1'],true);
                            $cmdata_new = [];
                            foreach ($cmdata as $lvid => $cmvalue){
                                $cmdata_new[$member_level[$lvid]] = $cmvalue;
                            }
                            $data['areafenhongdata1'] = json_encode($cmdata_new);
                        }
                        if($data['areafenhongdata2']){
                            $cmdata = json_decode($data['areafenhongdata2'],true);
                            $cmdata_new = [];
                            foreach ($cmdata as $lvid => $cmvalue){
                                $cmdata_new[$member_level[$lvid]] = $cmvalue;
                            }
                            $data['areafenhongdata2'] = json_encode($cmdata_new);
                        }
                        //显示等级
                        if($data['showtj'] !== -1){
                            $data['showtj'] = $this->getnewids($member_level,$data['showtj']);
                            if(!$data['showtj']){
	                        	$data['showtj'] = -1;
	                        }
                        }
                        //购买等级
                        if($data['gettj'] !== -1){
                            $data['gettj'] = $this->getnewids($member_level,$data['gettj']);
                            if(!$data['gettj']){
	                        	$data['gettj'] = -1;
	                        }
                        }
                        $id = Db::name('shop_product')->where('aid',$toid)->where('from_id',$oldid)->value('id');
                        if($id) {
                            $update = Db::name('shop_product')->where('aid',$toid)->where('id',$id)->update($data);
                        }else{
                            $id = Db::name('shop_product')->insertGetId($data);
                        }
                        $proid = $id;
                        $shop_guige_ids = [];
                        $gglist = Db::name('shop_guige')->where('aid',$fromid)->where('proid',$oldid)->select()->toArray();
                        foreach($gglist as $gg){
                            $oldid = $gg['id'];
                            unset($gg['id']);
                            $gg['aid'] = $toid;
                            $gg['proid'] = $proid;
                            $gg['from_id'] = $oldid;
                            $shop_guige_ids[]=$oldid;
                            //会员价
                            if($gg['lvprice_data']){
                                $lvprice_data = json_decode($gg['lvprice_data'],true);
                                $lvprice_data_new = [];
                                foreach ($lvprice_data as $lvid => $lvprice){
                                    $lvprice_data_new[$member_level[$lvid]] = $lvprice;
                                }
                                $gg['lvprice_data'] = json_encode($lvprice_data_new);
                            }
                            $id = Db::name('shop_guige')->where('aid',$toid)->where('from_id',$oldid)->value('id');
                            if($id) {
                                $update = Db::name('shop_guige')->where('aid',$toid)->where('id',$id)->update($gg);
                            }else{
                                $id = Db::name('shop_guige')->insertGetId($gg);
                            }
                        }
                        Db::name('shop_guige')->where('aid',$toid)->where('proid',$proid)->whereNotNull('from_id')->whereNotIn('from_id',$shop_guige_ids)->delete();
                    }
                    $shop_product_ids = Db::name('shop_product')->where('aid',$fromid)->where('bid',0)->column('id');
                    Db::name('shop_product')->where('aid',$toid)->whereNotNull('from_id')->whereNotIn('from_id',$shop_product_ids)->delete();

                    $pro_count = Db::name('shop_product')->where('aid',$fromid)->where('bid',0)->count();
                    $update_count = $pagelimit*$pagenum > $pro_count ? $pro_count : $pagelimit*$pagenum;
                    if($pro_count <= $pagelimit*$pagenum){
                        $finish[] = 'shop_product';
                        $status = 1;
                        $msg = '【商城商品】，更新'.$update_count.'/'.$pro_count.'条，失败0';
                    }else{ //还有下一页
                        $status = 2;
                        return json(['status'=>$status,'msg'=>'【商城商品】，更新'.$update_count.'/'.$pro_count.'条，失败0','page'=>$pagenum,'finish'=>$finish]);
                    }
                }
            }
            return json(['status'=>$status,'msg'=>$msg?$msg:'','page'=>$pagenum,'finish'=>$finish]);
        }
    }

    public function group(){
        if (getcustom('admin_user_group')){
            if(request()->isAjax()){
                if(input('param.field') && input('param.order')){
                    $order = input('param.field').' '.input('param.order');
                }else{
                    $order = 'sort desc,id desc';
                }
                $data = Db::name('admin_group')->order($order)->select()->toArray();
                return json(['code'=>0,'msg'=>'查询成功','count'=>count($data),'data'=>$data]);
            }
            return View::fetch();
        }
    }
    //编辑
    public function groupEdit(){
        if (getcustom('admin_user_group')){
            if(input('param.id')){
                $info = Db::name('admin_group')->where('id',input('param.id/d'))->find();
            }else{
                $info = array('id'=>'');
            }
            if(input('param.pid')) $info['pid'] = input('param.pid');
            View::assign('info',$info);
            return View::fetch();
        }
    }
    //保存
    public function groupSave(){
        if (getcustom('admin_user_group')){
            $info = input('post.info/a');
            if($info['id']){
                Db::name('admin_group')->where('id',$info['id'])->update($info);
                \app\commons\System::plog('编辑用户分组'.$info['id']);
            }else{
                $info['createtime'] = time();
                $id = Db::name('admin_group')->insertGetId($info);
                \app\commons\System::plog('添加用户分组'.$id);
            }
            return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
        }
    }
    //删除
    public function groupDel(){
        if (getcustom('admin_user_group')){
            $ids = input('post.ids/a');
            Db::name('admin_group')->where('id','in',$ids)->delete();
            \app\commons\System::plog('删除用户分组'.implode(',',$ids));
            return json(['status'=>1,'msg'=>'删除成功']);
        }
    }
	private function getnewids($arr,$ids){
		if(!$ids) return $ids;
		$ids = explode(',',$ids);
		$newids = [];
		foreach($ids as $id){
			if($arr[$id]){
				$newids[] = $arr[$id];
			}
		}
		return implode(',',$newids);
	}
    
	public function getFwsRatio(){
        if (getcustom('wx_fws_liuliangzhu')){
            $id = input('param.uid/d',0);
            $rdata =  Wechat::getCustomShareRatio($id,'wx');
            if($rdata){
                return json(['status'=>1,'msg'=>'查询成功','data' =>$rdata['share_ratio']]); 
            }else{
                return json(['status'=>0,'msg'=>'未查询到']);
            }
        }
    }
	//服务商流量主 结算收入数据
    public function fwssettle(){
        if (getcustom('wx_fws_liuliangzhu')){
            if(request()->isAjax()){
                $id = input('param.id/d',0);
                $page = input('param.page/d',1);
                $limit = input('param.limt/d',10);
                $start_date = input('param.start_date');
                $end_date = input('param.end_date');

                $data = [
                    'page' => $page,
                    'page_size' => $page ==1?100:$limit,
                    'start_date'  =>$start_date?$start_date:date('Y-m-d'),
                    'end_date'  =>$end_date?$end_date:date('Y-m-d',strtotime('-7 days'))
                ];
                $rdata =  Wechat::getComponentSettleData($id,'wx',$data);
                if($rdata['list']){
                    if(count($rdata['list']) >10){
                        $rdata['list']  = array_slice($rdata['list'],0,10);
                    }
                }else{
                    $rdata['list'] = [];
                    $rdata['total_num']=0;
                    $rdata['jsdata']=[];
                }

                return json(['code'=>0,'msg'=>'查询成功','count'=>$rdata['total_num'],'data'=>$rdata['list'],'summary' => $rdata['jsdata']]);
            }
            return View::fetch();
        }
    }

    public function smsset(){
        if (getcustom('sms_system')){
            $aid = input('param.id/d');
            if(request()->isPost()){
                $adata = input('post.ainfo/a');
                $data = input('post.info/a');

                if($data){
                    foreach ($data as $k => $v){
                        $data[$k] = trim($v);
                    }
                }
                Db::name('admin_set_sms')->where('aid',$aid)->update($data);
                Db::name('admin')->where('id',$aid)->update($adata);
                return json(['code'=>0,'msg'=>'操作成功']);
            }
            $ainfo = Db::name('admin')->where('id',$aid)->find();
            $info = Db::name('admin_set_sms')->where('aid',$aid)->find();
            $info['smstype'] = Db::name('admin_set_sms')->where('aid',0)->value('smstype');
            View::assign('info',$info);
            View::assign('ainfo',$ainfo);
            View::assign('webset',true);
            return View::fetch();
        }
    }

    //充值
    public function recharge(){
        if (getcustom('admin_money')){
            $aid = input('post.rechargeid/d');
            $money = floatval(input('post.rechargemoney'));
            $type = input('post.rechargetype');
            $actionname = '充值';
            if($money == 0 || $money == ''){
                return json(['status'=>0,'msg'=>'请输入金额']);
            }
            if($money < 0) $actionname = '扣费';
            $remark = 'SaaS用户'.$actionname;
            $rs = \app\commons\Admin::addmoney($aid,$money,$remark,$type);
            \app\commons\System::plog('给'.$aid.$actionname.'，金额'.$money);
            if($rs['status']==0) return json($rs);
            return json(['status'=>1,'msg'=>$actionname.'成功']);
        }
    }
    //余额明细
    public function moneylog(){
        if (getcustom('admin_money')){
            if(request()->isAjax()){
                $page = input('param.page');
                $limit = input('param.limit');
                if(input('param.field') && input('param.order')){
                    $order = 'admin_moneylog.'.input('param.field').' '.input('param.order');
                }else{
                    $order = 'admin_moneylog.id desc';
                }
                $where = [];
//                $where[] = ['admin_moneylog.aid','=',aid];

                if(input('param.nickname')) $where[] = ['admin_user.un','like','%'.trim(input('param.nickname')).'%'];
                if(input('param.aid')) $where[] = ['admin_moneylog.aid','=',trim(input('param.aid'))];
                if(input('?param.status') && input('param.status')!=='') $where[] = ['admin_moneylog.status','=',input('param.status')];
                $count = 0 + Db::name('admin_moneylog')->alias('admin_moneylog')->field('admin_user.un,admin_moneylog.*')->join('admin_user admin_user','admin_user.aid=admin_moneylog.aid and admin_user.isadmin>0 and admin_user.bid=0')->where($where)->count();
                $data = Db::name('admin_moneylog')->alias('admin_moneylog')->field('admin_user.un,admin_moneylog.*')->join('admin_user admin_user','admin_user.aid=admin_moneylog.aid and admin_user.isadmin>0 and admin_user.bid=0')->where($where)->page($page,$limit)->order($order)->select()->toArray();
                return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
            }
            return View::fetch();
        }

    }
    //余额明细导出
    public function moneylogexcel(){
        if (getcustom('admin_money')){
           if(input('param.field') && input('param.order')){
                $order = 'admin_moneylog.'.input('param.field').' '.input('param.order');
            }else{
                $order = 'admin_moneylog.id desc';
            }
            $where = [];
//                $where[] = ['admin_moneylog.aid','=',aid];

            if(input('param.nickname')) $where[] = ['admin_user.un','like','%'.trim(input('param.nickname')).'%'];
            if(input('param.aid')) $where[] = ['admin_moneylog.aid','=',trim(input('param.aid'))];
            if(input('?param.status') && input('param.status')!=='') $where[] = ['admin_moneylog.status','=',input('param.status')];
            $list = Db::name('admin_moneylog')->alias('admin_moneylog')->field('admin_user.un,admin_moneylog.*')->join('admin_user admin_user','admin_user.aid=admin_moneylog.aid and admin_user.isadmin>0 and admin_user.bid=0')->where($where)->order($order)->select()->toArray();
            $title = array();
            $title[] = '账号';
            $title[] = '变更金额';
            $title[] = '变更后剩余';
            $title[] = '变更时间';
            $title[] = '支付方式';
            $title[] = '备注';
            $data = array();
            foreach($list as $v){
                $tdata = array();
                $tdata[] = $v['un'];
                $tdata[] = $v['money'];
                $tdata[] = $v['after'];
                $tdata[] = date('Y-m-d H:i:s',$v['createtime']);
                if($v['paytype']=='wxpay') $tdata[] = '微信';
                else if($v['paytype']=='alipay')$tdata[] = '支付宝';
                else if($v['paytype']=='cash')$tdata[] = '现金';
                else if($v['paytype']=='bank')$tdata[] = '银行卡';
                else $tdata[] = '';
                $tdata[] = $v['remark'];
                $data[] = $tdata;
            }
            $this->export_excel($title,$data);
        }

    }
    //余额明细删除
    public function moneylogdel(){
        if (getcustom('admin_money')){
            $ids = input('post.ids/a');
            Db::name('admin_moneylog')->where('id','in',$ids)->delete();
            \app\commons\System::plog('删除余额明细'.implode(',',$ids));
            return json(['status'=>1,'msg'=>'删除成功']);
        }

    }

    public function authorizeSet(){
        if (getcustom('admin_user_authorize')){
            if(request()->isPost()) {
                //提交保存
                $content = input('post.');
                $info = Db::name('sysset')->where('name','user_authorize')->find();
                if($content['auth_type'] == 1){
                    $content['auth_data']  = [];
                }
                $value = $content?str_replace('^_^','\/*',jsonEncode($content)):'';
                if($info){
                    Db::name('sysset')->where('id',$info['id'])->update(['value'=>$value]);
                }else{
                    Db::name('sysset')->where('name','user_authorize')->insert(['value'=>$value,'name'=>'user_authorize']);
                }
                return json(['status'=>1,'msg'=>'设置成功']);
            }else{
                $menudata = \app\commons\Menu::getdata(0, 0);
                $info = Db::name('sysset')->where('name','user_authorize')->find();
                if($info){
                    $value = json_decode($info['value'],true);
                }else{
                    $value = [];
                }
                $auth_type = $value['auth_type']??0;
                if($auth_type==0){
                    $authdata = $value['auth_data']??[];
                    if (empty($menudata)) $menudata = [];
                    foreach ($menudata as $k=>&$v){
                        $v['urlpath'] = '';
                        if($v['path']) $v['urlpath'] = $v['path'].','.str_replace('/*','^_^',$v['authdata']);
                        if($v['child']){
                            foreach ($v['child']  as $k2=>&$v2){
                                $v2['urlpath'] = '';
                                if($v2['path']) $v2['urlpath'] = $v2['path'].','.str_replace('/*','^_^',$v2['authdata']);
                                if($v2['child']){
                                    foreach ($v2['child']  as $k3=>&$v3){
                                        $v3['urlpath'] = '';
                                        if($v3['path']) $v3['urlpath'] = $v3['path'].','.str_replace('/*','^_^',$v3['authdata']);
                                        if(in_array($v3['path'].','.$v3['authdata'],$authdata)){
                                            $v3['checked'] = 'checked';
                                        }else{
                                            $v3['checked'] = '';
                                        }
                                    }
                                }else{
                                    if(in_array($v2['path'].','.$v2['authdata'],$authdata)){
                                        $v2['checked'] = 'checked';
                                    }else{
                                        $v2['checked'] = '';
                                    }
                                }
                            }
                        }
                    }
                }
                //手机端权限
                $wxauth_data = $this->getH5Menudata(0,0);
                foreach ($wxauth_data as $key=>&$v){
                    if(empty($v['tag'])){
                        continue;
                    }
                    $field = $v['tag'];
                    $authdata_db = $value[$field]??[];
                    foreach ($v['child'] as $k2=>&$v2){
                        if(in_array($v2['tag'],$authdata_db)){
                            $v2['checked'] = 'checked';
                        }else{
                            $v2['checked'] = '';
                        }
                    }
                }
                return json(['menudata'=>$menudata,'menudataM'=>$wxauth_data,'auth_type'=>$auth_type]);
            }
        }
    }
    public function getH5Menudata($aid=0,$bid=0){
        $menudata = [];
        $menudata[] = [
            'name'=>'查看权限',
            'tag'=>'wxauth_data',
            'child'=>[
                ['tag'=>'member','name'=>t('会员')],
                ['tag'=>'product','name'=>'商品'],
                ['tag'=>'order','name'=>'订单'],
                ['tag'=>'finance','name'=>'财务'],
                ['tag'=>'zixun','name'=>'咨询']
            ]
        ];
        $childN = [
            ['tag'=>'tmpl_orderconfirm','name'=>'订单提交通知'],
            ['tag'=>'tmpl_orderpay','name'=>'订单支付通知'],
            ['tag'=>'tmpl_ordershouhuo','name'=>'订单收货通知'],
            ['tag'=>'tmpl_ordertui','name'=>'退款申请通知'],
            ['tag'=>'tmpl_withdraw','name'=>'提现申请通知'],
            ['tag'=>'tmpl_uplv','name'=>'升级申请通知'],
            ['tag'=>'tmpl_formsub','name'=>'表单提交通知'],
            ['tag'=>'tmpl_kehuzixun','name'=>'用户咨询通知'],
            ['tag'=>'tmpl_maidanpay','name'=>'买单付款通知'],
        ];
        if(getcustom('shop_stock_warning_notice')){
            $childN[] = ['tag'=>'tmpl_stockwarning','name'=>'库存不足通知'];
        }
        $menudata[] = [
            'name'=>'接收通知权限',
            'tag'=>'notice_auth_data',
            'child'=>$childN
        ];
        $childH = [];
        if(getcustom('member_code') && getcustom('business_update_member_score')){
            $childH[] = ['tag'=>'member_code_buy','name'=>'会员消费'];
        }
        $childH[] = ['tag'=>'shop','name'=>'商城订单'];
        $childH[] = ['tag'=>'collage','name'=>'拼团订单'];
        $childH[] = ['tag'=>'lucky_collage','name'=>'幸运拼团订单'];
        $childH[] = ['tag'=>'cycle','name'=>'周期购'];
        $childH[] = ['tag'=>'kanjia','name'=>'砍价订单'];
        $childH[] = ['tag'=>'seckill','name'=>'秒杀订单'];
        $childH[] = ['tag'=>'yuyue','name'=>'预约订单'];
        $childH[] = ['tag'=>'scoreshop','name'=>t('积分').'兑换'];
        $childH[] = ['tag'=>'coupon','name'=>t('优惠券')];
        $childH[] = ['tag'=>'choujiang','name'=>'抽奖活动'];
        $childH[] = ['tag'=>'restaurant_shop','name'=>'点餐订单'];
        $childH[] = ['tag'=>'restaurant_takeaway','name'=>'外卖订单'];
        $childH[] = ['tag'=>'tuangou','name'=>'团购订单'];
        if(getcustom('freight_selecthxbids')){
            $childH[] = ['tag'=>'shopproduct','name'=>'计次商品'];
        }
        if(getcustom('restaurant_take_food')){
            $childH[] = ['tag'=>'outfood','name'=>'出餐'];
        }
        $menudata[] = ['name'=>'核销权限','tag'=>'hexiao_auth_data','child'=>$childH];
        //餐饮
        $childC = [
            ['tag'=>'restaurant_product','name'=>'菜品管理'],
            ['tag'=>'restaurant_table','name'=>'餐桌设置'],
            ['tag'=>'restaurant_tableWaiter','name'=>'餐桌管理'],
            ['tag'=>'restaurant_shop','name'=>'点餐订单'],
            ['tag'=>'restaurant_takeaway','name'=>'外卖订单'],
            ['tag'=>'restaurant_booking','name'=>'预定'],
            ['tag'=>'restaurant_deposit','name'=>'寄存'],
            ['tag'=>'restaurant_queue','name'=>'排队'],
        ];
        $menudata[] = ['name'=>'餐饮权限','tag'=>'wxauth_data','child'=>$childC];
        return $menudata;
    }
	 public function authorize(){
        if (getcustom('admin_user_authorize')){
            if(request()->isAjax()){
                $where = [];
                $where[] = ['id','>',0];
                if(input('param.keyword')){
                    $where[] = ['name','like','%'.input('param.keyword').'%'];
                }
                $page = input('param.page',1);
                $limit = input('param.limit',20);
                if(input('param.field') && input('param.order')){
                    $order = input('param.field').' '.input('param.order');
                }else{
                    $order = 'id desc';
                }
                $count = 0 + Db::name('admin_authorize')->where($where)->count();
                $data = Db::name('admin_authorize')->where($where)->order($order)->page($page,$limit)->select()->toArray();
                return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
            }
            return View::fetch();
        }
    }
    //编辑
    public function authorizeEdit(){
        if (getcustom('admin_user_authorize')){
            if(input('param.id')){
                $info = Db::name('admin_authorize')->where('id',input('param.id/d'))->find();
            }else{
                $info = array('id'=>'');
            }
            View::assign('info',$info);
            return View::fetch();
        }
    }
    //保存
    public function authorizeSave(){
        if (getcustom('admin_user_authorize')){
            $info = input('post.info/a');
            if($info['id']){
                Db::name('admin_authorize')->where('id',$info['id'])->update($info);
                \app\commons\System::plog('编辑注册授权码'.$info['id']);
            }else{
                $info['createtime'] = time();
                $id = Db::name('admin_authorize')->insertGetId($info);
                \app\commons\System::plog('添加注册授权码'.$id);
            }
            return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
        }
    }
    //删除
    public function authorizeDel(){
        if (getcustom('admin_user_authorize')){
            $ids = input('post.ids/a');
            Db::name('admin_authorize')->where('id','in',$ids)->delete();
            \app\commons\System::plog('删除注册授权码'.implode(',',$ids));
            return json(['status'=>1,'msg'=>'删除成功']);
        }
    }

	//导入
	public function importexcel(){
        if (getcustom('admin_user_authorize')) {
            set_time_limit(0);
            ini_set('memory_limit', -1);
            $file = input('post.file');
            $exceldata = $this->import_excel($file);
            $insertnum = 0;
            $chongfunum = 0;
            $insertAll = [];
            foreach ($exceldata as $data) {
                $indata = [];
                $indata['name'] = $data[0];
                $indata['code'] = $data[1];
                $hasinfo = Db::name('admin_authorize')->where($indata)->count('id');
                if ($hasinfo) {
                    $chongfunum++;
                } else {
                    $indata['createtime'] = time();
                    $insertAll[] = $indata;
                    //1000条执行一次插入
                    if(count($insertAll)==1000){
                        Db::name('admin_authorize')->insertAll($insertAll);
                        $insertAll = [];
                    }
//                    Db::name('admin_authorize')->insert($indata);
                    $insertnum++;
                }
            }
            if($insertAll){
                Db::name('admin_authorize')->insertAll($insertAll);
            }
            //分批导入 1000条导入一次
            if(empty($insertAll)){
                return json(['status' => 0, 'msg' => '无数据导入']);
            }
            \app\commons\System::plog('导入账号');
            if ($chongfunum > 0) {
                return json(['status' => 1, 'msg' => '成功新增' . $insertnum . '条数据，重复' . $chongfunum . '条数据']);
            } else {
                return json(['status' => 1, 'msg' => '成功新增' . $insertnum . '条数据']);
            }
        }
	}


}
