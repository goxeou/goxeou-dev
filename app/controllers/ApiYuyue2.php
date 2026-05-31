<?php


namespace app\controllers;
use think\facade\Db;
class ApiYuyue2 extends ApiCommon{
	//师傅分类
	public function peocategory(){
		$bid = input('param.bid');
		if(!$bid) $bid = 0;
		$clist = Db::name('yuyue_category')->where('aid',aid)->where('bid',$bid)->where('pid',0)->where('status',1)->order('sort desc,id')->select()->toArray();
		foreach($clist as $k=>$v){
			$rs = Db::name('yuyue_category')->where('aid',aid)->where('bid',$bid)->where('pid',$v['appid'])->where('status',1)->order('sort desc,id')->select()->toArray();
			if(!$rs) $rs = [];
			$clist[$k]['child'] = $rs;
		}
		return $this->json(['status'=>1,'data'=>$clist]);
	}
	public function selectpeople(){
		$bid = input('param.bid');
		if(!$bid) $bid = 0;
		$type = input('param.type');
		$pernum = 10;
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		if($type=='list'){
			$st = input('param.st');
			$lon = input('param.longitude')?input('param.longitude'):'113.352364';
			$lat = input('param.latitude')?input('param.latitude'):'23.008877';
			if($st==='all'){
				//定制获取第三方
				$config = include(ROOT_PATH.'config.php');
				$appId=$config['hmyyuyue']['appId'];
				$appSecret=$config['hmyyuyue']['appSecret'];
				$url = 'http://shifu.api.kkgj123.cn/api/1/master/listAll';
				$headrs = array('appid:'.$appId,'appSecret:'.$appSecret);
				$param = [];
				$param['pageNum'] = $pagenum;
				$param['pageSize'] = $pernum;
				$param['lon'] = $lon;
				$param['lat'] = $lat;
				$res = curl_get($url,$param,$headrs);

				$res = json_decode($res,true);
		
				if($res['code']==200){		
					foreach($res['rows'] as &$r){
						$price= explode('.',$r['priceDesc']);
						$r['price'] = $price[0];
					}
					foreach($res['rows'] as &$r){
						$distanceDesc = getdistance($r['lon'],$r['lat'],$lon,$lat,2);
						$r['distanceDesc'] = $distanceDesc.'Km';
					}
					return $this->json(['status'=>1,'data'=>$res['rows']]);
				}else{
					return $this->json(['status'=>0,'msg'=>$res]);
				}
			}else{
				//定制获取第三方
				$config = include(ROOT_PATH.'config.php');
				$appId=$config['hmyyuyue']['appId'];
				$appSecret=$config['hmyyuyue']['appSecret'];
				$url = 'http://shifu.api.kkgj123.cn/api/1/master/list';
				$headrs = array('appid:'.$appId,'appSecret:'.$appSecret);
				$param = [];
				$param['pageNum'] = $pagenum;
				$param['pageSize'] = $pernum;
				$param['firstCategoryId'] = input('param.cid');
				$param['secondCategoryId'] = input('param.subCid');
				$param['lon'] = input('param.longitude')?input('param.longitude'):'118.356415';
				$param['lat'] = input('param.latitude')?input('param.latitude'):'35.112946';
				$res = curl_get($url,$param,$headrs);
				$res = json_decode($res,true);
				if($res['code']==200){		
					foreach($res['rows'] as &$r){
						$price= explode('.',$r['priceDesc']);
						$r['price'] = $price[0];
					}
			
					return $this->json(['status'=>1,'data'=>$res['rows']]);
				}else{
					return $this->json(['status'=>0,'msg'=>$res]);
				}
			}
		}else{
			$pro =explode(',',input('param.prodata'));
			$yydate = input('param.yydate');
			$product = Db::name('yuyue_product')->field('fwpeoid')->where('id',$pro[0])->find();
			$peoarr = explode(',',$product['fwpeoid']);
			$datalist = Db::name('yuyue_worker')->where('aid',aid)->where('id','in',$peoarr)->order('sort desc,id')->select()->toArray();
			//查看该时间是否已经预约出去
			foreach($datalist as &$d){
				$type = Db::name('yuyue_worker_category')->where(['id'=>$d['cid']])->find();
				$d['typename'] = $type['name'];
				$order = Db::name('yuyue_order')->where('aid',aid)->where('worker_id',$d['id'])->where('status','in','1,2')->where('yy_time',$yydate)->find();
				$d['yystatus']=1;
				if($order){
					$d['yystatus']=-1;
				}
			}
		}
		if(!$datalist) $datalist = [];
		return $this->json(['status'=>1,'data'=>$datalist]);
	}
	//师傅详情
	public function peodetail(){
		$id = input('param.id/d');
		$sysset = db('yuyue_set')->where(['aid'=>aid])->find();
		$res = $this->getMaster($id);
		$lon = input('param.longitude')?input('param.longitude'):'118.356415';
		$lat = input('param.latitude')?input('param.latitude'):'35.112946';
	
		
		if($res['code']==200){		
			//计算距离
			$juli = getdistance($res['data']['lon'],$res['data']['lat'],$lon,$lat,2);
			$res['data']['juli'] = $juli;
			$res['data']['lastOnlineTime'] = date('Y-m-d H:i',strtotime($res['data']['lastOnlineTime']));
			return $this->json(['status'=>1,'data'=>$res['data'],'set'=>$sysset]);
		}else{
			return $this->json(['status'=>0,'msg'=>$res['msg']]);
		}
	}
	

