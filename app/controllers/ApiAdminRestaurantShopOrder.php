<?php


//管理员中心 - 订单管理
namespace app\controllers;
use think\facade\Db;
use app\commons\Common;
use think\facade\Log;

class ApiAdminRestaurantShopOrder extends ApiAdmin
{
    //商城订单
    public function index(){
        $st = input('param.st');
        if(!input('?param.st') || $st === ''){
            $st = 'all';
        }
        $where = [];
        $where[] = ['aid','=',aid];
        $where[] = ['bid','=',bid];
        if(input('param.keyword')){
            $keywords = input('param.keyword');
            $orderids = Db::name('restaurant_shop_order_goods')->where($where)->where('name','like','%'.input('param.keyword').'%')->column('orderid');
            if(!$orderids){
                $where[] = ['ordernum|title', 'like', '%'.$keywords.'%'];
            }
        }
        if($this->user['mdid']){
            $where[] = ['mdid','=',$this->user['mdid']];
        }
        if($st == 'all'){

        }elseif($st == '0'){
            $where[] = ['status','=',0];
        }elseif($st == '1'){
            $where[] = ['status','=',1];
        }elseif($st == '2'){
            $where[] = ['status','=',2];
        }elseif($st == '3'){
            $where[] = ['status','=',3];
        }elseif($st == '10'){
            $where[] = ['refund_status','>',0];
        }
        $pernum = 10;
        $pagenum = input('post.pagenum');
        if(!$pagenum) $pagenum = 1;
        $datalist = Db::name('restaurant_shop_order')->where($where);
        if($orderids){
            $datalist->where(function ($query) use ($orderids,$keywords){
                $query->whereIn('id',$orderids)->whereOr('ordernum|title','like','%'.$keywords.'%');
            });
        }
        $datalist = $datalist->page($pagenum,$pernum)->order('id desc')->select()->toArray();
        if(!$datalist) $datalist = array();
        foreach($datalist as $key=>$v){
            $goodslist= Db::name('restaurant_shop_order_goods')->where('orderid',$v['id'])->select()->toArray();
           
            if(!$datalist[$key]['prolist']) $datalist[$key]['prolist'] = [];
            foreach($goodslist as $pgk=>$pro){
                }
            $datalist[$key]['prolist'] = $goodslist;
            $datalist[$key]['procount'] = Db::name('restaurant_shop_order_goods')->where('orderid',$v['id'])->sum('num');
            $datalist[$key]['member'] = Db::name('member')->field('id,headimg,nickname')->where('id',$v['mid'])->find();
            if(!$datalist[$key]['member']) $datalist[$key]['member'] = [];
            $datalist[$key]['totalprice'] = $v['totalprice']?$v['totalprice']:'0.00';
        }
        $rdata = [];
        $rdata['datalist'] = $datalist;
        $rdata['codtxt'] = Db::name('shop_sysset')->where('aid',aid)->value('codtxt');
        $rdata['st'] = $st;
        return $this->json($rdata);
    }
    //订单详情
    public function detail(){
        $detail = Db::name('restaurant_shop_order')->where('id',input('param.id/d'))->where('aid',aid)->where('bid',bid)->find();
        if(!$detail) $this->json(['status'=>0,'msg'=>'订单不存在']);
        $detail['createtime'] = $detail['createtime'] ? date('Y-m-d H:i:s',$detail['createtime']) : '';
        $detail['collect_time'] = $detail['collect_time'] ? date('Y-m-d H:i:s',$detail['collect_time']) : '';
        $detail['paytime'] = $detail['paytime'] ? date('Y-m-d H:i:s',$detail['paytime']) : '';
        $detail['refund_time'] = $detail['refund_time'] ? date('Y-m-d H:i:s',$detail['refund_time']) : '';
        $detail['send_time'] = $detail['send_time'] ? date('Y-m-d H:i:s',$detail['send_time']) : '';

        $member = Db::name('member')->where('id',$detail['mid'])->field('id,nickname,headimg')->find();
        $detail['nickname'] = $member['nickname'];
        $detail['headimg'] = $member['headimg'];

        $storeinfo = [];
        if($detail['freight_type'] == 1){
            $storeinfo = Db::name('mendian')->where('id',$detail['mdid'])->field('name,address,longitude,latitude')->find();
        }

        $prolist = Db::name('restaurant_shop_order_goods')->where('orderid',$detail['id'])->select()->toArray();
        foreach($prolist as $gk=>$gv){
            }
        
        
        $shopset = Db::name('shop_sysset')->where('aid',aid)->field('comment,autoclose')->find();
        $shop_set = Db::name('restaurant_shop_sysset')->where('aid',aid)->where('bid',$detail['bid'])->find();
        $shopset = array_merge($shopset,$shop_set);

        if($detail['status']==0 && $shopset['autoclose'] > 0){
            $lefttime = strtotime($detail['createtime']) + $shopset['autoclose']*60 - time();
            if($lefttime < 0) $lefttime = 0;
        }else{
            $lefttime = 0;
        }

        if($detail['field1']){
            $detail['field1data'] = explode('^_^',$detail['field1']);
        }
        if($detail['field2']){
            $detail['field2data'] = explode('^_^',$detail['field2']);
        }
        if($detail['field3']){
            $detail['field3data'] = explode('^_^',$detail['field3']);
        }
        if($detail['field4']){
            $detail['field4data'] = explode('^_^',$detail['field4']);
        }
        if($detail['field5']){
            $detail['field5data'] = explode('^_^',$detail['field5']);
        }

        $detail['tableName'] = Db::name('restaurant_table')->where('id',$detail['tableid'])->value('name');

        $detail['tabletext'] = '桌号';
        $is_refund  =0;
        $shopset['is_refund'] = $is_refund;
        $rdata = [];
        $rdata['detail'] = $detail;
        $rdata['prolist'] = $prolist;
        $rdata['shopset'] = $shopset;
        $rdata['storeinfo'] = $storeinfo;
        $rdata['lefttime'] = $lefttime;
        $rdata['expressdata'] = array_keys(express_data(['aid'=>aid,'bid'=>bid]));
        $rdata['codtxt'] = Db::name('shop_sysset')->where('aid',aid)->value('codtxt');

        return $this->json($rdata);
    }

