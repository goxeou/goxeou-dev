<?php


// custom_file(express_maiyatian)
// +----------------------------------------------------------------------
// | 麦芽田配送订单
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class MytPeisongOrder extends Common
{
    public function initialize(){
        parent::initialize();
        if(!getcustom('express_maiyatian')) showmsg('无访问权限');
    }
    //配送记录
    public function index(){
        if(request()->isAjax()){
            $page = input('param.page');
            $limit = input('param.limit');
            if(input('param.field') && input('param.order')){
                $order = input('param.field').' '.input('param.order');
            }else{
                $order = 'id desc';
            }
            $where = array();
            $where[] = ['aid','=',aid];
            if(bid > 0){
                $where[] = ['bid','=',bid];
            }

            if(input('param.psid')) $where[] = ['psid','=',input('param.psid')];
            if(input('param.ordernum')) $where[] = ['ordernum','=',input('param.ordernum')];
            if(input('?param.status') && input('param.status')!=='') $where[] = ['status','=',input('param.status')];

            //麦芽田
            $where[] = ['psid','=',-2];
            $count = 0 + Db::name('peisong_order')->where($where)->count();
            $data = Db::name('peisong_order')->where($where)->page($page,$limit)->order($order)->select()->toArray();
            foreach($data as $k=>&$v){
                $v['member'] = Db::name('member')->where('id',$v['mid'])->field('headimg,nickname')->find();

                $v['psfee']  = $v['psfee'].'元';
                $v['amount']  = '';
                $psuser = ['realname'=>'','tel'=>'','latitude'=>'','longitude'=>''];

                $v['logistic']    = '';
                $v['logistic_no'] = '';
                $v['weight']      = '';
                $v['remark']      = '';
                $v['cancel_amount'] = '';
                $v['cancel_reason'] = '';

                $v['reject_code'] = '';//拒绝码
                $v['reject_msg']  = '';//拒绝原因
                //查询关联表
                $myt = Db::name('peisong_order_myt')->where('poid',$v['id'])->where('aid',aid)->find();
                if($myt){
                    $v['amount']  = $myt['amount'].'元';
                    $psuser = [];
                    $psuser['realname'] = $myt['rider_name'];
                    $psuser['tel']      = $myt['rider_phone'];
                    $psuser['latitude'] = $myt['rider_latitude'];
                    $psuser['longitude']= $myt['rider_longitude'];

                    $v['logistic']    = $myt['logistic'];
                    $v['logistic_no'] = $myt['logistic_no'];
                    $v['weight']      = $myt['weight'];
                    $v['remark']      = $myt['remark'];
                    $v['cancel_amount'] = $myt['cancel_amount'];
                    $v['cancel_reason'] = $myt['cancel_reason'];
                    $v['reject_code'] = $myt['reject_code'];//拒绝码
                    $v['reject_msg']  = $myt['reject_msg'];//拒绝原因
                }

                $v['psuser'] = $psuser;
                
                $v['orderinfo'] = json_decode($v['orderinfo'],true);
                $v['binfo'] = json_decode($v['binfo'],true);
                if($v['type'] == 'paotui_order'){
                    $v['binfo']['name'] = '（跑腿）'.$v['binfo']['name'];
                }
                $v['prolist'] = json_decode($v['prolist'],true);

                $goodsdata=array();
                foreach($v['prolist'] as $og){
                    if($v['type'] == 'paotui_order'){
                        $goodsdata[] = '<div style="font-size:12px;float:left;clear:both;margin:1px 0">'.
                            '<img src="'.$og['pic'].'" style="max-width:60px;float:left">'.
                            '<div style="float: left;width:160px;margin-left: 10px;white-space:normal;line-height:16px;">'.
                                '<div style="width:100%;min-height:25px;max-height:32px;overflow:hidden">'.$og['name'].'</div>'.
                                '<div style="padding-top:0px;color:#f60"><span style="color:#888">'.$og['ggname'].'</span></div>'.
                                '<div style="padding-top:0px;color:#f60;"> × '.$og['num'].'</div>'.
                            '</div>'.
                        '</div>';
                    }else{
                        $goodsdata[] = '<div style="font-size:12px;float:left;clear:both;margin:1px 0">'.
                            '<img src="'.$og['pic'].'" style="max-width:60px;float:left">'.
                            '<div style="float: left;width:160px;margin-left: 10px;white-space:normal;line-height:16px;">'.
                                '<div style="width:100%;min-height:25px;max-height:32px;overflow:hidden">'.$og['name'].'</div>'.
                                '<div style="padding-top:0px;color:#f60"><span style="color:#888">'.$og['ggname'].'</span></div>'.
                                '<div style="padding-top:0px;color:#f60;">￥'.$og['sell_price'].' × '.$og['num'].'</div>'.
                            '</div>'.
                        '</div>';
                    }
                    
                }
                $v['goodsdata'] = implode('',$goodsdata);
                $v['pstype'] = '麦芽田配送';
                if($v['tip_fee']>0){
                    $v['ticheng'] = $v['ticheng'].'+'.$v['tip_fee'].'元小费';
                }else{
                    $v['ticheng'] .= '元';
                }
            }
            unset($v);
            return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
        }
        $psusers = Db::name('peisong_user')->where('aid',aid)->order('sort desc,id')->select()->toArray();
        View::assign('psusers',$psusers);
        return View::fetch();
    }
    //配送记录导出
    public function excel(){
        if(input('param.field') && input('param.order')){
            $order = input('param.field').' '.input('param.order');
        }else{
            $order = 'id desc';
        }
        $page = input('param.page');
        $limit = input('param.limit');
        $where = array();
        $where[] = ['aid','=',aid];
        if(bid > 0){
            $where[] = ['bid','=',bid];
        }
        if(input('param.psid')) $where[] = ['psid','=',input('param.psid')];
        if(input('param.ordernum')) $where[] = ['ordernum','=',input('param.ordernum')];
        if(input('?param.status') && input('param.status')!=='') $where[] = ['status','=',input('param.status')];
        
        $where[] = ['psid','=',-2];
        $list = Db::name('peisong_order')->where($where)->order($order)->page($page,$limit)->select()->toArray();
        $count = Db::name('peisong_order')->where($where)->order($order)->count();
        
        $title = array('麦芽田订单号','系统订单号','配送员','下单人','所属商家','商品信息','总价','实付款','收货地址','支付方式','配送状态','配送类型','配送提成','配送费','实际配送费','违约金','其他信息','创建时间');
        $data = [];
        foreach($list as $k=>$vo){
            $member = Db::name('member')->where('id',$vo['mid'])->field('headimg,nickname')->find();

            $amount = '';
            //查询关联表
            $myt = Db::name('peisong_order_myt')->where('poid',$vo['id'])->where('aid',aid)->find();
            if($myt){
                $amount = $myt['amount'].'元';
                $psuser = [];
                $psuser['realname'] = $myt['rider_name'];
                $psuser['tel']      = $myt['rider_phone'];
                $psuser['latitude'] = $myt['rider_latitude'];
                $psuser['longitude']= $myt['rider_longitude'];
            }

            $orderinfo = json_decode($vo['orderinfo'],true);
            $binfo = json_decode($vo['binfo'],true);
            $prolist = json_decode($vo['prolist'],true);
            $xm=array();
            foreach($prolist as $gg){
                $xm[] = $gg['name']."/".$gg['ggname']." × ".$gg['num']."";
            }
            $status='';
            if($vo['status']==0){
                $status = '待接单';
            }elseif($vo['status']==1){
                $status = '已接单';
            }elseif($vo['status']==2){
                $status = '已到店';
            }elseif($vo['status']==3){
                $status = '已取货';
            }elseif($vo['status']==4){
                $status = '已完成';
            }elseif($vo['status']==10){
                $status = '已取消';
            }
            $psstatus = '';
            if($vo['status']==0){
                $psstatus = '待配送';
            }else if($vo['status']==1){
                $psstatus = '配送中';
            }else if($vo['status']==2){
                $psstatus = '已完成';
            }

            $pstype = '麦芽田配送';

            if($vo['type'] == 'paotui_order'){
                $b_name = '（跑腿）'.$binfo['name'];
            }else{
                $b_name = $binfo['name'];
            }

            $ticheng = $vo['ticheng'];
            if($vo['tip_fee']>0){
                $ticheng = $vo['ticheng'].'+'.$vo['tip_fee'].'元小费';
            }
            
            $other = '';
            if($myt){
                $logistic = '无';//配送方
                if($myt['logistic']){
                    if(d.logistic == 'mtps'){
                       $logistic = '美团配送';
                    }else if($myt['logistic'] == 'fengka'){
                       $logistic = '蜂鸟配送';
                    }else if($myt['logistic'] == 'dada'){
                       $logistic = '达达';
                    }else if($myt['logistic'] == 'shunfeng'){
                       $logistic = '顺丰';
                    }else if($myt['logistic'] == 'bingex'){
                       $logistic = '闪送';
                    }else if($myt['logistic'] == 'uupt'){
                       $logistic = 'UU跑腿';
                    }else if($myt['logistic'] == 'ipaotui'){
                       $logistic = '爱跑腿';
                    }else if($myt['logistic'] == 'fuwu'){
                       $logistic = '快服务';
                    }else if($myt['logistic'] == 'guoxiaodi'){
                       $logistic = '裹小递';
                    }else if($myt['logistic'] == 'caosong'){
                       $logistic = '曹操送';
                    }else if($myt['logistic'] == 'caosong'){
                       $logistic = $myt['logistic'] ;
                    }
                }
                if($myt['logistic']){
                    $other .= "配送方：".$logistic." \n\r ";
                }
                if($myt['logistic_no']){
                    $other .= "配送单号：".$myt['logistic_no']." \n\r ";
                }
                if($myt['weight']){
                    $other .= "重量：".$myt['weight']."kg \n\r";
                }
                if($myt['remark']){
                    $other .= "备注：".$myt['remark']." \n\r ";
                }
                if($myt['cancel_reason']){
                    $other .= "取消原因：".$myt['cancel_reason']." \n\r ";
                }
                if($myt['reject_code']){
                  $other .= "拒绝码：".$myt['reject_code']." \n\r ";
                }
                if($myt['reject_msg']){
                  $other .= "拒绝原因：".$myt['reject_msg']." \n\r ";
                }
            }
            $data[] = [
                $vo['myt_order_id'],
                ' '.$orderinfo['ordernum'],
                $psuser['realname'].' '.$psuser['tel'],
                $member['nickname'],
                $b_name,
                implode("\r\n",$xm),
                $orderinfo['product_price'],
                $orderinfo['totalprice'],
                $orderinfo['linkman'].'('.$orderinfo['tel'].') '.$orderinfo['area'].' '.$orderinfo['address'],
                $orderinfo['paytype'],
                $status,
                $pstype,
                $ticheng.'元',
                !empty($orderinfo['freight_price'])?$orderinfo['freight_price'].'元':'',
                $amount,
                $myt && $myt['cancel_amount']?$myt['cancel_amount']:0,
                $other,
                !empty($orderinfo['createtime'])?date("Y-m-d H:i:s",$orderinfo['createtime']):'',
            ]; 
        }
        return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data,'title'=>$title]);
        $this->export_excel($title,$data);
    }
    //删除
    public function del(){
        $ids = input('post.ids/a');
        $where = [];
        $where[] = ['aid','=',aid];
        if(bid > 0){
            $where[] = ['bid','=',bid];
        }
        $where[] = ['id','in',$ids];
        Db::name('peisong_order')->where($where)->delete();
        \app\commons\System::plog('删除麦芽田配送记录'.implode(',',$ids));
        return json(['status'=>1,'msg'=>'删除成功']);
    }
    //改状态
    public function setst(){
        $ids = input('post.ids/a');
        $st = input('post.st/d');
        $where = [];
        $where[] = ['aid','=',aid];
        if(bid > 0){
            $where[] = ['bid','=',bid];
        }
        $where[] = ['id','in',$ids];
        $psorderlist = Db::name('peisong_order')->where($where)->select()->toArray();
        foreach($psorderlist as $k=>$v){
            if($st == 10){ //取消
                \app\models\PeisongOrder::quxiao($v);
            }else{
                Db::name('peisong_order')->where('aid',aid)->where('id',$v['id'])->update(['status'=>$st]);
            }
        }
        \app\commons\System::plog('取消麦芽田配送订单'.implode(',',$ids));
        return json(['status'=>1,'msg'=>'取消成功']);
    }
}