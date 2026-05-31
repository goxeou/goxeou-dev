<?php


// +----------------------------------------------------------------------
// | 公共接口
// +----------------------------------------------------------------------
namespace app\controllers;
use app\BaseController;
use think\facade\Db;
class ApiBase extends BaseController
{
	public $aid;
	public $mid;
	public $member;
	public $indexurl;
	public $sysset;
    public $admin;
    public function initialize(){

		$request = request();
		if(!in_array($request->controller(),['ApiAdminProduct','ApiAdminRestaurantProduct'])){
			\think\facade\Request::filter(['strip_tags','htmlspecialchars']);
		}
		
		//die(json_encode(['status'=>0,'msg'=>'test']));
        $aid = input('param.aid/d');
		if(!$aid) die(jsonEncode(['status'=>0,'msg'=>'参数错误']));
		$admin = Db::name('admin')->where('id',$aid)->find();
		if(!$admin) die(jsonEncode(['status'=>0,'msg'=>'参数错误']));
		if($admin['status'] == 0 ) die(jsonEncode(['status'=>0,'msg'=>'账号未启用']));//控制台-用户列表 编辑
		if($admin['endtime'] < time()) die(jsonEncode(['status'=>0,'msg'=>'账号过期']));
        $this->admin = $admin;

		$platform = input('param.platform');
		if($platform && !in_array($platform,['mp','wx','alipay','baidu','toutiao','qq','h5','app'])) die(jsonEncode(['status'=>0,'msg'=>'参数错误']));
		if($platform){
			define('platform',$platform);
		}else{
			if(!is_weixin()){
				define('platform','h5');
			}else{
				define('platform','mp');
			}
		}
		if(input('param.isdouyin') == 1){
			$douyinset = Db::name('douyin_sysset')->where('aid',$aid)->find();
			if($douyinset['status'] == 1){
				define('isdouyin',1);
			}else{
				define('isdouyin',0);
			}
		}else{
			define('isdouyin',0);
		}
		$this->aid = $aid;
		define('aid',$aid);
    }
}