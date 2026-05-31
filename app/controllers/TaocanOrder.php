<?php
//custom_file(taocan_product)
// +----------------------------------------------------------------------
// | 商城-商品订单
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\Log;
use think\facade\View;
use think\facade\Db;
class TaocanOrder extends Common
{
    public function initialize(){
        ll(PRE_URL,'auto_day');
        parent::initialize();
    }
	//订单列表
    public function index(){
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
			}else{
				$where[] = ['order.bid','=',bid];
			}
			if($this->mdid){
				$where[] = ['order.mdid','=',$this->mdid];
			}
			if(input('?param.ogid')){
				if(input('param.ogid')==''){
					$where[] = ['1','=',0];
				}else{
					$ids = Db::name('taocan_order_goods')->where('id','in',input('param.ogid'))->column('orderid');
					$where[] = ['order.id','in',$ids];
				}
			}
			if(input('param.mid')){
			    $where[] = ['order.mid','=',input('param.mid')];
            } 
			if(input('param.orderid')) $where[] = ['order.id','=',input('param.orderid')];
            if(input('param.freight_id')) $where[] = ['order.freight_id','=',input('param.freight_id')];
			if(input('param.proname')) $where[] = ['order.proname','like','%'.input('param.proname').'%'];
			if(input('param.ordernum')) $where[] = ['order.ordernum','like','%'.input('param.ordernum').'%'];
            if(input('param.nickname')) $where[] = ['member.nickname|member.realname','like','%'.input('param.nickname').'%'];
            if(input('param.linkman')) $where[] = ['order.linkman|order.tel','like','%'.input('param.linkman').'%'];
			if(input('param.tel')) $where[] = ['order.tel','like','%'.input('param.tel').'%'];
			if(input('param.proid')){
				$orderids = Db::name('taocan_order_goods')->where('aid',aid)->where('proid',input('param.proid'))->column('orderid');
				$where[] = ['order.id','in',$orderids];
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
					$orderids = Db::name('taocan_order_goods')->where('aid',aid)->where('proid',$keyword)->column('orderid');
					$where[] = ['order.id','in',$orderids];
				}elseif($keyword_type == 7){ //商品名称
					$orderids = Db::name('taocan_order_goods')->where('aid',aid)->where('name','like','%'.$keyword.'%')->column('orderid');
					$where[] = ['order.id','in',$orderids];
				}elseif($keyword_type == 8){ //商品编码
					$orderids = Db::name('taocan_order_goods')->where('aid',aid)->where('procode','like','%'.$keyword.'%')->column('orderid');
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

			$count = 0 + Db::name('taocan_order')->alias('order')->leftJoin('member member','member.id=order.mid')->where($where)->count();
			//echo M()->_sql();
			$list = Db::name('taocan_order')->alias('order')->field('order.*')->leftJoin('member member','member.id=order.mid')->where($where)->page($page,$limit)->order($order)->select()->toArray();

			foreach($list as $k=>$vo){
                // 订单添加备注信息
                $formfield = Db::name('freight')->where('id',$vo['freight_id'])->find();
                $formdataSet = json_decode($formfield['formdata'],true);
                foreach($formdataSet as $k1=>$v){
                    if($v['val1'] == '备注'){
                        $message =Db::name('freight_formdata')->where('type','taocan_order')->where('orderid',$vo['id'])->value('form'.$k1);
                        $value = explode('^_^',$message);
                        if($value[1] !== ''){
                            $list[$k]['message'] = $value[1];
                        }
                        break;
                    }
                }

				$member = Db::name('member')->where('id',$vo['mid'])->find();
				$oglist = Db::name('taocan_order_goods')->where('aid',aid)->where('orderid',$vo['id'])->select()->toArray();
				$goodsdata=array();
				foreach($oglist as $og){
				    $grstr = '';
				    
                    $ogremark = '';
                    if($og['gtype']==1){
                        $ogremark = '<span style="color:#f00;">【赠品】</span>';
                    }
					$goodshtml = '<div style="font-size:12px;float:left;clear:both;margin:1px 0">'.
						'<div class="table-imgbox"><img lay-src="'.$og['pic'].'" src="'.PRE_URL.'/static/admin/layui/css/modules/layer/default/loading-2.gif"></div>'.
						'<div style="float: left;width:180px;margin-left: 10px;white-space:normal;line-height:16px;">'.
							'<div style="width:100%;min-height:25px;max-height:32px;overflow:hidden">'.$og['gg_proname'].$ogremark.'</div>'.
							'<div style="padding-top:0px;color:#f60"><span style="color:#888">'.$og['ggname'].'</span></div>'.$grstr;
                    $goodshtml.='<div style="padding-top:0px;color:#f60;">￥'.$og['sell_price'].' × '.$og['num'].'</div>';
					
					$goodshtml.='</div>';
					$goodshtml.='</div>';
					
                    $goodsdata[] = $goodshtml;
				}
				$list[$k]['goodsdata'] = implode('',$goodsdata);
				if($vo['bid'] > 0){
					$list[$k]['bname'] = Db::name('business')->where('aid',aid)->where('id',$vo['bid'])->value('name');
				}else{
					$list[$k]['bname'] = '平台自营';
				}
                $list[$k]['refundCount'] = 0;
                $list[$k]['payorder'] = [];
                if($vo['paytypeid'] == 5) {
                    $list[$k]['payorder'] = Db::name('payorder')->where('id',$vo['payorderid'])->where('aid',aid)->find();
                }
				$list[$k]['nickname'] = $member['nickname'];
				$list[$k]['headimg'] = $member['headimg'];
				$list[$k]['m_remark'] = $member['remark'];
				$list[$k]['platform'] = getplatformname($vo['platform']);
                $list[$k]['yuding_type'] = $vo['yuding_type']??0;
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$list]);
		}

		$peisong_set = Db::name('peisong_set')->where('aid',aid)->find();
		if($peisong_set['status']==1 && bid>0 && $peisong_set['businessst']==0 && $peisong_set['make_status']==0) $peisong_set['status'] = 0;
        $freight = Db::name('freight')->where('aid',aid)->where('bid',bid)->select()->toArray();
		
		$adminset = Db::name('admin_set')->where('aid',aid)->find();
		$shopset = Db::name('shop_sysset')->where('aid',aid)->find();
        View::assign('freight',$freight);
		View::assign('peisong_set',$peisong_set);
		View::assign('express_data',express_data(['aid'=>aid,'bid'=>bid]));
		View::assign('adminset',$adminset);
		View::assign('shopset',$shopset);
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
        $page = input('param.page');
        $limit = input('param.limit');
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
		
		if(input('?param.transfer_check') && input('param.transfer_check')!==''){
			$where[] = ['order.paytypeid','=',5];
			$where[] = ['order.transfer_check','=',input('param.transfer_check')];
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
				$orderids = Db::name('taocan_order_goods')->where('aid',aid)->where('proid',$keyword)->column('orderid');
				$where[] = ['order.id','in',$orderids];
			}elseif($keyword_type == 7){ //商品名称
				$orderids = Db::name('taocan_order_goods')->where('aid',aid)->where('name','like','%'.$keyword.'%')->column('orderid');
				$where[] = ['order.id','in',$orderids];
			}elseif($keyword_type == 8){ //商品编码
				$orderids = Db::name('taocan_order_goods')->where('aid',aid)->where('procode','like','%'.$keyword.'%')->column('orderid');
				$where[] = ['order.id','in',$orderids];
			}elseif($keyword_type == 9){ //核销员
				$orderids = Db::name('hexiao_order')->where('aid',aid)->where('type','taocan')->where('remark','like','%'.$keyword.'%')->column('orderid');
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
			$where[] = Db::raw("order.id in (select orderid from ".table_name('taocan_order_goods')." where parent1={$fxmid} or parent2={$fxmid} or parent3={$fxmid})");
		}

		$list = Db::name('taocan_order')->alias('order')->field('order.*')->leftJoin('member member','member.id=order.mid')->where($where)->order($order)->page($page,$limit)->select()->toArray();
        $count = Db::name('taocan_order')->alias('order')->field('order.*')->leftJoin('member member','member.id=order.mid')->where($where)->count();

        //学校学生信息处理
        $need_school = 0;
       

		
			$bArr = Db::name('business')->where('aid',aid)->column('name','id');
			if(!$bArr) $bArr = [];
			$bArr['0'] = '自营';
            $title1 = array('订单号','openid','来源','所属商家','下单人','姓名','电话','收货地址','商品名称','商品编码','规格','数量','退款数量','总价','单价','成本','折扣金额','实付款','支付方式','配送方式','卡密','配送/提货时间','运费','积分抵扣','满额立减','优惠券优惠','状态','退款状态','退款金额','下单时间','付款时间','发货时间','完成时间','快递信息');
           
            $title2 = array('表单信息','商家备注','核销员','核销门店','佣金总额','一级佣金','会员信息','二级佣金','会员信息','三级佣金','会员信息');
            $title = array_merge($title1,$title2);
			$data = [];
			foreach($list as $k=>$vo){

				$status='';
				$refund_status = $vo['refund_status'];
				$refund_money = $vo['refund_money'];
                switch ($refund_status){
                    case 0:
                        $refund_status = '';
                        break;
                    case 1:
                        $refund_status = '退款待审核';
                        break;
                    case 2:
                        $refund_status = '已退款';
                        break;
                    case 3:
                        $refund_status = '退款驳回';
                        break;
                    case 4:
                        $refund_status = '审核通过，待退货';
                        break;
                    default:
                        $refund_status = '状态未找到';
                        break;

                }
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
                $allcolumn = false;
				
				$member = Db::name('member')->where('id',$vo['mid'])->find();
				$oglist = Db::name('taocan_order_goods')->where('orderid',$vo['id'])->select()->toArray();
				//$xm=array();
				foreach($oglist as $k2=>$og){
                    $ogremark = '';
                    if($og['gtype']==1){
                        $ogremark = '【赠品】';
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
					$vo['formdata'] = \app\models\Freight::getformdata($vo['id'],'taocan_order');
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
						$hexiao_order = Db::name('hexiao_order')->where('aid',aid)->where('orderid',$vo['id'])->where('type','taocan')->find();
						if($hexiao_order){
							$hexiao_order['uname'] = Db::name('admin_user')->where('id',$hexiao_order['uid'])->value('un');
							$hexiao_order['mendian'] = Db::name('mendian')->where('id',$vo['mdid'])->value('name');
						}
					}
					$paytype = $vo['paytype'];
					
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
							$og['gg_proname'].$ogremark,
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
							$og['gg_proname'].$ogremark,
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
			}
        return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data,'title'=>$title]);
			$this->export_excel($title,$data);
	}
	//订单详情
	public function getdetail(){
		$orderid = input('param.orderid');
		if(bid != 0){
			$order = Db::name('taocan_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->find();
		}else{
			$order = Db::name('taocan_order')->where('aid',aid)->where('id',$orderid)->find();
		}
        $order['school_info'] = '';
		if($order['coupon_rid']){
			$couponrecord = Db::name('coupon_record')->where('id',$order['coupon_rid'])->find();
			$couponnames = Db::name('coupon_record')->where('id','in',$order['coupon_rid'])->column('couponname');
			$couponnames = implode('，',$couponnames);
		}else{
			$couponrecord = false;
			$couponnames = '';
		}
		$oglist = Db::name('taocan_order_goods')->where('aid',aid)->where('orderid',$orderid)->select()->toArray();
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
		}
		$comdata['parent1']['money'] = round($comdata['parent1']['money'],2);
		$comdata['parent2']['money'] = round($comdata['parent2']['money'],2);
		$comdata['parent3']['money'] = round($comdata['parent3']['money'],2);

		$order['formdata'] = \app\models\Freight::getformdata($order['id'],'taocan_order');
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
        if($order['freight_type']==11 && $order['freight_content']){
            $order['freight_content'] = json_decode($order['freight_content'],true);
        }else{
            $order['freight_content'] = [];
        }
		$miandanst = Db::name('admin_set')->where('aid',aid)->value('miandanst');
		if(bid==0 && $miandanst==1 && in_array('wx',$this->platform) && ($member['wxopenid'] || $member['unionid'])){ //可以使用小程序物流助手发货
			$canmiandan = 1;
		}else{
			$canmiandan = 0;
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
		return json(['order'=>$order,'couponrecord'=>$couponrecord,'couponnames'=>$couponnames,'oglist'=>$oglist,'member'=>$member,'comdata'=>$comdata,'canmiandan'=>$canmiandan,'checkmember'=>$checkmember,'payorder' => $payorder]);
	}
	
	//设置备注
	public function setremark(){
		$orderid = input('post.orderid/d');
		$content = input('post.content');
		if(bid == 0){
			Db::name('taocan_order')->where('aid',aid)->where('id',$orderid)->update(['remark'=>$content]);
		}else{
			Db::name('taocan_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->update(['remark'=>$content]);
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

        $order = Db::name('taocan_order')->where($where)->where('id',$orderid)->find();
        if($newprice > $order['totalprice']) return json(['status'=>0,'msg'=>'只能优惠不可加价，加价可通过下单其他商品补差价']);
        $ordernumArr = explode('_',$order['ordernum']);
        if($ordernumArr[1]) $newordernum .= '_'.$ordernumArr[1];
        $discount_money_admin = $order['totalprice']-$newprice;//管理员优惠金额（正数）
        Db::name('taocan_order')->where($where)->where('id',$orderid)->update(['totalprice'=>$newprice,'ordernum'=>$newordernum,'discount_money_admin'=>$discount_money_admin]);
        Db::name('taocan_order_goods')->where($where)->where('orderid',$orderid)->update(['ordernum'=>$newordernum]);
        //订单商品价格也需同步修改，涉及商家结算
        $oglist = Db::name('taocan_order_goods')->where($where)->where('orderid',$orderid)->select()->toArray();
        foreach ($oglist as $og){
            $rate = $newprice/$order['totalprice'];
            $og['real_totalprice'] = $rate*$og['real_totalprice'];
            if(!is_null($og['business_total_money'])) {
                $og['business_total_money'] = $rate*$og['business_total_money'];
            }
            Db::name('taocan_order_goods')->where('id',$og['id'])->where('orderid',$orderid)->update($og);
        }

		$payorderid = Db::name('taocan_order')->where('aid',aid)->where('id',$orderid)->value('payorderid');

		\app\models\Payorder::updateorder($payorderid,$newordernum,$newprice,$orderid);
		\app\commons\System::plog('商城订单改价格'.$orderid.'，原价格:'.$order['totalprice'].'，新价格:'.$newprice);
		return json(['status'=>1,'msg'=>'修改完成']);
	}
	//关闭订单
	public function closeOrder(){
		$orderid = input('post.orderid/d');
		if(bid == 0){
			$order = Db::name('taocan_order')->where('id',$orderid)->where('aid',aid)->find();
		}else{
			$order = Db::name('taocan_order')->where('id',$orderid)->where('aid',aid)->where('bid',bid)->find();
		}
		if(!$order || $order['status']!=0){
			return json(['status'=>0,'msg'=>'关闭失败,订单状态错误']);
		}
		//加库存
		$oglist = Db::name('taocan_order_goods')->where('aid',aid)->where('orderid',$orderid)->select()->toArray();
		foreach($oglist as $og){
			Db::name('shop_guige')->where('aid',aid)->where('id',$og['ggid'])->update(['stock'=>Db::raw("stock+".$og['num']),'sales'=>Db::raw("sales-".$og['num'])]);
			Db::name('shop_product')->where('aid',aid)->where('id',$og['proid'])->update(['stock'=>Db::raw("stock+".$og['num']),'sales'=>Db::raw("sales-".$og['num'])]);

		}
		
		//优惠券抵扣的返还
		if($order['coupon_rid']){
			Db::name('coupon_record')->where('aid',aid)->where('mid',$order['mid'])->where('id','in',$order['coupon_rid'])->update(['status'=>0,'usetime'=>'']);
		}



		$rs = Db::name('taocan_order')->where('id',$orderid)->where('aid',aid)->update(['status'=>4]);
		Db::name('taocan_order_goods')->where('orderid',$orderid)->where('aid',aid)->update(['status'=>4]);
		\app\commons\System::plog('商城订单关闭订单'.$orderid);
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//改为已支付
	public function ispay(){
		if(bid > 0) showmsg('无权限操作');
		$orderid = input('post.orderid/d');
		if(bid == 0){
			Db::name('taocan_order')->where('aid',aid)->where('id',$orderid)->update(['status'=>1,'paytime'=>time(),'paytype'=>'后台支付']);
		}else{
			Db::name('taocan_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->update(['status'=>1,'paytime'=>time(),'paytype'=>'后台支付']);
		}
		\app\models\Payorder::taocan_pay($orderid);

		//Db::name('taocan_order_goods')->where('orderid',$orderid)->where('aid',aid)->where('bid',bid)->update(['status'=>1]);
		////奖励积分
		//$order = Db::name('taocan_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->find();
		//if($order['givescore'] > 0){
		//	\app\commons\Member::addscore(aid,$order['mid'],$order['givescore'],'购买产品奖励'.t('积分'));
		//}
		\app\commons\System::plog('商城订单改为已支付'.$orderid);

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
			Db::name('taocan_order')->where('aid',aid)->where('id',$orderid)->update(['balance_pay_status'=>1]);
		}else{
			Db::name('taocan_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->update(['balance_pay_status'=>1]);
		}
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//发货
	public function sendExpress(){
		$orderid = input('post.orderid/d');
		if(bid == 0){
			$order = Db::name('taocan_order')->where('aid',aid)->where('id',$orderid)->find();
		}else{
			$order = Db::name('taocan_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->find();
		}


		if($order['status']!=1 && $order['status']!=2){
			return json(['status'=>0,'msg'=>'该订单状态不允许发货']);
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

			$oglist = Db::name('taocan_order_goods')->where('orderid',$orderid)->where('aid',aid)->select()->toArray();
			if(count($oglist) > 1 && $express_ogidsAll){
				foreach($oglist as $og){
					if(!in_array($og['id'],$express_ogidsAll)){
						$express_isbufen = 1;
					}
				}
			}
		}
		
		if($order['status']!=1){ //修改物流信息
			Db::name('taocan_order')->where('aid',aid)->where('id',$orderid)->update(['express_com'=>$express_com,'express_no'=>$express_no,'express_ogids'=>$express_ogids,'express_content'=>$express_content,'express_isbufen'=>$express_isbufen]);
			return json(['status'=>1,'msg'=>'操作成功']);
		}

		Db::name('taocan_order')->where('aid',aid)->where('id',$orderid)->update(['express_com'=>$express_com,'express_no'=>$express_no,'express_ogids'=>$express_ogids,'express_content'=>$express_content,'send_time'=>time(),'status'=>2,'express_isbufen'=>$express_isbufen]);
		Db::name('taocan_order_goods')->where('orderid',$orderid)->where('aid',aid)->update(['status'=>2]);
		
		if($order['fromwxvideo'] == 1){
			\app\commons\Wxvideo::deliverysend($orderid);
		}
        //发货信息录入 微信小程序+微信支付
        if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
            \app\commons\Order::wxShipping(aid,$order,'taocan',['express_com'=>$express_comArr[0],'express_no'=>$express_noArr[0]]);
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
	//查物流
	public function getExpress(){
		$orderid = input('post.orderid/d');
		$order = Db::name('taocan_order')->where('id',$orderid)->where('aid',aid)->find();
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
					$oglist = Db::name('taocan_order_goods')->where('aid',aid)->where('id','in',$order['express_ogids'])->select()->toArray();
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
				$oglist = Db::name('taocan_order_goods')->where('aid',aid)->where('id','in',$order['express_ogids'])->select()->toArray();
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
	//退款审核
	public function refundCheck(){
		$orderid = input('post.orderid/d');
		$st = input('post.st/d');
		$remark = input('post.remark');
		if(bid == 0){
			$order = Db::name('taocan_order')->where('id',$orderid)->where('aid',aid)->find();
		}else{
			$order = Db::name('taocan_order')->where('id',$orderid)->where('aid',aid)->where('bid',bid)->find();
		}
		if(!$order) return json(['status'=>1,'msg'=>'订单不存在']);
		if($st==2){
			Db::name('taocan_order')->where('id',$orderid)->where('aid',aid)->update(['refund_status'=>3,'refund_checkremark'=>$remark]);
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
				$rs = \app\commons\Order::refund($order,$order['refund_money'],$order['refund_reason']);
				if($rs['status']==0){
					return json(['status'=>0,'msg'=>$rs['msg']]);
				}
			}

			Db::name('taocan_order')->where('id',$orderid)->where('aid',aid)->update(['status'=>4,'refund_status'=>2,'refund_checkremark'=>$remark]);
			Db::name('taocan_order_goods')->where('orderid',$orderid)->where('aid',aid)->update(['status'=>4]);

			//积分抵扣的返还
			if($order['scoredkscore'] > 0){
				\app\commons\Member::addscore(aid,$order['mid'],$order['scoredkscore'],'订单退款返还');
			}
			if($order['givescore2'] > 0){
                \app\commons\Member::addscore(aid,$order['mid'],-$order['givescore2'],'订单退款扣除');
            }
            //扣除消费赠送积分
            \app\commons\Member::decscorein(aid,'taocan',$order['id'],$order['ordernum'],'订单退款扣除消费赠送');
			//优惠券抵扣的返还
			if($order['coupon_rid']){
				Db::name('coupon_record')->where('aid',aid)->where('mid',$order['mid'])->where('id','in',$order['coupon_rid'])->update(['status'=>0,'usetime'=>'']);
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
        $detail = Db::name('taocan_order')->where('id',input('param.orderid/d'))->where('aid',aid)->find();
        if(!$detail)
            return json(['status'=>0,'msg'=>'订单不存在']);
        $detail['createtime'] = $detail['createtime'] ? date('Y-m-d H:i:s',$detail['createtime']) : '';
        $detail['collect_time'] = $detail['collect_time'] ? date('Y-m-d H:i:s',$detail['collect_time']) : '';
        $detail['paytime'] = $detail['paytime'] ? date('Y-m-d H:i:s',$detail['paytime']) : '';
        $detail['refund_time'] = $detail['refund_time'] ? date('Y-m-d H:i:s',$detail['refund_time']) : '';
        $detail['send_time'] = $detail['send_time'] ? date('Y-m-d H:i:s',$detail['send_time']) : '';

        $refundMoneySum = 0;

        $canRefundNum = 0;
        $totalNum = 0;
        $returnTotalprice = 0;
        $prolist = Db::name('taocan_order_goods')->where('orderid',$detail['id'])->select()->toArray();
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
			$order = Db::name('taocan_order')->where('id',$orderid)->where('aid',aid)->find();
		}else{
			$order = Db::name('taocan_order')->where('id',$orderid)->where('aid',aid)->where('bid',bid)->find();
		}
		if(!$order) return json(['status'=>0,'msg'=>'订单不存在']);
		if($order['status']!=1 && $order['status']!=2){
			return json(['status'=>0,'msg'=>'该订单状态不允许退款']);
		}


        try {
            Db::startTrans();
            //新退款 202108
            $post = input('post.');
            $orderid = intval($post['orderid']);
            $money = floatval($post['money']);
            $order = Db::name('taocan_order')->where('aid', aid)->where('id', $orderid)->find();
            if (!$order || ($order['status'] != 1 && $order['status'] != 2)) {
                return json(['status' => 0, 'msg' => '订单状态不符合退款要求']);
            }
            if ($money < 0 || $money > $order['totalprice']) {
                return json(['status' => 0, 'msg' => '退款金额有误']);
            }


            $totalRefundNum = 0;
            $prolist = Db::name('taocan_order_goods')->where('orderid', $orderid)->select();
            $newKey = 'id';
            $prolist = $prolist->dictionary(null, $newKey);
            $canRefundNum = 0;
			$totalprice = $order['totalprice'];
			if($order['balance_price'] > 0 && $order['balance_pay_status'] == 0){
				$totalprice = $totalprice - $order['balance_price'];
			}
            $refund_money = $totalprice;

            if ($money > $refund_money) {
                return json(['status' => 0, 'msg' => '退款金额超出范围']);
            }
            if($refund_money > 0) {
                $rs = \app\commons\Order::refund($order,$refund_money,$reason);
                if($rs['status']==0){
                    return json(['status'=>0,'msg'=>$rs['msg']]);
                }
            }

            //恢复库存 删除销量
            Db::name('taocan_product')->where('aid',aid)->where('id',$order['proid'])->update(['stock'=>Db::raw("stock+".$order['beishu']),'sales'=>Db::raw("sales-".$order['beishu'])]);
            foreach ($prolist as $item) {
                Db::name('shop_guige')->where('aid', aid)->where('id', $prolist[$item['ogid']]['ggid'])->update(['stock' => Db::raw("stock+" . $item['num']), 'sales' => Db::raw("sales-" . $item['num'])]);
                Db::name('shop_product')->where('aid', aid)->where('id', $prolist[$item['ogid']]['proid'])->update(['stock' => Db::raw("stock+" . $item['num']), 'sales' => Db::raw("sales-" . $item['num'])]);

            }
            //整单全部退时 返还积分和优惠券
            Db::name('taocan_order')->where('id', $order['id'])->where('aid', aid)->update(['status' => 4, 'refund_status' => 2, 'refund_money' => $refund_money]);
            Db::name('taocan_order_goods')->where('orderid', $order['id'])->where('aid', aid)->update(['status' => 4]);

            //查询后台是否开启退还已使用的优惠券
            $return_coupon = Db::name('shop_sysset')->where('aid',aid)->value('return_coupon');
            //优惠券抵扣的返还
            if ($return_coupon && $order['coupon_rid']) {
                Db::name('coupon_record')->where('aid', aid)->where('mid', $order['mid'])->where('id', 'in', $order['coupon_rid'])->update(['status' => 0, 'usetime' => '']);
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
			$order = Db::name('taocan_order')->where('aid',aid)->where('id',$orderid)->find();
		}else{
			$order = Db::name('taocan_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->find();
		}
        if(!$order || !in_array($order['status'], [1,2]) || $order['freight_type'] != 1){
            return json(['status'=>0,'msg'=>'订单状态不符合核销收货要求']);
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
            $data['type'] = 'taocan';
            $data['createtime'] = time();
            $data['remark'] = '核销员['.$this->user['un'].']核销';
            $data['mdid']   = empty($this->user['mdid'])?0:$this->user['mdid'];
            Db::name('hexiao_order')->insert($data);

            $rs = \app\commons\Order::collect($order, 'taocan', $this->user['mid']);
            if($rs['status']==0) return $rs;
            Db::name('taocan_order')->where('aid',aid)->where('id',$orderid)->update(['status'=>3,'collect_time'=>time()]);
            Db::name('taocan_order_goods')->where('aid',aid)->where('orderid',$orderid)->update(['status'=>3,'endtime'=>time()]);
            \app\commons\Member::uplv(aid,$order['mid']);
            //发货信息录入 微信小程序+微信支付
            if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
                \app\commons\Order::wxShipping(aid,$order);
            }
            Db::commit();
            \app\commons\System::plog('商城订单核销确认收货'.$orderid);

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
		$order = Db::name('taocan_order')->where('aid',aid)->where('id',$orderid)->find();
		if(bid != 0 && $order['paytypeid'] != 4){
			return json(['status'=>0,'msg'=>'无操作权限']);
		}
		if(!$order || ($order['status']!=2)){
			return json(['status'=>0,'msg'=>'订单状态不符合收货要求']);
		}

        try {
            Db::startTrans();
            $rs = \app\commons\Order::collect($order, 'taocan', $this->user['mid']);
            if($rs['status']==0) return $rs;
            Db::name('taocan_order')->where('aid',aid)->where('id',$orderid)->update(['status'=>3,'collect_time'=>time()]);
            Db::name('taocan_order_goods')->where('aid',aid)->where('orderid',$orderid)->update(['status'=>3,'endtime'=>time()]);

            \app\commons\Member::uplv(aid,$order['mid']);
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

	//删除
	public function del(){
		if(input('post.id')){
			$ids = [input('post.id/d')];
		}else{
			$ids = input('post.ids/a');
		}
		foreach($ids as $id){
			if(bid == 0){
				Db::name('taocan_order')->where('aid',aid)->where('id',$id)->delete();
				Db::name('taocan_order_goods')->where('aid',aid)->where('orderid',$id)->delete();
			}else{
				Db::name('taocan_order')->where('aid',aid)->where('bid',bid)->where('id',$id)->delete();
				Db::name('taocan_order_goods')->where('aid',aid)->where('bid',bid)->where('orderid',$id)->delete();
			}
			Db::name('invoice')->where('aid',aid)->where('bid',bid)->where('order_type','taocan')->where('orderid',$id)->delete();
			\app\commons\System::plog('商城订单删除'.$id);
		}
		return json(['status'=>1,'msg'=>'删除成功']);
	}

	//送货单
	public function shd(){
		$orderid = input('param.id/d');
		$info = Db::name('taocan_order')->where('aid',aid)->where('id',$orderid)->find();
		if(!$info || (bid !=0 && $info['bid'] != bid)) showmsg('订单不存在');
		$ordergoods = Db::name('taocan_order_goods')->where('aid',aid)->where('orderid',$orderid)->select()->toArray();
		$totalnum = 0;
		$order_goods = [];
		foreach($ordergoods as $k=>$v){
			if($v['num']>$v['refund_num']){
				$v['num'] = $v['num']-$v['refund_num'];
	            $remark = '';
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
		$order_goods2 = [];

		if(count($order_goods) < 9){
    		for($i=0;$i<9;$i++){
    			$order_goods2[] = $order_goods[$i];
    		}
		}else{
		    $order_goods2 = $order_goods;
		}

			$order_goods2[] = ['type'=>'yf'];
			$order_goods2[] = ['type'=>'totalprice'];
			$order_goods2[] = ['type'=>'totalprice2'];

		//买家留言
        $order_goods2[] = ['type'=>'remark'];
		$order_goods3 = array_chunk($order_goods2,13);



		$info['totalprice2'] = num_to_rmb($info['totalprice']);
		if($info['freight_type'] == 11){
			$info['freight_content'] = json_decode($info['freight_content'],true);
		}
		if($info['bid'] == 0){
			$bname = Db::name('admin_set')->where('aid',aid)->value('name');
		}else{
			$bname = Db::name('business')->where('id',$info['bid'])->value('name');
		}

        $field = 'shipping_pagetitle,shipping_pagenum,shipping_linenum';
		if(bid>0){
			$sysset = Db::name('business')->where('id',bid)->field($field)->find();
		}else{
			$sysset = Db::name('shop_sysset')->where('aid',aid)->field($field)->find();
		}
		View::assign('bname',$bname);
        View::assign('shipping_pagetitle',$sysset['shipping_pagetitle']);
		View::assign('info',$info);
		View::assign('order_goods3',$order_goods3);
		View::assign('discount',$discount);
		View::assign('express_data',express_data(['aid'=>aid,'bid'=>bid]));
		return View::fetch();
	}

}
