<?php


namespace app\models;
use think\facade\Db;
use think\facade\Log;

class YuyueWorkerOrder
{
	//创建订单
	static function create($order,$worker_id=0,$fwpeoid=''){
		$aid = $order['aid'];
		$hasorder = Db::name('yuyue_worker_order')->where('orderid',$order['id'])->where('status','<>',10)->find();
		//if($hasorder) return ['status'=>0,'msg'=>'已存在服务单'];
		if($order['bid']>0){
			$business = Db::name('business')->field('name,address,tel,logo,longitude,latitude')->where('id',$order['bid'])->find();
		}else{
			$business = Db::name('admin_set')->field('name,address,tel,logo,longitude,latitude')->where('aid',$aid)->find();
		}
		//预约设置
		$yyset = Db::name('yuyue_set')->where('aid',$aid)->where('bid',$order['bid'])->find();

		//商家距用户的距离
		$juli = getdistance($order['longitude'],$order['latitude'],$business['longitude'],$business['latitude'],1);
		
		if(!$hasorder){
			//订单信息
			$orderinfo = [];
			$formdata = \app\models\Freight::getformdata($order['id'],'yuyue_order');
			$orderinfo['formdata']  =  $formdata?$formdata:[];
			$orderinfo['id'] = $order['id'];
			$orderinfo['ordernum'] = $order['ordernum'];
			$orderinfo['createtime'] = $order['createtime'];
			$orderinfo['paytime'] = $order['paytime'];
			$orderinfo['paytype'] = $order['paytype'];
			$orderinfo['product_price'] = $order['product_price'];
			$orderinfo['freight_price'] = $order['paidan_money'];
			$orderinfo['totalprice'] = $order['totalprice'];
			$orderinfo['message'] = $order['message'];
			$orderinfo['linkman'] = $order['linkman'];
			$orderinfo['tel'] = $order['tel'];
			$orderinfo['area'] = $order['area'];
			$orderinfo['address'] = $order['address'];
			$orderinfo['longitude'] = $order['longitude'];
			$orderinfo['latitude'] = $order['latitude'];
			$orderinfo['yydate'] = $order['yy_time'];
			//商品信息
			$proinfo = [];
			$proinfo['name'] = $order['proname'];
			$proinfo['ggname'] = $order['ggname'];
			$proinfo['pic'] = $order['propic'];
			$proinfo['sell_price'] = $order['product_price'];
			$proinfo['num'] = $order['num'];
			$prolist = [$proinfo];
			$orderinfo['procount'] = $order['num'];

			$psorderdata = [];
			$psorderdata['aid'] = $aid;
			$psorderdata['bid'] = $order['bid'];
			$psorderdata['mid'] = $order['mid'];
			$psorderdata['worker_id'] = $worker_id;
			$psorderdata['orderid'] = $order['id'];
			$psorderdata['ordernum'] = $order['ordernum'];
			$psorderdata['createtime'] = time();
			$psorderdata['longitude'] = $business['longitude'];
			$psorderdata['latitude'] = $business['latitude'];
			$psorderdata['longitude2'] = $order['longitude'];
			$psorderdata['latitude2'] = $order['latitude'];
			if($worker_id != 0){ //指服务人员配送 
				$psorderdata['status'] = 1;
				$psorderdata['starttime'] = time();
			}
			$psorderdata['juli'] = $juli;
			$psorderdata['orderinfo'] = jsonEncode($orderinfo);
			$psorderdata['prolist'] = jsonEncode($prolist);
			$psorderdata['binfo'] = jsonEncode($business);
			$psorderdata['ticheng'] = self::ticheng($yyset,$order);
			$psorderdata['psfee'] = $psorderdata['ticheng'] * (1 + $yyset['businessfee']*0.01);
			$psorderdata['fwtype'] = $order['fwtype'];
			if($order['bid']!=0){
				//if($worker_id!=-1 || $set['make_shopkoufei']==1){
				//	$businessMoney = Db::name('business')->where('id',$order['bid'])->value('money');
				//	if($businessMoney < $psorderdata['psfee']){
				//		return ['status'=>0,'msg'=>'商家余额不足'];
				//	}
				//	\app\commons\Business::addmoney($aid,$order['bid'],-$psorderdata['psfee'],'服务费');
				//}
			}
			$hasorder = Db::name('yuyue_worker_order')->where('orderid',$order['id'])->find();
			$worker_orderid = Db::name('yuyue_worker_order')->insertGetId($psorderdata);
		}else{
			$hasorder = Db::name('yuyue_worker_order')->where('orderid',$order['id'])->find();
			Db::name('yuyue_worker_order')->where('id',$hasorder['id'])->update(['worker_id'=>$worker_id]);
			$worker_orderid = $hasorder['id'];
		}
		$psorder = Db::name('yuyue_worker_order')->where('id',$worker_orderid)->find();
		//if($worker_id == -1){
		//	$rs = \app\commons\Make::createorder($psorder);
		//	if($rs['status']== 0){
		//		Db::name('yuyue_worker_order')->where('id',$worker_orderid)->delete();
		//		return $rs;
		//	}
		//}
		Db::name('yuyue_order')->where('aid',$aid)->where('id',$order['id'])->update(['worker_id'=>$worker_id,'worker_orderid'=>$worker_orderid,'send_time'=>time()]);
	
		if($worker_id>=0){
			//发送到socket通知
			send_socket(['type'=>'worker_paidan','data'=>['aid'=>$aid,'worker_orderid'=>$worker_orderid]]);
		}
        //指派服务人员
		if($worker_id>0){
			//订单通知
			$psmid = Db::name('yuyue_worker')->where('id',$worker_id)->value('mid');
            if($psmid){
                $tmplcontent = [];
                if($order['fwtype']==1){
                    $tmplcontent['first'] = '您有新的订单等待顾客到店，请及时查看';
                }else{
                    $tmplcontent['first'] = '您有新的订单待上门服务，请及时上门';
                }
                $tmplcontent['remark'] = '请点击查看详情~';
                $tmplcontent['keyword1'] = $order['linkman'];
                $tmplcontent['keyword2'] = $order['tel'];
                $tmplcontent['keyword3'] = $order['area'] .' '. $order['address'];
                $tmplcontent['keyword4'] = $order['title'];
                $tmplcontent['keyword5'] = date('Y-m-d H:i',$order['paytime']);
                $tempconNew = [];
                $tempconNew['character_string1'] = $order['ordernum'];//订单编号
                $tempconNew['thing16'] = $business['name'];//门店名称
                $tempconNew['thing8'] = $order['title'];//商品名称
                $tempconNew['thing5'] = $order['address']?$order['address']:'到店';//客户地址
                $tempconNew['time2'] = date('Y-m-d H:i',$order['paytime']);//订单时间
                $rs = \app\commons\Wechat::sendtmpl($aid,$psmid,'tmpl_peisongorder',$tmplcontent,m_url('activity/yuyue/jdorderlist',$aid),$tempconNew);
//                Log::write([
//                    'file'=>__FILE__,
//                    'line'=>__LINE__,
//                    '$worker_id'=>$worker_id,
//                    '$psmid'=>$psmid,
//                    'tmplcontent'=>$tmplcontent,
//                    'tempconNew'=>$tempconNew,
//                    'rs'=>$rs
//                ]);
            }
        }else{
            //未指派
			//隐藏部分手机号
			$tel = '无';
			if($order['tel']){
				$tel = substr($order['tel'],0,3).'***'.substr($order['tel'],-4,4);
			}
			if($yyset['paidantype']==1){ //指定服务人员
				//取出该订单的服务人员
				$psuserlist = explode(',',$fwpeoid);
				foreach($psuserlist as $psuser){
					//新服务订单通知
					$psmid = Db::name('yuyue_worker')->where('id',$psuser)->value('mid');
                    if($psmid){
                        $tmplcontent = [];
                        $tmplcontent['first'] = '['.$business['name'].']有新的服务订单待接单';
                        $tmplcontent['remark'] = '点击查看详情~';
                        $tmplcontent['keyword1'] = $order['linkman']?$order['linkman']:'无';
                        $tmplcontent['keyword2'] = $tel;
                        $tmplcontent['keyword3'] = $order['area'] .' '. $order['address'];
                        $tmplcontent['keyword4'] = $order['title'];
                        $tmplcontent['keyword5'] = date('Y-m-d H:i',$order['paytime']);
                        $tempconNew = [];
                        $tempconNew['character_string1'] = $order['ordernum'];//订单编号
                        $tempconNew['thing16'] = $business['name'];//门店名称
                        $tempconNew['thing8'] = $order['title'];//商品名称
                        $tempconNew['thing5'] = $order['address']?$order['address']:'到店';//客户地址
                        $tempconNew['time2'] = date('Y-m-d H:i',$order['paytime']);//订单时间
                        \app\commons\Wechat::sendtmpl($aid,$psmid,'tmpl_peisongorder',$tmplcontent,m_url('activity/yuyue/dating',$aid),$tempconNew);
                    }
				}
			}else{ 
				//抢单模式，所有人都可接收消息
				$psuserlist = Db::name('yuyue_worker')->where('aid',$aid)->where('bid',$order['bid'])->where('status',1)->select()->toArray();
                foreach($psuserlist as $psuser){
					//新服务订单通知
					$psmid = $psuser['mid'];
                    if($psmid){
                        $tmplcontent = [];
                        $tmplcontent['first'] = '['.$business['name'].']有新的服务订单待接单';
                        $tmplcontent['remark'] = '点击查看详情~';
                        $tmplcontent['keyword1'] = $order['linkman']?$order['linkman']:'无';
                        $tmplcontent['keyword2'] = $tel;
                        $tmplcontent['keyword3'] = $order['area'] .' '. $order['address'];
                        $tmplcontent['keyword4'] = $order['title'];
                        $tmplcontent['keyword5'] = date('Y-m-d H:i',$order['paytime']);
                        $tempconNew = [];
                        $tempconNew['character_string1'] = $order['ordernum'];//订单编号
                        $tempconNew['thing16'] = $business['name'];//门店名称
                        $tempconNew['thing8'] = $order['title'];//商品名称
                        $tempconNew['thing5'] = $order['address']?$order['address']:'到店';//客户地址
                        $tempconNew['time2'] = date('Y-m-d H:i',$order['paytime']);//订单时间
                        $rs = \app\commons\Wechat::sendtmpl($aid,$psmid,'tmpl_peisongorder',$tmplcontent,m_url('activity/yuyue/dating',$aid),$tempconNew);
                    }
				}
			}
		}


		//订单发货通知
		$tmplcontent = [];
		$tmplcontent['first'] = '您的订单已分派服务人员';
		$tmplcontent['remark'] = '请点击查看详情~';
		$tmplcontent['keyword1'] = $order['title'];
		$tmplcontent['keyword2'] = '预约服务';
		$tmplcontent['keyword3'] = '';
		$tmplcontent['keyword4'] = $order['linkman'].' '.$order['tel'];
        $tmplcontentNew = [];
        $tmplcontentNew['thing4'] = $order['title'];//商品名称
        $tmplcontentNew['thing13'] = '预约服务';//快递公司
        $tmplcontentNew['character_string14'] = '';//快递单号
        $tmplcontentNew['thing16'] = $order['linkman'].' '.$order['tel'];//收货人
		\app\commons\Wechat::sendtmpl($aid,$order['mid'],'tmpl_orderfahuo',$tmplcontent,m_url('activity/yuyue/orderlist', $aid),$tmplcontentNew);
		//订阅消息
		$tmplcontent = [];
		$tmplcontent['thing2'] = $order['title'];
		$tmplcontent['thing7'] = '同城服务';
		$tmplcontent['character_string4'] = '';
		$tmplcontent['thing11'] = $order['address'];
		
		$tmplcontentnew = [];
		$tmplcontentnew['thing29'] = $order['title'];
		$tmplcontentnew['thing1'] = '同城服务';
		$tmplcontentnew['character_string2'] = '';
		$tmplcontentnew['thing9'] = $order['address'];
		\app\commons\Wechat::sendwxtmpl($aid,$order['mid'],'tmpl_orderfahuo',$tmplcontentnew,'activity/yuyue/orderlist',$tmplcontent);

		//短信通知
		$member = Db::name('member')->where('id',$order['mid'])->find();
		if($member['tel']){
			$tel = $member['tel'];
		}else{
			$tel = $order['tel'];
		}
		$rs = \app\commons\Sms::send($aid,$tel,'tmpl_orderfahuo',['ordernum'=>$order['ordernum'],'express_com'=>'同城服务','express_no'=>'']);
		return ['status'=>1,'msg'=>'','worker_orderid'=>$worker_orderid];
	}
	//计算服务人员提成
	public static function ticheng($set=[],$order=[]){
		$pro = Db::name('yuyue_product')->where('id',$order['proid'])->find();
		if($pro['jiesuantype']==0){
			$ticheng = $pro['tcmoney'];
		}else{
            if($pro['tichengtype']==1){
                $totalprice = $order['totalprice'];
            }else{
                $totalprice = $order['product_price'];
            }
            if($order['balance']>0){
                $totalprice = $totalprice + $order['balance'];
            }
			$ticheng = round($totalprice*$pro['tc_bfb']/100,2);
		}
		return $ticheng;
	}
	//计算预计到达时间
	public static function yujitime($set,$order,$juli){
		$psminute = floatval($set['yuji_psminute1']);
		if($juli - floatval($set['yuji_psjuli1']) > 0 && floatval($set['yuji_psjuli2']) > 0){
			$psminute += ceil(($juli - floatval($set['yuji_psjuli1']))/floatval($set['yuji_psjuli2'])) * floatval($set['yuji_psminute2']);
		}
		$yujitime = $order['paytime'] + $psminute*60;
		return $yujitime;
	}

