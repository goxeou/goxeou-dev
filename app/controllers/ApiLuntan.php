<?php


namespace app\controllers;
use think\facade\Db;
class ApiLuntan extends ApiCommon
{
    public function initialize(){
		parent::initialize();
		$bset = Db::name('luntan_sysset')->where('aid',aid)->find();
		if($bset['status'] == 0){
			die(jsonEncode(['status'=>-3,'url'=>'/pages/index/index']));
		}
	}
		//删除回复
	public function sysset(){
	
		$sysset = Db::name('luntan_sysset')->where('aid',aid)->find();
		return $this->json(['status'=>1,'sysset'=>$sysset]);
	}
	public function index(){
		$sysset = Db::name('luntan_sysset')->where('aid',aid)->find();
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['status','=',1];
		$pernum = 20;
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$datalist = Db::name('luntan')->where($where)->page($pagenum,$pernum)->order('is_top desc,id desc')->select()->toArray();
		if(!$datalist) $datalist = array();
		$clist = Db::name('luntan_category')->where('aid',aid)->select()->toArray();
		$cdata = array();
		foreach($clist as $c){
			$cdata[$c['id']] = $c['name'];
		}
		$readcount = $sysset['readcount'];
		foreach($datalist as $k=>$v){
		    
		    $datalist[$k]['artid'] = $v['id'];
		    
		    
		     Db::name('luntan')->where('id',$v['id'])->inc('readcount',1)->update();
		    
		    
		    
		    $datalist[$k]['cname'] = $cdata[$v['cid']];
			$datalist[$k]['plcount'] = Db::name('luntan_pinglun')->where('sid',$v['id'])->where('status',1)->count();
			//是否点赞
			$zanlog = Db::name('luntan_zanlog')->where('sid',$v['id'])->where('mid',mid)->find();
			if($zanlog){
				$datalist[$k]['iszan'] = 1;
			}else{
				$datalist[$k]['iszan'] = 0;
			}
			$datalist[$k]['showtime'] = $this->getshowtime($v['createtime']);
			if($v['pics']){
				$datalist[$k]['pics'] = explode(',',$v['pics']);
			}
			$datalist[$k]['isshowphone'] = false;
			if(getcustom('luntan_category_phone')){
				$cate = Db::name('luntan_category')->field('isshowphone')->where('aid',aid)->where('id',$v['cid'])->find();
				$datalist[$k]['isshowphone'] = $cate['isshowphone']?true:false;
			}
			$commentlist = Db::name('luntan_pinglun')->where('aid',aid)->where('sid',$v['id'])->where('status',1)->order('id desc')->select()->toArray();
    		if(!$commentlist) $commentlist = [];
    		$datalist[$k]['commentlist'] = $commentlist;
    		
    		$datalist[$k]['storeshowall'] = false;
    		$datalist[$k]['showcomment'] = true;
    		$datalist[$k]['readcount'] = $sysset['readcount']+$v['readcount'];
    		
    		
    		
    		
		}
		if($pagenum!=1){
			return $this->json(['status'=>1,'datalist'=>$datalist]);
		}
		$clist = Db::name('luntan_category')->where('aid',aid)->where('pid',0)->where('status',1)->order('sort desc,id')->select()->toArray();

		$rdata = [];
		$rdata['status'] = 1;
		$rdata['datalist'] = $datalist;
		$rdata['sysset'] = $sysset;
		$rdata['title'] = $sysset['title'];
		$rdata['clist'] = $clist;
		if(getcustom('luntan_call')){
			$rdata['need_call'] = true;
		}
		return $this->json($rdata);
	}

