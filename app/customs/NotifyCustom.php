<?php


namespace app\customs;
use think\facade\Db;
class NotifyCustom
{
    public static function deduct_cost($aid,$bid,$type,$msg,$paymoney)
    {
        if(getcustom('business_deduct_cost')){
            //扣除成本
            $typearr = ['shop' , 'collage' , 'cycle' ,  'seckill' ,  'scoreshop' , 'restaurant_takeaway' , 'restaurant_shop' , 'restaurant_booking'];
            if(in_array($type,$typearr)){

                $payorder    = Db::name('payorder')->where(['aid'=>$aid,'type'=>$type,'ordernum'=>$msg['out_trade_no']])->field('id,aid,bid,orderid')->find();
                if($payorder){

                    $binfo = Db::name('business')->where('id',$bid)->where('aid',$aid)->field('id,deduct_cost')->find();
                    //如果设置扣除成本
                    if($binfo && $binfo['deduct_cost'] == 1){
                        $orderid = $payorder['orderid'];

                        $all_cost_price = 0;

                        //商城、积分、外卖、点餐、预订
                        if(\app\commons\Order::hasOrderGoodsTable($type)){
                            $oglist  = Db::name($type.'_order_goods')->where('orderid',$orderid)->where('aid',$aid)->field('id,cost_price,sell_price')->select()->toArray();
                            if($oglist){
                                foreach($oglist as $og){
                                    if(!empty($og['cost_price']) && $og['cost_price']>0){
                                        if($og['cost_price']<=$og['sell_price']){
                                            $all_cost_price += $og['cost_price'];
                                        }else{
                                            $all_cost_price += $og['sell_price'];
                                        }
                                    }
                                }
                                unset($og);
                            }
                        }

                        //拼团、周期购、秒杀
                        if($type == 'collage' || $type=='cycle' || $type=='seckill'){
                            $order  = Db::name($type.'_order')->where('id',$orderid)->where('aid',$aid)->field('id,cost_price,sell_price')->find();
                            if($order['cost_price']<=$order['sell_price']){
                                $all_cost_price = $order['cost_price'];
                            }else{
                                $all_cost_price = $order['sell_price'];
                            }
                        }

                        if($all_cost_price>0) {
                            $paymoney -= $all_cost_price;
                        }
                    }
                }
            }

            if($paymoney<=0){
                $paymoney = 0;
            }
            return $paymoney;
        }
    }
    public static function deduct_cost2($aid,$bid,$ordernum,$paymoney,$type)
    {
        if(getcustom('business_deduct_cost')){
            //扣除成本
            $typearr = ['shop' , 'collage' , 'cycle' ,  'seckill' ,  'scoreshop' , 'restaurant_takeaway' , 'restaurant_shop' , 'restaurant_booking'];
            if(in_array($type,$typearr)){
                $binfo = Db::name('business')->where('id',$bid)->where('aid',$aid)->field('id,deduct_cost')->find();
                //如果设置扣除成本
                if($binfo && $binfo['deduct_cost'] == 1){
                    //查询订单信息
                    $order  = Db::name($type.'_order')->where('ordernum',$ordernum)->where('aid',$aid)->find();
                    if($order){
                        $orderid = $order['id'];

                        $all_cost_price = 0;
                        //商城、积分、外卖、点餐、预订
                        if(\app\commons\Order::hasOrderGoodsTable($type)){
                            $oglist  = Db::name($type.'_order_goods')->where('orderid',$orderid)->where('aid',$aid)->field('id,cost_price,sell_price')->select()->toArray();

                            foreach($oglist as $og){
                                if(!empty($og['cost_price']) && $og['cost_price']>0){
                                    if($og['cost_price']<=$og['sell_price']){
                                        $all_cost_price += $og['cost_price'];
                                    }else{
                                        $all_cost_price += $og['sell_price'];
                                    }
                                }
                            }
                            unset($og);
                        }

                        //拼团、周期购、秒杀
                        if($type == 'collage' || $type=='cycle' || $type=='seckill'){
                            if($order['cost_price']<=$order['sell_price']){
                                $all_cost_price = $order['cost_price'];
                            }else{
                                $all_cost_price = $order['sell_price'];
                            }
                        }

                        if($all_cost_price>0) {
                            $paymoney -= $all_cost_price;
                        }
                    }
                }
            }

            if($paymoney<=0){
                $paymoney = 0;
            }
            return $paymoney;
        }
    }

