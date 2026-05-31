<?php


// +----------------------------------------------------------------------
// | 点餐餐饮收银台  custom_file(restaurant_shop_cashdesk)
// +----------------------------------------------------------------------
namespace app\controllers;

use app\commons\Alipay;
use app\commons\Order;
use app\customs\Sxpay;
use think\facade\Log;
use think\facade\View;
use think\facade\Db;

class RestaurantCashdesk extends Common
{
    public function initialize()
    {
        parent::initialize();
    }

    public function index()
    {
        $domain = request()->domain();
        $cashier = Db::name('restaurant_cashdesk')->where('aid',aid)->where('bid', bid)->find();
        if (empty($cashier)) {
            Db::name('restaurant_cashdesk')->insert(['createtime' => time(), 'bid' => bid, 'aid' => aid, 'name' => '餐饮收银台']);
            $cashier = Db::name('restaurant_cashdesk')->where('bid', bid)->find();
        }
        $domain = PRE_URL;
        $cashier_url = $domain . '/cashdesk/index.html#/table/index?id=' . $cashier['id'];
        //餐饮设置
        $sysset = Db::name('restaurant_admin_set')->where('aid',aid)->find();
        //入口文件作为参数传递
        $mode = trim(str_replace('.php','',$_SERVER['PHP_SELF']),'/');
        if($mode!='index'){
            $cashier_url.='&_mode='.$mode;
        }
        $bwxtitle = '微信收款';
        if(getcustom('cashdesk_alipay')){
            if($sysset['business_cashdesk_wxpay_type']>0 && $sysset['business_cashdesk_alipay_type']>0){
                $bwxtitle = '微信或支付宝收款';
            }elseif ($sysset['business_cashdesk_wxpay_type'] ==0 && $sysset['business_cashdesk_alipay_type']>0){
                $bwxtitle = '支付宝收款';
            }elseif ($sysset['business_cashdesk_wxpay_type'] >0 && $sysset['business_cashdesk_alipay_type'] == 0){
                $bwxtitle = '微信收款';
            }
        }
        View::assign('bwxtitle',$bwxtitle);
        $wxtitle = '微信收款';
        if(getcustom('cashdesk_alipay')){
            $wxtitle = '微信或支付宝';
        }
        View::assign('wxtitle',$wxtitle);
        $login_url =  $domain.'/?s=/RestaurantCashdeskLogin/index';
        $pinfo = Db::name('admin_setapp_restaurant_cashdesk')->where('aid',aid)->where('bid',bid)->find();
        //打印机
        $printArr = Db::name('wifiprint_set')->where('aid',aid)->where('bid',bid)->order('id')->where('machine_type',0)->column('name','id');
        View::assign('printArr',$printArr);
        View::assign('sysset',$sysset);
        View::assign('pinfo',$pinfo);
        View::assign('info',$cashier);
        View::assign('cashier_url', $cashier_url);
        View::assign('login_url', $login_url);
        View::assign('auth_data', $this->auth_data);
        if(getcustom('member_overdraft_money')){
            $overdraft_moneypay = Db::name('admin_set')->where('aid',aid)->value('overdraft_moneypay');
            View::assign('overdraft_moneypay', $overdraft_moneypay);
        }
        return View::fetch();
    }
    public function save(){
        $info = input('post.info/a');
        $info['wxpay'] = !$info['wxpay']?0:$info['wxpay'];
        $info['sxpay'] = !$info['sxpay']?0:$info['sxpay'];
        $info['cashpay'] = !$info['cashpay']?0:$info['cashpay'];
        $info['moneypay'] = !$info['moneypay']?0:$info['moneypay'];
        if(getcustom('restaurant_douyin_qrcode_hexiao')){
            $info['douyinhx'] = !$info['douyinhx']?0:$info['douyinhx'];
        }
        if(getcustom('pay_huifu')){
            $info['huifupay'] = !$info['huifupay']?0:$info['huifupay'];
        }
        if(getcustom('member_overdraft_money')){
            $info['guazhangpay'] = !$info['guazhangpay']?0:$info['guazhangpay'];
        }
        $info['member_login_alert'] = !$info['member_login_alert']?0:$info['member_login_alert'];
        $info['jiaoban_print_ids'] = implode(',',$info['jiaoban_print_ids']);
        if($info['id']){
            $info['updatetime'] = time();
            Db::name('restaurant_cashdesk')->where('aid',aid)->where('id',$info['id'])->update($info);
            \app\commons\System::plog('编辑餐饮收银台'.$info['id']);
        }else{
            $info['aid'] = aid;
            $info['createtime'] = time();
            $id = Db::name('restaurant_cashdesk')->insertGetId($info);
            \app\commons\System::plog('添加餐饮收银台'.$id);
        }
        //设置支付信息
        $pinfo = input('post.pinfo/a');
        $pinfo['wxpay_apiclient_cert'] = str_replace(PRE_URL.'/','',$pinfo['wxpay_apiclient_cert']);
        $pinfo['wxpay_apiclient_key'] = str_replace(PRE_URL.'/','',$pinfo['wxpay_apiclient_key']);
        if(!empty($pinfo['wxpay_apiclient_cert']) && substr($pinfo['wxpay_apiclient_cert'], -4) != '.pem'){
            return json(['status'=>0,'msg'=>'PEM证书格式错误']);
        }
        if(!empty($pinfo['wxpay_apiclient_key']) && substr($pinfo['wxpay_apiclient_key'], -4) != '.pem'){
            return json(['status'=>0,'msg'=>'证书密钥格式错误']);
        }
        if($pinfo){
            $appinfo = Db::name('admin_setapp_restaurant_cashdesk')->where('aid',aid)->where('bid',bid)->find();
            if($appinfo){
                Db::name('admin_setapp_restaurant_cashdesk')->where('aid',aid)->where('bid',bid)->update($pinfo);
            }else{
                $pinfo['aid'] =aid;
                $pinfo['bid'] =bid;
                Db::name('admin_setapp_restaurant_cashdesk')->insert($pinfo);
            }  
        }
       
        return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
    }


    public function cashier()
    {
        echo 'cashier html......';
//        return View::fetch();
    }
    //------------------------------餐饮收银台页面接口-----------------------//

    /**
     * @description 商品一级分类
     */
    public function getCategoryList()
    {
        $where = [];
        if(getcustom('restaurant_product_category_cashdesk')){
            $where[] = ['cashdesk_show' ,'=',1];
        }
        if(!getcustom('restaurant_product_category_cashdesk')){
            $where[] = ['is_shop' ,'=',1];
        }
        $list = Db::name('restaurant_product_category')->where('aid', aid)->where('bid',bid)->where('pid', 0)->where('status', 1)->order('sort desc,id')->where($where)->select()->toArray();
        $list = empty($list)?[]:$list;
        return $this->json(1, 'ok', $list);
    }

    public function getAllCids($pid = '')
    {
        if (!is_array($pid)) {
            $pid = [$pid];
        }
        $cids = $pids1 = $pids2 = $pids3 = [];
        if (bid > 0) {
            $pids1 = Db::name('restaurant_product_category')->where('aid', aid)->where('bid', bid)->where('pid', 'in', $pid)->where('is_shop',1)->where('status', 1)->order('sort desc,id')->column('id');
        } else {
            $pids1 = Db::name('restaurant_product_category')->where('aid', aid)->where('pid', 'in', $pid)->where('status', 1)->where('is_shop',1)->order('sort desc,id')->column('id');
        }
        $cids = array_merge($pid, $pids1, $pids2, $pids3);
        return $cids;
    }

    //餐饮收银台配置项返回
    public function getCashierInfo(){
        $cashier_id = input('param.cashdesk_id/d', 0);
        $info = Db::name('restaurant_cashdesk')->where('aid',aid)->where('id',$cashier_id)->find();
        if(empty($info)){
            return $this->json(0,'餐饮收银台信息缺失');
        }
        if(empty($info['option_name'])){
            $info['option_name'] = $this->user['un'];
        }
        $webinfo = Db::name('sysset')->where(['name'=>'webinfo'])->value('value');
        if($info['bid']>0){
            $binfo = Db::name('business')->where('aid',aid)->where('id',$info['bid'])->field('logo,name,score2money')->find();
        }else{
            $binfo = Db::name('admin_set')->where('aid',aid)->field('logo,name,score2money')->find();
        }
        $webinfo = $webinfo?json_decode($webinfo,true):[];
        $info['ico'] = $webinfo['ico']??'';
        $info['bname'] = $binfo['name']??'';
        $info['blogo'] = $binfo['logo']??'';
        $info['color1'] = $info['color1'] ? $info['color1'] : '#2792FF';
        $info['color1rgb'] = $info['color1'] ? hex2rgb($info['color1']) : hex2rgb('#2792FF');
        $is_bar_table =0;
        if(getcustom('restaurant_bar_table_order')){
            $shop_sysset = Db::name('restaurant_shop_sysset')->where('aid',aid)->where('bid',bid)->find();
            if($shop_sysset['bar_table_order'] ==1){
                $is_bar_table = 1;
            }
        }
        $info['is_bar_table'] = $is_bar_table;
        //现金支付的组合支付
        $is_mix_pay = 0;
        if(getcustom('restaurant_cashdesk_mix_pay')){
            $is_mix_pay = 1;
        }
        $info['is_mix_pay'] = $is_mix_pay;
        //会员支付密码
        $is_paypwd = 0;
        if(getcustom('restaurant_cashdesk_member_paypwd')){
            if($info['bid'] ==0 && ($this->auth_data =='all' || in_array('Member/index',$this->auth_data) || in_array('Member/edit',$this->auth_data))){
                $is_paypwd = 1;
            }
            unset($info['default_paypwd']);
        }
        $info['is_paypwd'] = $is_paypwd;
        //取餐
        $is_take_food = 0;
        //出餐功能
        $is_out_food = 0;
        if(getcustom('restaurant_take_food')){
            $restaurant_take_food_sysset = Db::name('restaurant_take_food_sysset')->where('aid',aid)->where('bid',$info['bid'])->find();
            if($restaurant_take_food_sysset['status']) $is_take_food =1;
           
           
            if($this->auth_data =='all' || in_array('RestaurantCashdesk/outfood',$this->auth_data)){
                $is_out_food =1;
            }
        }
        $info['is_take_food'] = $is_take_food;
        $info['is_out_food'] = $is_out_food;//出餐权限
        //会员充值
        $is_member_recharge=0;
        if(getcustom('restaurant_cashdesk_member_recharge')){
            if(bid ==0){
                $is_member_recharge=1;
            }
        }
        $info['is_member_recharge'] = $is_member_recharge;
        //信用额度
        $is_overdraft_money  =0;
        if(getcustom('member_overdraft_money')){
            $overdraft_moneypay = Db::name('admin_set')->where('aid',aid)->value('overdraft_moneypay');
            if(bid ==0 && $overdraft_moneypay && ($this->auth_data == 'all' || in_array('OverdraftMoney/recharge',$this->auth_data))) $is_overdraft_money =1;
        }
        $info['is_overdraft_money'] = $is_overdraft_money;
        $is_order_goods_remark = 0; //预置备注
        if(getcustom('restaurant_cashdek_ordergoods_remark')){
            $is_order_goods_remark = 1;
        }
        $info['is_order_goods_remark'] = $is_order_goods_remark;
        $is_operate_product = 0;//操作产品上架
        if(getcustom('restaurant_cashdesk_product_operate')){
            $is_operate_product = 1;
        }
        $info['is_operate_product'] = $is_operate_product;
        $is_guadan_order = false;
        if(getcustom('restaurant_shop_guadan_order')){
            $is_guadan_order = true;
        }
        $info['is_guadan_order'] = $is_guadan_order;
        $is_show_baobiao = 0;//报表
        if(getcustom('restaurant_cashdesk_baobiao')){
            $is_show_baobiao = 1;
        }
        $info['is_show_baobiao'] = $is_show_baobiao;
        return  $this->json(1,'ok',$info);
    }


//------------------------------订单操作 start-----------------------//  
    
    /**
     * @description 商品列表
     */
    public function getProductList()
    {
        $page = input('param.page/d', 1);
        $limit = input('param.limit/d', 10);
        $cid = input('param.cid/d', 0);
        $where = array();
        $where[] = ['p.aid', '=', aid];
        $where[] = ['p.bid', '=', bid];
      
        $is_operate = 0;
        if(getcustom('restaurant_cashdesk_product_operate')){
            $is_operate =1;
        }
        //默认只能操作上架的，开启定制后可操作全部
        if($is_operate ==0) {
            $nowtime = time();
            $nowhm = date('H:i');
            $where[] = Db::raw("`status`=1 or (`status`=2 and unix_timestamp(start_time)<=$nowtime and unix_timestamp(end_time)>=$nowtime) or (`status`=3 and ((start_hours<end_hours and start_hours<='$nowhm' and end_hours>='$nowhm') or (start_hours>=end_hours and (start_hours<='$nowhm' or end_hours>='$nowhm'))) )");
        }
        $where[] = ['p.ischecked', '=', 1];//已通过审核
//        $where[] = ['p.sell_price', '>', 0];//测试用
        //周几可点
        
        $week = date("w");
        if($week==0) $week = 7;
        $where[] = Db::raw('find_in_set('.$week.',p.status_week)');
        if (input('param.name')) $where[] = ['p.name|p.procode|g.barcode', 'like', '%' . input('param.name') . '%'];

        if (input('param.code')) $where[] = ['procode|barcode|g.barcode', 'like', '%' . input('param.code') . '%'];
        $cwhere =[];
        if(getcustom('restaurant_product_category_cashdesk')){
            $cwhere[] = ['cashdesk_show' ,'=',1];
        }
        if(input('param.cid')){
            $cid = input('post.cid') ? input('post.cid/d') : input('param.cid/d');
            //子分类
            $clist = Db::name('restaurant_product_category')->where('aid',aid)->where('bid',bid)->where('pid',$cid)->where($cwhere)->column('id');
            if($clist){
                $clist2 = Db::name('restaurant_product_category')->where('aid',aid)->where('bid',bid)->where('pid','in',$clist)->column('id');
                $cCate = array_merge($clist, $clist2, [$cid]);
                if($cCate){
                    $whereCid = [];
                    foreach($cCate as $k => $c2){
                        $whereCid[] = "find_in_set({$c2},p.cid)";
                    }
                    $where[] = Db::raw(implode(' or ',$whereCid));
                }
            } else {
                $where[] = Db::raw("find_in_set(".$cid.",p.cid)");
            }
        }else{
            if(!getcustom('restaurant_product_category_cashdesk')){
                $cwhere[] = ['is_shop','=',1];
            }
            $clist = Db::name('restaurant_product_category')->where('aid',aid)->where('bid',bid)->where('status',1)->order('sort desc,id')->where($cwhere)->select()->toArray();
            if($clist){
                $whereCid = [];
                foreach($clist as $k=>$v){
                    $whereCid[] =  "find_in_set(".$v['id'].",p.cid)";
                }
                $where[] = Db::raw(implode(' or ',$whereCid));
            }
        }
        $field = "p.id,p.cid,p.pic,p.name,p.sales,p.market_price,p.sell_price,p.lvprice,p.lvprice_data,p.guigedata,p.status,p.ischecked,p.freighttype,p.start_time,p.end_time,p.start_hours,p.end_hours,p.commissionset,p.commissiondata1,p.commissiondata2,p.commissiondata3,p.sellpoint,p.status_week,p.stock";
        if(getcustom('restaurant_weigh')){
            $field .= ",p.product_type";
        }
        if(getcustom('restaurant_product_package')){
            $field .= ",p.packagedata,p.package_price";
        }
//        $count = 0 + Db::name('restaurant_product')->where($where)->count();
        if(getcustom('restaurant_product_jialiao')){
            $field .= ",p.jl_is_selected,p.jl_total_limit";
        }
        $data = Db::name('restaurant_product')->alias('p')
            ->join('restaurant_product_guige g','p.id=g.product_id')
            ->group('p.id')
            ->field($field)
            ->where($where)->page($page, $limit)
            ->order('p.sort desc,p.id desc')
            ->select()->toArray();
        $cdata = Db::name('restaurant_product_category')->where('aid', aid)->column('name', 'id');
        if (empty($data)) $data = [];
        $status = [];
        foreach ($data as $k => $v) {
            $v['cid'] = explode(',', $v['cid']);
            $data[$k]['cname'] = null;
            if ($v['cid']) {
                foreach ($v['cid'] as $cid) {
                    if ($data[$k]['cname'])
                        $data[$k]['cname'] .= ' ' . $cdata[$cid];
                    else
                        $data[$k]['cname'] .= $cdata[$cid];
                }
            }
            $data[$k]['cname2'] = '';
            $data[$k]['bname'] = '';
            $is_operate_status = 1;
            if ($v['status'] == 2) { //设置上架时间
                if (strtotime($v['start_time']) <= time() && strtotime($v['end_time']) >= time()) {
                    $data[$k]['status'] = 1;
                } else {
                    $data[$k]['status'] = 0;
                }
                $is_operate_status  =0;
            }
            if ($v['status'] == 3) { //设置上架周期
                $start_time = strtotime(date('Y-m-d ' . $v['start_hours']));
                $end_time = strtotime(date('Y-m-d ' . $v['end_hours']));
                if (($start_time < $end_time && $start_time <= time() && $end_time >= time()) || ($start_time >= $end_time && ($start_time <= time() || $end_time >= time()))) {
                    $data[$k]['status'] = 1;
                } else {
                    $data[$k]['status'] = 0;
                }
                $is_operate_status  =0;
            }
            $data[$k]['is_operate_status'] = $is_operate_status;
            if ($v['bid'] == -1) $data[$k]['sort'] = $v['sort'] - 1000000;
            $guige = Db::name('restaurant_product_guige')->where('product_id', $v['id'])->select()->toArray();
            if (count($guige) > 1) {
                $data[$k]['guige_num'] = count($guige);
            } else {
                $data[$k]['guige_num'] = 1;
            }
            $guigeks = [];
            foreach ($guige as  $gg){
                $guigeks[$gg['ks']] = $gg;
            }
            $data[$k]['guigelist'] = $guigeks ?? [];
            $data[$k]['guigedata'] = json_decode($v['guigedata'],true);
            $data[$k]['jialiaodata'] = [];
            $jldata = [];
            if(getcustom('restaurant_product_jialiao')){
                $jldata = Db::name('restaurant_product_jialiao')->where('proid',$v['id'])->select()->toArray();
            }
            $data[$k]['jldata'] = $jldata;
            if(getcustom('restaurant_product_package')){
                $packagedata = json_decode($v['packagedata'],true);
                foreach($packagedata as $key =>$val){
                    $prolist = $val['prolist'];
                    if($prolist){
                        foreach($prolist as $pk=>$pro){
                            $product =  Db::name('restaurant_product')->where('id',$pro['proid'])->field('pic,guigedata')->find();
                            $propic =$product['pic']; 
                            if($pro['ggid'] > 0){
                                $ggpic = Db::name('restaurant_product_guige')->where('id',$pro['ggid'])->value('pic');
                                $prolist[$pk]['pic'] =$ggpic?$ggpic:$propic;
                            }else{
                                $package_gglist = Db::name('restaurant_product_guige')->where('aid',aid)->where('product_id',$pro['proid'])->field('id as ggid,name as ggname,sell_price,pic,ks')->select()->toArray();
                                $ggpic = $package_gglist[0]['pic'];
                                $prolist[$pk]['pic'] =$ggpic?$ggpic:$propic;
                                $guigeks = [];
                                foreach ($package_gglist as  $gg){
                                    $guigeks[$gg['ks']] = $gg;
                                }
                                $prolist[$pk]['guigedata'] = json_decode($product['guigedata'],true);
                                $prolist[$pk]['guigelist'] = $guigeks ?? [];
                            }
                           
                        }
                    }
                    $packagedata[$key]['prolist'] =  $prolist;
                }
                $data[$k]['packagedata'] = json_encode($packagedata,JSON_UNESCAPED_UNICODE);
            }
            $status[$k] = $data[$k]['status'];
        }
//        print_r($data);
        //下架排到最下面
        array_multisort($status,SORT_DESC,$data);
        return $this->json(1, 'ok', $data);
    } 
    //操作上下架
    public function setProductStatus(){
        if(getcustom('restaurant_cashdesk_product_operate')){
            $status = input('param.status');
            $proid = input('param.proid');
            $product = Db::name('restaurant_product')->where('aid',aid)->where('bid',bid)->where('id',$proid)->find();
            if(!$product){
                return $this->json(0,'请选择需操作的菜品');
            }
            Db::name('restaurant_product')->where('aid',aid)->where('bid',bid)->where('id',$proid)->update(['status' => $status]);
            return $this->json(1,'成功');
        }
    }
    //设置每日库存 规格的列表
    public function getProductSkuList(){
        if(getcustom('restaurant_cashdesk_guige_stockdaily')){
            $proid = input('param.proid');
            $list = Db::name('restaurant_product_guige')->where('aid',aid)->where('product_id',$proid)->field('id,name,pic,sell_price,stock_daily,sales_daily')->select()->toArray();
            foreach($list as $key=>$val){
                $sy_stock = $val['stock_daily'] - $val['sales_daily'];
                $list[$key]['sy_stock'] = $sy_stock<=0?0:$sy_stock;
            }
            return $this->json(1,'成功',$list?$list:[]);
        }
    }
    public function setSkuStockDaily(){
        if(getcustom('restaurant_cashdesk_guige_stockdaily')) {
            $skudata = input('param.skudata');
            if (!$skudata) return $this->json(0, '参数有误');
            foreach ($skudata as $key => $val) {
                $update = ['stock_daily' => $val['stock_daily']];
                Db::name('restaurant_product_guige')->where('aid', aid)->where('id', $val['id'])->update($update);
            }
            //更新产品的
            $proid = Db::name('restaurant_product_guige')->where('aid',aid)->where('id',$skudata[0]['id'])->value('product_id');
            if($proid){
                //查询所有规格
                $total_stock_daily = Db::name('restaurant_product_guige')->where('aid', aid)->where('product_id',$proid)->sum('stock_daily');
                Db::name('restaurant_product')->where('aid',aid)->where('id',$proid)->update(['stock_daily' => $total_stock_daily]);
            }
            return $this->json(1, '操作成功');
        }
    }
    
    public function createorder()
    {
        $info = input('param.');
        if(!empty($info['tel']) && !checkTel($info['tel'])){
            return $this->json(0, '请检查手机号格式');
        }
        if(empty($info['tableId'])) {
            return $this->json(0, '请选择餐桌');
        }

        $table = Db::name('restaurant_table')->where('aid',aid)->where('bid', bid)->where('id',$info['tableId'])->find();
        if(empty($table)) {
            return $this->json(0, '餐桌不存在');
        }
        if($table['status'] != 0 || $table['orderid']){
            return $this->json(0, '餐桌当前状态不可接受新订单，请检查');
        }
        $tea_fee = 0;
        $shop_set = Db::name('restaurant_shop_sysset')->where('aid',aid)->where('bid',bid)->find();
        if($shop_set['tea_fee_status']==1){
            $tea_fee = $shop_set['tea_fee']>0 ? $shop_set['tea_fee'] * $info['renshu'] : 0;
        }
        $insert = [
            'aid' => aid,
            'bid' => $table['bid'] ? $table['bid'] : 0,
            'mid' => 0,
            'ordernum' => \app\commons\Common::generateOrderNo(aid,'restaurant_shop_order'),
            'tableid' => $info['tableId'],
            'renshu' => $info['renshu'],
            'tel' => $info['tel'],
            'message' => $info['message'],
            'linkman' => $info['linkman'],
            'cashdesk_id' => $info['cashdesk_id'],
            'platform' => 'restaurant_cashdesk',
            'createtime' => time(),
             'tea_fee' => $tea_fee,
            'status' => 0,
            'uid' => $this->user['id']
        ];
        $insert['title'] = '堂食订单:' . $insert['ordernum'];
        if(getcustom('restaurant_table_timing')){
            if($table['timing_fee_type'] >0){
                $insert['timeing_start'] = strtotime(date('Y-m-d H:i',time())); 
            }
        }
        //
        $orderid = Db::name('restaurant_shop_order')->insertGetId($insert);

        //更新餐桌状态
        Db::name('restaurant_table')->where('aid',aid)->where('bid', bid)->where('id',$info['tableId'])->update(['status' => 2, 'orderid' => $orderid]);
        if(getcustom('restaurant_table_default_product')){
            //创建桌台订单时，开启默认餐品，直接加入
            $tabledata =   Db::name('restaurant_table')->where('aid',aid)->where('bid', bid)->where('id',$info['tableId'])->field('default_product_status,default_product_bxdata,default_product_kxdata')->find();
            if($tabledata['default_product_status']){
                $bxdata = json_decode($tabledata['default_product_bxdata'],true);
                $kxdata = json_decode($tabledata['default_product_kxdata'],true);
                $ogdata =[
                    'aid' => aid,
                    'bid' =>bid,
                    'mid' =>0,
                    'orderid' => $orderid,
                    'ordernum'=> $insert['ordernum'],
                    'status' => 0,
                ];
                $bx_ogdata = [];
                foreach($bxdata as $key=>$val){
                    $bxogdata =$ogdata; 
                    $product = Db::name('restaurant_product')->where('id',$val['proid'])->field('pic,procode')->find();
                     $guige =  Db::name('restaurant_product_guige')->where('id',$val['ggid'])->field('cost_price,pic,sell_price')->find();
                    $totalprice = dd_money_format($val['num'] * $guige['sell_price']);
                    $bxogdata['proid'] = $val['proid'];
                    $bxogdata['name'] = $val['proname'];
                    $bxogdata['pic'] = $guige['pic']?$guige['pic']:$product['pic'];
                    $bxogdata['procode'] = $product['procode'];
                    $bxogdata['ggid'] = $val['ggid'];
                    $bxogdata['ggname'] = $val['ggname'];
                    $bxogdata['num'] = $val['num'];
                    $bxogdata['cost_price'] = $guige['cost_price'];
                    $bxogdata['sell_price'] = $guige['sell_price'];
                    $bxogdata['totalprice'] = $totalprice;
                    $bxogdata['createtime'] =  time();
                    $bxogdata['is_must_select'] = 1;
                    $bx_ogdata[] = $bxogdata;
                }
            
                if($bx_ogdata){
                    Db::name('restaurant_shop_order_goods')->insertAll($bx_ogdata);
                }
                $kx_ogdata = [];
                foreach($kxdata as $key=>$val){
                    $kxogdata = $ogdata;
                    $product = Db::name('restaurant_product')->where('id',$val['proid'])->field('pic,procode')->find();
                    $guige =  Db::name('restaurant_product_guige')->where('id',$val['ggid'])->field('cost_price,pic,sell_price')->find();
                    $totalprice = dd_money_format($val['num'] * $guige['sell_price']);
                    $kxogdata['proid'] = $val['proid'];
                    $kxogdata['name'] = $val['proname'];
                    $kxogdata['pic'] = $guige['pic']?$guige['pic']:$product['pic'];
                    $kxogdata['procode'] = $product['procode'];
                    $kxogdata['ggid'] = $val['ggid'];
                    $kxogdata['ggname'] = $val['ggname'];
                    $kxogdata['num'] = $val['num'];
                    $kxogdata['cost_price'] = $guige['cost_price'];
                    $kxogdata['sell_price'] = $guige['sell_price'];
                    $kxogdata['totalprice'] = $totalprice;
                    $kxogdata['createtime'] =  time();
                    $kx_ogdata[] = $kxogdata;
                }
                if($kx_ogdata){
                    Db::name('restaurant_shop_order_goods')->insertAll($kx_ogdata);
                }
            }
        }
        return $this->json(1,'下单成功，请开始点餐',['id'=>$orderid]);
    }
    /**
     * @description 加入餐饮收银台
     */
    public function addToOrder()
    {
        $cashier_id = input('param.cashdesk_id/d', 0);
        $proid = input('param.proid', 0);
        $barcode = input('param.barcode', 0);
        $ggid = input('param.ggid/d', 0);
        $num = input('param.num/d', 1);
        $tableid = input('param.tableid', 0);
        $price =   input('param.price',0);
        if ($num < 0) {
            $num = 1;
        }
        $is_bar_table =0;
        if(getcustom('restaurant_bar_table_order')){
            if($tableid ==0){
                $is_bar_table = 1;
                if(!$this->user['cashdesk_mdid']){
                    return $this->json(0,'请先绑定门店后进行点餐');
                }
            }
        }
        
        if(!$tableid && $is_bar_table==0){
            return $this->json(0,'请选择桌台后再进行点餐');
        }
        //如果是扫码获得的条形码信息
        if($barcode){
            //因guige中没有bid，先查出aid所有规格中符合编码的所有产品ID，再确定该商户中是哪个产品ID,确定后再以产品ID和编码确定规格信息
            $proids = Db::name('restaurant_product_guige')->where('aid',aid)->where('barcode',$barcode)->column('product_id');
            if(empty($proids)){
                $product = Db::name('restaurant_product')->where('aid',aid)->where('bid',bid)->where('procode',$barcode)->find();
                if(empty($product)) return $this->json(0, '未查询到相关菜品');
                $proid = $product['id'];
                $ggid = Db::name('restaurant_product_guige')->where('product_id', $proid)->value('id');
            }else{
                $product = Db::name('restaurant_product')->where('aid',aid)->where('bid',bid)->where('id','in',$proids)->find();
                
                if($product){
                    $ggid = Db::name('restaurant_product_guige')->where('aid',aid)->where('product_id', $product['id'])->where('barcode',$barcode)->value('id');
                    $proid = $product['id'];
                }else{
                    $product = Db::name('restaurant_product')->where('aid',aid)->where('bid',bid)->where('procode',$barcode)->find();
                    if(empty($product))return $this->json(0, '未查询到相关菜品');
                    $proid = $product['id'];
                    $ggid = Db::name('restaurant_product_guige')->where('aid',aid)->where('product_id', $proid)->value('id');
                }
            }
            $num = 1;
           
        }
        if($proid == -99){
            if (empty($price) || !is_numeric($price)) {
                return $this->json(0, '请输入收款金额');
            }
            $product['id'] = -99;
            $product['bid'] = bid;
            $product['name'] = '直接收款';
            $product['sell_price'] = $price;
            $product['pic'] = '';
            $product['protype'] = 2;
        }
        else{
            $product = Db::name('restaurant_product')->where('aid',aid)->where('bid',bid)->where('id',$proid)->find();
            if(!$product ||!$proid){
                return $this->json(0,'请选择菜品');
            }
            if($product['status']==0){
                return $this->json(0,'菜品未上架');
            }
            if($product['status']==2 && (strtotime($product['start_time']) > time() || strtotime($product['end_time']) < time())){
                return $this->json(0,'菜品未上架');
            }
            if($product['status']==3){
                $start_time = strtotime(date('Y-m-d '.$product['start_hours']));
                $end_time = strtotime(date('Y-m-d '.$product['end_hours']));
                if(($start_time < $end_time && ($start_time > time() || $end_time < time())) || ($start_time >= $end_time && ($start_time > time() && $end_time < time()))){
                    return $this->json(0,'菜品未上架');
                }
            }
            //周几可点
            $week = date("w");
            if($week==0) $week = 7;
            $status_week = explode(',',$product['status_week']);
            if(!in_array($week,$status_week)){
                $order_day = \app\customs\Restaurant::getStatusWeek($status_week);
                if($order_day) $order_day = '，仅限'.$order_day;
                return $this->json(0,'['.$product['name'].']今日不可点'.$order_day);
            }
            $product['protype'] = 1;
            $guige = Db::name('restaurant_product_guige')->where('id',$ggid)->find();
            $deletegg = 1;
            if(getcustom('restaurant_product_package')){
                $package_data =  input('param.package_data');
                //$package_data =  json_decode(input('param.package_data'),true);
                if($package_data){
                    $deletegg = 0;
                    foreach($package_data as $pk=>$pv){
                        unset($pv['pic']); 
                        $package_data[$pk] = $pv;
                    }
                }
            }
            if(!$guige && $deletegg){
                return $this->json(0,'产品该规格不存在或已下架');
            }
            $is_check_stock = 1;
            if(getcustom('restaurant_weigh')){
                //称重商品不校验库存
                if($product['product_type'] ==1){
                    $is_check_stock = 0;
                }
            }
            if($is_check_stock && $guige) {
                if ($guige['stock'] < $num || $guige['stock_daily'] - $guige['sales_daily'] < $num) {
                    return $this->json(0, '库存不足');
                }
            }
        }

        //查询是否存在商品
        if(getcustom('restaurant_product_jialiao')){
            $jldata = input('param.jldata');
            // $jialiaoldata = json_decode($jldata,true);
            $njlprice = 0;
            $njltitle = '';
            foreach($jldata as $key=>$val){
                if($val['num'] >0){
                    $njlprice += $val['num'] * $val['price'];
                    $njltitle .=$val['title'].'*'.$val['num'].'/';
                }
            }
            $njltitle = rtrim($njltitle,'/');
        }
        $orderdata = [
            'aid' => aid,
            'bid' => bid,
            'mid' => 0,
            'tableid' => $tableid,
            'platform' => 'restaurant_cashdesk',
             'cashdesk_id' => $cashier_id
        ];
        $owhere[] =['aid','=',aid];
       
        if($is_bar_table ==0){
            $table = Db::name('restaurant_table')->where('aid',aid)->where('id', $tableid)->find();
            $owhere[] =['id','=',$table['orderid']];
        }
        if(getcustom('restaurant_bar_table_order')){
            if($is_bar_table ==1){
                $orderid = Db::name('restaurant_shop_order')->where('aid',aid)->where('bid',bid)->where('tableid',0)->where('status',0)->where('cashdesk_id',$cashier_id)->order('id desc')->value('id');
                if(input('param.orderid','')){
                    //如果有传过来的orderid 使用传过来的
                    $orderid = input('param.orderid');
                }
                $owhere[] =['id','=',$orderid];
                $table['orderid'] =    $orderid;
            }
        }
        $order =  Db::name('restaurant_shop_order')->where($owhere)->find();
        if($table['orderid'] ){ //桌台存在订单 并且订单状态是0 的时候
            if($order['status'] ==1 ||$order['status'] ==3){
                return $this->json(0, '已结算完成，请清理桌台');
            }
            $sell_price =  $guige['sell_price']?$guige['sell_price']:$product['sell_price'];
            if(getcustom('restaurant_product_jialiao')){
                $sell_price = $sell_price + $njlprice ;
            }
            if(getcustom('restaurant_product_package')){
                $add_price = input('param.add_price');
                $sell_price = dd_money_format($product['package_price'] + $add_price);
            }
            $thisprice = dd_money_format($sell_price*$num);
            $product_price = bcadd($order['product_price'],$thisprice,2);
            $totalprice = $product_price;
            
            $orderdata['totalprice'] = $totalprice;
            $orderdata['product_price'] = $product_price;
            $orderdata['ordernum'] = $order['ordernum'];
            Db::name('restaurant_shop_order')->where('id', $table['orderid'])->update($orderdata);
            $orderid = $order['id'];
        }else{
            $sell_price =  $guige['sell_price']?$guige['sell_price']:$product['sell_price'];
            if(getcustom('restaurant_product_jialiao')){
                $sell_price = $sell_price + $njlprice ;
            }
            if(getcustom('restaurant_product_package')){
                $add_price = input('param.add_price');
                $sell_price = dd_money_format($product['package_price'] + $add_price);
            }
            
            $totalprice = $product_price =   $sell_price* $num;
            $orderdata['totalprice'] =  $totalprice;
            $orderdata['ordernum'] = date('ymdHis').rand(100000,999999);
            $orderdata['product_price'] =  $product_price;
            $orderdata['createtime'] =  time();
            if(getcustom('restaurant_bar_table_order')){
                $orderdata['mdid'] = $this->user['cashdesk_mdid'];
                $orderdata['is_bar_table_order'] = $is_bar_table;
            }
            if(getcustom('restaurant_table_timing')){
                if($table['timing_fee_type'] >0){
                    $orderdata['timeing_start'] = strtotime(date('Y-m-d H:i',time()));
                }
            }
            $orderid = Db::name('restaurant_shop_order')->insertGetId($orderdata);
             Db::name('restaurant_table')->where('aid',aid)->where('id', $tableid)->update(['orderid' =>$orderid,'status' => 2]);
        }
       
        $ordergoods = [];
        if($proid > 0){
            $ogwhere[] = ['orderid' ,'=',$orderid];
            $ogwhere[] = ['proid' ,'=',$proid];
            $ogwhere[] = ['ggid' ,'=',$ggid];
            if(getcustom('restaurant_cashdesk_link_table')){
                $ogwhere[] = ['tableid' ,'=',$tableid];  
            }
            if(getcustom('restaurant_product_jialiao')){
                if($njltitle){
                    $ogwhere[] = ['njltitle' ,'=',$njltitle];
                }
            }
            if(getcustom('restaurant_product_package')){
                if($package_data){
                    $ogwhere[] = ['package_data','=',json_encode($package_data,JSON_UNESCAPED_UNICODE)];
                }
            }
            $ordergoods = Db::name('restaurant_shop_order_goods')->where($ogwhere)->find();
        }
        if($ordergoods){
            if($product['limit_per'] > 0 && $ordergoods['num'] >= $product['limit_per']){ //每单限购
                return json(['status'=>0,'msg'=>$product['name'].'每单限购'.$product['limit_per'].'份']);
            }
            $totalprice = dd_money_format( $ordergoods['totalprice'] + $num * $guige['sell_price']);
           
            if(getcustom('restaurant_product_jialiao')){
                $totalprice =  dd_money_format($totalprice +$njlprice);
            }
            if(getcustom('restaurant_product_package')){
                $add_price = input('param.add_price');
                $totalprice = dd_money_format($totalprice + $product['package_price'] + $add_price);
            }
            $ogdata['totalprice'] = $totalprice;
            $ogdata['num'] = $ordergoods['num'] + $num;
            Db::name('restaurant_shop_order_goods')->where('id',$ordergoods['id'])->update($ogdata);
            $ogid =  $ordergoods['id'];
        }else{
            $sell_price =  $guige['sell_price']? $guige['sell_price']:$product['sell_price'];
            $cost_price =  $guige['cost_price']?$guige['cost_price']:$product['cost_price'];
            if(getcustom('restaurant_product_package')){
                if($package_data){
                    $add_price = input('param.add_price');
                    $sell_price = dd_money_format($product['package_price'] + $add_price);
                    $cost_price =  $sell_price;
                    $ogdata['package_data'] = json_encode($package_data,JSON_UNESCAPED_UNICODE);
                    $ogdata['is_package'] =1;
                }
            }
            $totalprice = dd_money_format($num * $sell_price);
           
            if(getcustom('restaurant_product_jialiao')){
                $totalprice =  dd_money_format($totalprice +$njlprice);
                $ogdata['njltitle'] = $njltitle;
                $ogdata['njlprice'] = $njlprice;
            }
            if(getcustom('restaurant_product_package')){
                $ogdata['product_type'] = $product['product_type'];
            }
            $ogdata['aid'] = aid;
            $ogdata['bid'] = $product['bid'];
            $ogdata['mid'] = 0;
            $ogdata['orderid'] = $orderid;
            $ogdata['ordernum'] = $orderdata['ordernum'];
            $ogdata['proid'] = $product['id'];
            $ogdata['name'] = $product['name'];
            $ogdata['pic'] = $product['pic'];
            $ogdata['procode'] = $product['procode'];
            $ogdata['ggid'] = $guige['id']??0;
            $ogdata['ggname'] = $guige['name']??'';
            //$ogdata['cid'] = $product['cid'];
            $ogdata['num'] = $num;
            $ogdata['cost_price'] = $cost_price;
            $ogdata['sell_price'] = $sell_price;
            $ogdata['totalprice'] = $totalprice;
            $ogdata['status'] = 0;
            $ogdata['protype'] = $product['protype'];
            $ogdata['createtime'] = time();
            if(getcustom('restaurant_cashdesk_link_table')){
                $ogdata['tableid'] = $tableid;
            }
            $ogid = Db::name('restaurant_shop_order_goods')->insertGetId($ogdata);
        }
        return $this->json(1, 'ok');
    }
    //加菜
    public function addToOrderMore(){
        $cashier_id = input('param.cashdesk_id/d', 0);
        $orderid = input('param.orderid/d', 0);
        $prodata = input('param.prodata');
        //$prodata = json_decode(input('param.prodata'),true);
        if(!$prodata){
            return $this->json(0,'请先选择菜品');
        }
        $owhere[] =['aid','=',aid];
        $owhere[] =['id','=',$orderid];
        $owhere[] =['cashdesk_id','=',$cashier_id];
        $order =  Db::name('restaurant_shop_order')->where($owhere)->find();
        if(!$order){
            return $this->json(0,'不存在该订单');
        }
        if($order['status'] ==1 ||$order['status'] ==3){
            return $this->json(0, '已结算完成，请清理桌台');
        }
        $ogdata = [];
        $thistotalprice = 0;
        $thistotaljlprice = 0;
        foreach($prodata as $key=>$item){
            $num =  $item['num'];
            $ggid = $item['ggid'];
            $proid = $item['proid'];
            $product = Db::name('restaurant_product')->where('aid',aid)->where('bid',bid)->where('id',$proid)->find();
            if(!$product ||!$proid){
                return $this->json(0,$product['name'].'菜品未上架');
            }
            if($product['status']==0){
                return $this->json(0,$product['name'].'未上架');
            }
            if($product['status']==3){
                $start_time = strtotime(date('Y-m-d '.$product['start_hours']));
                $end_time = strtotime(date('Y-m-d '.$product['end_hours']));
                if(($start_time < $end_time && ($start_time > time() || $end_time < time())) || ($start_time >= $end_time && ($start_time > time() && $end_time < time()))){
                    return $this->json(0,$product['name'].'菜品未上架1');
                }
            }
            //周几可点
            $week = date("w");
            if($week==0) $week = 7;
            $status_week = explode(',',$product['status_week']);
            if(!in_array($week,$status_week)){
                $order_day = \app\customs\Restaurant::getStatusWeek($status_week);
                if($order_day) $order_day = '，仅限'.$order_day;
                return $this->json(0,'['.$product['name'].']今日不可点'.$order_day);
            }
            
            $guige = Db::name('restaurant_product_guige')->where('id',$ggid)->find();
            $deletegg = 1;
            if(getcustom('restaurant_product_package')){
                $package_data = $item['package_data'];
                if($package_data){
                    $deletegg = 0;
                    foreach($package_data as $pk=>$pv){
                        unset($pv['pic']);
                        $package_data[$pk] = $pv;
                    }
                }
            }
            if(!$guige && $deletegg){
                return $this->json(0,'产品该规格不存在或已下架');
            }
            $sell_price =  $item['price']?$item['price']:$guige['sell_price'];
            $cost_price =  $guige['cost_price']?$guige['cost_price']:$product['cost_price'] ;
            if(getcustom('restaurant_product_package')){
                if($package_data){
                    $package_price = $item['add_price'] +  $product['package_price'];
                    $sell_price =  $item['price']?$item['price']: $package_price;
                    $cost_price =  $sell_price;
                }
            }
            $product_price = dd_money_format($sell_price*$num);
            $thistotalprice +=  $product_price;
             $oginsert = [
                'aid' => aid,
                'bid' => bid,
                'mid' => 0,
                'orderid' => $order['id'],
                'ordernum' => $order['ordernum'],
                'proid' => $proid,
                'name' => $product['name'],
                'pic' => $product['pic'],
                'procode' => $product['procode'],
                'ggid' => $ggid??0,
                'ggname' =>$guige['name']??'' ,
                'num' =>$item['num'] ,
                'cost_price' =>$cost_price,
                'sell_price' =>$sell_price ,
                'totalprice' =>$product_price ,
                'status' => 0,
                'protype'=> 1,
                'createtime' => time(),
            ];
            if(getcustom('restaurant_product_jialiao')){
                $jldata = $item['jldata'];
               
                // $jialiaoldata = json_decode($jldata,true);
                $njlprice = 0;
                $njltitle = '';
                foreach($jldata as $key=>$val){
                    if($val['num'] >0){
                        $njlprice += $val['num'] * $val['price'];
                        $njltitle .=$val['title'].'*'.$val['num'].'/';
                    }
                }
                $njltitle = rtrim($njltitle,'/');
                $oginsert['njlprice'] = $njlprice;
                $oginsert['njltitle'] = $njltitle;
                $thistotaljlprice +=$njlprice;
            }
            if(getcustom('restaurant_product_package')){
                if($package_data){
                    $oginsert['package_data'] = json_encode($package_data,JSON_UNESCAPED_UNICODE);
                    $oginsert['is_package'] = 1;
                }
            }
            if(getcustom('restaurant_cashdesk_link_table')){
                $tablid  = input('param.tableid/d');
                $oginsert['tableid'] = $tablid;
            }
            $ogdata []= $oginsert;
        }
        if($ogdata){
            $ogid = [];
            foreach ($ogdata as $key=>$og){
                $ogid[] = Db::name('restaurant_shop_order_goods')->insertGetId($og);
            }
            $orderGoods = Db::name('restaurant_shop_order_goods')->alias('og')->where('orderid',$orderid)->where('og.id', 'in', $ogid)->leftJoin('restaurant_product p', 'p.id=og.proid')
                ->fieldRaw('og.*,p.area_id')->select()->toArray();
            $order['isaddproduct'] = 1;
            \app\customs\Restaurant::print('restaurant_shop',$order, $orderGoods);
             //更新订单金额
            $product_price = bcadd($order['product_price'],$thistotalprice,2);
            $totalprice =  dd_money_format($order['totalprice'] + $thistotalprice + $thistotaljlprice);
            $orderdata['product_price'] =$product_price;
            $orderdata['totalprice'] =$totalprice ;
            Db::name('restaurant_shop_order')->where('id', $order['id'])->update($orderdata);
            return $this->json(1, '添加成功');
        }
      
        
    }
    //获取 桌台下的订单
    public function getWaitPayOrder(){
        $cashier_id = input('param.cashdesk_id/d', 0);
        $remove_zero = input('param.remove_zero/d', 0);
        $tableid = input('param.tableid');
        $mid = input('param.mid/d', 0); //会员ID 使用会员时
        $order = $this->getWaitOrder($cashier_id,$tableid);
        if (empty($order)) {
            return $this->json(1, '无待结算订单', '');
        }
        $set = Db::name('restaurant_cashdesk')->where('id', $cashier_id)->where('bid', bid)->find();
       
        if ($order['remove_zero'] != $remove_zero) {
            Db::name('restaurant_shop_order')->where('id', $order['id'])->update(['remove_zero' => $remove_zero, 'remove_zero_length' => $set['remove_zero_length'] ?? 0]);
        }
        $gwhere[] = ['orderid','=',$order['id']];
      
        $goodslist =   Db::name('restaurant_shop_order_goods')->where($gwhere)->select()->toArray();
        if (empty($goodslist)) $goodslist = [];
        $totalprice = 0;
        foreach ($goodslist as $gk => &$goods) {
            $stock = 0;
            if ($goods['protype'] == 1) {//1商品 2直接收款（proid=-99)
                $stock = Db::name('restaurant_product_guige')->where('product_id', $goods['proid'])->where('id', $goods['ggid'])->value('stock');
            }
            if($mid && $goods['is_gj'] == 0){
                $goods['sell_price'] =  $this->getVipPrice($goods['proid'],$mid, $goods['ggid'],$goods['sell_price']);
            }
            $goods['totalprice'] = dd_money_format($goods['sell_price'] * $goods['num']);
          
            if(getcustom('restaurant_product_jialiao')){
                $goods['totalprice'] = dd_money_format($goods['totalprice'] + $goods['njlprice']*$goods['num']);
                $goods['sell_price'] =dd_money_format( $goods['sell_price'] + $goods['njlprice']);
            }
            if(getcustom('restaurant_weigh')){
                $goods['num'] = floatval($goods['num']);
            }
            $totalprice = dd_money_format($totalprice + $goods['totalprice']);
            $goods['stock'] = $stock ?? 0;
            if(getcustom('restaurant_product_package')){
                if($goods['package_data']){
                    $package_data = json_decode($goods['package_data'],true);
                    $ggtext = [];
                    foreach($package_data as $pdk=>$pd){
                        $t = 'x'.$pd['num'].' '.$pd['proname'];
                        if($pd['ggname'] !='默认规格'){
                            $t .='('.$pd['ggname'].')';
                        }
                        $ggtext[] = $t;
                    }
                    $goodslist[$gk]['ggtext'] =$ggtext;
                }
            }
        }
        $order['prolist'] = $goodslist ?? [];
        $order['remove_zero'] = $remove_zero;
        $order['remove_zero_length'] = $set['remove_zero_length'] ?? 0;
        if ($remove_zero == 1) {
            $zeroinfo = $this->removeZero($totalprice,$cashier_id);
            $order['totalprice'] = $zeroinfo['totalprice'];
            $order['discount_money'] = $zeroinfo['moling_money'];
        }else{
            $order['totalprice'] = $totalprice;
            $order['discount_money'] = 0;
        }
        return $this->json(1, 'ok', $order);
    }

