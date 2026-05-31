<?php


// +----------------------------------------------------------------------
// | 邀请返现
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class InviteCashback extends Common
{
    //列表
    public function index(){
        if(request()->isAjax()){
            $page = input('param.page');
            $limit = input('param.limit');
            if(input('param.field') && input('param.order')){
                $order = input('param.field').' '.input('param.order');
            }else{
                $order = 'sort desc,id desc';
            }
            $where = array();
            $where[] = ['aid','=',aid];
            $where[] = ['bid','=',bid];
            if(input('param.name')) $where[] = ['name','like','%'.input('param.name').'%'];
            $count = 0 + Db::name('invite_cashback')->where($where)->count();
            $data = Db::name('invite_cashback')->where($where)->page($page,$limit)->order($order)->select()->toArray();
            foreach($data as $k=>$v){
                if($v['starttime'] > time()){
                    $data[$k]['status'] = '<button class="layui-btn layui-btn-sm" style="background-color:#888">未开始</button>';
                }elseif($v['endtime'] < time()){
                    $data[$k]['status'] = '<button class="layui-btn layui-btn-sm layui-btn-disabled">已结束</button>';
                }else{
                    $data[$k]['status'] = '<button class="layui-btn layui-btn-sm" style="background-color:#5FB878">进行中</button>';
                }
                $data[$k]['starttime'] = date('Y-m-d H:i',$v['starttime']);
                $data[$k]['endtime'] = date('Y-m-d H:i',$v['endtime']);
            }
            return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
        }
        return View::fetch();
    }
    //编辑
    public function edit(){
        if(input('param.id')){
            $info = Db::name('invite_cashback')->where('aid',aid)->where('bid',bid)->where('id',input('param.id/d'))->find();
            $info['starttime'] = date('Y-m-d H:i:s',$info['starttime']);
            $info['endtime'] = date('Y-m-d H:i:s',$info['endtime']);
            $info['invite_cashbak_data'] = json_decode($info['invite_cashbak_data'],true);
            $info['invite_cashbak_count'] = count($info['invite_cashbak_data']);
            
        }else{
            $info = array('id'=>'','starttime'=>date('Y-m-d 00:00:00'),'endtime'=>date('Y-m-d 00:00:00',time()+7*86400),'gettj'=>'-1','sort'=>0,'fwtype'=>0,'type'=>1);
        }
        $info['gettj'] = explode(',',$info['gettj']);
        View::assign('info',$info);
        $default_cid = Db::name('member_level_category')->where('aid',aid)->where('isdefault', 1)->value('id');
        $default_cid = $default_cid ? $default_cid : 0;
        $memberlevel = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->order('sort,id')->select()->toArray();
        View::assign('memberlevel',$memberlevel);

        $categorydata = array();
        if($info && $info['categoryids']){
            $categorydata = Db::name('shop_category')->where('aid',aid)->where('id','in',$info['categoryids'])->order('sort desc,id')->select()->toArray();
        }
        View::assign('categorydata',$categorydata);
        $productdata = array();
        if($info && $info['productids']){
            $productdata = Db::name('shop_product')->where('aid',aid)->where('id','in',$info['productids'])->order(Db::raw('field(id,'.$info['productids'].')'))->select()->toArray();
        }
        View::assign('productdata',$productdata);
        return View::fetch();
    }

    //保存
    public function save(){
        $info = input('post.info/a');
        $info['gettj'] = implode(',',$info['gettj']);
        $info['starttime'] = strtotime($info['starttime']);
        $info['endtime'] = strtotime($info['endtime']);
        $info['invite_cashbak_data'] = jsonEncode(input('post.invite_cashbak_data/a'));
        
        //开启限额
        $cashback_max = 0;
        //开启选择受益人
        $cashback_receiver = 0;
        //受益人限额仅限单个商品可用
        if($cashback_receiver || $cashback_max){
            $goods_multiple_max = $info['goods_multiple_max'];
            $receiver_type = $info['receiver_type'];
            $fwtype = $info['fwtype'];
            $productids = explode(',',$info['productids']);
            //开启受益人为参与活动的人或者限制倍数限制指定一个商品
            if($receiver_type ==2){
                if($fwtype !=2 || count($productids) != 1){
                    return json(['status'=>0,'msg'=>'开启受益人和限额仅限单个指定商品']);
                }
                //判定当前活动商品不能同时存在其它开始的活动商品中
                $now_time = time();
                //$where[] = ['starttime','<=',$now_time];
                $where[] = ['endtime','>',$now_time];
                //$where[] = ['productids','=',$info['productids']];
                if($info['id']){
                    $where[] = ['id','<>',$info['id']];
                }
                $where_pro = 'FIND_IN_SET("'.$info['productids'].'", productids) or fwtype = 0 or fwtype = 1';
                $goods_data = Db::name('invite_cashback')->where($where)->whereRaw($where_pro)->select()->toArray();
                if($goods_data){
                    return json(['status'=>0,'msg'=>'当前商品已存在其它活动']);
                }
            }
        }

        if($info['id']){
            Db::name('invite_cashback')->where('aid',aid)->where('bid',bid)->where('id',$info['id'])->update($info);
            \app\commons\System::plog('修改邀请返现活动'.$info['id']);
        }else{
            $info['aid'] = aid;
            $info['bid'] = bid;
            $info['createtime'] = time();
            $id = Db::name('invite_cashback')->insertGetId($info);
            \app\commons\System::plog('添加邀请返现活动'.$id);
        }
        return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
    }

    //删除
    public function del(){
        $ids = input('post.ids/a');
        Db::name('invite_cashback')->where('aid',aid)->where('bid',bid)->where('id','in',$ids)->delete();
        \app\commons\System::plog('删除邀请返现活动'.implode(',',$ids));
        return json(['status'=>1,'msg'=>'删除成功']);
    }

    //参与会员
    public function record(){
        if(request()->isAjax()){
            $page = input('param.page');
            $limit = input('param.limit');
            if(input('param.field') && input('param.order')){
                $order = 'cashback_member.'.input('param.field').' '.input('param.order');
            }else{
                $order = 'cashback_member.id desc';
            }
            $where = [];
            $where[] = ['cashback_member.aid','=',aid];
            if(input('param.id/d')) $where[] = ['cashback_member.cashback_id','=',input('param.id/d')];
            if(input('param.mid')) $where[] = ['cashback_member.mid','=',input('param.mid/d')];
            if(input('param.nickname')) $where[] = ['member.nickname','like','%'.input('param.nickname').'%'];
            if(input('param.ctime') ){
                $ctime = explode(' ~ ',input('param.ctime'));
                $where[] = ['cashback_member.create_time','>=',strtotime($ctime[0])];
                $where[] = ['cashback_member.create_time','<',strtotime($ctime[1]) + 86400];
            }

            $count = 0 + Db::name('cashback_member')->alias('cashback_member')->join('member member','cashback_member.mid=member.id')->where($where)->count();
            $data = Db::name('cashback_member')->alias('cashback_member')->field('cashback_member.*,member.nickname,member.headimg')->join('member member','cashback_member.mid=member.id')->where($where)->page($page,$limit)->order($order)->select()->toArray();
            return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
        }
        if(input('param.id/d')){
            $cashback = Db::name('cashback')->where('id',input('param.id/d'))->find();
        }
        View::assign('cashback',$cashback);
        return View::fetch();
    }
    //参与会员记录
    public function recordLog(){
        if(request()->isAjax()){
            $page = input('param.page');
            $limit = input('param.limit');
            if(input('param.field') && input('param.order')){
                $order = 'cashback_member.'.input('param.field').' '.input('param.order');
            }else{
                $order = 'cashback_member.id desc';
            }
            $where = [];
            $where[] = ['cashback_member.aid','=',aid];
            if(input('param.cashback_id/d')) $where[] = ['cashback_member.cashback_id','=',input('param.cashback_id/d')];
            if(input('param.pro_id/d')) $where[] = ['cashback_member.pro_id','=',input('param.pro_id/d')];
            if(input('param.mid')) $where[] = ['cashback_member.mid','=',input('param.mid/d')];
            if(input('param.ctime') ){
                $ctime = explode(' ~ ',input('param.ctime'));
                $where[] = ['cashback_member.create_time','>=',strtotime($ctime[0])];
                $where[] = ['cashback_member.create_time','<',strtotime($ctime[1]) + 86400];
            }

            $count = 0 + Db::name('cashback_member_log')->alias('cashback_member')->join('member member','cashback_member.mid=member.id')->where($where)->count();
            $data = Db::name('cashback_member_log')->alias('cashback_member')->field('cashback_member.*,member.nickname,member.headimg')->join('member member','cashback_member.mid=member.id')->where($where)->page($page,$limit)->order($order)->select()->toArray();
            return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
        }
        return View::fetch();
    }
}