<?php


//custom_file(paotui)
// +----------------------------------------------------------------------
// | 跑腿订单
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class PaotuiOrder extends Common
{
    public function initialize(){
        parent::initialize();
        if(bid > 0) showmsg('无访问权限');
    }
    //跑腿记录
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

            if(input('param.keyword')){
                $keyword = input('param.keyword');
                $keyword_type = input('param.keyword_type');
                if($keyword_type == 1){ //订单号
                    $where[] = ['ordernum','like','%'.$keyword.'%'];
                }elseif($keyword_type == 2){ //会员ID
                    $where[] = ['mid','=',$keyword];
                }elseif($keyword_type == 3){ //会员昵称
                    $mids = Db::name('member')->where('aid',aid)->where('nickname','like','%'.$keyword.'%')->column('id');
                    if($mids){
                        $where[] = ['mid','in',$mids];
                    }else{
                        $where[] = ['mid','=',0];
                    }
                    
                }elseif($keyword_type == 4){ //会员手机号
                    $mids = Db::name('member')->where('aid',aid)->where('tel','like','%'.$keyword.'%')->column('id');
                    if($mids){
                        $where[] = ['mid','in',$mids];
                    }else{
                        $where[] = ['mid','=',0];
                    }
                }
            }
            
            if(input('?param.btntype') && input('param.btntype')!==''){
                $btntype = input('btntype/d');
                $where[] = ['btntype','=',$btntype];
            }
            if(input('?param.push_type') && input('param.push_type')!==''){
                $push_type = input('push_type/d');
                $where[] = ['push_type','=',$push_type];
            }
            if(input('?param.status') && input('param.status')!=='') $where[] = ['status','=',input('param.status')];

            $where[] = ['is_del','=',0];

            $count = 0 + Db::name('paotui_order')->where($where)->count();
            $data = Db::name('paotui_order')->where($where)->page($page,$limit)->order($order)->select()->toArray();

            foreach($data as $k=>&$v){
                $v['member'] = Db::name('member')->where('id',$v['mid'])->field('headimg,nickname')->find();

                if($v['btntype'] == 1){
                    $v['btntype'] = '帮我送';
                }else if($v['btntype'] == 2){
                    $v['btntype'] = '帮我取';
                }else{
                    $v['btntype'] = '';
                }

                if($v['take_time']){
                    $v['take_time'] = date("Y-m-d H:i",$v['take_time']);
                }else{
                    $v['take_time'] = '立即取货';
                }

                if($v['push_type'] == 1){
                    $v['push_type'] = '系统配送';
                }else if($v['push_type'] == 2){
                    $v['push_type'] = t('码科').'配送';
                }else if($v['push_type'] == 3){
                    $v['push_type'] = '即时配送';
                }else{
                    $v['push_type'] = '无';
                }

                if($v['createtime']){
                    $v['createtime'] = date("Y-m-d H:i:s",$v['createtime']);
                }
            }
            unset($v);
            return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
        }

        return View::fetch();
    }

    //跑腿记录导出
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

        if(input('param.keyword')){
            $keyword = input('param.keyword');
            $keyword_type = input('param.keyword_type');
            if($keyword_type == 1){ //订单号
                $where[] = ['ordernum','like','%'.$keyword.'%'];
            }elseif($keyword_type == 2){ //会员ID
                $where[] = ['mid','=',$keyword];
            }elseif($keyword_type == 3){ //会员昵称
                $mids = Db::name('member')->where('aid',aid)->where('nickname','like','%'.$keyword.'%')->column('id');
                if($mids){
                    $where[] = ['mid','in',$mids];
                }else{
                    $where[] = ['mid','=',0];
                }
                
            }elseif($keyword_type == 4){ //会员手机号
                $mids = Db::name('member')->where('aid',aid)->where('tel','like','%'.$keyword.'%')->column('id');
                if($mids){
                    $where[] = ['mid','in',$mids];
                }else{
                    $where[] = ['mid','=',0];
                }
            }
        }
        if(input('?param.btntype') && input('param.btntype')!==''){
            $btntype = input('btntype/d');
            $where[] = ['btntype','=',$btntype];
        }
        if(input('?param.push_type') && input('param.push_type')!==''){
            $push_type = input('push_type/d');
            $where[] = ['push_type','=',$push_type];
        }
        if(input('?param.status') && input('param.status')!=='') $where[] = ['status','=',input('param.status')];

        $where[] = ['is_del','=',0];

        $list = Db::name('paotui_order')->where($where)->order($order)->page($page,$limit)->select()->toArray();
        $count = Db::name('paotui_order')->where($where)->order($order)->count();
        $title = array('订单号','下单人','类型','物品名称','取件地址','收货地址','配送距离','取货时间','重量','付费详情','实付','支付方式','配送方式','状态','下单时间');
        $data = [];
        foreach($list as $k=>$vo){
            $member = Db::name('member')->where('id',$vo['mid'])->field('headimg,nickname')->find();

            $xm=array();
            foreach($prolist as $gg){
                $xm[] = $gg['name']."/".$gg['ggname']." × ".$gg['num']."";
            }
            $status='';
            if($vo['status']==0){
                $status = '待付款';
            }else if($vo['status']==1){
                $status = '待接单';
            }else if($vo['status']==2){
                $status = '已接单';
            }else if($vo['status']==3){
                $status = '已到店';
            }else if($vo['status']==4){
                $status = '已取货';
            }else if($vo['status']==5){
                $status = '已完成';
            }else if($vo['status']==-1){
                $status = '已取消';
            }

            if($vo['btntype'] == 1){
                $btntype = '帮我送';
            }else if($vo['btntype'] == 2){
                $btntype = '帮我取';
            }else{
                $btntype = '';
            }

             if($vo['push_type'] == 1){
                $push_type = '系统配送';
            }else if($vo['push_type'] == 2){
                $push_type = t('码科').'配送';
            }else if($vo['push_type'] == 3){
                $push_type = '即时配送';
            }else{
                $push_type = '无';
            }

            if($vo['take_time']){
                $take_time = date("Y-m-d H:i",$vo['take_time']);
            }else{
                $take_time = '立即取货';
            }

            if($vo['createtime']){
                $createtime = date("Y-m-d H:i:s",$vo['createtime']);
            }else{
                $createtime = '';
            }

            $fee_detail = '';
            if($vo['distance_fee']>0){
                $fee_detail .= '距离费用:￥'.$vo['distance_fee']." \r\n ";
            }
            if($vo['weight_fee']>0){
                $fee_detail .= '重量费用:￥'.$vo['weight_fee']." \r\n ";
            }
            if($vo['tip_fee']>0){
                $fee_detail .= '小费:￥'.$vo['tip_fee']." \r\n ";
            }

            $take_address =  $vo['take_name'].'('.$vo['take_tel'].') '." \r\n ".$vo['take_area'].' '.$vo['take_address'];
            $send_address =  $vo['send_name'].'('.$vo['send_tel'].') '." \r\n ".$vo['send_area'].' '.$vo['send_address'];

            $data[] = [
                ' '.$vo['ordernum'],
                'ID:'.$member['id']." \r\n ".$member['nickname'],
                $btntype,
                $vo['name'],
                $take_address,
                $send_address,
                $vo['distance'].'公里',
                $take_time,
                $vo['weight'],
                $fee_detail,
                $vo['totalprice'],
                $vo['paytype'],
                $push_type,
                $status,
                $createtime
            ]; 
        }
        return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data,'title'=>$title]);
        $this->export_excel($title,$data);
    }

    //删除
    public function del(){
        $id  = input('post.id/d');
        $psorder = Db::name('paotui_order')->where('id',$id)->where('aid',aid)->find();
        if($psorder['status'] != -2  &&  $psorder['status']!=5){
            return json(['status'=>0,'msg'=>'删除失败，状态不符合']);
        }
        $del = Db::name('paotui_order')->where('aid',aid)->where('id','in',$ids)->update(['is_del'=>1,'updatetime'=>time()]);
        if($del){
            \app\commons\System::plog('删除跑腿记录'.implode(',',$ids));
            return json(['status'=>1,'msg'=>'删除成功']);
        }else{
            return json(['status'=>0,'msg'=>'删除失败']);
        }
        
    }

    //改状态
    public function cancel(){
        $id  = input('post.id/d');
        $st  = input('post.st/d');
        $psorder = Db::name('paotui_order')->where('id',$id)->where('aid',aid)->find();

        if($st == 1){
            if($psorder['status']<0 || $psorder['status']>5){
                return json(['status'=>0,'msg'=>'取消失败，状态不符合']);
            }
            if($psorder['status'] == 0){
                $up = Db::name('paotui_order')->where('id',$psorder['id'])->update(['status'=>-1,'updatetime'=>time()]);
                array_push($cancel_id,$psorder['id']);
            }else{
                if($psorder['status']>0 && $psorder['status']<5){
                    if($psorder['push_type'] ==3){
                        }else{
                        $ps_order = Db::name('peisong_order')->where('orderid',$psorder['id'])->where('type','paotui_order')->find();
                        if($ps_order){
                            if($ps_order['status']<10 && $ps_order['status']>=0){
                                $res = \app\models\PeisongOrder::quxiao($ps_order);
                                if($res['status']==0){
                                    return json(['status'=>0,'msg'=>$res['msg']]);
                                }
                            }else{
                                return json(['status'=>0,'msg'=>'取消失败，配送端订单不存在或状态不符']);
                            }
                        }else{
                            //直接取消
                            $res = \app\customs\PaotuiCustom::cancelOrder2($psorder);
                            return json($res);
                        }
                    }
                }
            }
            \app\commons\System::plog('取消跑腿订单'.$id);
            return json(['status'=>1,'msg'=>'取消成功']);
        }else if($st == 2){

            if($psorder['status'] !=-2  && $psorder['status'] !=5){
                return json(['status'=>0,'msg'=>'退款失败，状态不符合']);
            }
            if($psorder['refund_status']==2){
                return json(['status'=>0,'msg'=>'退款失败，已退款过']);
            }

            $data = [];

            if($psorder['status'] == -2){
                $data['updatetime'] = time();
                $data['status'] = -1;
                if($psorder['refund_money']>0){
                    //退款
                    $rs = \app\commons\Order::refund($psorder,$psorder['refund_money'],'订单退款');
                    if($rs['status']==0){
                        $data['refund_status']      = -2;//-2：退款失败 -1驳回退款 1: 申请退款 2：退款成功
                        $data['cancel_fail_reason'] = $rs['msg']?$rs['msg']:'';
                        $up = Db::name('paotui_order')->where('id',$psorder['id'])->update($data);
                        return json(['status'=>0,'msg'=>'退款失败，'.$rs['msg']]);
                    }else{
                        $data['status']         = -1;
                        $data['refund_status']  = 2;//-2：退款失败 -1驳回退款 1: 申请退款 2：退款成功
                        $data['refund_time']    = time();
                        $up = Db::name('paotui_order')->where('id',$psorder['id'])->update($data);
                        return json(['status'=>1,'msg'=>'退款成功，']);
                    }
                }else{
                    return json(['status'=>0,'msg'=>'退款失败，可退金额不足']);
                }
                $up = Db::name('paotui_order')->where('id',$psorder['id'])->update($data);
            }else{
                if($psorder['refund_status']==2){
                    return json(['status'=>0,'msg'=>'退款失败，已退款过']);
                }
                // if($psorder['refund_status']!=1){
                //     return json(['status'=>0,'msg'=>'退款失败，状态不符合']);
                // }
                if($psorder['totalprice']>0){

                    //退款
                    $rs = \app\commons\Order::refund($psorder,$psorder['totalprice'],'订单退款');
                    if($rs['status']==0){

                        //$data['status']             = -2;
                        $data['refund_status']      = -2;//-2：退款失败 -1驳回退款 1: 申请退款 2：退款成功
                        $data['refund_money']       = $psorder['totalprice'];
                        $data['cancel_fail_reason'] = $rs['msg']?$rs['msg']:'';
                        $up = Db::name('paotui_order')->where('id',$psorder['id'])->update($data);

                        return json(['status'=>0,'msg'=>$rs['msg']]);
                    }else{
                        $data['refund_status'] = 2;//-2：退款失败 -1驳回退款 1: 申请退款 2：退款成功
                        $data['refund_money']  = $psorder['totalprice'];
                        $data['refund_time']   = time();
                        $up = Db::name('paotui_order')->where('id',$psorder['id'])->update($data);
                    }
                    
                }else{
                    return json(['status'=>0,'msg'=>'退款失败，可退金额不足']);
                }
            }
            \app\commons\System::plog('跑腿订单退款'.$id);
            return json(['status'=>1,'msg'=>'退款成功']);
        }else if($st == -21){
            if($psorder['refund_status']==2){
                return json(['status'=>0,'msg'=>'退款失败，已退款过']);
            }
            if($psorder['refund_status']!=1){
                return json(['status'=>0,'msg'=>'驳回失败，状态不符合']);
            }
            $data = [];
            $data['refund_status'] = -1;//-2：退款失败 -1驳回退款 1: 申请退款 2：退款成功
            $up = Db::name('paotui_order')->where('id',$psorder['id'])->update($data);
            \app\commons\System::plog('驳回跑腿订单退款'.$id);
            return json(['status'=>1,'msg'=>'驳回成功']);
        }else{
            return json(['status'=>0,'msg'=>'操作类型错误']);
        }
        
    }
    //订单详情
    public function getdetail(){
        $orderid = input('param.orderid');
        if(bid != 0){
            $order = Db::name('paotui_order')->where('id',$orderid)->where('aid',aid)->find();
        }else{
            $order = Db::name('paotui_order')->where('id',$orderid)->where('aid',aid)->find();
        }

        $paidantype     = false;
        $paotui_set =  Db::name('paotui_set')->where('aid',aid)->find();
        if($paotui_set){
            //配送端设置
            $peisong_set = Db::name('peisong_set')->where('aid',aid)->find();
            if($peisong_set){
                if($paotui_set['type'] == 0){
                    if($peisong_set['paidantype'] == 1){
                        $paidantype  = true;
                    }
                }else if($paotui_set['type'] == 1){
                    $paidantype     = false;
                }else if($paotui_set['type'] == 2){
                    }
            }
        }

        return json(['order'=>$order,'paidantype'=>$paidantype]);
    }
    public static function peisong(){
        $orderid = input('param.orderid')?input('param.orderid/d'):0;
        $psid    = input('param.psid')?input('param.psid/d'):0;
        //处理推送到哪个端
        $res = \app\customs\PaotuiCustom::deal_push($orderid,$psid,1);
        if($res['status']!=1){
            return json(['status'=>0,'msg'=>$res['msg']]);
        }else{
            $updata = [];
            $updata['status']     = 2;
            $updata['is_assign']  = 2;
            $updata['starttime'] = time();
            $updata['updatetime'] = time();
            Db::name('paotui_order')->where('id',$orderid)->update($updata);
            \app\commons\System::plog('推送跑腿订单'.$orderid);
            return json(['status'=>1,'msg'=>'推送成功']);
        }
    }
}