<?php


namespace app\controllers;
use think\facade\Db;
class ApiPeisong extends ApiCommon
{
	public $psuser;
	public function initialize(){
		parent::initialize();
		$this->checklogin();
		$this->psuser = Db::name('peisong_user')->where('aid',aid)->where('mid',mid)->find();
		if(mid && !$this->psuser) die(json_encode(['status'=>0,'msg'=>'该登录用户未绑定配送员']));
	}
	//更新我的位置
	public function updatemylocation(){
		$longitude = input('param.longitude');
		$latitude = input('param.latitude');
		if($latitude && $longitude){
			Db::name('peisong_user')->where('id',$this->psuser['id'])->update(['longitude'=>$longitude,'latitude'=>$latitude]);
		}
		return $this->json(['status'=>1,'msg'=>'']);
	}
	//接单大厅
	public function dating(){
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['psid','=',0];
		$where[] = ['status','=',0];
		if(input('param.keyword')){
			$where[] = ['binfo','like','%'.input('param.keyword').'%'];
		}
		$pernum = 20;
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$psuser = Db::name('peisong_user')->where('id',$this->psuser['id'])->find();
		$datalist = Db::name('peisong_order')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
		if(!$datalist) $datalist = array();
		foreach($datalist as $key=>$v){
			$datalist[$key]['orderinfo'] = json_decode($v['orderinfo'],true);
			$datalist[$key]['binfo'] = json_decode($v['binfo'],true);
			$datalist[$key]['prolist'] = json_decode($v['prolist'],true);
			//商家到用户的距离
			if($v['juli']> 1000){  
				$datalist[$key]['juli'] = round($v['juli']/1000,1);
				$datalist[$key]['juli_unit'] = 'km';
			}else{
				$datalist[$key]['juli_unit'] = 'm';
			}
			//
            //配送员到用户的距离 骑行距离
            $mapqq = new \app\commons\MapQQ();
            $bicycl = $mapqq->getDirectionDistance($v['longitude2'],$v['latitude2'],$psuser['longitude'],$psuser['latitude'],1);
            if($bicycl && $bicycl['status']==1){
                $juli2 = $bicycl['distance'];
            }else{
                $juli2 = getdistance($v['longitude2'],$v['latitude2'],$psuser['longitude'],$psuser['latitude'],1);
            }
			$datalist[$key]['juli2'] = $juli2;
			if($juli2> 1000){
				$datalist[$key]['juli2'] = round($juli2/1000,1);
				$datalist[$key]['juli2_unit'] = 'km';
			}else{
				$datalist[$key]['juli2_unit'] = 'm';
			}
			$datalist[$key]['leftminute'] = ceil(($v['yujitime'] - time()) / 60);
			$datalist[$key]['ticheng'] = round($v['ticheng'],2);
		}
		$rdata = [];
		$rdata['status'] = 1;
		$rdata['datalist'] = $datalist;
		$rdata['nowtime'] = time();
		return $this->json($rdata);
	}
	//抢单
	public function qiangdan(){
		$id = input('param.id/d');
		if(!$id) return $this->json(['status'=>0,'msg'=>'参数错误']);
		$psorder = Db::name('peisong_order')->where('aid',aid)->where('id',$id)->find();
		if(!$psorder) return $this->json(['status'=>0,'msg'=>'订单不存在']);
		if($psorder['status']!=0) return $this->json(['status'=>0,'msg'=>'手慢了']);
		Db::name('peisong_order')->where('id',$id)->update(['status'=>1,'psid'=>$this->psuser['id'],'status'=>1,'starttime'=>time()]);
		if(getcustom('paotui')){
            if($psorder['type'] == 'paotui_order'){
            	$ptdata = [];
            	$ptdata['pu_id']   = $this->psuser['id'];
                $ptdata['pu_name'] = !empty($this->psuser['realname'])?$this->psuser['realname']:'';
                $ptdata['pu_tel']  = !empty($this->psuser['tel'])?$this->psuser['tel']:'';
                $ptdata['status']  = 2;
                $ptdata['starttime']  = time();
                $ptdata['updatetime'] = time();
                Db::name('paotui_order')->where('id',$psorder['orderid'])->update($ptdata);
            }
        }
		send_socket(['type'=>'peisong_jiedan','data'=>['aid'=>aid,'mid'=>mid,'psorderid'=>$psorder['id']]]);
		return $this->json(['status'=>1,'msg'=>'抢单成功']);
	}
	//改状态
	public function setst(){
		$id = input('param.id/d');
		$st = input('param.st/d');
		$psorder = Db::name('peisong_order')->where('aid',aid)->where('psid',$this->psuser['id'])->where('id',$id)->find();
		if(!$psorder) return $this->json(['status'=>0,'msg'=>'订单不存在']);
		
		$updata = [];
		$updata['status'] = $st;
		if($st == 2){
			if($psorder['status']!=1) return $this->json(['status'=>0,'msg'=>'订单状态不符合']);
			$updata['daodiantime'] = time();
		}
		if($st == 3){
			if($psorder['status']!=2) return $this->json(['status'=>0,'msg'=>'订单状态不符合']);
			$updata['quhuotime'] = time();
		}
		if($st == 4){
			if($psorder['status']!=3) return $this->json(['status'=>0,'msg'=>'订单状态不符合']);
			$updata['endtime'] = time();
		}
		Db::name('peisong_order')->where('id',$id)->update($updata);
		if(getcustom('paotui')){
            if($psorder['type'] == 'paotui_order'){
                $ptdata = [];
                if($st == 2){
                	$ptdata['status'] = 3;
                    $ptdata['daodiantime'] = time();
                }
                if($st == 3){
                	$ptdata['status'] = 4;
                    $ptdata['quhuotime'] = time();
                }
                if($st == 4){
                	$ptdata['status'] = 5;
                    $ptdata['endtime'] = time();
                }
                $ptdata['updatetime'] = time();
                Db::name('paotui_order')->where('id',$psorder['orderid'])->update($ptdata);
            }
        }
		if($st == 4){
			Db::name('peisong_user')->where('id',$this->psuser['id'])->inc('totalnum')->update();
			$ticheng = $psorder['ticheng'];
			if(getcustom('paotui')){
	            if($psorder['type'] == 'paotui_order'){
	            	$ticheng += $psorder['tip_fee'];
	            }
	        }
			\app\commons\PeisongUser::addmoney(aid,$this->psuser['id'],$ticheng,'配送提成');
			if($psorder['type']=='restaurant_takeaway_order'){
				\app\customs\Restaurant::takeaway_orderconfirm($psorder['orderid']);
			}
			if($psorder['type'] =='cycle_order_stage'){
			    Db::name('cycle_order_stage')->where('id',$psorder['orderid'])->update(['collect_time' => time(),'status' => 3]);
            }
		}
		return $this->json(['status'=>1,'msg'=>'操作成功']);
	}
	//我的配送单
	public function orderlist(){
		$st = input('param.st');
		if(!input('?param.st') || $st === ''){
			$st = 'all';
		}
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['psid','=',$this->psuser['id']];
		$where[] = ['status','<>',10];
		if($st == '11'){
			$where[] = ['status','<>',4];
		}elseif($st == '0'){
			$where[] = ['status','=',0];
		}elseif($st == '1'){
			$where[] = ['status','=',1];
		}elseif($st == '2'){
			$where[] = ['status','=',2];
		}elseif($st == '3'){
			$where[] = ['status','=',3];
		}elseif($st == '4'){
			$where[] = ['status','=',4];
		}
		if(input('param.keyword')){
			$where[] = ['binfo','like','%'.input('param.keyword').'%'];
		}
		$pernum = 10;
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$psuser = Db::name('peisong_user')->where('id',$this->psuser['id'])->find();
		$datalist = Db::name('peisong_order')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
		if(!$datalist) $datalist = array();
        $mapqq = new \app\commons\MapQQ();
		foreach($datalist as $key=>$v){
			$datalist[$key]['orderinfo'] = json_decode($v['orderinfo'],true);
			$datalist[$key]['binfo'] = json_decode($v['binfo'],true);
			$datalist[$key]['prolist'] = json_decode($v['prolist'],true);
			if($v['juli']> 1000){
				$datalist[$key]['juli'] = round($v['juli']/1000,1);
				$datalist[$key]['juli_unit'] = 'km';
			}else{
				$datalist[$key]['juli_unit'] = 'm';
			}
            $bicycl = $mapqq->getDirectionDistance($v['longitude2'],$v['latitude2'],$psuser['longitude'],$psuser['latitude'],1);
            if($bicycl && $bicycl['status']==1) {
                $juli2 = $bicycl['distance'];
            }else{
                $juli2 = getdistance($v['longitude2'],$v['latitude2'],$psuser['longitude'],$psuser['latitude'],1);
            }
			$datalist[$key]['juli2'] = $juli2;
			if($juli2> 1000){
				$datalist[$key]['juli2'] = round($juli2/1000,1);
				$datalist[$key]['juli2_unit'] = 'km';
			}else{
				$datalist[$key]['juli2_unit'] = 'm';
			}
			$datalist[$key]['leftminute'] = ceil(($v['yujitime'] - time()) / 60);
			$datalist[$key]['ticheng'] = round($v['ticheng'],2);
			if($v['status']==4){
				$datalist[$key]['useminute'] = ceil(($v['endtime'] - $v['createtime']) / 60);
				$datalist[$key]['useminute2'] = ceil(($v['endtime'] - $v['starttime']) / 60); 
			}
		}
		$rdata = [];
		$rdata['status'] = 1;
		$rdata['datalist'] = $datalist;
		$rdata['nowtime'] = time();
		return $this->json($rdata);
	}
	//订单详情
	public function orderdetail(){
		$psuser = Db::name('peisong_user')->where('id',$this->psuser['id'])->find();
		$psorder = Db::name('peisong_order')->where('id',input('param.id/d'))->where(function ($query) use ($psuser) {
		    $query->where('psid', $psuser['id'])->whereOr('psid is null ')->whereOr('psid',0);
        })->find();

		if(empty($psorder)) {
            return $this->json(['status'=>0,'msg'=>'不存在的信息']);
        }

		$orderinfo = json_decode($psorder['orderinfo'],true);
		$binfo = json_decode($psorder['binfo'],true);
		$prolist = json_decode($psorder['prolist'],true);
		//配送员距商家距离
		if($psorder['juli']> 1000){
			$psorder['juli'] = round($psorder['juli']/1000,1);
			$psorder['juli_unit'] = 'km';
		}else{
			$psorder['juli_unit'] = 'm';
		}
        //配送员距会员距离 查询骑行距离
        $mapqq = new \app\commons\MapQQ();
        $bicycl = $mapqq->getDirectionDistance($psorder['longitude2'],$psorder['latitude2'],$psuser['longitude'],$psuser['latitude'],1);
        if($bicycl && $bicycl['status']==1){
            $juli2 = $bicycl['distance'];
        }else{
            $juli2 = getdistance($psorder['longitude2'],$psorder['latitude2'],$psuser['longitude'],$psuser['latitude'],1);
        }
		$psorder['juli2'] = $juli2;
		if($juli2> 1000){
			$psorder['juli2'] = round($juli2/1000,1);
			$psorder['juli2_unit'] = 'km';
		}else{
			$psorder['juli2_unit'] = 'm';
		}
		$psorder['leftminute'] = ceil(($psorder['yujitime'] - time()) / 60);
		$psorder['ticheng'] = round($psorder['ticheng'],2);
		if($psorder['status']==4){
			$psorder['useminute'] = ceil(($psorder['endtime'] - $psorder['createtime']) / 60);
			$psorder['useminute2'] = ceil(($psorder['endtime'] - $psorder['starttime']) / 60); 
		}
		if(getcustom('paotui')){
            if($orderinfo && $orderinfo['expect_take_time'] && $orderinfo['expect_take_time']>0){
            	$orderinfo['expect_take_time'] = date("Y-m-d H:i",$orderinfo['expect_take_time']);
            }else{
            	$orderinfo['expect_take_time'] = '立即取件';
            }
        }
		$rdata = [];
		$rdata['psorder'] = $psorder;
		$rdata['binfo'] = $binfo;
		$rdata['psuser'] = $psuser;
		$rdata['orderinfo'] = $orderinfo;
		$rdata['prolist'] = $prolist;
		if(getcustom('paotui')){
			$admin = Db::name('admin_set')->where('aid',aid)->field('tel')->find();
			$rdata['shop_tel'] = $admin && $admin['tel']? $admin['tel']:'';
		}
		return $this->json($rdata);
	}
	//开启 关闭接单
	public function setpsst(){
		$st = input('param.st/d');
		Db::name('peisong_user')->where('id',$this->psuser['id'])->update(['status'=>$st]);
		return $this->json(['status'=>1,'msg'=>'操作成功','url'=>true]);
	}

