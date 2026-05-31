<?php


namespace app\controllers;
use think\facade\Db;

class ApiShortvideo extends ApiCommon
{
	//短视频首页
	public function index(){
		$param = input('');
		$where = [];
		$where[] = ['aid','=',aid];
		$sysset = Db::name('shortvideo_sysset')->where('aid',aid)->find();
		if(input('param.bid')){
			$where[] = ['bid','=',input('param.bid')];
		}else{
			if($sysset['show_business_video'] == 0){
				$where[] = ['bid','=',0];
			}

		}
		$where[]=['status','=',1];
		if($param['cid'] && !empty($param['cid']) && $param['cid'] != 0){
			$where[] = ['cid','=',$param['cid']];
		}
		if(input('param.keyword')){
			$where[] = ['name|description','like','%'.input('param.keyword').'%'];
		}
		$pagenum = $param['pagenum'] ? $param['pagenum'] : 1;
		$datalist = Db::name('shortvideo')->where($where)->order('sort desc,zan_num desc,createtime desc')->page($pagenum,10)->select()->toArray();
		foreach($datalist as $k=>$v){
			if($v['view_num'] > 10000){
				$datalist[$k]['view_num'] = round($v['view_num'] / 10000,1).'W';
			}
			if($v['zan_num'] > 10000){
				$datalist[$k]['zan_num'] = round($v['zan_num'] / 10000,1).'W';
			}
            if(getcustom('video_qq_url')){
                $datalist[$k]['url'] = \app\customs\VideoQQ::getMp4Url($v['url']);
            }
			if($v['bid']!=0){
				$binfo = Db::name('business')->where('aid',aid)->where('id',$v['bid'])->field('id,name,logo')->find();
			} else {
				if($v['mid'] > 0){
					$binfo = Db::name('member')->where('aid',aid)->where('id',$v['mid'])->field('nickname name,headimg logo')->find();
				}else{
					$binfo = Db::name('admin_set')->where('aid',aid)->field('name,logo')->find();
				}
            }
			if(!$binfo) $binfo = [];
			$datalist[$k]['binfo'] = $binfo;

			//if(!empty($v['productids'])){
			//	$shop = Db::name('shop_product')->field('id,pic,name,sales,sell_price')->where('aid',aid)->where('id',explode(',',$v['productids'])[0])->find();
			//	$datalist[$k]['proid'] = $shop['id'];
			//	$datalist[$k]['shoppic'] = $shop['pic'];
			//	$datalist[$k]['shopname'] = $shop['name'];
			//	$datalist[$k]['shopsales'] = $shop['sales'];
			//	$datalist[$k]['shopsell_price'] = $shop['sell_price'];
			//}
		}
		if($pagenum == 1){
			$where = [];
			$where[] = ['aid','=',aid];
			$where[] = ['status','=',1];
			if(input('param.bid')){
				$where[] = ['bid','=',input('param.bid')];
			}else{
				$where[] = ['bid','=',0];
			}
			$clist = Db::name('shortvideo_category')->where($where)->order('sort desc,id')->select()->toArray();

		}else{
			$clist = [];
		}

		return $this->json(['status'=>1,'datalist'=>$datalist,'clist'=>$clist,'sysset'=>$sysset,'msg'=>'获取成功']);
	}
	//短视频详情
	public function detail(){
		$firstvideo = Db::name('shortvideo')->where('aid',aid)->where('id',input('param.id'))->find();
		if(!$firstvideo || $firstvideo['status'] != 1){
			return $this->json(['status'=>-4,'msg'=>'视频不存在']);
		}
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['status','=',1];
		$sysset = Db::name('shortvideo_sysset')->where('aid',aid)->find();
		if($firstvideo['bid']>0){
			$where[] = ['bid','=',$firstvideo['bid']];
		}else{
			if($sysset['show_business_video'] == 0){
				$where[] = ['bid','=',$firstvideo['bid']];
			}
		}
		//$where[] = ['bid','=',$firstvideo['bid']];
		if(input('param.cid') && input('param.cid')!='all'){
			$where[] = ['cid','=',input('param.cid')];
		}
		$allvideoList = Db::name('shortvideo')->field('*,url as src')->where($where)->order('sort desc,id desc')->select()->toArray();
		$beforeList = [];
		$afterList = [];
		$isin = 0;
		foreach($allvideoList as $v){
			if($v['id']==$firstvideo['id']){
				$isin = 1;
			}
			$v['award_show'] = 0;
			if(getcustom('video_qq_url')){
                $v['url'] = \app\customs\VideoQQ::getMp4Url($v['url']);
                $v['src'] = $v['url'];
            }
			if($isin==1){
				$afterList[] = $v;
			}else{
				$beforeList[] = $v;
			}
		}
		$videoList = array_merge($afterList,$beforeList);

		//我赞过的视频
		$zanArr = Db::name('shortvideo_zanlog')->where('aid',aid)->where('bid',$firstvideo['bid'])->where('mid',mid)->column('vid');
		//评论数
		$commentnumList = Db::name('shortvideo_comment')->field('vid,count(1) c')->where('aid',aid)->where('bid',$firstvideo['bid'])->where('status',1)->group('vid')->select()->toArray();

		$commentnumArr = [];
		foreach($commentnumList as $k=>$v){
			$commentnumArr[$v['vid']] = $v['c'];
		}


		if($firstvideo['bid']!=0){
			$binfo = Db::name('business')->where('aid',aid)->where('id',$firstvideo['bid'])->field('id,name,logo,latitude,longitude,name,address')->find();
			if($binfo){
				$binfo['bid'] = $binfo['id'];
			}
		} else {
			if($firstvideo['mid'] > 0){
				$binfo = Db::name('member')->where('aid',aid)->where('id',$firstvideo['mid'])->field('nickname name,headimg logo')->find();
			}else{
				$binfo = Db::name('admin_set')->where('aid',aid)->field('name,logo')->find();
			}
			if($binfo){
				$binfo['bid'] = 0;
			}
		}
		foreach($videoList as $k=>$v){
			if(in_array($v['id'],$zanArr)){
				$videoList[$k]['iszan'] = true;
			}else{
				$videoList[$k]['iszan'] = false;
			}
			if($v['productids']){
				$proids = explode(',',$v['productids']);
				$videoList[$k]['pronum'] = count($proids);
				$product = Db::name('shop_product')->field('id,pic,name,sales,sell_price,lvprice_data')->where('id',$proids[0])->find();
				$product = $this->formatproduct($product);
				$videoList[$k]['proid']    = $product['id'];
				$videoList[$k]['proname']  = $product['name'];
				$videoList[$k]['propic']   = $product['pic'];
				$videoList[$k]['prosales'] = $product['sales'];
				$videoList[$k]['prosell_price'] = $product['sell_price'];
				$videoList[$k]['protype']       = 'shop';
			}else if($v['gbid']){
				}else{
				$videoList[$k]['pronum'] = 0;
			}
			//评论数
			$videoList[$k]['commentnum'] = $commentnumArr[$v['id']] ? $commentnumArr[$v['id']] : 0;
			$videoList[$k]['logo'] = $binfo['logo'];
		}

		Db::name('shortvideo')->where('aid',aid)->where('id',$firstvideo['id'])->inc('view_num')->update();

		$rdata = [];
		$rdata['status'] = 1;
		$rdata['videoList'] = $videoList;
		$rdata['binfo'] = $binfo;

		return $this->json($rdata);
	}
	//增加阅读数
	public function updateviewnum(){
		$id = input('param.id')?input('param.id/d'):0;
		Db::name('shortvideo')->where('aid',aid)->where('id',$id)->inc('view_num')->update();
		return json(['status'=>1]);
	}
	//获取评论数据
	public function getcommentlist(){
		$id = input('param.id/d');
		$pernum = 20;
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$datalist = Db::name('shortvideo_comment')->where('vid',$id)->where('status',1)->page($pagenum,$pernum)->order('createtime desc')->select()->toArray();
		if(!$datalist) $datalist = array();
		foreach($datalist as $k=>$v){
			$rs = Db::name('shortvideo_comment_zanlog')->where('pid',$v['id'])->where('mid',mid)->find();
			if($rs){
				$v['iszan'] = true;
			}else{
				$v['iszan'] = false;
			}
			//回复
			$replylist = Db::name('shortvideo_comment_reply')->field('nickname,headimg,content,createtime')->where('pid',$v['id'])->where('status',1)->order('createtime')->select()->toArray();
			foreach($replylist as $k2=>$v2){
				$v2['createtime'] = getshowtime($v2['createtime']);
				$v2['content'] = $this->getshowcontent($v2['content']);
				$replylist[$k2] = $v2;
			}
			$v['replylist'] = $replylist;
			$v['content'] = $this->getshowcontent($v['content']);
			$v['createtime'] = getshowtime($v['createtime']);
			$datalist[$k] = $v;
		}
		return $this->json(['status'=>1,'data'=>$datalist]);
	}
	//点赞
	public function zan(){
		$this->checklogin();
		if(!mid){
			return $this->json(['status'=>0,'msg'=>'请先登录']);
		}
		$id = input('post.id/d');
		$detail = Db::name('shortvideo')->where('id',$id)->find();
		$zanlog = Db::name('shortvideo_zanlog')->where('vid',$id)->where('mid',mid)->find();
		if($zanlog){
			Db::name('shortvideo_zanlog')->where('vid',$id)->where('mid',mid)->delete();
			$iszan = false;
			Db::name('shortvideo')->where('id',$id)->dec('zan_num')->update();
		}else{
			$data = [];
			$data['aid'] = aid;
			$data['bid'] = $detail['bid'];
			$data['vid'] = $id;
			$data['mid'] = mid;
			$data['createtime'] = time();
			Db::name('shortvideo_zanlog')->insert($data);
			$iszan = true;
			Db::name('shortvideo')->where('id',$id)->inc('zan_num')->update();
		}
		$zancount = Db::name('shortvideo')->where('id',$id)->value('zan_num');
		return $this->json(['status'=>1,'iszan'=>$iszan,'zan_num'=>$zancount]);
	}
	//评论
	public function subpinglun(){
		$this->checklogin();
		$id = input('param.id/d');
		$hfid = input('param.hfid/d');
		$content = trim(input('param.content'));
		if(!$id){
			return $this->json(['status'=>0,'msg'=>'参数错误']);
		}
		if(!mid){
			return $this->json(['status'=>0,'msg'=>'请先登录']);
		}
		$detail = Db::name('shortvideo')->where('id',$id)->where('status',1)->find();
		if($detail['comment']==0) return $this->json(['status'=>0,'msg'=>'评论功能未开启']);
		if($content==''){
			return $this->json(['status'=>0,'msg'=>'请输入评论内容']);
		}
		if($hfid==0){
			$data = [];
			$data['aid'] = aid;
			$data['mid'] = mid;
			$data['bid'] = $detail['bid'];
			$data['vid'] = $id;
			$data['headimg'] = $this->member['headimg'];
			$data['nickname'] = $this->member['nickname'];
			$data['content'] = $content;
			$data['createtime'] = time();
			if($detail['comment_check']==1){
				$data['status'] = 0;
				$msg = '提交成功，请等待审核';
			}else{
				$data['status'] = 1;
				$msg = '发表评论成功';
			}
			Db::name('shortvideo_comment')->insert($data);
		}else{
			$data = [];
			$data['aid'] = aid;
			$data['mid'] = mid;
			$data['bid'] = $detail['bid'];
			$data['vid'] = $id;
			$data['pid'] = $hfid;
			$data['headimg'] = $this->member['headimg'];
			$data['nickname'] = $this->member['nickname'];
			$data['content'] = $content;
			$data['createtime'] = time();
			if($detail['pinglun_check']==1){
				$data['status'] = 0;
				$msg = '提交成功，请等待审核';
			}else{
				$data['status'] = 1;
				$msg = '发表评论成功';
			}
			Db::name('shortvideo_comment_reply')->insert($data);
		}
		$commentnum = Db::name('shortvideo_comment')->where('vid',$id)->where('status',1)->count();
		return $this->json(['status'=>1,'msg'=>$msg,'commentnum'=>$commentnum]);
	}
	//评论点赞
	public function pzan(){
		$this->checklogin();
		if(!mid){
			return $this->json(['status'=>0,'msg'=>'请先登录']);
		}
		$id = input('post.id/d');
		$pinglun = Db::name('shortvideo_comment')->where('id',$id)->find();
		$zanlog = Db::name('shortvideo_comment_zanlog')->where('pid',$id)->where('mid',mid)->find();
		if($zanlog){
			Db::name('shortvideo_comment_zanlog')->where('pid',$id)->where('mid',mid)->delete();
			$type = 0;
			Db::name('shortvideo_comment')->where('id',$id)->dec('zan')->update();
		}else{
			$data = [];
			$data['aid'] = aid;
			$data['bid'] = $pinglun['bid'];
			$data['pid'] = $id;
			$data['mid'] = mid;
			$data['createtime'] = time();
			Db::name('shortvideo_comment_zanlog')->insert($data);
			$type = 1;
			Db::name('shortvideo_comment')->where('id',$id)->inc('zan')->update();
		}
		$zancount = Db::name('shortvideo_comment')->where('id',$id)->value('zan');
		return $this->json(['status'=>1,'type'=>$type,'zancount'=>$zancount]);
	}
	//商品海报
	function getposter(){
		$this->checklogin();
		$post = input('post.');
		$platform = platform;
		$page = '/activity/shortvideo/detail';
		$scene = 'id_'.$post['id'];
		$posterset = Db::name('admin_set_poster')->where('aid',aid)->where('type','shortvideo')->where('platform',$platform)->order('id')->find();

		$posterdata = Db::name('member_poster')->where('aid',aid)->where('mid',mid)->where('scene',$scene)->where('type','shortvideo')->where('posterid',$posterset['id'])->find();
		if(!$posterdata){
			$video = Db::name('shortvideo')->where('id',$post['id'])->find();
			$sysset = Db::name('admin_set')->where('aid',aid)->find();
			$textReplaceArr = [
				'[头像]'=>$this->member['headimg'],
				'[昵称]'=>$this->member['nickname'],
				'[姓名]'=>$this->member['realname'],
				'[手机号]'=>$this->member['mobile'],
				'[商城名称]'=>$sysset['name'],
				'[视频名称]'=>$video['name'],
				'[视频简介]'=>$video['description'],
				'[商品图片]'=>$video['coverimg'],
			];

			$poster = $this->_getposter(aid,$video['bid'],$platform,$posterset['content'],$page,$scene,$textReplaceArr);
			$posterdata = [];
			$posterdata['aid'] = aid;
			$posterdata['mid'] = $this->member['id'];
			$posterdata['scene'] = $scene;
			$posterdata['page'] = $page;
			$posterdata['type'] = 'shortvideo';
			$posterdata['poster'] = $poster;
			$posterdata['posterid'] = $posterset['id'];
			$posterdata['createtime'] = time();
			Db::name('member_poster')->insert($posterdata);
		}
		return $this->json(['status'=>1,'poster'=>$posterdata['poster']]);
	}
	//商品列表
	public function getprolist(){
		$video = Db::name('shortvideo')->where('aid',aid)->where('id',input('post.id/d'))->find();
		$haveproduct = false;//是否有商品数据

		if($video['productids']){
			$haveproduct = true;
		}
		if(!$haveproduct){
			return $this->json(['status'=>1,'data'=>[]]);
		}else{
			if($video['productids']){
				$where = [];
				$where[] = ['aid','=',aid];
				$where[] = ['id','in',explode(',', $video['productids'])];
				$datalist = Db::name('shop_product')->field("id,pic,name,sales,market_price,sell_price,lvprice,lvprice_data,sellpoint,fuwupoint,'shop' as type")->where($where)->order(Db::raw('field(id,'.$video['productids'].')'))->select()->toArray();
				if(!$datalist) $datalist = [];
				$datalist = $this->formatprolist($datalist);
			}else{
				}
			return $this->json(['status'=>1,'data'=>$datalist]);
		}
	}

