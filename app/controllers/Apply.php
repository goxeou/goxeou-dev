<?php


// +----------------------------------------------------------------------
// | 会员等级
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class Apply extends Common
{
    public function initialize(){
		parent::initialize();
		if(bid > 0) showmsg('无访问权限');
	}
	public function index(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = 'member_apply_area.'.input('param.field').' '.input('param.order');
			}else{
				$order = 'member_apply_area.id desc';
			}
			$where = array();
			$where[] = ['member_apply_area.aid','=',aid];
			
			if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
			if(input('param.mid')) $where[] = ['member_apply_area.mid','=',trim(input('param.mid'))];
			if(input('?param.status') && input('param.status')!=='') $where[] = ['member_apply_area.status','=',input('param.status')];
			$count = 0 + Db::name('member_apply_area')->alias('member_apply_area')->field('member.nickname,member.headimg,member_apply_area.*')->leftjoin('member member','member.id=member_apply_area.mid')->where($where)->count();
			$data = Db::name('member_apply_area')->alias('member_apply_area')->field('member.nickname,member.headimg,member_apply_area.*')->leftjoin('member member','member.id=member_apply_area.mid')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
	}

		//申请审核
	public function applyshenhe(){
		$id = input('post.id/d');
		$status = input('post.st');
		$info = Db::name('member_apply_area')->where('aid',aid)->where('id',$id)->find();
		Db::name('member_apply_area')->where('aid',aid)->where('id',$id)->update(['status'=>$status,'shenhetime'=>time()]);
		\app\commons\System::plog('审核会员区域申请记录'.$id);
		return json(['status'=>1,'msg'=>'操作成功']);
	}


	//审核删除
	public function applydel(){
		$type = input('post.type');
        $ids = input('post.ids/a');
		Db::name('member_apply_area')->where('aid',aid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('删除会员区域申请记录'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	
		
	public function edit(){
		if(input('param.id')){
			$info = Db::name('member_apply_area')->where('aid',aid)->where('id',input('param.id/d'))->find();
			$info['member']= Db::name('member')->where('aid',aid)->where('id',$info['mid'])->find();
		}else{
			$info = array('id'=>'');
		}
		View::assign('info',$info);
		return View::fetch();
	}
	
	public function save(){
		$info = input('post.info/a');
		$member = Db::name('member')->where('id',$info['mid'])->find();
		if (!$member) return json(['status'=>0,'msg'=>'会员不存在']);
		if($info['type']==1){
			$have = Db::name('member_apply_area')->where('id','<>',$info['id'])->where('status',1)->where('type',1)->where('province',$info['province'])->find();
		
		}elseif($info['type']==2){
		    $have = Db::name('member_apply_area')->where('id','<>',$info['id'])->where('status',1)->where('type',2)->where('province',$info['province'])->where('city',$info['city'])->find();
		}elseif($info['type']==3){
		   	$have = Db::name('member_apply_area')->where('id','<>',$info['id'])->where('status',1)->where('type',3)->where('province',$info['province'])->where('city',$info['city'])->where('area',$info['area'])->find();
		}
		
		if($have) return json(['status'=>0,'msg'=>'本区域已有代理商']);
        if($info['id']){
			Db::name('member_apply_area')->where('aid',aid)->where('id',$info['id'])->update($info);
			\app\commons\System::plog('编辑区域代理'.$info['id']);
		}else{
			$info['aid'] = aid;
			$info['createtime'] = time();
			$id = Db::name('member_apply_area')->insertGetId($info);
			\app\commons\System::plog('添加区域代理'.$id);
		}
		
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}
	public function fhlog(){
		$st = input('param.st');
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$pernum = 20;
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['mid','=',mid];
		$where[] = ['status','=',1];
		if($st ==1){
			$datalist = Db::name('member_fenhonglog')->where('type','teamfenhong')->where($where)->where('s_type',t('乐豆'))->page($pagenum,$pernum)->order('id desc')->select()->toArray();
			if(!$datalist) $datalist = [];
		}elseif($st ==2){
			$datalist = Db::name('member_fenhonglog')->where('type','teamfenhong')->where($where)->where('s_type',t('佣金'))->page($pagenum,$pernum)->order('id desc')->select()->toArray();
			if(!$datalist) $datalist = [];
		}else{
		    $datalist = Db::name('member_fenhonglog')->where('type','teamfenhong')->where($where)->where('s_type',t('积分'))->page($pagenum,$pernum)->order('id desc')->select()->toArray();
			if(!$datalist) $datalist = [];
		}
		
		return $this->json(['status'=>1,'data'=>$datalist]);
	}
	
}
