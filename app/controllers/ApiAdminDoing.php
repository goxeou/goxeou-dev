<?php


//表单提交记录
namespace app\controllers;
use think\facade\Db;
class ApiAdminDoing extends ApiAdmin
{	
	
	public function formlog(){
		$pagenum = input('post.pagenum');
        $st = input('post.st');
		if(!$pagenum) $pagenum = 1;
		$pernum = 20;
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['bid','=',bid];

		if(!input('?param.st') || $st === ''){
			$st = 'all';
		}
		if($st == 'all'){
			
		}elseif($st == '0'){
			$where[] = ['status','=',0];
		
		}elseif($st == '1'){
			$where[] = ['status','=',1];
		}elseif($st == '2'){
			$where[] = ['status','=',2];
		}elseif($st == '10'){
			$where[] = ['status','=',0];
			$where[] = ['paystatus','=',0];
			$where[] = ['payorderid','<>',''];
		}
		
		if(input('post.keyword')){
			$where[] = ['title|form0|form1|form2|form3|form4|form5|form6|form7|form8|form9|form10','like','%'.input('param.keyword').'%'];
		}

		//$where['status'] = 1;
		$datalist = Db::name('doing_order')->field('*,from_unixtime(createtime)createtime,from_unixtime(paytime)paytime')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
		if(!$datalist) $datalist = [];
		if(request()->isPost()){
			return $this->json(['status'=>1,'data'=>$datalist]);
		}
		$count = Db::name('form_order')->where($where)->count();
		$rdata = [];
		$rdata['count'] = $count;
		$rdata['datalist'] = $datalist;
		$rdata['pernum'] = $pernum;
		$rdata['st'] = $st;
		return $this->json($rdata);
	}
	//表单提交记录
	public function formdetail(){
		$id = input('param.id/d');
		$detail = Db::name('doing_order')->where('aid',aid)->where('bid',bid)->where('id',$id)->find();
			$doing = Db::name('doing')->where('id',$detail['formid'])->find();
         if(!$doing) return $this->json(['status'=>0,'msg'=>'活动不存在']);
		
		
		
		if(!$detail) return $this->json(['status'=>-4,'msg'=>'记录不存在']);
		$detail['paytime'] = date('Y-m-d H:i:s',$detail['paytime']);
		$detail['createtime'] = date('Y-m-d H:i:s',$detail['createtime']);
			$detail['starttime'] = date('Y-m-d H',$doing['starttime']);
        $detail['endtime'] = date('Y-m-d H',$doing['endtime']);
		$member = Db::name('member')->where('id',$detail['mid'])->find();
		 $detail['collect_time'] = $detail['collect_time'] ? date('Y-m-d H:i:s',$detail['collect_time']) : '';
		$detail['headimg'] = $member['headimg'];
		$detail['nickname'] = $member['nickname'];
		$form = Db::name('doing')->where('aid',aid)->where('bid',bid)->where('id',$detail['formid'])->find();
		$formcontent = json_decode($form['content'],true);
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
        $auth_data = json_decode($this->user['hexiao_auth_data'],true);
        $type = 'doing';
        if($this->user['isadmin']==0){
			if(!in_array($type,$auth_data)){
				return $this->json(['status'=>0,'msg'=>'您没有核销权限']);
			}
		}
        
		$order = Db::name('doing_order')->where('aid',aid)->where('bid',bid)->where('id',$id)->find();
		if(!$order) return json(['status'=>1,'msg'=>'操作失败']);
		if($order['status']!=1) return $this->json(['status'=>0,'msg'=>'订单状态错误']);
		
		if($st == 2 && $istuikuan == 1 && $order['paystatus']==1){
			$order['totalprice'] = $order['money'];
			$rs = \app\commons\Order::refund($order,$order['money'],'活动订单退款');
			if($rs['status']==0){
				return $this->json(['status'=>0,'msg'=>$rs['msg']]);
			}
			Db::name('doing_order')->where('aid',aid)->where('bid',bid)->where('id',$order['id'])->update(['collect_time'=>time(),'status'=>$st,'isrefund'=>1]);
		}elseif($st == 2){
			Db::name('doing_order')->where('aid',aid)->where('bid',bid)->where('id',$order['id'])->update(['collect_time'=>time(),'status'=>$st]);
		}
     	$data = array();
        $data['aid'] = aid;
        $data['bid'] = bid;
        $data['uid'] = $this->uid;
        $data['mid'] = $order['mid'];
        $data['orderid'] = $order['id'];
        $data['ordernum'] = $order['ordernum'];
        $data['title'] = $order['title'];
        $data['type'] = $type;
        $data['createtime'] = time();
        $data['remark'] = '核销员['.$this->user['un'].']核销';
     //  $data['mdid']   = empty($this->user['mdid'])?0:$this->user['mdid'];
		Db::name('hexiao_order')->insert($data);
		//审核结果通知
		$tmplcontent = [];
		$tmplcontent['first'] = ($st == 1 ? '恭喜您的活动报名已核销' : '抱歉您的活动报名未审核通过');
		$tmplcontent['remark'] = ($st == 1 ? '' : ($reason.'，')) .'请点击查看详情~';
		$tmplcontent['keyword1'] = $order['title'];
		$tmplcontent['keyword2'] = ($st == 1 ? '已核销' : '未通过');
		$tmplcontent['keyword3'] = date('Y年m月d日 H:i');
		\app\commons\Wechat::sendtmpl(aid,$order['mid'],'tmpl_shenhe',$tmplcontent,m_url('activity/doing/record'));
		//订阅消息
		$tmplcontent = [];
		$tmplcontent['thing8'] = $order['title'];
		$tmplcontent['phrase2'] = ($st == 1 ? '已核销' : '未通过');
		$tmplcontent['thing4'] = $reason;
		
		$tmplcontentnew = [];
		$tmplcontentnew['thing2'] = $order['title'];
		$tmplcontentnew['phrase1'] = ($st == 1 ? '已通过' : '未通过');
		$tmplcontentnew['thing5'] = $reason;
		\app\commons\Wechat::sendwxtmpl(aid,$order['mid'],'tmpl_shenhe',$tmplcontentnew,'activity/doing/record',$tmplcontent);

	}
	//删除
	public function formdel(){
		$id = input('param.id/d');
		Db::name('doing_order')->where('aid',aid)->where('bid',bid)->where('id',$id)->delete();
		return json(['status'=>1,'msg'=>'删除成功']);
	}
}