<?php


namespace app\models;
use think\facade\Db;
use think\facade\Log;
use function Qiniu\waterImg;

class Payorder
{
	//创建支付订单
	public static function createorder($aid,$bid,$mid,$type,$orderid,$ordernum,$title,$money,$score=0,$service_fee=0,$credit=0){
		$data = [];
		$data['aid'] = $aid;
		$data['bid'] = $bid;
		$data['mid'] = $mid;
		$data['orderid'] = $orderid;
		$data['ordernum'] = $ordernum;
		$data['title'] = $title;
		$data['money'] = $money;
		$data['score'] = $score;
		$data['credit'] = $credit;
        if(getcustom('product_service_fee')){
		    $data['service_fee_money'] = $service_fee;
        }
		$data['type'] = $type; //shop collage scoreshop kanjia seckill recharge designerpage form servicefee_recharge
		$data['createtime'] = time();
		$id = Db::name('payorder')->insertGetId($data);
		if($type == 'shop_hb' || $type == 'scoreshop_hb' || $type == 'shop_fenqi'){
			//Db::name('shop_order')->where('ordernum','like',$ordernum.'%')->update(['payorderid'=>$id]);
		}else{
			Db::name($type.'_order')->where('id',$orderid)->update(['payorderid'=>$id]);
		}
        //创建新的支付单，关闭旧的支付单
        Db::name('payorder')->where('id','<>',$id)->where('aid',$data['aid'])->where('bid',$data['bid'])->where('orderid',$data['orderid'])->where('type',$data['type'])->where('status',0)->update(['status'=>2]);
		return $id;
	}
	//修改订单
	public static function updateorder($id,$newOrdernum,$newprice,$updateOrderId=0){

//        // 查询订单原价
//        $order_info = Db::name('shop_order')->where('aid',aid)->where('id', $orderid)->find();
//        // 查询订单原来应该支付金额
//        $pay_money = Db::name('payorder')->where('aid',aid)->where('id', $order_info['payorderid'])->value('money');
//        // 计算修改差价
//        $order_num = explode('_', $order_info['ordernum'])[0] ?? $order_info['ordernum'] ; // 原支付订单号
//        // 查询同笔订单信息
//        $shop_order_info = Db::name('shop_order')->where('aid',aid)->where('ordernum', 'like',  $order_num . '%' )->select()->toArray();
//
//        // print_r($shop_order_info);die;
//
//        foreach ($shop_order_info as $k=>$v){
//            // 修改订单号
//            $new_order_num = str_replace($order_num, $newordernum, $v['ordernum']) ;
//            $update_arr = [ 'ordernum'=>$new_order_num  ] ;
//            // 只有当前修改的订单改价 其它子订单只修改单号 解决支付出现重复单号-金额不同问题
//            if($v['id'] == $orderid){
//                $update_arr['totalprice'] = $newprice ; // 后台改动后金额
//            }
//            Db::name('shop_order')->where('aid',aid)->where('id',$v['id'])->update($update_arr);
//            Db::name('shop_order_goods')->where('aid',aid)->where('orderid',$v['id'])->update(['ordernum'=>$new_order_num]);
//            // 修改子支付订单 订单号
//            Db::name('payorder')->where('ordernum', $v['ordernum'])->update(['ordernum'=>$new_order_num]);
//        }
//
//        // 计算差价 修改 总支付订单差价
//        if($order_info['totalprice'] > $newprice){
//            $newprice = bcsub($pay_money, bcsub($order_info['totalprice'], $newprice, 2), 2) ;
//        }else{
//            $newprice = bcadd($pay_money, bcsub($newprice, $order_info['totalprice'], 2), 2) ;
//        }

        $payorder = Db::name('payorder')->where('id',$id)->find();
        $ordernumArr = explode('_',$payorder['ordernum']);
        //合并订单
        if($ordernumArr[1]){
//        if(in_array($payorder['type'],['shop_hb','restaurant_takeaway_hb','scoreshop_hb'])){
            //合并支付订单重新计算
            // 查询同笔订单信息
            $child_order = Db::name('payorder')->where('aid',$payorder['aid'])->where('ordernum', 'like',  $ordernumArr[0] . '_%' )->select()->toArray();
            //修改子价格，修改订单号
            if($child_order){
                $totalprice = 0;
                $newOrdernumArr = explode('_',$newOrdernum);
                $newOrdernum = $newOrdernumArr[0];
                foreach ($child_order as $v){
                    // 修改订单号
                    $order_num = explode('_',$v['ordernum']);
                    $new_order_num = str_replace($order_num[0], $newOrdernum, $v['ordernum']) ;
                    $update_arr = ['ordernum'=>$new_order_num] ;
                    if($v['orderid'] == $updateOrderId){
                        $update_arr['money'] = $newprice ; //改动后金额
                        $totalprice += $newprice;
                    }else{
                        $totalprice += $v['money'];
                        $where=[];
                        $where[]=['aid','=',$v['aid']];
                        $where[]=['id','=',$v['orderid']];
                        Db::name($v['type'].'_order')->where($where)->where('id',$v['orderid'])->update($update_arr);
                        if(\app\commons\Order::hasOrderGoodsTable($v['type'])){
                            Db::name($v['type'].'_order_goods')->where('aid',$v['aid'])->where('orderid',$v['orderid'])->update($update_arr);
                        }
                    }
                    Db::name('payorder')->where('id',$v['id'])->update($update_arr);
                }

                //修改主价格，修改订单号
                Db::name('payorder')->where('ordernum',$ordernumArr[0])->update(['ordernum'=>$newOrdernum,'money'=>$totalprice]);
            }
        }else{
            Db::name('payorder')->where('id',$id)->update(['ordernum'=>$newOrdernum,'money'=>$newprice]);
        }
	}
	//支付完成后操作
	public static function payorder($orderid,$paytype,$paytypeid,$paynum=''){
		if(!$orderid) return;
		$payorder = Db::name('payorder')->where('id',$orderid)->find();
		//dump($payorder.'看看支付后的操作');
		if(!$payorder || $payorder['status']==1) return ['status'=>0,'msg'=>'该订单已支付'];
		if(getcustom('pay_yuanbao') && $payorder['type'] == 'shop'){
            if($payorder['is_yuanbao_pay'] == 1){
                $paytype = t('元宝').'支付+'.$paytype;
            }
            //元宝 更新shop_order和payorder
            self::yuanbao_up($payorder['type'],$payorder);
        }
		//成为多商户会员
        if(getcustom('business_member')){
            if($payorder['bid'] >0){
                $business_member = Db::name('business_member')->where('aid',$payorder['aid'])->where('bid',$payorder['bid'])->where('mid',$payorder['mid'])->find();
                if(empty($business_member)){
                    $insert=[
                        'aid' => $payorder['aid'],
                        'bid' => $payorder['bid'],
                        'mid' => $payorder['mid'],
                        'createtime' => time()
                    ];
                    Db::name('business_member')->insert($insert);
                }
            }
        }

		if($payorder['type'] == 'restaurant_shop' && $paytypeid == 4) {
            Db::name('payorder')->where('id',$orderid)->update(['paytype'=>$paytype,'paytypeid'=>$paytypeid,'paynum'=>$paynum]);
        } else {
            Db::name('payorder')->where('id',$orderid)->update(['status'=>1,'paytime'=>time(),'paytype'=>$paytype,'paytypeid'=>$paytypeid,'paynum'=>$paynum]);
        }
		$type = $payorder['type'];

		if($payorder['mid']){
		    Db::name('member')->where('id',$payorder['mid'])->update(['last_buytime'=>time()]);
        }

        if(getcustom('member_gongxian')){
            if(!in_array($type,['recharge'])){
                $aid = $payorder['aid'];
                $admin = Db::name('admin')->where('id',$aid)->find();
                $set = Db::name('admin_set')->where('aid',$aid)->find();
                if(empty($set['gongxian_bonus_disable']) || $payorder['bid']==0){//多商户商品是否参与赠送贡献值
                    if($admin['member_gongxian_status'] == 1 && $set['gongxianin_money'] > 0 && $set['gognxianin_value'] > 0){
                        $givevalue = floor($payorder['money'] / $set['gongxianin_money']) * $set['gognxianin_value'];
                        \app\commons\Member::addgongxian($aid,$payorder['mid'],$givevalue,'消费送'.t('贡献'),$type,$payorder['orderid']);
                    }
                }

            }
        }
        if($type=='daifu'){
            Db::name('payorder')->where('id',$payorder['pid'])->update([
                'paynum'=>$paynum,
                'status'=>1,
                'paytypeid'=>$paytypeid,
                'paytype'=>$paytype,
                'paytime'=>time(),
                'paymid'=>$payorder['mid'],
            ]);
            $payorder = Db::name('payorder')->where('id',$payorder['pid'])->find();
            $type = $payorder['type'];
        }
                   
                   
                                          
		if($type == 'shop_hb'){
			Db::name('shop_order')->where('ordernum','like',$payorder['ordernum'].'%')->update(['status'=>1,'paytime'=>time(),'paytype'=>$paytype,'paytypeid'=>$paytypeid,'paynum'=>$paynum,'platform'=>$payorder['platform']]);
		}elseif($type == 'expert_comment'){
	      	Db::name('expert_comment')->where('id',$payorder['orderid'])->update(['status'=>1,'paytime'=>time(),'paytype'=>$paytype,'paytypeid'=>$paytypeid,'paynum'=>$paynum,'platform'=>$payorder['platform']]);
		}elseif($type == 'scoreshop_hb'){
			Db::name('scoreshop_order')->where('ordernum','like',$payorder['ordernum'].'%')->update(['status'=>1,'paytime'=>time(),'paytype'=>$paytype,'paytypeid'=>$paytypeid,'paynum'=>$paynum,'platform'=>$payorder['platform']]);
		}elseif($type == 'balance'){
			Db::name('shop_order')->where('id',$payorder['orderid'])->update(['balance_pay_status'=>1,'balance_pay_orderid'=>$orderid]);
			return ['status'=>1,'msg'=>''];
		}elseif($type == 'shopfront'){
			$order = Db::name('shop_order')->where('id',$payorder['orderid'])->find();
			Db::name('shop_order')->where('id',$payorder['orderid'])->update(['status'=>1,'paytime'=>time(),'paytype'=>$paytype,'paytypeid'=>$paytypeid,'paynum'=>$paynum,'platform'=>$payorder['platform'],'balance_price'=>$order['totalprice'] - $payorder['money']]);
			self::shop_pay($payorder['orderid']);
			return ['status'=>1,'msg'=>''];
		}elseif($type == 'yuyue_balance'){
			Db::name('yuyue_order')->where('id',$payorder['orderid'])->update(['status'=>3,'balance_pay_status'=>1,'balance_pay_orderid'=>$orderid]);
		}elseif($type == 'yuyue_addmoney'){
			Db::name('yuyue_order')->where('id',$payorder['orderid'])->update(['addmoneyStatus'=>1]);
		}elseif($type == 'seckill2'){
			Db::name('seckill2_order')->where('id',$payorder['orderid'])->update(['status'=>1,'paytime'=>time(),'paytype'=>$paytype,'paytypeid'=>$paytypeid,'paynum'=>$paynum,'platform'=>$payorder['platform']]);
			$form_sale = Db::name('seckill2_order')->alias('or')->join('seckill2_product pro','or.proid = pro.id')->field('pro.saleid')->where('or.ordernum',$payorder['ordernum'])->find();
			$sale = Db::name('seckill2_sale')->where('id',$form_sale['saleid'])->find();
			Db::name('seckill2_order')->where('ordernum',$sale['form_ordernum'])->update(['status'=>10]);
		}elseif($type == 'form'){
			Db::name($type.'_order')->where('id',$payorder['orderid'])->update(['paystatus'=>1,'paytime'=>time(),'paytype'=>$paytype,'paytypeid'=>$paytypeid,'paynum'=>$paynum,'platform'=>$payorder['platform']]);
		}elseif($type == 'plug_business_pay'){
			Db::name($type.'_order')->where('id',$payorder['orderid'])->update(['status'=>1,'paytime'=>time(),'paytype'=>$paytype,'paytypeid'=>$paytypeid,'paynum'=>$paynum,'platform'=>$payorder['platform']]);
        }elseif($type == 'restaurant_shop' && $paytypeid == 4){
            Db::name($type.'_order')->where('id',$payorder['orderid'])->update(['paytype'=>$paytype,'paytypeid'=>$paytypeid,'paynum'=>$paynum,'platform'=>$payorder['platform']]);
        }elseif($type == 'workorder'){  //工单订单
            Db::name($type.'_order')->where('id',$payorder['orderid'])->update(['paystatus'=>1,'paytype'=>$paytype,'paytypeid'=>$paytypeid,'paynum'=>$paynum,'platform'=>$payorder['platform']]);
        }elseif($type == 'hbtk'){  //拓客活动
		    if(getcustom('yx_hbtk')){
                Db::name($type.'_order')->where('id',$payorder['orderid'])->update(['status'=>1,'paytype'=>$paytype,'paytypeid'=>$paytypeid,'paynum'=>$paynum,'platform'=>$payorder['platform']]);
            }
        }elseif($type=='imgai'){
            Db::name($type.'_order')->where('id',$payorder['orderid'])->update(['status'=>1,'paytime'=>time(),'paytype'=>$paytype,'paytypeid'=>$paytypeid,'paynum'=>$paynum,'platform'=>$payorder['platform']]);
        }elseif($type=='mapmark'){
            Db::name($type.'_order')->where('id',$payorder['orderid'])->update(['status'=>1,'paytime'=>time(),'paytype'=>$paytype,'paytypeid'=>$paytypeid,'paynum'=>$paynum,'platform'=>$payorder['platform']]);
        }elseif($type=='videospider'){
            Db::name($type.'_order')->where('id',$payorder['orderid'])->update(['status'=>1,'paytime'=>time(),'paytype'=>$paytype,'paytypeid'=>$paytypeid,'paynum'=>$paynum,'platform'=>$payorder['platform']]);
        }elseif($type=='cerberuse'){
		    if(getcustom('lot_cerberuse')){
                Db::name($type.'_order')->where('id',$payorder['orderid'])->update(['status'=>1,'paytime'=>time(),'paytype'=>$paytype,'paytypeid'=>$paytypeid,'platform'=>$payorder['platform']]);
            }
        }elseif($type=='shop_fenqi'){
            $fenqi_order = Db::name('shop_order')->where('id',$payorder['orderid'])->find();
            $fenqi_money = round($payorder['money'] + $fenqi_order['totalprice'],2);
            Db::name('shop_order')->where('id',$payorder['orderid'])->update(['status'=>1,'paytime'=>time(),'paytype'=>$paytype,'paytypeid'=>$paytypeid,'platform'=>$payorder['platform'],'totalprice'=>$fenqi_money]);
            self::shop_fenqi_pay_change($payorder);
        }elseif($type=='livepay') {
            Db::name('livepay_order')->where('id', $payorder['orderid'])->update(['status' => 1, 'paytime' => time(), 'paytype' => $paytype, 'paytypeid' => $paytypeid, 'platform' => $payorder['platform']]);
            $order = Db::name('livepay_order')->where('id', $payorder['orderid'])->find();
            $set = Db::name('admin_set')->where('aid', $aid)->find();
            if ($set['fxjiesuantime'] == 1 && $set['fxjiesuantime_delaydays'] == '0') {
                \app\commons\Order::giveCommission($order, 'livepay');
            }
            return ['status' => 1, 'msg' => ''];
        }elseif($type=='huodong_baoming') {
            Db::name('huodong_baoming_order')->where('id', $payorder['orderid'])->update(['status' => 1, 'paytime' => time(), 'paytype' => $paytype, 'paytypeid' => $paytypeid, 'platform' => $payorder['platform']]);
            $order = Db::name('huodong_baoming_order')->where('id', $payorder['orderid'])->find();
            Db::name('huodong_baoming_product')->where('aid',$aid)->where('id',$order['proid'])->update(['sales'=>Db::raw("sales+".$order['num'])]);
            if ($order['givescore'] > 0) {
                \app\commons\Member::addscore($aid,$order['mid'],$order['givescore'],'参与活动赠送'.t('积分'));
            }
            return ['status' => 1, 'msg' => ''];
        }elseif($type=='servicefee_recharge'){
            if(getcustom('product_service_fee')){
                Db::name($type.'_order')->where('id',$payorder['orderid'])->update(['status'=>1,'paytime'=>time(),'paytype'=>$paytype,'paytypeid'=>$paytypeid,'platform'=>$payorder['platform']]);
                \app\commons\Member::addServiceFee($payorder['aid'],$payorder['mid'],$payorder['money'],$paytype);
                return ['status'=>1,'msg'=>''];
            }
        }else{
			Db::name($type.'_order')->where('id',$payorder['orderid'])->update(['status'=>1,'paytime'=>time(),'paytype'=>$paytype,'paytypeid'=>$paytypeid,'paynum'=>$paynum,'platform'=>$payorder['platform']]);
		}
		if(getcustom('invite_free')){
        	if($payorder['type'] == 'shop'){
        		//发送订单通知
        		self::send_free_notice($payorder);
        	}
        }

		$fun = $type.'_pay';
		self::$fun($payorder['orderid'],$payorder['ordernum']);
        self::afterusecoupon($payorder['orderid'],$type,1,$payorder['ordernum']);
		self::payaftergive($payorder);

        if(getcustom('task_banner')){
            self::taskbanner($payorder['aid'],$payorder['mid']);
        }
        if(getcustom('ganer_fenxiao')){
            $set = Db::name('prize_pool_set')->where('aid',$payorder['aid'])->find();
            //订单业绩进入奖金池
            if($set['pool_time']==0){
                \app\commons\Fenxiao::bonus_poul($payorder['orderid'],$type);
            }
        }

        //支付完成增加商户销量,幸运拼团在开奖时触发
        if($type!='lucky_collage'){
            self::addSales($payorder['orderid'],$type,$payorder['aid'],$payorder['bid']);
        }

        if(getcustom('business_fenxiao')){
            $payorder = Db::name('payorder')->where('id',$orderid)->find();
            $fenxiao_type = Db::name('admin_set')->where('aid',$payorder['aid'])->value('business_fenxiao_type');
            //支付完成，店铺分销统计日营业额
            if($fenxiao_type==0){
                \app\commons\Business::countBusinessYeji($payorder);
            }
        }
        if(getcustom('yx_buy_fenhong')){
            if($payorder['type'] !='recharge' && $payorder['mid'] > 0){
                //余额支付和充值重复，保留余额
                \app\customs\BuyFenhong::getScoreWeight($payorder);
            }
        }
		return ['status'=>1,'msg'=>''];
	}
	//更新商户销量
	public static function addSales($orderid,$type,$aid,$bid=0,$sale_num=0){
        $sales_type = [
            'sales' => 'sales',//虚拟销量
            'shop' => 'shop_sales',//普通商铺
            'collage' => 'collage_sales',//多人拼团
            'kanjia' => 'kanjia_sales',//砍价
            'seckill' => 'seckill_sales',//秒杀
            'tuangou' => 'tuangou_sales',//团购
            'scoreshop' => 'scoreshop_sales',//积分商城
            'lucky_collage' => 'lucky_collage_sales',//幸运拼团
            'yuyue' => 'yuyue_sales',//预约服务
            'kecheng' => 'kecheng_sales',//课程
            'cycle' => 'cycle_sales',//周期购
            'restaurant_takeaway' => 'restaurant_takeaway_sales',//餐饮外卖
            'restaurant_shop' => 'restaurant_shop_sales',//餐饮点餐
            'maidan' => 'maidan_sales'//买单
        ];
        if(!empty($sales_type[$type])){
            if($sale_num==0 && $orderid){
                switch ($type){
                    case 'shop':
                        $sale_num = Db::name('shop_order_goods')->where('orderid',$orderid)->sum('num');
                        break;
                    case 'collage':
                        $sale_num = Db::name('collage_order')->where('id',$orderid)->sum('num');
                        break;
                    case 'kanjia':
                        $sale_num = Db::name('kanjia_order')->where('id',$orderid)->sum('num');
                        break;
                    case 'seckill':
                        $sale_num = Db::name('seckill_order')->where('id',$orderid)->sum('num');
                        break;
                    case 'tuangou':
                        $sale_num = Db::name('tuangou_order')->where('id',$orderid)->sum('num');
                        break;
                    case 'scoreshop':
                        $sale_num = Db::name('scoreshop_order_goods')->where('orderid',$orderid)->sum('num');
                        break;
                    case 'lucky_collage':
                        $sale_num = Db::name('lucky_collage_order')->where('id',$orderid)->sum('num');
                        break;
                    case 'yuyue':
                        $sale_num = Db::name('yuyue_order')->where('id',$orderid)->sum('num');
                        break;
                    case 'kecheng':
                        $sale_num = 1;
                        break;
                    case 'cycle':
                        $sale_num = Db::name('cycle_order')->where('id',$orderid)->sum('num');
                        break;
                    case 'restaurant_takeaway':
                        $sale_num = Db::name('restaurant_takeaway_order_goods')->where('orderid',$orderid)->sum('num');
                        break;
                    case 'restaurant_shop':
                        $sale_num = Db::name('restaurant_shop_order_goods')->where('orderid',$orderid)->sum('num');
                        break;
                    case 'maidan':
                        $sale_num = 1;
                        break;
                }
            }

            $sales_field = $sales_type[$type];
            $business_sales = Db::name('business_sales')
                ->where('aid',$aid)
                ->where('bid',$bid)
                ->find();
            if(!$business_sales && $sale_num>0){
                $data_sales = [];
                $data_sales['aid'] = $aid;
                $data_sales['bid'] = $bid;
                $data_sales[$sales_field] = $sale_num;
                $data_sales['total_sales'] = $sale_num;
                Db::name('business_sales')->insert($data_sales);
            }else{
                $data_sales = [];
                $data_sales[$sales_field] = $business_sales[$sales_field]+$sale_num;
                $data_sales['total_sales'] = $business_sales['total_sales']+$sale_num;
                Db::name('business_sales')->where('id',$business_sales['id'])->update($data_sales);
            }

        }
    }
	public static function cerberuse_pay($orderid,$ordernum){
         $order = Db::name('cerberuse_order')->where('id', $orderid)->find();
         $cerberuse = Db::name('cerberuse')->where('id',$order['proid'])->find();
         $member = Db::name('member')->where('aid',$order['aid'])->where('id',$order['mid'])->find();
          //短信通知
         $rs = \app\commons\Sms::send(aid,$order['tel'],'tmpl_yysucess',['name'=>$cerberuse['title'],'time'=>date('Y-m-d H:i',$order['starttime'])]);
         //通知管理员
         $tmplcontent = [];
         $tmplcontent['first']    = '有新订单提交成功';
         $tmplcontent['remark']   = '点击进入查看~';
         $tmplcontent['keyword1'] = ''; //店铺
         $tmplcontent['keyword2'] = date('Y-m-d H:i:s',$order['createtime']);//下单时间
         $tmplcontent['keyword3'] = $order['title']?$order['title']:'';//商品
         $tmplcontent['keyword4'] = $order['totalprice']?$order['totalprice']:'';//金额
         $tempconNew = [];
         $tempconNew['character_string2'] = $order['ordernum'];//订单号
         $tempconNew['thing8'] = '';//门店名称
         $tempconNew['thing3'] = $order['title']?$order['title']:'';//商品
         $tempconNew['amount7'] = $order['totalprice'];//金额
         $tempconNew['time4'] = date('Y-m-d H:i:s',$order['createtime']);//下单时间
         \app\commons\Wechat::sendhttmpl(aid,$order['bid'],'tmpl_orderconfirm',$tmplcontent,m_url('pages/index/index'),$order['mdid'],$tempconNew);
         
         $tmplcontent = [];
         $tmplcontent['thing11'] = $order['title'];
         $tmplcontent['character_string2'] = $order['ordernum'];
         $tmplcontent['phrase10'] = '已预约成功';
         $tmplcontent['amount13'] =$order['totalprice'];
         $tmplcontent['thing27'] =$order['linkman'];
         \app\commons\Wechat::sendhtwxtmpl(aid,$order['bid'],'tmpl_orderconfirm',$tmplcontent,'pages/index/index',$order['mdid']);
         
     }
	public static function hbtk_pay($orderid,$ordernum){
	    if(getcustom('yx_hbtk')){
            $hid = Db::name('hbtk_order')->where('id',$orderid)->value('hid');
            $hd = Db::name('hbtk_activity')->where('id',$hid)->find();
            //发红包 和产生佣金
            if($hd['j1yj'] > $hd['j1sl']) $hd['j1yj'] = $hd['j1sl'];
            if($hd['j2yj'] > $hd['j2sl']) $hd['j2yj'] = $hd['j2sl'];
            if($hd['j3yj'] > $hd['j3sl']) $hd['j3yj'] = $hd['j3sl'];
            if($hd['j4yj'] > $hd['j4sl']) $hd['j4yj'] = $hd['j4sl'];
            if($hd['j5yj'] > $hd['j5sl']) $hd['j5yj'] = $hd['j5sl'];
            if($hd['j6yj'] > $hd['j6sl']) $hd['j6yj'] = $hd['j6sl'];
            if($hd['j7yj'] > $hd['j7sl']) $hd['j7yj'] = $hd['j7sl'];
            if($hd['j8yj'] > $hd['j8sl']) $hd['j8yj'] = $hd['j8sl'];
            if($hd['j9yj'] > $hd['j9sl']) $hd['j9yj'] = $hd['j9sl'];
            if($hd['j10yj'] > $hd['j10sl']) $hd['j10yj'] = $hd['j10sl'];
            if($hd['j11yj'] > $hd['j11sl']) $hd['j11yj'] = $hd['j11sl'];
            if($hd['j12yj'] > $hd['j12sl']) $hd['j12yj'] = $hd['j12sl'];
            $count =  ($hd['j1sl'] - $hd['j1yj']) + ($hd['j2sl'] - $hd['j2yj']) + ($hd['j3sl'] - $hd['j3yj']) + ($hd['j4sl'] - $hd['j4yj']) + ($hd['j5sl'] - $hd['j5yj']) + ($hd['j6sl'] - $hd['j6yj']) + ($hd['j7sl'] - $hd['j7yj']) + ($hd['j8sl'] - $hd['j8yj']) + ($hd['j9sl'] - $hd['j9yj']) + ($hd['j10sl'] - $hd['j10yj']) + ($hd['j11sl'] - $hd['j11yj']) + ($hd['j12sl'] - $hd['j12yj']);
	
            if($count>0){
                $jparr = [
                    ($hd['j1sl'] - $hd['j1yj']),
                    ($hd['j2sl'] - $hd['j2yj']),
                    ($hd['j3sl'] - $hd['j3yj']),
                    ($hd['j4sl'] - $hd['j4yj']),
                    ($hd['j5sl'] - $hd['j5yj']),
                    ($hd['j6sl'] - $hd['j6yj']),
                    ($hd['j7sl'] - $hd['j7yj']),
                    ($hd['j8sl'] - $hd['j8yj']),
                    ($hd['j9sl'] - $hd['j9yj']),
                    ($hd['j10sl'] - $hd['j10yj']),
                    ($hd['j11sl'] - $hd['j11yj']),
                    ($hd['j12sl'] - $hd['j12yj']),
                ];
                $rands = rand(1,$count);
                $qian = 0;
                foreach ($jparr as $k=>$v) {
                    if($rands > $qian && $rands <= $qian + $v){
                        $jx = $k+1;
                        $jxmc = $hd["j{$jx}mc"];
                        $jxtp = $hd["j{$jx}tp"];
                        break;
                    }
                    $qian += $v;
                }
            }
            $data = [];
            $data['jx'] = $jx;
            $data['jxtp'] = $jxtp;
            $data['jxmc'] = $jxmc;
            Db::name('hbtk_order')->where('id', $orderid)->update($data);
        }
	    
     }
	public static function car_hailing_pay($orderid){
        if(getcustom('car_hailing')) {
            $order = Db::name('car_hailing_order')->where('id', $orderid)->find();
            if (!$order) return json(['status' => 0, 'msg' => '订单不存在']);
            if ($order['status'] != 1 && $order['status'] != 12) return json(['status' => 0, 'msg' => '订单状态不符合']);
            //短信通知
            $member = Db::name('member')->where('id',$order['mid'])->find();
            if($member['tel']){
                $tel = $member['tel'];
            }else{
                $tel = $order['tel'];
            }       
            $aid = $order['aid'];
            $mid = $order['aid'];
            $rs = \app\commons\Sms::send($order['aid'],$tel,'tmpl_carhailing_sucess',['ordernum'=>$order['ordernum']]);
            //支付后送券
            $couponlist = \app\commons\Coupon::getpaygive($aid,$mid,'car_hailing',$order['totalprice']);
            if($couponlist){
                foreach($couponlist as $coupon){
                    \app\commons\Coupon::send($aid,$mid,$coupon['id']);
                }
            }
        }
    }
	public static  function taskbanner($aid,$mid){
        if(getcustom('task_banner')){
            $member = Db::name('member')->where('id',$mid)->find();
            $sysset = Db::name('task_banner_set')->where('aid',$aid)->find();
            if($member['task_banner_total']>= $sysset['total_complete_num']){
                Db::name('member')->where('id',$mid)->update(['task_banner_total' => 0]);
            }
        }
    }
	//商城订单合并支付
	public static function shop_hb_pay($orderid,$ordernum){
		$orderlist = Db::name('shop_order')->where('ordernum','like',$ordernum.'%')->select()->toArray();
		foreach($orderlist as $order){
			self::shop_pay($order['id']);
		}
	}
	//分期商品支付
	public static function shop_fenqi_pay($orderid){
        self::shop_pay($orderid);
    }
	public static function shop_fenqi_pay_change($payorder){
        $orderid = $payorder['orderid'];
        $order = Db::name('shop_order')->where('id',$orderid)->find();
		$member = Db::name('member')->where('id',$order['mid'])->find();
		$aid = $order['aid'];
		$mid = $order['mid'];
		$oglist = Db::name('shop_order_goods')->where('orderid',$orderid)->select()->toArray();









        if(getcustom('shop_product_fenqi_pay')){
            if($order['is_fenqi'] == 1){
                $now_fenqi_num = explode(',',$order['now_fenqi_num']);
                $fenqi_data = json_decode($order['fenqi_data'],true);
                foreach($fenqi_data as $fqkey=>$fq){
                    if(in_array($fq['fenqi_num'],$now_fenqi_num)){

                        if($order['fenqigive_couponid']){
                            for($i=0;$i<$fq['fenqi_give_num'];$i++) {
                                \app\commons\Coupon::send($aid,$mid,$order['fenqigive_couponid']);
                            }
                        }                        
                        //分销奖励上级
                        if($member['pid'] > 0 && $order['fenqigive_fx_couponid']>0){                            
                            for($i=0;$i<$fq['fenqi_fx_num'];$i++) {
                                \app\commons\Coupon::send($aid,$member['pid'],$order['fenqigive_fx_couponid']);
                            } 
                        }
                        $fenqi_data[$fqkey]['status'] = 1;
                        $fenqi_data[$fqkey]['payorderid'] = $order['payorderid'];
                        $fenqi_data[$fqkey]['paytime'] = $order['paytime'];
                        $fenqi_data[$fqkey]['ordernum'] = $payorder['ordernum'];
                        
                    }
                }
                $up = Db::name('shop_order')->where('id',$orderid)->update(['fenqi_data'=>json_encode($fenqi_data,JSON_UNESCAPED_UNICODE)]);
            }
            
        }		
	}
	//商城订单
	public static function shop_pay($orderid){
		$order = Db::name('shop_order')->where('id',$orderid)->find();
		$member = Db::name('member')->where('id',$order['mid'])->find();
		$aid = $order['aid'];
		$mid = $order['mid'];
		$oglist = Db::name('shop_order_goods')->where('orderid',$orderid)->select()->toArray();
        Db::name('shop_order_goods')->where('orderid',$orderid)->update(['status'=>1,'paytime'=>$order['paytime']]);
        if(getcustom('sound')){
            \app\commons\Sound::play(aid,'shop',$order);
        }
        if(getcustom('commission_to_score')){
            Db::name('shop_order_goods')->where('orderid',$orderid)->update(['paytime'=>time()]);
        }
     	$set = Db::name('admin_set')->where('aid',$aid)->find();

        if(getcustom('2+1')){
            if($order["baodan"] == 1 ) {
    			\app\commons\Aaa::edit2($aid,$mid);
            }
        }
        
        
        
        
        
        
        
        
        if(getcustom('gongpai')){
         	$fengdan_num = 2;
            foreach($oglist as $og){
    			$product = Db::name('shop_product')->where('id',$og['proid'])->find();
    			if ($product['gongpai']==1) {
        	     	\app\commons\GongPai::checkpai($aid,$member['id'],$product['gongpai'],$og['totalprice']);
    			     //上级是否满足
    			    if ($member['pid']) { //上级是否满足
    	                $buycount = 0 + Db::name('shop_order_goods')->where('gongpai',$product['gongpai'])->where('status','in','1,2,3')->where('mid',$member['pid'])->count();
    	                \think\facade\Log::write('---'.$mid.'直推--上级购买---'.$buycount);
    	                if ($buycount > 0) {
    	                    $downmids = \app\commons\Member::getdownmids($aid,$member['pid'],1);
                            $ordercount = 0 + Db::name('shop_order_goods')->where('gongpai',$product['gongpai'])->where('status','in','1,2,3')->where('mid','in',$downmids)->count();
        			        $thisdannum = $ordercount%$fengdan_num;
        			        \think\facade\Log::write('---'.$mid.'直推--$thisdannum---'.$thisdannum);
    						if($thisdannum==1) {
    						    $parent1score = round($og['totalprice']*$set['pai_1']*0.01,2);
    						    \app\commons\Member::addscore($aid,$member['pid'],$parent1score,t('下级').'购买商品奖励');
        			        	// Db::name('member_commission_record')->insert(['aid'=>$aid,'mid'=>$member['pid'],'frommid'=>$og['mid'],'orderid'=>$orderid,'ogid'=>$og['id'],'type'=>'shop','score'=>$parent1score,'remark'=>t('下级').'购买商品奖励','createtime'=>time()]);
    						}else {
    						    \app\commons\GongPai::checkpai($aid,$member['pid'],$product['gongpai'],$og['totalprice']);
    						}
    	                }
    	            }
    			}elseif ($product['gongpai']==2) {
    			    \app\commons\GongPai::checkpai($aid,$member['id'],$product['gongpai'],$og['totalprice']);
    			}
            }
    
     }


        //逢单奖励时更新奖金数据
		if(getcustom('fengdanjiangli')){
			foreach($oglist as $og){
				$product = Db::name('shop_product')->where('id',$og['proid'])->find();
				if($product['fengdanjiangli'] && $product['commissionset']==2){
					$commissiondata = json_decode($product['commissiondata2'],true);
					$fengdanjiangliArr = explode(',',$product['fengdanjiangli']);
					$num = $og['num'];
					$ogupdate = [];
					/*
					$memberlevel = Db::name('member_level')->where('aid',$aid)->where('id',$member['levelid'])->find();
					if($memberlevel['sort'] > 1){
						Db::name('shop_order_goods')->where('id',$og['id'])->update(['isdan'=>1]);
						$dannum = Db::name('shop_order_goods')->where('aid',$aid)->where('proid',$product['id'])->where('status','in','1,2,3')->where("(mid=".$og['parent1']." and isdan=1) or parent1=".$og['parent1'])->sum('num');
						for($i=0;$i<$num;$i++){
							$thisdannum = ($dannum+1+$i)%10;
							if(in_array($thisdannum.'',$fengdanjiangliArr)){
								Db::name('shop_order_goods')->where('id',$og['id'])->update(['isfenhong'=>2]);
								Db::name('member_commission_record')->insert(['aid'=>$aid,'mid'=>$mid,'frommid'=>$mid,'orderid'=>$orderid,'ogid'=>$og['id'],'type'=>'shop','commission'=>$commissiondata[$memberlevel['id']]['commission1'],'remark'=>'复购奖励','createtime'=>time()]);
							}
						}
					}
					*/

					if($og['parent1']){
						$parent1 = Db::name('member')->where('id',$og['parent1'])->find();
						$agleveldata1 = Db::name('member_level')->where('aid',$aid)->where('id',$parent1['levelid'])->find();
						$dannum = Db::name('shop_order_goods')->where('aid',$aid)->where('proid',$product['id'])->where('status','in','1,2,3')->where("parent1",$og['parent1'])->sum('num');

						$ogupdate['parent1commission'] = 0;
						for($i=0;$i<$num;$i++){
							$thisdannum = ($dannum+1+$i)%10;
							if(in_array($thisdannum.'',$fengdanjiangliArr)){
								$ogupdate['parent1commission'] += $commissiondata[$agleveldata1['id']]['commission1'];
								$ogupdate['isfenhong'] = 2;
							}
						}
					}
					if($og['parent2']){
						$parent2 = Db::name('member')->where('id',$og['parent2'])->find();
						$agleveldata2 = Db::name('member_level')->where('aid',$aid)->where('id',$parent2['levelid'])->find();
						$dannum = Db::name('shop_order_goods')->where('aid',$aid)->where('proid',$product['id'])->where('status','in','1,2,3')->where("parent2",$og['parent2'])->sum('num');
						$ogupdate['parent2commission'] = 0;
						for($i=0;$i<$num;$i++){
							$thisdannum = ($dannum+1+$i)%10;
							if(in_array($thisdannum.'',$fengdanjiangliArr)){
								$ogupdate['parent2commission'] += $commissiondata[$agleveldata2['id']]['commission2'];
								$ogupdate['isfenhong'] = 2;
							}
						}
					}
					if($og['parent3']){
						$parent3 = Db::name('member')->where('id',$og['parent3'])->find();
						$agleveldata3 = Db::name('member_level')->where('aid',$aid)->where('id',$parent3['levelid'])->find();
						$dannum = Db::name('shop_order_goods')->where('aid',$aid)->where('proid',$product['id'])->where('status','in','1,2,3')->where("parent3",$og['parent3'])->sum('num');
						$ogupdate['parent3commission'] = 0;
						for($i=0;$i<$num;$i++){
							$thisdannum = ($dannum+1+$i)%10;
							if(in_array($thisdannum.'',$fengdanjiangliArr)){
								$ogupdate['parent3commission'] += $commissiondata[$agleveldata3['id']]['commission3'];
								$ogupdate['isfenhong'] = 2;
							}
						}
					}
					if($ogupdate){
						Db::name('shop_order_goods')->where('id',$og['id'])->update($ogupdate);
						if($og['parent1'] && $ogupdate['parent1commission']){
							Db::name('member_commission_record')->insert(['aid'=>$aid,'mid'=>$og['parent1'],'frommid'=>$og['mid'],'orderid'=>$orderid,'ogid'=>$og['id'],'type'=>'shop','commission'=>$ogupdate['parent1commission'],'remark'=>t('下级').'购买商品奖励','createtime'=>time()]);
						}
						if($og['parent2'] && $ogupdate['parent2commission']){
							Db::name('member_commission_record')->insert(['aid'=>$aid,'mid'=>$og['parent2'],'frommid'=>$og['mid'],'orderid'=>$orderid,'ogid'=>$og['id'],'type'=>'shop','commission'=>$ogupdate['parent2commission'],'remark'=>t('下二级').'购买商品奖励','createtime'=>time()]);
						}
						if($og['parent3'] && $ogupdate['parent3commission']){
							Db::name('member_commission_record')->insert(['aid'=>$aid,'mid'=>$og['parent3'],'frommid'=>$og['mid'],'orderid'=>$orderid,'ogid'=>$og['id'],'type'=>'shop','commission'=>$ogupdate['parent3commission'],'remark'=>t('下三级').'购买商品奖励','createtime'=>time()]);
						}
					}
				}
			}
		}
		if(getcustom('to86yk')){
			foreach($oglist as $og){
				if($og['to86yk_tid']){
					$shopset = Db::name('shop_sysset')->where('aid',$aid)->find();
					$formdata = \app\models\Freight::getformdata($orderid,'shop_order');
					$successnum = 0;
					for($i=0;$i<$og['num'];$i++){
						$url = 'http://api.yukawl.cn/api.php?act=pay&tid='.$og['to86yk_tid'].'&input1='.$formdata[0][1].'&user='.$shopset['to86yk_user'].'&pass='.$shopset['to86yk_pwd'];
						$rs = request_get($url);
						//\think\facade\Log::write($url);
						\think\facade\Log::write($rs);
						$rs = json_decode($rs,true);
						\think\facade\Log::write($rs);
						if($rs && $rs['code'] == 0) $successnum++;
					}
					Db::name('shop_order_goods')->where('id',$og['id'])->update(['to86yk_successnum'=>$successnum]);
				}
			}
		}

		if(getcustom('product_baodan')){
		   
           foreach($oglist as $og){
               $product = Db::name('shop_product')->where('id',$og['proid'])->find();
               if($product && $product['product_baodan'] ==1){
                   $field = 'baodan_beishu';
                   if(getcustom('fenhong_money_weishu')){
                       $field.=',fenhong_money_weishu';
                   }
                   $sysset = Db::name('admin_set')->where('aid',$og['aid'])->field($field)->find();
                   $max_money = $og['sell_price']* $og['num'] * $sysset['baodan_beishu']; 
                   //先更新上限值
                   //最终用户的上限值
                   $member_baodan_max = $member['baodan_max'] + $max_money;
                   Db::name('member')->where('id',$og['mid'])->update(['baodan_max' => $member_baodan_max]);
                    //判断是否有冻结,有冻结才操作解冻
                    if($member['baodan_freeze'] >0){
                         $member_total_commisiion = $member['totalcommission'];
                         //总佣金+冻结的  < 上限值，能使用的解冻范围就是上限值-用户的总佣金 
                         $max_nofreeze = 0;
                         if($member['totalcommission'] <= $member_baodan_max){
                             $max_nofreeze =  $member_baodan_max - $member['totalcommission'];
                         }
                         if($max_nofreeze >=$member['baodan_freeze'] ){
                             $nofreeze = $member['baodan_freeze'];
                         }else{
                             $nofreeze  = $max_nofreeze;
                         }
                         if($nofreeze > 0){
                             //加佣金
                             $commission = $member['commission'] + $nofreeze;
                             $totalcommission = $member['totalcommission'] + $nofreeze;
                              $mupdate = [
                                    'commission' => $commission,
                                    'totalcommission' => $totalcommission
                              ];
                             //减冻结
                             $weishu = $sysset['fenhong_money_weishu']??2;
                             $sy_freeze =$member['baodan_freeze']-$nofreeze;
                             $sy_freeze = dd_money_format( $sy_freeze,$weishu);
                             $mupdate['baodan_freeze'] = $sy_freeze;
                             Db::name('member')->where('id',$og['mid'])->update($mupdate);
                             //加记录
                             $baodan_data = [];
                             $baodan_data['aid'] = $aid;
                             $baodan_data['mid'] = $mid;
                             $baodan_data['commission'] = $nofreeze;
                             $baodan_data['after'] = $sy_freeze;
                             $baodan_data['createtime'] = time();
                             $baodan_data['remark'] = '报单产品佣金解冻，产品ID:'.$og['proid'];
                             Db::name('member_baodan_freeze_log')->insert($baodan_data);
                         }
                        
                    }
               }
           }
        }
        if(getcustom('product_bonus_pool')){
            \app\commons\Order::prodcutBonusPool($aid,$order,$oglist,$member);
        }
		if(getcustom('coupon_xianxia_buy')){
		    //购买一单升级,如果当前用户的等级 = 默认等级，进行升级
            $default_level = Db::name('member_level')->where('aid',$aid)->where('isdefault',1)->find();
            if($member['levelid'] ==$default_level['id']){
                $next_level = Db::name('member_level')->where('aid',$aid)->where('sort','>',$default_level['sort'])->order('sort asc')->find();
                if($next_level){
                    Db::name('member')->where('id',$member['id'])->update(['levelid' => $next_level['id']]);
                }
            }
        }
    	if(getcustom('path')){
            $pids = $member['path'];
    		if($pids && $order['baodan']>0) {//explode(',',str_replace('，',',',$lv['up_catid']));
    			$parentList = Db::name('member')->where('id','in',$pids)->order(Db::raw('field(id,'.$pids.')'))->select()->toArray();
    			if($parentList) {
                    $parentList = array_reverse($parentList);
                    $dataMoney= [];
                    $time = time();
                    $totals = Db::name('shop_order_goods')->where('orderid',$orderid)->where('commissionset',7)->field('sum(num) totalnum,sum(totalprice) totalmoney')->find();
                    foreach($parentList as $key=>$val){
            		     $dataMoney[] = [
                            'aid' => $aid,
                            'mid' => $val['id'],
                            'fromid' => $mid,
                            'totalnum' => $totals['totalnum'],
                            'orderid' => $order['id'],
                            'money' => $totals['totalmoney'],
                            'createtime' => $time,
                        ];
            		}
                    Db::name('member_team_money')->limit(100)->insertAll($dataMoney);
                }
            }
    	}	

        if(getcustom('douyin_groupbuy')){
            //抖音团购核销
            if($order['dydatas'] && $order['dypoi_id']){
                $dydatas = json_decode($order['dydatas'],true);
                foreach($dydatas as $dv){
                    $res = \app\customs\DouyinGroupbuyCustom::hexiao_dygroupbuy($aid,$order['bid'],$dv['verify_token'],$order['dypoi_id'],$dv['encrypted_codes']);
                    if(!$res || $res['status'] != 1){
                        //退款
                        if($order['totalprice']>0){
                            $res2 = \app\commons\Order::refund($order,$order['totalprice'],'核销抖音团购券失败'.$order['ordernum']);
                        }else{
                            $res2 = ['status'=>1,'msg'=>''];
                        }
                        if($res2 && $res2['status'] == 1){
                            //关闭订单
                            $up = Db::name('shop_order')->where('id',$order['id'])->update(['status'=>4]);
                            if($up){
                                //加库存
                                $oglist = Db::name('shop_order_goods')->where('orderid',$order['id'])->field('id,aid,ggid,num')->select()->toArray();
                                if($oglist){
                                    foreach($oglist as $og){
                                        Db::name('shop_guige')->where('aid',$aid)->where('id',$og['ggid'])->update(['stock'=>Db::raw("stock+".$og['num']),'sales'=>Db::raw("IF(sales>=".$og['num'].",sales-".$og['num'].",0)")]);
                                        Db::name('shop_product')->where('aid',$aid)->where('id',$og['proid'])->update(['stock'=>Db::raw("stock+".$og['num']),'sales'=>Db::raw("IF(sales>=".$og['num'].",sales-".$og['num'].",0)")]);
                                    }
                                    Db::name('shop_order_goods')->where('orderid',$order['id'])->update(['status'=>4]);
                                }
                                
                            }
                        }
                    }
                }
            }
        }
        if(getcustom('supply_zhenxin')){
            //甄新汇选下单
            if($order['issource'] && $order['source'] == 'supply_zhenxin'){

                $params  = [
                    'user_name'=>$order['linkman'],
                    'user_mobile'=>$order['tel'],
                    'shipAreaCode'=>$order['shipAreaCode'],
                    'user_address'=>$order['area'].$order['address'],
                    'third_sn'=>$order['ordernum'],
                    'third_sn'=>$order['ordernum'],
                    'remark'=>$order['message']??''
                ];
                if($order['usercard']){
                    $params['user_realname'] = $order['linkman'];
                    $params['user_idcard']   = $order['usercard'];
                }
                $sku_list= [];
                foreach($oglist as $ov){
                    $sku_list[] = ['sku_id'=>$ov['skuid'],'sku_num'=>$ov['num']];
                }
                unset($ov);
                $submit =  \app\customs\SupplyZhenxinCustom::submit($aid,$order['bid'],$params,$sku_list);
                if(!$submit || $submit['status'] != 1){
                    //退款
                    if($order['totalprice']>0){
                        $res2 = \app\commons\Order::refund($order,$order['totalprice'],'商品下单失败'.$order['ordernum']);
                    }else{
                        $res2 = ['status'=>1,'msg'=>''];
                    }
                    if($res2 && $res2['status'] == 1){
                        //关闭订单
                        $up = Db::name('shop_order')->where('id',$order['id'])->update(['status'=>4]);
                        if($up){
                            //加库存
                            $oglist = Db::name('shop_order_goods')->where('orderid',$order['id'])->field('id,aid,ggid,num')->select()->toArray();
                            if($oglist){
                                foreach($oglist as $og){
                                    Db::name('shop_guige')->where('aid',$aid)->where('id',$og['ggid'])->update(['stock'=>Db::raw("stock+".$og['num']),'sales'=>Db::raw("IF(sales>=".$og['num'].",sales-".$og['num'].",0)")]);
                                    Db::name('shop_product')->where('aid',$aid)->where('id',$og['proid'])->update(['stock'=>Db::raw("stock+".$og['num']),'sales'=>Db::raw("IF(sales>=".$og['num'].",sales-".$og['num'].",0)")]);
                                }
                                Db::name('shop_order_goods')->where('orderid',$order['id'])->update(['status'=>4]);
                            }
                        }
                    }
                }else{
                    $resdata = $submit['data'];
                    $updata = [];
                    $updata['sordernum'] = $resdata[0]['order_sn'];
                    $updata['sresdata']  = json_encode($resdata);
                    $uporder = Db::name('shop_order')->where('id',$order['id'])->update($updata);
                }
            }
        }
		if($order['fromwxvideo'] == 1){
			if($order['paytypeid'] !=60){ //不是视频号微信支付的
				Db::name('shop_order')->where('id',$orderid)->update(['fromwxvideo'=>0]);
				$order['fromwxvideo'] = 0;
				//\app\commons\Wxvideo::createorder($orderid);
			}else{
				\app\commons\Wxvideo::orderpay($orderid);
			}
		}

        //同城配送
        if($order['freight_type'] == 2){
            if(getcustom('express_maiyatian_autopush')) {
                //麦芽田同城配送自动推送
                \app\customs\MaiYaTianCustom::auto_push($aid,$orderid,$order,'shop_order');
            }
        }
		//自动发货
		if($order['freight_type']==3){
			$og = Db::name('shop_order_goods')->where('orderid',$order['id'])->find();
			$freight_content = Db::name('shop_product')->where('id',$og['proid'])->value('freightcontent');
			Db::name('shop_order')->where('id',$order['id'])->update(['freight_content'=>$freight_content,'status'=>2,'send_time'=>time()]);
			Db::name('shop_order_goods')->where('orderid',$order['id'])->update(['status'=>2]);

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
		}
		//在线卡密
		if($order['freight_type']==4){
			$og = Db::name('shop_order_goods')->where('orderid',$order['id'])->find();
			$codelist = Db::name('shop_codelist')->where('proid',$og['proid'])->where('status',0)->order('id')->limit($og['num'])->select()->toArray();
			if($codelist && count($codelist) >= $og['num']){
				$pscontent = [];
				foreach($codelist as $codeinfo){
					$pscontent[] = $codeinfo['content'];
					Db::name('shop_codelist')->where('id',$codeinfo['id'])->update(['orderid'=>$order['id'],'ordernum'=>$order['ordernum'],'headimg'=>$member['headimg'],'nickname'=>$member['nickname'],'buytime'=>time(),'status'=>1]);
				}
				$pscontent = implode("\r\n",$pscontent);
				Db::name('shop_order')->where('id',$order['id'])->update(['freight_content'=>$pscontent,'status'=>2,'send_time'=>time()]);
				Db::name('shop_order_goods')->where('orderid',$order['id'])->update(['status'=>2]);
                $express_com = '卡密订单';
                $express_no = $order['ordernum'];
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
                $tmplcontent['thing11'] = $order['address']?:"卡密订单";

                $tmplcontentnew = [];
                $tmplcontentnew['thing29'] = $order['title'];
                $tmplcontentnew['thing1'] = $express_com;
                $tmplcontentnew['character_string2'] = $express_no;
                $tmplcontentnew['thing9'] = $order['address']?:"卡密订单";
                \app\commons\Wechat::sendwxtmpl(aid,$order['mid'],'tmpl_orderfahuo',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);

                //短信通知
                $member = Db::name('member')->where('id',$order['mid'])->find();
                if($member['tel']){
                    $tel = $member['tel'];
                }else{
                    $tel = $order['tel'];
                }
                $rs = \app\commons\Sms::send(aid,$tel,'tmpl_orderfahuo',['ordernum'=>$order['ordernum'],'express_com'=>$express_com,'express_no'=>$express_no]);
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
		}
		if(getcustom('huawa') && $aid==1){ //定制1 订单对接 同步到花娃
			\app\customs\Huawa::api($order);
		}
		if(getcustom('cefang') && $aid==2){ //定制1 订单对接 同步到策方
			\app\customs\Cefang::api($order);
		}
        if(getcustom('h5zb') && $order['roomid']>0){
            Db::name('h5zb_room_record')->insert([
                'aid'=>$order['aid'],
                'bid'=>$order['bid'],
                'createtime'=>time(),
                'remark'=>'直播间下单',
                'nickname'=>$member['nickname'],
                'headimg'=>$member['headimg'],
                'roomid'=>$order['roomid'],
                'eventid'=>0,
                'eventdata'=>'',
                'type'=>'buy'
            ]);
        }

		if(getcustom('hmy_yuyue')){  // 红蚂蚁定制同步到跑腿订单
			\app\customs\Yuyue::api($order);
		}
        //加权分红份数记录
        if(getcustom('fenhong_jiaquan_bylevel')){
            //需要发放加权分红份数的会员【直推和自购】
            $jqMemberArr = [];
            //自购
            if($member['levelid']){
                $memberLevel = Db::name('member_level')->where('aid',$order['aid'])->where('id',$member['levelid'])->field('id,fenhong_zt_copies, 1 isZg')->find();
                $memberLevel['mid'] = $member['id'];
                $jqMemberArr[] = $memberLevel;
            }
            //直推
            if($member['pid']){
                $memberParent = Db::name('member')->where('aid',$aid)->where('id',$member['pid'])->find();
                if($memberParent && $memberParent['levelid']) {
                    $memberParentLevel = Db::name('member_level')->where('aid', $aid)->where('id', $memberParent['levelid'])->field('id,fenhong_zt_copies, 0 isZg')->find();
                    $memberParentLevel['mid'] = $memberParent['id'];
                    $jqMemberArr[] = $memberParentLevel;
                }
            }
            $allJiaQuan = [];
            foreach ($jqMemberArr as $mk => $jqMember) {
                if(empty($jqMember['fenhong_zt_copies'])){
                    continue;
                }
                //加权分红记录
                foreach ($oglist as $k => $goods) {
                    if ($goods['fenhong_jq_status'] != 1) {
                        continue;
                    }
                    $allJiaQuan[] = [
                        'aid' => $goods['aid'],
                        'bid' => $goods['bid'],
                        'orderid' => $goods['orderid'],
                        'ogid' => $goods['id'],
                        'mid' => $jqMember['mid'],
                        'frommid' => $goods['mid'],
                        'type' => 'shop',
                        'remark' => $jqMember['isZg']?'购物加权分红奖励':'直推购物加权分红奖励',
                        'createtime' => time(),
                        'status' => 0,//确认收货后算有效份数
                        'copies' => $jqMember['fenhong_zt_copies'],
                    ];
                }
            }
            if($allJiaQuan){
                Db::name('member_fenhong_jiaquan')->insertAll($allJiaQuan);
            }
        }

		//支付后送券
		$couponlist = \app\commons\Coupon::getpaygive($aid,$mid,'shop',$order['totalprice'],$order['id']);
		if($couponlist){
            $proids = db('shop_order_goods')->where('orderid','=',$order['id'])->column('proid');
			foreach($couponlist as $coupon){
				if($coupon['paygive']==1 && $coupon['paygive_minprice'] <= $order['totalprice'] && $coupon['paygive_maxprice'] >= $order['totalprice'] && in_array('shop',explode(',',$coupon['paygive_scene']))){
					\app\commons\Coupon::send($aid,$mid,$coupon['id']);
				}
				if($coupon['buyprogive'] == 1){
					$coupon['buyproids'] = explode(',',$coupon['buyproids']);
					$coupon['buypro_give_num'] = explode(',',$coupon['buypro_give_num']);
					foreach($coupon['buyproids'] as $k => $proid) {
						if(in_array($proid, $proids) && $coupon['buypro_give_num'][$k] > 0) {
							for($i=0;$i<$coupon['buypro_give_num'][$k];$i++) {
								\app\commons\Coupon::send($aid,$mid,$coupon['id']);
							}
						}
					}
				}
			}
		}
		//送积分
		if($order['givescore2'] > 0){
			if($order['bid'] > 0){
				\app\commons\Business::addmemberscore($aid,$order['bid'],$order['mid'],$order['givescore2'],'购买商品赠送'.t('积分'));
			}else{
				\app\commons\Member::addscore($aid,$order['mid'],$order['givescore2'],'购买商品赠送'.t('积分'));
			}
		}
		foreach($oglist as $k=>$og){
		    if ($og['commissionset']==7) {
		         Db::name('member')->where('aid',$aid)->where('id',$mid)->inc('buymoney',$og['totalprice'])->update();
		    }
		}
		if(getcustom('240308.fqywl.com') && $member['lv_time'] > 0){	
    		if($member['levelid']== $set['give_lvid'] && $order['totalprice'] >= $set['give_min']) {
               //奖励余额
               $give_credit1 = 0;
               $give_credit2 = $set['give_credit2'];
                if($set['give_credit1'] > 0) {
                    $give_credit1 =  round($order['totalprice']*$set['give_credit1'],2);
                    \app\commons\Member::addcredit1($aid,$mid,$give_credit1,'等级复购奖励',2);
                }
                   //奖励余额
                if($give_credit2 > 0) {
                    \app\commons\Member::addcredit2($aid,$mid,$give_credit2,'等级复购奖励',2);
                }
                $record_data = [];
                $record_data['aid'] = $aid;
                $record_data['mid'] = $mid;
                $record_data['totalprice'] = $order['totalprice'];
                $record_data['credit1'] = $give_credit1;
                $record_data['credit2'] = $give_credit2;
                $record_data['credit2_after'] = $give_credit2;
                $record_data['createtime'] = time();
                Db::name('member_credit_record')->insertGetId($record_data);
            }elseif ($member['levelid'] != $set['give_lvid'] && $order['totalprice'] >= $set['give_min']) {
                $leveldata = Db::name('member_level')->where('aid',$aid)->where('id',$member['levelid'])->find();
                if($leveldata['maxrate'] > 0) {
                    $give_credit1 =  round($order['totalprice']*$leveldata['maxrate'],2);
                    \app\commons\Member::addcredit1($aid,$mid,$give_credit1,'等级复购奖励',2);
                }
            }  
		 }
        
        
        
        
        $pids = $member['path'];
		if($pids) {
			$parentList = Db::name('member')->where('id','in',$pids)->order(Db::raw('field(id,'.$pids.')'))->select()->toArray();
			if($parentList) {
				$parentList = array_reverse($parentList);
                if($order['totalprice']>0) {
    				foreach($parentList2 as $k=>$parent) {
					    $rs = Db::name('member_yeji_record')->where('aid',$aid)->where('mid',$parent['id'])->where('createtime','>=',strtotime(date('Y-m-01 00:00:00',time())))->find();
            			if($rs){
            			    Db::name('member_yeji_record')->where('aid',$aid)->where('id',$rs['id'])->inc('totalprice',$order['totalprice'])->update();
            			}else{
            				Db::name('member_yeji_record')->insert(['aid'=>$aid,'mid'=>$parent['id'],'totalprice'=>$order['totalprice'],'createtime'=>time()]);
            			}
    				}
    			}
			}
		}
        
       
//         $pids = $member['path'];
// 		if($pids && $order['totalprice']>0 ) {//explode(',',str_replace('，',',',$lv['up_catid']));
// 			$parentList = Db::name('member')->where('id','in',$pids)->order(Db::raw('field(id,'.$pids.')'))->select()->toArray();
// 			if($parentList) {
//                 $parentList = array_reverse($parentList);
//                 $dataMoney= [];
//                 $time = time();
//                 foreach($parentList as $key=>$val){
//         		     $dataMoney[] = [
//                         'aid' => $aid,
//                         'mid' => $val['id'],
//                         'level' => $key+1,
//                         'totalprice' => $order['totalprice'],
//                         'ordernum' => $order['ordernum'],
//                         'createtime' => $time,
//                     ];
//         		}
//                 Db::name('member_yeji_record')->limit(100)->insertAll($dataMoney);
//             }
//         }
       
        
        
        
        
        
        if(getcustom('credit1_money')){
			$credit1_money = \app\commons\Aaa::Credit1Money($aid);
            if($order['tongji_1'] > 0  && $credit1_money > 0){
                $credit1 = round($order['tongji_1']/$credit1_money,4);
                \app\commons\Member::addcredit1($aid,$order['mid'],$credit1,'购买产品赠送'.t('credit1'),1,$credit1_money);
            }
		}
        if($order['tongji_1'] > 0 ){
            //\app\commons\Member::addcredit1($aid,$order['mid'],$order['tongji_1'],$order['ordernum'],1);
        }
        if($order['tongji_2'] > 0 ){
            \app\commons\Admin::addcredit1($aid,$order['mid'],$order['tongji_2'],$order['ordernum']);
        }
        
        
        if($order['givecredit2'] > 0 ){
            \app\commons\Member::addcredit2($aid,$order['mid'],$order['givecredit2'],'购买产品赠送'.t('credit2'));
        }
		
		if(getcustom('2+1')){
		   \app\commons\Aaa::Create($aid,$mid,$order,$oglist);
		}
		
		if(getcustom('product_payaftergive')){
		   \app\commons\Aaa::Create($aid,$mid,$order,$oglist);
		}
		
		if(getcustom('member_commission_max',$aid)){
            //送佣金上限
            if($order['give_commission_max2'] > 0){
                \app\commons\Member::addcommissionmax($aid,$order['mid'],$order['give_commission_max2'],'购买商品赠送'.t('佣金上限'),'shop',$orderid);
            }
        }

		if(getcustom('product_payaftergive')){
			foreach($oglist as $og){
				$product = Db::name('shop_product')->where('id',$og['proid'])->find();
				if($product['paygive_choujiangtimes'] > 0 && $product['paygive_choujiangid'] > 0){
					$sharelog = Db::name('choujiang_sharelog')->where('aid',$aid)->where('hid',$product['paygive_choujiangid'])->where('mid',$mid)->find();
					if($sharelog){
						Db::name('choujiang_sharelog')->where('id',$sharelog['id'])->inc('extratimes',$product['paygive_choujiangtimes'])->update();
					}else{
						$data = [];
						$data['aid'] = $aid;
						$data['hid'] = $product['paygive_choujiangid'];
						$data['mid'] = $mid;
						$data['extratimes'] = $product['paygive_choujiangtimes'];
						Db::name('choujiang_sharelog')->insert($data);
					}
				}
				if($product['paygive_money'] > 0){
					\app\commons\Member::addmoney($aid,$mid,$product['paygive_money'],'购买商品赠送');
				}
				if($product['paygive_score'] > 0){
					\app\commons\Member::addscore($aid,$mid,$product['paygive_score'],'购买商品赠送');
				}
				if($product['paygive_couponid'] > 0){
					\app\commons\Coupon::send($aid,$mid,$product['paygive_couponid']);
				}
			}
		}

		if(getcustom('jushuitan')){
			//创建聚水潭订单
			$rs = \app\customs\jushuitan::create($order,'WAIT_SELLER_SEND_GOODS');
		}
        if(getcustom('consumer_value_add')){
            //送绿色积分
            if($order['give_green_score2'] > 0){
                \app\commons\Member::addgreenscore($aid,$order['mid'],$order['give_green_score2'],'购买商品赠送'.t('绿色积分'),'shop_order',$orderid,0,$order['give_maximum'],0);
            }
            //放入奖金池
            if($order['give_bonus_pool2'] > 0){
                \app\commons\Member::addbonuspool($aid,$order['mid'],$order['give_bonus_pool2'],'购买商品赠送'.t('奖金池'),'shop_order',$orderid,0,$order['give_green_score2']);
            }
        }
        if(getcustom('active_coin',$aid)){
            //送激活币
            \app\commons\Order::giveActiveCoin($aid,$order);

        }
        if(getcustom('member_commission_max',$aid) && getcustom('add_commission_max',$aid)){
            //送佣金上限
            \app\commons\Order::giveCommissionMax($aid,$order);
        }

        if(getcustom('active_score')){
            //送积分
            \app\commons\Order::giveActiveScore($aid,$order);
        }

		if($order['platform'] == 'toutiao'){
			\app\commons\Ttpay::pushorder($aid,$order['ordernum'],1);
		}
		
		if(getcustom('shopshd_shuixitie')){
			$shd_style1_no = Db::name('shop_sysset')->where('aid',$aid)->value('shd_style1_no');
			$shd_style1_no = intval($shd_style1_no);
			$shd_style1_no = $shd_style1_no + 1;
			foreach($oglist as $k=>$og){
				//if(count($oglist) > 1){
				//	$shd_style1_no_this = $shd_style1_no.'-'.($k+1 > 9 ? '' : '0') . ($k+1);
				//}else{
					$shd_style1_no_this = $shd_style1_no;
				//}
				Db::name('shop_order_goods')->where('id',$og['id'])->update(['shd_style1_no'=>$shd_style1_no_this]);
			}
			Db::name('shop_sysset')->where('aid',$aid)->update(['shd_style1_no'=>$shd_style1_no]);
		}
	 if(getcustom('product_supply_chain')){
            if($order['product_type']==7 && $order['supplier_status']==0){
                //同步到供货平台：海带网
                \app\customs\Chain::orderPay(aid,$order['id']);
            }
        }
if(getcustom('ciruikang_fenxiao')){
           //会员当前等级及应该赠送的数量
           \app\customs\CiruikangCustom::deal_givenum($order);
            //一次购买升级
            \app\commons\Member::uplv($aid,$mid,'shop',['onebuy'=>1,'onebuy_orderid'=>$order['id']]);
        }else{
            \app\commons\Member::uplv($aid,$mid);
        }

		//送积分
		if($order['baodan'] > 0){
		//	
		}
		if(getcustom('teamlevel')){ 
			\app\plugin\TeamLevel::get_team($aid,$mid);
		}
	
		if(getcustom('storelevel')){ 
			\app\plugin\TeamLevel::get_store($aid,$mid);
		}
	  if(getcustom('member_shougou_parentreward')){
            $changelock = true;
            if(getcustom('ciruikang_fenxiao')){
                //查询当前用户，进行首购解锁
                $oldlevel = Db::name('member_level')->where('id',$member['levelid'])->field('id,up_pro_orderstatus2')->find();
                //如果统计的是确认收货后，则进行不解锁
                if($oldlevel && $oldlevel['up_pro_orderstatus2']){
                    $changelock = false;
                }
            }
            if($changelock){
                Db::name('member_commission_record')->where('orderid',$order['id'])->where('type','shop')->where('status',0)->where('islock',1)->where('aid',$order['aid'])->where('remark','like','%首购奖励')->update(['islock'=>0]);
            }
        }
        
        $shopOrderType = 1;//商城订单
        if(getcustom('product_weight') && $order['product_type']==2){
            $shopOrderType = 0;
            //称重商品不打印
//            \app\commons\Wifiprint::print($aid,'shop',$order['id'],1,0);
        }
        if($shopOrderType==1){
            \app\commons\Wifiprint::print($aid,'shop',$order['id']);
        }
        if(getcustom('erp_wangdiantong')){
            $c = new \app\customs\Wdt($aid,$order['bid']);
            $c->orderCreate($order['id']);
        }

		//公众号通知 订单支付成功
		$tmplcontent = [];
        if($order['paytypeid'] != 4) {
            $tmplcontent['first'] = '有新订单支付成功';
        } else {
            $tmplcontent['first'] = '有新订单下单成功（'.$order['paytype'].'）';
        }
		$tmplcontent['remark'] = '点击进入查看~';
		$tmplcontent['keyword1'] = $member['nickname']; //用户名
		$tmplcontent['keyword2'] = $order['ordernum'];//订单号
		$tmplcontent['keyword3'] = $order['totalprice'].'元';//订单金额
		$tmplcontent['keyword4'] = $order['title'];//商品信息
        $tmplcontentNew = [];
        $tmplcontentNew['thing8'] = \app\commons\Mendian::getNameWithBusines($order);//门店
        $tmplcontentNew['phrase18'] = $member['nickname']; //用户名
        $tmplcontentNew['character_string2'] = $order['ordernum'];//订单号
        $tmplcontentNew['amount5'] = $order['totalprice']==0?'0.00':$order['totalprice'];//订单金额
        $tmplcontentNew['thing3'] = $order['title'];//商品信息
		\app\commons\Wechat::sendhttmpl($aid,$order['bid'],'tmpl_orderpay',$tmplcontent,m_url('admin/order/shoporder', $aid),$order['mdid'],$tmplcontentNew);


        if($order['paytypeid'] != 4) {
            $tmplcontent['first'] = '恭喜您的订单已支付成功';
        } else {
            $tmplcontent['first'] = '恭喜您的订单已下单成功';
        }
		$rs = \app\commons\Wechat::sendtmpl($aid,$mid,'tmpl_orderpay',$tmplcontent,m_url('pages/my/usercenter', $aid),$tmplcontentNew);

		$tmplcontent = [];
		$tmplcontent['thing11'] = $order['title'];
		$tmplcontent['character_string2'] = $order['ordernum'];
		if($order['paytypeid'] != 4) {
			$tmplcontent['phrase10'] = '已支付';
		}else{
			$tmplcontent['phrase10'] = $order['paytype'];
		}
		$tmplcontent['amount13'] = $order['totalprice'].'元';
		$tmplcontent['thing27'] = $member['nickname'];
		\app\commons\Wechat::sendhtwxtmpl($aid,$order['bid'],'tmpl_orderpay',$tmplcontent,'admin/order/shoporder',$order['mdid']);

		//短信通知
		$rs = \app\commons\Sms::send($aid,$member['tel']?$member['tel']:$order['tel'],'tmpl_orderpay',['ordernum'=>$order['ordernum']]);

	
		if($set['fxjiesuantime'] == 1 && $set['fxjiesuantime_delaydays'] == '0'){
			\app\commons\Order::giveCommission($order,'shop');
		}

		if(getcustom('forcerebuy') && $member['commission_isfreeze'] == 1){
			$forcerebuy = Db::name('forcerebuy')->where('aid',$aid)->where('wfgtype',0)->where('status',1)->where("find_in_set('-1',gettj) or find_in_set('".$member['levelid']."',gettj)")->find();
			if($forcerebuy){
				$orderwhere = [];
				$orderwhere[] = ['aid','=',$aid];
				$orderwhere[] = ['mid','=',$mid];
				$orderwhere[] = ['status','in','1,2,3'];
				if($forcerebuy['type'] == 0){
					$orderwhere[] = ['isfg','=',1];
				}else{
					if($forcerebuy['daytype'] == 0){
						$starttime = strtotime(date('Y-m-01'));
						$endtime = time();
					}elseif($forcerebuy['daytype'] == 1){
						if(date('m') == '01' || date('m') == '02' || date('m') == '03'){
							$starttime = strtotime(date('Y-01-01'));
						}
						if(date('m') == '04' || date('m') == '05' || date('m') == '06'){
							$starttime = strtotime(date('Y-04-01'));
						}
						if(date('m') == '07' || date('m') == '08' || date('m') == '09'){
							$starttime = strtotime(date('Y-07-01'));
						}
						if(date('m') == '10' || date('m') == '11' || date('m') == '12'){
							$starttime = strtotime(date('Y-10-01'));
						}
						$endtime = time();
					}elseif($forcerebuy['daytype'] == 2){
						$starttime = strtotime(date('Y-01-01'));
						$endtime = time();
					}else{
						$starttime = 0;
						$endtime = time();
					}
					$orderwhere[] = ['createtime','>=',$starttime];
				}
				if($forcerebuy['fwtype'] == 1){
					$orderwhere[] = ['cid','in',$forcerebuy['categoryids']];
				}elseif($forcerebuy['fwtype'] == 2){
					$orderwhere[] = ['proid','in',$forcerebuy['productids']];
				}
				$totalprice = Db::name('shop_order_goods')->where($orderwhere)->sum('totalprice');
				if($totalprice >= $forcerebuy['price']){
					Db::name('member')->where('id', $member['id'])->update(['commission_isfreeze' => 0]);
				}
			}
		}

		 // 自动派单到大厅
        $canpaidan = true;
        if(getcustom('supply_zhenxin')){
            //甄新汇选下单
            if($order['issource'] && $order['source'] == 'supply_zhenxin'){
                $canpaidan = false;
            }
        }
        if(getcustom('express_paidan')){
            if($canpaidan){
                $set = Db::name('peisong_set')->where('aid',$aid)->find();
                if($set['paidantype'] == 0){
                    if($set['express_paidan'] == 1){
                        $rs = \app\models\PeisongOrder::create('shop_order',$order,$psid=0,$other=[]);
                    }
                }
            }
        }
        if(getcustom('yx_invite_cashback')){
            //邀请返现
            if($order && $oglist && $member){
                \app\customs\OrderCustom::deal_invitecashback($aid,$order,$oglist,$member);
            }
        }

        if(getcustom('yx_queue_free')){
            \app\customs\QueueFree::join($order,'shop');
        }

		if(getcustom('mendian_upgrade')){
			$admin = Db::name('admin')->where('id',$aid)->field('mendian_upgrade_status')->find();
			if($admin['mendian_upgrade_status']==1 && $order['mdid']>0){
				 \app\customs\Mendian::createCommission($order);
				 $mendian_sysset = Db::name('mendian_sysset')->where('aid',$aid)->find();
				//发送消息通知
				if($mendian_sysset['notice_status']==1){
					$mendian =  Db::name('mendian')->field('mid')->where('id',$order['mdid'])->find();
					$member =  Db::name('mendian')->where('id',$mendian['mid'])->find();
					if($member['wxopenid']){
					   	$tmplcontent = [];
						$tmplcontent['thing11']  = $order['title']?$order['title']:'';//商品
						$tmplcontent['character_string2'] = $order['ordernum']?$order['ordernum']:'';
						$tmplcontent['phrase10'] = '新订单下单成功';
						$tmplcontent['amount13'] =  $order['totalprice']?$order['totalprice']:'';//金额
						$tmplcontent['thing27']  = '';
						\app\commons\Wechat::sendwxtmpl($aid,$member['id'],'tmpl_orderconfirm',$tmplcontent,m_url('pagesA/mendiancenter/orderlist'),$order['mdid']);
					}
					if($member['mpopenid']){
						$rs = \app\commons\Wechat::sendtmpl($aid,$member['id'],'tmpl_orderpay',$tmplcontent,m_url('pagesA/mendiancenter/orderlist', $aid),$tmplcontentNew);
					}
				}
				
			}
		}

		if(getcustom('business_sales_quota')){
			if($order['bid']>0){
				$business = Db::name('business')->where(['aid'=>$aid,'id'=>$order['bid']])->field('kctime,kctype')->find();
				if($business['kctime']==0){
					$remark = '订单号：'.$order['ordernum'];
					$sales_price = $order['product_price'];
					if($business['kctype']==1){
						$sales_price = $order['totalprice'];
					}
					\app\commons\Business::addsalesquota($aid,$order['bid'],$sales_price,$remark,$order['id']);
				}
			}
		}
        if(getcustom('zhongkang_sync')){
            $zksysset = Db::name('admin_set')->where('aid',$aid)->find();
            foreach($oglist  as $key=>$val){
                $shop_product =  Db::name('shop_product')->where('aid',$aid)->where('id',$val['proid'])->field('zhongkang_appid,zhongkang_levelid')->find();
                 if($shop_product['zhongkang_appid'] && $shop_product['zhongkang_levelid'] && $zksysset['zhongkang_secret'] && $member['tel']){
                     \app\customs\Zhongkang::createMember($shop_product['zhongkang_appid'],$member['tel'],$shop_product['zhongkang_levelid'],$zksysset['zhongkang_secret']);
                 }
            }
        }

        
        if(getcustom('yx_mangfan')) {
			//支付成功计算盲返列表
            $order_goods = Db::name("shop_order_goods")
                ->where("orderid", $orderid)
                ->where("is_mangfan", 1)
                ->field("id ogid, aid, mid, num, real_totalprice, totalprice, is_mangfan, mangfan_rate, mangfan_commission_type")
                ->select();
            if($order_goods){
                \app\customs\Mangfan::createRecord($aid, $order['mid'], $order['id'], $order['ordernum'], $order['paytime'], $order_goods);
            }
        }

        if(getcustom('product_pickup_device')){
            \app\commons\Order::pickupDeviceGoodsPayafter($order);
        }


        \app\commons\Aaa::submitOrder($aid,$order);





	}

	public static function scoreshop_hb_pay($orderid,$ordernum){
		$orderlist = Db::name('scoreshop_order')->where('ordernum','like',$ordernum.'%')->select()->toArray();
		foreach($orderlist as $order){
			self::scoreshop_pay($order['id']);
		}
	}
	//积分商城订单
	public static function scoreshop_pay($orderid){
		$order = Db::name('scoreshop_order')->where('id',$orderid)->find();
		$member = Db::name('member')->where('id',$order['mid'])->find();
		$aid = $order['aid'];
		$mid = $order['mid'];

        Db::name('scoreshop_order_goods')->where('orderid',$order['id'])->update(['status'=>1]);
        //同城配送
        if($order['freight_type'] == 2){
            if(getcustom('express_maiyatian_autopush')) {
                //麦芽田同城配送自动推送
                \app\customs\MaiYaTianCustom::auto_push($aid,$orderid,$order,'scoreshop_order');
            }
        }
		//自动发货
		if($order['freight_type']==3){
			$og = Db::name('scoreshop_order_goods')->where('orderid',$order['id'])->find();
			$freight_content = Db::name('scoreshop_product')->where('id',$og['proid'])->value('freightcontent');
			Db::name('scoreshop_order')->where('id',$order['id'])->update(['freight_content'=>$freight_content,'status'=>2,'send_time'=>time()]);
			Db::name('scoreshop_order_goods')->where('orderid',$order['id'])->update(['status'=>2]);
			if(getcustom('scoreshop_wx_hongbao')){
			    //如果是兑换红包
                if($og['type'] ==1){
                    $hb_scoreshop_product = Db::name('scoreshop_product')->where('id',$og['proid'])->field('hongbao_money,scene_id')->find();

                    if($hb_scoreshop_product['hongbao_money'] > 0){
                        $money =  dd_money_format($hb_scoreshop_product['hongbao_money'],2);
                        $rs = \app\commons\Wxpay::sendredpackage($order['aid'],$order['mid'],$order['platform'],$money,mb_substr($order['title'],0,10),'微信红包','恭喜发财','微信红包',$hb_scoreshop_product['scene_id']);
                        if($rs['status']==0){ //发放失败
                            Db::name('scoreshop_order')->where('id',$order['id'])->update(['send_remark'=>$rs['msg']]);
                        }else{
                            //修改订单状态
                            Db::name('scoreshop_order')->where('id',$order['id'])->update(['status'=>3,'send_time'=>time(),'send_remark'=>'红包发放成功']);
                            Db::name('scoreshop_order_goods')->where('orderid',$order['id'])->update(['status'=>3]);
                        }
                    }
                }
            }
            if(getcustom('scoreshop_to_money')){
                if($order['type'] ==2 && $order['bid'] == 0){
                    $give_money = Db::name('scoreshop_product')->where('id',$og['proid'])->value('give_money');
                     if($give_money > 0){
                         $give_money =  dd_money_format($give_money,2);
                         //增加余额
                         $rs = \app\commons\Member::addmoney($order['aid'],$mid,$give_money,'积分兑换,订单号:'.$order['ordernum']);
                         if($rs['status'] ==1){
                             //修改订单状态
                             Db::name('scoreshop_order')->where('id',$order['id'])->update(['status'=>3,'send_time'=>time(),'send_remark'=>'余额发放成功']);
                             Db::name('scoreshop_order_goods')->where('orderid',$order['id'])->update(['status'=>3]);
                         }else{
                             Db::name('scoreshop_order')->where('id',$order['id'])->update(['send_remark'=>'余额发放失败']);
                         }

                     }
                }
            }
            //发货信息录入 微信小程序+微信支付
            if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
                \app\commons\Order::wxShipping($order['aid'],$order,'scoreshop');
            }
		}
		//在线卡密
		if($order['freight_type']==4){
			$og = Db::name('scoreshop_order_goods')->where('orderid',$order['id'])->find();
			$codelist = Db::name('scoreshop_codelist')->where('proid',$og['proid'])->where('status',0)->order('id')->limit($og['num'])->select()->toArray();
			if($codelist && count($codelist) >= $og['num']){
				$pscontent = [];
				foreach($codelist as $codeinfo){
					$pscontent[] = $codeinfo['content'];
					Db::name('scoreshop_codelist')->where('id',$codeinfo['id'])->update(['orderid'=>$order['id'],'ordernum'=>$order['ordernum'],'headimg'=>$member['headimg'],'nickname'=>$member['nickname'],'buytime'=>time(),'status'=>1]);
				}
				$pscontent = implode("\r\n",$pscontent);
				Db::name('scoreshop_order')->where('id',$order['id'])->update(['freight_content'=>$pscontent,'status'=>2,'send_time'=>time()]);
				Db::name('scoreshop_order_goods')->where('orderid',$order['id'])->update(['status'=>2]);
			}

            //发货信息录入 微信小程序+微信支付
            if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
                \app\commons\Order::wxShipping($order['aid'],$order,'scoreshop');
            }
		}
		//支付后送券
		$couponlist = \app\commons\Coupon::getpaygive($aid,$mid,'scoreshop',$order['totalprice']);
		if($couponlist){
			foreach($couponlist as $coupon){
				\app\commons\Coupon::send($aid,$mid,$coupon['id']);
			}
		}
		\app\commons\Wifiprint::print($aid,'scoreshop',$order['id']);
		//公众号通知 订单支付成功
		$tmplcontent = [];
		$tmplcontent['first'] = '有新'.t('积分').'商城订单支付成功';
		$tmplcontent['remark'] = '点击进入查看~';
		$tmplcontent['keyword1'] = $member['nickname']; //用户名
		$tmplcontent['keyword2'] = $order['ordernum'];//订单号
		$tmplcontent['keyword3'] = $order['totalscore'].t('积分').($order['totalprice']>0?' + '.$order['totalprice'].'元':'');//订单金额
		$tmplcontent['keyword4'] = $order['title'];//商品信息
        $tmplcontentNew = [];
        $tmplcontentNew['thing8'] = \app\commons\Mendian::getNameWithBusines($order);//门店
        $tmplcontentNew['phrase18'] = $member['nickname']; //用户名
        $tmplcontentNew['character_string2'] = $order['ordernum'];//订单号
        $tmplcontentNew['amount5'] = $order['totalscore'].t('积分').($order['totalprice']>0?' + '.$order['totalprice'].'元':'');//订单金额
        $tmplcontentNew['thing3'] = $order['title'];//商品信息
		\app\commons\Wechat::sendhttmpl($aid,0,'tmpl_orderpay',$tmplcontent,m_url('admin/order/scoreshoporder', $aid),$order['mdid'],$tmplcontentNew);

