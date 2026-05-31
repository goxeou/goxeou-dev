<?php


namespace app\customs;
use think\facade\Db;
class YuyueCustom
{
    public static function deal_protype($aid,$mid,$buydata,$post,$protype){
        if(getcustom('extend_yuyue_car')){
            //查询商品类型
            foreach($buydata as $data){
                $i++;
                if($data['prodata']){
                    $prodata = explode('-',$data['prodata']);
                }else{
                    return ['status'=>0,'msg'=>'产品数据错误'];
                }
                foreach($prodata as $key=>$pro){
                    $sdata = explode(',',$pro);
                    $sdata[2] = intval($sdata[2]);
                    if($sdata[2] <= 0) return ['status'=>0,'msg'=>'购买数量有误'];

                    $product = Db::name('yuyue_product')->where('aid',$aid)->where('id',$sdata[0])->field('id,status,type')->find();
                    if(!$product) return ['status'=>0,'msg'=>'产品不存在或已下架'];
                    if($product['status']==0) return ['status'=>0,'msg'=>'商品未上架'];

                    if($protype== -1 ){
                        $protype = $product['type'];
                    }else{
                        if($product['type'] != $protype){
                            return ['status'=>0,'msg'=>'订单存在不同类型商品，不能一同下单'];
                        }
                    }
                }
            }

            //如果是洗车类型
            if($protype == 1){

                if(!$post['cardata']){
                    return ['status'=>0,'msg'=>'请填写车辆相关信息'];
                }
                $cardata = $post['cardata'];

                $car = Db::name('member_car')->where('id',$cardata['carid'])->where('aid',$aid)->where('mid',$mid)->find();
                if(!$car){ 
                    return ['status'=>0,'msg'=>'所选车辆信息不存在'];
                }

                //查询车辆信息
                if($post['fwtype']==1){
                    $address = [
                        'id'       => 0,
                        'name'     => $car['name'],
                        'tel'      => $car['tel'],
                        'latitude' => '',
                        'longitude'=> '',
                        'area'     => '',
                        'province' => '',
                        'city'     => '',
                        'district' => '',
                        'area'     => '',
                        'address'  => ''
                    ];
                }else{
                    if(!$cardata['carlocat_latitude'] || !$cardata['carlocat_longitude']){
                        return ['status'=>0,'msg'=>'请选择车辆位置'];
                    }
                    //通过坐标获取省市区
                    $mapqq = new \app\commons\MapQQ();
                    $res = $mapqq->locationToAddress($cardata['carlocat_latitude'],$cardata['carlocat_longitude']);
                    if($res['status'] != 1){
                        return $res;
                    }
                    $province  = $res['province'];
                    $city      = $res['city'];
                    $district  = $res['district'];

                    if(!$cardata['carlocat_stop']) {
                        return ['status'=>0,'msg'=>'请填写停靠位置'];
                    }
                    $address = [
                        'id'       => 0,
                        'name'     => $car['name'],
                        'tel'      => $car['tel'],
                        'latitude' => $cardata['carlocat_latitude'],
                        'longitude'=> $cardata['carlocat_longitude'],
                        'area'     => $cardata['carlocat_address'],
                        'province' => $province,
                        'city'     => $city,
                        'district' => $district,
                        'area'     => $cardata['carlocat_address'],
                        'address'  => $cardata['carlocat_stop']
                    ];

                }
            }else{
                $car     = '';
                $address = '';
            }

            return ['status'=>1,'protype'=>$protype,'car'=>$car,'address'=>$address];
        }
    }

    public static function get_worker($order){
        if(getcustom('extend_yuyue_car')){
            //查询最近的可派单的师傅
            $worker_id  = 0;
            $worker_len = 0;
            //获取师傅
            $workerlist = Db::name('yuyue_worker')
                ->where('aid',$order['aid'])
                ->where('bid',$order['bid'])
                ->where('status',1);
            if(getcustom('yuyue_apply')){
                $workerlist = $workerlist
                    ->where('shstatus',1);
            }
            $workerlist = $workerlist
                ->field('id,aid,bid,longitude,latitude')
                ->select()
                ->toArray();
            if($workerlist){
                foreach($workerlist as $wv){
                    //查询此师傅是否操作过此订单
                    $count_worker = Db::name('yuyue_worker_order')->where('orderid',$order['id'])->where('worker_id',$wv['id'])->where('aid',$order['aid'])->count();
                    if(!$count_worker){
                        //查询师傅此时间是否已接单
                        $count_order = Db::name('yuyue_order')
                            ->where('worker_id',$wv['id'])
                            ->where('aid',$order['aid'])
                            ->where('status','in','1,2')
                            ->where('yy_time',$order['yy_time'])
                            ->count();
                        if(!$count_order){
                            //骑行距离
                            $mapqq = new \app\commons\MapQQ();
                            $bicycl = $mapqq->getDirectionDistance($order['longitude'],$order['latitude'],$wv['longitude'],$wv['latitude']);
                            if($bicycl && $bicycl['status']==1){
                                $len = $bicycl['distance'];
                            }else{
                                $len  = getdistance($order['longitude'],$order['latitude'],$wv['longitude'],$wv['latitude']);
                            }
                            if($worker_id == 0){
                                $worker_id = $wv['id'];
                            }else{
                                if($worker_len<$len){
                                    $worker_id  = $wv['id'];
                                    $worker_len = $len;
                                }
                            }
                        }
                    }
                }
                unset($wv);
            }
            return $worker_id;
        }
    }

