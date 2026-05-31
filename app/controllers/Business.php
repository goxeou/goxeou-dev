<?php


// +----------------------------------------------------------------------
// | 商家管理
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class Business extends Common
{
	public function initialize(){
		parent::initialize();
		if(bid > 0) showmsg('无操作权限');
	}
	 //商品列表
    public function pro(){
        set_time_limit(0);
		ini_set("memory_limit", "-1");
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
            $data = [];
            $data['page_index'] = $page;
            $data['page_size'] = $limit;
            if(input('param.ctime') ){
                $ctime = explode(' ~ ',input('param.ctime'));
                $data['modified_begin'] = $ctime[0];
                $data['modified_end'] = $ctime[1];
            }else{
                $data['modified_begin'] = date('Y-m-d H:i',time()-86400*6);
                $data['modified_end'] = date('Y-m-d H:i',time());
            }
            $params['orgId'] = 'd1bd8120945844edfcf14972d9748323';
	     	$Resdata = \app\commons\Aaa::sendpost($params,'/app/client/shoplist');
	//	 ll($Resdata,'$Resdata');
		    $data = $Resdata['data'];
		    $count = count($Resdata['data']);
            $iscustomoption = 0;
			foreach($data as $k=>$v){
				$status = Db::name('business')->where('aid',aid)->where('autoid',$v['id'])->count();
				$data[$k]['status'] = $status;
				$data[$k]['id'] = $v['id'];
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
    }
    //改状态
	public function prosetst(){
		$st = input('post.st/d');
		$id = input('post.id');
	    $params['id'] = $id;
	    $Resdata = \app\commons\Aaa::sendpost($params,'/app/client/shop/detail');
	
		if ($Resdata['data']) {
		    $info = $Resdata['data'];
			$have = Db::name('business')->where('aid',aid)->where('autoid',$info['id'])->find();
         	if ($have)   return json(['status'=>0,'msg'=>'商户已存在']);
         	 //continue;
     		$data = [];
    		$data['aid'] = aid;
    		$data['name'] = $info['name'].'-'.$info['businessName'];
    		$data['autoid'] = $info['id'];
			$data['brandId'] = $info['brandId'];
			$data['platformId'] = $info['platformId'];
    		$data['status'] = 0;
    		$data['createtime'] = time();
    		$proid = Db::name('business')->insertGetId($data);
		}
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//列表
    public function index(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
				$order = 'sort desc,id desc';
			}
			$where = array();
			$where[] = ['aid','=',aid];
            //if(input('param.cid')) $where[] = ['cid','=',input('param.cid/d')];
			if(input('param.cid')) $where[] = Db::raw("find_in_set(".input('param.cid/d').",cid)");
			if(input('param.name')) $where[] = ['name','like','%'.input('param.name').'%'];
			if(input('?param.status') && input('param.status')!==''){
				$where[] = ['status','=',input('param.status')];
			}
            if(input('?param.is_open') && input('param.is_open')!==''){
                $where[] = ['is_open','=',input('param.is_open')];
            }
		$bset = Db::name('business_sysset')->where('aid',aid)->find();
			$count = 0 + Db::name('business')->where($where)->count();
			$carr = Db::name('business_category')->where('aid',aid)->column('name','id');
			$data = Db::name('business')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			foreach($data as $k=>$v){
				$cnames = [];
				if($v['cid']){
					$cids = explode(',',$v['cid']);
					foreach($cids as $cid){
						$cnames[] = $carr[$cid];
					}
				}
				$data[$k]['cname'] = implode(',',$cnames);
				if($v['mid']){
					$member = Db::name('member')->where('id',$v['mid'])->find();
					$data[$k]['nickname'] = $member['nickname'];
					$data[$k]['headimg'] = $member['headimg'];
				}else{
					$data[$k]['nickname'] = '';
					$data[$k]['headimg'] = '';
				}
				}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		//分类
		$clist = Db::name('business_category')->Field('id,name')->where('aid',aid)->where('status',1)->order('sort desc,id')->select()->toArray();
		$bset = Db::name('business_sysset')->where('aid',aid)->find();
        View::assign('clist',$clist);
		View::assign('bset',$bset);

		View::assign('isadmin',$this->user['isadmin']);
		return View::fetch();
    }
	//编辑
	public function edit(){
		if(input('param.id')){
			$info = Db::name('business')->where('aid',aid)->where('id',input('param.id/d'))->find();
			if($info){
				$uinfo = Db::name('admin_user')->where('aid',aid)->where('bid',$info['id'])->where('isadmin',1)->find();
			}else{
				$uinfo = [];
			}
            if(getcustom('business_agent') && $uinfo['mid']){
                $member = Db::name('member')->where('id',$uinfo['mid'])->field('id,pid,levelid')->find();
                if($member && $member['pid']){
                    View::assign('business_pid',$member['pid']);
                }
            }
		}else{
			$info = array('id'=>'','cid'=>'0');
            $info['feepercent'] = Db::name('business_sysset')->where('aid',aid)->value('default_rate');
			$uinfo = [];
		}
		$info['cid'] = explode(',',$info['cid']);

		$auth_data = $uinfo ? json_decode($uinfo['auth_data'],true) : array();
		if(!$auth_data) $auth_data = array();

		//分类
		$clist = Db::name('business_category')->Field('id,name')->where('aid',aid)->where('status',1)->order('sort desc,id')->select()->toArray();
		$menudata = \app\commons\Menu::getdata(aid,-1);

        $wxauth_data = $uinfo ? json_decode($uinfo['wxauth_data'],true) : array();
        if(!$wxauth_data) $wxauth_data = array();
        $notice_auth_data = $uinfo ? json_decode($uinfo['notice_auth_data'],true) : array();
        if(!$notice_auth_data) $notice_auth_data = array();
        $hexiao_auth_data = $uinfo ? json_decode($uinfo['hexiao_auth_data'],true) : array();
        if(!$hexiao_auth_data) $hexiao_auth_data = array();
        $wxauth_data = $uinfo ? json_decode($uinfo['wxauth_data'],true) : array();
        if(!$wxauth_data) $wxauth_data = array();

        $set = Db::name('business_sysset')->where('aid',aid)->find();
        if(getcustom('buybutton_custom')){
            $buybtn_status = $this->admin['buybtn_status']?$this->admin['buybtn_status']:0;
            View::assign('buybtn_status',$buybtn_status);
        }
        if(getcustom('business_fenhong_memberlevel')){
			$gdlevellist = Db::name('member_level')->where('aid',aid)->where('fenhong','>','0')->order('sort,id')->select()->toArray();
			View::assign('gdlevellist',$gdlevellist);
		}
		$submchidlength = 0;
		View::assign('auth_data',$auth_data);
        View::assign('admin_auth_data',$this->auth_data);
        View::assign('notice_auth_data',$notice_auth_data);
        View::assign('hexiao_auth_data',$hexiao_auth_data);
        View::assign('wxauth_data',$wxauth_data);
		View::assign('menudata',$menudata);
		View::assign('clist',$clist);
		View::assign('info',$info);
		View::assign('uinfo',$uinfo);
		View::assign('set',$set);

        View::assign('thisuser',$this->user);
        View::assign('thisuser_showtj',$this->user['showtj']==1 || $this->user['isadmin']>0 ? 1 : 0);
        View::assign('thisuser_mdid',$this->user['mdid']);
        View::assign('thisuser_wxauth',json_decode($this->user['wxauth_data'],true));
        View::assign('thisuser_notice_auth',json_decode($this->user['notice_auth_data'],true));
        View::assign('thisuser_hexiao_auth',json_decode($this->user['hexiao_auth_data'],true));
        View::assign('restaurant_auth',strpos($this->user['wxauth_data'],'restaurant') !== false ? true : false);
        View::assign('thisuserid',$this->user['id']);

        $sysset = Db::name('admin_set')->where('aid',aid)->find();
        $maidan_fenhong_new = 0;
        View::assign('maidan_fenhong_new',$maidan_fenhong_new);

        return View::fetch();
	}
	public function save(){
		$info = input('post.info/a');
        $uinfo = input('post.uinfo/a');
		$uinfo['auth_data'] = str_replace('^_^','\/*',jsonEncode(input('post.auth_data/a')));
        $uinfo['notice_auth_data'] = jsonEncode(input('post.notice_auth_data/a'));
        $uinfo['hexiao_auth_data'] = jsonEncode(input('post.hexiao_auth_data/a'));
        $uinfo['wxauth_data'] = jsonEncode(input('post.wxauth_data/a'));
        $info['endtime'] = strtotime($info['endtime']);
        $member = Db::name('member')->where('id',$info['mid'])->find();
	    if(empty($member)){
			return json(['status'=>0,'msg'=>'该会员不存在']);
		}
	
    	$hasbusiness = Db::name('business')->where('mid',$info['mid'])->find();
		if($hasbusiness && $hasbusiness['id']!= $info['id']){
			return json(['status'=>0,'msg'=>'该会员已绑定过商家']);
		}
		
		if(getcustom('business_fenhong_memberlevel')){
			$info['fenhong_memberlevel'] = implode(',',$info['fenhong_memberlevel']);
		}

		$hasun = Db::name('admin_user')->where('id','<>',$uinfo['id'])->where('un',$uinfo['un'])->find();
		if($hasun){
			return json(['status'=>0,'msg'=>'该账号已存在']);
		}

        if($info['latitude'] && $info['longitude'] && !$info['district']){
            //通过坐标获取省市区
            $mapqq = new \app\commons\MapQQ();
            $address_component = $mapqq->locationToAddress($info['latitude'],$info['longitude']);
            if($address_component && $address_component['status']==1){
                $info['province'] = $address_component['province'];
                $info['city'] = $address_component['city'];
                $info['district'] = $address_component['district'];
            }
        }

        if($info['id']){
			$bid = $info['id'];
            $where = [];
            $where[] = ['aid','=',aid];
            $where[] = ['id','=',$info['id']];
            Db::name('business')->where($where)->update($info);
			if($uinfo['pwd']!=''){
				$uinfo['pwd'] = md5($uinfo['pwd']);
			}else{
				unset($uinfo['pwd']);
			}
			Db::name('admin_user')->where('aid',aid)->where('bid',$info['id'])->where('id',$uinfo['id'])->update($uinfo);
			if(getcustom('business_agent')){
                $uinfo = Db::name('admin_user')->where('aid',aid)->where('id','=',$uinfo['id'])->find();
                if($uinfo['mid']){
                    $pid = input('post.business_pid');
                    $member = Db::name('member')->where('aid',aid)->where('id',$uinfo['mid'])->field('id,pid,levelid')->find();
                    if($pid != $member['pid'])
                        \app\models\Member::edit(aid,['id'=>$uinfo['mid'], 'pid'=>$pid]);
                }
            }
			\app\commons\System::plog('修改商户'.$info['id']);
		}else{
			$info['aid'] = aid;
			$info['createtime'] = time();
			$bid = Db::name('business')->insertGetId($info);
			
			$uinfo['aid'] = aid;
			$uinfo['bid'] = $bid;
			//$uinfo['auth_type'] = 1;
			$uinfo['pwd'] = md5($uinfo['pwd']);
			$uinfo['createtime'] = time();
			$uinfo['isadmin'] = 1;
			$uinfo['random_str'] = random(16);
			$id = Db::name('admin_user')->insertGetId($uinfo);
			\app\commons\System::plog('添加商户'.$bid);

			$mendian = Db::name('mendian')->where('bid',$bid)->find();
			if(!$mendian){
				Db::name('mendian')->insert(['aid'=>aid,'bid'=>$bid,'name'=>$info['name'],'address'=>$info['address'],'pic'=>$info['logo'],'longitude'=>$info['longitude'],'latitude'=>$info['latitude'],'createtime'=>time()]);
			}
			$freight = Db::name('freight')->where('bid',$bid)->find();
			if(!$freight){
				Db::name('freight')->insert([
					'aid'=>aid,
					'bid'=>$bid,
					'name'=>'普通快递',
					'pstype'=>0,
					'pricedata'=>'[{"region":"全国(默认运费)","fristweight":"1000","fristprice":"0","secondweight":"1000","secondprice":"0"}]',
					'pstimedata'=>'[{"day":"1","hour":"12","minute":"0","hour2":"12","minute2":"30"},{"day":"1","hour":"18","minute":"0","hour2":"18","minute2":"30"},{"day":"2","hour":"12","minute":"0","hour2":"12","minute2":"30"},{"day":"2","hour":"18","minute":"0","hour2":"18","minute2":"30"}]',
					'status'=>1,
				]);
				Db::name('freight')->insert([
					'aid'=>aid,
					'bid'=>$bid,
					'name'=>'到店自提',
					'pstype'=>1,
					'pricedata'=>'[{"region":"全国(默认运费)","fristweight":"1000","fristprice":"0","secondweight":"1000","secondprice":"0"}]',
					'pstimedata'=>'[{"day":"1","hour":"12","minute":"0","hour2":"12","minute2":"30"},{"day":"1","hour":"18","minute":"0","hour2":"18","minute2":"30"},{"day":"2","hour":"12","minute":"0","hour2":"12","minute2":"30"},{"day":"2","hour":"18","minute":"0","hour2":"18","minute2":"30"}]',
					'status'=>1,
				]);
			}
			if(getcustom('restaurant')){
				\app\customs\Restaurant::init_freight(aid,$bid);
			}
			}
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}
	//改状态
	public function setst(){
		$st = input('post.st/d');
		$ids = input('post.ids/a');
		Db::name('business')->where('aid',aid)->where('id','in',$ids)->update(['status'=>$st]);
		\app\commons\System::plog('修改商户状态'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//审核
	public function setcheckst(){
		$st = input('post.st/d');
		$id = input('post.id/d');
		$reason = input('post.reason');
		$business = Db::name('business')->where('aid',aid)->where('id',$id)->find();
		if(!$business) return json(['status'=>0,'msg'=>'商家不存在']);
		Db::name('business')->where('aid',aid)->where('id',$id)->update(['status'=>$st,'reason'=>$reason]);
		if($st == 1){
			$mendian = Db::name('mendian')->where('bid',$id)->find();
			if(!$mendian){
				Db::name('mendian')->insert(['aid'=>aid,'bid'=>$id,'name'=>$business['name'],'address'=>$business['address'],'pic'=>$business['logo'],'longitude'=>$business['longitude'],'latitude'=>$business['latitude'],'createtime'=>time()]);
			}
			$freight = Db::name('freight')->where('bid',$id)->find();
			if(!$freight){
				Db::name('freight')->insert([
					'aid'=>aid,
					'bid'=>$id,
					'name'=>'普通快递',
					'pstype'=>0,
					'pricedata'=>'[{"region":"全国(默认运费)","fristweight":"1000","fristprice":"0","secondweight":"1000","secondprice":"0"}]',
					'pstimedata'=>'[{"day":"1","hour":"12","minute":"0","hour2":"12","minute2":"30"},{"day":"1","hour":"18","minute":"0","hour2":"18","minute2":"30"},{"day":"2","hour":"12","minute":"0","hour2":"12","minute2":"30"},{"day":"2","hour":"18","minute":"0","hour2":"18","minute2":"30"}]',
					'status'=>1,
				]);
				Db::name('freight')->insert([
					'aid'=>aid,
					'bid'=>$id,
					'name'=>'到店自提',
					'pstype'=>1,
					'pricedata'=>'[{"region":"全国(默认运费)","fristweight":"1000","fristprice":"0","secondweight":"1000","secondprice":"0"}]',
					'pstimedata'=>'[{"day":"1","hour":"12","minute":"0","hour2":"12","minute2":"30"},{"day":"1","hour":"18","minute":"0","hour2":"18","minute2":"30"},{"day":"2","hour":"12","minute":"0","hour2":"12","minute2":"30"},{"day":"2","hour":"18","minute":"0","hour2":"18","minute2":"30"}]',
					'status'=>1,
				]);
			}
			if(getcustom('restaurant')){
				\app\customs\Restaurant::init_freight(aid,$id);
			}
			}else{
			//商品下架
			Db::name('shop_product')->where('aid',aid)->where('bid',$id)->update(['status'=>0]);
			Db::name('collage_product')->where('aid',aid)->where('bid',$id)->update(['status'=>0]);
			Db::name('kanjia_product')->where('aid',aid)->where('bid',$id)->update(['status'=>0]);
			Db::name('seckill_product')->where('aid',aid)->where('bid',$id)->update(['status'=>0]);
			Db::name('tuangou_product')->where('aid',aid)->where('bid',$id)->update(['status'=>0]);

		}
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	private function addcommonproduct($bid){
        }

    private function tongbuproduct($bid){
        }
	//删除
	public function del(){
		$ids = input('post.ids/a');
		Db::name('business')->where('aid',aid)->where('id','in',$ids)->delete();
		Db::name('admin_user')->where('aid',aid)->where('bid','in',$ids)->delete();
		Db::name('shop_product')->where('aid',aid)->where('bid','in',$ids)->delete();
		Db::name('shop_order')->where('aid',aid)->where('bid','in',$ids)->delete();
		Db::name('shop_order_goods')->where('aid',aid)->where('bid','in',$ids)->delete();
		Db::name('collage_product')->where('aid',aid)->where('bid','in',$ids)->delete();
		Db::name('collage_order')->where('aid',aid)->where('bid','in',$ids)->delete();
		Db::name('collage_order_team')->where('aid',aid)->where('bid','in',$ids)->delete();
		Db::name('kanjia_product')->where('aid',aid)->where('bid','in',$ids)->delete();
		Db::name('kanjia_order')->where('aid',aid)->where('bid','in',$ids)->delete();
		Db::name('coupon')->where('aid',aid)->where('bid','in',$ids)->delete();
		Db::name('coupon_record')->where('aid',aid)->where('bid','in',$ids)->delete();
		Db::name('business_comment')->where('aid',aid)->where('bid','in',$ids)->delete();
		Db::name('business_moneylog')->where('aid',aid)->where('bid','in',$ids)->delete();
		Db::name('business_withdrawlog')->where('aid',aid)->where('bid','in',$ids)->delete();
        Db::name('shop_refund_order')->where('aid',aid)->where('bid','in',$ids)->delete();
        Db::name('shop_refund_order_goods')->where('aid',aid)->where('bid','in',$ids)->delete();
        Db::name('invoice')->where('aid',aid)->where('bid','in',$ids)->delete();
        Db::name('cycle_product')->where('aid',aid)->where('bid','in',$ids)->delete();
        //删除预约服务信息
        Db::name('yuyue_product')->where('aid',aid)->where('bid','in',$ids)->delete();
        Db::name('yuyue_order')->where('aid',aid)->where('bid','in',$ids)->delete();
        Db::name('yuyue_set')->where('aid',aid)->where('bid','in',$ids)->delete();
        Db::name('yuyue_guige')->where('aid',aid)->where('bid','in',$ids)->delete();
        Db::name('yuyue_fuwu')->where('aid',aid)->where('bid','in',$ids)->delete();
        Db::name('yuyue_comment')->where('aid',aid)->where('bid','in',$ids)->delete();
        Db::name('yuyue_category')->where('aid',aid)->where('bid','in',$ids)->delete();
        //知识付费信息删除
        Db::name('kecheng_category')->where('aid',aid)->where('bid','in',$ids)->delete();
        Db::name('kecheng_list')->where('aid',aid)->where('bid','in',$ids)->delete();
        Db::name('kecheng_chapter')->where('aid',aid)->where('bid','in',$ids)->delete();
        Db::name('kecheng_tiku')->where('aid',aid)->where('bid','in',$ids)->delete();
        Db::name('kecheng_order')->where('aid',aid)->where('bid','in',$ids)->delete();
        Db::name('kecheng_studylog')->where('aid',aid)->where('bid','in',$ids)->delete();
        //约课删除
        //酒店删除
        //门店删除
        Db::name('mendian')->where('aid', aid)->where('bid', 'in', $ids)->delete();
    
        \app\commons\System::plog('删除商户'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}

	//充值
	public function recharge(){
		$bid = input('post.rechargemid/d');
		$money = floatval(input('post.rechargemoney'));
        if($money == 0){
			return json(['status'=>0,'msg'=>'请输入充值金额']);
		}
		$info = Db::name('business')->where('aid',aid)->where('id',$bid)->find();
		if(!$info) return json(['status'=>0,'msg'=>'未找到该商家']);
		\app\commons\Business::addmoney(aid,$bid,$money,'平台充值');
		\app\commons\System::plog('给商户充值'.$bid);
		return json(['status'=>1,'msg'=>'充值成功']);
	}

    public function deposit(){
        }
	//加积分
	public function addscore(){
		$bid = input('post.id/d');
		$score = intval(input('post.score'));
		$remark = input('post.remark');
        $actionname = '增加';
		if($score == 0){
			return json(['status'=>0,'msg'=>'请输入'.t('积分').'数']);
		}
        if($score < 0) $actionname = '扣除';
		$rs = \app\commons\Business::addscore(aid,$bid,$score,''.$remark,1);
		\app\commons\System::plog('给商户'.$bid.$actionname.'积分'.$score);
		if($rs['status']==0) return json($rs);
		return json(['status'=>1,'msg'=>'操作成功']);
	}

	//商家小程序码
	public function getmdqr(){
		$id = input('post.id/d');
		$set = Db::name('admin_set')->where('aid',aid)->find();
		if(!$set['wxappid']){
			return json(['status'=>0,'msg'=>'请先授权小程序']);
		}
		$rs = \app\commons\Wechat::getQRCode(aid,'wx','pagesExt/business/detail',['id' => $id]);
		return $rs;
	}
	
	//复制数据
	public function copydata(){
		set_time_limit(0);
		ini_set('memory_limit','-1');

		$info = input('post.info/a');
		$toid = $info['toid'];
		$delold = $info['delold'];
		$module_data = input('post.module_data');
		if(!$module_data)  return json(['status'=>0,'msg'=>'请选择要复制的数据']);
		$business = Db::name('business')->where('aid',aid)->where('id',$toid)->find();
		if(!$business) return json(['status'=>0,'msg'=>'要复制到的商户未查找到']);
		
		foreach($module_data as $modulename){
			if($modulename == '商城商品分类'){
				if($delold == 1){
					Db::name('shop_category2')->where('aid',aid)->where('bid',$toid)->delete();
				}
				$clist = Db::name('shop_category')->where('aid',aid)->where('pid',0)->order('sort desc,id')->select()->toArray(); 
				foreach($clist as $k=>$v){
					$newdata = [];
					$newdata['id'] = '';
					$newdata['aid'] = $v['aid'];
					$newdata['bid'] = $toid;
					$newdata['pid'] = 0;
					$newdata['name'] = $v['name'];
					$newdata['pic'] = $v['pic'];
					$newdata['status'] = $v['status'];
					$newdata['sort'] = $v['sort'];
					$newdata['createtime'] = $v['createtime'];
					$newdata['fromid'] = $v['id'];
					$newid = Db::name('shop_category2')->insertGetId($newdata);

					$child = Db::name('shop_category')->where('aid',aid)->where('pid',$v['id'])->order('sort desc,id')->select()->toArray();
					foreach($child as $k2=>$v2){
						$newdata = [];
						$newdata['id'] = '';
						$newdata['aid'] = $v2['aid'];
						$newdata['bid'] = $toid;
						$newdata['pid'] = $newid;
						$newdata['name'] = $v2['name'];
						$newdata['pic'] = $v2['pic'];
						$newdata['status'] = $v2['status'];
						$newdata['sort'] = $v2['sort'];
						$newdata['createtime'] = $v2['createtime'];
						$newdata['fromid'] = $v2['id'];
						$newid2 = Db::name('shop_category2')->insertGetId($newdata);

						$child2 = Db::name('shop_category')->where('aid',aid)->where('pid',$v2['id'])->order('sort desc,id')->select()->toArray();
						foreach($child2 as $k3=>$v3){
							$newdata = [];
							$newdata['id'] = '';
							$newdata['aid'] = $v3['aid'];
							$newdata['bid'] = $toid;
							$newdata['pid'] = $newid2;
							$newdata['name'] = $v3['name'];
							$newdata['pic'] = $v3['pic'];
							$newdata['status'] = $v3['status'];
							$newdata['sort'] = $v3['sort'];
							$newdata['createtime'] = $v3['createtime'];
							$newdata['fromid'] = $v3['id'];
							$newid3 = Db::name('shop_category2')->insertGetId($newdata);
						}
					}
				}
			}
		}
		foreach($module_data as $modulename){
			if($modulename == '商城商品'){
				//服务
				if($delold == 1) Db::name('shop_fuwu')->where('aid',aid)->where('bid',$toid)->delete();
				$fromdata = Db::name('shop_fuwu')->where('aid',aid)->where('bid',0)->select()->toArray();
				$shop_fuwu_ids_map = [];
				$shop_fuwu_ids_map['0'] = '0';
				foreach($fromdata as $data){
					$oldid = $data['id'];
					$data['id'] = '';
					$data['bid'] = $toid;
					$shop_fuwu_ids_map[$oldid] = Db::name('shop_fuwu')->insertGetId($data);
				}
				//参数
				if($delold == 1) Db::name('shop_param')->where('aid',aid)->where('bid',$toid)->delete();
				$fromdata = Db::name('shop_param')->where('aid',aid)->where('bid',0)->select()->toArray();
				foreach($fromdata as $data){
					$oldid = $data['id'];
					$data['id'] = '';
					$data['bid'] = $toid;
					Db::name('shop_param')->insertGetId($data);
				}
				//商品
				if($delold == 1){
					$proids = Db::name('shop_product')->where('aid',aid)->where('bid',$toid)->column('id');
					Db::name('shop_product')->where('aid',aid)->where('bid',$toid)->delete();
					Db::name('shop_guige')->where('aid',aid)->where('proid','in',$proids)->delete();
				}
				$fromdata = [];

				if(false){}else{
					$fromdata = Db::name('shop_product')->where('aid',aid)->where('bid',0)->select()->toArray();
				}
				
				foreach($fromdata as $data){
					$oldid = $data['id'];
					unset($data['wxvideo_product_id']);
					unset($data['wxvideo_edit_status']);
					unset($data['wxvideo_status']);
					unset($data['wxvideo_reject_reason']);
					$data['id'] = '';
					$data['bid'] = $toid;
					$data['fwid'] = $this->getnewids($shop_fuwu_ids_map,$data['fwid']);
					if($data['freighttype'] == 0) $data['freighttype'] = 1;
					if($data['cid']){
						$cid2 = Db::name('shop_category2')->where('bid',$toid)->where('fromid','in',$data['cid'])->column('id');
						if($cid2) $data['cid2'] = implode(',',$cid2);
					}
					//if($data['commissionset'] == 1 || $data['commissionset'] == 2 || $data['commissionset'] == 3) $data['commissionset'] = 0;
					$id = Db::name('shop_product')->insertGetId($data);
					$gglist = Db::name('shop_guige')->where('aid',aid)->where('proid',$oldid)->select()->toArray();
					foreach($gglist as $gg){
						$gg['id'] = '';
						$gg['proid'] = $id;
						Db::name('shop_guige')->insert($gg);
					}
				}
			}
			if($modulename == '拼团商品'){
				//商品
				if($delold == 1){
					$proids = Db::name('collage_product')->where('aid',aid)->where('bid',$toid)->column('id');
					Db::name('collage_product')->where('aid',aid)->where('bid',$toid)->delete();
					Db::name('collage_guige')->where('aid',aid)->where('proid','in',$proids)->delete();
				}
				$fromdata = Db::name('collage_product')->where('aid',aid)->where('bid',0)->select()->toArray();
				foreach($fromdata as $data){
					$oldid = $data['id'];
					$data['id'] = '';
					$data['bid'] = $toid;
					if($data['freighttype'] == 0) $data['freighttype'] = 1;
					//if($data['commissionset'] == 1 || $data['commissionset'] == 2 || $data['commissionset'] == 3) $data['commissionset'] = 0;
					$id = Db::name('collage_product')->insertGetId($data);
					$gglist = Db::name('collage_guige')->where('aid',aid)->where('proid',$oldid)->select()->toArray();
					foreach($gglist as $gg){
						$gg['id'] = '';
						$gg['proid'] = $id;
						Db::name('collage_guige')->insert($gg);
					}
				}
			}
			if($modulename == '砍价商品'){
				//商品
				if($delold == 1) Db::name('kanjia_product')->where('aid',aid)->where('bid',$toid)->delete();
				$fromdata = Db::name('kanjia_product')->where('aid',aid)->where('bid',0)->select()->toArray();
				foreach($fromdata as $data){
					$oldid = $data['id'];
					$data['id'] = '';
					$data['bid'] = $toid;
					if($data['freighttype'] == 0) $data['freighttype'] = 1;
					$id = Db::name('kanjia_product')->insertGetId($data);
				}
			}
			if($modulename == '秒杀商品'){
				if($delold == 1){
					$proids = Db::name('seckill_product')->where('aid',aid)->where('bid',$toid)->column('id');
					Db::name('seckill_product')->where('aid',aid)->where('bid',$toid)->delete();
					Db::name('seckill_guige')->where('aid',aid)->where('proid','in',$proids)->delete();
				}
				$fromdata = Db::name('seckill_product')->where('aid',aid)->where('bid',0)->select()->toArray();
				foreach($fromdata as $data){
					$oldid = $data['id'];
					$data['id'] = '';
					$data['bid'] = $toid;
					if($data['freighttype'] == 0) $data['freighttype'] = 1;
					//if($data['commissionset'] == 1 || $data['commissionset'] == 2 || $data['commissionset'] == 3) $data['commissionset'] = 0;
					$id = Db::name('seckill_product')->insertGetId($data);
					$gglist = Db::name('seckill_guige')->where('aid',aid)->where('proid',$oldid)->select()->toArray();
					foreach($gglist as $gg){
						$gg['id'] = '';
						$gg['proid'] = $id;
						Db::name('seckill_guige')->insert($gg);
					}
				}
			}
			if($modulename == '团购商品'){
				//分类
				if($delold == 1) Db::name('tuangou_category')->where('aid',aid)->where('bid',$toid)->delete();
				$fromdata = Db::name('tuangou_category')->where('aid',aid)->where('bid',0)->select()->toArray();
				$tuangou_category_ids_map = [];
				$tuangou_category_ids_map['0'] = '0';
				foreach($fromdata as $data){
					$oldid = $data['id'];
					$data['id'] = '';
					$data['bid'] = $toid;
					$data['pid'] = $tuangou_category_ids_map[$data['pid']];
					$tuangou_category_ids_map[$oldid] = Db::name('tuangou_category')->insertGetId($data);
				}
				//商品
				if($delold == 1) Db::name('tuangou_product')->where('aid',aid)->where('bid',$toid)->delete();
				$fromdata = Db::name('tuangou_product')->where('aid',aid)->where('bid',0)->select()->toArray();
				foreach($fromdata as $data){
					$oldid = $data['id'];
					$data['id'] = '';
					$data['bid'] = $toid;
					$data['cid'] = $this->getnewids($tuangou_category_ids_map,$data['cid']);
					if($data['freighttype'] == 0) $data['freighttype'] = 1;
					//if($data['commissionset'] == 1 || $data['commissionset'] == 2 || $data['commissionset'] == 3) $data['commissionset'] = 0;
					$id = Db::name('tuangou_product')->insertGetId($data);
				}
			}
			if($modulename == '幸运拼团商品'){
				//商品
				if($delold == 1){
					$proids = Db::name('lucky_collage_product')->where('aid',aid)->where('bid',$toid)->column('id');
					Db::name('lucky_collage_product')->where('aid',aid)->where('bid',$toid)->delete();
					Db::name('lucky_collage_guige')->where('aid',aid)->where('proid','in',$proids)->delete();
				}
				$fromdata = Db::name('lucky_collage_product')->where('aid',aid)->where('bid',0)->select()->toArray();
				foreach($fromdata as $data){
					$oldid = $data['id'];
					$data['id'] = '';
					$data['bid'] = $toid;
					if($data['freighttype'] == 0) $data['freighttype'] = 1;
					//if($data['commissionset'] == 1 || $data['commissionset'] == 2 || $data['commissionset'] == 3) $data['commissionset'] = 0;
					$id = Db::name('lucky_collage_product')->insertGetId($data);
					$gglist = Db::name('lucky_collage_guige')->where('aid',aid)->where('proid',$oldid)->select()->toArray();
					foreach($gglist as $gg){
						$gg['id'] = '';
						$gg['proid'] = $id;
						Db::name('lucky_collage_guige')->insert($gg);
					}
				}
			}
			if($modulename == '短视频'){
				//分类
				if($delold == 1) Db::name('shortvideo_category')->where('aid',aid)->where('bid',$toid)->delete();
				$fromdata = Db::name('shortvideo_category')->where('aid',aid)->where('bid',0)->select()->toArray();
				$shortvideo_category_ids_map = [];
				$shortvideo_category_ids_map['0'] = '0';
				foreach($fromdata as $data){
					$oldid = $data['id'];
					$data['id'] = '';
					$data['bid'] = $toid;
					$shortvideo_category_ids_map[$oldid] = Db::name('shortvideo_category')->insertGetId($data);
				}
				//商品
				if($delold == 1) Db::name('shortvideo')->where('aid',aid)->where('bid',$toid)->delete();
				$fromdata = Db::name('shortvideo')->where('aid',aid)->where('bid',0)->select()->toArray();
				foreach($fromdata as $data){
					$oldid = $data['id'];
					$data['id'] = '';
					$data['bid'] = $toid;
					$data['mid'] = 0;
					$data['cid'] = $this->getnewids($shortvideo_category_ids_map,$data['cid']);
					$id = Db::name('shortvideo')->insertGetId($data);
				}
			}
			if($modulename == '文章列表'){
				//分类
				if($delold == 1) Db::name('article_category')->where('aid',aid)->where('bid',$toid)->delete();
				$fromdata = Db::name('article_category')->where('aid',aid)->where('bid',0)->select()->toArray();
				$article_category_ids_map = [];
				$article_category_ids_map['0'] = '0';
				foreach($fromdata as $data){
					$oldid = $data['id'];
					$data['id'] = '';
					$data['bid'] = $toid;
					$data['pid'] = $article_category_ids_map[$data['pid']];
					$article_category_ids_map[$oldid] = Db::name('article_category')->insertGetId($data);
				}
				//文章
				if($delold == 1) Db::name('article')->where('aid',aid)->where('bid',$toid)->delete();
				$fromdata = Db::name('article')->where('aid',aid)->where('bid',0)->select()->toArray();
				foreach($fromdata as $data){
					$oldid = $data['id'];
					$data['id'] = '';
					$data['bid'] = $toid;
					$data['cid'] = $this->getnewids($article_category_ids_map,$data['cid']);
					$id = Db::name('article')->insertGetId($data);
				}
			}
			if($modulename == '预约服务商品'){
				//分类
				if($delold == 1) Db::name('yuyue_category')->where('aid',aid)->where('bid',$toid)->delete();
				$fromdata = Db::name('yuyue_category')->where('aid',aid)->where('bid',0)->select()->toArray();
				$yuyue_category_ids_map = [];
				$yuyue_category_ids_map['0'] = '0';
				foreach($fromdata as $data){
					$oldid = $data['id'];
					$data['id'] = '';
					$data['bid'] = $toid;
					$data['pid'] = $yuyue_category_ids_map[$data['pid']];
					$yuyue_category_ids_map[$oldid] = Db::name('yuyue_category')->insertGetId($data);
				}
				//服务
				if($delold == 1) Db::name('yuyue_fuwu')->where('aid',aid)->where('bid',$toid)->delete();
				$fromdata = Db::name('yuyue_fuwu')->where('aid',aid)->where('bid',0)->select()->toArray();
				$yuyue_fuwu_ids_map = [];
				$yuyue_fuwu_ids_map['0'] = '0';
				foreach($fromdata as $data){
					$oldid = $data['id'];
					$data['id'] = '';
					$data['bid'] = $toid;
					$yuyue_fuwu_ids_map[$oldid] = Db::name('yuyue_fuwu')->insertGetId($data);
				}
				//商品
				if($delold == 1){
					$proids = Db::name('yuyue_product')->where('aid',aid)->where('bid',$toid)->column('id');
					Db::name('yuyue_product')->where('aid',aid)->where('bid',$toid)->delete();
					Db::name('yuyue_guige')->where('aid',aid)->where('proid','in',$proids)->delete();
				}
				$fromdata = Db::name('yuyue_product')->where('aid',aid)->where('bid',0)->select()->toArray();
				foreach($fromdata as $data){
					$oldid = $data['id'];
					$data['id'] = '';
					$data['bid'] = $toid;
					$data['cid'] = $this->getnewids($yuyue_category_ids_map,$data['cid']);
					$data['fwid'] = $this->getnewids($yuyue_fuwu_ids_map,$data['fwid']);
					//if($data['commissionset'] == 1 || $data['commissionset'] == 2 || $data['commissionset'] == 3) $data['commissionset'] = 0;
					$id = Db::name('yuyue_product')->insertGetId($data);
					$gglist = Db::name('yuyue_guige')->where('aid',aid)->where('proid',$oldid)->select()->toArray();
					foreach($gglist as $gg){
						$gg['id'] = '';
						$gg['bid'] = $toid;
						$gg['proid'] = $id;
						Db::name('yuyue_guige')->insert($gg);
					}
				}
			}
			if($modulename == '知识付费课程'){
				//分类
				if($delold == 1) Db::name('kecheng_category')->where('aid',aid)->where('bid',$toid)->delete();
				$fromdata = Db::name('kecheng_category')->where('aid',aid)->where('bid',0)->select()->toArray();
				$kecheng_category_ids_map = [];
				$kecheng_category_ids_map['0'] = '0';
				foreach($fromdata as $data){
					$oldid = $data['id'];
					$data['id'] = '';
					$data['bid'] = $toid;
					$data['pid'] = $kecheng_category_ids_map[$data['pid']];
					$kecheng_category_ids_map[$oldid] = Db::name('kecheng_category')->insertGetId($data);
				}
				$fromdata = Db::name('kecheng_list')->where('aid',aid)->where('bid',0)->select()->toArray();
				foreach($fromdata as $data){
					$oldid = $data['id'];
					$data['id'] = '';
					$data['bid'] = $toid;
					$data['cid'] = $this->getnewids($kecheng_category_ids_map,$data['cid']);
					$id = Db::name('kecheng_list')->insertGetId($data);
					$chapterlist = Db::name('kecheng_chapter')->where('aid',aid)->where('kcid',$oldid)->select()->toArray();
					foreach($chapterlist as $chapter){
						$chapter['id'] = '';
						$chapter['bid'] = $toid;
						$chapter['kcid'] = $id;
						Db::name('kecheng_chapter')->insert($chapter);
					}
					$tikulist = Db::name('kecheng_tiku')->where('aid',aid)->where('kcid',$oldid)->select()->toArray();
					foreach($tikulist as $tiku){
						$tiku['id'] = '';
						$tiku['bid'] = $toid;
						$tiku['kcid'] = $id;
						Db::name('kecheng_tiku')->insert($tiku);
					}
				}
			}
			if($modulename == '餐饮菜品'){
				//分类
				if($delold == 1) Db::name('restaurant_product_category')->where('aid',aid)->where('bid',$toid)->delete();
				$fromdata = Db::name('restaurant_product_category')->where('aid',aid)->where('bid',0)->select()->toArray();
				$restaurant_category_ids_map = [];
				$restaurant_category_ids_map['0'] = '0';
				foreach($fromdata as $data){
					$oldid = $data['id'];
					$data['id'] = '';
					$data['bid'] = $toid;
					$data['pid'] = $restaurant_category_ids_map[$data['pid']];
					$restaurant_category_ids_map[$oldid] = Db::name('restaurant_product_category')->insertGetId($data);
				}
				//商品
				if($delold == 1){
					$proids = Db::name('restaurant_product')->where('aid',aid)->where('bid',$toid)->column('id');
					Db::name('restaurant_product')->where('aid',aid)->where('bid',$toid)->delete();
					Db::name('restaurant_product_guige')->where('aid',aid)->where('product_id','in',$proids)->delete();
				}

				if(false){}else{
					$fromdata = Db::name('restaurant_product')->where('aid',aid)->where('bid',0)->select()->toArray();
				}

				foreach($fromdata as $data){
					$oldid = $data['id'];
					$data['id'] = '';
					$data['bid'] = $toid;
					$data['cid'] = $this->getnewids($restaurant_category_ids_map,$data['cid']);
					if($data['freighttype'] == 0) $data['freighttype'] = 1;
					//if($data['commissionset'] == 1 || $data['commissionset'] == 2 || $data['commissionset'] == 3) $data['commissionset'] = 0;
					$id = Db::name('restaurant_product')->insertGetId($data);
					$gglist = Db::name('restaurant_product_guige')->where('aid',aid)->where('product_id',$oldid)->select()->toArray();
					foreach($gglist as $gg){
						$gg['id'] = '';
						$gg['product_id'] = $id;
						Db::name('restaurant_product_guige')->insert($gg);
					}
				}
			}
			if($modulename == '优惠券'){
				if($delold == 1) Db::name('coupon')->where('aid',aid)->where('bid',$toid)->delete();
				$fromdata = Db::name('coupon')->where('aid',aid)->where('bid',0)->select()->toArray();
				foreach($fromdata as $data){
					$oldid = $data['id'];
					$data['id'] = '';
					$data['bid'] = $toid;
					$id = Db::name('coupon')->insertGetId($data);
				}
			}
			if($modulename == '设计页面'){
				if($delold == 1){
					Db::name('designerpage')->where('aid',aid)->where('bid',$toid)->delete();
				}else{
					Db::name('designerpage')->where('aid',aid)->where('bid',$toid)->update(['ishome'=>0]);
				}
				$fromdata = Db::name('designerpage')->where('aid',aid)->where('bid',0)->select()->toArray();
				foreach($fromdata as $data){
					$oldid = $data['id'];
					$data['id'] = '';
					$data['bid'] = $toid;
					$id = Db::name('designerpage')->insertGetId($data);
				}
			}
			}
		return json(['status'=>1,'msg'=>'复制完成']);
	}
	private function getnewids($arr,$ids){
		if(!$ids) return $ids;
		$ids = explode(',',$ids);
		$newids = [];
		foreach($ids as $id){
			$newids[] = $arr[$id];
		}
		return implode(',',$newids);
	}

	//分类列表
    public function category(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
				$order = 'sort desc,id';
			}
			$where = array();
			$where[] = ['aid','=',aid];
			if(input('param.name')) $where[] = ['name','like','%'.input('param.name').'%'];
			if(input('?param.status') && input('param.status')!=='') $where[] = ['status','=',input('param.status')];
			$count = 0 + Db::name('business_category')->where($where)->count();
			$data = Db::name('business_category')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
    }
	//编辑
	public function categoryedit(){
		if(input('param.id')){
			$info = Db::name('business_category')->where('aid',aid)->where('id',input('param.id/d'))->find();
		}else{
			$info = array('id'=>'');
		}
		$pcatelist = Db::name('business_category')->where('aid',aid)->order('sort desc,id')->select()->toArray();
		View::assign('info',$info);
		return View::fetch();
	}
	//保存
	public function categorysave(){
		$info = input('post.info/a');
		if($info['id']){
			Db::name('business_category')->where('aid',aid)->where('id',$info['id'])->update($info);
			\app\commons\System::plog('修改商家分类'.$info['id']);
		}else{
			$info['aid'] = aid;
			$info['createtime'] = time();
			$id = Db::name('business_category')->insertGetId($info);
			\app\commons\System::plog('添加商家分类'.$id);
		}
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}
	//删除
	public function categorydel(){
		$ids = input('post.ids/a');
		Db::name('business_category')->where('aid',aid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('删除商家分类'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}

	//系统设置
	public function sysset(){
		if(request()->isPost()){
			$rs = Db::name('business_sysset')->where('aid',aid)->find();
			$info = input('post.info/a');

			$info['wxfw_apiclient_cert'] = str_replace(PRE_URL.'/','',$info['wxfw_apiclient_cert']);
			$info['wxfw_apiclient_key'] = str_replace(PRE_URL.'/','',$info['wxfw_apiclient_key']);

            if(!empty($info['wxfw_apiclient_cert']) && substr($info['wxfw_apiclient_cert'], -4) != '.pem'){
                return json(['status'=>0,'msg'=>'PEM证书格式错误']);
            }
            if(!empty($info['wxfw_apiclient_key']) && substr($info['wxfw_apiclient_key'], -4) != '.pem'){
                return json(['status'=>0,'msg'=>'证书密钥格式错误']);
            }
             if($rs){
                //关闭多商户分销设置
                if($rs['commission_canset'] == 1 && $info['commission_canset'] == 0){
                    Db::name('shop_product')->where('aid',aid)->where('bid','>',0)->update(['commissionset'=>-1]);
                }
				Db::name('business_sysset')->where('aid',aid)->update($info);
				\app\commons\System::plog('多商户系统设置');
			}else{
				$info['aid'] = aid;
				Db::name('business_sysset')->insert($info);
			}
			return json(['status'=>1,'msg'=>'设置成功','url'=>true]);
		}
		$info = Db::name('business_sysset')->where('aid',aid)->find();
		if(!$info){
			Db::name('business_sysset')->insert(['aid'=>aid]);
			$info = Db::name('business_sysset')->where('aid',aid)->find();
		}
        //权限
        if($this->auth_data == 'all' || in_array('Cashier/*',$this->auth_data)){
            $info['business_auth'] = true;
        }
        if($info['wxfw_status']==0){
            $info['duli_disabled'] = true;
        }
        $scoredk_kouchu_list = [0=>'否',1=>'是'];
        View::assign('scoredk_kouchu_list',$scoredk_kouchu_list);
        View::assign('info',$info);
		return View::fetch();
	}
	public function choosebusiness(){
		return View::fetch();
	}
	public function getbusinessinfo(){
		$id = input('post.id/d');
		$info = Db::name('business')->where('id',$id)->where('aid',aid)->find();
		return json(['status'=>1,'data'=>$info]);
	}
	//登录
	public function blogin(){
		$id = input('param.id/d');
		$user = Db::name('admin_user')->where('aid',aid)->where('bid',$id)->where('isadmin',1)->find();
		if(!$user) die('未找到该商家');
		session('ADMIN_AUTH_UID',$user['id']);
		session('ADMIN_AUTH_BID',$id);
		return redirect(PRE_URL.'/business.php?s=/Backstage/index');
	}

	//商户销量统计
	public function sales(){
        if(request()->isAjax()){
            $page = input('param.page');
            $limit = input('param.limit');
            if(input('param.field') && input('param.order')){
                $order = 's.'.input('param.field').' '.input('param.order');
            }else{
                $order = 's.bid desc';
            }
            $where = array();
            $where[] = ['s.aid','=',aid];
            if(input('param.bid')) $where[] = ['s.bid','=',input('param.bid/d')];
            if(input('param.name')) $where[] = ['b.name','like','%'.input('param.name').'%'];
            $count = 0 + Db::name('business_sales')
                    ->alias('s')
                    ->join('business b','s.bid=b.id','left')
                    ->where($where)->count();
            $data = Db::name('business_sales')
                ->alias('s')
                ->join('business b','s.bid=b.id','left')
                ->field('s.*,b.name')
                ->where($where)->page($page,$limit)->order($order)->select()->toArray();

            return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
        }
        //判断管理员商品虚拟销量和数据是否匹配，不匹配显示手动更新按钮（解决首次更新代码销量没有数据问题）
        $show_update = 0;
        $product_sales = Db::name('shop_product')->where('aid',aid)->where('bid',0)->sum('sales');
        $business_sales = Db::name('business_sales')->where('aid',aid)->where('bid',0)->value('sales');
        if($product_sales>$business_sales){
            $show_update = 1;
        }
        View::assign('show_update',$show_update);

        return View::fetch();
    }

    //导出
    public function excel(){
        }

    public function setdepositrefund(){
	    }
}
