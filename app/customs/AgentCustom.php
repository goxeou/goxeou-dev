<?php


namespace app\customs;

use think\facade\Db;
use think\facade\Log;

class AgentCustom
{
    public static function ranking($aid,$pagenum,$st){
        if(getcustom('areafenhong_region_ranking')){
            //区域代理排行详活动
            if(!$pagenum) $pagenum = 1;
            $pernum  = 20;
            $where   = [];
            $where[] = ['aid','=',$aid];
            $where[] = ['status','=',1];
            $datalist = Db::name('region_ranking')->where($where)->page($pagenum,$pernum)->field('id,name,createtime')->order('id desc')->select()->toArray();
            if($datalist){
                foreach($datalist as &$dv){
                    $dv['createtime'] = $dv['createtime']?date("Y-m-d H:i",$dv['createtime']):'';
                }
                unset($dv);
            }else{
                $datalist = [];
            }
            $rdata = [];
            $rdata['status'] = 1;
            $rdata['data']   = $datalist;
            $rdata['pernum'] = $pernum;
            return $rdata;
        }
    }

    public static function rankingdetial($aid,$member,$id,$ranktype,$pagenum,$sorttype){
        if(getcustom('areafenhong_region_ranking')){
            //区域代理排行详情
            $ranking = Db::name('region_ranking')->where('id',$id)->where('status',1)->find();
            if(!$ranking){
                return ['status'=>0,'msg' =>'排行榜活动不存在'];
            }

            $start_time = '';
            $end_time   = '';
            if($ranking['ctime']){
                $ctime      = explode('~',$ranking['ctime']);
                $start_time = strtotime($ctime[0]);
                $end_time   = strtotime($ctime[1]);
            }

            //统计类型
            if(!$ranking['show_type']){
                return ['status'=>0,'data'=>[],'msg' =>'排行榜未开启'];
            }

            //可查看会员等级
            $gettj = explode(',',$ranking['levelids']);
            if(!in_array('-1',$gettj) && !in_array($member['levelid'],$gettj)) {
                $data = [];
                $data['status'] = 0;
                return ['status'=>0,'data'=>$data,'msg' =>'您没有查看权限'];
            }

            //统计等级
            if(!$ranking['levelids2']){
                return ['status'=>0,'data'=>[],'msg' =>'排行榜未开启'];
            }
            $countjs   = explode(',',$ranking['levelids2']);
            $levelids2 = [];//统计的等级
            $isall = false;//是否选中全部
            foreach($countjs as $cv){
                if($cv == -1){
                    $isall = true;
                    break;
                }else if($cv>0){
                    array_push($levelids2,$cv);
                }
            }
            unset($cv);
            if($isall){
                $where2 = '2=2';
            }else{
                if(!$levelids2){
                    return ['status'=>0,'data'=>[],'msg' =>'排行榜未开启'];
                }
                $levelids2 = implode(',',$levelids2);
                $where2 = 'm.levelid in ('.$levelids2.')';
            }

            //区域不参与排名商品
            if($ranking['qytype'] == 2){
                $where3 = 'og.proid not in ('.$ranking['productids'].')';
            }else{
                $where3 = '3=3';
            }

            //团队不参与排名商品
            if($ranking['tdtype'] == 2){
                $where4 = 'og.proid not in ('.$ranking['productids2'].')';
            }else{
                $where4 = '4=4';
            }

            $pernum  = 10;
            $pagenum = 1;
            $rdata     = [];
            $ranknum   = $ranking['people'];
            $totalpage = ceil($ranknum/$pernum);//2
            $quyu = $pernum;
            if($pernum * $pagenum > $ranknum) $quyu = $ranknum - $pernum * ($pagenum-1);
            if($ranknum>0){
                if($pagenum>$totalpage){
                    $rdata['data'] = [];
                    return ['status'=>0,'data'=>$rdata];
                }
            }
            $rdata['data'] = [];
            if(in_array($ranktype,[0,1,2])){
                $province = [];
                $city = [];
                $area =[];
                $where = [];
                $where[] = ['o.aid','=',$aid];
                $where[] = ['o.area2','<>',''];
                $where[] = ['o.delete','=',0];
                $where[] = Db::raw(" o.area2 IS NOT NULL and  o.area2 !=',,'");
                $area2   = Db::name('shop_order')
                    ->alias('o')
                    ->join('member m','o.mid = m.id')
                    ->join('shop_order_goods og','og.orderid = o.id')
                    ->where($where)
                    //->where($where2)
                    //->where($where3)
                    ->group('o.area2')->field('o.area2')->select()->toArray();
                foreach($area2 as $key=>$val){
                    $area2data = explode(',',$val['area2']);
                    if($area2data[0]){
                        $province[] = $area2data[0];
                    }
                    if($area2data[1]){
                        $city[] = $area2data[1];
                    }
                    if($area2data[2]){
                        $area[] = $area2data[2];
                    }
                }
                $province = array_unique($province);
                $city     = array_unique($city);
                $area     = array_unique($area);
            }

            if($ranktype==0){//省
                $list = [];
                foreach($province as $key=>$val){
                    $where = [];
                    $list[$key]['title'] = $val;
                    $where[] = ['o.aid','=',$aid];
                    $where[] = Db::raw("find_in_set('".$val."',o.area2)");
                    if($start_time && $end_time){
                        $where[] = ['o.paytime','between',[$start_time,$end_time]];
                    }
                    $where[] = ['o.status','in',[1,2,3]];
                    $where[] = ['o.delete','=',0];
                    $totalprice = 0+Db::name('shop_order')
                        ->alias('o')
                        ->join('member m','o.mid = m.id')
                        ->join('shop_order_goods og','og.orderid = o.id')
                        ->where($where)
                        ->where($where2)
                        ->where($where3)
                        ->sum('o.totalprice');
                    $list[$key]['totalprice'] = dd_money_format($totalprice);
                    $orderids = Db::name('shop_order')
                        ->alias('o')
                        ->join('member m','o.mid = m.id')
                        ->join('shop_order_goods og','og.orderid = o.id')
                        ->where($where)
                        ->where($where2)
                        ->where($where3)
                        ->column('o.id');
                    $num = Db::name('shop_order_goods')->alias('og')->where('og.aid',$aid)->where('og.orderid','in',$orderids)->where($where3)->sum('og.num');
                    $list[$key]['num'] = $num;
                }
                $totalprice = [];
                $num = [];
                foreach ($list as $key => $row)
                {
                    $totalprice[$key]  = $row['totalprice'];
                    $num[$key]  = $row['num'];
                }
                if($sorttype ==1){
                    array_multisort($totalprice,SORT_DESC,$list);
                } else{
                    array_multisort($num,SORT_DESC,$list); 
                }
              
                $datalist = [];
                if($ranknum > 0){
                    $ranknum = $ranknum >=count($list)?count($list): $ranknum;
                    for($i=0;$i<$ranknum;$i++){
                       $datalist[] = $list[$i];
                    }
                }else{
                    for($i=0;$i<count($list);$i++){
                       $datalist[] = $list[$i];
                    }
                }
              
                $rdata['data'] = $datalist;
            }elseif($ranktype==1){//市
                $list = [];
                foreach($city as $key=>$val){
                    $where = [];
                    $list[$key]['title'] = $val;
                    $where[] = ['o.aid','=',$aid];
                    $where[] = Db::raw("find_in_set('".$val."',o.area2)");

                    if($start_time && $end_time){
                        $where[] = ['o.paytime','between',[$start_time,$end_time]];
                    }
                    $where[] = ['o.status','in',[1,2,3]];
                    $where[] = ['o.delete','=',0];
                    $totalprice = 0+Db::name('shop_order')
                        ->alias('o')
                        ->join('member m','o.mid = m.id')
                        ->join('shop_order_goods og','og.orderid = o.id')
                        ->where($where)
                        ->where($where2)
                        ->where($where3)
                        ->sum('o.totalprice');
                    $list[$key]['totalprice'] = dd_money_format($totalprice);
                    $orderids =   Db::name('shop_order')
                        ->alias('o')
                        ->join('member m','o.mid = m.id')
                        ->join('shop_order_goods og','og.orderid = o.id')
                        ->where($where)
                        ->where($where2)
                        ->where($where3)
                        ->column('o.id');
                    $num =  Db::name('shop_order_goods')->alias('og')->where('og.aid',$aid)->where('og.orderid','in',$orderids)->where($where3)->sum('og.num');
                    $list[$key]['num'] = $num;
                }
              
                $totalprice = [];
                $num = [];
                foreach ($list as $key => $row)
                {
                    $totalprice[$key]  = $row['totalprice'];
                    $num[$key]  = $row['num'];
                }
                if($sorttype ==1){
                    array_multisort($totalprice,SORT_DESC,$list);
                } else{
                    array_multisort($num,SORT_DESC,$list);
                }
                $datalist = [];
                if($ranknum > 0){
                    $ranknum = $ranknum >=count($list)?count($list): $ranknum;
                    for($i=0;$i<$ranknum;$i++){
                       $datalist[] = $list[$i];
                    }
                }else{
                    for($i=0;$i<count($list);$i++){
                       $datalist[] = $list[$i];
                    }
                }
                $rdata['data'] = $datalist;
                
            }elseif($ranktype==2){//县区
                $list = [];
                foreach($area as $key=>$val){
                    $where = [];
                    $list[$key]['title'] = $val;
                    $where[] = ['o.aid','=',$aid];
                    $where[] = Db::raw("find_in_set('".$val."',o.area2)");
                    if($start_time && $end_time){
                        $where[] = ['o.paytime','between',[$start_time,$end_time]];
                    }
                    $where[] = ['o.status','in',[1,2,3]];
                    $where[] = ['o.delete','=',0];
                    $totalprice = 0+Db::name('shop_order')
                        ->alias('o')
                        ->join('member m','o.mid = m.id')
                        ->join('shop_order_goods og','og.orderid = o.id')
                        ->where($where)
                        ->where($where2)
                        ->where($where3)
                        ->sum('o.totalprice');
                    $list[$key]['totalprice'] = dd_money_format($totalprice);
                    $orderids =   Db::name('shop_order')
                        ->alias('o')
                        ->join('member m','o.mid = m.id')
                        ->join('shop_order_goods og','og.orderid = o.id')
                        ->where($where)
                        ->where($where2)
                        ->where($where3)
                        ->column('o.id');
                    $num =  Db::name('shop_order_goods')->alias('og')->where('og.aid',$aid)->where('og.orderid','in',$orderids)->where($where3)->sum('og.num');
                    $list[$key]['num'] = $num;
                }
                $totalprice = [];
                $num = [];
                foreach ($list as $key => $row)
                {
                    $totalprice[$key]  = $row['totalprice'];
                    $num[$key]  = $row['num'];
                }
                if($sorttype ==1){
                    array_multisort($totalprice,SORT_DESC,$list);
                } else{
                    array_multisort($num,SORT_DESC,$list);
                }
                $datalist = [];
                if($ranknum > 0){
                    $ranknum = $ranknum >=count($list)?count($list): $ranknum;
                    for($i=0;$i<$ranknum;$i++){
                       $datalist[] = $list[$i];
                    }
                }else{
                    for($i=0;$i<count($list);$i++){
                       $datalist[] = $list[$i];
                    }
                }
                $rdata['data'] = $datalist;
            }else{//个人团队

                //自己
                $selfdata = ["key"=>0,'headimg'=>$member['headimg'],'nickname'=>$member['nickname'],'totalprice'=>0,'num'=>0];

                $downmids   = [];
                $downmids[] = mid;
                if($ranking['levelnum']>0){
                    $deep =$ranking['levelnum'];
                }else{
                    $deep = 999;
                }
                if($isall){
                    $downmids_x = \app\commons\Member::getteammids($ranking['aid'],mid,$deep);
                }else{
                    $downmids_x = \app\commons\Member::getteammids($ranking['aid'],mid,$deep,$levelids);
                }
                $downmids = array_merge($downmids_x,$downmids);
                $where = [];
                $where[] = ['o.aid','=',$aid];
                $where[] = ['o.mid','in',$downmids];
                if($start_time && $end_time){
                    $where[] = ['o.paytime','between',[$start_time,$end_time]];
                }
                $where[] = ['o.status','in',[1,2,3]];
                $where[] = ['o.delete','=',0];
                if($sorttype ==1){
                    $totalprice =0+ Db::name('shop_order')
                        ->alias('o')
                        ->join('member m','o.mid = m.id')
                        ->join('shop_order_goods og','og.orderid = o.id')
                        ->where($where)
                        ->where($where2)
                        ->where($where4)
                        ->sum('o.totalprice');
                    $selfdata['totalprice'] = dd_money_format($totalprice);
                }else{
                    $orderids =   Db::name('shop_order')
                        ->alias('o')
                        ->join('member m','o.mid = m.id')
                        ->join('shop_order_goods og','og.orderid = o.id')
                        ->where($where)
                        ->where($where2)
                        ->where($where4)
                        ->column('o.id');
                    $num =0+ Db::name('shop_order_goods')->alias('og')->where('og.aid',$aid)->where('og.orderid','in',$orderids)->where($where4)->sum('og.num');
                    $selfdata['num'] = $num;
                }

                $where = [];
                $where[] = ['aid','=',$aid];
                $memberlist = Db::name('member')->where($where)->field('id,nickname,headimg,path')->select()->toArray();
                foreach($memberlist as $key=>$member){
                    $downmids = [];
                    $downmids[] = $member['id'];
                    if($ranking['levelnum']>0){
                        $deep =$ranking['levelnum'];
                    }else{
                        $deep = 999;
                    }

                    if($isall){
                        $downmids_x = \app\commons\Member::getteammids($ranking['aid'],$member['id'],$deep);
                    }else{
                        $downmids_x = \app\commons\Member::getteammids($ranking['aid'],$member['id'],$deep,$levelids);
                    }
                    $downmids = array_merge($downmids_x,$downmids);

                    $where = [];
                    $where[] = ['o.aid','=',$aid];
                    $where[] = ['o.mid','in',$downmids];
                    if($start_time && $end_time){
                        $where[] = ['o.paytime','between',[$start_time,$end_time]];
                    }
                    $where[] = ['o.status','in',[1,2,3]];
                    $where[] = ['o.delete','=',0];
                    if($sorttype ==1){
                        $totalprice =0+ Db::name('shop_order')
                            ->alias('o')
                            ->join('member m','o.mid = m.id')
                            ->join('shop_order_goods og','og.orderid = o.id')
                            ->where($where)
                            ->where($where2)
                            ->where($where4)
                            ->sum('o.totalprice');
                        $memberlist[$key]['totalprice'] = dd_money_format($totalprice);
                    }else{
                        $orderids =   Db::name('shop_order')
                            ->alias('o')
                            ->join('member m','o.mid = m.id')
                            ->join('shop_order_goods og','og.orderid = o.id')
                            ->where($where)
                            ->where($where2)
                            ->where($where4)
                            ->column('o.id');
                        $num =0+ Db::name('shop_order_goods')->alias('og')->where('og.aid',$aid)->where('og.orderid','in',$orderids)->where($where4)->sum('og.num');
                        $memberlist [$key]['num'] = $num;
                    }
                }
                $totalprice = [];
                $num = [];
                foreach ($memberlist as $key => $row)
                {
                    $totalprice[$key]  = $row['totalprice'];
                    $num[$key]  = $row['num'];
                }
                if($sorttype ==1){
                    array_multisort($totalprice,SORT_DESC,$memberlist);
                } else{
                    array_multisort($num,SORT_DESC,$memberlist);
                }
                $datalist = [];
              
                if($ranknum > 0){
                    $ranknum = $ranknum >=count($memberlist)?count($memberlist): $ranknum;
                    for($i=0;$i<$ranknum;$i++){
                       $datalist[] = $memberlist[$i];
                    }
                }else{
                    for($i=0;$i<count($memberlist);$i++){
                       $datalist[] = $memberlist[$i];
                    }
                }
                foreach ($datalist as $k =>$v)
                {
                    if($v['id'] == mid){
                        $selfdata['key'] = $k+1;
                    }
                }
                $rdata['selfdata'] = $selfdata;
                $rdata['data']     = $datalist;
            }
            return ['status'=>1,'data'=>$rdata,'region_ctime' => $ranking['ctime'],'show_type' =>explode(',',$ranking['show_type']) ];
        }
    }

