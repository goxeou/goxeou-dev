<?php
//custom_file(yx_gift_pack)
namespace app\controllers;
use think\facade\Db;
class ApiGiftPack extends ApiCommon
{

    public function initialize(){
        parent::initialize();
        if(!getcustom('yx_gift_pack')){
            return $this->json(['status'=>0,'msg'=>'无该功能权限']);
        }
    }
    public function index(){
        $bid = input('param.bid')?input('param.bid/d'):0;
        $where = [];
        $where[] = ['aid','=',aid];
        $where[] = ['bid','=',$bid];
        $where[] = ['status','=',1];
        if(input('param.keyword')){
            $where[] = ['name','like','%'.input('param.keyword').'%'];
        }
        $pernum = 20;
        $pagenum = input('post.pagenum');
        if(!$pagenum) $pagenum = 1;
        $datalist = Db::name('gift_pack')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
        if(!$datalist) $datalist = array();
      
        $rdata = [];
        $rdata['status']   = 1;
        $rdata['datalist'] = $datalist;
        return $this->json($rdata);
    }
    public function detail(){
        if(request()->isPost()){
            $giftid = input('giftid')?input('giftid/d'):0;
            if(!$giftid) {
                return $this->json(['status'=>0,'msg'=>'请选择要查看的礼包']);
            }
            $bid = input('param.bid')?input('param.bid/d'):0;
            $product = Db::name('gift_pack')->where('id',$giftid)->where('status',1)->where('aid',aid)->where('bid',$bid)->find();
            if(!$product){
                return $this->json(['status'=>0,'msg'=>'礼包不存在']);
            }
            $rdata = [];
            $rdata['status'] = 1;
            $rdata['product'] = $product;
            return $this->json($rdata);
        }
    }
    public function createOrder(){
        $this->checklogin();
        if(request()->isPost()){
            $giftid = input('param.giftid')?input('param.giftid/d'):0;
            if(!$giftid){
                return $this->json(['status'=>0,'msg'=>'礼包数据错误']);
            }


            $num = input('param.num')?input('param.num/d'):1;
            $num = intval($num);
            if($num <=0) return $this->json(['status'=>0,'msg'=>'礼包数据错误']);

            $bid  = input('param.bid')?input('param.bid/d'):0;
            if($bid){
                $store_info = Db::name('business')->where('aid',aid)->where('id',$bid)->find();
            }else{
                $store_info = Db::name('admin_set')->where('aid',aid)->find();
            }

            $product_price = 0;
            $product = Db::name('gift_pack')->where('id',$giftid)->where('aid',aid)->where('bid',$bid)->where('status',1)->find();
            if(!$product) return $this->json(['status'=>0,'msg'=>'礼包不存在']);
 

            $product_price += $product['sell_price'] * $num;
            $totalprice    = $product_price;
            if($totalprice<0) $totalprice = 0;

            $orderdata = [];
            $orderdata['aid'] = aid;
            $orderdata['bid'] = $bid;
            $orderdata['mid'] = mid;
            $ordernum = date('ymdHis').aid.rand(1000,9999);
            $orderdata['ordernum'] = $ordernum;
            $orderdata['title']    = $product['name'];
            $orderdata['giftid']     = $product['id'];
            $orderdata['proname']  = !empty($product['name'])?$product['name']:'';
            $orderdata['propic']   = $product['pic'];
            $orderdata['sell_price'] = $product['sell_price'];
            $orderdata['num']        = $num;
            $orderdata['hexiao_code'] = random(16);
            $orderdata['hexiao_qr']   = createqrcode(m_url('admin/hexiao/hexiao?type=gift_bag&co='.$orderdata['hexiao_code']));
            $orderdata['platform']    = platform;
            $orderdata['createtime']  = time();
		    $orderdata['type'] = $product['type'];
		    $orderdata['givescore'] = $product['zsscore'];
		    $orderdata['couponids'] = $product['coupon_ids'];
			if($this->member['pid']){
				$parent1 = Db::name('member')->where('aid',aid)->where('id',$this->member['pid'])->find();
				if($parent1){
					$agleveldata1 = Db::name('member_level')->where('aid',aid)->where('id',$parent1['levelid'])->find();
					if($agleveldata1['can_agent']!=0){
						$orderdata['parent1'] = $parent1['id'];
					}
				}
				$commission_totalprice = $product['sell_price'];
				if($agleveldata1) $orderdata['parent1commission'] = $product['commission'] * $commission_totalprice * 0.01;
			}


            $orderid = Db::name('gift_pack_order')->insertGetId($orderdata);
            if(!$orderid){
                return $this->json(['status'=>0,'msg'=>'提交失败']);
            }
            $payorderid = \app\models\Payorder::createorder(aid,0,$orderdata['mid'],'gift_pack',$orderid,$ordernum,$orderdata['title'],$orderdata['sell_price'],0);

            //加销量
            Db::name('gift_pack')->where('aid',aid)->where('id',$product['id'])->inc('sales',$num)->update();
			if($orderdata['parent1'] && $orderdata['parent1commission'] > 0){
				Db::name('member_commission_record')->insert(['aid'=>aid,'mid'=>$orderdata['parent1'],'frommid'=>mid,'orderid'=>$orderid,'ogid'=>$orderid,'type'=>'gift_pack','commission'=>$orderdata['parent1commission'],'score'=>0,'remark'=>'下级购买礼包奖励','createtime'=>time()]);
			}
            return $this->json(['status'=>1,'orderid'=>$orderid,'payorderid'=>$payorderid,'msg'=>'提交成功']);
        }
    }

