<?php


namespace app\commons;
use think\facade\Db;
use think\facade\Log;

class Wifiprint
{
    //打印小票 aid 模块 订单号
    /**
     * @param $aid
     * @param $type 订单类型 shop:商城
     * @param $orderid 订单id
     * @param int $autoprint 是否自动打印
     * @param int $machineType 打印机类型 0：小票打印机 1:标签打印机
     * @param int $bid
     * @param string $module 适用模块
     * @param string $brandType 打印机品牌 0:易联云 1:飞蛾 2：映美云
     * @param array $params 其他参数 如 'opttype'=>'collage_refund' 拼团退款操作
     * @return array
     */
    public static function print($aid,$type,$orderid,$autoprint=1,$machineType=-1,$bid=-1,$module='shop',$brandType=-1,$params = []){
        $order = db($type.'_order')->where('id',$orderid)->find();
        if($bid!=='' && $bid>-1){
            $bid = $bid;
        }else{
            $bid = $order['bid']??0;
        }

        $where = [];
        $where[] = ['aid', '=', $aid];
        $where[] = ['status', '=', 1];
        if($autoprint == 1) {
            $where[] = ['autoprint', '=', $autoprint];
        }
        if(is_numeric($machineType) && $machineType>-1){
            $where[] = ['machine_type', '=', $machineType];
        }
        if(is_numeric($brandType) && $brandType>-1){
            $where[] = ['type', '=', $brandType];
        }
        if(getcustom('wifiprint_bind_user') && uid>0){
            //该管理员绑定的打印机
            $print_ids = Db::name('wifiprint_user')->where('aid',$aid)->where('bid',$bid)->where('uid',uid)->column('print_id');
            if(empty($print_ids)) $print_ids = [];
            $print_ids[] = -1;//没有绑定打印机
            $where[] = ['id','in',$print_ids];
        }
        if(getcustom('restaurant_wifiprint_yingmeiyun')) {
            if(strpos($module, ',')!==false){
                $module = explode(',',$module);
                $where[] = ['module','in',$module];
            }else{
                $where[] = ['module','=',$module];
            }
        }
        $machinelist = db('wifiprint_set')->where($where)->where('bid',$bid)->select()->toArray(); //打印机列表
        if(!$machinelist) return ['status'=>0,'msg'=>'没有配置小票打印机'];
        //if($type == 'shop' && $order['bid']!=0) return [];
        $order['formdata'] = \app\models\Freight::getformdata($order['id'],$type.'_order');

        if(getcustom('sys_print_set')){
            //记录今天打印了几次
            $nowtime = strtotime(date("Y-m-d",time()));
            $printdaynum        = 'print_day_ordernum'.$aid.$bid.$nowtime;
            $print_day_ordernum = '';
            if($order['printdaynum']){
                if($type == 'shop' || $type == 'cashier' || $type == 'maidan' || $type == 'scoreshop'){
                    $print_day_ordernum = $order['printdaynum'];
                }
            }else{
                $print_day_ordernum = cache($printdaynum);
                if(!$print_day_ordernum || empty($print_day_ordernum)){
                    cache($printdaynum,1);
                    $print_day_ordernum = 1;
                }
            }
            //打印状态次数
            $print_status = 0;
        }

        if($params && $params['opttype']){
            //如果操作类型是拼团退款，则把$type重置为collage_refund
            if($params['opttype'] == 'collage_refund'){
                $type = 'collage_refund';
            }
        }

        $printnum = 0;
        foreach($machinelist as $machine){
            if(isset($order['freight_type']) && $order['freight_type']==0 && $machine['print_ps']==0){ //配送订单
                continue;
            }
            if($type == 'maidan' &&  $machine['print_maidan']==0){ //买单订单
                continue;
            }
            if($type == 'cashier' &&  $machine['print_cashdesk']==0){ //收银台订单
                continue;
            }
            if(getcustom('recharge_order_wifiprint')){
                if($type == 'recharge' &&  $machine['print_recharge']==0){ //充值订单
                    continue;
                }
            }
            if(getcustom('hexiao_auto_wifiprint')){
                if($params && $params['opttype'] =='hexiao'){
                    if(!$machine['print_hexiao'])  continue;
                }
            }
            if($order['freight_type']==1 || $order['freight_type']==5){ //自提订单
                if($machine['print_zt_type']==0) continue;
                if($machine['print_zt_type']==2){ //指定门店
                    $mdids = explode(',',$machine['print_zt_mdid']);
                    if(!in_array($order['mdid'],$mdids)) continue;
                }
            }
            $num = 1;
            if(getcustom('sys_print_set')){
                //打印次数
                $num =  $machine['print_num']?$machine['print_num']:1;
            }
            if(getcustom('restaurant_wifiprint_yingmeiyun')){
                if($order['product_type']==2 && $machine['type']==2 && $machine['module']!='shop_weight'){
                    continue;
                }
            }
            for($i=0;$i<$num;$i++){
                if($machine['type']==0){
                    $tmpltype = 0;
                    if(getcustom('sys_print_set')){
                        //易联云小票自定义（暂没使用，使用的是底部自定义）
                        $tmpltype =  $machine['tmpltype']?$machine['tmpltype']:0;
                    }
                    if(!$tmpltype){
                        $content = '';
                        $content = "<MS>1,".$machine['voice']."</MS>";
                        if(getcustom('sys_print_set')){
                            if($print_day_ordernum && $machine['day_ordernum']){
                                $content .=  '<FB><center>#'.$print_day_ordernum."</center></FB>\r\r";
                            }
                        }
                        $content .= "<center>@@2 ** ".$machine['title']." **</center>\r\r";
                        if($type=='shop_refund'){
                            $content .= "<center>申请退款</center>";
                            $order['refund_num'] = db('shop_refund_order_goods')->where('refund_orderid',$order['id'])->sum('refund_num');
                            $originalOrder = Db::name('shop_order')->where('aid',$order['aid'])->where('id',$order['orderid'])->find();
                            $order['num'] = db('shop_order_goods')->where('orderid',$order['orderid'])->sum('num');
                            $replaceField = ['num','freight_text','freight_time','linkman','tel','area','address','paytime','paytype','product_type','customer_id'];
                            foreach ($replaceField as $field){
                                $order[$field]  = $originalOrder[$field];
                            }
                        }else if($type=='collage_refund'){
                            $content .= "<center>申请退款</center>";
                            $order['refund_num'] = $order['num'];
                        }
                        if($type=='maidan'){
                            $content .= "订单标题：".$order['title']."\r";
                        }
                        $content .= "订单编号：".$order['ordernum']."\r";
                        if($type=='cashier' || $type=='maidan'){
                            //收银台无收货信息
                        }elseif($type=='yuyue' ){
                            if(getcustom('yuyue_order_wifiprint')){
                                $content .= "服务时间：".$order['yy_time']."\r";
                                $fwtype = $order['fwtype']==1?'到店服务':'上门服务';
                                $content .= "服务方式：".$fwtype."\r";
                                if($order['fwtype'] ==1){
                                    if($order['bid'] == 0){
                                        $bname = Db::name('admin_set')->where('aid',$order['aid'])->value('name');
                                    }else{
                                        $bname = Db::name('business')->where('id',$order['bid'])->value('name');
                                    }
                                    $content .= "服务门店:" .$bname."\r";
                                }
                                $master_order = db('yuyue_worker_order')->field('worker_id,status')->where(['orderid'=>$order['id'],'ordernum'=>$order['ordernum']])->find();
                                if($master_order && $master_order['worker_id']){
                                    $master = db('yuyue_worker')->where(['id'=>$master_order['worker_id']])->find();
                                    if($master){
                                        $content .= "服务人员：" . $master['realname'] . "\r";
                                        $content .= "服务人员电话：" . $master['tel'] . "\r";
                                    } else{
                                        $content .= "服务人员：未接单\r";
                                    }
                                }else{
                                    $content .= "服务人员：未接单\r";
                                }
                                if($order['fwtype'] ==2){
                                    $content .= "服务地址:" . $order['area'] . " " . $order['address'] . "\r";
                                } 
                                
                                $content .= "客户姓名:<FS>".$order['linkman']."</FS>\r";
                                $content .= "联系电话:<FS>".$order['tel']."</FS>\r";
                                if($fwtype ==2){
                                    $content .= "服务地址:<FS>".$order['area']." ".$order['address']."</FS>\r";
                                }
                            }
                        }else{
                            $content .= "配送方式：".$order['freight_text']."\r";
                            if($order['freight_time']){
                                $content .= "配送时间：<FS>".$order['freight_time']."</FS>\r";
                            }
                            $content .= "收货人:<FS>".$order['linkman']."</FS>\r";
                            $content .= "联系电话:<FS>".$order['tel']."</FS>\r";
                            $content .= "收货地址:<FS>".$order['area']." ".$order['address']."</FS>\r";
                        }
                        if($order['paytime']){
                            $content .= "付款时间：".date('Y-m-d H:i:s',$order['paytime'])."\r";
                        }
                        $content .= "付款方式：".$order['paytype']."\r\r";
                        $content .="--------------------------------\r";
                        if($type=='maidan'){ //买单没有商品信息
                            //买单没有商品信息
                        }elseif ($type=='cycle'){
                            $content .= "<table>";
                            $content .= "<tr><td>商品名称</td><td>数量</td><td>期数</td><td>总价</td></tr>";
                        }elseif($type=='shop' && isset($order['product_type']) && $order['product_type']==2){
                            //称重订单
                            $content .= "<table>";
                            $content .= "<tr><td>商品名称</td><td>单价</td><td>重量</td><td>总价</td></tr>";
                        }else{
                            $content .= "<table>";
                            $content .= "<tr><td>商品名称</td><td>数量</td><td>总价</td></tr>";
                        }
                        if($type == 'shop'){
                            $ordergoods = db($type.'_order_goods')->where('orderid',$order['id'])->select()->toArray();
                            foreach($ordergoods as $item){
								$product = db($type.'_product')->field('print_name')->where('id',$item['proid'])->find();
								$name = $product['print_name']?$product['print_name']:$item['name'];
                                if($type=='shop' && isset($order['product_type']) && $order['product_type']==2){
                                    $content .= "<tr><td><FB>".$name.'('.$item['ggname'].')'."</FB></td><td>".$item['real_sell_price']."</td><td>".$item['real_total_weight']."</td><td>".$item['real_totalprice']."</td></tr>";
                                }else{
                                    $content .= "<tr><td><FB>".$name.'('.$item['ggname'].')'."</FB></td><td>".$item['num']."</td><td>".$item['totalprice']."</td></tr>";
                                }
                                if(getcustom('product_glass')){
                                    //打印追加：视力档案输出
                                    $grrowArr = \app\models\ShopOrder::getGlassRecordRow($item);
                                    if($grrowArr){
                                        $row1 = str_replace('</p>','',str_replace('<p>','',$grrowArr['row1']));
                                        $row2 = str_replace('</p>','',str_replace('<p>','',$grrowArr['row2']));
                                        $row3 = str_replace('</p>','',str_replace('<p>','',$grrowArr['row3']));
                                        $content .= "<tr><td colspan='3'>".$row1."</td></tr>";
                                        $content .= "<tr><td colspan='3'>".$row2."</td></tr>";
                                        $content .= "<tr><td colspan='3'>".$row3."</td></tr>";
                                    }
                                }
                            }
                        }elseif($type == 'cycle'){
                            $content .= "<tr><td><FB>".$order['proname'].'('.$order['ggname'].')'."</FB></td><td>".$order['num']."</td><td>".$order['qsnum']."</td><td>".$order['totalprice']."</td></tr>";
                        }elseif($type=='collage'){
                            $content .= "<tr><td><FB>".$order['proname'].'('.$order['ggname'].')'."</FB></td><td>".$order['num']."</td><td>".$order['totalprice']."</td></tr>";
                        }elseif($type=='lucky_collage'){
                            $content .= "<tr><td><FB>".$order['proname'].'('.$order['ggname'].')'."</FB></td><td>".$order['num']."</td><td>".$order['totalprice']."</td></tr>";
                        }elseif($type=='seckill'){
                            $content .= "<tr><td><FB>".$order['proname'].'('.$order['ggname'].')'."</FB></td><td>".$order['num']."</td><td>".$order['totalprice']."</td></tr>";
                        }elseif($type=='kanjia'){
                            $content .= "<tr><td>".$order['proname']."</td><td>1</td><td>".$order['totalprice']."</td></tr>";
                        }elseif($type=='tuangou'){
                            $content .= "<tr><td>".$order['proname']."</td><td>".$order['num']."</td><td>".$order['totalprice']."</td></tr>";
                        }elseif($type=='scoreshop'){

                            $ordergoods = db('scoreshop_order_goods')->where('orderid',$order['id'])->select()->toArray();
                            foreach($ordergoods as $item){
                                if($item['totalmoney'] > 0 && $item['totalscore'] > 0){
                                    $price = $item['totalmoney']."元+".$item['totalscore'].t('积分');
                                }elseif($item['totalmoney'] > 0){
                                    $price = $item['totalmoney']."元";
                                }else{
                                    $price = $item['totalscore'].t('积分');
                                }

                                $content .= "<tr><td><FB>".$item['name']."</FB></td><td>".$item['num']." </td><td>".$price."</td></tr>";
                            }
                        }elseif($type == 'shop_refund'){
                            $ordergoods = db($type.'_order_goods')->where('refund_orderid',$order['id'])->select()->toArray();
                            foreach($ordergoods as $item){
                                $content .= "<tr><td><FB>".$item['name']."(".$item['ggname'].")</FB></td><td>".$item['refund_num']." </td><td>".$item['refund_money']."</td></tr>";
                            }
                        }elseif($type=='cashier'){
                            $ordergoods = db('cashier_order_goods')->where('orderid',$order['id'])->select()->toArray();
                            foreach($ordergoods as $item){
                                $content .= "<tr><td><FB>".$item['proname'].'('.$item['ggname'].')'."</FB></td><td>".$item['num']."</td><td>".$item['totalprice']."</td></tr>";
                            }
                            $order['message'] = $order['remark'];
                        } elseif($type=='yuyue' ){
                            if(getcustom('yuyue_order_wifiprint')){
                                $content .= "<tr><td><FB>".$order['proname'].'('.$order['ggname'].')'."</FB></td><td>".$order['num']."</td><td>".$order['totalprice']."</td></tr>";
                            }
                        }elseif($type == 'collage_refund'){
                            $content .= "<tr><td><FB>".$order['proname']."(".$order['ggname'].")</FB></td><td>".$order['num']." </td><td>".$order['refund_money']."</td></tr>";
                        }
                        if($type=='maidan'){ //买单没有商品信息
                            //买单没有商品信息
                        }else{
                            $content .= "</table>";
                            $content .= "\r";
                        }
                       // if($type=='shop'){
                       //     $order['message'] = \app\models\ShopOrder::checkOrderMessage($orderid,$order);
                       //     if($order['message']){
                       //         $content .= "备注：<FS>".$order['message']."</FS>\r";
                       //     }else{
                       //         //$content .= "备注：无\r";
                       //     }

                       // }
                       
                        if($type=='scoreshop'){
                            $content .="--------------------------------\r";
                            $content .= "<LR>实付金额：,".$price."</LR>";
                        }elseif($type=='cashier'){
                            $content .="--------------------------------\r";
                            $content .= "<LR>订单总价：,".dd_money_format($order['pre_totalprice'])."</LR>";
                            if($order['scoredk_money']>0){
                                $content .= "<LR>积分抵扣：,".$order['scoredk_money']."</LR>";
                            }
                            if($order['coupon_money']>0) {
                                $content .= "<LR>优惠券抵扣：," . dd_money_format($order['coupon_money']) . "</LR>";
                            }
                            if($order['leveldk_money']>0) {
                                $content .= "<LR>会员折扣：," . dd_money_format($order['leveldk_money']) . "</LR>";
                            }
                            if($order['moling_money']>0) {
                                $content .= "<LR>抹零金额：," . dd_money_format($order['moling_money']) . "</LR>";
                            }
                            $content .= "<LR>实付金额：,".dd_money_format($order['totalprice'])."</LR>";
                        }elseif($type=='maidan'){
                            $content .= "<LR>订单总价：,".dd_money_format($order['money'])."</LR>";
                            if($order['scoredk_money']>0){
                                $content .= "<LR>积分抵扣：,".$order['scoredk']."</LR>";
                            }
                            if($order['leveldk_money']>0) {
                                $content .= "<LR>会员折扣：," . dd_money_format($order['disprice']) . "</LR>";
                            }
                            if($order['decmoney']>0) {
                                $content .= "<LR>余额抵扣：," . dd_money_format($order['decmoney'] ). "</LR>";
                            }
                            if($order['couponmoney']>0) {
                                $content .= "<LR>优惠券抵扣：," . dd_money_format($order['couponmoney']) . "</LR>";
                            }
                            $content .= "<LR>实付金额：,".dd_money_format($order['paymoney'])."</LR>";
                        }elseif($type=='yuyue'){
                            if(getcustom('yuyue_order_wifiprint')) {
                                $content .= "--------------------------------\r";
                                if ($order['balance_price'] > 0) {
                                    $content .= "<LR>尾款金额：" . dd_money_format($order['balance_price']) . "</LR>";
                                }
                                $content .= "<LR>实付金额：" . dd_money_format($order['totalprice']) . "</LR>";
                            }
                        }elseif($type=='shop_refund' || $type=='collage_refund'){
                            $content .="--------------------------------\r";
                            $content .= "<LR>退款金额：".dd_money_format($order['refund_money'])."</LR>";
                        }else{
                            $content .="--------------------------------\r";
                            $content .= "<LR>实付金额：".dd_money_format($order['totalprice'])."</LR>";
                        }
                        if($order['formdata']) {
                            foreach ($order['formdata'] as $formdata) {
                                if($formdata[2] != 'upload') {
                                    if($formdata[0] == '备注') {
                                        $content .= $formdata[0]."：<FS>".$formdata[1]."</FS>\r\r";
                                    } else {
                                        $content .= $formdata[0]."：<FS>".$formdata[1]."</FS>\r";
                                    }
                                }
                            }
                        }
                        if(getcustom('sys_print_set')){
                            //易联云小票底部自定义
                            $boot_custom =  $machine['boot_custom']?$machine['boot_custom']:0;
                            if($boot_custom){
                                $deal_content = \app\customs\WifiprintCustom::deal_content($type,$order,$machine,$print_day_ordernum,2);
                                if($deal_content && $deal_content['status'] == 1){
                                    $content .= "\r\r".$deal_content['data'];
                                }
                            }
                        }
                        $content .= "\r\r";
                        $rs = self::yilianyun_print($machine['client_id'],$machine['client_secret'],$machine['access_token'],$machine['machine_code'],$machine['msign'],$content);
                        $printnum++;
                    }else{
                        if(getcustom('sys_print_set')){
                            //易联云小票自定义
                            $deal_content = \app\customs\WifiprintCustom::deal_content($type,$order,$machine,$print_day_ordernum,1);
                            if($deal_content && $deal_content['status'] == 1){
                                $content = $deal_content['data'];

                                //易联云小票底部自定义
                                $boot_custom =  $machine['boot_custom']?$machine['boot_custom']:0;
                                if($boot_custom){
                                    $deal_content = \app\customs\WifiprintCustom::deal_content($type,$order,$machine,$print_day_ordernum,2);
                                    if($deal_content && $deal_content['status'] == 1){
                                        $content .= "\r\r".$deal_content['data'];
                                    }
                                }

                                $content .= "\r\r";
                                $rs = self::yilianyun_print($machine['client_id'],$machine['client_secret'],$machine['access_token'],$machine['machine_code'],$machine['msign'],$content);
                                $printnum++;
                            }else{
                                continue;
                            }
                        }
                    }
                }
                elseif($machine['type']==1 || $machine['type']==3 || $machine['type']==4){
                    if($machine['machine_type']==1){ //标签打印机
                        if($machine['type']==3){
                            continue;//大趋打印机无标签
                        }
                        if($type == 'shop'){
                            $ordergoods = db($type.'_order_goods')->where('orderid',$order['id'])->select()->toArray();
                            $count = count($ordergoods);
                            $showType = 'shop';
                            //称重商品打印
                            if(getcustom('product_weight') && $order['product_type']==2){
                                $showType = 'weightShop';
                                if($order['customer_id']>0){
                                    $customer = Db::name('sh_customer')->where('id',$order['customer_id'])->find();
                                    $pcustomer = [];
                                    if($customer['pid']>0){
                                        $pcustomer = Db::name('sh_customer')->where('id',$customer['pid'])->find();
                                    }
                                }elseif($order['mid']>0){
                                    $member = Db::name('member')->where('aid',$aid)->where('id',$order['mid'])->find();
                                }
                                $count = count($ordergoods);
                                foreach ($ordergoods as $k=>$item){
                                   // [只打印已发货的有效订单]
                                    if(in_array($item['status'],[0,1,4])){
                                        continue;
                                    }
                                    $content = '';
                                    $content.= '<TEXT x="5" y="20" font="12" w="1" h="1" r="0">#'.$order['ordernum'].'('.($k+1).'/'.$count.')</TEXT>';
                                    $height = 20;
                                    $stepLen = 30;
                                    if($order['customer_id']>0){
                                        if($pcustomer){
                                            $height = $height+$stepLen;
                                            //$content.= '<B x="9" y="'.$height.'">客户：'.$pcustomer['name'].'</B>';
                                            $content.= '<TEXT x="5" y="'.$height.'" font="12" w="2" h="2" r="0">'.$pcustomer['name'].'</TEXT>';
                                        }
                                        if($customer){
                                            $height = $height+2*$stepLen;
                                            $customerName = $customer['name'].($customer['number']?'('.$customer['number'].')':'');
                                            $content.= '<TEXT x="5" y="'.$height.'" font="12" w="2" h="2" r="0">'.$customerName.'</TEXT>';
                                        }
                                    }else if($order['mid']>0){
                                        $height = $height+$stepLen;
                                        $content.= '<TEXT x="5" y="'.$height.'" font="12" w="2" h="2" r="0">客户：'.$member['nickname'].'</TEXT>';
                                    }

                                    $height = $height + 2*$stepLen;
                                    $content .= '<TEXT x="5" y="'.$height.'" font="12" w="2" h="2" r="0">'.$item['name'].$item['ggname'].'</TEXT>';
                                    $height = $height + $stepLen*2;
                                    /*$content .= '<TEXT x="9" y="'.$height.'" font="12" w="1" h="1" r="0">'.$item['real_sell_price'].'元/斤,'.round($item['real_total_weight']/500,2).'斤,￥'.$item['real_totalprice'].'</TEXT>';*/
                                    $content .= '<TEXT x="5" y="'.$height.'" font="12" w="1" h="1" r="0">购买数量：'.$item['num'].'  ，  分拣重量：'.round($item['real_total_weight']/500,2).'斤</TEXT>';
                                    $height = $height + $stepLen;
                                    $content .= '<TEXT x="5" y="'.$height.'" font="12" w="2" h="2" r="0">'.$item['remark'].'</TEXT>';
                                    if($machine['type']==1){
                                        $rs = self::feie_print($machine['client_id'],$machine['client_secret'],$machine['machine_code'],$machine['msign'],$content,1);
                                    }else if($machine['type']==4){
                                        $voice = 1;//默认静音
                                        if($order['status'] == 1){
                                            $voice = 2;//来单播报
                                        }
                                        $rs = self::xinye_print($machine['client_id'],$machine['client_secret'],$machine['machine_code'],$content,$voice);
                                    }
                                    $printnum++;
                                }

                            }
                            if($showType=='shop'){
                                foreach($ordergoods as $k=>$item){
									$product = db($showType.'_product')->field('print_name')->where('id',$item['proid'])->find();
									$name = $product['print_name']?$product['print_name']:$item['name'];
                                    $content = '';
                                    $content .= '<TEXT x="9" y="5" font="12" w="1" h="1" r="0">#'.$order['ordernum'].' '.($k+1).'/'.$count.'</TEXT>';
                                    $content .= '<TEXT x="9" y="40" font="12" w="1" h="1" r="0">'.$name.'</TEXT>';
                                    $content .= '<TEXT x="9" y="70" font="12" w="1" h="1" r="0">'.$item['ggname'] .' × '.$item['num'].'  共'.$item['totalprice'].'元</TEXT>';
                                    $content .= '<TEXT x="9" y="100" font="12" w="1" h="1" r="0">配送方式:'.$order['freight_text'].'</TEXT>';
                                    $content .= '<TEXT x="9" y="130" font="12" w="1" h="1" r="0">收货人:'.$order['linkman'] .'('.$order['tel'].')</TEXT>';
                                    $content .= '<TEXT x="9" y="155" font="12" w="1" h="1" r="0">'.$order['area'].'</TEXT>';
                                    $content .= '<TEXT x="9" y="180" font="12" w="1" h="1" r="0">'.$order['address'].'</TEXT>';
                                    $content .= '<TEXT x="9" y="210" font="12" w="1" h="1" r="0">实付金额：'.$order['totalprice'].'</TEXT>';

                                    if($machine['print_qrcode'] == 1){
                                        if($machine['type']==1){
                                            if($item['barcode']){
                                                $content .= '<QR x="270"  y="210"  e="L"  w="5">'.$item['barcode'].'</QR>';
                                            }elseif($item['procode']){
                                                $content .= '<QR x="270"  y="210"  e="L"  w="5">'.$item['procode'].'</QR>';
                                            }
                                        }else if($machine['type']==4){
                                            if($item['barcode']){
                                                $content .= '<QRC x="270"  y="210"  e="L"  w="5">'.$item['barcode'].'</QRC>';
                                            }elseif($item['procode']){
                                                $content .= '<QRC x="270"  y="210"  e="L"  w="5">'.$item['procode'].'</QRC>';
                                            }
                                        }
                                    }
                                    if(getcustom('print_label_barcode')){
                                        if($machine['print_barcode'] == 1){
                                            if($machine['print_qrcode']){
                                                if($item['barcode']){
                                                    $content .= '<BC128 x="9" y="320" s="1" r="0" n="2" w="4">'.$item['barcode'].'</BC128>';
                                                }elseif($item['procode']){
                                                    $content .= '<BC128 x="9" y="320" s="1" r="0" n="2" w="4">'.$item['procode'].'</BC128>';
                                                }
                                            }else{
                                                if($item['barcode']){
                                                    $content .= '<BC128 x="9" y="240" s="1" r="0" n="2" w="4">'.$item['barcode'].'</BC128>';
                                                }elseif($item['procode']){
                                                    $content .= '<BC128 x="9" y="240" s="1" r="0" n="2" w="4">'.$item['procode'].'</BC128>';
                                                }
                                            }
                                        }
                                    }
                                    if($machine['type']==1){
                                        $rs = self::feie_print($machine['client_id'],$machine['client_secret'],$machine['machine_code'],$machine['msign'],$content,1);
                                    }else if($machine['type']==4){
                                        $voice = 1;//默认静音
                                        if($order['status'] == 1){
                                            $voice = 2;//来单播报
                                        }
                                        $rs = self::xinye_print($machine['client_id'],$machine['client_secret'],$machine['machine_code'],$content,$voice);
                                    }
                                    $printnum++;
                                }
                            }
                        }elseif($type=='collage'){
                            $content = '';
                            $content .= '<TEXT x="9" y="5" font="12" w="1" h="1" r="0">#'.$order['ordernum'].' '.($k+1).'/'.$count.'</TEXT>';
                            $content .= '<TEXT x="9" y="40" font="12" w="1" h="1" r="0">'.$order['proname'].'</TEXT>';
                            $content .= '<TEXT x="9" y="70" font="12" w="1" h="1" r="0">    × '.$order['num'].'  共'.$order['totalprice'].'元</TEXT>';
                            $content .= '<TEXT x="9" y="100" font="12" w="1" h="1" r="0">配送方式:'.$order['freight_text'].'</TEXT>';
                            $content .= '<TEXT x="9" y="130" font="12" w="1" h="1" r="0">收货人:'.$order['linkman'] .'('.$order['tel'].')</TEXT>';
                            $content .= '<TEXT x="9" y="155" font="12" w="1" h="1" r="0">'.$order['area'].'</TEXT>';
                            $content .= '<TEXT x="9" y="180" font="12" w="1" h="1" r="0">'.$order['address'].'</TEXT>';
                            $content .= '<TEXT x="9" y="210" font="12" w="1" h="1" r="0">实付金额：'.$order['totalprice'].'</TEXT>';
                            if($machine['type']==1){
                                $rs = self::feie_print($machine['client_id'],$machine['client_secret'],$machine['machine_code'],$machine['msign'],$content,1);
                            }else if($machine['type']==4){
                                $voice = 1;//默认静音
                                if($order['status'] == 1){
                                    $voice = 2;//来单播报
                                }
                                $rs = self::xinye_print($machine['client_id'],$machine['client_secret'],$machine['machine_code'],$content,$voice);
                            }
                            $printnum++;
                        }elseif($type=='lucky_collage'){
                            $content = '';
                            $content .= '<TEXT x="9" y="5" font="12" w="1" h="1" r="0">#'.$order['ordernum'].' '.($k+1).'/'.$count.'</TEXT>';
                            $content .= '<TEXT x="9" y="40" font="12" w="1" h="1" r="0">'.$order['proname'].'</TEXT>';
                            $content .= '<TEXT x="9" y="70" font="12" w="1" h="1" r="0">    × '.$order['num'].'  共'.$order['totalprice'].'元</TEXT>';
                            $content .= '<TEXT x="9" y="100" font="12" w="1" h="1" r="0">配送方式:'.$order['freight_text'].'</TEXT>';
                            $content .= '<TEXT x="9" y="130" font="12" w="1" h="1" r="0">收货人:'.$order['linkman'] .'('.$order['tel'].')</TEXT>';
                            $content .= '<TEXT x="9" y="155" font="12" w="1" h="1" r="0">'.$order['area'].'</TEXT>';
                            $content .= '<TEXT x="9" y="180" font="12" w="1" h="1" r="0">'.$order['address'].'</TEXT>';
                            $content .= '<TEXT x="9" y="210" font="12" w="1" h="1" r="0">实付金额：'.$order['totalprice'].'</TEXT>';
                            if($machine['type']==1){
                                $rs = self::feie_print($machine['client_id'],$machine['client_secret'],$machine['machine_code'],$machine['msign'],$content,1);
                            }else if($machine['type']==4){
                                $voice = 1;//默认静音
                                if($order['status'] == 1){
                                    $voice = 2;//来单播报
                                }
                                $rs = self::xinye_print($machine['client_id'],$machine['client_secret'],$machine['machine_code'],$content,$voice);
                            }
                            $printnum++;
                        }elseif($type=='seckill'){
                            $content = '';
                            $content .= '<TEXT x="9" y="5" font="12" w="1" h="1" r="0">#'.$order['ordernum'].' '.($k+1).'/'.$count.'</TEXT>';
                            $content .= '<TEXT x="9" y="40" font="12" w="1" h="1" r="0">'.$order['proname'].'</TEXT>';
                            $content .= '<TEXT x="9" y="70" font="12" w="1" h="1" r="0">    × '.$order['num'].'  共'.$order['totalprice'].'元</TEXT>';
                            $content .= '<TEXT x="9" y="100" font="12" w="1" h="1" r="0">配送方式:'.$order['freight_text'].'</TEXT>';
                            $content .= '<TEXT x="9" y="130" font="12" w="1" h="1" r="0">收货人:'.$order['linkman'] .'('.$order['tel'].')</TEXT>';
                            $content .= '<TEXT x="9" y="155" font="12" w="1" h="1" r="0">'.$order['area'].'</TEXT>';
                            $content .= '<TEXT x="9" y="180" font="12" w="1" h="1" r="0">'.$order['address'].'</TEXT>';
                            $content .= '<TEXT x="9" y="210" font="12" w="1" h="1" r="0">实付金额：'.$order['totalprice'].'</TEXT>';
                            if($machine['type']==1){
                                $rs = self::feie_print($machine['client_id'],$machine['client_secret'],$machine['machine_code'],$machine['msign'],$content,1);
                            }else if($machine['type']==4){

                                $voice = 1;//默认静音
                                if($order['status'] == 1){
                                    $voice = 2;//来单播报
                                }
                                $rs = self::xinye_print($machine['client_id'],$machine['client_secret'],$machine['machine_code'],$content,$voice);
                            }
                            $printnum++;
                        }elseif($type=='kanjia'){
                            $content = '';
                            $content .= '<TEXT x="9" y="5" font="12" w="1" h="1" r="0">#'.$order['ordernum'].' '.($k+1).'/'.$count.'</TEXT>';
                            $content .= '<TEXT x="9" y="40" font="12" w="1" h="1" r="0">'.$order['proname'].'</TEXT>';
                            $content .= '<TEXT x="9" y="70" font="12" w="1" h="1" r="0">    × 1  共'.$order['totalprice'].'元</TEXT>';
                            $content .= '<TEXT x="9" y="100" font="12" w="1" h="1" r="0">配送方式:'.$order['freight_text'].'</TEXT>';
                            $content .= '<TEXT x="9" y="130" font="12" w="1" h="1" r="0">收货人:'.$order['linkman'] .'('.$order['tel'].')</TEXT>';
                            $content .= '<TEXT x="9" y="155" font="12" w="1" h="1" r="0">'.$order['area'].'</TEXT>';
                            $content .= '<TEXT x="9" y="180" font="12" w="1" h="1" r="0">'.$order['address'].'</TEXT>';
                            $content .= '<TEXT x="9" y="210" font="12" w="1" h="1" r="0">实付金额：'.$order['totalprice'].'</TEXT>';
                            if($machine['type']==1){
                                $rs = self::feie_print($machine['client_id'],$machine['client_secret'],$machine['machine_code'],$machine['msign'],$content,1);
                            }else if($machine['type']==4){
                                $voice = 1;//默认静音
                                if($order['status'] == 1){
                                    $voice = 2;//来单播报
                                }
                                $rs = self::xinye_print($machine['client_id'],$machine['client_secret'],$machine['machine_code'],$content,$voice);
                            }
                            $printnum++;
                        }elseif($type=='tuangou'){
                            $content = '';
                            $content .= '<TEXT x="9" y="5" font="12" w="1" h="1" r="0">#'.$order['ordernum'].' '.($k+1).'/'.$count.'</TEXT>';
                            $content .= '<TEXT x="9" y="40" font="12" w="1" h="1" r="0">'.$order['proname'].'</TEXT>';
                            $content .= '<TEXT x="9" y="70" font="12" w="1" h="1" r="0">    × '.$order['num'].'  共'.$order['totalprice'].'元</TEXT>';
                            $content .= '<TEXT x="9" y="100" font="12" w="1" h="1" r="0">配送方式:'.$order['freight_text'].'</TEXT>';
                            $content .= '<TEXT x="9" y="130" font="12" w="1" h="1" r="0">收货人:'.$order['linkman'] .'('.$order['tel'].')</TEXT>';
                            $content .= '<TEXT x="9" y="155" font="12" w="1" h="1" r="0">'.$order['area'].'</TEXT>';
                            $content .= '<TEXT x="9" y="180" font="12" w="1" h="1" r="0">'.$order['address'].'</TEXT>';
                            $content .= '<TEXT x="9" y="210" font="12" w="1" h="1" r="0">实付金额：'.$order['totalprice'].'</TEXT>';
                            if($machine['type']==1){
                                $rs = self::feie_print($machine['client_id'],$machine['client_secret'],$machine['machine_code'],$machine['msign'],$content,1);
                            }else if($machine['type']==4){
                                $voice = 1;//默认静音
                                if($order['status'] == 1){
                                    $voice = 2;//来单播报
                                }
                                $rs = self::xinye_print($machine['client_id'],$machine['client_secret'],$machine['machine_code'],$content,$voice);
                            }
                            $printnum++;
                        }elseif($type=='scoreshop'){
                            $content = '';
                            $content .= '<TEXT x="9" y="5" font="12" w="1" h="1" r="0">#'.$order['ordernum'].' 1/1</TEXT>';
                            $content .= '<TEXT x="9" y="40" font="12" w="1" h="1" r="0">'.$order['proname'].'</TEXT>';

                            if($order['totalprice'] > 0 && $order['score_price'] > 0){
                                $price = $order['totalprice']."元+".$order['score_price'].t('积分');
                            }elseif($order['totalprice'] > 0){
                                $price = $order['totalprice']."元";
                            }else{
                                $price = $order['score_price'].t('积分');
                            }
                            $content .= '<TEXT x="9" y="70" font="12" w="1" h="1" r="0"> ×'.$order['num'].' 共'.$price.'</TEXT>';
                            $content .= '<TEXT x="9" y="100" font="12" w="1" h="1" r="0">配送方式:'.$order['freight_text'].'</TEXT>';
                            $content .= '<TEXT x="9" y="130" font="12" w="1" h="1" r="0">收货人:'.$order['linkman'] .'('.$order['tel'].')</TEXT>';
                            $content .= '<TEXT x="9" y="155" font="12" w="1" h="1" r="0">'.$order['area'].'</TEXT>';
                            $content .= '<TEXT x="9" y="180" font="12" w="1" h="1" r="0">'.$order['address'].'</TEXT>';
                            $content .= '<TEXT x="9" y="210" font="12" w="1" h="1" r="0">实付金额：'.$order['totalprice'].'</TEXT>';
                            if($machine['type']==1){
                                $rs = self::feie_print($machine['client_id'],$machine['client_secret'],$machine['machine_code'],$machine['msign'],$content,1);
                            }else if($machine['type']==4){
                                $voice = 1;//默认静音
                                if($order['status'] == 1){
                                    $voice = 2;//来单播报
                                }
                                $rs = self::xinye_print($machine['client_id'],$machine['client_secret'],$machine['machine_code'],$content,$voice);
                            }
                            $printnum++;
                        }elseif($type == 'cashier'){
                            $ordergoods = db($type.'_order_goods')->where('orderid',$order['id'])->select()->toArray();
                            $count = count($ordergoods);
                            foreach($ordergoods as $k=>$item){
                                $content = '';
                                $content .= '<TEXT x="9" y="5" font="12" w="1" h="1" r="0">#'.$order['ordernum'].' '.($k+1).'/'.$count.'</TEXT>';
                                $content .= '<TEXT x="9" y="40" font="12" w="1" h="1" r="0">'.$item['name'].'</TEXT>';
                                $content .= '<TEXT x="9" y="70" font="12" w="1" h="1" r="0">'.$item['ggname'] .' × '.$item['num'].'  共'.$item['totalprice'].'元</TEXT>';
                                $content .= '<TEXT x="9" y="100" font="12" w="1" h="1" r="0">优惠券抵扣:'.$order['coupon_money'].'</TEXT>';
                                $content .= '<TEXT x="9" y="130" font="12" w="1" h="1" r="0">会员折扣:'.$order['leveldk_money'].'</TEXT>';
                                $content .= '<TEXT x="9" y="155" font="12" w="1" h="1" r="0">积分抵扣:'.$order['scoredk_money'].'</TEXT>';
                                $content .= '<TEXT x="9" y="180" font="12" w="1" h="1" r="0">抹零金额:'.$order['moling_money'].'</TEXT>';
                                $content .= '<TEXT x="9" y="210" font="12" w="1" h="1" r="0">订单总价：'.$order['pre_totalprice'].'</TEXT>';
                                $content .= '<TEXT x="9" y="240" font="12" w="1" h="1" r="0">实付金额：'.$order['totalprice'].'</TEXT>';
                                if($machine['type']==1){
                                    $rs = self::feie_print($machine['client_id'],$machine['client_secret'],$machine['machine_code'],$machine['msign'],$content,1);
                                }else if($machine['type']==4){
                                    $voice = 1;//默认静音
                                    if($order['status'] == 1){
                                        $voice = 2;//来单播报
                                    }
                                    $rs = self::xinye_print($machine['client_id'],$machine['client_secret'],$machine['machine_code'],$content,$voice);
                                }
                                $printnum++;
                            }
                        }elseif($type == 'maidan'){
                            $content = '';
                            $content .= '<TEXT x="9" y="5" font="12" w="1" h="1" r="0">#'.$order['ordernum'].'</TEXT>';
                            $content .= '<TEXT x="9" y="40" font="12" w="1" h="1" r="0">'.$order['title'].'</TEXT>';
                            $content .= '<TEXT x="9" y="100" font="12" w="1" h="1" r="0">优惠券抵扣:'.$order['couponmoney'].'</TEXT>';
                            $content .= '<TEXT x="9" y="130" font="12" w="1" h="1" r="0">会员折扣:'.$order['disprice'].'</TEXT>';
                            $content .= '<TEXT x="9" y="155" font="12" w="1" h="1" r="0">积分抵扣:'.$order['scoredk'].'</TEXT>';
                            $content .= '<TEXT x="9" y="210" font="12" w="1" h="1" r="0">订单总价：'.$order['money'].'</TEXT>';
                            $content .= '<TEXT x="9" y="240" font="12" w="1" h="1" r="0">实付金额：'.$order['paymoney'].'</TEXT>';
                            if($machine['type']==1){
                                $rs = self::feie_print($machine['client_id'],$machine['client_secret'],$machine['machine_code'],$machine['msign'],$content,1);
                            }else if($machine['type']==4){
                                $voice = 1;//默认静音
                                if($order['status'] == 1){
                                    $voice = 2;//来单播报
                                }
                                $rs = self::xinye_print($machine['client_id'],$machine['client_secret'],$machine['machine_code'],$content,$voice);
                            }
                            $printnum++;
                        }
                    }else{
                        $otherRemark = '';
                        //自定义模板
                        if($machine['tmpltype']==1){
                            if($type == 'shop'){
                                $ordergoods = db('shop_order_goods')->where('orderid',$order['id'])->select()->toArray();
                                $order['num'] = db('shop_order_goods')->where('orderid',$order['id'])->sum('num');
                            }elseif($type=='scoreshop'){
                                $ordergoods = db('scoreshop_order_goods')->where('orderid',$order['id'])->select()->toArray();
                                $order['num'] = db('scoreshop_order_goods')->where('orderid',$order['id'])->sum('num');
                                if($order['totalprice'] > 0 && $order['score_price'] > 0){
                                    $order['totalprice'] = $order['totalprice']."元 + ".$order['score_price'].t('积分');
                                }elseif($order['totalprice'] > 0){
                                    $order['totalprice'] = $order['totalprice']."元";
                                }else{
                                    $order['totalprice'] = $order['score_price'].t('积分');
                                }
                            }elseif($type=='shop_refund'){
                                $ordergoods = db('shop_refund_order_goods')->where('refund_orderid',$order['id'])->field('*,refund_num as num,refund_money as totalprice')->select()->toArray();
                                $order['refund_num'] = db('shop_refund_order_goods')->where('refund_orderid',$order['id'])->sum('refund_num');
                                $originalOrder = Db::name('shop_order')->where('aid',$order['aid'])->where('id',$order['orderid'])->find();
                                $originalOrder['num'] = db('shop_order_goods')->where('orderid',$order['orderid'])->sum('num');
                                $order['product_type'] = $originalOrder['product_type'];
                                $order['customer_id'] = $originalOrder['customer_id']??0;
                                $otherRemark = '申请退款';
                            }elseif($type == 'cashier'){
                                $ordergoods = db('cashier_order_goods')->where('orderid',$order['id'])->select()->toArray();
                                $order['num'] = db('cashier_order_goods')->where('orderid',$order['id'])->sum('num');
                            }elseif($type=='collage_refund'){
                                $order['refund_num'] = $order['num'];
                                $otherRemark         = '申请退款';
                            }else{
                                $ordergoods = [['name'=>$order['proname'],'ggname'=>$order['ggname'],'num'=>$order['num'],'totalprice'=>$order['product_price']]];
                            }

                            $message = '';
                            if(isset($order['formdata']) && $order['formdata']) {
                                foreach ($order['formdata'] as $formdata) {
                                    if($formdata[2] != 'upload') {
                                        if($formdata[0] == '备注') {
                                            $message = $formdata[1];
                                        }
                                    }
                                }
                            }

                            $tmplcontent = $machine['tmplcontent'];
                            if(strpos($tmplcontent,'<PLOOP>')!==false){
                                $tmplcontentArr = explode('<PLOOP>',$tmplcontent);
                                $tmplcontent1 = $tmplcontentArr[0];
                                $tmplcontentArr = explode('</PLOOP>',$tmplcontentArr[1]);
                                $tmplcontent2 = $tmplcontentArr[0];
                                $tmplcontent3 = $tmplcontentArr[1];
                            }else{
                                $tmplcontent1 = $tmplcontent;
                                $tmplcontent2 = '';
                                $tmplcontent3 = '';
                            }
                            if($type=='maidan'){
                                $textReplaceArr = [
                                    '[订单号]'=>$order['ordernum'],
                                    '[付款时间]'=>date('Y-m-d H:i:s',$order['paytime']),
                                    '[付款方式]'=>$order['paytype'],
                                    '[价格]'=>$order['money'],
                                    '[实付金额]'=>$order['paymoney'],
                                    '[备注]'=>$message,
                                ];
                            }elseif ($type=='shop' && isset($order['product_type']) && $order['product_type']==2){
                                //称重商品
                                if(getcustom('product_weight')){
                                    $customer = Db::name('sh_customer')->where('id',$order['customer_id'])->find();
                                    $customer_name = $customer?$customer['name']:'';
                                    if($customer['pid']){
                                        $pcustomer_name =  Db::name('sh_customer')->where('id',$customer['pid'])->value('name');
                                        if($pcustomer_name) $customer_name = $pcustomer_name.' '.$customer_name;
                                    }
                                    $textReplaceArr = [
                                        '[订单号]'=>$order['ordernum'],
                                        '[客户]'=>$customer_name,
                                        '[配送方式]'=>$order['freight_text'],
                                        '[收货人]'=>$order['linkman'],
                                        '[联系电话]'=>$order['tel'],
                                        '[收货地址]'=>$order['address'],
                                        '[付款方式]'=>$order['paytype'],
                                        '[商品名称]'=>$order['title'],
                                        '[实付金额]'=>$order['totalprice'],
                                        '[备注]'=>$message,
                                    ];
                                }
                            }elseif($type=='yuyue' ){
                                if(getcustom('yuyue_order_wifiprint')) {
                                    $fwtype = $order['fwtype'] == 1 ? '到店服务' : '上门服务';
                                    $textReplaceArr = [
                                        '[订单号]' => $order['ordernum'],
                                        '[服务时间]' => $order['yy_time'],
                                        '[服务方式]' => $fwtype,
                                        '[客户姓名]' => $order['linkman'],
                                        '[联系电话]' => $order['tel'],
                                        '[服务地址]' => $order['area'] . " " . $order['address'],
                                        '[付款时间]' => date('Y-m-d H:i:s', $order['paytime']),
                                        '[付款方式]' => $order['paytype'],
                                        '[商品名称]' => $order['title'],
                                        '[数量]' => $order['num'],
                                        '[价格]' => $order['product_price'],
                                        '[实付金额]' => $order['totalprice'],
                                        '[备注]' => $message,
                                    ];
                                }
                            }elseif ($type=='shop_refund'){
                                $textReplaceArr = [
                                    '[订单号]'=>$originalOrder['ordernum'],
                                    '[配送方式]'=>$originalOrder['freight_text'],
                                    '[配送时间]'=>$originalOrder['freight_time'],
                                    '[收货人]'=>$originalOrder['linkman'],
                                    '[联系电话]'=>$originalOrder['tel'],
                                    '[收货地址]'=>$originalOrder['area']." ".$originalOrder['address'],
                                    '[付款时间]'=>date('Y-m-d H:i:s',$originalOrder['paytime']),
                                    '[付款方式]'=>$originalOrder['paytype'],
                                    '[商品名称]'=>$originalOrder['title'],
                                    '[数量]'=>$originalOrder['num'],
                                    '[退款数量]'=>$order['refund_num'],
                                    '[价格]'=>$originalOrder['product_price'],
                                    '[实付金额]'=>$originalOrder['totalprice'],
                                    '[退款金额]'=>$order['refund_money'],
                                    '[备注]'=>$message,
                                    '[其他备注]'=>$otherRemark,
                                ];
                            }elseif($type=='collage_refund'){
                                $textReplaceArr = [
                                    '[订单号]'=>$order['ordernum'],
                                    '[配送方式]'=>$order['freight_text'],
                                    '[配送时间]'=>$order['freight_time'],
                                    '[收货人]'=>$order['linkman'],
                                    '[联系电话]'=>$order['tel'],
                                    '[收货地址]'=>$order['area']." ".$order['address'],
                                    '[付款时间]'=>date('Y-m-d H:i:s',$order['paytime']),
                                    '[付款方式]'=>$order['paytype'],
                                    '[商品名称]'=>$order['title'],
                                    '[数量]'=>$order['num'],
                                    '[退款数量]'=>$order['num'],
                                    '[价格]'=>$order['product_price'],
                                    '[实付金额]'=>$order['totalprice'],
                                    '[退款金额]'=>$order['refund_money'],
                                    '[备注]'=>$message,
                                    '[其他备注]'=>$otherRemark,
                                ];
                            }else{
                                $textReplaceArr = [
                                    '[订单号]'=>$order['ordernum'],
                                    '[配送方式]'=>$order['freight_text'],
                                    '[配送时间]'=>$order['freight_time'],
                                    '[收货人]'=>$order['linkman'],
                                    '[联系电话]'=>$order['tel'],
                                    '[收货地址]'=>$order['area']." ".$order['address'],
                                    '[付款时间]'=>date('Y-m-d H:i:s',$order['paytime']),
                                    '[付款方式]'=>$order['paytype'],
                                    '[商品名称]'=>$order['title'],
                                    '[数量]'=>$order['num'],
                                    '[价格]'=>$order['product_price'],
                                    '[实付金额]'=>$order['totalprice'],
                                    '[备注]'=>$message,
                                ];
                            }
                            $tmplcontent1 = str_replace(array_keys($textReplaceArr),array_values($textReplaceArr),$tmplcontent1);
                            if($tmplcontent3){
                                $tmplcontent3 = str_replace(array_keys($textReplaceArr),array_values($textReplaceArr),$tmplcontent3);
                            }
                            if($tmplcontent2){
                                $tmplcontent2Arr = [];
                                foreach($ordergoods as $item){
                                    if($type=='scoreshop'){
                                        if($item['totalscore'] > 0 && $item['totalmoney'] > 0){
                                            $item['totalprice'] = $item['totalmoney']."元 + ".$item['totalscore'].t('积分');
                                        }elseif($item['totalmoney'] > 0){
                                            $item['totalprice'] = $item['totalmoney']."元";
                                        }else{
                                            $item['totalprice'] = $item['totalscore'].t('积分');
                                        }
                                    }else{
                                        $item['totalprice'] = ''.$item['totalprice'];
                                    }
                                    $textReplaceArr2 = [
                                        '[订单号]'=>$order['ordernum'],
                                        '[配送方式]'=>$order['freight_text'],
                                        '[配送时间]'=>$order['freight_time'],
                                        '[收货人]'=>$order['linkman'],
                                        '[联系电话]'=>$order['tel'],
                                        '[收货地址]'=>$order['area']." ".$order['address'],
                                        '[付款时间]'=>date('Y-m-d H:i:s',$order['paytime']),
                                        '[付款方式]'=>$order['paytype'],
                                        '[商品名称]'=>$item['name'],
                                        '[规格]'=>$item['ggname'],
                                        '[数量]'=>$item['num'],
                                        '[价格]'=>$item['totalprice'],
                                        '[实付金额]'=>$order['totalprice'],
                                        '[备注]'=>$message,
                                        '[其他备注]'=>$otherRemark,
                                    ];
                                    $tmplcontent2Arr[] = str_replace(array_keys($textReplaceArr2),array_values($textReplaceArr2),$tmplcontent2);
                                }
                                $tmplcontent2 = implode('<BR>',$tmplcontent2Arr);
                            }
                            $content =  '';
                            if(getcustom('sys_print_set')){
                                if($machine['type']==1 || $machine['type']==4){
                                    if($print_day_ordernum && $machine['day_ordernum']){
                                        $content .=  '<CB>#'.$print_day_ordernum."</CB><BR><BR>";
                                    }
                                }else if($machine['type']==3){
                                    if($print_day_ordernum && $machine['day_ordernum']){
                                        $content .=  '<C><font# height=2 width=2>#'.$print_day_ordernum."</font#></C><BR><BR>";
                                    }
                                }
                            }
                            $content .= $tmplcontent1.$tmplcontent2.$tmplcontent3;

                            if(getcustom('sys_print_set')){
                                //底部自定义
                                $boot_custom =  $machine['boot_custom']?$machine['boot_custom']:0;
                                if($boot_custom){
                                    $boot_custom_content = $machine['boot_custom_content'];
                                    if($boot_custom_content){
                                        $content .= "<BR><BR>".$boot_custom_content;
                                    }
                                }
                            }
                            $content = str_replace(["\r","\n"],'',$content);
                        }else{
                            $content = '';
                            //飞蛾打印机
                            if($machine['type']==1){
                                if(getcustom('sys_print_set')){
                                    if($print_day_ordernum && $machine['day_ordernum']){
                                        $content .=  '<CB>#'.$print_day_ordernum."</CB>";
                                    }
                                }
                                $content .= "<CB>** ".$machine['title']." **</CB>";
                                if($type=='shop_refund'){
                                    $content .= "<C>申请退款</C>";
                                    //$ordergoods = db('shop_refund_order_goods')->where('refund_orderid',$order['id'])->select()->toArray();
                                    $order['refund_num'] = db('shop_refund_order_goods')->where('refund_orderid',$order['id'])->sum('refund_num');
                                    $originalOrder = Db::name('shop_order')->where('aid',$order['aid'])->where('id',$order['orderid'])->find();
                                    $order['num'] = db('shop_order_goods')->where('orderid',$order['orderid'])->sum('num');
                                    $replaceField = ['num','freight_text','freight_time','linkman','tel','area','address','paytime','paytype','product_type','customer_id'];
                                    foreach ($replaceField as $field){
                                        $order[$field]  = $originalOrder[$field];
                                    }
                                }else if($type=='collage_refund'){
                                    $content .= "<C>申请退款</C>";
                                    $order['refund_num'] = $order['num'];
                                }
                                $content .='<BR><BR>';
                                
                                if($type=='maidan'){
                                    $content .= "订单标题：".$order['title']."<BR>";
                                }
                                $content .= "订单编号：".$order['ordernum']."<BR>";
                                if($type=='cashier' || $type=='maidan'){
                                    //收银台无收货信息
                                }elseif($type=='yuyue'){
                                    if(getcustom('yuyue_order_wifiprint')) {
                                        $fwtype = $order['fwtype'] == 1 ? '到店服务' : '上门服务';
                                        $content .= "服务时间：" . $order['yy_time'] . "<BR>";
                                        $content .= "服务方式：" . $fwtype . "<BR>";
                                        if($order['fwtype'] ==1){
                                            if($order['bid'] == 0){
                                                $bname = Db::name('admin_set')->where('aid',$order['aid'])->value('name');
                                            }else{
                                                $bname = Db::name('business')->where('id',$order['bid'])->value('name');
                                            }
                                            $content .= "服务门店:" .$bname."<BR>";
                                        }
                                        $master_order = db('yuyue_worker_order')->field('worker_id,status')->where(['orderid'=>$order['id'],'ordernum'=>$order['ordernum']])->find();
                                        if($master_order && $master_order['worker_id']){
                                            $master = db('yuyue_worker')->where(['id'=>$master_order['worker_id']])->find();
                                            if($master){
                                                $content .= "服务人员：" . $master['realname'] . "<BR>";
                                                $content .= "服务人员电话：" . $master['tel'] . "<BR>";
                                            } else{
                                                $content .= "服务人员：未接单<BR>";
                                            }
                                        }else{
                                            $content .= "服务人员：未接单<BR>";
                                        }
                                        $content .= "客户姓名:" . $order['linkman'] . "<BR>";
                                        $content .= "联系电话:" . $order['tel'] . "<BR>";
                                        if($order['fwtype'] ==2){
                                            $content .= "服务地址:" . $order['area'] . " " . $order['address'] . "<BR>";
                                        }
                                    }
                                }else{
                                    $content .= "配送方式：".$order['freight_text']."<BR>";
                                    if($order['freight_time']){
                                        $content .= "配送时间：<B>".$order['freight_time']."</B><BR>";
                                    }
                                    $content .= "收货人:<B>".$order['linkman']."</B><BR>";
                                    $content .= "联系电话:<B>".$order['tel']."</B><BR>";
                                    $content .= "收货地址:<B>".$order['area']." ".$order['address']."</B><BR>";
                                }
                                $content .= "付款时间：".date('Y-m-d H:i:s',$order['paytime'])."<BR>";
                                $content .= "付款方式：".$order['paytype']."<BR><BR>";
                                if($type=='maidan'){
                                    //买单没有商品信息
                                }elseif($type=='cycle'){
                                    $content .= "商品名称     数量     期数     总价<BR>";
                                }else{
                                    $content .= "商品名称     数量     总价<BR>";
                                }
                                //\app\commons\Order::hasOrderGoodsTable($type) 判断是否有order goods表
                                if($type == 'shop'){
                                    $ordergoods = db($type.'_order_goods')->where('orderid',$order['id'])->select()->toArray();
                                    foreach($ordergoods as $item){
										$product = db($type.'_product')->field('print_name')->where('id',$item['proid'])->find();
										$name = $product['print_name']?$product['print_name']:$item['name'];
                                        $content .= "<BOLD>".$name."(".$item['ggname'].")</BOLD>   ".$item['num']."  ".$item['totalprice']."<BR>";
                                    }
                                }elseif($type == 'shop_refund'){
                                    $ordergoods = db($type.'_order_goods')->where('refund_orderid',$order['id'])->select()->toArray();
                                    foreach($ordergoods as $item){
                                        $content .= "<BOLD>".$item['name']."(".$item['ggname'].")</BOLD>   ".$item['refund_num']."  ".$item['refund_money']."<BR>";
                                    }
                                }elseif($type=='cycle'){
                                    $content .= "<BOLD>".$order['proname']."(".$order['ggname'].")</BOLD>   ".$order['num']."   ".$order['qsnum']."  ".$order['totalprice']."<BR>";
                                }elseif($type=='collage'){
                                    $content .= "<BOLD>".$order['proname']."(".$order['ggname'].")</BOLD>   ".$order['num']."  ".$order['totalprice']."<BR>";
                                }elseif($type=='lucky_collage'){
                                    $content .= "<BOLD>".$order['proname']."(".$order['ggname'].")</BOLD>   ".$order['num']."  ".$order['totalprice']."<BR>";
                                }elseif($type=='seckill'){
                                    $content .= "<BOLD>".$order['proname']."(".$order['ggname'].")</BOLD>   ".$order['num']."  ".$order['totalprice']."<BR>";
                                }elseif($type=='kanjia'){
                                    $content .= "".$order['proname']."  1  ".$order['totalprice']."<BR>";
                                }elseif($type=='tuangou'){
                                    $content .= "".$order['proname']."  ".$order['num']."  ".$order['totalprice']."<BR>";
                                }elseif($type=='scoreshop'){
                                    $ordergoods = db('scoreshop_order_goods')->where('orderid',$order['id'])->select()->toArray();
                                    foreach($ordergoods as $item){
                                        if($item['totalmoney'] > 0 && $item['totalscore'] > 0){
                                            $price = $item['totalmoney']."元+".$item['totalscore'].t('积分');
                                        }elseif($item['totalmoney'] > 0){
                                            $price = $item['totalmoney']."元";
                                        }else{
                                            $price = $item['totalscore'].t('积分');
                                        }
                                        $content .= "<BOLD>".$item['name']."</BOLD>   ".$item['num']."  ".$price."<BR>";
                                    }
                                }elseif($type == 'cashier'){
                                    $ordergoods = db($type.'_order_goods')->where('orderid',$order['id'])->select()->toArray();
                                    foreach($ordergoods as $item){
                                        $content .= "<BOLD>".$item['proname']."(".$item['ggname'].")</BOLD>   ".$item['num']."  ".$item['totalprice']."<BR>";
                                    }
                                    $order['message'] = $order['remark'];
                                } elseif($type=='yuyue'){
                                    $content .= "<BOLD>".$order['proname']."(".$order['ggname'].")</BOLD>   ".$order['num']."  ".$order['totalprice']."<BR>";
                                }elseif($type == 'collage_refund'){
                                    $content .= "<BOLD>".$order['proname']."(".$order['ggname'].")</BOLD>   ".$order['num']."  ".$order['refund_money']."<BR>";
                                }
                                $content .= "<BR>";
                                if($order['message']){
                                    $content .= "备注：<B>".$order['message']."</B><BR><BR>";
                                }else{
                                    //$content .= "备注：无<BR>";
                                }

                                if($type=='scoreshop'){
                                    $content .= "<RIGHT>实付金额：".$price."</RIGHT>";
                                }elseif($type=='cashier'){
                                    if($order['scoredk_money']>0){
                                        $content .= "<RIGHT>积分抵扣：".$order['scoredk_money']."</RIGHT>";
                                    }
                                    if($order['coupon_money']>0) {
                                        $content .= "<RIGHT>优惠券抵扣：" . $order['coupon_money']."</RIGHT>";
                                    }
                                    if($order['leveldk_money']>0) {
                                        $content .= "<RIGHT>会员折扣：" . $order['leveldk_money']."</RIGHT>";
                                    }
                                    if($order['moling_money']>0) {
                                        $content .= "<RIGHT>抹零金额：" . $order['moling_money']."</RIGHT>";
                                    }
                                    $content .= "<RIGHT>订单总价：".$order['pre_totalprice']."</RIGHT>";
                                    $content .= "<RIGHT>实付金额：".$order['totalprice']."</RIGHT>";
                                }elseif($type=='maidan'){

                                    if($order['scoredk_money']>0){
                                        $content .= "<RIGHT>积分抵扣：".$order['scoredk']."</RIGHT>";
                                    }
                                    if($order['leveldk_money']>0) {
                                        $content .= "<RIGHT>会员折扣：" . $order['disprice']."</RIGHT>";
                                    }
                                    if($order['decmoney']>0) {
                                        $content .= "<RIGHT>余额抵扣：" . $order['decmoney']."</RIGHT>";
                                    }
                                    if($order['couponmoney']>0) {
                                        $content .= "<RIGHT>优惠券抵扣：" . $order['couponmoney']."</RIGHT>";
                                    }
                                    $content .= "<RIGHT>订单总价：".$order['money']."</RIGHT>";
                                    $content .= "<RIGHT>实付金额：".$order['paymoney']."</RIGHT>";
                                }elseif($type=='yuyue'){
                                    $content .="--------------------------------<BR>";
                                    if($order['balance_price'] >0){
                                        $content .= "<RIGHT>尾款金额：".dd_money_format($order['balance_price'])."</RIGHT><BR>";
                                    }
                                    $content .= "<RIGHT>实付金额：".dd_money_format($order['totalprice'])."</RIGHT>";
                                }elseif($type=='shop_refund' || $type=='collage_refund'){
                                    $content .="--------------------------------<BR>";
                                    $content .= "<RIGHT>退款金额：".dd_money_format($order['refund_money'])."</RIGHT>";
                                }else{
                                    $content .= "<RIGHT>实付金额：".$order['totalprice']."</RIGHT>";
                                }
                                if($order['formdata']) {
                                    foreach ($order['formdata'] as $formdata) {
                                        if($formdata[2] != 'upload') {
                                            if($formdata[0] == '备注') {
                                                $content .= $formdata[0]."：<B>".$formdata[1]."</B><BR>";
                                            } else {
                                                $content .= $formdata[0]."：".$formdata[1]."<BR>";
                                            }
                                        }
                                    }
                                }
                            }else if($machine['type']==3){
                                if(getcustom('sys_print_daqu')){
                                    //大趋打印机
                                    if(getcustom('sys_print_set')){
                                        if($print_day_ordernum && $machine['day_ordernum']){
                                            $content .=  '<C><font# height=2 width=2>#'.$print_day_ordernum."</font#></C>";
                                        }
                                    }
                                    $content = self::daqu_content($machine,$order,$type,$content);
                                }
                            }else if($machine['type']==4){
                                if(getcustom('sys_print_xinye')){
                                    //芯烨打印机
                                    if(getcustom('sys_print_set')){
                                        if($print_day_ordernum && $machine['day_ordernum']){
                                            $content .=  '<CB>#'.$print_day_ordernum."</CB>";
                                        }
                                    }
                                    $content = self::xinye_content($machine,$order,$type,$content,$params);
                                }
                            }

                            if(getcustom('sys_print_set')){
                                //底部自定义
                                $boot_custom =  $machine['boot_custom']?$machine['boot_custom']:0;
                                if($boot_custom){
                                    $boot_custom_content = $machine['boot_custom_content'];
                                    if($boot_custom_content){
                                        $content .= "<BR><BR>".$boot_custom_content;
                                    }
                                }
                            }
                            $content .= "<BR><BR>";
                        }
                        if($machine['type']==1){
                            if(getcustom('recharge_order_wifiprint')){
                                if($type =='recharge' && $params['opttype'] =='recharge'){
                                    $content= self::getRechargeContent($order,$machine['type']);
                                }
                            }
                            if(getcustom('member_recharge_detail_refund')){
                                //退款打印
                                if($type =='recharge' && $params['opttype'] =='recharge_refund'){
                                    $content= self::getRechargeRefundContent($order,$machine['type'],$params['un']);
                                }
                            }
                            $rs = self::feie_print($machine['client_id'],$machine['client_secret'],$machine['machine_code'],$machine['msign'],$content);
                        }else if($machine['type']==3){
                            if(getcustom('sys_print_daqu')){
                                $voice = 0;
                                if($order['status'] == 1){
                                    $voice = 4;
                                }
                                $rs = self::daqu_print($machine['client_id'],$machine['client_secret'],$machine['machine_code'],$content,$voice);
                            }
                        }else if($machine['type']==4){
                            if(getcustom('sys_print_xinye')){
                                $voice = 1;//默认静音
                                if($order['status'] == 1 && $type !='recharge'){
                                    $voice = 2;//来单播报
                                }
                                $rs = self::xinye_print($machine['client_id'],$machine['client_secret'],$machine['machine_code'],$content,$voice,2);
                            }
                        }
                        $printnum++;
                    }

                }elseif($machine['type']==2){//映美云
                    if(getcustom('restaurant_wifiprint_yingmeiyun')) {
                        if(in_array($type,['maidan'])){//在里面 不让打印
                            return ['status' => 0, 'msg' => ''];
                        }
                        //称重订单
                        if(getcustom('product_weight') && $order['product_type']==2) {
                            if ($order['customer_id'] > 0) {
                                $customer = Db::name('sh_customer')->where('id', $order['customer_id'])->find();
                                $pcustomer = [];
                                $order['customerMendian'] = $customer['name'];
                                if ($customer['pid'] > 0) {
                                    $pcustomer = Db::name('sh_customer')->where('id', $customer['pid'])->find();
                                    $order['customerName'] = $pcustomer['name'];
                                }
                            } elseif ($order['mid'] > 0) {
                                $member = Db::name('member')->where('aid', $aid)->where('id', $order['mid'])->find();
                                $order['customerName'] = $member['nickname'];
                            }
                        }
                        if($type=='shop_refund' || $type=='collage_refund'){
                            $machine['title'] = '申请退款';//页眉信息
                        }
                        //按模板打印
                        $res = self::yingmeiyunPrintByTemplateId($machine,$order,$type);
                        $printnum++;
                    }
                }
            }
        }
        if(getcustom('sys_print_set')){
            if(!$order['printdaynum']){
                if($type == 'shop' || $type == 'cashier' || $type == 'maidan' || $type == 'scoreshop'){
                    //更新日单号
                    Db::name($type.'_order')->where('id',$order['id'])->update(['printdaynum'=>$print_day_ordernum]);

                    $print_day_ordernum ++;
                    cache($printdaynum,$print_day_ordernum);
                }
            }
        }
        return ['status'=>1,'msg'=>'成功打印'.$printnum.'张'];
    }
    /**
     * @title 优惠券打印
     * @param $aid
     * @param $type 订单类型 shop:商城
     * @param $recordid 记录ID
     * @param $brandType  打印机品牌 0:易联云
     * @return array
     */
    public static function couponPrint($aid,$bid,$recordid,$brandType=0){
        $data = Db::name('coupon_record')->where('aid',$aid)->where('bid',$bid)->where('id',$recordid)->find();
        $member =Db::name('member')->where('aid',$aid)->where('id',$data['mid'])->find();
        $where = [];
        $where[] = ['aid', '=', $aid];
        $where[] = ['status', '=', 1];
        if(is_numeric($brandType) && $brandType>-1){
            $where[] = ['type', '=', $brandType];
        }
        $machinelist = db('wifiprint_set')->where($where)->where('bid',$bid)->select()->toArray();
        foreach($machinelist as $machine){
            $content = '';
            $type_txt =  \app\commons\Coupon::getCouponTypeTxt($data['type']); 
            $content .= "<FS2><center>优惠券核销</center></FS2>";
            $content .= "优惠券名称：".$data['couponname']."\r";
            $content .= "优惠券类型：".$type_txt."\r";
            if($data['type'] ==1){
                $content .= "优惠券金额：￥".$data['money'];
                if($data['minprice'] > 0){
                    $content .= "，满".$data['minprice']."元可用\r";
                }else{
                    $content .= "，无门槛\r";
                }
            }elseif ($data['type'] ==3){
                $content .= "次数：".$data['limit_count']."次\r";
            }  elseif ($data['type'] ==10){
                $content .= "折扣：".$data['discount']."%\r";
            }
            if($data['type'] ==3){
                $content .= "已使用/总次数：".$data['used_count'].'/'.$data['limit_count']."\r";
            }
            $content .= "领取人：".$member['nickname'].'(ID：'.$member['id'].")\r";
            $content .= "领取时间：".date('Y-m-d H:i:s',$data['createtime'])."\r";
            $content .= "到期时间：".date('Y-m-d H:i:s',$data['endtime'])."\r";
          
            $content .= "使用时间：".date('Y-m-d H:i:s',time())."\r";
            $content .= "\r\r";
            $rs = self::yilianyun_print($machine['client_id'],$machine['client_secret'],$machine['access_token'],$machine['machine_code'],$machine['msign'],$content);
        }
    }
    public static function yingmeiyunPrintByTemplateId($machine,$order=[],$type='shop'){
        $machinecontent = $machine['tmplcontent'];
        if (!$machinecontent) {
            return ['status' => 0, 'msg' => '未配置打印模板'];
        }
        $machinecontent = json_decode($machinecontent, true);
        $ordergoods = [];
        if (\app\commons\Order::hasOrderGoodsTable($type)) {
            $ordergoods = db($type . '_order_goods')->where('orderid', $order['id'])->select()->toArray();
        }
        $message = \app\models\ShopOrder::checkOrderMessage($order['id'],$order);
//        $templateId = $machine['template_id'];
        if($machine['module']=='shop_weight' && $machine['machine_type']==1){
            //称重标签[按规格打]
            $count = count($ordergoods);
            $tmpNum = 1;
            foreach ($ordergoods as $k=>$og){
                $textReplaceArr = [
                    '订单编号' => $order['ordernum'],
                    '序号' => $tmpNum.'/'.$count,
                    '客户名称' => $order['customerName'],
                    '客户门店' => $order['customerMendian'],
                    '商品名称' => $og['name'].$og['ggname'],
                    '数量' => $og['num'],
                    '重量' => round($og['real_total_weight']/500,2),
                    '商品备注' => empty($og['remark'])?'':$og['remark'],
                    '订单备注' => empty($message)?'':$message,
                ];
                $bill_contet = [];
                foreach ($machinecontent as $key => $template) {
                    $bill_contet[$key] = str_replace(array_keys($textReplaceArr), array_values($textReplaceArr), $template);
                    if ($key == 'bottom') {
                        $bill_contet[$key] = $machine['boot_custom_content'];
                    }
                }
                $bill_contet = json_encode($bill_contet, JSON_UNESCAPED_UNICODE);
                $printerData2['template_id'] = $machine['template_id'];
                $printerData2['time_out'] = 86400;
                $res = \app\customs\Yingmeiyun::sendPrintTask($machine['client_id'], $machine['client_secret'], $machine['machine_code'], $bill_contet, 5, $printerData2);
                if(!$res || $res['status']!=1){
                    break;
                }
            }
        }elseif($machine['module']=='shop_weight' && $machine['machine_type']==0){
            //称重配送单
            $shipping_pagetitle = Db::name('shop_sysset')->where('aid',$order['aid'])->value('shipping_pagetitle');
            if($order['bid'] == 0){
                $bname = Db::name('admin_set')->where('aid',$order['aid'])->value('name');
            }else{
                $bname = Db::name('business')->where('id',$order['bid'])->value('name');
            }
            $textReplaceArr = [
                '标题'=> $bname.$shipping_pagetitle,//某某送货单
                '订单编号' => $order['ordernum'],
                '配送时间' => date('Y-m-d H:i:s'),
                '配送方式' => $order['freight_text'],
                '客户名称' => $order['customerName'].' '.$order['customerMendian'],
                '客户电话' => $order['tel'],
                '客户地址' => $order['area'].$order['address'],
                '订单备注' => $message,
            ];
            $oglist = [];
            $totalprice = 0;
            foreach ($ordergoods as $goods) {
                //挂单未发货
                if($goods['status']==22){
                    $oglist[] = [
                        'name' => $goods['name'],
                        'num' => 0,
                        'ggname' => $goods['ggname'],
                        'real_total_weight' =>0,
                        'real_sell_price' =>dd_money_format($goods['real_sell_price'],2),
                        'real_totalprice' =>0,
                        'remark' => '挂单未发货',
                    ];
                }else{
                    $oglist[] = [
                        'name' => $goods['name'],
                        'num' => $goods['num'],
                        'ggname' => $goods['ggname'],
                        'real_total_weight' =>round($goods['real_total_weight']/500,2),
                        'real_sell_price' =>dd_money_format($goods['real_sell_price'],2),
                        'real_totalprice' =>dd_money_format($goods['real_totalprice'],2),
                        'remark' => $goods['remark']?$goods['remark']:$message,
                    ];
                    $totalprice = $totalprice+$goods['real_totalprice'];
                }
            }
            $bill_contet = [];
            foreach ($machinecontent as $key => $template) {
                $bill_contet[$key] = str_replace(array_keys($textReplaceArr), array_values($textReplaceArr), $template);
                $adminname = $peisongName =  $peisongTel = '';;
                if(uid>0){
                    $adminname = Db::name('admin_user')->where('aid',$order['aid'])->where('id',uid)->value('un');
                }
                if(getcustom('customer_peisonguser')){
                    if($order['customer_id']){
                        $peisong_uid = Db::name('sh_customer')->where('aid',$order['aid'])->where('id',$order['customer_id'])->value('peisong_uid');
                        if($peisong_uid>0){
                            $peisong = Db::name('peisong_user')->where('aid',$order['aid'])->where('id',$peisong_uid)->find();
                            $peisongName = $peisong['realname']??'';
                            $peisongTel = $peisong['tel']??'';
                        }
                    }
                }
                if ($key == '商品信息') {
                    $bill_contet[$key] = [
                        'content' => $oglist,
                        '运费' => dd_money_format($order['freight_price'],2),
                        '合计金额小写' => $totalprice,
                        '合计金额大写' => num_to_rmb($totalprice),
                        '制单人'=>$adminname,
                        '送货人'=>$peisongName.' '.$peisongTel
                    ];
                }
                if ($key == 'bottom') {
                    $bill_contet[$key] = $machine['boot_custom_content'];
                }
            }
            $bill_contet = json_encode($bill_contet, JSON_UNESCAPED_UNICODE);
            $printerData2['template_id'] = $machine['template_id'];
            $printerData2['time_out'] = 86400;
            $res = \app\customs\Yingmeiyun::sendPrintTask($machine['client_id'], $machine['client_secret'], $machine['machine_code'], $bill_contet, 5, $printerData2);
        }else{
            //默认之前模板533000abf8989582
            $textReplaceArr = [
                '标题' => $machine['title'],
                '付款时间' => date('Y-m-d H:i:s', $order['paytime']),
                '配送方式' => $order['freight_text'],
                '订单号' => $order['ordernum'],
                '支付单号' => $order['paynum'],
                '收货人' => $order['linkman'],
                '联系电话' => $order['tel'],
                '备注' => $message,
            ];
            $oglist = [];
            foreach ($ordergoods as $goods) {
                $oglist[] = [
                    'name' => $goods['name'],
                    'procode' => $goods['procode'],
                    'num' => $goods['num'],
                    'ggname' => $goods['ggname'],
                    'sell_price' =>$goods['sell_price'],
                    'product_price' => dd_money_format($goods['sell_price'] *$goods['num']),
                ];
            }
            $bill_contet = [];
            foreach ($machinecontent as $key => $template) {
                $bill_contet[$key] = str_replace(array_keys($textReplaceArr), array_values($textReplaceArr), $template);
                if ($key == '商品信息') {
                    $bill_contet[$key] = ['content' => $oglist, '订单总价' => $order['product_price'], '应付金额' => $order['totalprice']];
                }
                if ($key == 'bottom') {
                    $bill_contet[$key] = $machine['boot_custom_content'];
                }
            }
            $bill_contet = json_encode($bill_contet, JSON_UNESCAPED_UNICODE);
            $printerData2['template_id'] = $machine['template_id'];
            $printerData2['time_out'] = 86400;
            $res = \app\customs\Yingmeiyun::sendPrintTask($machine['client_id'], $machine['client_secret'], $machine['machine_code'], $bill_contet, 5, $printerData2);
        }
        return $res;
    }

