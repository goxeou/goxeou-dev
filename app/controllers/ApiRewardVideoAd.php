<?php


namespace app\controllers;
use think\facade\Db;
class ApiRewardVideoAd extends ApiCommon
{
	//获取广告位ID
	public function getunitid(){
		$hid = input('post.hid/d');
		if(!$hid) return $this->json(['status'=>0,'msg'=>'未找到该广告记录']);
		$info = Db::name('designerpage_rwvideoad')->where('aid',aid)->where('id',$hid)->find();
		if(!$info) return $this->json(['status'=>0,'msg'=>'未找到该广告记录']);
		return $this->json(['status'=>1,'unitid'=>$info['unitid']]);
	}
	//给奖励
	public function givereward(){
		$this->checklogin();
		$hid = input('post.hid/d');
		if(!$hid) return $this->json(['status'=>0,'msg'=>'未找到该奖励记录']);
		$info = Db::name('designerpage_rwvideoad')->where('aid',aid)->where('id',$hid)->find();
		if(!$info) return $this->json(['status'=>0,'msg'=>'未找到该奖励记录']);

		$givescore = $info['givescore'];
		$givemoney = $info['givemoney'];

		$totalnum = Db::name('designerpage_rwvideoad_record')->where('aid',aid)->where('mid',mid)->where('hid',$hid)->sum('givetimes');
		if($totalnum >= $info['givetimestotal']){
			return $this->json(['status'=>0,'msg'=>'每人最多只能获得'.$info['givetimestotal'].'次奖励']);
		}

		$record = Db::name('designerpage_rwvideoad_record')->where('aid',aid)->where('mid',mid)->where('hid',$hid)->where('createdate',date('Y-m-d'))->find();
		if($record){
			if($record['givetimes'] >= $info['givetimes']) return $this->json(['status'=>0,'msg'=>'您今日已获得过奖励了']);
			if($givescore > 0 && $givemoney > 0){
				Db::name('designerpage_rwvideoad_record')->where('id',$record['id'])->inc('givescore',$givescore)->inc('givemoney',$givemoney)->inc('givetimes',1)->update();
			}elseif($givescore > 0){
				Db::name('designerpage_rwvideoad_record')->where('id',$record['id'])->inc('givescore',$givescore)->inc('givetimes',1)->update();
			}elseif($givemoney > 0){
				Db::name('designerpage_rwvideoad_record')->where('id',$record['id'])->inc('givemoney',$givemoney)->inc('givetimes',1)->update();
			}else{
				Db::name('designerpage_rwvideoad_record')->where('id',$record['id'])->inc('givetimes',1)->update();
			}
			if($info['givemoneyparent'] > 0){
				Db::name('designerpage_rwvideoad_record')->where('id',$record['id'])->inc('givemoneyparent',$info['givemoneyparent'])->update();
			}
		}else{
			$data = [];
			$data['aid'] = aid;
			$data['mid'] = mid;
			$data['hid'] = $hid;
			$data['givescore'] = $givescore;
			$data['givemoney'] = $givemoney;
			$data['givemoneyparent'] = $info['givemoneyparent'];
			$data['givetimes'] = 1;
			$data['createdate'] = date('Y-m-d');
			$data['createtime'] = time();
			Db::name('designerpage_rwvideoad_record')->insert($data);
		}
		$tips = '操作成功';
		$remark = '广告奖励';
		if($info['type'] == 1){
			$remark = '打开小程序奖励';
		}
		if($givescore > 0){
			\app\commons\Member::addscore(aid,mid,$givescore,$remark);
			$tips = '恭喜您获得'.$givescore.t('积分').'奖励';
		}
		if($givemoney > 0){
			\app\commons\Member::addmoney(aid,mid,$givemoney,$remark);
			$tips = '恭喜您获得'.$givemoney.t('余额').'奖励';
		}
		if($info['givemoneyparent'] && $this->member['pid']){
			\app\commons\Member::addmoney(aid,$this->member['pid'],$info['givemoneyparent'],t('下级').$remark);
		}
		if($givescore > 0 && $givemoney > 0) $tips = '恭喜您获得'.$givemoney.t('余额').'+'.$givescore.t('积分').'奖励';
		$rad_url='';
		return $this->json(['status'=>1,'msg'=>$tips,'url'=>$rad_url]);
	}

}