<?php


// +----------------------------------------------------------------------
// | 商城 商品分类
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class ShopCategory extends Common
{
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
			$cate0 = Db::name('shop_category')->where('aid',aid)->where('pid',0)->order($order)->select()->toArray();
			foreach($cate0 as $c0){
				$c0['deep'] = 0;
				$data[] = $c0;
				$cate1 = Db::name('shop_category')->where('aid',aid)->where('pid',$c0['id'])->order($order)->select()->toArray();
				foreach($cate1 as $k1=>$c1){
					if($k1 < count($cate1)-1){
						$c1['name'] = $c1['name'];
					}else{
						$c1['name'] = $c1['name'];
					}
					$c1['deep'] = 1;
					$data[] = $c1;
					$cate2 = Db::name('shop_category')->where('aid',aid)->where('pid',$c1['id'])->order($order)->select()->toArray();
					foreach($cate2 as $k2=>$c2){
						if($k2 < count($cate2)-1){
							$c2['name'] = $c2['name'];
						}else{
							$c2['name'] = $c2['name'];
						}
						$c2['deep'] = 2;
						$data[] = $c2;
					}
				}
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>count($cate0),'data'=>$data]);
		}
		return View::fetch();
    }
	//编辑
	public function edit(){
		if(bid > 0) showmsg('无访问权限');
		if(input('param.id')){
			$info = Db::name('shop_category')->where('aid',aid)->where('id',input('param.id/d'))->find();
		}else{
			$info = array('id'=>'','showtj'=>-1);
		}
		if(input('param.pid')) $info['pid'] = input('param.pid');
		$pcatelist = Db::name('shop_category')->where('aid',aid)->where('pid',0)->where('id','<>',$info['id'])->order('sort desc,id')->select()->toArray();
		foreach($pcatelist as $k=>$v){
			$pcatelist[$k]['child'] = Db::name('shop_category')->Field('id,name')->where('aid',aid)->where('pid',$v['id'])->where('id','<>',$info['id'])->order('sort desc,id')->select()->toArray(); 
		}
        View::assign('info',$info);
		View::assign('pcatelist',$pcatelist);
		return View::fetch();
	}
	//保存
	public function save(){
		if(bid > 0) showmsg('无访问权限');
		$info = input('post.info/a');
        $info['showtj'] = implode(',',$info['showtj']);
        if(empty($info['showtj'])) $info['showtj'] = -1;

        if($info['id']){
			Db::name('shop_category')->where('aid',aid)->where('id',$info['id'])->update($info);
			\app\commons\System::plog('编辑商城商品分类'.$info['id']);
		}else{
			$info['aid'] = aid;
			$info['createtime'] = time();
			$id = Db::name('shop_category')->insertGetId($info);
			\app\commons\System::plog('添加商城商品分类'.$id);
		}
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}
	//删除
	public function del(){
		if(bid > 0) showmsg('无访问权限');
		$ids = input('post.ids/a');
		Db::name('shop_category')->where('aid',aid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('删除商城商品分类'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	//选择分类弹窗
    public function choosecategory(){
    	$selmore = input('selmore')?true:false;//是否多选
		if(request()->isAjax()){
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
				$order = 'sort desc,id';
			}
			$where = [];
			$where[] = ['aid','=',aid];
			$data = [];
			$cate0 = Db::name('shop_category')->where('aid',aid)->where('pid',0)->order($order)->select()->toArray();
			foreach($cate0 as $c0){
				$c0['showname'] = $c0['name'];
				$c0['deep'] = 0;
				$data[] = $c0;
				$cate1 = Db::name('shop_category')->where('aid',aid)->where('pid',$c0['id'])->order($order)->select()->toArray();
				foreach($cate1 as $k1=>$c1){
					if($k1 < count($cate1)-1){
						$c1['showname'] = '<span style="color:#aaa">&nbsp;&nbsp;&nbsp;&nbsp;├ </span>'.$c1['name'];
					}else{
						$c1['showname'] = '<span style="color:#aaa">&nbsp;&nbsp;&nbsp;&nbsp;└ </span>'.$c1['name'];
					}
					$c1['deep'] = 1;
					$data[] = $c1;
					$cate2 = Db::name('shop_category')->where('aid',aid)->where('pid',$c1['id'])->order($order)->select()->toArray();
					foreach($cate2 as $k2=>$c2){
						if($k2 < count($cate2)-1){
							$c2['showname'] = '<span style="color:#aaa">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;├ </span>'.$c2['name'];
						}else{
							$c2['showname'] = '<span style="color:#aaa">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;└ </span>'.$c2['name'];
						}
						$c2['deep'] = 2;
						$data[] = $c2;
					}
				}
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>count($cate0),'data'=>$data]);
		}
		View::assign('selmore',$selmore);
		return View::fetch();
    }
}