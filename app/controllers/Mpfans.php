<?php


// +----------------------------------------------------------------------
// | 公众号粉丝
// +----------------------------------------------------------------------
namespace app\controllers;
use app\commons\Wechat;
use think\facade\View;
use think\facade\Db;
class Mpfans extends Common
{
    public function initialize(){
		parent::initialize();
		if(bid > 0) showmsg('无访问权限');
	}
	//粉丝列表
    public function fanslist(){
		
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
				$order = 'id desc';
			}
			$where = [];
			$where[] = ['aid','=',aid];
			if(input('?get.subscribe') && input('param.subscribe')!=='') $where[] = ['subscribe','=',input('param.subscribe')];
			if(input('param.sex')) $where[] = ['sex','=',input('param.sex')];
			if(input('param.name')) $where[] = ['name','like','%'.input('param.name').'%'];
			if(input('param.nickname')) $where[] = ['nickname','like','%'.input('param.nickname').'%'];
			if(input('param.subscribe_time') ){
				$ctime = explode(' ~ ',input('param.subscribe_time'));
				$where[] = ['subscribe_time','>=',strtotime($ctime[0])];
				$where[] = ['subscribe_time','<',strtotime($ctime[1]) + 86400];
			}
			$where1 = '1=1';
			if(input('?get.tagid') && input('param.tagid')!==''){
				$tagid = input('param.tagid/d');
				$where1.=" and find_in_set($tagid,tag)";
			}
			$count = 0 + Db::name('fans')->where($where)->where($where1)->count();
			$data = Db::name('fans')->where($where)->where($where1)->page($page,$limit)->order($order)->select()->toArray();

