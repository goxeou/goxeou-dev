<?php
namespace app\commons;
use think\facade\Db;
use think\facade\Log;
class Aaa {
    
   
   // 1.卖家已发货, 2.已发货，待签收,3. 等待买家确认收货
//4. 买家已付款,5. 待发货,6. 等待卖家发货, 等待买家付款,
//8.交易成功, 9.交易关闭,10. 售后关闭,11. 退款关闭, 
//12.退款成功
    public function sendOrder($aid,$order){ //jushuitan/orders/upload
        $orderid = $order['id'];
        $shopset = Db::name('admin_set')->where('aid',$aid)->find();
        $order = Db::name('shop_order')->where('id',$orderid)->find();
	    $Resdata = \app\commons\Aaa::sendpost($params,'/app/client/get/send');
    } 
    public function refundOrder($aid,$orderid,$refund_order=[]){ //jushuitan/orders/upload
    
        $refund_order= Db::name('shop_refund_order')->where('id',$orderid)->where('aid',$aid)->find();
        $order = Db::name('shop_order')->where('aid',$aid)->where('id',$refund_order['orderid'])->find();
        if ($order['so_id'] && $refund_order['express_com']) {
            $params = [
                'id' =>  $order['so_id'],           // 订单ID
                'expressName' => urlencode($refund_order['express_com']),   // 退款快递名称
                'expressCode' => $refund_order['express_no'],   // 退款快递单号
                'statusDesc' => urlencode($refund_order['refund_reason'])   // 退款状态描述
            ];
    	    $request = \app\commons\Aaa::sendpost($params,'/app/client/update/refund');
    	    if($request['code']==0){
                return ['status'=>1,'msg'=>'操作成功'];
    		}else{
    		    return ['status'=>0,'msg'=>$request['msg']];
    		}
        }
    } 
    
    public function statusOrder($aid,$orderid,$order=[]){ 
        $order = Db::name('shop_order')->where('id',$orderid)->find();
        if (!$order['so_id']) return ['status'=>1,'msg'=>'数造订单不存在'];
        $business = Db::name('business')->where('aid',$aid)->where('id',$order['bid'])->find();
        $sendStatus = '';
        if ($order['status']==1) {
            $sendStatus = '4';
        }elseif ($order['status']==2) {
            $sendStatus = '1';
        }
        $params = [
            'id' => $order['so_id'],            // 订单ID
            'refundStatus' => '12',  // 退款状态
            'isStop' => true,     // 是否暂停
            'autoSend' => 0,     ////是否自动发货
            'autoSendNum' => 0,     //自动发货次数
            'skuCode' => '',
            'sendStatus' => $sendStatus ,    // 发货状态
            'goodsStatus' => '9' // 货品状态
        ];
	    $request = \app\commons\Aaa::sendpost($params,'/app/client/update/status');
        ll($request,'$request');
	    if($request['code']==0){
            return ['status'=>1,'msg'=>'操作成功'];
		}else{
		    return ['status'=>0,'msg'=>$request['msg']];
		}
    } 
    public function closeOrder($aid,$orderid,$order=[]){ 
        $order = Db::name('shop_order')->where('aid',$aid)->where('id',$orderid)->find();
        if (!$order['so_id']) return ['status'=>1,'msg'=>'数造订单不存在'];
        $business = Db::name('business')->where('aid',$aid)->where('id',$order['bid'])->find();
        $params = [
            'shopId' =>$business['autoid'],
            'orders' => [
                'ordered' => $order['so_id'],
                'status' => '9'
            ]
        ];
	    $request = \app\commons\Aaa::sendpost($params,'/app/client/close/order');
	    if($request['code']==0){
            Db::name('shop_order')->where('aid',$aid)->where('id',$orderid)->update(['so_status'=>0]);
            return ['status'=>1,'msg'=>'操作成功'];
		}else{
		    return ['status'=>0,'msg'=>$request['msg']];
		}
    } 
    