	//预约详情
	public function product(){
		$skillid = input('param.skillid/d');
		$masterid = input('param.masterid/d');
		$sysset = Db::name('yuyue_set')->where('aid',aid)->find();
		$times = [];
		$j = $sysset['wanhour'] - $sysset['zaohour'];
		$res = $this->getSkill($masterid,$skillid);
		$product = [];
		if($res['code']==200){
			$product['name'] = $res['data']['firstCategoryName'].' '.$res['data']['secondCategoryName'];
			$product['price'] = $res['data']['price'];
			$product['unit'] = $res['data']['unit'];
			$product['firstCategoryId'] = $res['data']['firstCategoryId'];
			$product['secondCategoryId'] = $res['data']['secondCategoryId'];
			$product['firstCategoryId'] = $res['data']['firstCategoryId'];
			$product['skillId'] = $res['data']['skillId'];
			$product['masterId'] = $masterid;
			$product['masterName'] = $masterid;
		}else{
			return $this->json(['status'=>0,'msg'=>'技能获取失败']);
		}
	
		for($i=strtotime($sysset['zaohour'].':00') ;$i<=strtotime($sysset['wanhour'].':00') ; $i=$i+60*$sysset['timejg']){
			$times[]=date("H:i",$i);
		}
		if($sysset['rqtype']==1){
			$datelist = $this->GetWeeks($sysset['yyzhouqi']);
		}else{
			$datelist = [];
			$yybeigntime = strtotime($sysset['yybegintime']);
			$yyendtime = strtotime($sysset['yyendtime']);
			$days= ($yyendtime-$yybeigntime)/86400;
			for ($i=0;$i<=$days;$i++){
				$month=date('m',$yybeigntime+86400*$i).'月';
				$day=date('d',$yybeigntime+86400*$i);
				$week=date('w',$yybeigntime+86400*$i);
				if ($week=='1'){
					$week='周一';
					$key=1;
				}elseif ($week=='2'){
					$week='周二';
					$key=2;
				}elseif ($week=='3'){
					$week='周三';
					$key=3;
				}elseif ($week=='4'){
					$week='周四';
					$key=4;
				}elseif ($week=='5'){
					$week='周五';
					$key =5;
				}elseif ($week=='6'){
					$week='周六';
					$key=6;
				}elseif ($week=='0'){
					$week='周日';
					$key=0;
				}
				$datelist[$i]['key'] = $key;
				$datelist[$i]['weeks'] = $week;
				$datelist[$i]['date'] = $month.$day;

			}
		}

		$rdata = [];
		$rdata['status'] = 1;
		$rdata['title'] = $product['name'];
		$rdata['sysset'] = $sysset;
		$rdata['datelist'] = $datelist;
		$rdata['daydate'] = $daydate;
		$rdata['product'] = $product;
		return $this->json($rdata);
	}