    //打印小票 aid 订单id
    public static function print2($aid,$orderid){
        if(getcustom('form_print')){

            $info = db('form_order')->where('id',$orderid)->where('aid',$aid)->find();
            if(!$info){
                return ['status'=>0,'msg'=>'表单数据不存在'];
            }

            $form = Db::name('form')->where('id',$info['formid'])->where('aid',$aid)->find();

            if(!$form){
                return ['status'=>0,'msg'=>'表单不存在'];
            }
            if(!$form['print_status']){
                return ['status'=>0,'msg'=>'表单打印未开启'];
            }
            if(!$form['printid']){
                return ['status'=>0,'msg'=>'表单未绑定打印机'];
            }
            if(!$form['print_num'] || $form['print_num']<=0){
                return ['status'=>0,'msg'=>'表单打印数量为0'];
            }

            if($form['content']){
                $formcontent = json_decode($form['content'],true);
            }else{
                return ['status'=>0,'msg'=>'表单内容设置为空'];
            }

            if(empty($info['bid'])){
                $info['bid'] = 0;
            }
            if($info['bid'] == 0){
                $binfor = Db::name('admin_set')->where('aid',$aid)->field('id,name,tel')->find();
            }else{
                $binfor = Db::name('business')->where('aid',$aid)->where('id',$info['bid'])->field('id,name,tel')->find();
            }

            $where = [];
            $where[] = ['id', '=', $form['printid']];
            $where[] = ['aid', '=', $aid];
            $machinelist = db('wifiprint_set')->where($where)->where('bid',$info['bid'])->select()->toArray(); //打印机列表
            if(!$machinelist) return ['status'=>0,'msg'=>'没有配置小票打印机'];

            $printnum = 0;
            foreach($machinelist as $machine){
                $num = $form['print_num'];
                for($i=0;$i<$num;$i++){
                    if($machine['type']==0){
                        $content = '';

                        $content .= "<center>".$binfor['name']." </center>\r";
                        
                        $content .= "--------------------------------\r";

                        $title = $info['title']?$info['title']:'';
                        $content .= "表单名称：".$title."\r";

                        $createtime = $info['createtime']?date('Y-m-d H:i:s',$info['createtime']):'';
                        $content .= "提交时间：".$createtime."\r";

                        $content .= "商家电话：".$binfor['tel']."\r";
                        $content .= "--------------------------------\r";

                        if($formcontent){
                            foreach($formcontent as $k=>$field){

                                $pre_title = '';
                                if($field['key']=='separate'){
                                    $pre_title = '<FB>'.$field['val1']."</FB>";
                                }else{
                                    if($field['val3']==1){
                                        $pre_title = '*'.$field['val1']."";
                                    }else{
                                        $pre_title = $field['val1']."";
                                    }
                                }

                                if($field['key'] == 'input' || $field['key'] == 'textarea' || $field['key'] == 'time' || $field['key'] == 'date' || $field['key'] == 'region' || $field['key']== 'upload'){
                                    $content .= $pre_title.'：'.$info['form'.$k]."\r";
                                }else if($field['key'] == 'radio' || $field['key']== 'selector'){
                                    $after = '';
                                    foreach($field['val2'] as $k2=>$v2){
                                        if($info['form'.$k] && $info['form'.$k] == $v2){
                                            $after .= '√'.$v2."\r";
                                        }else{
                                            $after .= $v2."\r";
                                        }
                                    }
                                    unset($v2);
                                    $content .= $pre_title."：\r".$after."\r";
                                }else if($field['key']== 'checkbox'){
                                    $after = '';
                                    foreach($field['val2'] as $k2=>$v2){
                                        if($info['form'.$k] && in_array($v2,explode(',',$info['form'.$k]))){
                                            $after .= '√'.$v2."\r";
                                        }else{
                                            $after .= $v2."\r";
                                        }
                                    }
                                    unset($v2);
                                    $content .= $pre_title."：\r".$after."\r";
                                }

                            }
                            unset($fv);
                        }
                        $content .= "--------------------------------\r";
                        $content .= "打印时间：".date("Y-m-d H:i:s",time())."\r";
                        $rs = self::yilianyun_print($machine['client_id'],$machine['client_secret'],$machine['access_token'],$machine['machine_code'],$machine['msign'],$content);
                        $printnum++;
                    }elseif($machine['type']==1){
                        if($machine['machine_type']==1){ //标签打印机
                            return ['status'=>0,'msg'=>'不支持标签打印机'];
                        }else{
                            $content = '';

                            $content .= "<CB>".$binfor['name']."</CB><BR>";
                            
                            $content .= '--------------------------------<BR>';

                            $title = $info['title']?$info['title']:'';
                            $content .= "表单名称：".$title."<BR>";

                            $createtime = $info['createtime']?date('Y-m-d H:i:s',$info['createtime']):'';
                            $content .= "提交时间：".$createtime."<BR>";

                            $content .= "商家电话：".$binfor['tel']."<BR>";
                            $content .= '--------------------------------<BR>';

                            if($formcontent){
                                foreach($formcontent as $k=>$field){

                                    $pre_title = '';
                                    if($field['key']=='separate'){
                                        $pre_title = '<B>'.$field['val1']."</B>";
                                    }else{
                                        if($field['val3']==1){
                                            $pre_title = '*'.$field['val1']."";
                                        }else{
                                            $pre_title = $field['val1']."";
                                        }
                                    }

                                    if($field['key'] == 'input' || $field['key'] == 'textarea' || $field['key'] == 'time' || $field['key'] == 'date' || $field['key'] == 'region' || $field['key']== 'upload'){
                                        $content .= $pre_title.'：'.$info['form'.$k]."<BR>";
                                    }else if($field['key'] == 'radio' || $field['key']== 'selector'){
                                        $after = '';
                                        foreach($field['val2'] as $k2=>$v2){
                                            if($info['form'.$k] && $info['form'.$k] == $v2){
                                                $after .= '√'.$v2.'<BR>';
                                            }else{
                                                $after .= $v2.'<BR>';
                                            }
                                        }
                                        unset($v2);
                                        $content .= $pre_title.'：<BR>'.$after."<BR>";
                                    }else if($field['key']== 'checkbox'){
                                        $after = '';
                                        foreach($field['val2'] as $k2=>$v2){
                                            if($info['form'.$k] && in_array($v2,explode(',',$info['form'.$k]))){
                                                $after .= '√'.$v2.'<BR>';
                                            }else{
                                                $after .= $v2.'<BR>';
                                            }
                                        }
                                        unset($v2);
                                        $content .= $pre_title.'：<BR>'.$after."<BR>";
                                    }

                                }
                                unset($fv);
                            }
                            $content .= '--------------------------------<BR>';
                            $content .= "打印时间：".date("Y-m-d H:i:s",time())."<BR>";
                        }
                        $rs = self::feie_print($machine['client_id'],$machine['client_secret'],$machine['machine_code'],$machine['msign'],$content);
                        $printnum++;
                    }
                }
            }
            return ['status'=>1,'msg'=>'成功打印'.$printnum.'张'];
        }
    }
    //易联云文本打印 $machine_code:易联云打印机终端号 $msign:易联云终端密钥 $content:打印内容
    public static function yilianyun_print($client_id,$client_secret,$access_token,$machine_code,$msign,$content){
        if(!$machine_code || !$content) return ['status'=>0,'error'=>1,'msg'=>'参数错误'];
        $data = [];
        $data['client_id'] = $client_id;
        $data['access_token'] = $access_token;
        $data['machine_code'] = $machine_code;
        $data['content'] = $content;
        $data['origin_id'] = date('YmdHis').rand(10000,99999);
        $data['timestamp'] = time();
        $data['id'] = self::uuid4();
        $data['sign'] = md5($data['client_id'].$data['timestamp'].$client_secret);
        $rs = request_post('https://open-api.10ss.net/print/index',$data);
        $rs = json_decode($rs,true);
        //dump($rs);
        if($rs['error'] == 8){
            //授权
            $data2 = [];
            $data2['client_id'] = $client_id;
            $data2['machine_code'] = $machine_code;
            $data2['msign'] = $msign;
            $data2['access_token'] = $access_token;
            $data2['timestamp'] = time();
            $data2['id'] = self::uuid4();
            $data2['sign'] = md5($data2['client_id'].$data2['timestamp'].$client_secret);
            $rs2 = request_post('https://open-api.10ss.net/printer/addprinter',$data2);
            //dump($rs2);
            $rs2 = json_decode($rs2,true);
            if($rs2['error'] == 0){
                $rs = request_post('https://open-api.10ss.net/print/index',$data);
                //dump($rs);
                $rs = json_decode($rs,true);
            }else{
                $rs = $rs2;
            }
        }
        //dump($rs);
        if($rs['error']==0){
            $rs['status'] = 1;
            $rs['msg'] = '打印成功';
        }else{
            $rs['status'] = 0;
            $rs['msg'] = $rs['error_description'];
            if($rs['error']==16){
                $rs['msg'] = '终端号配置错误，请检查终端号';
            }
        }
        return $rs;
    }
    //飞鹅小票打印机文本打印
    public static function feie_print($user,$ukey,$sn,$key,$content,$type=0){
        $postdata = [];
        $postdata['user'] = $user;
        $postdata['stime'] = time();
        $postdata['sig'] = sha1($user.$ukey.$postdata['stime']);
        if($type==1){
            $postdata['apiname'] = 'Open_printLabelMsg';
        }else{
            $postdata['apiname'] = 'Open_printMsg';
        }
        $postdata['sn'] = $sn;
        $postdata['content'] = $content;
        $rs = request_post('http://api.feieyun.cn/Api/Open/',$postdata);
        $rs = json_decode($rs,true);
        if($rs['ret'] == 0){
            $rs['status'] = 1;
        }else{
            $rs['status'] = 0;
        }
        return $rs;
    }