    /**
     * @description 餐饮收银台改价
     */
    public function cashierChangePrice()
    {
        $cashier_id = input('param.cashdesk_id/d', 0);
        $id = input('param.id', 0);
        $price = input('param.price', 0);
        $tableid = input('param.tableid');
        $order = $this->getWaitOrder($cashier_id,$tableid);
        if (empty($order)) {
            return $this->json(0, '没有待结算订单不支持该操作');
        }
        $ordergods = Db::name('restaurant_shop_order_goods')->where('orderid', $order['id'])->where('id', $id)->find();
        if(!$ordergods){
            return $this->json(0, '数据有误');
        }
        Db::name('restaurant_shop_order_goods')->where('orderid', $order['id'])->where('id', $id)->update(['sell_price' => $price,'is_gj' => 1]);
        return $this->json(1, 'ok');
    }
    /**
     * @description 餐饮收银台商品数量增减
     */
    public function cashierChangeNum()
    {
        $cashier_id = input('param.cashdesk_id/d', 0);
        $tableid = input('param.tableid');
        $id = input('param.id', 0);
        $num = input('param.num/d', 0);
        $order = $this->getWaitOrder($cashier_id,$tableid);
        if (empty($order)) {
            return $this->json(0, '没有待结算订单不支持该操作');
        }
        $ordergoods = Db::name('restaurant_shop_order_goods')->where('orderid', $order['id'])->where('id', $id)->where('aid',aid)->where('bid',bid)->find();
        if (empty($ordergoods)) {
            return $this->json(0, '数据有误');
        }
        $product = Db::name('restaurant_product')->where('aid',aid)->where('bid',bid)->where('id',$ordergoods['proid'])->find();
//        if(getcustom('restaurant_weigh')){
//            $weigh = input('param.weigh');
//            if($ordergoods['product_type'] ==1 && $weigh >0){
//                Db::name('restaurant_shop_order_goods')->where('orderid', $order['id'])->where('id', $id)->update(['weigh' => $weigh]);
//                return $this->json(1, 'ok');
//            }
//        }
        if($product['limit_per'] > 0 && $num > $product['limit_per']){ //每单限购
            return json(['status'=>0,'msg'=>$product['name'].'每单限购'.$product['limit_per'].'份']);
        }
        if ($num < 1) {
            if(getcustom('restaurant_table_default_product')){
                if($ordergoods['is_must_select'] ==1){
                    return json(['status'=>0,'msg'=>'必选产品，不能删除']);
                }
            }
            //删除该商品
            Db::name('restaurant_shop_order_goods')->where('orderid', $order['id'])->where('id', $id)->delete();
        } else {
            $totalprice = $ordergoods['sell_price'] * $num;
            if(getcustom('restaurant_product_jialiao')){
                $totalprice = $totalprice + $ordergoods['njlprice']* $num;
            }
            Db::name('restaurant_shop_order_goods')->where('orderid', $order['id'])->where('id', $id)->update(['num' => $num,'totalprice' => $totalprice]);
        }
        $orderresult = $this->getOrderPrice($order);
        $orderprice = dd_money_format($orderresult['product_price']);
        Db::name('restaurant_shop_order')->where('aid',aid)->where('bid',bid)->where('id',$order['id'])->update(['product_price' => $orderprice,'totalprice' =>$orderprice ]);
        return $this->json(1, 'ok');
    }


    /**
     * 修改订单备注
     */
    public function cashierChangeRemark()
    {
        $orderid = input('param.orderid');
        $remark = input('param.remark');
        Db::name('restaurant_shop_order')->where('bid', bid)->where('aid', aid)->where('id', $orderid)->update(['remark' => $remark]);
        return $this->json(1, '备注修改成功');
    }

    /**
     *订单商品表 单独设置备注
     */
    public function  setOrderGoodsRemark(){
        if(getcustom('restaurant_cashdek_ordergoods_remark')){
            $cashier_id = input('param.cashdesk_id/d', 0);
            $id = input('param.id', 0);
            $tableid = input('param.tableid');
            $remark = input('param.remark');
            $order = $this->getWaitOrder($cashier_id,$tableid);
            if (empty($order)) {
                return $this->json(0, '没有待结算订单不支持该操作');
            }
            if (empty($remark)) {
                return $this->json(0, '请输入备注');
            }
            $ordergods = Db::name('restaurant_shop_order_goods')->where('aid',aid)->where('bid',bid)->where('orderid', $order['id'])->where('id', $id)->find();

            if(!$ordergods){
                return $this->json(0, '数据有误');
            }
            Db::name('restaurant_shop_order_goods')->where('orderid', $order['id'])->where('id', $id)->update(['remark' => $remark]);
            return $this->json(1, 'ok');
        }
        
    }
    
    /**
     * @description 清空订单
     */
    public function delCashierOrder()
    {
        $orderid = input('param.orderid/d', 0);
       $resg = Db::name('restaurant_shop_order_goods')->where('aid',aid)->where('bid',bid)->where('orderid', $orderid)->delete();
        return $this->json(1, '删除成功');
    }
    
    //待收银订单status=0
    protected function getWaitOrder($cashier_id = 0,$tableid=0)
    {
        $orderid = Db::name('restaurant_table')->where('id',$tableid)->value('orderid');
         if (getcustom('restaurant_bar_table_order')){
             if($tableid == 0){
                 $orderid = Db::name('restaurant_shop_order')->where('aid',aid)->where('bid',bid)->where('tableid',0)->where('status',0)->where('cashdesk_id',$cashier_id)->order('id desc')->value('id');
             }
         }
         $status = 0;
        if(getcustom('restaurant_shop_guadan_order')){
            if(input('param.orderid')){
                $orderid = input('param.orderid',0);
                $status = -1; 
            }
        }
        $where = [];
        $where['status'] = $status;
        $where['aid'] = aid;
        $where['bid'] = bid;
        $where['id'] = $orderid;
        $order = Db::name('restaurant_shop_order')->where($where)->find();
        if (empty($order)) $order = [];
        return $order;
    }
    
