<?php


namespace app\controllers;
use think\Exception;
use think\facade\Db;
use think\facade\Log;

class ApiOrder extends ApiCommon
{
	public function initialize(){
		parent::initialize();
		$this->checklogin();
	}
	public function orderlist(){
		$st = input('param.st');
		if(!input('?param.st') || $st === ''){
			$st = 'all';
		}
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['mid','=',mid];
        if(input('param.keyword')){
            $keywords = input('param.keyword');
            $orderids = Db::name('shop_order_goods')->where($where)->where('name','like','%'.input('param.keyword').'%')->column('orderid');
            if(!$orderids){
                $where[] = ['ordernum|title', 'like', '%'.$keywords.'%'];
            }
        }

        $where[] = ['delete','=',0];
		if($st == 'all'){

		}elseif($st == '0'){
			$where[] = ['status','=',0];
		}elseif($st == '1'){
			$where[] = ['status','=',1];
		}elseif($st == '2'){
			$where[] = ['status','=',2];
		}elseif($st == '3'){
			$where[] = ['status','=',3];
		}elseif($st == '10'){
			$where[] = ['refund_status','>',0];
		}
		$pernum = 10;
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$datalist = Db::name('shop_order')->where($where);
        if($orderids){
            $datalist->where(function ($query) use ($orderids,$keywords){
                $query->whereIn('id',$orderids)->whereOr('ordernum|title','like','%'.$keywords.'%');
            });
        }
        $datalist = $datalist->page($pagenum,$pernum)->order('id desc')->select()->toArray();
        if(!$datalist) $datalist = array();
        $supplierName = '';
        $shopset = Db::name('shop_sysset')->where('aid',aid)->find();
		foreach($datalist as $key=>$v){

			$can_collect = true;
			$datalist[$key]['can_collect'] = $can_collect;

            $datalist[$key]['prolist'] = [];
			$prolist = Db::name('shop_order_goods')->where('orderid',$v['id'])->select()->toArray();
			$isjici = 0;
			foreach ($prolist as $pk=>$pv){
				if($pv['hexiao_code']) $isjici++;
			}
			if($isjici >= count($prolist)) $datalist[$key]['hexiao_qr'] = '';
			if($prolist) $datalist[$key]['prolist'] = $prolist;
			$datalist[$key]['procount'] = Db::name('shop_order_goods')->where('orderid',$v['id'])->sum('num');
			$datalist[$key]['refundnum'] = Db::name('shop_order_goods')->where('orderid',$v['id'])->sum('refund_num');

			if($v['bid']!=0){
                $business = Db::name('business')->where('aid',aid)->where('id',$v['bid'])->field('id,name,logo')->find();
				if(!$business) $business = ['id'=>$v['bid']];
				$commentdp = Db::name('business_comment')->where('orderid',$v['id'])->where('aid',aid)->where('mid',mid)->find();
				if($commentdp){
					$datalist[$key]['iscommentdp'] = 1;
				}else{
					$datalist[$key]['iscommentdp'] = 0;
				}
			} else {
                $business = Db::name('admin_set')->where('aid',aid)->field('name,logo')->find();
            }
            $isNeedCard = 0;//是否需要上传海关所需身份证
            $datalist[$key]['isNeedCard'] = $isNeedCard;
            $datalist[$key]['binfo'] = $business;

            $refundOrder = Db::name('shop_refund_order')->where('refund_status','>',0)->where('aid',aid)->where('orderid',$v['id'])->count();
            $datalist[$key]['refundCount'] = $refundOrder;
            //发票
            $datalist[$key]['invoice'] = 0;
            if($v['bid']) {
                $datalist[$key]['invoice'] = Db::name('business')->where('aid',aid)->where('id',$v['bid'])->value('invoice');
            } else {
                $datalist[$key]['invoice'] = Db::name('admin_set')->where('aid',aid)->value('invoice');
            }
			$datalist[$key]['tips'] = '';
			//独立订单是否允许退款
            $orderCanRefund = 1;
            $datalist[$key]['order_can_refund'] = $orderCanRefund;

            $needWxpaylog = false;
            //发货信息录入 微信小程序+微信支付
            if($v['platform'] == 'wx' && $v['paytypeid'] == 2){
                $needWxpaylog = true;
            }
            if($needWxpaylog){
                $wxpaylog = Db::name('wxpay_log')->where('aid',aid)->where('ordernum',$v['ordernum'])->where('tablename','shop')->field('ordernum,mch_id,transaction_id,openid,is_upload_shipping_info')->find();
                $datalist[$key]['wxpaylog'] = $wxpaylog;
            }
            }
		$rdata = [];
		$rdata['datalist'] = $datalist;
		$rdata['codtxt'] = $shopset['codtxt'];
		$rdata['canrefund'] = $shopset['canrefund'];
		$rdata['st'] = $st;
		$rdata['showprice_dollar'] = false;
		$mendian_no_select = 0;
        $rdata['mendian_no_select'] = $mendian_no_select;
		return $this->json($rdata);
	}
	public function detail(){
		$detail = Db::name('shop_order')->where('id',input('param.id/d'))->where('aid',aid)->where('mid',mid)->find();
		if(!$detail) return $this->json(['status'=>0,'msg'=>'订单不存在']);

		$can_collect = true;
		$detail['can_collect'] = $can_collect;
		$detail['formdata'] = \app\models\Freight::getformdata($detail['id'],'shop_order');
		$detail['procount'] = Db::name('shop_order_goods')->where('orderid',$detail['id'])->sum('num');
		$detail['refundnum'] = Db::name('shop_order_goods')->where('orderid',$detail['id'])->sum('refund_num');

		$storeinfo = [];
		$storelist = [];
		if($detail['freight_type'] == 1){
			if($detail['mdid'] == -1){
				$freight = Db::name('freight')->where('id',$detail['freight_id'])->find();
				if($freight && $freight['hxbids']){
					if($detail['longitude'] && $detail['latitude']){
						$orderBy = Db::raw("({$detail['longitude']}-longitude)*({$detail['longitude']}-longitude) + ({$detail['latitude']}-latitude)*({$detail['latitude']}-latitude) ");
					}else{
						$orderBy = 'sort desc,id';
					}
					$storelist = Db::name('business')->where('aid',$freight['aid'])->where('id','in',$freight['hxbids'])->where('status',1)->field('id,name,logo pic,longitude,latitude,address')->order($orderBy)->select()->toArray();
					foreach($storelist as $k2=>$v2){
						if($detail['longitude'] && $detail['latitude'] && $v2['longitude'] && $v2['latitude']){
							$v2['juli'] = '距离'.getdistance($detail['longitude'],$detail['latitude'],$v2['longitude'],$v2['latitude'],2).'千米';
						}else{
							$v2['juli'] = '';
						}
						$storelist[$k2] = $v2;
					}
				}
			}else{
				$storeinfo = Db::name('mendian')->where('id',$detail['mdid'])->field('id,name,address,longitude,latitude')->find();
			}
		}
        $mendian_no_select = 0;
        $mendianArr = [];
        if($mendian_no_select){
            $pro_ids = Db::name('shop_order_goods')->where('orderid',$detail['id'])->column('proid');
            $mendian_ids = Db::name('shop_product')->where('id','in',$pro_ids)->column('bind_mendian_ids');
            $mendian_ids = explode(',',implode(',',$mendian_ids));
            if(in_array('-1',$mendian_ids)){
                $limit_mendianids = [];
            }else{
                $limit_mendianids = $mendian_ids;
            }
            $whereb = [];
            $whereb[] = ['aid','=',aid];
            $whereb[] = ['status','=',1];
            if($limit_mendianids){
                $whereb[] = ['id','in',$limit_mendianids];
            }
            $mendianArr = Db::name('mendian')->where($whereb)->order($orderBy)->field('id,name,pic,longitude,latitude,address')->select()->toArray();
            $address = Db::name('member_address')->where('aid',aid)->where('mid',mid)->order('isdefault desc,id desc')->find();
            if(!$address) $address = [];
            $longitude = $address['longitude'];
            $latitude = $address['latitude'];
            foreach($mendianArr as $k2=>$v2){
                //限定显示门店
                if($longitude && $latitude){
                    $v2['juli'] = '距离'.getdistance($longitude,$latitude,$v2['longitude'],$v2['latitude'],2).'千米';
                }else{
                    $v2['juli'] = '';
                }
                $mendianArr[$k2] = $v2;
            }
        }


		if($detail['bid'] > 0){
			$detail['binfo'] = Db::name('business')->where('aid',aid)->where('id',$detail['bid'])->field('id,name,logo')->find();
			if(!$detail['binfo']) $detail['binfo'] = [];
			$iscommentdp = 0;
			$commentdp = Db::name('business_comment')->where('orderid',$detail['id'])->where('aid',aid)->where('mid',mid)->find();
			if($commentdp) $iscommentdp = 1;
		}else{
			$iscommentdp = 1;
		}

		$prolist = Db::name('shop_order_goods')->where('orderid',$detail['id'])->select()->toArray();
		$isjici = 0;
		foreach ($prolist as $pk=>$pv){
			if($pv['hexiao_code']) $isjici++;
		}
		if($isjici >= count($prolist)) $detail['hexiao_qr'] = '';

		$field = 'comment,autoclose,canrefund';
		$shopset = Db::name('shop_sysset')->where('aid',aid)->field($field)->find();


		if($detail['status']==0 && $shopset['autoclose'] > 0 && $detail['paytypeid'] != 5){
			$lefttime = strtotime($detail['createtime']) + $shopset['autoclose']*60 - time();
			if($lefttime < 0) $lefttime = 0;
		}else{
			$lefttime = 0;
		}

		//退款记录
        $refundOrder = Db::name('shop_refund_order')->where('refund_status','>',0)->where('aid',aid)->where('orderid',$detail['id'])->count();
        $refundingMoneyTotal = Db::name('shop_refund_order')->where('refund_status','in',[1,4])->where('aid',aid)->where('orderid',$detail['id'])->sum('refund_money');
        $refundedMoneyTotal = Db::name('shop_refund_order')->where('refund_status','=',2)->where('aid',aid)->where('orderid',$detail['id'])->sum('refund_money');
		$detail['refundCount'] = $refundOrder;
        $detail['refundingMoneyTotal'] = $refundingMoneyTotal;
        $detail['refundedMoneyTotal'] = $refundedMoneyTotal;
		//弃用
		if($detail['field1']){
			$detail['field1data'] = explode('^_^',$detail['field1']);
		}
		if($detail['field2']){
			$detail['field2data'] = explode('^_^',$detail['field2']);
		}
		if($detail['field3']){
			$detail['field3data'] = explode('^_^',$detail['field3']);
		}
		if($detail['field4']){
			$detail['field4data'] = explode('^_^',$detail['field4']);
		}
		if($detail['field5']){
			$detail['field5data'] = explode('^_^',$detail['field5']);
		}
		if($detail['freight_type']==11 && $detail['freight_content']){
			$detail['freight_content'] = json_decode($detail['freight_content'],true);
		}

		if($detail['checkmemid']){
			$detail['checkmember'] = Db::name('member')->field('id,nickname,headimg,realname,tel')->where('id',$detail['checkmemid'])->find();
		}else{
			$detail['checkmember'] = [];
		}
		$detail['payaftertourl'] = '';
		$detail['payafterbtntext'] = '';
		if(getcustom('payaftertourl') && in_array($detail['status'],['1','2','3'])){
			foreach($prolist as $pro){
				$product = Db::name('shop_product')->where('id',$pro['proid'])->find();
				if($product['payaftertourl'] && $product['payafterbtntext']){
					$detail['payaftertourl'] = $product['payaftertourl'];
					$detail['payafterbtntext'] = $product['payafterbtntext'];
					if(platform == 'mp' && strpos($product['payaftertourl'],'miniProgram::') === 0){
						$afterurl = explode('|',str_replace('miniProgram::','',$detail['payaftertourl']));
						$detail['payafter_username'] = $afterurl[2];
						$appinfo = \app\commons\System::appinfo(aid,platform);
						$detail['payafter_path'] = $afterurl[1].(strpos($afterurl[1],'?')!==false ? '&' : '?') .'appid='.$appinfo['appid'].'&uid='.mid.'&ordernum='.$detail['ordernum'];
					}
				}
			}
		}
        $detail['message'] = \app\models\ShopOrder::checkOrderMessage($detail['id'],$detail);

        $detail['createtime'] = $detail['createtime'] ? date('Y-m-d H:i:s',$detail['createtime']) : '';
		$detail['collect_time'] = $detail['collect_time'] ? date('Y-m-d H:i:s',$detail['collect_time']) : '';
		$detail['paytime'] = $detail['paytime'] ? date('Y-m-d H:i:s',$detail['paytime']) : '';
		$detail['refund_time'] = $detail['refund_time'] ? date('Y-m-d H:i:s',$detail['refund_time']) : '';
		$detail['send_time'] = $detail['send_time'] ? date('Y-m-d H:i:s',$detail['send_time']) : '';
		$rdata = [];
		$rdata['status'] = 1;
        //订单是否允许退款【在全局设置的基础上再控制退款】
        $detail['order_can_refund'] = 1;
        $rdata['detail'] = $detail;
		$rdata['iscommentdp'] = $iscommentdp;
		$rdata['prolist'] = $prolist;
		$rdata['shopset'] = $shopset;
		$rdata['storeinfo'] = $storeinfo;
		$rdata['storelist'] = $storelist;
        $rdata['mendianArr'] = $mendianArr;
		$rdata['lefttime'] = $lefttime;
		$rdata['codtxt'] = Db::name('shop_sysset')->where('aid',aid)->value('codtxt');

        if(getcustom('pay_transfer')){
            //转账汇款
            if($detail['paytypeid'] == 5) {
                $set = Db::name('admin_set')->where('aid',aid)->find();
                $pay_transfer = 1;
                $pay_transfer_info['pay_transfer_account_name'] = $pay_transfer ? $set['pay_transfer_account_name'] : '';
                $pay_transfer_info['pay_transfer_account'] = $pay_transfer ? $set['pay_transfer_account'] : '';
                $pay_transfer_info['pay_transfer_bank'] = $pay_transfer ? $set['pay_transfer_bank'] : '';
                $pay_transfer_info['pay_transfer_desc'] = $pay_transfer ? $set['pay_transfer_desc'] : '';
                $pay_transfer_info['pay_transfer_qrcode'] = $pay_transfer ? $set['pay_transfer_qrcode'] : '';
                $pay_transfer_info['pay_transfer_qrcode_arr'] = $set['pay_transfer_qrcode'] ? explode(',',$set['pay_transfer_qrcode']) : [];
                $rdata['pay_transfer_info'] = $pay_transfer_info;
                $payorder = Db::name('payorder')->where('id',$detail['payorderid'])->where('aid',aid)->find();
                If($payorder) {
                    If($payorder['check_status'] === 0) {
                        $payorder['check_status_label'] = '待审核';
                    }Elseif($payorder['check_status'] == 1) {
                        $payorder['check_status_label'] = '通过';
                    }Elseif($payorder['check_status'] == 2) {
                        $payorder['check_status_label'] = '驳回';
                    }Else{
                        $payorder['check_status_label'] = '未上传';
                    }
                }
                $rdata['payorder'] = $payorder ? $payorder : [];
            }
        }
		$rdata['showprice_dollar'] = false;
		//发票
        $rdata['invoice'] = 0;
        if($detail['bid']) {
            $rdata['invoice'] = Db::name('business')->where('aid',aid)->where('id',$detail['bid'])->value('invoice');
        } else {
            $rdata['invoice'] = Db::name('admin_set')->where('aid',aid)->value('invoice');
        }

		//定制 查看是否有工单可提交
		$rdata['isworkorder'] = 0;
		$needWxpaylog = false;
        //发货信息录入 微信小程序+微信支付
        if($detail['platform'] == 'wx' && $detail['paytypeid'] == 2){
            $needWxpaylog = true;
        }
        if($needWxpaylog){
            $wxpaylog = Db::name('wxpay_log')->where('aid',aid)->where('ordernum',$detail['ordernum'])->where('tablename','shop')->field('ordernum,mch_id,transaction_id,openid,is_upload_shipping_info')->find();
            $rdata['detail']['wxpaylog'] = $wxpaylog;
        }

        $rdata['mendian_no_select'] = $mendian_no_select;
		return $this->json($rdata);
	}