	//我的
	public function my(){
//		$set = Db::name('peisong_set')->where('aid',aid)->find();
		$psuser = Db::name('peisong_user')->where('id',$this->psuser['id'])->find();
		$member = Db::name('member')->where('id',$psuser['mid'])->find();
		$psuser['headimg'] = $member['headimg'];
		$psuser['nickname'] = $member['nickname'];
		if(!$psuser['totalmoney']) $psuser['totalmoney'] = 0;
		return $this->json(['status'=>1,'psuser'=>$psuser]);
	}
	//余额提现
	public function withdraw(){
		$set = db('peisong_set')->where(['aid'=>aid])->field('withdrawmin,withdrawfee,withdraw_weixin,withdraw_aliaccount,withdraw_bankcard')->find();
		if(request()->isPost()){
			$post = input('post.');
			//if($set['withdraw'] == 0){
			//	return ['status'=>0,'msg'=>'余额提现功能未开启'];
			//}
			$binfo = Db::name('peisong_user')->where('id',$this->psuser['id'])->find();
			if($post['paytype']=='支付宝' && $binfo['aliaccount']==''){
				return $this->json(['status'=>0,'msg'=>'请先设置支付宝账号']);
			}
			if($post['paytype']=='银行卡' && ($binfo['bankname']==''||$binfo['bankcarduser']==''||$binfo['bankcardnum']=='')){
				return $this->json(['status'=>0,'msg'=>'请先设置完整银行卡信息']);
			}

			$money = $post['money'];
			if($money<=0 || $money < $set['withdrawmin']){
				return $this->json(['status'=>0,'msg'=>'提现金额必须大于'.($set['withdrawmin']?$set['withdrawmin']:0)]);
			}
			if($money > $binfo['money']){
				return $this->json(['status'=>0,'msg'=>'可提现余额不足']);
			}
			
			$ordernum = date('ymdHis').aid.rand(1000,9999);
			$record = [];
			$record['aid'] = aid;
			$record['uid'] = $this->psuser['id'];
			$record['createtime']= time();
			$record['money'] = $money*(1-$set['withdrawfee']*0.01);
			$record['txmoney'] = $money;
			if($post['paytype']=='微信钱包'){
				$record['weixin'] = $binfo['weixin'];
			}
			if($post['paytype']=='支付宝'){
				$record['aliaccount'] = $binfo['aliaccount'];
			}
			if($post['paytype']=='银行卡'){
				$record['bankname'] = $binfo['bankname'];
				$record['bankcarduser'] = $binfo['bankcarduser'];
				$record['bankcardnum'] = $binfo['bankcardnum'];
			}
			$record['ordernum'] = $ordernum;
			$record['paytype'] = $post['paytype'];
			$recordid = db('peisong_withdrawlog')->insertGetId($record);

			\app\commons\PeisongUser::addmoney(aid,$this->psuser['id'],-$money,'余额提现');
			
			return $this->json(['status'=>1,'msg'=>'提交成功,请等待打款']);
		}
		$userinfo = db('peisong_user')->where(['id'=>$this->psuser['id']])->field('id,money,weixin,aliaccount,bankname,bankcarduser,bankcardnum')->find();
		
		$rdata = [];
		$rdata['userinfo'] = $userinfo;
		$rdata['sysset'] = $set;
		return $this->json($rdata);
	}
	