    //收款预览
    public function payPreview(){
        $cashier_id = input('param.cashdesk_id/d', 0);
        $tableid = input('param.tableid',0);
        $couponrid = input('param.couponid/d', 0);
        $mid = input('param.mid/d', 0);
        $userscore = input('param.userscore/d', 0);
        $order = $this->getWaitOrder($cashier_id,$tableid);
        if (empty($order)) {
            return $this->json(0, '无待结算订单');
        }
        //优惠券
        $bid = bid;
        $userinfo = [];
        $newcouponlist = [];
        //计算总价
        $awhere[] = ['orderid','=',$order['id']];
//        if(getcustom('restaurant_cashdesk_link_table')){
//            if($order['tableid'] != $tableid){
//                $awhere[] = ['tableid','=',$tableid];
//            }
//        }
        $allgoods = Db::name('restaurant_shop_order_goods')->where($awhere)->select()->toArray();
        if (empty($allgoods)) {
            return $this->json(0, '无待结算菜品');
        }
        $member = [];
        //商城商品
        $totalprice = 0;
        $manjian_money = 0;
        $buydata = [];
        $proids = [];
        $totalnum = 0;
        $not_cuxiao_num=0;//不进行促销的数量
        $not_cuxiao = 0;
        $not_discount = 0;
        $not_coupon = 0 ;
        foreach ($allgoods as $k => $v) {
            $totalprice += $v['sell_price'] * $v['num'];
            //商城商品
            if ($v['protype'] == 1) {
                $buydata[] = $v;
                $proids[] = $v['proid'];
                $product = Db::name('restaurant_product')->where('id',$v['proid'])->find();
                $ordernum = Db::name('restaurant_shop_order_goods')->where('orderid',$v['orderid'])->where('proid',$v['proid'])->sum('num');
                if($product['limit_start'] > 0 && $ordernum < $product['limit_start']){ //起售份数
                    return  $this->json(0,$product['name'].'最低购买'.$product['limit_start'].'份');
                }
                //库存校验
                $gginfo = Db::name('restaurant_product_guige')->where('aid',aid)->where('id',$v['ggid'])->find();
                if($gginfo && ($gginfo['stock']<$v['num'] || $gginfo['stock_daily'] - $gginfo['sales_daily'] < $v['num']) ){
                    return $this->json(0, $product['name'].'('.$v['ggname'].')'.'库存不足');
                }
                if(getcustom('restaurant_product_jialiao')){
                    if($product['jl_is_discount'] ==0){
                        $not_cuxiao += $v['njlprice'];
                    }
                    if($product['jl_is_discount'] ==0){
                        $not_discount += $product['njlprice'];
                    }
                }
                if(getcustom('restaurant_product_package')){
           
                    if($v['package_data'] ){
                        $pdprice =  $v['sell_price'] * $v['num'];
                        if($product['product_type'] ==2 && $product['package_is_discount'] ==0){
                            $not_discount +=$pdprice ;
                        }
                        if($product['product_type'] ==2 && $product['package_is_cuxiao'] ==0){
                            $not_cuxiao += $pdprice;
                            $not_cuxiao_num +=$v['num'];
                        }
                        if($product['product_type'] ==2 && $product['package_is_coupon'] ==0){
                            $not_coupon += $pdprice;
                        }
                    }
                }
            }
            $totalnum += $v['num'];
            if(getcustom('restaurant_product_jialiao')){
                $totalprice = $totalprice + $v['njlprice'];
            }
            $allgoods[$k]['product'] = Db::name('restaurant_product')->where('id',$v['proid'])->find();
            $allgoods[$k]['guige'] = Db::name('restaurant_product_guige')->where('id',$v['ggid'])->find();
        }
        $cids = [];
        if ($proids) {
            $cidarr = Db::name('restaurant_product')->where('aid',aid)->where('bid', bid)->where('id', 'in', $proids)->column('cid','id');
            if($cidarr){
                foreach ($cidarr as $cval){
                    $cid = explode(',',$cval);
                    $cids = array_merge($cids,$cid);
                }
            }
        }
        $cuxiaolist = [];
        $allcuxiaolist = [];
        if ($mid) {
            $member = Db::name('member')->where('id', $mid)->where('aid',aid)->find();
            $adminset = Db::name('admin_set')->where('aid', aid)->find();
            $userlevel = Db::name('member_level')->where('aid', aid)->where('id', $member['levelid'])->find();
            $level_discount = is_numeric($userlevel['discount'])?$userlevel['discount']:10;
            $userinfo['discount'] = $level_discount;
            $userinfo['score'] = $member['score'];
            $userinfo['score2money'] = $adminset['score2money'];
            $userinfo['dkmoney'] = round($userinfo['score'] * $userinfo['score2money'], 2);
            $userinfo['scoredkmaxpercent'] = $adminset['scoredkmaxpercent'];
            $userinfo['money'] = $member['money'];
            $manjian_money = 0;
            $moneyduan = 0;
            $discount_total_price = $totalprice -  $not_discount;
          
            $leveldk_money = round($discount_total_price * (10-$level_discount) * 0.1, 2);
            //满减活动
            $mjset = Db::name('manjian_set')->where('aid',aid)->find();
            if($mjset && $mjset['status']==1){
                $mjdata = json_decode($mjset['mjdata'],true);
            }else{
                $mjdata = array();
            }
            if($mjdata){
                foreach($mjdata as $give){
                    if(($totalprice - $level_discount)*1 >= $give['money']*1 && $give['money']*1 > $moneyduan){
                        $moneyduan = $give['money']*1;
                        $manjian_money = $give['jian']*1;
                    }
                }
            }
            if($manjian_money > 0){
                $manjian_money = round($manjian_money,2);
            }else{
                $manjian_money = 0;
            }
            $price = $totalprice - $leveldk_money;
            $cr_where = [];
            $cr_where[] = ['aid','=',aid];
            $cr_where[] = ['mid','=',$mid];
            $cr_where[] = ['status','=',0];
            //优惠券类型，默认餐饮券
            $cr_type = [5] ;
            if(getcustom('restaurant_cashdesk_free_coupon'))$cr_type[] = 51;//免费券
            if(getcustom('restaurant_cashdesk_discount_coupon'))$cr_type[] = 10;//折扣券
            $cr_where[] = ['type','in',$cr_type];
            $cr_where[] = ['minprice','<=',$price - $manjian_money - $not_coupon];
            $cr_where[] = ['starttime','<=',time()];
            $cr_where[] = ['endtime','>',time()];
            
            $couponList = Db::name('coupon_record')
                ->where("bid=-1 or bid=".$bid)
                ->where($cr_where)
                ->order('id desc')->select()->toArray();
            if (!$couponList) $couponList = [];
            foreach ($couponList as $k => $v) {
                $couponinfo = Db::name('coupon')->where('aid',aid)->where('id',$v['couponid'])->find();
                if(empty($couponinfo)) continue;
               
                $v['thistotalprice'] = $price - $manjian_money;
                $v['couponmoney'] = $v['money'];//可抵扣金额
                if($couponinfo['fwtype']==2){//指定菜品可用
                    $productids = explode(',',$couponinfo['productids']);
                    
                    if(!array_intersect($proids,$productids)){
                        continue;
                    }
                    $thistotalprice = 0;
                    foreach($buydata['prodata'] as $k2=>$v2){
                        $product = $v2['product'];
                        if(in_array($product['id'],$productids)){
                            $thistotalprice += $v2['guige']['sell_price'] * $v2['num'];
                        }
                    }
                    if($thistotalprice < $v['minprice']){
                        continue;
                    }
                    $v['thistotalprice'] = $thistotalprice;
                    $v['couponmoney'] = min($thistotalprice,$v['money']);//可抵扣金额
                }
                if($couponinfo['fwtype']==1){//指定类目可用
                   
                    $categoryids = explode(',',$couponinfo['categoryids']);
                    $clist = Db::name('restaurant_product_category')->where('pid','in',$categoryids)->select()->toArray();
                    foreach($clist as $kc=>$vc){
                        $categoryids[] = $vc['id'];
                        $cate2 = Db::name('restaurant_product_category')->where('pid',$vc['id'])->find();
                        $categoryids[] = $cate2['id'];
                    }
                    if(!array_intersect($cids,$categoryids)){
                        continue;
                    }
                    if($totalprice < $v['minprice']){
                        continue;
                    }
                    $v['thistotalprice'] = $thistotalprice;
                    $v['couponmoney'] = min($thistotalprice,$v['money']);//可抵扣金额
                }
                $v['endtime'] =date('Y-m-d 00:00',$v['endtime']);
                $tip = $this->getCouponTip($v);
                $v['tip'] = $tip;
                $newcouponlist[] = $v;
                
            }
            
        }
        if(getcustom('restaurant_cashdesk_cuxiao')){
            //促销活动
            $where =[];
            $where[] = ['aid','=',aid];
            $where[] = ['bid','=',$bid];
            $where[] = ['starttime','<',time()];
            $where[] = ['endtime','>',time()];
            $where[] = ['restaurant_cashdesk_status','=',1];
            $totalnum = $totalnum - $not_cuxiao_num;
            $swhere = '';
            if(getcustom('restaurant_the_second_discount')){
                if($totalnum > 1){
                    $swhere = 'or (type=7)';
                }
            }
            $cuxiaolist = Db::name('restaurant_cuxiao')->where("(type in (1,2,3,4) and minprice<=".($totalprice - $manjian_money - $not_cuxiao).") or ((type=5 or type=6) and minnum<=".$totalnum.") ".$swhere)->where($where)->order('is_not_share asc, sort desc')->select()->toArray();
           
            $newcxlist = [];
            foreach($cuxiaolist as $k=>$v){
                $gettj = explode(',',$v['gettj']);
                if(!in_array('-1',$gettj) && !in_array($member['levelid'],$gettj)){ //不是所有人
                    continue;
                }
                if(getcustom('restaurant_cuxiao_activity_day')){
                    //促销活动日
                    if($v['activity_day_type'] ==0){//周几
                        $now_week = date('w');
                        $activity_day_week = explode(',',$v['activity_day_week']);
                        if(!in_array($now_week,$activity_day_week)){
                            continue;
                        }
                    }else{//几号
                        $now_date = date('d');
                        $activity_day_date = explode(',',$v['activity_day_week']);
                        if(!in_array($now_date,$activity_day_date)){
                            continue;
                        }
                    }
                }
                if($v['fwtype']==2){//指定菜品可用
                    $productids = explode(',',$v['productids']);
                    if(!array_intersect($proids,$productids)){
                        continue;
                    }
                    $cuxiao_product_total = 0;
                    if($v['type']==1 || $v['type']==2 || $v['type']==3 || $v['type']==4){//指定菜品是否达到金额要求
                        foreach($buydata as $vpro){
                            if(in_array($vpro['proid'], $productids)){
                                $cuxiao_product_total += $vpro['sell_price'] * $vpro['num'];
                                if($vpro['product']['jl_is_cuxiao'] ==1){
                                    $cuxiao_product_total += $vpro['njlprice'] * $vpro['num'];
                                }
                                if(getcustom('restaurant_product_package')){
                                    if($vpro['product']['product_type'] ==2&& $vpro['product']['package_is_coupon'] ==1){
                                        $cuxiao_product_total += $vpro['sell_price'] * $vpro['num'];
                                    }
                                }
                            }
                        }
                        if($cuxiao_product_total < $v['minprice']){
                            continue;
                        }
                    }
                    if($v['type']==81 || $v['type']==5){//指定菜品是否达到件数要求
                        $thistotalnum = 0;
                        foreach($buydata as $vpro){
                            if(in_array($vpro['proid'], $productids)){
                                $thistotalnum += $vpro['num'];
                            }
                        }
                        if($thistotalnum < $v['minnum']){
                            continue;
                        }
                    }
                  
                }
                if($v['fwtype']==1){//指定类目可用
                    $categoryids = explode(',',$v['categoryids']);
                    $clist = Db::name('restaurant_product_category')->where('pid','in',$categoryids)->select()->toArray();
                    foreach($clist as $kc=>$vc){
                        $categoryids[] = $vc['id'];
                        $cate2 = Db::name('restaurant_product_category')->where('pid',$vc['id'])->find();
                        $categoryids[] = $cate2['id'];
                    }
                    if(!array_intersect($cids,$categoryids)){
                        continue;
                    }
                    if($v['type']==1 || $v['type']==2 || $v['type']==3 || $v['type']==4){//指定菜品是否达到金额要求
                        $cuxiao_cate_total = 0;
                        foreach($allgoods as $vpro){
                            $cuxiao_pro_cidArr = explode(',',$vpro['product']['cid']);
                           
                            if(array_intersect($cuxiao_pro_cidArr, $categoryids)){
                                if(!$vpro['product_type'] || $vpro['product_type'] ==0){
                                    $cuxiao_cate_total += $vpro['guige']['sell_price'] * $vpro['num'];
                                }
                                if($vpro['product']['jl_is_cuxiao'] ==1){
                                    $cuxiao_cate_total += $vpro['njlprice'] * $vpro['num'];
                                }
                                if(getcustom('restaurant_product_package')){
                                    if($vpro['product']['product_type'] ==2&& $vpro['product']['package_is_coupon'] ==1){
                                        $cuxiao_cate_total += $vpro['sell_price'] * $vpro['num'];
                                    }
                                }
                            }
                        }
                        if($cuxiao_cate_total < $v['minprice']){
                            continue;
                        }
                    }
                    if($v['type']==81 || $v['type']==5){//指定类目内菜品是否达到件数要求
                        $thistotalnum = 0;
                        foreach($buydata['prodata'] as $vpro){
                            $cuxiao_pro_cidArr = explode(',',$vpro['product']['cid']);
                            if(array_intersect($cuxiao_pro_cidArr, $categoryids)){
                                $thistotalnum += $vpro['num'];
                            }
                        }
                        if($thistotalnum < $v['minnum']){
                            continue;
                        }
                    }
                }
                if(getcustom('restaurant_the_second_discount')){
                    //第二件折扣时,判断是同产品 还是非同产品，如果必须同产品 判断是否有购买2件以上的，否则不可用
                    if($v['is_one_product'] ==1 && $v['type'] ==7){//判断是否必须同一件商品
                        $is_have_two = 0;
                        foreach ($allgoods as $gk => $gv) {
                            if($gv['num'] > 1){
                                $is_have_two = 1;
                            }
                        }
                        if($is_have_two ==0){
                            continue;
                        }
                    }
                }
                $v['starttime'] =date('Y-m-d H:i:s',$v['starttime']);
                $v['endtime'] =date('Y-m-d H:i:s',$v['endtime']);
                $newcxlist[] = $v;

            }

            $allcuxiaolist =  $cuxiaolist =   $newcxlist;
        }
        //抹零
        $return = [];
        if(getcustom('restaurant_cashdesk_cuxiao')) {
            //如果有选择的 查询后 进行挑选不同类型的不同的促销，否则就使用所有的
            $cuxiaoids = input('param.cuxiaoids');
            if ($cuxiaoids) {
                $cuxiaolist = Db::name('restaurant_cuxiao')->where('aid', aid)->where('bid', bid)->where('id','in',$cuxiaoids)->order('is_not_share asc')->select()->toArray();
                $cuxiaolist =  $cuxiaolist?$cuxiaolist:[];
            }
        }
        $orderResult = $this->getOrderPrice($order,$couponrid,$userscore,$mid,$cuxiaolist);
       
        if($orderResult['status']!=1){
            return $this->json(0, $orderResult['msg']);
        }
        $return['is_scan_coupon'] =0;
        if(getcustom('restaurant_scan_qrcode_coupon')|| getcustom('restaurant_cashdesk_scan_coupon_qrcode')){
            $return['is_scan_coupon'] =1; //展示扫码的按钮
        }
        if(getcustom('restaurant_scan_qrcode_coupon')){
            $coupon_code = input('param.coupon_code');
            $is_scan = input('param.is_scan');
            $qrcode_coupon_money = 0;
            $qrcode_coupon_record = [];
            if($coupon_code && $is_scan){
                $qrcode_return= $this->computeQrcodeCoupon($coupon_code,$order,$orderResult['product_price'],$orderResult['totalprice']);  
                if($qrcode_return['status'] ==0){
                    return $this->json(0, $qrcode_return['msg']);
                }else{
                    $qrcode_coupon_money= $qrcode_return['qrcode_coupon_money'];
                    $qrcode_coupon_record =  $qrcode_return['qrcode_coupon_record'];
                }
            }
            $orderResult['totalprice'] = $orderResult['totalprice'] - $qrcode_coupon_money;
            $return['qrcode_coupon_money'] = $qrcode_coupon_money;
            $return['qrcode_coupon_record'] = $qrcode_coupon_record;
        }
        if(getcustom('restaurant_cashdesk_cuxiao')) {
            if($allcuxiaolist){
                foreach($allcuxiaolist as $key=>$cuxiao){
                    if(in_array($cuxiao['id'],$orderResult['cuxiao_checked'])){
                        $allcuxiaolist[$key]['is_checked'] =1;
                    }else{
                        $allcuxiaolist[$key]['is_checked'] =0;
                    }
                }
            }
        }
        if(getcustom('restaurant_cashdesk_auth_enter')){
            $direct_money = input('param.direct_money',0);//直接优惠价格
            $orderResult['totalprice'] = dd_money_format($orderResult['totalprice'] - $direct_money);
        }
        $return['totalprice'] = dd_money_format($orderResult['product_price'],2);
        $return['final_totalprice'] = dd_money_format($orderResult['totalprice'],2);//实际价格
        $return['leveldk_money'] = $orderResult['leveldk_money'];
        $return['coupon_money'] = $orderResult['coupon_money'];
        $return['cuxiao_checked'] = $orderResult['cuxiao_checked']? $orderResult['cuxiao_checked']:[];
        $return['cuxiao_money'] = $orderResult['cuxiao_money']? $orderResult['cuxiao_money']:0;
        $return['scoredk_money'] = $orderResult['scoredk_money'];
        $return['moling_money'] = $orderResult['moling_money'];
        $return['totalscore'] = $orderResult['totalscore'];
        $return['tea_fee'] = dd_money_format($orderResult['tea_fee'],2);
        $return['manjian_money'] = $manjian_money;
        $return['memberinfo'] = $userinfo??'';
        $return['couponlist'] = $newcouponlist;
        $return['cuxiaolist'] = $allcuxiaolist;
        $set = Db::name('admin_set')->where('aid', aid)->find();
        $return['score2money'] =$set['score2money']?$set['score2money']:'0';
        $is_direct_money = 0;
        if(getcustom('restaurant_cashdesk_auth_enter')){
            //开启定制 且 改账号没有权限时 弹窗确认权限
            $is_direct_money = 1;//显示直接优惠
            $auth_path =$this->handleAuthData($this->user);
            if(!in_array('RestaurantCashdesk/discount',$auth_path)  && $auth_path !='all'){
                $is_show_check_auth = 1;
            }else{
                $is_show_check_auth = 0;
            }
            $return['is_show_check_auth'] = $is_show_check_auth;//是否显示 授权弹窗  
            $return['direct_money'] = dd_money_format($direct_money,2);
        }
        $return['is_direct_money'] = $is_direct_money;
        if(getcustom('restaurant_table_timing')){
            $return['timing_money'] =  $orderResult['timing_money'];
        }
        if(getcustom('restaurant_table_minprice')){
            $return['service_money'] = $orderResult['service_money'];
        }
        //预览时 更新订单信息
        $orderup['moling_money'] = $orderResult['moling_money'];
        if($orderResult['coupon_money']>0){
            $orderup['coupon_money'] = $orderResult['coupon_money'];
        }
        if(getcustom('restaurant_cashdesk_cuxiao')){
            $orderup['cuxiao_money'] = $orderResult['cuxiao_money'];
        }
        if(getcustom('restaurant_scan_qrcode_coupon')){
            $orderup['qrcode_coupon_money'] = $orderResult['qrcode_coupon_money'];
        }
        if(getcustom('restaurant_cashdesk_auth_enter')){
            $orderup['direct_money'] = $direct_money;
        }
        if(getcustom('restaurant_table_timing')){
            $orderup['timing_money'] = $orderResult['timing_money'];
        }
        if(getcustom('restaurant_table_minprice')){
            $orderup['service_money'] = $orderResult['service_money'];
        }
        $orderup['scoredk_money'] = $orderResult['scoredk_money'];
        $orderup['scoredkscore'] = $orderResult['scoredkscore'];
        $orderup['leveldk_money'] = $orderResult['leveldk_money'];
        $orderup['totalprice'] = dd_money_format($orderResult['totalprice'],2); 
        Db::name('restaurant_shop_order')->where('id',$order['id'])->where('aid',aid)->where('bid',bid)->update($orderup);
        
        return $this->json(1,'ok',$return);
    }
    public function computeQrcodeCoupon($coupon_code,$order,$product_price,$totalprice){
        if(getcustom('restaurant_scan_qrcode_coupon')){
            $qrcode_coupon_money = 0;
            $goodslist = Db::name('restaurant_shop_order_goods')->where('orderid', $order['id'])->select()->toArray();
            if (empty($order) || empty($goodslist)) {
                return ['status'=>0,'msg'=>'暂无待结算订单'];
            }
            $proids = [];
            foreach ($goodslist as $product) {
                if ($product['protype'] == 1) {
                    $proids[] = $product['proid'];
                }
            }
            $cids = [];
            if ($proids) {
                $cidarr = Db::name('restaurant_product')->where('aid',aid)->where('bid', bid)->where('id', 'in', $proids)->column('cid','id');
                if($cidarr){
                    foreach ($cidarr as $cval){
                        $cid = explode(',',$cval);
                        $cids = array_merge($cids,$cid);
                    }
                }
            }
            $couponrecord = Db::name('coupon_record')->where('aid', aid)->where('is_scan',1)->where('code', $coupon_code)->find();
            if (!$couponrecord) {
                return ['status'=>0,'msg'=>'该' . t('优惠券') . '不存在'];
            } elseif ($couponrecord['status'] != 0) {
                return ['status'=>0,'msg'=>'该' . t('优惠券') . '已使用过了'];
            } elseif ($couponrecord['starttime'] > time()) {
                return ['status'=>0,'msg'=>'该' . t('优惠券') . '尚未开始使用'];
            } elseif ($couponrecord['endtime'] < time()) {
                return ['status'=>0,'msg'=>'该' . t('优惠券') . '已过期'];
            } elseif ($couponrecord['minprice'] > $totalprice) {
                return ['status'=>0,'msg'=>'该' . t('优惠券') . '不符合条件'];
            }
            $couponinfo = Db::name('coupon')->where('aid', aid)->where('id', $couponrecord['couponid'])->find();
          
            if (empty($couponinfo)) {
                return ['status'=>0,'msg'=>'该' . t('优惠券') . '不存在或已作废'];
            }
            //0全场通用,1指定类目,2指定商品
            if (!in_array($couponinfo['fwtype'], [0, 1, 2]) ||!in_array($couponinfo['fwscene'],[0]) ) {
                return ['status'=>0,'msg'=>'该' . t('优惠券') . '超出可用范围'];
            }
            if ($couponrecord['from_mid']==0 && $couponinfo && $couponinfo['isgive'] == 2) {
                return $this->json(0, '该' . t('优惠券') . '仅可转赠');
            }
            $thistotalprice = $product_price;
            if ($couponinfo['fwtype'] == 2) {//指定商品可用
                $productids = explode(',', $couponinfo['productids']);
                if (!array_intersect($proids, $productids)) {
                    return ['status'=>0,'msg'=>'该' . t('优惠券') . '指定菜品可用'];
                }
    
                if ($thistotalprice < $couponinfo['minprice']) {
                    return ['status'=>0,'msg'=>'该' . t('优惠券') . '指定菜品未达到' . $couponinfo['minprice'] . '元'];
                }
            }
            if ($couponinfo['fwtype'] == 1) {//指定类目可用
                $categoryids = explode(',',$couponinfo['categoryids']);
                $clist = Db::name('restaurant_product_category')->where('pid','in',$categoryids)->select()->toArray();
                foreach($clist as $kc=>$vc){
                    $categoryids[] = $vc['id'];
                    $cate2 = Db::name('restaurant_product_category')->where('pid',$vc['id'])->find();
                    $categoryids[] = $cate2['id'];
                }
                if(!array_intersect($cids,$categoryids)){
                    return ['status' => 0,'msg' =>'该' . t('优惠券') . '指定分类可用'];
                }
                if ($thistotalprice < $couponinfo['minprice']) {
                    return ['status'=>0,'msg'=>'该' . t('优惠券') . '指定分类未达到' . $couponinfo['minprice'] . '元'];
                }
                $qrcode_coupon_money += $couponrecord['money'];
            }
            if( $couponinfo['type']==10){ //折扣券
                if ($couponinfo['fwtype'] == 1 || $couponinfo['fwtype'] == 2) {
                    $qrcode_coupon_money += $thistotalprice *  (100-$couponrecord['discount']) * 0.01;
                } else {
                    $qrcode_coupon_money += $totalprice * (100- $couponrecord['discount']) * 0.01;
                }
                if ($qrcode_coupon_money > $totalprice) $qrcode_coupon_money = $totalprice;
            }else{
                $qrcode_coupon_money += $couponrecord['money'];
            }
            if ($qrcode_coupon_money > $totalprice) $qrcode_coupon_money = $totalprice;
            return ['status' =>1,'qrcode_coupon_money' => $qrcode_coupon_money,'qrcode_coupon_record' => $couponrecord];
        }
    }
    //计算订单价格
    protected function getOrderPrice($order=[],$couponrid=0,$userscore=0,$mid=0,$cuxiaolist=[]){
        //计算总价
        $goodslist = Db::name('restaurant_shop_order_goods')->where('orderid', $order['id'])->select()->toArray();
        if (empty($order) || empty($goodslist)) {
            return ['status'=>0,'msg'=>'暂无待结算订单'];
        }
        $totalprice = 0;
        $totalnum = 0;
        $not_discount = 0;
        $not_cuxiao = 0;
        $not_coupon = 0;
        foreach ($goodslist as $key=>$product) {
            if ($product['protype'] == 1) {
                $proids[] = $product['proid'];
            }
            //如果开启会员
            if($mid && $product['is_gj'] ==0){
                $product['sell_price']=  $this -> getVipPrice($product['proid'],$mid,$product['ggid'],$product['sell_price']);
            }
            //读取最新的价格
            $goods_totalprice = round($product['sell_price'] * $product['num'],2);
            if(getcustom('restaurant_product_jialiao')){
                $goods_totalprice = dd_money_format( $goods_totalprice +$product['njlprice']*$product['num']);
                //加料 判断不打折的加料价格 和 不促销的加料价格
                $is_diacount_is_cuxiao = Db::name('restaurant_product')->where('aid',aid)->where('bid', bid)->where('id', $product['proid'] )->field('id,jl_is_discount,jl_is_cuxiao')->find();
                if($is_diacount_is_cuxiao['jl_is_discount'] ==0){
                    $not_discount += $product['njlprice'];
                }
                if($is_diacount_is_cuxiao['jl_is_discount'] ==0){
                    $not_cuxiao += $product['njlprice'];
                }
            }
            if($goods_totalprice>0){
                Db::name('cashier_order_goods')->where('id',$product['id'])->update(['totalprice'=>$goods_totalprice]);
            }
            $totalprice = $totalprice + $goods_totalprice;
            $totalnum +=  $product['num'];
            $productdata = Db::name('restaurant_product')->where('id',$product['proid'])->find();
            $goodslist[$key]['product'] =  $productdata;
            $goodslist[$key]['guige'] = Db::name('restaurant_product_guige')->where('id',$product['ggid'])->find();
            if(getcustom('restaurant_product_package')){
                 if($product['package_data'] ){
                     $pdprice =  $product['sell_price'] * $product['num'];
                     if($productdata['product_type'] ==2 && $productdata['package_is_discount'] ==0){
                         $not_discount +=$pdprice ;
                     }
                     if($productdata['product_type'] ==2 && $productdata['package_is_cuxiao'] ==0){
                         $not_cuxiao += $pdprice;
                     }
                     if($productdata['product_type'] ==2 && $productdata['package_is_coupon'] ==0){
                         $not_coupon += $pdprice;
                     }
                 }
            }
        }
      
        $product_price = $totalprice;
        //茶位费
        $shop_set = Db::name('restaurant_shop_sysset')->where('aid',aid)->where('bid',bid)->find();
        $tea_fee = 0;
        if($shop_set['tea_fee_status']==1){
            $tea_fee = $shop_set['tea_fee']>0 ? $shop_set['tea_fee'] * $order['renshu'] : 0;
            $totalprice = $totalprice + $tea_fee;
        }
        $cidarr = [];
        if ($proids) {
            $cidarr = Db::name('restaurant_product')->where('aid',aid)->where('bid', bid)->where('id', 'in', $proids)->column('cid','id');
            if($cidarr){
                $cids = array_values($cidarr);
            }
        }
        //优惠券
        $coupon_money = 0;
        $scoredk_money = 0;
        $scoretotal = 0;
        $leveldk_money = 0;
        $moling_money = 0;
       
        if (getcustom('restaurant_cashdesk_cuxiao')){
            $cuxiaotype = [];
            $cuxiao_money = 0;
            $cuxiao_checked = [];
            $cuxiao_give_product = [];
        }
        //会员折扣
        if ($mid) {
            $member = Db::name('member')->where('id', $mid)->where('aid', aid)->find();
            $adminset = Db::name('admin_set')->where('aid', aid)->find();
            $userlevel = Db::name('member_level')->where('aid', aid)->where('id', $member['levelid'])->find();
            $level_discount = $userlevel['discount'];
            if (is_numeric($level_discount) && $level_discount<10) {
                $leveldk_totalprice =  $totalprice - $not_discount;
                $leveldk_money = round($leveldk_totalprice * (10-$level_discount) * 0.1, 2);
                $totalprice = $totalprice - $leveldk_money;
            }
            //积分抵扣
            if($userscore && $adminset['score2money']>0){
                $score2money = $adminset['score2money'];
                if($adminset['scoredkmaxpercent']>0 && $adminset['scoredkmaxpercent']<=100){
                    $scoreMaxDk = round($totalprice * $adminset['scoredkmaxpercent']*0.01,2);
                }else{
                    $scoreMaxDk = $totalprice;
                }
                $scoredk_money = round($member['score'] * $adminset['score2money'], 2);
                $scoredk_money = min($scoreMaxDk,$scoredk_money);
                $scoretotal = intval($scoredk_money / $adminset['score2money']);
            }
            $totalprice = $totalprice-$scoredk_money;
            if ($couponrid) {
                $couponrecord = Db::name('coupon_record')->where("bid=-1 or bid=" . bid)->where('aid', aid)->where('mid', $mid)->where('id', $couponrid)->find();
                if (!$couponrecord) {
                    return ['status'=>0,'msg'=>'该' . t('优惠券') . '不存在'];
                } elseif ($couponrecord['status'] != 0) {
                    return ['status'=>0,'msg'=>'该' . t('优惠券') . '已使用过了'];
                } elseif ($couponrecord['starttime'] > time()) {
                    return ['status'=>0,'msg'=>'该' . t('优惠券') . '尚未开始使用'];
                } elseif ($couponrecord['endtime'] < time()) {
                    return ['status'=>0,'msg'=>'该' . t('优惠券') . '已过期'];
                } elseif ($couponrecord['minprice'] > $totalprice) {
                    return ['status'=>0,'msg'=>'该' . t('优惠券') . '不符合条件'];
                } 

                $couponinfo = Db::name('coupon')->where('aid', aid)->where('id', $couponrecord['couponid'])->find();
                if (empty($couponinfo)) {
                    return ['status'=>0,'msg'=>'该' . t('优惠券') . '不存在或已作废'];
                }
                //0全场通用,1指定类目,2指定商品
                if (!in_array($couponinfo['fwtype'], [0, 1, 2])) {
                    return ['status'=>0,'msg'=>'该' . t('优惠券') . '超出可用范围'];
                }
                $fwscene = [0];
                if(getcustom('coupon_maidan_cashdesk')){
                    $fwscene[] = 2;
                }
                if(!in_array($couponinfo['fwscene'],$fwscene)){//适用场景 
                    return ['status'=>0,'msg'=>'该' . t('优惠券') . '超出可用范围'];
                }
                if ($couponrecord['from_mid']==0 && $couponinfo && $couponinfo['isgive'] == 2) {
                    return ['status'=>0,'msg'=>'该' . t('优惠券') . '仅可转赠'];
                }
                
                if ($couponinfo['fwtype'] == 2) {//指定商品可用
                    $productids = explode(',', $couponinfo['productids']);
                    if (!array_intersect($proids, $productids)) {
                        return ['status'=>0,'msg'=>'该' . t('优惠券') . '指定菜品可用'];
                    }
                    $thistotalprice = 0;
                    foreach ($goodslist as $k2 => $product) {
                        if (in_array($product['proid'],$productids)){
                            $thistotalprice += $product['sell_price'] * $product['num'];
                        }
                    }
                    if ($thistotalprice < $couponinfo['minprice']) {
                        return ['status'=>0,'msg'=>'该' . t('优惠券') . '指定菜品未达到' . $couponinfo['minprice'] . '元'];
                    }
                }
                if ($couponinfo['fwtype'] == 1) {//指定类目可用
                    $categoryids = explode(',',$couponinfo['categoryids']);
                    $clist = Db::name('restaurant_product_category')->where('pid','in',$categoryids)->select()->toArray();
                    foreach($clist as $kc=>$vc){
                        $categoryids[] = $vc['id'];
                        $cate2 = Db::name('restaurant_product_category')->where('pid',$vc['id'])->find();
                        $categoryids[] = $cate2['id'];
                    }
                    if(!array_intersect($cids,$categoryids)){
                        return ['status'=>0,'msg'=>'该' . t('优惠券') . '指定分类可用'];
                    }
                    $thistotalprice = 0;
                    foreach ($goodslist as $k2 => $product) {
                        if(isset($cidarr[$product['proid']])){
                            $thistotalprice += $product['sell_price'] * $product['num'];
                        }
                    }
                    if ($thistotalprice < $couponinfo['minprice']) {
                        return ['status'=>0,'msg'=>'该' . t('优惠券') . '指定分类未达到' . $couponinfo['minprice'] . '元'];
                    }
                }
                if( $couponinfo['type']==10){ //折扣券
                    if ($couponinfo['fwtype'] == 1 || $couponinfo['fwtype'] == 2) {
                        $coupon_money += $thistotalprice *  (100-$couponrecord['discount']) * 0.01;
                    } else {
                        $coupon_money += $totalprice *  (100-$couponrecord['discount']) * 0.01;
                    }
                    if ($coupon_money > $totalprice) $coupon_money = $totalprice;
                }else if($couponinfo['type']==51){//免费券，直接抵扣全部
                    $coupon_money += $totalprice;
                }else{
                    $coupon_money += $couponrecord['money'];
                }
                if ($coupon_money > $totalprice) $coupon_money = $totalprice;
                $totalprice =  round($totalprice - $coupon_money, 2);
            }
           
        }
        if (getcustom('restaurant_cashdesk_cuxiao')) {
            if ($cuxiaolist) {
                foreach ($cuxiaolist as $key => $cuxiaoinfo) {
                    if(getcustom('restaurant_cuxiao_activity_day')){
                        //促销活动日
                        if($cuxiaoinfo['activity_day_type'] ==0){//周几
                            $now_week = date('w');
                            $activity_day_week = explode(',',$cuxiaoinfo['activity_day_week']);
                            if(!in_array($now_week,$activity_day_week)){
                                continue;
                            }
                        }else{//几号
                            $now_date = date('d');
                            $activity_day_date = explode(',',$cuxiaoinfo['activity_day_week']);
                            if(!in_array($now_date,$activity_day_date)){
                                continue;
                            }
                        }
                    }
                    if (!in_array($cuxiaoinfo['type'], $cuxiaotype)) {

                        if($cuxiaoinfo['is_not_share'] ==1 && count($cuxiao_checked) > 0){
                            continue;
                        }
                        $cuxiaotype[] = $cuxiaoinfo['type'];
                        $cuxiao_product_total = 0;
                        if($cuxiaoinfo['fwtype']==2){//指定菜品可用
                            $productids = explode(',',$cuxiaoinfo['productids']);
                            if($cuxiaoinfo['type']==1 || $cuxiaoinfo['type']==2 || $cuxiaoinfo['type']==3 || $cuxiaoinfo['type']==4){//指定菜品是否达到金额要求
                                foreach($goodslist as $vpro){
                                    if(in_array($vpro['proid'], $productids)){
                                        if($vpro['product_type'] ==0){
                                            $cuxiao_product_total += $vpro['sell_price'] * $vpro['num'];
                                        }
                                        if(getcustom('restaurant_product_jialiao')) {
                                            if ($vpro['product']['jl_is_cuxiao'] == 1) {
                                                $cuxiao_product_total += $vpro['njlprice'] * $vpro['num'];
                                            }
                                        }
                                        if(getcustom('restaurant_product_package')){
                                            if($vpro['product']['product_type'] ==2&& $vpro['product']['package_is_coupon'] ==1){
                                                $cuxiao_product_total += $vpro['sell_price'] * $vpro['num'];
                                            }
                                        }
                                    }
                                }
                            }
                            if($cuxiaoinfo['type']==81 || $cuxiaoinfo['type']==5){//指定菜品是否达到件数要求
                                $thistotalnum = 0;
                                foreach($goodslist as $vpro){
                                    if(in_array($vpro['proid'], $productids)){
                                        $thistotalnum += $vpro['num'];
                                    }
                                }
                            }
                         
                        }
                        if($cuxiaoinfo['fwtype']==1){//指定类目可用
                            $categoryids = explode(',',$cuxiaoinfo['categoryids']);
                            $clist = Db::name('restaurant_product_category')->where('pid','in',$categoryids)->select()->toArray();
                            foreach($clist as $kc=>$vc){
                                $categoryids[] = $vc['id'];
                                $cate2 = Db::name('restaurant_product_category')->where('pid',$vc['id'])->find();
                                $categoryids[] = $cate2['id'];
                            }
                       
                            if($cuxiaoinfo['type']==1 || $cuxiaoinfo['type']==2 || $cuxiaoinfo['type']==3 || $cuxiaoinfo['type']==4){
                                foreach($goodslist as $vpro){
                                    $cuxiao_pro_cidArr = explode(',',$vpro['product']['cid']);
                                    if(array_intersect($cuxiao_pro_cidArr, $categoryids)){
                                        if(!$vpro['product_type'] || $vpro['product_type']==0){
                                            $cuxiao_product_total += $vpro['sell_price'] * $vpro['num'];
                                        }
                                       
                                        if(getcustom('restaurant_product_jialiao')) {
                                            if ($vpro['product']['jl_is_cuxiao'] == 1) {
                                                $cuxiao_product_total += $vpro['njlprice'] * $vpro['num'];
                                            }
                                        }
                                        if(getcustom('restaurant_product_package')){
                                            if($vpro['product']['product_type'] ==2&& $vpro['product']['package_is_coupon'] ==1){
                                                $cuxiao_product_total += $vpro['sell_price'] * $vpro['num'];
                                            }
                                        }
                                    }
                                }
                            }
                            if($cuxiaoinfo['type']==81 || $cuxiaoinfo['type']==5){//指定类目内菜品是否达到件数要求
                                $thistotalnum = 0;
                                foreach($goodslist as $vpro){
                                    $cuxiao_pro_cidArr = explode(',',$vpro['product']['cid']);
                                    if(array_intersect($cuxiao_pro_cidArr, $categoryids)){
                                        $thistotalnum += $vpro['num'];
                                    }
                                }
                                if($thistotalnum < $cuxiaoinfo['minnum']){
                                    continue;
                                }
                            }
                        }
                       
                        if ($cuxiaoinfo['type'] == 1 ) {//满额立减
                            if($cuxiaoinfo['fwtype']==2 || $cuxiaoinfo['fwtype']==1){
                                $cuxiao_totalprice = $cuxiao_product_total;
                            }else{
                                $cuxiao_totalprice = $totalprice;
                                if(getcustom('restaurant_product_jialiao')){
                                    $cuxiao_totalprice = $cuxiao_totalprice  -  $not_cuxiao;
                                }
                            }
                           
                            if($cuxiao_totalprice > $couponinfo['minprice']){
                                $cuxiao_money += $cuxiaoinfo['money'];
                            }

                        }elseif ($cuxiaoinfo['type'] == 2) {//满额赠送
                            $product = Db::name('restaurant_product')->where('aid',aid)->where('id',$cuxiaoinfo['proid'])->find();
                            $guige = Db::name('restaurant_product_guige')->where('aid',aid)->where('id',$cuxiaoinfo['ggid'])->find();
                            if(!$product || !$guige || $guige['stock'] < 1){
                                continue;
                            }
                            $cuxiao_give_product[] = ['product'=>$product,'guige'=>$guige,'num'=>1,'isSeckill'=>0];
                        } elseif ($cuxiaoinfo['type'] == 3) { //加价换购
                            $cuxiao_money -= $cuxiaoinfo['money'];
                            $product = Db::name('restaurant_product')->where('aid',aid)->where('id',$cuxiaoinfo['proid'])->find();
                            $guige = Db::name('restaurant_product_guige')->where('aid',aid)->where('id',$cuxiaoinfo['ggid'])->find();
                            if(!$product || !$guige ||$guige['stock'] < 1){
                                continue;
                            }
                            $cuxiao_give_product[] = ['product'=>$product,'guige'=>$guige,'num'=>1,'isSeckill'=>0];
                        } elseif ($cuxiaoinfo['type'] == 4 || $cuxiaoinfo['type'] == 5) {//满额打折 满件打折
                            if($cuxiaoinfo['fwtype']==2 || $cuxiaoinfo['fwtype']==1){
                                $cuxiao_totalprice = $cuxiao_product_total;
                            }else{
                                $cuxiao_totalprice = $totalprice;
                                if(getcustom('restaurant_product_jialiao')){
                                    $cuxiao_totalprice = $cuxiao_totalprice  -  $not_cuxiao;
                                } 
                            }
                            $cuxiao_money += $cuxiao_totalprice * (1 - $cuxiaoinfo['zhekou'] * 0.1);
                        }elseif($cuxiaoinfo['type'] ==7){
                            if(getcustom('restaurant_the_second_discount')){
                                $this_cuxiao_money = 0;

                                if($cuxiaoinfo['is_one_product'] ==1){//同一件 
                                    foreach ($goodslist as $product) {
                                        if(getcustom('restaurant_product_package')) {
                                            if ($product['product']['package_is_cuxiao'] == 0 && $product['product']['product_type'] ==2) {
                                                continue;
                                            }
                                        }
                                        if($product['num'] >1){
                                            if($cuxiaoinfo['fwtype'] ==2){
                                                //指定菜品的 如果不是指定的菜品，不打折
                                                $productids = explode(',',$cuxiaoinfo['productids']);
                                                if(!in_array($product['proid'],$productids)){
                                                    continue;
                                                }
                                            }
                                            if($cuxiaoinfo['fwtype']==1){
                                                $categoryids = explode(',',$couponinfo['categoryids']);
                                                $product_cids = Db::name('restaurant_product_categor')->where('id',$product['proid'])->value('cid');
                                                $p_category = explode(',',$product_cids);
                                                if(!array_intersect($p_category,$categoryids)){
                                                    continue;
                                                }
                                            }

                                            $this_cuxiao_money +=  $product['sell_price'] * (1 - $cuxiaoinfo['zhekou'] * 0.1);
                                            if(getcustom('restaurant_product_jialiao')){
                                                if($product['product']['jl_is_cuxiao'] ==1){
                                                    $this_cuxiao_money += $product['njlprice'] *  (1 - $cuxiaoinfo['zhekou'] * 0.1);
                                                }
                                            }
                                        }
                                    }
                                }else{
                                    //查找最低价格,未计算加料
                                    $this_min_money = 0;
                                    foreach ($goodslist as $product) {
                                        if($cuxiaoinfo['fwtype'] ==2){
                                            //指定菜品的 如果不是指定的菜品，不打折
                                            $productids = explode(',',$cuxiaoinfo['productids']);
                                            if(!in_array($product['id'],$productids)){
                                                continue;
                                            }
                                        }
                                        if($cuxiaoinfo['fwtype']==1){
                                            $categoryids = explode(',',$couponinfo['categoryids']);
                                            $p_category = explode(',',$product['cid']);
                                            if(!array_intersect($p_category,$categoryids)){
                                                continue;
                                            }
                                        }
                                        if(getcustom('restaurant_product_package')) {
                                            if ($product['product']['package_is_cuxiao'] == 0 && $product['product']['product_type'] ==2) {
                                                continue;
                                            }
                                        }
                                        $p_sell_price =  $product['sell_price'];
                                        if(getcustom('restaurant_product_jialiao')){
                                            if($product['product']['jl_is_cuxiao'] ==1){
                                                $p_sell_price += $product['njlprice'];
                                            }
                                        }      
                                        if($p_sell_price < $this_min_money || $this_min_money == 0 ){
                                            $this_min_money = $p_sell_price;
                                        }
                                    }
                                    $this_cuxiao_money=  $this_min_money * (1 - $cuxiaoinfo['zhekou'] * 0.1);
                                }
                                $cuxiao_money += $this_cuxiao_money;
                            }
                        }
                        $cuxiao_checked[] = $cuxiaoinfo['id'];
                    }
                }

                $totalprice = $totalprice - $cuxiao_money;
            }
        }
        $timing_money = 0;
        if(getcustom('restaurant_table_timing')){
            $timing_money =  $this->getTimingFee($order['id'],$order['tableid']);
            $totalprice =   $totalprice + $timing_money;
        }
        //抹零
        if($order['remove_zero']){
            $zeroinfo = $this->removeZero($totalprice,$order['cashdesk_id']);
            $moling_money = $zeroinfo['moling_money']??0;
            $totalprice = $totalprice - $moling_money;
        }
        
        if(getcustom('restaurant_table_minprice')){
            $service_money = 0;
            $minprice_totalprice= $totalprice ;
            if($order['tableid'] > 0){
                $table = Db::name('restaurant_table')->where('aid',$order['aid'])->where('id',$order['tableid'])->find();
                if($table['minprice'] > 0 && $minprice_totalprice < $table['minprice']){
                    //计算服务费
                    if($table['service_fee_type'] ==0){
                        $service_money = $table['service_fee'];
                    }
                    if($table['service_fee_type'] ==1){
                        $service_money = $table['service_fee']/100 * $minprice_totalprice;
                    }
                }
            }
            $totalprice = $totalprice +  $service_money;
        }
        $rdata = [
            'status'=>1,
            'product_price' =>dd_money_format($product_price),//实际价格
            'totalprice' =>dd_money_format($totalprice) <0?0:dd_money_format($totalprice),//总价格
            'moling_money'=>dd_money_format($moling_money),
            'coupon_money'=>dd_money_format($coupon_money),
            'leveldk_money'=>dd_money_format($leveldk_money),
            'scoredk_money'=>dd_money_format($scoredk_money),
            'scoredkscore' => dd_money_format($scoretotal),
            'tea_fee' => $tea_fee
        ];
        if (getcustom('restaurant_cashdesk_cuxiao')) {
            $rdata['cuxiao_money'] = dd_money_format($cuxiao_money);
            $rdata['cuxiao_checked'] = $cuxiao_checked;
            $rdata['cuxiao_give_product'] = $cuxiao_give_product; //促销赠送产品 
        }
        if(getcustom('restaurant_table_timing')){
            $rdata['timing_money'] = dd_money_format($timing_money,2);
        }
        if(getcustom('restaurant_table_minprice')){
            $rdata['service_money'] = dd_money_format($service_money,2);
        }
        return  $rdata;
    }
    
    //挂单操作  status:-1
    public function getGuadanOrderList(){
        if(getcustom('restaurant_shop_guadan_order')) {

            $page = input('param.page/d', 1);
            $limit = input('param.limit/d', 20);
            $list = Db::name('restaurant_shop_order')
                ->where('bid', bid)->where('aid', aid)
                ->where('status', -1)
                ->page($page, $limit)
                ->order('id desc,createtime desc')
                ->select()->toArray();
            foreach ($list as $key => $order) {
                $shop_goods = Db::name('restaurant_shop_order_goods')->where('orderid', $order['id'])->select()->toArray();
                foreach ($shop_goods as $gk => &$goods) {
                    $stock = 0;
                    if ($goods['protype'] == 1) {//1商品 2直接收款（proid=-99)
                        $stock = Db::name('restaurant_product_guige')->where('product_id', $goods['proid'])->where('id', $goods['ggid'])->value('stock');
                    }
                    if ($goods['mid'] && $goods['is_gj'] == 0) {
                        $goods['sell_price'] = $this->getVipPrice($goods['proid'], $goods['mid'], $goods['ggid'], $goods['sell_price']);
                    }
                    $goods['totalprice'] = dd_money_format($goods['sell_price'] * $goods['num']);

                    if (getcustom('restaurant_product_jialiao')) {
                        $goods['totalprice'] = dd_money_format($goods['totalprice'] + $goods['njlprice'] * $goods['num']);
                        $goods['sell_price'] = dd_money_format($goods['sell_price'] + $goods['njlprice']);
                    }
                    if (getcustom('restaurant_weigh')) {
                        $goods['num'] = floatval($goods['num']);
                    }
                    $goods['stock'] = $stock ?? 0;
                    if (getcustom('restaurant_product_package')) {
                        if ($goods['package_data']) {
                            $package_data = json_decode($goods['package_data'], true);
                            $ggtext = [];
                            foreach ($package_data as $pdk => $pd) {
                                $t = 'x' . $pd['num'] . ' ' . $pd['proname'];
                                if ($pd['ggname'] != '默认规格') {
                                    $t .= '(' . $pd['ggname'] . ')';
                                }
                                $ggtext[] = $t;
                            }
                            $goodslist[$gk]['ggtext'] = $ggtext;
                        }
                    }
                }
                $list[$key]['prolist'] = $shop_goods ? $shop_goods : [];
                $tablename = Db::name('restaurant_table')->where('aid', $order['aid'])->where('id', $order['tableid'])->value('name');
                $list[$key]['tablename'] = $tablename;
                $list[$key]['createtime'] = date('Y-m-d H:i:s',$order['createtime']);
            }
            return $this->json(1, '成功', $list);
        }
    }
    //设置挂单状态 
    public function setGuadanOrderStatus(){
        if(getcustom('restaurant_shop_guadan_order')) {
            $orderid = input('param.orderid', 0);
            $order = Db::name('restaurant_shop_order')->where('bid', bid)->where('aid', aid)->where('id', $orderid)->find();
            if (!$order) {
                return $this->json(0, '不存在该订单');
            }
            //先更新订单的价格
            if(!$order['totalprice']){
                $couponrid = $order['coupon_rid'];
                $mid = $order['mid'];
                $cuxiaolist = [];
                if(getcustom('restaurant_cashdesk_cuxiao')) {
                    if ($order['cuxiao_ids']) {
                        $cuxiaolist = Db::name('restaurant_cuxiao')->where('aid', aid)->where('bid', bid)->where('id','in',$order['cuxiao_ids'])->select()->toArray();
                    }
                }
                $orderResult = $this->getOrderPrice($order,$couponrid,0,$mid,$cuxiaolist);
                $orderup['moling_money'] = $orderResult['moling_money'];
                if($orderResult['coupon_money']>0){
                    $orderup['coupon_money'] = $orderResult['coupon_money'];
                }
                if(getcustom('restaurant_cashdesk_cuxiao')){
                    $orderup['cuxiao_money'] = $orderResult['cuxiao_money'];
                }
                if(getcustom('restaurant_scan_qrcode_coupon')){
                    $orderup['qrcode_coupon_money'] = $orderResult['qrcode_coupon_money'];
                }
                if(getcustom('restaurant_table_timing')){
                    $orderup['timing_money'] = $orderResult['timing_money'];
                }
                if(getcustom('restaurant_table_minprice')){
                    $orderup['service_money'] = $orderResult['service_money'];
                }
                $orderup['scoredk_money'] = $orderResult['scoredk_money'];
                $orderup['scoredkscore'] = $orderResult['scoredkscore'];
                $orderup['leveldk_money'] = $orderResult['leveldk_money'];
                $orderup['totalprice'] = dd_money_format($orderResult['totalprice'],2);
                Db::name('restaurant_shop_order')->where('id',$order['id'])->where('aid',aid)->where('bid',bid)->update($orderup);
            }
            
            
            //必须是未支付的订单才可以挂单
            if (!in_array($order['status'],[0,-1])) {
                return $this->json(0, '订单状态不符');
            }
            //更改订单状态，
            Db::name('restaurant_shop_order')->where('bid', bid)->where('aid', aid)->where('id', $orderid)->update(['status' => -1]);
            // 清理桌台
            Db::name('restaurant_table')->where('id', $order['tableid'])->where('aid', $order['aid'])->where('bid', $order['bid'])->update(['status' => 0, 'orderid' => 0]);
            $guadan_print = Db::name('restaurant_cashdesk')->where('id',$order['cashdesk_id'])->value('guadan_print');
            if($guadan_print){
                //重新查询
                $order = Db::name('restaurant_shop_order')->where('bid', bid)->where('aid', aid)->where('id', $orderid)->find();
                \app\customs\Restaurant::print('restaurant_shop', $order, [], '');
            }
            return $this->json(1, '挂单成功');
        }
    }
    
//------------------------------订单操作 end-----------------------//  
//------------------------------桌台 start-----------------------//
    //桌台分类列表
    public function getTableCategory(){
        $where[] = ['aid', '=', aid];
        $where[] = ['bid', '=', bid];
        $where[] = ['status','=',1];
        $list = Db::name('restaurant_table_category')->where($where)->order('sort desc,id desc')->select()->toArray();
        return $this->json(1, '', $list);
    }
    //table 桌台列表
    public function getTableList(){
        $page = input('param.page',1);
        $limit = input('param.limit',10);
        $cid = input('param.cid/d', 0);
        $status = input('param.status/d','');
        $where[] = ['aid', '=', aid];
        $where[] = ['bid', '=', bid];
        if($cid){
            $where[] = ['cid', '=', $cid];
        }
        if($status !==''){
            $where[] = ['status', '=', $status];
        }
        $keyword = input('param.keyword');
        if($keyword) $where[] = ['name', 'like', '%'.$keyword.'%'];
        if(getcustom('restaurant_cashdesk_link_table')){
            $pindan_status = input('param.pindan_status');
            if($pindan_status !=''){
                $where[] = ['pindan_status', '=', $pindan_status];
            }
        }
        $list = Db::name('restaurant_table')->where($where)->page($page,$limit)->order('sort desc,id desc')->select()->toArray();
        if($list){
            foreach ($list as  $key=>$val){
                if($val['orderid']){
                    $order = Db::name('restaurant_shop_order')->where('id',$val['orderid'])->find();
                    if($order['status'] ==1 ||$order['status'] ==3 && $val['status'] ==2){
                        $list[$key]['status'] =4;
                    }
                }
            }
        }
        $is_add_bar_table = true;
        if(getcustom('restaurant_cashdesk_link_table')){
            if(input('param.pindan_status') ==1){
                $is_add_bar_table = false;
            }
        }
        if(getcustom('restaurant_bar_table_order') && $is_add_bar_table){
            $bar_table = [
                'id' =>0,
                'name' =>'吧台',
                'pindan_status' => 0
            ];
            array_unshift($list,$bar_table);
        }
        return $this->json(1, '', $list);
    }

