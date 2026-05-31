<?php


namespace app\controllers;
use think\facade\Db;
class ApiMemberArchives extends ApiCommon
{
	public function initialize(){
		parent::initialize();
		$this->checklogin();
	}
	public function index(){
		$pagenum = input('param.pagenum/d');
		if(!$pagenum) $pagenum = 1;
		$cid = input('param.cid/d');
		$bid = input('param.bid') ? input('param.bid') : 0;
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['mid','=',mid];
		$where[] = ['status','=',1];
		if(input('param.keyword')){
			$where[] = ['name','like','%'.input('param.keyword').'%'];
		}
		$datalist = Db::name('member_archives')->where($where)->order('sort desc,id desc')->page($pagenum,20)->select()->toArray();
		if(!$datalist) $datalist = [];
		foreach($datalist as $k=>$v){
			$datalist[$k]['createtime'] = date('Y年m月d日 H:i:s',$v['createtime']);
		}
		return $this->json(['status'=>1,'data'=>$datalist]);
	}
	public function detail(){
		$id = input('param.id/d');
		$detail = Db::name('member_archives')->where('id',$id)->where('mid',mid)->where('status',1)->find();
		if(!$detail) return $this->json(['status'=>0,'msg'=>'档案不存在']);

		$detail['createtime'] = date('Y年m月d日 H:i:s',$detail['createtime']);
		$pagecontent = json_decode(\app\commons\System::initpagecontent($detail['content'],aid,mid,platform),true);

		$rdata = [];
		$rdata['status'] = 1;
		$rdata['detail'] = $detail;
		$rdata['pagecontent'] = $pagecontent;
		return $this->json($rdata);
	}
}