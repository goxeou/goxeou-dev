<?php


// +----------------------------------------------------------------------
// | 礼品卡分类
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class LipinCategory extends Common
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
			$where[] = ['bid','=',bid];
			$data = [];
			$cate0 = Db::name('lipin_category')->where('aid',aid)->where('bid',bid)->where('pid',0)->order($order)->select()->toArray();
			foreach($cate0 as $c0){
				$data[] = $c0;
				$cate1 = Db::name('lipin_category')->where('aid',aid)->where('bid',bid)->where('pid',$c0['id'])->order($order)->select()->toArray();
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
			$info = Db::name('lipin_category')->where('aid',aid)->where('bid',bid)->where('id',input('param.id/d'))->find();
		}else{
			$info = array('id'=>'');
		}
		if(input('param.pid')) $info['pid'] = input('param.pid');
		$pcatelist = Db::name('lipin_category')->where('aid',aid)->where('bid',bid)->where('id','<>',$info['id'])->order('sort desc,id')->select()->toArray();
		View::assign('info',$info);
		View::assign('pcatelist',$pcatelist);
		return View::fetch();
	}
	//保存
	public function save(){
		$info = input('post.info/a');
		if($info['id']){
			Db::name('lipin_category')->where('aid',aid)->where('bid',bid)->where('id',$info['id'])->update($info);
            \app\commons\System::plog('编辑礼品卡分类'.$info['id']);
		}else{
			$info['aid'] = aid;
			$info['bid'] = bid;
			$info['createtime'] = time();
            $id = Db::name('lipin_category')->insertGetId($info);

            \app\commons\System::plog('添加礼品卡分类'.$id);
		}
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}
	//删除
	public function del(){
		$ids = input('post.ids/a');
		Db::name('lipin_category')->where('aid',aid)->where('bid',bid)->where('id','in',$ids)->delete();
        \app\commons\System::plog('删除礼品卡分类'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
}