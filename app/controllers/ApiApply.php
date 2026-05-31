<?php
namespace app\controllers;
use think\facade\Db;
class ApiApply extends ApiCommon
{	
	 public function initialize(){
		parent::initialize();
	}
    public function apply(){
		$this->checklogin();
		$info = Db::name('member_apply_area')->where('aid',aid)->where('mid',mid)->find();
		if(request()->isPost()){
	    	$post = input('post.');
	    	$type = input('post.type');
	    	
	    	if(!$this->member['levelid']){
				return $this->json(['status'=>0,'msg'=>'您当前等级暂无权限申请']);
			}
	    	$level = Db::name('member_level')->where('id',$this->member['levelid'])->find();
	    	if(!in_array($type,explode(',',$level['areafenhong_new']))){
				return $this->json(['status'=>0,'msg'=>'您当前等级暂无权限申请']);
			}
	    	
			if ($type==1) {
		     	$have = Db::name('member_apply_area')->where('aid',aid)->where('mid','<>',mid)->where('status',1)->where('type',$type)->where('province',$post['province'])->find();
			}elseif ($type==2) {
		     	$have = Db::name('member_apply_area')->where('aid',aid)->where('mid','<>',mid)->where('status',1)->where('type',$type)->where('province',$post['province'])->where('city',$post['city'])->find();
			}elseif ($type==3) {
			     $have = Db::name('member_apply_area')->where('aid',aid)->where('mid','<>',mid)->where('status',1)->where('type',$type)->where('province',$post['province'])->where('city',$post['city'])->where('area',$post['area'])->find();
			}
			if($have){
				return $this->json(['status'=>0,'msg'=>'本区域已有代理商']);
			}
			$apply = Db::name('member_apply_area')->where('aid',aid)->where('mid',mid)->find();
            $data = [];
            $data['tel'] = $post['tel'];
            $data['name'] = $post['name'];
            $data['province'] = $post['province'];
			$data['city'] = $post['city'];
            $data['area'] = $post['area'];
            $data['type'] = $post['type'];
            $data['status'] =0;
            if ($apply) {
			    Db::name('member_apply_area')->where('aid',aid)->where('mid',mid)->update($data);
			}else {
    		    $data['aid'] = aid;
    	    	$data['mid'] = mid;
    	    	 $data['createtime'] = time();
    			Db::name('member_apply_area')->insert($data);
			}
			return $this->json(['status'=>1,'msg'=>'提交成功,请等待审核']);
		}
		if ($info){
		    $info['headimg'] = $this->member['headimg'];
		    $info['nickname'] = $this->member['nickname'];
		    $info['count0'] = 0 + Db::name('member_fenhonglog')->where('aid',aid)->where('mid',mid)->where('type','areafenhong')->sum('commission');
		    $dayEnd = strtotime(date('Y-m-d').' 00:00:00');
    	    $dayStart =  $today_start - 86400 * 10;
            $totalnum = 0 + Db::name('member_team_money')->where('aid',aid)->where('mid',mid)->where('createtime', 'between', [$dayStart,$dayEnd])->sum('totalnum');
		    $info['count1'] =$totalnum;
		}else {
		     $info = array('status'=>-1);
		} 
		return json(['status'=>1,'data'=>$info,'applyimg'=>$applyimg]);
	}

			//分红记录
	public function yunlog(){
		$st = input('param.st');
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['mid','=',mid];
		$where[] = ['type','=','areafenhong'];
		$pernum = 20;
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$datalist = Db::name('member_fenhonglog')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
	
		foreach($datalist as $k=>$v){
		}
		if(!$datalist) $datalist = [];
		return $this->json(['status'=>1,'data'=>$datalist]);
	}	//改状态
	
}