    public static function rankingorder($aid,$member,$id,$pagenum,$sorttype){
        if(getcustom('areafenhong_region_ranking')){
            //区域代理排行详情
            $ranking = Db::name('region_ranking')->where('id',$id)->where('status',1)->find();
            if(!$ranking){
                return ['status'=>0,'msg' =>'排行榜活动不存在'];
            }

            $start_time = '';
            $end_time   = '';
            if($ranking['ctime']){
                $ctime      = explode('~',$ranking['ctime']);
                $start_time = strtotime($ctime[0]);
                $end_time   = strtotime($ctime[1]);
            }

            //统计类型
            if(!$ranking['show_type']){
                return ['status'=>0,'data'=>[],'msg' =>'排行榜未开启'];
            }

            //可查看会员等级
            $gettj = explode(',',$ranking['levelids']);
            if(!in_array('-1',$gettj) && !in_array($member['levelid'],$gettj)) {
                $data = [];
                $data['status'] = 0;
                return ['status'=>0,'data'=>$data,'msg' =>'您没有查看权限'];
            }

            //统计等级
            if(!$ranking['levelids2']){
                return ['status'=>0,'data'=>[],'msg' =>'排行榜未开启'];
            }
            $countjs   = explode(',',$ranking['levelids2']);
            $levelids2 = [];//统计的等级
            $isall = false;//是否选中全部
            foreach($countjs as $cv){
                if($cv == -1){
                    $isall = true;
                    break;
                }else if($cv>0){
                    array_push($levelids2,$cv);
                }
            }
            unset($cv);
            if($isall){
                $where2 = '2=2';
            }else{
                if(!$levelids2){
                    return ['status'=>0,'data'=>[],'msg' =>'排行榜未开启'];
                }
                $levelids2 = implode(',',$levelids2);
                $where2 = 'm.levelid in ('.$levelids2.')';
            }

            //区域不参与排名商品
            if($ranking['qytype'] == 2){
                $where3 = 'og.proid not in ('.$ranking['productids'].')';
            }else{
                $where3 = '3=3';
            }

            //团队不参与排名商品
            if($ranking['tdtype'] == 2){
                $where4 = 'og.proid not in ('.$ranking['productids2'].')';
            }else{
                $where4 = '4=4';
            }

            $pernum  = 10;
            if(!$pagenum) $pagenum = 1;

            $downmids   = [];
            $downmids[] = mid;
            if($ranking['levelnum']>0){
                $deep =$ranking['levelnum'];
            }else{
                $deep = 999;
            }
            if($isall){
                $downmids_x = \app\commons\Member::getteammids($ranking['aid'],mid,$deep);
            }else{
                $downmids_x = \app\commons\Member::getteammids($ranking['aid'],mid,$deep,$levelids);
            }
            $downmids = array_merge($downmids_x,$downmids);
            $where = [];
            $where[] = ['o.aid','=',$aid];
            $where[] = ['o.mid','in',$downmids];
            if($start_time && $end_time){
                $where[] = ['o.paytime','between',[$start_time,$end_time]];
            }
            $where[] = ['o.status','in',[1,2,3]];
            $where[] = ['o.delete','=',0];
            if($sorttype ==1){
                $datalist = Db::name('shop_order')
                    ->alias('o')
                    ->join('member m','o.mid = m.id')
                    ->join('shop_order_goods og','og.orderid = o.id')
                    ->where($where)
                    ->where($where2)
                    ->where($where4)
                    ->page($pagenum,$pernum)
                    ->order('o.id desc')
                    ->field('o.*,m.nickname,m.headimg')
                    ->select()
                    ->toArray();
            }else{
                $datalist = Db::name('shop_order')
                    ->alias('o')
                    ->join('member m','o.mid = m.id')
                    ->join('shop_order_goods og','og.orderid = o.id')
                    ->where($where)
                    ->where($where2)
                    ->where($where4)
                    ->page($pagenum,$pernum)
                    ->order('o.id desc')
                    ->field('o.*,m.nickname,m.headimg')
                    ->select()
                    ->toArray();
            }
            if($datalist){
                foreach($datalist as $key=>$v){

                    $datalist[$key]['prolist'] = [];
                    $prolist = Db::name('shop_order_goods')->where('orderid',$v['id'])->select()->toArray();
                    if($prolist) $datalist[$key]['prolist'] = $prolist;
                    $datalist[$key]['procount']  = Db::name('shop_order_goods')->where('orderid',$v['id'])->sum('num');
                    $datalist[$key]['refundnum'] = Db::name('shop_order_goods')->where('orderid',$v['id'])->sum('refund_num');
                    if($v['bid']!=0){
                        $datalist[$key]['binfo'] = Db::name('business')->where('aid',aid)->where('id',$v['bid'])->field('id,name,logo')->find();
                        if(!$datalist[$key]['binfo']) $datalist[$key]['binfo'] = [];
                    } else {
                        $datalist[$key]['binfo'] = Db::name('admin_set')->where('aid',aid)->field('name,logo')->find();
                    }
                    $datalist[$key]['tips'] = '';
                }
            }
            $rdata = [];
            $rdata['status']   = 1;
            $rdata['datalist'] = $datalist;
            return $rdata;
        }
    }

