<?php


namespace app\controllers;
use think\facade\Db;

class ApiFifa extends ApiCommon
{
	public function initialize(){
		parent::initialize();
		//$this->checklogin();
	}
	public function index(){
		$set = Db::name('fifa_set')->where('aid',aid)->find();
		if(!$set || $set['status'] == 0) return json(['status'=>-4,'msg'=>'活动未开启']);
		$datalist = Db::name('fifa')->where('1=1')->order('id')->select()->toArray();
		
		$selectdate = date('m-d');
		if(time() < strtotime(date('2022-11-21'))) $selectdate = '11-21';
		if(time() > strtotime(date('2022-12-18'))) $selectdate = '12-18';
		$newdatalist = [];
		foreach($datalist as $data){
			if(!isset($newdatalist[$data['startDate']])){
				$newdatalist[$data['startDate']] = ['date'=>$data['startDate'],'week'=>$this->getweek(date('w',strtotime(date('Y').'-'.$data['startDate']))),'data'=>[]];
			}
			$newdatalist[$data['startDate']]['data'][] = $data;
		}
		
		if($this->member){
			$successnum = Db::name('fifa_record')->where('aid',aid)->where('mid',mid)->whereRaw('guess1st=1 or guess2st=1')->count();
			$winscore = Db::name('fifa_record')->where('aid',aid)->where('mid',mid)->sum('givescore1') + Db::name('fifa_record')->where('aid',aid)->where('mid',mid)->sum('givescore2');
		}else{
			$successnum = '-';
			$winscore = '-';
		}

		$rdata = [];
		$rdata['datalist'] = $newdatalist;
		$rdata['myscore'] = $this->member ? $this->member['score'] : '-';
		$rdata['successnum'] = $successnum;
		$rdata['winscore'] = $winscore;
		$rdata['selectdate'] = $selectdate;
		return $this->json($rdata);
	}

	public function detail(){
		$set = Db::name('fifa_set')->where('aid',aid)->find();
		if(!$set || $set['status'] == 0) return json(['status'=>-4,'msg'=>'活动未开启']);

		$hid = input('param.id/d');
		$detail = Db::name('fifa')->where('id',$hid)->find();
		if($this->member){
			$myrecord = Db::name('fifa_record')->where('aid',aid)->where('mid',mid)->where('hid',$hid)->find();
			if(!$myrecord) $myrecord = [];
		}else{
			$myrecord = [];
		}

		$guessStatus = 0;
		if($detail['startTimestamp'] > time() + $set['starthour'] * 3600){
			$guessStatus = 1;
		}
		if($detail['startTimestamp'] < time()){
			$guessStatus = 2;
		}

		$rdata = [];
		$rdata['detail'] = $detail;
		$rdata['myrecord'] = $myrecord;
		$rdata['sset'] = $set;
		$rdata['guessStatus'] = $guessStatus;
		return $this->json($rdata);
	}

	//提交竞猜
	public function subguess(){
		$this->checklogin();
		$hid = input('param.hid/d');
		$guesstype = input('param.guesstype/d');
		$guess = input('param.guess');
		$set = Db::name('fifa_set')->where('aid',aid)->find();
		if(!$set || $set['status'] == 0) return json(['status'=>-4,'msg'=>'活动未开启']);

		$detail = Db::name('fifa')->where('id',$hid)->find();

		if($detail['startTimestamp'] > time() + $set['starthour'] * 3600){
			return json(['status'=>0,'msg'=>'本场竞猜尚未开始']);
		}
		if($detail['startTimestamp'] < time()){
			return json(['status'=>0,'msg'=>'本场竞猜已结束']);
		}

		$myrecord = Db::name('fifa_record')->where('aid',aid)->where('mid',mid)->where('hid',$hid)->find();
		
		if($guesstype == 1){
			if($myrecord && $myrecord['guess1']){
				return json(['status'=>0,'msg'=>'本场您已竞猜过了']);
			}
		}else{
			if($myrecord && $myrecord['guess2']){
				return json(['status'=>0,'msg'=>'本场您已竞猜过了']);
			}
		}

		if($myrecord){
			if($guesstype == 1){
				Db::name('fifa_record')->where('id',$myrecord['id'])->update(['guess1'=>$guess]);
			}else{
				Db::name('fifa_record')->where('id',$myrecord['id'])->update(['guess2'=>$guess]);
			}
		}else{
			$data = [];
			$data['aid'] = aid;
			$data['hid'] = $hid;
			$data['mid'] = mid;
			$data['headimg'] = $this->member['headimg'];
			$data['nickname'] = $this->member['nickname'];
			if($guesstype == 1){
				$data['guess1'] = $guess;
			}else{
				$data['guess2'] = $guess;
			}
			$data['createtime'] = time();
			Db::name('fifa_record')->insert($data);
		}
		return json(['status'=>1,'msg'=>'竞猜成功']);
	}

	//海报
	public function getposter(){
		$this->checklogin();
		$post = input('post.');
		$platform = platform;
		$page = '/pagesExt/fifa/index';
		$scene = 'pid_'.$this->member['id'];
		//if($platform == 'mp' || $platform == 'h5' || $platform == 'app'){
		//	$page = PRE_URL .'/h5/'.aid.'.html#'. $page;
		//}
		$posterset = Db::name('admin_set_poster')->where('aid',aid)->where('type','fifa')->where('platform',$platform)->order('id')->find();

		$successnum = Db::name('fifa_record')->where('aid',aid)->where('mid',mid)->whereRaw('guess1st=1 or guess2st=1')->count();
		$winscore = Db::name('fifa_record')->where('aid',aid)->where('mid',mid)->sum('givescore1') + Db::name('fifa_record')->where('aid',aid)->where('mid',mid)->sum('givescore2');

//		$posterdata = Db::name('member_poster')->where('aid',aid)->where('mid',mid)->where('scene',$scene)->where('type','fifa')->where('posterid',$posterset['id'])->find();
		if(true || !$posterdata){
			$textReplaceArr = [
				'[头像]'=>$this->member['headimg'],
				'[昵称]'=>$this->member['nickname'],
				'[姓名]'=>$this->member['realname'],
				'[手机号]'=>$this->member['mobile'],
				'[猜中场次]'=>$successnum,
				'[已获得积分]'=>$winscore,
			];

			$poster = $this->_getposter(aid,0,$platform,$posterset['content'],$page,$scene,$textReplaceArr);
			$posterdata = [];
			$posterdata['aid'] = aid;
			$posterdata['mid'] = $this->member['id'];
			$posterdata['scene'] = $scene;
			$posterdata['page'] = $page;
			$posterdata['type'] = 'fifa';
			$posterdata['poster'] = $poster;
			$posterdata['createtime'] = time();
			Db::name('member_poster')->insert($posterdata);
		}
		return $this->json(['status'=>1,'poster'=>$posterdata['poster']]);
	}

	public function getweek($week){
		if($week == 0) return '星期天';
		if($week == 1) return '星期一';
		if($week == 2) return '星期二';
		if($week == 3) return '星期三';
		if($week == 4) return '星期四';
		if($week == 5) return '星期五';
		if($week == 6) return '星期六';
		return '';
	}
}