	//订单提交页
	public function buy(){
		$this->checklogin();
		$multi_promotion = 0;
        $yydate = input('param.yydate');
		$prodata = input('param.prodata');	
		$prodata = explode(',',$prodata);
		$adminset = Db::name('admin_set')->where('aid',aid)->find();
		$userinfo = [];
		$userinfo['realname'] = $this->member['realname'];
		$userinfo['tel'] = $this->member['tel'];
		$address = Db::name('member_address')->where('aid',aid)->where('mid',mid)->order('isdefault desc,id desc')->find();

		if(!$address) $address = [];
		$needLocation = 0;

		$res = $this->getSkill($prodata[1],$prodata[0]);
		if($res['code']!='200'){
			return $this->json(['status'=>0,'msg'=>'技能获取失败']);
		}
		$master = $this->getMaster($prodata[1]);
		if($master['code']=='200'){
			$master = $master['data'];
		}else{
			return $this->json(['status'=>0,'msg'=>'师傅信息获取失败']);
		}

		$product = [];
		$product['name'] = $res['data']['firstCategoryName'].' '.$res['data']['secondCategoryName'];
		$product['price'] = $res['data']['price'];
		$product['unit'] = $res['data']['unit'];
		$product['firstCategoryId'] = $res['data']['firstCategoryId'];
		$product['secondCategoryId'] = $res['data']['secondCategoryId'];
		$product['firstCategoryId'] = $res['data']['firstCategoryId'];
		$product['skillId'] = $prodata[0];
		$product['masterId'] = $prodata[1];
		$product['num'] = $prodata[2];
		$product['serviceType'] = $res['data']['serviceType'];
		$config = include(ROOT_PATH.'config.php');
		$appId=$config['hmyyuyue']['appId'];
		$appSecret=$config['hmyyuyue']['appSecret'];


		//路程费计算规则
		$headrs = array('content-type: application/json;charset=UTF-8','appid:'.$appId,'appSecret:'.$appSecret);
		$url = 'https://shifu.api.kkgj123.cn/api/1/distancefee/set';
		$res2 = curl_get($url,$param=[],$headrs);
		//var_dump($res2);
		$res2 = json_decode($res2,true);
		$res2 = $res2['data'];

		
		$freight_price = 0;
		$juli = 0;
		if($res2['isOpen']==1){
            ///计算配送费 骑行距离
            $mapqq = new \app\commons\MapQQ();
            $bicycl = $mapqq->getDirectionDistance($address['longitude'],$address['latitude'],$master['lon'],$master['lat'],2);
            if($bicycl && $bicycl['status']==1){
                $juli = $bicycl['distance'];
            }else{
                $juli = getdistance($address['longitude'],$address['latitude'],$master['lon'],$master['lat'],2);
            }

			$peisong_juli = 1;
			$freight_price = floatval($res2['perKilometerFee']);
		
			if($juli - floatval($peisong_juli1) > 0 && floatval($res2['plusKilometer']) > 0){
				$freight_price += ceil(($juli - floatval($peisong_juli1))/floatval($res2['plusKilometer'])) * floatval($res2['plusFee']);
			}
			if($freight_price>$res2['upToFee'])$freight_price = $res2['upToFee'];
			
		}
		$product['freight_price'] = $freight_price;
		$rdata = [];
		$rdata['status'] = 1;
		$rdata['address'] = $address;
		$rdata['linkman'] = $address ? $address['name'] : strval($userinfo['realname']);
		$rdata['tel'] = $address ? $address['tel'] : strval($userinfo['tel']);
		if(!$rdata['linkman']){
			$lastorder = Db::name('yuyue_order')->where('aid',aid)->where('mid',mid)->where('linkman','<>','')->find();
			if($lastorder){
				$rdata['linkman'] = $lastorder['linkman'];
				$rdata['tel'] = $lastorder['tel'];
			}
		}
		$master['juli'] = $juli;
		$sysset = Db::name('yuyue_set')->where('aid',aid)->find();
		$product['pic'] = $sysset['pic'];
		$rdata['totalprice'] = $product['price']*$prodata[2]+$freight_price; 
		$rdata['userinfo'] = $userinfo;
		$rdata['allbuydata'] = $product;
		$rdata['yydate'] = $yydate;
		$rdata['master'] =  $master;
		return $this->json($rdata);
	}
	public function createOrder(){
		$this->checklogin();

		$sysset = Db::name('admin_set')->where('aid',aid)->find();
		$post = input('post.');
		
		//收货地址
		if($post['fwtype']==1){
			$address = ['id'=>0,'name'=>$post['linkman'],'tel'=>$post['tel'],'area'=>'','address'=>''];
		}else{
			$address = Db::name('member_address')->where('id',$post['addressid'])->where('aid',aid)->where('mid',mid)->find();
			if(!$address) return $this->json(['status'=>0,'msg'=>'所选地址不存在']);
		}

		$yydate = explode('-',$post['yydate']);
		//开始时间
		$begindate = date('Y年').$yydate[0];
		$begindate = preg_replace(['/年|月/','/日/'],['-',''],$begindate);
		$begintime = strtotime($begindate);

		$config = include(ROOT_PATH.'config.php');
		$appId=$config['hmyyuyue']['appId'];
		$appSecret=$config['hmyyuyue']['appSecret'];

		$prodata = input('param.prodata');	
		$prodata = explode(',',$prodata);

		if($prodata[2]<=0){
			return $this->json(['status'=>0,'msg'=>'数量不能小于0']);
		}

		$headrs = array('content-type: application/json;charset=UTF-8','appid:'.$appId,'appSecret:'.$appSecret);
		$url = 'https://shifu.api.kkgj123.cn/api/1/skill/detail';
		$data = [];

		$data['masterId'] = $prodata[1];
		$data['skillId'] = $prodata[0];
		$data = json_encode($data,JSON_UNESCAPED_UNICODE);
		$res = curl_post($url,$data,'',$headrs);	
		$res = json_decode($res,true);
		$product = [];
		if($res['code']=='200'){
			$product['name'] = $res['data']['firstCategoryName'].' '.$res['data']['secondCategoryName'];
			$product['price'] = $res['data']['price'];
			$product['unit'] = $res['data']['unit'];
			$product['firstCategoryId'] = $res['data']['firstCategoryId'];
			$product['secondCategoryId'] = $res['data']['secondCategoryId'];
			$product['skillId'] = $prodata[0];
			$product['masterId'] = $prodata[1];;
			$product['num'] = $prodata[2];
		}else{
			return $this->json(['status'=>0,'msg'=>'技能获取失败']);
		}
		//获取师傅详情
		$res3 = $this->getMaster($prodata[1]);
		$master = $res3['data'];
		//路程费计算规则
		$url = 'https://shifu.api.kkgj123.cn/api/1/distancefee/set';
		$res2 = curl_get($url,$param=[],$headrs);
		$res2 = json_decode($res2,true);
		$res2 = $res2['data'];
		if($res2['isOpen']==1){
			///	计算配送费 骑行距离
            $mapqq = new \app\commons\MapQQ();
            $bicycl = $mapqq->getDirectionDistance($address['longitude'],$address['latitude'],$master['lon'],$master['lat'],2);
            if($bicycl && $bicycl['status']==1){
                $juli = $bicycl['distance'];
            }else{
                $juli = getdistance($address['longitude'],$address['latitude'],$master['lon'],$master['lat'],2);
            }
			//5km 内多少钱
			$peisong_juli = 1;
			$freight_price = floatval($res2['perKilometerFee']);
			if($juli - floatval($peisong_juli1) > 0 && floatval($res2['plusKilometer']) > 0){
				$freight_price += ceil(($juli - floatval($peisong_juli1))/floatval($res2['plusKilometer'])) * floatval($res2['plusFee']);
			}
			if($freight_price>$res2['upToFee'])$freight_price = $res2['upToFee'];
		}

		$product['freight_price'] = $freight_price;
		$ordernum = date('ymdHis').rand(100000,999999);
		$totalprice = $product['price'] * $product['num']+$freight_price; 

		if(!$address['longitude']){
			return $this->json(['status'=>0,'msg'=>'经纬度不能为空，请重新选择地址']);
		}
		
		$orderdata = [];
		$orderdata['aid'] = aid;
		$orderdata['mid'] = mid;
		$orderdata['bid'] =0;
		if(count($buydata) > 1){
			$orderdata['ordernum'] = $ordernum.'_'.$i;
		}else{
			$orderdata['ordernum'] = $ordernum;
		}
		if($res3['code']==200){
			$orderdata['masterName'] = $master['name'];     //师傅姓名
			$orderdata['errandDistance'] = $juli?$juli:0;  //路程距离
			$orderdata['propic'] = $master['avatar'];    //师傅姓名
		}
		$orderdata['title'] = $product['name'];
		$orderdata['linkman'] = $address['name'];
		$orderdata['tel'] = $address['tel'];
		$orderdata['area'] = $address['area'];
		$orderdata['address'] = $address['address'];
		$orderdata['longitude'] = $address['longitude'];
		$orderdata['latitude'] = $address['latitude'];
		$orderdata['area2'] = $address['province'].','.$address['city'].','.$address['district'];
		$orderdata['totalprice'] = $totalprice;
		$orderdata['product_price'] = $product['price'];
		$orderdata['yy_time'] = $post['yydate']; //预约时间
		$orderdata['createtime'] = time();
		$orderdata['platform'] = platform;
		$orderdata['hexiao_code'] = random(16);
		$orderdata['message'] = $post['remark'];
		$orderdata['proname'] = $product['name'];
		$orderdata['ggname'] = '';
		$orderdata['num'] = $prodata[2];
		$orderdata['proid'] = 0;
		$orderdata['paidan_type'] = 3;
		$orderdata['ggid'] = 0; 
		$orderdata['worker_id'] = $product['masterId'];
		$orderdata['fwtype'] = 1; //服务方式
		$orderdata['begintime'] = $begintime; 
		$orderdata['hexiao_qr'] = createqrcode(m_url('admin/hexiao/hexiao?type=yuyue&co='.$orderdata['hexiao_code']));
		$orderdata['paidan_money'] = $freight_price;
	
		$url2 = 'https://shifu.api.kkgj123.cn/api/1/commission/rule';
		$res2 = curl_get($url2,$param=[],$headrs);
		$res2 = json_decode($res2,true);
		//指定师傅
		if($res2['code']==200){
			$res2 = $res2['data'];
			if($res2['appointMasterCommissionRule']!=1){
				if($res2['appointMasterCommission']>0){
					$orderdata['commission'] = ($totalprice-$freight_price)*$res2['appointMasterCommission']/100;
				}else{
					$orderdata['commission'] = $totalprice;
				}
			}else{
				$orderdata['commission'] = $totalprice;
			}
		}

		$orderdata['platformIncome'] = '';  //平台收入
		$orderdata['firstCategory'] = $product['firstCategoryId'];
		$orderdata['secondCategory'] = $product['secondCategoryId'];
		$orderdata['unit'] = $product['unit'];
		$orderid = Db::name('yuyue_order')->insertGetId($orderdata);
		$payorderid = \app\models\Payorder::createorder(aid,$orderdata['bid'],$orderdata['mid'],'yuyue',$orderid,$orderdata['ordernum'],$orderdata['title'],$orderdata['totalprice']);
					
		return $this->json(['status'=>1,'payorderid'=>$payorderid,'msg'=>'提交成功']);
	}

