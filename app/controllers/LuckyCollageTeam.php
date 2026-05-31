<?php


// +----------------------------------------------------------------------
// | 拼团商城 拼团管理
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class LuckyCollageTeam extends Common
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
			$count = 0 + Db::name('lucky_collage_order_team')->where($where)->count();
			$data = Db::name('lucky_collage_order_team')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			foreach($data as $k=>$v){
				$product = Db::name('lucky_collage_product')->where('aid',aid)->where('bid',bid)->where('id',$v['proid'])->find();
				$data[$k]['product'] = $product;
				$orderlist = Db::name('lucky_collage_order')->where('aid',aid)->where('bid',bid)->where('teamid',$v['id'])->where('status','in','1,2,3,4')->select()->toArray();
				$userlist = array();
				foreach($orderlist as $v2){
					$user = Db::name('member')->field('id,nickname,headimg,province,city,sex')->where('aid',aid)->where('id',$v2['mid'])->find();
					if($v2['isjiqiren']==1){
						$user = Db::name('lucky_collage_jiqilist')->field('id,nickname,headimg')->where('aid',aid)->where('id',$v2['mid'])->find();
					}
					
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
        $this->defaultSet();
		return View::fetch();
    }
	//删除
	public function del(){
		$ids = input('post.ids/a');
		$list = Db::name('lucky_collage_order_team')->where('aid',aid)->where('bid',bid)->where('id','in',$ids)->select()->toArray();
		foreach($list as $v){
			Db::name('lucky_collage_order_team')->where('aid',aid)->where('bid',bid)->where('id',$v['id'])->delete();
			Db::name('lucky_collage_order')->where('aid',aid)->where('bid',bid)->where('teamid',$v['id'])->delete();
		}
		\app\commons\System::plog('删除拼团的团'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}

	//订单详情
	public function getdetail(){
		$orderid = input('post.orderid');
		$order = Db::name('lucky_collage_order')->where('aid',aid)->where('bid',bid)->where('teamid',$orderid)->find();
		return json(['order'=>$order]);
	}
    function defaultSet(){
        $set = Db::name('lucky_collage_sysset')->where('aid',aid)->find();
        if(!$set){
            Db::name('lucky_collage_sysset')->insert(['aid'=>aid]);
        }
    }
}