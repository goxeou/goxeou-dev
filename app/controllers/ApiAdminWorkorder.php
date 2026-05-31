<?php


//工单记录
namespace app\controllers;
use think\facade\Db;
class ApiAdminWorkorder extends ApiAdmin
{	
	//获取表单类型
	public function getcategory(){
		$this->checklogin();
		$where[] = ['aid','=',aid];
		$nowtime = time();
		$where[] = ['status','=',1];
		$where[] = ['usertype','=',2];
		if($post['cateid']){
			$where[] = ['id','=',$post['cateid']];
		}
		$cate = Db::name('workorder_category')->where($where)->select()->toArray();
		foreach($cate as &$c){
			$c['content'] =  json_decode($c['content'],true);
		}
		return $this->json(['status'=>1,'data'=>$cate]);

	}

	//提交表单
	public function formsubmit(){
		$this->checklogin();
		$post = input('post.');
		//var_dump($post);
		//var_dump($post['formdata']);
		//die;
		$form = Db::name('workorder_category')->where('aid',aid)->where('id',$post['formid'])->find();
		//var_dump($form);

		if($form['maxlimit'] > 0){
			$count = 0 + Db::name('workorder_order')->where('formid',$form['id'])->count();
			if($count >= $form['maxlimit']){
				return $this->json(['status'=>0,'msg'=>'提交人数已满']);
			}
		}
		$mycs = 0 + Db::name('workorder_order')->where('formid',$form['id'])->where('mid',mid)->count();
		if($form['perlimit'] > 0 && $mycs >= $form['perlimit']){
			return $this->json(['status'=>0,'msg'=>$form['perlimit']==1?'您已经提交过了':'每人最多可提交'.$form['perlimit'].'次']);
		}
		$data =[];
		$data['aid'] = aid;
		$data['bid'] = $form['bid'];
		$data['formid'] = $form['id'];
		$data['title'] = $form['name'];
		$data['mid'] = uid;
		$data['createtime'] = time();
		$data['type'] = 2;

		//var_dump($post);
		$fromdata = $post['formdata'];
		$formcontent = json_decode($form['content'],true);
		foreach($formcontent as $k=>$v){
			$value = $fromdata['form'.$k];
			if(is_array($value)){
				$value = implode(',',$value);
			}
			if($v['key']=='switch'){
				if($value){
					$value = '是';
				}else{
					$value = '否';
				}
			}
			$data['form'.$k] = strval($value);
			if($v['val3']==1 && $data['form'.$k]===''){
				return $this->json(['status'=>0,'msg'=>$v['val1'].' 必填']);
			}
		}
		$price = 0;
		if($form['payset']==1){
			if($form['priceedit']==1){
				$price = $post['price'];
			}else{
				$price = $form['price'];
			}
		}
		$ordernum = date('ymdHis').aid.rand(1000,9999);
		$data['money'] = $price;
		$data['ordernum'] = $ordernum;
		$data['fromurl'] = $post['fromurl'];
		$orderid = Db::name('workorder_order')->insertGetId($data);

		//订阅消息
		$tmplids = [];
		if(platform == 'wx'){
			$wx_tmplset = Db::name('wx_tmplset')->where('aid',aid)->find();
			if($wx_tmplset['tmpl_shenhe_new']){
				$tmplids[] = $wx_tmplset['tmpl_shenhe_new'];
			}elseif($wx_tmplset['tmpl_shenhe']){
				$tmplids[] = $wx_tmplset['tmpl_shenhe'];
			}
		}

		if(!$orderid) return $this->json(['status'=>0,'msg'=>'提交失败','tmplids'=>$tmplids]);
		if($price > 0){
			$payorderid = \app\models\Payorder::createorder(aid,$data['bid'],$data['mid'],'workorder',$orderid,$data['ordernum'],$data['title'],$data['money']);
			return $this->json(['status'=>2,'msg'=>'需要支付','orderid'=>$orderid,'payorderid'=>$payorderid,'tmplids'=>$tmplids]);
		}else{
			$tmplcontent = [];
			$tmplcontent['first'] = '有客户提交表单成功';
			$tmplcontent['remark'] = '点击查看详情~';
			$tmplcontent['keyword1'] = $form['name'];
			$tmplcontent['keyword2'] = date('Y-m-d H:i');
			\app\commons\Wechat::sendhttmpl(aid,$form['bid'],'tmpl_formsub',$tmplcontent,m_url('admin/workorder/formdetail?id='.$orderid));
			return $this->json(['status'=>1,'msg'=>'提交成功','tmplids'=>$tmplids]);
		}
	}

