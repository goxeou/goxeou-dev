<?php


//custom_file(paotui)
namespace app\controllers;
use think\facade\Db;
class ApiPaotui extends ApiCommon
{
    public $set = [];
    public $xitong_status     = false;
    public $make_status       = false;
    public $express_wx_status = false;
    public $cancel_status     = 2;//可取消最大状态。1 已支付 2：已接单
    public $end_refund_status = false;//完成的是否可以申请退款

    public function initialize(){
        parent::initialize();

        $set =  Db::name('paotui_set')->where('aid',aid)->find();
        $action = request()->action();
        if($action == 'index' || $action == 'get_timelist' || $action == 'get_address' || $action == 'count_price' || $action == 'distance_fee' || $action == 'weight_fee' || $action == 'create'){
            if(!$set || $set['status']!=1){
                die(jsonEncode(['status'=>0,'msg'=>'系统暂未开启跑腿服务']));
            }
            $this->set = $set;

            $peisong_set = Db::name('peisong_set')->where('aid',aid)->find();

            if($set['type'] == 0){
                if($peisong_set['status'] !=1){
                    die(jsonEncode(['status'=>0,'msg'=>'系统配送未开启']));
                }
                if(!$peisong_set){
                    die(jsonEncode(['status'=>0,'msg'=>'无系统配送设置']));
                }
            }else if($set['type'] == 1){
                if(!$peisong_set){
                    die(jsonEncode(['status'=>0,'msg'=>'无'.t('码科').'配送设置']));
                }
                $res = \app\commons\Make::access_token(aid);
                if($res && $res['status'] === 0){
                    die(jsonEncode(['status'=>0,'msg'=>$res['msg']]));
                }
            }else if($set['type'] == 2){
                if(!$peisong_set){
                    die(jsonEncode(['status'=>0,'msg'=>'无即时配送设置']));
                }
                if(false){}else{
                    die(jsonEncode(['status'=>0,'msg'=>'无指定配送端']));
                }
            }else{
                die(jsonEncode(['status'=>0,'msg'=>'无指定配送端']));
            }
        }
        
        
        // if($peisong_set['status']==1 && bid>0 && $peisong_set['businessst']==0 && $peisong_set['make_status']==0){
        //     die(jsonEncode(['status'=>0,'msg'=>'多商户暂无跑腿服务']));
        // };

        // $this->xitong_status     = $peisong_set['status']==1 ? true : false;
        // $this->make_status       = $peisong_set['make_status']==1 ? true : false;
        // if($this->make_status){
        //     $res = \app\commons\Make::access_token(aid);
        //     if($res['status'] == 0){
        //         die(jsonEncode(['status'=>0,'msg'=>$res['msg']]));
        //     }
        // }
        // $this->express_wx_status = false;
         // if(!$this->xitong_status && !$this->make_status && !$this->express_wx_status){
        //     die(jsonEncode(['status'=>0,'msg'=>'系统暂未设置配送端']));
        // }

    }
    public function index(){
        $this->checklogin();

        $set = $this->set;
        $data = [];
        $data['show_detail'] = true;
        $data['show_weight'] = true;
        $data['content']  = '';
        if($set['content']){
            $data['content'] = $set['content'];
        }

        $data['dayList'] = [
            '今天','明天'
        ];

        $hourList = [
            '00','01','02','03','04','05','06','07','08','09','10','11',
            '12','13','14','15','16','17','18','19','20','21','22','23'
        ];

        $data['hourList'] = ['立即取件'];
        $hour = date("H",time());
        foreach($hourList as $hv){
            $hv_num = intval($hv);
            if($hv_num>$hour){
                array_push($data['hourList'],$hv);
            }
        }
        unset($hv);

        $data['minuteList'] = [
            '立即取件','00','10','20','30','40','50'
        ];
        $data['pic'] = $set['pic'];
        $data['cancel_status'] = $this->cancel_status;
        return $this->json(['status'=>1,'data'=>$data]);
    }

