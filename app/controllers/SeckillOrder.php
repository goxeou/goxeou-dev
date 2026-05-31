<?php


// +----------------------------------------------------------------------
// | 秒杀商城-商品订单
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;
class SeckillOrder extends Common
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
			$where[] = ['bid','=',bid];
			if($this->mdid){
				$where[] = ['mdid','=',$this->mdid];
			}
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
			$count = 0 + Db::name('seckill_order')->where($where)->count();
			$list = Db::name('seckill_order')->where($where)->page($page,$limit)->order($order)->select()->toArray();

			foreach($list as $k=>$vo){
				$member = Db::name('member')->where('id',$vo['mid'])->find();
				$goodsdata=array();
				$goodsdata[] = '<div style="font-size:12px;float:left;clear:both;margin:1px 0">'.
					'<img src="'.$vo['propic'].'" style="max-width:60px;float:left">'.
					'<div style="float: left;width:160px;margin-left: 10px;white-space:normal;line-height:16px;">'.
						'<div style="width:100%;min-height:25px;max-height:32px;overflow:hidden">'.$vo['proname'].'</div>'.
						'<div style="padding-top:0px;color:#f60"><span style="color:#888">'.$vo['ggname'].'</span></div>'.
						'<div style="padding-top:0px;color:#f60;">￥'.$vo['sell_price'].' × '.$vo['num'].'</div>'.
					'</div>'.
				'</div>';
				$list[$k]['goodsdata'] = implode('',$goodsdata);
				$list[$k]['nickname'] = $member['nickname'];
				$list[$k]['headimg'] = $member['headimg'];
				$list[$k]['m_remark'] = $member['remark'];
				$list[$k]['platform'] = getplatformname($vo['platform']);
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$list]);
		}
		$peisong_set = Db::name('peisong_set')->where('aid',aid)->find();
		if($peisong_set['status']==1 && bid>0 && $peisong_set['businessst']==0 && $peisong_set['make_status']==0) $peisong_set['status'] = 0;
		View::assign('peisong_set',$peisong_set);
		View::assign('express_data',express_data(['aid'=>aid,'bid'=>bid]));
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
				$where['refund_status'] = 1;
			}elseif(input('param.status') == 6){
				$where[] = ['refund_status','=',2];
			}elseif(input('param.status') == 7){
				$where[] = ['refund_status','=',3];
			}else{
				$where[] = ['status','=',input('param.status')];
			}
		}
		$list = Db::name('seckill_order')->where($where)->order($order)->page($page,$limit)->select()->toArray();
        $count = Db::name('seckill_order')->where($where)->order($order)->count();
		$title = array('订单号','下单人','商品名称','规格数量','总价','实付款','支付方式','姓名','电话','收货地址','配送方式','配送/提货时间','快递信息','客户留言','后台备注','下单时间','状态','备注','其他');
		$data = [];
		foreach($list as $k=>$vo){
			$member = Db::name('member')->where('id',$vo['mid'])->find();
			$xm=array();
			$xm[] = $vo['proname']."/".$vo['ggname']." × ".$vo['num']."";
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
			$data[$k] = [
				' '.$vo['ordernum'],
				$member['nickname'],
				$vo['title'],
				implode("\r\n",$xm),
				$vo['product_price'],
				$vo['totalprice'],
				$vo['paytype'],
				$vo['linkman'],
				$vo['tel'],
				$vo['area'].' '.$vo['address'],
				$vo['freight_text'],
				$vo['freight_time'],
				($vo['express_com'] ? $vo['express_com'].'('.$vo['express_no'].')':''),
				$vo['message'],
				$vo['remark'],
				date('Y-m-d H:i:s',$vo['createtime']),
				$status,
                ''
			];
            //配送自定义表单
            $vo['formdata'] = \app\models\Freight::getformdata($vo['id'],'seckill_order');
            if($vo['formdata']) {
                foreach ($vo['formdata'] as $formdata) {
                    if($formdata[2] != 'upload') {
                        if($formdata[0] == '备注') {
                            $data[$k][17] = $formdata[1];
                        } else {
                            $data[$k][18] .= $formdata[0].':'.$formdata[1]."\r\n";
                        }
                    }
                }
            }
		}
        return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data,'title'=>$title]);
		$this->export_excel($title,$data);
	}
	//订单详情
	public function getdetail(){
		$orderid = input('post.orderid');
		$order = Db::name('seckill_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->find();
		if($order['coupon_rid']){
			$couponrecord = Db::name('coupon_record')->where('id',$order['coupon_rid'])->find();
		}else{
			$couponrecord = false;
		}
		$member = Db::name('member')->field('id,nickname,headimg,realname,tel')->where('id',$order['mid'])->find();
		if(!$member) $member = ['id'=>$order['mid'],'nickname'=>'','headimg'=>''];
		$comdata = array();
		$comdata['parent1'] = ['mid'=>'','nickname'=>'','headimg'=>'','money'=>0];
		$comdata['parent2'] = ['mid'=>'','nickname'=>'','headimg'=>'','money'=>0];
		$comdata['parent3'] = ['mid'=>'','nickname'=>'','headimg'=>'','money'=>0];
		
		$order['formdata'] = \app\models\Freight::getformdata($order['id'],'seckill_order');

		return json(['order'=>$order,'couponrecord'=>$couponrecord,'member'=>$member,'comdata'=>$comdata]);
	}
	//设置备注
	public function setremark(){
		$orderid = input('post.orderid/d');
		$content = input('post.content');
		Db::name('seckill_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->update(['remark'=>$content]);
		\app\commons\System::plog('设置秒杀订单备注'.$orderid);
		return json(['status'=>1,'msg'=>'设置完成']);
	}
	//改价格
	public function changeprice(){
		$orderid = input('post.orderid/d');
		$newprice = input('post.newprice/f');
		Db::name('seckill_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->update(['totalprice'=>$newprice,'ordernum'=>date('ymdHis').aid.rand(1000,9999)]);
		\app\commons\System::plog('修改秒杀订单价格'.$orderid);
		return json(['status'=>1,'msg'=>'修改完成']);
	}
	//关闭订单
	public function closeOrder(){
		$orderid = input('post.orderid/d');
		$order = Db::name('seckill_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->find();
		if(!$order || $order['status']!=0){
			return json(['status'=>0,'msg'=>'关闭失败,订单状态错误']);
		}
		Db::name('seckill_guige')->where('id',$order['ggid'])->update(['stock'=>Db::raw("stock+".$order['num']),'sales'=>Db::raw("sales-".$order['num'])]);
		Db::name('seckill_product')->where('aid',aid)->where('bid',bid)->where('id',$order['proid'])->update(['stock'=>Db::raw("stock+".$order['num']),'sales'=>Db::raw("sales-".$order['num'])]);
		
		//优惠券抵扣的返还
		if($order['coupon_rid'] > 0){
			Db::name('coupon_record')->where('aid',aid)->where('bid',bid)->where('mid',$order['mid'])->where('id',$order['coupon_rid'])->update(['status'=>0,'usetime'=>'']);
		}
		$rs = Db::name('seckill_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->update(['status'=>4]);
		\app\commons\System::plog('关闭秒杀订单'.$orderid);
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//改为已支付
	public function ispay(){
		$orderid = input('post.orderid/d');
		Db::name('seckill_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->update(['status'=>1,'paytime'=>time(),'paytype'=>'后台支付']);
        \app\models\Payorder::seckill_pay($orderid);
//		//奖励积分
//		$order = Db::name('seckill_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->find();
//		if($order['givescore'] > 0){
//			\app\modal\Member::addscore(aid,$order['mid'],$order['givescore'],'购买产品奖励'.t('积分'));
//		}
		\app\commons\System::plog('秒杀订单改为已支付'.$orderid);
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//发货
	public function sendExpress(){
		$orderid = input('post.orderid/d');
		$order = Db::name('seckill_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->find();
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
			$express_com = input('post.express_com');
			$express_no = input('post.express_no');
		}
		
		if($order['status']!=1){ //修改物流信息
			Db::name('seckill_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->update(['express_com'=>$express_com,'express_no'=>$express_no]);
			return json(['status'=>1,'msg'=>'操作成功']);
		}

		Db::name('seckill_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->update(['express_com'=>$express_com,'express_no'=>$express_no,'send_time'=>time(),'status'=>2]);
        //发货信息录入 微信小程序+微信支付
        if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
            \app\commons\Order::wxShipping(aid,$order,'seckill',['express_com'=>$express_com,'express_no'=>$express_no]);
        }
		//订单发货通知
		$tmplcontent = [];
		$tmplcontent['first'] = '您的秒杀订单已发货';
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
		\app\commons\System::plog('秒杀订单发货'.$orderid);
		return json(['status'=>1,'msg'=>'操作成功']);
	}

			//批量发货
	public function plfh(){
		$express_com = input('post.plfh_express');
		$file = input('post.plfh_file');
		$exceldata = $this->import_excel($file);
		//dump($exceldata);
		// $countnum = count($exceldata);
		// 过滤空格
		$countnum = 0;
		$successnum = 0;
		$errornum = 0;

		foreach($exceldata as $v){
			$ordernum = trim($v[0]);
			$express_no = $v[1];
			if(!$ordernum || !$express_no){
				continue;
			}
			$countnum++;

			$order = Db::name('seckill_order')->where('aid',aid)->where('bid',bid)->where('ordernum',$ordernum)->find();
			if($order['freight_type']==10){
				$errornum++;
				// 货运托运
				continue;
			}
			
			if($order['status']!=1){ //修改物流信息
				Db::name('seckill_order')->where('aid',aid)->where('bid',bid)->where('id',$order['id'])->update(['express_com'=>$express_com,'express_no'=>$express_no]);
				$errornum++;
				continue;
			}

			Db::name('seckill_order')->where('aid',aid)->where('bid',bid)->where('id',$order['id'])->update(['express_com'=>$express_com,'express_no'=>$express_no,'send_time'=>time(),'status'=>2]);
	        //发货信息录入 微信小程序+微信支付
	        if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
	            \app\commons\Order::wxShipping(aid,$order,'seckill',['express_com'=>$express_com,'express_no'=>$express_no]);
	        }
			//订单发货通知
			$tmplcontent = [];
			$tmplcontent['first'] = '您的秒杀订单已发货';
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

		\app\commons\System::plog('整点秒杀订单批量发货');
		return json(['status'=>1,'msg'=>'共导入 '.$countnum.' 条数据，成功发货 '.$successnum.' 条，失败 '.$errornum.' 条']);
	}

	//查物流
	public function getExpress(){
		$orderid = input('post.orderid/d');
		$order = Db::name('seckill_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->find();
		if($order['freight_type'] == '10'){
			$data = Db::name('freight_type10_record')->where('id',$order['express_no'])->find();
			return json(['status'=>1,'data'=>$data]);
		}
		if($order['express_com'] == '顺丰速运'){
			$totel = $order['tel'];
			$order['express_no'] = $order['express_no'].":".substr($totel,-4);
		}

		$list[] = [
            'express_no' => $order['express_no'],
            'express_com' => $order['express_com'],
            'express_data' => \app\commons\Common::getwuliu($order['express_no'],$order['express_com'],'', aid),
			'oglist'=>[]
        ];

		return json(['status'=>1,'data'=>$list]);
	}
    //核销并确认收货
    function orderHexiao(){
        $post = input('post.');
        $orderid = intval($post['orderid']);
        $order = Db::name('seckill_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->find();
        if(!$order || !in_array($order['status'], [1,2]) || $order['freight_type'] != 1){
            return json(['status'=>0,'msg'=>'订单状态不符合核销收货要求']);
        }

        $data = array();
        $data['aid'] = aid;
        $data['bid'] = bid;
        $data['uid'] = $this->uid;
        $data['mid'] = $order['mid'];
        $data['orderid'] = $order['id'];
        $data['ordernum'] = $order['ordernum'];
        $data['title'] = $order['title'];
        $data['type'] = 'seckill';
        $data['createtime'] = time();
        $data['remark'] = '核销员['.$this->user['un'].']核销';
        $data['mdid']   = empty($this->user['mdid'])?0:$this->user['mdid'];
		Db::name('hexiao_order')->insert($data);

        $rs = \app\commons\Order::collect($order, 'seckill');
        if($rs['status']==0) return $rs;
        Db::name('seckill_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->update(['status'=>3,'collect_time'=>time()]);
        \app\commons\Member::uplv(aid,$order['mid']);
        //发货信息录入 微信小程序+微信支付
        if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
            \app\commons\Order::wxShipping(aid,$order,'seckill');
        }
        \app\commons\System::plog('秒杀订单核销确认收货'.$orderid);
        return json(['status'=>1,'msg'=>'核销成功']);
    }
    function orderCollect(){ //确认收货
        $post = input('post.');
        $orderid = intval($post['orderid']);
        $order = Db::name('seckill_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->find();
        if(bid != 0){
            return json(['status'=>0,'msg'=>'无操作权限']);
        }
        if(!$order || ($order['status']!=2)){
            return json(['status'=>0,'msg'=>'订单状态不符合收货要求']);
        }

        $rs = \app\commons\Order::collect($order, 'seckill', $this->user['mid']);
        if($rs['status']==0) return $rs;
        Db::name('seckill_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->update(['status'=>3,'collect_time'=>time()]);
        \app\commons\Member::uplv(aid,$order['mid']);
        \app\commons\System::plog('秒杀订单确认收货'.$orderid);
        return json(['status'=>1,'msg'=>'确认收货成功']);
    }
	//退款审核
	public function refundCheck(){
		$orderid = input('post.orderid/d');
		$st = input('post.st/d');
		$remark = input('post.remark');
		$order = Db::name('seckill_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->find();
		if($st==2){
			Db::name('seckill_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->update(['refund_status'=>3,'refund_checkremark'=>$remark]);
			
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
			
			\app\commons\System::plog('秒杀订单退款审核驳回'.$orderid);
			return json(['status'=>1,'msg'=>'退款已驳回']);
		}elseif($st == 1){
			if($order['status']!=1 && $order['status']!=2){
				return json(['status'=>0,'msg'=>'该订单状态不允许退款']);
			}
			$rs = \app\commons\Order::refund($order,$order['refund_money'],$order['refund_reason']);
			if($rs['status']==0){
				return json(['status'=>0,'msg'=>$rs['msg']]);
			}

			Db::name('seckill_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->update(['status'=>4,'refund_status'=>2]);
            //退款减去商户销量
            \app\models\Payorder::addSales($orderid,'seckill',$order['aid'],$order['bid'],-$order['num']);

			//积分抵扣的返还
			if($order['scoredkscore'] > 0){
				\app\commons\Member::addscore(aid,$order['mid'],$order['scoredkscore'],'订单退款返还');
			}
			//扣除消费赠送积分
            \app\commons\Member::decscorein(aid,'seckill',$order['id'],$order['ordernum'],'订单退款扣除消费赠送');
			//优惠券抵扣的返还
			if($order['coupon_rid'] > 0){
				Db::name('coupon_record')->where('aid',aid)->where('bid',bid)->where('mid',$order['mid'])->where('id',$order['coupon_rid'])->update(['status'=>0,'usetime'=>'']);
			}
			//退款退还佣金
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
			\app\commons\System::plog('秒杀订单退款审核通过并退款'.$orderid);
			return json(['status'=>1,'msg'=>'已退款成功']);
		}
	}
	//删除
	public function del(){
		$id = input('post.id/d');
		Db::name('seckill_order')->where('aid',aid)->where('bid',bid)->where('id',$id)->delete();
		\app\commons\System::plog('删除秒杀订单'.implode(',',$id));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
    //送货单
    public function shd(){
        $set = Db::name('admin_set')->where('aid',aid)->find();
        $orderid = input('param.id/d');
        $info = Db::name('seckill_order')->where('aid',aid)->where('id',$orderid)->find();
        $order_goods = Db::name('seckill_order')->where('aid',aid)->where('id',$orderid)->select()->toArray();

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
}
