<?php


// +----------------------------------------------------------------------
// | 店铺评价
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class BusinessComment extends Common
{
	public function initialize(){
		parent::initialize();
		//if(bid > 0) showmsg('无操作权限');
	}
	//评价列表
    public function index(){
		$bnameArr = Db::name('business')->where('aid',aid)->column('name','id');
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
				$order = 'id desc';
			}
			$where = array();
			$where[] = ['aid','=',aid];
			if(bid==0){
				if(input('param.bid')){
					$where[] = ['bid','=',input('param.bid')];
				}elseif(input('param.showtype')==2){
					$where[] = ['bid','<>',0];
                }elseif(input('param.showtype')=='all'){
                    $where[] = ['bid','>=',0];
				}else{
					$where[] = ['bid','=',0];
				}
                }else{
				$where[] = ['bid','=',bid];
			}
			if(input('param.content')) $where[] = ['content','like','%'.input('param.content').'%'];
			if(input('param.ctime') ){
				$ctime = explode(' ~ ',input('param.ctime'));
				$where[] = ['createtime','>=',strtotime($ctime[0])];
				$where[] = ['createtime','<',strtotime($ctime[1]) + 86400];
			}
			//dump($where);
			$count = 0 + Db::name('business_comment')->where($where)->count();
			$data = Db::name('business_comment')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			foreach($data as $k=>$v){
				$data[$k]['bname'] = $bnameArr[$v['bid']];
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		View::assign('bnameArr',$bnameArr);
		return View::fetch();
    }
	//评价审核
	public function setst(){
		if(bid > 0) showmsg('无操作权限');
		$st = input('post.st/d');
		$ids = input('post.ids/a');
		$list = Db::name('business_comment')->where('aid',aid)->where('id','in',$ids)->select()->toArray();
		foreach($list as $v){
			Db::name('business_comment')->where('aid',aid)->where('id',$v['id'])->update(['status'=>$st]);
			$countnum = Db::name('business_comment')->where('bid',$v['bid'])->where('status',1)->count();
			$score = Db::name('business_comment')->where('bid',$v['bid'])->where('status',1)->avg('score');
			Db::name('business')->where('id',$v['bid'])->update(['comment_num'=>$countnum,'comment_score'=>$score]);
		}
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//评价详情
	public function getdetail(){
		$detail= Db::name('business_comment')->where('aid',aid)->where('id',$_POST['id'])->find();
		if($detail['content_pic']) $detail['content_pic'] = explode(',',$detail['content_pic']);
		$member = Db::name('member')->where('aid',aid)->where('id',$detail['mid'])->find();
		if(!$member) $member = ['nickname'=>$detail['nickname'],'headimg'=>$detail['headimg']];
		return json(['status'=>1,'detail'=>$detail,'member'=>$member]);
	}
	//评价回复
	public function reply(){
		$id = input('post.id/d');
		if(bid == 0){
			Db::name('business_comment')->where('aid',aid)->where('id',$id)->update(['reply_content'=>$_POST['content'],'reply_time'=>time()]);
		}else{
			Db::name('business_comment')->where('aid',aid)->where('bid',bid)->where('id',$id)->update(['reply_content'=>$_POST['content'],'reply_time'=>time()]);
		}
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//删除
	public function del(){
		if(bid > 0) showmsg('无操作权限');
		$ids = input('post.ids/a');
		Db::name('business_comment')->where('aid',aid)->where('id','in',$ids)->delete();
		return json(['status'=>1,'msg'=>'删除成功']);
	}
}
