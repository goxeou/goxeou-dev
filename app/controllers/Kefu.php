<?php


// +----------------------------------------------------------------------
// | 商城 商品服务
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class Kefu extends Common
{
    public function initialize(){
		parent::initialize();
		//if(bid > 0) showmsg('无访问权限');
	}
	
    public function fastreply(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
				$order = 'sort desc,id';
			}
			$where = array();
			$where[] = ['aid','=',aid];
			$where[] = ['bid','=',bid];
			if(input('param.name')) $where[] = ['name','like','%'.input('param.name').'%'];
			if(input('?param.status') && input('param.status')!=='') $where[] = ['status','=',input('param.status')];
			//dump($where);
			$count = 0 + Db::name('kefu_fastreply')->where($where)->count();
			$data = Db::name('kefu_fastreply')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
    }
	//编辑
	public function fastreplyedit(){
		if(input('param.id')){
			$info = Db::name('kefu_fastreply')->where('aid',aid)->where('id',input('param.id/d'))->find();
		}else{
			$info = array('id'=>'');
		}
		$pcatelist = Db::name('kefu_fastreply')->where('aid',aid)->order('sort desc,id')->select()->toArray();
		View::assign('info',$info);
		return View::fetch();
	}
	//保存
	public function fastreplysave(){
		$info = input('post.info/a');
		if($info['id']){
			Db::name('kefu_fastreply')->where('aid',aid)->where('id',$info['id'])->update($info);
			\app\commons\System::plog('编辑快捷回复'.$info['id']);
		}else{
			$info['aid'] = aid;
			$info['bid'] = bid;
			$info['createtime'] = time();
			$id = Db::name('kefu_fastreply')->insertGetId($info);
			\app\commons\System::plog('添加快捷回复'.$id);
		}
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}
	//删除
	public function fastreplydel(){
		$ids = input('post.ids/a');
		Db::name('kefu_fastreply')->where('aid',aid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('快捷回复删除'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	
	
	
	
	public function question(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
				$order = 'sort desc,id';
			}
			$where = array();
			$where[] = ['aid','=',aid];
			$where[] = ['bid','=',bid];
			if(input('param.name')) $where[] = ['name','like','%'.input('param.name').'%'];
			if(input('?param.status') && input('param.status')!=='') $where[] = ['status','=',input('param.status')];
			//dump($where);
			$count = 0 + Db::name('kefu_question')->where($where)->count();
			$data = Db::name('kefu_question')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
    }
	//编辑
	public function questionedit(){
		if(input('param.id')){
			$info = Db::name('kefu_question')->where('aid',aid)->where('id',input('param.id/d'))->find();
		}else{
			$info = array('id'=>'');
		}
		$pcatelist = Db::name('kefu_question')->where('aid',aid)->order('sort desc,id')->select()->toArray();
		View::assign('info',$info);
		return View::fetch();
	}
	//保存
	public function questionsave(){
		$info = input('post.info/a');
		if($info['id']){
			Db::name('kefu_question')->where('aid',aid)->where('id',$info['id'])->update($info);
			\app\commons\System::plog('编辑常见问题'.$info['id']);
		}else{
			$info['aid'] = aid;
			$info['bid'] = bid;
			$info['createtime'] = time();
			$id = Db::name('kefu_question')->insertGetId($info);
			\app\commons\System::plog('添加常见问题'.$id);
		}
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}
	//删除
	public function questiondel(){
		$ids = input('post.ids/a');
		Db::name('kefu_question')->where('aid',aid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('常见问题删除'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	
	
	
	
	
}