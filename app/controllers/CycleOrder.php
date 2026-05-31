<?php


// +----------------------------------------------------------------------
// | 周期购-商品订单
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;
class CycleOrder extends Common
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
			if(input('param.orderid')) $where[] = ['id','=',input('param.orderid')];
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
			$count = 0 + Db::name('cycle_order')->where($where)->count();
			$list = Db::name('cycle_order')->where($where)->page($page,$limit)->order($order)->select()->toArray();

			foreach($list as $k=>$vo){

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
				$list[$k]['platform'] = getplatformname($vo['platform']);
				$stage = Db::name('cycle_order_stage')->where('orderid',$vo['id'])->order('cycle_number asc')->limit(0,3)->select();
				$stage_status = [];
				foreach($stage as $ke=>$v){
                    if($vo['freight_type'] ==1 ){
                        $status = ['1' => '待提货','2'=>'已提货','3'=>'已完成'];
                    }  else{
                        $status = ['1' => '待发货','2'=>'已发货','3'=>'已完成'];
                    }
				    
                    $stage_status[$ke]['title'] = '<div style="padding: 5px 0px">第'.$v['cycle_number'].'期<span style="margin-left: 5px;color: red">'.$status[$v['status']].'<span></div>';
                }
			
                if($vo['status'] > 0){
                    $list[$k]['stage_list'] = $stage_status?$stage_status:[];
                }

			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$list]);
		}
		$peisong_set = Db::name('peisong_set')->where('aid',aid)->find();
		if($peisong_set['status']==1 && bid>0 && $peisong_set['businessst']==0 && $peisong_set['make_status']==0) $peisong_set['status'] = 0;
		View::assign('peisong_set',$peisong_set);
		View::assign('express_data',express_data(['aid'=>aid,'bid'=>bid]));
        $this->defaultSet();
		return View::fetch();
    }

    public function cycle_list(){
        $status = input('param.status');
        if(request()->isAjax()){
            $page = input('param.page');
            $limit = input('param.limit');
            $order = 'os.status asc,os.cycle_strtotime asc,os.cycle_number asc';
            $where = [];
            $where[] = ['os.aid','=',aid];
            $where[] = ['os.bid','=',bid];
            $where[] = ['o.status','>',0];
            $where[] = ['o.status','<>',4];
            if(input('param.orderid')) $where[] = ['os.orderid','=',input('param.orderid')];
            if(input('param.linkman')) $where[] = ['o.linkman','like','%'.input('param.linkman').'%'];
            if(input('param.tel')) $where[] = ['o.tel','like','%'.input('param.tel').'%'];
            if(input('param.ordernum')) $where[] = ['os.ordernum','like','%'.input('param.ordernum').'%'];
            if(input('param.ctime') ){
                $ctime = explode(' ~ ',input('param.ctime'));
                $where[] = ['o.createtime','>=',strtotime($ctime[0])];
                $where[] = ['o.createtime','<',strtotime($ctime[1]) + 86400];
            }
            $where[] = ['os.status','>',0];
            if($status) $where[] = ['os.status','=',$status];
            $count = 0 + Db::name('cycle_order_stage')
                    ->alias('os')
                    ->join('cycle_order o','o.id = os.orderid')
                    ->where($where)
                    ->count();
            $list = Db::name('cycle_order_stage')
                ->alias('os')
                ->join('cycle_order o','o.id = os.orderid')
                ->where($where)
                ->page($page,$limit)
                ->field('os.*,o.title,o.createtime,o.address,o.linkman,o.area,o.tel,o.freight_text,o.freight_type')
                ->order($order)
                ->select();
            return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$list]);
        }

        $peisong_set = Db::name('peisong_set')->where('aid',aid)->find();
        if($peisong_set['status']==1 && bid>0 && $peisong_set['businessst']==0 && $peisong_set['make_status']==0) $peisong_set['status'] = 0;
        View::assign('peisong_set',$peisong_set);
        View::assign('status',$status);
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
		if(input('param.teamid')) $where[] = ['teamid','=',input('param.teamid')];
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
		$list = Db::name('cycle_order')->where($where)->order($order)->page($page,$limit)->select()->toArray();
        $count = Db::name('cycle_order')->where($where)->order($order)->count();
		$title = array('订单号','下单人','商品名称','规格数量','总价','实付款','支付方式','姓名','电话','收货地址','配送方式','快递信息','客户留言','备注','下单时间','状态','其他');
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
//				$vo['freight_time'],
				($vo['kuaidi'] ? $vo['kuaidi'].'('.$vo['danhao'].')':''),
				$vo['message'],
				$vo['remark'],
				date('Y-m-d H:i:s',$vo['createtime']),
				$status,
                ''
			];
            //配送自定义表单
            $vo['formdata'] = \app\models\Freight::getformdata($vo['id'],'cycle_order');
            if($vo['formdata']) {
                foreach ($vo['formdata'] as $formdata) {
                    if($formdata[2] != 'upload') {
                        if($formdata[0] == '备注') {
                            $data[$k][14] = $formdata[1];
                        } else {
                            $data[$k][14] .= $formdata[0].':'.$formdata[1]."\r\n";
                        }
                    }
                }
            }
		}
        return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data,'title'=>$title]);
		$this->export_excel($title,$data);
	}
	//订单详情
