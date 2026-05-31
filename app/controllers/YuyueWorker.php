<?php


// +----------------------------------------------------------------------
// | 配送员
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class YuyueWorker extends Common
{
    public function initialize(){
		parent::initialize();
	}
	//列表
    public function index(){
		$set = db('yuyue_set')->field('diyname')->where('aid',aid)->find();
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
				$order = 'sort desc,id';
			}
			$where = array();
			$where[] = ['aid','=',aid];
			$where[] = ['bid','=',bid];
			if(input('param.realname')) $where[] = ['realname','like','%'.input('param.realname').'%'];
			if(input('?param.status') && input('param.status')!=='') $where[] = ['status','=',input('param.status')];
			if(input('?param.shstatus') && input('param.shstatus')!=='') $where[] = ['shstatus','=',input('param.shstatus')];
			$count = 0 + Db::name('yuyue_worker')->where($where)->count();
			$data = Db::name('yuyue_worker')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			foreach($data as $k=>$v){
				$member = Db::name('member')->where('id',$v['mid'])->find();
				$data[$k]['nickname'] = $member['nickname'];
				$data[$k]['mheadimg'] = $member['headimg'];
				$pszCount = Db::name('yuyue_worker_order')->where('aid',aid)->where('worker_id',$v['id'])->where('status','in','0,1,2')->count();
				$ywcCount = Db::name('yuyue_worker_order')->where('aid',aid)->where('worker_id',$v['id'])->where('status',3)->count();
				$data[$k]['pszCount'] = $pszCount;
				$data[$k]['ywcCount'] = $ywcCount;

				}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		View::assign('set',$set);
        $this->defaultSet();
		return View::fetch();
    }
	//编辑
	public function edit(){
		if(input('param.id')){
			$info = Db::name('yuyue_worker')->where('aid',aid)->where('bid',bid)->where('id',input('param.id/d'))->find();
			$areaArr = explode(',',$info['citys']);
            $province = $areaArr[0];
            $city = $areaArr[1];
            $area = $areaArr[2];
			$info['province'] = $province;
			$info['city'] = $city;
			$info['district'] = $area;
		}else{
			$info = array('id'=>'');
		}

		//分类
		$clist = Db::name('yuyue_worker_category')->Field('id,name')->where('aid',aid)->where('bid',bid)->where('status',1)->where('pid',0)->order('sort desc,id')->select()->toArray(); 
		$bid = $info['bid']?$info['bid']:bid;
		$fwclist = Db::name('yuyue_category')->Field('id,name,pid')->where('aid',aid)->where('bid',$bid)->where('pid',0)->order('sort desc,id')->select()->toArray(); 
		foreach($fwclist as $k=>$v){
			$child = Db::name('yuyue_category')->Field('id,name')->where('aid',aid)->where('bid',$bid)->where('pid',$v['id'])->order('sort desc,id')->select()->toArray();
			$fwclist[$k]['child'] = $child;
		}
		$isapply = false;
		View::assign('isapply',$isapply);
		View::assign('fwclist',$fwclist);
		View::assign('clist',$clist);
		View::assign('info',$info);
		return View::fetch();
	}
	public function save(){
		$info = input('post.info/a');
		$hasun = Db::name('yuyue_worker')->where('aid',aid)->where('id','<>',$info['id'])->where('un',$info['un'])->find();
		if($hasun){
			return json(['status'=>0,'msg'=>'该账号已被占用']);
		}
		$hasrealname = Db::name('yuyue_worker')->where('aid',aid)->where('bid',bid)->where('id','<>',$info['id'])->where('realname',$info['realname'])->find();
		if($hasrealname){
			return json(['status'=>0,'msg'=>'该姓名已存在，请填写其他姓名']);
		}
		if($info['latitude']){
			//通过坐标获取省市区
            $mapqq = new \app\commons\MapQQ();
            $address = $mapqq->locationToAddress($info['latitude'],$info['longitude']);
            if($address['status'] == 1){
                $info['citys'] = $address['area'];
                $info['province'] = $address['province'];
                $info['city']     = $address['city'];
                $info['district'] = $address['district'];
                // $info['citys'] = $info['province'].','.$info['city'].','.$info['district'];
            }
		}
        
        
		unset($info['address']);
		if($info['id']){
			//$member = Db::name('member')->where('id',$info['mid'])->find();
			//$info['headimg'] = $member['headimg'];
			$info['pwd'] = md5($info['pwd']);
			Db::name('yuyue_worker')->where('aid',aid)->where('id',$info['id'])->update($info);
			\app\commons\System::plog('编辑人员'.$info['id']);
		}else{
			if($info['pwd']!=''){
				$info['pwd'] = md5($info['pwd']);
			}else{
				unset($info['pwd']);
			}
			$info['aid'] = aid;
			$info['bid'] = bid;
			$info['createtime'] = time();
			//$member = Db::name('member')->where('id',$info['mid'])->find();
			//$info['headimg'] = $member['headimg'];
			$id = Db::name('yuyue_worker')->insertGetId($info);
			\app\commons\System::plog('添加人员'.$id);
		}
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}
	//改状态
	public function setst(){
		$st = input('post.st/d');
		$ids = input('post.ids/a');
		Db::name('yuyue_worker')->where('aid',aid)->where('bid',bid)->where('id','in',$ids)->update(['status'=>$st]);
		\app\commons\System::plog('配送员改状态'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//删除
	public function del(){
		$ids = input('post.ids/a');
		Db::name('yuyue_worker')->where('aid',aid)->where('bid',bid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('配送员删除'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	//获取配送员
	public function getpeisonguser(){
		$set = Db::name('yuyue_set')->where('aid',aid)->where('bid',bid)->find();

		$order = Db::name(input('param.type'))->where('id',input('param.orderid'))->find();
		if($order['bid']>0){
			$business = Db::name('business')->field('name,address,tel,logo,longitude,latitude')->where('id',$order['bid'])->find();
		}else{
			$business = Db::name('admin_set')->field('name,address,tel,logo,longitude,latitude')->where('aid',aid)->find();
		}
//		$juli = getdistance($order['longitude'],$order['latitude'],$business['longitude'],$business['latitude'],1);
		$ticheng = $order['paidan_money'];
		$selectArr = [];
		if($set['paidantype'] == 0){ //抢单模式
			$selectArr[] = ['id'=>0,'title'=>'--服务人员抢单--'];
		}else{
            $where = [];
            $where[] = ['aid','=',aid];
            $where[] = ['status','=',1];
            $bid = $order['bid'];
            //使用平台配送员
            $where[] = ['bid','=',$bid];
			$peisonguser = Db::name('yuyue_worker')->where($where)->order('sort desc,id')->select()->toArray();
			foreach($peisonguser as $k=>$v){
				$dan = Db::name('yuyue_worker_order')->where('worker_id',$v['id'])->where('status','in','0,1')->count();
				$title = $v['realname'].'-'.$v['tel'].'(进行中'.$dan.'单)';
				//查看是否在改时间已经有服务
				$order = Db::name('yuyue_order')->where('aid',aid)->where('worker_id',$v['id'])->where('status','in','1,2')->where('yy_time',$order['yy_time'])->find();
				$status = 1;
				if($order){
					$status=-1;
				}
				$selectArr[] = ['id'=>$v['id'],'title'=>$title,'status'=>$status];
			}
		}
	
		$psfee = $ticheng * (1 + $set['businessfee']*0.01);
		return json(['status'=>1,'peisonguser'=>$selectArr,'paidantype'=>$set['paidantype'],'psfee'=>$psfee,'ticheng'=>$ticheng]);
	}
	//派单
	public function peisong(){
		$orderid = input('post.orderid/d');
		$worker_id = input('post.worker_id/d');
        $where = [];
        $where[] = ['aid','=',aid];
        $where[] = ['id','=',$orderid];
        $allBid = false;
        if(!$allBid){
            $where[] = ['bid','=',bid];
        }
		$order = Db::name('yuyue_order')->where($where)->find();
		if(!$order) return json(['status'=>0,'msg'=>'订单不存在']);
		if($order['status']!=1 && $order['status']!=12) return json(['status'=>0,'msg'=>'订单状态不符合']);
		//取出该订单的服务人员
		$fwpeoid = Db::name('yuyue_product')->where('id',$order['proid'])->where('aid',aid)->where('bid',bid)->value('fwpeoid');
		
		$rs = \app\models\YuyueWorkerOrder::create($order,$worker_id,$fwpeoid);
		if($rs['status']==0) return json($rs);
		\app\commons\System::plog('预约派单'.$orderid);
		return json(['status'=>1,'msg'=>'操作成功']);
	}

	//审核
	public function setcheckst(){
		$st = input('post.st/d');
		$id = input('post.id/d');
		$reason = input('post.reason');
		$worker = Db::name('yuyue_worker')->where('aid',aid)->where('id',$id)->find();
		if(!$worker) return json(['status'=>0,'msg'=>'信息不存在']);
	
		if($st == 1){
			Db::name('yuyue_worker')->where('aid',aid)->where('id',$id)->update(['shstatus'=>$st,'status'=>1]);
			//通过后将类目下的商品服务人员增加
			$list = Db::name('yuyue_product')->where('aid',aid)->where('bid',$worker['bid'])->where('cid','in',$worker['fwcids'])->select()->toArray();
			foreach($list as $l){
				$peoids = '';
				if($l['fwpeoid']){
					$peoarr = explode(',',$l['fwpeoid']);
					if(!in_array($worker['id'],$peoarr)) $peoids = $l['fwpeoid'].','.$worker['id'];
					else 
						$peoids = $l['fwpeoid'];
				}else{
					$peoids = $worker['id'];
				}	
				Db::name('yuyue_product')->where('id',$l['id'])->update(['fwpeoid'=>$peoids]);
			}
		}else{
			Db::name('yuyue_worker')->where('aid',aid)->where('id',$id)->update(['shstatus'=>$st,'reason'=>$reason]);
		}
		//审核结果通知
		$tmplcontent = [];
		$tmplcontent['first'] = ($st == 1 ? '恭喜您的申请入驻通过' : '抱歉您的提交未审核通过');
		$tmplcontent['remark'] = ($st == 1 ? '' : ($reason.'，')) .'请点击查看详情~';
		$tmplcontent['keyword1'] = $worker['realname'].'师傅申请';
		$tmplcontent['keyword2'] = ($st == 1 ? '已通过' : '未通过');
		$tmplcontent['keyword3'] = date('Y年m月d日 H:i');
        $tempconNew = [];
        $tempconNew['thing9'] = $worker['realname'].'师傅申请';
        $tempconNew['thing2'] = ($st == 1 ? '已通过' : '未通过');
        $tempconNew['time3'] = date('Y年m月d日 H:i');
		$rs = \app\commons\Wechat::sendtmpl(aid,$worker['mid'],'tmpl_shenhe',$tmplcontent,m_url('activity/yuyue/apply'),$tempconNew);
		//订阅消息
		$tmplcontent = [];
		$tmplcontent['thing8'] = $worker['realname'].'师傅申请';
		$tmplcontent['phrase2'] = ($st == 1 ? '已通过' : '未通过');
		$tmplcontent['thing4'] = $st == 1?'您的申请未通过':'您的申请已通过';
		$rs = \app\commons\Wechat::sendwxtmpl(aid,$worker['mid'],'tmpl_shenhe',$tmplcontent,'activity/yuyue/apply','');
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	public function refund(){
		$id = input('post.id/d');
		if(bid == 0){
			$worker = Db::name('yuyue_worker')->where('id',$id)->where('aid',aid)->find();
		}else{
			$worker = Db::name('yuyue_worker')->where('id',$id)->where('aid',aid)->where('bid',bid)->find();
		}
		$order = Db::name('yuyue_workerapply_order')->where('ordernum',$worker['ordernum'])->where('aid',aid)->where('bid',bid)->find();
		if(!$order) return json(['status'=>0,'msg'=>'支付订单不存在']);
		if($order['status']!=1 || $order['refund_status']==1){
			return json(['status'=>0,'msg'=>'该订单状态不允许退款']);
		}
		if($order['price'] > 0){
			$order['totalprice'] = $order['price'];
			$rs = \app\commons\Order::refund($order,$order['price'],'预约申请费用后台退款');
			if($rs['status']==1){
				Db::name('yuyue_workerapply_order')->where('id',$order['id'])->where('aid',aid)->update(['status'=>2,'refund_status'=>1,'refund_money' => $order['price'], 'refund_time'=>time()]);
			}else{
				return json(['status'=>0,'msg'=>$rs['msg']]);
			}
		}
		
	
        $refund_money = $order['price'];

		//退款成功通知
		$tmplcontent = [];
		$tmplcontent['first'] = '您的预约申请费用已经完成退款，¥'.$refund_money.'已经退回您的付款账户，请留意查收。';
		$tmplcontent['remark'] = '请点击查看详情~';
		$tmplcontent['orderProductPrice'] = $refund_money.'元';
		$tmplcontent['orderProductName'] = $order['title'];
		$tmplcontent['orderName'] = $order['ordernum'];
        $tmplcontentNew = [];
        $tmplcontentNew['character_string1'] = $order['ordernum'];//订单编号
        $tmplcontentNew['thing2'] = $order['title'];//商品名称
        $tmplcontentNew['amount3'] = $refund_money;//退款金额
		\app\commons\Wechat::sendtmpl(aid,$order['mid'],'tmpl_tuisuccess',$tmplcontent,m_url('pages/my/usercenter'),$tmplcontentNew);
		//订阅消息
		$tmplcontent = [];
		$tmplcontent['amount6'] = $refund_money;
		$tmplcontent['thing3'] = $order['title'];
		$tmplcontent['character_string2'] = $order['ordernum'];
		
		$tmplcontentnew = [];
		$tmplcontentnew['amount3'] = $refund_money;
		$tmplcontentnew['thing6'] = $order['title'];
		$tmplcontentnew['character_string4'] = $order['ordernum'];
		\app\commons\Wechat::sendwxtmpl(aid,$order['mid'],'tmpl_tuisuccess',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);

		//短信通知
		$member = Db::name('member')->where('id',$order['mid'])->find();
		if($member['tel']){
			$tel = $member['tel'];
		}else{
			$tel = $order['tel'];
		}
		$rs = \app\commons\Sms::send(aid,$tel,'tmpl_tuisuccess',['ordernum'=>$order['ordernum'],'money'=>$refund_money]);
		\app\commons\System::plog('预约申请费用退款'.$order['id']);
		return json(['status'=>1,'msg'=>'已退款成功']);
	}
    function defaultSet(){
        $set = Db::name('yuyue_set')->where('aid',aid)->where('bid',bid)->find();
        if(!$set){
            Db::name('yuyue_set')->insert(['aid'=>aid,'bid' => bid]);
        }
    }
    //选择人员
    public function chooseyuyueworker(){
        return View::fetch();
    }

    //获取商品信息
    public function getworker(){
        $workerid = input('post.workerid/d');
        $worker = Db::name('yuyue_worker')->where('id',$workerid)->where('aid',aid)->find();
        return json(['worker'=>$worker]);
    }
}
