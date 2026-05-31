<?php


namespace app\controllers;
use think\facade\Db;
class ApiSucai extends ApiCommon
{
    public function initialize(){
		parent::initialize();
	}
    public function index(){
		$clist = Db::name('sucai_category')->where('aid',aid)->where('status',1)->order('sort desc,id')->select()->toArray();
		$rdata = [];
		$rdata['status'] = 1;
		$rdata['sysset'] = $sysset;
		$rdata['clist'] = $clist;
		return $this->json($rdata);
	}
	public function getprolist(){
	    
	    
	    $where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['status','=',1];
	
		if(input('param.cid')){
			$where[] = ['cid','=',input('param.cid/d')];
		}else{
		
		}
		$type = input('param.st');
	   // if ($type) {
	   //   	$where[] = ['type','=',1];
	   // }else {
	   //     $where[] = ['type','=',0];
	   // }
		if(input('param.keyword')){
			$where[] = ['content','like','%'.input('param.keyword').'%'];
		}
		
		if(input('param.field') && input('param.order')){
			$order = input('param.field').' '.input('param.order').',sort,id desc';
		}else{
			$order = 'sort desc,id desc';
		}

		$pernum = 20;
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
        $datalist = Db::name('sucai')->where($where)->page($pagenum,$pernum)->order($order)->select()->toArray();
		if(!$datalist){
			$datalist = array();
		} else {
		    foreach ($datalist as $k=>$v) {
    			$datalist[$k]['createtime'] = date('Y-m-d H:i',$v['createtime']);
    			if($v['pics']){
    				$datalist[$k]['pics'] = explode(',',$v['pics']);
    			}
            }
        }
		return $this->json(['status'=>1,'data'=>$datalist]);
		$count = Db::name('scoreshop_product')->where($where)->count();

		$rdata = [];
		$rdata['clist'] = $clist;
		$rdata['searchcid'] = $searchcid;
		$rdata['pernum'] = $pernum;
		$rdata['count'] = $count;
		$rdata['datalist'] = $datalist;
		return $this->json($rdata);
	   
	}

}