    public function add()
    {
        if(request()->isPost()){
            $info = input('param.info');
            if(!empty($info['tel']) && !checkTel($info['tel'])){
                return json(['status'=>0,'msg'=>'请检查手机号格式']);
            }
            if(empty($info['tableId'])) {
                return json(['status'=>0,'msg'=>'请选择餐桌']);
            }

            $table = Db::name('restaurant_table')->where('aid',aid)->where('bid', bid)->where('id',$info['tableId'])->find();
            if(empty($table)) {
                return json(['status'=>0,'msg'=>'餐桌不存在']);
            }
            if($table['status'] != 0 || $table['orderid']){
                return json(['status'=>0,'msg'=>'餐桌当前状态不可接受新订单，请检查']);
            }

            $insert = [
                'aid' => aid,
                'bid' => $info['bid'] ? $info['bid'] : bid,
                'mid' => mid,
                'ordernum' => Common::generateOrderNo(aid,'restaurant_shop_order'),
                'tableid' => $info['tableId'],
                'renshu' => $info['renshu'],
                'tel' => $info['tel'],
                'message' => $info['message'],
                'linkman' => $info['linkman'],
                'platform' => platform,
                'createtime' => time(),
                'status' => 0
            ];
            $insert['title'] = '堂食订单:' . $insert['ordernum'];
            //
            $orderid = Db::name('restaurant_shop_order')->insertGetId($insert);
//            $payorderid = \app\models\Payorder::createorder(aid,$insert['bid'],$insert['mid'],'restaurant_booking',$orderid,$insert['ordernum'],'预定订单：'.$insert['ordernum'],$insert['totalprice']);

            //更新餐桌状态
            Db::name('restaurant_table')->where('aid',aid)->where('bid', bid)->where('id',$info['tableId'])->update(['status' => 2, 'orderid' => $orderid]);
            \app\commons\System::plog('餐饮点餐下单，桌号：'.$table['name']);
            return $this->json(['status'=>1,'msg' => '下单成功，请开始点餐','id'=>$orderid/*, 'payorderid' => $payorderid*/ ]);
        }
    }
    //编辑改价
    public function edit(){
        $orderid = input('param.id/d');
        $info = Db::name('restaurant_shop_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->find();
        if(empty($info)){
            return $this->json(['status'=>0,'msg' => '订单不存在']);
        }
        if($info['status'] >= 3) {
            return $this->json(['status'=>0,'msg' => '订单状态不可修改']);
        }

        $member = Db::name('member')->where('id',$info['mid'])->find();
        $userlevel = Db::name('member_level')->where('aid',aid)->where('id',$member['levelid'])->find();
        if($userlevel && $userlevel['discount']>0 && $userlevel['discount']<10){
            $discount = $userlevel['discount']*0.1; //会员折扣
        }else{
            $discount = 1;
        }

        if(input('post.info/a')){
            $postinfo = input('post.info/a');
            $orderUpdate['product_price'] = $postinfo['product_price'];
            $orderUpdate['leveldk_money'] = $postinfo['leveldk_money'];
            $orderUpdate['coupon_money'] = $postinfo['coupon_money'];
            $orderUpdate['scoredk_money'] = $postinfo['scoredk_money'];
            $orderUpdate['totalprice'] = $postinfo['totalprice'];
            Db::name('restaurant_shop_order')->where('id',$orderid)->where('aid',aid)->where('bid',bid)->update($orderUpdate);
            $order = Db::name('restaurant_shop_order')->where('id',$orderid)->where('aid',aid)->where('bid',bid)->find();

            $newordernum = date('ymdHis').rand(100000,999999);
            //支付单修改
            if($order['payorderid']){
                Db::name('payorder')->where('aid',aid)->where('bid',bid)->where('id',$order['payorderid'])->update([
                    'money'=>$orderUpdate['totalprice'],
                    'ordernum'=>$newordernum,
                ]);
            }

            //商品
            $istc1 = 0; //设置了按单固定提成时 只将佣金计算到第一个商品里
            $istc2 = 0;
            $istc3 = 0;
            $postgoods = input('post.goods/a');
            foreach($postgoods as $k=> $item){
                $ogid = $item['id'];
                $oginfo = Db::name('restaurant_shop_order_goods')->where('id',$ogid)->find();
                $ogdata = [];
                $ogdata['sell_price'] = $item['sell_price'] >= 0 ? $item['sell_price'] : 0;
                $ogdata['num'] = $item['num'] >= 0 ? $item['num'] : 0;
                $ogdata['totalprice'] = $ogdata['sell_price'] * $ogdata['num'];
                $ogdata['real_totalprice'] =  $ogdata['totalprice'];

                $product = Db::name('restaurant_product')->where('id',$oginfo['proid'])->find();
                $ogtotalprice = $ogdata['totalprice'];
                $fxjiesuantype = Db::name('admin_set')->where('aid',aid)->value('fxjiesuantype');
                if($fxjiesuantype == 1 || $fxjiesuantype == 2){
                    $allgoodsprice = $order['product_price'] /*- $order['scoredk_money'] - $order['scoredk_money'] - $order['scoredk_money']*/;
                    $couponmoney = $order['couponmoney'];
                    $scoredk = $order['scoredk'];
                    $disprice = 0;
                    $ogcouponmoney = 0;
                    $ogscoredk = 0;
                    if($product['lvprice']==0 && $userlevel && $userlevel['discount']>0 && $userlevel['discount']<10){ //未开启会员价
                        $disprice = $ogtotalprice * (1 - $userlevel['discount'] * 0.1);
                        $ogtotalprice = $ogtotalprice - $disprice;
                    }
                    if($couponmoney){
                        $ogcouponmoney = $ogtotalprice / $allgoodsprice * $couponmoney;
                    }
                    if($scoredk){
                        $ogscoredk = $ogtotalprice / $allgoodsprice * $scoredk;
                    }
                    $ogtotalprice = round($ogtotalprice - $ogcouponmoney - $ogscoredk,2);
                    if($ogtotalprice < 0) $ogtotalprice = 0;
                }

                //计算佣金的商品金额
                $commission_totalprice = $ogdata['totalprice'];
                if($fxjiesuantype == 1){ //按成交价格
                    $commission_totalprice = $ogdata['real_totalprice'];
                    if($commission_totalprice < 0) $commission_totalprice = 0;
                }
                if($fxjiesuantype==2){ //按利润提成
                    $commission_totalprice = $ogdata['real_totalprice'] - $oginfo['cost_price'] * $item['num'];
                    if($commission_totalprice < 0) $commission_totalprice = 0;
                }
                if($commission_totalprice < 0) $commission_totalprice = 0;

                $agleveldata = Db::name('member_level')->where('aid',aid)->where('id',$member['levelid'])->find();
                if($agleveldata['can_agent'] > 0 && $agleveldata['commission1own']==1){
                    $member['pid'] = $member['id'];
                }

                if($product['commissionset']!=-1){
                    if($member['pid']){
                        $parent1 = Db::name('member')->where('aid',aid)->where('id',$member['pid'])->find();

                        if($parent1){
                            $agleveldata1 = Db::name('member_level')->where('aid',aid)->where('id',$parent1['levelid'])->find();
                            if($agleveldata1['can_agent']!=0){
                                $ogdata['parent1'] = $parent1['id'];
                            }
                        }
                    }
                    if($parent1['pid']){
                        $parent2 = Db::name('member')->where('aid',aid)->where('id',$parent1['pid'])->find();
                        if($parent2){
                            $agleveldata2 = Db::name('member_level')->where('aid',aid)->where('id',$parent2['levelid'])->find();
                            if($agleveldata2['can_agent']>1){
                                $ogdata['parent2'] = $parent2['id'];
                            }
                        }
                    }
                    if($parent2['pid']){
                        $parent3 = Db::name('member')->where('aid',aid)->where('id',$parent2['pid'])->find();
                        if($parent3){
                            $agleveldata3 = Db::name('member_level')->where('aid',aid)->where('id',$parent3['levelid'])->find();
                            if($agleveldata3['can_agent']>2){
                                $ogdata['parent3'] = $parent3['id'];
                            }
                        }
                    }
                    if($product['commissionset']==1){//按比例
                        $commissiondata = json_decode($product['commissiondata1'],true);
                        if($commissiondata){
                            if($ogdata['parent1']) $ogdata['parent1commission'] = $commissiondata[$agleveldata1['id']]['commission1'] * $commission_totalprice * 0.01;
                            if($ogdata['parent2']) $ogdata['parent2commission'] = $commissiondata[$agleveldata2['id']]['commission2'] * $commission_totalprice * 0.01;
                            if($ogdata['parent3']) $ogdata['parent3commission'] = $commissiondata[$agleveldata3['id']]['commission3'] * $commission_totalprice * 0.01;
                        }
                    }elseif($product['commissionset']==2){//按固定金额
                        $commissiondata = json_decode($product['commissiondata2'],true);
                        if($commissiondata){
                            if($ogdata['parent1']) $ogdata['parent1commission'] = $commissiondata[$agleveldata1['id']]['commission1'] * $ogdata['num'];
                            if($ogdata['parent2']) $ogdata['parent2commission'] = $commissiondata[$agleveldata2['id']]['commission2'] * $ogdata['num'];
                            if($ogdata['parent3']) $ogdata['parent3commission'] = $commissiondata[$agleveldata3['id']]['commission3'] * $ogdata['num'];
                        }
                    }elseif($product['commissionset']==3){//提成是积分
                        $commissiondata = json_decode($product['commissiondata3'],true);
                        if($commissiondata){
                            if($ogdata['parent1']) $ogdata['parent1score'] = $commissiondata[$agleveldata1['id']]['commission1'] * $ogdata['num'];
                            if($ogdata['parent2']) $ogdata['parent2score'] = $commissiondata[$agleveldata2['id']]['commission2'] * $ogdata['num'];
                            if($ogdata['parent3']) $ogdata['parent3score'] = $commissiondata[$agleveldata3['id']]['commission3'] * $ogdata['num'];
                        }
                    }else{ //按会员等级设置的分销比例
                        if($ogdata['parent1']){
                            if($agleveldata1['commissiontype']==1){ //固定金额按单
                                if($istc1==0){
                                    $ogdata['parent1commission'] = $agleveldata1['commission1'];
                                    $istc1 = 1;
                                }
                            }else{
                                $ogdata['parent1commission'] = $agleveldata1['commission1'] * $commission_totalprice * 0.01;
                            }
                        }
                        if($ogdata['parent2']){
                            if($agleveldata2['commissiontype']==1){
                                if($istc2==0){
                                    $ogdata['parent2commission'] = $agleveldata2['commission2'];
                                    $istc2 = 1;
                                }
                            }else{
                                $ogdata['parent2commission'] = $agleveldata2['commission2'] * $commission_totalprice * 0.01;
                            }
                        }
                        if($ogdata['parent3']){
                            if($agleveldata3['commissiontype']==1){
                                if($istc3==0){
                                    $ogdata['parent3commission'] = $agleveldata3['commission3'];
                                    $istc3 = 1;
                                }
                            }else{
                                $ogdata['parent3commission'] = $agleveldata3['commission3'] * $commission_totalprice * 0.01;
                            }
                        }
                    }
                }

                Db::name('restaurant_shop_order_goods')->where('aid',aid)->where('bid',bid)->where('id',$ogid)->update($ogdata);
                //修改分成记录
                $record = Db::name('member_commission_record')->where(['aid'=>aid,'mid'=>$ogdata['parent1'],'frommid'=>$member['id'],'orderid'=>$orderid,'ogid'=>$ogid,'type'=>'restaurant_shop'])->find();
                if($record && $record['status'] == 0) {
                    Db::name('member_commission_record')->where('id', $record['id'])->delete();
                    if($ogdata['parent1'] && ($ogdata['parent1commission'] > 0 || $ogdata['parent1score'] > 0)){
                        Db::name('member_commission_record')->insert(['aid'=>aid,'mid'=>$ogdata['parent1'],'frommid'=>$member['id'],'orderid'=>$orderid,'ogid'=>$ogid,'type'=>'restaurant_shop','commission'=>$ogdata['parent1commission'],'score'=>$ogdata['parent1score'],'remark'=>t('下级').'购买菜品奖励','createtime'=>time()]);
                    }
                }
                $record = Db::name('member_commission_record')->where(['aid'=>aid,'mid'=>$ogdata['parent2'],'frommid'=>$member['id'],'orderid'=>$orderid,'ogid'=>$ogid,'type'=>'restaurant_shop'])->find();
                if($record && $record['status'] == 0) {
                    Db::name('member_commission_record')->where('id', $record['id'])->delete();
                    if($ogdata['parent2'] && ($ogdata['parent2commission'] || $ogdata['parent2score'])){
                        Db::name('member_commission_record')->insert(['aid'=>aid,'mid'=>$ogdata['parent2'],'frommid'=>$member['id'],'orderid'=>$orderid,'ogid'=>$ogid,'type'=>'restaurant_shop','commission'=>$ogdata['parent2commission'],'score'=>$ogdata['parent2score'],'remark'=>t('下二级').'购买菜品奖励','createtime'=>time()]);
                    }
                }

                $record = Db::name('member_commission_record')->where(['aid'=>aid,'mid'=>$ogdata['parent3'],'frommid'=>$member['id'],'orderid'=>$orderid,'ogid'=>$ogid,'type'=>'restaurant_shop'])->find();
                if($record && $record['status'] == 0) {
                    Db::name('member_commission_record')->where('id', $record['id'])->delete();
                    if($ogdata['parent3'] && ($ogdata['parent3commission'] || $ogdata['parent3score'])){
                        Db::name('member_commission_record')->insert(['aid'=>aid,'mid'=>$ogdata['parent3'],'frommid'=>$member['id'],'orderid'=>$orderid,'ogid'=>$ogid,'type'=>'restaurant_shop','commission'=>$ogdata['parent3commission'],'score'=>$ogdata['parent3score'],'remark'=>'下三级购买菜品奖励','createtime'=>time()]);
                    }
                }
            }

            Db::name('restaurant_shop_order')->where('aid',aid)->where('id',$orderid)->update(['ordernum'=>$newordernum]);
            Db::name('restaurant_shop_order_goods')->where('aid',aid)->where('orderid',$orderid)->update(['ordernum'=>$newordernum]);
            \app\commons\System::plog('餐饮点餐订单编辑'.$orderid);
            Log::write([
                'file'=>__FILE__.__LINE__,
                '餐饮点餐订单编辑,orderid：'=>$orderid,
                'post'=>json_encode(input('post.')),
                'uid'=>$this->uid
            ]);
            return $this->json(['status'=>1,'msg'=>'修改成功']);
        }

        if($info['tableid']) $info['tableName'] = Db::name('restaurant_table')->where('aid',aid)->where('bid',bid)->where('id', $info['tableid'])->value('name');
        $order_goods = Db::name('restaurant_shop_order_goods')->where('aid',aid)->where('bid',bid)->where('orderid',$orderid)->select()->toArray();
        foreach($order_goods as $k=>$v){
            $order_goods[$k]['lvprice'] = Db::name('restaurant_product')->where('id',$v['proid'])->value('lvprice'); //是否开启会员价
            }
        $rdata['status'] = 1;
        $rdata['info'] = $info;
        $rdata['order_goods'] = $order_goods;
        $rdata['discount'] = $discount;
        return $this->json($rdata);
    }

    //服务员确认支付
    public function pay()
    {
        if(!input('param.info')){
            return $this->json(['status'=>0,'msg'=>'参数错误']);
        }
        $info = input('param.info');
        $tableid = $info['tableId'];
        $discount = $info['discount'];
        $info = Db::name('restaurant_table')->where('aid',aid)->where('bid',bid)->where('id',$tableid)->find();
        if(empty($info)) {
            return $this->json(['status'=>0,'msg'=>'餐桌不存在']);
        }
        if($info['status'] == 0 || $info['orderid'] == 0) {
            return $this->json(['status'=>0,'msg'=>'未找到待结算订单']);
        }
//        Db::name('restaurant_shop_order')->where('aid',aid)->where('bid',bid)->where('tableid',$tableid)
//            ->where('id', $info['orderid'])->update(['status' => 3]);
        $order = Db::name('restaurant_shop_order')->where('aid',aid)->where('bid',bid)->where('id',$info['orderid'])
            ->find();
        if($order['totalprice'] < $discount) {
            return $this->json(['status'=>0,'msg'=>'优惠金额不正确']);
        }
        $updateData = ['discount_money'=>$discount, 'totalprice' => $order['totalprice'] - $discount];
        if(empty($order['paytypeid'])) {
            $updateData['paytypeid'] = 4;
            $updateData['paytype'] = '线下支付';
        }
        Db::name('restaurant_shop_order')->where('aid',aid)->where('bid',bid)->where('id',$info['orderid'])
            ->update($updateData);
        \app\commons\System::plog('餐饮点餐订单线下支付，桌号：'.$info['name'].'，订单ID：'.$info['orderid'].'，订单号：'.$order['ordernum']);
        Log::write([
            'file'=>__FILE__.__LINE__,
            'pay,orderid：'=>$info['orderid'],
            'tableid：'=>$tableid,
            'uid'=>$this->uid
        ]);
        $rs = \app\customs\Restaurant::shop_orderconfirm($info['orderid']);
        if($rs['status'] == 0) return $this->json($rs);


        return $this->json(['status'=>1,'msg'=>'我已收款，结算完成，请清理桌台']);
    }

    //出餐
    public function outfood(){
        }
    
    public function refundProlist(){
        }
    public function refund(){
        }
}