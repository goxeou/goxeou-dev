<?php


// +----------------------------------------------------------------------
// | 自动回复
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class Mpkeyword extends Common
{
    public function initialize(){
		parent::initialize();
		if(bid > 0) showmsg('无访问权限');
	}
	//关注回复
	public function subscribe(){
		//$set = Db::name('admin_set')->where('aid',aid)->find();
		//if($set['mpisauth']==0 && ($set['mpappid']=='' || $set['mpappsecret']=='')){
		//	showmsg('请先授权公众号',(string)url('Shouquan/index'));
		//}
		$info = Db::name('mp_keyword')->where('aid',aid)->where('ktype',2)->find();
		if(!$info) $info = ['id'=>''];
		if($info['msgtype'] == 'text'){
			$text = $info['content'];
		}elseif($info['msgtype'] == 'image'){
			$image = json_decode($info['content'],true);
		}elseif($info['msgtype'] == 'voice'){
			$voice = json_decode($info['content'],true);
		}elseif($info['msgtype'] == 'video'){
			$video = json_decode($info['content'],true);
		}elseif($info['msgtype'] == 'music'){
			$music = json_decode($info['content'],true);
		}elseif($info['msgtype'] == 'news'){
			$news = json_decode($info['content'],true);
		}
		View::assign('info',$info);
		View::assign('text',$text);
		View::assign('image',$image);
		View::assign('voice',$voice);
		View::assign('video',$video);
		View::assign('music',$music);
		View::assign('news',$news);
		return View::fetch();
	}
	//列表
    public function index(){
		//$set = Db::name('admin_set')->where('aid',aid)->find();
		//if($set['mpisauth']==0 && ($set['mpappid']=='' || $set['mpappsecret']=='')){
		//	showmsg('请先授权公众号',(string)url('Shouquan/index'));
		//}
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
			$count = 0 + Db::name('mp_keyword')->where($where)->count();
			$data = Db::name('mp_keyword')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
    }
	//编辑
	public function edit(){
		if(input('param.id')){
			$info = Db::name('mp_keyword')->where('aid',aid)->where('id',input('param.id/d'))->find();
		}else{
			$info = array('id'=>'');
		}
		if($info['msgtype'] == 'text'){
			$text = $info['content'];
		}elseif($info['msgtype'] == 'image'){
			$image = json_decode($info['content'],true);
		}elseif($info['msgtype'] == 'voice'){
			$voice = json_decode($info['content'],true);
		}elseif($info['msgtype'] == 'video'){
			$video = json_decode($info['content'],true);
		}elseif($info['msgtype'] == 'music'){
			$music = json_decode($info['content'],true);
		}elseif($info['msgtype'] == 'news'){
			$news = json_decode($info['content'],true);
		}
		View::assign('info',$info);
		View::assign('text',$text);
		View::assign('image',$image);
		View::assign('voice',$voice);
		View::assign('video',$video);
		View::assign('music',$music);
		View::assign('news',$news);
		return View::fetch();
	}
	public function save(){
		$info = input('post.info/a');
		if($info['msgtype'] == 'text'){
			$info['content'] = $_POST['text'];
		}elseif($info['msgtype'] == 'image'){
			$image = $_POST['image'];
			$image['MediaId'] = \app\commons\Wechat::getmediaid(aid,$image['url']);
			$info['content'] = jsonEncode($image);
		}elseif($info['msgtype'] == 'voice'){
			$voice = $_POST['voice'];
			$voice['MediaId'] = \app\commons\Wechat::getmediaid(aid,$voice['url'],'voice');
			$info['content'] = jsonEncode($voice);
		}elseif($info['msgtype'] == 'video'){
			$video = $_POST['video'];
			$video['MediaId'] = \app\commons\Wechat::getmediaid(aid,$video['url'],'video',jsonEncode(['title'=>$video['title'],'introduction'=>$video['description']]));
			$info['content'] = jsonEncode($video);
		}elseif($info['msgtype'] == 'music'){
			$music = $_POST['music'];
			$info['content'] = jsonEncode($music);
		}elseif($info['msgtype'] == 'news'){
			$news = $_POST['news'];
			$info['content'] = jsonEncode($news);
		}
		if($info['id']){
			Db::name('mp_keyword')->where('aid',aid)->where('id',$info['id'])->update($info);
			\app\commons\System::plog('编辑公众号关键字回复'.$info['id']);
		}else{
			$info['aid'] = aid;
			$info['createtime'] = time();
			$id = Db::name('mp_keyword')->insertGetId($info);
			\app\commons\System::plog('添加公众号关键字回复'.$id);
		}
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}
	//删除
	public function del(){
		$ids = input('post.ids/a');
		Db::name('mp_keyword')->where('aid',aid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('删除公众号关键字回复'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
}