    public function get_timelist(){
        $this->checklogin();

        $set = $this->set;
        $dayVal = input('dayVal')?input('dayVal'):'';
        if(!$dayVal){
            return $this->json(['status'=>0,'msg'=>'时间错误']);
        }

        $hourList = [
            '00','01','02','03','04','05','06','07','08','09','10','11',
            '12','13','14','15','16','17','18','19','20','21','22','23'
        ];

        $minuteList = [
            '00','10','20','30','40','50'
        ];

        $data = [];
        $data['hourList'] = [];
        if($dayVal == '今天'){
             $data['hourList'] = ['立即取件'];
            $hour = date("H",time());
            foreach($hourList as $hv){
                $hv_num = intval($hv);
                if($hv_num>$hour){
                    array_push($data['hourList'],$hv);
                }
            }
            unset($hv);
            $data['minuteList'] = [
                '立即取件','00','10','20','30','40','50'
            ];
        }else{
            $data['hourList'] = [
                '00','01','02','03','04','05','06','07','08','09','10','11',
                '12','13','14','15','16','17','18','19','20','21','22','23'
            ];
            $data['minuteList'] = [
                '00','10','20','30','40','50'
            ];
        }
        return $this->json(['status'=>1,'data'=>$data]);
    }

    //获取地址信息
    public function get_address(){
        $this->checklogin();
        if(request()->isPost()){

            $set = $this->set;

            $key = 'ABLBZ-4BIKU-GFTVB-BK7IK-OLQ35-QCBFF';
            $post = input('post.');

            if(!$post['latitude'] || !$post['longitude']){
                return $this->json(['status'=>0,'msg'=>'请授权获取地理位置']);
            }

            $data = [];

            $data['latitude']  = $post['latitude'];
            $data['longitude'] = $post['longitude'];

            //通过坐标获取省市区
            $mapqq = new \app\commons\MapQQ();
            $address = $mapqq->locationToAddress($data['latitude'],$data['longitude']);
            if($address && $address['status']==1){
                $result = $address['result'];
                $data['formatted_addresses']   = $result['formatted_addresses']['recommend'];
                $data['address']               = $address['address'];

                $data['prov_name'] = $address['province'];
                $data['city_name'] = $address['city'];
                $data['dist_name'] = $address['district'];

                //查询是否属于开通城市
                $province = explode(',',$set['province']);
                if(!in_array($data['prov_name'],$province)){
                    return $this->json(['status'=>0,'msg'=>'此区域暂未开通服务']);
                }

                //查询是否属于开通城市
                $city = explode(',',$set['city']);
                if(!in_array($data['city_name'],$city)){
                    return $this->json(['status'=>0,'msg'=>'此区域暂未开通服务']);
                }

                return $this->json(['status'=>1,'data'=>$data,'msg'=>'']);
            }else{
                return $this->json(['status'=>0,'msg'=>'获城市信息失败']);
            }
        }
    }

    //计算价格
    public function count_price(){
        if(request()->isPost()){

            $btntype = input('btntype')?input('btntype/d'):0;
            if($btntype !=1 && $btntype != 2){
                return $this->json(['status'=>1,'price'=>'']);
            }

            $take_longitude  = input('take_longitude')?input('take_longitude'):0;
            $take_latitude   = input('take_latitude')?input('take_latitude'):0;
            $send_longitude = input('send_longitude')?input('send_longitude'):0;
            $send_latitude  = input('send_latitude')?input('send_latitude'):0;

            $weight   = input('weight')?round(input('weight'),2):0;

            $tip_fee  = 0;
            if(input('tip_fee')){
                $tip_fee  = input('tip_fee')*100;
                $tip_fee  = floor($tip_fee)/100;
            }

            $dayVal   = input('dayVal')?input('dayVal'):'';
            $hourVal  = input('hourVal')?input('hourVal'):'';
            $minuteVal= input('minuteVal')?input('minuteVal'):'';

            if(!$take_longitude || !$take_latitude || !$send_longitude || !$send_latitude){
                return $this->json(['status'=>1,'price'=>'']);
            }

            $set = $this->set;
            $price = 0;

            $res_distance = self::distance_fee($set,$take_longitude,$take_latitude,$send_longitude,$send_latitude);
            if($res_distance['status'] == 0){
                return $this->json($res_distance);
            }
            $distance_fee = $res_distance['distance_fee'];
            $distance     = $res_distance['distance'];
            $price        += $distance_fee;

            $res_weight = self::weight_fee($set,$weight);
            if($res_weight['status'] == 0){
                return $this->json($res_weight);
            }
            $weight_fee = $res_weight['weight_fee'];
            $price      += $weight_fee;

            if($tip_fee>0){
                $price += $tip_fee;
            }

            $data = [];
            $data['status'] = 1;
            $data['price']  = $price;
            $data['distance_fee'] = $distance_fee;
            if($distance<=$set['distance_one']){
                $data['distance'] =  '基础费用';
            }else{
                $data['distance'] = '约'.$distance.'公里';
            }
            $data['weight_fee']   = $weight_fee;
            $data['tip_fee']      = $tip_fee;
            $data['time_fee']     = 0;
            $data['dt_fee']       = 0;

            return $this->json($data);
        }
    }

