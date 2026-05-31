<?php


// +----------------------------------------------------------------------
// | 短视频分类
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;
use think\facade\Log;

class ShortvideoCategory extends Common
{
	//分类列表
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
            if(bid==0){
                if(input('param.bid')){
                    $where[] = ['bid','=',input('param.bid')];
                }elseif(input('param.showtype')==2){
                    $where[] = ['bid','<>',0];
                }elseif(input('param.showtype')=='all'){
                    $where[] = ['bid','>=',0];
                }else{
                    $where[] = ['bid','=',0];
                }
            }else{
                $where[] = ['bid','=',bid];
            }
            if(input('param.name')) $where[] = ['name','like',input('param.name')];
            if(input('param.status') && input('param.status')!=='') $where[] = ['status','=',input('param.status')];

            $count = 0 + Db::name('shortvideo_category')->where($where)->count();
            $data = Db::name('shortvideo_category')->where($where)->page($page,$limit)->order($order)->select()->toArray();
            foreach($data as $k=>$v){
            }
            return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
        }
        $this->defaultSet();
        return View::fetch();
    }
    //编辑分类
    public function edit(){
        if(input('param.id')){
            $info = Db::name('shortvideo_category')->where('aid',aid)->where('id',input('param.id/d'))->find();
            if(!$info) showmsg('分类不存在');
            if(bid != 0 && $info['bid']!=bid) showmsg('无权限操作');
        }
        //dump($info);
        $typelist = db('shortvideo_category')->where('aid',aid)->where('status',1)->column('id,name');
        View::assign('info',$info);
        return View::fetch();
    }
    //保存分类
    public function save(){
        if(input('post.id')){
            $type = Db::name('shortvideo_category')->where('aid',aid)->where('id',input('post.id/d'))->find();
            if(!$type) showmsg('分类不存在');
            if(bid != 0 && $tid['bid']!=bid) showmsg('无权限操作');
        }
        $info = input('post.info/a');

        if($type){
            Db::name('shortvideo_category')->where('aid',aid)->where('id',$type['id'])->update($info);
            $tid = $type['id'];
            \app\commons\System::plog('短视频编辑'.$tid);
        }else{
            $info['aid'] = aid;
            $info['bid'] = bid;
            $info['createtime'] = time();
            $vid = Db::name('shortvideo_category')->insertGetId($info);
            \app\commons\System::plog('短视频分类编辑'.$tid);
        }
        return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
    }
    //改状态
    public function setst(){
        $st = input('post.st/d');
        $ids = input('post.ids/a');
        $where = [];
        $where[] = ['aid','=',aid];
        $where[] = ['id','in',$ids];
        if(bid !=0){
            $where[] = ['bid','=',bid];
        }
        Db::name('shortvideo_category')->where($where)->update(['status'=>$st]);
        \app\commons\System::plog('短视频分类改状态'.implode(',',$ids));
        return json(['status'=>1,'msg'=>'操作成功']);
    }
    //删除
    public function del(){
        $ids = input('post.ids/a');
        if(!$ids) $ids = array(input('post.id/d'));
        $where = [];
        $where[] = ['aid','=',aid];
        $where[] = ['id','in',$ids];
        if(bid !=0){
            $where[] = ['bid','=',bid];
        }
        Db::name('shortvideo_category')->where($where)->delete();
        \app\commons\System::plog('短视频分类删除'.implode(',',$ids));
        return json(['status'=>1,'msg'=>'删除成功']);
    }
    function defaultSet(){
        $set = Db::name('shortvideo_sysset')->where('aid',aid)->find();
        if(!$set){
            Db::name('shortvideo_sysset')->insert(['aid'=>aid]);
        }
    }
}