    /*
    * 多商户费率抽成时类型选择
    * '商家费率结算类型   0:销售价 1：结算价2：成本价
    * 销售价为商品定价(订单价格);结算价为商品实际成交价，不含运费;成本价为商品设置的成本价
    */ 
    public static function business_fee_type_money($aid,$bid,$type,$msg,$paymoney)
    {
        if(getcustom('business_fee_type')){
            //扣除成本
            $typearr = ['shop' , 'collage' , 'cycle' ,  'seckill' ,  'scoreshop' , 'restaurant_takeaway' , 'restaurant_shop' , 'restaurant_booking'];
            if(in_array($type,$typearr)){

                $payorder    = Db::name('payorder')->where(['aid'=>$aid,'type'=>$type,'ordernum'=>$msg['out_trade_no']])->field('id,aid,bid,orderid')->find();
                if($payorder){

                    $binfo_set = Db::name('business_sysset')->where('id',$bid)->where('aid',$aid)->field('id,business_fee_type')->find();
                    $orderid = $payorder['orderid'];
                    //0按照销售价格原逻辑1按实际支付价格，扣除运费2按照成本计算抽成
                    if($binfo_set && $binfo_set['business_fee_type'] == 2){                       

                        $all_cost_price = 0;

                        //商城、积分、外卖、点餐、预订
                        if(\app\commons\Order::hasOrderGoodsTable($type)){
                            $oglist  = Db::name($type.'_order_goods')->where('orderid',$orderid)->where('aid',$aid)->field('id,cost_price,sell_price')->select()->toArray();
                            if($oglist){
                                foreach($oglist as $og){
                                    if(!empty($og['cost_price']) && $og['cost_price']>0){
                                        if($og['cost_price']<=$og['sell_price']){
                                            $all_cost_price += $og['cost_price'];
                                        }else{
                                            $all_cost_price += $og['sell_price'];
                                        }
                                    }
                                }
                                unset($og);
                            }
                        }

                        //拼团、周期购、秒杀
                        if($type == 'collage' || $type=='cycle' || $type=='seckill'){
                            $order  = Db::name($type.'_order')->where('id',$orderid)->where('aid',$aid)->field('id,cost_price,sell_price')->find();
                            if($order['cost_price']<=$order['sell_price']){
                                $all_cost_price = $order['cost_price'];
                            }else{
                                $all_cost_price = $order['sell_price'];
                            }
                        }

                        if($all_cost_price>0) {
                            $paymoney = $all_cost_price;
                        }
                    }elseif($binfo_set && $binfo_set['business_fee_type'] == 1){
                        $order  = Db::name($type.'_order')->where('id',$orderid)->where('aid',$aid)->field('id,totalprice,freight_price')->find();
                        $paymoney = $order['totalprice'] - $order['freight_price'];
                    }
                }
            }

            if($paymoney<=0){
                $paymoney = 0;
            }
            return $paymoney;
        }
    }

    /*
    * 多商户费率抽成时类型选择
    * '商家费率结算类型   0:销售价 1：结算价2：成本价
    * 销售价为商品定价(订单价格);结算价为商品实际成交价，不含运费;成本价为商品设置的成本价
    */ 

    public static function business_fee_type_money2($aid,$bid,$ordernum,$paymoney,$type)
    {
        if(getcustom('business_fee_type')){
            //扣除成本
            $typearr = ['shop' , 'collage' , 'cycle' ,  'seckill' ,  'scoreshop' , 'restaurant_takeaway' , 'restaurant_shop' , 'restaurant_booking'];
            if(in_array($type,$typearr)){
                $binfo_set = Db::name('business_sysset')->where('id',$bid)->where('aid',$aid)->field('id,business_fee_type')->find();
                //如果设置扣除成本
                if($binfo_set && $binfo_set['business_fee_type'] == 2){
                    //查询订单信息
                    $order  = Db::name($type.'_order')->where('ordernum',$ordernum)->where('aid',$aid)->find();
                    if($order){
                        $orderid = $order['id'];

                        $all_cost_price = 0;
                        //商城、积分、外卖、点餐、预订
                        if(\app\commons\Order::hasOrderGoodsTable($type)){
                            $oglist  = Db::name($type.'_order_goods')->where('orderid',$orderid)->where('aid',$aid)->field('id,cost_price,sell_price')->select()->toArray();

                            foreach($oglist as $og){
                                if(!empty($og['cost_price']) && $og['cost_price']>0){
                                    if($og['cost_price']<=$og['sell_price']){
                                        $all_cost_price += $og['cost_price'];
                                    }else{
                                        $all_cost_price += $og['sell_price'];
                                    }
                                }
                            }
                            unset($og);
                        }

                        //拼团、周期购、秒杀
                        if($type == 'collage' || $type=='cycle' || $type=='seckill'){
                            if($order['cost_price']<=$order['sell_price']){
                                $all_cost_price = $order['cost_price'];
                            }else{
                                $all_cost_price = $order['sell_price'];
                            }
                        }

                        if($all_cost_price>0) {
                            $paymoney = $all_cost_price;
                        }
                    }
                }elseif($binfo_set && $binfo_set['business_fee_type'] == 1){
                    //查询订单信息
                    $order  = Db::name($type.'_order')->where('ordernum',$ordernum)->where('aid',$aid)->find();
                    $paymoney = $order['totalprice'] - $order['freight_price'];
                }
            }

            if($paymoney<=0){
                $paymoney = 0;
            }
            return $paymoney;
        }
    }
}