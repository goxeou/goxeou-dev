<?php


// +----------------------------------------------------------------------
// | 小程序关键字回复
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class Wxkeyword extends Common
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
				$order = 'sort desc,id';
			}
			$where = array();
			$where[] = ['aid','=',aid];
			$where[] = ['ktype','in','0,1'];
			if(input('param.keyword')) $where[] = ['keyword','like','%'.input('param.keyword').'%'];
			$count = 0 + Db::name('wx_keyword')->where($where)->count();
			$data = Db::name('wx_keyword')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
    }
	//编辑
	public function edit(){
		if(input('param.id')){
			$info = Db::name('wx_keyword')->where('aid',aid)->where('id',input('param.id/d'))->find();
		}else{
			$info = array('id'=>'');
		}
		if($info['msgtype'] == 'text'){
			$text = $info['content'];
		}elseif($info['msgtype'] == 'image'){
			$image = json_decode($info['content'],true);
		}elseif($info['msgtype'] == 'link'){
			$link = json_decode($info['content'],true);
		}elseif($info['msgtype'] == 'miniprogrampage'){
			$miniprogrampage = json_decode($info['content'],true);
		}
		View::assign('info',$info);
		View::assign('text',$text);
		View::assign('image',$image);
		View::assign('link',$link);
		View::assign('miniprogrampage',$miniprogrampage);
		return View::fetch();
	}
	public function save(){
		$info = input('post.info/a');
		if($info['msgtype'] == 'text'){
			$info['content'] = $_POST['text'];
		}elseif($info['msgtype'] == 'image'){
			$image = $_POST['image'];
			$info['content'] = jsonEncode($image);
		}elseif($info['msgtype'] == 'link'){
			$link = $_POST['link'];
			$info['content'] = jsonEncode($link);
		}elseif($info['msgtype'] == 'miniprogrampage'){
			$miniprogrampage = $_POST['miniprogrampage'];
			$info['content'] = jsonEncode($miniprogrampage);
		}
		if($info['id']){
			Db::name('wx_keyword')->where('aid',aid)->where('id',$info['id'])->update($info);
			\app\commons\System::plog('编辑小程序关键字回复'.$info['id']);
		}else{
			$info['aid'] = aid;
			$info['createtime'] = time();
			$id = Db::name('wx_keyword')->insertGetId($info);
			\app\commons\System::plog('添加小程序关键字回复'.$id);
		}
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}
	//删除
	public function del(){
		$ids = input('post.ids/a');
		Db::name('wx_keyword')->where('aid',aid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('删除小程序关键字回复'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
}