	public function myformlog(){
		$pagenum = input('post.pagenum');
        $st = input('post.st');
		if(!$pagenum) $pagenum = 1;
		$pernum = 20;
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['mid','=',uid];
		if(!input('?param.st') || $st === ''){
			$st = 'all';
		}
		if($st == 'all'){
			
		}elseif($st == '0'){
			$where[] = ['status','=',0];
			$where[] = Db::raw('payorderid is null or paystatus=1');
		}elseif($st == '1'){
			$where[] = ['status','=',1];
		}elseif($st == '2'){
			$where[] = ['status','=',2];
		}elseif($st == '10'){
			$where[] = ['status','=',0];
			$where[] = ['paystatus','=',0];
			$where[] = ['payorderid','<>',''];
		}	

	    $formid = input('post.formid');
		if($formid) $where[] = ['formid','=',$formid];
		

		//$where['status'] = 1;
		$datalist = Db::name('workorder_order')->field('*,from_unixtime(createtime)createtime,from_unixtime(paytime)paytime')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
		if(!$datalist) $datalist = [];
		if(request()->isPost()){
			return $this->json(['status'=>1,'data'=>$datalist]);
		}
		$count = Db::name('workorder_order')->where($where)->count();
		$rdata = [];
		$rdata['count'] = $count;
		$rdata['datalist'] = $datalist;
		$rdata['pernum'] = $pernum;
		$rdata['st'] = $st;
		return $this->json($rdata);
	}
	public function formlog(){

		$pagenum = input('post.pagenum');
        $st = input('post.st');
		if(!$pagenum) $pagenum = 1;
		$pernum = 20;
		if($this->user['isadmin']>0){
			$where = "aid=".aid." and bid=".bid." and type={$this->user['workorder_type']} and ( formid={$this->user['workcate_id']} or cltype=1 or userid={$this->user['id']})";
		}else{
			$where =  "aid=".aid." and bid=".bid;
		}
		if($st == 'all'){
			$datalist = db('workorder_order')->field('*,from_unixtime(createtime)createtime,from_unixtime(paytime)paytime')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
		
		}elseif($st == '0'){
			$where .=" and status=0";
			$datalist = db('workorder_order')->field('*,from_unixtime(createtime)createtime,from_unixtime(paytime)paytime')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
		}elseif($st == '1'){
			$where .=" and status=1";
			$datalist = db('workorder_order')->field('*,from_unixtime(createtime)createtime,from_unixtime(paytime)paytime')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
		}elseif($st == '2'){
			$where .=" and status=2";
			$datalist = db('workorder_order')->field('*,from_unixtime(createtime)createtime,from_unixtime(paytime)paytime')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
		}elseif($st == '10'){
			$where .=" and status=10 and  paystatus=0 and payorderid <> '' ";
			$datalist = db('workorder_order')->field('*,from_unixtime(createtime)createtime,from_unixtime(paytime)paytime')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
		}
		//$where[] = Db::raw('cltype=1 or userid='.$this->user['id']);
	

		//$datalist = Db::name('workorder_order')->field('*,from_unixtime(createtime)createtime,from_unixtime(paytime)paytime')->where($where)->whereOr(['cltype'=>1])->whereOr(['userid'=>$this->user['id']])->page($pagenum,$pernum)->order('id desc')->select()->toArray();
		//var_dump(db('workorder_order')->getlastsql());
		if(!$datalist) $datalist = [];
		if(request()->isPost()){
			return $this->json(['status'=>1,'data'=>$datalist]);
		}
		$count = Db::name('workorder_order')->where($where)->count();
		$rdata = [];
		$rdata['count'] = $count;
		$rdata['datalist'] = $datalist;
		$rdata['pernum'] = $pernum;
		$rdata['st'] = $st;
		return $this->json($rdata);
	}
	// 获取流程
	public function getliucheng(){
		$cid = input('post.cid');
		$where = [];
		if($cid) {
			$cids=[];
			$cids=[0,$cid];
			$where[] = ['cid','in',$cids];
		}
		$datalist = Db::name('workorder_liucheng')->where('aid',aid)->where('status',1)->where($where)->order('sort desc,id desc')->select()->toArray();
		if(!$datalist) $datalist = array();
		
		$rdata['datalist'] = $datalist;

		return $this->json($rdata);
	}
	public function addjindu(){
		if(request()->isPost()){
			$lcid = input('post.lcid');
			$logid = input('post.logid');
			if(!$logid) return json(['status'=>0,'msg'=>'缺少参数']);
			$content = input('post.content');
			$content_pic = input('post.content_pic');
			$liucheng = Db::name('workorder_liucheng')->where('id',$lcid)->find();
			$data = [];
			$data['aid'] = aid;
			$data['lcid'] = $lcid;
			$data['userid'] = uid;
			$data['logid'] = $logid;
			$data['desc'] = $liucheng['name'];
			$data['remark'] = $content;
			$data['content_pic'] = $content_pic;
			$data['createtime'] = time();
			Db::name('workorder_chuli')->insert($data);
			//完成将状态改为完成
			if($liucheng['lcstatus']==2){ 
				Db::name('workorder_order')->where('id',$logid)->update(['status'=>2]);
			}else{
				Db::name('workorder_order')->where('id',$logid)->update(['status'=>1,'userid'=>$this->user['id']]);
			}
			return json(['status'=>1,'msg'=>'处理成功']);
		}
		$clist = Db::name('luntan_category')->where('aid',aid)->where('pid',0)->where('status',1)->order('sort desc,id')->select()->toArray();
		$rdata = [];
		$rdata['clist'] = $clist;
		
	}
	//表单提交记录
	public function formdetail(){
		$id = input('param.id/d');
		$detail = Db::name('workorder_order')->where('aid',aid)->where('bid',bid)->where('id',$id)->find();
		if(!$detail) return json(['status'=>-4,'msg'=>'记录不存在']);
		$detail['paytime'] = date('Y-m-d H:i:s',$detail['paytime']);
		$detail['createtime'] = date('Y-m-d H:i:s',$detail['createtime']);
		if($detail['type']==1){
			$member = Db::name('member')->where('id',$detail['mid'])->find();
			$detail['headimg'] = $member['headimg'];
			$detail['nickname'] = $member['nickname'];
		}else if($detail['type']==2){
			$member = Db::name('admin_user')->where('id',$detail['mid'])->find();
			$detail['headimg'] = '';
			$detail['nickname'] = $member['un'];
		}
		
		$form = Db::name('workorder_category')->where('aid',aid)->where('bid',bid)->where('id',$detail['formid'])->find();
		$formcontent = json_decode($form['content'],true);
		foreach($formcontent as $k=>$form){
			if($form['key']=='upload'){
				$detail['form'.$k] = explode(',',$detail['form'.$k]);
			}
		}
		$rdata = [];
		$rdata['form'] = $form;
		$rdata['formcontent'] = $formcontent;
		$rdata['detail'] = $detail;
		return $this->json($rdata);
	}
	//改状态
	public function formsetst(){
		$id = input('param.id/d');
		$st = input('param.st/d');
		$istuikuan = input('post.istuikuan/d');
		$istuikuan = 1;

		$order = Db::name('workorder_order')->where('aid',aid)->where('bid',bid)->where('id',$id)->find();
		if(!$order) return json(['status'=>1,'msg'=>'操作失败']);
			
		if($st == 2 && $istuikuan == 1){
			$order['totalprice'] = $order['money'];
			$rs = \app\commons\Order::refund($order,$order['money'],input('post.reason'));
			if($rs['status']==0){
				return json(['status'=>0,'msg'=>$rs['msg']]);
			}
			Db::name('workorder_order')->where('aid',aid)->where('bid',bid)->where('id',$order['id'])->update(['isrefund'=>1]);
		}
		if($st == 2){
			$reason = input('post.reason');
			Db::name('workorder_order')->where('aid',aid)->where('bid',bid)->where('id',$order['id'])->update(['status'=>$st,'reason'=>$reason]);
		}else{
			Db::name('workorder_order')->where('aid',aid)->where('bid',bid)->where('id',$order['id'])->update(['status'=>$st]);
		}
		//审核结果通知
		$tmplcontent = [];
		$tmplcontent['first'] = ($st == 1 ? '恭喜您的提交审核通过' : '抱歉您的提交未审核通过');
		$tmplcontent['remark'] = ($st == 1 ? '' : ($reason.'，')) .'请点击查看详情~';
		$tmplcontent['keyword1'] = $order['title'];
		$tmplcontent['keyword2'] = ($st == 1 ? '已通过' : '未通过');
		$tmplcontent['keyword3'] = date('Y年m月d日 H:i');
		\app\commons\Wechat::sendtmpl(aid,$order['mid'],'tmpl_shenhe',$tmplcontent,m_url('pages/form/formlog'));
		//订阅消息
		$tmplcontent = [];
		$tmplcontent['thing8'] = $order['title'];
		$tmplcontent['phrase2'] = ($st == 1 ? '已通过' : '未通过');
		$tmplcontent['thing4'] = $reason;
		
		$tmplcontentnew = [];
		$tmplcontentnew['thing2'] = $order['title'];
		$tmplcontentnew['phrase1'] = ($st == 1 ? '已通过' : '未通过');
		$tmplcontentnew['thing5'] = $reason;
		\app\commons\Wechat::sendwxtmpl(aid,$order['mid'],'tmpl_shenhe',$tmplcontentnew,'pages/form/formlog',$tmplcontent);

	}
	//删除
	public function formdel(){
		$id = input('param.id/d');
		Db::name('workorder_order')->where('aid',aid)->where('bid',bid)->where('id',$id)->delete();
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	//商户自己提交的记录
	public function myformdetail(){
		$id = input('param.id/d');
		$detail = Db::name('workorder_order')->where('aid',aid)->where('id',$id)->find();
		//echo db('workorder_order')->getlastsql();
		if(!$detail) return json(['status'=>-4,'msg'=>'记录不存在']);
		$detail['paytime'] = date('Y-m-d H:i:s',$detail['paytime']);
		$detail['createtime'] = date('Y-m-d H:i:s',$detail['createtime']);
		$member = Db::name('admin_user')->where('id',$detail['mid'])->find();
		$detail['nickname'] = $member['un'];
		$form = Db::name('workorder_category')->where('aid',aid)->where('id',$detail['formid'])->find();
		$formcontent = json_decode($form['content'],true);
		foreach($formcontent as $k=>$form){
			if($form['key']=='upload'){
				$detail['form'.$k] = explode(',',$detail['form'.$k]);
			}
		}
		$rdata = [];
		$rdata['form'] = $form;
		$rdata['formcontent'] = $formcontent;
		$rdata['detail'] = $detail;
		return $this->json($rdata);
	}
}