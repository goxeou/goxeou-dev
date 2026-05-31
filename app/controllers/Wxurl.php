<?php


// +----------------------------------------------------------------------
// | 小程序 外部链接
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;
use app\commons\Wechat;

class Wxurl extends Common
{	
    public function initialize(){
		parent::initialize();
		if(bid > 0) showmsg('无访问权限');
	}
	//列表
    public function index(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
				$order = 'id desc';
			}
			$where = array();
			$where[] = ['aid','=',aid];
			if(input('param.name')) $where[] = ['name','like','%'.input('param.name').'%'];
			if(input('param.path')) $where[] = ['path','like','%'.input('param.path').'%'];
			if(input('param.url')) $where[] = ['url','like','%'.input('param.url').'%'];
			if(input('?param.status') && input('param.status')!=='') $where[] = ['status','=',input('param.status')];
			//dump($where);
			$count = 0 + Db::name('wx_url')->where($where)->count();
			$data = Db::name('wx_url')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
    }
	//编辑
	public function edit(){
		if(input('param.id')){
			$info = Db::name('wx_url')->where('aid',aid)->where('id',input('param.id/d'))->find();
		}else{
			$info = array('id'=>'','days'=>'30');
		}
		$pcatelist = Db::name('wx_url')->where('aid',aid)->order('sort desc,id')->select()->toArray();
		View::assign('info',$info);
		return View::fetch();
	}
	//保存
	public function save(){
		$info = input('post.info/a');
		if($info['id']){
			$oldinfo = Db::name('wx_url')->where('id',$info['id'])->find();
			$info['endtime'] = $oldinfo['createtime'] + 86400 * $info['days'];
			Db::name('wx_url')->where('aid',aid)->where('id',$info['id'])->update($info);
		}else{
			/*
			$url = 'https://api.weixin.qq.com/wxa/generate_urllink?access_token='.\app\commons\Wechat::access_token(aid);

			$pathurl = explode('?',$info['path']);
			$postdata = [];
			$postdata['path'] = $pathurl[0];
			if($pathurl[1]){
				$postdata['query'] = $pathurl[1];
			}
			$postdata['expire_type'] = '1';
			$postdata['expire_interval'] = '30';
			$rs = curl_post($url,jsonEncode($postdata));
			$rs = json_decode($rs,true);
			if($rs['errcode']!=0){
				return json(['status'=>0,'msg'=>$rs['errcode'].'：'.$rs['errmsg']]);
			}
			$info['url'] = $rs['url_link'];
			*/

			$info['aid'] = aid;
			$info['createtime'] = time();
			$info['endtime'] = $info['createtime'] + 86400 * $info['days'];
			$id = Db::name('wx_url')->insertGetId($info);
			Db::name('wx_url')->where('id',$id)->update(['code'=>$this->getcode($id)]);
		}
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}
	private function getcode($num){
		$num = $num*29 + 400204032;
		$ABCstr = "";
		//if( $num ==0 ) return "A";
		while($num!=0){
			$x = $num%52;
			$ABCstr .= chr(65+$x);
			$num = intval($num/52);
		}
		$str = strrev($ABCstr);
		$fromArr = str_split('ABCDEFGHIJKLMNOPQRSTUVWXYZ[\]^_`abcdefghijklmnopqrst');
		$toArr = str_split('wauckFrLGjeyJoZzgCYbdNEmSIAKsflhHODUTtQMpXVqnPvxWRBi');
		$fromto = array_combine($fromArr,$toArr);
		$newstr = [];
		foreach(str_split($str) as $k=>$v){
			if($k==1){
				$newstr[5] = $fromto[$v];
			}elseif($k==5){
				$newstr[1] = $fromto[$v];
			}else{
				$newstr[$k] = $fromto[$v];
			}
		}
		ksort($newstr);
		return implode('',$newstr);
	}
	//删除
	public function del(){
		$ids = input('post.ids/a');
		Db::name('wx_url')->where('aid',aid)->where('id','in',$ids)->delete();
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	//
	public function test(){

		$url = 'https://api.weixin.qq.com/wxa/generatescheme?access_token='.\app\commons\Wechat::access_token(aid);
		$postdata = [];
		$postdata['jump_wxa'] = ['path'=>'/pagesExt/article/detail','query'=>'id=27'];
		$postdata['expire_type'] = '1';
		$postdata['expire_interval'] = '1';

		$rs = curl_post($url,jsonEncode($postdata));
		var_dump($rs);
		die;


		$url = 'https://api.weixin.qq.com/wxa/generate_urllink?access_token='.\app\commons\Wechat::access_token(aid);

		$postdata = [];
		$postdata['path'] = '/pagesExt/article/detail';
		$postdata['query'] = 'id=27';
		$postdata['expire_type'] = '1';
		$postdata['expire_interval'] = '30';

		$rs = curl_post($url,jsonEncode($postdata));
		var_dump($rs);
	}
}