	public function getSkill($masterid,$skillid){
		$config = include(ROOT_PATH.'config.php');
		$appId=$config['hmyyuyue']['appId'];
		$appSecret=$config['hmyyuyue']['appSecret'];
		$headrs = array('content-type: application/json;charset=UTF-8','appid:'.$appId,'appSecret:'.$appSecret);
		$url = 'https://shifu.api.kkgj123.cn/api/1/skill/detail';
		$data = [];
		$data['masterId'] = $masterid;
		$data['skillId'] = $skillid;
		$data = json_encode($data,JSON_UNESCAPED_UNICODE);
		$res = curl_post($url,$data,'',$headrs);	
		$res = json_decode($res,true);
		return $res;
	}
	
	public function getMaster($id){
		//定制获取第三方
		$url = 'http://shifu.api.kkgj123.cn/api/1/master/detail';
		$config = include(ROOT_PATH.'config.php');
		$appId=$config['hmyyuyue']['appId'];
		$appSecret=$config['hmyyuyue']['appSecret'];
		$headrs = array('appid:'.$appId,'appSecret:'.$appSecret);
		$param = [];
		$param['id'] = $id;
		$param['lon'] = input('param.longitude')?input('param.longitude'):'118.356415';
		$param['lat'] = input('param.latitude')?input('param.latitude'):'35.112946';
		$res = curl_get($url,$param,$headrs);
		$res = json_decode($res,true);
		return $res;
	}