		$tmplcontent['first'] = '恭喜您的订单已支付成功';
		$rs = \app\commons\Wechat::sendtmpl($aid,$mid,'tmpl_orderpay',$tmplcontent,m_url('activity/scoreshop/orderlist', $aid),$tmplcontentNew);

		$tmplcontent = [];
		$tmplcontent['thing11'] = $order['title'];
		$tmplcontent['character_string2'] = $order['ordernum'];
		$tmplcontent['phrase10'] = '已支付';
		$tmplcontent['amount13'] = $order['totalprice'].'元';
		$tmplcontent['thing27'] = $member['nickname'];
		\app\commons\Wechat::sendhtwxtmpl($aid,0,'tmpl_orderpay',$tmplcontent,'admin/order/scoreshoporder',$order['mdid']);
		//短信通知
		$rs = \app\commons\Sms::send($aid,$member['tel']?$member['tel']:$order['tel'],'tmpl_orderpay',['ordernum'=>$order['ordernum']]);

		$set = Db::name('admin_set')->where('aid',$aid)->find();
		if($set['fxjiesuantime'] == 1 && $set['fxjiesuantime_delaydays'] == '0'){
			\app\commons\Order::giveCommission($order,'scoreshop');
		}
	}
	//拼团订单
	public static function collage_pay($orderid){
		$order = Db::name('collage_order')->where('id',$orderid)->find();
		$member = Db::name('member')->where('id',$order['mid'])->find();
		$aid = $order['aid'];
		$mid = $order['mid'];

        if(getcustom('sound')){
            \app\commons\Sound::play(aid,'collage',$order);
        }

		if($order['buytype']!=1){
			$team = Db::name('collage_order_team')->where('id',$order['teamid'])->find();
			$tdata = [];

            $addnum = true;//是否增加参团数量
            if(getcustom('yx_collage_teambuy_type')){
                //是开启团长拼团模式一 不占参团人数
                if($order['buytype'] == 2 && $order['teambuy_type'] == 1){
                    $addnum = false;
                }
            }
            if($addnum){
                $tdata['num'] = $team['num'] + 1;
            }else{
                $tdata['num'] = $team['num'];
            }
            $checknum = true;
            if(getcustom('yx_collage_jieti')){
                if($team['collage_type'] ==1){
                    $checknum = false;
                }
            }

            if(getcustom('yx_collage_team_in_team')){
                //参与拼团支付完成，查询是否标记此团上级团为自己所参与团
                if($order['buytype'] == 3){
                    $teampid = 0;
                    $has_teaminteam = \app\customs\CollageTeamInTeamCustom::has_teaminteam($order['aid']);//团中团权限
                    if($has_teaminteam){
                        //商品信息
                        $product = Db::name('collage_product')->where('id',$order['proid'])->field('id,teaminteam_splitnum,teaminteam_status')->find();
                        if($product && $product['teaminteam_status'] == 1 && $product['teaminteam_splitnum']>0){
                            //标记此订单的上级团为自己所参与团
                            $teampid = $order['teamid'];
                        }
                    }
                    //更新上级团ID
                    $up = Db::name('collage_order')->where('id',$order['id'])->update(['teampid'=>$teampid]);
                    if($up){
                        $order['teampid'] = $teampid;
                    }
                }
            }

			if($tdata['num'] >= $team['teamnum'] && $checknum){
				$tdata['status'] = 2;
				//团长奖励积分
				$orderlist = Db::name('collage_order')->where(['teamid'=>$team['id'],'status'=>1])->select()->toArray();
				$leader = Db::name('member')->where('id',$team['mid'])->find();
				foreach($orderlist as $v){
					if($v['givescore'] > 0){
						\app\commons\Member::addscore($aid,$v['mid'],$v['givescore'],'购买拼团产品奖励'.t('积分'));
					}
                    if(getcustom('collage_givescore_time')){
                        //付款后送积分
                        if($v['givescore2'] > 0){
                            \app\commons\Member::addscore($aid,$v['mid'],$v['givescore2'],'购买拼团商品赠送'.t('积分'));
                        }
                    }

                    //是否直接完成
                    $endorder = false;
                    if(getcustom('yx_collage_teambuy_type')){
                        //是开启团长拼团模式一 不发货直接完成
                        if($v['buytype'] == 2 && $v['teambuy_type'] == 1){
                            $endorder = true;
                        }
                    }
                    if(getcustom('yx_collage_team_in_team')){
                        //处理拼团成功，团中团的队长订单直接完成
                        if($order['buytype'] == 2 && $order['teampid']){
                            $endorder = true;
                        }
                    }
                    if(!$endorder){
                        //同城配送
                        if($order['freight_type'] == 2){
                            if(getcustom('express_maiyatian_autopush')) {
                                //麦芽田同城配送自动推送
                                \app\customs\MaiYaTianCustom::auto_push($aid,$v['id'],$v,'collage_order');
                            }
                        }
                        //自动发货
                        if($v['freight_type']==3){
                            $freight_content = Db::name('collage_product')->where('id',$v['proid'])->value('freightcontent');
                            Db::name('collage_order')->where('id',$v['id'])->update(['freight_content'=>$freight_content,'status'=>2,'send_time'=>time()]);

                            //发货信息录入 微信小程序+微信支付
                            if($v['platform'] == 'wx' && $v['paytypeid'] == 2){
                                \app\commons\Order::wxShipping($v['aid'],$v,'collage');
                            }
                        }
                        //在线卡密
                        if($v['freight_type']==4){
                            $codelist = Db::name('collage_codelist')->where('proid',$v['proid'])->where('status',0)->order('id')->limit($v['num'])->select()->toArray();
                            if($codelist && count($codelist) >= $v['num']){
                                $pscontent = [];
                                foreach($codelist as $codeinfo){
                                    $pscontent[] = $codeinfo['content'];
                                    Db::name('collage_codelist')->where('id',$codeinfo['id'])->update(['orderid'=>$v['id'],'ordernum'=>$v['ordernum'],'headimg'=>$member['headimg'],'nickname'=>$member['nickname'],'buytime'=>time(),'status'=>1]);
                                }
                                $pscontent = implode("\r\n",$pscontent);
                                Db::name('collage_order')->where('id',$v['id'])->update(['freight_content'=>$pscontent,'status'=>2,'send_time'=>time()]);
                            }
                            //发货信息录入 微信小程序+微信支付
                            if($v['platform'] == 'wx' && $v['paytypeid'] == 2){
                                \app\commons\Order::wxShipping($v['aid'],$v,'collage');
                            }
                        }
						\app\commons\Wifiprint::print($aid,'collage',$v['id']);//拼团成功后打印
                    }else{
                        //完成订单
                        Db::name('collage_order')->where('id',$v['id'])->update(['status'=>3,'collect_time'=>time()]);
                    }

                    if(getcustom('yx_collage_team_in_team')){
                        if($v['buytype'] == 3){
                            //处理团中团分裂新团
                            \app\customs\CollageTeamInTeamCustom::deal_splitteam($v);
                        }
                    }
					//公众号通知 拼团成功通知
					$tmplcontent = [];
					$tmplcontent['first'] = '有新拼团订单拼团成功';
					$tmplcontent['remark'] = '点击进入查看~';
					$tmplcontent['keyword1'] = $v['title']; //商品名称
					$tmplcontent['keyword2'] = $leader['nickname'];//团长
					$tmplcontent['keyword3'] = $team['teamnum'];//成团人数
					//\app\commons\Wechat::sendhttmpl(aid,$v['bid'],'tmpl_collagesuccess',$tmplcontent,m_url('admin/order/collageorder'));
					$tmplcontent['first'] = '恭喜您拼团成功';
					$rs = \app\commons\Wechat::sendtmpl($aid,$v['mid'],'tmpl_collagesuccess',$tmplcontent,m_url('activity/collage/orderlist', $aid));
					//订阅消息
					$tmplcontent = [];
					$tmplcontent['thing1'] = $v['title'];
					$tmplcontent['thing10'] = $leader['nickname'];
					$tmplcontent['number12'] = $team['teamnum'];

					$tmplcontentnew = [];
					$tmplcontentnew['thing7'] = $v['title'];
					$tmplcontentnew['thing12'] = $leader['nickname'];
					$tmplcontentnew['number2'] = $team['teamnum'];
					\app\commons\Wechat::sendwxtmpl($aid,$v['mid'],'tmpl_collagesuccess',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);

                    $mtel = Db::name('member')->where('id',$v['mid'])->value('tel');
                    $tel = $mtel?$mtel:$v['tel'];
					//短信通知
					$rs = \app\commons\Sms::send($aid,$tel,'tmpl_collagesuccess',['ordernum'=>$v['ordernum']]);

				}
                if(getcustom('yx_collage_team_in_team')){
                    //处理拼团成功，分发团中团团长分销奖励
                    if($order['buytype'] == 3 && $order['teampid']){
                        \app\customs\CollageTeamInTeamCustom::deal_reward($order,$team);
                    }
                }
			}else{
				$tdata['status'] = 1;
			}
			Db::name('collage_order_team')->where('aid',$aid)->where('id',$order['teamid'])->update($tdata);
		}else{
            if(getcustom('collage_givescore_time')){
                //付款后送积分
                if($order['givescore2'] > 0){
                    \app\commons\Member::addscore($aid,$order['mid'],$order['givescore2'],'购买拼团商品赠送'.t('积分'));
                }
            }
            //同城配送
            if($order['freight_type'] == 2){
                if(getcustom('express_maiyatian_autopush')) {
                    //麦芽田同城配送自动推送
                    \app\customs\MaiYaTianCustom::auto_push($aid,$orderid,$order,'collage_order');
                }
            }
			//自动发货
			if($order['freight_type']==3){
				$freight_content = Db::name('collage_product')->where('id',$order['proid'])->value('freightcontent');
				Db::name('collage_order')->where('id',$order['id'])->update(['freight_content'=>$freight_content,'status'=>2,'send_time'=>time()]);
                //发货信息录入 微信小程序+微信支付
                if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
                    \app\commons\Order::wxShipping($order['aid'],$order,'collage');
                }
			}
			//在线卡密
			if($order['freight_type']==4){
				$codelist = Db::name('collage_codelist')->where('proid',$order['proid'])->where('status',0)->order('id')->limit($order['num'])->select()->toArray();
				if($codelist && count($codelist) >= $order['num']){
					$pscontent = [];
					foreach($codelist as $codeinfo){
						$pscontent[] = $codeinfo['content'];
						Db::name('collage_codelist')->where('id',$codeinfo['id'])->update(['orderid'=>$order['id'],'ordernum'=>$order['ordernum'],'headimg'=>$member['headimg'],'nickname'=>$member['nickname'],'buytime'=>time(),'status'=>1]);
					}
					$pscontent = implode("\r\n",$pscontent);
					Db::name('collage_order')->where('id',$order['id'])->update(['freight_content'=>$pscontent,'status'=>2,'send_time'=>time()]);
				}
                //发货信息录入 微信小程序+微信支付
                if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
                    \app\commons\Order::wxShipping($order['aid'],$order,'collage');
                }
			}
            \app\commons\Wifiprint::print($aid,'collage',$order['id']);//成功后打印
		}

        if(getcustom('yx_mangfan_collage')) {
            //支付成功计算盲返列表
            $order_goods = $order;
            $order_goods['ogid'] = $order['id'];
            $order_goods['real_totalprice'] = $order['totalprice'];
            $order_goods['type'] = 'collage';
            if($order_goods){
                \app\customs\Mangfan::createRecord($aid, $order['mid'], $order['id'], $order['ordernum'], $order['paytime'], $order_goods,false,0,'collage');
            }
        }

        if(getcustom('yx_collage_team_in_team')){
            if($order['buytype'] == 1 || $order['buytype'] == 2){
                //处理团中团分裂新团
                \app\customs\CollageTeamInTeamCustom::deal_splitteam($order);
            }
        }

        if(getcustom('yx_queue_free_collage')){
            //多人拼团排队免单
            \app\customs\QueueFree::join($order,'collage');
        }

		//支付后送券
		$couponlist = \app\commons\Coupon::getpaygive($aid,$mid,'collage',$order['totalprice']);
		if($couponlist){
			foreach($couponlist as $coupon){
				\app\commons\Coupon::send($aid,$mid,$coupon['id']);
			}
		}

		//公众号通知 订单支付成功
		$tmplcontent = [];
		$tmplcontent['first'] = '有新拼团订单支付成功';
		$tmplcontent['remark'] = '点击进入查看~';
		$tmplcontent['keyword1'] = $member['nickname']; //用户名
		$tmplcontent['keyword2'] = $order['ordernum'];//订单号
		$tmplcontent['keyword3'] = $order['totalprice'].'元';//订单金额
		$tmplcontent['keyword4'] = $order['title'];//商品信息
        $tmplcontentNew = [];
        $tmplcontentNew['thing8'] = \app\commons\Mendian::getNameWithBusines($order);//门店
        $tmplcontentNew['phrase18'] = $member['nickname']; //用户名
        $tmplcontentNew['character_string2'] = $order['ordernum'];//订单号
        $tmplcontentNew['amount5'] = $order['totalprice']==0?'0.00':$order['totalprice'];//订单金额
        $tmplcontentNew['thing3'] = $order['title'];//商品信息
		\app\commons\Wechat::sendhttmpl($aid,$order['bid'],'tmpl_orderpay',$tmplcontent,m_url('admin/order/collageorder', $aid),$order['mdid'],$tmplcontentNew);
		$tmplcontent['first'] = '恭喜您的订单已支付成功';
		$rs = \app\commons\Wechat::sendtmpl($aid,$mid,'tmpl_orderpay',$tmplcontent,m_url('activity/collage/orderlist', $aid),$tmplcontentNew);

		$tmplcontent = [];
		$tmplcontent['thing11'] = $order['title'];
		$tmplcontent['character_string2'] = $order['ordernum'];
		$tmplcontent['phrase10'] = '已支付';
		$tmplcontent['amount13'] = $order['totalprice'].'元';
		$tmplcontent['thing27'] = $member['nickname'];
		\app\commons\Wechat::sendhtwxtmpl($aid,$order['bid'],'tmpl_orderpay',$tmplcontent,'admin/order/collageorder',$order['mdid']);

		//短信通知
		if($order['buytype']==1){ //直接购买
			$rs = \app\commons\Sms::send($aid,$member['tel'] ? $member['tel'] : $order['tel'],'tmpl_orderpay',['ordernum'=>$order['ordernum']]);
		}
	}
    //周期购
    public static function cycle_pay($orderid){
        $order = Db::name('cycle_order')->where('id',$orderid)->find();

        $member = Db::name('member')->where('id',$order['mid'])->find();
        $aid = $order['aid'];
        $mid = $order['mid'];
        Db::name('cycle_order_stage')->where('aid',$aid)->where('orderid',$order['id'])->update(['status' =>1]);
        //同城配送
        if($order['freight_type'] == 2){
            if(getcustom('express_maiyatian_autopush')) {
                //麦芽田同城配送自动推送
                \app\customs\MaiYaTianCustom::auto_push($aid,$orderid,$order,'cycle_order_stage');
            }
        }
        //支付后送券
        $couponlist = \app\commons\Coupon::getpaygive($aid,$mid,'cycle',$order['totalprice']);
        if($couponlist){
            foreach($couponlist as $coupon){
                \app\commons\Coupon::send($aid,$mid,$coupon['id']);
            }
        }
        \app\commons\Wifiprint::print($aid,'cycle',$order['id']);

        //发货信息录入 微信小程序+微信支付
        if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
            \app\commons\Order::wxShipping($order['aid'],$order,'cycle');
        }

        //公众号通知 订单支付成功
        $tmplcontent = [];
        $tmplcontent['first'] = '有新周期购订单支付成功';
        $tmplcontent['remark'] = '点击进入查看~';
        $tmplcontent['keyword1'] = $member['nickname']; //用户名
        $tmplcontent['keyword2'] = $order['ordernum'];//订单号
        $tmplcontent['keyword3'] = $order['totalprice'].'元';//订单金额
        $tmplcontent['keyword4'] = $order['title'];//商品信息
        $tmplcontentNew = [];
        $tmplcontentNew['thing8'] = \app\commons\Mendian::getNameWithBusines($order);//门店
        $tmplcontentNew['phrase18'] = $member['nickname']; //用户名
        $tmplcontentNew['character_string2'] = $order['ordernum'];//订单号
        $tmplcontentNew['amount5'] = $order['totalprice']==0?'0.00':$order['totalprice'];//订单金额
        $tmplcontentNew['thing3'] = $order['title'];//商品信息
        \app\commons\Wechat::sendhttmpl($aid,$order['bid'],'tmpl_orderpay',$tmplcontent,m_url('admin/order/cycleorder', $aid),$order['mdid'],$tmplcontentNew);
        $tmplcontent['first'] = '恭喜您的订单已支付成功';
        $rs = \app\commons\Wechat::sendtmpl($aid,$mid,'tmpl_orderpay',$tmplcontent,m_url('pagesExt/cycle/orderList', $aid),$tmplcontentNew);

        $tmplcontent = [];
        $tmplcontent['thing11'] = $order['title'];
        $tmplcontent['character_string2'] = $order['ordernum'];
        $tmplcontent['phrase10'] = '已支付';
        $tmplcontent['amount13'] = $order['totalprice'].'元';
        $tmplcontent['thing27'] = $member['nickname'];
        \app\commons\Wechat::sendhtwxtmpl($aid,$order['bid'],'tmpl_orderpay',$tmplcontent,'admin/order/cycleorder',$order['mdid']);

        $rs = \app\commons\Sms::send($aid,$member['tel'] ? $member['tel'] : $order['tel'],'tmpl_orderpay',['ordernum'=>$order['ordernum']]);

    }
	//幸运拼团订单
	public static function lucky_collage_pay($orderid){

		$order = Db::name('lucky_collage_order')->where('id',$orderid)->find();
		$member = Db::name('member')->where('id',$order['mid'])->find();
		$aid = $order['aid'];
		$mid = $order['mid'];
		if($order['buytype']!=1){
			//支付成功后奖励分享者余额，积分，佣金，优惠券
			if(getcustom('plug_luckycollage')){
				if($order['shareid']>0){
					//取出分享
					$pmember = Db::name('member')->where('id',$order['shareid'])->find();
					if($pmember && $order['sharemoney'] > 0){  //1 奖励余额
						\app\commons\Member::addmoney($aid,$order['shareid'],$order['sharemoney'],'推荐奖励'.t('余额'));
					}else if($order['sharescore'] > 0){  //2 奖励积分
						\app\commons\Member::addscore($aid,$order['shareid'],$order['sharescore'],'推荐奖励'.t('积分'));
					}else if($order['sharecommission'] > 0){  //3 奖励佣金
						\app\commons\Member::addcommission($aid,$order['shareid'],$order['mid'],$order['sharecommission'],'推荐奖励'.t('佣金'));
					}elseif($order['share_yhqids']){ //奖励优惠券
						foreach($order['share_yhqids'] as $yhqid){
							\app\commons\Coupon::send($aid,$order['shareid'],$yhqid);
						}
					}
				}
				if($order['buytype']==2){
					//减掉开团次数
					if($order['isjiqiren']!=1 && $member['ktnum']>0){
						$ktnum = $member['ktnum']-1;
						 Db::name('member')->where(['aid'=>$aid,'id'=>$order['mid']])->update(['ktnum'=>$ktnum]);
					}
				}
			}
			Db::startTrans();	
			$team = Db::name('lucky_collage_order_team')->where('id',$order['teamid'])->lock(true)->find();
			$tdata = [];
			$tdata['num'] = $team['num'] + 1;
			$iscanjia = 1;
			if(getcustom('member_tag')){
				$product = Db::name('lucky_collage_product')->where('aid',$aid)->where('status',1)->where('ischecked',1)->where('id',$order['proid'])->lock(true)->find();
				if($product['istag']==1){
					$rs = \app\models\LuckyCollage::membertag_collage($order['mid'],$order['teamid'],$product);
					if($rs && $rs['status']==0){
						$iscanjia = 0;
						\app\commons\Order::refund($order,$order['totalprice'],'不符合参加条件订单退款');
						Db::name('lucky_collage_order')->where('id',$order['id'])->update(['teamid'=>0,'status'=>4,'refund_status'=>2,'refund_time'=>time(),'refund_money'=>$order['totalprice'],'refund_reason'=>'不符合参加条件订单退款','iszj'=>0]);
					}
				}
			}
			if($iscanjia==1){
				if($tdata['num']  == $team['teamnum']){
					$tdata['status'] = 2;
					Db::name('lucky_collage_order_team')->where('aid',$aid)->where('id',$order['teamid'])->update($tdata);
					\app\models\LuckyCollage::kaijiang($order);
				}elseif($tdata['num'] >$team['teamnum']){
					//已拼团成功退款
					\app\commons\Order::refund($order,$order['totalprice'],'拼团参加失败订单退款');
					Db::name('lucky_collage_order')->where('id',$order['id'])->update(['teamid'=>0,'status'=>4,'refund_status'=>2,'refund_time'=>time(),'refund_money'=>$order['totalprice'],'refund_reason'=>'拼团参加失败订单退款','iszj'=>0]);
				}else{
					$tdata['status'] = 1;
					Db::name('lucky_collage_order_team')->where('aid',$aid)->where('id',$order['teamid'])->update($tdata);
				}
				//关闭其他参与未支付得订单
				Db::name('lucky_collage_order')->where('aid',$aid)->where('teamid',$order['teamid'])->where('mid',$order['mid'])->where('id','<>',$order['id'])->where('isjiqiren',0)->update(['status'=>4,'teamid'=>0]);
			}
			Db::commit();
		}else{
            //同城配送
            if($order['freight_type'] == 2){
                if(getcustom('express_maiyatian_autopush')) {
                    //麦芽田同城配送自动推送
                    \app\customs\MaiYaTianCustom::auto_push($aid,$orderid,$order,'lucky_collage_order');
                }
            }
        }

		//支付后送券
		$couponlist = \app\commons\Coupon::getpaygive($aid,$mid,'lucky_collage',$order['totalprice']);
		if($couponlist){
			foreach($couponlist as $coupon){
				\app\commons\Coupon::send($aid,$mid,$coupon['id']);
			}
		}

		//公众号通知 订单支付成功
		$tmplcontent = [];
		$tmplcontent['first'] = '有新拼团订单支付成功';
		$tmplcontent['remark'] = '点击进入查看~';
		$tmplcontent['keyword1'] = $member['nickname']; //用户名
		$tmplcontent['keyword2'] = $order['ordernum'];//订单号
		$tmplcontent['keyword3'] = $order['totalprice'].'元';//订单金额
		$tmplcontent['keyword4'] = $order['title'];//商品信息
        $tmplcontentNew = [];
        $tmplcontentNew['thing8'] = \app\commons\Mendian::getNameWithBusines($order);//门店
        $tmplcontentNew['phrase18'] = $member['nickname']; //用户名
        $tmplcontentNew['character_string2'] = $order['ordernum'];//订单号
        $tmplcontentNew['amount5'] = $order['totalprice'];//订单金额
        $tmplcontentNew['thing3'] = $order['title'];//商品信息
		\app\commons\Wechat::sendhttmpl($aid,$order['bid'],'tmpl_orderpay',$tmplcontent,m_url('admin/order/luckycollageorder', $aid),$order['mdid'],$tmplcontentNew);
		$tmplcontent['first'] = '恭喜您的订单已支付成功';
		$rs = \app\commons\Wechat::sendtmpl($aid,$order['mid'],'tmpl_orderpay',$tmplcontent,m_url('activity/luckycollage/orderlist', $aid),$tmplcontentNew);

		$tmplcontent = [];
		$tmplcontent['thing11'] = $order['title'];
		$tmplcontent['character_string2'] = $order['ordernum'];
		$tmplcontent['phrase10'] = '已支付';
		$tmplcontent['amount13'] = $order['totalprice'].'元';
		$tmplcontent['thing27'] = $member['nickname'];
		\app\commons\Wechat::sendhtwxtmpl($aid,$order['bid'],'tmpl_orderpay',$tmplcontent,'admin/order/luckycollageorder',$order['mdid']);

		\app\commons\Wifiprint::print($aid,'collage',$order['id']);
		//短信通知
		if($order['buytype']==1){ //直接购买
			$rs = \app\commons\Sms::send($aid,$member['tel'] ? $member['tel'] : $order['tel'],'tmpl_orderpay',['ordernum'=>$order['ordernum']]);
		}
	}

	//砍价订单
	public static function kanjia_pay($orderid){
		$order = Db::name('kanjia_order')->where('id',$orderid)->find();
		$member = Db::name('member')->where('id',$order['mid'])->find();
		$product = Db::name('kanjia_product')->where('id',$order['proid'])->find();
		$aid = $order['aid'];
		$mid = $order['mid'];
        //同城配送
        if($order['freight_type'] == 2){
            if(getcustom('express_maiyatian_autopush')) {
                //麦芽田同城配送自动推送
                \app\customs\MaiYaTianCustom::auto_push($aid,$orderid,$order,'kanjia_order');
            }
        }
		//自动发货
		if($order['freight_type']==3){
			$freight_content = $product['freightcontent'];
			Db::name('kanjia_order')->where('id',$order['id'])->update(['freight_content'=>$freight_content,'status'=>2,'send_time'=>time()]);
            //发货信息录入 微信小程序+微信支付
            if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
                \app\commons\Order::wxShipping($order['aid'],$order,'kanjia');
            }
		}
		//在线卡密
		if($order['freight_type']==4){
			$codelist = Db::name('kanjia_codelist')->where('proid',$order['proid'])->where('status',0)->order('id')->limit($order['num'])->select()->toArray();
			if($codelist && count($codelist) >= $order['num']){
				$pscontent = [];
				foreach($codelist as $codeinfo){
					$pscontent[] = $codeinfo['content'];
					Db::name('kanjia_codelist')->where('id',$codeinfo['id'])->update(['orderid'=>$order['id'],'ordernum'=>$order['ordernum'],'headimg'=>$member['headimg'],'nickname'=>$member['nickname'],'buytime'=>time(),'status'=>1]);
				}
				$pscontent = implode("\r\n",$pscontent);
				Db::name('kanjia_order')->where('id',$order['id'])->update(['freight_content'=>$pscontent,'status'=>2,'send_time'=>time()]);
			}
            //发货信息录入 微信小程序+微信支付
            if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
                \app\commons\Order::wxShipping($order['aid'],$order,'kanjia');
            }
		}

		//买单后发放帮砍得积分或余额
		if($product['helpgive_ff'] == 1 && $product['helpgive_percent'] > 0 && $order['joinid']){
			$helplist = Db::name('kanjia_help')->where('aid',$aid)->where('joinid',$order['joinid'])->where('mid','<>',$mid)->select()->toArray();
			foreach($helplist as $help){
				if($product['helpgive_type'] == 0){ //积分
					$givescore = intval($help['cut_price'] * $product['helpgive_percent'] * 0.01);
					if($givescore > 0){
						\app\commons\Member::addscore($aid,$help['mid'],$givescore,'帮好友砍价奖励');
					}
				}
				if($product['helpgive_type'] == 1){ //余额
					$givemoney = round($help['cut_price'] * $product['helpgive_percent'] * 0.01,2);
					if($givemoney > 0){
						\app\commons\Member::addmoney($aid,$help['mid'],$givemoney,'帮好友砍价奖励');
					}
				}
			}
		}
		//下单增加帮砍次数
		if($product['perhelpnum_buyadd'] > 0){
			$sharelog = Db::name('kanjia_sharelog')->where('aid',$aid)->where('proid',$product['id'])->where('mid',$mid)->find();
			if($sharelog){
				Db::name('kanjia_sharelog')->where('id',$sharelog['id'])->inc('addtimes',$product['perhelpnum_buyadd'])->update();
			}else{
				$data = [];
				$data['aid'] = $aid;
				$data['proid'] = $product['id'];
				$data['mid'] = $mid;
				$data['addtimes'] = $product['perhelpnum_buyadd'];
				Db::name('kanjia_sharelog')->insert($data);
			}
		}
		//下单送抽奖/余额/积分/优惠券
		if($product['paygive_choujiangtimes'] > 0 && $product['paygive_choujiangid'] > 0){
			$sharelog = Db::name('choujiang_sharelog')->where('aid',$aid)->where('hid',$product['paygive_choujiangid'])->where('mid',$mid)->find();
			if($sharelog){
				Db::name('choujiang_sharelog')->where('id',$sharelog['id'])->inc('extratimes',$product['paygive_choujiangtimes'])->update();
			}else{
				$data = [];
				$data['aid'] = $aid;
				$data['hid'] = $product['paygive_choujiangid'];
				$data['mid'] = $mid;
				$data['extratimes'] = $product['paygive_choujiangtimes'];
				Db::name('choujiang_sharelog')->insert($data);
			}
		}
		if($product['paygive_money'] > 0){
			\app\commons\Member::addmoney($aid,$mid,$product['paygive_money'],'砍价活动下单赠送');
		}
		if($product['paygive_score'] > 0){
			\app\commons\Member::addscore($aid,$mid,$product['paygive_score'],'砍价活动下单赠送');
		}
		if($product['paygive_couponid'] > 0){
			\app\commons\Coupon::send($aid,$mid,$product['paygive_couponid']);
		}

		//支付后送券
		$couponlist = \app\commons\Coupon::getpaygive($aid,$mid,'kanjia',$order['totalprice']);
		if($couponlist){
			foreach($couponlist as $coupon){
				\app\commons\Coupon::send($aid,$mid,$coupon['id']);
			}
		}
		\app\commons\Wifiprint::print($aid,'kanjia',$order['id']);

		//公众号通知 订单支付成功
		$tmplcontent = [];
		$tmplcontent['first'] = '有新砍价订单支付成功';
		$tmplcontent['remark'] = '点击进入查看~';
		$tmplcontent['keyword1'] = $member['nickname']; //用户名
		$tmplcontent['keyword2'] = $order['ordernum'];//订单号
		$tmplcontent['keyword3'] = $order['totalprice'].'元';//订单金额
		$tmplcontent['keyword4'] = $order['title'];//商品信息
        $tmplcontentNew = [];
        $tmplcontentNew['thing8'] = \app\commons\Mendian::getNameWithBusines($order);//门店
        $tmplcontentNew['phrase18'] = $member['nickname']; //用户名
        $tmplcontentNew['character_string2'] = $order['ordernum'];//订单号
        $tmplcontentNew['amount5'] = $order['totalprice']==0?'0.00':$order['totalprice'];//订单金额
        $tmplcontentNew['thing3'] = $order['title'];//商品信息
		\app\commons\Wechat::sendhttmpl($aid,$order['bid'],'tmpl_orderpay',$tmplcontent,m_url('admin/order/kanjiaorder', $aid),$order['mdid'],$tmplcontentNew);
		$tmplcontent['first'] = '恭喜您的订单已支付成功';
		$rs = \app\commons\Wechat::sendtmpl($aid,$mid,'tmpl_orderpay',$tmplcontent,m_url('activity/kanjia/orderlist', $aid),$tmplcontentNew);

		$tmplcontent = [];
		$tmplcontent['thing11'] = $order['title'];
		$tmplcontent['character_string2'] = $order['ordernum'];
		$tmplcontent['phrase10'] = '已支付';
		$tmplcontent['amount13'] = $order['totalprice'].'元';
		$tmplcontent['thing27'] = $member['nickname'];
		\app\commons\Wechat::sendhtwxtmpl($aid,$order['bid'],'tmpl_orderpay',$tmplcontent,'admin/order/kanjiaorder',$order['mdid']);

		//短信通知
		$rs = \app\commons\Sms::send($aid,$member['tel'] ? $member['tel'] : $order['tel'],'tmpl_orderpay',['ordernum'=>$order['ordernum']]);
	}
	//秒杀订单
	public static function seckill_pay($orderid){
		$order = Db::name('seckill_order')->where('id',$orderid)->find();
		$member = Db::name('member')->where('id',$order['mid'])->find();
		$aid = $order['aid'];
		$mid = $order['mid'];
        //同城配送
        if($order['freight_type'] == 2){
            if(getcustom('express_maiyatian_autopush')) {
                //麦芽田同城配送自动推送
                \app\customs\MaiYaTianCustom::auto_push($aid,$orderid,$order,'seckill_order');
            }
        }
		//自动发货
		if($order['freight_type']==3){
			$freight_content = Db::name('seckill_product')->where('id',$order['proid'])->value('freightcontent');
			Db::name('seckill_order')->where('id',$order['id'])->update(['freight_content'=>$freight_content,'status'=>2,'send_time'=>time()]);
            //发货信息录入 微信小程序+微信支付
            if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
                \app\commons\Order::wxShipping($order['aid'],$order,'seckill');
            }
		}
		//在线卡密
		if($order['freight_type']==4){
			$codelist = Db::name('seckill_codelist')->where('proid',$order['proid'])->where('status',0)->order('id')->limit($order['num'])->select()->toArray();
			if($codelist && count($codelist) >= $order['num']){
				$pscontent = [];
				foreach($codelist as $codeinfo){
					$pscontent[] = $codeinfo['content'];
					Db::name('seckill_codelist')->where('id',$codeinfo['id'])->update(['orderid'=>$order['id'],'ordernum'=>$order['ordernum'],'headimg'=>$member['headimg'],'nickname'=>$member['nickname'],'buytime'=>time(),'status'=>1]);
				}
				$pscontent = implode("\r\n",$pscontent);
				Db::name('seckill_order')->where('id',$order['id'])->update(['freight_content'=>$pscontent,'status'=>2,'send_time'=>time()]);
			}
            //发货信息录入 微信小程序+微信支付
            if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
                \app\commons\Order::wxShipping($order['aid'],$order,'seckill');
            }
		}

		//支付后送券
		$couponlist = \app\commons\Coupon::getpaygive($aid,$mid,'seckill',$order['totalprice']);
		if($couponlist){
			foreach($couponlist as $coupon){
				\app\commons\Coupon::send($aid,$mid,$coupon['id']);
			}
		}
		\app\commons\Wifiprint::print($aid,'seckill',$order['id']);

		//公众号通知 订单支付成功
		$tmplcontent = [];
		$tmplcontent['first'] = '有新秒杀订单支付成功';
		$tmplcontent['remark'] = '点击进入查看~';
		$tmplcontent['keyword1'] = $member['nickname']; //用户名
		$tmplcontent['keyword2'] = $order['ordernum'];//订单号
		$tmplcontent['keyword3'] = $order['totalprice'].'元';//订单金额
		$tmplcontent['keyword4'] = $order['title'];//商品信息
        $tmplcontentNew = [];
        $tmplcontentNew['thing8'] = \app\commons\Mendian::getNameWithBusines($order);//门店
        $tmplcontentNew['phrase18'] = $member['nickname']; //用户名
        $tmplcontentNew['character_string2'] = $order['ordernum'];//订单号
        $tmplcontentNew['amount5'] = $order['totalprice']==0?'0.00':$order['totalprice'];//订单金额
        $tmplcontentNew['thing3'] = $order['title'];//商品信息
		\app\commons\Wechat::sendhttmpl($aid,$order['bid'],'tmpl_orderpay',$tmplcontent,m_url('admin/order/seckillorder', $aid),$order['mdid'],$tmplcontentNew);
		$tmplcontent['first'] = '恭喜您的订单已支付成功';
		$rs = \app\commons\Wechat::sendtmpl($aid,$mid,'tmpl_orderpay',$tmplcontent,m_url('activity/seckill/orderlist', $aid),$tmplcontentNew);

		$tmplcontent = [];
		$tmplcontent['thing11'] = $order['title'];
		$tmplcontent['character_string2'] = $order['ordernum'];
		$tmplcontent['phrase10'] = '已支付';
		$tmplcontent['amount13'] = $order['totalprice'].'元';
		$tmplcontent['thing27'] = $member['nickname'];
		\app\commons\Wechat::sendhtwxtmpl($aid,$order['bid'],'tmpl_orderpay',$tmplcontent,'admin/order/seckillorder',$order['mdid']);

		//短信通知
		$rs = \app\commons\Sms::send($aid,$member['tel'] ? $member['tel'] : $order['tel'],'tmpl_orderpay',['ordernum'=>$order['ordernum']]);

		$set = Db::name('admin_set')->where('aid',$aid)->find();
		if($set['fxjiesuantime'] == 1 && $set['fxjiesuantime_delaydays'] == '0'){
			\app\commons\Order::giveCommission($order,'seckill');
		}
	}

	//充值订单
	public static function recharge_pay($orderid){
		$order = Db::name('recharge_order')->where('id',$orderid)->find();
		$member = Db::name('member')->where('id',$order['mid'])->find();
		$aid = $order['aid'];
		$mid = $order['mid'];

		//充值赠送
		$giveset = Db::name('recharge_giveset')->where('aid',$aid)->find();
		if($giveset && $giveset['status']==1){
			$givedata = json_decode($giveset['givedata'],true);
		}else{
			$givedata = array();
		}
		$givemoney = 0;
		$givescore = 0;
		$moneyduan = 0;

        if(getcustom('yx_money_monthsend')){
            //处理充值每月赠送
            $monthdatas = [];
        }
		if($givedata){
			foreach($givedata as $give){
				if($order['money']*1 >= $give['money']*1 && $give['money']*1 > $moneyduan){
					$moneyduan = $give['money']*1;
					$givemoney = $give['give']*1;
                    $givescore = $give['give_score']*1;
                    if(getcustom('yx_money_monthsend')){
                        //总共返多少
                        $monthdatas['allsendmoney'] = $order['money'];
                        $monthdatas['allsendscore'] = 0;

                        //立即赠送
                        $monthdatas['month_sendmoney']  = $give['month_sendmoney'];
                        $monthdatas['month_sendscore']  = $give['month_sendscore'];
                        //每个月返还
                        $monthdatas['month_sendmoney2'] = $give['month_sendmoney2'];
                        $monthdatas['month_sendscore2'] = $give['month_sendscore2'];
                        $monthdatas['month_sendnum']    = $give['month_sendnum'];
                    }
				}
			}
		}

        //是否直接增加充值
        $addmoney = true;

        if(getcustom('yx_money_monthsend')){
            //处理充值每月赠送
            if($monthdatas){
                //查询是否有立即到账设置和按月返还设置
                if(!empty($monthdatas['month_sendmoney']) || $monthdatas['month_sendmoney'] === '0'){
                    $addmoney = false;
                    if($monthdatas['month_sendmoney'] > 0){
                        \app\commons\Member::addmoney($aid,$mid,$monthdatas['month_sendmoney'],'充值立即到账');
                    }
                }
                if(!empty($monthdatas['month_sendscore']) || $monthdatas['month_sendscore'] === '0'){
                    $addmoney = false;
                    if($monthdatas['month_sendscore'] > 0){
                        \app\commons\Member::addscore($aid,$mid,$monthdatas['month_sendscore'],'充值立即到账');
                    }
                }
                //是否有每月到账设置
                if((!empty($monthdatas['month_sendmoney']) || $monthdatas['month_sendmoney'] === '0') || (!empty($monthdatas['month_sendmoney']) || $monthdatas['month_sendmoney'] === '0')){
                    $addmoney = false;
                }
                //是否有按月返还设置
                if((!empty($monthdatas['month_sendnum']) || $monthdatas['month_sendnum'] === '0')){
                    $addmoney = false;
                }
                \app\customs\yingxiao\MoneyMonthsendCustom::deal_monthlog($order,$monthdatas);
            }
        }
        if($addmoney){
            $params = [];
            if(getcustom('moneylog_detail')){
                $params['type'] = 'recharge';
                $params['ordernum'] = $order['ordernum'];
            }
            \app\commons\Member::addmoney($aid,$mid,$order['money'],t('余额').'充值','','',$orderid,$params);
        }

        if($givemoney > 0){
            \app\commons\Member::addmoney($aid,$mid,$givemoney,'充值赠送');
            if(getcustom('member_recharge_detail_refund')){
                Db::name('recharge_order')->where('id',$orderid)->update(['give_money' => $givemoney]);
            }
        }
        if($givescore > 0){
            \app\commons\Member::addscore($aid,$mid,$givescore,'充值赠送');
        }

		//支付后送券
		$couponlist = \app\commons\Coupon::getpaygive($aid,$mid,'recharge',$order['money']);
		if($couponlist){
			foreach($couponlist as $coupon){
				\app\commons\Coupon::send($aid,$mid,$coupon['id']);
			}
		}

		//升级
		\app\commons\Member::uplv($aid,$mid);

        //发货信息录入 微信小程序+微信支付
        if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
            \app\commons\Order::wxShipping($order['aid'],$order,'recharge');
        }
        if(getcustom('recharge_order_wifiprint')){
            $rs = \app\commons\Wifiprint::print($aid,'recharge',$order['id'],0);
        }
		$tmplcontent = array();
		$tmplcontent['first'] = '有新充值订单支付成功';
		$tmplcontent['remark'] = '点击进入查看~';
		$tmplcontent['accountType'] = t('会员').'昵称';
		$tmplcontent['account'] = $member['nickname'];
		$tmplcontent['amount'] = $order['money'].'元' . ($givemoney>0?'+赠送'.$givemoney.'元':'');
		$tmplcontent['result'] = '充值成功';
		\app\commons\Wechat::sendhttmpl($aid,0,'tmpl_recharge',$tmplcontent,m_url('admin/finance/rechargelog', $aid),$order['mdid']);

		//充值通知
        if(getcustom('zhaopin')){
            \app\models\Zhaopin::sendhtsms('tmpl_recharge',[],$order['aid'],$order['bid'],$order['mdid']);
        }
        //充值余额
        if(getcustom('sms_temp_money_recharge')){
            if($member['tel']){
                $rs = \app\commons\Sms::send($aid,$member['tel'],'tmpl_money_recharge',['money'=>$order['money'],'givemoney'=>$givemoney]);
            }
        }
        if(getcustom('member_recharge_yj')){
            //充值业绩
            \app\customs\RechargeYj::addyj($order);
        }
	}
    //充值订单
    public static function overdraft_recharge_pay($orderid){
        $order = Db::name('overdraft_recharge_order')->where('id',$orderid)->find();
        $aid = $order['aid'];
		$mid = $order['mid'];
		$money = $order['totalprice'];
        $remark = '用户还款';
        \app\commons\Member::addOverdraftMoney($aid,$mid,$money,$remark);
        return true;
    }


	//会员升级订单
	public static function member_levelup_pay($orderid){
		$order = Db::name('member_levelup_order')->where('id',$orderid)->find();
		$member = Db::name('member')->where('id',$order['mid'])->find();
		$aid = $order['aid'];
		$mid = $order['mid'];

       







		//成为分销商
		$leveldata = Db::name('member_level')->where('aid',$aid)->where('id',$order['levelid'])->find();
		if(getcustom('gdfenhong_level')){
            if(!$leveldata['apply_paygudong']){
                //当时参与股东分红的直接改状态，防止后期设置了可以参与分红又触发分红
                Db::name('member_levelup_order')->where('id',$orderid)->update(['isfenhong'=>2]);
            }
        }
        
       if($leveldata["level_type"] == 1 ) {
			\app\commons\Aaa::edit2($aid,$mid);
			
			\app\commons\Aaa::LvCreate($aid,$mid,$order,$leveldata);
			
        }
        
        
        
        
        
        
        
        
        
        
        
        
		if($leveldata['apply_check']){
			//$return = array('status'=>2,'msg'=>'付款成功请等待审核');

			$tmplcontent = [];
			$tmplcontent['first'] = '有新用户申请升级,请及时处理';
			$tmplcontent['remark'] = '请进入电脑端后台进行审核~';
			$tmplcontent['keyword1'] = $leveldata['name']; //会员等级
			$tmplcontent['keyword2'] = '待审核';//审核状态
			\app\commons\Wechat::sendhttmpl($aid,0,'tmpl_uplv',$tmplcontent);

		}else{
			if($leveldata['yxqdate'] > 0){
				$levelendtime = strtotime(date('Y-m-d')) + 86400 + 86400 * $leveldata['yxqdate'];
			}else{
				$levelendtime = 0;
			}
			Db::name('member_levelup_order')->where('id',$orderid)->update(['status'=>2,'paytime'=>time(),'levelup_time' =>time()]);
            //判断是否默认分组
			if($leveldata['cid'] > 0)
            $is_default = Db::name('member_level_category')->where('id', $leveldata['cid'])->value('isdefault');
            if($is_default || $leveldata['cid'] == 0) {
                Db::name('member')->where('aid',$aid)->where('id',$mid)->update(['levelid'=>$leveldata['id'],'levelendtime'=>$levelendtime,'levelstarttime'=>time()]);

                //更新代理区域
                if($leveldata['areafenhong']==1){
                    Db::name('member')->where('aid',$aid)->where('id',$order['mid'])->update(['areafenhong_province'=>$order['areafenhong_province'],'areafenhong_city'=>'','areafenhong_area'=>'']);
                }elseif($leveldata['areafenhong']==2){
                    Db::name('member')->where('aid',$aid)->where('id',$order['mid'])->update(['areafenhong_province'=>$order['areafenhong_province'],'areafenhong_city'=>$order['areafenhong_city'],'areafenhong_area'=>'']);
                }elseif($leveldata['areafenhong']==3){
                    Db::name('member')->where('aid',$aid)->where('id',$order['mid'])->update(['areafenhong_province'=>$order['areafenhong_province'],'areafenhong_city'=>$order['areafenhong_city'],'areafenhong_area'=>$order['areafenhong_area']]);
                }elseif($leveldata['areafenhong']==10){
                    Db::name('member')->where('aid',$aid)->where('id',$order['mid'])->update(['areafenhong_largearea'=>$order['areafenhong_largearea']]);
                }
            } else {
                if(getcustom('plug_sanyang')) {
                    $count = Db::name('member_level_record')->where('aid', $aid)->where('mid', $mid)->where('cid', $leveldata['cid'])->count();
                    if($count) Db::name('member_level_record')->where('aid', $aid)->where('mid', $mid)->where('cid', $leveldata['cid'])->update(['levelid' => $leveldata['id'], 'levelendtime' => $levelendtime]);
                    else {
                        $record_data = ['levelid' => $leveldata['id'], 'levelendtime' => $levelendtime];
                        $record_data['aid'] = $aid;
                        $record_data['mid'] = $mid;
                        $record_data['createtime'] = time();
                        $record_data['cid'] = $leveldata['cid'];
                        Db::name('member_level_record')->insertGetId($record_data);
                    }
                    Db::name('member_level_record')->where('aid', $aid)->where('mid', $mid)->where('cid', $leveldata['cid'])->update(['levelstarttime'=>time()]);

                    //更新代理区域
                    if($leveldata['areafenhong']==1){
                        Db::name('member_level_record')->where('aid', $aid)->where('mid', $mid)->where('cid', $leveldata['cid'])->update(['areafenhong_province'=>$order['areafenhong_province']]);
                    }elseif($leveldata['areafenhong']==2){
                        Db::name('member_level_record')->where('aid', $aid)->where('mid', $mid)->where('cid', $leveldata['cid'])->update(['areafenhong_province'=>$order['areafenhong_province'],'areafenhong_city'=>$order['areafenhong_city']]);
                    }elseif($leveldata['areafenhong']==3){
                        Db::name('member_level_record')->where('aid', $aid)->where('mid', $mid)->where('cid', $leveldata['cid'])->update(['areafenhong_province'=>$order['areafenhong_province'],'areafenhong_city'=>$order['areafenhong_city'],'areafenhong_area'=>$order['areafenhong_area']]);
                    }
                }
            }

            //0702给上级更新拉新时间
            if(getcustom('seckill2') && in_array($order['levelid'],explode(',',Db::name('seckill2_sysset')->where('aid',$order['aid'])->value('seckill_level')))){
                Db::name('member')->where(['aid'=>$aid,'id'=>$member['pid']])->update(['laxin_time'=>time()]);
            }

            if($leveldata['apply_payfenxiao'] == 1){ //升级费用参与分销及分红
                \app\commons\Common::applypayfenxiao($aid,$order['id']);
            }

            //赠送积分
            if($leveldata['up_give_score'] > 0) {
                \app\commons\Member::addscore($aid,$mid,$leveldata['up_give_score'],'升级奖励');
            }

            //奖励佣金
            if($leveldata['up_give_commission'] > 0) {
                \app\commons\Member::addcommission($aid,$mid,0,$leveldata['up_give_commission'],'升级奖励');
            }

            //奖励余额
            if($leveldata['up_give_money'] > 0) {
                \app\commons\Member::addmoney($aid,$mid,$leveldata['up_give_money'],'升级奖励');
            }

            //赠送上级佣金
            if ($leveldata['up_give_parent_money'] > 0) {
                $pid = Db::name('member')->where('aid', $aid)->where('id', $mid)->value('pid');
                if($pid > 0) \app\commons\Member::addcommission($aid, $pid, $mid, $leveldata['up_give_parent_money'], '直推奖');
            }

			//升级赠送优惠券
            if(getcustom('up_give_coupon')){
                //商城优惠券赠送
                $shop_coupon = $leveldata['up_give_coupon']?json_decode($leveldata['up_give_coupon'],true):[];
                foreach($shop_coupon as $k=>$v){
                    if($v['num']<1){
                        continue;
                    }
                    for($i=0;$i<$v['num'];$i++){
                        \app\commons\Coupon::send($aid,$mid,$v['id'],true);
                    }
                }
                //餐饮优惠券赠送
                $restaurant_coupon = $leveldata['up_give_restaurant_coupon']?json_decode($leveldata['up_give_restaurant_coupon'],true):[];
                foreach($restaurant_coupon as $k=>$v){
                    if($v['num']<1){
                        continue;
                    }
                    for($i=0;$i<$v['num'];$i++){
                        \app\commons\Coupon::send($aid,$mid,$v['id'],true);
                    }
                }
            }
            if(getcustom('school_product')) {
                \app\models\School::updateMemberClass($aid, $mid, $orderid, $order['school_id'], $order['grade_id'], $order['class_id'], $order['levelid']);
            }
            if(getcustom('up_fxorder_condition_new')){
                //升级
                \app\commons\Member::uplv($aid,$mid);
            }
            if(getcustom('ganer_fenxiao')){
                //升级
                \app\commons\Member::uplv($aid,$mid);
                \app\commons\Fenxiao::tuiguang_bonus($orderid);
            }
            //die('stop');
            $member_levelup_parentcommission_jicha = 0;
            if(getcustom('member_levelup_parentcommission_jicha')){
                //上级佣金按团队级差发放
                $member_levelup_parentcommission_jicha = 1;
            }
			if(getcustom('member_levelup_parentcommission')){
				if($leveldata['levelup_parentcommission']){
					$levelup_parentcommission = json_decode($leveldata['levelup_parentcommission'],true);
					$parent = Db::name('member')->where('aid',$aid)->where('id',$member['pid'])->find();
					$commission_money =dd_money_format($levelup_parentcommission[$parent['levelid']]);
					if($commission_money > 0){ 
						\app\commons\Member::addcommission($aid, $member['pid'], $member['id'], $commission_money, '直推-会员升级奖励');
					}
                    if($member_levelup_parentcommission_jicha && $leveldata['levelup_parent_jicha']){
                        \app\commons\Member::parent_commission($leveldata,$parent,$member['id'],$aid);
                    }
				}		
			}
			//[{"coupon_id":"485","cycletype":"2","cyclenum":"2","coupon_num":"1"}]
			if(getcustom('member_levelup_givecoupon')){  //赠送周期优惠券
				if($leveldata['givecoupondata']){
					$givecoupondata = json_decode($leveldata['givecoupondata'],true);
					foreach($givecoupondata as $k=>$g){
						if($g['cycletype']>=1 && $g['cyclenum']>=1){
							$log = Db::name('member_give_coupon_log')->where('aid',$aid)->where('mid',$mid)->where('levelid',$leveldata['id'])->find();
							if($log)  break;
							for($i=1;$i<=$g['cyclenum'];$i++){
								$data = [];
								$days=0;
								if($g['cycletype']==2) $days=7;
								if($g['cycletype']==3) $days=30;
								if($i==1){
									$data['beginzstime'] = time();
									$data['status'] = 1;
									$data['zstime'] = time();
									//赠送优惠券	
									for($j=1;$j<=$g['coupon_num'];$j++){
										\app\commons\Coupon::send($aid,$mid,$g['coupon_id'],false,0,$days);
									}
								}elseif($i>1){
									$BeginDate=date('Y-m-01',strtotime(date('Y-m-d')));
									$data['beginzstime'] = strtotime("$BeginDate +$i month");;
									$data['status'] = 0;
								}
								$data['aid'] = $aid;
								$data['mid'] = $mid;
								$data['couponid'] = $g['coupon_id'];
								$data['coupon_num'] = $g['coupon_num'];
								$data['cycle_type'] = $g['cycletype'];
								$data['createtime'] = time();
								$data['levelid'] = $leveldata['id'];
								Db::name('member_give_coupon_log')->insert($data);
							}
						}
					}
				}		
			}
			if(getcustom('member_levelup_auth')){
				if($leveldata['give_level_totalmoney']>0){
					//查看已经赠送了的额度
					$yzsed =  Db::name('member_salelevel_order')->where('aid',$aid)->where('from_mid',$order['mid'])->where('status',1)->sum('levelprice');
					$salelevel_money =  round($leveldata['give_level_totalmoney']-$yzsed,2);
					if($salelevel_money>0){
						Db::name('member')->where('id',$order['mid'])->update(['salelevel_money'=>$salelevel_money]);
					}
				}
			}
            //会员升级
            \app\commons\Member::uplv($aid,$mid);
            

            if(getcustom('network_slide')){
                //公排网滑落
                $res = \app\commons\Member::net_slide($member['pid'],$mid,$leveldata['id']);
                //dump($res);
            }


            //发货信息录入 微信小程序+微信支付
            if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
                \app\commons\Order::wxShipping($order['aid'],$order,'member_levelup');
            }

			$tmplcontent = [];
			$tmplcontent['first'] = '恭喜您成功升级为'.$leveldata['name'];
			$tmplcontent['remark'] = '点击进入查看~';
			$tmplcontent['keyword1'] = $leveldata['name']; //会员等级
			$tmplcontent['keyword2'] = '已生效';//审核状态
			$rs = \app\commons\Wechat::sendtmpl($aid,$mid,'tmpl_uplv',$tmplcontent,m_url('pages/my/usercenter', $aid));

		}
	}
	//表单支付
	public static function form_pay($orderid){
		$order = Db::name('form_order')->where('id',$orderid)->find();
		$member = Db::name('member')->where('id',$order['mid'])->find();
		$aid = $order['aid'];
		$mid = $order['mid'];
		//分销提成
		$form = Db::name('form')->where('id',$order['formid'])->find();
		$totalcommission = 0;
		if($form && $form['commissionset']!=-1){
			$ogdata = [];
			if($member['pid']){
				$parent1 = Db::name('member')->where('aid',$aid)->where('id',$member['pid'])->find();
				if($parent1){
					$agleveldata1 = Db::name('member_level')->where('aid',$aid)->where('id',$parent1['levelid'])->find();
					if($agleveldata1['can_agent']!=0){
						$ogdata['parent1'] = $parent1['id'];
					}
				}
			}
			if($parent1['pid']){
				$parent2 = Db::name('member')->where('aid',$aid)->where('id',$parent1['pid'])->find();
				if($parent2){
					$agleveldata2 = Db::name('member_level')->where('aid',$aid)->where('id',$parent2['levelid'])->find();
					if($agleveldata2['can_agent']>1){
						$ogdata['parent2'] = $parent2['id'];
					}
				}
			}
			if($parent2['pid']){
				$parent3 = Db::name('member')->where('aid',$aid)->where('id',$parent2['pid'])->find();
				if($parent3){
					$agleveldata3 = Db::name('member_level')->where('aid',$aid)->where('id',$parent3['levelid'])->find();
					if($agleveldata3['can_agent']>2){
						$ogdata['parent3'] = $parent3['id'];
					}
				}
			}
			if($form['commissionset']==1){//按比例
				$commissiondata = json_decode($form['commissiondata1'],true);
				if($commissiondata){
					$ogdata['parent1commission'] = $commissiondata[$agleveldata1['id']]['commission1'] * $order['money'] * 0.01;
					$ogdata['parent2commission'] = $commissiondata[$agleveldata2['id']]['commission2'] * $order['money'] * 0.01;
					$ogdata['parent3commission'] = $commissiondata[$agleveldata3['id']]['commission3'] * $order['money'] * 0.01;
				}
			}elseif($form['commissionset']==2){//按固定金额
				$commissiondata = json_decode($form['commissiondata2'],true);
				if($commissiondata){
					$ogdata['parent1commission'] = $commissiondata[$agleveldata1['id']]['commission1'] * 1;
					$ogdata['parent2commission'] = $commissiondata[$agleveldata2['id']]['commission2'] * 1;
					$ogdata['parent3commission'] = $commissiondata[$agleveldata3['id']]['commission3'] * 1;
				}
			}else{
				$ogdata['parent1commission'] = $agleveldata1['commission1'] * $order['money'] * 0.01;
				$ogdata['parent2commission'] = $agleveldata2['commission2'] * $order['money'] * 0.01;
				$ogdata['parent3commission'] = $agleveldata3['commission3'] * $order['money'] * 0.01;
			}

			if($ogdata['parent1'] && $ogdata['parent1commission'] > 0){
				$totalcommission+=$ogdata['parent1commission'];
				\app\commons\Member::addcommission($aid,$ogdata['parent1'],$mid,$ogdata['parent1commission'],t('下级').'购买奖励');
			}
			if($ogdata['parent2'] && $ogdata['parent2commission'] > 0){
				$totalcommission+=$ogdata['parent2commission'];
				\app\commons\Member::addcommission($aid,$ogdata['parent2'],$mid,$ogdata['parent2commission'],t('下二级').'购买奖励');
			}
			if($ogdata['parent3'] && $ogdata['parent3commission'] > 0){
				$totalcommission+=$ogdata['parent3commission'];
				\app\commons\Member::addcommission($aid,$ogdata['parent3'],$mid,$ogdata['parent3commission'],t('下三级').'购买奖励');
			}
			if($ogdata['parent1']){
				\app\commons\Member::uplv($aid,$ogdata['parent1']);
			}
		}

		if($order['bid']!=0){//入驻商家的货款
			$binfo = Db::name('business')->where('aid',$aid)->where('id',$order['bid'])->find();
			$totalmoney = $order['money'] - $totalcommission;
			if($totalmoney > 0){
				$totalmoney = $totalmoney * (100-$binfo['feepercent']) * 0.01;
			}
			\app\commons\Business::addmoney($aid,$order['bid'],$totalmoney,'表单支付 订单号：'.$order['ordernum']);
		}
        //发货信息录入 微信小程序+微信支付
        if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
            \app\commons\Order::wxShipping($order['aid'],$order,'form');
        }

        if(getcustom('form_option_givescore')){
            //赠送选项积分（暂单选选择）
            if($form && $form['content']){
                //查询表设置的参数
                $formcontent = json_decode($form['content'],true);
                if($formcontent){
                    //$givescore = 0;//赠送积分
                    //查询选择的选项
                    foreach($formcontent as $k=>$v){
                        if($v['key']=='radio' || $v['key']=='selector'){
                            if(isset($order['form'.$k])){
                                $val = $order['form'.$k];//获取选项值;
                                $i = -1;//对应的序号
                                if($v['val2']){
                                    foreach($v['val2'] as $k2=>$v2){
                                        if($v2 == $val){
                                            $i = $k2;
                                        }
                                    }
                                    unset($v2);
                                }
                                if($i>=0 && $v['val16']){
                                    $givescore = $v['val16'][$i]?$v['val16'][$i]:0;
                                    if($givescore >0){
                                        $res = \app\commons\Member::addscore($aid,$order['mid'],$givescore,$val.'赠送'.t('积分'));
                                        if($res && $res['status'] == 1){
                                            Db::name('form_order')->where('aid',$aid)->where('id',$order['id'])->update(['issend_opscore'=>1,'send_opscoretime'=>time()]);
                                        }
                                    }
                                }
                            }
                        }
                    }
                    unset($v);
                }
            }
        }

		$tmplcontent = [];
		$tmplcontent['first'] = '有客户提交表单成功';
		$tmplcontent['remark'] = '点击查看详情~';
		$tmplcontent['keyword1'] = $order['title'];
		$tmplcontent['keyword2'] = date('Y-m-d H:i');
        $tempconNew = [];
        $tempconNew['thing3'] = $order['title'];//报名名称
        $tempconNew['time5'] = date('Y-m-d H:i');//申请时间
		\app\commons\Wechat::sendhttmpl($aid,$order['bid'],'tmpl_formsub',$tmplcontent,m_url('admin/form/formdetail?id='.$orderid,$aid),0,$tempconNew);
		$tel = $member['tel']?$member['tel']:'';
		if($tel){
			//短信通知
			$rs = \app\commons\Sms::send($aid,$tel,'tmpl_formsubmit');
		}
	}

	//工单支付
	public static function workorder_pay($orderid){
		$order = Db::name('workorder_order')->where('id',$orderid)->find();
		$aid = $order['aid'];

        //发货信息录入 微信小程序+微信支付
        if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
            \app\commons\Order::wxShipping($order['aid'],$order,'workorder');
        }

		$tmplcontent = [];
		$tmplcontent['first'] = '有客户提交工单成功';
		$tmplcontent['remark'] = '请进入电脑端后台进行查看~';
		$tmplcontent['keyword1'] = $order['title'];
		$tmplcontent['keyword2'] = date('Y-m-d H:i');
        $tempconNew = [];
        $tempconNew['thing3'] = $order['title'];//报名名称
        $tempconNew['time5'] = date('Y-m-d H:i');//申请时间
		\app\commons\Wechat::sendhttmpl($aid,$order['bid'],'tmpl_formsub',$tmplcontent,'',0,$tempconNew);
	}

	//付费查看页面
	public static function designerpage_pay($orderid){
		$order = Db::name('designerpage_order')->where('id',$orderid)->find();
		\app\commons\Order::collect($order,'designerpage');
        //发货信息录入 微信小程序+微信支付
        if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
            \app\commons\Order::wxShipping($order['aid'],$order,'designerpage');
        }
	}
	//购买优惠券
	public static function coupon_pay($orderid){
		$order = Db::name('coupon_order')->where('id',$orderid)->find();
		$rs = \app\commons\Coupon::send($order['aid'],$order['mid'],$order['cpid']);
		if($rs && $rs['status'] == 0){
			$order['totalprice'] = $order['price'];
			$rs = \app\commons\Order::refund($order,$order['totalprice'],$rs['msg']);
			//\think\facade\Log::write($rs);
		}else{
			\app\commons\Order::collect($order,'coupon');
            //发货信息录入 微信小程序+微信支付
            if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
                \app\commons\Order::wxShipping($order['aid'],$order,'coupon');
            }
		}
	}
	//买单
	public static function maidan_pay($orderid){
		$order = Db::name('maidan_order')->where('id',$orderid)->find();
		$member = Db::name('member')->where('id',$order['mid'])->find();
		$aid = $order['aid'];
		$mid = $order['mid'];
		if($order['couponrid']){
			Db::name('coupon_record')->where('id',$order['couponrid'])->update(['status'=>1,'usetime'=>time()]);
		}
		\app\commons\Order::collect($order,'maidan');

        //支付后送券
		$couponlist = \app\commons\Coupon::getpaygive($aid,$mid,'maidan',$order['money'],$order['id']);
		if($couponlist){
			foreach($couponlist as $coupon){
				\app\commons\Coupon::send($aid,$mid,$coupon['id']);
			}
		}
		//新增打印机打印
        \app\commons\Wifiprint::print(aid,'maidan',$order['id']);
        if(getcustom('sound')){
            \app\commons\Sound::play(aid,'maidan',$order);
        }
        if(getcustom('everyday_hongbao')) {
            $hd = Db::name('hongbao_everyday')->where('aid',$aid)->find();
            if($hd['status'] == 1 && $hd['hongbao_bl_maidan'] > 0) {
                $hongbaoEdu = round($order['paymoney'] * $hd['maidan_hongbao_bl'] / 100,2);
                if($hongbaoEdu > 0)
                \app\commons\Member::addHongbaoEverydayEdu($aid,$order['mid'],$hongbaoEdu, '买单增加红包额度');
            }
        }
        if(getcustom('maidan_qrcode')) {
            \app\customs\MaidanQrcode::deal_ycommission($order,$member);
        }

        if(getcustom('yx_cashback_maidan')){
            //处理返现
            \app\customs\OrderCustom::deal_maidancashback($aid,$order);
        }
        if(getcustom('maidan_invite')){
            //商家买单拉新
            if($order['bid'] > 0){
                $pid = 0;$invitedata = [];
                //查询系统设置
                $inviteset = Db::name('maidan_invite')->where('aid',aid)->where('bid',0)->where('status',1)->find();
                if($inviteset && $inviteset['validday']>0){
                    $invitedata = $inviteset['invitedata']?json_decode($inviteset['invitedata'],true):[];
                    $business = Db::name('business')->where('id',$order['bid'])->where('aid',aid)->field('maidaninviteset')->find();
                    if($business && $business['maidaninviteset']>=0){
                        //查询买单商家绑定的mid
                        $mid = Db::name('admin_user')->where('aid',aid)->where('bid',$order['bid'])->where('isadmin','>',0)->value('mid');
                        $pid = $mid && $mid >0?$mid:0;
                        if($business['maidaninviteset'] == 1){
                            $invitedata = [];
                            //查询商家单独设置
                            $inviteset2 = Db::name('maidan_invite')->where('bid',$order['bid'])->where('aid',aid)->where('status',1)->find();
                            if($inviteset2){
                                $invitedata = $inviteset2['invitedata']?json_decode($inviteset2['invitedata'],true):[];
                            }
                        }
                        //如果用户邀请上级是商家绑定的mid
                        if($pid>0 && $member['pid'] == $pid && $invitedata){
                            //查询用户注册天数
                            $regday = time()-$member['createtime'];
                            $validday = $inviteset['validday']*86400;
                            if($regday<=$validday){
                                //查询用户是否提前消费其他项目
                                $payorderid = Db::name('payorder')->where('orderid',$orderid)->where('type','maidan')->where('aid',aid)->value('id');
                                if($payorderid){
                                    $count = Db::name('payorder')->where('id','<>',$payorderid)->where('mid',$order['mid'])->where('status',1)->where('aid',aid)->count('id');
                                    if(!$count){
                                        //拉新奖励
                                        $invitemoney = 0;
                                        foreach($invitedata as $invite){
                                            if($order['paymoney'] >= $invite['min'] && $invite['paymoney']<= $invite['max']){
                                                $invitemoney = 0;
                                                //返还比例
                                                if($invite['back'] && $invite['back']>0){
                                                    $invitemoney += $order['paymoney']*$invite['back']*0.01;
                                                }
                                                //返还固定金额
                                                if($invite['back2'] && $invite['back2']>0){
                                                    $invitemoney += $invite['back2'];
                                                }
                                            }
                                        }
                                        $invitemoney = $invitemoney?round($invitemoney,2):0;
                                        if($invitemoney>0){
                                            $up = Db::name('maidan_order')->where('id',$order['id'])->update(['invitemoney'=>$invitemoney]);
                                            if($up){
                                                //给商家增加拉新奖励
                                                \app\commons\Business::addmoney(aid,$order['bid'],$invitemoney,'买单拉新奖励，订单号：'.$order['ordernum'],false,'maidan',$order['ordernum']);
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        //发货信息录入 微信小程序+微信支付
        if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
            \app\commons\Order::wxShipping($order['aid'],$order,'maidan');
        }

		//公众号通知 订单支付成功
        $tmplcontent = [];
        $tmplcontent['first'] = '有新买单订单支付成功';
        $tmplcontent['remark'] = '点击进入查看~';
        $tmplcontent['keyword1'] = $member['nickname']; //用户名
        $tmplcontent['keyword2'] = $order['ordernum'];//订单号
        $tmplcontent['keyword3'] = $order['money'].'元';//订单金额
        $tmplcontent['keyword4'] = $order['title'];//商品信息
        $tmplnew = Db::name('mp_tmplset_new')->where('aid',$aid)->find();
        if($tmplnew['tmpl_maidanpay']){
            $tmplcontentNew = [];
            $tmplcontentNew['thing13'] = \app\commons\Mendian::getNameWithBusines($order);//门店
            $tmplcontentNew['thing12'] = $member['nickname']; //用户名
            $tmplcontentNew['character_string4'] = $order['ordernum'];//订单号
            $tmplcontentNew['amount15'] = $order['money'];//订单金额
            $tmplcontentNew['thing11'] = $order['title'];//商品信息
            \app\commons\Wechat::sendhttmpl($aid,$order['bid'],'tmpl_maidanpay',$tmplcontent,m_url('adminExt/order/maidanlog', $aid),$order['mdid'],$tmplcontentNew);
        }elseif($tmplnew['tmpl_orderpay']){
            $tmplcontentNew = [];
            $tmplcontentNew['thing8'] = \app\commons\Mendian::getNameWithBusines($order);//门店
            $tmplcontentNew['phrase18'] = $member['nickname']; //用户名
            $tmplcontentNew['character_string2'] = $order['ordernum'];//订单号
            $tmplcontentNew['amount5'] = $order['money']==0?'0.00': $order['money'];//订单金额
            $tmplcontentNew['thing3'] = $order['title'];//商品信息
            \app\commons\Wechat::sendhttmpl($aid,$order['bid'],'tmpl_orderpay',$tmplcontent,m_url('adminExt/order/maidanlog', $aid),$order['mdid'],$tmplcontentNew);
        }else{
            \app\commons\Wechat::sendhttmpl($aid,$order['bid'],'tmpl_orderpay',$tmplcontent,m_url('adminExt/order/maidanlog', $aid),$order['mdid']);
        }
        if(getcustom('yx_hongbao_queue_free')){
            \app\customs\HongbaoQueueFree::join($order,'maidan');
        }
		$tmplcontent = [];
		$tmplcontent['thing11'] = $order['title'];
		$tmplcontent['character_string2'] = $order['ordernum'];
		$tmplcontent['phrase10'] = '已支付';
		$tmplcontent['amount13'] = $order['money'].'元';
		$tmplcontent['thing27'] = $member['nickname'];
		\app\commons\Wechat::sendhtwxtmpl($aid,$order['bid'],'tmpl_orderpay',$tmplcontent,'adminExt/order/maidanlog',$order['mdid']);
	}

	//餐饮订单合并支付
	public static function restaurant_takeaway_hb_pay($orderid,$ordernum){
		$orderlist = Db::name('restaurant_takeaway_order')->where('ordernum','like',$ordernum.'%')->select()->toArray();
		foreach($orderlist as $order){
			self::restaurant_takeaway_pay($order['id']);
		}
	}
	//餐饮订单
	public static function restaurant_takeaway_pay($orderid){
		$order = Db::name('restaurant_takeaway_order')->where('id',$orderid)->find();
		$member = Db::name('member')->where('id',$order['mid'])->find();
		$aid = $order['aid'];
		$mid = $order['mid'];
		Db::name('restaurant_takeaway_order_goods')->where('orderid',$orderid)->update(['status'=>1]);
		//真实销量和实际销量不含退款的 更新
        $goodslist =Db::name('restaurant_takeaway_order_goods')->where('aid',$aid)->where('orderid', $orderid)->select()->toArray();
        foreach($goodslist as $key=>$val){
            $num = $val['num'];
            Db::name('restaurant_product')->where('aid',$aid)->where('id',$val['proid'])->update(['real_sales'=>Db::raw("real_sales+$num"),'real_sales2'=>Db::raw("real_sales2+$num")]);
        }
		
		$takeaway_set = Db::name('restaurant_takeaway_sysset')->where('aid',$aid)->where('bid',$order['bid'])->find();
		if($takeaway_set['confirm_auto']==1){ //自动接单
			Db::name('restaurant_takeaway_order')->where('id',$orderid)->update(['status'=>12]);
			Db::name('restaurant_takeaway_order_goods')->where('orderid',$orderid)->update(['status'=>12]);
		}

		//支付后送券
		$couponlist = \app\commons\Coupon::getpaygive($aid,$mid,'restaurant',$order['totalprice'],$order['id']);
		if($couponlist){
			foreach($couponlist as $coupon){
				\app\commons\Coupon::send($aid,$mid,$coupon['id']);
			}
		}

		\app\commons\Member::uplv($aid,$mid);
        \app\customs\Restaurant::print('restaurant_takeaway', $order,'','',1);//限制能自动打印的打印机

        if($takeaway_set['confirm_auto']==1){ //自动接单
            //判断是否自动派单
            if($order['freight_type'] == 2){
                $peisong_set = \db('peisong_set')->where('aid',$aid)->find();
                if($peisong_set['express_wx_status'] == 1 && $peisong_set['express_wx_paidan'] == 1){
                    Db::name('restaurant_takeaway_order')->where('id',$orderid)->update(['express_type'=>'express_wx']);
                    \app\customs\ExpressWx::addOrder('restaurant_takeaway_order',$order);
                }else{
                    //0 配送员抢单 1 指定配送员
                    if($peisong_set['paidantype'] == 0){
                        //外卖设置-自动发单 1 开启 0 关闭
                        if($takeaway_set['auto_send_order'] == 1){
                            \app\models\PeisongOrder::create('restaurant_takeaway_order', $order);
                        }
                    }

                }
            }
        }

        //发货信息录入 微信小程序+微信支付
        if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
            \app\commons\Order::wxShipping($order['aid'],$order,'restaurant_takeaway');
        }

		//公众号通知 订单支付成功
		$tmplcontent = [];
		$tmplcontent['first'] = '有新订单支付成功';
		$tmplcontent['remark'] = '点击进入查看~';
		$tmplcontent['keyword1'] = $member['nickname']; //用户名
		$tmplcontent['keyword2'] = $order['ordernum'];//订单号
		$tmplcontent['keyword3'] = $order['totalprice'].'元';//订单金额
		$tmplcontent['keyword4'] = $order['title'];//商品信息
        $tmplcontentNew = [];
        $tmplcontentNew['thing8'] = \app\commons\Mendian::getNameWithBusines($order);//门店
        $tmplcontentNew['phrase18'] = $member['nickname']; //用户名
        $tmplcontentNew['character_string2'] = $order['ordernum'];//订单号
        $tmplcontentNew['amount5'] = $order['totalprice']==0?'0.00':$order['totalprice'];//订单金额
        $tmplcontentNew['thing3'] = $order['title'];//商品信息
		\app\commons\Wechat::sendhttmpl($aid,$order['bid'],'tmpl_orderpay',$tmplcontent,m_url('admin/restaurant/takeawayorder', $aid),$order['mdid'],$tmplcontentNew);
		$tmplcontent['first'] = '恭喜您的订单已支付成功';
		$rs = \app\commons\Wechat::sendtmpl($aid,$mid,'tmpl_orderpay',$tmplcontent,m_url('restaurant/takeaway/orderlist', $aid),$tmplcontentNew);

		$tmplcontent = [];
		$tmplcontent['thing11'] = $order['title'];
		$tmplcontent['character_string2'] = $order['ordernum'];
		$tmplcontent['phrase10'] = '已支付';
		$tmplcontent['amount13'] = $order['totalprice'].'元';
		$tmplcontent['thing27'] = $member['nickname'];
		\app\commons\Wechat::sendhtwxtmpl($aid,$order['bid'],'tmpl_orderpay',$tmplcontent,'admin/restaurant/takeawayorder',$order['mdid']);

		//短信通知
		$rs = \app\commons\Sms::send($aid,$member['tel']?$member['tel']:$order['tel'],'tmpl_orderpay',['ordernum'=>$order['ordernum']]);
	}
    //餐饮订单
    public static function restaurant_booking_pay($orderid){
        $order = Db::name('restaurant_booking_order')->where('id',$orderid)->find();
        $member = Db::name('member')->where('id',$order['mid'])->find();
        $aid = $order['aid'];
        $mid = $order['mid'];
        Db::name('restaurant_booking_order_goods')->where('orderid',$orderid)->update(['status'=>1]);

        //支付后送券
        $couponlist = \app\commons\Coupon::getpaygive($aid,$mid,'restaurant',$order['totalprice'],$order['id']);
        if($couponlist){
            foreach($couponlist as $coupon){
                \app\commons\Coupon::send($aid,$mid,$coupon['id']);
            }
        }

        \app\commons\Member::uplv($aid,$mid);
        \app\customs\Restaurant::print('restaurant_booking', $order,'','',1);

        //发货信息录入 微信小程序+微信支付
        if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
            \app\commons\Order::wxShipping($order['aid'],$order,'restaurant_booking');
        }

        //公众号通知 订单支付成功
        $tmplcontent = [];
        $tmplcontent['first'] = '有新预定支付成功';
        $tmplcontent['remark'] = '点击进入查看~';
        $tmplcontent['keyword1'] = $member['nickname']; //用户名
        $tmplcontent['keyword2'] = $order['ordernum'];//订单号
        $tmplcontent['keyword3'] = $order['totalprice'].'元';//订单金额
        $tmplcontent['keyword4'] = $order['title'];//商品信息
        $tmplcontentNew = [];
        $tmplcontentNew['thing8'] = \app\commons\Mendian::getNameWithBusines($order);//门店
        $tmplcontentNew['phrase18'] = $member['nickname']; //用户名
        $tmplcontentNew['character_string2'] = $order['ordernum'];//订单号
        $tmplcontentNew['amount5'] = $order['totalprice']==0?'0.00':$order['totalprice'];//订单金额
        $tmplcontentNew['thing3'] = $order['title'];//商品信息
        \app\commons\Wechat::sendhttmpl($aid,$order['bid'],'tmpl_orderpay',$tmplcontent,m_url('admin/restaurant/bookingorder', $aid),$order['mdid'],$tmplcontentNew);
        $tmplcontent['first'] = '恭喜您的预定订单已支付成功';
        $rs = \app\commons\Wechat::sendtmpl($aid,$mid,'tmpl_orderpay',$tmplcontent,m_url('restaurant/booking/orderlist', $aid),$tmplcontentNew);

		$tmplcontent = [];
		$tmplcontent['thing11'] = $order['title'];
		$tmplcontent['character_string2'] = $order['ordernum'];
		$tmplcontent['phrase10'] = '已支付';
		$tmplcontent['amount13'] = $order['totalprice'].'元';
		$tmplcontent['thing27'] = $member['nickname'];
		\app\commons\Wechat::sendhtwxtmpl($aid,$order['bid'],'tmpl_orderpay',$tmplcontent,'admin/restaurant/bookingorder',$order['mdid']);

        //短信通知
        $rs = \app\commons\Sms::send($aid,$member['tel']?$member['tel']:$order['tel'],'tmpl_orderpay',['ordernum'=>$order['ordernum']]);
    }

    //餐饮订单
    public static function restaurant_shop_pay($orderid){
        $order = Db::name('restaurant_shop_order')->where('id',$orderid)->find();
        $member = Db::name('member')->where('id',$order['mid'])->find();
        $aid = $order['aid'];
        $bid = $order['bid'];
        $mid = $order['mid'];
        Db::name('restaurant_shop_order_goods')->where('orderid',$orderid)->update(['status'=>1]);
        //更新销量（包含退款） 和真实销量 不含退款
        $goodslist =Db::name('restaurant_shop_order_goods')->where('aid',$aid)->where('orderid', $orderid)->select()->toArray();
         foreach($goodslist as $key=>$val){
             $num = $val['num'];
             Db::name('restaurant_product')->where('aid',$aid)->where('id',$val['proid'])->update(['real_sales'=>Db::raw("real_sales+$num"),'real_sales2'=>Db::raw("real_sales2+$num")]);
             if(getcustom('restaurant_product_package')){
                 if($val['package_data']){
                     $package_data = json_decode($val['package_data'],true);
                     foreach($package_data as $pdk=>$pd){
                         $pdnum = $pd['num'];
                         Db::name('restaurant_product')->where('aid',$aid)->where('id',$pd['proid'])->update(['real_sales'=>Db::raw("real_sales+$pdnum"),'real_sales2'=>Db::raw("real_sales2+$pdnum")]);
                     }
                 }
             }
         }
       
         //判断该桌台
        //支付后送券
        $couponlist = \app\commons\Coupon::getpaygive($aid,$mid,'restaurant',$order['totalprice'],$order['id']);
        if($couponlist){
            foreach($couponlist as $coupon){
                \app\commons\Coupon::send($aid,$mid,$coupon['id']);
            }
        }

        \app\commons\Member::uplv($aid,$mid);
        //发货信息录入 微信小程序+微信支付
        if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
            \app\commons\Order::wxShipping($order['aid'],$order,'restaurant_shop');
        }

        //根据餐后付款设置，开启时下单后打印小票，关闭时付款后打印小票
        $restaurant_shop_sysset = Db::name('restaurant_shop_sysset')->where('aid', $aid)->where('bid', $bid)->find();
        if(getcustom('restaurant_shop_pindan')){
            if($order['tableid']){
                $table = Db::name('restaurant_table')->where('id',$order['tableid'])->where('aid',$aid)->where('bid',$bid)->find();
                if($table['pindan_status'] ==1){
                    $restaurant_shop_sysset['pay_after'] = 1;
                } else{
                    $restaurant_shop_sysset['pay_after'] = 0;
                }
            }else{
                $restaurant_shop_sysset['pay_after'] = 0;
            }
        }
        if($restaurant_shop_sysset['pay_after'] == 0){
            \app\customs\Restaurant::print('restaurant_shop',$order,'','',1);
            if(getcustom('restaurant_table_auto_clean')){
                //清台后变为空闲
                if($order['tableid']) {
                    Db::name('restaurant_table')->where('id',$order['tableid'])->where('aid',$aid)->where('bid',$bid)->update(['status' => 0, 'orderid' => 0]);
                }
                //修改订单为已完成
                Db::name('restaurant_shop_order')->where('aid',$aid)->where('id',$order['id'])->update(['status' => 3]);
            }
        }
        if(getcustom('restaurant_table_after_pay_clean')){
            //每个桌台设置自动清理，付款后自动清理
            if($order['tableid']){
                $table = Db::name('restaurant_table')->where('id',$order['tableid'])->where('aid',$aid)->where('bid',$bid)->find();
                if($table['auto_clean'] ==1){
                    Db::name('restaurant_table')->where('id',$order['tableid'])->where('aid',$aid)->where('bid',$bid)->update(['status' => 0, 'orderid' => 0]);
                }
            }
        }
        if(getcustom('restaurant_take_food')){
            //发送取餐通知
            if($order['pickup_number']){
                \app\customs\Restaurant::addTakeFoodNumber($order);
//                if($order['bid'] == 0){
//                    $bname = Db::name('admin_set')->where('aid',$order['aid'])->value('name');
//                }else{
//                    $bname = Db::name('business')->where('id',$order['bid'])->value('name');
//                }
//                if(platform=='wx'){
//                    //订阅消息
//                    $tmplcontent = [];
//                    $tmplcontent['thing13'] = $bname;
//                    $tmplcontent['thing1'] = $order['pickup_number'];//取单号
//                    $tmplcontent['phrase8'] = '请取餐';//订单状态
//                    $tmplcontent['character_string12'] = $order['ordernum'];//订单编号
//                    $tmplcontentnew = [];
//                    \app\commons\Wechat::sendwxtmpl($aid,$order['mid'],'tmpl_take_food',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);
//                }
//                if(platform=='alipay'){
//                    $templatecontent= [$bname,$order['pickup_number'],$order['ordernum'],'待取餐'];
//                    \app\commons\Alipay::sendTemplateMessage($order['aid'],$order['mid'],$templatecontent);
//                }
            }
        }
        //公众号通知 订单支付成功
        $tmplcontent = [];
        if($order['paytypeid'] != 4) {
            $tmplcontent['first'] = '有新点餐订单支付成功';
        } else {
            $tmplcontent['first'] = '有新点餐订单下单成功（线下支付），';
        }
        $tmplcontent['remark'] = '点击进入查看~';
        $tmplcontent['keyword1'] = $member['nickname']; //用户名
        $tmplcontent['keyword2'] = $order['ordernum'];//订单号
        $tmplcontent['keyword3'] = $order['totalprice'].'元';//订单金额
        $tmplcontent['keyword4'] = $order['title'];//商品信息
        $tmplcontentNew = [];
        $tmplcontentNew['thing8'] = \app\commons\Mendian::getNameWithBusines($order);//门店
        $tmplcontentNew['phrase18'] = $member['nickname']; //用户名
        $tmplcontentNew['character_string2'] = $order['ordernum'];//订单号
        $tmplcontentNew['amount5'] = $order['totalprice']==0?'0.00':$order['totalprice'];//订单金额
        $tmplcontentNew['thing3'] = $order['title'];//商品信息
        \app\commons\Wechat::sendhttmpl($aid,$order['bid'],'tmpl_orderpay',$tmplcontent,m_url('admin/restaurant/shoporder', $aid),$order['mdid'],$tmplcontentNew);
        if($order['paytypeid'] != 4) {
            $tmplcontent['first'] = '恭喜您的点餐订单已支付成功';
        } else {
            $tmplcontent['first'] = '恭喜您的点餐订单下单成功';
        }
        $rs = \app\commons\Wechat::sendtmpl($aid,$mid,'tmpl_orderpay',$tmplcontent,m_url('restaurant/shop/orderlist', $aid),$tmplcontentNew);

		$tmplcontent = [];
		$tmplcontent['thing11'] = $order['title'];
		$tmplcontent['character_string2'] = $order['ordernum'];
		$tmplcontent['phrase10'] = '已支付';
		$tmplcontent['amount13'] = $order['totalprice'].'元';
		$tmplcontent['thing27'] = $member['nickname'];
		\app\commons\Wechat::sendhtwxtmpl($aid,$order['bid'],'tmpl_orderpay',$tmplcontent,'admin/restaurant/shoporder',$order['mdid']);

        //短信通知
        $rs = \app\commons\Sms::send($aid,$member['tel']?$member['tel']:$order['tel'],'tmpl_orderpay',['ordernum'=>$order['ordernum']]);
    }
	public static function plug_businessqr_pay_pay($orderid){
        $order = Db::name('plug_businessqr_pay_order')->where('id',$orderid)->find();
        $member = Db::name('member')->where('id',$order['mid'])->find();
        $aid = $order['aid'];
		if($order['bid']!=0){//入驻商家的货款
			$binfo = Db::name('business')->where('aid',$aid)->where('id',$order['bid'])->find();
			$totalmoney = $order['cost_price'];
			\app\commons\Business::addmoney($aid,$order['bid'],$totalmoney,'会员支付，订单号：'.$order['ordernum']);
			//店铺加销量
			Db::name('business')->where('aid',$aid)->where('id',$order['bid'])->inc('sales',1)->update();

			//公众号通知 订单支付成功
			$tmplcontent = [];
			$tmplcontent['first'] = '有新会员支付订单支付成功';
			$tmplcontent['remark'] = '点击进入查看~';
			$tmplcontent['keyword1'] = $member['nickname']; //用户名
			$tmplcontent['keyword2'] = $order['ordernum'];//订单号
			$tmplcontent['keyword3'] = $order['totalprice'].'元';//订单金额
			$tmplcontent['keyword4'] = $order['title'];//商品信息
            $tmplcontentNew = [];
            $tmplcontentNew['thing8'] = \app\commons\Mendian::getNameWithBusines($order);//门店
            $tmplcontentNew['phrase18'] = $member['nickname']; //用户名
            $tmplcontentNew['character_string2'] = $order['ordernum'];//订单号
            $tmplcontentNew['amount5'] = $order['totalprice']==0?'0.00':$order['totalprice'];//订单金额
            $tmplcontentNew['thing3'] = $order['title'];//商品信息
			\app\commons\Wechat::sendhttmpl($aid,$order['bid'],'tmpl_orderpay',$tmplcontent,m_url('admin/finance/bmoneylog', $aid),$tmplcontentNew);
			$tmplcontent['first'] = '恭喜您已支付成功';
			$rs = \app\commons\Wechat::sendtmpl($aid,$order['mid'],'tmpl_orderpay',$tmplcontent,m_url('pagesExt/money/moneylog?st=1', $aid),$tmplcontentNew);

			$tmplcontent = [];
			$tmplcontent['thing11'] = $order['title'];
			$tmplcontent['character_string2'] = $order['ordernum'];
			$tmplcontent['phrase10'] = '已支付';
			$tmplcontent['amount13'] = $order['totalprice'].'元';
			$tmplcontent['thing27'] = $member['nickname'];
			\app\commons\Wechat::sendhtwxtmpl($aid,$order['bid'],'tmpl_orderpay',$tmplcontent,'admin/finance/bmoneylog');
		}
	}
	//预约服务支付
	public static function yuyue_pay($orderid){
        $order = Db::name('yuyue_order')->where('id',$orderid)->find();
        $product = Db::name('yuyue_product')->field('pdprehour,yynum')->where('id',$order['proid'])->find();
        //查看是否已经存在
        $yycount= Db::name('yuyue_order')->where('aid',$order['aid'])->where('yy_time',$order['yy_time'])->where('proid',$order['proid'])->where('mid','<>',$order['mid'])->where('status','in','1,2')->count();
        if($yycount>=$product['yynum']){
            Db::name('yuyue_order')->where('id',$orderid)->where('aid',$order['aid'])->where('bid',$order['bid'])->update(['status'=>4,'refund_money'=>$order['totalprice'],'refund_status'=>2]);
            //如果已经存在走退款
            $rs = \app\commons\Order::refund($order,$order['totalprice'],'时间重复退款');
            //取消配送订单
            Db::name('yuyue_worker_order')->where('orderid',$orderid)->where('aid',$order['aid'])->where('bid',$order['bid'])->update(['status'=>-1]);
            //退款成功通知
            $tmplcontent = [];
            $tmplcontent['first'] = '您的订单已经退款，¥'.$order['totalprice'].'已经退回您的付款账户，请留意查收。';
            $tmplcontent['remark'] = '请点击查看详情~';
            $tmplcontent['orderProductPrice'] = (string) $order['totalprice'];
            $tmplcontent['orderProductName'] = $order['title'];
            $tmplcontent['orderName'] = $order['ordernum'];
            $tmplcontentNew = [];
            $tmplcontentNew['character_string1'] = $order['ordernum'];//订单编号
            $tmplcontentNew['thing2'] = $order['title'];//商品名称
            $tmplcontentNew['amount3'] = $order['totalprice'];//退款金额
            \app\commons\Wechat::sendtmpl(aid,$order['mid'],'tmpl_tuisuccess',$tmplcontent,m_url('pages/my/usercenter'),$tmplcontentNew);
        }

        $aid = $order['aid'];
        $member = Db::name('member')->where('id',$order['mid'])->find();
        if(!$order) return json(['status'=>0,'msg'=>'订单不存在']);
        if($order['status']!=1 && $order['status']!=12) return json(['status'=>0,'msg'=>'订单状态不符合']);
        if(getcustom('hmy_yuyue') && in_array($order['paidan_type'],[2,3])){ //定制 将订单同步到师傅app端
            \app\customs\Yuyue::apiyuyue($order);
        }else{
            if($order['worker_id']){
                //如果用户已经选择服务人员则支付后直接进行派单
                if(empty($order['worker_orderid'])) {
                    $rs = \app\models\YuyueWorkerOrder::create($order, $order['worker_id'], '');
                    if ($rs['status'] == 0) return json($rs);
                    //\app\commons\System::plog('预约派单'.$orderid);
                }else{
                    //更新提成
                    $ticheng = \app\models\YuyueWorkerOrder::ticheng([],$order);
                    Db::name('yuyue_worker_order')->where('aid',$order['aid'])->where('id',$order['worker_orderid'])->update(['ticheng'=>$ticheng]);
                }
            }else{
                $yyset = Db::name('yuyue_set')->where('aid',$aid)->where('bid',$order['bid'])->find();

                $autopd_worker = false;
                if(getcustom('extend_yuyue_car')){
                    //如果是洗车订单
                    if($order['protype'] == 1){
                        $admin = Db::name('admin')->where('id',$aid)->field('yuyuecar_status')->find();
                        //如果有洗车权限且开启洗车订单推送最近师傅功能
                        if($admin && $admin['yuyuecar_status'] == 1 && $yyset['autopd_worker']== 1){
                            $autopd_worker = true;
                        }
                    }
                }

                if(!$autopd_worker){
                    //自动派单到大厅
                    if($yyset['paidantype']==0 && $yyset['isautopd']==1){
                        $rs = \app\models\YuyueWorkerOrder::create($order,0,'');
                    }
                }else{
                    if(getcustom('extend_yuyue_car')){
                        //下一个小时内的结束时间
                        $next_endtime = strtotime(date("Y-m-d H",$order['paytime']).':00:00')+2*60*60;

                        //转换预约时间
                        $yydate = explode('-',$order['yy_time']);
                        //开始时间
                        $begindate = $yydate[0];
                        if(strpos($begindate,'年') === false){
                            $begindate = date('Y').'年'.$begindate;
                        }
                        $begindate = preg_replace(['/年|月/','/日/'],['-',''],$begindate);
                        $begintime = strtotime($begindate);

                        //如果等于或超出结束时间
                        if($begintime>=$next_endtime){
                        //进入抢单大厅
                            $rs = \app\models\YuyueWorkerOrder::create($order,0,'');
                        }else{
                            //继续派单
                            $worker_id = \app\customs\YuyueCustom::get_worker($order);
                            if($worker_id){
                                \app\models\YuyueWorkerOrder::create($order,$worker_id,'');
                            }
                        }

                        //洗车订单派送给最近的服务人员
                        // $worker_id = \app\customs\YuyueCustom::get_worker($order);
                        // if($worker_id){
                        //     $rs = \app\models\YuyueWorkerOrder::create($order,$worker_id,'');
                        //     if($rs['status']==0) return json($rs);
                        // }
                    }
                }
            }
        }
        if(getcustom('yuyue_order_wifiprint')){
            $rs = \app\commons\Wifiprint::print($aid,'yuyue',$orderid);
        }
        
        //发货信息录入 微信小程序+微信支付
        if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
            \app\commons\Order::wxShipping($order['aid'],$order,'yuyue');
        }

        //支付后送券
        $couponlist = \app\commons\Coupon::getpaygive($aid,$order['mid'],'yuyue',$order['totalprice'],$order['id']);
        if($couponlist){
            foreach($couponlist as $coupon){
                if($coupon['buyyuyueprogive'] == 1){
                    $coupon['buyyuyueproids'] = explode(',',$coupon['buyyuyueproids']);
                    $coupon['buyyuyuepro_give_num'] = explode(',',$coupon['buyyuyuepro_give_num']);
                    foreach($coupon['buyyuyueproids'] as $k => $proid) {
                        if($proid == $order['proid'] && $coupon['buyyuyuepro_give_num'][$k] > 0) {
                            for($i=0;$i<$coupon['buyyuyuepro_give_num'][$k];$i++) {
                                \app\commons\Coupon::send($aid,$order['mid'],$coupon['id']);
                            }
                        }
                    }
                }
            }
        }

        //公众号通知 订单支付成功
        $tmplcontent = [];
        $tmplcontent['first'] = '有新预约订单支付成功';
        $tmplcontent['remark'] = '点击进入查看~';
        $tmplcontent['keyword1'] = $member['nickname']; //用户名
        $tmplcontent['keyword2'] = $order['ordernum'];//订单号
        $tmplcontent['keyword3'] = $order['totalprice'].'元';//订单金额
        $tmplcontent['keyword4'] = $order['title'];//商品信息
        $tmplcontentNew = [];
        $tmplcontentNew['thing8'] = \app\commons\Mendian::getNameWithBusines($order);//门店
        $tmplcontentNew['phrase18'] = $member['nickname']; //用户名
        $tmplcontentNew['character_string2'] = $order['ordernum'];//订单号
        $tmplcontentNew['amount5'] = $order['totalprice']==0?'0.00':$order['totalprice'];//订单金额
        $tmplcontentNew['thing3'] = $order['title'];//商品信息
        \app\commons\Wechat::sendhttmpl($aid,$order['bid'],'tmpl_orderpay',$tmplcontent,m_url('admin/order/yuyueorder', $aid),$order['mdid'],$tmplcontentNew);
        $tmplcontent['first'] = '恭喜您的订单已支付成功';
        $rs = \app\commons\Wechat::sendtmpl($aid,$order['mid'],'tmpl_orderpay',$tmplcontent,m_url('activity/yuyue/orderlist', $aid),$tmplcontentNew);

        $tmplcontent = [];
        $tmplcontent['thing11'] = $order['title'];
        $tmplcontent['character_string2'] = $order['ordernum'];
        $tmplcontent['phrase10'] = '已支付';
        $tmplcontent['amount13'] = $order['totalprice'].'元';
        $tmplcontent['thing27'] = $member['nickname'];
        \app\commons\Wechat::sendhtwxtmpl($aid,$order['bid'],'tmpl_orderpay',$tmplcontent,'admin/order/yuyueorder',$order['mdid']);

        //短信通知
        $rs = \app\commons\Sms::send($aid,$member['tel']?$member['tel']:$order['tel'],'tmpl_orderpay',['ordernum'=>$order['ordernum']]);
	}


	//预约尾款支付
	public static function yuyue_balance_pay($orderid){
		//var_dump($orderid);
	    $order = Db::name('yuyue_order')->where('id',$orderid)->find();
		$aid = $order['aid'];
        $member = Db::name('member')->where('id',$order['mid'])->find();
		if(!$order) return json(['status'=>0,'msg'=>'订单不存在']);
		if($order['balance_pay_status']!=1) return json(['status'=>0,'msg'=>'订单状态不符合']);
		if(getcustom('hmy_yuyue') && $order['sysOrderNo']){ //将订单同步到师傅app端
			//直接完成订单
			db('yuyue_order')->where(['aid'=>$order['aid'],'id'=>$orderid])->update(['status'=>3,'collect_time'=>time()]);
			$rs = \app\commons\Order::collect($order,'yuyue');
			Db::name('yuyue_worker_order')->where('id',$order['worker_orderid'])->update(['status'=>3,'endtime'=>time()]);
			\app\customs\Yuyue::payoff($order);
		}

        //发货信息录入 微信小程序+微信支付
        if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
            \app\commons\Order::wxShipping($order['aid'],$order,'yuyue');
        }
		//公众号通知 订单支付成功
		$tmplcontent = [];
		$tmplcontent['first'] = '尾款订单支付成功';
		$tmplcontent['remark'] = '点击进入查看~';
		$tmplcontent['keyword1'] = $member['nickname']; //用户名
		$tmplcontent['keyword2'] = $order['ordernum'];//订单号
		$tmplcontent['keyword3'] = $order['totalprice'].'元';//订单金额
		$tmplcontent['keyword4'] = $order['title'];//商品信息
        $tmplcontentNew = [];
        $tmplcontentNew['thing8'] = \app\commons\Mendian::getNameWithBusines($order);//门店
        $tmplcontentNew['phrase18'] = $member['nickname']; //用户名
        $tmplcontentNew['character_string2'] = $order['ordernum'];//订单号
        $tmplcontentNew['amount5'] = $order['totalprice']==0?'0.00':$order['totalprice'];//订单金额
        $tmplcontentNew['thing3'] = $order['title'];//商品信息
		\app\commons\Wechat::sendhttmpl($aid,$order['bid'],'tmpl_orderpay',$tmplcontent,m_url('admin/order/yuyueorder', $aid),$order['mdid'],$tmplcontentNew);
		$tmplcontent['first'] = '恭喜您的订单已支付成功';
		$rs = \app\commons\Wechat::sendtmpl($aid,$order['mid'],'tmpl_orderpay',$tmplcontent,m_url('activity/yuyue/orderlist', $aid),$tmplcontentNew);

		$tmplcontent = [];
		$tmplcontent['thing11'] = $order['title'];
		$tmplcontent['character_string2'] = $order['ordernum'];
		$tmplcontent['phrase10'] = '已支付';
		$tmplcontent['amount13'] = $order['totalprice'].'元';
		$tmplcontent['thing27'] = $member['nickname'];
		\app\commons\Wechat::sendhtwxtmpl($aid,$order['bid'],'tmpl_orderpay',$tmplcontent,'admin/order/yuyueorder',$order['mdid']);

		//短信通知
		$rs = \app\commons\Sms::send($aid,$member['tel']?$member['tel']:$order['tel'],'tmpl_orderpay',['ordernum'=>$order['ordernum']]);
	}
    public function yuyue_workerapply_pay($orderid){
        $order = Db::name('yuyue_workerapply_order')->where('id',$orderid)->find();
        $info = Db::name('yuyue_worker')->where('id',$order['bid'])->find();
        //入驻成功给管理员发通知
        $tmplcontent = [];
        $tmplcontent['first'] = '有师傅申请成功';
        $tmplcontent['remark'] = '请登录后台，查看申请详情~';
        $tmplcontent['keyword1'] = '预约师傅申请';
        $tmplcontent['keyword2'] = date('Y-m-d H:i');
        $tempconNew = [];
        $tempconNew['thing3'] = '预约师傅申请';//报名名称
        $tempconNew['time5'] = date('Y-m-d H:i');//申请时间
        \app\commons\Wechat::sendhttmpl(aid,$info['bid'],'tmpl_formsub',$tmplcontent,'',0,$tempconNew);
    }

    //预约补余款支付
    public static function yuyue_addmoney_pay($orderid){
        //var_dump($orderid);
        $order = Db::name('yuyue_order')->where('id',$orderid)->find();
        $aid = $order['aid'];
        $member = Db::name('member')->where('id',$order['mid'])->find();
        if(!$order) return json(['status'=>0,'msg'=>'订单不存在']);
        if($order['addmoneyStatus']!=1) return json(['status'=>0,'msg'=>'订单状态不符合']);

        //发货信息录入 微信小程序+微信支付
        if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
            \app\commons\Order::wxShipping($order['aid'],$order,'yuyue');
        }
        //公众号通知 订单支付成功
        /*$tmplcontent = [];
        $tmplcontent['first'] = '补余款订单支付成功';
        $tmplcontent['remark'] = '点击进入查看~';
        $tmplcontent['keyword1'] = $member['nickname']; //用户名
        $tmplcontent['keyword2'] = $order['ordernum'];//订单号
        $tmplcontent['keyword3'] = $order['addmoney'].'元';//订单金额
        $tmplcontent['keyword4'] = $order['title'];//商品信息
        \app\commons\Wechat::sendhttmpl($aid,$order['bid'],'tmpl_orderpay',$tmplcontent,m_url('admin/order/yuyueorder', $aid),$order['mdid']);
        $tmplcontent['first'] = '恭喜您的订单已支付成功';
        $rs = \app\commons\Wechat::sendtmpl($aid,$order['mid'],'tmpl_orderpay',$tmplcontent,m_url('activity/yuyue/orderlist', $aid));

        $tmplcontent = [];
        $tmplcontent['thing11'] = $order['title'];
        $tmplcontent['character_string2'] = $order['ordernum'];
        $tmplcontent['phrase10'] = '已支付';
        $tmplcontent['amount13'] = $order['addmoney'].'元';
        $tmplcontent['thing27'] = $member['nickname'];
        \app\commons\Wechat::sendhtwxtmpl($aid,$order['bid'],'tmpl_orderpay',$tmplcontent,'admin/order/yuyueorder',$order['mdid']);
        */
        //短信通知
        $rs = \app\commons\Sms::send($aid,$member['tel']?$member['tel']:$order['tel'],'tmpl_orderpay',['ordernum'=>$order['ordernum']]);
    }

	//课程支付
	public static function kecheng_pay($orderid){
		//var_dump($orderid);
	    $order = Db::name('kecheng_order')->where('id',$orderid)->find();
        $aid = $order['aid'];
		//增加学习人数
		Db::name('kecheng_list')->where('aid',$order['aid'])->where('id',$order['kcid'])->inc('join_num')->update();
		if(!$order) return json(['status'=>0,'msg'=>'订单不存在']);
		if($order['status']!=1 && $order['status']!=12) return json(['status'=>0,'msg'=>'订单状态不符合']);
        $member = Db::name('member')->where('aid',$order['aid'])->where('id',$order['mid'])->find();

        //发货信息录入 微信小程序+微信支付
        if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
            \app\commons\Order::wxShipping($order['aid'],$order,'kecheng');
        }

        if(getcustom('kecheng_lecturer')){
            if($order['lecturerid']>0){
                //平台权限给讲师发放分佣
                $admin_user = Db::name('admin_user')->where('aid',$aid)->where('isadmin','>',0)->field('auth_type,auth_data')->find();
                if($admin_user){
                    $cansend = true;
                    if($admin_user['auth_type'] !=1){
                        $admin_auth = !empty($admin_user['auth_data'])?json_decode($admin_user['auth_data'],true):[];
                        if($admin_user['groupid']){
                            $admin_auth = Db::name('admin_user_group')->where('id',$admin_user['groupid'])->value('auth_data');
                        }
                        if(!$admin_auth || !in_array('KechengLecturerList/index,KechengLecturerList/*',$admin_auth)){
                            $cansend = false;
                        }
                    }
                    if($cansend){
                        //查询关联的课程讲师
                        $lmember = Db::name('member')
                            ->alias('m')
                            ->join('kecheng_lecturer lecturer','lecturer.mid = m.id')
                            ->where('lecturer.id',$order['lecturerid'])
                            ->where('lecturer.checkstatus',1)
                            ->where('lecturer.commissionratio','>',0)
                            ->where('lecturer.mid','>',0)
                            ->field('m.id,lecturer.commissionratio')->find();
                        if($lmember){
                            $sendmoney = $order['totalprice'] * $lmember['commissionratio'] * 0.01;
                            $sendmoney = round($sendmoney,2);
                            $updata = [];
                            $updata['lecturer_mid'] = $lmember['id'];
                            $updata['lecturer_commissionratio'] = $lmember['commissionratio'];
                            if($sendmoney){
                                \app\commons\Member::addmoney($order['aid'],$lmember['id'],$sendmoney,'用户购买课程：'.$order['title'].'，订单号'.$order['ordernum'].'增加');
                                $updata['lecturer_sendmoney'] = $sendmoney;
                            }
                            //更新发放分佣
                            Db::name('kecheng_order')->where('id',$orderid)->update($updata);
                        }
                    }
                }
            }
        }
		//公众号通知 订单支付成功
		$tmplcontent = [];
		$tmplcontent['first'] = '有新课程订单支付成功';
		$tmplcontent['remark'] = '点击进入查看~';
		$tmplcontent['keyword1'] = $member['nickname']; //用户名
		$tmplcontent['keyword2'] = $order['ordernum'];//订单号
		$tmplcontent['keyword3'] = $order['price'].'元';//订单金额
		$tmplcontent['keyword4'] = $order['title'];//商品信息
        $tmplcontentNew = [];
        $tmplcontentNew['thing8'] = \app\commons\Mendian::getNameWithBusines($order);//门店
        $tmplcontentNew['phrase18'] = $member['nickname']; //用户名
        $tmplcontentNew['character_string2'] = $order['ordernum'];//订单号
        $tmplcontentNew['amount5'] = $order['price']==0?'0.00':$order['price'];//订单金额
        $tmplcontentNew['thing3'] = $order['title'];//商品信息
		\app\commons\Wechat::sendhttmpl($aid,$order['bid'],'tmpl_orderpay',$tmplcontent,m_url('admin/order/kechengorder', $aid),$order['mdid'],$tmplcontentNew);
		$tmplcontent['first'] = '恭喜您的订单已支付成功';
		$rs = \app\commons\Wechat::sendtmpl($aid,$order['mid'],'tmpl_orderpay',$tmplcontent,m_url('activity/kecheng/orderlist?bid='.$order['bid'], $aid),$tmplcontentNew);

		$tmplcontent = [];
		$tmplcontent['thing11'] = $order['title'];
		$tmplcontent['character_string2'] = $order['ordernum'];
		$tmplcontent['phrase10'] = '已支付';
		$tmplcontent['amount13'] = $order['totalprice'].'元';
		$tmplcontent['thing27'] = $member['nickname'];
		\app\commons\Wechat::sendhtwxtmpl($aid,$order['bid'],'tmpl_orderpay',$tmplcontent,'admin/order/kechengorder',$order['mdid']);

		//短信通知
		$rs = \app\commons\Sms::send($aid,$member['tel']?$member['tel']:$order['tel'],'tmpl_orderpay',['ordernum'=>$order['ordernum']]);
	    \app\commons\Order::collect($order,'kecheng');
	}


	//团购
	public static function tuangou_pay($orderid){
		$order = Db::name('tuangou_order')->where('id',$orderid)->find();
		$member = Db::name('member')->where('id',$order['mid'])->find();
		$aid = $order['aid'];
		$mid = $order['mid'];
        //同城配送
        if($order['freight_type'] == 2){
            if(getcustom('express_maiyatian_autopush')) {
                //麦芽田同城配送自动推送
                \app\customs\MaiYaTianCustom::auto_push($aid,$orderid,$order,'tuangou_order');
            }
        }
		//自动发货
		if($order['freight_type']==3){
			Db::name('tuangou_order')->where('id',$order['id'])->update(['status'=>2,'send_time'=>time()]);

            //发货信息录入 微信小程序+微信支付
            if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
                \app\commons\Order::wxShipping($order['aid'],$order,'tuangou');
            }
		}
		//在线卡密
		if($order['freight_type']==4){
			$codelist = Db::name('tuangou_codelist')->where('proid',$order['proid'])->where('status',0)->order('id')->limit($order['num'])->select()->toArray();
			if($codelist && count($codelist) >= $order['num']){
				$pscontent = [];
				foreach($codelist as $codeinfo){
					$pscontent[] = $codeinfo['content'];
					Db::name('tuangou_codelist')->where('id',$codeinfo['id'])->update(['orderid'=>$order['id'],'ordernum'=>$order['ordernum'],'headimg'=>$member['headimg'],'nickname'=>$member['nickname'],'buytime'=>time(),'status'=>1]);
				}
				$pscontent = implode("\r\n",$pscontent);
				Db::name('tuangou_order')->where('id',$order['id'])->update(['freight_content'=>$pscontent,'status'=>2,'send_time'=>time()]);
			}

            //发货信息录入 微信小程序+微信支付
            if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
                \app\commons\Order::wxShipping($order['aid'],$order,'tuangou');
            }
		}
		//支付后送券
		$couponlist = \app\commons\Coupon::getpaygive($aid,$mid,'tuangou',$order['totalprice']);
		if($couponlist){
			foreach($couponlist as $coupon){
				\app\commons\Coupon::send($aid,$mid,$coupon['id']);
			}
		}
		\app\commons\Wifiprint::print($aid,'tuangou',$order['id']);
		//公众号通知 订单支付成功
		$tmplcontent = [];
		$tmplcontent['first'] = '有新团购订单支付成功';
		$tmplcontent['remark'] = '点击进入查看~';
		$tmplcontent['keyword1'] = $member['nickname']; //用户名
		$tmplcontent['keyword2'] = $order['ordernum'];//订单号
		$tmplcontent['keyword3'] = $order['totalprice'].'元';//订单金额
		$tmplcontent['keyword4'] = $order['title'];//商品信息
        $tmplcontentNew = [];
        $tmplcontentNew['thing8'] = \app\commons\Mendian::getNameWithBusines($order);//门店
        $tmplcontentNew['phrase18'] = $member['nickname']; //用户名
        $tmplcontentNew['character_string2'] = $order['ordernum'];//订单号
        $tmplcontentNew['amount5'] = $order['totalprice']==0?'0.00':$order['totalprice'];//订单金额
        $tmplcontentNew['thing3'] = $order['title'];//商品信息
		\app\commons\Wechat::sendhttmpl($aid,$order['bid'],'tmpl_orderpay',$tmplcontent,m_url('admin/order/tuangouorder', $aid),$order['mdid'],$tmplcontentNew);
		$tmplcontent['first'] = '恭喜您的订单已支付成功';
		$rs = \app\commons\Wechat::sendtmpl($aid,$mid,'tmpl_orderpay',$tmplcontent,m_url('activity/tuangou/orderlist', $aid),$tmplcontentNew);

		$tmplcontent = [];
		$tmplcontent['thing11'] = $order['title'];
		$tmplcontent['character_string2'] = $order['ordernum'];
		$tmplcontent['phrase10'] = '已支付';
		$tmplcontent['amount13'] = $order['totalprice'].'元';
		$tmplcontent['thing27'] = $member['nickname'];
		\app\commons\Wechat::sendhtwxtmpl($aid,$order['bid'],'tmpl_orderpay',$tmplcontent,'admin/order/tuangouorder',$order['mdid']);

		//短信通知
		$rs = \app\commons\Sms::send($aid,$member['tel']?$member['tel']:$order['tel'],'tmpl_orderpay',['ordernum'=>$order['ordernum']]);

		$set = Db::name('admin_set')->where('aid',$aid)->find();
		if($set['fxjiesuantime'] == 1 && $set['fxjiesuantime_delaydays'] == '0'){
			\app\commons\Order::giveCommission($order,'tuangou');
		}
	}

	//约课服务支付
	public static function yueke_pay($orderid){
        $order = Db::name('yueke_order')->where('id',$orderid)->find();
        $aid = $order['aid'];
        $member = Db::name('member')->where('id',$order['mid'])->find();
        if(!$order) return json(['status'=>0,'msg'=>'订单不存在']);
        if($order['status']!=1 && $order['status']!=12) return json(['status'=>0,'msg'=>'订单状态不符合']);
        $workerinfo = Db::name('yueke_worker')->where('aid',$aid)->where('workerid',$order['workerid'])->find();

        //发货信息录入 微信小程序+微信支付
        if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
            \app\commons\Order::wxShipping($order['aid'],$order,'yueke');
        }

        //公众号通知 订单支付成功
        $tmplcontent = [];
        $tmplcontent['first'] = '有新预约订单支付成功';
        $tmplcontent['remark'] = '点击进入查看~';
        $tmplcontent['keyword1'] = $member['nickname']; //用户名
        $tmplcontent['keyword2'] = $order['ordernum'];//订单号
        $tmplcontent['keyword3'] = $order['totalprice'].'元';//订单金额
        $tmplcontent['keyword4'] = $order['title'];//商品信息
        $tmplcontentNew = [];
        $tmplcontentNew['thing8'] = \app\commons\Mendian::getNameWithBusines($order);//门店
        $tmplcontentNew['phrase18'] = $member['nickname']; //用户名
        $tmplcontentNew['character_string2'] = $order['ordernum'];//订单号
        $tmplcontentNew['amount5'] = $order['totalprice']==0?'0.00':$order['totalprice'];//订单金额
        $tmplcontentNew['thing3'] = $order['title'];//商品信息
        \app\commons\Wechat::sendhttmpl($aid,$order['bid'],'tmpl_orderpay',$tmplcontent,m_url('admin/order/yuekeorder', $aid),$order['mdid'],$tmplcontentNew);
        if($workerinfo && $workerinfo['mid']){
            $rs = \app\commons\Wechat::sendtmpl($aid,$workerinfo['mid'],'tmpl_orderpay',$tmplcontent,m_url('pagesExt/yueke/workerorderlist', $aid),$tmplcontentNew);
        }
        $tmplcontent['first'] = '恭喜您的订单已支付成功';
        $rs = \app\commons\Wechat::sendtmpl($aid,$order['mid'],'tmpl_orderpay',$tmplcontent,m_url('pagesExt/yueke/orderlist', $aid),$tmplcontentNew);

        $tmplcontent = [];
        $tmplcontent['thing11'] = $order['title'];
        $tmplcontent['character_string2'] = $order['ordernum'];
        $tmplcontent['phrase10'] = '已支付';
        $tmplcontent['amount13'] = $order['totalprice'].'元';
        $tmplcontent['thing27'] = $member['nickname'];
        \app\commons\Wechat::sendhtwxtmpl($aid,$order['bid'],'tmpl_orderpay',$tmplcontent,'admin/order/yuekeorder',$order['mdid']);

        //短信通知
        $rs = \app\commons\Sms::send($aid,$member['tel']?$member['tel']:$order['tel'],'tmpl_orderpay',['ordernum'=>$order['ordernum']]);
	}

	public function business_recharge_pay($orderid){
		$order = Db::name('business_recharge_order')->where('id',$orderid)->find();
		$info = Db::name('business')->where('id',$order['bid'])->find();
		\app\commons\Business::addmoney($order['aid'],$order['bid'],$order['money'],t('余额').'充值');

        //发货信息录入 微信小程序+微信支付
        if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
            \app\commons\Order::wxShipping($order['aid'],$order,'business_recharge');
        }
		//\app\commons\System::plog('给商户充值'.$order['bid']);
	}
	//元宝 更新shop_order和payorder
    private static function yuanbao_up($type,$payorder){
        //元宝支付
        if(getcustom('pay_yuanbao') && $type == 'shop'){
            //如果是元宝支付，则需要重置支付金额
            if($payorder['is_yuanbao_pay'] == 1){
                //更新pay_order
                $up_pay = Db::name('payorder')->where('id',$payorder['id'])->update(['money'=>$payorder['yuanbao_money']]);

                //查询订单
                $order = Db::name('shop_order')->where('id',$payorder['orderid'])->field('aid,ordernum,mid,total_yuanbao')->find();
                if($order){
                    //更新shop_order
                    $up_order = Db::name('shop_order')->where('id',$payorder['orderid'])->update(['is_yuanbao_pay'=>1,'yuanbao_money'=>$payorder['yuanbao_money'],'totalprice'=>$payorder['yuanbao_money']]);
                    //更新商品id
                   	$sel_goods = Db::name('shop_order_goods')
                   		->where('orderid',$payorder['orderid'])
                   		->select()
                   		->toArray();
                   	if($sel_goods){
                   		//查询系统设置
                   		$sysset = Db::name('admin_set')->where('aid',$order['aid'])->find();
                   		//查询商城设置
                   		$shopset = Db::name('shop_sysset')->where('aid',$order['aid'])->field('showjd,comment,showcommission,hide_sales,hide_stock,show_lvupsavemoney')->find();
                   		//查询下单者
                   		$member  = Db::name('member')->where('id',$order['mid'])->find();
				        self::deal_commission($order['aid'],$order,$sel_goods,$sysset,$shopset,$member);
                   	}
                }
                //扣除元宝
                  \app\commons\Member::addyuanbao($order['aid'],$order['mid'],-$order['total_yuanbao'],'支付订单：'.$order['ordernum']);
            }
        }
    }
    //元宝转账
    private static function member_yuanbao_transfer_pay($id){
        //元宝支付
        if(getcustom('pay_yuanbao')){
            //查询订单
            $order = Db::name('member_yuanbao_transfer_order')->where('id',$id)->field('aid,mid,to_mid,money,yuanbao,parent1,parent2,parent3,parent1commission,parent2commission,parent3commission,iscommission')->find();
            $aid = $order['aid'];
            if($order){
                $member     = Db::name('member')->where('id',$order['mid'])->field('nickname')->find();
                $to_member  = Db::name('member')->where('id',$order['to_mid'])->field('nickname')->find();
                if($member){
                    //直接转账
                    $rs = \app\commons\Member::addyuanbao($aid,$order['to_mid'],$order['yuanbao'],sprintf("来自%s的".t('元宝')."转赠", $member["nickname"]));
                    if ($rs['status'] == 1) {
                        \app\commons\Member::addyuanbao($aid,$order['mid'],$order['yuanbao'] * -1, sprintf(t('元宝')."转赠给：%s",$to_member['nickname']));
                    }
                }

                if($order['iscommission'] != 1){
                    $totalcommission = 0;
                    //发奖
                    if($order['parent1'] && $order['parent1commission'] > 0){
                        $totalcommission+=$order['parent1commission'];
                        \app\commons\Member::addcommission($aid,$order['parent1'],$order['mid'],$order['parent1commission'],t('下级').''.t('元宝').'转账奖励');
                    }
                    if($order['parent2'] && $order['parent2commission'] > 0){
                        $totalcommission+=$order['parent2commission'];
                        \app\commons\Member::addcommission($aid,$order['parent2'],$order['mid'],$order['parent2commission'],t('下二级').''.t('元宝').'转账奖励');
                    }
                    if($order['parent3'] && $order['parent3commission'] > 0){
                        $totalcommission+=$order['parent3commission'];
                        \app\commons\Member::addcommission($aid,$order['parent3'],$order['mid'],$order['parent3commission'],t('下三级').''.t('元宝').'转账奖励');
                    }

                    //更新发佣金状态
                    $up = Db::name('member_yuanbao_transfer_order')->where('id',$id)->update(['iscommission'=>1]);
                }
            }
        }
    }
    //重新计算佣金
    private static function deal_commission($aid,$order,$sel_goods,$sysset,$shopset,$member){
        //元宝支付
        if(getcustom('pay_yuanbao')){
            if($sysset){
                $yuanbao_money_ratio = $sysset['yuanbao_money_ratio']/100;
            }else{
                $yuanbao_money_ratio  = 0;
            }
            foreach($sel_goods as $ogdata){

                //计算商品元宝现金价格
                $yuanbao_money = $ogdata['total_yuanbao']*$yuanbao_money_ratio;
                $yuanbao_money = round($yuanbao_money,2);
                //更新商品金额
                $up_goods = Db::name('shop_order_goods')->where('id',$ogdata['id'])->update(['yuanbao_money'=>$yuanbao_money,'totalprice'=>$yuanbao_money,'real_totalprice'=>$yuanbao_money]);


                //删除之前的会员佣金记录
                Db::name('member_commission_record')->where('orderid',$ogdata['orderid'])->where('ogid',$ogdata['id'])->delete();

                //查询规格
                $guige = Db::name('shop_guige')->where('aid',$aid)->where('id',$ogdata['ggid'])->find();

                //实际支付价格
                $og_totalprice = $yuanbao_money;

                //数量
                $num = $ogdata['num'];
                //佣金总价格
                $commission_totalprice   = 0;

                if($sysset['fxjiesuantype']==1){ //按成交价格
					$commission_totalprice = $yuanbao_money;
					if($commission_totalprice < 0){
						$commission_totalprice = 0;
					}
				}
				if($sysset['fxjiesuantype']==2){ //按销售利润
					$commission_totalprice = $yuanbao_money - $guige['cost_price'] * $num;
					if($commission_totalprice < 0) {
						$commission_totalprice = 0;
					}
				}
                $commission_totalpriceCache = $commission_totalprice;

                $ogupdate = [];
                $ogupdate['parent1'] = 0;
            	$ogupdate['parent2'] = 0;
            	$ogupdate['parent3'] = 0;
            	$ogupdate['parent4'] = 0;

            	$ogupdate['parent1commission'] = 0;
            	$ogupdate['parent2commission'] = 0;
            	$ogupdate['parent3commission'] = 0;
            	$ogupdate['parent4commission'] = 0;

            	$ogupdate['parent1score'] = 0;
            	$ogupdate['parent2score'] = 0;
            	$ogupdate['parent3score'] = 0;

            	$ogupdate['hongbaoEdu'] = 0;
            	$ogupdate['business_total_money'] = 0;
            	//自己是否拿一级分成
                $agleveldata = Db::name('member_level')->where('aid',$aid)->where('id',$member['levelid'])->find();
                if($agleveldata['can_agent'] > 0 && $agleveldata['commission1own']==1){
                    $member['pid'] = $ogdata['mid'];
                }

                //查询商品
                $product = Db::name('shop_product')->where('id',$ogdata['proid'])->find();

                if($product['bid'] > 0) {

                	$store_info = Db::name('business')->where('aid',$aid)->where('id',$product['bid'])->find();

                    $totalprice_business = $og_totalprice;
                    //商品独立费率
                    if($product['feepercent'] != '' && $product['feepercent'] != null && $product['feepercent'] >= 0) {
                        $ogupdate['business_total_money'] = $totalprice_business * (100-$product['feepercent']) * 0.01;
                        if(getcustom('business_deduct_cost')){
                            if($store_info && $store_info['deduct_cost'] == 1){
                                if($ogdata['cost_price']<=$ogdata['sell_price']){
                                    $all_cost_price = $ogdata['cost_price'];
                                }else{
                                    $all_cost_price = $ogdata['sell_price'];
                                }
                                //扣除成本
                                $ogupdate['business_total_money'] = $totalprice_business - ($totalprice_business-$all_cost_price)*$product['feepercent']/100;
                            }
                        }
                    } else {
                        //商户费率
                        $ogupdate['business_total_money'] = $totalprice_business * (100-$store_info['feepercent']) * 0.01;
                        if(getcustom('business_deduct_cost')){
                            if($store_info && $store_info['deduct_cost'] == 1){
                                if($ogdata['cost_price']<=$ogdata['sell_price']){
                                    $all_cost_price = $ogdata['cost_price'];
                                }else{
                                    $all_cost_price = $ogdata['sell_price'];
                                }
                                //扣除成本
                                $ogupdate['business_total_money'] = $totalprice_business - ($totalprice_business-$all_cost_price)*$store_info['feepercent']/100;
                            }
                        }
                    }

                }

                if($product['commissionset']!=-1){

                    if($member['pid']){
                        $parent1 = Db::name('member')->where('aid',$aid)->where('id',$member['pid'])->find();
                        if($parent1){
                            $agleveldata1 = Db::name('member_level')->where('aid',$aid)->where('id',$parent1['levelid'])->find();
                            if($agleveldata1['can_agent']!=0){
                                $ogupdate['parent1'] = $parent1['id'];
                            }
                        }
                    }
                    if($parent1['pid']){
                        $parent2 = Db::name('member')->where('aid',$aid)->where('id',$parent1['pid'])->find();
                        if($parent2){
                            $agleveldata2 = Db::name('member_level')->where('aid',$aid)->where('id',$parent2['levelid'])->find();
                            if($agleveldata2['can_agent']>1){
                                $ogupdate['parent2'] = $parent2['id'];
                            }
                        }
                    }
                    if($parent2['pid']){
                        $parent3 = Db::name('member')->where('aid',$aid)->where('id',$parent2['pid'])->find();
                        if($parent3){
                            $agleveldata3 = Db::name('member_level')->where('aid',$aid)->where('id',$parent3['levelid'])->find();
                            if($agleveldata3['can_agent']>2){
                                $ogupdate['parent3'] = $parent3['id'];
                            }
                        }
                    }
                    if($parent3['pid']){
                        $parent4 = Db::name('member')->where('aid',$aid)->where('id',$parent3['pid'])->find();
                        if($parent4){
                            $agleveldata4 = Db::name('member_level')->where('aid',$aid)->where('id',$parent4['levelid'])->find();
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
                            if(getcustom('plug_ttdz') && $ogdata['isfg'] == 1){
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
                            if(getcustom('plug_ttdz') && $ogdata['isfg'] == 1){
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
                            if(getcustom('plug_ttdz') && $ogdata['isfg'] == 1){
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
                    Db::name('shop_order_goods')->where('id',$ogdata['id'])->update($ogupdate);
                }

                if($product['commissionset4']==1 && $product['lvprice']==1){ //极差分销
                    if($member['path']){
                        $parentList = Db::name('member')->where('id','in',$member['path'])->order(Db::raw('field(id,'.$member['path'].')'))->select()->toArray();
                        if($parentList){
                            $parentList   = array_reverse($parentList);
                            $lvprice_data = json_decode($guige['lvprice_data'],true);
                            $nowprice     = $commission_totalpriceCache;
                            $giveidx      = 0;
                            foreach($parentList as $k=>$parent){
                                if($parent['levelid'] && $lvprice_data[$parent['levelid']]){
                                    $thisprice = floatval($lvprice_data[$parent['levelid']]) * $num;
                                    if($nowprice > $thisprice){
                                        $commission = $nowprice - $thisprice;
                                        $nowprice = $thisprice;
                                        $giveidx++;
                                        //添加新的
                                        Db::name('member_commission_record')->insert(['aid'=>$aid,'mid'=>$parent['id'],'frommid'=>$ogdata['mid'],'orderid'=>$ogdata['orderid'],'ogid'=>$ogdata['id'],'type'=>'shop','commission'=>$commission,'score'=>0,'remark'=>t('下级').'购买商品差价','createtime'=>time()]);
                                    }
                                }
                            }
                        }
                    }
                }

                if($product['commissionset']!=4){
                    if(getcustom('plug_ttdz') && $ogdata['isfg'] == 1){
                        if($ogupdate['parent1'] && ($ogupdate['parent1commission'] || $ogupdate['parent1score'])){
                            Db::name('member_commission_record')->insert(['aid'=>$aid,'mid'=>$ogupdate['parent1'],'frommid'=>$ogdata['mid'],'orderid'=>$ogdata['orderid'],'ogid'=>$ogdata['id'],'type'=>'shop','commission'=>$ogupdate['parent1commission'],'score'=>$ogupdate['parent1score'],'remark'=>t('下级').'复购奖励','createtime'=>time()]);
                        }
                        if($ogupdate['parent2'] && ($ogupdate['parent2commission'] || $ogupdate['parent2score'])){
                            Db::name('member_commission_record')->insert(['aid'=>$aid,'mid'=>$ogupdate['parent2'],'frommid'=>$ogdata['mid'],'orderid'=>$ogdata['orderid'],'ogid'=>$ogdata['id'],'type'=>'shop','commission'=>$ogupdate['parent2commission'],'score'=>$ogupdate['parent2score'],'remark'=>t('下二级').'复购奖励','createtime'=>time()]);
                        }
                        if($ogupdate['parent3'] && ($ogupdate['parent3commission'] || $ogupdate['parent3score'])){
                            Db::name('member_commission_record')->insert(['aid'=>$aid,'mid'=>$ogupdate['parent3'],'frommid'=>$ogdata['mid'],'orderid'=>$ogdata['orderid'],'ogid'=>$ogdata['id'],'type'=>'shop','commission'=>$ogupdate['parent3commission'],'score'=>$ogupdate['parent3score'],'remark'=>t('下三级').'复购奖励','createtime'=>time()]);
                        }
                    }else{
                        if($ogupdate['parent1'] && ($ogupdate['parent1commission'] || $ogupdate['parent1score'])){
                            Db::name('member_commission_record')->insert(['aid'=>$aid,'mid'=>$ogupdate['parent1'],'frommid'=>$ogdata['mid'],'orderid'=>$ogdata['orderid'],'ogid'=>$ogdata['id'],'type'=>'shop','commission'=>$ogupdate['parent1commission'],'score'=>$ogupdate['parent1score'],'remark'=>t('下级').'购买商品奖励','createtime'=>time()]);
                        }
                        if($ogupdate['parent2'] && ($ogupdate['parent2commission'] || $ogupdate['parent2score'])){
                            Db::name('member_commission_record')->insert(['aid'=>$aid,'mid'=>$ogupdate['parent2'],'frommid'=>$ogdata['mid'],'orderid'=>$ogdata['orderid'],'ogid'=>$ogdata['id'],'type'=>'shop','commission'=>$ogupdate['parent2commission'],'score'=>$ogupdate['parent2score'],'remark'=>t('下二级').'购买商品奖励','createtime'=>time()]);
                        }
                        if($ogupdate['parent3'] && ($ogupdate['parent3commission'] || $ogupdate['parent3score'])){
                            Db::name('member_commission_record')->insert(['aid'=>$aid,'mid'=>$ogupdate['parent3'],'frommid'=>$ogdata['mid'],'orderid'=>$ogdata['orderid'],'ogid'=>$ogdata['id'],'type'=>'shop','commission'=>$ogupdate['parent3commission'],'score'=>$ogupdate['parent3score'],'remark'=>t('下三级').'购买商品奖励','createtime'=>time()]);
                        }
                        if($ogupdate['parent4'] && ($ogupdate['parent4commission'])){
                            Db::name('member_commission_record')->insert(['aid'=>$aid,'mid'=>$ogupdate['parent4'],'frommid'=>$ogdata['mid'],'orderid'=>$ogdata['orderid'],'ogid'=>$ogdata['id'],'type'=>'shop','commission'=>$ogupdate['parent4commission'],'score'=>0,'remark'=>'持续推荐奖励','createtime'=>time()]);
                        }
                    }
                    if($order['checkmemid'] && $commission_totalprice > 0){
                        $checkmember = Db::name('member')->where('aid',$aid)->where('id',$order['checkmemid'])->find();
                        if($checkmember){
                            $buyselect_commission = Db::name('member_level')->where('id',$checkmember['levelid'])->value('buyselect_commission');
                            $checkmemcommission = $buyselect_commission * $commission_totalprice * 0.01;
                            Db::name('member_commission_record')->insert(['aid'=>$aid,'mid'=>$checkmember['id'],'frommid'=>$ogdata['mid'],'orderid'=>$ogdata['orderid'],'ogid'=>$ogdata['id'],'type'=>'shop','commission'=>$checkmemcommission,'score'=>0,'remark'=>'购买商品时指定奖励','createtime'=>time()]);
                        }
                    }
                }

                if(getcustom('everyday_hongbao')) {

                    $hd = Db::name('hongbao_everyday')->where('aid', $aid)->find();
                    $hongbaoEdu = 0;
                    if($product['everyday_hongbao_bl'] === null) {
                        $hongbaoEdu = $og_totalprice * $hd['shop_product_hongbao_bl'] / 100;
                    } elseif($product['everyday_hongbao_bl'] > 0 ) {
                        $hongbaoEdu = $og_totalprice * $product['everyday_hongbao_bl'] / 100;
                    }
                    $hongbaoEdu = round($hongbaoEdu,2);
                    if($hongbaoEdu > 0){
                        Db::name('shop_order_goods')->where('id',$ogupdate['id'])->update(['hongbaoEdu' => $hongbaoEdu]);
                    }
                }
            }
            unset($sg_v);
        }

    }
    public static function cashier_pay($orderid){
//	    //如果是多商家且余额支付，则结算商家费用
//        $order = Db::name('cashier_order')->where('id',$orderid)->find();
//        $totalmoney = $order['totalprice'];
//        if($order['paytypeid']==1 && $order['bid']>0 && $totalmoney>0){
//            //商家费率
//            $feepercent = Db::name('business')->where('aid',$order['aid'])->where('id',$order['bid'])->value('feepercent');
//            if($feepercent){
//                $totalmoney = $totalmoney * (100-$feepercent) * 0.01;
//            }
//            //结算给商家
//            $res = \app\commons\Business::addmoney($order['aid'],$order['bid'],$totalmoney,'收银台收款');
//            if(!$res || $res['status']!=1){
//                \think\facade\Log::write('--------------商家结算失败--------------');
//                \think\facade\Log::write(json_encode($res));
//                \think\facade\Log::write('--------------商家结算失败--------------');
//            }
//        }
	    return true;
    }
    public static function dscj_pay($orderid){
        $order = Db::name('dscj_order')->where('id',$orderid)->find();
        //其他订单关闭
        $where = [];
        $where[] = ['status','=',0];
        $where[] = ['bid','=',$order['bid']];
        $where[] = ['aid','=',$order['aid']];
        $where[] = ['hid','=',$order['hid']];
        $where[] = ['mid','=',$order['mid']];
        Db::name('dscj_order')->where($where)->update(['status'=>4]);
        Db::name('dscj')->where('id',$order['hid'])->inc('joinnum',1)->update();
        return true;
    }
    //招聘置顶
    public static function zhaopin_top_pay($orderid){
	    if(getcustom('zhaopin')){
            $order = Db::name('zhaopin_top_order')->where('id',$orderid)->find();
            $zhaopinupdate  = [];
            if($order['status']==1 && $order['related_id']){
                $starttime = $order['paytime'];
                $endtimeS = $order['paytime'];
                $duration = $order['top_duration'];
                $durationTotal = 86400 * $duration;
                $relateinfo = Db::name('zhaopin')->where('aid',$order['aid'])->where('id',$order['related_id'])->find();
                if($relateinfo){
                    if($relateinfo['top_endtime'] && $relateinfo['top_endtime']>time()){
                        $starttime = $relateinfo['top_starttime'];
                        $endtimeS = $relateinfo['top_endtime'];
                    }
                    $zhaopinupdate['top_starttime'] = $starttime;
                    $zhaopinupdate['top_endtime'] = $endtimeS + $durationTotal;
                    $zhaopinupdate['top_feetype'] = $order['top_feetype'];
                    $zhaopinupdate['top_area'] = $order['top_area'];
//                    Db::name('zhaopin')->where('id',$order['related_id'])->update($zhaopinupdate);
                }
            }
            if($order['assurance_total']>0){
                //同步保证金订单
                $ordera = [];
                $ordera['ordernum'] = $order['ordernum'];
                $ordera['top_orderid'] = $order['id'];
                $ordera['totalprice'] = $order['assurance_total'];
                $ordera['createtime'] = time();
                $ordera['title'] = '担保招聘保证金';
                $ordera['aid'] = $order['aid'];
                $ordera['bid'] = $order['bid'];
                $ordera['mid'] = $order['mid'];
                $ordera['apply_id'] = $order['apply_id'];
                $ordera['status'] = 1;
                $ordera['paynum'] = $order['paynum'];
                $ordera['paytype'] = $order['paytype'];
                $ordera['paytime'] = $order['paytime'];
                Db::name('zhaopin_assurancefee_order')->insertGetId($ordera);

                //保证金累加
                Db::name('zhaopin_apply')->where('aid',$order['aid'])->where('id',$order['apply_id'])->update(['assurance_fee'=>Db::raw("assurance_fee+{$order['assurance_total']}")]);

                $data = [];
                $data['createtime'] = time();
                $data['aid'] = $order['aid'];
                $data['bid'] = $order['bid'];
                $data['mid'] = $order['mid'];
                $data['apply_id'] = $order['apply_id'];
                $data['zhaopin_id'] = $order['related_id'];
                $data['status'] = 1;//担保中
                $data['fee'] =  $order['assurance_total'];
                $data['remark'] =  '置顶担保招聘';
                $assurance_id = Db::name('zhaopin_assurance')->insertGetId($data);
                $zhaopinupdate['assurance_id'] = $assurance_id;
            }
            if($order['totalprice']>0){
                //发放奖励
                $givescore = \app\models\Zhaopin::getSetValue($order['aid'],'zhaopin','top_give_score',0);
                if($givescore>0){
                    \app\commons\Member::addscore($order['aid'],$order['mid'],$givescore,'招聘置顶奖励');
                }
            }
            if($zhaopinupdate){
                Db::name('zhaopin')->where('id',$order['related_id'])->update($zhaopinupdate);
            }
        }
        return true;
    }

    //vip
    public static function zhaopin_vip_pay($orderid){
        if(getcustom('zhaopin')){
            //其他未支付的订单删除
            $order = Db::name('zhaopin_vip_order')->where('id',$orderid)->find();
            Db::name('zhaopin_vip_order')->where('status',0)->where('mid',$order['mid'])->where('zhaopin_id',$order['zhaopin_id'])->delete();
            Db::name('zhaopin')->where('aid',$order['aid'])->where('id',$order['zhaopin_id'])->update(['vip_orderid'=>$orderid]);
            //vip_order
            Db::name('zhaopin_apply')->where('aid',$order['aid'])->where('mid',$order['mid'])->update(['vip_orderid'=>$orderid,'zhaopin_id'=>$order['zhaopin_id']]);
            return;
        }
        return true;
    }

    //求职置顶
    public static function zhaopin_qiuzhi_top_pay($orderid){
        if(getcustom('zhaopin')) {
            $order = Db::name('zhaopin_qiuzhi_top_order')->where('id', $orderid)->find();
            if ($order['status'] == 1 && $order['related_id']) {
                $starttime = $order['paytime'];
                $endtimeS = $order['paytime'];
                $duration = $order['top_duration'];
                $durationTotal = 86400 * ($duration + 1);
                $relateinfo = Db::name('zhaopin_qiuzhi')->where('aid', $order['aid'])->where('id', $order['related_id'])->find();
                if ($relateinfo) {
                    if ($relateinfo['top_endtime'] && $relateinfo['top_endtime']>time()) {
                        $starttime = $relateinfo['top_starttime'];
                        $endtimeS = $relateinfo['top_endtime'];
                    }
                    $update['top_starttime'] = $starttime;
                    $update['top_endtime'] = $endtimeS + $durationTotal;
                    $update['top_feetype'] = $order['top_feetype'];
                    $update['top_area'] = $order['top_area'];
                    Db::name('zhaopin_qiuzhi')->where('id', $order['related_id'])->update($update);
                }
            }
            //发放奖励
            $givescore = \app\models\Zhaopin::getSetValue($order['aid'],'qiuzhi','top_give_score',0);
            if($givescore>0){
                \app\commons\Member::addscore($order['aid'],$order['mid'],$givescore,'求职置顶奖励');
            }
        }
        return true;
    }
    //招聘置顶
    public static function zhaopin_assurancefee_pay($orderid){
        if(getcustom('zhaopin')){
            $order = Db::name('zhaopin_assurancefee_order')->where('id',$orderid)->find();
            if($order['apply_id']){
                //更新商家缴纳保证金的费用
                Db::name('zhaopin_apply')->where('aid',$order['aid'])->where('id',$order['apply_id'])->inc('assurance_fee',$order['totalprice'])->update();
            }
        }
        return true;
    }

    /**
     * @param $orderid
     * @param $type
     * @param $usecoupon_type 1付款后，2确认收货
     * @param $ordernum
     * @return bool
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function afterusecoupon($orderid,$type,$usecoupon_type=1,$ordernum='')
    {
        //所有使用优惠券的订单类型maidan = couponrid
        if(getcustom('usecoupon_give_score') || getcustom('usecoupon_give_coupon') || getcustom('usecoupon_give_money')){
            if(in_array($type,[
                'shop','shop_hb','yuyue','tuangou','seckill2','seckill',
                'restaurant_takeaway','restaurant_shop','restaurant_booking',
                'maidan','lucky_collage','dscj','collage','cashier'
            ])){
                $couponOrders = [];//优惠券字段 全部转为coupon_rid
                //特殊处理的表
                if($type=='shop_hb'){
                    $couponOrders = Db::name('shop_order')->where('ordernum','like',$ordernum.'%')->select()->toArray();
                }elseif($type=='maidan'){
                    $couponOrders[] = Db::name('maidan_order')->where('id',$orderid)->field('*,couponrid coupon_rid')->find();
                }else{
                    $couponOrders[] = Db::name($type.'_order')->where('id',$orderid)->find();
                }
                if(empty($couponOrders)){
                    return true;
                }
                foreach ($couponOrders as $key=>$order){
                    if(!isset($order['coupon_rid']) || empty($order['coupon_rid'])){
                        continue;
                    }
                    \app\commons\Coupon::useCoupon($order['aid'],$order['coupon_rid'],$usecoupon_type);
                }
            }
        }

        return true;
    }

    public static function send_free_notice($payorder){
    	if(getcustom('invite_free')){
    		//查询免单设置
            $set = Db::name('invite_free')->where('aid',$payorder['aid'])->find();
    		if($set && $set['status'] ==1 && $set['start_time']<=time() && $set['end_time']>=time() && $payorder['aid'] && $payorder['aid'] == 10){
    			//查询订单
		        $order = Db::name('shop_order')->where('id',$payorder['orderid'])->field('id,ordernum,mid,title,totalprice,mdid')->find();
		        if($order){
		        	//查询他下单次数
		        	$count_num = Db::name('shop_order')
		        		->where('mid',$order['mid'])
		        		->where('status','>=',1)
		        		->where('status','<=',3)
		        		->count();
		        	if($count_num == 1){
		        		//查询他上级
		        		$member = Db::name('member')
		        			->where('id',$order['mid'])
		        			->field('pid')
		        			->find();
		        		if($member && $member['pid']>0){

							// $tmplcontent['keyword1'] = Db::name('admin_set')->where('aid',aid)->value('name'); //店铺
							// $tmplcontent['thing27'] = $this->member['nickname'];

		        			//查询通知设置
				        	$mp_tmplset = Db::name('mp_tmplset')->where('aid',$payorder['aid'])->field('tmpl_orderconfirm')->find();
				        	if($mp_tmplset && $mp_tmplset['tmpl_orderconfirm']){
				        		//公众号通知 发送有新伙伴下单
								$tmplcontent = [];
								$tmplcontent['first']    = '有新伙伴下单';
								$tmplcontent['remark']   = '点击进入查看~';
								$tmplcontent['keyword1'] = ''; //店铺
								$tmplcontent['keyword2'] = date('Y-m-d H:i:s',$payorder['paytime']);//下单时间
								$tmplcontent['keyword3'] = $order['title']?$order['title']:'';//商品
								$tmplcontent['keyword4'] = $order['totalprice']?$order['totalprice']:'';//金额
                                $tempconNew = [];
                                $tempconNew['character_string2'] = $order['ordernum'];//订单号
                                $tempconNew['thing8'] = '';//门店名称
                                $tempconNew['thing3'] = $order['title']?$order['title']:'';//商品
                                $tempconNew['amount7'] = $order['totalprice']?$order['totalprice']:'';//金额
                                $tempconNew['time4'] = date('Y-m-d H:i:s',$payorder['paytime']);//下单时间
								\app\commons\Wechat::sendtmpl($payorder['aid'],$member['pid'],'tmpl_orderconfirm',$tmplcontent,m_url('pagesExt/invite_free/index'),$tempconNew);
				        	}

				        	//查询通知设置
				        	$wx_tmplset = Db::name('wx_tmplset')->where('aid',$payorder['aid'])->field('tmpl_orderconfirm')->find();
				        	if($wx_tmplset && $wx_tmplset['tmpl_orderconfirm']){
								$tmplcontent = [];
								$tmplcontent['thing11']  = $order['title']?$order['title']:'';//商品
								$tmplcontent['character_string2'] = $order['ordernum']?$order['ordernum']:'';
								$tmplcontent['phrase10'] = '新伙伴下单';
								$tmplcontent['amount13'] =  $order['totalprice']?$order['totalprice']:'';//金额
								$tmplcontent['thing27']  = '';
								\app\commons\Wechat::sendwxtmpl($payorder['aid'],$member['pid'],'tmpl_orderconfirm',$tmplcontent,m_url('pagesExt/invite_free/index'),$order['mdid']);
							}
		        		}
		        	}
		        }
    		}

	    }
    }

    public static function xixie_pay($orderid){
        if(getcustom('xixie')){
            $order = Db::name('shop_order')->where('id',$orderid)->find();
            $member = Db::name('member')->where('id',$order['mid'])->find();
            $aid = $order['aid'];
            $mid = $order['mid'];
            Db::name('shop_order_goods')->where('orderid',$orderid)->update(['status'=>1]);
        }
    }

    //充值订单
    public static function xixie_vip_pay($orderid){
        if(getcustom('xixie')){
            $order = Db::name('xixie_vip_order')->where('id',$orderid)->find();
            $member = Db::name('member')->where('id',$order['mid'])->update(['is_vip'=>1]);
        }
    }

    public static function article_reward_pay($orderid){
        if(getcustom('article_reward')){
            $order = Db::name('article_reward_order')->where('id',$orderid)->find();
            if($order){
                //增加打赏
                \app\commons\Member::addmoney($order['aid'],$order['send_mid'],$order['num'],'文章打赏');
            }
        }
    }
	//支付后赠送活动
	public static function payaftergive($payorder){
		$aid = $payorder['aid'];
		$mid = $payorder['mid'];
		$bid = $payorder['bid'];
		$member = Db::name('member')->where('id',$payorder['mid'])->find();
		$payordertype = $payorder['type'];
		if($payordertype == 'shop_hb') $payordertype = 'shop';
		if($payordertype == 'restaurant_shop' || $payordertype == 'restaurant_takeaway' || $payordertype == 'restaurant_booking') $payordertype = 'restaurant';
		$where = [];
        $where[] = ['aid','=',$payorder['aid']];
        $where[] = ['bid','=',$payorder['bid']];
        if(getcustom('payaftergive_bind_bids')){
            $where[] = Db::raw("find_in_set({$payorder['bid']},`bind_bids`) OR ISNULL(bind_bids)");
        }
        if(getcustom('coupon_other_business')){
            $couponset = Db::name('coupon_set')->where('aid',$aid)->where('bid',$bid)->find();
            $showOtherBusinessCoupon = $couponset['show_other_bcoupon']??1;
            if($showOtherBusinessCoupon==0){
                //只展示该商家的赠送优惠券
                $where[] = ['bid','=',$bid];
            }
        }
        $payaftergivelist = Db::name('payaftergive')->where($where)->where('pricestart','<=',$payorder['money'])->where('priceend','>=',$payorder['money'])->where('starttime','<',time())->where('endtime','>',time())->whereRaw("find_in_set('".$payordertype."',paygive_scene)")->whereRaw("find_in_set('-1',gettj) or find_in_set('".$member['levelid']."',gettj)")->select()->toArray();

		foreach($payaftergivelist as $payaftergive){
			if($payaftergive['limittimes'] != 0){
				$hastimes = Db::name('payaftergive_record')->where('aid',$aid)->where('mid',$mid)->where('hid',$payaftergive['id'])->count();
				if($hastimes >= $payaftergive['limittimes']) continue;
			}
			
			$record = [];
			$record['aid'] = $aid;
			$record['mid'] = $mid;
			$record['hid'] = $payaftergive['id'];
			$record['name'] = $payaftergive['name'];
			$record['money'] = $payaftergive['money'];
			$record['score'] = $payaftergive['score'];
			$record['choujiangtimes'] = $payaftergive['choujiangtimes'];
			$record['choujiangid'] = $payaftergive['choujiangid'];
			$record['give_coupon'] = $payaftergive['give_coupon'];
			$record['coupon_ids'] = $payaftergive['coupon_ids'];
			$record['createtime'] = time();
			Db::name('payaftergive_record')->insert($record);

			if($payaftergive['money'] > 0){
				\app\commons\Member::addmoney($aid,$mid,$payaftergive['money'],$payaftergive['name']);
			}
			if($payaftergive['score'] > 0){
				\app\commons\Member::addscore($aid,$mid,$payaftergive['score'],$payaftergive['name']);
			}
			if($payaftergive['choujiangtimes'] > 0 && $payaftergive['choujiangid'] > 0){
				$sharelog = Db::name('choujiang_sharelog')->where('aid',$aid)->where('hid',$payaftergive['choujiangid'])->where('mid',$mid)->find();
				if($sharelog){
					Db::name('choujiang_sharelog')->where('id',$sharelog['id'])->inc('extratimes',$payaftergive['choujiangtimes'])->update();
				}else{
					$data = [];
					$data['aid'] = $aid;
					$data['hid'] = $payaftergive['choujiangid'];
					$data['mid'] = $mid;
					$data['extratimes'] = $payaftergive['choujiangtimes'];
					Db::name('choujiang_sharelog')->insert($data);
				}
			}
			if($payaftergive['give_coupon']==1 && $payaftergive['coupon_ids']){
                $coupon_ids = explode(',',$payaftergive['coupon_ids']);
                foreach ($coupon_ids as $couponid){
                    \app\commons\Coupon::send($aid,$mid,$couponid);
                }
            }
		}
	}

    public static function paotui_pay($orderid){
        if(getcustom('paotui')){
            //处理推送到哪个端
            $res = \app\customs\PaotuiCustom::deal_push($orderid);
            if($res['status']!=1){
                return json(['status'=>0,'msg'=>$res['msg']]);
            } 
        }
    }

    //百度AI绘画支付完成
    public static function imgai_pay($orderid){
        //放在 app\customs\BaiduAi 文件中处理了
        $order = Db::name('imgai_order')->where('id',$orderid)->find();
        if($order['able_time']>0){
            Db::name('member')->where('id',$order['mid'])->update(['imgai_time'=>$order['able_time']]);
        }
        //百度AI绘画处理
        $baidu_ai = new \app\customs\BaiduAi($order['aid']);
        $res = $baidu_ai->afterPay($orderid);
        return true;
    }
    //地图标注支付完成
    public static function mapmark_pay(){
        return true;
    }
    //短视频去水印支付完成
    public static function videospider_pay($orderid){
        $order = Db::name('videospider_order')->where('id',$orderid)->find();
        if($order['able_time']>0){
            Db::name('member')->where('id',$order['mid'])->update(['videospider_time'=>$order['able_time']]);
        }
       return true;
    }

    public static function tour_activity_pay($orderid){
        if(getcustom('extend_tour')){
            $order = Db::name('tour_activity_order')->where('id',$orderid)->find();
            $member = Db::name('member')->where('id',$order['mid'])->field('id,nickname')->find();
            $aid = $order['aid'];
            $mid = $order['mid'];

            //公众号通知 订单支付成功
            $tmplcontent = [];
            $tmplcontent['first'] = '恭喜您的订单已支付成功';
            $tmplcontent['remark'] = '点击进入查看~';
            $tmplcontent['keyword1'] = $member['nickname']; //用户名
            $tmplcontent['keyword2'] = $order['ordernum'];//订单号
            $tmplcontent['keyword3'] = $order['totalprice'].'元';//订单金额
            $tmplcontent['keyword4'] = $order['title'];//商品信息
            $tmplcontentNew = [];
            $tmplcontentNew['phrase18'] = $member['nickname']; //用户名
            $tmplcontentNew['character_string2'] = $order['ordernum'];//订单号
            $tmplcontentNew['amount5'] = $order['totalprice']==0?'0.00':$order['totalprice'];//订单金额
            $tmplcontentNew['thing3'] = $order['title'];//商品信息
            $rs = \app\commons\Wechat::sendtmpl($aid,$mid,'tmpl_orderpay',$tmplcontent,m_url('pagesA/tour/orderlist', $aid),$tmplcontentNew);

            $rs = \app\commons\Sms::send($aid,$member['tel'] ? $member['tel'] : $order['tel'],'tmpl_orderpay',['ordernum'=>$order['ordernum']]);
        }
    }

    public static function gift_bag_pay($orderid){
        if(getcustom('extend_gift_bag')){
           Db::name('gift_bag_order_goods')->where('orderid',$orderid)->update(['status'=>1,'paytime'=>time()]);
           return true;
       }
    }

    public static function lipin_pay($orderid){
        if(getcustom('lipinka_morefee') || getcustom('lipinka_freight_free')){
           $order = Db::name('lipin_order')->where('id',$orderid)->field('id,aid,mid,codeid,title,ordernum,type,paytype,paytypeid,paytime,platform')->find();
           if($order){
                $member = Db::name('member')->where('id',$order['mid'])->find();
                $updata = [
                    'status'=>1,
                    'usetime'=>time(),
                    'mid'=>$order['mid'],
                    'headimg'=>$member['headimg'],
                    'nickname'=>$member['nickname'],
                    'remark'=>'兑换商品:'.$order['title']
                ];
                Db::name('lipin_codelist')->where('id',$order['codeid'])->update($updata);
                if($order['type'] == 1){
                    //修改商品为已支付
                    Db::name('shop_order')->where('ordernum','like',$order['ordernum'].'%')->update(['status'=>1,'paytime'=>$order['paytime']]);
                }
                if($order['type'] == 4){
                    //修改积分商品为已支付
                     Db::name('scoreshop_order')->where('ordernum','like',$order['ordernum'].'%')->update(['status'=>1,'paytime'=>$order['paytime']]);
                }
               //发货信息录入 微信小程序+微信支付
               if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
                   \app\commons\Order::wxShipping($order['aid'],$order,'lipin');
               }
           }
       }
    }
    public static function article_resource_pay($orderid){
        $order = Db::name('article_resource_order')->where('id',$orderid)->find();
        $aid = $order['aid'];
        $mid = $order['mid'];
        $artid = $order['artid'];
        $detail = Db::name('article')->where('aid',$aid)->where('id',$artid)->find();
      	$sysset = Db::name('expert_sysset')->where('aid',$order['aid'])->find();
        $member = Db::name('member')->where('id',$detail['mid'])->find();
        if ($member) {
            $money = round($order['money']*(100-$sysset['default_rate'])*0.01,2);
            \app\commons\Member::addmoney($order['aid'],$detail['mid'],$money,'文章附件下载费'.$order['mid']);
        }
    	$data = [
            'aid' => $aid,
            'bid' => $detail['bid'],
            'mid' => $mid,
            'artid' => $detail['id'],
            'createtime' => time()
        ];
        $res = Db::name('article_resource')->insert($data);
        
    }
    public function expert_comment_pay($orderid){
	  
        $order = Db::name('expert_comment_order')->where('id',$orderid)->find();
        $aid = $order['aid'];
        $bid = $order['sid'];
       	Db::name('expert_comment_order')->where('id',$orderid)->update(['paystatus'=>1]);
       	$expert = Db::name('expert')->where('aid',$aid)->where('id',$bid)->find();
       	$sysset = Db::name('expert_sysset')->where('aid',$order['aid'])->find();
        $member = Db::name('member')->where('id',$expert['mid'])->find();
        if ($member) {
            $money = round($order['money']*(100-$sysset['default_rate'])*0.01,2);
            \app\commons\Member::addmoney($order['aid'],$expert['mid'],$money,'回复评论费用'.$order['mid']);
        }
	}
    public static function expert_deposit_pay($orderid){
       
        $order = Db::name('expert_deposit_order')->where('id',$orderid)->find();
        $aid = $order['aid'];
        $bid = $order['bid'];
        $expert = Db::name('expert')->where('aid',$aid)->where('id',$bid)->find();
        Db::name('expert')->where('aid',$aid)->where('id',$bid)->inc('deposit',$order['money'])->update();
        $data = [];
        $data['aid'] = $aid;
        $data['bid'] = $bid;
        $data['money'] = $order['money'];
        $data['after'] = $expert['deposit'] + $order['money'];
        $data['createtime'] = time();
        $data['remark'] = '保证金充值';
        $data['type'] = 'recharge';
        $data['ordernum'] = $order['ordernum'];
        Db::name('expert_depositlog')->insert($data);
    }
    public static function business_deposit_pay($orderid){
        if(getcustom('business_deposit')){
            $order = Db::name('business_deposit_order')->where('id',$orderid)->find();
            $aid = $order['aid'];
            $bid = $order['bid'];

            \app\commons\Business::updateDeposit($aid,$bid,$order['money'],'保证金充值','recharge',$order['ordernum']);
        }
    }

	public function baoming_xcx_pay($orderid){
	    if(getcustom('baoming_xcx')){
            $order = Db::name('baoming_xcx_order')->where('id',$orderid)->find();
            $aid = $order['aid'];
            $bid = $order['bid'];

           	Db::name('baoming_xcx_order')->where('id',$orderid)->update(['paystatus'=>1]);
        }
		
	}
	public function member_salelevel_pay($orderid){
	    if(getcustom('member_levelup_auth')){
            $order = Db::name('member_salelevel_order')->where('id',$orderid)->find();
            $aid = $order['aid'];
            $bid = $order['bid'];
			$zsuserlevel = Db::name('member_level')->where('aid',$aid)->where('id',$order['levelid'])->find();
			$member = Db::name('member')->where('id',$order['mid'])->find();

			\app\commons\Member::addmoney($aid,$order['from_mid'],$order['totalprice'],'推荐'.$member['nickname'].'购买'.$zsuserlevel['name']);

			//减掉赠送人的额度
            Db::name('member')->where('aid',aid)->where('id',$order['from_mid'])->dec('salelevel_money',$zsuserlevel['apply_paymoney'])->update();
			//增加领取人的额度
			//查看已经赠送了的额度
			$yzsed =  Db::name('member_salelevel_order')->where('aid',$aid)->where('from_mid',$order['mid'])->where('status',1)->sum('levelprice');
			$salelevel_money =  round($zsuserlevel['give_level_totalmoney']-$yzsed,2);
			if($salelevel_money>0){
				Db::name('member')->where('id',$order['mid'])->update(['salelevel_money'=>$salelevel_money]);
			}
			$rs = \app\commons\Member::handleUpLevel($aid,$order['mid'],$zsuserlevel,$member,$member,$zsuserlevel['cid']);
           
        }
		
	}
    //申请区域合伙人订单
    public static function region_partner_pay($orderid){
        if(getcustom('region_partner')){
            return true;
        }
    }
	public static function gift_pack_pay($orderid){
		 if(getcustom('yx_gift_pack')){
            $order = Db::name('gift_pack_order')->where('id',$orderid)->find();
            $aid = $order['aid'];
            $bid = $order['bid'];
			$member = Db::name('member')->where('id',$order['mid'])->find();
			if($order['givescore']>0){
				\app\commons\Member::addscore($aid,$order['mid'],$order['givescore'],'购买礼包赠送'.t('积分'));				
			}
			//优惠券赠送
			$coupon = explode(',',$order['couponids']);
			foreach($coupon as $k=>$v){
				\app\commons\Coupon::send($aid,$order['mid'],$v,true);
			}
            \app\commons\Order::giveCommission($order,'gift_pack');
        }
	}

    //商城订单
    public static function taocan_pay($orderid){
        if(getcustom('taocan_product')){
            $order = Db::name('taocan_order')->where('id',$orderid)->find();
            $member = Db::name('member')->where('id',$order['mid'])->find();
            $aid = $order['aid'];
            $mid = $order['mid'];
            $oglist = Db::name('taocan_order_goods')->where('orderid',$orderid)->select()->toArray();
            Db::name('taocan_order_goods')->where('orderid',$orderid)->update(['status'=>1]);
            //自动发货
            if($order['freight_type']==3){
                $og = Db::name('taocan_order_goods')->where('orderid',$order['id'])->find();
                $freight_content = Db::name('taocan_product')->where('id',$og['proid'])->value('freightcontent');
                Db::name('taocan_order')->where('id',$order['id'])->update(['freight_content'=>$freight_content,'status'=>2,'send_time'=>time()]);
                Db::name('taocan_order_goods')->where('orderid',$order['id'])->update(['status'=>2]);
            }
            if($order['platform'] == 'toutiao'){
                \app\commons\Ttpay::pushorder($aid,$order['ordernum'],1);
            }

            \app\commons\Member::uplv($aid,$mid);

        }
    }
	//签到订单
	public static function sign_pay($orderid){
		$order = Db::name('sign_order')->where('id',$orderid)->find();
		$rs = \app\commons\SignBonus::signin($order['aid'],$order);
		//var_dump($rs);
	}

	//商家打赏
    public static function business_reward_pay($orderid){
        if(getcustom('business_reward_member')){
            $order = Db::name('business_reward_order')->where('id',$orderid)->find();
            Db::name('business_reward_order')->where('id',$orderid)->update(['status'=>1]);
            $set = Db::name('business_reward_set')->where('aid',$order['aid'])->find();
            $sysset = Db::name('admin_set')->where('aid',$order['aid'])->find();
            $member = Db::name('member')->where('id',$order['to_mid'])->find();
            //增加被打赏会员积分
            \app\commons\Member::addscore($order['aid'],$order['to_mid'],$order['to_money'],'商家会员ID'.$order['mid'].'打赏');
            //增加商家会员积分
            \app\commons\Member::addscore($order['aid'],$order['mid'],$order['to_business_money'],'打赏会员ID'.$order['to_mid'].'奖励');
            //发放分销佣金
            $product = [
                'commissionset' => $set['commissionset'],
                'commissiondata1' => $set['commissiondata'],
                'fx_differential' => -1
            ];
            $commission_data = \app\commons\Fenxiao::fenxiao($sysset,$member,$product,1,$order['pay_money']);
            if($commission_data['parent1'] && $commission_data['parent1commission']>0){
                $data_c = [
                    'aid'=>$order['aid'],
                    'mid'=>$commission_data['parent1'],
                    'frommid'=>$order['to_mid'],
                    'orderid'=>$orderid,
                    'ogid'=>0,
                    'type'=>'business_reward',
                    'commission'=>$commission_data['parent1commission'],
                    'score'=>0,
                    'remark'=>'打赏一级奖励',
                    'createtime'=>time()
                ];

                Db::name('member_commission_record')->insert($data_c);
            }
            if($commission_data['parent2'] && $commission_data['parent2commission']>0){
                $data_c = [
                    'aid'=>$order['aid'],
                    'mid'=>$commission_data['parent2'],
                    'frommid'=>$order['to_mid'],
                    'orderid'=>$orderid,
                    'ogid'=>0,
                    'type'=>'business_reward',
                    'commission'=>$commission_data['parent2commission'],
                    'score'=>0,
                    'remark'=>'打赏二级奖励',
                    'createtime'=>time()
                ];

                Db::name('member_commission_record')->insert($data_c);
            }
            if($commission_data['parent3'] && $commission_data['parent3commission']>0){
                $data_c = [
                    'aid'=>$order['aid'],
                    'mid'=>$commission_data['parent3'],
                    'frommid'=>$order['to_mid'],
                    'orderid'=>$orderid,
                    'ogid'=>0,
                    'type'=>'business_reward',
                    'commission'=>$commission_data['parent3commission'],
                    'score'=>0,
                    'remark'=>'打赏三级奖励',
                    'createtime'=>time()
                ];

                Db::name('member_commission_record')->insert($data_c);
            }
            \app\commons\Order::giveCommission($order,'business_reward');
        }
    }

    //积分赠送手续费
    public static function score_transfer_pay($orderid){
        if(getcustom('score_transfer_sxf')){
            $order = Db::name('score_transfer_order')->where('id',$orderid)->find();
            if($order){
                // 1 已支付 0 待支付
                Db::name('score_transfer_order')->where('id',$orderid)->update(['status' => 1]);

                $nickname = Db::name('member')->where('id',$order['mid'])->value('nickname');

                \app\commons\Member::addscore($order['aid'],$order['receive_mid'],$order['score_num'],sprintf("来自%s的".t('积分')."转赠",$nickname), '', 0, $order['mid']);
            }
        }
    }
    //找到修改过单号但支付为非最新单号的订单（支付了老的单号，或者支付了以后又去触发了支付更新了单号），更新为支付的ordernum
    public static function changeOrdernumToPayOrdernum($payorder=[])
    {
        $change_log = Db::name('ordernum_change_log')->where('ordernum',$payorder['ordernum'])->where('orderid',$payorder['orderid'])->find();
        if($change_log){
            $neworder = Db::name($change_log['tablename'])->where('ordernum',$change_log['ordernum_new'])->where('orderid',$change_log['orderid'])->find();
            if($neworder){
                //只改了一次单号
                Db::name($change_log['tablename'])->where('ordernum',$change_log['ordernum_new'])->where('orderid',$change_log['orderid'])
                    ->update(['ordernum'=>$payorder['ordernum']]);
                $newlog = $change_log;
                unset($newlog['id']);
                $newlog['ordernum'] = $change_log['ordernum_new'];
                $newlog['ordernum_new'] = $payorder['ordernum'];
                Db::name($change_log['tablename'])->insert($newlog);
            }else{
                //改了多次单号
                $change_loglist = Db::name('ordernum_change_log')->where('tablename',$change_log['tablename'])->where('orderid',$change_log['orderid'])->select()->toArray();
                foreach ($change_loglist as $item){
                    $neworder = Db::name($item['tablename'])->where('ordernum',$item['ordernum_new'])->where('orderid',$item['orderid'])->find();
                    if($neworder){
                        Db::name($item['tablename'])->where('ordernum',$item['ordernum_new'])->where('orderid',$item['orderid'])
                            ->update(['ordernum'=>$payorder['ordernum']]);
                        $newlog = $item;
                        unset($newlog['id']);
                        $newlog['ordernum'] = $item['ordernum_new'];
                        $newlog['ordernum_new'] = $payorder['ordernum'];
                        Db::name($item['tablename'])->insert($newlog);
                        break;
                    }
                }
            }
        }
    }

    //$type =1//交班   2：报表
    /**
     * 餐饮收银台交班报表等统计营业额信息
     *  $datetype 报表的日期类型 today：今天   yesterday昨天  custom自定义
     *  $type=1 =1//交班   2：报表
     * $other 其他字段   datetype日期类型  starttime开始  endtime结束时间   paytype 支付方式限制，算总的时候 只显示限的支付方式
     */
    public static function tradeReport($aid,$bid,$uid,$type=1,$other=[]){
        $rdata = [];
        $jiaobantime = time();
        $logintime = Db::name('admin_loginlog')->where('aid',$aid)->where('uid',$uid)->order('id desc')->where('logintype','餐饮收银台账号登录')->value('logintime');

        $cashier = [];
        if(getcustom('restaurant_shop_cashdesk')){
            //收银员账号
            $cwhere []= ['aid' ,'=',$aid];
            if($bid > 0){
                $cwhere []= ['bid' ,'=',$bid];
            }else{
                $cwhere []= ['bid' ,'=',0];
            }
            $cashier = Db::name('restaurant_cashdesk')->where($cwhere)->find();
        }
        $search_paytype = $other['search_paytype']??'';
        $rdata['search_paytype'] =$search_paytype;//用于打印的判断
        //默认
        $today_start_time = strtotime(date('Y-m-d 00:00:01'));
        if($logintime > $today_start_time){
            $today_start_time =$logintime;
        }

        //$datetype today:今日 yesterday昨日 custom自定义 
        if($other['datetype'] =='today'){
            $logintime =$today_start_time =strtotime(date('Y-m-d 00:00:01'));
            $jiaobantime = time();
        }
        if($other['datetype'] =='yesterday'){
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            $logintime =$today_start_time =strtotime($yesterday.' 00:00:01');
            $jiaobantime = strtotime($yesterday.' 23:59:59');
        }
        if($other['datetype'] =='custom'){
            $logintime =$today_start_time =strtotime($other['starttime']);
            $jiaobantime = $other['endtime']?strtotime($other['endtime']):$jiaobantime;
        }

        $today_total_money = 0;
        $today_cash_money  = 0;//现金
        $today_yue_money   = 0;//余额
        $today_sxf_money   = 0;//随行付
        $today_wx_money    = 0;//微信
        $today_alipay_money= 0;//支付宝

        if(getcustom('restaurant_shop_cashdesk')){
            $where = [];
            $where[] =['aid','=',$aid];
            $where[] =['bid','=',$bid];
            $where[] = Db::raw("status in (1,3) or (status =4 and refund_status =2)");
            $where[] = ['paytime','between',[$today_start_time,$jiaobantime]];
            if($type ==1){
                $where[] = ['uid','=',$uid];
            }
            $where[] = ['platform','=','restaurant_cashdesk'];
            //------------------收银台营业额----------------
            //现金 0
            $today_cash_money =  0+ Db::name('restaurant_shop_order')->where($where)->where('paytypeid','=',0)->sum('totalprice');
            //余额 1
            $today_yue_money =  0+ Db::name('restaurant_shop_order')->where($where)->where('paytypeid','=',1)->sum('totalprice');
            //随行付 0
            $today_sxf_money = 0;
            if(getcustom('cashdesk_sxpay')){
                $today_sxf_money =  0+ Db::name('restaurant_shop_order')->where($where)->where('paytypeid','in',[5,81])->sum('totalprice');
            }
            //微信 2
            $today_wx_money =  0+ Db::name('restaurant_shop_order')->where($where)->where('paytypeid','=',2)->sum('totalprice');
            //支付宝 3
            $today_alipay_money =  0+ Db::name('restaurant_shop_order')->where($where)->where('paytypeid','=',3)->sum('totalprice');
        }

        $today_douyin_hexaio_money = 0;
        if(getcustom('restaurant_douyin_qrcode_hexiao')){
            $today_douyin_hexaio_money =0+ Db::name('restaurant_shop_order')->where($where)->where('paytypeid','=',121)->sum('totalprice');
        }
        $today_custom_money = 0;
        if(getcustom('restaurant_cashdesk_custom_pay')){
            //自定义支付
            $today_custom_pay_list  =[];
            $custom_pay_list = Db::name('restaurant_cashdesk_custom_pay')->where('aid',$aid)->where('bid',$bid)->where('status',1)->select()->toArray();
            foreach($custom_pay_list as $key=>$val){
                $custom_paytypeid = 10000 + $val['id'];
                $custom_money =0 +Db::name('restaurant_shop_order')->where($where)->where('paytypeid','=',$custom_paytypeid)->where('child_paytypeid',$val['id'])->sum('totalprice');
                //用于支付方式的搜索
                if($search_paytype==$custom_paytypeid)$today_total_money +=  $custom_money;
                if($custom_money > 0){
                    $today_custom_pay_list[$val['id']] =[
                        'money' => dd_money_format( $custom_money),
                        'title' =>   $val['title'] ,
                        'paytypeid' => $custom_paytypeid
                    ]  ;
                    $today_custom_money+=  $custom_money;
                }
            }
        }
        //用于支付方式搜索
        if($search_paytype==0){
            $today_total_money +=  $today_cash_money;
        }
        if($search_paytype==1){
            $today_total_money +=  $today_yue_money;
        }
        if($search_paytype==81){
            $today_total_money +=  $today_sxf_money;
        }
        if($search_paytype==2){
            $today_total_money +=  $today_wx_money;
        }
        if($search_paytype==3){
            $today_total_money +=  $today_alipay_money;
        }
        if($search_paytype==121){
            $today_total_money +=  $today_douyin_hexaio_money;
        }

        if($search_paytype ==''){
            $today_total_money = $today_cash_money+$today_yue_money+$today_sxf_money+ $today_wx_money +$today_alipay_money+$today_custom_money+$today_douyin_hexaio_money;
        }

        if(getcustom('restaurant_cashdesk_mix_pay')){
            //开启 混合支付
            $mix_wx_pay =  0+ Db::name('restaurant_shop_order')->where($where)->where('mix_paytypeid','=',2)->sum('mix_money');
            $mix_alipay_pay =  0+ Db::name('restaurant_shop_order')->where($where)->where('mix_paytypeid','=',3)->sum('mix_money');
            $mix_sxf_pay =  0+ Db::name('restaurant_shop_order')->where($where)->where('mix_paytypeid','=',81)->sum('mix_money');
            $rdata['mix_sxf_pay'] =dd_money_format($mix_sxf_pay); //随行付
            $rdata['mix_alipay_pay'] =dd_money_format($mix_alipay_pay); //支付宝
            $rdata['mix_wx_pay'] =dd_money_format($mix_wx_pay); //微信
        }
        $rdata['today_total_money'] =dd_money_format($today_total_money);//总
        $rdata['today_cash_money'] =dd_money_format($today_cash_money); //现金
        $rdata['today_yue_money'] =dd_money_format($today_yue_money); //余额
        $rdata['today_sxf_money'] =dd_money_format($today_sxf_money); //随行付
        $rdata['today_wx_money'] =dd_money_format($today_wx_money); //微信
        $rdata['today_alipay_money'] =dd_money_format($today_alipay_money); //支付宝
        $rdata['today_douyin_hexaio_money'] =dd_money_format($today_douyin_hexaio_money); //抖音核销
        if(getcustom('restaurant_cashdesk_custom_pay')){
            $rdata['today_custom_pay_list'] = $today_custom_pay_list;
        }
//------------------收银台订单统计----------------
        $where = [];
        $where[] = ['aid','=',$aid];
        $where[] = ['bid','=',$bid];
        $where[] = ['status','=',1];
        $where[] = ['paytime','between',[$today_start_time,$jiaobantime]];
        if($type ==1){
            $where[] = ['uid','=',$uid];
        }
        $cashier_total_money = 0;
        //现金 0
        $cashier_cash_money =  0+ Db::name('cashier_order')->where($where)->where('paytypeid','=',0)->sum('totalprice');
        //余额 1
        $cashier_yue_money =  0+ Db::name('cashier_order')->where($where)->where('paytypeid','=',1)->sum('totalprice');
        //随行付 0
        $cashier_sxf_money = 0;
        if(getcustom('cashdesk_sxpay')){
            $cashier_sxf_money =  0+ Db::name('cashier_order')->where($where)->where('paytypeid','in',[5,81])->sum('totalprice');
        }
        //微信 2
        $cashier_wx_money =  0+ Db::name('cashier_order')->where($where)->where('paytypeid','=',2)->sum('totalprice');
        //支付宝 3
        $cashier_alipay_money =  0+ Db::name('cashier_order')->where($where)->where('paytypeid','=',3)->sum('totalprice');
        //用于支付方式搜索
        if($search_paytype==0){
            $cashier_total_money +=  $cashier_cash_money;
        }
        if($search_paytype==1){
            $cashier_total_money +=  $cashier_yue_money;
        }
        if($search_paytype==81){
            $cashier_total_money +=  $cashier_sxf_money;
        }
        if($search_paytype==2){
            $cashier_total_money +=  $cashier_wx_money;
        }
        if($search_paytype==3){
            $cashier_total_money +=  $cashier_alipay_money;
        }
        if($search_paytype ==''){
            $cashier_total_money = $cashier_cash_money+$cashier_yue_money+$cashier_sxf_money+ $cashier_wx_money +$cashier_alipay_money;
        }
        $rdata['cashier_total_money']  = dd_money_format($cashier_total_money);//总
        $rdata['cashier_cash_money']   = dd_money_format($cashier_cash_money); //现金
        $rdata['cashier_yue_money']    = dd_money_format($cashier_yue_money); //余额
        $rdata['cashier_sxf_money']    = dd_money_format($cashier_sxf_money); //随行付
        $rdata['cashier_wx_money']     = dd_money_format($cashier_wx_money); //微信
        $rdata['cashier_alipay_money'] = dd_money_format($cashier_alipay_money); //支付宝

        $rdata['today_cash_money']   += $rdata['cashier_cash_money']; //现金
        $rdata['today_yue_money']    += $rdata['cashier_yue_money']; //余额
        $rdata['today_sxf_money']    += $rdata['cashier_sxf_money']; //随行付
        $rdata['today_wx_money']     += $rdata['cashier_wx_money']; //微信
        $rdata['today_alipay_money'] += $rdata['cashier_alipay_money']; //支付宝
//------------------线上营业额营业额（包括线上商城和扫码点餐）----------------
        $online_total_money = 0;
        $online_where = [];
        $online_where[] = ['platform','<>','cashdesk'];
        $online_where[] = ['platform','<>','cashier'];
        $online_where[]= ['aid' ,'=',$aid];
        $online_where[]= ['bid' ,'=',$bid];
        $online_where[]= ['status' ,'=',1];
        $online_where[]= ['type' ,'<>','recharge'];
        $online_where[] = ['paytime','between',[$today_start_time,$jiaobantime]];
        $online_yue_money = 0+Db::name('payorder')->where($online_where)->where('paytypeid',1)->sum('money');
        $online_wx_money = 0+Db::name('payorder')->where($online_where)->where('paytypeid',2)->sum('money');
        $online_alipay_money = 0+Db::name('payorder')->where($online_where)->where('paytypeid',3)->sum('money');
        $online_admin_money = 0+Db::name('payorder')->where($online_where)->where('paytypeid',0)->sum('money');
        if($search_paytype==1){
            $online_total_money +=  $today_douyin_hexaio_money;
        }
        if($search_paytype==2){
            $online_total_money +=  $online_wx_money;
        }
        if($search_paytype==3){
            $online_total_money +=  $online_alipay_money;
        }
        if($search_paytype==0){
            $online_total_money +=  $online_admin_money;
        }
        if($search_paytype==''){
            $online_total_money =$online_yue_money + $online_wx_money+ $online_alipay_money+$online_admin_money;
        }

        $rdata['online_yue_money'] =dd_money_format($online_yue_money); //余额
        $rdata['online_wx_money'] =dd_money_format($online_wx_money); //微信
        $rdata['online_alipay_money'] =dd_money_format($online_alipay_money); //支付宝
        $rdata['online_admin_money'] =dd_money_format($online_admin_money); //后台补录
        $rdata['online_total_money'] =dd_money_format($online_total_money); //总
//------------------优惠金额（商城的优惠劵、餐饮的优惠劵、餐饮的促销、收银台优惠券）----------------
        $youhui_where =[];
        $youhui_where[]= ['aid' ,'=',$aid];
        $youhui_where[] =['bid','=',$bid];
        $youhui_where[] = ['paytime','between',[$today_start_time,$jiaobantime]];
        //商城
        $shop_coupon = Db::name('shop_order')->where($youhui_where)->where('status','in',[1,2,3])->sum('coupon_money');
        //餐饮
        $restaurant_youhui_where[] =  $youhui_where;
        $restaurant_youhui_where[] = Db::raw("((status in (1,3)) or (status =4 and refund_status =2))");
        $restaurant_coupon = Db::name('restaurant_shop_order')->where($restaurant_youhui_where)->sum('coupon_money');
        $restaurant_cuxiao = Db::name('restaurant_shop_order')->where($restaurant_youhui_where)->sum('cuxiao_money');
        //收银台
        $cashier_coupon = Db::name('cashier_order')->where($youhui_where)->where('status',1)->sum('coupon_money');
        $rdata['shop_coupon']       = $shop_coupon;
        $rdata['restaurant_coupon'] = $restaurant_coupon;
        $rdata['restaurant_cuxiao'] = $restaurant_cuxiao;
        $rdata['cashier_coupon']    = $cashier_coupon;
        $rdata['youhui_total'] =dd_money_format($shop_coupon+$restaurant_coupon+$restaurant_cuxiao+$cashier_coupon); //总
//------------------会员充值（所有）----------------
        $recharge_total_money = 0;
        if($bid ==0){
            $recharge_where = [];
            $recharge_where[]= ['aid' ,'=',$aid];
            
            $recharge_where[]= ['type' ,'=','recharge'];
            $recharge_where[] = ['paytime','between',[$today_start_time,$jiaobantime]];
            $recharge_sxf_money  = 0;
            if(getcustom('cashdesk_sxpay')) {
                $recharge_sxf_money = 0 + Db::name('payorder')->where($recharge_where)->where('paytypeid', 81)->sum('money');//随行付
            }
            $recharge_wx_money =  0+Db::name('payorder')->where($recharge_where)->where('paytypeid',2)->sum('money');//微信
            $recharge_alipay_money =  0+Db::name('payorder')->where($recharge_where)->where('paytypeid',3)->sum('money');//支付宝
            $recharge_cash_money  =  0+Db::name('payorder')->where($recharge_where)->where('paytypeid',0)->where('platform','cashdesk')->sum('money');//现金
            $recharge_cash_money +=  0+Db::name('payorder')->where($recharge_where)->where('paytypeid',0)->where('platform','cashier')->sum('money');//现金
    //        $recharge_admin_money =  0+Db::name('payorder')->where($recharge_where)->where('paytypeid',0)->where('platform','<>','cashdesk')->sum('money');//后台充值
            if($search_paytype==81){
                $recharge_total_money +=  $recharge_sxf_money;
            }
            if($search_paytype==2){
                $recharge_total_money +=  $recharge_wx_money;
            }
            if($search_paytype==3){
                $recharge_total_money +=  $recharge_alipay_money;
            }
            if($search_paytype==0){
                $recharge_total_money +=  $recharge_cash_money;
            }
            
            if($search_paytype=='') {
                $recharge_total_money = $recharge_sxf_money + $recharge_wx_money + $recharge_alipay_money + $recharge_cash_money;
            }
            $rdata['recharge_sxf_money'] =dd_money_format($recharge_sxf_money); //随行付
            $rdata['recharge_wx_money'] =dd_money_format($recharge_wx_money); //微信
            $rdata['recharge_alipay_money'] =dd_money_format($recharge_alipay_money); //支付宝
            $rdata['recharge_cash_money'] =dd_money_format($recharge_cash_money); //现金
            //$rdata['recharge_admin_money'] =dd_money_format($recharge_admin_money); //现金
            $rdata['recharge_total_money'] =dd_money_format($recharge_total_money); //总
        }
//------------------退款总额（所有）----------------
        $refund_total_money = 0;
        $refund_where = [];
        $refund_where[] = ['aid' ,'=',$aid];
        $refund_where[] = ['bid' ,'=',$bid];
        $refund_where[] = ['refund_time' ,'>',0];
        $refund_where[] = ['refund_time','between',[$today_start_time,$jiaobantime]];
        //退款 现金
        $refund_cash_money =  0+Db::name('payorder')->where($refund_where)->where('paytypeid',0)->sum('refund_money');//现金
        //退款 余额
        $refund_yue_money =  0+Db::name('payorder')->where($refund_where)->where('paytypeid',1)->sum('refund_money');//现金
        //退款 随行付
        $refund_sxf_money =0;
        if(getcustom('cashdesk_sxpay')){
            $refund_sxf_money = 0+Db::name('payorder')->where($refund_where)->where('paytypeid',81)->where('platform','cashdesk')->sum('refund_money');//随行付
            $refund_sxf_money += 0+Db::name('payorder')->where($refund_where)->where('paytypeid',81)->where('platform','cashier')->sum('refund_money');//随行付
        }
        //微信线上退款    商城 + 点餐退款金额
        $refund_wx_money = 0+Db::name('payorder')->where($refund_where)->where('paytypeid',2)->sum('refund_money');;//现金;
        //支付宝线上退款     
        $refund_alipay_money = 0+Db::name('payorder')->where($refund_where)->where('paytypeid',3)->sum('refund_money');
        $refund_douyin_hexaio_money = 0;
        if(getcustom('restaurant_douyin_qrcode_hexiao')){
            //抖音核销券     
            $refund_douyin_hexaio_money = 0+Db::name('payorder')->where($refund_where)->where('paytypeid',121)->sum('refund_money');
        }
        //支出
        if(getcustom('expend')){
            $expend_where = [];
            $expend_where[] = ['aid' ,'=',$aid];
            $expend_where[] = ['bid' ,'=',$bid];
            $expend_where[] = ['createtime','between',[$today_start_time,$jiaobantime]];
            //
            $rdata['expend_total_money'] = 0+Db::name('expend')->where($expend_where)->sum('money');
            //分类
            $expendCategory = Db::name('expend_category')->where('aid',$aid)->where('bid',$bid)->column('name','id');
            $expendCids = [];
            if($expendCategory){
                foreach ($expendCategory as $cid => $cname){
                    $expendCids[] = $cid;
                    $rdata['expend_money_cat'][] = [
                        'cid'=>$cid,
                        'cname'=>$cname,
                        'money'=> 0+Db::name('expend')->where($expend_where)->where('cid',$cid)->sum('money')
                    ];
                }
            }
            $rdata['expend_money_cat'][] = [
                'cid'=>0,
                'cname'=>'未分类',
                'money'=> 0+Db::name('expend')->where($expend_where)->where('cid','not in',$expendCids)->sum('money')
            ];
        }
        //自定义支付计算
        $refund_custom_money = 0;
        $refund_custom_list = [];
        if(getcustom('restaurant_cashdesk_custom_pay')){
            //自定义支付
            $custom_pay_list = Db::name('restaurant_cashdesk_custom_pay')->where('aid',$aid)->where('bid',$bid)->where('status',1)->select()->toArray();
            foreach($custom_pay_list as $key=>$val){
                $custom_paytypeid = 10000 + $val['id'];
                $custom_refund_money =0 +Db::name('payorder')->where($refund_where)->where('paytypeid','=',$custom_paytypeid)->sum('refund_money');
                //用于支付方式的搜索
                if($search_paytype==$custom_paytypeid)$refund_total_money +=  $custom_refund_money;
                if($custom_refund_money > 0){
                    $refund_custom_money+=  $custom_refund_money;
                    $refund_custom_list[$val['id']] =[
                        'refund_money' => dd_money_format( $custom_refund_money),
                        'title' =>   $val['title'],
                        'paytypeid' => $custom_paytypeid
                    ]  ;
                }
            }
        }
        if($search_paytype==0){
            $refund_total_money+=$refund_cash_money;
        }
        if($search_paytype==1){
            $refund_total_money+=$refund_yue_money;
        }
        if($search_paytype==81){
            $refund_total_money+=$refund_sxf_money;
        }
        if($search_paytype==2){
            $refund_total_money+=$refund_wx_money;
        }
        if($search_paytype==3){
            $refund_total_money+=$refund_alipay_money;
        }
        if($search_paytype==121){
            $refund_total_money+=$refund_douyin_hexaio_money;
        }
        if($search_paytype=='') {
            $refund_total_money = $refund_cash_money + $refund_yue_money + $refund_sxf_money + $refund_wx_money + $refund_alipay_money + $refund_douyin_hexaio_money + $refund_custom_money;
        }
        $rdata['refund_total_money'] =dd_money_format($refund_total_money); //总退款
        $rdata['refund_cash_money'] =dd_money_format($refund_cash_money); //现金退款
        $rdata['refund_yue_money'] =dd_money_format($refund_yue_money); //余额退款
        $rdata['refund_sxf_money'] =dd_money_format($refund_sxf_money); //随行付退款
        $rdata['refund_wx_money'] =dd_money_format($refund_wx_money); //微信退款
        $rdata['refund_alipay_money'] =dd_money_format($refund_alipay_money); //支付宝退款
        $rdata['refund_douyin_hexaio_money'] =dd_money_format($refund_douyin_hexaio_money); //抖音核销
        $rdata['refund_custom_money'] =dd_money_format($refund_custom_money); //自定义支付退款
        $rdata['refund_custom_list'] =$refund_custom_list; //自定义支付列表
        if(getcustom('restaurant_cashdesk_mix_pay')){
            //开启 混合支付
            $mix_refund_where = [];
            $mix_refund_where[] = ['refund_status','=',2];
            $mix_refund_where[] = ['paytime','between',[$today_start_time,$jiaobantime]];

            $mix_refund_wx_pay =  0+ Db::name('restaurant_shop_order')->where($mix_refund_where)->where('mix_paytypeid','=',2)->sum('mix_money');
            $mix_refund_alipay_pay =  0+ Db::name('restaurant_shop_order')->where($mix_refund_where)->where('mix_paytypeid','=',3)->sum('mix_money');
            $mix_refund_sxf_pay =  0+ Db::name('restaurant_shop_order')->where($mix_refund_where)->where('mix_paytypeid','=',81)->sum('mix_money');

            $rdata['mix_refund_wx_pay'] =dd_money_format($mix_refund_wx_pay); //微信
            $rdata['mix_refund_alipay_pay'] =dd_money_format($mix_refund_alipay_pay); //支付宝
            $rdata['mix_refund_sxf_pay'] =dd_money_format($mix_refund_sxf_pay); //随行付
        }
//------------------订单数量（收银机-已完成，线上（商城-已支付 +扫码点餐-已完成））----------------
        $cashdesk_ordercount = 0;
        if(getcustom('restaurant_shop_cashdesk')){
            $cashdesk_ordercount_where[] = ['aid' ,'=',$aid];
            $cashdesk_ordercount_where[] = ['bid' ,'=',$bid];
            $cashdesk_ordercount_where[] = ['status','in',[1,3]];
            $cashdesk_ordercount_where[] = ['cashdesk_id','>',0];
            $cashdesk_ordercount_where[] = ['paytime','between',[$today_start_time,$jiaobantime]];
            if($search_paytype !=''){
                $cashdesk_ordercount_where[] = ['paytypeid','=',$search_paytype];
            }
            $cashdesk_ordercount = Db::name('restaurant_shop_order')->where($cashdesk_ordercount_where)->count();
        }
        //收银台订单
        $cashier_ordercount_where   = [];
        $cashier_ordercount_where[] = ['aid' ,'=',$aid];
        $cashier_ordercount_where[] = ['bid' ,'=',$bid];
        $cashier_ordercount_where[] = ['status','=',1];
        $cashier_ordercount_where[] = ['paytime','between',[$today_start_time,$jiaobantime]];
        if($search_paytype !=''){
            $cashier_ordercount_where[] = ['paytypeid','=',$search_paytype];
        }
        $cashier_ordercount = Db::name('cashier_order')->where($cashier_ordercount_where)->count();
        //扫码点餐+商城
        $shop_ordercount_where = [];
        $shop_ordercount_where[] = ['status','in',[1,2,3]];
        $shop_ordercount_where[] = ['aid','=',$aid];
        $shop_ordercount_where[] = ['bid','=',$bid];
        if($search_paytype !=''){
            $shop_ordercount_where[] = ['paytypeid','=',$search_paytype];
        }
        $shop_ordercount_where[] = ['paytime','between',[$today_start_time,$jiaobantime]];
        $shop_ordercount = 0+Db::name('shop_order')->where($shop_ordercount_where)->count();

        //扫码点餐
        $scan_ordercount = 0;
        if(getcustom('restaurant_shop_cashdesk')){
            $scan_ordercount_where =[];
            $scan_ordercount_where[] = ['status','in',[1,3]];
            $scan_ordercount_where[] = ['aid','=',$aid];
            $scan_ordercount_where[] = ['bid','=',$bid];
            $scan_ordercount_where[] = ['cashdesk_id','=',0];
            $scan_ordercount_where[] = ['paytime','between',[$today_start_time,$jiaobantime]];
            if($search_paytype !=''){
                $scan_ordercount_where[] = ['paytypeid','=',$search_paytype];
            }
            $scan_ordercount =0+ Db::name('restaurant_shop_order')->where($scan_ordercount_where)->count();
        }
        $rdata['cashdesk_ordercount'] = $cashdesk_ordercount+$cashier_ordercount;//收银台订单数
        $rdata['online_ordercount']   = $shop_ordercount +$scan_ordercount ;//商城+扫码点餐
        $rdata['total_ordercount']    = $cashdesk_ordercount + $shop_ordercount + $scan_ordercount+$cashier_ordercount;
//------------------汇总数据----------------
        $rdata['recharge_show'] =true;
        if($bid>0){
            //如果是商户 不统计 充值金额
            $recharge_total_money = 0;
            $rdata['recharge_show'] =false;
        }
        //营业额汇总  收银机营业额+线上营业额-退款总额
        $yingyee_money =  $today_total_money + $cashier_total_money +  $online_total_money -  $refund_total_money;
        
        $rdata['yingyee_money'] =dd_money_format($yingyee_money); //营业额汇总
        $rdata['all_yingyee_money'] =dd_money_format($yingyee_money+$recharge_total_money - $online_yue_money -$cashier_yue_money); //营业额汇总 （减去线上和线下余额支付）
        //总收款    收银机营业额+线上营业额-会员余额消费+会员储值预付款收款小计-退款总额
        if($search_paytype !=''){
            $online_yue_money = 0;
        }
        $total_in_money =  $today_total_money + $cashier_total_money + $online_total_money - $online_yue_money  + $recharge_total_money - $refund_total_money;
        $rdata['total_in_money'] = dd_money_format($total_in_money - $cashier_yue_money); //减去线下收音机的余额支付
        $rdata['all_total_in_money'] = dd_money_format($total_in_money+$online_yue_money);//包含会员余额消费
        $rdata['all_yue_money'] = dd_money_format($online_yue_money + $cashier_yue_money - $refund_yue_money);//总余额消费

        $cashdesk_user = Db::name('admin_user')->where('id',$uid)->value('un');
        //根据设置显示不同的支付信息
        $wxpay_show = true;
        $sxfpay_show = true;
        $alipay_show = true;
        $cashpay_show = true;
        $yuepay_show =  true;
        $douyinhx_show =  true;
        if($cashier['bid'] ==0){
            if(!$cashier['wxpay']){
                $wxpay_show = false;
            }
            if(!$cashier['sxpay']){
                $sxfpay_show = false;
            }
            if(!getcustom('cashdesk_alipay') || !$cashier['alipay']){
                $alipay_show = false;
            }
            if(!$cashier['cashpay']){
                $cashpay_show = false;
            }
            if(!$cashier['cashpay']){
                $yuepay_show = false;
            }
            if(!$cashier['douyinhx']){
                $douyinhx_show = false;
            }
        }else{//bid>0
            $sysset = Db::name('restaurant_admin_set')->where('aid',$aid)->find();
            if(!$sysset['business_cashdesk_wxpay_type']){
                $wxpay_show = false;
            }
            if(!$sysset['business_cashdesk_sxpay_type']){
                $sxfpay_show = false;
            }
            if(!$sysset['business_cashdesk_alipay_type'] ||!getcustom('cashdesk_alipay')){
                $alipay_show = false;
            }
            if(!$sysset['business_cashdesk_cashpay']){
                $cashpay_show = false;
            }
            if(!$sysset['business_cashdesk_yue']){
                $yuepay_show = false;
            }
            if(!$sysset['business_cashdesk_douyinhx']){
                $douyinhx_show = false;
            }
        }
        $rdata['logintime'] = date('Y-m-d H:i:s',$logintime);
        $rdata['jiaobantime'] = date('Y-m-d H:i:s',$jiaobantime);
        $rdata['cashier_info'] =$cashier;
        $rdata['cashdesk_user'] =$cashdesk_user;
        $rdata['wxpay_show'] =$wxpay_show;
        $rdata['sxfpay_show'] =$sxfpay_show;
        $rdata['alipay_show'] =$alipay_show;
        $rdata['cashpay_show'] =$cashpay_show;
        $rdata['yuepay_show'] =$yuepay_show;
        $rdata['douyinhx_show'] =$douyinhx_show;
       
        return  $rdata;
    }

	//酒店订单
    public static function hotel_pay($orderid){
        $order = Db::name('hotel_order')->where('id',$orderid)->find();
	    $member = Db::name('member')->where('id',$order['mid'])->find();
        $aid = $order['aid'];
        $mid = $order['mid'];
		if($order['use_money']>0){
			\app\commons\Member::addmoney($aid,$mid,-$order['use_money'],t('余额').'抵扣，订单号: '.$order['ordernum']);
		}
		$room = Db::name('hotel_room')->where('id',$order['roomid'])->find();
		//增加押金记录
		if($order['yajin_money']>0){
			$yjdata = [];
			$yjdata['aid'] = $order['aid'];
			$yjdata['bid'] = $order['bid'];
			$yjdata['mid'] = $order['mid'];
			$yjdata['orderid'] = $order['id'];
			$yjdata['ordernum'] = $order['ordernum'];
			$yjdata['yajin_money'] = $order['yajin_money'];
			$yjdata['yajin_type'] = $order['yajin_type'];
			$yjdata['refund_money'] = $order['yajin_money'];
			$yjdata['refund_status'] = 0;
			$yjdata['refund_ordernum'] = '' . date('ymdHis') . rand(100000, 999999);
			//$yjdata['apply_time'] = time();
			$yjdata['yd_num'] = $order['totalnum']; //预定人数
			$yajinid = Db::name('hotel_order_yajin')->insertGetId($yjdata);
			//修改关联的押金订单
			Db::name('hotel_order')->where('id',$orderid)->update(['yajin_orderid'=>$yajinid]);
		}
		//发货信息录入 微信小程序+微信支付
		if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
			\app\commons\Order::wxShipping($order['aid'],$order,'hotel');
		}
		//是否为即时确认
		if($room['qrtype']==1){
			Db::name('hotel_order')->where('id',$orderid)->update(['status'=>2,'confirm_time'=>time()]);
			//发送消息通知
			\app\models\Hotel::sendNotice($aid,$order);
		}
		//加销量
		\app\models\Hotel::addroomsales($order,$order['totalnum']);
		
		//短信通知
        $rs = \app\commons\Sms::send($aid,$member['tel']?$member['tel']:$order['tel'],'tmpl_orderpay',['ordernum'=>$order['ordernum']]);
	
    }


    //鱼塘
    public static function fishpond_pay($orderid){
        $order = Db::name('fishpond_order')->where('id',$orderid)->find();
        $hours = $order['hours'];
        $hours_float = floatval($hours);
        $minutes = (int) ($hours_float * 60);
        $start_time = time();
        $end_time = strtotime("+$minutes minutes", $start_time);
        Db::name('fishpond_order')
            ->where('id',$orderid)
            ->update([
               'starttime' => $start_time,
               'endtime' => $end_time
            ]);

        //修改钓点状态
        Db::name('fishpond_basan')
            ->where('aid',aid)
            ->where('id','in',$order['basanid'])
            ->update([
                'starttime' => $start_time,
                'endtime' => $end_time,
                'status' => 2 //已售出
            ]);

        $set = Db::name('admin_set')->where('aid',$order['aid'])->find();
        if($set['fxjiesuantime'] == 1 && $set['fxjiesuantime_delaydays'] == '0'){
            \app\commons\Order::giveCommission($order,'fishpond');
        }
    }
}
