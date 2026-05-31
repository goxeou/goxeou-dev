<?php


namespace app\controllers;
use think\facade\Db;
use think\facade\Log;
class ApiExpress extends ApiCommon
{
	public function initialize(){
		parent::initialize();
		$this->checklogin();
	}
	
	public function getAddress(){
		$type = input('param.type');
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['mid','=',mid];
		$where[]=['mailtype','=','1'];
		$address = Db::name('express_address')->where($where)->order('isdefault desc,id desc')->find();
		if(!$address) $address = [];

		$address2 = Db::name('express_address')->where(['aid'=>aid,'mid'=>mid,'mailtype'=>2])->order('isdefault desc,id desc')->find();
		if(!$address2) $address2 = [];

		$data = [];
		$data['address'] = $address;
		$data['address2'] = $address2;
		//快递公司
		$data['expressdata'] =  array_keys(express_data100());;

		//pstimeArr
		$set = Db::name('express_sysset')->where('aid',aid)->find();
		$pstimedata = json_decode($set['pstimedata'],true);
		$pstimeArr = [];
		foreach($pstimedata as $k2=>$v2){
			if($v2['day']==1){
				$thistxt = date('m月d日').' '.($v2['hour']<=9?'0'.$v2['hour']:$v2['hour']).':'.($v2['minute']<=9?'0'.$v2['minute']:$v2['minute']);
				$thisval = date('Y-m-d').' '.($v2['hour']<=9?'0'.$v2['hour']:$v2['hour']).':'.($v2['minute']<=9?'0'.$v2['minute']:$v2['minute']);
				$thisval2 = date('Y-m-d').' '.($v2['hour2']<=9?'0'.$v2['hour2']:$v2['hour2']).':'.($v2['minute2']<=9?'0'.$v2['minute2']:$v2['minute2']);
				if(strtotime($thisval2) > time() + 3600*$freight['psprehour']){
					if($v2['hour2'] != $v2['hour'] || $v2['minute2'] != $v2['minute']){
						$thistxt.= ('-'.($v2['hour2']<=9?'0'.$v2['hour2']:$v2['hour2']).':'.($v2['minute2']<=9?'0'.$v2['minute2']:$v2['minute2']));
						$thisval.= ('~'.($v2['hour2']<=9?'0'.$v2['hour2']:$v2['hour2']).':'.($v2['minute2']<=9?'0'.$v2['minute2']:$v2['minute2']));
					}
					$pstimeArr[] = [
						'title'=>$thistxt.'（今天）',
						'value'=>$thisval,
						'bid'=>$set['bid'],
					];
				}
			}
			if($v2['day']==2){
				$thistxt = ''.date('m月d日',time()+86400).' '.($v2['hour']<=9?'0'.$v2['hour']:$v2['hour']).':00';
				$thisval = date('Y-m-d',time()+86400).' '.($v2['hour']<=9?'0'.$v2['hour']:$v2['hour']).':'.($v2['minute']<=9?'0'.$v2['minute']:$v2['minute']);
				$thisval2 = date('Y-m-d',time()+86400).' '.($v2['hour2']<=9?'0'.$v2['hour2']:$v2['hour2']).':'.($v2['minute2']<=9?'0'.$v2['minute2']:$v2['minute2']);
				if(strtotime($thisval2) > time() + 3600*$freight['psprehour']){
					if($v2['hour2'] != $v2['hour'] || $v2['minute2'] != $v2['minute']){
						$thistxt.= ('-'.($v2['hour2']<=9?'0'.$v2['hour2']:$v2['hour2']).':'.($v2['minute2']<=9?'0'.$v2['minute2']:$v2['minute2']));
						$thisval.= ('~'.($v2['hour2']<=9?'0'.$v2['hour2']:$v2['hour2']).':'.($v2['minute2']<=9?'0'.$v2['minute2']:$v2['minute2']));
					}
					$pstimeArr[] = [
						'title'=>$thistxt.'（明天）',
						'value'=>$thisval,
						'bid'=>$set['bid'],
					];
				}
			}
			if($v2['day']==3){
				$thistxt = ''.date('m月d日',time()+86400*2).' '.($v2['hour']<=9?'0'.$v2['hour']:$v2['hour']).':00';
				$thisval = date('Y-m-d',time()+86400*2).' '.($v2['hour']<=9?'0'.$v2['hour']:$v2['hour']).':'.($v2['minute']<=9?'0'.$v2['minute']:$v2['minute']);
				$thisval2 = date('Y-m-d',time()+86400*2).' '.($v2['hour2']<=9?'0'.$v2['hour2']:$v2['hour2']).':'.($v2['minute2']<=9?'0'.$v2['minute2']:$v2['minute2']);
				if(strtotime($thisval2) > time() + 3600*$freight['psprehour']){
					if($v2['hour2'] != $v2['hour'] || $v2['minute2'] != $v2['minute']){
						$thistxt.= ('-'.($v2['hour2']<=9?'0'.$v2['hour2']:$v2['hour2']).':'.($v2['minute2']<=9?'0'.$v2['minute2']:$v2['minute2']));
						$thisval.= ('~'.($v2['hour2']<=9?'0'.$v2['hour2']:$v2['hour2']).':'.($v2['minute2']<=9?'0'.$v2['minute2']:$v2['minute2']));
					}
					$pstimeArr[] = [
						'title'=>$thistxt.'（后天）',
						'value'=>$thisval,
						'bid'=>$set['bid'],
					];
				}
			}
			if($v2['day']==4){
				$thistxt = ''.date('m月d日',time()+86400*3).' '.($v2['hour']<=9?'0'.$v2['hour']:$v2['hour']).':00';
				$thisval = date('Y-m-d',time()+86400*3).' '.($v2['hour']<=9?'0'.$v2['hour']:$v2['hour']).':'.($v2['minute']<=9?'0'.$v2['minute']:$v2['minute']);
				$thisval2 = date('Y-m-d',time()+86400*3).' '.($v2['hour2']<=9?'0'.$v2['hour2']:$v2['hour2']).':'.($v2['minute2']<=9?'0'.$v2['minute2']:$v2['minute2']);
				if(strtotime($thisval2) > time() + 3600*$freight['psprehour']){
					if($v2['hour2'] != $v2['hour'] || $v2['minute2'] != $v2['minute']){
						$thistxt.= ('-'.($v2['hour2']<=9?'0'.$v2['hour2']:$v2['hour2']).':'.($v2['minute2']<=9?'0'.$v2['minute2']:$v2['minute2']));
						$thisval.= ('~'.($v2['hour2']<=9?'0'.$v2['hour2']:$v2['hour2']).':'.($v2['minute2']<=9?'0'.$v2['minute2']:$v2['minute2']));
					}
					$pstimeArr[] = [
						'title'=>$thistxt.'（大后天）',
						'value'=>$thisval,
						'bid'=>$set['bid'],
					];
				}
			}
			if($v2['day']>4){
				$thistxt = ''.date('m月d日',time()+86400*($v2['day']-1)).' '.($v2['hour']<=9?'0'.$v2['hour']:$v2['hour']).':00';
				$thisval = date('Y-m-d',time()+86400*($v2['day']-1)).' '.($v2['hour']<=9?'0'.$v2['hour']:$v2['hour']).':'.($v2['minute']<=9?'0'.$v2['minute']:$v2['minute']);
				$thisval2 = date('Y-m-d',time()+86400*($v2['day']-1)).' '.($v2['hour2']<=9?'0'.$v2['hour2']:$v2['hour2']).':'.($v2['minute2']<=9?'0'.$v2['minute2']:$v2['minute2']);
				if(strtotime($thisval2) > time() + 3600*$freight['psprehour']){
					if($v2['hour2'] != $v2['hour'] || $v2['minute2'] != $v2['minute']){
						$thistxt.= ('-'.($v2['hour2']<=9?'0'.$v2['hour2']:$v2['hour2']).':'.($v2['minute2']<=9?'0'.$v2['minute2']:$v2['minute2']));
						$thisval.= ('~'.($v2['hour2']<=9?'0'.$v2['hour2']:$v2['hour2']).':'.($v2['minute2']<=9?'0'.$v2['minute2']:$v2['minute2']));
					}
					$pstimeArr[] = [
						'title'=>$thistxt,
						'value'=>$thisval,
						'bid'=>$set['bid'],
					];
				}
			}
		}
		$data['pstimeArr'] = $pstimeArr;
	
		return $this->json(['status'=>1,'data'=>$data]);
	}
	public function address(){
		$type = input('param.type');
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['mid','=',mid];
		if(input('param.keyword')){
			$where[] = ['name|tel','like','%'.input('param.keyword').'%'];
		}
		if($type == 1){ //需要坐标
			$datalist = Db::name('express_address')->where($where)->where('latitude','>',0)->order('isdefault desc,id desc')->select()->toArray();
		}else{
			$datalist = Db::name('express_address')->where($where)->order('isdefault desc,id desc')->select()->toArray();
		}
		return $this->json(['status'=>1,'data'=>$datalist]);
	}
	public function addressadd(){
		$type = input('param.type');
		if(request()->isPost()){
			$post = input('post.');
			if($type == 1){
				if(!$post['latitude'] || !$post['longitude']){
					return $this->json(['status'=>0,'msg'=>'请选择坐标点']);
				}
			}
			$data = array();
			$data['aid'] = aid;
			$data['mid'] = mid;
			$data['name'] = $post['name'];
			$data['tel'] = $post['tel'];
			$data['address'] = $post['address'];
			$data['createtime'] = time();
            $data['company'] = $post['company'];
		    $data['mailtype'] = $post['mailtype'];
			if($type == 1 || $post['latitude']){
				$data['area'] = $post['area'];
				$data['latitude'] = $post['latitude'];
				$data['longitude'] = $post['longitude'];
				if($data['latitude'] && !$data['province']){
					//通过坐标获取省市区
                    $mapqq = new \app\commons\MapQQ();
                    $res = $mapqq->locationToAddress($data['latitude'],$data['longitude']);
					if($res && $res['status']==1){
						$data['province'] = $res['province'];
						$data['city'] = $res['city'];
						$data['district'] = $res['district'];
					}
				}
			}else{
				$area = explode(',',$post['area']);
				$data['province'] = $area[0];
				$data['city'] = $area[1];
				$data['district'] = $area[2];
				$data['area'] = implode('',$area);
			}
			if($post['addressid']){
				Db::name('express_address')->where('id',$post['addressid'])->update($data);
			}else{
				$default = Db::name('express_address')->where('aid',aid)->where('mid',mid)->where('isdefault',1)->find();
				if(!$default) $data['isdefault'] = 1;
				Db::name('express_address')->insert($data);
			}
			return $this->json(['status'=>1,'msg'=>'保存成功']);
		}
		if(input('param.id')){
			$addressid = input('param.id/d');
			$address = Db::name('express_address')->where('aid',aid)->where('mid',mid)->where('id',$addressid)->find();
		}else{
			$address = [];
		}
		return $this->json(['status'=>1,'data'=>$address]);
	}
	//设置默认地址
	public function setdefault(){
		$from = input('param.from');
		$addressid = input('param.addressid/d');
		$mailtype = input('param.mailtype/d');
		if($mailtype==2){
			Db::name('express_address')->where('aid',aid)->where('mid',mid)->where('mailtype',2)->update(['isdefault'=>0]);
			Db::name('express_address')->where('aid',aid)->where('mid',mid)->where('id',$addressid)->update(['isdefault'=>2,'mailtype'=>$mailtype]);
		}else{
			Db::name('express_address')->where('aid',aid)->where('mid',mid)->update(['isdefault'=>0]);
			Db::name('express_address')->where('aid',aid)->where('mid',mid)->where('id',$addressid)->update(['isdefault'=>1,'mailtype'=>$mailtype]);
		}
		return $this->json(['status'=>1,'msg'=>'设置成功']);
	}
	//删除地址
	public function del(){
		$addressid = input('param.addressid/d');
		$rs = Db::name('express_address')->where('aid',aid)->where('mid',mid)->where('id',$addressid)->delete();
		if($rs){
			return $this->json(['status'=>1,'msg'=>'删除成功']);
		}else{
			return $this->json(['status'=>0,'msg'=>'删除失败']);
		}
	}
	public function getYunli(){
		$addressid = input('param.addressid');
		$address2 = Db::name('express_address')->where(['id'=>$addressid])->find();
		if(!$address2) $address2 = [];	
		$set = Db::name('express_sysset')->where('aid',aid)->find();
		$param['secret_key'] = $set['secret_key']; 
		$param['secret_code'] = '7c5c49c5e51a4b75bd960f4997d2846a'; 
		$param['secret_sign'] = strtoupper(md5($set['secret_key'].$set['secret_secret']));
		$param['address'] = $address2['area'].$address2['address'] ;
	
		$res = curl_post('http://cloud.kuaidi100.com/api',$param);
		$res = json_decode($res,true);
		if($res['code']==200){
			//快递公司
			$expressdata =  express_data100();
			$data=[];
			$express = array_flip($expressdata);
			foreach($res['data'] as $r){
				if(in_array($r['com'],$expressdata)){
					
					$data[] = $express[$r['com']]; 
				}
			}
			return $this->json(['status'=>1,'data'=>$data]);
		}else{
			return $this->json(['status'=>0,'msg'=>$res['message']]);
		}
	
	}
	//提交寄件订单
	public function createOrder(){
		$type = input('param.type');
		if(request()->isPost()){
			$post = input('post.');
			//收件人地址
			$address = Db::name('express_address')->where('aid',aid)->where('mid',mid)->where('id',$post['addressid'])->find();
			//寄件人地址
			$address2 = Db::name('express_address')->where('aid',aid)->where('mid',mid)->where('id',$post['address2id'])->find();
			$comarr = express_data100();
			$time1 = explode(' ',$post['sm_time']);
			$time2 = explode('~',$time1[1]);
	
			$data = array();
			$data['aid'] = aid;
			$ordernum = \app\commons\Common::generateOrderNo(aid);
			$data['mid'] = mid;
			$data['ordernum'] = $ordernum;
			$data['cargo'] = $post['cargo'];
			$data['company'] = $post['company'];
			$data['sm_time'] = $post['sm_time'];
			$data['createtime'] = time();
			$data['recManName'] = $address2['name'];
			$data['recManMobile'] = $address2['tel'];
			$data['recManPrintAddr'] = $address2['area']. $address2['address'];
			$data['recManPrintPro'] = $address2['province'];
			$data['recManPrintCity'] = $address2['city'];
			$data['sendManName'] = $address['name'];
			$data['sendManMobile'] = $address['tel'];
			$data['sendManPrintAddr'] = $address['area'].$address['address'];
			$data['sendManPrintPro'] = $address['province'];
			$data['sendManPrintCity'] = $address['city'];
			$data['weight'] = $post['weight'];
			$data['remark'] = $post['remark'];
			$data['platform'] = platform;
			$data['salt'] = random(4);
			$id=Db::name('express_order')->insertGetId($data);


			$yourtime = strtotime($time1[0]);
			$todayEnd = strtotime(date('Y-m-d 23:59:59',time()))+1;//当天的结束时间
			$todayStart = strtotime(date('Y-m-d 00:00:00',time()));//当天的开始时间
			$dayType = '';
			$dayTypes = [0=>'今天',1=>'明天',2=>'后天'];
			if($yourtime>= $todayStart && $yourtime<$todayEnd){
				$days = 0;
			}else{
				$days = ceil(($yourtime-$todayEnd)/86400);
			}
			if(isset($dayTypes[$days])){
				$dayType = $dayTypes[$days];
			}
			//同步到快递100
			$set = Db::name('express_sysset')->where('aid',aid)->find();
			$param['secret_key'] = $set['secret_key']; 
			$param['secret_code'] = $set['secret_code']; 
			$param['secret_sign'] = strtoupper(md5($set['secret_key'].$set['secret_secret']));
			$param['com'] = $comarr[$post['company']] ;
			$param['recManName'] = $data['recManName'];
			$param['recManMobile'] =$data['recManMobile'];
			$param['recManPrintAddr'] = $data['recManPrintAddr'];
			$param['sendManName'] = $data['sendManName'];
			$param['sendManMobile'] = $data['sendManMobile'];
			$param['sendManPrintAddr'] = $data['sendManPrintAddr'];
			$param['cargo'] = $data['cargo'];
			$param['dayType'] =$dayType;
			$param['pickupStartTime'] = $time2[0]; 
			$param['pickupEndTime'] = $time2[1]; 
			$param['callBackUrl'] = PRE_URL.'/notify.php';

			$res = curl_post('http://cloud.kuaidi100.com/api',$param);
			$res = json_decode($res,true);

			/*$key = $set['key'];           //客户授权key
			$secret = $set['secret'];     //客户授权secret
			$param = array (
				'kuaidicom' => $comarr[$post['company']],          
				'recManName' => $data['recManName'],           
				'recManMobile' =>$data['recManMobile'],
				'recManPrintAddr' =>$data['recManPrintAddr'],
				'sendManName' =>$data['sendManName'],
				'sendManMobile' =>$data['sendManMobile'],
				'sendManPrintAddr' =>$data['sendManPrintAddr'],
				'callBackUrl' => PRE_URL.'/notify.php',
				'cargo' =>$data['cargo'],
				'remark' =>$data['remark'],
				'salt' =>$data['salt'],
				'dayType' =>$dayType,
				'pickupStartTime' =>$time2[0],
				'pickupEndTime' =>$time2[1],
			);
			$res = $this->wuliu_post($param,$key,$secret,'bOrder');
			*/
			if($res['code']=='200'){
				Db::name('express_order')->where(['id'=>$id])->update(['taskId'=>$res['data']['taskId'],'orderId'=>$res['data']['orderId']]);
				return $this->json(['status'=>1,'msg'=>'寄件成功']);
			}else{
				return $this->json(['status'=>0,'msg'=>$res['message']]);
			}
			die;
		}
		if(input('param.id')){
			$addressid = input('param.id/d');
			$address = Db::name('express_address')->where('aid',aid)->where('mid',mid)->where('id',$addressid)->find();
		}else{
			$address = [];
		}
		return $this->json(['status'=>1,'data'=>$address]);
	}
	public function getPrice(){
		$post = input('post.');
		if(request()->isPost()){
			$comarr = express_data100();
			//var_dump($comarr[$post['company']]);
			//收件人地址
			$address = Db::name('express_address')->where('aid',aid)->where('mid',mid)->where('id',$post['addressid'])->find();
			//寄件人地址
			$address2 = Db::name('express_address')->where('aid',aid)->where('mid',mid)->where('id',$post['address2id'])->find();
			$set = Db::name('express_sysset')->where('aid',aid)->find();
			$data['secret_key'] = $set['secret_key']; 
			$data['secret_code'] = $set['secret_codep']; 
			$data['secret_sign'] = strtoupper(md5($set['secret_key'].$set['secret_secret']));
			$data['companyName'] = $comarr[$post['company']] ;
			$data['sendAddr'] = $address['area']. $address['address']; 
			$data['receiveAddr	'] = $address2['area'].$address2['address'];
			$data['weight	'] = $post['weight'];
			$res = curl_post('http://cloud.kuaidi100.com/api',$data);
			$res = json_decode($res,true);
			if($res['status']==200){
				return $this->json(['status'=>1,'data'=>$res['data']]);
			}else{
				return $this->json(['status'=>0,'msg'=>$res['message']]);
			}
		
		}
	}
	public function getLog(){
		$data = [];
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['mid','=',mid];

		//$where[] = ['bid','=',bid];
		$pernum = 10;
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$post = input('post.');	
		if($post['type']==1){
			$data['datalist'] = Db::name('express_cxlog')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
		}else{
			$starr = ['0'=>'下单成功','1'=>'已接单','2'=>'收件中','9'=>'用户已取消','10'=>'已取件','11'=>'揽货失败','12'=>'已退回','13'=>'已签收','14'=>'异常签收','99'=>'订单已取消','101'=>'在途中'];
			$data['datalist'] = Db::name('express_order')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
			foreach($data['datalist'] as $k=>$d){
				$status = $starr[$d['status']];
				$data['datalist'][$k]['status'] = $status; 
			}
		}
		$data['expressdata'] =  array_keys(express_data100());
		return $this->json(['status'=>1,'data'=>$data]);
	}
	public function kddetail(){
		$data = [];
		if(request()->isPost()){
			$post = input('post.');	
			$id = $post['id'];
			$data=  Db::name('express_order')->where(['id'=>$id])->find();
			$starr = ['0'=>'下单成功','1'=>'已接单','2'=>'收件中','9'=>'用户已取消','10'=>'已取件','11'=>'揽货失败','12'=>'已退回','13'=>'已签收','14'=>'异常签收','99'=>'订单已取消','101'=>'在途中'];
			$data['sta'] =  $starr[$data['status']];
			$datalist = [];
			if($data['kuaidinum']){
				$set = Db::name('express_sysset')->where('aid',aid)->find();
				$key = $set['key'];
				$customer = $set['customer'];
				$param = array ('com' => $data['company'],'num' => $data['kuaidinum']);
				$result = kuaidi100_getwuliu($key,$customer,$param);
				$datalist = json_decode($result);
			}
		}	
		return $this->json(['status'=>1,'data'=>$data,'datalist'=>$datalist]);
	}
	public function logistics(){
		$type = input('param.type');
		if(request()->isPost()){
			$post = input('post.');	
			$comarr = express_data100();
			$set = Db::name('express_sysset')->where('aid',aid)->find();
			$key = $set['key'];
			$customer = $set['customer'];
			$comname = $post['com'];
			$com = $comarr[$post['com']];
			if(!$com || $com=='undefined'){
				$data1 = array();
				$data1['num'] = $post['num'];
				$data1['key'] = $key;
				$url1 = 'http://www.kuaidi100.com/autonumber/auto?num='.$post['num'].'&key='.$key.'';
				$res = curl_form_post($url1,$data1);	
				$res = json_decode($res,true);
				if($res['returnCode']){
					$this->json(['status'=>0,'msg'=>$res['message']]);
				}
				$com = $res[0]['comCode'];
				$comname =  $res[0]['name'];
			}
			$param = array ('com' => $com,'num' => $post['num']);
			$result = kuaidi100_getwuliu($key,$customer,$param);
			$data = json_decode($result);
			if($data->status=='200'){
				$expresslog = Db::name('express_cxlog')->where(['aid'=>aid,'mid'=>mid,'num'=>$post['num']])->find();
				if(!$expresslog){
					$data2 = [];
					$data2['aid'] = aid;
					$data2['mid'] = mid;
					$data2['company'] = $comname;
					$data2['text'] = $data->data[0]->context;
					$data2['createtime'] = time();
					$data2['num'] = $post['num'];
					$data2['state'] = $data->state;
					Db::name('express_cxlog')->insert($data2);
				}
				return $this->json(['status'=>1,'datalist'=>$data,'com'=>$comname]);
			}else{
				return $this->json(['status'=>-4,'msg'=>$data->message,'url'=>'/activity/express/index']);
			}
		}	
		
	}
	public function cancle(){
		$data = [];
		if(request()->isPost()){
			$post = input('post.');	
			$id = $post['id'];
			$data=  Db::name('express_order')->where(['id'=>$id])->find();
			$set=  Db::name('express_sysset')->where(['aid'=>aid])->find();

			$key = $set['key'];           //客户授权key
			$secret = $set['secret'];     //客户授权secret
	
			$param = array (
				'taskId' => $data['taskId'],            //任务ID
				'orderId' => $data['orderId'],           //订单ID
				'cancelMsg' =>$post['remark'],//取消原因
			);
	
			$result = $this->wuliu_post($param,$key,$secret,'cancel');
			$data = json_decode($result,true);
			if($data['returnCode']=='200'){
				 Db::name('express_order')->where(['id'=>$id])->update(['remark'=>$post['remark']]);
				 return $this->json(['status'=>1,'msg'=>'取消成功']);
			}else{
				 return $this->json(['status'=>0,'msg'=>$data['message']]);
			}
			
		}	
		
	}


	public function wuliu_post($param,$key,$secret,$method){
		$param_str = json_encode($param, JSON_UNESCAPED_UNICODE);
		list($msec, $sec) = explode(' ', microtime());
		$t = (float)sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000);    //当前时间戳
		$sign = strtoupper(md5($param_str.$t.$key.$secret));
		//请求参数
		$post_data = array();
		$post_data["method"] = $method;
		$post_data["key"] = $key;
		$post_data["t"] = $t;
		$post_data["sign"] = $sign;
		$post_data["param"] = $param_str;

		$url = 'https://poll.kuaidi100.com/order/borderapi.do';    
		//发送post请求
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		return  curl_exec($ch);
	}

	//识别地址信息
	public function shibie(){
		$addressxx = input('param.addressxx');
		$postdata = [];
		$postdata['text'] = $addressxx;
	//	$rs = request_post('https://www.diandashop.com/index/api/address',$postdata);
		$rs = json_decode($rs,true);
		return $this->json($rs);
	}

}