    private static function distance_fee($set,$take_longitude,$take_latitude,$send_longitude,$send_latitude){
        $distance_fee = 0;
        //计算距离 按照骑行距离计算
        $mapqq = new \app\commons\MapQQ();
        $bicycl = $mapqq->getDirectionDistance($take_longitude,$take_latitude,$send_longitude,$send_latitude,2);
        if($bicycl && $bicycl['status']==1){
            $distance = $bicycl['distance'];
        }else{
            $distance = getdistance($take_longitude,$take_latitude,$send_longitude,$send_latitude,2);
        }
        if($distance>$set['max_distance']){
            return ['status'=>0,'msg'=>'配送距离过远，请重新选择'];
        }
        //计算距离价格
        if($distance<=$set['distance_one']){
            $distance_fee =  round($set['distance_fee_one'],2);
        }else{
            //如果有设置
            if($set['distance_two']>0 && $set['distance_fee_two']>0){
                //计算超出倍数
                $cha = $distance-$set['distance_one'];
                $b_num = ceil($cha/$set['distance_two']) * $set['distance_fee_two'];

                $distance_fee =  round($set['distance_fee_one'],2)+round($b_num,2);
            }
        }
        return ['status'=>1,'distance_fee'=>$distance_fee,'distance'=>$distance];
    }