    public function getTableDetail() {
        $id = input('param.id/d',0);
        if(!$id){
            return $this->json(0,'参数错误');
        }
        $info = Db::name('restaurant_table')->where('aid',aid)->where('bid',bid)->where('id',$id)->find();
        $info['create_time'] = date('Y-m-d H:i:s',$info['create_time']);
        $info['order'] = [];
        if($info['orderid']) {
            $order = Db::name('restaurant_shop_order')->where('aid',aid)->where('bid',bid)->where('tableid',$info['id'])->where('id', $info['orderid'])->find();
            $shop_goods = [];
            if($order){
                $order['createtime'] = date('Y-m-d H:i:s',$order['createtime']);
                $gwhere[] = ['orderid','=',$order['id']];
                if(getcustom('restaurant_cashdesk_link_table')){
                    if($order['link_tableid']){
                        $gwhere[] = ['tableid','=',$id];
                    }
                }
                $shop_goods = Db::name('restaurant_shop_order_goods')->where($gwhere)->select()->toArray();
                $totalnum = 0;
                $totalprice = 0;
                foreach ($shop_goods as $key=>$val){
                    $totalnum = $totalnum + $val['num'];
                    $goods_totalprice = round($val['sell_price'] * $val['num'],2);

                    $totalprice = $totalprice + $goods_totalprice;
                    $shop_goods[$key]['num'] = floatval($val['num']);
                }
                $order['totalnum'] = $totalnum;
                $order['totalprice'] = $totalprice;
                $info['order'] = $order;
                $info['create_time'] = $order['createtime'];
            }
            if($order['status'] ==1 || $order['status'] ==3 && $info['status'] ==2){
                $info['status'] =4;
            }
            $info['shop_goods'] =$shop_goods;
            if(getcustom('restaurant_table_timing')) {
                $timing_log = Db::name('restaurant_timing_log')->where('aid', $info['aid'])->where('bid', $info['bid'])->where('tableid', $info['id'])->where('orderid', $info['orderid'])->select()->toArray();
                if ($order['timeing_start'] > 0) {
                    $num = intval((time() - $order['timeing_start']) / 60, 0);
                    if ($num > 0) {
                        $timing_log[] = [
                            'start_time' => date('H:i', $order['timeing_start']),
                            'end_time' => date('H:i', time()),
                            'num' => $num
                        ];
                    }
                }
                $total_timing = '';
                if($timing_log){
                    $total_timing_num = 0;
                    foreach($timing_log as $key=>$val){
                        $total_timing_num +=$val['num'];
                    }
                    $hours = floor($total_timing_num / 60);
                    $minutes = $total_timing_num % 60;
                    if($hours >0){
                        $total_timing .= $hours.'小时';
                    }
                    $total_timing .=$minutes.'分钟';
                }
                $info['total_timing'] = $total_timing;
                $info['timing_log'] = $timing_log ? $timing_log : [];
            }
        }
        $info['order_goods_sum']= 0+Db::name('restaurant_shop_order_goods')->where('aid',aid)->where('bid',bid)->where('orderid', $info['orderid'])->count();
        $info['is_change_renshu'] = 0;
        if(getcustom('restaurant_change_renshu')){
            $info['is_change_renshu'] = 1;
        }
        $info['link_tablename'] = '';//关联的桌台的名称
        $info['already_link_tablename'] = '';//已关联的桌台名称
        if(getcustom('restaurant_cashdesk_link_table')){
            if($info['link_tableid']){
                $linktablename = Db::name('restaurant_table')->where('aid', aid)->where('bid', bid)->where('id', $info['link_tableid'])->value('name');
                $info['link_tablename'] =$linktablename;
            }
            //已经关联的桌台
            if($info['order']['link_tableid']){
                $already_linktablename = Db::name('restaurant_table')->where('aid', aid)->where('bid', bid)->where('id','in', $info['order']['link_tableid'])->column('name');
                $info['already_link_tablename'] = implode(',',$already_linktablename);
            }
        }
        return $this->json(1,'',$info);
    }
    
    //换桌
    public function change()
    {
        $originid = input('param.origin/d') ;
        if(!input('param.new/d') || !input('param.origin/d')){
            return $this->json(0,'参数错误');
        }
        $info = Db::name('restaurant_table')->where('aid',aid)->where('bid',bid)->where('id',input('param.origin/d'))->find();
        if(empty($info)) {
            return $this->json(0,'餐桌不存在');
        }
        $new_table = Db::name('restaurant_table')->where('aid',aid)->where('bid',bid)->where('id',input('param.new/d'))->find();
        if(empty($new_table)) {
            return $this->json(0,'餐桌不存在');
        }
        if($new_table['status'] !== 0 || $new_table['orderid']) {
            return $this->json(0,'餐桌状态不可用');
        }
        if(getcustom('restaurant_shop_pindan')){
            if($info['pindan_status'] != $new_table['pindan_status']){
                return $this->json(0,'先吃后付和先付后吃的桌台之间禁止换桌');
            }
        }
        //更改新桌台
        $newupdate = ['status' => 2, 'orderid' => $info['orderid']];
        if(getcustom('restaurant_cashdesk_link_table')){
            $newupdate['link_tableid'] = $info['link_tableid'];
        }
        Db::name('restaurant_table')->where('aid',aid)->where('bid',bid)->where('id',input('param.new/d'))->update($newupdate);
        //更改以前桌台
        $update = ['status' => 0, 'orderid' => 0];
        if(getcustom('restaurant_cashdesk_link_table')){
            $update['link_tableid'] = 0;
        }
        Db::name('restaurant_table')->where('aid',aid)->where('bid',bid)->where('id',input('param.origin/d'))->update($update);
        
        if($info['orderid']) {
            Db::name('restaurant_shop_order')->where('aid',aid)->where('bid',bid)->where('tableid',input('param.origin/d'))
                ->where('id', $info['orderid'])->update(['tableid' => $new_table['id']]);
        } 
        if(getcustom('restaurant_cashdesk_link_table')){
            //修改对应order_goods表中桌台ID
            Db::name('restaurant_shop_order_goods')->where('aid',aid)->where('orderid',$info['orderid'])->where('tableid',$originid)->update(['tableid' => $new_table['id']]);
        }

        return $this->json(1,'换桌成功');
    }
    //清台
    public function clean()
    {
        $tableid = input('param.tableid/d');
        if(!$tableid){
            return $this->json(0,'参数错误');
        }
        $info = Db::name('restaurant_table')->where('aid',aid)->where('bid',bid)->where('id',$tableid)->find();
        if(empty($info)) {
            return $this->json(0,'餐桌不存在');
        }
        if($info['status'] == 0) {
            return $this->json(0,'当前无需清台');
        }
        if($info['orderid']) {
            $order = Db::name('restaurant_shop_order')->where('aid',aid)->where('bid',bid)->where('tableid',$tableid)->where('id', $info['orderid'])->find();
            if($order && in_array($order['status'] ,[0]) && $order['totalprice'] > 0)
                return $this->json(0,'请先完成订单结算');
        }
        $tupdate = ['status' => 3, 'orderid' => 0];
        if(getcustom('restaurant_cashdesk_link_table')){
            $tupdate['link_tableid'] = 0;
        }
        Db::name('restaurant_table')->where('aid',aid)->where('bid',bid)->where('id',$tableid)->update($tupdate);
        return $this->json(1,'清理完后请切换餐桌状态');
    }
    //清台完成 设为空闲中
    public function cleanOver()
    {     
        $tableid = input('param.tableid/d');
        if(!$tableid){
            return $this->json(0,'参数错误');
        }
        $info = Db::name('restaurant_table')->where('aid',aid)->where('bid',bid)->where('id',$tableid)->find();
        if(empty($info)) {
            return $this->json(0,'餐桌不存在');
        }
        if($info['status'] == 2) {
            return $this->json(0,'就餐中，请先结算然后清台');
        }
        Db::name('restaurant_table')->where('aid',aid)->where('bid',bid)->where('id',$tableid)->update(['status' => 0, 'orderid' => 0]);

        return $this->json(1,'设置成功');
    }
    //关闭
    public function closeOrder()
    {
        $tableid = input('param.tableid/d');
        if(!$tableid){
            return $this->json(0,'参数错误');
        }
        $info = Db::name('restaurant_table')->where('aid',aid)->where('bid',bid)->where('id',$tableid)->find();
        if(empty($info)) {
            return $this->json(0,'餐桌不存在');
        }
        if($info['orderid']) {
            $order = Db::name('restaurant_shop_order')->where('aid',aid)->where('bid',bid)->where('tableid',$tableid)
                ->where('id', $info['orderid'])->find();
            $orderid = $info['orderid'];
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
        }
        Db::name('restaurant_table')->where('aid',aid)->where('bid',bid)->where('id',$tableid)->update(['status' => 0, 'orderid' => 0]);

        return $this->json(1,'操作成功');
    }
    //关联桌台操作
    public function linkTable(){
        if(getcustom('restaurant_cashdesk_link_table')) {
            $link_tableid = input('param.link_tableid', 0);//被关联桌台
            $tableid = input('param.tableid', 0);//当前桌台
            //关联桌台信息
            $linktable = Db::name('restaurant_table')->where('aid', aid)->where('bid', bid)->where('id', $link_tableid)->find();
            if (!$linktable || !$linktable['orderid'] || $linktable['status'] != 2) {
                return $this->json(0, '关联桌台未下单');
            }
            if($linktable['pindan_status'] !=1){
                return $this->json(0, '关联的桌台非餐后付款模式');
            }
            //当前桌台
            $table = Db::name('restaurant_table')->where('aid', aid)->where('bid', bid)->where('id', $tableid)->find();
            if($table['pindan_status'] !=1){
                return $this->json(0, '当前桌台非餐后付款模式');
            }
            if (!$table || $table['status'] != 0) {
                return $this->json(0, '当前桌台状态不符合');
            }
            $order = Db::name('restaurant_shop_order')->where('aid', aid)->where('bid', bid)->where('id', $linktable['orderid'])->find();
            if (!$order || $order['status'] != 0) {
                return $this->json(0, '桌台订单为无效订单');
            }
            //1组合关联的桌台ID
            $order_tableid = $order['link_tableid'] ? $order['link_tableid'] . ',' . $tableid : $tableid;
            Db::name('restaurant_shop_order')->where('id', $linktable['orderid'])->update(['link_tableid' => $order_tableid]);
            //2修改当前桌台的订单为 被关联桌台的订单
            Db::name('restaurant_table')->where('id', $tableid)->update(['status' => 2, 'orderid' => $linktable['orderid'],'link_tableid' => $link_tableid]);
            return $this->json(1, '关联成功');
        }
    }
//------------------------------桌台 end-----------------------// 
//------------------------------支付操作-----------------------//
    public function getPayList(){
        $paylist =[];
        $cashier_id = input('param.cashdesk_id/d', 0);
        $cashier = Db::name('restaurant_cashdesk')->where('id',$cashier_id)->where('aid',aid)->find();
        $sysset = Db::name('restaurant_admin_set')->where('aid',aid)->find();
        if(!empty($cashier)){
            if(($cashier['wxpay'] && $cashier['bid'] ==0)){
                $wxtitle = '微信';
                $wxicon = PRE_URL.'/static/img/cashdesk/wxpay.png';
                if(getcustom('cashdesk_alipay')){
                    $wxtitle = '微信或支付宝';
                    $wxicon = PRE_URL.'/static/img/cashdesk/wechat_alipay.png';
                }
                $paylist[] = ['value'=>'1','lable'=>$wxtitle,'tip' =>'请扫描'.$wxtitle.'付款码收款，确认收款成功后，点击确认收款即可完成收款操作。','pay_tip' =>'请扫描'.$wxtitle.'付款码','icon' => $wxicon];
            }
            if($cashier['bid'] > 0 ){
                if($sysset['business_cashdesk_wxpay_type'] > 0 && $sysset['business_cashdesk_alipay_type'] == 0){
                    $wxtitle = '微信';
                    $wxicon = PRE_URL.'/static/img/cashdesk/wxpay.png';
                    $paylist[] = ['value'=>'1','lable'=>$wxtitle,'tip' =>'请扫描'.$wxtitle.'付款码收款，确认收款成功后，点击确认收款即可完成收款操作。','pay_tip' =>'请扫描'.$wxtitle.'付款码','icon' => $wxicon];
                }elseif ($sysset['business_cashdesk_wxpay_type'] == 0 && $sysset['business_cashdesk_alipay_type'] > 0){
                    if(getcustom('cashdesk_alipay')){
                        $wxtitle = '支付宝';
                        $wxicon = PRE_URL.'/static/img/cashdesk/alipay.png';
                        $paylist[] = ['value'=>'1','lable'=>$wxtitle,'tip' =>'请扫描'.$wxtitle.'付款码收款，确认收款成功后，点击确认收款即可完成收款操作。','pay_tip' =>'请扫描'.$wxtitle.'付款码','icon' => $wxicon];
                    }
                }elseif ($sysset['business_cashdesk_wxpay_type'] > 0 && $sysset['business_cashdesk_alipay_type'] > 0){
                    $wxtitle = '微信';
                    $wxicon = PRE_URL.'/static/img/cashdesk/wxpay.png';
                    if(getcustom('cashdesk_alipay')){
                        $wxtitle = '微信或支付宝';
                        $wxicon = PRE_URL.'/static/img/cashdesk/wechat_alipay.png';
                    }
                    $paylist[] = ['value'=>'1','lable'=>$wxtitle,'tip' =>'请扫描'.$wxtitle.'付款码收款，确认收款成功后，点击确认收款即可完成收款操作。','pay_tip' =>'请扫描'.$wxtitle.'付款码','icon' => $wxicon];
                }
            }

            if(getcustom('cashdesk_sxpay')){
                if($cashier['sxpay'] && $cashier['bid'] == 0){
                    //随行付  5已经被占用，value从5改为81，为了和平台兼容
                    $paylist[] = ['value'=>'81','lable'=>'随行付','tip' =>'请扫描微信或支付宝付款码收款，确认收款成功后，点击确认收款即可完成收款操作。','pay_tip' =>'请扫描微信或支付宝付款码','icon' =>PRE_URL.'/static/img/cashdesk/wechat_alipay.png'];
                }
                if($cashier['bid'] > 0 && $sysset['business_cashdesk_sxpay_type'] > 0){
                    $paylist[] = ['value'=>'81','lable'=>'随行付','tip' =>'请扫描微信或支付宝付款码收款，确认收款成功后，点击确认收款即可完成收款操作。','pay_tip' =>'请扫描微信或支付宝付款码','icon' =>PRE_URL.'/static/img/cashdesk/wechat_alipay.png'];
                }
            }
        }
        if(($cashier['bid'] == 0 &&  $cashier['cashpay'] > 0)||($cashier['bid'] > 0 && $sysset['business_cashdesk_cashpay'] > 0)){
            $paylist[]=['value'=>'3','lable'=>t('现金'),'tip' => '','pay_tip' =>'','icon' => PRE_URL.'/static/img/cashdesk/xianjin.png'];
        }
        if(getcustom('restaurant_douyin_qrcode_hexiao')){
            if(($cashier['bid'] == 0 &&  $cashier['douyinhx'] > 0)||($cashier['bid'] > 0 && $sysset['business_cashdesk_douyinhx'] > 0)) {
                $paylist[] = ['value' => '121', 'lable' => '抖音团购券', 'tip' => '', 'pay_tip' => '', 'icon' => PRE_URL . '/static/img/cashdesk/quan.png'];
            }
        }
        if(getcustom('pay_huifu')){
            if(($cashier['bid'] == 0 &&  $cashier['huifupay'] > 0)||($cashier['bid'] > 0 && $sysset['business_cashdesk_huifupay'] > 0)) {
                $paylist[] = ['value' => '62', 'lable' => '汇付支付', 'tip' => '', 'pay_tip' => '', 'icon' => PRE_URL . '/static/img/cashdesk/wechat_alipay.png'];
            }
        }
        if(getcustom('restaurant_cashdesk_custom_pay')){
            if($this->auth_data =='all' || in_array('RestaurantCashdeskCustomPay/*',$this->auth_data)){
                $paylist[] = ['value' => '10000', 'lable' => '自定义支付', 'tip' => '', 'pay_tip' => '', 'icon' => PRE_URL . '/static/img/cashdesk/custom_pay.png'];
            }
        }
        $normal_list =$paylist;
        if(($cashier['bid'] == 0 &&  $cashier['moneypay'] > 0)||($cashier['bid'] > 0 && $sysset['business_cashdesk_yue'] > 0)) {
            $paylist[] = ['value' => '4', 'lable' => t('余额'), 'tip' => '', 'pay_tip' => '', 'icon' => PRE_URL . '/static/img/cashdesk/yue.png'];
        }
        if(getcustom('member_overdraft_money')){
            $overdraft_moneypay = Db::name('admin_set')->where('aid',aid)->value('overdraft_moneypay');
            if(($cashier['bid'] == 0 &&  $cashier['guazhangpay'] > 0 && $overdraft_moneypay)||($cashier['bid'] > 0 && $sysset['business_cashdesk_guazhang'] > 0 && $overdraft_moneypay)) {
                $paylist[] = ['value' => '38', 'lable' => '挂账', 'tip' => '', 'pay_tip' => '', 'icon' => PRE_URL . '/static/img/cashdesk/guazhang.png'];
            }
        }
        $return['paylist'] = $paylist;
        $return['normal_list'] = $normal_list;
        return $this->json(1,'ok',$return);
    }
    //只获取线上支付方式
    public function getOnlinePayList(){
        $type=input('param.type',0);//只获取线上支付  1：加 现金支付
        $cashier_id = input('param.cashdesk_id/d', 0);
        $cashier = Db::name('restaurant_cashdesk')->where('id',$cashier_id)->where('aid',aid)->find();
        $sysset = Db::name('restaurant_admin_set')->where('aid',aid)->find();
        $paylist = [];
        if($cashier['bid'] > 0){
            if($sysset['business_cashdesk_wxpay_type'] > 0 && $sysset['business_cashdesk_alipay_type'] == 0){
                $wxtitle = '微信';
                $wxicon = PRE_URL.'/static/img/cashdesk/wxpay.png';
                $paylist[] = ['value'=>'1','lable'=>$wxtitle,'tip' =>'请扫描'.$wxtitle.'付款码收款，确认收款成功后，点击确认收款即可完成收款操作。','pay_tip' =>'请扫描'.$wxtitle.'付款码','icon' => $wxicon];
            }elseif ($sysset['business_cashdesk_wxpay_type'] == 0 && $sysset['business_cashdesk_alipay_type'] > 0){
                if(getcustom('cashdesk_alipay')){
                    $wxtitle = '支付宝';
                    $wxicon = PRE_URL.'/static/img/cashdesk/alipay.png';
                    $paylist[] = ['value'=>'1','lable'=>$wxtitle,'tip' =>'请扫描'.$wxtitle.'付款码收款，确认收款成功后，点击确认收款即可完成收款操作。','pay_tip' =>'请扫描'.$wxtitle.'付款码','icon' => $wxicon];
                }
            }elseif ($sysset['business_cashdesk_wxpay_type'] > 0 && $sysset['business_cashdesk_alipay_type'] > 0){
                $wxtitle = '微信';
                $wxicon = PRE_URL.'/static/img/cashdesk/wxpay.png';
                if(getcustom('cashdesk_alipay')){
                    $wxtitle = '微信或支付宝';
                    $wxicon = PRE_URL.'/static/img/cashdesk/wechat_alipay.png';
                }
                $paylist[] = ['value'=>'1','lable'=>$wxtitle,'tip' =>'请扫描'.$wxtitle.'付款码收款，确认收款成功后，点击确认收款即可完成收款操作。','pay_tip' =>'请扫描'.$wxtitle.'付款码','icon' => $wxicon];
            }
            if(getcustom('cashdesk_sxpay')){
                if($sysset['business_cashdesk_sxpay_type'] > 0){
                    $paylist[] = ['value'=>'81','lable'=>'随行付','tip' =>'请扫描微信或支付宝付款码收款，确认收款成功后，点击确认收款即可完成收款操作。','pay_tip' =>'请扫描微信或支付宝付款码','icon' =>PRE_URL.'/static/img/cashdesk/wechat_alipay.png'];
                }
            }
            if($sysset['business_cashdesk_cashpay'] > 0 && $type){
                $paylist[]=['value'=>'3','lable'=>t('现金'),'tip' => '','pay_tip' =>'','icon' => PRE_URL.'/static/img/cashdesk/xianjin.png'];
            }
        }else{
            if($cashier['wxpay']){
                $wxtitle = '微信';
                $wxicon = PRE_URL.'/static/img/cashdesk/wxpay.png';
                if(getcustom('cashdesk_alipay')){
                    $wxtitle = '微信或支付宝';
                    $wxicon = PRE_URL.'/static/img/cashdesk/wechat_alipay.png';
                }
                $paylist[] = ['value'=>'1','lable'=>$wxtitle,'tip' =>'请扫描'.$wxtitle.'付款码收款，确认收款成功后，点击确认收款即可完成收款操作。','pay_tip' =>'请扫描'.$wxtitle.'付款码','icon' => $wxicon];
            }
            if($cashier['sxpay']){
                $paylist[] = ['value'=>'81','lable'=>'随行付','tip' =>'请扫描微信或支付宝付款码收款，确认收款成功后，点击确认收款即可完成收款操作。','pay_tip' =>'请扫描微信或支付宝付款码','icon' =>PRE_URL.'/static/img/cashdesk/wechat_alipay.png'];
            }
            if($cashier['cashpay'] > 0 && $type){
                $paylist[]=['value'=>'3','lable'=>t('现金'),'tip' => '','pay_tip' =>'','icon' => PRE_URL.'/static/img/cashdesk/xianjin.png'];
            }
        }
        $return['paylist'] = $paylist;
        return $this->json(1,'ok',$return);
    }   
    
    //获取自定义支付
    public function getCustomPayList(){
        $where = [];
        $where[] = ['aid','=',aid];
        $where[] = ['bid','=',bid];
        $where[] = ['status','=',1];
        $order = 'sort desc,id desc';
        $list = Db::name('restaurant_cashdesk_custom_pay')->where($where)->order($order)->select()->toArray();
        return json(['code'=>0,'msg'=>'查询成功','data'=>$list]);
    }
    
    /**
     * @description 餐饮收银台结算  
     *  type=1=>微信扫码支付    =》2
     *  type=2=>支付宝扫码支付    =》3
     *  type=3=> 现金支付         =》 0
     *  type=4=> 余额支付 随行付   =》1   
     *  type=81=> 随行付           =》 81  5在平台已经被占用，从5改为81 
     *  type=121 抖音券核销           =》 121
     *  type=62 汇付           =》 62
     *  type=38 挂账           =》38
     */
    public function pay()
    {
        $cashier_id = input('param.cashdesk_id/d', 0);
        $couponrid = input('param.couponid/d', 0);
        $tableid = input('param.tableid/d', 0);
        $mid = input('param.mid/d', 0);
        $userscore = input('param.userscore/d', 0);
        $paytype = input('param.paytype/d', 0);
        $cuxiaoids = input('param.cuxiaoids','');
        $cuxiaolist = [];
        if(getcustom('restaurant_cashdesk_cuxiao')) {
            if ($cuxiaoids) {
                $cuxiaolist = Db::name('restaurant_cuxiao')->where('aid', aid)->where('bid', bid)->where('id','in',$cuxiaoids)->select()->toArray();
            }
        }
        if($paytype==1){
            $auth_code = input('param.auth_code');
            $wx_reg = '/^1[0-6][0-9]{16}$/';//微信
            $ali_reg = '/^(?:2[5-9]|30)\d{14,22}$/';;//支付宝
            if(preg_match($wx_reg,$auth_code)){
                return $this->wxScanPay($cashier_id,$tableid,$mid,$couponrid,$userscore,$cuxiaolist);
            }elseif(preg_match($ali_reg,$auth_code)){
                return $this->aliScanPay($cashier_id,$tableid,$mid,$couponrid,$userscore,$cuxiaolist);
            }else{
                return $this->json(0,'请扫微信或支付宝付款码付款');
            }
        }elseif($paytype==3){
            return $this->cashPay($cashier_id,$tableid,$mid,$couponrid,$userscore,$cuxiaolist);
        }elseif($paytype==4){
           return $this->moneyPay($cashier_id,$tableid,$mid,$couponrid,$userscore,$cuxiaolist);
       }elseif($paytype==5 ||$paytype==81){
            return $this->sxPay($cashier_id,$tableid,$mid,$couponrid,$userscore,$cuxiaolist);
        }elseif($paytype==121){//抖音核销券
            if(getcustom('restaurant_douyin_qrcode_hexiao')){
                return $this->douyinHexiao($cashier_id,$tableid,$mid,$couponrid,$userscore,$cuxiaolist);
            }
        }elseif ($paytype ==62){//汇付支付
            if(getcustom('pay_huifu')){
                return $this->huifuPay($cashier_id,$tableid,$mid,$couponrid,$userscore,$cuxiaolist);
            }
        }elseif ($paytype ==38){//挂账
            if(getcustom('member_overdraft_money')){
                return $this->guazhangPay($cashier_id,$tableid,$mid,$couponrid,$userscore,$cuxiaolist);
            }
        }
        elseif($paytype ==10000){ //自定义
            $custom_pay_id = input('param.custom_pay_id/d');//自定义支付的
            return $this->customPay($custom_pay_id,$cashier_id,$tableid,$mid,$couponrid,$userscore,$cuxiaolist);
        }
        elseif($paytype ==91){ //免费券
            if(getcustom('restaurant_cashdesk_free_coupon')){
                return  $this->freeCouponPay($cashier_id,$tableid,$mid,$couponrid,$userscore,$cuxiaolist);
            }
        }else{
            return $this->json(0,'非法请求');
        }
    }
    //获取order_goods的真实付款价格
    protected function getOgRealPrice($order=[],$ogtotalprice = 0){
        //计算总价
        $coupon_money = 0;
        $leveldk_money = 0;
        $scoredk_money = 0;
        if($ogtotalprice > 0){
            if($order['coupon_money']){
                $coupon_money = dd_money_format($ogtotalprice/$order['product_price'] * $order['coupon_money']);
            }
            if($order['leveldk_money']){
                $leveldk_money = dd_money_format($ogtotalprice/$order['product_price'] * $order['leveldk_money']);

            }
            if($order['scoredk_money']){
                $scoredk_money = dd_money_format($ogtotalprice/$order['product_price'] * $order['scoredk_money']);
            }

        }
        return    dd_money_format($ogtotalprice - $coupon_money - $leveldk_money- $scoredk_money);
    }
    //扫码枪微信扫码支付
    protected function wxScanPay($cashier_id=0,$tableid=0,$mid=0,$couponrid=0,$userscore=0,$cuxiaolist=[]){
        $auth_code = input('param.auth_code');
        //过滤capslock
        $auth_code = str_replace('capslock','',str_replace(' ','',strtolower($auth_code)));
        //验证code是否正确
        $reg = '/^1[0-6][0-9]{16}$/';
        if(!preg_match($reg,$auth_code)){
            return $this->json(0, '无效的付款码:'.$auth_code);
        }
        
        $order = $this->getWaitOrder($cashier_id,$tableid);
        if (empty($order)) {
            return $this->json(0, '无待结算订单');
        }
        $goodslist = Db::name('restaurant_shop_order_goods')->where('orderid', $order['id'])->select()->toArray();
        if (empty($goodslist)) {
            return $this->json(0, '无待结算菜品');
        }
        foreach ($goodslist as $k=>$v){
            if($v['protype']==1){
                //库存校验
                $gginfo = Db::name('restaurant_product_guige')->where('aid',aid)->where('id',$v['ggid'])->find();
                if($gginfo['stock']<$v['num']){
                    return $this->json(0, $v['proname'].'('.$v['ggname'].')'.'库存不足');
                }
            }
        }
        $orderResult = $this->getOrderPrice($order,$couponrid,$userscore,$mid);
        if($orderResult['status']!=1){
            return $this->json(0, $orderResult['msg']);
        }
        if(getcustom('restaurant_scan_qrcode_coupon')){
            $coupon_code = input('param.coupon_code');
            $is_scan = input('param.is_scan');
            $qrcode_coupon_money = 0;
            if($coupon_code && $is_scan){
                $qrcode_return= $this->computeQrcodeCoupon($coupon_code,$order,$orderResult['product_price'],$orderResult['totalprice']);
                if($qrcode_return['status'] ==0){
                    return $this->json(0, $qrcode_return['msg']);
                }else{
                    $qrcode_coupon_money= $qrcode_return['qrcode_coupon_money'];
                }
            }
            $orderResult['totalprice'] = $orderResult['totalprice'] - $qrcode_coupon_money;
            $orderResult['qrcode_coupon_money'] = $qrcode_coupon_money;
            $orderResult['coupon_code'] = $coupon_code;
        }
        if(getcustom('restaurant_cashdesk_auth_enter')){
            $direct_money = input('param.direct_money',0);//直接优惠价格
            $direct_auth_uid = input('param.direct_auth_uid');
            $orderResult['totalprice'] = dd_money_format($orderResult['totalprice'] - $direct_money);
        }
        $set = Db::name('admin_set')->where('aid',aid)->find();
        $wxpaymoney  = $orderResult['totalprice'];
        
        $orderup= [];
        $orderup['status'] = 3;
        $orderup['paytime'] = time();  
        $orderup['product_price'] = $orderResult['product_price'];
        $orderup['totalprice'] = $orderResult['totalprice'];
        $orderup['moling_money'] = $orderResult['moling_money'];
        if($orderResult['coupon_money']>0){
            $orderup['coupon_money'] = $orderResult['coupon_money'];
            $orderup['coupon_rid'] = $couponrid;
        }
        if(getcustom('restaurant_cashdesk_cuxiao')){
            $orderup['cuxiao_money'] = $orderResult['cuxiao_money'];
            $orderup['cuxiao_ids'] = implode(',',$orderResult['cuxiao_checked']);
        }
        if(getcustom('restaurant_scan_qrcode_coupon')){
            $orderup['qrcode_coupon_money'] = $orderResult['qrcode_coupon_money'];
            $orderup['coupon_code'] = $orderResult['coupon_code'];
        }
        if(getcustom('restaurant_cashdesk_auth_enter')){
            $orderup['qrcode_coupon_money'] = $direct_money;
            $orderup['direct_auth_uid'] = $direct_auth_uid;
        }
        if(getcustom('restaurant_table_timing')){
            $orderup['timing_money'] = $orderResult['timing_money'];
        }
        if(getcustom('restaurant_table_minprice')){
            $orderup['service_money'] = $orderResult['service_money'];
        }
        $orderup['scoredk_money'] = $orderResult['scoredk_money'];
        $orderup['scoredkscore'] = $orderResult['scoredkscore'];
        $orderup['leveldk_money'] = $orderResult['leveldk_money'];
        $orderup['tea_fee'] = $orderResult['tea_fee'];
        $orderup['uid'] = $this->uid;
        $orderup['mid'] = $mid;
        
        if($wxpaymoney > 0){
            $wxplatform = 'restaurant_cashdesk';
            $appinfo = Db::name('admin_setapp_restaurant_cashdesk')->where('aid',aid)->where('bid',0)->find();
            $pars = [];
            
            if($appinfo['wxpay_type']==0){
                $pars['appid'] = $appinfo['appid'];
                $pars['mch_id'] = $appinfo['wxpay_mchid'];
                $mchkey = $appinfo['wxpay_mchkey'];
            }else{
                $dbwxpayset = Db::name('sysset')->where('name','wxpayset')->value('value');
                $dbwxpayset = json_decode($dbwxpayset,true);
                if(!$dbwxpayset){
                    return $this->json(0,'未配置服务商微信支付信息');
                }
                $pars['appid'] = $dbwxpayset['appid'];
                //$pars['sub_appid'] = $appid;
                $pars['mch_id'] = $dbwxpayset['mchid'];
                $pars['sub_mch_id'] = $appinfo['wxpay_sub_mchid'];
                $mchkey = $dbwxpayset['mchkey'];
            }
            if(bid > 0){
                $bappinfo = Db::name('admin_setapp_restaurant_cashdesk')->where('aid',aid)->where('bid',bid)->find();
                //1:服务商 2：平台收款 3：独立收款 0：关闭
                $restaurant_sysset = Db::name('restaurant_admin_set')->where('aid',aid)->find();
               
                if(!$restaurant_sysset || $restaurant_sysset['business_cashdesk_wxpay_type'] ==0){
                    return  $this->json(0,'微信收款已禁用');
                }
                if($restaurant_sysset['business_cashdesk_wxpay_type'] ==1){
                    $dbwxpayset = Db::name('sysset')->where('name','wxpayset')->value('value');
                    $dbwxpayset = json_decode($dbwxpayset,true);
                    $pars['appid'] = $dbwxpayset['appid'];
                    $pars['mch_id'] = $dbwxpayset['mchid'];
                    $pars['sub_mch_id'] = $bappinfo['wxpay_sub_mchid'];
                    $mchkey = $dbwxpayset['mchkey'];
                }
                if($restaurant_sysset['business_cashdesk_wxpay_type'] ==3){
                    if($bappinfo['wxpay_type']==0){
                        $pars['appid'] = $bappinfo['appid'];
                        $pars['mch_id'] = $bappinfo['wxpay_mchid'];
                        $mchkey = $bappinfo['wxpay_mchkey'];
                    }else{
                        $bset = Db::name('business_sysset')->where('aid',aid)->find();
                        if($bset['wxfw_status']==2){
                            $dbwxpayset = Db::name('sysset')->where('name','wxpayset')->value('value');
                            $dbwxpayset = json_decode($dbwxpayset,true);
                        }else{
                            $dbwxpayset = [
                                'mchname'=>$bset['wxfw_mchname'],
                                'appid'=>$bset['wxfw_appid'],
                                'mchid'=>$bset['wxfw_mchid'],
                                'mchkey'=>$bset['wxfw_mchkey'],
                                'apiclient_cert'=>$bset['wxfw_apiclient_cert'],
                                'apiclient_key'=>$bset['wxfw_apiclient_key'],
                            ];
                        }
                        if(!$dbwxpayset){
                            return $this->json(0,'未配置服务商微信支付信息');
                        }
                        $pars['appid'] = $dbwxpayset['appid'];
                        //$pars['sub_appid'] = $appid;
                        $pars['mch_id'] = $dbwxpayset['mchid'];
                        $pars['sub_mch_id'] = $bappinfo['wxpay_sub_mchid'];
                        $mchkey = $dbwxpayset['mchkey'];
                    }
                }
                if($restaurant_sysset['business_cashdesk_wxpay_type'] ==2){
                    if($appinfo['wxpay_type']==0){
                        $pars['appid'] = $appinfo['appid'];
                        $pars['mch_id'] = $appinfo['wxpay_mchid'];
                        $mchkey = $appinfo['wxpay_mchkey'];
                    }else{
                        $bset = Db::name('business_sysset')->where('aid',aid)->find();
                        if($bset['wxfw_status']==2){
                            $dbwxpayset = Db::name('sysset')->where('name','wxpayset')->value('value');
                            $dbwxpayset = json_decode($dbwxpayset,true);
                        }else{
                            $dbwxpayset = [
                                'mchname'=>$bset['wxfw_mchname'],
                                'appid'=>$bset['wxfw_appid'],
                                'mchid'=>$bset['wxfw_mchid'],
                                'mchkey'=>$bset['wxfw_mchkey'],
                                'apiclient_cert'=>$bset['wxfw_apiclient_cert'],
                                'apiclient_key'=>$bset['wxfw_apiclient_key'],
                            ];
                        }
                        if(!$dbwxpayset){
                            return $this->json(0,'未配置服务商微信支付信息');
                        }
                        $pars['appid'] = $dbwxpayset['appid'];
                        //$pars['sub_appid'] = $appid;
                        $pars['mch_id'] = $dbwxpayset['mchid'];
                        $pars['sub_mch_id'] = $appinfo['wxpay_sub_mchid'];
                        $mchkey = $dbwxpayset['mchkey'];
                    }
                }
            }
            $pars['body'] = $set['name'].'-付款码付款';
            $pars['out_trade_no'] = $order['ordernum'];
            $pars['total_fee'] = $wxpaymoney*100;
            $pars['spbill_create_ip'] = request()->ip();
            $pars['auth_code'] = $auth_code;
            $pars['nonce_str'] = random(8);
            ksort($pars, SORT_STRING);
            $string1 = '';
            foreach ($pars as $key => $v){
                if (empty($v)) {
                    continue;
                }
                $string1 .= "{$key}={$v}&";
            }
            $string1 .= "key=".$mchkey;
            $pars['sign'] = strtoupper(md5($string1));
            $dat = array2xml($pars);
            $response = request_post('https://api.mch.weixin.qq.com/pay/micropay', $dat);
            $response = @simplexml_load_string($response, 'SimpleXMLElement', LIBXML_NOCDATA);
            //直接支付成功
            if($response->return_code=='SUCCESS' && $response->result_code=='SUCCESS' && $response->trade_type=='MICROPAY'){
                $response = json_decode(json_encode($response),true);
                $transaction_id = $response['transaction_id'];
            }
            else{
                $result = false;
                for($i=0;$i<10;$i++){
                    $pars2          = array();
                    if($appinfo['wxpay_type']==0){
                        $pars2['appid'] = $appinfo['appid'];
                        $pars2['mch_id'] = $appinfo['wxpay_mchid'];
                        $mchkey = $appinfo['wxpay_mchkey'];
                    }else{
                        $dbwxpayset = Db::name('sysset')->where('name','wxpayset')->value('value');
                        $dbwxpayset = json_decode($dbwxpayset,true);
                        if(!$dbwxpayset){
                            return $this->json(0,'未配置服务商微信支付信息');
                        }
                        $pars2['appid'] = $dbwxpayset['appid'];
                        $pars2['mch_id'] = $dbwxpayset['mchid'];
                        $pars2['sub_mch_id'] = $appinfo['wxpay_sub_mchid'];
                        $mchkey = $dbwxpayset['mchkey'];
                    }
                    $pars2['out_trade_no'] = $order['ordernum'];
                    $pars2['nonce_str'] = random(8);
                    ksort($pars2, SORT_STRING);
                    $string2 = '';
                    foreach ($pars2 as $key => $v){
                        if (empty($v)) {
                            continue;
                        }
                        $string2 .= "{$key}={$v}&";
                    }
                    $string2 .= "key=".$mchkey;
                    $pars2['sign'] = strtoupper(md5($string2));
                    $dat2 = array2xml($pars2);
                    $response2 = request_post('https://api.mch.weixin.qq.com/pay/orderquery', $dat2);
                    $response2 = @simplexml_load_string($response2, 'SimpleXMLElement', LIBXML_NOCDATA);
                    //
                    if($response2->return_code=='FAIL'){
                        return $this->json(0,'支付失败：'.strval($response2->return_msg));
                    }else{
                        if ($response2->return_code=='SUCCESS' && $response2->result_code == 'SUCCESS' && $response2->trade_state=="SUCCESS") {
                            $result = true;
                            $response2 = json_decode(json_encode($response2),true);
                            $transaction_id = $response2['transaction_id'];
                            break;
                        }elseif($response2->trade_state == 'PAYERROR'){
                            $this->refreshOrdernum($order['id']);
                            return $this->json(0,'支付失败：'.strval($response2->trade_state_desc));
                        }
                    }
                    sleep(3);
                }
            }
            
            if($transaction_id){
                $orderup['paytype'] = '餐饮收银台微信收款';
                $orderup['paytypeid'] = 2;
                $orderup['paynum'] =$transaction_id;
                $orderup['platform'] = $wxplatform;
            }else{
                return $this->json(0,'支付失败:'.$response->return_msg,$response);
            }
        }else{
            $orderup['paytype'] = '无须支付';
            $orderup['paynum'] = '';
        }
        $res = Db::name('restaurant_shop_order')->where('id',$order['id'])->update($orderup);
        //更新收银台表
        $payorderid =\app\models\Payorder::createorder(aid, $order['bid'], $mid, 'restaurant_shop', $order['id'], $order['ordernum'], '餐饮收银台微信收款', $orderup['totalprice'], $orderResult['scoredkscore']);
        Db::name('payorder')->where('id',$payorderid)->update(['paytype'=>'微信收款-餐饮收银台','paytypeid'=>2,'paynum'=>$orderup['paynum'],'status' =>1,'paytime' => time(),'platform' =>'cashdesk']);

        if($res){
            $this->afterPay($order['id'],$cashier_id);
            if(getcustom('restaurant_cashdesk_cuxiao')){
                $this->addCuxiaoGiveProduct($order,$orderResult['cuxiao_give_product']);
            }
            return $this->json(1,'支付成功',$response);
        }else{
            return $this->json(0,'支付失败！！！');
        }
    }
    //支付宝扫码支付
    protected function aliScanPay($cashier_id,$tableid=0,$mid=0,$couponrid=0,$userscore=0,$cuxiaolist=[]){
        if(getcustom('cashdesk_alipay')) {
            $auth_code = input('param.auth_code');
            //过滤capslock
            $auth_code = str_replace('capslock', '', str_replace(' ', '', strtolower($auth_code)));
            //验证code是否正确
            $reg = '/^(?:2[5-9]|30)\d{14,22}$/';
            if (!preg_match($reg, $auth_code)) {
                return $this->json(0, '无效的付款码:' . $auth_code);
            }
            $order = $this->getWaitOrder($cashier_id,$tableid);
            if (empty($order)) {
                return $this->json(0, '无待结算订单');
            }
            $goodslist = Db::name('restaurant_shop_order_goods')->where('orderid', $order['id'])->select()->toArray();
            if (empty($goodslist)) {
                return $this->json(0, '无待结算菜品');
            }
            foreach ($goodslist as $k => $v) {
                if ($v['protype'] == 1) {
                    //库存校验
                    $gginfo = Db::name('restaurant_product_guige')->where('aid', aid)->where('id', $v['ggid'])->find();
                    if ($gginfo['stock'] < $v['num']) {
                        return $this->json(0, $v['proname'] . '(' . $v['ggname'] . ')' . '库存不足');
                    }
                }
            }
            $orderResult = $this->getOrderPrice($order, $couponrid, $userscore, $mid);
            if ($orderResult['status'] != 1) {
                return $this->json(0, $orderResult['msg']);
            }
            if(getcustom('restaurant_scan_qrcode_coupon')){
                $coupon_code = input('param.coupon_code');
                $is_scan = input('param.is_scan');
                $qrcode_coupon_money = 0;
                if($coupon_code && $is_scan){
                    $qrcode_return= $this->computeQrcodeCoupon($coupon_code,$order,$orderResult['product_price'],$orderResult['totalprice']);
                    if($qrcode_return['status'] ==0){
                        return $this->json(0, $qrcode_return['msg']);
                    }else{
                        $qrcode_coupon_money= $qrcode_return['qrcode_coupon_money'];
                    }
                }
                $orderResult['totalprice'] = $orderResult['totalprice'] - $qrcode_coupon_money;
                $orderResult['qrcode_coupon_money'] = $qrcode_coupon_money;
                $orderResult['coupon_code'] = $coupon_code;
            }
            if(getcustom('restaurant_cashdesk_auth_enter')){
                $direct_money = input('param.direct_money',0);//直接优惠价格
                $direct_auth_uid = input('param.direct_auth_uid');
                $orderResult['totalprice'] = dd_money_format($orderResult['totalprice'] - $direct_money);
            }
            $wxpaymoney = $orderResult['totalprice'];
            $platform = 'restaurant_cashdesk';
            
            $orderup= [];
            $orderup['status'] = 3;
            $orderup['paytime'] = time();
            $orderup['product_price'] = $orderResult['product_price'];
            $orderup['totalprice'] = $orderResult['totalprice'];
            $orderup['moling_money'] = $orderResult['moling_money'];
            if($orderResult['coupon_money']>0){
                $orderup['coupon_money'] = $orderResult['coupon_money'];
                $orderup['coupon_rid'] = $couponrid;
            }
            if(getcustom('restaurant_cashdesk_cuxiao')){
                $orderup['cuxiao_money'] = $orderResult['cuxiao_money'];
                $orderup['cuxiao_ids'] = implode(',',$orderResult['cuxiao_checked']);
            }
            if(getcustom('restaurant_scan_qrcode_coupon')){
                $orderup['qrcode_coupon_money'] = $orderResult['qrcode_coupon_money'];
                $orderup['coupon_code'] = $orderResult['coupon_code'];
            }
            if(getcustom('restaurant_cashdesk_auth_enter')){
                $orderup['qrcode_coupon_money'] = $direct_money;
                $orderup['direct_auth_uid'] = $direct_auth_uid;
            }
            if(getcustom('restaurant_table_timing')){
                $orderup['timing_money'] = $orderResult['timing_money'];
            }
            if(getcustom('restaurant_table_minprice')){
                $orderup['service_money'] = $orderResult['service_money'];
            }
            $orderup['scoredk_money'] = $orderResult['scoredk_money'];
            $orderup['scoredkscore'] = $orderResult['scoredkscore'];
            $orderup['leveldk_money'] = $orderResult['leveldk_money'];
            $orderup['tea_fee'] = $orderResult['tea_fee'];
            $orderup['uid'] = $this->uid;
            $orderup['mid'] = $mid;
            
            if($wxpaymoney > 0){
                $set = Db::name('admin_set')->where('aid',aid)->find();
                $return = Alipay::build_scan(aid,bid,'',$set['name'].'-当面付',$order['ordernum'],$wxpaymoney,$platform,'',$auth_code,$platform);
                if($return['status'] ==1){
                    $orderup['paytype'] = '餐饮收银台支付宝收款';
                    $orderup['paytypeid'] = 3;
                    $orderup['paynum'] = $return['data']['trade_no'];
                    $orderup['platform'] = $platform;
                }else{
                    return $this->json(0,$return['msg']);
                } 
            }else{
                $orderup['paytype'] = '无须支付';
                $orderup['paynum'] = '';
            }
            $res = Db::name('restaurant_shop_order')->where('id',$order['id'])->update($orderup);
            //更新收银台表
            $payorderid =\app\models\Payorder::createorder(aid, $order['bid'], $mid, 'restaurant_shop', $order['id'], $order['ordernum'], '餐饮收银台支付宝收款', $orderup['totalprice'], $orderResult['scoredkscore']);
            Db::name('payorder')->where('id',$payorderid)->update(['paytype'=>'支付宝收款-餐饮收银台','paytypeid'=>3,'paynum'=>$orderup['paynum'],'status' =>1,'paytime' => time(),'platform' =>'cashdesk']);
            if($res){
                $this->afterPay($order['id'],$cashier_id);
                if(getcustom('restaurant_cashdesk_cuxiao')){
                    $this->addCuxiaoGiveProduct($order,$orderResult['cuxiao_give_product']);
                }
                return $this->json(1,'支付成功');
            }else{
                return $this->json(0,'支付失败！！！');
            }
        }   
    }
    
