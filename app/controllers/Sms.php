<?php


// +----------------------------------------------------------------------
// | 短信管理
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class Sms extends Common
{
    public function initialize(){
		parent::initialize();
		if(bid > 0) showmsg('无访问权限');
	}
	//短信配置
    public function set(){
		if(request()->isPost()){
			$data = input('post.info/a');
//			$data['accesskey'] = trim($data['accesskey']);
//			$data['accesssecret'] = trim($data['accesssecret']);
//			$data['sdkappid'] = trim($data['sdkappid']);
            if($data){
                foreach ($data as $k => $v){
                    $data[$k] = trim($v);
                }
            }
			Db::name('admin_set_sms')->where('aid',aid)->update($data);
			\app\commons\System::plog('短信设置');
			return json(['code'=>0,'msg'=>'操作成功']);
		}
		$info = Db::name('admin_set_sms')->where('aid',aid)->find();
		View::assign('info',$info);
        View::assign('restaurant',getcustom('restaurant'));
        return View::fetch();
    }
	//发送记录
	public function sendlog(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
				$order = 'id desc';
			}
			$where = [];
			$where[] = ['aid','=',aid];
			if(input('param.ctime') ){
				$ctime = explode(' ~ ',input('param.ctime'));
				$where[] = ['createtime','>=',strtotime($ctime[0])];
				$where[] = ['createtime','<',strtotime($ctime[1]) + 86400];
			}
			$count = 0 + Db::name('smslog')->where($where)->count();
			$data = Db::name('smslog')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
	}
}