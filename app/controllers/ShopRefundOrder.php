<?php


// +----------------------------------------------------------------------
// | 商城-退款订单
// +----------------------------------------------------------------------
namespace app\controllers;
use app\commons\Order;
use think\facade\View;
use think\facade\Db;
class ShopRefundOrder extends Common
{
	//订单列表
    public function index(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = 'ro.'.input('param.field').' '.input('param.order');
			}else{
				$order = 'ro.id desc';
			}
			$where = [];
            $where[] = ['ro.aid','=',aid];
            if(bid==0){
                if(input('param.bid')){
                    $where[] = ['ro.bid','=',input('param.bid')];
                }elseif(input('param.showtype')==2){
                    $where[] = ['ro.bid','<>',0];
                }elseif(input('param.showtype')=='all'){
                    $where[] = ['ro.bid','>=',0];
                }else{
                    $where[] = ['ro.bid','=',0];
                }
            }else{
                $where[] = ['ro.bid','=',bid];
            }
            if($this->mdid){
                $where[] = ['ro.mdid','=',$this->mdid];
            }
            if(input('param.mid')) $where[] = ['ro.mid','=',input('param.mid')];
            if(input('param.refund_ordernum')) $where[] = ['ro.refund_ordernum','like','%'.input('param.refund_ordernum').'%'];
            if(input('param.orderid')) $where[] = ['ro.orderid','=',input('param.orderid')];
            if(input('param.ordernum')) $where[] = ['ro.ordernum','like','%'.input('param.ordernum').'%'];
            if(input('param.express_no')) $where[] = ['ro.express_no','like','%'.input('param.express_no').'%'];
            if(input('param.linkman')) $where[] = ['o.linkman','like','%'.input('param.linkman').'%'];
            if(input('param.tel')) $where[] = ['o.tel','like','%'.input('param.tel').'%'];
            if(input('param.ctime') ){
                $ctime = explode(' ~ ',input('param.ctime'));
                $where[] = ['ro.createtime','>=',strtotime($ctime[0])];
                $where[] = ['ro.createtime','<',strtotime($ctime[1]) + 86400];
            }
            if(input('?param.status') && input('param.status') === '0'){
                $where[] = ['ro.refund_status','=',0];
            }elseif(input('param.status') == 1){
                $where[] = ['ro.refund_status','=',1];
            }elseif(input('param.status') == 2){
                $where[] = ['ro.refund_status','=',2];
            }elseif(input('param.status') == 3){
                $where[] = ['ro.refund_status','=',3];
            }elseif(input('param.status') == 4 || input('param.status') == 41){
                $where[] = ['ro.refund_status','=',4];
                if(input('param.status') == 41){
	                $where[] = ['ro.isexpress','=',1];
	            }else{
	            	$where[] = ['ro.isexpress','=',0];
	            }
            }
            $count = 0 + Db::name('shop_refund_order')->alias('ro')->leftJoin('shop_order o','ro.orderid=o.id')->where($where)->count('ro.id');
            //echo M()->_sql();
            $list = Db::name('shop_refund_order')->alias('ro')->leftJoin('shop_order o','ro.orderid=o.id')->where($where)->field('ro.*,o.tel')->page($page,$limit)->order($order)->select()->toArray();

