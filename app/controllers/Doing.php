<?php

namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class Doing extends Common
{
	public function initialize(){
		parent::initialize();
		if(bid > 0) showmsg('无操作权限');
	}
	//列表
    public function index(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
				$order = 'sort desc,id desc';
			}
			$where = array();
			$where[] = ['aid','=',aid];
			if(input('param.cid')) $where[] = ['cid','=',input('param.cid/d')];
			if(input('param.name')) $where[] = ['name','like','%'.input('param.name').'%'];
			if(input('?param.status') && input('param.status')!==''){
				$where[] = ['status','=',input('param.status')];
			}
			$count = 0 + Db::name('doing')->where($where)->count();
			$carr = Db::name('doing_category')->where('aid',aid)->column('name','id');
			$data = Db::name('doing')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			foreach($data as $k=>$v){
		    	if($v['money'] > 0){
					$st0count = Db::name('doing_order')->where('formid',$v['id'])->where('status',1)->where('paystatus',1)->count();
				}else{
					$st0count = Db::name('doing_order')->where('formid',$v['id'])->where('status',1)->count();
				}
				$data[$k]['st0count'] = $st0count;
				$data[$k]['cname'] = $carr[$v['cid']];
			
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		//分类
		$clist = Db::name('doing_category')->Field('id,name')->where('aid',aid)->where('status',1)->order('sort desc,id')->select()->toArray();
        View::assign('clist',$clist);
		return View::fetch();
    }
	//编辑
	public function edit(){
		if(input('param.id')){
			$info = Db::name('doing')->where('aid',aid)->where('id',input('param.id/d'))->find();
         }else{
        
			$info = array('id'=>'','content'=>'[]','commissionset'=>'-1','status'=>'0','cid'=>'0','starttime'=>time(),'endtime'=>time() + 7*86400);
		}
		$info['cid'] = explode(',',$info['cid']);
		$clist = Db::name('doing_category')->Field('id,name')->where('aid',aid)->where('status',1)->order('sort desc,id')->select()->toArray();
		 $default_cid = Db::name('member_level_category')->where('aid',aid)->where('isdefault', 1)->value('id');
        $default_cid = $default_cid ? $default_cid : 0;
        $aglevellist = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->where('can_agent','<>',0)->order('sort,id')->select()->toArray();
		$levellist = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->order('sort,id')->select()->toArray();
		View::assign('aglevellist',$aglevellist);
		View::assign('levellist',$levellist);
		View::assign('clist',$clist);
		View::assign('info',$info);
		return View::fetch();
	}
	public function save(){
		$info = input('post.info/a');
		$info['commissiondata1'] = jsonEncode(input('post.commissiondata1/a'));
		$info['commissiondata2'] = jsonEncode(input('post.commissiondata2/a'));
        $datatype = input('post.datatype/a');
		$dataval1 = input('post.dataval1/a');
		$dataval2 = input('post.dataval2/a');
		$dataval3 = input('post.dataval3/a');
		$dataval4 = input('post.dataval4/a');
		$dataval5 = input('post.dataval5/a');
        $dataval6 = [];
        $dataval7 = [];
		$dataval8 = input('post.dataval8/a');
		$dataval9 = input('post.dataval9/a');
		$dataval10 = input('post.dataval10/a');
		$dataval11 = input('post.dataval11/a');
        $dataval12 = [];
        $dataval13 = [];
        $dataval18 = input('post.dataval18/a');
    	$dataval_query = input('post.dataval_query/a');
		$dhdata = array();
		foreach($datatype as $k=>$v){
			if($dataval3[$k]!=1) $dataval3[$k] = 0;
			$dhdata[] = [
			'key'=>$v,
			'val1'=>$dataval1[$k],
			'val2'=>$dataval2[$k],
			'val3'=>$dataval3[$k],
			'val4'=>$dataval4[$k],
			'val5'=>($dataval5 ? $dataval5[$k] : ''),
			'val6'=>$dataval6[$k]??0,
			'val7'=>$dataval7[$k]??0,
			'val8'=>$dataval8[$k]??0,
			'val9'=>$dataval9[$k]??0,
			'val10'=>$dataval10[$k]??0,
			'val11'=>$dataval11[$k]??0,
            'val12'=>$dataval12[$k]??0,
            'val13'=>$dataval13[$k]??0,
            'val18'=>$dataval18[$k]??'',
			'query'=>($dataval_query[$k] ? $dataval_query[$k] : '0'),
			'linkitem'=>$linkitem[$k]??'',
			
			];
		}
		
		ll($dataval18,'auto_day');
		$info['detail'] = \app\commons\Common::geteditorcontent($info['detail']);
    	$info['starttime'] = strtotime($info['starttime']);
        $info['endtime'] = strtotime($info['endtime']);
		$info['pics'] = $info['pics'];
		$info['content'] = json_encode($dhdata,JSON_UNESCAPED_UNICODE);
		if($info['id']){
// 			$bid = $info['id'];
			Db::name('doing')->where('aid',aid)->where('id',$info['id'])->update($info);
			\app\commons\System::plog('修改报名活动'.$info['id']);
		}else{
			$info['aid'] = aid;
			$info['createtime'] = time();
			$bid = Db::name('doing')->insertGetId($info);
			\app\commons\System::plog('添加报名活动'.$bid);
		}
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}


	public function del(){
		$ids = input('post.ids/a');
		Db::name('doing')->where('aid',aid)->where('id','in',$ids)->delete();
        \app\commons\System::plog('删除报名活动'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	//分类列表
    public function category(){
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
			if(input('param.name')) $where[] = ['name','like','%'.input('param.name').'%'];
			if(input('?param.status') && input('param.status')!=='') $where[] = ['status','=',input('param.status')];
			$count = 0 + Db::name('doing_category')->where($where)->count();
			$data = Db::name('doing_category')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
    }
	//编辑
	public function categoryedit(){
		if(input('param.id')){
			$info = Db::name('doing_category')->where('aid',aid)->where('id',input('param.id/d'))->find();
		}else{
			$info = array('id'=>'');
		}
		$pcatelist = Db::name('doing_category')->where('aid',aid)->order('sort desc,id')->select()->toArray();
		View::assign('info',$info);
		return View::fetch();
	}
	//保存
	public function categorysave(){
		$info = input('post.info/a');
		if($info['id']){
			Db::name('doing_category')->where('aid',aid)->where('id',$info['id'])->update($info);
			\app\commons\System::plog('修改活动分类'.$info['id']);
		}else{
			$info['aid'] = aid;
			$info['createtime'] = time();
			$id = Db::name('doing_category')->insertGetId($info);
			\app\commons\System::plog('添加活动分类'.$id);
		}
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}
	//删除
	public function categorydel(){
		$ids = input('post.ids/a');
		Db::name('doing_category')->where('aid',aid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('删除活动分类'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}

	//报名数据
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
			if(input('param.formid')) $where[] = ['formid','=',input('param.formid/d')];
			if(input('param.name')) $where[] = ['name','like','%'.input('param.name').'%'];
			if(input('param.ctime') ){
				$ctime = explode(' ~ ',input('param.ctime'));
				$where[] = ['createtime','>=',strtotime($ctime[0])];
				$where[] = ['createtime','<',strtotime($ctime[1]) + 86400];
			}
			if(input('?param.status') && input('param.status')!==''){
				$where[] = ['status','=',input('param.status')];
			}
			if(input('param.tel')){
				$where[] = ['form0|form1|form2|form3|form4|form5|form6|form7|form8|form9|form10','=',input('param.tel')];
			}
			if(input('param.keyword')){
				$where[] = ['form0|form1|form2|form3|form4|form5|form6|form7|form8|form9|form10','like','%'.input('param.keyword').'%'];
			}
			
			$form = Db::name('doing')->where('aid',aid)->where('id',input('param.formid/d'))->find();
			$formcontent = json_decode($form['content'],true);

			$count = 0 + Db::name('doing_order')->where($where)->count();
			$data = Db::name('doing_order')->where($where)->page($page,$limit)->order($order)->select()->toArray();
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
				$pics = [];
				foreach($formcontent as $k2=>$field){
					if($field['key']=='upload'){
						$pics[] = $v['form'.$k2];
					}
				}
				$data[$k]['pics'] = implode(',',$pics);
			}
			ll($data,'$data');
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		$form = Db::name('doing')->where('aid',aid)->where('id',input('param.formid/d'))->find();
		$formcontent = json_decode($form['content'],true);

		
		View::assign('form',$form);
		View::assign('formcontent',$formcontent);
		return View::fetch();
	}
	//报名数据导出
	public function recordexcel(){
		$form = Db::name('doing')->where('aid',aid)->where('id',input('param.formid/d'))->find();
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

		$list = Db::name('doing_order')->where($where)->order($order)->select()->toArray();
		
		$title = array();
		$title[] = '序号';
		$title[] = t('会员').'信息';
		$type = [];
        $type[] = 1;
		foreach($formcontent as $k=>$v){
			$title[]=$v['val1'];
            $type[]=$v['val4'];
		}
		if($form['price'] > 0){
			$title[] = '支付状态';
			$title[] = '支付金额';
			$title[] = '支付单号';
			$title[] = '支付时间';
		}
       
		$title[] = '提交时间';
		$title[] = '状态';
	
		$data = array();
		foreach($list as $v){
		    $parent = Db::name('member')->where('aid',aid)->where('id',$v['mid'])->find();
			$tdata = array();
			$tdata[] = $v['id'];
			$tdata[] = $parent['nickname'].'('.t('会员').'ID:'.$v['mid'].')';
			
			foreach($formcontent as $k=>$d){
				$tdata[] = $v['form'.$k];
			}
			if($form['price']>0){
				$tdata[] = $v['paystatus'] == 1?'已支付':'未支付';
				$tdata[] = $v['money'];
				$tdata[] = $v['paynum'];
				$tdata[] =  $v['paytime']? date("Y-m-d H:i:s",$v['paytime']) : '';
			}
            
			$tdata[] = date('Y-m-d H:i:s',$v['createtime']);
			$status = '';
			if($v['status']==0){
				$status = '待支付';
			}elseif($v['status']==1){
				$status = '待核销';
			}elseif($v['status']==2){
				$status = '已核销';
			}
			if($v['isudel']==1){
				$status.=',用户已删除';
			}
			$tdata[] = $status;
			$data[] = $tdata;
		}
		$this->export_excel($title,$data,$type);
	}
		//改状态
	public function recordsetst(){
		$id = input('post.id');
		$st = input('post.st/d');
		$istuikuan = input('post.istuikuan/d');
		$order = Db::name('doing_order')->where('aid',aid)->where('id',$id)->find();
	    if ($order['isrefund']==1 || $order['status']==0) {
	        return json(['status'=>0,'msg'=>'订单状态错误']);
	    }
		if($st == 2 && $istuikuan == 1 && $order['paystatus']==1){
			$order['totalprice'] = $order['money'];
			$rs = \app\commons\Member::addmoney($order['aid'],$order['mid'],$order['money'],'报名费用退款');
			if($rs['status']==0){
				return json(['status'=>0,'msg'=>$rs['msg']]);
			}
			Db::name('doing_order')->where('aid',aid)->where('id',$order['id'])->update(['collect_time'=>time(),'isrefund'=>1,'status'=>$st]);
		}elseif($st == 2){
			$reason = input('post.reason');
			Db::name('doing_order')->where('aid',aid)->where('id',$order['id'])->update(['collect_time'=>time(),'status'=>$st]);
		}
	    $data = array();
        $data['aid'] = aid;
        $data['bid'] = $order['bid'];
        $data['uid'] = $this->uid;
        $data['mid'] = $order['mid'];
        $data['orderid'] = $order['id'];
        $data['ordernum'] = $order['ordernum'];
        $data['title'] = $order['title'];
        $data['type'] = 'doing';
        $data['createtime'] = time();
        $data['remark'] = '核销员['.$this->user['un'].']核销';
		Db::name('hexiao_order')->insert($data);
		//审核结果通知
		$tmplcontent = [];
		$tmplcontent['first'] = ($st == 1 ? '恭喜您的活动报名已核销' : '抱歉您的报名未审核通过');
		$tmplcontent['remark'] = ($st == 1 ? '' : ($reason.'，')) .'请点击查看详情~';
		$tmplcontent['keyword1'] = $order['title'];
		$tmplcontent['keyword2'] = ($st == 1 ? '已核销' : '未通过');
		$tmplcontent['keyword3'] = date('Y年m月d日 H:i');
		\app\commons\Wechat::sendtmpl(aid,$order['mid'],'tmpl_shenhe',$tmplcontent,m_url('pages/form/formlog'));
		//订阅消息
		$tmplcontent = [];
		$tmplcontent['thing8'] = $order['title'];
		$tmplcontent['phrase2'] = ($st == 2 ? '已核销' : '已核销');
		$tmplcontent['thing4'] = $reason;
		
		$tmplcontentnew = [];
		$tmplcontentnew['thing2'] = $order['title'];
		$tmplcontentnew['phrase1'] = ($st == 1 ? '已核销' : '已核销');
		$tmplcontentnew['thing5'] = $reason;
		\app\commons\Wechat::sendwxtmpl(aid,$order['mid'],'tmpl_shenhe',$tmplcontentnew,'pages/form/formlog',$tmplcontent);
	
		\app\commons\System::plog('修改报名数据状态'.$id);
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//编辑报名数据
	public function recordedit(){
		$form = Db::name('doing')->where('aid',aid)->where('id',input('param.formid/d'))->find();
		if(!$form) return json(['status'=>0,'msg'=>'报名不存在']);
		if(bid !=0 && $form['bid']!=bid) return json(['status'=>0,'msg'=>'报名不存在']);
		$formcontent = json_decode($form['content'],true);
		$id = input('param.id/d');
		if($id){
			$order = Db::name('doing_order')->where('aid',aid)->where('formid',$form['id'])->where('id',$id)->find();
			if(!$order) return json(['status'=>0,'msg'=>'数据不存在']);
		}else{
			$order = ['formid'=>$form['id']];
		}
		View::assign('form',$form);
		View::assign('formcontent',$formcontent);
		View::assign('info',$order);
		return View::fetch();
	}
	public function recordsave(){
		$info = input('post.info/a');
		$form = Db::name('doing')->where('aid',aid)->where('id',input('param.formid/d'))->find();
		if(!$form) return json(['status'=>0,'msg'=>'报名不存在']);
		if(bid !=0 && $form['bid']!=bid) return json(['status'=>0,'msg'=>'报名不存在']);
		
		$formcontent = json_decode($form['content'],true);

		$data =[];
		$data['mid'] = $info['mid'];
		$data['status'] = $info['status'];
		if(getcustom('form_map')){
            $data['adr_lon'] = $info['adr_lon']??'';
            $data['adr_lat'] = $info['adr_lat']??'';
        }
		foreach($formcontent as $k=>$v){
			$value = $info['form'.$k];
			if(is_array($value)){
				$value = implode(',',$value);
			}
			$data['form'.$k] = strval($value);
			if($v['val3']==1 && $data['form'.$k]==='' && $v['key']!='map'){
				return json(['status'=>0,'msg'=>$v['val1'].' 必填']);
			}
		}

		if($form['price']>0){
			$price = input('post.price/f');
			$data['money'] = $price;
			$data['paystatus'] = $info['paystatus'];
		}

		if($info['id']){
			$oldinfo = Db::name('doing_order')->where('formid',$form['id'])->where('id',$info['id'])->find();
			if(!$oldinfo) return json(['status'=>0,'msg'=>'数据不存在']);
			Db::name('doing_order')->where('formid',$form['id'])->where('id',$info['id'])->update($data);
			if($data['status']!= 0 && $data['status'] != $oldinfo['status']){
				$st = $data['status'];
				//审核结果通知
				$tmplcontent = [];
				$tmplcontent['first'] = ($st == 1 ? '恭喜您的提交审核通过' : '抱歉您的提交未审核通过');
				$tmplcontent['remark'] = ($st == 1 ? '' : ($reason.'，')) .'请点击查看详情~';
				$tmplcontent['keyword1'] = $order['title'];
				$tmplcontent['keyword2'] = ($st == 1 ? '已通过' : '未通过');
				$tmplcontent['keyword3'] = date('Y年m月d日 H:i');
				\app\commons\Wechat::sendtmpl(aid,$order['mid'],'tmpl_shenhe',$tmplcontent,m_url('pages/form/formlog'));
			}
		}else{
			$data['aid'] = aid;
			$data['bid'] = $form['bid'];
			$data['formid'] = $form['id'];
			$data['title'] = $form['name'];
			$data['createtime'] = time();
			$data['ordernum'] = date('ymdHis').aid.rand(1000,9999);
			$orderid = Db::name('doing_order')->insertGetId($data);
			if($form['price'] >0 && $data['money'] > 0 && $data['paystatus']!=1){
				$payorderid = \app\models\Payorder::createorder(aid,$data['bid'],$data['mid'],'form',$orderid,$data['ordernum'],$data['title'],$data['money']);
			}
		}
		return json(['status'=>1,'msg'=>'操作成功']);
	}
//删除
	public function recorddel(){
		$ids = input('post.ids/a');
		if(bid != 0){
			Db::name('doing_order')->where('aid',aid)->where('bid',bid)->where('id','in',$ids)->delete();
		}else{
			Db::name('doing_order')->where('aid',aid)->where('id','in',$ids)->delete();
		}
		\app\commons\System::plog('删除报名数据'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
}
