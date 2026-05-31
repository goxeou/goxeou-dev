<?php


namespace app\models;
use think\facade\Db;
class PeisongOrder
{
	//创建订单
	static function create($type,$order,$psid=0,$other = []){
		$aid = $order['aid'];
		$hasorder = Db::name('peisong_order')->where('type',$type)->where('orderid',$order['id'])->where('status','<>',10)->find();
		if($hasorder) return ['status'=>0,'msg'=>'已存在配送单'];

		$set = Db::name('peisong_set')->where('aid',$aid)->find();

		if($type == 'paotui_order'){
			$business = ['id'=>0,'name'=>$order['take_name'],'tel'=>$order['take_tel'],'city'=>$order['take_city'],'longitude'=>$order['take_longitude'],'latitude'=>$order['take_latitude'],'area'=>$order['take_area'],'address'=>$order['take_address']];
		}else{
			if($order['bid']>0){
				$business = Db::name('business')->field('name,address,tel,logo,longitude,latitude,money')->where('id',$order['bid'])->find();
			}elseif($order['mdid']>0){
                $business = Db::name('mendian')->field('name,address,tel,pic,longitude,latitude,money')->where('id',$order['mdid'])->find();
            }else{
				$business = Db::name('admin_set')->field('name,address,tel,logo,longitude,latitude')->where('aid',$aid)->find();
			}
		}

		//查询骑行距离
        $mapqq = new \app\commons\MapQQ();
        $bicycl = $mapqq->getDirectionDistance($business['longitude'],$business['latitude'],$order['longitude'],$order['latitude'],1);
		if($bicycl && $bicycl['status']==1){
			$juli = $bicycl['distance'];
		}else{
			$juli = getdistance($order['longitude'],$order['latitude'],$business['longitude'],$business['latitude'],1);
		}

        $order_stage = $order;
		//订单信息
        if($type=='cycle_order_stage'){
            $orderinfo = Db::name('cycle_order')->where('id',$order['orderid'])->find();
            $order['paytime'] = $orderinfo['paytime'];
            $order['linkman'] = $orderinfo['linkman'];
            $order['tel'] = $orderinfo['tel'];
            $order['area'] = $orderinfo['area'];
            $order['address'] = $orderinfo['address'];
            $order['title'] = $orderinfo['title'];
            //设置订单 cycle_order 的状态为 已发货
            if($orderinfo['status'] ==1){
                Db::name('cycle_order')->where('id',$order['orderid'])->update(['status' => 2,'send_time' => time()]);
            }
        }else{
            $orderinfo = [];
            $orderinfo['id'] = $order['id'];
            $orderinfo['ordernum'] = $order['ordernum'];
            $orderinfo['createtime'] = $order['createtime'];
            $orderinfo['paytime'] = $order['paytime'];
            $orderinfo['paytype'] = $order['paytype'];
        	$orderinfo['product_price'] = $order['product_price'];
        	$orderinfo['freight_price'] = $order['freight_price'];
            $orderinfo['message'] = $order['message'];
            $orderinfo['linkman'] = $order['linkman'];
            $orderinfo['tel'] = $order['tel'];
            $orderinfo['area'] = $order['area'];
            $orderinfo['address'] = $order['address'];
            $orderinfo['longitude'] = $order['longitude'];
            $orderinfo['latitude'] = $order['latitude'];
            $orderinfo['totalprice'] = $order['totalprice'];
            if($type == 'paotui_order'){
            	$orderinfo['type'] 		       = 'paotui_order';
            	$orderinfo['expect_take_time'] = $order['take_time']?$order['take_time']:0;
            	$orderinfo['pic'] 			   = $order['pic']?$order['pic']:'';
            	$orderinfo['btntype'] 		   = $order['btntype']?$order['btntype']:'';
            	$orderinfo['take_tel'] 		   = $order['take_tel']?$order['take_tel']:'';
            	$orderinfo['send_tel'] 		   = $order['send_tel']?$order['send_tel']:'';
            }
        }
        if($type == 'paotui_order'){
        	$formdata = [];
        }else{
        	$formdata = \app\models\Freight::getformdata($order['id'],$type);
        }
        $orderinfo['formdata']  =  $formdata?$formdata:[];

		//商品信息
		if($type == 'shop_order' || $type=='scoreshop_order' || $type=='restaurant_takeaway_order' ){
			if($type=='scoreshop_order'){
				$prolist = Db::name($type.'_goods')->field('name,pic,sell_price,num')->where('orderid',$order['id'])->select()->toArray();
			}else{
				$prolist = Db::name($type.'_goods')->field('name,ggname,pic,sell_price,num')->where('orderid',$order['id'])->select()->toArray();
			}
			$orderinfo['procount'] = Db::name($type.'_goods')->where('orderid',$order['id'])->sum('num');

		}else{
			$proinfo = [];
			$proinfo['name'] = $order['proname'];
			$proinfo['ggname'] = $order['ggname'];
			$proinfo['pic'] = $order['propic'];
			$proinfo['sell_price'] = $order['sell_price'];
			$proinfo['num'] = $order['num'];
			$prolist = [$proinfo];
			$orderinfo['procount'] = $order['num'];
		}
		$psorderdata = [];
		$psorderdata['aid'] = $aid;
		$psorderdata['bid'] = $order['bid'];
        $psorderdata['mdid'] = $order['mdid'];
		$psorderdata['mid'] = $order['mid'];
		$psorderdata['psid'] = $psid;
		$psorderdata['orderid'] = $order['id'];
		$psorderdata['ordernum'] = $order['ordernum'];
		$psorderdata['createtime'] = time();

		$psorderdata['longitude'] = $business['longitude'];
		$psorderdata['latitude'] = $business['latitude'];
		$psorderdata['longitude2'] = $order['longitude'];
		$psorderdata['latitude2'] = $order['latitude'];

		if($psid != 0 && $psid!=-1 && $psid!=-2){ //指定配送员配送  -1码科配送 -2 麦芽田
			$psorderdata['status'] = 1;
			$psorderdata['starttime'] = time();
		}
		$psorderdata['type'] = $type;
		$psorderdata['juli'] = $juli;
		$psorderdata['yujitime'] = self::yujitime($set,$order,$juli/1000);
        $psorderdata['orderinfo'] =  jsonEncode($orderinfo);
		$psorderdata['prolist'] = jsonEncode($prolist);
		$psorderdata['binfo'] = jsonEncode($business);
		if($psid == -1){ //码科配送费
			if($type == 'paotui_order'){
				$rs = \app\commons\Make::getprice($aid,$order['bid'],$order['take_latitude'],$order['take_longitude'],$order['send_latitude'],$order['send_longitude']);
			}else{
				$rs = \app\commons\Make::getprice($aid,$order['bid'],$business['latitude'],$business['longitude'],$order['latitude'],$order['longitude']);
			}
			if($rs['status']==0) return $rs;
			$price = $rs['price'];
			$psorderdata['ticheng'] = $price;
			$psorderdata['psfee']   = $psorderdata['ticheng'] * (1 + $set['businessfee']*0.01);
		} else if($psid == -2){
			if(getcustom('express_maiyatian')) {
				//不支持跑腿订单推送
				if($type != 'paotui_order'){
	                $set = Db::name('peisong_set')->where('aid',$aid)->find();
	                if(!$set) 	return ['status'=>0,'msg'=>'系统设置不存在'];
		            $res_check = \app\customs\MaiYaTianCustom::check_info($aid,$set,$order,$prolist,$other);
		            if($res_check['status'] == 0){
		            	return ['status'=>0,'msg'=>$res_check['msg']];
		            }
		        }
				// $psorderdata['ticheng'] = 0;
				// $psorderdata['psfee']   = 0;
	        }
		}else{
			$psorderdata['ticheng'] = self::ticheng($set,$order,$juli/1000);
			$psorderdata['psfee'] = $psorderdata['ticheng'] * (1 + $set['businessfee']*0.01);
		}
		if(getcustom('paotui')){
		 	//添加小费和期望取件时间
		 	if($type == 'paotui_order'){
		 		$psorderdata['tip_fee'] = $order['tip_fee'];
		 		$psorderdata['expect_take_time'] = $order['take_time'];
		 	}
		}

		if($order['bid']>0 && !getcustom('hmy_yuyue')){
			if($psid!=-1 || $set['make_shopkoufei']==1){
				$businessMoney = Db::name('business')->where('id',$order['bid'])->value('money');
				if($businessMoney < $psorderdata['psfee']){
					return ['status'=>0,'msg'=>'商家余额不足'];
				}
				\app\commons\Business::addmoney($aid,$order['bid'],-$psorderdata['psfee'],'配送费');
			}
		}

//		$hasorder = Db::name('peisong_order')->where('type',$type)->where('orderid',$order['id'])->find();
		$psorderid = Db::name('peisong_order')->insertGetId($psorderdata);
		$psorder = Db::name('peisong_order')->where('id',$psorderid)->find();
		if($psid == -1){
			$rs = \app\commons\Make::createorder($psorder);
			if($rs['status']== 0){
				Db::name('peisong_order')->where('id',$psorderid)->delete();
				return $rs;
			}
		}
		if(getcustom('express_maiyatian')) {
			//跑腿的不对接在这里面
			if($type != 'paotui_order' && $psid == -2){
				$rs = \app\customs\MaiYaTianCustom::order_add($aid,$set,$psorderid,$order,$prolist,$other);
				if($rs['status']== 0){
					Db::name('peisong_order')->where('id',$psorderid)->delete();
					return $rs;
				}
			}
	    }

        //发货信息录入 微信小程序+微信支付
        if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
            \app\commons\Order::wxShipping($order['aid'],$order,$type);
        }