    public static function shoporderranking($aid,$member,$st){
        if(getcustom('shoporder_ranking')){
            //查看当前月份消费排行榜数据
            //排行详情
            $ranking = Db::name('shoporder_ranking_set')->where('aid',$aid)->where('status',1)->find();
            if(!$ranking){
                return ['status'=>0,'msg' =>'排行榜活动未开启'];
            }
            //平台、商户参与的订单类型
            $join_ordertype  = $ranking['join_ordertype']?explode(',',$ranking['join_ordertype']):[];
            $join_ordertype2 = $ranking['join_ordertype2']?explode(',',$ranking['join_ordertype2']):[];

            $givedata = json_decode($ranking['givedata'],true);
            if(empty($givedata)){
                return ['status'=>0,'msg' =>'排行榜活动暂未开启'];
            }

            $start_time = strtotime(date('Y-m'));
            $end_time   = strtotime('+1 month',$start_time)-1;

            //获取订单商品列表
            $oglist = [];
            //商城订单
            if(in_array('shop',$join_ordertype) || in_array('shop',$join_ordertype2)){
                $shoplist = Db::name('shop_order_goods')
                    ->alias('og')
                    ->join('shop_order o','o.id=og.orderid')
                    ->join('member m','m.id=og.mid')
                    ->where('og.aid',$aid);
                if(in_array('shop',$join_ordertype) && !in_array('shop',$join_ordertype2)){
                    $shoplist = $shoplist->where('o.bid',0);
                }else if(!in_array('shop',$join_ordertype) && in_array('shop',$join_ordertype2)){
                    $shoplist = $shoplist->where('o.bid','>',0);
                }
                $shoplist = $shoplist->where('o.status',3)
                    ->where('og.endtime','>=',$start_time)
                    ->where('og.endtime','<=',$end_time)
                    ->where('o.delete','=',0)
                    ->field('og.id,og.mid,og.real_totalprice,og.proid,og.num,m.nickname,m.headimg,m.tel,"shop" as ordertype')
                    ->select()
                    ->toArray();
                if($shoplist){
                    $oglist = array_merge($oglist,$shoplist);
                }
            }

            //多人拼团订单
            if(in_array('collage',$join_ordertype) || in_array('collage',$join_ordertype2)){
                $collagelist = Db::name('collage_order')
                    ->alias('o')
                    ->join('member m','m.id=o.mid')
                    ->where('o.aid',$aid);
                if(in_array('collage',$join_ordertype) && !in_array('collage',$join_ordertype2)){
                    $collagelist = $collagelist->where('o.bid',0);
                }else if(!in_array('collage',$join_ordertype) && in_array('collage',$join_ordertype2)){
                    $collagelist = $collagelist->where('o.bid','>',0);
                }
                $collagelist = $collagelist ->where('o.status',3)
                    ->where('o.collect_time','>=',$start_time)
                    ->where('o.collect_time','<=',$end_time)
                    ->where('o.delete','=',0)
                    ->field('o.id,o.mid,o.totalprice as real_totalprice,o.proid,o.num,m.nickname,m.headimg,m.tel,"collage" as ordertype')
                    ->select()
                    ->toArray();
                if($collagelist){
                    $oglist = array_merge($oglist,$collagelist);
                }
            }

            //砍价订单
            if(in_array('kanjia',$join_ordertype) || in_array('kanjia',$join_ordertype2)){
                $kanjialist = Db::name('kanjia_order')
                    ->alias('o')
                    ->join('member m','m.id=o.mid')
                    ->where('o.aid',$aid);
                if(in_array('kanjia',$join_ordertype) && !in_array('kanjia',$join_ordertype2)){
                    $kanjialist = $kanjialist->where('o.bid',0);
                }else if(!in_array('kanjia',$join_ordertype) && in_array('kanjia',$join_ordertype2)){
                    $kanjialist = $kanjialist->where('o.bid','>',0);
                }
                $kanjialist = $kanjialist ->where('o.status',3)
                    ->where('o.collect_time','>=',$start_time)
                    ->where('o.collect_time','<=',$end_time)
                    ->where('o.delete','=',0)
                    ->field('o.id,o.mid,o.totalprice as real_totalprice,o.proid,o.num,m.nickname,m.headimg,m.tel,"kanjia" as ordertype')
                    ->select()
                    ->toArray();
                if($kanjialist){
                    $oglist = array_merge($oglist,$kanjialist);
                }
            }

            //秒杀订单
            if(in_array('seckill',$join_ordertype) || in_array('seckill',$join_ordertype2)){
                $seckilllist = Db::name('seckill_order')
                    ->alias('o')
                    ->join('member m','m.id=o.mid')
                    ->where('o.aid',$aid);
                if(in_array('seckill',$join_ordertype) && !in_array('seckill',$join_ordertype2)){
                    $seckilllist = $seckilllist->where('o.bid',0);
                }else if(!in_array('seckill',$join_ordertype) && in_array('seckill',$join_ordertype2)){
                    $seckilllist = $seckilllist->where('o.bid','>',0);
                }
                $seckilllist = $seckilllist ->where('o.status',3)
                    ->where('o.collect_time','>=',$start_time)
                    ->where('o.collect_time','<=',$end_time)
                    ->where('o.delete','=',0)
                    ->field('o.id,o.mid,o.totalprice as real_totalprice,o.proid,o.num,m.nickname,m.headimg,m.tel,"seckill" as ordertype')
                    ->select()
                    ->toArray();
                if($seckilllist){
                    $oglist = array_merge($oglist,$seckilllist);
                }
            }

            //团购订单
            if(in_array('tuangou',$join_ordertype) || in_array('tuangou',$join_ordertype2)){
                $tuangoulist = Db::name('tuangou_order')
                    ->alias('o')
                    ->join('member m','m.id=o.mid')
                    ->where('o.aid',$aid);
                if(in_array('tuangou',$join_ordertype) && !in_array('tuangou',$join_ordertype2)){
                    $tuangoulist = $tuangoulist->where('o.bid',0);
                }else if(!in_array('tuangou',$join_ordertype) && in_array('tuangou',$join_ordertype2)){
                    $tuangoulist = $tuangoulist->where('o.bid','>',0);
                }
                $tuangoulist = $tuangoulist ->where('o.status',3)
                    ->where('o.collect_time','>=',$start_time)
                    ->where('o.collect_time','<=',$end_time)
                    ->where('o.delete','=',0)
                    ->field('o.id,o.mid,o.totalprice as real_totalprice,o.proid,o.num,m.nickname,m.headimg,m.tel,"tuangou" as ordertype')
                    ->select()
                    ->toArray();
                if($tuangoulist){
                    $oglist = array_merge($oglist,$tuangoulist);
                }
            }

            //买单订单
            if(in_array('maidan',$join_ordertype) || in_array('maidan',$join_ordertype2)){
                $maidanlist = Db::name('maidan_order')
                    ->alias('o')
                    ->join('member m','m.id=o.mid')
                    ->where('o.aid',$aid);
                if(in_array('maidan',$join_ordertype) && !in_array('maidan',$join_ordertype2)){
                    $maidanlist = $maidanlist->where('o.bid',0);
                }else if(!in_array('maidan',$join_ordertype) && in_array('maidan',$join_ordertype2)){
                    $maidanlist = $maidanlist->where('o.bid','>',0);
                }
                $maidanlist = $maidanlist ->where('o.status',1)
                    ->where('o.paytime','>=',$start_time)
                    ->where('o.paytime','<=',$end_time)
                    ->field('o.id,o.mid,o.paymoney as real_totalprice,m.nickname,m.headimg,m.tel,"1" as num,"maidan" as ordertype')
                    ->select()
                    ->toArray();
                if($maidanlist){
                    $oglist = array_merge($oglist,$maidanlist);
                }
            }

            //计算总分红池
            $poolmoney = 0;
            //处理会员奖金计算
            $mids = [];
            $mlist= [];
            if($oglist){
                foreach($oglist as $ov){
                    $summoney   = 0;
                    $poolmoney2 = 0;
                    if($ov['ordertype'] == 'shop'){
                        //查询商品参数
                        $goods = Db::name('shop_product')->where('id',$ov['proid'])->field('rankingset,ranking_radio,ranking_money')->find();
                        if($goods && $goods['rankingset'] != 0){
                            if($goods['rankingset'] == -1){
                                continue;
                            }else if($goods['rankingset'] == 1){
                                $poolmoney2 = $ov['real_totalprice'] * $goods['ranking_radio']/100 + $goods['ranking_money'];
                            }
                        }else{
                            $poolmoney2 = $ov['real_totalprice'] * $ranking['pool']/100;
                        }
                    }else{
                        $poolmoney2 = $ov['real_totalprice'] * $ranking['pool']/100;
                    }
                    $summoney = $ov['real_totalprice'];

                    //查询是否添加过
                    $pos = array_search($ov['mid'],$mids);
                    if($pos ===0 || $pos>0){
                        $mlist[$pos]['summoney'] += $summoney;
                        $mlist[$pos]['summoney'] = round($mlist[$pos]['summoney'],2);
                    }else{
                        $mids[] = $ov['mid'];
                        //昵称中间隐藏
                        if($ranking['nickname_center_hidden'] == 1){
                            $len = mb_strlen($ov['nickname']);
                            if($len<=1){
                                $ov['nickname'] = $ov['nickname'];
                            }else if($len==2){
                                $ov['nickname'] = mb_substr($ov['nickname'],0,1).'***';
                            }else if($len==3){
                                $ov['nickname'] = mb_substr($ov['nickname'],0,1).'***'.mb_substr($ov['nickname'],-1);
                            }else if($len>=9){
                                $dmv['nickname'] = mb_substr($dmv['nickname'],0,3).'***'.mb_substr($dmv['nickname'],-3);
                            }else{
                                $ov['nickname'] = mb_substr($ov['nickname'],0,2).'***'.mb_substr($ov['nickname'],-2);
                            }
                        }
                        $mlist[]= [
                            'mid'=>$ov['mid'],
                            'nickname'=>$ov['nickname'],
                            'headimg'=>$ov['headimg'],
                            'tel'=>$ov['tel'],
                            'summoney'=>round($summoney,2)
                        ];
                    }
                    $poolmoney += ($poolmoney2*$ov['num']);
                }
                unset($ov);
            }

            //总分红池
            $poolmoney = round($poolmoney,2);

            //变动金额
            $changemoney = 0;
            //查询是否已添加过
            $log = Db::name('shoporder_ranking_log')->where('month',$start_time)->where('aid',$aid)->field('id,changemoney')->find();
            if($log){
                $changemoney = $log['changemoney'];
            }

            //子分红池数据
            $itemdata  = [];
            $itemst    = [];
            $childdata = [];//当前子分红池
            foreach($givedata as $gk=>$gv){
                if($gk == $st){
                    $childdata = $gv;
                }

                //子分红池奖金
                $childmoney = ($poolmoney+$changemoney)*$gv['ratio']/100;
                $childmoney = $childmoney>0?round($childmoney,2):0;

                if($ranking['childprefix_addmonth']){
                    $prefixname = intval(date('m'));
                    $gv['name'] = $prefixname.'月'.$gv['name'];
                }
                $itemdata[] = ['name'=>$gv['name'],'childmoney'=>$childmoney];
                $itemst[]   = $gk;
            }
            if(empty($childdata)){
                return ['status'=>0,'msg' =>'分红池不存在'];
            }

            //子分红池奖金
            $childmoney = ($poolmoney+$changemoney)*$childdata['ratio']/100;
            $childmoney = $childmoney>0?round($childmoney,2):0;

            //自己排名
            $selfranking = 0;
            if($mlist){
                //按金额重新排序
                $count = count($mlist);
                if($count>1){
                    $mlist2 = array_column($mlist,'summoney');
                    array_multisort($mlist2 ,SORT_DESC,$mlist);
                }
                if($childdata['num']>0){
                    //处理平分人数
                    $newmlist = [];
                    $i = 1;
                    foreach($mlist as $mv){
                        if($mv['mid'] == $member['id']){
                            $selfranking = $i;
                        }
                        if($i<=$childdata['num'] && $mv['summoney']>=$childdata['money']){
                            if($childdata['moreave'] && !empty($childdata['moreave'])){
                                //计算多段平分奖励
                                $moreavenum  = 0;//符合的多段平分人数
                                $moreaveratio= 0;//符合的多段平分比例
                                foreach($childdata['moreave'] as $moreave){
                                    if($i>=$moreave['min'] && $i<=$moreave['max']){
                                        if($moreave['min'] == $moreave['max']){
                                            $moreavenum = 1;
                                        }else{
                                            $moreavenum = $moreave['max'] - $moreave['min']+1;
                                        }
                                        $moreaveratio = $moreave['ratio'];
                                    }
                                }
                                if($moreavenum<=0){
                                    $avgmoney = 0;
                                }else{
                                    $avgmoney = ($childmoney*$moreaveratio*0.01)/$moreavenum;
                                }
                                $mv['avgmoney'] = round($avgmoney,2);
                            }else{
                                //平均金额
                                $mv['avgmoney'] = round($childmoney/$childdata['num'],2);
                            }
                            $newmlist[] = $mv;
                        }else{
                            break;
                        }
                        $i++;
                    }
                    unset($mv);
                    $mlist = $newmlist;
                }else{
                    $mlist = [];
                }
            }

            $rdata = [];
            $rdata['status']    = 1;
            $rdata['title']     = $ranking['name']?$ranking['name']:'';
            $rdata['pic']       = $ranking['pic']?$ranking['pic']:'';
            $rdata['poolmoney'] = ($poolmoney+$changemoney);//总分红池
            $rdata['poolmoney'] = $rdata['poolmoney']>0?round($rdata['poolmoney'],2):0;//总分红池
            $rdata['itemdata']  = $itemdata;//子分红池名称组
            $rdata['itemst']    = $itemst;//子分红池key组
            $rdata['display']   = $childdata['display'];//预估奖金是否显示
            $rdata['childmoney']= $childmoney;//子分红池
            $rdata['showmoney'] = false;
            $rdata['money']     = $childdata['money']>0?round($childdata['money'],2):0;//达标消费差额
            $rdata['mlist']     = $mlist;
            $set = [
                'showpool'=>$ranking['showpool']?true:false,
                'showchildpool'=>$ranking['showchildpool']?true:false,
                'showselfranking'=>$ranking['showselfranking']?true:false,
                'showlast'=>$ranking['showlast']?true:false
            ];
            $rdata['set']         = $set;
            $rdata['selfranking'] = $selfranking;
            $lastid = 0;
            if($ranking['showlast']){
                //查询上一期ID
                $nowmonth  = strtotime(date("Y-m"));
                $lastmonth = strtotime('-1 month',$nowmonth);
                $lastid = Db::name('shoporder_ranking_log')->where('aid',$aid)->where('month',$lastmonth)->order('id desc')->value('id');
            }
            $rdata['lastid'] = $lastid??0;
            return $rdata;
        }
    }
    public static function allshoporderranking($aid,$type=1){
        if(getcustom('shoporder_ranking')){
            //记录当前月份所有数据

            //排行详情
            $ranking = Db::name('shoporder_ranking_set')->where('aid',$aid)->where('status',1)->find();
            if(!$ranking){
                return ['status'=>0,'msg' =>'排行榜活动未开启'];
            }
            //平台、商户参与的订单类型
            $join_ordertype  = $ranking['join_ordertype']?explode(',',$ranking['join_ordertype']):[];
            $join_ordertype2 = $ranking['join_ordertype2']?explode(',',$ranking['join_ordertype2']):[];

            $givedata = json_decode($ranking['givedata'],true);
            if(empty($givedata)){
                return ['status'=>0,'msg' =>'排行榜活动暂未开启'];
            }

            $start_time = strtotime(date('Y-m'));//统计当月
            if($type == 2){//统计上个月
                $start_time = strtotime('-1 month',$start_time);
                //查询是否已发放过
                $lastmonth = cache($aid.'-2-ranking-lastmonth');
                if($lastmonth && !empty($lastmonth) && $lastmonth == $start_time){
                    return ['status'=>0,'msg' =>'此月份已发放过'];
                }
                cache($aid.'-2-ranking-lastmonth',$start_time);
            }
            $end_time   = strtotime('+1 month',$start_time)-1;

            //获取订单商品列表
            $oglist = [];
            //商城订单
            if(in_array('shop',$join_ordertype) || in_array('shop',$join_ordertype2)){
                $shoplist = Db::name('shop_order_goods')
                    ->alias('og')
                    ->join('shop_order o','o.id=og.orderid')
                    ->join('member m','m.id=og.mid')
                    ->where('og.aid',$aid);
                if(in_array('shop',$join_ordertype) && !in_array('shop',$join_ordertype2)){
                    $shoplist = $shoplist->where('o.bid',0);
                }else if(!in_array('shop',$join_ordertype) && in_array('shop',$join_ordertype2)){
                    $shoplist = $shoplist->where('o.bid','>',0);
                }
                $shoplist = $shoplist ->where('o.status',3)
                    ->where('og.endtime','>=',$start_time)
                    ->where('og.endtime','<=',$end_time)
                    ->where('o.delete','=',0)
                    ->field('og.id,og.mid,og.real_totalprice,og.proid,og.num,m.nickname,m.headimg,m.tel,"shop" as ordertype')
                    ->select()
                    ->toArray();
                if($shoplist){
                    $oglist = array_merge($oglist,$shoplist);
                }
            }

            //多人拼团订单
            if(in_array('collage',$join_ordertype) || in_array('collage',$join_ordertype2)){
                $collagelist = Db::name('collage_order')
                    ->alias('o')
                    ->join('member m','m.id=o.mid')
                    ->where('o.aid',$aid);
                if(in_array('collage',$join_ordertype) && !in_array('collage',$join_ordertype2)){
                    $collagelist = $collagelist->where('o.bid',0);
                }else if(!in_array('collage',$join_ordertype) && in_array('collage',$join_ordertype2)){
                    $collagelist = $collagelist->where('o.bid','>',0);
                }
                $collagelist = $collagelist ->where('o.status',3)
                    ->where('o.collect_time','>=',$start_time)
                    ->where('o.collect_time','<=',$end_time)
                    ->where('o.delete','=',0)
                    ->field('o.id,o.mid,o.totalprice as real_totalprice,o.proid,o.num,m.nickname,m.headimg,m.tel,"collage" as ordertype')
                    ->select()
                    ->toArray();
                if($collagelist){
                    $oglist = array_merge($oglist,$collagelist);
                }
            }

            //砍价订单
            if(in_array('kanjia',$join_ordertype) || in_array('kanjia',$join_ordertype2)){
                $kanjialist = Db::name('kanjia_order')
                    ->alias('o')
                    ->join('member m','m.id=o.mid')
                    ->where('o.aid',$aid);
                if(in_array('kanjia',$join_ordertype) && !in_array('kanjia',$join_ordertype2)){
                    $kanjialist = $kanjialist->where('o.bid',0);
                }else if(!in_array('kanjia',$join_ordertype) && in_array('kanjia',$join_ordertype2)){
                    $kanjialist = $kanjialist->where('o.bid','>',0);
                }
                $kanjialist = $kanjialist ->where('o.status',3)
                    ->where('o.collect_time','>=',$start_time)
                    ->where('o.collect_time','<=',$end_time)
                    ->where('o.delete','=',0)
                    ->field('o.id,o.mid,o.totalprice as real_totalprice,o.proid,o.num,m.nickname,m.headimg,m.tel,"kanjia" as ordertype')
                    ->select()
                    ->toArray();
                if($kanjialist){
                    $oglist = array_merge($oglist,$kanjialist);
                }
            }

            //秒杀订单
            if(in_array('seckill',$join_ordertype) || in_array('seckill',$join_ordertype2)){
                $seckilllist = Db::name('seckill_order')
                    ->alias('o')
                    ->join('member m','m.id=o.mid')
                    ->where('o.aid',$aid);
                if(in_array('seckill',$join_ordertype) && !in_array('seckill',$join_ordertype2)){
                    $seckilllist = $seckilllist->where('o.bid',0);
                }else if(!in_array('seckill',$join_ordertype) && in_array('seckill',$join_ordertype2)){
                    $seckilllist = $seckilllist->where('o.bid','>',0);
                }
                $seckilllist = $seckilllist ->where('o.status',3)
                    ->where('o.collect_time','>=',$start_time)
                    ->where('o.collect_time','<=',$end_time)
                    ->where('o.delete','=',0)
                    ->field('o.id,o.mid,o.totalprice as real_totalprice,o.proid,o.num,m.nickname,m.headimg,m.tel,"seckill" as ordertype')
                    ->select()
                    ->toArray();
                if($seckilllist){
                    $oglist = array_merge($oglist,$seckilllist);
                }
            }

            //团购订单
            if(in_array('tuangou',$join_ordertype) || in_array('tuangou',$join_ordertype2)){
                $tuangoulist = Db::name('tuangou_order')
                    ->alias('o')
                    ->join('member m','m.id=o.mid')
                    ->where('o.aid',$aid);
                if(in_array('tuangou',$join_ordertype) && !in_array('tuangou',$join_ordertype2)){
                    $tuangoulist = $tuangoulist->where('o.bid',0);
                }else if(!in_array('tuangou',$join_ordertype) && in_array('tuangou',$join_ordertype2)){
                    $tuangoulist = $tuangoulist->where('o.bid','>',0);
                }
                $tuangoulist = $tuangoulist ->where('o.status',3)
                    ->where('o.collect_time','>=',$start_time)
                    ->where('o.collect_time','<=',$end_time)
                    ->where('o.delete','=',0)
                    ->field('o.id,o.mid,o.totalprice as real_totalprice,o.proid,o.num,m.nickname,m.headimg,m.tel,"tuangou" as ordertype')
                    ->select()
                    ->toArray();
                if($tuangoulist){
                    $oglist = array_merge($oglist,$tuangoulist);
                }
            }

            //买单订单
            if(in_array('maidan',$join_ordertype) || in_array('maidan',$join_ordertype2)){
                $maidanlist = Db::name('maidan_order')
                    ->alias('o')
                    ->join('member m','m.id=o.mid')
                    ->where('o.aid',$aid);
                if(in_array('maidan',$join_ordertype) && !in_array('maidan',$join_ordertype2)){
                    $maidanlist = $maidanlist->where('o.bid',0);
                }else if(!in_array('maidan',$join_ordertype) && in_array('maidan',$join_ordertype2)){
                    $maidanlist = $maidanlist->where('o.bid','>',0);
                }
                $maidanlist = $maidanlist ->where('o.status',1)
                    ->where('o.paytime','>=',$start_time)
                    ->where('o.paytime','<=',$end_time)
                    ->field('o.id,o.mid,o.paymoney as real_totalprice,m.nickname,m.headimg,m.tel,"1" as num,"maidan" as ordertype')
                    ->select()
                    ->toArray();
                if($maidanlist){
                    $oglist = array_merge($oglist,$maidanlist);
                }
            }

            //计算总分红池
            $poolmoney = 0;
            //处理会员奖金计算
            $mids = [];
            $mlist= [];
            if($oglist){
                foreach($oglist as $ov){
                    $summoney   = 0;
                    $poolmoney2 = 0;
                    if($ov['ordertype'] == 'shop'){
                        //查询商品参数
                        $goods = Db::name('shop_product')->where('id',$ov['proid'])->field('rankingset,ranking_radio,ranking_money')->find();
                        if($goods && $goods['rankingset'] != 0){
                            if($goods['rankingset'] == -1){
                                continue;
                            }else if($goods['rankingset'] == 1){
                                $poolmoney2 = $ov['real_totalprice'] * $goods['ranking_radio']/100 + $goods['ranking_money'];
                            }
                        }else{
                            $poolmoney2 = $ov['real_totalprice'] * $ranking['pool']/100;
                        }
                    }else{
                        $poolmoney2 = $ov['real_totalprice'] * $ranking['pool']/100;
                    }
                    $summoney = $ov['real_totalprice'];

                    //查询是否添加过
                    $pos = array_search($ov['mid'],$mids);
                    if($pos ===0 || $pos>0){
                        $mlist[$pos]['summoney'] += $summoney;
                        $mlist[$pos]['summoney'] = round($mlist[$pos]['summoney'],2);
                    }else{
                        $mids[] = $ov['mid'];
                        $mlist[]= [
                            'mid'=>$ov['mid'],
                            'nickname'=>$ov['nickname'],
                            'headimg'=>$ov['headimg'],
                            'tel'=>$ov['tel'],
                            'summoney'=>round($summoney,2)
                        ];
                    }
                    $poolmoney += ($poolmoney2*$ov['num']);
                }
                unset($ov);
            }
            //总分红池
            $poolmoney = round($poolmoney,2);

            //变动金额
            $changemoney = 0;
            //查询是否已添加过
            $log = Db::name('shoporder_ranking_log')->where('month',$start_time)->where('aid',$aid)->field('id,changemoney,issend')->find();
            if($log){
                if($type == 2 && $log['issend'] == 1){
                    return ['status'=>0,'msg' =>'此月份已发放过'];
                }
                $changemoney = $log['changemoney'];
            }

            if($mlist){
                //按金额重新排序
                $count = count($mlist);
                if($count>1){
                    $mlist2 = array_column($mlist,'summoney');
                    array_multisort($mlist2 ,SORT_DESC,$mlist);
                }
            }

            $data = [];
            $data['poolmoney']       = $poolmoney;
            $data['showpool']        = $ranking['showpool']?1:0;//是否显示总奖金池
            $data['showchildpool']   = $ranking['showchildpool']?1:0;//是否显示子奖金池
            $data['showselfranking'] = $ranking['showselfranking']?1:0;//是否显示自己的排行
            $data['showlast']        = $ranking['showlast']?1:0;//是否显示上一个
            $data['sendstatus']      = $ranking['sendstatus']?1:0;//发放奖金是否开启
            $data['childprefix_addmonth']   = $ranking['childprefix_addmonth']?1:0;//子奖金池名称前加月份
            $data['nickname_center_hidden'] = $ranking['nickname_center_hidden']?1:0;//昵称中间隐藏
            if($log){
                $data['updatetime'] = time();
                $sql = Db::name('shoporder_ranking_log')->where('id',$log['id'])->update($data);
                $logid = $log['id'];
            }else{
                $data['aid']        = $aid;
                $data['month']      = $start_time;
                $data['createtime'] = time();
                $sql = Db::name('shoporder_ranking_log')->insertGetid($data);
                $logid = $sql;
            }
            if(!$sql){
                return ['status'=>0,'msg' =>'操作失败'];
            }

            foreach($givedata as $gk=>$gv){

                //子分红池奖金
                $childmoney = ($poolmoney+$changemoney)*$gv['ratio']/100;
                $gv['childmoney'] = $childmoney>0?round($childmoney,2):0;
                //子分红池赋值
                $givedata[$gk]['childmoney'] = $gv['childmoney'];

                $gv['mlist'] = [];
                if($mlist && $gv['num']>0){
                    //处理平分人数
                    $i = 1;
                    foreach($mlist as $mv){
                        if($i<=$gv['num'] && $mv['summoney']>=$gv['money']){
                            if($gv['moreave'] && !empty($gv['moreave'])){
                                //计算多段平分奖励
                                $moreavenum  = 0;//符合的多段平分人数
                                $moreaveratio= 0;//符合的多段平分比例
                                foreach($gv['moreave'] as $moreave){
                                    if($i>=$moreave['min'] && $i<=$moreave['max']){
                                        if($moreave['min'] == $moreave['max']){
                                            $moreavenum = 1;
                                        }else{
                                            $moreavenum = $moreave['max'] - $moreave['min']+1;
                                        }
                                        $moreaveratio = $moreave['ratio'];
                                    }
                                }
                                if($moreavenum<=0){
                                    $avgmoney = 0;
                                }else{
                                    $avgmoney = ($gv['childmoney']*$moreaveratio*0.01)/$moreavenum;
                                }
                                $mv['avgmoney'] = round($avgmoney,2);
                            }else{
                                //平均金额
                                $mv['avgmoney'] = round($gv['childmoney']/$gv['num'],2);
                            }
                            $gv['mlist'][] = $mv;
                        }else{
                            break;
                        }
                        $i++;
                    }
                    unset($mv);
                }
                //查询是否已添加过
                $did = Db::name('shoporder_ranking_log_detail')->where('logid',$logid)->where('key',$gk)->value('id');
                $data = [];
                $data['name']    = $gv['name'];
                $data['ratio']   = $gv['ratio'];
                $data['money']   = $gv['money'];
                $data['num']     = $gv['num'];
                $data['display'] = $gv['display'];
                $data['childmoney'] = $gv['childmoney'];
                $data['mlist']      = !empty($gv['mlist'])?json_encode($gv['mlist']):'';
                if($did){
                    $data['updatetime'] = time();
                    $sql = Db::name('shoporder_ranking_log_detail')->where('id',$did)->update($data);
                }else{
                    $data['aid']        = $aid;
                    $data['logid']      = $logid;
                    $data['key']        = $gk;
                    $data['createtime'] = time();
                    $sql = Db::name('shoporder_ranking_log_detail')->insertGetid($data);
                    $did = $sql;
                }

                //判断是否能发放奖励
                if($type == 2){
                    //发放奖金开启、且有发奖人员
                    if($ranking['sendstatus'] == 1){
                        $updata = [];
                        $updata['issend']   = 1;
                        $updata['sendtime'] = time();
                        if($gv['mlist']){
                            foreach($gv['mlist'] as &$gmv){
                                if($gmv['avgmoney']>0){
                                    //发放奖金
                                    \app\commons\Member::addcommission($aid,$gmv['mid'],0,$gmv['avgmoney'],'发放消费排行榜奖金');
                                }
                                $gmv['issend']   = 1;
                                $gmv['sendtime'] = time();
                            }
                            unset($gmv);
                            $updata['mlist']    = json_encode($gv['mlist']);
                        }
                        Db::name('shoporder_ranking_log_detail')->where('id',$did)->update($updata);
                    }
                }
            }
            unset($gv);

            $updata = [];
            //记录发放奖金时间
            if($type == 2 && $ranking['sendstatus'] == 1){
                $updata['issend']   = 1;
                $updata['sendtime'] = time();
            }
            //子分红池数据
            $updata['givedata'] = $givedata?json_encode($givedata):'';
            $up = Db::name('shoporder_ranking_log')->where('id',$logid)->update($updata);

            //删除多余数据
            if($log){
                Db::name('shoporder_ranking_log_detail')->where('logid',$logid)->where('key','>',$gk)->where('aid',$aid)->delete();
            }

            return ['status'=>1,'msg' =>'操作成功'];
        }
    }
    public function shoporderrankinglog($aid,$member,$pagenum){
        if(getcustom('shoporder_ranking')){
            //商城订单消费排行榜记录
            $month = strtotime(date('Y-m'));
            $where = array();
            $where[] = ['aid','=',$aid];
            $where[] = ['month','<',$month];
            $data = Db::name('shoporder_ranking_log')->where($where)->page($pagenum,20)->order('month desc,id desc')->field('id,month')->select()->toArray();
            if($data){
                foreach($data as &$v){
                    $v['month'] = date('Y-m',$v['month']);
                }
                unset($v);
            }
            $ranking = Db::name('shoporder_ranking_set')->where('aid',$aid)->where('status',1)->field('id,name')->find();
            $title  = $ranking && $ranking['name']?$ranking['name'].'记录':'记录';
            return ['status'=>1,'msg'=>'查询成功','data'=>$data,'title'=>$title];
        }
    }
    public function shoporderrankingdetail($aid,$member,$id,$key){
        if(getcustom('shoporder_ranking')){
            $month = strtotime(date('Y-m'));
            $where = [];
            $where[] = ['id','=',$id];
            $where[] = ['aid','=',$aid];
            $where[] = ['month','<',$month];
            $log = Db::name('shoporder_ranking_log')->where($where)->find();
            if(!$log){
                return ['status'=>0,'msg'=>'数据不存在'];
            }

            $ranking = Db::name('shoporder_ranking_set')->where('aid',$aid)->find();
            if(!$ranking){
                return ['status'=>0,'msg'=>'系统参数不存在'];
            }
            if($ranking['status'] !=1){
                return ['status'=>0,'msg'=>'排行榜暂未开启'];
            }

            $itemdata  = [];
            $itemst    = [];
            if($log['givedata']){
                $log['givedata'] = json_decode($log['givedata'],true);
                foreach($log['givedata'] as $gk=>$gv){
                    if($ranking['childprefix_addmonth']){
                        $prefixname = intval(date('m',$log['month']));
                        $gv['name'] = $prefixname.'月'.$gv['name'];
                    }
                    $itemdata[] = ['name'=>$gv['name'],'childmoney'=>$gv['childmoney']];
                    $itemst[]   = $gk;
                }
            }

            $where = [];
            $where[] = ['logid','=',$log['id']];
            $where[] = ['key','=',$key];
            $where[] = ['aid','=',$aid];
            $detail = Db::name('shoporder_ranking_log_detail')->where($where)->find();
            if(!$detail){
                return ['status'=>0,'msg'=>'分红数据不存在'];
            }
            if($detail['mlist']){
                $detail['mlist'] = json_decode($detail['mlist'],true);
                //昵称中间隐藏
                if($detail['mlist'] && $ranking['nickname_center_hidden'] == 1){
                    foreach($detail['mlist'] as &$dmv){
                        $len = mb_strlen($dmv['nickname']);
                        if($len<=1){
                            $dmv['nickname'] = $dmv['nickname'];
                        }else if($len==2){
                            $dmv['nickname'] = mb_substr($dmv['nickname'],0,1).'***';
                        }else if($len==3){
                            $dmv['nickname'] = mb_substr($dmv['nickname'],0,1).'***'.mb_substr($dmv['nickname'],-1);
                        }else if($len>=9){
                            $dmv['nickname'] = mb_substr($dmv['nickname'],0,3).'***'.mb_substr($dmv['nickname'],-3);
                        }else{
                            $dmv['nickname'] = mb_substr($dmv['nickname'],0,2).'***'.mb_substr($dmv['nickname'],-2);
                        }
                    }
                    unset($dmv);
                }
            }

            $rdata = [];
            $rdata['status']    = 1;
            $rdata['title']     = $ranking && $ranking['name']?$ranking['name'].'记录':'记录';
            $rdata['pic']       = $ranking && $ranking['pic']?$ranking['pic']:'';
            $rdata['poolmoney'] = $log['poolmoney']-$log['changemoney'];//总分红池
            $rdata['poolmoney'] = $rdata['poolmoney']>0?$rdata['poolmoney']:0;
            $rdata['itemdata']  = $itemdata;//子分红池名称组
            $rdata['itemst']    = $itemst;//子分红池key组
            $rdata['display']   = $detail?$detail['display']:0;//预估奖金是否显示
            $rdata['childmoney']= $detail?$detail['childmoney']:'';
            $rdata['money']     = $detail['money']>0?round($detail['money'],2):0;//达标消费差额
            $rdata['mlist']     = $detail?$detail['mlist']:'';
            $set = [
                'showpool'=>$ranking['showpool']?true:false,
                'showchildpool'=>$ranking['showchildpool']?true:false,
            ];
            $rdata['set']       = $set;
            return $rdata;
        }
    }
}