//订单详情
    public function getdetail(){
        $orderid = input('post.orderid');

        $order = Db::name('cycle_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->find();
        if($order['coupon_rid']){
            $couponrecord = Db::name('coupon_record')->where('id',$order['coupon_rid'])->find();
        }else{
            $couponrecord = false;
        }
        $member = Db::name('member')->field('id,nickname,headimg,realname,tel')->where('id',$order['mid'])->find();
        if(!$member) $member = ['id'=>$order['mid'],'nickname'=>'','headimg'=>''];

        $comdata = array();
        $comdata['parent1'] = ['mid'=>'','nickname'=>'','headimg'=>'','money'=>0,'score'=>0];
        $comdata['parent2'] = ['mid'=>'','nickname'=>'','headimg'=>'','money'=>0,'score'=>0];
        $comdata['parent3'] = ['mid'=>'','nickname'=>'','headimg'=>'','money'=>0,'score'=>0];
        if($order['parent1']){
            $parent1 = Db::name('member')->where('id',$order['parent1'])->find();
            $comdata['parent1']['mid'] = $order['parent1'];
            $comdata['parent1']['nickname'] = $parent1['nickname'];
            $comdata['parent1']['headimg'] = $parent1['headimg'];
            $comdata['parent1']['money'] += $order['parent1commission'];
            $comdata['parent1']['score'] += $order['parent1score'];
        }
        if($order['parent2']){
            $parent2 = Db::name('member')->where('id',$order['parent2'])->find();
            $comdata['parent2']['mid'] = $order['parent2'];
            $comdata['parent2']['nickname'] = $parent2['nickname'];
            $comdata['parent2']['headimg'] = $parent2['headimg'];
            $comdata['parent2']['money'] += $order['parent2commission'];
            $comdata['parent2']['score'] += $order['parent2score'];
        }
        if($order['parent3']){
            $parent3 = Db::name('member')->where('id',$order['parent3'])->find();
            $comdata['parent3']['mid'] = $order['parent3'];
            $comdata['parent3']['nickname'] = $parent3['nickname'];
            $comdata['parent3']['headimg'] = $parent3['headimg'];
            $comdata['parent3']['money'] += $order['parent3commission'];
            $comdata['parent3']['score'] += $order['parent3score'];
        }

        $order['formdata'] = \app\models\Freight::getformdata($order['id'],'cycle_order');

        //计算money
        $stage = Db::name('cycle_order_stage')->where('orderid',$order['id'])->select();
        $money = 0;
        foreach($stage as $key=>$val){
            if($val['status'] == 1){
                $money = bcadd($money,$val['sell_price']*$val['num'],2);
            }
        }

        if($order['leveldk_money']){
            $money = bcsub($money,$order['leveldk_money'],2);
        }
        if($order['coupon_money']){
            $money = bcsub($money,$order['coupon_money'],2);
        }

        if($order['freight_price']){
            $money = bcadd($money,$order['freight_price'],2);
        }
        if($money >= $order['totalprice']){
            $money = $order['totalprice'];
        }
        $order['refund_money'] =   $money;

        return json(['order'=>$order,'couponrecord'=>$couponrecord,'member'=>$member,'comdata'=>$comdata,'refund_money' => $money]);
    }
    public function getStatedetail(){
        $orderid = input('post.orderid');
        $state =  Db::name('cycle_order_stage')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->find();

        $order = Db::name('cycle_order')->where('aid',aid)->where('bid',bid)->where('id',$state['orderid'])->find();
        if($order['coupon_rid']){
            $couponrecord = Db::name('coupon_record')->where('id',$order['coupon_rid'])->find();
        }else{
            $couponrecord = false;
        }
        $member = Db::name('member')->field('id,nickname,headimg,realname,tel')->where('id',$order['mid'])->find();
        if(!$member) $member = ['id'=>$order['mid'],'nickname'=>'','headimg'=>''];

        $order['formdata'] = \app\models\Freight::getformdata($order['id'],'cycle_order');

        return json(['order'=>$order,'couponrecord'=>$couponrecord,'member'=>$member,'state' =>$state]);
    }
	//设置备注
	public function setremark(){
		$orderid = input('post.orderid/d');
		$content = input('post.content');
		Db::name('cycle_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->update(['remark'=>$content]);
		\app\commons\System::plog('设置周期购订单备注'.$orderid);
		return json(['status'=>1,'msg'=>'设置完成']);
	}
	//改价格
	public function changeprice(){
		$orderid = input('post.orderid/d');
		$newprice = input('post.newprice/f');
		Db::name('cycle_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->update(['totalprice'=>$newprice,'ordernum'=>date('ymdHis').aid.rand(1000,9999)]);
		\app\commons\System::plog('修改周期购订单价格'.$orderid);
		return json(['status'=>1,'msg'=>'修改完成']);
	}
	//关闭订单
	public function closeOrder(){
		$orderid = input('post.orderid/d');
		$order = Db::name('cycle_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->find();
		if(!$order || $order['status']!=0){
			return json(['status'=>0,'msg'=>'关闭失败,订单状态错误']);
		}
		Db::name('collage_guige')->where('id',$order['ggid'])->update(['stock'=>Db::raw("stock+".$order['num']),'sales'=>Db::raw("sales-".$order['num'])]);
		Db::name('collage_product')->where('aid',aid)->where('bid',bid)->where('id',$order['proid'])->update(['stock'=>Db::raw("stock+".$order['num']),'sales'=>Db::raw("sales-".$order['num'])]);
		//优惠券抵扣的返还
		if($order['coupon_rid'] > 0){
			Db::name('coupon_record')->where('aid',aid)->where('bid',bid)->where('mid',$order['mid'])->where('id',$order['coupon_rid'])->update(['status'=>0,'usetime'=>'']);
		}
		$rs = Db::name('cycle_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->update(['status'=>4]);
		\app\commons\System::plog('关闭周期购订单'.$orderid);
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//改为已支付
	public function ispay(){
		$orderid = input('post.orderid/d');
		Db::name('cycle_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->update(['status'=>1,'paytime'=>time(),'paytype'=>'后台支付']);
		//奖励积分
		$order = Db::name('cycle_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->find();
		if($order['givescore'] > 0){
			\app\commons\Member::addscore(aid,$order['mid'],$order['givescore'],'购买产品奖励'.t('积分'));
		}
        Db::name('cycle_order_stage')->where('orderid',$orderid)->update(['status'=>1]);
		\app\commons\System::plog('周期购订单改为已支付'.$orderid);
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//发货
	public function sendExpress(){
		$orderid = input('post.orderid/d');
		$order = Db::name('cycle_order_stage')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->find();
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
			Db::name('cycle_order_stage')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->update(['express_com'=>$express_com,'express_no'=>$express_no]);
			return json(['status'=>1,'msg'=>'操作成功']);
		}

		Db::name('cycle_order_stage')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->update(['express_com'=>$express_com,'express_no'=>$express_no,'send_time'=>time(),'status'=>2]);
		//修改总订单
        Db::name('cycle_order')->where('aid',aid)->where('bid',bid)->where('id',$order['orderid'])->update(['status'=>2]);
        $ysorder =   Db::name('cycle_order')->where('aid',aid)->where('bid',bid)->where('id',$order['orderid'])->find();
		//订单发货通知
		$tmplcontent = [];
		$tmplcontent['first'] = '您的周期购订单已发货';
		$tmplcontent['remark'] = '请点击查看详情~';
		$tmplcontent['keyword1'] = $ysorder['title'];
		$tmplcontent['keyword2'] = $express_com;
		$tmplcontent['keyword3'] = $express_no;
		$tmplcontent['keyword4'] = $ysorder['linkman'].' '.$ysorder['tel'];
        $tmplcontentNew = [];
        $tmplcontentNew['thing4'] = $ysorder['title'];//商品名称
        $tmplcontentNew['thing13'] = $express_com;//快递公司
        $tmplcontentNew['character_string14'] = $express_no;//快递单号
        $tmplcontentNew['thing16'] = $ysorder['linkman'].' '.$ysorder['tel'];//收货人
		\app\commons\Wechat::sendtmpl(aid,$order['mid'],'tmpl_orderfahuo',$tmplcontent,m_url('pages/my/usercenter'),$tmplcontentNew);
		//订阅消息
		$tmplcontent = [];
		$tmplcontent['thing2'] = $ysorder['title'];
		$tmplcontent['thing7'] = $express_com;
		$tmplcontent['character_string4'] = $express_no;
		$tmplcontent['thing11'] = $ysorder['address'];

		$tmplcontentnew = [];
		$tmplcontentnew['thing29'] = $ysorder['title'];
		$tmplcontentnew['thing1'] = $express_com;
		$tmplcontentnew['character_string2'] = $express_no;
		$tmplcontentnew['thing9'] = $ysorder['address'];
		\app\commons\Wechat::sendwxtmpl(aid,$order['mid'],'tmpl_orderfahuo',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);

		//短信通知
		$member = Db::name('member')->where('id',$order['mid'])->find();
		if($member['tel']){
			$tel = $member['tel'];
		}else{
			$tel = $order['tel'];
		}
		$rs = \app\commons\Sms::send(aid,$tel,'tmpl_orderfahuo',['ordernum'=>$order['ordernum'],'express_com'=>$express_com,'express_no'=>$express_no]);
		\app\commons\System::plog('周期购订单发货'.$orderid);
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//查物流
	public function getExpress(){
		$orderid = input('post.orderid/d');
		$order = Db::name('cycle_order_stage')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->find();
		if($order['freight_type'] == '10'){
			$data = Db::name('freight_type10_record')->where('id',$order['express_no'])->find();
			return json(['status'=>1,'data'=>$data]);
		}
		if($order['express_com'] == '顺丰速运'){
			$totel = $order['tel'];
			$order['express_no'] = $order['express_no'].":".substr($totel,-4);
		}
		$list = \app\commons\Common::getwuliu($order['express_no'],$order['express_com'], '', aid);
		return json(['status'=>1,'data'=>$list]);
	}
    //核销并确认收货
    function ordercollect(){
        $post = input('post.');
        $orderid = intval($post['orderid']);
        $order = Db::name('cycle_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->find();
        
        if(!$order || !in_array($order['status'], [1,2])){
            return json(['status'=>0,'msg'=>'订单状态不符合完成要求']);
        }
        $order_stage = Db::name('cycle_order_stage')
            ->where('status',1)
            ->where('orderid',$order['id'])
            ->count();
        if($order_stage > 0){
            return json(['status'=>0,'msg'=>'订单存在没有完成的周期计划']);
        }
        $data = array();
        $data['aid'] = aid;
        $data['bid'] = bid;
        $data['uid'] = $this->uid;
        $data['mid'] = $order['mid'];
        $data['orderid'] = $order['id'];
        $data['ordernum'] = $order['ordernum'];
        $data['title'] = $order['title'];
        $data['type'] = 'collage';
        $data['createtime'] = time();
//        $data['remark'] = '核销员['.$this->user['un'].']核销';
//        Db::name('hexiao_order')->insert($data);

        $rs = \app\commons\Order::collect($order, 'cycle');
        if($rs['status']==0) return $rs;
        Db::name('cycle_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->update(['status'=>3,'collect_time'=>time()]);
        
        Db::name('cycle_order_stage')->where('aid',aid)->where('bid',bid)->where('orderid',$orderid)->update(['status'=>3,'collect_time'=>time()]);
        
        \app\commons\Member::uplv(aid,$order['mid']);
        \app\commons\System::plog('周期购订单核销确认收货'.$orderid);
        return json(['status'=>1,'msg'=>'订单完成']);
    }

    /**
     * 周期订单收货
     */
    public function orderStageCollect(){
        $post = input('post.');
        $orderid = intval($post['orderid']);
        $order = Db::name('cycle_order_stage')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->find();

        if(!$order || !in_array($order['status'], [2])){
            return json(['status'=>0,'msg'=>'订单状态不符合完成要求']);
        }
        Db::name('cycle_order_stage')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->update(['status'=>3,'collect_time'=>time()]);


        $order_stage_count = Db::name('cycle_order_stage')
            ->where('status','in','0,1,2')
            ->where('orderid',$order['orderid'])
            ->count();
        if($order_stage_count == 0){
            Db::name('cycle_order')->where('aid',aid)->where('bid',bid)->where('id',$order['orderid'])->update(['status'=>3,'collect_time'=>time()]);

            $cycle_order = Db::name('cycle_order')->where('aid',aid)->where('bid',bid)->where('id',$order['orderid'])->find();
            $rs = \app\commons\Order::collect($cycle_order, 'cycle');
            if($rs['status']==0) return $rs;
        }
        
        \app\commons\System::plog('周期购周期订单确认收货'.$orderid);
        return json(['status'=>1,'msg'=>'订单完成']);
    }
    public function orderHexiao(){
        $post = input('post.');
        $orderid = intval($post['orderid']);
        $order = Db::name('cycle_order_stage')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->find();
        
        if(!$order || !in_array($order['status'], [1,2]) ){
            return json(['status'=>0,'msg'=>'订单状态不符合完成要求']);
        }
        $cycle_order = Db::name('cycle_order')->where('aid',aid)->where('bid',bid)->where('id',$order['orderid'])->find();
        if(!$cycle_order ||  $cycle_order['freight_type'] !=1){
            return json(['status'=>0,'msg'=>'订单状态不符合完成要求']);
        }
        Db::name('cycle_order_stage')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->update(['status'=>3,'collect_time'=>time()]);
        
        $order_stage_count = Db::name('cycle_order_stage')
            ->where('status','in','0,1,2')
            ->where('orderid',$order['orderid'])
            ->count();
        if($order_stage_count == 0){
            Db::name('cycle_order')->where('aid',aid)->where('bid',bid)->where('id',$order['orderid'])->update(['status'=>3,'collect_time'=>time()]);

            $rs = \app\commons\Order::collect($cycle_order, 'cycle');
            if($rs['status']==0) return $rs;
        }else{
            Db::name('cycle_order')->where('aid',aid)->where('bid',bid)->where('id',$order['orderid'])->update(['status'=>2]);
        }

        \app\commons\System::plog('周期购周期订单确认核销'.$orderid);
        return json(['status'=>1,'msg'=>'订单完成']);
    }
	//核销并确认收货
    function orderHexiao1(){
        $post = input('post.');
        $orderid = intval($post['orderid']);
        $order = Db::name('cycle_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->find();
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
        $data['type'] = 'collage';
        $data['createtime'] = time();
//        $data['remark'] = '核销员['.$this->user['un'].']核销';
//        Db::name('hexiao_order')->insert($data);

        $rs = \app\commons\Order::collect($order, 'cycle');
        if($rs['status']==0) return $rs;
        Db::name('cycle_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->update(['status'=>3,'collect_time'=>time()]);
        \app\commons\Member::uplv(aid,$order['mid']);
        \app\commons\System::plog('周期购订单核销确认收货'.$orderid);
        return json(['status'=>1,'msg'=>'核销成功']);
    }
    //退款
    public function refund(){
        $orderid = input('post.orderid/d');
        $reason = input('post.reason');
        $refund_money = input('post.refund_money');
        $order = Db::name('cycle_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->find();
        if($order['status']!=1 && $order['status']!=2){
            return json(['status'=>0,'msg'=>'该订单状态不允许退款']);
        }
        if(bccomp($refund_money,$order['totalprice'],2) > 0){
            return json(['status'=>0,'msg'=>'退款金额不能超出订单实际付款金额']);
        }
        $total_refund_money =  bcadd($order['refund_total_money'],$refund_money,2);
//        if($total_refund_money  > $order['totalprice']){
//            $sy_money = bcsub($order['totalprice'],$order['refund_total_money'],2);
//            return json(['status'=>0,'msg'=>'该订单还可以退款：￥'.$sy_money]);
//        }
        Db::name('cycle_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->update(['refund_status'=>2, 'refund_money' => $refund_money,'refund_total_money' => $total_refund_money,'refund_reason' => $reason,'status' =>4]);
        if($refund_money > 0) {
            $rs = \app\commons\Order::refund($order,$refund_money,$reason);
            if($rs['status']==0){
                return json(['status'=>0,'msg'=>$rs['msg']]);
            }
        }

        //退款减去商户销量
        $refund_num = Db::name('cycle_order')->where('id',$orderid)->sum('num');
        \app\models\Payorder::addSales($orderid,'cycle',aid,$order['bid'],-$refund_num);
        

        //积分抵扣的返还
        if($order['scoredkscore'] > 0){
            \app\commons\Member::addscore(aid,$order['mid'],$order['scoredkscore'],'订单退款返还');
        }
        //扣除消费赠送积分
        \app\commons\Member::decscorein(aid,'cycle',$order['id'],$order['ordernum'],'订单退款扣除消费赠送');
        //优惠券抵扣的返还
        if($order['coupon_rid'] > 0){
            Db::name('coupon_record')->where('aid',aid)->where('bid',bid)->where('mid',$order['mid'])->where('id',$order['coupon_rid'])->update(['status'=>0,'usetime'=>'']);
        }
        //退款退还佣金
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

        \app\commons\System::plog('周期购订单退款'.$orderid);
        return json(['status'=>1,'msg'=>'已退款成功']);
    }
	//退款审核
	public function refundCheck(){
		$orderid = input('post.orderid/d');
		$st = input('post.st/d');
		$remark = input('post.remark');
		$order = Db::name('cycle_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->find();
		if($st==2){
			Db::name('cycle_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->update(['refund_status'=>3,'refund_checkremark'=>$remark]);

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

			\app\commons\System::plog('周期购订单退款审核驳回'.$orderid);
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
            $total_refund_money =  bcadd($order['refund_total_money'],$order['refund_money'],2);
			Db::name('cycle_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->update(['status'=>4,'refund_status'=>2,'refund_total_money' => $total_refund_money]);

            //退款减去商户销量
            $refund_num = Db::name('cycle_order')->where('id',$orderid)->sum('num');
            \app\models\Payorder::addSales($orderid,'cycle',aid,$order['bid'],-$refund_num);
			//积分抵扣的返还
			if($order['scoredkscore'] > 0){
				\app\commons\Member::addscore(aid,$order['mid'],$order['scoredkscore'],'订单退款返还');
			}
			//扣除消费赠送积分
        	\app\commons\Member::decscorein(aid,'cycle',$order['id'],$order['ordernum'],'订单退款扣除消费赠送');
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
			\app\commons\System::plog('周期购订单退款审核通过并退款'.$orderid);
			return json(['status'=>1,'msg'=>'已退款成功']);
		}
	}
	//删除
	public function del(){
		$id = input('post.id/d');
		Db::name('cycle_order')->where('aid',aid)->where('bid',bid)->where('id',$id)->delete();
        Db::name('cycle_order_stage')->where('aid',aid)->where('bid',bid)->where('orderid',$id)->delete();
		\app\commons\System::plog('删除周期购订单'.implode(',',$id));
		return json(['status'=>1,'msg'=>'删除成功']);
	}

    //送货单
    public function shd(){
        $orderid = input('param.id/d');
        $info = Db::name('cycle_order_stage')
            ->where('aid',aid)
            ->where('id',$orderid)
            ->find();
        if(!$info || (bid !=0 && $info['bid'] != bid)) showmsg('订单不存在');
        $order = Db::name('cycle_order')->where('id',$info['orderid'])->where('aid',aid)->find();
        $info['linkman'] = $order['linkman'];
        $info['tel'] = $order['tel'];
        $info['company'] = $order['company'];
        $info['freight_price'] = $order['freight_price'];
        $info['totalprice'] = $order['sell_price']*$order['num'];
        $info['address'] = $order['address'];
        $info['area'] = $order['area'];
        $info['remark'] = $order['remark'];
        $totalnum = 0;

        $member = Db::name('member')->where('id',$info['mid'])->find();
        $userlevel = Db::name('member_level')->where('aid',aid)->where('id',$member['levelid'])->find();
        if($userlevel && $userlevel['discount']>0 && $userlevel['discount']<10){
            $discount = $userlevel['discount']*0.1; //会员折扣
        }else{
            $discount = 1;
        }
        $order_goods2[] = $order;

        if(!getcustom('baikangxie')){
            $order_goods2[] = ['type'=>'yf'];
            $order_goods2[] = ['type'=>'totalprice'];
            $order_goods2[] = ['type'=>'totalprice2'];
        }else{
            $order_goods2[] = ['name'=>'合计','num'=>$totalnum];
        }
        $order_goods3 = array_chunk($order_goods2,13);
        $info['totalprice2'] = num_to_rmb($info['totalprice']);

        if($info['freight_type']==11 && $info['freight_content']){
            $info['freight_content'] = json_decode($info['freight_content'],true);
        }else{
            $info['freight_content'] = [];
        }
        if($info['bid'] == 0){
            $bname = Db::name('admin_set')->where('aid',0)->value('name');
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
    //打印小票
    public function wifiprint(){
        $id = input('post.id/d');
        $rs = \app\commons\Wifiprint::print(aid,'cycle',$id,0);
        return json($rs);
    }
    function defaultSet(){
        $set = Db::name('cycle_sysset')->where('aid',aid)->find();
        if(!$set){
            Db::name('cycle_sysset')->insert(['aid'=>aid]);
        }
    }
}
