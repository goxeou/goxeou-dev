<?php


// +----------------------------------------------------------------------
// | 拼团商城 拼团管理
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class CollageTeam extends Common
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
			$where = [];
			$where[] = ['aid','=',aid];
			$where[] = ['bid','=',bid];
			$where[] = ['status','<>',0];

			if(input('param.status')){
				$where[] = ['status','=',input('param.status')];
			}
			
			$count = 0 + Db::name('collage_order_team')->where($where)->count();
			$data = Db::name('collage_order_team')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			foreach($data as $k=>$v){
				$product = Db::name('collage_product')->where('aid',aid)->where('bid',bid)->where('id',$v['proid'])->find();
				$data[$k]['product'] = $product;
				$orderlist = Db::name('collage_order')->where('aid',aid)->where('bid',bid)->where('teamid',$v['id'])->where('status','in','1,2,3')->select()->toArray();
				$userlist = array();
				foreach($orderlist as $v2){
					$user = Db::name('member')->field('id,nickname,headimg,province,city,sex')->where('aid',aid)->where('id',$v2['mid'])->find();
					if(!$user) $user = ['id'=>$v2['mid'],'nickname'=>'','headimg'=>''];
					$userlist[] = $user;
				}
				if($v['teamnum'] > $v['num']){
					for($i=0;$i<$v['teamnum'] - $v['num'];$i++){
						$userlist[] = array('id'=>'','nickname'=>'','headimg'=>PRE_URL.'/static/admin/img/wh.png');
					}
				}
				$data[$k]['userlist'] = $userlist;
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
    }
	//删除
	public function del(){
		$ids = input('post.ids/a');
		$list = Db::name('collage_order_team')->where('aid',aid)->where('bid',bid)->where('id','in',$ids)->select()->toArray();
		foreach($list as $v){
			Db::name('collage_order_team')->where('aid',aid)->where('bid',bid)->where('id',$v['id'])->delete();
			$proComment = Db::name('collage_order_team')->where('aid',aid)->where('bid',bid)->where('proid',$v['proid'])->where('status',1)->avg('level');
			Db::name('collage_product')->where('aid',aid)->where('bid',bid)->where('id',$v['proid'])->update(['comment'=>$proComment]);
		}
		\app\commons\System::plog('删除拼团的团'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
}