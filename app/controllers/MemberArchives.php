<?php


// +----------------------------------------------------------------------
// | 会员档案信息录入
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class MemberArchives extends Common
{
	public function initialize(){
		parent::initialize();
		if(bid > 0) showmsg('无访问权限');
	}
	//列表
	public function index(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
				$order = 'sort desc,id desc';
			}
			$where = array();
			$where[] = ['aid','=',aid];
			if(input('param.mid')) $where[] = ['mid','=',input('param.mid/d')];
			if(input('param.name')) $where[] = ['name','like','%'.input('param.name').'%'];
			if(input('?param.status') && input('param.status')!=='') $where[] = ['status','=',input('param.status')];

			if(input('param.ctime') ){
				$ctime = explode(' ~ ',input('param.ctime'));
				$where[] = ['createtime','>=',strtotime($ctime[0])];
				$where[] = ['createtime','<',strtotime($ctime[1]) + 86400];
			}
			$count = 0 + Db::name('member_archives')->where($where)->count();
			$data = Db::name('member_archives')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			
			foreach($data as $k=>$v){
				$member = Db::name('member')->where('aid',aid)->where('id',$v['mid'])->find();
				$data[$k]['headimg'] = $member['headimg'];
				$data[$k]['nickname'] = $member['nickname'];
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}

		return View::fetch();
	}
	//编辑文章
	public function edit(){
		if(input('param.id')){
			$info = Db::name('member_archives')->where('aid',aid)->where('id',input('param.id/d'))->find();
		}else{
			$info = ['id'=>'','mid'=>input('param.mid'),'createtime'=>time()];
		}
		View::assign('info',$info);
		return View::fetch();
	}
	//保存
	public function save(){
		$info = input('post.info/a');
		$info['content'] = \app\commons\Common::geteditorcontent($info['content']);
		$info['createtime'] = strtotime($info['createtime']);
		if($info['id']){
			Db::name('member_archives')->where('aid',aid)->where('id',$info['id'])->update($info);
		}else{
			$info['aid'] = aid;
			Db::name('member_archives')->insert($info);
		}
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}
	//删除
	public function del(){
		$ids = input('post.ids/a');
		Db::name('member_archives')->where('aid',aid)->where('id','in',$ids)->delete();
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	//设置状态
	public function setst(){
		$aid = $this->aid;
		$ids = input('post.ids/a');
		Db::name('member_archives')->where('aid',aid)->where('id','in',$ids)->update(['status'=>input('post.st/d')]);
		return json(['status'=>1,'msg'=>'操作']);
	}
}