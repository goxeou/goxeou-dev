<?php


//custom_file(paotui)
namespace app\customs;
use think\facade\Db;
class PaotuiCustom
{   
    public static function change_status($order,$rider_name,$rider_mobile,$time){
        if(getcustom('paotui')){
            if($order['type'] == 'paotui_order'){
                $updata = [];
                $updata['pu_name'] = $rider_name;
                $updata['pu_tel']  = $rider_mobile;
                if($post['status'] == 'accepted'){ //接单
                    $updata['status'] = 2;
                    $updata['starttime'] = $time;
                }elseif($post['status'] == 'wait_to_shop'){ //到店
                    $updata['status'] = 3;
                    $updata['daodiantime'] = $time;
                }elseif($post['status'] == 'geted'){ //取货
                    $updata['status'] = 4;
                    $updata['quhuotime'] = $time;
                }elseif($post['status'] == 'gotoed'){ //完成
                    $updata['status'] = 5;
                    $updata['endtime'] = $time;
                }
                $updata['updatetime'] = time();
                Db::name('paotui_order')->where('id',$order['orderid'])->update($updata);
            }
        }
    }
    public static function change_status2($order,$postObj){
        if(getcustom('paotui')){
            if($order['type'] == 'paotui_order'){
                $updata = [];
                $updata['pu_name'] = strval($postObj->agent->name);
                $updata['pu_tel']  = strval($postObj->agent->phone);

                //配送公司接单阶段——分配骑手成功
                if($postObj->order_status == '102'){
                    $updata['status']    = 2;
                    $updata['starttime'] = strval($postObj->action_time);
                }
                //骑手取货阶段——骑手到店开始取货
                if($postObj->order_status == '201'){
                    $updata['status']      = 3;
                    $updata['daodiantime'] = strval($postObj->action_time);
                }
                //202   骑手取货阶段——取货成功,301    骑手配送阶段——配送中
                if($postObj->order_status == '202' || $postObj->order_status == '301'){
                    $updata['status']    = 4;
                    $updata['quhuotime'] = strval($postObj->action_time);
                }
                //301   骑手配送阶段——配送中
                if($postObj->order_status == '301' && empty($order['quhuotime'])){
                    $updata['status']    = 4;
                    $updata['quhuotime'] = strval($postObj->action_time);
                }
                //骑手配送阶段——配送成功， 订单结束
                if($postObj->order_status == '302'){
                    $updata['status']  = 5;
                    $updata['endtime'] = strval($postObj->action_time);
                }
                $updata['updatetime'] = time();
                Db::name('paotui_order')->where('id',$order['orderid'])->update($updata);
            }
        }
    }

    //直接取消
    public static function cancelOrder2($order){
        $data = [];
        $data['updatetime'] = time();
        $data['status'] = -1;
        if($order['totalprice']>0){
            //退款
            $rs = \app\commons\Order::refund($order,$order['totalprice'],'取消订单');
            if($rs['status']==0){
                $data['status']             = -2;
                $data['refund_status']      = -2;//-2：退款失败 -1驳回退款 1: 申请退款 2：退款成功
                $data['refund_money']       = $order['totalprice'];
                $data['cancel_fail_reason'] = $rs['msg']?$rs['msg']:'';
                $up = Db::name('paotui_order')->where('id',$order['id'])->update($data);
                return ['status'=>0,'msg'=>$rs['msg']];
            }else{
                $data['status']       = -1;
                $data['refund_status']= 2;//-2：退款失败 -1驳回退款 1: 申请退款 2：退款成功
                $data['refund_money'] = $order['totalprice'];
                $data['refund_time']  = time();
                //$data['cancel_reason']= '';
            }
        }
        $up = Db::name('paotui_order')->where('id',$order['id'])->update($data);
        if($up){
            return ['status'=>1,'msg'=>'取消成功'];
        }else{
            return ['status'=>0,'msg'=>'取消失败'];
        }
    }