	public function ltlist(){
		$sysset = Db::name('luntan_sysset')->where('aid',aid)->find();
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['status','=',1];
		if(input('param.keyword')){
			$where[] = ['content|nickname','like','%'.input('param.keyword').'%'];
		}
		$title = $sysset['title'];
		$banner = '';
		if(input('param.cid')){
			$where[] = ['cid','=',input('param.cid')];
			$cdata = Db::name('luntan_category')->where('aid',aid)->where('id',input('param.cid'))->find();
			$title = $cdata['name'];
			$banner = $cdata['banner'];
		}
		$pernum = 20;
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$datalist = Db::name('luntan')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
		if(!$datalist) $datalist = array();
		$clist = Db::name('luntan_category')->where('aid',aid)->select()->toArray();
		$cdatas = array();
		foreach($clist as $c){
			$cdatas[$c['id']] = $c['name'];
		}
		foreach($datalist as $k=>$v){
		     $datalist[$k]['cname'] = $cdatas[$v['cid']];
			$datalist[$k]['plcount'] = Db::name('luntan_pinglun')->where('sid',$v['id'])->where('status',1)->count();
			//是否点赞
			$zanlog = Db::name('luntan_zanlog')->where('sid',$v['id'])->where('mid',mid)->find();
			if($zanlog){
				$datalist[$k]['iszan'] = 1;
			}else{
				$datalist[$k]['iszan'] = 0;
			}
			
			$datalist[$k]['artid'] = $v['id'];
			    Db::name('luntan')->where('id',$v['id'])->inc('readcount',1)->update();
		    
		    
			$commentlist = Db::name('luntan_pinglun')->where('aid',aid)->where('sid',$v['id'])->where('status',1)->order('id desc')->select()->toArray();
    		if(!$commentlist) $commentlist = [];
    		$datalist[$k]['commentlist'] = $commentlist;
    		
    		$datalist[$k]['storeshowall'] = false;
    		$datalist[$k]['showcomment'] = true;
			
			
			
			$datalist[$k]['showtime'] = $this->getshowtime($v['createtime']);
			if($v['pics']){
				$datalist[$k]['pics'] = explode(',',$v['pics']);
			}
			$datalist[$k]['isshowphone'] = false;
			if(getcustom('luntan_category_phone')){
				$cate = Db::name('luntan_category')->field('isshowphone')->where('aid',aid)->where('id',$v['cid'])->find();
				$datalist[$k]['isshowphone'] = $cate['isshowphone']?true:false;
			}
						//获取评论
    		$commentlist = Db::name('luntan_pinglun')->where('aid',aid)->where('sid',$v['id'])->where('status',1)->order('id desc')->limit(10)->select()->toArray();
    		if(!$commentlist) $commentlist = [];
    		$datalist[$k]['commentlist'] = $commentlist;
    			$datalist[$k]['readcount'] = $sysset['readcount']+$v['readcount'];
		}
		if($pagenum!=1){
			return $this->json(['status'=>1,'datalist'=>$datalist]);
		}
		$rdata = [];
		$rdata['status'] = 1;
		$rdata['cid'] = input('param.cid');
		$rdata['title'] = $title;
		$rdata['banner'] = $banner;
		$rdata['datalist'] = $datalist;
		$rdata['sysset'] = $sysset;
		return $this->json($rdata);
	}
	
