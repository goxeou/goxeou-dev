<?php


namespace app\controllers;
use think\facade\Db;
class ApiKefu extends ApiCommon
{
	public function initialize(){
		parent::initialize();
		$this->checklogin();
	}
	//首页 聊天列表
	public function index(){
		$config = include(ROOT_PATH.'config.php');
		$authtoken = $config['authtoken'];
		$token = md5(md5($authtoken.mid));
		return $this->json(['token'=>$token,'nowtime'=>time()]);
	}
	//获取聊天内容
	public function getmessagelist(){
		$pagenum = input('post.pagenum');
		$bid = input('post.bid');
		if(!$bid) $bid = 0;
		if(!$pagenum) $pagenum = 1;
		$pernum = 20;
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['bid','=',$bid];
		$where[] = ['mid','=',mid];
		$datalist = Db::name('kefu_message')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
		if(!$datalist) $datalist = [];
		if($pagenum==1 && !$datalist){ //初次进入自动发送
			if($bid == 0){
				$set = Db::name('admin_set')->where('aid',aid)->find();
			}else{
				$set = Db::name('business')->field('name,logo')->where('id',$bid)->find();
			}
			$insertdata = [];
			$insertdata['aid'] = aid;
			$insertdata['mid'] = mid;
			$insertdata['bid'] = $bid;
			$insertdata['uid'] = 0;
			$insertdata['nickname'] = $this->member['nickname'];
			$insertdata['headimg'] = $this->member['headimg'];
			$insertdata['tel'] = $this->member['tel'];
			$insertdata['unickname'] = $set['name'];
			$insertdata['uheadimg'] = $set['logo'];
			$insertdata['msgtype'] = 'template';
			$insertdata['content'] = '您好，'.$set['name'].'竭诚为您服务！请问您要咨询什么问题呢?';
			$insertdata['createtime'] = time();
			$insertdata['isreply'] = 1;
			$insertdata['isread'] = 1;
			$insertdata['platform'] = platform;
			Db::name('kefu_message')->insert($insertdata);
			$datalist = Db::name('kefu_message')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
		}
		foreach($datalist as $k=>$v){
			$datalist[$k]['showtime'] = getshowtime($v['createtime']);
			$datalist[$k]['content'] = getshowcontent($v['content']);
		}
		$datalist = array_reverse($datalist);
		if ($pagenum==1) {
		   	$templateList = Db::name('kefu_question')->where('aid',aid)->where('bid',bid)->where('status',1)->order('sort desc,id')->select()->toArray();
		}
		Db::name('kefu_message')->where($where)->where('isreply',1)->where('isread',0)->update(['isread'=>1]);
		return $this->json(['status'=>1,'data'=>$datalist,'templateList'=>$templateList]);
	}
	//改为已读
	public function isread(){
		$mid = input('post.mid/d');
		Db::name('kefu_message')->where('aid',aid)->where('mid',$mid)->where('isreply',0)->where('isread',0)->update(['isread'=>1]);
		return $this->json(['status'=>1]);
	}
}