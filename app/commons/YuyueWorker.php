<?php


namespace app\commons;
use think\facade\Db;
class YuyueWorker
{
	//加余额
	public static function addmoney($aid,$bid,$uid,$money,$remark,$addtotal=1){
		if($money==0) return ;
		$user = Db::name('yuyue_worker')->where('aid',$aid)->where('id',$uid)->find();
		if(!$user) return ['status'=>0,'msg'=>'服务人员不存在'];

		if($money > 0 && $addtotal==1){
			$totalmoney = $user['totalmoney'] + $money;
		}else{
			$totalmoney = $user['totalmoney'];
		}
		$after = $user['money'] + $money;
		Db::name('yuyue_worker')->where('aid',$aid)->where('id',$uid)->update(['totalmoney'=>$totalmoney,'money'=>$after]);
		
		$data = [];
		$data['aid'] = $aid;
		$data['uid'] = $uid;
		$data['bid'] = $bid;
		$data['money'] = $money;
		$data['after'] = $after;
		$data['createtime'] = time();
		$data['remark'] = $remark;
		Db::name('yuyue_worker_moneylog')->insert($data);
		return ['status'=>1,'msg'=>''];
	}
}