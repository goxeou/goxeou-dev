<?php


// +----------------------------------------------------------------------
// | 分享设置
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class DesignerShare extends Common
{
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
			$where[] = ['bid','=',bid];
			if(input('param.name')) $where[] = ['name','=',input('param.name')];
			if(input('param.platform')) $where[] = ['platform','=',input('param.platform')];
			if(input('param.indexurl')) $where[] = ['indexurl','like','%'.input('param.indexurl').'%'];
			if(input('param.indexurlname')) $where[] = ['indexurlname','like','%'.input('param.indexurlname').'%'];
			if(input('?param.status') && input('param.status')!=='') $where[] = ['status','=',input('param.status')];
			$count = 0 + Db::name('designer_share')->where($where)->count();
			$data = Db::name('designer_share')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			foreach($data as $k=>$v){
				if($v['platform'] == 'all'){
					$data[$k]['platform'] = '全部';
				}else{
					$data[$k]['platform'] = getplatformname($v['platform']);
				}
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
    }
	//编辑
	public function edit(){
		if(input('param.id')){
			$info = Db::name('designer_share')->where('aid',aid)->where('bid',bid)->where('id',input('param.id/d'))->find();
		}else{
			$info = array(
				'id'=>'',
				'platform'=>'all'
			);
		}
		View::assign('info',$info);
		return View::fetch();
	}
	//保存
	public function save(){
		$info = input('post.info/a');
		if($info['id']){
			Db::name('designer_share')->where('aid',aid)->where('bid',bid)->where('id',$info['id'])->update($info);
			\app\commons\System::plog('编辑分享设置'.$info['id']);
		}else{
			$info['aid'] = aid;
			$info['bid'] = bid;
			$info['createtime'] = time();
			$id = Db::name('designer_share')->insertGetId($info);
			\app\commons\System::plog('添加分享设置'.$id);
		}
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}
	//改状态
	public function setst(){
		$st = input('post.st/d');
		$ids = input('post.ids/a');
		Db::name('designer_share')->where('aid',aid)->where('bid',bid)->where('id','in',$ids)->update(['status'=>$st]);
		\app\commons\System::plog('分享设置改状态'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//删除
	public function del(){
		$ids = input('post.ids/a');
		Db::name('designer_share')->where('aid',aid)->where('bid',bid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('删除分享设置'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
}