	public function fatie(){
		$this->checklogin();
		$sysset = Db::name('luntan_sysset')->where('aid',aid)->find();
		$sendtj = explode(',',$sysset['sendtj']);
		if(!in_array('-1',$sendtj) && !in_array($this->member['levelid'],$sendtj)){ //不是所有人
			if(in_array('0',$sendtj)){ //关注用户才能领
				if($this->member['subscribe']!=1){
					$appinfo = getappinfo(aid,'mp');
					return $this->fetch('guanzhu',['img'=>$appinfo['qrcode'],'msg'=>'请先关注'.$appinfo['nickname'].'公众号']);
				}
			}else{
				return $this->json(['status'=>0,'msg'=>'您没有发帖权限']);
			}
		}
        if($sysset['sendcheck']==2){
            return $this->json(['status'=>0,'msg'=>'用户不可发帖']);
        }

		if(request()->isPost()){
			$title = input('post.title');
			$content = input('post.content');
			$pics = input('post.pics');
			$video = input('post.video');
			$cid = input('post.cid');
			$data = [];
			$data['aid'] = aid;
			$data['cid'] = $cid;
			if(getcustom('luntan_second_category')){
				//查询他的二级
				$cidlist2 =  Db::name('luntan_category')->where('pid',$cid)->where('aid',aid)->where('status',1)->column('id');
				$cid2 = input('post.cid2')?input('cid2/d'):0;
				if($cidlist2){
					if(!$cid2){
						return $this->json(['status'=>0,'msg'=>'请选择二级分类']);
					}
					if(!in_array($cid2,$cidlist2)){
						return $this->json(['status'=>0,'msg'=>'二级分类不存在或已关闭']);
					}
					if($cid2){
						$data['cid'] = $cid2;
					}
				}
			}
			$data['mid'] = mid;
			$data['content'] = $content;
			$data['pics'] = $pics;
			$data['video'] = $video;
			$data['createtime'] = time();
			$data['headimg'] = $this->member['headimg'];
			$data['nickname'] = $this->member['nickname'];
			if($sysset['sendcheck']==1){//需要审核
				$data['status'] = 0;
				$msg = '提交成功，请等待审核';
			}else{
				$data['status'] = 1;
				$msg = '发布成功';
			}
			if(getcustom('luntan_call')){	}
				$data['mobile'] = input('post.mobile')?input('post.mobile'):'';
		
			if(getcustom('luntan_category_phone')){
				$data['mobile'] = input('post.mobile')?input('post.mobile'):'';
				$data['name'] = input('post.name')?input('post.name'):'';
			}

			if(getcustom('luntan_category_give_coupon')){
				$cate =  Db::name('luntan_category')->where('id',$cid)->where('aid',aid)->where('status',1)->find();
				//查看赠送次数
				$givecount = 0+Db::name('luntan_give_couponlog')->where('cateid',$cid)->where('aid',aid)->where('mid',mid)->count();
				if(time()>$cate['starttime'] && time()<$cate['endtime'] && $cate['coupon_ids']){
					if(($cate['limitnum'] >0 && $givecount<$cate['limitnum']) || !$cate['limitnum']){
						$couponids = explode(',',$cate['coupon_ids']);
						foreach($couponids as $coupon){
							\app\commons\Coupon::send(aid,mid,$coupon,false,0);
						}
						//新增赠送记录
						$zslog = [];
						$zslog['aid'] = aid;
						$zslog['mid'] = mid;
						$zslog['createtime'] = time();
						$zslog['cateid'] = $cid;
						$zslog['couponid'] = $cate['coupon_ids'];
						Db::name('luntan_give_couponlog')->insert($zslog);
					}
				}
            }

			$id = Db::name('luntan')->insertGetId($data);
			if(getcustom('ext_give_score')){
			    if($data['status']==1){
                    \app\models\Score::extGiveScore(aid,$this->mid,'luntan',$id,'add');
                }
            }
		


			return $this->json(['status'=>1,'msg'=>$msg]);
		}
		$clist = Db::name('luntan_category')->where('aid',aid)->where('pid',0)->where('status',1)->order('sort desc,id')->select()->toArray();
		if(getcustom('luntan_second_category')){
			$display_type = input('display_type');
			if($display_type>=0){
				$clist = Db::name('luntan_category')->where('aid',aid)->where('pid',0)->where('status',1)->where('display_type',$display_type)->order('sort desc,id')->select()->toArray();
			}
		}
		$iscatephone = false;
		if(getcustom('luntan_category_phone')){
			$iscatephone = true;
		}	

		$rdata = [];
		$rdata['clist'] = $clist;
		$rdata['mobile'] = $this->member['tel'];
		
		if(!$rdata['mobile']){
			$lastorder = Db::name('luntan')->where('aid',aid)->where('mid',mid)->where('mobile','<>','')->find();
			if($lastorder){
				$rdata['mobile'] = $lastorder['mobile'];
			}
		}
		
		
		if(getcustom('luntan_call')){	}
			$rdata['need_call'] = true;
	
		if(getcustom('luntan_second_category')){
			$rdata['cate2'] = true;
			$rdata['cateArr2'] = [];
		}
		$rdata['iscatephone'] = $iscatephone;
		return $this->json($rdata);
	}
	public function detail(){
		$id = input('param.id/d');
		$detail = Db::name('luntan')->where('id',$id)->where('status',1)->find();
		if(!$detail) return $this->json(['status'=>0,'msg'=>'帖子已删除']);
		$sysset = Db::name('luntan_sysset')->where('aid',aid)->find();
      
		
		$readcount = $detail['readcount'] + rand($sysset['readmin'],$sysset['readmax']);
		Db::name('luntan')->where('id',input('param.id/d'))->where('aid',aid)->update(['readcount'=>$readcount]);
		
		
		
		$detail['readcount'] = $readcount;
		$detail['readcount'] = $sysset['readcount']+$detail['readcount'];

		
		
		
		$detail['showtime'] = $this->getshowtime($detail['createtime']);
		if($detail['pics']){
			$detail['pics'] = explode(',',$detail['pics']);
		}
	

		$detail['isshowphone'] = false;
		if(getcustom('luntan_category_phone')){
			$cate = Db::name('luntan_category')->field('isshowphone')->where('aid',aid)->where('id',$detail['cid'])->find();
			$detail['isshowphone'] = $cate['isshowphone']?true:false;
		}
		//评论
		$pernum = 20;
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$datalist = Db::name('luntan_pinglun')->where('sid',$id)->where('status',1)->page($pagenum,$pernum)->order('createtime desc')->select()->toArray();
		if(!$datalist) $datalist = array();
		foreach($datalist as $k=>$v){
			$rs = Db::name('luntan_pzanlog')->where('pid',$v['id'])->where('mid',mid)->find();
			if($rs){
				$v['iszan'] = 1;
			}else{
				$v['iszan'] = 0;
			}
			//回复
			$replylist = Db::name('luntan_pinglun_reply')->field('id,mid,nickname,headimg,content,createtime')->where('pid',$v['id'])->where('status',1)->order('createtime')->select()->toArray();
			foreach($replylist as $k2=>$v2){
				$v2['createtime'] = $this->getshowtime($v2['createtime']);
				$v2['content'] = getshowcontent($v2['content']);
				$replylist[$k2] = $v2;
			}
			$v['replylist'] = $replylist;
			$v['content'] = nl2br(getshowcontent($v['content']));
			$v['createtime'] = $this->getshowtime($v['createtime']);
			$datalist[$k] = $v;
		}
		$plcount = Db::name('luntan_pinglun')->where('sid',$id)->where('status',1)->count();
		//是否点赞
		$zanlog = Db::name('luntan_zanlog')->where('sid',$detail['id'])->where('mid',mid)->find();
		if($zanlog){
			$iszan = 1;
		}else{
			$iszan = 0;
		}

		$rdata = [];
		$rdata['mid'] = mid;
		$rdata['datalist'] = $datalist;
		$rdata['plcount'] = $plcount;
		$rdata['iszan'] = $iszan;
		$rdata['status'] = 1;
		$rdata['detail'] = $detail;
		if(getcustom('luntan_call')){
			$rdata['need_call'] = true;
		}
		return $this->json($rdata);
	}
	//点赞
	public function zan(){
		$this->checklogin();
		$id = input('post.id/d');
		$detail = Db::name('luntan')->where('id',$id)->find();
		$zanlog = Db::name('luntan_zanlog')->where('sid',$id)->where('mid',mid)->find();
		if($zanlog){
			Db::name('luntan_zanlog')->where('sid',$id)->where('mid',mid)->delete();
			$type = 0;
			Db::name('luntan')->where('id',$id)->dec('zan')->update();
		}else{
			$data = [];
			$data['aid'] = aid;
			$data['sid'] = $id;
			$data['mid'] = mid;
			$data['createtime'] = time();
			Db::name('luntan_zanlog')->insert($data);
			$type = 1;
			Db::name('luntan')->where('id',$id)->inc('zan')->update();
		}
		$zancount = Db::name('luntan')->where('id',$id)->value('zan');
		return $this->json(['status'=>1,'type'=>$type,'zancount'=>$zancount]);
	}
	//评论
	public function subpinglun(){
		$this->checklogin();
		$id = input('param.id/d');
		$type = input('param.type/d');
		$hfid = input('param.hfid/d');
		$content = trim(input('param.content'));
		if(!$id){
			return $this->json(['status'=>0,'msg'=>'参数错误']);
		}
		$detail = Db::name('luntan')->where('id',$id)->where('status',1)->find();
		//if($detail['canpl']==0) return $this->json(['status'=>0,'msg'=>'评论功能未开启']);
		//if($hfid && $detail['canplrp']==0) return $this->json(['status'=>0,'msg'=>'评论回复功能未开启']);

		if($content==''){
			return $this->json(['status'=>1,'msg'=>'请输入评论内容']);
		}
		$sysset = Db::name('luntan_sysset')->where('aid',aid)->find();
		if($type==0){
			$data = [];
			$data['aid'] = aid;
			$data['mid'] = mid;
			$data['sid'] = $id;
			$data['headimg'] = $this->member['headimg'];
			$data['nickname'] = $this->member['nickname'];
			$data['content'] = $content;
			$data['createtime'] = time();
			if($sysset['pingluncheck']==1){
				$data['status'] = 0;
				$msg = '提交成功，请等待审核';
			}else{
				$data['status'] = 1;
				$msg = '发表评论成功';
			}
			Db::name('luntan_pinglun')->insert($data);
            if(getcustom('ext_give_score')){
                if($data['status']==1){
                    \app\models\Score::extGiveScore(aid,$this->mid,'luntan',$id,'pinglun');
                }
            }
		}else{
			$data = [];
			$data['aid'] = aid;
			$data['mid'] = mid;
			$data['sid'] = $id;
			$data['pid'] = $hfid;
			$data['headimg'] = $this->member['headimg'];
			$data['nickname'] = $this->member['nickname'];
			$data['content'] = $content;
			$data['createtime'] = time();
			if($sysset['pingluncheck']==1){
				$data['status'] = 0;
				$msg = '提交成功，请等待审核';
			}else{
				$data['status'] = 1;
				$msg = '发表评论成功';
			}
			Db::name('luntan_pinglun_reply')->insert($data);
		}
		$commentlist = Db::name('luntan_pinglun')->where('aid',aid)->where('sid',$id)->where('status',1)->order('id desc')->select()->toArray();
		if(!$commentlist) $commentlist = [];
		
		return $this->json(['status'=>1,'commentlist'=>$commentlist,'msg'=>$msg,'url'=>true]);
	}
	//评论点赞
	public function pzan(){
		$this->checklogin();
		$id = input('post.id/d');
		$pinglun = Db::name('luntan_pinglun')->where('id',$id)->find();
		$zanlog = Db::name('luntan_pzanlog')->where('pid',$id)->where('mid',mid)->find();
		if($zanlog){
			Db::name('luntan_pzanlog')->where('pid',$id)->where('mid',mid)->delete();
			$type = 0;
			Db::name('luntan_pinglun')->where('id',$id)->dec('zan')->update();
		}else{
			$data = [];
			$data['aid'] = aid;
			$data['pid'] = $id;
			$data['mid'] = mid;
			$data['createtime'] = time();
			Db::name('luntan_pzanlog')->insert($data);
			$type = 1;
			Db::name('luntan_pinglun')->where('id',$id)->inc('zan')->update();
		}
		$zancount = Db::name('luntan_pinglun')->where('id',$id)->value('zan');
		return $this->json(['status'=>1,'type'=>$type,'zancount'=>$zancount]);
	}
	//删除
	public function deltie(){
		$this->checklogin();
		$id = input('param.id/d');
		$detail = Db::name('luntan')->where('aid',aid)->where('id',$id)->find();
		if($detail['mid']!=mid){
			return $this->json(['status'=>0,'msg'=>'无权限操作']);
		}
		Db::name('luntan')->where('aid',aid)->where('id',$id)->delete();
		Db::name('luntan_pinglun')->where('aid',aid)->where('sid',$id)->delete();
		Db::name('luntan_pinglun_reply')->where('aid',aid)->where('sid',$id)->delete();
		return $this->json(['status'=>1,'msg'=>'删除成功']);
	}
	//删除评论
	public function delpinglun(){
		$this->checklogin();
		$id = input('param.id/d');
		$pinglun = Db::name('luntan_pinglun')->where('aid',aid)->where('id',$id)->find();
		if($pinglun['mid']!=mid){
			return $this->json(['status'=>0,'msg'=>'无权限操作']);
		}
		Db::name('luntan_pinglun')->where('aid',aid)->where('id',$id)->delete();
		Db::name('luntan_pinglun_reply')->where('aid',aid)->where('pid',$id)->delete();
		return $this->json(['status'=>1,'msg'=>'删除成功','url'=>true]);
	}
	//删除回复
	public function delplreply(){
		$this->checklogin();
		$id = input('param.id/d');
		$plreply = Db::name('luntan_pinglun_reply')->where('aid',aid)->where('id',$id)->find();
		if($plreply['mid']!=mid){
			return $this->json(['status'=>0,'msg'=>'无权限操作']);
		}
		Db::name('luntan_pinglun_reply')->where('aid',aid)->where('id',$id)->delete();
		return $this->json(['status'=>1,'msg'=>'删除成功','url'=>true]);
	}

	
	//显示时间
	private function getshowtime($time){
		if(time() - $time < 60){
			return '刚刚';
		}elseif(time() - $time < 3600){
			$minite = ceil((time() - $time)/60);
			return $minite.'分钟前';
		}elseif(date('Ymd')==date('Ymd',$time)){
			return date('H:i',$time);
		}elseif(time()-$time<86400){
			return '昨天 '.date('H:i',$time);
		}elseif(date('Y')==date('Y',$time)){
			return date('m-d H:i',$time);
		}else{
			return date('Y-m-d H:i',$time);
		}
	}