    public function orderlist(){
        $this->checklogin();
		if(request()->isPost()){
			$st = input('param.st');
			if(!input('?param.st') || $st === ''){
				$st = 'all';
			}
			$where = [];
			$where[] = ['aid','=',aid];
			$where[] = ['mid','=',mid];
			$where[] = ['status','=',1];
			if(input('param.keyword')) $where[] = ['ordernum|title', 'like', '%'.input('param.keyword').'%'];
			$pernum = 10;
			$pagenum = input('post.pagenum');
			if(!$pagenum) $pagenum = 1;
			$datalist = Db::name('gift_pack_order')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
			foreach($datalist as &$d){
				$d['couponcount'] =  count(explode(',',$d['couponids']));
			}
			if(!$datalist) $datalist = array();
			$rdata = [];
			$rdata['st'] = $st;
			$rdata['datalist'] = $datalist;
		   return $this->json($rdata);
		}
		
		$couponcount = 0+Db::name('coupon_record')->where('mid',mid)->where('aid',aid)->where('status',0)->count();
		

		$rdata = [];
		$rdata['score'] = $this->member['score'];
		$rdata['couponcount'] = $couponcount;
		return $this->json($rdata);

    }
    public function orderdetail(){
        $this->checklogin();
        $detail = Db::name('gift_pack_order')->where('id',input('param.id/d'))->where('mid',mid)->where('aid',aid)->find();
        if(!$detail) return $this->json(['status'=>0,'msg'=>'订单不存在']);
        $detail['createtime'] = $detail['createtime'] ? date('Y-m-d H:i:s',$detail['createtime']) : '';
        $detail['collect_time'] = $detail['collect_time'] ? date('Y-m-d H:i:s',$detail['collect_time']) : '';
        $detail['paytime'] = $detail['paytime'] ? date('Y-m-d H:i:s',$detail['paytime']) : '';
        $detail['refund_time'] = $detail['refund_time'] ? date('Y-m-d H:i:s',$detail['refund_time']) : '';
        $detail['send_time']   = $detail['send_time'] ? date('Y-m-d H:i:s',$detail['send_time']) : '';
		$gift = Db::name('gift_pack')->where('id',$detail['giftid'])->find();
		$detail['pic'] = $gift['pic'];
		$couponids = explode(',',$gift['coupon_ids']);
		$couponlist = Db::name('coupon')->where('id','in',$couponids)->select()->toArray(); 
        $rdata = [];
        $rdata['detail'] = $detail;
		$rdata['couponlist'] = $couponlist;
        return $this->json($rdata);
    }
    public function getproducthxqr(){
        $ogid = input('post.hxogid/d');
        $hxnum = input('post.hxnum/d');
        if(!$ogid || !$hxnum) return json(['status'=>0,'msg'=>'参数错误']);

        $og = Db::name('gift_bag_order_goods')->where('aid',aid)->where('mid',mid)->where('id',$ogid)->find();
        if($og['num'] - $og['hexiao_num'] < $hxnum) return json(['status'=>0,'msg'=>'剩余可核销数量不足']);

        $hexiao_qr = createqrcode(m_url('admin/hexiao/hexiao?type=gift_bag_goods&hxnum='.$hxnum.'&co='.$og['hexiao_code']));
        return json(['status'=>1,'hexiao_qr'=>$hexiao_qr]);
    }
    function closeOrder(){
        $this->checklogin();
        $post = input('post.');
        $orderid = intval($post['orderid']);
        $order = Db::name('gift_bag_order')->where('id',$orderid)->where('aid',aid)->where('mid',mid)->find();
        if(!$order || $order['status']!=0){
            return $this->json(['status'=>0,'msg'=>'关闭失败,订单状态错误']);
        }
        //加库存
        Db::name('gift_bag')->where('aid',aid)->where('id',$order['proid'])->update(['sales'=>Db::raw("sales-".$order['num'])]);

        $rs = Db::name('gift_bag_order')->where('id',$orderid)->where('aid',aid)->where('mid',mid)->update(['status'=>4]);
        return $this->json(['status'=>1,'msg'=>'操作成功']);
    }
    function delOrder(){
        $this->checklogin();
        $post = input('post.');
        $orderid = intval($post['orderid']);
        $order = Db::name('gift_bag_order')->where('id',$orderid)->where('aid',aid)->where('mid',mid)->find();
        if(!$order || ($order['status']!=4 && $order['status']!=3)){
            return $this->json(['status'=>0,'msg'=>'删除失败,订单状态错误']);
        }
        if($order['status']==3){
            $rs = Db::name('gift_bag_order')->where('id',$orderid)->where('aid',aid)->where('mid',mid)->update(['delete'=>1]);
        }else{
            $rs = Db::name('gift_bag_order')->where('id',$orderid)->where('aid',aid)->where('mid',mid)->delete();
        }
        return $this->json(['status'=>1,'msg'=>'删除成功']);
    }
    function orderCollect(){ //确认收货
        die;
        $this->checklogin();
        $post = input('post.');
        $orderid = intval($post['orderid']);
        $order = Db::name('gift_bag_order')->where('aid',aid)->where('mid',mid)->where('id',$orderid)->find();
        if(!$order || ($order['status']!=2)){
            return $this->json(['status'=>0,'msg'=>'订单状态不符合收货要求']);
        }
        $rs = \app\commons\Order::collect($order,'gift_bag');
        if($rs['status'] == 0) return $this->json($rs);
        Db::name('gift_bag_order')->where('aid',aid)->where('mid',mid)->where('id',$orderid)->update(['status'=>3,'collect_time'=>time()]);
        \app\commons\Member::uplv(aid,mid);
        return $this->json(['status'=>1,'msg'=>'确认收货成功']);
    }
    function refund(){//申请退款
        $this->checklogin();
        if(request()->isPost()){

            $set = Db::name('tour_set')->where('aid',aid)->find();
            if(!$set || !$set['can_refund']){
                return $this->json(['status'=>0,'msg'=>'暂不支持退款']);
            }

            $post = input('post.');
            $orderid = intval($post['orderid']);
            $money = floatval($post['money']);
            $order = Db::name('gift_bag_order')->where('aid',aid)->where('mid',mid)->where('id',$orderid)->find();
            if(!$order || ($order['status']!=1 && $order['status'] != 2) || $order['refund_status'] == 2){
                return $this->json(['status'=>0,'msg'=>'订单状态不符合退款要求']);
            }
            if($money < 0 || $money > $order['totalprice']){
                return $this->json(['status'=>0,'msg'=>'退款金额有误']);
            }
            Db::name('gift_bag_order')->where('aid',aid)->where('mid',mid)->where('id',$orderid)->update(['refund_time'=>time(),'refund_status'=>1,'refund_reason'=>$post['reason'],'refund_money'=>$money]);

            return $this->json(['status'=>1,'msg'=>'提交成功,请等待商家审核']);
        }
        $rdata = [];
        $rdata['price'] = input('param.price/f');
        $rdata['orderid'] = input('param.orderid/d');
        $order = Db::name('gift_bag_order')->where('aid',aid)->where('mid',mid)->where('id',$rdata['orderid'])->find();
        $rdata['price'] = $order['totalprice'];
        return $this->json($rdata);
    }

}