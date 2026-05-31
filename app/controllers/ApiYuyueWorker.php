<?php


namespace app\controllers;
use think\facade\Db;
class ApiYuyueWorker extends ApiCommon
{
	public $worker;
	public function initialize(){
		parent::initialize();
		//$this->checklogin();
		if(!$this->member){
			echojson(['status'=>-4,'msg'=>'请先登录服务人员绑定的用户','url'=>'/pages/index/login?frompage=/activity/yuyue/my']);
		}
		if(!$this->worker && request()->action() != 'login'){
			$worker = Db::name('yuyue_worker')->where('aid',aid)->where('mid',mid)->find();
			if(!$worker){
				echojson(['status'=>-4,'msg'=>'请先登录','url'=>'/activity/yuyue/login']);
			}else{
				$this->worker = $worker;
			}
		}
		//查看状态
		}
	//服务人员登录
	public function login(){
		if(request()->isPost()){
			$username = trim(input('post.username'));
			$password = trim(input('post.password'));
			$captcha = trim(input('post.captcha'));
			if($username=='' || $password==''){
				return $this->json(['status'=>0,'msg'=>'用户名和密码不能为空']);
			}elseif($captcha == ''){
				return $this->json(['status'=>0,'msg'=>'验证码不能为空']);
			}elseif(strtolower($captcha) != strtolower(cache($this->sessionid.'_captcha'))){
				 return $this->json(['status'=>0,'msg'=>'验证码错误']);
			}
			$rs = Db::name('yuyue_worker')->where('aid',aid)->where(['un'=>$username,'pwd'=>md5($password)])->find();
			if($rs){
				$aid = $rs['aid'];
				if($rs['status']!=1) return ['status'=>0,'msg'=>'账号未启用'];
				Db::name('yuyue_worker')->where('aid',aid)->where('mid',mid)->update(['mid'=>'']);
				Db::name('yuyue_worker')->where('id',$rs['id'])->update(['mid'=>mid]);
				if(!$rs['headimg']){
					Db::name('yuyue_worker')->where('id',$rs['id'])->update(['headimg'=>$rs['headimg']]);
				}
				return $this->json(['status'=>1,'msg'=>'登录成功']);
			}else{
				return $this->json(['status'=>2,'msg'=>'账号或密码错误']);
			}
		}else{
			return $this->json(['status'=>1]);
		}
	}
	//修改密码
	public function setpwd(){
		if(request()->isPost()){
			$user = Db::name('yuyue_worker')->where('id',$this->worker['id'])->find();
			$oldpwd = input('post.oldpwd');
			$pwd = input('post.pwd');
			if(md5($oldpwd)!=$user['pwd']){
				return $this->json(['status'=>0,'msg'=>'原密码输入错误']);
			}
			Db::name('yuyue_worker')->where('id',$user['id'])->update(['pwd'=>md5($pwd)]);
			return $this->json(['status'=>1,'msg'=>'修改成功']);
		}
		$user = Db::name('yuyue_worker')->field('id,un')->where('id',$this->worker['id'])->find();
		$rdata = [];
		$rdata['status'] = 1;
		$rdata['user'] = $user;
		return $this->json($rdata);
	}
	//更新我的位置
	public function updatemylocation(){
		$longitude = input('param.longitude');
		$latitude = input('param.latitude');
		if($latitude && $longitude){
			Db::name('yuyue_worker')->where('id',$this->worker['id'])->update(['longitude'=>$longitude,'latitude'=>$latitude]);
		}
		return $this->json(['status'=>1,'msg'=>'']);
	}
	//接单大厅
	public function dating(){
        $allBid = false;
        //使用平台
        $where = [];
		$where[] = ['aid','=',aid];
        if(!$allBid){
            $where[] = ['bid','=',$this->worker['bid']];
        }
		$where[] = ['worker_id','=',0];
		$where[] = ['status','=',0];
		if(input('param.keyword')){
			$where[] = ['binfo','like','%'.input('param.keyword').'%'];
		}
		$pernum = 20;
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$worker = Db::name('yuyue_worker')->where('id',$this->worker['id'])->find();
		$category = Db::name('yuyue_worker_category')->where('id',$worker['cid'])->find();
		$datalist = Db::name('yuyue_worker_order')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
		if(!$datalist) $datalist = array();
        $datalist = $this->getListsdistance($datalist,$worker);
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
			//师傅到用户的距离 骑行距离
//            $mapqq = new \app\commons\MapQQ();
//            $bicycl = $mapqq->getDirectionDistance($v['longitude2'],$v['latitude2'],$worker['longitude'],$worker['latitude'],1);
//            if($bicycl && $bicycl['status']==1){
//                $juli2 = $bicycl['distance'];
//            }else{
//                $juli2 = getdistance($v['longitude2'],$v['latitude2'],$worker['longitude'],$worker['latitude'],1);
//            }
//			$datalist[$key]['juli2'] = $juli2;
            $juli2 = $v['juli2'];
			if($juli2> 1000){
				$datalist[$key]['juli2'] = round($juli2/1000,1);
				$datalist[$key]['juli2_unit'] = 'km';
			}else{
				$datalist[$key]['juli2_unit'] = 'm';
			}
			$datalist[$key]['leftminute'] = ceil(($v['yujitime'] - time()) / 60);
			$datalist[$key]['ticheng'] = round($v['ticheng'],2);



			//查看可抢单时间
			$datalist[$key]['isqd']=true;
			}
		$set = Db::name('yuyue_set')->where('aid',aid)->where('bid',$this->worker['bid'])->find();
		$showform = $set['formurl'] ? 1 : 0;
		