    public static function uuid4(){
        mt_srand((double)microtime() * 10000);
        $charid = strtolower(md5(uniqid(rand(), true)));
        $hyphen = '-';
        $uuidV4 =
            substr($charid, 0, 8) . $hyphen .
            substr($charid, 8, 4) . $hyphen .
            substr($charid, 12, 4) . $hyphen .
            substr($charid, 16, 4) . $hyphen .
            substr($charid, 20, 12);
        return $uuidV4;
    }
    //交班打印
    public static function jiaobanPrint($printdata=[]){
        $cashier_info = $printdata['cashier_info'];
        $jiaoban_print_ids =explode(',',$cashier_info['jiaoban_print_ids']);
        $where = [];
        $where[] = ['aid', '=', $cashier_info['aid']];
        $where[] = ['id', 'in', $jiaoban_print_ids];

        $machineList = Db::name('wifiprint_set')->where($where)->select()->toArray();
        if(empty($machineList)) {
            return false;
        }

        foreach ($machineList as $machine) {
            if($machine['type'] ==0){  //易联云
                $content = $printdata['title']."\r";
                $content .=  "--------------------------------\r";
                if($printdata['cashdesk_user']) {
                    $content .= "当前账号：" . $printdata['cashdesk_user'] . "\r";
                    $content .= "--------------------------------\r";
                }
                $content .=  "登录时间：".$printdata['logintime']."\r";
                $content .=  "--------------------------------\r";
                $content .=  "交班时间：".$printdata['jiaobantime']."\r";
                $content .=  "--------------------------------\r";
                $content .=  "订单总数：".$printdata['total_ordercount']."\r";
                $content .=  "   收银机订单数：".$printdata['cashdesk_ordercount']."\r";
                $content .=  "   线上订单数：".$printdata['online_ordercount']."\r";
                $content .=  "--------------------------------\r";
                $content .=  "<FB>收银机营业额： ".$printdata['today_total_money']."</FB>\r";
                if($printdata['cashpay_show'] && $printdata['today_cash_money']>0 && in_array($printdata['search_paytype'] ,['',0])){
                    $content .=  "-现金支付         金额：".$printdata['today_cash_money']."\r";
                }
                if($printdata['yuepay_show'] && $printdata['today_yue_money']>0 && in_array($printdata['search_paytype'] ,['',1])){
                    $content .=  "-余额支付         金额：".$printdata['today_yue_money']."\r";
                }
                if(getcustom('restaurant_cashdesk_mix_pay')){
                    if(in_array($printdata['search_paytype'] ,['',0])){
                        if($printdata['mix_wx_pay'] > 0) {
                            $content .= "--混合支付微信     金额：" . $printdata['mix_wx_pay'] . "\r";
                        }
                        if($printdata['mix_alipay_pay'] > 0) {
                            $content .= "--混合支付支付宝    金额：" . $printdata['mix_alipay_pay'] . "\r";
                        }
                        if($printdata['mix_sxf_pay'] > 0 && $printdata['sxfpay_show']) {
                            $content .= "--混合支付随行付   金额：" . $printdata['mix_sxf_pay'] . "\r";
                        }
                    }
                }
                if($printdata['sxfpay_show'] && $printdata['today_sxf_money']>0 && in_array($printdata['search_paytype'] ,['',81]) ){
                    $content .=  "-随行付支付        金额：".$printdata['today_sxf_money']."\r";
                }
                if($printdata['douyinhx_show'] && $printdata['today_douyin_hexaio_money']>0 && in_array($printdata['search_paytype'] ,['',121])){
                    $content .=  "-抖音核销          金额：".$printdata['today_douyin_hexaio_money']."\r";
                }
                if($printdata['wxpay_show'] && $printdata['today_wx_money'] > 0 && in_array($printdata['search_paytype'] ,['',2])) {
                    $content .= "-微信           金额：" . $printdata['today_wx_money'] . "\r";
                }
                if($printdata['alipay_show'] && $printdata['today_alipay_money'] > 0 && in_array($printdata['search_paytype'] ,['',3])) {
                    $content .= "-支付宝         金额：" . $printdata['today_alipay_money'] . "\r";
                }
                //自定义支付
                if(getcustom('restaurant_cashdesk_custom_pay')){
                    if($printdata['today_custom_pay_list']){
                        foreach($printdata['today_custom_pay_list'] as $ckey=>$cval){
                            if($cval['money'] > 0 && in_array($printdata['search_paytype'] ,['',$cval['paytypeid']])){
                                $content .=  "-".$cval['title']."         金额：".$cval['money']."\r";
                            }
                        }
                    }
                }

                $content .=  "--------------------------------\r";
                $content .=  "<FB>线上营业额(会员消费归集在这里)：".$printdata['online_total_money']."</FB> \r";
                if($printdata['online_yue_money'] > 0 && in_array($printdata['search_paytype'] ,['',1])) {
                    $content .= "-会员余额消费     金额：" . $printdata['online_yue_money'] . "\r";
                }
                if($printdata['online_wx_money'] > 0 && in_array($printdata['search_paytype'] ,['',2]) ) {
                    $content .= "-微信线上支付         金额：" . $printdata['online_wx_money'] . "\r";
                }
                if($printdata['online_alipay_money'] > 0 &&  in_array($printdata['search_paytype'] ,['',3]) ) {
                    $content .= "-支付宝线上支付         金额：" . $printdata['online_alipay_money'] . "\r";
                }
                if($printdata['online_admin_money'] > 0 &&  in_array($printdata['search_paytype'] ,['',0]) ) {
                    $content .= "-后台补录         金额：" . $printdata['online_admin_money'] . "\r";
                }
                $content .=  "--------------------------------\r";
                $content .=  "<FB>优惠金额（不参与其他统计）：-".$printdata['youhui_total']."</FB> \r";
                if($printdata['recharge_show']){
                    $content .=  "--------------------------------\r";
                    $content .=  "<FB>会员储值 (预付款)收款小计：".$printdata['recharge_total_money']."</FB> \r";
                    if($printdata['recharge_cash_money'] > 0 &&  in_array($printdata['search_paytype'] ,['',0]) ) {
                        $content .= "-现金支付         金额：" . $printdata['recharge_cash_money'] . "\r";
                    }
                    if($printdata['recharge_sxf_money'] > 0 &&  in_array($printdata['search_paytype'] ,['',81]) ) {
                        $content .= "-随行付支付    金额：" . $printdata['recharge_sxf_money'] . "\r";
                    }
                    if($printdata['recharge_wx_money'] > 0 &&  in_array($printdata['search_paytype'] ,['',2]) ) {
                        $content .= "-微信线上支付    金额：" . $printdata['recharge_wx_money'] . "\r";
                    }
                    if($printdata['recharge_alipay_money'] > 0 &&  in_array($printdata['search_paytype'] ,['',3]) ) {
                        $content .= "-支付宝上支付    金额：" . $printdata['recharge_alipay_money'] . "\r";
                    }
                }

                $content .=  "--------------------------------\r";
                $content .=  "<FB>退款总额：".$printdata['refund_total_money']."</FB> \r";
                if($printdata['refund_cash_money'] > 0 && in_array($printdata['search_paytype'] ,['',0])) {
                    $content .= "-现金退款       金额：" . $printdata['refund_cash_money'] . "\r";
                }
                if(getcustom('restaurant_cashdesk_mix_pay')){
                    if(in_array($printdata['search_paytype'] ,['',0])) {
                        if ($printdata['mix_refund_wx_pay'] > 0) {
                            $content .= "--混合支付微信退款   金额：" . $printdata['mix_refund_wx_pay'] . "\r";
                        }
                        if ($printdata['mix_refund_alipay_pay'] > 0) {
                            $content .= "--混合支付支付宝退款  金额：" . $printdata['mix_refund_alipay_pay'] . "\r";
                        }
                        if ($printdata['mix_refund_sxf_pay'] > 0 && $printdata['sxfpay_show']) {
                            $content .= "-混合支付随行付退款  金额：" . $printdata['mix_refund_sxf_pay'] . "\r";
                        }
                    }
                }
                if($printdata['refund_yue_money'] > 0 &&  in_array($printdata['search_paytype'] ,['',1])) {
                    $content .= "-余额退款     金额：" . $printdata['refund_yue_money'] . "\r";
                }
                if($printdata['refund_wx_money'] > 0 &&  in_array($printdata['search_paytype'] ,['',2])) {
                    $content .= "-微信线上退款     金额：" . $printdata['refund_wx_money'] . "\r";
                }
                if($printdata['refund_alipay_money'] > 0 &&  in_array($printdata['search_paytype'] ,['',3])) {
                    $content .= "-支付宝线上退款     金额：" . $printdata['refund_alipay_money'] . "\r";
                }
                if($printdata['refund_sxf_money'] > 0 && $printdata['sxfpay_show'] &&  in_array($printdata['search_paytype'] ,['',81])) {
                    $content .= "-随行付退款     金额：" . $printdata['refund_sxf_money'] . "\r";
                }
                if($printdata['refund_douyin_hexaio_money'] > 0 && $printdata['douyinhx_show'] &&  in_array($printdata['search_paytype'] ,['',121])) {
                    $content .= "-抖音核销券退款     金额：" . $printdata['refund_douyin_hexaio_money'] . "\r";
                }
                if(getcustom('restaurant_cashdesk_custom_pay')){
                    if($printdata['refund_custom_list']){
                        foreach($printdata['refund_custom_list'] as $ckey=>$cval){
                            if($cval['refund_money'] > 0 && in_array($printdata['search_paytype'] ,['',$cval['paytypeid']])){
                                $content .=  "-".$cval['title']."退款      金额：".$cval['refund_money']."\r";
                            }
                        }
                    }
                }

                if(false){
                    $content .=  "      -银行卡     金额：0.00\r";
                    $content .=  "      -抖音团购   金额：0.00\r";
                    $content .=  "      -美团团购\r";
                }
                $content .=  "--------------------------------\r";
                $content .=  "<FB>营业额汇总（包含会员储值与会员消费）：".$printdata['all_yingyee_money']."</FB> \r";
                $content .=  "<FB>营业额汇总（仅不含会员储值）：".$printdata['yingyee_money']."</FB> \r";
                $content .=  "<FB>收款汇总（仅不含会员余额消费）：".$printdata['total_in_money']."</FB> \r";
//                $content .=  "<FB>收款汇总：".$printdata['all_total_in_money']."</FB> \r";
                $content .=  "<FB>余额支付汇总（线上+线下）：".$printdata['all_yue_money']."</FB> \r";
                if (false){
                    $content .=  "  -现金           金额：0.00\r";
                    $content .=  "  -随行付         金额：0.00\r";
                    $content .=  "  -微信           金额：0.00\r";
                    $content .=  "  -支付宝         金额：0.00\r";
                    $content .=  "  -银行卡         金额：0.00\r";
                    $content .=  "  -退款明细       金额：0.00\r";
                    $content .=  "      -现金       金额：0.00\r";
                    $content .=  "      -随行付     金额：0.00\r";
                    $content .=  "      -微信       金额：0.00\r";
                    $content .=  "      -支付宝     金额：0.00\r";
                    $content .=  "      -银行卡     金额：0.00\r";
                }
                if(false){
                    $content .=  "———————————————\r";
                    $content .=  "<FB>钱箱预留：0.00</FB>\r";
                    $content .=  "<FB>存入备用金：0.00</FB>\r";
                }
                if(false){
                    $content .=  "<FB>应有现金：0.00</FB>\r";
                    $content .=  "———————————————\r";
                    $content .=  "<FB>上交现金：0.00</FB>\r";
                    $content .=  "<FB>钱箱留存：0.00</FB>\r";
                }
                $content .=  "───────────────\r";
                $content .=  "<FB>打印日期：".date('Y-m-d H:i:s')."</FB>\r";
                $content .= "\r\r";
                $rs = \app\commons\Wifiprint::yilianyun_print($machine['client_id'],$machine['client_secret'],$machine['access_token'],$machine['machine_code'],$machine['msign'],$content);
            }elseif ($machine['type']==1){ //飞蛾
                $content = $printdata['title']."<BR>";
                $content .=  "--------------------------------<BR>";
                if($printdata['cashdesk_user']){
                    $content .=  "当前账号：".$printdata['cashdesk_user']."<BR>";
                    $content .=  "--------------------------------<BR>";
                }
                $content .=  "登录时间：".$printdata['logintime']."<BR>";
                $content .=  "--------------------------------<BR>";
                $content .=  "交班时间：".$printdata['jiaobantime']."<BR>";
                $content .=  "--------------------------------<BR>";
                $content .=  "订单总数：".$printdata['total_ordercount']."<BR>";
                $content .=  "   收银机订单数：".$printdata['cashdesk_ordercount']."<BR>";
                $content .=  "   线上订单数：".$printdata['online_ordercount']."<BR>";
                $content .=  "--------------------------------<BR>";
                $content .=  "<BOLD>收银机营业额： ".$printdata['today_total_money']."</BOLD> <BR>";
                if($printdata['cashpay_show'] && $printdata['today_cash_money']>0 && in_array($printdata['search_paytype'] ,['',0])){
                    $content .=  "-现金支付         金额：".$printdata['today_cash_money']."<BR>";
                }
                if($printdata['yuepay_show'] && $printdata['today_yue_money']>0 && in_array($printdata['search_paytype'] ,['',1])){
                    $content .=  "-余额支付         金额：".$printdata['today_yue_money']."<BR>";
                }
                if(getcustom('restaurant_cashdesk_mix_pay')){
                    if(in_array($printdata['search_paytype'] ,['',0])){
                        if($printdata['mix_wx_pay'] > 0) {
                            $content .= "--混合支付微信     金额：" . $printdata['mix_wx_pay'] . "<BR>";
                        }
                        if($printdata['mix_alipay_pay'] > 0) {
                            $content .= "--混合支付支付宝    金额：" . $printdata['mix_alipay_pay'] . "<BR>";
                        }
                        if($printdata['mix_sxf_pay'] > 0 && $printdata['sxfpay_show']) {
                            $content .= "--混合支付随行付   金额：" . $printdata['mix_sxf_pay'] . "<BR>";
                        }
                    }
                }
                if($printdata['sxfpay_show'] && $printdata['today_sxf_money']>0 && in_array($printdata['search_paytype'] ,['',81]) ){
                    $content .=  "-随行付支付        金额：".$printdata['today_sxf_money']."<BR>";
                }
                if($printdata['douyinhx_show'] && $printdata['today_douyin_hexaio_money']>0 && in_array($printdata['search_paytype'] ,['',121])){
                    $content .=  "-抖音核销          金额：".$printdata['today_douyin_hexaio_money']."<BR>";
                }
                if($printdata['wxpay_show'] && $printdata['today_wx_money'] > 0 && in_array($printdata['search_paytype'] ,['',2])) {
                    $content .= "-微信           金额：" . $printdata['today_wx_money'] . "<BR>";
                }
                if($printdata['alipay_show'] && $printdata['today_alipay_money'] > 0 && in_array($printdata['search_paytype'] ,['',3])) {
                    $content .= "-支付宝         金额：" . $printdata['today_alipay_money'] . "<BR>";
                }
                //自定义支付
                if(getcustom('restaurant_cashdesk_custom_pay')){
                    if($printdata['today_custom_pay_list']){
                        foreach($printdata['today_custom_pay_list'] as $ckey=>$cval){
                            if($cval['money'] > 0 && in_array($printdata['search_paytype'] ,['',$cval['paytypeid']])){
                                $content .=  "-".$cval['title']."         金额：".$cval['money']."<BR>";
                            }
                        }
                    }
                }
                
                $content .=  "--------------------------------<BR>";
                $content .=  "<BOLD>线上营业额：".$printdata['online_total_money']."</BOLD> <BR>";
                if($printdata['online_yue_money'] > 0 && in_array($printdata['search_paytype'] ,['',1])) {
                    $content .= "-会员余额消费     金额：" . $printdata['online_yue_money'] . "<BR>";
                }
                if($printdata['online_wx_money'] > 0 && in_array($printdata['search_paytype'] ,['',2]) ) {
                    $content .= "-微信线上支付         金额：" . $printdata['online_wx_money'] . "<BR>";
                }
                if($printdata['online_alipay_money'] > 0 &&  in_array($printdata['search_paytype'] ,['',3]) ) {
                    $content .= "-支付宝线上支付         金额：" . $printdata['online_alipay_money'] . "<BR>";
                }
                if($printdata['online_admin_money'] > 0 &&  in_array($printdata['search_paytype'] ,['',0]) ) {
                    $content .= "-后台补录         金额：" . $printdata['online_admin_money'] . "<BR>";
                }
                $content .=  "--------------------------------<BR>";
                $content .=  "<BOLD>优惠金额（不参与其他统计）：-".$printdata['youhui_total']."</BOLD> <BR>";
                if($printdata['recharge_show']){
                    $content .=  "--------------------------------<BR>";
                    $content .=  "<BOLD>会员储值 (预付款)收款小计：".$printdata['recharge_total_money']."</BOLD> <BR>";
                    if($printdata['recharge_cash_money'] > 0 &&  in_array($printdata['search_paytype'] ,['',0]) ) {
                        $content .= "-现金支付         金额：" . $printdata['recharge_cash_money'] . "<BR>";
                    }
                    if($printdata['recharge_sxf_money'] > 0 &&  in_array($printdata['search_paytype'] ,['',81]) ) {
                        $content .= "-随行付支付    金额：" . $printdata['recharge_sxf_money'] . "<BR>";
                    }
                    if($printdata['recharge_wx_money'] > 0 &&  in_array($printdata['search_paytype'] ,['',2]) ) {
                        $content .= "-微信线上支付    金额：" . $printdata['recharge_wx_money'] . "<BR>";
                    }
                    if($printdata['recharge_alipay_money'] > 0 &&  in_array($printdata['search_paytype'] ,['',3]) ) {
                        $content .= "-支付宝上支付    金额：" . $printdata['recharge_alipay_money'] . "<BR>";
                    }
                }

                $content .=  "--------------------------------<BR>";
                $content .=  "<BOLD>退款总额：".$printdata['refund_total_money']."</BOLD> <BR>";
                if($printdata['refund_cash_money'] > 0 && in_array($printdata['search_paytype'] ,['',0])) {
                    $content .= "-现金退款       金额：" . $printdata['refund_cash_money'] . "<BR>";
                }
                if(getcustom('restaurant_cashdesk_mix_pay')){
                    if(in_array($printdata['search_paytype'] ,['',0])) {
                        if ($printdata['mix_refund_wx_pay'] > 0) {
                            $content .= "--混合支付微信退款   金额：" . $printdata['mix_refund_wx_pay'] . "<BR>";
                        }
                        if ($printdata['mix_refund_alipay_pay'] > 0) {
                            $content .= "--混合支付支付宝退款  金额：" . $printdata['mix_refund_alipay_pay'] . "<BR>";
                        }
                        if ($printdata['mix_refund_sxf_pay'] > 0 && $printdata['sxfpay_show']) {
                            $content .= "--混合支付随行付退款  金额：" . $printdata['mix_refund_sxf_pay'] . "<BR>";
                        }
                    }
                }
                if($printdata['refund_yue_money'] > 0 &&  in_array($printdata['search_paytype'] ,['',1])) {
                    $content .= "-余额退款     金额：" . $printdata['refund_yue_money'] . "<BR>";
                }
                if($printdata['refund_wx_money'] > 0 &&  in_array($printdata['search_paytype'] ,['',2])) {
                    $content .= "-微信线上退款     金额：" . $printdata['refund_wx_money'] . "<BR>";
                }
                if($printdata['refund_alipay_money'] > 0 &&  in_array($printdata['search_paytype'] ,['',3])) {
                    $content .= "-支付宝线上退款     金额：" . $printdata['refund_alipay_money'] . "<BR>";
                }
                if($printdata['refund_sxf_money'] > 0 && $printdata['sxfpay_show'] &&  in_array($printdata['search_paytype'] ,['',81])) {
                    $content .= "-随行付退款     金额：" . $printdata['refund_sxf_money'] . "<BR>";
                }
                if($printdata['refund_douyin_hexaio_money'] > 0 && $printdata['douyinhx_show'] &&  in_array($printdata['search_paytype'] ,['',121])) {
                    $content .= "-抖音核销券退款     金额：" . $printdata['refund_douyin_hexaio_money'] . "<BR>";
                }
                if(getcustom('restaurant_cashdesk_custom_pay')){
                    if($printdata['refund_custom_list']){
                        foreach($printdata['refund_custom_list'] as $ckey=>$cval){
                            if($cval['refund_money'] > 0 && in_array($printdata['search_paytype'] ,['',$cval['paytypeid']])){
                                $content .=  "-".$cval['title']."退款      金额：".$cval['refund_money']."<BR>";
                            }
                        }
                    }
                }
               
                if(false){
                    $content .=  "      -银行卡     金额：0.00<BR>";
                    $content .=  "      -抖音团购   金额：0.00<BR>";
                    $content .=  "      -美团团购<BR>";

                }
                //店内扫码
                if(false) {
                    $content .= "  -现金           金额：0.00<BR>";
                    $content .= "  -随行付         金额：0.00<BR>";
                    $content .= "  -微信           金额：0.00<BR>";
                    $content .= "  -支付宝         金额：0.00<BR>";
                    $content .= "  -银行卡         金额：0.00<BR>";
                    $content .= "  -退款明细       金额：0.00<BR>";
                    $content .= "      -现金       金额：0.00<BR>";
                    $content .= "      -随行付     金额：0.00<BR>";
                    $content .= "      -微信       金额：0.00<BR>";
                    $content .= "      -支付宝     金额：0.00<BR>";
                    $content .= "      -银行卡     金额：0.00<BR>";
                }
                if(false) {
                    $content .=  "───────────────<BR>";
                    $content .= "<BOLD>钱箱预留：0.00</BOLD><BR>";
                    $content .= "<BOLD>存入备用金：0.00</BOLD><BR>";
                }
                $content .=  "--------------------------------<BR>";
                $content .=  "<BOLD>营业额汇总（包含会员储值与会员消费）：".$printdata['all_yingyee_money']."</BOLD><BR>";
                $content .=  "<BOLD>营业额汇总（仅不含会员储值）：".$printdata['yingyee_money']."</BOLD><BR>";

                $content .=  "<BOLD>收款汇总（仅不含会员余额消费）：".$printdata['total_in_money']."</BOLD><BR>";
//                $content .=  "<BOLD>收款汇总：".$printdata['all_total_in_money']."</BOLD><BR>";
                $content .=  "<BOLD>余额支付汇总（线上+线下）：".$printdata['all_yue_money']."</BOLD><BR>";
                
                if(false) {
                    $content .=  "<BOLD>应有现金：0.00</BOLD><BR>";
                    $content .=  "<BOLD>上交现金：0.00</BOLD><BR>";
                    $content .=  "<BOLD>钱箱留存：0.00</BOLD><BR>";
                }
                $content .=  "───────────────<BR>";
                $content .=  "<BOLD>打印日期：".date('Y-m-d H:i:s')."</BOLD><BR>";
                $content .= "<BR><BR>";
                $rs = \app\commons\Wifiprint::feie_print($machine['client_id'],$machine['client_secret'],$machine['machine_code'],$machine['msign'],$content,$machine['machine_type']);
            }
        }
    }