    public function checkAddOrder($aid,$order){ //jushuitan/orders/upload
        $orderid = $order['id'];
        $order = Db::name('shop_order')->where('id',$orderid)->find();
        $params = [
            'orderId' => $order['so_id'], // 商城订单编号 必填
        ];
        $request = \app\commons\Aaa::sendpost($params,'/app/client/check/address');
        if($request['code']==0){
			$orderdata = $request['data'];
			if ($orderdata && $orderdata['id']) {
                Db::name('shop_order')->where('aid',$aid)->where('id',$orderid)->update(['add_id'=>$orderdata['id']]);
                return ['status'=>1,'msg'=>'提交成功'];
            }
            return ['status'=>1,'msg'=>'修改成功'];
		}else{
		    return ['status'=>0,'msg'=>$request['msg']];
		}
        return ['status'=>0,'msg'=>'提交失败,原因未知'];
    } 
    public function editOrder($aid,$order){ //jushuitan/orders/upload
        $orderid = $order['id'];
        
        $business = Db::name('business')->where('aid',$aid)->where('id',$order['bid'])->find();
        $order = Db::name('shop_order')->where('id',$orderid)->find();
        $areaArr = explode(',',$order['area2']);
        $params = [
            'id' => $order['so_id'], // 商城订单编号 必填
            'orderid' => $order['ordernum'], // 商城订单编号 必填
            'real_name' =>urlencode($order['linkman']),       // 收货人名称                 必填
            'zone' => urlencode(implode('-',$areaArr)),            // 地区 广东省-广州市-天河区	必填
            'address' => urlencode($order['address']),  
            'mobile' => $order['tel'], 
            'buy_name' => urlencode($business['name']), 
        ];
        $request = \app\commons\Aaa::sendpost($params,'/app/client/update/address');
        if($request['code']==0){
            return ['status'=>1,'msg'=>'修改成功'];
		}else{
		    return ['status'=>0,'msg'=>$request['msg']];
		}
        return ['status'=>0,'msg'=>'提交失败,原因未知'];
    } 
 
