<?php


// +----------------------------------------------------------------------
// | 商城-商品订单
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\Log;
use think\facade\View;
use think\facade\Db;
class ShopOrder extends Common
{
    public function initialize(){
        parent::initialize();
    }
    public function iswxshipping(){
        $rs = \app\commons\Wechat::isTradeManaged(aid);
        dump($rs);
        $rs = \app\commons\Wechat::get_delivery_list(aid);
        dump($rs);
        $rs2 = \app\commons\Order::getWxExpressCompany(aid,'申通快递');
        dd($rs2);
    }
    	//改为尾款已支付
	public function upshuzao(){
		$orderid = input('post.orderid/d');
		$order = Db::name('shop_order')->where('aid',aid)->where('id',$orderid)->find();
		$res = \app\commons\Aaa::submitOrder(aid,$order);
		return json($res);
	}
	
		//改为尾款已支付
	public function coloseshuzao(){
		$orderid = input('post.orderid/d');
		$res = \app\commons\Aaa::closeOrder(aid,$orderid);
		return json($res);
	}
	
	
	
	
	
    public function giveCommission(){
        $order =  Db::name('shop_order')->where('id','=',input('param.id'))->find();
        \app\commons\Order::giveCommission($order,'shop');
    }
    public function giveCommissionBatch() {
        $list = Db::name('member_commission_record')->alias('r')->join('shop_order_goods og','r.ogid=og.id')
            ->where('r.aid',aid)->where('r.type','shop')->where('r.status',0)->where('og.status',3)->where('og.iscommission',0)->select()->toArray();
        foreach ($list as $item){
            $order =  Db::name('shop_order')->where('id','=',$item['orderid'])->find();
            \app\commons\Order::giveCommission($order,'shop');
        }

    }
	//订单列表
    public function index(){
        if(getcustom('plug_xiongmao')) {
            $admin = Db::name('admin')->where('id',aid)->find();
            if(in_array('order_show_member_apply', explode(',',$admin['remark']))) {
                $order_show_member_apply = true;
                View::assign('order_show_member_apply',$order_show_member_apply);
            }
        }
        $erpWdtOpen = 0;
        if(getcustom('erp_wangdiantong')){
            $erpWdtOpen = Db::name('wdt_sysset')->where('aid',aid)->value('status');
        }
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = 'order.'.input('param.field').' '.input('param.order');
			}else{
				$order = 'order.id desc';
			}
			$where = [];
			$where[] = ['order.aid','=',aid];
			if(bid==0){
				if(input('param.bid')){
					$where[] = ['order.bid','=',input('param.bid')];
				}elseif(input('param.showtype')==2){
					$where[] = ['order.bid','<>',0];
                }elseif(input('param.showtype')=='all'){
                    $where[] = ['order.bid','>=',0];
				}else{
					$where[] = ['order.bid','=',0];
				}
                if(getcustom('user_area_agent') && $this->user['isadmin']==3){
                    $areaBids = \app\commons\Business::getUserAgentBids(aid,$this->user);
                    $where[] = ['order.bid','in',$areaBids];
                }
			}else{
				$where[] = ['order.bid','=',bid];
			}
            if(getcustom('h5zb')){
                if(input('param.roomid')) $where[] = ['order.roomid','=',input('param.roomid')];
            }
			if($this->user['isadmin']!=1 && $this->mdid){
				$where[] = ['order.mdid','=',$this->mdid];
			}
			if(input('?param.ogid')){
				if(input('param.ogid')==''){
					$where[] = ['1','=',0];
				}else{
					$ids = Db::name('shop_order_goods')->where('id','in',input('param.ogid'))->column('orderid');
					$where[] = ['order.id','in',$ids];
				}
			}
			if(getcustom('product_wholesale')){
				//批发加商品类型搜索
				if(input('param.product_type')!=''){
					$ids = Db::name('shop_order_goods')->alias('shop_order_goods')->join('shop_product shop_product','shop_product.id=shop_order_goods.proid')->fieldRaw('shop_order_goods.orderid')->where('shop_product.product_type','=',input('param.product_type'))->page(0,100000)->order('shop_order_goods.orderid desc')->column('orderid');
					$where[] = ['order.id','in',$ids];
				}
			}
			if(input('param.mid')){
                if(getcustom('order_show_onlychildren')){
                    if($this->admin['order_show_onlychildren']){
                        if($this->user['isadmin'] == 0){
                            $childmids = \app\commons\Member::getdownmids(aid,$this->user['mid']);
                            if(in_array(input('param.mid'),$childmids))
                                $where[] = ['order.mid','=',input('param.mid')];
                            elseif($childmids)
                                $where[] = ['order.mid','in',$childmids];
                        }
                    }else{
                        $where[] = ['order.mid','=',input('param.mid')];
                    }
                }else{
                    $where[] = ['order.mid','=',input('param.mid')];
                }
            } else {
                if(getcustom('order_show_onlychildren')){
                    if($this->admin['order_show_onlychildren']){
                        if($this->user['isadmin'] == 0){
                            $childmids = \app\commons\Member::getdownmids(aid,$this->user['mid']);
                            if($childmids) $where[] = ['order.mid','in',$childmids];
                        }
                    }
                }
            }
            if(getcustom('school_product')) {
                if(input('param.school_id')) $where[] = ['order.school_id','=',input('param.school_id')];
            }
            if(getcustom('product_weight')){
                $where[] = ['order.product_type','<>',2];
            }
            if(getcustom('product_supply_chain')){
                $where[] = ['order.product_type','<>',7];
            }
            if(getcustom('douyin_groupbuy')){
				//抖音团购核销
				if(input('param.isdygroupbuy')){
					$where[] = ['isdygroupbuy','=',1];
				}
			}
			if(getcustom('supply_zhenxin')){
                if(input('?param.source') && input('param.source')!==''){
                	$source = input('param.source');
                	if($source == 'self'){
                		$where[] = ['issource','=',0];
                	}else{
                		$where[] = ['issource','=',1];
                		$where[] = ['source','=',$source];
                	}
                }
                if(input('param.sordernum')) $where[] = ['order.sordernum','like','%'.input('param.sordernum').'%'];
            }
            if(input('param.bid')){
                $where[] = ['order.bid','=',input('param.bid')];
            }
			if(input('param.orderid')) $where[] = ['order.id','=',input('param.orderid')];
            if(input('param.freight_id')) $where[] = ['order.freight_id','=',input('param.freight_id')];
			if(input('param.proname')) $where[] = ['order.proname','like','%'.input('param.proname').'%'];
			if(input('param.ordernum')) $where[] = ['order.ordernum','like','%'.input('param.ordernum').'%'];
            if(input('param.nickname')) $where[] = ['member.nickname|member.realname','like','%'.input('param.nickname').'%'];
            if(input('param.linkman')) $where[] = ['order.linkman|order.tel','like','%'.input('param.linkman').'%'];
			if(input('param.tel')) $where[] = ['order.tel','like','%'.input('param.tel').'%'];
			if(input('param.proid')){
				$orderids = Db::name('shop_order_goods')->where('aid',aid)->where('proid',input('param.proid'))->column('orderid');
				$where[] = ['order.id','in',$orderids];
			}
			if(input('?param.check_status') && input('param.check_status')!==''){
				if(input('param.check_status') == 0){
					$payids = Db::name('payorder')->where('aid',aid)->where('type','shop')->where('check_status',0)->column('id');
			     	$where[] = ['order.payorderid','in',$payids];
				}else{
    				$payids = Db::name('payorder')->where('aid',aid)->where('type','shop')->where('check_status',input('param.check_status'))->column('id');
    				$where[] = ['order.payorderid','in',$payids];
				}
			}
			
			if(input('param.ctime')){
				$ctime = explode(' ~ ',input('param.ctime'));
				if(input('param.time_type') == 1){ //下单时间
					$where[] = ['order.createtime','>=',strtotime($ctime[0])];
					$where[] = ['order.createtime','<',strtotime($ctime[1])];
				}elseif(input('param.time_type') == 2){ //付款时间
					$where[] = ['order.paytime','>=',strtotime($ctime[0])];
					$where[] = ['order.paytime','<',strtotime($ctime[1])];
				}elseif(input('param.time_type') == 3){ //发货时间
					$where[] = ['order.send_time','>=',strtotime($ctime[0])];
					$where[] = ['order.send_time','<',strtotime($ctime[1])];
				}elseif(input('param.time_type') == 4){ //完成时间
					$where[] = ['order.collect_time','>=',strtotime($ctime[0])];
					$where[] = ['order.collect_time','<',strtotime($ctime[1])];
				}
			}
			if(input('param.keyword')){
				$keyword = input('param.keyword');
				$keyword_type = input('param.keyword_type');
				if($keyword_type == 1){ //订单号
					$where[] = ['order.ordernum','like','%'.$keyword.'%'];
				}elseif($keyword_type == 2){ //会员ID
					$where[] = ['order.mid','=',$keyword];
				}elseif($keyword_type == 3){ //会员信息
					$where[] = ['member.nickname|member.realname','like','%'.$keyword.'%'];
				}elseif($keyword_type == 4){ //收货信息
					$where[] = ['order.linkman|order.tel|order.area|order.address','like','%'.$keyword.'%'];
				}elseif($keyword_type == 5){ //快递单号
					$where[] = ['order.express_no','like','%'.$keyword.'%'];
				}elseif($keyword_type == 6){ //商品ID
					$orderids = Db::name('shop_order_goods')->where('aid',aid)->where('proid',$keyword)->column('orderid');
					$where[] = ['order.id','in',$orderids];
				}elseif($keyword_type == 7){ //商品名称
					$orderids = Db::name('shop_order_goods')->where('aid',aid)->where('name','like','%'.$keyword.'%')->column('orderid');
					$where[] = ['order.id','in',$orderids];
				}elseif($keyword_type == 8){ //商品编码
					$orderids = Db::name('shop_order_goods')->where('aid',aid)->where('procode','like','%'.$keyword.'%')->column('orderid');
					$where[] = ['order.id','in',$orderids];
				}elseif($keyword_type == 9){ //核销员
					$orderids = Db::name('hexiao_order')->where('aid',aid)->where('type','shop')->where('remark','like','%'.$keyword.'%')->column('orderid');
					$where[] = ['order.id','in',$orderids];
				}elseif($keyword_type == 10){ //所属门店
					$mdids = Db::name('mendian')->where('aid',aid)->where('name','like','%'.$keyword.'%')->column('id');
					if(getcustom('mendian_upgrade')){
						//门店分组
						if(input('param.mdgid')){
							$mdids2 = Db::name('mendian')->where('groupid',input('param.mdgid'))->where('aid',aid)->where('bid',bid)->column('id');
							if(!empty($mdids2)){
								if(!empty($mdids)){
									$mdids = array_merge($mdids,$mdids2);
								}else{
									$mdids = $mdids2;
								}
							}
						}
						//门店id
						if(input('param.mdid')) $mdids[] = input('param.mdid');
						$mdids = array_unique($mdids);
					}
					$where[] = ['order.mdid','in',$mdids];
				}elseif($keyword_type == 11){
					if(getcustom('shop_buy_worknum')){
		            	$where[] = ['order.worknum','like','%'.$keyword.'%'];//工号
			        }
				}elseif($keyword_type == 21){ //兑换卡号
					$where[] = ['order.duihuan_cardno','=',$keyword];
				}
			}
			if(getcustom('invite_free')){
				if(input('?param.is_free') && input('param.is_free')!==''){
					$where[] = ['order.is_free','=',input('param.is_free')];
				}
			}

			if(getcustom('mendian_upgrade')){
				if(!input('param.keyword') || input('param.keyword_type')!=10){
					//门店分组
					if(input('param.mdgid')){
						$mdids = [];
						$mdids2 = Db::name('mendian')->where('groupid',input('param.mdgid'))->where('aid',aid)->where('bid',bid)->column('id');
						if(!empty($mdids2)){
							$mdids = $mdids2;
						}
						//门店id
						if(input('param.mdid')) $mdids[] = input('param.mdid');
						$mdids = array_unique($mdids);
						if(!empty($mdids)){
							$where[] = ['order.mdid','in',$mdids];
						}else{
							$where[] = ['order.id','=',0];
						}
					}else{
						if(input('param.mdid')) $where[] = ['order.mdid','=',input('param.mdid')];
					}
				}
			}
			if(getcustom('pay_transfer')){
				if(input('?param.transfer_check') && input('param.transfer_check')!==''){
					$where[] = ['order.paytypeid','=',5];
					$where[] = ['order.transfer_check','=',input('param.transfer_check')];
				}
			}
			if(input('?param.status') && input('param.status')!==''){
				if(input('param.status') == 5){
					$where[] = ['order.refund_status','=',1];
				}elseif(input('param.status') == 6){
					$where[] = ['order.refund_status','=',2];
				}elseif(input('param.status') == 7){
					$where[] = ['order.refund_status','=',3];
				}elseif(input('param.status') == 22){
					$where[] = ['order.status','=',2];
					$where[] = ['order.express_isbufen','=',1];
				}else{
					$where[] = ['order.status','=',input('param.status')];
				}
			}
			$totalcommission = 0;
			if(input('param.fxmid')){
				$fxmid = input('param.fxmid/d');
				if($page == 1){
					$where1 = $where;
					$where1[] = Db::raw("order.id in (select orderid from ".table_name('shop_order_goods')." where parent1={$fxmid})");
					$totalcommission1 = Db::name('shop_order_goods')->where('orderid','in',function($query)use($where1){
						$query->name('shop_order')->alias('order')->leftJoin('member member','member.id=order.mid')->where($where1)->field('order.id');
					})->sum('parent1commission');
					$where2 = $where;
					$where2[] = Db::raw("order.id in (select orderid from ".table_name('shop_order_goods')." where parent2={$fxmid})");
					$totalcommission2 = Db::name('shop_order_goods')->where('orderid','in',function($query)use($where2){
						$query->name('shop_order')->alias('order')->leftJoin('member member','member.id=order.mid')->where($where2)->field('order.id');
					})->sum('parent2commission');
					$where3 = $where;
					$where3[] = Db::raw("order.id in (select orderid from ".table_name('shop_order_goods')." where parent3={$fxmid})");
					$totalcommission3 = Db::name('shop_order_goods')->where('orderid','in',function($query)use($where3){
						$query->name('shop_order')->alias('order')->leftJoin('member member','member.id=order.mid')->where($where3)->field('order.id');
					})->sum('parent3commission');

					$totalcommission = round($totalcommission1 + $totalcommission2 + $totalcommission3,2);
				}

				$where[] = Db::raw("order.id in (select orderid from ".table_name('shop_order_goods')." where parent1={$fxmid} or parent2={$fxmid} or parent3={$fxmid})");
			}
			$count = 0 + Db::name('shop_order')->alias('order')->leftJoin('member member','member.id=order.mid')->where($where)->count();
			//echo M()->_sql();
			$list = Db::name('shop_order')->alias('order')->field('order.*')->leftJoin('member member','member.id=order.mid')->where($where)->page($page,$limit)->order($order)->select()->toArray();

			if(getcustom('mendian_upgrade')){
				$mendian_upgrade_status = Db::name('admin')->where('id',aid)->value('mendian_upgrade_status');
			}