	//评论内容显示
	function getshowcontent($content){
		$icontxtArr = ["[微笑]", "[撇嘴]", "[色]", "[发呆]", "[得意]", "[流泪]", "[害羞]", "[闭嘴]", "[睡]", "[大哭]", "[尴尬]", "[发怒]", "[调皮]", "[呲牙]", "[惊讶]", "[难过]", "[酷]", "[囧]", "[抓狂]", "[吐]", "[偷笑]", "[愉快]", "[白眼]", "[傲慢]", "[饥饿]", "[困]", "[惊恐]", "[流汗]", "[憨笑]", "[悠闲]", "[奋斗]", "[咒骂]", "[疑问]", "[嘘]", "[晕]", "[折磨]", "[衰]", "[骷髅]", "[敲打]", "[再见]", "[擦汗]", "[抠鼻]", "[鼓掌]","[糗大了]", "[坏笑]", "[左哼哼]", "[右哼哼]", "[哈欠]", "[鄙视]", "[委屈]", "[快哭了]", "[阴险]", "[亲亲]","[吓]", "[可怜]", "[菜刀]", "[西瓜]", "[啤酒]", "[篮球]", "[乒乓]", "[咖啡]", "[饭]", "[猪头]", "[玫瑰]", "[凋谢]", "[嘴唇]", "[爱心]", "[心碎]", "[蛋糕]","[闪电]", "[炸弹]", "[刀]" ,"[足球]", "[瓢虫]", "[便便]", "[月亮]", "[太阳]","[礼物]", "[拥抱]", "[强]", "[弱]", "[握手]", "[胜利]", "[抱拳]", "[勾引]", "[拳头]", "[差劲]","[爱你]","[NO]","[OK]", "[跳跳]", "[发抖]", "[怄火]", "[转圈]"];
		$imgArr = [];
		for($i=0;$i<94;$i++){
			$imgArr[] = '^wxface^'.PRE_URL.'/static/chat/wxface/'.$i.'.png'.'^wxface^';
		}
		$content = str_replace($icontxtArr,$imgArr,$content);
		$contentArr = explode('^wxface^',$content);
		$arr = [];
		foreach($contentArr as $v){
			if($v==='') continue;
			if(strpos($v,PRE_URL.'/static/chat/wxface/')===0){
				$arr[] = ['type'=>'image','content'=>$v];
			}else{
				$arr[] = ['type'=>'text','content'=>$v];
			}
		}
		return $arr;
	}

