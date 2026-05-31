<?php


// +----------------------------------------------------------------------
// | 订阅消息设置
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class Wxtmpl extends Common
{	
    public function initialize(){
		parent::initialize();
		if(bid > 0) showmsg('无访问权限');
	}
	//模板消息设置
	public function tmplset(){
		if(request()->isPost()){
			$rs = Db::name('wx_tmplset')->where('aid',aid)->find();
			$info = input('post.info/a');
			if($rs){
				Db::name('wx_tmplset')->where('aid',aid)->update($info);
				\app\commons\System::plog('设置小程序订阅消息');
			}else{
				$info['aid'] = aid;
				Db::name('wx_tmplset')->insert($info);
				\app\commons\System::plog('设置小程序订阅消息');
			}
			return json(['status'=>1,'msg'=>'设置成功','url'=>(string)url()]);
		}
		$info = Db::name('wx_tmplset')->where('aid',aid)->find();
		if(!$info){
			$set = Db::name('admin_set')->where('aid',aid)->find();
			Db::name('wx_tmplset')->insert(['aid'=>aid]);
			$info = Db::name('wx_tmplset')->where('aid',aid)->find();
		}
		View::assign('info',$info);
		return View::fetch();
	}
	//获取模板ID
	public function gettmplid(){
		$template_no = input('post.template_no');

		$keywordArr = explode(',',input('post.keywords'));
		//dump($keywordArr);
		$kidList = [];
		foreach($keywordArr as $k=>$v){
			$kidList[] = intval($v);
		}
		$access_token = \app\commons\Wechat::access_token(aid,'wx');
		$data = array();
		$data['tid'] = $template_no;
		$data['kidList'] = $kidList;
		$data['sceneDesc'] = '1';
		$res = curl_form_post('https://api.weixin.qq.com/wxaapi/newtmpl/addtemplate?access_token='.$access_token,$data);	
		//dump($data);
		//dump(jsonEncode($data));
		//dump($res);
		$res = json_decode($res,true);
		if($res['errcode']!=0){
			if($res['errcode'] == 45026){
				return json(['status'=>0,'msg'=>'模板数超过最大限制,请先删除一些再添加']);
			}
			return json(['status'=>0,'msg'=>$res['errcode'].'：'.$res['errmsg']]);
		}else{
			return json(['status'=>1,'data'=>$res['priTmplId'],'msg'=>'添加成功']);
		}
	}
}