	public function invoice()
    {
        $id = input('param.id/d');
        $post = input('post.');
        $order_type = input('param.type');

        $detail = Db::name($order_type.'_order')->where('id',$id)->where('aid',aid)->where('mid',mid)->find();
        if(empty($detail)) return $this->json(['status'=>0,'msg'=>'订单不存在']);
        if($detail['refund_money']){
        	$detail['totalprice'] -= $detail['refund_money'];
        }
        if($detail['totalprice']<0){
        	$detail['totalprice'] = 0;
        }

        $detail['createtime'] = $detail['createtime'] ? date('Y-m-d H:i:s',$detail['createtime']) : '';
        //发票
        $invoice = [];
        if($detail['bid']) {
            $invoice = Db::name('business')->where('aid',aid)->where('id',$detail['bid'])->find();
        } else {
            $invoice = Db::name('admin_set')->where('aid',aid)->find();
        }
        if(!$invoice['invoice']) return $this->json(['status'=>0,'msg'=>'未开启发票功能']);

        $info = Db::name('invoice')->where('order_type', $order_type)->where('orderid', $detail['id'])->find();

        if($post) {
            if($info['status'] == 1) {
                return $this->json(['status'=>0,'msg'=>'当前状态不可修改']);
            }
            $data = [
                'order_type' => $order_type,
                'orderid' => $detail['id'],
                'ordernum' => $detail['ordernum'],
                'type' => $post['formdata']['invoice_type'] ? $post['formdata']['invoice_type'] : 1,
                'invoice_name' => $post['formdata']['invoice_name'] ? $post['formdata']['invoice_name'] : '个人',
                'name_type' => $post['formdata']['name_type'] ? $post['formdata']['name_type'] : 1,
                'tax_no' => $post['formdata']['tax_no'] ? $post['formdata']['tax_no'] : '',
                'address' => $post['formdata']['address'] ? $post['formdata']['address'] : '',
                'tel' => $post['formdata']['tel'] ? $post['formdata']['tel'] : '',
                'bank_name' => $post['formdata']['bank_name'] ? $post['formdata']['bank_name'] : '',
                'bank_account' => $post['formdata']['bank_account'] ? $post['formdata']['bank_account'] : '',
                'mobile' => $post['formdata']['mobile'] ? $post['formdata']['mobile'] : '',
                'email' => $post['formdata']['email'] ? $post['formdata']['email'] : ''
            ];
            if(empty($info)) {
                $data['aid'] = aid;
                $data['bid'] = $detail['bid'];
                $data['create_time'] = time();
                Db::name('invoice')->insertGetId($data);
            } else {
                Db::name('invoice')->where('order_type', $order_type)->where('orderid', $detail['id'])->update($data);
            }

            return $this->json(['status'=>1,'msg'=>'操作成功']);
        }
        if($info) {
            if($info['status'] === 0) {
                $info['status_label'] = '待审核';
            }elseif($info['status'] == 1) {
                $info['status_label'] = '通过';
            }elseif($info['status'] == 2) {
                $info['status_label'] = '驳回';
            }else{
                $info['status_label'] = '未申请';
            }
        }
        $rdata['status'] = 1;
        $rdata['detail'] = $detail;
        $rdata['invoice'] = $info;
        $rdata['invoice_type'] = explode(',', $invoice['invoice_type']);
        return $this->json($rdata);
    }
	public function logistics(){
		$get = input('param.');
		if($get['express_com'] == '同城配送'){
		    if($get['type'] == 'express_wx'){
                $psorder = Db::name('express_wx_order')->where('id',$get['express_no'])->find();
                $psuser=['realname'=>$psorder['rider_name'],'tel'=>$psorder['rider_phone'],'latitude' => $psorder['rider_lat'],'longitude'=>$psorder['rider_lng']];
                $orderinfo = json_decode($psorder['orderinfo'],true);
                $binfo = json_decode($psorder['binfo'],true);
                $prolist = json_decode($psorder['prolist'],true);
                if($psorder['distance']> 1000){
                    $psorder['juli'] = round($psorder['distance']/1000,1);
                    $psorder['juli_unit'] = 'km';
                }else{
                    $psorder['juli']=$psorder['distance'];
                    $psorder['juli_unit'] = 'm';
                }
                //查询骑行距离
                $mapqq = new \app\commons\MapQQ();
                $bicycl = $mapqq->getDirectionDistance($psorder['orderinfo']['longitude'],$psorder['orderinfo']['latitude'],$psuser['longitude'],$psuser['latitude'],1);
                if($bicycl && $bicycl['status']==1){
                    $juli2 = $bicycl['distance'];
                }else{
                    $juli2 = getdistance($psorder['orderinfo']['longitude'],$psorder['orderinfo']['latitude'],$psuser['longitude'],$psuser['latitude'],1);
                }
                $psorder['juli2'] = $juli2;
                if($juli2> 1000){
                    $psorder['juli2'] = round($juli2/1000,1);
                    $psorder['juli2_unit'] = 'km';
                }else{
                    $psorder['juli2_unit'] = 'm';
                }
            }else{
                $psorder = Db::name('peisong_order')->where('id',$get['express_no'])->find();
                if($psorder['psid'] == -2){
                    $psuser = ['realname'=>'','tel'=>'','latitude'=>'','longitude'=>''];
                    if(getcustom('express_maiyatian')) {
                        //查询关联表
                        $myt = Db::name('peisong_order_myt')->where('poid',$psorder['id'])->where('aid',aid)->find();
                        if($myt){
                            $psuser = [];
                            $psuser['realname'] = $myt['rider_name'];
                            $psuser['tel']      = $myt['rider_phone'];
                            $psuser['latitude'] = '';
                            $psuser['longitude']= '';
                        }
                        if($psorder['status']>=1 && $psorder['status']<=3){
                            //更新骑手位置
                            $res_location = \app\customs\MaiYaTianCustom::delivery_location($psorder['aid'],$psorder['bid'],[],$psorder['ordernum']);

                            if($res_location['status'] == 1){
                                $location_data = $res_location['data'];
                                if($location_data["rider_name"])      $psuser['realname']    = $location_data["rider_name"];
                                if($location_data["rider_phone"])     $psuser['tel']         = $location_data["rider_phone"];
                                if($location_data["rider_longitude"]) $psuser['longitude']   = $location_data["rider_longitude"];
                                if($location_data["rider_latitude"])  $psuser['latitude']    = $location_data["rider_latitude"];
                            }
                        }
                    }
                }else if($psorder['psid']<0){
                    $psuser=['realname'=>$psorder['make_rider_name'],'tel'=>$psorder['make_rider_mobile']];
                }else{
                    $psuser = Db::name('peisong_user')->where('id',$psorder['psid'])->find();
                }

                $orderinfo = json_decode($psorder['orderinfo'],true);
                $binfo = json_decode($psorder['binfo'],true);
                $prolist = json_decode($psorder['prolist'],true);
                if($psorder['juli']> 1000){
                    $psorder['juli'] = round($psorder['juli']/1000,1);
                    $psorder['juli_unit'] = 'km';
                }else{
                    $psorder['juli_unit'] = 'm';
                }

                if($psuser['longitude'] && $psuser['latitude']){
                    //查询骑行距离
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
                }else{
                    $psorder['juli2']      = '无';
                    $psorder['juli2_unit'] = '';
                }

                $psorder['leftminute'] = ceil(($psorder['yujitime'] - time()) / 60);

                $psorder['ticheng'] = round($psorder['ticheng'],2);
                if($psorder['status']==4){
                    $psorder['useminute'] = ceil(($psorder['endtime'] - $psorder['createtime']) / 60);
                    $psorder['useminute2'] = ceil(($psorder['endtime'] - $psorder['starttime']) / 60);
                }
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
		}elseif($get['express_com'] == '货运托运'){
			$data = Db::name('freight_type10_record')->where('id',$get['express_no'])->find();
			return $this->json(['datalist'=>$data]);
		}elseif($get['express_com'] == '自提') {
            $list = Db::name('shop_order_shipping_log')->field('id,aid,ordernum,freight_message,freight_time,remark')->where('aid',aid)->where('ordernum',$get['express_no'])
                ->order('freight_time','desc')->select()->toArray();
            if($list){
                foreach ($list as $k => $row){
                    $list[$k] = ['time'=>date('Y-m-d H:i',$row['freight_time']),'context'=>$row['freight_message']];
                }
            }
            $rdata = [];
            $rdata['status'] = 1;
            $rdata['datalist'] = $list;
            return $this->json($rdata);
        }else{
			if($get['express_com'] == '顺丰速运'){
				$totel = Db::name('shop_order')->where('aid',aid)->where('express_no',$get['express_no'])->value('tel');
				if(!$totel){
					$totel = Db::name('seckill_order')->where('aid',aid)->where('express_no',$get['express_no'])->value('tel');
				}
				if(!$totel){
					$totel = Db::name('collage_order')->where('aid',aid)->where('express_no',$get['express_no'])->value('tel');
				}
				if(!$totel){
					$totel = Db::name('kanjia_order')->where('aid',aid)->where('express_no',$get['express_no'])->value('tel');
				}
				if(!$totel){
					$totel = Db::name('scoreshop_order')->where('aid',aid)->where('express_no',$get['express_no'])->value('tel');
				}
				$get['express_no'] = $get['express_no'].":".substr($totel,-4);
			}
			$list = \app\commons\Common::getwuliu($get['express_no'],$get['express_com'],'', aid);
			$rdata = [];
			$rdata['datalist'] = $list;
			return $this->json($rdata);
		}
	}

	function closeOrder(){
		$post = input('post.');
		$orderid = intval($post['orderid']);
		$order = Db::name('shop_order')->where('id',$orderid)->where('aid',aid)->where('mid',mid)->find();
		if(!$order || $order['status']!=0){
			return $this->json(['status'=>0,'msg'=>'关闭失败,订单状态错误']);
		}
		//加库存
		$oglist = Db::name('shop_order_goods')->where('aid',aid)->where('orderid',$orderid)->select()->toArray();
		foreach($oglist as $og){
			Db::name('shop_guige')->where('aid',aid)->where('id',$og['ggid'])->update(['stock'=>Db::raw("stock+".$og['num']),'sales'=>Db::raw("sales-".$og['num'])]);
			Db::name('shop_product')->where('aid',aid)->where('id',$og['proid'])->update(['stock'=>Db::raw("stock+".$og['num']),'sales'=>Db::raw("sales-".$og['num'])]);
			if($og['seckill_starttime']){
				Db::name('seckill_prodata')->where('aid',aid)->where('proid',$og['proid'])->where('ggid',$og['ggid'])->where('starttime',$og['seckill_starttime'])->dec('sales',$og['num'])->update();
			}
			}
		//优惠券抵扣的返还
		if($order['coupon_rid']){
			Db::name('coupon_record')->where('aid',aid)->where('mid',mid)->where('id','in',$order['coupon_rid'])->update(['status'=>0,'usetime'=>'']);
		}
        if(getcustom('money_dec')){
            //返回余额抵扣
            if($order['dec_money']>0){
                \app\commons\Member::addmoney(aid,$order['mid'],$order['dec_money'],t('余额').'抵扣返回，订单号: '.$order['ordernum']);
            }
        }
        $rs = Db::name('shop_order')->where('id',$orderid)->where('aid',aid)->where('mid',mid)->update(['status'=>4]);
		Db::name('shop_order_goods')->where('orderid',$orderid)->where('aid',aid)->where('mid',mid)->update(['status'=>4]);

		if($order['platform'] == 'toutiao'){
			\app\commons\Ttpay::pushorder(aid,$order['ordernum'],2);
		}
        return $this->json(['status'=>1,'msg'=>'操作成功']);
	}
	function delOrder(){
		$post = input('post.');
		$orderid = intval($post['orderid']);
		$order = Db::name('shop_order')->where('id',$orderid)->where('aid',aid)->where('mid',mid)->find();
		if(!$order || ($order['status']!=4 && $order['status']!=3)){
			return $this->json(['status'=>0,'msg'=>'删除失败,订单状态错误']);
		}
		if($order['status']==3){
			$rs = Db::name('shop_order')->where('id',$orderid)->where('aid',aid)->where('mid',mid)->update(['delete'=>1]);
		}else{
			$rs = Db::name('shop_order')->where('id',$orderid)->where('aid',aid)->where('mid',mid)->delete();
			$rs = Db::name('shop_order_goods')->where('orderid',$orderid)->where('aid',aid)->where('mid',mid)->delete();
		}
		return $this->json(['status'=>1,'msg'=>'删除成功']);
	}
	public function orderCollect(){ //确认收货
		$post = input('post.');
		$orderid = intval($post['orderid']);
		$order = Db::name('shop_order')->where('aid',aid)->where('mid',mid)->where('id',$orderid)->find();

        $rsCheck = \app\commons\Order::collectCheck(aid,$orderid,$order,mid);
        if($rsCheck['status'] != 1) return $this->json($rsCheck);

		$rs = \app\commons\Order::collect($order,'shop');
		if($rs['status'] == 0) return $this->json($rs);

		Db::name('shop_order')->where('aid',aid)->where('mid',mid)->where('id',$orderid)->update(['status'=>3,'collect_time'=>time()]);
		Db::name('shop_order_goods')->where('aid',aid)->where('orderid',$orderid)->update(['status'=>3,'endtime'=>time()]);
		if(false){}else{
            \app\commons\Member::uplv(aid,mid);
        }

        $return = ['status'=>1,'msg'=>'确认收货成功','url'=>true];
		$tmplcontent = [];
		$tmplcontent['first'] = '有订单客户已确认收货';
		$tmplcontent['remark'] = '点击进入查看~';
		$tmplcontent['keyword1'] = $this->member['nickname'];
		$tmplcontent['keyword2'] = $order['ordernum'];
		$tmplcontent['keyword3'] = $order['totalprice'].'元';
		$tmplcontent['keyword4'] = date('Y-m-d H:i',$order['paytime']);
        $tmplcontentNew = [];
        $tmplcontentNew['thing3'] = $this->member['nickname'];//收货人
        $tmplcontentNew['character_string7'] = $order['ordernum'];//订单号
        $tmplcontentNew['time8'] = date('Y-m-d H:i');//送达时间
		\app\commons\Wechat::sendhttmpl(aid,$order['bid'],'tmpl_ordershouhuo',$tmplcontent,m_url('admin/order/shoporder'),$order['mdid'],$tmplcontentNew);

		$tmplcontent = [];
		$tmplcontent['thing2'] = $order['title'];
		$tmplcontent['character_string6'] = $order['ordernum'];
		$tmplcontent['thing3'] = $this->member['nickname'];
		$tmplcontent['date5'] = date('Y-m-d H:i');
		\app\commons\Wechat::sendhtwxtmpl(aid,$order['bid'],'tmpl_ordershouhuo',$tmplcontent,'admin/order/shoporder',$order['mdid']);

		return $this->json($return);
	}
    //确认收货 前 验证
    public function orderCollectBefore(){
        $post = input('post.');
        $orderid = intval($post['orderid']);
        $rs = \app\commons\Order::collectCheck(aid,$orderid,[],mid);

        return $this->json($rs);
    }

	//退款单列表
    public function refundList(){
        $st = input('param.st');
        if(!input('param.st') || $st === ''){
            $st = 'all';
        }
        $where = [];
        $where[] = ['aid','=',aid];
        $where[] = ['mid','=',mid];
        $where[] = ['delete','=',0];
        if(input('param.keyword')) $where[] = ['ordernum|refund_ordernum|title', 'like', '%'.input('param.keyword').'%'];
        if($st == 'all'){

        }elseif($st == '0'){
            $where[] = ['refund_status','=',0];
        }elseif($st == '1'){
            $where[] = ['refund_status','=',1];
        }elseif($st == '2'){
            $where[] = ['refund_status','=',2];
        }elseif($st == '3'){
            $where[] = ['refund_status','=',3];
        }

        $pernum = 10;
        $pagenum = input('post.pagenum');
        if(!$pagenum) $pagenum = 1;

        if(input('param.orderid/d')) {
            $where[] = ['orderid','=',input('param.orderid/d')];
            $pernum = 99;
        }

        $datalist = Db::name('shop_refund_order')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
        if(!$datalist) $datalist = array();
        foreach($datalist as $key=>$v){
            $datalist[$key]['prolist'] = Db::name('shop_refund_order_goods')->where('refund_orderid',$v['id'])->select()->toArray();
            if(!$datalist[$key]['prolist']) $datalist[$key]['prolist'] = [];
            $datalist[$key]['procount'] = Db::name('shop_refund_order_goods')->where('refund_orderid',$v['id'])->sum('refund_num');
            if($v['bid']!=0){
                $datalist[$key]['binfo'] = Db::name('business')->where('aid',aid)->where('id',$v['bid'])->field('id,name,logo')->find();
            } else {
                $datalist[$key]['binfo'] = Db::name('admin_set')->where('aid',aid)->field('name,logo')->find();
            }
            if($v['refund_type'] == 'refund') {
                $datalist[$key]['refund_type_label'] = '退款';
            }elseif($v['refund_type'] == 'return') {
                $datalist[$key]['refund_type_label'] = '退货退款';
            }
        }
        $rdata = [];
        $rdata['datalist'] = $datalist;
        $rdata['st'] = $st;
        return $this->json($rdata);
    }
    public function refundDetail()
    {
        $detail = Db::name('shop_refund_order')->where('id',input('param.id/d'))->where('aid',aid)->where('mid',mid)->find();
        if(!$detail) $this->json(['status'=>0,'msg'=>'退款单不存在']);
        $detail['createtime'] = $detail['createtime'] ? date('Y-m-d H:i:s',$detail['createtime']) : '';
        $detail['collect_time'] = $detail['collect_time'] ? date('Y-m-d H:i:s',$detail['collect_time']) : '';
        $detail['paytime'] = $detail['paytime'] ? date('Y-m-d H:i:s',$detail['paytime']) : '';
        $detail['refund_time'] = $detail['refund_time'] ? date('Y-m-d H:i:s',$detail['refund_time']) : '';
        $detail['send_time'] = $detail['send_time'] ? date('Y-m-d H:i:s',$detail['send_time']) : '';
        if($detail['refund_type'] == 'refund') {
            $detail['refund_type_label'] = '退款';
        }elseif($detail['refund_type'] == 'return') {
            $detail['refund_type_label'] = '退货退款';
        }
        if($detail['refund_pics']) {
            $detail['refund_pics'] = explode(',', $detail['refund_pics']);
        }
        $show_return_component = false;
        if($detail['bid'] > 0){
            $detail['binfo'] = Db::name('business')->where('aid',aid)->where('id',$detail['bid'])->field('id,name,logo')->find();
        }else{
            $detail['binfo'] = Db::name('admin_set')->where('aid',aid)->field('name,logo')->find();
        }

        $prolist = Db::name('shop_refund_order_goods')->where('refund_orderid',$detail['id'])->select()->toArray();

        //判断是否有回寄信息
        if($detail['refund_status'] ==4 && !$detail['isexpress']){
        	if(!$detail['return_address'] && !$detail['return_name']){
        		$detail['return_address'] = '等待商家填写';
        	}
        }

        $rdata = [];
        $rdata['detail'] = $detail;
        $rdata['prolist'] = $prolist;
        $rdata['show_return_component'] = $show_return_component;
        $bid = $detail['bid']??0;

        $expressdata = array_keys(express_data(['aid'=>aid,'bid'=>$bid]));
        $rdata['expressdata'] = $expressdata;
        return $this->json($rdata);
    }
    public function refundOrderClose(){
        $post = input('post.');
        $id = intval($post['id']);
        Db::startTrans();
        $order = Db::name('shop_refund_order')->lock(true)->where('id',$id)->where('aid',aid)->where('mid',mid)->find();
        if(!$order || !in_array($order['refund_status'], [1,4])){
            Db::rollback();
            return $this->json(['status'=>0,'msg'=>'关闭失败,退款单状态错误']);
        }
        $rupdate = ['refund_status'=>0];
        Db::name('shop_refund_order')->where('id',$id)->where('aid',aid)->where('mid',mid)->update($rupdate);
//        $rs = Db::name('shop_order')->where('id',$order['orderid'])->where('aid',aid)->where('mid',mid)->update(['refund_status'=>0]);
        $og = Db::name('shop_refund_order_goods')->where('refund_orderid',$id)->where('aid',aid)->where('mid',mid)->select()->toArray();
        foreach ($og as $item) {
            //恢复退款数量
            Db::name('shop_order_goods')->where('id',$item['ogid'])->where('orderid',$order['orderid'])->where('aid',aid)->where('mid',mid)
            ->dec('refund_num', $item['refund_num'])->update();
        }

		Db::commit();
		if($order['fromwxvideo'] == 1){
			\app\commons\Wxvideo::aftersaleupdate($order['orderid'],$order['id']);
		}
        return $this->json(['status'=>1,'msg'=>'操作成功']);
    }
	//退款
	public function refundinit(){
	    //查询订单信息
        $detail = Db::name('shop_order')->where('id',input('param.id/d'))->where('aid',aid)->where('mid',mid)->find();
        if(!$detail)
            return $this->json(['status'=>0,'msg'=>'订单不存在']);
        $detail['createtime'] = $detail['createtime'] ? date('Y-m-d H:i:s',$detail['createtime']) : '';
        $detail['collect_time'] = $detail['collect_time'] ? date('Y-m-d H:i:s',$detail['collect_time']) : '';
        $detail['paytime'] = $detail['paytime'] ? date('Y-m-d H:i:s',$detail['paytime']) : '';
        $detail['refund_time'] = $detail['refund_time'] ? date('Y-m-d H:i:s',$detail['refund_time']) : '';
        $detail['send_time'] = $detail['send_time'] ? date('Y-m-d H:i:s',$detail['send_time']) : '';

        $storeinfo = [];
        if($detail['freight_type'] == 1){
            if($detail['bid'] == 0){
                $storeinfo = Db::name('mendian')->where('id',$detail['mdid'])->field('name,address,longitude,latitude')->find();
            }else{
                $storeinfo = Db::name('business')->where('id',$detail['bid'])->field('name,address,longitude,latitude')->find();
            }
        }

        if($detail['bid'] > 0){
            $detail['binfo'] = Db::name('business')->where('aid',aid)->where('id',$detail['bid'])->field('id,name,logo')->find();
        }

        //新增指定商品退款，默认为整单处理
        $ogid = input('ogid', 0);
        $refundMoneyWhere = [
            ["rog.orderid", "=", $detail['id']],
            ["rog.aid", "=", aid],
            ["ro.refund_status", "in", [1,2,4]]
        ];
        if(!empty($ogid)){
            $refundMoneyWhere[] = [
                "rog.ogid", "=", $ogid
            ];
        }

        $refundMoneySum = Db::name('shop_refund_order_goods')->alias('rog')->join('shop_refund_order ro', 'rog.refund_orderid=ro.id')->where($refundMoneyWhere)->sum('rog.refund_money');

        $canRefundNum = 0;
        $totalNum = Db::name('shop_order_goods')->where("orderid", $detail['id'])->sum(Db::raw('num-refund_num'));
        $returnTotalprice = 0;

        $ogwhere = [
            [
                'orderid', '=', $detail['id']
            ]
        ];
        if(!empty($ogid)){
            $ogwhere[] = [
                "id", "=", $ogid
            ];
        }
        $prolist = Db::name('shop_order_goods')->where($ogwhere)->select()->toArray();
        foreach ($prolist as $key => $item) {
            $prolist[$key]['canRefundNum'] = $item['num'] - $item['refund_num'];
//            $totalNum += $item['num'];
            $canRefundNum += $item['num'] - $item['refund_num'];
//            $returnTotalprice += $item['real_totalprice'] / $item['num'] * ($item['num'] - $item['refund_num']);
        }

		$totalprice = $detail['totalprice'];
		if($detail['balance_price'] > 0 && $detail['balance_pay_status'] == 0){
			$totalprice = $totalprice - $detail['balance_price'];
		}
        if($canRefundNum != $totalNum) {
            //选中的可退款数量和总数量不相同，则表示订单相关其他费用不用退回（例如运费）,重置总的可退款金额
            $totalprice = Db::name('shop_order_goods')->where($ogwhere)->sum('totalprice');
        }else{
            $refundMoneySum = Db::name('shop_refund_order')->where('orderid',$detail['id'])->where('aid',aid)->whereIn('refund_status',[1,2,4])->sum('refund_money');
        }
        $returnTotalprice = $totalprice - $refundMoneySum;
        $returnTotalprice = max($returnTotalprice, 0);
        //可退款金额=总金额-审核中-已退款
        $detail['canRefundNum'] = $canRefundNum;
        $detail['totalNum'] = $totalNum;
        $detail['returnTotalprice'] = round($returnTotalprice,2);
//        if($canRefundNum == 0) {
//            return $this->json(['status'=>0,'msg'=>'当前订单没有可退款的商品']);
//        }
        $setfield = 'comment,autoclose,refundpic';
        $shopset = Db::name('shop_sysset')->where('aid',aid)->field($setfield)->find();
        //todo 确认收货后的退款

        $rdata = [];
        $rdata['status'] = 1;
        $rdata['detail'] = $detail;
        $rdata['prolist'] = $prolist;
        $rdata['shopset'] = $shopset;
        $rdata['storeinfo'] = $storeinfo;

		//订阅消息
		$wx_tmplset = Db::name('wx_tmplset')->where('aid',aid)->find();
		$tmplids = [];

		if($wx_tmplset['tmpl_tuisuccess_new']){
			$tmplids[] = $wx_tmplset['tmpl_tuisuccess_new'];
		}elseif($wx_tmplset['tmpl_tuisuccess']){
			$tmplids[] = $wx_tmplset['tmpl_tuisuccess'];
		}
		if($wx_tmplset['tmpl_tuierror_new']){
			$tmplids[] = $wx_tmplset['tmpl_tuierror_new'];
		}elseif($wx_tmplset['tmpl_tuierror']){
			$tmplids[] = $wx_tmplset['tmpl_tuierror'];
		}
		$rdata['tmplids'] = $tmplids;
		return $this->json($rdata);
	}
	function refund(){//申请退款

		if(request()->isPost()){
			$post = input('post.');
			$orderid = intval($post['orderid']);
			$money = floatval($post['money']);
			$refundNum = $post['refundNum'];

            try {
                Db::startTrans();
                $addtype = 1;//添加订单类型 1：默认添加方式 2：根据退款商品，一商品规格一退货订单
                //老版本
                if (empty($refundNum)) {
                    $order = Db::name('shop_order')->where('aid', aid)->where('mid', mid)->where('id', $orderid)->find();
                    if (!$order || ($order['status'] != 1 && $order['status'] != 2) || $order['refund_status'] == 2) {
                        return $this->json(['status' => 0, 'msg' => '订单状态不符合退款要求']);
                    }
                    if ($money < 0 || $money > $order['totalprice']) {
                        return $this->json(['status' => 0, 'msg' => '退款金额有误']);
                    }
                    Db::name('shop_order')->where('aid', aid)->where('mid', mid)->where('id', $orderid)->update(['refund_time' => time(), 'refund_status' => 1, 'refund_reason' => $post['reason'], 'refund_money' => $money]);
                } else {
                    //新退款 20210610
                    $order = Db::name('shop_order')->where('aid', aid)->where('mid', mid)->where('id', $orderid)->find();

                    if (!$order || ($order['status'] != 1 && $order['status'] != 2)) {
                        return $this->json(['status' => 0, 'msg' => '订单状态不符合退款要求']);
                    }
                    if ($money < 0 || $money > $order['totalprice']) {
                        return $this->json(['status' => 0, 'msg' => '退款金额有误']);
                    }
                    if (empty($refundNum)) {
                        return $this->json(['status' => 0, 'msg' => '请选择退款的商品']);
                    }

                    //仅退款判断图片是否上传
                    $refundpic = Db::name('shop_sysset')->where('aid',aid)->value('refundpic');
                    if($post['type']=='refund' && $refundpic == 1){
                        if(!$post['content_pic'] || empty($post['content_pic'])){
                            return $this->json(['status' => 0, 'msg' => '请上传图片']);
                        }
                    }

                    $refundMoneySum = Db::name('shop_refund_order')->where('orderid', $order['id'])->where('aid', aid)->whereIn('refund_status', [1, 2, 4])->sum('refund_money');

                    $totalRefundNum = 0;
                    $returnTotalprice = 0;
                    $prolist = Db::name('shop_order_goods')->where('orderid', $orderid)->select();
                    $newKey = 'id';
                    $prolist = $prolist->dictionary(null, $newKey);
                    $ogids = array_keys($prolist);
                    Log::write([
                        'file' => __FILE__ . ' L' . __LINE__,
                        'function' => __FUNCTION__,
                        '$prolist' => $prolist,
                    ]);

                    $canRefundNum = 0;
                    $totalNum = 0;
                    $canRefundProductPrice = 0;
                    $canRefundTotalprice = 0;
                    foreach ($prolist as $key => $item) {
                        $prolist[$key]['canRefundNum'] = $item['num'] - $item['refund_num'];
                        $totalNum += $item['num'];
                        $canRefundNum += $item['num'] - $item['refund_num'];
                        $canRefundProductPrice += $item['real_totalprice'] / $item['num'] * ($item['num'] - $item['refund_num']);
                    }
                    foreach ($refundNum as $item) {
                        if (!in_array($item['ogid'], $ogids)) {
                            return $this->json(['status' => 0, 'msg' => '退款商品不存在']);
                        }
                        if ($item['num'] > $prolist[$item['ogid']]['num'] - $prolist[$item['ogid']]['refund_num']) {
                            return $this->json(['status' => 0, 'msg' => $prolist[$item['ogid']]['name'] . '退款数量超出范围']);
                        }
                        $totalRefundNum += $item['num'];
                        $returnTotalprice += $prolist[$item['ogid']]['real_totalprice'] / $prolist[$item['ogid']]['num'] * $item['num'];

                        }
                    if ($totalRefundNum <= 0) {
                        return $this->json(['status' => 0, 'msg' => '请选择退款的商品']);
                    }
                    if ($canRefundNum == $totalNum && $totalNum == $totalRefundNum) {
                        $canRefundTotalprice = $order['totalprice'];
                    } else {
                        $canRefundTotalprice = $order['totalprice'] - $refundMoneySum;
                    }
                    $canRefundTotalprice = round($canRefundTotalprice, 2);
                    if ($money > $canRefundTotalprice) {
                        return $this->json(['status' => 0, 'msg' => '退款金额超出范围']);
                    }
                	//Db::name('shop_order')->where('aid',aid)->where('mid',mid)->where('id',$orderid)->update(['refund_time'=>time(),'refund_status'=>1,'refund_money'=>$money]);

                    $data = [
                        'aid' => $order['aid'],
                        'bid' => $order['bid'],
                        'mdid' => $order['mdid'],
                        'mid' => $order['mid'],
                        'orderid' => $order['id'],
                        'ordernum' => $order['ordernum'],
                        'title' => $order['title'],
                        'refund_type' => $post['type'],
                        'refund_ordernum' => '' . date('ymdHis') . rand(100000, 999999),
                        'refund_money' => $money,
                        'refund_reason' => $post['reason'],
                        'refund_pics' => implode(',', $post['content_pic']),
                        'createtime' => time(),
                        'refund_time' => time(),
                        'refund_status' => 1,
                        'platform' => platform,
                    ];

                    //1、默认添加方式 2、根据退款商品、一商品规格一退货单
                    if($addtype == 1){
                        $refund_id = Db::name('shop_refund_order')->insertGetId($data);
                    }

                    foreach ($refundNum as $item) {
                        if ($item['num'] < 1) continue;
                        //退款金额 *（单价*退款数量）/退款总价=当前商品占退款金额比例
                        $refund_money = $returnTotalprice==0?0:($money * (($prolist[$item['ogid']]['real_totalprice'] / $prolist[$item['ogid']]['num']) * $item['num'] / $returnTotalprice));
                        $od = [
                            'aid' => $order['aid'],
                            'bid' => $order['bid'],
                            'mid' => $order['mid'],
                            'orderid' => $order['id'],
                            'ordernum' => $order['ordernum'],
                            'refund_orderid' => $refund_id,
                            'refund_ordernum' => $data['refund_ordernum'],
                            'refund_num' => $item['num'],
                            //'refund_money' => $item['num'] * $prolist[$item['ogid']]['real_totalprice'] / $prolist[$item['ogid']]['num'],
                            'refund_money' => $refund_money,//退款金额 *（单价*退款数量）/退款总价=当前商品占退款金额比例
                            'ogid' => $item['ogid'],
                            'proid' => $prolist[$item['ogid']]['proid'],
                            'name' => $prolist[$item['ogid']]['name'],
                            'pic' => $prolist[$item['ogid']]['pic'],
                            'procode' => $prolist[$item['ogid']]['procode'],
                            'ggid' => $prolist[$item['ogid']]['ggid'],
                            'ggname' => $prolist[$item['ogid']]['ggname'],
                            'cid' => $prolist[$item['ogid']]['cid'],
                            'cost_price' => $prolist[$item['ogid']]['cost_price'],
                            'sell_price' => $prolist[$item['ogid']]['sell_price'],
                            'createtime' => time()
                        ];

                        Db::name('shop_refund_order_goods')->insertGetId($od);
                        Db::name('shop_order_goods')->where('aid', aid)->where('mid', mid)->where('id', $item['ogid'])->inc('refund_num', $item['num'])->update();

                        if($addtype == 2){
                            if ($order['fromwxvideo'] == 1) {
                                \app\commons\Wxvideo::aftersaleadd($order['id'], $refund_id);
                            }
                            //退款打印小票
                            }
                    }

                    if($addtype == 1){
                        if ($order['fromwxvideo'] == 1) {
                            \app\commons\Wxvideo::aftersaleadd($order['id'], $refund_id);
                        }
                    }
                }
                Db::commit();
                if($addtype == 1){
                    //退款打印小票
                    }
            } catch (\Exception $e) {
                Log::error([
                    'file' => __FILE__ . ' L' . __LINE__,
                    'function' => __FUNCTION__,
                    'error' => $e->getMessage(),
                ]);
                Db::rollback();
                return $this->json(['status'=>0,'msg'=>'提交失败,请重试']);
            }
            try {
                $tmplcontent = [];
                $tmplcontent['first'] = '有订单客户申请退款';
                $tmplcontent['remark'] = '点击进入查看~';
                $tmplcontent['keyword1'] = $order['ordernum'];
                $tmplcontent['keyword2'] = $money.'元';
                $tmplcontent['keyword3'] = $post['reason'];
                $tmplcontentNew = [];
                $tmplcontentNew['number2'] = $order['ordernum'];//订单号
                $tmplcontentNew['amount4'] = $money;//退款金额
                \app\commons\Wechat::sendhttmpl(aid,$order['bid'],'tmpl_ordertui',$tmplcontent,m_url('admin/order/shopRefundOrder'),$order['mdid'],$tmplcontentNew);

				$tmplcontent = [];
				$tmplcontent['thing1'] = $order['title'];
				$tmplcontent['character_string4'] = $order['ordernum'];
				$tmplcontent['amount2'] = $order['totalprice'];
				$tmplcontent['amount9'] = $money.'元';
				$tmplcontent['thing10'] = $post['reason'];
				\app\commons\Wechat::sendhtwxtmpl(aid,$order['bid'],'tmpl_ordertui',$tmplcontent,'admin/order/shopRefundOrder',$order['mdid']);
            } catch (\Exception $e) {

            }
			return $this->json(['status'=>1,'msg'=>'提交成功,请等待商家审核','rs'=>$rs]);
		}
	}
	//评价商品
	public function comment(){
		$ogid = input('param.ogid/d');
		$og = Db::name('shop_order_goods')->where('id',$ogid)->where('mid',mid)->find();
		if(!$og){
			return $this->json(['status'=>0,'msg'=>'未查找到相关记录']);
		}
		$comment = Db::name('shop_comment')->where('ogid',$ogid)->where('aid',aid)->where('mid',mid)->find();
		if(request()->isPost()){
			$shopset = Db::name('shop_sysset')->where('aid',aid)->find();
			if($shopset['comment']==0) return $this->json(['status'=>0,'msg'=>'评价功能未开启']);
			if($comment){
				return $this->json(['status'=>0,'msg'=>'您已经评价过了']);
			}
			$order_good = Db::name('shop_order_goods')->where('aid',aid)->where('mid',mid)->where('id',$ogid)->find();
			$order = Db::name('shop_order')->where('id',$order_good['orderid'])->find();
			$content = input('post.content');
			$content_pic = input('post.content_pic');
			$score = input('post.score/d');
			if($score < 1){
				return $this->json(['status'=>0,'msg'=>'请打分']);
			}
			$data['aid'] = aid;
			$data['mid'] = mid;
			$data['bid'] = $order_good['bid'];
			$data['ogid'] = $order_good['id'];
			$data['proid'] =$order_good['proid'];
			$data['proname'] = $order_good['name'];
			$data['propic'] = $order_good['pic'];
			$data['orderid']= $order['id'];
			$data['ordernum']= $order['ordernum'];
			$data['score'] = $score;
			$data['content'] = $content;
			$data['openid']= $this->member['openid'];
			$data['nickname']= $this->member['nickname'];
			$data['headimg'] = $this->member['headimg'];
			$data['createtime'] = time();
			$data['content_pic'] = $content_pic;
			$data['ggid'] = $order_good['ggid'];
			$data['ggname'] = $order_good['ggname'];
			$data['status'] = ($shopset['comment_check']==1 ? 0 : 1);
			Db::name('shop_comment')->insert($data);
			Db::name('shop_order_goods')->where('aid',aid)->where('mid',mid)->where('id',$ogid)->update(['iscomment'=>1]);
			//Db::name('shop_order')->where('id',$order['id'])->update(['iscomment'=>1]);

			//如果不需要审核 增加产品评论数及评分
			if($shopset['comment_check']==0){
				$countnum = Db::name('shop_comment')->where('proid',$order_good['proid'])->where('status',1)->count();
				$score = Db::name('shop_comment')->where('proid',$order_good['proid'])->where('status',1)->avg('score'); //平均评分
				$haonum = Db::name('shop_comment')->where('proid',$order_good['proid'])->where('status',1)->where('score','>',3)->count(); //好评数
				if($countnum > 0){
					$haopercent = $haonum/$countnum*100;
				}else{
					$haopercent = 100;
				}
				Db::name('shop_product')->where('id',$order_good['proid'])->update(['comment_num'=>$countnum,'comment_score'=>$score,'comment_haopercent'=>$haopercent]);
			}
			return $this->json(['status'=>1,'msg'=>'评价成功']);
		}
		$rdata = [];
		$rdata['og'] = $og;
		$rdata['comment'] = $comment;
		return $this->json($rdata);
	}
	//评价店铺
	public function commentdp(){
		$orderid = input('param.orderid/d');
		$order = Db::name('shop_order')->where('id',$orderid)->where('mid',mid)->find();
		if(!$order){
			return $this->json(['status'=>0,'msg'=>'未查找到相关记录']);
		}
		$comment = Db::name('business_comment')->where('orderid',$orderid)->where('aid',aid)->where('mid',mid)->find();
		if(request()->isPost()){
			if($comment){
				return $this->json(['status'=>0,'msg'=>'您已经评价过了']);
			}
			$content = input('post.content');
			$content_pic = input('post.content_pic');
			$score = input('post.score/d');
			if($score < 1){
				return $this->json(['status'=>0,'msg'=>'请打分']);
			}
			$data['aid'] = aid;
			$data['mid'] = mid;
			$data['bid'] = $order['bid'];
			$data['bname'] = Db::name('business')->where('id',$order['bid'])->value('name');
			$data['orderid']= $order['id'];
			$data['ordernum']= $order['ordernum'];
			$data['score'] = $score;
			$data['content'] = $content;
			$data['content_pic'] = $content_pic;
			$data['openid']= $this->member['openid'];
			$data['nickname']= $this->member['nickname'];
			$data['headimg'] = $this->member['headimg'];
			$data['createtime'] = time();
			$data['status'] = 1;
			Db::name('business_comment')->insert($data);

			//如果不需要审核 增加店铺评论数及评分
			$countnum = Db::name('business_comment')->where('bid',$order['bid'])->where('status',1)->count();
			$score = Db::name('business_comment')->where('bid',$order['bid'])->where('status',1)->avg('score');
			$haonum = Db::name('business_comment')->where('bid',$order['bid'])->where('status',1)->where('score','>',3)->count(); //好评数
			if($countnum > 0){
				$haopercent = $haonum/$countnum*100;
			}else{
				$haopercent = 100;
			}
			Db::name('business')->where('id',$order['bid'])->update(['comment_num'=>$countnum,'comment_score'=>$score,'comment_haopercent'=>$haopercent]);
			return $this->json(['status'=>1,'msg'=>'评价成功']);
		}
		$rdata = [];
		$rdata['order'] = $order;
		$rdata['comment'] = $comment;
		return $this->json($rdata);
	}
	//评价配送员
	public function commentps(){
		$id = input('param.id/d');
		$psorder = Db::name('peisong_order')->where('id',$id)->where('mid',mid)->find();
		if(!$psorder) return $this->json(['status'=>0,'msg'=>'未找到相关记录']);
		$comment = Db::name('peisong_order_comment')->where('orderid',$id)->where('aid',aid)->where('mid',mid)->find();
		if(request()->isPost()){
			if($comment){
				return $this->json(['status'=>0,'msg'=>'您已经评价过了']);
			}
			$content = input('post.content');
			$content_pic = input('post.content_pic');
			$score = input('post.score/d');
			if($score < 1){
				return $this->json(['status'=>0,'msg'=>'请打分']);
			}
			$data['aid'] = aid;
			$data['mid'] = mid;
			$data['bid'] = $psorder['bid'];
			$data['psid'] = $psorder['psid'];
			$data['orderid']= $psorder['id'];
			$data['ordernum']= $psorder['ordernum'];
			$data['score'] = $score;
			$data['content'] = $content;
			$data['content_pic'] = $content_pic;
			$data['nickname']= $this->member['nickname'];
			$data['headimg'] = $this->member['headimg'];
			$data['createtime'] = time();
			$data['status'] = 1;
			Db::name('peisong_order_comment')->insert($data);

			//如果不需要审核 增加配送员评论数及评分
			$countnum = Db::name('peisong_order_comment')->where('psid',$psorder['psid'])->where('status',1)->count();
			$score = Db::name('peisong_order_comment')->where('psid',$psorder['psid'])->where('status',1)->avg('score'); //平均评分
			$haonum = Db::name('peisong_order_comment')->where('psid',$psorder['psid'])->where('status',1)->where('score','>',3)->count(); //好评数
			if($countnum > 0){
				$haopercent = $haonum/$countnum*100;
			}else{
				$haopercent = 100;
			}
			Db::name('peisong_user')->where('id',$psorder['psid'])->update(['comment_num'=>$countnum,'comment_score'=>$score,'comment_haopercent'=>$haopercent]);

			return $this->json(['status'=>1,'msg'=>'评价成功']);
		}
		$rdata = [];
		$rdata['psorder'] = $psorder;
		$rdata['comment'] = $comment;
		return $this->json($rdata);
	}

	public function getproducthxqr(){
		$ogid = input('post.hxogid/d');
		$hxnum = input('post.hxnum/d');
		if(!$ogid || !$hxnum) return json(['status'=>0,'msg'=>'参数错误']);

		$og = Db::name('shop_order_goods')->where('aid',aid)->where('mid',mid)->where('id',$ogid)->find();
		if($og['num'] - $og['hexiao_num'] < $hxnum) return json(['status'=>0,'msg'=>'剩余可核销数量不足']);

		$hexiao_qr = createqrcode(m_url('admin/hexiao/hexiao?type=shopproduct&hxnum='.$hxnum.'&co='.$og['hexiao_code']));
		return json(['status'=>1,'hexiao_qr'=>$hexiao_qr]);
	}

    public function hexiao(){
        $post = input('post.');
        $orderid = intval($post['orderid']);
        $order = Db::name('shop_order')->where('id',$orderid)->where('aid',aid)->where('mid',mid)->find();
        if(!$order) return $this->json(['status'=>0,'msg'=>'订单不存在']);
//
        $type = 'shop';
        if($order['status']==0) return $this->json(['status'=>0,'msg'=>'订单未支付']);
        if($order['status']==3) return $this->json(['status'=>0,'msg'=>'订单已核销']);
        if($order['status']==4) return $this->json(['status'=>0,'msg'=>'订单已关闭']);
        if($order['hexiao_code_member'] != $post['hexiao_code_member']){
            return $this->json(['status'=>0,'msg'=>'核销密码不正确']);
        }
        $order['prolist'] = Db::name($type.'_order_goods')->where(['orderid'=>$order['id']])->select()->toArray();
        if($order['freight_type'] == 1){
            $order['storeinfo'] = Db::name('mendian')->where('id',$order['mdid'])->field('name,address,longitude,latitude')->find();
        }
        $member = Db::name('member')->where('id',$order['mid'])->field('id,nickname,levelid,headimg')->find();
        $order['nickname'] = $member['nickname'];
        $order['headimg'] = $member['headimg'];
        $data = array();
        $data['aid'] = aid;
        $data['bid'] = $order['bid'];
        $data['uid'] = 0;
        $data['mid'] = $order['mid'];
        $data['orderid'] = $order['id'];
        $data['ordernum'] = $order['ordernum'];
        $data['title'] = $order['title'];
        $data['type'] = $type;
        $data['createtime'] = time();
        $data['remark'] = t('会员').'核销';
        Db::name('hexiao_order')->insert($data);
        $remark = $order['remark'] ? $order['remark'].' '.$data['remark'] : $data['remark'];

        $rs = \app\commons\Order::collect($order,$type);
        if($rs['status']==0) return $this->json($rs);

        db($type.'_order')->where('id',$orderid)->where('aid',aid)->update(['status'=>3,'collect_time'=>time(),'remark'=>$remark]);

        if($type == 'shop'){
            Db::name($type.'_order_goods')->where(['aid'=>aid,'orderid'=>$order['id']])->update(['status'=>3,'endtime'=>time()]);
            if(false){}else{
                \app\commons\Member::uplv(aid,$order['mid']);
            }

            }

        return $this->json(['status'=>1,'msg'=>'操作成功']);
    }

    /**
     * 获取收银台订单信息
     * 待结算status=0
     * 已结算订单status=1
     * 挂单status=2
     */
    public function getCashierOrder()
    {
        $status = input('param.st', 'all');
        $keyword = input('param.keyword', 0);
        $page = input('param.pagenum/d', 1);
        $limit = input('param.limit/d', 10);
        $where = [];
        $where[] = ['o.aid','=',aid];

        if($status == 'all'){
            $where[] = ['o.status' ,'in', [1,10]];
        }else{
            $where[] = ['o.status' ,'=', $status];
        }
        $where[] = ['o.mid','=',mid];
        if($keyword){
            $where[] = ['g.proname|g.barcode','like','%'.$keyword.'%'];
        }
        if($status==2){
            $orderby = 'hangup_time desc';
        }else{
            $orderby = 'id desc';
        }
        $lists = Db::name('cashier_order')->alias('o')->join('cashier_order_goods g','o.id=g.orderid')->group('o.id')->where($where)->field('o.*')->order($orderby)->page($page,$limit)->select()->toArray();
        if (empty($lists)) $lists = [];
        foreach ($lists as $k => $order) {
            if($order['uid'] > 0){
                $admin_user_name = Db::name('admin_user')->where('id',$order['uid'])->value('un');
                $lists[$k]['admin_user'] = $admin_user_name??'超级管理员';
            }else{
                $lists[$k]['admin_user'] = '超级管理员';
            }
            $goodslist = Db::name('cashier_order_goods')->where('orderid', $order['id'])->select()->toArray();
            if (empty($goodslist)) $goodslist = [];
            $totalprice = 0;
            foreach ($goodslist as $gk => $goods) {
                $goodslist[$gk]['stock'] = 0;
                if($status==2){
                    $stock = 0;
                    if ($goods['protype'] == 1) {
                        $stock = Db::name('shop_guige')->where('proid', $goods['proid'])->where('id', $goods['ggid'])->value('stock');
                    }
                    $goods_totalprice = round($goods['sell_price'] * $goods['num'],2);
                    $totalprice = $totalprice+$goods_totalprice;
                    $goodslist[$gk]['stock'] = $stock ?? 0;
                }
                if($goods['protype'] ==2){
                    $goodslist[$gk]['propic'] =PRE_URL.'/static/imgsrc/picture-1.jpg';
                }
            }
            if($status==2){
                $lists[$k]['totalprice']  = $totalprice;
            }
            $lists[$k]['hangup_time'] = '';
            if ($order['hangup_time']) {
                $lists[$k]['hangup_time'] = date('Y-m-d H:i:s', $order['hangup_time']);
            }
            $lists[$k]['paytime'] = $order['paytime']?date('Y-m-d H:i:s', $order['paytime']):'';
            $lists[$k]['createtime'] = date('Y-m-d H:i:s', $order['createtime']);
            $arr =[0=>'待付款',1=>'已支付',2=>'挂单',3=>'st3',4=>'已关闭'];
            $lists[$k]['status_desc'] = $arr[$status]??$status;
            if($order['mid']){
                $member =  Db::name('member')->where('id',$order['mid'])->field('id,nickname,realname')->find();
                $lists[$k]['buyer'] = $member['nickname']??'';
            }else{
                $lists[$k]['buyer'] = '匿名购买';
            }
            $lists[$k]['prolist'] = $goodslist ?? [];
            if($order['bid']!=0){
                $lists[$k]['binfo'] = Db::name('business')->where('aid',aid)->where('id',$order['bid'])->field('id,name,logo')->find();
                if(!$lists[$k]['binfo']) $lists[$k]['binfo'] = [];
                $commentdp = Db::name('business_comment')->where('orderid',$order['id'])->where('aid',aid)->where('mid',mid)->find();
                if($commentdp){
                    $lists[$k]['iscommentdp'] = 1;
                }else{
                    $lists[$k]['iscommentdp'] = 0;
                }
            } else {
                $lists[$k]['binfo'] = Db::name('admin_set')->where('aid',aid)->field('name,logo')->find();
            }
            $lists[$k]['procount'] = Db::name('cashier_order_goods')->where('orderid',$order['id'])->sum('num');
        }
        return $this->json(['status'=>1,'msg'=>'操作成功','datalist' => $lists]);
    }
    public function getCashierOrderDetail(){
        $detail = Db::name('cashier_order')->where('id',input('param.id/d'))->where('aid',aid)->where('mid',mid)->find();
        if(!$detail) return $this->json(['status'=>0,'msg'=>'订单不存在']);
        $detail['createtime'] = $detail['createtime'] ? date('Y-m-d H:i:s',$detail['createtime']) : '';
        $detail['paytime'] = $detail['paytime'] ? date('Y-m-d H:i:s',$detail['paytime']) : '';
        $detail['hangup_time'] = $detail['hangup_time'] ? date('Y-m-d H:i:s',$detail['hangup_time']) : '';
        $detail['procount'] = Db::name('cashier_order_goods')->where('orderid',$detail['id'])->sum('num');

        if($detail['uid'] > 0){
            $admin_user_name = Db::name('admin_user')->where('id',$detail['uid'])->value('un');
            $detail['admin_user'] = $admin_user_name??'超级管理员';
        }else{
            $detail['admin_user'] = '超级管理员';
        }
        $prolist = Db::name('cashier_order_goods')->where('orderid',$detail['id'])->select()->toArray();
        foreach ($prolist as $gk=>$goods){
            if($goods['protype'] ==2){
                $prolist[$gk]['propic'] =PRE_URL.'/static/imgsrc/picture-1.jpg';
            }
        }
        $rdata = [];
        $rdata['detail'] = $detail;
        $rdata['prolist'] = $prolist;
        return $this->json($rdata);
    }


	public function handinit(){
        }

    public function hand(){
        }

    public function handList(){
        }

    public function handDetail()
    {
        }

    public function handChangeexpress()
    {
        }
    //退款退货发快递
    function refundExpress(){

        if(request()->isPost()){
            $post = input('post.');
            $orderid = intval($post['orderid']);
            $refund_order = Db::name('shop_refund_order')->where('id',$orderid)->where('refund_status',4)->find();
            if(!$refund_order){
            	return $this->json(['status'=>0,'msg'=>'退款订单不存在或状态不符']);
            }
            if($refund_order['isexpress']){
            	return $this->json(['status'=>0,'msg'=>'已填写过快递']);
            }
            //处理快递信息
            $express_com     = $post['express_com'];
            $express_no      = $post['express_no'];
            if(!$express_com || !$express_no){
               return $this->json(['status'=>0,'msg'=>'快递信息请填写完整']);
            }
            $data = [];
            $data['express_com'] = $express_com;
            $data['express_no']  = $express_no;
            $express_content = jsonEncode([['express_com'=>$express_com,'express_no'=>$express_no]]);
            $data['express_content'] = $express_content;
            $data['isexpress']  = 1;
            $data['expresstime']= time();
            $up = Db::name('shop_refund_order')->where('id',$orderid)->update($data);
            
                
             \app\commons\Aaa::refundOrder(aid,$orderid,$refund_order);
                
        
            
            
            if($up){
            	return $this->json(['status'=>1,'msg'=>'提交成功']);
            }else{
            	return $this->json(['status'=>0,'msg'=>'提交失败']);
            }
        }
    }
    //获取分期数据详情
    function getFenqidata(){
        }

    //分期数据生成支付订单
    function saveFenqidata(){
        }

    public function uploadcard()
    {
        $orderid = input('param.orderid/d');
        $detail = Db::name('shop_order')->where('id', $orderid)->where('aid', aid)->where('mid', mid)->find();
        $oglist = Db::name('shop_order_goods')->where('orderid', $orderid)->where('aid', aid)->select()->toArray();
        if (!$detail) return $this->json(['status' => 0, 'msg' => '订单不存在']);
        if(!in_array($detail['trade_type'],['1101','1303'])){
            return $this->json(['status' => 0, 'msg' => '无须上传身份证信息']);
        }
        $detail['area2'] = $detail['area2']?str_replace(',','/',$detail['area2']): '';
        if(request()->isPost()){
            $data = input('post.');
            if($detail['supplier_status']!=200){
                return $this->json(['status'=>0,'msg'=>'订单状态有误']);
            }
            if(empty($data['cardno']) || empty($data['card']) || empty($data['cardf'])){
                return $this->json(['status'=>0,'msg'=>'身份证信息不完整']);
            }
            $data['area'] = $data['area']?str_replace('/',',',$data['area']): '';
            $areainfo = \app\customs\Chain::getHaidaiArea($data['area']);
            $update = [
                'linkman'=>$data['linkman'],
                'tel'=>$data['tel'],
                'address'=>$data['address'],
                'area'=>str_replace(',','',$data['area']),
                'area2'=>$data['area'],
                'cardno'=>$data['cardno'],
                'card'=>$data['card'],
                'card_back'=>$data['cardf'],
                'area_id'=>$areainfo['area_id'],
                'area_regionid'=>$areainfo['region_id'],
            ];
            Db::name('shop_order')->where('id',$orderid)->update($update);
            $res = \app\customs\Chain::syncOrderToSupplier(aid,$orderid);
            if($res['status']==1){
                \app\customs\Chain::orderPay(aid,$orderid);
            }
        }
        return $this->json(['status'=>1,'order'=>$detail,'oglist'=>$oglist]);
    }
 }