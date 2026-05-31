<?php


//股东分红 团队分红
namespace app\commons;
use think\facade\Db;
use think\facade\Log;
class Fenhong
{
    public static function jiesuan($aid,$starttime=0,$endtime=0){
        return;
        if($endtime == 0) $endtime = time();
        $sysset = Db::name('admin_set')->where('aid',$aid)->find();

        $where = [];
        $where[] = ['og.aid','=',$aid];
        $where[] = ['og.isfenhong','=',0];
        $fail_where = [
          ["og.aid", "=", $aid],
          ["og.isfenhong", "=", 0],
          ["og.iszj", "=", 2],
          ['og.isjiqiren', '=', 0]
        ];
        if($sysset['fhjiesuanbusiness'] == 1){ //多商户的商品是否参与分红
            
        }else{
            $where[] = ['og.bid','=','0'];
            $fail_where[] = ['og.bid', '=', 0];
        }
        if($sysset['fhjiesuantime_type'] == 1) { //分红结算时间类型 0收货后，1付款后
            $where[] = ['og.status','in',[1,2,3]];
            $where[] = ['og.createtime','>=',$starttime];
            $where[] = ['og.createtime','<',$endtime];
            $fail_where[] = ['og.status', '=', 4];
            $fail_where[] = ['og.createtime','>=',$starttime];
            $fail_where[] = ['og.createtime','<',$endtime];
            $where2 = $where;
        }else{
            $where[] = ['og.status','=','3'];
            $where2 = $where;
            $where[] = ['og.endtime','>=',$starttime];
            $where[] = ['og.endtime','<',$endtime];
            $where2[] = ['og.collect_time','>=',$starttime];
            $where2[] = ['og.collect_time','<',$endtime];
            $fail_where[] = ['og.status', '=', 4];
        }
        //排除退款订单
        $oglist = Db::name('shop_order_goods')->alias('og')->field('og.*,o.area2,o.paytime')->join('shop_order o','o.id=og.orderid')->where($where)->where('refund_num',0)->select()->toArray();
        if($oglist){
            if(getcustom('fenhong_manual',$aid)){
                $update = ['og.isfenhong'=>1];
            }else{
                $update = ['og.isfenhong'=>1,'og.isteamfenhong'=>1];
            }
            $ids = array_column($oglist,'id');
            Db::name('shop_order_goods')->alias('og')->where('id','in',$ids)->update($update);
        }
        if(getcustom('yuyue_fenhong',$aid)){
            $yyorderlist = Db::name('yuyue_order')->alias('og')->field('og.*,m.nickname,m.headimg')->join('member m','m.id=og.mid')->where($where2)->select()->toArray();
            foreach($yyorderlist as $k=>$v){
                $v['name'] = $v['proname'];
                $v['real_totalprice'] = $v['totalprice'];
                $v['cost_price'] = $v['cost_price'] ?? 0;
                $v['module'] = 'yuyue';
                $oglist[] = $v;
            }
            if($yyorderlist){
                Db::name('yuyue_order')->alias('og')->field('og.*,m.nickname,m.headimg')->join('member m','m.id=og.mid')->where($where2)->update(['og.isfenhong'=>1]);
            }
        }
        if(getcustom('scoreshop_fenhong',$aid)){
            $scoreshopoglist = Db::name('scoreshop_order_goods')->alias('og')->field('og.*,o.area2,o.paytime')->join('scoreshop_order o','o.id=og.orderid')->where($where)->select()->toArray();
            foreach($scoreshopoglist as $v){
                $v['real_totalprice'] = $v['totalmoney'];
                $v['module'] = 'scoreshop';
                $oglist[] = $v;
            }

            if($scoreshopoglist){
                Db::name('scoreshop_order_goods')->alias('og')->field('og.*,o.area2,o.paytime')->join('scoreshop_order o','o.id=og.orderid')->where($where)->update(['og.isfenhong'=>1]);
            }
        }
        if(getcustom('luckycollage_fenhong',$aid)){
            $lcorderlist = Db::name('lucky_collage_order')->alias('og')->field('og.*,m.nickname,m.headimg')->join('member m','m.id=og.mid')->where($where2)->whereRaw('og.buytype=1 or og.iszj=1')->where('og.isjiqiren',0)->select()->toArray();
            foreach($lcorderlist as $k=>$v){
                $v['name'] = $v['proname'];
                $v['real_totalprice'] = $v['totalprice'];
                $v['cost_price'] = 0;
                $v['module'] = 'luckycollage';
                $oglist[] = $v;
            }

            if($lcorderlist){
                Db::name('lucky_collage_order')->alias('og')->field('og.*,m.nickname,m.headimg')->join('member m','m.id=og.mid')->where($where2)->whereRaw('og.buytype=1 or og.iszj=1')->where('og.isjiqiren',0)->update(['og.isfenhong'=>1]);
            }
        }

        //幸运拼团失败者分红
        if (getcustom('luckycollage_fail_commission',$aid)) {
            $lcorderlist = Db::name('lucky_collage_order')->alias('og')->field('og.*,m.nickname,m.headimg')->join('member m', 'm.id=og.mid')
                ->where($fail_where)
                ->select()
                ->toArray();
            foreach ($lcorderlist as $k => $v) {
                $v['name'] = $v['proname'];
                $v['real_totalprice'] = $v['totalprice'];
                $v['cost_price'] = 0;
                $v['module'] = 'luckycollage';
                $oglist[] = $v;
            }
            if ($lcorderlist) {
                Db::name('lucky_collage_order')->alias('og')->field('og.*,m.nickname,m.headimg')->join('member m', 'm.id=og.mid')
                    ->where($fail_where)
                    ->update(['og.isfenhong' => 1]);
            }
        }

        if(getcustom('maidan_fenhong',$aid) && !getcustom('maidan_fenhong_new',$aid)){
            //买单
            $maidan_orderlist = Db::name('maidan_order')
                ->alias('og')
                ->join('member m','m.id=og.mid')
                ->where('og.aid',$aid)
                ->where('og.isfenhong',0)
                ->where('og.status',1)
                ->where('og.paytime','>=',$starttime)
                ->where('og.paytime','<',$endtime);
            if($sysset['fhjiesuanbusiness'] != 1){ //多商户的商品是否参与分红
                $maidan_orderlist = $maidan_orderlist
                    ->where('og.bid',0);
            }
            $maidan_orderlist = $maidan_orderlist
                ->field('og.*,m.nickname,m.headimg')
                ->order('og.id desc')
                ->select()
                ->toArray();
            if($maidan_orderlist){
                foreach($maidan_orderlist as $mdk=>$mdv){
                    $mdv['proid']            = 0;
                    $mdv['name']            = $mdv['title'];
                    $mdv['real_totalprice'] = $mdv['paymoney'];
                    $mdv['cost_price']      = 0;
                    $mdv['num']             = 1;
                    $mdv['module']          = 'maidan';
                    $oglist[]               = $mdv;
                    Db::name('maidan_order')->where('id',$mdv['id'])->update(['isfenhong'=>1]);
                }
                unset($mdv);
            }
        }
        //多商户买单订单，按商户地址结算区域分红
        if(getcustom('maidan_area_fenhong',$aid) && $sysset['fhjiesuanbusiness'] == 1){
            $maidanWhere = [];
            $maidanWhere[] = ['og.aid','=',$aid];
            $maidanWhere[] = ['og.bid','>',0];
            $maidanWhere[] = ['og.status','=',1];
            $maidanWhere[] = ['og.isfenhong','=',0];
            $maidanWhere[] = ['og.paytime','>=',$starttime];
            $maidanWhere[] = ['og.paytime','<',$endtime];
            $oglistM = Db::name('maidan_order')->alias('og')->field("og.*,og.paymoney real_totalprice,og.title name,'1' as num,'maidan' as module,0 proid")->where($maidanWhere)->select()->toArray();
            if($oglistM){
                $ids = array_column($oglistM,'id');
                Db::name('maidan_order')->where('id','in',$ids)->update(['isfenhong'=>1]);
                $oglist = array_merge($oglist,$oglistM);
            }
        }
        //多商户收银订单，按商户地址结算区域分红
        if(getcustom('cashier_area_fenhong',$aid) && $sysset['fhjiesuanbusiness'] == 1){
            $cashierWhere = [];
            $cashierWhere[] = ['og.aid','=',$aid];
            $cashierWhere[] = ['og.bid','>',0];
            $cashierWhere[] = ['o.status','=',1];
            $cashierWhere[] = ['og.isfenhong','=',0];
            $cashierWhere[] = ['o.paytime','>=',$starttime];
            $cashierWhere[] = ['o.paytime','<',$endtime];
            $oglistC = Db::name('cashier_order_goods')->alias('og')->field("og.*,o.paytime,'cashier' as module")->join('cashier_order o','o.id=og.orderid')->where($cashierWhere)->select()->toArray();
            if($oglistC){
                $ids = array_column($oglistC,'id');
                Db::name('cashier_order_goods')->where('id','in',$ids)->update(['isfenhong'=>1]);
                $oglist = array_merge($oglist,$oglistC);
            }
        }
        if(getcustom('maidan_fenhong_new',$aid) && $sysset['maidanfenhong'] && !getcustom('maidan_area_fenhong',$aid)){
            //买单
            $maidan_orderlist = Db::name('maidan_order')
                ->alias('og')
                ->join('member m','m.id=og.mid')
                ->where('og.aid',$aid)
                ->where('og.isfenhong',0)
                ->where('og.status',1)
                ->where('og.paytime','>=',$starttime)
                ->where('og.paytime','<',$endtime);
            if($sysset['fhjiesuanbusiness'] != 1){ //多商户的商品是否参与分红
                $maidan_orderlist = $maidan_orderlist
                    ->where('og.bid',0);
            }
            $maidan_orderlist = $maidan_orderlist
                ->field('og.*,m.nickname,m.headimg')
                ->order('og.id desc')
                ->select()
                ->toArray();
            if($maidan_orderlist){
                foreach($maidan_orderlist as $mdk=>$mdv){
                    $mdv['proid']            = 0;
                    $mdv['name']            = $mdv['title'];
                    $mdv['real_totalprice'] = $mdv['paymoney'];
                    //买单分红结算方式
                    if($sysset['maidanfenhong_type'] == 1){
                        //按利润结算时直接把销售额改成利润
                        $mdv['real_totalprice'] = $mdv['paymoney'] - $mdv['cost_price'];
                    }
                    $mdv['cost_price']      = 0;
                    $mdv['num']             = 1;
                    $mdv['module']          = 'maidan';
                    $oglist[]               = $mdv;
                    Db::name('maidan_order')->where('id',$mdv['id'])->update(['isfenhong'=>1]);
                }
                unset($mdv);
            }
        }
        if(getcustom('restaurant_fenhong',$aid)){
            //点餐
            $diancan_oglist = Db::name('restaurant_shop_order_goods')->alias('og')->field('og.*,o.area2,o.paytime')->join('restaurant_shop_order o','o.id=og.orderid')->where($where)->select()->toArray();
            if($diancan_oglist){
                foreach($diancan_oglist as $dck=>$dcv){
                    $dcv['module'] = 'diancan';
                    $oglist[]      = $dcv;
                }
                Db::name('restaurant_shop_order_goods')->alias('og')->field('og.*,o.area2,o.paytime')->join('restaurant_shop_order o','o.id=og.orderid')->where($where)->update(['og.isfenhong'=>1]);
            }
            //外卖
            $takeaway_oglist = Db::name('restaurant_takeaway_order_goods')->alias('og')->field('og.*,o.area2,o.paytime')->join('restaurant_takeaway_order o','o.id=og.orderid')->where($where)->select()->toArray();
            if($takeaway_oglist){
                foreach($takeaway_oglist as $twk=>$twv){
                    $twv['module'] = 'takeaway';
                    $oglist[]      = $twv;
                }
                Db::name('restaurant_takeaway_order_goods')->alias('og')->field('og.*,o.area2,o.paytime')->join('restaurant_takeaway_order o','o.id=og.orderid')->where($where)->update(['og.isfenhong'=>1]);
            }
        }
        if(getcustom('fenhong_times_coupon',$aid)){
            $cwhere[] =['og.isfenhong','=',0]; 
            $cwhere[] =['og.status','=',1]; 
            $cwhere[] =['og.paytime','>=',$starttime]; 
            $cwhere[] =['og.paytime','<',$endtime];
            if($sysset['fhjiesuanbusiness'] != 1){ //多商户的商品是否参与分红
                $cwhere[] =['og.bid','=',0];
            }
            
            $couponorderlist = Db::name('coupon_order')->alias('og')
                ->join('member m','m.id=og.mid')
                ->where($cwhere)
                ->field('og.*,m.nickname,m.headimg')
                ->order('og.id desc')
                ->select()
                ->toArray();
            
            foreach($couponorderlist as $k=>$v){
                $v['name'] = $v['title'];
                $v['real_totalprice'] = $v['price'];
                $v['cost_price'] = 0;
                $v['module'] = 'coupon';
                $oglist[] = $v;
            }
            if($couponorderlist){
                Db::name('coupon_order')->alias('og')->field('og.*,m.nickname,m.headimg')->join('member m','m.id=og.mid')->where($cwhere)->update(['og.isfenhong'=>1]);
            }
        }
        if(getcustom('fenhong_kecheng',$aid)){
            //课程直接支付，无区域分红
            $kwhere = [];
            $kwhere[] = ['og.aid','=',$aid];
            $kwhere[] = ['og.isfenhong','=',0];
            $kwhere[] = ['og.status','=',1];
            $kwhere[] = ['og.paytime','>=',$starttime];
            $kwhere[] = ['og.paytime','<',$endtime];
            if($sysset['fhjiesuanbusiness'] != 1){
                $kwhere[] = ['og.bid','=','0'];
            }
            $kechenglist = Db::name('kecheng_order')
                ->alias('og')
                ->join('member m','m.id=og.mid')
                ->where($kwhere)
                ->field('og.*," " as area2,m.nickname,m.headimg')
                ->select()
                ->toArray();
            if($kechenglist){
                foreach($kechenglist as $v){
                    $v['name']            = $v['title'];
                    $v['real_totalprice'] = $v['totalprice'];
                    $v['cost_price']      = 0;
                    $v['module']          = 'kecheng';
                    $v['num']             = 1;
                    $oglist[]             = $v;
                    Db::name('kecheng_order')->where('id',$v['id'])->update(['isfenhong'=>1]);
                }
            }
        }
        if(getcustom('business_reward_member',$aid)){
            //商家打赏订单
            $rwhere = [];
            $rwhere[] = ['og.aid','=',$aid];
            $rwhere[] = ['og.isfenhong','=',0];
            $rwhere[] = ['og.status','=',1];
            $rwhere[] = ['og.paytime','>=',$starttime];
            $rwhere[] = ['og.paytime','<',$endtime];

            $reward_list = Db::name('business_reward_order')
                ->alias('og')
                ->join('member m','m.id=og.to_mid')
                ->where($rwhere)
                ->field('og.*," " as area2,m.nickname,m.headimg')
                ->select()
                ->toArray();
            if($reward_list){
                foreach($reward_list as $v){
                    $v['mid'] = $v['to_mid'];
                    $v['name']            = $v['name'];
                    $v['real_totalprice'] = $v['pay_money'];
                    $v['cost_price']      = 0;
                    $v['module']          = 'business_reward';
                    $v['num']             = 1;
                    $oglist[]             = $v;
                    Db::name('business_reward_order')->where('id',$v['id'])->update(['isfenhong'=>1]);
                }
            }
        }
        if(getcustom('hotel',$aid)){
            $where3 = [];
            $where3[] = ['og.aid','=',$aid];
            $where3[] = ['og.isfenhong','=',0];
            if($sysset['fhjiesuanbusiness'] == 1){ //多商户的商品是否参与分红
                
            }else{
                $where3[] = ['og.bid','=','0'];
            }
            if($sysset['fhjiesuantime_type'] == 1) { //分红结算时间类型 0收货后，1付款后
                $where3[] = ['og.status','in',[2,3,4]];
                $where3[] = ['og.createtime','>=',$starttime];
                $where3[] = ['og.createtime','<',$endtime];
            }else{
                $where3[] = ['og.status','=','4'];
            }
            $hotelorderlist = Db::name('hotel_order')->alias('og')->field('og.*,m.nickname,m.headimg')->join('member m','m.id=og.mid')->where($where3)->select()->toArray();
            foreach($hotelorderlist as $k=>$v){
                $v['name'] = $v['title'];
                $v['real_totalprice'] = $v['sell_price'];
                $v['cost_price'] = $v['cost_price'] ?? 0;
                $v['module'] = 'hotel';
                $v['num'] = $v['totalnum'];
                $oglist[] = $v;
            }
            if($hotelorderlist){
                Db::name('hotel_order')->alias('og')->field('og.*,m.nickname,m.headimg')->join('member m','m.id=og.mid')->where($where3)->update(['og.isfenhong'=>1]);
            }
        }

        self::gdfenhong($aid,$sysset,$oglist,$starttime,$endtime);

        self::teamfenhong($aid,$sysset,$oglist,$starttime,$endtime);
        if(getcustom('teamfenhong_jiandan',$aid)){
            self::teamfenhong_jiandan($aid,$sysset,$oglist,$starttime,$endtime);
        }
        self::areafenhong($aid,$sysset,$oglist,$starttime,$endtime);
        self::product_teamfenhong($aid,$sysset,$oglist,$starttime,$endtime);
        self::level_teamfenhong($aid,$sysset,$oglist,$starttime,$endtime);
        self::gongxian_fenhong($aid,$sysset,$oglist,$starttime,$endtime);
        self::touzi_fenhong($aid,$sysset,$oglist,$starttime,$endtime);
        if(getcustom('fenhong_gudong_huiben',$aid)){
            self::gdfenhong_huiben($aid,$sysset,$oglist,$starttime,$endtime);
        }
        if(getcustom('business_teamfenhong',$aid)){
            //商家团队分红
            //买单
            $maidan_orderlist = Db::name('maidan_order')
                ->alias('og')
                ->join('member m','m.id=og.mid')
                ->where('og.aid',$aid)
                ->where('og.isfenhong',0)
                ->where('og.status',1)
                ->where('og.paytime','>=',$starttime)
                ->where('og.paytime','<',$endtime)
                ->where('og.bid','<>',0)
                ->field('og.*,m.nickname,m.headimg')
                ->order('og.id desc')
                ->select()
                ->toArray();
            if($maidan_orderlist){
                foreach($maidan_orderlist as $mdk=>$mdv){
                    $mdv['proid']            = 0;
                    $mdv['name']            = $mdv['title'];
                    $mdv['real_totalprice'] = $mdv['paymoney'];
                    $mdv['cost_price']      = 0;
                    $mdv['num']             = 1;
                    $mdv['module']          = 'maidan';
                    $oglist[]               = $mdv;
                    Db::name('maidan_order')->where('id',$mdv['id'])->update(['isfenhong'=>1]);
                }
                unset($mdv);
            }
            self::business_teamfenhong($aid,$sysset,$oglist,$starttime,$endtime);
        }
        if(getcustom('teamfenhong_jicha',$aid)){
            self::teamfenhong_jicha($aid,$sysset,$oglist,$starttime,$endtime);
        }
        if(getcustom('team_leader_fh',$aid)){
            self::teamleader_fenhong($aid,$sysset,$oglist,$starttime,$endtime);
        }
        if(getcustom('team_jiandian',$aid)){
            self::team_jiandian($aid,$sysset,$oglist,$starttime,$endtime);
        }
        if(getcustom('team_fuchijin',$aid)){
            self::team_fuchijin($aid,$sysset,$oglist,$starttime,$endtime);
        }

    }
    //股东分红
    public static function gdfenhong($aid,$sysset,$oglist,$starttime=0,$endtime=0,$isyj=0,$yjmid=0){
        if($endtime == 0) $endtime = time();

        if(getcustom('fenhong_business_item_switch',$aid)){
            //查找开启的多商户
            $bids = Db::name('business')->where('aid',$aid)->where('gdfenhong_status',1)->column('id');
            $bids = array_merge([0],$bids);
        }
        if(getcustom('maidan_fenhong_new',$aid)){
            $bids_maidan = Db::name('business')->where('maidan_gudong',1)->column('id');
            $bids_maidan = array_merge([0],$bids_maidan);
        }
        //是否开启股东分红叠加
        $gdfenhong_add = 0;
        if(getcustom('gdfenhong_add',$aid) && $sysset['gdfenhong_add'] && empty($sysset['partner_jiaquan'])){
            //与股东加权分红冲突，如果开启了加权分红这里失效
            $gdfenhong_add = 1;
        }
        if($isyj == 1 && !$oglist){
            //多商户的商品是否参与分红
            if($sysset['fhjiesuanbusiness'] == 1){
                $bwhere = '1=1';
            }else{
                $bwhere = [['og.bid','=','0']];
            }
            if(getcustom('fenhong_business_item_switch',$aid)){
                $oglist = Db::name('shop_order_goods')->alias('og')->field('og.*,o.area2,m.nickname,m.headimg')
                    ->where('og.bid','in',$bids)
                    ->where('og.aid',$aid)->where('og.isfenhong',0)->where('og.status','in',[1,2,3])->where('og.refund_num',0)->join('shop_order o','o.id=og.orderid')->join('member m','m.id=og.mid')->where($bwhere)->order('og.id desc')->select()->toArray();
            }else{
                $oglist = Db::name('shop_order_goods')->alias('og')->field('og.*,o.area2,m.nickname,m.headimg')->where('og.aid',$aid)->where('og.isfenhong',0)->where('og.status','in',[1,2,3])->where('og.refund_num',0)->join('shop_order o','o.id=og.orderid')->join('member m','m.id=og.mid')->where($bwhere)->order('og.id desc')->select()->toArray();
            }
            if(getcustom('yuyue_fenhong',$aid)){
                $yyorderlist = Db::name('yuyue_order')->alias('og')->field('og.*,m.nickname,m.headimg')->where('og.aid',$aid)->where('og.isfenhong',0)->where('og.status','in',[1,2,3])->join('member m','m.id=og.mid')->where($bwhere)->order('og.id desc')->select()->toArray();
                foreach($yyorderlist as $k=>$v){
                    $v['name'] = $v['proname'];
                    $v['real_totalprice'] = $v['totalprice'];
                    $v['cost_price'] = $v['cost_price'] ?? 0;
                    $v['module'] = 'yuyue';
                    $oglist[] = $v;
                }
            }
            if(getcustom('scoreshop_fenhong',$aid)){
                $scoreshopoglist = Db::name('scoreshop_order_goods')->alias('og')->field('og.*,m.nickname,m.headimg')->where('og.aid',$aid)->where('og.isfenhong',0)->where('og.status','in',[1,2,3])->join('member m','m.id=og.mid')->where($bwhere)->order('og.id desc')->select()->toArray();
                foreach($scoreshopoglist as $v){
                    $v['real_totalprice'] = $v['totalmoney'];
                    $v['module'] = 'scoreshop';
                    $oglist[] = $v;
                }
            }
            if(getcustom('luckycollage_fenhong',$aid)){
                $lcorderlist = Db::name('lucky_collage_order')->alias('og')->field('og.*,m.nickname,m.headimg')->where('og.aid',$aid)->where('og.isfenhong',0)->where('og.status','in',[1,2,3])->join('member m','m.id=og.mid')->where($bwhere)->whereRaw('og.buytype=1 or og.iszj=1')->where('og.isjiqiren',0)->order('og.id desc')->select()->toArray();
                foreach($lcorderlist as $k=>$v){
                    $v['name'] = $v['proname'];
                    $v['real_totalprice'] = $v['totalprice'];
                    $v['cost_price'] = 0;
                    $v['module'] = 'luckycollage';
                    $oglist[] = $v;
                }
            }
            if(getcustom('maidan_fenhong',$aid) && !getcustom('maidan_fenhong_new',$aid)){
                //买单分红
                $maidan_orderlist = Db::name('maidan_order')
                    ->alias('og')
                    ->join('member m','m.id=og.mid')
                    ->where('og.aid',$aid)
                    ->where('og.isfenhong',0)
                    ->where('og.status',1)
                    ->where($bwhere)
                    ->field('og.*,m.nickname,m.headimg')
                    ->order('og.id desc')
                    ->select()
                    ->toArray();
                if($maidan_orderlist){
                    foreach($maidan_orderlist as $mdk=>$mdv){
                        $mdv['proid']            = 0;
                        $mdv['name']             = $mdv['title'];
                        $mdv['real_totalprice']  = $mdv['paymoney'];
                        $mdv['cost_price']       = 0;
                        $mdv['num']              = 1;
                        $mdv['module']           = 'maidan';
                        $oglist[] = $mdv;
                    }
                    unset($mdv);
                }
            }
            if(getcustom('maidan_fenhong_new',$aid) && $sysset['maidanfenhong']){
                //买单分红
                $bwhere_maidan = [['og.bid', 'in', $bids_maidan]];
                $maidan_orderlist = Db::name('maidan_order')
                    ->alias('og')
                    ->join('member m','m.id=og.mid')
                    ->where('og.aid',$aid)
                    ->where('og.isfenhong',0)
                    ->where('og.status',1)
                    ->where($bwhere_maidan)
                    ->field('og.*,m.nickname,m.headimg')
                    ->order('og.id desc')
                    ->select()
                    ->toArray();
                if($maidan_orderlist){
                    foreach($maidan_orderlist as $mdk=>$mdv){
                        $mdv['name']             = $mdv['title'];
                        $mdv['real_totalprice']  = $mdv['paymoney'];
                        //买单分红结算方式
                        if($sysset['maidanfenhong_type'] == 1){
                            //按利润结算时直接把销售额改成利润
                            $mdv['real_totalprice'] = $mdv['paymoney'] - $mdv['cost_price'];
                        }
                        $mdv['cost_price']       = 0;
                        $mdv['num']              = 1;
                        $mdv['module']           = 'maidan';
                        $oglist[] = $mdv;
                    }
                }
            }
            if(getcustom('restaurant_fenhong',$aid)){
                //点餐
                $diancan_oglist = Db::name('restaurant_shop_order_goods')->alias('og')->field('og.*,o.area2,m.nickname,m.headimg')->where('og.aid',$aid)->where('og.isfenhong',0)->where('og.status','in',[1,2,3])->join('restaurant_shop_order o','o.id=og.orderid')->join('member m','m.id=og.mid')->where($bwhere)->order('og.id desc')->select()->toArray();
                if($diancan_oglist){
                    foreach($diancan_oglist as $dck=>$dcv){
                        $dcv['module'] = 'diancan';
                        $oglist[]      = $dcv;
                    }
                    unset($dcv);
                }
                //外卖
                $takeaway_oglist = Db::name('restaurant_takeaway_order_goods')->alias('og')->field('og.*,o.area2,m.nickname,m.headimg')->where('og.aid',$aid)->where('og.isfenhong',0)->where('og.status','in',[1,2,3])->join('restaurant_takeaway_order o','o.id=og.orderid')->join('member m','m.id=og.mid')->where($bwhere)->order('og.id desc')->select()->toArray();
                if($takeaway_oglist){
                    foreach($takeaway_oglist as $twk=>$twv){
                        $twv['module'] = 'takeaway';
                        $oglist[]      = $twv;
                    }
                    unset($twv);
                }
            }
            if(getcustom('fenhong_times_coupon',$aid)){
                $cwhere[] =['og.isfenhong','=',0];
                $cwhere[] =['og.status','=',1];
                $cwhere[] =['og.paytime','>=',$starttime];
                $cwhere[] =['og.paytime','<',$endtime];
                if($sysset['fhjiesuanbusiness'] != 1){ //多商户的商品是否参与分红
                    $cwhere[] =['og.bid','=',0];
                }
                $couponorderlist = Db::name('coupon_order')->alias('og')
                    ->join('member m','m.id=og.mid')
                    ->where($cwhere)
                    ->field('og.*,m.nickname,m.headimg')
                    ->order('og.id desc')
                    ->select()
                    ->toArray();
                foreach($couponorderlist as $k=>$v){
                    $v['name'] = $v['title'];
                    $v['real_totalprice'] = $v['price'];
                    $v['cost_price'] = 0;
                    $v['module'] = 'coupon';
                    $v['num'] = 1;
                    $oglist[] = $v;
                }
            }
            if(getcustom('fenhong_kecheng',$aid)){
                $kwhere = [];
                $kwhere[] = ['og.aid','=',$aid];
                $kwhere[] = ['og.isfenhong','=',0];
                $kwhere[] = ['og.status','=',1];
                $kwhere[] = ['og.paytime','>=',$starttime];
                $kwhere[] = ['og.paytime','<',$endtime];
                if($sysset['fhjiesuanbusiness'] != 1){
                    $kwhere[] = ['og.bid','=','0'];
                }
                $kechenglist = Db::name('kecheng_order')
                    ->alias('og')
                    ->join('member m','m.id=og.mid')
                    ->where($kwhere)
                    ->field('og.*," " as area2,m.nickname,m.headimg')
                    ->select()
                    ->toArray();
                if($kechenglist){
                    foreach($kechenglist as $v){
                        $v['name']            = $v['title'];
                        $v['real_totalprice'] = $v['totalprice'];
                        $v['cost_price']      = 0;
                        $v['module']          = 'kecheng';
                        $v['num']             = 1;
                        $oglist[]             = $v;
                    }
                }
            }
            if(getcustom('hotel',$aid)){
                $hotelorderlist = Db::name('hotel_order')->alias('og')->field('og.*,m.nickname,m.headimg')->where('og.aid',$aid)->where('og.isfenhong',0)->where('og.status','in',[2,3,4])->join('member m','m.id=og.mid')->where($bwhere)->order('og.id desc')->select()->toArray();
                foreach($hotelorderlist as $k=>$v){
                    $v['name'] = $v['title'];
                    $v['real_totalprice'] = $v['sell_price'];
                    $v['cost_price'] = $v['cost_price'] ?? 0;
                    $v['module'] = 'hotel';
                    $oglist[] = $v;
                }
            }
        }
        if(getcustom('gdfenhong_level',$aid)){
            //升级费用用于股东分红
            $gd_levelids = Db::name('member_level')->where('aid',$aid)->where('apply_paygudong',1)->column('id');
            if($gd_levelids){
                $level_orders = Db::name('member_levelup_order')
                    ->where('aid',$aid)
                    ->where('isfenhong',0)
                    ->where('status',2)
                    ->where('totalprice','>',0)
                    ->whereIn('levelid',$gd_levelids)->select()->toArray();
                if($level_orders){
                    foreach($level_orders as $v){
                        $v['name']            = $v['title'];
                        $v['real_totalprice'] = $v['totalprice'];
                        $v['cost_price']      = 0;
                        $v['module']          = 'member_levelup';
                        $v['num']             = 1;
                        $oglist[]             = $v;
                        if($isyj==0){
                            Db::name('member_levelup_order')->where('id',$v['id'])->update(['isfenhong'=>1]);
                        }
                    }
                }
            }

        }

        if(getcustom('fenhong_cashier_order',$aid) && $sysset['fenhong_cashier_order_money']){

            //收银台订单
            $cowhere = [];
            $cowhere[] = ['og.aid','=',$aid];
            $cowhere[] = ['og.bid','=',0];
            $cowhere[] = ['og.isfenhong','=',0];
            $cowhere[] = ['o.status','=',1];
            if($starttime) $cowhere[] = ['o.paytime','>=',$starttime];
            if($endtime) $cowhere[] = ['o.paytime','<',$endtime];
            $cashier_order_list = Db::name('cashier_order_goods')
                ->alias('og')
                // ->join('member m','m.id=og.mid')
                ->join('cashier_order o','o.id=og.orderid')
                ->where($cowhere)
                ->field('og.*')
                ->select()
                ->toArray();
            if($cashier_order_list){
                // 0商品价格1成交价格2按销售利润
                foreach($cashier_order_list as $v){
                    if($sysset['fxjiesuantype'] == 0){
                        $v['real_totalprice'] = $v['totalprice'];
                    }
                    $v['name']            = $v['proname'];
                    $v['module']          = 'cashier_order';
                    $oglist[]             = $v;
                    Db::name('cashier_order_goods')->where('id',$v['id'])->update(['isfenhong'=>1]);
                }
            }
        }


        if(!$oglist) return ['commissionyj'=>0,'oglist'=>[]];
        //参与股东分红的等级
        $where_level = [];
        $where_level[] = ['fenhong','>',0];
        if(getcustom('maidan_fenhong_new',$aid)){
            $where_level = [];
            $where_level[] = ['fenhong|fenhong_maidan_percent','>',0];
        }
        $fhlevellist = Db::name('member_level')->where('aid',$aid)->where($where_level)->order('sort desc,id desc')->column('*','id');
        if(!$fhlevellist) return ['commissionyj'=>0,'oglist'=>[]];

        if(getcustom('business_reward_member',$aid)){
            //商家打赏订单
            $business_reward_set =Db::name('business_reward_set')->where('aid',$aid)->find();
        }
        //股东最大分红累加低级别的分红上限参数
        if(getcustom('fenhong_max',$aid) && !empty($sysset['fenhong_max_add'])){
            foreach($fhlevellist as $k=>$v){
                $fenhong_max = Db::name('member_level')
                    ->where('aid',$aid)
                    ->where('sort','<',$v['sort'])
                    ->sum('fenhong_max_money');
                $fhlevellist[$k]['fenhong_max_money'] = bcadd($v['fenhong_max_money'],$fenhong_max,2);
            }
        }
        
        $defaultCid = Db::name('member_level_category')->where('aid',$aid)->where('isdefault', 1)->value('id');
        if($defaultCid) {
            $defaultLevelIds = Db::name('member_level')->where('aid',$aid)->where('cid', $defaultCid)->column('id');
        } else {
            $defaultLevelIds = Db::name('member_level')->where('aid',$aid)->column('id');
        }

        $ogids = [];
        $midfhArr = [];
        $newoglist = [];
        $commissionyj = 0;
        $allfenhongprice = 0;
        foreach($oglist as $og){
            if(getcustom('maidan_fenhong_new',$aid) && $og['module']=='maidan'){
                if($og['bid'] > 0 && !in_array($og['bid'],$bids_maidan)){
                    continue;
                }
            }
            if(getcustom('fenhong_business_item_switch') && $og['module']!='maidan'){
                if($og['bid'] > 0 && !in_array($og['bid'],$bids)){
                    continue;
                }
            }
            $levelid_only = 0;
            if(getcustom('partner_parent_only',$aid)){
                //股东分红仅奖励购买人上级等级
                if($sysset['partner_parent_only']){
                    $levelid_only = -1;
                    $pid_og = Db::name('member')->where('id',$og['mid'])->value('pid');
                    if($pid_og)
                        $levelid_only = Db::name('member')->where('id',$pid_og)->value('levelid');
                    else
                        continue;
                }
            }
            if(getcustom('commission2moneypercent',$aid) && $sysset['commission2moneypercent1'] > 0){
                //是否是首单
                $beforeorder = Db::name('shop_order')->where('aid',$aid)->where('mid',$og['mid'])->where('status','in','1,2,3')->where('paytime','<',$og['paytime'])->find();
                if(!$beforeorder){
                    $commissionpercent = 1 - $sysset['commission2moneypercent1'] * 0.01;
                    $moneypercent = $sysset['commission2moneypercent1'] * 0.01;
                }else{
                    $commissionpercent = 1 - $sysset['commission2moneypercent2'] * 0.01;
                    $moneypercent = $sysset['commission2moneypercent2'] * 0.01;
                }
            }else{
                $commissionpercent = 1;
                $moneypercent = 0;
            }

            if($og['module'] == 'yuyue'){
                $product = Db::name('yuyue_product')->where('id',$og['proid'])->find();
            }elseif($og['module'] == 'luckycollage'){
                $product = Db::name('lucky_collage_product')->where('id',$og['proid'])->find();
                if (getcustom('luckycollage_fail_commission',$aid)) {
                  if ($og['iszj'] == 2) {
                    $product['fenhongset'] = $product['fail_fenhongset'];
                    $product['gdfenhongset'] = $product['fail_gdfenhongset'];
                    $product['gdfenhongdata1'] = $product['fail_gdfenhongdata1'];
                    $product['gdfenhongdata2'] = $product['fail_gdfenhongdata2'];
                  }
                }
                if ($product['fenhongset'] == 0) {
                    $product['gdfenhongset'] = -1;
                }
            }elseif($og['module'] == 'coupon'){
                $product = Db::name('coupon')->where('id',$og['cpid'])->find();
            }elseif($og['module'] == 'scoreshop'){
                $product = Db::name('scoreshop_product')->where('id',$og['proid'])->find();
            }elseif($og['module'] == 'kecheng'){
                $product = Db::name('kecheng_list')->where('id',$og['kcid'])->find();
            }elseif($og['module'] == 'business_reward'){
                if(getcustom('business_reward_member',$aid)){
                    //商家打赏订单
                    $product = [
                        'gdfenhongset' => $business_reward_set['gdfenhongset'],
                        'gdfenhongdata1' => $business_reward_set['gdfenhongdata'],
                    ];
                }
            }elseif($og['module'] == 'hotel'){
                $product = Db::name('hotel_room')->where('id',$og['roomid'])->find();
            }elseif($og['module'] == 'cashier_order'){
                $product = Db::name('shop_product')->where('id', $og['proid'])->find();
            }else{
                $product = Db::name('shop_product')->where('id',$og['proid'])->find();
            }
            if(getcustom('maidan_fenhong',$aid) || getcustom('maidan_fenhong_new',$aid)){
                if($og['module'] == 'maidan'){
                    $product = [];
                    $product['gdfenhongset'] = 0;
                }
            }
            if(getcustom('restaurant_fenhong',$aid)){
                if($og['module'] == 'diancan' || $og['module'] == 'takeaway'){
                    $product = [];
                    $product['gdfenhongset'] = 0;
                }
            }

            if($sysset['fhjiesuantype'] == 0){
                $fenhongprice = $og['real_totalprice'];
            }else{
                $fenhongprice = $og['real_totalprice'] - $og['cost_price'] * $og['num'];
            }
            if(getcustom('baikangxie',$aid)){
                $fenhongprice = $og['cost_price'] * $og['num'];
            }
            if($fenhongprice <= 0 && $product['gdfenhongset']!=2 && $product['gdfenhongset']!=3) continue;

            $ogids[] = $og['id'];
            $allfenhongprice = $allfenhongprice + $fenhongprice;
//          $member = Db::name('member')->where('id', $og['mid'])->find();
//          $member_extend = Db::name('member_level_record')->field('mid id,levelid')->where('aid', $aid)->where('mid', $og['mid'])->find();

            if($fhlevellist){
                $lastmidlist = [];
                $old_midlist = [];
                $old_mids = [];
                foreach($fhlevellist as $fhlevel){
                    if(getcustom('partner_parent_only',$aid)){
                        //股东分红仅奖励购买人上级等级
                        if($levelid_only && $fhlevel['id'] != $levelid_only)
                            continue;
                    }
                    if(getcustom('business_fenhong_memberlevel',$aid) && $og['bid'] > 0){
                        $business = Db::name('business')->where('id',$og['bid'])->find();
                        if($business && $business['fenhong_memberlevel']!='' && !in_array($fhlevel['id'],explode(',',$business['fenhong_memberlevel']))) continue;
                    }
                    $where = [];
                    $where[] = ['aid', '=', $aid];
                    $where[] = ['levelid', '=', $fhlevel['id']];
                    $where[] = ['levelstarttime', '<', $og['createtime']]; //判断升级时间
                    $where2 = [];
                    $where2[] = ['ml.aid', '=', $aid];
                    $where2[] = ['ml.levelid', '=', $fhlevel['id']];
                    $where2[] = ['ml.levelstarttime', '<', $og['createtime']];
                    if($fhlevel['fenhong_max_money'] > 0) {
                        $where[] = ['total_fenhong_partner', '<', $fhlevel['fenhong_max_money']];
                        $where2[] = ['m.total_fenhong_partner', '<', $fhlevel['fenhong_max_money']];
                    }

                    if($defaultCid > 0 && $defaultCid != $fhlevel['cid']) {
                        //其他分组
                        if(getcustom('plug_sanyang',$aid)) {
                            if($fhlevel['fenhong_num'] > 0){
                                $midlist = Db::name('member_level_record')->alias('ml')->leftJoin('member m', 'm.id = ml.mid')
                                    ->where($where2)->order('ml.levelstarttime,id')->limit(intval($fhlevel['fenhong_num']))->column('m.id,m.total_fenhong_partner,m.levelstarttime','ml.mid');
                            } else {
                                $midlist = Db::name('member_level_record')->alias('ml')->leftJoin('member m', 'm.id = ml.mid')
                                    ->where($where2)->column('m.id,m.total_fenhong_partner,m.levelstarttime','ml.mid');
                            }
                        }
                    } else {
                        $field = 'id,total_fenhong_partner,levelstarttime,levelid';
                        if(getcustom('fenhong_max',$aid)){
                            $field .= ',fenhong_max';
                        }
                        //默认分组
                        if ($fhlevel['fenhong_num'] > 0) {
                            $midlist = Db::name('member')->where($where)->order('levelstarttime,id')->limit(intval($fhlevel['fenhong_num']))->column($field, 'id');
                        }else{
                            $midlist = Db::name('member')->where($where)->column($field,'id');
                        }
                    }
                    if($midlist){
                        foreach ($midlist as $mk => $memberarr){
                            //购买前最后一条升级记录，如果下单前等级不等于当前等级 则排除（当前等级不断变化，不是完全准确，所以需要对照升级记录表）
                            $levelup_last_log = Db::name('member_levelup_order')->where('aid',$aid)->where('status', 2)
                                ->where('levelup_time', '<', $og['createtime'])->where('mid',$memberarr['id'])->order('levelup_time', 'desc')->find();
                            if($levelup_last_log && $levelup_last_log['levelid'] != $memberarr['levelid']){
                                unset($midlist[$mk]);
                            }
                        }
                    }
                    $levelup_order_mids = Db::name('member_levelup_order')->where('aid',$aid)->where('levelid', $fhlevel['id'])->where('status', 2)
                        ->where('levelup_time', '<', $og['createtime'])->group('mid')->order('levelup_time', 'desc')->column('mid');
                    if($levelup_order_mids) {
                        $levelup_order_list = [];
                        foreach($levelup_order_mids as $lk => $item_lomid){
                            //最后一条记录等于当前等级才有价值
                            $lastlog = Db::name('member_levelup_order')->where('aid',$aid)->where('mid', $item_lomid)->where('status', 2)
                                ->where('levelup_time', '<', $og['createtime'])
                                ->order('levelup_time', 'desc')->find();
                            $levelup_order_list[$item_lomid] = $lastlog['levelid'];
                            if($lastlog['levelid']!=$fhlevel['id']){
                                unset($levelup_order_mids[$lk]);
                            }
                        }
                        $field = 'id,total_fenhong_partner,levelstarttime,levelid';
                        if(getcustom('fenhong_max',$aid)){
                            $field .= ',fenhong_max';
                        }
                        if($levelup_order_mids){
                            $levelup_order_member = Db::name('member')->whereIn('id',$levelup_order_mids)->column($field,'id');
                            $midlist = array_merge((array)$midlist, (array) $levelup_order_member );
                            $midlist = array_unique_map($midlist);
                        }
                    }
                    if($sysset['partner_jiaquan'] == 1){
                        //开启后高等级的股东也会参与低等级的股东分红
                        $oldmidlist = $midlist;
                        $midlist = array_merge((array)$lastmidlist,(array)$midlist);
                        $lastmidlist = array_merge((array)$lastmidlist,(array)$oldmidlist);
                    }
                    if(!$midlist) continue;
                    if(getcustom('fenhong_gudong_yeji',$aid)){
                        //检测业绩条件
                        $fenhong_yeji_lv = $fhlevel['fenhong_yeji_lv']??0;
                        $fenhong_yeji_num = $fhlevel['fenhong_yeji_num']??0;
                        if($fenhong_yeji_num>0){
                            foreach($midlist as $fk=>$fv){
                                $downmids = \app\commons\Member::getdownmids($aid,$fv['id'],$fenhong_yeji_lv);
                                if(empty($downmids)){
                                    $yeji = 0;
                                }else{
                                    $yejiwhere = [];
                                    $yejiwhere[] = ['status','in','1,2,3'];
                                    $yejiwhere[] = ['mid','in',$downmids];
                                    $yeji = Db::name('shop_order')->where('aid',$aid)->where('mid','in',$downmids)->where($yejiwhere)->sum('totalprice');
                                }
                                if($yeji<$fenhong_yeji_num){
                                    unset($midlist[$fk]);
                                }
                            }
                            $midlist = array_values($midlist);
                        }
                    }

                    //股东贡献量分红 开启后可设置一定比例的分红金额按照股东的团队业绩量分红
                    $pergxcommon = 0;
                    if($sysset['partner_gongxian']==1 && $fhlevel['fenhong_gongxian_percent'] > 0){
                        $gongxian_percent = $fhlevel['fenhong'] * $fhlevel['fenhong_gongxian_percent']*0.01;
                        $fhlevel['fenhong'] = $fhlevel['fenhong'] * (1 - $fhlevel['fenhong_gongxian_percent']*0.01);
                        $gongxianCommissionTotal = $gongxian_percent * $fenhongprice * 0.01;
                        //总业绩
                        //$levelids = Db::name('member_level')->where('aid',$aid)->where('sort','<',$fhlevel['sort'])->column('id');
                        //$levelids = Db::name('member_level')->where('aid',$aid)->column('id');
                        $yejiwhere = [];
                        $yejiwhere[] = ['createtime','>=',$starttime];
                        $yejiwhere[] = ['createtime','<',$endtime];
                        $yejiwhere[] = ['isfenhong','=',0];
                        //if($sysset['fhjiesuantime_type'] == 1) {
                            $yejiwhere[] = ['status','in','1,2,3'];
                        //}else{
                        //  $yejiwhere[] = ['status','=','3'];
                        //}
                        $totalyeji = 0;
                        foreach($midlist as $kk=>$item){
                            $downmids = \app\commons\Member::getteammids($aid,$item['id']);
                            $yeji = Db::name('shop_order')->where('aid',$aid)->where('mid','in',$downmids)->where($yejiwhere)->sum('totalprice');
                            $yeji2 = $yeji;
                            if($fhlevel['fenhong_gongxian_peraddnum'] > 0){ //下级每出现一个同级股东增加份额
                                $tjmembercount = Db::name('member')->where('aid',$aid)->where('levelid','=',$fhlevel['id'])->where('find_in_set('.$item['id'].',path)')->count();
                                if($tjmembercount > 0){
                                    $yeji2 = $yeji2 * (1+$tjmembercount*$fhlevel['fenhong_gongxian_peraddnum']);
                                }
                            }
                            $midlist[$kk]['yeji'] = $yeji;
                            $midlist[$kk]['yeji2'] = $yeji2;
                            $totalyeji += $yeji2;
                        }
                        if($totalyeji > 0){
                            $pergxcommon = $gongxianCommissionTotal / $totalyeji;
                        }else{
                            $pergxcommon = 0;
                        }
                    }

                    //$commission = $fhlevel['fenhong'] * $fenhongprice * 0.01 / count($midlist);//平均分给此等级的会员
                    $totalcommission = 0;
                    $totalscore = 0;

                    if($product['gdfenhongset']==1){//按比例
                        $fenhongdata = json_decode($product['gdfenhongdata1'],true);
                        if($fenhongdata){
                            $totalcommission = $fenhongdata[$fhlevel['id']]['commission'] * $fenhongprice * 0.01;
                        }
                    }elseif($product['gdfenhongset']==2){//按固定金额
                        $fenhongdata = json_decode($product['gdfenhongdata2'],true);
                        if($fenhongdata){
                            $totalcommission = $fenhongdata[$fhlevel['id']]['commission'] * $og['num'];
                        }
                    }elseif($product['gdfenhongset']==3){//按固定积分
                        $fenhongdata = json_decode($product['gdfenhongdata2'],true);
                        if($fenhongdata){
                            $totalscore = $fenhongdata[$fhlevel['id']]['score'] * $og['num'];
                        }
                    }elseif($product['gdfenhongset']==4){//按积分比例
                        $fenhongdata = json_decode($product['gdfenhongdata1'],true);
                        if($fenhongdata){
                            $totalscore = round($fenhongdata[$fhlevel['id']]['score'] * $fenhongprice * 0.01);
                        }
                    }elseif($product['gdfenhongset'] == 0){

                        $totalcommission = $fhlevel['fenhong'] * $fenhongprice * 0.01;
                        if(getcustom('fenhong_maidan_percent',$aid) || getcustom('maidan_fenhong_new',$aid)){
                            if($og['module'] == 'maidan'){
                                //买单单独比例
                                if($fhlevel['fenhong_maidan_percent']>=0){
                                    $totalcommission = $fhlevel['fenhong_maidan_percent'] * $fenhongprice * 0.01;
                                }else{
                                    $totalcommission = 0;
                                }
                            }
                        }

                        if($fhlevel['fenhong_score_percent'] > 0){
                            $totalscore = round($fhlevel['fenhong_score_percent'] * $fenhongprice * 0.01);
                        }
                    }
                    if(getcustom('fenhong_removefenxiao',$aid) && $fhlevel['gdfenhong_removefenxiao'] == 1){
                        if($og['parent1'] && $og['parent1commission']){
                            $totalcommission = $totalcommission - $og['parent1commission'];
                        }
                        if($og['parent2'] && $og['parent2commission']){
                            $totalcommission = $totalcommission - $og['parent2commission'];
                        }
                        if($og['parent3'] && $og['parent3commission']){
                            $totalcommission = $totalcommission - $og['parent3commission'];
                        }
                        if($totalcommission <= 0) continue;
                    }

                    if($totalcommission == 0 && $totalscore==0) continue;

                    $commission = $totalcommission / count($midlist);
                    $score = floor($totalscore / count($midlist));
                    
                    //下级每出现一个同级股东增加份额
                    if($fhlevel['fenhong_gongxian_peraddnum'] > 0){ 
                        $gxtotalnum = 0;
                        foreach($midlist as $kk=>$item){
                            $gxnum = 1;
                            $tjmembercount = Db::name('member')->where('aid',$aid)->where('levelid','=',$fhlevel['id'])->where('find_in_set('.$item['id'].',path)')->count();
                            if($tjmembercount > 0){
                                $gxnum += $tjmembercount*$fhlevel['fenhong_gongxian_peraddnum'];
                            }
                            $gxtotalnum += $gxnum;
                            $midlist[$kk]['gxnum'] = $gxnum;
                        }
                        $precommission = $totalcommission / $gxtotalnum;
                    }


                    if(!$midfhArr['level_'.$fhlevel['id']]) $midfhArr['level_'.$fhlevel['id']] = [];
                    $newcommission = 0;
                    if($gdfenhong_add){
                        //叠加股东分红，必须上面计算完平均值之后再合并会员,与股东加权分红冲突
                        if($old_midlist){
                            $old_mids = array_column($old_midlist,'id');
                            $midlist = array_merge($midlist,$old_midlist);
                        }
                        $old_midlist = $midlist;
                    }
                    foreach($midlist as $item){
                        if($fhlevel['fenhong_gongxian_peraddnum'] > 0){
                            $commission = $precommission * $item['gxnum'];
                        }
                        $fenhong_max_money = $fhlevel['fenhong_max_money'];
                        if($gdfenhong_add){
                            //叠加股东分红，高等级使用自身级别的最大值
                            if($old_midlist && in_array($item['id'],$old_mids)){
                                $fenhong_max_money = $fhlevellist[$item['levelid']]['fenhong_max_money']??0;
                            }
                        }
                        //股东最大分红，优先使用会员列表单独设置的参数
                        if(getcustom('fenhong_max',$aid) && $item['fenhong_max']>0){
                            $fenhong_max_money = $item['fenhong_max'];
                        }
                        $mid = $item['id'];
                        if($isyj == 1 && $mid == $yjmid && $commission > 0){
                            $commissionyj += $commission;
                            $og['commission'] = round($commission,2);
                            $og['fhname'] = t('股东分红',$aid);
                            $newoglist[] = $og;
                            break;
                        }
                        $gxcommon = 0;
                        if($pergxcommon > 0){
                            if($item['yeji'] >= $fhlevel['fenhong_gongxian_minyeji']){
                                $gxcommon = $item['yeji2'] * $pergxcommon;
                            }
                        }
                        $newcommission = $commission + $gxcommon;
                        if($midfhArr['level_'.$fhlevel['id']][$mid]){
                            if($fenhong_max_money > 0) {
                                if($midfhArr['level_'.$fhlevel['id']][$mid]['totalcommission'] + $newcommission + $item['total_fenhong_partner'] >$fenhong_max_money) {
                                    //Log::write('大于最大分红金额'.$commission);
                                    $newcommission = $fenhong_max_money - $midfhArr['level_'.$fhlevel['id']][$mid]['totalcommission'] - $item['total_fenhong_partner'];
                                }
                            }
                            if($commissionpercent != 1){
                                $fenhongcommission = round($newcommission*$commissionpercent,2);
                                $fenhongmoney = round($newcommission*$moneypercent,2);
                            }else{
                                $fenhongcommission = $newcommission;
                                $fenhongmoney = 0;
                            }
                            $midfhArr['level_'.$fhlevel['id']][$mid]['totalcommission'] = $midfhArr['level_'.$fhlevel['id']][$mid]['totalcommission'] + $newcommission;
                            $midfhArr['level_'.$fhlevel['id']][$mid]['commission'] = $midfhArr['level_'.$fhlevel['id']][$mid]['commission'] + $fenhongcommission;
                            $midfhArr['level_'.$fhlevel['id']][$mid]['money'] = $midfhArr['level_'.$fhlevel['id']][$mid]['money'] + $fenhongmoney;
                            $midfhArr['level_'.$fhlevel['id']][$mid]['score'] = $score;
                            $midfhArr['level_'.$fhlevel['id']][$mid]['levelid'] = $fhlevel['id'];
                            $midfhArr['level_'.$fhlevel['id']][$mid]['ogids'][] = $og['id'];
                        }else{
                            if($fenhong_max_money > 0) {
                                if($newcommission + $item['total_fenhong_partner'] > $fenhong_max_money) {
                                    $newcommission = $fenhong_max_money - $item['total_fenhong_partner'];
                                }
                            }
                            if($commissionpercent != 1){
                                $fenhongcommission = round($newcommission*$commissionpercent,2);
                                $fenhongmoney = round($newcommission*$moneypercent,2);
                            }else{
                                $fenhongcommission = $newcommission;
                                $fenhongmoney = 0;
                            }
                            $midfhArr['level_'.$fhlevel['id']][$mid] = [
                                'totalcommission'=>$newcommission,
                                'commission'=>$fenhongcommission,
                                'money'=>$fenhongmoney,
                                'score'=>$score,
                                'ogids'=>[$og['id']],
                                'module'=>$og['module'] ?? 'shop',
                            ];
                        }
                        if(getcustom('commission_orderrefund_deduct',$aid) && $isyj == 0){
                            self::fhrecord($aid,$mid,$fenhongcommission,$score,$og['id'],$og['module'] ?? 'shop','fenhong',t('股东分红',$aid));
                        }
                    }
                }
            }
            if($isyj == 0 && $sysset['fhjiesuanhb'] == 0) {
                if($midfhArr){
                    foreach($midfhArr as $levelstr=>$midfhArr2){
                        $levelid = explode('_',$levelstr)[1];
                        $levelname = $fhlevellist[$levelid]['name'];
                        $remark = t('股东分红',$aid);
                        if(getcustom('partner_jiaquan',$aid)){
                            $remark = '['.$levelname.']'.t('股东分红',$aid);
                        }
                        self::fafang($aid,$midfhArr2,'fenhong',$remark);
                    }
                    //根据分红奖团队收益
                    if(getcustom('teamfenhong_shouyi',$aid)){
                        self::teamshouyi($aid,$sysset,$midfhArr,$oglist,$isyj,$yjmid,$commissionpercent,$moneypercent);
                    }
                }
                $midfhArr = [];
            }
        }
        if($isyj == 0 && $sysset['fhjiesuanhb'] == 1) {
            if($midfhArr){
                foreach($midfhArr as $levelstr=>$midfhArr2){
                    $levelid = explode('_',$levelstr)[1];
                    $levelname = $fhlevellist[$levelid]['name'];
                    $remark = t('股东分红',$aid);
                    if(getcustom('partner_jiaquan',$aid)){
                        $remark = '['.$levelname.']'.t('股东分红',$aid);
                    }
                    self::fafang($aid,$midfhArr2,'fenhong',$remark);
                }
                //根据分红奖团队收益
                if(getcustom('teamfenhong_shouyi',$aid)){
                    self::teamshouyi($aid,$sysset,$midfhArr,$oglist,$isyj,$yjmid,$commissionpercent,$moneypercent);
                }
            }
        }
        if($isyj == 1){
            //计算团队收益预收益
            if(getcustom('teamfenhong_shouyi',$aid)){
                self::teamshouyi($aid,$sysset,$midfhArr,$oglist,$isyj,$yjmid,$commissionpercent,$moneypercent);
                if(!empty($res_lvyou['commissionyj']) && $res_lvyou['commissionyj']>0){
                    $res_lvyou_commissionyj = $res_lvyou['commissionyj']??0;
                    $commissionyj = bcadd($commissionyj,$res_lvyou_commissionyj,2);
                }
            }
            return ['commissionyj'=>round($commissionyj,2),'oglist'=>$newoglist];
        }
    }
    //团队分红
    public static function teamfenhong($aid,$sysset,$oglist,$starttime=0,$endtime=0,$isyj=0,$yjmid=0){
        if($endtime == 0) $endtime = time();
        if(getcustom('fenhong_manual',$aid)) return ['commissionyj'=>0,'oglist'=>[]];
        $teamfenhong_pingji_single_bl_set = getcustom('teamfenhong_pingji_single_bl',$aid);
        $teamfenhong_money_product_status = getcustom('teamfenhong_money_product',$aid);//是否设置等级分销的团队分红每单金额参与产品的单独设置
        if(getcustom('fenhong_business_item_switch',$aid)){
            //查找开启的多商户
            $bids = Db::name('business')->where('aid',$aid)->where('teamfenhong_status',1)->column('id');
            $bids = array_merge([0],$bids);
        }
        if(getcustom('maidan_fenhong_new',$aid)){
            $bids_maidan = Db::name('business')->where('maidan_team',1)->column('id');
            $bids_maidan = array_merge([0],$bids_maidan);
        }
        $teamfenhong_pingji_fenhong = 0;
        if(getcustom('teamfenhong_pingji_fenhong',$aid)){
            $teamfenhong_pingji_fenhong = $sysset['teamfenhong_pingji_fenhong']?:0;
        }
        //是否开启无限极团队分红
        $teamfenhong_wuxian = getcustom('teamfenhong_wuxian',$aid)?:0;
        if($isyj == 1 && !$oglist){
            //多商户的商品是否参与分红
            if($sysset['fhjiesuanbusiness'] == 1){
                $bwhere = '1=1';
            }else{
                $bwhere = [['og.bid','=','0']];
            }
            if(getcustom('fenhong_business_item_switch',$aid)){
                $oglist = Db::name('shop_order_goods')->alias('og')->field('og.*,o.area2,m.nickname,m.headimg')
                    ->where('og.bid','in',$bids)
                    ->where('og.aid',$aid)->where('og.isfenhong',0)->where('og.status','in',[1,2,3])->where('og.refund_num',0)->join('shop_order o','o.id=og.orderid')->join('member m','m.id=og.mid')->where($bwhere)->order('og.id desc')->select()->toArray();
            }else{
                $oglist = Db::name('shop_order_goods')->alias('og')->field('og.*,o.area2,m.nickname,m.headimg')->where('og.aid',$aid)->where('og.isfenhong',0)->where('og.status','in',[1,2,3])->where('og.refund_num',0)->join('shop_order o','o.id=og.orderid')->join('member m','m.id=og.mid')->where($bwhere)->order('og.id desc')->select()->toArray();
            }
                //            dump($oglist);
            if(!$oglist) $oglist = [];
            if(getcustom('yuyue_fenhong',$aid)){
                $yyorderlist = Db::name('yuyue_order')->alias('og')->field('og.*,m.nickname,m.headimg')->where('og.aid',$aid)->where('og.isfenhong',0)->where('og.status','in',[1,2,3])->join('member m','m.id=og.mid')->where($bwhere)->order('og.id desc')->select()->toArray();
                foreach($yyorderlist as $k=>$v){
                    $v['name'] = $v['proname'];
                    $v['real_totalprice'] = $v['totalprice'];
                    $v['cost_price'] = $v['cost_price'] ?? 0;
                    $v['module'] = 'yuyue';
                    $oglist[] = $v;
                }
            }
            if(getcustom('scoreshop_fenhong',$aid)){
                $scoreshopoglist = Db::name('scoreshop_order_goods')->alias('og')->field('og.*,m.nickname,m.headimg')->where('og.aid',$aid)->where('og.isfenhong',0)->where('og.status','in',[1,2,3])->join('member m','m.id=og.mid')->where($bwhere)->order('og.id desc')->select()->toArray();
                foreach($scoreshopoglist as $v){
                    $v['real_totalprice'] = $v['totalmoney'];
                    $v['module'] = 'scoreshop';
                    $oglist[] = $v;
                }
            }
            if(getcustom('luckycollage_fenhong',$aid)){
                $lcorderlist = Db::name('lucky_collage_order')->alias('og')->field('og.*,m.nickname,m.headimg')->where('og.aid',$aid)->where('og.isfenhong',0)->whereRaw('og.buytype=1 or og.iszj=1')->where('og.isjiqiren',0)->where('og.status','in',[1,2,3])->join('member m','m.id=og.mid')->where($bwhere)->order('og.id desc')->select()->toArray();
                foreach($lcorderlist as $k=>$v){
                    $v['name'] = $v['proname'];
                    $v['real_totalprice'] = $v['totalprice'];
                    $v['cost_price'] = 0;
                    $v['module'] = 'luckycollage';
                    $oglist[] = $v;
                }
            }
            if(getcustom('maidan_fenhong',$aid) && !getcustom('maidan_fenhong_new',$aid)){
                //买单分红
                $maidan_orderlist = Db::name('maidan_order')
                    ->alias('og')
                    ->join('member m','m.id=og.mid')
                    ->where('og.aid',$aid)
                    ->where('og.isfenhong',0)
                    ->where('og.status',1)
                    ->where($bwhere)
                    ->field('og.*,m.nickname,m.headimg')
                    ->order('og.id desc')
                    ->select()
                    ->toArray();
                if($maidan_orderlist){
                    foreach($maidan_orderlist as $mdk=>$mdv){
                        $mdv['name']             = $mdv['title'];
                        $mdv['real_totalprice']  = $mdv['paymoney'];
                        $mdv['cost_price']       = 0;
                        $mdv['num']              = 1;
                        $mdv['module']           = 'maidan';
                        $oglist[] = $mdv;
                    }
                }
            }
            if(getcustom('maidan_fenhong_new',$aid) && $sysset['maidanfenhong']){
                //买单分红
                $bwhere_maidan = [['og.bid', 'in', $bids_maidan]];
                $maidan_orderlist = Db::name('maidan_order')
                    ->alias('og')
                    ->join('member m','m.id=og.mid')
                    ->where('og.aid',$aid)
                    ->where('og.isfenhong',0)
                    ->where('og.status',1)
                    ->where($bwhere_maidan)
                    ->field('og.*,m.nickname,m.headimg')
                    ->order('og.id desc')
                    ->select()
                    ->toArray();
                if($maidan_orderlist){
                    foreach($maidan_orderlist as $mdk=>$mdv){
                        $mdv['name']             = $mdv['title'];
                        $mdv['real_totalprice']  = $mdv['paymoney'];
                        //买单分红结算方式
                        if($sysset['maidanfenhong_type'] == 1){
                            //按利润结算时直接把销售额改成利润
                            $mdv['real_totalprice'] = $mdv['paymoney'] - $mdv['cost_price'];
                        }
                        $mdv['cost_price']       = 0;
                        $mdv['num']              = 1;
                        $mdv['module']           = 'maidan';
                        $oglist[] = $mdv;
                    }
                }
            }
            if(getcustom('restaurant_fenhong',$aid)){
                //点餐
                $diancan_oglist = Db::name('restaurant_shop_order_goods')->alias('og')->field('og.*,o.area2,m.nickname,m.headimg')->where('og.aid',$aid)->where('og.isfenhong',0)->where('og.status','in',[1,2,3])->join('restaurant_shop_order o','o.id=og.orderid')->join('member m','m.id=og.mid')->where($bwhere)->order('og.id desc')->select()->toArray();
                if($diancan_oglist){
                    foreach($diancan_oglist as $dck=>$dcv){
                        $dcv['module'] = 'diancan';
                        $oglist[]      = $dcv;
                    }
                    unset($dcv);
                }
                //外卖
                $takeaway_oglist = Db::name('restaurant_takeaway_order_goods')->alias('og')->field('og.*,o.area2,m.nickname,m.headimg')->where('og.aid',$aid)->where('og.isfenhong',0)->where('og.status','in',[1,2,3])->join('restaurant_takeaway_order o','o.id=og.orderid')->join('member m','m.id=og.mid')->where($bwhere)->order('og.id desc')->select()->toArray();
                if($takeaway_oglist){
                    foreach($takeaway_oglist as $tak=>$tav){
                        $tav['module'] = 'takeaway';
                        $oglist[]      = $tav;
                    }
                    unset($tav);
                }
            }
            if(getcustom('fenhong_times_coupon',$aid)){
                $cwhere[] =['og.isfenhong','=',0];
                $cwhere[] =['og.status','=',1];
                $cwhere[] =['og.paytime','>=',$starttime];
                $cwhere[] =['og.paytime','<',$endtime];
                if($sysset['fhjiesuanbusiness'] != 1){ //多商户的商品是否参与分红
                    $cwhere[] =['og.bid','=',0];
                }
                $couponorderlist = Db::name('coupon_order')->alias('og')
                    ->join('member m','m.id=og.mid')
                    ->where($cwhere)
                    ->field('og.*,m.nickname,m.headimg')
                    ->order('og.id desc')
                    ->select()
                    ->toArray();
                foreach($couponorderlist as $k=>$v){
                    $v['name'] = $v['title'];
                    $v['real_totalprice'] = $v['price'];
                    $v['cost_price'] = 0;
                    $v['module'] = 'coupon';
                    $oglist[] = $v;
                }
            }
            if(getcustom('fenhong_kecheng',$aid)){
                $kwhere = [];
                $kwhere[] = ['og.aid','=',$aid];
                $kwhere[] = ['og.isfenhong','=',0];
                $kwhere[] = ['og.status','=',1];
                $kwhere[] = ['og.paytime','>=',$starttime];
                $kwhere[] = ['og.paytime','<',$endtime];
                if($sysset['fhjiesuanbusiness'] != 1){
                    $kwhere[] = ['og.bid','=','0'];
                }
                $kechenglist = Db::name('kecheng_order')
                    ->alias('og')
                    ->join('member m','m.id=og.mid')
                    ->where($kwhere)
                    ->field('og.*," " as area2,m.nickname,m.headimg')
                    ->select()
                    ->toArray();
                if($kechenglist){
                    foreach($kechenglist as $v){
                        $v['name']            = $v['title'];
                        $v['real_totalprice'] = $v['totalprice'];
                        $v['cost_price']      = 0;
                        $v['module']          = 'kecheng';
                        $v['num']             = 1;
                        $oglist[]             = $v;
                    }
                }
            }
            if(getcustom('hotel',$aid)){
                $hotelorderlist = Db::name('hotel_order')->alias('og')->field('og.*,m.nickname,m.headimg')->where('og.aid',$aid)->where('og.isfenhong',0)->where('og.status','in',[2,3,4])->join('member m','m.id=og.mid')->where($bwhere)->order('og.id desc')->select()->toArray();
                foreach($hotelorderlist as $k=>$v){
                    $v['name'] = $v['title'];
                    $v['real_totalprice'] = $v['sell_price'];
                    $v['cost_price'] = $v['cost_price'] ?? 0;
                    $v['module'] = 'hotel';
                    $v['num']    = $v['totalnum'];
                    $oglist[] = $v;
                }
            }
        }
        if(getcustom('business_reward_member',$aid)){
            //商家打赏订单
            $business_reward_set =Db::name('business_reward_set')->where('aid',$aid)->find();
        }
        //        dump($oglist);
        //参与团队分红的等级
        $teamfhlevellist = Db::name('member_level')->where('aid',$aid)->where('teamfenhonglv','>','0')->column('*','id');
        if(!$teamfhlevellist) return ['commissionyj'=>0,'oglist'=>[]];

        if(getcustom('teamfenhong_pingji',$aid)){
            //如果产品存在单独设置团队分红平级奖奖励金额，那就取消级别设置的每单奖励
            foreach($oglist as $og){
                if(empty($og['module'])){
                    $product_teamfenhongpjset = Db::name('shop_product')->where('id',$og['proid'])->value('teamfenhongpjset');
                    if($product_teamfenhongpjset==2){
                        foreach($teamfhlevellist as $levelid=>$levelinfo){
                            $teamfhlevellist[$levelid]['teamfenhong_pingji_money'] = 0;
                        }
                        break;
                    }
                }
            }
        }
        if(getcustom('teamfenhong_pingji',$aid)){
            if(getcustom('hotel',$aid)){
                //如果存在单独设置团队分红平级奖奖励金额，那就取消级别设置的每单奖励
                foreach($oglist as $og){
                    if(empty($og['module'])){
                        $product_teamfenhongpjset = Db::name('hotel_room')->where('id',$og['roomid'])->value('teamfenhongpjset');
                        if($product_teamfenhongpjset==2){
                            foreach($teamfhlevellist as $levelid=>$levelinfo){
                                $teamfhlevellist[$levelid]['teamfenhong_pingji_money'] = 0;
                            }
                            break;
                        }
                    }
                }
            }
         
        }

        if(getcustom('luckycollage_teamfenhong',$aid)){
            if($sysset['fhjiesuanbusiness'] == 1){
                $bwhere2 = '1=1';
            }else{
                $bwhere2 = [['bid','=','0']];
            }
            if($sysset['fhjiesuantime_type'] == 1) {
                $lkorderlist = Db::name('lucky_collage_order')->where('isfenhong',0)->where('status','in',[1,2,3])->where('iszj',1)->where('isjiqiren',0)->where($bwhere2)->where('createtime','>=',$starttime)->where('createtime','<',$endtime)->select()->toArray();
                if($lkorderlist && $isyj ==0){
                    Db::name('lucky_collage_order')->where('isfenhong',0)->where('status','in',[1,2,3])->where('iszj',1)->where('isjiqiren',0)->where($bwhere2)->where('createtime','>=',$starttime)->where('createtime','<',$endtime)->update(['isfenhong'=>1]);
                }
            } else {
                $lkorderlist = Db::name('lucky_collage_order')->where('isfenhong',0)->where('status',3)->where('iszj',1)->where('isjiqiren',0)->where($bwhere2)->where('createtime','>=',$starttime)->where('createtime','<',$endtime)->select()->toArray();
                if($lkorderlist && $isyj ==0){
                    Db::name('lucky_collage_order')->where('isfenhong',0)->where('status',3)->where('iszj',1)->where('isjiqiren',0)->where($bwhere2)->where('createtime','>=',$starttime)->where('createtime','<',$endtime)->update(['isfenhong'=>1]);
                }
            }
            foreach($lkorderlist as $k=>$v){
                $v['name'] = $v['proname'];
                $v['real_totalprice'] = $v['totalprice'];
                if($isyj == 1){
                    $member = Db::name('member')->where('id',$v['mid'])->find();
                    $v['headimg'] = $member['headimg'];
                    $v['nickname'] = $member['nickname'];
                }
                $v['module'] = 'luckycollage2';
                $oglist[] = $v;
            }
        }
        if(!$oglist) return ['commissionyj'=>0,'oglist'=>[]];
        
        $defaultCid = Db::name('member_level_category')->where('aid',$aid)->where('isdefault', 1)->value('id');
        if($defaultCid) {
            $defaultLevelIds = Db::name('member_level')->where('aid',$aid)->where('cid', $defaultCid)->column('id');
        } else {
            $defaultLevelIds = Db::name('member_level')->where('aid',$aid)->column('id');
        }
        
        $isjicha = ($sysset['teamfenhong_differential'] == 1 ? true : false);
        $allfenhongprice = 0;
        $ogids = [];
        $midteamfhArr = [];
        $midteamfhArrNew = [];
        $midteamfhArrPj = [];//平级奖单独拆出
        $midteamfhArrBole = [];//伯乐奖单独拆出
        $teamfenhong_orderids = [];//按单奖励类 每单只发一次
        $teamfenhong_orderids_pj = [];
        $teamfenhong_orderids_cat = [];
        $newoglist = [];
        $commissionyj = 0;
        //团队分红平级奖仅限直推上级
        $yueji_limit = 0;
        if(getcustom('teamfenhong_yueji',$aid)){
            $yueji_limit = $sysset['teamfenhong_yueji']??0;
        }
        if(getcustom('teamfenhong_pingji_yueji',$aid)){
            //平级奖允许越级 1允许，0不允许
            $pingji_yueji_status = $sysset['teamfenhong_pingji_yueji']??0;
        }
        if(!getcustom('teamfenhong_pingji_yueji',$aid)){
            $pingji_yueji_status = 1;
        }
        $pingji_yueji_bonus_status = 0;
        if(getcustom('teamfenhong_pingji_yueji_bonus',$aid)){
            //平级奖允许越级 1允许，0不允许
            $pingji_yueji_bonus_status = $sysset['teamfenhong_pingji_yueji_bonus']??0;
        }
        $pingji_diji_bonus_status = 0;
        if(getcustom('teamfenhong_pingji_diji_bonus',$aid)){
            //平级奖 低级别拿高级别
            $pingji_diji_bonus_status = $sysset['teamfenhong_pingji_diji_bonus']??0;
        }
        //团队分红分钱包
        $teamfenhong_product_wallet = 0;
        if(getcustom('active_coin',$aid)){
            $teamfenhong_product_wallet = 1;
        }
        //团队分红级差同时减掉平级奖
        $teamfenhong_jicha_add_pj = 0;
        if(getcustom('teamfenhong_jicha_add_pj',$aid)){
            $teamfenhong_jicha_add_pj = $sysset['teamfenhong_jicha_add_pj']?1:0;
        }
        $teamfenhong_pingji_source = 0;//团队分红平级奖来源
        if(getcustom('teamfenhong_pingji_source',$aid)){
            $teamfenhong_pingji_source = $sysset['teamfenhong_pingji_source']??0;
        }
        $pj_duli = 0;//团队分红平级奖独立发放
        if(getcustom('teamfenhong_pingji_duli',$aid)){
            $pj_duli = 1;
        }
        foreach($oglist as $og){
            if(getcustom('maidan_fenhong_new',$aid) && $og['module']=='maidan'){
                if($og['bid'] > 0 && !in_array($og['bid'],$bids_maidan)){
                    continue;
                }
            }
            if(getcustom('fenhong_business_item_switch',$aid) && $og['module']!='maidan'){
                if($og['bid'] > 0 && !in_array($og['bid'],$bids)){
                    continue;
                }
            }
            $commissionyj_my = 0;
            if(getcustom('commission2moneypercent',$aid) && $sysset['commission2moneypercent1'] > 0){
                //是否是首单
                $beforeorder = Db::name('shop_order')->where('aid',$aid)->where('mid',$og['mid'])->where('status','in','1,2,3')->where('paytime','<',$og['paytime'])->find();
                if(!$beforeorder){
                    $commissionpercent = 1 - $sysset['commission2moneypercent1'] * 0.01;
                    $moneypercent = $sysset['commission2moneypercent1'] * 0.01;
                }else{
                    $commissionpercent = 1 - $sysset['commission2moneypercent2'] * 0.01;
                    $moneypercent = $sysset['commission2moneypercent2'] * 0.01;
                }
            }else{
                $commissionpercent = 1;
                $moneypercent = 0;
            }
            if($sysset['fhjiesuantype'] == 0){
                $fenhongprice = $og['real_totalprice'];
            }else{
                $fenhongprice = $og['real_totalprice'] - $og['cost_price'] * $og['num'];
            }
            if(getcustom('baikangxie',$aid)){
                $fenhongprice = $og['cost_price'] * $og['num'];
            }
            //无限层级团队分红使用产品单独设置的奖励金额
            if($fenhongprice <= 0 && $teamfenhong_wuxian==0) continue;
            $ogids[] = $og['id'];
            $allfenhongprice = $allfenhongprice + $fenhongprice;
            $path_origin_state = false;
            $member = Db::name('member')->where('id', $og['mid'])->find();
            if(empty($member)){
                continue;
            }
            //下单会员等级
            if($member['levelstarttime'] >= $og['createtime']) {
                $levelup_order_levelid = Db::name('member_levelup_order')->where('aid',$aid)->where('mid', $member['id'])->where('status', 2)
                    ->where('levelup_time', '<', $og['createtime'])->whereIn('levelid',$defaultLevelIds)->order('levelup_time', 'desc')->value('levelid');
                if($levelup_order_levelid) {
                    $member['levelid'] = $levelup_order_levelid;
                }
            }
            $memberLevel = Db::name('member_level')->where('aid',$aid)->where('id',$member['levelid'])->find();
            $member_extend = Db::name('member_level_record')->field('mid id,levelid')->where('aid', $aid)->where('mid', $og['mid'])->find();
            $member_levelid_buy = Db::name('member_levelup_order')->where('aid',$aid)->where('mid', $og['id'])->where('status', 2)
                ->where('levelup_time', '<', $og['createtime'])->whereIn('levelid',$defaultLevelIds)->order('levelup_time', 'desc')->value('levelid');//购买时等级id
            if($teamfhlevellist){
                //判断脱离时间
                if($member['change_pid_time'] && $member['change_pid_time'] >= $og['createtime']){
                    $pids = $member['path_origin'];
                    $path_origin_state = true;
                }else{
                    $pids = $member['path'];
                }

                if($pids){
                    $pids .= ','.$og['mid'];
                }else{
                    $pids = (string)$og['mid'];
                }
                if($pids){
                    $parentList = Db::name('member')->where('id','in',$pids)->order(Db::raw('field(id,'.$pids.')'))->select()->toArray();
                    $parentList = array_reverse($parentList);//父级从近到远，自己，上一级，上二级，上三级。。。
                    $hasfhlevelids = [];
                    $last_teamfenhongbl = 0;
                    $last_teamfenhongmoney = 0;
                    $last_teamfenhongmoney_pj_total = 0;
                    $last_teamfenhong_score_percent = 0;
                    $last_level_teamfenhongbl = 0;
                    $last_totalfenhongmoney = 0;//上次团队分红总额 金额+比例
                    $has_level_fhlevelids = [];
                    $haspingjinumArr = [];
                    //层级判断，如购买人等级未开启“包含自己teamfenhong_self“则购买人的上级为第一级，开启了则购买人为第一级
                    $level_i = 0;
                    $total_fafang_commission = 0;//总的要发放的佣金
                    $boleArr = [];
                    $boleStatus = true;

                    foreach($parentList as $k=>$parent){
                        //判断升级时间
                        $leveldata = $teamfhlevellist[$parent['levelid']];
                        if($parent['levelstarttime'] >= $og['createtime']) {
                            $levelup_order_levelid = Db::name('member_levelup_order')->where('aid',$aid)->where('mid', $parent['id'])->where('status', 2)
                                ->where('levelup_time', '<', $og['createtime'])->whereIn('levelid',$defaultLevelIds)->order('levelup_time', 'desc')->value('levelid');
                            if($levelup_order_levelid) {
                                $parent['levelid'] = $levelup_order_levelid;
                                $leveldata = $teamfhlevellist[$parent['levelid']];
                            }else{
                                    //if($leveldata['teamfenhong_self'] != 1 || ($leveldata['teamfenhong_self'] == 1 && $parent['id'] != $og['mid']))
                                    //不包含自己跳过
                                    unset($parentList[$k]);
                                    continue;
                            }
                        }

                        $level_i++;
                        if($parent['id'] == $og['mid'] && $leveldata['teamfenhong_self'] != 1) { $level_i--; unset($parentList[$k]);continue;}//不包含自己则层级-1
                        //无限层级团队分红使用产品单独设置的层级
                        if(!$leveldata || ($level_i>$leveldata['teamfenhonglv'] && !$teamfenhong_wuxian)) continue;
                        $teamfhStatus = true;
                        if($leveldata['teamfenhongonly'] == 1 && in_array($parent['levelid'],$hasfhlevelids)){
                            //该等级设置了只给最近的上级分红,表示如果下单人有多个上级均符合分红条件则只给离他最近的上级分红，其他上级不分红
                            $teamfhStatus = false;
                        }
                        if(getcustom('teamfenhong_not_send_cengji')){
                            //不发放层级
                            $teamfenhong_not_lv = explode(',',$leveldata['teamfenhong_not_lv']);
                            if($teamfenhong_not_lv && in_array($level_i,$teamfenhong_not_lv)){
                                   continue;
                            }
                        }
                        if(getcustom('teamfenhong_yejitj',$aid)){
                            //月初结算类型，且需要验证业绩大于0
                            if($sysset['fhjiesuantime'] == 1 && $leveldata['teamfenhong_yeji_money']>0){
                                //当前月份
                                $nowmonth = strtotime(date("Y-m"));
                                //查询是否有清零记录月份
                                $yejizerolog = Db::name('member_teamfenhong_yejizerolog')->where('mid',$parent['id'])->where('levelid',$parent['levelid'])->order('zero_month desc')->field('id,total_month,zero_month')->find();
                                $zero_month = $yejizerolog?$yejizerolog['zero_month']:0;//清零月份

                                //是否开启累积月份，并且累积月份大于0
                                if($leveldata['teamfenhong_yeji_total'] && $leveldata['teamfenhong_yeji_month']>0){
                                    //计算累积开始月份
                                    $stime = strtotime('-'.$leveldata['teamfenhong_yeji_month'].' month',$nowmonth);
                                    //判断用户是否有清零月份,有则清零月份及之前的月份数据不统计
                                    if($stime<=$zero_month){
                                        $stime = strtotime('+1 month',$zero_month);
                                        //如果统计月份大于等于当前月份则停止统计
                                        if($stime>=$nowmonth){
                                            continue;
                                        }
                                    }
                                }else{
                                    $stime = strtotime('-1 month',$nowmonth);
                                }
                                $etime = $nowmonth - 1;

                                //查询团队，业绩统计是否包含自己
                                if($leveldata['teamfenhong_yeji_self']){
                                    $mids =[$parent['id']];
                                }else{
                                    $mids =[];
                                }
                                $mids2 = \app\commons\Member::getdownmids($aid,$parent['id']);
                                if($mids2){
                                    $mids = array_merge($mids,$mids2);
                                }
                                if($mids){
                                    //团队最早购买时间
                                    $mintime = Db::name('shop_order')->where('mid','in',$mids)->where('status','>=',1)->where('status','<=',3)->where('aid',$aid)->order('paytime asc')->value('paytime');
                                    $mintime = $mintime??0;

                                    //统计团队时间内所有业绩
                                    $allyejiorders = Db::name('shop_order')
                                        ->where('mid','in',$mids)->where('status','>=',1)->where('status','<=',3)->where('paytime','>=',$stime)->where('paytime','<=',$etime)->where('aid',$aid)
                                        ->order('paytime asc')->field('id,totalprice,refund_money,freight_price,freight_type,paytime')
                                        ->select()->toArray();
                                    $mintime2 = 0;//累积的最小月份
                                    $allyeji  = 0;//业绩总和
                                    if($allyejiorders){
                                        foreach($allyejiorders as $ak=>$av){
                                            if($ak == 0){
                                                $mintime2 = strtotime(date("Y-m",$av['paytime']));
                                            }
                                            //是否计算配方方式的运费、配送费
                                            //if(!$leveldata['teamfenhong_yeji_yunfee'] && ($av['freight_type'] == 0 || $av['freight_type'] == 2)){
                                            if(!$leveldata['teamfenhong_yeji_yunfee']){
                                                $allyeji = $av['totalprice'] - $av['refund_money'] - $av['freight_price'];
                                            }else{
                                                $allyeji = $av['totalprice'] - $av['refund_money'];
                                            }
                                        }
                                        unset($ak);
                                        unset($av);
                                        //如果业绩小于达标业绩，则不发放
                                        if($allyeji < $leveldata['teamfenhong_yeji_money']){
                                            //累积的需要判断用户业绩是否要清零
                                            if($leveldata['teamfenhong_yeji_total'] && $leveldata['teamfenhong_yeji_month']>0){
                                                //没有清零月份记录且开始计算时间大于等于最小支付月份时间，或者有清零月份且开始计算时间大于清零月份的
                                                if((!$zero_month && $stime>=$mintime) || ($zero_month && $stime>$zero_month)){
                                                    //添加清零记录
                                                    $log = [];
                                                    $log['aid']     = $aid;
                                                    $log['mid']     = $parent['id'];
                                                    $log['levelid'] = $parent['levelid'];
                                                    $log['total_month'] = $leveldata['teamfenhong_yeji_total'];
                                                    $log['zero_month']  = strtotime(date("Y-m",$etime));
                                                    $log['createtime']  = time();
                                                    Db::name('member_teamfenhong_yejizerolog')->insert($log);
                                                }
                                            }
                                            continue;
                                        }
                                    }else{
                                        //如果有购买记录时间，且开启了累积，需要判断用户业绩是否要清零
                                        if($mintime && $leveldata['teamfenhong_yeji_total'] && $leveldata['teamfenhong_yeji_month']>0){
                                            //没有清零月份记录且开始计算时间大于等于最小支付月份时间，或者有清零月份且开始计算时间大于清零月份的
                                            if((!$zero_month && $stime>=$mintime) || ($zero_month && $stime>$zero_month)){
                                                //添加清零记录
                                                $log = [];
                                                $log['aid']     = $aid;
                                                $log['mid']     = $parent['id'];
                                                $log['levelid'] = $parent['levelid'];
                                                $log['total_month'] = $leveldata['teamfenhong_yeji_total'];
                                                $log['zero_month']  = strtotime(date("Y-m",$etime));
                                                $log['createtime']  = time();
                                                Db::name('member_teamfenhong_yejizerolog')->insert($log);
                                            }
                                        }
                                        continue;
                                    }
                                }else{
                                    continue;
                                }
                            };
                        }

                        //var_dump($og['id']);
                        //var_dump($parent['id']);
                        $hasfhlevelids[] = $parent['levelid'];
                        $totalfenhongmoney = 0;
                        $totalfenhongscore = 0;
                        $leveldata['teamfenhong_money_dan'] = $leveldata['teamfenhong_money'];//每单奖励 230915
                        $leveldata['teamfenhong_pingji_money_dan'] = $leveldata['teamfenhong_pingji_money'];//每单奖励 230915
                        $leveldata['teamfenhong_money'] = 0;//重新赋值为0，否则按单奖励会重复计算
                        $leveldata['teamfenhong_pingji_money'] = 0;
                        if(getcustom('teamfenhong_removemax',$aid) && $k==1 && $leveldata['teamfenhong_removemax'] == 1){ //去掉一个直推业绩最高的
                            $downmemberids = Db::name('member')->where('pid',$parent['id'])->column('id');
                            $downmemberYeji = [];
                            foreach($oglist as $og2){
                                if(in_array($og2['mid'],$downmemberids)){
                                    if(!$downmemberYeji[$og2['mid']]) $downmemberYeji[$og2['mid']] = 0;
                                    $downmemberYeji[$og2['mid']] += $og2['real_totalprice'];
                                }
                            }
                            $maxyj2 = 0;
                            $maxmid2 = 0;
                            foreach($downmemberYeji as $mid2=>$yj2){
                                if($maxyj2 < $yj2){
                                    $maxmid2 = $mid2;
                                    $maxyj2 = $yj2;
                                }
                            }
                            if($maxmid2 == $og['mid']) continue;
                        }
                        if($og['module'] != 'luckycollage2'){
                            if($og['module'] == 'yuyue'){
                                $product = Db::name('yuyue_product')->where('id',$og['proid'])->find();
                            }elseif($og['module'] == 'coupon'){
                                $product = Db::name('coupon')->where('id',$og['cpid'])->find();
                            }elseif($og['module'] == 'luckycollage'){
                                $product = Db::name('lucky_collage_product')->where('id',$og['proid'])->find();
                                if(getcustom('luckycollage_fail_commission',$aid)){
                                    if ($og['iszj'] == 2) {
                                        $product['fenhongset'] = $product['fail_fenhongset'];
                                        $product['teamfenhongset'] = $product['fail_teamfenhongset'];
                                        $product['teamfenhongdata1'] = $product['fail_teamfenhongdata1'];
                                        $product['teamfenhongdata2'] = $product['fail_teamfenhongdata2'];

                                  }
                                }
                                if ($product['fenhongset'] == 0) {
                                    $product['teamfenhongset'] = -1;
                                }
                            }elseif($og['module'] == 'scoreshop'){
                                $product = Db::name('scoreshop_product')->where('id',$og['proid'])->find();
                            }elseif($og['module'] == 'kecheng'){
                                $product = Db::name('kecheng_list')->where('id',$og['kcid'])->find();
                            }elseif($og['module'] == 'business_reward'){
                                if(getcustom('business_reward_member',$aid)){
                                    $product = [
                                        'teamfenhongpjset' => -1,
                                        'teamfenhongset' => $business_reward_set['teamfenhongset'],
                                        'teamfenhongdata1' => $business_reward_set['teamfenhongdata']
                                    ];
                                }
                            }elseif($og['module'] == 'hotel'){
                                $product = Db::name('hotel_room')->where('id',$og['roomid'])->find();
                            }else{
                                $product = Db::name('shop_product')->where('id',$og['proid'])->find();
                            }
                            if(getcustom('maidan_fenhong',$aid) || getcustom('maidan_fenhong_new',$aid)){
                                if($og['module'] == 'maidan'){
                                    $product = [];
                                    $product['teamfenhongset']   = 0;
                                    $product['teamfenhongpjset'] = 0;
                                }
                            }
                            if(getcustom('restaurant_fenhong',$aid)){
                                if($og['module'] == 'diancan' || $og['module'] == 'takeaway'){
                                    $product = [];
                                    $product['teamfenhongset']   = 0;
                                    $product['teamfenhongpjset'] = 0;
                                }
                            }
                            //商品团队分红独立设置时每单奖励也会发放
                            if($product['teamfenhongset'] == 1){ //按比例
                                $fenhongdata = json_decode($product['teamfenhongdata1'],true);
                                if($fenhongdata){
                                    $leveldata['teamfenhongbl'] = $fenhongdata[$leveldata['id']]['commission'];
                                    $leveldata['teamfenhong_money'] = 0;
                                    $leveldata['teamfenhong_score_percent'] = 0;
                                }
                            }elseif($product['teamfenhongset'] == 2){ //按固定金额
                                $fenhongdata = json_decode($product['teamfenhongdata2'],true);
                                if($fenhongdata){
                                    $leveldata['teamfenhongbl'] = 0;
                                    $leveldata['teamfenhong_money'] = $fenhongdata[$leveldata['id']]['commission'] * $og['num'];
                                    $leveldata['teamfenhong_score_percent'] = 0;
                                }
                            }elseif($product['teamfenhongset'] == 4){ //按积分比例
                                $fenhongdata = json_decode($product['teamfenhongdata1'],true);
                                if($fenhongdata){
                                    $leveldata['teamfenhongbl'] = 0;
                                    $leveldata['teamfenhong_money'] = 0;
                                    $leveldata['teamfenhong_score_percent'] = $fenhongdata[$leveldata['id']]['score'];
                                }
                            }elseif($product['teamfenhongset'] == -1){
                                $leveldata['teamfenhongbl'] = 0;
                                $leveldata['teamfenhong_money'] = 0;
                                $leveldata['teamfenhong_score_percent'] = 0;
                            }
                            if($teamfenhong_wuxian){
                                //无限层级团队分红
                                if($product['teamfenhongset']==5){
                                    $fenhongdata = json_decode($product['teamfenhongdata1'],true);
                                    $fenhongprice = $fenhongdata['commission']*$og['num'];
                                    $leveldata['teamfenhongbl'] = $fenhongdata['bili'];
                                    $leveldata['teamfenhonglv'] = $fenhongdata['lv'];
                                }
                            }
                            $totalfenhongmoney += $leveldata['teamfenhong_money'];

                            //平级独立设置
                            if($product['teamfenhongpjset'] == 1){ //按比例
                                $fenhongpjdata = json_decode($product['teamfenhongpjdata1'],true);
                                if($fenhongpjdata){
                                    $leveldata['teamfenhong_pingji_bl'] = $fenhongpjdata[$leveldata['id']]['commission'];
                                    $leveldata['teamfenhong_pingji_money'] = 0;
                                    $leveldata['teamfenhong_pingji_score_percent'] = 0;
                                }
                            }elseif($product['teamfenhongpjset'] == 2){ //按固定金额
                                $fenhongpjdata = json_decode($product['teamfenhongpjdata2'],true);
                                if($fenhongpjdata){
                                    $leveldata['teamfenhong_pingji_bl'] = 0;
                                    $leveldata['teamfenhong_pingji_money'] = $fenhongpjdata[$leveldata['id']]['commission'] * $og['num'];
                                    $leveldata['teamfenhong_pingji_score_percent'] = 0;
                                }
                            }elseif($product['teamfenhongpjset'] == 4){ //按积分比例
                                $fenhongpjdata = json_decode($product['teamfenhongpjdata1'],true);
                                if($fenhongpjdata){
                                    $leveldata['teamfenhong_pingji_bl'] = 0;
                                    $leveldata['teamfenhong_pingji_money'] = 0;
                                    $leveldata['teamfenhong_pingji_score_percent'] = $fenhongpjdata[$leveldata['id']]['score'];
                                }
                            }elseif($product['teamfenhongpjset'] == -1){
                                $leveldata['teamfenhong_pingji_bl'] = 0;
                                $leveldata['teamfenhong_pingji_money'] = 0;
                                $leveldata['teamfenhong_pingji_score_percent'] = 0;
                            }
                            //团队分红伯乐奖参数
                            if(getcustom('teamfenhong_bole',$aid)){
                                if($product['teamfenhongblset'] == 1){ //按比例
                                    $fenhongbldata = json_decode($product['teamfenhongbldata1'],true);
                                    if($fenhongbldata){
                                        $leveldata['teamfenhong_bole_bl'] = $fenhongbldata[$leveldata['id']]['commission'];
                                        $leveldata['teamfenhong_bole_bl_tuoli'] = $fenhongbldata[$leveldata['id']]['commission_tuoli'];
                                        $leveldata['teamfenhong_bole_money'] = 0;
                                        $leveldata['teamfenhong_bole_money_tuoli'] = 0;
                                    }
                                }elseif($product['teamfenhongblset'] == 2){ //按固定金额
                                    $fenhongbldata = json_decode($product['teamfenhongbldata2'],true);
                                    if($fenhongbldata){
                                        $leveldata['teamfenhong_bole_bl'] = 0;
                                        $leveldata['teamfenhong_bole_bl_tuoli'] = 0;
                                        $leveldata['teamfenhong_bole_money'] = $fenhongbldata[$leveldata['id']]['commission'] * $og['num'];
                                        $leveldata['teamfenhong_bole_money_tuoli'] = $fenhongbldata[$leveldata['id']]['commission_tuoli'] * $og['num'];
                                    }
                                }elseif($product['teamfenhongblset'] == -1){//不参与
                                    $leveldata['teamfenhong_bole_bl'] = 0;
                                    $leveldata['teamfenhong_bole_bl_tuoli'] = 0;
                                    $leveldata['teamfenhong_bole_money'] = 0;
                                    $leveldata['teamfenhong_bole_money_tuoli'] = 0;
                                }
                            }
                            $teamfenhong_bole_custom = getcustom('teamfenhong_bole',$aid);
                            if(getcustom('maidan_fenhong_new',$aid) && $og['module']=='maidan'){
                                //买单分红单独设置的提成比例
                                $leveldata['teamfenhongbl'] = $leveldata['teamfenhongbl_maidan'];
                                $leveldata['teamfenhong_pingji_bl'] = $leveldata['teamfenhong_pingji_bl_maidan'];
                                if($teamfenhong_bole_custom){
                                    $leveldata['teamfenhong_bole_bl'] = $leveldata['teamfenhong_bole_bl_maidan'];
                                }
                            }
                        }
                        $teamfenhong_money_dan_product = 1;
                        if($teamfenhong_money_product_status){
                            //产品单独设置的团队分红要判断级别是否设置了每单分红可参与
                            if($product['teamfenhongpjset']!=0){
                                $teamfenhong_money_dan_product = $leveldata['teamfenhong_money_product']?:0;
                            }
                        }

                        if($teamfhStatus){
                            //每单奖励
                            if($leveldata['teamfenhong_money_dan'] > 0 && !in_array($og['orderid'],$teamfenhong_orderids[$parent['id']]) && $teamfenhong_money_dan_product==1) {
                                $totalfenhongmoney += $leveldata['teamfenhong_money_dan'];
                                $teamfenhong_orderids[$parent['id']][] = $og['orderid'];
                            }
                            if($isjicha){
                                $totalfenhongmoney = $totalfenhongmoney - $last_teamfenhongmoney - $last_teamfenhongmoney_pj_total;
                            }else{
                                $totalfenhongmoney = $totalfenhongmoney;
                            }
                            if($totalfenhongmoney < 0) $totalfenhongmoney = 0;
                            //分红金额可设置扣除分销佣金后进行分红
                            if(getcustom('fenhong_removefenxiao',$aid) && $leveldata['teamfenhong_removefenxiao'] == 1 && (($isjicha && $last_teamfenhongbl == 0) || !$isjicha)){
                                $fxcommission = 0;
                                if($og['parent1'] && $og['parent1commission']){
                                    $fxcommission = $fxcommission + $og['parent1commission'];
                                }
                                if($og['parent2'] && $og['parent2commission']){
                                    $fxcommission = $fxcommission + $og['parent2commission'];
                                }
                                if($og['parent3'] && $og['parent3commission']){
                                    $fxcommission = $fxcommission + $og['parent3commission'];
                                }
                                if($fxcommission > 0){
                                    if($isjicha){
                                        $last_teamfenhongbl = $fxcommission / $fenhongprice*100;
                                    }else{
                                        $leveldata['teamfenhongbl'] = $leveldata['teamfenhongbl'] - $fxcommission / $fenhongprice*100;
                                        if($leveldata['teamfenhongbl'] < 0) $leveldata['teamfenhongbl'] = 0;
                                    }
                                }
                            }
                            //var_dump('teamfenhongbl:'.$leveldata['teamfenhongbl']);
                            //var_dump('teamfenhong_money:'.$leveldata['teamfenhong_money']);
                            //var_dump('$totalfenhongmoney:'.$totalfenhongmoney);
                            //分红比例
                            if($leveldata['teamfenhongbl'] > 0) {
                                if(!$teamfenhong_wuxian){
                                    if($isjicha){
                                        $this_teamfenhongbl = $leveldata['teamfenhongbl'] - $last_teamfenhongbl;
                                        if(getcustom('level_teamfenhong2',$aid)) {
                                            $this_teamfenhongbl = $this_teamfenhongbl - $last_level_teamfenhongbl;
                                            if($k > 0 && $parentList[$k-1]['levelid'] == $leveldata['id'] && $parent['levelid'] == $leveldata['id'] && $leveldata['level_teamfenhong_ids'] == $leveldata['id'] && $leveldata['level_teamfenhongbl'] > 0 && ($leveldata['level_teamfenhongonly'] == 0 || !in_array($parent['levelid'],$has_level_fhlevelids))){ //设置了等级团队分红 减去等级团队分红的比例(平级奖)
                                                $has_level_fhlevelids[] = $parent['levelid'];
                                                $last_level_teamfenhongbl = $last_level_teamfenhongbl + $leveldata['level_teamfenhongbl'];
                                            }
                                        }
                                    }else{
                                        $this_teamfenhongbl = $leveldata['teamfenhongbl'];
                                    }
                                    if($this_teamfenhongbl <=0) $this_teamfenhongbl = 0;
                                    $last_teamfenhongbl = $last_teamfenhongbl + $this_teamfenhongbl;
                                    $totalfenhongmoney = $totalfenhongmoney + $this_teamfenhongbl * $fenhongprice * 0.01;
                                    $totalfenhongmoney = bcsub($totalfenhongmoney,$last_teamfenhongmoney_pj_total,2);
                                }else{
                                    //无限级团队分红 奖励=（奖励金额-上级累计奖励）*比例
                                    $cacl_fenhongmoney = bcmul(bcsub($fenhongprice,$last_teamfenhongmoney,2),$leveldata['teamfenhongbl']/100,2);
                                    //$last_teamfenhongmoney = bcadd($last_teamfenhongmoney,$cacl_fenhongmoney,2);
                                    if($cacl_fenhongmoney<0.01 || ($leveldata['teamfenhonglv']>0 && $level_i>$leveldata['teamfenhonglv'])){
                                        continue;
                                    }
                                    $totalfenhongmoney = bcadd($totalfenhongmoney,$cacl_fenhongmoney,2);
                                }

                            }
                            //分红积分比例
                            if($leveldata['teamfenhong_score_percent'] > 0) {
                                if($isjicha){
                                    $this_teamfenhong_score_percent = $leveldata['teamfenhong_score_percent'] - $last_teamfenhong_score_percent;
                                }else{
                                    $this_teamfenhong_score_percent = $leveldata['teamfenhong_score_percent'];
                                }
                                if($this_teamfenhong_score_percent <=0) $this_teamfenhong_score_percent = 0;
                                $last_teamfenhong_score_percent = $last_teamfenhong_score_percent + $this_teamfenhong_score_percent;

                                $totalfenhongscore = $totalfenhongscore + round($this_teamfenhong_score_percent * $fenhongprice * 0.01);
                            }

                            //最后一次累计 极差计算用
                            $last_teamfenhongmoney = $last_teamfenhongmoney + $totalfenhongmoney;
                            $last_totalfenhongmoney = $totalfenhongmoney;

                            //var_dump('$totalfenhongmoney:'.$totalfenhongmoney);
                            if($totalfenhongmoney > 0 || $totalfenhongscore > 0){
                                if($isyj == 1 && $yjmid == $parent['id']){
                                    $commissionyj_my += $totalfenhongmoney;
                                }
                                if($commissionpercent != 1){
                                    $fenhongcommission = round($totalfenhongmoney*$commissionpercent,2);
                                    $fenhongmoney = round($totalfenhongmoney*$moneypercent,2);
                                    $fenhongscore = $totalfenhongscore;
                                }else{
                                    $fenhongcommission = $totalfenhongmoney;
                                    $fenhongmoney = 0;
                                    $fenhongscore = $totalfenhongscore;
                                }
                                //分红最大不超过
                                if(getcustom('teamfenhong_max',$aid)){
                                    $total_fafang_commission = $total_fafang_commission+$fenhongcommission;
                                    $teamfenhong_max_type = Db::name('admin_set')->where('aid',$aid)->value('teamfenhong_max_type');
                                    if($teamfenhong_max_type==1 && $total_fafang_commission >=$og['real_totalprice']){//不超过订单金额
                                        continue;
                                    }
                                }
                                if($teamfenhong_product_wallet){
                                    //团队分红分钱包发放
                                    $product = Db::name('shop_product')->where('id',$og['proid'])->field('teamfenhongwalletset,teamfenhongwallet')->find();
                                    if($product['teamfenhongwalletset']){
                                        $teamfenhongwallet = json_decode($product['teamfenhongwallet'],true);
                                        $commission_wallet_bili = $teamfenhongwallet['commission']?:0;
                                        $score_wallet_bili = $teamfenhongwallet['score']?:0;
                                        $money_wallet_bili = $teamfenhongwallet['money']?:0;
                                        $fuchi_wallet_bili = $teamfenhongwallet['fuchi']?:0;
                                        $fenhongcommission = bcmul($totalfenhongmoney,$commission_wallet_bili/100,2);
                                        $fenhongscore = bcadd($fenhongscore,bcmul($totalfenhongmoney,$score_wallet_bili/100,2),2);
                                        $fenhongmoney = bcmul($totalfenhongmoney,$money_wallet_bili/100,2);
                                        $fenhongfuchi = bcmul($totalfenhongmoney,$fuchi_wallet_bili/100,2);
                                    }
                                }
                                //dump([$k,$member]);
                                if($midteamfhArr[$parent['id']]){
                                    $midteamfhArr[$parent['id']]['totalcommission'] = $midteamfhArr[$parent['id']]['totalcommission'] + $totalfenhongmoney;
                                    $midteamfhArr[$parent['id']]['commission'] = $midteamfhArr[$parent['id']]['commission'] + $fenhongcommission;
                                    $midteamfhArr[$parent['id']]['money'] = $midteamfhArr[$parent['id']]['money'] + $fenhongmoney;
                                    $midteamfhArr[$parent['id']]['score'] = $midteamfhArr[$parent['id']]['score'] + $fenhongscore;
                                    $midteamfhArr[$parent['id']]['ogids'][] = $og['id'];
                                    $midteamfhArr[$parent['id']]['levelid'] = $parent['levelid'];
                                    $midteamfhArr[$parent['id']]['downMember'] = $k > 1 ? $parentList[$k-1] : $member;
                                    if($teamfenhong_product_wallet){
                                        $midteamfhArr[$parent['id']]['fuchi'] = $midteamfhArr[$parent['id']]['fuchi'] + $fenhongfuchi;
                                    }
                                }else{
                                    $midteamfhArr[$parent['id']] = [
                                        'totalcommission'=>$totalfenhongmoney,
                                        'commission'=>$fenhongcommission,
                                        'money'=>$fenhongmoney,
                                        'score'=>$fenhongscore,
                                        'ogids'=>[$og['id']],
                                        'module'=>$og['module'] ?? 'shop',
                                        'levelid' => $parent['levelid'],
                                        'type' => '团队分红',
                                        'downMember' => $k > 1 ? $parentList[$k-1] : $member
                                    ];
                                    if($teamfenhong_product_wallet){
                                        $midteamfhArr[$parent['id']]['fuchi'] = $fenhongfuchi;
                                    }
                                }
                                //dump($parent['id'].'('.$leveldata['name'].')获得团队分红'.$totalfenhongmoney);
                                if(getcustom('teamfenhong_share',$aid)){
                                    $member_orign_parent = self::get_pid_origin_bylog($aid,$member['id'],$parent['id'],$og['paytime'],$defaultLevelIds);
                                    if($member_orign_parent !== false) $midteamfhArr[$parent['id']]['downMember'] = $member_orign_parent;
                                }
                                if(getcustom('commission_orderrefund_deduct',$aid) && $isyj == 0){
                                    self::fhrecord($aid,$parent['id'],$fenhongcommission,$fenhongscore,$og['id'],$og['module'] ?? 'shop','teamfenhong',t('团队分红',$aid));
                                }

                            }
                        }


                        //平级奖 找最近的上级
                        if(getcustom('teamfenhong_pingji',$aid)){
                            //teamfenhong_pingji_type:0按奖励金额,1按订单金额
                            $last_teamfenhongbl_pj = 0;
                            $last_teamfenhongmoney_pj = 0;
                            $last_teamfenhong_score_percent_pj = 0;
                            $last_teamfenhong_pj = 0;//级差奖励金额，新增的teamfenhong_pingji_fenhong要累计前面的分红+平级奖用来计算，直接用级差比例的话会丢失奖励
                            $levelSort = [];
                             if($leveldata['teamfenhong_pingji_lv']>0 && ($leveldata['teamfenhong_pingji_bl']>0 || $leveldata['teamfenhong_pingji_money'] > 0 || $leveldata['teamfenhong_pingji_money_dan'] > 0 || $leveldata['teamfenhong_pingji_score_percent'] > 0) && ($totalfenhongmoney > 0 || $leveldata['teamfenhong_pingji_type'] == 1)){
                                $last_pingji_levelid = $parent['levelid'];//上一个拿平级奖的会员id
                                $pingji_yueji_bonus = 0;
                                foreach($parentList as $k2=>$parent2){
                                    //dump('上级'.$parent2['id'].'平级奖开始');
                                    $parent2Level = $teamfhlevellist[$parent2['levelid']];
                                    $levelSort[] = $parent2Level['sort'];
                                    //开启越级限制，如果当前会员级别不等于上一个会员级别就不再拿奖
                                    if($k2 > $k){
                                        if($yueji_limit && $last_pingji_levelid>0 && $parent2['levelid']!=$last_pingji_levelid){
                                            break;
                                        }
                                    }

                                    if($k2 > $k && $pingji_yueji_bonus_status==1){
                                        //越级拿平级奖
                                        if($parent2Level['sort'] > $leveldata['sort']){
                                            $pingji_yueji_bonus = 1;
                                        }
                                    }
                                    if($k2 > $k && $pingji_diji_bonus_status==1){
                                        //越级拿平级奖
                                        if($parent2Level['sort'] < $leveldata['sort']){
                                            $pingji_yueji_bonus = 1;
                                        }
                                    }

                                    if($k2 > $k && ($parent2['levelid'] == $parent['levelid'] || $pingji_yueji_bonus==1)){
                                        if($pingji_yueji_status != 1){
                                            //团队分红平级奖越级，关闭后团队中间有人越级不发奖
                                            rsort($levelSort);
                                            if($parent2Level['sort'] < $levelSort[0]){
                                                break;
                                            }
                                        }
                                        $teamfenhong_pingji_bl = $leveldata['teamfenhong_pingji_bl'];
                                        $teamfenhong_pingji_money = $leveldata['teamfenhong_pingji_money'];
                                        if($teamfenhong_pingji_single_bl_set){
                                            //单独设置平1、2、3级比例
                                            if($haspingjinumArr[$parent['levelid']]){
                                                $lv = $haspingjinumArr[$parent['levelid']] + 1;
                                            }else{
                                                $lv = 1;
                                            }
                                            $teamfenhong_pingji_single_bl = json_decode($leveldata['teamfenhong_pingji_single_bl'],true);
                                            $teamfenhong_pingji_single_money = json_decode($leveldata['teamfenhong_pingji_single_money'],true);
                                            if(!empty($teamfenhong_pingji_single_bl[$lv])){
                                                $teamfenhong_pingji_bl = $teamfenhong_pingji_single_bl[$lv];
                                            }
                                            if(!empty($teamfenhong_pingji_single_money[$lv])){
                                                $teamfenhong_pingji_money = $teamfenhong_pingji_single_money[$lv];
                                            }
                                        }
                                        //暂时关闭 等级比例优先级低于商品独立设置
                                        //if($isjicha && $sysset['teamfenhong_differential_pj'] == 1 && $teamfhlevellist[$parent2['levelid']]['teamfenhong_pingji_bl'] > $leveldata['teamfenhong_pingji_bl']) break;
                                        if($isjicha && $sysset['teamfenhong_differential_pj'] == 1){
                                            if(!$teamfenhong_pingji_fenhong){
                                                $this_teamfenhongbl_pj = $teamfenhong_pingji_bl - $last_teamfenhongbl_pj;
                                            }else{
                                                $this_teamfenhongbl_pj = $teamfenhong_pingji_bl;
                                            }
                                            $this_teamfenhongmoney_pj = $teamfenhong_pingji_money - $last_teamfenhongmoney_pj;
                                            $this_teamfenhong_score_percent_pj = $leveldata['teamfenhong_pingji_score_percent'] - $last_teamfenhong_score_percent_pj;
                                        }else{
                                            $this_teamfenhongbl_pj = $teamfenhong_pingji_bl;
                                            $this_teamfenhongmoney_pj = $teamfenhong_pingji_money;
                                            $this_teamfenhong_score_percent_pj = $leveldata['teamfenhong_pingji_score_percent'];
                                        }
                                        if($this_teamfenhongbl_pj <=0) $this_teamfenhongbl_pj = 0;
                                        if($this_teamfenhongmoney_pj <=0) $this_teamfenhongmoney_pj = 0;
                                        if($this_teamfenhong_score_percent_pj <=0) $this_teamfenhong_score_percent_pj = 0;
                                        if($this_teamfenhongbl_pj == 0 && $this_teamfenhongmoney_pj == 0 && $this_teamfenhong_score_percent_pj == 0 && $leveldata['teamfenhong_pingji_money_dan']==0) continue;
                                        $last_teamfenhongbl_pj = $last_teamfenhongbl_pj + $this_teamfenhongbl_pj;

                                        $last_teamfenhongmoney_pj = $last_teamfenhongmoney_pj + $this_teamfenhongmoney_pj;
                                        $last_teamfenhong_score_percent_pj = $last_teamfenhong_score_percent_pj + $this_teamfenhong_score_percent_pj;

                                        $totalfenhongmoney_pj = 0;
                                        $totalfenhongscore_pj = 0;
                                        if($this_teamfenhongbl_pj>0){
                                            if($leveldata['teamfenhong_pingji_type'] == 0){
                                                //按奖励金额
                                                if($teamfenhong_money_product_status && $teamfenhong_money_dan_product==1 ){
                                                    //等级分销单独设置的团队分红每单分红金额不参与平级奖计算
                                                    $totalfenhongmoney = bcsub($totalfenhongmoney,$leveldata['teamfenhong_money_dan'],2);
                                                }
                                                $totalfenhongmoney_pj += $this_teamfenhongbl_pj * $totalfenhongmoney * 0.01;
                                            }else{
                                                //按订单金额
                                                $totalfenhongmoney_pj += $this_teamfenhongbl_pj * $fenhongprice * 0.01;
                                            }
                                        }
                                        if($isjicha && $sysset['teamfenhong_differential_pj'] == 1 && $teamfenhong_pingji_fenhong){
                                            $totalfenhongmoney_pj = bcsub($totalfenhongmoney_pj , $last_teamfenhong_pj,2);
                                        }

                                        if($this_teamfenhongbl_pj == 0 && $this_teamfenhongmoney_pj == 0 && $this_teamfenhong_score_percent_pj == 0 && $leveldata['teamfenhong_pingji_money_dan']==0) continue;
                                        if($isjicha && $sysset['teamfenhong_differential_pj'] == 1) {
                                            if($teamfenhong_pingji_fenhong){
                                                $last_teamfenhong_pj = $last_teamfenhong_pj + $totalfenhongmoney_pj;
                                            }
                                        }
                                        if($haspingjinumArr[$parent['levelid']]){
                                            $haspingjinumArr[$parent['levelid']]++;
                                        }else{
                                            $haspingjinumArr[$parent['levelid']] = 1;
                                        }
                                        if($haspingjinumArr[$parent['levelid']] > $leveldata['teamfenhong_pingji_lv']) break;
                                        if($product['teamfenhongpjset'] == 0){
                                            //按会员等级，按总订单发放一次平级奖
                                            if(($this_teamfenhongmoney_pj > 0 || $leveldata['teamfenhong_pingji_money_dan'] > 0) && !in_array($og['orderid'],$teamfenhong_orderids_pj[$parent2['id']])){
                                                $this_teamfenhongmoney_pj += $leveldata['teamfenhong_pingji_money_dan'];//230915 每单奖励
                                                $totalfenhongmoney_pj += $this_teamfenhongmoney_pj;
                                                if($totalfenhongmoney_pj < 0) $totalfenhongmoney_pj = 0;
                                                $teamfenhong_orderids_pj[$parent2['id']][] = $og['orderid'];
                                            }
                                        }else{
                                            //产品单独设置参数时，按分订单发放多次平级奖
                                            if($this_teamfenhongmoney_pj > 0 && !in_array($og['id'],$teamfenhong_orderids_pj[$parent2['id']])){
                                                $totalfenhongmoney_pj += $this_teamfenhongmoney_pj;
                                                if($totalfenhongmoney_pj < 0) $totalfenhongmoney_pj = 0;
                                                $teamfenhong_orderids_pj[$parent2['id']][] = $og['id'];
                                            }
                                        }
                                        if($this_teamfenhong_score_percent_pj > 0){
                                            if($leveldata['teamfenhong_pingji_type'] == 0){
                                                //按奖励金额
                                                $totalfenhongscore_pj = round($this_teamfenhong_score_percent_pj * $totalfenhongscore * 0.01);
                                            }else{
                                                //按订单金额
                                                $totalfenhongscore_pj = round($this_teamfenhong_score_percent_pj * $fenhongprice * 0.01);
                                            }
                                        }
                                        if($totalfenhongmoney_pj > 0 || $totalfenhongscore_pj > 0){
                                            if($teamfenhong_jicha_add_pj){
                                                //团队分红级差减掉平级奖
                                                $last_teamfenhongmoney_pj_total = bcadd($last_teamfenhongmoney_pj_total,$totalfenhongmoney_pj,2);
                                            }
                                            if($isyj == 1 && $yjmid == $parent2['id']){
                                                $commissionyj_my += $totalfenhongmoney_pj;
                                                $og['pj_money'] = $totalfenhongmoney_pj;
                                            }
                                            if($commissionpercent != 1){
                                                $fenhongcommission = round($totalfenhongmoney_pj*$commissionpercent,2);
                                                $fenhongmoney = round($totalfenhongmoney_pj*$moneypercent,2);
                                                $fenhongscore = round($totalfenhongscore_pj*$commissionpercent);
                                            }else{
                                                $fenhongcommission = $totalfenhongmoney_pj;
                                                $fenhongmoney = 0;
                                                $fenhongscore = $totalfenhongscore_pj;
                                            }
                                            if($pj_duli){
                                                //平级奖独立发放
                                                if($midteamfhArrPj[$parent2['id']]){
                                                    $midteamfhArrPj[$parent2['id']]['totalcommission'] = $midteamfhArrPj[$parent2['id']]['totalcommission'] + $totalfenhongmoney_pj;
                                                    $midteamfhArrPj[$parent2['id']]['commission'] = $midteamfhArrPj[$parent2['id']]['commission'] + $fenhongcommission;
                                                    $midteamfhArrPj[$parent2['id']]['money'] = $midteamfhArrPj[$parent2['id']]['money'] + $fenhongmoney;
                                                    $midteamfhArrPj[$parent2['id']]['score'] = $midteamfhArrPj[$parent2['id']]['score'] + $fenhongscore;
                                                    $midteamfhArrPj[$parent2['id']]['ogids'][] = $og['id'];
                                                    $midteamfhArrPj[$parent2['id']]['levelid'] = $parent2['levelid'];
                                                    $midteamfhArrPj[$parent2['id']]['downMember'] = $k2 > 1 ? $parentList[$k2-1] : $member;
                                                }else{
                                                    $midteamfhArrPj[$parent2['id']] = [
                                                        'totalcommission'=>$totalfenhongmoney_pj,
                                                        'commission'=>$fenhongcommission,
                                                        'money'=>$fenhongmoney,
                                                        'score'=>$fenhongscore,
                                                        'ogids'=>[$og['id']],
                                                        'module'=>$og['module'] ?? 'shop',
                                                        'levelid' => $parent2['levelid'],
                                                        'downMember' => $k2 > 1 ? $parentList[$k2-1] : $member
                                                    ];
                                                }
                                            }else{
                                                if($midteamfhArr[$parent2['id']]){
                                                    $midteamfhArr[$parent2['id']]['totalcommission'] = $midteamfhArr[$parent2['id']]['totalcommission'] + $totalfenhongmoney_pj;
                                                    $midteamfhArr[$parent2['id']]['commission'] = $midteamfhArr[$parent2['id']]['commission'] + $fenhongcommission;
                                                    $midteamfhArr[$parent2['id']]['money'] = $midteamfhArr[$parent2['id']]['money'] + $fenhongmoney;
                                                    $midteamfhArr[$parent2['id']]['score'] = $midteamfhArr[$parent2['id']]['score'] + $fenhongscore;
                                                    $midteamfhArr[$parent2['id']]['ogids'][] = $og['id'];
                                                    $midteamfhArr[$parent2['id']]['levelid'] = $parent2['levelid'];
                                                    $midteamfhArr[$parent2['id']]['downMember'] = $k2 > 1 ? $parentList[$k2-1] : $member;
                                                }else{
                                                    $midteamfhArr[$parent2['id']] = [
                                                        'totalcommission'=>$totalfenhongmoney_pj,
                                                        'commission'=>$fenhongcommission,
                                                        'money'=>$fenhongmoney,
                                                        'score'=>$fenhongscore,
                                                        'ogids'=>[$og['id']],
                                                        'module'=>$og['module'] ?? 'shop',
                                                        'levelid' => $parent2['levelid'],
                                                        'downMember' => $k2 > 1 ? $parentList[$k2-1] : $member
                                                    ];
                                                }
                                            }

                                            if(getcustom('teamfenhong_share',$aid)){
                                                $member_orign_parent = self::get_pid_origin_bylog($aid,$member['id'],$parent2['id'],$og['paytime'],$defaultLevelIds);
                                                if($member_orign_parent !== false) $midteamfhArr[$parent2['id']]['downMember'] = $member_orign_parent;
                                            }
                                            $last_pingji_levelid = $parent2['levelid'];
                                            if(getcustom('commission_orderrefund_deduct',$aid) && $isyj == 0){
                                                self::fhrecord($aid,$parent2['id'],$fenhongcommission,$fenhongscore,$og['id'],$og['module'] ?? 'shop','teamfenhong',t('团队分红',$aid));
                                            }
                                            //dump($parent['id'].'=>'.$parent2['id'].'('.$parent2Level['name'].')拿平级奖'.$totalfenhongmoney_pj);
                                            if($teamfenhong_pingji_fenhong){
                                                //计算平级奖时分红金额包含上一级拿到的平级奖
                                                $totalfenhongmoney = bcadd($totalfenhongmoney,$totalfenhongmoney_pj,2);
                                            }
                                            if($teamfenhong_pingji_source==1){
                                                //平级奖来源于前面会员的团队分红
                                                $midteamfhArr[$parent['id']]['totalcommission'] = bcsub($midteamfhArr[$parent['id']]['totalcommission'],$totalfenhongmoney_pj,2);
                                                $midteamfhArr[$parent['id']]['commission'] = bcsub($midteamfhArr[$parent['id']]['commission'],$totalfenhongmoney_pj,2);
                                            }
                                            if($pingji_yueji_bonus_status==1 && $pingji_yueji_bonus==1){
                                                //越级拿平级奖后，上级的平级会员不再拿奖
                                                break;
                                            }
                                        }
                                    }
                                }
                            }
                        }

                        //伯乐奖 找最近的上级，脱离后的新上级不算，发给原上级 （teamfenhong_bole_origin=1 仅发放原上级：新上级不发奖励，只给脱离过同时存在新上级和原上级的原上级发奖励）
                        //奖金参数优先使用产品单独设置的
                        $teamfenhong_bole_bl = $leveldata['teamfenhong_bole_bl'];//没脱离的比例
                        $teamfenhong_bole_bl_tuoli = $leveldata['teamfenhong_bole_bl_tuoli'];//脱离的比例
                        $teamfenhong_bole_money = $leveldata['teamfenhong_bole_money'];
                        $teamfenhong_bole_money_tuoli = $leveldata['teamfenhong_bole_money_tuoli'];
                        $wangyangjun = getcustom('plug_wangyangjun',$aid);

                        if($wangyangjun){
                            //update@24-4-6
                            //判断下单人和获得团队分红人的关系，不是原推荐人的情况不发
                            if($parent['id'] == $member['pid'] && empty($member['pid_origin'])){
                                $boleStatus = false;
                            }
                        }

                        if(getcustom('teamfenhong_bole',$aid) && $boleStatus && ($teamfenhong_bole_bl>0 || $teamfenhong_bole_money > 0 || $teamfenhong_bole_bl_tuoli > 0 || $teamfenhong_bole_money_tuoli > 0) && ($totalfenhongmoney > 0 || $leveldata['teamfenhong_bole_type'] == 1)){
                            if($leveldata['teamfenhong_bole_origin'] == 1){
                                //$leveldata['teamfenhong_bole_origin']=1 仅发放原上级：购买人脱离过的只给原上级或者没脱离过的当前上级发奖励
                                if($parent['pid_origin']) {
                                    $parent_bl = Db::name('member')->where('id','=',$parent['pid_origin'])->find();
                                }else{
                                    $parent_bl = $parentList[$k+1];
                                }
                            }else{
                                if($parent['pid_origin']) {
                                    $parent_bl = Db::name('member')->where('id','=',$parent['pid_origin'])->find();
                                }else{
                                    $parent_bl = $parentList[$k+1];
                                }
                            }

                            if($parent_bl){
                                $parent_bl_level = $teamfhlevellist[$parent_bl['levelid']];
                                if($parent_bl['levelstarttime'] >= $og['createtime']) {
                                    $parentbl_buy_levelid = Db::name('member_levelup_order')->where('aid',$aid)->where('mid', $parent_bl['id'])->where('status', 2)
                                        ->where('levelup_time', '<', $og['createtime'])->whereIn('levelid',$defaultLevelIds)->order('levelup_time', 'desc')->value('levelid');
                                    if($parentbl_buy_levelid) {
                                        $parent_bl['levelid'] = $parentbl_buy_levelid;
                                        $parent_bl_level = Db::name('member_level')->where('aid',$aid)->where('id',$parent_bl['levelid'])->find();
                                    }
                                }
                            }
                            Log::write([
                                'file'=>__FILE__.__LINE__,
                                'fun'=>__FUNCTION__,
                                '$parent'=>json_encode($parent),
                                '$parent_bl'=>json_encode($parent_bl),
                                '$parent_bl_level'=>json_encode($parent_bl_level),
                                '$leveldata'=>json_encode($leveldata)
                            ]);


                            //团队中伯乐奖只发一次
                            if($parent_bl &&
                                (($parent_bl_level['teamfenhong_bole_one'] && !in_array($parent_bl['levelid'],$boleArr['levelids'])) || empty($parent_bl_level['teamfenhong_bole_one']))
                            ){
                                $totalfenhongmoney = 0;
                                //dump('type:'.$leveldata['teamfenhong_bole_type'].',比例：'.$teamfenhong_bole_bl.'，分红金额：'.$fenhongprice.'，上次团队分红：'.$last_totalfenhongmoney);
                                if($leveldata['teamfenhong_bole_type'] == 0){
                                    //按奖励金额
                                    if($parent['pid_origin']) {
                                        $totalfenhongmoney += $teamfenhong_bole_bl_tuoli * $last_totalfenhongmoney * 0.01;
                                    }else{
                                        $totalfenhongmoney += $teamfenhong_bole_bl * $last_totalfenhongmoney * 0.01;
                                    }
                                }elseif($leveldata['teamfenhong_bole_type'] == 1){
                                    //按订单金额
                                    if($parent['pid_origin']) {
                                        $totalfenhongmoney += $teamfenhong_bole_bl_tuoli * $fenhongprice * 0.01;
                                    }else{
                                        $totalfenhongmoney += $teamfenhong_bole_bl * $fenhongprice * 0.01;
                                    }
                                }
                                //脱离过
                                if($parent['pid_origin']) {
                                    if($teamfenhong_bole_money > 0 ){
                                        $totalfenhongmoney += $teamfenhong_bole_money_tuoli;
                                        $og['bole_money'] = $teamfenhong_bole_money_tuoli;
                                        $teamfenhong_orderids[$parent_bl['id']][] = $og['orderid'];
                                    }
                                }else{
                                    if($teamfenhong_bole_money > 0 ){
                                        $totalfenhongmoney += $teamfenhong_bole_money;
                                        $og['bole_money'] = $teamfenhong_bole_money;
                                        $teamfenhong_orderids[$parent_bl['id']][] = $og['orderid'];
                                    }
                                }
                                //dump($parent_bl['id'].'伯乐奖进入'.$totalfenhongmoney);
                                if($totalfenhongmoney > 0){
                                    if($isyj == 1 && $yjmid == $parent_bl['id']){
                                        $commissionyj_my += $totalfenhongmoney;
                                    }
                                    if($commissionpercent != 1){
                                        $fenhongcommission = round($totalfenhongmoney*$commissionpercent,2);
                                        $fenhongmoney = round($totalfenhongmoney*$moneypercent,2);
                                    }else{
                                        $fenhongcommission = $totalfenhongmoney;
                                        $fenhongmoney = 0;
                                    }
                                    if($pj_duli){
                                        if($midteamfhArrBole[$parent_bl['id']]){
                                            $midteamfhArrBole[$parent_bl['id']]['totalcommission'] = $midteamfhArrBole[$parent_bl['id']]['totalcommission'] + $totalfenhongmoney;
                                            $midteamfhArrBole[$parent_bl['id']]['commission'] = $midteamfhArrBole[$parent_bl['id']]['commission'] + $fenhongcommission;
                                            $midteamfhArrBole[$parent_bl['id']]['money'] = $midteamfhArrBole[$parent_bl['id']]['money'] + $fenhongmoney;
                                            $midteamfhArrBole[$parent_bl['id']]['ogids'][] = $og['id'];
                                            $midteamfhArrBole[$parent_bl['id']]['levelid'] = $parent_bl['levelid'];
                                        }else{
                                            $midteamfhArrBole[$parent_bl['id']] = [
                                                'totalcommission'=>$totalfenhongmoney,
                                                'commission'=>$fenhongcommission,
                                                'money'=>$fenhongmoney,
                                                'ogids'=>[$og['id']],
                                                'module'=>$og['module'] ?? 'shop',
                                                'levelid' => $parent_bl['levelid']
                                            ];
                                        }
                                    }else{
                                        if($midteamfhArr[$parent_bl['id']]){
                                            $midteamfhArr[$parent_bl['id']]['totalcommission'] = $midteamfhArr[$parent_bl['id']]['totalcommission'] + $totalfenhongmoney;
                                            $midteamfhArr[$parent_bl['id']]['commission'] = $midteamfhArr[$parent_bl['id']]['commission'] + $fenhongcommission;
                                            $midteamfhArr[$parent_bl['id']]['money'] = $midteamfhArr[$parent_bl['id']]['money'] + $fenhongmoney;
                                            $midteamfhArr[$parent_bl['id']]['ogids'][] = $og['id'];
                                            $midteamfhArr[$parent_bl['id']]['levelid'] = $parent_bl['levelid'];
                                        }else{
                                            $midteamfhArr[$parent_bl['id']] = [
                                                'totalcommission'=>$totalfenhongmoney,
                                                'commission'=>$fenhongcommission,
                                                'money'=>$fenhongmoney,
                                                'ogids'=>[$og['id']],
                                                'module'=>$og['module'] ?? 'shop',
                                                'levelid' => $parent_bl['levelid']
                                            ];
                                        }
                                    }

                                    $boleArr['levelids'][] = $parent_bl['levelid'];
                                    $boleArr['mids'][] = $parent_bl['id'];
                                    if(getcustom('commission_orderrefund_deduct',$aid) && $isyj == 0){
                                        self::fhrecord($aid,$parent_bl['id'],$fenhongcommission,0,$og['id'],$og['module'] ?? 'shop','teamfenhong',t('团队分红',$aid));
                                    }
                                }
                            }
                           // foreach($parentList as $k_bl=>$parent_bl){
                           //     if($k_bl > $k){//&& $leveldata['sort'] < $levellist[$member['levelid']]['sort']
                           //         break;
                           //     }
                           // }

                        }
                    }
                    //其他分组等级
                    if(getcustom('plug_sanyang',$aid)) {
                        $catList = Db::name('member_level_category')->where('aid', $aid)->where('isdefault', 0)->select()->toArray();
                        foreach ($catList as $cat) {
                            $parentList = Db::name('member_level_record')->field('mid id,levelid')->where('aid', $aid)->where('cid', $cat['id'])->whereIn('mid', $pids)->select()->toArray();
                            $parentList = array_reverse($parentList);
                            $hasfhlevelids = [];
                            $last_teamfenhongbl = 0;
                            foreach($parentList as $k=>$parent){
                                //判断升级时间
                                $leveldata = $teamfhlevellist[$parent['levelid']];
                                if($parent['levelstarttime'] >= $og['createtime']) {
                                    $levelup_order_levelid = Db::name('member_levelup_order')->where('aid',$aid)->where('mid', $parent['id'])->where('status', 2)
                                        ->where('levelup_time', '<', $og['createtime'])->whereNotIn('levelid',$defaultLevelIds)->order('levelup_time', 'desc')->value('levelid');
                                    if($levelup_order_levelid) {
                                        $parent['levelid'] = $levelup_order_levelid;
                                        $leveldata = $teamfhlevellist[$parent['levelid']];
                                    }
                                }
                                if(!$leveldata || $k>=$leveldata['teamfenhonglv']) continue;
                                if($parent['id'] == $og['mid'] && $leveldata['teamfenhong_self'] != 1) continue;
                                //每单奖励
                                if($leveldata['teamfenhong_money'] > 0 && !in_array($og['orderid'],$teamfenhong_orderids_cat[$parent['id']])) {
                                    if($leveldata['teamfenhongonly'] == 1 && in_array($parent['levelid'],$hasfhlevelids)) continue; //该等级设置了只给最近的上级分红
                                    $hasfhlevelids[] = $parent['levelid'];
                                    $commission = $leveldata['teamfenhong_money'];
                                    
                                    if($isyj == 1 && $yjmid == $parent['id']){
                                        $commissionyj_my += $commission;
                                    }

                                    if($commissionpercent != 1){
                                        $fenhongcommission = round($commission*$commissionpercent,2);
                                        $fenhongmoney = round($commission*$moneypercent,2);
                                    }else{
                                        $fenhongcommission = $commission;
                                        $fenhongmoney = 0;
                                    }

                                    if($midteamfhArr[$parent['id']]){
                                        $midteamfhArr[$parent['id']]['totalcommission'] = $midteamfhArr[$parent['id']]['totalcommission'] + $commission;
                                        $midteamfhArr[$parent['id']]['commission'] = $midteamfhArr[$parent['id']]['commission'] + $fenhongcommission;
                                        $midteamfhArr[$parent['id']]['money'] = $midteamfhArr[$parent['id']]['money'] + $fenhongmoney;
                                        $midteamfhArr[$parent['id']]['ogids'][] = $og['id'];
                                        $midteamfhArr[$parent['id']]['levelid'] = $parent['levelid'];
                                    }else{
                                        $midteamfhArr[$parent['id']] = [
                                            'totalcommission'=>$commission,
                                            'commission'=>$fenhongcommission,
                                            'money'=>$fenhongmoney,
                                            'ogids'=>[$og['id']],
                                            'module'=>$og['module'] ?? 'shop',
                                            'levelid' => $parent['levelid']
                                        ];
                                    }
                                    if(getcustom('commission_orderrefund_deduct',$aid) && $isyj == 0){
                                        self::fhrecord($aid,$parent['id'],$fenhongcommission,0,$og['id'],$og['module'] ?? 'shop','teamfenhong',t('团队分红',$aid));
                                    }

                                    $teamfenhong_orderids_cat[$parent['id']][] = $og['orderid'];
                                }
                                //分红比例
                                if($leveldata['teamfenhongbl'] > 0) {
                                    if($isjicha){
                                        $this_teamfenhongbl = $leveldata['teamfenhongbl'] - $last_teamfenhongbl;
                                    }else{
                                        $this_teamfenhongbl = $leveldata['teamfenhongbl'];
                                    }
                                    if($this_teamfenhongbl <=0) continue;
                                    $last_teamfenhongbl = $last_teamfenhongbl + $this_teamfenhongbl;
                                    if($leveldata['teamfenhongonly'] == 1 && in_array($parent['levelid'],$hasfhlevelids)) continue; //该等级设置了只给最近的上级分红
                                    $hasfhlevelids[] = $parent['levelid'];

                                    $commission = $this_teamfenhongbl * $fenhongprice * 0.01;
                                    
                                    if($isyj == 1 && $yjmid == $parent['id']){
                                        $commissionyj_my += $commission;
                                    }
                                    
                                    if($commissionpercent != 1){
                                        $fenhongcommission = round($commission*$commissionpercent,2);
                                        $fenhongmoney = round($commission*$moneypercent,2);
                                    }else{
                                        $fenhongcommission = $commission;
                                        $fenhongmoney = 0;
                                    }

                                    if($midteamfhArr[$parent['id']]){
                                        $midteamfhArr[$parent['id']]['totalcommission'] = $midteamfhArr[$parent['id']]['totalcommission'] + $commission;
                                        $midteamfhArr[$parent['id']]['commission'] = $midteamfhArr[$parent['id']]['commission'] + $fenhongcommission;
                                        $midteamfhArr[$parent['id']]['money'] = $midteamfhArr[$parent['id']]['money'] + $fenhongmoney;
                                        $midteamfhArr[$parent['id']]['ogids'][] = $og['id'];
                                        $midteamfhArr[$parent['id']]['levelid'] = $parent['levelid'];
                                    }else{
                                        $midteamfhArr[$parent['id']] = [
                                            'totalcommission'=>$commission,
                                            'commission'=>$fenhongcommission,
                                            'money'=>$fenhongmoney,
                                            'ogids'=>[$og['id']],
                                            'module'=>$og['module'] ?? 'shop',
                                            'levelid' => $parent['levelid']
                                        ];
                                    }
                                    if(getcustom('commission_orderrefund_deduct',$aid) && $isyj == 0){
                                        self::fhrecord($aid,$parent['id'],$fenhongcommission,0,$og['id'],$og['module'] ?? 'shop','teamfenhong',t('团队分红',$aid));
                                    }

                                }
                            }
                        }
                    }
                }
            }
            //dump($midteamfhArr);exit;
            if($isyj == 1 && $commissionyj_my > 0){
                $commissionyj += $commissionyj_my;
                $og['commission'] = round($commissionyj_my,2);
                $og['fhname'] = t('团队分红',$aid);
                $newoglist[] = $og;
            }

            //todo 团队分红共享
//            dump($midteamfhArr);
            if(getcustom('teamfenhong_share',$aid)){
                $format = self::teamfenhong_share_format($aid,$midteamfhArr,$midteamfhArrNew);
                $midteamfhArr = $format['midteamfhArr'];
                $midteamfhArrNew = $format['midteamfhArrNew'];
            }
//            dd($format);

            if($isyj == 0 && $sysset['fhjiesuanhb'] == 0) {
                self::fafang($aid,$midteamfhArr,'teamfenhong',t('团队分红',$aid),0,$midteamfhArrNew);
                if($pj_duli && $midteamfhArrPj){
                    self::fafang($aid,$midteamfhArrPj,'teamfenhong_pj',t('团队分红平级奖',$aid),0);
                }
                if($pj_duli && $midteamfhArrBole){
                    self::fafang($aid,$midteamfhArrBole,'teamfenhong_bole',t('团队分红伯乐奖',$aid),0);
                }
                //根据分红奖发放购车基金和旅游基金
                if(getcustom('teamfenhong_gouche',$aid)){
                    self::goucheBonus($aid,$sysset,$midteamfhArr,$oglist,$isyj,$yjmid,$commissionpercent,$moneypercent);
                }
                if(getcustom('teamfenhong_lvyou',$aid)){
                    self::lvyouBonus($aid,$sysset,$midteamfhArr,$oglist,$isyj,$yjmid,$commissionpercent,$moneypercent);
                }
                //根据分红奖团队收益
                if(getcustom('teamfenhong_shouyi',$aid)){
                    self::teamshouyi($aid,$sysset,$midteamfhArr,$oglist,$isyj,$yjmid,$commissionpercent,$moneypercent);
                }
                //团队培育奖
                if(getcustom('teamfenhong_peiyujiang',$aid)){
                    self::teampeiyujiang($aid,$sysset,$midteamfhArr,$oglist,$isyj,$yjmid,$commissionpercent,$moneypercent);
                }
                $midteamfhArr = [];
                $midteamfhArrPj = [];
            }
        }
        //die('stop');
        if($isyj == 1){
            if(getcustom('teamfenhong_gouche',$aid)){
                //计算购车基金预收益
                $res_gouche = self::goucheBonus($aid,$sysset,$midteamfhArr,$oglist,$isyj,$yjmid,$commissionpercent,$moneypercent);
                if(!empty($res_gouche['commissionyj']) && $res_gouche['commissionyj']>0){
                    $res_gouche_commissionyj = $res_gouche['commissionyj']??0;
                    $commissionyj = bcadd($commissionyj,$res_gouche_commissionyj,2);
                    foreach($newoglist as $k=>$v){
                        $gouche_bonus = $res_gouche['oglist'][$v['id']]??0;
                        $newoglist[$k]['commission'] = bcadd($newoglist[$k]['commission'],$gouche_bonus,2);
                    }
                }
            }
            if(getcustom('teamfenhong_lvyou',$aid)){
                //计算旅游基金预收益
                $res_lvyou = self::lvyouBonus($aid,$sysset,$midteamfhArr,$oglist,$isyj,$yjmid,$commissionpercent,$moneypercent);
                if(!empty($res_lvyou['commissionyj']) && $res_lvyou['commissionyj']>0){
                    $res_lvyou_commissionyj = $res_lvyou['commissionyj']??0;
                    $commissionyj = bcadd($commissionyj,$res_lvyou_commissionyj,2);
                    foreach($newoglist as $k=>$v){
                        $lvyou_bonus = $res_lvyou['oglist'][$v['id']]??0;
                        $newoglist[$k]['commission'] = bcadd($newoglist[$k]['commission'],$lvyou_bonus,2);
                    }
                }
            }
            //计算团队收益预收益
            if(getcustom('teamfenhong_shouyi',$aid)){
                self::teamshouyi($aid,$sysset,$midteamfhArr,$oglist,$isyj,$yjmid,$commissionpercent,$moneypercent);
                if(!empty($res_lvyou['commissionyj']) && $res_lvyou['commissionyj']>0){
                    $res_lvyou_commissionyj = $res_lvyou['commissionyj']??0;
                    $commissionyj = bcadd($commissionyj,$res_lvyou_commissionyj,2);
                }
            }

            return ['commissionyj'=>round($commissionyj,2),'oglist'=>$newoglist];
        }
        if($isyj == 0 && $sysset['fhjiesuanhb'] == 1) {
            self::fafang($aid,$midteamfhArr,'teamfenhong',t('团队分红',$aid),0,$midteamfhArrNew);
            if($pj_duli && $midteamfhArrPj){
                self::fafang($aid,$midteamfhArrPj,'teamfenhong_pj',t('团队分红平级奖',$aid),0);
            }
            if($pj_duli && $midteamfhArrBole){
                self::fafang($aid,$midteamfhArrBole,'teamfenhong_bole',t('团队分红伯乐奖',$aid),0);
            }
            //根据分红奖发放购车基金和旅游基金
            if(getcustom('teamfenhong_gouche',$aid)){
                self::goucheBonus($aid,$sysset,$midteamfhArr,$oglist,$isyj,$yjmid,$commissionpercent,$moneypercent);
            }
            if(getcustom('teamfenhong_lvyou',$aid)){
                self::lvyouBonus($aid,$sysset,$midteamfhArr,$oglist,$isyj,$yjmid,$commissionpercent,$moneypercent);
            }
            //根据分红奖团队收益
            if(getcustom('teamfenhong_shouyi',$aid)){
                self::teamshouyi($aid,$sysset,$midteamfhArr,$oglist,$isyj,$yjmid,$commissionpercent,$moneypercent);
            }
            //团队培育奖
            if(getcustom('teamfenhong_peiyujiang',$aid)){
                self::teampeiyujiang($aid,$sysset,$midteamfhArr,$oglist,$isyj,$yjmid,$commissionpercent,$moneypercent);
            }
        }
    }

    private function teamfenhong_share_format($aid, $midteamfhArr,$midteamfhArrNew,$levellist = [])
    {
        if(getcustom('teamfenhong_share',$aid)){
            if(empty($midteamfhArr)) return $midteamfhArr;
            if(empty($levellist)){
                $levellist = Db::name('member_level')->where('aid',$aid)->column('*','id');
                if(empty($levellist)) return $midteamfhArr;
            }
//            $midteamfhArrNew = [];
//            'totalcommission'=>$totalfenhongmoney,
//            'commission'=>$fenhongcommission,
//            'money'=>$fenhongmoney,
//            'score'=>$fenhongscore,
//            'ogids'=>[$og['id']],
//            'module'=>$og['module'] ?? 'shop',
//            'levelid' => $parent['levelid'],
//            'type' => '团队分红',
//            'downMember' => $k > 0 ? $parentList[$k-1] : $member
            //团队分红共享奖
            foreach ($midteamfhArr as $mid =>$item){
                if($item['commission'] <= 0) continue;
                if(empty($item['downMember']['pid_origin'])) {
                    Log::write([
                        'file'=>__FILE__.__LINE__,
                        'fun'=>__FUNCTION__,
                        'item'=>$item,
                        'msg'=>'下级不存在原上级'
                    ]);
                    continue;
                }
                $level = $levellist[$item['levelid']];
                if(empty($level['teamfenhong_share_pid_origin_bl']) || $level['teamfenhong_share_pid_origin_bl'] < 0) { //原上级奖励比例(%)
                    Log::write([
                        'file'=>__FILE__.__LINE__,
                        'fun'=>__FUNCTION__,
                        'item'=>$item,
                        'level'=>$level,
                        'msg'=>'原上级奖励比例为空或小于0'
                    ]);
                    continue;
                }
                $down_levelid = explode(',',$level['teamfenhong_share_down_levelid']);//下级等级ID
                if($level['teamfenhong_share_down_levelid'] != 0 && !in_array($item['downMember']['levelid'],$down_levelid)) {
                    Log::write([
                        'file'=>__FILE__.__LINE__,
                        'fun'=>__FUNCTION__,
                        'level'=>$level,
                        'msg'=>'下级不在等级范围内'
                    ]);
                    continue;
                }
                $parentOrigin = Db::name('member')->where('aid',$aid)->where('id',$item['downMember']['pid_origin'])->find();
                if(empty($parentOrigin)) {
                    Log::write([
                        'file'=>__FILE__.__LINE__,
                        'fun'=>__FUNCTION__,
                        'item'=>$item,
                        'msg'=>'原上级不存在'
                    ]);
                    continue;
                }
                $pid_origin_levelid = explode(',',$level['teamfenhong_share_pid_origin_levelid']);//原上级等级ID
                if($level['teamfenhong_share_pid_origin_levelid'] != 0 && !in_array($parentOrigin['levelid'],$pid_origin_levelid)) {
                    Log::write([
                        'file'=>__FILE__.__LINE__,
                        'fun'=>__FUNCTION__,
                        'level'=>$level,
                        'parentOrigin'=>$parentOrigin,
                        'msg'=>'原上级等级不在范围内'
                    ]);
                    continue;
                }

                $money = $item['commission'];
                $moneyParentOrigin = round($money * $level['teamfenhong_share_pid_origin_bl'] * 0.01,2);
                $moneyParent = $money - $moneyParentOrigin;
                if($moneyParentOrigin > 0){
                    if($moneyParent){
                        if($midteamfhArrNew[$mid]){
                            $midteamfhArrNew[$mid]['totalcommission'] += $moneyParent;
                            $midteamfhArrNew[$mid]['commission'] += $moneyParent;
                            $midteamfhArrNew[$mid]['money'] += $midteamfhArr[$mid]['money'];
                            $midteamfhArrNew[$mid]['score'] += $midteamfhArr[$mid]['score'];
                        }else{
                            $midteamfhArrNew[$mid] = [
                                'totalcommission'=>$moneyParent,
                                'commission'=>$moneyParent,
                                'money'=>$item['money'],
                                'score'=>$item['score'],
                                'ogids'=>$item['ogids'],
                                'module'=>$item['module'],
                                'levelid' => $item['levelid'],
                                'type' => $item['type'],
                                'remark'=> t('团队分红共享奖',$aid)
                            ];
                        }
                    }
                    unset($midteamfhArr[$mid]);

                    if($midteamfhArrNew[$parentOrigin['id']]){
                        $midteamfhArrNew[$parentOrigin['id']]['totalcommission'] += $moneyParentOrigin;
                        $midteamfhArrNew[$parentOrigin['id']]['commission'] += $moneyParentOrigin;
                    }else{
                        $midteamfhArrNew[$parentOrigin['id']] = [
                            'totalcommission'=>$moneyParentOrigin,
                            'commission'=>$moneyParentOrigin,
                            'money'=>0,
                            'score'=>0,
                            'ogids'=>$item['ogids'],
                            'module'=>$item['module'],
                            'levelid' => $parentOrigin['levelid'],
                            'type' => '团队分红',
                            'remark'=> t('团队分红共享奖',$aid)
                        ];
                    }
                }
            }
            return ['midteamfhArr'=>$midteamfhArr,'midteamfhArrNew'=>$midteamfhArrNew];
        }
    }

    private function get_pid_origin_bylog($aid,$mid,$pid,$order_paytime=0,$defaultLevelIds=[]){
        $changelog = Db::name('member_pid_changelog')->where('aid',$aid)->where('mid',$mid)->where('pid',$pid)
            ->where('createtime','>=',$order_paytime)->order('createtime','desc')->find();
        if($changelog){
            $parent = Db::name('member')->where('id',$changelog['pid_origin'])->find();
            if(is_null($parent['pid_origin'])){
                //可能是回归了 没有原上级
                $plog = Db::name('member_pid_changelog')->where('aid',$aid)->where('mid',$parent['id'])->where('pid',$changelog['pid'])->order('createtime','desc')->find();
                if($plog['isback'] == 1){
                    $parent['pid_origin'] = $plog['pid_origin'];
                    $parent['levelid'] = Db::name('member_levelup_order')->where('aid', $aid)->where('mid', $parent['id'])->where('status', 2)
                        ->where('levelup_time', '<', $order_paytime)->whereIn('levelid', $defaultLevelIds)->order('levelup_time', 'desc')->value('levelid');
                }
            }
            return $parent ? $parent : [];
        }
        return false;
    }
    //团队见单分红
    public static function teamfenhong_jiandan($aid,$sysset,$oglist,$starttime=0,$endtime=0,$isyj=0,$yjmid=0){
        $teamfenhong_jiandan_custom = getcustom('teamfenhong_jiandan',$aid);
        if($teamfenhong_jiandan_custom) {
            if ($endtime == 0) $endtime = time();
            if(getcustom('maidan_fenhong_new',$aid)){
                $bids_maidan = Db::name('business')->where('maidan_team_jiandan',1)->column('id');
                $bids_maidan = array_merge([0],$bids_maidan);
            }
            if ($isyj == 1 && !$oglist) {
                //多商户的商品是否参与分红
                if ($sysset['fhjiesuanbusiness'] == 1) {
                    $bwhere = '1=1';
                } else {
                    $bwhere = [['og.bid', '=', '0']];
                }
                $oglist = Db::name('shop_order_goods')->alias('og')->field('og.*,o.area2,m.nickname,m.headimg')->where('og.aid', $aid)->where('og.isfenhong', 0)->where('og.status', 'in', [1, 2, 3])->where('og.refund_num',0)->join('shop_order o', 'o.id=og.orderid')->join('member m', 'm.id=og.mid')->where($bwhere)->order('og.id desc')->select()->toArray();
                if (!$oglist) $oglist = [];
                if(getcustom('maidan_fenhong_new',$aid) && $sysset['maidanfenhong']){
                    //买单分红
                    $bwhere_maidan = [['og.bid', 'in', $bids_maidan]];
                    $maidan_orderlist = Db::name('maidan_order')
                        ->alias('og')
                        ->join('member m','m.id=og.mid')
                        ->where('og.aid',$aid)
                        ->where('og.isfenhong',0)
                        ->where('og.status',1)
                        ->where($bwhere_maidan)
                        ->field('og.*,m.nickname,m.headimg')
                        ->order('og.id desc')
                        ->select()
                        ->toArray();
                    if($maidan_orderlist){
                        foreach($maidan_orderlist as $mdk=>$mdv){
                            $mdv['name']             = $mdv['title'];
                            $mdv['real_totalprice']  = $mdv['paymoney'];
                            //买单分红结算方式
                            if($sysset['maidanfenhong_type'] == 1){
                                //按利润结算时直接把销售额改成利润
                                $mdv['real_totalprice'] = $mdv['paymoney'] - $mdv['cost_price'];
                            }
                            $mdv['cost_price']       = 0;
                            $mdv['num']              = 1;
                            $mdv['module']           = 'maidan';
                            $oglist[] = $mdv;
                        }
                    }
                }
            }
            //参与团队分红的等级
            $teamfhlevellist = Db::name('member_level')->where('aid', $aid)->where('teamfenhong_jiandan_lv', '>', '0')->column('*', 'id');
            if (!$teamfhlevellist) return ['commissionyj' => 0, 'oglist' => []];

            if (!$oglist) return ['commissionyj' => 0, 'oglist' => []];

            $defaultCid = Db::name('member_level_category')->where('aid', $aid)->where('isdefault', 1)->value('id');
            if ($defaultCid) {
                $defaultLevelIds = Db::name('member_level')->where('aid', $aid)->where('cid', $defaultCid)->column('id');
            } else {
                $defaultLevelIds = Db::name('member_level')->where('aid', $aid)->column('id');
            }

            $isjicha = ($sysset['teamfenhong_jiandan_differential'] == 1 ? true : false);
            $allfenhongprice = 0;
            $ogids = [];
            $midteamfhArr = [];
            $teamfenhong_orderids = [];
            $teamfenhong_orderids_pj = [];
            $teamfenhong_orderids_cat = [];
            $newoglist = [];
            $commissionyj = 0;
            foreach ($oglist as $og) {
                if(getcustom('maidan_fenhong_new',$aid) && $og['module']=='maidan'){
                    if($og['bid'] > 0 && !in_array($og['bid'],$bids_maidan)){
                        continue;
                    }
                }
                $commissionyj_my = 0;

                $commissionpercent = 1;
                $moneypercent = 0;

                if ($sysset['fhjiesuantype'] == 0) {
                    $fenhongprice = $og['real_totalprice'];
                } else {
                    $fenhongprice = $og['real_totalprice'] - $og['cost_price'] * $og['num'];
                }
                if ($fenhongprice <= 0) continue;
                $ogids[] = $og['id'];
                $allfenhongprice = $allfenhongprice + $fenhongprice;
                $member = Db::name('member')->where('id', $og['mid'])->find();
                if ($teamfhlevellist) {
                    //判断脱离时间
                    if ($member['change_pid_time'] && $member['change_pid_time'] >= $og['createtime']) {
                        $pids = $member['path_origin'];
                    } else {
                        $pids = $member['path'];
                    }

                    if ($pids) {
                        $pids .= ',' . $og['mid'];
                    } else {
                        $pids = (string)$og['mid'];
                    }
                    if ($pids) {
                        $parentList = Db::name('member')->where('id', 'in', $pids)->order(Db::raw('field(id,' . $pids . ')'))->select()->toArray();
                        $parentList = array_reverse($parentList);
                        $hasfhlevelids = [];
                        $last_teamfenhongbl = 0;
                        $last_teamfenhongmoney = 0;
                        //层级判断，如购买人等级未开启“包含自己teamfenhong_jiandan_self“则购买人的上级为第一级，开启了则购买人为第一级
                        $level_i = 0;
                        foreach ($parentList as $k => $parent) {
                            $ii++;
                            //判断升级时间
                            $leveldata = $teamfhlevellist[$parent['levelid']];
                            if ($parent['levelstarttime'] >= $og['createtime']) {
                                $levelup_order_levelid = Db::name('member_levelup_order')->where('aid', $aid)->where('mid', $parent['id'])->where('status', 2)
                                    ->where('levelup_time', '<', $og['createtime'])->whereIn('levelid', $defaultLevelIds)->order('levelup_time', 'desc')->value('levelid');
                                if ($levelup_order_levelid) {
                                    $parent['levelid'] = $levelup_order_levelid;
                                    $leveldata = $teamfhlevellist[$parent['levelid']];
                                } else {
                                    unset($parentList[$k]);
                                    continue;
                                }
                            }
                            $level_i++;
                            if ($parent['id'] == $og['mid'] && $leveldata['teamfenhong_jiandan_self'] != 1) {
                                $level_i--;
                                unset($parentList[$k]);
                                continue;
                            }//不包含自己则层级-1
                            if (!$leveldata || $level_i > $leveldata['teamfenhong_jiandan_lv']) continue;
                            if ($leveldata['teamfenhong_jiandan_only'] == 1 && in_array($parent['levelid'], $hasfhlevelids)) continue; //该等级设置了只给最近的上级分红
                            $hasfhlevelids[] = $parent['levelid'];
                            if($og['module'] == 'shop'){
                                $product = Db::name('shop_product')->where('id', $og['proid'])->find();
                            }
                            if(getcustom('maidan_fenhong_new',$aid)){
                                if($og['module'] == 'maidan'){
                                    $product = [];
                                    $product['teamfenhong_jiandan_bl']   = 0;
                                    $product['teamfenhong_jiandan_money'] = 0;
                                }
                            }
                            if ($product['teamfenhongjdset'] == 1) { //按比例
                                $fenhongdata = json_decode($product['teamfenhongjddata1'], true);
                                if ($fenhongdata) {
                                    $leveldata['teamfenhong_jiandan_bl'] = $fenhongdata[$leveldata['id']]['commission'];
                                    $leveldata['teamfenhong_jiandan_money'] = 0;
                                }
                            } elseif ($product['teamfenhongjdset'] == 2) { //按固定金额
                                $fenhongdata = json_decode($product['teamfenhongjddata2'], true);
                                if ($fenhongdata) {
                                    $leveldata['teamfenhong_jiandan_bl'] = 0;
                                    $leveldata['teamfenhong_jiandan_money'] = $fenhongdata[$leveldata['id']]['commission'] * $og['num'];
                                }
                            } elseif ($product['teamfenhongjdset'] == -1) {
                                $leveldata['teamfenhong_jiandan_bl'] = 0;
                                $leveldata['teamfenhong_jiandan_money'] = 0;
                            }
                            if(getcustom('maidan_fenhong_new',$aid) && $og['module'] == 'maidan'){
                                $leveldata['teamfenhong_jiandan_bl'] = $leveldata['teamfenhong_jiandan_bl_maidan'];
                            }
                            //每单奖励
                            $totalfenhongmoney = 0;
                            $totalfenhongscore = 0;
                            if ($leveldata['teamfenhong_jiandan_money'] > 0 && !in_array($og['orderid'], $teamfenhong_orderids[$parent['id']])) {
                                if ($isjicha) {
                                    $totalfenhongmoney = $totalfenhongmoney + $leveldata['teamfenhong_jiandan_money'] - $last_teamfenhongmoney;
                                } else {
                                    $totalfenhongmoney = $totalfenhongmoney + $leveldata['teamfenhong_jiandan_money'];
                                }
                                if ($totalfenhongmoney < 0) $totalfenhongmoney = 0;
                                $last_teamfenhongmoney = $last_teamfenhongmoney + $totalfenhongmoney;
                                $teamfenhong_orderids[$parent['id']][] = $og['orderid'];
                            }
                            //分红比例
                            if ($leveldata['teamfenhong_jiandan_bl'] > 0) {
                                if ($isjicha) {
                                    $this_teamfenhongbl = $leveldata['teamfenhong_jiandan_bl'] - $last_teamfenhongbl;
                                } else {
                                    $this_teamfenhongbl = $leveldata['teamfenhong_jiandan_bl'];
                                }
                                if ($this_teamfenhongbl <= 0) $this_teamfenhongbl = 0;
                                $last_teamfenhongbl = $last_teamfenhongbl + $this_teamfenhongbl;
                                $totalfenhongmoney = $totalfenhongmoney + $this_teamfenhongbl * $fenhongprice * 0.01;
                            }
                            if ($totalfenhongmoney > 0) {
                                if ($isyj == 1 && $yjmid == $parent['id']) {
                                    $commissionyj_my += $totalfenhongmoney;
                                }
                                if ($commissionpercent != 1) {
                                    $fenhongcommission = round($totalfenhongmoney * $commissionpercent, 2);
                                    $fenhongmoney = round($totalfenhongmoney * $moneypercent, 2);
                                } else {
                                    $fenhongcommission = $totalfenhongmoney;
                                    $fenhongmoney = 0;
                                }
                                if ($midteamfhArr[$parent['id']]) {
                                    $midteamfhArr[$parent['id']]['totalcommission'] = $midteamfhArr[$parent['id']]['totalcommission'] + $totalfenhongmoney;
                                    $midteamfhArr[$parent['id']]['commission'] = $midteamfhArr[$parent['id']]['commission'] + $fenhongcommission;
                                    $midteamfhArr[$parent['id']]['money'] = $midteamfhArr[$parent['id']]['money'] + $fenhongmoney;
                                    $midteamfhArr[$parent['id']]['ogids'][] = $og['id'];
                                    $midteamfhArr[$parent['id']]['levelid'] = $parent['levelid'];
                                } else {
                                    $midteamfhArr[$parent['id']] = [
                                        'totalcommission' => $totalfenhongmoney,
                                        'commission' => $fenhongcommission,
                                        'money' => $fenhongmoney,
                                        'ogids' => [$og['id']],
                                        'module' => $og['module'] ?? 'shop',
                                        'levelid' => $parent['levelid'],
                                        'type' => '团队见单分红',
                                    ];
                                }
                                if(getcustom('commission_orderrefund_deduct',$aid) && $isyj == 0){
                                    self::fhrecord($aid,$parent['id'],$fenhongcommission,0,$og['id'],$og['module'] ?? 'shop','teamfenhong_jiandan',t('团队见单分红',$aid));
                                }
                            }
                        }
                    }
                }
                if ($isyj == 1 && $commissionyj_my > 0) {
                    $commissionyj += $commissionyj_my;
                    $og['commission'] = round($commissionyj_my, 2);
                    $og['fhname'] = t('团队见单分红', $aid);
                    $newoglist[] = $og;
                }
                if ($isyj == 0 && $sysset['fhjiesuanhb'] == 0) {
                    self::fafang($aid, $midteamfhArr, 'teamfenhong_jiandan', t('团队见单分红', $aid));
                    //根据分红奖团队收益
                    if(getcustom('teamfenhong_shouyi',$aid)){
                        self::teamshouyi($aid,$sysset,$midteamfhArr,$oglist,$isyj,$yjmid,$commissionpercent,$moneypercent);
                    }
                    $midteamfhArr = [];
                }
            }
            //die('stop');
            if ($isyj == 1) {
                //计算团队收益预收益
                if(getcustom('teamfenhong_shouyi',$aid)){
                    self::teamshouyi($aid,$sysset,$midteamfhArr,$oglist,$isyj,$yjmid,$commissionpercent,$moneypercent);
                    if(!empty($res_lvyou['commissionyj']) && $res_lvyou['commissionyj']>0){
                        $res_lvyou_commissionyj = $res_lvyou['commissionyj']??0;
                        $commissionyj = bcadd($commissionyj,$res_lvyou_commissionyj,2);
                    }
                }
                return ['commissionyj' => round($commissionyj, 2), 'oglist' => $newoglist];
            }
            if ($isyj == 0 && $sysset['fhjiesuanhb'] == 1) {
                self::fafang($aid, $midteamfhArr, 'teamfenhong_jiandan', t('团队见单分红', $aid));
                //根据分红奖团队收益
                if(getcustom('teamfenhong_shouyi',$aid)){
                    self::teamshouyi($aid,$sysset,$midteamfhArr,$oglist,$isyj,$yjmid,$commissionpercent,$moneypercent);
                }
            }
        }
    }
    //区域代理分红
    //门店区域代理分红未发放原因有：1.门店资料的area为空（腾讯地图接口超限）
    public static function areafenhong($aid,$sysset,$oglist,$starttime=0,$endtime=0,$isyj=0,$yjmid=0){
        if($endtime == 0) $endtime = time();
        if(getcustom('fenhong_business_item_switch',$aid)){
            //查找开启的多商户
            $bids = Db::name('business')->where('aid',$aid)->where('areafenhong_status',1)->column('id');
            $bids = array_merge([0],$bids);
        }
        if(getcustom('maidan_fenhong_new',$aid)){
            $bids_maidan = Db::name('business')->where('maidan_area',1)->column('id');
            $bids_maidan = array_merge([0],$bids_maidan);
        }
        if($isyj == 1 && !$oglist){
            //多商户的商品是否参与分红
            if($sysset['fhjiesuanbusiness'] == 1){
                $bwhere = '1=1';
            }else{
                $bwhere = [['og.bid','=','0']];
            }
            if(getcustom('fenhong_business_item_switch',$aid)){
                $oglist = Db::name('shop_order_goods')->alias('og')->field('og.*,o.area2,m.nickname,m.headimg')
                    ->where('og.bid','in',$bids)
                    ->where('og.aid',$aid)->where('og.isfenhong',0)->where('og.status','in',[1,2,3])->where('og.refund_num',0)->join('shop_order o','o.id=og.orderid')->join('member m','m.id=og.mid')->where($bwhere)->order('og.id desc')->select()->toArray();
            }else{
                $oglist = Db::name('shop_order_goods')->alias('og')->field('og.*,o.area2,m.nickname,m.headimg')->where('og.aid',$aid)->where('og.isfenhong',0)->where('og.status','in',[1,2,3])->where('og.refund_num',0)->join('shop_order o','o.id=og.orderid')->join('member m','m.id=og.mid')->where($bwhere)->order('og.id desc')->select()->toArray();
            }
            if(getcustom('yuyue_fenhong',$aid)){
                $yyorderlist = Db::name('yuyue_order')->alias('og')->field('og.*,m.nickname,m.headimg')->where('og.aid',$aid)->where('og.isfenhong',0)->where('og.status','in',[1,2,3])->join('member m','m.id=og.mid')->where($bwhere)->order('og.id desc')->select()->toArray();
                foreach($yyorderlist as $k=>$v){
                    $v['name'] = $v['proname'];
                    $v['real_totalprice'] = $v['totalprice'];
                    $v['cost_price'] = $v['cost_price'] ?? 0;
                    $v['module'] = 'yuyue';
                    $oglist[] = $v;
                }
            }
            if(getcustom('scoreshop_fenhong',$aid)){
                $scoreshopoglist = Db::name('scoreshop_order_goods')->alias('og')->field('og.*,o.area2,m.nickname,m.headimg')->where('og.aid',$aid)->where('og.isfenhong',0)->where('og.status','in',[1,2,3])->join('scoreshop_order o','o.id=og.orderid')->join('member m','m.id=og.mid')->where($bwhere)->order('og.id desc')->select()->toArray();
                foreach($scoreshopoglist as $v){
                    $v['real_totalprice'] = $v['totalmoney'];
                    $v['module'] = 'scoreshop';
                    $oglist[] = $v;
                }
            }
            if(getcustom('luckycollage_fenhong',$aid)){
                $lcorderlist = Db::name('lucky_collage_order')->alias('og')->field('og.*,m.nickname,m.headimg')->where('og.aid',$aid)->where('og.isfenhong',0)->where('og.status','in',[1,2,3])->whereRaw('og.buytype=1 or og.iszj=1')->where('og.isjiqiren',0)->join('member m','m.id=og.mid')->where($bwhere)->order('og.id desc')->select()->toArray();
                foreach($lcorderlist as $k=>$v){
                    $v['name'] = $v['proname'];
                    $v['real_totalprice'] = $v['totalprice'];
                    $v['cost_price'] = 0;
                    $v['module'] = 'luckycollage';
                    $oglist[] = $v;
                }
            }
            if(getcustom('fenhong_times_coupon',$aid)){
                $cwhere[] =['og.isfenhong','=',0];
                $cwhere[] =['og.status','=',1];
                $cwhere[] =['og.paytime','>=',$starttime];
                $cwhere[] =['og.paytime','<',$endtime];
                if($sysset['fhjiesuanbusiness'] != 1){ //多商户的商品是否参与分红
                    $cwhere[] =['og.bid','=',0];
                }
                $couponorderlist = Db::name('coupon_order')->alias('og')
                    ->join('member m','m.id=og.mid')
                    ->where($cwhere)
                    ->field('og.*,m.nickname,m.headimg')
                    ->order('og.id desc')
                    ->select()
                    ->toArray();
                foreach($couponorderlist as $k=>$v){
                    $v['name'] = $v['title'];
                    $v['real_totalprice'] = $v['price'];
                    $v['cost_price'] = 0;
                    $v['module'] = 'coupon';
                    $oglist[] = $v;
                }
            }
            if(getcustom('fenhong_kecheng',$aid)){
                //课程直接支付，无区域分红
                $kwhere = [];
                $kwhere[] = ['og.aid','=',$aid];
                $kwhere[] = ['og.isfenhong','=',0];
                $kwhere[] = ['og.status','=',1];
                $kwhere[] = ['og.paytime','>=',$starttime];
                $kwhere[] = ['og.paytime','<',$endtime];
                if($sysset['fhjiesuanbusiness'] != 1){
                    $kwhere[] = ['og.bid','=','0'];
                }
                $kechenglist = Db::name('kecheng_order')
                    ->alias('og')
                    ->join('member m','m.id=og.mid')
                    ->where($kwhere)
                    ->field('og.*," " as area2,m.nickname,m.headimg')
                    ->select()
                    ->toArray();
                if($kechenglist){
                    foreach($kechenglist as $v){
                        $v['name']            = $v['title'];
                        $v['real_totalprice'] = $v['totalprice'];
                        $v['cost_price']      = 0;
                        $v['module']          = 'kecheng';
                        $v['num']             = 1;
                        $oglist[]             = $v;
                    }
                }
            }
            if(getcustom('maidan_fenhong_new',$aid) && $sysset['maidanfenhong']){
                //买单分红
                $bwhere_maidan = [['og.bid', 'in', $bids_maidan]];
                $maidan_orderlist = Db::name('maidan_order')
                    ->alias('og')
                    ->join('member m','m.id=og.mid')
                    ->where('og.aid',$aid)
                    ->where('og.isfenhong',0)
                    ->where('og.status',1)
                    ->where($bwhere_maidan)
                    ->field('og.*,m.nickname,m.headimg')
                    ->order('og.id desc')
                    ->select()
                    ->toArray();
                if($maidan_orderlist){
                    foreach($maidan_orderlist as $mdk=>$mdv){
                        $mdv['name']             = $mdv['title'];
                        $mdv['real_totalprice']  = $mdv['paymoney'];
                        //买单分红结算方式
                        if($sysset['maidanfenhong_type'] == 1){
                            //按利润结算时直接把销售额改成利润
                            $mdv['real_totalprice'] = $mdv['paymoney'] - $mdv['cost_price'];
                        }
                        $mdv['cost_price']       = 0;
                        $mdv['num']              = 1;
                        $mdv['module']           = 'maidan';
                        $oglist[] = $mdv;
                    }
                }
            }
            //if(getcustom('hotel')){
            //  $hotelorderlist = Db::name('hotel_order')->alias('og')->field('og.*,m.nickname,m.headimg')->where('og.aid',$aid)->where('og.isfenhong',0)->where('og.status','in',[1,2,3])->join('member m','m.id=og.mid')->where($bwhere)->order('og.id desc')->select()->toArray();
            //  foreach($hotelorderlist as $k=>$v){
            //      $v['name'] = $v['title'];
            //      $v['real_totalprice'] = $v['sell_price'];
            //      $v['cost_price'] = $v['cost_price'] ?? 0;
            //      $v['module'] = 'hotel';
            //      $oglist[] = $v;
            //  }
            //}
        }
        if(!$oglist) return ['commissionyj'=>0,'oglist'=>[]];
        //参与区域代理分红的等级
        $areafhlevellist = Db::name('member_level')->where('aid',$aid)->where('areafenhong','>','0')->order('sort,id')->column('*','id');
        if(!$areafhlevellist) return ['commissionyj'=>0,'oglist'=>[]];
        if($sysset['areafenhong_jiaquan'] == 1){
            $largearealevelids = Db::name('member_level')->where('aid',$aid)->where('areafenhong','10')->where('areafenhongbl','>',0)->column('id');
            $provincelevelids = Db::name('member_level')->where('aid',$aid)->where('areafenhong','1')->where('areafenhongbl','>',0)->column('id');
            $citylevelids = Db::name('member_level')->where('aid',$aid)->where('areafenhong','2')->where('areafenhongbl','>',0)->column('id');
        }

        $defaultCid = Db::name('member_level_category')->where('aid',$aid)->where('isdefault', 1)->value('id');
        if($defaultCid) {
            $defaultLevelIds = Db::name('member_level')->where('aid',$aid)->where('cid', $defaultCid)->column('id');
        } else {
            $defaultLevelIds = Db::name('member_level')->where('aid',$aid)->column('id');
        }
        $field = 'id,levelid,areafenhong_province,areafenhong_city,areafenhong_area,areafenhongbl,areafenhong';
        if(getcustom('maidan_fenhong_new',$aid)){
            $field.=',areafenhongbl_maidan';
        }
        $memberlist1 = Db::name('member')->field($field)->where('aid',$aid)->where('areafenhong',1)->where('areafenhongbl','>',0)->select()->toArray();
        $memberlist2 = Db::name('member')->field($field)->where('aid',$aid)->where('areafenhong',2)->where('areafenhongbl','>',0)->select()->toArray();
        $memberlist3 = Db::name('member')->field($field)->where('aid',$aid)->where('areafenhong',3)->where('areafenhongbl','>',0)->select()->toArray();
        $field.= ',areafenhong_largearea';
        $memberlist10 = Db::name('member')->field($field)->where('aid',$aid)->where('areafenhong',10)->where('areafenhongbl','>',0)->select()->toArray();

        $areamemberlist = array_merge((array)$memberlist1,(array)$memberlist2,(array)$memberlist3,(array)$memberlist10);
        //其他分组等级
        $member_level_record = Db::name('member_level_record')->field('mid id,levelid,areafenhong_province,areafenhong_city,areafenhong_area,areafenhongbl,areafenhong')->where('aid',$aid)->whereIn('areafenhong',[1,2,3])->where('areafenhongbl','>',0)->select()->toArray();
        $areamemberlist = array_merge((array)$areamemberlist, (array)$member_level_record);

        if(getcustom('member_area_agent_multi',$aid)){
            $areamemberlist2 = Db::name('member_area_agent')->where('aid',$aid)->where('areafenhong',
            'in',[1,2,3])->where('areafenhongbl','>',0)->select()->toArray();
        }

        $isjicha = ($sysset['areafenhong_differential'] == 1 ? true : false);
        $ogids = [];
        $midareafhArr = [];
        $midareafhArr2 = [];

        $newoglist = [];
        $commissionyj = 0;
        $businessArr = [];
        foreach($oglist as $og){
            if(getcustom('maidan_fenhong_new',$aid) && $og['module']=='maidan'){
                if($og['bid'] > 0 && !in_array($og['bid'],$bids_maidan)){
                    continue;
                }
            }
            if(getcustom('fenhong_business_item_switch',$aid) && $og['module']!='maidan'){
                if($og['bid'] > 0 && !in_array($og['bid'],$bids)){
                    continue;
                }
            }
            if($og['module'] == 'hotel') continue;
            $commissionyj_my = 0;
            if(getcustom('commission2moneypercent',$aid) && $sysset['commission2moneypercent1'] > 0){
                //是否是首单
                $beforeorder = Db::name('shop_order')->where('aid',$aid)->where('mid',$og['mid'])->where('status','in','1,2,3')->where('paytime','<',$og['paytime'])->find();
                if(!$beforeorder){
                    $commissionpercent = 1 - $sysset['commission2moneypercent1'] * 0.01;
                    $moneypercent = $sysset['commission2moneypercent1'] * 0.01;
                }else{
                    $commissionpercent = 1 - $sysset['commission2moneypercent2'] * 0.01;
                    $moneypercent = $sysset['commission2moneypercent2'] * 0.01;
                }
            }else{
                $commissionpercent = 1;
                $moneypercent = 0;
            }
            if($sysset['fhjiesuantype'] == 0){
                $fenhongprice = $og['real_totalprice'];
            }else{
                $fenhongprice = $og['real_totalprice'] - $og['cost_price'] * $og['num'];
            }
            if(getcustom('baikangxie',$aid)){
                $fenhongprice = $og['cost_price'] * $og['num'];
            }
            if($fenhongprice <= 0) continue;
            $ogids[] = $og['id'];
            $allfenhongprice = $allfenhongprice + $fenhongprice;
            $member = Db::name('member')->where('id', $og['mid'])->find();
            $member_extend = Db::name('member_level_record')->field('mid id,levelid')->where('aid', $aid)->where('mid', $og['mid'])->find();
            
            if($og['module'] == 'yuyue'){
                $product = Db::name('yuyue_product')->where('id',$og['proid'])->find();
            }elseif($og['module'] == 'coupon'){
                $product = Db::name('coupon')->where('id',$og['cpid'])->find();
            }elseif($og['module'] == 'luckycollage'){
                $product = Db::name('lucky_collage_product')->where('id',$og['proid'])->find();
                if (getcustom('luckycollage_fail_commission',$aid)) {
                    if ($og['iszj'] == 2) {
                        $product['fenhongset'] = $product['fail_fenhongset'];
                        $product['areafenhongset'] = $product['fail_areafenhongset'];
                        $product['areafenhongdata1'] = $product['fail_areafenhongdata1'];
                        $product['areafenhongdata2'] = $product['fail_areafenhongdata2'];

                    }
                }
                if ($product['fenhongset'] == 0) {
                    $product['areafenhongset'] = -1;
                }

            }elseif($og['module'] == 'scoreshop'){
                $product = Db::name('scoreshop_product')->where('id',$og['proid'])->find();
            }elseif($og['module'] == 'kecheng'){
                $product = Db::name('kecheng_list')->where('id',$og['kcid'])->find();
            //}elseif($og['module'] == 'hotel'){
            //  $product = Db::name('hotel_room')->where('id',$og['roomid'])->find();
            }elseif($og['module'] == 'maidan'){
                $product['areafenhongset'] = 0;//按会员等级
            }else{
                $product = Db::name('shop_product')->where('id',$og['proid'])->find();
            }
            if(getcustom('maidan_fenhong_new',$aid)){
                if($og['module'] == 'maidan'){
                    $product = [];
                    $product['areafenhongset']   = 0;
                    $product['areafenhongdata1'] = 0;
                    if($og['bid'] > 0){
                        if(isset($businessArr[$og['bid']])){
                            $business_info = $businessArr[$og['bid']];
                        }else{
                            $business_info = Db::name('business')->where('id',$og['bid'])->find();
                            $businessArr[$og['bid']] = $business_info;
                        }
                        $og['area2'] = $business_info['province'].','.$business_info['city'].','.$business_info['district'];
                    }else{
                        $og['area2'] = $sysset['province'].','.$sysset['city'].','.$sysset['district'];
                    }
                }
            }
            if(getcustom('ganer_fenxiao',$aid)){
                if(empty($og['area2'])){
                    if($og['bid'] > 0){
                        if(isset($businessArr[$og['bid']])){
                            $business_info = $businessArr[$og['bid']];
                        }else{
                            $business_info = Db::name('business')->where('id',$og['bid'])->find();
                            $businessArr[$og['bid']] = $business_info;
                        }
                        $og['area2'] = $business_info['province'].','.$business_info['city'].','.$business_info['district'];
                    }else{
                        $og['area2'] = $sysset['province'].','.$sysset['city'].','.$sysset['district'];
                    }
                }
            }
            if((getcustom('cashier_area_fenhong',$aid) || getcustom('maidan_area_fenhong',$aid))  && $og['bid']>0){
                if(isset($businessArr[$og['bid']])){
                    $business_info = $businessArr[$og['bid']];
                }else{
                    $business_info = Db::name('business')->where('id',$og['bid'])->find();
                    $businessArr[$og['bid']] = $business_info;
                }
                if(empty($og['area2'])){
                    $og['area2'] = $business_info['province'].','.$business_info['city'].','.$business_info['district'];
                }
            }

            $last_areafenhongbl = 0;
            $last_areafenhongmoney = 0;
            $last_areafenhong_score_percent = 0;
            $fenhong_manual_custom = getcustom('fenhong_manual',$aid);
            if(!$fenhong_manual_custom || $og['isfg']==1){
                //区域代理分红
                $areaArr = explode(',',$og['area2']);
                $province = $areaArr[0];
                $city = $areaArr[1];
                $area = $areaArr[2];
                foreach($areafhlevellist as $fhlevel){
                    if($product['areafenhongset'] == 1){ //按比例
                        $fenhongdata = json_decode($product['areafenhongdata1'],true);
                        if($fenhongdata){
                            $fhlevel['areafenhongbl'] = $fenhongdata[$fhlevel['id']]['commission'];
                            $fhlevel['areafenhong_money'] = 0;
                        }
                    }elseif($product['areafenhongset'] == 2){ //按固定金额
                        $fenhongdata = json_decode($product['areafenhongdata2'],true);
                        if($fenhongdata){
                            $fhlevel['areafenhongbl'] = 0;
                            $fhlevel['areafenhong_money'] = $fenhongdata[$fhlevel['id']]['commission'] * $og['num'];
                        }
                    }elseif($product['areafenhongset'] == 4){ //按积分比例
                        $fenhongdata = json_decode($product['areafenhongdata1'],true);
                        if($fenhongdata){
                            $fhlevel['areafenhongbl'] = 0;
                            $fhlevel['areafenhong_money'] = 0;
                            $fhlevel['areafenhong_score_percent'] = $fenhongdata[$fhlevel['id']]['score'];
                        }
                    }elseif($product['areafenhongset'] == -1){
                        $fhlevel['areafenhongbl'] = 0;
                        $fhlevel['areafenhong_money'] = 0;
                    }else{
                        $fhlevel['areafenhong_money'] = 0;
                    }
                    if(getcustom('maidan_fenhong_new',$aid)){
                        if($og['module'] == 'maidan'){
                            $fhlevel['areafenhongbl']   = $fhlevel['areafenhongbl_maidan'];
                        }
                    }

                    if($fhlevel['areafenhongbl']==0 && $fhlevel['areafenhong_money']==0 && $fhlevel['areafenhong_score_percent'] == 0 && $fhlevel['areafenhong_score_percent'] == 0 ) continue;
                    if($fhlevel['areafenhong'] == 3 && $province && $city && $area){
                        $memberlist = Db::name('member')->field('id,levelid,areafenhong_province,areafenhongbl,areafenhong')->where('levelid',$fhlevel['id'])->where('areafenhong',0)->where('areafenhong_province',$province)->where('areafenhong_city',$city)->where('areafenhong_area',$area)->select()->toArray();
                        if(getcustom('plug_sanyang',$aid)){
                            $memberlist_extend = Db::name('member_level_record')->field('mid id,levelid,areafenhong_province,areafenhongbl,areafenhong')->where('levelid',$fhlevel['id'])->where('areafenhong',0)->where('areafenhong_province',$province)->where('areafenhong_city',$city)->where('areafenhong_area',$area)->select()->toArray();
                        }
                        if($sysset['areafenhong_jiaquan'] == 1 && $largearealevelids){ //大区代理也参与
                            $largeareaList = Db::name('largearea')->where("find_in_set('{$province}',province)")->where('status',1)->column('name');
                            $memberlist10 = Db::name('member')->field('id,levelid,areafenhong_largearea,areafenhongbl,areafenhong')->where('levelid','in',$largearealevelids)->where('areafenhong',0)->where('areafenhong_largearea','in',$largeareaList)->select()->toArray();
                            $memberlist = array_merge((array)$memberlist, (array)$memberlist10);
                        }
                        if($sysset['areafenhong_jiaquan'] == 1 && ($provincelevelids || $citylevelids)){ 
                            if($provincelevelids){
                                $memberlist1 = Db::name('member')->field('id,levelid,areafenhong_province,areafenhongbl,areafenhong')->where('levelid','in',$provincelevelids)->where('areafenhong',0)->where('areafenhong_province',$province)->select()->toArray();
                            }else{
                                $memberlist1 = [];
                            }
                            if($citylevelids){
                                $memberlist2 = Db::name('member')->field('id,levelid,areafenhong_province,areafenhongbl,areafenhong')->where('levelid','in',$citylevelids)->where('areafenhong',0)->where('areafenhong_province',$province)->where('areafenhong_city',$city)->select()->toArray();
                            }else{
                                $memberlist2 = [];
                            }
                            $memberlist = array_merge((array)$memberlist, (array)$memberlist1, (array)$memberlist2);
                            //Log::write('$memberlist 3');
                            //Log::write($memberlist);
                        }
                    }
                    if($fhlevel['areafenhong'] == 2 && $province && $city){
                        $memberlist = Db::name('member')->field('id,levelid,areafenhong_province,areafenhongbl,areafenhong')->where('levelid',$fhlevel['id'])->where('areafenhong',0)->where('areafenhong_province',$province)->where('areafenhong_city',$city)->select()->toArray();
                        if(getcustom('plug_sanyang',$aid)){
                            $memberlist_extend = Db::name('member_level_record')->field('mid id,levelid,areafenhong_province,areafenhongbl,areafenhong')->where('levelid',$fhlevel['id'])->where('areafenhong',0)->where('areafenhong_province',$province)->where('areafenhong_city',$city)->select()->toArray();
                        }
                        if($sysset['areafenhong_jiaquan'] == 1 && $largearealevelids){ //大区代理也参与
                            $largeareaList = Db::name('largearea')->where("find_in_set('{$province}',province)")->where('status',1)->column('name');
                            $memberlist10 = Db::name('member')->field('id,levelid,areafenhong_largearea,areafenhongbl,areafenhong')->where('levelid','in',$largearealevelids)->where('areafenhong',0)->where('areafenhong_largearea','in',$largeareaList)->select()->toArray();
                            $memberlist = array_merge((array)$memberlist, (array)$memberlist10);
                        }
                        if($sysset['areafenhong_jiaquan'] == 1 && $provincelevelids){ //省级代理也参与
                            $memberlist1 = Db::name('member')->field('id,levelid,areafenhong_province,areafenhongbl,areafenhong')->where('levelid','in',$provincelevelids)->where('areafenhong',0)->where('areafenhong_province',$province)->select()->toArray();
                            $memberlist = array_merge((array)$memberlist, (array)$memberlist1);
                            //Log::write('$memberlist 2');
                            //Log::write($memberlist);
                        }
                    }
                    if($fhlevel['areafenhong'] == 1 && $province){
                        $memberlist = Db::name('member')->field('id,levelid,areafenhong_province,areafenhongbl,areafenhong')->where('levelid',$fhlevel['id'])->where('areafenhong',0)->where('areafenhong_province',$province)->select()->toArray();
                        if(getcustom('plug_sanyang',$aid))
                        $memberlist_extend = Db::name('member_level_record')->field('mid id,levelid,areafenhong_province,areafenhongbl,areafenhong')->where('levelid',$fhlevel['id'])->where('areafenhong',0)->where('areafenhong_province',$province)->select()->toArray();
                        
                        if($sysset['areafenhong_jiaquan'] == 1 && $largearealevelids){ //大区代理也参与
                            $largeareaList = Db::name('largearea')->where("find_in_set('{$province}',province)")->where('status',1)->column('name');
                            $memberlist10 = Db::name('member')->field('id,levelid,areafenhong_largearea,areafenhongbl,areafenhong')->where('levelid','in',$largearealevelids)->where('areafenhong',0)->where('areafenhong_largearea','in',$largeareaList)->select()->toArray();
                            $memberlist = array_merge((array)$memberlist, (array)$memberlist10);
                        }
                    }
                    if($fhlevel['areafenhong'] == 10 && $province){
                        $largeareaList = Db::name('largearea')->where("find_in_set('{$province}',province)")->where('status',1)->column('name');
                        $memberlist = Db::name('member')->field('id,levelid,areafenhong_largearea,areafenhongbl,areafenhong')->where('levelid',$fhlevel['id'])->where('areafenhong',0)->where('areafenhong_largearea','in',$largeareaList)->select()->toArray();
                    }
                    if(getcustom('plug_sanyang',$aid)){
                        $memberlist = array_merge((array)$memberlist, (array)$memberlist_extend);
                    }
                    if($memberlist){
                        $this_areafenhongbl = $fhlevel['areafenhongbl'];
                        $this_areafenhong_score_percent = $fhlevel['areafenhong_score_percent'];
                        if(($this_areafenhongbl > 0 || $this_areafenhong_score_percent > 0) && $isjicha){
                            $this_areafenhongbl = $fhlevel['areafenhongbl'] - $last_areafenhongbl;
                            $this_areafenhong_score_percent = $fhlevel['areafenhong_score_percent'] - $last_areafenhong_score_percent;
                        }
                        $last_areafenhongbl = $last_areafenhongbl + $this_areafenhongbl;
                        $last_areafenhong_score_percent = $last_areafenhong_score_percent + $this_areafenhong_score_percent;
                        
                        $areafenhong_money = $fhlevel['areafenhong_money'];
                        if($fhlevel['areafenhong_money'] > 0 && $isjicha){
                            $areafenhong_money = $fhlevel['areafenhong_money'] - $last_areafenhongmoney;
                        }
                        $last_areafenhongmoney = $last_areafenhongmoney + $areafenhong_money;
                        $commission = ($this_areafenhongbl * $fenhongprice * 0.01 + $areafenhong_money) / count($memberlist);
                        $commission_score = floor(($this_areafenhong_score_percent * $fenhongprice * 0.01) / count($memberlist));
                        if($commission <= 0 && $commission_score <= 0) continue;
                        //Log::write('$commission');
                        //Log::write($commission);
                        if(getcustom('fenhong_removefenxiao',$aid) && $fhlevel['areafenhong_removefenxiao'] == 1){
                            if($og['parent1'] && $og['parent1commission']){
                                $commission = $commission - $og['parent1commission'];
                            }
                            if($og['parent2'] && $og['parent2commission']){
                                $commission = $commission - $og['parent2commission'];
                            }
                            if($og['parent3'] && $og['parent3commission']){
                                $commission = $commission - $og['parent3commission'];
                            }
                            if($commission <= 0) continue;
                        }

                        if($commissionpercent != 1){
                            $fenhongcommission = round($commission*$commissionpercent,2);
                            $fenhongmoney = round($commission*$moneypercent,2);
                            $fenhongscore = round($commission_score*$commissionpercent);
                        }else{
                            $fenhongcommission = $commission;
                            $fenhongmoney = 0;
                            $fenhongscore = $commission_score;
                        }

                        foreach($memberlist as $member){
                            $mid = $member['id'];
                            if($isyj == 1 && $yjmid == $mid){
                                $commissionyj_my += $commission;
                            }
                            if($midareafhArr[$mid]){
                                $midareafhArr[$mid]['totalcommission'] = $midareafhArr[$mid]['totalcommission'] + $commission;
                                $midareafhArr[$mid]['commission'] = $midareafhArr[$mid]['commission'] + $fenhongcommission;
                                $midareafhArr[$mid]['money'] = $midareafhArr[$mid]['money'] + $fenhongmoney;
                                $midareafhArr[$mid]['score'] = $midareafhArr[$mid]['score'] + $fenhongscore;
                                $midareafhArr[$mid]['ogids'][] = $og['id'];
                            }else{
                                $midareafhArr[$mid] = [
                                    'totalcommission'=>$commission,
                                    'commission'=>$fenhongcommission,
                                    'money'=>$fenhongmoney,
                                    'score'=>$fenhongscore,
                                    'ogids'=>[$og['id']],
                                    'module'=>$og['module'] ?? 'shop'
                                ];
                            }
                            if(getcustom('commission_orderrefund_deduct',$aid) && $isyj == 0){
                                self::fhrecord($aid,$mid,$fenhongcommission,$fenhongscore,$og['id'],$og['module'] ?? 'shop','areafenhong',t('区域代理分红',$aid));
                            }
                        }
                    }
                }

                //如果商品设置为不等于-1不参与分红，则先根据商品设置后根据会员设置发分红
                if($product['areafenhongset']!=-1){
                    //单独设置的区域代理
                    if($areamemberlist){
                        foreach($areamemberlist as $member){
                            if(
                                ($member['areafenhong']==1 && $member['areafenhong_province'] == $province) || 
                                ($member['areafenhong']==2 && $member['areafenhong_province'] == $province && $member['areafenhong_city'] == $city) || 
                                ($member['areafenhong']==3 && $member['areafenhong_province'] == $province && $member['areafenhong_city'] == $city && $member['areafenhong_area'] == $area) || 
                                ($member['areafenhong']==10 && in_array($member['areafenhong_largearea'],Db::name('largearea')->where("find_in_set('{$province}',province)")->where('status',1)->column('name')))
                            ){

                                $commission = 0;
                                $commission_score = 0;
                                if($product['areafenhongset'] == 1 || $product['areafenhongset'] == 2) {
                                    $areafenhongbl = 0;
                                    $areafenhong_money = 0;
                                    $areafenhong_score_percent = 0;
                                    if ($product['areafenhongset'] == 1) { //按比例
                                        $fenhongdata = json_decode($product['areafenhongdata1'], true);
                                        if ($fenhongdata) {
                                            $areafenhongbl = $fenhongdata[$member['levelid']]['commission'];
                                            $areafenhong_money = 0;
                                        }
                                    } else { //按固定金额
                                        $fenhongdata = json_decode($product['areafenhongdata2'], true);
                                        if ($fenhongdata) {
                                            $areafenhongbl = 0;
                                            $areafenhong_money = $fenhongdata[$member['levelid']]['commission'] * $og['num'];
                                        }
                                    }
                                    $commission = ($areafenhongbl * $fenhongprice * 0.01 + $areafenhong_money);
                                }elseif($product['areafenhongset'] == 4){
                                    //按积分比例
                                    $fenhongdata = json_decode($product['areafenhongdata1'], true);
                                    if ($fenhongdata) {
                                        $areafenhong_score_percent = $fenhongdata[$member['levelid']]['score'];
                                        $areafenhong_money = 0;
                                        $commission_score = round($areafenhong_score_percent * $fenhongprice * 0.01);
                                    }
                                }else{

                                    if(getcustom('maidan_fenhong_new',$aid) && $og['module']=='maidan'){
                                        $member['areafenhongbl'] = $member['areafenhongbl_maidan'];
                                    }
                                    $commission = $fenhongprice * 0.01 * $member['areafenhongbl'];
                                    //$commission_score = round($areafenhong_score_percent * $fenhongprice * 0.01);//暂无单独设置
                                }

                                if(getcustom('yuyue_areafenhong_removefx',$aid)){
                                    if($og['parent1'] && $og['parent1commission']){
                                        $commission = $commission - $og['parent1commission'];
                                    }
                                    if($og['parent2'] && $og['parent2commission']){
                                        $commission = $commission - $og['parent2commission'];
                                    }
                                    if($og['parent3'] && $og['parent3commission']){
                                        $commission = $commission - $og['parent3commission'];
                                    }
                                    if($commission <= 0) continue;
                                }

                                $mid = $member['id'];
                                if($isyj == 1 && $yjmid == $mid){
                                    $commissionyj_my += $commission;
                                }
                                if($commissionpercent != 1){
                                    $fenhongcommission = round($commission*$commissionpercent,2);
                                    $fenhongmoney = round($commission*$moneypercent,2);
                                    $fenhongscore = $commission_score;
                                }else{
                                    $fenhongcommission = $commission;
                                    $fenhongmoney = 0;
                                    $fenhongscore = $commission_score;
                                }
                                if($midareafhArr[$mid]){
                                    $midareafhArr[$mid]['totalcommission'] = $midareafhArr[$mid]['totalcommission'] + $commission;
                                    $midareafhArr[$mid]['commission'] = $midareafhArr[$mid]['commission'] + $fenhongcommission;
                                    $midareafhArr[$mid]['money'] = $midareafhArr[$mid]['money'] + $fenhongmoney;
                                    $midareafhArr[$mid]['score'] = $midareafhArr[$mid]['score'] + $fenhongscore;
                                    $midareafhArr[$mid]['ogids'][] = $og['id'];
                                }else{
                                    $midareafhArr[$mid] = [
                                        'totalcommission'=>$commission,
                                        'commission'=>$fenhongcommission,
                                        'money'=>$fenhongmoney,
                                        'score'=>$fenhongscore,
                                        'ogids'=>[$og['id']],
                                        'module'=>$og['module'] ?? 'shop'
                                    ];
                                }
                                if(getcustom('commission_orderrefund_deduct',$aid) && $isyj == 0){
                                    self::fhrecord($aid,$mid,$fenhongcommission,$fenhongscore,$og['id'],$og['module'] ?? 'shop','areafenhong',t('区域代理分红',$aid));
                                }
                            }
                        }
                    }

                    //单独设置的区域代理
                    if($areamemberlist2){
                        foreach($areamemberlist2 as $member){
                            if(
                                ($member['areafenhong']==1 && $member['areafenhong_province'] == $province) ||
                                ($member['areafenhong']==2 && $member['areafenhong_province'] == $province && $member['areafenhong_city'] == $city) ||
                                ($member['areafenhong']==3 && $member['areafenhong_province'] == $province && $member['areafenhong_city'] == $city && $member['areafenhong_area'] == $area)
                            ){
                                $commission = 0;
                                $commission_score = 0;
                                if($product['areafenhongset'] == 1 || $product['areafenhongset'] == 2){
                                    $areafenhongbl     = 0;
                                    $areafenhong_money = 0;
                                    if($product['areafenhongset'] == 1){ //按比例
                                        $fenhongdata = json_decode($product['areafenhongdata1'],true);
                                        if($fenhongdata){
                                            $areafenhongbl     = $fenhongdata[$member['levelid']]['commission'];
                                            $areafenhong_money = 0;
                                        }
                                    }else{ //按固定金额
                                        $fenhongdata = json_decode($product['areafenhongdata2'],true);
                                        if($fenhongdata){
                                            $areafenhongbl     = 0;
                                            $areafenhong_money = $fenhongdata[$member['levelid']]['commission'] * $og['num'];
                                        }
                                    }
                                    $commission = ($areafenhongbl * $fenhongprice * 0.01 + $areafenhong_money);
                                }elseif($product['areafenhongset'] == 4){
                                    //按积分比例
                                    $fenhongdata = json_decode($product['areafenhongdata1'], true);
                                    if ($fenhongdata) {
                                        $areafenhong_score_percent = $fenhongdata[$member['levelid']]['score'];
                                        $areafenhong_money = 0;
                                        $commission_score = round($areafenhong_score_percent * $fenhongprice * 0.01);
                                    }
                                }else{
                                    $commission = $fenhongprice * 0.01 * $member['areafenhongbl'];
                                    //$commission_score = round($fenhongprice * 0.01 * $member['areafenhongbl']);//暂无单独设置
                                }

                                if(getcustom('yuyue_areafenhong_removefx',$aid)){
                                    if($og['parent1'] && $og['parent1commission']){
                                        $commission = $commission - $og['parent1commission'];
                                    }
                                    if($og['parent2'] && $og['parent2commission']){
                                        $commission = $commission - $og['parent2commission'];
                                    }
                                    if($og['parent3'] && $og['parent3commission']){
                                        $commission = $commission - $og['parent3commission'];
                                    }
                                    if($commission <= 0) continue;
                                }

                                $mid = $member['mid'];
                                if($isyj == 1 && $yjmid == $mid){
                                    $commissionyj_my += $commission;
                                }
                                if($commissionpercent != 1){
                                    $fenhongcommission = round($commission*$commissionpercent,2);
                                    $fenhongmoney = round($commission*$moneypercent,2);
                                    $fenhongscore = $commission_score;
                                }else{
                                    $fenhongcommission = $commission;
                                    $fenhongmoney = 0;
                                    $fenhongscore = $commission_score;
                                }
                                if($midareafhArr[$mid]){
                                    $midareafhArr[$mid]['totalcommission'] = $midareafhArr[$mid]['totalcommission'] + $commission;
                                    $midareafhArr[$mid]['commission'] = $midareafhArr[$mid]['commission'] + $fenhongcommission;
                                    $midareafhArr[$mid]['money'] = $midareafhArr[$mid]['money'] + $fenhongmoney;
                                    $midareafhArr[$mid]['score'] = $midareafhArr[$mid]['score'] + $fenhongscore;
                                    $midareafhArr[$mid]['ogids'][] = $og['id'];
                                }else{
                                    $midareafhArr[$mid] = [
                                        'totalcommission'=>$commission,
                                        'commission'=>$fenhongcommission,
                                        'money'=>$fenhongmoney,
                                        'score'=>$fenhongscore,
                                        'ogids'=>[$og['id']],
                                        'module'=>$og['module'] ?? 'shop'
                                    ];
                                }
                                if(getcustom('commission_orderrefund_deduct',$aid) && $isyj == 0){
                                    self::fhrecord($aid,$mid,$fenhongcommission,$fenhongscore,$og['id'],$og['module'] ?? 'shop','areafenhong',t('区域代理分红',$aid));
                                }
                            }
                        }
                    }
                }
                //多商户单独设置的区域分红
                if(getcustom('business_area_fenhong',$aid) && $og['bid']>0){
                    if(isset($businessArr[$og['bid']])){
                        $business_info = $businessArr[$og['bid']];
                    }else{
                        $business_info = Db::name('business')->where('id',$og['bid'])->find();
                        $businessArr[$og['bid']] = $business_info;
                    }
                    $b_province = $business_info['province'];
                    $b_province_bl = $business_info['areafenhong_province']??0;
                    $b_city = $business_info['city'];
                    $b_city_bl = $business_info['areafenhong_city']??0;
                    $b_area = $business_info['district'];
                    $b_area_bl = $business_info['areafenhong_district']??0;
                    if($b_province && $b_province_bl>0){
                        $memberProvinceB = Db::name('member')->where('aid',$aid)->where('areafenhong','1')->where('areafenhong_province',$b_province)->field('id,nickname')->select()->toArray();
                        //跟随会员等级的代理
                        $memberProvinceLevelist = Db::name('member_level')->where('aid',$aid)->where('areafenhong','1')->select()->toArray();
                        foreach ($memberProvinceLevelist as $fk=>$lv){
                            $areafenhongmaxnum  = 9999999;
                            if($lv['areafenhongmaxnum']>0) $areafenhongmaxnum = $lv['areafenhongmaxnum'];
                            $memberProvinceTmp = Db::name('member')->where('levelid',$lv['id'])->where('areafenhong',0)->limit($areafenhongmaxnum)->field('id,nickname')->select()->toArray();
                            if($memberProvinceTmp) $memberProvinceB = array_merge($memberProvinceB,$memberProvinceTmp);
                        }
                        if($memberProvinceB) {
                            $commissionProvinceB = ($b_province_bl * $fenhongprice * 0.01) / count($memberProvinceB);
                            if ($commissionProvinceB > 0) {
                                foreach ($memberProvinceB as $bk => $pbmember) {
                                    $mid = $pbmember['id'];
                                    if($midareafhArr2[$mid]){
                                        $midareafhArr2[$mid]['totalcommission'] = $midareafhArr2[$mid]['totalcommission'] + $commissionProvinceB;
                                        $midareafhArr2[$mid]['commission'] = $midareafhArr2[$mid]['commission'] + $commissionProvinceB;
                                        $midareafhArr2[$mid]['ogids'][] = $og['id'];
                                    }else{
                                        $midareafhArr2[$mid] = [
                                            'totalcommission' => $commissionProvinceB,
                                            'commission' => $commissionProvinceB,
                                            'money' => 0,
                                            'score' => 0,
                                            'ogids' => [$og['id']],
                                            'module' => $og['module'] ?? 'shop',
                                            'remark'=>'商户省级代理分红',
                                        ];
                                    }
                                }
                            }
                        }
                    }
                    if($b_city && $b_city_bl>0){
                        $memberCityB = Db::name('member')->where('aid',$aid)->where('areafenhong','2')->where('areafenhong_province',$b_province)->where('areafenhong_city',$b_city)->field('id,nickname')->select()->toArray();
                        //跟随会员等级的代理
                        $memberCityLevelist = Db::name('member_level')->where('aid',$aid)->where('areafenhong','2')->select()->toArray();
                        foreach ($memberCityLevelist as $fk=>$lv){
                            $areafenhongmaxnum  = 9999999;
                            if($lv['areafenhongmaxnum']>0) $areafenhongmaxnum = $lv['areafenhongmaxnum'];
                            $memberCityTmp = Db::name('member')->where('levelid',$lv['id'])->where('areafenhong',0)->limit($areafenhongmaxnum)->field('id,nickname')->select()->toArray();
                            if($memberCityTmp) $memberCityB = array_merge($memberCityB,$memberCityTmp);
                        }
                        if($memberCityB){
                            $commissionCityB = ($b_city_bl * $fenhongprice * 0.01) / count($memberCityB);
                            if($commissionCityB>0){
                                foreach ($memberCityB as $ck=>$cbmember){
                                    $mid = $cbmember['id'];
                                    if($midareafhArr2[$mid]){
                                        $midareafhArr2[$mid]['totalcommission'] = $midareafhArr2[$mid]['totalcommission'] + $commissionCityB;
                                        $midareafhArr2[$mid]['commission'] = $midareafhArr2[$mid]['commission'] + $commissionCityB;
                                        $midareafhArr2[$mid]['ogids'][] = $og['id'];
                                    }else{
                                        $midareafhArr2[$mid] = [
                                            'totalcommission' => $commissionCityB,
                                            'commission' => $commissionCityB,
                                            'money' => 0,
                                            'score' => 0,
                                            'ogids' => [$og['id']],
                                            'module' => $og['module'] ?? 'shop',
                                            'remark'=>'商户市级代理分红',
                                        ];
                                    }
                                }
                            }
                        }
                    }
                    if($b_area && $b_area_bl>0){
                        $memberAreaB = Db::name('member')->where('aid',$aid)->where('areafenhong','3')->where('areafenhong_province',$b_province)->where('areafenhong_city',$b_city)->where('areafenhong_area',$b_area)->field('id,nickname')->select()->toArray();

                        //跟随会员等级的代理
                        $memberAreaLevelist = Db::name('member_level')->where('aid',$aid)->where('areafenhong','3')->where('areafenhongbl','>',0)->select()->toArray();
                        foreach ($memberAreaLevelist as $fk=>$lv){
                            $areafenhongmaxnum  = 9999999;
                            if($lv['areafenhongmaxnum']>0) $areafenhongmaxnum = $lv['areafenhongmaxnum'];
                            $memberAreaTmp = Db::name('member')->where('levelid',$lv['id'])->where('areafenhong',0)->limit($areafenhongmaxnum)->field('id,nickname')->select()->toArray();
                            if($memberAreaTmp) $memberAreaB = array_merge($memberAreaB,$memberAreaTmp);
                        }
                        if($memberAreaB){
                            $commissionAreaB = ($b_area_bl * $fenhongprice * 0.01) / count($memberAreaB);
                            if($commissionAreaB>0){
                                foreach ($memberAreaB as $ak=>$abmember){
                                    $mid = $abmember['id'];
                                    if($midareafhArr2[$mid]){
                                        $midareafhArr2[$mid]['totalcommission'] = $midareafhArr2[$mid]['totalcommission'] + $commissionAreaB;
                                        $midareafhArr2[$mid]['commission'] = $midareafhArr2[$mid]['commission'] + $commissionAreaB;
                                        $midareafhArr2[$mid]['ogids'][] = $og['id'];
                                    }else{
                                        $midareafhArr2[$mid] = [
                                            'totalcommission' => $commissionAreaB,
                                            'commission' => $commissionAreaB,
                                            'money' => 0,
                                            'score' => 0,
                                            'ogids' => [$og['id']],
                                            'module' => $og['module'] ?? 'shop',
                                            'remark'=>'商户县区级代理分红',
                                        ];
                                    }
                                }
                            }
                        }
                    }
                }
            }
            if($isyj == 1 && $commissionyj_my > 0){
                $commissionyj += $commissionyj_my;
                $og['commission'] = round($commissionyj_my,2);
                $og['fhname'] = t('区域代理分红',$aid);
                $newoglist[] = $og;
            }
            if($isyj == 0 && $sysset['fhjiesuanhb'] == 0) {
                self::fafang($aid,$midareafhArr,'areafenhong',t('区域代理分红',$aid));
                if(getcustom('business_area_fenhong',$aid) && $midareafhArr2){
                    self::fafang($aid,$midareafhArr2,'areafenhong',t('区域代理商户分红',$aid));
                }
                //根据分红奖团队收益
                if(getcustom('teamfenhong_shouyi',$aid)){
                    self::teamshouyi($aid,$sysset,$midareafhArr,$oglist,$isyj,$yjmid,$commissionpercent,$moneypercent);
                }
                $midareafhArr = [];
            }
        }
        if($isyj == 1){
            //计算团队收益预收益
            if(getcustom('teamfenhong_shouyi',$aid)){
                self::teamshouyi($aid,$sysset,$midareafhArr,$oglist,$isyj,$yjmid,$commissionpercent,$moneypercent);
                if(!empty($res_lvyou['commissionyj']) && $res_lvyou['commissionyj']>0){
                    $res_lvyou_commissionyj = $res_lvyou['commissionyj']??0;
                    $commissionyj = bcadd($commissionyj,$res_lvyou_commissionyj,2);
                }
            }
            return ['commissionyj'=>round($commissionyj,2),'oglist'=>$newoglist];
        }
        if($isyj == 0 && $sysset['fhjiesuanhb'] == 1) {
            self::fafang($aid,$midareafhArr,'areafenhong',t('区域代理分红',$aid));
            if(getcustom('business_area_fenhong',$aid) && $midareafhArr2){
                self::fafang($aid,$midareafhArr2,'areafenhong',t('区域代理商户分红',$aid));
            }
            //根据分红奖团队收益
            if(getcustom('teamfenhong_shouyi',$aid)){
                self::teamshouyi($aid,$sysset,$midareafhArr,$oglist,$isyj,$yjmid,$commissionpercent,$moneypercent);
            }
        }
    }
    //商品团队分红
    public static function product_teamfenhong($aid,$sysset,$oglist,$starttime=0,$endtime=0,$isyj=0,$yjmid=0){
        if($endtime == 0) $endtime = time();
        if(!getcustom('product_teamfenhong',$aid)) return ['commissionyj'=>0,'oglist'=>[]];
        if($isyj == 1 && !$oglist){
            //多商户的商品是否参与分红
            if($sysset['fhjiesuanbusiness'] == 1){
                $bwhere = '1=1';
            }else{
                $bwhere = [['og.bid','=','0']];
            }
            $oglist = Db::name('shop_order_goods')->alias('og')->field('og.*,o.area2,m.nickname,m.headimg')->where('og.aid',$aid)->where('og.isfenhong',0)->where('og.status','in',[1,2,3])->where('og.refund_num',0)->join('shop_order o','o.id=og.orderid')->join('member m','m.id=og.mid')->where($bwhere)->order('og.id desc')->select()->toArray();
        }
        if(!$oglist) return ['commissionyj'=>0,'oglist'=>[]];
        //参与商品团队分红的等级
        $product_fhlevellist = Db::name('member_level')->where('aid',$aid)->where('product_teamfenhonglv','>','0')->where('product_teamfenhong_money','>',0)->where('product_teamfenhong_ids','<>','')->column('id,cid,name,product_teamfenhonglv,product_teamfenhong_ids,product_teamfenhongonly,product_teamfenhong_money,product_teamfenhong_self','id');
        if(!$product_fhlevellist) return ['commissionyj'=>0,'oglist'=>[]];
        
        $defaultCid = Db::name('member_level_category')->where('aid',$aid)->where('isdefault', 1)->value('id');
        if($defaultCid) {
            $defaultLevelIds = Db::name('member_level')->where('aid',$aid)->where('cid', $defaultCid)->column('id');
        } else {
            $defaultLevelIds = Db::name('member_level')->where('aid',$aid)->column('id');
        }

        $isjicha = ($sysset['teamfenhong_differential'] == 1 ? true : false);
        $ogids = [];
        $mid_product_teamfhArr = [];
        
        $newoglist = [];
        $commissionyj = 0;
        foreach($oglist as $og){
            $commissionyj_my = 0;
            if(getcustom('commission2moneypercent',$aid) && $sysset['commission2moneypercent1'] > 0){
                //是否是首单
                $beforeorder = Db::name('shop_order')->where('aid',$aid)->where('mid',$og['mid'])->where('status','in','1,2,3')->where('paytime','<',$og['paytime'])->find();
                if(!$beforeorder){
                    $commissionpercent = 1 - $sysset['commission2moneypercent1'] * 0.01;
                    $moneypercent = $sysset['commission2moneypercent1'] * 0.01;
                }else{
                    $commissionpercent = 1 - $sysset['commission2moneypercent2'] * 0.01;
                    $moneypercent = $sysset['commission2moneypercent2'] * 0.01;
                }
            }else{
                $commissionpercent = 1;
                $moneypercent = 0;
            }
            if($sysset['fhjiesuantype'] == 0){
                $fenhongprice = $og['real_totalprice'];
            }else{
                $fenhongprice = $og['real_totalprice'] - $og['cost_price'] * $og['num'];
            }
            if(getcustom('baikangxie',$aid)){
                $fenhongprice = $og['cost_price'] * $og['num'];
            }
            if($fenhongprice <= 0) continue;
            $ogids[] = $og['id'];
            $allfenhongprice = $allfenhongprice + $fenhongprice;
            $member = Db::name('member')->where('id', $og['mid'])->find();
            $member_extend = Db::name('member_level_record')->field('mid id,levelid')->where('aid', $aid)->where('mid', $og['mid'])->find();
            
            $prolevelids = [];
            foreach ($product_fhlevellist as $item_pl) {
                if(in_array($og['proid'],explode(',',$item_pl['product_teamfenhong_ids']))) {
                    $prolevelids[] = $item_pl['id'];
                }
            }
            $pids = Db::name('member')->where('id',$og['mid'])->value('path');
            if($pids){
                $pids .= ','.$og['mid']; 
            }else{
                $pids = (string)$og['mid'];
            }
            if($pids){
                $parentList = Db::name('member')->where('id','in',$pids)->where('levelid','in',$prolevelids)->order(Db::raw('field(id,'.$pids.')'))->select()->toArray();
                $parentList = array_reverse($parentList);
                $hasfhlevelids = [];
                $last_teamfenhongbl = 0;
                foreach($parentList as $k=>$parent){
                    $leveldata = $product_fhlevellist[$parent['levelid']];
                    if($parent['levelstarttime'] >= $og['createtime']) {
                        $levelup_order_levelid = Db::name('member_levelup_order')->where('aid',$aid)->where('mid', $parent['id'])->where('status', 2)
                            ->where('levelup_time', '<', $og['createtime'])->whereIn('levelid',$defaultLevelIds)->order('levelup_time', 'desc')->value('levelid');
                        if($levelup_order_levelid) {
                            $parent['levelid'] = $levelup_order_levelid;
                            $leveldata = $product_fhlevellist[$parent['levelid']];
                        }
                    }
                    if(!$leveldata || $k>=$leveldata['product_teamfenhonglv']) continue;
                    if($parent['id'] == $og['mid'] && $leveldata['product_teamfenhong_self'] != 1) continue;
                    if($leveldata['product_teamfenhongonly'] == 1 && in_array($parent['levelid'],$hasfhlevelids)) continue; //该等级设置了只给最近的上级分红
                    $hasfhlevelids[] = $parent['levelid'];
                    //每单奖励
                    if($leveldata['product_teamfenhong_money'] > 0) {
                        if($leveldata['product_teamfenhonglv'] == 1 && in_array($parent['levelid'],$hasfhlevelids)) continue; //该等级设置了只给最近的上级分红
                        $hasfhlevelids[] = $parent['levelid'];
                        $commission = $leveldata['product_teamfenhong_money'] * $og['num'];
                        if($isyj == 1 && $yjmid == $parent['id']){
                            $commissionyj_my += $commission;
                        }

                        if($commissionpercent != 1){
                            $fenhongcommission = round($commission*$commissionpercent,2);
                            $fenhongmoney = round($commission*$moneypercent,2);
                        }else{
                            $fenhongcommission = $commission;
                            $fenhongmoney = 0;
                        }

                        if($mid_product_teamfhArr[$parent['id']]){
                            $mid_product_teamfhArr[$parent['id']]['totalcommission'] = $mid_product_teamfhArr[$parent['id']]['totalcommission'] + $commission;
                            $mid_product_teamfhArr[$parent['id']]['commission'] = $mid_product_teamfhArr[$parent['id']]['commission'] + $fenhongcommission;
                            $mid_product_teamfhArr[$parent['id']]['money'] = $mid_product_teamfhArr[$parent['id']]['money'] + $fenhongmoney;
                            $mid_product_teamfhArr[$parent['id']]['ogids'][] = $og['id'];
                        }else{
                            $mid_product_teamfhArr[$parent['id']] = ['totalcommission'=>$commission,'commission'=>$fenhongcommission,'money'=>$fenhongmoney,'ogids'=>[$og['id']],'module'=>$og['module'] ?? 'shop'];
                        }
                        if(getcustom('commission_orderrefund_deduct',$aid) && $isyj == 0){
                            self::fhrecord($aid,$parent['id'],$fenhongcommission,0,$og['id'],$og['module'] ?? 'shop','product_teamfenhong',t('商品团队分红',$aid));
                        }
                    }
                }
                //其他分组等级
                if(getcustom('plug_sanyang',$aid)) {
                    $catList = Db::name('member_level_category')->where('aid', $aid)->where('isdefault', 0)->select()->toArray();
                    foreach ($catList as $cat) {
                        $parentList = Db::name('member_level_record')->field('mid id,levelid')->where('aid', $aid)->where('cid', $cat['id'])->whereIn('mid', $pids)->where('levelid','in',$prolevelids)->select()->toArray();
                        $parentList = array_reverse($parentList);
                        $hasfhlevelids = [];
                        $last_teamfenhongbl = 0;
                        foreach($parentList as $k=>$parent){
                            $leveldata = $product_fhlevellist[$parent['levelid']];
                            if($parent['levelstarttime'] >= $og['createtime']) {
                                $levelup_order_levelid = Db::name('member_levelup_order')->where('aid',$aid)->where('mid', $parent['id'])->where('status', 2)
                                    ->where('levelup_time', '<', $og['createtime'])->whereNotIn('levelid',$defaultLevelIds)->order('levelup_time', 'desc')->value('levelid');
                                if($levelup_order_levelid) {
                                    $parent['levelid'] = $levelup_order_levelid;
                                    $leveldata = $product_fhlevellist[$parent['levelid']];
                                }
                            }
                            if(!$leveldata || $k>=$leveldata['product_teamfenhonglv']) continue;
                            if($parent['id'] == $og['mid'] && $leveldata['product_teamfenhong_self'] != 1) continue;
                            //每单奖励
                            if($leveldata['product_teamfenhong_money'] > 0) {
                                if($leveldata['product_teamfenhonglv'] == 1 && in_array($parent['levelid'],$hasfhlevelids)) continue; //该等级设置了只给最近的上级分红
                                $hasfhlevelids[] = $parent['levelid'];
                                $commission = $leveldata['product_teamfenhong_money'] * $og['num'];
                                if($isyj == 1 && $yjmid == $parent['id']){
                                    $commissionyj_my += $commission;
                                }

                                if($commissionpercent != 1){
                                    $fenhongcommission = round($commission*$commissionpercent,2);
                                    $fenhongmoney = round($commission*$moneypercent,2);
                                }else{
                                    $fenhongcommission = $commission;
                                    $fenhongmoney = 0;
                                }

                                if($mid_product_teamfhArr[$parent['id']]){
                                    $mid_product_teamfhArr[$parent['id']]['totalcommission'] = $mid_product_teamfhArr[$parent['id']]['totalcommission'] + $commission;
                                    $mid_product_teamfhArr[$parent['id']]['commission'] = $mid_product_teamfhArr[$parent['id']]['commission'] + $fenhongcommission;
                                    $mid_product_teamfhArr[$parent['id']]['money'] = $mid_product_teamfhArr[$parent['id']]['money'] + $fenhongmoney;
                                    $mid_product_teamfhArr[$parent['id']]['ogids'][] = $og['id'];
                                }else{
                                    $mid_product_teamfhArr[$parent['id']] = ['totalcommission'=>$commission,'commission'=>$fenhongcommission,'money'=>$fenhongmoney,'ogids'=>[$og['id']],'module'=>$og['module'] ?? 'shop'];
                                }
                                if(getcustom('commission_orderrefund_deduct',$aid) && $isyj == 0){
                                    self::fhrecord($aid,$parent['id'],$fenhongcommission,0,$og['id'],$og['module'] ?? 'shop','product_teamfenhong',t('商品团队分红',$aid));
                                }
                            }
                        }
                    }
                }
            }
            if($isyj == 1 && $commissionyj_my > 0){
                $commissionyj += $commissionyj_my;
                $og['commission'] = round($commissionyj_my,2);
                $og['fhname'] = t('商品团队分红',$aid);
                $newoglist[] = $og;
            }
            if($isyj == 0 && $sysset['fhjiesuanhb'] == 0) {
                self::fafang($aid,$mid_product_teamfhArr,'product_teamfenhong',t('商品团队分红',$aid));
                //根据分红奖团队收益
                if(getcustom('teamfenhong_shouyi',$aid)){
                    self::teamshouyi($aid,$sysset,$mid_product_teamfhArr,$oglist,$isyj,$yjmid,$commissionpercent,$moneypercent);
                }
                $mid_product_teamfhArr = [];
            }
        }
        if($isyj == 1){
            //计算团队收益预收益
            if(getcustom('teamfenhong_shouyi',$aid)){
                self::teamshouyi($aid,$sysset,$mid_product_teamfhArr,$oglist,$isyj,$yjmid,$commissionpercent,$moneypercent);
                if(!empty($res_lvyou['commissionyj']) && $res_lvyou['commissionyj']>0){
                    $res_lvyou_commissionyj = $res_lvyou['commissionyj']??0;
                    $commissionyj = bcadd($commissionyj,$res_lvyou_commissionyj,2);
                }
            }
            return ['commissionyj'=>round($commissionyj,2),'oglist'=>$newoglist];
        }
        if($isyj == 0 && $sysset['fhjiesuanhb'] == 1) {
            self::fafang($aid,$mid_product_teamfhArr,'product_teamfenhong',t('商品团队分红',$aid));
            //根据分红奖团队收益
            if(getcustom('teamfenhong_shouyi',$aid)){
                self::teamshouyi($aid,$sysset,$mid_product_teamfhArr,$oglist,$isyj,$yjmid,$commissionpercent,$moneypercent);
            }
        }
    }
    //等级团队分红
    public static function level_teamfenhong($aid,$sysset,$oglist,$starttime=0,$endtime=0,$isyj=0,$yjmid=0){
        if($endtime == 0) $endtime = time();
        if(getcustom('level_teamfenhong',$aid)){
            //查询此账号是否有等级分红权限
            $uinfo = db('admin_user')->where('aid',$aid)->where('bid',0)->where('isadmin','>=',1)->field('id,auth_type,auth_data')->find();
            if(!$uinfo){ return ['commissionyj'=>0,'oglist'=>[]]; }

            if($uinfo['auth_type'] !=1){
                if(empty($uinfo['auth_data'])){ return ['commissionyj'=>0,'oglist'=>[]]; }

                $auth_data =  json_decode($uinfo['auth_data'],true);
                if(!in_array('level_teamfenhong,level_teamfenhong',$auth_data)){ return ['commissionyj'=>0,'oglist'=>[]]; }
            }

            if($isyj == 1 && !$oglist){
                //多商户的商品是否参与分红
                if($sysset['fhjiesuanbusiness'] == 1){
                    $bwhere = '1=1';
                }else{
                    $bwhere = [['og.bid','=','0']];
                }
                $oglist = Db::name('shop_order_goods')->alias('og')->field('og.*,o.area2,m.nickname,m.headimg')->where('og.aid',$aid)->where('og.isfenhong',0)->where('og.status','in',[1,2,3])->where('og.refund_num',0)->join('shop_order o','o.id=og.orderid')->join('member m','m.id=og.mid')->where($bwhere)->order('og.id desc')->select()->toArray();
            }
            if(!$oglist) return ['commissionyj'=>0,'oglist'=>[]];

            //参与等级团队分红的等级
            $level_teamfhlevellist = Db::name('member_level')->where('aid',$aid)->where('level_teamfenhong_ids','<>','')->where('level_teamfenhonglv','>','0')->where(function ($query) {
                $query->where('level_teamfenhongbl','>',0)->whereOr('level_teamfenhong_money','>',0);
            })->column('id,cid,name,level_teamfenhong_ids,level_teamfenhonglv,level_teamfenhongbl,level_teamfenhongonly,level_teamfenhong_money,level_teamfenhongbl_type,level_surpass,level_jicha,sort','id');
            //if(!$level_teamfhlevellist) return ['commissionyj'=>0,'oglist'=>[]];

            $defaultCid = Db::name('member_level_category')->where('aid',$aid)->where('isdefault', 1)->value('id');
            if($defaultCid) {
                $defaultLevelIds = Db::name('member_level')->where('aid',$aid)->where('cid', $defaultCid)->column('id');
            } else {
                $defaultLevelIds = Db::name('member_level')->where('aid',$aid)->column('id');
            }

            $isjicha = ($sysset['teamfenhong_differential'] == 1 ? true : false);
            $ogids = [];
            $midlevel_teamfhArr = [];
            $level_teamfenhong_orderids = [];
            $level_teamfenhong_orderids_cat = [];

            $newoglist = [];
            $commissionyj = 0;
            foreach($oglist as $og){

                //判断是否是商城商品是否开启了等级分红配置
                if(!$og['module'] || empty($og['module']) || $og['module'] == 'shop'){
                    $product = Db::name('shop_product')->where('id',$og['proid'])->field('id,level_teamfenhongset,levelteamfenhongs')->find();
                    if($product){
                        //关闭了等级分红，则不走
                        if($product['level_teamfenhongset'] == -1){
                            continue;
                        //单独设置
                        }else if($product['level_teamfenhongset'] == 1){
                            if(!$product['levelteamfenhongs']){
                                continue;
                            }
                            $levelteamfenhongs = json_decode($product['levelteamfenhongs'],true);
                            if(!$levelteamfenhongs){
                                continue;
                            }

                            $level_teamfhlevellistnew = [];
                            foreach($levelteamfenhongs as $lk=>$lv){
                                //查询等级是否存在
                                $teamlevel = Db::name('member_level')->where('id',$lv['id'])->field('id,cid,name,sort')->find();
                                if(!$teamlevel) continue;
                                //如果团队等级ID为空 或者 分红级数小等于0 或者 分红提成比例或分红固定金额小于等于0且每单分红金额 则不走
                                if($lv['level_teamfenhong_ids'] == '' || $lv['level_teamfenhonglv']<=0 || ($lv['level_teamfenhongbl']<=0 && $lv['level_teamfenhong_money']<=0)){
                                    continue;
                                }
                                $lv['name'] = $teamlevel['name'];
                                $lv['sort'] = $teamlevel['sort'];
                                $level_teamfhlevellistnew[$lk]=$lv;
                            }
                        }else{
                            if(!$level_teamfhlevellist) continue;
                            $level_teamfhlevellistnew = $level_teamfhlevellist;
                        }
                    }else{
                        if(!$level_teamfhlevellist) continue;
                        $level_teamfhlevellistnew = $level_teamfhlevellist;
                    }
                }else{
                    if(!$level_teamfhlevellist) continue;
                    $level_teamfhlevellistnew = $level_teamfhlevellist;
                }

                if(getcustom('commission2moneypercent',$aid) && $sysset['commission2moneypercent1'] > 0){
                    //是否是首单
                    $beforeorder = Db::name('shop_order')->where('aid',$aid)->where('mid',$og['mid'])->where('status','in','1,2,3')->where('paytime','<',$og['paytime'])->find();
                    if(!$beforeorder){
                        $commissionpercent = 1 - $sysset['commission2moneypercent1'] * 0.01;
                        $moneypercent = $sysset['commission2moneypercent1'] * 0.01;
                    }else{
                        $commissionpercent = 1 - $sysset['commission2moneypercent2'] * 0.01;
                        $moneypercent = $sysset['commission2moneypercent2'] * 0.01;
                    }
                }else{
                    $commissionpercent = 1;
                    $moneypercent = 0;
                }

                if($sysset['fhjiesuantype'] == 0){
                    $fenhongprice = $og['real_totalprice'];
                }else{
                    $fenhongprice = $og['real_totalprice'] - $og['cost_price'] * $og['num'];
                }
                if(getcustom('baikangxie',$aid)){
                    $fenhongprice = $og['cost_price'] * $og['num'];
                }
                if($fenhongprice <= 0) continue;
                $ogids[] = $og['id'];
                $allfenhongprice = $allfenhongprice + $fenhongprice;
                $member = Db::name('member')->where('id', $og['mid'])->find();
                $member_extend = Db::name('member_level_record')->field('mid id,levelid')->where('aid', $aid)->where('mid', $og['mid'])->find();
                
                $pids = Db::name('member')->where('id',$og['mid'])->value('path');
                if($pids){

                    //筛选上级条件:上级等级id集合
                    $plevelids = [];
                    foreach($level_teamfhlevellistnew as $level) {
                        array_push($plevelids,$level['id']);
                    }

                    //格式化pids数组
                    $pidsarr = explode(',',$pids);
                    //反转数组
                    $pidsarr = array_reverse($pidsarr);

                    //筛选上级
                    $parentList = Db::name('member')->where('id','in',$pids)->whereIn('levelid',$plevelids)->order(Db::raw('field(id,'.$pids.')'))->select()->toArray();
                    if(!count($parentList)) continue;
                    $parentList = array_reverse($parentList);

                    //标记上级的是此会员的多少层级，从0开始
                    foreach($pidsarr as $pk=>$pv){
                        foreach($parentList as &$pv2){
                            if($pv2['id'] == $pv){
                                $pv2['teamnum'] = $pk;
                            }
                        }
                        unset($pv2);
                    }

                    $hasfhlevelids = [];
                    $last_teamcommission   = 0;
                    foreach($parentList as $k=>$parent){

                        //获取上级等级信息
                        $leveldata = $level_teamfhlevellistnew[$parent['levelid']];
                        //判断升级最后时间
                        if($parent['levelstarttime'] >= $og['createtime']) {
                            $levelup_order_levelid = Db::name('member_levelup_order')->where('mid', $parent['id'])->where('status', 2)
                                ->where('levelup_time', '<', $og['createtime'])->where('aid',$aid)->order('levelup_time', 'desc')->value('levelid');
                            if($levelup_order_levelid) {
                                $parent['levelid'] = $levelup_order_levelid;
                                $leveldata = $level_teamfhlevellistnew[$parent['levelid']];
                            }
                        }

                        //等级不存在 或者团队级数超过他级数
                        if(!$leveldata || $parent['teamnum']>=$leveldata['level_teamfenhonglv']) continue;

                        //上级等级设置的团队等级ID
                        $level_teamfenhong_ids = $leveldata['level_teamfenhong_ids']?explode(',',$leveldata['level_teamfenhong_ids']):'';
                        if(!in_array($member['levelid'], $level_teamfenhong_ids)) continue;

                        //查询是否开启需要直属下级超越，开启则需要查询直属下级级别是否超越了此上级
                        if($leveldata['level_surpass'] == 1){
                            //如果上级团队层级是直属上级，则查询直接查本会员的等级
                            if($parent['teamnum']==0){
                                $mlevel = 0+Db::name('member_level')->where('id',$member['levelid'])->where('sort','>',$leveldata['sort'])->count('id');
                                if(!$mlevel) continue;
                            //其他层次上级，查询此层次上级的直属下级等级
                            }else{
                                //查询上级所处位置
                                $pos = array_search($parent['id'],$pidsarr);
                                if($pos>0){
                                    $lowpid = $pidsarr[$pos-1];
                                    //查询直属下级会员信息
                                    $lowparent = Db::name('member')->where('id',$lowpid)->field('levelid')->find();
                                    if(!$lowparent || empty($lowparent)) continue;

                                    //是否符合上级设置团队等级ID的范围
                                    //if(!in_array($lowparent['levelid'], $level_teamfenhong_ids)) continue;

                                    $lowlevel = 0+Db::name('member_level')->where('id',$lowparent['levelid'])->where('sort','>',$leveldata['sort'])->count('id');
                                    if(!$lowlevel) continue;
                                }else{
                                    continue;
                                }
                            }
                        }

                        //每单奖励
                        if($leveldata['level_teamfenhong_money'] > 0 && !in_array($og['orderid'], $level_teamfenhong_orderids[$parent['id']])) {
                            //该等级设置了只给最近的上级分红
                            if($leveldata['level_teamfenhongonly'] == 1 && in_array($parent['levelid'],$hasfhlevelids)) continue; 
                            $hasfhlevelids[] = $parent['levelid'];

                            $commission = $leveldata['level_teamfenhong_money'];
                            if($isyj == 1 && $yjmid == $parent['id']){
                                $commissionyj_my += $commission;
                            }
                            if($commissionpercent != 1){
                                $fenhongcommission = round($commission*$commissionpercent,2);
                                $fenhongmoney = round($commission*$moneypercent,2);
                            }else{
                                $fenhongcommission = $commission;
                                $fenhongmoney = 0;
                            }

                            if($midlevel_teamfhArr[$parent['id']]){
                                $midlevel_teamfhArr[$parent['id']]['totalcommission'] = $midlevel_teamfhArr[$parent['id']]['totalcommission'] + $commission;
                                $midlevel_teamfhArr[$parent['id']]['commission'] = $midlevel_teamfhArr[$parent['id']]['commission'] + $fenhongcommission;
                                $midlevel_teamfhArr[$parent['id']]['money'] = $midlevel_teamfhArr[$parent['id']]['money'] + $fenhongmoney;
                                $midlevel_teamfhArr[$parent['id']]['ogids'][] = $og['id'];
                            }else{
                                $midlevel_teamfhArr[$parent['id']] = ['totalcommission'=>$commission,'commission'=>$fenhongcommission,'money'=>$fenhongmoney,'ogids'=>[$og['id']],'module'=>$og['module'] ?? 'shop'];
                            }
                            if(getcustom('commission_orderrefund_deduct',$aid) && $isyj == 0){
                                self::fhrecord($aid,$parent['id'],$fenhongcommission,0,$og['id'],$og['module'] ?? 'shop','level_teamfenhong',t('等级团队分红',$aid));
                            }
                            $level_teamfenhong_orderids[$parent['id']][] = $og['orderid'];
                        }

                        //分红比例
                        if($leveldata['level_teamfenhongbl'] > 0) {

                            //该等级设置了只给最近的上级分红
                            if($leveldata['level_teamfenhongonly'] == 1 && in_array($parent['levelid'],$hasfhlevelids)) continue;
                            $hasfhlevelids[] = $parent['levelid'];

                            //如果是分红类型是固定分红
                            if($leveldata['level_teamfenhongbl_type'] && $leveldata['level_teamfenhongbl_type'] == 1){
                                //查询是等级团队分红级差单独设置
                                if(!$leveldata['level_jicha']){
                                    if($isjicha){
                                        $commission   = $leveldata['level_teamfenhongbl'] - $last_teamcommission;
                                    }else{
                                        $commission   = $leveldata['level_teamfenhongbl'];
                                    }
                                }else{
                                    if($leveldata['level_jicha'] == 1){
                                        $commission   = $leveldata['level_teamfenhongbl'] - $last_teamcommission;
                                    }else{
                                        $commission   = $leveldata['level_teamfenhongbl'];
                                    }
                                }
                                if($commission <=0) continue;
                                $last_teamcommission = $last_teamcommission + $commission;

                            //如果是分红类型是比例
                            }else{
                                //查询是等级团队分红级差单独设置
                                if(!$leveldata['level_jicha']){
                                    if($isjicha){
                                        $commission = $leveldata['level_teamfenhongbl']* $fenhongprice * 0.01 - $last_teamcommission;
                                    }else{
                                        $commission = $leveldata['level_teamfenhongbl']* $fenhongprice * 0.01;
                                    }
                                }else{
                                    if($leveldata['level_jicha'] == 1){
                                        $commission = $leveldata['level_teamfenhongbl']* $fenhongprice * 0.01 - $last_teamcommission;
                                    }else{
                                        $commission = $leveldata['level_teamfenhongbl']* $fenhongprice * 0.01;
                                    }
                                }
                                if($commission <=0) continue;
                                $last_teamcommission = $last_teamcommission + $commission;
                            }
                            
                            if($isyj == 1 && $yjmid == $parent['id']){
                                $commissionyj_my += $commission;
                            }

                            if($commissionpercent != 1){
                                $fenhongcommission = round($commission*$commissionpercent,2);
                                $fenhongmoney = round($commission*$moneypercent,2);
                            }else{
                                $fenhongcommission = $commission;
                                $fenhongmoney = 0;
                            }

                            if($midlevel_teamfhArr[$parent['id']]){
                                $midlevel_teamfhArr[$parent['id']]['totalcommission'] = $midlevel_teamfhArr[$parent['id']]['totalcommission'] + $commission;
                                $midlevel_teamfhArr[$parent['id']]['commission'] = $midlevel_teamfhArr[$parent['id']]['commission'] + $fenhongcommission;
                                $midlevel_teamfhArr[$parent['id']]['money'] = $midlevel_teamfhArr[$parent['id']]['money'] + $fenhongmoney;
                                $midlevel_teamfhArr[$parent['id']]['ogids'][] = $og['id'];
                            }else{
                                $midlevel_teamfhArr[$parent['id']] = ['totalcommission'=>$commission,'commission'=>$fenhongcommission,'money'=>$fenhongmoney,'ogids'=>[$og['id']],'module'=>$og['module'] ?? 'shop'];
                            }
                            if(getcustom('commission_orderrefund_deduct',$aid) && $isyj == 0){
                                self::fhrecord($aid,$parent['id'],$fenhongcommission,0,$og['id'],$og['module'] ?? 'shop','level_teamfenhong',t('等级团队分红',$aid));
                            }
                        }
                    }

                    //其他分组等级
                    if(getcustom('plug_sanyang')) {
                        $catList = [];//暂停使用
                        //$catList = Db::name('member_level_category')->where('aid', $aid)->where('isdefault', 0)->select()->toArray();
                        if($catList){
                            foreach ($catList as $cat) {
                                $parentList = Db::name('member_level_record')->field('mid id,levelid')->where('aid', $aid)->whereIn('levelid',$plevelids)->where('cid', $cat['id'])->whereIn('mid', $pids)->select()->toArray();
                                $parentList = array_reverse($parentList);
                                $hasfhlevelids = [];
                                $last_teamcommission   = 0;
                                foreach($parentList as $k=>$parent){

                                    $leveldata = $level_teamfhlevellistnew[$parent['levelid']];
                                    if($parent['levelstarttime'] >= $og['createtime']) {
                                        $levelup_order_levelid = Db::name('member_levelup_order')->where('aid',$aid)->where('mid', $parent['id'])->where('status', 2)
                                            ->where('levelup_time', '<', $og['createtime'])->whereNotIn('levelid',$defaultLevelIds)->order('levelup_time', 'desc')->value('levelid');
                                        if($levelup_order_levelid) {
                                            $parent['levelid'] = $levelup_order_levelid;
                                            $leveldata = $level_teamfhlevellistnew[$parent['levelid']];
                                        }
                                    }

                                    //等级不存在 或者团队级数超过他级数
                                    if(!$leveldata /*|| $k>=$leveldata['level_teamfenhonglv']*/) continue;
                                    //最近的上级分不分看当前会员是达到级别
                                    if(!in_array($member_extend['levelid'], explode(',',(string)$leveldata['level_teamfenhong_ids']))) continue;

                                    //每单奖励
                                    if($leveldata['level_teamfenhong_money'] > 0 && !in_array($og['orderid'], $level_teamfenhong_orderids_cat[$parent['id']])) {
                                        if($leveldata['level_teamfenhongonly'] == 1 && in_array($parent['levelid'],$hasfhlevelids)) continue; //该等级设置了只给最近的上级分红
                                        $hasfhlevelids[] = $parent['levelid'];
                                        $commission = $leveldata['level_teamfenhong_money'];
                                        if($isyj == 1 && $yjmid == $parent['id']){
                                            $commissionyj_my += $commission;
                                        }

                                        if($commissionpercent != 1){
                                            $fenhongcommission = round($commission*$commissionpercent,2);
                                            $fenhongmoney = round($commission*$moneypercent,2);
                                        }else{
                                            $fenhongcommission = $commission;
                                            $fenhongmoney = 0;
                                        }

                                        if($midlevel_teamfhArr[$parent['id']]){
                                            $midlevel_teamfhArr[$parent['id']]['totalcommission'] = $midlevel_teamfhArr[$parent['id']]['totalcommission'] + $commission;
                                            $midlevel_teamfhArr[$parent['id']]['commission'] = $midlevel_teamfhArr[$parent['id']]['commission'] + $fenhongcommission;
                                            $midlevel_teamfhArr[$parent['id']]['money'] = $midlevel_teamfhArr[$parent['id']]['money'] + $fenhongmoney;
                                            $midlevel_teamfhArr[$parent['id']]['ogids'][] = $og['id'];
                                        }else{
                                            $midlevel_teamfhArr[$parent['id']] = ['totalcommission'=>$commission,'commission'=>$fenhongcommission,'money'=>$fenhongmoney,'ogids'=>[$og['id']],'module'=>$og['module'] ?? 'shop'];
                                        }
                                        if(getcustom('commission_orderrefund_deduct',$aid) && $isyj == 0){
                                            self::fhrecord($aid,$parent['id'],$fenhongcommission,0,$og['id'],$og['module'] ?? 'shop','level_teamfenhong',t('等级团队分红',$aid));
                                        }
                                        $level_teamfenhong_orderids_cat[$parent['id']][] = $og['orderid'];
                                    }

                                    //分红比例
                                    if($leveldata['level_teamfenhongbl'] > 0) {

                                        if($leveldata['level_teamfenhongonly'] == 1 && in_array($parent['levelid'],$hasfhlevelids)) continue; //该等级设置了只给最近的上级分红
                                        $hasfhlevelids[] = $parent['levelid'];

                                        //如果是分红类型是固定分红
                                        if($leveldata['level_teamfenhongbl_type'] && $leveldata['level_teamfenhongbl_type'] == 1){
                                            //查询是等级团队分红级差单独设置
                                            if(!$leveldata['level_jicha']){
                                                if($isjicha){
                                                    $commission   = $leveldata['level_teamfenhongbl'] - $last_teamcommission;
                                                }else{
                                                    $commission   = $leveldata['level_teamfenhongbl'];
                                                }
                                            }else{
                                                if($leveldata['level_jicha'] == 1){
                                                    $commission   = $leveldata['level_teamfenhongbl'] - $last_teamcommission;
                                                }else{
                                                    $commission   = $leveldata['level_teamfenhongbl'];
                                                }
                                            }
                                            if($commission <=0) continue;
                                            $last_teamcommission = $last_teamcommission + $commission;
                                        }else{
                                            //查询是等级团队分红级差单独设置
                                            if(!$leveldata['level_jicha']){
                                                if($isjicha){
                                                    $commission = $leveldata['level_teamfenhongbl']* $fenhongprice * 0.01 - $last_teamcommission;
                                                }else{
                                                    $commission = $leveldata['level_teamfenhongbl']* $fenhongprice * 0.01;
                                                }
                                            }else{
                                                if($leveldata['level_jicha'] == 1){
                                                    $commission = $leveldata['level_teamfenhongbl']* $fenhongprice * 0.01 - $last_teamcommission;
                                                }else{
                                                    $commission = $leveldata['level_teamfenhongbl']* $fenhongprice * 0.01;
                                                }
                                            }
                                            if($commission <=0) continue;
                                            $last_teamcommission = $last_teamcommission + $commission;
                                        }

                                        if($isyj == 1 && $yjmid == $parent['id']){
                                            $commissionyj_my += $commission;
                                        }

                                        if($commissionpercent != 1){
                                            $fenhongcommission = round($commission*$commissionpercent,2);
                                            $fenhongmoney = round($commission*$moneypercent,2);
                                        }else{
                                            $fenhongcommission = $commission;
                                            $fenhongmoney = 0;
                                        }

                                        if($midlevel_teamfhArr[$parent['id']]){
                                            $midlevel_teamfhArr[$parent['id']]['totalcommission'] = $midlevel_teamfhArr[$parent['id']]['totalcommission'] + $commission;
                                            $midlevel_teamfhArr[$parent['id']]['commission'] = $midlevel_teamfhArr[$parent['id']]['commission'] + $fenhongcommission;
                                            $midlevel_teamfhArr[$parent['id']]['money'] = $midlevel_teamfhArr[$parent['id']]['money'] + $fenhongmoney;
                                            $midlevel_teamfhArr[$parent['id']]['ogids'][] = $og['id'];
                                        }else{
                                            $midlevel_teamfhArr[$parent['id']] = ['totalcommission'=>$commission,'commission'=>$fenhongcommission,'money'=>$fenhongmoney,'ogids'=>[$og['id']],'module'=>$og['module'] ?? 'shop'];
                                        }
                                        if(getcustom('commission_orderrefund_deduct',$aid) && $isyj == 0){
                                            self::fhrecord($aid,$parent['id'],$fenhongcommission,0,$og['id'],$og['module'] ?? 'shop','level_teamfenhong',t('等级团队分红',$aid));
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                if($isyj == 1 && $commissionyj_my > 0){
                    $commissionyj += $commissionyj_my;
                    $og['commission'] = round($commissionyj_my,2);
                    $og['fhname'] = t('等级团队分红',$aid);
                    $newoglist[] = $og;
                }
                if($isyj == 0 && $sysset['fhjiesuanhb'] == 0) {
                    self::fafang($aid,$midlevel_teamfhArr,'level_teamfenhong',t('等级团队分红',$aid));
                    //根据分红奖团队收益
                    if(getcustom('teamfenhong_shouyi',$aid)){
                        self::teamshouyi($aid,$sysset,$midlevel_teamfhArr,$oglist,$isyj,$yjmid,$commissionpercent,$moneypercent);
                    }
                    $midlevel_teamfhArr = [];
                }
            }
            if($isyj == 1){
                //计算团队收益预收益
                if(getcustom('teamfenhong_shouyi',$aid)){
                    self::teamshouyi($aid,$sysset,$midlevel_teamfhArr,$oglist,$isyj,$yjmid,$commissionpercent,$moneypercent);
                    if(!empty($res_lvyou['commissionyj']) && $res_lvyou['commissionyj']>0){
                        $res_lvyou_commissionyj = $res_lvyou['commissionyj']??0;
                        $commissionyj = bcadd($commissionyj,$res_lvyou_commissionyj,2);
                    }
                }
                return ['commissionyj'=>round($commissionyj,2),'oglist'=>$newoglist];
            }
            if($isyj == 0 && $sysset['fhjiesuanhb'] == 1) {
                self::fafang($aid,$midlevel_teamfhArr,'level_teamfenhong',t('等级团队分红',$aid));
                //根据分红奖团队收益
                if(getcustom('teamfenhong_shouyi',$aid)){
                    self::teamshouyi($aid,$sysset,$midlevel_teamfhArr,$oglist,$isyj,$yjmid,$commissionpercent,$moneypercent);
                }
            }
        }else{
            return ['commissionyj'=>0,'oglist'=>[]];
        }
    }

    public static function gongxian_fenhong($aid,$sysset,$oglist,$starttime=0,$endtime=0,$isyj=0,$yjmid=0)
    {
        $member_gongxian_custom = getcustom('member_gongxian',$aid);
        if($member_gongxian_custom){
            $admin = Db::name('admin')->where('id',$aid)->find();
            if($admin['member_gongxian_status'] != 1)
                return ['commissionyj'=>0,'oglist'=>[]];
            if(empty($sysset['gongxian_percent']) || $sysset['gongxian_percent'] <= 0){
                return ['commissionyj'=>0,'oglist'=>[]];
            }

            //开启的多商户
            $bids = Db::name('business')->where('aid',$aid)->where('fenhong_member_gongxian',1)->column('id');
            $bids = array_merge([0],$bids);
            if(getcustom('maidan_fenhong_new',$aid)){
                $bids_maidan = Db::name('business')->where('maidan_gongxian',1)->column('id');
                $bids_maidan = array_merge([0],$bids_maidan);
            }
            if($endtime == 0) $endtime = time();
            if($isyj == 1 && !$oglist){
                //多商户的商品是否参与分红
                if($sysset['fhjiesuanbusiness'] == 1){
                    $bwhere = [['og.bid','in',$bids]];
                }else{
                    $bwhere = [['og.bid','=','0']];
                }
                $oglist = Db::name('shop_order_goods')->alias('og')->field('og.*,o.area2,m.nickname,m.headimg')->where('og.aid',$aid)->where('og.isfenhong',0)->where('og.status','in',[1,2,3])->where('og.refund_num',0)->join('shop_order o','o.id=og.orderid')->join('member m','m.id=og.mid')->where($bwhere)->order('og.id desc')->select()->toArray();
                if(getcustom('maidan_fenhong_new',$aid) && $sysset['maidanfenhong']){
                    //买单分红
                    $bwhere_maidan = [['og.bid', 'in', $bids_maidan]];
                    $maidan_orderlist = Db::name('maidan_order')
                        ->alias('og')
                        ->join('member m','m.id=og.mid')
                        ->where('og.aid',$aid)
                        ->where('og.isfenhong',0)
                        ->where('og.status',1)
                        ->where($bwhere_maidan)
                        ->field('og.*,m.nickname,m.headimg')
                        ->order('og.id desc')
                        ->select()
                        ->toArray();
                    if($maidan_orderlist){
                        foreach($maidan_orderlist as $mdk=>$mdv){
                            $mdv['name']             = $mdv['title'];
                            $mdv['real_totalprice']  = $mdv['paymoney'];
                            //买单分红结算方式
                            if($sysset['maidanfenhong_type'] == 1){
                                //按利润结算时直接把销售额改成利润
                                $mdv['real_totalprice'] = $mdv['paymoney'] -  $mdv['cost_price'];
                            }
                            $mdv['cost_price']       = 0;
                            $mdv['num']              = 1;
                            $mdv['module']           = 'maidan';
                            $oglist[] = $mdv;
                        }
                    }
                }
            }
            if(!$oglist) return ['commissionyj'=>0,'oglist'=>[]];
            //参与分红的会员
            $gongxianTotal = Db::name('member')->where('aid',$aid)->where('gongxian','>',0)->sum('gongxian');
            if(!$gongxianTotal) return ['commissionyj'=>0,'oglist'=>[]];
//            $fhlevellist = Db::name('member_level')->where('aid',$aid)->where('fenhong','>','0')->order('sort desc,id desc')->column('*','id');
//            if(!$fhlevellist) return ['commissionyj'=>0,'oglist'=>[]];

            $ogids = [];
            $midfhArr = [];
            $newoglist = [];
            $commissionyj = 0;
            $allfenhongprice = 0;
            foreach($oglist as $og){
                if(getcustom('maidan_fenhong_new',$aid) && $og['module']=='maidan'){
                    if($og['bid'] > 0 && !in_array($og['bid'],$bids_maidan)){
                        continue;
                    }
                }
                if($og['bid'] > 0 && !in_array($og['bid'],$bids) && $og['module']!='maidan'){
                    continue;
                }
                $ogids[] = $og['id'];

                $commissionpercent = 1;
                $moneypercent = 0;
                if($sysset['fhjiesuantype'] == 0){
                    $fenhongprice = $og['real_totalprice'];
                }else{
                    $fenhongprice = $og['real_totalprice'] - $og['cost_price'] * $og['num'];
                }
                if($fenhongprice <= 0) continue;
                $ogids[] = $og['id'];
                $allfenhongprice = $allfenhongprice + $fenhongprice;

                if($gongxianTotal){
                    $lastmidlist = [];
                    $where = [];
                    $where[] = ['aid', '=', $aid];
                    $where[] = ['gongxian', '>', 0]; //判断贡献值

                    $midlist = Db::name('member')->where($where)->column('id,gongxian,total_fenhong_partner,levelstarttime','id');
                    if(!$midlist) continue;

                    $commission = 0;
                    $totalscore = 0;

                    if(!$midfhArr) $midfhArr = [];
                    $newcommission = 0;
                    foreach($midlist as $item){
                        $mid = $item['id'];
                        if($mid == $og['mid'])
                        {
                            //会员自己的首单不分
                            $orderid_first = Db::name('shop_order')->where('mid',$mid)->whereIn('status',[1,2,3])->order('id','asc')->value('id');
                            if($og['orderid'] == $orderid_first) continue;
                        }
                        //分红规则：（本人的贡献值）÷（平台所有会员的全部贡献值总和(排除首单不分)）x（单个订单可分配的利润）=本人单个订单应分配金额
                        $commission = $item['gongxian'] / $gongxianTotal * $sysset['gongxian_percent'] * $fenhongprice * 0.01;
                        if($commission == 0) continue;
                        if($isyj == 1 && $mid == $yjmid && $commission > 0){
                            $commissionyj += $commission;
                            $og['commission'] = round($commission,2);
                            $og['fhname'] = t('贡献',$aid).'分红';
                            $newoglist[] = $og;
                            break;
                        }
                        if($midfhArr[$mid]){
                            $midfhArr[$mid]['totalcommission'] = $midfhArr[$mid]['totalcommission'] + $commission;
                            $midfhArr[$mid]['commission'] = $midfhArr[$mid]['commission'] + $commission;
                            $midfhArr[$mid]['money'] = 0;
                            $midfhArr[$mid]['ogids'][] = $og['id'];
                            $midfhArr[$mid]['score'] = 0;
                        }else{
                            $midfhArr[$mid] = [
                                'totalcommission'=>$commission,
                                'commission'=>$commission,
                                'money'=>0,
                                'score'=>0,
                                'ogids'=>[$og['id']],
                                'module'=>$og['module'] ?? 'shop',
                            ];
                        }
                        if(getcustom('commission_orderrefund_deduct',$aid) && $isyj == 0){
                            self::fhrecord($aid,$mid,$commission,0,$og['id'],$og['module'] ?? 'shop','gongxian_fenhong',t('贡献',$aid).'分红');
                        }
                    }
                }
                if($isyj == 0 && $sysset['fhjiesuanhb'] == 0) {
                    if($midfhArr){
                        $remark = t('贡献',$aid).'分红';
                        self::fafang($aid,$midfhArr,'gongxian_fenhong',$remark);
                    }
                    $midfhArr = [];
                }
            }
            if($isyj == 0 && $sysset['fhjiesuanhb'] == 1) {
                if($midfhArr){
                    $remark = t('贡献',$aid).'分红';
                    self::fafang($aid,$midfhArr,'gongxian_fenhong',$remark);
                }
            }
            if($isyj == 1){
                return ['commissionyj'=>round($commissionyj,2),'oglist'=>$newoglist];
            }
        }
    }

    public static function fafang($aid,$fhArr,$type,$remark,$frommid=0,$fhShareArr = []){
        if(!$fhArr && !$fhShareArr) return [];
        $moeny_weishu = 2;
        if(getcustom('fenhong_money_weishu',$aid)){
            $moeny_weishu = Db::name('admin_set')->where('aid',$aid)->value('fenhong_money_weishu');
        }
        $moeny_weishu = $moeny_weishu?$moeny_weishu:2;
        $score_weishu = 0;

        $moeny_weishu2 = 2;
        if(getcustom('member_money_weishu',$aid)){
            $moeny_weishu2 = Db::name('admin_set')->where('aid',$aid)->value('member_money_weishu');
        }
        $moeny_weishu2 = $moeny_weishu2?$moeny_weishu2:2;
        if(getcustom('score_weishu',$aid)){
            $score_weishu = Db::name('admin_set')->where('aid',$aid)->value('score_weishu');
            $score_weishu = $score_weishu?$score_weishu:0;
        }
        foreach($fhArr as $mid=>$midfh){
            $totalcommission = dd_money_format($midfh['totalcommission'],$moeny_weishu);
            $commission = dd_money_format($midfh['commission'],$moeny_weishu);
            $money = dd_money_format($midfh['money'],$moeny_weishu2);
            $score = dd_money_format($midfh['score'],$score_weishu);
            $fuchi = dd_money_format($midfh['fuchi']);
            //  var_dump($midfh);
            //  var_dump($midfh['totalcommission']);
            if($totalcommission > 0 || $score>0) {
                $fhdata = [];
                $fhdata['aid'] = $aid;
                $fhdata['mid'] = $mid;
                $fhdata['commission'] = $totalcommission;
                if(getcustom('gdfenhong_score',$aid)){
                    $fhdata['score'] = $score;
                }
                $fhdata['remark'] = $midfh['remark'] ? $midfh['remark'] : $remark;
                $fhdata['type'] = $type;
                $fhdata['createtime'] = time();
                $fhdata['ogids'] = implode(',',$midfh['ogids']);
                $fhdata['module'] = $midfh['module'];
//              var_dump($fhdata);
                Db::name('member_fenhonglog')->insert($fhdata);
            }
            if($commission > 0){
                $levelid = 0;
                if(getcustom('commission_frozen_level',$aid)){
                    $levelid = $midfh['levelid']??0;
                }
                \app\commons\Member::addcommission($aid,$mid,$frommid,$commission,$fhdata['remark'],1,$type,$levelid);
                //分红到账通知
                if(getcustom('fenhong_send_tmpl',$aid)){
                    $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->field('id,nickname,realname')->find();
                    $tmplcontent = [];
                    $tmplcontent['first'] = '分红到账提醒';
                    $tmplcontent['remark'] = '请更加努力！';
                    $tmplcontent['keyword1'] = '';//商品名称
                    $tmplcontent['keyword2'] = $member['nickname']??'';  //兑换用户
                    $tmplcontent['keyword3'] = $commission.'元';  //结算佣金
                    $tmplcontent['keyword4'] = '';  //订单编号
                    $tmplcontent['keyword5'] = date('Y-m-d H:i:s',time());  //结算时间
                    //商品名称和订单编号获取：如果是多个订单一起结算，则统一发一条通知
                    $ogids = implode(',',$midfh['ogids']);
                    if($ogids){
                        $orderlist = Db::name('shop_order_goods')->alias('og')
                            ->join('shop_order o','o.id=og.orderid')
                            ->where('og.aid',$aid)->where('og.id','in',$ogids)
                            ->field('og.orderid,o.ordernum,o.title')
                            ->group('og.orderid')
                            ->select()->toArray();
                        if($orderlist){
                            if(count($orderlist)>1){
                                $tmplcontent['keyword1'] = $orderlist[0]['title']; //商品名称
                                $tmplcontent['keyword4'] = $orderlist[0]['ordernum'].'等';  //订单编号
                            }else{
                                $tmplcontent['keyword1'] = $orderlist[0]['title']; //商品名称
                                $tmplcontent['keyword4'] = $orderlist[0]['ordernum'];  //订单编号
                            }
                        }
                    }
                    $rs = \app\commons\Wechat::sendtmpl($aid,$mid,'tmpl_fenhong',$tmplcontent,m_url('pages/my/usercenter', $aid));
                }
            }
            if($money > 0){
                \app\commons\Member::addmoney($aid,$mid,$money,$fhdata['remark']);
            }
            
            if($score > 0){
                \app\commons\Member::addscore($aid,$mid,$score,$fhdata['remark']);
            }
            if($fuchi > 0){
                \app\commons\Member::addFuchi($aid,$mid,$frommid,$fuchi,$fhdata['remark']);
            }
        }

        if($fhShareArr){
            $fhArr = $fhShareArr;
            foreach($fhArr as $mid=>$midfh){
                $totalcommission = dd_money_format($midfh['totalcommission'],$moeny_weishu);
                $commission = dd_money_format($midfh['commission'],$moeny_weishu);
                $money = dd_money_format($midfh['money'],$moeny_weishu2);
                $score = dd_money_format($midfh['score'],$score_weishu);
                if($totalcommission > 0 || $score>0) {
                    $fhdata = [];
                    $fhdata['aid'] = $aid;
                    $fhdata['mid'] = $mid;
                    $fhdata['commission'] = $totalcommission;
                    if(getcustom('gdfenhong_score',$aid)){
                        $fhdata['score'] = $score;
                    }
                    $fhdata['remark'] = $midfh['remark'] ? $midfh['remark'] : $remark;
                    $fhdata['type'] = $type;
                    $fhdata['createtime'] = time();
                    $fhdata['ogids'] = implode(',',$midfh['ogids']);
                    $fhdata['module'] = $midfh['module'];
                    Db::name('member_fenhonglog')->insert($fhdata);
                }
                if($commission > 0){
                   $rs =  \app\commons\Member::addcommission($aid,$mid,$frommid,$commission,$fhdata['remark'],1,$type);

                    //分红到账通知
                    if(getcustom('fenhong_send_tmpl',$aid)){
                        $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->field('id,nickname,realname')->find();
                        $tmplcontent = [];
                        $tmplcontent['first'] = '分红到账提醒';
                        $tmplcontent['remark'] = '请更加努力！';
                        $tmplcontent['keyword1'] = '';//商品名称
                        $tmplcontent['keyword2'] = $member['nickname']??'';  //兑换用户
                        $tmplcontent['keyword3'] = $commission.'元';  //结算佣金
                        $tmplcontent['keyword4'] = '';  //订单编号
                        $tmplcontent['keyword5'] = date('Y-m-d H:i:s',time());  //结算时间
                        //商品名称和订单编号获取：如果是多个订单一起结算，则统一发一条通知
                        $ogids = implode(',',$midfh['ogids']);
                        if($ogids){
                            $orderlist = Db::name('shop_order_goods')->alias('og')
                                ->join('shop_order o','o.id=og.orderid')
                                ->where('og.aid',$aid)->where('og.id','in',$ogids)
                                ->field('og.orderid,o.ordernum,o.title')
                                ->group('og.orderid')
                                ->select()->toArray();
                            if($orderlist){
                                if(count($orderlist)>1){
                                    $tmplcontent['keyword1'] = $orderlist[0]['title']; //商品名称
                                    $tmplcontent['keyword4'] = $orderlist[0]['ordernum'].'等';  //订单编号
                                }else{
                                    $tmplcontent['keyword1'] = $orderlist[0]['title']; //商品名称
                                    $tmplcontent['keyword4'] = $orderlist[0]['ordernum'];  //订单编号
                                }
                            }
                        }
                        $rs = \app\commons\Wechat::sendtmpl($aid,$mid,'tmpl_fenhong',$tmplcontent,m_url('pages/my/usercenter', $aid));
                    }
                }
                if($money > 0){
                    \app\commons\Member::addmoney($aid,$mid,$money,$fhdata['remark']);
                }

                if($score > 0){
                    \app\commons\Member::addscore($aid,$mid,$score,$fhdata['remark']);
                }
            }
        }
    }
    public static function touzi_fenhong($aid,$sysset,$oglist,$starttime=0,$endtime=0,$isyj=0,$yjmid=0,$bid=0){
        $touzi_fenhong_custom = getcustom('touzi_fenhong',$aid);
        if($touzi_fenhong_custom){
            if(getcustom('maidan_fenhong_new',$aid)){
                $bids_maidan = Db::name('business')->where('maidan_touzi',1)->column('id');
                $bids_maidan = array_merge([0],$bids_maidan);
            }
            $admin = Db::name('admin')->where('id',$aid)->find();
            if($admin['shareholder_status'] != 1)
                return ['commissionyj'=>0,'oglist'=>[]];
            if(empty($sysset['touzi_fh_percent']) || $sysset['touzi_fh_percent'] <= 0){
                return ['commissionyj'=>0,'oglist'=>[]];
            }

            if($endtime == 0) $endtime = time();
            if($isyj == 1 && !$oglist){
                //多商户的商品是否参与分红
                if($sysset['fhjiesuanbusiness'] == 1){
                    $bwhere = '1=1';
                    if($bid >0){
                        $bwhere = [['og.bid','=',$bid]];
                    }
                }else{
                    $bwhere = [['og.bid','=','0']];
                }
              
                $oglist = Db::name('shop_order_goods')->alias('og')->field('og.*,o.area2,m.nickname,m.headimg')->where('og.aid',$aid)->where('og.isfenhong',0)->where('og.status','in',[1,2,3])->where('og.refund_num',0)->join('shop_order o','o.id=og.orderid')->join('member m','m.id=og.mid')->where($bwhere)->order('og.id desc')->select()->toArray();
                if(getcustom('maidan_fenhong_new',$aid) && $sysset['maidanfenhong']){
                    //买单分红
                    $bwhere_maidan = [['og.bid', 'in', $bids_maidan]];
                    $maidan_orderlist = Db::name('maidan_order')
                        ->alias('og')
                        ->join('member m','m.id=og.mid')
                        ->where('og.aid',$aid)
                        ->where('og.isfenhong',0)
                        ->where('og.status',1)
                        ->where($bwhere_maidan)
                        ->field('og.*,m.nickname,m.headimg')
                        ->order('og.id desc')
                        ->select()
                        ->toArray();
                    if($maidan_orderlist){
                        foreach($maidan_orderlist as $mdk=>$mdv){
                            $mdv['name']             = $mdv['title'];
                            $mdv['real_totalprice']  = $mdv['paymoney'];
                            //买单分红结算方式
                            if($sysset['maidanfenhong_type'] == 1){
                                //按利润结算时直接把销售额改成利润
                                $mdv['real_totalprice'] = $mdv['paymoney'] - $mdv['cost_price'];
                            }
                            $mdv['cost_price']       = 0;
                            $mdv['num']              = 1;
                            $mdv['module']           = 'maidan';
                            $oglist[] = $mdv;
                        }
                    }
                }
            }
            if(!$oglist) return ['commissionyj'=>0,'oglist'=>[]];
            
            $ogids = [];
            $midfhArr = [];
            $newoglist = [];
            $commissionyj = 0;
            $allfenhongprice = 0;
            foreach($oglist as $og){
                $ogids[] = $og['id'];
                if($og['bid'] == 0){
                    //参与分红的股东（平台）
                    $touziTotal = Db::name('shareholder')->where('aid',$aid)->where('bid','=',0)->where('status',1)->where('money','>',0)->sum('money');
                    if(!$touziTotal) continue ;
                    if($sysset['touzi_fh_type'] == 0){
                        $fenhongprice = $og['real_totalprice'];
                    }else{
                        $fenhongprice = $og['real_totalprice'] - $og['cost_price'] * $og['num'];
                    }
                    if($fenhongprice <= 0) continue;
                    $where = [];
                    $where[] = ['aid', '=', $aid];
                    $where[] = ['money', '>', 0]; //判断投资额
                    $where[] = ['bid', '=', 0];//bid=0是分平台的股东
                    //平台进行分红  和多商户的分红
                    $midlist = Db::name('shareholder')->where($where)->column('id,money,mid','id');
                    if(!$midlist) continue;
                    if(!$midfhArr) $midfhArr = [];
                    foreach($midlist as $item){
                        $mid = $item['mid'];
                        if($mid == $og['mid'])
                        {
                            //会员自己的首单不分
                            $orderCount = Db::name('shop_order')->where('mid',$mid)->whereIn('status',[1,2,3])->count();
                            if($orderCount <= 1) continue;
                        }
                        //分红规则：本人投资额÷所有人的投资额总和x订单销售额（或利润）x系统设置的分配比例
                        $commission = $item['money'] / $touziTotal * $sysset['touzi_fh_percent'] * $fenhongprice * 0.01;
                        if($commission == 0) continue;
                        if($isyj == 1 && $mid == $yjmid && $commission > 0){
                            $commissionyj += $commission;
                            $og['commission'] = round($commission,2);
                            $og['fhname'] = t('投资分红',$aid);
                            $newoglist[] = $og;
                            break;
                        }
                       
                        if($midfhArr[$mid]){
                            $midfhArr[$mid]['totalcommission'] = $midfhArr[$mid]['totalcommission'] + $commission;
                            $midfhArr[$mid]['commission'] = $midfhArr[$mid]['commission'] + $commission;
                            $midfhArr[$mid]['money'] = 0;
                            $midfhArr[$mid]['ogids'][] = $og['id'];
                            $midfhArr[$mid]['score'] = 0;
                        }else{
                            $midfhArr[$mid] = [
                                'totalcommission'=>$commission,
                                'commission'=>$commission,
                                'money'=>0,
                                'score'=>0,
                                'ogids'=>[$og['id']],
                                'module'=>$og['module'] ?? 'shop',
                            ];
                        }
                        if(getcustom('commission_orderrefund_deduct',$aid) && $isyj == 0){
                            self::fhrecord($aid,$mid,$commission,0,$og['id'],$og['module'] ?? 'shop','touzi_fenhong',t('投资分红',$aid));
                        }
                    }
                    if($isyj == 0 && $sysset['fhjiesuanhb'] == 0) {
                        if($midfhArr){
                            $remark = t('投资分红',$aid);
                            self::fafang($aid,$midfhArr,'touzi_fenhong',$remark);
                        }
                        $midfhArr = [];
                    }
                }
                
                if($og['bid'] > 0){
                    if(getcustom('maidan_fenhong_new',$aid) && $og['module']=='maidan'){
                        if($og['bid'] > 0 && !in_array($og['bid'],$bids_maidan)){
                            continue;
                        }
                    }
                    $b_touziTotal = Db::name('shareholder')->where('aid',$aid)->where('bid',$og['bid'])->where('status',1)->where('money','>',0)->sum('money');
                    if(!$b_touziTotal) continue;
                    //查询多商户是否开始 投资分红
                    $business = Db::name('business')->where('id',$og['bid'])->find();
                    //判断商户是否开启了 投资分红 ，开启了投资还进行分红
                    if($business['shareholder_status'] ==1){
                        if($business['touzi_fh_type'] == 0){
                            $fenhongprice = $og['real_totalprice'];
                        }else{
                            $fenhongprice = $og['real_totalprice'] - $og['cost_price'] * $og['num'];
                        }
                        if($fenhongprice <= 0) continue;
                        $bswhere = [];
                        $bswhere[] = ['aid', '=', $aid];
                        $bswhere[] = ['money', '>', 0]; //判断投资额
                        $bswhere[] = ['bid', '=', $og['bid']]; //查询当前商品的 多商户的股东
                        $bmidlist = Db::name('shareholder')->where($bswhere)->column('id,money,mid','id');
                        if(!$bmidlist) continue;
                       
                        if(!$midfhArr) $midfhArr = [];

                        foreach($bmidlist as $bitem){
                            $bmid = $bitem['mid'];
                            if($bmid == $og['mid'])
                            {
                                //会员自己的首单不分
                                $orderCount = Db::name('shop_order')->where('mid',$mid)->whereIn('status',[1,2,3])->count();
                                if($orderCount <= 1) continue;
                            }
                            //分红规则：本人投资额÷所有人的投资额总和x订单销售额（或利润）x系统设置的分配比例
                            $commission = $bitem['money'] / $b_touziTotal * $business['touzi_fh_percent'] * $fenhongprice * 0.01;
                            if($commission == 0) continue;
                            if($isyj == 1 && $bmid == $yjmid && $commission > 0){
                                $commissionyj += $commission;
                                $og['commission'] = round($commission,2);
                                $og['fhname'] = t('投资分红',$aid);
                                $newoglist[] = $og;
                                break;
                            }
                            if($midfhArr[$bmid]){
                                $midfhArr[$bmid]['totalcommission'] = $midfhArr[$bmid]['totalcommission'] + $commission;
                                $midfhArr[$bmid]['commission'] = $midfhArr[$bmid]['commission'] + $commission;
                                $midfhArr[$bmid]['money'] = 0;
                                $midfhArr[$bmid]['ogids'][] = $og['id'];
                                $midfhArr[$bmid]['score'] = 0;
                            }else{
                                $midfhArr[$bmid] = [
                                    'totalcommission'=>$commission,
                                    'commission'=>$commission,
                                    'money'=>0,
                                    'score'=>0,
                                    'ogids'=>[$og['id']],
                                    'module'=>$og['module'] ?? 'shop',
                                ];
                            }
                            if(getcustom('commission_orderrefund_deduct',$aid) && $isyj == 0){
                                self::fhrecord($aid,$bmid,$commission,0,$og['id'],$og['module'] ?? 'shop','touzi_fenhong',t('投资分红',$aid));
                            }

                        }
                        if($isyj == 0 && $sysset['fhjiesuanhb'] == 0) {
                            if($midfhArr){
                                $remark = t('投资分红',$aid);
                                self::fafang($aid,$midfhArr,'touzi_fenhong',$remark);
                            }
                            $midfhArr = [];
                        }
                    }

                }
                $allfenhongprice = $allfenhongprice + $fenhongprice;
            }
            if($isyj == 0 && $sysset['fhjiesuanhb'] == 1) {
                if($midfhArr){
                    $remark = t('投资分红',$aid);
                    self::fafang($aid,$midfhArr,'touzi_fenhong',$remark);
                }
            }
            if($isyj == 1){
                return ['commissionyj'=>round($commissionyj,2),'oglist'=>$newoglist];
            }
        }
    }

    //购车基金
    public static function goucheBonus($aid,$sysset,$midteamfhArr = [],$oglist = [],$isyj=0,$yjmid=0,$commissionpercent=1,$moneypercent=0){
        $gouche_bonus_able = getcustom('teamfenhong_gouche',$aid);
        if(getcustom('teamfenhong_gouche',$aid)){
            writeLog('购车基金进入','gouche_bonus.log');
            //dump($midteamfhArr);
            writeLog('团队分红数据'.json_encode($midteamfhArr),'gouche_bonus.log');
            if(empty($midteamfhArr)){
                return true;
            }
            //购车基金没开启的话直接返回
            if(!$gouche_bonus_able){
                writeLog('未开启购车基金','gouche_bonus.log');
                return true;
            }
            $midgoucheArr = [];//购车基金
            $newoglist = [];
            $oglist = array_column($oglist,null,'id');
            //循环处理拿团队分红奖的会员
            foreach($midteamfhArr as $t_mid=>$team){
                if($team['type']!='团队分红'){
                    continue;
                }
                writeLog('会员'.$t_mid.'购车基金开始','gouche_bonus.log');

                //拿奖人从mid开始顺着推荐网向上找最近符合条件的会员
                $member = Db::name('member')->where('id', $t_mid)->find();
                $pids = $member['path'];
                if($pids){
                    $pids .= ','.$t_mid;
                }else{
                    $pids = (string)$t_mid;
                }
                $parentList = Db::name('member')->where('id','in',$pids)->order(Db::raw('field(id,'.$pids.')'))->select()->toArray();
                $parentList = array_reverse($parentList);
                //判断会员是否满足购车基金条件
                $mid = 0;
                foreach($parentList as $parent){
                    $gouche_able = \app\commons\Member::goucheAble($parent['id']);
                    if(!$gouche_able){
                        continue;
                    }else{
                        $mid = $parent['id'];
                        break;
                    }
                }
                if(!$mid){
                    continue;
                }
                $commissionyj = 0;
                //根据分红奖订单查询购车基金
                foreach($team['ogids'] as $ogid){
                    $commissionyj_my = 0;
                    $og = $oglist[$ogid]??'';
                    if(!$og){
                        //未查询到订单，跳过不处理
                        continue;
                    }
                    //购车基金计算
                    if($og['gouchebonusset']==1){
                        $gouchebonusdata = json_decode($og['gouchebonusdata1'],true);
                    }elseif($og['gouchebonusset']==2){
                        $gouchebonusdata = json_decode($og['gouchebonusdata2'],true);
                    }else{
                        $gouchebonusdata = [];
                    }
                    writeLog('会员'.$mid.'购车基金：订单'.$og['id'].'奖金参数'.$og['gouchebonusset'].'=>'.json_encode($gouchebonusdata),'gouche_bonus.log');
                    if(empty($gouchebonusdata)){
                        //未查询到设置的奖金数据，跳过不处理
                        continue;
                    }
                    //判断是使用订单金额还是利润计算奖金
                    if($sysset['fhjiesuantype'] == 0){
                        $fenhongprice = $og['real_totalprice'];
                    }else{
                        $fenhongprice = $og['real_totalprice'] - $og['cost_price'] * $og['num'];
                    }

                    //计算奖金
                    $bonus = $gouchebonusdata[$team['levelid']]['commission']?:0;
                    if($og['gouchebonusset']==1){
                        //按比例计算
                        $gouche_bonus = bcmul($bonus/100,$fenhongprice,2);
                    }elseif($og['gouchebonusset']==2){
                        //按金额计算
                        $gouche_bonus = $bonus;
                    }
                    writeLog('会员'.$mid.'购车基金：订单'.$og['id'].'订单金额'.$fenhongprice.'级别'.$team['levelid'].'奖金=>'.$gouche_bonus,'gouche_bonus.log');
                    if($gouche_bonus<0){
                        continue;
                    }
                    if($isyj == 1 && $yjmid == $mid){
                        $commissionyj_my = $gouche_bonus;
                    }
                    if($commissionpercent != 1){
                        $fenhongcommission = round($gouche_bonus*$commissionpercent,2);
                        $fenhongmoney = round($gouche_bonus*$moneypercent,2);
                    }else{
                        $fenhongcommission = $gouche_bonus;
                        $fenhongmoney = 0;
                    }
                    //购车基金汇总
                    if($midgoucheArr[$mid]){
                        $midgoucheArr[$mid]['totalcommission'] = bcadd($midgoucheArr[$mid]['totalcommission'] , $gouche_bonus);
                        $midgoucheArr[$mid]['commission'] = bcadd($midgoucheArr[$mid]['commission'] , $fenhongcommission);
                        $midgoucheArr[$mid]['money'] = bcadd($midgoucheArr[$mid]['money'] , $fenhongmoney);
                        $midgoucheArr[$mid]['score'] = 0;
                        $midgoucheArr[$mid]['ogids'][] = $og['id'];
                        $midgoucheArr[$mid]['levelid'] = $team['levelid'];
                    }else{
                        $midgoucheArr[$mid] = [
                            'totalcommission' => $gouche_bonus,
                            'commission' => $fenhongcommission,
                            'money' => $fenhongmoney,
                            'score' => 0,
                            'ogids' => [$og['id']],
                            'module' => $og['module'] ?? 'shop',
                            'levelid' => $team['levelid']
                        ];
                    }
                    writeLog('会员'.$mid.'购车基金：订单'.$og['id'].'汇总'.json_encode($midgoucheArr),'gouche_bonus.log');
                    //dump($midgoucheArr);
                    if($isyj == 1 && $commissionyj_my > 0){
                        $commissionyj = bcadd($commissionyj,$commissionyj_my,2);

                        $newoglist[$og['id']] = $commissionyj_my;
                    }
                    if($isyj == 0 && $sysset['fhjiesuanhb'] == 0) {
                        self::fafang($aid,$midgoucheArr,'teamfenhong_gouche',t('购车基金',$aid));
                        $midgoucheArr = [];
                    }
                }
            }
            if($isyj == 1){
                return ['commissionyj'=>round($commissionyj,2),'oglist'=>$newoglist];
            }
            if($isyj == 0 && $sysset['fhjiesuanhb'] == 1) {
                self::fafang($aid,$midgoucheArr,'teamfenhong_gouche',t('购车基金',$aid));
            }
        }
    }

    //旅游基金
    public static function lvyouBonus($aid,$sysset,$midteamfhArr = [],$oglist = [],$isyj=0,$yjmid=0,$commissionpercent=1,$moneypercent=0){
        $lvyou_bonus_able = getcustom('teamfenhong_lvyou',$aid);
        if(getcustom('teamfenhong_lvyou',$aid)){
            writeLog('旅游基金进入','lvyou_bonus.log');
            writeLog('团队分红数据'.json_encode($midteamfhArr),'lvyou_bonus.log');
            if(empty($midteamfhArr)){
                return true;
            }
            //旅游基金没开启的话直接返回
            if(!$lvyou_bonus_able){
                writeLog('未开启旅游基金','lvyou_bonus.log');
                return true;
            }
            $midlvyouArr = [];//旅游基金
            $newoglist = [];
            $oglist = array_column($oglist,null,'id');

            //循环处理拿团队分红奖的会员
            foreach($midteamfhArr as $t_mid=>$team){
                if($team['type']!='团队分红'){
                    continue;
                }
                writeLog('会员'.$t_mid.'旅游基金开始','lvyou_bonus.log');

                //拿奖人从mid开始顺着推荐网向上找最近符合条件的会员
                $member = Db::name('member')->where('id', $t_mid)->find();
                $pids = $member['path'];
                if($pids){
                    $pids .= ','.$t_mid;
                }else{
                    $pids = (string)$t_mid;
                }
                $parentList = Db::name('member')->where('id','in',$pids)->order(Db::raw('field(id,'.$pids.')'))->select()->toArray();
                $parentList = array_reverse($parentList);
                //判断会员是否满足拿奖条件
                $mid = 0;
                foreach($parentList as $parent){
                    $lvyou_able = \app\commons\Member::lvyouAble($parent['id']);
                    if(!$lvyou_able){
                        continue;
                    }else{
                        $mid = $parent['id'];
                        break;
                    }
                }
                if(!$mid){
                    continue;
                }

                $commissionyj = 0;
                //根据分红奖订单查询旅游基金
                foreach($team['ogids'] as $ogid){
                    $commissionyj_my = 0;
                    $og = $oglist[$ogid]??'';
                    if(!$og){
                        //未查询到订单，跳过不处理
                        continue;
                    }
                    //旅游基金计算
                    if($og['lvyoubonusset']==1){
                        $lvyoubonusdata = json_decode($og['lvyoubonusdata1'],true);
                    }elseif($og['lvyoubonusset']==2){
                        $lvyoubonusdata = json_decode($og['lvyoubonusdata2'],true);
                    }else{
                        $lvyoubonusdata = [];
                    }
                    writeLog('会员'.$mid.'旅游基金：订单'.$og['id'].'奖金参数'.$og['lvyoubonusset'].'=>'.json_encode($lvyoubonusdata),'lvyou_bonus.log');
                    if(empty($lvyoubonusdata)){
                        //未查询到设置的奖金数据，跳过不处理
                        continue;
                    }
                    //判断是使用订单金额还是利润计算奖金
                    if($sysset['fhjiesuantype'] == 0){
                        $fenhongprice = $og['real_totalprice'];
                    }else{
                        $fenhongprice = $og['real_totalprice'] - $og['cost_price'] * $og['num'];
                    }
                    //计算奖金
                    $bonus = $lvyoubonusdata[$team['levelid']]['commission']?:0;
                    if($og['lvyoubonusset']==1){
                        //按比例计算
                        $lvyou_bonus = bcmul($bonus/100,$fenhongprice,2);
                    }elseif($og['lvyoubonusset']==2){
                        //按金额计算
                        $lvyou_bonus = $bonus;
                    }
                    writeLog('会员'.$mid.'旅游基金：订单'.$og['id'].'订单金额'.$fenhongprice.'级别'.$team['levelid'].'奖金=>'.$lvyou_bonus,'lvyou_bonus.log');
                    if($isyj == 1 && $yjmid == $mid){
                        $commissionyj_my = $lvyou_bonus;
                    }
                    if($commissionpercent != 1){
                        $fenhongcommission = round($lvyou_bonus*$commissionpercent,2);
                        $fenhongmoney = round($lvyou_bonus*$moneypercent,2);
                    }else{
                        $fenhongcommission = $lvyou_bonus;
                        $fenhongmoney = 0;
                    }
                    //旅游基金汇总
                    if($midlvyouArr[$mid]){
                        $midlvyouArr[$mid]['totalcommission'] = bcadd($midlvyouArr[$mid]['totalcommission'] , $lvyou_bonus,2);
                        $midlvyouArr[$mid]['commission'] = bcadd($midlvyouArr[$mid]['commission'] , $fenhongcommission);
                        $midlvyouArr[$mid]['money'] = bcadd($midlvyouArr[$mid]['money'] , $fenhongmoney);
                        $midlvyouArr[$mid]['score'] = 0;
                        $midlvyouArr[$mid]['ogids'][] = $og['id'];
                        $midlvyouArr[$mid]['levelid'] = $team['levelid'];
                    }else{
                        $midlvyouArr[$mid] = [
                            'totalcommission' => $lvyou_bonus,
                            'commission' => $fenhongcommission,
                            'money' => $fenhongmoney,
                            'score' => 0,
                            'ogids' => [$og['id']],
                            'module' => $og['module'] ?? 'shop',
                            'levelid' => $team['levelid']
                        ];
                    }
                    if($isyj == 1 && $commissionyj_my > 0){
                        $commissionyj = bcadd($commissionyj,$commissionyj_my,2);
                        $newoglist[$og['id']] = $commissionyj_my;
                    }
                    writeLog('会员'.$mid.'旅游基金：订单'.$og['id'].'汇总'.json_encode($midlvyouArr),'lvyou_bonus.log');
                    if($isyj == 0 && $sysset['fhjiesuanhb'] == 0) {
                        self::fafang($aid,$midlvyouArr,'teamfenhong_lvyou',t('旅游基金',$aid));
                        $midlvyouArr = [];
                    }
                }
            }
            if($isyj == 1){
                return ['commissionyj'=>round($commissionyj,2),'oglist'=>$newoglist];
            }
            if($isyj == 0 && $sysset['fhjiesuanhb'] == 1) {
                self::fafang($aid,$midlvyouArr,'teamfenhong_lvyou',t('旅游基金',$aid));
            }
        }
    }

    /**
     * 团队收益
     * 拿下级总佣金收益的百分比
     */
    public static function teamshouyi($aid,$sysset,$midteamfhArr = [],$oglist = [],$isyj=0,$yjmid=0,$commissionpercent=1,$moneypercent=0){
        if(getcustom('teamfenhong_shouyi',$aid)){
            writeLog('团队收益进入','teamfenhong_shouyi.log');
            //dump($midteamfhArr);
            writeLog('奖金数据'.json_encode($midteamfhArr),'teamfenhong_shouyi.log');
            if(empty($midteamfhArr)){
                return true;
            }
            $midshouyiArr = [];//团队收益
            $newoglist = [];
            $oglist = array_column($oglist,null,'id');
            //循环处理拿团队分红奖的会员
            foreach($midteamfhArr as $t_mid=>$team){

                writeLog('会员'.$t_mid.'团队收益开始,总收益'.$team['commission'],'teamfenhong_shouyi.log');

                //拿奖人从$t_mid开始顺着推荐网向上找最近符合条件的会员
                $member = Db::name('member')->where('id', $t_mid)->find();
                $pids = $member['path'];
                if(!$pids){
                    continue;
                }

                $parentList = Db::name('member')->where('id','in',$pids)->order(Db::raw('field(id,'.$pids.')'))->select()->toArray();
                $parentList = array_reverse($parentList);
                //循环上级发放团队收益
                $commissionyj = 0;
                $ceng = 1;//拿奖层级
                $commission_total = $team['commission'];
                $down_mid = $t_mid;
                foreach($parentList as $parent){
                    writeLog('会员'.$t_mid.',第'.$ceng.'层会员id'.$parent['id'].'开始','teamfenhong_shouyi.log');
                    $mid = $parent['id'];
                    $commissionyj_my = 0;
                    $level_data = Db::name('member_level')->where('id',$parent['levelid'])->find();
                    //判断会员是否满足购车基金条件
                    $teamshouyi_able = \app\commons\Member::teamshouyiAble($parent['id'],$level_data);
                    if(!$teamshouyi_able){
                        continue;
                    }
                    //拿奖层级
                    $bonus_cengji = $level_data['team_shouyi_lv'];
                    //奖金比例
                    $bonus_bili = $level_data['team_shouyi'];
                    writeLog('会员'.$t_mid.',第'.$ceng.'层会员id'.$parent['id'].'拿奖层级'.$bonus_cengji.',拿奖比例'.$bonus_bili,'teamfenhong_shouyi.log');
                    if($bonus_cengji && $bonus_cengji>$ceng){
                        continue;
                    }
                    if($bonus_bili<=0){
                        continue;
                    }
                    $commission_total = bcmul($commission_total,$bonus_bili/100,2);
                    writeLog('会员'.$t_mid.',第'.$ceng.'层会员id'.$parent['id'].'拿奖金额'.$commission_total,'teamfenhong_shouyi.log');
                    $shouyi_min = $level_data['team_shouyi_min'];
                    if($commission_total<$shouyi_min){
                        writeLog('会员'.$t_mid.',第'.$ceng.'层会员id'.$parent['id'].'收益小于'.$shouyi_min,'teamfenhong_shouyi.log');
                        break;
                    }

                    if($isyj == 1 && $yjmid == $mid){
                        $commissionyj_my = $commission_total;
                    }
                    if($commissionpercent != 1){
                        $fenhongcommission = round($commission_total*$commissionpercent,2);
                        $fenhongmoney = round($commission_total*$moneypercent,2);
                    }else{
                        $fenhongcommission = $commission_total;
                        $fenhongmoney = 0;
                    }
                    //团队收益汇总
                    if($midshouyiArr[$mid]){
                        $midshouyiArr[$mid]['totalcommission'] = bcadd($midshouyiArr[$mid]['totalcommission'] , $commission_total);
                        $midshouyiArr[$mid]['commission'] = bcadd($midshouyiArr[$mid]['commission'] , $fenhongcommission);
                        $midshouyiArr[$mid]['money'] = bcadd($midshouyiArr[$mid]['money'] , $fenhongmoney);
                        $midshouyiArr[$mid]['score'] = 0;
                        $midshouyiArr[$mid]['ogids'][] = $down_mid;
                        $midshouyiArr[$mid]['levelid'] = $parent['levelid'];
                    }else{
                        $midshouyiArr[$mid] = [
                            'totalcommission' => $commission_total,
                            'commission' => $fenhongcommission,
                            'money' => $fenhongmoney,
                            'score' => 0,
                            'ogids' => [$down_mid],
                            'module' => 'member',
                            'levelid' => $parent['levelid']
                        ];
                    }
                    writeLog('会员'.$mid.'团队收益：下级会员'.$t_mid.'汇总'.json_encode($midshouyiArr),'teamfenhong_shouyi.log');
                    //dump($midgoucheArr);
                    if($isyj == 1 && $commissionyj_my > 0){
                        $commissionyj = bcadd($commissionyj,$commissionyj_my,2);

                        $newoglist[$t_mid] = $commissionyj_my;
                    }
                    if($isyj == 0 && $sysset['fhjiesuanhb'] == 0) {
                        self::fafang($aid,$midshouyiArr,'teamfenhong_shouyi',t('团队收益',$aid),$down_mid);
                        $midshouyiArr = [];
                    }
                    $down_mid = $mid;
                }
            }
            if($isyj == 1){
                return ['commissionyj'=>round($commissionyj,2),'oglist'=>$newoglist];
            }
            if($isyj == 0 && $sysset['fhjiesuanhb'] == 1) {
                self::fafang($aid,$midshouyiArr,'teamfenhong_shouyi',t('团队收益',$aid));
            }
        }
    }

    /**
     * 区域合伙人分红
     */
    public static function regionPartnerBonus($aid,$sysset){
        if(getcustom('region_partner',$aid)){
            //查询后台添加的所有区域
            $area_set = Db::name('region_partner_set')->where('aid',$aid)->select()->toArray();
            if(!$area_set){
                return true;
            }
            $midfhArr = [];
            foreach($area_set as $set){
                //查询该区域下参与分红的合伙人
                $map = [];
                $map[] = ['aid','=',$aid];
                $map[] = ['set_id','=',$set['id']];
//                $map[] = ['province','=',$set['province']];
//                $map[] = ['city','=',$set['city']];
//                $map[] = ['district','=',$set['district']];
                $map[] = ['status','=',1];
                $map[] = ['bonus_status','=',0];
                $lists = Db::name('region_partner_order')->where($map)->limit($set['fh_num'])->select()->toArray();
                if(!$lists){
                    continue;
                }
                //计算会员分红
                $count = count($lists);

                $bonus = bcdiv($set['day_fh'],$count,2);
                //dump($set['day_fh'].'=>'.$count.'=>'.$bonus);
                //分红汇总
                foreach($lists as $partner){
                    //判断分红金额
                    if($bonus>bcsub($partner['apply_money'],$partner['bonus'],2)){
                        $bonus = $partner['remain'];
                    }
                    $mid = $partner['mid'];
                    if($midfhArr[$mid]){
                        $midfhArr[$mid]['totalcommission'] = bcadd($midfhArr[$mid]['totalcommission'] , $bonus,2);
                        $midfhArr[$mid]['commission'] = bcadd($midfhArr[$mid]['commission'] , $bonus);
                        $midfhArr[$mid]['money'] = 0;
                        $midfhArr[$mid]['score'] = 0;
                        $midfhArr[$mid]['ogids'][] = $partner['id'];
                    }else{
                        $midfhArr[$mid] = [
                            'totalcommission' => $bonus,
                            'commission' => $bonus,
                            'money' => 0,
                            'score' => 0,
                            'ogids' => [$partner['id']],
                            'module' => 'region_partner',
                        ];
                    }
                    //if($sysset['fhjiesuanhb'] == 0) {
                        self::fafang($aid,$midfhArr,'region_partner',t('区域合伙人分红',$aid));
                        $midfhArr = [];
                    //}
                    //更新合伙人分红信息
                    $bonus_total = bcadd($partner['bonus'],$bonus);
                    $bonus_remain = bcsub($partner['apply_money'],$bonus_total,2);
                    $data_u = [];
                    $data_u['bonus'] = $bonus_total;
                    $data_u['remain'] = $bonus_remain;
                    if($bonus_total>=$partner['apply_money']){
                        $data_u['bonus_status'] = 1;
                    }
                    Db::name('region_partner_order')->where('id',$partner['id'])->update($data_u);
                }
            }
//            if($sysset['fhjiesuanhb'] == 1) {
//                self::fafang($aid,$midfhArr,'region_partner',t('区域合伙人分红',$aid));
//            }
        }
    }

    //回本股东分红
    public static function gdfenhong_huiben($aid,$sysset,$oglist,$starttime=0,$endtime=0,$isyj=0,$yjmid=0){
        $fenhong_gudong_huiben = getcustom('fenhong_gudong_huiben',$aid);
        if($fenhong_gudong_huiben){
            if($endtime == 0) $endtime = time();
            if($isyj == 1 && !$oglist){
                //多商户的商品是否参与分红
                if($sysset['fhjiesuanbusiness'] == 1){
                    $bwhere = '1=1';
                }else{
                    $bwhere = [['og.bid','=','0']];
                }
                $oglist = Db::name('shop_order_goods')->alias('og')->field('og.*,o.area2,m.nickname,m.headimg')->where('og.aid',$aid)->where('og.isfenhong',0)->where('og.status','in',[1,2,3])->where('og.refund_num',0)->join('shop_order o','o.id=og.orderid')->join('member m','m.id=og.mid')->where($bwhere)->order('og.id desc')->select()->toArray();

                if(getcustom('yuyue_fenhong',$aid)){
                    $yyorderlist = Db::name('yuyue_order')->alias('og')->field('og.*,m.nickname,m.headimg')->where('og.aid',$aid)->where('og.isfenhong',0)->where('og.status','in',[1,2,3])->join('member m','m.id=og.mid')->where($bwhere)->order('og.id desc')->select()->toArray();
                    foreach($yyorderlist as $k=>$v){
                        $v['name'] = $v['proname'];
                        $v['real_totalprice'] = $v['totalprice'];
                        $v['cost_price'] = $v['cost_price'] ?? 0;
                        $v['module'] = 'yuyue';
                        $oglist[] = $v;
                    }
                }
                if(getcustom('scoreshop_fenhong',$aid)){
                    $scoreshopoglist = Db::name('scoreshop_order_goods')->alias('og')->field('og.*,m.nickname,m.headimg')->where('og.aid',$aid)->where('og.isfenhong',0)->where('og.status','in',[1,2,3])->join('member m','m.id=og.mid')->where($bwhere)->order('og.id desc')->select()->toArray();
                    foreach($scoreshopoglist as $v){
                        $v['real_totalprice'] = $v['totalmoney'];
                        $v['module'] = 'scoreshop';
                        $oglist[] = $v;
                    }
                }
                if(getcustom('luckycollage_fenhong',$aid)){
                    $lcorderlist = Db::name('lucky_collage_order')->alias('og')->field('og.*,m.nickname,m.headimg')->where('og.aid',$aid)->where('og.isfenhong',0)->where('og.status','in',[1,2,3])->join('member m','m.id=og.mid')->where($bwhere)->whereRaw('og.buytype=1 or og.iszj=1')->where('og.isjiqiren',0)->order('og.id desc')->select()->toArray();
                    foreach($lcorderlist as $k=>$v){
                        $v['name'] = $v['proname'];
                        $v['real_totalprice'] = $v['totalprice'];
                        $v['cost_price'] = 0;
                        $v['module'] = 'luckycollage';
                        $oglist[] = $v;
                    }
                }
                if(getcustom('maidan_fenhong',$aid)){
                    //买单分红
                    $maidan_orderlist = Db::name('maidan_order')
                        ->alias('og')
                        ->join('member m','m.id=og.mid')
                        ->where('og.aid',$aid)
                        ->where('og.isfenhong',0)
                        ->where('og.status',1)
                        ->where($bwhere)
                        ->field('og.*,m.nickname,m.headimg')
                        ->order('og.id desc')
                        ->select()
                        ->toArray();
                    if($maidan_orderlist){
                        foreach($maidan_orderlist as $mdk=>$mdv){
                            $mdv['proid']            = 0;
                            $mdv['name']             = $mdv['title'];
                            $mdv['real_totalprice']  = $mdv['paymoney'];
                            $mdv['cost_price']       = 0;
                            $mdv['num']              = 1;
                            $mdv['module']           = 'maidan';
                            $oglist[] = $mdv;
                        }
                        unset($mdv);
                    }
                }
                if(getcustom('restaurant_fenhong',$aid)){
                    //点餐
                    $diancan_oglist = Db::name('restaurant_shop_order_goods')->alias('og')->field('og.*,o.area2,m.nickname,m.headimg')->where('og.aid',$aid)->where('og.isfenhong',0)->where('og.status','in',[1,2,3])->join('restaurant_shop_order o','o.id=og.orderid')->join('member m','m.id=og.mid')->where($bwhere)->order('og.id desc')->select()->toArray();
                    if($diancan_oglist){
                        foreach($diancan_oglist as $dck=>$dcv){
                            $dcv['module'] = 'diancan';
                            $oglist[]      = $dcv;
                        }
                        unset($dcv);
                    }
                    //外卖
                    $takeaway_oglist = Db::name('restaurant_takeaway_order_goods')->alias('og')->field('og.*,o.area2,m.nickname,m.headimg')->where('og.aid',$aid)->where('og.isfenhong',0)->where('og.status','in',[1,2,3])->join('restaurant_takeaway_order o','o.id=og.orderid')->join('member m','m.id=og.mid')->where($bwhere)->order('og.id desc')->select()->toArray();
                    if($takeaway_oglist){
                        foreach($takeaway_oglist as $twk=>$twv){
                            $twv['module'] = 'takeaway';
                            $oglist[]      = $twv;
                        }
                        unset($twv);
                    }
                }
                if(getcustom('fenhong_times_coupon',$aid)){
                    $cwhere[] =['og.isfenhong','=',0];
                    $cwhere[] =['og.status','=',1];
                    $cwhere[] =['og.paytime','>=',$starttime];
                    $cwhere[] =['og.paytime','<',$endtime];
                    if($sysset['fhjiesuanbusiness'] != 1){ //多商户的商品是否参与分红
                        $cwhere[] =['og.bid','=',0];
                    }
                    $couponorderlist = Db::name('coupon_order')->alias('og')
                        ->join('member m','m.id=og.mid')
                        ->where($cwhere)
                        ->field('og.*,m.nickname,m.headimg')
                        ->order('og.id desc')
                        ->select()
                        ->toArray();
                    foreach($couponorderlist as $k=>$v){
                        $v['name'] = $v['title'];
                        $v['real_totalprice'] = $v['price'];
                        $v['cost_price'] = 0;
                        $v['module'] = 'coupon';
                        $v['num'] = 1;
                        $oglist[] = $v;
                    }
                }
                if(getcustom('fenhong_kecheng',$aid)){
                    $kwhere = [];
                    $kwhere[] = ['og.aid','=',$aid];
                    $kwhere[] = ['og.isfenhong','=',0];
                    $kwhere[] = ['og.status','=',1];
                    $kwhere[] = ['og.paytime','>=',$starttime];
                    $kwhere[] = ['og.paytime','<',$endtime];
                    if($sysset['fhjiesuanbusiness'] != 1){
                        $kwhere[] = ['og.bid','=','0'];
                    }
                    $kechenglist = Db::name('kecheng_order')
                        ->alias('og')
                        ->join('member m','m.id=og.mid')
                        ->where($kwhere)
                        ->field('og.*," " as area2,m.nickname,m.headimg')
                        ->select()
                        ->toArray();
                    if($kechenglist){
                        foreach($kechenglist as $v){
                            $v['name']            = $v['title'];
                            $v['real_totalprice'] = $v['totalprice'];
                            $v['cost_price']      = 0;
                            $v['module']          = 'kecheng';
                            $v['num']             = 1;
                            $oglist[]             = $v;
                        }
                    }
                }
            }
            if(!$oglist) return ['commissionyj'=>0,'oglist'=>[]];
            //参与股东分红的等级
            $fhlevellist = Db::name('member_level')->where('aid',$aid)->where('fenhong_huiben','>','0')->order('sort asc,id asc')->column('*','id');
            if(!$fhlevellist) return ['commissionyj'=>0,'oglist'=>[]];

            $defaultCid = Db::name('member_level_category')->where('aid',$aid)->where('isdefault', 1)->value('id');
            if($defaultCid) {
                $defaultLevelIds = Db::name('member_level')->where('aid',$aid)->where('cid', $defaultCid)->column('id');
            } else {
                $defaultLevelIds = Db::name('member_level')->where('aid',$aid)->column('id');
            }

            $ogids = [];
            $midfhArr = [];
            $newoglist = [];
            $commissionyj = 0;
            $allfenhongprice = 0;
            foreach($oglist as $og){
                $levelid_only = 0;
                if(getcustom('commission2moneypercent',$aid) && $sysset['commission2moneypercent1'] > 0){
                    //是否是首单
                    $beforeorder = Db::name('shop_order')->where('aid',$aid)->where('mid',$og['mid'])->where('status','in','1,2,3')->where('paytime','<',$og['paytime'])->find();
                    if(!$beforeorder){
                        $commissionpercent = 1 - $sysset['commission2moneypercent1'] * 0.01;
                        $moneypercent = $sysset['commission2moneypercent1'] * 0.01;
                    }else{
                        $commissionpercent = 1 - $sysset['commission2moneypercent2'] * 0.01;
                        $moneypercent = $sysset['commission2moneypercent2'] * 0.01;
                    }
                }else{
                    $commissionpercent = 1;
                    $moneypercent = 0;
                }
                if($sysset['fhjiesuantype'] == 0){
                    $fenhongprice = $og['real_totalprice'];
                }else{
                    $fenhongprice = $og['real_totalprice'] - $og['cost_price'] * $og['num'];
                }
                if(getcustom('baikangxie',$aid)){
                    $fenhongprice = $og['cost_price'] * $og['num'];
                }
                if($fenhongprice <= 0) continue;
                $ogids[] = $og['id'];

                $ogids[] = $og['id'];
                $allfenhongprice = $allfenhongprice + $fenhongprice;
                if($fhlevellist){
                    $lastmidlist = [];
                    foreach($fhlevellist as $fhlevel){
                        $where = [];
                        $where[] = ['aid', '=', $aid];
                        $where[] = ['levelid', '=', $fhlevel['id']];
                        $where[] = ['levelstarttime', '<', $og['createtime']]; //判断升级时间
                        if($fhlevel['fenhong_max_money_huiben'] > 0) {
                            $where[] = ['total_fenhong_huiben', '<', $fhlevel['fenhong_max_money_huiben']];
                        }
                        $field = 'id,total_fenhong_huiben,levelstarttime,levelid';
                        $midlist = Db::name('member')->where($where)->column($field,'id');
                        if($midlist){
                            foreach ($midlist as $mk => $memberarr){
                                //购买前最后一条升级记录，如果下单前等级不等于当前等级 则排除（当前等级不断变化，不是完全准确，所以需要对照升级记录表）
                                $levelup_last_log = Db::name('member_levelup_order')->where('aid',$aid)->where('status', 2)
                                    ->where('levelup_time', '<', $og['createtime'])->where('mid',$memberarr['id'])->order('levelup_time', 'desc')->find();
                                if($levelup_last_log && $levelup_last_log['levelid'] != $memberarr['levelid'] && $levelup_last_log['sort']<$fhlevel['sort']){
                                    //后台操作把高级别降到了低级别的情况要保留
                                    $last_level = $fhlevellist[$levelup_last_log['levelid']];
                                    if($last_level['sort']<$fhlevel['sort']){
                                        unset($midlist[$mk]);
                                    }
                                }
                            }
                        }
                        if(!$midlist) continue;
                        $pergxcommon = 0;

                        $totalcommission = 0;
                        $totalscore = 0;
                        if($og['module'] == 'yuyue'){
                            $product = Db::name('yuyue_product')->where('id',$og['proid'])->find();
                        }elseif($og['module'] == 'luckycollage'){
                            $product = Db::name('lucky_collage_product')->where('id',$og['proid'])->find();
                        }elseif($og['module'] == 'coupon'){
                            $product = Db::name('coupon')->where('id',$og['cpid'])->find();
                        }elseif($og['module'] == 'scoreshop'){
                            $product = Db::name('scoreshop_product')->where('id',$og['proid'])->find();
                        }elseif($og['module'] == 'kecheng'){
                            $product = Db::name('kecheng_list')->where('id',$og['kcid'])->find();
                        }else{
                            $product = Db::name('shop_product')->where('id',$og['proid'])->find();
                        }
                        if(getcustom('maidan_fenhong',$aid) || getcustom('maidan_fenhong_new',$aid)){
                            if($og['module'] == 'maidan'){
                                $product = [];
                                $product['gdfenhongset'] = 0;
                            }
                        }
                        if(getcustom('restaurant_fenhong',$aid)){
                            if($og['module'] == 'diancan' || $og['module'] == 'takeaway'){
                                $product = [];
                                $product['gdfenhongset'] = 0;
                            }
                        }
                        if($product['gdfenhongset_huiben']==1){//按比例
                            $fenhongdata = json_decode($product['gdfenhongdata1_huiben'],true);
                            if($fenhongdata){
                                $totalcommission = $fenhongdata[$fhlevel['id']]['commission'] * $fenhongprice * 0.01;
                            }
                        }elseif($product['gdfenhongset_huiben']==2){//按固定金额
                            $fenhongdata = json_decode($product['gdfenhongdata2_huiben'],true);
                            if($fenhongdata){
                                $totalcommission = $fenhongdata[$fhlevel['id']]['commission'] * $og['num'];
                            }
                        }elseif($product['gdfenhongset_huiben'] == 0){
                            $totalcommission = $fhlevel['fenhong_huiben'] * $fenhongprice * 0.01;
                        }
                        if(getcustom('fenhong_removefenxiao',$aid) && $fhlevel['gdfenhong_removefenxiao'] == 1){
                            if($og['parent1'] && $og['parent1commission']){
                                $totalcommission = $totalcommission - $og['parent1commission'];
                            }
                            if($og['parent2'] && $og['parent2commission']){
                                $totalcommission = $totalcommission - $og['parent2commission'];
                            }
                            if($og['parent3'] && $og['parent3commission']){
                                $totalcommission = $totalcommission - $og['parent3commission'];
                            }
                            if($totalcommission <= 0) continue;
                        }

                        if($totalcommission == 0 && $totalscore==0) continue;

                        $commission = $totalcommission / count($midlist);
                        $score = floor($totalscore / count($midlist));

                        if(!$midfhArr['level_'.$fhlevel['id']]) $midfhArr['level_'.$fhlevel['id']] = [];
                        $newcommission = 0;

                        foreach($midlist as $k=>$item){
                            $fenhong_max_money = $fhlevel['fenhong_max_money_huiben'];
                            $mid = $item['id'];
                            //查询上一级别的封顶值
                            $last_fenhong_max_money = Db::name('fenhong_huiben')->where('mid',$mid)->where('level_sort','<',$fhlevel['sort'])->value('max');
                            if($last_fenhong_max_money>0){
                                $fenhong_max_money = bcsub($fenhong_max_money,$last_fenhong_max_money,2);
                            }
                            //已获得分红
                            $total_fenhong_huiben =  Db::name('fenhong_huiben')->where('mid',$mid)->where('levelid',$fhlevel['id'])->value('fenhong');
                            $item['total_fenhong_huiben'] = $total_fenhong_huiben;
                            if($isyj == 1 && $mid == $yjmid && $commission > 0){
                                $commissionyj += $commission;
                                $og['commission'] = round($commission,2);
                                $og['fhname'] = t('回本股东分红',$aid);
                                $newoglist[] = $og;
                                break;
                            }
                            $gxcommon = 0;
                            $newcommission = $commission + $gxcommon;
                            if($midfhArr['level_'.$fhlevel['id']][$mid]){

                                if($fenhong_max_money > 0) {
                                    if($midfhArr['level_'.$fhlevel['id']][$mid]['totalcommission'] + $newcommission + $item['total_fenhong_huiben'] >$fenhong_max_money) {
                                        //Log::write('大于最大分红金额'.$commission);
                                        $newcommission = $fenhong_max_money - $midfhArr['level_'.$fhlevel['id']][$mid]['totalcommission'] - $item['total_fenhong_huiben'];
                                    }
                                }
                                if($commissionpercent != 1){
                                    $fenhongcommission = round($newcommission*$commissionpercent,2);
                                    $fenhongmoney = round($newcommission*$moneypercent,2);
                                }else{
                                    $fenhongcommission = $newcommission;
                                    $fenhongmoney = 0;
                                }
                                $midfhArr['level_'.$fhlevel['id']][$mid]['totalcommission'] = $midfhArr['level_'.$fhlevel['id']][$mid]['totalcommission'] + $newcommission;
                                $midfhArr['level_'.$fhlevel['id']][$mid]['commission'] = $midfhArr['level_'.$fhlevel['id']][$mid]['commission'] + $fenhongcommission;
                                $midfhArr['level_'.$fhlevel['id']][$mid]['money'] = $midfhArr['level_'.$fhlevel['id']][$mid]['money'] + $fenhongmoney;
                                $midfhArr['level_'.$fhlevel['id']][$mid]['score'] = $score;
                                $midfhArr['level_'.$fhlevel['id']][$mid]['ogids'][] = $og['id'];
                            }else{
                                if($fenhong_max_money > 0) {
                                    if($newcommission + $item['total_fenhong_huiben'] > $fenhong_max_money) {
                                        $newcommission = $fenhong_max_money - $item['total_fenhong_huiben'];
                                    }
                                }
                                if($commissionpercent != 1){
                                    $fenhongcommission = round($newcommission*$commissionpercent,2);
                                    $fenhongmoney = round($newcommission*$moneypercent,2);
                                }else{
                                    $fenhongcommission = $newcommission;
                                    $fenhongmoney = 0;
                                }
                                $midfhArr['level_'.$fhlevel['id']][$mid] = [
                                    'totalcommission'=>$newcommission,
                                    'commission'=>$fenhongcommission,
                                    'money'=>$fenhongmoney,
                                    'score'=>$score,
                                    'ogids'=>[$og['id']],
                                    'module'=>$og['module'] ?? 'shop',
                                ];
                                
                            }
                            if(getcustom('commission_orderrefund_deduct',$aid) && $isyj == 0){
                                self::fhrecord($aid,$mid,$fenhongcommission,$score,$og['id'],$og['module'] ?? 'shop','fenhong_huiben',t('回本股东分红',$aid));
                            }
                            if($newcommission>0 && $isyj == 0){
                                //插入回本股东分红级别记录，用于计算封顶值级差
                                $exit = Db::name('fenhong_huiben')->where('aid',$aid)->where('mid',$mid)->where('levelid',$fhlevel['id'])->find();
                                if($exit){
                                    $fh_log = [];
                                    $fh_log['fenhong'] = bcadd($exit['fenhong'],$newcommission,2);
                                    $fh_log['max'] = $fhlevel['fenhong_max_money_huiben'];
                                    $fh_log['update_time'] = time();
                                    Db::name('fenhong_huiben')->where('id',$exit['id'])->update($fh_log);
                                }else{
                                    $fh_log = [];
                                    $fh_log['aid'] = $aid;
                                    $fh_log['mid'] = $mid;
                                    $fh_log['levelid'] = $fhlevel['id'];
                                    $fh_log['level_sort'] = $fhlevel['sort'];
                                    $fh_log['fenhong'] = $newcommission;
                                    $fh_log['update_time'] = time();
                                    $fh_log['max'] = $fhlevel['fenhong_max_money_huiben'];
                                    Db::name('fenhong_huiben')->insert($fh_log);
                                }
                            }

                        }
                    }
                }
                if($isyj == 0 && $sysset['fhjiesuanhb'] == 0) {
                    if($midfhArr){
                        foreach($midfhArr as $levelstr=>$midfhArr2){
                            $levelid = explode('_',$levelstr)[1];
                            $remark = t('回本股东分红',$aid);
                            self::fafang($aid,$midfhArr2,'fenhong_huiben',$remark);
                        }
                        //根据分红奖团队收益
                        if(getcustom('teamfenhong_shouyi',$aid)){
                            self::teamshouyi($aid,$sysset,$midfhArr,$oglist,$isyj,$yjmid,$commissionpercent,$moneypercent);
                        }
                    }
                    $midfhArr = [];
                }
            }

            if($isyj == 0 && $sysset['fhjiesuanhb'] == 1) {
                if($midfhArr){
                    foreach($midfhArr as $levelstr=>$midfhArr2){
                        $levelid = explode('_',$levelstr)[1];
                        $remark = t('回本股东分红',$aid);
                        self::fafang($aid,$midfhArr2,'fenhong_huiben',$remark);
                    }
                    //根据分红奖团队收益
                    if(getcustom('teamfenhong_shouyi',$aid)){
                        self::teamshouyi($aid,$sysset,$midfhArr,$oglist,$isyj,$yjmid,$commissionpercent,$moneypercent);
                    }
                }
            }
            if($isyj == 1){
                //计算团队收益预收益
                if(getcustom('teamfenhong_shouyi',$aid)){
                    self::teamshouyi($aid,$sysset,$midfhArr,$oglist,$isyj,$yjmid,$commissionpercent,$moneypercent);
                    if(!empty($res_lvyou['commissionyj']) && $res_lvyou['commissionyj']>0){
                        $res_lvyou_commissionyj = $res_lvyou['commissionyj']??0;
                        $commissionyj = bcadd($commissionyj,$res_lvyou_commissionyj,2);
                    }
                }
                return ['commissionyj'=>round($commissionyj,2),'oglist'=>$newoglist];
            }
        }

    }

    //加权分红结算
    static public function JiesuanJiaquanFenhongByDay($aid='',$date='',$iszx=false)
    {
        if (getcustom('fenhong_jiaquan_bylevel')) {
            $amap = [];
            if($aid){
                $amap[] = ['aid','=',$aid];
            }
            if($date){
                $curtime = strtotime($date);
            }else{
                $curtime = time();
            }
            writeLog('-----------jiaquan: '.date('Ymd H:i:s',$curtime).'---------');
            $adminsetlist = Db::name('admin_set')->where('fenhong_jqjs_rate', '>', 0)->where($amap)->field('id,aid,fenhong_jqjs_time,fenhong_jqjs_rate')->select()->toArray();
            foreach ($adminsetlist as $k => $adminset) {
                $totalprice = 0;
                $aid = $adminset['aid'];
                //如果未设置，则每天1点执行结算
                $rate = $adminset['fenhong_jqjs_rate'];
                if(empty($rate) || $rate<=0){
                    continue;
                }
                $time = $adminset['fenhong_jqjs_time'] ? intval($adminset['fenhong_jqjs_time']) : '1';
                $zxtime = $curtime+$time*3600;//执行时间
                if (!$iszx && intval(date('H')) < $time) {
                    continue;
                }
                $done = Db::name('fenhong_jiaquan_log')->where('aid',$aid)->where('date',date('Ymd',$curtime))->find();
                if(!$iszx && $done){
                    continue;
                }
                $jiesuanEndtime = strtotime(date('Y-m-d 00:00:00', $curtime));//上一天结束时间
                $jiesuanStarttime = $jiesuanEndtime-86400;

                $jisuandate = date('Ymd',$jiesuanEndtime-10);
                //所有的等级份数
                $totalCopies = Db::name('member')
                    ->where('aid', $aid)
                    ->where('fhcopies', '<>',0)
                    ->sum('fhcopies');
                if (empty($totalCopies) || $totalCopies<0) {
                    continue;
                }
                $js_type = $adminset['fhjiesuantime_type']??0;
                $logicType = 0;
                //总金额=商城金额+ 收银台金额 + 门店金额
                if(getcustom('fenhong_jiaquan_copies')){
                    $logicType = 1;//二次定制，会员等级的订单统计时间
                    //按会员等级的有效日期汇总金额
                    $mendianTotalprice = Db::name('mendian_shop_order')->where('aid', $aid)->where('status', 0)->where('date', $jisuandate)->sum('totalprice');
                    writeLog('门店总金额0：'.$mendianTotalprice);
                    $totalprice = round($totalprice+$mendianTotalprice,2);
                    //统计开始
                    $awhere = $effectWhere= [];
                    $awhere[] = ['aid','=',$aid];
                    if($js_type==1){
                        $afield = 'paytime';
                        $awhere[] = ['status','in',[1,2,3]];
                    }else{
                        $afield = 'collect_time';
                        $awhere[] = ['status','=',3];
                    }
                    $awhere[] = [$afield,'between',[$jiesuanStarttime,$jiesuanEndtime]];
                    //商城
                    $shopTotalpriceTmp = round(Db::name('shop_order')->where($awhere)->sum('totalprice'),2);
                    //收银台
                    $cashierTotalpriceTmp = round(Db::name('cashier_order')->where('aid', $aid)->where('status', '=', 1)->where('paytime', 'between',[$jiesuanStarttime,$jiesuanEndtime])->sum('totalprice'),2);
                    //买单
                    $maidanTotalprice = round(Db::name('maidan_order')->where('aid', $aid)->where('status', 1)->where('paytime', 'between',[$jiesuanStarttime,$jiesuanEndtime])->sum('paymoney'),2);
                    //外卖
                    $restaurantTakeawayTotalpriceTmp = round(Db::name('restaurant_takeaway_order')->where($awhere)->sum('totalprice'),2);
                    //点餐
                    $restaurantShopTotalpriceTmp = round(Db::name('restaurant_shop_order')->where($awhere)->sum('totalprice'),2);
                    //非全额退款后关闭的订单
                    //预约
                    $yuyueTotalpriceTmp = round(Db::name('yuyue_order')->where($awhere)->sum('totalprice'),2);
                    $totalprice = round($totalprice + $shopTotalpriceTmp + $cashierTotalpriceTmp + $restaurantShopTotalpriceTmp + $restaurantTakeawayTotalpriceTmp + $yuyueTotalpriceTmp + $maidanTotalprice,2);
                    writeLog('商城总金额：'.$shopTotalpriceTmp);
                    writeLog('收银台总金额：'.$cashierTotalpriceTmp);
                    writeLog('买单总金额：'.$maidanTotalprice);
                    writeLog('点餐总金额：'.$restaurantShopTotalpriceTmp);
                    writeLog('外卖总金额：'.$restaurantTakeawayTotalpriceTmp);
                    writeLog('预约总金额：'.$yuyueTotalpriceTmp);
                }
                if($logicType==0){
                    //确认收货的【已完成的】
                    $shopTotalprice = Db::name('shop_order_goods')->where('aid', $aid)->where('endtime', 'between',[$jiesuanStarttime,$jiesuanEndtime])->where('status', 'in', [3])->sum('totalprice');
                    $cashierTotalprice = Db::name('cashier_order')
                        ->where('aid', $aid)->where('status', 1)->where('paytime', 'between',[$jiesuanStarttime,$jiesuanEndtime])->sum('totalprice');
                    $mendianTotalprice = Db::name('mendian_shop_order')->where('aid', $aid)->where('status', 0)->where('date', $jisuandate)->sum('totalprice');
                    writeLog('商城总金额：'.$shopTotalprice);
                    writeLog('收银台总金额：'.$cashierTotalprice);
                    writeLog('门店总金额：'.$mendianTotalprice);
                    $totalprice = round($shopTotalprice + $cashierTotalprice + $mendianTotalprice, 2);
                }
                $jiesuanTotalprice = round($totalprice * $rate * 0.01,2);
                writeLog('平台总金额：'.$totalprice);
                writeLog('结算总金额：'.$jiesuanTotalprice);
                writeLog('平台总份数：'.$totalCopies);
                //计算每一份的金额
                $oneCopiePrice = $jiesuanTotalprice / $totalCopies;
                writeLog('每份数金额：'.$oneCopiePrice);
                //会员数据发放
                $mfield = 'm.id,m.fhcopies,m.aid,m.levelid';
                if(getcustom('fenhong_jiaquan_copies')) {
                    $mfield.=',l.fenhong_jiaquan_maxmoney,l.fenhong_jiaquan_maxmoney';
                }
                $memberlist = Db::name('member')->alias('m')->join('member_level l','m.levelid=l.id')->where('m.aid', $aid)->where('m.fhcopies', '>',0)->field($mfield)->select()->toArray();
                foreach ($memberlist as $k => $member) {
                    $memberTotalCopies = $member['fhcopies'];
                    writeLog('会员mid='.$member['id'].'&份数='.$memberTotalCopies);
                    //会员获得份数 * 一份多少钱
                    $fenhongMoney = round($memberTotalCopies * $oneCopiePrice, 2);
                    if ($fenhongMoney > 0) {
                        //设置了分红上限
                        if(getcustom('fenhong_jiaquan_copies')) {
                            if ($member['levelid']>0 && $member['fenhong_jiaquan_maxmoney']!=-1){
                                $fenhongMaxmoney = $member['fenhong_jiaquan_maxmoney']??0;
                                $logWhere = [];
                                $logWhere[] = ['aid','=',$aid];
                                $logWhere[] = ['mid','=',$member['id']];
                                $logWhere[] = ['module','=','shop_jiaquan'];
                                $logWhere[] = ['type','=','copies_fenhong'];
                                $alreadyGetFenhongMoney = round(Db::name('member_fenhonglog')->where($logWhere)->sum('commission'),2);
                                //超过分红限制不在发放
                                if($alreadyGetFenhongMoney>=$fenhongMaxmoney){
                                    writeLog('mid='.$member['id'].'超过分红限制不在发放'.'，上限:'.$member['fenhong_jiaquan_maxmoney'].'已发放:'.$alreadyGetFenhongMoney);
                                    continue;
                                }
                                //剩余额度小于当前将要发放的分红
                                $remainFenhongMoney  = round($fenhongMaxmoney - $alreadyGetFenhongMoney,2);
                                if($remainFenhongMoney<$fenhongMoney){
                                    $fenhongMoney = $remainFenhongMoney;
                                }
                            }
                        }
                        writeLog('会员mid='.$member['id'].'&份数='.$memberTotalCopies.'&money='.$fenhongMoney);
                        $remark = $jisuandate . '加权分红结算';
                        \app\commons\Member::addcommission($aid, $member['id'], 0, $fenhongMoney, $remark);
                        Db::name('member_commission_record')->insert(['aid'=>$aid,'mid'=>$member['id'],'frommid'=>0,'orderid'=>0,'ogid'=>0,'type'=>'fenhong_copies','commission'=>$fenhongMoney,'copies'=>$memberTotalCopies,'remark'=>$remark,'createtime'=>time(),'status'=>1]);
                        $fhdata = [];
                        $fhdata['aid'] = $aid;
                        $fhdata['mid'] = $member['id'];
                        $fhdata['commission'] = $fenhongMoney;
                        $fhdata['remark'] = $remark;
                        $fhdata['type'] = 'copies_fenhong';
                        $fhdata['createtime'] = $zxtime;
                        $fhdata['copies'] = $memberTotalCopies;
                        $fhdata['module'] = 'shop_jiaquan';
                        Db::name('member_fenhonglog')->insert($fhdata);
                    }
                }
                Db::name('member_fenhong_jiaquan')->where('aid', $aid)->where('status',1)->where('effect_time', '<', $jiesuanEndtime)->update(['status'=>2,'jiesuan_time'=>$zxtime]);
                Db::name('mendian_shop_order')->where('aid', $aid)->where('status', 0)->where('date', $jisuandate)->update(['status'=>1]);
                Db::name('fenhong_jiaquan_log')->insert(['aid'=>$aid,'date'=>date('Ymd',$curtime),'createtime'=>$zxtime]);
            }
        }
    }
    //加权分红状态修改
    static public function updateJiaquanCopies2member($orderid)
    {
        if(getcustom('fenhong_jiaquan_bylevel')) {
            $copielist = Db::name('member_fenhong_jiaquan')->where('orderid', $orderid)->where('status', 0)->field('aid,sum(copies) totalcopies,mid,remark')->group('mid')->select()->toArray();
            Db::name('member_fenhong_jiaquan')->where('orderid', $orderid)->where('status', 0)->update(['status' => 1, 'effect_time' => time()]);
            foreach ($copielist as $k => $item) {
                \app\commons\Member::addfhcopies($item['aid'], $item['mid'], $item['totalcopies'], $item['remark']);
            }
        }
    }

    //商家推荐人团队分红
    public static function business_teamfenhong($aid,$sysset,$oglist,$starttime=0,$endtime=0,$isyj=0,$yjmid=0){
        if(getcustom('business_teamfenhong',$aid)){
            if($endtime == 0) $endtime = time();
            if($isyj == 1 && !$oglist){
                $bwhere = [['og.bid','<>','0']];
                $oglist = Db::name('shop_order_goods')->alias('og')->field('og.*,o.area2,m.nickname,m.headimg')->where('og.aid',$aid)->where('og.isfenhong',0)->where('og.status','in',[1,2,3])->where('og.refund_num',0)->join('shop_order o','o.id=og.orderid')->join('member m','m.id=og.mid')->where($bwhere)->order('og.id desc')->select()->toArray();
                if(!$oglist) $oglist = [];

                //买单分红
                $maidan_orderlist = Db::name('maidan_order')
                    ->alias('og')
                    ->join('member m','m.id=og.mid')
                    ->where('og.aid',$aid)
                    ->where('og.isfenhong',0)
                    ->where('og.status',1)
                    ->where($bwhere)
                    ->field('og.*,m.nickname,m.headimg')
                    ->order('og.id desc')
                    ->select()
                    ->toArray();
                if($maidan_orderlist){
                    foreach($maidan_orderlist as $mdk=>$mdv){
                        $mdv['name']             = $mdv['title'];
                        $mdv['real_totalprice']  = $mdv['paymoney'];
                        $mdv['cost_price']       = 0;
                        $mdv['num']              = 1;
                        $mdv['module']           = 'maidan';
                        $oglist[] = $mdv;
                    }
                }
            }
//        dump($oglist);
            //参与商家推荐人团队分红的等级
            $teamfhlevellist = Db::name('member_level')->where('aid',$aid)->where('business_teamfenhonglv','>','0')->column('*','id');
//      dump($teamfhlevellist);
            if(!$teamfhlevellist) return ['commissionyj'=>0,'oglist'=>[]];

            if(!$oglist) return ['commissionyj'=>0,'oglist'=>[]];

            $defaultCid = Db::name('member_level_category')->where('aid',$aid)->where('isdefault', 1)->value('id');
            if($defaultCid) {
                $defaultLevelIds = Db::name('member_level')->where('aid',$aid)->where('cid', $defaultCid)->column('id');
            } else {
                $defaultLevelIds = Db::name('member_level')->where('aid',$aid)->column('id');
            }
            $ogids = [];
            $midteamfhArr = [];
            $newoglist = [];
            $commissionyj = 0;

            foreach($oglist as $og){
                $commissionyj_my = 0;
                $commissionpercent = 1;
                $moneypercent = 0;

//                if($sysset['fhjiesuantype'] == 0){
//                    $fenhongprice = $og['real_totalprice'];
//                }else{
//                    $fenhongprice = $og['real_totalprice'] - $og['cost_price'] * $og['num'];
//                }
                $feepercent = Db::name('business')->where('id',$og['bid'])->value('feepercent');
                $fenhongprice = bcmul($og['real_totalprice'],$feepercent/100,2);

                if($fenhongprice <= 0) continue;
                $ogids[] = $og['id'];
                $uinfo = Db::name('admin_user')->where('aid',$aid)->where('bid',$og['bid'])->where('isadmin',1)->find();
                $member = Db::name('member')->where('id',$uinfo['mid'])->field('id,pid,levelid,path,path_origin')->find();
                if($teamfhlevellist){
                    //判断脱离时间
                    if($member['change_pid_time'] && $member['change_pid_time'] >= $og['createtime']){
                        $pids = $member['path_origin'];
                    }else{
                        $pids = $member['path'];
                    }

                    if($pids){
                        $parentList = Db::name('member')->where('id','in',$pids)->order(Db::raw('field(id,'.$pids.')'))->select()->toArray();
                        $parentList = array_reverse($parentList);
                        $hasfhlevelids = [];
                        //层级判断，如购买人等级未开启“包含自己teamfenhong_self“则购买人的上级为第一级，开启了则购买人为第一级
                        $level_i = 0;
                        $haspingjinumArr = [];
                        foreach($parentList as $k=>$parent){
                            //判断升级时间
                            $leveldata = $teamfhlevellist[$parent['levelid']];
                            if($parent['levelstarttime'] >= $og['createtime']) {
                                $levelup_order_levelid = Db::name('member_levelup_order')->where('aid',$aid)->where('mid', $parent['id'])->where('status', 2)
                                    ->where('levelup_time', '<', $og['createtime'])->whereIn('levelid',$defaultLevelIds)->order('levelup_time', 'desc')->value('levelid');
                                if($levelup_order_levelid) {
                                    $parent['levelid'] = $levelup_order_levelid;
                                    $leveldata = $teamfhlevellist[$parent['levelid']];
                                }else{
                                    //不包含自己跳过
                                    unset($parentList[$k]);
                                    continue;
                                }
                            }

                            $level_i++;
//                            if($parent['id'] == $og['mid']) { $level_i--; unset($parentList[$k]);continue;}//不包含自己则层级-1
                            if(!$leveldata || $level_i>$leveldata['business_teamfenhonglv']) continue;

                            $hasfhlevelids[] = $parent['levelid'];
                            $totalfenhongmoney = 0;

                            //分红比例
                            if($leveldata['business_teamfenhongbl'] > 0) {
                                $this_teamfenhongbl = $leveldata['business_teamfenhongbl'];
                                $totalfenhongmoney =  $this_teamfenhongbl * $fenhongprice * 0.01;
                            }

//                        var_dump('$totalfenhongmoney:'.$totalfenhongmoney);
                            if($totalfenhongmoney > 0 ){
                                if($isyj == 1 && $yjmid == $parent['id']){
                                    $commissionyj_my += $totalfenhongmoney;
                                }
                                if($commissionpercent != 1){
                                    $fenhongcommission = round($totalfenhongmoney*$commissionpercent,2);
                                    $fenhongmoney = round($totalfenhongmoney*$moneypercent,2);
                                }else{
                                    $fenhongcommission = $totalfenhongmoney;
                                    $fenhongmoney = 0;
                                }
                                if($midteamfhArr[$parent['id']]){
                                    $midteamfhArr[$parent['id']]['totalcommission'] = $midteamfhArr[$parent['id']]['totalcommission'] + $totalfenhongmoney;
                                    $midteamfhArr[$parent['id']]['commission'] = $midteamfhArr[$parent['id']]['commission'] + $fenhongcommission;
                                    $midteamfhArr[$parent['id']]['money'] = $midteamfhArr[$parent['id']]['money'] + $fenhongmoney;
                                    $midteamfhArr[$parent['id']]['score'] = $midteamfhArr[$parent['id']]['score'] + 0;
                                    $midteamfhArr[$parent['id']]['ogids'][] = $og['id'];
                                    $midteamfhArr[$parent['id']]['levelid'] = $parent['levelid'];
                                }else{
                                    $midteamfhArr[$parent['id']] = [
                                        'totalcommission'=>$totalfenhongmoney,
                                        'commission'=>$fenhongcommission,
                                        'money'=>$fenhongmoney,
                                        'score'=>0,
                                        'ogids'=>[$og['id']],
                                        'module'=>$og['module'] ?? 'shop',
                                        'levelid' => $parent['levelid'],
                                        'type' => '商家团队分红',
                                    ];
                                }
                                if(getcustom('commission_orderrefund_deduct',$aid) && $isyj == 0){
                                    self::fhrecord($aid,$parent['id'],$fenhongcommission,0,$og['id'],$og['module'] ?? 'shop','business_teamfenhong',t('商家团队分红',$aid));
                                }
                            }
                            //平级奖 找最近的上级
                            if(getcustom('business_teamfenhong_pj',$aid)){
                                //teamfenhong_pingji_type:0按奖励金额,1按订单金额
                                $last_teamfenhongbl_pj = 0;
                                $levelSort = [];
                                if($leveldata['business_teamfenhonglv_pj']>0 && $leveldata['business_teamfenhongbl_pj']>0 ){
                                    $last_pingji_levelid = $parent['levelid'];//上一个拿平级奖的会员id
                                    foreach($parentList as $k2=>$parent2){
                                        //dump($parent2['id'].'平级开始');
                                        $parent2Level = $teamfhlevellist[$parent2['levelid']];
                                        $levelSort[] = $parent2Level['sort'];
                                        if($k2 > $k && $parent2['levelid'] == $parent['levelid']){
                                            //暂时关闭 等级比例优先级低于商品独立设置
                                            $this_teamfenhongbl_pj = $leveldata['business_teamfenhongbl_pj'];
                                            if($this_teamfenhongbl_pj <=0) $this_teamfenhongbl_pj = 0;
                                            if($this_teamfenhongbl_pj == 0 ) continue;

                                            if($haspingjinumArr[$parent['levelid']]){
                                                $haspingjinumArr[$parent['levelid']]++;
                                            }else{
                                                $haspingjinumArr[$parent['levelid']] = 1;
                                            }
                                            if($haspingjinumArr[$parent['levelid']] > $leveldata['business_teamfenhonglv_pj']) break;
                                            $totalfenhongmoney_pj = 0;
                                            if($this_teamfenhongbl_pj>0){
                                                if($leveldata['business_teamfenhong_pingji_type'] == 0){
                                                    //按奖励金额
                                                    $totalfenhongmoney_pj += $this_teamfenhongbl_pj * $totalfenhongmoney * 0.01;
                                                }else{
                                                    //按订单金额
                                                    $totalfenhongmoney_pj += $this_teamfenhongbl_pj * $fenhongprice * 0.01;
                                                }
                                            }
                                            if($totalfenhongmoney_pj > 0 ){
                                                if($isyj == 1 && $yjmid == $parent2['id']){
                                                    $commissionyj_my += $totalfenhongmoney_pj;
                                                }
                                                if($commissionpercent != 1){
                                                    $fenhongcommission = round($totalfenhongmoney_pj*$commissionpercent,2);
                                                    $fenhongmoney = round($totalfenhongmoney_pj*$moneypercent,2);
                                                }else{
                                                    $fenhongcommission = $totalfenhongmoney_pj;
                                                    $fenhongmoney = 0;
                                                }

                                                if($midteamfhArr[$parent2['id']]){
                                                    $midteamfhArr[$parent2['id']]['totalcommission'] = $midteamfhArr[$parent2['id']]['totalcommission'] + $totalfenhongmoney_pj;
                                                    $midteamfhArr[$parent2['id']]['commission'] = $midteamfhArr[$parent2['id']]['commission'] + $fenhongcommission;
                                                    $midteamfhArr[$parent2['id']]['money'] = $midteamfhArr[$parent2['id']]['money'] + $fenhongmoney;
                                                    $midteamfhArr[$parent2['id']]['score'] = $midteamfhArr[$parent2['id']]['score'] + 0;
                                                    $midteamfhArr[$parent2['id']]['ogids'][] = $og['id'];
                                                    $midteamfhArr[$parent2['id']]['levelid'] = $parent2['levelid'];
                                                }else{
                                                    $midteamfhArr[$parent2['id']] = [
                                                        'totalcommission'=>$totalfenhongmoney_pj,
                                                        'commission'=>$fenhongcommission,
                                                        'money'=>$fenhongmoney,
                                                        'score'=>0,
                                                        'ogids'=>[$og['id']],
                                                        'module'=>$og['module'] ?? 'shop',
                                                        'levelid' => $parent2['levelid']
                                                    ];
                                                }
                                                if(getcustom('commission_orderrefund_deduct',$aid) && $isyj == 0){
                                                    self::fhrecord($aid,$parent2['id'],$fenhongcommission,0,$og['id'],$og['module'] ?? 'shop','business_teamfenhong',t('商家团队分红',$aid));
                                                }
                                                $last_pingji_levelid = $parent2['levelid'];
                                                //dump($parent2['id'].'获得平级奖'.$fenhongcommission);
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
//            dump($midteamfhArr);
                if($isyj == 1 && $commissionyj_my > 0){
                    $commissionyj += $commissionyj_my;
                    $og['commission'] = round($commissionyj_my,2);
                    $og['fhname'] = t('商家团队分红',$aid);
                    $newoglist[] = $og;
                }

                if($isyj == 0 && $sysset['fhjiesuanhb'] == 0) {
                    self::fafang($aid,$midteamfhArr,'business_teamfenhong',t('商家团队分红',$aid));
                    $midteamfhArr = [];
                }
            }
            //die('stop');
            if($isyj == 1){
                return ['commissionyj'=>round($commissionyj,2),'oglist'=>$newoglist];
            }
            if($isyj == 0 && $sysset['fhjiesuanhb'] == 1) {
                self::fafang($aid,$midteamfhArr,'business_teamfenhong',t('商家团队分红',$aid));
            }
        }

    }
    /**
     * 团队培育奖
     * 拿下级佣金收益的百分比
     */
    public static function teampeiyujiang($aid,$sysset,$midteamfhArr = [],$oglist = [],$isyj=0,$yjmid=0,$commissionpercent=1,$moneypercent=0){
        if(getcustom('teamfenhong_peiyujiang',$aid)){
            writeLog('团队培育奖','teamfenhong_peiyujiang.log');
            //dump($midteamfhArr);
            writeLog('奖金数据'.json_encode($midteamfhArr),'teamfenhong_peiyujiang.log');
            if(empty($midteamfhArr)){
                return true;
            }
            $midshouyiArr = [];//团队培育奖
            $newoglist = [];
            $oglist = array_column($oglist,null,'id');
            //循环处理拿团队分红奖的会员
            foreach($midteamfhArr as $t_mid=>$team){

                writeLog('会员'.$t_mid.'团队培育奖开始,总收益'.$team['commission'],'teamfenhong_peiyujiang.log');

                //拿奖人从$t_mid开始顺着推荐网向上找最近符合条件的会员
                $member = Db::name('member')->where('id', $t_mid)->find();
                $pid = $member['pid'];
                if(!$pid){
                    continue;
                }
                $parent = Db::name('member')->where('id','=',$pid)->find();
                $level_data = Db::name('member_level')->where('id',$parent['levelid'])->find();
                //奖金比例
                $bonus_bili = $level_data['teamfenhong_peiyujiang_bl'];
                if($bonus_bili <=0){
                    continue;
                }
                $commissionyj = 0;
                $down_mid = $t_mid;
                $mid = $parent['id'];
                $commission_total = $team['commission'];
                $commission_total = bcmul($commission_total,$bonus_bili/100,2);

                if($isyj == 1 && $yjmid == $mid){
                    $commissionyj_my = $commission_total;
                }
                if($commissionpercent != 1){
                    $fenhongcommission = round($commission_total*$commissionpercent,2);
                    $fenhongmoney = round($commission_total*$moneypercent,2);
                }else{
                    $fenhongcommission = $commission_total;
                    $fenhongmoney = 0;
                }
                //团队收益汇总
                if($midshouyiArr[$mid]){
                    $midshouyiArr[$mid]['totalcommission'] = bcadd($midshouyiArr[$mid]['totalcommission'] , $commission_total);
                    $midshouyiArr[$mid]['commission'] = bcadd($midshouyiArr[$mid]['commission'] , $fenhongcommission);
                    $midshouyiArr[$mid]['money'] = bcadd($midshouyiArr[$mid]['money'] , $fenhongmoney);
                    $midshouyiArr[$mid]['score'] = 0;
                    $midshouyiArr[$mid]['ogids'][] = $down_mid;
                    $midshouyiArr[$mid]['levelid'] = $parent['levelid'];
                }else{
                    $midshouyiArr[$mid] = [
                        'totalcommission' => $commission_total,
                        'commission' => $fenhongcommission,
                        'money' => $fenhongmoney,
                        'score' => 0,
                        'ogids' => [$down_mid],
                        'module' => 'member',
                        'levelid' => $parent['levelid']
                    ];
                }
                if($isyj == 1 && $commissionyj_my > 0){
                    $commissionyj = bcadd($commissionyj,$commissionyj_my,2);

                    $newoglist[$t_mid] = $commissionyj_my;
                }
                if($isyj == 0 && $sysset['fhjiesuanhb'] == 0) {
                    self::fafang($aid,$midshouyiArr,'teamfenhong_peiyujiang',t('团队培育奖',$aid),$down_mid);
                    writeLog('会员'.$t_mid.'团队培育奖写入'.$team['commission'],'teamfenhong_peiyujiang.log');
                    $midshouyiArr = [];
                }

            }
            if($isyj == 1){
                return ['commissionyj'=>round($commissionyj,2),'oglist'=>$newoglist];
            }
            if($isyj == 0 && $sysset['fhjiesuanhb'] == 1) {
                self::fafang($aid,$midshouyiArr,'teamfenhong_peiyujiang',t('团队培育奖',$aid));
                writeLog('团队培育奖汇总写入','teamfenhong_peiyujiang.log');
            }
        }
    }

    //团队分红级差
    public static function teamfenhong_jicha($aid,$sysset,$oglist,$starttime=0,$endtime=0,$isyj=0,$yjmid=0){
        if(!getcustom('teamfenhong_jicha',$aid)) return ['commissionyj'=>0,'oglist'=>[]];
//        dump('团队级差分红进入');
        if($endtime == 0) $endtime = time();
        if($isyj == 1 && !$oglist){
            //多商户的商品是否参与分红
            if($sysset['fhjiesuanbusiness'] == 1){
                $bwhere = '1=1';
            }else{
                $bwhere = [['og.bid','=','0']];
            }
            $oglist = Db::name('shop_order_goods')->alias('og')->field('og.*,o.area2,m.nickname,m.headimg')->where('og.aid',$aid)->where('og.isfenhong',0)->where('og.status','in',[1,2,3])->where('og.refund_num',0)->join('shop_order o','o.id=og.orderid')->join('member m','m.id=og.mid')->where($bwhere)->order('og.id desc')->select()->toArray();
//            dump($oglist);
            if(!$oglist) $oglist = [];
            if(getcustom('yuyue_fenhong',$aid)){
                $yyorderlist = Db::name('yuyue_order')->alias('og')->field('og.*,m.nickname,m.headimg')->where('og.aid',$aid)->where('og.isfenhong',0)->where('og.status','in',[1,2,3])->join('member m','m.id=og.mid')->where($bwhere)->order('og.id desc')->select()->toArray();
                foreach($yyorderlist as $k=>$v){
                    $v['name'] = $v['proname'];
                    $v['real_totalprice'] = $v['totalprice'];
                    $v['cost_price'] = $v['cost_price'] ?? 0;
                    $v['module'] = 'yuyue';
                    $oglist[] = $v;
                }
            }
            if(getcustom('scoreshop_fenhong',$aid)){
                $scoreshopoglist = Db::name('scoreshop_order_goods')->alias('og')->field('og.*,m.nickname,m.headimg')->where('og.aid',$aid)->where('og.isfenhong',0)->where('og.status','in',[1,2,3])->join('member m','m.id=og.mid')->where($bwhere)->order('og.id desc')->select()->toArray();
                foreach($scoreshopoglist as $v){
                    $v['real_totalprice'] = $v['totalmoney'];
                    $v['module'] = 'scoreshop';
                    $oglist[] = $v;
                }
            }
            if(getcustom('luckycollage_fenhong',$aid)){
                $lcorderlist = Db::name('lucky_collage_order')->alias('og')->field('og.*,m.nickname,m.headimg')->where('og.aid',$aid)->where('og.isfenhong',0)->whereRaw('og.buytype=1 or og.iszj=1')->where('og.isjiqiren',0)->where('og.status','in',[1,2,3])->join('member m','m.id=og.mid')->where($bwhere)->order('og.id desc')->select()->toArray();
                foreach($lcorderlist as $k=>$v){
                    $v['name'] = $v['proname'];
                    $v['real_totalprice'] = $v['totalprice'];
                    $v['cost_price'] = 0;
                    $v['module'] = 'luckycollage';
                    $oglist[] = $v;
                }
            }
            if(getcustom('maidan_fenhong',$aid) && !getcustom('maidan_fenhong_new',$aid)){
                //买单分红
                $maidan_orderlist = Db::name('maidan_order')
                    ->alias('og')
                    ->join('member m','m.id=og.mid')
                    ->where('og.aid',$aid)
                    ->where('og.isfenhong',0)
                    ->where('og.status',1)
                    ->where($bwhere)
                    ->field('og.*,m.nickname,m.headimg')
                    ->order('og.id desc')
                    ->select()
                    ->toArray();
                if($maidan_orderlist){
                    foreach($maidan_orderlist as $mdk=>$mdv){
                        $mdv['name']             = $mdv['title'];
                        $mdv['real_totalprice']  = $mdv['paymoney'];
                        $mdv['cost_price']       = 0;
                        $mdv['num']              = 1;
                        $mdv['module']           = 'maidan';
                        $oglist[] = $mdv;
                    }
                }
            }

            if(getcustom('restaurant_fenhong',$aid)){
                //点餐
                $diancan_oglist = Db::name('restaurant_shop_order_goods')->alias('og')->field('og.*,o.area2,m.nickname,m.headimg')->where('og.aid',$aid)->where('og.isfenhong',0)->where('og.status','in',[1,2,3])->join('restaurant_shop_order o','o.id=og.orderid')->join('member m','m.id=og.mid')->where($bwhere)->order('og.id desc')->select()->toArray();
                if($diancan_oglist){
                    foreach($diancan_oglist as $dck=>$dcv){
                        $dcv['module'] = 'diancan';
                        $oglist[]      = $dcv;
                    }
                    unset($dcv);
                }
                //外卖
                $takeaway_oglist = Db::name('restaurant_takeaway_order_goods')->alias('og')->field('og.*,o.area2,m.nickname,m.headimg')->where('og.aid',$aid)->where('og.isfenhong',0)->where('og.status','in',[1,2,3])->join('restaurant_takeaway_order o','o.id=og.orderid')->join('member m','m.id=og.mid')->where($bwhere)->order('og.id desc')->select()->toArray();
                if($takeaway_oglist){
                    foreach($takeaway_oglist as $tak=>$tav){
                        $tav['module'] = 'takeaway';
                        $oglist[]      = $tav;
                    }
                    unset($tav);
                }
            }
            if(getcustom('fenhong_times_coupon',$aid)){
                $cwhere[] =['og.isfenhong','=',0];
                $cwhere[] =['og.status','=',1];
                $cwhere[] =['og.paytime','>=',$starttime];
                $cwhere[] =['og.paytime','<',$endtime];
                if($sysset['fhjiesuanbusiness'] != 1){ //多商户的商品是否参与分红
                    $cwhere[] =['og.bid','=',0];
                }
                $couponorderlist = Db::name('coupon_order')->alias('og')
                    ->join('member m','m.id=og.mid')
                    ->where($cwhere)
                    ->field('og.*,m.nickname,m.headimg')
                    ->order('og.id desc')
                    ->select()
                    ->toArray();
                foreach($couponorderlist as $k=>$v){
                    $v['name'] = $v['title'];
                    $v['real_totalprice'] = $v['price'];
                    $v['cost_price'] = 0;
                    $v['module'] = 'coupon';
                    $oglist[] = $v;
                }
            }
            if(getcustom('fenhong_kecheng',$aid)){
                $kwhere = [];
                $kwhere[] = ['og.aid','=',$aid];
                $kwhere[] = ['og.isfenhong','=',0];
                $kwhere[] = ['og.status','=',1];
                $kwhere[] = ['og.paytime','>=',$starttime];
                $kwhere[] = ['og.paytime','<',$endtime];
                if($sysset['fhjiesuanbusiness'] != 1){
                    $kwhere[] = ['og.bid','=','0'];
                }
                $kechenglist = Db::name('kecheng_order')
                    ->alias('og')
                    ->join('member m','m.id=og.mid')
                    ->where($kwhere)
                    ->field('og.*," " as area2,m.nickname,m.headimg')
                    ->select()
                    ->toArray();
                if($kechenglist){
                    foreach($kechenglist as $v){
                        $v['name']            = $v['title'];
                        $v['real_totalprice'] = $v['totalprice'];
                        $v['cost_price']      = 0;
                        $v['module']          = 'kecheng';
                        $v['num']             = 1;
                        $oglist[]             = $v;
                    }
                }
            }
        }
        //        dump($oglist);
        //参与团队分红的等级
        $teamfhlevellist = Db::name('member_level')->where('aid',$aid)->column('*','id');
//      dump($teamfhlevellist);
        if(!$teamfhlevellist) return ['commissionyj'=>0,'oglist'=>[]];
        if(!$oglist) return ['commissionyj'=>0,'oglist'=>[]];
        $defaultCid = Db::name('member_level_category')->where('aid',$aid)->where('isdefault', 1)->value('id');
        if($defaultCid) {
            $defaultLevelIds = Db::name('member_level')->where('aid',$aid)->where('cid', $defaultCid)->column('id');
        } else {
            $defaultLevelIds = Db::name('member_level')->where('aid',$aid)->column('id');
        }

        $isjicha = true;
        $allfenhongprice = 0;
        $ogids = [];
        $midteamfhArr = [];
        $teamfenhong_orderids = [];//按单奖励类 每单只发一次
        $teamfenhong_orderids_pj = [];
        $newoglist = [];
        $commissionyj = 0;
        foreach($oglist as $og){
            $commissionyj_my = 0;
            $pj_levelids = [];
            if($sysset['fhjiesuantype'] == 0){
                $fenhongprice = $og['real_totalprice'];
            }else{
                $fenhongprice = $og['real_totalprice'] - $og['cost_price'] * $og['num'];
            }
            if($fenhongprice <= 0) continue;
            $ogids[] = $og['id'];
            $allfenhongprice = $allfenhongprice + $fenhongprice;
            $member = Db::name('member')->where('id', $og['mid'])->find();
            if($teamfhlevellist){
                //判断脱离时间
                if($member['change_pid_time'] && $member['change_pid_time'] >= $og['createtime']){
                    $pids = $member['path_origin'];
                }else{
                    $pids = $member['path'];
                }
                if($pids){
                    $pids .= ','.$og['mid'];
                }else{
                    $pids = (string)$og['mid'];
                }
                if($pids){
                    $parentList = Db::name('member')->where('id','in',$pids)->order(Db::raw('field(id,'.$pids.')'))->select()->toArray();
                    $parentList = array_reverse($parentList);
                    $hasfhlevelids = [];
                    $last_teamfenhongbl = 0;
                    $last_teamfenhongmoney = 0;
                    //层级判断，如购买人等级未开启“包含自己teamfenhong_self“则购买人的上级为第一级，开启了则购买人为第一级
                    $level_i = 0;

                    foreach($parentList as $k=>$parent){
//                        dump('上级会员'.$parent['id'].'开始');
                        //判断升级时间
                        $leveldata = $teamfhlevellist[$parent['levelid']];
                        if($parent['levelstarttime'] >= $og['createtime']) {
                            $levelup_order_levelid = Db::name('member_levelup_order')->where('aid',$aid)->where('mid', $parent['id'])->where('status', 2)
                                ->where('levelup_time', '<', $og['createtime'])->whereIn('levelid',$defaultLevelIds)->order('levelup_time', 'desc')->value('levelid');
                            if($levelup_order_levelid) {
                                $parent['levelid'] = $levelup_order_levelid;
                                $leveldata = $teamfhlevellist[$parent['levelid']];
                            }else{
//                                if($leveldata['teamfenhong_self'] != 1 || ($leveldata['teamfenhong_self'] == 1 && $parent['id'] != $og['mid']))
                                //不包含自己跳过
                                unset($parentList[$k]);
                                continue;
                            }
                        }
                        if($parent['id'] == $og['mid']) { unset($parentList[$k]);continue;}//不包含自己则层级-1
                        $level_i++;


                        $hasfhlevelids[] = $parent['levelid'];
                        $totalfenhongmoney = 0;
                        $leveldata['teamjicha_money_dan'] = $leveldata['teamjicha_money'];//每单奖励 230915
                        $leveldata['teamjicha_pingji_money_dan'] = $leveldata['teamjicha_pingji_money'];//每单奖励 230915
                        $leveldata['teamjicha_money'] = 0;//重新赋值为0，否则按单奖励会重复计算
                        $leveldata['teamjicha_pingji_money'] = 0;

                        if($og['module'] != 'luckycollage2'){
                            if($og['module'] == 'yuyue'){
                                $product = Db::name('yuyue_product')->where('id',$og['proid'])->find();
                            }elseif($og['module'] == 'coupon'){
                                $product = Db::name('coupon')->where('id',$og['cpid'])->find();
                            }elseif($og['module'] == 'luckycollage'){
                                $product = Db::name('lucky_collage_product')->where('id',$og['proid'])->find();
                            }elseif($og['module'] == 'scoreshop'){
                                $product = Db::name('scoreshop_product')->where('id',$og['proid'])->find();
                            }elseif($og['module'] == 'kecheng'){
                                $product = Db::name('kecheng_list')->where('id',$og['kcid'])->find();
                            }else{
                                $product = Db::name('shop_product')->where('id',$og['proid'])->find();
                            }
                            //商品团队分红独立设置时每单奖励也会发放
                            if($product['teamjichaset'] == 1){ //按比例
                                $fenhongdata = json_decode($product['teamjichadata1'],true);
                                if($fenhongdata){
                                    $leveldata['teamjichabl'] = $fenhongdata[$leveldata['id']]['commission'];
                                    $leveldata['teamjicha_money'] = 0;
                                }
                            }elseif($product['teamjichaset'] == 2){ //按固定金额
                                $fenhongdata = json_decode($product['teamjichadata2'],true);
                                if($fenhongdata){
                                    $leveldata['teamjichabl'] = 0;
                                    $leveldata['teamjicha_money'] = $fenhongdata[$leveldata['id']]['commission'] * $og['num'];
                                }
                            }elseif($product['teamjichaset'] == -1){
                                $leveldata['teamjichabl'] = 0;
                                $leveldata['teamjicha_money'] = 0;
                            }
                            $totalfenhongmoney += $leveldata['teamjicha_money'];

                            //平级独立设置
                            if($product['teamjichapjset'] == 1){ //按比例
                                $fenhongpjdata = json_decode($product['teamjichapjdata1'],true);
                                if($fenhongpjdata){
                                    $leveldata['teamjicha_pingji_bl'] = $fenhongpjdata[$leveldata['id']]['commission'];
                                    $leveldata['teamjicha_pingji_money'] = 0;
                                }
                            }elseif($product['teamjichapjset'] == 2){ //按固定金额
                                $fenhongpjdata = json_decode($product['teamjichapjdata2'],true);
                                if($fenhongpjdata){
                                    $leveldata['teamjicha_pingji_bl'] = 0;
                                    $leveldata['teamjicha_pingji_money'] = $fenhongpjdata[$leveldata['id']]['commission'] * $og['num'];
                                }
                            }elseif($product['teamjichapjset'] == -1){
                                $leveldata['teamjicha_pingji_bl'] = 0;
                                $leveldata['teamjicha_pingji_money'] = 0;
                            }
                        }
                       // dump($product['teamjichapjset'].'=>'.$leveldata['id'].'=>'.$leveldata['teamjicha_pingji_money'].'=>'. $leveldata['teamjicha_pingji_money_dan']);

                        //每单奖励
                        if($leveldata['teamjicha_money_dan'] > 0 && !in_array($og['orderid'],$teamfenhong_orderids[$parent['id']])) {
                            $totalfenhongmoney += $leveldata['teamjicha_money_dan'];
                            $teamfenhong_orderids[$parent['id']][] = $og['orderid'];
                        }
                        if($isjicha){
                            $totalfenhongmoney = $totalfenhongmoney - $last_teamfenhongmoney;
                        }else{
                            $totalfenhongmoney = $totalfenhongmoney;
                        }
                        if($totalfenhongmoney < 0) $totalfenhongmoney = 0;
                        //分红比例
                        if($leveldata['teamjichabl'] > 0) {
                            if($isjicha){
                                $this_teamfenhongbl = $leveldata['teamjichabl'] - $last_teamfenhongbl;
                            }else{
                                $this_teamfenhongbl = $leveldata['teamjichabl'];
                            }
                            if($this_teamfenhongbl <=0) $this_teamfenhongbl = 0;
                            $last_teamfenhongbl = $last_teamfenhongbl + $this_teamfenhongbl;
                            $totalfenhongmoney = $totalfenhongmoney + $this_teamfenhongbl * $fenhongprice * 0.01;
                        }
                        $last_teamfenhongmoney = $last_teamfenhongmoney + $totalfenhongmoney;
                        //最后一次累计 极差计算用
                        if($totalfenhongmoney > 0 && $parent['id'] != $og['mid'] && (!in_array($parent['levelid'],$pj_levelids) )){
                            //1、下单人自身向上查找平级，但是自身不拿奖；2、已拿平级奖的不拿分红
                            if($isyj == 1 && $yjmid == $parent['id']){
                                $commissionyj_my += $totalfenhongmoney;
                            }
                            $fenhongcommission = $totalfenhongmoney;
                            $fenhongmoney = 0;
                            if($midteamfhArr[$parent['id']]){
                                $midteamfhArr[$parent['id']]['totalcommission'] = $midteamfhArr[$parent['id']]['totalcommission'] + $totalfenhongmoney;
                                $midteamfhArr[$parent['id']]['commission'] = $midteamfhArr[$parent['id']]['commission'] + $fenhongcommission;
                                $midteamfhArr[$parent['id']]['money'] = $midteamfhArr[$parent['id']]['money'] + $fenhongmoney;
                                $midteamfhArr[$parent['id']]['score'] = 0;
                                $midteamfhArr[$parent['id']]['ogids'][] = $og['id'];
                                $midteamfhArr[$parent['id']]['levelid'] = $parent['levelid'];
                            }else{
                                $midteamfhArr[$parent['id']] = [
                                    'totalcommission'=>$totalfenhongmoney,
                                    'commission'=>$fenhongcommission,
                                    'money'=>$fenhongmoney,
                                    'score'=>0,
                                    'ogids'=>[$og['id']],
                                    'module'=>$og['module'] ?? 'shop',
                                    'levelid' => $parent['levelid'],
                                    'type' => '团队级差分红',
                                ];
                            }
                            if(getcustom('commission_orderrefund_deduct',$aid) && $isyj == 0){
                                self::fhrecord($aid,$parent['id'],$fenhongcommission,0,$og['id'],$og['module'] ?? 'shop','teamfenhong',t('团队级差分红',$aid));
                            }
//                            dump($parent['id'].'拿奖'.$totalfenhongmoney);
                        }

                        //平级奖 找最近的上级
                        //teamfenhong_pingji_type:0按奖励金额,1按订单金额
                        $levelSort = [];

//                        dump('平级奖开始');
                        if(($leveldata['teamjicha_pingji_bl']>0 || $leveldata['teamjicha_pingji_money'] > 0 || $leveldata['teamjicha_pingji_money_dan'] > 0)
                            || ($totalfenhongmoney > 0 || $leveldata['teamjicha_pingji_type'] == 1) ){
                                foreach($parentList as $k2=>$parent2){
                                    $parent2Level = $teamfhlevellist[$parent2['levelid']];
                                    $parentLevel = $teamfhlevellist[$parent['levelid']];
                                    $levelSort[] = $parent2Level['sort'];
                                    //dump($parent2Level['id'].'=>'.$parentLevel['id']);
                                    if($parent2Level['sort'] > $parentLevel['sort']){
                                        break;
                                    }
                                    if(in_array($parent2['levelid'],$pj_levelids)){
                                        //每个级别平级奖只发一次
                                        continue;
                                    }
                                    if($k2<=1){
                                        //直推会员不拿平级奖
                                        continue;
                                    }
                                    if($k2 > $k && $parent2['levelid'] == $parent['levelid']){
                                        $this_teamfenhongbl_pj = $leveldata['teamjicha_pingji_bl'];
                                        $this_teamfenhongmoney_pj = $leveldata['teamjicha_pingji_money'];
                                        if($this_teamfenhongbl_pj <=0) $this_teamfenhongbl_pj = 0;
                                        if($this_teamfenhongmoney_pj <=0) $this_teamfenhongmoney_pj = 0;
                                        if($this_teamfenhongbl_pj == 0 && $this_teamfenhongmoney_pj == 0 && $leveldata['teamjicha_pingji_money_dan']==0) continue;

                                        $totalfenhongmoney_pj = 0;
                                        if($this_teamfenhongbl_pj>0){
                                            if($leveldata['teamjicha_pingji_type'] == 0){
                                                //按奖励金额
                                                $totalfenhongmoney_pj += $this_teamfenhongbl_pj * $totalfenhongmoney * 0.01;
                                            }else{
                                                //按订单金额
                                                $totalfenhongmoney_pj += $this_teamfenhongbl_pj * $fenhongprice * 0.01;
                                            }
                                        }
                                        if($product['teamjichapjset'] == 0){
                                            //按会员等级，按总订单发放一次平级奖
                                            if(($this_teamfenhongmoney_pj > 0 || $leveldata['teamjicha_pingji_money_dan'] > 0) && !in_array($og['orderid'],$teamfenhong_orderids_pj[$parent2['id']])){

                                                $this_teamfenhongmoney_pj += $leveldata['teamjicha_pingji_money_dan'];//230915 每单奖励
                                                $totalfenhongmoney_pj += $this_teamfenhongmoney_pj;
                                                if($totalfenhongmoney_pj < 0) $totalfenhongmoney_pj = 0;
                                                $teamfenhong_orderids_pj[$parent2['id']][] = $og['orderid'];
                                            }
                                        }else{
                                            //产品单独设置参数时，按分订单发放多次平级奖
                                            if($this_teamfenhongmoney_pj > 0 && !in_array($og['id'],$teamfenhong_orderids_pj[$parent2['id']])){
                                                $totalfenhongmoney_pj += $this_teamfenhongmoney_pj;
                                                if($totalfenhongmoney_pj < 0) $totalfenhongmoney_pj = 0;
                                                $teamfenhong_orderids_pj[$parent2['id']][] = $og['id'];
                                            }
                                        }

                                        if($totalfenhongmoney_pj > 0 ){
                                            if($isyj == 1 && $yjmid == $parent2['id']){
                                                $commissionyj_my += $totalfenhongmoney_pj;
                                            }
                                            $fenhongcommission = $totalfenhongmoney_pj;
                                            $fenhongmoney = 0;
                                            $fenhongscore = 0;


                                            if($midteamfhArr[$parent2['id']]){
                                                $midteamfhArr[$parent2['id']]['totalcommission'] = $midteamfhArr[$parent2['id']]['totalcommission'] + $totalfenhongmoney_pj;
                                                $midteamfhArr[$parent2['id']]['commission'] = $midteamfhArr[$parent2['id']]['commission'] + $fenhongcommission;
                                                $midteamfhArr[$parent2['id']]['money'] = $midteamfhArr[$parent2['id']]['money'] + $fenhongmoney;
                                                $midteamfhArr[$parent2['id']]['score'] = $midteamfhArr[$parent2['id']]['score'] + $fenhongscore;
                                                $midteamfhArr[$parent2['id']]['ogids'][] = $og['id'];
                                                $midteamfhArr[$parent2['id']]['levelid'] = $parent2['levelid'];
                                            }else{
                                                $midteamfhArr[$parent2['id']] = [
                                                    'totalcommission'=>$totalfenhongmoney_pj,
                                                    'commission'=>$fenhongcommission,
                                                    'money'=>$fenhongmoney,
                                                    'score'=>$fenhongscore,
                                                    'ogids'=>[$og['id']],
                                                    'module'=>$og['module'] ?? 'shop',
                                                    'levelid' => $parent2['levelid']
                                                ];
                                            }
                                            if(getcustom('commission_orderrefund_deduct',$aid) && $isyj == 0){
                                                self::fhrecord($aid,$parent['id'],$fenhongcommission,0,$og['id'],$og['module'] ?? 'shop','teamfenhong',t('团队级差分红',$aid));
                                            }
                                            $pj_levelids[] = $parent2['levelid'];
//                                            dump($parent2['id'].'拿平级奖'.$totalfenhongmoney_pj);
                                        }
                                    }
                                }
                        }
                    }
                }
            }
//            dump($midteamfhArr);
            if($isyj == 1 && $commissionyj_my > 0){
                $commissionyj += $commissionyj_my;
                $og['commission'] = round($commissionyj_my,2);
                $og['fhname'] = t('团队级差分红',$aid);
                $newoglist[] = $og;
            }

            if($isyj == 0 && $sysset['fhjiesuanhb'] == 0) {
                self::fafang($aid,$midteamfhArr,'teamfenhong',t('团队级差分红',$aid));
                $midteamfhArr = [];
            }
        }
        //die('stop');
        if($isyj == 1){
            return ['commissionyj'=>round($commissionyj,2),'oglist'=>$newoglist];
        }
        if($isyj == 0 && $sysset['fhjiesuanhb'] == 1) {
            self::fafang($aid,$midteamfhArr,'teamfenhong',t('团队级差分红',$aid));
        }
    }

    //团队长分红
    public static function teamleader_fenhong($aid,$sysset,$oglist,$starttime=0,$endtime=0,$isyj=0,$yjmid=0){
        //dump('团队长分红进入');
        if($endtime == 0) $endtime = time();
        if(getcustom('fenhong_manual',$aid)) return ['commissionyj'=>0,'oglist'=>[]];

        if(getcustom('fenhong_business_item_switch',$aid)){
            //查找开启的多商户
            $bids = Db::name('business')->where('aid',$aid)->where('teamfenhong_status',1)->column('id');
            $bids = array_merge([0],$bids);
        }
        if(getcustom('maidan_fenhong_new',$aid)){
            $bids_maidan = Db::name('business')->where('maidan_team',1)->column('id');
            $bids_maidan = array_merge([0],$bids_maidan);
        }
        if($isyj == 1 && !$oglist){
            //多商户的商品是否参与分红
            if($sysset['fhjiesuanbusiness'] == 1){
                $bwhere = '1=1';
            }else{
                $bwhere = [['og.bid','=','0']];
            }
            if(getcustom('fenhong_business_item_switch',$aid)){
                $oglist = Db::name('shop_order_goods')->alias('og')->field('og.*,o.area2,m.nickname,m.headimg')
                    ->where('og.bid','in',$bids)
                    ->where('og.aid',$aid)->where('og.isfenhong',0)->where('og.status','in',[1,2,3])->where('og.refund_num',0)->join('shop_order o','o.id=og.orderid')->join('member m','m.id=og.mid')->where($bwhere)->order('og.id desc')->select()->toArray();
            }else{
                $oglist = Db::name('shop_order_goods')->alias('og')->field('og.*,o.area2,m.nickname,m.headimg')->where('og.aid',$aid)->where('og.isfenhong',0)->where('og.status','in',[1,2,3])->where('og.refund_num',0)->join('shop_order o','o.id=og.orderid')->join('member m','m.id=og.mid')->where($bwhere)->order('og.id desc')->select()->toArray();
            }
//            dump($oglist);
            if(!$oglist) $oglist = [];
            if(getcustom('yuyue_fenhong',$aid)){
                $yyorderlist = Db::name('yuyue_order')->alias('og')->field('og.*,m.nickname,m.headimg')->where('og.aid',$aid)->where('og.isfenhong',0)->where('og.status','in',[1,2,3])->join('member m','m.id=og.mid')->where($bwhere)->order('og.id desc')->select()->toArray();
                foreach($yyorderlist as $k=>$v){
                    $v['name'] = $v['proname'];
                    $v['real_totalprice'] = $v['totalprice'];
                    $v['cost_price'] = $v['cost_price'] ?? 0;
                    $v['module'] = 'yuyue';
                    $oglist[] = $v;
                }
            }
            if(getcustom('scoreshop_fenhong',$aid)){
                $scoreshopoglist = Db::name('scoreshop_order_goods')->alias('og')->field('og.*,m.nickname,m.headimg')->where('og.aid',$aid)->where('og.isfenhong',0)->where('og.status','in',[1,2,3])->join('member m','m.id=og.mid')->where($bwhere)->order('og.id desc')->select()->toArray();
                foreach($scoreshopoglist as $v){
                    $v['real_totalprice'] = $v['totalmoney'];
                    $v['module'] = 'scoreshop';
                    $oglist[] = $v;
                }
            }
            if(getcustom('luckycollage_fenhong',$aid)){
                $lcorderlist = Db::name('lucky_collage_order')->alias('og')->field('og.*,m.nickname,m.headimg')->where('og.aid',$aid)->where('og.isfenhong',0)->whereRaw('og.buytype=1 or og.iszj=1')->where('og.isjiqiren',0)->where('og.status','in',[1,2,3])->join('member m','m.id=og.mid')->where($bwhere)->order('og.id desc')->select()->toArray();
                foreach($lcorderlist as $k=>$v){
                    $v['name'] = $v['proname'];
                    $v['real_totalprice'] = $v['totalprice'];
                    $v['cost_price'] = 0;
                    $v['module'] = 'luckycollage';
                    $oglist[] = $v;
                }
            }
            if(getcustom('maidan_fenhong',$aid) && !getcustom('maidan_fenhong_new',$aid)){
                //买单分红
                $maidan_orderlist = Db::name('maidan_order')
                    ->alias('og')
                    ->join('member m','m.id=og.mid')
                    ->where('og.aid',$aid)
                    ->where('og.isfenhong',0)
                    ->where('og.status',1)
                    ->where($bwhere)
                    ->field('og.*,m.nickname,m.headimg')
                    ->order('og.id desc')
                    ->select()
                    ->toArray();
                if($maidan_orderlist){
                    foreach($maidan_orderlist as $mdk=>$mdv){
                        $mdv['name']             = $mdv['title'];
                        $mdv['real_totalprice']  = $mdv['paymoney'];
                        $mdv['cost_price']       = 0;
                        $mdv['num']              = 1;
                        $mdv['module']           = 'maidan';
                        $oglist[] = $mdv;
                    }
                }
            }
            if(getcustom('maidan_fenhong_new',$aid) && $sysset['maidanfenhong']){
                //买单分红
                $bwhere_maidan = [['og.bid', 'in', $bids_maidan]];
                $maidan_orderlist = Db::name('maidan_order')
                    ->alias('og')
                    ->join('member m','m.id=og.mid')
                    ->where('og.aid',$aid)
                    ->where('og.isfenhong',0)
                    ->where('og.status',1)
                    ->where($bwhere_maidan)
                    ->field('og.*,m.nickname,m.headimg')
                    ->order('og.id desc')
                    ->select()
                    ->toArray();
                if($maidan_orderlist){
                    foreach($maidan_orderlist as $mdk=>$mdv){
                        $mdv['name']             = $mdv['title'];
                        $mdv['real_totalprice']  = $mdv['paymoney'];
                        //买单分红结算方式
                        if($sysset['maidanfenhong_type'] == 1){
                            //按利润结算时直接把销售额改成利润
                            $mdv['real_totalprice'] = $mdv['paymoney'] - $mdv['cost_price'];
                        }
                        $mdv['cost_price']       = 0;
                        $mdv['num']              = 1;
                        $mdv['module']           = 'maidan';
                        $oglist[] = $mdv;
                    }
                }
            }
            if(getcustom('restaurant_fenhong',$aid)){
                //点餐
                $diancan_oglist = Db::name('restaurant_shop_order_goods')->alias('og')->field('og.*,o.area2,m.nickname,m.headimg')->where('og.aid',$aid)->where('og.isfenhong',0)->where('og.status','in',[1,2,3])->join('restaurant_shop_order o','o.id=og.orderid')->join('member m','m.id=og.mid')->where($bwhere)->order('og.id desc')->select()->toArray();
                if($diancan_oglist){
                    foreach($diancan_oglist as $dck=>$dcv){
                        $dcv['module'] = 'diancan';
                        $oglist[]      = $dcv;
                    }
                    unset($dcv);
                }
                //外卖
                $takeaway_oglist = Db::name('restaurant_takeaway_order_goods')->alias('og')->field('og.*,o.area2,m.nickname,m.headimg')->where('og.aid',$aid)->where('og.isfenhong',0)->where('og.status','in',[1,2,3])->join('restaurant_takeaway_order o','o.id=og.orderid')->join('member m','m.id=og.mid')->where($bwhere)->order('og.id desc')->select()->toArray();
                if($takeaway_oglist){
                    foreach($takeaway_oglist as $tak=>$tav){
                        $tav['module'] = 'takeaway';
                        $oglist[]      = $tav;
                    }
                    unset($tav);
                }
            }
            if(getcustom('fenhong_times_coupon',$aid)){
                $cwhere[] =['og.isfenhong','=',0];
                $cwhere[] =['og.status','=',1];
                $cwhere[] =['og.paytime','>=',$starttime];
                $cwhere[] =['og.paytime','<',$endtime];
                if($sysset['fhjiesuanbusiness'] != 1){ //多商户的商品是否参与分红
                    $cwhere[] =['og.bid','=',0];
                }
                $couponorderlist = Db::name('coupon_order')->alias('og')
                    ->join('member m','m.id=og.mid')
                    ->where($cwhere)
                    ->field('og.*,m.nickname,m.headimg')
                    ->order('og.id desc')
                    ->select()
                    ->toArray();
                foreach($couponorderlist as $k=>$v){
                    $v['name'] = $v['title'];
                    $v['real_totalprice'] = $v['price'];
                    $v['cost_price'] = 0;
                    $v['module'] = 'coupon';
                    $oglist[] = $v;
                }
            }
            if(getcustom('fenhong_kecheng',$aid)){
                $kwhere = [];
                $kwhere[] = ['og.aid','=',$aid];
                $kwhere[] = ['og.isfenhong','=',0];
                $kwhere[] = ['og.status','=',1];
                $kwhere[] = ['og.paytime','>=',$starttime];
                $kwhere[] = ['og.paytime','<',$endtime];
                if($sysset['fhjiesuanbusiness'] != 1){
                    $kwhere[] = ['og.bid','=','0'];
                }
                $kechenglist = Db::name('kecheng_order')
                    ->alias('og')
                    ->join('member m','m.id=og.mid')
                    ->where($kwhere)
                    ->field('og.*," " as area2,m.nickname,m.headimg')
                    ->select()
                    ->toArray();
                if($kechenglist){
                    foreach($kechenglist as $v){
                        $v['name']            = $v['title'];
                        $v['real_totalprice'] = $v['totalprice'];
                        $v['cost_price']      = 0;
                        $v['module']          = 'kecheng';
                        $v['num']             = 1;
                        $oglist[]             = $v;
                    }
                }
            }
        }
        //        dump($oglist);
        //参与团队分红的等级
        $teamfhlevellist = Db::name('member_level')->where('aid',$aid)->where('teamleader_fenhonglv','>','0')->column('*','id');
//      dump($teamfhlevellist);
        if(!$teamfhlevellist) return ['commissionyj'=>0,'oglist'=>[]];

        if(getcustom('luckycollage_teamfenhong',$aid)){
            if($sysset['fhjiesuanbusiness'] == 1){
                $bwhere2 = '1=1';
            }else{
                $bwhere2 = [['bid','=','0']];
            }
            if($sysset['fhjiesuantime_type'] == 1) {
                $lkorderlist = Db::name('lucky_collage_order')->where('isfenhong',0)->where('status','in',[1,2,3])->where('iszj',1)->where('isjiqiren',0)->where($bwhere2)->where('createtime','>=',$starttime)->where('createtime','<',$endtime)->select()->toArray();
                if($lkorderlist && $isyj ==0){
                    Db::name('lucky_collage_order')->where('isfenhong',0)->where('status','in',[1,2,3])->where('iszj',1)->where('isjiqiren',0)->where($bwhere2)->where('createtime','>=',$starttime)->where('createtime','<',$endtime)->update(['isfenhong'=>1]);
                }
            } else {
                $lkorderlist = Db::name('lucky_collage_order')->where('isfenhong',0)->where('status',3)->where('iszj',1)->where('isjiqiren',0)->where($bwhere2)->where('createtime','>=',$starttime)->where('createtime','<',$endtime)->select()->toArray();
                if($lkorderlist && $isyj ==0){
                    Db::name('lucky_collage_order')->where('isfenhong',0)->where('status',3)->where('iszj',1)->where('isjiqiren',0)->where($bwhere2)->where('createtime','>=',$starttime)->where('createtime','<',$endtime)->update(['isfenhong'=>1]);
                }
            }
            foreach($lkorderlist as $k=>$v){
                $v['name'] = $v['proname'];
                $v['real_totalprice'] = $v['totalprice'];
                if($isyj == 1){
                    $member = Db::name('member')->where('id',$v['mid'])->find();
                    $v['headimg'] = $member['headimg'];
                    $v['nickname'] = $member['nickname'];
                }
                $v['module'] = 'luckycollage2';
                $oglist[] = $v;
            }
        }
        if(!$oglist) return ['commissionyj'=>0,'oglist'=>[]];

        $defaultCid = Db::name('member_level_category')->where('aid',$aid)->where('isdefault', 1)->value('id');
        if($defaultCid) {
            $defaultLevelIds = Db::name('member_level')->where('aid',$aid)->where('cid', $defaultCid)->column('id');
        } else {
            $defaultLevelIds = Db::name('member_level')->where('aid',$aid)->column('id');
        }
        $allfenhongprice = 0;
        $ogids = [];
        $midteamfhArr = [];
        $teamfenhong_orderids = [];//按单奖励类 每单只发一次
        $newoglist = [];
        $commissionyj = 0;

        foreach($oglist as $og){
            //dump($og['orderid'].'开始');
            if(getcustom('maidan_fenhong_new',$aid) && $og['module']=='maidan'){
                if($og['bid'] > 0 && !in_array($og['bid'],$bids_maidan)){
                    continue;
                }
            }
            if(getcustom('fenhong_business_item_switch',$aid) && $og['module']!='maidan'){
                if($og['bid'] > 0 && !in_array($og['bid'],$bids)){
                    continue;
                }
            }
            $commissionyj_my = 0;
            if(getcustom('commission2moneypercent',$aid) && $sysset['commission2moneypercent1'] > 0){
                //是否是首单
                $beforeorder = Db::name('shop_order')->where('aid',$aid)->where('mid',$og['mid'])->where('status','in','1,2,3')->where('paytime','<',$og['paytime'])->find();
                if(!$beforeorder){
                    $commissionpercent = 1 - $sysset['commission2moneypercent1'] * 0.01;
                    $moneypercent = $sysset['commission2moneypercent1'] * 0.01;
                }else{
                    $commissionpercent = 1 - $sysset['commission2moneypercent2'] * 0.01;
                    $moneypercent = $sysset['commission2moneypercent2'] * 0.01;
                }
            }else{
                $commissionpercent = 1;
                $moneypercent = 0;
            }
            if($sysset['fhjiesuantype'] == 0){
                $fenhongprice = $og['real_totalprice'];
            }else{
                $fenhongprice = $og['real_totalprice'] - $og['cost_price'] * $og['num'];
            }
            if(getcustom('baikangxie',$aid)){
                $fenhongprice = $og['cost_price'] * $og['num'];
            }
            if($fenhongprice <= 0) continue;
            $ogids[] = $og['id'];
            $allfenhongprice = $allfenhongprice + $fenhongprice;
            $member = Db::name('member')->where('id', $og['mid'])->find();
            //dump('下单人会员id'.$member['id'].'级别id'.$member['levelid']);
            $member_level = Db::name('member_level')->where('id',$member['levelid'])->find();
            $member_extend = Db::name('member_level_record')->field('mid id,levelid')->where('aid', $aid)->where('mid', $og['mid'])->find();
            if($teamfhlevellist){
                //判断脱离时间
                if($member['change_pid_time'] && $member['change_pid_time'] >= $og['createtime']){
                    $pids = $member['path_origin'];
                }else{
                    $pids = $member['path'];
                }

                if($pids){
                    $pids .= ','.$og['mid'];
                }else{
                    $pids = (string)$og['mid'];
                }
                if($pids){
                    $parentList = Db::name('member')->where('id','in',$pids)->order(Db::raw('field(id,'.$pids.')'))->select()->toArray();
                    $parentList = array_reverse($parentList);
                    $hasfhlevelids = [];

                    //层级判断，如购买人等级未开启“包含自己teamfenhong_self“则购买人的上级为第一级，开启了则购买人为第一级
                    $level_i = 0;
                    $total_fafang_commission = 0;//总的要发放的佣金

                    foreach($parentList as $k=>$parent){
                        //dump('会员'.$parent['id'].'级别'.$parent['levelid'].'开始');
                        //判断升级时间
                        $leveldata = $teamfhlevellist[$parent['levelid']];
                        if($parent['levelstarttime'] >= $og['createtime']) {
                            $levelup_order_levelid = Db::name('member_levelup_order')->where('aid',$aid)->where('mid', $parent['id'])->where('status', 2)
                                ->where('levelup_time', '<', $og['createtime'])->whereIn('levelid',$defaultLevelIds)->order('levelup_time', 'desc')->value('levelid');
                            if($levelup_order_levelid) {
                                $parent['levelid'] = $levelup_order_levelid;
                                $leveldata = $teamfhlevellist[$parent['levelid']];
                            }else{
//                                if($leveldata['teamfenhong_self'] != 1 || ($leveldata['teamfenhong_self'] == 1 && $parent['id'] != $og['mid']))
                                //不包含自己跳过
                                unset($parentList[$k]);
                                continue;
                            }
                        }

                        $level_i++;
                        if($parent['id'] == $og['mid'] && $leveldata['teamleader_fenhong_self'] != 1) {
                            //不包含自己则层级-1
                            $level_i--;
                            unset($parentList[$k]);continue;
                        }
                        if(!$leveldata || $level_i>$leveldata['teamleader_fenhonglv']){
                            //dump('超出级数跳过');
                            continue;
                        }
                        if($leveldata['teamleader_fenhong_money']<=0 && $leveldata['teamleader_fenhongbl']<=0){
                            //dump('未设置拿奖条件跳过');
                            //未设置拿奖条件跳过
                            continue;
                        }
                        if($leveldata['sort']<$member_level['sort']){
                            //dump('小于下单人级别跳过');
                            //上级会员级别小于下单人级别，跳过
                            continue;
                        }
                        $hasfhlevelids[] = $parent['levelid'];
                        $totalfenhongmoney = 0;
                        $totalfenhongscore = 0;
                        $leveldata['teamleader_fenhong_money_dan'] = $leveldata['teamleader_fenhong_money'];//每单奖励 230915
                        $leveldata['teamleader_fenhong_money'] = 0;//重新赋值为0，否则按单奖励会重复计算
                        if($og['module'] != 'luckycollage2'){
                            if($og['module'] == 'yuyue'){
                                $product = Db::name('yuyue_product')->where('id',$og['proid'])->find();
                            }elseif($og['module'] == 'coupon'){
                                $product = Db::name('coupon')->where('id',$og['cpid'])->find();
                            }elseif($og['module'] == 'luckycollage'){
                                $product = Db::name('lucky_collage_product')->where('id',$og['proid'])->find();
                            }elseif($og['module'] == 'scoreshop'){
                                $product = Db::name('scoreshop_product')->where('id',$og['proid'])->find();
                            }elseif($og['module'] == 'kecheng'){
                                $product = Db::name('kecheng_list')->where('id',$og['kcid'])->find();
                            }else{
                                $product = Db::name('shop_product')->where('id',$og['proid'])->find();
                            }
                            if(getcustom('maidan_fenhong',$aid) || getcustom('maidan_fenhong_new',$aid)){
                                if($og['module'] == 'maidan'){
                                    $product = [];
                                    $product['teamleader_fenhongset']   = 0;
                                }
                            }
                            if(getcustom('restaurant_fenhong',$aid)){
                                if($og['module'] == 'diancan' || $og['module'] == 'takeaway'){
                                    $product = [];
                                    $product['teamleader_fenhongset']   = 0;
                                }
                            }
                            //商品团队分红独立设置时每单奖励也会发放
                            if($product['teamleader_fenhongset'] == 1){ //按比例
                                $fenhongdata = json_decode($product['teamleader_fenhongdata1'],true);
                                if($fenhongdata){
                                    $leveldata['teamleader_fenhongbl'] = $fenhongdata[$leveldata['id']]['commission'];
                                    $leveldata['teamleader_fenhong_money'] = 0;
                                }
                            }elseif($product['teamleader_fenhongset'] == 2){ //按固定金额
                                $fenhongdata = json_decode($product['teamleader_fenhongdata2'],true);
                                if($fenhongdata){
                                    $leveldata['teamleader_fenhongbl'] = 0;
                                    $leveldata['teamleader_fenhong_money'] = $fenhongdata[$leveldata['id']]['commission'] * $og['num'];
                                }
                            }elseif($product['teamleader_fenhongset'] == -1){
                                $leveldata['teamleader_fenhongbl'] = 0;
                                $leveldata['teamleader_fenhong_money'] = 0;
                            }
                            $totalfenhongmoney += $leveldata['teamleader_fenhong_money'];
                        }
                        //每单奖励
                        if($leveldata['teamleader_fenhong_money_dan'] > 0 && !in_array($og['orderid'],$teamfenhong_orderids[$parent['id']])) {
                            $totalfenhongmoney += $leveldata['teamleader_fenhong_money_dan'];
                            $teamfenhong_orderids[$parent['id']][] = $og['orderid'];
                        }

                        //dump($totalfenhongmoney);
                        $totalfenhongmoney = $totalfenhongmoney;
                        if($totalfenhongmoney < 0) $totalfenhongmoney = 0;
                        //分红比例
                        if($leveldata['teamleader_fenhongbl'] > 0) {
                            $this_teamfenhongbl = $leveldata['teamleader_fenhongbl'];
                            if($this_teamfenhongbl <=0) $this_teamfenhongbl = 0;
                            $totalfenhongmoney = $totalfenhongmoney + $this_teamfenhongbl * $fenhongprice * 0.01;
                        }

                        if($totalfenhongmoney > 0 || $totalfenhongscore > 0){
                            if($isyj == 1 && $yjmid == $parent['id']){
                                $commissionyj_my += $totalfenhongmoney;
                            }
                            if($commissionpercent != 1){
                                $fenhongcommission = round($totalfenhongmoney*$commissionpercent,2);
                                $fenhongmoney = round($totalfenhongmoney*$moneypercent,2);
                                $fenhongscore = $totalfenhongscore;
                            }else{
                                $fenhongcommission = $totalfenhongmoney;
                                $fenhongmoney = 0;
                                $fenhongscore = $totalfenhongscore;
                            }

                            if($midteamfhArr[$parent['id']]){
                                $midteamfhArr[$parent['id']]['totalcommission'] = $midteamfhArr[$parent['id']]['totalcommission'] + $totalfenhongmoney;
                                $midteamfhArr[$parent['id']]['commission'] = $midteamfhArr[$parent['id']]['commission'] + $fenhongcommission;
                                $midteamfhArr[$parent['id']]['money'] = $midteamfhArr[$parent['id']]['money'] + $fenhongmoney;
                                $midteamfhArr[$parent['id']]['score'] = $midteamfhArr[$parent['id']]['score'] + $fenhongscore;
                                $midteamfhArr[$parent['id']]['ogids'][] = $og['id'];
                                $midteamfhArr[$parent['id']]['levelid'] = $parent['levelid'];
                            }else{
                                $midteamfhArr[$parent['id']] = [
                                    'totalcommission'=>$totalfenhongmoney,
                                    'commission'=>$fenhongcommission,
                                    'money'=>$fenhongmoney,
                                    'score'=>$fenhongscore,
                                    'ogids'=>[$og['id']],
                                    'module'=>$og['module'] ?? 'shop',
                                    'levelid' => $parent['levelid'],
                                    'type' => '团队长分红',
                                ];
                            }
                            if(getcustom('commission_orderrefund_deduct',$aid) && $isyj == 0){
                                self::fhrecord($aid,$parent['id'],$fenhongcommission,$fenhongscore,$og['id'],$og['module'] ?? 'shop','teamfenhong',t('团队长分红',$aid));
                            }
                            //dump($parent['id'].'获得团队长分红'.$fenhongcommission);
                            break;
                        }
                    }
                }
            }
//            dump($midteamfhArr);
            if($isyj == 1 && $commissionyj_my > 0){
                $commissionyj += $commissionyj_my;
                $og['commission'] = round($commissionyj_my,2);
                $og['fhname'] = t('团队长分红',$aid);
                $newoglist[] = $og;
            }

            if($isyj == 0 && $sysset['fhjiesuanhb'] == 0) {
                self::fafang($aid,$midteamfhArr,'teamfenhong',t('团队长分红',$aid));
                $midteamfhArr = [];
            }
        }
        //die('stop');
        if($isyj == 1){
            return ['commissionyj'=>round($commissionyj,2),'oglist'=>$newoglist];
        }
        if($isyj == 0 && $sysset['fhjiesuanhb'] == 1) {
            self::fafang($aid,$midteamfhArr,'teamfenhong',t('团队长分红',$aid));
        }
    }
    /**
     * 
     * 分红发放记录
     */

    public static function fhrecord($aid,$mid,$commission,$score,$ogid,$module,$type,$remark){
        if(getcustom('commission_orderrefund_deduct',$aid)){
            $record = Db::name('member_fenhong_record')->where('mid',$mid)->where('module',$module)->where('type',$type)->where('ogid',$ogid)->find();
            if($record){
                return ;
            }
            if($commission > 0 || $score>0) {
                $fhdata = [];
                $fhdata['aid'] = $aid;
                $fhdata['mid'] = $mid;
                $fhdata['commission'] = $commission;
                if(getcustom('gdfenhong_score',$aid)){
                    $fhdata['score'] = $score;
                }
                $fhdata['remark'] = $remark;
                $fhdata['type'] = $type;
                $fhdata['createtime'] = time();
                $fhdata['ogid'] = $ogid;
                $fhdata['module'] = $module;
                Db::name('member_fenhong_record')->insert($fhdata);
            }
        }        
    }

    //团队见点奖
    public static function team_jiandian($aid,$sysset,$oglist,$starttime=0,$endtime=0,$isyj=0,$yjmid=0){
        $team_jiandian_custom = getcustom('team_jiandian',$aid);
        if($team_jiandian_custom) {
            if ($endtime == 0) $endtime = time();

            if ($isyj == 1 && !$oglist) {
                //多商户的商品是否参与分红
                $bwhere = '1=1';
                $oglist = Db::name('shop_order_goods')->alias('og')->field('og.*,o.area2,m.nickname,m.headimg')->where('og.aid', $aid)->where('og.isfenhong', 0)->where('og.status', 'in', [1, 2, 3])->where('og.refund_num',0)->join('shop_order o', 'o.id=og.orderid')->join('member m', 'm.id=og.mid')->where($bwhere)->order('og.id desc')->select()->toArray();
                if (!$oglist) $oglist = [];
            }
            //参与团队分红的等级
            $teamfhlevellist = Db::name('member_level')->where('aid', $aid)->column('*', 'id');
            if (!$teamfhlevellist) return ['commissionyj' => 0, 'oglist' => []];

            if (!$oglist) return ['commissionyj' => 0, 'oglist' => []];

            $defaultCid = Db::name('member_level_category')->where('aid', $aid)->where('isdefault', 1)->value('id');
            if ($defaultCid) {
                $defaultLevelIds = Db::name('member_level')->where('aid', $aid)->where('cid', $defaultCid)->column('id');
            } else {
                $defaultLevelIds = Db::name('member_level')->where('aid', $aid)->column('id');
            }

            $allfenhongprice = 0;
            $ogids = [];
            $midteamfhArr = [];
            $teamfenhong_orderids = [];
            $newoglist = [];
            $commissionyj = 0;

            foreach ($oglist as $og) {
                $commissionyj_my = 0;

                $commissionpercent = 1;
                $moneypercent = 0;

                if ($sysset['fhjiesuantype'] == 0) {
                    $fenhongprice = $og['real_totalprice'];
                } else {
                    $fenhongprice = $og['real_totalprice'] - $og['cost_price'] * $og['num'];
                }
                if ($fenhongprice <= 0) continue;
                $ogids[] = $og['id'];
                $allfenhongprice = $allfenhongprice + $fenhongprice;
                $member = Db::name('member')->where('id', $og['mid'])->find();
                $member_leveldata = $teamfhlevellist[$member['levelid']];
                if($member_leveldata['team_jiandian_status']!=1){
                    continue;
                }
                $max_sendnum = $member_leveldata['team_jiandian_people'];
                if ($teamfhlevellist) {
                    //判断脱离时间
                    if ($member['change_pid_time'] && $member['change_pid_time'] >= $og['createtime']) {
                        $pids = $member['path_origin'];
                    } else {
                        $pids = $member['path'];
                    }

                    if ($pids) {
                        $pids .= ',' . $og['mid'];
                    } else {
                        $pids = (string)$og['mid'];
                    }
                    if ($pids) {
                        $parentList = Db::name('member')->where('id', 'in', $pids)->order(Db::raw('field(id,' . $pids . ')'))->select()->toArray();
                        $parentList = array_reverse($parentList);
                        $hasfhlevelids = [];
                        $last_teamfenhongbl = 0;
                        $last_teamfenhongmoney = 0;
                        $level_i = 0;
                        foreach ($parentList as $k => $parent) {
                            if($parent['id']==$og['mid']){
                                continue;
                            }
                            $ii++;
                            //判断升级时间
                            $leveldata = $teamfhlevellist[$parent['levelid']];
                            if ($parent['levelstarttime'] >= $og['createtime']) {
                                $levelup_order_levelid = Db::name('member_levelup_order')->where('aid', $aid)->where('mid', $parent['id'])->where('status', 2)
                                    ->where('levelup_time', '<', $og['createtime'])->whereIn('levelid', $defaultLevelIds)->order('levelup_time', 'desc')->value('levelid');
                                if ($levelup_order_levelid) {
                                    $parent['levelid'] = $levelup_order_levelid;
                                    $leveldata = $teamfhlevellist[$parent['levelid']];
                                } else {
                                    unset($parentList[$k]);
                                    continue;
                                }
                            }

                            $hasfhlevelids[] = $parent['levelid'];
                            if($og['module'] == 'shop' || $og['module']==''){
                                $product = Db::name('shop_product')->where('id', $og['proid'])->find();
                            }
                            if ($product['teamjiandianset'] == 1) { //按比例
                                $fenhongdata = json_decode($product['teamjiandiandata1'], true);
                                if ($fenhongdata) {
                                    $leveldata['team_jiandan_bl'] = $fenhongdata[$leveldata['id']]['commission'];
                                    $leveldata['team_jiandian_money'] = 0;
                                }
                            } elseif ($product['teamjiandianset'] == 2) { //按固定金额
                                $fenhongdata = json_decode($product['teamjiandiandata2'], true);
                                if ($fenhongdata) {
                                    $leveldata['team_jiandan_bl'] = 0;
                                    $leveldata['team_jiandian_money'] = $fenhongdata[$leveldata['id']]['commission'] * $og['num'];
                                }
                            } elseif ($product['teamjiandianset'] == -1) {
                                $leveldata['team_jiandan_bl'] = 0;
                                $leveldata['team_jiandian_money'] = 0;
                            }

                            if (!$leveldata || $level_i >= $max_sendnum) continue;
                            //每单奖励
                            $totalfenhongmoney = 0;
                            if ($leveldata['team_jiandian_money'] > 0 && !in_array($og['orderid'], $teamfenhong_orderids[$parent['id']])) {
                                $totalfenhongmoney = $totalfenhongmoney + $leveldata['team_jiandian_money'];
                                if ($totalfenhongmoney < 0) $totalfenhongmoney = 0;
                                $last_teamfenhongmoney = $last_teamfenhongmoney + $totalfenhongmoney;
                                $teamfenhong_orderids[$parent['id']][] = $og['orderid'];
                            }
                            //分红比例
                            if ($leveldata['team_jiandan_bl'] > 0) {
                                $this_teamfenhongbl = $leveldata['team_jiandan_bl'];
                                if ($this_teamfenhongbl <= 0) $this_teamfenhongbl = 0;
                                $last_teamfenhongbl = $last_teamfenhongbl + $this_teamfenhongbl;
                                $totalfenhongmoney = $totalfenhongmoney + $this_teamfenhongbl * $fenhongprice * 0.01;
                            }
                            if ($totalfenhongmoney > 0) {
                                $level_i++;
                                if ($isyj == 1 && $yjmid == $parent['id']) {
                                    $commissionyj_my += $totalfenhongmoney;
                                }
                                if ($commissionpercent != 1) {
                                    $fenhongcommission = round($totalfenhongmoney * $commissionpercent, 2);
                                    $fenhongmoney = round($totalfenhongmoney * $moneypercent, 2);
                                } else {
                                    $fenhongcommission = $totalfenhongmoney;
                                    $fenhongmoney = 0;
                                }
                                if ($midteamfhArr[$parent['id']]) {
                                    $midteamfhArr[$parent['id']]['totalcommission'] = $midteamfhArr[$parent['id']]['totalcommission'] + $totalfenhongmoney;
                                    $midteamfhArr[$parent['id']]['commission'] = $midteamfhArr[$parent['id']]['commission'] + $fenhongcommission;
                                    $midteamfhArr[$parent['id']]['money'] = $midteamfhArr[$parent['id']]['money'] + $fenhongmoney;
                                    $midteamfhArr[$parent['id']]['ogids'][] = $og['id'];
                                    $midteamfhArr[$parent['id']]['levelid'] = $parent['levelid'];
                                } else {
                                    $midteamfhArr[$parent['id']] = [
                                        'totalcommission' => $totalfenhongmoney,
                                        'commission' => $fenhongcommission,
                                        'money' => $fenhongmoney,
                                        'ogids' => [$og['id']],
                                        'module' => $og['module'] ?? 'shop',
                                        'levelid' => $parent['levelid'],
                                        'type' => t('团队见点奖', $aid),
                                    ];
                                }
                                if(getcustom('commission_orderrefund_deduct',$aid) && $isyj == 0){
                                    self::fhrecord($aid,$parent['id'],$fenhongcommission,0,$og['id'],$og['module'] ?? 'shop','team_jiandian',t('团队见点奖',$aid));
                                }
                            }
                        }
                    }
                }
                if ($isyj == 1 && $commissionyj_my > 0) {
                    $commissionyj += $commissionyj_my;
                    $og['commission'] = round($commissionyj_my, 2);
                    $og['fhname'] = t('团队见点奖', $aid);
                    $newoglist[] = $og;
                }
                if ($isyj == 0 && $sysset['fhjiesuanhb'] == 0) {
                    self::fafang($aid, $midteamfhArr, 'team_jiandian', t('团队见点奖', $aid));
                    //根据分红奖团队收益
                    if(getcustom('teamfenhong_shouyi',$aid)){
                        self::teamshouyi($aid,$sysset,$midteamfhArr,$oglist,$isyj,$yjmid,$commissionpercent,$moneypercent);
                    }
                    $midteamfhArr = [];
                }
            }
            //die('stop');
            if ($isyj == 1) {
                //计算团队收益预收益
                if(getcustom('teamfenhong_shouyi',$aid)){
                    self::teamshouyi($aid,$sysset,$midteamfhArr,$oglist,$isyj,$yjmid,$commissionpercent,$moneypercent);
                    if(!empty($res_lvyou['commissionyj']) && $res_lvyou['commissionyj']>0){
                        $res_lvyou_commissionyj = $res_lvyou['commissionyj']??0;
                        $commissionyj = bcadd($commissionyj,$res_lvyou_commissionyj,2);
                    }
                }
                return ['commissionyj' => round($commissionyj, 2), 'oglist' => $newoglist];
            }
            if ($isyj == 0 && $sysset['fhjiesuanhb'] == 1) {
                self::fafang($aid, $midteamfhArr, 'team_jiandian', t('团队见点奖', $aid));
                //根据分红奖团队收益
                if(getcustom('teamfenhong_shouyi',$aid)){
                    self::teamshouyi($aid,$sysset,$midteamfhArr,$oglist,$isyj,$yjmid,$commissionpercent,$moneypercent);
                }
            }
        }
    }

    public static function team_fuchijin($aid,$sysset,$oglist,$starttime=0,$endtime=0,$isyj=0,$yjmid=0){
        $team_fuchijin_custom = getcustom('team_fuchijin',$aid);
        if($team_fuchijin_custom) {
            if ($endtime == 0) $endtime = time();

            if ($isyj == 1 && !$oglist) {
                //多商户的商品是否参与分红
                $bwhere = '1=1';
                $oglist = Db::name('shop_order_goods')->alias('og')->field('og.*,o.area2,m.nickname,m.headimg')->where('og.aid', $aid)->where('og.isfenhong', 0)->where('og.status', 'in', [1, 2, 3])->where('og.refund_num',0)->join('shop_order o', 'o.id=og.orderid')->join('member m', 'm.id=og.mid')->where($bwhere)->order('og.id desc')->select()->toArray();
                if (!$oglist) $oglist = [];
            }
            //参与团队分红的等级
            $teamfhlevellist = Db::name('member_level')->where('aid', $aid)->where('team_fuchijin_lv','>',0)->column('*', 'id');
            if (!$teamfhlevellist) return ['commissionyj' => 0, 'oglist' => []];

            if (!$oglist) return ['commissionyj' => 0, 'oglist' => []];

            $defaultCid = Db::name('member_level_category')->where('aid', $aid)->where('isdefault', 1)->value('id');
            if ($defaultCid) {
                $defaultLevelIds = Db::name('member_level')->where('aid', $aid)->where('cid', $defaultCid)->column('id');
            } else {
                $defaultLevelIds = Db::name('member_level')->where('aid', $aid)->column('id');
            }

            $allfenhongprice = 0;
            $ogids = [];
            $midteamfhArr = [];
            $teamfenhong_orderids = [];
            $newoglist = [];
            $commissionyj = 0;

            foreach ($oglist as $og) {
                $commissionyj_my = 0;

                $commissionpercent = 1;
                $moneypercent = 0;

                if ($sysset['fhjiesuantype'] == 0) {
                    $fenhongprice = $og['real_totalprice'];
                } else {
                    $fenhongprice = $og['real_totalprice'] - $og['cost_price'] * $og['num'];
                }
                if ($fenhongprice <= 0) continue;
                $ogids[] = $og['id'];
                $allfenhongprice = $allfenhongprice + $fenhongprice;
                $member = Db::name('member')->where('id', $og['mid'])->find();
                $member_leveldata = $teamfhlevellist[$member['levelid']];

                if ($teamfhlevellist) {
                    //判断脱离时间
                    if ($member['change_pid_time'] && $member['change_pid_time'] >= $og['createtime']) {
                        $pids = $member['path_origin'];
                    } else {
                        $pids = $member['path'];
                    }
                    if ($pids) {
                        $pids .= ',' . $og['mid'];
                    } else {
                        $pids = (string)$og['mid'];
                    }

                    if ($pids) {
                        foreach($teamfhlevellist as $k=>$teamfhlevel) {
                            $parentList = Db::name('member')->where('id', 'in', $pids)->where('levelid', $teamfhlevel['id'])->order(Db::raw('field(id,' . $pids . ')'))->select()->toArray();
                            $count = count($parentList);
                            if($count<=0){
                                continue;
                            }
                            if($count>=$teamfhlevel['team_fuchijin_lv']){
                                $count = $teamfhlevel['team_fuchijin_lv'];
                            }
                            $bonus_total = bcmul($fenhongprice, $teamfhlevel['team_fuchijin_bl'] / 100, 2);
                            $avg_bonus = bcdiv($bonus_total, $count, 2);
                            $i = 0;
                            $parentList = array_reverse($parentList);
                            foreach ($parentList as $k => $parent) {
                                if ($avg_bonus > 0) {
                                    $i++;
                                    if($i>$teamfhlevel['team_fuchijin_lv']){
                                        break;
                                    }
                                    if ($isyj == 1 && $yjmid == $parent['id']) {
                                        $commissionyj_my += $avg_bonus;
                                    }
                                    if ($commissionpercent != 1) {
                                        $fenhongcommission = round($avg_bonus * $commissionpercent, 2);
                                        $fenhongmoney = round($avg_bonus * $moneypercent, 2);
                                    } else {
                                        $fenhongcommission = $avg_bonus;
                                        $fenhongmoney = 0;
                                    }
                                    if ($midteamfhArr[$parent['id']]) {
                                        $midteamfhArr[$parent['id']]['totalcommission'] = $midteamfhArr[$parent['id']]['totalcommission'] + $avg_bonus;
                                        $midteamfhArr[$parent['id']]['commission'] = $midteamfhArr[$parent['id']]['commission'] + $fenhongcommission;
                                        $midteamfhArr[$parent['id']]['money'] = $midteamfhArr[$parent['id']]['money'] + $fenhongmoney;
                                        $midteamfhArr[$parent['id']]['ogids'][] = $og['id'];
                                        $midteamfhArr[$parent['id']]['levelid'] = $parent['levelid'];
                                    } else {
                                        $midteamfhArr[$parent['id']] = [
                                            'totalcommission' => $avg_bonus,
                                            'commission' => $fenhongcommission,
                                            'money' => $fenhongmoney,
                                            'ogids' => [$og['id']],
                                            'module' => $og['module'] ?? 'shop',
                                            'levelid' => $parent['levelid'],
                                            'type' => t('团队扶持金', $aid),
                                        ];
                                    }
                                    if (getcustom('commission_orderrefund_deduct', $aid) && $isyj == 0) {
                                        self::fhrecord($aid, $parent['id'], $fenhongcommission, 0, $og['id'], $og['module'] ?? 'shop', 'team_fuchijin', t('团队扶持金', $aid));
                                    }
                                }
                            }
                        }
                    }
                }
                if ($isyj == 1 && $commissionyj_my > 0) {
                    $commissionyj += $commissionyj_my;
                    $og['commission'] = round($commissionyj_my, 2);
                    $og['fhname'] = t('团队扶持金', $aid);
                    $newoglist[] = $og;
                }
                if ($isyj == 0 && $sysset['fhjiesuanhb'] == 0) {
                    self::fafang($aid, $midteamfhArr, 'team_fuchijin', t('团队扶持金', $aid));
                    //根据分红奖团队收益
                    if(getcustom('team_fuchijin',$aid)){
                        self::teamshouyi($aid,$sysset,$midteamfhArr,$oglist,$isyj,$yjmid,$commissionpercent,$moneypercent);
                    }
                    $midteamfhArr = [];
                }
            }
            //die('stop');
            if ($isyj == 1) {
                //计算团队收益预收益
                if(getcustom('teamfenhong_shouyi',$aid)){
                    self::teamshouyi($aid,$sysset,$midteamfhArr,$oglist,$isyj,$yjmid,$commissionpercent,$moneypercent);
                    if(!empty($res_lvyou['commissionyj']) && $res_lvyou['commissionyj']>0){
                        $res_lvyou_commissionyj = $res_lvyou['commissionyj']??0;
                        $commissionyj = bcadd($commissionyj,$res_lvyou_commissionyj,2);
                    }
                }
                return ['commissionyj' => round($commissionyj, 2), 'oglist' => $newoglist];
            }
            if ($isyj == 0 && $sysset['fhjiesuanhb'] == 1) {
                self::fafang($aid, $midteamfhArr, 'team_fuchijin', t('团队扶持金', $aid));
                //根据分红奖团队收益
                if(getcustom('teamfenhong_shouyi',$aid)){
                    self::teamshouyi($aid,$sysset,$midteamfhArr,$oglist,$isyj,$yjmid,$commissionpercent,$moneypercent);
                }
            }
        }
    }
}