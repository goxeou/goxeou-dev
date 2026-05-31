<?php


namespace app\controllers;
use think\facade\Db;
class ApiWorkorder extends ApiCommon
{	

	//获取表单类型
	public function getcategory(){
		$this->checklogin();
		$post = input('post.');
		$where[] = ['aid','=',aid];
		$nowtime = time();
		$where[] = ['status','=',1];
		$where[] = ['usertype','=',1];

		if($post['cateid']){
			$where[] = ['id','=',$post['cateid']];
		}
		if($post['type']) $where[] = ['isglorder','=',$post['type']];
		$cate = Db::name('workorder_category')->where($where)->select()->toArray();
		foreach($cate as &$c){
			$c['content'] =  json_decode($c['content'],true);
		}
		return $this->json(['status'=>1,'data'=>$cate]);

	}
	//获取表单内容
	public function getform(){
		$post = input('post.');
		$form = Db::name('workorder_category')->where('aid',aid)->where('id',$post['id'])->find();
		$form['content'] =  json_decode($form['content'],true);
		$form['editorFormdata'] = [];
		return $this->json(['status'=>1,'data'=>$form]);
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
		//if(strtotime($form['starttime']) > time()){
		//	return $this->json(['status'=>0,'msg'=>'活动未开始']);
		//}
		//if(strtotime($form['endtime']) < time()){
		//	return $this->json(['status'=>0,'msg'=>'活动已结束']);
		//}
		if($form['status']==0){
			return $this->json(['status'=>0,'msg'=>'分类已关闭']);
		}
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
		$data['mid'] = mid;
		$data['createtime'] = time();
		if($post['orderid'] && $post['type']){
			$data['ordertype'] = $post['type'];
			if($data['ordertype']==1){
				$table='shop_order';
			} else if($data['ordertype']==2){
				$table='yuyue_order';
			} 
			$order = Db::name($table)->field('id,ordernum')->where('id',$post['orderid'])->find();
			$data['orderid'] = $order['id'];
			$data['glordernum'] = $order['ordernum'];
		} 
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
		$data['cltype'] = $form['cltype'];
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
	
	//获取进度
	public function selectjindu(){
		$this->checklogin();
		$post = input('post.');
		$data = Db::name('workorder_chuli')->where('aid',aid)->where('logid',$post['id'])->order('id desc')->select()->toArray();
		if(!$data) $data = array();
		foreach($data as &$d){
			$d['time'] = date('Y-m-d H:i:s',$d['createtime']);
			if($d['content_pic'])$d['content_pic'] = explode(',',$d['content_pic']);

			$huifu = Db::name('workorder_huifu')->where('aid',aid)->where('clid',$d['id'])->order('id desc')->select()->toArray();
			if(!$huifu) $huifu = array();
			foreach($huifu as &$h){
				$h['hftime'] = date('Y-m-d H:i:s',$h['createtime']);
				if($h['hfcontent_pic']) $h['hfcontent_pic'] = explode(',',$h['hfcontent_pic']);
			}
			$d['hflist'] = $huifu;
		}
		return $this->json(['status'=>1,'data'=>$data]);
	}
	
	//回复信息
	public function addhuifu(){
		$this->checklogin();
		$post = input('post.formdata');
		$content_pic = input('post.hfcontent_pic');
		$info = Db::name('workorder_chuli')->where('id',$post['lcid'])->find();
		if(!$info['id']) return $this->json(['status'=>0,'msg'=>'记录不存在']);
		$data = [];
		$data['aid'] = aid;
		$data['bid'] = bid;
		$data['logid'] = $info['logid'];
		$data['mid'] = mid;
		$data['hfremark'] = $post['content'];
		$data['createtime'] = time();
		$data['hfcontent_pic'] = $content_pic;
		$data['clid'] = $info['id'];
		$data['hfuserid'] = $info['userid'];
		//Db::name('workorder_chuli')->where('id',$post['lcid'])->update(['hfremark'=>$post['content'],'hftime'=>time(),'hfcontent_pic'=>$content_pic]);
		Db::name('workorder_huifu')->insert($data);
		return $this->json(['status'=>1,'msg'=>'回复成功']);
	}

	//用户结束
	public function confirmend(){
		if(request()->isPost()){
			$orderid = input('post.id');
			Db::name('workorder_order')->where('id',$orderid)->update(['status'=>2,'enddate'=>date('Y-m-d H:i:s',time())]);
			return json(['status'=>1,'msg'=>'处理成功']);
		}
	}
	//用户评价
	public function tocomment(){
		if(request()->isPost()){
			$orderid = input('post.id');
			$commentstatus = input('post.commentstatus');
			Db::name('workorder_order')->where('id',$orderid)->update(['iscomment'=>1,'comment_status'=>$commentstatus]);
			return json(['status'=>1,'msg'=>'处理成功']);
		}
	}
	//获取上一次的数据
	public function getlastformdata(){
		if(!getcustom('plug_mantouxia')) return json(['status'=>0,'msg'=>'没有记录']);
		$formid = input('param.formid/d');
		if(!$formid || !$this->member) return json(['status'=>0,'msg'=>'参数错误']);
		$formorder = Db::name('workorder_order')->where('aid',aid)->where('formid',$formid)->where('mid',mid)->order('id desc')->find();
		if(!$formorder){
			return json(['status'=>0,'msg'=>'没有记录']);
		}
		return json(['status'=>1,'data'=>$formorder]);
	}

	public function record(){
		$pagenum = input('post.pagenum');
        $st = input('post.st');
		if(!$pagenum) $pagenum = 1;
		$pernum = 20;
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['mid','=',mid];
		$where[] = ['isudel','=',0];

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
	public function formdetail(){
		$id = input('param.id/d');
		$detail = Db::name('workorder_order')->where('aid',aid)->where('mid',mid)->where('id',$id)->find();
		$detail['paytime'] = date('Y-m-d H:i:s',$detail['paytime']);
		$detail['createtime'] = date('Y-m-d H:i:s',$detail['createtime']);

		$form = Db::name('workorder_category')->where('aid',aid)->where('id',$detail['formid'])->find();
		$formcontent = json_decode($form['content'],true);
		foreach($formcontent as $k=>$form){
			if($form['key']=='upload'){
				$detail['form'.$k] = explode(',',$detail['form'.$k]);
			}
		}
		//var_dump($detail);
		$rdata = [];
		$rdata['form'] = $form;
		$rdata['formcontent'] = $formcontent;
		$rdata['detail'] = $detail;
		return $this->json($rdata);

	}


	public function formdelete(){
		$id = input('param.id/d');
		Db::name('workorder_order')->where('aid',aid)->where('mid',mid)->where('id',$id)->update(['isudel'=>1]);
		return json(['status'=>1,'msg'=>'操作成功']);
	}
}