	//个人发帖记录
	public function fatielog(){
		if(request()->isPost()){
			$pagenum = input('post.pagenum');
			if(!$pagenum) $pagenum = 1;
			$pernum = 20;
			$where = [];
			$where[] = ['aid','=',aid];
			$where[] = ['mid','=',mid];
			$where[] = ['status','=',1];
			$datalist = Db::name('luntan')
				->where($where)
				->field('id,content,pics,createtime')
				->page($pagenum,$pernum)
				->order('is_top desc,id desc')
				->select()
				->toArray();
			if(!$datalist){
				$datalist = [];
			}else{
				foreach($datalist as &$v){
					$v['pic'] = '';
					if($v['pics']){
						$v['pics'] = explode(',',$v['pics']);
						$v['pic']  = $v['pics'][0];
					}
					$v['createtime'] = date("Y-m-d H:i:s",$v['createtime']);
				}
				unset($v);
			}
			return $this->json(['status'=>1,'data'=>$datalist] );
		}
	}

	//个人关注记录
	public function focuslog(){
		if(request()->isPost()){
			$pagenum = input('post.pagenum');
			if(!$pagenum) $pagenum = 1;
			$pernum = 20;
			$datalist = Db::name('luntan_zanlog')
				->alias('lz')
				->join('luntan l','l.id = lz.sid')
				->where('lz.mid',mid)
				->where('lz.aid',aid)
				->page($pagenum,$pernum)
				->order('lz.id desc')
				->field('lz.id,lz.sid,l.content,l.pics,lz.createtime')
				->select()
				->toArray();
			if(!$datalist){
				$datalist = [];
			}else{
				foreach($datalist as &$v){
					$v['pic'] = '';
					if($v['pics']){
						$v['pics'] = explode(',',$v['pics']);
						$v['pic']  = $v['pics'][0];
					}
					$v['createtime'] = date("Y-m-d H:i:s",$v['createtime']);
				}
				unset($v);
			}
			return $this->json(['status'=>1,'data'=>$datalist] );
		}
	}

