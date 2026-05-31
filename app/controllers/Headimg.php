<?php


//custom_file(headimg)
// +----------------------------------------------------------------------
// | 头像
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class Headimg extends Common
{	
	public function initialize(){
		parent::initialize();
		if(bid > 0) showmsg('无操作权限');
	}
	//列表
    public function index()
    {
        Db::name('headimg_upload')->where('aid',aid)->whereNull('url')->delete();
        if(request()->isAjax()){
            $page = input('param.page');
            $limit = input('param.limit');
            $where = [];
//            if(input('param.gid') != '-1'){
//                $where[] = ['gid','=',1];
//            }
            if(input('param.keyword') != ''){
                $where[] = ['name','like','%'.input('param.keyword').'%'];
            }
            if(input('param.sort')==1){
                $sort = 'createtime asc';
            }elseif(input('param.sort')==3){
                $sort = 'name asc';
            }elseif(input('param.sort')==4){
                $sort = 'name desc';
            }else{
                $sort = 'createtime desc';
            }
            $count = Db::name('headimg_upload')->field('id,name,url,type,size,createtime,width,height,dir')->where('aid',aid)->where('isdel',0)->where('platform','ht')->where($where)->where('type','<>','pem')->count();
            $data = Db::name('headimg_upload')->field('id,name,url,type,size,createtime,width,height,dir')->where('aid',aid)->where('isdel',0)->where('platform','ht')->where($where)->where('type','<>','pem')->order($sort)->page($page,$limit)->select()->toArray();

            return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
        }
        return View::fetch();
    }
	//编辑
	public function edit(){

			$info = array(
				'id'=>'',
				'name'=>'',
				'starttime'=>time(),
				'endtime'=>time()+30*86400,
				'status'=>1,
				'type'=>0
			);

		return View::fetch();
	}
	//保存
	public function save(){
		$info = input('post.info/a');
		$pics = explode(',',$info['pics']);
		foreach ($pics as $picname) {
            if(empty($picname)) continue;
		    $pic = Db::name('admin_upload')->where('url',$picname)->find();
		    unset($pic['id']);
		    unset($pic['bid']);
		    unset($pic['hash']);
		    unset($pic['channels_file_id']);
            unset($pic['other_param']);
            unset($pic['old_url']);
            $pic['aid'] = aid;
            $pic['createtime'] = time();
            $id = Db::name('headimg_upload')->insertGetId($pic);
        }

        \app\commons\System::plog('添加头像');

		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}
	//删除
	public function del(){
		$ids = input('post.ids/a');
		Db::name('headimg_upload')->where('aid',aid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('删除头像'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
}