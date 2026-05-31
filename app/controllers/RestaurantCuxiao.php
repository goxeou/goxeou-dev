<?php


// +----------------------------------------------------------------------
// | 促销
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class RestaurantCuxiao extends Common
{
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
			$where[] = ['bid','=',bid];
			if(input('param.name')) $where[] = ['name','like','%'.input('param.name').'%'];
			$count = 0 + Db::name('restaurant_cuxiao')->where($where)->count();
			$data = Db::name('restaurant_cuxiao')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			foreach($data as $k=>$v){
				if($v['starttime'] > time()){
					$data[$k]['status'] = '<button class="layui-btn layui-btn-sm" style="background-color:#888">未开始</button>';
				}elseif($v['endtime'] < time()){
					$data[$k]['status'] = '<button class="layui-btn layui-btn-sm layui-btn-disabled">已结束</button>';
				}else{
					$data[$k]['status'] = '<button class="layui-btn layui-btn-sm" style="background-color:#5FB878">进行中</button>';
				}
				$data[$k]['starttime'] = date('Y-m-d H:i',$v['starttime']);
				$data[$k]['endtime'] = date('Y-m-d H:i',$v['endtime']);
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
	}
	//编辑
	public function edit(){
		if(input('param.id')){
			$info = Db::name('restaurant_cuxiao')->where('aid',aid)->where('bid',bid)->where('id',input('param.id/d'))->find();
			$info['starttime'] = date('Y-m-d H:i:s',$info['starttime']);
			$info['endtime'] = date('Y-m-d H:i:s',$info['endtime']);
		}else{
            $info = array('id'=>'','starttime'=>date('Y-m-d 00:00:00'),'endtime'=>date('Y-m-d 00:00:00',time()+7*86400),'gettj'=>'-1','sort'=>0,'fwtype'=>0,'type'=>1,'tip'=>'满减');
		}
        $info['gettj'] = explode(',',$info['gettj']);
		if($info['proid']){
			$proinfo = Db::name('restaurant_product')->where('aid',aid)->where('bid',bid)->where('id',$info['proid'])->find();
			$gginfo = Db::name('restaurant_product_guige')->where('aid',aid)->where('id',$info['ggid'])->find();
			View::assign('proinfo',$proinfo);
			View::assign('gginfo',$gginfo);
		}
		View::assign('info',$info);
        $default_cid = Db::name('member_level_category')->where('aid',aid)->where('isdefault', 1)->value('id');
        $default_cid = $default_cid ? $default_cid : 0;
        $memberlevel = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->order('sort,id')->select()->toArray();
		View::assign('memberlevel',$memberlevel);

		$categorydata = array();
		if($info && $info['categoryids']){
			$categorydata = Db::name('restaurant_product_category')->where('aid',aid)->where('id','in',$info['categoryids'])->order('sort desc,id')->select()->toArray();
		}
		View::assign('categorydata',$categorydata);
		$productdata = array();
		if($info && $info['productids']){
			$productdata = Db::name('restaurant_product')->where('aid',aid)->where('id','in',$info['productids'])->order('sort desc,id')->select()->toArray();
		}
		View::assign('productdata',$productdata);
		return View::fetch();
	}
	//保存
	public function save(){
		$info = input('post.info/a');
		$info['gettj'] = implode(',',$info['gettj']);
		$info['starttime'] = strtotime($info['starttime']);
		$info['endtime'] = strtotime($info['endtime']);
		if($info['id']){
			Db::name('restaurant_cuxiao')->where('aid',aid)->where('bid',bid)->where('id',$info['id'])->update($info);
			\app\commons\System::plog('修改促销活动'.$info['id']);
		}else{
			$info['aid'] = aid;
			$info['bid'] = bid;
			$info['createtime'] = time();
			$id = Db::name('restaurant_cuxiao')->insertGetId($info);
			\app\commons\System::plog('添加促销活动'.$id);
		}
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}
	//删除
	public function del(){
		$ids = input('post.ids/a');
		Db::name('restaurant_cuxiao')->where('aid',aid)->where('bid',bid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('删除促销活动'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
}