            foreach($list as $k=>$vo){
				$oglist = Db::name('shop_refund_order_goods')->where('aid',aid)->where('refund_orderid',$vo['id'])->select()->toArray();
				$goodsdata=array();
				foreach($oglist as $og){
					$goodsdata[] = '<div style="font-size:12px;float:left;clear:both;margin:1px 0">'.
						'<div class="table-imgbox"><img lay-src="'.$og['pic'].'" src="'.PRE_URL.'/static/admin/layui/css/modules/layer/default/loading-2.gif"></div>'.
						'<div style="float: left;width:160px;margin-left: 10px;white-space:normal;line-height:16px;">'.
							'<div style="width:100%;min-height:25px;max-height:32px;overflow:hidden">'.$og['name'].'</div>'.
							'<div style="padding-top:0px;color:#f60"><span style="color:#888">'.$og['ggname'].'</span></div>'.
							'<div style="padding-top:0px;color:#f60;">￥'.$og['sell_price'].' × '.$og['refund_num'].'</div>'.
						'</div>'.
					'</div>';
				}
				$list[$k]['goodsdata'] = implode('',$goodsdata);
				if($vo['bid'] > 0){
					$list[$k]['bname'] = Db::name('business')->where('aid',aid)->where('id',$vo['bid'])->value('name');
				}else{
					$list[$k]['bname'] = '平台自营';
				}

                $list[$k]['cancheck'] = true;
                //快递公司信息
				// $expressdata = '';
				// if($vo['express_content']){
				// 	$express_content = json_decode($vo['express_content'],true);
				// 	if($express_content){
				// 		foreach($express_content as $ev){
				// 			$expressdata .= '快递公司：'.$ev['express_com'].'<br>';
				// 			$expressdata .= '快递单号：'.$ev['express_no'].'<br>';
				// 		}
				// 	}
				// }
				// $list[$k]['expressdata'] = $expressdata;
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$list]);
		}
        $hasprint = 0;
        View::assign('hasprint',$hasprint);
		return View::fetch();
    }
	//订单详情
	public function getdetail(){
		$orderid = input('param.orderid');
		if(bid != 0){
			$order = Db::name('shop_refund_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->find();
		}else{
			$order = Db::name('shop_refund_order')->where('aid',aid)->where('id',$orderid)->find();
		}
		if($order['refund_type'] == 'refund') {
            $order['refund_type_label'] = '退款';
        }elseif($order['refund_type'] == 'return') {
            $order['refund_type_label'] = '退货退款';
        }
		if($order['refund_pics']) {
            $order['refund_pics'] = explode(',', $order['refund_pics']);
            foreach ($order['refund_pics'] as $item) {
                $order['refund_pics_html'] .= '<img src="'.$item.'" width="200"/>';
            }
        }
        $order['refundMoneyTotal'] =  Db::name('shop_refund_order')->where('aid',aid)->where('bid',bid)->where('orderid',$order['orderid'])->where('refund_status',2)->sum('refund_money');
		$oglist = Db::name('shop_refund_order_goods')->where('aid',aid)->where('refund_orderid',$orderid)->select()->toArray();
		$member = Db::name('member')->field('id,nickname,headimg,realname,tel,wxopenid,unionid')->where('id',$order['mid'])->find();
		if(!$member) $member = ['id'=>$order['mid'],'nickname'=>'','headimg'=>''];
		if(bid != 0){
			$orderdetail = Db::name('shop_order')->where('id',$order['orderid'])->where('aid',aid)->where('bid',bid)->find();
		}else{
			$orderdetail = Db::name('shop_order')->where('id',$order['orderid'])->where('aid',aid)->find();
		}
        //学校信息
        $order['cancheck'] = true;
        //判断是否有回寄信息
        if($detail['refund_status'] ==4 && !$detail['isexpress']){
            if(!$detail['return_address'] && !$detail['return_name']){
                $detail['return_address'] = '等待商家填写';
            }
        }
        $rdata = ['order'=>$order,'oglist'=>$oglist,'member'=>$member,'orderdetail'=>$orderdetail];
        return json($rdata);
	}

	//退款审核
	public function refundCheck(){
		$orderid = input('post.orderid/d');
		$st = input('post.st/d');
		$remark = input('post.remark');
		if(bid == 0){
			$order = Db::name('shop_refund_order')->where('id',$orderid)->where('aid',aid)->find();
			$orderOrigin = Db::name('shop_order')->where('id',$order['orderid'])->where('aid',aid)->find();
		}else{
			$order = Db::name('shop_refund_order')->where('id',$orderid)->where('aid',aid)->where('bid',bid)->find();
			$orderOrigin = Db::name('shop_order')->where('id',$order['orderid'])->where('aid',aid)->where('bid',bid)->find();
		}

        $reog = Db::name('shop_refund_order_goods')->where('refund_orderid',$orderid)->select();
		if($st==2){
			//聚水潭售后订单驳回
			Db::name('shop_refund_order')->where('id',$orderid)->where('aid',aid)->where('bid',$order['bid'])->update(['refund_status'=>3,'refund_checkremark'=>$remark]);
			foreach ($reog as $item) {
                Db::name('shop_order_goods')->where('id',$item['ogid'])->where('orderid',$orderOrigin['id'])
                    ->dec('refund_num', $item['refund_num'])->update();
            }
			
			if($orderOrigin['fromwxvideo'] == 1){
				\app\commons\Wxvideo::aftersaleupdate($order['orderid'],$order['id']);
			}

			//退款申请驳回通知
			$tmplcontent = [];
			$tmplcontent['first'] = '您的退款申请被商家驳回，可与商家协商沟通。';
			$tmplcontent['remark'] = $remark.'，请点击查看详情~';
			$tmplcontent['orderProductPrice'] = $order['refund_money'];
			$tmplcontent['orderProductName'] = $orderOrigin['title'];
			$tmplcontent['orderName'] = $order['ordernum'];
            $tmplcontentNew = [];
            $tmplcontentNew['character_string1'] = $order['ordernum'];//订单编号
            $tmplcontentNew['thing2'] = $orderOrigin['title'];//商品名称
            $tmplcontentNew['amount3'] = $order['refund_money'];//退款金额
			\app\commons\Wechat::sendtmpl(aid,$order['mid'],'tmpl_tuierror',$tmplcontent,m_url('pages/my/usercenter'),$tmplcontentNew);
			//订阅消息
			$tmplcontent = [];
			$tmplcontent['amount3'] = $order['refund_money'];
			$tmplcontent['thing2'] = $orderOrigin['title'];
			$tmplcontent['character_string1'] = $order['ordernum'];
			
			$tmplcontentnew = [];
			$tmplcontentnew['amount3'] = $order['refund_money'];
			$tmplcontentnew['thing8'] = $orderOrigin['title'];
			$tmplcontentnew['character_string4'] = $order['ordernum'];
			\app\commons\Wechat::sendwxtmpl(aid,$order['mid'],'tmpl_tuierror',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);
			//短信通知
			$member = Db::name('member')->where('id',$order['mid'])->find();
			if($member['tel']){
				$tel = $member['tel'];
			}else{
				$tel = $order['tel'];
			}
			$rs = \app\commons\Sms::send(aid,$tel,'tmpl_tuierror',['ordernum'=>$order['ordernum'],'reason'=>$remark]);
			\app\commons\System::plog('商城订单退款驳回'.$orderid);
			return json(['status'=>1,'msg'=>'退款已驳回']);
		}elseif($st == 1){
			if($orderOrigin['status']!=1 && $orderOrigin['status']!=2){
				return json(['status'=>0,'msg'=>'该订单状态不允许退款']);
			}
			if($order['refund_money'] > 0){
                $is_refund = 1;
                if($is_refund){
                    $params = [];
                    $rs = \app\commons\Order::refund($orderOrigin,$order['refund_money'],$order['refund_reason'],$params);
                    if($rs['status']==0){
                        if($orderOrigin['balance_price'] > 0){
                            $orderOrigin2 = $orderOrigin;
                            $orderOrigin2['totalprice'] = $orderOrigin2['totalprice'] - $orderOrigin2['balance_price'];
                            $orderOrigin2['ordernum'] = $orderOrigin2['ordernum'].'_0';
                            $rs = \app\commons\Order::refund($orderOrigin2,$order['refund_money'],$order['refund_reason']);
                            if($rs['status']==0){
                                return json(['status'=>0,'msg'=>$rs['msg']]);
                            }
                            if($orderOrigin['balance_pay_status'] == 0){
                                $orderOrigin['totalprice'] = $orderOrigin['totalprice'] - $orderOrigin['balance_price'];
                            }
                        }else{
                            return json(['status'=>0,'msg'=>$rs['msg']]);
                        }
                    }
                }
			}

			Db::name('shop_refund_order')->where('id',$orderid)->where('aid',aid)->where('bid',$order['bid'])->update(['status'=>4,'refund_status'=>2,'refund_checkremark'=>$remark]);
            $reOrder = Db::name('shop_refund_order')->where('orderid',$order['orderid'])->where('refund_status', 'in', [2,4])->where('aid',aid)->select();
            $refundTotal = 0;
            foreach ($reOrder as $item) {
                $refundTotal += $item['refund_money'];
            }
            $refundTotal = round($refundTotal,2);
            $orderOrigin['totalprice'] = round($orderOrigin['totalprice'],2);

            //整单全部退时 返还积分和优惠券
            $canRefundNum = 0;
            $prolist = Db::name('shop_order_goods')->where('orderid',$orderOrigin['id'])->select()->toArray();
            foreach ($prolist as $key => $item) {
                $canRefundNum += $item['num'] - $item['refund_num'];
                //退款减去商户销量
                \app\models\Payorder::addSales($orderOrigin['id'],'shop',$orderOrigin['aid'],$orderOrigin['bid'],-$item['refund_num']);
            }
            if($canRefundNum == 0 && $refundTotal == $orderOrigin['totalprice']) {
                
                
                	\app\commons\Aaa::statusOrder(aid,$orderid,$order);
                
                
                
                
                Db::name('shop_order')->where('id',$order['orderid'])->where('aid',aid)->where('bid',$order['bid'])->update(['status'=>4,'refund_status'=>2,'refund_money' => $refundTotal]);
                Db::name('shop_order_goods')->where('orderid',$order['orderid'])->where('aid',aid)->where('bid',$order['bid'])->update(['status'=>4]);
                //积分抵扣的返还
                if($orderOrigin['scoredkscore'] > 0){
                    \app\commons\Member::addscore(aid,$orderOrigin['mid'],$orderOrigin['scoredkscore'],'订单退款返还');
                }
                //扣除消费赠送积分
                \app\commons\Member::decscorein(aid,'shop',$orderOrigin['id'],$orderOrigin['ordernum'],'订单退款扣除消费赠送');
if($orderOrigin['creditdkcredit'] > 0){
    				\app\commons\Member::addcredit2(aid,$orderOrigin['mid'],$orderOrigin['creditdkcredit'],'订单退款返还');
    			}
                
                //查询后台是否开启退还已使用的优惠券
                $return_coupon = Db::name('shop_sysset')->where('aid',aid)->value('return_coupon');
                //优惠券抵扣的返还
                if($return_coupon && $orderOrigin['coupon_rid'] > 0){
                    Db::name('coupon_record')->where('aid',aid)->where(['mid'=>$orderOrigin['mid'],'id'=>$orderOrigin['coupon_rid']])->update(['status'=>0,'usetime'=>'']);
                }
                //元宝返回
                if(getcustom('money_dec')){
                    if($orderOrigin['dec_money']>0){
                        \app\commons\Member::addmoney(aid,$orderOrigin['mid'],$orderOrigin['dec_money'],t('余额').'抵扣返回，订单号: '.$orderOrigin['ordernum']);
                    }
                }
                //退款退还佣金
                //恢复额度
				} else {
                //部分退款
                Db::name('shop_order')->where('id',$order['orderid'])->where('aid',aid)->where('bid',$order['bid'])->inc('refund_money',$order['refund_money'])->update(['refund_status'=>2]);
                //重新计算佣金
                \app\commons\Order::updateCommission($prolist,$reog);

					//恢复额度
				}

            //恢复库存 删除销量
			foreach ($reog as $item) {
				Db::name('shop_guige')->where('aid',aid)->where('id',$item['ggid'])->update(['stock'=>Db::raw("stock+".$item['refund_num']),'sales'=>Db::raw("sales-".$item['refund_num'])]);
				Db::name('shop_product')->where('aid',aid)->where('id',$item['proid'])->update(['stock'=>Db::raw("stock+".$item['refund_num']),'sales'=>Db::raw("sales-".$item['refund_num'])]);
                //份额改为失效
                }

			//卖家已经同意，买家已退货
			if($orderOrigin['fromwxvideo'] == 1){
				\app\commons\Wxvideo::aftersaleupdate($order['orderid'],$order['id']);
			}
            if($orderOrigin['platform'] == 'toutiao'){
				\app\commons\Ttpay::pushorder(aid,$orderOrigin['ordernum'],6);
			}
			//erp旺店通退款
            //退款成功通知
			$tmplcontent = [];
			$tmplcontent['first'] = '您的订单已经完成退款，¥'.$order['refund_money'].'已经退回您的付款账户，请留意查收。';
			$tmplcontent['remark'] = $remark.'，请点击查看详情~';
			$tmplcontent['orderProductPrice'] = $order['refund_money'];
			$tmplcontent['orderProductName'] = $orderOrigin['title'];
			$tmplcontent['orderName'] = $order['ordernum'];
            $tmplcontentNew = [];
            $tmplcontentNew['character_string1'] = $order['ordernum'];//订单编号
            $tmplcontentNew['thing2'] = $order['title'];//商品名称
            $tmplcontentNew['amount3'] = $order['refund_money'];//退款金额
			\app\commons\Wechat::sendtmpl(aid,$orderOrigin['mid'],'tmpl_tuisuccess',$tmplcontent,m_url('pages/my/usercenter'),$tmplcontentNew);
			//订阅消息
			$tmplcontent = [];
			$tmplcontent['amount6'] = $order['refund_money'];
			$tmplcontent['thing3'] = $orderOrigin['title'];
			$tmplcontent['character_string2'] = $order['ordernum'];
			
			$tmplcontentnew = [];
			$tmplcontentnew['amount3'] = $order['refund_money'];
			$tmplcontentnew['thing6'] = $orderOrigin['title'];
			$tmplcontentnew['character_string4'] = $order['ordernum'];
			\app\commons\Wechat::sendwxtmpl(aid,$orderOrigin['mid'],'tmpl_tuisuccess',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);

			//短信通知
			$member = Db::name('member')->where('id',$order['mid'])->find();
			if($member['tel']){
				$tel = $member['tel'];
			}else{
				$tel = $orderOrigin['tel'];
			}
			$rs = \app\commons\Sms::send(aid,$tel,'tmpl_tuisuccess',['ordernum'=>$order['ordernum'],'money'=>$order['refund_money']]);
			
			\app\commons\System::plog('商城订单退款审核通过并退款'.$orderid);
			return json(['status'=>1,'msg'=>'已退款成功']);
		}
	}

    //退货退款-审核
    public function returnCheck(){
        $orderid = input('post.orderid/d');
        $st = input('post.st/d');
        $remark = input('post.remark');
		if(bid == 0){
			$order = Db::name('shop_refund_order')->where('id',$orderid)->where('aid',aid)->find();
			$orderOrigin = Db::name('shop_order')->where('id',$order['orderid'])->where('aid',aid)->find();
		}else{
			$order = Db::name('shop_refund_order')->where('id',$orderid)->where('aid',aid)->where('bid',bid)->find();
			$orderOrigin = Db::name('shop_order')->where('id',$order['orderid'])->where('aid',aid)->where('bid',bid)->find();
		}
        if($orderOrigin['status']!=1 && $orderOrigin['status']!=2){
            return json(['status'=>0,'msg'=>'该订单状态不允许退款']);
        }
        $refundUpdate = [];
        $refundUpdate['refund_status']      = 4;
        $refundUpdate['refund_checkremark'] = $remark;

        $returnField = 'return_name,return_tel,return_province,return_city,return_area,return_address';
        if(bid > 0){
            $return = Db::name('business')->where('aid',aid)->where('id',bid)->field($returnField)->find();
        }else{
            $return = Db::name('shop_sysset')->where('aid',aid)->field($returnField)->find();
        }
        if(empty($return) || empty($return['return_tel']) || empty($return['return_name']) || empty($return['return_province']) || empty($return['return_address'])){
        	if(!bid){
        		return json(['status'=>0,'msg'=>'请先在菜单商城-系统设置中设置完整的退货地址信息']);
        	}else{
        		return json(['status'=>0,'msg'=>'请先在菜单系统-系统设置中设置完整的退货地址信息']);
        	}
        }

        $refundUpdate['return_name']     = $return['return_name'];
        $refundUpdate['return_tel']      = $return['return_tel'];
        $refundUpdate['return_province'] = $return['return_province'];
        $refundUpdate['return_city']     = $return['return_city'];
        $refundUpdate['return_area']     = $return['return_area'];
        $refundUpdate['return_address']  = $return['return_address'];

        Db::name('shop_refund_order')->where('id',$orderid)->where('aid',aid)->where('bid',$order['bid'])->update($refundUpdate);
        //退款减去商户销量
//        $refund_num = Db::name('shop_refund_order_goods')->where('refund_orderid',$orderid)->sum('refund_num');
//        \app\models\Payorder::addSales($orderOrigin['id'],'shop',$orderOrigin['aid'],$orderOrigin['bid'],-$refund_num);
//		if($orderOrigin['fromwxvideo'] == 1){
//			\app\commons\Wxvideo::aftersaleupdate($order['orderid'],$order['id']);
//		}
        //erp旺店通退款
        //退款同意通知
        $tmplcontent = [];
        $tmplcontent['first'] = '商家已同意您的退货退款申请，请及时联系商家退货，商家收到退货后将进行退款';
        $tmplcontent['remark'] = $remark.'，请点击查看详情~';
        $tmplcontent['orderProductPrice'] = $order['refund_money'];
        $tmplcontent['orderProductName'] = $orderOrigin['title'];
        $tmplcontent['orderName'] = $order['ordernum'];
        $tmplcontentNew = [];
        $tmplcontentNew['character_string1'] = $order['ordernum'];//订单编号
        $tmplcontentNew['thing2'] = $orderOrigin['title'];//商品名称
        $tmplcontentNew['amount3'] = $order['refund_money'];//退款金额
        \app\commons\Wechat::sendtmpl(aid,$orderOrigin['mid'],'tmpl_tuisuccess',$tmplcontent,m_url('pages/my/usercenter'),$tmplcontentNew);
        //订阅消息
        $tmplcontent = [];
        $tmplcontent['amount6'] = $order['refund_money'];
        $tmplcontent['thing3'] = $orderOrigin['title'];
        $tmplcontent['character_string2'] = $order['ordernum'];
		
		$tmplcontentnew = [];
		$tmplcontentnew['amount3'] = $order['refund_money'];
		$tmplcontentnew['thing6'] = $orderOrigin['title'];
		$tmplcontentnew['character_string4'] = $order['ordernum'];
        \app\commons\Wechat::sendwxtmpl(aid,$orderOrigin['mid'],'tmpl_tuisuccess',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);

        //短信通知
//        $member = Db::name('member')->where('id',$order['mid'])->find();
//        if($member['tel']){
//            $tel = $member['tel'];
//        }else{
//            $tel = $orderOrigin['tel'];
//        }
//        $rs = \app\commons\Sms::send(aid,$tel,'tmpl_tuisuccess',['ordernum'=>$order['ordernum'],'money'=>$order['refund_money']]);


		//聚水潭售后订单审核通过等待退货
		\app\commons\System::plog('商城订单退款审核通过等待退货'.$orderid);
        return json(['status'=>1,'msg'=>'审核通过']);

    }
	//退货退款-退款操作
	public function refund(){
		$orderid = input('post.orderid/d');
		if(bid == 0){
			$order = Db::name('shop_refund_order')->where('id',$orderid)->where('aid',aid)->find();
			$orderOrigin = Db::name('shop_order')->where('id',$order['orderid'])->where('aid',aid)->find();
		}else{
			$order = Db::name('shop_refund_order')->where('id',$orderid)->where('aid',aid)->where('bid',bid)->find();
			$orderOrigin = Db::name('shop_order')->where('id',$order['orderid'])->where('aid',aid)->where('bid',bid)->find();
		}

		$refund_money = $order['refund_money'];
		if($orderOrigin['status']!=1 && $orderOrigin['status']!=2){
			return json(['status'=>0,'msg'=>'该订单状态不允许退款']);
		}
        $is_refund = 1;
        if($is_refund){
            $params = [];
            $rs = \app\commons\Order::refund($orderOrigin,$refund_money,$order['refund_reason'],$params);

            if($rs['status']==0){
                if($orderOrigin['balance_price'] > 0){
                    $orderOrigin2 = $orderOrigin;
                    $orderOrigin2['totalprice'] = $orderOrigin2['totalprice'] - $orderOrigin2['balance_price'];
                    $orderOrigin2['ordernum'] = $orderOrigin2['ordernum'].'_0';
                    $rs = \app\commons\Order::refund($orderOrigin2,$refund_money,$order['refund_reason']);
                    if($rs['status']==0){
                        return json(['status'=>0,'msg'=>$rs['msg']]);
                    }
                    if($orderOrigin['balance_pay_status'] == 0){
                        $orderOrigin['totalprice'] = $orderOrigin['totalprice'] - $orderOrigin['balance_price'];
                    }
                }else{
                    return json(['status'=>0,'msg'=>$rs['msg']]);
                }
            }
        }

        Db::name('shop_refund_order')->where('id',$orderid)->where('aid',aid)->where('bid',$order['bid'])->update(['refund_status'=>2]);
        $reOrder = Db::name('shop_refund_order')->where('orderid',$order['orderid'])->where('refund_status', 'in', [2,4])->where('aid',aid)->select();
        $refundTotal = 0;
        foreach ($reOrder as $item) {
            //refund_status 本单=4也算，其他单必须=2才算  0取消 1申请退款审核中 2已同意退款 4同意待退货 3已驳回
            if($item['id'] == $orderid || ($item['id'] != $orderid && $item['refund_status'] == 2)){
                $refundTotal += $item['refund_money'];
            }
        }
        //退款减去商户销量
        $refund_num = Db::name('shop_refund_order_goods')->where('refund_orderid',$orderid)->sum('refund_num');
        \app\models\Payorder::addSales($orderOrigin['id'],'shop',$orderOrigin['aid'],$orderOrigin['bid'],-$refund_num);

        //整单全部退时 返还积分和优惠券
        $canRefundNum = 0;
        $prolist = Db::name('shop_order_goods')->where('orderid',$orderOrigin['id'])->select()->toArray();
        foreach ($prolist as $key => $item) {
            $canRefundNum += $item['num'] - $item['refund_num'];
        }

        $reog = Db::name('shop_refund_order_goods')->where('refund_orderid',$orderid)->select()->toArray();
        if($canRefundNum == 0 && $refundTotal == $orderOrigin['totalprice']) {
            //整单退款
            \app\commons\Aaa::statusOrder(aid,$orderid,$orderOrigin);
                
                
            Db::name('shop_order')->where('id',$order['orderid'])->where('aid',aid)->where('bid',$order['bid'])->update(['status'=>4,'refund_status'=>2, 'refund_money' => $refundTotal]);
            //积分抵扣的返还
            if($orderOrigin['scoredkscore'] > 0){
                \app\commons\Member::addscore(aid,$orderOrigin['mid'],$orderOrigin['scoredkscore'],'订单退款返还');
            }
            //扣除消费赠送积分
            \app\commons\Member::decscorein(aid,'shop',$orderOrigin['id'],$orderOrigin['ordernum'],'订单退款扣除消费赠送');
       if($orderOrigin['creditdkcredit'] > 0){
				\app\commons\Member::addcredit2(aid,$orderOrigin['mid'],$orderOrigin['creditdkcredit'],'订单退款返还');
			}
            //优惠券抵扣的返还
            if($orderOrigin['coupon_rid'] > 0){
                Db::name('coupon_record')->where('aid',aid)->where(['mid'=>$orderOrigin['mid'],'id'=>$orderOrigin['coupon_rid']])->update(['status'=>0,'usetime'=>'']);
            }
            //元宝返回
            if(getcustom('money_dec')){
                if($orderOrigin['dec_money']>0){
                    \app\commons\Member::addmoney(aid,$orderOrigin['mid'],$orderOrigin['dec_money'],t('余额').'抵扣返回，订单号: '.$orderOrigin['ordernum']);
                }
            }
            //退款退还佣金
            //恢复额度
			} else {
            //部分退款
            Db::name('shop_order')->where('id',$order['orderid'])->where('aid',aid)->where('bid',$order['bid'])->inc('refund_money',$refund_money)->update(['refund_status'=>2]);
            //重新计算佣金
            \app\commons\Order::updateCommission($prolist,$reog);

			//恢复额度
			}

        //退服务费
        //恢复库存 删除销量
        foreach ($reog as $item) {
            Db::name('shop_guige')->where('aid',aid)->where('id',$item['ggid'])->update(['stock'=>Db::raw("stock+".$item['refund_num']),'sales'=>Db::raw("sales-".$item['refund_num'])]);
            Db::name('shop_product')->where('aid',aid)->where('id',$item['proid'])->update(['stock'=>Db::raw("stock+".$item['refund_num']),'sales'=>Db::raw("sales-".$item['refund_num'])]);
            //份额改为失效
            }
		if($orderOrigin['fromwxvideo'] == 1){
			\app\commons\Wxvideo::aftersaleupdate($order['orderid'],$order['id']);
		}
        //聚水潭售后订单
		if($orderOrigin['platform'] == 'toutiao'){
			\app\commons\Ttpay::pushorder(aid,$orderOrigin['ordernum'],6);
		}

		//退款成功通知
		$tmplcontent = [];
		$tmplcontent['first'] = '您的订单已经完成退款，¥'.$refund_money.'已经退回您的付款账户，请留意查收。';
		$tmplcontent['remark'] = $order['refund_reason'].'，请点击查看详情~';
		$tmplcontent['orderProductPrice'] = $refund_money;
		$tmplcontent['orderProductName'] = $order['title'];
		$tmplcontent['orderName'] = $order['ordernum'];
        $tmplcontentNew = [];
        $tmplcontentNew['character_string1'] = $order['ordernum'];//订单编号
        $tmplcontentNew['thing2'] = $order['title'];//商品名称
        $tmplcontentNew['amount3'] = $refund_money;//退款金额
		\app\commons\Wechat::sendtmpl(aid,$order['mid'],'tmpl_tuisuccess',$tmplcontent,m_url('pages/my/usercenter'),$tmplcontentNew);
		//订阅消息
		$tmplcontent = [];
		$tmplcontent['amount6'] = $refund_money;
		$tmplcontent['thing3'] = $order['title'];
		$tmplcontent['character_string2'] = $order['ordernum'];
		
		$tmplcontentnew = [];
		$tmplcontentnew['amount3'] = $order['refund_money'];
		$tmplcontentnew['thing6'] = $orderOrigin['title'];
		$tmplcontentnew['character_string4'] = $order['ordernum'];
		\app\commons\Wechat::sendwxtmpl(aid,$order['mid'],'tmpl_tuisuccess',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);

		//短信通知
		$member = Db::name('member')->where('id',$order['mid'])->find();
		if($member['tel']){
			$tel = $member['tel'];
		}else{
			$tel = $order['tel'];
		}
		$rs = \app\commons\Sms::send(aid,$tel,'tmpl_tuisuccess',['ordernum'=>$order['ordernum'],'money'=>$refund_money]);
		
		\app\commons\System::plog('商城订单退款'.$orderid);
		return json(['status'=>1,'msg'=>'已退款成功']);
	}

    //删除
    public function del(){
        $id = input('post.id/d');
		if(bid == 0){
			Db::name('shop_refund_order')->where('aid',aid)->where('id',$id)->delete();
			Db::name('shop_refund_order_goods')->where('aid',aid)->where('refund_orderid',$id)->delete();
		}else{
			Db::name('shop_refund_order')->where('aid',aid)->where('bid',bid)->where('id',$id)->delete();
			Db::name('shop_refund_order_goods')->where('aid',aid)->where('bid',bid)->where('refund_orderid',$id)->delete();
		}
        \app\commons\System::plog('商城订单退款删除'.$id);
        return json(['status'=>1,'msg'=>'删除成功']);
    }
    //打印小票
    public function wifiprint(){
        $id = input('post.id/d');
        $rs = \app\commons\Wifiprint::print(aid,'shop_refund',$id,0);
        return json($rs);
    }

    //查退款订单物流
	public function getExpress(){
		$orderid = input('post.orderid/d');
		$order = Db::name('shop_refund_order')->where('id',$orderid)->where('aid',aid)->find();
		$list = [];
		if($order['express_content']) {
		    $expressArr = json_decode($order['express_content'],true);
		    foreach ($expressArr as $express) {
                if($express['express_com'] == '顺丰速运'){
                    $totel = $order['tel'];
                    $express['express_no'] = $express['express_no'].":".substr($totel,-4);
                }
				if($express['express_ogids']){
					$oglist = Db::name('shop_order_goods')->where('aid',aid)->where('id','in',$express['express_ogids'])->select()->toArray();
				}else{
					$oglist = [];
				}
                $list[] = [
                    'express_no' => $express['express_no'],
                    'express_com' => $express['express_com'],
                    'express_data' => \app\commons\Common::getwuliu($express['express_no'],$express['express_com'],'', aid),
					'oglist'=>$oglist,
                ];
            }
        }
		return json(['status'=>1,'data'=>$list]);
	}

    //填写寄回地址
    public function writeReturnaddress(){
        $orderid = input('post.orderid/d');
        $order = Db::name('shop_refund_order')->where('id',$orderid)->where('aid',aid)->find();
        if(!$order){
            return json(['status'=>0,'msg'=>'订单不存在']);
        }
        if($order['refund_status']!=4 || $order['isexpress']){
            return json(['status'=>0,'msg'=>'订单状态不符']);
        }
        if($order['return_name'] && $order['return_address']){
            return json(['status'=>0,'msg'=>'已有寄回地址']);
        }
        $updata = [];
        $updata['return_name'] = input('post.return_name')?input('post.return_name'):'';
        $updata['return_tel']  = input('post.return_tel')?input('post.return_tel'):'';
        $updata['return_province'] = input('post.return_province')?input('post.return_province'):'';
        $updata['return_city']     = input('post.return_city')?input('post.return_city'):'';
        $updata['return_area']     = input('post.return_area')?input('post.return_area'):'';
        $updata['return_address']  = input('post.return_address')?input('post.return_address'):'';
        $up = Db::name('shop_refund_order')->where('id',$orderid)->update($updata);
        
   
        
        
        \app\commons\System::plog('商城退款订单填写寄回地址'.$orderid);
        return json(['status'=>1,'msg'=>'操作成功']);
    }
}
