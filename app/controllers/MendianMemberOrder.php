<?php


// +----------------------------------------------------------------------
// | mend取货订单
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class MendianMemberOrder extends Common
{
    public function initialize(){
        parent::initialize();
        if(bid > 0) showmsg('无访问权限');
    }
    //取货记录
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
            $where[] = ['psid','>',0];
            $where[] = ['aid','=',aid];
            if($this->mdid){
                $where[] = ['mdid','=',$this->mdid];
            }else{
                if(input('param.mdid')){
                    $where[] = ['mdid','=',input('param.mdid')];
                }
            }
            $where[] = ['delete','=',0];
            if(input('param.psid')) $where[] = ['psid','=',input('param.psid')];
            if(input('param.ordernum')) $where[] = ['ordernum','=',input('param.ordernum')];
            if(input('?param.status') && input('param.status')!=='' && input('param.status')>=1){
                 $where[] = ['status','=',input('param.status')];
            }else{
                $where[] = ['status','>=',1];
            }

            $count = 0 + Db::name('xixie_order')->where($where)->count();
            $data = Db::name('xixie_order')->where($where)->page($page,$limit)->order($order)->select()->toArray();
            foreach($data as &$v){
                $v['member'] = Db::name('member')->where('id',$v['mid'])->field('headimg,nickname')->find();
                $v['psuser'] = Db::name('mendian_member')->where('id',$v['psid'])->field('realname,tel')->find();

                $prolist   = Db::name('xixie_order_goods')->where('orderid',$v['id'])->select()->toArray();

                $goodsdata=array();
                foreach($prolist as $og){
                    $goodsdata[] = '<div style="font-size:12px;float:left;clear:both;margin:1px 0">'.
                        '<img src="'.$og['pic'].'" style="max-width:60px;float:left">'.
                        '<div style="float: left;width:160px;margin-left: 10px;white-space:normal;line-height:16px;">'.
                            '<div style="width:100%;min-height:25px;max-height:32px;overflow:hidden">'.$og['name'].'</div>'.
                            '<div style="padding-top:0px;color:#f60"><span style="color:#888">'.$og['ggname'].'</span></div>'.
                            '<div style="padding-top:0px;color:#f60;">￥'.$og['sell_price'].' × '.$og['num'].'</div>'.
                        '</div>'.
                    '</div>';
                }
                $v['goodsdata'] = implode('',$goodsdata);
                $v['deal_time'] = '';
                $v['deal_time'] .= $v['qh_time']?'取货完成时间：<br>'.date('Y-m-d H:i:s',$v['qh_time'])."<br>":'';
                $v['deal_time'] .= $v['rk_time']?'入库完成时间：<br>'.date('Y-m-d H:i:s',$v['rk_time'])."<br>":'';
                $v['deal_time'] .= $v['qx_time']?'清洗完成时间：<br>'.date('Y-m-d H:i:s',$v['qx_time'])."<br>":'';
                $v['deal_time'] .= $v['end_time']?'订单完成时间：<br>'.date('Y-m-d H:i:s',$v['end_time'])."<br>":'';

                //图片
                if($v['qh_pics']){
                    $pics_arr = explode(',',$v['qh_pics']);
                    $pics_html = '';
                    foreach($pics_arr as $pa_v){
                        $pics_html .= "<a href='".$pa_v."' target='_blank' ><img src='".$pa_v."'  style='width:80px;height:auto;margin-right:5px'></a>";
                    }
                    $v['qh_pics'] = $pics_html;
                }
            }
            return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
        }
        unset($v);
        $where = [];
        if($this->mdid){
            $where[] = ['mdid','=',$this->mdid];
        }
        $where[] = ['aid','=',aid];
        $psusers = Db::name('mendian_member')->where($where)->order('id desc')->select()->toArray();
        View::assign('psusers',$psusers);

        View::assign('mdid',$this->mdid?$this->mdid:0);
        return View::fetch();
    }
    //取货记录导出
    public function excel(){
        if(input('param.field') && input('param.order')){
            $order = input('param.field').' '.input('param.order');
        }else{
            $order = 'id desc';
        }
        $where = array();
        $where[] = ['aid','=',aid];
        if($this->mdid){
            $where[] = ['mdid','=',$this->mdid];
        }else{
            if(input('param.mdid')){
                $where[] = ['mdid','=',input('param.mdid')];
            }
        }
        
        $where[] = ['delete','=',0];

        if(input('param.psid')) $where[] = ['psid','=',input('param.psid')];
        if(input('param.ordernum')) $where[] = ['ordernum','=',input('param.ordernum')];

        if(input('?param.status') && input('param.status')!=='' && input('param.status')>=1){
             $where[] = ['status','=',input('param.status')];
        }else{
            $where[] = ['status','>=',1];
        }
        $list = Db::name('xixie_order')->where($where)->order($order)->select()->toArray();
        $title = array('订单号','取货员','下单人','商品信息','总价','实付款','地址','取货状态','取货完成时间','入库完成时间','清洗完成时间','订单完成时间');
        $data = [];
        foreach($list as $k=>$vo){

            $member = Db::name('member')->where('id',$vo['mid'])->field('headimg,nickname')->find();
            $psuser = Db::name('mendian_member')->where('id',$vo['psid'])->field('realname,tel')->find();

            $prolist = Db::name('xixie_order_goods')->where('orderid',$vo['id'])->select()->toArray();

            $xm=array();
            foreach($prolist as $gg){
                $xm[] = $gg['name']."/"." × ".$gg['num']."";
            }

            $status='';
            if($vo['status']==1){
                $status = '待取货';
            }elseif($vo['status']==2){
                $status = '入库中';
            }elseif($vo['status']==3){
                $status = '清洗中';
            }elseif($vo['status']==4){
                $status = '送货中';
            }elseif($vo['status']==5){
                $status = '已完成';
            }

            $data[] = [
                ' '.$vo['ordernum'],
                $psuser['realname'].' '.$psuser['tel'],
                $member['nickname'],
                implode("\r\n",$xm),
                $vo['product_price'],
                $vo['totalprice'],
                $vo['linkman'].'('.$vo['tel'].') '.$vo['area'].' '.$vo['address'],
                $status,
                $vo['qh_time']?date('Y-m-d H:i:s',$vo['qh_time']):'',
                $vo['rk_time']?date('Y-m-d H:i:s',$vo['rk_time']):'',
                $vo['qx_time']?date('Y-m-d H:i:s',$vo['qx_time']):'',
                $vo['end_time']?date('Y-m-d H:i:s',$vo['end_time']):''
            ];
        }
        $this->export_excel($title,$data);
    }

    //改状态
    public function setst(){
        $ids = input('post.ids/a');
        $st = input('post.st/d');
        if(!$this->mdid){
            return json(['status'=>0,'msg'=>'取货人员订单操作只能门店人员管理人员进行操作']);
        }
        $psorderlist = Db::name('xixie_order')->where('mdid',$this->mdid)->where('aid',aid)->where('id','in',$ids)->where('delete',0)->select()->toArray();
        foreach($psorderlist as $k=>$v){
            Db::name('xixie_order')->where('aid',aid)->where('id',$v['id'])->update(['psid'=>0,'psmid'=>0]);
        }
        \app\commons\System::plog('取消取货单'.implode(',',$ids));
        return json(['status'=>1,'msg'=>'取消成功']);
    }
}