	public function class(){
		if(getcustom('luntan_second_category')){
			if(request()->isPost()){
				
				$pagenum = input('post.pagenum');
				if(!$pagenum) $pagenum = 1;
				$pernum = 20;
				$clist = Db::name('luntan_category')
					->where('aid',aid)
					->where('pid',0)
					->where('display_type',0)
					->where('status',1)
					->page($pagenum,$pernum)
					->order('sort desc,id')
					->select()
					->toArray();
				if(!$clist){
					$clist = [];
				}
				$sysset = Db::name('luntan_sysset')->where('aid',aid)->field('id,title')->find();
				$title = $sysset['title'];
				return $this->json(['status'=>1,'data'=>$clist,'title'=>$title]);
			}
		}
	}
	public function class2(){
		if(getcustom('luntan_second_category')){
			if(request()->isPost()){
				
				$pagenum = input('post.pagenum');
				if(!$pagenum) $pagenum = 1;
				$pernum = 20;
				$clist = Db::name('luntan_category')
					->where('aid',aid)
					->where('pid',0)
					->where('display_type',1)
					->where('status',1)
					->page($pagenum,$pernum)
					->order('sort desc,id')
					->select()
					->toArray();
				if(!$clist){
					$clist = [];
				}else{
					foreach($clist as &$cv){
						//显示他分类信息数量
						$cids = [$cv['id']];
						$cids2 = Db::name('luntan_category')
							->where('pid',$cv['id'])
							->where('aid',aid)
							->where('status',1)
							->order('sort desc,id')
							->column('id');
						if($cids2){
							$cids = array_merge($cids,$cids2);
						}
						$childlist = Db::name('luntan')->where('cid','in',$cids)->limit($cv['child_num'])->order('id desc')->select()->toArray();
						if($childlist){
							foreach($childlist as &$cdv){
								$cdv['pic'] = '';
								if($cdv['pics']){
									$picarr = explode(',',$cdv['pics']);
									if($picarr){
										$cdv['pic'] = $picarr[0];
									}
								}
							}
							unset($cdv);
							
						}
						$cv['childlist'] = $childlist;
					}
					unset($cv);
				}

				$sysset = Db::name('luntan_sysset')->where('aid',aid)->field('id,title')->find();
				$title = $sysset['title'];
				return $this->json(['status'=>1,'data'=>$clist,'title'=>$title]);
			}
		}
	}
	public function list(){
		if(getcustom('luntan_second_category')){
			if(request()->isPost()){
				$pagenum = input('post.pagenum');
				if(!$pagenum) $pagenum = 1;
				$pernum = 20;

				$pid = input('post.pid')?input('pid/d'):0;
				$display_type = input('post.display_type')?input('display_type/d'):0;
				if($pid){
					$clist = [['id'=>0,'name'=>'全部']];
					//二级分类
					$clist_arr = Db::name('luntan_category')
						->where('aid',aid)
						->where('pid',$pid)
						->where('status',1)
						->field('id,name')
						->page($pagenum,$pernum)
						->order('sort desc,id')
						->select()
						->toArray();
					if($clist_arr){
						$clist = array_merge($clist ,$clist_arr);
					}
				}else{
					$clist = [];
				}

				$sysset = Db::name('luntan_sysset')->where('aid',aid)->find();
				$title = $sysset['title'];
				$where = [];
				$where[] = ['aid','=',aid];
				$where[] = ['status','=',1];

				if($pid){//区分分类

					if(input('param.keyword')){
						$where[] = ['content|nickname','like','%'.input('param.keyword').'%'];
					}
					$cid = input('param.cid')?input('cid/d'):0;
					if(input('param.cid')){
						$cid = input('param.cid');
						//验证二级分类是否正确
						$count = Db::name('luntan_category')->where('id',$cid)->where('pid',$pid)->where('aid',aid)->where('status',1)->count();
						if(empty($count)){
							return $this->json(['status'=>0,'msg'=>'分类不存在']);
						}
						$where[] = ['cid','=',$cid];
						$cdata  = Db::name('luntan_category')->where('aid',aid)->where('id',$cid)->field('id,name')->find();
						$title  = $cdata['name'];
					}else{
						$cids = [$pid];
						$cids2  = Db::name('luntan_category')->where('pid',$pid)->where('aid',aid)->where('status',1)->order('sort desc,id')->column('id');
						if($cids2){
							$cids = array_merge($cids,$cids2);
						}
						$where[] = ['cid','in',$cids];
					}

					$datalist = Db::name('luntan')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
					if(!$datalist) $datalist = array();
					foreach($datalist as $k=>$v){
						$datalist[$k]['plcount'] = Db::name('luntan_pinglun')->where('sid',$v['id'])->where('status',1)->count();
						//是否点赞
						$zanlog = Db::name('luntan_zanlog')->where('sid',$v['id'])->where('mid',mid)->find();
						if($zanlog){
							$datalist[$k]['iszan'] = 1;
						}else{
							$datalist[$k]['iszan'] = 0;
						}
						$datalist[$k]['showtime'] = $this->getshowtime($v['createtime']);
						if($v['pics']){
							$datalist[$k]['pics'] = explode(',',$v['pics']);
						}
					}
					if($pagenum!=1){
						return $this->json(['status'=>1,'datalist'=>$datalist]);
					}
				}else{//不区分分类 需要有关键字搜索
					
					if(input('param.keyword')){
						$where[] = ['content|nickname','like','%'.input('param.keyword').'%'];

						$cids  = Db::name('luntan_category')->where('display_type',$display_type)->where('aid',aid)->where('status',1)->column('id');
						if($cids){
							$cids2  = Db::name('luntan_category')->where('pid','in',$cids)->where('aid',aid)->where('status',1)->order('sort desc,id')->column('id');
							if($cids2){
								$cids = array_merge($cids,$cids2);
							}
						}

						if($cids){
							$where[] = ['cid','in',$cids];
						}else{
							$where[] = ['id','=',0];
						}
						
						$datalist = Db::name('luntan')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
						if(!$datalist) $datalist = array();
						foreach($datalist as $k=>$v){
							$datalist[$k]['plcount'] = Db::name('luntan_pinglun')->where('sid',$v['id'])->where('status',1)->count();
							//是否点赞
							$zanlog = Db::name('luntan_zanlog')->where('sid',$v['id'])->where('mid',mid)->find();
							if($zanlog){
								$datalist[$k]['iszan'] = 1;
							}else{
								$datalist[$k]['iszan'] = 0;
							}
							$datalist[$k]['showtime'] = $this->getshowtime($v['createtime']);
							if($v['pics']){
								$datalist[$k]['pics'] = explode(',',$v['pics']);
							}
						}
						if($pagenum!=1){
							return $this->json(['status'=>1,'datalist'=>$datalist]);
						}
					}
				}

				$rdata = [];
				$rdata['clist']  = $clist;
				$rdata['status'] = 1;
				$rdata['cid'] = input('param.cid');
				$rdata['title'] = $title;
				$rdata['banner'] = $banner;
				$rdata['datalist'] = $datalist;
				$rdata['sysset'] = $sysset;
				return $this->json($rdata);
			}
		}
	}

