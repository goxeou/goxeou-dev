<?php


// +----------------------------------------------------------------------
// | 课程分类
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class KechengCategory extends Common
{
    public function initialize(){
		parent::initialize();
		$this->defaultSet();
	}
	//列表
    public function index(){
		if(request()->isAjax()){
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
				$order = 'sort desc, id';
			}
			$where = [];
			$where[] = ['aid','=',aid];
			$where[] = ['bid','=',bid];
			$data = [];
			$cate0 = Db::name('kecheng_category')->where('aid',aid)->where('bid',bid)->where('pid',0)->order($order)->select()->toArray();
			foreach($cate0 as $c0){
				if($c0['pcid']){
					$pcinfo = Db::name('kecheng_category')->where('aid',aid)->where('id',$c0['pcid'])->find();
					$pcname = $pcinfo['name'];
					if($pcinfo['pid']){
						$pcinfoP = Db::name('kecheng_category')->where('aid',aid)->where('id',$pcinfo['pid'])->find();
						$pcname = $pcinfoP['name'] . ' / ' .$pcname;
					}
					$c0['pcname'] = $pcname;
				}
				$data[] = $c0;
				$cate1 = Db::name('kecheng_category')->where('aid',aid)->where('bid',bid)->where('pid',$c0['id'])->order($order)->select()->toArray();
				foreach($cate1 as $k1=>$c1){
					if($k1 < count($cate1)-1){
						$c1['name'] = '<span style="color:#aaa">&nbsp;&nbsp;&nbsp;&nbsp;├ </span>'.$c1['name'];
					}else{
						$c1['name'] = '<span style="color:#aaa">&nbsp;&nbsp;&nbsp;&nbsp;└ </span>'.$c1['name'];
					}
					$data[] = $c1;
				}
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>count($cate0),'data'=>$data]);
		}
		return View::fetch();
    }
	//编辑
	public function edit(){
		if(input('param.id')){
			$info = Db::name('kecheng_category')->where('aid',aid)->where('bid',bid)->where('id',input('param.id/d'))->find();
		}else{
			$info = array('id'=>'');
			if(input('param.pid')) $info['pid'] = input('param.pid');
		}
		if(false){}else{
			$pcCidArr = [];
		}

		View::assign('pcCidArr',$pcCidArr);
		View::assign('info',$info);
		return View::fetch();
	}
	public function save(){
		$info = input('post.info/a');
		if($info['id']){
			Db::name('kecheng_category')->where('aid',aid)->where('bid',bid)->where('id',$info['id'])->update($info);
			\app\commons\System::plog('编辑课程分类'.$info['id']);
		}else{
			$info['aid'] = aid;
			$info['bid'] = bid;
			$info['createtime'] = time();
			$id = Db::name('kecheng_category')->insertGetId($info);
			\app\commons\System::plog('添加课程分类'.$id);
		}
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}
	//改状态
	public function setst(){
		$st = input('post.st/d');
		$ids = input('post.ids/a');
		Db::name('kecheng_category')->where('aid',aid)->where('bid',bid)->where('id','in',$ids)->update(['status'=>$st]);
		\app\commons\System::plog('课程分类改状态'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//删除
	public function del(){
		$ids = input('post.ids/a');
		Db::name('kecheng_category')->where('aid',aid)->where('bid',bid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('课程分类删除'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
    function defaultSet(){
        $set = Db::name('kecheng_sysset')->where('aid',aid)->find();
        if(!$set){
            Db::name('kecheng_sysset')->insert(['aid'=>aid]);
        }
    }
}
