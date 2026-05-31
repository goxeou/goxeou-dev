<?php


// +----------------------------------------------------------------------
// | 消费记录
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class Payorder extends Common
{
    public function initialize(){
		parent::initialize();
		if(request()->action() !='tradereport'){
            if(bid > 0) showmsg('无访问权限');
        }
	}
    public function index(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = 'payorder.'.input('param.field').' '.input('param.order');
			}else{
				$order = 'payorder.id desc';
			}
			$where = array();
			$where[] = ['payorder.aid','=',aid];
			$where[] = ['payorder.status','=',1];
			$where[] = ['payorder.money','>',0];
			if(input('param.paytypeid') !=''){
                $where[] = ['payorder.paytypeid','=',trim(input('param.paytypeid'))];
            }
			
			if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
			if(input('param.mid')) $where[] = ['payorder.mid','=',trim(input('param.mid'))];
            if(input('param.tel')) $where[] = ['member.tel','like','%'.trim(input('param.tel')).'%'];
			if(input('?param.status') && input('param.status')!=='') $where[] = ['payorder.status','=',input('param.status')];
            if(input('param.ctime') ){
                $ctime = explode(' ~ ',input('param.ctime'));
                $where[] = ['payorder.createtime','>=',strtotime($ctime[0])];
                $where[] = ['payorder.createtime','<',strtotime($ctime[1])];
            }
			$count = 0 + Db::name('payorder')->alias('payorder')->field('member.nickname,member.headimg,payorder.*')->join('member member','member.id=payorder.mid','left')->where($where)->count();
			$data = Db::name('payorder')->alias('payorder')->field('member.nickname,member.headimg,payorder.*')->join('member member','member.id=payorder.mid','left')->where($where)->page($page,$limit)->order($order)->select()->toArray();
            //总收款
            $total_money = 0 + Db::name('payorder')->alias('payorder')->field('payorder.*')->join('member member','member.id=payorder.mid','left')->where($where)->sum('payorder.money');
            //总退款
            $refund_money =   0 + Db::name('payorder')->alias('payorder')->field('payorder.*')->join('member member','member.id=payorder.mid','left')->where($where)->sum('payorder.refund_money');
             //总消费
            $xf_money =  dd_money_format($total_money -  $refund_money);
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data,'total_money'=>dd_money_format($total_money),'refund_money'=>$refund_money,'xf_money'=>$xf_money]);
		}
		if(getcustom('restaurant_cashdesk_custom_pay')){
		    $custom_paylist = Db::name('restaurant_cashdesk_custom_pay')->where('aid',aid)->where('bid',bid)->where('status',1)->order('sort desc,id desc')->select()->toArray();
		    foreach($custom_paylist as $ck=>$cv){
                $custom_paylist[$ck]['id'] = 10000+ $cv['id'];
            }
            View::assign('custom_paylist',$custom_paylist);
        }
		return View::fetch();
    }
	//导出
	public function excel(){
		if(input('param.field') && input('param.order')){
			$order = 'payorder.'.input('param.field').' '.input('param.order');
		}else{
			$order = 'payorder.id desc';
		}
        $page = input('param.page')?:1;
        $limit = input('param.limit')?:10;
		$where = array();
		$where[] = ['payorder.aid','=',aid];
		$where[] = ['payorder.status','=',1];
		$where[] = ['payorder.money','>',0];
        if(input('param.paytypeid') !=''){
            $where[] = ['payorder.paytypeid','=',trim(input('param.paytypeid'))];
        }
		if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
		if(input('param.mid')) $where[] = ['payorder.mid','=',trim(input('param.mid'))];
		if(input('?param.status') && input('param.status')!=='') $where[] = ['payorder.status','=',input('param.status')];
        if(input('param.ctime') ){
            $ctime = explode(' ~ ',input('param.ctime'));
            $where[] = ['payorder.createtime','>=',strtotime($ctime[0])];
            $where[] = ['payorder.createtime','<',strtotime($ctime[1]) + 86400];
        }
		$list = Db::name('payorder')->alias('payorder')->field('member.nickname,member.headimg,payorder.*')
            ->join('member member','member.id=payorder.mid','left')->where($where)->order($order)
            ->page($page,$limit)
            ->select()->toArray();
        $count = Db::name('payorder')->alias('payorder')->field('member.nickname,member.headimg,payorder.*')
            ->join('member member','member.id=payorder.mid','left')->where($where)->order($order)
            ->count();
		$title = array();
		$title[] = t('会员').'信息';
		$title[] = '订单号';
		$title[] = '支付项目';
		$title[] = '支付金额';
		$title[] = '支付方式';
        $title[] = '发起时间';
		$title[] = '支付时间';
		$data = array();
		foreach($list as $v){
			$tdata = array();
			$tdata[] = $v['nickname'].'('.t('会员').'ID:'.$v['mid'].')';
			$tdata[] = ' '.$v['ordernum'];
			$tdata[] = $v['title'];
			$tdata[] = $v['money'];
			$tdata[] = $v['paytype'];
            $tdata[] = date('Y-m-d H:i:s',$v['createtime']);
			$tdata[] = date('Y-m-d H:i:s',$v['paytime']);
			$data[] = $tdata;
		}
        $total_money = 0 + Db::name('payorder')->alias('payorder')->field('payorder.*')->join('member member','member.id=payorder.mid','left')->where($where)->sum('payorder.money');
        if(!$data){ //最后一页没有数据的时候再追加，放到最后
            $data[]= [
                '',
                '',
                '',
                '',
                '',
                '',
                '总消费金额：'.dd_money_format($total_money)
            ];
        }
        return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data,'title'=>$title]);
		$this->export_excel($title,$data);
	}
	//删除
	public function del(){
		$ids = input('post.ids/a');
		Db::name('payorder')->where('aid',aid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('消费记录删除'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	//头条小程序分账
	public function datasettle(){
		$ids = input('post.ids/a');
		$payorderlist = Db::name('payorder')->where('aid',aid)->where('id','in',$ids)->where('paytypeid',12)->where('status',1)->where('money','>',0)->where('issettle','in','0,2')->select()->toArray();
		$successnum = 0;
		foreach($payorderlist as $payorder){
			$rs = \app\commons\Ttpay::settle(aid,$payorder['ordernum']);
			if($rs['status'] == 1){
				Db::name('payorder')->where('id',$payorder['id'])->update(['issettle'=>1]);
				$successnum++;
			}else{
				return json(['status'=>0,'msg'=>$rs['msg']]);
			}
		}
		return json(['status'=>1,'msg'=>'成功处理分账'.$successnum.'条']);
	}
	//抖音小程序订单核销
	public function datapushorder(){
		$ids = input('post.ids/a');
		$payorderlist = Db::name('payorder')->where('aid',aid)->where('id','in',$ids)->where('paytypeid',12)->where('status',1)->where('money','>',0)->where('issettle',0)->select()->toArray();
		$successnum = 0;
		foreach($payorderlist as $payorder){
			$rs = \app\commons\Ttpay::pushorder(aid,$payorder['ordernum']);
			if($rs['status'] == 1){
				Db::name('payorder')->where('id',$payorder['id'])->update(['issettle'=>2]);
				$successnum++;
			}else{
				return json(['status'=>0,'msg'=>$rs['msg']]);
			}
		}
		return json(['status'=>1,'msg'=>'成功核销'.$successnum.'条']);
	}

    //营业报表
    public function tradereport(){
        if(getcustom('finance_trade_report')){
            if(request()->isAjax()){
                $other['datetype'] ='today';
                $paytypeid = input('param.paytypeid');
                if($paytypeid !='')$other['search_paytype'] =  $paytypeid;
                $ctime = input('param.ctime');
                if($ctime){
                    $ctime = explode('~',$ctime);
                    $other['starttime'] =$ctime[0];
                    $other['endtime'] =$ctime[1];
                    $other['datetype'] ='custom';
                }
               $data = \app\models\Payorder::tradeReport(aid,bid,0,2,$other);
                return json(['code'=>0,'msg'=>'查询成功','data' => $data]);
            }
            if(getcustom('restaurant_cashdesk_custom_pay')){
                $custom_paylist = Db::name('restaurant_cashdesk_custom_pay')->where('aid',aid)->where('bid',bid)->where('status',1)->order('sort desc,id desc')->select()->toArray();
                foreach($custom_paylist as $ck=>$cv){
                    $custom_paylist[$ck]['id'] = 10000+ $cv['id'];
                }
                View::assign('custom_paylist',$custom_paylist);
            }
            View::assign('bid',bid); 
            return View::fetch();
        }
    }

    public function getorderdetail(){
        if(getcustom('payorder_show_orderdetail')){
            $id = input('param.id');
            $payorder = Db::name('payorder')->where('aid',aid)->where('id',$id)->find();
            $orderid = $payorder['orderid'];
            $type = $payorder['type'];
            $detail = Db::name($type.'_order')->where ('aid',aid) ->where('id',$orderid)->find();
            $detail['type'] = $type;
            $comdata = array();
            $oglist = [];
            if(!in_array($type,['recharge','maidan','form','collage'])){
                $comdata['parent1'] = ['mid'=>'','nickname'=>'','headimg'=>'','money'=>0,'score'=>0];
                $comdata['parent2'] = ['mid'=>'','nickname'=>'','headimg'=>'','money'=>0,'score'=>0];
                $comdata['parent3'] = ['mid'=>'','nickname'=>'','headimg'=>'','money'=>0,'score'=>0];
               
                $ogwhere = [];
                $ogwhere[] = ['aid','=',aid];
                $ogwhere[] = ['orderid','=',$orderid];
                $oglist = Db::name($type.'_order_goods')->where($ogwhere)->select()->toArray();
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
            }
            if($type =='recharge'){
                $detail['totalprice'] = $detail['money'];
            }elseif($type =='maidan'){
                $detail['totalprice'] = $detail['paymoney'];
            }elseif($type =='form'){
                $detail['totalprice'] = $detail['money'];
            }elseif($type =='restaurant_shop'){
                $detail['tablename'] = Db::name('restaurant_table')->where('id',$detail['tableid'])->value('name');
            }
            $detail['realprice'] = dd_money_format( $detail['totalprice'] - $detail['refund_money']);
            $orderdetail = $detail??[];
            $member = Db::name('member')->field('id,nickname,headimg,realname,tel,wxopenid,unionid')->where('id',$detail['mid'])->find();
            if(!$member) $member = ['id'=>$detail['mid'],'nickname'=>'','headimg'=>''];
            return json(['order'=>$orderdetail,'member'=>$member,'oglist'=>$oglist,'comdata'=>$comdata]); 
        }
    }
    
    public function shoprestauranttongji(){
        if(getcustom('payorder_shop_restaurant_tongji')){
            if(request()->isAjax()){
                $page = input('param.page');
                $limit = input('param.limit');
                if(input('param.field') && input('param.order')){
                    $order = 'payorder.'.input('param.field').' '.input('param.order');
                }else{
                    $order = 'payorder.id desc';
                }
                $where = array();
                $where[] = ['payorder.aid','=',aid];
                $where[] = ['payorder.status','=',1];
                $where[] = ['payorder.money','>',0];
                $where[] = ['payorder.type','in',['restaurant_shop','shop','cashier','restaurant_takeaway']];
                if(input('param.bid')) $where[] = ['payorder.bid','=',input('param.bid')];
                if(input('param.mid')) $where[] = ['member.id','=',input('param.mid')];
                if(input('param.paytypeid') !='') $where[] = ['payorder.paytypeid','=',input('param.paytypeid')];
                if(input('param.ordernum')) $where[] = ['payorder.ordernum','like','%'.input('param.ordernum').'%'];
                //数据类型 restaurant  shop
                if(input('param.type')){
                    if(input('param.type') =='restaurant'){
                        $where[] = ['payorder.type','in',['restaurant_shop','restaurant_takeaway']];
                    }
                    if(input('param.type') =='shop'){
                        $where[] = ['payorder.type','in',['shop','cashier']];
                    }
                } 
                if(input('param.ctime') ){
                    $ctime = explode(' ~ ',input('param.ctime'));
                    $where[] = ['payorder.paytime','>=',strtotime($ctime[0])];
                    $where[] = ['payorder.paytime','<',strtotime($ctime[1]) + 86400];
                }
                $count = 0 + Db::name('payorder')->alias('payorder')->field('member.nickname,member.headimg,payorder.*')->join('member member','member.id=payorder.mid','left')->where($where)->count();
                
                $data = Db::name('payorder')->alias('payorder')->field('member.nickname,member.headimg,payorder.*')->join('member member','member.id=payorder.mid','left')->where($where)->page($page,$limit)->order($order)->select()->toArray();
                foreach($data as $key=>$val){
                    if($val['bid'] > 0){
                        $bname = Db::name('business')->where('id',$val['bid'])->value('name');
                    }else{
                        $bname ='平台订单';
                    }
                    //订单信息
                    $order = Db::name($val['type'].'_order')->where('aid',aid)->where('id',$val['orderid'])->find();
                    $data[$key]['ostatus'] = $order['status'];
                    $data[$key]['refund_status'] = $order['refund_status'];
                    $data[$key]['bname'] = $bname;
                    $leixing ='商城';
                    if(in_array($val['type'],['restaurant_shop','restaurant_takeaway'])){
                        $oglist = Db::name('restaurant_shop_order_goods')->where('aid',aid)->where('orderid',$val['orderid'])->select()->toArray();
                        $goodsdata=array();
                        foreach($oglist as $og){
                            $sell_price = $og['sell_price'];
                            $ggname =  $og['ggname'];
                            $goodsdata[] = '<div style="font-size:12px;float:left;clear:both;margin:1px 0">'.
                                '<img src="'.$og['pic'].'" style="max-width:60px;float:left">'.
                                '<div style="float: left;width:250px;margin-left: 10px;white-space:normal;line-height:16px;">'.
                                '<div style="width:100%;min-height:25px;max-height:32px;overflow:hidden">'.$og['name'].'</div>'.
                                '<div style="padding-top:0px;color:#f60"><span style="color:#888">'.$ggname.'</span></div>'.
                                '<div style="padding-top:0px;color:#f60;">￥'.$sell_price.' × '.$og['num'].'</div>'.
                                '</div>'.
                                '</div>';
                        }
                        $data[$key]['product_price'] =  $order['product_price'];
                        $data[$key]['totalprice'] =  $order['totalprice'];
                        $leixing ='餐饮';
                    }
                    if(in_array($val['type'],['shop','cashier'])){
                        $oglist = Db::name($val['type'].'_order_goods')->where('aid',aid)->where('orderid',$val['orderid'])->select()->toArray();
                        $goodsdata=array();
                        foreach($oglist as $og){
                            $pic = $og['pic'];
                            if($val['type'] =='cashier')$pic = $og['propic'];
                            $goodshtml = '<div style="font-size:12px;float:left;clear:both;margin:1px 0">'.
                                '<img src="'.$pic.'" style="max-width:60px;float:left">'.
                                '<div style="float: left;width:250px;margin-left: 10px;white-space:normal;line-height:16px;">'.
                                '<div style="width:100%;min-height:25px;max-height:32px;overflow:hidden">'.$og['name'].'</div>'.
                                '<div style="padding-top:0px;color:#f60"><span style="color:#888">'.$og['ggname'].'</span></div>';
                            
                                $goodshtml.='<div style="padding-top:0px;color:#f60;">￥'.$og['sell_price'].' × '.$og['num'].'</div>';
                           
                            $goodshtml.='</div>';
                            $goodshtml.='</div>';

                            $goodsdata[] = $goodshtml;
                        }
                        $data[$key]['product_price'] =  $order['product_price'];
                        $data[$key]['totalprice'] =  $order['totalprice'];
                    }
                    if(in_array($val['type'],['cashier'])){
                        $data[$key]['product_price'] =  $order['pre_totalprice'];
                        $data[$key]['totalprice'] =  $order['totalprice'];
                    }
                    $data[$key]['goodsdata'] = implode('',$goodsdata);
                    $data[$key]['leixing'] = $leixing;
                    
                    $data[$key]['typename'] = \app\commons\Order::getOrderTypeName($val['type']);
                    $member = Db::name('member')->field('nickname,headimg')->where('id',$val['mid'])->find();
                    $data[$key]['nickname'] = $member['nickname'];
                    $data[$key]['headimg'] = $member['headimg'];
                    $data[$key]['createtime']  = $order['createtime'];
                }
               
                return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
            }
            return View::fetch();
        }
    }
    public function shoprestauranttongjiexcel(){
        if(getcustom('payorder_shop_restaurant_tongji')){
            if(request()->isAjax()){
                $page = input('param.page');
                $limit = input('param.limit');
                if(input('param.field') && input('param.order')){
                    $order = 'payorder.'.input('param.field').' '.input('param.order');
                }else{
                    $order = 'payorder.id desc';
                }
                $where = array();
                $where[] = ['payorder.aid','=',aid];
                $where[] = ['payorder.status','=',1];
                $where[] = ['payorder.money','>',0];
                $where[] = ['payorder.type','in',['restaurant_shop','shop','cashier','restaurant_takeaway']];
                if(input('param.bid')) $where[] = ['payorder.bid','=',input('param.bid')];
                if(input('param.mid')) $where[] = ['member.id','=',input('param.mid')];
                if(input('param.paytypeid') !='') $where[] = ['payorder.paytypeid','=',input('param.paytypeid')];
                if(input('param.ordernum')) $where[] = ['payorder.ordernum','like','%'.input('param.ordernum').'%'];
                //数据类型 restaurant  shop
                if(input('param.type')){
                    if(input('param.type') =='restaurant'){
                        $where[] = ['payorder.type','in',['restaurant_shop','restaurant_takeaway']];
                    }
                    if(input('param.type') =='shop'){
                        $where[] = ['payorder.type','in',['shop','cashier']];
                    }
                }
                if(input('param.ctime') ){
                    $ctime = explode(' ~ ',input('param.ctime'));
                    $where[] = ['payorder.paytime','>=',strtotime($ctime[0])];
                    $where[] = ['payorder.paytime','<',strtotime($ctime[1]) + 86400];
                }

                $ostatusarr = ['0' => '未支付','1' => '已支付','3' =>'已完成','4' => '已关闭'];
                $refundstatusarr = ['1' => '退款待审核','2' => '已退款','3' => '退款驳回'];
                $data = Db::name('payorder')->alias('payorder')->field('member.nickname,member.headimg,payorder.*')->join('member member','member.id=payorder.mid','left')->where($where)->page($page,$limit)->order($order)->select()->toArray();
                $title = ['订单号','所属商户','会员信息','商品名称','规格','数量','单价','总价','实付金额','支付方式','下单时间','付款时间','数据类型','数据来源','状态'];
                $exceldata = [];
                foreach($data as $key=>$val){
                   
                    if($val['bid'] > 0){
                        $bname = Db::name('business')->where('id',$val['bid'])->value('name');
                    }else{
                        $bname ='平台订单';
                    }
                    //订单信息
                    $order = Db::name($val['type'].'_order')->where('aid',aid)->where('id',$val['orderid'])->find();
                    $totalprice =  $order['totalprice'];
                    $product_price = $order['product_price'];
                    if($val['type'] == 'cashier'){
                        $product_price =  $order['pre_totalprice'];
                    }
                    $leixing ='商城';
                    $typename = \app\commons\Order::getOrderTypeName($val['type']);
                    $member = Db::name('member')->field('nickname,headimg')->where('id',$val['mid'])->find();
                    $nickname = $member['nickname'];
                    $ostatus  = $ostatusarr[$order['status']];
                    $refundstatus =  $refundstatusarr[$order['refund_status']];
                    if(in_array($val['type'],['restaurant_shop','restaurant_takeaway'])){
                        $oglist = Db::name('restaurant_shop_order_goods')->where('aid',aid)->where('orderid',$val['orderid'])->select()->toArray();
                        foreach($oglist as $og){
                            $sell_price = $og['sell_price'];
                            $ggname =  $og['ggname'];
                            $leixing ='餐饮';
                            $exceldata[] =[
                                $order['ordernum'],
                                $bname,
                                $nickname.'(ID:'.$val['mid'].')',
                                $og['name'],
                                $ggname,
                                $og['num'],
                                $sell_price,
                                $og['totalprice'],
                                $og['real_totalprice'],
                                $val['paytype'],
                                date('Y-m-d H:i',$order['createtime']),
                                date('Y-m-d H:i',$order['paytime']),
                                $leixing,
                                $typename,
                                $ostatus?$ostatus:$refundstatus
                            ]; 
                        }
                     
                    }
                    if(in_array($val['type'],['shop','cashier'])){
                        $oglist = Db::name($val['type'].'_order_goods')->where('aid',aid)->where('orderid',$val['orderid'])->select()->toArray();
                        foreach($oglist as $og){
                            $sell_price = $og['sell_price'];
                            $ggname = $og['ggname'];
                            $exceldata[] =[
                                $order['ordernum'],
                                $bname,
                                $nickname.'(ID:'.$val['mid'].')',
                                $og['name'],
                                $ggname,
                                $og['num'],
                                $sell_price,
                                $og['totalprice'],
                                $og['real_totalprice']?$og['real_totalprice']:$og['totalprice'],
                                $val['paytype'],
                                date('Y-m-d H:i',$order['createtime']),
                                date('Y-m-d H:i',$order['paytime']),
                                $leixing,
                                $typename ,
                                $ostatus?$ostatus:$refundstatus
                            ];
                        }
                    }
               
                }
                return json(['code'=>0,'msg'=>'查询成功','count'=>count($exceldata),'data'=>$exceldata,'title'=>$title]);
                $this->export_excel($title,$data);
            }
            return View::fetch();
        }
    }
}
