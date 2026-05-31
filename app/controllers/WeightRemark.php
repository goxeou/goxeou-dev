<?php
// +----------------------------------------------------------------------
// | custom_file(product_weight) 称重商品订单
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;
class WeightRemark extends Common
{
    //列表
    public function index(){
        if(request()->isAjax()){
            $page = input('param.page');
            $limit = input('param.limit');
            if(input('param.field') && input('param.order')){
                $order = input('param.field').' '.input('param.order');
            }else{
                $order = 'id desc';
            }
            $where = [];
            $where[] = ['aid','=',aid];
            $bid = bid;
            if(bid>0){
                $where[] = Db::raw("bid={$bid} OR find_in_set({$bid},`ext_bids`)");
            }
            if(input('param.keyword')) $where[] = ['name','like','%'.input('param.keyword').'%'];
            if(input('param.ctime') ){
                $ctime = explode(' ~ ',input('param.ctime'));
                $where[] = ['createtime','>=',strtotime($ctime[0])];
                $where[] = ['createtime','<',strtotime($ctime[1]) + 86400];
            }
            $count = 0 + Db::name('sh_weight_remark')->where($where)->count();
            $list = Db::name('sh_weight_remark')->where($where)->page($page,$limit)->order($order)->select()->toArray();
            foreach ($list as $k=>$v){
                $isedit = 0;
                if(bid==0 || $v['bid']==bid){
                    $isedit = 1;
                }
                $bnames = '';
                if($v['ext_bids']){
                    $ext_bids = explode(',',$v['ext_bids']);
                    $bnames = Db::name('business')->where('aid',aid)->where('id','in',$ext_bids)->column('name');
                    if($bnames){
                        $bnames = implode(',',$bnames);
                    }
                }
                $list[$k]['ext_bnames'] = $bnames??'';
                $list[$k]['isedit'] = $isedit;
            }
            return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$list]);
        }
        return View::fetch();
    }
    public function getList(){
        $list = Db::name('sh_weight_remark')->where('aid',aid)->where('bid',bid)->select()->toArray();
        return json(['status'=>1,'data'=>$list]);
    }
    //编辑
    public function edit(){
        if(input('param.id')){
            $where = [];
            $where[] = ['aid','=',aid];
            if(bid>0){
                $where[] = ['bid','=',bid];
            }
            $info = Db::name('sh_weight_remark')->where($where)->where('id',input('param.id/d'))->find();
        }else {
            $info = array('id' => '');
        }
        $businesslist = [];
        if(bid==0){
            $businesslist = Db::name('business')->where('aid',aid)->select()->toArray();
        }
        View::assign('businesslist',$businesslist);
        View::assign('info',$info);
        View::assign('bid',bid);
        return View::fetch();
    }
    //保存
    public function save(){
        $info = input('post.info/a');
        if(bid>0){
            $info['ext_bids'] = '';
        }
        if($info['id']){
            $where = [];
            $where[] = ['aid','=',aid];
            if(bid>0){
                $where[] = ['bid','=',bid];
            }
            $info['updatetime'] = time();
            $res = Db::name('sh_weight_remark')->where($where)->where('id',$info['id'])->update($info);
            if($res){
                \app\commons\System::plog('称重订单备注预置修改');
            }else{
                return json(['status'=>0,'msg'=>'您不能修改该数据']);
            }
        }else{
            $info['aid'] = aid;
            $info['bid'] = bid;
            $info['createtime'] = time();
            $id = Db::name('sh_weight_remark')->insertGetId($info);
            \app\commons\System::plog('称重订单备注预置添加');
        }
        return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
    }
    //删除
    public function del(){
        $ids = input('post.ids/a');
        $where = [];
        $where[] = ['aid','=',aid];
        if(bid>0){
            $where[] = ['bid','=',bid];
        }
        $res = Db::name('sh_weight_remark')->where($where)->where('id','in',$ids)->delete();
        if($res){
            \app\commons\System::plog('称重订单备注预置删除ids='.implode(',',$ids));
            return json(['status'=>1,'msg'=>'删除成功']);
        }else{
            return json(['status'=>0,'msg'=>'您不能删除该数据']);
        }
    }

    public function chooseremark(){
        if(request()->isPost()){
            $where = [];
            $where[] = ['aid','=',aid];
            $bid = bid;
            if(bid>0){
                $where[] = Db::raw("bid={$bid} OR find_in_set({$bid},`ext_bids`)");
            }
            $data = Db::name('sh_weight_remark')->where($where)->where('id',input('post.id/d'))->find();
            return json(['status'=>1,'msg'=>'查询成功','data'=>$data]);
        }
        return View::fetch();
    }
}