    private static function weight_fee($set,$weight){
        $weight_fee = 0;
        if($weight>$set['max_weight']){
            return ['status'=>0,'msg'=>'超出最大'.$set['max_weight'].'公斤配送重量'];
        }
        //计算重量价格
        if($weight<=$set['weight_one']){

            $weight_fee =  round($set['weight_fee_one'],2);
        }else{
            //如果有设置
            if($set['weight_two']>0 && $set['weight_fee_two']>0){
                //计算超出倍数
                $cha = $weight-$set['weight_one'];
                $b_num = ceil($cha/$set['weight_two']) * $set['weight_fee_two'];

                $weight_fee =  round($set['weight_fee_one'],2)+round($b_num,2);
            }
        }
        return ['status'=>1,'weight_fee'=>$weight_fee];
    }
    //下单
    public function create(){
        if(request()->isPost()){
            $set = $this->set;
            //类型
            $btntype = input('btntype')?input('btntype/d'):0;
            if($btntype !=1 && $btntype !=2){
                return $this->json(['status'=>0,'msg'=>'下单类型错误']);
            }

            $name  = input('name')?input('name'):0;
            if(!$name){
                return $this->json(['status'=>0,'msg'=>'请填写物品名称']);
            }

            $pic  = input('pic')?input('pic'):'';
            if(!$pic){
                $pic = $set['pic']?$set['pic']:'';
            }

            $take_id      = input('take_id')?input('take_id'):0;
            $take_address = Db::name('member_address')->where('id',$take_id)->where('mid',mid)->where('aid',aid)->find();
            if(!$take_address){
                if($btntype == 1){
                    return $this->json(['status'=>0,'msg'=>'请完善取货地址']);
                }else{
                    return $this->json(['status'=>0,'msg'=>'请完善收货地址']);
                }
            }

            $send_id      = input('send_id')?input('send_id'):0;
            $send_address = Db::name('member_address')->where('id',$send_id)->where('mid',mid)->where('aid',aid)->find();
            if(!$send_address){
                if($btntype == 1){
                    return $this->json(['status'=>0,'msg'=>'请完善收货地址']);
                }else{
                    return $this->json(['status'=>0,'msg'=>'请完善取货地址']);
                }
            }

            $weight   = input('weight')?round(input('weight'),2):0;
            if(!$weight|| $weight<=0){
                return $this->json(['status'=>0,'msg'=>'请填写重量']);
            }

            $tip_fee  = 0;
            if(input('tip_fee')){
                $tip_fee  = input('tip_fee')*100;
                $tip_fee  = floor($tip_fee)/100;
            }

            //取货时间
            $dayVal   = input('dayVal')?input('dayVal'):'';
            $hourVal  = input('hourVal')?input('hourVal'):'';
            $minuteVal= input('minuteVal')?input('minuteVal'):'';
            $take_time = 0;
            if($dayVal){
                if($dayVal == '今天'){
                    $day_time = date("Y-m-d",time());
                }else if($dayVal == '明天'){
                    $day_time = date("Y-m-d",strtotime("+1 days",time()));
                }else if($dayVal == '后天'){
                    $day_time = date("Y-m-d",strtotime("+2 days",time()));
                }else{
                    return $this->json(['status'=>0,'msg'=>'取货时间错误']);
                }

                if(!$hourVal || !$minuteVal){
                    return $this->json(['status'=>0,'msg'=>'取货时间错误']);
                }
                $take_time = strtotime($day_time." ".$hourVal.":".$minuteVal);

                if($take_time<time()){
                    return $this->json(['status'=>0,'msg'=>'取货时间必须大于当前时间']);
                }
            }

            $set = $this->set;

            //计算支付费用
            $price = 0;

            //距离
            $res_distance = self::distance_fee($set,$take_address['longitude'],$take_address['latitude'],$send_address['longitude'],$send_address['latitude']);
            if($res_distance['status'] == 0){
                return $this->json($res_distance);
            }
            $distance_fee = $res_distance['distance_fee'];
            $distance     = $res_distance['distance'];
            $price        += $distance_fee;

            //物品
            $res_weight = self::weight_fee($set,$weight);
            if($res_weight['status'] == 0){
                return $this->json($res_weight);
            }
            $weight_fee = $res_weight['weight_fee'];
            $price      += $weight_fee;

            if($tip_fee>0){
                $price += $tip_fee;
            }

            $data = [];
            $data['aid'] = aid;
            $data['mid'] = mid;
            $data['btntype'] = $btntype;
            $data['ordernum']= date('ymdHis').rand(100000,999999);

            //物品名称
            $data['name']    = $name;
            $data['pic']     = $pic;
            //取件地址
            $data['take_addressid'] = $take_id;
            $data['take_area']      = $take_address['area'];
            $data['take_address']   = $take_address['address'];
            $data['take_name']      = $take_address['name'];
            $data['take_tel']       = $take_address['tel'];
            $data['take_longitude'] = $take_address['longitude'];
            $data['take_latitude']  = $take_address['latitude'];
            $data['take_province']  = $take_address['province']?$take_address['province']:'';
            $data['take_city']      = $take_address['city']?$take_address['city']:'';

            //收件地址
            $data['send_addressid']= $send_id;
            $data['send_area']     = $send_address['area'];
            $data['send_address']  = $send_address['address'];
            $data['send_name']     = $send_address['name'];
            $data['send_tel']      = $send_address['tel'];
            $data['send_longitude']= $send_address['longitude'];
            $data['send_latitude'] = $send_address['latitude'];
            $data['send_province'] = $send_address['province']?$send_address['province']:'';
            $data['send_city']     = $send_address['city']?$send_address['city']:'';

            //重量
            $data['weight']    = $weight;

            //取货时间
            $data['dayVal']    = $dayVal;
            $data['hourVal']   = $hourVal;
            $data['minuteVal'] = $minuteVal;
            $data['take_time'] = $take_time;

            $data['distance']     = $distance;//距离

            //费用
            $data['distance_fee'] = $distance_fee;//距离费用
            $data['weight_fee']   = $weight_fee;//重量费用
            $data['tip_fee']      = $tip_fee;//小费
            $data['totalprice']   = $price;

            $data['remark']       = input('remark')?input('remark'):'';
            $data['platform']     = platform;
            $data['createtime']   = time();
            //$data['updatetime']   = time();

            $orderid = Db::name('paotui_order')->insertGetId($data);

            if($orderid){
                $payorderid = \app\models\Payorder::createorder(aid,0,mid,'paotui',$orderid,$data['ordernum'],'跑腿',$data['totalprice']);
                if($payorderid){
                    return $this->json(['status'=>1,'msg'=>'下单成功','payorderid'=>$payorderid]);
                }else{
                    return $this->json(['status'=>0,'msg'=>'下单失败']);
                }
            }else{
                return $this->json(['status'=>0,'msg'=>'下单失败']);
            }
        }
    }