	//取消配送单
	public static function quxiao($order){
		$aid = $order['aid'];
		//$set = Db::name('peisong_set')->where('aid',$aid)->find();
		//if($order['status']!=10 && $order['bid'] > 0 && $order['psfee'] > 0){
		//	if($order['psid']!=-1 || $set['make_shopkoufei']==1){
		//		\app\commons\Business::addmoney($order['aid'],$order['bid'],$order['psfee'],'取消配送返还配送费');
		//	}
		//}
		//if($order['psid']==-1){
		//	$rs = \app\commons\Make::cancelorder($order);
		//	if($rs['status']== 0) return json($rs);
		//}
		Db::name('yuyue_worker_order')->where('id',$order['id'])->update(['status'=>10]);
		return json(['status'=>1,'msg'=>'取消成功']);
	}

	static function create2($order,$worker_id){
		$aid = $order['aid'];
		$hasorder = Db::name('yuyue_worker_order')->where('ordernum',$order['ordernum'])->where('status','<>',10)->find();
		if($hasorder) return ['status'=>0,'msg'=>'已存在配送单'];
		if($order['bid']>0){
			$business = Db::name('business')->field('name,address,tel,logo,longitude,latitude')->where('id',$order['bid'])->find();
		}else{
			$business = Db::name('admin_set')->field('name,address,tel,logo,longitude,latitude')->where('aid',$aid)->find();
		}
        //骑行距离
        $mapqq = new \app\commons\MapQQ();
        $bicycl = $mapqq->getDirectionDistance($order['longitude'],$order['latitude'],$business['longitude'],$business['latitude'],1);
        if($bicycl && $bicycl['status']==1){
            $juli = $bicycl['distance'];
        }else{
            $juli = getdistance($order['longitude'],$order['latitude'],$business['longitude'],$business['latitude'],1);
        }
        //预约设置
        $yyset = Db::name('yuyue_set')->where('aid',$aid)->where('bid',$order['bid'])->find();
		
		//订单信息
		$orderinfo = [];
		$orderinfo['id'] = $order['id'];
		$orderinfo['ordernum'] = $order['ordernum'];
		$orderinfo['createtime'] = $order['createtime'];
		$orderinfo['paytime'] = $order['paytime'];
		$orderinfo['paytype'] = $order['paytype'];
		$orderinfo['product_price'] = $order['product_price'];
		$orderinfo['freight_price'] = $order['paidan_money'];
		$orderinfo['totalprice'] = $order['totalprice'];
		$orderinfo['message'] = $order['message'];
		$orderinfo['linkman'] = $order['linkman'];
		$orderinfo['tel'] = $order['tel'];
		$orderinfo['area'] = $order['area'];
		$orderinfo['address'] = $order['address'];
		$orderinfo['longitude'] = $order['longitude'];
		$orderinfo['latitude'] = $order['latitude'];
		$orderinfo['yydate'] = $order['yy_time'];
		//商品信息
		$proinfo = [];
		$proinfo['name'] = $order['proname'];
		$proinfo['ggname'] = $order['ggname'];
		$proinfo['pic'] = $order['propic'];
		$proinfo['sell_price'] = $order['product_price'];
		$proinfo['num'] = $order['num'];
		$prolist = [$proinfo];
		$orderinfo['procount'] = $order['num'];

		$psorderdata = [];
		$psorderdata['aid'] = $aid;
		$psorderdata['bid'] = $order['bid'];
		$psorderdata['mid'] = $order['mid'];
		$psorderdata['worker_id'] = $worker_id;
		$psorderdata['orderid'] = $order['id'];
		$psorderdata['ordernum'] = $order['ordernum'];
		$psorderdata['createtime'] = time();
		$psorderdata['longitude'] = $business['longitude'];
		$psorderdata['latitude'] = $business['latitude'];
		$psorderdata['longitude2'] = $order['longitude'];
		$psorderdata['latitude2'] = $order['latitude'];
		if($worker_id != 0){ //指服务人员配送 
			$psorderdata['status'] = 1;
			$psorderdata['starttime'] = time();
		}
		$psorderdata['juli'] = $juli;
		$psorderdata['orderinfo'] = jsonEncode($orderinfo);
		$psorderdata['prolist'] = jsonEncode($prolist);
		$psorderdata['binfo'] = jsonEncode($business);
		$psorderdata['ticheng'] = $order['paidan_money'];
		$psorderdata['psfee'] = $psorderdata['ticheng'] * (1 + $yyset['businessfee']*0.01);
		$psorderdata['fwtype'] = $order['fwtype'];

		if($order['bid']!=0){
			//if($worker_id!=-1 || $yyset['make_shopkoufei']==1){
			//	$businessMoney = Db::name('business')->where('id',$order['bid'])->value('money');
			//	if($businessMoney < $psorderdata['psfee']){
			//		return ['status'=>0,'msg'=>'商家余额不足'];
			//	}
			//	\app\commons\Business::addmoney($aid,$order['bid'],-$psorderdata['psfee'],'服务费');
			//}
		}

		$hasorder = Db::name('yuyue_worker_order')->where('orderid',$order['id'])->find();
		$worker_orderid = Db::name('yuyue_worker_order')->insertGetId($psorderdata);
		$psorder = Db::name('yuyue_worker_order')->where('id',$worker_orderid)->find();
		//if($worker_id == -1){
		//	$rs = \app\commons\Make::createorder($psorder);
		//	if($rs['status']== 0){
		//		Db::name('yuyue_worker_order')->where('id',$worker_orderid)->delete();
		//		return $rs;
		//	}
		//}
		Db::name('yuyue_order')->where('aid',$aid)->where('id',$order['id'])->update(['worker_id'=>$worker_id,'worker_orderid'=>$worker_orderid,'send_time'=>time(),'status'=>2]);
	
		if($worker_id>=0){
			//发送到socket通知
			send_socket(['type'=>'worker_paidan','data'=>['aid'=>$aid,'worker_orderid'=>$worker_orderid]]);
		}

		//订单发货通知
		$tmplcontent = [];
		$tmplcontent['first'] = '您的订单已分派服务人员';
		$tmplcontent['remark'] = '请点击查看详情~';
		$tmplcontent['keyword1'] = $order['title'];
		$tmplcontent['keyword2'] = '预约服务';
		$tmplcontent['keyword3'] = '';
		$tmplcontent['keyword4'] = $order['linkman'].' '.$order['tel'];
        $tmplcontentNew = [];
        $tmplcontentNew['thing4'] = $order['title'];//商品名称
        $tmplcontentNew['thing13'] = '预约服务';//快递公司
        $tmplcontentNew['character_string14'] = '';//快递单号
        $tmplcontentNew['thing16'] = $order['linkman'].' '.$order['tel'];//收货人
		\app\commons\Wechat::sendtmpl($aid,$order['mid'],'tmpl_orderfahuo',$tmplcontent,m_url('activity/yuyue/orderlist',$aid),$tmplcontentNew);
		//订阅消息
		$tmplcontent = [];
		$tmplcontent['thing2'] = $order['title'];
		$tmplcontent['thing7'] = '同城服务';
		$tmplcontent['character_string4'] = '';
		$tmplcontent['thing11'] = $order['address'];
		
		$tmplcontentnew = [];
		$tmplcontentnew['thing29'] = $order['title'];
		$tmplcontentnew['thing1'] = '同城服务';
		$tmplcontentnew['character_string2'] = '';
		$tmplcontentnew['thing9'] = $order['address'];
		\app\commons\Wechat::sendwxtmpl($aid,$order['mid'],'tmpl_orderfahuo',$tmplcontentnew,'activity/yuyue/orderlist',$tmplcontent);

		//短信通知
		$member = Db::name('member')->where('id',$order['mid'])->find();
		if($member['tel']){
			$tel = $member['tel'];
		}else{
			$tel = $order['tel'];
		}
		$rs = \app\commons\Sms::send($aid,$tel,'tmpl_orderfahuo',['ordernum'=>$order['ordernum'],'express_com'=>'同城服务','express_no'=>'']);
		return ['status'=>1,'msg'=>''];
	}
	