    public static function deal_order(){
        if(getcustom('extend_yuyue_car')){
            //预约洗车订单
            $adminlist = Db::name('admin')
                ->where('status',1)
                ->field('id,yuyuecar_status')
                ->select()
                ->toArray();
            if($adminlist){
                foreach($adminlist as $v){
                    //如果有洗车权限
                    if($v['yuyuecar_status']){
                        //查询预约配置
                        $yyset = Db::name('yuyue_set')->where('aid',$v['id'])->field('id,autopd_worker')->find();
                        //开启派送最近服务人员功能
                        if($yyset && $yyset['autopd_worker']){
                            //查询未派送洗车订单
                            $orderlist = Db::name('yuyue_order')
                                ->where('status',1)
                                ->where('protype',1)
                                ->where('worker_id',0)
                                ->where('worker_orderid',0)
                                ->select()
                                ->toArray();
                            if($orderlist){
                                foreach($orderlist as $ov){

                                    //下一个小时内的结束时间
                                    $next_endtime = strtotime(date("Y-m-d H",$ov['paytime']).':00:00')+2*60*60;

                                    //转换预约时间
                                    $yydate = explode('-',$ov['yy_time']);
                                    //开始时间
                                    $begindate = $yydate[0];
                                    if(strpos($begindate,'年') === false){
                                        $begindate = date('Y').'年'.$begindate;
                                    }
                                    $begindate = preg_replace(['/年|月/','/日/'],['-',''],$begindate);
                                    $begintime = strtotime($begindate);

                                    //如果等于或超出结束时间
                                    if($begintime>=$next_endtime){
                                        //进入抢单大厅
                                        $rs = \app\models\YuyueWorkerOrder::create($ov,0,'');
                                    }else{
                                        //继续派单
                                        $worker_id = self::get_worker($ov);
                                        if($worker_id){
                                            \app\models\YuyueWorkerOrder::create($ov,$worker_id,'');
                                        }
                                    }
                                }
                                unset($ov);
                            }
                        }
                    }
                }
                unset($sv);
            }
            
        }
    }
    //派单
    public static function  dispatch_order(){
        if(getcustom('extend_yuyue_car')) {
            $adminlist = Db::name('admin')
                ->where('status', 1)
                ->where('id',1)
                ->field('id,yuyuecar_status')
                ->select()
                ->toArray();
            if (!$adminlist) {
                return;
            }
            foreach ($adminlist as $v) {
                //洗车权限
                if(!$v['yuyuecar_status']){
                    continue;
                }
                //查询预约配置
                $yyset = Db::name('yuyue_set')->where('aid', $v['id'])->field('id,autopd_worker')->find();
                //开启派送最近服务人员功能
                if ($yyset && $yyset['autopd_worker']) {
                    $orderlist = Db::name('yuyue_worker_order')->alias('wo')
                        ->join('yuyue_order yo', 'wo.orderid = yo.id')
                        ->where('wo.status', 0)
                        ->where('wo.worker_id', 0) 
                        ->field('wo.*,yo.paytime,yo.yy_time')
                        ->select()->toArray();
                    if(!$orderlist){
                        continue;
                    }
                    foreach ($orderlist as $ov) {
                        //17:05
                        $pay_time = date('H:i', $ov['paytime']);
                        
                        $pay_time_arr = explode(':',$pay_time);
                        //分钟 大于30 还是小于30
                        if($pay_time_arr[1] < 30){
                            $starttime = $pay_time_arr[0].':00';
                        }else{
                            $hour = $pay_time_arr[0] +1;
                            $starttime =$hour.':00';  
                        }
                      
                        $starttime = strtotime(date('Y-m-d', $ov['paytime']).' '.$starttime);
                        $endtime = $starttime + 30*60;
                        //转换预约时间
                        $yydate = explode('-',$ov['yy_time']);
                        //开始时间
                        $begindate = $yydate[0];
                        if(strpos($begindate,'年') === false){
                            $begindate = date('Y').'年'.$begindate;
                        }
                        $begindate = preg_replace(['/年|月/','/日/'],['-',''],$begindate);
                        $begintime = strtotime($begindate);
                        if($starttime <= $begintime && $begintime <= $endtime){
                            //派单
                            $worker_id = self::get_worker($ov);
                            if($worker_id){
                                Db::name('yuyue_order')->where('aid',$v['id'])->where('id',$ov['orderid'])->update(['worker_id'=>$worker_id,'worker_orderid'=>$ov['id'],'send_time'=>time()]);
                                Db::name('yuyue_worker_order')->where('id',$ov['id'])->update(['status'=>1,'worker_id'=>$worker_id,'starttime'=>time()]);
                                send_socket(['type'=>'yuyue_worker_jiedan','data'=>['aid'=>$v['id'],'mid'=>mid,'psorderid'=>$ov['id']]]);
                            }
                        }
                    }
                }
            }
        }
    }
}