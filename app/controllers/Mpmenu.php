<?php


// +----------------------------------------------------------------------
// | 公众号 自定义菜单设置
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class Mpmenu extends Common
{
    public function initialize(){
		parent::initialize();
		if(bid > 0) showmsg('无访问权限');
	}
	//菜单
	public function index(){
		$menuset = Db::name('mp_menu')->where('aid',aid)->find();
		if(!$menuset){
			$url = 'https://api.weixin.qq.com/cgi-bin/get_current_selfmenu_info?access_token='.\app\commons\Wechat::access_token(aid,'mp');
			$rs = request_get($url);
			$rs = json_decode($rs,true);
			if($rs['errcode']!=0){
				showmsg(\app\commons\Wechat::geterror($rs));
			}else{
				$menudata = $rs['selfmenu_info']['button'];
				$newmenudata = [];
				foreach($menudata as $k=>$v){
					$newmenudata[$k]['menu_name'] = $v['name'];
					if($v['sub_button']){
						$menu2data = [];
						foreach($v['sub_button']['list'] as $k2=>$v2){
							$menu2data[$k2]['menu_name'] = $v2['name'];
							$menu2data[$k2]['menu_type'] = $v2['type'];
							if($v2['type']=='view'){
								$menu2data[$k2]['menu_url'] = $v2['url'];
							}elseif($v2['type']=='miniprogram'){
								$menu2data[$k2]['menu_appid'] = $v2['appid'];
								$menu2data[$k2]['menu_pagepath'] = $v2['pagepath'];
							}
						}
						$newmenudata[$k]['sub_button'] = $menu2data;
					}else{
						$newmenudata[$k]['menu_name'] = $v['name'];
						$newmenudata[$k]['menu_type'] = $v['type'];
						if($v['type']=='view'){
							$newmenudata[$k]['menu_url'] = $v['url'];
						}elseif($v['type']=='miniprogram'){
							$newmenudata[$k]['menu_appid'] = $v['appid'];
							$newmenudata[$k]['menu_pagepath'] = $v['pagepath'];
						}
					}
				}
				$menudata = jsonEncode($newmenudata);
				Db::name('mp_menu')->insert(['aid'=>aid,'menudata'=>$menudata,'createtime'=>time()]);
			}
		}else{
			$menudata = $menuset['menudata'];
		}
		if(!$menudata) $menudata = '[]';
        $mpauthinfo = Db::name('admin_setapp_mp')->where('aid',aid)->find();

		View::assign('menudata',$menudata);
		View::assign('mpauthinfo',$mpauthinfo);
		return View::fetch();
	}
	//获取图文素材
	public function newslist(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			$access_token = \app\commons\Wechat::access_token(aid,'mp');
			$url = 'https://api.weixin.qq.com/cgi-bin/material/get_materialcount?access_token='.$access_token;
			$rs = request_post($url,jsonEncode(['offset'=>0,'count'=>10]));
			$rs = json_decode($rs,true);
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
	//根据media_id获取素材内容
	public function getmediainfo(){
		$url = 'https://api.weixin.qq.com/cgi-bin/material/get_material?access_token='.\app\commons\Wechat::access_token(aid,'mp');
		$data = [];
		$data['media_id'] = input('param.media_id');
		$rs = request_post($url,jsonEncode($data));
		$rs = json_decode($rs,true);
		if($rs['errcode']!=0){
			return json(['status'=>0,'msg'=>\app\commons\Wechat::geterror($rs)]);
		}else{
			$newsinfo = $rs['news_item'];
			foreach($newsinfo as $k2=>$v2){
				$newsinfo[$k2]['thumb_url'] = \app\commons\Pic::uploadoss($v2['thumb_url'],true);
			}
		}
		return json(['status'=>1,'data'=>$newsinfo]);
	}
	//获取公众号关联的小程序(暂未使用)
	public function getminiprogram(){
		$url = 'https://api.weixin.qq.com/cgi-bin/wxopen/wxamplinkget?access_token='.\app\commons\Wechat::access_token(aid,'mp');
		$rs = request_post($url);
		$rs = json_decode($rs,true);
		if($rs['errcode']!=0){
			return json(['status'=>0,'msg'=>\app\commons\Wechat::geterror($rs)]);
		}

		//$xcxauthinfo = Db::name('admin_authinfo')->field('appid,nick_name,head_img,user_name')->where('aid',aid)->where('apptype','wx')->find();
		//$wxisauth = Db::name('admin_set')->where('aid',aid)->value('wxisauth');
		
		$hasgl = 0;//当前授权的小程序是否关联
		$data = $rs['wxopens']['items'];
		foreach($data as $k=>$v){
			//dump($v['username']);
			//if($v['status'] == 1 && $v['username']==$xcxauthinfo['user_name']) $hasgl = 1;
			$authinfo = Db::name('admin_authinfo')->where('user_name',$v['username'])->find();
			if($authinfo){
				$data[$k]['appid'] = $authinfo['appid'];
			}else{
				$data[$k]['appid'] = '';
			}
		}
		//dump($wxisauth);
		//dump($hasgl);
		//if($xcxauthinfo && $wxisauth==1 && $hasgl==0){ //已授权并且未关联 显示需要关联
		//	$showgl = 1;
		//}else{
		//	$showgl = 0;
		//}
		return json(['status'=>1,'data'=>$data,'showgl'=>$showgl,'xcxauthinfo'=>$xcxauthinfo]);
	}
	//关联小程序
	public function guanlian(){
		$appid = input('param.appid');
		$url = 'https://api.weixin.qq.com/cgi-bin/wxopen/wxamplink?access_token='.\app\commons\Wechat::access_token(aid,'mp');
		$data = [];
		$data['appid'] = $appid;
		$data['notify_users'] = 0;
		$data['show_profile'] = 1;
		$rs = request_post($url,jsonEncode($data));
		$rs = json_decode($rs,true);
		if($rs['errcode']!=0){
			if($rs['errcode']==89010){
				return json(['status'=>1,'msg'=>'已经发送关联邀请，请联系管理员确认']);
			}
			return json(['status'=>0,'msg'=>\app\commons\Wechat::geterror($rs)]);
		}
		return json(['status'=>1,'msg'=>'关联成功']);
	}
	public function save(){
		$menudata = input('post.menudata/a');

		if(!$menudata) $menudata = [];
		$type = input('post.type');
		if(Db::name('mp_menu')->where('aid',aid)->find()){
			Db::name('mp_menu')->where('aid',aid)->update(['menudata'=>jsonEncode($menudata),'updatetime'=>time()]);
		}else{
			Db::name('mp_menu')->insert(['aid'=>aid,'menudata'=>jsonEncode($menudata),'createtime'=>time()]);
		}
		if($type==1){
			$buttondata = [];
			foreach($menudata as $k=>$v){
				$newval = [];
				$newval['name'] = $v['menu_name'];
				if($v['sub_button']){
					$sub_button = [];
					foreach($v['sub_button'] as $subv){
						$newsubval = [];
						$newsubval['name'] = $subv['menu_name'];
						if($subv['menu_type'] == 'click'){
							if($subv['message_type']=='news'){
								$newsubval['type'] = 'media_id';
								$newsubval['media_id'] = $subv['news_content'];
							}elseif($subv['message_type']=='image'){
								$newsubval['type'] = 'media_id';
								$newsubval['media_id'] = \app\commons\Wechat::getmediaid(aid,$subv['image_content']);
							}elseif($subv['message_type']=='voice'){
								$newsubval['type'] = 'media_id';
								$newsubval['media_id'] = \app\commons\Wechat::getmediaid(aid,$subv['voice_content'],'voice');
							}elseif($subv['message_type']=='video'){
								$newsubval['type'] = 'media_id';
								if(!$subv['video_title'] || !$subv['video_introduction']){
									return json(['status'=>0,'msg'=>'请填写视频标题及描述']);
								}
								$newsubval['media_id'] = \app\commons\Wechat::getmediaid(aid,$subv['video_content'],'video',jsonEncode(['title'=>$subv['video_title'],'introduction'=>$subv['video_introduction']]));
							}elseif($subv['message_type']=='text'){
								$newsubval['type'] = 'click';
								$newsubval['key'] = uniqid().'_'.rand(1000,9999);
								Db::name('mp_menukey')->insert(['key'=>$newsubval['key'],'val'=>$subv['text_content']]);
							}
						}elseif($subv['menu_type']=='view'){
							$newsubval['type'] = 'view';
							$newsubval['url'] = $subv['menu_url'];
						}elseif($subv['menu_type']=='miniprogram'){
							$newsubval['type'] = 'miniprogram';
							$newsubval['appid'] = $subv['menu_appid'];
							$newsubval['pagepath'] = $subv['menu_pagepath'];
							$newsubval['url'] = request()->domain();
						}
						$sub_button[] = $newsubval;
					}
					$newval['sub_button'] = $sub_button;
				}else{
					if($v['menu_type'] == 'click'){
						if($v['message_type']=='news'){
							$newval['type'] = 'media_id';
							$newval['media_id'] = $v['news_content'];
						}elseif($v['message_type']=='image'){
							$newval['type'] = 'media_id';
							$newval['media_id'] = \app\commons\Wechat::getmediaid(aid,$v['image_content']);
						}elseif($v['message_type']=='voice'){
							$newval['type'] = 'media_id';
							$newval['media_id'] = \app\commons\Wechat::getmediaid(aid,$v['voice_content'],'voice');
						}elseif($v['message_type']=='video'){
							$newval['type'] = 'media_id';
							if(!$v['video_title'] || !$v['video_introduction']){
								return json(['status'=>0,'msg'=>'请填写视频标题及描述']);
							}
							$newval['media_id'] = \app\commons\Wechat::getmediaid(aid,$v['video_content'],'video',jsonEncode(['title'=>$v['video_title'],'introduction'=>$v['video_introduction']]));
						}elseif($v['message_type']=='text'){
							$newval['type'] = 'click';
							$newval['key'] = uniqid().'_'.rand(1000,9999);
							Db::name('mp_menukey')->insert(['key'=>$newval['key'],'val'=>$v['text_content']]);
						}
					}elseif($v['menu_type']=='view'){
						$newval['type'] = 'view';
						$newval['url'] = $v['menu_url'];
					}elseif($v['menu_type']=='miniprogram'){
						$newval['type'] = 'miniprogram';
						$newval['appid'] = $v['menu_appid'];
						$newval['pagepath'] = $v['menu_pagepath'];
						$newval['url'] = request()->domain();
					}
				}
				$buttondata[] = $newval;
			}
			$access_token = \app\commons\Wechat::access_token(aid,'mp');
			if(!$buttondata){
				$url = 'https://api.weixin.qq.com/cgi-bin/menu/delete?access_token='.$access_token;
				$rs = request_get($url);
				$rs = json_decode($rs,true);
			}else{
				$url = 'https://api.weixin.qq.com/cgi-bin/menu/create?access_token='.$access_token;
				$rs = request_post($url,jsonEncode(['button'=>$buttondata]));
				$rs = json_decode($rs,true);

				//$url = 'https://api.weixin.qq.com/cgi-bin/menu/addconditional?access_token='.$access_token;
				//$rs = request_post($url,jsonEncode(['button'=>$buttondata,'matchrule'=>['tag_id'=>'100']]));
				//$rs = json_decode($rs,true);
			}
			if($rs['errcode']!=0){
				if($rs['errcode']==40119){
					
				}
				return json(['status'=>0,'msg'=>\app\commons\Wechat::geterror($rs),'buttondata'=>$buttondata,'rs'=>$rs]);
			}else{
				\app\commons\System::plog('公众号菜单发布');
				return json(['status'=>1,'msg'=>'发布成功','url'=>(string)url('index')]);
			}
		}else{
			return json(['status'=>1,'msg'=>'保存成功','url'=>(string)url('index')]);
		}
	}
}