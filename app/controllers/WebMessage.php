<?php


// +----------------------------------------------------------------------
// | 网站留言
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class WebMessage extends Common
{
    public function initialize(){
		parent::initialize();
		$this->uid = session('BST_ID');
		$this->user = db('admin_user')->where(['id'=>$this->uid])->find();
		if(!session('BST_ID') || !$this->user || $this->user['isadmin'] != 2){
			showmsg('无访问权限');
		}
	}
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
			//$where['aid'] = aid;
			if(input('param.realname')) $where[] = ['realname','like','%'.input('param.realname').'%'];
			if(input('param.tel')) $where[] = ['tel','like','%'.input('param.tel').'%'];
			if(input('?param.status') && input('param.status')!=='') $where[] = ['status','=',input('param.status')];
			if(input('param.ctime') ){
				$ctime = explode(' ~ ',input('param.ctime'));
				$where[] = ['createtime','>=',strtotime($ctime[0])];
				$where[] = ['createtime','<',strtotime($ctime[1]) + 86400];
			}
			$count = 0 + Db::name('webmessage')->where($where)->count();
			$data = Db::name('webmessage')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			
			return ['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data];
		}
		return View::fetch();
	}
	//改状态
	public function setst(){
		$ids = input('post.ids/a');
		Db::name('webmessage')->where('id','in',$ids)->update(['status'=>input('post.st/d')]);
		\app\commons\System::plog('网站留言改状态'.implode(',',$ids),1);
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//删除
	public function del(){
		$ids = input('post.ids/a');
		Db::name('webmessage')->where('id','in',$ids)->delete();
		\app\commons\System::plog('网站留言删除'.implode(',',$ids),1);
		return json(['status'=>1,'msg'=>'删除成功']);
	}
}