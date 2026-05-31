<?php


// +----------------------------------------------------------------------
// | 收银台订单    custom_file(restaurant_shop_cashdesk)
// +----------------------------------------------------------------------
namespace app\controllers;
use think\db\Where;
use think\facade\View;
use think\facade\Db;

class RestaurantCashdeskOrder extends Common
{
    public function initialize(){
		parent::initialize();
	}
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
            
            $cwhere []= ['aid' ,'=',aid];
            if(bid > 0){
                $cwhere []= ['bid' ,'=',bid];
            }else{
                $cwhere []= ['bid' ,'=',0];
            }
            $cashdesk = Db::name('restaurant_cashdesk')->where($cwhere)->find();
            $where[] = ['cashdesk_id','=',$cashdesk['id']];
            
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
                    $ids = Db::name('restaurant_shop_order_goods')->where('id','in',input('param.ogid'))->column('orderid');
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
            $count = 0 + Db::name('restaurant_shop_order')->where($where)->count();
            //echo M()->_sql();
            $list = Db::name('restaurant_shop_order')->where($where)->page($page,$limit)->order($order)->select()->toArray();
            foreach($list as $k=>$vo){
                $member = Db::name('member')->field('nickname,headimg')->where('id',$vo['mid'])->find();
                $oglist = Db::name('restaurant_shop_order_goods')->where('aid',aid)->where('orderid',$vo['id'])->select()->toArray();
                $goodsdata=array();
                foreach($oglist as $og){
                    $sell_price = $og['sell_price'];
                    $ggname =  $og['ggname'];
                    $goodsdata[] = '<div style="font-size:12px;float:left;clear:both;margin:1px 0">'.
                        '<img src="'.$og['pic'].'" style="max-width:60px;float:left">'.
                        '<div style="float: left;width:160px;margin-left: 10px;white-space:normal;line-height:16px;">'.
                        '<div style="width:100%;min-height:25px;max-height:32px;overflow:hidden">'.$og['name'].'</div>'.
                        '<div style="padding-top:0px;color:#f60"><span style="color:#888">'.$ggname.'</span></div>'.
                        '<div style="padding-top:0px;color:#f60;">￥'.$sell_price.' × '.$og['num'].'</div>'.
                        '</div>'.
                        '</div>';
                }
                $list[$k]['goodsdata'] = implode('',$goodsdata);
                if($vo['bid'] > 0){
                    $list[$k]['bname'] = Db::name('business')->where('aid',aid)->where('id',$vo['bid'])->value('name');
                }else{
                    $list[$k]['bname'] = '平台自营';
                }
                $list[$k]['tablename'] = Db::name('restaurant_table')->where('id',$vo['tableid'])->value('name');
                $list[$k]['nickname'] = $member['nickname'];
                $list[$k]['headimg'] = $member['headimg'];
                }
            return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$list]);
        }
        $machinelist = Db::name('wifiprint_set')->where('aid',aid)->where('status',1)->where('bid',bid)->select()->toArray();
        $hasprint = 0;
        if($machinelist){
            $hasprint = 1;
        }
        $peisong_set = Db::name('peisong_set')->where('aid',aid)->find();
        if($peisong_set['status']==1 && bid>0 && $peisong_set['businessst']==0 && $peisong_set['make_status']==0) $peisong_set['status'] = 0;
        View::assign('peisong_set',$peisong_set);
        View::assign('hasprint',$hasprint);
        View::assign('express_data',express_data(['aid'=>aid,'bid'=>bid]));
        return View::fetch();
    }
    //打印小票
    public function wifiprint(){
        $id = input('post.id/d');
        $order = Db::name('restaurant_shop_order')->where('aid',aid)->where('bid',bid)->where('id',$id)->find();
        $templateType=0;
        $rs = \app\customs\Restaurant::print('restaurant_shop', $order, [], $templateType);//0普通打印，1一菜一单
        return json($rs);
    }
    //订单详情
    public function getdetail(){
        $orderid = input('post.orderid');
        $order = Db::name('restaurant_shop_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->find();
        if($order['coupon_rid']){
            $couponrecord = Db::name('coupon_record')->where('id',$order['coupon_rid'])->find();
        }else{
            $couponrecord = false;
        }
        $oglist = Db::name('restaurant_shop_order_goods')->where('aid',aid)->where('orderid',$orderid)->select()->toArray();
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
                $comdata['parent1']['money'] += round($v['parent1commission'],2);
                $comdata['parent1']['money'] = round($comdata['parent1']['money'],2);
                $comdata['parent1']['score'] += $v['parent1score'];
            }
            if($v['parent2']){
                $parent2 = Db::name('member')->where('id',$v['parent2'])->find();
                $comdata['parent2']['mid'] = $v['parent2'];
                $comdata['parent2']['nickname'] = $parent2['nickname'];
                $comdata['parent2']['headimg'] = $parent2['headimg'];
                $comdata['parent2']['money'] += round($v['parent2commission'],2);
                $comdata['parent2']['money'] = round($comdata['parent2']['money'],2);
                $comdata['parent2']['score'] += $v['parent2score'];
            }
            if($v['parent3']){
                $parent3 = Db::name('member')->where('id',$v['parent3'])->find();
                $comdata['parent3']['mid'] = $v['parent3'];
                $comdata['parent3']['nickname'] = $parent3['nickname'];
                $comdata['parent3']['headimg'] = $parent3['headimg'];
                $comdata['parent3']['money'] += round($v['parent3commission'],2);
                $comdata['parent3']['money'] = round($comdata['parent3']['money'],2);
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
        $order['tablename'] = Db::name('restaurant_table')->where('id',$order['tableid'])->value('name');
        //		$miandanst = Db::name('admin_set')->where('aid',aid)->value('miandanst');
//		if(bid==0 && $miandanst==1 && in_array('wx',$this->platform) && ($member['wxopenid'] || $member['unionid'])){ //可以使用小程序物流助手发货
//			$canmiandan = 1;
//		}else{
//			$canmiandan = 0;
//		}
        $shopset = Db::name('restaurant_shop_sysset')->where('aid',aid)->where('bid',$order['bid'])->find();
        return json(['order'=>$order,'couponrecord'=>$couponrecord,'oglist'=>$oglist,'member'=>$member,'comdata'=>$comdata,'shopset' => $shopset]);
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
        
        $cwhere []= ['aid' ,'=',aid];
        if(bid > 0){
            $cwhere []= ['bid' ,'=',bid];
        }
        $cashdesk = Db::name('restaurant_cashdesk')->where($cwhere)->find();
        $where[] = ['cashdesk_id','=',$cashdesk['id']];
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
        $list = Db::name('restaurant_shop_order')->where($where)->order($order)->page($page,$limit)->select()->toArray();
        $count = Db::name('restaurant_shop_order')->where($where)->count();
        $title = array('订单号','下单人','餐桌','商品信息','总价','实付款','用餐人数','支付方式','客户留言','备注','下单时间','状态');
        $data = [];
        foreach($list as $k=>$vo){
            $tablename = Db::name('restaurant_table')->where('id',$vo['tableid'])->value('name');
            $member = Db::name('member')->where('id',$vo['mid'])->find();
            $oglist = Db::name('restaurant_shop_order_goods')->where('orderid',$vo['id'])->select()->toArray();
            $xm=array();
            foreach($oglist as $gg){
                $xm[] = $gg['name']."/".$gg['ggname']." × ".$gg['num']."";
            }
            $status='';
            if($vo['status']==0){
                $status = '未支付';
            }elseif($vo['status']==2){
                $status = '已发货';
            }elseif($vo['status']==1){
                $status = '已支付';
            }elseif($vo['status']==3){
                $status = '已完成';
            }elseif($vo['status']==4){
                $status = '已关闭';
            }
            $data[] = [
                ' '.$vo['ordernum'],
                $member['nickname'],
                $tablename,
                implode("\r\n",$xm),
                $vo['product_price'],
                $vo['totalprice'],
                $vo['renshu'],
                $vo['paytype'],
                $vo['message'],
                $vo['remark'],
                date('Y-m-d H:i:s',$vo['createtime']),
                $status
            ];
        }
        return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data,'title'=>$title]);
        $this->export_excel($title,$data);
    }
    //删除
    public function del(){
        $id = input('post.id/d');
        Db::name('restaurant_shop_order')->where('aid',aid)->where('bid',bid)->where('id',$id)->delete();
        Db::name('restaurant_shop_order_goods')->where('aid',aid)->where('bid',bid)->where('orderid',$id)->delete();
        \app\commons\System::plog('餐饮点餐订单删除'.$id);
        return json(['status'=>1,'msg'=>'删除成功']);
    }
    //改价格
    public function changeprice(){
        $orderid = input('post.orderid/d');
        $newprice = input('post.newprice/f');
        $newordernum = date('ymdHis').rand(100000,999999);
        Db::name('restaurant_shop_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->update(['totalprice'=>$newprice,'ordernum'=>$newordernum]);
        Db::name('restaurant_shop_order_goods')->where('aid',aid)->where('bid',bid)->where('orderid',$orderid)->update(['ordernum'=>$newordernum]);

        $payorderid = Db::name('restaurant_shop_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->value('payorderid');
        \app\models\Payorder::updateorder($payorderid,$newordernum,$newprice);
        \app\commons\System::plog('餐饮点餐订单改价格'.$orderid);
        return json(['status'=>1,'msg'=>'修改完成']);
    }
    //关闭订单
    public function closeOrder(){
        $orderid = input('post.orderid/d');
        $order = Db::name('restaurant_shop_order')->where('id',$orderid)->where('aid',aid)->where('bid',bid)->find();
        if(!$order || $order['status']!=0){
            return json(['status'=>0,'msg'=>'关闭失败,订单状态错误']);
        }
        //加库存
        $oglist = Db::name('restaurant_shop_order_goods')->where('aid',aid)->where('bid',bid)->where('orderid',$orderid)->select()->toArray();
        foreach($oglist as $og){
            Db::name('restaurant_product_guige')->where('aid',aid)->where('id',$og['ggid'])->update(['stock'=>Db::raw("stock+".$og['num']),'sales'=>Db::raw("sales-".$og['num'])]);
            Db::name('restaurant_product')->where('aid',aid)->where('bid',bid)->where('id',$og['proid'])->update(['stock'=>Db::raw("stock+".$og['num']),'sales'=>Db::raw("sales-".$og['num'])]);
        }

        //优惠券抵扣的返还
        if($order['coupon_rid'] > 0){
            Db::name('coupon_record')->where('aid',aid)->where(['mid'=>$order['mid'],'id'=>$order['coupon_rid']])->update(['status'=>0,'usetime'=>'']);
        }
        $rs = Db::name('restaurant_shop_order')->where('id',$orderid)->where('aid',aid)->where('bid',bid)->update(['status'=>4]);
        Db::name('restaurant_shop_order_goods')->where('orderid',$orderid)->where('aid',aid)->where('bid',bid)->update(['status'=>4]);
        \app\commons\System::plog('餐饮点餐订单关闭订单'.$orderid);
        return json(['status'=>1,'msg'=>'操作成功']);
    }
    //改为已支付
    public function ispay(){
        if(bid > 0) showmsg('无权限操作');
        $orderid = input('post.orderid/d');
        Db::name('restaurant_shop_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->update(['status'=>1,'paytime'=>time(),'paytype'=>'后台支付']);
        Db::name('restaurant_shop_order_goods')->where('orderid',$orderid)->where('aid',aid)->where('bid',bid)->update(['status'=>1]);
        //奖励积分
        $order = Db::name('restaurant_shop_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->find();
        if($order['givescore'] > 0){
            \app\commons\Member::addscore(aid,$order['mid'],$order['givescore'],'购买产品奖励'.t('积分'));
        }
        \app\commons\System::plog('餐饮点餐订单改为已支付'.$orderid);
        return json(['status'=>1,'msg'=>'操作成功']);
    }
	//退款
    public function refund(){
        $orderid = input('orderid');
        $reason = input('param.reason','');
        $order = Db::name('restaurant_shop_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->find();
        if(empty($order) || $order['status'] ==0 || !in_array($order['refund_status'],[0,3])){
            return json(['status'=>0,'msg'=>'订单信息有误']);
        }
        $refund_money = $order['totalprice'];
        if(empty($refund_money)){
            return json(['status'=>0,'msg'=>'退款金额有误']);
        }
        $remark = $reason?'订单号: '.$order['ordernum'].','.$reason:'订单号: '.$order['ordernum'];
        //直接退款
        if($order['paytypeid']==5 ||$order['paytypeid']==81){ //5被占用 更换为81
            $rs = \app\customs\Sxpay::refund($order['aid'],'restaurant_cashdesk',$order['ordernum'],$order['totalprice'],$refund_money,$remark,$order['bid']);
            //更改payorder 
            $payorder = Db::name('payorder')->where('aid',$order['aid'])->where('ordernum',$order['ordernum'])->find();
            Db::name('payorder')->where('aid',$order['aid'])->where('id',$payorder['id'])->update(['refund_money' => $payorder['refund_money'] + $refund_money,'refund_time' => time()]);
            }elseif ($order['paytypeid'] ==0){//现金退款
            $rs = ['status'=>1,'msg'=>''];
            if($order['mix_paynum']){
                $mix_refund_money  = $order['mix_money'];
                $order['paytypeid'] =$order['mix_paytypeid'];
                $rs = \app\commons\Order::refund($order,$mix_refund_money,$remark);
                $refund_money =  $refund_money -   $mix_refund_money;
            }
            //更新payorder表退款信息
            $payorder = Db::name('payorder')->where('aid',$order['aid'])->where('ordernum',$order['ordernum'])->find();
            Db::name('payorder')->where('aid',$order['aid'])->where('id',$payorder['id'])->update(['refund_money' => $payorder['refund_money'] + $refund_money,'refund_time' => time()]);
            } elseif ($order['paytypeid'] ==121){//抖音核销
            }else{
            $refund_type = 1;
            if($refund_type){
                $rs = \app\commons\Order::refund($order,$refund_money,$remark);
            }
        }
        if($rs && $rs['status']==1){
            $orderup = [
                'refund_money'=>$refund_money,
                'refund_reason'=>$remark,
                'refund_status'=>2,
                'status'=>4,//退款
                'refund_time'=>time()
            ];
            Db::name('restaurant_shop_order')->where('id',$orderid)->update($orderup);
            
            //更新实际库存 和修改  restaurant_shop_order_goods状态
            Db::name('restaurant_shop_order_goods')->where('orderid',$orderid)->where('aid',aid)->where('bid',bid)->update(['status'=>4]);
            $goodslist =Db::name('restaurant_shop_order_goods')->where('aid',aid)->where('orderid', $orderid)->select()->toArray();
            foreach($goodslist as $key=>$val){
                $num = $val['num'];
                Db::name('restaurant_product')->where('aid',aid)->where('id',$val['proid'])->update(['real_sales2'=>Db::raw("real_sales2-$num")]);
                }
            //查询当前桌台的订单 和 退款订单是否是一个 如果是操作桌台为空闲
            
           $table =  Db::name('restaurant_table')->where('id',$order['tableid'])->find();
           if($table['orderid'] == $order['id'] && $table['status'] !=0){
               Db::name('restaurant_table')->where('id',$order['tableid'])->update(['status' => 0,'orderid' => 0]);
           }
           //
            if($order['bid'] > 0){
                $sysset = Db::name('restaurant_admin_set')->where('aid',$order['aid'])->find();
                $add_business_money = false;
                if($order['paytypeid'] ==2 && $sysset &&  $sysset['business_cashdesk_wxpay_type'] ==2){//微信支付
                    $add_business_money = true;
                }elseif ($order['paytypeid'] ==3 && $sysset && $sysset['business_cashdesk_alipay_type'] ==2){//支付宝
                    $add_business_money = true;
                }elseif (($order['paytypeid'] ==5||$order['paytypeid'] ==81 ) && $sysset && $sysset['business_cashdesk_sxpay_type'] ==2){//随行付
                    $add_business_money = true;
                } elseif ($order['paytypeid'] ==1 && $sysset && $sysset['business_cashdesk_yue']){//随行付
                    $add_business_money = true;
                }
                //todo 收银台退款 扣除佣金
                if($add_business_money){
                    $log = Db::name('business_moneylog')->where('aid',$order['aid'])->where('bid',$order['bid'])->where('type','restaurant_cashdesk')->where('ordernum',$order['ordernum'])->find();
                    if($log['money'] > 0){
                        \app\commons\Business::addmoney($order['aid'],$order['bid'],-$log['money'],'餐饮收银台退款，订单号：'.$order['ordernum'],true,'restaurant_cashdesk',$order['ordernum']);
                    }
                }
            }
            return json(['status'=>1,'msg'=>'退款成功']);
        }else{
            return json(['status'=>0,'msg'=>$rs['msg']??'退款失败']);
        }
    }
    //设置备注
    public function setremark(){
        $orderid = input('post.orderid/d');
        $content = input('post.content');
        Db::name('restaurant_shop_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->update(['remark'=>$content]);
        \app\commons\System::plog('餐饮点餐订单设置备注'.$orderid);
        return json(['status'=>1,'msg'=>'设置完成']);
    }
	//订单统计
	public function tongji(){
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
								$val = 0 + Db::name('restaurant_shop_order')->where('aid',aid)->where('paytime','>=',$starttime)->where('paytime','<',$endtime)->where('status','in','1,2,3')->sum('totalprice');
							}else{
								$val = 0 + Db::name('restaurant_shop_order')->where('aid',aid)->where('bid',bid)->where('paytime','>=',$starttime)->where('paytime','<',$endtime)->where('status','in','1,2,3')->sum('totalprice');
							}
						}else{ //成交量
							if(bid == 0){
								$val = 0 + Db::name('restaurant_shop_order')->where('aid',aid)->where('paytime','>=',$starttime)->where('paytime','<',$endtime)->where('status','in','1,2,3')->count();
							}else{
								$val = 0 + Db::name('restaurant_shop_order')->where('aid',aid)->where('bid',bid)->where('paytime','>=',$starttime)->where('paytime','<',$endtime)->where('status','in','1,2,3')->count();
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
								$val = 0 + Db::name('restaurant_shop_order')->where('aid',aid)->where('paytime','>=',$starttime)->where('paytime','<',$endtime)->where('status','in','1,2,3')->sum('totalprice');
							}else{
								$val = 0 + Db::name('restaurant_shop_order')->where('aid',aid)->where('bid',bid)->where('paytime','>=',$starttime)->where('paytime','<',$endtime)->where('status','in','1,2,3')->sum('totalprice');
							}
						}else{ //成交量
							if(bid == 0){
								$val = 0 + Db::name('restaurant_shop_order')->where('aid',aid)->where('paytime','>=',$starttime)->where('paytime','<',$endtime)->where('status','in','1,2,3')->count();
							}else{
								$val = 0 + Db::name('restaurant_shop_order')->where('aid',aid)->where('bid',bid)->where('paytime','>=',$starttime)->where('paytime','<',$endtime)->where('status','in','1,2,3')->count();
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
								$val = 0 + Db::name('restaurant_shop_order')->where('aid',aid)->where('paytime','>=',$starttime)->where('paytime','<',$endtime)->where('status','in','1,2,3')->sum('totalprice');
							}else{
								$val = 0 + Db::name('restaurant_shop_order')->where('aid',aid)->where('bid',bid)->where('paytime','>=',$starttime)->where('paytime','<',$endtime)->where('status','in','1,2,3')->sum('totalprice');
							}
						}else{ //成交量
							if(bid == 0){
								$val = 0 + Db::name('restaurant_shop_order')->where('aid',aid)->where('paytime','>=',$starttime)->where('paytime','<',$endtime)->where('status','in','1,2,3')->count();
							}else{
								$val = 0 + Db::name('restaurant_shop_order')->where('aid',aid)->where('bid',bid)->where('paytime','>=',$starttime)->where('paytime','<',$endtime)->where('status','in','1,2,3')->count();
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
			else{
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
					$where[] = ['og.createtime','<',strtotime($ctime[1]) + 86400];
				}
				if(input('param.paytime') ){
					$ctime = explode(' ~ ',input('param.paytime'));
					$where[] = ['restaurant_shop_order.paytime','>=',strtotime($ctime[0])];
					$where[] = ['restaurant_shop_order.paytime','<',strtotime($ctime[1]) + 86400];
				}
				if(input('param.proname')){
					$where[] = ['og.name','like','%'.input('param.proname').'%'];
				}
				if(input('param.cid') && input('param.cid')!==''){
					//取出cid 在的商品
					$cid = input('param.cid');
					//子分类
					$clist = Db::name('restaurant_product_category')->where('aid',aid)->where('pid',$cid)->column('id');
					if($clist){
						$clist2 = Db::name('restaurant_product_category')->where('aid',aid)->where('pid','in',$clist)->column('id');
						$cCate = array_merge($clist, $clist2, [$cid]);
						if($cCate){
							$whereCid = [];
							foreach($cCate as $k => $c2){
								$whereCid[] = "find_in_set({$c2},cid)";
							}
							$where[] = Db::raw(implode(' or ',$whereCid));
						}
					} else {
						$where[] = Db::raw("find_in_set(".$cid.",cid)");
					}
					
				}
				$fields = 'og.proid,og.name name,og.pic,og.ggname,sum(og.num) num,sum(og.totalprice) totalprice,sum(og.totalprice)/sum(og.num) as avgprice,sum(og.cost_price*og.num) as chengben,sum(og.totalprice-og.cost_price*og.num) lirun';
				if(input('param.type')==2){
					$count = 0 + Db::name('restaurant_shop_order_goods')->alias('og')->join('restaurant_shop_order','restaurant_shop_order.id=og.orderid')->fieldRaw('og.proid')->where($where)->group('ggid')->count();
					$list = Db::name('restaurant_shop_order_goods')->alias('og')->join('restaurant_shop_order','restaurant_shop_order.id=og.orderid')->fieldRaw($fields)->where($where)->group('ggid')->page($page,$limit)->order($order)->select()->toArray();
				}else{
					$count = 0 + Db::name('restaurant_shop_order_goods')->alias('og')->join('restaurant_shop_order','restaurant_shop_order.id=og.orderid')->fieldRaw('og.proid')->where($where)->group('proid')->count();
					$list = Db::name('restaurant_shop_order_goods')->alias('og')->join('restaurant_shop_order','restaurant_shop_order.id=og.orderid')->fieldRaw($fields)->where($where)->group('proid')->page($page,$limit)->order($order)->select()->toArray();
					//var_dump(db('restaurant_shop_order_goods')->getlastsql());
				}
				if($page == 1){
					$totaldata = Db::name('restaurant_shop_order_goods')->alias('og')->join('restaurant_shop_order','restaurant_shop_order.id=og.orderid')->fieldRaw($fields)->where($where)->where('og.status','in',[1,2,3])->find();
				}
				foreach($list as $k=>$v){
					$list[$k]['ph'] = ($k+1) + ($page-1)*$limit;
					$list[$k]['avgprice'] = number_format($v['avgprice'],2,'.','');
				}
				return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$list,'totaldata'=>$totaldata]);
			}
		}
		if(input('param.type')==3){
			return View::fetch('tongji3');
		}
		if(input('param.type')==4){
			$membercount = Db::name('member')->where('aid',aid)->count(); //总会员数
			if(bid == 0){
				$totalprice = Db::name('restaurant_shop_order')->where('aid',aid)->where('status','in','1,2,3')->sum('totalprice'); //总订单金额
				$totalnum = Db::name('restaurant_shop_order')->where('aid',aid)->where('status','in','1,2,3')->count(); //总订单数
				$totalview = Db::name('restaurant_product')->where('aid',aid)->sum('viewnum'); //总访问数
				$memberxf = Db::name('restaurant_shop_order')->where('aid',aid)->group('mid')->where('status','in','1,2,3')->count(); //消费会员数
			}else{
				$totalprice = Db::name('restaurant_shop_order')->where('aid',aid)->where('bid',bid)->where('status','in','1,2,3')->sum('totalprice'); //总订单金额
				$totalnum = Db::name('restaurant_shop_order')->where('aid',aid)->where('bid',bid)->where('status','in','1,2,3')->count(); //总订单数
				$totalview = Db::name('restaurant_product')->where('aid',aid)->where('bid',bid)->sum('viewnum'); //总访问数
				$memberxf = Db::name('restaurant_shop_order')->where('aid',aid)->where('bid',bid)->group('mid')->where('status','in','1,2,3')->count(); //消费会员数
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
			$catelist =  Db::name('restaurant_product_category')->where('aid',aid)->where('pid','=',0)->select()->toArray();
			View::assign('paytime',input('param.paytime'));
			View::assign('catelist',$catelist);
			return View::fetch('tongji6');
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
        $page = input('param.page');
        $limit = input('param.limit');
		$where = [];
		$where[] = ['og.aid','=',aid];
		$where[] = ['og.bid','=',bid];
		//$where[] = ['og.status','in','1,2,3'];
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
			$where[] = ['restaurant_shop_order.paytime','>=',strtotime($ctime[0])];
			$where[] = ['restaurant_shop_order.paytime','<',strtotime($ctime[1]) + 86400];
		}
		if(input('param.proname')){
			$where[] = ['og.name','like','%'.input('param.proname').'%'];
		}
		if(input('param.cid')){
			//取出cid 在的商品
			$cid = input('param.cid');
			//子分类
			$clist = Db::name('restaurant_product_category')->where('aid',aid)->where('pid',$cid)->column('id');
			if($clist){
				$clist2 = Db::name('restaurant_product_category')->where('aid',aid)->where('pid','in',$clist)->column('id');
				$cCate = array_merge($clist, $clist2, [$cid]);
				if($cCate){
					$whereCid = [];
					foreach($cCate as $k => $c2){
						$whereCid[] = "find_in_set({$c2},cid)";
					}
					$where[] = Db::raw(implode(' or ',$whereCid));
				}
			} else {
				$where[] = Db::raw("find_in_set(".$cid.",cid)");
			}		
			//$where[] = ['og.cid','=',input('param.cid')];
		}
		$fields = 'og.proid,og.name,og.pic,og.ggname,sum(og.num) num,sum(og.totalprice) totalprice,sum(og.totalprice)/sum(og.num) as avgprice';
		if(input('param.type')==2){
			$list = Db::name('restaurant_shop_order_goods')->alias('og')
                ->join('restaurant_shop_order','restaurant_shop_order.id=og.orderid')->field($fields)
                ->where($where)->group('ggid')->order($order)
                ->page($page,$limit)
                ->select()->toArray();
            $count = Db::name('restaurant_shop_order_goods')->alias('og')
                ->join('restaurant_shop_order','restaurant_shop_order.id=og.orderid')->field($fields)
                ->where($where)->group('ggid')->count();
		}else{
			$list = Db::name('restaurant_shop_order_goods')->alias('og')
                ->join('restaurant_shop_order','restaurant_shop_order.id=og.orderid')->field($fields)
                ->where($where)->group('proid')->order($order)
                ->page($page,$limit)
                ->select()->toArray();
            $count = Db::name('restaurant_shop_order_goods')->alias('og')
                ->join('restaurant_shop_order','restaurant_shop_order.id=og.orderid')->field($fields)
                ->where($where)->group('proid')->count();
		}
		foreach($list as $k=>$v){
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
        return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data,'title'=>$title]);
		$this->export_excel($title,$data);
	}
}