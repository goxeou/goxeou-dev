<?php


// +----------------------------------------------------------------------
// | 积分商城 商品分类
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class ScoreshopCategory extends Common
{
    public function initialize(){
		parent::initialize();
		if(bid > 0) showmsg('无访问权限');
	}
	//分类列表
    public function index(){
		if(request()->isAjax()){
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
				$order = 'sort desc,id';
			}
			$where = [];
			$where[] = ['aid','=',aid];
			$data = [];
			$cate0 = Db::name('scoreshop_category')->where('aid',aid)->where('pid',0)->order($order)->select()->toArray();
			foreach($cate0 as $c0){
				$data[] = $c0;
				$cate1 = Db::name('scoreshop_category')->where('aid',aid)->where('pid',$c0['id'])->order($order)->select()->toArray();
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
			$info = Db::name('scoreshop_category')->where('aid',aid)->where('id',input('param.id/d'))->find();
		}else{
			$info = array('id'=>'');
		}
		if(input('param.pid')) $info['pid'] = input('param.pid');
		$pcatelist = Db::name('scoreshop_category')->where('aid',aid)->where('pid',0)->where('id','<>',$info['id'])->order('sort desc,id')->select()->toArray();
		View::assign('info',$info);
		View::assign('pcatelist',$pcatelist);
		return View::fetch();
	}
	//保存
	public function save(){
		$info = input('post.info/a');
		if($info['id']){
			Db::name('scoreshop_category')->where('aid',aid)->where('id',$info['id'])->update($info);
			\app\commons\System::plog('编辑积分商城分类'.$info['id']);
		}else{
			$info['aid'] = aid;
			$info['createtime'] = time();
			$id = Db::name('scoreshop_category')->insertGetId($info);
			\app\commons\System::plog('添加积分商城分类'.$id);
		}
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}
	//删除
	public function del(){
		$ids = input('post.ids/a');
		Db::name('scoreshop_category')->where('aid',aid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('删除积分商城分类'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
}