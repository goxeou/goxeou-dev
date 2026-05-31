<?php


// +----------------------------------------------------------------------
// | 收银台
// +----------------------------------------------------------------------
namespace app\controllers;

use app\commons\Alipay;
use app\commons\Order;
use app\customs\Sxpay;
use think\facade\View;
use think\facade\Db;
use think\Log;

class Cashier extends Common
{
    public function initialize()
    {
        parent::initialize();
    }

    public function index()
    {
        $domain = request()->domain();
        $c_where = [];
        $c_where[] = ['aid','=',aid];
        $c_where[] = ['bid','=',bid];
        if(getcustom('cashier_business_multi_account')){
            $c_where[] = ['uid','=',uid];
        }
        $cashier = Db::name('cashier')->where($c_where)->find();
        if (empty($cashier)) {
            $insert =   ['createtime' => time(), 'bid' => bid, 'aid' => aid, 'name' => '收银台'];
            if(getcustom('cashier_business_multi_account')){
                $insert['uid']= uid;
            }
            Db::name('cashier')->insert($insert);
            $cashier = Db::name('cashier')->where($c_where)->find();
        }
        $domain = PRE_URL;
        $cashier_url = $domain . '/cashier/index.html#/index/index?id=' . $cashier['id'];
        //入口文件作为参数传递
        $mode = trim(str_replace('.php','',$_SERVER['PHP_SELF']),'/');
        if($mode!='index'){
            $cashier_url.='&_mode='.$mode;
        }
        $bsysset = Db::name('business_sysset')->where('aid',aid)->find();
        $bwxtitle = '微信收款';
        if(getcustom('cashdesk_alipay')){
            if($bsysset['business_cashdesk_wxpay_type']>0 && $bsysset['business_cashdesk_alipay_type']>0){
                $bwxtitle = '微信或支付宝收款';
            }elseif ($bsysset['business_cashdesk_wxpay_type'] ==0 && $bsysset['business_cashdesk_alipay_type']>0){
                $bwxtitle = '支付宝收款';
            }elseif ($bsysset['business_cashdesk_wxpay_type'] >0 && $bsysset['business_cashdesk_alipay_type'] == 0){
                $bwxtitle = '微信收款';
            }
        }
        View::assign('bwxtitle',$bwxtitle);
        $wxtitle = '微信';
        if(getcustom('cashdesk_alipay')){
            $wxtitle = '微信或支付宝';
        }
        View::assign('wxtitle',$wxtitle);
        $login_url =  $domain.'/?s=/CashierLogin/index';
        $pinfo = Db::name('admin_setapp_cashdesk')->where('aid',aid)->where('bid',bid)->find();
        View::assign('sysset',$bsysset);
        View::assign('pinfo',$pinfo);
        View::assign('info',$cashier);
        View::assign('cashier_url', $cashier_url);
        View::assign('login_url', $login_url);
        if (getcustom('cashdesk_member_recharge')){
            $isadmin = Db::name('admin_user')->where('id',$this->uid)->value('isadmin');
            View::assign('isadmin', $isadmin);
        }
        //打印机
        $printArr = Db::name('wifiprint_set')->where('aid',aid)->where('bid',bid)->order('id')->where('machine_type',0)->column('name','id');
        View::assign('printArr',$printArr);
        if(getcustom('member_overdraft_money')){
            $overdraft_moneypay = Db::name('admin_set')->where('aid',aid)->value('overdraft_moneypay');
            View::assign('overdraft_moneypay', $overdraft_moneypay);
        }
        return View::fetch();
    }
    public function save(){
        $info = input('post.info/a');
        $info['wxpay'] = !$info['wxpay']?0:$info['wxpay'];
        if(getcustom('cashdesk_sxpay')){
            $info['sxpay'] = !$info['sxpay']?0:$info['sxpay'];
        }
        if(getcustom('pay_huifu')){
            $info['huifupay'] = !$info['huifupay']?0:$info['huifupay'];
        }
        if(getcustom('member_overdraft_money')){
            $info['guazhangpay'] = !$info['guazhangpay']?0:$info['guazhangpay'];
        }
        $info['cashpay'] = !$info['cashpay']?0:$info['cashpay'];
        $info['moneypay'] = !$info['moneypay']?0:$info['moneypay'];
        $info['jiaoban_print_ids'] = implode(',',$info['jiaoban_print_ids']);
        if($info['id']){
            $info['updatetime'] = time();
            Db::name('cashier')->where('aid',aid)->where('id',$info['id'])->update($info);
            \app\commons\System::plog('编辑收银台'.$info['id']);
        }else{
            $info['aid'] = aid;
            $info['createtime'] = time();
            $id = Db::name('cashier')->insertGetId($info);
            \app\commons\System::plog('添加收银台'.$id);
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
            $appinfo = Db::name('admin_setapp_cashdesk')->where('aid',aid)->where('bid',bid)->find();
            if($appinfo){
                Db::name('admin_setapp_cashdesk')->where('aid',aid)->where('bid',bid)->update($pinfo);
            }else{
                $pinfo['aid'] =aid;
                $pinfo['bid'] =bid;
                Db::name('admin_setapp_cashdesk')->insert($pinfo);
            }  
        }
       
        return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
    }


    public function cashier()
    {
        echo 'cashier html......';
//        return View::fetch();
    }
    //------------------------------收银台页面接口-----------------------//
     public function getIco(){
        
         $info = Db::name('sysset')->where('name','webinfo')->find();
         $webinfo = json_decode($info['value'],true);
         return $this->json(1, 'ok', ['ico' =>$webinfo['ico']?$webinfo['ico']:'/favicon.ico'] );
     }
    /**
     * @description 商品一级分类
     */
    public function getCategoryList()
    {
        if (bid > 0) {
            $list = Db::name('shop_category2')->where('aid', aid)->where('bid', bid)->where('pid', 0)->where('status', 1)->order('sort desc,id')->select()->toArray();
            if(!empty($list)){
                foreach($list as $k=>$v){
                    $rs = Db::name('shop_category2')->where('aid',aid)->where('pid',$v['id'])->where('status',1)->order('sort desc,id')->select()->toArray();
                    if(!$rs) $rs = [];
                    $list[$k]['child'] = $rs;
                }
            }
        } else {
            $list = Db::name('shop_category')->where('aid', aid)->where('pid', 0)->where('status', 1)->order('sort desc,id')->select()->toArray();
            if(!empty($list)){
                foreach($list as $k=>$v){
                    $rs = Db::name('shop_category')->where('aid',aid)->where('pid',$v['id'])->where('status',1)->order('sort desc,id')->select()->toArray();
                    if(!$rs) $rs = [];
                    $list[$k]['child'] = $rs;
                }
            }
        }
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
            $pids1 = Db::name('shop_category2')->where('aid', aid)->where('bid', bid)->where('pid', 'in', $pid)->where('status', 1)->order('sort desc,id')->column('id');
            if ($pids1) {
                $pids2 = Db::name('shop_category2')->where('aid', aid)->where('bid', bid)->where('pid', 'in', $pids1)->where('status', 1)->order('sort desc,id')->column('id');
                if ($pids2) {
                    $pids3 = Db::name('shop_category2')->where('aid', aid)->where('bid', bid)->where('pid', 'in', $pids2)->where('status', 1)->order('sort desc,id')->column('id');
                }
            }
        } else {
            $pids1 = Db::name('shop_category')->where('aid', aid)->where('pid', 'in', $pid)->where('status', 1)->order('sort desc,id')->column('id');
            if ($pids1) {
                $pids2 = Db::name('shop_category')->where('aid', aid)->where('pid', 'in', $pids1)->where('status', 1)->order('sort desc,id')->column('id');
                if ($pids2) {
                    $pids3 = Db::name('shop_category')->where('aid', aid)->where('pid', 'in', $pids2)->where('status', 1)->order('sort desc,id')->column('id');
                }
            }
        }
        $cids = array_merge($pid, $pids1, $pids2, $pids3);
        return $cids;
    }

    //收银台配置项返回
    public function getCashierInfo(){
        $cashier_id = input('param.cashier_id/d', 0);
        $info = Db::name('cashier')->where('aid',aid)->where('id',$cashier_id)->find();
        if(empty($info)){
            return $this->json(0,'收银台信息缺失');
        }
        if(empty($info['option_name'])){
            $info['option_name'] = $this->user['un'];
        }
        $webinfo = Db::name('sysset')->where(['name'=>'webinfo'])->value('value');
        if($info['bid']>0){
            $binfo = Db::name('business')->where('aid',aid)->where('id',$info['bid'])->field('logo,name,score2money')->find();
            if(getcustom('cashdesk_member_recharge')){
                $info['member_recharge_status'] = 0;
            }
        }else{
            $binfo = Db::name('admin_set')->where('aid',aid)->field('logo,name,score2money')->find();
        }
        $webinfo = $webinfo?json_decode($webinfo,true):[];
        $info['ico'] = $webinfo['ico']??'';
        $info['score2money'] = $binfo['score2money']??'0';
        $info['bname'] = $binfo['name']??'';
        $info['blogo'] = $binfo['logo']??'';
        $info['color1'] = $info['color1'] ? $info['color1'] : '#2792FF';
        $info['color1rgb'] = $info['color1'] ? hex2rgb($info['color1']) : hex2rgb('#2792FF');
        //信用额度
        $is_overdraft_money  =0;
        if(getcustom('member_overdraft_money')){
            $overdraft_moneypay = Db::name('admin_set')->where('aid',aid)->value('overdraft_moneypay');
            if(bid ==0 && $overdraft_moneypay && ($this->auth_data == 'all' || in_array('OverdraftMoney/recharge',$this->auth_data))) $is_overdraft_money =1;
        }
        $info['is_overdraft_money'] = $is_overdraft_money;
        //会员支付密码
        $is_paypwd = 0;
        if(getcustom('cashier_member_paypwd')){
            if($info['bid'] ==0 && ($this->auth_data =='all' || in_array('Member/index',$this->auth_data) || in_array('Member/edit',$this->auth_data) || $is_overdraft_money)){
                $is_paypwd = 1;
            }
        }
        $info['is_paypwd'] = $is_paypwd;
        if(getcustom('extend_staff')){
            $info['staffs'] = Db::name('staff')->where('aid',aid)->where('bid',bid)->where('status',1)->field('id,realname,tel')->order('sort desc,id desc')->select()->toArray();
        }
        return  $this->json(1,'ok',$info);
    }

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
        $where[] = ['p.status', '=', 1];//已上架的
        $where[] = ['p.ischecked', '=', 1];//已通过审核
        // $where[] = ['p.sell_price', '>', 0];//测试用
        $where[] = ['p.douyin_product_id', '=', ''];
        if (input('param.name')) $where[] = ['p.name|p.procode|p.barcode|g.barcode', 'like', '%' . input('param.name') . '%'];