	public function set(){
		$smsset = Db::name('admin_set_sms')->where('aid',aid)->find();
		if($smsset && $smsset['status'] == 1 && $smsset['tmpl_smscode'] && $smsset['tmpl_smscode_st']==1){
			$needsms = 1;
		}else{
			$needsms = 0;
		}
		if(request()->isPost()){
			$formdata = input('post.');
			if($needsms==1){
				if(md5($this->psuser['tel'].'-'.$formdata['code']) != cache(input('param.session_id').'_smscode') || cache(input('param.session_id').'_smscodetimes') > 5){
					return $this->json(['status'=>0,'msg'=>'短信验证码错误']);
				}
			}
			cache(input('param.session_id').'_smscode',null);
			cache(input('param.session_id').'_smscodetimes',null);
			$info = [];
			$info['weixin'] = $formdata['weixin'];
			$info['aliaccount'] = $formdata['aliaccount'];
			$info['bankname'] = $formdata['bankname'];
			$info['bankcarduser'] = $formdata['bankcarduser'];
			$info['bankcardnum'] = $formdata['bankcardnum'];
			Db::name('peisong_user')->where('id',$this->psuser['id'])->update($info);
			return $this->json(['status'=>1,'msg'=>'修改成功']);
		}
		$userinfo = Db::name('peisong_user')->where('id',$this->psuser['id'])->field('id,realname,tel,weixin,aliaccount,bankname,bankcarduser,bankcardnum')->find();
		$rdata = [];
		$rdata['needsms'] = $needsms;
		$rdata['userinfo'] = $userinfo;
		return $this->json($rdata);
	}
	//余额明细
	public function moneylog(){
		$st = input('param.st');
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$pernum = 20;
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['uid','=',$this->psuser['id']];
		if($st ==2){//提现记录
			$datalist = Db::name('peisong_withdrawlog')->field("id,money,txmoney,`status`,createtime")->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
			if(!$datalist) $datalist = [];
		}else{ //余额明细
			$datalist = Db::name('peisong_moneylog')->field("id,money,`after`,createtime,remark")->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
			if(!$datalist) $datalist = [];
		}
		return $this->json(['status'=>1,'data'=>$datalist]);
	}
}