	public function getCate2(){
		if(getcustom('luntan_second_category')){
			if(request()->isPost()){
				$this->checklogin();
				$sysset = Db::name('luntan_sysset')->where('aid',aid)->find();
				$sendtj = explode(',',$sysset['sendtj']);
				if(!in_array('-1',$sendtj) && !in_array($this->member['levelid'],$sendtj)){ //不是所有人
					if(in_array('0',$sendtj)){ //关注用户才能领
						if($this->member['subscribe']!=1){
							$appinfo = getappinfo(aid,'mp');
							return $this->fetch('guanzhu',['img'=>$appinfo['qrcode'],'msg'=>'请先关注'.$appinfo['nickname'].'公众号']);
						}
					}else{
						return $this->json(['status'=>0,'msg'=>'您没有发帖权限']);
					}
				}
				$pid = input('post.pid')?input('pid/d'):0;
				if(!$pid){
					return $this->json(['status'=>0,'msg'=>'请先选择一级分类']);
				}
				$clist = Db::name('luntan_category')->where('pid',$pid)->where('aid',aid)->where('status',1)->order('sort desc,id')->select()->toArray();

				return $this->json(['status'=>1,'data'=>$clist]);
			}
		}
	}
	function removeSpecialChars($str) {
        $pattern = '/[^\p{L}\p{N}\s]/u'; 
        $replacement = ''; // 替换为空字符串
        $result = preg_replace($pattern, $replacement, $str);
        return $result;
    }
    
    
    
    
    
