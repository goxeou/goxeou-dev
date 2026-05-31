<?php


//管理员中心 - 订单管理
namespace app\controllers;
use think\facade\Db;
class ApiAdminRestaurantOrderCommon extends ApiAdmin
{
	//备注
	public function setremark(){
		$post = input('post.');
		$type = $post['type'];
		$orderid = $post['orderid'];
		$content = $post['content'];
		Db::name($type.'_order')->where(['aid'=>aid,'bid'=>bid,'id'=>$orderid])->update(['remark'=>$content]);
		return $this->json(['status'=>1,'msg'=>'设置完成']);
	}
	//删除订单
	function delOrder(){
		$post = input('post.');
		$type = $post['type'];
		$orderid = input('post.orderid/d');
		$order = Db::name($type.'_order')->where(['id'=>$orderid,'aid'=>aid])->find();
		if(!$order || $order['status']!=4){
			return $this->json(['status'=>0,'msg'=>'删除失败,订单状态错误']);
		}else{
			$rs = Db::name($type.'_order')->where(['id'=>$orderid,'aid'=>aid])->delete();
            if(\app\commons\Order::hasOrderGoodsTable($type)){
                $rs = Db::name($type.'_order_goods')->where(['orderid'=>$orderid,'aid'=>aid])->delete();
            }
			return $this->json(['status'=>1,'msg'=>'删除成功']);
		}
	}
	//改为已支付
	function ispay(){
		if(bid != 0) return json(['status'=>-4,'msg'=>'无操作权限']);
		$type = input('post.type');
		$orderid = input('post.orderid/d');
		Db::name($type.'_order')->where(['aid'=>aid,'id'=>$orderid])->update(['status'=>1,'paytime'=>time(),'paytype'=>'后台支付']);
		if(\app\commons\Order::hasOrderGoodsTable($type)){
            Db::name($type.'_order_goods')->where(['orderid'=>$orderid,'aid'=>aid])->update(['status'=>1]);
        }
		//奖励积分
		$order = Db::name($type.'_order')->where(['aid'=>aid,'id'=>$orderid])->find();
		if($order['givescore'] > 0){
			\app\commons\Member::addscore(aid,$order['mid'],$order['givescore'],'购买奖励'.t('积分'));
		}
		return $this->json(['status'=>1,'msg'=>'操作成功']);
	}
	//发货
	function sendExpress(){
		$post = input('post.');
		$type = $post['type'];
		if($type == 'restaurant_shop') $tourl = 'restaurant/shop/orderlist';
		if($type == 'restaurant_takeaway') $tourl = 'restaurant/takeaway/orderlist';
		$orderid = $post['orderid'];
		$express_com = $post['express_com'];
		$express_no = $post['express_no'];
		Db::name($type.'_order')->where(['aid'=>aid,'id'=>$orderid])->update(['express_com'=>$express_com,'express_no'=>$express_no,'send_time'=>time(),'status'=>2]);
		if(\app\commons\Order::hasOrderGoodsTable($type)) {
            Db::name($type.'_order_goods')->where(['orderid'=>$orderid,'aid'=>aid])->update(['status'=>2]);
        }
		$order = Db::name($type.'_order')->where(['aid'=>aid,'id'=>$orderid])->find();
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
		\app\commons\Wechat::sendtmpl(aid,$order['mid'],'tmpl_orderfahuo',$tmplcontent,m_url($tourl),$tmplcontentNew);
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
		\app\commons\Wechat::sendwxtmpl(aid,$order['mid'],'tmpl_orderfahuo',$tmplcontentnew,$tourl,$tmplcontent);

		//短信通知
		$member = Db::name('member')->where(['id'=>$order['mid']])->find();
		$rs = \app\commons\Sms::send(aid,$member['tel']?$member['tel']:$order['tel'],'tmpl_orderfahuo',['ordernum'=>$order['ordernum'],'express_com'=>$express_com,'express_no'=>$express_no]);

		return $this->json(['status'=>1,'msg'=>'操作成功']);
	}
	//退款驳回
	function refundnopass(){
		$type = input('post.type');
		$orderid = input('post.orderid/d');
		$remark = input('post.remark');
		Db::name($type.'_order')->where(['id'=>$orderid,'aid'=>aid])->update(['refund_status'=>3,'refund_checkremark'=>$remark]);
		$order = Db::name($type.'_order')->where(['id'=>$orderid,'aid'=>aid])->find();

		if($type == 'restaurant_shop') $tourl = 'restaurant/shop/orderlist';
		if($type == 'restaurant_takeaway') $tourl = 'restaurant/takeaway/orderlist';
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
		\app\commons\Wechat::sendtmpl(aid,$order['mid'],'tmpl_tuierror',$tmplcontent,m_url($tourl),$tmplcontentNew);
		//订阅消息
		$tmplcontent = [];
		$tmplcontent['amount3'] = $order['refund_money'];
		$tmplcontent['thing2'] = $order['title'];
		$tmplcontent['character_string1'] = $order['ordernum'];
		
		$tmplcontentnew = [];
		$tmplcontentnew['amount3'] = $order['refund_money'];
		$tmplcontentnew['thing8'] = $order['title'];
		$tmplcontentnew['character_string4'] = $order['ordernum'];
		\app\commons\Wechat::sendwxtmpl(aid,$order['mid'],'tmpl_tuierror',$tmplcontentnew,$tourl,$tmplcontent);
		//短信通知
		$member = Db::name('member')->where(['id'=>$order['mid']])->find();
		$rs = \app\commons\Sms::send(aid,$member['tel']?$member['tel']:$order['tel'],'tmpl_tuierror',['ordernum'=>$order['ordernum'],'reason'=>$remark]);
		return $this->json(['status'=>1,'msg'=>'退款已驳回']);
	}
	//退款通过
	function refundpass(){
		$type = input('post.type');
		$orderid = input('post.orderid/d');
		$refund_desc = input('post.reason');
		$order = Db::name($type.'_order')->where(['id'=>$orderid,'aid'=>aid])->find();
		if($order['status']!=1 && $order['status']!=2){
			return json(['status'=>0,'msg'=>'该订单状态不允许退款']);
		}
		
		$rs = \app\commons\Order::refund($order,$order['refund_money'],$refund_desc);
		if($rs['status']==0){
			return json(['status'=>0,'msg'=>$rs['msg']]);
		}

		Db::name($type.'_order')->where(['id'=>$orderid,'aid'=>aid])->update(['status'=>4,'refund_status'=>2,'refund_reason'=>$refund_desc]);
		if(\app\commons\Order::hasOrderGoodsTable($type)){
            Db::name($type.'_order_goods')->where(['orderid'=>$orderid,'aid'=>aid])->update(['status'=>4]);
        }
		//积分抵扣的返还
		if($order['scoredkscore'] > 0){
			\app\commons\Member::addscore(aid,$order['mid'],$order['scoredkscore'],'订单退款返还');
		}
		//扣除消费赠送积分
        \app\commons\Member::decscorein(aid,$type,$order['id'],$order['ordernum'],'订单退款扣除消费赠送');
		//优惠券抵扣的返还
		if($order['coupon_rid'] > 0){
			Db::name('coupon_record')->where(['aid'=>aid,'mid'=>$order['mid'],'id'=>$order['coupon_rid']])->update(['status'=>0,'usetime'=>'']);
		}
		if($type == 'restaurant_shop') $tourl = 'restaurant/shop/orderlist';
		if($type == 'restaurant_takeaway') $tourl = 'restaurant/takeaway/orderlist';
		//退款成功通知
		$tmplcontent = [];
		if($type=='scoreshop'){
			$tmplcontent['first'] = '您的订单已经完成退款，'.$order['score_price'].t('积分').' + ¥'.$order['refund_money'].'已经退回您的付款账户，请留意查收。';
			$tmplcontent['orderProductPrice'] = $order['score_price'].t('积分').' + ¥'.$order['refund_money'];
		}else{
			$tmplcontent['first'] = '您的订单已经完成退款，¥'.$order['refund_money'].'已经退回您的付款账户，请留意查收。';
			$tmplcontent['orderProductPrice'] = $order['refund_money'];
		}
		$tmplcontent['remark'] = $remark.'，请点击查看详情~';
		$tmplcontent['orderProductName'] = $order['title'];
		$tmplcontent['orderName'] = $order['ordernum'];
        $tmplcontentNew = [];
        $tmplcontentNew['character_string1'] = $order['ordernum'];//订单编号
        $tmplcontentNew['thing2'] = $order['title'];//商品名称
        $tmplcontentNew['amount3'] = $order['refund_money'];//退款金额
		\app\commons\Wechat::sendtmpl(aid,$order['mid'],'tmpl_tuisuccess',$tmplcontent,m_url($tourl),$tmplcontentNew);
		//订阅消息
		$tmplcontent = [];
		$tmplcontent['amount6'] = $order['refund_money'];
		$tmplcontent['thing3'] = $order['title'];
		$tmplcontent['character_string2'] = $order['ordernum'];
		
		$tmplcontentnew = [];
		$tmplcontentnew['amount3'] = $order['refund_money'];
		$tmplcontentnew['thing6'] = $order['title'];
		$tmplcontentnew['character_string4'] = $order['ordernum'];
		\app\commons\Wechat::sendwxtmpl(aid,$order['mid'],'tmpl_tuisuccess',$tmplcontentnew,$tourl,$tmplcontent);

		//短信通知
		$member = Db::name('member')->where(['id'=>$order['mid']])->find();
		$rs = \app\commons\Sms::send(aid,$member['tel']?$member['tel']:$order['tel'],'tmpl_tuisuccess',['ordernum'=>$order['ordernum'],'money'=>$order['refund_money']]);

		return $this->json(['status'=>1,'msg'=>'已退款成功']);
	}
	//关闭订单
	function closeOrder(){
		$type = input('post.type');
		$orderid = input('post.orderid/d');
		$order = Db::name($type.'_order')->where(['id'=>$orderid,'aid'=>aid])->find();
		if(!$order || $order['status']!=0){
			return $this->json(['status'=>0,'msg'=>'关闭失败,订单状态错误']);
		}
		if(\app\commons\Order::hasOrderGoodsTable($type)){
            //加库存
            $oglist = Db::name($type.'_order_goods')->where(['aid'=>aid,'orderid'=>$orderid])->select();
            if($oglist) {
                foreach($oglist as $og){
                    Db::name('restaurant_product_guige')->where(['aid'=>aid,'id'=>$og['ggid']])->update(['stock'=>Db::raw("stock+".$og['num']),'sales'=>Db::raw("sales-".$og['num'])]);
                    Db::name('restaurant_product')->where(['aid'=>aid,'id'=>$og['proid']])->update(['stock'=>Db::raw("stock+".$og['num']),'sales'=>Db::raw("sales-".$og['num'])]);
                }
            }
		}
		//积分抵扣的返还
		if($order['scoredkscore'] > 0){
			\app\commons\Member::addscore(aid,$order['mid'],$order['scoredkscore'],'订单退款返还');
		}
		//优惠券抵扣的返还
		if($order['coupon_rid'] > 0){
			Db::name('coupon_record')->where(['aid'=>aid,'id'=>$order['coupon_rid']])->update(['status'=>0,'usetime'=>'']);
		}
		$rs = Db::name($type.'_order')->where(['id'=>$orderid,'aid'=>aid])->update(['status'=>4]);
        if(\app\commons\Order::hasOrderGoodsTable($type)){
            Db::name($type.'_order_goods')->where(['orderid'=>$orderid,'aid'=>aid])->update(['status'=>4]);
        }
		return $this->json(['status'=>1,'msg'=>'操作成功']);
	}
	
