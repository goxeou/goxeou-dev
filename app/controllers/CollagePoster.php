<?php


// +----------------------------------------------------------------------
// | 拼团海报
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class CollagePoster extends Common
{
	public function initialize(){
		parent::initialize();
		if(bid > 0) showmsg('无操作权限');
	}
	public function index(){
		$type = input('param.type') ? input('param.type') : $this->platform[0];
		$field = input('param.field') ? input('param.field') : 'collage';
		
		$posterset = Db::name('admin_set_poster')->where('aid',aid)->where('platform',$type)->where('type',$field)->order('id')->find();
		$posterdata = json_decode($posterset['content'],true);

		$poster_bg = $posterdata['poster_bg'];
		$poster_data = $posterdata['poster_data'];

		View::assign('type',$type);
		View::assign('field',$field);
		View::assign('poster_bg',$poster_bg);
		View::assign('poster_data',$poster_data);
		return View::fetch();
    }
	public function save(){
		$type = input('param.type') ? input('param.type') : $this->platform[0];
		$field = input('param.field') ? input('param.field') : 'collage';
		$poster_bg = input('post.poster_bg');
		$poster_data = input('post.poster_data');
		$data_index = ['poster_bg'=>$poster_bg,'poster_data'=>json_decode($poster_data)];
		$posterset = Db::name('admin_set_poster')->where('aid',aid)->where('platform',$type)->where('type',$field)->order('id')->find();
		Db::name('admin_set_poster')->where('id',$posterset['id'])->update(['content'=>json_encode($data_index)]);
		if(input('post.clearhistory') == 1){
			Db::name('member_poster')->where('aid',aid)->where('posterid',$posterset['id'])->delete();
			$msg = '保存成功';
		}else{
			$msg ='保存成功';
		}
		\app\commons\System::plog('编辑拼团分享海报');
		return json(['status'=>1,'msg'=>$msg,'url'=>true]);
	}
}