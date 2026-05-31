<?php


namespace app\controllers;
use think\facade\Db;
class ApiDoing extends ApiCommon
{	
   	public function initialize(){
		parent::initialize();
		$this->checklogin();
	}
   
    public function list(){
		$bid = input('param.bid') ? input('param.bid') : 0;
		//分类
		if(input('param.cid')){
			$cid = input('param.cid/d');
			$clist = Db::name('doing_category')->where('id',$cid)->where('aid',aid)->where('status',1)->order('sort desc,id')->select()->toArray();
			if(!$clist) $clist = [];
		}else{
			$clist = Db::name('doing_category')->where('aid',aid)->where('status',1)->order('sort desc,id')->select()->toArray();
			if(!$clist) $clist = [];
		}
		return $this->json(['clist'=>$clist]);
	}
   
	public function getlist(){
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['status','=',1];
		$where[] = ['starttime', '<=', time()];
		$where[] = ['endtime', '>', time()];
		
		$bid = input('param.bid') ? input('param.bid') : 0;
		if(input('param.bid')){
			$where[] = ['bid','=',input('param.bid/d')];
		}elseif(false){}else{
			$where[] = ['bid','=',0];
		}
		if(input('param.field') && input('param.order')){
			$order = input('param.field').' '.input('param.order').',sort,id desc';
		}else{
			$order = 'sort desc,id desc';
		}
		//分类 
		if(input('param.cid')){
			$cid = input('post.cid') ? input('post.cid/d') : input('param.cid/d');
            $where[] =['cid','=',$cid];
            
		}
		if(input('param.keyword')){
			$where[] = ['name','like','%'.input('param.keyword').'%'];
		}
		$pernum = 10;
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$datalist = Db::name('doing')->where($where)->page($pagenum,$pernum)->order($order)->select()->toArray();
		foreach($datalist as &$d){
		    $d['endtime'] = date('Y-m-d H:i:s',$d['endtime']);
		   
			//查看共有几节课
		//	$d['count'] = 0+Db::name('kecheng_chapter')->where(['aid'=>aid,'kcid'=>$d['id']])->count();
		}
		if(!$datalist) $datalist = [];
	
		return $this->json(['status'=>1,'data'=>$datalist]);
	}
	public function detail(){
		$id = input('param.id/d');
		$detail = Db::name('doing')->where('id',$id)->where('aid',aid)->find();
		if(!$detail) return $this->json(['status'=>0,'msg'=>'活动不存在']);
		if($detail['status'] == 0) return json(['status'=>0,'msg'=>'活动未开启']);
		if($detail['starttime'] > time()) return json(['status'=>0,'msg'=>'活动未开始']);
		if($detail['endtime'] < time()) return json(['status'=>0,'msg'=>'活动已结束']);
        //$starttime = strtotime(date('Y-m-d'));
        $detail['canapply'] = 1;
        if($detail['maxlimit'] > 0){
			$count = 0 + Db::name('doing_order')->where('formid',$detail['id'])->count();
			if($count >= $detail['maxlimit']){
				$detail['canapply'] = 0;
			}
		}
		$mycs = 0 + Db::name('doing_order')->where('formid',$detail['id'])->where('mid',mid)->count();
		if($detail['perlimit'] > 0 && $mycs >= $detail['perlimit']){
		    $detail['canapply'] = 0;
		}
		 if(!$detail['pics']) $detail['pics'] = $detail['pic'];
	    $detail['pics'] = explode(',',$detail['pics']);
		$detail['starttime'] = date('Y-m-d  H:i:s',$detail['starttime']);
		$detail['endtime'] = date('Y-m-d  H:i:s',$detail['endtime']);
		Db::name('doing')->where('id',$id)->inc('readnum')->update();
		$detail['createtime'] = date('Y-m-d',$detail['createtime']);
		$rdata = [];
		$rdata['status'] = 1;
		$rdata['detail'] = $detail;
		return $this->json($rdata);
	}
	public function baoming(){
		$id = input('param.id/d');
		$detail = Db::name('doing')->where('id',$id)->where('aid',aid)->find();
		if(!$detail) return $this->json(['status'=>0,'msg'=>'活动不存在']);
		if($detail['status'] == 0) return json(['status'=>0,'msg'=>'活动未开启']);
		if($detail['starttime'] > time()) return json(['status'=>0,'msg'=>'活动未开始']);
		if($detail['endtime'] < time()) return json(['status'=>0,'msg'=>'活动已结束']);
       
        $detail['canapply'] = 1;
        if($detail['maxlimit'] > 0){
			$count = 0 + Db::name('doing_order')->where('formid',$detail['id'])->count();
			if($count >= $detail['maxlimit']){
		      	return $this->json(['status'=>0,'msg'=>'提交人数已满']);
			}
		}
		$mycs = 0 + Db::name('doing_order')->where('formid',$detail['id'])->where('mid',mid)->count();
		if($detail['perlimit'] > 0 && $mycs >= $detail['perlimit']){
		   	return $this->json(['status'=>0,'msg'=>$detail['perlimit']==1?'您已经提交过了':'每人最多可提交'.$detail['perlimit'].'次']);
		}
		$detail['content'] = json_decode($detail['content'],true);
		$rdata = [];
		$xieyi = Db::name('admin_set_xieyi')->where('aid',aid)->find();
		if(!$xieyi) $xieyi = ['status3'=>0,'name3'=>0,'content3'=>''];

		$rdata['xystatus'] = $xieyi['status3'];
		$rdata['xyname'] = $xieyi['name3'];
		$rdata['xycontent'] = $xieyi['content3'];
		$rdata['formdata'] =  $detail;
		$rdata['status'] = 1;
		return $this->json($rdata);
	}
	
