<?php



//订单同步到红蚂蚁订单
namespace app\customs;
use think\facade\Db;
class Yuyue
{
	public static function api($order){
		$business = [];
		if($order['bid']){
			  $business = Db::name('business')->where('aid',aid)->where('id',$order['bid'])->find();
		}
		$config = include(ROOT_PATH.'config.php');
		$appId=$config['hmyyuyue']['appId'];
		$appSecret=$config['hmyyuyue']['appSecret'];
		$headrs = array('content-type: application/json;charset=UTF-8','appid:'.$appId,'appSecret:'.$appSecret);
		$tb_data = [];
		$url2 = 'https://shifu.api.kkgj123.cn/api/1/commission/rule';
		$res2 = curl_get($url2,$param=[],$headrs);
		$res2 = json_decode($res2,true);
		if($res2['code']==200){
			$res2 = $res2['data'];
			if($res2['merchantErrandCommission']!=1){
				$tb_data['commission'] = floatval($order['totalprice']*$res2['merchantErrandCommission'])/100;
			}
		}
		$juli = getdistance($address['longitude'],$address['latitude'],$business['peisong_lng'],$business['peisong_lat'],2);
		$tb_data['shopOrderNo'] = $order['ordernum'];
		$tb_data['orderTime'] = date('Y-m-d H:i:s');
		$tb_data['name'] = $order['linkman'];
		$tb_data['phone'] = $order['tel'];
		$tb_data['address'] = $order['area'].$order['address'];
		$tb_data['errandDistance'] = $juli;
		$tb_data['errandFee'] = $order['freight_price'];
		$tb_data['merchantName'] = $business['name']?$business['name']:'';
		$tb_data['merchantPhone'] = $business['tel']?$business['tel']:'';
		$tb_data['merchantAddress'] = $business['address']?$business['address']:'';
		$tb_data['productName'] = mb_substr($order['title'],0,8);
		$tb_data['lon'] = $order['longitude']?$order['longitude']:'';
		$tb_data['lat'] = $order['latitude'];
		$tb_data['merchantLon'] = $business['longitude'];
	    $tb_data['merchantLat'] = $business['latitude'];
		$formdata = \app\models\Freight::getformdata($order['id'],'shop_order');
		$tb_data['customerMessage'] = $formdata[0][1];
		$url = 'http://shifu.api.kkgj123.cn/api/1/order/errand';
		$data2 = json_encode($tb_data,JSON_UNESCAPED_UNICODE);
		$res = curl_post($url,$data2,'',$headrs);	
		$res = json_decode($res,true);
		if($res['code']==200){
			Db::name('shop_order')->where('id',$order['id'])->update(['sysOrderNo'=>$res['data']['sysOrderNo']]);
			return ['status'=>1,'msg'=>'同步成功'];	
		}else{
			\think\facade\Log::write($res);
			return ['status'=>0,'msg'=>$res['msg']];	
		}
	}
	public static function apiyuyue($order){
		 $config = include(ROOT_PATH.'config.php');
		 $appId=$config['hmyyuyue']['appId'];
		 $appSecret=$config['hmyyuyue']['appSecret'];
		 $product = db('yuyue_product')->where(['id'=>$order['proid']])->find();
		 if($order['paidan_type']==2){
			 $ordergg = db('yuyue_guige')->field('name')->where(['id'=>$order['ggid']])->find();
			 $data1 = [];
			 $data1['shopOrderNo'] = $order['ordernum'];
			 $data1['orderTime'] = date('Y-m-d H:i:s',$order['paytime']);
			 $data1['name'] = $order['linkman'];
			 $data1['phone'] = $order['tel'];
			 $data1['address'] = $order['address'];
			 $data1['appointTime'] = date('Y-m-d H:i:s',$order['begintime']);
			 $data1['firstCategory'] = $order['firstCategory'];
			 $data1['secondCategory'] = $order['secondCategory'];
			 $data1['commission'] = $order['commission'];
			 $data1['platformIncome'] = 0;
			 $data1['brand'] = '';
			 $data1['duration'] = $product['fwlong'];
			 $data1['quantity'] = $order['num'];
			 $data1['unit'] = $product['danwei'];
			 $data1['price'] = floatval($order['product_price']);
			 $data1['deposit'] = $order['totalprice'];
			 $data1['orderBalance'] = ($order['balance_price']/$data1['price'])*100;
			 $data1['lon'] = $order['longitude'];
			 $data1['lat'] = $order['latitude'];
			 $formdata = \app\models\Freight::getformdata($order['id'],'yuyue_order');
			 $data1['customerMessage'] = $formdata[0][1];
			 $data1['serviceStandard'] = $ordergg['name'];
			 $data2 = json_encode($data1,JSON_UNESCAPED_UNICODE);
			 //var_dump($desc);
				
			 $url = 'http://shifu.api.kkgj123.cn/api/1/order/paltform';
			 $headrs = array('content-type: application/json;charset=UTF-8','appid:'.$appId,'appSecret:'.$appSecret);
			 $res = curl_post($url,$data2,'',$headrs);	
			 $res = json_decode($res,true);
			 if($res['code']==200){
				Db::name('yuyue_order')->where('id',$order['id'])->update(['sysOrderNo'=>$res['data']['sysOrderNo']]);
				return ['status'=>1,'msg'=>'同步成功'];	
			}else{
				\think\facade\Log::write($res);
				return ['status'=>0,'msg'=>$res['msg']];	
			}
	
		 }
		 if($order['paidan_type']==3){ //定制 指定师傅订单 将订单同步到师傅app端
			 $data1 = [];
			 $data1['shopOrderNo'] = $order['ordernum'];
			 $data1['orderTime'] = date('Y-m-d H:i:s',$order['createtime']);
			 $data1['name'] = $order['linkman'];
			 $data1['phone'] = $order['tel'];
			 $data1['address'] = $order['address'];
			 $data1['appointTime'] = date('Y-m-d H:i:s',$order['begintime']);
			 $data1['firstCategory'] = $order['firstCategory'];
			 $data1['secondCategory'] = $order['secondCategory'];
			 $data1['masterId'] = $order['worker_id'];
			 $data1['masterName'] = $order['masterName'];
			 $data1['errandDistance'] = $order['errandDistance']?$order['errandDistance']:0;
			 $data1['errandFee'] = $order['paidan_money'];
			 $data1['commission'] = $order['commission'];
			 $data1['duration'] = 0;
			 $data1['times'] = $order['num'];
			 $data1['unit'] = $order['unit'];
			 $data1['price'] = floatval($order['totalprice']);
			 $data1['lon'] = $order['longitude'];
			 $data1['lat'] = $order['latitude'];
			 $data1['customerMessage'] = $order['message'];
			 $data2 = json_encode($data1,JSON_UNESCAPED_UNICODE);
			 $url = 'http://shifu.api.kkgj123.cn//api/1/order/appoint';
			 $headrs = array('content-type: application/json;charset=UTF-8','appid:'.$appId,'appSecret:'.$appSecret);
			 $res = curl_post($url,$data2,'',$headrs);	
			 $res = json_decode($res,true);
			 if($res['code']==200){
				Db::name('yuyue_order')->where('id',$order['id'])->update(['sysOrderNo'=>$res['data']['sysOrderNo']]);
				return ['status'=>1,'msg'=>'同步成功'];	
			 }else{
				\think\facade\Log::write($res);
				return ['status'=>0,'msg'=>$res['msg']];	
			}
		}
		
	}