    //处理跑腿订单推送到哪个配送端 $type 0:下单推送 1、后台推送
    public static function deal_push($orderid,$psid=0,$type = 0){
        $order = Db::name('paotui_order')->where('id',$orderid)->find();
        if($order && $order['status'] == 1){

            //推送到哪个配送端，没则默认推送到系统

            $xitong_status     = false;
            $make_status       = false;
            $express_wx_status = false;

            $paotui_set =  Db::name('paotui_set')->where('aid',$order['aid'])->find();
            if($paotui_set){
                //配送端设置
                $peisong_set = Db::name('peisong_set')->where('aid',$order['aid'])->find();
                if($peisong_set){
                    if($paotui_set['type'] == 0){
                        $xitong_status  = true;
                    }else if($paotui_set['type'] == 1){
                        $make_status =  true ;
                        $psid = -1;
                    }else if($paotui_set['type'] == 2){
                        }
                }
            }

            //如果这几个方式都没有，就默认为系统配送
            if(!$xitong_status && !$make_status && !$express_wx_status){
                $xitong_status = true;
            }

            //处理一些兼容数据参数
            $order['title'] = $order['name'];
            $order['type']  = 'paotui_order';
            $order['linkman']   = $order['send_name'];
            $order['tel']       = $order['send_tel'];
            $order['area']      = $order['send_area'];
            $order['address']   = $order['send_address'];
            $order['longitude'] = $order['send_longitude'];
            $order['latitude']  = $order['send_latitude'];
            $order['message']   = $order['remark'];
            $order['product_price'] = 0;
            $order['freight_price'] = $order['totalprice'];

            $order['proname']   = $order['name'];
            $order['ggname']    = '';
            $order['propic']    = '';
            $order['sell_price']= 0;
            $order['num']       = 1;

            $updata = [];
            $updata['updatetime'] = time();
            if($express_wx_status){

                $rs = \app\customs\ExpressWx::addOrder('paotui_order',$order);
                $test = [];
                \think\facade\Log::write(json_encode($rs));
                if($rs['status'] == 0){
                    $rs2 = \app\commons\Order::refund($order,$order['totalprice'],'下单失败，退回');
                    if($rs2['status']==0){
                        $updata['status']             = -2;
                        $updata['refund_status']      = -2;//-2：退款失败 -1驳回退款 1: 申请退款 2：退款成功
                        $updata['refund_money']       = $order['totalprice'];
                        $updata['cancel_fail_reason'] = $rs2['msg']?$rs2['msg']:'';
                    }else{
                        $updata['status']        = -1;
                        $updata['refund_status'] = 2;//-2：退款失败 -1驳回退款 1: 申请退款 2：退款成功
                        $updata['refund_money']  = $order['totalprice'];
                        $updata['cancel_reason'] = '下单失败，退回';
                        $updata['refund_time']   = time();
                    }
                }else{
                    //即时配送
                    $updata['push_type'] = 3;
                }
            }else{
                if($type == 0){
                    //如果是系统培训，并且没有系统参数或者为指定配送员模式，先不推送，后台手动推送
                    if($xitong_status && (!$peisong_set || $peisong_set['paidantype'] ==1)){
                        $updata['is_assign'] = 1;//后台手动推送
                        //系统
                        $updata['push_type'] = 1;
                    }else{
                        $other = [];
                        $rs = \app\models\PeisongOrder::create('paotui_order',$order,$psid,$other);
                        if($rs['status'] == 0){
                            $rs2 = \app\commons\Order::refund($order,$order['totalprice'],'下单失败，退回');
                            if($rs2['status']==0){
                                $updata['status']             = -2;
                                $updata['refund_status']      = -2;//-2：退款失败 -1驳回退款 1: 申请退款 2：退款成功
                                $updata['refund_money']       = $order['totalprice'];
                                $updata['cancel_fail_reason'] = $rs2['msg']?$rs2['msg']:'';
                            }else{
                                $updata['status']        = -1;
                                $updata['refund_status'] = 2;//-2：退款失败 -1驳回退款 1: 申请退款 2：退款成功
                                $updata['refund_money']  = $order['totalprice'];
                                $updata['cancel_reason'] = '下单失败，退回';
                                $updata['refund_time']   = time();
                            }
                        }else{
                            if($make_status){
                                //码科
                                $updata['push_type'] = 2;
                            }else{
                                //系统
                                $updata['push_type'] = 1;
                            }
                        }
                    }
                }else{
                    $other = [];
                    $rs = \app\models\PeisongOrder::create('paotui_order',$order,$psid,$other);
                    if($rs['status'] == 0){
                        $rs2 = \app\commons\Order::refund($order,$order['totalprice'],'下单失败，退回');
                        if($rs2['status']==0){
                            $updata['status']             = -2;
                            $updata['refund_status']      = -2;//-2：退款失败 -1驳回退款 1: 申请退款 2：退款成功
                            $updata['refund_money']       = $order['totalprice'];
                            $updata['cancel_fail_reason'] = $rs2['msg']?$rs2['msg']:'';
                        }else{
                            $updata['status']        = -1;
                            $updata['refund_status'] = 2;//-2：退款失败 -1驳回退款 1: 申请退款 2：退款成功
                            $updata['refund_money']  = $order['totalprice'];
                            $updata['cancel_reason'] = '下单失败，退回';
                            $updata['refund_time']   = time();
                        }
                    }else{
                        if($make_status){
                            //码科
                            $updata['push_type'] = 2;
                        }else{
                            //系统
                            $updata['push_type'] = 1;
                        }
                    }
                }
            }
            Db::name('paotui_order')->where('id',$orderid)->update($updata);
            if($updata['status'] == -1){
                return ['status'=>0 ,'msg'=>$updata['cancel_reason']];
            }else if($updata['status'] == -2){
                return ['status'=>0 ,'msg'=>$updata['cancel_fail_reason']];
            }else{
                return ['status'=>1 ,'msg'=>''];
            }
            
        }else{
            return ['status'=>0 ,'msg'=>'订单不存在或状态不符'];
        }
    }
}