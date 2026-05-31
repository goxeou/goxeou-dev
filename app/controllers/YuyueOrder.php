<?php


// +----------------------------------------------------------------------
// | 预约-订单记录
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;
class YuyueOrder extends Common
{
	//订单列表
    public function index(){
    	//多商户使用平台服务人员，平台可见多商户订单
        $allBidOrder = false;
        $canPaidan = true;
        $businessArr = [];
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
			if(input('param.bid') == 'all'){
			
			}else{
                if(!$allBidOrder){
                    $where[] = ['bid','=',bid];
                }else{
                    if(is_numeric(input('param.bid'))){
                        $where[] = ['bid','=',input('param.bid')];
                    }
                }
			}
			if(input('param.orderids')) $where[] = ['id','in',input('param.orderids')];
			if(input('param.orderid')) $where[] = ['id','=',input('param.orderid')];
			if(input('param.mid/d')) $where[] = ['mid','=',input('param.mid/d')];
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
	    	if(input('param.worker_id')){
				 	$where[] = ['worker_id','=',input('param.worker_id')];
			}
			$count = 0 + Db::name('yuyue_order')->where($where)->count();
			$list = Db::name('yuyue_order')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			foreach($list as $k=>$vo){
				$member = Db::name('member')->where('id',$vo['mid'])->find();
				$list[$k]['goodsdata'] = '<div style="font-size:12px;float:left;clear:both;margin:1px 0">'.
					'<img src="'.$vo['propic'].'" style="max-width:60px;float:left">'.
					'<div style="float: left;width:160px;margin-left: 10px;white-space:normal;line-height:16px;">'.
						'<div style="width:100%;min-height:25px;max-height:32px;overflow:hidden">'.$vo['proname'].'</div>'.
						'<div style="width:100%;min-height:25px;max-height:32px;overflow:hidden">'.$vo['ggname'].'X'.$vo['num'].'</div>'.
						'<div style="padding-top:0px;color:#f60;font-size:12px">购买价￥'.$vo['product_price'].'</div>'.
					'</div>'.
				'</div>';
				$list[$k]['nickname'] = $member['nickname'];
				$list[$k]['headimg'] = $member['headimg'];
				$list[$k]['platform'] = getplatformname($vo['platform']);
                $paymoney = $vo['totalprice'];
                //包含尾款的订单金额显示判断
                if($vo['balance_price']>0 && $vo['balance_pay_status']==0){
                    $paymoney = round($vo['totalprice'] - $vo['balance_price'],2);
                }
                $list[$k]['paymoney'] = $paymoney;
				$master_order = db('yuyue_worker_order')->field('worker_id,status')->where(['orderid'=>$vo['id'],'ordernum'=>$vo['ordernum']])->find();
				if(false){}else{
                    $worker_id = $master_order['worker_id']?$master_order['worker_id']:$vo['worker_id'];
					if($worker_id){
					    $master = db('yuyue_worker')->where('id',$worker_id)->find();
						$list[$k]['fwname'] = $master['realname'];
						$list[$k]['fwtel'] = $master['tel'];
					}
				}
                if($allBidOrder){
                    $list[$k]['bname'] = $vo['bid']==0?'平台':'';
                    if($vo['bid']>0 && isset($businessArr[$vo['bid']])){
                        $list[$k]['bname'] = $businessArr[$vo['bid']]['name'];
                    }
                }
                $list[$k]['can_paidan'] = $canPaidan;

				$list[$k]['fwstatus'] = $master_order['status'];
				}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$list]);
		}
		$where = [];
		if(input('param.')) $where = input('param.');
		$where = json_encode($where);
		View::assign('where',$where);
		$peisong_set = Db::name('peisong_set')->where('aid',aid)->find();
		if($peisong_set['status']==1 && bid>0 && $peisong_set['businessst']==0 && $peisong_set['make_status']==0) $peisong_set['status'] = 0;
		View::assign('peisong_set',$peisong_set);
		View::assign('express_data',express_data(['aid'=>aid,'bid'=>bid]));
		View::assign('businesslist',$businessArr);
		View::assign('allBidOrder',$allBidOrder);
        $this->defaultSet();
		$text = ['上门服务'=>'上门服务','到店服务'=>'到店服务'];
		View::assign('text',$text);
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
        $allBidOrder = false;
        $where = [];
		$where[] = ['aid','=',aid];
		if(input('param.bid') == 'all'){
			
		}else{
            if(!$allBidOrder){
                $where[] = ['bid','=',bid];
            }else{
                if(is_numeric(input('param.bid'))){
                    $where[] = ['bid','=',input('param.bid')];
                }
            }
		}
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
		$list = Db::name('yuyue_order')->where($where)->order($order)->select()->toArray();
		$title = array('订单号','下单人','商品名称','总价','实付款','尾款金额','支付方式','预约时间','客户信息','服务人员','服务方式','服务状态','客户留言','备注','下单时间','订单状态','表单信息');
		$data = [];
		foreach($list as $k=>$vo){
			$member = Db::name('member')->where('id',$vo['mid'])->find();
			$worker = Db::name('yuyue_worker')->where('id',$vo['worker_id'])->find();
			$worker_order = db('yuyue_worker_order')->field('worker_id,status')->where(['orderid'=>$vo['id']])->find();
			$status='';
			if($vo['status']==0){
				$status = '未支付';
			}elseif($vo['status']==2){
				$status = '已派单';
			}elseif($vo['status']==1){
				$status = '已支付';
			}elseif($vo['status']==3){
				$status = '已完成';
			}elseif($vo['status']==4){
				$status = '已关闭';
			}
			if($vo['balance_price']>0){
				if($vo['balance_pay_status']==0) $status .=' 尾款未支付';
			}
			if($vo['fwtype']==1){
				$fwtype = '到店服务';
			}else if($vo['fwtype']==3){
				$fwtype = '到商家服务';
			}else{
				$fwtype = '上门服务';
			}
			if($worker_order['status']==0) $fwstatus ='待接单';
			if($worker_order['status']==1) $fwstatus ='已接单';
			if($worker_order['status']==2) $fwstatus ='已到达';
			if($worker_order['status']==3) $fwstatus ='已完成';
            $vo['formdata'] = \app\models\Freight::getformdata($vo['id'],'yuyue_order');
            $formdataArr = [];
            $message = '';
            if($vo['formdata']) {
                foreach ($vo['formdata'] as $formdata) {
                    if($formdata[2] != 'upload') {
                        $formdataArr[] = $formdata[0].':'.$formdata[1];
                    }
                }
            }
            $formdatastr = implode("\r\n",$formdataArr);
            $pre_name = '';
			if( $vo['fwtype'] == 2 && $vo['carid']){
				$pre_name = '车辆位置信息：';
			}
			$data1 = [
				' '.$vo['ordernum'],
				$member['nickname'],
				$vo['title'],
				$vo['product_price'],
				$vo['totalprice'],
				$vo['balance_price'],
				$vo['paytype'],
				$vo['yy_time'],
				$vo['linkman'].'('.$vo['tel'].') '." \r\n ".$pre_name.$vo['area'].' '.$vo['address'],
				$worker['realname'].$worker['tel'],
				$fwtype,
				$fwstatus,
				$vo['message'],
				$vo['beizhu'],
				date('Y-m-d H:i:s',$vo['createtime']),
				$status,
                $formdatastr
			];
			$data[] = $data1;
		}
        return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data,'title'=>$title]);
		$this->export_excel($title,$data);
	}
	//订单详情
	public function getdetail(){
		$orderid = input('post.orderid');
        $allBids = false;
        //平台可见商户订单
        $where = [];
        $where[] = ['aid','=',aid];
        $where[] = ['id','=',$orderid];
        if(!$allBids){
            $where[] = ['bid','=',bid];
        }
		$order = Db::name('yuyue_order')->where($where)->find();
		if(empty($order)) showmsg('订单数据有误');

		if($order['coupon_rid']){
			$couponrecord = Db::name('coupon_record')->where('id',$order['coupon_rid'])->find();
		}else{
			$couponrecord = false;
		}
		$formdata = Db::name('freight_formdata')->where('aid',aid)->where('orderid',$orderid)->where('type','yuyue_order')->find();
		$data = [];
		for($i=0;$i<=30;$i++){
			if($formdata['form'.$i]){
				$thisdata = explode('^_^',$formdata['form'.$i]);
				if($thisdata[1]!==''){
					$data[] = $thisdata;
				}
			}
		}
		$order['formdata'] = $data;
		$member = Db::name('member')->field('id,nickname,headimg,realname,tel')->where('id',$order['mid'])->find();
		if(!$member) $member = ['id'=>$order['mid'],'nickname'=>'','headimg'=>''];

		return json(['order'=>$order,'member'=>$member,'couponrecord'=>$couponrecord]);
	}
	//设置备注
	public function setremark(){
		$orderid = input('post.orderid/d');
		$content = input('post.content');
        $allBids = false;
        //平台可见商户订单
        $where = [];
        $where[] = ['aid','=',aid];
        $where[] = ['id','=',$orderid];
        if(!$allBids){
            $where[] = ['bid','=',bid];
        }
        Db::name('yuyue_order')->where($where)->update(['remark'=>$content]);
		return json(['status'=>1,'msg'=>'设置完成']);
	}
	//改价格
	public function changeprice(){
		$orderid = input('post.orderid/d');
		$newprice = input('post.newprice/f');
        $allBids = false;
        //平台可见商户订单
        $where = [];
        $where[] = ['aid','=',aid];
        $where[] = ['id','=',$orderid];
        if(!$allBids){
            $where[] = ['bid','=',bid];
        }
        $ordernum = date('ymdHis').aid.rand(1000,9999);
        $order =  Db::name('yuyue_order')->where($where)->find();
        if($order['status']!=0){
            return json(['status'=>0,'msg'=>'该订单状态不支持改价']);
        }
        Db::name('yuyue_order')->where('id',$order['id'])->update(['totalprice'=>$newprice,'ordernum'=>$ordernum]);
        Db::name('payorder')->where('orderid',$orderid)->where('type','yuyue')->update(['money'=>$newprice,'ordernum'=>$ordernum]);
		return json(['status'=>1,'msg'=>'修改完成']);
	}
	//关闭订单
	public function closeOrder(){
		$orderid = input('post.orderid/d');
        $allBids = false;
        //平台可见商户订单
        $where = [];
        $where[] = ['aid','=',aid];
        $where[] = ['id','=',$orderid];
        if(!$allBids){
            $where[] = ['bid','=',bid];
        }
		$order = Db::name('yuyue_order')->where($where)->find();
//		if(bid != 0 && $order['bid']!=bid) showmsg('无权限操作');
		if(!$order || $order['status']>1){
			return json(['status'=>0,'msg'=>'关闭失败,订单信息错误']);
		}
		//优惠券抵扣的返还
		if($order['coupon_rid'] > 0){
			//查看是不是计次卡
			$record = Db::name('coupon_record')->where('aid',aid)->where('mid',$order['mid'])->where('id',$order['coupon_rid'])->find();
			if(false){}else{
				Db::name('coupon_record')->where('aid',aid)->where('mid',$order['mid'])->where('id',$order['coupon_rid'])->update(['status'=>0,'usetime'=>'']);	
			} 
		}	
		if($order['status']==1){
			//如果订单金额大于0 走退款
			if($order['totalprice']>0){
				$rs = \app\commons\Order::refund($order,$order['totalprice'],'后台退款');
				if($rs['status']==0){
					return json(['status'=>0,'msg'=>$rs['msg']]);
				}
			}
			Db::name('yuyue_order')->where('id',$orderid)->where('aid',aid)->where('bid',$order['bid'])->update(['status'=>4,'refund_money'=>$order['totalprice'],'refund_status'=>2]);
			//积分抵扣的返还
			if($order['scoredkscore'] > 0){
				\app\commons\Member::addscore(aid,$order['mid'],$order['scoredkscore'],'订单退款返还');
			}
			//扣除消费赠送积分
            \app\commons\Member::decscorein(aid,'yuyue',$order['id'],$order['ordernum'],'订单退款扣除消费赠送');
			//取消配送订单
			Db::name('yuyue_worker_order')->where('orderid',$orderid)->where('aid',aid)->where('bid',$order['bid'])->update(['status'=>-1]);

			//退款成功通知
			$tmplcontent = [];
			$tmplcontent['first'] = '您的订单已经完成退款，¥'.$order['totalprice'].'已经退回您的付款账户，请留意查收。';
			$tmplcontent['remark'] = '请点击查看详情~';
			$tmplcontent['orderProductPrice'] = (string) $order['totalprice'];
			$tmplcontent['orderProductName'] = $order['title'];
			$tmplcontent['orderName'] = $order['ordernum'];
            $tmplcontentNew = [];
            $tmplcontentNew['character_string1'] = $order['ordernum'];//订单编号
            $tmplcontentNew['thing2'] = $order['title'];//商品名称
            $tmplcontentNew['amount3'] = $order['totalprice'];//退款金额
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
		}else{
			$rs = Db::name('yuyue_order')->where('id',$orderid)->where('aid',aid)->where('bid',$order['bid'])->update(['status'=>4]);
		}
		
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//改为已支付
	public function ispay(){
		if(bid > 0) showmsg('无权限操作');
		$orderid = input('post.orderid/d');
        $allBids = false;
        //平台可见商户订单
        $where = [];
        $where[] = ['aid','=',aid];
        $where[] = ['id','=',$orderid];
        if(!$allBids){
            $where[] = ['bid','=',bid];
        }
		$order = Db::name('yuyue_order')->where($where)->find();
        if($order['status']!=0){
            return json(['status'=>0,'msg'=>'订单状态不支持该操作']);
        }
		Db::name('yuyue_order')->where('id',$order['id'])->update(['status'=>1,'paytime'=>time(),'paytype'=>'后台支付']);
		Db::name('payorder')->where('orderid',$order['id'])->where('type','yuyue')->update(['status'=>1,'paytime'=>time(),'paytype'=>'后台支付']);
		if($order['worker_id']){
			//如果用户已经选择服务人员则支付后直接进行派单
			$rs = \app\models\YuyueWorkerOrder::create($order,$order['worker_id'],'');
			if($rs['status']==0) return json($rs);
			\app\commons\System::plog('预约服务派单'.$orderid);
		}
		//奖励积分
//		$order = Db::name('yuyue_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->find();
		if($order['givescore'] > 0){
			\app\commons\Member::addscore(aid,$order['mid'],$order['givescore'],'购买产品奖励'.t('积分'));
		}
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//发货
	public function sendExpress(){
		$orderid = input('post.orderid/d');
        $allBids = false;
        //平台可见商户订单
        $where = [];
        $where[] = ['aid','=',aid];
        $where[] = ['id','=',$orderid];
        if(!$allBids){
            $where[] = ['bid','=',bid];
        }
		$order = Db::name('yuyue_order')->where($where)->find();
		if(empty($order)) showmsg('订单数据有误');
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
			Db::name('yuyue_order')->where('aid',aid)->where('id',$orderid)->update(['express_com'=>$express_com,'express_no'=>$express_no]);
			return json(['status'=>1,'msg'=>'操作成功']);
		}

		Db::name('yuyue_order')->where('aid',aid)->where('id',$orderid)->update(['express_com'=>$express_com,'express_no'=>$express_no,'send_time'=>time(),'status'=>2]);


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

		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//查物流
	public function getExpress(){
		$orderid = input('post.orderid/d');
		$order = Db::name('yuyue_order')->where('id',$orderid)->where('aid',aid)->find();
		$list =$this->getjindu($order['worker_orderid']);
		return json(['status'=>1,'data'=>$list]);
	}
	//退款审核
	public function refundCheck(){
		$orderid = input('post.orderid/d');
		$st = input('post.st/d');
		$remark = input('post.remark');
		$order = Db::name('yuyue_order')->where('id',$orderid)->where('aid',aid)->find();
		if(bid != 0 && $order['bid']!=bid) showmsg('无权限操作');
		if($st==2){
			Db::name('yuyue_order')->where('id',$orderid)->where('aid',aid)->update(['refund_status'=>3,'refund_checkremark'=>$remark]);
			//退款申请驳回通知
			$tmplcontent = [];
			$tmplcontent['first'] = '您的退款申请被商家驳回，可与商家协商沟通。';
			$tmplcontent['remark'] = $remark.'，请点击查看详情~';
			$tmplcontent['orderProductPrice'] = (string) $order['refund_money'];
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

			return json(['status'=>1,'msg'=>'退款已驳回']);
		}elseif($st == 1){
			if($order['status']!=1 && $order['status']!=2){
				return json(['status'=>0,'msg'=>'该订单状态不允许退款']);
			}
			$rs = \app\commons\Order::refund($order,$order['refund_money'],$order['refund_reason']);
			if($rs['status']==0){
				return json(['status'=>0,'msg'=>$rs['msg']]);
			}

			Db::name('yuyue_order')->where('id',$orderid)->where('aid',aid)->update(['status'=>4,'refund_status'=>2]);
				
			//取消配送订单
			Db::name('yuyue_worker_order')->where('id',$order['worker_orderid'])->update(['status'=>-1]);

            //退款减去商户销量
            $refund_num = Db::name('yuyue_order')->where('id',$orderid)->sum('num');
            \app\models\Payorder::addSales($orderid,'yuyue',aid,$order['bid'],-$refund_num);


			//积分抵扣的返还
			if($order['scoredkscore'] > 0){
				\app\commons\Member::addscore(aid,$order['mid'],$order['scoredkscore'],'订单退款返还');
			}
			//扣除消费赠送积分
            \app\commons\Member::decscorein(aid,'yuyue',$order['id'],$order['ordernum'],'订单退款扣除消费赠送');
			//退款退还佣金
			//退款成功通知
			$tmplcontent = [];
			$tmplcontent['first'] = '您的订单已经完成退款，¥'.$order['refund_money'].'已经退回您的付款账户，请留意查收。';
			$tmplcontent['remark'] = '请点击查看详情~';
			$tmplcontent['orderProductPrice'] = (string) $order['refund_money'];
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
			return json(['status'=>1,'msg'=>'已退款成功']);
		}
	}
	//删除
	public function del(){
		$id = input('post.id/d');
		if(bid == 0){
            $where = $where1 = [];
            $where[] = ['aid','=',aid];
            $where[] = ['id','=',$id];
            $where1[] = ['aid','=',aid];
            $where1[] = ['orderid','=',$id];
            $allBids = false;
            //平台可见商户订单
            if(!$allBids){
                $where[] = ['bid','=',bid];
                $where1[] = ['bid','=',bid];
            }

			Db::name('yuyue_order')->where($where)->delete();
			//删除派单的订单
			Db::name('yuyue_worker_order')->where($where1)->delete();
		}else{
			Db::name('yuyue_order')->where('aid',aid)->where('bid',bid)->where('id',$id)->delete();
			//删除派单的订单
			Db::name('yuyue_worker_order')->where('aid',aid)->where('bid',bid)->where('orderid',$id)->delete();
		}
		return json(['status'=>1,'msg'=>'删除成功']);
	}

	public function getjindu($express_no){
			$psorderinfo =Db::name('yuyue_worker_order')->where('id',$express_no)->find();
			$psuser = Db::name('yuyue_worker')->where('id',$psorderinfo['worker_id'])->find();
			//查看是服务方式
			$order = Db::name('yuyue_order')->where('id',$psorderinfo['orderid'])->find();
			if($order['fwtype']==1){
					$list = [];
					if($psorderinfo['createtime']){
						$list[] = ['time'=>date('Y-m-d H:i',$psorderinfo['createtime']),'context'=>'已发布服务单'];
					}
					if($psorderinfo['starttime']){
						$list[] = ['time'=>date('Y-m-d H:i',$psorderinfo['starttime']),'context'=>'等待顾客'.$order['linkman'].'('.$order['tel'].')'.'到店'];
					}
					if($psorderinfo['daodiantime']){
						$list[] = ['time'=>date('Y-m-d H:i',$psorderinfo['daodiantime']),'context'=>'顾客已到店'];
					}
					if($psorderinfo['endtime']){
						$list[] = ['time'=>date('Y-m-d H:i',$psorderinfo['endtime']),'context'=>'服务完成'];
					}
			}else{
					$list = [];
					if($psorderinfo['createtime']){
						$list[] = ['time'=>date('Y-m-d H:i',$psorderinfo['createtime']),'context'=>'已发布服务单'];
					}
					if(false){}else{
						if($psorderinfo['starttime']){
							$list[] = ['time'=>date('Y-m-d H:i',$psorderinfo['starttime']),'context'=>'服务人员'.$psuser['realname'].'('.$psuser['tel'].')'.'正在途中'];
						}
						if($psorderinfo['daodiantime']){
							$list[] = ['time'=>date('Y-m-d H:i',$psorderinfo['daodiantime']),'context'=>'服务人员已到达现场'];
						}
					}
					if($psorderinfo['endtime']){
						$list[] = ['time'=>date('Y-m-d H:i',$psorderinfo['endtime']),'context'=>'服务完成'];
					}
			}

			$list = array_reverse($list);
			return $list;
	}
	
	function collect(){ //确认完成
		$post = input('post.');
		$orderid = intval($post['orderid']);
		$order = Db::name('yuyue_order')->where('aid',aid)->where('id',$orderid)->find();
		$psorder = Db::name('yuyue_worker_order')->where('aid',aid)->where('id',$order['worker_orderid'])->find();
		if($psorder['status']!=2 && $psorder['status']!=1) return json(['status'=>0,'msg'=>'订单状态不符合']);
		if($order['balance_price']>0 && $order['balance_pay_status']!=1){
			return json(['status'=>0,'msg'=>'请等顾客支付尾款后，再点击完成']);
		}
		$updata = [];
		$updata['status'] = 3;
		$updata['endtime'] = time();
		Db::name('yuyue_worker')->where('id',$psorder['worker_id'])->inc('totalnum')->update();
		db('yuyue_order')->where(['aid'=>aid,'id'=>$orderid])->update(['status'=>3,'collect_time'=>time()]);
		$rs = \app\commons\Order::collect($order,'yuyue');
		if($rs['status'] == 0) return json($rs);
		\app\commons\YuyueWorker::addmoney(aid,$psorder['bid'],$psorder['worker_id'],$psorder['ticheng'],'服务提成');
		Db::name('yuyue_worker_order')->where('id',$order['worker_orderid'])->update($updata);
		return json(['status'=>1,'msg'=>'操作成功']);
	}
    function defaultSet(){
        $set = Db::name('yuyue_set')->where('aid',aid)->where('bid',bid)->find();
        if(!$set){
            Db::name('yuyue_set')->insert(['aid'=>aid,'bid' => bid]);
        }
    }
    //打印小票
    public function wifiprint(){
        }
}