	//获取配送员
	public function getpeisonguser(){
        $type = input('post.type');
		$set = Db::name('peisong_set')->where('aid',aid)->find();
		
		$order = Db::name($type.'_order')->where('id',input('param.orderid'))->find();
		if($order['bid']>0){
			$business = Db::name('business')->field('name,address,tel,logo,longitude,latitude')->where('id',$order['bid'])->find();
		}else{
			$business = Db::name('admin_set')->field('name,address,tel,logo,longitude,latitude')->where('aid',aid)->find();
		}

        //查询骑行距离
        $mapqq = new \app\commons\MapQQ();
        $bicycl = $mapqq->getDirectionDistance($order['longitude'],$order['latitude'],$business['longitude'],$business['latitude'],1);
        if($bicycl && $bicycl['status']==1){
            $juli = $bicycl['distance'];
        }else{
            $juli = getdistance($order['longitude'],$order['latitude'],$business['longitude'],$business['latitude'],1);
        }
		$ticheng = \app\models\PeisongOrder::ticheng($set,$order,$juli/1000);
		if($set['make_status']==1){ //码科配送
			$rs = \app\commons\Make::getprice(aid,$order['bid'],$business['latitude'],$business['longitude'],$order['latitude'],$order['longitude']);
			if($rs['status']==0) return json($rs);
			$ticheng = $rs['price'];
			$selectArr = [];
			$set['paidantype'] = 2;
		}else{
			$selectArr = [];
			if($set['paidantype'] == 0){ //抢单模式
				$selectArr[] = ['id'=>0,'title'=>'--配送员抢单--'];
			}else{
				$peisonguser = Db::name('peisong_user')->where('aid',aid)->where('status',1)->order('sort desc,id')->select()->toArray();
				foreach($peisonguser as $k=>$v){
					$dan = Db::name('peisong_order')->where('psid',$v['id'])->where('status','in','0,1')->count();
					$title = $v['realname'].'-'.$v['tel'].'(配送中'.$dan.'单)';
					$selectArr[] = ['id'=>$v['id'],'title'=>$title];
				}
			}
		}
		$psfee = $ticheng * (1 + $set['businessfee']*0.01);
		return json(['status'=>1,'peisonguser'=>$selectArr,'paidantype'=>$set['paidantype'],'psfee'=>$psfee,'ticheng'=>$ticheng]);
	}
	//下配送单
	public function peisong(){
		$orderid = input('post.orderid/d');
		$type = input('post.type');
		$psid = input('post.psid/d');
		$order = Db::name($type.'_order')->where('id',$orderid)->where('aid',aid)->where('bid',bid)->find();
		if(!$order) return json(['status'=>0,'msg'=>'订单不存在']);
		if($order['status']!=1) return json(['status'=>0,'msg'=>'订单状态不符合']);

		$rs = \app\models\PeisongOrder::create($type,$order,$psid);
		if($rs['status']==0) return json($rs);
		\app\commons\System::plog('订单配送'.$orderid);
		return json(['status'=>1,'msg'=>'操作成功']);
	}
}