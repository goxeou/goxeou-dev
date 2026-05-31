<?php


// +----------------------------------------------------------------------
// | 拼团商城 机器人管理
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class LuckyCollageJiqiren extends Common
{
	//分类列表
    public function index(){
		if(request()->isAjax()){
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
				$order = 'id desc';
			}
			$where = [];
			$where[] = ['aid','=',aid];
			$data = [];
			$data = Db::name('lucky_collage_jiqilist')->where('aid',aid)->order($order)->select()->toArray();
			return json(['code'=>0,'msg'=>'查询成功','count'=>count($data),'data'=>$data]);
		}
        $this->defaultSet();
		return View::fetch();
    }
	//编辑
	public function edit(){
		if(input('param.id')){
			$info = Db::name('lucky_collage_jiqilist')->where('aid',aid)->where('id',input('param.id/d'))->find();
		}else{
			$info = array('id'=>'');
		}
		if(input('param.pid')) $info['pid'] = input('param.pid');
		$pcatelist = Db::name('lucky_collage_jiqilist')->where('aid',aid)->where('id','<>',$info['id'])->order('sort desc,id')->select()->toArray();
		View::assign('info',$info);
		View::assign('pcatelist',$pcatelist);
		return View::fetch();
	}
	//保存
	public function save(){
		$info = input('post.info/a');
		if($info['id']){
			Db::name('lucky_collage_jiqilist')->where('aid',aid)->where('id',$info['id'])->update($info);
			\app\commons\System::plog('修改拼团分类'.$info['id']);
		}else{
			$info['aid'] = aid;
			$info['createtime'] = time();
			$id = Db::name('lucky_collage_jiqilist')->insertGetId($info);
			\app\commons\System::plog('添加拼团机器人'.$id);
		}
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}
	//删除
	public function del(){
		$ids = input('post.ids/a');
		Db::name('lucky_collage_jiqilist')->where('aid',aid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('删除拼团机器人'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
    function defaultSet(){
        $set = Db::name('lucky_collage_sysset')->where('aid',aid)->find();
        if(!$set){
            Db::name('lucky_collage_sysset')->insert(['aid'=>aid]);
        }
    }
}