	//发布短视频
	public function uploadvideo(){
		$this->checklogin();
		$sysset = Db::name('shortvideo_sysset')->where('aid',aid)->find();
		if($sysset['can_upload'] == 0) return json(['status'=>-4,'msg'=>'发布功能未启用']);
		$sysset['upload_maxduration'] = 60;

		if(input('param.bid')){
			$bid = input('param.bid');
		}else{
			$bid = 0;
		}

		if(request()->isPost()){
			$title = input('post.title');
			$content = input('post.content');
			$pics = input('post.pics');
			$video = input('post.video');
			$cid = input('post.cid');
			$data = [];
			$data['aid'] = aid;
			$data['bid'] = $bid;
			$data['cid'] = $cid;
			$data['mid'] = mid;
			$data['name'] = $title;
			$data['description'] = $content;
			$data['coverimg'] = $pics;
			$data['url'] = $video;
			$data['createtime'] = time();
			if($sysset['upload_check']==1){//需要审核
				$data['status'] = 0;
				$msg = '提交成功，请等待审核';
			}else{
				$data['status'] = 1;
				$msg = '发布成功';
			}
			$id = Db::name('shortvideo')->insertGetId($data);
            return $this->json(['status'=>1,'msg'=>$msg]);
		}

		$clist = Db::name('shortvideo_category')->where('aid',aid)->where('bid',$bid)->where('status',1)->order('sort desc,id')->select()->toArray();
		$rdata = [];
		$rdata['clist'] = $clist;
		$rdata['sysset'] = $sysset;
		return $this->json($rdata);
	}
	//我的发表记录
	public function myupload(){
		$this->checklogin();
		$where[] = ['aid', '=', aid];
        $where[] = ['mid', '=', mid];
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
		Db::name('shortvideo')->where('aid',aid)->where('mid',mid)->where('id',$id)->update(['status'=>$st]);
		return $this->json(['status'=>1,'msg'=>'操作成功']);
	}
	//商品删除
	public function myuploaddel(){
		$this->checklogin();
		$id = input('post.id/d');
		$rs = Db::name('shortvideo')->where('aid',aid)->where('mid',mid)->where('id',$id)->delete();
		return $this->json(['status'=>1,'msg'=>'操作成功']);
	}
	public function playEnd(){
      $id = input('post.id/d');
      $info = Db::name('shortvideo')->where('aid',aid)->where('id',$id)->find();
      if(empty($info)){
        return $this->json(['status'=>0,'msg'=>'视频不存在']);
      }
      $res_data = [
          'award_msg'=>'',
          'award_data'=>[]
      ];
      $has_more = 0;
      //视频看完奖励结算
              return $this->json(['status'=>1,'msg'=>'ok', 'award_msg'=>$res_data['award_msg'], 'award_data'=>$res_data['award_data'],'has_more'=>$has_more]);
  }

	public function addlook($id = 0,$jindu = 0,$addnum = false){
		}

	public function getshareinfor(){
		}

    //视频播放完成是否可得奖励
    public function playEndAward()
    {
      return $this->json(['status'=>1,'data'=>[]]);
    }
}
