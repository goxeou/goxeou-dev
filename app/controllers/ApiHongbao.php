<?php


namespace app\controllers;
use think\facade\Db;

/**
 * Class ApiHongbao
 * @package app\controllers
 * @deprecated
 */
class ApiHongbao extends Common
{
	public function initialize(){
		parent::initialize();
		$this->checklogin();
	}
	public function index(){
		$hid = input('param.id/d');
		Db::startTrans();
		$hd = Db::name('hongbao')->where('aid',aid)->where('id',$hid)->lock(true)->find();
		if(!$hd) return $this->json(['status'=>0,'msg'=>'活动不存在']);
		if($hd['status']==0) return $this->json(['status'=>0,'msg'=>'活动未开启']);
		$member = Db::name('member')->where('aid',aid)->where('id',mid)->find();
		if(!$member){
			//showmsg('未找到会员信息');
			return $this->fetch('pub/logindialog');
		}
		$gettj = explode(',',$hd['gettj']);
		if(!in_array('-1',$gettj) && !in_array($member['levelid'],$gettj)){ //不是所有人
			if(in_array('0',$gettj)){ //关注用户才能领
				if($member['subscribe']!=1){
					$appinfo = getappinfo(aid,'mp');
					return $this->fetch('guanzhu',['img'=>$appinfo['qrcode'],'msg'=>'请先关注'.$appinfo['nickname'].'公众号']);
				}
			}else{
				return $this->json(['status'=>0,'msg'=>'您没有领取权限']);
			}
		}
		if($hd['needcode']==1 && !input('param.cjcode')){ //需要抽奖码
			$rdata = [];
			$rdata['info'] = $hd;
			return $this->fetch('indexcode',$rdata);
		}
		if($hd['needcode']==1 && input('param.cjcode')){
			$codeinfo = Db::name('hongbao_codelist')->where('hid',$hid)->where('code',input('param.cjcode'))->find();
			if(!$codeinfo) return $this->json(['status'=>0,'msg'=>'抽奖码不正确']);
			if($codeinfo['status'] != 0){
				return $this->json(['status'=>0,'msg'=>'该抽奖码已使用']);
			}
		}
		$hdstatus = 1;
		//已领奖次数
		$mynum = Db::name('hongbao_record')->where('aid',aid)->where('hid',$hid)->where('mid',mid)->count();
		if($mynum >= $hd['pernum']){ //已经领过了
			$hdstatus = 4;
			$msg = '您已经抢过了';
			Db::commit(); //解锁
		}elseif($hd['starttime']>time()){//活动未开始
			$hdstatus = 0;
			$msg = '活动还没开始';
			Db::commit(); //解锁
		}elseif($hd['endtime']<time()){//活动已结束
			$hdstatus = 2;
			$msg = '活动已经结束';
			Db::commit(); //解锁
		}elseif($hd['givenmoney'] >= $hd['totalmoney'] || $hd['givenmoney']+$hd['minmoney'] > $hd['totalmoney']){ //达到总发放金额
			$hdstatus = 3;
			$msg = '红包已经被抢完了';
			Db::commit(); //解锁
		}else{
			//计算金额
			srand(microtime(true) * 1000);
			$ss = rand($hd['minmoney']*100,$hd['maxmoney']*100).PHP_EOL;
			$money = number_format($ss/100, 2, '.', '');
			
			if($hd['totalmoney'] - $hd['givenmoney'] < $money) $money = $hd['totalmoney'] - $hd['givenmoney'];
			
			Db::name('hongbao')->where('aid',aid)->where('id',$hid)->update(['givenmoney'=>Db::raw("givenmoney+{$money}")]);
			Db::commit(); //解锁
			
			//发红包
			$rs = \app\commons\Wxpay::sendredpackage(aid,mid,platform,$money,mb_substr($hd['name'],0,10),$hd['sendname'],$hd['wishing'],'微信红包',$hd['scene_id']);
			if($rs['status']==0){ //发放失败
				$hdstatus = 10;
				$data = [];
				$data['aid'] = aid;
				$data['hid'] = $hid;
				$data['name'] = $hd['name'];
				$data['mid'] = mid;
				$data['headimg'] = $member['headimg'];
				$data['nickname'] = $member['nickname'];
				$data['money'] = $money;
				$data['createtime'] = time();
				$data['status'] = 2;
				$data['remark'] = $rs['msg'];
				Db::name('hongbao_record')->insert($data);
				$msg = '很遗憾没有抢到';
			}else{
				$data = [];
				$data['aid'] = aid;
				$data['hid'] = $hid;
				$data['name'] = $hd['name'];
				$data['mid'] = mid;
				$data['headimg'] = $member['headimg'];
				$data['nickname'] = $member['nickname'];
				$data['money'] = $money;
				$data['createtime'] = time();
				$data['status'] = 1;
				$data['remark'] = '发放成功';
				Db::name('hongbao_record')->insert($data);
			}
			if($hd['needcode']==1 && input('param.cjcode')){
				Db::name('hongbao_codelist')->where('hid',$hid)->where('code',input('param.cjcode'))->update(['status'=>1,'usetime'=>time(),'mid'=>mid,'headimg'=>$member['headimg'],'nickname'=>$member['nickname'],'money'=>$money,'remark'=>$data['remark']]);
			}
		}
		$rdata = [];
		$rdata['st'] = $hdstatus;
		$rdata['msg'] = $msg;
		$rdata['money'] = $money;
		$rdata['info'] = $hd;
		return $this->json($rdata);
	}
}