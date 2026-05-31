<?php


//管理员中心 -- 核销订单
namespace app\controllers;
use think\facade\Db;
class ApiAdminHexiao extends ApiAdmin
{	
	//核销
	public function hexiao(){
		$type = input('param.type');
        if(!$type || empty($type)){
            return $this->json(['status'=>0,'msg'=>'无效二维码，不支持核销']);
        }
		$code = input('param.co');
        $mendian_no_select = 0;
        //多商户可以核销平台的商品
		$plat_hexiao = false;
		//核销次数
        if($type=='coupon'){
			$order = Db::name('coupon_record')->where('aid',aid)->where('code', $code)->find();
			if($order['bid']!=bid && !$plat_hexiao) return $this->json(['status'=>0,'msg'=>'登录的账号不是该商家的管理账号']);
            if(!$order) return $this->json(['status'=>0,'msg'=>t('优惠券').'不存在']);
            if($order['status']==1) return $this->json(['status'=>0,'msg'=>t('优惠券').'已使用']);
            if($order['starttime'] > time()) return $this->json(['status'=>0,'msg'=>t('优惠券').'尚未生效']);
            if($order['endtime'] < time()) return $this->json(['status'=>0,'msg'=>t('优惠券').'已过期']);
            if($order['type']==3 && $order['used_count']>=$order['limit_count']) return $this->json(['status'=>0,'msg'=>'已达到使用次数']);
            $order['show_addnum'] = 0;
            if($order['type']==3 && $order['limit_perday'] > 0){ //是否达到每天使用次数限制
                $dayhxnum = Db::name('hexiao_order')->where('orderid',$order['id'])->where('type','coupon')->where('createtime','between',[strtotime(date('Y-m-d 00:00:00')),strtotime(date('Y-m-d 23:59:59'))])->count();
                if($dayhxnum >= $order['limit_perday']){
                    return $this->json(['status'=>0,'msg'=>'该计次券每天最多核销'.$order['limit_perday'].'次']);
                }
            }

			$coupon = Db::name('coupon')->where('id',$order['couponid'])->find();
			$order['usetips'] = $coupon['usetips'];
            $order['createtime'] = date('Y-m-d H:i:s',$order['createtime']);
            $order['usetime'] = date('Y-m-d H:i:s',$order['usetime']);
            $order['starttime'] = date('Y-m-d H:i:s',$order['starttime']);
            $order['endtime'] = date('Y-m-d H:i:s',$order['endtime']);
		}elseif($type=='cycle'){
            $order_state = db('cycle_order_stage')->where(['aid'=>aid,'hexiao_code'=>$code])->find();
            if($order_state['bid']!=bid && !$plat_hexiao) return $this->json(['status'=>2,'msg'=>'登录的账号不是该商家的管理账号，是否切换账号？']);
            if(!$order_state) return $this->json(['status'=>0,'msg'=>'订单不存在']);
            if($order_state['status']==0) return $this->json(['status'=>0,'msg'=>'订单未支付']);
            if($order_state['status']==3) return $this->json(['status'=>0,'msg'=>'订单已核销']);
            $order = db('cycle_order')->where(['aid'=>aid,'id'=>$order_state['orderid']])->find();
            $order['prolist'] = [['name'=>$order['proname'],'pic'=>$order['propic'],'ggname'=>$order['ggname'],'sell_price'=>$order['sell_price'],'num'=>$order['num']]];
            $order['stage'] =$order_state;
            if($order['freight_type'] == 1){
                $order['storeinfo'] = Db::name('mendian')->where('id',$order['mdid'])->field('name,address,longitude,latitude')->find();
            }
            $member = Db::name('member')->where('id',$order['mid'])->field('id,nickname,headimg')->find();
            $order['createtime'] = date('Y-m-d H:i:s',$order['createtime']);
            $order['paytime'] = date('Y-m-d H:i:s',$order['paytime']);
            $order['nickname'] = $member['nickname'];
            $order['headimg'] = $member['headimg'];
            
        }elseif($type=='choujiang'){
			if(bid!=0 && !$plat_hexiao) return $this->json(['status'=>0,'msg'=>'登录的账号不是该商家的管理账号']);
			$order = Db::name('choujiang_record')->where(['aid'=>aid,'code'=>$code])->find();
			if(!$order) return $this->json(['status'=>0,'msg'=>'奖品不存在']);
			if($order['status']==1) return $this->json(['status'=>0,'msg'=>'奖品已兑换']);
			$order['formdata'] = json_decode($order['formdata'],true);
        }elseif($type=='huodong_baoming'){
            }elseif($type=='shopproduct'){
            $og = Db::name('shop_order_goods')->where('aid',aid)->where('hexiao_code',$code)->find();
            if(!$og) return $this->json(['status'=>0,'msg'=>'核销码已失效']);

            $order = Db::name('shop_order')->where('aid',aid)->where('id',$og['orderid'])->find();
            if(!$order) return $this->json(['status'=>0,'msg'=>'订单已删除']);

            if($order['bid']!=bid){
                $freight = Db::name('freight')->where('aid',aid)->where('id',$order['freight_id'])->find();
                if($freight['hxbids'] && !in_array(bid,explode(',',$freight['hxbids']))){
                    return $this->json(['status'=>0,'msg'=>'登录的账号不能核销平台商品']);
                }
            }
            $order['createtime'] = date('Y-m-d H:i:s',$order['createtime']);
            $order['paytime'] = date('Y-m-d H:i:s',$order['paytime']);

            $order['ogdata'] = $og;
            $order['hxnum'] = input('param.hxnum');

            if($order['hxnum'] > $og['num'] - $og['hexiao_num']) return $this->json(['status'=>0,'msg'=>'可核销数量不足']);
        }elseif($type=='doing'){
			$order = Db::name('doing_order')->where('aid',aid)->where('hexiao_code', $code)->find();
// 			if($order['bid']!=bid && !$plat_hexiao  ) return $this->json(['status'=>0,'msg'=>'登录的账号不是该商家的管理账号']);
            if(!$order) return $this->json(['status'=>0,'msg'=>'记录不存在']);
            if($order['status']==2) return $this->json(['status'=>0,'msg'=>'已签到完成']);
            $doing = Db::name('doing')->where('id',$order['formid'])->find();
             if(!$doing) return $this->json(['status'=>0,'msg'=>'活动不存在']);
            if($doing['starttime'] > time()) return $this->json(['status'=>0,'msg'=>'活动未生效']);
            if($doing['endtime'] < time()) return $this->json(['status'=>0,'msg'=>'活动已过期']);
            
            $formcontent = json_decode($doing['content'],true);
        	$order['formcontent']  = $formcontent;
            $member = Db::name('member')->where('id',$order['mid'])->field('id,nickname,headimg')->find();
			$order['nickname'] = $member['nickname'];
			$order['headimg'] = $member['headimg'];
            if($order['createtime']){
                $order['createtime'] = date('Y-m-d H:i:s',$order['createtime']);
            }
            if($order['paytime']){
                $order['paytime'] = date('Y-m-d H:i:s',$order['paytime']);
            }
            $order['starttime'] = date('Y-m-d H',$doing['starttime']);
            $order['endtime'] = date('Y-m-d H',$doing['endtime']);
	}elseif($type=='takeaway_order_product'){
            $og = Db::name('restaurant_takeaway_order_goods')->where('aid',aid)->where('hexiao_code',$code)->find();
            if(!$og) return $this->json(['status'=>0,'msg'=>'核销码已失效']);

            $set = Db::name('restaurant_takeaway_sysset')->where('aid',aid)->where('bid',$og['bid'])->field('alone_hexiao_status')->find();
            if(!$set || !$set['alone_hexiao_status']){
                return $this->json(['status'=>0,'msg'=>'商家未开启外卖菜品单独核销功能']);
            }

            $order = Db::name('restaurant_takeaway_order')->where('aid',aid)->where('id',$og['orderid'])->find();
            if(!$order) return $this->json(['status'=>0,'msg'=>'订单已删除']);

            if($order['bid']!=bid){
                $freight = Db::name('freight')->where('aid',aid)->where('id',$order['freight_id'])->find();
                if($freight['hxbids'] && !in_array(bid,explode(',',$freight['hxbids']))){
                    return $this->json(['status'=>0,'msg'=>'登录的账号不能核销平台商品']);
                }
            }
            $order['createtime'] = date('Y-m-d H:i:s',$order['createtime']);
            $order['paytime'] = date('Y-m-d H:i:s',$order['paytime']);

            $order['ogdata']    = $og;
            $order['hxnum']     = $og['num'];
            $order['now_hxnum'] = $og['num']-$order['hexiao_num'];

        }elseif($type=='business_miandan'){
            }elseif($type=='hbtk'){
            if(!$plat_hexiao && !getcustom('yx_hbtk')) return $this->json(['status'=>0,'msg'=>'登录的账号不是该商家的管理账号']);
            $order = Db::name('hbtk_order')->where(['aid'=>aid,'code'=>$code])->find();
            if(!$order) return $this->json(['status'=>0,'msg'=>'活动不存在']);
            if($order['status']==2) return $this->json(['status'=>0,'msg'=>'活动已核销']);
            //邀请人数
            $order['yqnum'] = 0 + Db::name('hbtk_order')->where('aid',aid)->where('pid',$order['mid'])->where('hid',$order['hid'])->count();
            $order['createtime'] = date('Y-m-d H:i:s',$order['createtime']);
            if($order['paytime']){
                $order['paytime'] = date('Y-m-d H:i:s',$order['paytime']);
            }
            $hd = Db::name('hbtk_activity')->where('id',$order['hid'])->find();
            $order['pic'] = $hd['fmpic'];
            $yqlist =  Db::name('hbtk_order')->where('aid',aid)->where('hid',$order['hid'])->where('pid',$order['mid'])->where('status','in',[1,2])->select()->toArray();
            $order['yqlist'] = $yqlist?$yqlist:[];
        }elseif($type=='gift_bag_goods'){
        	}elseif($type=='verifyauth'){
            }elseif($type=='hotel'){
			$order = db($type.'_order')->where(['aid'=>aid,'hexiao_code'=>$code])->find();
			if(!$order) return $this->json(['status'=>0,'msg'=>'订单不存在']);
			if($order['status']==0) return $this->json(['status'=>0,'msg'=>'订单未支付']);
			if($order['status']==3) return $this->json(['status'=>0,'msg'=>'订单已核销']);
			if($order['status']==-1) return $this->json(['status'=>0,'msg'=>'订单已关闭']);
			$member = Db::name('member')->where('id',$order['mid'])->field('id,nickname,headimg')->find();
			$order['nickname'] = $member['nickname'];
			$order['headimg'] = $member['headimg'];
            if($order['createtime']){
                $order['createtime'] = date('Y-m-d H:i:s',$order['createtime']);
            }
            if($order['paytime']){
                $order['paytime'] = date('Y-m-d H:i:s',$order['paytime']);
            }
		}else{
			$order = db($type.'_order')->where(['aid'=>aid,'hexiao_code'=>$code])->find();

			if($order['bid']!=bid && !$plat_hexiao){
				if(false){}else{
					return $this->json(['status'=>2,'msg'=>'登录的账号不是该商家的管理账号，是否切换账号？']);
				}
            }

			//if($order['bid']!=bid && !$plat_hexiao && !getcustom('mendian_hexiao_givemoney')) return $this->json(['status'=>2,'msg'=>'登录的账号不是该商家的管理账号，是否切换账号？']);
			if(!$order) return $this->json(['status'=>0,'msg'=>'订单不存在']);
			if($order['status']==0) return $this->json(['status'=>0,'msg'=>'订单未支付']);
			if($order['status']==3) return $this->json(['status'=>0,'msg'=>'订单已核销']);
			if($order['status']==4) return $this->json(['status'=>0,'msg'=>'订单已关闭']);
			if(\app\commons\Order::hasOrderGoodsTable($type)){
				if($type=='gift_bag'){
					$prolist = Db::name($type.'_order_goods')->where('orderid',$order['id'])->where('status','between',[1,2])->select()->toArray();
				}else{
					$prolist = Db::name($type.'_order_goods')->where(['orderid'=>$order['id']])->select()->toArray();
				}
                $order['prolist'] = $prolist;
			}elseif($type=='lucky_collage' ){
				$order['prolist'] = [['name'=>$order['proname'],'pic'=>$order['propic'],'ggname'=>$order['ggname'],'sell_price'=>$order['sell_price'],'num'=>$order['num']]];
			}else{
				$order['prolist'] = [['name'=>$order['proname'],'pic'=>$order['propic'],'ggname'=>$order['ggname'],'sell_price'=>$order['sell_price'],'num'=>$order['num']]];
			}
			if($order['freight_type'] == 1){
				$order['storeinfo'] = Db::name('mendian')->where('id',$order['mdid'])->field('name,address,longitude,latitude')->find();
				if(!$order['storeinfo']) $order['storeinfo'] = [];
			}
			$member = Db::name('member')->where('id',$order['mid'])->field('id,nickname,headimg')->find();
			$order['nickname'] = $member['nickname'];
			$order['headimg'] = $member['headimg'];
            if($order['createtime']){
                $order['createtime'] = date('Y-m-d H:i:s',$order['createtime']);
            }
            if($order['paytime']){
                $order['paytime'] = date('Y-m-d H:i:s',$order['paytime']);
            }
		}

        if($this->user['isadmin']==0 && !getcustom('freight_selecthxbids')){
            $auth_data = json_decode($this->user['hexiao_auth_data'],true);
            if(!in_array($type,$auth_data)){
                return $this->json(['status'=>0,'msg'=>'您没有核销权限']);
            }
            if($type=='shop' || $type=='collage' || $type=='kanjia' || $type=='scoreshop' ||$type=='cycle' ){
                if(!$mendian_no_select) {
                    if ($this->user['mdid'] != 0 && $this->user['mdid'] != $order['mdid'] && !$plat_hexiao) {
                        return $this->json(['status' => 0, 'msg' => '您没有该门店核销权限']);
                    }
                }
            }
        }
        if($mendian_no_select && $type=='shop'){
            $prolist = Db::name($type.'_order_goods')->where(['orderid'=>$order['id']])->select()->toArray();
            $have_hx_proids = Db::name('hexiao_order')->where('orderid',$order['id'])->column('proids');
            $have_hx_proids = array_unique(explode(',',implode(',',$have_hx_proids)));
            foreach($prolist as $k=>$pro){
                $bind_mendian_ids = Db::name('shop_product')->where('id',$pro['proid'])->value('bind_mendian_ids');
                $bind_mendian_ids = explode(',',$bind_mendian_ids);
                if($pro['bind_mendian_ids']!='-1' && $this->user['mdid'] != 0 && !in_array($this->user['mdid'],$bind_mendian_ids) && !in_array('-1',$bind_mendian_ids)){
                    unset($prolist[$k]);
                }
                if(in_array($pro['proid'],$have_hx_proids)){
                    unset($prolist[$k]);
                }
            }
            $prolist = array_values($prolist);

            if(empty($prolist)){
                return $this->json(['status'=>0,'msg'=>'暂无待核销产品']);
            }
        }

		if(input('post.op') == 'confirm'){
            $order['createtime'] = strtotime($order['createtime']);
            $order['paytime']    = strtotime($order['paytime']);
            $typeArr = ['shop','collage','lucky_collage','kanjia','scoreshop','seckill','yueke','restaurant_shop','restaurant_takeaway','tuangou','gift_bag'];
			if(in_array($type,$typeArr)){
                $data = array();
				$data['aid'] = aid;
				$data['bid'] = bid;
				$data['uid'] = $this->uid;
				$data['mid'] = $order['mid'];
				$data['orderid']  = $order['id'];
				$data['ordernum'] = $order['ordernum'];
				$data['title']    = $order['title'];
                if($mendian_no_select && $type=='shop'){
                    $pro_ids = Db::name('shop_order_goods')->where('orderid',$order['id'])->where('is_hx','=',0)->column('proid');
                    if($this->user['mdid']!=0){
                        $pro_ids = Db::name('shop_product')->where('id','in',$pro_ids)->where('find_in_set('.$this->user['mdid'].',bind_mendian_ids) or find_in_set("-1",bind_mendian_ids)')->column('id');
                    }
                    $data['proids'] = implode(',',$pro_ids);
                    $title_arr = Db::name('shop_product')->where('id','in',$pro_ids)->column('name');
                    $data['title'] = implode(',',$title_arr);
                }
				$data['type']     = $type;
				$data['createtime'] = time();
				$data['remark'] = '核销员['.$this->user['un'].']核销';
				if(false){}else{
					$data['mdid']   = empty($this->user['mdid'])?0:$this->user['mdid'];
				}
                Db::name('hexiao_order')->insert($data);
				$remark = $order['remark'] ? $order['remark'].' '.$data['remark'] : $data['remark'];

                $is_confirm = 1;
                if($mendian_no_select && $type=='shop'){
                    //不需要选择门店的，多产品可能会在不同门店核销，全部核销完成之后再收货
                    $pro_count = Db::name('shop_order_goods')->where('orderid',$order['id'])->where('refund_num',0)->count();
                    $hx_pro = Db::name('hexiao_order')->where('orderid',$order['id'])->column('proids');
                    $hx_pro = array_unique(explode(',',implode(',',$hx_pro)));
                    if($pro_count>count($hx_pro)){
                        $is_confirm = 0;
                    }
                    $hx_mdids = $order['hx_mdids'].','.$this->mendian['id'];
                    $hx_mdids = ltrim($hx_mdids,',');
                    Db::name('shop_order')->where('id',$order['id'])->update(['hx_mdids'=>$hx_mdids]);
                    Db::name('shop_order_goods')->where('orderid',$order['id'])->where('proid','in',$data['proids'])->update(['is_hx'=>1,'hx_mdid'=>$this->mendian['id']]);
                }
                if($is_confirm==1){
                    //发货信息录入 微信小程序+微信支付
                    if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
                        \app\commons\Order::wxShipping(aid,$order,$type);
                    }

                    $rs = \app\commons\Order::collect($order,$type, $this->user['mid']);
                    if($rs['status']==0) return $this->json($rs);

                    if($type == 'restaurant_shop'){
                        $rs = \app\customs\Restaurant::shop_orderconfirm($order['id']);
                        if($rs['status']==0){
                            return $this->json($rs);
                        }
                    }else if($type == 'restaurant_takeaway'){
                        $rs = \app\customs\Restaurant::takeaway_orderconfirm($order['id']);
                        if($rs['status']==0){
                            return $this->json($rs);
                        }
                    }else if($type == 'gift_bag'){
                        }else{
                        db($type.'_order')->where(['aid'=>aid,'hexiao_code'=>$code])->update(['status'=>3,'collect_time'=>time(),'remark'=>$remark]);
                        if(in_array($type,['scoreshop'])){
                            Db::name($type.'_order_goods')->where(['aid'=>aid,'orderid'=>$order['id']])->update(['status'=>3,'endtime'=>time()]);
                        }
                    }
                    if($type == 'shop'){
                        Db::name('shop_order_goods')->where(['aid'=>aid,'orderid'=>$order['id']])->update(['status'=>3,'endtime'=>time()]);
                        if(false){}else{
                            \app\commons\Member::uplv(aid,$order['mid']);
                        }
                        }

                    if(getcustom('mendian_hexiao_givemoney') && $order['mdid']){
                        $mendian = Db::name('mendian')->where('aid',aid)->where('id',$order['mdid'])->find();
                        if($mendian){
                            $givemoney = 0;
                            if($type == 'shop'){
                                $oglist = Db::name('shop_order_goods')->where(['aid'=>aid,'orderid'=>$order['id']])->select()->toArray();
                                if($oglist){
                                    if(getcustom('product_mendian_hexiao_givemoney')){
                                        foreach ($oglist as $og){
                                            $pro = Db::name('shop_product')->where('aid',aid)->where('id',$og['proid'])->find();
                                            $hexiao_set = Db::name('shop_product_mendian_hexiaoset')->where('aid',aid)->where('mdid',$order['mdid'])->where('proid',$og['proid'])->find();
                                            if($hexiao_set['hexiaogivepercent']>0 || $hexiao_set['hexiaogivemoney']>0){
                                                $givemoney += $hexiao_set['hexiaogivepercent'] * 0.01 * $og['real_totalprice'] + $hexiao_set['hexiaogivemoney'];
                                            }
                                            elseif(!is_null($pro['hexiaogivepercent']) || !is_null($pro['hexiaogivemoney'])){

                                                $givemoney += $pro['hexiaogivepercent'] * 0.01 * $og['real_totalprice'] + $pro['hexiaogivemoney'];
                                            }else{
                                                $givemoney += $mendian['hexiaogivepercent'] * 0.01 * $og['real_totalprice'] + $mendian['hexiaogivemoney'];
                                            }
                                        }
                                    }else{
                                        foreach ($oglist as $og){
                                            $pro = Db::name('shop_product')->where('aid',aid)->where('id',$og['proid'])->find();
                                            if(!is_null($pro['hexiaogivepercent']) || !is_null($pro['hexiaogivemoney'])){
                                                $givemoney += $pro['hexiaogivepercent'] * 0.01 * $og['real_totalprice'] + $pro['hexiaogivemoney'];
                                            }else{
                                                $givemoney += $mendian['hexiaogivepercent'] * 0.01 * $og['real_totalprice'] + $mendian['hexiaogivemoney'];
                                            }
                                        }
                                    }
                                }
                            }elseif(($mendian['hexiaogivepercent'] || $mendian['hexiaogivemoney'])){
                                $givemoney = $mendian['hexiaogivepercent'] * 0.01 * $order['totalprice'] + $mendian['hexiaogivemoney'];
                            }
                            if($givemoney > 0){
                                \app\commons\Mendian::addmoney(aid,$mendian['id'],$givemoney,'核销订单'.$order['ordernum']);
                            }
                            if(getcustom('business_platform_auth')){
                                if($mendian['bid']>0 && $order['bid']!=$mendian['bid']){
                                    $business = Db::name('business')->where('aid',aid)->where('id',$mendian['bid'])->find();
                                    if($business['isplatform_auth']==1){
                                        \app\commons\Business::addmoney(aid,$mendian['bid'],$givemoney,$mendian['name'].'核销平台商品 订单号：'.$order['ordernum']);
                                    }
                                }
                            }
                        }
                    }
                }



			}elseif($type=='coupon'){
			    //如果是计次优惠券，则需要判断使用间隔
                if(getcustom('coupon_times_expire') || getcustom('coupon_times_use_gap')){
                    $cheRes = $this->couponTimesCheck(aid,bid,$order);
                    if($cheRes['status']!=1){
                        return $this->json(['status'=>0,'msg'=>$cheRes['msg']]);
                    }
                }
				$data = array();
				$data['aid'] = aid;
				$data['bid'] = bid;
				$data['uid'] = $this->uid;
				$data['mid'] = $order['mid'];
				$data['orderid'] = $order['id'];
				$data['ordernum'] = date('ymdHis').aid.rand(1000,9999);
				$data['title'] = $order['couponname'];
				$data['type'] = $type;
				$data['createtime'] = time();
				$data['remark'] = '核销员['.$this->user['un'].']核销';
                $data['mdid']   = empty($this->user['mdid'])?0:$this->user['mdid'];
                Db::name('hexiao_order')->insert($data);
				$remark = $order['remark'] ? $order['remark'].' '.$data['remark'] : $data['remark'];
				if($order['type']==3){//计次券
				    $hxnum = 1;
				    Db::name($type.'_record')->where(['aid'=>aid,'code'=>$code])->inc('used_count',$hxnum)->update();
					if($order['used_count']+$hxnum>=$order['limit_count']){
						Db::name($type.'_record')->where(['aid'=>aid,'code'=>$code])->update(['status'=>1,'usetime'=>time(),'remark'=>$remark]);
					}
				}else{
					Db::name($type.'_record')->where(['aid'=>aid,'code'=>$code])->update(['status'=>1,'usetime'=>time(),'remark'=>$remark]);
                    $record = Db::name($type.'_record')->where(['aid'=>aid,'code'=>$code])->find();
                    \app\commons\Coupon::useCoupon(aid,$record['id'],'hexiao');
				}
				\app\commons\Wechat::updatemembercard(aid,$order['mid']);
			}elseif($type=='doing'){
			   
                $data = array();
                $data['aid'] = aid;
                $data['bid'] = bid;
                $data['uid'] = $this->uid;
                $data['mid'] = $order['mid'];
                $data['orderid'] = $order_state['id'];
                $data['ordernum'] = $order_state['ordernum'];
                $data['title'] = $order['title'];
                $data['type'] = $type;
                $data['createtime'] = time();
                $data['remark'] = '核销员['.$this->user['un'].']核销';
                Db::name('hexiao_order')->insert($data);
                if($order['money'] >0 &&  $order['paystatus'] > 0){
                    $rs = \app\commons\Member::addmoney(aid,$order['mid'],$order['money'],'报名费退回');
        			if($rs['status']==0){
        				return $this->json(['status'=>0,'msg'=>$rs['msg']]);
        			}
        			Db::name('doing_order')->where('aid',aid)->where('id',$order['id'])->update(['collect_time'=>time(),'isrefund'=>1,'status'=>2]);
                }else{
                    Db::name('doing_order')->where('aid',aid)->where('id',$order['id'])->update(['collect_time'=>time(), 'status'=>2]);
                }
                
            }elseif($type=='cycle'){
			   
                $data = array();
                $data['aid'] = aid;
                $data['bid'] = bid;
                $data['uid'] = $this->uid;
                $data['mid'] = $order['mid'];
                $data['orderid'] = $order_state['id'];
                $data['ordernum'] = $order_state['ordernum'];
                $data['title'] = $order['title'];
                $data['type'] = $type;
                $data['createtime'] = time();
                $data['remark'] = '核销员['.$this->user['un'].']核销';
                $data['mdid']   = empty($this->user['mdid'])?0:$this->user['mdid'];
                Db::name('hexiao_order')->insert($data);
                $remark = $order['remark'] ? $order['remark'].' '.$data['remark'] : $data['remark'];

                db($type.'_order_stage')->where(['aid'=>aid,'hexiao_code'=>$code])->update(['status'=>3,'collect_time'=>time(),'remark'=>$remark]);
                $order_stage_count = Db::name('cycle_order_stage')
                    ->where('status','in','0,1,2')
                    ->where('orderid',$order['id'])
                    ->count();
                if($order_stage_count == 0){
                    Db::name('cycle_order')->where('aid',aid)->where('bid',bid)->where('id',$order['id'])->update(['status'=>3,'collect_time'=>time()]);
                    $rs = \app\commons\Order::collect($order,$type, $this->user['mid']);
                    if($rs['status']==0) return $this->json($rs);
                }else{
                    Db::name('cycle_order')->where('aid',aid)->where('bid',bid)->where('id',$order['id'])->update(['status'=>2]);
                }
            }elseif($type=='choujiang'){
			
				$data = array();
				$data['aid'] = aid;
				$data['bid'] = bid;
				$data['uid'] = $this->uid;
				$data['mid'] = $order['mid'];
				$data['orderid'] = $order['id'];
				$data['ordernum'] = date('ymdHis').aid.rand(1000,9999);
				$data['title'] = $order['jxmc'];
				$data['type'] = $type;
				$data['createtime'] = time();
				$data['remark'] = '核销员['.$this->user['un'].']核销';
				if(false){}else{
					$data['mdid']   = empty($this->user['mdid'])?0:$this->user['mdid'];
				}
                Db::name('hexiao_order')->insert($data);
				$remark = $order['remark'] ? $order['remark'].' '.$data['remark'] : $data['remark'];
				Db::name($type.'_record')->where(['aid'=>aid,'code'=>$code])->update(['status'=>1,'remark'=>$remark]);
				//查看是否有设置核销奖励
				}elseif($type=='huodong_baoming'){
                }else if($type == 'business_miandan'){
                }elseif($type=='shopproduct' || $type == 'gift_bag_goods'){
				$data = array();
				$data['aid'] = aid;
				$data['bid'] = bid;
				$data['uid'] = $this->uid;
				$data['mid'] = $order['mid'];
				$data['orderid'] = $order['ogdata']['id'];
				$data['ordernum'] = $order['ordernum'];
				if($type=='shopproduct'){
					$data['title'] = $order['ogdata']['name'].'('.$order['ogdata']['ggname'].')';
				}else if($type=='gift_bag_goods'){
					$data['title'] = $order['ogdata']['name'];
				}
				$data['type'] = $type;
				$data['createtime'] = time();
				$data['remark'] = '核销员['.$this->user['un'].']核销';
				$data['mdid']   = empty($this->user['mdid'])?0:$this->user['mdid'];
                Db::name('hexiao_order')->insert($data);
				$remark = $order['remark'] ? $order['remark'].' '.$data['remark'] : $data['remark'];

				if($type=='shopproduct'){
					Db::name('shop_order_goods')->where('id',$order['ogdata']['id'])->inc('hexiao_num',$order['hxnum'])->update(['hexiao_code'=>random(18)]);
				}else if($type=='gift_bag_goods'){
					}

				$pdata = [];
				$pdata['aid'] = aid;
				$pdata['bid'] = bid;
				$pdata['uid'] = $this->uid;
				$pdata['mid'] = $order['mid'];
				$pdata['orderid'] = $order['ogdata']['id'];
				$pdata['ordernum'] = $order['ordernum'];
				if($type=='shopproduct'){
					$pdata['title'] = $order['ogdata']['name'].'('.$order['ogdata']['ggname'].')';
				}else if($type=='gift_bag_goods'){
					$pdata['title'] = $order['ogdata']['name'];
				}
				$pdata['createtime'] = time();
				$pdata['remark'] = '核销员['.$this->user['un'].']核销';
				$pdata['proid'] = $order['ogdata']['proid'];
				$pdata['name'] = $order['ogdata']['name'];
				$pdata['pic'] = $order['ogdata']['pic'];
				
				$pdata['num'] = $order['hxnum'];
				$pdata['ogid'] = $order['ogdata']['id'];
				if($type=='shopproduct'){
					$pdata['ggid'] = $order['ogdata']['ggid'];
					$pdata['ggname'] = $order['ogdata']['ggname'];
					Db::name('hexiao_shopproduct')->insert($pdata);
				}else if($type=='gift_bag_goods'){
					}

				if($order['hxnum'] + $order['ogdata']['hexiao_num'] == $order['ogdata']['num']){
					if($type=='shopproduct'){
						$totalhxnum = Db::name('shop_order_goods')->where('orderid',$order['id'])->sum('hexiao_num');
						$totalnum   = Db::name('shop_order_goods')->where('orderid',$order['id'])->sum('num');
						if($totalhxnum >= $totalnum){
							$rs = \app\commons\Order::collect($order,'shop', $this->user['mid']);
							//if($rs['status']==0) return $this->json($rs);
							Db::name('shop_order')->where('id',$order['id'])->update(['status'=>3,'collect_time'=>time(),'remark'=>'已核销']);
							Db::name('shop_order_goods')->where(['aid'=>aid,'orderid'=>$order['id']])->update(['status'=>3,'endtime'=>time()]);
                            if(false){}else{
                                \app\commons\Member::uplv(aid,$order['mid']);
                            }
                            }
					}else if($type=='gift_bag_goods'){
						}
				}
			}elseif($type=='takeaway_order_product'){
                $data = array();
                $data['aid'] = aid;
                $data['bid'] = bid;
                $data['uid'] = $this->uid;
                $data['mid'] = $order['mid'];
                $data['orderid'] = $order['ogdata']['id'];
                $data['ordernum'] = $order['ordernum'];
                $data['title'] = $order['ogdata']['name'].'('.$order['ogdata']['ggname'].')';
                $data['type'] = $type;
                $data['createtime'] = time();
                $data['remark'] = '核销员['.$this->user['un'].']核销';
                $up = $data['mdid']   = empty($this->user['mdid'])?0:$this->user['mdid'];
                Db::name('hexiao_order')->insert($data);

                //$remark = $order['remark'] ? $order['remark'].' '.$data['remark'] : $data['remark'];

                Db::name('restaurant_takeaway_order_goods')->where('id',$order['ogdata']['id'])->inc('hexiao_num',$order['hxnum'])->update(['hexiao_code'=>random(18),'status'=>3,'endtime'=>time()]);

                $pdata = [];
                $pdata['aid']  = aid;
                $pdata['bid']  = bid;
                $pdata['type'] = 1;
                $pdata['uid']  = $this->uid;
                $pdata['mid']  = $order['mid'];

                $pdata['orderid']    = $order['ogdata']['id'];
                $pdata['ordernum']   = $order['ordernum'];
                $pdata['title']      = $order['ogdata']['name'].'('.$order['ogdata']['ggname'].')';
                $pdata['remark']     = '核销员['.$this->user['un'].']核销';
                $pdata['proid']      = $order['ogdata']['proid'];
                $pdata['name']       = $order['ogdata']['name'];
                $pdata['pic']        = $order['ogdata']['pic'];
                $pdata['ggid']       = $order['ogdata']['ggid'];
                $pdata['ggname']     = $order['ogdata']['ggname'];
                $pdata['num']        = $order['hxnum'];
                $pdata['ogid']       = $order['ogdata']['id'];

                $pdata['createtime'] = time();
                Db::name('hexiao_restaurantproduct')->insert($pdata);

                if($order['hxnum'] + $order['ogdata']['hexiao_num'] == $order['ogdata']['num']){
                    $totalhxnum = Db::name('restaurant_takeaway_order_goods')->where('orderid',$order['id'])->sum('hexiao_num');
                    $totalnum   = Db::name('restaurant_takeaway_order_goods')->where('orderid',$order['id'])->sum('num');
                    if($totalhxnum >= $totalnum){
                        $rs = \app\customs\Restaurant::takeaway_orderconfirm($order['id']);
                        if($rs['status']==0){
                            return $this->json($rs);
                        }
                    }
                }
            } elseif($type=='hbtk'){
                if(!$plat_hexiao && !getcustom('yx_hbtk')) return $this->json(['status'=>0,'msg'=>'登录的账号不是该商家的管理账号']);
                $order = Db::name('hbtk_order')->where(['aid'=>aid,'code'=>$code])->find();
                if(!$order) return $this->json(['status'=>0,'msg'=>'活动不存在']);
                if($order['status']==2) return $this->json(['status'=>0,'msg'=>'活动已核销']);
                $data = array();
                $data['aid'] = aid;
                $data['bid'] = bid;
                $data['uid'] = $this->uid;
                $data['mid'] = $order['mid'];
                $data['orderid'] = $order['id'];
                $data['ordernum'] = date('ymdHis').aid.rand(1000,9999);
                $data['title'] = $order['name'];
                $data['type'] = $type;
                $data['createtime'] = time();
                $data['remark'] = '核销员['.$this->user['un'].']核销';
                $data['mdid']   = empty($this->user['mdid'])?0:$this->user['mdid'];
                Db::name('hexiao_order')->insert($data);
                Db::name($type.'_order')->where(['aid'=>aid,'code'=>$code])->update(['status'=>2,'hxtime' => time()]);
                //核销
                if($order['pid'] > 0){
                    $hd = Db::name('hbtk_activity')->where('id',aid)->where('id',$order['hid'])->find();
                    $jxtp =  $order['jxtp'];
                    $jxmc = $order['jxmc'];
                    $oldjxmc = $jxmc;
                    if($jxtp==1){

                    }elseif($jxtp==2){
                        $jxmc =  $jxmc.'元红包';
                    }elseif($jxtp==3){
                        $jxmc =  '优惠券(ID:'.$jxmc.')';
                    }elseif($jxtp==4){
                        $jxmc =  $jxmc.'积分';
                    }elseif($jxtp==5){
                        $jxmc =  $jxmc.'元'.t('余额');
                    }
                    if($jxtp == 2){
                        srand(microtime(true) * 1000);
                        $moneyArr = explode('-',str_replace('~','-',$jxmc));
                        if(!$moneyArr[1]) $moneyArr[1] = $moneyArr[0];
                        $ss = rand($moneyArr[0]*100,$moneyArr[1]*100).PHP_EOL;
                        $money = number_format($ss/100, 2, '.', '');
                        $jxmc = $money.'元红包';
                        $rs = \app\commons\Wxpay::sendredpackage(aid,$order['pid'],platform,$money,mb_substr($order['name'],0,10),'微信红包','恭喜发财','微信红包',$hd['scene_id']);
                        if($rs['status']==0){ //发放失败
                            Db::name('hbtk_order')->where('id',$order['id'])->update(['jxmc'=>$jxmc,'remark'=>$rs['msg']]);
                        }else{
                            Db::name('hbtk_order')->where('id',$order['id'])->update(['jxmc'=>$jxmc,'status'=>2,'remark'=>'发放成功']);
                            if(platform == 'wx'){//小程序红包
                                $appinfo = \app\commons\System::appinfo(aid,platform);
                                $appid = $appinfo['appid'];
                                $mchkey = $appinfo['wxpay_mchkey'];
                                $spdata = [];
                                $spdata['appId'] = $appid;
                                $spdata['timeStamp'] = strval(time());
                                $spdata['nonceStr'] = random(16);
                                $spdata['package'] = urlencode($rs['resp']['package']);
                                ksort($spdata, SORT_STRING);
                                $string1 = '';
                                foreach ($spdata as $key => $v) {
                                    if (empty($v)) {
                                        continue;
                                    }
                                    $string1 .= "{$key}={$v}&";
                                }
                                $string1 .= "key={$mchkey}";
                                $spdata['signType'] = 'MD5';
                                $spdata['paySign'] = md5($string1);
                            }
                        }
                    }
                    //优惠券
                    if($jxtp==3){
                        $rs = \app\commons\Coupon::send(aid,$order['pid'],$oldjxmc);
                        if($rs['status']==0){ //发放失败
                            Db::name('hbtk_order')->where('id',$order['id'])->update(['jxmc'=>$jxmc,'remark'=>$rs['msg']]);
                        }else{
                            Db::name('hbtk_order')->where('id',$order['id'])->update(['jxmc'=>$jxmc,'status'=>2,'remark'=>'发放成功']);
                        }
                    }
                    //积分
                    if($jxtp==4){
                        $rs = \app\commons\Member::addscore(aid,$order['pid'],$oldjxmc,$order['name'].'-拓客活动');
                        if($rs['status']==0){ //发放失败
                            Db::name('hbtk_order')->where('id',$order['id'])->update(['jxmc'=>$jxmc,'remark'=>$rs['msg']]);
                        }else{
                            Db::name('hbtk_order')->where('id',$order['id'])->update(['jxmc'=>$jxmc,'status'=>2,'remark'=>'发放成功']);
                        }
                    }
                    //余额
                    if($jxtp==5){
                        $rs = \app\commons\Member::addmoney(aid,$order['pid'],$oldjxmc,$order['name']);
                        if($rs['status']==0){ //发放失败
                            Db::name('hbtk_order')->where('id',$order['id'])->update(['jxmc'=>$jxmc,'remark'=>$rs['msg']]);
                        }else{
                            Db::name('hbtk_order')->where('id',$order['id'])->update(['jxmc'=>$jxmc,'status'=>2,'remark'=>'发放成功']);
                        }
                    }
                }
            }elseif($type=='verifyauth'){
                }else if($type=='hotel'){
                $data = array();
                $data['aid'] = aid;
                $data['bid'] = bid;
                $data['uid'] = $this->uid;
                $data['mid'] = $order['mid'];
                $data['orderid'] = $order['id'];
                $data['ordernum'] = $order['ordernum'];
                $data['title'] = $order['title'];
                $data['type'] = $type;
                $data['createtime'] = time();
                $data['remark'] = '核销员['.$this->user['un'].']核销';
                $data['mdid']   = empty($this->user['mdid'])?0:$this->user['mdid'];
                Db::name('hexiao_order')->insert($data);
                $remark = $order['remark'] ? $order['remark'].' '.$data['remark'] : $data['remark'];
				db($type.'_order')->where(['aid'=>aid,'hexiao_code'=>$code])->update(['status'=>3,'daodian_time'=>time(),'remark'=>$remark]);
			}
            return $this->json(['status'=>1,'msg'=>'核销成功']);
		}
        $hexiao_type = 0;
        return $this->json(['order'=>$order,'status'=>1,'hexiao_type'=>$hexiao_type,'type'=>$type,'mendian_no_select'=>$mendian_no_select]);
	}
	//核销记录
	public function record(){
		$pagenum = input('post.pagenum');
		$type = input('post.type/d',0);
		if(!$pagenum) $pagenum = 1;
		$pernum = 20;
		$where = [];
		$where[] = ['order.aid','=',aid];
		$where[] = ['order.bid','=',bid];

		if(false){}else{
            if($this->user['mdid']){
                $where[] = ['uid','=',$this->user['id']];
            }
        }
		if(input('param.keyword')) $where[] = ['member.nickname','like','%'.trim(input('param.keyword')).'%'];
		if(input('?param.status') && input('param.status')!=='') $where[] = ['member_moneylog.status','=',input('param.status')];
		$datalist = Db::name('hexiao_order')->alias('order')->field('member.nickname,member.headimg,order.*')->join('member member','member.id=order.mid')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
		if($pagenum==1){
			$count = 0 + Db::name('hexiao_order')->alias('order')->field('member.nickname,member.headimg,order.*')->join('member member','member.id=order.mid')->where($where)->count();
		}
        //核销记录按年月分组
        $showgroup = false;
        return $this->json(['status'=>1,'count'=>$count,'data'=>$datalist,'showgroup'=>$showgroup]);
	}
    /*
     * 1.本年度按月分
     * 2.之前年份按年分且折叠
     */
    public function recordGroup(){
        return $this->json(['status'=>0,'msg'=>'功能未开放']);
    }
    public function recordMonthList(){
        return $this->json(['status'=>0,'msg'=>'功能未开放']);
    }



    public function couponTimesCheck($aid,$bid,$couponrecord=[]){
        if(getcustom('coupon_times_expire') || getcustom('coupon_times_use_gap')){
            if($couponrecord['type']!=3){
                return ['status'=>1,'msg'=>''];
            }
            $where  = [];
            $where['aid'] = $aid;
            $where['bid'] = $bid;
            $where['mid'] = $couponrecord['mid'];
            $where['orderid'] = $couponrecord['id'];
            $where['type'] = 'coupon';
            $exist = Db::name('hexiao_order')->where($where)->order('id desc')->find();
            if(empty($exist)){
                return ['status'=>1,'msg'=>''];
            }
            $couponinfo = Db::name('coupon')->where('id',$couponrecord['couponid'])->find();
            if(empty($couponinfo)){
                return ['status'=>1,'msg'=>''];
            }
            $use_gap = $couponinfo['use_gap'];
            if($use_gap<=0){
                return ['status'=>1,'msg'=>''];
            }
            $useGapTime = $use_gap * 86400;
            $use_gap = round($use_gap,2);
            if(time() - $exist['createtime']<$useGapTime){
                return ['status'=>0,'msg'=>"该优惠券使用间隔必须大于{$use_gap}天"];
            }
            return ['status'=>1,'msg'=>''];
        }
    }

}