//        if (input('param.code')) $where[] = ['procode|barcode', 'like', '%' . input('param.code') . '%'];

        if ($cid) {
            $sx_cid = 'p.cid';
            if(bid > 0){
                $sx_cid = 'p.cid2';
            }
            //获取一级分类下面的所有子类
            $cids = $this->getAllCids($cid);
            if ($cids) {
                    $whereCid = [];
                    foreach($cids as $k => $c2){
                        $whereCid[] = "find_in_set({$c2},{$sx_cid})";
                    }
                    $where[] = Db::raw(implode(' or ',$whereCid));
            } else {
                //无分类内容
                $where[] = Db::raw("find_in_set(".$cid.",{$sx_cid})");
            }
        }

        // ===== 直营店引用模式处理 =====
        $isDirectStore = false;
        $storeHeadBid = 0;
        if(bid > 0){
            $storeBiz = Db::name('business')->where('id', bid)->find();
            if($storeBiz && $storeBiz['type'] == 1){
                $isDirectStore = true;
                $storeHeadBid = intval($storeBiz['head_bid']);
            }
        }

        if($isDirectStore && $storeHeadBid > 0){
            // 直营店：移除 bid 筛选条件
            $whereNoBid = [];
            foreach($where as $w){
                if(is_array($w) && isset($w[0]) && $w[0] == 'p.bid') continue;
                $whereNoBid[] = $w;
            }

            // 获取映射的总店商品ID
            $storeProids = Db::name('shop_product_store')->where('bid', bid)->column('proid');
            if(!$storeProids) $storeProids = [0];

            // 1) 总店映射商品
            $whereStore = $whereNoBid;
            $whereStore[] = ['p.bid', '=', $storeHeadBid];
            $whereStore[] = ['p.id', 'in', $storeProids];
            $data1 = Db::name('shop_product')->alias('p')
                ->join('shop_guige g', 'p.id=g.proid')
                ->group('p.id')
                ->field('p.*')
                ->where($whereStore)
                ->page($page, $limit)
                ->order('sort desc,id desc')
                ->select()->toArray();

            // 2) 直营店自营商品
            $whereSelf = $whereNoBid;
            $whereSelf[] = ['p.bid', '=', bid];
            $whereSelf[] = ['p.sync_from_bid', '=', null];
            $data2 = Db::name('shop_product')->alias('p')
                ->join('shop_guige g', 'p.id=g.proid')
                ->group('p.id')
                ->field('p.*')
                ->where($whereSelf)
                ->page($page, $limit)
                ->order('sort desc,id desc')
                ->select()->toArray();

            $data = array_merge($data1, $data2);

            // 按 sort desc, id desc 排序
            usort($data, function($a, $b){
                $sa = intval($a['sort']);
                $sb = intval($b['sort']);
                if($sa != $sb) return $sb - $sa;
                return intval($b['id']) - intval($a['id']);
            });

            // 分页截取（因为用了 array_merge 需要手动分页）
            $offset = ($page - 1) * $limit;
            $data = array_slice($data, $offset, $limit);
        } else {
            $data = Db::name('shop_product')->alias('p')->join('shop_guige g','p.id=g.proid')->group('p.id')->field('p.*')->where($where)->page($page, $limit)->order('sort desc,id desc')->select()->toArray();
        }
        $cdata = Db::name('shop_category')->where('aid', aid)->column('name', 'id');
        if (bid > 0) {
            $cdata2 = Db::name('shop_category2')->Field('id,name')->where('aid', aid)->where('bid', bid)->order('sort desc,id')->column('name', 'id');
        }
        if (empty($data)) $data = [];
        foreach ($data as $k => $v) {
            // 直营店映射商品：使用直营店售价
            if($isDirectStore && $storeHeadBid > 0 && $v['bid'] == $storeHeadBid){
                $data[$k]['sell_price'] = \app\commons\ShopSync::getDisplayPrice(bid, $v['id']);
                // 标记来源
                $data[$k]['source_type'] = 'mapped';
            }
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
            if ($v['bid'] > 0) {
                $v['cid2'] = explode(',', $v['cid2']);
                $data[$k]['cname2'] = null;
                if ($v['cid2']) {
                    foreach ($v['cid2'] as $cid) {
                        if ($data[$k]['cname2'])
                            $data[$k]['cname2'] .= ' ' . $cdata2[$cid];
                        else
                            $data[$k]['cname2'] .= $cdata2[$cid];
                    }
                }
                $data[$k]['bname'] = Db::name('business')->where('aid', aid)->where('id', $v['bid'])->value('name');
            } else {
                $data[$k]['cname2'] = '';
                $data[$k]['bname'] = '平台自营';
            }
            if ($v['status'] == 2) { //设置上架时间
                if (strtotime($v['start_time']) <= time() && strtotime($v['end_time']) >= time()) {
                    $data[$k]['status'] = 1;
                } else {
                    $data[$k]['status'] = 0;
                }
            }
            if ($v['status'] == 3) { //设置上架周期
                $start_time = strtotime(date('Y-m-d ' . $v['start_hours']));
                $end_time = strtotime(date('Y-m-d ' . $v['end_hours']));
                if (($start_time < $end_time && $start_time <= time() && $end_time >= time()) || ($start_time >= $end_time && ($start_time <= time() || $end_time >= time()))) {
                    $data[$k]['status'] = 1;
                } else {
                    $data[$k]['status'] = 0;
                }
            }
            if ($v['bid'] == -1) $data[$k]['sort'] = $v['sort'] - 1000000;
            $guige = Db::name('shop_guige')->where('proid', $v['id'])->select()->toArray();
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
        }
        return $this->json(1, 'ok', $data);
    }

    /**
     * @description 加入收银台
     */
    public function addToCashier()
    {
        $cashier_id = input('param.cashier_id/d', 0);
        $proid = input('param.proid', 0);
        $barcode = input('param.barcode', 0);
        $ggid = input('param.ggid/d', 0);
        $num = input('param.num/d', 1);
        $price = input('param.price', 0);
        if ($num < 0) {
            $num = 1;
        }
        //如果是扫码获得的条形码信息
        if($barcode){
            //因guige中没有bid，先查出aid所有规格中符合编码的所有产品ID，再确定该商户中是哪个产品ID,确定后再以产品ID和编码确定规格信息
            $proids = Db::name('shop_guige')->where('aid',aid)->where('barcode',$barcode)->column('proid');
            if(empty($proids)){
                $shopproduct = Db::name('shop_product')->where('aid',aid)->where('bid',bid)->where('procode',$barcode)->find();
                if(empty($shopproduct))return $this->json(0, '未查询到相关商品');
                $proid = $shopproduct['id'];
                $ggid = Db::name('shop_guige')->where('aid',aid)->where('proid', $proid)->value('id');
            } else{
                $shopproduct = Db::name('shop_product')->where('aid',aid)->where('bid',bid)->where('id','in',$proids)->find();
                if($shopproduct){
                    $ggid = Db::name('shop_guige')->where('aid',aid)->where('proid', $shopproduct['id'])->where('barcode',$barcode)->value('id');
                    $proid = $shopproduct['id'];
                }else{
                    $shopproduct = Db::name('shop_product')->where('aid',aid)->where('bid',bid)->where('procode',$barcode)->find();
                    if(empty($shopproduct))return $this->json(0, '未查询到相关商品');
                    $proid = $shopproduct['id'];
                    $ggid = Db::name('shop_guige')->where('aid',aid)->where('proid', $proid)->value('id');
                }
            }
            $num = 1;
        }
    
        if (empty($proid)) {
            return $this->json(0, '请选择商品');
        }
        $order = $this->getWaitOrder($cashier_id);
        $goodsinfo = [];
        if (empty($order)) {
            //重新生成订单编号
            $date = date('ymdHis');
            $rand = rand(100000, 999999);
            $ordernum = 'C' . $date . $rand;
            $orderid = 0;
            $data = [];
            $data['ordernum'] = $ordernum;
            $data['cashier_id'] = $cashier_id;
            $data['aid'] = aid;
            $data['bid'] = bid;
            $data['createtime'] = time();
            $data['status'] = 0;
            $orderid = Db::name('cashier_order')->insertGetId($data);
            $order = $data;
            $order['id'] = $orderid;
        } else {
            $ordernum = $order['ordernum'];
            $orderid = $order['id'];
        }
        $sql = '';
        //直接收款
        $guige = [];
        if ($proid == -99) {
            if (empty($price) || !is_numeric($price)) {
                return $this->json(0, '请输入收款金额');
            }
            $goodsCount = Db::name('cashier_order_goods')->where('orderid', $orderid)->where('protype', 2)->count();
            $dbproid = $ordernum . '_' . ($goodsCount + 1);
            $product['id'] = $dbproid;
			$product['id2'] = 0;
            $product['name'] = '直接收款';
            $product['sell_price'] = $price;
            $product['pic'] = '';
            $product['protype'] = 2;
        } else {
            //如果是多规格，需要指定规格
            $guiges = Db::name('shop_guige')->where('proid', $proid)->select()->toArray();
            if (count($guiges) > 1) {
                if (empty($ggid)) {
                    return $this->json(0, '请选择购买的商品规格');
                } else {
                    $guige = Db::name('shop_guige')->where('proid', $proid)->where('id', $ggid)->find();
                }
            } elseif (count($guiges) == 1) {
                $guige = $guiges[0];
                $ggid = $guige['id'];
            }
            $product = Db::name('shop_product')->where('aid', aid)->where('bid',bid)->where('status', '<>', 0)->where('ischecked', 1)->where('id', $proid)->find();
          
            if (!$product) return $this->json(0, '产品不存在或已下架');
            $product['protype'] = 1;
            if($guige['stock']<1){
                return $this->json(0, '产品库存不足');
            }
			$product['id2'] = $product['id'];
        }
        if ($proid > 0) {
            $goodsinfo = Db::name('cashier_order_goods')->where('orderid', $order['id'])->where('proid', $proid)->where('ggid', $ggid)->find();
        }

        $goodsData = [];
        $goodsData['orderid'] = $orderid;
        $goodsData['proid'] = $product['id'];
        $goodsData['proid2'] = $product['id2'];
        $goodsData['proname'] = $product['name'];
        $goodsData['ggid'] = $guige['id'] ?? 0;
        $goodsData['ggname'] = $guige['name'] ?? '';
        $goodsData['barcode'] = $guige['barcode'] ?? '';
        $goodsData['propic'] = $guige['pic'] ? $guige['pic'] : $product['pic'];
        $goodsData['sell_price'] = $guige['sell_price'] ? $guige['sell_price'] : $product['sell_price'];
		$goodsData['cost_price'] = $guige['cost_price'] ? $guige['cost_price'] : $product['cost_price'];
        $goodsData['protype'] = $product['protype'];
        $goodsData['aid'] = aid;
        $goodsData['bid'] = bid;
        if(getcustom('cashier_area_fenhong',aid) && $product['bid']>0){
            $sysset = Db::name('admin_set')->where('aid',aid)->find();
            if(empty($sysset['fhjiesuanbusiness'])){
                $goodsData['isfenhong'] = -1;
            }
        }
       
        if (empty($goodsinfo)) {
            if($product['perlimitdan'] > 0 && $num >= $product['perlimitdan']){ //每单限购
                return json(['status'=>0,'msg'=>$product['name'].'每单限购'.$product['perlimitdan'].'份']);
            }
            $goodsData['createtime'] = time();
            $goodsData['num'] = $num;
            Db::name('cashier_order_goods')->insert($goodsData);
        } else {
            $goodsData['num'] = $goodsinfo['num'] + $num;
            if($product['perlimitdan'] > 0 &&  $goodsData['num'] >= $product['perlimitdan']){ //每单限购
                return json(['status'=>0,'msg'=>$product['name'].'每单限购'.$product['perlimitdan'].'份']);
            }
            Db::name('cashier_order_goods')->where('id', $goodsinfo['id'])->update($goodsData);
        }
        return $this->json(1, 'ok');
    }

    /**
     * @description 挂单
     */
    public function hangup()
    {
        $cashier_id = input('param.cashier_id/d', 0);
        $mid = input('param.mid/d', 0);
        $order = $this->getWaitOrder($cashier_id);
        if (empty($order)) {
            return $this->json(0, '暂无可挂订单');
        }
        $orderup = ['status' => 2, 'hangup_time' => time()];
        if($mid){
            $orderup['mid'] = $mid;
            Db::name('cashier_order_goods')->where('orderid', $order['id'])->update(['mid'=>$mid]);
        }
        Db::name('cashier_order')->where('id', $order['id'])->update($orderup);
        return $this->json(1, '挂单成功');
    }

    /**
     * @description 取单
     */
    public function cancelHangup()
    {
        $orderid = input('param.orderid/d', 0);
        $order = Db::name('cashier_order')->where('aid', aid)->where('bid', bid)->where('id', $orderid)->find();
        $order0 = $this->getWaitOrder($order['cashier_id']);

        if (empty($order)) {
            return $this->json(0, '无效订单');
        }
        if ($order0) {
            $goodsC = Db::name('cashier_order_goods')->where('orderid',$order0['id'])->count();
            if($goodsC<1){
                //如果待结算没有物品，则直接删除这个订单
                Db::name('cashier_order')->where('id',$order0['id'])->delete();
            }else{
                return $this->json(0, '请先结算其他订单后再取单');
            }
        }
        if ($order['status'] != 2) {
            return $this->json(0, '订单状态有误');
        }
        Db::name('cashier_order')->where('id', $order['id'])->update(['status' => 0]);
        return $this->json(1, '取单成功');
    }

    public function payPreview(){
        $cashier_id = input('param.cashier_id/d', 0);
        $couponrid = input('param.couponid/d', 0);
        $mid = input('param.mid/d', 0);
        $userscore = input('param.userscore/d', 0);
        $order = $this->getWaitOrder($cashier_id);
        if (empty($order)) {
            return $this->json(0, '无待结算订单');
        }
        $params  = [];
        if(getcustom('cashier_money_dec')){
            //计算余额抵扣 
            $moneyrate = input('moneyrate')?input('moneyrate'):0;//是否使用余额抵扣 0否 1是
            $params['moneyrate'] = $moneyrate;
        }
        if(getcustom('cashier_overdraft_money_dec')){
            //计算余额抵扣 
            $overdraft_moneyrate = input('overdraft_moneyrate')?input('overdraft_moneyrate'):0;//是否使用余额抵扣 0否 1是
            $params['overdraft_moneyrate'] = $overdraft_moneyrate;
        }
        //优惠券
        $bid = bid;
        $userinfo = [];
        $newcouponlist = [];
        //计算总价
        $allgoods = Db::name('cashier_order_goods')->where('orderid', $order['id'])->select()->toArray();
        if (empty($allgoods)) {
            return $this->json(0, '无待结算商品');
        }
        $member = [];
        //商城商品
        $totalprice = 0;
        $buydata = [];
        $proids = [];
        if(getcustom('yx_hongbao_queue_free')){
            $is_use_youhui = 1;
            $hongbao_queue_set = Db::name('hongbao_queue_free_set')->where('aid',aid)->field('productids,gettj')->find();
            $hongbao_join_proids = $hongbao_queue_set['productids'];
            $hongbao_join_proids_arr = explode(',',$hongbao_join_proids);
            $hongbao_gettj = explode(',',$hongbao_queue_set['gettj']);
        }
        foreach ($allgoods as $k => $v) {
            $totalprice += $v['sell_price'] * $v['num'];
            //商城商品
            if ($v['protype'] == 1) {
                $buydata[] = $v;
                $proids[] = $v['proid'];
                $product = Db::name('shop_product')->where('id',$v['proid'])->field('name,limit_start,perlimit,perlimitdan')->find();
                $ordernum = Db::name('cashier_order_goods')->where('orderid',$v['orderid'])->where('proid',$v['proid'])->sum('num');
                if($product['limit_start'] > 0 && $ordernum < $product['limit_start']){ //起售份数
                    return  $this->json(0,$product['name'].'最低购买'.$product['limit_start'].'份');
                }
                if($mid){
                    $buynum =  Db::name('cashier_order_goods')->alias('og')
                        ->join('ddwx_cashier_order co','og.orderid = co.id')
                        ->where('og.aid',aid)->where('co.mid',$mid)->where('og.proid',$v['proid'])->sum('og.num');   
                    if($product['perlimit'] > 0 && $buynum > $product['perlimit']){ //起售份数
                        return  $this->json(0,'['.$product['name'].'] 每人限购'.$product['perlimit'].'件');
                    }
                }
                if($product['perlimitdan'] > 0 && $v['num'] >= $product['perlimitdan']){ //每单限购
                    return json(['status'=>0,'msg'=>$product['name'].'每单限购'.$product['perlimitdan'].'份']);
                }
                //库存校验
                $gginfo = Db::name('shop_guige')->where('aid',aid)->where('id',$v['ggid'])->find();
                if($gginfo['stock']<$v['num']){
                    return $this->json(0, $v['proname'].'('.$v['ggname'].')'.'库存不足');
                }
                if(getcustom('yx_hongbao_queue_free')){
                    $member_levelid = Db::name('member')->where('id',$mid)->value('levelid');
                   
                    if(in_array($v['proid'],$hongbao_join_proids_arr) && (in_array($member_levelid,$hongbao_gettj) || in_array(-1,$hongbao_gettj))) $is_use_youhui = 0;
                }
            }
        }
        $cids = [];
        $cids2 = [];
        if ($proids) {
            $cidarr = Db::name('shop_product')->where('aid',aid)->where('bid', bid)->where('id', 'in', $proids)->column('cid','id');
            if($cidarr){
                $cids = array_values($cidarr);
            }
            $cidarr2 = Db::name('shop_product')->where('aid',aid)->where('bid', bid)->where('id', 'in', $proids)->column('cid2','id');
            if($cidarr2){
                $cids2 = array_values($cidarr2);
            }
        }
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
            $couponlist = Db::name('coupon_record')
                ->where('aid', aid)->where('mid', $mid)->where('bid', $bid)->where('type', 'in',[1,2,10])->where('status', 0)->where('starttime', '<=', time())->where('endtime', '>', time())
                ->order('id desc')->select()->toArray();
            if (!$couponlist) $couponlist = [];
            foreach ($couponlist as $k => $v) {
                $couponinfo = Db::name('coupon')->where('aid', aid)->where('id', $v['couponid'])->find();
                if(empty($couponinfo)){
                    continue;
                }
                $gettj = explode(',', $couponinfo['gettj']);
                if (!in_array('-1', $gettj) && !in_array($member['levelid'], $gettj)) { //不是所有人
                    continue;
                }
                //0全场通用,1指定类目,2指定商品
                $fwtype_array = [0, 1, 2];
                if (!in_array($couponinfo['fwtype'],$fwtype_array)) {
                    continue;
                }
                //适用范围
                $fwscene_array = [0];
                if(getcustom('coupon_maidan_cashdesk')){
                    $fwscene_array[] = 2;
                }
                if (!in_array($couponinfo['fwscene'],$fwscene_array)) {
                    continue;
                }
                if ($couponinfo['fwtype'] == 2) {//指定商品可用
                    $productids = explode(',', $couponinfo['productids']);
                    if (!array_intersect($proids, $productids)) {
                        continue;
                    }
                    $thistotalprice = 0;
                    foreach ($buydata as $k2 => $product) {
                        if (in_array($product['proid'],$productids)){
                            $thistotalprice += $product['sell_price'] * $product['num'];
                        }
                    }
                    if ($thistotalprice < $v['minprice']) {
                        continue;
                    }
                }
                if ($couponinfo['fwtype'] == 1) {//指定类目可用
                    $categoryids = explode(',', $couponinfo['categoryids']);
                    $categoryids1 = Db::name('shop_category')->where('pid', 'in', $categoryids)->column('id');
                    if (empty($categoryids1)) $categoryids1 = [];
                    $categoryids = array_merge($categoryids, $categoryids1);
                    if (!array_intersect($cids, $categoryids)) {
                        continue;
                    }
                    $thistotalprice = 0;
                    foreach ($buydata as $k2 => $product) {
                        if(isset($cidarr[$product['proid']])){
                            $thistotalprice += $product['sell_price'] * $product['num'];
                        }
                    }
                    if ($thistotalprice < $v['minprice']) {
                        continue;
                    }
                }  
                if ($couponinfo['fwtype'] == 6) {//指定商家类目可用
                    $categoryids2 = explode(',', $couponinfo['categoryids2']);
                    $categoryids3 = Db::name('shop_category2')->where('pid', 'in', $categoryids2)->column('id');
                    if (empty($categoryids3)) $categoryids3 = [];
                    $categoryids2 = array_merge($categoryids2, $categoryids3);
                    if (!array_intersect($cids2, $categoryids2)) {
                        continue;
                    }
                    $thistotalprice = 0;
                    foreach ($buydata as $k2 => $product) {
                        if(isset($cidarr2[$product['proid']])){
                            $thistotalprice += $product['sell_price'] * $product['num'];
                        }
                    }
                    if ($thistotalprice < $v['minprice']) {
                        continue;
                    }
                }
                if(getcustom('coupon_maidan_cashdesk')){
                    if ($couponinfo['fwtype'] == 7) {//收银台专用
                        $thistotalprice = 0;
                        foreach ($buydata as $k2 => $product) {
                            if(isset($cidarr[$product['proid']])){
                                $thistotalprice += $product['sell_price'] * $product['num'];
                            }
                        }
                        if ($thistotalprice < $v['minprice']) {
                            continue;
                        }
                    }
                }
                if ($v['bid'] > 0) {
                    $binfo = Db::name('business')->where('aid', aid)->where('id', $v['bid'])->find();
                    $v['bname'] = $binfo['name'] ?? '';
                }
                $v['starttime'] = date('Y-m-d H:i:s',$v['starttime']);
                $v['endtime'] = date('Y-m-d H:i:s',$v['endtime']);
                $tip = $this->getCouponTip($v);
                $v['tip'] = $tip;
                $newcouponlist[] = $v;
            }
            if(getcustom('yx_hongbao_queue_free')){
                if($is_use_youhui ==0) $newcouponlist=[];
            }
            
        }
        //抹零
        $return = [];
        $orderResult = $this->getOrderPrice($order,$couponrid,$userscore,$mid,$params);
        if($orderResult['status']!=1){
            return $this->json(0, $orderResult['msg']);
        }
        $return['totalprice'] = round($orderResult['pre_totalprice'],2);
        $return['final_totalprice'] = round($orderResult['totalprice'],2);
        $return['leveldk_money'] = $orderResult['leveldk_money'];
        $return['coupon_money'] = $orderResult['coupon_money'];
        $return['scoredk_money'] = $orderResult['scoredk_money'];
        $return['moling_money'] = $orderResult['moling_money'];
        $return['totalscore'] = $orderResult['totalscore'];
        $set = Db::name('admin_set')->where('aid', aid)->find();
        $return['memberinfo'] = $userinfo??'';
        $return['couponlist'] = $newcouponlist;
        $return['score2money'] =$set['score2money']?$set['score2money']:'0';

        if(getcustom('cashier_money_dec')){
            //余额抵扣
            $moneydec = false;
            $money_dec_rate = 0;
            if(empty(bid)){
                $adminset = Db::name('admin_set')->where('aid',aid)->field('money_dec,money_dec_rate')->find();
                $money_dec_rate = 0;//抵扣比例
                if($adminset['money_dec'] && $adminset['money_dec_rate']>0){
                    $moneydec = true;
                    $money_dec_rate = $adminset['money_dec_rate'];
                }
            }else{
                //查询商户余额抵扣比例
                $business = Db::name('business')->where(['aid'=>aid,'id'=>bid])->field('money_dec,money_dec_rate')->find();
                if($business && $business['money_dec'] && $business['money_dec_rate']>0){
                    $moneydec = true;
                    $money_dec_rate = $business['money_dec_rate'];
                }
            }
            $return['moneydec']       = $moneydec;//定制是否开启
            $return['dec_money']      = $orderResult['dec_money'];//余额抵扣数值
        }
        if(getcustom('cashier_overdraft_money_dec')){
            //信用额度抵扣
            $overdraft_moneydec = false;
            if(empty(bid)){
                $adminset = Db::name('admin_set')->where('aid',aid)->field('overdraft_money_dec,overdraft_money_dec_rate')->find();
                if($adminset['overdraft_money_dec'] && $adminset['overdraft_money_dec_rate']>0){
                    $overdraft_moneydec = true;
                }
            }else{
                //查询商户余额抵扣比例
                $business = Db::name('business')->where(['aid'=>aid,'id'=>bid])->field('overdraft_money_dec,overdraft_money_dec_rate')->find();
                if($business && $business['overdraft_money_dec'] && $business['overdraft_money_dec_rate']>0){
                    $overdraft_moneydec = true;
                }
            }
            $return['overdraft_moneydec']       = $overdraft_moneydec;//定制是否开启
            $return['dec_overdraft_money']      = $orderResult['dec_overdraft_money'];//余额抵扣数值
            if ($mid) {
                $limit_money = $member['limit_overdraft_money'];
                $open_overdraft_money = $member['open_overdraft_money'];
                $overdraft_money = $member['overdraft_money']*-1;
                if(empty($limit_money)){
                    $overdraft_money_now = 0; 
                }else{
                    $overdraft_money_now = round($limit_money - $overdraft_money,2);
                }
                //额度
                if($open_overdraft_money == 1){
                    $overdraft_money_now = '无限';
                }
                $userinfo['overdraft_money'] = $overdraft_money_now;
                $return['memberinfo'] = $userinfo;
            }
        }
        return $this->json(1,'ok',$return);
    }
    
    public function getPayList(){

        $paylist =[];
        $cashier_id = input('param.cashier_id/d', 0);
        $cashier = Db::name('cashier')->where('id',$cashier_id)->where('aid',aid)->find();
        $cashier['bid'] = bid;
        $sysset = Db::name('business_sysset')->where('aid',aid)->find();
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
                    $paylist[] = ['value'=>'81','lable'=>'随行付','tip' =>'请扫描微信或支付宝付款码收款，确认收款成功后，点击确认收款即可完成收款操作。','pay_tip' =>'请扫描微信或支付宝付款码','icon' =>PRE_URL.'/static/img/cashdesk/wechat_alipay.png'];
                }
                if($cashier['bid'] > 0 && $sysset['business_cashdesk_sxpay_type'] > 0){
                    $paylist[] = ['value'=>'81','lable'=>'随行付','tip' =>'请扫描微信或支付宝付款码收款，确认收款成功后，点击确认收款即可完成收款操作。','pay_tip' =>'请扫描微信或支付宝付款码','icon' =>PRE_URL.'/static/img/cashdesk/wechat_alipay.png'];
                }
            }
        }
        if(getcustom('pay_huifu')){
            if(($cashier['bid'] == 0 &&  $cashier['huifupay'] > 0)||($cashier['bid'] > 0 && $sysset['business_cashdesk_huifupay'] > 0)) {
                $paylist[] = ['value' => '62', 'lable' => '汇付支付', 'tip' => '', 'pay_tip' => '', 'icon' => PRE_URL . '/static/img/cashdesk/wechat_alipay.png'];
            }
        }
        if(($cashier['bid'] == 0 &&  $cashier['cashpay'] > 0)  ||($cashier['bid'] > 0 && $sysset['business_cashdesk_cashpay'] > 0)){
            $paylist[]=['value'=>'3','lable'=>t('现金'),'tip' => '','pay_tip' =>'','icon' => PRE_URL.'/static/img/cashdesk/xianjin.png'];
        }
        $normal_list =$paylist;
        if(($cashier['bid'] == 0 &&  $cashier['moneypay'] > 0) ||($cashier['bid'] > 0 && $sysset['business_cashdesk_yue'] > 0)) {
            $paylist[] = ['value' => '4', 'lable' => t('余额'), 'tip' => '', 'pay_tip' => '', 'icon' => PRE_URL . '/static/img/cashdesk/yue.png'];
        }
        if(getcustom('member_overdraft_money')){
            $overdraft_moneypay = Db::name('admin_set')->where('aid',aid)->value('overdraft_moneypay');
     
            if(($cashier['bid'] == 0 &&  $cashier['guazhangpay'] > 0 && $overdraft_moneypay)||($cashier['bid'] > 0 && $sysset['business_cashdesk_guazhang'] > 0 && $overdraft_moneypay)) {
                $paylist[] = ['value' => '38', 'lable' => t('信用额度'), 'tip' => '', 'pay_tip' => '', 'icon' => PRE_URL . '/static/img/cashdesk/guazhang.png'];
            }
        }
        $return['paylist'] = $paylist;
        $return['normal_list'] = $normal_list;
        return $this->json(1,'ok',$return);
    }
    //只获取线上支付方式
    public function getOnlinePayList(){
        $type=input('param.type',0);//只获取线上支付  1：加 现金支付
        $cashier_id = input('param.cashier_id/d', 0);
        $cashier = Db::name('cashier')->where('id',$cashier_id)->where('aid',aid)->find();
        $sysset = Db::name('business_sysset')->where('aid',aid)->find();
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
    /**
     * @description 收银台结算  
     *  type=1=>微信扫码支付    =》2
     *  type=2=>支付宝扫码支付    =》3
     *  type=3=> 现金支付         =》 0
     *  type=4=> 余额支付 随行付   =》1   
     *  type=5=> 随行付           =》 5 =>更改为81
     */
    public function pay()
    {
        $cashier_id = input('param.cashier_id/d', 0);
        $couponrid = input('param.couponid/d', 0);
        $mid = input('param.mid/d', 0);
        $userscore = input('param.userscore/d', 0);
        $paytype = input('param.paytype/d', 0);
        $params  = [];
        if(getcustom('cashier_money_dec')){
            //计算余额抵扣
            $moneyrate = input('moneyrate')?input('moneyrate'):0;//是否使用余额抵扣 0否 1是
            $dec_money = input('dec_money')?input('dec_money'):0;//余额抵扣数值
            $params['moneyrate'] = $moneyrate;
            $params['dec_money'] = $dec_money;
            $params['ispay']     = true;
        }
        //信用额度抵扣
        if(getcustom('cashier_overdraft_money_dec')){
            //计算余额抵扣
            $overdraft_moneyrate = input('overdraft_moneyrate')?input('overdraft_moneyrate'):0;//是否使用余额抵扣 0否 1是
            $dec_overdraft_money = input('dec_overdraft_money')?input('dec_overdraft_money'):0;//余额抵扣数值
            $params['overdraft_moneyrate'] = $overdraft_moneyrate;
          
            $params['dec_overdraft_money'] = $dec_overdraft_money;
            $params['ispay']     = true;
        }
        if(getcustom('extend_staff')){
            //选择的员工
            $params['staffid']               = input('staffid')?input('staffid'):0;
            $params['staff_commission_rate'] = 0;
            if($params['staffid']){
                $staff = Db::name('staff')->where('id',$params['staffid'])->where('aid',aid)->where('bid',bid)->where('status',1)->field('id,commission_rate')->find();
                if(!$staff){
                    return $this->json(0,'所选员工不存在');
                }
                $params['staff_commission_rate'] = $staff['commission_rate'];
            }
        }
        if($paytype==1){
            $auth_code = input('param.auth_code');
            $wx_reg = '/^1[0-6][0-9]{16}$/';//微信
            $ali_reg = '/^(?:2[5-9]|30)\d{14,22}$/';;//支付宝
            if(preg_match($wx_reg,$auth_code)){
                return $this->wxScanPay($cashier_id,$mid,$couponrid,$userscore,$params);
            }elseif(preg_match($ali_reg,$auth_code)){
                return $this->aliScanPay($cashier_id,$mid,$couponrid,$userscore,$params);
            }else{
                return $this->json(0,'请扫微信或支付宝付款码付款');
            }
        }elseif($paytype==3){
        return $this->cashPay($cashier_id,$mid,$couponrid,$userscore,$params);
        }elseif($paytype==4){
           return $this->moneyPay($cashier_id,$mid,$couponrid,$userscore,$params);
       }elseif($paytype==5 || $paytype==81){//随行付5被占用，改为81
            return $this->sxPay($cashier_id,$mid,$couponrid,$userscore,$params);
        }elseif ($paytype ==38){//挂账
            if(getcustom('member_overdraft_money')){
                return $this->guazhangPay($cashier_id,$mid,$couponrid,$userscore,$params);
            }
        }else{
            return $this->json(0,'非法请求');
        }
    }
    //扫码枪微信扫码支付
    protected function wxScanPay($cashier_id=0,$mid=0,$couponrid=0,$userscore=0,$params=[]){
        $auth_code = input('param.auth_code');
        //过滤capslock
        $auth_code = str_replace('capslock','',str_replace(' ','',strtolower($auth_code)));
        //验证code是否正确
        $reg = '/^1[0-6][0-9]{16}$/';
        if(!preg_match($reg,$auth_code)){
            return $this->json(0, '无效的付款码:'.$auth_code);
        }
        //$file = ROOT_PATH.'runtime/log/wxpay.txt';
        $order = $this->getWaitOrder($cashier_id);
        if (empty($order)) {
            return $this->json(0, '无待结算订单');
        }
        $goodslist = Db::name('cashier_order_goods')->where('orderid', $order['id'])->select()->toArray();
        if (empty($goodslist)) {
            return $this->json(0, '无待结算商品');
        }
        foreach ($goodslist as $k=>$v){
            if($v['protype']==1){
                //库存校验
                $gginfo = Db::name('shop_guige')->where('aid',aid)->where('id',$v['ggid'])->find();
                if($gginfo['stock']<$v['num']){
                    return $this->json(0, $v['proname'].'('.$v['ggname'].')'.'库存不足');
                }
            }
        }
        $orderResult = $this->getOrderPrice($order,$couponrid,$userscore,$mid,$params);
        if($orderResult['status']!=1){
            return $this->json(0, $orderResult['msg']);
        }
        $set = Db::name('admin_set')->where('aid',aid)->find();
        $wxpaymoney  = $orderResult['totalprice'];
        if($wxpaymoney > 0){
            $wxplatform = 'cashdesk';
            $appinfo = Db::name('admin_setapp_cashdesk')->where('aid',aid)->where('bid',0)->find();
            $pars = [];
            if($appinfo['wxpay_type']==0){
                $pars['appid'] = $appinfo['appid'];
                $pars['mch_id'] = $appinfo['wxpay_mchid'];
                $mchkey = $appinfo['wxpay_mchkey'];
            }else{
                if(bid > 0){
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
                }
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
                $bappinfo = Db::name('admin_setapp_cashdesk')->where('aid',aid)->where('bid',bid)->find();
                
                //1:服务商 2：平台收款 3：独立收款 0：关闭
                $restaurant_sysset = Db::name('business_sysset')->where('aid',aid)->find();
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
            $this->wlog(json_encode($response));
            //直接支付成功
            if($response->return_code=='SUCCESS' && $response->result_code=='SUCCESS' && $response->trade_type=='MICROPAY'){
                $transaction_id = $response->transaction_id;
            }else{
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
                    $this->wlog('-----第'.$i.'次查询-------');
                    $this->wlog(json_encode($response2));
                    if($response2->return_code=='FAIL'){
                        return $this->json(0,'支付失败：'.strval($response2->return_msg));
                    }else{
                        if ($response2->return_code=='SUCCESS' && $response2->result_code == 'SUCCESS' && $response2->trade_state=="SUCCESS") {
                            $result = true;
                            $transaction_id = $response2->transaction_id;
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
                $orderup= [];
                $orderup['status'] = 1;
                $orderup['paytype'] = '收银台微信扫码';
                $orderup['paytypeid'] = 2;
                $orderup['paynum'] = $transaction_id;
                $orderup['platform'] = $wxplatform;
                $orderup['paytime'] = time();
                $orderup['pre_totalprice'] = $orderResult['pre_totalprice'];
                $orderup['totalprice'] = $orderResult['totalprice'];
                $orderup['moling_money'] = $orderResult['moling_money'];
                if($orderResult['coupon_money']>0){
                    $orderup['coupon_money'] = $orderResult['coupon_money'];
                    $orderup['coupon_rid'] = $couponrid;
                }
                $orderup['scoredk_money'] = $orderResult['scoredk_money'];
                $orderup['scoredkscore'] = $orderResult['totalscore'];
                $orderup['leveldk_money'] = $orderResult['leveldk_money'];
                $orderup['uid'] = $this->uid;
                $orderup['mid'] = $mid;
            }else{
                return $this->json(0,'支付失败:'.$response->return_msg,$response);
            }
        }else{
            $orderup= [];
            $orderup['status'] = 1;
            $orderup['paytype'] = '无须支付';
            $orderup['paynum'] = '';
            $orderup['paytime'] = time();
            $orderup['pre_totalprice'] = $orderResult['pre_totalprice'];
            $orderup['totalprice'] = $orderResult['totalprice'];
            $orderup['moling_money'] = $orderResult['moling_money'];
            $orderup['coupon_money'] = $orderResult['coupon_money'];
            $orderup['scoredk_money'] = $orderResult['scoredk_money'];
            $orderup['scoredkscore'] = $orderResult['totalscore'];
            $orderup['leveldk_money'] = $orderResult['leveldk_money'];
            $orderup['uid'] = $this->uid;
            $orderup['mid'] = $mid;
        }
        if(getcustom('cashier_money_dec')){
            if($params && $params['moneyrate'] && $orderResult['dec_money']>0){
                $orderup['dec_money']      = $orderResult['dec_money'];
                $orderup['money_dec_rate'] = $orderResult['money_dec_rate'];
            }
        }
        if(getcustom('cashier_overdraft_money_dec')){
            if($params && $params['overdraft_moneyrate'] && $orderResult['dec_overdraft_money']>0){
                $orderup['dec_overdraft_money']      = $orderResult['dec_overdraft_money'];
                $orderup['overdraft_money_dec_rate'] = $orderResult['overdraft_money_dec_rate'];
            }
        }
        if(getcustom('extend_staff')){
            //员工提成
            if($params && $params['staffid'] && $params['staff_commission_rate']>0){
                $orderup['staffid']               = $params['staffid'];
                $orderup['staff_commission_rate'] = $params['staff_commission_rate'];
                //计算员工提成
                $staff_commission = $params['staff_commission_rate']*$orderup['totalprice']*0.01;
                $orderup['staff_commission'] = round($staff_commission,2);
            }
        }
        $res = Db::name('cashier_order')->where('id',$order['id'])->update($orderup);
        //更新收银台表
        $payorderid =\app\models\Payorder::createorder(aid, $order['bid'], $mid, 'cashier', $order['id'], $order['ordernum'], '收银台微信收款', $orderup['totalprice'], $orderResult['totalscore']);
        Db::name('payorder')->where('id',$payorderid)->update(['paytype'=>'微信收款-餐饮收银台','paytypeid'=>2,'paynum'=>$orderup['paynum'],'status' =>1,'paytime' => time(),'platform' =>'cashier']);
        if($res){
            \app\commons\Wifiprint::print(aid,'cashier',$order['id']);
            $this->afterPay($order['id']);
            return $this->json(1,'支付成功',$response);

        }else{
            return $this->json(0,'支付失败！！！');
        }
    }
    //支付宝扫码支付
    protected function aliScanPay($cashier_id,$mid=0,$couponrid=0,$userscore=0,$params=[]){
        if(getcustom('cashdesk_alipay')) {
            $auth_code = input('param.auth_code');
            //过滤capslock
            $auth_code = str_replace('capslock', '', str_replace(' ', '', strtolower($auth_code)));
            //验证code是否正确
            $reg = '/^(?:2[5-9]|30)\d{14,22}$/';
            if (!preg_match($reg, $auth_code)) {
                return $this->json(0, '无效的付款码:' . $auth_code);
            }
            $order = $this->getWaitOrder($cashier_id);
            if (empty($order)) {
                return $this->json(0, '无待结算订单');
            }
            $goodslist = Db::name('cashier_order_goods')->where('orderid', $order['id'])->select()->toArray();
            if (empty($goodslist)) {
                return $this->json(0, '无待结算商品');
            }
            foreach ($goodslist as $k => $v) {
                if ($v['protype'] == 1) {
                    //库存校验
                    $gginfo = Db::name('shop_guige')->where('aid', aid)->where('id', $v['ggid'])->find();
                    if ($gginfo['stock'] < $v['num']) {
                        return $this->json(0, $v['proname'] . '(' . $v['ggname'] . ')' . '库存不足');
                    }
                }
            }
            $orderResult = $this->getOrderPrice($order, $couponrid, $userscore, $mid,$params);
            if ($orderResult['status'] != 1) {
                return $this->json(0, $orderResult['msg']);
            }
            $wxpaymoney = $orderResult['totalprice'];
            if($wxpaymoney > 0){
                $set = Db::name('admin_set')->where('aid',aid)->find();
                $platform = 'cashdesk';
                $return = Alipay::build_scan(aid,bid,'',$set['name'].'-当面付',$order['ordernum'],$wxpaymoney,'cashier','',$auth_code,'cashdesk');
                if($return['status'] ==1){
                    $orderup= [];
                    $orderup['status'] = 1;
                    $orderup['mid'] = $mid;
                    $orderup['paytype'] = '收银台支付宝当面付';
                    $orderup['paytypeid'] = 3;
                    $orderup['paynum'] = $return['data']['trade_no'];
                    $orderup['platform'] = $platform;
                    $orderup['paytime'] = time();
                    $orderup['pre_totalprice'] = $orderResult['pre_totalprice'];
                    $orderup['totalprice'] = $orderResult['totalprice'];
                    $orderup['moling_money'] = $orderResult['moling_money'];
                    if($orderResult['coupon_money']>0){
                        $orderup['coupon_money'] = $orderResult['coupon_money'];
                        $orderup['coupon_rid'] = $couponrid;
                    }
                    $orderup['scoredk_money'] = $orderResult['scoredk_money'];
                    $orderup['scoredkscore'] = $orderResult['totalscore'];
                    $orderup['leveldk_money'] = $orderResult['leveldk_money'];
                    $orderup['uid'] = $this->uid;
                    $orderup['mid'] = $mid;
                }else{
                    return $this->json(0,$return['msg']);
                } 
            }else{
                $orderup= [];
                $orderup['status'] = 1;
                $orderup['mid'] = $mid;
                $orderup['paytype'] = '无须支付';
                $orderup['paynum'] = '';
                $orderup['paytime'] = time();
                $orderup['platform'] = $this->platform;
                $orderup['pre_totalprice'] = $orderResult['pre_totalprice'];
                $orderup['totalprice'] = $orderResult['totalprice'];
                $orderup['moling_money'] = $orderResult['moling_money'];
                $orderup['coupon_money'] = $orderResult['coupon_money'];
                $orderup['scoredk_money'] = $orderResult['scoredk_money'];
                $orderup['scoredkscore'] = $orderResult['totalscore'];
                $orderup['leveldk_money'] = $orderResult['leveldk_money'];
                $orderup['uid'] = $this->uid;
                $orderup['mid'] = $mid;
            }
            if(getcustom('cashier_money_dec')){
                if($params && $params['moneyrate'] && $orderResult['dec_money']>0){
                    $orderup['dec_money']      = $orderResult['dec_money'];
                    $orderup['money_dec_rate'] = $orderResult['money_dec_rate'];
                }
            }
            if(getcustom('cashier_overdraft_money_dec')){
                if($params && $params['overdraft_moneyrate'] && $orderResult['dec_overdraft_money']>0){
                    $orderup['dec_overdraft_money']      = $orderResult['dec_overdraft_money'];
                    $orderup['overdraft_money_dec_rate'] = $orderResult['overdraft_money_dec_rate'];
                }
            }
            if(getcustom('extend_staff')){
                //员工提成
                if($params && $params['staffid'] && $params['staff_commission_rate']>0){
                    $orderup['staffid']               = $params['staffid'];
                    $orderup['staff_commission_rate'] = $params['staff_commission_rate'];
                    //计算员工提成
                    $staff_commission = $params['staff_commission_rate']*$orderup['totalprice']*0.01;
                    $orderup['staff_commission'] = round($staff_commission,2);
                }
            }
            $res = Db::name('cashier_order')->where('id',$order['id'])->update($orderup);
            //更新收银台表
            $payorderid =\app\models\Payorder::createorder(aid, $order['bid'], $mid, 'cashier', $order['id'], $order['ordernum'], '收银台支付宝收款', $orderup['totalprice'], $orderResult['totalscore']);
            Db::name('payorder')->where('id',$payorderid)->update(['paytype'=>'支付宝收款-收银台','paytypeid'=>3,'paynum'=>$orderup['paynum'],'status' =>1,'paytime' => time(),'platform' =>'cashier']);
            if($res){
                //打印
                \app\commons\Wifiprint::print(aid,'cashier',$order['id']);
                $this->afterPay($order['id']);
                return $this->json(1,'支付成功');
            }else{
                return $this->json(0,'支付失败！！！');
            }
        }   
    }
    
    protected function afterPay($orderid=0){

        $order = Db::name('cashier_order')->where('aid',aid)->where('id',$orderid)->find();
        if(empty($order)){
            return false;
        }
        if($order['coupon_rid']){
            Db::name('coupon_record')->where('id',$order['coupon_rid'])->where('mid',$order['mid'])->update(['status'=>1,'usetime'=>time()]);
        }
        if($order['scoredkscore'] > 0){
            if($order['bid'] ==0) {
                $rs = \app\commons\Member::addscore(aid,$order['mid'],-$order['scoredkscore'],'支付订单，订单号: '.$order['ordernum']);
            } else{
                $rs = \app\commons\Business::addmemberscore(aid,$order['bid'],$order['mid'],-$order['scoredkscore'],'支付订单,订单号: '.$order['ordernum'],1);
            }
        }

        if(getcustom('cashier_money_dec')){
            if($order['dec_money']>0){
                $res = \app\commons\Member::addmoney(aid,$order['mid'],-$order['dec_money'],t('余额').'抵扣订单,订单号: '.$order['ordernum']);
            }
        }
        if(getcustom('cashier_overdraft_money_dec')){
            if($order['dec_overdraft_money']>0){
                $res = \app\commons\Member::addOverdraftMoney(aid,$order['mid'],-$order['dec_overdraft_money'],t('信用额度').'抵扣订单,订单号: '.$order['ordernum']);
            }
        }

        $sysset = Db::name('admin_set')->where('aid',aid)->find();
        if(getcustom('cashdesk_commission')){
            if($order && $order['mid'] && $sysset['cashdeskfenxiao'] ==1){
                $order_goods = Db::name('cashier_order_goods')->where('orderid',$orderid)->select()->toArray();
                $member =  Db::name('member')->where('id',$order['mid'])->where('aid',$order['aid'])->find();
                $istc1 = 0; //设置了按单固定提成时 只将佣金计算到第一个商品里
                $istc2 = 0;
                $istc3 = 0;
                foreach ($order_goods as $key=>$val){
                    $product = Db::name('shop_product')->where('id',$val['proid'])->where('aid',$order['aid'])->find();
                    $commission_totalprice = $val['totalprice'];
                    
                    if($sysset['fxjiesuantype']==1){ //按成交价格
                        $commission_totalprice = $this->getOgRealPrice($order,$val['id']);
                    }
                    if($sysset['fxjiesuantype']==2){ //按销售利润
                        $real_price = $this->getOgRealPrice($order,$val['id']);
                        $commission_totalprice = dd_money_format($real_price - $val['cost_price'] * $val['num']);
                    }
                    $is_commission = true;
                    if($order['bid'] > 0){
                        $restaurant_sysset = Db::name('business_sysset')->where('aid',aid)->find();
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
                     
                        $this->getcommission($product,$member,$val,$commission_totalprice,$val['num'],$order['mid'],$istc1,$istc2,$istc3);
                    }
                }
                //进行分佣
                $record_list = Db::name('member_commission_record')->where('aid',aid)->where('status',0)->where('type','cashier')->select();
                foreach($record_list as $k=>$v){
                    Order::giveCommission($order,'cashier');
                }
            }
        }
       
        //平台收款时商户加佣金
        if($order['bid'] > 0){
            $order_goods = Db::name('cashier_order_goods')->where('orderid',$orderid)->select()->toArray();
            $this->addBusinessMoney($order,$order_goods);
        }
        if($order['mid'] > 0){
            \app\commons\Member::uplv(aid,$order['mid'],'cashier');

            //消费送积分
            if($sysset['scorein_money']>0 && $sysset['scorein_score']>0){
                if(($order['paytypeid'] == 1 && $sysset['score_from_moneypay'] == 1) || $order['paytypeid'] != 1)
                {
                    $givescore = floor($order['totalprice'] / $sysset['scorein_money']) * $sysset['scorein_score'];
                    $res = \app\commons\Member::addscore(aid,$order['mid'],$givescore,'消费送'.t('积分'),'cashier');
                    if($res && $res['status'] == 1){
                        //记录消费赠送积分记录
                        \app\commons\Member::scoreinlog(aid,0,$order['mid'],'cashier',$order['id'],$order['ordernum'],$givescore,$order['totalprice']);
                    }
                }
            }
        }
        if(getcustom('member_product_price')){
            if($order['mid'] > 0){
                //改价后的不参与 ,类型是商品的 
                $goodslist = Db::name('cashier_order_goods')->where('orderid', $order['id'])->where('is_gj',0)->where('protype',1)->select()->toArray();
                foreach ($goodslist as $k=>$goods){
                    //一客一价
                    $member_product = Db::name('member_product')->where('aid',aid)->where('mid',$order['mid'])->where('proid',$goods['proid'])->where('ggid',$goods['ggid'])->find();
                    if($member_product){
                        //增加记录
                        $buylog = [
                            'aid' => aid,
                            'mid' => $order['mid'],
                            'ordernum' => $order['ordernum'],
                            'type' =>'cashier',
                            'proid' => $goods['proid'],
                            'ggid' => $goods['ggid'],
                            'orderid' => $order['id'],
                            'sell_price' => $goods['totalprice']/$goods['num'],
                            'num' => $goods['num'],
                            'createtime' => time()
                        ];
                         Db::name('member_product_buylog')->insert($buylog);
                    }
                }
            }
            
        }
        if(getcustom('yx_buy_fenhong')){
            if($order['paytypeid'] !=1 && $order['mid'] > 0){
                $payorder = Db::name('payorder')->where('aid',aid)->where('id',$order['payorderid'])->find();
                \app\customs\BuyFenhong::getScoreWeight($payorder);
            }
        }
        if(getcustom('extend_staff')){
            //员工提成
            if($order['staffid'] && $order['staff_commission']>0 && $order['staff_commission_rate']>0){
                $staffparams = [
                    'staff_commission_rate'=>$order['staff_commission_rate'],
                    'orderid'=>$order['id'],
                    'type'   =>'cashier',
                    'uid'=>$order['uid'],
                    'totalprice'=>$order['totalprice'],
                ];
                \app\commons\Member::addstaffcommission(aid,bid,$order['staffid'],$order['staff_commission'],'收银成功发放: '.$order['ordernum'],$staffparams);
            }
        }
        //计算使用优惠券后的比例
         $goodslist  = Db::name('cashier_order_goods')->where('aid',$order['aid'])->where('orderid',$orderid)->select()->toArray();
        foreach($goodslist as $key=>$val){
            $real_price =  $this->getOgRealPrice($order,$val['id']);
            Db::name('cashier_order_goods')->where('id',$val['id'])->update(['real_totalprice' => $real_price]);
            //非直接收款的 修改库存
            if($val['proid'] > 0){
                $num = $val['num'];
                Db::name('shop_guige')->where('aid',aid)->where('id',$val['ggid'])->update(['stock'=>Db::raw("stock-$num"),'sales'=>Db::raw("sales+$num")]);
                Db::name('shop_product')->where('aid',aid)->where('id',$val['proid'])->update(['stock'=>Db::raw("stock-$num"),'sales'=>Db::raw("sales+$num")]);
            }
        }

        if(getcustom('yx_hongbao_queue_free')){
            \app\customs\HongbaoQueueFree::join($order,'cashier');
        }
    }
    public function addBusinessMoney($order=[],$oglist=[]){
        $businessDkScore = $businessDkMoney = 0;
        if($order['bid']!=0){//入驻商家的货款
            $aid = aid;
            $totalnum = 0;
            foreach($oglist as $og){
                $totalnum += $og['num'];
            }
            //判断是什么支付，判断是不是平台收款
            $sysset = Db::name('business_sysset')->where('aid',aid)->find();
            $add_business_money = false;
            if($order['paytypeid'] ==2 && $sysset &&  $sysset['business_cashdesk_wxpay_type'] ==2){//微信支付
                $add_business_money = true;
            }elseif ($order['paytypeid'] ==3 && $sysset && $sysset['business_cashdesk_alipay_type'] ==2){//支付宝
                $add_business_money = true;
            }elseif (($order['paytypeid'] ==5 || $order['paytypeid'] ==81 ) && $sysset && $sysset['business_cashdesk_sxpay_type'] ==2){//随行付
                $add_business_money = true;
            }elseif ($order['paytypeid'] ==1 && $sysset && $sysset['business_cashdesk_yue'] ==1){//余额
                $add_business_money = true;
            }
            $bset = Db::name('business_sysset')->where('aid',$aid)->find();
            if($add_business_money){
                $totalcommission = 0;
                $og_business_money = false;
                $totalmoney = 0;
                $lirun_cost_price = 0;
                foreach($oglist as $og){
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
                if($bset['commission_kouchu'] == 0){ //不扣除佣金
                    $totalcommission = 0;
                }
                $business_lirun = 0;
                if(getcustom('business_agent')){                    
                    $business_lirun = $order['totalprice']-$order['refund_money']-$lirun_cost_price;
                }
                $scoredkmoney = 0;
                if($bset['scoredk_kouchu'] == 0){
                    $scoredkmoney = 0;
                }elseif($bset['scoredk_kouchu'] == 1){ //扣除积分抵扣
                    $scoredkmoney = $order['scoredk_money'] ?? 0;
                }elseif($bset['scoredk_kouchu'] == 2){ //到商户余额
                    $businessDkMoney = $order['scoredk_money'];
                }elseif($bset['scoredk_kouchu'] == 3){ //到商户积分
                    $scoredkmoney = $order['scoredk_money'] ?? 0;
                    $businessDkScore = $order['scoredkscore'];
                }
                //商品独立费率
                if($og_business_money) {
                    $totalmoney = $totalmoney - $totalcommission - $order['refund_money'] - $scoredkmoney;
                    $platformMoney = $order['totalprice']-$totalmoney - $order['refund_money'];
                } else {
                    $totalmoney = $order['pre_totalprice']  - $order['coupon_money'] - $order['refund_money'] - $totalcommission - $scoredkmoney;
                    if($totalmoney > 0){
//                        $totalmoney = $totalmoney * (100-$binfo['feepercent']) * 0.01;
                        $platformMoney = $totalmoney * $binfo['feepercent'] * 0.01;
                        $totalmoney = $totalmoney - $platformMoney;
                    }
                }
                if($totalmoney < 0){
                    $bmoney = Db::name('business')->where('aid',$aid)->where('id',$order['bid'])->value('money');
                    if($bmoney + $totalmoney < 0){
                        return ['status'=>0,'msg'=>'操作失败,商家余额不足'];
                    }
                }
                \app\commons\Business::addmoney($aid,$order['bid'],$totalmoney,'收银台，订单号：'.$order['ordernum'],true,'cashdesk',$order['ordernum'],['platformMoney'=>$platformMoney,'business_lirun'=>$business_lirun]);
            }
            //会员抵扣积分兑换到余额
            if(getcustom('business_score_jiesuan')){
                if($businessDkMoney>0){
                    \app\commons\Business::addmoney($aid,$order['bid'],$businessDkMoney,t('积分').'抵扣转'.t('余额').'，收银台订单号：'.$order['ordernum'],false,'cashdesk',$order['ordernum']);
                }
                if($businessDkScore>0){
                    \app\commons\Business::addscore($aid,$order['bid'],$businessDkScore,t('积分').'抵扣到商户'.t('积分').'，收银台订单号：'.$order['ordernum']);
                }
            }
            //店铺加销量
            Db::name('business')->where('aid',$aid)->where('id',$order['bid'])->inc('sales',$totalnum)->update();
        }
    }
    public function getcommission($product,$member,$og,$commission_totalprice = 0,$num=0,$omid=0, $istc1 = 0,$istc2 = 0,
$istc3 = 0){
        if(getcustom('cashdesk_commission')){
            $ogupdate =  ['parent1' =>0,'parent2' => 0,'parent3' => 0,'parent4' => 0 ,'parent1commission' => 0,'parent2commission' => 0,'parent3commission' => 0 ];
            
            if(!$product || !$member || $commission_totalprice==0 || $product['commissionset'] ==-1){
                return  $ogupdate;
            }
            $sysset = Db::name('admin_set')->where('aid',aid)->find();
            if($product['commissionset']!=-1){
                if($sysset['fenxiao_manage_status']){
                    $commission_data = \app\commons\Fenxiao::fenxiao_jicha($sysset,$member,$product,$num,$commission_totalprice);
                }else{
                    $hasordergoods = Db::name('shop_order_goods')->where('aid',aid)->where('mid',$member['mid'])->where('status','in','1,2,3')->find();
                    if($hasordergoods){
                        $isfg = 1;
                    }else{
                        $isfg = 0;
                    }
                    $commission_data = \app\commons\Fenxiao::fenxiao($sysset,$member,$product,$num,$commission_totalprice,$isfg,$istc1,$istc2,$istc3);
                }
                $ogupdate['parent1'] = $commission_data['parent1']??0;
                $ogupdate['parent2'] = $commission_data['parent2']??0;
                $ogupdate['parent3'] = $commission_data['parent3']??0;
                $ogupdate['parent4'] = $commission_data['parent4']??0;
                $ogupdate['parent1commission'] = $commission_data['parent1commission']??0;
                $ogupdate['parent2commission'] = $commission_data['parent2commission']??0;
                $ogupdate['parent3commission'] = $commission_data['parent3commission']??0;
                $ogupdate['parent4commission'] = $commission_data['parent4commission']??0;
                $ogupdate['parent1score'] = $commission_data['parent1score']??0;
                $ogupdate['parent2score'] = $commission_data['parent2score']??0;
                $ogupdate['parent3score'] = $commission_data['parent3score']??0;
            }
            $totalcommission = 0;
            if($ogupdate['parent1'] && ($ogupdate['parent1commission'] || $ogupdate['parent1score'])){
                Db::name('member_commission_record')->insert(['aid'=>aid,'mid'=>$ogupdate['parent1'],'frommid'=>$member['id'],'orderid'=>$og['orderid'],'ogid'=>$og['id'],'type'=>'cashier','commission'=>$ogupdate['parent1commission'],'score'=>$ogupdate['parent1score'],'remark'=>'下级购买商品奖励','createtime'=>time()]);
                $totalcommission += $ogupdate['parent1commission'];
            }
            if($ogupdate['parent2'] && ($ogupdate['parent2commission'] || $ogupdate['parent2score'])){
                Db::name('member_commission_record')->insert(['aid'=>aid,'mid'=>$ogupdate['parent2'],'frommid'=>$member['id'],'orderid'=>$og['orderid'],'ogid'=>$og['id'],'type'=>'cashier','commission'=>$ogupdate['parent2commission'],'score'=>$ogupdate['parent2score'],'remark'=>'下二级购买商品奖励','createtime'=>time()]);
                $totalcommission += $ogupdate['parent2commission'];
            }
            if($ogupdate['parent3'] && ($ogupdate['parent3commission'] || $ogupdate['parent3score'])){
                Db::name('member_commission_record')->insert(['aid'=>aid,'mid'=>$ogupdate['parent3'],'frommid'=>$member['id'],'orderid'=>$og['orderid'],'ogid'=>$og['id'],'type'=>'cashier','commission'=>$ogupdate['parent3commission'],'score'=>$ogupdate['parent3score'],'remark'=>'下三级购买商品奖励','createtime'=>time()]);
                $totalcommission += $ogupdate['parent3commission'];
            }
            if($ogupdate['parent4'] && ($ogupdate['parent4commission'])){
                Db::name('member_commission_record')->insert(['aid'=>aid,'mid'=>$ogupdate['parent4'],'frommid'=>$member['id'],'orderid'=>$og['orderid'],'ogid'=>$og['id'],'type'=>'cashier','commission'=>$ogupdate['parent4commission'],'score'=>0,'remark'=>'持续推荐奖励','createtime'=>time()]);
                $totalcommission += $ogupdate['parent4commission'];
            }
            //更新order_goods
            Db::name('cashier_order_goods')->where('id',$og['id'])->update($ogupdate);
            return  $ogupdate;
        }
    }
    protected function refreshOrdernum($orderid=''){
        $date = date('ymdHis');
        $rand = rand(100000, 999999);
        $newordernum = 'C' . $date . $rand;
        Db::name('cashier_order')->where('id',$orderid)->update(['ordernum'=>$newordernum]);
    }

    protected function wlog($content){
        return ;
        $file = ROOT_PATH.'runtime/log/wxpay.txt';
        file_put_contents($file,date('Y-m-d H:i:s').PHP_EOL,FILE_APPEND);
        file_put_contents($file,$content.PHP_EOL,FILE_APPEND);
    }

   
    //现金支付（线下其他支付方式，直接更改订单状态）
    protected function cashPay($cashier_id=0,$mid=0,$couponrid=0,$userscore=0,$params=[]){
        $order = $this->getWaitOrder($cashier_id);
        if (empty($order)) {
            return $this->json(0, '无待结算订单');
        }
        $goodslist = Db::name('cashier_order_goods')->where('orderid', $order['id'])->select()->toArray();
        if (empty($goodslist)) {
            return $this->json(0, '无待结算商品');
        }
        $orderResult = $this->getOrderPrice($order,$couponrid,$userscore,$mid,$params);
        if($orderResult['status']!=1){
            return $this->json(0, $orderResult['msg']);
        }
        //抹零
        $orderup = [];
        $orderup['pre_totalprice'] = $orderResult['pre_totalprice'];
        $orderup['totalprice'] = $orderResult['totalprice'];
        $orderup['moling_money'] = $orderResult['moling_money'];
        if($orderResult['coupon_money']>0){
            $orderup['coupon_money'] = $orderResult['coupon_money'];
            $orderup['coupon_rid'] = $couponrid;
        }
        $orderup['scoredk_money'] = $orderResult['scoredk_money'];
        $orderup['leveldk_money'] = $orderResult['leveldk_money'];
        $orderup['scoredkscore'] = $orderResult['totalscore'];
        $orderup['mid'] = $mid;
        $orderup['paytime'] = time();
        $orderup['paytype'] = t('现金').'支付';
        $orderup['paytypeid'] = 0;
        $orderup['status'] = 1;
        $orderup['uid'] = $this->uid;
        if(getcustom('cashier_money_dec')){
            if($params && $params['moneyrate'] && $orderResult['dec_money']>0){
                $orderup['dec_money']      = $orderResult['dec_money'];
                $orderup['money_dec_rate'] = $orderResult['money_dec_rate'];
            }
        }
        if(getcustom('cashier_money_dec')){
            if($params && $params['overdraft_moneyrate'] && $orderResult['dec_overdraft_money']>0){
                $orderup['dec_overdraft_money']      = $orderResult['dec_overdraft_money'];
                $orderup['overdraft_money_dec_rate'] = $orderResult['overdraft_money_dec_rate'];
            }
        }
        if(getcustom('extend_staff')){
            //员工提成
            if($params && $params['staffid'] && $params['staff_commission_rate']>0){
                $orderup['staffid']               = $params['staffid'];
                $orderup['staff_commission_rate'] = $params['staff_commission_rate'];
                //计算员工提成
                $staff_commission = $params['staff_commission_rate']*$orderup['totalprice']*0.01;
                $orderup['staff_commission'] = round($staff_commission,2);
            }
        }
        Db::name('cashier_order')->where('bid', bid)->where('aid', aid)->where('id', $order['id'])->update($orderup);
        //更新收银台表
        $payorderid =\app\models\Payorder::createorder(aid, $order['bid'], $mid, 'cashier', $order['id'], $order['ordernum'], '收银台'.t('现金').'收款', $orderup['totalprice'], $orderResult['totalscore']);
        
        Db::name('payorder')->where('id',$payorderid)->update(['paytype'=>t('现金').'收款-收银台','paytypeid'=>0,'paynum'=>0,'status' =>1,'paytime' => time(),'platform' =>'cashier']);
        $this->afterPay($order['id']);
        \app\commons\Wifiprint::print(aid,'cashier',$order['id']);
        return $this->json(1, '支付成功');
    }
    //余额支付
    protected function moneyPay($cashier_id=0,$mid=0,$couponrid=0,$userscore=0,$params=[]){
        $order = $this->getWaitOrder($cashier_id);
        if (empty($order)) {
            return $this->json(0, '无待结算订单');
        }
        $scoredk_money = 0;
        //计算总价
        $goodslist = Db::name('cashier_order_goods')->where('orderid', $order['id'])->select()->toArray();
        if (empty($goodslist)) {
            return $this->json(0, '无待结算商品');
        }
        foreach ($goodslist as $k=>$v){
            if($v['protype']==1){
                //库存校验
                $gginfo = Db::name('shop_guige')->where('aid',aid)->where('id',$v['ggid'])->find();
                if($gginfo['stock']<$v['num']){
                    return $this->json(0, $v['proname'].'('.$v['ggname'].')'.'库存不足');
                }
            }
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
        if(getcustom('cashier_member_paypwd')){
            //使用密码
            $paypwd_use_status = Db::name('cashier')->where('aid',aid)->where('bid',bid)->value('paypwd_use_status');
            $paypwd = input('param.paypwd');
            //比如输入密码 且密码为空 
            if(!$paypwd && $paypwd_use_status ==1){
                return $this->json(0,'请输入正确的支付密码');
            }
            if($paypwd && md5($paypwd.$member['paypwd_rand']) != $member['paypwd']){
                return $this->json(0,'请输入正确的支付密码');
            }
        }
        $orderResult = $this->getOrderPrice($order,$couponrid,$userscore,$mid,$params);
        if($orderResult['status']!=1){
            Db::rollback();
            return $this->json(0, $orderResult['msg']);
        }
        //抹零
        $orderup = [];
        $orderup['pre_totalprice'] = $orderResult['pre_totalprice'];
        $orderup['totalprice'] = $orderResult['totalprice'];
        $orderup['moling_money'] = $orderResult['moling_money'];
        if($orderResult['coupon_money']>0){
            $orderup['coupon_money'] = $orderResult['coupon_money'];
            $orderup['coupon_rid'] = $couponrid;
        }
        $orderup['scoredk_money'] = $orderResult['scoredk_money'];
        $orderup['leveldk_money'] = $orderResult['leveldk_money'];
        $orderup['scoredkscore'] = $orderResult['totalscore'];
        $orderup['mid'] = $mid;
        $orderup['uid'] = $this->uid;
        if($orderup['coupon_money']>0){
            Db::name('coupon_record')->where('id', $couponrid)->update(['status' => 1, 'usetime' => time()]);
        }
        $totalscore = $orderResult['totalscore'];
        if(getcustom('cashier_money_dec')){
            if($params && $params['moneyrate'] && $orderResult['dec_money']>0){
                $orderup['dec_money']      = $orderResult['dec_money'];
                $orderup['money_dec_rate'] = $orderResult['money_dec_rate'];
            }
        }
        if(getcustom('cashier_overdraft_money_dec')){
            if($params && $params['overdraft_moneyrate'] && $orderResult['dec_overdraft_money']>0){
                $orderup['dec_overdraft_money']      = $orderResult['dec_overdraft_money'];
                $orderup['overdraft_money_dec_rate'] = $orderResult['overdraft_money_dec_rate'];
            }
        }
        if(getcustom('extend_staff')){
            //员工提成
            if($params && $params['staffid'] && $params['staff_commission_rate']>0){
                $orderup['staffid']               = $params['staffid'];
                $orderup['staff_commission_rate'] = $params['staff_commission_rate'];
                //计算员工提成
                $staff_commission = $params['staff_commission_rate']*$orderup['totalprice']*0.01;
                $orderup['staff_commission'] = round($staff_commission,2);
            }
        }
      
        if($member['money'] < $orderup['totalprice']){
            Db::rollback();
            return $this->json(0,t('余额').'不足,请充值');
        }
        if(getcustom('cashier_money_dec')){
            if($params && $params['moneyrate'] && $orderResult['dec_money']>0){
                if($member['money'] < $orderup['totalprice']+$orderResult['dec_money']){
                    Db::rollback();
                    return $this->json(0,t('余额').'不足,请充值');
                }
            }
        }
        if($orderup['totalprice'] > 0){
            //减去会员的余额
            \app\commons\Member::addmoney(aid,$mid,-$orderup['totalprice'],'收银台买单,订单号: '.$order['ordernum']);
        }
        Db::name('cashier_order')->where('id', $order['id'])->update($orderup);
        $payorderid = \app\models\Payorder::createorder(aid, $order['bid'], $mid, 'cashier', $order['id'], $order['ordernum'], '收银台'.t('余额').'收款', $orderup['totalprice'], $totalscore);
        $res = Db::name('payorder')->where('id',$payorderid)->update(['paytype'=>'余额收款-收银台','paytypeid'=>1,'paynum'=>$orderup['paynum'],'status' =>1,'paytime' => time(),'platform' =>'cashier']);
        if($res){
            //标记已支付
            Db::name('cashier_order')->where('id',$order['id'])->update(['status'=>1,'paytime'=>time(),'paytype'=>'余额收款-收银台','paytypeid'=>1,'paynum'=>$orderup['paynum'],'platform'=>'cashdesk']);
            Db::commit();
            $this->afterPay($order['id']);
            \app\commons\Wifiprint::print(aid,'cashier',$order['id']);
            return $this->json(1,'付款成功');
        }else{
            Db::rollback();
            return $this->json(0,'付款失败');
        }
    }
    //随行付支付
    protected function sxPay($cashier_id=0,$mid=0,$couponrid=0,$userscore=0,$params=[]){
        if(getcustom('cashdesk_sxpay')) {
            $auth_code = input('param.auth_code');
            //过滤capslock
            $auth_code = str_replace('capslock', '', str_replace(' ', '', strtolower($auth_code)));
            //验证code是否正确
            if (empty($auth_code)) {
                return $this->json(0, '无效的付款码' );
            }
            $order = $this->getWaitOrder($cashier_id);
            if (empty($order)) {
                return $this->json(0, '无待结算订单');
            }
            $goodslist = Db::name('cashier_order_goods')->where('orderid', $order['id'])->select()->toArray();
            if (empty($goodslist)) {
                return $this->json(0, '无待结算商品');
            }
            foreach ($goodslist as $k => $v) {
                if ($v['protype'] == 1) {
                    //库存校验
                    $gginfo = Db::name('shop_guige')->where('aid', aid)->where('id', $v['ggid'])->find();
                    if ($gginfo['stock'] < $v['num']) {
                        return $this->json(0, $v['proname'] . '(' . $v['ggname'] . ')' . '库存不足');
                    }
                }
            }
            $orderResult = $this->getOrderPrice($order, $couponrid, $userscore, $mid,$params);
            if ($orderResult['status'] != 1) {
                return $this->json(0, $orderResult['msg']);
            }
            $orderup= [];
            $orderup['status'] = 1;
            $orderup['paytype'] = '收银台随行付当面付';
            $orderup['paytypeid'] = 81;
            $orderup['paytime'] = time();
            $orderup['pre_totalprice'] = $orderResult['pre_totalprice'];
            $orderup['totalprice'] = $orderResult['totalprice'];
            $orderup['moling_money'] = $orderResult['moling_money'];
            if($orderResult['coupon_money']>0){
                $orderup['coupon_money'] = $orderResult['coupon_money'];
                $orderup['coupon_rid'] = $couponrid;
            }
            $orderup['scoredk_money'] = $orderResult['scoredk_money'];
            $orderup['leveldk_money'] = $orderResult['leveldk_money'];
            $orderup['scoredkscore'] = $orderResult['totalscore'];
            $orderup['uid'] = $this->uid;
            $orderup['mid'] = $mid;
            $orderup['platform'] = 'cashier';
            $wxpaymoney = $orderResult['totalprice'];
            if($wxpaymoney > 0){
                $set = Db::name('admin_set')->where('aid',aid)->find();
                $return = Sxpay::build_scan(aid,bid,$set['name'].'-当面付',$order['ordernum'],$wxpaymoney,'cashdesk',$auth_code);
                if($return['status'] ==1){
                    $orderup['paynum'] = $return['data']['trade_no'];
                    $payorderid = \app\models\Payorder::createorder(aid, $order['bid'], $mid, 'cashier', $order['id'], $order['ordernum'], '收银台买单', $orderup['totalprice'], $orderResult['totalscore']);
                }else{
                    $payorderid = \app\models\Payorder::createorder(aid, $order['bid'], $mid, 'cashier', $order['id'], $order['ordernum'], '收银台买单', $orderup['totalprice'], $orderResult['totalscore']);
                    $transaction_id = $this->sxpayTradequery($payorderid);
                    if($transaction_id){
                        $orderup['paynum'] = $transaction_id;
                    } else{
                        return $this->json(0,$return['msg']);
                    }
                }
            }else{
                $orderup= [];
                $orderup['status'] = 1;
                $orderup['paytype'] = '无须支付';
                $orderup['paynum'] = '';
                $orderup['paytime'] = time();
                $orderup['pre_totalprice'] = $orderResult['pre_totalprice'];
                $orderup['totalprice'] = $orderResult['totalprice'];
                $orderup['moling_money'] = $orderResult['moling_money'];
                $orderup['coupon_money'] = $orderResult['coupon_money'];
                $orderup['scoredk_money'] = $orderResult['scoredk_money'];
                $orderup['leveldk_money'] = $orderResult['leveldk_money'];
                $orderup['scoredkscore'] = $orderResult['totalscore'];
                $orderup['uid'] = $this->uid;
                $orderup['mid'] = $mid;
            }
            if(getcustom('cashier_money_dec')){
                if($params && $params['moneyrate'] && $orderResult['dec_money']>0){
                    $orderup['dec_money']      = $orderResult['dec_money'];
                    $orderup['money_dec_rate'] = $orderResult['money_dec_rate'];
                }
            }
            if(getcustom('cashier_overdraft_money_dec')){
                if($params && $params['overdraft_moneyrate'] && $orderResult['dec_overdraft_money']>0){
                    $orderup['dec_overdraft_money']      = $orderResult['dec_overdraft_money'];
                    $orderup['overdraft_money_dec_rate'] = $orderResult['overdraft_money_dec_rate'];
                }
            }
            if(getcustom('extend_staff')){
                //员工提成
                if($params && $params['staffid'] && $params['staff_commission_rate']>0){
                    $orderup['staffid']               = $params['staffid'];
                    $orderup['staff_commission_rate'] = $params['staff_commission_rate'];
                    //计算员工提成
                    $staff_commission = $params['staff_commission_rate']*$orderup['totalprice']*0.01;
                    $orderup['staff_commission'] = round($staff_commission,2);
                }
            }
            Db::name('payorder')->where('id',$payorderid)->update(['paytype'=>'随行付收款-收银台','paytypeid'=>$orderup['paytypeid'],'paynum'=>$orderup['paynum'],'status' =>1,'paytime' => time(),'platform' =>'cashier']);
            $res = Db::name('cashier_order')->where('id',$order['id'])->update($orderup);
            if($res){
                //打印
                \app\commons\Wifiprint::print(aid,'cashier',$order['id']);
                $this->afterPay($order['id']);
                return $this->json(1,'支付成功');
            }else{
                return $this->json(0,'支付失败！！！');
            }
        }
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
    //挂账支付
    protected function guazhangPay($cashier_id,$mid=0,$couponrid=0,$userscore=0,$params=[]){
       if(getcustom('member_overdraft_money')){
            $order = $this->getWaitOrder($cashier_id);
            if (empty($order)) {
                return $this->json(0, '无待结算订单');
            }
            $scoredk_money = 0;
            //计算总价
            $goodslist = Db::name('cashier_order_goods')->where('orderid', $order['id'])->select()->toArray();
            if (empty($goodslist)) {
                return $this->json(0, '无待结算商品');
            }
            foreach ($goodslist as $k=>$v){
                if($v['protype']==1){
                    //库存校验
                    $gginfo = Db::name('shop_guige')->where('aid',aid)->where('id',$v['ggid'])->find();
                    if($gginfo['stock']<$v['num']){
                        return $this->json(0, $v['proname'].'('.$v['ggname'].')'.'库存不足');
                    }
                }
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
            $orderResult = $this->getOrderPrice($order,$couponrid,$userscore,$mid,$params);
            if($orderResult['status']!=1){
                Db::rollback();
                return $this->json(0, $orderResult['msg']);
            }
            $paytypeid = 38;
            //抹零
            $orderup = [];
            $orderup['paytypeid'] = $paytypeid;
            $orderup['paytype'] = t('信用额度').'支付';
            $orderup['pre_totalprice'] = $orderResult['pre_totalprice'];
            $orderup['totalprice'] = $orderResult['totalprice'];
            $orderup['moling_money'] = $orderResult['moling_money'];
            if($orderResult['coupon_money']>0){
                $orderup['coupon_money'] = $orderResult['coupon_money'];
                $orderup['coupon_rid'] = $couponrid;
            }
            $orderup['scoredk_money'] = $orderResult['scoredk_money'];
            $orderup['leveldk_money'] = $orderResult['leveldk_money'];
            $orderup['scoredkscore'] = $orderResult['totalscore'];
            $orderup['mid'] = $mid;
            $orderup['uid'] = $this->uid;
            if($orderup['coupon_money']>0){
                Db::name('coupon_record')->where('id', $couponrid)->update(['status' => 1, 'usetime' => time()]);
            }
            $totalscore = $orderResult['totalscore'];
            if(getcustom('cashier_money_dec')){
                if($params && $params['moneyrate'] && $orderResult['dec_money']>0){
                    $orderup['dec_money']      = $orderResult['dec_money'];
                    $orderup['money_dec_rate'] = $orderResult['money_dec_rate'];
                }
            }
            if(getcustom('cashier_overdraft_money_dec')){
                if($params && $params['overdraft_moneyrate'] && $orderResult['dec_overdraft_money']>0){
                    $orderup['dec_overdraft_money']      = $orderResult['dec_overdraft_money'];
                    $orderup['overdraft_money_dec_rate'] = $orderResult['overdraft_money_dec_rate'];
                }
            }
            if(getcustom('extend_staff')){
                //员工提成
                if($params && $params['staffid'] && $params['staff_commission_rate']>0){
                    $orderup['staffid']               = $params['staffid'];
                    $orderup['staff_commission_rate'] = $params['staff_commission_rate'];
                    //计算员工提成
                    $staff_commission = $params['staff_commission_rate']*$orderup['totalprice']*0.01;
                    $orderup['staff_commission'] = round($staff_commission,2);
                }
            }
            Db::name('cashier_order')->where('id', $order['id'])->update($orderup);
            $payorder =  Db::name('payorder')->where('aid',aid)->where('bid',$order['bid'])->where('ordernum',$order['ordernum'])->where('type','cashier')->where('mid',$mid)->where('status',0)->find();
            if($payorder){
                $payorderid = $payorder['id'];
            }else{
                $payorderid = \app\models\Payorder::createorder(aid, $order['bid'], $mid, 'cashier', $order['id'], $order['ordernum'], '收银台买单', $orderup['totalprice'], $totalscore);
            }
            
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
                \app\commons\Member::addOverdraftMoney(aid,$mid,-$orderup['totalprice'],'收银台买单,订单号: '.$order['ordernum']);
            }
    
            $res = \app\models\Payorder::payorder($payorderid,t('信用额度').'支付',$paytypeid,'');
            if($res && $res['status']==1){
                Db::commit();
                $this->afterPay($order['id']);
                \app\commons\Wifiprint::print(aid,'cashier',$order['id']);
                return $this->json(1,'付款成功');
            }else{
                Db::rollback();
                return $this->json(0,'付款失败');
            }
       }
    }
    //待支付订单
    public function getWaitPayOrder()
    {
        $cashier_id = input('param.cashier_id/d', 0);
        $remove_zero = input('param.remove_zero/d', 0);
        $mid = input('param.mid/d', 0); //会员ID
        $order = $this->getWaitOrder($cashier_id);
        if (empty($order)) {
            return $this->json(1, '无待结算订单', '');
        }
        $set = Db::name('cashier')->where('id', $cashier_id)->where('bid', bid)->find();
        if ($order['remove_zero'] != $remove_zero) {
            Db::name('cashier_order')->where('id', $order['id'])->update(['remove_zero' => $remove_zero, 'remove_zero_length' => $set['remove_zero_length'] ?? 0]);
        }
        $goodslist = Db::name('cashier_order_goods')->order('createtime desc')->where('orderid', $order['id'])->select()->toArray();
        //待结算订单，读取库存
        $discount_totalmoney = 0;
        $totalprice = 0;
        if (empty($goodslist)) $goodslist = [];
        foreach ($goodslist as $gk => $goods) {
            $stock = 0;
            if ($goods['protype'] == 1) {
                $stock = Db::name('shop_guige')->where('proid', $goods['proid'])->where('id', $goods['ggid'])->value('stock');
            }
          
            if($mid && $goods['is_gj'] == 0){
                $goodslist[$gk]['sell_price'] = $goods['sell_price'] =  $this->getVipPrice($goods['proid'],$mid,
                    $goods['ggid'],$goods['sell_price']);
            }
            $gtotalprice = $goods['sell_price'] * $goods['num'];
            $goodslist[$gk]['totalprice'] = round($gtotalprice,2);
            $totalprice = $totalprice + $gtotalprice;
            $goodslist[$gk]['stock'] = $stock ?? 0;
        }
        $order['prolist'] = $goodslist ?? [];
        $order['remove_zero'] = $remove_zero;
        $order['remove_zero_length'] = $set['remove_zero_length'] ?? 0;
        if ($remove_zero == 1) {
            $zeroinfo = $this->removeZero($totalprice,$cashier_id);
            $order['totalprice'] = $zeroinfo['totalprice'];
            $order['discount_money'] = $zeroinfo['moling_money'];
        }else{
            $order['totalprice'] = round($totalprice - $discount_totalmoney, 2);
            $order['discount_money'] = $discount_totalmoney;
        }
        $order['scan_membercode_pay'] = 0;
        if(getcustom('cashier_scan_membercode_pay')){
            $order['scan_membercode_pay'] = 1;
        }
        return $this->json(1, 'ok', $order);
    }
    //抹零
    protected function removeZero($totalprice,$cashier_id=0){
        if($cashier_id){
            $set = Db::name('cashier')->where('id', $cashier_id)->where('bid', bid)->find();
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
     * 获取收银台订单信息
     * 待结算status=0
     * 已结算订单status=1
     * 挂单status=2
     */
    public function getCashierOrder()
    {
        $status = input('param.status/d', 1);
//        $bid = input('param.bid/d',0);
        $cashier_id = input('param.cashier_id/d', 0);
        $keyword = input('param.keyword', 0);
        $page = input('param.page/d', 1);
        $limit = input('param.limit/d', 10);
        $where = [];
        $where[] = ['o.aid','=',aid];
        $where[] = ['o.status' ,'=', $status];
        $where[] = ['o.bid','=',bid];
        $where[] = ['o.cashier_id','=',$cashier_id];
        if($keyword){
            $where[] = ['g.proname|g.barcode','like','%'.$keyword.'%'];
        }
        if($status==2){
            $orderby = 'hangup_time desc';
        }else{
            $orderby = 'id desc';
        }
        $lists = Db::name('cashier_order')->alias('o')->join('cashier_order_goods g','o.id=g.orderid')->group('o.id')->where($where)->field('o.*')->order($orderby)->page($page,$limit)->select()->toArray();
        if (empty($lists)) $lists = [];
        foreach ($lists as $k => $order) {
            if($order['uid'] > 0){
                $admin_user_name = Db::name('admin_user')->where('id',$order['uid'])->value('un');
                $lists[$k]['admin_user'] = $admin_user_name??'超级管理员';
            }else{
                $lists[$k]['admin_user'] = '超级管理员';
            }
            $goodslist = Db::name('cashier_order_goods')->where('orderid', $order['id'])->select()->toArray();
            if (empty($goodslist)) $goodslist = [];
            $totalprice = 0;
            foreach ($goodslist as $gk => $goods) {
                $goodslist[$gk]['stock'] = 0;
                if($status==2){
                    $stock = 0;
                    if ($goods['protype'] == 1) {
                        $stock = Db::name('shop_guige')->where('proid', $goods['proid'])->where('id', $goods['ggid'])->value('stock');
                    }
                    $goods_totalprice = round($goods['sell_price'] * $goods['num'],2);
                    $totalprice = $totalprice+$goods_totalprice;
                    $goodslist[$gk]['stock'] = $stock ?? 0;
                }
            }
            if($status==2){
                $lists[$k]['totalprice']  = $totalprice;
            }
            $lists[$k]['hangup_time'] = '';
            if ($order['hangup_time']) {
                $lists[$k]['hangup_time'] = date('Y-m-d H:i:s', $order['hangup_time']);
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
        }
        return $this->json(1, 'ok', $lists);
    }

    public function getOrderDetail(){
        $orderid = input('param.orderid/d');
        $order = Db::name('cashier_order')->where('aid',aid)->where('bid',bid)->where('id', $orderid)->find();
        if(empty($order)){
            return $this->json(0,'该订单不存在');
        }
        $order['createtime'] = date("Y-m-d H:i:s",$order['createtime']);
        $order['paytime'] = $order['paytime']?date("Y-m-d H:i:s",$order['paytime']):'';
        $ordergoods = Db::name('cashier_order_goods')->where('orderid',$orderid)->select()->toArray();
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
     * @description 删除订单
     */
    public function delCashierOrder()
    {
        $orderid = input('param.orderid/d', 0);
        $res = Db::name('cashier_order')->where('aid',aid)->where('bid',bid)->where('id', $orderid)->delete();
        if($res){
            $resg = Db::name('cashier_order_goods')->where('orderid', $orderid)->delete();
        }
        return $this->json(1, '删除成功');
    }

    /**
     * @description 收银台商品数量增减
     */
    public function cashierChangeNum()
    {
        $cashier_id = input('param.cashier_id/d', 0);
        $id = input('param.id', 0);
        $num = input('param.num', 0);
        $order = $this->getWaitOrder($cashier_id);
        if (empty($order)) {
            return $this->json(0, '没有待结算订单不支持该操作');
        }
        $ordergoods = Db::name('cashier_order_goods')->where('orderid', $order['id'])->where('id', $id)->find();
        if (empty($ordergoods)) {
            return $this->json(0, '数据有误');
        }
        $product = Db::name('shop_product')->where('aid',aid)->where('bid',bid)->where('id',$ordergoods['proid'])->find();
        if($product['perlimitdan'] > 0 && $num > $product['perlimitdan']){ //每单限购
            return json(['status'=>0,'msg'=>$product['name'].'每单限购'.$product['perlimitdan'].'份']);
        }
        $delnum = 1;
        if(getcustom('cashier_num_weishu')){
            $delnum = 0.01;
        }
        if ($num < $delnum) {
            //删除该商品
            Db::name('cashier_order_goods')->where('orderid', $order['id'])->where('id', $id)->delete();
        } else {
            Db::name('cashier_order_goods')->where('orderid', $order['id'])->where('id', $id)->update(['num' => $num]);
        }
        return $this->json(1, 'ok');
    }

    /**
     * @description 收银台商品数量增减
     */
    public function cashierChangePrice()
    {
        $cashier_id = input('param.cashier_id/d', 0);
        $id = input('param.id', 0);
        $price = input('param.price', 0);
        $order = $this->getWaitOrder($cashier_id);
        if (empty($order)) {
            return $this->json(0, '没有待结算订单不支持该操作');
        }
        Db::name('cashier_order_goods')->where('orderid', $order['id'])->where('id', $id)->update(['sell_price' => $price,'is_gj' => 1]);
        return $this->json(1, 'ok');
    }

    /**
     * 修改订单备注
     */
    public function cashierChangeRemark()
    {
        $orderid = input('param.orderid');
        $remark = input('param.remark');
        Db::name('cashier_order')->where('bid', bid)->where('aid', aid)->where('id', $orderid)->update(['remark' => $remark]);
        return $this->json(1, '备注修改成功');
    }

    /**
     * 修改订单状态
     */
    public function cashierChangeStatus()
    {
        $orderid = input('param.orderid');
        Db::name('cashier_order')->where('bid', bid)->where('aid', aid)->where('id', $orderid)->update(['status' => 1]);
        return $this->json(1, '修改成功');
    }

    
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
        if(getcustom('cashier_member_paypwd')){
            if($this->user['bid'] ==0 && ($this->auth_data =='all' || in_array('Member/index',$this->auth_data) || in_array('Member/edit',$this->auth_data))){
                $paypwd = input('param.paypwd','');
                if(!$paypwd){
                    $cashier = Db::name('cashier')->where('aid',aid)->where('bid',bid)->find();
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
        return json(['status'=>1,'msg'=>'会员注册成功']);
    }
    //修改会员支付密码
    public function editMemberPaypwd(){
        if(getcustom('cashier_member_paypwd')){
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
        $field = 'id,nickname,headimg,realname,money,score,tel,createtime,birthday,remark,levelid';
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
            ->where('cr.type','in',[1,2,10])
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
    //获取别名列表
    public function getBieming(){
        $data = [
            'coupon' => t('优惠券'),
            'score' => t('积分'),
            'yue' => t('余额'),
            'overdraft_money' => t('信用额度'),
        ] ;
        return  json(['status' => 1,'data' =>$data]);
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
        $where[] = ['type', 'in', [1,2,10]];
        $datalist = Db::name('coupon_record')->field('id,bid,type,limit_count,used_count,couponid,couponname,money,minprice,discount,from_unixtime(starttime,"%Y-%m-%d %H:%i") starttime,from_unixtime(endtime,"%Y-%m-%d %H:%i") endtime,from_unixtime(usetime) usetime,from_unixtime(createtime) createtime,status')
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
    
    //待收银订单status=0
    protected function getWaitOrder($cashier_id = 0)
    {
        $where = [];
        $where['status'] = 0;
        $where['aid'] = aid;
        $where['bid'] = bid;
        $where['cashier_id'] = $cashier_id;
        $order = Db::name('cashier_order')->where($where)->find();
        if (empty($order)) $order = [];
        return $order;
    }

    //打印小票
    public function print(){
        $orderid = input('post.orderid/d');
        //查找该商家是否有配置的打印机
        $machine = Db::name('wifiprint_set')->where('aid',aid)->where('bid',bid)->whereNotNull('client_id')->find();
        if(!$machine){
            return  $this->json(0,'未配置打印机');
        }
        $orderinfo  = Db::name('cashier_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->find();
        if(empty($orderinfo)){
            return  $this->json(0,'订单信息有误');
        }
        $rs = \app\commons\Wifiprint::print(aid,'cashier',$orderid,0);
        return json($rs);
    }

    protected function getOrderPrice($order=[],$couponrid=0,$userscore=0,$mid=0,$params=[]){
        //计算总价
        $goodslist = Db::name('cashier_order_goods')->where('orderid', $order['id'])->select()->toArray();
        if (empty($order) || empty($goodslist)) {
            return ['status'=>0,'msg'=>'暂无待结算订单'];
        }
        $totalprice = 0;
        $proids = [];
        $proPriceArr = [];//按商品proid分组的总金额
        if(getcustom('yx_hongbao_queue_free')){
            $is_use_youhui = 1;
            $hongbao_queue_set = Db::name('hongbao_queue_free_set')->where('aid',aid)->field('productids,gettj')->find();
            $hongbao_join_proids = $hongbao_queue_set['productids'];
            $hongbao_join_proids_arr = explode(',',$hongbao_join_proids);
            $hongbao_gettj = explode(',',$hongbao_queue_set['gettj']);
        }
        $needzkproduct_price = 0;
        foreach ($goodslist as $og) {
            //如果开启会员
            if($mid && $og['is_gj'] == 0){
                $og['sell_price']=  $this -> getVipPrice($og['proid'],$mid,$og['ggid'],$og['sell_price']);
            }
            //读取最新的价格
            $goods_totalprice = round($og['sell_price'] * $og['num'],2);
            if($goods_totalprice>0){
                Db::name('cashier_order_goods')->where('id',$og['id'])->update(['totalprice'=>$goods_totalprice,'sell_price' => $og['sell_price']]);
            }
            $totalprice = $totalprice + $goods_totalprice;
            if ($og['protype'] == 1) {
                $proids[] = $og['proid'];
                if(isset($proPriceArr[$og['proid']])){
                    $proPriceArr[$og['proid']] = $proPriceArr[$og['proid']] + $goods_totalprice;
                }else{
                    $proPriceArr[$og['proid']] = $goods_totalprice;
                }
            }
            $product = Db::name('shop_product')->where('aid',$og['aid'])->where('id',$og['proid'])->find();
            if($og['protype'] ==0 || ($product['lvprice']==0 && $product['no_discount'] == 0)){ //未开启会员价
                $needzkproduct_price += $og['sell_price'] * $og['num'];
            }
            if(getcustom('yx_hongbao_queue_free')){
                $member_levelid = Db::name('member')->where('id',$mid)->value('levelid');
                if(in_array($og['proid'],$hongbao_join_proids_arr) && (in_array($member_levelid,$hongbao_gettj) || in_array(-1,$hongbao_gettj))) $is_use_youhui = 0;
            }
        }
        $pre_totalprice = $totalprice;
        $cidarr = [];
        $proArr = [];
        if ($proids) {
            $prolist = Db::name('shop_product')->where('bid', bid)->where('id', 'in', $proids)->field('id,cid,scoredkmaxset,scoredkmaxval,scoredkmaxval')->select()->toArray();
            foreach ($prolist as $pk=>$pv){
                if($pv['cid']>0) $cidarr[] = $pv['cid'];
                $proArr[$pv['id']] = $pv;
            }
        }
        //优惠券
        $coupon_money = 0;
        $scoredk_money = 0;
        $scoretotal = 0;
        $leveldk_money = 0;
        $moling_money = 0;
        $dec_money     = 0;
        $money_dec_rate= 0;
        //会员折扣
        if ($mid) {
            $member = Db::name('member')->where('id', $mid)->where('aid', aid)->find();
            $adminset = Db::name('admin_set')->where('aid', aid)->find();
            $userlevel = Db::name('member_level')->where('aid', aid)->where('id', $member['levelid'])->find();
            $level_discount = $userlevel['discount'];
            if (is_numeric($level_discount) && $level_discount<10) {
//                $leveldk_money = round($totalprice * (10-$level_discount) * 0.1, 2);
                $leveldk_money = round($needzkproduct_price * (10-$level_discount) * 0.1, 2);
                if(getcustom('yx_hongbao_queue_free')){
                    if($is_use_youhui ==0) $leveldk_money=0;
                }
                $totalprice = $totalprice - $leveldk_money;
            }
            //积分抵扣
            if($userscore && $adminset['score2money']>0){
                //如果商品单独设置了积分抵扣
                foreach ($proPriceArr as $proid=>$proTotalPrice) {
                    $proinfo = isset($proArr[$proid]) ? $proArr[$proid] : [];
                    if (empty($proinfo) || $proinfo['scoredkmaxset'] == '-1') {
                        //积分不抵扣
                        continue;
                    }
                    if ($proinfo['scoredkmaxset'] == 1 && $proinfo['scoredkmaxval'] > 0) {
                        $exchangeRate = $proinfo['scoredkmaxval'];
                        $scoredk_promoney = round($proTotalPrice * $exchangeRate * 0.01,2);
                    } elseif ($proinfo['scoredkmaxset'] == 2 && $proinfo['scoredkmaxval'] > 0) {
                        $scoredk_promoney = $proinfo['scoredkmaxval'];
                    } else {
                        $exchangeRate = $adminset['scoredkmaxpercent'];//按系统比例
                        if($exchangeRate > 0 && $exchangeRate <= 100) {
                            $scoredk_promoney = round($proTotalPrice * $exchangeRate * 0.01,2);
                        }else{
                            $scoredk_promoney = 0;
                        }
                    }
                    $scoredk_money += $scoredk_promoney;
                }
                $scoredk_money = dd_money_format($scoredk_money,$this->score_weishu);
                if ($adminset['scoredkmaxpercent'] > 0 && $adminset['scoredkmaxpercent'] <= 100) {
                    $scoreMaxDk = round($totalprice * $adminset['scoredkmaxpercent'] * 0.01, 2);
                } else {
                    $scoreMaxDk = $totalprice;
                }
                $scoredk_member_money = round($member['score'] * $adminset['score2money'], 2);
                $scoredk_money = min($scoreMaxDk, $scoredk_money,$scoredk_member_money);
                $scoretotal = $scoredk_money / $adminset['score2money'];
                $scoretotal = dd_money_format($scoretotal,$this->score_weishu);
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
                        return ['status'=>0,'msg'=>'该' . t('优惠券') . '指定商品可用'];
                    }
                    $thistotalprice = 0;
                    foreach ($goodslist as $k2 => $product) {
                        if (in_array($product['proid'],$productids)){
                            $thistotalprice += $product['sell_price'] * $product['num'];
                        }
                    }
                    if ($thistotalprice < $couponinfo['minprice']) {
                        return ['status'=>0,'msg'=>'该' . t('优惠券') . '指定商品未达到' . $couponinfo['minprice'] . '元'];
                    }
                }
               
                if ($couponinfo['fwtype'] == 1) {//指定类目可用
                    $categoryids = explode(',', $couponinfo['categoryids']);
                    $categoryids1 = Db::name('shop_category')->where('pid', 'in', $categoryids)->select()->toArray();
                    if (empty($categoryids1)) $categoryids1 = [];
                    $categoryids = array_merge($categoryids, $categoryids1);
                    $cids = array_values($cidarr);
                    if (!array_intersect($cids, $categoryids)) {
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
                    $coupon_money += $couponrecord['money'];
                }elseif( $couponinfo['type']==10){ //折扣券
                    
                    if ($couponinfo['fwtype'] == 1 || $couponinfo['fwtype'] == 2) {
                        $coupon_money += $thistotalprice *   (100 - $couponrecord['discount']) * 0.01;
                    } else {
                        $coupon_money += $totalprice *  (100 - $couponrecord['discount']) * 0.01;
                    }
                    if ($coupon_money > $totalprice) $coupon_money = $totalprice;
                }else{
                    $coupon_money += $couponrecord['money'];
                }
                
                if ($coupon_money > $totalprice) $coupon_money = $totalprice;
                if(getcustom('yx_hongbao_queue_free')){
                    if($is_use_youhui ==0) $coupon_money=0;
                }
                $totalprice =  round($totalprice - $coupon_money, 2);
            }

            //信用额度抵扣
            $dec_overdraft_money = 0;
            $overdraft_money_dec_rate = 0;
            if(getcustom('cashier_overdraft_money_dec')){
                if($params){
                    $overdraft_moneyrate = $params['overdraft_moneyrate']?true:false;

                    //是否开启余额抵扣
                    $overdraft_moneydec = false;                    
                    if(empty(bid)){
                        $adminset = Db::name('admin_set')->where('aid',aid)->field('overdraft_money_dec,overdraft_money_dec_rate')->find();
                        $overdraft_money_dec_rate = 0;//抵扣比例
                        if($adminset['overdraft_money_dec'] && $adminset['overdraft_money_dec_rate']>0){
                            $overdraft_moneydec = true;
                            $overdraft_money_dec_rate = $adminset['overdraft_money_dec_rate'];
                        }
                    }else{
                        //查询商户余额抵扣比例
                        $business = Db::name('business')->where(['aid'=>aid,'id'=>bid])->field('overdraft_money_dec,overdraft_money_dec_rate')->find();
                        if($business && $business['overdraft_money_dec'] && $business['overdraft_money_dec_rate']>0){
                            $overdraft_moneydec = true;
                            $overdraft_money_dec_rate = $business['overdraft_money_dec_rate'];
                        }
                    }
                    
                    //计算抵扣
                    if($overdraft_moneydec && $overdraft_money_dec_rate && $totalprice > 0){
                        $dec_overdraft_money  = $pre_totalprice*$overdraft_money_dec_rate/100;
                        $dec_overdraft_money  = round($dec_overdraft_money,2);
                        //有额度限制
                        if($member['open_overdraft_money_radio'] != 1){
                            $open_overdraft_money = $member['open_overdraft_money']??0;
                            $limit_money = $member['limit_overdraft_money']??0;                    
                            //减掉授权额度
                            if($open_overdraft_money == 0 && $limit_money>0){
                                $use = $limit_money - ($member['overdraft_money']*-1);//可用抵扣接
                                if($use < $dec_overdraft_money){
                                    $dec_overdraft_money = $use;
                                }
                            }
                            //高于支付金额的按照支付金额
                            if($dec_overdraft_money > $totalprice){
                                $dec_overdraft_money = $totalprice;
                            }
                        }
                    }

                    //选择后判断
                    if($overdraft_moneyrate){
                        
                        //如果是支付时，使用前面传来的抵扣余额数值
                        if($params['ispay']){
                            if(!$overdraft_moneydec || !$overdraft_money_dec_rate){
                                return ['status'=>0,'msg'=>t('信用额度').'抵扣未开启'];
                            }
                            $dec_overdraft_moneyp = $params['dec_overdraft_money']>0?$params['dec_overdraft_money']:0;
                            if($dec_overdraft_moneyp > $dec_overdraft_money){
                                return ['status'=>0,'msg'=>t('信用余额').'或抵扣比例发生变动，抵扣数额不足'];
                            }
                            //$dec_overdraft_money = $dec_overdraft_moneyp;
                        }

                        $totalprice -= $dec_overdraft_money;
                        $totalprice = round($totalprice, 2);
                    }
                }
                
            }


            if(getcustom('cashier_money_dec')){
                if($params){

                    $moneyrate = $params['moneyrate']?true:false;

                    //是否开启余额抵扣
                    $moneydec = false;
                    $money_dec_rate = 0;
                    if(empty(bid)){
                        $adminset = Db::name('admin_set')->where('aid',aid)->field('money_dec,money_dec_rate')->find();
                        $money_dec_rate = 0;//抵扣比例
                        if($adminset['money_dec'] && $adminset['money_dec_rate']>0){
                            $moneydec = true;
                            $money_dec_rate = $adminset['money_dec_rate'];
                        }
                    }else{
                        //查询商户余额抵扣比例
                        $business = Db::name('business')->where(['aid'=>aid,'id'=>bid])->field('money_dec,money_dec_rate')->find();
                        if($business && $business['money_dec'] && $business['money_dec_rate']>0){
                            $moneydec = true;
                            $money_dec_rate = $business['money_dec_rate'];
                        }
                    }

                    $dec_money = 0;
                    //计算余额抵扣
                    if($moneydec && $money_dec_rate && $totalprice > 0){
                        $dec_money  = $pre_totalprice*$money_dec_rate/100;
                        $dec_money  = round($dec_money,2);
                        if($dec_money>= $member['money']){
                            $dec_money = $member['money'];
                        }
                        //高于支付金额的按照支付金额
                        if($dec_money > $totalprice){
                            $dec_money = $totalprice;
                        }
                    }

                    //选择后判断
                    if($moneyrate){

                        if(!$moneydec || !$money_dec_rate){
                            return ['status'=>0,'msg'=>t('余额').'抵扣未开启'];
                        }

                        //如果是支付时，使用前面传来的抵扣余额数值
                        if($params['ispay']){
                            $dec_moneyp = $params['dec_money']>0?$params['dec_money']:0;
                            if($dec_moneyp > $dec_money){
                                return ['status'=>0,'msg'=>t('余额').'或抵扣比例发生变动，抵扣数额不足'];
                            }
                            //验证余额是否足够
                            if($dec_moneyp && $dec_moneyp > $member['money']){
                                return ['status'=>0,'msg'=>t('余额').'不足'];
                            }
                            $dec_money = $dec_moneyp;
                        }

                        $totalprice -= $dec_money;
                        $totalprice = round($totalprice, 2);
                    }
                }
                
            }

        }
        //抹零
        if($order['remove_zero']){
            $zeroinfo = $this->removeZero($totalprice,$order['cashier_id']);
            $moling_money = $zeroinfo['moling_money']??0;
            $totalprice = $totalprice - $moling_money;
        }
        return [
            'status'=>1,
            'pre_totalprice' =>dd_money_format($pre_totalprice),
            'totalprice' =>dd_money_format($totalprice),
            'moling_money'=>dd_money_format($moling_money),
            'coupon_money'=>dd_money_format($coupon_money),
            'leveldk_money'=>dd_money_format($leveldk_money),
            'scoredk_money'=>dd_money_format($scoredk_money),
            'totalscore' => $scoretotal??0,
            'dec_overdraft_money'      => $dec_overdraft_money,
            'overdraft_money_dec_rate' => $overdraft_money_dec_rate,
            'dec_money'      => $dec_money,
            'money_dec_rate' => $money_dec_rate,
            'needzkproduct_price' => $needzkproduct_price,
        ];
    }
    //获取order_goods的真实付款价格
    protected function getOgRealPrice($order=[],$ogid){
        //计算总价
        $ogdata = Db::name('cashier_order_goods')->where('id', $ogid)->find();
        $coupon_money = 0;
        $leveldk_money = 0;
        $scoredk_money = 0;
        if($ogdata){
            if($order['coupon_money']){
                $coupon_money = dd_money_format($ogdata['totalprice']/$order['pre_totalprice'] * $order['coupon_money']); 
            }
            if($order['leveldk_money']){
                $leveldk_money = dd_money_format($ogdata['totalprice']/$order['pre_totalprice'] * $order['leveldk_money']);
                  
            }
            if($order['scoredk_money']){
                $scoredk_money = dd_money_format($ogdata['totalprice']/$order['pre_totalprice'] * $order['scoredk_money']);
            }
            
        }
        return    dd_money_format($ogdata['totalprice'] - $coupon_money - $leveldk_money- $scoredk_money);
    }
    /**
     * 获取会员价格 
     */
    public function getVipPrice($proid=0,$mid=0,$ggid=0,$sell_price=0){
       
        $product =  Db::name('shop_product')->where('id',$proid)->find();
        $member = Db::name('member')->where('id',$mid)->find();
        $ggdata = Db::name('shop_guige')->where('proid', $proid)->where('id', $ggid)->find();
        if($product['lvprice']==1){
            $lvprice_data = json_decode($ggdata['lvprice_data'],true);
            if($lvprice_data && isset($lvprice_data[$member['levelid']])){
                $sell_price = $lvprice_data[$member['levelid']];
            }
        }
        
        if(getcustom('member_product_price')){
            //一客一价,存在用户时，存在设置的专享商品时 
            $member_product = Db::name('member_product')->where('aid',$product['aid'])->where('mid',$mid)->where('proid',$proid)->where('ggid',$ggid)->find();
            if($member_product){
                $sell_price = $member_product['sell_price'];
            }
        }
        return $sell_price;
    }
    public function getOrderStatus($status){
        $arr =[0=>'待付款',1=>'已支付',2=>'挂单',3=>'st3',4=>'已关闭'];
        return $arr[$status]??$status;
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

    //会员充值
    public function memberRecharge(){
        if(getcustom('cashdesk_member_recharge')){
            $mid = input('param.mid/d', 0);
            $money = floatval(input('post.rechargemoney'));
            $type = input('post.rechargetype');
            $actionname = '充值';
            if($money == 0 || $money == ''){
                return json(['status'=>0,'msg'=>'请输入金额']);
            }
            if($money < 0) $actionname = '扣费';
            if(session('IS_ADMIN')==0){
                $user = Db::name('admin_user')->where('aid',aid)->where('id',$this->uid)->find();
                $remark = '收银台'.$actionname.'，操作员：'.$user['un'];
            }else{
                $remark = '收银台'.$actionname;
            }
            $rs = \app\commons\Member::addmoney(aid,$mid,$money,$remark,0,$type);
            \app\commons\System::plog('收银台给会员'.$mid.$actionname.'，金额'.$money);
            if($rs['status']==0) return json($rs);
            if(getcustom('sms_temp_money_recharge')){
                $tel = Db::name('member')->where('aid',aid)->where('id',$mid)->value('tel');
                if($tel){
                    $rs = \app\commons\Sms::send(aid,$tel,'tmpl_money_recharge',['money'=>$money,'givemoney'=>0]);
                }
            }
            return json(['status'=>1,'msg'=>$actionname.'成功']);
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
            if($member['open_overdraft_money'] == 0 &&  $member['limit_overdraft_money'] < $money){
                return json(['status'=>0,'msg'=>'还款金额超出信用额度']);
            }
            if($pay_return['transaction_id']){
                $remark = '收银台还款';
                \app\commons\Member::addOverdraftMoney(aid,$mid,$money,$remark);
                return json(['status'=>1,'msg'=>'还款成功']);
            }else{
                return $this->json(0,'还款失败');
            }
        }
    }
    /**
     * 聚合支付  线上支付的聚合
     *               
     */
    public function together_pay($order,$paytypeid,$auth_code,$paymoney){
        if(getcustom('member_overdraft_money')) {
            if($paytypeid !=3){
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
            if ($paytypeid == 1) {
                if (preg_match($wx_reg, $auth_code)) {//微信
                    $paytype = '微信支付';
                    $appinfo = Db::name('admin_setapp_cashdesk')->where('aid', aid)->where('bid', 0)->find();
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
                        $bappinfo = Db::name('admin_setapp_cashdesk')->where('aid', aid)->where('bid', bid)->find();
                        //1:服务商 2：平台收款 3：独立收款 0：关闭
                        $restaurant_sysset = Db::name('admin_set')->where('aid', aid)->find();

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
                    $return = Alipay::build_scan(aid, bid, '', $set['name'] . '-当面付', $order['ordernum'], $paymoney, 'cashier', '', $auth_code, 'cashdesk');
                    if ($return['status'] == 1) {
                        $transaction_id = $return['data']['trade_no'];
                    }
                }
            } elseif (($paytypeid == 5 || $paytypeid == 81) || $paytypeid == 81) {//随行付
                $paytype = '随行付支付';
                $return = Sxpay::build_scan(aid, bid, $set['name'] . '-当面付', $order['ordernum'], $paymoney, 'cashdesk', $auth_code);
                if ($return['status'] == 1) {
                    $transaction_id = $return['data']['trade_no'];
                }
            }elseif($paytypeid == 3){
                $transaction_id = 1;
                $paytype = '现金支付';
            }
            return ['paytype' =>$paytype,'transaction_id' =>$transaction_id ];
        }
    }

    /**
     * @description 扫会员码结算 
     * 默认调用抵扣，开启后会抵扣
     * 优先抵扣信用额度，开启余额抵扣会再次用余额抵扣
     */
    public function scanmembercodepay()
    {
        $params  = [];
        $has_cashier_overdraft_money_dec = 0;
        if(getcustom('cashier_overdraft_money_dec')){
            //计算信用额度抵扣
            
            $overdraft_moneydec = 0;
            if(empty(bid)){
                $adminset = Db::name('admin_set')->where('aid',aid)->field('overdraft_money_dec,overdraft_money_dec_rate')->find();
                if($adminset['overdraft_money_dec'] && $adminset['overdraft_money_dec_rate']>0){
                    $overdraft_moneydec = 1;
                }
            }else{
                //查询商户余额抵扣比例
                $business = Db::name('business')->where(['aid'=>aid,'id'=>bid])->field('overdraft_money_dec,overdraft_money_dec_rate')->find();
                if($business && $business['overdraft_money_dec'] && $business['overdraft_money_dec_rate']>0){
                    $overdraft_moneydec = 1;
                }
            }
            $params['overdraft_moneyrate'] = $overdraft_moneydec;
            $has_cashier_overdraft_money_dec = $overdraft_moneydec;
        }
        $has_cashier_money_dec = 0;
        
        if(getcustom('cashier_money_dec')){            //计算余额抵扣
            $money_dec = 0;
            if(empty(bid)){                
                $adminset = Db::name('admin_set')->where('aid',aid)->field('money_dec,money_dec_rate')->find();                
                if($adminset['money_dec'] && $adminset['money_dec_rate']>0){
                    $money_dec = 1;
                }
            }else{
                //查询商户余额抵扣比例
                $business = Db::name('business')->where(['aid'=>aid,'id'=>bid])->field('money_dec,money_dec_rate')->find();
                if($business && $business['money_dec'] && $business['money_dec_rate']>0){
                    $money_dec = 1;
                    
                }
            }
            $params['moneyrate'] = $money_dec;//默认使用抵扣
            $has_cashier_money_dec = $money_dec;
            
        }

        if(getcustom('cashier_scan_membercode_pay')){
            $cashier_id = input('param.cashier_id/d', 0);
            $member_code = input('param.member_code/d', 0);        
            $member = Db::name('member')->where('member_code',$member_code)->find();
            if(!$member){
                return $this->json(0,'会员不存在');
            }
            $mid = $member['id'];
            
            
            $order = $this->getWaitOrder($cashier_id);
            if (empty($order)) {
                return $this->json(0, '无待结算订单');
            }
            $goodslist = Db::name('cashier_order_goods')->where('orderid', $order['id'])->select()->toArray();
            if (empty($goodslist)) {
                return $this->json(0, '无待结算商品');
            }
            foreach ($goodslist as $k=>$v){
                if($v['protype']==1){
                    //库存校验
                    $gginfo = Db::name('shop_guige')->where('aid',aid)->where('id',$v['ggid'])->find();
                    if($gginfo['stock']<$v['num']){
                        return $this->json(0, $v['proname'].'('.$v['ggname'].')'.'库存不足');
                    }
                }
            }
            $orderResult = $this->getOrderPrice($order,0,0,$mid,$params);
            if($orderResult['status']!=1){
                return $this->json(0, $orderResult['msg']);
            }

            //抹零
            $orderup = [];
            $orderup['pre_totalprice'] = $orderResult['pre_totalprice'];
            $orderup['totalprice'] = $orderResult['totalprice'];
            $orderup['moling_money'] = $orderResult['moling_money'];
            $orderup['scoredk_money'] = $orderResult['scoredk_money'];
            $orderup['leveldk_money'] = $orderResult['leveldk_money'];
            $orderup['scoredkscore'] = $orderResult['totalscore'];
            $orderup['mid'] = $mid;
            $orderup['uid'] = $this->uid;
            $totalscore = $orderResult['totalscore'];
            if($has_cashier_money_dec){
                if($params && $params['moneyrate'] && $orderResult['dec_money']>0){
                    $orderup['dec_money']      = $orderResult['dec_money'];
                    $orderup['money_dec_rate'] = $orderResult['money_dec_rate'];
                }
            }
            if($has_cashier_overdraft_money_dec){
                if($params && $params['moneyrate'] && $orderResult['dec_overdraft_money']>0){
                    $orderup['dec_overdraft_money']      = $orderResult['dec_overdraft_money'];
                    $orderup['overdraft_money_dec_rate'] = $orderResult['overdraft_money_dec_rate'];
                }
            }
            if($orderResult['totalprice'] ==0){

                if(getcustom('cashier_member_paypwd')){
                    //使用密码
                    if($has_cashier_overdraft_money_dec  || $has_cashier_money_dec ){
                        $paypwd_use_status = Db::name('cashier')->where('aid',aid)->where('bid',bid)->value('paypwd_use_status');
                        $paypwd = input('param.paypwd');
                        //比如输入密码 且密码为空 
                        if(!$paypwd && $paypwd_use_status ==1){
                            return $this->json(3,'请输入正确的支付密码');
                        }
                        if($paypwd && md5($paypwd.$member['paypwd_rand']) != $member['paypwd']){
                            return $this->json(3,'请输入正确的支付密码');
                        }
                    }
                }
                Db::name('cashier_order')->where('id', $order['id'])->update($orderup);
                $payorderid = \app\models\Payorder::createorder(aid, $order['bid'], $mid, 'cashier', $order['id'], $order['ordernum'], '收银台'.t('余额').'收款', $orderup['totalprice'], $totalscore);
                $res = Db::name('payorder')->where('id',$payorderid)->update(['paytype'=>'余额收款-收银台','paytypeid'=>1,'paynum'=>$orderup['paynum'],'status' =>1,'paytime' => time(),'platform' =>'cashier']);
                if($res){
                    //标记已支付
                    Db::name('cashier_order')->where('id',$order['id'])->update(['status'=>1,'paytime'=>time(),'paytype'=>'余额收款-收银台','paytypeid'=>1,'paynum'=>$orderup['paynum'],'platform'=>'cashdesk']);
                    //减库存
                    foreach ($goodslist as $k=>$v){
                        $num = $v['num'];
                        Db::name('shop_guige')->where('aid',aid)->where('id',$v['ggid'])->update(['stock'=>Db::raw("stock-$num"),'sales'=>Db::raw("sales+$num")]);
                        Db::name('shop_product')->where('aid',aid)->where('id',$v['proid'])->update(['stock'=>Db::raw("stock-$num"),'sales'=>Db::raw("sales+$num")]);
                    }
                    Db::commit();
                    $this->afterPay($order['id']);
                    \app\commons\Wifiprint::print(aid,'cashier',$order['id']);
                    return $this->json(1,'付款成功');
                }else{
                    return $this->json(0,'付款失败');
                }
            }else{
                $data = [];
                $data['moneyrate'] = $has_cashier_money_dec;
                $data['overdraft_moneyrate'] = $has_cashier_overdraft_money_dec;
                $data['mid'] = $mid;
                return $this->json(2,'跳转支付页',$data);
            }
        }
    }
}