    public function orderlist(){

        $st = trim(input('param.st'));
        if($st != 'all'){
            $st = intval($st);
        }

        $where = [];
        $where[] = ['aid','=',aid];
        $where[] = ['mid','=',mid];
        $where[] = ['is_del','=',0];

        if($st !== 'all'){
            if($st ==1 || $st ==5){
                $where[] = ['status','=',$st];
            }else if($st ==24){
                $where[] = ['status','>=',2];
                $where[] = ['status','<=',4];
            }else if($st ==-2){
                $where[] = ['refund_money','>',0];
                $where[] = ['refund_status','<>',0];
            }
        }

        $pernum = 10;
        $pagenum = input('post.pagenum');
        if(!$pagenum) $pagenum = 1;

        $datalist = Db::name('paotui_order')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
        if(!$datalist) $datalist = array();

        $rdata = [];
        $rdata['datalist'] = $datalist;
        $rdata['st'] = $st;
        $rdata['cancel_status']     = $this->cancel_status;
        $rdata['end_refund_status'] = $this->end_refund_status;
        return $this->json($rdata);
    }
    public function orderdetail(){
        $detail = Db::name('paotui_order')->where('id',input('param.id/d'))->where('mid',mid)->where('is_del',0)->where('aid',aid)->find();
        if(!$detail) return $this->json(['status'=>0,'msg'=>'订单不存在']);

        if($detail['take_time']){
            $detail['take_time'] = date('Y-m-d H:i',$detail['take_time']);
        }else{
            $detail['take_time'] = '立即取件';
        }
        $detail['createtime']      = $detail['createtime'] ? date('Y-m-d H:i:s',$detail['createtime']) : '';
        $detail['paytime']      = $detail['paytime'] ? date('Y-m-d H:i:s',$detail['paytime']) : '';
        $detail['starttime']    = $detail['starttime'] ? date('Y-m-d H:i:s',$detail['starttime']) : '';
        $detail['daodiantime']  = $detail['daodiantime'] ? date('Y-m-d H:i:s',$detail['daodiantime']) : '';
        $detail['quhuotime']    = $detail['quhuotime'] ? date('Y-m-d H:i:s',$detail['quhuotime']) : '';
        $detail['edntime']      = $detail['edntime'] ? date('Y-m-d H:i:s',$detail['edntime']) : '';
        $detail['refund_time']  = $detail['refund_time'] ? date('Y-m-d H:i:s',$detail['refund_time']) : '';

        $rdata = [];
        $rdata['status'] = 1;
        $rdata['detail'] = $detail;
        $rdata['cancel_status']     = $this->cancel_status;
        $rdata['end_refund_status'] = $this->end_refund_status;
        return $this->json($rdata);
    }

    //删除
    public function delOrder(){

        $orderid = input('post.orderid')?input('orderid/d'):0;
        if(!$orderid){
            return $this->json(['status'=>0,'msg'=>'删除失败']);
        }

        $order = Db::name('paotui_order')->where('id',$orderid)->where('mid',mid)->where('aid',aid)->find();
        if(!$order){
            return $this->json(['status'=>0,'msg'=>'订单不存在']);
        }

        if($order['status'] != -1 && $order['status'] != 5 ){
            return $this->json(['status'=>0,'msg'=>'订单状态不符']);
        }

        $del = Db::name('paotui_order')->where('id',$orderid)->where('mid',mid)->where('aid',aid)->update(['is_del'=>1,'updatetime'=>time()]);
        if($del){
            return $this->json(['status'=>1,'msg'=>'删除成功']);
        }else{
            return $this->json(['status'=>0,'msg'=>'删除失败']);
        }
    }

