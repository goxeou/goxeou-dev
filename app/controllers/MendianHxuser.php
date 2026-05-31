<?php
// +----------------------------------------------------------------------
// | 门店核销员管理   custom_file(mendian_upgrade)
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class MendianHxuser extends Common
{
	
	//列表
    public function index(){
		if(request()->isAjax()){
			$page = input('param.page');
			$mdid = input('param.mdid');
			$limit = input('param.limit');
			$where = [];
			$where[] = ['aid','=',aid];
			$where[] = ['aid','=',aid];
			if(input('param.name')) $where[] = ['name','like','%'.input('param.name').'%'];
			if(input('?param.status') && input('param.status')!=='') $where[] = ['status','=',input('param.status')];
			if($mdid) 	$where[] = ['mdid','=',$mdid];
			$count = 0 + Db::name('mendian_hexiaouser')->where($where)->count();
			$data = Db::name('mendian_hexiaouser')->where($where)->page($page,$limit)->order($order)->select()->toArray(); 
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
    }

	//核销人员修改状态
	public function setst(){
		$st = input('post.st/d');
		$ids = input('post.ids/a');
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['id','in',$ids];
		Db::name('mendian_hexiaouser')->where($where)->update(['status'=>$st]);
		return json(['status'=>1,'msg'=>'操作成功']);
	}

	//编辑
	public function edit(){
		$mdid = input('param.mdid');
		if(input('param.id')){
			$info = Db::name('mendian_hexiaouser')->where('aid',aid)->where('id',input('param.id/d'))->find();
		}else{
			$info = array('id'=>'');
		}
		View::assign('mdid',$mdid);
		View::assign('info',$info);
		return View::fetch();
	}
	public function save(){
		$info = input('post.info/a');
		$member = Db::name('member')->where('id',$info['mid'])->find();
		if(!$member){	return json(['status'=>0,'msg'=>'该会员不存在']);}

		$hasun = Db::name('mendian_hexiaouser')->where('aid',aid)->where('id','<>',$info['id'])->where('mid',$info['mid'])->find();
		if($hasun){
			return json(['status'=>0,'msg'=>'该会员已添加']);
		}
		if($info['id']){
			//$member = Db::name('member')->where('id',$info['mid'])->find();
			//$info['headimg'] = $member['headimg'];
			Db::name('mendian_hexiaouser')->where('aid',aid)->where('id',$info['id'])->update($info);
			\app\commons\System::plog('编辑门店核销人员'.$info['id']);
		}else{
			$info['aid'] = aid;
			$info['createtime'] = time();
			$info['headimg'] = $member['headimg'];
			$info['nickname'] = $member['nickname'];
			$id = Db::name('mendian_hexiaouser')->insertGetId($info);
			\app\commons\System::plog('添加门店核销人员'.$id);
		}
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}

	//删除
	public function del(){
		$ids = input('post.ids/a');
		if(!$ids) $ids = array(input('post.id/d'));
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['id','in',$ids];
		Db::name('mendian_hexiaouser')->where($where)->delete();
		\app\commons\System::plog('门店核销人员删除'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
}