    //普通收银台 交班打印
    public static function jiaobanPrintCashier($printdata=[]){
        $cashier_info = $printdata['cashier_info'];
        $jiaoban_print_ids =explode(',',$cashier_info['jiaoban_print_ids']);
        $where = [];
        $where[] = ['aid', '=', $cashier_info['aid']];
        $where[] = ['id', 'in', $jiaoban_print_ids];

        $machineList = Db::name('wifiprint_set')->where($where)->select()->toArray();
        if(empty($machineList)) {
            return false;
        }

        foreach ($machineList as $machine) {
            if($machine['type'] ==0){  //易联云
                $content = $printdata['title']."\r";
                $content .=  "--------------------------------\r";
                $content .=  "Pos机：1\r";
                $content .=  "收银员：".$printdata['cashdesk_user']."\r";
                $content .=  "开始时间：".$printdata['logintime']."\r";
                $content .=  "结束时间：".$printdata['jiaobantime']."\r";
                $content .=  "———————————————\r";
                $content .=  "<FB>当班营业额，金额 ".$printdata['today_total_money']."</FB> \r";
                if($printdata['cashpay_show']){
                    $content .=  "  -现金           金额：".$printdata['today_cash_money']."\r";
                }
                if($printdata['yuepay_show']){
                    $content .=  "  -".t('会员')."余额       金额：".$printdata['today_yue_money']."\r";
                }
                if($printdata['sxfpay_show']){
//                    $content .=  "  -随行付          金额：".$printdata['today_sxf_money']."\r";
                    $content .=  "  -随行付微信      金额：".$printdata['today_sxf_wx_money']."\r";
                    $content .=  "  -随行付支付宝    金额：".$printdata['today_sxf_alipay_money']."\r";
                }
                if($printdata['wxpay_show']){
                    $content .=  "  -微信           金额：".$printdata['today_wx_money']."\r";
                }
                if($printdata['alipay_show']) {
                    $content .= "  -支付宝         金额：" . $printdata['today_alipay_money'] . "\r";
                }
                if(false) {
                    $content .=  "  -银行卡         金额：0.00\r";
                    $content .=  "  -抖音团购       金额：0.00\r";
                    $content .=  "  -美团团购\r";
                }
                //自定义支付
                if(getcustom('restaurant_cashdesk_custom_pay')){
                    if($printdata['today_custom_pay_list']){
                        foreach($printdata['today_custom_pay_list'] as $ckey=>$cval){
                            $content .=  "-".$cval['title']."         金额：".$cval['money']."\r";
                        }
                    }
                }
                $content .=  "  -退款明细\r";
                if($printdata['cashpay_show']) {
                    $content .= "      -现金       金额：" . $printdata['today_refund_cash_money'] . "\r";
                }
                if($printdata['sxfpay_show']) {
                    $content .= "      -随行付     金额：" . $printdata['today_refund_sxf_money'] . "\r";
                }
                if($printdata['wxpay_show']) {
                    $content .= "      -微信       金额：" . $printdata['today_refund_wx_money'] . "\r";
                }
                if($printdata['alipay_show']) {
                    $content .= "      -支付宝     金额：" . $printdata['today_refund_alipay_money'] . "\r";
                }
                if(false){
                    $content .=  "      -银行卡     金额：0.00\r";
                    $content .=  "      -抖音团购   金额：0.00\r";
                    $content .=  "      -美团团购\r";

                }
                //店内扫码
                $content .= "-店内扫码           金额：" . $printdata['today_scan_code_money'] . "\r";
                $content .=  "———————————————\r";
                if($printdata['recharge_show']) {
                    $total_recharge = $printdata['total_recharge'] > 0 ? $printdata['total_recharge'] : 0.00;
                    $content .= "<FB>会员储值（预付款）:" . $total_recharge . "</FB> \r";
                }
                if (false){
                    $content .=  "  -现金           金额：0.00\r";
                    $content .=  "  -随行付         金额：0.00\r";
                    $content .=  "  -微信           金额：0.00\r";
                    $content .=  "  -支付宝         金额：0.00\r";
                    $content .=  "  -银行卡         金额：0.00\r";
                    $content .=  "  -退款明细       金额：0.00\r";
                    $content .=  "      -现金       金额：0.00\r";
                    $content .=  "      -随行付     金额：0.00\r";
                    $content .=  "      -微信       金额：0.00\r";
                    $content .=  "      -支付宝     金额：0.00\r";
                    $content .=  "      -银行卡     金额：0.00\r";
                }
                if(false){
                    $content .=  "———————————————\r";
                    $content .=  "<FB>钱箱预留：0.00</FB>\r";
                    $content .=  "<FB>存入备用金：0.00</FB>\r";
                }
                $content .=  "———————————————\r";
                $content .=  "<FB>总收款（实收金额）：".$printdata['total_money']."</FB>\r";
                if(false){
                    $content .=  "<FB>应有现金：0.00</FB>\r";
                    $content .=  "———————————————\r";
                    $content .=  "<FB>上交现金：0.00</FB>\r";
                    $content .=  "<FB>钱箱留存：0.00</FB>\r";
                }
                $content .=  "———————————————\r";
                $content .=  "<FB>打印日期：".date('Y-m-d H:i:s')."</FB>\r";
                $content .= "\r\r";
                $rs = \app\commons\Wifiprint::yilianyun_print($machine['client_id'],$machine['client_secret'],$machine['access_token'],$machine['machine_code'],$machine['msign'],$content);
            }elseif ($machine['type']==1){ //飞蛾
                $content = $printdata['title']."<BR>";
                $content .=  "--------------------------------<BR>";
                $content .=  "Pos机：1<BR>";
                $content .=  "收银员：".$printdata['cashdesk_user']."<BR>";
                $content .=  "开始时间：".$printdata['logintime']."<BR>";
                $content .=  "结束时间：".$printdata['jiaobantime']."<BR>";
                $content .=  "───────────────<BR>";
                $content .=  "<BOLD>当班营业额，金额 ".$printdata['today_total_money']."</BOLD> <BR>";
                if($printdata['cashpay_show'] ){
                    $content .=  "-现金           金额：".$printdata['today_cash_money']."<BR>";
                }
                if($printdata['yuepay_show']) {
                    $content .= "-" . t('会员') . "余额       金额：" . $printdata['today_yue_money'] . "<BR>";
                }
                if($printdata['sxfpay_show'] ) {
//                    $content .= "-随行付         金额：" . $printdata['today_sxf_money'] . "<BR>";
                    $content .= "-随行付微信         金额：" . $printdata['today_sxf_wx_money'] . "<BR>";
                    $content .= "-随行付支付宝       金额：" . $printdata['today_sxf_alipay_money'] . "<BR>";
                }
                if($printdata['wxpay_show']) {
                    $content .= "-微信           金额：" . $printdata['today_wx_money'] . "<BR>";
                }
                if($printdata['alipay_show']) {
                    $content .= "-支付宝         金额：" . $printdata['today_alipay_money'] . "<BR>";
                }
                if(false){
                    $content .=  "-银行卡         金额：0.00<BR>";
                    $content .=  "-抖音团购       金额：0.00<BR>";
                    $content .=  "-美团团购<BR>";
                }
                //自定义支付
                if(getcustom('restaurant_cashdesk_custom_pay')){
                    if($printdata['today_custom_pay_list']){
                        foreach($printdata['today_custom_pay_list'] as $ckey=>$cval){
                            $content .=  "-".$cval['title']."         金额：".$cval['money']."<BR>";
                        }
                    }
                }
                $content .=  "-退款明细<BR>";
                if($printdata['cashpay_show'] > 0) {
                    $content .= "      -现金       金额：" . $printdata['today_refund_cash_money'] . "<BR>";
                }
                if($printdata['sxfpay_show']) {
                    $content .= "      -随行付     金额：" . $printdata['today_refund_sxf_money'] . "<BR>";
                }
                if($printdata['wxpay_show']) {
                    $content .= "      -微信       金额：" . $printdata['today_refund_wx_money'] . "<BR>";
                }
                if($printdata['alipay_show']) {
                    $content .= "      -支付宝     金额：" . $printdata['today_refund_alipay_money'] . "<BR>";
                }
                if(false){
                    $content .=  "      -银行卡     金额：0.00<BR>";
                    $content .=  "      -抖音团购   金额：0.00<BR>";
                    $content .=  "      -美团团购<BR>";

                }
                //店内扫码
                $content .= "-店内扫码           金额：" . $printdata['today_scan_code_money'] . "<BR>";
                $content .=  "───────────────<BR>";
                if($printdata['recharge_show']){
                    $total_recharge = $printdata['total_recharge']>0?$printdata['total_recharge']:0.00;
                    $content .=  "<BOLD>预付款数据(会员储值): ".$total_recharge."</BOLD> <BR>";
                }
              
                if(false) {
                    $content .= "  -现金           金额：0.00<BR>";
                    $content .= "  -随行付         金额：0.00<BR>";
                    $content .= "  -微信           金额：0.00<BR>";
                    $content .= "  -支付宝         金额：0.00<BR>";
                    $content .= "  -银行卡         金额：0.00<BR>";
                    $content .= "  -退款明细       金额：0.00<BR>";
                    $content .= "      -现金       金额：0.00<BR>";
                    $content .= "      -随行付     金额：0.00<BR>";
                    $content .= "      -微信       金额：0.00<BR>";
                    $content .= "      -支付宝     金额：0.00<BR>";
                    $content .= "      -银行卡     金额：0.00<BR>";
                }
                if(false) {
                    $content .=  "───────────────<BR>";
                    $content .= "<BOLD>钱箱预留：0.00</BOLD><BR>";
                    $content .= "<BOLD>存入备用金：0.00</BOLD><BR>";
                }
                $content .=  "───────────────<BR>";
                $content .=  "<BOLD>总收款（今日实际收到金额）：".$printdata['total_money']."</BOLD><BR>";
                if(false) {
                    $content .=  "<BOLD>应有现金：0.00</BOLD><BR>";
                    $content .=  "<BOLD>上交现金：0.00</BOLD><BR>";
                    $content .=  "<BOLD>钱箱留存：0.00</BOLD><BR>";
                }
                $content .=  "───────────────<BR>";
                $content .=  "<BOLD>打印日期：".date('Y-m-d H:i:s')."</BOLD><BR>";
                $content .= "<BR><BR>";
                $rs = \app\commons\Wifiprint::feie_print($machine['client_id'],$machine['client_secret'],$machine['machine_code'],$machine['msign'],$content,$machine['machine_type']);
            }
        }
    }
    public static function printModule(){
        return [
            'shop'=>'商城',
            'shop_weight'=>'称重',
            'restaurant'=>'餐饮',
        ];
    }

