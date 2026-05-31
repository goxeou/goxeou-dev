<?php



//排队大屏
namespace app\controllers;

use app\BaseController;
use think\facade\Db;
use think\facade\View;

class RestaurantQueueScreen extends BaseController
{
    public function index()
    {
        $aid = input('param.aid/d');
        $bid = input('param.bid/d');
		$queueset = Db::name('restaurant_queue_sysset')->where('aid',$aid)->where('bid',$bid)->find();

		$lastqueue = Db::name('restaurant_queue')->where('aid',$aid)->where('bid',$bid)->where('date',date('Y-m-d'))->where('status',1)->where('call_time','>',time()-300)->order('call_time desc')->find();
		if(!$lastqueue){
			$lastqueue = ['queue_no'=>'--','call_text'=>'当前叫号'];
		}
		
		$lastqueuelist = Db::name('restaurant_queue')->where('aid',$aid)->where('bid',$bid)->where('date',date('Y-m-d'))->where('status',0)->order('create_time')->select()->toArray();
		if(!$lastqueuelist) $lastqueuelist = [];
		for($i=0;$i<12;$i++){
			if(!$lastqueuelist[$i]) $lastqueuelist[$i] = [];
		}
		$nomal_list = true;
		View::assign('nomal_list',$nomal_list);
		
		$config = include(ROOT_PATH.'config.php');
		$authtoken = $config['authtoken'];

		View::assign('token',md5(md5($authtoken.$aid.$bid)));
		View::assign('aid',$aid);
		View::assign('bid',$bid);

		View::assign('lastqueue',$lastqueue);
		View::assign('queueset',$queueset);
		View::assign('lastqueuelist',$lastqueuelist);
	
        return View::fetch();
    }

	//获取叫号列表
	public function getqueuelist(){
        $aid = input('param.aid/d');
        $bid = input('param.bid/d');
		if(input('param.call_id/d')){
			$lastqueue = Db::name('restaurant_queue')->where('aid',$aid)->where('id',input('param.call_id/d'))->find();
		}else{
			$lastqueue = Db::name('restaurant_queue')->where('aid',$aid)->where('bid',$bid)->where('date',date('Y-m-d'))->where('status',1)->where('call_time','>',time()-300)->order('call_time desc')->find();
		}
		if(!$lastqueue){
			$lastqueue = ['queue_no'=>'--','call_text'=>'当前叫号'];
		}

		$lastqueuelist = Db::name('restaurant_queue')->where('aid',$aid)->where('date',date('Y-m-d'))->where('status',0)->order('create_time')->select()->toArray();
	
		if(!$lastqueuelist) $lastqueuelist = [];
		for($i=0;$i<16;$i++){
			if(!$lastqueuelist[$i]) $lastqueuelist[$i] = [];
		}
		
        return json(['status'=>1,'lastqueue'=>$lastqueue,'lastqueuelist'=>$lastqueuelist]);
	}
}