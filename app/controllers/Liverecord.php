<?php
namespace app\controllers;
use think\facade\View;
use think\facade\Db;
use think\Log;

class Liverecord extends Common{
    public function lst(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = 'live_record.'.input('param.field').' '.input('param.order');
			}else{
				$order = 'live_record.id desc';
			}
			$where = array();
			$where[] = ['live_record.aid','=',aid];
			
			if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
			if(input('param.mid')) $where[] = ['live_record.mid','=',trim(input('param.mid'))];
			if(input('?param.status') && input('param.status')!=='') $where[] = ['live_record.status','=',input('param.status')];
			$count = 0 + Db::name('live_record')->alias('live_record')->field('member.nickname,member.headimg,live_record.*')->join('member member','member.id=live_record.mid')->join('lives live','live.room=live_record.room')->where($where)->count();
			$data = Db::name('live_record')->alias('live_record')->field('live.title,member.nickname,member.headimg,live_record.*')->join('member member','member.id=live_record.mid')->join('lives live','live.room=live_record.room')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			
			
			foreach ($data as $key => &$item) {
			     if(($item['endtime']-$item['starttime'])>0) {
                    $item['timedate'] = timediff($item['endtime'], $item['starttime']);
                }else{
                    $item['timedate']=0;
                }
			}
			
			
			ll($data,'auto_day');
			
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
    }
   	//删除
	public function del(){
		$ids = input('post.ids/a');
		Db::name('live_record')->where('aid',aid)->where('id','in',$ids)->delete();
	
		return json(['status'=>1,'msg'=>'删除成功']);
	}
}
?>