<?php


/**
 * @deprecated 废弃
 */
namespace app\controllers;
use think\facade\Db;
class ApiTuiguang extends ApiCommon
{
	public function index(){

		$this->checklogin();
		$user = Db::name('tuiguang_user')->where('aid',aid)->where('mid',mid)->where('status',1)->find();
		if(!$user) $this->json(['status'=>0,'msg'=>'您不是推广员']);

		$set = Db::name('tuiguang_set')->where('aid',aid)->find();
		$list = Db::name('tuiguang_record')->where('tgid',$user['id'])->order('id desc')->select()->toArray();
		$rdata = [];
		$rdata['user'] = $user;
		$rdata['list'] = $list;
		$rdata['set'] = $set;
		return $this->json($rdata);
	}
	public function phb(){
		$userlist = Db::name('tuiguang_user')->where('aid',aid)->where('status',1)->field('*,(select count(1) from '.table_name('tuiguang_record').' where tgid='.table_name('tuiguang_user').'.id) c')->order('c desc')->select()->toArray();
		$rdata = [];
		$rdata['list'] = $userlist;
		return $this->json($rdata);
	}
	public function fillview(){
		if(input('param.rid')){
			$user = Db::name('tuiguang_user')->where('aid',aid)->where('mid',mid)->where('status',1)->find();
			if(!$user) $this->json(['status'=>0,'msg'=>'您不是推广员']);
			$record = Db::name('tuiguang_record')->where('aid',aid)->where('id',input('param.rid/d'))->find();
			if($record['tgid']!=$user['id']){
				return $this->json(['status'=>0,'msg'=>'无操作权限']);
			}
		}else{
			$record = Db::name('tuiguang_record')->where('aid',aid)->where('mid',mid)->find();
			if(!$record){
				return $this->json(['status'=>0,'msg'=>'请扫描推广码进入']);
			}
		}
		
		$record['createtime'] = date('Y-m-d H:i:s',$record['createtime']);

		$set = Db::name('tuiguang_set')->where('aid',aid)->find();
		$formcontent = json_decode($set['formcontent'],true);
		$rdata = [];
		$rdata['formcontent'] = $formcontent;
		$rdata['detail'] = $record;
		return $this->json($rdata);
	}
	//推广填写表单
	public function fillform(){
		$this->checklogin();
		$set = Db::name('tuiguang_set')->where('aid',aid)->find();
		if($set['status']==0){
			return $this->json(['status'=>0,'msg'=>'活动已关闭']);
		}
		if(input('param.rid')){
			$user = Db::name('tuiguang_user')->where('aid',aid)->where('mid',mid)->where('status',1)->find();
			if(!$user) $this->json(['status'=>0,'msg'=>'您不是推广员']);
			$record = Db::name('tuiguang_record')->where('aid',aid)->where('id',input('param.rid/d'))->find();
			if($record['tgid']!=$user['id']){
				return $this->json(['status'=>0,'msg'=>'无操作权限']);
			}
		}else{
			$record = Db::name('tuiguang_record')->where('aid',aid)->where('mid',mid)->find();
			if(!$record){
				return $this->json(['status'=>0,'msg'=>'请扫描推广码进入']);
			}
		}
		if($record['isfill']){
			return $this->json(['status'=>1,'msg'=>'您已填写完成']);
		}
		$formcontent = json_decode($set['formcontent'],true);
		if(request()->isPost()){
			$post = input('post.');
			$fromdata = $post['formdata'];
			$data = [];
			$data['isfill'] = 1;
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
			$data['createtime'] = time();
			if(input('param.rid')){
				Db::name('tuiguang_record')->where('aid',aid)->where('id',input('param.rid/d'))->update($data);
			}else{
				Db::name('tuiguang_record')->where('aid',aid)->where('mid',mid)->update($data);
			}
			if($set['givecheck']==0 && $record['isgive']==0){
				$tguser = Db::name('tuiguang_user')->where('aid',aid)->where('id',$record['tgid'])->find();
				if($set['givemoney'] > 0){
					\app\commons\Member::addmoney(aid,$tguser['mid'],$set['givemoney'],'推广奖励');
				}
				if($set['givescore'] > 0){
					\app\commons\Member::addscore(aid,$tguser['mid'],$set['givescore'],'推广奖励');
				}
				Db::name('tuiguang_record')->where('id',$record['id'])->update(['isgive'=>1,'givemoney'=>$set['givemoney'],'givescore'=>$set['givescore']]);
			}
			if(input('param.rid')){
				return $this->json(['status'=>1,'msg'=>'提交成功']);
			}else{
				return $this->json(['status'=>1,'msg'=>'提交成功','url'=>true]);
			}
		}
		$rdata = [];
		$rdata['formcontent'] = $formcontent;
		return $this->json($rdata);
	}

}