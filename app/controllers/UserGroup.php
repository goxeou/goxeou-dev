<?php


// +----------------------------------------------------------------------
// | 后台账号 账号权限组
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class UserGroup extends Common
{	
	public $type = 0;//类型 0：默认 1：仅商户
    public function initialize(){
		parent::initialize();
		if($this->user['isadmin'] <=0) die('无权限操作');
		}
	//权限组列表
    public function index(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
				$order = 'sort desc,id';
			}
			$where = array();
			$where[] = ['aid','=',aid];
			$where[] = ['bid','=',bid];
			if(input('param.name')) $where[] = ['name','like','%'.input('param.name').'%'];
			$count = 0 + Db::name('admin_user_group')->where($where)->count();
			$data = Db::name('admin_user_group')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		View::assign('aid',aid);
		View::assign('type',$this->type);
		return View::fetch();
    }
	//编辑
	public function edit(){
		if(input('param.id')){
			$where = [];
			$where[] = ['id','=',input('param.id/d')];
			$where[] = ['aid','=',aid];
			$where[] = ['bid','=',bid];
			$info = Db::name('admin_user_group')->where($where)->find();
		}else{
			$info = [];
		}
		$mendianlist = Db::name('mendian')->where('aid',aid)->where('bid',bid)->select()->toArray();
		$auth_data = $info ? json_decode($info['auth_data'],true) : array();
		if(!$auth_data) $auth_data = array();

		$uid = uid;
		$menudata = \app\commons\Menu::getdata(aid,$uid);

		$wxauth_data = $info ? json_decode($info['wxauth_data'],true) : array();
		if(!$wxauth_data) $wxauth_data = array();
		$notice_auth_data = $info ? json_decode($info['notice_auth_data'],true) : array();
		if(!$notice_auth_data) $notice_auth_data = array();
		$hexiao_auth_data = $info ? json_decode($info['hexiao_auth_data'],true) : array();
		if(!$hexiao_auth_data) $hexiao_auth_data = array();
		$wxauth_data = $info ? json_decode($info['wxauth_data'],true) : array();
		if(!$wxauth_data) $wxauth_data = array();

		if($menudata['restaurant']['child']) {
            $menudata_restaurant = collect($menudata['restaurant']['child'])->column('name');
            View::assign('menudata_restaurant',$menudata_restaurant);
        }

		View::assign('auth_data',$auth_data);
		View::assign('notice_auth_data',$notice_auth_data);
		View::assign('hexiao_auth_data',$hexiao_auth_data);
		View::assign('wxauth_data',$wxauth_data);
		View::assign('menudata',$menudata);
		View::assign('info',$info);
		View::assign('mendianlist',$mendianlist);
        View::assign('thisuser',$this->user);
        View::assign('thisuser_showtj',$this->user['showtj']==1 || $this->user['isadmin']>0 ? 1 : 0);
        View::assign('thisuser_mdid',$this->user['mdid']);
        View::assign('thisuser_wxauth',json_decode($this->user['wxauth_data'],true));
        View::assign('thisuser_notice_auth',json_decode($this->user['notice_auth_data'],true));
        View::assign('thisuser_hexiao_auth',json_decode($this->user['hexiao_auth_data'],true));
        View::assign('restaurant_auth',strpos($this->user['wxauth_data'],'restaurant') !== false ? true : false);

        View::assign('type',$this->type);
		return View::fetch();
	}
	public function save(){
		$info = input('post.info/a');
		$info['auth_data'] = str_replace('^_^','\/*',jsonEncode(input('post.auth_data/a')));
		$info['notice_auth_data'] = jsonEncode(input('post.notice_auth_data/a'));
		$info['hexiao_auth_data'] = jsonEncode(input('post.hexiao_auth_data/a'));
		$info['wxauth_data'] = jsonEncode(input('post.wxauth_data/a'));
		if($info['id']){
			Db::name('admin_user_group')->where('id',$info['id'])->update($info);
			\app\commons\System::plog('编辑权限组'.$info['id']);
		}else{
			$info['aid'] = aid;
			$info['bid'] = bid;
			$info['createtime'] = time();
			$id = Db::name('admin_user_group')->insertGetId($info);
			\app\commons\System::plog('添加权限组'.$id);
		}
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}
	//删除
	public function del(){
		$id = input('post.id');
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['bid','=',bid];
		if(is_array($id)){
			$where[] = ['id','in',$id];
			Db::name('admin_user_group')->where($where)->delete();
		}else{
			$where[] = ['id','=',intval($id)];
			Db::name('admin_user_group')->where($where)->delete();
		}
		return json(['status'=>1,'msg'=>'删除成功']);
	}
}
