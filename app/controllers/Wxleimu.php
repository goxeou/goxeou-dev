<?php


// +----------------------------------------------------------------------
// | 小程序服务类目设置
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;
use app\commons\Wechat;

class Wxleimu extends Common
{	
    public function initialize(){
		parent::initialize();
		if(bid > 0) showmsg('无访问权限');
	}
	//列表
	public function index(){
		$wxappinfo = Db::name('admin_setapp_wx')->where('aid',aid)->find();
		if(request()->isAjax()){
			if($wxappinfo['createtype']==1 || $wxappinfo['createtype']==2){
				$rs = request_get('https://api.weixin.qq.com/cgi-bin/wxopen/getcategory?access_token='.Wechat::access_token(aid,'wx'));
				$rs = json_decode($rs,true);
				$category_list = $rs['categories'];
				foreach($category_list as $k=>$v){
					$category_list[$k]['first_class'] = $v['first_name'];
					$category_list[$k]['second_class'] = $v['second_name'];
				}
			}else{
				$rs = request_get('https://api.weixin.qq.com/wxa/get_category?access_token='.Wechat::access_token(aid,'wx'));
				$rs = json_decode($rs,true);
				$category_list = $rs['category_list'];
				foreach($category_list as $k=>$v){
					$category_list[$k]['audit_status'] = 3;
				}
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>count($category_list),'data'=>$category_list]);
		}
		View::assign('wxappinfo',$wxappinfo);
		return View::fetch();
	}
    public function setleimu(){
		$post = input('post.');
		$datalist = [];

		$leimu = $post['leimu1'];
		$lmArr = explode('_',$leimu);
		$data = array();
		$data['first'] = $lmArr[0];
		$data['second'] = $lmArr[1];
		$certicates = array();
		foreach($post['exter_inner1'] as $k=>$v){
			$v = Wechat::pictomedia(aid,'wx',$v);
			$certicates[] = array('key'=>$k,'value'=>$v);
		}
		$data['certicates'] = $certicates;
		$datalist[] = $data;

		if($post['isshow2']==1){
			$leimu = $post['leimu2'];
			$lmArr = explode('_',$leimu);
			$data = array();
			$data['first'] = $lmArr[0];
			$data['second'] = $lmArr[1];
			$certicates = array();
			foreach($post['exter_inner2'] as $k=>$v){
				$v = Wechat::pictomedia(aid,'wx',$v);
				$certicates[] = array('key'=>$k,'value'=>$v);
			}
			$data['certicates'] = $certicates;
			$datalist[] = $data;
		}
		if($post['isshow3']==1){
			$leimu = $post['leimu3'];
			$lmArr = explode('_',$leimu);
			$data = array();
			$data['first'] = $lmArr[0];
			$data['second'] = $lmArr[1];
			$certicates = array();
			foreach($post['exter_inner3'] as $k=>$v){
				$v = Wechat::pictomedia(aid,'wx',$v);
				$certicates[] = array('key'=>$k,'value'=>$v);
			}
			$data['certicates'] = $certicates;
			$datalist[] = $data;
		}
		if($post['isshow4']==1){
			$leimu = $post['leimu4'];
			$lmArr = explode('_',$leimu);
			$data = array();
			$data['first'] = $lmArr[0];
			$data['second'] = $lmArr[1];
			$certicates = array();
			foreach($post['exter_inner4'] as $k=>$v){
				$v = Wechat::pictomedia(aid,'wx',$v);
				$certicates[] = array('key'=>$k,'value'=>$v);
			}
			$data['certicates'] = $certicates;
			$datalist[] = $data;
		}
		if($post['isshow5']==1){
			$leimu = $post['leimu5'];
			$lmArr = explode('_',$leimu);
			$data = array();
			$data['first'] = $lmArr[0];
			$data['second'] = $lmArr[1];
			$certicates = array();
			foreach($post['exter_inner5'] as $k=>$v){
				$v = Wechat::pictomedia(aid,'wx',$v);
				$certicates[] = array('key'=>$k,'value'=>$v);
			}
			$data['certicates'] = $certicates;
			$datalist[] = $data;
		}
		//var_dump($datalist);die;
		$url = 'https://api.weixin.qq.com/cgi-bin/wxopen/addcategory?access_token='.Wechat::access_token(aid);
		//dump(jsonEncode($data));die;
		$rs = request_post($url,jsonEncode(['categories'=>$datalist]));
		$rs = json_decode($rs,true);
		if($rs['errcode']!=0){
			return json(['status'=>0,'msg'=>\app\commons\Wechat::geterror($rs)]);
		}else{
			\app\commons\System::plog('添加小程序服务类目');
			return json(['status'=>1,'msg'=>'提交成功','url'=>(string)url('index')]);
		}
	}
	public function del(){//删除
		$data = array('first'=>$_POST['first'],'second'=>$_POST['second']);
		$rs = request_post('https://api.weixin.qq.com/cgi-bin/wxopen/deletecategory?access_token='.Wechat::access_token(aid),jsonEncode($data));
		$rs = json_decode($rs,true);
		if($rs['errcode']!=0){
			return json(['status'=>0,'msg'=>\app\commons\Wechat::geterror($rs)]);
		}else{
			\app\commons\System::plog('删除小程序服务类目');
			return json(['status'=>1,'msg'=>'删除成功']);
		}
	}
	public function getallleimu(){
		$category = request_get('https://api.weixin.qq.com/cgi-bin/wxopen/getallcategories?access_token='.Wechat::access_token(aid));
		$category = json_decode($category,true);
		//dump($category);
		$category_list = $category['categories_list']['categories'];
		//dump($category_list);
		$list = array();
		foreach($category_list as $v){
			if($v['level']==1){
				$list[$v['id']] = array('first'=>$v['id'],'first_name'=>$v['name']);
			}
		}
		$list2 = array();
		foreach($category_list as $v){
			if($v['level']==2){
				$thisarr = array(
					'first'=>$list[$v['father']]['first'],
					'first_name'=>$list[$v['father']]['first_name'],
					'second'=>$v['id'],
					'second_name'=>$v['name'],
				);
				if($v['sensitive_type']==1){
					$thisarr['exter_list'] = $v['qualify']['exter_list'];
				}
				$list2[] = $thisarr;
			}
		}
		$firstids = array_column($list2,'first');
		array_multisort($firstids,SORT_ASC,$list2);
		$list3 = array();
		foreach($list2 as $v){
			$list3[$v['first'].'_'.$v['second']] = $v;
		}
		return json(['status'=>0,'data'=>$list3,'asd'=>$category_list]);
	}
}