	//商品海报
	public function getposter(){
		$this->checklogin();
		$post = input('post.');
		$platform = platform;
		$page = '/activity/luntan/detail';
		$scene = 'id_'.$post['id'].'-pid_'.$this->member['id'];
		$posterset = Db::name('admin_set_poster')->where('aid',aid)->where('type','luntan')->where('platform',$platform)->order('id')->find();
		$posterdata = Db::name('member_poster')->where('aid',aid)->where('mid',mid)->where('scene',$scene)->where('type','luntan')->where('posterid',$posterset['id'])->find();
		if(true || !$posterdata){
			$product = Db::name('luntan')->where('id',$post['id'])->find();
			$sysset = Db::name('admin_set')->where('aid',aid)->find();
			$content = getshowcontent($product['content']);
			$content = self::removeSpecialChars($product['content']);
			if ($content) {
			   	$content =	mb_substr($content,0,72);
			}
		
			if($product['pics']){
				$pics = explode(',',$product['pics']);
			}else {
			    $pics = [];
			}

        	$cname = Db::name('luntan_category')->where('aid',aid)->where('id',$product['cid'])->value('name');
    		
    		$posterdata = json_decode($posterset['content'],true);
    		$poster_data = $posterdata['poster_data'];
    
    		
			$textReplaceArr = [
				'[头像]'=>$this->member['headimg'],
				'[昵称]'=>$this->member['nickname'],
				'[姓名]'=>$this->member['realname'],
				'[手机号]'=>$this->member['mobile'],
				'[商城名称]'=>$sysset['name'],
				'[帖子分类]'=>$cname,
				'[帖子内容]'=>$content,
				'[商品图片]'=>$product['pic'],
				
				'[pro_img1]'=>$pics[0],
				'[pro_img2]'=>$pics[1],
				'[pro_img3]'=>$pics[2],
				
				
			];

			$poster = $this->_getposter(aid,$product['bid'],$platform,$posterset['content'],$page,$scene,$textReplaceArr);
			$posterdata = [];
			$posterdata['aid'] = aid;
			$posterdata['mid'] = $this->member['id'];
			$posterdata['scene'] = $scene;
			$posterdata['page'] = $page;
			$posterdata['type'] = 'luntan';
			$posterdata['poster'] = $poster;
			$posterdata['createtime'] = time();
			Db::name('member_poster')->insert($posterdata);
		}
		return $this->json(['status'=>1,'poster'=>$posterdata['poster']]);
	}
}