	//改派订单
	static function update($order,$worker_id,$fwpeoid){
		$aid = $order['aid'];
		$hasorder = Db::name('yuyue_worker_order')->where('orderid',$order['id'])->where('status','<>',10)->find();
		if(!$hasorder) return ['status'=>0,'msg'=>'服务单不存在，无法改派'];
		if($hasorder['status']!=1) return ['status'=>0,'msg'=>'服务单状态不允许改派'];
	    Db::name('yuyue_worker_order')->where('id',$hasorder['id'])->update(['worker_id'=>$worker_id]);
		$worker_orderid = $hasorder['id'];
		Db::name('yuyue_order')->where('aid',$aid)->where('id',$order['id'])->update(['worker_id'=>$worker_id,'worker_orderid'=>$worker_orderid,'send_time'=>time()]);	
		if($worker_id>=0){
			//发送到socket通知
			send_socket(['type'=>'worker_paidan','data'=>['aid'=>$aid,'worker_orderid'=>$worker_orderid]]);
		}
		if($worker_id>0){
			//订单通知
			$psmid = Db::name('yuyue_worker')->where('id',$worker_id)->value('mid');
			$tmplcontent = [];
			if($order['fwtype']==1){
				$tmplcontent['first'] = '您有新的订单等待顾客到店，请及时查看';
			}else{
				$tmplcontent['first'] = '您有新的订单待上门服务，请及时上门';
			}
			$tmplcontent['remark'] = '请点击查看详情~';
			$tmplcontent['keyword1'] = $order['linkman'];
			$tmplcontent['keyword2'] = $order['tel'];
			$tmplcontent['keyword3'] = $order['area'] .' '. $order['address'];
			$tmplcontent['keyword4'] = $order['title'];
			$tmplcontent['keyword5'] = date('Y-m-d H:i',$order['paytime']);
		}
		//订单改派通知
		$tmplcontent = [];
		$tmplcontent['first'] = '您的订单已改派服务人员';
		$tmplcontent['remark'] = '请点击查看详情~';
		$tmplcontent['keyword1'] = $order['title'];
		$tmplcontent['keyword2'] = '预约服务';
		$tmplcontent['keyword3'] = '';
		$tmplcontent['keyword4'] = $order['linkman'].' '.$order['tel'];
        $tmplcontentNew = [];
        $tmplcontentNew['thing4'] = $order['title'];//商品名称
        $tmplcontentNew['thing13'] = '预约服务';//快递公司
        $tmplcontentNew['character_string14'] = '';//快递单号
        $tmplcontentNew['thing16'] = $order['linkman'].' '.$order['tel'];//收货人
		\app\commons\Wechat::sendtmpl($aid,$order['mid'],'tmpl_orderfahuo',$tmplcontent,m_url('activity/yuyue/orderlist',$aid),$tmplcontentNew);
		//订阅消息
		$tmplcontent = [];
		$tmplcontent['thing2'] = $order['title'];
		$tmplcontent['thing7'] = '同城服务';
		$tmplcontent['character_string4'] = '';
		$tmplcontent['thing11'] = $order['address'];
		
		$tmplcontentnew = [];
		$tmplcontentnew['thing29'] = $order['title'];
		$tmplcontentnew['thing1'] = '同城服务';
		$tmplcontentnew['character_string2'] = '';
		$tmplcontentnew['thing9'] = $order['address'];
		\app\commons\Wechat::sendwxtmpl($aid,$order['mid'],'tmpl_orderfahuo',$tmplcontentnew,'activity/yuyue/orderlist',$tmplcontent);

		//短信通知
		$member = Db::name('member')->where('id',$order['mid'])->find();
		if($member['tel']){
			$tel = $member['tel'];
		}else{
			$tel = $order['tel'];
		}
		$rs = \app\commons\Sms::send($aid,$tel,'tmpl_orderfahuo',['ordernum'=>$order['ordernum'],'express_com'=>'同城服务','express_no'=>'']);
		return ['status'=>1,'msg'=>''];
	}
}