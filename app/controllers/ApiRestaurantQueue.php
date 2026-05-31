<?php


namespace app\controllers;
use think\facade\Db;
class ApiRestaurantQueue extends ApiCommon{
    public function initialize(){
		parent::initialize();
	}
	
	//排队首页
	public function index(){
        $this->checklogin();
		$bid = input('param.bid');
		if(!$bid) $bid = 0;
		if($bid!=0){
			$business = Db::name('business')->where('aid',aid)->where('id',$bid)->field('id,name,logo,content,pics,desc,tel,address,sales,start_hours,end_hours')->find();
			$business['pic'] = explode(',',$business['pics'])[0];
		}else{
			$business = Db::name('admin_set')->where('aid',aid)->field('id,name,logo,desc,tel,address')->find();
		}
		$queue_set = Db::name('restaurant_queue_sysset')->where('aid',aid)->where('bid',$bid)->find();
		if($queue_set['status']==0){
			return $this->json(['status'=>0,'msg'=>'该商家未开启排队']);
		}
		if($queue_set['start_hours'] != $queue_set['end_hours']){
			$start_time = strtotime(date('Y-m-d '.$queue_set['start_hours']));
			$end_time = strtotime(date('Y-m-d '.$queue_set['end_hours']));
			if(($start_time < $end_time && ($start_time > time() || $end_time < time())) || ($start_time >= $end_time && ($start_time > time() && $end_time < time()))){
				return $this->json(['status'=>0,'msg'=>'该商家不在排队时间']);
			}
		}

		$clist = Db::name('restaurant_queue_category')->where('aid',aid)->where('bid',$bid)->where('status',1)->order('sort desc,id')->select()->toArray();
		foreach($clist as $k=>$v){
			$clist[$k]['waitnum'] = Db::name('restaurant_queue')->where('aid',aid)->where('cid',$v['id'])->where('status',0)->count();
			if($clist[$k]['waitnum'] == 0){
				$clist[$k]['need_minute'] = '--';
			}else{
				$clist[$k]['need_minute'] = $clist[$k]['waitnum'] * $v['per_minute'];
			}
		}
		$lastqueue = Db::name('restaurant_queue')->where('aid',aid)->where('date',date('Y-m-d'))->where('status',1)->where('call_time','>',time()-300)->order('call_time desc')->find();
		if($lastqueue){
			$notice = $lastqueue['call_text'];
		}else{
			$notice = '当前没有叫号的顾客';
		}

		//我的排队
		$myqueue = Db::name('restaurant_queue')->where('aid',aid)->where('bid',$bid)->where('mid',mid)->where('date',date('Y-m-d'))->where('status',0)->order('id desc')->find();
		if($myqueue){
			$category = Db::name('restaurant_queue_category')->where('aid',aid)->where('bid',$bid)->where('id',$myqueue['cid'])->find();
			$myqueue['beforenum'] = Db::name('restaurant_queue')->where('aid',aid)->where('bid',$bid)->where('id','<',$myqueue['id'])->where('date',date('Y-m-d'))->where('status',0)->count();
			$myqueue['need_minute'] = ($myqueue['beforenum']+1) * $category['per_minute'];
			$myqueue['cname'] = $category['name'];
		}else{
			$myjustqueue = Db::name('restaurant_queue')->where('aid',aid)->where('bid',$bid)->where('mid',mid)->where('status',1)->where('call_time','>',time()-300)->order('call_time desc')->find();
			if($myjustqueue){
				$category = Db::name('restaurant_queue_category')->where('aid',aid)->where('bid',$bid)->where('id',$myjustqueue['cid'])->find();
				$myjustqueue['cname'] = $category['name'];
			}
		}
		
		$rdata = [];
		$rdata['status'] = 1;
		$rdata['business'] = $business;
		$rdata['notice'] = $notice;
		$rdata['clist'] = $clist;
		$rdata['myqueue'] = $myqueue;
		$rdata['myjustqueue'] = $myjustqueue;
		
		$config = include(ROOT_PATH.'config.php');
		$authtoken = $config['authtoken'];
		$rdata['token'] = md5(md5($authtoken.aid.$bid));
		return $this->json($rdata);
	}
	//取号排队
	public function quhao(){
		$bid = input('param.bid');
		if(!getcustom('restaurant_queue_screen_by_category')){
		    $this->checklogin();
        }
		if(!$bid) $bid = 0;
		if($bid!=0){
			$business = Db::name('business')->where('aid',aid)->where('id',$bid)->field('id,name,logo,content,pics,desc,tel,address,sales,start_hours,end_hours')->find();
			$business['pic'] = explode(',',$business['pics'])[0];
		}else{
			$business = Db::name('admin_set')->where('aid',aid)->field('id,name,logo,desc,tel,address')->find();
		}
		$queue_set = Db::name('restaurant_queue_sysset')->where('aid',aid)->where('bid',$bid)->find();
		if($queue_set['status']==0){
			return $this->json(['status'=>0,'msg'=>'该商家未开启排队']);
		}
		if($queue_set['start_hours'] != $queue_set['end_hours']){
			$start_time = strtotime(date('Y-m-d '.$queue_set['start_hours']));
			$end_time = strtotime(date('Y-m-d '.$queue_set['end_hours']));
			if(($start_time < $end_time && ($start_time > time() || $end_time < time())) || ($start_time >= $end_time && ($start_time > time() && $end_time < time()))){
				return $this->json(['status'=>0,'msg'=>'该商家不在排队时间']);
			}
		}
		//是否正在排队
        if(mid){
            $myqueue = Db::name('restaurant_queue')->where('aid',aid)->where('bid',$bid)->where('status',0)->where('mid',mid)->where('date',date('Y-m-d'))->find();
            if($myqueue) return $this->json(['status'=>0,'msg'=>'您已取号']);
        }

		if(request()->isPost()){
			$linkman = input('post.linkman');
			$tel = input('post.tel');
			$renshu = input('post.renshu');
			$cid = input('post.cid');
			$category = Db::name('restaurant_queue_category')->where('aid',aid)->where('bid',$bid)->where('id',$cid)->find();
			$hasnum = Db::name('restaurant_queue')->where('aid',aid)->where('bid',$bid)->where('date',date('Y-m-d'))->where('cid',$cid)->count();
			$queue_no = $hasnum + 1;
			if($queue_no < 10) $queue_no = '0'.$queue_no;
			$queue_no = $category['code'].$queue_no;
            $call_text =  str_replace('[排队号]',$queue_no,$category['call_text']);
			$data = [];
			$data['aid'] = aid;
			$data['bid'] = $bid;
			$data['cid'] = $cid;
			$data['mid'] = mid;
			$data['date'] = date('Y-m-d');
			$data['queue_no'] = $queue_no;
            $data['call_text'] = $call_text?$call_text:'请'.$queue_no.'号顾客前往就餐';
			$data['create_time'] = time();
			$data['linkman'] = $linkman;
			$data['tel'] = $tel;
			$data['renshu'] = $renshu;
			Db::name('restaurant_queue')->insert($data);
			return $this->json(['status'=>1,'msg'=>'取号成功，排队号：'.$queue_no,'queue_no'=>$queue_no]);
		}

		$clist = Db::name('restaurant_queue_category')->where('aid',aid)->where('bid',$bid)->where('status',1)->order('sort desc,id')->select()->toArray();
		foreach($clist as $k=>$v){
			$clist[$k]['waitnum'] = Db::name('restaurant_queue')->where('aid',aid)->where('bid',$bid)->where('cid',$v['id'])->where('status',0)->count();
			if($clist[$k]['waitnum'] == 0){
				$clist[$k]['need_minute'] = '--';
			}else{
				$clist[$k]['need_minute'] = $clist[$k]['waitnum'] * $v['per_minute'];
			}
		}
		$lastqueue = Db::name('restaurant_queue')->where('aid',aid)->where('date',date('Y-m-d'))->where('bid',$bid)->where('status',1)->order('call_time desc')->find();
		if($lastqueue){
			$notice = $lastqueue['call_text'];
		}else{
			$notice = '当前没有叫号的顾客';
		}
		
		//查上一次填的姓名手机号
		$mylastqueue = Db::name('restaurant_queue')->where('aid',aid)->where('bid',$bid)->where('mid',mid)->find();
		if($mylastqueue){
			$linkman = $mylastqueue['linkman'];
			$tel = $mylastqueue['tel'];
		}else{
			$linkman = $this->member['realname'];
			$tel = $this->member['tel'];
		}
		

		$rdata = [];
		$rdata['status'] = 1;
		$rdata['business'] = $business;
		$rdata['notice'] = $notice;
		$rdata['clist'] = $clist;
		$rdata['linkman'] = $linkman;
		$rdata['tel'] = $tel;
        $rdata['is_to_record'] = 1;
		$config = include(ROOT_PATH.'config.php');
		$authtoken = $config['authtoken'];
		$rdata['token'] = md5(md5($authtoken.aid.$bid));
		return $this->json($rdata);
	}
	//取消排队
	public function cancel(){
        $this->checklogin();
		$myqueue = Db::name('restaurant_queue')->where('aid',aid)->where('mid',mid)->where('id',input('param.id'))->find();
		if(!$myqueue){
			return $this->json(['status'=>0,'msg'=>'未找到该排队信息']);
		}
		if($myqueue['status']!=0){
			return $this->json(['status'=>0,'msg'=>'取消失败,该排队已不在队列中']);
		}
		Db::name('restaurant_queue')->where('id',$myqueue['id'])->update(['status'=>3,'expiry_time'=>time()]);
		return $this->json(['status'=>1,'msg'=>'取消成功']);
	}
	//排队记录
	public function record(){
        $this->checklogin();
		$bid = input('param.bid');
		if(!$bid) $bid = 0;
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$pernum = 20;
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['bid','=',$bid];
		$where[] = ['mid','=',mid];
		$datalist = Db::name('restaurant_queue')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
		if(!$datalist) $datalist = [];
		foreach($datalist as $k=>$v){
			$datalist[$k]['cname'] = Db::name('restaurant_queue_category')->where('id',$v['cid'])->value('name');
		}

		if(request()->isPost()){
			return $this->json(['status'=>1,'data'=>$datalist]);
		}
		
		$count = Db::name('restaurant_queue')->where($where)->count();

		$rdata = [];
		$rdata['count'] = $count;
		$rdata['datalist'] = $datalist;
		$rdata['pernum'] = $pernum;
		return $this->json($rdata);
	}
}