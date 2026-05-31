<?php


namespace app\controllers;
use app\BaseController;
use app\commons\Yilianyun;
use think\facade\Db;

class ApiYilianyunNotify extends BaseController
{
    public function notify(){
        $message = 'ok';
        $postdata = $_POST;
        $client_id = input('param.client_id');
        $wifiprint = Db::name('wifiprint_set')->where('client_id',$client_id)->whereNotNull('rsa_publickey')->whereNotNull('aes_publickey')->find();
        $ras_publickey=  $wifiprint['rsa_publickey'];
        $aes_publickey=  $wifiprint['aes_publickey'];
        if(!$ras_publickey || !$aes_publickey)  $message = '配置平台公钥';
        $isVerify = Yilianyun::verifySignature($postdata['ciphertext'],$ras_publickey, base64_decode($postdata['signature']));
        if (!$isVerify) $message = '验签失败';
        $params = Yilianyun::decode(base64_decode($postdata['ciphertext']), $aes_publickey, $postdata['iv'], base64_decode($postdata['tag']));
        if (!$params)$message = '解密失败';
        $params_decode = json_decode($params,true);

        //原始数据
        $orderdata = $params_decode['order_other'];
        //扫码内容,目前支持,其他定制 https:///h5/32.html#/admin/hexiao/hexiao?type=shop&co=K47H54t2203F9VVC
        $scanned_code  = $orderdata['scanned_code'];
        $qqData = Yilianyun::getPathParams($scanned_code);
        $code =   $qqData['co'];
        
      
        
        $this->hexiao($qqData['type'],$code,$client_id);
        
        return json(Yilianyun::result($message));
    }
    public function hexiao($type,$code,$client_id){
        $remark = '扫码核销';
        $title = '';
        if($type =='coupon'){
            $order = Db::name('coupon_record')->where('code', $code)->find();
            $aid = $order['aid'];
            //判断 订单是否在打印机所属平台商户中
            if(!self::checkAidBid($client_id,$aid,$order['bid'])){
                return json(['status'=>0,'msg'=>'未查询到打印机']);
            }
            
            $title = $order['couponname'];
            if(!$order) return json(['status'=>0,'msg'=>t('优惠券').'不存在']);
            if($order['status']==1) return json(['status'=>0,'msg'=>t('优惠券').'已使用']);
            if($order['starttime'] > time()) return json(['status'=>0,'msg'=>t('优惠券').'尚未生效']);
            if($order['endtime'] < time()) return json(['status'=>0,'msg'=>t('优惠券').'已过期']);
            if($order['type']==3 && $order['used_count']>=$order['limit_count']) return  json(['status'=>0,'msg'=>'已达到使用次数']);
            if($order['type']==3 && $order['limit_perday'] > 0){ //是否达到每天使用次数限制
                $dayhxnum = Db::name('hexiao_order')->where('orderid',$order['id'])->where('type','coupon')->where('createtime','between',[strtotime(date('Y-m-d 00:00:00')),strtotime(date('Y-m-d 23:59:59'))])->count();
                if($dayhxnum >= $order['limit_perday']){
                    return  json(['status'=>0,'msg'=>'该计次券每天最多核销'.$order['limit_perday'].'次']);
                }
            }
            if($order['type']==3){//计次券
                $hxnum = 1;
                Db::name($type.'_record')->where(['aid'=>$aid,'code'=>$code])->inc('used_count',$hxnum)->update();
                if($order['used_count']+$hxnum>=$order['limit_count']){
                    Db::name($type.'_record')->where(['aid'=>$aid,'code'=>$code])->update(['status'=>1,'usetime'=>time(),'remark'=>$remark]);
                }
            }else{
                Db::name($type.'_record')->where(['aid'=>$aid,'code'=>$code])->update(['status'=>1,'usetime'=>time(),'remark'=>$remark]);
                $record = Db::name($type.'_record')->where(['aid'=>$aid,'code'=>$code])->find();
                \app\commons\Coupon::useCoupon($aid,$record['id'],'hexiao');
            }
             \app\commons\Wifiprint::couponPrint($aid,$order['bid'],$order['id'],0); 
        }elseif ($type =='shop'){
            $order = db($type.'_order')->where(['hexiao_code'=>$code])->find();
            $title = $order['title'];
            $aid = $order['aid'];
            //判断 订单是否在打印机所属平台和商户中
            if(!self::checkAidBid($client_id,$aid,$order['bid'])){
                return json(['status'=>0,'msg'=>'未查询到打印机']);
            }
            if(!$order) return json(['status'=>0,'msg'=>'订单不存在']);
            if($order['status']==0) return json(['status'=>0,'msg'=>'订单未支付']);
            if($order['status']==3) return json(['status'=>0,'msg'=>'订单已核销']);
            if($order['status']==4) return json(['status'=>0,'msg'=>'订单已关闭']);
            db($type.'_order')->where(['aid'=>$aid,'hexiao_code'=>$code])->update(['status'=>3,'collect_time'=>time(),'remark'=>$remark]);
            Db::name($type.'_order_goods')->where(['aid'=>$aid,'orderid'=>$order['id']])->update(['status'=>3,'endtime'=>time()]);
            \app\commons\Member::uplv($aid,$order['mid']);
            $rs = \app\commons\Wifiprint::print($aid,'shop',$order['id'],0);
        }
                
        $data = array();
        $data['aid'] = $order['aid'];
        $data['bid'] = $order['bid'];
        $data['uid'] = 0;
        $data['mid'] = $order['mid'];
        $data['orderid'] = $order['id'];
        $data['ordernum'] = date('ymdHis').$aid.rand(1000,9999);
        $data['title'] = $title;
        $data['type'] = $type;
        $data['createtime'] = time();
        $data['remark'] = $remark;
        $data['mdid']   = 0;
        Db::name('hexiao_order')->insert($data);
    }
    
    //检测核销订单的aid,bid是否有配置 该应用
    public static function checkAidBid($client_id,$aid,$bid){
        $wifiprint_list =Db::name('wifiprint_set')->where('client_id',$client_id)->field('aid,bid')->where('status',1)->select()->toArray();
        $is_have = 0;
        foreach($wifiprint_list as $key=>$val){
            if($val['aid'] == $aid && $val['bid'] == $bid){
                $is_have = 1;
            }
        }
         return $is_have;
    }
}