    //取消订单
    public function cancelOrder(){
        $type = input('post.type')?input('type/d'):0;
        if($type != 1 && $type != 2 && $type != 20){
            return $this->json(['status'=>0,'msg'=>'操作类型错误']);
        }

        $orderid = input('post.orderid')?input('orderid/d'):0;
        if(!$orderid){
            return $this->json(['status'=>0,'msg'=>'取消失败']);
        }

        $order = Db::name('paotui_order')->where('id',$orderid)->where('mid',mid)->where('aid',aid)->find();
        if(!$order){
            return $this->json(['status'=>0,'msg'=>'订单不存在']);
        }
        if($type == 1){
            if($order['status'] < 0 || $order['status']>$this->cancel_status){
                return $this->json(['status'=>0,'msg'=>'订单状态不符']);
            }
            if($order['status'] == 0){
                $up = Db::name('paotui_order')->where('id',$order['id'])->update(['status'=>-1,'updatetime'=>time()]);
                if($up){
                    return $this->json(['status'=>1,'msg'=>'取消成功']);
                }else{
                    return $this->json(['status'=>0,'msg'=>'取消失败']);
                }
            }else{
                if($order['status']>0 && $order['status']<=$this->cancel_status){
                    if($order['push_type'] == 3){
                        }else{
                        $ps_order = Db::name('peisong_order')->where('orderid',$order['id'])->where('type','paotui_order')->find();
                        if($ps_order){
                            if($ps_order['status']<10 && $ps_order['status']>=0){
                                $res = \app\models\PeisongOrder::quxiao($ps_order);
                                if($res['status']==0){
                                    return $this->json(['status'=>0,'msg'=>$res['msg']]);
                                }
                                return $this->json(['status'=>1,'msg'=>'取消成功']);
                            }else{
                                return $this->json(['status'=>1,'msg'=>'取消失败，配送端订单不存在或状态不符']);
                            }
                        }else{
                            //直接取消
                            $res = \app\customs\PaotuiCustom::cancelOrder2($order);
                            return $this->json($res);
                        }
                    }
                }else{
                    return $this->json(['status'=>0,'msg'=>'订单状态不符']);
                }
            }
        }else if($type == 2){
            if($order['status'] !=-2 ){
                return $this->json(['status'=>0,'msg'=>'退款失败，状态不符合']);
            }
            if($order['refund_status']==2){
                return $this->json(['status'=>0,'msg'=>'退款失败，已退款过']);
            }

            $data = [];
            $data['updatetime'] = time();

            if($order['refund_money']>0){
                //退款
                $rs = \app\commons\Order::refund($order,$order['refund_money'],'取消订单');
                if($rs['status']==0){

                    $data['status']             = -2;
                    $data['refund_status']      = -2;//-2：退款失败 1：退款成功
                    $data['refund_money']       = $order['refund_money'];
                    $data['cancel_fail_reason'] = $rs['msg']?$rs['msg']:'';
                    $up = Db::name('paotui_order')->where('id',$order['id'])->update($data);

                    return $this->json(['status'=>0,'msg'=>$rs['msg']]);
                }else{
                    $data['status']             = -1;
                    $data['refund_status']      = 2;//-2：退款失败 -1驳回退款 1: 申请退款 2：退款成功
                    $data['refund_money']       = $order['refund_money'];
                    $data['cancel_fail_reason'] = $rs['msg']?$rs['msg']:'';
                    $data['refund_time']        = time();
                }
                
            }else{
                $data['status'] = -1;
            }

            $up = Db::name('paotui_order')->where('id',$order['id'])->update($data);
            if($up){
                return $this->json(['status'=>1,'msg'=>'退款成功']);
            }else{
                return $this->json(['status'=>0,'msg'=>'退款失败']);
            }

        }else if($type == 20){
            if($order['status'] !=5){
                return $this->json(['status'=>0,'msg'=>'退款失败，状态不符合']);
            }
            if($order['refund_status']==2){
                return $this->json(['status'=>0,'msg'=>'退款失败，已退款过']);
            }
            if($order['refund_status']==0 && $order['refund_money']>0){
                return $this->json(['status'=>0,'msg'=>'等待审核中']);
            }
            if(!$this->end_refund_status){
                return $this->json(['status'=>0,'msg'=>'暂不支持退款']);
            }
            $data = [];
            $data['refund_status']= 1;
            $data['refund_money'] = $order['totalprice'];
            $data['updatetime']   = time();
            $up = Db::name('paotui_order')->where('id',$order['id'])->update($data);
            if($up){
                return $this->json(['status'=>1,'msg'=>'提交成功，等待审核']);
            }else{
                return $this->json(['status'=>0,'msg'=>'提交失败']);
            }
        }
    }
}