			$tagArr = Db::name('fans_tag')->where('aid',aid)->column('name','tagid');
			foreach($data as $k=>$v){
				$tagname = [];
				if($v['tag']){
					$tagids = explode(',',$v['tag']);
					foreach($tagids as $tagid){
						$tagname[]=$tagArr[$tagid];
					}
				}
				$data[$k]['tagname'] = implode(',',$tagname);
			}
			
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		$count = 0 + Db::name('fans')->where('aid',aid)->count();
		//标签列表
		$tagslist = Db::name('fans_tag')->where('aid',aid)->select()->toArray();
		if(!$tagslist){
			$this->tbtag();
			$tagslist = Db::name('fans_tag')->where('aid',aid)->select()->toArray();
		}
		View::assign('tagslist',$tagslist);
		View::assign('count',$count);
		return View::fetch();
    }
	//粉丝删除
	public function fansdel(){
		$ids = input('post.ids/a');
		Db::name('fans')->where('aid',aid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('粉丝记录删除'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	//打标签
	public function settag(){
		$fansids = input('post.fansids/a');
		$tagids = input('post.tagids/a');
		foreach($fansids as $fansid){
			$fans = Db::name('fans')->where('aid',aid)->where('id',$fansid)->find();
			if(!$fans) return json(['status'=>0,'msg'=>'未找到该粉丝']);
			$access_token = Wechat::access_token(aid,'mp');
			$data = [];
			$data['openid_list'] = [$fans['openid']];
			if($fans['tag']){
				$oldtags = explode(',',$fans['tag']);
			}else{
				$oldtags = array();
			}
			if(!$tagids) $tagids = array();
			$qudiao = array_diff($oldtags,$tagids);
			$zengjia = array_diff($tagids,$oldtags);
			$url = 'https://api.weixin.qq.com/cgi-bin/tags/members/batchuntagging?access_token='.$access_token;
			foreach($qudiao as $tagid){
				$data['tagid'] = $tagid;
				$rs = request_post($url,jsonEncode($data));
				$rs = json_decode($rs,true);
				if($rs['errcode']){
					return json(['status'=>0,'msg'=>\app\commons\Wechat::geterror($rs)]);
				}else{
					$tags = Db::name('fans')->where('id',$fans['id'])->value('tag');
					if($tags){
						$tags = explode(',',$tags);
					}else{
						$tags = array();
					}
					$key = array_search($tagid,$tags);
					if($key!==false){
						unset($tags[$key]);
					}
					Db::name('fans')->where('id',$fans['id'])->update(['tag'=>implode(',',$tags)]);
				}
			}
			$url = 'https://api.weixin.qq.com/cgi-bin/tags/members/batchtagging?access_token='.$access_token;
			foreach($zengjia as $tagid){
				$data['tagid'] = $tagid;
				$rs = request_post($url,jsonEncode($data));
				$rs = json_decode($rs,true);
				if($rs['errcode']){
					return json(['status'=>0,'msg'=>\app\commons\Wechat::geterror($rs)]);
				}else{
					$tags = Db::name('fans')->where('id',$fans['id'])->value('tag');
					if($tags){
						$tags = explode(',',$tags);
					}else{
						$tags = array();
					}
					$tags[] = $tagid;
					Db::name('fans')->where('id',$fans['id'])->update(['tag'=>implode(',',$tags)]);
				}
			}
		}
		return json(['status'=>1,'msg'=>'设置成功']);
	}
	//同步粉丝
	public function tbfans(){
		$access_token = Wechat::access_token(aid,'mp');
		if(input('post.op') == 'getpage'){
			//正在执行中
			Db::name('fans')->where('aid',aid)->update(['doing'=>1]);
			$next_openid = input('post.next_openid');
			$url = 'https://api.weixin.qq.com/cgi-bin/user/get?access_token='.$access_token;
			$rs = request_get($url);
			$rs = json_decode($rs,true);
			if(!$rs['errcode']){
				$totalpage = ceil($rs['total']/10000);
			}else{
				if($rs['errcode'] == 48001){
					return json(['status'=>0,'msg'=>'仅认证的公众号才有同步权限']);
				}else{
					return json(['status'=>0,'msg'=>\app\commons\Wechat::geterror($rs['errcode'])]);
				}
			}
			return json(['totalpage'=>$totalpage,'total'=>$rs['total'],'status'=>1]);
		}elseif(input('post.op') == 'getopenid'){
			$next_openid = input('post.next_openid');
			if($next_openid){
				$url = 'https://api.weixin.qq.com/cgi-bin/user/get?access_token='.$access_token.'&next_openid='.$next_openid;
			}else{
				$url = 'https://api.weixin.qq.com/cgi-bin/user/get?access_token='.$access_token;
			}
			$rs = request_get($url);
			$rs = json_decode($rs,true);
			if(!$rs['errcode']){
				$openids = $rs['data']['openid'];
			}
			$newopenids = array();
			$openid100 = array();
			foreach($openids as $k=>$openid){
				$openid100[] = $openid;
				if(($k+1)%100==0){//每100条获取用户信息一次
					$newopenids[] = $openid100;
					$openid100 = array();
				}
			}
			if($openid100){
				$newopenids[] = $openid100;
				$openid100 = array();
			}
			return json(['status'=>1,'openids'=>$newopenids,'next_openid'=>$rs['next_openid']]);
		}elseif(input('post.op')=='getuserinfo'){
			$user_list = [];
			foreach(input('post.openids/a') as $openid){
				$user_list[] = ['openid'=>$openid];
			}
			$data = jsonEncode(['user_list'=>$user_list]);
			$url = 'https://api.weixin.qq.com/cgi-bin/user/info/batchget?access_token='.$access_token;
			$rs = request_post($url,$data);
			$rs = json_decode($rs,true);
			if(!$rs['errcode']){
				$userlist = $rs['user_info_list'];
				foreach($userlist as $userinfo){
					$member = Db::name('member')->where('aid',aid)->where('mpopenid',$userinfo['openid'])->find();
					$data = array();
					$data['aid'] = aid;
					$data['subscribe'] = $userinfo['subscribe'];
					$data['openid'] = $userinfo['openid'];
					$data['unionid'] = $userinfo['unionid'];
					if($member && strpos($member['headimg'],'/static/img/touxiang.png') === false){
						$data['nickname'] = $member['nickname'];
						$data['headimgurl'] = $member['headimg'];
						if($member['sex']==1){
							$member['sex'] = '男';
						}elseif($member['sex']==2){
							$member['sex'] = '女';
						}else{
							$member['sex'] = '未知';
						}
						$data['sex'] = $member['sex'];
					}
					
					
					$data['language'] = $userinfo['language'];
					$data['city'] = $userinfo['city'];
					$data['province'] = $userinfo['province'];
					$data['country'] = $userinfo['country'];
					$data['subscribe_time'] = $userinfo['subscribe_time'];
					$data['subscribe_scene'] = $userinfo['subscribe_scene'];
					$data['qr_scene'] = $userinfo['qr_scene'];
					$data['qr_scene_str'] = $userinfo['qr_scene_str'];
					$data['tag'] = implode(',',$userinfo['tagid_list']);
					$data['doing'] = 0;
					$rs = Db::name('fans')->where('aid',aid)->where('openid',$data['openid'])->find();
					if($rs){
						Db::name('fans')->where('aid',aid)->where('openid',$data['openid'])->update($data);
					}else{
						Db::name('fans')->insert($data);
					}
					//dump($data);
				}
				//dump($userlist);
			}else{
				return json(['status'=>0,'msg'=>\app\commons\Wechat::geterror($rs)]);
			}
			return json(['status'=>1,'msg'=>'操作成功']);
		}elseif(input('post.op')=='xiuzheng'){
			Db::name('fans')->where('aid',aid)->where('doing',1)->update(['doing'=>0,'subscribe'=>0]);
			\app\commons\System::plog('粉丝记录同步');
			return json(['status'=>1,'msg'=>'同步完成']);
		}
	}
	//标签列表
	public function tagslist(){
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
			$count = 0 + Db::name('fans_tag')->where($where)->count();
			$data = Db::name('fans_tag')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		//没有标签先同步
		$count = 0 + Db::name('fans_tag')->where('aid',aid)->count();
		if($count==0) $this->tbtag();
		return View::fetch();
	}
	//编辑标签
	public function tagsedit(){
		if(request()->isPost()){
			$info = input('post.info/a');
			if($info['id']){
				$tagdata = Db::name('fans_tag')->where('aid',aid)->where('id',$info['id'])->find();
				$tagid = $tagdata['tagid'];
			}else{
				$tagid = '';
			}
			$tagname = $info['name'];
			if($tagname == ''){
				return json(['status'=>0,'msg'=>'标签名称不能为空']);
			}
			$access_token = Wechat::access_token(aid,'mp');
			if($tagid){
				$url = 'https://api.weixin.qq.com/cgi-bin/tags/update?access_token='.$access_token;
				$data = ['tag'=>['id'=>$tagid,'name'=>$tagname]];
				$rs = request_post($url,jsonEncode($data));
				$rs = json_decode($rs,true);
				if($rs['errcode']!=0){
					return json(['status'=>0,'msg'=>\app\commons\Wechat::geterror($rs)]);
				}
				Db::name('fans_tag')->where('aid',aid)->where('tagid',$tagid)->update(['name'=>$tagname]);
				return json(['status'=>1,'msg'=>'编辑成功']);
			}else{
				$url = 'https://api.weixin.qq.com/cgi-bin/tags/create?access_token='.$access_token;
				$data = ['tag'=>['name'=>$tagname]];
				$rs = request_post($url,jsonEncode($data));
				$rs = json_decode($rs,true);
				if($rs['errcode']){
					return json(['status'=>0,'msg'=>\app\commons\Wechat::geterror($rs)]);
				}
				$tag = $rs['tag'];
				$data = array();
				$data['aid'] = aid;
				$data['name'] = $tag['name'];
				$data['tagid'] = $tag['id'];
				Db::name('fans_tag')->insert($data);
				return json(['status'=>1,'msg'=>'添加成功']);
			}
		}
		if(input('param.id')){
			$info = Db::name('fans_tag')->where('aid',aid)->where('id',input('param.id/d'))->find();
		}else{
			$info = array('id'=>'');
		}
		View::assign('info',$info);
		return View::fetch();
	}
	//删除标签
	public function tagsdel(){
		$tagids = input('post.ids/a');
		$access_token = Wechat::access_token(aid,'mp');
		$url = 'https://api.weixin.qq.com/cgi-bin/tags/delete?access_token='.$access_token;
		foreach($tagids as $tagid){
			$data = ['tag'=>['id'=>$tagid]];
			$rs = request_post($url,jsonEncode($data));
			if($rs['errcode']!=0){
				return json(['status'=>0,'msg'=>\app\commons\Wechat::geterror($rs)]);
			}
			Db::name('fans_tag')->where('aid',aid)->where('tagid',$tagid)->delete();
		}
		\app\commons\System::plog('删除粉丝标签');
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	//同步标签
	public function tbtag(){
		$access_token = Wechat::access_token(aid,'mp');
		$url = 'https://api.weixin.qq.com/cgi-bin/tags/get?access_token='.$access_token;
		$rs = request_get($url);
		$rs = json_decode($rs,true);
		if($rs['errcode']!=0){
			return json(['status'=>0,'msg'=>\app\commons\Wechat::geterror($rs)]);
		}
		Db::name('fans_tag')->where('aid',aid)->delete();
		$tags = $rs['tags'];
		$tagids = [];
		foreach($tags as $tag){
			$data = array();
			$data['aid'] = aid;
			$data['name'] = $tag['name'];
			$data['tagid'] = $tag['id'];
			$tagids[] = $tag['id'];
			$hastag = Db::name('fans_tag')->where('aid',aid)->where('tagid',$tag['id'])->find();
			if(!$hastag){
				Db::name('fans_tag')->insert($data);
			}else{
				Db::name('fans_tag')->where('id',$hastag['id'])->update($data);
			}
		}
		Db::name('fans_tag')->where('aid',aid)->where('tagid','not in',$tagids)->delete();
		\app\commons\System::plog('同步粉丝标签');
		return json(['status'=>1,'msg'=>'同步完成']);
	}
	//素材列表
	public function sourcelist(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			$access_token = WeChat::access_token(aid,'mp');
			$url = 'https://api.weixin.qq.com/cgi-bin/material/get_materialcount?access_token='.$access_token;
			$rs = request_get($url);
			$rs = json_decode($rs,true);
			//dump($rs);
			$voice_count = $rs['voice_count'];
			$video_count = $rs['video_count'];
			$image_count = $rs['image_count'];
			$news_count = $rs['news_count'];
			//获取图文素材
			$artlist = [];
			$url = 'https://api.weixin.qq.com/cgi-bin/material/batchget_material?access_token='.$access_token;
			//dump($url);
			$data = [];
			$data['type'] = 'news';
			$data['offset'] = ($page-1)*20;
			$data['count'] = 20;
			$rs = request_post($url,jsonEncode($data));
			$rs = json_decode($rs,true);
			if($rs['item']){
				$artlist = array_merge($artlist,$rs['item']);
			}
			foreach($artlist as $k=>$v){
				foreach($v['content']['news_item'] as $k2=>$v2){
					$v['content']['news_item'][$k2]['thumb_url'] = \app\commons\Pic::uploadoss($v2['thumb_url'],true);
				}
				$artlist[$k] = $v;
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$news_count,'data'=>$artlist]);
		}
		return View::fetch();
	}
	//编辑素材
	public function sourceedit(){
		$media_id = input('param.media_id');
		if($media_id){
			$url = 'https://api.weixin.qq.com/cgi-bin/material/get_material?access_token='.Wechat::access_token(aid,'mp');
			$data = [];
			$data['media_id'] = $media_id;
			$rs = request_post($url,jsonEncode($data));
			$rs = json_decode($rs,true);
			foreach($rs['news_item'] as $k=>$item){
				/*
				$item['content'] = preg_replace_callback('/(https?:\/\/((?!&quot;)[^\"\'\)\s])+)/is', function($matches){
					//dump($matches);
					$rurl = $this->getlocalurl($matches[1]);
					//dump($rurl);
					return $rurl;
				}, $item['content']);
				*/

				$item['content'] = preg_replace_callback('/(<img.*?src=[\'|\"])([^\"\']*?)([\'|\"].*?[\/]?>)/is', function($matches){
					$rurl = $this->getlocalurl(trim(str_replace('&quot;','',$matches[2])));
					return $matches[1].$rurl.$matches[3];
				}, $item['content']);

				$item['content'] = preg_replace_callback('/(background(\-image)?\s*:\s*url\([|\"|\']?)([^\"\']*?)([|\"|\']?\))/is', function($matches){
					$rurl = $this->getlocalurl(trim(str_replace('&quot;','',$matches[3])));
					return $matches[1].$rurl.$matches[4];
				}, $item['content']);

				$item['content'] = str_replace(' data-src',' src',$item['content']);
				$rs['news_item'][$k] = $item;
			}
			//dump($rs);die;
			$info = $rs;
			$info['media_id'] = $media_id;
		}else{
			$info = [
				'media_id'=>'',
				'news_item'=>[
					['title'=>'请填写文章标题','author'=>'','digest'=>'','content'=>'','content_source_url'=>'','show_cover_pic'=>0,'thumb_url'=>PRE_URL.'/static/images/default-pic2.jpg','need_open_comment'=>0,'only_fans_can_comment'=>0]
				]
			];
		}
		View::assign('info',$info);
		return View::fetch();
	}
	public function sourcesave(){
		$media_id = input('post.media_id');
		$old_news_item = input('post.old_news_item/a');
		$news_item = input('post.news_item/a');
		$access_token = Wechat::access_token(aid,'mp');
		$needadd = true;
		if($media_id && count($news_item) == count($old_news_item)){ //条数相同 条数不同需要重新创建
			$needadd = false;
			foreach($news_item as $k=>$v){
				if($v['need_open_comment'] != $old_news_item[$k]['need_open_comment'] || ($v['need_open_comment']!=0 && $v['only_fans_can_comment'] == $old_news_item[$k]['only_fans_can_comment'])){ //评论修改了需要重新创建
					$needadd = true;
				}
			}
		}
		if(!$needadd){
			$url = 'https://api.weixin.qq.com/cgi-bin/material/update_news?access_token='.$access_token;
			$data = [];
			$data['media_id'] = $media_id;
			foreach($news_item as $k=>$item){
				$data['index'] = $k;
				$item['thumb_media_id'] = \app\commons\Wechat::getmediaid(aid,$item['thumb_url']);
				unset($item['thumb_url']);

				$item['content'] = preg_replace_callback('/(<img.*?src=[\'|\"])([^\"\']*?)([\'|\"].*?[\/]?>)/is', function($matches){
					$rurl = $this->getwxurl(trim(str_replace('&quot;','',$matches[2])));
					return $matches[1].$rurl.$matches[3];
				}, $item['content']);

				$item['content'] = preg_replace_callback('/(background(\-image)?\s*:\s*url\([|\"|\']?)([^\"\']*?)([|\"|\']?\))/is', function($matches){
					$rurl = $this->getwxurl(trim(str_replace('&quot;','',$matches[3])));
					return $matches[1].$rurl.$matches[4];
				}, $item['content']);
				/*
				$item['content'] = preg_replace_callback('/(https?:\/\/((?!&quot;)[^\"\'\)\s])+)/is', function($matches){
					//dump($matches);
					$rurl = $this->getwxurl($matches[1]);
					//dump($rurl);
					return $rurl;
				}, $item['content']);
				*/
				//die;
				$data['articles'] = $item;
				$rs = request_post($url,jsonEncode($data));
				$rs = json_decode($rs,true);
			}
			if($rs['errcode']!=0){
				return json(['status'=>0,'msg'=>\app\commons\Wechat::geterror($rs),'rs'=>$rs]);
			}
			\app\commons\System::plog('编辑公众号素材'.$media_id);
			return json(['status'=>1,'msg'=>'提交成功']);
		}else{
			$url = 'https://api.weixin.qq.com/cgi-bin/material/add_news?access_token='.$access_token;
			$data = [];
			$data['articles'] = [];
			foreach($news_item as $k=>$item){
				$item['thumb_media_id'] = \app\commons\Wechat::getmediaid(aid,$item['thumb_url']);
				unset($item['thumb_url']);

				$item['content'] = preg_replace_callback('/(<img.*?src=[\'|\"])([^\"\']*?)([\'|\"].*?[\/]?>)/is', function($matches){
					$rurl = $this->getwxurl(trim(str_replace('&quot;','',$matches[2])));
					return $matches[1].$rurl.$matches[3];
				}, $item['content']);

				$item['content'] = preg_replace_callback('/(background(\-image)?\s*:\s*url\([|\"|\']?)([^\"\']*?)([|\"|\']?\))/is', function($matches){
					$rurl = $this->getwxurl(trim(str_replace('&quot;','',$matches[3])));
					return $matches[1].$rurl.$matches[4];
				}, $item['content']);

				$data['articles'][] = $item;
			}
			$rs = request_post($url,jsonEncode($data));
			$rs = json_decode($rs,true);
			if($rs['errcode']!=0){
				return json(['status'=>0,'msg'=>\app\commons\Wechat::geterror($rs),'rs'=>$rs]);
			}
			\app\commons\System::plog('添加公众号素材');
			return json(['status'=>1,'msg'=>'提交成功']);
		}
		//dump($media_id);
		//dump($news_item);
	}
	//删除素材
	public function sourcedel(){
		$media_id = input('post.media_id');
		$url = 'https://api.weixin.qq.com/cgi-bin/material/del_material?access_token='.Wechat::access_token(aid,'mp');
		$rs = request_post($url,jsonEncode(['media_id'=>$media_id]));
		$rs = json_decode($rs,true);
		if($rs['errcode']!=0){
			return json(['status'=>0,'msg'=>\app\commons\Wechat::geterror($rs)]);
		}
		\app\commons\System::plog('删除公众号素材'.$media_id);
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	//素材预览 
	public function submit_preview(){
		$media_id = input('post.media_id');
		$towxname = trim(input('post.towxname'));
		$url = 'https://api.weixin.qq.com/cgi-bin/message/mass/preview?access_token='.Wechat::access_token(aid,'mp');
		$data = [];
		$data['towxname'] = $towxname;
		$data['mpnews'] = ['media_id'=>$media_id];
		$data['msgtype'] = 'mpnews';
		$rs = request_post($url,jsonEncode($data));
		//dump(jsonEncode($data));
		//dump($rs);
		$rs = json_decode($rs,true);
		if($rs['errcode']!=0){
			return json(['status'=>0,'msg'=>\app\commons\Wechat::geterror($rs)]);
		}
		$has = Db::name('mp_preview_wxhistory')->where('aid',aid)->where('towxname',$towxname)->find();
		if($has){
			Db::name('mp_preview_wxhistory')->where('aid',aid)->where('towxname',$towxname)->update(['createtime'=>time(),'isdel'=>0]);
		}else{
			Db::name('mp_preview_wxhistory')->insert(['aid'=>aid,'towxname'=>$towxname,'createtime'=>time()]);
		}
		return json(['status'=>1,'msg'=>'发送预览成功，请留意手机的微信','rs'=>$rs]);
	}
	public function getsourcedata(){
		$media_id = input('post.media_id');
		$url = 'https://api.weixin.qq.com/cgi-bin/material/get_material?access_token='.Wechat::access_token(aid,'mp');
		$data = [];
		$data['media_id'] = $media_id;
		$rs = request_post($url,jsonEncode($data));
		$rs = json_decode($rs,true);
		if($rs['errcode']!=0){
			return json(['status'=>0,'msg'=>\app\commons\Wechat::geterror($rs)]);
		}
		//标签列表
		$tagslist = Db::name('fans_tag')->where('aid',aid)->select()->toArray();
		if(!$tagslist){
			$this->tbtag();
			$tagslist = Db::name('fans_tag')->where('aid',aid)->select()->toArray();
		}
		return json(['status'=>1,'data'=>$rs,'tagslist'=>$tagslist,'time'=>time()]);
	}
	//素材群发
	public function sourcesendall(){
		$info = input('post.info/a');
		if(!$info['media_id']) return json(['status'=>0,'msg'=>'请选择素材']);
		$indata = [];
		$indata['aid'] = aid;
		$indata['media_id'] = $info['media_id'];
		$indata['msgtype'] = 'mpnews';
		$indata['send_ignore_reprint'] = $info['send_ignore_reprint'];
		$indata['is_to_all'] = $info['is_to_all'];
		$indata['tag_id'] = $info['tag_id'];
		$indata['createtime'] = time();
		$indata['isdingshi'] = $info['isdingshi'];
		if($info['isdingshi']==1){
			$indata['sendtime'] = strtotime($info['send_day'].' '.$info['send_hour'].':'.$info['send_minute'].':00');
			if($indata['sendtime'] <= time()){
				return json(['status'=>0,'msg'=>'定时群发时间不能小于当前时间']);
			}
		}else{
			$indata['sendtime'] = time();
		}
		$id = Db::name('mp_source_sendalllog')->insertGetId($indata);
		//立即群发
		if($info['isdingshi']==0){
			$url = 'https://api.weixin.qq.com/cgi-bin/message/mass/sendall?access_token='.Wechat::access_token(aid,'mp');
			$data = [];
			$data['filter'] = [];
			if($info['is_to_all']==1){
				$data['filter'] = ['is_to_all'=>true];
			}else{
				$data['filter'] = ['is_to_all'=>false,'tag_id'=>$info['tag_id']];
			}
			$data['mpnews'] = ['media_id'=>$info['media_id']];
			$data['msgtype'] = 'mpnews';
			$data['send_ignore_reprint'] = $info['send_ignore_reprint']; //图文消息被判定为转载时，是否继续群发。 1为继续群发（转载），0为停止群发。 该参数默认为0
			$rs = request_post($url,jsonEncode($data));
			$rs = json_decode($rs,true);
			if($rs['errcode']!=0){
				Db::name('mp_source_sendalllog')->where('id',$id)->update(['errcode'=>$rs['errcode'],'errmsg'=>\app\commons\Wechat::geterror($rs)]);
				return json(['status'=>0,'msg'=>\app\commons\Wechat::geterror($rs)]);
			}else{
				Db::name('mp_source_sendalllog')->where('id',$id)->update(['msg_id'=>$rs['msg_id'],'msg_data_id'=>$rs['msg_data_id'],'status'=>1,'errcode'=>$rs['errcode'],'errmsg'=>'提交成功']);
				\app\commons\System::plog('公众号素材群发'.$info['media_id']);
				return json(['status'=>0,'msg'=>'已群发','url'=>url('sourcesendlog')]);
			}
		}else{
			\app\commons\System::plog('公众号素材定时群发'.$info['media_id']);
			return json(['status'=>1,'msg'=>'操作成功，系统将在 '.date('Y-m-d H:i',$indata['sendtime']).' 为您进行群发','url'=>url('sourcesendlog')]);
		}
	}
	//群发记录
	public function sourcesendlog(){
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
			if(input('param.ctime') ){
				$ctime = explode(' ~ ',input('param.ctime'));
				$where[] = ['createtime','>=',strtotime($ctime[0])];
				$where[] = ['createtime','<',strtotime($ctime[1]) + 86400];
			}
			$count = 0 + Db::name('mp_source_sendalllog')->where($where)->count();
			$list = Db::name('mp_source_sendalllog')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			$tagArr = Db::name('fans_tag')->where('aid',aid)->column('name','tagid');
			foreach($list as $k=>$v){
				if($v['tag_id']){
					$list[$k]['tagname'] = $tagArr[$v['tag_id']];
				}
				$url = 'https://api.weixin.qq.com/cgi-bin/material/get_material?access_token='.Wechat::access_token(aid,'mp');
				$data = [];
				$data['media_id'] = $v['media_id'];
				$rs = request_post($url,jsonEncode($data));
				$rs = json_decode($rs,true);
				$list[$k]['content'] = $rs;
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$list]);
		}
		return View::fetch();
	}
	//群发记录删除
	public function sourcesendlogdel(){
		$id = input('post.id/d');
		Db::name('mp_source_sendalllog')->where('aid',aid)->where('id',$id)->delete();
		\app\commons\System::plog('公众号素材群发记录删除');
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	//获取预览微信记录
	public function gethistorywx(){
		$historywx = Db::name('mp_preview_wxhistory')->field('id,towxname')->where('aid',aid)->where('isdel',0)->order('createtime desc')->select()->toArray();
		return json(['status'=>1,'data'=>$historywx]);
	}
	//删除预览微信记录
	public function removehistorywx(){
		$id = input('post.id/d');
		Db::name('mp_preview_wxhistory')->where('aid',aid)->where('id',$id)->update(['isdel'=>1]);
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	//图片上传到微信
	public function getwxurl($picurl){
		if($picurl=='') return $picurl;
		$picurl2 = \app\commons\Pic::tolocal($picurl);
		$info = Db::name('pictolocal')->where('aid',aid)->where('pic',$picurl2)->find();
		if($info){
			return $info['url'];
		}
		$mediapath = ROOT_PATH.str_replace(PRE_URL.'/','',$picurl2);
		//$data = array('media'=>'@'.$mediapath);
		$data = [];
		$data['media'] = new \CurlFile($mediapath);
		$result = request_post('https://api.weixin.qq.com/cgi-bin/media/uploadimg?access_token='.Wechat::access_token(aid,'mp'),$data);
		$res = json_decode($result,true);
		if($res['url']){
			Db::name('pictolocal')->insert(['aid'=>aid,'pic'=>$picurl,'url'=>$res['url'],'createtime'=>time()]);
			return $res['url'];
		}else{
			return $picurl;
			return json(['status'=>0,'msg'=>\app\commons\Wechat::geterror($res)]);
		}
	}
	//微信图片下载到本地 防止无法显示(此图片来自微信公众平台 未经允许禁止转载)
	public function getlocalurl($url){
		$info = Db::name('pictolocal')->where('aid',aid)->where('url',$url)->find();
		if($info){
			return $info['pic'];
		}
		$pic = \app\commons\Pic::tolocal($url);
		return $pic;
	}

	//模板消息群发
	public function tmplsend(){
		set_time_limit(0);
		if(request()->isPost()){
			$info = input('post.info/a');
			$data = input('post.data/a');
			$pagenum = input('param.pagenum/d');
			$pagelimit = input('param.pagelimit/d');
            // 模板类型 1:模板 2:类目模板
            if($info['tmpl_type'] == 2){
                $info['template_id'] = $info['lm_template_id'];
            }
			if($info['template_id']=='') return json(['status'=>0,'msg'=>'请选择模板']);
			//dump($info);
			$senddata = [];
			$senddata['template_id'] = $info['template_id'];
			$senddata['url'] = $info['url'];
			$senddata['data'] = $data;
			if($info['to_type']==1){//按openid发送
				$allopenids = preg_replace('/\s+/','',str_replace(["\r\n","\r","\n"],',',$info['openids']));
				$allopenids = explode(",",preg_replace('/,+/',',',$allopenids));
				$openids = array_slice($allopenids,($pagenum-1)*$pagelimit,$pagelimit);
				//$fanslist = [];
				//foreach($sendopenids as $k=>$v){
				//	$fanslist[] = ['openid'=>$v];
				//}
			}elseif($info['to_type']==2){//按标签发送
				$tagid = $info['tagid'];
				$openids = Db::name('fans')->field('openid')->where('aid',aid)->where('subscribe',1)->where("find_in_set({$tagid},tag)")->page($pagenum,$pagelimit)->column('openid');
			}elseif($info['to_type']==3){//全部用户
				$tagid = $info['tagid'];
				$openids = Db::name('fans')->field('openid')->where('aid',aid)->where('subscribe',1)->page($pagenum,$pagelimit)->column('openid');
			}
			if(input('post.logid')){
				$logid = input('post.logid');
			}else{
				$logid = Db::name('mp_tmpl_sendlog')->insertGetId([
					'aid'=>aid,
					'title'=>$info['title'],
					'template_id'=>$info['template_id'],
					'data'=>jsonEncode($data),
					'url'=>$info['url'],
					'to_type'=>$info['to_type'],
					'tagid'=>$info['tagid'],
					'openids'=>jsonEncode($allopenids),
					'createtime'=>time(),
					'sendcount'=>0,
					'successcount'=>0,
					'errorcount'=>0,
					'endtime'=>time(),
				]);
			}

            //模板消息链接 mp:公众号 wx:小程序
            if(isset($info['tmpl_message_link']) && $info['tmpl_message_link']!=''){
                if($info['tmpl_message_link'] == 'wx'){
                    //验证权限
                    $platform = \app\commons\Common::getplatform(aid);
                    if(in_array('wx',$platform) && $info['url']!=''){
                        $appid = Db::name('admin_setapp_wx')->where('aid',aid)->value('appid');
                        if($appid){
                            $senddata['miniprogram'] = [
                                'appid' => $appid,
                                'pagepath' => getWxAppPath($info['url'])
                            ];
                        }
                    }
                }
            }

			//dump($openids);
			$url = 'https://api.weixin.qq.com/cgi-bin/message/template/send?access_token='.Wechat::access_token(aid,'mp');
			$sendcount = 0;
			$successcount = 0;
			$errorcount = 0;
			foreach($openids as $openid){
				$senddata['touser'] = $openid;
				//die;
				$rs = request_post($url,jsonEncode($senddata));
				$rs = json_decode($rs,true);
				if($rs['errcode'] ==0 ){
					$successcount++;
				}else{
					$errorcount++; 
					Db::name('mp_tmpl_sendlog_errlog')->insert(['aid'=>aid,'logid'=>$logid,'openid'=>$openid,'createtime'=>time(),'errcode'=>$rs['errcode'],'errmsg'=>\app\commons\Wechat::geterror($rs)]);
				}
				$sendcount++;
			}
			Db::name('mp_tmpl_sendlog')->where('aid',aid)->where('id',$logid)->update([
				'sendcount'=>Db::raw("sendcount+{$sendcount}"),
				'successcount'=>Db::raw("successcount+{$successcount}"),
				'errorcount'=>Db::raw("errorcount+{$errorcount}"),
				'endtime'=>time(),
			]);

			$tmpllog = Db::name('mp_tmpl_sendlog')->where('aid',aid)->where('id',$logid)->find();
			if(count($openids)<$pagelimit){
				$status = 1;
			}else{ //还有下一页
				$status = 2;
			}
			return json(['status'=>$status,'msg'=>'','logid'=>$logid,'sendcount'=>$tmpllog['sendcount'],'successcount'=>$tmpllog['successcount'],'errorcount'=>$tmpllog['errorcount']]);
		}
		//dump(access_token(aid,'mp'));
		$url = 'https://api.weixin.qq.com/cgi-bin/template/get_all_private_template?access_token='.Wechat::access_token(aid,'mp');
		$res = request_get($url);
		$res = json_decode($res,true);
		if($res['errcode']!=0){
			//return json(['status'=>0,'msg'=>\app\commons\Wechat::geterror($res)]);
			if($res['errcode']==48001){
				showmsg('只有认证的服务号才有此权限');
			}else{
				showmsg(\app\commons\Wechat::geterror($res));
			}
		}


        //获取模板消息设置
        $gettemplate = Db::name('mp_tmplset')->where('aid',aid)->find();
        $gettemplate = array_filter(array_values($gettemplate));

        //dump($gettemplate);
        $template = [];
		$tmpllist = $res['template_list'];
		foreach($tmpllist as $k=>$v){
			if($v['title']=='订阅模板消息'){
				unset($tmpllist[$k]);
			}else{
                $fields = explode('!^!',str_replace(["\r\n","\r","\n"],'!^!',$v['content']));
                //判断是否存在 顶部描述：{{first.DATA}}
                $isDescribe = strpos($fields[0],'{{');
                $fieldarr = [];
                foreach($fields as $k2=>$v2){
                    if($isDescribe == 0){
                        if($k2!=0 && $k2!=count($fields)-1){
                            $v2 = str_replace('.DATA}}','',$v2);
                            $fieldarr[] = explode('{{',$v2);
                        }
                    }else{
                        if($k2!=count($fields)-1){
                            $v2 = str_replace('.DATA}}','',$v2);
                            $fieldarr[] = explode('{{',$v2);
                        }
                    }
                }
                $tmpllist[$k]['fields'] = $fieldarr;
                //查询出非类目模板消息
                if(in_array($v['template_id'],$gettemplate)){
                    $template[] = $tmpllist[$k];
                    unset($tmpllist[$k]);
                }
			}
		}
		//标签列表
		$tagslist = Db::name('fans_tag')->where('aid',aid)->select()->toArray();
		if(!$tagslist){
			$this->tbtag();
			$tagslist = Db::name('fans_tag')->where('aid',aid)->select()->toArray();
		}
		foreach($tagslist as $k=>$v){
			$tagslist[$k]['count'] = Db::name('fans')->where('aid',aid)->where('subscribe',1)->where("find_in_set({$v['tagid']},tag)")->count();
		}
		$fanscount = Db::name('fans')->where('aid',aid)->where('subscribe',1)->count();
		View::assign('tmpllist',$tmpllist);  //类目模板消息
		View::assign('template',$template);  //模板消息
		View::assign('tagslist',$tagslist);
		View::assign('fanscount',$fanscount);
		return View::fetch();
	}
	//模板消息发送记录
	public function tmplsendlog(){
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

			$count = 0 + Db::name('mp_tmpl_sendlog')->where($where)->count();
			$data = Db::name('mp_tmpl_sendlog')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			$tagArr = Db::name('fans_tag')->where('aid',aid)->column('name','tagid');
			foreach($data as $k=>$v){
				$data[$k]['data'] = json_decode($v['data'],true);
				if($v['tagid']){
					$data[$k]['tagname'] = $tagArr[$v['tagid']];
				}
			}
			
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
	}
	//模板消息发送记录删除
	public function tmplsendlogdel(){
		$ids = input('post.ids/a');
		Db::name('mp_tmpl_sendlog')->where('aid',aid)->where('id','in',$ids)->delete();
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	//模板消息发送失败记录
	public function tmplsendlog_errlog(){
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
			$where[] = ['logid','=',input('param.logid')];

			$count = 0 + Db::name('mp_tmpl_sendlog_errlog')->where($where)->count();
			$data = Db::name('mp_tmpl_sendlog_errlog')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
	}
	//活跃粉丝发送图文消息
	public function kfmsgsend(){
		set_time_limit(0);
		if(request()->isPost()){
			$info = input('post.info/a');
			$pagenum = input('param.pagenum/d');
			$pagelimit = input('param.pagelimit/d');
			if($info['media_id']=='') return json(['status'=>0,'msg'=>'请选择素材']);
			//dump($info);
			if($info['to_type']==1){//按openid发送
				$allopenids = preg_replace('/\s+/','',str_replace(["\r\n","\r","\n"],',',$info['openids']));
				$allopenids = explode(",",preg_replace('/,+/',',',$allopenids));
				$openids = array_slice($allopenids,($pagenum-1)*$pagelimit,$pagelimit);
				//$fanslist = [];
				//foreach($sendopenids as $k=>$v){
				//	$fanslist[] = ['openid'=>$v];
				//}
			}elseif($info['to_type']==2){//按标签发送
				$tagid = $info['tagid'];
				$openids = Db::name('fans')->field('openid')->where('aid',aid)->where('subscribe',1)->where("find_in_set({$tagid},tag)")->page($pagenum,$pagelimit)->column('openid');
			}elseif($info['to_type']==3){//全部用户
				$tagid = $info['tagid'];
				$openids = Db::name('fans')->field('openid')->where('aid',aid)->where('subscribe',1)->page($pagenum,$pagelimit)->column('openid');
			}
			if(input('post.logid')){
				$logid = input('post.logid');
			}else{
				$logid = Db::name('mp_kfmsg_sendlog')->insertGetId([
					'aid'=>aid,
					'media_id'=>$info['media_id'],
					'to_type'=>$info['to_type'],
					'tagid'=>$info['tagid'],
					'openids'=>jsonEncode($allopenids),
					'createtime'=>time(),
					'sendcount'=>0,
					'successcount'=>0,
					'errorcount'=>0,
					'endtime'=>time(),
				]);
			}
			$senddata = [];
			$senddata['msgtype'] = 'mpnews';
			$senddata['mpnews'] = ['media_id'=>$info['media_id']];
			//dump($openids);
			$url = 'https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token='.Wechat::access_token(aid,'mp');
			$sendcount = 0;
			$successcount = 0;
			$errorcount = 0;
			foreach($openids as $openid){
				$senddata['touser'] = $openid;
				//die;
				$rs = request_post($url,jsonEncode($senddata));
				$rs = json_decode($rs,true);
				if($rs['errcode'] ==0 ){
					$successcount++;
					Db::name('mp_kfmsg_sendlog_errlog')->insert(['aid'=>aid,'logid'=>$logid,'openid'=>$openid,'createtime'=>time(),'errcode'=>$rs['errcode'],'errmsg'=>\app\commons\Wechat::geterror($rs)]);
				}else{
					$errorcount++; 
					Db::name('mp_kfmsg_sendlog_errlog')->insert(['aid'=>aid,'logid'=>$logid,'openid'=>$openid,'createtime'=>time(),'errcode'=>$rs['errcode'],'errmsg'=>\app\commons\Wechat::geterror($rs)]);
				}
				$sendcount++;
			}
			Db::name('mp_kfmsg_sendlog')->where('aid',aid)->where('id',$logid)->update([
				'sendcount'=>Db::raw("sendcount+{$sendcount}"),
				'successcount'=>Db::raw("successcount+{$successcount}"),
				'errorcount'=>Db::raw("errorcount+{$errorcount}"),
				'endtime'=>time(),
			]);

			$tmpllog = Db::name('mp_kfmsg_sendlog')->where('aid',aid)->where('id',$logid)->find();
			if(count($openids)<$pagelimit){
				$status = 1;
			}else{ //还有下一页
				$status = 2;
			}
			return json(['status'=>$status,'msg'=>'','logid'=>$logid,'sendcount'=>$tmpllog['sendcount'],'successcount'=>$tmpllog['successcount'],'errorcount'=>$tmpllog['errorcount']]);
		}

		//标签列表
		$tagslist = Db::name('fans_tag')->where('aid',aid)->select()->toArray();
		if(!$tagslist){
			$this->tbtag();
			$tagslist = Db::name('fans_tag')->where('aid',aid)->select()->toArray();
		}
		foreach($tagslist as $k=>$v){
			$tagslist[$k]['count'] = Db::name('fans')->where('aid',aid)->where('subscribe',1)->where("find_in_set({$v['tagid']},tag)")->count();
		}
		$fanscount = Db::name('fans')->where('aid',aid)->where('subscribe',1)->count();
		View::assign('tagslist',$tagslist);
		View::assign('fanscount',$fanscount);
		return View::fetch();
	}
	//选择素材
	public function choosesource(){
		return View::fetch();
	}
	//48小时图文消息发送记录
	public function kfmsgsendlog(){
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

			$count = 0 + Db::name('mp_kfmsg_sendlog')->where($where)->count();
			$list = Db::name('mp_kfmsg_sendlog')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			$tagArr = Db::name('fans_tag')->where('aid',aid)->column('name','tagid');
			foreach($list as $k=>$v){
				if($v['tagid']){
					$list[$k]['tagname'] = $tagArr[$v['tagid']];
				}
				$url = 'https://api.weixin.qq.com/cgi-bin/material/get_material?access_token='.Wechat::access_token(aid,'mp');
				$data = [];
				$data['media_id'] = $v['media_id'];
				$rs = request_post($url,jsonEncode($data));
				$rs = json_decode($rs,true);
				$list[$k]['sourcedata'] = $rs;
			}
			
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$list]);
		}
		return View::fetch();
	}
	//48小时图文消息发送记录删除
	public function kfmsgsendlogdel(){
		$ids = input('post.ids/a');
		Db::name('mp_kfmsg_sendlog')->where('aid',aid)->where('id','in',$ids)->delete();
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	//48小时图文消息发送失败记录
	public function kfmsgsendlog_errlog(){
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
			$where[] = ['logid','=',input('param.logid')];
			if(input('param.status')){
				if(input('param.status')==1){
					$where[] = ['errcode','=',0];
				}else{
					$where[] = ['errcode','<>',0];
				}
			}
			$count = 0 + Db::name('mp_kfmsg_sendlog_errlog')->where($where)->count();
			$data = Db::name('mp_kfmsg_sendlog_errlog')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
	}
}