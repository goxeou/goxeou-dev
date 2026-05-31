<?php
// +----------------------------------------------------------------------
// | custom_file(product_weight) 称重商品订单
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;
class WeightOrder extends Common
{
    //列表
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
            if($this->bid>0){
                $where[] = ['bid','=',$this->bid];
            }else{
                $bid = $this->bid;
                $where[] = Db::raw("bid={$bid} OR sync_bid={$bid}");
            }
            $status = input('param.status');
            $mid = input('param.mid/d');
            if($mid){
                $where[] = ['mid','=',$mid];
            }
            if(is_numeric($status)){
                if($status>-1){
                    if(input('param.status') == 5){
                        $where[] = ['refund_status','=',1];
                    }elseif(input('param.status') == 6){
                        $where[] = ['refund_status','=',2];
                    }elseif(input('param.status') == 7){
                        $where[] = ['refund_status','=',3];
                    }elseif(input('param.status') == 22){
                        $where[] = ['status','=',2];
                        $where[] = ['express_isbufen','=',1];
                    }else{
                        $where[] = ['status','=',input('param.status')];
                    }
                }
            }else{
                $where[] = ['status','=',1];
            }
            $where[] = ['product_type','=',2];
            if(input('param.keyword')) $where[] = ['ordernum|linkman|tel','like','%'.input('param.keyword').'%'];
            if(input('param.ctime') ){
                $ctime = explode(' ~ ',input('param.ctime'));
                $where[] = ['createtime','>=',strtotime($ctime[0])];
                $where[] = ['createtime','<',strtotime($ctime[1]) + 86400];
            }
            $count = 0 + Db::name('shop_order')->where($where)->count();
            //统计
            $list = Db::name('shop_order')->where($where)->page($page,$limit)->order($order)->select()->toArray();
            $customerIds = array_unique(array_column($list,'customer_id'));
            $customerKeyVals = [];
            if($customerIds){
                $customerlist = Db::name('sh_customer')->alias('c')
                    ->join('sh_customer pc','c.pid=pc.id','LEFT')
                    ->where('c.aid',aid)->where('c.id','in',$customerIds)->field('c.*,pc.name pname')->select()->toArray();
                foreach ($customerlist as $k=>$value){
                    $customerKeyVals[$value['id']] = $value;
                }
            }
            foreach($list as $k=>$vo){
                $oglist = Db::name('shop_order_goods')->where('aid',aid)->where('orderid',$vo['id'])->select()->toArray();
                $goodsdata=array();
                foreach($oglist as $og){
                    $ogremark = '';

                    $bname = '';
                    if($og['bid']){
                        $bname = Db::name('business')->where('aid',aid)->where('id',$og['bid'])->value('name');
                    }
                    if($bname){
                        $ogremark = '(<span style="color:#3d8ffa;">'.$bname.'</span>)';
                    }
                    if($og['gtype']==1){
                        $ogremark = '<span style="color:#f00;">【赠品】</span>';
                    }
                    $goodshtml = '<div class="goods-item">'.
                        '<div class="table-imgbox"><img src="'.$og['pic'].'" height="50" width="50"></div>'.
                        '<div style="padding-left: 10px;font-size: 10px">'.
                        '<div style="width:100%;overflow:hidden">'.$og['name'].$ogremark.'</div>'.
                        '<div style="color:#f60"><span style="color:#888">'.$og['ggname'].'</span></div>';
                        $goodshtml.='<div style="padding-top:0px;color:#f60;">￥'.$og['real_sell_price'].'元/斤 × '.round($og['real_total_weight']/500,2).'斤× '.$og['num'].'</div>';
                    $goodshtml.='</div>';
                    $goodshtml.='</div>';
                    $goodsdata[] = $goodshtml;
                }
                $list[$k]['goodsdata'] = implode('',$goodsdata);
                //customerhtml
                $customerhtml = '';
                $customerhtml .= "<p>{$vo['linkman']}".($vo['mid']?'(mid='.$vo['mid'].')':'')."</p>";
                $customerhtml .= "<p>{$vo['tel']}</p>";
                $customerhtml .= "<p>{$vo['area']}{$vo['address']}</p>";
                $list[$k]['customerdata'] = $customerhtml;
                if($vo['mid']){
//                        $member = Db::name('member')->where('id',$vo['mid'])->field('id,nickname,headimg,realname')->find();
                }
                $refundOrder = Db::name('shop_refund_order')->where('refund_status','>',0)->where('aid',aid)->where('orderid',$vo['id'])->count();
                $list[$k]['refundCount'] = $refundOrder;
                $list[$k]['payorder'] = [];
            }
            return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$list]);
        }
        $machinelist = Db::name('wifiprint_set')->where('aid',aid)->where('status',1)->where('bid',$this->bid)->select()->toArray();
        $hasprint = 0;
        if($machinelist){
            $hasprint = 1;
        }
        $orderdoneAuth = false;
        if($this->auth_data == 'all' || in_array('WeightOrder/orderDone',$this->auth_data)){
            $orderdoneAuth = true;
        }
        $param = [];
        $param['t'] = time();
        $param['status'] = 1;//默认待发货
        View::assign('hasprint',$hasprint);
        View::assign('datawhere',json_encode($param));
        View::assign('status',input('param.status','1'));
        View::assign('orderdoneAuth',$orderdoneAuth);
        return View::fetch();
    }

    public function orderDone(){
        $ids = input('param.ids',[]);
        if(empty($ids) || !is_array($ids)){
            return json(['status'=>0,'msg'=>'参数有误']);
        }
        $where = [];
        $where[] = ['aid','=',aid];
        $where[] = ['product_type','=',2];
//        $where[] = ['paytypeid','=',38];//透支支付
        $where[] = ['status','=',2];//已发货的
        if($this->bid>0){
            $where[] = ['bid','=',$this->bid];
        }else{
            $bid = $this->bid;
            $where[] = Db::raw("bid={$bid} OR sync_bid={$bid}");
        }
        $where[] = ['id','in',$ids];
        //所有已发货[待确认收货]的订单设为已完成
        $lists = Db::name('shop_order')->where($where)->select()->toArray();
        if(empty($lists)) return json(['status'=>0,'msg'=>'无可操作数据']);
        foreach ($lists as $key=>$order){
            Db::name('shop_order')->where('aid',aid)->where('status',2)->where('id',$order['id'])->update(['status'=>3,'collect_time'=>time()]);
            Db::name('shop_order_goods')->where('orderid',$order['id'])->update(['status'=>3,'endtime'=>time()]);
            //信用额度支付的
            if($order['paytypeid']==38 && $order['mid'] && $order['totalprice']){
                //恢复额度
                \app\commons\Member::addOverdraftMoney(aid,$order['mid'],$order['totalprice'],"订单完成（{$order['ordernum']}），额度恢复");
            }
            if($order['mid']>0){
//                \app\commons\Member::uplv(aid,$order['mid']);
            }
        }
        return json(['status'=>1,'msg'=>'订单已完成']);
    }

    public function indexTongji(){
        $where = [];
        $where[] = ['order.aid','=',aid];
        if(empty($this->bid)){
            $where[] = ['order.bid','=',$this->bid];
        }else{
            $bid = $this->bid;
            $where[] = Db::raw("order.bid={$bid} OR order.sync_bid={$bid}");
        }
        $status = input('param.status');
        $mid = input('param.mid/d');
        if($mid){
            $where[] = ['order.mid','=',$mid];
        }
        if(is_numeric($status)){
            if($status>-1){
                if(input('param.status') == 5){
                    $where[] = ['order.refund_status','=',1];
                }elseif(input('param.status') == 6){
                    $where[] = ['order.refund_status','=',2];
                }elseif(input('param.status') == 7){
                    $where[] = ['order.refund_status','=',3];
                }else{
                    $where[] = ['order.status','=',input('param.status')];
                }
            }
        }else{
            $where[] = ['order.status','=',1];
        }
        $where[] = ['order.product_type','=',2];
        if(input('param.keyword')) $where[] = ['order.ordernum|order.linkman|order.tel','like','%'.input('param.keyword').'%'];
        if(input('param.ctime') ){
            $ctime = explode(' ~ ',input('param.ctime'));
            $where[] = ['order.createtime','>=',strtotime($ctime[0])];
            $where[] = ['order.createtime','<',strtotime($ctime[1]) + 86400];
        }
        $totalNum = Db::name('shop_order_goods')->alias('og')->join('shop_order order','og.orderid=order.id')->where($where)->sum('og.num');
        $totalPrice = Db::name('shop_order_goods')->alias('og')->join('shop_order order','og.orderid=order.id')->where($where)->sum('og.real_totalprice');
        $totalWeight = Db::name('shop_order_goods')->alias('og')->join('shop_order order','og.orderid=order.id')->where($where)->sum('og.real_total_weight');
        $data = [
            'totalnum'=>$totalNum,
            'totalweight'=>number_format(round($totalWeight/500,2),2),
            'totalprice'=>number_format(round($totalPrice,2),2),
        ];
        return json($data);
    }
    public function detail(){
        $where = [];
        $where[] = ['aid','=',aid];
        if($this->bid>0){
            $where[] = ['bid','=',$this->bid];
        }else{
            $bid = $this->bid;
            $where[] = Db::raw("bid={$bid} OR sync_bid={$bid}");
        }
        $orderid = input('param.id/d',0);
        $ishb = input('param.ishb/d',0);//同源订单合并展示
        $info = Db::name('shop_order')->where($where)->where('id',$orderid)->find();
        if(!$info){
            showmsg('订单不存在');die();
        }
        $statusArr = [0=>'未支付',1=>'已支付',2=>'已发货',3=>'已完成'];
        if($ishb && strpos($info['ordernum'],'_')!=false){
            $ordernum = explode('_',$info['ordernum'])[0];
            $orderList = Db::name('shop_order')->where('aid',aid)->where('bid',$this->bid)->where('ordernum','like',$ordernum.'%')->order('id asc')->select()->toArray();
        }else{
            $orderList = [$info];
        }
        $orderids = [];
        foreach ($orderList as $k=>$order){
            $order['message'] = \app\models\ShopOrder::checkOrderMessage($order['id'],$order);
            $orderids[] = $order['id'];
            $orderList[$k]['customer'] = [];
            if($order['customer_id']){
                $customer = Db::name('sh_customer')->where('aid',aid)->where('id',$order['customer_id'])->find();
                if(getcustom('customer_peisonguser')){
                    $peisong = [];
                    if($customer['peisong_uid']){
                        $peisong = Db::name('peisong_user')->where('aid',aid)->where('id',$customer['peisong_uid'])->find();
                    }
                    $customer['peisong'] = $peisong;
                }
                $orderList[$k]['customer'] = $customer;
            }
            $order_goods = Db::name('shop_order_goods')->where('aid',aid)->where('orderid',$order['id'])->select()->toArray();
            foreach ($order_goods as $gk=>$gv){
                $order_goods[$gk]['total_weight'] = round($gv['total_weight']/500,2);
                $order_goods[$gk]['real_total_weight'] = round($gv['real_total_weight']/500,2);
                $order_goods[$gk]['remark'] = $gv['remark']?$gv['remark']:$order['message'];
            }
            $orderList[$k]['goodslist'] = $order_goods;
            $orderList[$k]['status_txt'] = $statusArr[$order['status']];
            if($order['mid']>0){
                $member = Db::name('member')->where('aid',aid)->where('id',$order['mid'])->field('id,headimg,nickname,realname,tel')->find();
                $orderList[$k]['headimg'] = $member['headimg'];
                $orderList[$k]['nickname'] = $member['nickname'];
            }

        }
        View::assign('orderid',implode(',',$orderids));
        View::assign('orderList',$orderList);
        return View::fetch();
    }
    //小票打印（标签）
    public function wifiprint(){
        $orderid = input('param.id');
        $machineType = input('param.machineType',1);//0小票打印  1标签打印
        $orderIds = explode(',',$orderid);
        $msg = '';
        $where = [];
        $where[] = ['aid','=',aid];
        if($this->bid>0){
            $where[] = ['bid','=',$this->bid];
        }else{
            $bid = $this->bid;
            $where[] = Db::raw("bid={$bid} OR sync_bid={$bid}");
        }
        if($orderIds){
            $orderlist = Db::name('shop_order')->where($where)->where('id','in',$orderid)->select()->toArray();
            foreach ($orderlist as $k=>$order){
                $rs = \app\commons\Wifiprint::print(aid,'shop',$order['id'],0,$machineType,$this->bid,'shop_weight,shop');
                if($rs['status']==1){
                    $msg = $rs['msg'];
                }else{
                    return json(['status'=>0,'msg'=>$rs['msg']]);
                }
            }
        }
        return json(['status'=>1,'msg'=>$msg]);
    }
    //批量打印送货单
    public function plshd(){
        $orderids = input('param.ids');
        $orderarr = explode(',',$orderids);
        $goodslist = [];
        $bname = 0;
        $where = [];
        $where[] = ['aid','=',aid];
        if($this->bid>0){
            $where[] = ['bid','=',$this->bid];
        }else{
            $bid = $this->bid;
            $where[] = Db::raw("bid={$bid} OR sync_bid={$bid}");
        }
        foreach($orderarr as $k=>$orderid){
            $info = Db::name('shop_order')->where($where)->where('id',$orderid)->find();
            if(!$info || ($this->bid !=0 && $info['bid'] != $this->bid)) showmsg('订单不存在');
            $info['message'] = \app\models\ShopOrder::checkOrderMessage($info['id'],$info);
            if(getcustom('customer_peisonguser')){
                $peisong = [];
                if($info['customer_id']){
                    $peisong_uid = Db::name('sh_customer')->where('aid',aid)->where('id',$info['customer_id'])->value('peisong_uid');
                    if($peisong_uid>0){
                        $peisong = Db::name('peisong_user')->where('aid',aid)->where('id',$peisong_uid)->find();
                    }
                }
                $info['cpeisong'] = $peisong;
            }
            $order_goods = Db::name('shop_order_goods')->where('aid',aid)->where('orderid',$orderid)->select()->toArray();
            $totalprice = 0;//只累计发货的价格
            foreach($order_goods as $k=>&$v){
                if($v['status']==22){
                    $v['total_weight'] = 0;
                    $v['real_total_weight'] = 0;
                    $v['real_totalprice'] = 0;
                    $v['remark'] = '挂单未发货';
                    $v['num'] = 0;
                }else{
                    $remark = $v['remark']?$v['remark']:$info['message'];
                    $v['total_weight'] = round($v['total_weight']/500,2);
                    $v['real_total_weight'] = round($v['real_total_weight']/500,2);
//                    if($v['sell_price']!=$v['real_sell_price']){
//                        $remark.=' 原价:￥'.$v['sell_price'];
//                    }
//                    if($v['total_weight']!=$v['real_total_weight']){
//                        $remark.=' 原重量:'.$v['total_weight'].'斤';
//                    }
                    $totalprice = $totalprice+$v['real_totalprice'];
                    $v['remark'] = $remark;
                }

            }
            $order_goods2 = [];
            if(count($order_goods) < 9){
                for($i=0;$i<6;$i++){
                    $order_goods2[] = $order_goods[$i];
                }
            }else{
                $order_goods2 = $order_goods;
            }

            $order_goods2[] = ['name'=>'运费','real_sell_price'=>'','real_total_weight'=>'','real_totalprice'=>$info['freight_price']];
            $otherFee = $info['invoice_money'] - $info['scoredk_money'] - $info['leveldk_money'] - $info['manjian_money'] - $info['coupon_money'];
            if($otherFee!=0){
                $order_goods2[] = ['name'=>'其他','real_sell_price'=>'','real_total_weight'=>'','real_totalprice'=>$otherFee];
            }
//            $order_goods2[] = ['name'=>'合计','real_sell_price'=>'','real_total_weight'=>'','real_totalprice'=>$info['totalprice']];
            $order_goods2[] = ['type'=>'totalprice'];
            $order_goods2[] = ['type'=>'totalprice2'];
            //买家留言
//            $order_goods2[] = ['type'=>'remark'];
            $order_goods3 = array_chunk($order_goods2,13);
//            $info['totalprice2'] = num_to_rmb($info['totalprice']);
            if($info['freight_type']==11 && $info['freight_content']){
                $info['freight_content'] = json_decode($info['freight_content'],true);
            }else{
                $info['freight_content'] = [];
            }
            if($info['bid'] == 0){
                $bname = Db::name('admin_set')->where('aid',aid)->value('name');
            }else{
                $bname = Db::name('business')->where('id',$info['bid'])->value('name');
            }
            $info['totalprice'] = number_format($totalprice,2);
            $info['order_goods3'] = $order_goods3;
            $info['totalprice2'] = num_to_rmb($info['totalprice']);
            //如果买家留言为空，则找自定义字段为备注的值
            $info['message'] = \app\models\ShopOrder::checkOrderMessage($orderid,$info);
            $goodslist[] = $info;
        }


        $shipping_pagetitle = Db::name('shop_sysset')->where('aid',aid)->value('shipping_pagetitle');
        View::assign('bname',$bname);
        View::assign('shipping_pagetitle',$shipping_pagetitle);
        View::assign('goodslist',$goodslist);
        View::assign('count',count($goodslist)-1);
        View::assign('adminname',$this->user['un']);
        View::assign('express_data',express_data(['aid'=>aid,'bid'=>bid]));
        return View::fetch();
    }
    public function editchoose(){
        $pwhere = [];
        $pwhere[] = ['aid','=',aid];
        $pwhere[] = ['status','=',1];
        $pwhere[] = ['ischecked','=',1];
        $pwhere[] = ['product_type','=',2];
        $pwhere[] = ['douyin_product_id','=',''];
        $where = [];
        $where[] = ['aid','=',aid];
        $bid = $this->bid;
        if($this->bid>0){
            $where[] = Db::raw("bid={$bid} OR find_in_set({$bid},`ext_bids`)");
            $pwhere[] = ['bid','=',$this->bid];
        }
        $customerlist = Db::name('sh_customer')->where($where)->where('pid',0)->order('sort desc,id desc')->select()->toArray();

        $prolist = Db::name('shop_product')->where($pwhere)->field('id,name,bid')->select()->toArray();

        foreach ($prolist as $k=>$v){
            $prolist[$k]['bname'] = '';
            if($v['bid']>0){
                $bname = Db::name('business')->where('id',$v['bid'])->value('name');
                $prolist[$k]['bname'] = $bname??'';
            }
        }
        View::assign('customerlist',$customerlist);
        View::assign('prolist',$prolist);
        return View::fetch();
    }
    public function edit(){
        $ctmid = input('param.ctmid');
        $proids = input('param.proids','');
        $proids = $proids?explode(',',$proids):[];
        if(!is_array($ctmid)){
            $ctmid = [$ctmid];
        }
        //测试
//        $ctmid = [1];
//        $proids = [1268,1262];
        //测试
        $gglist = Db::name('shop_guige')->alias('g')->join('shop_product p','p.id=g.proid')
            ->where('g.aid',aid)->where('g.proid','in',$proids)->order('g.proid desc')->field('g.*,p.name proname,p.lvprice')->select()->toArray();
        //换算单价和重量
        foreach ($gglist as $k=>$v){
            $_price = $v['sell_price'];
            $_weight = round($v['weight']/500,2);//化成斤
            //单价
            if($_weight>0){
                $price = round($_price / $_weight,2);
            }else{
                $price = $_price;
            }
            $gglist[$k]['origin_weight'] = $v['weight'];
            $gglist[$k]['origin_price'] = $price;
            $gglist[$k]['price'] = $price;
            $gglist[$k]['weight'] = $_weight;
        }
        $cwhere = [];
        $cwhere[] = ['aid','=',aid];
        $bid = $this->bid;
        if($this->bid>0){
            $cwhere[] = Db::raw("bid={$bid} OR find_in_set({$bid},`ext_bids`)");
        }
        $cfield = 'id,name,mid,pid,ext_bids,number';
        $customerList = Db::name('sh_customer')->where($cwhere)->where('id','in',$ctmid)->order('pid desc')->select()->toArray();
        if(empty($customerList)){
            showmsg('请先选择客户');
        }
        $cpwhere = [];
        $cpwhere[] = ['aid','=',aid];
        $bid = $this->bid;
        if($this->bid>0){
            $cpwhere[] = Db::raw("bid={$bid} OR find_in_set({$bid},`ext_bids`) OR bid=0");
        }
        $pids = array_column($customerList,'id');
        $mids = array_column($customerList,'mid');
        $childCustomer = Db::name('sh_customer')->where($cpwhere)->where('pid','in',$pids)->order('sort desc,id desc')->select()->toArray();
        $childCustomerGroup = [];
        if($childCustomer){
            //pid分组
            foreach ($childCustomer as $k=>$child){
                $childCustomerGroup[$child['pid']][] = $child;
                if($child['mid'] && !in_array($child['mid'],$mids)){
                    $mids[] = $child['mid'];
                }
            }
        }
        $mids = array_unique($mids);//所有绑定的客户会员
        //会员等级
        $memberLevelIds = [];
        if($mids){
            $memberLevelIds = Db::name('member')->where('aid',aid)->where('id','in',$mids)->column('levelid','id');
        }
        //客户定价
        $customerPrice = [];
        foreach ($customerList as $customer){
            foreach ($gglist as $gk=>$gv){
                $ckey = $customer['id'].'_'.$gv['id'];
                $cprice = Db::name('customer_price')->where('aid',aid)->where('customer_id',$customer['id'])->where('ggid',$gv['id'])->value('price');
                if($cprice){
                    $customerPrice[$ckey] = $cprice;
                }
            }
        }
        foreach ($customerList as $k=>$customer){
            //如果没有子客户，则默认为父级
            if(!isset($childCustomerGroup[$customer['id']])){
                $tmpChildCustomer = [$customer];
            }else{
                $tmpChildCustomer = $childCustomerGroup[$customer['id']];
            }

            foreach ($tmpChildCustomer as $k2=>$childC){
                //会员价
                foreach ($gglist as $gk=>$gv){
                    //客户定价
                    $cp_key = $customer['id'].'_'.$gv['id'];
                    if(isset($customerPrice[$cp_key])){
                        $_price = $customerPrice[$cp_key];
                        $_weight = round($gv['origin_weight']/500,2);
                        //单价
                        if($_weight>0){
                            $price = round($_price / $_weight,2);
                        }else{
                            $price = $_price;
                        }
                        $gglist[$gk]['price'] = $price;
                    }elseif($childC['mid'] && isset($memberLevelIds[$childC['mid']])){
                        //会员价
                        $levelid = $memberLevelIds[$childC['mid']];
                        $lvprice_data = $gv['lvprice_data']?json_decode($gv['lvprice_data'],true):[];
                        $_price = $lvprice_data[$levelid]??0;
                        if($_price){
                            $_weight = round($gv['weight']/500,2);//化成斤
                            //单价
                            if($_weight>0){
                                $price = round($_price / $_weight,2);
                            }else{
                                $price = $_price;
                            }
                            $gglist[$gk]['price'] = $price;
                        }
                    }
                }
                $tmpChildCustomer[$k2]['gglist'] = $gglist;
            }
            $customerList[$k]['child'] = $tmpChildCustomer;
            $customerList[$k]['child_num'] = count($tmpChildCustomer);
        }
        View::assign('list',$customerList);
        return  View::fetch();
    }
    public function save(){
        $ggidsGroup = input('post.ggids/a',[]);
        $priceGroup = input('post.price/a',[]);
        $weightGroup = input('post.weight/a',[]);
        $remarkGroup = input('post.remark/a',[]);
        $remark1Group = input('post.remark1/a',[]);//内部备注
        $remark0Group = input('post.remark0/a',[]);//输入的订单备注
        $numberGroup = input('post.number/a',[]);
        if(empty($ggidsGroup)){
            return json(['status'=>0,'请录入数据']);
        }
        $allGgids = [];
        foreach ($ggidsGroup as $customerId=>$ggids){
            foreach ($ggids as $k=>$ggid){
                if(!isset($allGgids[$ggid])) {
                    $allGgids[$ggid] = $ggid;
                }
            }
        }
        //获取商品信息
        $ggproduct = Db::name('shop_guige')->alias('g')->join('shop_product p','p.id=g.proid')
            ->where('g.aid',aid)->where('g.id','in',$allGgids)->field('g.*,p.bid,p.pic p_pic,p.name p_name,p.sell_price p_sell_price,p.procode p_procode,p.cid p_cid,p.product_type,p.bid')->select()->toArray();
        //规格id为键值
        $ggKeyProduct = [];
        foreach ($ggproduct as $k=>$v){
            $ggKeyProduct[$v['id']] = $v;
        }
        $allCustomerIds = [];
        $data = [];
        $bidArr = [];
        $cstIdsArr = [];
        foreach ($ggidsGroup as $customerId=>$ggids){
            if(!$weightGroup[$customerId]){
                continue;
            }
            $allCustomerIds[] = $customerId;
            foreach ($ggids as $k=>$ggid){
                //代理下单 数量大于1 或者 重量大于0 算有效订单
                if($weightGroup[$customerId][$k]<=0 && $numberGroup[$customerId][$k]<=1){
                    continue;
                }
                if(!isset($ggKeyProduct[$ggid])){
                    continue;
                }
                $bid = $ggKeyProduct[$ggid]['bid'];
                $remark = [];
                if($remarkGroup[$customerId][$k]){
                    $remark[] = $remarkGroup[$customerId][$k];
                }
                if($remark0Group[$customerId][$k]){
                    $remark[] = $remark0Group[$customerId][$k];
                }
                $data[$bid][$customerId][] = [
                    'customer_id'=>$customerId,
                    'ggid'=>$ggid,
                    'price'=>$priceGroup[$customerId][$k],
                    'weight'=>$weightGroup[$customerId][$k]??0,//斤化成g
                    'remark'=>implode(',',$remark),
                    'number'=>$numberGroup[$customerId][$k],
                    'remark1'=>$remark1Group[$customerId][$k],
                ];
                $bidArr[$bid] = $bid;
                $cstIdsArr[$customerId] = $customerId;
            }
        }
        $customers = [];
        if($allCustomerIds){
            $customers = Db::name('sh_customer')->where('aid',aid)->where('id','in',$allCustomerIds)->column('*','id');
        }
//            dump($ggKeyProduct);die();
        $orderAll = [];
        //生成订单
        $ordernumhb = \app\commons\Common::generateOrderNo(aid);
        $i = 1;
        $orderIds = [];
        foreach ($data as $bid=>$cdata) {
            foreach ($cdata as $customerId => $items) {
                $totalprice = 0;
                $product_name = '';
                $orderGoods = [];
                $ordernum = (count($cstIdsArr)>1 || count($bidArr)>1) ? $ordernumhb . '_' . $i : $ordernumhb;
                $customerInfo = $customers[$customerId] ?? [];
                foreach ($items as $k => $item) {
                    $gginfo = $ggKeyProduct[$item['ggid']] ?? [];
                    $gtotalprice = round($item['price'] * $item['weight'], 2);
                    $orderGoods[] = [
                        'aid' => aid,
                        'bid' => $bid,
                        'mid' => $customerInfo['mid'] ?? 0,
                        'orderid' => 0,//后面追加
                        'ordernum' => $ordernum,//后面追加
                        'createtime' => time(),
                        'status' => 1,
                        'proid' => $gginfo['proid'],
                        'name' => $gginfo['p_name'],
                        'pic' => $gginfo['pic'] ? $gginfo['pic'] : $gginfo['p_pic'],
                        'procode' => $gginfo['procode'],
                        'barcode' => $gginfo['barcode'],
                        'ggid' => $gginfo['id'],
                        'ggname' => $gginfo['name'],
                        'cid' => $gginfo['p_cid'],
                        'num' => $item['number'],//
                        'sell_price' => $item['price'],//每公斤的单价
                        'total_weight' => $item['weight'] * 500,//总重量
                        'totalprice' => $gtotalprice,
                        'real_totalprice' => $gtotalprice,//后期发货单根据实际修改
                        'real_total_weight' => $item['weight'] * 500,//后期发货单根据实际修改
                        'real_sell_price' => $item['price'],//后期发货单根据实际修改
                        'remark' => $item['remark'],
                        'remark_ext' => $item['remark1'],
                    ];
                    //订单总价
                    $totalprice = $totalprice + $gtotalprice;
                    $product_name = $gginfo['p_name'];
                }
                $order = [
                    'aid' => aid,
                    'bid' => $bid,
                    'mid' => $customerInfo['mid'] ?? 0,
                    'ordernum' => $ordernum,
                    'createtime' => time(),
                    'title' => $product_name,
                    'product_price' => $totalprice,
                    'totalprice' => $totalprice,//实付后期称重后改
                    'status' => 1,
                    'linkman' => $customerInfo['name'],
                    'tel' => $customerInfo['tel'],
                    'area' => '',
                    'address' => $customerInfo['address'],
                    'paytypeid' => 38,//信用额度
                    'paytype' => '信用额度支付',
                    'platform' => 'pc',
                    'freight_id' => 0,
                    'freight_text' => '商家配送',
                    'remark' => '称重订单后台录入',
                    //新增字段
                    'product_type' => 2,//订单类型
                    'customer_id' => $customerId,
                ];
                if ($bid != $this->bid) {
                    $order['sync_bid'] = $this->bid;
                }
                $orderid = Db::name('shop_order')->insertGetId($order);
                $orderIds = $orderid;
                foreach ($orderGoods as $gk => $gv) {
                    $orderGoods[$gk]['orderid'] = $orderid;
                }
                Db::name('shop_order_goods')->insertAll($orderGoods);
                $i++;
            }
        }
        \app\commons\System::plog('录入称重订单'.implode(',',$orderIds));
        return json(['status'=>1,'msg'=>'录入成功']);
    }
    public function getEditData(){
        $customerlist = Db::name('sh_customer')->where('aid',aid)->where('bid',$this->bid)->where('pid',0)->order('sort desc,id desc')->select()->toArray();
        $pwhere = [];
        $pwhere[] = ['aid','=',aid];
        $pwhere[] = ['bid','=',$this->bid];
        $pwhere[] = ['status','=',1];
        $pwhere[] = ['ischecked','=',1];
        $pwhere[] = ['product_type','=',2];
        $pwhere[] = ['douyin_product_id','=',''];
        $prolist = Db::name('shop_product')->where($pwhere)->field('id,name')->select()->toArray();
        return json(['status'=>1,'customerlist'=>$customerlist,'prolist'=>$prolist]);
    }

    public function searchCustomer(){
        $keyword = input('param.keyword','');
        //默认显示20行
        $where = [];
        $where[] = ['aid','=',aid];
        if($keyword){
            if(is_numeric($keyword)){
                $where[] = Db::raw("id={$keyword} OR number like '%{$keyword}%'");
            }else{
                $where[] = ['name|number','like','%'.$keyword.'%'];
            }
        }
        $bid = $this->bid;
        if($this->bid>0){
            $where[] = Db::raw("bid={$bid} OR find_in_set({$bid},`ext_bids`)");
        }
        $data = Db::name('sh_customer')->where($where)->where('pid',0)->order('id desc')->field("id as value,name,'' as selected,'' as disabled")->limit(20)->select()->toArray();
        if(empty($data)) $data = [];
        return json($data);
    }

    public function searchProduct(){
        $keyword = input('param.keyword','');
        //默认显示20行
        $pwhere = [];
        $pwhere[] = ['aid','=',aid];
        $pwhere[] = ['status','=',1];
        $pwhere[] = ['ischecked','=',1];
        $pwhere[] = ['product_type','=',2];
        $pwhere[] = ['douyin_product_id','=',''];
        $where = [];
        $where[] = ['aid','=',aid];
        if($this->bid>0){
            $pwhere[] = ['bid','=',$this->bid];
        }
        if($keyword){
            $pwhere[] = ['name|procode','like','%'.$keyword.'%'];
        }

        $prolist = Db::name('shop_product')->where($pwhere)->field("id as value,name,bid,'' selected,'' disabled")->select()->toArray();
        if(empty($prolist)) $prolist = [];
        foreach ($prolist as $k=>&$v){
            $prolist[$k]['bname'] = '';
            if($v['bid']>0){
                $bname = Db::name('business')->where('id',$v['bid'])->value('name');
                if($bname){
                    $v['name'] = $v['name']."({$bname})";
                }
            }
        }
        return json($prolist);
    }
    public function searchRemark(){
        $keyword = input('param.keyword','');
        //默认显示20行
        $where = [];
        $where[] = ['aid','=',aid];
        if($keyword){
            $where[] = ['name','like','%'.$keyword.'%'];
        }
        $bid = $this->bid;
        if($this->bid>0){
            $where[] = Db::raw("bid={$bid} OR find_in_set({$bid},`ext_bids`)");
        }
        $remarklist = Db::name('sh_weight_remark')->where($where)->field("id as value,name,bid,'' selected,'' disabled")->select()->toArray();
        if(empty($remarklist)) $remarklist = [];
        return json($remarklist);
    }

    //称重订单发货
    public function fahuo(){
        $where = [];
        $where[] = ['aid','=',aid];
        if($this->bid>0){
            $where[] = ['bid','=',$this->bid];
        }else{
            $bid = $this->bid;
            $where[] = Db::raw("bid={$bid} OR sync_bid={$bid}");
        }
        $ispending = input('param.ispending/d',0);
        $orderid = input('param.id/d',0);
        $ishb = input('param.ishb/d',1);//相同客户合并发货
        $info = Db::name('shop_order')->where($where)->where('id',$orderid)->find();
        if(!$info){
            showmsg('订单不存在');die();
        }
        if($info['status']!=1){
            showmsg('该订单状态不可发货');die();
        }
        if($ishb && strpos($info['ordernum'],'_')!=false){
            $ordernum = explode('_',$info['ordernum'])[0];
            $orderList = Db::name('shop_order')->where($where)->where('ordernum','like',$ordernum.'%')->order('id desc')->select()->toArray();
        }else{
            $orderList = [$info];
        }
        $newOrderlist = [];
        foreach ($orderList as $k=>$order){
            $order['message'] = \app\models\ShopOrder::checkOrderMessage($order['id'],$order);
            $orderList[$k]['customer'] = [];
            if($order['customer_id']){
                $customer = Db::name('sh_customer')->where('aid',aid)->where('id',$order['customer_id'])->find();
                if(getcustom('customer_peisonguser')){
                    $peisong = [];
                    if($info['peisong_uid']){
                        $peisong = Db::name('peisong_user')->where('aid',aid)->where('id',$customer['peisong_uid'])->find();
                    }
                    $customer['peisong'] = $peisong;
                }
                $orderList[$k]['customer'] = $customer;
            }
            $orderList[$k]['bname'] = '';
            if($order['bid']>0){
                $orderList[$k]['bname'] = Db::name('business')->where('id',$order['bid'])->value('name');
            }
            if($order['mid']>0){
                $member = Db::name('member')->where('aid',aid)->where('id',$order['mid'])->find();
                $orderList[$k]['nickname'] = $member['nickname'];
            }
            //挂单的不显示2未发货，22挂单
            $ogwhere = [];
            $ogwhere[] = ['aid','=',aid];
            $ogwhere[] = ['orderid','=',$order['id']];
            if($ispending){
                //挂单发货
                $ogwhere[] = ['status','=',22];
            }else{
                $ogwhere[] = ['status','=',1];
            }
            $order_goods = Db::name('shop_order_goods')->where($ogwhere)->select()->toArray();
            foreach ($order_goods as $gk=>$gv){
                $order_goods[$gk]['total_weight'] = round($gv['total_weight']/500,2);
                $order_goods[$gk]['real_total_weight'] = round($gv['real_total_weight']/500,2);
                $order_goods[$gk]['remark'] = $gv['remark']?$gv['remark']:$order['message'];
            }
            $orderList[$k]['goodslist'] = $order_goods;
            if($order_goods){
                $newOrderlist[] = $orderList[$k];
            }
        }
        View::assign('ispending',$ispending);
        View::assign('orderList',$newOrderlist);
        return View::fetch();
    }

    public function fahuoSave(){
        $data = input('param.info/a',[]);
        $orderData = input('param.order/a',[]);
        $ispending = input('param.ispending/d',0);//1 挂单发货
        //重新计算实际重量和实际价格
        $status = 2;//已发货
        $orderIds  = [];
        $where = [];
        $where[] = ['aid','=',aid];
        if($this->bid>0){
            $where[] = ['bid','=',$this->bid];
        }else{
            $bid = $this->bid;
            $where[] = Db::raw("bid={$bid} OR sync_bid={$bid}");
        }
        foreach ($data as $orderId=>$orderlist){
            $order = Db::name('shop_order')->where($where)->where('id',$orderId)->find();
            if($order['status']!=1){
                return  json(['status'=>0,'msg'=>'订单状态不支持发货']);
            }
            $order['message'] = \app\models\ShopOrder::checkOrderMessage($order['id'],$order);
            $totalprice = 0;
            $fahuoTotalprice = 0;
            $orderUp = [];
            foreach ($orderlist as $goodsId=>$goods){
                $gtotalprice = round($goods['price'] * $goods['weight'],2);
                $fahuoTotalprice += $gtotalprice;
                $goodsUp = [];
                $goodsUp['real_totalprice'] = $gtotalprice;//实际价格
                $goodsUp['real_sell_price'] = $goods['price'];
                $goodsUp['real_total_weight'] = $goods['weight']*500;//数据库重量单位斤化为克
                $goodsUp['num'] = $goods['num'];
                $goodsUp['status'] = $status;
                $goodsUp['remark'] = $goods['remark'];
                $ogwhere = [];
                $ogwhere[] = ['aid','=',aid];
                $ogwhere[] = ['orderid','=',$order['id']];
                $ogwhere[] = ['id','=',$goodsId];
                if($this->bid>0){
                    $ogwhere[] = ['bid','=',$this->bid];
                }
                //挂单发货
                if($ispending){
                    $ogwhere[] = ['status','=',22];
                }else{
                    $ogwhere[] = ['status','=',1];
                }
                Db::name('shop_order_goods')->where($ogwhere)->update($goodsUp);
            }
            //发货后重新计算价格
            $newGoodsList = Db::name('shop_order_goods')->where('aid',aid)->where('orderid',$order['id'])->select()->toArray();
            $fahuoNum = 0;
            foreach ($newGoodsList as $nk=>$ngoods){
                $price = $ngoods['real_sell_price'];
                $weight = $ngoods['real_total_weight']/500;
                $totalprice += round($price*$weight,2);
                if($ngoods['status']==2){
                    $fahuoNum++;
                }
            }

            //优惠信息
            $freight_price  = $order['freight_price'];
            $invoice_money  = $order['invoice_money'];
            $scoredk_money  = $order['scoredk_money'];
            $leveldk_money  = $order['leveldk_money'];
            $manjian_money  = $order['manjian_money'];
            $coupon_money  = $order['coupon_money'];
            $totalprice = max(0,$totalprice + $freight_price + $invoice_money - $scoredk_money - $leveldk_money - $manjian_money - $coupon_money);
            //发货订单处理
            $status1Count = count($newGoodsList);
            $orderUp['totalprice'] = $totalprice;
            $orderUp['send_time'] = time();
            if($fahuoNum==$status1Count){
                //全部已发货
                $orderUp['status'] = $status;//已发货
                $orderUp['freight_type'] = 1;
                $orderUp['express_isbufen'] = 0;
                //修改订单状态和金额
                Db::name('shop_order')->where('id',$order['id'])->update($orderUp);
                //前端用户下单且货品不足则退差价给用户
                if($order['platform']!='pc' && $order['mid']>0 && $totalprice<$order['totalprice']){
                    //自动退款
                    $refundMoney = $order['totalprice'] - $totalprice;
                    $rs = \app\commons\Order::refund($order,$refundMoney,'差额退款,订单号:'.$order['ordernum']);
                    /*if($rs['status']!=1){
                        return  json(['status'=>0,'msg'=>$rs['msg']]);
                    }*/
                }
            }else{
                $orderUp['express_isbufen'] = 1;//部分发货|挂单
                //修改订单状态和金额
                Db::name('shop_order')->where('id',$order['id'])->update($orderUp);
            }
            //扣除信用额度[这里前端下单的无需再次扣除]
            if($order['platform']=='pc' && $order['mid']>0 && $fahuoTotalprice>0){
                \app\commons\Member::addOverdraftMoney(aid,$order['mid'],-$fahuoTotalprice,'称重订单发货扣除');
            }
            $orderIds[] = $orderId;
            $rs = \app\commons\Wifiprint::print(aid,'shop',$order['id'],1,-1,-1,'shop_weight,shop');
        }
        \app\commons\System::plog('称重订单发货'.implode(',',$orderIds));
        return json(['status'=>1,'msg'=>'发货成功','orderids'=>implode(',',$orderIds).'n='.$fahuoNum.'s1='.$status1Count]);
    }

    public function getChildCustomer(){
        $id = input('param.id/d',0);
        $customerlist = Db::name('sh_customer')->where('aid',aid)->where('bid',$this->bid)->where('pid',$id)->order('sort desc,id desc')->select()->toArray();
        return json(['status'=>1,'datalist'=>$customerlist]);
    }
    public function saveOrderGoodsRemark(){
        $orderid = input('param.orderid/d');
        $ggid = input('param.id/d');
        $remark = input('param.remark');
        $where = [];
        $where[] = ['aid','=',aid];
        if($this->bid>0){
            $where[] = ['bid','=',$this->bid];
        }else{
            $bid = $this->bid;
            $where[] = Db::raw("bid={$bid} OR sync_bid={$bid}");
        }
        $order =  Db::name('shop_order')->where($where)->where('id',$orderid)->find();
        if(empty($order)){
            return json(['status'=>0,'msg'=>'数据权限不足']);
        }
        Db::name('shop_order_goods')->where('aid',aid)->where('orderid',$orderid)->where('id',$ggid)->update(['remark'=>$remark]);
        \app\commons\System::plog('修改订单备注orderid='.$orderid);
        return json(['status'=>1,'msg'=>'修改成功']);
    }

    //挂单
    public function pendingOrder(){
        $ids = input('post.ids/a',[]);
        if(empty($ids)){
            return json(['status'=>0,'msg'=>'请选择挂单数据']);
        }
        $where = [];
        $where[] = ['o.aid','=',aid];
        $where[] = ['og.status','=',1];//待发货的
        $where[] = ['og.id','in',$ids];
        if($this->bid>0){
            $where[] = ['o.bid','=',$this->bid];
        }else{
            $bid = $this->bid;
            $where[] = Db::raw("o.bid={$bid} OR o.sync_bid={$bid}");
        }
        $shopOrderGoods = Db::name('shop_order_goods')->alias('og')->join('shop_order o','og.orderid=o.id')->where($where)->field('og.*')->select()->toArray();
        if(empty($shopOrderGoods)){
            return json(['status'=>0,'msg'=>'暂无数据处理']);
        }
        $orderIds = array_column($shopOrderGoods,'orderid');
        $orderGoodsIds = array_column($shopOrderGoods,'id');
        Db::name('shop_order')->where('aid',aid)->where('id','in',$orderIds)->update(['express_isbufen'=>1]);
        Db::name('shop_order_goods')->where('aid',aid)->where('id','in',$orderGoodsIds)->update(['status'=>22]);
        \app\commons\System::plog('挂单orderid='.implode($orderIds));
        return json(['status'=>1,'msg'=>'挂单成功']);
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
        $page = input('param.page')?:1;
        $limit = input('param.limit')?:10;
        $where = [];
        $where[] = ['order.aid','=',aid];
        if($this->bid>0){
            $where[] = ['order.bid','=',$this->bid];
        }else{
            $bid = $this->bid;
            $where[] = Db::raw("order.bid={$bid} OR order.sync_bid={$bid}");
        }
        $where[] = ['order.product_type','=',2];
        if(input('param.mid')) $where[] = ['order.mid','=',input('param.mid')];
        if(input('param.keyword')) $where[] = ['order.ordernum|order.linkman|order.tel','like','%'.input('param.keyword').'%'];
        $status = input('param.status');
        if(is_numeric($status)){
            if($status>-1){
                if(input('param.status') == 5){
                    $where[] = ['refund_status','=',1];
                }elseif(input('param.status') == 6){
                    $where[] = ['refund_status','=',2];
                }elseif(input('param.status') == 7){
                    $where[] = ['refund_status','=',3];
                }elseif(input('param.status') == 22){
                    $where[] = ['status','=',2];
                    $where[] = ['express_isbufen','=',1];
                }else{
                    $where[] = ['status','=',input('param.status')];
                }
            }
        }else{
            $where[] = ['status','=',1];
        }
        if(input('param.ctime') ){
            $ctime = explode(' ~ ',input('param.ctime'));
            $where[] = ['order.createtime','>=',strtotime($ctime[0])];
            $where[] = ['order.createtime','<',strtotime($ctime[1]) + 86400];
        }
        $list = Db::name('shop_order')->alias('order')->field('order.*')->leftJoin('member member','member.id=order.mid')->where($where)->order($order)->page($page,$limit)->select()->toArray();
        $count = Db::name('shop_order')->alias('order')->field('order.*')->leftJoin('member member','member.id=order.mid')->where($where)->count();
        $bArr = Db::name('business')->where('aid',aid)->column('name','id');
        $customerArr = Db::name('sh_customer')->where('aid',aid)->column('name,remark','id');
        if(!$bArr) $bArr = [];
        $bArr['0'] = '自营';
        $title = array('会员','客户','商品名称','数量','重量(斤)','单价(元/斤)','总价','运费','订单号','订单预置备注','内部备注','所属商家','商户备注','电话','收货地址','商品编码','规格','积分抵扣','满额立减','优惠券优惠','付款方式','状态','退款状态','退款金额','下单时间');
        $data = [];
        $statusArr = [0=>'未支付',1=>'待发货',2=>'已发货',3=>'已收货',4=>'已关闭',12=>'已挂单',22=>'挂单未发货'];
        $totalNum = 0;
        $totalPrice = 0;
        $totalWeight = 0;
        $totalFreight = 0;
        foreach($list as $k=>$vo){
            $customer = [];
            $refund_status = '';
            $refund_money = '';
            if($vo['customer_id']>0){
                $customer = $customerArr[$vo['customer_id']]??[];
            }
            $oglist = Db::name('shop_order_goods')->where('orderid',$vo['id'])->select()->toArray();
            //$xm=array();
            foreach($oglist as $k2=>$og){
                $status= $statusArr[$og['status']]??$og['status'];
                $ogremark = '';
                if($og['gtype']==1){
                    $ogremark = '【赠品】';
                }
                $refund_status='';
                $refund_money = '';
                // 导出的订单里退款的商品退款记录
                $ro = Db::name('shop_refund_order')->where('orderid',$vo['id'])->select()->toArray();
                foreach ($ro as $v){
                    $isrefund = Db::name('shop_refund_order_goods')->where('refund_orderid',$v['id'])->find();
                    if($isrefund['ogid'] == $og['id']){
                        switch ($v['refund_status']){
                            case 0:
                                $refund_status = '退款已取消';
                                $refund_money  = $isrefund['refund_money'];
                                break;
                            case 1:
                                $refund_status = '退款待审核';
                                $refund_money  = $isrefund['refund_money'];
                                break;
                            case 2:
                                $refund_status = '已退款';
                                $refund_money  = $isrefund['refund_money'];
                                break;
                            case 3:
                                $refund_status = '退款驳回';
                                $refund_money  = $isrefund['refund_money'];
                                break;
                            case 4:
                                $refund_status = '审核通过，待退货';
                                $refund_money  = $isrefund['refund_money'];
                                break;
                            default:
                                $refund_status = '状态未找到';
                                $refund_money  = '0';
                                break;

                        }
                        break;
                    }
                }
                $ogstatus = $statusArr[$og['status']]??$og['status'];
                $barcode = '';
                if($og['barcode'])  $barcode = "(".$og['barcode'].")";
                $_weight = round($og['real_total_weight']/500,2);
                if($k2 == 0){
                    $tmpdata1 = [
                        ($vo['nickname']?$vo['nickname']."(mid={$vo['mid']})":''),
                        $vo['linkman'],
                        $og['name'].$og['ggname'].$ogremark,
                        $og['num'],
                        $_weight,
                        $og['real_sell_price'],
                        $og['real_totalprice'],
                        $vo['freight_price']??0,
                        ' '.$vo['ordernum'],
                        $vo['remark'],
                        $vo['remark_ext'],
                        $bArr[$vo['bid']],
                        ($vo['customer_id']?$customer[$vo['customer_id']]['remark']:''),
                        $vo['tel'],
                        $vo['area'].' '.$vo['address'],
                        $og['procode'],
                        $og['ggname'].$barcode,
                        $vo['scoredk_money'],
                        $vo['manjian_money'],
                        $vo['coupon_money'],
                        $vo['paytype'],
                        $status,
                        $refund_status,
                        $refund_money,
                        date('Y-m-d H:i:s',$vo['createtime'])
                    ];
                    $data[] = $tmpdata1;
                }else{
                    $tmpdata1 = [
                        '',
                        '',
                        $og['name'].$og['ggname'].$ogremark,
                        $og['num'],
                        $_weight,
                        $og['real_sell_price'],
                        $og['real_totalprice'],
                        0,
                        ' '.$vo['ordernum'],
                        $og['remark'],
                        $og['remark_ext'],
                        '',
                        '',
                        '',
                        '',
                        $og['procode'],
                        $og['ggname'].$barcode,
                        '',
                        '',
                        '',
                        '',
                        $status,
                        $refund_status,
                        $refund_money,
                        ''
                    ];
                    $data[] = $tmpdata1;
                }
                $totalNum = $totalNum + $og['num'];
                $totalPrice = $totalPrice + $og['real_totalprice'];
                $totalWeight = $totalWeight + $_weight;
            }
            $totalFreight = $totalFreight + $vo['freight_price'];
        }
        //'数量','重量(斤)','单价(元/斤)','总价','运费'
        $data[] =[
            '汇总',
            '',
            '',
            $totalNum,
            $totalWeight,
            '',
            number_format($totalPrice,2),
            $totalFreight,
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            ''
        ];
        return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data,'title'=>$title]);
        $this->export_excel($title,$data);
    }
    //订单详情
    public function getdetail(){
        $orderid = input('param.orderid');
        if($this->bid != 0){
            $order = Db::name('shop_order')->where('aid',aid)->where('bid',$this->bid)->where('id',$orderid)->find();
        }else{
            $order = Db::name('shop_order')->where('aid',aid)->where('id',$orderid)->find();
        }
        $order['school_info'] = '';
        if($order['coupon_rid']){
            $couponrecord = Db::name('coupon_record')->where('id',$order['coupon_rid'])->find();
            $couponnames = Db::name('coupon_record')->where('id','in',$order['coupon_rid'])->column('couponname');
            $couponnames = implode('，',$couponnames);
        }else{
            $couponrecord = false;
            $couponnames = '';
        }
        $oglist = Db::name('shop_order_goods')->where('aid',aid)->where('orderid',$orderid)->select()->toArray();
        $member = Db::name('member')->field('id,nickname,headimg,realname,tel,wxopenid,unionid')->where('id',$order['mid'])->find();
        if(!$member) $member = ['id'=>$order['mid'],'nickname'=>'','headimg'=>''];
        $comdata = array();
        $comdata['parent1'] = ['mid'=>'','nickname'=>'','headimg'=>'','money'=>0,'score'=>0];
        $comdata['parent2'] = ['mid'=>'','nickname'=>'','headimg'=>'','money'=>0,'score'=>0];
        $comdata['parent3'] = ['mid'=>'','nickname'=>'','headimg'=>'','money'=>0,'score'=>0];
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

        $order['formdata'] = \app\models\Freight::getformdata($order['id'],'shop_order');
        //弃用
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
        if($order['freight_type']==11){
            $order['freight_content'] = json_decode($order['freight_content'],true);
        }
        $miandanst = Db::name('admin_set')->where('aid',aid)->value('miandanst');
        if($this->bid==0 && $miandanst==1 && in_array('wx',$this->platform) && ($member['wxopenid'] || $member['unionid'])){ //可以使用小程序物流助手发货
            $canmiandan = 1;
        }else{
            $canmiandan = 0;
        }
        if($order['checkmemid']){
            $checkmember = Db::name('member')->field('id,nickname,headimg,realname,tel')->where('id',$order['checkmemid'])->find();
        }else{
            $checkmember = [];
        }

        $payorder = [];
        if($order['paytypeid'] == 5) {
            $payorder = Db::name('payorder')->where('id',$order['payorderid'])->where('aid',aid)->find();
            if($payorder) {
                if($payorder['check_status'] === 0) {
                    $payorder['check_status_label'] = '待审核';
                }elseif($payorder['check_status'] == 1) {
                    $payorder['check_status_label'] = '通过';
                }elseif($payorder['check_status'] == 2) {
                    $payorder['check_status_label'] = '驳回';
                }else{
                    $payorder['check_status_label'] = '未上传';
                }
                if($payorder['paypics']) {
                    $payorder['paypics'] = explode(',', $payorder['paypics']);
                    foreach ($payorder['paypics'] as $item) {
                        $payorder['paypics_html'] .= '<img src="'.$item.'" width="200" onclick="preview(this)"/>';
                    }
                }
            }
        }
        if($order['express_content']) $order['express_content'] = json_decode($order['express_content'],true);
        if($order['status'] == 1){
            $order['express_ogids'] = implode(',',$ogids);
        }
        if($order['express_ogids']){
            $order['express_ogids'] = explode(',',$order['express_ogids']);
        }else{
            $order['express_ogids'] = [];
        }
        foreach($order['express_content'] as $k=>$v){
            if(!$v['express_ogids']){
                $v['express_ogids'] = [];
            }else{
                $v['express_ogids'] = explode(',',$v['express_ogids']);
            }
            $order['express_content'][$k] = $v;
        }
        return json(['order'=>$order,'couponrecord'=>$couponrecord,'couponnames'=>$couponnames,'oglist'=>$oglist,'member'=>$member,'comdata'=>$comdata,'canmiandan'=>$canmiandan,'checkmember'=>$checkmember,'payorder' => $payorder]);
    }

}
