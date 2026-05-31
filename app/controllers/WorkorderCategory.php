<?php


// +----------------------------------------------------------------------
// | 工单分类
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class WorkorderCategory extends Common
{	
	//表单列表
	public function index(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
				$order = 'sort desc,id desc';
			}
			$where = [];
			$where[] = ['aid','=',aid];
			if(bid==0){
				if(input('param.bid')){
					$where[] = ['bid','=',input('param.bid')];
				}elseif(input('param.showtype')==2){
					$where[] = ['bid','<>',0];
				}else{
					$where[] = ['bid','=',0];
				}
				$bids = explode(',',$this->user['bids']);
				if(!in_array('0',$bids)){
					$where[] = ['bid','in',$bids];
				}
			}else{
				$where[] = ['bid','=',bid];
			}

			if(input('param.name')) $where[] = ['name','like','%'.input('param.name').'%'];
			$count = 0 + Db::name('workorder_category')->where($where)->count();
			$data = Db::name('workorder_category')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			foreach($data as $k=>$v){
				
				if($v['payset'] == 1){
					$st0count = Db::name('workorder_order')->where('formid',$v['id'])->where('status',0)->where('paystatus',1)->count();
				}else{
					$st0count = Db::name('workorder_order')->where('formid',$v['id'])->where('status',0)->where('isudel',0)->count();
				}

				$data[$k]['st0count'] = $st0count;
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
	}
	//编辑
	public function edit(){
		if(input('param.id')){
			if(bid == 0){
				$info = Db::name('workorder_category')->where('aid',aid)->where('id',input('param.id/d'))->find();
			}else{
				$info = Db::name('workorder_category')->where('aid',aid)->where('bid',bid)->where('id',input('param.id/d'))->find();
			}
		}else{
			$info = array('id'=>'','content'=>'[]','commissionset'=>'-1');
		}
        $default_cid = Db::name('member_level_category')->where('aid',aid)->where('isdefault', 1)->value('id');
        $default_cid = $default_cid ? $default_cid : 0;
        $aglevellist = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->where('can_agent','<>',0)->order('sort,id')->select()->toArray();
		$levellist = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->order('sort,id')->select()->toArray();
		View::assign('aglevellist',$aglevellist);
		View::assign('levellist',$levellist);
		View::assign('info',$info);
		return View::fetch();
	}
	//保存
	public function save(){
		$info = input('post.info/a');
		$datatype = input('post.datatype/a');
		$dataval1 = input('post.dataval1/a');
		$dataval2 = input('post.dataval2/a');
		$dataval3 = input('post.dataval3/a');
		$dataval4 = input('post.dataval4/a');
		$dataval5 = input('post.dataval5/a');
		$dhdata = array();
		foreach($datatype as $k=>$v){
			if($dataval3[$k]!=1) $dataval3[$k] = 0;
			$dhdata[] = array('key'=>$v,'val1'=>$dataval1[$k],'val2'=>$dataval2[$k],'val3'=>$dataval3[$k],'val4'=>$dataval4[$k],'val5'=>($dataval5 ? $dataval5[$k] : ''));
		}
		$info['content'] = json_encode($dhdata,JSON_UNESCAPED_UNICODE);
		if($info['id']){
			if(bid == 0){
				Db::name('workorder_category')->where('aid',aid)->where('id',$info['id'])->update($info);
			}else{
				Db::name('workorder_category')->where('aid',aid)->where('bid',bid)->where('id',$info['id'])->update($info);
			}
			\app\commons\System::plog('编辑自定义工单类型'.$info['id']);
		}else{
			$info['aid'] = aid;
			$info['bid'] = bid;
			$info['createtime'] = time();
			$id = Db::name('workorder_category')->insertGetId($info);
			\app\commons\System::plog('添加自定义工单类型'.$id);
		}
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}
	//删除
	public function del(){
		$ids = input('post.ids/a');
		if(bid == 0){
			Db::name('workorder_category')->where('aid',aid)->where('id','in',$ids)->delete();
		}else{
			Db::name('workorder_category')->where('aid',aid)->where('bid',bid)->where('id','in',$ids)->delete();
		}
		\app\commons\System::plog('删除工单分类'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	//表单数据
	public function record(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
				$order = 'id desc';
			}
			$where = [];
			$where[] = ['aid','=',aid];
			if(bid != 0){
				$where[] = ['bid','=',bid];
			}
			$where[] = ['formid','=',input('param.formid/d')];
			if(input('param.user_id/d'))$where[] = ['userid','=',input('param.user_id/d')];
			if(input('param.name')) $where[] = ['name','like','%'.input('param.name').'%'];
			if(input('param.ctime') ){
				$ctime = explode(' ~ ',input('param.ctime'));
				$where[] = ['createtime','>=',strtotime($ctime[0])];
				$where[] = ['createtime','<',strtotime($ctime[1]) + 86400];
			}
			if(input('?param.status') && input('param.status')!==''){
				$where[] = ['status','=',input('param.status')];
			}
			if(input('?param.comment_status') && input('param.comment_status')!==''){
				$where[] = ['comment_status','=',input('param.comment_status')];
			}

			if(input('param.keyword')){
				$where[] = ['form0|form1|form2|form3|form4|form5|form6|form7|form8|form9|form10','like','%'.input('param.keyword').'%'];
			}
			
			$form = Db::name('workorder_category')->where('aid',aid)->where('id',input('param.formid/d'))->find();
			$formcontent = json_decode($form['content'],true);
			$count = 0 + Db::name('workorder_order')->where($where)->count();
			$data = Db::name('workorder_order')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			foreach($data as $k=>$v){
				$data[$k]['headimg'] = '';
				$data[$k]['nickname'] = '';
				if($v['mid']){
					$member = Db::name('member')->where('id',$v['mid'])->find();
					if($member){
						$data[$k]['headimg'] = $member['headimg'];
						$data[$k]['nickname'] = $member['nickname'];
					}
				}
				if($v['userid']){
					$user = Db::name('admin_user')->where('id',$v['userid'])->find();
					if($user){
						$data[$k]['username'] = $user['un'];
					}
				}
				$pics = [];
				foreach($formcontent as $k2=>$field){
					if($field['key']=='upload'){
						$pics =explode(',',$v['form'.$k2]);
					}

				}
				$data[$k]['pics'] = $pics;
			}

			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		$type =  input('param.type');
		//权限有 工单的员工list
		$user = Db::name('admin_user')->where('aid',aid)->where('bid',0)->where("workorder_type",$type)->select()->toArray();
		$form = Db::name('workorder_category')->where('aid',aid)->where('id',input('param.formid/d'))->find();
		$formcontent = json_decode($form['content'],true);
		View::assign('user',$user);
		View::assign('form',$form);
		View::assign('formcontent',$formcontent);
		return View::fetch();
	}
	//表单数据导出
	public function recordexcel(){
		$form = Db::name('workorder_category')->where('aid',aid)->where('id',input('param.formid/d'))->find();
		$formcontent = json_decode($form['content'],true);
		if(input('param.field') && input('param.order')){
			$order = input('param.field').' '.input('param.order');
		}else{
			$order = 'id desc';
		}
		$where = [];
		$where[] = ['aid','=',aid];
		if(bid != 0){
			$where[] = ['bid','=',bid];
		}
		$where[] = ['formid','=',input('param.formid/d')];
		if(input('param.name')) $where[] = ['name','like','%'.input('param.name').'%'];

		if(input('param.ctime') ){
			$ctime = explode(' ~ ',input('param.ctime'));
			$where[] = ['createtime','>=',strtotime($ctime[0])];
			$where[] = ['createtime','<',strtotime($ctime[1]) + 86400];
		}
		if(input('?param.status') && input('param.status')!==''){
			$where[] = ['status','=',input('param.status')];
		}
		if(input('param.keyword')){
			$where[] = ['form0|form1|form2|form3|form4|form5|form6|form7|form8|form9|form10','like','%'.input('param.keyword').'%'];
		}

		$list = Db::name('workorder_order')->where($where)->order($order)->select()->toArray();
		
		$title = array();
		$title[] = '序号';
		foreach($formcontent as $k=>$v){
			$title[]=$v['val1'];
		}
		if($form['payset']==1){
			$title[] = '支付状态';
			$title[] = '支付金额';
			$title[] = '支付单号';
			$title[] = '支付时间';
		}
		$title[] = '提交时间';
		$title[] = '状态';
		$title[] = '驳回原因';
		$title[] = '员工';
		$data = array();
		foreach($list as $v){
			$tdata = array();
			$tdata[] = $v['id'];
			foreach($formcontent as $k=>$d){
				$tdata[] = $v['form'.$k];
			}
			if($form['payset']==1){
				$tdata[] = $v['paystatus'] == 1?'已支付':'未支付';
				$tdata[] = $v['money'];
				$tdata[] = $v['paynum'];
				$tdata[] =  $v['paytime']? date("Y-m-d H:i:s",$v['paytime']) : '';
			}
			$tdata[] = date('Y-m-d H:i:s',$v['createtime']);
			$status = '';
			if($v['status']==0){
				$status = '待处理';
			}elseif($v['status']==1){
				$status = '处理中';
			}elseif($v['status']==2){
				$status = '已完成';
			}elseif($v['status']==-1){
				$status = '已驳回';
			}
			if($v['isudel']==1){
				$status.=',用户已删除';
			}
			$user = db('admin_user')->where('id',$v['userid'])->find();
			$tdata[] = $status;
			$tdata[] = $v['reason'];
			$tdata[] = $user['un'];
			$data[] = $tdata;
		}
		$this->export_excel($title,$data);
	}
	//改状态
	public function recordsetst(){
		$ids = input('post.ids/a');
		$st = input('post.st/d');
		$istuikuan = input('post.istuikuan/d');
		if(bid != 0){
			$orderlist = Db::name('workorder_order')->where('aid',aid)->where('bid',bid)->where('id','in',$ids)->select()->toArray();
		}else{
			$orderlist = Db::name('workorder_order')->where('aid',aid)->where('id','in',$ids)->select()->toArray();
		}
		foreach($orderlist as $order){
			if($st == 2 && $istuikuan == 1){
				$order['totalprice'] = $order['money'];
				$rs = \app\commons\Order::refund($order,$order['money'],input('post.reason'));
				if($rs['status']==0){
					return json(['status'=>0,'msg'=>$rs['msg']]);
				}
				Db::name('workorder_order')->where('aid',aid)->where('id',$order['id'])->update(['isrefund'=>1]);
			}
			if($st == 2){
				$reason = input('post.reason');
				Db::name('workorder_order')->where('aid',aid)->where('id',$order['id'])->update(['status'=>$st,'reason'=>$reason]);
			}else{
				Db::name('workorder_order')->where('aid',aid)->where('id',$order['id'])->update(['status'=>$st]);
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
		\app\commons\System::plog('修改表单数据状态'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//设置状态
	public function setst(){
		$aid = $this->aid;
		$ids = input('post.ids/a');
		Db::name('workorder_category')->where('aid',aid)->where('bid',bid)->where('id','in',$ids)->update(['status'=>input('post.st/d')]);
		return json(['status'=>1,'msg'=>'操作']);
	}
	//删除
	public function recorddel(){
		$ids = input('post.ids/a');
		if(bid != 0){
			Db::name('workorder_order')->where('aid',aid)->where('bid',bid)->where('id','in',$ids)->delete();
		}else{
			Db::name('workorder_order')->where('aid',aid)->where('id','in',$ids)->delete();
		}
		\app\commons\System::plog('删除表单数据'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	public function chooseform(){
		if(request()->isPost()){
			$data = Db::name('form')->where('aid',aid)->where('id',input('post.id/d'))->find();
			return json(['status'=>1,'msg'=>'查询成功','data'=>$data]);
		}
		return View::fetch();
	}
	public function formdetail(){
		$id = input('param.id/d');
		$detail = Db::name('workorder_order')->where('id',$id)->find();
		$detail['paytime'] = date('Y-m-d H:i:s',$detail['paytime']);
		$detail['createtime'] = date('Y-m-d H:i:s',$detail['createtime']);

		$form = Db::name('form')->where('aid',aid)->where('id',$detail['formid'])->find();
		$formcontent = json_decode($form['content'],true);

		$rdata = [];
		$rdata['form'] = $form;
		$rdata['formcontent'] = $formcontent;
		$rdata['detail'] = $detail;
		return json(['status'=>1,'data'=>$rdata]);

	}
	//获取员工
	public function getuser(){
		$order = Db::name(input('param.type'))->where('id',input('param.id'))->find();
		$user = Db::name('admin_user')->where('aid',aid)->where('bid',0)->where('status',1)->where('isadmin',0)->select()->toArray();
		foreach($user as $k=>$v){
			$title = $v['un'];
			$selectArr[] = ['id'=>$v['id'],'title'=>$title];
		}
		return json(['status'=>1,'user'=>$selectArr]);
	}

	//派单
	public function paidan(){
		$orderid = input('post.orderid/d');
		$userid = input('post.userid/d');
		$order = Db::name('workorder_order')->where('id',$orderid)->find();
		if(!$order) return json(['status'=>0,'msg'=>'工单不存在']);
		if($order['userid']) return json(['status'=>0,'msg'=>'工单已有员工处理']);

		Db::name('workorder_order')->where('id',$orderid)->update(['userid'=>$userid]);

		\app\commons\System::plog('工单派单'.$orderid);
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	
	//获取进度
	public function getjindu(){
		$post = input('post.');
		$data = Db::name('workorder_chuli')->where('aid',aid)->where('logid',$post['orderid'])->order('id desc')->select()->toArray();
		foreach($data as &$d){
			$d['time'] = date('Y-m-d H:i:s',$d['createtime']);
		}
		return json(['status'=>1,'data'=>$data]);
	}

}