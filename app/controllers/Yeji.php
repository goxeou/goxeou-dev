<?php


// +----------------------------------------------------------------------
// | 会员等级
// +----------------------------------------------------------------------
namespace app\controller;
use think\facade\View;
use think\facade\Db;

class Yeji extends Common
{
    public function initialize(){
		parent::initialize();
		if(bid > 0) showmsg('无访问权限');
	}
		//佣金记录
    public function index(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = 'member_yeji_record.'.input('param.field').' '.input('param.order');
			}else{
				$order = 'member_yeji_record.id desc';
			}
			$where = [];
			$where[] = ['member_yeji_record.aid','=',aid];
			$where[] = ['member_yeji_record.status','in',[0,1]];
			if(input('param.ctime') ){
                $ctime = explode(' ~ ',input('param.ctime'));
                $where[] = ['member_yeji_record.createtime','>=',strtotime($ctime[0])];
                $where[] = ['member_yeji_record.createtime','<',strtotime($ctime[1]) + 86400];
            }
            
            $year = date('Y');
            if(input('param.year')) $year = input('param.year');
			if(input('param.month')){
			    $month = input('param.month');
			}else {
			    $month = date('m');
			} 
			$starttime = getTimeRangeByMonth($year,$month);
            $where[] = ['member_yeji_record.createtime','>=',$starttime[0]];
            $where[] = ['member_yeji_record.createtime','<',$starttime[1] + 86400];
            
			if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
			if(input('param.mid')) $where[] = ['member_yeji_record.mid','=',trim(input('param.mid'))];
			if(input('?param.status') && input('param.status')!=='') $where[] = ['member_yeji_record.status','=',input('param.status')];
			
			$totalcommission = 0;
			if(input('param.mid')){
				$fxmid = input('param.mid/d');
				if($page == 1){
					$totalcommission = Db::name('member_yeji_record')->alias('member_yeji_record')->field('member.nickname,member.headimg,member_yeji_record.*')->join('member member','member.id=member_yeji_record.mid')->where($where)->sum('member_yeji_record.totalprice');
					$totalcommission = round($totalcommission,2);
				}
			}
			
			
			$count = 0 + Db::name('member_yeji_record')->alias('member_yeji_record')->field('member.nickname,member.headimg,member_yeji_record.*')->join('member member','member.id=member_yeji_record.mid')->where($where)->count();
			$data = Db::name('member_yeji_record')->alias('member_yeji_record')->field('member.nickname,member.headimg,member_yeji_record.*')->join('member member','member.id=member_yeji_record.mid')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			foreach($data as $k=>$v){
			
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data,'totalcommission'=>$totalcommission]);
		}
		return View::fetch();
    }
    	//审核删除
	public function del(){
		$type = input('post.type');
        $ids = input('post.ids/a');
		Db::name('member_yeji_record')->where('aid',aid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('删除业绩记录'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}

	
}
