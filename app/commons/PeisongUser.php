<?php


namespace app\commons;
use think\facade\Db;
class PeisongUser
{
	//加余额
	public static function addmoney($aid,$uid,$money,$remark,$addtotal=1){
		if($money==0) return ;
		$user = Db::name('peisong_user')->where('aid',$aid)->where('id',$uid)->find();
		if(!$user) return ['status'=>0,'msg'=>'配送员不存在'];

		if($money > 0 && $addtotal==1){
			$totalmoney = $user['totalmoney'] + $money;
		}else{
			$totalmoney = $user['totalmoney'];
		}
		$after = $user['money'] + $money;
		Db::name('peisong_user')->where('aid',$aid)->where('id',$uid)->update(['totalmoney'=>$totalmoney,'money'=>$after]);
		
		$data = [];
		$data['aid'] = $aid;
		$data['uid'] = $uid;
		$data['money'] = $money;
		$data['after'] = $after;
		$data['createtime'] = time();
		$data['remark'] = $remark;
		Db::name('peisong_moneylog')->insert($data);
		return ['status'=>1,'msg'=>''];
	}
}