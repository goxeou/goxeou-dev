<?php


// +----------------------------------------------------------------------
// | 帮助中心
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class WebHelp extends Common
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
				$order = 'sort desc,id';
			}
			$where = [];
			if(input('param.pid')) $where[] = ['pid','=',input('param.pid')];
			if(input('param.name')) $where[] = ['name','like','%'.input('param.name').'%'];
			if(input('?param.status') && input('param.status')!=='') $where[] = ['status','=',input('param.status')];
			if(input('param.cid')) $where[] = ['cid','=',input('param.cid')];
			if(input('param.ctime') ){
				$ctime = explode(' ~ ',input('param.ctime'));
				$where[] = ['createtime','>=',strtotime($ctime[0])];
				$where[] = ['createtime','<',strtotime($ctime[1]) + 86400];
			}
			$count = 0 + Db::name('help')->where($where)->count();
			$data = Db::name('help')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			
			return ['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data];
		}
		return View::fetch();
	}
	//编辑文章
	public function edit(){
		if(input('param.id')){
			$info = Db::name('help')->where('id',input('param.id/d'))->find();
			}else{
			$info = array('id'=>'','sendtime'=>time());
			$webinfo = json_decode(Db::name('sysset')->where('name','webinfo')->value('value'),true);
			$info['author'] = $webinfo['webname'];
		}
		View::assign('info',$info);
		return View::fetch();
	}
	//保存
	public function save(){
		$info = input('post.info/a');
		$info['content'] = \app\commons\Common::geteditorcontent($info['content'],0);
		$info['sendtime'] = strtotime($info['sendtime']);
		if($info['id']){
			Db::name('help')->where('id',$info['id'])->update($info);
			\app\commons\System::plog('编辑帮助中心文章'.$info['id'],1);
		}else{
			//$info['aid'] = aid;
			$info['createtime'] = time();
			$id = Db::name('help')->insertGetId($info);
			\app\commons\System::plog('添加帮助中心文章'.$id,1);
		}
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}
	//改状态
	public function setst(){
		$ids = input('post.ids/a');
		Db::name('help')->where('id','in',$ids)->update(['status'=>input('post.st/d')]);
		\app\commons\System::plog('帮助中心文章改状态'.implode(',',$ids),1);
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//删除
	public function del(){
		$ids = input('post.ids/a');
		Db::name('help')->where('id','in',$ids)->delete();
		\app\commons\System::plog('帮助中心文章删除'.implode(',',$ids),1);
		return json(['status'=>1,'msg'=>'删除成功']);
	}
}