	public function payoff($order){
		$config = include(ROOT_PATH.'config.php');
		$appId=$config['hmyyuyue']['appId'];
		$appSecret=$config['hmyyuyue']['appSecret'];
		 if($order['firstCategory']>0){ //定制 将订单同步到师傅app端
			 $data1 = [];
			 $data1['sysOrderNo'] = $order['sysOrderNo'];
			 $data2 = json_encode($data1,JSON_UNESCAPED_UNICODE);
			 //var_dump($desc);
			 $url = 'http://shifu.api.kkgj123.cn/api/1/order/payoff';
			 $headrs = array('content-type: application/json;charset=UTF-8','appid:'.$appId,'appSecret:'.$appSecret);
			 $res = curl_post($url,$data2,'',$headrs);	
			 \think\facade\Log::write($res);

		}
	}

	public function refund($order){
		$orderid = $order['id'];
		$aid = $order['aid'];
		$rs = \app\commons\Order::refund($order,$order['refund_money'],$order['refund_reason']);
		if($rs['status']==0){
			return json(['status'=>0,'msg'=>$rs['msg']]);
		}
		//积分抵扣的返还
		if($order['scoredkscore'] > 0){
			\app\commons\Member::addscore($aid,$order['mid'],$order['scoredkscore'],'订单退款返还');
		}
		//退款退还佣金
		//退款成功通知
		$tmplcontent = [];
		$tmplcontent['first'] = '您的订单已经完成退款，¥'.$order['refund_money'].'已经退回您的付款账户，请留意查收。';
		$tmplcontent['remark'] = '请点击查看详情~';
		$tmplcontent['orderProductPrice'] = $order['refund_money'];
		$tmplcontent['orderProductName'] = $order['title'];
		$tmplcontent['orderName'] = $order['ordernum'];
        $tmplcontentNew = [];
        $tmplcontentNew['character_string1'] = $order['ordernum'];//订单编号
        $tmplcontentNew['thing2'] = $order['title'];//商品名称
        $tmplcontentNew['amount3'] = $order['refund_money'];//退款金额
		\app\commons\Wechat::sendtmpl($aid,$order['mid'],'tmpl_tuisuccess',$tmplcontent,m_url('pages/my/usercenter'),$tmplcontentNew);
		//订阅消息
		$tmplcontent = [];
		$tmplcontent['amount6'] = $order['refund_money'];
		$tmplcontent['thing3'] = $order['title'];
		$tmplcontent['character_string2'] = $order['ordernum'];
		
		$tmplcontentnew = [];
		$tmplcontentnew['amount3'] = $order['refund_money'];
		$tmplcontentnew['thing6'] = $order['title'];
		$tmplcontentnew['character_string4'] = $order['ordernum'];
		\app\commons\Wechat::sendwxtmpl($aid,$order['mid'],'tmpl_tuisuccess',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);
		//短信通知
		$member = Db::name('member')->where('id',$order['mid'])->find();
		if($member['tel']){
			$tel = $member['tel'];
		}else{
			$tel = $order['tel'];
		}
		$rs = \app\commons\Sms::send($aid,$tel,'tmpl_tuisuccess',['ordernum'=>$order['ordernum'],'money'=>$order['refund_money']]);
		//退款状态同步到app
		$config = include(ROOT_PATH.'config.php');
		$appId=$config['hmyyuyue']['appId'];
		$appSecret=$config['hmyyuyue']['appSecret'];
		$headrs = array('content-type: application/json;charset=UTF-8','appid:'.$appId,'appSecret:'.$appSecret);
		$url = 'https://shifu.api.kkgj123.cn/api/1/order/cancel';
		$data = [];
		$data['sysOrderNo'] = $order['sysOrderNo'];
		$data['cancelParty'] = 1;
		$data['cancelReason'] = '用户自己主动取消';
		$data = json_encode($data,JSON_UNESCAPED_UNICODE);
		$res = curl_post($url,$data,'',$headrs);	
		$res = json_decode($res,true);
		if($res['code']==200){
			return ['status'=>1,'msg'=>'退款成功'];	
		 }else{
			\think\facade\Log::write($res);
			return ['status'=>0,'msg'=>$res['msg']];	
		}
				
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

    //预约分销
    public static function yuyueCommission($aid,$member,$product,$guige,&$orderdata=[]){
        if(getcustom('yuyue_commission')){
            $sysset = Db::name('admin_set')->where('aid',$aid)->find();
            $agleveldata = Db::name('member_level')->where('aid',$aid)->where('id',$member['levelid'])->find();
            if($agleveldata['can_agent'] > 0 && $agleveldata['commission1own']==1){
                $member['pid'] = $member['id'];
            }
            //计算佣金的商品金额
            if($sysset['fxjiesuantype']==1){
                $commission_totalprice = $orderdata['totalprice'];
            }else if($sysset['fxjiesuantype']==2){
                $commission_totalprice = $orderdata['totalprice'];
                $commission_totalprice = $commission_totalprice - $guige['cost_price'] * $orderdata['num'];
            }else{
                $commission_totalprice = $guige['sell_price'] * $orderdata['num'];
            }
            if($product['commissionset']!=-1){
                if($member['pid']){
                    $parent1 = Db::name('member')->where('aid',$aid)->where('id',$member['pid'])->find();
                    if($parent1){
                        $agleveldata1 = Db::name('member_level')->where('aid',$aid)->where('id',$parent1['levelid'])->find();
                        if($agleveldata1['can_agent']!=0){
                            $orderdata['parent1'] = $parent1['id'];
                        }
                    }
                }
                if($parent1['pid']){
                    $parent2 = Db::name('member')->where('aid',$aid)->where('id',$parent1['pid'])->find();
                    if($parent2){
                        $agleveldata2 = Db::name('member_level')->where('aid',$aid)->where('id',$parent2['levelid'])->find();
                        if($agleveldata2['can_agent']>1){
                            $orderdata['parent2'] = $parent2['id'];
                        }
                    }
                }
                if($parent2['pid']){
                    $parent3 = Db::name('member')->where('aid',$aid)->where('id',$parent2['pid'])->find();
                    if($parent3){
                        $agleveldata3 = Db::name('member_level')->where('aid',$aid)->where('id',$parent3['levelid'])->find();
                        if($agleveldata3['can_agent']>2){
                            $orderdata['parent3'] = $parent3['id'];
                        }
                    }
                }
                if($product['commissionset']==1){//按商品设置的分销比例
                    $commissiondata = json_decode($product['commissiondata1'],true);
                    if($commissiondata){
                        if($agleveldata1) $orderdata['parent1commission'] = $commissiondata[$agleveldata1['id']]['commission1'] * $commission_totalprice * 0.01;
                        if($agleveldata2) $orderdata['parent2commission'] = $commissiondata[$agleveldata2['id']]['commission2'] * $commission_totalprice * 0.01;
                        if($agleveldata3) $orderdata['parent3commission'] = $commissiondata[$agleveldata3['id']]['commission3'] * $commission_totalprice * 0.01;
                    }
                }elseif($product['commissionset']==2){//按固定金额
                    $commissiondata = json_decode($product['commissiondata2'],true);
                    if($commissiondata){
                        if($agleveldata1) $orderdata['parent1commission'] = $commissiondata[$agleveldata1['id']]['commission1'];
                        if($agleveldata2) $orderdata['parent2commission'] = $commissiondata[$agleveldata2['id']]['commission2'];
                        if($agleveldata3) $orderdata['parent3commission'] = $commissiondata[$agleveldata3['id']]['commission3'];
                    }
                }elseif($product['commissionset']==3){//提成是积分
                    $commissiondata = json_decode($product['commissiondata3'],true);
                    if($commissiondata){
                        if($agleveldata1) $orderdata['parent1score'] = $commissiondata[$agleveldata1['id']]['commission1'];
                        if($agleveldata2) $orderdata['parent2score'] = $commissiondata[$agleveldata2['id']]['commission2'];
                        if($agleveldata3) $orderdata['parent3score'] = $commissiondata[$agleveldata3['id']]['commission3'];
                    }
                }else{ //按会员等级设置的分销比例
                    if($agleveldata1){
                        if($agleveldata1['commissiontype']==1){ //固定金额按单
                            $orderdata['parent1commission'] = $agleveldata1['commission1'];
                        }else{
                            $orderdata['parent1commission'] = $agleveldata1['commission1'] * $commission_totalprice * 0.01;
                        }
                    }
                    if($agleveldata2){
                        if($agleveldata2['commissiontype']==1){
                            $orderdata['parent2commission'] = $agleveldata2['commission2'];
                        }else{
                            $orderdata['parent2commission'] = $agleveldata2['commission2'] * $commission_totalprice * 0.01;
                        }
                    }
                    if($agleveldata3){
                        if($agleveldata3['commissiontype']==1){
                            $orderdata['parent3commission'] = $agleveldata3['commission3'];
                        }else{
                            $orderdata['parent3commission'] = $agleveldata3['commission3'] * $commission_totalprice * 0.01;
                        }
                    }
                }
            }
        }
    }

}