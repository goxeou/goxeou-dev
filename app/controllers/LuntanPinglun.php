<?php


// +----------------------------------------------------------------------
// | 论坛 帖子评论列表
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class LuntanPinglun extends Common
{
    
	public function initialize(){
		parent::initialize();
		if(bid > 0) showmsg('无操作权限');
        $this->defaultSet();
	}
	//晒图列表
    public function index(){
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
			if(input('param.sid')) $where[] = ['sid','=',input('param.sid')];
			if(input('?param.st')) $where[] = ['status','=',input('param.st')];
			if(input('param.title')) $where[] = ['title','like','%'.input('param.title').'%'];
			if(input('param.ctime') ){
				$ctime = explode(' ~ ',input('param.ctime'));
				$where[] = ['createtime','>=',strtotime($ctime[0])];
				$where[] = ['createtime','<',strtotime($ctime[1]) + 86400];
			}
			//dump($where);
			$count = 0 + Db::name('luntan_pinglun')->where($where)->count();
			$datalist = Db::name('luntan_pinglun')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			foreach($datalist as $k=>$v){
				$v['luntan'] = Db::name('luntan')->where('id',$v['sid'])->find();
				$v['replycount'] = Db::name('luntan_pinglun_reply')->where('pid',$v['id'])->count();
				$datalist[$k] = $v;
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$datalist]);
		}
		$set = Db::name('admin_set')->where('aid',aid)->find();
		View::assign('set',$set);
		return View::fetch();
    }
	//审核
	public function setst(){
		$st = input('post.st/d');
		$ids = input('post.ids/a');
		$score = input('post.givescore/d');
		$list = Db::name('luntan_pinglun')->where('aid',aid)->where('id','in',$ids)->select()->toArray();
		foreach($list as $v){
			Db::name('luntan_pinglun')->where('aid',aid)->where('id',$v['id'])->update(['status'=>$st,'score'=>$score]);
			if($score!=0){
				\app\commons\Member::addscore(aid,$v['mid'],$score,'评论奖励'.t('积分'));
			}else{
                }

		}
		\app\commons\System::plog('用户论坛评论审核'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//删除
	public function del(){
		$ids = input('post.ids/a');
		Db::name('luntan_pinglun')->where('aid',aid)->where('id','in',$ids)->delete();
		Db::name('luntan_pinglun_reply')->where('aid',aid)->where('pid','in',$ids)->delete();
		\app\commons\System::plog('用户论坛评论删除'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
    function defaultSet(){
        $set = Db::name('luntan_sysset')->where('aid',aid)->find();
        if(!$set){
            Db::name('luntan_sysset')->insert(['aid'=>aid]);
        }
    }
}