			foreach($list as $k=>$vo){
                // 订单添加备注信息
                $formfield = Db::name('freight')->where('id',$vo['freight_id'])->find();
                $formdataSet = json_decode($formfield['formdata'],true);
                foreach($formdataSet as $k1=>$v){
                    if($v['val1'] == '备注'){
                        $message =Db::name('freight_formdata')->where('type','shop_order')->where('orderid',$vo['id'])->value('form'.$k1);
                        $value = explode('^_^',$message);
                        if($value[1] !== ''){
                            $list[$k]['message'] = $value[1];
                        }
                        break;
                    }
                }

				$member = Db::name('member')->where('id',$vo['mid'])->find();
				$oglist = Db::name('shop_order_goods')->where('aid',aid)->where('orderid',$vo['id'])->select()->toArray();
				$goodsdata=array();
				foreach($oglist as $og){
				    $grstr = '';
				    if(getcustom('product_glass')){
				        if($og['glass_record_id']){
                            $glassrecord = \app\models\Glass::orderGlassRecord($og['glass_record_id'],aid);
                            $glassrecord && $grstr = '<div style="padding-top:0px;color:red;"><span>验光档案-'.$glassrecord['name'].'</span></div>';
				        }
                    }
                    $ogremark = '';
                    if($og['gtype']==1){
                        $ogremark = '<span style="color:#f00;">【赠品】</span>';
                    }
					$goodshtml = '<div style="font-size:12px;float:left;clear:both;margin:1px 0">'.
						'<div class="table-imgbox"><img lay-src="'.$og['pic'].'" src="'.PRE_URL.'/static/admin/layui/css/modules/layer/default/loading-2.gif"></div>'.
						'<div style="float: left;width:180px;margin-left: 10px;white-space:normal;line-height:16px;">'.
							'<div style="width:100%;min-height:25px;max-height:32px;overflow:hidden">'.$og['name'].$ogremark.'</div>'.
							'<div style="padding-top:0px;color:#f60"><span style="color:#888">'.$og['ggname'].'</span></div>'.$grstr;
					if(getcustom('price_dollar') && $og['usd_sell_price']){
						$goodshtml.='<div style="padding-top:0px;color:#f60;">$'.$og['usd_sellprice'].' / ￥'.$og['sell_price'].' × '.$og['num'].'</div>';
					}elseif(getcustom('product_weight') && $vo['yuding_type']==2){
                        $goodshtml.='<div style="padding-top:0px;color:#f60;">￥'.$og['sell_price'].' × '.$og['total_weight'].'</div>';
                    }else{
						$goodshtml.='<div style="padding-top:0px;color:#f60;">￥'.$og['sell_price'].' × '.$og['num'].'</div>';
					}
					$goodshtml.='</div>';
					$goodshtml.='</div>';
					
                    $goodsdata[] = $goodshtml;
				}
				if(getcustom('ciruikang_fenxiao')){
					if($vo['crk_givenum']>0){
						$goodsdata[] ='<div style="padding-top:0px;color:#f60;line-height:35px">+随机赠送'.$vo['crk_givenum'].'件</div>';
					}
				}
				$list[$k]['goodsdata'] = implode('',$goodsdata);
				if($vo['bid'] > 0){
					$list[$k]['bname'] = Db::name('business')->where('aid',aid)->where('id',$vo['bid'])->value('name');
				}else{
					$list[$k]['bname'] = '平台自营';
				}
                $refundOrder = Db::name('shop_refund_order')->where('refund_status','>',0)->where('aid',aid)->where('orderid',$vo['id'])->count();
                $list[$k]['refundCount'] = $refundOrder;
                $list[$k]['payorder'] = [];
                if($vo['paytypeid'] == 5) {
                    $list[$k]['payorder'] = Db::name('payorder')->where('id',$vo['payorderid'])->where('aid',aid)->find();
                }
				$list[$k]['nickname'] = $member['nickname'];
				$list[$k]['headimg'] = $member['headimg'];
				$list[$k]['m_remark'] = $member['remark'];
				$list[$k]['platform'] = getplatformname($vo['platform']);
				if($order_show_member_apply) {
                    $member_level = Db::name('member_level')->where('id',$member['levelid'])->find();
                    $list[$k]['member_apply_info'] = $member['realname'] . '<br>'.$member['tel'] .'<br>'. $member_level['name'];
                }
                $list[$k]['yuding_type'] = $vo['yuding_type']??0;
				if($mendian_upgrade_status && $vo['mdid']){
					$mendian = Db::name('mendian')->where('aid',aid)->where('id',$vo['mdid'])->find();
					$list[$k]['mdname'] = $mendian['name']??'';
					$list[$k]['mdtel'] = $mendian['tel']??'';
					$list[$k]['mdxqname'] = $mendian['xqname']??'';
				}
                $canFahuo = 0;
                if($vo['status']==1){
                    $canFahuo = 1;
                }
                if(getcustom('erp_wangdiantong') && $erpWdtOpen==1){
                    if($vo['status']==1 && $vo['wdt_status']==1) {
                        $canFahuo = 0;
                    }
                    //快递信息提取
                    $expressdata = '';
                    if($vo['status']==2){
                        $express_content = $vo['express_content']?json_decode($vo['express_content'],true):[];
                        foreach ($express_content as $ek=>$ev){
                            $expressdata .='<p>'.$ev['express_com'].':'.$ev['express_no'].'</p>';
                        }
                    }
                    $list[$k]['expressdata'] = $expressdata;
                }
                $list[$k]['can_fahuo'] = $canFahuo;

                $canPay = true;$canEdit = true;
                if(getcustom('supply_zhenxin')){
                    if($vo['issource'] == 1 && $vo['source'] == 'supply_zhenxin'){
                        $canPay   = false;
                        $canEdit  = false;
                    }
                }
                $list[$k]['canPay']  = $canPay;
                $list[$k]['canEdit'] = $canEdit;

                if(getcustom('pay_money_combine')){
                    if($vo['combine_money'] && $vo['combine_money'] > 0){
                        if(!empty($vo['paytype'])){
                        	$list[$k]['paytype'] .= ' + '.t('余额').'支付';
                        }else{
                        	$list[$k]['paytype'] .= t('余额').'支付';
                        }
                    }
                }
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$list,'totalcommission'=>$totalcommission]);
		}
		$machinelist = Db::name('wifiprint_set')->where('aid',aid)->where('status',1)->where('bid',bid)->select()->toArray();
		$hasprint = 0;
		if($machinelist){
			$hasprint = 1;
		}
		$peisong_set = Db::name('peisong_set')->where('aid',aid)->find();
		if($peisong_set['status']==1 && bid>0 && $peisong_set['businessst']==0 && $peisong_set['make_status']==0) $peisong_set['status'] = 0;
        $freight = Db::name('freight')->where('aid',aid)->where('bid',bid)->select()->toArray();
		
		$adminset = Db::name('admin_set')->where('aid',aid)->find();
		$shopset = Db::name('shop_sysset')->where('aid',aid)->find();
        if(getcustom('school_product')) {
            $need_school = Db::name('admin')->where('id', aid)->value('need_school');
            if($need_school){
                $schoollist = Db::name('school')->where('aid',aid)->select()->toArray();
                View::assign('needschool',1);
                View::assign('schoollist',$schoollist);
            }
        }
		
		if(getcustom('mendian_upgrade')){
			$mendian_upgrade_status = Db::name('admin')->where('id',aid)->value('mendian_upgrade_status');
			if($mendian_upgrade_status==1){
				 View::assign('mendian_upgrade',1);
			}
			$mendian_groups = Db::name('mendian_group')->where('aid',aid)->where('bid',bid)->where('pid',0)->order('id desc')->column('name','id');
			View::assign('mendian_groups',$mendian_groups);
		}
		if(getcustom('shop_order_merge_export')){
		    $is_merge_export = 0;
		    if($this->auth_data == 'all' || in_array('ShopOrderMergeExport',$this->auth_data)){
                $is_merge_export = 1;
            }
            View::assign('is_merge_export',$is_merge_export);
        }   
		if(input('param.showtype') ==2){
		    $business_list = Db::name('business')->where('aid',aid)->field('id,name')->select()->toArray();
            View::assign('business_list',$business_list);
        }
        View::assign('freight',$freight);
		View::assign('peisong_set',$peisong_set);
		View::assign('hasprint',$hasprint);
		View::assign('express_data',express_data(['aid'=>aid,'bid'=>bid]));
		View::assign('adminset',$adminset);
		View::assign('shopset',$shopset);
		View::assign('erpWdtOpen',$erpWdtOpen);
        $logdel_auth = true;
        if(getcustom('business_del_auth')){
            if($this->auth_data == 'all' || in_array('Payorder/logdel',$this->auth_data)){
                $logdel_auth = true;
            }else{
                $logdel_auth = false;
            }
        }
        View::assign('logdel_auth',$logdel_auth);
		return View::fetch();
    }
	//导出
	public function excel(){
		set_time_limit(0);
		ini_set('memory_limit', '2000M');
		if(input('param.field') && input('param.order')){
			$order = input('param.field').' '.input('param.order');
		}else{
			$order = 'id desc';
		}
        $page = input('param.page')?:1;
        $limit = input('param.limit')?:10;
		$where = [];
		$where[] = ['order.aid','=',aid];
		if(bid==0){
			if(input('param.bid')){
				$where[] = ['order.bid','=',input('param.bid')];
			}elseif(input('param.showtype')==2){
				$where[] = ['order.bid','<>',0];
            }elseif(input('param.showtype')=='all'){
                $where[] = ['order.bid','>=',0];
			}else{
				$where[] = ['order.bid','=',0];
			}
		}else{
			$where[] = ['order.bid','=',bid];
		}
		if($this->mdid){
			$where[] = ['order.mdid','=',$this->mdid];
		}
        if(getcustom('school_product')) {
            if(input('param.school_id')) $where[] = ['order.school_id','=',input('param.school_id')];
        }
		if(getcustom('product_wholesale')){
			//批发加商品类型搜索
			if(input('param.product_type')!=''){
				$ids = Db::name('shop_order_goods')->alias('shop_order_goods')->join('shop_product shop_product','shop_product.id=shop_order_goods.proid')->fieldRaw('shop_order_goods.orderid')->where('shop_product.product_type','=',input('param.product_type'))->page(0,100000)->order('shop_order_goods.orderid desc')->column('orderid');
				$where[] = ['order.id','in',$ids];
			}
		}
        if(getcustom('product_weight')){
            $where[] = ['order.product_type','<>',2];
        }
        if(getcustom('product_supply_chain')){
            $where[] = ['order.product_type','<>',7];
        }
        if(getcustom('supply_zhenxin')){
            if(input('?param.source') && input('param.source')!==''){
            	$source = input('param.source');
            	if($source == 'self'){
            		$where[] = ['issource','=',0];
            	}else{
            		$where[] = ['issource','=',1];
            		$where[] = ['source','=',$source];
            	}
            }
            if(input('param.sordernum')) $where[] = ['order.sordernum','like','%'.input('param.sordernum').'%'];
        }
        if(input('param.orderid')) $where[] = ['order.id','=',input('param.orderid')];
        if(input('param.freight_id')) $where[] = ['order.freight_id','=',input('param.freight_id')];
		if(input('param.mid')) $where[] = ['order.mid','=',input('param.mid')];
		if(input('param.proname')) $where[] = ['order.proname','like','%'.input('param.proname').'%'];
		if(input('param.ordernum')) $where[] = ['order.ordernum','like','%'.input('param.ordernum').'%'];
        if(input('param.nickname')) $where[] = ['member.nickname|member.realname','like','%'.input('param.nickname').'%'];
        if(input('param.linkman')) $where[] = ['order.linkman','like','%'.input('param.linkman').'%'];
		if(input('param.tel')) $where[] = ['order.tel','like','%'.input('param.tel').'%'];
		if(getcustom('invite_free')){
			if(input('?param.is_free') && input('param.is_free')!==''){
				$where[] = ['order.is_free','=',input('param.is_free')];
			}
		}
		if(getcustom('douyin_groupbuy')){
			//抖音团购核销
			if(input('?param.isdygroupbuy') && input('param.isdygroupbuy')!==''){
				$where[] = ['order.isdygroupbuy','=',input('param.isdygroupbuy')];
			}
		}
		if(getcustom('pay_transfer')){
			if(input('?param.transfer_check') && input('param.transfer_check')!==''){
				$where[] = ['order.paytypeid','=',5];
				$where[] = ['order.transfer_check','=',input('param.transfer_check')];
			}
		}
		if(input('?param.status') && input('param.status')!==''){
			if(input('param.status') == 5){
				$where[] = ['order.refund_status','=',1];
			}elseif(input('param.status') == 6){
				$where[] = ['order.refund_status','=',2];
			}elseif(input('param.status') == 7){
				$where[] = ['order.refund_status','=',3];
			}else{
				$where[] = ['order.status','=',input('param.status')];
			}
		}
		if(input('param.ctime')){
			$ctime = explode(' ~ ',input('param.ctime'));
			if(input('param.time_type') == 1){ //下单时间
				$where[] = ['order.createtime','>=',strtotime($ctime[0])];
				$where[] = ['order.createtime','<',strtotime($ctime[1])];
			}elseif(input('param.time_type') == 2){ //付款时间
				$where[] = ['order.paytime','>=',strtotime($ctime[0])];
				$where[] = ['order.paytime','<',strtotime($ctime[1])];
			}elseif(input('param.time_type') == 3){ //发货时间
				$where[] = ['order.send_time','>=',strtotime($ctime[0])];
				$where[] = ['order.send_time','<',strtotime($ctime[1])];
			}elseif(input('param.time_type') == 4){ //完成时间
				$where[] = ['order.collect_time','>=',strtotime($ctime[0])];
				$where[] = ['order.collect_time','<',strtotime($ctime[1])];
			}
		}
		if(input('param.keyword')){
			$keyword = input('param.keyword');
			$keyword_type = input('param.keyword_type');
			if($keyword_type == 1){ //订单号
				$where[] = ['order.ordernum','like','%'.$keyword.'%'];
			}elseif($keyword_type == 2){ //会员ID
				$where[] = ['order.mid','=',$keyword];
			}elseif($keyword_type == 3){ //会员信息
				$where[] = ['member.nickname|member.realname','like','%'.$keyword.'%'];
			}elseif($keyword_type == 4){ //收货信息
				$where[] = ['order.linkman|order.tel|order.area|order.address','like','%'.$keyword.'%'];
			}elseif($keyword_type == 5){ //快递单号
				$where[] = ['order.express_no','like','%'.$keyword.'%'];
			}elseif($keyword_type == 6){ //商品ID
				$orderids = Db::name('shop_order_goods')->where('aid',aid)->where('proid',$keyword)->column('orderid');
				$where[] = ['order.id','in',$orderids];
			}elseif($keyword_type == 7){ //商品名称
				$orderids = Db::name('shop_order_goods')->where('aid',aid)->where('name','like','%'.$keyword.'%')->column('orderid');
				$where[] = ['order.id','in',$orderids];
			}elseif($keyword_type == 8){ //商品编码
				$orderids = Db::name('shop_order_goods')->where('aid',aid)->where('procode','like','%'.$keyword.'%')->column('orderid');
				$where[] = ['order.id','in',$orderids];
			}elseif($keyword_type == 9){ //核销员
				$orderids = Db::name('hexiao_order')->where('aid',aid)->where('type','shop')->where('remark','like','%'.$keyword.'%')->column('orderid');
				$where[] = ['order.id','in',$orderids];
			}elseif($keyword_type == 10){ //所属门店
				$mdids = Db::name('mendian')->where('aid',aid)->where('name','like','%'.$keyword.'%')->column('id');
				$where[] = ['order.mdid','in',$mdids];
			}elseif($keyword_type == 11){
				if(getcustom('shop_buy_worknum')){
	            	$where[] = ['order.worknum','like','%'.$keyword.'%'];//工号
		        }
			}elseif($keyword_type == 21){ //兑换卡号
				$where[] = ['order.duihuan_cardno','=',$keyword];
			}
		}
		
		if(input('param.fxmid')){
			$fxmid = input('param.fxmid/d');
			$where[] = Db::raw("order.id in (select orderid from ".table_name('shop_order_goods')." where parent1={$fxmid} or parent2={$fxmid} or parent3={$fxmid})");
		}

		$count = Db::name('shop_order')->alias('order')->field('order.*')->leftJoin('member member','member.id=order.mid')->where($where)->count();
		$list = Db::name('shop_order')->alias('order')->field('order.*')->leftJoin('member member','member.id=order.mid')->where($where)->page($page,$limit)->order($order)->select()->toArray();
        //学校学生信息处理
        $need_school = 0;
        if(getcustom('school_product')) {
            $need_school = Db::name('admin')->where('id', aid)->value('need_school');
            if($need_school){
                $school_ids = array_filter(array_column($list,'school_id'));
                $grade_ids = array_filter(array_column($list,'grade_id'));
                $class_ids = array_filter(array_column($list,'class_id'));
                if($school_ids){
                    $schoolArr = Db::name('school')->where('id','in',$school_ids)->column('name','id');
                }
                $class_ids = array_merge($grade_ids,$class_ids);
                if($school_ids){
                    $classArr = Db::name('school_class')->where('id','in',$class_ids)->column('name','id');
                }
            }
        }
        if(getcustom('member_goldmoney_silvermoney')){
			$ShopSendSilvermoney = true;//赠送银值权限
            $ShopSendGoldmoney   = true;//赠送金值权限
            //平台权限
            $admin_user = Db::name('admin_user')->where('aid',aid)->where('isadmin','>',0)->field('auth_type,auth_data')->find();
            if($admin_user['auth_type'] !=1 ){
                $admin_auth = !empty($admin_user['auth_data'])?json_decode($admin_user['auth_data'],true):[];
                if(!in_array('ShopSendSilvermoney,ShopSendSilvermoney',$admin_auth)){
                    $ShopSendSilvermoney = false;
                }
                if(!in_array('ShopSendGoldmoney,ShopSendGoldmoney',$admin_auth)){
                    $ShopSendGoldmoney   = false;
                }
            }
        }
		if(getcustom('plug_xiongmao')){
			$res = $this->excel2($list);
            return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$res['data'],'title'=>$res['title']]);
		}else{
			$bArr = Db::name('business')->where('aid',aid)->column('name','id');
			if(!$bArr) $bArr = [];
			$bArr['0'] = '自营';
            $title1 = array('订单号','openid','来源','所属商家','下单人','姓名','电话','收货地址','商品名称','商品编码','规格','数量','退款数量','总价','单价','成本','折扣金额','实付款','支付方式','配送方式','卡密','配送/提货时间','运费','积分抵扣','满额立减','优惠券优惠','状态','退款状态','退款金额','下单时间','付款时间','发货时间','完成时间','快递信息');
            if(getcustom('school_product') && $need_school){
                $title1[] = '学校';
                $title1[] = '年级';
                $title1[] = '班级';
                $title1[] = '学生';
            }
			if(getcustom('lipinka_jihuo2')){
				$title1[] = '兑换卡卡号';
			}
			if(getcustom('money_dec')){
				$title1[] = t('余额').'抵扣';
			}
			if(getcustom('shop_buy_worknum')){
				$title1[] = '工号';
			}
			$mendian_upgrade_status = 0;
			if(getcustom('mendian_upgrade')){
				$mendian_upgrade_status = Db::name('admin')->where('id',aid)->value('mendian_upgrade_status');
			}
			if($mendian_upgrade_status){
				$title1[] = '社区/'.t('门店');
			}
			if(getcustom('pay_money_combine')){
                $title1[] = '组合支付';
            }
            if(getcustom('member_goldmoney_silvermoney')){
                if($ShopSendSilvermoney){
                    $title1[] = t('银值').'抵扣';
                }
                if($ShopSendGoldmoney){
                    $title1[] = t('金值').'抵扣';
                }
            }
            $title2 = array('表单信息','商家备注','核销员','核销门店','佣金总额','一级佣金','会员信息','二级佣金','会员信息','三级佣金','会员信息');
            $title = array_merge($title1,$title2);
			$data = [];
			foreach($list as $k=>$vo){

				$status='';
				$refund_status = '';
				$refund_money = '';
				if($vo['status']==0){
					$status = '未支付';
				}elseif($vo['status']==2){
					$status = '已发货';
				}elseif($vo['status']==1){
					$status = '已支付';
				}elseif($vo['status']==3){
					$status = '已收货';
				}elseif($vo['status']==4){
					$status = '已关闭';
				}
				if(getcustom('invite_free')){
					if($vo['is_free']){
						$status .= '(免单)';
					}else{
						$status .= '(非免单)';
					}
				}
                $allcolumn = false;
				if(getcustom('school_product') && $need_school){
                    $allcolumn = true;
                    $member_content = '';
                    $school_name = $schoolArr[$vo['school_id']]??'';
                    $grade_name = $classArr[$vo['grade_id']]??'';
                    $class_name = $classArr[$vo['class_id']]??'';
                    if($vo['member_content']){
                       $member_content_list = $vo['member_content']?json_decode($vo['member_content'],true):[];
                       foreach ($member_content_list as $mk=>$mv){
                           if($mv['key']!='upload'){
                               $member_content .=' '. $mv['value'];
                           }
                       }
                    }
                }
                if(getcustom('pay_money_combine')){
                    if($vo['combine_money'] && $vo['combine_money'] > 0){
                        if(!empty($vo['paytype'])){
                        	$vo['paytype'] .= ' + '.t('余额').'支付';
                        }else{
                        	$vo['paytype'] .= t('余额').'支付';
                        }
                    }
                }
				$member = Db::name('member')->where('id',$vo['mid'])->find();
				$oglist = Db::name('shop_order_goods')->where('orderid',$vo['id'])->select()->toArray();
				//$xm=array();
				foreach($oglist as $k2=>$og){
                    $ogremark = '';
                    if($og['gtype']==1){
                        $ogremark = '【赠品】';
                    }
					$refund_status='';
					$refund_money = '';
                    // 导出的订单里退款的商品退款记录
                    $ro = Db::name('shop_refund_order')->where('orderid',$vo['id'])->where('refund_status','not in',[0,3])->select()->toArray();
                    foreach ($ro as $v){
                        $isrefund = Db::name('shop_refund_order_goods')->where('refund_orderid',$v['id'])->find();
                        if($isrefund['ogid'] == $og['id']){
                            switch ($v['refund_status']){
                                case 0:
                                    $refund_status = '退款已取消';
                                    $refund_money  = $isrefund['refund_money'];
                                    break;
                                case 1:
                                    $refund_status = '退款待审核';
                                    $refund_money  = $isrefund['refund_money'];
                                    break;
                                case 2:
                                    $refund_status = '已退款';
                                    $refund_money  = $isrefund['refund_money'];
                                    break;
                                case 3:
                                    $refund_status = '退款驳回';
                                    $refund_money  = $isrefund['refund_money'];
                                    break;
                                case 4:
                                    $refund_status = '审核通过，待退货';
                                    $refund_money  = $isrefund['refund_money'];
                                    break;
                                default:
                                    $refund_status = '状态未找到';
                                    $refund_money  = '0';
                                    break;
							
                            }
                            break;
                        }
                    }
                    $ogstatus='';
                    if($og['status']==0){
                        $ogstatus = '未支付';
                    }elseif($og['status']==2){
                        $ogstatus = '已发货';
                    }elseif($og['status']==1){
                        $ogstatus = '已支付';
                    }elseif($og['status']==3){
                        $ogstatus = '已收货';
                    }elseif($og['status']==4){
                        $ogstatus = '已关闭';
                    }
				    $barcode = '';
				    if($og['barcode'])  $barcode = "(".$og['barcode'].")";
					//$xm[] = $og['name'].$barcode."/".$og['ggname']." × ".$og['num']."";
					
					$parent1commission = $og['parent1'] ? $og['parent1commission'] : 0;
					$parent2commission = $og['parent2'] ? $og['parent2commission'] : 0;
					$parent3commission = $og['parent3'] ? $og['parent3commission'] : 0;
					$totalcommission = $parent1commission+$parent2commission+$parent3commission;
					if($og['parent1']){
						$parent1 = Db::name('member')->where('id',$og['parent1'])->find();
						$parent1str = $parent1['nickname'].'(会员ID:'.$parent1['id'].')';
					}else{
						$parent1str = '';
					}
					if($og['parent2']){
						$parent2 = Db::name('member')->where('id',$og['parent2'])->find();
						$parent2str = $parent2['nickname'].'(会员ID:'.$parent2['id'].')';
					}else{
						$parent2str = '';
					}
					if($og['parent3']){
						$parent3 = Db::name('member')->where('id',$og['parent3'])->find();
						$parent3str = $parent3['nickname'].'(会员ID:'.$parent3['id'].')';
					}else{
						$parent3str = '';
					}
					//配送自定义表单
					$vo['formdata'] = \app\models\Freight::getformdata($vo['id'],'shop_order');
					$formdataArr = [];
					$message = '';
					if($vo['formdata']) {
						foreach ($vo['formdata'] as $formdata) {
							if($formdata[2] != 'upload') {
								//if($formdata[0] == '备注') {
								//	$message = $formdata[1];
								//} else {
									$formdataArr[] = $formdata[0].':'.$formdata[1];
								//}
							}
						}
					}
					$formdatastr = implode("\r\n",$formdataArr);

					if($vo['freight_type'] == 1 && $vo['status'] == 3){
						$hexiao_order = Db::name('hexiao_order')->where('aid',aid)->where('orderid',$vo['id'])->where('type','shop')->find();
						if($hexiao_order){
							$hexiao_order['uname'] = Db::name('admin_user')->where('id',$hexiao_order['uid'])->value('un');
							$hexiao_order['mendian'] = Db::name('mendian')->where('id',$vo['mdid'])->value('name');
						}
					}
					$paytype = $vo['paytype'];
					if(getcustom('pay_yuanbao') && $vo['is_yuanbao_pay'] == 1){
						$paytype .= "(".t('元宝')."：".$vo['total_yuanbao'].")";
					}

					if(getcustom('yunyuzhou')){
						$tmpdata1 = [
							' '.$vo['ordernum'],
							$member[$vo['platform'].'openid'],
							getplatformname($vo['platform']),
							$bArr[$vo['bid']],
							$member['nickname'],
							$vo['linkman'],
							$vo['tel'],
							$vo['area'].' '.$vo['address'],
							$og['name'].$ogremark,
							$og['procode'],
							$og['ggname'].$barcode,
							$og['num'],
                            $og['refund_num'],
							$og['totalprice'],
							$og['sell_price'],
							$og['cost_price'],
							$og['leveldk_money'],
							$og['real_totalprice'],
							$paytype,
							$vo['freight_text'],
							' '.$vo['freight_content'],
							$vo['freight_time'],
							$vo['freight_price'],
							$og['scoredk_money'],
							$og['manjian_money'],
							$og['coupon_money'],
							$status,
                            $refund_status,
                            $refund_money,
							date('Y-m-d H:i:s',$vo['createtime']),
							$vo['paytime'] ? date('Y-m-d H:i:s',$vo['paytime']) : '',
							$vo['send_time'] ? date('Y-m-d H:i:s',$vo['send_time']) : '',
							$vo['collect_time'] ? date('Y-m-d H:i:s',$vo['collect_time']) : '',
							($vo['express_com'] ? $vo['express_com'].'('.$vo['express_no'].')':'')
                        ];
                        if($need_school){
                            $tmpdata1[] = $school_name;
                            $tmpdata1[] = $class_name;
                            $tmpdata1[] = $grade_name;
                            $tmpdata1[] = $member_content;
                        }
						if(getcustom('lipinka_jihuo2')){
							$tmpdata1[] = $vo['duihuan_cardno'];
						}
						if(getcustom('money_dec')){
							$tmpdata1[] = $vo['dec_money'];
						}
						if(getcustom('shop_buy_worknum')){
							$tmpdata1[] = $vo['worknum'];
						}
						if(getcustom('pay_money_combine')){
	                        //组合支付
	                        $combine = '';
	                        if($vo['combine_money']>0){
	                            $combine .= t('余额').'已付：'.$vo['combine_money']." \n\r ";
	                        }
	                        if($vo['combine_wxpay']>0){
	                            $combine .= '微信已付：'.$vo['combine_money']." \n\r ";
	                        } 
	                        if($vo['combine_alipay']>0){
	                            $combine .= '支付宝已付：'.$vo['combine_money']." \n\r ";
	                        }
	                        $tmpdata1[] = $combine;
	                    }
                        if(getcustom('member_goldmoney_silvermoney')){
                            if($ShopSendSilvermoney){
                                $tmpdata1[] = $vo['silvermoneydec'];
                            }
                            if($ShopSendGoldmoney){
                                $tmpdata1[] = $vo['goldmoneydec'];
                            }
                        }
						$tmpdata2 =	[
						    $formdatastr,
							$vo['remark'],
							$hexiao_order['uname'],
							$hexiao_order['mendian'],
							$totalcommission,
							$parent1commission,
							$parent1str,
							$parent2commission,
							$parent2str,
							$parent3commission,
							$parent3str,
						];
						$data[] = array_merge($tmpdata1,$tmpdata2);
						continue;
					}
					if($k2 == 0 || $allcolumn){
						$tmpdata1 = [
							' '.$vo['ordernum'],
							$member[$vo['platform'].'openid'],
							getplatformname($vo['platform']),
							$bArr[$vo['bid']],
							$member['nickname'],
							$vo['linkman'],
							$vo['tel'],
							$vo['area'].' '.$vo['address'],
							$og['name'].$ogremark,
							$og['procode'],
							$og['ggname'].$barcode,
							$og['num'],
                            $og['refund_num'],
							$og['totalprice'],
							$og['sell_price'],
							$og['cost_price'],
							$vo['leveldk_money'],
							$vo['totalprice'],
							$paytype,
							$vo['freight_text'],
							' '.$vo['freight_content'],
							$vo['freight_time'],
							$vo['freight_price'],
							$vo['scoredk_money'],
							$vo['manjian_money'],
							$vo['coupon_money'],
							$status,
                            $refund_status,
                            $refund_money,
							date('Y-m-d H:i:s',$vo['createtime']),
							$vo['paytime'] ? date('Y-m-d H:i:s',$vo['paytime']) : '',
							$vo['send_time'] ? date('Y-m-d H:i:s',$vo['send_time']) : '',
							$vo['collect_time'] ? date('Y-m-d H:i:s',$vo['collect_time']) : '',
							($vo['express_com'] ? $vo['express_com'].'('.$vo['express_no'].')':''),
						];
						if($need_school){
                            $tmpdata1[] = $school_name;
                            $tmpdata1[] = $class_name;
                            $tmpdata1[] = $grade_name;
                            $tmpdata1[] = $member_content;
                        }
						if(getcustom('lipinka_jihuo2')){
							$tmpdata1[] = $vo['duihuan_cardno'];
						}
						if(getcustom('money_dec')){
							$tmpdata1[] = $vo['dec_money'];
						}
						if(getcustom('shop_buy_worknum')){
							$tmpdata1[] = $vo['worknum'];
						}
						if(getcustom('pay_money_combine')){
	                        //组合支付
	                        $combine = '';
	                        if($vo['combine_money']>0){
	                            $combine .= t('余额').'已付：'.$vo['combine_money']." \n\r ";
	                        }
	                        if($vo['paytypeid'] == 2 && $vo['combine_wxpay']>0){
	                            $combine .= '微信已付：'.$vo['combine_wxpay']." \n\r ";
	                        } 
	                        if(($vo['paytypeid'] == 3 || ($vo['paytypeid']>=302 && $vo['paytypeid']<=330)) && $vo['combine_alipay']>0){
	                            $combine .= '支付宝已付：'.$vo['combine_alipay']." \n\r ";
	                        }
	                        $tmpdata1[] = $combine;
	                    }
	                    if(getcustom('member_goldmoney_silvermoney')){
                            if($ShopSendSilvermoney){
                                $tmpdata1[] = $vo['silvermoneydec'];
                            }
                            if($ShopSendGoldmoney){
                                $tmpdata1[] = $vo['goldmoneydec'];
                            }
                        }

						if($mendian_upgrade_status){
							$mendian = Db::name('mendian')->where('aid',aid)->where('id',$vo['mdid'])->find();
							$tmpdata1[] =  $mendian['name'].$mendian['tel'].$mendian['xqname'];
						}
						$tmpdata2 = [
						    $formdatastr,
                            $vo['remark'],
                            $hexiao_order['uname'],
                            $hexiao_order['mendian'],
                            $totalcommission,
                            $parent1commission,
                            $parent1str,
                            $parent2commission,
                            $parent2str,
                            $parent3commission,
                            $parent3str,
                        ];
						$data[] = array_merge($tmpdata1,$tmpdata2);
					}else{
						$tmpdata1 = [
                            ' '.$vo['ordernum'],
							'',
							'',
							'',
							'',
                            $vo['linkman'],
                            $vo['tel'],
                            $vo['area'].' '.$vo['address'],
							$og['name'].$ogremark,
                            $og['procode'],
							$og['ggname'].$barcode,
							$og['num'],
                            $og['refund_num'],
							$og['totalprice'],
							$og['sell_price'],
							$og['cost_price'],
							'',
							'',
							'',
							'',
							'',
							'',
							'',
							'',
							'',
							'',
                            $ogstatus,//status
                            $refund_status,
                            $refund_money,
							'',
							'',
							'',
							'',
							''
                        ];
						if($need_school){
                            $tmpdata1[] = $school_name;
                            $tmpdata1[] = $class_name;
                            $tmpdata1[] = $grade_name;
                            $tmpdata1[] = $member_content;
                        }
						if(getcustom('lipinka_jihuo2')){
							$tmpdata1[] = $vo['duihuan_cardno'];
						}
						if(getcustom('money_dec')){
							$tmpdata1[] = $vo['dec_money'];
						}
						if(getcustom('shop_buy_worknum')){
							$tmpdata1[] = $vo['worknum'];
						}
						if(getcustom('pay_money_combine')){
	                        $tmpdata1[] = '';
	                    }
	                    if(getcustom('member_goldmoney_silvermoney')){
                            if($ShopSendSilvermoney){
                                $tmpdata1[] = $vo['silvermoneydec'];
                            }
                            if($ShopSendGoldmoney){
                                $tmpdata1[] = $vo['goldmoneydec'];
                            }
                        }
						$tmpdata2 = [
						    '',
							'',
							'',
							'',
							$totalcommission,
							$parent1commission,
							$parent1str,
							$parent2commission,
							$parent2str,
							$parent3commission,
							$parent3str,
						];
                        $data[] = array_merge($tmpdata1,$tmpdata2);
					}
				}

				if(getcustom('ciruikang_fenxiao')){
					if($vo['crk_givenum']>0){
						$data[] = [
		                    ' '.$vo['ordernum'],
							'',
							'',
							'',
							'',
		                    '',
		                    '',
		                    '',
							'+随机赠送'.$vo['crk_givenum'].'件'
		                ];
					}
	            }
			}
			if(getcustom('shop_order_merge_export')){
			    if(input('param.merge',0)){
                    //更改        
                    $mergedata = [];
                    foreach($data as $key=>$val){
                        $nowname = $val[8].' '.$val[10].'*'. $val[11];
                        if(!$mergedata[$val[0]]){
                            $val[8] = $nowname;
                            $mergedata[$val[0]] = $val;
                        }else{
                            $proname =$mergedata[$val[0]][8].'，'.$nowname;
                            $mergedata[$val[0]][8] = $proname ;
                        }
                    }
                    $data =array_values($mergedata);
                }
            }
            return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data,'title'=>$title]);
			$this->export_excel($title,$data);
		}
	}
	//订单详情
	public function getdetail(){
		$orderid = input('param.orderid');
		$optionType = input('param.optionType',0);
		if(bid != 0){
			$order = Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->find();
		}else{
			$order = Db::name('shop_order')->where('aid',aid)->where('id',$orderid)->find();
		}
        $order['school_info'] = '';
		if(getcustom('school_product')){
		    $school = '';
		    if($order['school_id']){
		        $school_name = Db::name('school')->where('aid',aid)->where('id',$order['school_id'])->value('name');
		        $school_name && $school .=$school_name;
            }
            if($order['grade_id']){
                $grade_name = Db::name('school_class')->where('aid',aid)->where('id',$order['grade_id'])->value('name');
                $grade_name && $school .=' '. $grade_name;
            }
            if($order['class_id']){
                $class_name = Db::name('school_class')->where('aid',aid)->where('id',$order['class_id'])->value('name');
                $class_name && $school .=' '. $class_name;
            }
            $order['school_info'] = $school;
            $member_content = '';
            if($order['member_content']){
                $member_content_list = $order['member_content']?json_decode($order['member_content'],true):[];
                foreach ($member_content_list as $mk=>$mv){
                    if($mv['key']!='upload'){
                        $member_content .=' '. $mv['value'];
                    }
                }
            }
            $order['member_content'] = $member_content;
        }
		if($order['coupon_rid']){
			$couponrecord = Db::name('coupon_record')->where('id',$order['coupon_rid'])->find();
			$couponnames = Db::name('coupon_record')->where('id','in',$order['coupon_rid'])->column('couponname');
			$couponnames = implode('，',$couponnames);
		}else{
			$couponrecord = false;
			$couponnames = '';
		}
        $ogwhere = [];
        $ogwhere[] = ['aid','=',aid];
        $ogwhere[] = ['orderid','=',$orderid];
        //同步到ERP的订单，由ERP进行发货,系统只发解绑的单品
        if(getcustom('erp_wangdiantong') && $order['wdt_status']==2){
            if($optionType=='发货'){
                $ogwhere[] = ['wdt_status','=',2];
            }
        }
		$oglist = Db::name('shop_order_goods')->where($ogwhere)->select()->toArray();
		$member = Db::name('member')->field('id,nickname,headimg,realname,tel,wxopenid,unionid')->where('id',$order['mid'])->find();
		if(!$member) $member = ['id'=>$order['mid'],'nickname'=>'','headimg'=>''];
		$comdata = array();
		$comdata['parent1'] = ['mid'=>'','nickname'=>'','headimg'=>'','money'=>0,'score'=>0];
		$comdata['parent2'] = ['mid'=>'','nickname'=>'','headimg'=>'','money'=>0,'score'=>0];
		$comdata['parent3'] = ['mid'=>'','nickname'=>'','headimg'=>'','money'=>0,'score'=>0];
		$ogids = [];
		foreach($oglist as $gk=>$v){
			$ogids[] = $v['id'];
			if($v['parent1']){
				$parent1 = Db::name('member')->where('id',$v['parent1'])->find();
				$comdata['parent1']['mid'] = $v['parent1'];
				$comdata['parent1']['nickname'] = $parent1['nickname'];
				$comdata['parent1']['headimg'] = $parent1['headimg'];
				$comdata['parent1']['money'] += $v['parent1commission'];
				$comdata['parent1']['score'] += $v['parent1score'];
			}
			if($v['parent2']){
				$parent2 = Db::name('member')->where('id',$v['parent2'])->find();
				$comdata['parent2']['mid'] = $v['parent2'];
				$comdata['parent2']['nickname'] = $parent2['nickname'];
				$comdata['parent2']['headimg'] = $parent2['headimg'];
				$comdata['parent2']['money'] += $v['parent2commission'];
				$comdata['parent2']['score'] += $v['parent2score'];
			}
			if($v['parent3']){
				$parent3 = Db::name('member')->where('id',$v['parent3'])->find();
				$comdata['parent3']['mid'] = $v['parent3'];
				$comdata['parent3']['nickname'] = $parent3['nickname'];
				$comdata['parent3']['headimg'] = $parent3['headimg'];
				$comdata['parent3']['money'] += $v['parent3commission'];
				$comdata['parent3']['score'] += $v['parent3score'];
			}
            if(getcustom('product_glass')){
                $glassrecord = '';
                if($v['glass_record_id']){
                    $glassrecord = \app\models\Glass::orderGlassRecord($v['glass_record_id'],aid);
                }
                $oglist[$gk]['glassrecord'] = $glassrecord??'';
            }
            if(getcustom('product_service_fee')){
                $shd_remark = Db::name('shop_product')->where('id',$v['proid'])->value('shd_remark');
                $oglist[$gk]['shd_remark'] = $shd_remark;
            }
		}
		$comdata['parent1']['money'] = round($comdata['parent1']['money'],2);
		$comdata['parent2']['money'] = round($comdata['parent2']['money'],2);
		$comdata['parent3']['money'] = round($comdata['parent3']['money'],2);

		$order['formdata'] = \app\models\Freight::getformdata($order['id'],'shop_order');
		//弃用
		if($order['field1']){
			$order['field1data'] = explode('^_^',$order['field1']);
		}
		if($order['field2']){
			$order['field2data'] = explode('^_^',$order['field2']);
		}
		if($order['field3']){
			$order['field3data'] = explode('^_^',$order['field3']);
		}
		if($order['field4']){
			$order['field4data'] = explode('^_^',$order['field4']);
		}
		if($order['field5']){
			$order['field5data'] = explode('^_^',$order['field5']);
		}
		if($order['freight_type']==11){
			$order['freight_content'] = json_decode($order['freight_content'],true);
		}
		$miandanst = Db::name('admin_set')->where('aid',aid)->value('miandanst');
		if(bid==0 && $miandanst==1 && in_array('wx',$this->platform) && ($member['wxopenid'] || $member['unionid'])){ //可以使用小程序物流助手发货
			$canmiandan = 1;
		}else{
			$canmiandan = 0;
		}
		if(getcustom('freight_add_district')){ //暂时试错用
			$appinfo = \app\commons\System::appinfo(aid,'mp');
			if(bid==0 && $miandanst==1 && in_array('wx',$this->platform) && ($member['wxopenid'] || $member['unionid'] || !empty($appinfo['appid']))){
				$canmiandan = 1;
			}
		}
		if($order['checkmemid']){
			$checkmember = Db::name('member')->field('id,nickname,headimg,realname,tel')->where('id',$order['checkmemid'])->find();
		}else{
			$checkmember = [];
		}

        $payorder = [];
        if($order['paytypeid'] == 5) {
            $payorder = Db::name('payorder')->where('id',$order['payorderid'])->where('aid',aid)->find();
            if($payorder) {
                if($payorder['check_status'] === 0) {
                    $payorder['check_status_label'] = '待审核';
                }elseif($payorder['check_status'] == 1) {
                    $payorder['check_status_label'] = '通过';
                }elseif($payorder['check_status'] == 2) {
                    $payorder['check_status_label'] = '驳回';
                }else{
                    $payorder['check_status_label'] = '未上传';
                }
                if($payorder['paypics']) {
                    $payorder['paypics'] = explode(',', $payorder['paypics']);
                    foreach ($payorder['paypics'] as $item) {
                        $payorder['paypics_html'] .= '<img src="'.$item.'" width="200" onclick="preview(this)"/>';
                    }
                }
            }
        }
		if($order['express_content']) $order['express_content'] = json_decode($order['express_content'],true);
		if($order['status'] == 1){
			$order['express_ogids'] = implode(',',$ogids);
		}
		if($order['express_ogids']){
			$order['express_ogids'] = explode(',',$order['express_ogids']);
		}else{
			$order['express_ogids'] = [];
		}
		foreach($order['express_content'] as $k=>$v){
			if(!$v['express_ogids']){
				$v['express_ogids'] = [];
			}else{
				$v['express_ogids'] = explode(',',$v['express_ogids']);
			}
			$order['express_content'][$k] = $v;
		}
		if(getcustom('shop_product_fenqi_pay')){ 
			if($order['is_fenqi'] == 1){
				$fenqi_data = json_decode($order['fenqi_data'],true);
				
				$fenqi_one_paydate = $order['fenqi_one_paydate'];
                $time = time();
				foreach($fenqi_data as $fqkey=>$fq){
                    if($fq['status'] == 0){
                        $i = $fq['fenqi_num'] - 1;
                        if(isset($fq['end_paytime'])){
                            $nextMonth =  strtotime($fq['end_paytime'])+86400;
                        }else{
                            $nextMonth = strtotime("+{$i} month", strtotime($fenqi_one_paydate)+86400);
                        }
                        if($time > $nextMonth){                            
                            $fenqi_data[$fqkey]['status'] = 2;
                        }
                    }
                }
				$order['fenqi_data'] = $fenqi_data;
			}
			
		}
		if(getcustom('pay_money_combine')){
            if($order['combine_money'] && $order['combine_money'] > 0){
                if(!empty($order['paytype'])){
                	$order['paytype'] .= ' + '.t('余额').'支付';
                }else{
                	$order['paytype'] .= t('余额').'支付';
                }
            }
        }
        $order['canFahuo'] = 1;
        $order['canPay'] = 1;
        if(getcustom('supply_zhenxin')){
            if($order['issource'] == 1 && $order['source'] == 'supply_zhenxin'){
                $order['canFahuo'] = 0;
                $order['canPay'] = 0;
            }
        }
		return json(['order'=>$order,'couponrecord'=>$couponrecord,'couponnames'=>$couponnames,'oglist'=>$oglist,'member'=>$member,'comdata'=>$comdata,'canmiandan'=>$canmiandan,'checkmember'=>$checkmember,'payorder' => $payorder]);
	}
	
	//设置备注
	public function setremark(){
		$orderid = input('post.orderid/d');
		$content = input('post.content');
		if(bid == 0){
			Db::name('shop_order')->where('aid',aid)->where('id',$orderid)->update(['remark'=>$content]);
		}else{
			Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->update(['remark'=>$content]);
		}
		\app\commons\System::plog('商城订单设置备注'.$orderid);
		return json(['status'=>1,'msg'=>'设置完成']);
	}
	//改价格
	public function changeprice(){
		$orderid = input('post.orderid/d');
		$newprice = input('post.newprice/f');
		$newordernum = date('ymdHis').rand(100000,999999);

        $where = [];
        $where[] = ['aid','=',aid];
		if(bid > 0){
            $where[] = ['bid','=',bid];
		}

        $order = Db::name('shop_order')->where($where)->where('id',$orderid)->find();
        if($newprice > $order['totalprice']) return json(['status'=>0,'msg'=>'只能优惠不可加价，加价可通过下单其他商品补差价']);
        $ordernumArr = explode('_',$order['ordernum']);
        if($ordernumArr[1]) $newordernum .= '_'.$ordernumArr[1];
        $discount_money_admin = $order['totalprice']-$newprice;//管理员优惠金额（正数）
        Db::name('shop_order')->where($where)->where('id',$orderid)->update(['totalprice'=>$newprice,'ordernum'=>$newordernum,'discount_money_admin'=>$discount_money_admin]);
        Db::name('shop_order_goods')->where($where)->where('orderid',$orderid)->update(['ordernum'=>$newordernum]);
        //订单商品价格也需同步修改，涉及商家结算
        $oglist = Db::name('shop_order_goods')->where($where)->where('orderid',$orderid)->select()->toArray();
        foreach ($oglist as $og){
            $rate = $newprice/$order['totalprice'];
            $og['real_totalprice'] = $rate*$og['real_totalprice'];
            if(!is_null($og['business_total_money'])) {
                $og['business_total_money'] = $rate*$og['business_total_money'];
            }
            Db::name('shop_order_goods')->where('id',$og['id'])->where('orderid',$orderid)->update($og);
        }

		$payorderid = Db::name('shop_order')->where('aid',aid)->where('id',$orderid)->value('payorderid');

		\app\models\Payorder::updateorder($payorderid,$newordernum,$newprice,$orderid);
		\app\commons\System::plog('商城订单改价格'.$orderid.'，原价格:'.$order['totalprice'].'，新价格:'.$newprice);
		return json(['status'=>1,'msg'=>'修改完成']);
	}
	//关闭订单
	public function closeOrder(){
		$orderid = input('post.orderid/d');
		if(bid == 0){
			$order = Db::name('shop_order')->where('id',$orderid)->where('aid',aid)->find();
		}else{
			$order = Db::name('shop_order')->where('id',$orderid)->where('aid',aid)->where('bid',bid)->find();
		}
		if(!$order || $order['status']!=0){
			return json(['status'=>0,'msg'=>'关闭失败,订单状态错误']);
		}
		//加库存
		$oglist = Db::name('shop_order_goods')->where('aid',aid)->where('orderid',$orderid)->select()->toArray();
		foreach($oglist as $og){
			Db::name('shop_guige')->where('aid',aid)->where('id',$og['ggid'])->update(['stock'=>Db::raw("stock+".$og['num']),'sales'=>Db::raw("sales-".$og['num'])]);
			Db::name('shop_product')->where('aid',aid)->where('id',$og['proid'])->update(['stock'=>Db::raw("stock+".$og['num']),'sales'=>Db::raw("sales-".$og['num'])]);
			if(getcustom('guige_split')){
				\app\models\ShopProduct::addlinkstock($og['proid'],$og['ggid'],$og['num']);
			}
			if(getcustom('ciruikang_fenxiao')){
                //是否开启了商城商品需上级购买足量
                $deal_ogstock2 = \app\customs\CiruikangCustom::deal_ogstock2($order,$og,$og['num'],'下级订单关闭');
            }
		}
		
		//优惠券抵扣的返还
		if($order['coupon_rid']){
			Db::name('coupon_record')->where('aid',aid)->where('mid',$order['mid'])->where('id','in',$order['coupon_rid'])->update(['status'=>0,'usetime'=>'']);
		}

		if(getcustom('money_dec')){
            //返回余额抵扣
            if($order['dec_money']>0){
                \app\commons\Member::addmoney(aid,$order['mid'],$order['dec_money'],t('余额').'抵扣返回，订单号: '.$order['ordernum']);
            }
        }

        if(getcustom('member_goldmoney_silvermoney')){
            //返回银值抵扣
            if($order['silvermoneydec']>0){
                $res = \app\commons\Member::addsilvermoney(aid,$order['mid'],$order['silvermoneydec'],t('银值').'抵扣返回，订单号: '.$order['ordernum'],$order['ordernum']);
            }
            //返回金值抵扣
            if($order['goldmoneydec']>0){
                $res = \app\commons\Member::addgoldmoney(aid,$order['mid'],$order['goldmoneydec'],t('金值').'抵扣返回，订单号: '.$order['ordernum'],$order['ordernum']);
            }
        }

        if(getcustom('pay_money_combine')){
            //返回余额组合支付
            if($order['combine_money']>0){
                $res = \app\commons\Member::addmoney(aid,$order['mid'],$order['combine_money'],t('余额').'组合支付返回，订单号: '.$order['ordernum']);
                if($res['status'] ==1){
                    Db::name('shop_order')->where('id',$orderid)->update(['combine_money'=>0]);
                }
            }
        }

		$rs = Db::name('shop_order')->where('id',$orderid)->where('aid',aid)->update(['status'=>4]);
		Db::name('shop_order_goods')->where('orderid',$orderid)->where('aid',aid)->update(['status'=>4]);
		\app\commons\System::plog('商城订单关闭订单'.$orderid);
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//改为已支付
	public function ispay(){
		if(bid > 0) showmsg('无权限操作');
		$orderid = input('post.orderid/d');
		if(getcustom('douyin_groupbuy')){
            $order = Db::name('shop_order')->where(['id'=>$orderid,'aid'=>aid,'bid'=>bid])->field('id,status,isdygroupbuy')->find();
            if(!$order || $order['status'] !=0){
                return $this->json(['status'=>0,'msg'=>'订单状态不符']);
            }
            if($order['isdygroupbuy'] == 1){
                return $this->json(['status'=>0,'msg'=>'抖音团购券订单不支持手动改为已支付']);
            }
        }
        if(getcustom('supply_zhenxin')){
            if($order['issource'] == 1 && $order['source'] == 'supply_zhenxin'){
                return $this->json(['status'=>0,'msg'=>'甄新汇选订单不支持手动改为已支付']);
            }
        }

        $updata = [];
        $updata['status']  = 1;
        $updata['paytime'] = time();
        $updata['paytype'] = '后台支付';

        if(getcustom('pay_money_combine')){
        	$order = Db::name('shop_order')->where(['id'=>$orderid,'aid'=>aid,'bid'=>bid])->field('totalprice,combine_money')->find();
			//余额组合支付退款，改为都是余额支付
			if($order['combine_money']>0){
				$updata['combine_money'] = 0;
				$updata['combine_wxpay'] = 0;
				$updata['combine_alipay']= 0;
			}
			
        }
		if(bid == 0){
			Db::name('shop_order')->where('aid',aid)->where('id',$orderid)->update($updata);
		}else{
			Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->update($updata);
		}
		\app\models\Payorder::shop_pay($orderid);

		//Db::name('shop_order_goods')->where('orderid',$orderid)->where('aid',aid)->where('bid',bid)->update(['status'=>1]);
		////奖励积分
		//$order = Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->find();
		//if($order['givescore'] > 0){
		//	\app\commons\Member::addscore(aid,$order['mid'],$order['givescore'],'购买产品奖励'.t('积分'));
		//}
		\app\commons\System::plog('商城订单改为已支付'.$orderid);

        // 自动派单到大厅
        if(getcustom('express_paidan')){
            $set = Db::name('peisong_set')->where('aid',aid)->find();
            if($set['paidantype'] == 0){
                if($set['express_paidan'] == 1){
                    $this->peisong($orderid,'shop_order');
                }
            }
        }
		return json(['status'=>1,'msg'=>'操作成功']);
	}
    //下配送单
    public function peisong($orderid,$type,$psid = 0){
        $set = Db::name('peisong_set')->where('aid',aid)->find();
        if(bid == 0){
            $order = Db::name($type)->where('id',$orderid)->where('aid',aid)->find();
        }else{
            $order = Db::name($type)->where('id',$orderid)->where('aid',aid)->where('bid',bid)->find();
        }
        if(!$order) return json(['status'=>0,'msg'=>'订单不存在']);
        if($order['status']!=1 && $order['status']!=12) return json(['status'=>0,'msg'=>'订单状态不符合']);
        $other = [];
        $rs = \app\models\PeisongOrder::create($type,$order,$psid,$other);

        //发货信息录入 微信小程序+微信支付
        if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
            \app\commons\Order::wxShipping(aid,$order,$type);
        }
        if($rs['status']==0) return json($rs);
        \app\commons\System::plog('订单配送'.$orderid);
    }

	//改为尾款已支付
	public function ispaybalance(){
		$orderid = input('post.orderid/d');
		if(bid == 0){
			Db::name('shop_order')->where('aid',aid)->where('id',$orderid)->update(['balance_pay_status'=>1]);
		}else{
			Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->update(['balance_pay_status'=>1]);
		}
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//发货
	public function sendExpress(){
		set_time_limit(0);
		ini_set('memory_limit', -1);
		$orderid = input('post.orderid/d');
		if(bid == 0){
			$order = Db::name('shop_order')->where('aid',aid)->where('id',$orderid)->find();
		}else{
			$order = Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->find();
		}

        if(getcustom('supply_zhenxin')){
            if($order['issource'] == 1 && $order['source'] == 'supply_zhenxin'){
                return json(['status'=>0,'msg'=>'甄新汇选商品订单不允许发货']);
            }
        }
        $refundingMoney = Db::name('shop_refund_order')->where('orderid',$order['id'])->where('aid',aid)->whereIn('refund_status',[1,4])->sum('refund_money');
        if($refundingMoney > 0) {
            return json(['status'=>0,'msg'=>'请先处理完进行中的退款单']);
        }

		//如果选择了配送时间，未到配送时间内不可以进行配送
		if(getcustom('business_withdraw') && ($this->auth_data == 'all' || in_array('OrderSendintime',$this->auth_data))){
			if($order['freight_time']){
				$freight_time = explode('~',$order['freight_time']);
				$begin_time = strtotime($freight_time[0]);
				$date = explode(' ',$freight_time[0]);
				$end_time =strtotime($date[0].' '.$freight_time[1]);
				if(time()<$begin_time || (time()>$end_time)){
					return json(['status'=>0,'msg'=>'未在配送时间范围内']);	
				}
			}
		}

		if($order['status']!=1 && $order['status']!=2){
			return json(['status'=>0,'msg'=>'该订单状态不允许发货']);
		}
        if($order['freight_type']==4){
            $member = Db::name('member')->where('id',$order['mid'])->find();
            $og = Db::name('shop_order_goods')->where('orderid',$order['id'])->find();
            $codelist = Db::name('shop_codelist')->where('proid',$og['proid'])->where('status',0)->order('id')->limit($og['num'])->select()->toArray();
            if(!$codelist){
                return json(['status'=>0,'msg'=>'卡密库存不足']);
            }
            if($codelist && count($codelist) >= $og['num']){
                $pscontent = [];
                foreach($codelist as $codeinfo){
                    $pscontent[] = $codeinfo['content'];
                    Db::name('shop_codelist')->where('id',$codeinfo['id'])->update(['orderid'=>$order['id'],'ordernum'=>$order['ordernum'],'headimg'=>$member['headimg'],'nickname'=>$member['nickname'],'buytime'=>time(),'status'=>1]);
                }
                $pscontent = implode("\r\n",$pscontent);
                Db::name('shop_order')->where('id',$order['id'])->update(['freight_content'=>$pscontent,'status'=>2,'send_time'=>time()]);
                Db::name('shop_order_goods')->where('orderid',$order['id'])->update(['status'=>2]);
            }
            if($order['fromwxvideo'] == 1){
                \app\commons\Wxvideo::deliverysend($orderid);
            }

            //发货信息录入 微信小程序+微信支付
            if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
                \app\commons\Order::wxShipping($order['aid'],$order);
            }
            if(getcustom('plug_zhiming')){
                \app\commons\Order::collect($order);
                Db::name('shop_order')->where('id',$orderid)->update(['status'=>3,'collect_time'=>time()]);
                Db::name('shop_order_goods')->where('orderid',$orderid)->update(['status'=>3,'endtime'=>time()]);
            }
            $express_com = '卡密订单';
            $express_no = '卡密订单';
            //订单发货通知
            $tmplcontent = [];
            $tmplcontent['first'] = '您的订单已发货';
            $tmplcontent['remark'] = '请点击查看详情~';
            $tmplcontent['keyword1'] = $order['title'];
            $tmplcontent['keyword2'] = $express_com;
            $tmplcontent['keyword3'] = $express_no;
            $tmplcontent['keyword4'] = $order['linkman'].' '.$order['tel'];
            $tmplcontentNew = [];
            $tmplcontentNew['thing4'] = $order['title'];//商品名称
            $tmplcontentNew['thing13'] = $express_com;//快递公司
            $tmplcontentNew['character_string14'] = $express_no;//快递单号
            $tmplcontentNew['thing16'] = $order['linkman'].' '.$order['tel'];//收货人
            \app\commons\Wechat::sendtmpl(aid,$order['mid'],'tmpl_orderfahuo',$tmplcontent,m_url('pages/my/usercenter'),$tmplcontentNew);
            //订阅消息
            $tmplcontent = [];
            $tmplcontent['thing2'] = $order['title'];
            $tmplcontent['thing7'] = $express_com;
            $tmplcontent['character_string4'] = $express_no;
            $tmplcontent['thing11'] = $order['address'];

            $tmplcontentnew = [];
            $tmplcontentnew['thing29'] = $order['title'];
            $tmplcontentnew['thing1'] = $express_com;
            $tmplcontentnew['character_string2'] = $express_no;
            $tmplcontentnew['thing9'] = $order['address'];
            \app\commons\Wechat::sendwxtmpl(aid,$order['mid'],'tmpl_orderfahuo',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);

            //短信通知
            $member = Db::name('member')->where('id',$order['mid'])->find();
            if($member['tel']){
                $tel = $member['tel'];
            }else{
                $tel = $order['tel'];
            }
            $rs = \app\commons\Sms::send(aid,$tel,'tmpl_orderfahuo',['ordernum'=>$order['ordernum'],'express_com'=>$express_com,'express_no'=>$express_no]);

            \app\commons\System::plog('商城订单发货'.$orderid);
            return json(['status'=>1,'msg'=>'操作成功']);
        }
		$express_isbufen = 0;
		$expres_content = '';
		if($order['freight_type']==10){
			$pic = input('post.pic');
			$fhname = input('post.fhname');
			$fhaddress = input('post.fhaddress');
			$shname = input('post.shname');
			$shaddress = input('post.shaddress');
			$remark = input('post.remark');
			$data = [];
			$data['aid'] = aid;
			$data['pic'] = $pic;
			$data['fhname'] = $fhname;
			$data['fhaddress'] = $fhaddress;
			$data['shname'] = $shname;
			$data['shaddress'] = $shaddress;
			$data['remark'] = $remark;
			$data['createtime'] = time();
			$id = Db::name('freight_type10_record')->insertGetId($data);
			$express_com = '货运托运';
			$express_no = $id;
		}else{
			$express_comArr = input('post.express_com/a');
			$express_noArr = input('post.express_no/a');
			$express_ogidsArr = input('post.express_ogids/a');

			$express_ogidsAll = [];
			if(count($express_comArr) > 1){
				$express_com = '多单发货';
				$express_no = '';
				$express_content = [];
				foreach($express_comArr as $k=>$v){
					$express_content[] = ['express_com'=>$v,'express_no'=>$express_noArr[$k],'express_ogids'=>$express_ogidsArr[$k]];
					if($express_ogidsArr[$k]){
						foreach(explode(',',$express_ogidsArr[$k]) as $ogid){
							$express_ogidsAll[] = $ogid;
						}
					}
				}
				$express_content = jsonEncode($express_content);
			}else{
				$express_com = $express_comArr[0];
				$express_no = $express_noArr[0];
				$express_ogids = $express_ogidsArr[0];
				foreach(explode(',',$express_ogidsArr[0]) as $ogid){
					$express_ogidsAll[] = $ogid;
				}
			}

			$oglist = Db::name('shop_order_goods')->where('orderid',$orderid)->where('aid',aid)->select()->toArray();
			if(count($oglist) > 1 && $express_ogidsAll){
				foreach($oglist as $og){
					if(!in_array($og['id'],$express_ogidsAll)){
						$express_isbufen = 1;
					}
				}
			}
		}
		
		if($order['status']!=1){ //修改物流信息
			Db::name('shop_order')->where('aid',aid)->where('id',$orderid)->update(['express_com'=>$express_com,'express_no'=>$express_no,'express_ogids'=>$express_ogids,'express_content'=>$express_content,'express_isbufen'=>$express_isbufen]);
			return json(['status'=>1,'msg'=>'操作成功']);
		}

		Db::name('shop_order')->where('aid',aid)->where('id',$orderid)->update(['express_com'=>$express_com,'express_no'=>$express_no,'express_ogids'=>$express_ogids,'express_content'=>$express_content,'send_time'=>time(),'status'=>2,'express_isbufen'=>$express_isbufen]);
		Db::name('shop_order_goods')->where('orderid',$orderid)->where('aid',aid)->update(['status'=>2]);
		
		if($order['fromwxvideo'] == 1){
			\app\commons\Wxvideo::deliverysend($orderid);
		}
        //发货信息录入 微信小程序+微信支付
        if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
            \app\commons\Order::wxShipping(aid,$order,'shop',['express_com'=>$express_comArr[0],'express_no'=>$express_noArr[0]]);
        }
        //支付宝小程序交易组件订单状态同步
        if($order['platform']=='alipay' && $order['paytypeid'] == 3){
            if(getcustom('alipay_plugin_trade') && $order['alipay_component_orderid']){
                $pluginResult = \app\commons\Alipay::pluginOrderSend($orderid,'shop');
            }
        }

		if(getcustom('cefang') && aid==2){ //定制1 订单对接 同步到策方
		    $order['status'] = 2;
			\app\customs\Cefang::api($order);
		}

		//订单发货通知
		$tmplcontent = [];
		$tmplcontent['first'] = '您的订单已发货';
		$tmplcontent['remark'] = '请点击查看详情~';
		$tmplcontent['keyword1'] = $order['title'];
		$tmplcontent['keyword2'] = $express_com;
		$tmplcontent['keyword3'] = $express_no;
		$tmplcontent['keyword4'] = $order['linkman'].' '.$order['tel'];
        $tmplcontentNew = [];
        $tmplcontentNew['thing4'] = $order['title'];//商品名称
        $tmplcontentNew['thing13'] = $express_com;//快递公司
        $tmplcontentNew['character_string14'] = $express_no;//快递单号
        $tmplcontentNew['thing16'] = $order['linkman'].' '.$order['tel'];//收货人
		\app\commons\Wechat::sendtmpl(aid,$order['mid'],'tmpl_orderfahuo',$tmplcontent,m_url('pages/my/usercenter'),$tmplcontentNew);
		//订阅消息
		$tmplcontent = [];
		$tmplcontent['thing2'] = $order['title'];
		$tmplcontent['thing7'] = $express_com;
		$tmplcontent['character_string4'] = $express_no;
		$tmplcontent['thing11'] = $order['address'];
		
		$tmplcontentnew = [];
		$tmplcontentnew['thing29'] = $order['title'];
		$tmplcontentnew['thing1'] = $express_com;
		$tmplcontentnew['character_string2'] = $express_no;
		$tmplcontentnew['thing9'] = $order['address'];
		\app\commons\Wechat::sendwxtmpl(aid,$order['mid'],'tmpl_orderfahuo',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);

		//短信通知
		$member = Db::name('member')->where('id',$order['mid'])->find();
		if($member['tel']){
			$tel = $member['tel'];
		}else{
			$tel = $order['tel'];
		}
		$rs = \app\commons\Sms::send(aid,$tel,'tmpl_orderfahuo',['ordernum'=>$order['ordernum'],'express_com'=>$express_com,'express_no'=>$express_no]);
		
		\app\commons\System::plog('商城订单发货'.$orderid);
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//批量发货
	public function plfh(){
		$express_com = input('post.plfh_express');
		$file = input('post.plfh_file');
		$exceldata = $this->import_excel($file);
		//dump($exceldata);
		$countnum = count($exceldata);
		$successnum = 0;
		$errornum = 0;
		foreach($exceldata as $v){
			$ordernum = trim($v[0]);
			$express_no = $v[1];
			$order = Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('ordernum',$ordernum)->find();
			if(!$order || $order['status'] != 1 && $order['status'] != 2){
				$errornum++;
				continue;
			}
			if(getcustom('supply_zhenxin')){
	            if($order['issource'] == 1 && $order['source'] == 'supply_zhenxin'){
	                continue;
	            }
	        }
			$orderid = $order['id'];

			$updata = [];
			if($order['express_content'] && !empty($order['express_content'])){
				$express_content = json_decode($order['express_content'],true);
				if($express_content){
					$add = true;
					foreach($express_content as $ev){
						if($ev['express_com'] == $express_com && $ev['express_no'] == $express_no){
							$add = false;
						}
					}
					if(!$add){
						continue;
					}
				}
			}else{
				$express_content = [];
				$updata['express_com'] = $express_com;
				$updata['express_no']  = $express_no;
			}
			$express_content[] = ['express_com'=>$express_com,'express_no'=>$express_no];
			$updata['express_content'] = json_encode($express_content);
			$updata['send_time'] = time();
			$updata['status']    = 2;
			$up = Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->update($updata);
			if(!$up){
				continue;
			}
			Db::name('shop_order_goods')->where('orderid',$orderid)->where('aid',aid)->where('bid',bid)->update(['status'=>2]);
			
			if($order['fromwxvideo'] == 1){
				\app\commons\Wxvideo::deliverysend($orderid);
			}

            //发货信息录入 微信小程序+微信支付
            if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
                \app\commons\Order::wxShipping(aid,$order,'shop',['express_com'=>$express_com,'express_no'=>$express_no]);
            }

		    if(getcustom('cefang') && aid==2){ //定制1 订单对接 同步到策方
				$order['status'] = 2;
			    \app\customs\Cefang::api($order);
		    }
			//订单发货通知
			$tmplcontent = [];
			$tmplcontent['first'] = '您的订单已发货';
			$tmplcontent['remark'] = '请点击查看详情~';
			$tmplcontent['keyword1'] = $order['title'];
			$tmplcontent['keyword2'] = $express_com;
			$tmplcontent['keyword3'] = $express_no;
			$tmplcontent['keyword4'] = $order['linkman'].' '.$order['tel'];
            $tmplcontentNew = [];
            $tmplcontentNew['thing4'] = $order['title'];//商品名称
            $tmplcontentNew['thing13'] = $express_com;//快递公司
            $tmplcontentNew['character_string14'] = $express_no;//快递单号
            $tmplcontentNew['thing16'] = $order['linkman'].' '.$order['tel'];//收货人
			\app\commons\Wechat::sendtmpl(aid,$order['mid'],'tmpl_orderfahuo',$tmplcontent,m_url('pages/my/usercenter'),$tmplcontentNew);
			//订阅消息
			$tmplcontent = [];
			$tmplcontent['thing2'] = $order['title'];
			$tmplcontent['thing7'] = $express_com;
			$tmplcontent['character_string4'] = $express_no;
			$tmplcontent['thing11'] = $order['address'];
			
			$tmplcontentnew = [];
			$tmplcontentnew['thing29'] = $order['title'];
			$tmplcontentnew['thing1'] = $express_com;
			$tmplcontentnew['character_string2'] = $express_no;
			$tmplcontentnew['thing9'] = $order['address'];
			\app\commons\Wechat::sendwxtmpl(aid,$order['mid'],'tmpl_orderfahuo',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);

			//短信通知
			$member = Db::name('member')->where('id',$order['mid'])->find();
			if($member['tel']){
				$tel = $member['tel'];
			}else{
				$tel = $order['tel'];
			}
			$rs = \app\commons\Sms::send(aid,$tel,'tmpl_orderfahuo',['ordernum'=>$order['ordernum'],'express_com'=>$express_com,'express_no'=>$express_no]);
			$successnum++;
		}
		\app\commons\System::plog('商城订单批量发货');
		return json(['status'=>1,'msg'=>'共导入 '.$countnum.' 条数据，成功发货 '.$successnum.' 条，失败 '.$errornum.' 条']);
	}
	//选中项批量发货
	public function plfh2(){
		set_time_limit(0);
		ini_set('memory_limit', -1);

		$ids = input('post.ids/a');
		$express_com = input('post.express_com');
		$express_no = input('post.express_no');
		
		if(input('post.type') == 1){
			if(bid == 0){
				$orderlist = Db::name('shop_order')->where('aid',aid)->where('id','in',$ids)->select()->toArray();
			}else{
				$orderlist = Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('id','in',$ids)->select()->toArray();
			}
		}else{
			$where = [];
			$where[] = ['order.aid','=',aid];
			if(bid==0){
				if(input('param.bid')){
					$where[] = ['order.bid','=',input('param.bid')];
				}elseif(input('param.showtype')==2){
					$where[] = ['order.bid','<>',0];
				}elseif(input('param.showtype')=='all'){
					$where[] = ['order.bid','>=',0];
				}else{
					$where[] = ['order.bid','=',0];
				}
			}else{
				$where[] = ['order.bid','=',bid];
			}
			if($this->mdid){
				$where[] = ['order.mdid','=',$this->mdid];
			}
			if(input('param.mid')) $where[] = ['order.mid','=',input('param.mid')];
			if(input('param.proname')) $where[] = ['order.proname','like','%'.input('param.proname').'%'];
			if(input('param.ordernum')) $where[] = ['order.ordernum','like','%'.input('param.ordernum').'%'];
			if(input('param.nickname')) $where[] = ['member.nickname|member.realname','like','%'.input('param.nickname').'%'];
			if(input('param.linkman')) $where[] = ['order.linkman','like','%'.input('param.linkman').'%'];
			if(input('param.tel')) $where[] = ['order.tel','like','%'.input('param.tel').'%'];

			if(getcustom('pay_transfer')){
				if(input('?param.transfer_check') && input('param.transfer_check')!==''){
					$where[] = ['order.paytypeid','=',5];
					$where[] = ['order.transfer_check','=',input('param.transfer_check')];
				}
			}

			if(input('?param.status') && input('param.status')!==''){
				if(input('param.status') == 5){
					$where[] = ['order.refund_status','=',1];
				}elseif(input('param.status') == 6){
					$where[] = ['order.refund_status','=',2];
				}elseif(input('param.status') == 7){
					$where[] = ['order.refund_status','=',3];
				}else{
					$where[] = ['order.status','=',input('param.status')];
				}
			}
			if(input('param.ctime')){
				$ctime = explode(' ~ ',input('param.ctime'));
				if(input('param.time_type') == 1){ //下单时间
					$where[] = ['order.createtime','>=',strtotime($ctime[0])];
					$where[] = ['order.createtime','<',strtotime($ctime[1])];
				}elseif(input('param.time_type') == 2){ //付款时间
					$where[] = ['order.paytime','>=',strtotime($ctime[0])];
					$where[] = ['order.paytime','<',strtotime($ctime[1])];
				}elseif(input('param.time_type') == 3){ //发货时间
					$where[] = ['order.send_time','>=',strtotime($ctime[0])];
					$where[] = ['order.send_time','<',strtotime($ctime[1])];
				}elseif(input('param.time_type') == 4){ //完成时间
					$where[] = ['order.collect_time','>=',strtotime($ctime[0])];
					$where[] = ['order.collect_time','<',strtotime($ctime[1])];
				}
			}
			if(input('param.keyword')){
				$keyword = input('param.keyword');
				$keyword_type = input('param.keyword_type');
				if($keyword_type == 1){ //订单号
					$where[] = ['order.ordernum','like','%'.$keyword.'%'];
				}elseif($keyword_type == 2){ //会员ID
					$where[] = ['order.mid','=',$keyword];
				}elseif($keyword_type == 3){ //会员信息
					$where[] = ['member.nickname|member.realname','like','%'.$keyword.'%'];
				}elseif($keyword_type == 4){ //收货信息
					$where[] = ['order.linkman|order.tel|order.area|order.address','like','%'.$keyword.'%'];
				}elseif($keyword_type == 5){ //快递单号
					$where[] = ['order.express_no','like','%'.$keyword.'%'];
				}elseif($keyword_type == 6){ //商品ID
					$orderids = Db::name('shop_order_goods')->where('aid',aid)->where('proid',$keyword)->column('orderid');
					$where[] = ['order.id','in',$orderids];
				}elseif($keyword_type == 7){ //商品名称
					$orderids = Db::name('shop_order_goods')->where('aid',aid)->where('name','like','%'.$keyword.'%')->column('orderid');
					$where[] = ['order.id','in',$orderids];
				}elseif($keyword_type == 8){ //商品编码
					$orderids = Db::name('shop_order_goods')->where('aid',aid)->where('procode','like','%'.$keyword.'%')->column('orderid');
					$where[] = ['order.id','in',$orderids];
				}elseif($keyword_type == 9){ //核销员
					$orderids = Db::name('hexiao_order')->where('aid',aid)->where('type','shop')->where('remark','like','%'.$keyword.'%')->column('orderid');
					$where[] = ['order.id','in',$orderids];
				}elseif($keyword_type == 10){ //所属门店
					$mdids = Db::name('mendian')->where('aid',aid)->where('name','like','%'.$keyword.'%')->column('id');
					$where[] = ['order.mdid','in',$mdids];
				}elseif($keyword_type == 11){
					
				}elseif($keyword_type == 21){ //兑换卡号
					$where[] = ['order.duihuan_cardno','=',$keyword];
				}
			}
			if(input('param.fxmid')){
				$fxmid = input('param.fxmid/d');
				$where[] = Db::raw("order.id in (select orderid from ".table_name('shop_order_goods')." where parent1={$fxmid} or parent2={$fxmid} or parent3={$fxmid})");
			}
			$orderlist = Db::name('shop_order')->alias('order')->field('order.*')->leftJoin('member member','member.id=order.mid')->where($where)->select()->toArray();
		}

		$countnum = count($orderlist);
		$successnum = 0;
		$errornum = 0;
		foreach($orderlist as $order){
			if(!$order || $order['status'] != 1 && $order['status'] != 2){
				$errornum++;
				continue;
			}
			if(getcustom('supply_zhenxin')){
	            if($order['issource'] == 1 && $order['source'] == 'supply_zhenxin'){
	                continue;
	            }
	        }
			$orderid = $order['id'];

			$updata = [];
			if($order['express_content'] && !empty($order['express_content'])){
				$express_content = json_decode($order['express_content'],true);
				if($express_content){
					$add = true;
					foreach($express_content as $ev){
						if($ev['express_com'] == $express_com && $ev['express_no'] == $express_no){
							$add = false;
						}
					}
					if(!$add){
						continue;
					}
				}
			}else{
				$express_content = [];
				$updata['express_com'] = $express_com;
				$updata['express_no']  = $express_no;
			}
			$express_content[] = ['express_com'=>$express_com,'express_no'=>$express_no];
			$updata['express_content'] = json_encode($express_content);
			$updata['send_time'] = time();
			$updata['status']    = 2;
			$up = Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->update($updata);
			if(!$up){
				continue;
			}
			Db::name('shop_order_goods')->where('orderid',$orderid)->where('aid',aid)->where('bid',bid)->update(['status'=>2]);
			
			if($order['fromwxvideo'] == 1){
				\app\commons\Wxvideo::deliverysend($orderid);
			}

            //发货信息录入 微信小程序+微信支付
            if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
                \app\commons\Order::wxShipping(aid,$order,'shop',['express_com'=>$express_com,'express_no'=>$express_no]);
            }

		    if(getcustom('cefang') && aid==2){ //定制1 订单对接 同步到策方
				$order['status'] = 2;
			    \app\customs\Cefang::api($order);
		    }
			//订单发货通知
			$tmplcontent = [];
			$tmplcontent['first'] = '您的订单已发货';
			$tmplcontent['remark'] = '请点击查看详情~';
			$tmplcontent['keyword1'] = $order['title'];
			$tmplcontent['keyword2'] = $express_com;
			$tmplcontent['keyword3'] = $express_no;
			$tmplcontent['keyword4'] = $order['linkman'].' '.$order['tel'];
            $tmplcontentNew = [];
            $tmplcontentNew['thing4'] = $order['title'];//商品名称
            $tmplcontentNew['thing13'] = $express_com;//快递公司
            $tmplcontentNew['character_string14'] = $express_no;//快递单号
            $tmplcontentNew['thing16'] = $order['linkman'].' '.$order['tel'];//收货人
			\app\commons\Wechat::sendtmpl(aid,$order['mid'],'tmpl_orderfahuo',$tmplcontent,m_url('pages/my/usercenter'),$tmplcontentNew);
			//订阅消息
			$tmplcontent = [];
			$tmplcontent['thing2'] = $order['title'];
			$tmplcontent['thing7'] = $express_com;
			$tmplcontent['character_string4'] = $express_no;
			$tmplcontent['thing11'] = $order['address'];
			
			$tmplcontentnew = [];
			$tmplcontentnew['thing29'] = $order['title'];
			$tmplcontentnew['thing1'] = $express_com;
			$tmplcontentnew['character_string2'] = $express_no;
			$tmplcontentnew['thing9'] = $order['address'];
			\app\commons\Wechat::sendwxtmpl(aid,$order['mid'],'tmpl_orderfahuo',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);

			//短信通知
			$member = Db::name('member')->where('id',$order['mid'])->find();
			if($member['tel']){
				$tel = $member['tel'];
			}else{
				$tel = $order['tel'];
			}
			$rs = \app\commons\Sms::send(aid,$tel,'tmpl_orderfahuo',['ordernum'=>$order['ordernum'],'express_com'=>$express_com,'express_no'=>$express_no]);
			$successnum++;
		}
		\app\commons\System::plog('商城订单批量发货');
		return json(['status'=>1,'msg'=>'共操作 '.$countnum.' 条数据，成功发货 '.$successnum.' 条，失败 '.$errornum.' 条']);
	}
    //批量发货-云仓
    public function plfhyc(){
        $file = input('post.plfhyc_file');
        $exceldata = $this->import_excel($file);
//        dd($exceldata);
        $countnum = count($exceldata);
        $successnum = 0;
        $errornum = 0;
        foreach($exceldata as $v){
            $ordernum = $v[1];
            $express_com = $v[4];
            $express_no = $v[5];
            $order = Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('ordernum',$ordernum)->find();
            if(!$order || $order['status'] != 1 && $order['status'] != 2){
                $errornum++;
                continue;
            }
            if(getcustom('supply_zhenxin')){
	            if($order['issource'] == 1 && $order['source'] == 'supply_zhenxin'){
	                continue;
	            }
	        }
            $orderid = $order['id'];
            Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->update(['express_com'=>$express_com,'express_no'=>$express_no,'send_time'=>time(),'status'=>2]);
            Db::name('shop_order_goods')->where('orderid',$orderid)->where('aid',aid)->where('bid',bid)->update(['status'=>2]);

            if($order['fromwxvideo'] == 1){
                \app\commons\Wxvideo::deliverysend($orderid);
            }

            //订单发货通知
            $tmplcontent = [];
            $tmplcontent['first'] = '您的订单已发货';
            $tmplcontent['remark'] = '请点击查看详情~';
            $tmplcontent['keyword1'] = $order['title'];
            $tmplcontent['keyword2'] = $express_com;
            $tmplcontent['keyword3'] = $express_no;
            $tmplcontent['keyword4'] = $order['linkman'].' '.$order['tel'];
            $tmplcontentNew = [];
            $tmplcontentNew['thing4'] = $order['title'];//商品名称
            $tmplcontentNew['thing13'] = $express_com;//快递公司
            $tmplcontentNew['character_string14'] = $express_no;//快递单号
            $tmplcontentNew['thing16'] = $order['linkman'].' '.$order['tel'];//收货人
            \app\commons\Wechat::sendtmpl(aid,$order['mid'],'tmpl_orderfahuo',$tmplcontent,m_url('pages/my/usercenter'),$tmplcontentNew);
            //订阅消息
            $tmplcontent = [];
            $tmplcontent['thing2'] = $order['title'];
            $tmplcontent['thing7'] = $express_com;
            $tmplcontent['character_string4'] = $express_no;
            $tmplcontent['thing11'] = $order['address'];
			
			$tmplcontentnew = [];
			$tmplcontentnew['thing29'] = $order['title'];
			$tmplcontentnew['thing1'] = $express_com;
			$tmplcontentnew['character_string2'] = $express_no;
			$tmplcontentnew['thing9'] = $order['address'];
            \app\commons\Wechat::sendwxtmpl(aid,$order['mid'],'tmpl_orderfahuo',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);

            //短信通知
            $member = Db::name('member')->where('id',$order['mid'])->find();
            if($member['tel']){
                $tel = $member['tel'];
            }else{
                $tel = $order['tel'];
            }
            $rs = \app\commons\Sms::send(aid,$tel,'tmpl_orderfahuo',['ordernum'=>$order['ordernum'],'express_com'=>$express_com,'express_no'=>$express_no]);
            $successnum++;
        }
        \app\commons\System::plog('商城订单批量发货');
        return json(['status'=>1,'msg'=>'共导入 '.$countnum.' 条数据，成功发货 '.$successnum.' 条，失败 '.$errornum.' 条']);
    }
	//查物流
	public function getExpress(){
		$orderid = input('post.orderid/d');
		$order = Db::name('shop_order')->where('id',$orderid)->where('aid',aid)->find();
		if($order['freight_type'] == '10'){
			$data = Db::name('freight_type10_record')->where('id',$order['express_no'])->find();
			return json(['status'=>1,'data'=>$data]);
		}
		if($order['express_content']) {
		    $expressArr = json_decode($order['express_content'],true);
		    foreach ($expressArr as $order) {
                if($order['express_com'] == '顺丰速运'){
                    $totel = $order['tel'];
                    $order['express_no'] = $order['express_no'].":".substr($totel,-4);
                }
				if($order['express_ogids']){
					$oglist = Db::name('shop_order_goods')->where('aid',aid)->where('id','in',$order['express_ogids'])->select()->toArray();
				}else{
					$oglist = [];
				}
                $list[] = [
                    'express_no' => $order['express_no'],
                    'express_com' => $order['express_com'],
                    'express_data' => \app\commons\Common::getwuliu($order['express_no'],$order['express_com'],$order['express_type'], aid),
					'oglist'=>$oglist,
                ];
            }

        } else {
            if($order['express_com'] == '顺丰速运'){
                $totel = $order['tel'];
                $order['express_no'] = $order['express_no'].":".substr($totel,-4);
            }
			if($order['express_ogids']){
				$oglist = Db::name('shop_order_goods')->where('aid',aid)->where('id','in',$order['express_ogids'])->select()->toArray();
			}else{
				$oglist = [];
			}
            $list[] = [
                'express_no' => $order['express_no'],
                'express_com' => $order['express_com'],
                'express_data' => \app\commons\Common::getwuliu($order['express_no'],$order['express_com'],$order['express_type'], aid),
				'oglist'=>$oglist,
            ];
        }

		return json(['status'=>1,'data'=>$list]);
	}
    //付款审核
    public function payCheck(){
        if(getcustom('pay_transfer')){
            $orderid = input('post.orderid/d');
            $st = input('post.st/d');
            $remark = input('post.remark');
            $order = Db::name('shop_order')->where('id',$orderid)->where('aid',aid)->where('bid',bid)->find();

            if($order['status']!=0){
               return json(['status'=>0,'msg'=>'该订单状态不允许审核付款']);
            }

            if($st==2){
                Db::name('payorder')->where('id',$order['payorderid'])->where('aid',aid)->where('bid',bid)->update(['check_status'=>2,'check_remark'=>$remark]);

                \app\commons\System::plog('商城订单付款审核驳回'.$orderid);
                return json(['status'=>1,'msg'=>'付款已驳回']);
            }elseif($st == 1){

                \app\models\Payorder::payorder($order['payorderid'],t('转账汇款'),5,'');
                Db::name('payorder')->where('id',$order['payorderid'])->where('aid',aid)->where('bid',bid)->update(['check_status'=>1,'check_remark'=>$remark]);

                Db::name('shop_order')->where('id',$orderid)->where('aid',aid)->where('bid',bid)->update(['status'=>1,'paytime' => time()]);

                \app\commons\System::plog('商城订单付款审核通过'.$orderid);
                return json(['status'=>1,'msg'=>'审核通过']);
            }
        }
    }
	//退款审核
	public function refundCheck(){
		$orderid = input('post.orderid/d');
		$st = input('post.st/d');
		$remark = input('post.remark');
		if(bid == 0){
			$order = Db::name('shop_order')->where('id',$orderid)->where('aid',aid)->find();
		}else{
			$order = Db::name('shop_order')->where('id',$orderid)->where('aid',aid)->where('bid',bid)->find();
		}
		if(!$order) return json(['status'=>1,'msg'=>'订单不存在']);
		if($st==2){
			Db::name('shop_order')->where('id',$orderid)->where('aid',aid)->update(['refund_status'=>3,'refund_checkremark'=>$remark]);
			//退款申请驳回通知
			$tmplcontent = [];
			$tmplcontent['first'] = '您的退款申请被商家驳回，可与商家协商沟通。';
			$tmplcontent['remark'] = $remark.'，请点击查看详情~';
			$tmplcontent['orderProductPrice'] = $order['refund_money'].'元';
			$tmplcontent['orderProductName'] = $order['title'];
			$tmplcontent['orderName'] = $order['ordernum'];
            $tmplcontentNew = [];
            $tmplcontentNew['character_string1'] = $order['ordernum'];//订单编号
            $tmplcontentNew['thing2'] = $order['title'];//商品名称
            $tmplcontentNew['amount3'] = $order['refund_money'];//退款金额
			\app\commons\Wechat::sendtmpl(aid,$order['mid'],'tmpl_tuierror',$tmplcontent,m_url('pages/my/usercenter'),$tmplcontentNew);
			//订阅消息
			$tmplcontent = [];
			$tmplcontent['amount3'] = $order['refund_money'];
			$tmplcontent['thing2'] = $order['title'];
			$tmplcontent['character_string1'] = $order['ordernum'];
			
			$tmplcontentnew = [];
			$tmplcontentnew['amount3'] = $order['refund_money'];
			$tmplcontentnew['thing8'] = $order['title'];
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
			if($order['status']!=1 && $order['status']!=2){
				return json(['status'=>0,'msg'=>'该订单状态不允许退款']);
			}
			if($order['refund_money'] > 0){
				$is_refund = 1;
				//分期支付的退款
				if(getcustom('shop_product_fenqi_pay')){
					if($order['is_fenqi'] == 1){
						$rs = \app\commons\Order::fenqi_refund($order,$order['refund_money'], $order['refund_reason']);
						if($rs['status']==0){
							return json(['status'=>0,'msg'=>$rs['msg']]);
						}				
						$is_refund = 0;
					}					
				}
				if($is_refund){
					$params = [];
                    if(getcustom('pay_money_combine')){
                        //如果是组合支付，退款需要判断余额、微信、支付宝退款部分
                        if($order['combine_money']>0 && ($order['combine_wxpay']>0 || $order['combine_alipay']>0)){
                        	//refund_combine 1 走shop_refund_order 退款;2 走shop_order 退款
                            $params = [
                                'refund_combine'=> 2,
                                'refund_order'  => $order
                            ];
                        }
                    }
					$rs = \app\commons\Order::refund($order,$order['refund_money'],$order['refund_reason'],$params);
					if($rs['status']==0){
						return json(['status'=>0,'msg'=>$rs['msg']]);
					}
				}
				
			}



        //  	\app\commons\Aaa::closeOrder(aid,$orderid);
        //  	\app\commons\Aaa::refundOrder(aid,$orderid);
         	\app\commons\Aaa::statusOrder(aid,$orderid,$order);
         	
	
			Db::name('shop_order')->where('id',$orderid)->where('aid',aid)->update(['status'=>4,'refund_status'=>2,'refund_checkremark'=>$remark]);
			Db::name('shop_order_goods')->where('orderid',$orderid)->where('aid',aid)->update(['status'=>4]);

			//积分抵扣的返还
			if($order['scoredkscore'] > 0){
				\app\commons\Member::addscore(aid,$order['mid'],$order['scoredkscore'],'订单退款返还');
			}
			//扣除消费赠送积分
            \app\commons\Member::decscorein(aid,'shop',$order['id'],$order['ordernum'],'订单退款扣除消费赠送');
			if($order['givescore2'] > 0){
                \app\commons\Member::addscore(aid,$order['mid'],-$order['givescore2'],'订单退款扣除');
            }
			//优惠券抵扣的返还
			if($order['coupon_rid']){
				Db::name('coupon_record')->where('aid',aid)->where('mid',$order['mid'])->where('id','in',$order['coupon_rid'])->update(['status'=>0,'usetime'=>'']);
			}

			//元宝返回
            if(getcustom('pay_yuanbao') && $order['is_yuanbao_pay'] ==1 && $order['total_yuanbao']>0){
                \app\commons\Member::addyuanbao(aid,$order['mid'],$order['total_yuanbao'],'订单退款返还');
            }

			if(getcustom('cefang') && aid==2){ //定制1 订单对接 同步到策方
				$order['status'] = 4;
				\app\customs\Cefang::api($order);
			}

			if(getcustom('hmy_yuyue')){
				if($order['sysOrderNo']){
					$rs = \app\customs\Yuyue::refund($order);
				}
			}
            if(getcustom('member_gongxian') && $order['refund_money'] > 0){
                //扣除贡献值
                $admin = Db::name('admin')->where('id',aid)->find();
                $set = Db::name('admin_set')->where('aid',aid)->find();
                $gongxian_days = $set['gongxian_days'];
                //等级设置优先
                $level_gongxian_days = Db::name('member_level')->where('aid',aid)->where('id',$order['mid'])->value('gongxian_days');
                if($level_gongxian_days > 0){
                    $gongxian_days = $level_gongxian_days;
                }
                if($admin['member_gongxian_status'] == 1 && $set['gongxianin_money'] > 0 && $set['gognxianin_value'] > 0 && ((time()-$order['paytime'])/86400 <= $gongxian_days)){
                    $log = Db::name('member_gongxianlog')->where('aid',aid)->where('mid',$order['mid'])->where('channel','shop')->where('orderid',$order['id'])->find();
                    if($log){
                        $givevalue = $log['value']*-1;
                        Db::name('member_gongxianlog')->where('aid',aid)->where('id',$log['id'])->update(['is_expire'=>2]);
                        \app\commons\Member::addgongxian(aid,$order['mid'],$givevalue,'退款扣除'.t('贡献'),'shop_refund',$order['id']);
                    }
                }
            }

            if(getcustom('money_dec')){
				if($order['dec_money']>0){
					\app\commons\Member::addmoney(aid,$order['mid'],$order['dec_money'],t('余额').'抵扣返回，订单号: '.$order['ordernum']);
				}
			}
            if(getcustom('member_goldmoney_silvermoney')){
                //返回银值抵扣
                if($order['silvermoneydec']>0){
                    $res = \app\commons\Member::addsilvermoney(aid,$order['mid'],$order['silvermoneydec'],t('银值').'抵扣返回，订单号: '.$order['ordernum'],$order['ordernum']);
                }
                //返回金值抵扣
                if($order['goldmoneydec']>0){
                    $res = \app\commons\Member::addgoldmoney(aid,$order['mid'],$order['goldmoneydec'],t('金值').'抵扣返回，订单号: '.$order['ordernum'],$order['ordernum']);
                }
            }

			if(getcustom('yx_invite_cashback')){
				//取消邀请返现
				\app\customs\OrderCustom::cancel_invitecashbacklog(aid,$order,'订单退款');
			}
			//退款退还佣金
			if(getcustom('commission_orderrefund_deduct')){
				\app\commons\Fenxiao::refundFenxiao(aid,$order['id'],'shop');
				\app\commons\Order::refundFenhongDeduct($order,'shop');
			}
            if(getcustom('consumer_value_add')){
                //送绿色积分
                if($order['give_green_score2'] > 0){
                    \app\commons\Member::addgreenscore(aid,$order['mid'],-$order['give_green_score2'],'订单退款扣除'.t('绿色积分'),'shop_order',$orderid);
                    \app\commons\Member::addmaximum(aid,$order['mid'],-$order['give_maximum'],'订单退款扣除','shop_order',$orderid);
                }
                //放入奖金池
                if($order['give_bonus_pool2'] > 0){
                    \app\commons\Member::addbonuspool(aid,$order['mid'],-$order['give_bonus_pool2'],'订单退款扣除'.t('奖金池'),'shop_order',$orderid,0,-$order['give_green_score2']);
                }
            }

			//退款成功通知
			$tmplcontent = [];
			$tmplcontent['first'] = '您的订单已经完成退款，¥'.$order['refund_money'].'已经退回您的付款账户，请留意查收。';
			$tmplcontent['remark'] = $remark.'，请点击查看详情~';
			$tmplcontent['orderProductPrice'] = $order['refund_money'].'元';
			$tmplcontent['orderProductName'] = $order['title'];
			$tmplcontent['orderName'] = $order['ordernum'];
            $tmplcontentNew = [];
            $tmplcontentNew['character_string1'] = $order['ordernum'];//订单编号
            $tmplcontentNew['thing2'] = $order['title'];//商品名称
            $tmplcontentNew['amount3'] = $order['refund_money'];//退款金额
			\app\commons\Wechat::sendtmpl(aid,$order['mid'],'tmpl_tuisuccess',$tmplcontent,m_url('pages/my/usercenter'),$tmplcontentNew);
			//订阅消息
			$tmplcontent = [];
			$tmplcontent['amount6'] = $order['refund_money'];
			$tmplcontent['thing3'] = $order['title'];
			$tmplcontent['character_string2'] = $order['ordernum'];
			
			$tmplcontentnew = [];
			$tmplcontentnew['amount3'] = $order['refund_money'];
			$tmplcontentnew['thing6'] = $order['title'];
			$tmplcontentnew['character_string4'] = $order['ordernum'];
			\app\commons\Wechat::sendwxtmpl(aid,$order['mid'],'tmpl_tuisuccess',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);

			//短信通知
			$member = Db::name('member')->where('id',$order['mid'])->find();
			if($member['tel']){
				$tel = $member['tel'];
			}else{
				$tel = $order['tel'];
			}
			$rs = \app\commons\Sms::send(aid,$tel,'tmpl_tuisuccess',['ordernum'=>$order['ordernum'],'money'=>$order['refund_money']]);
			
			\app\commons\System::plog('商城订单退款审核通过并退款'.$orderid);
			return json(['status'=>1,'msg'=>'已退款成功']);
		}
	}
    //退款
    public function refundinit(){
        //查询订单信息
        $detail = Db::name('shop_order')->where('id',input('param.orderid/d'))->where('aid',aid)->find();
        if(!$detail)
            return json(['status'=>0,'msg'=>'订单不存在']);
        $detail['createtime'] = $detail['createtime'] ? date('Y-m-d H:i:s',$detail['createtime']) : '';
        $detail['collect_time'] = $detail['collect_time'] ? date('Y-m-d H:i:s',$detail['collect_time']) : '';
        $detail['paytime'] = $detail['paytime'] ? date('Y-m-d H:i:s',$detail['paytime']) : '';
        $detail['refund_time'] = $detail['refund_time'] ? date('Y-m-d H:i:s',$detail['refund_time']) : '';
        $detail['send_time'] = $detail['send_time'] ? date('Y-m-d H:i:s',$detail['send_time']) : '';

        $refundMoneySum = Db::name('shop_refund_order')->where('orderid',$detail['id'])->where('aid',aid)->whereIn('refund_status',[1,2,4])->sum('refund_money');

        $canRefundNum = 0;
        $totalNum = 0;
        $returnTotalprice = 0;
        $prolist = Db::name('shop_order_goods')->where('orderid',$detail['id'])->select()->toArray();
        foreach ($prolist as $key => $item) {
            $prolist[$key]['canRefundNum'] = $item['num'] - $item['refund_num'];
            $totalNum += $item['num'];
            $canRefundNum += $item['num'] - $item['refund_num'];
//            $returnTotalprice += $item['real_totalprice'] / $item['num'] * ($item['num'] - $item['refund_num']);
        }
		$totalprice = $detail['totalprice'];
		if($detail['balance_price'] > 0 && $detail['balance_pay_status'] == 0){
			$totalprice = $totalprice - $detail['balance_price'];
		}
        if($canRefundNum == $totalNum) {
            $returnTotalprice = $totalprice;
        } else {
            $returnTotalprice = $totalprice - $refundMoneySum;
        }
        //可退款金额=总金额-审核中-已退款
        $detail['canRefundNum'] = $canRefundNum;
        $detail['totalNum'] = $totalNum;
        $detail['returnTotalprice'] = $returnTotalprice;
//        if($canRefundNum == 0) {
//            return $this->json(['status'=>0,'msg'=>'当前订单没有可退款的商品']);
//        }
        //todo 确认收货后的退款

        $rdata = [];
        $rdata['status'] = 1;
        $rdata['detail'] = $detail;
        $rdata['prolist'] = $prolist;

        return json($rdata);
    }
	//退款
	public function refund(){
		$orderid = input('post.orderid/d');
		$reason = input('post.reason');
		if(bid == 0){
			$order = Db::name('shop_order')->where('id',$orderid)->where('aid',aid)->find();
		}else{
			$order = Db::name('shop_order')->where('id',$orderid)->where('aid',aid)->where('bid',bid)->find();
		}
		if(!$order) return json(['status'=>0,'msg'=>'订单不存在']);
		if($order['status']!=1 && $order['status']!=2){
			return json(['status'=>0,'msg'=>'该订单状态不允许退款']);
		}
        $refundingMoney = Db::name('shop_refund_order')->where('orderid',$order['id'])->where('aid',aid)->whereIn('refund_status',[1,4])->sum('refund_money');
		if($refundingMoney) {
            return json(['status'=>0,'msg'=>'请先处理完进行中的退款单']);
        }
        //$refundedMoney = Db::name('shop_refund_order')->where('orderid',$order['id'])->where('aid',aid)->where('bid',bid)->where('refund_status',2)->sum('refund_money');
        //$refund_money -= $refundedMoney;

        try {
            Db::startTrans();
            //新退款 202108
            $post = input('post.');
            $orderid = intval($post['orderid']);
            $money = floatval($post['money']);
            $refundNum = $post['refundNum'];
            $order = Db::name('shop_order')->where('aid', aid)->where('id', $orderid)->find();
            if (!$order || ($order['status'] != 1 && $order['status'] != 2)) {
                return json(['status' => 0, 'msg' => '订单状态不符合退款要求']);
            }
            if ($money < 0 || $money > $order['totalprice']) {
                return json(['status' => 0, 'msg' => '退款金额有误']);
            }
            if (empty($refundNum)) {
                return json(['status' => 0, 'msg' => '请选择退款的商品']);
            }

            $totalRefundNum = 0;
            $returnTotalprice = 0;
            $prolist = Db::name('shop_order_goods')->where('orderid', $orderid)->select();
            $newKey = 'id';
            $prolist = $prolist->dictionary(null, $newKey);
            $ogids = array_keys($prolist);

            $refundMoneySum = Db::name('shop_refund_order')->where('orderid', $orderid)->where('aid', aid)->whereIn('refund_status', [1, 2, 4])->sum('refund_money');

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
                    return json(['status' => 0, 'msg' => '退款商品不存在']);
                }
                if ($item['num'] > $prolist[$item['ogid']]['num'] - $prolist[$item['ogid']]['refund_num']) {
                    return json(['status' => 0, 'msg' => $prolist[$item['ogid']]['name'] . '退款数量超出范围']);
                }
                $totalRefundNum += $item['num'];
                //            $returnTotalprice += $prolist[$item['ogid']]['real_totalprice'] / $prolist[$item['ogid']]['num'] * $item['num'];
            }
            if ($totalRefundNum == 0) {
                return json(['status' => 0, 'msg' => '请选择退款的商品']);
            }
			$totalprice = $order['totalprice'];
			if($order['balance_price'] > 0 && $order['balance_pay_status'] == 0){
				$totalprice = $totalprice - $order['balance_price'];
			}
            if ($canRefundNum == $totalNum && $totalNum == $totalRefundNum) {
                $canRefundTotalprice = $totalprice;
            } else {
                $canRefundTotalprice = $totalprice - $refundMoneySum;
            }

            if ($money > $canRefundTotalprice) {
                return json(['status' => 0, 'msg' => '退款金额超出范围']);
            }

            $data = [
                'aid' => $order['aid'],
                'bid' => $order['bid'],
                'mdid' => $order['mdid'],
                'mid' => $order['mid'],
                'orderid' => $order['id'],
                'ordernum' => $order['ordernum'],
                'refund_type' => 'refund',
                'refund_ordernum' => '' . date('ymdHis') . rand(100000, 999999),
                'refund_money' => $money,
                'refund_reason' => '后台退款：' . $post['reason'],
                'refund_pics' => '',
                'createtime' => time(),
                'refund_time' => time(),
                'refund_status' => 2,
                'platform' => platform,
            ];

            if(getcustom('pay_money_combine')){
                //处理余额组合支付退款
                $res = \app\customs\OrderCustom::deal_refund_combine($order,$money);
                if($res['status'] == 1){
                    $data['refund_combine_money']  = $res['refund_combine_money'];//退余额部分
                    $data['refund_combine_wxpay']  = $res['refund_combine_wxpay'];//退微信部分
                    $data['refund_combine_alipay'] = $res['refund_combine_alipay'];//退支付宝部分
                }else{
                    return $this->json($res);
                }
            }
            if(getcustom('erp_wangdiantong')) {
                $data['wdt_status'] = $order['wdt_status'];
            }

            $refund_id = Db::name('shop_refund_order')->insertGetId($data);

            if ($order['fromwxvideo'] == 1) {
                \app\commons\Wxvideo::aftersaleadd($order['id'], $refund_id);
            }
            if ($data['refund_money'] > 0) {
				$is_refund = 1;
				if(getcustom('shop_product_fenqi_pay')){
					if($order['is_fenqi'] == 1){
						$rs = \app\commons\Order::fenqi_refund($order,$data['refund_money'], $reason);
						if($rs['status']==0){
							return json(['status'=>0,'msg'=>$rs['msg']]);
						}
						$is_refund = 0;
					}
					
				}
				if($is_refund){
					$params = [];
                    if(getcustom('pay_money_combine')){
                        //如果是组合支付，退款需要判断余额、微信、支付宝退款部分
                        if($order['combine_money']>0 && ($order['combine_wxpay']>0 || $order['combine_alipay']>0)){
                        	//refund_combine 1 走shop_refund_order 退款;2 走shop_order 退款
                            $params = [
                                'refund_combine'=> 1,
                                'refund_order'  => $data
                            ];
                        }
                    }
					$rs = \app\commons\Order::refund($order, $data['refund_money'], $reason,$params);
					if ($rs['status'] == 0) {
						if($order['balance_price'] > 0){
							$order2 = $order;
							$order2['totalprice'] = $order2['totalprice'] - $order2['balance_price'];
							$order2['ordernum'] = $order2['ordernum'].'_0';
							$rs = \app\commons\Order::refund($order2,$data['refund_money'],$reason);
							if($rs['status']==0){
								Db::name('shop_refund_order')->where('id', $refund_id)->delete();
								return json(['status'=>0,'msg'=>$rs['msg']]);
							}
						}else{
							Db::name('shop_refund_order')->where('id', $refund_id)->delete();
							return json(['status'=>0,'msg'=>$rs['msg']]);
						}
					}
				}
                
            }

            foreach ($refundNum as $item) {
                if ($item['num'] < 1) continue;
                $od = [
                    'aid' => $order['aid'],
                    'bid' => $order['bid'],
                    'mid' => $order['mid'],
                    'orderid' => $order['id'],
                    'ordernum' => $order['ordernum'],
                    'refund_orderid' => $refund_id,
                    'refund_ordernum' => $data['refund_ordernum'],
                    'refund_num' => $item['num'],
                    'refund_money' => $item['num'] * $prolist[$item['ogid']]['real_totalprice'] / $prolist[$item['ogid']]['num'],
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
                if(getcustom('erp_wangdiantong')) {
                    $od['wdt_status'] = $prolist[$item['ogid']]['wdt_status'];
                }
                Db::name('shop_refund_order_goods')->insertGetId($od);
                Db::name('shop_order_goods')->where('aid', aid)->where('id', $item['ogid'])->inc('refund_num', $item['num'])->update();
                if(getcustom('consumer_value_add')){
                    $goods_info =  Db::name('shop_order_goods')->where('aid', aid)->where('id', $item['ogid'])->find();
                    //扣除绿色积分
                    if($goods_info['give_green_score2'] > 0){
                        \app\commons\Member::addgreenscore(aid,$order['mid'],-bcmul($goods_info['give_green_score2'],$item['num'],2),'订单退款扣除'.t('绿色积分'),'shop_order',$orderid);
                        \app\commons\Member::addmaximum(aid,$order['mid'],-$goods_info['give_maximum'],'订单退款扣除','shop_order',$orderid);
                    }
                    //扣除奖金池
                    if($goods_info['give_bonus_pool2'] > 0){
                        \app\commons\Member::addbonuspool(aid,$order['mid'],-bcmul($goods_info['give_bonus_pool2'],$item['num'],2),'订单退款扣除'.t('奖金池'),'shop_order',$orderid,0,-bcmul($goods_info['give_green_score2'],$item['num'],2));
                    }
                }
            }

            //        $order_goods = Db::name('shop_order_goods')->where('orderid',$orderid)->where('aid',aid)->where('bid',bid)->fieldRaw('ggid,proid,num,refund_num, num-refund_num as true_num')->select()->toArray();
            //		Db::name('shop_order')->where('id',$orderid)->where('aid',aid)->where('bid',bid)->update(['status'=>4,'refund_status'=>2,'refund_money'=>$refund_money,'refund_reason'=>$reason]);
            //		Db::name('shop_order_goods')->where('orderid',$orderid)->where('aid',aid)->where('bid',bid)->update(['status'=>4,'refund_num' => Db::raw('num')]);

            //恢复库存 删除销量
            foreach ($refundNum as $item) {
                Db::name('shop_guige')->where('aid', aid)->where('id', $prolist[$item['ogid']]['ggid'])->update(['stock' => Db::raw("stock+" . $item['num']), 'sales' => Db::raw("sales-" . $item['num'])]);
                Db::name('shop_product')->where('aid', aid)->where('id', $prolist[$item['ogid']]['proid'])->update(['stock' => Db::raw("stock+" . $item['num']), 'sales' => Db::raw("sales-" . $item['num'])]);

                if (getcustom('guige_split')) {
                    \app\models\ShopProduct::addlinkstock($prolist[$item['ogid']]['proid'], $prolist[$item['ogid']]['ggid'], $item['num']);
                }
                if(getcustom('ciruikang_fenxiao')){
                    //是否开启了商城商品需上级购买足量
                    $og = $prolist[$item['ogid']];
                    if($og){
                    	$deal_ogstock2 = \app\customs\CiruikangCustom::deal_ogstock2($order,$og,$item['num'],'下级订单退款');
                    }
                }
            }

            //整单全部退时 返还积分和优惠券
            if ($totalRefundNum == $canRefundNum && $money == $canRefundTotalprice) {
                
        //  	\app\commons\Aaa::closeOrder(aid,$orderid);
        //  	\app\commons\Aaa::refundOrder(aid,$orderid);
         	\app\commons\Aaa::statusOrder(aid,$orderid,$order);
         	
                Db::name('shop_order')->where('id', $order['id'])->where('aid', aid)->update(['status' => 4, 'refund_status' => 2, 'refund_money' => $refundMoneySum + $data['refund_money']]);
                Db::name('shop_order_goods')->where('orderid', $order['id'])->where('aid', aid)->update(['status' => 4]);
                //积分抵扣的返还
                if ($order['scoredkscore'] > 0) {
                    \app\commons\Member::addscore(aid, $order['mid'], $order['scoredkscore'], '订单退款返还');
                }
                if ($order['givescore2'] > 0) {
                    \app\commons\Member::addscore(aid, $order['mid'], -$order['givescore2'], '订单退款扣除');
                }
                //扣除消费赠送积分
            	\app\commons\Member::decscorein(aid,'shop',$order['id'],$order['ordernum'],'订单退款扣除消费赠送');
                //查询后台是否开启退还已使用的优惠券
                $return_coupon = Db::name('shop_sysset')->where('aid',aid)->value('return_coupon');
                //优惠券抵扣的返还
                if ($return_coupon && $order['coupon_rid']) {
                    Db::name('coupon_record')->where('aid', aid)->where('mid', $order['mid'])->where('id', 'in', $order['coupon_rid'])->update(['status' => 0, 'usetime' => '']);
                }
                //元宝返回
                if (getcustom('pay_yuanbao') && $order['is_yuanbao_pay'] == 1 && $order['total_yuanbao'] > 0) {
                    \app\commons\Member::addyuanbao(aid, $order['mid'], $order['total_yuanbao'], '订单退款返还');
                }
                if ($order['givescore2'] > 0) {
                    \app\commons\Member::addscore(aid, $order['mid'], -$order['givescore2'], '订单退款扣除');
                }
                if(getcustom('money_dec')){
					if($order['dec_money']>0){
						\app\commons\Member::addmoney(aid,$order['mid'],$order['dec_money'],t('余额').'抵扣返回，订单号: '.$order['ordernum']);
					}
				}
				if(getcustom('yx_invite_cashback')){
					//取消邀请返现
					\app\customs\OrderCustom::cancel_invitecashbacklog(aid,$order,'订单退款');
				}
				//退款退还佣金
				if(getcustom('commission_orderrefund_deduct')){
					\app\commons\Fenxiao::refundFenxiao(aid,$order['id'],'shop');
					\app\commons\Order::refundFenhongDeduct($order,'shop');
				}
                if(getcustom('member_goldmoney_silvermoney')){
                    //返回银值抵扣
                    if($order['silvermoneydec']>0){
                        $res = \app\commons\Member::addsilvermoney(aid,$order['mid'],$order['silvermoneydec'],t('银值').'抵扣返回，订单号: '.$order['ordernum'],$order['ordernum']);
                    }
                    //返回金值抵扣
                    if($order['goldmoneydec']>0){
                        $res = \app\commons\Member::addgoldmoney(aid,$order['mid'],$order['goldmoneydec'],t('金值').'抵扣返回，订单号: '.$order['ordernum'],$order['ordernum']);
                    }
                }
            } else {
                //部分退款
                Db::name('shop_order')->where('id', $order['id'])->where('aid', aid)->inc('refund_money', $data['refund_money'])->update(['refund_status' => 2]);
                //重新计算佣金
                $prolist = Db::name('shop_order_goods')->where('orderid', $order['id'])->where('aid', aid)->select();
                $reog = Db::name('shop_refund_order_goods')->where('refund_orderid', $refund_id)->select();
                \app\commons\Order::updateCommission($prolist, $reog);

                //判断当前订单是否全部退款 退款关闭
                $total_num = Db::name('shop_order_goods')->where('orderid', $order['id'])->where('aid', aid)->field("SUM(`num`) as total_num,SUM(`refund_num`) as total_refund_num")->find();
                if ($total_num['total_num'] == $total_num['total_refund_num']) {
                    Db::name('shop_order')->where('id', $order['id'])->where('aid', aid)->update(['status' => 4, 'refund_status' => 2]);
                    Db::name('shop_order_goods')->where('orderid', $order['id'])->where('aid', aid)->update(['status' => 4]);
                }
            }

            if(getcustom('yx_mangfan')){
                \app\customs\Mangfan::delAndCreate(aid, $order['mid'], $order['id'], $order['ordernum']);
            }

            //		//积分抵扣的返还
            //		if($order['scoredkscore'] > 0){
            //			\app\commons\Member::addscore(aid,$order['mid'],$order['scoredkscore'],'订单退款返还');
            //		}
            //		//优惠券抵扣的返还
            //		if($order['coupon_rid'] > 0){
            //			Db::name('coupon_record')->where('aid',aid)->where(['mid'=>$order['mid'],'id'=>$order['coupon_rid']])->update(['status'=>0,'usetime'=>'']);
            //		}

            if (getcustom('cefang') && aid == 2) { //定制1 订单对接 同步到策方
                $order['status'] = 4;
                \app\customs\Cefang::api($order);
            }
            if(getcustom('member_gongxian')){
                //扣除贡献值
                $admin = Db::name('admin')->where('id',aid)->find();
                $set = Db::name('admin_set')->where('aid',aid)->find();
                $gongxian_days = $set['gongxian_days'];
                //等级设置优先
                $level_gongxian_days = Db::name('member_level')->where('aid',aid)->where('id',$order['mid'])->value('gongxian_days');
                if($level_gongxian_days > 0){
                    $gongxian_days = $level_gongxian_days;
                }
                if($admin['member_gongxian_status'] == 1 && $set['gongxianin_money'] > 0 && $set['gognxianin_value'] > 0 && ((time()-$order['paytime'])/86400 <= $gongxian_days)){
                    $log = Db::name('member_gongxianlog')->where('aid',aid)->where('mid',$order['mid'])->where('channel','shop')->where('orderid',$order['id'])->find();
                    if($log){
                        $givevalue = $log['value']*-1;
                        Db::name('member_gongxianlog')->where('aid',aid)->where('id',$log['id'])->update(['is_expire'=>2]);
                        \app\commons\Member::addgongxian(aid,$order['mid'],$givevalue,'退款扣除'.t('贡献'),'shop_refund',$order['id']);
                    }
                }
            }
            if(getcustom('product_bonus_pool')){
                foreach ($prolist as $key=>$val){
                    $recordlist = Db::name('member_bonus_pool_record')->where('ogid',$val['id'])->select()->toArray();
                    foreach ($recordlist as $rkey=>$rval){
                        //修改记录
                        Db::name('member_bonus_pool_record')->where('id',$rval['id'])->update(['status' => 2]);
                        //修改奖金池
                        Db::name('bonus_pool')->where('id',$rval['bpid'])->update(['status' => 0,'mid' => 0]);
                    }
                }
            }
            if(getcustom('erp_wangdiantong')){
                $reog = Db::name('shop_refund_order_goods')->where('refund_orderid', $refund_id)->where('wdt_status',1)->select()->toArray();
                if($reog) {
                    $c = new \app\customs\Wdt(aid, bid);
                    $data['id'] = $refund_id;
                    $c->orderRefund($order, $data, $reog);
                }
            }
            Db::commit();
        } catch (\Exception $e) {
            Log::write([
                'file' => __FILE__ . ' L' . __LINE__,
                'function' => __FUNCTION__,
                'error' => $e->getMessage(),
            ]);
            Db::rollback();
            return json(['status'=>0,'msg'=>'提交失败,请重试']);
        }

        $refund_money = $data['refund_money'];
		//退款成功通知
		$tmplcontent = [];
		$tmplcontent['first'] = '您的订单已经完成退款，¥'.$refund_money.'已经退回您的付款账户，请留意查收。';
		$tmplcontent['remark'] = $reason.'，请点击查看详情~';
		$tmplcontent['orderProductPrice'] = $refund_money.'元';
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
		$tmplcontentnew['amount3'] = $refund_money;
		$tmplcontentnew['thing6'] = $order['title'];
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


	//核销并确认收货
    function orderHexiao(){
        $post = input('post.');
        $orderid = intval($post['orderid']);
		if(bid == 0){
			$order = Db::name('shop_order')->where('aid',aid)->where('id',$orderid)->find();
		}else{
			$order = Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->find();
		}
        if(!$order || !in_array($order['status'], [1,2]) || $order['freight_type'] != 1){
            return json(['status'=>0,'msg'=>'订单状态不符合核销收货要求']);
        }

        $refundOrder = Db::name('shop_refund_order')->where('refund_status','in',[1,4])->where('aid',aid)->where('orderid',$orderid)->count();
        if($refundOrder){
            return json(['status'=>0,'msg'=>'有正在进行的退款，无法核销']);
        }
        try {
            Db::startTrans();

            $data = array();
            $data['aid'] = aid;
            $data['bid'] = $order['bid'];
            $data['uid'] = $this->uid;
            $data['mid'] = $order['mid'];
            $data['orderid'] = $order['id'];
            $data['ordernum'] = $order['ordernum'];
            $data['title'] = $order['title'];
            $data['type'] = 'shop';
            $data['createtime'] = time();
            $data['remark'] = '核销员['.$this->user['un'].']核销';
            $data['mdid']   = empty($this->user['mdid'])?0:$this->user['mdid'];
            Db::name('hexiao_order')->insert($data);

            $rs = \app\commons\Order::collect($order, 'shop', $this->user['mid']);
            if($rs['status']==0) return $rs;
            Db::name('shop_order')->where('aid',aid)->where('id',$orderid)->update(['status'=>3,'collect_time'=>time()]);
            Db::name('shop_order_goods')->where('aid',aid)->where('orderid',$orderid)->update(['status'=>3,'endtime'=>time()]);
            if(getcustom('ciruikang_fenxiao')){
                //一次购买升级
                \app\commons\Member::uplv(aid,$order['mid'],'shop',['onebuy'=>1,'onebuy_orderid'=>$order['id']]);
            }else{
            	\app\commons\Member::uplv(aid,$order['mid']);
            }

            if(getcustom('member_shougou_parentreward')){
                //首购解锁
                Db::name('member_commission_record')->where('orderid',$order['id'])->where('type','shop')->where('status',0)->where('islock',1)->where('aid',$order['aid'])->where('remark','like','%首购奖励')->update(['islock'=>0]);
            }

            //发货信息录入 微信小程序+微信支付
            if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
                \app\commons\Order::wxShipping(aid,$order);
            }
            Db::commit();
            if(getcustom('shop_zthx_backmoney')){
	            if($order['freight_type'] == 1){
	            	$prolist = Db::name('shop_order_goods')->where(['orderid'=>$order['id']])->field('id,market_price,num')->select()->toArray();
	                $levelid = Db::name('member')->where('id',$order['mid'])->value('levelid');
	                if(!empty($prolist) && !empty($levelid) && $levelid>0){
	                    //自提核销返现
	                    $zthx_backmoney = Db::name('member_level')->where('id',$levelid)->where('aid',aid)->value('zthx_backmoney');
	                    if($zthx_backmoney && $zthx_backmoney>0){
	                        $back_money = 0;
                            foreach($prolist as $pv){
                                $back_money += $pv['market_price']*$pv['num'];
                            }
                            unset($pv);
	                        $back_money = $back_money*$zthx_backmoney/100;
	                        $back_money = round($back_money,2);
	                        if($back_money>0){
	                            \app\commons\Member::addmoney(aid,$order['mid'],$back_money,'自提核销返回，订单号: '.$order['ordernum']);
	                        }
	                    }
	                }
	            }
	        }
	        if(getcustom('mendian_hexiao_givemoney') && $order['mdid']){
                $mendian = Db::name('mendian')->where('aid',aid)->where('id',$order['mdid'])->find();
                if($mendian){
                    $givemoney = 0;
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
            \app\commons\System::plog('商城订单核销确认收货'.$orderid);
            if(getcustom('hexiao_auto_wifiprint')){
                \app\commons\Wifiprint::print($order['aid'],'shop',$order['id'],0,-1,$order['bid'],'shop',-1,['opttype' => 'hexiao']);
            }
            return json(['status'=>1,'msg'=>'核销成功']);
        } catch (\Exception $e) {
            Log::write([
                'file' => __FILE__ . ' L' . __LINE__,
                'function' => __FUNCTION__,
                'error' => $e->getMessage(),
            ]);
            Db::rollback();
            return json(['status'=>0,'msg'=>'系统繁忙','error'=>$e->getMessage()]);
        }
    }
	function orderCollect(){ //确认收货
		$post = input('post.');
		$orderid = intval($post['orderid']);
		$order = Db::name('shop_order')->where('aid',aid)->where('id',$orderid)->find();
		if(bid != 0 && $order['paytypeid'] != 4){
			return json(['status'=>0,'msg'=>'无操作权限']);
		}
		if(!$order || ($order['status']!=2)){
			return json(['status'=>0,'msg'=>'订单状态不符合收货要求']);
		}

        $refundOrder = Db::name('shop_refund_order')->where('refund_status','in',[1,4])->where('aid',aid)->where('orderid',$orderid)->count();
        if($refundOrder){
            return json(['status'=>0,'msg'=>'有正在进行的退款，无法确认收货']);
        }
        try {
            Db::startTrans();
            $rs = \app\commons\Order::collect($order, 'shop', $this->user['mid']);
            if($rs['status']==0) return json($rs);
            Db::name('shop_order')->where('aid',aid)->where('id',$orderid)->update(['status'=>3,'collect_time'=>time()]);
            Db::name('shop_order_goods')->where('aid',aid)->where('orderid',$orderid)->update(['status'=>3,'endtime'=>time()]);

            if(getcustom('ciruikang_fenxiao')){
                //一次购买升级
                \app\commons\Member::uplv(aid,$order['mid'],'shop',['onebuy'=>1,'onebuy_orderid'=>$order['id']]);
            }else{
                \app\commons\Member::uplv(aid,$order['mid']);
            }

            if(getcustom('member_shougou_parentreward')){
                //首购解锁
                Db::name('member_commission_record')->where('orderid',$order['id'])->where('type','shop')->where('status',0)->where('islock',1)->where('aid',$order['aid'])->where('remark','like','%首购奖励')->update(['islock'=>1]);
            }

            \app\commons\System::plog('商城订单确认收货'.$orderid);
            Db::commit();

            return json(['status'=>1,'msg'=>'确认收货成功']);
        } catch (\Exception $e) {
            Log::write([
                'file' => __FILE__ . ' L' . __LINE__,
                'function' => __FUNCTION__,
                'error' => $e->getMessage(),
            ]);
            Db::rollback();
            return json(['status'=>0,'msg'=>'系统繁忙','error'=>$e->getMessage()]);
        }
	}
	//打印小票
	public function wifiprint(){
		$id = input('post.id/d');
		$rs = \app\commons\Wifiprint::print(aid,'shop',$id,0);
		return json($rs);
	}
	//删除
	public function del(){
		if(input('post.id')){
			$ids = [input('post.id/d')];
		}else{
			$ids = input('post.ids/a');
		}
		foreach($ids as $id){
			if(bid == 0){
				Db::name('shop_order')->where('aid',aid)->where('id',$id)->delete();
				Db::name('shop_order_goods')->where('aid',aid)->where('orderid',$id)->delete();

				Db::name('shop_refund_order')->where('aid',aid)->where('orderid',$id)->delete();
				Db::name('shop_refund_order_goods')->where('aid',aid)->where('orderid',$id)->delete();
			}else{
				Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('id',$id)->delete();
				Db::name('shop_order_goods')->where('aid',aid)->where('bid',bid)->where('orderid',$id)->delete();

				Db::name('shop_refund_order')->where('aid',aid)->where('bid',bid)->where('orderid',$id)->delete();
				Db::name('shop_refund_order_goods')->where('aid',aid)->where('bid',bid)->where('orderid',$id)->delete();
			}
			Db::name('invoice')->where('aid',aid)->where('bid',bid)->where('order_type','shop')->where('orderid',$id)->delete();
			\app\commons\System::plog('商城订单删除'.$id);
		}
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	//编辑
	public function edit(){
		$orderid = input('param.id/d');

		$cwhere = [];
		$cwhere[] = ['aid','=',aid];
		if(bid){
			$cwhere[] = ['bid','=',bid];
		}
		$info = Db::name('shop_order')->where($cwhere)->where('id',$orderid)->find();
		if(!$info) return json(['status'=>0,'msg'=>'订单不存在']);
		$order_goods = Db::name('shop_order_goods')->where($cwhere)->where('orderid',$orderid)->select()->toArray();
		foreach($order_goods as $k=>$v){
			$order_goods[$k]['lvprice'] = Db::name('shop_product')->where('id',$v['proid'])->value('lvprice'); //是否开启会员价
		}
		$member = Db::name('member')->where('id',$info['mid'])->find();
		$userlevel = Db::name('member_level')->where('aid',aid)->where('id',$member['levelid'])->find();
		if($userlevel && $userlevel['discount']>0 && $userlevel['discount']<10){
			$discount = $userlevel['discount']*0.1; //会员折扣
		}else{
			$discount = 1;
		}
		
		
		
		if ($info['area2']) {
		    $areaArr = explode(',',$info['area2']);
		    $info['province'] = $areaArr[0];
		    $info['city'] = $areaArr[1];
		    $info['district'] = $areaArr[2];
		}
		
		
		
		
		
		
        $price_update_status = $info['status'] == 0 ? 1 : 0;
        if(getcustom('order_update_price_anystatus')){
            $price_update_status = 1;
        }

        $calType = 0;//计算类型
        if(getcustom('product_weight') && $info['product_type']==2){
            //计重商品，价格为单价/kg,总价为单价*重量
            $calType = 2;
        }
		if(request()->isAjax()){
			if(getcustom('supply_zhenxin')){
	            if($info['issource'] == 1 && $info['source'] == 'supply_zhenxin'){
	                return $this->json(['status'=>0,'msg'=>'甄新汇选订单不支持修改']);
	            }
	        }
	       
			$postinfo = input('post.info/a');
			$product_price = $postinfo['product_price'];

            if($price_update_status){
                Db::name('shop_order')->where('id',$orderid)->update($postinfo);
            }else{
                $update_data = [
                    "linkman"=>$postinfo['linkman'],
                    "tel"=>$postinfo['tel'],
                    "area"=>$postinfo['area'],
                    "address"=>$postinfo['address'],
                    "freight_price"=>$postinfo['freight_price']
                ];
                if ($postinfo['province'] && $postinfo['city'] && $postinfo['district']) {
                    $update_data['area2'] = $postinfo['province'].','.$postinfo['city'].','.$postinfo['district'];
                }
                Db::name('shop_order')->where('id',$orderid)->update($update_data);
                if($info['so_id']){
    	            $order = Db::name('shop_order')->where('aid',aid)->where('id',$orderid)->find();
	              	$res = \app\commons\Aaa::editOrder(aid,$order);
	              	if ($res['status']!=1) {
	              	    return json(['status'=>0,'msg'=>$res['msg']]);
	              	}
    	        }
                \app\commons\System::plog('商城订单编辑'.$orderid);
                return json(['status'=>1,'msg'=>'修改成功']);
            }
            $order = Db::name('shop_order')->where('id',$orderid)->find();
            $sysset = Db::name('admin_set')->where('aid',aid)->find();
			$mid = $order['mid'];
			//是否是复购
			$hasordergoods = Db::name('shop_order_goods')->where('aid',aid)->where('mid',$mid)->where('createtime','<',$order['createtime'])->where('status','in','1,2,3')->find();
			if($hasordergoods){
				$isfg = 1;
			}else{
				$isfg = 0;
			}
			$goods_id = input('post.goods_id/a');
			$goods_ggname = input('post.goods_ggname/a');
			$goods_sell_price = input('post.goods_sell_price/a');
			$goods_num = input('post.goods_num/a');
			$goods_weight = input('post.goods_weight/a',[]);
			foreach($goods_id as $k=>$ogid){
				$oginfo = Db::name('shop_order_goods')->where('id',$ogid)->find();
				$ogdata = [];
				$ogdata['ggname'] = $goods_ggname[$k];
				$ogdata['sell_price'] = $goods_sell_price[$k];
                if($calType==2){
                    $num = 1;
                    $gweight = $goods_weight[$k]*1000;//化成kg
                    $ogdata['num'] = 1;
                    $ogdata['real_sell_price'] = $goods_sell_price[$k];
                    $ogdata['total_weight'] = $gweight;//化成kg
                    $ogdata['real_total_weight'] = $gweight;//化成kg
                    $ogdata['totalprice'] = round($ogdata['sell_price'] * $goods_weight[$k],2);
                    $ogdata['real_totalprice'] = $ogdata['totalprice'];
                }else{
                    $num = $goods_num[$k];
                    $ogdata['num'] = $goods_num[$k];
                    $ogdata['totalprice'] = $ogdata['sell_price'] * $ogdata['num'];
                }
				$product = Db::name('shop_product')->where('id',$oginfo['proid'])->find();
				$og_totalprice = $ogdata['totalprice'];
				if($product['balance'] > 0){
					$og_totalprice = $og_totalprice * (1 - $product['balance']*0.01);
				}

                $allproduct_price = $product_price;
                $og_leveldk_money = 0;
                $og_coupon_money = 0;
                $og_scoredk_money = 0;
                $og_manjian_money = 0;
                if(getcustom('money_dec')){
                    $og_dec_money  = 0 ;//余额抵扣比例
                }
                if($allproduct_price > 0 && $og_totalprice > 0){
                    if($order['leveldk_money']){
                        $og_leveldk_money = $og_totalprice / $allproduct_price * $order['leveldk_money'];
                    }
                    if($order['coupon_money']){
                        $og_coupon_money = $og_totalprice / $allproduct_price * $order['coupon_money'];
                    }
                    if($order['scoredk_money']){
                        $og_scoredk_money = $og_totalprice / $allproduct_price * $order['scoredk_money'];
                    }
                    if($order['manjian_money']){
                        $og_manjian_money = $og_totalprice / $allproduct_price * $order['manjian_money'];
                    }
                    if(getcustom('money_dec')){
                        if($order['dec_money']){
                            $og_dec_money = $og_totalprice / $allproduct_price * $order['dec_money'];
                        }
                    }
                }
                $ogdata['scoredk_money'] = $og_scoredk_money;
                $ogdata['leveldk_money'] = $og_leveldk_money;
                $ogdata['manjian_money'] = $og_manjian_money;
                $ogdata['coupon_money']  = $og_coupon_money;
                if(getcustom('money_dec')){
                    $ogdata['dec_money'] = $og_dec_money;//余额抵扣比例
                }

                if(bid && bid>0){
	                $store_info = Db::name('business')->where('aid',aid)->where('id',bid)->find();
                }else{
	                $store_info = Db::name('admin_set')->where('aid',aid)->find();
	            }

                if($product['bid'] > 0) {
                    $totalprice_business = $og_totalprice - $og_manjian_money - $og_coupon_money;
                    //商品独立费率
                    if($product['feepercent'] != '' && $product['feepercent'] != null && $product['feepercent'] >= 0) {
                        $ogdata['business_total_money'] = $totalprice_business * (100-$product['feepercent']) * 0.01;
                        if(getcustom('business_deduct_cost')){
                        	if($store_info && $store_info['deduct_cost'] == 1 && $oginfo['cost_price']>0){
                        		if($oginfo['cost_price']<=$ogdata['sell_price']){
									$all_cost_price = $oginfo['cost_price'];
								}else{
									$all_cost_price = $ogdata['sell_price'];
								}
	                        	//扣除成本
			                	$ogdata['business_total_money'] = $totalprice_business - ($totalprice_business-$all_cost_price)*$product['feepercent']/100;
			                }
		                }
						if(getcustom('business_fee_type')){
							$bset = Db::name('business_sysset')->where('aid',aid)->find();
							if($bset['business_fee_type'] == 1){
								$platformMoney = $totalprice_business * $product['feepercent'] * 0.01;
								$ogdata['business_total_money'] = $totalprice_business - $platformMoney;
							}elseif($bset['business_fee_type'] == 2){
								$platformMoney = $oginfo['cost_price'] * $product['feepercent'] * 0.01;
								$ogdata['business_total_money'] = $totalprice_business - $platformMoney;
							}
						}
                    } else {
                        //商户费率
                        $ogdata['business_total_money'] = $totalprice_business * (100-$store_info['feepercent']) * 0.01;
                        if(getcustom('business_deduct_cost')){
                        	if($store_info && $store_info['deduct_cost'] == 1 && $oginfo['cost_price']>0){
                        		if($oginfo['cost_price']<=$ogdata['sell_price']){
									$all_cost_price = $oginfo['cost_price'];
								}else{
									$all_cost_price = $ogdata['sell_price'];
								}
	                        	//扣除成本
			                	$ogdata['business_total_money'] = $totalprice_business - ($totalprice_business-$all_cost_price)*$store_info['feepercent']/100;
			                }
		                }
						if(getcustom('business_fee_type')){
							$bset = Db::name('business_sysset')->where('aid',aid)->find();
							if($bset['business_fee_type'] == 1){
								$platformMoney = $totalprice_business * $store_info['feepercent'] * 0.01;
								$ogdata['business_total_money'] = $totalprice_business - $platformMoney;
							}elseif($bset['business_fee_type'] == 2){
								$platformMoney = $oginfo['cost_price'] * $store_info['feepercent'] * 0.01;
								$ogdata['business_total_money'] = $totalprice_business - $platformMoney;
							}
						}
                    }
                }

				//计算商品实际金额  商品金额 - 会员折扣 - 积分抵扣 - 满减抵扣 - 优惠券抵扣
				if($sysset['fxjiesuantype'] == 1 || $sysset['fxjiesuantype'] == 2){
					$og_totalprice = $og_totalprice - $og_leveldk_money - $og_scoredk_money - $og_manjian_money;
					if($couponrecord['type']!=4) {//运费抵扣券
						$og_totalprice -= $og_coupon_money;
					}
					$og_totalprice = round($og_totalprice,2);
					if($og_totalprice < 0) $og_totalprice = 0;
				}
				if(getcustom('money_dec')){
                    $og_totalprice -= $og_dec_money;//余额抵扣比例
                }
				$ogdata['real_totalprice'] = $og_totalprice; //实际商品销售金额
                $guige = Db::name('shop_guige')->where('id',$oginfo['ggid'])->lock(true)->find();
				//计算佣金的商品金额
				$commission_totalprice = $ogdata['totalprice'];
				if($sysset['fxjiesuantype']==1){ //按成交价格
					$commission_totalprice = $ogdata['real_totalprice'];
					if($commission_totalprice < 0) $commission_totalprice = 0;
				}
				$commission_totalpriceCache = $commission_totalprice;
				if($sysset['fxjiesuantype']==2){ //按销售利润
					$commission_totalprice = $ogdata['real_totalprice'] - $guige['cost_price'] * $num;
					if($commission_totalprice < 0) $commission_totalprice = 0;
				}
                if(getcustom('pay_yuanbao')){
                    $ogdata['yuanbao']       = $product['yuanbao'];
                    $ogdata['total_yuanbao'] = $num*$product['yuanbao'];
                }
				Db::name('shop_order_goods')->where('id',$ogid)->update($ogdata);
				
				$hasff = Db::name('member_commission_record')->where('aid',aid)->where('orderid',$orderid)->where('ogid',$ogid)->where('type','shop')->where('status',1)->find();
				//佣金没有发放 重新计算佣金
				if(!$hasff){
					Db::name('member_commission_record')->where('aid',aid)->where('orderid',$orderid)->where('ogid',$ogid)->where('type','shop')->delete();
					$ogupdate = [];
					$agleveldata = Db::name('member_level')->where('aid',aid)->where('id',$member['levelid'])->find();
					if($agleveldata['can_agent'] > 0 && $agleveldata['commission1own']==1){
						$member['pid'] = $mid;
					}
					if($product['commissionset']!=-1){
						if($member['pid']){
							$parent1 = Db::name('member')->where('aid',aid)->where('id',$member['pid'])->find();
							if($parent1){
								$agleveldata1 = Db::name('member_level')->where('aid',aid)->where('id',$parent1['levelid'])->find();
								if($agleveldata1['can_agent']!=0){
									$ogupdate['parent1'] = $parent1['id'];
								}
							}
						}
						if($parent1['pid']){
							$parent2 = Db::name('member')->where('aid',aid)->where('id',$parent1['pid'])->find();
							if($parent2){
								$agleveldata2 = Db::name('member_level')->where('aid',aid)->where('id',$parent2['levelid'])->find();
								if($agleveldata2['can_agent']>1){
									$ogupdate['parent2'] = $parent2['id'];
								}
							}
						}
						if($parent2['pid']){
							$parent3 = Db::name('member')->where('aid',aid)->where('id',$parent2['pid'])->find();
							if($parent3){
								$agleveldata3 = Db::name('member_level')->where('aid',aid)->where('id',$parent3['levelid'])->find();
								if($agleveldata3['can_agent']>2){
									$ogupdate['parent3'] = $parent3['id'];
								}
							}
						}
						if($parent3['pid']){
							$parent4 = Db::name('member')->where('aid',aid)->where('id',$parent3['pid'])->find();
							if($parent4){
								$agleveldata4 = Db::name('member_level')->where('aid',aid)->where('id',$parent4['levelid'])->find();
								//持续推荐奖励
								if($agleveldata4['can_agent'] > 0 && ($agleveldata4['commission_parent'] > 0 || ($parent4['levelid']==$parent3['levelid'] && $agleveldata4['commission_parent_pj'] > 0))){
									$ogupdate['parent4'] = $parent4['id'];
								}
							}
						}
						if($product['commissionset']==1){//按商品设置的分销比例
							$commissiondata = json_decode($product['commissiondata1'],true);
							if($commissiondata){
								if($agleveldata1) $ogupdate['parent1commission'] = $commissiondata[$agleveldata1['id']]['commission1'] * $commission_totalprice * 0.01;
								if($agleveldata2) $ogupdate['parent2commission'] = $commissiondata[$agleveldata2['id']]['commission2'] * $commission_totalprice * 0.01;
								if($agleveldata3) $ogupdate['parent3commission'] = $commissiondata[$agleveldata3['id']]['commission3'] * $commission_totalprice * 0.01;
							}
						}elseif($product['commissionset']==2){//按固定金额
							$commissiondata = json_decode($product['commissiondata2'],true);
							if($commissiondata){
								if(getcustom('fengdanjiangli') && $product['fengdanjiangli']){
									
								}else{
									if($agleveldata1) $ogupdate['parent1commission'] = $commissiondata[$agleveldata1['id']]['commission1'] * $num;
									if($agleveldata2) $ogupdate['parent2commission'] = $commissiondata[$agleveldata2['id']]['commission2'] * $num;
									if($agleveldata3) $ogupdate['parent3commission'] = $commissiondata[$agleveldata3['id']]['commission3'] * $num;
								}
							}
						}elseif($product['commissionset']==3){//提成是积分
							$commissiondata = json_decode($product['commissiondata3'],true);
							if($commissiondata){
								if($agleveldata1) $ogupdate['parent1score'] = $commissiondata[$agleveldata1['id']]['commission1'] * $num;
								if($agleveldata2) $ogupdate['parent2score'] = $commissiondata[$agleveldata2['id']]['commission2'] * $num;
								if($agleveldata3) $ogupdate['parent3score'] = $commissiondata[$agleveldata3['id']]['commission3'] * $num;
							}
						}else{ //按会员等级设置的分销比例
							if($agleveldata1){
								if(getcustom('plug_ttdz') && $isfg == 1){
									$agleveldata1['commission1'] = $agleveldata1['commission4'];
								}
								if($agleveldata1['commissiontype']==1){ //固定金额按单
									if($istc1==0){
										$ogupdate['parent1commission'] = $agleveldata1['commission1'];
										$istc1 = 1;
									}
								}else{
									$ogupdate['parent1commission'] = $agleveldata1['commission1'] * $commission_totalprice * 0.01;
								}
							}
							if($agleveldata2){
								if(getcustom('plug_ttdz') && $isfg == 1){
									$agleveldata2['commission2'] = $agleveldata2['commission5'];
								}
								if($agleveldata2['commissiontype']==1){
									if($istc2==0){
										$ogupdate['parent2commission'] = $agleveldata2['commission2'];
										$istc2 = 1;
										//持续推荐奖励
										if($agleveldata2['commission_parent'] > 0) {
											$ogupdate['parent2commission'] = $ogupdate['parent2commission'] + $agleveldata2['commission_parent'];
										}
										if($agleveldata1['id'] == $agleveldata2['id'] && $agleveldata2['commission_parent_pj'] > 0) {
											$ogupdate['parent2commission'] = $ogupdate['parent2commission'] + $agleveldata2['commission_parent_pj'];
										}
									}
								}else{
									$ogupdate['parent2commission'] = $agleveldata2['commission2'] * $commission_totalprice * 0.01;
									//持续推荐奖励
									if($agleveldata2['commission_parent'] > 0 && $ogupdate['parent1commission'] > 0) {
										$ogupdate['parent2commission'] = $ogupdate['parent2commission'] + $ogupdate['parent1commission'] * $agleveldata2['commission_parent'] * 0.01;
									}
									if($agleveldata1['id'] == $agleveldata2['id'] && $agleveldata2['commission_parent_pj'] > 0 && $ogupdate['parent1commission'] > 0) {
										$ogupdate['parent2commission'] = $ogupdate['parent2commission'] + $ogupdate['parent1commission'] * $agleveldata2['commission_parent_pj'] * 0.01;
									}
								}
							}
							if($agleveldata3){
								if(getcustom('plug_ttdz') && $isfg == 1){
									$agleveldata3['commission3'] = $agleveldata3['commission6'];
								}
								if($agleveldata3['commissiontype']==1){
									if($istc3==0){
										$ogupdate['parent3commission'] = $agleveldata3['commission3'];
										$istc3 = 1;
										//持续推荐奖励
										if($agleveldata3['commission_parent'] > 0) {
											$ogupdate['parent3commission'] = $ogupdate['parent3commission'] + $agleveldata3['commission_parent'];
										}
										if($agleveldata2['id'] == $agleveldata3['id'] && $agleveldata3['commission_parent_pj'] > 0) {
											$ogupdate['parent3commission'] = $ogupdate['parent3commission'] + $agleveldata3['commission_parent_pj'];
										}
									}
								}else{
									$ogupdate['parent3commission'] = $agleveldata3['commission3'] * $commission_totalprice * 0.01;
									//持续推荐奖励
									if($agleveldata3['commission_parent'] > 0 && $ogupdate['parent2commission'] > 0) {
										$ogupdate['parent3commission'] = $ogupdate['parent3commission'] + $ogupdate['parent2commission'] * $agleveldata3['commission_parent'] * 0.01;
									}
									if($agleveldata2['id'] == $agleveldata3['id'] && $agleveldata3['commission_parent_pj'] > 0 && $ogupdate['parent2commission'] > 0) {
										$ogupdate['parent3commission'] = $ogupdate['parent3commission'] + $ogupdate['parent2commission'] * $agleveldata3['commission_parent_pj'] * 0.01;
									}
								}
							}
							//持续推荐奖励
							if($agleveldata4['commission_parent'] > 0) {
								if($agleveldata3['commissiontype']==1){
									$ogupdate['parent4commission'] = $agleveldata4['commission_parent'];
								} else {
									$ogupdate['parent4commission'] = $ogupdate['parent3commission'] * $agleveldata4['commission_parent'] * 0.01;
								}
							}
							if($agleveldata3['id'] == $agleveldata4['id'] && $agleveldata4['commission_parent_pj'] > 0) {
								if($agleveldata3['commissiontype']==1){
									$ogupdate['parent4commission'] = $agleveldata4['commission_parent_pj'];
								} else {
									$ogupdate['parent4commission'] = $ogupdate['parent3commission'] * $agleveldata4['commission_parent_pj'] * 0.01;
								}
							}
						}
					}
					if($ogupdate){
						Db::name('shop_order_goods')->where('id',$ogid)->update($ogupdate);
					}

					if($product['commissionset4']==1 && $product['lvprice']==1){ //极差分销
						if($member['path']){
							$parentList = Db::name('member')->where('id','in',$member['path'])->order(Db::raw('field(id,'.$member['path'].')'))->select()->toArray();
							if($parentList){
								$parentList = array_reverse($parentList);
								$lvprice_data = json_decode($guige['lvprice_data'],true);
								$nowprice = $commission_totalpriceCache;
								$giveidx = 0;
								foreach($parentList as $k=>$parent){
									if($parent['levelid'] && $lvprice_data[$parent['levelid']]){
										$thisprice = floatval($lvprice_data[$parent['levelid']]) * $num;
										if($nowprice > $thisprice){
											$commission = $nowprice - $thisprice;
											$nowprice = $thisprice;
											$giveidx++;
											//if($giveidx <=3){
											//	$ogupdate['parent'.$giveidx] = $parent['id'];
											//	$ogupdate['parent'.$giveidx.'commission'] = $commission;
											//}
											Db::name('member_commission_record')->insert(['aid'=>aid,'mid'=>$parent['id'],'frommid'=>$mid,'orderid'=>$orderid,'ogid'=>$ogid,'type'=>'shop','commission'=>$commission,'score'=>0,'remark'=>'下级购买商品差价','createtime'=>time()]);
										}
									}
								}
							}
						}
					}

					if($product['commissionset']!=4){
						if(getcustom('plug_ttdz') && $isfg == 1){
							if($ogupdate['parent1'] && ($ogupdate['parent1commission'] || $ogupdate['parent1score'])){
								Db::name('member_commission_record')->insert(['aid'=>aid,'mid'=>$ogupdate['parent1'],'frommid'=>$mid,'orderid'=>$orderid,'ogid'=>$ogid,'type'=>'shop','commission'=>$ogupdate['parent1commission'],'score'=>$ogupdate['parent1score'],'remark'=>'下级复购奖励','createtime'=>time()]);
							}
							if($ogupdate['parent2'] && ($ogupdate['parent2commission'] || $ogupdate['parent2score'])){
								Db::name('member_commission_record')->insert(['aid'=>aid,'mid'=>$ogupdate['parent2'],'frommid'=>$mid,'orderid'=>$orderid,'ogid'=>$ogid,'type'=>'shop','commission'=>$ogupdate['parent2commission'],'score'=>$ogupdate['parent2score'],'remark'=>'下二级复购奖励','createtime'=>time()]);
							}
							if($ogupdate['parent3'] && ($ogupdate['parent3commission'] || $ogupdate['parent3score'])){
								Db::name('member_commission_record')->insert(['aid'=>aid,'mid'=>$ogupdate['parent3'],'frommid'=>$mid,'orderid'=>$orderid,'ogid'=>$ogid,'type'=>'shop','commission'=>$ogupdate['parent3commission'],'score'=>$ogupdate['parent3score'],'remark'=>'下三级复购奖励','createtime'=>time()]);
							}
						}else{
							if($ogupdate['parent1'] && ($ogupdate['parent1commission'] || $ogupdate['parent1score'])){
								Db::name('member_commission_record')->insert(['aid'=>aid,'mid'=>$ogupdate['parent1'],'frommid'=>$mid,'orderid'=>$orderid,'ogid'=>$ogid,'type'=>'shop','commission'=>$ogupdate['parent1commission'],'score'=>$ogupdate['parent1score'],'remark'=>'下级购买商品奖励','createtime'=>time()]);
							}
							if($ogupdate['parent2'] && ($ogupdate['parent2commission'] || $ogupdate['parent2score'])){
								Db::name('member_commission_record')->insert(['aid'=>aid,'mid'=>$ogupdate['parent2'],'frommid'=>$mid,'orderid'=>$orderid,'ogid'=>$ogid,'type'=>'shop','commission'=>$ogupdate['parent2commission'],'score'=>$ogupdate['parent2score'],'remark'=>'下二级购买商品奖励','createtime'=>time()]);
							}
							if($ogupdate['parent3'] && ($ogupdate['parent3commission'] || $ogupdate['parent3score'])){
								Db::name('member_commission_record')->insert(['aid'=>aid,'mid'=>$ogupdate['parent3'],'frommid'=>$mid,'orderid'=>$orderid,'ogid'=>$ogid,'type'=>'shop','commission'=>$ogupdate['parent3commission'],'score'=>$ogupdate['parent3score'],'remark'=>'下三级购买商品奖励','createtime'=>time()]);
							}
							if($ogupdate['parent4'] && ($ogupdate['parent4commission'])){
								Db::name('member_commission_record')->insert(['aid'=>aid,'mid'=>$ogupdate['parent4'],'frommid'=>$mid,'orderid'=>$orderid,'ogid'=>$ogid,'type'=>'shop','commission'=>$ogupdate['parent4commission'],'score'=>0,'remark'=>'持续推荐奖励','createtime'=>time()]);
							}
						}
					}
				}
			}
            $newordernum = date('ymdHis').rand(100000,999999);
            $ordernumArr = explode('_',$info['ordernum']);
            if($ordernumArr[1]) $newordernum .= '_'.$ordernumArr[1];
            Db::name('shop_order')->where('aid',aid)->where('id',$orderid)->update(['ordernum'=>$newordernum]);
            Db::name('shop_order_goods')->where('aid',aid)->where('orderid',$orderid)->update(['ordernum'=>$newordernum]);
            $payorderid = Db::name('shop_order')->where('aid',aid)->where('id',$orderid)->value('payorderid');
            \app\models\Payorder::updateorder($payorderid,$newordernum,$postinfo['totalprice'],$orderid);
			\app\commons\System::plog('商城订单编辑'.$orderid);
			return json(['status'=>1,'msg'=>'修改成功']);
		}
        if($calType==2){
            foreach ($order_goods as $k=>$goods){
                $order_goods[$k]['total_weight'] = round(($goods['real_total_weight']>0?$goods['real_total_weight']:$goods['total_weight'])/1000,2);
                $order_goods[$k]['sell_price'] = $goods['real_sell_price']>0?$goods['real_sell_price']:$goods['sell_price'];
            }
            $info['product_price'] = $info['totalprice']?$info['totalprice']:$info['product_price'];
        }

        View::assign('info',$info);
        View::assign('price_status', $price_update_status);
		View::assign('caltype',$calType);
		View::assign('order_goods',$order_goods);
		View::assign('discount',$discount);
		View::assign('express_data',express_data(['aid'=>aid,'bid'=>bid]));
		return View::fetch();
	}
	//送货单
	public function shd(){
		$orderid = input('param.id/d');
		$info = Db::name('shop_order')->where('aid',aid)->where('id',$orderid)->find();
		if(!$info || (bid !=0 && $info['bid'] != bid)) showmsg('订单不存在');
		$ordergoods = Db::name('shop_order_goods')->where('aid',aid)->where('orderid',$orderid)->select()->toArray();
		$totalnum = 0;
		$order_goods = [];
		foreach($ordergoods as $k=>$v){
			if($v['refund_num']>0){
				//减去退款金额
				$refund_money = 0+Db::name('shop_refund_order_goods')
					->alias('og')
					->join('shop_refund_order o','o.id=og.refund_orderid')
					->where('og.orderid',$orderid)
					->where('og.ogid',$v['id'])
					->whereRaw('o.refund_status = 1 or o.refund_status = 2 or o.refund_status = 4')
					->where('og.aid',aid)
					->sum('og.refund_money');
				$info['totalprice'] -= $refund_money;
			}
			if($v['num']>$v['refund_num']){
				$v['num'] = $v['num']-$v['refund_num'];
				$v['lvprice'] = Db::name('shop_product')->where('id',$v['proid'])->value('lvprice'); //是否开启会员价
	            $remark = '';
	            if(getcustom('product_glass')){
	                $grrowArr = \app\models\ShopOrder::getGlassRecordRow($v);
	                if($grrowArr){
	                    $remark = $grrowArr['row1'].$grrowArr['row2'].$grrowArr['row3'].$grrowArr['row4'];
	                }
	            }
                if(getcustom('product_service_fee')){
                    $remark = Db::name('shop_product')->where('id', $v['proid'])->value('shd_remark');
                }

                $v['remark'] = $remark;
	            $order_goods[] = $v;
	            $totalnum += $v['num'];
            }
		}
        //如果买家留言为空，则找自定义字段为备注的值
        $info['message'] = \app\models\ShopOrder::checkOrderMessage($info['id'],$info);
		$member = Db::name('member')->where('id',$info['mid'])->find();
		$userlevel = Db::name('member_level')->where('aid',aid)->where('id',$member['levelid'])->find();
		if($userlevel && $userlevel['discount']>0 && $userlevel['discount']<10){
			$discount = $userlevel['discount']*0.1; //会员折扣
		}else{
			$discount = 1;
		}

		$field = 'shipping_pagetitle,shipping_pagenum,shipping_linenum';
		if(bid>0){
			$sysset = Db::name('business')->where('id',bid)->field($field)->find();
		}else{
			$sysset = Db::name('shop_sysset')->where('aid',aid)->field($field)->find();
		}
		$pagenum = $sysset['shipping_pagenum'];

		$order_goods2 = [];
		$count = count($order_goods);
		$num  = $count+1;

		if($pagenum >0){
			$beinum = ceil($num/$pagenum);//倍数
			$cha    = $pagenum-$num;
			if($beinum>1){
				$order_goods2 = $order_goods;
				$yunum = $num % $pagenum;//余数
				$cha  = $pagenum-($yunum+3);
				if($cha<=0){
					$len = $cha+$pagenum;
					if($len>0){
						for($i=0;$i<$len;$i++){
							$order_goods2[] = [];
						}
					}
				}else{
					for($i=0;$i<$cha;$i++){
						$order_goods2[] = [];
					}
				}
			}else{
				$order_goods2 = $order_goods;
				if($cha>0){
					//添加行数
					for($i=0;$i<$cha;$i++){
						$order_goods2[] = [];
					}
				}
			}
		}else{
			$order_goods2 = $order_goods;
		}

		if(!getcustom('baikangxie')){
			$order_goods2[] = ['type'=>'yf'];
			$order_goods2[] = ['type'=>'totalprice'];
			$order_goods2[] = ['type'=>'totalprice2'];
		}else{
			$order_goods2[] = ['name'=>'合计','num'=>$totalnum];
		}
		//买家留言
        $order_goods2[] = ['type'=>'remark'];
        if($pagenum >0){
			if($beinum>1){
				$order_goods3 = array_chunk($order_goods2,$pagenum);
			}else{
				$chunk = $pagenum+4;
				$order_goods3 = array_chunk($order_goods2,$chunk);
			}
		}else{
			$order_goods3 = [$order_goods2];
		}

		$info['totalprice2'] = num_to_rmb($info['totalprice']);
		if($info['freight_type'] == 11){
			$info['freight_content'] = json_decode($info['freight_content'],true);
		}
		if($info['bid'] == 0){
			$bname = Db::name('admin_set')->where('aid',aid)->value('name');
		}else{
			$bname = Db::name('business')->where('id',$info['bid'])->value('name');
		}

		if(getcustom('mendian_apply')){
			if($info['mdid']){
				$mendian =  Db::name('mendian')->where('id',$info['mdid'])->find();
				$info['mdaddress'] = $mendian['address'];
				$info['mdname'] = $mendian['name'];
			}
		}
		$info['order_goods3'] = $order_goods3;

		View::assign('bname',$bname);
        View::assign('shipping_pagetitle', $sysset['shipping_pagetitle']);
		View::assign('info',$info);
		View::assign('order_goods3',$order_goods3);
		View::assign('discount',$discount);
		View::assign('express_data',express_data(['aid'=>aid,'bid'=>bid]));
		View::assign('sysset',$sysset);
		View::assign('bid',bid);
		View::assign('count',count($info['order_goods3']));
		return View::fetch();
	}

	//批量打印送货单
	public function plshd(){
		$isopen   = !input('param.isopen') || input('param.isopen') == 1?1:0;
		$orderids = input('param.ids');
		$orderarr = explode(',',$orderids);
		$goodslist = [];

		$field = 'shipping_pagetitle,shipping_pagenum,shipping_linenum';
		if(getcustom('shd_print') ||  getcustom('shop_shd_print2')){
            $field .= ',printmoney,printlian';
        }
		if(bid>0){
			$sysset = Db::name('business')->where('id',bid)->field($field)->find();
		}else{
			$sysset = Db::name('shop_sysset')->where('aid',aid)->field($field)->find();
		}
		$pagenum = $sysset['shipping_pagenum'];
		foreach($orderarr as $k=>$orderid){
			$info = Db::name('shop_order')->where('aid',aid)->where('id',$orderid)->find();
			if(!$info || (bid !=0 && $info['bid'] != bid)) showmsg('订单不存在');

			$info['mname']    = '';
			$info['maddress'] = '';
			if($info['mdid']){
				$mendian = Db::name('mendian')->where('id',$info['mdid'])->field('id,name,area,address')->find();
				if($mendian){
					$info['mname']    = $mendian['name'];
					$info['maddress'] = $mendian['area'].' '.$mendian['address'];
				}
			}

			$ordergoods = Db::name('shop_order_goods')->where('aid',aid)->where('orderid',$orderid)->select()->toArray();
			$totalnum = 0;
			$order_goods = [];
			foreach($ordergoods as $k=>$v){
				if($v['refund_num']>0){
					//减去退款金额
					$refund_money = 0+Db::name('shop_refund_order_goods')
						->alias('og')
						->join('shop_refund_order o','o.id=og.refund_orderid')
						->where('og.orderid',$orderid)
						->where('og.ogid',$v['id'])
						->whereRaw('o.refund_status = 1 or o.refund_status = 2 or o.refund_status = 4')
						->where('og.aid',aid)
						->sum('og.refund_money');
					$info['totalprice'] -= $refund_money;
				}
				if($v['num']>$v['refund_num']){
					$v['num'] = $v['num']-$v['refund_num'];
					$v['lvprice'] = Db::name('shop_product')->where('id',$v['proid'])->value('lvprice'); //是否开启会员价
					$totalnum += $v['num'];
	                $remark = '';
	                if(getcustom('product_glass')){
	                    $grrowArr = \app\models\ShopOrder::getGlassRecordRow($v);
	                    if($grrowArr){
	                        $remark = $grrowArr['row1'].$grrowArr['row2'].$grrowArr['row3'].$grrowArr['row4'];
	                    }
	                }
                    if(getcustom('product_service_fee')){
                        $remark = Db::name('shop_product')->where('id', $v['proid'])->value('shd_remark');
                    }

	                $v['remark'] = $remark;
	                $order_goods[] = $v;
	            }
			}
			unset($v);
			$member = Db::name('member')->where('id',$info['mid'])->find();
			$userlevel = Db::name('member_level')->where('aid',aid)->where('id',$member['levelid'])->find();
			if($userlevel && $userlevel['discount']>0 && $userlevel['discount']<10){
				$discount = $userlevel['discount']*0.1; //会员折扣
			}else{
				$discount = 1;
			}

			$order_goods2 = [];
			$count = count($order_goods);
			if($sysset && $sysset['printmoney'] == 1){
				$num  = $count+1;
			}else{
				$num  = $count;
			}
			if($pagenum >0){
				$beinum = ceil($num/$pagenum);//倍数
				$cha    = $pagenum-$num;
				if($beinum>1){
					$order_goods2 = $order_goods;
					$yunum = $num % $pagenum;//余数
					if($sysset && $sysset['printmoney'] == 1){
						$cha  = $pagenum-($yunum+3);
					}else{
						$cha  = $pagenum-($yunum+1);
					}
					if($cha<=0){
						$len = $cha+$pagenum;
						if($len>0){
							for($i=0;$i<$len;$i++){
								$order_goods2[] = [];
							}
						}
					}else{
						for($i=0;$i<$cha;$i++){
							$order_goods2[] = [];
						}
					}
				}else{
					$order_goods2 = $order_goods;
					if($cha>0){
						//添加行数
						for($i=0;$i<$cha;$i++){
							$order_goods2[] = [];
						}
					}
				}
			}else{
				$order_goods2 = $order_goods;
			}
			if($sysset && $sysset['printmoney'] == 1){
				if(!getcustom('baikangxie')){
					$order_goods2[] = ['type'=>'yf'];
					$order_goods2[] = ['type'=>'totalprice'];
					$order_goods2[] = ['type'=>'totalprice2'];
				}else{
					$order_goods2[] = ['name'=>'合计','num'=>$totalnum];
				}
			}
			//买家留言
            $order_goods2[] = ['type'=>'remark'];

	        if($pagenum >0){
				if($beinum>1){
					$order_goods3 = array_chunk($order_goods2,$pagenum);
				}else{
					if($sysset && $sysset['printmoney'] == 1){
						$chunk = $pagenum+4;
					}else{
						$chunk = $pagenum+1;
					}
					$order_goods3 = array_chunk($order_goods2,$chunk);
				}
			}else{
				$order_goods3 = [$order_goods2];
			}

			$info['totalprice2'] = num_to_rmb($info['totalprice']);
			if($info['freight_type'] == 11){
				$info['freight_content'] = json_decode($info['freight_content'],true);
			}
			if($info['bid'] == 0){
				$bname = Db::name('admin_set')->where('aid',aid)->value('name');
			}else{
				$bname = Db::name('business')->where('id',$info['bid'])->value('name');
			}
			$info['order_goods3'] = $order_goods3;

            //如果买家留言为空，则找自定义字段为备注的值
            $info['message'] = \app\models\ShopOrder::checkOrderMessage($orderid,$info);
			$goodslist[] = $info;
		}

		View::assign('bname',$bname);
        View::assign('shipping_pagetitle', $sysset['shipping_pagetitle']);
		View::assign('goodslist',$goodslist);
		View::assign('count',count($goodslist));
		View::assign('discount',$discount);
		View::assign('express_data',express_data(['aid'=>aid,'bid'=>bid]));

		View::assign('sysset',$sysset);
		View::assign('isopen',$isopen);
		View::assign('bid',bid);
		return View::fetch();
	}
	//批量打印送货单
	public function plshd2(){
		if(getcustom('shop_shd_print2')){
			$orderids = input('param.ids');
			$orderarr = explode(',',$orderids);

			$goodslist   = [];
			$ordergoods2= [];
			$totalprice = 0;
			$totalnum   = 0;

			$field = 'shipping_pagetitle,shipping_pagenum,shipping_linenum';
			if(getcustom('shd_print') ||  getcustom('shop_shd_print2')){
	            $field .= ',printmoney,printlian';
	        }
			if(bid>0){
				$sysset = Db::name('business')->where('id',bid)->field($field)->find();
			}else{
				$sysset = Db::name('shop_sysset')->where('aid',aid)->field($field)->find();
			}
			$pagenum = $sysset['shipping_pagenum'];
			foreach($orderarr as $k=>$orderid){
				$info = Db::name('shop_order')->where('aid',aid)->where('id',$orderid)->find();
				if(!$info || (bid !=0 && $info['bid'] != bid)) showmsg('订单不存在');

				//查询区县位置
                $pos = strpos($v['area'],'区');
                if($pos>=0){
                	//截取后面
                	$info['area'] = substr($v['area'],$pos+1);
                }else{
                	$pos = strpos($info['area'],'县');
                	if($pos>=0){
	                	//截取后面
	                	$info['area'] = substr($v['area'],$pos+1);
	                }
                }

				$totalprice += $info['totalprice'];
				if($info['freight_type'] == 11){
					$info['freight_content'] = json_decode($info['freight_content'],true);
				}
				if($info['bid'] == 0){
					$bname = Db::name('admin_set')->where('aid',aid)->value('name');
				}else{
					$bname = Db::name('business')->where('id',$info['bid'])->value('name');
				}

				$ordergoods = Db::name('shop_order_goods')->where('aid',aid)->where('orderid',$orderid)->select()->toArray();

				$totalnum = 0;
				if($ordergoods){
					foreach($ordergoods as $k=>$v){
						if($v['num']>$v['refund_num']){
							$v['num'] = $v['num']-$v['refund_num'];
							$v['lvprice'] = Db::name('shop_product')->where('id',$v['proid'])->value('lvprice'); //是否开启会员价
							$v['order']    = $info;

							//减去退款金额
							$refund_money = 0+Db::name('shop_refund_order_goods')
								->alias('og')
								->join('shop_refund_order o','o.id=og.refund_orderid')
								->where('og.orderid',$orderid)
								->where('og.ogid',$v['id'])
								->whereRaw('o.refund_status = 1 or o.refund_status = 2 or o.refund_status = 4')
								->where('og.aid',aid)
								->sum('og.refund_money');
							$v['price'] = $v['totalprice'] - $refund_money;
							array_push($ordergoods2,$v);
							$totalnum      += $v['num'];
						}
					}
				}
			}

			$count = count($ordergoods2);
			$num  = $count;
			if($pagenum >0){
				$beinum = ceil($num/$pagenum);//倍数
				$cha    = $pagenum-$num;
				if($beinum>1){
					$yunum = $num % $pagenum;//余数
					if($yunum!= 0){
						$cha  = $pagenum-$yunum;
						if($cha<0){
							$len = $cha+$pagenum;
							if($len>0){
								for($i=0;$i<$len;$i++){
									$ordergoods2[] = [];
								}
							}
						}else if($cha>0){
							for($i=0;$i<$cha;$i++){
								$ordergoods2[] = [];
							}
						}
					}
					$goodslist = array_chunk($ordergoods2,$pagenum);

				}else{
					if($cha>0){
						//添加行数
						for($i=0;$i<$cha;$i++){
							$ordergoods2[] = [];
						}
					}
					if($sysset && $sysset['printmoney'] == 1){
						$chunk = $pagenum+4;
					}else{
						$chunk = $pagenum+1;
					}
					$goodslist = array_chunk($ordergoods2,$chunk);
				}
			}else{
				$goodslist = [$ordergoods2];
			}
			View::assign('bname',$bname);
	        View::assign('shipping_pagetitle',$sysset['shipping_pagetitle']);
			View::assign('goodslist',$goodslist);
			View::assign('count',count($goodslist));
			//View::assign('discount',$discount);

			$totalprice2 = num_to_rmb($totalprice);
			View::assign('totalprice',$totalprice);
			View::assign('totalprice2',$totalprice2);

			View::assign('sysset',$sysset);
			return View::fetch();
		}
	}
	public function shd1(){
		$orderid = input('param.id/d');
		$info = Db::name('shop_order')->where('aid',aid)->where('id',$orderid)->find();
		if(!$info || (bid !=0 && $info['bid'] != bid)) showmsg('订单不存在');
		$order_goods = Db::name('shop_order_goods')->where('aid',aid)->where('orderid',$orderid)->select()->toArray();
		$totalnum = 0;
		$count = count($order_goods);
		foreach($order_goods as $k=>$v){
			$order_goods[$k]['no2'] = ''.($k+1);
			if($k == 0) $order_goods[$k]['no2'] = '①';
			if($k == 1) $order_goods[$k]['no2'] = '②';
			if($k == 2) $order_goods[$k]['no2'] = '③';
			if($k == 3) $order_goods[$k]['no2'] = '④';
			if($k == 4) $order_goods[$k]['no2'] = '⑤';
			if($k == 5) $order_goods[$k]['no2'] = '⑥';
			if($k == 6) $order_goods[$k]['no2'] = '⑦';
			if($k == 7) $order_goods[$k]['no2'] = '⑧';
			if($k == 8) $order_goods[$k]['no2'] = '⑨';
			if($k == 9) $order_goods[$k]['no2'] = '⑩';
            $order_goods[$k]['no'] = ($count < 10 ? '0' : '').$count.'-'.($k+1 < 10 ? '0' : '').($k+1);
		}
        //如果买家留言为空，则找自定义字段为备注的值
        $info['message'] = \app\models\ShopOrder::checkOrderMessage($info['id'],$info);
		$member = Db::name('member')->where('id',$info['mid'])->find();
		$userlevel = Db::name('member_level')->where('aid',aid)->where('id',$member['levelid'])->find();
		if($userlevel && $userlevel['discount']>0 && $userlevel['discount']<10){
			$discount = $userlevel['discount']*0.1; //会员折扣
		}else{
			$discount = 1;
		}
		$info['totalprice2'] = num_to_rmb($info['totalprice']);
		if($info['freight_type'] == 11){
			$info['freight_content'] = json_decode($info['freight_content'],true);
		}
		if($info['bid'] == 0){
			$bname = Db::name('admin_set')->where('aid',aid)->value('name');
		}else{
			$bname = Db::name('business')->where('id',$info['bid'])->value('name');
		}

        //$shipping_pagetitle = Db::name('shop_sysset')->where('aid',aid)->value('shipping_pagetitle');
		//if(!$shipping_pagetitle) $shipping_pagetitle = $bname.'送货单';
		View::assign('bname',$bname);
        //View::assign('shipping_pagetitle',$shipping_pagetitle);
		View::assign('info',$info);
		View::assign('order_goods',$order_goods);
		return View::fetch();
	}

	//订单统计
	public function tongji(){
        //是否隐藏成本和利润
        $order_cost_hide = 0;
        if(getcustom('shoporder_cost_hide')){
            $order_cost_hide = Db::name('admin')->where('id',aid)->value('order_cost_hide');
        }
        $getType = input('param.type',0);
		if(request()->isAjax() || input('param.excel') == 1){
			if(input('param.type')==3){
				$year = date('Y');
				$month = '';
				$day = '';
				$tjtype = 1;
				if(input('param.year')) $year = input('param.year');
				if(input('param.month')) $month = input('param.month');
				if(input('param.day')) $day = input('param.day');
				if(input('param.tjtype')) $tjtype = input('param.tjtype');
                $where = [];
                if(input('param.mid')){
                    if (input('param.isteam')==1) {
                        $downmids = \app\commons\Member::getteammids(aid,input('param.mid'));
                        $where[] = ['mid','in',$downmids];
                    } else {
                        $where[] = ['mid','=',input('param.mid')];
                    }
                    
                }
                
                
                
                
                
                
                
                
                
				$data = [];
				$totalval = 0;
				$maxval = 0;
				$maxdate = '';
				if(!$month){
					for($i=1;$i<13;$i++){
						$thismonth = $i >=10 ? ''.$i : '0'.$i;
						$starttime = strtotime($year.'-'.$thismonth.'-01');
						if($thismonth == 12){
							$endtime = strtotime(($year+1).'-01-01');
						}else{
							$nextmonth = $thismonth+1;
							$nextmonth = $nextmonth >=10 ? ''.$nextmonth : '0'.$nextmonth;
							$endtime = strtotime($year.'-'.$nextmonth.'-01');
						}
						
						$thisdata = [];
						$thisdata['date'] = $i;
						if($tjtype == 1){ //成交额
                           
							if(bid == 0){
								$val = 0 + Db::name('shop_order')->where('aid',aid)->where('paytime','>=',$starttime)->where('paytime','<',$endtime)->where('status','in','1,2,3')->where($where)->sum('totalprice');
							}else{
								$val = 0 + Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('paytime','>=',$starttime)->where('paytime','<',$endtime)->where('status','in','1,2,3')->where($where)->sum('totalprice');
							}
						}else{ //成交量
							if(bid == 0){
								$val = 0 + Db::name('shop_order')->where('aid',aid)->where('paytime','>=',$starttime)->where('paytime','<',$endtime)->where('status','in','1,2,3')->where($where)->count();
							}else{
								$val = 0 + Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('paytime','>=',$starttime)->where('paytime','<',$endtime)->where('status','in','1,2,3')->where($where)->count();
							}
						}
						$val = round($val,2);
						$totalval += $val;
						$thisdata['val'] = $val;
						
						if($maxval < $val){
							$maxval = $val;
							$maxdate = $thismonth.'月';
						}
						$data[] = $thisdata;
					}
					$title = array('月份',$tjtype == 1 ? '交易额' : '交易量','占比');
				}elseif(!$day){
					$month = $month>9?''.$month:'0'.$month;
					$ts = date('t',strtotime($year.'-'.$month.'-01'));
					for($i=1;$i<$ts;$i++){
						$thisday = $i >=10 ? ''.$i : '0'.$i;
						$starttime = strtotime($year.'-'.$month.'-'.$thisday);
						$endtime = $starttime + 86400;
						
						$thisdata = [];
						$thisdata['date'] = $i;
						if($tjtype == 1){ //成交额
							if(bid == 0){
								$val = 0 + Db::name('shop_order')->where('aid',aid)->where('paytime','>=',$starttime)->where('paytime','<',$endtime)->where('status','in','1,2,3')->where($where)->sum('totalprice');
							}else{
								$val = 0 + Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('paytime','>=',$starttime)->where('paytime','<',$endtime)->where('status','in','1,2,3')->where($where)->sum('totalprice');
							}
						}else{ //成交量
							if(bid == 0){
								$val = 0 + Db::name('shop_order')->where('aid',aid)->where('paytime','>=',$starttime)->where('paytime','<',$endtime)->where('status','in','1,2,3')->where($where)->count();
							}else{
								$val = 0 + Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('paytime','>=',$starttime)->where('paytime','<',$endtime)->where('status','in','1,2,3')->where($where)->count();
							}
						}
						$val = round($val,2);
						$totalval += $val;
						$thisdata['val'] = $val;

						if($maxval < $val){
							$maxval = $val;
							$maxdate = $thisday.'日';
						}
						$data[] = $thisdata;
					}
					$title = array('日期',$tjtype == 1 ? '交易额' : '交易量','占比');
				}else{
					$month = $month>9?''.$month:'0'.$month;
					$day = $day >6 ? ''.$day : '0'.$day;
					for($i=0;$i<24;$i++){
						$starttime = strtotime($year.'-'.$month.'-'.$day) + $i*3600;
						$endtime = $starttime + 3600;
						
						$thisdata = [];
						$thisdata['date'] = $i.'点-'.($i+1).'点';
						if($tjtype == 1){ //成交额
							if(bid == 0){
								$val = 0 + Db::name('shop_order')->where('aid',aid)->where('paytime','>=',$starttime)->where('paytime','<',$endtime)->where('status','in','1,2,3')->where($where)->sum('totalprice');
							}else{
								$val = 0 + Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('paytime','>=',$starttime)->where('paytime','<',$endtime)->where('status','in','1,2,3')->where($where)->sum('totalprice');
							}
						}else{ //成交量
							if(bid == 0){
								$val = 0 + Db::name('shop_order')->where('aid',aid)->where('paytime','>=',$starttime)->where('paytime','<',$endtime)->where('status','in','1,2,3')->where($where)->count();
							}else{
								$val = 0 + Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('paytime','>=',$starttime)->where('paytime','<',$endtime)->where('status','in','1,2,3')->where($where)->count();
							}
						}
						$val = round($val,2);
						$totalval += $val;
						$thisdata['val'] = $val;

						if($maxval < $val){
							$maxval = $val;
							$maxdate =$i.'点-'.($i+1).'点';
						}
						$data[] = $thisdata;
					}
					$title = array('时间',$tjtype == 1 ? '交易额' : '交易量','占比');
				}
				$totalval = round($totalval,2);
				foreach($data as $k=>$v){
					if($totalval == 0){
						$data[$k]['percent'] = 0;
					}else{
						$data[$k]['percent'] = round($v['val'] / $totalval * 100,2);
					}
				}

				if(input('param.excel') == 1){
					$data[] = ['date'=>'总数','val'=>$totalval,'percent'=>''];
					$data[] = ['date'=>'最高','val'=>$maxval,'percent'=>''];
					$this->export_excel($title,$data);
				}
				return json(['code'=>0,'msg'=>'查询成功','count'=>count($data),'data'=>$data,'tjtype'=>$tjtype,'totalval'=>$totalval,'maxval'=>$maxval,'maxdate'=>$maxdate]);
			}
			elseif(input('param.type')==5){
				if(input('param.excel') == 1){
					$page = 1;
					$limit = 10000000;
				}else{
					$page = input('param.page');
					$limit = input('param.limit');
				}
				if(input('param.field') && input('param.order')){
					$order = input('param.field').' '.input('param.order');
				}else{
					$order = 'sort desc,id desc';
				}
				$where = array();
				$where[] = ['aid','=',aid];
				$where[] = ['bid','=',bid];
				if(input('param.name')) $where[] = ['name','like','%'.$_GET['name'].'%'];
				if(input('param.mid')){
                    $mids = Db::name('shop_order_goods')->where('mid',input('param.mid'))->where('status','in',[1,2,3])->group('proid')->where('aid',aid)->column('proid');
                    $where[] = ['id','in',$mids];
                }
				$count = 0 + Db::name('shop_product')->where($where)->count();
				$data = Db::name('shop_product')->field('*,(select sum(num) from ddwx_shop_order_goods where proid=ddwx_shop_product.id and status in(1,2,3)) buynum,((select sum(num) from ddwx_shop_order_goods where proid=ddwx_shop_product.id and status in(1,2,3)) / viewnum) buypercent')->where($where)->page($page,$limit)->order($order)->select()->toArray();
				$exceldata = [];
				foreach($data as $k=>$v){
					//$data[$k]['buynum'] = Db::name('shop_order_goods')->where('aid',aid)->where('proid',$v['id'])->where('status','in','1,2,3')->sum('num');
					if(!$v['buynum']){
						$data[$k]['buynum'] = 0;
					}
					$data[$k]['buypercent'] = $v['viewnum'] > 0 ? round( $v['buynum'] / $v['viewnum'] * 100 ,2) : 0;
					$exceldata[] = [$v['name'],$v['viewnum'],$data[$k]['buynum'],$data[$k]['buypercent'].'%'];
				}
				if(input('param.excel') == 1){
					$title = ['商品名称','访问次数','购买件数','转化率'];
					$this->export_excel($title,$exceldata);
				}

				return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
			}else{
				$page = input('param.page');
				$limit = input('param.limit');
				if(input('param.field') && input('param.order')){
					$order = input('param.field').' '.input('param.order');
				}else{
					$order = 'totalprice desc';
				}
				$where = [];
				$where[] = ['og.aid','=',aid];
				$where[] = ['og.bid','=',bid];
				$where[] = ['shop_order.status','in','1,2,3'];
//				$where[] = ['og.status','in','1,2,3'];
				if($this->mdid){
					$where[] = ['shop_order.mdid','=',$this->mdid];
				}
				if(input('param.ctime') ){
					$ctime = explode(' ~ ',input('param.ctime'));
					$where[] = ['og.createtime','>=',strtotime($ctime[0])];
					$where[] = ['og.createtime','<',strtotime($ctime[1])];
				}
				if(input('param.paytime') ){
					$ctime = explode(' ~ ',input('param.paytime'));
					$where[] = ['shop_order.paytime','>=',strtotime($ctime[0])];
					$where[] = ['shop_order.paytime','<',strtotime($ctime[1]) + 86400];
				}
				if(input('param.proname')){
					$where[] = ['og.name','like','%'.input('param.proname').'%'];
				}
				if(input('param.cid') && input('param.cid')!==''){
					//取出cid 在的商品
					$cid = input('param.cid');
					//子分类
					$clist = Db::name('shop_category')->where('aid',aid)->where('pid',$cid)->column('id');
					if($clist){
						$clist2 = Db::name('shop_category')->where('aid',aid)->where('pid','in',$clist)->column('id');
						$cCate = array_merge($clist, $clist2, [$cid]);
						if($cCate){
							$whereCid = [];
							foreach($cCate as $k => $c2){
								$whereCid[] = "find_in_set({$c2},og.cid)";
							}
							$where[] = Db::raw(implode(' or ',$whereCid));
						}
					} else {
						$where[] = Db::raw("find_in_set(".$cid.",og.cid)");
					}
					

					//$cprolist = Db::name('shop_product')->where('aid',aid)->where("find_in_set(".input('param.cid').",cid)")->column('id');
					//var_dump($cprolist);
					//if($cprolist){
					//	$where[] =  ['og.proid','in',$cprolist];
					//}
				}
                if(input('param.mid')){
                    $mids = Db::name('shop_order_goods')->where('mid',input('param.mid'))->where('status','in',[1,2,3])->group('proid')->where('aid',aid)->column('proid');
                    $where[] = ['og.proid','in',$mids];
                    $where[] = ['og.mid','=',input('param.mid')];
                }
                if(input('param.mdid') && input('param.mdid')!==''){
                	$where[] = ['shop_order.mdid','=',input('param.mdid')];
                }
                if(input('param.freight_id') && input('param.freight_id')!==''){
                	$where[] = ['shop_order.freight_id','=',input('param.freight_id')];
                }
				$fields = 'og.proid,og.name,og.pic,og.ggname,sum(og.num) num,sum(og.refund_num) refund_num,sum(og.totalprice) totalprice,sum(og.totalprice)/sum(og.num) as avgprice,sum(og.cost_price*og.num) as chengben,sum(og.totalprice-og.cost_price*og.num) lirun,GROUP_CONCAT(",",shop_order.refund_status) as refund_status';
				if(input('param.type')==2){
					$count = 0 + Db::name('shop_order_goods')->alias('og')->join('shop_order','shop_order.id=og.orderid')->fieldRaw('og.proid')->where($where)->group('ggid')->count();
                    $fields.=',og.ggid';
					$list = Db::name('shop_order_goods')->alias('og')->join('shop_order','shop_order.id=og.orderid')->fieldRaw($fields)->where($where)->group('ggid')->page($page,$limit)->order($order)->select()->toArray();
				}elseif(input('param.type')==7){
                    if(getcustom('school_product')){
                        $school_id = input('param.school_id/d');
                        $gradeClass = input('param.grade_id');
                        $class_id = $grade_id = '';
                        if($gradeClass){
                            //选择年级下班级时，格式为：年级_班级
                            $gradeIds = explode('_',$gradeClass);
                            $grade_id = $gradeIds[0];
                            if(count($gradeIds)>1){
                                $class_id = $gradeIds[1];
                            }
                        }
                        if($school_id){
                            $where[] = ['shop_order.school_id','=',$school_id];
                        }else{
                            $where[] = ['shop_order.school_id','<>',0];
                        }
                        if($grade_id){
                            $where[] = ['shop_order.grade_id','=',$grade_id];
                        }else{
                            $where[] = ['shop_order.grade_id','<>',0];
                        }
                        if($class_id){
                            $where[] = ['shop_order.class_id','=',$class_id];
                        }else{
                            $where[] = ['shop_order.class_id','<>',0];
                        }
                        $fields .= ',shop_order.school_id,shop_order.grade_id,shop_order.class_id';
                        $count = 0 + Db::name('shop_order_goods')->alias('og')->join('shop_order','shop_order.id=og.orderid')->fieldRaw('og.orderid')->where($where)->group('ggid,shop_order.school_id,shop_order.grade_id,shop_order.class_id')->count();
                        $list = Db::name('shop_order_goods')->alias('og')->join('shop_order','shop_order.id=og.orderid')->fieldRaw($fields)->where($where)->group('ggid,shop_order.school_id,shop_order.grade_id,shop_order.class_id')->page($page,$limit)->order($order)->select()->toArray();
                    }
                }else{
					$count = 0 + Db::name('shop_order_goods')->alias('og')->join('shop_order','shop_order.id=og.orderid')->fieldRaw('og.proid')->where($where)->group('proid')->count();
					$list = Db::name('shop_order_goods')->alias('og')->join('shop_order','shop_order.id=og.orderid')->fieldRaw($fields)->where($where)->group('proid')->page($page,$limit)->order($order)->select()->toArray();
//					var_dump(db('shop_order_goods')->getlastsql());die;
				}
				if($page == 1){
					$totaldata = Db::name('shop_order_goods')->alias('og')->join('shop_order','shop_order.id=og.orderid')->fieldRaw($fields)->where($where)->find();
                    if(in_array($getType,[0,2])){
                        $refundList = Db::name('shop_refund_order')->alias('refund')
                                ->join('shop_order','shop_order.id=refund.orderid')
                                ->join('shop_order_goods og','shop_order.id=og.orderid')
                                ->where($where)
                                ->where('refund.refund_status',2)
                                ->group('shop_order.id')
                                ->column('refund.refund_money,sum(refund_num) refund_num');
                        //成本，按退款比例计算
                        if($refundList){
                            $refundMoneyAll = array_sum(array_column($refundList,'refund_money'));
                            $refundNumAll = array_sum(array_column($refundList,'refund_num'));
                            if($totaldata['totalprice'] > 0)
                                $rate = round($refundMoneyAll/$totaldata['totalprice'],4);
                            else
                                $rate = 0;
                            $totaldata['chengben'] = round($totaldata['chengben']*(1-$rate),2);
                            $totaldata['totalprice'] = round($totaldata['totalprice']-$refundMoneyAll,2);
                            $totaldata['lirun'] = round($totaldata['totalprice'] - $totaldata['chengben'],2);
                            $totaldata['num'] = intval($totaldata['num'] - $refundNumAll);
                        }
                    }
				}
				foreach($list as $k=>&$v){
					//实际销售量 = 总数量 - 商品退款数量
//                    $v['num'] = $v['num'] - $v['refund_num'];
					//查询部分商品退款金额（退款审核通过的才算）
                    $refundMoney = 0;
                    $hasRefund = false;
                    if($v['refund_status']){
                        $refundStatusArr = explode(',',$v['refund_status']);
                        if(array_search('2',$refundStatusArr)!==false){
                            $hasRefund = true;
                        }
                    }
                    if(in_array($getType,[0,2]) && $hasRefund){
                        //按规格
                        if(input('param.type')==2) {
                            $refundGoods = Db::name('shop_refund_order_goods')->alias('rog')
                                    ->join('shop_refund_order ro','rog.refund_orderid=ro.id')->where('ro.refund_status',2)
                                    ->where('rog.ggid', $v['ggid'])
                                    ->where('rog.aid', aid)
                                    ->where('rog.bid', bid)
                                    ->field('sum(rog.refund_money) as refundMoney,sum(rog.refund_num) as refundNum')->find();
                        }else{
                            $refundGoods =Db::name('shop_refund_order_goods')->alias('rog')
                                    ->join('shop_refund_order ro','rog.refund_orderid=ro.id')->where('ro.refund_status',2)
                                    ->where('rog.proid', $v['proid'])
                                    ->where('rog.aid', aid)
                                    ->where('rog.bid', bid)
                                    ->field('sum(rog.refund_money) as refundMoney,sum(rog.refund_num) as refundNum')->find();
                        }
                        if($refundGoods){
                            $refundMoney = $refundGoods['refundMoney']??0;
                            $refundNum = $refundGoods['refundNum']??0;
                            if($v['totalprice'] > 0)
                                $rateG = $refundMoney>0?round($refundMoney/$v['totalprice'],4):1;
                            else
                                $rateG = 0;
                            $v['totalprice'] = round($v['totalprice'] - $refundMoney,2);
                            $v['chengben'] = round($v['chengben'] * (1-$rateG),2);
                            $v['num'] = $v['num'] - $refundNum;
                            $v['lirun'] = $v['totalprice'] - $v['chengben'];
                        }
                        //实际平均价格
                        $v['avgprice'] = $v['num']>0?$v['totalprice']/$v['num']:$v['totalprice'];
                        $list[$k]['ph'] = ($k+1) + ($page-1)*$limit;
                        $list[$k]['avgprice'] = number_format($v['avgprice'],2,'.','');
                    }else{
                        //实际总款
                        $v['totalprice'] = round($v['totalprice'] - $refundMoney,2);
                        //实际平均价格
                        $v['avgprice'] = $v['num']>0?$v['totalprice']/$v['num']:$v['totalprice'];

                        $list[$k]['ph'] = ($k+1) + ($page-1)*$limit;
                        $list[$k]['avgprice'] = number_format($v['avgprice'],2,'.','');
                    }
                    $v['lirun'] = round($v['lirun'],2);
					if(getcustom('school_product')){
                        $school = '';
                        if($v['school_id']){
                            $school_name = Db::name('school')->where('aid',aid)->where('id',$v['school_id'])->value('name');
                            $school_name && $school .=$school_name;
                        }
                        if($order['grade_id']){
                            $grade_name = Db::name('school_class')->where('aid',aid)->where('id',$v['grade_id'])->value('name');
                            $grade_name && $school .=' '. $grade_name;
                        }
                        if($order['class_id']){
                            $class_name = Db::name('school_class')->where('aid',aid)->where('id',$v['class_id'])->value('name');
                            $grade_name && $school .=' '. $class_name;
                        }
                        $list[$k]['school_info'] = $school;
                    }
				}
				unset($v);
				return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$list,'totaldata'=>$totaldata]);
			}
		}
        //是不是有学校班级统计
        if(getcustom('school_product')){
            $need_school = Db::name('admin')->where('id',aid)->value('need_school');
            if($need_school){
                $schoollist = Db::name('school')->where('aid',aid)->select()->toArray();
                View::assign('schoollist',$schoollist);
            }
            View::assign('need_school',$need_school??0);
        }
        View::assign('order_cost_hide',$order_cost_hide);
        View::assign('type',input('param.type'));
		if(input('param.type')==3){
			return View::fetch('tongji3');
		}
		if(input('param.type')==4){
			$membercount = Db::name('member')->where('aid',aid)->count(); //总会员数
			if(bid == 0){
				$totalprice = Db::name('shop_order')->where('aid',aid)->where('status','in','1,2,3')->sum('totalprice'); //总订单金额
				$totalnum = Db::name('shop_order')->where('aid',aid)->where('status','in','1,2,3')->count(); //总订单数
				$totalview = Db::name('shop_product')->where('aid',aid)->sum('viewnum'); //总访问数
				$memberxf = Db::name('shop_order')->where('aid',aid)->group('mid')->where('status','in','1,2,3')->count(); //消费会员数
			}else{
				$totalprice = Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('status','in','1,2,3')->sum('totalprice'); //总订单金额
				$totalnum = Db::name('shop_order')->where('aid',aid)->where('bid',bid)->where('status','in','1,2,3')->count(); //总订单数
				$totalview = Db::name('shop_product')->where('aid',aid)->where('bid',bid)->sum('viewnum'); //总访问数
				$memberxf = Db::name('shop_order')->where('aid',aid)->where('bid',bid)->group('mid')->where('status','in','1,2,3')->count(); //消费会员数
			}

			$percent1 = $membercount > 0 ? round($totalprice / $membercount,2) : 0; //会员人均消费
			$percent2 = $totalview > 0 ? round($totalprice / $totalview * 100,2) : 0; //访问转换率
			$percent3 = $totalview > 0 ? round($totalnum / $totalview * 100,2) : 0; //订单转化率
			$percent4 = $membercount > 0 ? round($memberxf / $membercount * 100,2) : 0; //会员消费率
			$percent5 = $membercount > 0 ? round($totalnum / $membercount * 100,2) : 0; //订单购买率
			View::assign('membercount',$membercount);
			View::assign('totalprice',round($totalprice,2));
			View::assign('totalnum',$totalnum);
			View::assign('totalview',$totalview);
			View::assign('memberxf',$memberxf);
			View::assign('percent1',$percent1);
			View::assign('percent2',$percent2);
			View::assign('percent3',$percent3);
			View::assign('percent4',$percent4);
			View::assign('percent5',$percent5);
			return View::fetch('tongji4');
		}
		if(input('param.type')==5){
			return View::fetch('tongji5');
		}
		if(input('param.type')==6){
			//取出商城分类
			$catelist =  Db::name('shop_category')->where('aid',aid)->where('pid','=',0)->select()->toArray();
			View::assign('paytime',input('param.paytime'));
			View::assign('catelist',$catelist);
			return View::fetch('tongji6');
		}

		$mendians = Db::name('mendian')->where('aid',aid)->where('bid',bid)->field('id,name')->select()->toArray();
        View::assign('mendians',$mendians);

        $freights = Db::name('freight')->where('aid',aid)->where('bid',bid)->field('id,name')->select()->toArray();
        View::assign('freights',$freights);
		return View::fetch();
	}
	//导出
	public function tjexcel(){
		set_time_limit(0);
		ini_set('memory_limit', '2000M');
        $getType = input('param.type',0);
		if(input('param.field') && input('param.order')){
			$order = input('param.field').' '.input('param.order');
		}else{
			$order = 'totalprice desc';
		}
        $page = input('param.page')?:1;
        $limit = input('param.limit')?:10;
		$where = [];
		$where[] = ['og.aid','=',aid];
		$where[] = ['og.bid','=',bid];
//		$where[] = ['og.status','in','1,2,3'];
		$where[] = ['shop_order.status','in','1,2,3'];
		if($this->mdid){
			$where[] = ['shop_order.mdid','=',$this->mdid];
		}
		if(input('param.ctime') ){
			$ctime = explode(' ~ ',input('param.ctime'));
			$where[] = ['og.createtime','>=',strtotime($ctime[0])];
			$where[] = ['og.createtime','<',strtotime($ctime[1])];
		}
		if(input('param.paytime') ){
			$ctime = explode(' ~ ',input('param.paytime'));
			$where[] = ['shop_order.paytime','>=',strtotime($ctime[0])];
			$where[] = ['shop_order.paytime','<',strtotime($ctime[1]) + 86400];
		}
		if(input('param.proname')){
			$where[] = ['og.name','like','%'.input('param.proname').'%'];
		}
		if(input('param.cid')){
			//取出cid 在的商品
			$cid = input('param.cid');
			//子分类
			$clist = Db::name('shop_category')->where('aid',aid)->where('pid',$cid)->column('id');
			if($clist){
				$clist2 = Db::name('shop_category')->where('aid',aid)->where('pid','in',$clist)->column('id');
				$cCate = array_merge($clist, $clist2, [$cid]);
				if($cCate){
					$whereCid = [];
					foreach($cCate as $k => $c2){
						$whereCid[] = "find_in_set({$c2},og.cid)";
					}
					$where[] = Db::raw(implode(' or ',$whereCid));
				}
			} else {
				$where[] = Db::raw("find_in_set(".$cid.",og.cid)");
			}		
			//$where[] = ['og.cid','=',input('param.cid')];
		}
        if(input('param.mid')){
            $mids = Db::name('shop_order_goods')->where('mid',input('param.mid'))->where('status','in',[1,2,3])->group('proid')->where('aid',aid)->column('proid');
            $where[] = ['og.proid','in',$mids];
            $where[] = ['og.mid','=',input('param.mid')];
        }
		$fields = 'og.proid,og.name,og.pic,og.ggname,sum(og.num) num,sum(og.totalprice) totalprice,sum(og.refund_num) refund_num,sum(og.totalprice)/sum(og.num) as avgprice,GROUP_CONCAT(",",shop_order.refund_status) as refund_status';
		if(input('param.type')==2){
            $fields.=',og.ggid';
			$list = Db::name('shop_order_goods')->alias('og')->join('shop_order','shop_order.id=og.orderid')->field($fields)->where($where)->group('ggid')->page($page,$limit)->order($order)->select()->toArray();
		    $count = Db::name('shop_order_goods')->alias('og')->join('shop_order','shop_order.id=og.orderid')->field($fields)->where($where)->count();
		}elseif(input('param.type')==7){
            if(getcustom('school_product')){
                $school_id = input('param.school_id/d');
                $gradeClass = input('param.grade_id');
                $class_id = $grade_id = '';
                if($gradeClass){
                    //选择年级下班级时，格式为：年级_班级
                    $gradeIds = explode('_',$gradeClass);
                    $grade_id = $gradeIds[0];
                    if(count($gradeIds)>1){
                        $class_id = $gradeIds[1];
                    }
                }
                if($school_id){
                    $where[] = ['school_id','=',$school_id];
                }else{
                    $where[] = ['school_id','<>',0];
                }
                if($grade_id){
                    $where[] = ['grade_id','=',$grade_id];
                }else{
                    $where[] = ['grade_id','<>',0];
                }
                if($class_id){
                    $where[] = ['class_id','=',$class_id];
                }else{
                    $where[] = ['class_id','<>',0];
                }
                $fields .= ',shop_order.school_id,shop_order.grade_id,shop_order.class_id';
                $list = Db::name('shop_order_goods')->alias('og')->join('shop_order','shop_order.id=og.orderid')->fieldRaw($fields)->where($where)->group('ggid,shop_order.school_id,shop_order.grade_id,shop_order.class_id')->page($page,$limit)->order($order)->select()->toArray();
                $count = Db::name('shop_order_goods')->alias('og')->join('shop_order','shop_order.id=og.orderid')->fieldRaw($fields)->where($where)->group('ggid,shop_order.school_id,shop_order.grade_id,shop_order.class_id')->count();
            }
        }else{
			$list = Db::name('shop_order_goods')->alias('og')->join('shop_order','shop_order.id=og.orderid')->field($fields)->where($where)->group('proid')->order($order)->page($page,$limit)->select()->toArray();
            $count = Db::name('shop_order_goods')->alias('og')->join('shop_order','shop_order.id=og.orderid')->field($fields)->where($where)->group('proid')->order($order)->count();
		}
		foreach($list as $k=>$v){
            $list[$k]['new_num'] = $v['num'];
            $list[$k]['new_totalprice'] = $v['totalprice'];
            $v['lirun'] = round($v['lirun'],2);
            $list[$k]['new_lirun'] = $v['lirun'];
            $list[$k]['new_chengben'] = $v['chengben'];
            $v['avgprice'] = $v['num']>0?$v['totalprice']/$v['num']:$v['totalprice'];
            $hasRefund = false;
            if($v['refund_status']){
                $refundStatusArr = explode(',',$v['refund_status']);
                if(array_search('2',$refundStatusArr)!==false){
                    $hasRefund = true;
                }
            }
            if(in_array($getType,[0,2]) && $hasRefund){
                //按规格
                if(input('param.type')==2) {
                    $refundGoods = Db::name('shop_refund_order_goods')->alias('rog')
                        ->join('shop_refund_order ro','rog.refund_orderid=ro.id')->where('ro.refund_status',2)
                        ->where('rog.ggid', $v['ggid'])
                        ->where('rog.aid', aid)
                        ->where('rog.bid', bid)
                        ->field('sum(rog.refund_money) as refundMoney,sum(rog.refund_num) as refundNum')->find();
                }else{
                    $refundGoods =Db::name('shop_refund_order_goods')->alias('rog')
                        ->join('shop_refund_order ro','rog.refund_orderid=ro.id')->where('ro.refund_status',2)
                        ->where('rog.proid', $v['proid'])
                        ->where('rog.aid', aid)
                        ->where('rog.bid', bid)
                        ->field('sum(rog.refund_money) as refundMoney,sum(rog.refund_num) as refundNum')->find();
                }
                if($refundGoods){
                    $refundMoney = $refundGoods['refundMoney']??0;
                    $refundNum = $refundGoods['refundNum']??0;
                    $totalpriceG = round($v['totalprice'] - $refundMoney,2);
                    $rateG = $refundMoney>0?round($totalpriceG/$refundMoney,4):1;
                    $chengbenG = round($v['chengben'] * $rateG,2);
                    $numG = $v['num'] - $refundNum;
                    $lirunG = $totalpriceG - $chengbenG;
                    $list[$k]['new_num'] = $numG;
                    $list[$k]['new_totalprice'] = $totalpriceG;
                    $list[$k]['new_lirun'] = $lirunG;
                    $list[$k]['new_chengben'] = $chengbenG;
                    //实际平均价格
                    $v['avgprice'] = $numG>0?$totalpriceG/$numG:$totalpriceG;
                }
            }
			$list[$k]['ph'] = ($k+1);
			$list[$k]['avgprice'] = number_format($v['avgprice'],2,'.','');
		}
		if(input('param.type')==2){
			$title = array('排名','商品名称','商品规格','销售数量','销售金额','平均单价');
			$data = [];
			foreach($list as $k=>$vo){
				$data[] = [
					$vo['ph'],
					$vo['name'],
					$vo['ggname'],
					$vo['new_num'],
					$vo['new_totalprice'],
					$vo['avgprice'],
				];
			}
		}elseif(input('param.type')==7){
            if(getcustom('school_product')){
                $title = array('排名','商品名称','学校班级','销售数量','销售金额','平均单价');
                $data = [];
                foreach($list as $k=>$vo){
                    $school = '';
                    if($order['school_id']){
                        $school_name = Db::name('school')->where('aid',aid)->where('id',$vo['school_id'])->value('name');
                        $school_name && $school .=$school_name;
                    }
                    if($order['grade_id']){
                        $grade_name = Db::name('school_class')->where('aid',aid)->where('id',$vo['grade_id'])->value('name');
                        $grade_name && $school .=' '. $grade_name;
                    }
                    if($order['class_id']){
                        $class_name = Db::name('school_class')->where('aid',aid)->where('id',$vo['class_id'])->value('name');
                        $class_name && $school .=' '. $class_name;
                    }
                    $data[] = [
                        $vo['ph'],
                        $vo['name'],
                        $school,
                        $vo['num'],
                        $vo['totalprice'],
                        $vo['avgprice']
                    ];
                }
            }
        }else{
			$title = array('排名','商品名称','销售数量','销售金额','平均单价');
			$data = [];
			foreach($list as $k=>$vo){
				$data[] = [
					$vo['ph'],
					$vo['name'],
					$vo['new_num'],
					$vo['new_totalprice'],
					$vo['avgprice'],
				]; 
			}
		}
        return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data,'title'=>$title]);
		$this->export_excel($title,$data);
	}

	private function excel2($list){
		$title = array('订单号','客户名称','业务员','业务员手机号码','产品代码','产品条码','商品名称','规格','数量','单价','总价','已付金额','支付方式','收货信息','配送方式','备注','其他');
		$data = [];
		foreach($list as $k=>$vo){
			$member = Db::name('member')->where('id',$vo['mid'])->find();
			$oglist = Db::name('shop_order_goods')->where('orderid',$vo['id'])->select()->toArray();
			$invoice = Db::name('invoice')->where('aid',aid)->where('order_type','shop')->where('orderid',$vo['id'])->find();
			if($vo['bid'] != 0){
				$business = Db::name('business')->where('id',$vo['bid'])->find();
			}else{
				$business = [];
				if($member['levelid']!=3){
					if($member['realname']) $business['linkman'] = $member['realname'];
					if($member['tel']) $business['linktel'] = $member['tel'];
				}
			}
			$xm=array();
			foreach($oglist as $og){
				$xm[] = $og['name']."/".$og['ggname']." × ".$og['num']."";
				$status='';
				if($vo['status']==0){
					$status = '未支付';
				}elseif($vo['status']==2){
					$status = '已发货';
				}elseif($vo['status']==1){
					$status = '已支付';
				}elseif($vo['status']==3){
					$status = '已收货';
				}elseif($vo['status']==4){
					$status = '已关闭';
				}
				$remark = '';
				if($vo['freight_time']) $remark.= $vo['freight_time']."\r\n";
				/*
				if($invoice){
					if($remark) $remark .= "\r\n";
					if($invoice['type'] == 1){
						$remark.= $invoice['invoice_name'].' '.$invoice['mobile'].' '.$invoice['emall'];
					}else{
						if($invoice['name_type'] == 1){
							$remark.= $invoice['invoice_name'].' '.$invoice['tax_no'].' '.$invoice['mobile'].' '.$invoice['emall'];
						}else{
							$remark.= $invoice['invoice_name'].' '.$invoice['tax_no'].' '.$invoice['address'].' '.$invoice['tel'].' '.$invoice['bank_name'].' '.$invoice['bank_account'];
						}
					}
				}
				*/
                //配送自定义表单
                $vo['formdata'] = \app\models\Freight::getformdata($vo['id'],'shop_order');
                if($vo['formdata']) {
                    foreach ($vo['formdata'] as $formdata) {
                        if($formdata[2] != 'upload') {
                            $remark .= $formdata[0].':'.$formdata[1]."\r\n";
                        }
                    }
                }
				$data[] = [
					' '.$vo['ordernum'],
					$invoice ? $invoice['invoice_name'] : '',
					$business['linkman'],
					$business['linktel'],
					$og['procode'],
                    $og['barcode'],
					$og['name'],
					$og['ggname'],
					$og['num'],
					$og['sell_price'],
					$og['totalprice'],
					in_array($vo['status'],[1,2,3]) ? $og['totalprice'] : 0,
					$vo['paytype'],
					$vo['area'].' '.$vo['address'] . ','.$vo['linkman']. ','.$vo['company'].','.$vo['tel'],
					$vo['freight_text'],
					$remark
				];
			}
		}
        return ['data'=>$data,'title'=>$title];
		$this->export_excel($title,$data);
	}

	//订单统计
    public function tongjiAdminuser(){
        if(request()->isAjax()){
            $page = input('param.page');
            $limit = input('param.limit');
            if(input('param.field') && input('param.order')){
                $order = input('param.field').' '.input('param.order');
            }else{
                $order = 'id';
            }
            $where = array();
            $where[] = ['aid','=',aid];
            $where[] = ['bid','=',bid];
            if(input('param.un')) $where[] = ['un','like','%'.input('param.un').'%'];
            if($this->user['isadmin'] == 0){
                if($this->user['groupid'] == 0){
                    $where[] = ['addid','=',$this->user['id']];
                }else{
                    $thisgroup = Db::name('admin_user_group')->where('id',$this->user['groupid'])->find();
                    $groupids = Db::name('admin_user_group')->where('aid',aid)->where('bid',bid)->where("`sort`<".$thisgroup['sort']." or (`sort`={$thisgroup['sort']} and id>{$thisgroup['id']})")->column('id');
                    if($groupids){
                        $where[] = ['groupid','in',$groupids];
                    }else{
                        $where[] = Db::raw('1=0');
                    }
                }
            }
            $count = 0 + Db::name('admin_user')->where($where)->whereOr('id','=',$this->user['id'])->whereOr("groupid=0 and addid=".$this->user['id'])->count();
            $data = Db::name('admin_user')->where($where)->whereOr('id','=',$this->user['id'])->whereOr("groupid=0 and addid=".$this->user['id'])->page($page,$limit)->order($order)->select()->toArray();
            foreach($data as $k=>$v){
                $data[$k]['total'] = 0;
                if($v['mid']){
                    $member = Db::name('member')->where('aid',aid)->where('id',$v['mid'])->find();
                    if($member){
                        $data[$k]['headimg'] = $member['headimg'];
                        $data[$k]['nickname'] = $member['nickname'];
                    }
                    $childrenmid = \app\commons\Member::getdownmids(aid,$v['mid']);
                    $data[$k]['total'] = $this->orderSumByMids($childrenmid);
                }
            }
            return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
        }
        return View::fetch();
    }
    //导出
    public function tongjiadminExcel(){
        set_time_limit(0);
        ini_set('memory_limit', '2000M');
        $where = array();
        $where[] = ['aid','=',aid];
        $where[] = ['bid','=',bid];
        if(input('param.un')) $where[] = ['un','like','%'.input('param.un').'%'];
        if($this->user['isadmin'] == 0){
            if($this->user['groupid'] == 0){
                $where[] = ['addid','=',$this->user['id']];
            }else{
                $thisgroup = Db::name('admin_user_group')->where('id',$this->user['groupid'])->find();
                $groupids = Db::name('admin_user_group')->where('aid',aid)->where('bid',bid)->where("`sort`<".$thisgroup['sort']." or (`sort`={$thisgroup['sort']} and id>{$thisgroup['id']})")->column('id');
                if($groupids){
                    $where[] = ['groupid','in',$groupids];
                }else{
                    $where[] = Db::raw('1=0');
                }
            }
        }
        $data = Db::name('admin_user')->where($where)->whereOr('id','=',$this->user['id'])->whereOr("groupid=0 and addid=".$this->user['id'])->order($order)->select()->toArray();
        foreach($data as $k=>$v){
            $data[$k]['total'] = 0;
            if($v['mid']){
                $member = Db::name('member')->where('aid',aid)->where('id',$v['mid'])->find();
                if($member){
                    $data[$k]['headimg'] = $member['headimg'];
                    $data[$k]['nickname'] = $member['nickname'];
                }
                $childrenmid = \app\commons\Member::getdownmids(aid,$v['mid']);
                $data[$k]['total'] = $this->orderSumByMids($childrenmid);
            }
        }
        $list= $data;
        $title = array('ID','账号','业绩','会员信息');
        $data = [];
        foreach($list as $k=>$vo){
            $data[] = [
                $vo['id'],
                $vo['un'],
                $vo['total'],
                $vo['nickname'].'('.$vo['mid'].')'
            ];
        }
        $this->export_excel($title,$data);
    }

    private function orderSumByMids($mids){
        if(empty($mids)) return 0;

        $where = [];
        $where[] = ['aid','=',aid];
        $where[] = ['bid','=',bid];
        $where[] = ['status','in','1,2,3'];
        $where[] = ['mid','in',$mids];
        if($this->mdid){
            $where[] = ['mdid','=',$this->mdid];
        }
        if(input('param.ctime') ){
            $ctime = explode(' ~ ',input('param.ctime'));
            $where[] = ['createtime','>=',strtotime($ctime[0])];
            $where[] = ['createtime','<',strtotime($ctime[1]) + 86400];
        }
        if(input('param.paytime') ){
            $ctime = explode(' ~ ',input('param.paytime'));
            $where[] = ['paytime','>=',strtotime($ctime[0])];
            $where[] = ['paytime','<',strtotime($ctime[1]) + 86400];
        }
        $totalprice = 0 + Db::name('shop_order')->where($where)->sum('totalprice');
        $totalprice = round($totalprice,2);
        return $totalprice;

    }

	public function getordercount(){
		$ctime = input('param.ctime');
		if(!$ctime){
			return json(['ordercount'=>0,'orderprice'=>0]);
		}
		$ctime = explode(' ~ ',input('param.ctime'));
		
		$sysset = Db::name('admin_set')->where('aid',aid)->find();
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['status','=',3];
		$where[] = ['endtime','>=',strtotime($ctime[0])];
		$where[] = ['endtime','<',strtotime($ctime[1]) + 86400];
		$where[] = ['isfenhong','=',0];
		//多商户的商品是否参与分红
		if($sysset['fhjiesuanbusiness'] != 1){
			$where[] = ['bid','=',0];
		}
		$ordercount = Db::name('shop_order_goods')->where($where)->group('orderid')->count();
		$orderprice = Db::name('shop_order_goods')->where($where)->sum('real_totalprice');
		return json(['ordercount'=>$ordercount,'orderprice'=>round($orderprice,2)]);
	}
	public function fhjiesuan(){
		$ctime = input('param.ctime');
		if(!$ctime){
			return json(['status'=>0,'msg'=>'请选择时间范围']);
		}
		$ctime = explode(' ~ ',input('param.ctime'));
		$starttime = strtotime($ctime[0]);
		$endtime = strtotime($ctime[1]) + 86400;
		\app\commons\Fenhong::jiesuan(aid,$starttime,$endtime);
		return json(['status'=>1,'msg'=>'结算完成']);
	}
	//转账审核
    public function transferCheck(){
        if(getcustom('pay_transfer')){
            $orderid = input('post.orderid/d');
            $st = input('post.st/d');

            $order = Db::name('shop_order')->where('id',$orderid)->where('aid',aid)->where('bid',bid)->field('id,status')->find();
            if($order['status']!=0){
               return json(['status'=>0,'msg'=>'该订单状态不允许审核']);
            }

            if($st==1){
                $up = Db::name('shop_order')->where('id',$orderid)->where('aid',aid)->where('bid',bid)->update(['transfer_check'=>1]);
                if($up){
                	\app\commons\System::plog('商城订单转账审核驳回'.$orderid);
                	return json(['status'=>1,'msg'=>'审核通过']);
                }else{
                	return json(['status'=>0,'msg'=>'操作失败']);
                }
            }else{
                $up = Db::name('shop_order')->where('id',$orderid)->where('aid',aid)->where('bid',bid)->update(['transfer_check'=>-1]);
                if($up){
                	\app\commons\System::plog('商城订单转账审核通过'.$orderid);
                	return json(['status'=>1,'msg'=>'转账已驳回']);
                }else{
                	return json(['status'=>0,'msg'=>'操作失败']);
                }
            }
        }
    }

    //待发货订单统计
    public function unsendTongji(){
        if(request()->isAjax() || input('param.excel') == 1){
            $page = input('param.page');
            $limit = input('param.limit');
            $type = input('type');
            if(input('param.field') && input('param.order')){
                $order = input('param.field').' '.input('param.order');
            }else{
                $order = 'total_weight desc';
            }
            $where = [];
            $where[] = ['og.aid','=',aid];
            $where[] = ['og.bid','=',bid];
            $where[] = ['og.status','=','1'];
            if($this->mdid){
                $where[] = ['shop_order.mdid','=',$this->mdid];
            }
            if(input('param.proname')){
                $where[] = ['og.name','like','%'.input('param.proname').'%'];
            }
            //按规格统计
            if($type==2){
                $fields = 'og.proid,og.name,og.pic,og.ggname,sum(og.num) num,sum(og.refund_num) refund_num,sg.weight,sp.weight sp_weight,sum(og.totalprice) totalprice,sum(og.total_weight) total_weight';

                $count = 0 + Db::name('shop_order_goods')
                        ->alias('og')
                        ->join('shop_order','shop_order.id=og.orderid')
                        ->join('shop_guige sg','og.ggid=sg.id','left')
                        ->join('shop_product sp','og.proid=sp.id','left')
                        ->fieldRaw('og.proid')
                        ->where($where)
                        ->whereRaw('og.num>og.refund_num')
                        ->group('ggid')->count();
                $list = Db::name('shop_order_goods')->alias('og')
                    ->join('shop_order','shop_order.id=og.orderid')
                    ->join('shop_guige sg','og.ggid=sg.id','left')
                    ->join('shop_product sp','og.proid=sp.id','left')
                    ->fieldRaw($fields)
                    ->where($where)
                    ->whereRaw('og.num>og.refund_num')
                    ->group('ggid')
                    ->page($page,$limit)
                    ->order($order)
                    ->select()
                    ->toArray();
                //echo Db::getLastSql();exit;
                if($page == 1){
                    $totaldata = Db::name('shop_order_goods')
                        ->alias('og')
                        ->join('shop_order','shop_order.id=og.orderid')
                        ->join('shop_guige sg','og.ggid=sg.id','left')
                        ->join('shop_product sp','og.proid=sp.id','left')
                        ->fieldRaw($fields)->where($where)->find();
                }
            }else{
                //按商品统计
                $fields = 'og.proid,og.name,og.pic,og.ggname,sum(og.num) num,sum(og.refund_num) refund_num,sp.weight,sum(og.totalprice) totalprice,sum(og.total_weight) total_weight';

                $count = 0 + Db::name('shop_order_goods')
                        ->alias('og')
                        ->join('shop_order','shop_order.id=og.orderid')
                        ->join('shop_product sp','og.proid=sp.id','left')
                        ->fieldRaw('og.proid')
                        ->where($where)
                        ->whereRaw('og.num>og.refund_num')
                        ->group('proid')->count();
                $list = Db::name('shop_order_goods')->alias('og')
                    ->join('shop_order','shop_order.id=og.orderid')
                    ->join('shop_product sp','og.proid=sp.id','left')
                    ->fieldRaw($fields)
                    ->where($where)
                    ->whereRaw('og.num>og.refund_num')
                    ->group('proid')
                    ->page($page,$limit)
                    ->order($order)
                    ->select()
                    ->toArray();
                //echo Db::getLastSql();exit;
                if($page == 1){
                    $totaldata = Db::name('shop_order_goods')
                        ->alias('og')
                        ->join('shop_order','shop_order.id=og.orderid')
                        ->join('shop_product sp','og.proid=sp.id','left')
                        ->fieldRaw($fields)
                        ->where($where)
                        ->whereRaw('og.num>og.refund_num')
                        ->find();
                }

            }

            foreach($list as $k=>$v){
                //$list[$k]['name'] = $v['name'].'('.$v['ggname'].')';
                //单个重量有规格的取规格数据，没有规格的取产品数据
                $weight = $v['weight'];//单个重量
//                if(empty($weight)){
//                    $weight = $v['sp_weight'];//单个重量
//                }
                $list[$k]['weight'] = $weight;
                //实际销售量 = 总数量 - 商品退款数量
                $num = $v['num'] - $v['refund_num'];
                $list[$k]['num'] = $num;
                //总重量减去已发货的重量
                $total_weight = bcdiv(bcsub($v['total_weight'],$weight*$v['refund_num'],2),1000,2);
                $list[$k]['total_weight'] = $total_weight;
                $list[$k]['ph'] = ($k+1) + ($page-1)*$limit;

            }
            return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$list,'totaldata'=>$totaldata]);
        }
        View::assign('type',input('param.type'));

        return View::fetch();
    }
    //待发货商品统计导出
    public function unsendexcel(){
        set_time_limit(0);
        ini_set('memory_limit', '2000M');
        $type = input('type');
        if(input('param.field') && input('param.order')){
            $order = input('param.field').' '.input('param.order');
        }else{
            $order = 'total_weight desc';
        }
        $where = [];
        $where[] = ['og.aid','=',aid];
        $where[] = ['og.bid','=',bid];
        $where[] = ['og.status','=','1'];
        if($this->mdid){
            $where[] = ['shop_order.mdid','=',$this->mdid];
        }
        if(input('param.proname')){
            $where[] = ['og.name','like','%'.input('param.proname').'%'];
        }
        if($type==2){
            $fields = 'og.proid,og.name,og.pic,og.ggname,sum(og.num) num,sum(og.refund_num) refund_num,sg.weight,sp.weight sp_weight,sum(og.totalprice) totalprice,sum(og.total_weight) total_weight';
            $list = Db::name('shop_order_goods')->alias('og')
                ->join('shop_order','shop_order.id=og.orderid')
                ->join('shop_guige sg','og.ggid=sg.id','left')
                ->join('shop_product sp','og.proid=sp.id','left')
                ->fieldRaw($fields)
                ->where($where)
                ->whereRaw('og.num>og.refund_num')
                ->group('ggid')
                ->order($order)
                ->select()
                ->toArray();
        }else{
            $fields = 'og.proid,og.name,og.pic,og.ggname,sum(og.num) num,sum(og.refund_num) refund_num,sp.weight,sum(og.totalprice) totalprice,sum(og.total_weight) total_weight';
            $list = Db::name('shop_order_goods')->alias('og')
                ->join('shop_order','shop_order.id=og.orderid')
                ->join('shop_product sp','og.proid=sp.id','left')
                ->fieldRaw($fields)
                ->where($where)
                ->whereRaw('og.num>og.refund_num')
                ->group('proid')
                ->order($order)
                ->select()
                ->toArray();
        }

        foreach($list as $k=>$v){
            $list[$k]['ph'] = ($k+1);
            //单个重量有规格的取规格数据，没有规格的取产品数据
            $weight = $v['weight'];//单个重量
//            if(empty($weight)){
//                $weight = $v['sp_weight'];//单个重量
//            }
            $list[$k]['weight'] = $weight;
            //实际销售量 = 总数量 - 商品退款数量
            $num = $v['num'] - $v['refund_num'];
            $list[$k]['num'] = $num;
            $list[$k]['total_weight'] = bcdiv(bcsub($v['total_weight'],$weight*$v['refund_num'],2),1000,2);
        }
        $title = array('排名','商品名称','单个重量(克)','待发货数量','待发货总重量(KG)');
        $data = [];
        foreach($list as $k=>$vo){
            $data[] = [
                $vo['ph'],
                $vo['name'],
                $vo['weight'],
                $vo['num'],
                $vo['total_weight'],
            ];
        }

        $this->export_excel($title,$data);
    }

    //刷新旺店通物流数据
    public function refreshWdtExpress(){
        if(getcustom('erp_wangdiantong')) {
            $c = new \app\customs\Wdt(aid, bid);
            $c->logisticsQuery();
            return json(['status' => 1, 'msg' => '刷新成功']);
        }
    }
}
