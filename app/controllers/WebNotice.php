<?php


// +----------------------------------------------------------------------
// | 通知公告
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class WebNotice extends Common
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
				$order = 'notice.' . input('param.field').' '.input('param.order');
			}else{
				$order = 'notice.id desc';
			}
			$where = [];
			$where[] = ['notice.type','=',1];
			if(input('param.un')) $where[] = ['user.un','like','%'.input('param.un').'%'];
			if(input('param.title')) $where[] = ['notice.title','like','%'.input('param.title').'%'];
			if(input('?param.isread') && input('param.isread')!=='') $where[] = ['notice.isread','=',input('param.isread')];
			if(input('param.ctime')){
				$ctime = explode(' ~ ',input('param.ctime'));
				$where[] = ['notice.createtime','>=',strtotime($ctime[0])];
				$where[] = ['notice.createtime','<',strtotime($ctime[1]) + 86400];
			}
			$count = 0 + Db::name('admin_notice')->alias('notice')->field('user.un,notice.*')->join('admin_user user','user.id=notice.uid')->where($where)->count();
			$data = Db::name('admin_notice')->alias('notice')->field('user.un,notice.*')->join('admin_user user','user.id=notice.uid')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			
			return ['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data];
		}
		return View::fetch();
	}
	//编辑公告
	public function edit(){
		if(input('param.id')){
			$info = Db::name('admin_notice')->where('id',input('param.id/d'))->find();
		}else{
			$info = [];
		}
		//所有账号 平台账号+商家账号
		$userlist = Db::name('admin_user')->where([])->column('id,un','id');
		//平台主账号
		$adminuserlist = Db::name('admin_user')->where('isadmin','>=',1)->where('bid',0)->where('status',1)->column('id,un','id');
		//平台主账号 + 分账号
		$adminuserlist2 = Db::name('admin_user')->where('bid',0)->where('status',1)->column('id,un','id');
		//商家主账号
		$businessuserlist = Db::name('admin_user')->where('isadmin','>=',1)->where('bid','>',0)->where('status',1)->column('id,un','id');
		//商家主账号 + 分账号
		$businessuserlist2 = Db::name('admin_user')->where('bid','>',0)->where('status',1)->column('id,un','id');

		View::assign('info',$info);
		View::assign('userlist',$userlist);
		View::assign('adminuserlist',$adminuserlist);
		View::assign('adminuserlist2',$adminuserlist2);
		View::assign('businessuserlist',$businessuserlist);
		View::assign('businessuserlist2',$businessuserlist2);
		return View::fetch();
	}
	//保存
	public function save(){
		$info = input('post.info/a');
		$info['content'] = \app\commons\Common::geteditorcontent($info['content'],0);
		$info['createtime'] = time();
		$info['type'] = 1;

		$sendtype = input('post.sendtype');
		$senduser = input('post.senduser'.$sendtype);
		if($senduser != 0){
			$userlist = Db::name('admin_user')->field('id,aid,bid')->where('id',$senduser)->select()->toArray();
		}else{
			if($sendtype == 1){
				$userlist = Db::name('admin_user')->field('id,aid,bid')->where([])->select()->toArray();
			}
			if($sendtype == 2){
				$userlist = Db::name('admin_user')->field('id,aid,bid')->where('isadmin','>=',1)->where('bid',0)->select()->toArray();
			}
			if($sendtype == 3){
				$userlist = Db::name('admin_user')->field('id,aid,bid')->where('bid',0)->where('status',1)->select()->toArray();
			}
			if($sendtype == 4){
				$userlist = Db::name('admin_user')->field('id,aid,bid')->where('isadmin','>=',1)->where('bid','>',0)->where('status',1)->select()->toArray();
			}
			if($sendtype == 5){
				$userlist = Db::name('admin_user')->field('id,aid,bid')->where('bid','>',0)->where('status',1)->select()->toArray();
			}
		}
		foreach($userlist as $user){
			$info['aid'] = $user['aid'];
			$info['bid'] = $user['bid'];
			$info['uid'] = $user['id'];
			Db::name('admin_notice')->insert($info);
		}
		\app\commons\System::plog('发送通知公告',1);
		
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}
	//查看
	public function detail(){
		if(input('param.id')){
			$info = Db::name('admin_notice')->where('id',input('param.id/d'))->find();
		}else{
			$info = array('id'=>'');
		}
		View::assign('info',$info);
		return View::fetch();
	}
	//删除
	public function del(){
		$ids = input('post.ids/a');
		Db::name('admin_notice')->where('id','in',$ids)->delete();
		\app\commons\System::plog('删除通知公告',1);
		return json(['status'=>1,'msg'=>'删除成功']);
	}
}