	    if($type == 'paotui_order' ){
	    	Db::name($type)->where('aid',$aid)->where('id',$order['id'])->update(['express_com'=>'同城配送','express_no'=>$psorderid,'send_time'=>time()]);
	    }else{
		    Db::name($type)->where('aid',$aid)->where('id',$order['id'])->update(['express_com'=>'同城配送','express_no'=>$psorderid,'send_time'=>time(),'status'=>2]);
	    }

		if($type == 'shop_order'){
			Db::name('shop_order_goods')->where('orderid',$order['id'])->where('aid',$aid)->update(['status'=>2]);
		}
		if($type == 'scoreshop_order'){
			Db::name('scoreshop_order_goods')->where('orderid',$order['id'])->where('aid',$aid)->update(['status'=>2]);
		}
		if($type == 'restaurant_takeaway_order'){
			Db::name('restaurant_takeaway_order_goods')->where('orderid',$order['id'])->where('aid',$aid)->update(['status'=>2]);
		}
		if($psid>=0){
			//发送到socket通知
			send_socket(['type'=>'peisong','data'=>['aid'=>$aid,'psorderid'=>$psorderid]]);
		}
		if($psid>0){
			//新配送订单通知
			$psmid = Db::name('peisong_user')->where('id',$psid)->value('mid');
			$tmplcontent = [];
			$tmplcontent['first'] = '您有新的订单待配送，请及时配送';
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
            $tempconNew['thing5'] = $order['address']?$order['address']:'无';//客户地址
            $tempconNew['time2'] = date('Y-m-d H:i',$order['paytime']);//订单时间
			\app\commons\Wechat::sendtmpl($aid,$psmid,'tmpl_peisongorder',$tmplcontent,m_url('activity/peisong/orderlist', $aid),$tempconNew);
		}else if($psid==0){
			$psuserlist = Db::name('peisong_user')->where('aid',$aid)->where('status',1)->select()->toArray();
			foreach($psuserlist as $psuser){
				//新配送订单通知
				$psmid = Db::name('peisong_user')->where('id',$psuser['id'])->value('mid');
				$tmplcontent = [];
				$tmplcontent['first'] = '['.$business['name'].']有新的配送订单待接单';
				$tmplcontent['remark'] = '点击查看详情~';
                $tmplcontent['keyword1'] = $order['linkman'];
                $tmplcontent['keyword2'] = $order['tel'];
				$tmplcontent['keyword3'] = $order['area'] .' '. $order['address'];
				$tmplcontent['keyword4'] = $order['title'];
				$tmplcontent['keyword5'] = date('Y-m-d H:i',$order['paytime']);
                $tempconNew = [];
                $tempconNew['character_string1'] = $order['ordernum'];//订单编号
                $tempconNew['thing16'] = $business['name'];//门店名称
                $tempconNew['thing8'] = $order['title'];//商品名称
                $tempconNew['thing5'] = $order['address']?$order['address']:'无';//客户地址
                $tempconNew['time2'] = date('Y-m-d H:i',$order['paytime']);//订单时间
				\app\commons\Wechat::sendtmpl($aid,$psmid,'tmpl_peisongorder',$tmplcontent,m_url('activity/peisong/dating', $aid),$tempconNew);
			}
		}
		//订单发货通知
		$tmplcontent = [];
		$tmplcontent['first'] = '您的订单已分派配送人员进行配送';
		$tmplcontent['remark'] = '请点击查看详情~';
		$tmplcontent['keyword1'] = $order['title'];
		$tmplcontent['keyword2'] = '同城配送';
		$tmplcontent['keyword3'] = '';
		$tmplcontent['keyword4'] = $order['linkman'].' '.$order['tel'];
        $tmplcontentNew = [];
        $tmplcontentNew['thing4'] = $order['title'];//商品名称
        $tmplcontentNew['thing13'] = '同城配送';//快递公司
        $tmplcontentNew['character_string14'] = '';//快递单号
        $tmplcontentNew['thing16'] = $order['linkman'].' '.$order['tel'];//收货人
		\app\commons\Wechat::sendtmpl($aid,$order['mid'],'tmpl_orderfahuo',$tmplcontent,m_url('pages/my/usercenter', $aid),$tmplcontentNew);
		//订阅消息
		$tmplcontent = [];
		$tmplcontent['thing2'] = $order['title'];
		$tmplcontent['thing7'] = '同城配送';
		$tmplcontent['character_string4'] = '';
		$tmplcontent['thing11'] = $order['address'];