	//提交表单
	public function formsubmit(){
		
		$post = input('post.');
	
		$doing = Db::name('doing')->where('aid',aid)->where('id',$post['formid'])->find();
		if($doing['status'] == 0) return json(['status'=>0,'msg'=>'活动未开启']);
		if($doing['starttime'] > time()){
			return $this->json(['status'=>0,'msg'=>'活动未开始']);
		}
		if($doing['endtime'] < time()){
			return $this->json(['status'=>0,'msg'=>'活动已结束']);
		}
		if($doing['maxlimit'] > 0){
			$count = 0 + Db::name('doing_order')->where('formid',$doing['id'])->count();
			if($count >= $doing['maxlimit']){
				return $this->json(['status'=>0,'msg'=>'提交人数已满']);
			}
		}
		$mycs = 0 + Db::name('doing_order')->where('formid',$doing['id'])->where('mid',mid)->count();
		if($doing['perlimit'] > 0 && $mycs >= $doing['perlimit']){
			return $this->json(['status'=>0,'msg'=>$doing['perlimit']==1?'您已经提交过了':'每人最多可提交'.$doing['perlimit'].'次']);
		}

		
		$data =[];
		$data['aid'] = aid;
		$data['bid'] = $doing['bid'];
		$data['formid'] = $doing['id'];
		$data['title'] = $doing['name'];
		$data['mid'] = mid;
		$data['createtime'] = time();
    	$data['hexiao_code'] = random(16);
		$data['hexiao_qr'] = createqrcode(m_url('admin/hexiao/hexiao?type=doing&co='.$data['hexiao_code']));
		//var_dump($post);
		$fromdata = $post['formdata'];
		$doingcontent = json_decode($doing['content'],true);
		foreach($doingcontent as $k=>$v){
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
			if($v['val3']==1 && $data['form'.$k]==='' && !$v['linkitem']){
				return $this->json(['status'=>0,'msg'=>$v['val1'].' 必填']);
			}
		}
		$price = 0;
		if($doing['price'] > 0){
		    $is_other_fee = 0;
            $price = $doing['price'];
		}else {
		    $data['status'] = 1;
		    $data['paytype'] = '无需支付';
		}
		$ordernum = date('ymdHis').aid.rand(1000,9999);
		$data['money'] = $price;
		$data['ordernum'] = $ordernum;
		$data['fromurl'] = $post['fromurl'];
		if(false){}else{
			$orderid = Db::name('doing_order')->insertGetId($data);
		}
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
			$payorderid = \app\models\Payorder::createorder(aid,$data['bid'],$data['mid'],'doing',$orderid,$data['ordernum'],$data['title'],$data['money']);
			return $this->json(['status'=>2,'msg'=>'需要支付','orderid'=>$orderid,'payorderid'=>$payorderid,'tmplids'=>$tmplids,'fee'=>$feedata]);
		}else{
		    Db::name('doing')->where('id',$post['formid'])->inc('joinnum')->update();
		    
			$tmplcontent = [];
			$tmplcontent['first'] = '有客户提交报名成功';
			$tmplcontent['remark'] = '点击查看详情~';
			$tmplcontent['keyword1'] = $doing['name'];
			$tmplcontent['keyword2'] = date('Y-m-d H:i');
			\app\commons\Wechat::sendhttmpl(aid,$doing['bid'],'tmpl_formsub',$tmplcontent,'');
			return $this->json(['status'=>1,'msg'=>'提交成功','tmplids'=>$tmplids]);
		}
	}
	public function formlog(){
		$pagenum = input('post.pagenum');
        $st = input('post.st');
		if(!$pagenum) $pagenum = 1;
		$pernum = 20;
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['mid','=',mid];
		$where[] = ['isudel','=',0];
		
		if(input('post.keyword')){
			$where[] = ['title|form0|form1|form2|form3|form4|form5|form6|form7|form8|form9|form10','like','%'.input('param.keyword').'%'];
		}

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
		}
		//$where['status'] = 1;
		$datalist = Db::name('doing_order')->field('*,from_unixtime(createtime)createtime,from_unixtime(paytime)paytime')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
		if(!$datalist) $datalist = [];

		if($datalist){
		    foreach($datalist as $dk=>$detail){
                $form = Db::name('doing')->where('aid',aid)->where('id',$detail['formid'])->find();
                $formcontent = json_decode($form['content'],true);

                $linkitemArr = [];
                foreach($formcontent as $k=>$v){
                    if(($v['key'] == 'radio' || $v['key'] == 'selector') && $detail['form'.$k]!==''){
                        $linkitemArr[] = $v['val1'].'|'.$detail['form'.$k];
                    }
                }
                foreach($formcontent as $k=>$v){
                    if($v['linkitem'] && !in_array($v['linkitem'],$linkitemArr)){
                        $formcontent[$k]['hidden'] = true;
                    }
                   
                }
                //距离
                $detail['distance'] = '';
                $detail['show_distance'] = 0;
                $detail['formcontent'] = $formcontent;
                $datalist[$dk] = $detail;
            }
        }
		if(request()->isPost()){
			return $this->json(['status'=>1,'data'=>$datalist]);
		}
		$count = Db::name('doing_order')->where($where)->count();
		$rdata = [];
		$rdata['count'] = $count;
		$rdata['datalist'] = $datalist;
		$rdata['pernum'] = $pernum;
		$rdata['st'] = $st;
		return $this->json($rdata);
	}
	public function formdetail(){
		$id = input('param.id/d');
        $op = input('param.op');
		$detail = Db::name('doing_order')->where('aid',aid)->where('mid',mid)->where('id',$id)->find();
		$detail['paytime'] = date('Y-m-d H:i:s',$detail['paytime']);
		$detail['createtime'] = date('Y-m-d H:i:s',$detail['createtime']);
	
	 
    	$doing = Db::name('doing')->where('id',$detail['formid'])->find();
         if(!$doing) return $this->json(['status'=>0,'msg'=>'活动不存在']);
	
		$detail['starttime'] = date('Y-m-d H',$doing['starttime']);
        $detail['endtime'] = date('Y-m-d H',$doing['endtime']);
        $detail['collect_time'] = $detail['collect_time'] ? date('Y-m-d H:i:s',$detail['collect_time']) : '';
        $detail['distance'] = '';
        $detail['show_distance'] = 0;
		$form = Db::name('doing')->where('aid',aid)->where('id',$detail['formid'])->find();
		$formcontent = json_decode($form['content'],true);
		$linkitemArr = [];
		foreach($formcontent as $k=>$v){
			if(($v['key'] == 'radio' || $v['key'] == 'selector') && $detail['form'.$k]!==''){
				$linkitemArr[] = $v['val1'].'|'.$detail['form'.$k];
			}
		}
		foreach($formcontent as $k=>$v){
			if($v['linkitem'] && !in_array($v['linkitem'],$linkitemArr)){
				$formcontent[$k]['hidden'] = true;
			}
		}
		$againname = '再次提交';
		$rdata = [];
		$rdata['form'] = $form;
		$rdata['formcontent'] = $formcontent;
		$rdata['detail'] = $detail;
		$rdata['againname'] = $againname;
		return $this->json($rdata);

	}
	public function formdelete(){
		$id = input('param.id/d');
		Db::name('doing_order')->where('aid',aid)->where('mid',mid)->where('id',$id)->update(['isudel'=>1]);
		return json(['status'=>1,'msg'=>'操作成功']);
	}
}