    //现金支付（线下其他支付方式，直接更改订单状态）
    protected function cashPay($cashier_id=0,$tableid=0,$mid=0,$couponrid=0,$userscore=0,$cuxiaolist=[]){
        //查询自定义支付存不存在
        $order = $this->getWaitOrder($cashier_id,$tableid);
        if (empty($order)) {
            return $this->json(0, '无待结算订单');
        }
        $goodslist = Db::name('restaurant_shop_order_goods')->where('orderid', $order['id'])->select()->toArray();
        if (empty($goodslist)) {
            return $this->json(0, '无待结算菜品');
        }
        $orderResult = $this->getOrderPrice($order,$couponrid,$userscore,$mid,$cuxiaolist);
        if($orderResult['status']!=1){
            return $this->json(0, $orderResult['msg']);
        }
        $orderup = [];
        if(getcustom('restaurant_cashdesk_mix_pay')){
            //组合支付 如果支付金额不足
            $cash_money = input('param.cashmoney',0);//给的现金
            $paymoney = input('param.paymoney');              
            if($paymoney>0){
                $yf_money =dd_money_format($orderResult['totalprice'] -  $cash_money);
                if($yf_money !=$paymoney){
                    return $this->json(0, '组合支付金额错误');
                }
                $auth_code = input('param.auth_code');
                $mix_paytype =input('param.mix_paytype');
                $mix_return = $this->together_pay($order,$mix_paytype,$auth_code,$paymoney);
                if(!$mix_return['transaction_id']){
                    return $this->json(0, '支付失败，请重新扫码');
                } else{
                    $orderup['mix_paytypeid'] = $mix_return['paytypeid'];
                    $orderup['mix_money'] = $paymoney;
                    $orderup['mix_paynum'] = $mix_return['transaction_id'];
                    $orderup['mix_paytype'] = $mix_return['paytype'];
                }
            }
        }
        
        if(getcustom('restaurant_scan_qrcode_coupon')){
            $coupon_code = input('param.coupon_code');
            $is_scan = input('param.is_scan');
            $qrcode_coupon_money = 0;
            if($coupon_code && $is_scan){
                $qrcode_return= $this->computeQrcodeCoupon($coupon_code,$order,$orderResult['product_price'],$orderResult['totalprice']);
                if($qrcode_return['status'] ==0){
                    return $this->json(0, $qrcode_return['msg']);
                }else{
                    $qrcode_coupon_money= $qrcode_return['qrcode_coupon_money'];
                }
            }
            $orderResult['totalprice'] = $orderResult['totalprice'] - $qrcode_coupon_money;
            $orderResult['qrcode_coupon_money'] = $qrcode_coupon_money;
            $orderResult['coupon_code'] = $coupon_code;
        }
        if(getcustom('restaurant_cashdesk_auth_enter')){
            $direct_money = input('param.direct_money',0);//直接优惠价格
            $direct_auth_uid = input('param.direct_auth_uid');
            $orderResult['totalprice'] = dd_money_format($orderResult['totalprice'] - $direct_money);
        }
        //抹零
     
        $orderup['totalprice'] = $orderResult['totalprice'];
        $orderup['product_price'] = $orderResult['product_price'];
        $orderup['moling_money'] = $orderResult['moling_money'];
        if($orderResult['coupon_money']>0){
            $orderup['coupon_money'] = $orderResult['coupon_money'];
            $orderup['coupon_rid'] = $couponrid;
        }
        if(getcustom('restaurant_cashdesk_cuxiao')){
            $orderup['cuxiao_money'] = $orderResult['cuxiao_money'];
            $orderup['cuxiao_ids'] = implode(',',$orderResult['cuxiao_checked']);
        }
        if(getcustom('restaurant_scan_qrcode_coupon')){
            $orderup['qrcode_coupon_money'] = $orderResult['qrcode_coupon_money'];
            $orderup['coupon_code'] = $orderResult['coupon_code'];
        }
        if(getcustom('restaurant_cashdesk_auth_enter')){
            $orderup['direct_money'] = $direct_money;
            $orderup['direct_auth_uid'] = $direct_auth_uid;
        }
        if(getcustom('restaurant_table_timing')){
            $orderup['timing_money'] = $orderResult['timing_money'];
        }
        if(getcustom('restaurant_table_minprice')){
            $orderup['service_money'] = $orderResult['service_money'];
        }
        $orderup['scoredk_money'] = $orderResult['scoredk_money'];
        $orderup['scoredkscore'] = $orderResult['scoredkscore'];
        $orderup['leveldk_money'] = $orderResult['leveldk_money'];
        $orderup['tea_fee'] = $orderResult['tea_fee'];
        $orderup['mid'] = $mid;
        $orderup['paytime'] = time();
        $orderup['paytype'] = t('现金').'收款';
        $orderup['paytypeid'] = 0;
        $orderup['status'] = 3;
        $orderup['uid'] = $this->uid;
        Db::name('restaurant_shop_order')->where('bid', bid)->where('aid', aid)->where('id', $order['id'])->update($orderup);
        //更新收银台表
        $payorderid =\app\models\Payorder::createorder(aid, $order['bid'], $mid, 'restaurant_shop', $order['id'], $order['ordernum'], '餐饮收银台'.t('现金').'收款', $orderup['totalprice'], $orderResult['scoredkscore']);
        Db::name('payorder')->where('id',$payorderid)->update(['paytype'=>t('现金').'收款-餐饮收银台','paytypeid'=>0,'paynum'=>0,'status' =>1,'paytime' => time(),'platform' =>'cashdesk']);

        $this->afterPay($order['id'],$cashier_id);
        if(getcustom('restaurant_cashdesk_cuxiao')){
            $this->addCuxiaoGiveProduct($order,$orderResult['cuxiao_give_product']);
        }
        return $this->json(1, '支付成功');
    }
    //自定义支付（线下其他支付方式，直接更改订单状态）
    protected function customPay($custom_pay_id=0,$cashier_id=0,$tableid=0,$mid=0,$couponrid=0,$userscore=0,$cuxiaolist=[]){
        if(getcustom('restaurant_cashdesk_custom_pay')){
            $order = $this->getWaitOrder($cashier_id,$tableid);
            if (empty($order)) {
                return $this->json(0, '无待结算订单');
            }
            $goodslist = Db::name('restaurant_shop_order_goods')->where('orderid', $order['id'])->select()->toArray();
            if (empty($goodslist)) {
                return $this->json(0, '无待结算菜品');
            }
            $orderResult = $this->getOrderPrice($order,$couponrid,$userscore,$mid,$cuxiaolist);
            if($orderResult['status']!=1){
                return $this->json(0, $orderResult['msg']);
            }
            $paytype = Db::name('restaurant_cashdesk_custom_pay')->where('aid',$order['aid'])->where('bid',$order['bid'])->where('id',$custom_pay_id)->value('title');
            $paytype = $paytype?$paytype:'自定义支付';
            $orderup = [];
            //子支付类型，方便统计
            $orderup['child_paytypeid'] =$custom_pay_id;
            if(getcustom('restaurant_cashdesk_mix_pay')){
                //组合支付 如果支付金额不足
                $cash_money = input('param.cashmoney',0);//给的现金
                $paymoney = input('param.paymoney');
                if($paymoney>0){
                    $yf_money =dd_money_format($orderResult['totalprice'] -  $cash_money);
                    if($yf_money !=$paymoney){
                        return $this->json(0, '组合支付金额错误');
                    }
                    $auth_code = input('param.auth_code');
                    $mix_paytype =input('param.mix_paytype');
                    $mix_return = $this->together_pay($order,$mix_paytype,$auth_code,$paymoney);
                    if(!$mix_return['transaction_id']){
                        return $this->json(0, '支付失败，请重新扫码');
                    } else{
                        $orderup['mix_paytypeid'] = $mix_return['paytypeid'];
                        $orderup['mix_money'] = $paymoney;
                        $orderup['mix_paynum'] = $mix_return['transaction_id'];
                        $orderup['mix_paytype'] = $mix_return['paytype'];
                    }
                }
            }
    
            if(getcustom('restaurant_scan_qrcode_coupon')){
                $coupon_code = input('param.coupon_code');
                $is_scan = input('param.is_scan');
                $qrcode_coupon_money = 0;
                if($coupon_code && $is_scan){
                    $qrcode_return= $this->computeQrcodeCoupon($coupon_code,$order,$orderResult['product_price'],$orderResult['totalprice']);
                    if($qrcode_return['status'] ==0){
                        return $this->json(0, $qrcode_return['msg']);
                    }else{
                        $qrcode_coupon_money= $qrcode_return['qrcode_coupon_money'];
                    }
                }
                $orderResult['totalprice'] = $orderResult['totalprice'] - $qrcode_coupon_money;
                $orderResult['qrcode_coupon_money'] = $qrcode_coupon_money;
                $orderResult['coupon_code'] = $coupon_code;
            }
            if(getcustom('restaurant_cashdesk_auth_enter')){
                $direct_money = input('param.direct_money',0);//直接优惠价格
                $direct_auth_uid = input('param.direct_auth_uid');
                $orderResult['totalprice'] = dd_money_format($orderResult['totalprice'] - $direct_money);
            }
            //抹零
    
            $orderup['totalprice'] = $orderResult['totalprice'];
            $orderup['product_price'] = $orderResult['product_price'];
            $orderup['moling_money'] = $orderResult['moling_money'];
            if($orderResult['coupon_money']>0){
                $orderup['coupon_money'] = $orderResult['coupon_money'];
                $orderup['coupon_rid'] = $couponrid;
            }
            if(getcustom('restaurant_cashdesk_cuxiao')){
                $orderup['cuxiao_money'] = $orderResult['cuxiao_money'];
                $orderup['cuxiao_ids'] = implode(',',$orderResult['cuxiao_checked']);
            }
            if(getcustom('restaurant_scan_qrcode_coupon')){
                $orderup['qrcode_coupon_money'] = $orderResult['qrcode_coupon_money'];
                $orderup['coupon_code'] = $orderResult['coupon_code'];
            }
            if(getcustom('restaurant_cashdesk_auth_enter')){
                $orderup['direct_money'] = $direct_money;
                $orderup['direct_auth_uid'] = $direct_auth_uid;
            }
            if(getcustom('restaurant_table_timing')){
                $orderup['timing_money'] = $orderResult['timing_money'];
            }
            if(getcustom('restaurant_table_minprice')){
                $orderup['service_money'] = $orderResult['service_money'];
            }
            $orderup['scoredk_money'] = $orderResult['scoredk_money'];
            $orderup['scoredkscore'] = $orderResult['scoredkscore'];
            $orderup['leveldk_money'] = $orderResult['leveldk_money'];
            $orderup['tea_fee'] = $orderResult['tea_fee'];
            $orderup['mid'] = $mid;
            $orderup['paytime'] = time();
            $orderup['paytype'] = $paytype;
            $orderup['paytypeid'] = 10000+$custom_pay_id;
            $orderup['status'] = 3;
            $orderup['uid'] = $this->uid;
            Db::name('restaurant_shop_order')->where('bid', bid)->where('aid', aid)->where('id', $order['id'])->update($orderup);

            //更新收银台表
            $payorderid =\app\models\Payorder::createorder(aid, $order['bid'], $mid, 'restaurant_shop', $order['id'], $order['ordernum'], '餐饮收银台'.$paytype.'收款', $orderup['totalprice'], $orderResult['scoredkscore']);
            Db::name('payorder')->where('id',$payorderid)->update(['paytype'=>$paytype.'收款-餐饮收银台','paytypeid'=>$orderup['paytypeid'],'paynum'=>$orderup['paytypeid'],'status' =>1,'paytime' => time(),'platform' =>'cashdesk']);
            
            $this->afterPay($order['id'],$cashier_id);
            if(getcustom('restaurant_cashdesk_cuxiao')){
                $this->addCuxiaoGiveProduct($order,$orderResult['cuxiao_give_product']);
            }
            return $this->json(1, '支付成功');
        }
    }
    //余额支付
    protected function moneyPay($cashier_id=0,$tableid=0,$mid=0,$couponrid=0,$userscore=0,$cuxiaolist=[]){
        
        $order = $this->getWaitOrder($cashier_id,$tableid);
        if (empty($order)) {
            return $this->json(0, '无待结算订单');
        }
        $scoredk_money = 0;
        //计算总价
        $goodslist = Db::name('restaurant_shop_order_goods')->where('orderid', $order['id'])->select()->toArray();
        if (empty($goodslist)) {
            return $this->json(0, '无待结算菜品');
        }
    
        if(empty($mid)){
            return $this->json(0, t('余额').'支付请选择会员账号');
        }
        Db::startTrans();
        $member = Db::name('member')->where('id', $mid)->where('aid', aid)->lock(true)->find();
        if(empty($member)){
            Db::rollback();
            return $this->json(0,'会员信息有误');
        }
        if(getcustom('restaurant_cashdesk_member_paypwd')){
            //使用密码
            $paypwd_use_status = Db::name('restaurant_cashdesk')->where('aid',aid)->where('bid',bid)->value('paypwd_use_status');
            $paypwd = input('param.paypwd');
            //比如输入密码 且密码为空 
            if(!$paypwd && $paypwd_use_status['paypwd_use_status'] ==1){
                return $this->json(0,'请输入正确的支付密码');
            }
            if($paypwd && md5($paypwd.$member['paypwd_rand']) != $member['paypwd']){
                return $this->json(0,'请输入正确的支付密码');
            }
        }
        foreach ($goodslist as $k=>$v){
            if($v['protype']==1){
                //库存校验
                $gginfo = Db::name('restaurant_product_guige')->where('aid',aid)->where('id',$v['ggid'])->find();
                if($gginfo && $gginfo['stock']<$v['num']){
                    return $this->json(0, $v['proname'].'('.$v['ggname'].')'.'库存不足');
                }
            }
        }
        $orderResult = $this->getOrderPrice($order,$couponrid,$userscore,$mid,$cuxiaolist);
        if($orderResult['status']!=1){
            Db::rollback();
            return $this->json(0, $orderResult['msg']);
        }
        if(getcustom('restaurant_scan_qrcode_coupon')){
            $coupon_code = input('param.coupon_code');
            $is_scan = input('param.is_scan');
            $qrcode_coupon_money = 0;
            if($coupon_code && $is_scan){
                $qrcode_return= $this->computeQrcodeCoupon($coupon_code,$order,$orderResult['product_price'],$orderResult['totalprice']);
                if($qrcode_return['status'] ==0){
                    return $this->json(0, $qrcode_return['msg']);
                }else{
                    $qrcode_coupon_money= $qrcode_return['qrcode_coupon_money'];
                }
            }
            $orderResult['totalprice'] = $orderResult['totalprice'] - $qrcode_coupon_money;
            $orderResult['qrcode_coupon_money'] = $qrcode_coupon_money;
            $orderResult['coupon_code'] = $coupon_code;
        }
        if(getcustom('restaurant_cashdesk_auth_enter')){
            $direct_money = input('param.direct_money',0);//直接优惠价格
            $direct_auth_uid = input('param.direct_auth_uid');
            $orderResult['totalprice'] = dd_money_format($orderResult['totalprice'] - $direct_money);
        }
        //抹零
        $orderup = [];
        $orderup['totalprice'] = $orderResult['totalprice'];
        $orderup['product_price'] = $orderResult['product_price'];
        $orderup['moling_money'] = $orderResult['moling_money'];
        if($orderResult['coupon_money']>0){
            $orderup['coupon_money'] = $orderResult['coupon_money'];
            $orderup['coupon_rid'] = $couponrid;
        }
        if(getcustom('restaurant_cashdesk_cuxiao')){
            $orderup['cuxiao_money'] = $orderResult['cuxiao_money'];
            $orderup['cuxiao_ids'] = implode(',',$orderResult['cuxiao_checked']);
        }
        if(getcustom('restaurant_scan_qrcode_coupon')){
            $orderup['qrcode_coupon_money'] = $orderResult['qrcode_coupon_money'];
            $orderup['coupon_code'] = $orderResult['coupon_code'];
        }
        if(getcustom('restaurant_cashdesk_auth_enter')){
            $orderup['direct_money'] = $direct_money;
            $orderup['direct_auth_uid'] = $direct_auth_uid;
        }
        if(getcustom('restaurant_table_timing')){
            $orderup['timing_money'] = $orderResult['timing_money'];
        }
        if(getcustom('restaurant_table_minprice')){
            $orderup['service_money'] = $orderResult['service_money'];
        }
        $orderup['scoredk_money'] = $orderResult['scoredk_money'];
        $orderup['scoredkscore'] = $orderResult['scoredkscore'];
        $orderup['leveldk_money'] = $orderResult['leveldk_money'];
        $orderup['tea_fee'] = $orderResult['tea_fee'];
        $orderup['status'] = 3;
        $orderup['paytypeid'] = 1;
        $orderup['paytype'] = t('余额').'支付';
        $orderup['paytime'] = time();
        $orderup['mid'] = $mid;
        $orderup['uid'] = $this->uid;
        if($orderup['coupon_money']>0){
            Db::name('coupon_record')->where('id', $couponrid)->update(['status' => 1, 'usetime' => time()]);
        }
        $totalscore = $orderResult['scoredkscore'];
        Db::name('restaurant_shop_order')->where('id', $order['id'])->update($orderup);
        $payorderid = \app\models\Payorder::createorder(aid, $order['bid'], $mid, 'restaurant_shop', $order['id'], $order['ordernum'], '餐饮收银台'.t('余额').'收款', $orderup['totalprice'], $totalscore);
        if($member['money'] < $orderup['totalprice']){
            Db::rollback();
            return $this->json(0,t('余额').'不足,请充值');
        }
        if($orderup['totalprice'] > 0){
            //减去会员的余额
            $params = [];
            if(getcustom('moneylog_detail')){
                $params['type'] = 'restaurant_shop';
                $params['ordernum'] = $order['ordernum'];
            }
            \app\commons\Member::addmoney(aid,$mid,-$orderup['totalprice'],'餐饮收银台买单,订单号: '.$order['ordernum'],0,'','',$params);
        }
//        if($totalscore > 0){
//            //减去会员的积分
//            \app\commons\Member::addscore(aid,$mid,-$totalscore,'餐饮收银台买单,订单号: '.$order['ordernum']);
//        }
//        $res = \app\models\Payorder::payorder($payorderid,t('余额').'收款'.'-餐饮收银台',$orderup['paytypeid'],1);
        $res = Db::name('payorder')->where('id',$payorderid)->update(['paytype'=>'余额收款-餐饮收银台','paytypeid'=>$orderup['paytypeid'],'paynum'=>$orderup['paynum'],'status' =>1,'paytime' => time(),'platform' =>'cashdesk']);
        if($res) {
            //减库存
            foreach ($goodslist as $k=>$v){
                $num = $v['num'];
                Db::name('restaurant_product_guige')->where('aid',aid)->where('id',$v['ggid'])->update(['stock'=>Db::raw("stock-$num"),'sales'=>Db::raw("sales+$num")]);
                Db::name('restaurant_product')->where('aid',aid)->where('id',$v['proid'])->update(['stock'=>Db::raw("stock-$num"),'sales'=>Db::raw("sales+$num")]);
            }
            Db::commit();
            $this->afterPay($order['id'],$cashier_id);
            if(getcustom('restaurant_cashdesk_cuxiao')){
                $this->addCuxiaoGiveProduct($order,$orderResult['cuxiao_give_product']);
            }
            return $this->json(1,'付款成功');
        }else{
            Db::rollback();
            return $this->json(0,'付款失败');
        }
    }
    //随行付支付
    protected function sxPay($cashier_id=0,$tableid=0,$mid=0,$couponrid=0,$userscore=0,$cuxiaolist=[]){
        if(getcustom('cashdesk_sxpay')) {
            $auth_code = input('param.auth_code');
            //过滤capslock
            $auth_code = str_replace('capslock', '', str_replace(' ', '', strtolower($auth_code)));
            //验证code是否正确
            if (empty($auth_code)) {
                return $this->json(0, '无效的付款码' );
            }
            $order = $this->getWaitOrder($cashier_id,$tableid);
            if (empty($order)) {
                return $this->json(0, '无待结算订单');
            }
            $goodslist = Db::name('restaurant_shop_order_goods')->where('orderid', $order['id'])->select()->toArray();
            if (empty($goodslist)) {
                return $this->json(0, '无待结算菜品');
            }
            foreach ($goodslist as $k => $v) {
                if ($v['protype'] == 1) {
                    //库存校验
                    $gginfo = Db::name('restaurant_product_guige')->where('aid', aid)->where('id', $v['ggid'])->find();
                    if ($gginfo['stock'] < $v['num']) {
                        return $this->json(0, $v['proname'] . '(' . $v['ggname'] . ')' . '库存不足');
                    }
                }
            }
            $orderResult = $this->getOrderPrice($order, $couponrid, $userscore, $mid,$cuxiaolist);
            if ($orderResult['status'] != 1) {
                return $this->json(0, $orderResult['msg']);
            }
            if(getcustom('restaurant_scan_qrcode_coupon')){
                $coupon_code = input('param.coupon_code');
                $is_scan = input('param.is_scan');
                $qrcode_coupon_money = 0;
                if($coupon_code && $is_scan){
                    $qrcode_return= $this->computeQrcodeCoupon($coupon_code,$order,$orderResult['product_price'],$orderResult['totalprice']);
                    if($qrcode_return['status'] ==0){
                        return $this->json(0, $qrcode_return['msg']);
                    }else{
                        $qrcode_coupon_money= $qrcode_return['qrcode_coupon_money'];
                    }
                }
                $orderResult['totalprice'] = $orderResult['totalprice'] - $qrcode_coupon_money;
                $orderResult['qrcode_coupon_money'] = $qrcode_coupon_money;
                $orderResult['coupon_code'] = $coupon_code;
            }
            if(getcustom('restaurant_cashdesk_auth_enter')){
                $direct_money = input('param.direct_money',0);//直接优惠价格
                $direct_auth_uid = input('param.direct_auth_uid');
                $orderResult['totalprice'] = dd_money_format($orderResult['totalprice'] - $direct_money);
            }
            $wxpaymoney = $orderResult['totalprice'];
            
            $orderup= [];
            $orderup['status'] = 3;
            $orderup['paytime'] = time();
            $orderup['totalprice'] = $orderResult['totalprice'];
            $orderup['product_price'] = $orderResult['product_price'];
            $orderup['moling_money'] = $orderResult['moling_money'];
            if($orderResult['coupon_money']>0){
                $orderup['coupon_money'] = $orderResult['coupon_money'];
                $orderup['coupon_rid'] = $couponrid;
            }
            if(getcustom('restaurant_cashdesk_cuxiao')){
                $orderup['cuxiao_money'] = $orderResult['cuxiao_money'];
                $orderup['cuxiao_ids'] = implode(',',$orderResult['cuxiao_checked']);
            }
            if(getcustom('restaurant_scan_qrcode_coupon')){
                $orderup['qrcode_coupon_money'] = $orderResult['qrcode_coupon_money'];
                $orderup['coupon_code'] = $orderResult['coupon_code'];
            }
            if(getcustom('restaurant_cashdesk_auth_enter')){
                $orderup['direct_money'] = $direct_money;
                $orderup['direct_auth_uid'] = $direct_auth_uid;
            }
            if(getcustom('restaurant_table_timing')){
                $orderup['timing_money'] = $orderResult['timing_money'];
            }
            if(getcustom('restaurant_table_minprice')){
                $orderup['service_money'] = $orderResult['service_money'];
            }
            $orderup['scoredk_money'] = $orderResult['scoredk_money'];
            $orderup['scoredkscore'] = $orderResult['scoredkscore'];
            $orderup['leveldk_money'] = $orderResult['leveldk_money'];
            $orderup['tea_fee'] = $orderResult['tea_fee'];
            $orderup['uid'] = $this->uid;
            $orderup['mid'] = $mid;
            $wx_reg = '/^1[0-6][0-9]{16}$/';//微信 
            $ali_reg = '/^(?:2[5-9]|30)\d{14,22}$/';;//支付宝  
            if(preg_match($wx_reg,$auth_code)){
                $orderup['child_paytypeid'] =2;
            }elseif(preg_match($ali_reg,$auth_code)){
                $orderup['child_paytypeid'] =3;
            }
            if($wxpaymoney > 0){
                $set = Db::name('admin_set')->where('aid',aid)->find();
                $return = Sxpay::build_scan(aid,bid,$set['name'].'-当面付',$order['ordernum'],$wxpaymoney,'restaurant_cashdesk',$auth_code);
                if($return['status'] ==1){
                    $orderup['paytype'] = '餐饮收银台随行付当面付';
                    $orderup['paytypeid'] = 81;//5已在平台被占用，改为81
                    $orderup['paynum'] = $return['data']['trade_no'];
                    $orderup['platform'] = 'restaurant_cashdesk';
                    //更新收银台表
                    $payorderid =\app\models\Payorder::createorder(aid, $order['bid'], $mid, 'restaurant_shop', $order['id'], $order['ordernum'], '餐饮收银台随行付收款', $wxpaymoney, $orderResult['scoredkscore']);
                }else{
                    $payorderid = \app\models\Payorder::createorder(aid, $order['bid'], $mid, 'restaurant_shop', $order['id'], $order['ordernum'], '餐饮收银台随行付收款', $wxpaymoney, $orderResult['scoredkscore']);
                    $transaction_id = $this->sxpayTradequery($payorderid);
                     if($transaction_id){
                         $orderup['paytype'] = '餐饮收银台随行付当面付';
                         $orderup['paytypeid'] = 81;
                         $orderup['paynum'] = $transaction_id;
                         $orderup['platform'] = 'restaurant_cashdesk';
                     }else{
                         return $this->json(0,$return['msg']);
                     }
                }
            }else{
                $orderup['paytype'] = '无须支付';
                $orderup['paynum'] = '';
            }
            Db::name('payorder')->where('id',$payorderid)->update(['paytype'=>'随行付收款-餐饮收银台','paytypeid'=>$orderup['paytypeid'],'paynum'=>$orderup['paynum'],'status' =>1,'paytime' => time(),'platform' =>'cashdesk']);
            $res = Db::name('restaurant_shop_order')->where('id',$order['id'])->update($orderup);
            if($res){
                //减库存
                foreach ($goodslist as $k=>$v){
                    $num = $v['num'];
                    Db::name('restaurant_product_guige')->where('aid',aid)->where('id',$v['ggid'])->update(['stock'=>Db::raw("stock-$num"),'sales'=>Db::raw("sales+$num")]);
                    Db::name('restaurant_product')->where('aid',aid)->where('id',$v['proid'])->update(['stock'=>Db::raw("stock-$num"),'sales'=>Db::raw("sales+$num")]);
                }
                $this->afterPay($order['id'],$cashier_id);
                if(getcustom('restaurant_cashdesk_cuxiao')){
                    $this->addCuxiaoGiveProduct($order,$orderResult['cuxiao_give_product']);
                }
                return $this->json(1,'支付成功');
            }else{
                return $this->json(0,'支付失败！！！');
            }
        }
    }