		$tmplcontentnew = [];
		$tmplcontentnew['thing29'] = $order['title'];
		$tmplcontentnew['thing1'] = '同城配送';
		$tmplcontentnew['character_string2'] = '';
		$tmplcontentnew['thing9'] = $order['address'];
		\app\commons\Wechat::sendwxtmpl($aid,$order['mid'],'tmpl_orderfahuo',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);

		//短信通知
		$member = Db::name('member')->where('id',$order['mid'])->find();
		if($member['tel']){
			$tel = $member['tel'];
		}else{
			$tel = $order['tel'];
		}
		$rs = \app\commons\Sms::send($aid,$tel,'tmpl_orderfahuo',['ordernum'=>$order['ordernum'],'express_com'=>'同城配送','express_no'=>'']);
		return ['status'=>1,'msg'=>''];
	}
	//计算配送员提成
	public static function ticheng($set,$order,$juli){
		if($set['jiesuantype']==0){
			$ticheng = $set['tcmoney'];
		}else{
			$ticheng = floatval($set['peisong_tcmoney1']);
			if($juli - floatval($set['peisong_juli1']) > 0 && floatval($set['peisong_juli2']) > 0){
				$ticheng += ceil(($juli - floatval($set['peisong_juli1']))/floatval($set['peisong_juli2'])) * floatval($set['peisong_tcmoney2']);
			}
		}
		if($set['peisong_tcmoneymax'] > 0 && $ticheng > $set['peisong_tcmoneymax']) $ticheng = $set['peisong_tcmoneymax'];
		return $ticheng;
	}
	//计算预计送达时间
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
		$data = [];
		$set = Db::name('peisong_set')->where('aid',$aid)->find();

		if($order['type'] !='paotui_order' && $order['psid'] !=-2 && $order['status']!=10 && $order['bid'] > 0 && $order['psfee'] > 0){
			if($order['psid']!=-1 || $set['make_shopkoufei']==1){
				\app\commons\Business::addmoney($order['aid'],$order['bid'],$order['psfee'],'取消配送返还配送费');
			}
		}

		if($order['psid']==-1){
			$rs = \app\commons\Make::cancelorder($order);
			if($rs['status']== 0) return $rs;
		}else if($order['psid']==-2){
			if(getcustom('express_maiyatian')){
				$res_cancel = \app\customs\MaiYaTianCustom::order_cancel($aid,$order['bid'],'',$order['ordernum']);
				if($res_cancel['status']== 0){
					return json($res_cancel['msg']);
				}
				$data = $res_cancel['data'];
				if($order['bid'] > 0){
					if($order['psfee']>0){
						\app\commons\Business::addmoney($order['aid'],$order['bid'],$order['psfee'],'取消配送返还配送费');
					}
	                //扣除违约金
	                if($data['cancel_fee'] > 0){
	                    \app\commons\Business::addmoney($order['aid'],$order['bid'],$data['cancel_fee']*-1,'取消配送扣除违约金');

	                    //查询关联表，记录cancel_fee
	                    $myt = Db::name('peisong_order_myt')->where('poid',$order['id'])->field('id')->find();
	                    if($myt){
	                    	$mytdata = [];
	                    	$mytdata['cancel_amount'] = $data['cancel_fee'];
                        	$mytdata['updatetime'] = time();
                        	$upmyt = Db::name('peisong_order_myt')->where('id',$myt['id'])->update($mytdata);
	                    }
	                }
	            }
	        }
		}

		Db::name('peisong_order')->where('id',$order['id'])->update(['status'=>10]);

		if(getcustom('paotui')){
			if($order['type'] =='paotui_order'){
				$pt_order = Db::name('paotui_order')->where('id',$order['orderid'])->find();
				if($pt_order){
					//退款
		            $rs2 = \app\commons\Order::refund($pt_order,$pt_order['totalprice'],'取消订单');
		            $data = [];
		            $data['updatetime'] = time();
		            if($rs2['status']==0){
		            	$msg = $rs2['msg']?$rs2['msg']:'';

		            	$data['status']        = -2;
		            	$data['refund_status'] = -2;//-2：退款失败 -1驳回退款 1: 申请退款 2：退款成功
		            	$data['refund_money']  = $pt_order['totalprice'];
		            	$data['cancel_fail_reason'] = $msg;
		                $up = Db::name('paotui_order')->where('id',$order['orderid'])->update($data);
		                return json(['status'=>0,'msg'=>$rs2['msg']]);
		            }else{
		            	$data['status']       = -1;
		            	$data['refund_status']= 2;//-2：退款失败 -1驳回退款 1: 申请退款 2：退款成功
		            	$data['refund_money'] = $pt_order['totalprice'];
		            	$data['refund_time']  = time();
		            	$up = Db::name('paotui_order')->where('id',$order['orderid'])->update($data);
		            }
				}
			}
		}

		return ['status'=>1,'msg'=>'取消成功'];
	}

    public static function getStatusTxt($st){
        $status = [
           '-1'=>'未派单','0'=>'待接单', '1'=>'已接单', '2'=>'已到店', '3'=>'已取货','4'=>'已完成','10'=>'已取消'
        ];
        if(isset($status[$st])){
            return $status[$st];
        }else{
            return $st;
        }
    }
}
