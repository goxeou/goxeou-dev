<?php


// +----------------------------------------------------------------------
// | 码科跑腿 订单状态通知
// +----------------------------------------------------------------------
namespace app\controllers;
use app\BaseController;
use think\facade\Db;
class ApiMake extends BaseController
{
    public function initialize(){

	}
	public function notify(){
		
		//\think\facade\Log::write('-----param-----');
		//\think\facade\Log::write(input('param.'));
		$post = input('param.');
		
		$aid = $post['aid'];
		$token = $post['token'];
		$set = Db::name('peisong_set')->where('aid',$aid['aid'])->find();

		if($token != md5($set['make_appid'].$set['make_token'])){
			die('error');
		}
		
		$order = Db::name('peisong_order')->where('aid',$aid)->where('make_ordernum',$post['order_no'])->find();
		
		$time = $post['time'];
		$rider_name = $post['rider_name'];
		$rider_mobile = $post['rider_mobile'];
		$update = [];
		$update['make_rider_name'] = $rider_name;
		$update['make_rider_mobile'] = $rider_mobile;
		if($post['status'] == 'accepted'){ //接单
			$update['status'] = 1;
			$update['starttime'] = $time;
		}elseif($post['status'] == 'wait_to_shop'){ //到店
			$update['status'] = 2;
			$update['daodiantime'] = $time;
		}elseif($post['status'] == 'geted'){ //取货
			$update['status'] = 3;
			$update['quhuotime'] = $time;
		}elseif($post['status'] == 'gotoed'){ //完成
			$update['status'] = 4;
			$update['endtime'] = $time;
		}
		Db::name('peisong_order')->where('aid',$aid)->where('make_ordernum',$post['order_no'])->update($update);

		if(getcustom('paotui')){
			\app\customs\PaotuiCustom::change_status($order,$rider_name,$rider_mobile,$time);
		}

		die('success');
	}
	
}