    public function submitOrder($aid,$order){ //jushuitan/orders/upload
        $orderid = $order['id'];
        $business = Db::name('business')->where('aid',$aid)->where('id',$order['bid'])->find();
        $order = Db::name('shop_order')->where('id',$orderid)->find();
        if ($order['freight_type']!=0 ) return ['status'=>0,'msg'=>'提交失败,配送方式为自提'];
        if (!$business['autoid']) return ['status'=>0,'msg'=>'提交失败,数造商户ID未绑定'];
        if ($order['status']!=1) return ['status'=>0,'msg'=>'提交失败,订单状态错误'];
        if ($order['so_id']) return ['status'=>0,'msg'=>'订单已存在,取消后不可上传'];
        $oglist = Db::name('shop_order_goods')->where('orderid',$orderid)->select()->toArray();
		$remark = '';
        $formfield = Db::name('freight')->where('id',$order['freight_id'])->find();
        $formdataSet = json_decode($formfield['formdata'],true);
        foreach($formdataSet as $k1=>$v){
            if($v['val1'] == '备注'){
                $message =Db::name('freight_formdata')->where('orderid',$order['id'])->value('form'.$k1);
                $value = explode('^_^',$message);
                if($value[1] !== ''){
                    $remark = $value[1];
                }
                break;
            }
        }
        
        $goodsList = [];
        $product_num = 0;
		foreach($oglist as $og){
		    $product_num +=$og['num'];
	        $goodsList[] = array(
	            'sku' => $og['barcode'],// 产品SKU 必填
	            'goods_title' => urlencode($og['ggname']),// 产品名称 必填
                'goods_price' => $og['sell_price'], // 购买单价 必填
                'buy_num' => $og['num'], // 购买数量 必填
                'goods_sn' => $og['barcode'], // 产品编号 必填
                "goods_url"=> "",
                "goods_sn"=> "",
                "goods_status"=> "",
                "goods_color"=> urlencode($og['ggname']),
                "size"=> "",
                "size_desc"=> "",
                'total_price' => $og['totalprice'], // 小计金额 必填
	        );
		} 
		if (!$goodsList)  return ['status'=>0,'msg'=>'提交失败,订单商品未绑定编码'];
		$areaArr = explode(',',$order['area2']);
        $params = [
            'orderid' => $order['ordernum'], // 商城订单编号 必填
            'create_time' => date('Y-m-d H:i:s',$order['paytime']), // 支付时间 2024-12-03 10:11:13 字符串类型 必填
            'unix_timestamp' => date($order['paytime']), // 支付时间unix类型 必填
            'detail' => m_url('/pages/index/index'), // 支付时间unix类型 必填
            'real_name' =>urlencode($order['linkman']),       // 收货人名称                 必填
            'zone' => urlencode(implode('-',$areaArr)),            // 地区 广东省-广州市-天河区	必填
            'address' => urlencode($order['address']),  
            'mobile' => $order['tel'],      
            'goods' => $goodsList, // 产品信息
            'party_name' => urlencode($order['title']), // 下单名称 必填
            'money' => number_format($order['product_price'],2), // 订单状态 必填
            'status' => urlencode('买家已付款'), // 订单状态 必填
            'pay_money' =>number_format( $order['totalprice'],2), // 支付金额 必填
            'bewrite' => urlencode($order['remark']), // 商家备注 可选    
            "total_goods"=> $product_num,
            'freight' =>  urlencode($order['freight_text']), // 订单总金额 必填
            "seller_remarks"=> urlencode($remark),
            "refund_status"=>  0,
            'total_price' =>  number_format($order['totalprice'],2), // 订单总金额 必填
            'product_num' => $product_num, // 订单产品数量 必填
            'sku_num' => count($oglist), // SKU数量 必填
            'pay_canal' => urlencode($order['paytype']), // 支付渠道 必填
            'pay_status' => urlencode('买家已付款'), // 支付状态 必填
            'shopId' => $business['autoid'], // 店铺ID 必填
        ];
        $data = [];
        $data['orders']  =  [$params];
        $data['shopId']  =  $business['autoid'];
        $data['shopName']  =  urlencode($business['name']);
        $data['platformId']  =  $business['platformId'];
        $data['brandId']  =  $business['brandId'];
       // return $data;
        $request = \app\commons\Aaa::sendpost($data,'/app/client/create/order');
        if($request['code']==0){
			$orderdata = $request['data'];
			//修改订单的聚水潭内部单号
			if ($orderdata && $orderdata['id']) {
                Db::name('shop_order')->where('aid',$aid)->where('id',$orderid)->update(['so_id'=>$orderdata['id'],'so_status'=>1]);
                return ['status'=>1,'msg'=>'提交成功'];
            }
		}
	    Db::name('shop_order')->where('aid',$aid)->where('id',$orderid)->update(['so_status'=>4]);
        return ['status'=>0,'msg'=>'提交失败,原因未知'];
	} 
    public function sendpost($params,$suburl) //每月分红 
    {
        $accessToken = 'VW9ZrUCD1U7S68rR3tj39D3f19H97r70#0ce09a599334ed87b30a63a42308501b';
		$headrs = array('content-type: application/json;charset=UTF-8','App-Type:5','Authorization:'.$accessToken);
		$secret = 'S8KEiewlso23Swi21n943koxm56ckdKWLasi';
        $orderStr  = json_encode($params,JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        	 ll($orderStr,'$res')	;
        $orderStr  = base64_encode($orderStr);
		$string1 = "key=".$secret.'&params='.$orderStr;	
	    $sign = strtoupper(md5($string1));
		$url = 'https://shuzao.12fz.com'.$suburl;
		$data = [];
		$data['key'] = $secret;
		$data['sign'] = $sign;
		$data['params'] = $orderStr;
		$data = json_encode($data,JSON_UNESCAPED_UNICODE);
		$res = curl_post($url,$data,'',$headrs);

		if ($suburl!='/app/client/shoplist') {
		   	Log::write([
                '$suburl'=>$suburl,
                '$params'=> json_encode($params,JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'file'=>$res,
            ]);
		}
		$res = json_decode($res,true);
        return $res;
    }
    
    public function Credit1Money($aid) { //每天涨价SHL 
		//$total = 0 + Db::name('admin_credit1log')->where('aid',$aid)->sum('credit1');
		$set = Db::name('admin_set')->field('credit1_money')->where('aid',$aid)->find();
		return $set['credit1_money'];
	}
	public static function LvCreate($aid,$mid,$order,$leveldata){
        $orderid = $order['id'];
	    $member = Db::name('member')->where('id',$mid)->find();
        $levellist = Db::name('member_level')->where('aid',$aid)->column('*','id');
       
		$pids = $member['path'];
		if($pids){  
		    $parentList = Db::name('member')->where('id','in',$pids)->order(Db::raw('field(id,'.$pids.')'))->select()->toArray();
	    	if($parentList){
	    	    $parentList = array_reverse($parentList);
	    	}
		}
		$_pids = $member['_path'];
		if($_pids){  
		    $_parentList = Db::name('member')->where('id','in',$_pids)->order(Db::raw('field(id,'.$_pids.')'))->select()->toArray();
	    	if($_parentList){
	    	    $_parentList = array_reverse($_parentList);
	    	}
		}
		$commissiondata = json_decode($leveldata['commissiondata7'],true);
		if($member['pid']){
			$parent1 = Db::name('member')->where('id',$member['pid'])->find();
			$parent1commission = $commissiondata[$parent1['levelid']]['commission1'];
	    	\app\commons\Member::addcommission($aid, $parent1['id'], $member['id'], $parent1commission, '直推-会员升级奖励');
	    	
			if ($parent1['pid']) {
			    $parent2 = Db::name('member')->where(['aid' => $aid, 'id' =>$parent1['pid']])->find();
				$agleveldata2 = $levellist[$parent2['levelid']];
				if ($agleveldata2 && $agleveldata2['level_type'] == 2) {
				    $boss_pingji_money = $commissiondata[$agleveldata2['id']]['commission1pj']*$parent1commission*0.01;
				    \app\commons\Member::addcommission($aid, $parent2['id'], $member['id'], $boss_pingji_money, '直推-会员升级奖励-平级');
			
				}
			}
		}
    	$fenhongdata1 = json_decode($leveldata['fenhongdata1'],true);
        if ($fenhongdata1 && $_parentList) {
			foreach($_parentList as $k=>$parent) {
			    if ($k >= 10) break;
				$leveldata = $levellist[$parent['levelid']];
				$comdata = $fenhongdata1[$parent['levelid']];
				if($comdata && $leveldata['level_type'] == 2) {
					$boss_money = $comdata['commission'];
					\app\commons\Member::addcommission($aid, $parent['id'], $member['id'], $boss_money, '团队-会员升级奖励');
					if ($parent['pid']) {
					    $parent1 = Db::name('member')->where(['aid' => $aid, 'id' =>$parent['pid']])->find();
						$leveldata1 = $levellist[$parent1['levelid']];
						if ($leveldata1 && $leveldata1['level_type'] == 2) {
							$boss_pingji_money = $comdata['commissionpj']*$boss_money*0.01;
							\app\commons\Member::addcommission($aid, $parent1['id'], $member['id'], $boss_pingji_money, '团队-会员升级奖励-平级');
						}
					}
					break;
				}
			}
		}
		
    }
    public static function Create($aid,$mid,$order,$oglist){
        $orderid = $order['id'];
	    $member = Db::name('member')->where('id',$mid)->find();
        $levellist = Db::name('member_level')->where('aid',$aid)->column('*','id');
        // $teamlist = Db::name('member_team')->where('aid',$aid)->column('*','id');
		$pids = $member['path'];
		if($pids){  
		    $parentList = Db::name('member')->where('id','in',$pids)->order(Db::raw('field(id,'.$pids.')'))->select()->toArray();
	    	if($parentList){
	    	    $parentList = array_reverse($parentList);
	    	}
		}
		$_pids = $member['_path'];
		if($_pids){  
		    $_parentList = Db::name('member')->where('id','in',$_pids)->order(Db::raw('field(id,'.$_pids.')'))->select()->toArray();
	    	if($_parentList){
	    	    $_parentList = array_reverse($_parentList);
	    	}
		}
		foreach($oglist as $og){
	        $product = Db::name('shop_product')->where('id',$og['proid'])->find();
        
        	if($product['commissionset']==7){
				$commissiondata = json_decode($product['commissiondata7'],true);
				if($og['parent1']){
				    $ogupdate = [];
					$parent1 = Db::name('member')->where('id',$og['parent1'])->find();
					$agleveldata1 = $levellist[$parent1['levelid']];
					$parent1commission = $commissiondata[$agleveldata1['id']]['commission1']*$og['num'];
					$ogupdate['parent1commission'] = $parent1commission;
					Db::name('shop_order_goods')->where('id',$og['id'])->update($ogupdate);
			    	//self::fafang($aid,$parent1['id'],$order['mid'],$parent1commission,'下级购买产品奖励',$agleveldata1);
			    	Db::name('member_commission_record')->insert(['aid'=>$aid,'mid'=>$parent1['id'],'frommid'=>$og['mid'],'orderid'=>$orderid,'ogid'=>$og['id'],'type'=>'shop','commission'=>$parent1commission,'remark'=>t('下一级').'购买商品奖励','createtime'=>time()]);
					if ($parent1['pid']) {
					    $parent2 = Db::name('member')->where(['aid' => $aid, 'id' =>$parent1['pid']])->find();
						$agleveldata2 = $levellist[$parent2['levelid']];
						if ($agleveldata2 && $agleveldata2['level_type'] == 2) {
						    $boss_pingji_money = $commissiondata[$agleveldata2['id']]['commission1pj']*$parent1commission*0.01;
						    Db::name('member_commission_record')->insert(['aid'=>$aid,'mid'=>$parent2['id'],'frommid'=>$og['mid'],'orderid'=>$orderid,'ogid'=>$og['id'],'type'=>'shop','commission'=>$boss_pingji_money,'remark'=>t('下一级').'购买商品奖励伯乐奖','createtime'=>time()]);
							//self::fafang($aid,$parent2['id'],$order['mid'],$boss_pingji_money,$order['ordernum'].'培育伯乐奖',$agleveldata2);
						}
					}
				}
				
        	}elseif($product['commissionset']==6){
		    	$commissiondata = json_decode($product['commissiondata6'],true);
		    	$ogupdate = [];
		    	$num = $og['num'];
		    	$fengdan_num = 3;
		        if($og['parent1']){
					$parent1 = Db::name('member')->where('id',$og['parent1'])->find();
					$agleveldata1 = $levellist[$parent1['levelid']];
					$dannum = Db::name('shop_order_goods')->where('aid',$aid)->where('orderid','<>',$orderid)->where('commissionset',6)->where('status','in','1,2,3')->where("parent1",$og['parent1'])->sum('num');
					$ogupdate['parent1commission'] = 0;
					for($i=0;$i<$num;$i++){
						$thisdannum = ($dannum+1+$i)%$fengdan_num;
						if($thisdannum==1) {
						    $ogupdate['parent1commission'] += round($commissiondata[$agleveldata1['id']]['commission1'] * $og['sell_price']*0.01,2);
						}elseif($thisdannum==2) {
						    $ogupdate['parent1commission'] += round($commissiondata[$agleveldata1['id']]['commission2'] * $og['sell_price']*0.01,2);
						}elseif($thisdannum==0){
						     $ogupdate['parent1commission'] += round($commissiondata[$agleveldata1['id']]['commission3'] * $og['sell_price']*0.01,2);
						}
					}
				}
				if($ogupdate){
					Db::name('shop_order_goods')->where('id',$og['id'])->update($ogupdate);
					if($og['parent1'] && $ogupdate['parent1commission']){
				    	Db::name('member_commission_record')->insert(['aid'=>$aid,'mid'=>$og['parent1'],'frommid'=>$og['mid'],'orderid'=>$orderid,'ogid'=>$og['id'],'type'=>'shop','commission'=>$ogupdate['parent1commission'],'remark'=>t('下级').'购买商品奖励','createtime'=>time()]);
					}
				}
        	}
        	if ($product['commissionsettype']!=2) {
            	$fenhongdata1 = json_decode($product['fenhongdata1'],true);
                if ($fenhongdata1 && $_parentList) {
    				foreach($_parentList as $k=>$parent) {
    				    if ($k>=10) break;
    					$leveldata = $levellist[$parent['levelid']];
    					$comdata = $fenhongdata1[$parent['levelid']];
    					if($comdata && $leveldata['level_type'] == 2) {
    						$boss_money = $comdata['commission']*$og['num'];
    						Db::name('member_commission_record')->insert(['aid'=>$aid,'mid'=>$parent['id'],'frommid'=>$og['mid'],'orderid'=>$orderid,'ogid'=>$og['id'],'type'=>'shop','commission'=>$boss_money,'remark'=>'裂变退休收益','createtime'=>time()]);
    						if ($parent['pid']) {
    						    $parent1 = Db::name('member')->where(['aid' => $aid, 'id' =>$parent['pid']])->find();
        						$leveldata1 = $levellist[$parent1['levelid']];
        						if ($leveldata1 && $leveldata1['level_type'] == 2) {
        							$boss_pingji_money = $comdata['commissionpj']*$boss_money*0.01;
        							Db::name('member_commission_record')->insert(['aid'=>$aid,'mid'=>$parent1['id'],'frommid'=>$og['mid'],'orderid'=>$orderid,'ogid'=>$og['id'],'type'=>'shop','commission'=>$boss_pingji_money,'remark'=>'裂变退休收益伯乐奖','createtime'=>time()]);
        						}
    						}
    						break;
    					}
    				}
    			}
    		
        	}
		}
    }
    public static function checkparent($aid,$mid,$formid,$commission,$ordernum){
		if($commission > 0 ) {
		    $parent1 = Db::name('member')->where('id',$mid)->find();
			$agleveldata1 = self::getlevel($aid,$parent1['levelid']);
			if ($parent1 && $agleveldata1['dongrate']>0) {
			    $commission = round($commission*$agleveldata1['dongrate']*0.01,2);
			    self::fafang($aid,$parent1['id'],$formid,$commission,$ordernum.'培育伯乐奖');
			}
		}
	}
	public static function fafang($aid,$mid,$formid,$commission,$remark,$level=[]){
		if($commission > 0 ) {
		    
         	\app\commons\Member::addcommission($aid,$mid,$formid,$commission,$remark);
		}
	}
	public static function edit2($aid,$mid) {
		$member =  Db::name('member')->where(['aid' => $aid, 'id' => $mid])->find();
		if(empty($member)) return ['status'=>0,'msg'=>t('会员').'不存在'];
		if($member['_pid_time']) return ['status'=>0,'msg'=>'存在排位'];
		$updata = [];
		$updata['_pid_time'] = time();
		if($member['pid']) {
			$parent = \app\commons\Aaa::getmember($aid,$member['pid']);
			if(!$parent) {
				$updata['_pid'] = 0;
				$updata['_path'] = '';
			} else {
				$levelinfo = $parent['levelinfo'];
				if ($levelinfo['level_type']==2) {
					$updata['_pid'] = $parent['id'];
					if($parent['_path']) {
						$updata['_path'] = $parent['_path'] . ',' .$parent['id'];
					} else {
						$updata['_path'] = ''.$parent['id'];
					}
				} elseif ($parent['_pid']) {
					$updata['_pid'] = $parent['_pid'];
					$updata['_path'] = $parent['_path'];
				}
			}
		} else {
			$updata['_pid'] = 0;
			$updata['_path'] = '';
		}
		Db::name('member')->where('aid',$aid)->where('id',$mid)->update($updata);
	}
	public static  function getlevel($aid,$levelid) {
		$levelinfo =  Db::name('member_level')->where(['aid' => $aid, 'id' => $levelid])->find();
		return $levelinfo;
	}
	public static  function getmember($aid,$mid) {
		$member =  Db::name('member')->where(['aid' => $aid, 'id' => $mid])->find();
		if ($member['levelid']) {
			$member['levelinfo'] = \app\commons\Aaa::getlevel($aid,$member['levelid']);
		}
		return $member;
	}
}