    //抖音团购券核销
    protected function douyinHexiao($cashier_id=0,$tableid=0,$mid=0,$couponrid=0,$userscore=0,$cuxiaolist=[]){
        if(getcustom('restaurant_douyin_qrcode_hexiao')){
            $cashdesk = Db::name('restaurant_cashdesk')->where('id', $cashier_id)->where('aid', aid)->where('bid', bid)->find();
            if(!$cashdesk){
                return $this->json(0, '系统错误' );
            }
            $order = $this->getWaitOrder($cashier_id,$tableid);
            $orderResult = $this->getOrderPrice($order, $couponrid, $userscore, $mid,$cuxiaolist);
            if(getcustom('restaurant_cashdesk_auth_enter')){
                $direct_money = input('param.direct_money',0);//直接优惠价格
                $direct_auth_uid = input('param.direct_auth_uid');
                $orderResult['totalprice'] = dd_money_format($orderResult['totalprice'] - $direct_money);
            }
            $hxcode = input('param.hxcode','');
            if (empty($hxcode)) {
                return $this->json(0, '无效购券码' );
            }
            $pattern = '/http[s]?:\/\/[\w.]+[\w\/]*[\w.]*\??[\w=&\+\%]*/is';
            //如果是短连接 获取object_id 否则
            $encrypterd_data = '';
            $code ='';
            if(preg_match($pattern, $hxcode)) {
                $get_url =  shortUrlToLongUrl($hxcode);
                $qqData = getPathParams($get_url);
                $encrypterd_data =   $qqData['object_id'];
            }else {
                
                $code = $hxcode;
            }
            $key = input('param.douyinhx_key',[0]);//一码多券
            $res = \app\commons\Douyin::hexiaoQrcode(aid,bid,$cashdesk['poi_id'],$key,$encrypterd_data,$code);
            if($res['data']['error_code'] ==0){
                $results = $res['verify_results'];
                $orderup= [];
                $orderup['status'] = 3;
                $orderup['paytype'] = '餐饮收银台抖音团购券核销';
                $orderup['paytypeid'] = 121;
                $orderup['paynum'] =  $results['verify_id'];
                $orderup['paytime'] = time();
                $orderup['totalprice'] = $orderResult['totalprice'];
                $orderup['product_price'] = $orderResult['product_price'];
                $orderup['moling_money'] = $orderResult['moling_money'];
                if($orderResult['coupon_money']>0){
                    $orderup['coupon_money'] = $orderResult['coupon_money'];
                    $orderup['coupon_rid'] = $couponrid;
                }
                if(getcustom('restaurant_cashdesk_cuxiao')){
                    $orderup['cuxiao_money'] = $orderResult['cuxiao_money'];
                    $orderup['cuxiao_ids'] = implode(',',$orderResult['cuxiao_checked']);
                }
                if(getcustom('restaurant_scan_qrcode_coupon')){
                    $orderup['qrcode_coupon_money'] = $orderResult['qrcode_coupon_money'];
                    $orderup['coupon_code'] = $orderResult['coupon_code'];
                }
                if(getcustom('restaurant_cashdesk_auth_enter')){
                    $orderup['direct_money'] = $direct_money;
                    $orderup['direct_auth_uid'] = $direct_auth_uid;
                }
                if(getcustom('restaurant_table_timing')){
                    $orderup['timing_money'] = $orderResult['timing_money'];
                }
                if(getcustom('restaurant_table_minprice')){
                    $orderup['service_money'] = $orderResult['service_money'];
                }
                $orderup['scoredk_money'] = $orderResult['scoredk_money'];
                $orderup['scoredkscore'] = $orderResult['scoredkscore'];
                $orderup['leveldk_money'] = $orderResult['leveldk_money'];
                $orderup['tea_fee'] = $orderResult['tea_fee'];
                $orderup['uid'] = $this->uid;
                $orderup['mid'] = $mid;
                $res = Db::name('restaurant_shop_order')->where('id',$order['id'])->update($orderup);
                if($res){
                    //更新收银台表
                    $payorderid =\app\models\Payorder::createorder(aid, $order['bid'], $mid, 'restaurant_shop', $order['id'], $order['ordernum'], '餐饮收银台抖音团购券核销', $orderup['totalprice'], $orderResult['scoredkscore']);
                    Db::name('payorder')->where('id',$payorderid)->update(['paytype'=>'抖音团购券核销-餐饮收银台','paytypeid'=>$orderup['paytypeid'],'paynum'=>$results['verify_id'],'status' =>1,'paytime' => time(),'platform' =>'cashdesk']);
                    
                    $goodslist = Db::name('restaurant_shop_order_goods')->where('orderid', $order['id'])->select()->toArray();
                    //减库存
                    foreach ($goodslist as $k=>$v){
                        $num = $v['num'];
                        Db::name('restaurant_product_guige')->where('aid',aid)->where('id',$v['ggid'])->update(['stock'=>Db::raw("stock-$num"),'sales'=>Db::raw("sales+$num")]);
                        Db::name('restaurant_product')->where('aid',aid)->where('id',$v['proid'])->update(['stock'=>Db::raw("stock-$num"),'sales'=>Db::raw("sales+$num")]);
                    }
                    $this->afterPay($order['id'],$cashier_id);
                    if(getcustom('restaurant_cashdesk_cuxiao')){
                        $this->addCuxiaoGiveProduct($order,$orderResult['cuxiao_give_product']);
                    }
                    return $this->json(1,'核销成功');
                }
            }else{
                return $this->json(0,$res['data']['description']);
            }
        }
    }
    //汇付支付
    protected function huifuPay($cashier_id=0,$tableid=0,$mid=0,$couponrid=0,$userscore=0,$cuxiaolist=[]){
        if(getcustom('pay_huifu')){
            $auth_code = input('param.auth_code');
            if (empty($auth_code)) {
                return $this->json(0, '无效的付款码' );
            }
            
            $cashdesk = Db::name('restaurant_cashdesk')->where('id', $cashier_id)->where('aid', aid)->where('bid', bid)->find();
            if(!$cashdesk){
                return $this->json(0, '系统错误' );
            }
            $order = $this->getWaitOrder($cashier_id,$tableid);
            if (empty($order)) {
                return $this->json(0, '无待结算订单');
            }
            $goodslist = Db::name('restaurant_shop_order_goods')->where('orderid', $order['id'])->select()->toArray();
            if (empty($goodslist)) {
                return $this->json(0, '无待结算菜品');
            }
            
            $orderResult = $this->getOrderPrice($order, $couponrid, $userscore, $mid,$cuxiaolist);
            if ($orderResult['status'] != 1) {
                return $this->json(0, $orderResult['msg']);
            }

            if(getcustom('restaurant_scan_qrcode_coupon')){
                $coupon_code = input('param.coupon_code');
                $is_scan = input('param.is_scan');
                $qrcode_coupon_money = 0;
                if($coupon_code && $is_scan){
                    $qrcode_return= $this->computeQrcodeCoupon($coupon_code,$order,$orderResult['product_price'],$orderResult['totalprice']);
                    if($qrcode_return['status'] ==0){
                        return $this->json(0, $qrcode_return['msg']);
                    }else{
                        $qrcode_coupon_money= $qrcode_return['qrcode_coupon_money'];
                    }
                }
                $orderResult['totalprice'] = $orderResult['totalprice'] - $qrcode_coupon_money;
                $orderResult['qrcode_coupon_money'] = $qrcode_coupon_money;
                $orderResult['coupon_code'] = $coupon_code;
            }
            if(getcustom('restaurant_cashdesk_auth_enter')){
                $direct_money = input('param.direct_money',0);//直接优惠价格
                $direct_auth_uid = input('param.direct_auth_uid');
                $orderResult['totalprice'] = dd_money_format($orderResult['totalprice'] - $direct_money);
            }
          
            $orderup= [];
            $orderup['status'] = 3;
            $orderup['paytime'] = time();
            $orderup['totalprice'] = $orderResult['totalprice'];
            $orderup['product_price'] = $orderResult['product_price'];
            $orderup['moling_money'] = $orderResult['moling_money'];
            if($orderResult['coupon_money']>0){
                $orderup['coupon_money'] = $orderResult['coupon_money'];
                $orderup['coupon_rid'] = $couponrid;
            }
            if(getcustom('restaurant_cashdesk_cuxiao')){
                $orderup['cuxiao_money'] = $orderResult['cuxiao_money'];
                $orderup['cuxiao_ids'] = implode(',',$orderResult['cuxiao_checked']);
            }
            if(getcustom('restaurant_scan_qrcode_coupon')){
                $orderup['qrcode_coupon_money'] = $orderResult['qrcode_coupon_money'];
                $orderup['coupon_code'] = $orderResult['coupon_code'];
            }
            if(getcustom('restaurant_cashdesk_auth_enter')){
                $orderup['direct_money'] = $direct_money;
                $orderup['direct_auth_uid'] = $direct_auth_uid;
            }
            if(getcustom('restaurant_table_timing')){
                $orderup['timing_money'] = $orderResult['timing_money'];
            }
            if(getcustom('restaurant_table_minprice')){
                $orderup['service_money'] = $orderResult['service_money'];
            }
            $orderup['scoredk_money'] = $orderResult['scoredk_money'];
            $orderup['scoredkscore'] = $orderResult['scoredkscore'];
            $orderup['leveldk_money'] = $orderResult['leveldk_money'];
            $orderup['tea_fee'] = $orderResult['tea_fee'];
            $orderup['uid'] = $this->uid;
            $orderup['mid'] = $mid;
            $wxpaymoney = $orderResult['totalprice'];
            if($wxpaymoney > 0){
                $set = Db::name('admin_set')->where('aid',aid)->find();
                $huifu = new \app\customs\Huifu([],aid,bid,$mid,$set['name'].'-当面付',$order['ordernum'],$orderResult['totalprice']);
                $return = $huifu->micropay($auth_code);
                if($return['status'] ==1){
                    $orderup['paytype'] = '餐饮收银台汇付当面付';
                    $orderup['paytypeid'] = 62;
                    $orderup['paynum'] = $return['data']['hf_seq_id'];
                    $orderup['platform'] = 'restaurant_cashdesk';
                }else{
                    return $this->json(0,$return['msg']);
                }
            }else{
                $orderup['paytype'] = '无须支付';
                $orderup['paynum'] = '';
            }
            $res = Db::name('restaurant_shop_order')->where('id',$order['id'])->update($orderup);
            if($res){
                //更新收银台表
                $payorderid =\app\models\Payorder::createorder(aid, $order['bid'], $mid, 'restaurant_shop', $order['id'], $order['ordernum'], '餐饮收银台汇付收款', $orderup['totalprice'], $orderResult['scoredkscore']);
                Db::name('payorder')->where('id',$payorderid)->update(['paytype'=>'汇付收款-餐饮收银台','paytypeid'=>$orderup['paytypeid'],'paynum'=>$orderup['paynum'],'status' =>1,'paytime' => time(),'platform' =>'cashdesk']);
                
                $goodslist = Db::name('restaurant_shop_order_goods')->where('orderid', $order['id'])->select()->toArray();
                //减库存
                foreach ($goodslist as $k=>$v){
                    $num = $v['num'];
                    Db::name('restaurant_product_guige')->where('aid',aid)->where('id',$v['ggid'])->update(['stock'=>Db::raw("stock-$num"),'sales'=>Db::raw("sales+$num")]);
                    Db::name('restaurant_product')->where('aid',aid)->where('id',$v['proid'])->update(['stock'=>Db::raw("stock-$num"),'sales'=>Db::raw("sales+$num")]);
                }
                $this->afterPay($order['id'],$cashier_id);
                if(getcustom('restaurant_cashdesk_cuxiao')){
                    $this->addCuxiaoGiveProduct($order,$orderResult['cuxiao_give_product']);
                }
                return $this->json(1,'支付成功');
            }
            
        }
    }
    
    //挂账
    protected function guazhangPay($cashier_id=0,$tableid=0,$mid=0,$couponrid=0,$userscore=0,$cuxiaolist=[]){
        if(getcustom('restaurant_cashdesk_pay_guazhang')){
            $order = $this->getWaitOrder($cashier_id,$tableid);
            if (empty($order)) {
                return $this->json(0, '无待结算订单');
            }
            $scoredk_money = 0;
            //计算总价
            $goodslist = Db::name('restaurant_shop_order_goods')->where('orderid', $order['id'])->select()->toArray();
            if (empty($goodslist)) {
                return $this->json(0, '无待结算菜品');
            }
            if(empty($mid)){
                return $this->json(0, '请选择会员账号');
            }
            Db::startTrans();
            $member = Db::name('member')->where('id', $mid)->where('aid', aid)->lock(true)->find();
            if(empty($member)){
                Db::rollback();
                return $this->json(0,'会员信息有误');
            }
    
            foreach ($goodslist as $k=>$v){
                if($v['protype']==1){
                    //库存校验
                    $gginfo = Db::name('restaurant_product_guige')->where('aid',aid)->where('id',$v['ggid'])->find();
                    if($gginfo && $gginfo['stock']<$v['num']){
                        return $this->json(0, $v['proname'].'('.$v['ggname'].')'.'库存不足');
                    }
                }
            }
            $orderResult = $this->getOrderPrice($order,$couponrid,$userscore,$mid,$cuxiaolist);
            if($orderResult['status']!=1){
                Db::rollback();
                return $this->json(0, $orderResult['msg']);
            }
            if(getcustom('restaurant_scan_qrcode_coupon')){
                $coupon_code = input('param.coupon_code');
                $is_scan = input('param.is_scan');
                $qrcode_coupon_money = 0;
                if($coupon_code && $is_scan){
                    $qrcode_return= $this->computeQrcodeCoupon($coupon_code,$order,$orderResult['product_price'],$orderResult['totalprice']);
                    if($qrcode_return['status'] ==0){
                        return $this->json(0, $qrcode_return['msg']);
                    }else{
                        $qrcode_coupon_money= $qrcode_return['qrcode_coupon_money'];
                    }
                }
                $orderResult['totalprice'] = $orderResult['totalprice'] - $qrcode_coupon_money;
                $orderResult['qrcode_coupon_money'] = $qrcode_coupon_money;
                $orderResult['coupon_code'] = $coupon_code;
            }
            if(getcustom('restaurant_cashdesk_auth_enter')){
                $direct_money = input('param.direct_money',0);//直接优惠价格
                $direct_auth_uid = input('param.direct_auth_uid');
                $orderResult['totalprice'] = dd_money_format($orderResult['totalprice'] - $direct_money);
            }
            //抹零
            $orderup = [];
            $orderup['totalprice'] = $orderResult['totalprice'];
            $orderup['product_price'] = $orderResult['product_price'];
            $orderup['moling_money'] = $orderResult['moling_money'];
            if($orderResult['coupon_money']>0){
                $orderup['coupon_money'] = $orderResult['coupon_money'];
                $orderup['coupon_rid'] = $couponrid;
            }
            if(getcustom('restaurant_cashdesk_cuxiao')){
                $orderup['cuxiao_money'] = $orderResult['cuxiao_money'];
                $orderup['cuxiao_ids'] = implode(',',$orderResult['cuxiao_checked']);
            }
            if(getcustom('restaurant_scan_qrcode_coupon')){
                $orderup['qrcode_coupon_money'] = $orderResult['qrcode_coupon_money'];
                $orderup['coupon_code'] = $orderResult['coupon_code'];
            }
            if(getcustom('restaurant_cashdesk_auth_enter')){
                $orderup['direct_money'] = $direct_money;
                $orderup['direct_auth_uid'] = $direct_auth_uid;
            }
            if(getcustom('restaurant_table_timing')){
                $orderup['timing_money'] = $orderResult['timing_money'];
            }
            if(getcustom('restaurant_table_minprice')){
                $orderup['service_money'] = $orderResult['service_money'];
            }
            $orderup['scoredk_money'] = $orderResult['scoredk_money'];
            $orderup['scoredkscore'] = $orderResult['scoredkscore'];
            $orderup['leveldk_money'] = $orderResult['leveldk_money'];
            $orderup['tea_fee'] = $orderResult['tea_fee'];
            $orderup['status'] = 3;
            $orderup['paytypeid'] = 38;
            $orderup['paytype'] = t('信用额度').'支付';
            $orderup['paytime'] = time();
            $orderup['mid'] = $mid;
            $orderup['uid'] = $this->uid;
            if($orderup['coupon_money']>0){
                Db::name('coupon_record')->where('id', $couponrid)->update(['status' => 1, 'usetime' => time()]);
            }
            $totalscore = $orderResult['totalscore'];
            Db::name('restaurant_shop_order')->where('id', $order['id'])->update($orderup);
            $payorderid = \app\models\Payorder::createorder(aid, $order['bid'], $mid, 'restaurant_shop', $order['id'], $order['ordernum'], '餐饮收银台'. t('信用额度').'收款', $orderup['totalprice'], $totalscore);
            if($orderup['totalprice'] > 0){
                $open_overdraft_money = $member['open_overdraft_money']??0;
                $limit_money = $member['limit_overdraft_money']??0;
                if($open_overdraft_money == 0 && $limit_money == 0){
                    return $this->json(0,t('信用额度').'不足');
                }
                if($open_overdraft_money == 0 && $limit_money>0 && ($member['overdraft_money']-$orderup['totalprice'] < $limit_money*-1)){
                    return $this->json(0,t('信用额度').'不足');
                }
                //减去会员的额度
                \app\commons\Member::addOverdraftMoney(aid,$mid,-$orderup['totalprice'],'餐饮收银台买单,订单号: '.$order['ordernum']);
            }
            $res = \app\models\Payorder::payorder($payorderid,t('信用额度').'收款-餐饮收银台',$orderup['paytypeid'],$orderup['paytypeid']);
            if($res && $res['status']==1){
                //减库存
                foreach ($goodslist as $k=>$v){
                    $num = $v['num'];
                    Db::name('restaurant_product_guige')->where('aid',aid)->where('id',$v['ggid'])->update(['stock'=>Db::raw("stock-$num"),'sales'=>Db::raw("sales+$num")]);
                    Db::name('restaurant_product')->where('aid',aid)->where('id',$v['proid'])->update(['stock'=>Db::raw("stock-$num"),'sales'=>Db::raw("sales+$num")]);
                }
                Db::commit();
                $this->afterPay($order['id'],$cashier_id);
                if(getcustom('restaurant_cashdesk_cuxiao')){
                    $this->addCuxiaoGiveProduct($order,$orderResult['cuxiao_give_product']);
                }
                return $this->json(1,'付款成功');
            }else{
                Db::rollback();
                return $this->json(0,'付款失败');
            }
        }
    }
   
    //$order
    protected function freeCouponPay($cashier_id=0,$tableid=0,$mid=0,$couponrid=0,$userscore=0,$cuxiaolist=[]){
        if(getcustom('restaurant_cashdesk_free_coupon')){
            //查询自定义支付存不存在
            $order = $this->getWaitOrder($cashier_id,$tableid);
            if (empty($order)) {
                return $this->json(0, '无待结算订单');
            }
            $goodslist = Db::name('restaurant_shop_order_goods')->where('orderid', $order['id'])->select()->toArray();
            if (empty($goodslist)) {
                return $this->json(0, '无待结算菜品');
            }
            $orderResult = $this->getOrderPrice($order,$couponrid,$userscore,$mid,$cuxiaolist);
            if($orderResult['status']!=1){
                return $this->json(0, $orderResult['msg']);
            }
            $orderup = [];

            if(getcustom('restaurant_scan_qrcode_coupon')){
                $coupon_code = input('param.coupon_code');
                $is_scan = input('param.is_scan');
                $qrcode_coupon_money = 0;
                if($coupon_code && $is_scan){
                    $qrcode_return= $this->computeQrcodeCoupon($coupon_code,$order,$orderResult['product_price'],$orderResult['totalprice']);
                    if($qrcode_return['status'] ==0){
                        return $this->json(0, $qrcode_return['msg']);
                    }else{
                        $qrcode_coupon_money= $qrcode_return['qrcode_coupon_money'];
                    }
                }
                $orderResult['totalprice'] = $orderResult['totalprice'] - $qrcode_coupon_money;
                $orderResult['qrcode_coupon_money'] = $qrcode_coupon_money;
                $orderResult['coupon_code'] = $coupon_code;
            }
            if(getcustom('restaurant_cashdesk_auth_enter')){
                $direct_money = input('param.direct_money',0);//直接优惠价格
                $direct_auth_uid = input('param.direct_auth_uid');
                $orderResult['totalprice'] = dd_money_format($orderResult['totalprice'] - $direct_money);
            }
            //抹零

            $orderup['totalprice'] = $orderResult['totalprice'];
            $orderup['product_price'] = $orderResult['product_price'];
            $orderup['moling_money'] = $orderResult['moling_money'];
            if($orderResult['coupon_money']>0){
                $orderup['coupon_money'] = $orderResult['coupon_money'];
                $orderup['coupon_rid'] = $couponrid;
            }
            if(getcustom('restaurant_cashdesk_cuxiao')){
                $orderup['cuxiao_money'] = $orderResult['cuxiao_money'];
                $orderup['cuxiao_ids'] = implode(',',$orderResult['cuxiao_checked']);
            }
            if(getcustom('restaurant_scan_qrcode_coupon')){
                $orderup['qrcode_coupon_money'] = $orderResult['qrcode_coupon_money'];
                $orderup['coupon_code'] = $orderResult['coupon_code'];
            }
            if(getcustom('restaurant_cashdesk_auth_enter')){
                $orderup['direct_money'] = $direct_money;
                $orderup['direct_auth_uid'] = $direct_auth_uid;
            }
            if(getcustom('restaurant_table_timing')){
                $orderup['timing_money'] = $orderResult['timing_money'];
            }
            if(getcustom('restaurant_table_minprice')){
                $orderup['service_money'] = $orderResult['service_money'];
            }
            $orderup['scoredk_money'] = $orderResult['scoredk_money'];
            $orderup['scoredkscore'] = $orderResult['scoredkscore'];
            $orderup['leveldk_money'] = $orderResult['leveldk_money'];
            $orderup['tea_fee'] = $orderResult['tea_fee'];
            $orderup['mid'] = $mid;
            $orderup['paytime'] = time();
            $orderup['paytype'] = '免费券收款';
            $orderup['paytypeid'] = 91;
            $orderup['status'] = 3;
            $orderup['uid'] = $this->uid;
            Db::name('restaurant_shop_order')->where('bid', bid)->where('aid', aid)->where('id', $order['id'])->update($orderup);
            //更新收银台表
            $payorderid =\app\models\Payorder::createorder(aid, $order['bid'], $mid, 'restaurant_shop', $order['id'], $order['ordernum'], '免费券收款', $orderup['totalprice'], $orderResult['scoredkscore']);
            Db::name('payorder')->where('id',$payorderid)->update(['paytype'=>'免费券收款-餐饮收银台','paytypeid'=>91,'paynum'=>0,'status' =>1,'paytime' => time(),'platform' =>'cashdesk']);
            $this->afterPay($order['id'],$cashier_id);
            if(getcustom('restaurant_cashdesk_cuxiao')){
                $this->addCuxiaoGiveProduct($order,$orderResult['cuxiao_give_product']);
            }
            return $this->json(1,'付款成功');
        }
    }
   