		$isdelayed = false;
		$rdata = [];
		$rdata['status'] = 1;
		$rdata['isdelayed'] = $isdelayed;
		$rdata['datalist'] = $datalist;
		$rdata['nowtime'] = time();
		$rdata['showform'] = $showform;
		return $this->json($rdata);
	}
	//抢单
	public function qiangdan(){
		$id = input('param.id/d');
		if(!$id) return $this->json(['status'=>0,'msg'=>'参数错误']);
		$psorder = Db::name('yuyue_worker_order')->where('aid',aid)->where('id',$id)->find();
		if(!$psorder) return $this->json(['status'=>0,'msg'=>'订单不存在']);
		if($psorder['status']!=0) return $this->json(['status'=>0,'msg'=>'手慢了']);

		//查看该服务人员是否在该时间已经接单
		$orders = Db::name('yuyue_order')->where('aid',aid)->where('id',$psorder['orderid'])->find();
		$order = Db::name('yuyue_order')->where('aid',aid)->where('worker_id',$this->worker['id'])->where('status','in','1,2')->where('yy_time',$orders['yy_time'])->find();
		if($order) return $this->json(['status'=>0,'msg'=>'此时间您已经接单，不可再抢单']);
		
		//查看订单状态
		if($orders['status']==4) 	return $this->json(['status'=>0,'msg'=>'订单已取消']);

		Db::name('yuyue_order')->where('aid',aid)->where('id',$psorder['orderid'])->update(['worker_id'=>$this->worker['id'],'worker_orderid'=>$id,'send_time'=>time()]);
		Db::name('yuyue_worker_order')->where('id',$id)->update(['status'=>1,'worker_id'=>$this->worker['id'],'status'=>1,'starttime'=>time()]);
		send_socket(['type'=>'yuyue_worker_jiedan','data'=>['aid'=>aid,'mid'=>mid,'psorderid'=>$psorder['id']]]);
		return $this->json(['status'=>1,'msg'=>'抢单成功']);
	}
	//改状态
	public function setst(){
		$id = input('param.id/d');
		$st = input('param.st/d');
		$psorder = Db::name('yuyue_worker_order')->where('aid',aid)->where('worker_id',$this->worker['id'])->where('id',$id)->find();
		if(!$psorder) return $this->json(['status'=>0,'msg'=>'订单不存在']);
		
		$updata = [];
		$updata['status'] = $st;
		if(getcustom('extend_yuyue_car')){
			//取消订单
			if($st == 10){
				if($psorder['status']!=1) return $this->json(['status'=>0,'msg'=>'订单状态不符合']);
				$admin = Db::name('admin')->where('id',aid)->field('yuyuecar_status')->find();
				//查询是否有洗车权限
				if($admin && $admin['yuyuecar_status'] == 1){
					//查询用户订单
					$order = Db::name('yuyue_order')->where(['id'=>$psorder['orderid'],'worker_orderid'=>$id,'worker_id'=>$this->worker['id'],'aid'=>aid])->find();
					if($order){
						//只有洗车产品有此功能
						if($order['protype'] == 1){
							$cancel = Db::name('yuyue_worker_order')->where('id',$psorder['orderid'])->update(['status'=>10]);
							if($cancel){
								//重置订单
								$uporder = Db::name('yuyue_order')->where('id',$psorder['orderid'])->update(['worker_id'=>0,'worker_orderid'=>0]);

				                //下一个小时内的结束时间
                                $next_endtime = strtotime(date("Y-m-d H",$order['paytime']).':00:00')+2*60*60;

                                //转换预约时间
                                $yydate = explode('-',$order['yy_time']);
                                //开始时间
                                $begindate = $yydate[0];
                                if(strpos($begindate,'年') === false){
                                    $begindate = date('Y').'年'.$begindate;
                                }
                                $begindate = preg_replace(['/年|月/','/日/'],['-',''],$begindate);
                                $begintime = strtotime($begindate);

                                //如果等于或超出结束时间
                                if($begintime>=$next_endtime){
			                    //进入抢单大厅
				                    $rs = \app\models\YuyueWorkerOrder::create($order,0,'');
				                }else{
				                    //继续派单
				                    $worker_id = \app\customs\YuyueCustom::get_worker($order);
				                    if($worker_id){
				                        \app\models\YuyueWorkerOrder::create($order,$worker_id,'');
				                    }
				                }
							}
						}else{
							return $this->json(['status'=>0,'msg'=>'暂无此功能']);
						}
					}else{
						return $this->json(['status'=>0,'msg'=>'用户订单不存在']);
					}
				}else{
					return $this->json(['status'=>0,'msg'=>'暂无此功能']);
				}
			}
		}

		if($st == 2){
			if($psorder['status']!=1) return $this->json(['status'=>0,'msg'=>'订单状态不符合']);
			$uporder = [];
			$uporder['status'] = 2;

			$order = db('yuyue_order')->where(['aid'=>aid,'id'=>$psorder['orderid']])->update($uporder);
			$updata['daodiantime'] = time();
		}
		if($st == 3){
			if($psorder['status']!=2) return $this->json(['status'=>0,'msg'=>'订单状态不符合']);

			$order = db('yuyue_order')->where(['aid'=>aid,'id'=>$psorder['orderid']])->find();
			if($order['balance_price']>0 && $order['balance_pay_status']!=1){
				return $this->json(['status'=>0,'msg'=>'请等顾客支付尾款后，再点击完成']);
			}
			$uporder = [];
			$uporder['status'] = 3;
			$uporder['collect_time'] = time();
			$updata['endtime'] = time();
			Db::name('yuyue_worker')->where('id',$this->worker['id'])->inc('totalnum')->update();
			db('yuyue_order')->where(['aid'=>aid,'id'=>$psorder['orderid']])->update($uporder);
			$rs = \app\commons\Order::collect($order,'yuyue');
			if($rs['status'] == 0) return $this->json($rs);
			\app\commons\YuyueWorker::addmoney(aid,$psorder['bid'],$this->worker['id'],$psorder['ticheng'],'服务提成');

		}
		Db::name('yuyue_worker_order')->where('id',$id)->update($updata);
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
		$where[] = ['worker_id','=',$this->worker['id']];
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
		if(input('param.mid')){
			$where[] = ['mid','=',input('param.mid')];
		}
		$pernum = 10;
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$worker = Db::name('yuyue_worker')->where('id',$this->worker['id'])->find();
		$datalist = Db::name('yuyue_worker_order')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
		if(!$datalist) $datalist = array();
		$datalist = $this->getListsdistance($datalist,$worker);
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
			//服务人员到用户的距离 骑行距离
//            $mapqq = new \app\commons\MapQQ();
//            $bicycl = $mapqq->getDirectionDistance($v['longitude2'],$v['latitude2'],$worker['longitude'],$worker['latitude'],1);
//            $mapqq = new \app\commons\MapQQ();
//            $bicycl = $mapqq->getDirectionDistance($v['longitude2'],$v['latitude2'],$worker['longitude'],$worker['latitude'],1);
//            if($bicycl && $bicycl['status']==1){
//                $juli2 = $bicycl['distance'];
//            }else{
//                $juli2 = getdistance($v['longitude2'],$v['latitude2'],$worker['longitude'],$worker['latitude'],1);
//            }
            $juli2 = $v['juli2'];
			$datalist[$key]['juli2'] = $juli2;
			if($juli2> 1000){
				$datalist[$key]['juli2'] = round($juli2/1000,1);
				$datalist[$key]['juli2_unit'] = 'km';
			}else{
				$datalist[$key]['juli2_unit'] = 'm';
			}
			$datalist[$key]['leftminute'] = ceil(($v['yujitime'] - time()) / 60);
			$datalist[$key]['ticheng'] = round($v['ticheng'],2);
			if($v['status']==2){
				$datalist[$key]['useminute'] = ceil(($v['daodiantime'] - $v['createtime']) / 60);
				$datalist[$key]['useminute2'] = ceil(($v['endtime'] - $v['starttime']) / 60); 
			}
			$datalist[$key]['order_status'] = 1;
            $datalist[$key]['showprice'] = false;
            }
		$set = Db::name('yuyue_set')->where('aid',aid)->where('bid',$this->worker['bid'])->find();
		$showform = $set['formurl'] ? 1 : 0;
		$addmoney = false;
		$rdata = [];
		$rdata['status'] = 1;
		$rdata['datalist'] = $datalist;
		$rdata['nowtime'] = time();
		$rdata['showform'] = $showform;
		$rdata['addmoney'] = $addmoney;
		return $this->json($rdata);
	}
	//订单详情
	public function orderdetail(){
		$worker = Db::name('yuyue_worker')->where('id',$this->worker['id'])->find();
		$psorder = Db::name('yuyue_worker_order')->where('id',input('param.id/d'))->find();
		if(!$psorder) 	return $this->json(['status'=>0,'msg'=>'配送单不存在']);
		//查看订单状态
		$order = Db::name('yuyue_order')->where('aid',aid)->where('id',$psorder['orderid'])->find();
        $paymoney = $order['totalprice'];
        if($order['balance_price']>0 && $order['balance_pay_status']==0){
            $paymoney = round($order['totalprice'] - $order['balance_price'],2);
        }
        $order['paymoney'] = $paymoney;
		if($order['status']==4) 	return $this->json(['status'=>0,'msg'=>'订单已取消']);
        $orderW = $psorder['orderinfo']?json_decode($psorder['orderinfo'],true):[];
		$orderinfo = array_merge($orderW,$order);
		$binfo = json_decode($psorder['binfo'],true);
		$prolist = json_decode($psorder['prolist'],true);
		
		if($psorder['juli']> 1000){
			$psorder['juli'] = round($psorder['juli']/1000,1);
			$psorder['juli_unit'] = 'km';
		}else{
			$psorder['juli_unit'] = 'm';
		}
		$juli2 = getdistance($psorder['longitude2'],$psorder['latitude2'],$worker['longitude'],$worker['latitude'],1);
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
		$yuyue_sign = false;
		$psorder['showprice'] = false;
        $psorder['isqd']=true;
		$isdelayed =false;
		$rdata = [];
		$rdata['psorder'] = $psorder;
		$rdata['binfo'] = $binfo;
		$rdata['worker'] = $worker;
		$rdata['orderinfo'] = $orderinfo;
		$rdata['prolist'] = $prolist;
		$rdata['yuyue_sign'] =$yuyue_sign;
		$rdata['isdelayed'] =$isdelayed;
		$rdata['nowtime'] = time();
		$text = ['上门服务'=>'上门服务','到店服务'=>'到店服务'];
		$rdata['text'] = $text;
		return $this->json($rdata);
	}
	//开启 关闭接单
	public function setpsst(){
		$st = input('param.st/d');
		Db::name('yuyue_worker')->where('id',$this->worker['id'])->update(['status'=>$st]);
		return $this->json(['status'=>1,'msg'=>'操作成功','url'=>true]);
	}

	//我的
	public function my(){
		$set = Db::name('yuyue_set')->where('aid',aid)->where('bid',$this->worker['bid'])->find();
		$worker = Db::name('yuyue_worker')->where('id',$this->worker['id'])->find();
		//查看状态
		$member = Db::name('member')->where('id',$worker['mid'])->find();
		$worker['headimg'] = $member['headimg'];
		$worker['nickname'] = $member['nickname'];
		if(!$worker['totalmoney']) $worker['totalmoney'] = 0;
		$showform = $set['formurl'] ? 1 : 0;
		
		$searchmember = false;
		$sets = false;
		return $this->json(['status'=>1,'worker'=>$worker,'showform'=>$showform,'searchmember'=>$searchmember,'sets'=>$sets]);
	}
	//余额提现
	public function withdraw(){
		$set = db('yuyue_set')->where('aid',aid)->where('bid',$this->worker['bid'])->field('withdrawmin,withdrawfee,withdraw_weixin,withdraw_aliaccount,withdraw_bankcard')->find();
		if(request()->isPost()){
			$post = input('post.');
			//if($set['withdraw'] == 0){
			//	return ['status'=>0,'msg'=>'余额提现功能未开启'];
			//}
			$binfo = Db::name('yuyue_worker')->where('id',$this->worker['id'])->find();
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
			$record['bid'] = $this->worker['bid'];
			$record['uid'] = $this->worker['id'];
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
			$recordid = db('yuyue_worker_withdrawlog')->insertGetId($record);

			\app\commons\YuyueWorker::addmoney(aid,$binfo['bid'],$this->worker['id'],-$money,'余额提现');
			
			return $this->json(['status'=>1,'msg'=>'提交成功,请等待打款']);
		}
		$userinfo = db('yuyue_worker')->where(['id'=>$this->worker['id']])->field('id,money,weixin,aliaccount,bankname,bankcarduser,bankcardnum')->find();
		
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
				if(md5($this->worker['tel'].'-'.$formdata['code']) != cache(input('param.session_id').'_smscode') || cache(input('param.session_id').'_smscodetimes') > 5){
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
			Db::name('yuyue_worker')->where('id',$this->worker['id'])->update($info);
			return $this->json(['status'=>1,'msg'=>'修改成功']);
		}
		$userinfo = Db::name('yuyue_worker')->where('id',$this->worker['id'])->field('id,realname,tel,weixin,aliaccount,bankname,bankcarduser,bankcardnum')->find();
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
		$where[] = ['uid','=',$this->worker['id']];
		if($st ==2){//提现记录
			$datalist = Db::name('yuyue_worker_withdrawlog')->field("id,money,txmoney,`status`,createtime")->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
			if(!$datalist) $datalist = [];
		}else{ //余额明细
			$datalist = Db::name('yuyue_worker_moneylog')->field("id,money,`after`,createtime,remark")->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
			if(!$datalist) $datalist = [];
		}
		return $this->json(['status'=>1,'data'=>$datalist]);
	}
	public function addmoney(){
		$woker_orderid = input('post.id');
		$price = input('post.price');
		if(!$price) return $this->json(['status'=>0,'msg'=>'请填写金额']);
		$wokerorder = Db::name('yuyue_worker_order')->where(['id'=>$woker_orderid])->find();
		if(!$wokerorder ) 	return $this->json(['status'=>0,'msg'=>'服务订单不存在']);
		$order = Db::name('yuyue_order')->where(['id'=>$wokerorder['orderid']])->find();
		if(!$order ) return $this->json(['status'=>0,'msg'=>'预约订单不存在']);
		$addmoneyPayorderid = input('post.addmoneyPayorderid');
		if($addmoneyPayorderid){
			//修改
			$payorder = Db::name('payorder')->where(['id'=>$addmoneyPayorderid])->find();
			if(!$payorder){
				return $this->json(['status'=>0,'msg'=>'支付订单不存在']);
			}
			Db::name('payorder')->where(['id'=>$addmoneyPayorderid])->update(['money'=>$price]);
			$balance_pay_orderid = $addmoneyPayorderid;
			$order = Db::name('yuyue_order')->where(['id'=>$wokerorder['orderid']])->update(['addmoney'=>$price]);
		}else{
			//创建支付订单
			$balancedata = [];
			$balancedata['aid'] = aid;
			$balancedata['bid'] = $order['bid'];
			$balancedata['mid'] = $order['mid'];
			$balancedata['orderid'] = $order['id'];
			$balancedata['ordernum'] = $order['ordernum'].'01';
			$balancedata['title'] = $order['title'].'补余款';
			$balancedata['money'] = $price;
			$balancedata['type'] = 'yuyue_addmoney';
			$balancedata['score'] = 0;
			$balancedata['createtime'] = time();
			$balancedata['status'] = 0;
			$balance_pay_orderid = Db::name('payorder')->insertGetId($balancedata);
			$data = [];
			$data['addmoney'] = $price;
			$data['addmoneyPaycode'] =  createqrcode(m_url('pages/pay/pay?id='.$balance_pay_orderid));
			$data['addmoneyPayorderid'] = $balance_pay_orderid;
			$order = Db::name('yuyue_order')->where(['id'=>$wokerorder['orderid']])->update($data);
		}
	    return $this->json(['status'=>0,'msg'=>'创建成功','payorderid'=>$balance_pay_orderid]);

	}
	//修改资料
	public function sets(){
		if(input('param.bid')){
			$bid = input('param.bid/d');
		}else{
			$bid = 0;
		}
		if(request()->isPost()){
			$formdata = input('post.info/a');
			$info = [];
			$info['aid'] = aid;
			$info['mid'] = mid;
			$info['bid'] = $formdata['bid'];
			$info['cid'] = $formdata['cid'];
			$info['realname'] = $formdata['realname'];
			$info['tel'] = $formdata['tel'];
			$info['age'] = $formdata['age'];
			$info['sex'] = $formdata['sex'];
			$info['tel'] = $formdata['tel'];
			$info['citys'] = $formdata['citys'];
			$info['latitude'] = $formdata['latitude'];
			$info['longitude'] = $formdata['longitude'];
			$info['fuwu_juli'] = $formdata['fuwu_juli'];
			$info['codepic'] = $formdata['codepic'];
			$info['otherpic'] = $formdata['otherpic'];
			$info['headimg'] = $formdata['headimg'];
			$info['un'] = $formdata['un'];
			$info['desc'] = $formdata['desc'];
			$info['status'] = 1;
			$info['shstatus'] = 1;
			$info['fwcids'] = implode(',',$formdata['fwcids']);
			if($formdata['id']){
				Db::name('yuyue_worker')->where('aid',aid)->where('bid',$info['bid'])->where('id',$formdata['id'])->update($info);
				//查看服务类目
				return $this->json(['status'=>1,'msg'=>'修改成功']);
			}else{
				return $this->json(['status'=>1,'msg'=>'资料不存在']);
			}
		}
		$info = Db::name('yuyue_worker')->where('id',$this->worker['id'])->find();
		$clist = Db::name('yuyue_worker_category')->where('aid',aid)->where('status',1)->where('bid',$bid)->order('sort desc,id')->select()->toArray();
		//商家
		$blist1 = [['id'=>'0','name'=>'平台自营']];
		$blist = Db::name('business')->where('aid',aid)->where('status',1)->order('sort desc,id')->select()->toArray();
		$blist = array_merge($blist1,$blist);
		$fwcateArr = Db::name('yuyue_category')->Field('id,name')->where('aid',aid)->column('name','id');
		$busarr = Db::name('business')->Field('id,name')->where('aid',aid)->column('name','id');

		$fwclist =  Db::name('yuyue_category')->where('aid',aid)->where('status',1)->where('bid',$bid)->order('sort desc,id')->select()->toArray();
		foreach($fwclist as $k=>$v){
			$rs = Db::name('yuyue_category')->where('aid',aid)->where('pid',$v['id'])->where('status',1)->order('sort desc,id')->select()->toArray();
			if(!$rs) $rs = [];
			$fwclist[$k]['child'] = $rs;
		}
		$rdata = []; 
        $rdata['title'] = '修改资料';
		$rdata['clist'] = $clist;
		$rdata['blist'] = $blist;
		$rdata['fwcateArr'] = $fwcateArr;
		$rdata['info'] = $info ? $info : [];
		$rdata['fwcids'] = $info['fwcids'] ? explode(',',$info['fwcids']) : [];
		$rdata['busarr'] = $busarr ? $busarr : [];
		$rdata['fwclist'] = $fwclist ? $fwclist : [];
		return $this->json($rdata);
	}

    //服务人员修改预约订单价格
    public function changeprice(){
        }

    //获取订单列表距离
    public function getListsdistance($datalist,$worker){
        $to = [];
        foreach($datalist as $key=>$v){
            if($v['longitude2'] && $v['latitude2']){
                $to[] = $v['latitude2'].','.$v['longitude2'];
            }
        }
        $to_str = implode($to,';');
        $mapqq = new \app\commons\MapQQ();
        $res = $mapqq->getDirectionDistanceMatrix($worker['longitude'],$worker['latitude'],$to_str,1);
        $distance_arr = $res['distance_arr'];
        $distance_arr_new = [];
        foreach($to as $t_k=>$t_v){
            $distance_arr_new[$t_v] = $distance_arr[$t_k];
        }
        foreach($datalist as $key=>$v){
            $lat_lng = $v['latitude2'].','.$v['longitude2'];
            if(!empty($distance_arr_new[$lat_lng])){
                $juli2 = $distance_arr_new[$lat_lng];
            }else{
                $juli2 = getdistance($v['longitude2'],$v['latitude2'],$worker['longitude'],$worker['latitude'],1);
            }
            $datalist[$key]['juli2'] = $juli2;
        }
        return $datalist;
    }
}