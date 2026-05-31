<?php



namespace app\customs;
use think\facade\Db;
class WifiprintCustom
{   
    //易联云自定义 $deal_type 1、自定义 2：底部自定义
    public static function deal_content($type,$order,$machine,$print_day_ordernum='',$deal_type = 2){
        if(getcustom('sys_print_set')){

            $content = '';
            if($deal_type == 1){
                if($type == 'shop'){
                    $ordergoods = db('shop_order_goods')->where('orderid',$order['id'])->select()->toArray();
                    $order['num'] = db('shop_order_goods')->where('orderid',$order['id'])->sum('num');
                }elseif($type=='scoreshop'){
                    $ordergoods = db('scoreshop_order_goods')->where('orderid',$order['id'])->select()->toArray();
                    $order['num'] = db('scoreshop_order_goods')->where('orderid',$order['id'])->sum('num');
                }else{
                    $ordergoods = [['name'=>$order['proname'],'ggname'=>$order['ggname'],'num'=>$order['num'],'totalprice'=>$order['product_price']]];
                }
                if($type=='scoreshop'){
                    if($order['totalprice'] > 0 && $order['score_price'] > 0){
                        $order['totalprice'] = $order['totalprice']."元 + ".$order['score_price'].t('积分');
                    }elseif($order['totalprice'] > 0){
                        $order['totalprice'] = $order['totalprice']."元";
                    }else{
                        $order['totalprice'] = $order['score_price'].t('积分');
                    }
                }elseif($type == 'cashier'){
                    $ordergoods = db('cashier_order_goods')->where('orderid',$order['id'])->select()->toArray();
                    $order['num'] = db('cashier_order_goods')->where('orderid',$order['id'])->sum('num');
                }

                $message = '';
                if($order['formdata']) {
                    foreach ($order['formdata'] as $formdata) {
                        if($formdata[2] != 'upload') {
                            if($formdata[0] == '备注') {
                                $message = $formdata[1];
                            }
                        }
                    }
                }
                $content .= "<MS>1,".$machine['voice']."</MS>\r";
                if(getcustom('sys_print_set')){
                    if($print_day_ordernum && $machine['day_ordernum']){
                        $content .=  '<FB><center>#'.$print_day_ordernum."</center></FB>\r\r";
                    }
                }
                $tmplcontent = $machine['tmplcontent'];
            }else if($deal_type == 2){
                $tmplcontent = $machine['boot_custom_content'];
            }
            
            if($tmplcontent){
                if(strpos($tmplcontent,'<br>')!==false){
                    $tmplcontent = str_replace("<br>","\r",$tmplcontent);
                }
            }

            if($deal_type == 1){
                if(strpos($tmplcontent,'<td>')!==false){
                    $tmplcontentArr = explode('<td>',$tmplcontent);
                    //td之前的数据
                    $tmplcontent1 = $tmplcontentArr[0];

                    $tmplcontentArr = explode('</td>',$tmplcontentArr[1]);

                    //td直接的数据
                    $tmplcontent2 = $tmplcontentArr[0];
                    $table = '';

                    //td之后的数据
                    $tmplcontent3 = $tmplcontentArr[1];
                }else{
                    $tmplcontent1 = $tmplcontent;

                    $tmplcontent2 = '';
                    $table        = '';

                    $tmplcontent3 = '';
                }
                //替换变量数据
                if($type=='maidan'){
                    $textReplaceArr = [
                        '[订单号]'=>$order['ordernum'],
                        '[付款时间]'=>date('Y-m-d H:i:s',$order['paytime']),
                        '[付款方式]'=>$order['paytype'],
                        '[价格]'=>$order['money'],
                        '[实付金额]'=>$order['paymoney'],
                        '[备注]'=>$message,
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

                //替换变量
                $tmplcontent1 = str_replace(array_keys($textReplaceArr),array_values($textReplaceArr),$tmplcontent1);
                if($tmplcontent3){
                    $tmplcontent3 = str_replace(array_keys($textReplaceArr),array_values($textReplaceArr),$tmplcontent3);
                }

                if($tmplcontent2){
                    $table = "<table>";
                    if($type!='maidan'){ //买单没有商品信息
                        //$table .= "<tr><td>商品名称</td><td>数量</td><td>总价</td></tr>";
                        //替换掉[
                        $str = str_replace("[","",$tmplcontent2);
                        //分隔成数组
                        $str_arr = explode(']',$str);

                        //取出标题
                        $table .= "<tr>";
                        foreach($str_arr as $sv){
                            $table .= "<td>";
                            $table .= $sv;
                            $table .= "</td>";
                        }
                        unset($sv);
                        $table .= "</tr>";

                        if($type == 'shop'){

                            foreach($ordergoods as $item){

                                $table .= "<tr>";
                                foreach($str_arr as $sv){
                                    $sv = trim($sv);
                                    if($sv == '商品名称'){
                                        $table .= "<td>";
                                        $table .= $item['name'];
                                        $table .= "</td>";
                                    }
                                    if($sv == '规格'){
                                        $table .= "<td>";
                                        $table .= $item['ggname'];
                                        $table .= "</td>";
                                    }
                                    if($sv == '数量'){
                                        $table .= "<td>";
                                        $table .= $item['num'];
                                        $table .= "</td>";
                                    }
                                    if($sv == '总价'){
                                        $table .= "<td>";
                                        $table .= $item['totalprice'];
                                        $table .= "</td>";
                                    }
                                }
                                unset($sv);
                                $table .= "</tr>";
                            }
                        }elseif($type=='collage' || $type=='lucky_collage' || $type=='seckill' || $type=='tuangou'){
                            $table .= "<tr>";
                            foreach($str_arr as $sv){
                                $sv = trim($sv);
                                if($sv == '商品名称'){
                                    $table .= "<td>";
                                    $table .= $order['proname'];
                                    $table .= "</td>";
                                }
                                if($sv == '规格'){
                                    $table .= "<td>";
                                    $table .= $order['ggname'];
                                    $table .= "</td>";
                                }
                                if($sv == '数量'){
                                    $table .= "<td>";
                                    $table .= $order['num'];
                                    $table .= "</td>";
                                }
                                if($sv == '价格'){
                                    $table .= "<td>";
                                    $table .= $order['totalprice'];
                                    $table .= "</td>";
                                }
                            }
                            $table .= "</tr>";

                        }elseif($type=='kanjia'){
                            $table .= "<tr>";
                            foreach($str_arr as $sv){
                                $sv = trim($sv);
                                if($sv == '商品名称'){
                                    $table .= "<td>";
                                    $table .= $order['proname'];
                                    $table .= "</td>";
                                }
                                if($sv == '规格'){
                                    $table .= "<td>";
                                    $table .= '';
                                    $table .= "</td>";
                                }
                                if($sv == '数量'){
                                    $table .= "<td>";
                                    $table .= 1;
                                    $table .= "</td>";
                                }
                                if($sv == '价格'){
                                    $table .= "<td>";
                                    $table .= $order['totalprice'];
                                    $table .= "</td>";
                                }
                            }
                            $table .= "</tr>";
                        }elseif($type=='scoreshop'){
                            foreach($ordergoods as $item){
                                if($item['totalmoney'] > 0 && $item['totalscore'] > 0){
                                    $price = $item['totalmoney']."元+".$item['totalscore'].t('积分');
                                }elseif($item['totalmoney'] > 0){
                                    $price = $item['totalmoney']."元";
                                }else{
                                    $price = $item['totalscore'].t('积分');
                                }
                                $table .= "<tr>";
                                foreach($str_arr as $sv){
                                    if($sv == '商品名称'){
                                        $table .= "<td>";
                                        $table .= $item['name'];
                                        $table .= "</td>";
                                    }
                                    if($sv == '规格'){
                                        $table .= "<td>";
                                        $table .= '';
                                        $table .= "</td>";
                                    }
                                    if($sv == '数量'){
                                        $table .= "<td>";
                                        $table .= $item['num'];
                                        $table .= "</td>";
                                    }
                                    if($sv == '总价'){
                                        $table .= "<td>";
                                        $table .= $price;
                                        $table .= "</td>";
                                    }
                                }
                                unset($sv);
                                $table .= "</tr>";
                            }
                        }elseif($type=='cashier'){
                            foreach($ordergoods as $item){
                                $table .= "<tr>";
                                foreach($str_arr as $sv){
                                    if($sv == '商品名称'){
                                        $table .= "<td>";
                                        $table .= $item['proname'];
                                        $table .= "</td>";
                                    }
                                    if($sv == '规格'){
                                        $table .= "<td>";
                                        $table .= $item['ggname'];
                                        $table .= "</td>";
                                    }
                                    if($sv == '数量'){
                                        $table .= "<td>";
                                        $table .= $item['num'];
                                        $table .= "</td>";
                                    }
                                    if($sv == '总价'){
                                        $table .= "<td>";
                                        $table .= $item['totalprice'];
                                        $table .= "</td>";
                                    }
                                }
                                unset($sv);
                                $table .= "</tr>";
                            }
                        }
                    }
                    $table .= "</table>";
                }
                $content .= $tmplcontent1.$table.$tmplcontent3;
            }else if($deal_type == 2){
                $content = $tmplcontent;
            }
            
            return ['status'=>1,'data'=>$content];
        }
    }
}