    /**
     * 聚合支付  现金的组合支付 和 充值余额 使用
     * 
     */
    public function together_pay($order,$typeid,$auth_code,$paymoney,$payorderid=0){
        if($typeid !=3){
            $auth_code = str_replace('capslock', '', str_replace(' ', '', strtolower($auth_code)));
            $wx_reg = '/^1[0-6][0-9]{16}$/';//微信
            $ali_reg = '/^(?:2[5-9]|30)\d{14,22}$/';;//支付宝
           
            if (!preg_match($wx_reg, $auth_code) && !preg_match($ali_reg, $auth_code)) {
                return $this->json(0, '无效的付款码:' . $auth_code);
            } 
        }
        $set = Db::name('admin_set')->where('aid', aid)->find();
        $transaction_id = 0;
        $paytype = '';
        if ($typeid == 1) {
            if (preg_match($wx_reg, $auth_code)) {//微信
                $paytype = '微信支付';
                $paytypeid = 2;
                $appinfo = Db::name('admin_setapp_restaurant_cashdesk')->where('aid', aid)->where('bid', 0)->find();
                $pars = [];
                if ($appinfo['wxpay_type'] == 0) {
                    $pars['appid'] = $appinfo['appid'];
                    $pars['mch_id'] = $appinfo['wxpay_mchid'];
                    $mchkey = $appinfo['wxpay_mchkey'];
                } else {
                    $dbwxpayset = Db::name('sysset')->where('name', 'wxpayset')->value('value');
                    $dbwxpayset = json_decode($dbwxpayset, true);
                    if (!$dbwxpayset) {
                        return $this->json(0, '未配置服务商微信支付信息');
                    }
                    $pars['appid'] = $dbwxpayset['appid'];
                    //$pars['sub_appid'] = $appid;
                    $pars['mch_id'] = $dbwxpayset['mchid'];
                    $pars['sub_mch_id'] = $appinfo['wxpay_sub_mchid'];
                    $mchkey = $dbwxpayset['mchkey'];
                }
                if (bid > 0) {
                    $bappinfo = Db::name('admin_setapp_restaurant_cashdesk')->where('aid', aid)->where('bid', bid)->find();
                    //1:服务商 2：平台收款 3：独立收款 0：关闭
                    $restaurant_sysset = Db::name('restaurant_admin_set')->where('aid', aid)->find();

                    if (!$restaurant_sysset || $restaurant_sysset['business_cashdesk_wxpay_type'] == 0) {
                        return $this->json(0, '微信收款已禁用');
                    }
                    if ($restaurant_sysset['business_cashdesk_wxpay_type'] == 1) {
                        $dbwxpayset = Db::name('sysset')->where('name', 'wxpayset')->value('value');
                        $dbwxpayset = json_decode($dbwxpayset, true);
                        $pars['appid'] = $dbwxpayset['appid'];
                        $pars['mch_id'] = $dbwxpayset['mchid'];
                        $pars['sub_mch_id'] = $bappinfo['wxpay_sub_mchid'];
                        $mchkey = $dbwxpayset['mchkey'];
                    }
                    if ($restaurant_sysset['business_cashdesk_wxpay_type'] == 3) {
                        if ($bappinfo['wxpay_type'] == 0) {
                            $pars['appid'] = $bappinfo['appid'];
                            $pars['mch_id'] = $bappinfo['wxpay_mchid'];
                            $mchkey = $bappinfo['wxpay_mchkey'];
                        } else {
                            $bset = Db::name('business_sysset')->where('aid', aid)->find();
                            if ($bset['wxfw_status'] == 2) {
                                $dbwxpayset = Db::name('sysset')->where('name', 'wxpayset')->value('value');
                                $dbwxpayset = json_decode($dbwxpayset, true);
                            } else {
                                $dbwxpayset = [
                                    'mchname' => $bset['wxfw_mchname'],
                                    'appid' => $bset['wxfw_appid'],
                                    'mchid' => $bset['wxfw_mchid'],
                                    'mchkey' => $bset['wxfw_mchkey'],
                                    'apiclient_cert' => $bset['wxfw_apiclient_cert'],
                                    'apiclient_key' => $bset['wxfw_apiclient_key'],
                                ];
                            }
                            if (!$dbwxpayset) {
                                return $this->json(0, '未配置服务商微信支付信息');
                            }
                            $pars['appid'] = $dbwxpayset['appid'];
                            //$pars['sub_appid'] = $appid;
                            $pars['mch_id'] = $dbwxpayset['mchid'];
                            $pars['sub_mch_id'] = $bappinfo['wxpay_sub_mchid'];
                            $mchkey = $dbwxpayset['mchkey'];
                        }
                    }
                    if ($restaurant_sysset['business_cashdesk_wxpay_type'] == 2) {
                        if ($appinfo['wxpay_type'] == 0) {
                            $pars['appid'] = $appinfo['appid'];
                            $pars['mch_id'] = $appinfo['wxpay_mchid'];
                            $mchkey = $appinfo['wxpay_mchkey'];
                        } else {
                            $bset = Db::name('business_sysset')->where('aid', aid)->find();
                            if ($bset['wxfw_status'] == 2) {
                                $dbwxpayset = Db::name('sysset')->where('name', 'wxpayset')->value('value');
                                $dbwxpayset = json_decode($dbwxpayset, true);
                            } else {
                                $dbwxpayset = [
                                    'mchname' => $bset['wxfw_mchname'],
                                    'appid' => $bset['wxfw_appid'],
                                    'mchid' => $bset['wxfw_mchid'],
                                    'mchkey' => $bset['wxfw_mchkey'],
                                    'apiclient_cert' => $bset['wxfw_apiclient_cert'],
                                    'apiclient_key' => $bset['wxfw_apiclient_key'],
                                ];
                            }
                            if (!$dbwxpayset) {
                                return $this->json(0, '未配置服务商微信支付信息');
                            }
                            $pars['appid'] = $dbwxpayset['appid'];
                            //$pars['sub_appid'] = $appid;
                            $pars['mch_id'] = $dbwxpayset['mchid'];
                            $pars['sub_mch_id'] = $appinfo['wxpay_sub_mchid'];
                            $mchkey = $dbwxpayset['mchkey'];
                        }
                    }
                }
                $pars['body'] = $set['name'] . '-付款码付款';
                $pars['out_trade_no'] = $order['ordernum'];
                $pars['total_fee'] = $paymoney * 100;
                $pars['spbill_create_ip'] = request()->ip();
                $pars['auth_code'] = $auth_code;
                $pars['nonce_str'] = random(8);
                ksort($pars, SORT_STRING);
               
                $string1 = '';
                foreach ($pars as $key => $v) {
                    if (empty($v)) {
                        continue;
                    }
                    $string1 .= "{$key}={$v}&";
                }
                $string1 .= "key=" . $mchkey;
                $pars['sign'] = strtoupper(md5($string1));
                $dat = array2xml($pars);
                $response = request_post('https://api.mch.weixin.qq.com/pay/micropay', $dat);
                $response = @simplexml_load_string($response, 'SimpleXMLElement', LIBXML_NOCDATA);
                
                if ($response->return_code == 'SUCCESS' && $response->result_code == 'SUCCESS' && $response->trade_type == 'MICROPAY') {
                    $response = json_decode(json_encode($response),true);
                    $transaction_id = $response['transaction_id'];
                }else{
                    $this->refreshOrdernum($order['id']);
                }
            } 
            elseif (preg_match($ali_reg, $auth_code)) {
                $paytype = '支付宝支付';
                $paytypeid = 3;
                $return = Alipay::build_scan(aid, bid, '', $set['name'] . '-当面付', $order['ordernum'], $paymoney, 'restaurant_cashdesk', '', $auth_code, 'restaurant_cashdesk');
                if ($return['status'] == 1) {
                    $transaction_id = $return['data']['trade_no'];
                }
            }
        } elseif ($typeid == 5 ||$typeid == 81) {//随行付
            $paytype = '随行付支付';
            $paytypeid = 81;
            $return = Sxpay::build_scan(aid, bid, $set['name'] . '-当面付', $order['ordernum'], $paymoney, 'restaurant_cashdesk', $auth_code);
           
            if ($return['status'] == 1) {
                $transaction_id = $return['data']['trade_no'];
            }else{
                $transaction_id = $this->sxpayTradequery($payorderid); 
            }
        }elseif($typeid == 3){
            $transaction_id = 1;
            $paytype = '现金支付';
            $paytypeid = 0;
        }
        return ['paytype' =>$paytype,'transaction_id' =>$transaction_id ,'paytypeid'=>$paytypeid];
    
    }
    public function sxpayTradequery($payorderid){
        $transaction_id = '';
        for($i=0;$i<10;$i++){
            $payorder = Db::name('payorder')->where('aid',aid)->where('id',$payorderid)->find();
            $rs = \app\customs\Sxpay::tradeQuery($payorder);
            if($rs['status'] == 1 && $rs['data']['tranSts'] == 'SUCCESS'){
                $transaction_id = $rs['data']['transactionId'];
            }
            if($transaction_id)break;
            sleep(3);
        }
        return  $transaction_id;
    }
    protected function afterPay($orderid=0,$cashier_id=0){
        $order = Db::name('restaurant_shop_order')->where('aid',aid)->where('id',$orderid)->find();
        if(empty($order)){
            return false;
        }
        if($order['coupon_rid']){
            Db::name('coupon_record')->where('id',$order['coupon_rid'])->where('mid',$order['mid'])->update(['status'=>1,'usetime'=>time()]);
        }
        if($order['scoredkscore'] > 0){
            if($order['bid'] == 0) {
                $rs = \app\commons\Member::addscore(aid,$order['mid'],-$order['scoredkscore'],'餐饮收银台订单，订单号: '.$order['ordernum']);
            } else {
                $rs = \app\commons\Business::addmemberscore(aid,$order['bid'],$order['mid'],-$order['scoredkscore'],'餐饮收银台订单,订单号: '.$order['ordernum'],1);
            }
        }
        if(!$order['cashdesk_id']){
            Db::name('restaurant_shop_order')->where('aid',aid)->where('id',$orderid)->update(['cashdesk_id' => $cashier_id]);
        }
        if(getcustom('restaurant_scan_qrcode_coupon')){
            if($order['coupon_code']){
                Db::name('coupon_record')->where('code',$order['coupon_code'])->update(['status'=>1,'usetime'=>time(),'mid' => $order['mid']?$order['mid']:0]);
            }
        }
        $goodslist =Db::name('restaurant_shop_order_goods')->where('protype',1)->where('orderid', $orderid)->select()->toArray();
        //减库存 加销量
        foreach ($goodslist as $k=>$v){
            $num = $v['num'];
            Db::name('restaurant_product_guige')->where('aid',aid)->where('id',$v['ggid'])->update(['stock'=>Db::raw("stock-$num"),'sales'=>Db::raw("sales+$num"),'sales_daily'=>Db::raw("sales_daily+$num")]);
            
            Db::name('restaurant_product')->where('aid',aid)->where('id',$v['proid'])->update(['stock'=>Db::raw("stock-$num"),'sales'=>Db::raw("sales+$num"),'sales_daily'=>Db::raw("sales_daily+$num"),'real_sales'=>Db::raw("real_sales+$num"),'real_sales2'=>Db::raw("real_sales2+$num")]);
            if(getcustom('restaurant_product_package')){
                if($v['is_package']){
                    $packagedata = json_decode($v['package_data'],true);
                    foreach($packagedata as $pk=>$p){
                        $pnum = $p['num'] * $num;
                        Db::name('restaurant_product_guige')->where('aid',aid)->where('id',$p['ggid'])->update(['stock'=>Db::raw("stock-$pnum"),'sales'=>Db::raw("sales+$pnum"),'sales_daily'=>Db::raw("sales_daily+$pnum")]);
                        Db::name('restaurant_product')->where('aid',aid)->where('id',$p['proid'])->update(['stock'=>Db::raw("stock-$pnum"),'sales'=>Db::raw("sales+$pnum"),'sales_daily'=>Db::raw("sales_daily+$pnum"), 'real_sales'=>Db::raw("real_sales+$pnum"), 'real_sales2'=>Db::raw("real_sales2+$pnum")]);
                    }
                }
            }
            //获取适用会员价格
            $sell_price = $v['sell_price'];
            $totalprice = $v['totalprice'];
            if($order['mid'] && $order['is_gj'] ==0){
                $sell_price = $this->getVipPrice($v['proid'],$order['mid'],$v['ggid'],$v['sell_price']);
                $totalprice = dd_money_format($sell_price* $v['num']);
            }
            $real_price =  $this->getOgRealPrice($order,$totalprice);
            Db::name('restaurant_shop_order_goods')->where('id',$v['id'])->update(['real_totalprice' => $real_price,'status' => 1,'sell_price'=> $sell_price,'totalprice' => $totalprice]);
        }
        $sysset = Db::name('admin_set')->where('aid',aid)->find();
        if(getcustom('cashdesk_commission')){
            if($order && $order['mid'] && $sysset['cashdeskfenxiao'] ==1){
                $order_goods = Db::name('restaurant_shop_order_goods')->where('protype',1)->where('orderid',$orderid)->select()->toArray();
                $member =  Db::name('member')->where('id',$order['mid'])->where('aid',$order['aid'])->find();
                foreach ($order_goods as $key=>$val){
                    $product = Db::name('restaurant_product')->where('id',$val['proid'])->where('aid',$order['aid'])->find();
                    $commission_totalprice = $val['totalprice'];
                    
                    if($sysset['fxjiesuantype']==1){ //按成交价格
                        $commission_totalprice = $this->getOgRealPrice($order,$val['totalprice']);
                    }
                    if($sysset['fxjiesuantype']==2){ //按销售利润
                        $real_price = $this->getOgRealPrice($order,$val['totalprice']);
                        $commission_totalprice = dd_money_format($real_price - $val['cost_price'] * $val['num']);
                    }
                    $is_commission = true;
                    if($order['bid'] > 0){
                        $restaurant_sysset = Db::name('restaurant_admin_set')->where('aid',aid)->find();
                        if(getcustom('cashdesk_alipay')){
                            if($order['paytypeid'] ==3 && $restaurant_sysset['business_cashdesk_alipay_type'] ==3){//支付宝
                                $is_commission = false;
                            }
                        }
                        if($order['paytypeid'] ==2 && $restaurant_sysset['business_cashdesk_wxpay_type'] ==3){//微信
                            $is_commission = false;
                        }
                        if(($order['paytypeid'] ==5 || $order['paytypeid'] ==81) && $restaurant_sysset['business_cashdesk_wxpay_type'] ==3){//随行付
                            $is_commission = false;
                        }
                    }
                   
                    if($is_commission){
                        $ss = $this->getcommission($product,$member,$val,$commission_totalprice,$val['num']);
                    }
                }
                //进行分佣
                $record_list = Db::name('member_commission_record')->where('aid',aid)->where('status',0)->where('type','restaurant_shop')->select();
                foreach($record_list as $k=>$v){
                    Order::giveCommission($order,'restaurant_shop');
                }
            }
        }
        //平台收款时商户加佣金
        if($order['bid'] > 0){
            $this->addBusinessMoney($order,$goodslist);
        }
        if($order['mid'] > 0){
            \app\commons\Member::uplv(aid,$order['mid']);
            //消费送积分
            if($sysset['scorein_money']>0 && $sysset['scorein_score']>0){
                if(($order['paytypeid'] == 1 && $sysset['score_from_moneypay'] == 1) || $order['paytypeid'] != 1)
                {
                    $givescore = floor($order['totalprice'] / $sysset['scorein_money']) * $sysset['scorein_score'];
                    $res = \app\commons\Member::addscore(aid,$order['mid'],$givescore,'消费送'.t('积分'));
                    if($res && $res['status'] == 1){
                        //记录消费赠送积分记录
                        \app\commons\Member::scoreinlog(aid,0,$order['mid'],'restaurant_shop',$order['id'],$order['ordernum'],$givescore,$order['totalprice']);
                    }
                }
            }
        }

        if(getcustom('restaurant_table_after_pay_clean')){
            //每个桌台设置自动清理，付款后自动清理
            if($order['tableid']){
                $table = Db::name('restaurant_table')->where('id',$order['tableid'])->where('aid',aid)->where('bid',$order['bid'])->find();
             
                if($table['auto_clean'] ==1){
                    $tupdate = ['status' => 0, 'orderid' => 0];
                    if(getcustom('restaurant_cashdesk_link_table')){
                        $tupdate['link_tableid'] = 0;
                    }
                    Db::name('restaurant_table')->where('id',$order['tableid'])->where('aid',$order['aid'])->where('bid',$order['bid'])->update($tupdate);
                }
            }
            //操作订单为已完成
        }
        if(getcustom('restaurant_take_food')){
            $restaurant_take_food_sysset = Db::name('restaurant_take_food_sysset')->where('aid',aid)->where('bid',$order['bid'])->find();
            $take_table = Db::name('restaurant_table')->where('id',$order['tableid'])->where('aid',aid)->where('bid',$order['bid'])->find();
            if(!$take_table['pindan_status'] && $restaurant_take_food_sysset['status']){
                $shop_set = Db::name('restaurant_shop_sysset')->where('aid',aid)->where('bid',bid)->find();
                $taday_start =  strtotime(date('Y-m-d 00:00:01'));
                $taday_end =  $taday_start + 86399;
                $today_ordernum = Db::name('restaurant_shop_order')
                    ->where('aid',aid)->where('bid',bid)
                    ->where('createtime','between',[$taday_start,$taday_end])
                    ->count();
                $today_ordernum = $shop_set['start_pickup_number'] + $today_ordernum;
                if($today_ordernum < 10 ){
                    $today_ordernum ='00'.$today_ordernum;
                }elseif ($today_ordernum >= 10 && $today_ordernum < 100){
                    $today_ordernum ='0'.$today_ordernum;
                }
                $today_ordernum = $shop_set['take_food_number_prefix'].$today_ordernum;
                Db::name('restaurant_shop_order')->where('aid',aid)->where('id',$orderid)->update(['pickup_number' => $today_ordernum]);
                //增加记录
                $order['pickup_number'] = $today_ordernum;
                \app\customs\Restaurant::addTakeFoodNumber($order);
            }
        }
        if(getcustom('restaurant_table_timing')){
            Db::name('restaurant_shop_order')->where('aid', aid)->where('bid', bid)->where('id', $orderid)->update(['timeing_start' => 0]);
        }
        $order = Db::name('restaurant_shop_order')->where('aid',aid)->where('id',$orderid)->find();
        $tptype = 0;
        if(getcustom('restaurant_bar_table_order')){
            if($order['is_bar_table_order'] ==1){//吧台点餐和先付后吃的桌台 一菜一单
                $tptype = '';
            }
        }
        if(getcustom('restaurant_shop_pindan')){
            $pindan_status = Db::name('restaurant_table')->where('id',$order['tableid'])->value('pindan_status');
            if($pindan_status ==0){
                $tptype = '';
            }
        }
        if(getcustom('yx_buy_fenhong')){
            if($order['paytypeid'] !=1 && $order['mid'] > 0){
                $payorder = Db::name('payorder')->where('aid',aid)->where('id',$order['payorderid'])->find();
                \app\customs\BuyFenhong::getScoreWeight($payorder);
            }
        }
        \app\customs\Restaurant::print('restaurant_shop', $order, [], $tptype);
    }
//------------------------------支付end-----------------------//
//------------------------------取餐 start-----------------------
    //获取取餐号列表
    public function getTakeFoodList(){
        $page = input('param.page/d', 1);
        $limit = input('param.limit/d', 20);
        $status = input('param.status',0);
        $where = [];
        $where[] = ['aid','=',aid];
        $where[] = ['bid','=',bid];
        $where[] = ['status','=',$status];
        $pickup_number = input('param.pickup_number');
        if($pickup_number){
            $where[] = ['pickup_number','like','%'.$pickup_number.'%'];
        }
        if($status ==1){
            //时间筛选  24小时
            $start_time = time()- 86400;
            $where[] = ['create_time','between',[$start_time,time()]]; 
        }
        $list = Db::name('restaurant_take_food')
            ->where($where)
            ->page($page, $limit)
            ->order('id desc,create_time desc')->select()->toArray();
        
        foreach($list as $key=>$val){
            $list[$key]['call_time'] = date('Y-m-d H:i:s',$val['call_time']);
            $diff_time = time() - $val['create_time'];
            $hours = floor($diff_time / 3600);
            $minutes = floor(($diff_time % 3600) / 60);
            $seconds = $diff_time % 60;
            $wait_time = '';
            if($hours){
                $wait_time .=$hours.'时';
            }
            if($minutes){
                $wait_time .=$minutes.'分';
            }
            $wait_time .=$seconds.'秒';
            $list[$key]['wait_time']  = $wait_time;
        }
        return $this->json(1,'成功',$list?$list:[]);
    }
    //取餐操作
    public function outFoodOpera(){
        $url = input('param.url','');
        $url = $url?base64_decode($url):'';
        $params = getPathParams('',$url);
        if(!$params || $params['type'] !='outfood') return $this->json(0, '无效二维码' );
        $data  = Db::name('restaurant_take_food')
            ->where('aid',aid)
            ->where('bid',bid)
            ->where('orderid',$params['id'])
            ->where('pickup_number',$params['co'])
            ->find();
        if(!$data){
            return $this->json(0, '无效二维码' );
        }
        Db::name('restaurant_take_food')
            ->where('id',$data['id'])
            ->update(['status' => 1,'call_time' =>time()]);
        send_socket(['type'=>'restaurant_outfood_call','data'=>['aid'=>aid,'bid' => bid,'id'=>$data['id']]]);
        return $this->json(1, '扫码成功' ); 
    }
    
//------------------------------取餐 end----------------------- 
    protected function addCuxiaoGiveProduct($order,$cuxiao_give_product){
        if (getcustom('restaurant_cashdesk_cuxiao')){
            if($cuxiao_give_product){
                foreach($cuxiao_give_product as $give_product){
                    $product = $give_product['product'];
                    $guige =  $give_product['guige'];
                    $ogdata['aid'] = aid;
                    $ogdata['bid'] = $product['bid'];
                    $ogdata['mid'] = 0;
                    $ogdata['orderid'] = $order['id'];
                    $ogdata['ordernum'] = $order['ordernum'];
                    $ogdata['proid'] = $product['id'];
                    $ogdata['name'] = $product['name'];
                    $ogdata['pic'] = $product['pic'];
                    $ogdata['procode'] = $product['procode'];
                    $ogdata['ggid'] = $guige['id']??0;
                    $ogdata['ggname'] = $guige['name']??'';
                    //$ogdata['cid'] = $product['cid'];
                    $ogdata['num'] = 1;
                    $ogdata['cost_price'] = $guige['cost_price']?$guige['cost_price']:$product['cost_price'];
                    $ogdata['sell_price'] = $guige['sell_price'];
                    $ogdata['totalprice'] = $guige['sell_price'];
                    $ogdata['status'] = 0;
                    $ogdata['protype'] = $product['protype'];
                    $ogdata['createtime'] = time();
                    $ogid = Db::name('restaurant_shop_order_goods')->insertGetId($ogdata);
                }

            }
        }
    }
    public function addBusinessMoney($order=[],$oglist=[]){
        if($order['bid']!=0){//入驻商家的货款
            $aid = aid;
            $totalnum = 0;
            foreach($oglist as $og){
                $totalnum += $og['num'];
            }
            //判断是什么支付，判断是不是平台收款
            $sysset = Db::name('restaurant_admin_set')->where('aid',aid)->find();
            $add_business_money = false;
            if($order['paytypeid'] ==2 && $sysset &&  $sysset['business_cashdesk_wxpay_type'] ==2){//微信支付
                $add_business_money = true;
            }elseif ($order['paytypeid'] ==3 && $sysset && $sysset['business_cashdesk_alipay_type'] ==2){//支付宝
                $add_business_money = true;
            }elseif (($order['paytypeid'] ==5 || $order['paytypeid'] ==81) && $sysset && $sysset['business_cashdesk_sxpay_type'] ==2){//随行付
                $add_business_money = true;
            } elseif ($order['paytypeid'] ==1 && $sysset && $sysset['business_cashdesk_yue'] ==1){//余额
                $add_business_money = true;
            }
            if($add_business_money){
                $totalcommission = 0;
                $og_business_money = false;
                $totalmoney = 0;
                $lirun_cost_price = 0;
                
                foreach($oglist as $og){
                    //if($og['iscommission']) continue;
                    if($og['parent1'] && $og['parent1commission'] > 0){
                        $totalcommission += $og['parent1commission'];
                    }
                    if($og['parent2'] && $og['parent2commission'] > 0){
                        $totalcommission += $og['parent2commission'];
                    }
                    if($og['parent3'] && $og['parent3commission'] > 0){
                        $totalcommission += $og['parent3commission'];
                    }
                    if(!is_null($og['business_total_money'])) {
                        $og_business_money = true;
                        $totalmoney += $og['business_total_money'];
                    }
                    if(getcustom('business_agent')){
                        if(!empty($og['cost_price']) && $og['cost_price']>0){
                                $lirun_cost_price += $og['cost_price'];
                        }
                    }
                }
                $binfo = Db::name('business')->where('aid',$aid)->where('id',$order['bid'])->find();
                $bset = Db::name('business_sysset')->where('aid',$aid)->find();
                if($bset['commission_kouchu'] == 0){ //不扣除佣金
                    $totalcommission = 0;
                }
                $business_lirun = 0;
                if(getcustom('business_agent')){                    
                    $business_lirun = $order['totalprice']-$order['refund_money']-$lirun_cost_price;
                }
                //商品独立费率
                if($og_business_money) {
                    $totalmoney = $totalmoney - $totalcommission - $order['refund_money'];
                } else {
                    $scoredkmoney = $order['scoredk_money'] ?? 0;
                    if($bset['scoredk_kouchu'] == 0){ //扣除积分抵扣
                        $scoredkmoney = 0;
                    }
                    $totalmoney = $order['product_price'] - $order['coupon_money'] - $order['refund_money'] - $totalcommission ;
                    if($bset['scoredk_kouchu']==1){
                        $totalmoney = $totalmoney - $scoredkmoney;
                    }
                    if($totalmoney > 0){
//                        $totalmoney = $totalmoney * (100-$binfo['feepercent']) * 0.01;
                        $platformMoney = $totalmoney * $binfo['feepercent'] * 0.01;
                        $totalmoney = $totalmoney - $platformMoney;
                    }
                    $totalmoney  = $order['tea_fee'] + $totalmoney;
                }
                
                if($totalmoney < 0){
                    $bmoney = Db::name('business')->where('aid',$aid)->where('id',$order['bid'])->value('money');
                    if($bmoney + $totalmoney < 0){
                        return ['status'=>0,'msg'=>'操作失败,商家余额不足'];
                    }
                }
                \app\commons\Business::addmoney($aid,$order['bid'],$totalmoney,'餐饮收银台，订单号：'.$order['ordernum'],true,'restaurant_cashdesk',$order['ordernum'],['platformMoney'=>$platformMoney,'business_lirun'=>$business_lirun]);
            }
            //店铺加销量
            Db::name('business')->where('aid',$aid)->where('id',$order['bid'])->inc('sales',$totalnum)->update();
        }
    }
    public function getcommission($product,$member,$og,$commission_totalprice = 0,$num=0){
        if(getcustom('cashdesk_commission')){
            $ogupdate =  ['parent1' =>0,'parent2' => 0,'parent3' => 0,'parent1commission' => 0,'parent2commission' => 0,'parent3commission' => 0 ];
            
            if(!$product || !$member || $commission_totalprice==0 || $product['commissionset'] ==-1){
                return  $ogupdate;
            }
            if($product['commissionset']!=-1){
                $isfg = 0;
                $istc1 = 0;
                $istc2 = 0;
                $istc3 = 0;
                if($member['pid']){
                    $parent1 = Db::name('member')->where('aid',aid)->where('id',$member['pid'])->find();
                    if($parent1){
                        $agleveldata1 = Db::name('member_level')->where('aid',aid)->where('id',$parent1['levelid'])->find();
                        if($agleveldata1['can_agent']!=0 && (!$agleveldata1['commission_appointlevelid'] || in_array($member['levelid'],explode(',',$agleveldata1['commission_appointlevelid'])))){
                            $ogupdate['parent1'] = $parent1['id'];
                        }
                    }
                }
                if($parent1['pid']){
                    $parent2 = Db::name('member')->where('aid',aid)->where('id',$parent1['pid'])->find();
                    if($parent2){
                        $agleveldata2 = Db::name('member_level')->where('aid',aid)->where('id',$parent2['levelid'])->find();
                        if($agleveldata2['can_agent']>1 && (!$agleveldata2['commission_appointlevelid'] || in_array($member['levelid'],explode(',',$agleveldata2['commission_appointlevelid'])))){
                            $ogupdate['parent2'] = $parent2['id'];
                        }
                    }
                }
                if($parent2['pid']){
                    $parent3 = Db::name('member')->where('aid',aid)->where('id',$parent2['pid'])->find();
                    if($parent3){
                        $agleveldata3 = Db::name('member_level')->where('aid',aid)->where('id',$parent3['levelid'])->find();
                        if($agleveldata3['can_agent']>2 && (!$agleveldata3['commission_appointlevelid'] || in_array($member['levelid'],explode(',',$agleveldata3['commission_appointlevelid'])))){
                            $ogupdate['parent3'] = $parent3['id'];
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
                }elseif($product['commissionset']==5){//提成比例+积分
                    $commissiondata = json_decode($product['commissiondata1'],true);
                    if($commissiondata){
                        if($agleveldata1) $ogupdate['parent1commission'] = $commissiondata[$agleveldata1['id']]['commission1'] * $commission_totalprice * 0.01;
                        if($agleveldata2) $ogupdate['parent2commission'] = $commissiondata[$agleveldata2['id']]['commission2'] * $commission_totalprice * 0.01;
                        if($agleveldata3) $ogupdate['parent3commission'] = $commissiondata[$agleveldata3['id']]['commission3'] * $commission_totalprice * 0.01;
                    }
                    $commissiondata = json_decode($product['commissiondata3'],true);
                    if($commissiondata){
                        if($agleveldata1) $ogupdate['parent1score'] = $commissiondata[$agleveldata1['id']]['commission1'] * $num;
                        if($agleveldata2) $ogupdate['parent2score'] = $commissiondata[$agleveldata2['id']]['commission2'] * $num;
                        if($agleveldata3) $ogupdate['parent3score'] = $commissiondata[$agleveldata3['id']]['commission3'] * $num;
                    }
                }elseif($product['commissionset']==6){//提成金额+积分
                    $commissiondata = json_decode($product['commissiondata2'],true);
                    if($commissiondata){
                        if(getcustom('fengdanjiangli') && $product['fengdanjiangli']){

                        }else{
                            if($agleveldata1) $ogupdate['parent1commission'] = $commissiondata[$agleveldata1['id']]['commission1'] * $num;
                            if($agleveldata2) $ogupdate['parent2commission'] = $commissiondata[$agleveldata2['id']]['commission2'] * $num;
                            if($agleveldata3) $ogupdate['parent3commission'] = $commissiondata[$agleveldata3['id']]['commission3'] * $num;
                        }
                    }
                    $commissiondata = json_decode($product['commissiondata3'],true);
                    if($commissiondata){
                        if($agleveldata1) $ogupdate['parent1score'] = $commissiondata[$agleveldata1['id']]['commission1'] * $num;
                        if($agleveldata2) $ogupdate['parent2score'] = $commissiondata[$agleveldata2['id']]['commission2'] * $num;
                        if($agleveldata3) $ogupdate['parent3score'] = $commissiondata[$agleveldata3['id']]['commission3'] * $num;
                    }
                }else{ //按会员等级设置的分销比例
                    if($agleveldata1){
                        if(getcustom('plug_ttdz') && $isfg == 1){
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
                        if(getcustom('plug_ttdz') && $isfg == 1){
                            $agleveldata2['commission2'] = $agleveldata2['commission5'];
                        }
                        if($agleveldata2['commissiontype']==1){
                            if($istc2==0){
                                $ogupdate['parent2commission'] = $agleveldata2['commission2'];
                                $istc2 = 1;
                                //持续推荐奖励
                                if($agleveldata2['commission_parent'] > 0 && $ogupdate['parent1']) {
                                    $ogupdate['parent2commission'] = $ogupdate['parent2commission'] + $agleveldata2['commission_parent'];
                                }
                            }
                        }else{
                            $ogupdate['parent2commission'] = $agleveldata2['commission2'] * $commission_totalprice * 0.01;
                            //持续推荐奖励
                            if($agleveldata2['commission_parent'] > 0 && $ogupdate['parent1commission'] > 0 && $ogupdate['parent1']) {
                                $ogupdate['parent2commission'] = $ogupdate['parent2commission'] + $ogupdate['parent1commission'] * $agleveldata2['commission_parent'] * 0.01;
                            }
                        }

                    }
                    if($agleveldata3){
                        if(getcustom('plug_ttdz') && $isfg == 1){
                            $agleveldata3['commission3'] = $agleveldata3['commission6'];
                        }

                        if($agleveldata3['commissiontype']==1){
                            if($istc3==0){
                                $ogupdate['parent3commission'] = $agleveldata3['commission3'];
                                $istc3 = 1;
                                //持续推荐奖励
                                if($agleveldata3['commission_parent'] > 0 && $ogupdate['parent2']) {
                                    $ogupdate['parent3commission'] = $ogupdate['parent3commission'] + $agleveldata3['commission_parent'];
                                }
                            }
                        }else{
                            $ogupdate['parent3commission'] = $agleveldata3['commission3'] * $commission_totalprice * 0.01;
                            //持续推荐奖励
                            if($agleveldata3['commission_parent'] > 0 && $ogupdate['parent2commission'] > 0 && $ogupdate['parent2']) {
                                $ogupdate['parent3commission'] = $ogupdate['parent3commission'] + $ogupdate['parent2commission'] * $agleveldata3['commission_parent'] * 0.01;
                            }
                        }
                    }
                }
                if($agleveldata3 && $ogupdate['parent2'] && $ogupdate['parent2commission'] > 0 && $agleveldata2['id'] == $agleveldata3['id']){
                    $agleveldata3['commissionpingjitype'] = $agleveldata3['commissiontype'];
                    if($product['commissionpingjiset'] != 0){
                        if($product['commissionpingjiset'] == 1){
                            $commissionpingjidata1 = json_decode($product['commissionpingjidata1'],true);
                            $agleveldata3['commission_parent_pj'] = $commissionpingjidata1[$agleveldata3['id']]['commission'];
                        }elseif($product['commissionpingjiset'] == 2){
                            $commissionpingjidata2 = json_decode($product['commissionpingjidata2'],true);
                            $agleveldata3['commission_parent_pj'] = $commissionpingjidata2[$agleveldata3['id']]['commission'];
                            $agleveldata3['commissionpingjitype'] = 1;
                        }else{
                            $agleveldata3['commission_parent_pj'] = 0;
                        }
                    }
                    if($agleveldata3['commission_parent_pj'] > 0){
                        if(!$ogupdate['parent3']){
                            $ogupdate['parent3commission'] = 0;
                            $ogupdate['parent3'] = $parent3['id'];
                        }
                        if($agleveldata3['commissionpingjitype'] == 0){
                            $ogupdate['parent3commission'] = $ogupdate['parent3commission'] + $ogupdate['parent2commission'] * $agleveldata3['commission_parent_pj'] * 0.01;
                        }else{
                            $ogupdate['parent3commission'] = $ogupdate['parent3commission'] + $agleveldata3['commission_parent_pj'];
                        }
                    }
                }
                if($agleveldata2 && $ogupdate['parent1'] && $ogupdate['parent1commission'] > 0 && $agleveldata1['id'] == $agleveldata2['id']){
                    $agleveldata2['commissionpingjitype'] = $agleveldata2['commissiontype'];
                    if($product['commissionpingjiset'] != 0){
                        if($product['commissionpingjiset'] == 1){
                            $commissionpingjidata1 = json_decode($product['commissionpingjidata1'],true);
                            $agleveldata2['commission_parent_pj'] = $commissionpingjidata1[$agleveldata2['id']]['commission'];
                        }elseif($product['commissionpingjiset'] == 2){
                            $commissionpingjidata2 = json_decode($product['commissionpingjidata2'],true);
                            $agleveldata2['commission_parent_pj'] = $commissionpingjidata2[$agleveldata2['id']]['commission'];
                            $agleveldata2['commissionpingjitype'] = 1;
                        }else{
                            $agleveldata2['commission_parent_pj'] = 0;
                        }
                    }
                    if($agleveldata2['commission_parent_pj'] > 0){
                        if(!$ogupdate['parent2']){
                            $ogupdate['parent2commission'] = 0;
                            $ogupdate['parent2'] = $parent2['id'];
                        }
                        if($agleveldata2['commissionpingjitype'] == 0){
                            $ogupdate['parent2commission'] = $ogupdate['parent2commission'] + $ogupdate['parent1commission'] * $agleveldata2['commission_parent_pj'] * 0.01;
                        }else{
                            $ogupdate['parent2commission'] = $ogupdate['parent2commission'] + $agleveldata2['commission_parent_pj'];
                        }
                    }
                }
            }
            
            $totalcommission = 0;
            if($ogupdate['parent1'] && ($ogupdate['parent1commission'] || $ogupdate['parent1score'])){
                Db::name('member_commission_record')->insert(['aid'=>aid,'mid'=>$ogupdate['parent1'],'frommid'=>$member['id'],'orderid'=>$og['orderid'],'ogid'=>$og['id'],'type'=>'restaurant_shop','commission'=>$ogupdate['parent1commission'],'score'=>$ogupdate['parent1score'],'remark'=>'下级购买菜品奖励','createtime'=>time()]);
                $totalcommission += $ogupdate['parent1commission'];
            }
            if($ogupdate['parent2'] && ($ogupdate['parent2commission'] || $ogupdate['parent2score'])){
                Db::name('member_commission_record')->insert(['aid'=>aid,'mid'=>$ogupdate['parent2'],'frommid'=>$member['id'],'orderid'=>$og['orderid'],'ogid'=>$og['id'],'type'=>'restaurant_shop','commission'=>$ogupdate['parent2commission'],'score'=>$ogupdate['parent2score'],'remark'=>'下二级购买菜品奖励','createtime'=>time()]);
                $totalcommission += $ogupdate['parent2commission'];
            }
            if($ogupdate['parent3'] && ($ogupdate['parent3commission'] || $ogupdate['parent3score'])){
                Db::name('member_commission_record')->insert(['aid'=>aid,'mid'=>$ogupdate['parent3'],'frommid'=>$member['id'],'orderid'=>$og['orderid'],'ogid'=>$og['id'],'type'=>'restaurant_shop','commission'=>$ogupdate['parent3commission'],'score'=>$ogupdate['parent3score'],'remark'=>'下三级购买菜品奖励','createtime'=>time()]);
                $totalcommission += $ogupdate['parent3commission'];
            }
            //更新order_goods
            Db::name('restaurant_shop_order_goods')->where('id',$og['id'])->update($ogupdate);
            return  $ogupdate;
        }
    }
    protected function refreshOrdernum($orderid=''){
        $newordernum = \app\commons\Common::generateOrderNo(aid,'restaurant_shop_order');
        Db::name('restaurant_shop_order')->where('id',$orderid)->update(['ordernum'=>$newordernum]);
    }

    //抖音核销准备接口
    public function  getHexiaoQrcodePrepare(){
        if(getcustom('restaurant_douyin_qrcode_hexiao')){
            $hxcode = input('param.hxcode','');
            if (empty($hxcode)) {
                return $this->json(0, '无效购券码' );
            }
            //判断是短连接 还是code码
            $pattern = '/http[s]?:\/\/[\w.]+[\w\/]*[\w.]*\??[\w=&\+\%]*/is';
            $encrypterd_data = '';
            $code ='';
            if(preg_match($pattern, $hxcode)) {
                $get_url =  shortUrlToLongUrl($hxcode);
                $qqData = getPathParams($get_url);
                 $encrypterd_data =   $qqData['object_id'];
            }else {
                $code = $hxcode;
            }
            $accessToken = \app\commons\Douyin::getDouyinAccessToken(aid,bid);
            $res = \app\commons\Douyin::hexiaoQrcodePrepare($accessToken, $encrypterd_data, $code);
            if($res['data']['error_code'] ==0){
                return $this->json(1, '',$res['data']['certificates'] ); 
            }else{
                return $this->json(0, $res['data']['description'] );
            }
        }
    }
    //抹零
    protected function removeZero($totalprice,$cashier_id=0){
        if($cashier_id){
            $set = Db::name('restaurant_cashdesk')->where('id', $cashier_id)->where('bid', bid)->find();
        }else{
            $set['remove_zero_length'] = 1;//抹去一位
        }
        $discount_totalmoney = 0;
        $zero_length = $set['remove_zero_length'];

        $totalprice = sprintf("%.2f", $totalprice);

        //小于100的 不支持整数部分抹零
        if ($totalprice < 100) {
            $zero_length = min(2, $zero_length);
        }
        if (strlen($totalprice) - 1 <= $zero_length) {
            $zero_length = 2;
        }
        if ($zero_length > 0 && $zero_length <= 2) {
            $discount = substr($totalprice, 0 - $zero_length);
            $discount_money = round($discount / 100, 2);
        } elseif ($zero_length > 2) {
            $discount_money = substr($totalprice, 0 - ($zero_length + 1));
        }
        $discount_totalmoney = round($discount_totalmoney + $discount_money,2);
        $totalprice = round($totalprice - $discount_totalmoney,2);
        return ['totalprice'=>$totalprice,'moling_money'=>$discount_totalmoney];
    }

    /**
     * 获取餐饮收银台订单信息
     * 待结算status=0
     * 已结算订单status=1
     * 挂单status=2
     */
    public function getCashierOrder()
    {
        $status = input('param.status/d', 1);
//        $bid = input('param.bid/d',0);
        $cashier_id = input('param.cashdesk_id/d', 0);
        $keyword = input('param.keyword', 0);
        $page = input('param.page',1);
        $limit = input('param.limit',30);
        $where = [];
        $where[] = ['o.aid','=',aid];
        if($status ==3){
            //兼容手机端下的单的状态
            $where[] = ['o.status' ,'in', [1,3]];
        }else{
            $where[] = ['o.status' ,'=', $status];
        }
        $where[] = ['o.bid','=',bid];
//        $where[] = ['o.cashdesk_id','=',$cashier_id];
      
        if($keyword){
            if($keyword =='吧台'){
                $where[] = ['o.tableid','=',0];
            }else{
                $where[] = ['g.name|g.procode|rb.name|o.ordernum','like','%'.$keyword.'%'];
            }
        }
        $start_time = input('param.start_time');
        $end_time = input('param.end_time');
        if($start_time && $end_time){
            $start_time = $start_time/1000;
            $end_time = $end_time/1000 +86400;
            $where[] = ['o.createtime','between',[$start_time,$end_time]];
        }
        $orderby = 'id desc';
        $lists = Db::name('restaurant_shop_order')->alias('o')
            ->join('restaurant_shop_order_goods g','o.id=g.orderid')
            ->join('restaurant_table rb','o.tableid = rb.id','left')
            ->group('o.id')->where($where)->field('o.*,rb.name,rb.seat')
            ->order($orderby)->page($page,$limit)->select()->toArray();
        if (empty($lists)) $lists = [];
        foreach ($lists as $k => $order) {
            if($order['uid'] > 0){
                $admin_user_name = Db::name('admin_user')->where('id',$order['uid'])->value('un');
                $lists[$k]['admin_user'] = $admin_user_name??'超级管理员';
            }else{
                //如果收银台大于0
                if($order['cashdesk_id'] >0){
                    $lists[$k]['admin_user'] = '超级管理员';
                } else{
                    $lists[$k]['admin_user'] = '扫码下单';
                }
            }
            $goodslist = Db::name('restaurant_shop_order_goods')->where('orderid', $order['id'])->where('status','in',[1,2,3])->select()->toArray();
            if (empty($goodslist)) $goodslist = [];
            $totalprice = 0;
            foreach ($goodslist as $gk => $goods) {
                $goodslist[$gk]['stock'] = 0;
                if($status==2){
                    $stock = 0;
                    if ($goods['protype'] == 1) {
                        $stock = Db::name('restaurant_product_guige')->where('proid', $goods['proid'])->where('id', $goods['ggid'])->value('stock');
                    }
                    $goods_totalprice = round($goods['sell_price'] * $goods['num'],2);
                    $totalprice = $totalprice+$goods_totalprice;
                    $goodslist[$gk]['stock'] = $stock ?? 0;
                }
                if(getcustom('restaurant_weigh')){
                    $goodslist[$gk]['sell_price'] = $goods['sell_price'].'/斤';
                    $goodslist[$gk]['num'] = floatval($goods['num']);
                    if($goods['product_type'] ==1){
                        $goodslist[$gk]['num'] = floatval($goods['num']).'斤';
                    }
                }
                if(getcustom('restaurant_product_jialiao')){
                    $goodslist[$gk]['sell_price'] = dd_money_format($goods['sell_price'] + $goods['njlprice']);
                    if($goods['njltitle']) {
                        $goodslist[$gk]['ggname'] = $goods['ggname'] . '(' . $goods['njltitle'] . ')';
                    }
                }
                if(getcustom('restaurant_product_package')){
                    if($goods['package_data']){
                        $package_data = json_decode($goods['package_data'],true);
                        $ggtext = [];
                        foreach($package_data as $pdk=>$pd){
                            $t = 'x'.$pd['num'].' '.$pd['proname'];
                            if($pd['ggname'] !='默认规格'){
                                $t .='('.$pd['ggname'].')';
                            }
                            $ggtext[] = $t;
                        }
                        $goodslist[$gk]['ggtext'] =$ggtext;
                    }
                }
            }
            if($status==2){
                $lists[$k]['totalprice']  = $totalprice;
            }
            $lists[$k]['paytime'] = $order['paytime']?date('Y-m-d H:i:s', $order['paytime']):'';
            $lists[$k]['createtime'] = date('Y-m-d H:i:s', $order['createtime']);
            $lists[$k]['status_desc'] = $this->getOrderStatus($order['status']);
            if($order['mid']){
                $member =  Db::name('member')->where('id',$order['mid'])->field('id,nickname,realname')->find();
                $lists[$k]['buyer'] = $member['nickname']??'';
            }else{
                $lists[$k]['buyer'] = '匿名购买';
            }
            $lists[$k]['prolist'] = $goodslist ?? [];
            if(getcustom('restaurant_cashdesk_mix_pay')){
               if($order['mix_paynum']){
                   $lists[$k]['paytype'] = $order['paytype'].'和'.$order['mix_paytype']; 
               } 
            }
        }
        $return = [
            'is_show_refund' => 0,
            'is_show_cancel' => 0
        ];
        if(getcustom('restaurant_cashdesk_auth_enter')){
            $return['is_show_refund'] = 1;
            $return['is_show_cancel'] = 1;
            $auth_path =$this->handleAuthData($this->user);
            if(!in_array('RestaurantCashdesk/refund',$auth_path)  && $auth_path !='all'){
                $return['is_show_refund_check_auth'] = 1;
            }else{
                $return['is_show_refund_check_auth'] = 0;
            }
            if(!in_array('RestaurantCashdesk/cancel',$auth_path)  && $auth_path !='all'){
                $return['is_show_cancel_check_auth'] = 1;
            }else{
                $return['is_show_cancel_check_auth'] = 0;
            }
        }
        return json(['status'=>1,'msg'=>'ok','data' => $lists,'return' =>$return]);
    }

    public function getOrderDetail(){
        $orderid = input('param.orderid/d');
        $order = Db::name('restaurant_shop_order')->where('aid',aid)->where('bid',bid)->where('id', $orderid)->find();
        if(empty($order)){
            return $this->json(0,'该订单不存在');
        }
        $order['createtime'] = date("Y-m-d H:i:s",$order['createtime']);
        $order['paytime'] = $order['paytime']?date("Y-m-d H:i:s",$order['paytime']):'';
        //获取桌台
        $tablename = '';
        if($order['tableid']){
            $tablename = Db::name('restaurant_table')->where('id',$order['tableid'])->value('name');
        }
        $order['tablename'] = $tablename;
        $ordergoods = Db::name('restaurant_shop_order_goods')->where('orderid',$orderid)->select()->toArray();
        $order['prolist'] = $ordergoods??[];
        if($order['mid']){
            $member =  Db::name('member')->where('id',$order['mid'])->where('id,nickname,realname,')->find();
            $order['buyer'] = $member['nickname']??'';
        }else{
            $order['buyer'] = '匿名购买';
        }
        return $this->json(1, 'ok',$order);
    }
    
    /**
     * 修改订单状态
     */
    public function cashierChangeStatus()
    {
        $orderid = input('param.orderid');
        Db::name('restaurant_shop_order')->where('bid', bid)->where('aid', aid)->where('id', $orderid)->update(['status' => 1]);
        return $this->json(1, '修改成功');
    }
    public function change_renshu(){
        if(getcustom('restaurant_change_renshu')){
            $renshu = input('param.renshu',0);
            $tableid = input('param.tableid');
            $cashier_id = input('param.cashdesk_id/d', 0);
            if($renshu <= 0){
                return $this->json(0, '请输入就餐人数');
            }
            $order = $this->getWaitOrder($cashier_id,$tableid);
            
            if (empty($order)) {
                return $this->json(0, '无待结算订单');
            }
            $shop_set = Db::name('restaurant_shop_sysset')->where('aid',aid)->where('bid',bid)->find();
            if($shop_set['tea_fee_status']==1){
                $tea_fee = $shop_set['tea_fee']>0 ? $shop_set['tea_fee'] * $renshu['renshu'] : 0;
            }
            Db::name('restaurant_shop_order')->where('id',$order['id'])->update(['renshu' => $renshu,'tea_fee' => $tea_fee]);
            return $this->json(1, '修改成功');
        }
    }
//------------------------------会员操作 start-----------------------//  
    public function registerMember(){
        $realname = input('param.realname','');
        $sex = input('param.sex',3);
        $tel = input('param.tel');
        $birthday = input('param.birthday');
        if (!checkTel($tel)) {
            return json(['status'=>0,'msg'=>'请检查手机号格式']);
        }
        $member = Db::name('member')->where('aid',aid)->where('tel',$tel)->find();
        if($member){
            return json(['status'=>0,'msg'=>'该手机号已注册']);
        }
        $data = [];
        $data['aid'] = aid;
        $data['tel'] = $tel;
        $data['sex'] = $sex;
        $data['realname'] = $realname;
        $data['birthday'] = $birthday;
        $data['nickname'] = $realname==''? substr($tel,0,3).'****'.substr($tel,-4):$realname;
        $data['headimg'] = PRE_URL.'/static/img/touxiang.png';
        if(getcustom('restaurant_cashdesk_member_paypwd')){
            if($this->user['bid'] ==0 && ($this->auth_data =='all' || in_array('Member/index',$this->auth_data) || in_array('Member/edit',$this->auth_data))){
                $paypwd = input('param.paypwd','');
                if(!$paypwd){
                    $cashier = Db::name('restaurant_cashdesk')->where('aid',aid)->where('bid',bid)->find();
                    $paypwd = $cashier['default_paypwd']?$cashier['default_paypwd']:'123456';
                }
                $data['paypwd'] = md5($paypwd);
            }
           
        }
        $data['createtime'] = time();
        $data['last_visittime'] = time();
        $data['platform'] = 'cashdesk';
        $mid = \app\models\Member::add(aid,$data);
        \app\commons\Common::registerGive(aid,array_merge($data, ['id' => $mid]));
        $binddata = '';
        if(getcustom('restaurant_cashdesk_member_paypwd')){
            $binddata = m_url('restaurant/cashdesk/bind?id='.$mid);
        }
        return json(['status'=>1,'msg'=>'会员注册成功','data' => $binddata]);
    }
    //修改会员支付密码
    public function editMemberPaypwd(){
        if(getcustom('restaurant_cashdesk_member_paypwd')){
            if($this->user['bid'] > 0  || ($this->auth_data !='all' &&  !in_array('Member/index',$this->auth_data) && !in_array('Member/edit',$this->auth_data))){
                return json(['status'=>0,'msg'=>'无权限操作']);      
            }
            $mid = input('param.mid');
            $member = Db::name('member')->where('aid',aid)->where('id',$mid)->find();
            if(!$member){
                return json(['status'=>0,'msg'=>'会员不存在']);
            }
            $paypwd = input('param.paypwd','');
            $enter_paypwd = input('param.enter_paypwd','');
            if(!$paypwd){
                return json(['status'=>0,'msg'=>'请输入支付密码']);
            }
            if(md5($paypwd.$member['paypwd_rand']) != md5($enter_paypwd.$member['paypwd_rand'])){
                return json(['status'=>0,'msg'=>'两次密码不一致']);
            }
            $res = Db::name('member')->where('aid',aid)->where('id',$mid)->update(['paypwd' => md5($paypwd.$member['paypwd_rand'])]);
            if($res !==false){
                return json(['status'=>1,'msg'=>'修改成功']);
            }else{
                return json(['status'=>0,'msg'=>'修改失败']);
            }
        }
    }
    /**
     * @description 获取用户信息
     */
    public function searchMember()
    {
        $keyword = input('param.keyword');
        if (empty($keyword)) {
            return $this->json(0, '请输入会员ID、手机号或微信会员卡号');
        }
        $field =  'id,nickname,headimg,realname,money,score,tel,createtime,birthday,remark,levelid';
        if(getcustom('member_overdraft_money')){
            $field .=',overdraft_money,limit_overdraft_money,open_overdraft_money';
        }
        $member = Db::name('member')->where('aid',aid)->where('id|tel|card_code', $keyword)->field($field)->find();
        if (empty($member)) {
            return $this->json(0, '未查到会员信息');
        }
        $fwtype_array = [0, 1, 2];
        $fwscene_array = [0];
        if(getcustom('coupon_maidan_cashdesk')){
            $fwscene_array[] = 2;
        }
        $member['couponcount'] = Db::name('coupon_record')->alias('cr')
            ->join('coupon c','c.id = cr.couponid')
            ->where('cr.aid', aid)->where('cr.bid',bid)->where('cr.mid', $member['id'])
            ->where('cr.status', 0)
            ->where('cr.type','in',[5,51])
            ->where('c.fwtype','in',$fwtype_array)
            ->where('c.fwscene','in',$fwscene_array)
            ->where('cr.starttime', '<=', time())->where('cr.endtime', '>', time())->count();
        
        $mlevel = Db::name('member_level')->where('aid', aid)->where('id', $member['levelid'])->field('id,name,discount,icon')->find();
        $member['level_name'] = '';
        $member['level_icon'] = '';
        $member['tel'] = $member['tel'] ?? '';
        $member['birthday'] = $member['birthday'] ?? '';
        $member['realname'] = $member['realname'] ?? '';
        $member['level_discount'] = 0;
        $member['createtime'] = date('Y-m-d H:i:s', $member['createtime']);
        $address = Db::name('member_address')->where('mid', $member['id'])->field('id,name,tel,province,city,district,area,address')->order('isdefault desc')->find();
        $member['address'] = '';
        if ($address) {
//            $member['address'] = ($address['province'] ?? '') . ($address['city'] ?? '') . ($address['district'] ?? '') . ($address['area'] ?? '') . ($address['address'] ?? '');
            $member['address'] = $address['area']. $address['address'];
        }
        if ($mlevel) {
            $member['level_name'] = $mlevel['name']??'';
            $member['level_icon'] = $mlevel['icon']??'';
            $member['level_discount'] = $mlevel['discount'];
        }
        return $this->json(1, 'ok', $member);
    }
    //会员优惠券
    public function memberCouponList()
    {
        $page = input('param.page/d', 1);
        $limit = input('param.limit/d', 10);
        $mid = input('param.mid/d', 0);
        $where = [];
        $where[] = ['aid', '=', aid];
        $where[] = ['bid', '=', bid];
        $where[] = ['mid', '=', $mid];
        $where[] = ['status', '=', 0];
        $where[] = ['type', 'in', [5,51]];
        $datalist = Db::name('coupon_record')->field('id,bid,type,limit_count,used_count,couponid,couponname,money,minprice,from_unixtime(starttime,"%Y-%m-%d %H:%i") starttime,from_unixtime(endtime,"%Y-%m-%d %H:%i") endtime,from_unixtime(usetime) usetime,from_unixtime(createtime) createtime,status,discount')
            ->where($where)
            ->where('starttime', '<=', time())->where('endtime', '>', time())
            ->order('id desc')->page($page, $limit)->select()->toArray();
        if (!$datalist) $datalist = [];
        $newdatalist = [];
        foreach ($datalist as $k => $v) {
            if ($v['bid'] > 0) {
                $binfo = Db::name('business')->where('aid', aid)->where('id', $v['bid'])->find();
                $datalist[$k]['bname'] = $binfo['name'];
            }
            $c_field = 'isgive,fwtype';
            if(getcustom('coupon_maidan_cashdesk')){
                $c_field = $c_field.',fwscene';
            }
            $coupon = Db::name('coupon')->where('id', $v['couponid'])->field($c_field)->find();
            $datalist[$k]['isgive'] = $coupon['isgive'];
            $datalist[$k]['fwtype'] = $coupon['fwtype'];
            $datalist[$k]['fwscene'] = $coupon['fwscene'];
            $tip = $this->getCouponTip($v);
            $datalist[$k]['tip'] = $tip;
            $fwtype_array = [0, 1, 2];
            //适用场景
            $fwscene_array = [0];
            if(getcustom('coupon_maidan_cashdesk')){
                $fwscene_array[] = 2;
            }
            
            if(in_array($coupon['fwtype'],$fwtype_array) && in_array($coupon['fwscene'],$fwscene_array)){
                $newdatalist[] =  $datalist[$k];
            }
            
        }
        return $this->json(1, 'ok', $newdatalist);
    }
   
    //会员充值
    public function memberRecharge(){
        $recharge_order_wifiprint = getcustom('recharge_order_wifiprint');
        $moneylog_detail = getcustom('moneylog_detail');
        if(getcustom('restaurant_cashdesk_member_recharge')){
            $money = input('param.money',0);
            if($money <=0)  return $this->json(0,'请输入充值金额');
            $mid =input('param.mid',0);
            $member = Db::name('member')->where('aid',aid)->where('id',$mid)->find();
            if(!$member) return $this->json(0,'不存在该会员');
            $orderdata = [
                'aid' =>aid,
                'money'=>$money,
                'mid'=>$mid,
                'ordernum' => date('ymdHis').aid.rand(1000,9999),
                'createtime' => time(),
                'platform' => 'cashdesk'
            ];
            $auth_code = input('param.auth_code');
            $paytypeid =input('param.paytype');
            $payorderid = \app\models\Payorder::createorder(aid, 0, $mid, 'recharge', 0, $orderdata['ordernum'], '餐饮收银台充值', $money, 0);
            $pay_return = $this->together_pay($orderdata,$paytypeid,$auth_code,$money,$payorderid);
            if($pay_return['transaction_id']){
                //更改payorder状态
                Db::name('payorder')->where('id',$payorderid)->update(['paytype'=>$pay_return['paytype'].'-餐饮收银台','paytypeid'=>$pay_return['paytypeid'],'paynum'=>$pay_return['paynum'],'status' =>1,'paytime' => time(),'platform' =>'cashdesk']);
                $orderdata['paytypeid'] =$pay_return['paytypeid'];
                $orderdata['paytype'] = $pay_return['paytype'];
                $orderdata['paynum'] = $pay_return['transaction_id'];
                $orderdata['paytime'] = time();
                $orderdata['status'] = 1;
                $orderdata['payorderid'] = $payorderid;
                if($recharge_order_wifiprint){
                    $orderdata['uid'] = $this->uid;
                }
                $orderid =Db::name('recharge_order')->insertGetId($orderdata);
                 //加余额
                $params = [];
                if($moneylog_detail){
                    $params['type'] = 'recharge';
                    $params['ordernum'] = $orderdata['ordernum'];
                }
                $rs = \app\commons\Member::addmoney(aid,$mid,$money,'餐饮收银台充值,订单号: '.$orderdata['ordernum'],0,'',$orderid,$params);
                //更新payorder表
                Db::name('payorder')->where('id',$payorderid)->update(['orderid' => $orderid]);
                //充值赠送
                $giveset = Db::name('recharge_giveset')->where('aid',aid)->find();
                if($giveset && $giveset['status']==1){
                    $givedata = json_decode($giveset['givedata'],true);
                }else{
                    $givedata = array();
                }
                $givemoney = 0;
                $givescore = 0;
                $moneyduan = 0;
                if($givedata){
                    foreach($givedata as $give){
                        if($orderdata['money']*1 >= $give['money']*1 && $give['money']*1 > $moneyduan){
                            $moneyduan = $give['money']*1;
                            $givemoney = $give['give']*1;
                            $givescore = $give['give_score']*1;
                        }
                    }
                }
                if($givemoney > 0){
                    \app\commons\Member::addmoney(aid,$mid,$givemoney,'充值赠送');
                    if(getcustom('member_recharge_detail_refund')){
                        Db::name('recharge_order')->where('id',$orderid)->update(['give_money' => $givemoney]);
                    }
                }
                if($givescore > 0){
                    \app\commons\Member::addscore(aid,$mid,$givescore,'充值赠送');
                }
                //支付后送券
                $couponlist = \app\commons\Coupon::getpaygive(aid,$mid,'recharge',$orderdata['money']);
                if($couponlist){
                    foreach($couponlist as $coupon){
                        \app\commons\Coupon::send(aid,$mid,$coupon['id']);
                    }
                }
                if($recharge_order_wifiprint){
                    $rs = \app\commons\Wifiprint::print(aid,'recharge',$orderid,0);
                }
                return $this->json(1,'充值成功');
            }else{
                return $this->json(0,'充值失败');
            }
        }
    }
    //信用额度还款
    public function overdraftMoneyRecharge(){
        if(getcustom('member_overdraft_money')){
            if($this->user['isadmin']==0 && !in_array('OverdraftMoney/recharge',$this->auth_data)){
                return json(['status'=>0,'msg'=>'无还款权限']);
            }
            $money = input('param.money',0);
            $mid = input('param.mid/d',0);
            $member = Db::name('member')->where('aid',aid)->where('id',$mid)->find();
            if(!$member) return $this->json(0,'不存在该'.t('会员'));
            if($money <=0)return json(['status'=>0,'msg'=>'还款金额为0']);
            //支付
            $auth_code = input('param.auth_code');
            $paytypeid =input('param.paytype');
            $orderdata=[
                'ordernum' => \app\commons\Common::generateOrderNo(aid),
                'orderid' => 0
            ];
            $pay_return = $this->together_pay($orderdata,$paytypeid,$auth_code,$money);
            if($member['open_overdraft_money'] == 0 && $member['limit_overdraft_money'] < $money){
                return json(['status'=>0,'msg'=>'还款金额超出信用额度']);
            }
            if($pay_return['transaction_id']){
                $remark = '餐饮收银台还款';
                \app\commons\Member::addOverdraftMoney(aid,$mid,$money,$remark);
                return json(['status'=>1,'msg'=>'还款成功']);
            }else{
                return $this->json(0,'还款失败');
            }
        }
    }
//------------------------------会员操作 end-----------------------//  
    //获取别名列表
    public function getBieming(){
        $tea_text = Db::name('restaurant_shop_sysset')->where('aid',aid)->where('bid',bid)->value('tea_fee_text');
        $data = [
            'coupon' => t('优惠券'),
            'score' => t('积分'),
            'yue' => t('余额'),
            'tea_fee' =>  $tea_text?$tea_text:'茶位费'
        ] ;
        if(getcustom('restaurant_table_timing')){
           $timing_fee_text= Db::name('restaurant_shop_sysset')->where('aid',aid)->where('bid',bid)->value('timing_fee_text');
            $data['timing_fee_text'] = $timing_fee_text;
        }
        return  json(['status' => 1,'data' =>$data]);
    }
    //退款
    public function orderRefund(){
        if(getcustom('restaurant_cashdesk_auth_enter')){
            $orderid = input('orderid');
            $remark = input('param.remark','');
            $order = Db::name('restaurant_shop_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->find();
            $remark = $remark?'订单号: '.$order['ordernum'].','.$remark:'订单号: '.$order['ordernum'];
            if(empty($order) || $order['status'] ==0 || $order['status'] ==4){
                return json(['status'=>0,'msg'=>'订单信息有误']);
            }
            $refund_auth_uid = input('param.auth_uid',0);
            if(!$refund_auth_uid){
                //判断权限
                $auth_path = $this->handleAuthData($this->user);
                if(!in_array('RestaurantCashdesk/refund',$auth_path)  && $auth_path !='all'){
                    return json(['status'=>0,'msg'=>'请确认退款权限']);
                }
            }
            $refund_money = input('param.refund_money',0);
            if($refund_money <=0){
                return json(['status'=>0,'msg'=>'退款金额有误']);
            }

            //剩余退款金额
            $sy_totalprice = dd_money_format($order['totalprice'] - $order['refund_money']);
            if($sy_totalprice <=0){
                return json(['status'=>0,'msg'=>'剩余退款金额'.$sy_totalprice.'元']);
            }
            if($sy_totalprice < $refund_money){
                return json(['status'=>0,'msg'=>'剩余退款金额'.$sy_totalprice.'元']);
            }
            //手机端只退随行付 和余额支付 2
            if(!in_array($order['paytypeid'],[1,2,5])  && $order['cashdesk_id'] == 0){
                return json(['status'=>0,'msg'=>'扫码下单退款请在后台或手机端进行退款']);
            }
            if($order['paytypeid']==5 || $order['paytypeid']==81){//5冲突，新的使用81
                $rs = \app\customs\Sxpay::refund($order['aid'],'restaurant_cashdesk',$order['ordernum'],$order['totalprice'],$refund_money,$remark,$order['bid']);
                //更改payorder 
                $payorder = Db::name('payorder')->where('aid',$order['aid'])->where('ordernum',$order['ordernum'])->find();
                Db::name('payorder')->where('aid',$order['aid'])->where('id',$payorder['id'])->update(['refund_money' => $payorder['refund_money'] + $refund_money,'refund_time' => time()]);
            } elseif($order['paytypeid']==0){
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
                if(getcustom('restaurant_douyin_qrcode_hexiao')){
                    $rs = ['status'=>1,'msg'=>''];
                    //更新payorder表退款信息
                    $payorder = Db::name('payorder')->where('aid',$order['aid'])->where('ordernum',$order['ordernum'])->find();
                    Db::name('payorder')->where('aid',$order['aid'])->where('id',$payorder['id'])->update(['refund_money' => $payorder['refund_money'] + $refund_money,'refund_time' => time()]);
                }
            }else{
                $refund_type = 1;
                if(getcustom('restaurant_cashdesk_custom_pay')){
                    $custom_paytypeid = $order['paytypeid'] - $order['child_paytypeid'];
                    if($custom_paytypeid ==10000){
                        //自定义退款
                        $rs = ['status'=>1,'msg'=>''];
                        $refund_type = 0;
                        //更新payorder表
                        $payorder = Db::name('payorder')->where('aid',$order['aid'])->where('ordernum',$order['ordernum'])->find();
                        Db::name('payorder')->where('aid',$order['aid'])->where('id',$payorder['id'])->update(['refund_money' => $payorder['refund_money'] + $refund_money,'refund_time' => time()]);
                    }
                }

                if($refund_type){
                    $rs = \app\commons\Order::refund($order,$refund_money,$remark);
                }
            }
            $total_refund = dd_money_format($order['refund_money'] + $refund_money);
            if($rs && $rs['status']==1){
                $orderup = [
                    'refund_money'=>$total_refund ,
                    'refund_reason'=>$remark,
                    'refund_status'=>2,
                    'refund_time'=>time(),
                    'refund_auth_uid' => $refund_auth_uid
                ];
                //如果订单金额为0 直接关闭
                if($total_refund == $order['totalprice']){
                    $orderup['status'] = 4;
                }
                Db::name('restaurant_shop_order')->where('id',$orderid)->update($orderup);
                
                //收银台选中退某几个
                $ogwhere = [];
                $ogids = input('param.ogids');//restaurant_shop_order_goods 的id集合，“,”字符串或数组
                if($ogids){
                    $ogwhere[] = ['id','in',$ogids];
                }
                $oglist = Db::name('restaurant_shop_order_goods')->where('orderid',$order['id'])->where('aid',$order['aid'])->where($ogwhere)->select()->toArray();
                if($oglist){
                    foreach($oglist as $og){
                        $num = $og['num'];
                        Db::name('restaurant_product')->where('aid',$order['aid'])->where('id',$og['proid'])->update(['real_sales2'=>Db::raw("real_sales2-$num")]);
                        
                        Db::name('restaurant_shop_order_goods')->where('id',$og['id'])->update(['status' =>4 ]);
                    }
                }
                //积分抵扣的返还
                if($order['scoredkscore'] > 0){
                    \app\commons\Member::addscore(aid,$order['mid'],$order['scoredkscore'],'订单退款返还');
                }
                //扣除消费赠送积分
                \app\commons\Member::decscorein(aid,'restaurant_shop',$order['id'],$order['ordernum'],'订单退款扣除消费赠送');
                //优惠券抵扣的返还
                if($order['coupon_rid'] > 0){
                    Db::name('coupon_record')->where('aid',aid)->where(['mid'=>$order['mid'],'id'=>$order['coupon_rid']])->update(['status'=>0,'usetime'=>'']);
                }
                //是否打印
                $order_refund_print = Db::name('restaurant_cashdesk')->where('id',$order['cashdesk_id'])->value('order_refund_pint');  
                $is_print= input('param.is_print'); //前端传来的打印 优先级更高                  
                if($is_print !='') $order_refund_print = $is_print;
                if($order_refund_print ){
                    $order['ogids'] = $ogids;
                    $order['print_type'] = 2; //打印类型0 默认 1：预结单  2：退款或取消
                    $order['refund_money'] = $refund_money;
                    \app\customs\Restaurant::print('restaurant_shop', $order, $oglist, '');
                }
                return json(['status'=>1,'msg'=>'退款成功']);
            }else{
                return json(['status'=>0,'msg'=>$rs['msg']??'退款失败']);
            }
        }
    }
    //取消订单
    public function orderCancel(){
        if(getcustom('restaurant_cashdesk_auth_enter')){
            $orderid = input('orderid');
            $remark = input('param.remark','');
            $order = Db::name('restaurant_shop_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->find();
            $remark = $remark?'订单号: '.$order['ordernum'].','.$remark:'订单号: '.$order['ordernum'];
            if(empty($order) || $order['status'] ==0 || $order['status'] ==4){
                return json(['status'=>0,'msg'=>'订单信息有误']);
            }
            $cancel_auth_uid = input('param.auth_uid',0);
            if(!$cancel_auth_uid){
                //判断权限
                $auth_path = $this->handleAuthData($this->user);
                if(!in_array('RestaurantCashdesk/cancel',$auth_path)  && $auth_path !='all'){
                    return json(['status'=>0,'msg'=>'请确认取消权限']);
                }
            }
            //挂单 撤单
            $orderup = [];
            if($order['status'] ==-1){
                $orderup=['status' => 4];
            } else{
                $refund_money = dd_money_format($order['totalprice'] - $order['refund_money']);
                $refund_money = $refund_money <=0?0:$refund_money;
                $rs = ['status' => 1];
                if($refund_money >0){
                    //手机端只退随行付 和余额支付 2
                    if(!in_array($order['paytypeid'],[1,2,5,81])  && $order['cashdesk_id'] == 0){
                        return json(['status'=>0,'msg'=>'扫码下单退款请在后台或手机端进行退款']);
                    }
                    if($order['paytypeid']==5 ||$order['paytypeid']==81){
                        $rs = \app\customs\Sxpay::refund($order['aid'],'restaurant_cashdesk',$order['ordernum'],$order['totalprice'],$refund_money,$remark,$order['bid']);
                        //更改payorder 
                        $payorder = Db::name('payorder')->where('aid',$order['aid'])->where('ordernum',$order['ordernum'])->find();
                        Db::name('payorder')->where('aid',$order['aid'])->where('id',$payorder['id'])->update(['refund_money' => $payorder['refund_money'] + $refund_money,'refund_time' => time()]);
                    } elseif($order['paytypeid']==0){
                        $rs = ['status' => 1];
                        if($order['mix_paynum']){
                            $mix_refund_money  = $order['mix_money'];
                            $order['paytypeid'] =$order['mix_paytypeid'];
                            $rs = \app\commons\Order::refund($order,$mix_refund_money,$remark);
                            $refund_money =  $refund_money -   $mix_refund_money;
                        }
                        //更新payorder表退款信息
                        $payorder = Db::name('payorder')->where('aid',$order['aid'])->where('ordernum',$order['ordernum'])->find();
                        Db::name('payorder')->where('aid',$order['aid'])->where('id',$payorder['id'])->update(['refund_money' => $payorder['refund_money'] + $refund_money,'refund_time' => time()]);
                    }elseif ($order['paytypeid'] ==121){//抖音核销
                        if(getcustom('restaurant_douyin_qrcode_hexiao')){
                            $rs = ['status'=>1,'msg'=>''];
                            //更新payorder表退款信息
                            $payorder = Db::name('payorder')->where('aid',$order['aid'])->where('ordernum',$order['ordernum'])->find();
                            Db::name('payorder')->where('aid',$order['aid'])->where('id',$payorder['id'])->update(['refund_money' => $payorder['refund_money'] + $refund_money,'refund_time' => time()]);
                        }
                    }else{
                        $refund_type = 1;
                        if(getcustom('restaurant_cashdesk_custom_pay')){
                            $custom_paytypeid = $order['paytypeid'] - $order['child_paytypeid'];
                            if($custom_paytypeid ==10000){
                                //自定义退款
                                $rs = ['status'=>1,'msg'=>''];
                                $refund_type = 0;
                                //更新payorder表
                                $payorder = Db::name('payorder')->where('aid',$order['aid'])->where('ordernum',$order['ordernum'])->find();
                                Db::name('payorder')->where('aid',$order['aid'])->where('id',$payorder['id'])->update(['refund_money' => $payorder['refund_money'] + $refund_money,'refund_time' => time()]);
                            }
                        }
                        if($refund_type){
                            $rs = \app\commons\Order::refund($order,$refund_money,$remark);
                        }
                    }
                }
                if($rs && $rs['status']==1){
                    $total_refund = dd_money_format($order['refund_money'] + $refund_money);
                    $orderup = [
                        'refund_money'=>$total_refund ,
                        'refund_reason'=>$remark,
                        'refund_status'=>2,
                        'refund_time'=>time(),
                        'status'=>4,//退款
                        'cancel_auth_uid' => $cancel_auth_uid
                    ];
                }
            }
            Db::name('restaurant_shop_order')->where('id',$orderid)->update($orderup);

            $oglist = Db::name('restaurant_shop_order_goods')->where('orderid',$order['id'])->where('aid',$order['aid'])->select()->toArray();
            if($oglist){
                foreach($oglist as $og){
                    Db::name('restaurant_product_guige')->where('aid',$order['aid'])->where('id',$og['ggid'])->update(['stock'=>Db::raw("stock+".$og['num']),'sales'=>Db::raw("sales-".$og['num'])]);
                    Db::name('restaurant_product')->where('aid',$order['aid'])->where('id',$og['proid'])->update(['stock'=>Db::raw("stock+".$og['num']),'sales'=>Db::raw("sales-".$og['num'])]);
                }
            }
            //是否打印
            $order_cancel_print = Db::name('restaurant_cashdesk')->where('id',$order['cashdesk_id'])->value('order_cancel_pint');
            $is_print= input('param.is_print'); //前端传来的打印 优先级更高                  
            if($is_print !='') $order_cancel_print = $is_print;
            if($order_cancel_print){
                $order['print_type'] = 2; //打印类型0 默认 1：预结单  2：退款或取消
                //退款金额
                $order['refund_money'] = $refund_money;
                \app\customs\Restaurant::print('restaurant_shop', $order, [], '');
            }
            return json(['status'=>1,'msg'=>'取消成功']);
        }
    }
    //打印小票
    public function print(){
        $orderid = input('post.orderid/d');
        $order = Db::name('restaurant_shop_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->find();
        $tptype = 0;
        $print_type = input('param.print_type',0);//打印类型 0默认 1：预结单
        $order['print_type'] =  $print_type;
        $rs = \app\customs\Restaurant::print('restaurant_shop', $order, [], $tptype);//0普通打印，1一菜一单
        return json($rs);
    }
    //------------------------------店长权限-----------------------//  
    //检查店长身份和对应功能权限
    public function checkUserAuth(){
        if(getcustom('restaurant_cashdesk_auth_enter')){
            $type = input('param.type','');
            $username = input('param.username');
            $password = input('param.password');
            $user = Db::name('admin_user')->where('aid',aid)->where('bid',bid)->where('un',$username)->where('pwd',md5($password))->find();
            if(!$user){
                return json(['status'=>0,'msg'=>'不存在该管理员']);
            }
            $auth_path = $this->handleAuthData($user);
            if($type=='refund'){
                if(!in_array('RestaurantCashdesk/refund',$auth_path) && $auth_path !='all'){
                    return json(['status'=>0,'msg'=>'该管理员无权限']);
                }
            }elseif($type=='cancel'){
                if(!in_array('RestaurantCashdesk/cancel',$auth_path)  && $auth_path !='all'){
                    return json(['status'=>0,'msg'=>'该管理员无权限']);
                }
            }elseif ($type=='discount'){
                if(!in_array('RestaurantCashdesk/discount',$auth_path)  && $auth_path !='all'){
                    return json(['status'=>0,'msg'=>'该管理员无权限']);
                }
            }else{
                return json(['status'=>0,'msg'=>'该管理员无权限']);
            }
            return json(['status'=>1,'msg'=>'成功','user_id' =>$user['id']]);
        }
    }
    //获取处理后的管理的权限
    public function handleAuthData($user){
        if($user['auth_type']==0){
            if($user['groupid']){
                $user['auth_data'] = Db::name('admin_user_group')->where('id',$user['groupid'])->value('auth_data');
            }
            $auth_data = json_decode($user['auth_data'],true);
            $auth_path = [];
            foreach($auth_data as $v) {
                $auth_path = array_merge($auth_path,explode(',',$v));
            }
        }else{
            $auth_path ='all';
        }
        return    $auth_path;
    }
    //获取验证店长权限二维码  5分钟过期
    public function  getCheckUserAuthQrcode(){
        if(getcustom('restaurant_cashdesk_auth_enter')) {
            $check_code = input('param.check_code');
            if(!$check_code){
                $type = input('param.type','');
                $code = random(16);
                $qrcode = createqrcode(m_url('admin/hexiao/hexiao?type=verifyauth&co=' . $code));
                $insert = [
                    'aid' => aid,
                    'code' => $code,
                    'qrcode' => $qrcode,
                    'type' => $type,
                    'uid' =>$this->uid,
                    'createtime' => time(),
                    'expiretime' => time() + 300
                ];
                Db::name('restaurant_verify_auth_log')->insert($insert);
                return json(['status'=>1,'msg'=>'成功','data' =>['qrcode'=>$qrcode,'code' => $code]]);
            }else{
                $operate = input('param.operate');
                if($operate =='delete'){
                    Db::name('restaurant_verify_auth_log')->where('aid',aid)->where('code',$check_code)->delete();
                    return json(['status'=>1,'msg'=>'成功']);
                }else{
                    $log = Db::name('restaurant_verify_auth_log')->where('aid',aid)->where('code',$check_code)->find();
                    return json(['status'=>1,'msg'=>'成功','data' =>$log]);
                }
            }
            
       } 
    }
    //------------------------------店长权限end-----------------------//    
    /**
     * 获取会员价格 
     */
    public function getVipPrice($proid=0,$mid=0,$ggid=0,$sell_price=0){
       
        $product =  Db::name('restaurant_product')->where('id',$proid)->find();
        $member = Db::name('member')->where('id',$mid)->find();
        $ggdata = Db::name('restaurant_product_guige')->where('product_id', $proid)->where('id', $ggid)->find();
        if($product['lvprice']==1){
            $lvprice_data = json_decode($ggdata['lvprice_data'],true);
            if($lvprice_data && isset($lvprice_data[$member['levelid']])){
                $sell_price = $lvprice_data[$member['levelid']];
            }
        }
        return $sell_price;
    }
    public function getOrderStatus($status){
        $arr =[0=>'待付款',1=>'已支付',2=>'挂单',3=>'已结算',4=>'已关闭'];
        return $arr[$status]??$status;
    }
    //获取扫码券的信息,餐饮券，免费券，扫码券 通用
    public function getQrcodeCoupon(){
        $codedata = input('param.codedata','');
        if(!$codedata){
            return json(['status'=>0,'msg'=>'请扫描'. t('优惠券') .'二维码']);
        }
        if(substr($codedata, 0, 3) !='co='){ //手动输入的 拼接上co=
            $codedata = 'co='. $codedata;
        }
        $codedata = str_replace('/','&',$codedata);
        $scandata = explode('&',$codedata);
        $param = [];
        foreach($scandata as $scdata){
            $p= explode('=',$scdata);
            $param[$p[0]] = $p[1];
        }
        $coupon_code =  $param['co'];
        $is_scan =$param['isscan']??'';
        if(!$coupon_code){
            return json(['status'=>0,'msg'=>'请扫描'. t('优惠券') .'二维码']);
        }
        $where = [];
        $where[] = ['aid','=',aid];
        $where[] = ['bid','=',bid];
        $where[] = ['code','=',$coupon_code];
        if($is_scan){
            $where[] = ['is_scan','=',1];
        }
        $couponrecord = Db::name('coupon_record')->where($where)->find();
        if(!$couponrecord){
            return json(['status'=>0,'msg'=>'不存在该'. t('优惠券') ]);
        }
        $couponrecord['createtime'] = date('Y-m-d H:i:s',$couponrecord['createtime']);
        $couponrecord['endtime'] = date('Y-m-d H:i:s',$couponrecord['endtime']);
        $couponrecord['starttime'] = date('Y-m-d H:i:s',$couponrecord['starttime']);
        //返回会员信息
        $memberinfo =[];
        if($couponrecord['mid'] > 0){
            $field =  'id,nickname,headimg,realname,money,score,tel,createtime,birthday,remark,levelid';
            if(getcustom('member_overdraft_money')){
                $field .=',overdraft_money,limit_overdraft_money,open_overdraft_money';
            }
            $memberinfo = Db::name('member')->where('aid',aid)->where('id',$couponrecord['mid'])->field($field)->find();
        }
        $couponrecord['memberinfo']= $memberinfo;
        
        return json(['status'=>1,'msg'=>'成功','data' =>$couponrecord]);
    }
   //计时暂停开始
    public function timingPause(){
        if(getcustom('restaurant_table_timing')) {
            $type = input('param.type');//1开始 0暂停
            $tableid = input('param.tableid',0);
            $table = Db::name('restaurant_table')->where('aid', aid)->where('bid', bid)->where('id', $tableid)->find();
            if (!$table) {
                return json(['status' => 0, 'msg' => '桌台不存在']);
            }
            if (!$table['timing_fee_type']) {
                return json(['status' => 0, 'msg' => '该桌台未开启计时']);
            }
            $order = Db::name('restaurant_shop_order')->where('aid', aid)->where('bid', bid)->where('id', $table['orderid'])->where('tableid', $tableid)->find();
            if (!$order) {
                return json(['status' => 0, 'msg' => '订单不存在']);
            }
            if (!$order) {
                return json(['status' => 0, 'msg' => '订单不存在']);
            }
            $insert = [
                'aid' => aid,
                'bid' => bid,
                'tableid' => $tableid,
                'orderid' =>  $table['orderid'],
            ];

            if ($type == 0) {//暂停
                if (!$order['timeing_start']) {
                    return json(['status' => 0, 'msg' => '请先开始计时']);
                }
                $strttime = strtotime(date('Y-m-d H:i', $order['timeing_start']));
                $endtime = strtotime(date('Y-m-d H:i',time()));
               
                $num = intval(( $endtime - $strttime) / 60, 0);
                if ($num > 0) {
                    $insert['starttime'] =  $strttime;
                    $insert['endtime'] = $endtime;
                    $insert['start_time'] = date('H:i',$strttime);
                    $insert['end_time'] = date('H:i',$endtime);
                    $insert['num'] = $num;
                    $insert['createtime'] = time();
                    Db::name('restaurant_timing_log')->insertGetId($insert);
                    //修改订单的开始时间为空
                    Db::name('restaurant_shop_order')->where('aid', aid)->where('bid', bid)->where('id',  $table['orderid'])->update(['timeing_start' => 0]);
                    
                    return json(['status' => 1, 'msg' => '暂停成功']);
                } else{
                    return json(['status' => 0, 'msg' => '不足一分钟，不能进行暂停']);
                }
            } else {//开始
                if ($order['timeing_start']) {
                    return json(['status' => 0, 'msg' => '计时中']);
                }
                Db::name('restaurant_shop_order')->where('aid', aid)->where('bid', bid)->where('id',  $table['orderid'])->update(['timeing_start' => time()]);
                return json(['status' => 1, 'msg' => '恢复成功']);
            }
           
        }
    }
    
    public function getTimingFee($orderid,$tableid){
        if(getcustom('restaurant_table_timing')) {
            $table = Db::name('restaurant_table')->where('aid', aid)->where('bid', bid)->where('id', $tableid)->find();
            $order = Db::name('restaurant_shop_order')->where('id', $orderid)->find();
            $fee = 0;
            $strttime = strtotime(date('Y-m-d H:i', $order['timeing_start']));
            $endtime = strtotime(date('Y-m-d H:i',time()));
            if ($table['timing_fee_type'] == 1) {//阶梯计时
                if(!$table['timing_data1'])return $fee;
                $timingdata = json_decode($table['timing_data1'], true);
                //计算出全部时段分钟数
                $totalnum = Db::name('restaurant_timing_log')->where('aid', aid)->where('bid', bid)->where('orderid', $orderid)->where('tableid', $tableid)->sum('num');
                if ($order['timeing_start']) {//未结束的 开始时间-当前时间
                   
                    $nownum = intval(($endtime - $strttime) / 60, 0);
                    $totalnum = $totalnum + $nownum;
                }
                $price = 0;
                foreach ($timingdata as $key => $val) {
                    if ($totalnum > $val['end']) {
                        $price += $val['money'] * ceil( ($val['end'] - $val['start'])/$val['minute']);
                    }
                    if($totalnum < $val['end'] && $totalnum > $val['start']){
                        $price += ceil(($totalnum - $val['start'])/$val['minute']) * $val['money'];
                    }
                }
                $fee = dd_money_format($price);
            } elseif ($table['timing_fee_type'] == 2) {//时段计费
                if (!$table['timing_data2'])return $fee;
                $timingdata = json_decode($table['timing_data2'], true);
                //切割成1分钟
                $timelog = Db::name('restaurant_timing_log')->where('aid', aid)->where('bid', bid)->where('orderid', $orderid)->where('tableid', $tableid)->select()->toArray();
                //如果当前订单的计时处于开始中
                if ($order['timeing_start']) {
                    $timelog[] = [
                        'starttime' => $strttime,
                        'endtime' => $endtime,
                        'start_time' => date('H:i', $strttime),
                        'end_time' => date('H:i', $endtime),
                    ];
                }
                $all_log = [];
                $i = 0;
                foreach ($timelog as $key => $log) {
                    $nowtime = $log['starttime'];
                    while ($nowtime < $log['endtime'] && ($log['endtime'] - $nowtime) >= 60) {
                        $all_log[$i]['id'] = $log['id'];
                        $all_log[$i]['start_time'] = date('H:i', $nowtime);
                        $end_time = $nowtime + 60;
                        $all_log[$i]['end_time'] = date('H:i', $end_time);
                        $nowtime = $end_time;
                        $i++;
                    }
                }
               
                //$m_arr = [];
                foreach ($all_log as $key => $logdata) {
                    foreach ($timingdata as $tk=>$set) {
                        if ($set['start'] <= $logdata['start_time'] && $logdata['start_time'] < $set['end'] && $logdata['end_time'] > $set['start'] && $logdata['end_time'] < $set['end']) {
                            //$m_arr[] = $logdata;
                            if(!$timingdata[$tk]['num']){
                                $timingdata[$tk]['num'] =  1;
                            }else{
                                $timingdata[$tk]['num'] =  $timingdata[$tk]['num'] +1;
                            }
                            continue;
                        }
                    }
                }
                $money = 0;
                
                foreach($timingdata as $timek=>$timev){
                    if($timev['num']){
                        $money +=ceil($timev['num']/$timev['minute']) *$timev['money'];
                    }
                }
                $fee =  dd_money_format($money);
            }
            return $fee;
        }
    }
    //格式化返回
    protected function json($status = 0, $msg = '', $data = '')
    {
        return json(['status' => $status, 'msg' => $msg, 'data' => $data]);
    }
    public function getCouponTip($record=[]){
        if(empty($record)){
              return  '';
        }
        switch ($record['type']){
            case 1:
            case 5:
                if($record['minprice'] > 0){
                    $tip = '满'.$record['minprice'].'元减'.$record['money'].'元';
                }else{
                    $tip = '无门槛';
                }
                break;
            case 10:
                if($record['discount'] >0){
                    $discout = $record['discount']/10;
                    $tip = $discout.'折';
                }else{
                    $tip = '0折';
                }
                break;
            case 2:
                $tip='礼品券';
                break;
            case 3:
                $tip = $record['limit_count'].'次';
                break;
            case 4:
                $tip = '运费抵扣券';
                break;
            default:
                $tip = $record['title'];
                break;
        }
        return $tip;
    }
    // 获取备注预置
    public function getRemarkList(){
       $list =  Db::name('restaurant_remark')->where('aid',aid)->where('bid',bid)->order('sort desc,id desc')->select()->toArray();
        return json(['status' => 1, 'msg' => '', 'data' => $list?$list:[]]);
    }

    public  function getJwexinPackage(){
        $appinfo = \app\commons\System::appinfo(aid,'mp');
        $jsapiTicket = \app\commons\Wechat::jsapi_ticket(aid);
        $appid = $appinfo['appid'];
        if(!$jsapiTicket) return [
            "appId"     => '',
            "nonceStr"  => '',
            "timestamp" => time(),
            "url"       => '',
            "signature" => '',
            "rawString" => '',
        ];
        // 注意 URL 一定要动态获取，不能 hardcode.
        //$url = PRE_URL.'/h5/'.$aid.'.html';
        $url = \think\facade\Request::header('referer');
        $timestamp = time();
        $nonceStr = random(6);
        // 这里参数的顺序要按照 key 值 ASCII 码升序排序
        $string = "jsapi_ticket=$jsapiTicket&noncestr=$nonceStr&timestamp=$timestamp&url=$url";
        $signature = sha1($string);
        $signPackage = [
            "appId"     => $appid,
            "nonceStr"  => $nonceStr,
            "timestamp" => $timestamp,
            "url"       => $url,
            "signature" => $signature,
            "rawString" => $string
        ];
        return json(['status' => 1, 'msg' => '', 'data' => $signPackage]);
    }
}
