<?php


// +----------------------------------------------------------------------
// | 门店管理
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class Shebei extends Common
{
	//列表
    public function index(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = 'shebei.'.input('param.field').' '.input('param.order');
			}else{
				$order = 'shebei.id desc';
			}
			$where = [];
			$where[] = ['shebei.aid','=',aid];
		
			if(input('param.name')) $where[] = ['name','like','%'.input('shebei.param.name').'%'];
			if(input('?param.status') && input('param.status')!=='') $where[] = ['shebei.status','=',input('param.status')];
			if(input('param.mid')) 	$where[] = ['shebei.mid','=',input('param.mid')];
			if(input('param.ordernum')) 	$where[] = ['shebei.ordernum','=',input('param.ordernum')];
			if(input('param.nickname')) $where[] = ['member.nickname|member.realname','like','%'.input('param.nickname').'%'];
			$count = 0 + Db::name('shebei')->alias('shebei')->field('member.nickname,member.headimg,shebei.*')->join('member member','member.id=shebei.mid')->where($where)->count();
			$data = Db::name('shebei')->alias('shebei')->field('member.nickname,member.headimg,shebei.*')->join('member member','member.id=shebei.mid')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			
			foreach($data as $k=>$vo){
				$member = Db::name('member')->where('id',$vo['mid'])->find();
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
    }
	//编辑
	public function edit(){
		if(input('param.id')){
			$info = Db::name('shebei')->where('aid',aid)->where('id',input('param.id/d'))->find();
		}
		View::assign('info',$info);
		return View::fetch();
	}
	public function save(){
		$info = input('post.info/a');
		$member = Db::name('member')->where('id',$info['mid'])->find();
	    if(empty($member)){
			return json(['status'=>0,'msg'=>'该会员不存在']);
		}
		if($info['id']){
			 Db::name('shebei')->where('aid',aid)->where('id',$info['id'])->update($info);
			\app\commons\System::plog('编辑分红股权'.$info['id']);
		}else {
		    $info['aid'] = aid;
			$info['createtime'] = time();
			$id = Db::name('shebei')->insertGetId($info);
			\app\commons\System::plog('添加分红股权'.$id);
		}
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}

	public function del(){
		$ids = input('post.ids/a');
		Db::name('shebei')->where('aid',aid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('删除分红股权'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
}