    //检查是否配置了某种打印机
    public static function checkWifiprintType($aid,$bid=-1,$type=0,$machineType=-1,$module=''){
        $where = [];
        if($bid!=='' && $bid>-1){
            $bid = $bid;
            $where[] = ['bid', '=', $bid];
        }
        $where[] = ['aid', '=', $aid];
        $where[] = ['status', '=', 1];
        if(is_numeric($machineType) && $machineType>-1){
            $where[] = ['machine_type', '=', $machineType];
        }
        $where[] = ['type', '=', $type];
        if(getcustom('wifiprint_bind_user') && uid>0){
            //该管理员绑定的打印机
            $print_ids = Db::name('wifiprint_user')->where('aid',$aid)->where('bid',$bid)->where('uid',uid)->column('print_id');
            if(empty($print_ids)) $print_ids = [];
            $print_ids[] = -1;//没有绑定打印机
            $where[] = ['id','in',$print_ids];
        }
        if(getcustom('restaurant_wifiprint_yingmeiyun') && !empty($module)) {
            $where[] = ['module','=',$module];
        }
        $machine = db('wifiprint_set')->where($where)->find();
        return $machine;
    }
    public static function daqu_print($user,$ukey,$sn,$content,$voice=0){
        if(getcustom('sys_print_daqu')){
            //芯烨标签打印
            $postdata = [];
            $postdata['sn']        = $sn;
            if($voice){
                //播报音源 不传参数视为不播报语音，只打印小票;
                $postdata['voice'] = $voice;
            }
            $postdata['content']   = $content;


            $uid  = uniqid('rxid');
            $time = time();
            $json = json_encode($postdata);
            $str  = $uid . $user . $time . $ukey . $json;
            $md5  = md5($str);
            $header = [
                'Content-Type: application/json',
                "uid:" . $uid,
                "appid:" . $user,
                "stime:" . $time,
                "sign:" . $md5
            ];
            $rs = curl_post('https://printer.juhesaas.com/openapi/print', $json, 0,$header);
            $rs = json_decode($rs,true);
            if($rs['code'] == 0){
                $rs['status'] = 1;
            }else{
                $rs['status'] = 0;
            }
            return $rs;
        }
    }
     public static function daqu_content($machine,$order=[],$type,$content=''){
        if(getcustom('sys_print_daqu')){
            $content .= "<C><font# height=2 width=2>** ".$machine['title']." **</font#></C>";
            if($type=='shop_refund'){
                $content .= "<C>申请退款</C>";
                $order['refund_num'] = db('shop_refund_order_goods')->where('refund_orderid',$order['id'])->sum('refund_num');
                $originalOrder = Db::name('shop_order')->where('aid',$order['aid'])->where('id',$order['orderid'])->find();
                $order['num'] = db('shop_order_goods')->where('orderid',$order['orderid'])->sum('num');
                $replaceField = ['num','freight_text','freight_time','linkman','tel','area','address','paytime','paytype','product_type','customer_id'];
                foreach ($replaceField as $field){
                    $order[$field]  = $originalOrder[$field];
                }
            }else if($type=='collage_refund'){
                $content .= "<C>申请退款</C>";
                $order['refund_num'] = $order['num'];
            }

            $content .='<BR><BR>';
            
            if($type=='maidan'){
                $content .= "订单标题：".$order['title']."<BR>";
            }
            $content .= "订单编号：".$order['ordernum']."<BR>";
            if($type=='cashier' || $type=='maidan'){
                //收银台无收货信息
            }else{
                $content .= "配送方式：".$order['freight_text']."<BR>";
                if($order['freight_time']){
                    $content .= "配送时间：<font# height=2 width=2>".$order['freight_time']."</font#><BR>";
                }
                $content .= "收货人:<font# height=2 width=2>".$order['linkman']."</font#><BR>";
                $content .= "联系电话:<font# height=2 width=2>".$order['tel']."</font#><BR>";
                $content .= "收货地址:<font# height=2 width=2>".$order['area']." ".$order['address']."</font#><BR>";
            }
            $content .= "付款时间：".date('Y-m-d H:i:s',$order['paytime'])."<BR>";
            $content .= "付款方式：".$order['paytype']."<BR><BR>";
            if($type=='maidan'){
                //买单没有商品信息
            }elseif($type=='cycle'){
                $content .= "商品名称     数量     期数     总价<BR>";
            }else{
                $content .= "商品名称     数量     总价<BR>";
            }
            //\app\commons\Order::hasOrderGoodsTable($type) 判断是否有order goods表
            if($type == 'shop'){
                $ordergoods = db($type.'_order_goods')->where('orderid',$order['id'])->select()->toArray();
                foreach($ordergoods as $item){
                    $content .= "<font# bolder=1>".$item['name']."(".$item['ggname'].")</font#>   ".$item['num']."  ".$item['totalprice']."<BR>";
                }
            }elseif($type == 'shop_refund'){
                $ordergoods = db($type.'_order_goods')->where('refund_orderid',$order['id'])->select()->toArray();
                foreach($ordergoods as $item){
                    $content .= "<font# bolder=1>".$item['name']."(".$item['ggname'].")</font#>   ".$item['refund_num']."  ".$item['refund_money']."<BR>";
                }
            }elseif($type=='cycle'){
                $content .= "<font# bolder=1>".$order['proname']."(".$order['ggname'].")</font#>   ".$order['num']."   ".$order['qsnum']."  ".$order['totalprice']."<BR>";
            }elseif($type=='collage'){
                $content .= "<font# bolder=1>".$order['proname']."(".$order['ggname'].")</font#>   ".$order['num']."  ".$order['totalprice']."<BR>";
            }elseif($type=='lucky_collage'){
                $content .= "<font# bolder=1>".$order['proname']."(".$order['ggname'].")</font#>   ".$order['num']."  ".$order['totalprice']."<BR>";
            }elseif($type=='seckill'){
                $content .= "<font# bolder=1>".$order['proname']."(".$order['ggname'].")</font#>   ".$order['num']."  ".$order['totalprice']."<BR>";
            }elseif($type=='kanjia'){
                $content .= "".$order['proname']."  1  ".$order['totalprice']."<BR>";
            }elseif($type=='tuangou'){
                $content .= "".$order['proname']."  ".$order['num']."  ".$order['totalprice']."<BR>";
            }elseif($type=='scoreshop'){
                $ordergoods = db('scoreshop_order_goods')->where('orderid',$order['id'])->select()->toArray();
                foreach($ordergoods as $item){
                    if($item['totalmoney'] > 0 && $item['totalscore'] > 0){
                        $price = $item['totalmoney']."元+".$item['totalscore'].t('积分');
                    }elseif($item['totalmoney'] > 0){
                        $price = $item['totalmoney']."元";
                    }else{
                        $price = $item['totalscore'].t('积分');
                    }
                    $content .= "<font# bolder=1>".$item['name']."</font#>   ".$item['num']."  ".$price."<BR>";
                }
            }elseif($type == 'cashier'){
                $ordergoods = db($type.'_order_goods')->where('orderid',$order['id'])->select()->toArray();
                foreach($ordergoods as $item){
                    $content .= "<font# bolder=1>".$item['proname']."(".$item['ggname'].")</font#>   ".$item['num']."  ".$item['totalprice']."<BR>";
                }
                $order['message'] = $order['remark'];
            }elseif($type == 'collage_refund'){
                $content .= "<font# bolder=1>".$order['proname']."(".$order['ggname'].")</font#>   ".$order['num']."  ".$order['refund_money']."<BR>";
            }
            $content .= "<BR>";
            if($order['message']){
                $content .= "备注：<font# height=2 width=2>".$order['message']."</font#><BR><BR>";
            }else{
                //$content .= "备注：无<BR>";
            }
            if($type=='scoreshop'){
                $content .= "<RIGHT>实付金额：".$price."</RIGHT>";
            }elseif($type=='cashier'){
                if($order['scoredk_money']>0){
                    $content .= "<RIGHT>积分抵扣：".$order['scoredk_money']."</RIGHT>";
                }
                if($order['coupon_money']>0) {
                    $content .= "<RIGHT>优惠券抵扣：" . $order['coupon_money'] . "</RIGHT>";
                }
                if($order['leveldk_money']>0) {
                    $content .= "<RIGHT>会员折扣：" . $order['leveldk_money'] . "</RIGHT>";
                }
                if($order['moling_money']>0) {
                    $content .= "<RIGHT>抹零金额：" . $order['moling_money'] . "</RIGHT>";
                }
                $content .= "<RIGHT>订单总价：".$order['pre_totalprice']."</RIGHT>";
                $content .= "<RIGHT>实付金额：".$order['totalprice']."</RIGHT>";
            }elseif($type=='maidan'){

                if($order['scoredk_money']>0){
                    $content .= "<RIGHT>积分抵扣：".$order['scoredk']."</RIGHT>";
                }
                if($order['leveldk_money']>0) {
                    $content .= "<RIGHT>会员折扣：" . $order['disprice'] . "</RIGHT>";
                }
                if($order['decmoney']>0) {
                    $content .= "<RIGHT>余额抵扣：" . $order['decmoney'] . "</RIGHT>";
                }
                if($order['couponmoney']>0) {
                    $content .= "<RIGHT>优惠券抵扣：" . $order['couponmoney'] . "</RIGHT>";
                }
                $content .= "<RIGHT>订单总价：".$order['money']."</RIGHT>";
                $content .= "<RIGHT>实付金额：".$order['paymoney']."</RIGHT>";
            }else{
                $content .= "<RIGHT>实付金额：".$order['totalprice']."</RIGHT>";
            }
            if($order['formdata']) {
                foreach ($order['formdata'] as $formdata) {
                    if($formdata[2] != 'upload') {
                        if($formdata[0] == '备注') {
                            $content .= $formdata[0]."：<font# height=2 width=2>".$formdata[1]."</font#><BR>";
                        } else {
                            $content .= $formdata[0]."：".$formdata[1]."<BR>";
                        }
                    }
                }
            }
            return $content;
        }
    }
    public static function xinye_print($user,$ukey,$sn,$content,$voice=1,$type=1){
        if(getcustom('sys_print_xinye')){
            //芯烨标签打印
            $postdata = [];
            $postdata['user']      = $user;
            $postdata['timestamp'] = time();
            $postdata['sign']      = sha1($user.$ukey.$postdata['timestamp']);
            $postdata['sn']        = $sn;
            $postdata['content']   = $content;
            //声音播放模式，0 为取消订单模式，1 为静音模式，2 为来单播放模式，3 为有用户申请退单了，默认为2 来单播放模式
            $postdata['voice']     = $voice;
            $header = [
                'Content-Type: application/json;charset=UTF-8'
            ];
            if($type == 1){
                $url = 'https://open.xpyun.net/api/openapi/xprinter/printLabel';
            }else{
                $url = 'https://open.xpyun.net/api/openapi/xprinter/print';
            }
            $rs = curl_post($url, json_encode($postdata), 0,$header);
            $rs = json_decode($rs,true);
            if($rs['code'] == 0){
                $rs['status'] = 1;
            }else{
                $rs['status'] = 0;
            }
            return $rs;
        }
    }
    public static function xinye_content($machine,$order=[],$type,$content='',$params=[]){
        if(getcustom('sys_print_xinye')){
            if($type =='recharge' && $params['opttype'] =='recharge'){
                //充值订单打印 ，直接重置
                $content= self::getRechargeContent($order,$machine['type']);
                return  $content ;
            }
            if($type =='recharge' && $params['opttype'] =='recharge_refund'){
                //充值订单打印 ，直接重置
                $content= self::getRechargeRefundContent($order,$machine['type'],$params['un']);
                return  $content ;
            }
            $content .= "<CB>** ".$machine['title']." **</CB>";
            if($type=='shop_refund'){
                $content .= "<C>申请退款</C>";
                //$ordergoods = db('shop_refund_order_goods')->where('refund_orderid',$order['id'])->select()->toArray();
                $order['refund_num'] = db('shop_refund_order_goods')->where('refund_orderid',$order['id'])->sum('refund_num');
                $originalOrder = Db::name('shop_order')->where('aid',$order['aid'])->where('id',$order['orderid'])->find();
                $order['num'] = db('shop_order_goods')->where('orderid',$order['orderid'])->sum('num');
                $replaceField = ['num','freight_text','freight_time','linkman','tel','area','address','paytime','paytype','product_type','customer_id'];
                foreach ($replaceField as $field){
                    $order[$field]  = $originalOrder[$field];
                }
            }elseif($type=='collage_refund'){
                $content .= "<C>申请退款</C>";
                $order['refund_num'] = $order['num'];
            }
            $content .='<BR><BR>';
            
            if($type=='maidan'){
                $content .= "订单标题：".$order['title']."<BR>";
            }
            $content .= "订单编号：".$order['ordernum']."<BR>";
            if($type=='cashier' || $type=='maidan'){
                //收银台无收货信息
            }else{
                $content .= "配送方式：".$order['freight_text']."<BR>";
                if($order['freight_time']){
                    $content .= "配送时间：<B>".$order['freight_time']."<BR></B>";
                }
                $content .= "收货人:<B>".$order['linkman']."<BR></B>";
                $content .= "联系电话:<B>".$order['tel']."<BR></B>";
                $content .= "收货地址:<B>".$order['area']." ".$order['address']."<BR></B>";
            }
            $content .= "付款时间：".date('Y-m-d H:i:s',$order['paytime'])."<BR>";
            $content .= "付款方式：".$order['paytype']."<BR><BR>";
            if($type=='maidan'){
                //买单没有商品信息
            }elseif($type=='cycle'){
                $content .= "商品名称     数量     期数     总价<BR>";
            }else{
                $content .= "商品名称     数量     总价<BR>";
            }
            //\app\commons\Order::hasOrderGoodsTable($type) 判断是否有order goods表
            if($type == 'shop'){
                $ordergoods = db($type.'_order_goods')->where('orderid',$order['id'])->select()->toArray();
                foreach($ordergoods as $item){
                    $content .= "<BOLD>".$item['name']."(".$item['ggname'].")</BOLD>   ".$item['num']."  ".$item['totalprice']."<BR>";
                }
            }elseif($type == 'shop_refund'){
                $ordergoods = db($type.'_order_goods')->where('refund_orderid',$order['id'])->select()->toArray();
                foreach($ordergoods as $item){
                    $content .= "<BOLD>".$item['name']."(".$item['ggname'].")</BOLD>   ".$item['refund_num']."  ".$item['refund_money']."<BR>";
                }
            }elseif($type=='cycle'){
                $content .= "<BOLD>".$order['proname']."(".$order['ggname'].")</BOLD>   ".$order['num']."   ".$order['qsnum']."  ".$order['totalprice']."<BR>";
            }elseif($type=='collage'){
                $content .= "<BOLD>".$order['proname']."(".$order['ggname'].")</BOLD>   ".$order['num']."  ".$order['totalprice']."<BR>";
            }elseif($type=='lucky_collage'){
                $content .= "<BOLD>".$order['proname']."(".$order['ggname'].")</BOLD>   ".$order['num']."  ".$order['totalprice']."<BR>";
            }elseif($type=='seckill'){
                $content .= "<BOLD>".$order['proname']."(".$order['ggname'].")</BOLD>   ".$order['num']."  ".$order['totalprice']."<BR>";
            }elseif($type=='kanjia'){
                $content .= "".$order['proname']."  1  ".$order['totalprice']."<BR>";
            }elseif($type=='tuangou'){
                $content .= "".$order['proname']."  ".$order['num']."  ".$order['totalprice']."<BR>";
            }elseif($type=='scoreshop'){
                $ordergoods = db('scoreshop_order_goods')->where('orderid',$order['id'])->select()->toArray();
                foreach($ordergoods as $item){
                    if($item['totalmoney'] > 0 && $item['totalscore'] > 0){
                        $price = $item['totalmoney']."元+".$item['totalscore'].t('积分');
                    }elseif($item['totalmoney'] > 0){
                        $price = $item['totalmoney']."元";
                    }else{
                        $price = $item['totalscore'].t('积分');
                    }
                    $content .= "<BOLD>".$item['name']."</BOLD>   ".$item['num']."  ".$price."<BR>";
                }
            }elseif($type == 'cashier'){
                $ordergoods = db($type.'_order_goods')->where('orderid',$order['id'])->select()->toArray();
                foreach($ordergoods as $item){
                    $content .= "<BOLD>".$item['proname']."(".$item['ggname'].")</BOLD>   ".$item['num']."  ".$item['totalprice']."<BR>";
                }
                $order['message'] = $order['remark'];
            }elseif($type == 'collage_refund'){
                $content .= "<BOLD>".$order['proname']."(".$order['ggname'].")</BOLD>   ".$order['num']."  ".$order['refund_money']."<BR>";
            }
            $content .= "<BR>";
            if($order['message']){
                $content .= "备注：<B>".$order['message']."<BR><BR></B>";
            }else{
                //$content .= "备注：无<BR>";
            }
            if($type=='scoreshop'){
                $content .= "<R>实付金额：".$price."<BR></R>";
            }elseif($type=='cashier'){
                if($order['scoredk_money']>0){
                    $content .= "<R>积分抵扣：".$order['scoredk_money']."<BR></R>";
                }
                if($order['coupon_money']>0) {
                    $content .= "<R>优惠券抵扣：" . $order['coupon_money'] . "<BR></R>";
                }
                if($order['leveldk_money']>0) {
                    $content .= "<R>会员折扣：" . $order['leveldk_money'] . "<BR></R>";
                }
                if($order['moling_money']>0) {
                    $content .= "<R>抹零金额：" . $order['moling_money'] . "<BR></R>";
                }
                $content .= "<R>订单总价：".$order['pre_totalprice']."<BR></R>";
                $content .= "<R>实付金额：".$order['totalprice']."<BR></R>";
            }elseif($type=='maidan'){

                if($order['scoredk_money']>0){
                    $content .= "<R>积分抵扣：".$order['scoredk']."<BR></R>";
                }
                if($order['leveldk_money']>0) {
                    $content .= "<R>会员折扣：" . $order['disprice'] . "<BR></R>";
                }
                if($order['decmoney']>0) {
                    $content .= "<R>余额抵扣：" . $order['decmoney'] . "<BR></R>";
                }
                if($order['couponmoney']>0) {
                    $content .= "<R>优惠券抵扣：" . $order['couponmoney'] . "<BR></R>";
                }
                $content .= "<R>订单总价：".$order['money']."<BR></R>";
                $content .= "<R>实付金额：".$order['paymoney']."<BR></R>";
            }else{
                $content .= "<R>实付金额：".$order['totalprice']."<BR></R>";
            }
            if($order['formdata']) {
                foreach ($order['formdata'] as $formdata) {
                    if($formdata[2] != 'upload') {
                        if($formdata[0] == '备注') {
                            $content .= $formdata[0]."：<B>".$formdata[1]."<BR></B>";
                        } else {
                            $content .= $formdata[0]."：".$formdata[1]."<BR>";
                        }
                    }
                }
            }
            return $content;
        }
    }
    //获取充值订单的打印内容 $machine_type 打印机类型
    public function getRechargeContent($order, $machine_type){
        if(getcustom('recharge_order_wifiprint')){
            $member =Db::name('member')->alias('m')
                ->join('member_level ml','ml.id = m.levelid')
                ->where('m.aid',$order['aid'])
                ->where('m.id',$order['mid'])
                ->field('m.id,ml.name as level_name,m.money')->find();
            $content = '';
            if($machine_type ==1){//飞蛾
                $content .= "<CB>充值凭证</CB><BR>";
                $content .= "会员ID：".$member['id']."<BR>";
                $content .= "会员等级：".$member['level_name']."<BR>";
                if($order['uid'] > 0){
                    $content .= "收银员 ID：".$order['uid']."<BR>";
                }else{
                    $content .= "收银员 ID：消费者线上充值<BR>";
                }
                $content .= '-----------------------------<BR>';
                $content .= '付款时间：'.date('Y-m-d H:i:s',$order['paytime'])."<BR>";
                $content .= '充值订单号：'.$order['ordernum']."<BR>";
                $content .= '付款方式：'.$order['paytype']."<BR>";
                if($order['paynum'] && $order['paynum'] !=1){
                    $content .= '付款单号：'.$order['ordernum']."<BR>";
                }
                $content .= "-----------------------------<BR>";
                $content .= "本次充值金额:<BR>";
                $content .= "<B>".$order['money']."元</B><BR>";
                $content .= '本次赠送金额:'.$order['give_money']."元<BR>";
                $totalmoney =  dd_money_format($order['money']+ $order['give_money'] );
                $content .= '合计到账:'.$totalmoney."元<BR>";
                $content .= "-----------------------------<BR>";
                $content .= "平台会员可用余额:<BR>";
                $content .= $member['money']."元<BR>";
                $content .= "-----------------------------<BR>";
                $content .= "更多优惠与福利请咨询店员";
            } elseif($machine_type ==4){//芯烨
                $content .= "<CB>充值凭证<BR></CB>";
                $content .= "会员ID：".$member['id']."<BR>";
                $content .= "会员等级：".$member['level_name']."<BR>";
                if($order['uid'] > 0){
                    $content .= "收银员 ID：".$order['uid']."<BR>";
                }else{
                    $content .= "收银员 ID：消费者线上充值<BR>";
                }
                $content .= '-----------------------------<BR>';
                $content .= '付款时间：'.date('Y-m-d H:i:s',$order['paytime'])."<BR>";
                $content .= '充值订单号：'.$order['ordernum']."<BR>";
                $content .= '付款方式：'.$order['paytype']."<BR>";
                if($order['paynum'] && $order['paynum'] !=1){
                    $content .= '付款单号：'.$order['ordernum']."<BR>";
                }
                $content .= "-----------------------------<BR>";
                $content .= "本次充值金额:<BR>";
                $content .= "<B>".$order['money']."元<BR></B>";
                $content .= '本次赠送金额:'.$order['give_money']."元<BR>";
                $totalmoney =  dd_money_format($order['money']+ $order['give_money'] );
                $content .= '合计到账:'.$totalmoney."元<BR>";
                $content .= "-----------------------------<BR>";
                $content .= "平台会员可用余额:<BR>";
                $content .= $member['money']."元<BR>";
                $content .= "-----------------------------<BR>";
                $content .= "更多优惠与福利请咨询店员";
            }
            return $content;
        }
    }
    //充值退款打印
    public function getRechargeRefundContent($order, $machine_type,$un=''){
        if (getcustom('member_recharge_detail_refund')){
            $member =Db::name('member')->alias('m')
                ->join('member_level ml','ml.id = m.levelid')
                ->where('m.aid',$order['aid'])
                ->where('m.id',$order['mid'])
                ->field('m.id,ml.name as level_name,m.money')->find();
            $content = '';
            if($machine_type ==1){//飞蛾
                $content .= "<CB>充值退款凭证</CB><BR>";
                $content .= "会员ID：".$member['id']."<BR>";
                $content .= "会员等级：".$member['level_name']."<BR>";
                $content .= "操作员 ID：".$un."<BR>";
                $content .= '操作时间：'.date('Y-m-d H:i:s',$order['refund_time'])."<BR>";
                $content .= '-----------------------------<BR>';
                $content .= '原充值单号：'.$order['ordernum']."<BR>";
                $content .= '原付款方式：'.$order['paytype']."<BR>";
                $content .= '原付款时间：'.date('Y-m-d H:i:s',$order['paytime'])."<BR>";
                $content .= '原实际支付金额：'.$order['money']."元<BR>";
                $content .= '原赠送金额:'.$order['give_money']."元<BR>";
                $totalmoney =  dd_money_format($order['money']+ $order['give_money'] );
                $content .= '原合计到账:'.$totalmoney."元<BR>";
                $content .= "-----------------------------<BR>";
                $content .= "依照双方签订的相关协议本次应退:<BR>";
                $content .= "<B>".$order['refund_money']."元</B><BR>";
                $out_refund_no = Db::name('wxrefund_log')->where('aid',$order['aid'])->where('ordernum',$order['ordernum'])->value('out_refund_no');
                if($out_refund_no){
                    $content .= "退款单号：".$out_refund_no."<BR>";
                } else{
                    $content .= "退款说明：已退回到原付款方式<BR>";
                }
                $content .= "-----------------------------<BR>";
                $content .= '退款后可用余额:'.$member['money']."元<BR>";
                $content .= '备注:<BR>';
                $content .= "<B>".$order['refund_reason']."</B><BR>";
               
            } elseif($machine_type ==4){//芯烨

                $content .= "<CB>充值退款凭证<BR></CB>";
                $content .= "会员ID：".$member['id']."<BR>";
                $content .= "会员等级：".$member['level_name']."<BR>";
                $content .= "操作员 ID：".$un."<BR>";
                $content .= '操作时间：'.date('Y-m-d H:i:s',$order['refund_time'])."<BR>";
                $content .= '-----------------------------<BR>';
                $content .= '原充值单号：'.$order['ordernum']."<BR>";
                $content .= '原付款方式：'.$order['paytype']."<BR>";
                $content .= '原付款时间：'.date('Y-m-d H:i:s',$order['paytime'])."<BR>";
                $content .= '原实际支付金额：'.$order['money']."元<BR>";
                $content .= '原赠送金额:'.$order['give_money']."元<BR>";
                $totalmoney =  dd_money_format($order['money']+ $order['give_money'] );
                $content .= '原合计到账:'.$totalmoney."元<BR>";
                $content .= "-----------------------------<BR>";
                $content .= "依照双方签订的相关协议本次应退:<BR>";
                $content .= "<B>".$order['refund_money']."元<BR></B>";
                $out_refund_no = Db::name('wxrefund_log')->where('aid',$order['aid'])->where('ordernum',$order['ordernum'])->value('out_refund_no');
                if($out_refund_no){
                    $content .= "退款单号：".$out_refund_no."<BR>";
                }else{
                    $content .= "退款说明：已退回到原付款方式<BR>";
                }
                $content .= "-----------------------------<BR>";
                $content .= '退款后可用余额:'.$member['money']."元<BR>";
                $content .= '备注:<BR>';
                $content .= "<B>".$order['refund_reason']."<BR></B>";
            }
            return $content;
        }
    }
}