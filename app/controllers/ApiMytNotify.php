<?php


//custom_file(express_maiyatian) 
// +----------------------------------------------------------------------
// | 麦芽田
// +----------------------------------------------------------------------
namespace app\controllers;
use app\BaseController;
use think\facade\Db;
use think\facade\Log;
class ApiMytNotify  extends BaseController
{
    public function index(){
        if(getcustom('express_maiyatian')){
            $xml  = file_get_contents('php://input');
            Log::write('MytNotify');
            Log::write($xml);
            $res = json_decode($xml,true);
            if(!$res['order_id'] || !$res['origin_id'] || !$res['sign']){
                die(json_encode(['status'=>'fail']));
            }
            //查询订单
            $where   = [];
            $where[] = ['ordernum','=',$res['origin_id']];
            $where[] = ['myt_order_id','=',$res['order_id']];
            $where[] = ['psid','=',-2];
            $order = Db::name('peisong_order')->where($where)->field('id,aid,bid,mid,orderid,ordernum,type')->order('id desc')->find();
            if($order){
                $set = Db::name('peisong_set')->where('aid',$order['aid'])->find();
                if($set){
                    $res_sign = $res['sign'];
                    unset($res['sign']);
                    $sign = \app\customs\MaiYaTianCustom::get_sign($set,$res);
                    if($sign == $res_sign){
                    //if(true){
                        $mytdata = [];
                        $mytdata['updatetime'] = time();
                        //查询关联表
                        $myt = Db::name('peisong_order_myt')->where('poid',$order['id'])->find();

                        if($res['status'] == 10){
                            $up = Db::name('peisong_order')->where('id',$order['id'])->update(['status'=>0]);
                        }
                        if($res['status'] == 20){
                            $updata = [];
                            $updata['status']  = 1;
                            $updata['psfee']   = $res["amount"];
                            $updata['starttime'] = $res['ctime']?$res['ctime']:time();
                            $up = Db::name('peisong_order')->where('id',$order['id'])->update($updata);

                            $mytdata["delivery_amount"] = $res["delivery_amount"];
                            $mytdata["tip_amount"]      = $res["tip_amount"];
                            $mytdata["amount"]          = $res["amount"];

                            if($res["amount"]>0){
                                \app\commons\Business::addmoney($order['aid'],$order['bid'],-$res["amount"],'配送费');
                            }
                        }
                        if($res['status'] == 30){
                            $updata = [];
                            $updata['status']   = 2;
                            $updata['daodiantime'] = $res['ctime']?$res['ctime']:time();
                            $up = Db::name('peisong_order')->where('id',$order['id'])->update($updata);

                        }
                        if($res['status'] == 40){
                            $updata = [];
                            $updata['status']   = 3;
                            $updata['quhuotime'] = $res['ctime']?$res['ctime']:time();
                            $up = Db::name('peisong_order')->where('id',$order['id'])->update($updata);

                        }
                        if($res['status'] == 50){
                            $updata = [];
                            $updata['status']   = 4;
                            $updata['endtime'] = $res['ctime']?$res['ctime']:time();
                            $up = Db::name('peisong_order')->where('id',$order['id'])->update($updata);

                        }
                        if($res['status'] == 60 || $res['status'] == 70){
                            $updata = [];
                            $updata['status']   = 10;
                            $up = Db::name('peisong_order')->where('id',$order['id'])->update($updata);

                            if($res['status'] == 60){
                                $mytdata["cancel_amount"]      = $res["cancel_amount"];
                                $mytdata["cancel_reason_code"] = $res["cancel_reason_code"];
                                $mytdata["cancel_reason"]      = $res["cancel_reason"];
                                if($myt && $myt['cancel_amount']<=0 && $res["cancel_amount"]>0){
                                    \app\commons\Business::addmoney($order['aid'],$order['bid'], $res["cancel_amount"]*-1,'取消配送扣除违约金');
                                }
                            }

                            if($res['status'] == 70){
                                $mytdata["reject_code"] = $res["reject_code"];
                                $mytdata["reject_msg"]  = $res["reject_msg"];
                            }

                            //修改订单状态为已支付或已接单
                            if(empty($order['bid'])){
                                $type_order = Db::name($order['type'])->where('id',$order['orderid'])->where('aid',$order['aid'])->where('status',2)->count();
                            }else{
                                $type_order = Db::name($order['type'])->where('id',$order['orderid'])->where('aid',$order['aid'])->where('bid',$order['bid'])->where('status',2)->count();
                            }

                            if($type_order){
                                $updata = [];
                                if($order['type'] == 'restaurant_takeaway_order'){
                                    $updata['status'] = 12;
                                }else{
                                    $updata['status'] = 1;
                                }
                                $up = Db::name($order['type'])->where('id',$order['orderid'])->where('status',2)->update($updata);
                            }
                        }

                        // if($res['status'] == 70){
                        //     $updata = [];
                        //     $updata['status']   = 0;
                        //     $up = Db::name('peisong_order')->where('id',$order['id'])->update($updata);
                        //     $mytdata["reject_code"] = $res["reject_code"];
                        //     $mytdata["reject_msg"]  = $res["reject_msg"];
                        // }

                        if($res["logistic"]) $mytdata["logistic"]    = $res["logistic"];
                        if($res["logistic_no"]) $mytdata["logistic_no"] = $res["logistic_no"];

                        if($res["rider_id"]) $mytdata["rider_id"]    = $res["rider_id"];
                        if($res["rider_name"]) $mytdata["rider_name"]  = $res["rider_name"];
                        if($res["rider_phone"]) $mytdata["rider_phone"] = $res["rider_phone"];
                        if($res["rider_longitude"]) $mytdata["rider_longitude"] = $res["rider_longitude"];
                        if($res["rider_latitude"]) $mytdata["rider_latitude"]   = $res["rider_latitude"];

                        if($res["content"]) $mytdata["content"]     = $res["content"]?$res["content"]:'';
                        if($res["distance"]) $mytdata["distance"]    = $res["distance"];
                        if($res["is_transfer"]) $mytdata["is_transfer"] = $res["is_transfer"];
                        if($res["rider_latitude"]) $mytdata["rider_latitude"]  = $res["rider_latitude"];
                        if($res["rider_longitude"]) $mytdata["rider_longitude"] = $res["rider_longitude"];

                        if($res['status'] == 20 || $res['status'] == 30 || $res['status'] == 40 ){
                            //更新骑手位置
                            $res_location = \app\customs\MaiYaTianCustom::delivery_location($order['aid'],$order['bid'],$set,$order['ordernum']);
                            if($res_location['status'] == 1){
                                if($res_location["rider_id"]) $mytdata["rider_id"]       = $res_location["rider_id"];
                                if($res_location["rider_name"]) $mytdata["rider_name"]   = $res_location["rider_name"];
                                if($res_location["rider_phone"]) $mytdata["rider_phone"] = $res_location["rider_phone"];
                                if($res_location["rider_longitude"]) $mytdata["rider_longitude"] = $res_location["rider_longitude"];
                                if($res_location["rider_latitude"]) $mytdata["rider_latitude"]   = $res_location["rider_latitude"];
                            }
                        }
                        if($myt){
                            $upmyt = Db::name('peisong_order_myt')->where('id',$myt['id'])->update($mytdata);
                        }else{
                            $mytdata['aid'] = $order['aid'];
                            $mytdata['bid'] = $order['bid'];
                            $mytdata['mid'] = $order['mid'];
                            $mytdata['poid']= $order['id'];
                            $mytdata['createtime'] = time();
                            Db::name('peisong_order_myt')->insert($mytdata);
                        }
                    }
                }
            }
            die(json_encode(['status'=>'success']));
        }
    }
}