<?php
namespace app\controllers;
use think\facade\Db;
class ApiExpert extends ApiCommon {
	public function initialize() {
		parent::initialize();
		$this->checklogin();
	}
			//删除回复
	public function checkreply() {
		$id = input('param.id/d');
		$detail = Db::name('expert_comment_order')->where('id',$id)->find();
		$replynum = Db::name('expert_replylog')->where('mid',mid)->count();
		$article_resource = Db::name('article_resource')->where('mid',mid)->count();
		$replynum = $replynum+$article_resource;
		$checkreply = Db::name('member_level')->field('id,checktel,checkreply')->where('id',$this->member['levelid'])->value('checkreply');
		if ($checkreply - $replynum > 0) {
			Db::name('expert_replylog')->insert(['aid'=>aid,'mid'=>mid,'proid'=>$id,'type'=>'reply','createtime'=>time()]);
			Db::name('expert_comment_order')->where('id',$id)->update(['paystatus'=>1,'paytype'=>'会员消耗次数']);
			return $this->json(['status'=>1,'msg'=>'查看成功']);
		} else {
		    //生成保证金订单，跳转支付
			$ordernum = date('ymdHis').aid.rand(1000,9999);
			$money = $detail['money'];
			$orderdata = [];
			$orderdata['ordernum'] = $ordernum;
			Db::name('expert_comment_order')->where('id',$id)->update($orderdata);
			$payorderid = \app\models\Payorder::createorder(aid,0,$detail['mid'],'expert_comment',$detail['id'],$ordernum,'专家回复费',$money);
			return $this->json(['status'=>-3,'msg'=>'提交成功，请支付￥ '.$money.'后查看','url'=>'/pagesExt/pay/pay?id='.$payorderid,'orderid'=>$detail['id'],'payorderid'=>$payorderid]);
		}
		
	}
	//删除回复
	public function checktel() {
		$id = input('param.id/d');
		$detail = Db::name('expert')->where('id',$id)->find();
		$readlog = Db::name('expert_readlog')->where('sid',$id)->where('mid',mid)->find();
		if($readlog) {
			return $this->json(['status'=>1,'phone'=>$detail['linktel']]);
		} else {
			$readnum = Db::name('expert_readlog')->where('mid',mid)->count();
			$checktel = Db::name('member_level')->field('id,checktel,checkreply')->where('id',$this->member['levelid'])->value('checktel');
			if ($checktel - $readnum > 0) {
				Db::name('expert_readlog')->insert(['aid'=>aid,'mid'=>mid,'sid'=>$id,'createtime'=>time()]);
				return $this->json(['status'=>1,'checktel'=>$detail['linktel']]);
			} else {
				return $this->json(['status'=>0,'msg'=>'当前等级无权限查看']);
			}
		}
	}
	//获取商品列表 评价列表
	public function getdatalist() {
		$id = input('param.id/d');
		$st = input('param.st/d');
		$mendian_id = input('param.mendian_id/d',0);
		//如果切换了门店，则只显示该商家下该门店的数据
		$unserlevel = Db::name('member_level')->field('id,checktel,checkreply,showreply')->where('id',$this->member['levelid'])->find();
		$pagenum = input('param.pagenum');
		if(!$pagenum) $pagenum = 1;
		if($st == 0) {
		
			$where = [];
    		$where[] = ['aid','=',aid];
    		$where[] = ['status','=',1];
			$datalist = Db::name('article')->where($where)->order('sort desc,id desc')->page($pagenum,20)->select()->toArray();
	    	if(!$datalist) $datalist = [];
			foreach($datalist as $k=>$v){
    			$datalist[$k]['createtime'] = date('Y-m-d',$v['createtime']);
    			if(getcustom('article_portion')){
    				if($v['pic']){
    					$pic_arr = explode(',',$v['pic']);
    					if($set['listtype'] == 5){
    						$pic = [];
    						if($pic_arr[0]){
    							array_push($pic,$pic_arr[0]);
    						}
    						if($pic_arr[1]){
    							array_push($pic,$pic_arr[1]);
    						}
    						if($pic_arr[2]){
    							array_push($pic,$pic_arr[2]);
    						}
    						$datalist[$k]['pic'] = $pic;
    					}else{
    						$datalist[$k]['pic'] = $pic_arr[0];
    					}
    				}
    			}
    		}
			return $this->json(['status'=>1,'data'=>$datalist]);
		} else {
			//评价
			$pernum = 10;
			$where = [];
			$where[] = ['aid','=',aid];
			$where[] = ['sid','=',$id];
			$where[] = ['status','=',1];
			if ($unserlevel['showreply']==0) {
			    $where[] = ['mid','=',mid];
			}
			$commentlist = Db::name('expert_comment_order')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
			if(!$commentlist) $commentlist = [];
			foreach($commentlist as $k=>$pl) {
				$commentlist[$k]['createtime'] = date('Y-m-d H:i',$pl['createtime']);
				if($commentlist[$k]['content_pic']) $commentlist[$k]['content_pic'] = explode(',',$commentlist[$k]['content_pic']);
				if ($pl['reply_content']) {
					$commentlist[$k]['reply_time'] = date('Y-m-d H:i',$pl['reply_time']);
				}
				if($commentlist[$k]['reply_content_pic']) $commentlist[$k]['reply_content_pic'] = explode(',',$commentlist[$k]['reply_content_pic']);
				
				/*******************回复显示*********************/
				$expert = Db::name('expert')->where('id',$pl['sid'])->find();
				$replylog = Db::name('expert_replylog')->where('proid',$pl['id'])->where('type','reply')->where('mid',mid)->find();
        		if ($replylog  || $expert['mid']==mid  || ($pl['mid']==mid && $pl['paystatus']==1)) {
        	     	$showreply = 1;
        		} else {
        			$showreply = 0;
        		}
        		if ($pl['mid']!=mid && $unserlevel['showreply']==1) {
        		    $showreply = 1;
        		}
        		$commentlist[$k]['showreply']	 = $showreply;
        		
			}
			if(request()->isPost()) {
				return $this->json(['status'=>1,'data'=>$commentlist]);
			}
		}
	}
	//获取商品列表 评价列表
	public function commentlist() {
		$this->checklogin();
		$pagenum = input('param.pagenum');
		if(!$pagenum) $pagenum = 1;
		$pernum = 10;
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['mid','=',mid];
		$where[] = ['status','=',1];
		$commentlist = Db::name('expert_comment_order')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
		if(!$commentlist) $commentlist = [];
		foreach($commentlist as $k=>$pl) {
			$commentlist[$k]['createtime'] = date('Y-m-d H:i',$pl['createtime']);
			if($commentlist[$k]['content_pic']) $commentlist[$k]['content_pic'] = explode(',',$commentlist[$k]['content_pic']);
			if ($pl['reply_content']) {
				$commentlist[$k]['reply_time'] = date('Y-m-d H:i',$pl['reply_time']);
			}
			if($commentlist[$k]['reply_content_pic']) $commentlist[$k]['reply_content_pic'] = explode(',',$commentlist[$k]['reply_content_pic']);
			$expert = Db::name('expert')->where('id',$pl['sid'])->find();
			if(!$expert) {
				Db::name('expert_comment_order')->where('id',$pl['sid'])->delete();
				unset($commentlist[$k]);
			} else {
				$commentlist[$k]['business'] = $expert;
			}
			/*******************回复显示*********************/
			$expert = Db::name('expert')->where('id',$pl['sid'])->find();
			$replylog = Db::name('expert_replylog')->where('proid',$pl['id'])->where('type','reply')->where('mid',mid)->find();
    		if ($replylog  || $expert['mid']==mid  || ($pl['mid']==mid && $pl['paystatus']==1)) {
    	     	$showreply = 1;
    		} else {
    			$showreply = 0;
    		}
    		
    		if ($pl['mid']!=mid && $unserlevel['showreply']==1) {
    		    $showreply = 1;
    		}
    		$commentlist[$k]['showreply']	 = $showreply;
		}
		if(request()->isPost()) {
			return $this->json(['status'=>1,'data'=>$commentlist]);
		}
	}
	public function replylist() {

		$pagenum = input('param.pagenum');
		if(!$pagenum) $pagenum = 1;
		$detail = Db::name('expert')->where('aid',aid)->where('mid',mid)->where('status',1)->find();
		$pernum = 10;
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['sid','=',$detail['id']];
		$where[] = ['status','=',1];
		$commentlist = Db::name('expert_comment_order')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
		if(!$commentlist) $commentlist = [];
		foreach($commentlist as $k=>$pl) {
			$commentlist[$k]['createtime'] = date('Y-m-d H:i',$pl['createtime']);
			if($commentlist[$k]['content_pic']) $commentlist[$k]['content_pic'] = explode(',',$commentlist[$k]['content_pic']);
			if ($pl['reply_content']) {
				$commentlist[$k]['reply_time'] = date('Y-m-d H:i',$pl['reply_time']);
			}
			if($commentlist[$k]['reply_content_pic']) $commentlist[$k]['reply_content_pic'] = explode(',',$commentlist[$k]['reply_content_pic']);
			$expert = Db::name('expert')->where('id',$pl['sid'])->find();
			if(!$expert) {
				Db::name('expert_comment_order')->where('id',$pl['sid'])->delete();
				unset($commentlist[$k]);
			} else {
				$commentlist[$k]['business'] = $expert;
			}
		}
		if(request()->isPost()) {
			return $this->json(['status'=>1,'data'=>$commentlist]);
		}
	}
	//评论
	public function subpinglun() {
		$id = input('param.id/d');
		$type = input('param.type/d');
		$hfid = input('param.hfid/d');
		$content = trim(input('param.content'));
		$content_pic = input('post.content_pic');
		$money = input('post.money');
		if(!$id) {
			return $this->json(['status'=>0,'msg'=>'参数错误']);
		}
		$detail = Db::name('expert')->where('id',$id)->where('status',1)->find();
		if($content=='') {
			return $this->json(['status'=>1,'msg'=>'请输入评论内容']);
		}
		$sysset = Db::name('expert_sysset')->where('aid',aid)->find();
		if($type==0) {
			$data = [];
			$data['aid'] = aid;
			$data['mid'] = mid;
			$data['sid'] = $id;
			$data['headimg'] = $this->member['headimg'];
			$data['nickname'] = $this->member['nickname'];
			$data['content'] = $content;
			$data['content_pic'] = $content_pic;
			$data['createtime'] = time();
			$data['status'] = 1;
			$msg = '发表评论成功';
			Db::name('expert_comment_order')->insert($data);
		} else {
			$data = [];
			$data['reply_content'] = $content;
			$data['reply_content_pic'] = $content_pic;
			$data['reply_time'] = time();
			$data['money'] = $money;
			if ($money<=0) {
			    $data['paystatus'] = 1;
			    $data['paytype'] = '无需付款';
			} 
			$msg = '回复评论成功';
			Db::name('expert_comment_order')->where('aid',aid)->where('id',$hfid)->update($data);
		}
		return $this->json(['status'=>1,'msg'=>$msg,'url'=>true]);
	}
	//商家详情页
	public function index() {
		$bid =  input('param.id/d');
		$business = Db::name('expert')->where('aid',aid)->where('id',$bid)->where('status',1)->find();
		if(!$business) return $this->json(['status'=>0,'msg'=>'专家信息不存在']);
		$bset = Db::name('expert_sysset')->where('aid',aid)->find();
		if ($business['linktel']) {
			$business['showtel'] = 1;
		} else {
			$business['showtel'] = 0;
		}
		/*******************电话显示*********************/
		$readlog = Db::name('expert_readlog')->where('sid',$bid)->where('mid',mid)->find();
		if ($readlog  || $business['mid']==mid) {
			$business['showLinkStatus'] = 1;
			$business['linktel'] = $business['linktel'];
		} else {
			$business['showLinkStatus'] = 0;
			$business['linktel'] =  substr($business['linktel'],0,3).'****'.substr($business['linktel'],-4);
		}
		$zanlog = Db::name('expert_zanlog')->where('proid',$business['id'])->where('mid',mid)->find();
		if($zanlog) {
			$iszan = 1;
		} else {
			$iszan = 0;
		}
// 		$showreply = Db::name('member_level')->field('id,checktel,checkreply,showreply')->where('id',$this->member['levelid'])->value('showreply');
		$plcount = Db::name('expert_comment_order')->where('sid',$bid)->where('status',1)->count();
		$rdata = [];
		$rdata['status'] = 1;
		$rdata['isdiy'] = 0;
		$rdata['iszan'] = $iszan;
// 		if ($showreply==1) {
// 		    $rdata['st'] = 1;
// 		}else {
		    $rdata['st'] = 0;
		//}
		$rdata['plcount'] = $plcount;
		$rdata['bset'] = $bset;
		$rdata['business'] = $business;
		$rdata['mid'] = mid;
		return $this->json($rdata);
	}
	//商家列表
	public function blist() {
		$carr = Db::name('expert_category')->where('aid',aid)->column('name','id');
		if(request()->isPost()) {
			$pernum = 10;
			$pagenum = input('post.pagenum/d');
			if(!$pagenum) $pagenum = 1;
			$cid = input('post.cid/d');
			$where = [];
			$where[] = ['aid','=',aid];
			$where[] = ['status','=',1];
			if(input('param.keyword')) {
				$where[] = ['name|desc|cnames','like','%'.input('param.keyword').'%'];
			}
			$order = 'sort desc,id desc';
			$field = 'id,logo,name,cid,tel,desc,address,work,viewnum';
			$datalist = Db::name('expert')->where($where)->field($field)->page($pagenum,$pernum)->order($order)->select()->toArray();
			if(!$datalist) $datalist = array();
			foreach($datalist as $k=>$v) {
				// $sales = Db::name('business_sales')->where('bid',$v['id'])->value('total_sales');
				// $v['sales'] = $sales?:0;
				$cnames = [];
				if($v['cid']) {
					$cids = explode(',',$v['cid']);
					foreach($cids as $cid) {
						$cnames[] = $carr[$cid];
					}
				}
				$datalist[$k]['cname'] = $cnames?$cnames:[];
				if($v['mid']) {
					$member = Db::name('member')->where('id',$v['mid'])->find();
					$datalist[$k]['nickname'] = $member['nickname'];
					$datalist[$k]['headimg'] = $member['headimg'];
				} else {
					$datalist[$k]['nickname'] = '';
					$datalist[$k]['headimg'] = '';
				}
			}
			ll($datalist,'$datalist');
			return $this->json(['status'=>1,'data'=>$datalist]);
		}
		$bset = Db::name('expert_sysset')->where('aid',aid)->find();
		//分类
		$rdata = [];
		$rdata['pics'] =  $bset['pics'] ? explode(',',$bset['pics']) : [];
		;
		$rdata['showtype'] = 0;
		return $this->json($rdata);
	}
	//入驻申请
	public function setinfo() {
		$bset = Db::name('expert_sysset')->where('aid',aid)->field( 'deposit')->find();
		if(request()->isPost()) {
			$formdata = input('post.info/a');
			if(!checkTel($formdata['linktel'])) {
				return $this->json(['status'=>0, 'msg'=>'请输入正确的手机号']);
			}
			$info = [];
			$info['aid'] = aid;
			$info['mid'] = mid;
			$info['cid'] = implode(',',$formdata['cid']);
			$info['name'] = $formdata['name'];
			$info['linkman'] = $formdata['linkman'];
			$info['linktel'] = $formdata['linktel'];
			$cnames = Db::name('expert_category')->Field('id,name')->where('aid',aid)->where('id','in',$formdata['cid'])->column('name','id');
			if ($cnames) {
				$info['cnames'] = implode(',',$cnames);
			} else {
				$info['cnames'] ='';
			}
			$info['tel'] = $formdata['tel'];
			$info['logo'] = $formdata['pic'];
			$info['work'] = $formdata['work'];
			$info['desc'] = $formdata['desc'];
			$info['status'] = 0;
			$info['createtime'] = time();
			if($formdata['id']) {
				Db::name('expert')->where('aid',aid)->where('mid',mid)->where('id',$formdata['id'])->update($info);
			}
			return $this->json(['status'=>1,'msg'=>'提交成功']);
		}
		$info = Db::name('expert')->where('aid',aid)->where('mid',mid)->find();
		if(!$info) {
			return $this->json(['status'=>2,'msg'=>'您还未申请入驻']);
		}
		if($info && $info['logo']) {
			$info['pic'] = $info['logo'];
		}
		$glist = Db::name('expert_category')->where('aid',aid)->where('status',1)->order('sort desc,id')->select()->toArray();
		$groupArr = Db::name('expert_category')->Field('id,name')->where('aid',aid)->column('name','id');
		$rdata = [];
		$rdata['glist'] = $glist;
		$rdata['title'] = '商家资料';
		$rdata['groupArr'] = $groupArr;
		$rdata['bset'] = $bset;
		$rdata['info'] = $info ? $info : [];
		$rdata['gids'] = $info['cid'] ? explode(',',$info['cid']) : [];
		return $this->json($rdata);
	}
	//入驻申请
	public function apply() {
		$bset = Db::name('expert_sysset')->where('aid',aid)->field( 'xieyi_show,xieyi,deposit')->find();
		if(request()->isPost()) {
			$formdata = input('post.info/a');
			if(!checkTel($formdata['linktel'])) {
				return $this->json(['status'=>0, 'msg'=>'请输入正确的手机号']);
			}
			$info = [];
			$info['aid'] = aid;
			$info['mid'] = mid;
			$info['cid'] = implode(',',$formdata['cid']);
			$info['name'] = $formdata['name'];
			$info['linkman'] = $formdata['linkman'];
			$info['linktel'] = $formdata['linktel'];
			$info['tel'] = $formdata['tel'];
			$info['logo'] = $formdata['pic'];
			$info['work'] = $formdata['work'];
			$info['desc'] = $formdata['desc'];
			$cnames = Db::name('expert_category')->Field('id,name')->where('aid',aid)->where('id','in',$formdata['cid'])->column('name','id');
			if ($cnames) {
				$info['cnames'] = implode(',',$cnames);
			} else {
				$info['cnames'] ='';
			}
			$info['status'] = 0;
			$info['createtime'] = time();
			if(!isset($info['feepercent'])) {
				$info['feepercent'] = Db::name('business_sysset')->where('aid',aid)->value('default_rate');
			}
			if($formdata['id']) {
				Db::name('expert')->where('aid',aid)->where('mid',mid)->where('id',$formdata['id'])->update($info);
			} else {
				$bid = Db::name('expert')->insertGetId($info);
				if($bset['deposit'] > 0) {
					//生成保证金订单，跳转支付
					$ordernum = date('ymdHis').aid.rand(1000,9999);
					$money = $bset['deposit'];
					$orderdata = [];
					$orderdata['aid'] = aid;
					$orderdata['mid'] = mid;
					$orderdata['bid'] = $bid;
					$orderdata['createtime']= time();
					$orderdata['money'] = $money;
					$orderdata['ordernum'] = $ordernum;
					$orderid = Db::name('expert_deposit_order')->insertGetId($orderdata);
					$payorderid = \app\models\Payorder::createorder(aid,0,$orderdata['mid'],'expert_deposit',$orderid,$ordernum,'专家认证费',$money);
					return $this->json(['status'=>-3,'msg'=>'提交成功，请支付认证费，等待审核','url'=>'/pagesExt/pay/pay?id='.$payorderid,'orderid'=>$orderid,'payorderid'=>$payorderid]);
				}
			}
			return $this->json(['status'=>1,'msg'=>'提交成功，请等待审核']);
		}
		$info = Db::name('expert')->where('aid',aid)->where('mid',mid)->find();
		if($info && $info['status']==1) {
			return $this->json(['status'=>2,'msg'=>'您已成功入驻']);
		}
		if($info && $info['logo']) {
			$info['pic'] = $info['logo'];
		}
		$glist = Db::name('expert_category')->where('aid',aid)->where('status',1)->order('sort desc,id')->select()->toArray();
		$groupArr = Db::name('expert_category')->Field('id,name')->where('aid',aid)->column('name','id');
		$rdata = [];
		$rdata['glist'] = $glist;
		$rdata['title'] = '申请入驻';
		$rdata['groupArr'] = $groupArr;
		$rdata['bset'] = $bset;
		$rdata['info'] = $info ? $info : [];
		$rdata['gids'] = $info['cid'] ? explode(',',$info['cid']) : [];
		return $this->json($rdata);
	}
	//删除评论
	public function delpinglun() {
		$id = input('param.id/d');
		$pinglun = Db::name('expert_comment_order')->where('aid',aid)->where('id',$id)->find();
		if($pinglun['mid']!=mid) {
			return $this->json(['status'=>0,'msg'=>'无权限操作']);
		}
		Db::name('expert_comment_order')->where('aid',aid)->where('id',$id)->delete();
		return $this->json(['status'=>1,'msg'=>'删除成功','url'=>true]);
	}
	//删除回复
	public function delplreply() {
		$id = input('param.id/d');
		$plreply = Db::name('expert_comment_order')->where('aid',aid)->where('id',$id)->find();
		$detail = Db::name('expert')->where('id',$plreply['sid'])->find();
		if($detail['mid']!=mid) {
			return $this->json(['status'=>0,'msg'=>'无权限操作']);
		}
		Db::name('expert_comment_order')->where('aid',aid)->where('id',$id)->update(['reply_content'=>'']);
		return $this->json(['status'=>1,'msg'=>'删除成功','url'=>true]);
	}
	//点赞
	public function zan() {
		$id = input('post.id/d');
		$detail = Db::name('expert')->where('id',$id)->find();
		$zanlog = Db::name('expert_zanlog')->where('proid',$id)->where('mid',mid)->find();
		if($zanlog) {
			Db::name('expert_zanlog')->where('proid',$id)->where('mid',mid)->delete();
			$type = 0;
			Db::name('expert')->where('id',$id)->dec('zan')->update();
		} else {
			$data = [];
			$data['aid'] = aid;
			$data['proid'] = $id;
			$data['mid'] = mid;
			$data['createtime'] = time();
			Db::name('expert_zanlog')->insert($data);
			$type = 1;
			Db::name('expert')->where('id',$id)->inc('zan')->update();
		}
		$zancount = Db::name('expert')->where('id',$id)->value('zan');
		return $this->json(['status'=>1,'type'=>$type,'zancount'=>$zancount]);
	}
	public function favorite() {
		$pagenum = input('post.pagenum');
		$carr = Db::name('expert_category')->where('aid',aid)->column('name','id');
		if(!$pagenum) $pagenum = 1;
		$pernum = 20;
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['mid','=',mid];
		$datalist = Db::name('expert_zanlog')->field('id,proid,from_unixtime(createtime)createtime')->where($where)->page($pagenum,$pernum)->order('createtime desc')->select()->toArray();
		if(!$datalist) $datalist = [];
		foreach($datalist as $k=>$v) {
			$product = Db::name('expert')->where('id',$v['proid'])->find();
			if(!$product) {
				Db::name('expert_zanlog')->where('id',$v['id'])->delete();
				unset($datalist[$k]);
			} else {
				$cnames = [];
				if($product['cid']) {
					$cids = explode(',',$product['cid']);
					foreach($cids as $cid) {
						$cnames[] = $carr[$cid];
					}
				}
				$datalist[$k]['cnames'] = $cnames?$cnames:[];
				$datalist[$k]['product'] = $product;
			}
		}
		ll($datalist,'$datalist');
		if(request()->isPost()) {
			return $this->json(['status'=>1,'data'=>$datalist]);
		}
		$count = Db::name('expert_zanlog')->where($where)->count();
		$rdata = [];
		$rdata['count'] = $count;
		$rdata['datalist'] = $datalist;
		$rdata['pernum'] = $pernum;
		return $this->json($rdata);
	}
	public function favoritedel() {
		$post = input('post.');
		$zanlog = Db::name('expert_zanlog')->where('aid',aid)->where('mid',mid)->where('id',$post['id'])->find();
		if($zanlog) {
			Db::name('expert_zanlog')->where('aid',aid)->where('mid',mid)->where('id',$post['id'])->delete();
			Db::name('expert')->where('id',$zanlog['proid'])->dec('zan')->update();
		}
		return $this->json(['status'=>1,'msg'=>'已取消','url'=>true]);
	}
	//显示时间
	private function getshowtime($time) {
		if(time() - $time < 60) {
			return '刚刚';
		} elseif(time() - $time < 3600) {
			$minite = ceil((time() - $time)/60);
			return $minite.'分钟前';
		} elseif(date('Ymd')==date('Ymd',$time)) {
			return date('H:i',$time);
		} elseif(time()-$time<86400) {
			return '昨天 '.date('H:i',$time);
		} elseif(date('Y')==date('Y',$time)) {
			return date('m-d H:i',$time);
		} else {
			return date('Y-m-d H:i',$time);
		}
	}
}