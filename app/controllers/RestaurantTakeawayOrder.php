<?php


// +----------------------------------------------------------------------
// | 餐饮外卖-商品订单
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;
class RestaurantTakeawayOrder extends Common
{
	//订单列表
    public function index(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
				$order = 'id desc';
			}
			$where = [];
			$where[] = ['aid','=',aid];
			if(bid==0){
				if(input('param.bid')){
					$where[] = ['bid','=',input('param.bid')];
				}elseif(input('param.showtype')==2){
					$where[] = ['bid','<>',0];
                }elseif(input('param.showtype')=='all'){
                    $where[] = ['bid','>=',0];
				}else{
					$where[] = ['bid','=',0];
				}
			}else{
				$where[] = ['bid','=',bid];
			}
			if($this->mdid){
				$where[] = ['mdid','=',$this->mdid];
			}
			if(input('?param.ogid')){
				if(input('param.ogid')==''){
					$where[] = ['1','=',0];
				}else{
					$ids = Db::name('restaurant_takeaway_order_goods')->where('id','in',input('param.ogid'))->column('orderid');
					$where[] = ['id','in',$ids];
				}
			}
			if(input('param.orderid')) $where[] = ['id','=',input('param.orderid')];
			if(input('param.mid')) $where[] = ['mid','=',input('param.mid')];
			if(input('param.proname')) $where[] = ['proname','like','%'.input('param.proname').'%'];
			if(input('param.ordernum')) $where[] = ['ordernum','like','%'.input('param.ordernum').'%'];
			if(input('param.linkman')) $where[] = ['linkman','like','%'.input('param.linkman').'%'];
			if(input('param.tel')) $where[] = ['tel','like','%'.input('param.tel').'%'];
			if(input('param.ctime') ){
				$ctime = explode(' ~ ',input('param.ctime'));
				$where[] = ['createtime','>=',strtotime($ctime[0])];
				$where[] = ['createtime','<',strtotime($ctime[1])];
			}
			if(input('?param.status') && input('param.status')!==''){
				if(input('param.status') == 5){
					$where[] = ['refund_status','=',1];
				}elseif(input('param.status') == 6){
					$where[] = ['refund_status','=',2];
				}elseif(input('param.status') == 7){
					$where[] = ['refund_status','=',3];
				}else{
					$where[] = ['status','=',input('param.status')];
				}
			}
			$count = 0 + Db::name('restaurant_takeaway_order')->where($where)->count();
			//echo M()->_sql();
			$list = Db::name('restaurant_takeaway_order')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			foreach($list as $k=>$vo){
				$member = Db::name('member')->where('id',$vo['mid'])->find();
                $list[$k]['nickname'] = $member['nickname'];
                $list[$k]['headimg'] = $member['headimg'];
				$oglist = Db::name('restaurant_takeaway_order_goods')->where('aid',aid)->where('orderid',$vo['id'])->select()->toArray();
				$goodsdata=array();
				foreach($oglist as $og){
                    $sell_price = $og['sell_price'];
                    $ggname =  $og['ggname'];
				    $html = '<div style="font-size:12px;float:left;clear:both;margin:1px 0">'.
						'<img src="'.$og['pic'].'" style="max-width:60px;float:left">'.
						'<div style="float: left;width:160px;margin-left: 10px;white-space:normal;line-height:16px;">'.
							'<div style="width:100%;min-height:25px;max-height:32px;overflow:hidden">'.$og['name'].'</div>'.
							'<div style="padding-top:0px;color:#f60"><span style="color:#888">'.$ggname.'</span></div>'.
							'<div style="padding-top:0px;color:#f60;">￥'.$sell_price.' × '.$og['num'].'</div>';
							$html .= '</div></div>';
					$goodsdata[] = $html;
				}
				$list[$k]['goodsdata'] = implode('',$goodsdata);
				if($vo['bid'] > 0){
					$list[$k]['bname'] = Db::name('business')->where('aid',aid)->where('id',$vo['bid'])->value('name');
				}else{
					$list[$k]['bname'] = '平台自营';
				}
				if($vo['express_type'] == 'express_wx') {
                    $list[$k]['express_order'] = \db('express_wx_order')->where('aid',aid)->where('bid',bid)->where('id',$vo['express_no'])->find();
                    //查状态
                    $orderStatusEnd = \app\customs\ExpressWx::$orderStatusEnd;
                    if($list[$k]['express_order']['order_status'] && !in_array($list[$k]['express_order']['order_status'],$orderStatusEnd)) {
                        $rs = \app\customs\ExpressWx::getOrder($list[$k]['express_order']);
                        if($rs['status'] == 1) {
                            $list[$k]['express_order'] = $rs['order'];
                        };
                    }
                }
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$list]);
		}
		$machinelist = Db::name('wifiprint_set')->where('aid',aid)->where('status',1)->where('bid',bid)->select()->toArray();
		$hasprint = 0;
		if($machinelist){
			$hasprint = 1;
		}
		$peisong_set = Db::name('peisong_set')->where('aid',aid)->find();
		if($peisong_set['status']==1 && bid>0 && $peisong_set['businessst']==0 && $peisong_set['make_status']==0 && $peisong_set['express_wx_status']==0) $peisong_set['status'] = 0;
		View::assign('peisong_set',$peisong_set);
		View::assign('hasprint',$hasprint);
		View::assign('express_data',express_data(['aid'=>aid,'bid'=>bid]));
        $this->defaultSet();
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
		$where[] = ['aid','=',aid];
		$where[] = ['bid','=',bid];
		if($this->mdid){
			$where[] = ['mdid','=',$this->mdid];
		}
		if(input('param.mid')) $where[] = ['mid','=',input('param.mid')];
		if(input('param.proname')) $where[] = ['proname','like','%'.input('param.proname').'%'];
		if(input('param.ordernum')) $where[] = ['ordernum','like','%'.input('param.ordernum').'%'];
		if(input('param.linkman')) $where[] = ['linkman','like','%'.input('param.linkman').'%'];
		if(input('param.tel')) $where[] = ['tel','like','%'.input('param.tel').'%'];
		if(input('param.ctime') ){
			$ctime = explode(' ~ ',input('param.ctime'));
			$where[] = ['createtime','>=',strtotime($ctime[0])];
			$where[] = ['createtime','<',strtotime($ctime[1]) + 86400];
		}
		if(input('?param.status') && input('param.status')!==''){
			if(input('param.status') == 5){
				$where[] = ['refund_status','=',1];
			}elseif(input('param.status') == 6){
				$where[] = ['refund_status','=',2];
			}elseif(input('param.status') == 7){
				$where[] = ['refund_status','=',3];
			}else{
				$where[] = ['status','=',input('param.status')];
			}
		}
		$list = Db::name('restaurant_takeaway_order')->where($where)->order($order)->page($page,$limit)->select()->toArray();
        $count = Db::name('restaurant_takeaway_order')->where($where)->count();
		$title = array('订单号','下单人','商品名称','规格数量','总价','实付款','支付方式','名字','电话','地址','配送方式','配送/提货时间','快递信息','客户留言','备注','下单时间','状态');
		$data = [];
		foreach($list as $k=>$vo){
			$member = Db::name('member')->where('id',$vo['mid'])->find();
			$oglist = Db::name('restaurant_takeaway_order_goods')->where('orderid',$vo['id'])->select()->toArray();
			$xm=array();
			foreach($oglist as $gg){
				$html = $gg['name']."/".$gg['ggname']." × ".$gg['num']."";
				$xm[] = $html;
			}
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
			$message = $vo['message'];
            if($vo['field1']){
                $field1data = explode('^_^',$vo['field1']);
                $message .= ','.$field1data[0].':'.$field1data[1];
            }
            if($vo['field2']){
                $field2data = explode('^_^',$vo['field2']);
                $message .= ','.$field2data[0].':'.$field2data[1];
            }
            if($vo['field3']){
                $field3data = explode('^_^',$vo['field3']);
                $message .= ','.$field3data[0].':'.$field3data[1];
            }
            if($vo['field4']){
                $field4data = explode('^_^',$vo['field4']);
                $message .= ','.$field4data[0].':'.$field4data[1];
            }
            if($vo['field5']){
                $field5data = explode('^_^',$vo['field5']);
                $message .= ','.$field5data[0].':'.$field5data[1];
            }
            $message = trim($message,',');
			$data[] = [
				' '.$vo['ordernum'],
				$member['nickname'],
				$vo['title'],
				implode("\r\n",$xm),
				$vo['product_price'],
				$vo['totalprice'],
				$vo['paytype'],
				$vo['linkman'],
                $vo['tel'],
                $vo['address'],
				$vo['freight_text'],
				$vo['freight_time'],
				($vo['express_com'] ? $vo['express_com'].'('.$vo['express_no'].')':''),
                $message,
				$vo['remark'],
				date('Y-m-d H:i:s',$vo['createtime']),
				$status
			]; 
		}
        return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data,'title'=>$title]);
		$this->export_excel($title,$data);
	}
	//订单详情
	public function getdetail(){
		$orderid = input('post.orderid');
		$order = Db::name('restaurant_takeaway_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->find();
		if($order['coupon_rid']){
			$couponrecord = Db::name('coupon_record')->where('id',$order['coupon_rid'])->find();
		}else{
			$couponrecord = false;
		}
		$oglist = Db::name('restaurant_takeaway_order_goods')->where('aid',aid)->where('orderid',$orderid)->select()->toArray();
		$member = Db::name('member')->field('id,nickname,headimg,realname,tel,wxopenid,unionid')->where('id',$order['mid'])->find();
		if(!$member) $member = ['id'=>$order['mid'],'nickname'=>'','headimg'=>''];
		$comdata = array();
		$comdata['parent1'] = ['mid'=>'','nickname'=>'','headimg'=>'','money'=>0,'score'=>0];
		$comdata['parent2'] = ['mid'=>'','nickname'=>'','headimg'=>'','money'=>0,'score'=>0];
		$comdata['parent3'] = ['mid'=>'','nickname'=>'','headimg'=>'','money'=>0,'score'=>0];
		foreach($oglist as $key=>$v){
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
		$miandanst = Db::name('admin_set')->where('aid',aid)->value('miandanst');
		if(bid==0 && $miandanst==1 && in_array('wx',$this->platform) && ($member['wxopenid'] || $member['unionid'])){ //可以使用小程序物流助手发货
			$canmiandan = 1;
		}else{
			$canmiandan = 0;
		}
		return json(['order'=>$order,'couponrecord'=>$couponrecord,'oglist'=>$oglist,'member'=>$member,'comdata'=>$comdata,'canmiandan'=>$canmiandan]);
	}
	
	//设置备注
	public function setremark(){
		$orderid = input('post.orderid/d');
		$content = input('post.content');
		Db::name('restaurant_takeaway_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->update(['remark'=>$content]);
		\app\commons\System::plog('餐饮外卖订单设置备注'.$orderid);
		return json(['status'=>1,'msg'=>'设置完成']);
	}
	//改价格
	public function changeprice(){
		$orderid = input('post.orderid/d');
		$newprice = input('post.newprice/f');
		$newordernum = date('ymdHis').rand(100000,999999);
        $order = Db::name('restaurant_takeaway_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->find();
        $ordernumArr = explode('_',$order['ordernum']);
        if($ordernumArr[1]) $newordernum .= '_'.$ordernumArr[1];
		Db::name('restaurant_takeaway_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->update(['totalprice'=>$newprice,'ordernum'=>$newordernum]);
		Db::name('restaurant_takeaway_order_goods')->where('aid',aid)->where('bid',bid)->where('orderid',$orderid)->update(['ordernum'=>$newordernum]);

		$payorderid = Db::name('restaurant_takeaway_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->value('payorderid');
		\app\models\Payorder::updateorder($payorderid,$newordernum,$newprice,$orderid);
		\app\commons\System::plog('餐饮外卖订单改价格'.$orderid);
		return json(['status'=>1,'msg'=>'修改完成']);
	}
	//关闭订单
	public function closeOrder(){
		$orderid = input('post.orderid/d');
		$order = Db::name('restaurant_takeaway_order')->where('id',$orderid)->where('aid',aid)->where('bid',bid)->find();
		if(!$order || $order['status']!=0){
			return json(['status'=>0,'msg'=>'关闭失败,订单状态错误']);
		}
		//加库存
		$oglist = Db::name('restaurant_takeaway_order_goods')->where('aid',aid)->where('bid',bid)->where('orderid',$orderid)->select()->toArray();
		foreach($oglist as $og){
			Db::name('restaurant_product_guige')->where('aid',aid)->where('id',$og['ggid'])->update(['stock'=>Db::raw("stock+".$og['num']),'sales'=>Db::raw("sales-".$og['num'])]);
			Db::name('restaurant_product')->where('aid',aid)->where('bid',bid)->where('id',$og['proid'])->update(['stock'=>Db::raw("stock+".$og['num']),'sales'=>Db::raw("sales-".$og['num'])]);
		}
		
		//优惠券抵扣的返还
		if($order['coupon_rid'] > 0){
			Db::name('coupon_record')->where('aid',aid)->where(['mid'=>$order['mid'],'id'=>$order['coupon_rid']])->update(['status'=>0,'usetime'=>'']);
		}
		$rs = Db::name('restaurant_takeaway_order')->where('id',$orderid)->where('aid',aid)->where('bid',bid)->update(['status'=>4]);
		Db::name('restaurant_takeaway_order_goods')->where('orderid',$orderid)->where('aid',aid)->where('bid',bid)->update(['status'=>4]);
		\app\commons\System::plog('餐饮外卖订单关闭订单'.$orderid);
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//改为已支付
	public function ispay(){
		if(bid > 0) showmsg('无权限操作');
		$orderid = input('post.orderid/d');
		Db::name('restaurant_takeaway_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->update(['status'=>1,'paytime'=>time(),'paytype'=>'后台支付']);
		Db::name('restaurant_takeaway_order_goods')->where('orderid',$orderid)->where('aid',aid)->where('bid',bid)->update(['status'=>1]);
		//奖励积分
		$order = Db::name('restaurant_takeaway_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->find();
		if($order['givescore'] > 0){
			\app\commons\Member::addscore(aid,$order['mid'],$order['givescore'],'购买产品奖励'.t('积分'));
		}
		\app\commons\System::plog('餐饮外卖订单改为已支付'.$orderid);
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//改为已接单
	public function jiedan(){
		$orderid = input('post.orderid/d');
        $order = Db::name('restaurant_takeaway_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->find();
        if(!$order) return json(['status'=>0,'msg'=>'订单不存在']);
		Db::name('restaurant_takeaway_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->update(['status'=>12]);
		Db::name('restaurant_takeaway_order_goods')->where('orderid',$orderid)->where('aid',aid)->where('bid',bid)->update(['status'=>12]);
        //判断是否自动派单
        if($order['freight_type'] == 2){
            $peisong_set = \db('peisong_set')->where('aid',aid)->find();
            if($peisong_set['express_wx_status'] == 1 && $peisong_set['express_wx_paidan'] == 1){
                Db::name('restaurant_takeaway_order')->where('id',$orderid)->update(['express_type'=>'express_wx']);
                \app\customs\ExpressWx::addOrder('restaurant_takeaway_order',$order);
                \app\commons\System::plog('订单接单，即时配送自动派单:'.$orderid);
            }else{
                //0 配送员抢单 1 指定配送员
                if($peisong_set['paidantype'] == 0){
                    $takeaway_set = Db::name('restaurant_takeaway_sysset')->where('aid',aid)->where('bid',bid)->find();
                    //自动发单 1 开启 0 关闭
                    if($takeaway_set['auto_send_order'] == 1){
                        \app\models\PeisongOrder::create('restaurant_takeaway_order',$order);
                    }
                }
            }
        }
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//发货
	public function sendExpress(){
		$orderid = input('post.orderid/d');
		$express_com = input('post.express_com');
		$express_no = input('post.express_no');
		Db::name('restaurant_takeaway_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->update(['express_com'=>$express_com,'express_no'=>$express_no,'send_time'=>time(),'status'=>2]);
		Db::name('restaurant_takeaway_order_goods')->where('orderid',$orderid)->where('aid',aid)->where('bid',bid)->update(['status'=>2]);
		
		$order = Db::name('restaurant_takeaway_order')->where('id',$orderid)->where('aid',aid)->where('bid',bid)->find();

        //发货信息录入 微信小程序+微信支付
        if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
            \app\commons\Order::wxShipping(aid,$order,'restaurant_takeaway');
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
		\app\commons\Wechat::sendtmpl(aid,$order['mid'],'tmpl_orderfahuo',$tmplcontent,m_url('restaurant/takeaway/orderlist'),$tmplcontentNew);
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
		\app\commons\Wechat::sendwxtmpl(aid,$order['mid'],'tmpl_orderfahuo',$tmplcontentnew,'restaurant/takeaway/orderlist',$tmplcontent);

		//短信通知
		$member = Db::name('member')->where('id',$order['mid'])->find();
		if($member['tel']){
			$tel = $member['tel'];
		}else{
			$tel = $order['tel'];
		}
		$rs = \app\commons\Sms::send(aid,$tel,'tmpl_orderfahuo',['ordernum'=>$order['ordernum'],'express_com'=>$express_com,'express_no'=>$express_no]);
		
		\app\commons\System::plog('餐饮外卖订单发货'.$orderid);
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//查物流
	public function getExpress(){
		$orderid = input('post.orderid/d');
		$order = Db::name('restaurant_takeaway_order')->where('id',$orderid)->where('aid',aid)->where('bid',bid)->find();
		$list = \app\commons\Common::getwuliu($order['express_no'],$order['express_com'],$order['express_type'], aid);
		return json(['status'=>1,'data'=>$list]);
	}
	//退款审核
	public function refundCheck(){
		$orderid = input('post.orderid/d');
		$st = input('post.st/d');
		$remark = input('post.remark');
		$order = Db::name('restaurant_takeaway_order')->where('id',$orderid)->where('aid',aid)->where('bid',bid)->find();
		if($st==2){
			Db::name('restaurant_takeaway_order')->where('id',$orderid)->where('aid',aid)->where('bid',bid)->update(['refund_status'=>3,'refund_checkremark'=>$remark]);
			
			//退款申请驳回通知
			$tmplcontent = [];
			$tmplcontent['first'] = '您的退款申请被商家驳回，可与商家协商沟通。';
			$tmplcontent['remark'] = $remark.'，请点击查看详情~';
			$tmplcontent['orderProductPrice'] = $order['refund_money'];
			$tmplcontent['orderProductName'] = $order['title'];
			$tmplcontent['orderName'] = $order['ordernum'];
            $tmplcontentNew = [];
            $tmplcontentNew['character_string1'] = $order['ordernum'];//订单编号
            $tmplcontentNew['thing2'] = $order['title'];//商品名称
            $tmplcontentNew['amount3'] = $order['refund_money'];//退款金额
			\app\commons\Wechat::sendtmpl(aid,$order['mid'],'tmpl_tuierror',$tmplcontent,m_url('restaurant/takeaway/orderlist'),$tmplcontentNew);
			//订阅消息
			$tmplcontent = [];
			$tmplcontent['amount3'] = $order['refund_money'];
			$tmplcontent['thing2'] = $order['title'];
			$tmplcontent['character_string1'] = $order['ordernum'];
			
			$tmplcontentnew = [];
			$tmplcontentnew['amount3'] = $order['refund_money'];
			$tmplcontentnew['thing8'] = $order['title'];
			$tmplcontentnew['character_string4'] = $order['ordernum'];
			\app\commons\Wechat::sendwxtmpl(aid,$order['mid'],'tmpl_tuierror',$tmplcontentnew,'restaurant/takeaway/orderlist',$tmplcontent);
			//短信通知
			$member = Db::name('member')->where('id',$order['mid'])->find();
			if($member['tel']){
				$tel = $member['tel'];
			}else{
				$tel = $order['tel'];
			}
			$rs = \app\commons\Sms::send(aid,$tel,'tmpl_tuierror',['ordernum'=>$order['ordernum'],'reason'=>$remark]);
			\app\commons\System::plog('餐饮外卖订单退款驳回'.$orderid);
			return json(['status'=>1,'msg'=>'退款已驳回']);
		}elseif($st == 1){
			if($order['status']!=1 && $order['status']!=2){
				return json(['status'=>0,'msg'=>'该订单状态不允许退款']);
			}
			 //查询配送订单 
             $have_peisong_order = Db::name('peisong_order')->where('aid',aid)->where('orderid',$orderid)->where('ordernum',$order['ordernum'])->where('type','restaurant_takeaway_order')->where('status','in',[0,1,2,3])->find();
			if($have_peisong_order){
                return json(['status'=>0,'msg'=>'请到“扩展-同城配送-配送单列表”中取消对应配送订单后再进行审核']);
            }
			$rs = \app\commons\Order::refund($order,$order['refund_money'],$order['refund_reason']);
			if($rs['status']==0){
				return json(['status'=>0,'msg'=>$rs['msg']]);
			}

			Db::name('restaurant_takeaway_order')->where('id',$orderid)->where('aid',aid)->where('bid',bid)->update(['status'=>4,'refund_status'=>2,'refund_checkremark'=>$remark]);
			Db::name('restaurant_takeaway_order_goods')->where('orderid',$orderid)->where('aid',aid)->where('bid',bid)->update(['status'=>4]);
            //更新实际库存
            $goodslist =Db::name('restaurant_takeaway_order_goods')->where('aid',aid)->where('orderid', $orderid)->select()->toArray();
            foreach($goodslist as $key=>$val){
                $num = $val['num'];
                Db::name('restaurant_product')->where('aid',aid)->where('id',$val['proid'])->update(['real_sales2'=>Db::raw("real_sales2-$num")]);
            }
            //退款减去商户销量
            $refund_num = Db::name('restaurant_takeaway_order_goods')->where('orderid',$orderid)->sum('num');
            \app\models\Payorder::addSales($orderid,'restaurant_takeaway',aid,$order['bid'],-$refund_num);
			//积分抵扣的返还
			if($order['scoredkscore'] > 0){
				\app\commons\Member::addscore(aid,$order['mid'],$order['scoredkscore'],'订单退款返还');
			}
			//扣除消费赠送积分
            \app\commons\Member::decscorein(aid,'restaurant_takeaway',$order['id'],$order['ordernum'],'订单退款扣除消费赠送');
			//优惠券抵扣的返还
			if($order['coupon_rid'] > 0){
				Db::name('coupon_record')->where('aid',aid)->where(['mid'=>$order['mid'],'id'=>$order['coupon_rid']])->update(['status'=>0,'usetime'=>'']);
			}
			//退款退还佣金
			//设置大厅的订单
            Db::name('peisong_order')->where('aid',aid)->where('orderid',$orderid)->where('ordernum',$order['ordernum'])->where('type','restaurant_takeaway_order')->where('status',0)->update(['status' => -1]);
            
			//退款成功通知
			$tmplcontent = [];
			$tmplcontent['first'] = '您的订单已经完成退款，¥'.$order['refund_money'].'已经退回您的付款账户，请留意查收。';
			$tmplcontent['remark'] = $remark.'，请点击查看详情~';
			$tmplcontent['orderProductPrice'] = $order['refund_money'];
			$tmplcontent['orderProductName'] = $order['title'];
			$tmplcontent['orderName'] = $order['ordernum'];
            $tmplcontentNew = [];
            $tmplcontentNew['character_string1'] = $order['ordernum'];//订单编号
            $tmplcontentNew['thing2'] = $order['title'];//商品名称
            $tmplcontentNew['amount3'] = $order['refund_money'];//退款金额
			\app\commons\Wechat::sendtmpl(aid,$order['mid'],'tmpl_tuisuccess',$tmplcontent,m_url('restaurant/takeaway/orderlist'),$tmplcontentNew);
			//订阅消息
			$tmplcontent = [];
			$tmplcontent['amount6'] = $order['refund_money'];
			$tmplcontent['thing3'] = $order['title'];
			$tmplcontent['character_string2'] = $order['ordernum'];
			
			$tmplcontentnew = [];
			$tmplcontentnew['amount3'] = $order['refund_money'];
			$tmplcontentnew['thing6'] = $order['title'];
			$tmplcontentnew['character_string4'] = $order['ordernum'];
			\app\commons\Wechat::sendwxtmpl(aid,$order['mid'],'tmpl_tuisuccess',$tmplcontentnew,'restaurant/takeaway/orderlist',$tmplcontent);

			//短信通知
			$member = Db::name('member')->where('id',$order['mid'])->find();
			if($member['tel']){
				$tel = $member['tel'];
			}else{
				$tel = $order['tel'];
			}
			$rs = \app\commons\Sms::send(aid,$tel,'tmpl_tuisuccess',['ordernum'=>$order['ordernum'],'money'=>$order['refund_money']]);
			
			\app\commons\System::plog('餐饮外卖订单退款审核通过并退款'.$orderid);
			return json(['status'=>1,'msg'=>'已退款成功']);
		}
	}
	//退款
	public function refund(){
		$orderid = input('post.orderid/d');
		$reason = input('post.reason');
		$order = Db::name('restaurant_takeaway_order')->where('id',$orderid)->where('aid',aid)->where('bid',bid)->find();
		$refund_money = $order['totalprice'];
		if($order['status']!=1 && $order['status']!=2 && $order['status']!=12){
			return json(['status'=>0,'msg'=>'该订单状态不允许退款']);
		}
		if($refund_money > 0) {
            $rs = \app\commons\Order::refund($order,$refund_money,$reason);
            if($rs['status']==0){
                return json(['status'=>0,'msg'=>$rs['msg']]);
            }
        }

		Db::name('restaurant_takeaway_order')->where('id',$orderid)->where('aid',aid)->where('bid',bid)->update(['status'=>4,'refund_status'=>2,'refund_money'=>$refund_money,'refund_reason'=>$reason]);
		Db::name('restaurant_takeaway_order_goods')->where('orderid',$orderid)->where('aid',aid)->where('bid',bid)->update(['status'=>4]);
        //更新实际库存
        $goodslist =Db::name('restaurant_takeaway_order_goods')->where('aid',aid)->where('orderid', $orderid)->select()->toArray();
        foreach($goodslist as $key=>$val){
            $num = $val['num'];
            Db::name('restaurant_product')->where('aid',aid)->where('id',$val['proid'])->update(['real_sales2'=>Db::raw("real_sales2-$num")]);
        }
        //退款减去商户销量
        $refund_num = Db::name('restaurant_takeaway_order_goods')->where('orderid',$orderid)->sum('num');
        \app\models\Payorder::addSales($orderid,'restaurant_takeaway',aid,$order['bid'],-$refund_num);

		//积分抵扣的返还
		if($order['scoredkscore'] > 0){
			\app\commons\Member::addscore(aid,$order['mid'],$order['scoredkscore'],'订单退款返还');
		}
		//扣除消费赠送积分
        \app\commons\Member::decscorein(aid,'restaurant_takeaway',$order['id'],$order['ordernum'],'订单退款扣除消费赠送');
		//优惠券抵扣的返还
		if($order['coupon_rid'] > 0){
			Db::name('coupon_record')->where('aid',aid)->where(['mid'=>$order['mid'],'id'=>$order['coupon_rid']])->update(['status'=>0,'usetime'=>'']);
		}
		//退款退还佣金
		//取消配送大厅的订单
        if($order['freight_type'] ==2 && !$order['express_type']){
            //同城配送的时候 取消
          $peisong_order =  Db::name('peisong_order')->where('aid',aid)->where('bid',$order['bid'])->where('orderid',$order['id'])->where('type','restaurant_takeaway_order')->where('status','in',[0,1,2,3])->find(); 
           if($peisong_order){
               Db::name('peisong_order')->where('aid',aid)->where('bid',$order['bid'])->where('id',$peisong_order['id'])->update(['status' => -1]); //新增-1取消
           }
        }
		//退款成功通知
		$tmplcontent = [];
		$tmplcontent['first'] = '您的订单已经完成退款，¥'.$refund_money.'已经退回您的付款账户，请留意查收。';
		$tmplcontent['remark'] = $reason.'，请点击查看详情~';
		$tmplcontent['orderProductPrice'] = $refund_money;
		$tmplcontent['orderProductName'] = $order['title'];
		$tmplcontent['orderName'] = $order['ordernum'];
        $tmplcontentNew = [];
        $tmplcontentNew['character_string1'] = $order['ordernum'];//订单编号
        $tmplcontentNew['thing2'] = $order['title'];//商品名称
        $tmplcontentNew['amount3'] = $refund_money;//退款金额
		\app\commons\Wechat::sendtmpl(aid,$order['mid'],'tmpl_tuisuccess',$tmplcontent,m_url('restaurant/takeaway/orderlist'),$tmplcontentNew);
		//订阅消息
		$tmplcontent = [];
		$tmplcontent['amount6'] = $refund_money;
		$tmplcontent['thing3'] = $order['title'];
		$tmplcontent['character_string2'] = $order['ordernum'];
		
		$tmplcontentnew = [];
		$tmplcontentnew['amount3'] = $refund_money;
		$tmplcontentnew['thing6'] = $order['title'];
		$tmplcontentnew['character_string4'] = $order['ordernum'];
		\app\commons\Wechat::sendwxtmpl(aid,$order['mid'],'tmpl_tuisuccess',$tmplcontentnew,'restaurant/takeaway/orderlist',$tmplcontent);

		//短信通知
		$member = Db::name('member')->where('id',$order['mid'])->find();
		if($member['tel']){
			$tel = $member['tel'];
		}else{
			$tel = $order['tel'];
		}
		$rs = \app\commons\Sms::send(aid,$tel,'tmpl_tuisuccess',['ordernum'=>$order['ordernum'],'money'=>$refund_money]);
		
		\app\commons\System::plog('餐饮外卖订单退款'.$orderid);
		return json(['status'=>1,'msg'=>'已退款成功']);
	}
	function orderCollect(){ //确认收货
		$post = input('post.');
		$orderid = intval($post['orderid']);
		$order = Db::name('restaurant_takeaway_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->find();
		//if(bid != 0){
		//	return json(['status'=>0,'msg'=>'无操作权限']);
		//}
		if(!$order || ($order['status']!=2 && $order['status']!=12)){
			return json(['status'=>0,'msg'=>'订单状态不符合收货要求']);
		}
		$rs = \app\customs\Restaurant::takeaway_orderconfirm($orderid);
		if($rs['status']==0) return $rs;
		//Db::name('restaurant_takeaway_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->update(['status'=>3,'collect_time'=>time()]);
		//Db::name('restaurant_takeaway_order_goods')->where('aid',aid)->where('bid',bid)->where('orderid',$orderid)->update(['status'=>3,'endtime'=>time()]);
		//\app\commons\Member::uplv(aid,$order['mid']);
		
		return json(['status'=>1,'msg'=>'确认收货成功']);
	}
	//打印小票
	public function wifiprint(){
		$id = input('post.id/d');
        $order = Db::name('restaurant_takeaway_order')->where('aid',aid)->where('bid',bid)->where('id',$id)->find();
        $rs = \app\customs\Restaurant::print('restaurant_takeaway', $order, [], 0);//0普通打印，1一菜一单
        return json($rs);
	}
	//删除
	public function del(){
		$id = input('post.id/d');
		Db::name('restaurant_takeaway_order')->where('aid',aid)->where('bid',bid)->where('id',$id)->delete();
		Db::name('restaurant_takeaway_order_goods')->where('aid',aid)->where('bid',bid)->where('orderid',$id)->delete();
		\app\commons\System::plog('餐饮外卖订单删除'.$id);
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	//编辑
	public function edit(){
		$orderid = input('param.id/d');
		$info = Db::name('restaurant_takeaway_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->find();
		$order_goods = Db::name('restaurant_takeaway_order_goods')->where('aid',aid)->where('bid',bid)->where('orderid',$orderid)->select()->toArray();
		foreach($order_goods as $k=>$v){
			$order_goods[$k]['lvprice'] = Db::name('restaurant_product')->where('id',$v['proid'])->value('lvprice'); //是否开启会员价
		}
		$member = Db::name('member')->where('id',$info['mid'])->find();
		$userlevel = Db::name('member_level')->where('aid',aid)->where('id',$member['levelid'])->find();
		if($userlevel && $userlevel['discount']>0 && $userlevel['discount']<10){
			$discount = $userlevel['discount']*0.1; //会员折扣
		}else{
			$discount = 1;
		}

		if(request()->isAjax()){
			$postinfo = input('post.info/a');
			Db::name('restaurant_takeaway_order')->where('id',$orderid)->update($postinfo);
			$order = Db::name('restaurant_takeaway_order')->where('id',$orderid)->find();
			$goods_id = input('post.goods_id/a');
			$goods_ggname = input('post.goods_ggname/a');
			$goods_sell_price = input('post.goods_sell_price/a');
			$goods_num = input('post.goods_num/a');
			foreach($goods_id as $k=>$ogid){
				$oginfo = Db::name('restaurant_takeaway_order_goods')->where('id',$ogid)->find();
				$ogdata = [];
				$ogdata['ggname'] = $goods_ggname[$k];
				$ogdata['sell_price'] = $goods_sell_price[$k];
				$ogdata['num'] = $goods_num[$k];
				$ogdata['totalprice'] = $ogdata['sell_price'] * $ogdata['num'];
				
				$product = Db::name('restaurant_product')->where('id',$oginfo['proid'])->find();
				$ogtotalprice = $ogdata['totalprice'];
				$commissiontype = Db::name('admin_set')->where('aid',aid)->value('commissiontype');
				if($commissiontype == 1){
					$allgoodsprice = $order['goodsprice'] - $order['disprice'];
					$couponmoney = $order['couponmoney'];
					$scoredk = $order['scoredk'];
					$disprice = 0;
					$ogcouponmoney = 0;
					$ogscoredk = 0;
					if($product['lvprice']==0 && $userlevel && $userlevel['discount']>0 && $userlevel['discount']<10){ //未开启会员价
						$disprice = $ogtotalprice * (1 - $userlevel['discount'] * 0.1);
						$ogtotalprice = $ogtotalprice - $disprice;
					}
					if($couponmoney){
						$ogcouponmoney = $ogtotalprice / $allgoodsprice * $couponmoney;
					}
					if($scoredk){
						$ogscoredk = $ogtotalprice / $allgoodsprice * $scoredk;
					}
					$ogtotalprice = round($ogtotalprice - $ogcouponmoney - $ogscoredk,2);
					if($ogtotalprice < 0) $ogtotalprice = 0;
				}
				$agleveldata = Db::name('member_level')->where('aid',aid)->where('id',$member['levelid'])->find();
				
				if($product['commissionset']!=-1){
					if($member['pid']){
						$parent1 = Db::name('member')->where('aid',aid)->where('id',$member['pid'])->find();
						
						if($parent1){
							$agleveldata1 = Db::name('member_level')->where('aid',aid)->where('id',$parent1['levelid'])->find();
							if($agleveldata1['can_agent']!=0){
								$ogdata['parent1'] = $parent1['id'];
							}
						}
						//return json(['status'=>0,'msg'=>'11','data'=>$parent1,'data2'=>$agleveldata1]);
					}
					if($parent1['pid']){
						$parent2 = Db::name('member')->where('aid',aid)->where('id',$parent1['pid'])->find();
						if($parent2){
							$agleveldata2 = Db::name('member_level')->where('aid',aid)->where('id',$parent2['levelid'])->find();
							if($agleveldata2['can_agent']>1){
								$ogdata['parent2'] = $parent2['id'];
							}
						}
					}
					if($parent2['pid']){
						$parent3 = Db::name('member')->where('aid',aid)->where('id',$parent2['pid'])->find();
						if($parent3){
							$agleveldata3 = Db::name('member_level')->where('aid',aid)->where('id',$parent3['levelid'])->find();
							if($agleveldata3['can_agent']>2){
								$ogdata['parent3'] = $parent3['id'];
							}
						}
					}
					if($product['commissionset']==1){//按比例
						$commissiondata = json_decode($product['commissiondata1'],true);
						if($commissiondata){
							$ogdata['parent1commission'] = $commissiondata[$agleveldata1['id']]['commission1'] * $ogtotalprice * 0.01;
							$ogdata['parent2commission'] = $commissiondata[$agleveldata2['id']]['commission2'] * $ogtotalprice * 0.01;
							$ogdata['parent3commission'] = $commissiondata[$agleveldata3['id']]['commission3'] * $ogtotalprice * 0.01;
						}
					}elseif($product['commissionset']==2){//按固定金额
						$commissiondata = json_decode($product['commissiondata2'],true);
						if($commissiondata){
							$ogdata['parent1commission'] = $commissiondata[$agleveldata1['id']]['commission1'] * $num;
							$ogdata['parent2commission'] = $commissiondata[$agleveldata2['id']]['commission2'] * $num;
							$ogdata['parent3commission'] = $commissiondata[$agleveldata3['id']]['commission3'] * $num;
						}
					}else{
						$ogdata['parent1commission'] = $agleveldata1['commission1'] * $ogtotalprice * 0.01;
						$ogdata['parent2commission'] = $agleveldata2['commission2'] * $ogtotalprice * 0.01;
						$ogdata['parent3commission'] = $agleveldata3['commission3'] * $ogtotalprice * 0.01;
					}
				}

				Db::name('restaurant_takeaway_order_goods')->where('aid',aid)->where('bid',bid)->where('id',$ogid)->update($ogdata);
			}
			
			$newordernum = date('ymdHis').rand(100000,999999);
			Db::name('restaurant_takeaway_order')->where('aid',aid)->where('id',$orderid)->update(['ordernum'=>$newordernum]);
			Db::name('restaurant_takeaway_order_goods')->where('aid',aid)->where('orderid',$orderid)->update(['ordernum'=>$newordernum]);
			\app\commons\System::plog('餐饮外卖订单编辑'.$orderid);
			return json(['status'=>1,'msg'=>'修改成功']);
		}
		View::assign('info',$info);
		View::assign('order_goods',$order_goods);
		View::assign('discount',$discount);
		View::assign('express_data',express_data(['aid'=>aid,'bid'=>bid]));
		return View::fetch();
	}
	//送货单
	public function shd(){
		$set = Db::name('admin_set')->where('aid',aid)->find();
		$orderid = input('param.id/d');
		$info = Db::name('restaurant_takeaway_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->find();
		$order_goods = Db::name('restaurant_takeaway_order_goods')->where('aid',aid)->where('bid',bid)->where('orderid',$orderid)->select()->toArray();
		foreach($order_goods as $k=>$v){
			$order_goods[$k]['lvprice'] = Db::name('restaurant_product')->where('id',$v['proid'])->value('lvprice'); //是否开启会员价
		}
		$member = Db::name('member')->where('id',$info['mid'])->find();
		$userlevel = Db::name('member_level')->where('aid',aid)->where('id',$member['levelid'])->find();
		if($userlevel && $userlevel['discount']>0 && $userlevel['discount']<10){
			$discount = $userlevel['discount']*0.1; //会员折扣
		}else{
			$discount = 1;
		}
		$order_goods2 = [];
		if(count($order_goods) < 10){
    		for($i=0;$i<10;$i++){
    			$order_goods2[] = $order_goods[$i];
    		}
		}else{
		    $order_goods2 = $order_goods;
		}
		$order_goods2[] = ['type'=>'yf'];
		$order_goods2[] = ['type'=>'totalprice'];
		$order_goods2[] = ['type'=>'totalprice2'];
		$order_goods3 = array_chunk($order_goods2,13);
		$info['totalprice2'] = num_to_rmb($info['totalprice']);
		View::assign('set',$set);
		View::assign('info',$info);
		View::assign('order_goods3',$order_goods3);
		View::assign('discount',$discount);
		View::assign('express_data',express_data(['aid'=>aid,'bid'=>bid]));
		return View::fetch();
	}
	//订单统计
	public function tongji(){
		if(request()->isAjax()){
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
			$where[] = ['og.status','in','1,2,3'];
			if($this->mdid){
				$where[] = ['mdid','=',$this->mdid];
			}
			if(input('param.ctime') ){
				$ctime = explode(' ~ ',input('param.ctime'));
				$where[] = ['og.createtime','>=',strtotime($ctime[0])];
				$where[] = ['og.createtime','<',strtotime($ctime[1])];
			}
			if(input('param.paytime') ){
				$ctime = explode(' ~ ',input('param.paytime'));
				$where[] = ['restaurant_takeaway_order.paytime','>=',strtotime($ctime[0])];
				$where[] = ['restaurant_takeaway_order.paytime','<',strtotime($ctime[1]) + 86400];
			}
			if(input('param.proname')){
				$where[] = ['og.name','like','%'.input('param.proname').'%'];
			}
			if(input('param.cid')){
				$where[] = ['og.cid','=',input('param.cid')];
			}
			if(input('param.type')==2){
				$count = 0 + Db::name('restaurant_takeaway_order_goods')->alias('og')->join('restaurant_takeaway_order','restaurant_takeaway_order.id=og.orderid')->field('og.proid,og.name,og.ggname,sum(og.num) num,sum(og.totalprice) totalprice')->where($where)->group('ggid')->count();
				$list = Db::name('restaurant_takeaway_order_goods')->alias('og')->join('restaurant_takeaway_order','restaurant_takeaway_order.id=og.orderid')->field('og.proid,og.name,og.pic,og.ggname,sum(og.num) num,sum(og.totalprice) totalprice,sum(og.totalprice)/sum(og.num) as avgprice')->where($where)->group('ggid')->page($page,$limit)->order($order)->select()->toArray();
			}else{
				$count = 0 + Db::name('restaurant_takeaway_order_goods')->alias('og')->join('restaurant_takeaway_order','restaurant_takeaway_order.id=og.orderid')->field('og.proid,og.name,og.ggname,sum(og.num) num,sum(og.totalprice) totalprice')->where($where)->group('proid')->count();
				$list = Db::name('restaurant_takeaway_order_goods')->alias('og')->join('restaurant_takeaway_order','restaurant_takeaway_order.id=og.orderid')->field('og.proid,og.name,og.pic,og.ggname,sum(og.num) num,sum(og.totalprice) totalprice,sum(og.totalprice)/sum(og.num) as avgprice')->where($where)->group('proid')->page($page,$limit)->order($order)->select()->toArray();
			}
			foreach($list as $k=>$v){
				$list[$k]['ph'] = ($k+1) + ($page-1)*$limit;
				$list[$k]['avgprice'] = number_format($v['avgprice'],2,'.','');
			}

			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$list]);
		}
		return View::fetch();
	}
	//导出
	public function tjexcel(){
		set_time_limit(0);
		ini_set('memory_limit', '2000M');
		if(input('param.field') && input('param.order')){
			$order = input('param.field').' '.input('param.order');
		}else{
			$order = 'totalprice desc';
		}
		$where = [];
		$where[] = ['og.aid','=',aid];
		$where[] = ['og.bid','=',bid];
		$where[] = ['og.status','in','1,2,3'];
		if($this->mdid){
			$where[] = ['mdid','=',$this->mdid];
		}
		if(input('param.ctime') ){
			$ctime = explode(' ~ ',input('param.ctime'));
			$where[] = ['og.createtime','>=',strtotime($ctime[0])];
			$where[] = ['og.createtime','<',strtotime($ctime[1]) + 86400];
		}
		if(input('param.paytime') ){
			$ctime = explode(' ~ ',input('param.paytime'));
			$where[] = ['restaurant_takeaway_order.paytime','>=',strtotime($ctime[0])];
			$where[] = ['restaurant_takeaway_order.paytime','<',strtotime($ctime[1]) + 86400];
		}
		if(input('param.proname')){
			$where[] = ['og.name','like','%'.input('param.proname').'%'];
		}
		if(input('param.cid')){
			$where[] = ['og.cid','=',input('param.cid')];
		}
		if(input('param.type')==2){
			$list = Db::name('restaurant_takeaway_order_goods')->alias('og')->join('restaurant_takeaway_order','restaurant_takeaway_order.id=og.orderid')->field('og.proid,og.name,og.pic,og.ggname,sum(og.num) num,sum(og.totalprice) totalprice,sum(og.totalprice)/sum(og.num) as avgprice')->where($where)->group('ggid')->order($order)->select()->toArray();
		}else{
			$list = Db::name('restaurant_takeaway_order_goods')->alias('og')->join('restaurant_takeaway_order','restaurant_takeaway_order.id=og.orderid')->field('og.proid,og.name,og.pic,og.ggname,sum(og.num) num,sum(og.totalprice) totalprice,sum(og.totalprice)/sum(og.num) as avgprice')->where($where)->group('proid')->order($order)->select()->toArray();
		}
		foreach($list as $k=>$v){
			$list[$k]['ph'] = ($k+1) + ($page-1)*$limit;
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
					$vo['num'],
					$vo['totalprice'],
					$vo['avgprice'],
				]; 
			}
		}else{
			$title = array('排名','商品名称','销售数量','销售金额','平均单价');
			$data = [];
			foreach($list as $k=>$vo){
				$data[] = [
					$vo['ph'],
					$vo['name'],
					$vo['num'],
					$vo['totalprice'],
					$vo['avgprice'],
				]; 
			}
		}
		$this->export_excel($title,$data);
	}

    function defaultSet(){
        $set = Db::name('restaurant_takeaway_sysset')->where('aid',aid)->where('bid',bid)->find();
        if(!$set){
            Db::name('restaurant_takeaway_sysset')->insert(['aid'=>aid,'bid' => bid]);
        }
    }
}
