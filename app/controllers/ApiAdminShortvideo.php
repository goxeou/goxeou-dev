<?php


namespace app\controllers;
use think\facade\Db;

class ApiAdminShortvideo extends ApiAdmin
{
	//发布短视频
	public function uploadvideo(){
		$this->checklogin();
		$sysset = Db::name('shortvideo_sysset')->where('aid',aid)->find();
		if($sysset['can_upload'] == 0) return json(['status'=>-4,'msg'=>'发布功能未启用']);
		if(request()->isPost()){
			$title = input('post.title');
			$content = input('post.content');
			$pics = input('post.pics');
			$video = input('post.video');
			$cid = input('post.cid');
			$data = [];
			$data['aid'] = aid;
			$data['bid'] = bid;
			$data['cid'] = $cid;
			//$data['mid'] = mid;
			$data['name'] = $title;
			$data['description'] = $content;
			$data['coverimg'] = $pics;
			$data['url'] = $video;
			$data['createtime'] = time();
			$needcheck = Db::name('business_sysset')->where('aid',aid)->value('shortvideo_check');
			if(bid!=0 && $needcheck==1){//需要审核
				$data['status'] = 0;
				$msg = '提交成功，请等待审核';
			}else{
				$data['status'] = 1;
				$msg = '发布成功';
			}
			Db::name('shortvideo')->insert($data);
			return $this->json(['status'=>1,'msg'=>$msg]);
		}
		$clist = Db::name('shortvideo_category')->where('aid',aid)->where('bid',bid)->where('status',1)->order('sort desc,id')->select()->toArray();
		$rdata = [];
		$rdata['clist'] = $clist;
		$rdata['sysset'] = $sysset;
		return $this->json($rdata);
	}
	//我的发表记录
	public function myupload(){
		$this->checklogin();
		$where[] = ['aid', '=', aid];
        $where[] = ['bid', '=', bid];
        $where[] = ['mid', '=', 0];
		$st = input('param.st');
		if(!input('?param.st') || input('param.st') === ''){
			$st = 'all';
		}
        if(input('param.keyword')) $where[] = ['name|description', 'like', '%'.input('param.keyword').'%'];

        $countall = Db::name('shortvideo')->where($where)->count();
        $count0 = Db::name('shortvideo')->where(array_merge($where,[['status', '=', 0]]))->count();
        $count1 = Db::name('shortvideo')->where(array_merge($where,[['status', '=', 1]]))->count();

        if($st == 'all'){

        }elseif($st == '0'){
            $where[] = ['status', '=', 0];
        }elseif($st == '1'){
            $where[] = ['status', '=', 1];
        }
		$pernum = 20;
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$datalist = Db::name('shortvideo')->where($where)->page($pagenum,$pernum)->order('sort desc,id desc')->select();
		if(!$datalist) $datalist = array();
		if(request()->isAjax()){
			return ['status'=>1,'data'=>$datalist];
		}
		$rdata = [];
		$rdata['countall'] = $countall;
		$rdata['count0'] = $count0;
		$rdata['count1'] = $count1;
		$rdata['datalist'] = $datalist;
		$rdata['st'] = $st;
		return $this->json($rdata);
	}
	//上下架
	public function myuploadsetst(){
		$this->checklogin();
		$st = input('post.st/d');
		$id = input('post.id/d');
		Db::name('shortvideo')->where('aid',aid)->where('bid',bid)->where('id',$id)->update(['status'=>$st]);
		return $this->json(['status'=>1,'msg'=>'操作成功']);
	}
	//删除
	public function myuploaddel(){
		$this->checklogin();
		$id = input('post.id/d');
		$rs = Db::name('shortvideo')->where('aid',aid)->where('bid',bid)->where('id',$id)->delete();
		return $this->json(['status'=>1,'msg'=>'操作成功']);
	}
}