	 //获取接下来一周的日期
    function GetWeeks($yyzhouqi) {
		//查看今天周几
		$zqarr = explode(',',$yyzhouqi);
        $i=0;
        $weeks=[];
        for ($i;$i<7;$i++){
            $month=date('m',time()+86400*$i).'月';
            $day=date('d',time()+86400*$i);
            $week=date('w',time()+86400*$i);
            if ($week=='1'){
                $week='周一';
				$key=1;
            }elseif ($week=='2'){
                $week='周二';
				$key=2;
            }elseif ($week=='3'){
                $week='周三';
				$key=3;
            }elseif ($week=='4'){
                $week='周四';
				$key=4;
            }elseif ($week=='5'){
                $week='周五';
				$key =5;
            }elseif ($week=='6'){
                $week='周六';
				$key=6;
            }elseif ($week=='0'){
                $week='周日';
				$key=0;
            }
			$weeks[$i]['key'] = $key;
			$weeks[$i]['weeks'] = $week;
			$weeks[$i]['date'] = $month.$day;
            //array_push($weeks,$month.$day."(".$week."）");
			$newweek = [];
			foreach($weeks as $k=>$w){
				if(!in_array($w['key'],$zqarr)){
					unset($weeks[$k]);
				}
			}
        }
	    $weeks=array_values($weeks);
        return $weeks;
    }
	public function isgettime(){
		$date = input('param.date/t');
		//获取设置
		$sets = Db::name('yuyue_set')->where('aid',aid)->find();
		//var_dump($sets['datetype']);
		if($sets['datetype']==1){
			$timearr = [];
			$j=0;
			$nowdate =strtotime(date('H:i',time()))+$sets['pdprehour']*60*60;		
			for($i=strtotime($sets['zaohour'].':00') ;$i<=strtotime($sets['wanhour'].':00') ; $i=$i+60*$sets['timejg']){
				$j++;
				$time =strtotime(preg_replace(['/年|月/','/日/'],['-',''],date('Y年').$date.' '.date("H:i",$i)));
					if( $time<$nowdate){
							$timearr[$j]['status'] = 0;
					}else{
							$timearr[$j]['status'] = 1;
					}
					$timearr[$j]['time'] = date("H:i",$i);
					$timearr[$j]['timeint'] = str_replace(':','',date("H:i",$i));
			 }
		}
		if($sets['datetype']==2){
			$timearr = [];
			$timearrs = explode(',',$sets['timepoint']); 
			$nowdate =strtotime(date('H:i',time()))+$sets['pdprehour']*60*60;	
			foreach($timearrs as $k=>$t){
				$time =strtotime(preg_replace(['/年|月/','/日/'],['-',''],date('Y年').$date.' '.$t));
				if($time<$nowdate){
						$timearr[$k]['status'] = 0;
				}else{
						$timearr[$k]['status'] = 1;
				}
				$timearr[$k]['time'] = $t;
				$timearr[$k]['timeint'] = str_replace(':','',$t);
			}
		}
		return $this->json(['status'=>1,'data'=>$timearr]);
	}
}