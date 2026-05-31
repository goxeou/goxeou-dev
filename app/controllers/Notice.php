<?php


// +----------------------------------------------------------------------
// | 系统消息
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class Notice extends Common
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
			$where = array();
			$where[] = ['aid','=',aid];
			$where[] = ['uid','=',uid];
			if(input('param.title')) $where[] = ['title','like','%'.input('param.title').'%'];
			if(input('?param.status') && input('param.status')!=='') $where[] = ['status','=',input('param.status')];
			$count = 0 + Db::name('admin_notice')->where($where)->count();
			$data = Db::name('admin_notice')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
    }
	//查看
	public function detail(){
		if(input('param.id')){
			$info = Db::name('admin_notice')->where('aid',aid)->where('uid',uid)->where('id',input('param.id/d'))->find();
		}else{
			$info = array('id'=>'');
		}
		Db::name('admin_notice')->where('aid',aid)->where('uid',uid)->where('id',$info['id'])->update(['isread'=>1,'readtime'=>time()]);
		View::assign('info',$info);
		return View::fetch();
	}
	//改状态
	public function allread(){
		Db::name('admin_notice')->where('aid',aid)->where('uid',uid)->where('uid',$this->uid)->where('isread',0)->update(['isread'=>1,'readtime'=>time()]);
	}
	//删除
	public function del(){
		$ids = input('post.ids/a');
		Db::name('admin_notice')->where('aid',aid)->where('uid',uid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('通知公告删除'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
}
