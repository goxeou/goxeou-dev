<?php


//名片功能
namespace app\controllers;
use think\facade\Db;
class ApiMingpian extends ApiCommon
{	
	public function index(){
		$set = Db::name('mingpian_set')->where('aid',aid)->find();
		if(!$set || $set['status'] == 0) return json(['status'=>-5,'msg'=>'功能未启用']);
		$nologin = false;//是否不登录
		if(input('param.scene') && input('param.scene') == 1154){ //朋友圈卡片的场景
			$nologin = true;
		}
		if(!$nologin){
			$this->checklogin();
		}
		$where = [];
        $where[] = ['aid', '=', aid];
		if(input('param.id')){
			$where[] = ['id', '=', input('param.id/d')];
		}else{
			$where[] = ['mid', '=', mid];
		}

		$info = Db::name('mingpian')->where($where)->find();
		if(!$info){
			if(input('param.id')){
				return json(['status'=>-4,'msg'=>'名片不存在','url'=>'/pagesExt/mingpian/edit']);
			}else{
				return json(['status'=>-4,'msg'=>'请先创建名片','url'=>'/pagesExt/mingpian/edit']);
			}
		}
		$pagecontent = json_decode(\app\commons\System::initpagecontent($info['detail'],aid),true);
		if(!$pagecontent) $pagecontent = [];
        $field_list = json_decode($set['field_list'],true);

		$newfield_list = [];
		$field_list2 = [];
		$i = 0;
		foreach($field_list as $k=>$v){
			$i++;
			if($i <= 3){
				$field_list2[$k] = $v;
			}
			if($v['isshow'] == 1){
				$newfield_list[$k] = $v;
			}
		}
		$viewmymp = false;
		if($this->member && $info['mid'] != mid){
			Db::name('mingpian_readlog')->where('aid',aid)->where('mid',mid)->where('mpid',$info['id'])->delete();
			$data = [];
			$data['aid'] = aid;
			$data['mid'] = mid;
			$data['mpid'] = $info['id'];
			$data['createtime'] = time();
			Db::name('mingpian_readlog')->insert($data);

			$hasmp = Db::name('mingpian')->where('aid',aid)->where('mid',mid)->find();
			if($hasmp){
				$viewmymp = true;
			}
		}
		$open_haibao = 0;
		$rdata = [];
		$rdata['info'] = $info;
		$rdata['mid'] = mid;
		$rdata['pagecontent'] = $pagecontent;
		$rdata['field_list'] = $newfield_list;
		$rdata['field_list2'] = $field_list2;
		$rdata['viewmymp'] = $viewmymp;
		$rdata['open_haibao'] = $open_haibao;
		$rdata['islogin'] = false;//是否登录
		if($this->member && mid ){
			$rdata['islogin'] = true;
		}
		return $this->json($rdata);
	}
	//谁看过
	public function readlog(){
		$this->checklogin();
		$id = input('param.id/d');
		$info = Db::name('mingpian')->where('id',$id)->find();
		if(!$info || $info['mid'] != mid) return json(['status'=>-5,'msg'=>'无权限查看']);
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$pernum = 20;
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['mpid','=',$id];
		$datalist = Db::name('mingpian_readlog')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
		if(!$datalist) $datalist = [];
		foreach($datalist as $k=>$v){
			$memberinfo = Db::name('member')->where('id',$v['mid'])->field('headimg,nickname')->find();
			$datalist[$k]['headimg'] = $memberinfo['headimg'];
			$datalist[$k]['nickname'] = $memberinfo['nickname'];
		}

        return $this->json(['status'=>1,'data'=>$datalist]);
	}
	//谁收藏了
	public function favoritelog(){
		$this->checklogin();
		$id = input('param.id/d');
		$info = Db::name('mingpian')->where('id',$id)->find();
		if(!$info || $info['mid'] != mid) return json(['status'=>-5,'msg'=>'无权限查看']);
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$pernum = 20;
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['mpid','=',$id];
		$datalist = Db::name('mingpian_favorite')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
		if(!$datalist) $datalist = [];
		foreach($datalist as $k=>$v){
			$memberinfo = Db::name('member')->where('id',$v['mid'])->field('headimg,nickname')->find();
			$datalist[$k]['headimg'] = $memberinfo['headimg'];
			$datalist[$k]['nickname'] = $memberinfo['nickname'];
		}
        return $this->json(['status'=>1,'data'=>$datalist]);
	}
	//编辑名片
	public function edit(){
		$this->checklogin();
		$set = Db::name('mingpian_set')->where('aid',aid)->find();
		if(!$set || $set['status'] == 0) return json(['status'=>-5,'msg'=>'功能未启用']);

		
		$createtj = explode(',',$set['createtj']);
		if(!in_array('-1',$createtj) && !in_array($this->member['levelid'],$createtj)){ //不是所有人
			return $this->json(['status'=>-5,'msg'=>'您没有权限创建名片']);
		}

		$info = Db::name('mingpian')->where('aid',aid)->where('mid',mid)->find();
		if(!$info) $info = [];

		$bglist = $set['bgpics'] ? explode(',',$set['bgpics']) : [];
		
		$pagecontent = json_decode(\app\commons\System::initpagecontent($info['detail'],aid),true);
		if(!$pagecontent) $pagecontent = [];

		$field_list = json_decode($set['field_list'],true);

		$rdata = [];
		$rdata['status'] = 1;
		$rdata['info'] = $info;
		$rdata['bglist'] = $bglist;
		$rdata['field_list'] = $field_list;
		$rdata['pagecontent'] = $pagecontent;
		return $this->json($rdata);
	}
	//保存名片
	public function save(){
		$this->checklogin();
		$mingpian = Db::name('mingpian')->where('aid',aid)->where('mid',mid)->find();
		$info = input('post.info/a');
		$data = array();
		$data['aid'] = aid;
		$data['mid'] = mid;
		$data['bgpic'] = $info['bgpic'];
		$data['headimg'] = $info['headimg'];
		$data['realname'] = $info['realname'];
		$touxianArr = [];
		if($info['touxian1']) $touxianArr[] = $info['touxian1'];
		if($info['touxian2']) $touxianArr[] = $info['touxian2'];
		if($info['touxian3']) $touxianArr[] = $info['touxian3'];

		$data['touxian1'] = $touxianArr[0];
		$data['touxian2'] = $touxianArr[1] ?? '';
		$data['touxian3'] = $touxianArr[2] ?? '';
		$data['tel'] = $info['tel'];
		$data['weixin'] = $info['weixin'];
		$data['address'] = $info['address'];
		$data['email'] = $info['email'];
		$data['douyin'] = $info['douyin'];
		$data['weibo'] = $info['weibo'];
		$data['toutiao'] = $info['toutiao'];
		$data['field1'] = $info['field1'] ?? '';
		$data['field2'] = $info['field2'] ?? '';
		$data['field3'] = $info['field3'] ?? '';
		$data['field4'] = $info['field4'] ?? '';
		$data['field5'] = $info['field5'] ?? '';
		$data['sharetitle'] = $info['sharetitle'] ?? '';
		$data['longitude'] = $info['longitude'] ?? '';
		$data['latitude'] = $info['latitude'] ?? '';
		
		$data['detail'] = json_encode(input('post.pagecontent'));
		
		if(!$mingpian){
			$data['createtime'] = time();
		}
		$data['updatetime'] = time();

		if($mingpian){
			Db::name('mingpian')->where('aid',aid)->where('id',$mingpian['id'])->update($data);
		}else{
			$data['aid'] = aid;
			$proid = Db::name('mingpian')->insertGetId($data);
		}
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//删除
	public function del(){
		$this->checklogin();
		$id = input('post.id/d');
		$rs = Db::name('mingpian')->where('aid',aid)->where('mid',mid)->delete();
		return $this->json(['status'=>1,'msg'=>'操作成功']);
	}

	//存名片夹
	public function addfavorite(){
		$this->checklogin();
		$id = input('param.id/d');
		$info = Db::name('mingpian')->where('aid',aid)->where('id',$id)->find();
		if(!$info){
			return json(['status'=>0,'msg'=>'未找到该名片']);
		}
		Db::name('mingpian_favorite')->where('aid',aid)->where('mid',mid)->where('mpid',$id)->delete();
		$data = [];
		$data['aid'] = aid;
		$data['mid'] = mid;
		$data['mpid'] = $info['id'];
		$data['createtime'] = time();
		Db::name('mingpian_favorite')->insert($data);
		return json(['status'=>1,'msg'=>'保存成功']);
	}
	//删名片夹
	public function delfavorite(){
		$this->checklogin();
		$id = input('param.id/d');
		Db::name('mingpian_favorite')->where('aid',aid)->where('mid',mid)->where('id',$id)->delete();
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//我的名片夹
	public function favorite(){
		$this->checklogin();
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$pernum = 20;
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['mid','=',mid];
		$datalist = Db::name('mingpian_favorite')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
		if(!$datalist) $datalist = [];
		foreach($datalist as $k=>$v){
			$datalist[$k]['info'] = Db::name('mingpian')->where('id',$v['mpid'])->find();
		}
		if($pagenum == 1){
			$set = Db::name('mingpian_set')->where('aid',aid)->find();
			$field_list = json_decode($set['field_list'],true);
			
			$newfield_list = [];
			$field_list2 = [];
			$i = 0;
			foreach($field_list as $k=>$v){
				$i++;
				if($i <= 3){
					$field_list2[$k] = $v;
				}
				if($v['isshow'] == 1){
					$newfield_list[$k] = $v;
				}
			}
		}
        return $this->json(['status'=>1,'data'=>$datalist,'field_list'=>$newfield_list,'field_list2'=>$field_list2]);
	}

	function getposter(){
		$this->checklogin();
		$post = input('post.');
		$platform = platform;
		$page = '/pagesExt/mingpian/index';
		$info = Db::name('mingpian')->where('aid',aid)->where('mid',mid)->find();
		$scene = 'id_'.$info['id'];
		//if($platform == 'mp' || $platform == 'h5' || $platform == 'app'){
		//	$page = PRE_URL .'/h5/'.aid.'.html#'. $page;
		//}
		$posterset = Db::name('admin_set_poster')->where('aid',aid)->where('type','mingpian')->where('platform',$platform)->order('id')->find();

//		$posterdata = Db::name('member_poster')->where('aid',aid)->where('mid',mid)->where('scene',$scene)->where('type','kecheng')->where('posterid',$posterset['id'])->find();
        //关闭缓存
        if(true || !$posterdata){
			
			$textReplaceArr = [
				'[头像]'=>$info['headimg'],
				'[姓名]'=>$info['realname'],
				'[电话]'=>$info['tel'],
				'[微信]'=>$info['weixin'],
				'[地址]'=>$info['address'],
				'[头衔1]'=>$info['touxian1'],
				'[头衔2]'=>$info['touxian2'],
				'[头衔3]'=>$info['touxian3'],
			];

			$poster = $this->_getposter(aid,0,$platform,$posterset['content'],$page,$scene,$textReplaceArr);
			$posterdata = [];
			$posterdata['aid'] = aid;
			$posterdata['mid'] = $this->member['id'];
			$posterdata['scene'] = $scene;
			$posterdata['page'] = $page;
			$posterdata['type'] = 'mingpian';
			$posterdata['poster'] = $poster;
            $posterdata['posterid'] = $posterset['id'];
			$posterdata['createtime'] = time();
//			Db::name('member_poster')->insert($posterdata);
		}
		return $this->json(['status'=>1,'poster'=>$posterdata['poster']]);
	}
}