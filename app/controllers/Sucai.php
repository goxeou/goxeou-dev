<?php

namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class Sucai extends Common
{
	public function initialize(){
		parent::initialize();
		if(bid > 0) showmsg('无操作权限');
	}
	//列表
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
			if(input('param.cid')) $where[] = ['cid','=',input('param.cid/d')];
			if(input('param.name')) $where[] = ['name','like','%'.input('param.name').'%'];
			if(input('?param.status') && input('param.status')!==''){
				$where[] = ['status','=',input('param.status')];
			}
			$count = 0 + Db::name('sucai')->where($where)->count();
			$carr = Db::name('sucai_category')->where('aid',aid)->column('name','id');
			$data = Db::name('sucai')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			foreach($data as $k=>$v){
				$data[$k]['cname'] = $carr[$v['cid']];
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		//分类
		$clist = Db::name('sucai_category')->Field('id,name')->where('aid',aid)->where('status',1)->order('sort desc,id')->select()->toArray();
        View::assign('clist',$clist);
		return View::fetch();
    }
//编辑
	public function edit(){
		if(input('param.id')){
			$info = Db::name('sucai')->where('aid',aid)->where('id',input('param.id/d'))->find();
		}else{
			$info = array('id'=>'','type'=>0);
		}
		//分类
		$clist = Db::name('sucai_category')->Field('id,name')->where('aid',aid)->where('status',1)->order('sort desc,id')->select()->toArray(); 
		View::assign('clist',$clist);
		View::assign('info',$info);
		return View::fetch();
	}
	//保存
	public function save(){
		$info = input('post.info/a');
        $isAudit = 0;
        $mid = 0;
		if($info['id']){
			$oldinfo = Db::name('sucai')->where('aid',aid)->where('id',$info['id'])->find();
		}
		if ($info['type']==1) {
		    $info['pics'] = '';
		} else {
		     $info['video'] = '';
		}
		if($info['id']){
			Db::name('sucai')->where('aid',aid)->where('id',$info['id'])->update($info);
		}else{
			$info['aid'] = aid;
			$info['createtime'] = time();
			$id = Db::name('sucai')->insertGetId($info);
		}
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}


	public function del(){
		$ids = input('post.ids/a');
		Db::name('sucai')->where('aid',aid)->where('id','in',$ids)->delete();
        \app\commons\System::plog('删除素材'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	//分类列表
    public function category(){
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
			if(input('param.name')) $where[] = ['name','like','%'.input('param.name').'%'];
			if(input('?param.status') && input('param.status')!=='') $where[] = ['status','=',input('param.status')];
			$count = 0 + Db::name('sucai_category')->where($where)->count();
			$data = Db::name('sucai_category')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
    }
	//编辑
	public function categoryedit(){
		if(input('param.id')){
			$info = Db::name('sucai_category')->where('aid',aid)->where('id',input('param.id/d'))->find();
		}else{
			$info = array('id'=>'');
		}
		$pcatelist = Db::name('sucai_category')->where('aid',aid)->order('sort desc,id')->select()->toArray();
		View::assign('info',$info);
		return View::fetch();
	}
	//保存
	public function categorysave(){
		$info = input('post.info/a');
		if($info['id']){
			Db::name('sucai_category')->where('aid',aid)->where('id',$info['id'])->update($info);
			\app\commons\System::plog('修改素材分类'.$info['id']);
		}else{
			$info['aid'] = aid;
			$info['createtime'] = time();
			$id = Db::name('sucai_category')->insertGetId($info);
			\app\commons\System::plog('添加素材分类'.$id);
		}
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}
	//删除
	public function categorydel(){
		$ids = input('post.ids/a');
		Db::name('sucai_category')->where('aid',aid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('删除素材分类'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}

}
