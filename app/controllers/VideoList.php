<?php


// +----------------------------------------------------------------------
// | 视频管理，商品和视频组件可直接选择链接
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class VideoList extends Common
{
    //分类列表
    public function index(){
        $typelist = $this->typelist(true);
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
            $where[] = ['bid','=',bid];
            if(is_numeric(input('param.type'))) $where[] = ['type','=',input('param.type/d')];
            if(input('param.name')) $where[] = ['name','like','%'.input('param.name').'%'];
            $count = 0 + Db::name('video_list')->where($where)->count();
            $data = Db::name('video_list')->where($where)->page($page,$limit)->order($order)->select()->toArray();
            foreach($data as $k=>$v){
                $data[$k]['type_txt'] = isset($typelist[$v['type']])?$typelist[$v['type']]['name']:$v['type'];
            }
            return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
        }
        $param = input('param.');
        if(empty($param)) $param = ['_t'=>time()];
        View::assign('typelist',$typelist);
        View::assign('datawhere',json_encode($param));
        return View::fetch();
    }
    //编辑
    public function edit(){
        $ext = [];
        if(input('param.id')){
            $info = Db::name('video_list')->where('aid',aid)->where('bid',bid)->where('id',input('param.id/d'))->find();
            if($info['ext_param']){
                $ext = json_decode($info['ext_param'],true);
            }
        }else{
            $info = array('id'=>'','type'=>0);
        }
        if(empty($ext['feedtype'])){
            $ext['feedtype'] = 0;
        }
        View::assign('info',$info);
        View::assign('ext',$ext);
        View::assign('typelist',$this->typelist());
        return View::fetch();
    }
    public function typelist($iskey=false){
        $types = [
            ['type'=>0,'name'=>'本地视频'],
            ['type'=>1,'name'=>'微信视频号']
        ];
        if($iskey){
            $newtype = [];
            foreach ($types as $k=>$v){
                $newtype[$v['type']] = $v;
            }
            return $newtype;
        }
        return $types;
    }
    //保存
    public function save(){
        $info = input('post.info/a');
        $ext = input('post.ext/a');
        if($ext){
            $info['ext_param'] = json_encode($ext);
        }
        if($info['id']){
            Db::name('video_list')->where('aid',aid)->where('bid',bid)->where('id',$info['id'])->update($info);
            \app\commons\System::plog('编辑视频'.$info['id']);
        }else{
            $info['aid'] = aid;
            $info['bid'] = bid;
            $info['createtime'] = time();
            $id = Db::name('video_list')->insertGetId($info);
            \app\commons\System::plog('添加视频'.$id);
        }
        return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
    }
    //删除
    public function del(){
        $ids = input('post.ids/a');
        Db::name('video_list')->where('aid',aid)->where('bid',bid)->where('id','in',$ids)->delete();
        \app\commons\System::plog('删除视频'.implode(',',$ids));
        return json(['status'=>1,'msg'=>'删除成功']);
    }

    public function choosevideolist(){
        if(request()->isPost()){
            $data = Db::name('video_list')->where('aid',aid)->where('bid',bid)->where('id',input('post.id/d'))->find();
            if($data['ext_param']){
                $ext = json_decode($data['ext_param'],true);
                unset($data['ext_param']);
                $data = array_merge($data,$ext);
            }
            return json(['status'=>1,'msg'=>'查询成功','data'=>$data]);
        }
        $typelist = $this->typelist(true);
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
            $where[] = ['bid','=',bid];
            if(is_numeric(input('param.type'))) $where[] = ['type','=',input('param.type/d')];
            if(input('param.name')) $where[] = ['name','like','%'.input('param.name').'%'];
            $count = 0 + Db::name('video_list')->where($where)->count();
            $data = Db::name('video_list')->where($where)->page($page,$limit)->order($order)->select()->toArray();
            foreach($data as $k=>$v){
                $data[$k]['type_txt'] = isset($typelist[$v['type']])?$typelist[$v['type']]['name']:$v['type'];
            }
            return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
        }
        $param = input('param.');
        if(empty($param)) $param = ['_t'=>time()];
        View::assign('typelist',$typelist);
        View::assign('datawhere',json_encode($param));
        return View::fetch();
    }
}