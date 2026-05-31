<?php


// +----------------------------------------------------------------------
// | 授权设置
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Log;
use think\facade\Db;

class Binding extends Common
{
    public function initialize(){
		parent::initialize();
		if(bid > 0) showmsg('无访问权限');
	}
	public function index(){
		$mpappinfo = Db::name('admin_setapp_mp')->where('aid',aid)->find();
		$wxappinfo = Db::name('admin_setapp_wx')->where('aid',aid)->find();
		if($wxappinfo && $wxappinfo['authtype']==1){
			$wxalogList = [];
			//已发布的
			$wxalog1 = Db::name('admin_wxalog')->where('aid',aid)->where('status',3)->order('id desc')->find();
			//审核中 待发布 已驳回
			if($wxalog1){
				$wxalog2 = Db::name('admin_wxalog')->where('aid',aid)->where('createtime','>',$wxalog1['createtime'])->where('status','in','1,2,4')->order('id desc')->find();
			}else{
				$wxalog2 = Db::name('admin_wxalog')->where('aid',aid)->where('status','in','1,2,4')->order('id desc')->find();
			}
			//未提交的
			if($wxalog2){
				$wxalog0 = Db::name('admin_wxalog')->where('aid',aid)->where('createtime','>',$wxalog2['createtime'])->where('status',0)->order('id desc')->find();
			}elseif($wxalog1){
				$wxalog0 = Db::name('admin_wxalog')->where('aid',aid)->where('createtime','>',$wxalog1['createtime'])->where('status',0)->order('id desc')->find();
			}else{
				$wxalog0 = Db::name('admin_wxalog')->where('aid',aid)->where('status',0)->order('id desc')->find();
			}


			if($wxalog0) $wxalog0['createtime'] = $this->dmformatTime($wxalog0['createtime']);
			if($wxalog1) $wxalog1['createtime'] = $this->dmformatTime($wxalog1['createtime']);
			if($wxalog2) $wxalog2['createtime'] = $this->dmformatTime($wxalog2['createtime']);

			if($wxalog0) $wxalogList[] = $wxalog0;
			if($wxalog2) $wxalogList[] = $wxalog2;
			if($wxalog1) $wxalogList[] = $wxalog1;

			//$wxalogList = Db::name('admin_wxalog')->where('aid',aid)->order('id desc')->limit(10)->select()->toArray();
			if(!$wxalogList) $wxalogList = [];
		}
		$componentinfo = Db::name('sysset')->where('name','component')->value('value');
		$componentinfo = json_decode($componentinfo,true);
		View::assign('mpappinfo',$mpappinfo);
		View::assign('wxappinfo',$wxappinfo);
		View::assign('wxalogList',$wxalogList);
		View::assign('componentinfo',$componentinfo);

		$webinfo = Db::name('sysset')->where('name','webinfo')->value('value');
		$webinfo = json_decode($webinfo,true);
		View::assign('webinfo',$webinfo);

		$setmenu = Db::name('designer_menu')->where('aid',aid)->where('platform','wx')->find();
		View::assign('setmenu',$setmenu);

		if(!$wxappinfo || $wxappinfo['appid']==''){
			$wxreglog = Db::name('admin_wxreglog')->where('aid',aid)->order('id desc')->limit(10)->select()->toArray();
		}else{
			$wxreglog = [];
		}
		View::assign('wxreglog',$wxreglog);
        if(getcustom('show_location')){
            $mode = Db::name('admin_set')->where('aid',aid)->value('mode');
            View::assign('mode',$mode??0);
        }
		$version = file_get_contents('version.php');
		if(!$version) $version = '1.0';
		View::assign('version',$version);
		View::assign('desc',cache('wxfabu_desc_'.aid));
		return View::fetch();
	}
	//人性化时间显示
	function dmformatTime($time){
		$rtime = date("m-d H:i",$time);
		$htime = date("H:i",$time);
		$time = time() - $time;
		if ($time < 60){
			$str = '刚刚';
		}elseif($time < 60 * 60){
			$min = floor($time/60);
			$str = $min.'分钟前';
		}elseif($time < 60 * 60 * 24){
			$h = floor($time/(60*60));
			$str = $h.'小时前 '.$htime;
		}elseif($time < 60 * 60 * 24 * 3){
			$d = floor($time/(60*60*24));
			if($d==1){
				$str = '昨天 '.$rtime;
			}else{
				$str = '前天 '.$rtime;
			}
		}else{
			$str = $rtime;
		}
		return $str;
	}
	//手动绑定
	public function sdbangding(){
        if(input('param.type')=='mp'){
			if(input('param.op') == 'setappid'){
				$postinfo = input('post.info/a');
				$data = [];
				$data['appid'] = trim($postinfo['appid']);

                $isbind = Db::name('admin_setapp_mp')->where('aid','<>',aid)->where('appid',$data['appid'])->where('authtype',0)->find();
                if($isbind){
                    return json(['status'=>0,'msg'=>'其他站点已手动绑定此公众号，不可重复手动绑定，可使用授权绑定方式']);
                }

				$data['appsecret'] = trim($postinfo['appsecret']);
				$data['authtype'] = 0;
				$data['level'] = $postinfo['level'];
				$data['nickname'] = trim($postinfo['nickname']);
				$data['headimg'] = trim($postinfo['headimg']);
				$data['qrcode'] = trim($postinfo['qrcode']);
				$data['key'] = trim($postinfo['key']);
				$data['token'] = trim($postinfo['token']);
				Db::name('admin_setapp_mp')->where('aid',aid)->update($data);
				\app\commons\System::plog('绑定公众号');
				$access_token = \app\commons\Wechat::access_token(aid,'mp',false); //检测是否能获取到access_token
				return json(['status'=>1,'msg'=>'保存成功']);
			}

			$info = Db::name('admin_setapp_mp')->where('aid',aid)->find();
			if(!$info){
				Db::name('admin_setapp_mp')->insert(['aid'=>aid,'level'=>4,'token'=>random(16),'key'=>random(43)]);
				$info = Db::name('admin_setapp_mp')->where('aid',aid)->find();
			}
			if(!$info['token'] || !$info['key']){
				Db::name('admin_setapp_mp')->where('aid',aid)->update(['token'=>random(16),'key'=>random(43)]);
				$info = Db::name('admin_setapp_mp')->where('aid',aid)->find();
			}
			View::assign('info',$info);
			return View::fetch('sdbangdingmp');
		}else{
			if(input('param.op') == 'setappid'){
				$postinfo = input('post.info/a');
				$data = [];
				$data['appid'] = trim($postinfo['appid']);

                $isbind = Db::name('admin_setapp_wx')->where('aid','<>',aid)->where('appid',$data['appid'])->where('authtype',0)->find();
                if($isbind){
                    return json(['status'=>0,'msg'=>'其他站点已手动绑定此小程序，不可重复手动绑定，可使用授权绑定方式']);
                }
				$data['appsecret'] = trim($postinfo['appsecret']);
				$data['authtype'] = 0;
				$data['level'] = 1;
				$data['nickname'] = trim($postinfo['nickname']);
				$data['headimg'] = trim($postinfo['headimg']);
				$data['qrcode'] = trim($postinfo['qrcode']);
				$data['key'] = trim($postinfo['key']);
				$data['token'] = trim($postinfo['token']);
				Db::name('admin_setapp_wx')->where('aid',aid)->update($data);
				\app\commons\System::plog('绑定小程序');
				$access_token = \app\commons\Wechat::access_token(aid,'wx',false); //检测是否能获取到access_token
				return json(['status'=>1,'msg'=>'保存成功']);
			}

			$info = Db::name('admin_setapp_wx')->where('aid',aid)->find();
			if(!$info){
				Db::name('admin_setapp_wx')->insert(['aid'=>aid,'level'=>1,'token'=>random(16),'key'=>random(43)]);
				$info = Db::name('admin_setapp_wx')->where('aid',aid)->find();
			}
			if(!$info['token'] || !$info['key']){
				Db::name('admin_setapp_wx')->where('aid',aid)->update(['token'=>random(16),'key'=>random(43)]);
				$info = Db::name('admin_setapp_wx')->where('aid',aid)->find();
			}
			View::assign('info',$info);
			return View::fetch();
		}
	}
	//授权绑定
	public function sqbangding(){
        //获取预授权码
		$url = 'https://api.weixin.qq.com/cgi-bin/component/api_create_preauthcode?component_access_token='.\app\commons\Wechat::component_access_token();
		$data = array();
		$data['component_appid'] = \app\commons\Wechat::component_appid();
		$rs = request_post($url,jsonEncode($data));
		$rs = json_decode($rs,true);
		if(isset($rs['errcode']) && $rs['errcode']!=0){
			die(\app\commons\Wechat::geterror($rs));
		}
		$pre_auth_code = $rs['pre_auth_code'];
		$type = input('param.type');
		if($type=='mp'){
			$auth_type = 1;
		}else{
			$auth_type = 2;
		}
		$authurl = 'https://mp.weixin.qq.com/cgi-bin/componentloginpage?component_appid='.\app\commons\Wechat::component_appid().'&pre_auth_code='.$pre_auth_code.'&auth_type='.$auth_type.'&redirect_uri='.urlencode(PRE_URL.'/?s=/binding/callbackxcx');
		return redirect($authurl);
	}
	//授权回调
	public function callbackxcx(){
		$authorization_code = input('param.auth_code');
		if($authorization_code){
			$rs = \app\commons\Wechat::setauthinfo(aid,$authorization_code);
			if($rs['status']==0) die($rs['msg']);
			return redirect((string)url('index'));
		}
	}

	//刷新授权信息
	public function refreshauthinfo(){
		$apptype = input('param.apptype');
		$appinfo = \app\commons\System::appinfo(aid,$apptype);
		//获取授权方的帐号基本信息
		$url = 'https://api.weixin.qq.com/cgi-bin/component/api_get_authorizer_info?component_access_token='.\app\commons\Wechat::component_access_token();
		$data = array();
		$data['component_appid'] = \app\commons\Wechat::component_appid();
		$data['authorizer_appid'] = $appinfo['appid'];
		$rs = request_post($url,jsonEncode($data));
		$rs = json_decode($rs,true);
		if(isset($rs['errcode']) && $rs['errcode']!=0){
			return json(['status'=>1,'msg'=>\app\commons\Wechat::geterror($rs)]);
		}
		$authorizer_info = $rs['authorizer_info'];
		$appid = $rs['authorization_info']['authorizer_appid'];

		$infodata = array();
		$infodata['aid'] = aid;
		$infodata['authtype'] = 1;
		$infodata['appid'] = $appid;
		$infodata['nickname'] = $authorizer_info['nick_name'];
		$infodata['headimg'] = \app\commons\Pic::tolocal($authorizer_info['head_img']);
		$infodata['qrcode'] = \app\commons\Pic::tolocal($authorizer_info['qrcode_url']);
		if($authorizer_info['MiniProgramInfo']){
			$apptype = 'wx';
			$verify_type_info = $authorizer_info['verify_type_info']['id'];
			if($verify_type_info > -1){
				$infodata['level'] = 1; //已认证
			}else{
				$infodata['level'] = 0; //未认证
			}
			if(Db::name('admin_setapp_wx')->where('aid',aid)->find()){
				Db::name('admin_setapp_wx')->where('aid',aid)->update($infodata);
			}else{
				Db::name('admin_setapp_wx')->insert($infodata);
			}
		}else{
			$apptype = 'mp';
			$service_type_info = $authorizer_info['service_type_info']['id'];
			$verify_type_info = $authorizer_info['verify_type_info']['id'];
			$infodata['level'] = 0;
			if($service_type_info==2){
				if($verify_type_info > -1){
					$infodata['level'] = 4; //认证服务号
				}else{
					$infodata['level'] = 2; //未认证服务号
				}
			}else{
				if($verify_type_info > -1){
					$infodata['level'] = 3; //认证订阅号
				}else{
					$infodata['level'] = 1; //未认证订阅号
				}
			}
			if(Db::name('admin_setapp_mp')->where('aid',aid)->find()){
				Db::name('admin_setapp_mp')->where('aid',aid)->update($infodata);
			}else{
				Db::name('admin_setapp_mp')->insert($infodata);
			}
			Db::name('mp_material')->where('aid',aid)->delete();
		}
		$access_token = \app\commons\Wechat::access_token(aid,$apptype);
		//获取开放平台账号appid
		$url = 'https://api.weixin.qq.com/cgi-bin/open/get?access_token='.$access_token;
		$postdata = array();
		$postdata['appid'] = $appid;
		$rs = request_post($url,jsonEncode($postdata));
		$rs = json_decode($rs,true);
		if($rs && $rs['open_appid']){
			$open_appid = $rs['open_appid'];
		}elseif($rs['errcode']==0 || $rs['errcode']==89002){
			$open_appid = null;
		}else{
			$open_appid = '-1'; //没有权限 获取不到
		}
		if($apptype == 'wx'){
			Db::name('admin_setapp_wx')->where('aid',aid)->update(['open_appid'=>$open_appid]);
		}else{
			Db::name('admin_setapp_mp')->where('aid',aid)->update(['open_appid'=>$open_appid]);
		}
		return json(['status'=>1,'msg'=>'刷新成功','url'=>(string)url('index')]);
	}
	//绑定体验者账号
    public function bind_tester(){
		$data = ['wechatid'=>input('param.wechatid')];
		$rs = request_post('https://api.weixin.qq.com/wxa/bind_tester?access_token='.\app\commons\Wechat::access_token(aid,'wx'),jsonEncode($data));
		$rs = json_decode($rs,true);
		if(isset($rs['errcode']) && $rs['errcode']!=0){
			return json(['status'=>0,'msg'=>\app\commons\Wechat::geterror($rs)]);
		}else{
			return json(['status'=>1,'msg'=>'添加成功']);
		}
	}
	//授权上传小程序代码
	public function commit(){
		$sysset = Db::name('admin_set')->where('aid',aid)->find();
		$wxappinfo = Db::name('admin_setapp_wx')->where('aid',aid)->find();
		$version = input('post.version');
		$desc = input('post.desc');
		$homeNavigationCustom = input('post.homeNavigationCustom');
		$usercenterNavigationCustom = input('post.usercenterNavigationCustom');
        $hideHomeButton = input('post.hideHomeButton')?:0;
        $businessindexNavigationCustom = input('post.businessindexNavigationCustom',0);
        cache('wxfabu_version_'.aid,$version);
        cache('wxfabu_desc_'.aid,$desc);
        cache('wxfabu_homeNavigationCustom_'.aid,$homeNavigationCustom);
        cache('wxfabu_usercenterNavigationCustom_'.aid,$usercenterNavigationCustom);
        cache('wxfabu_businessindexNavigationCustom_'.aid,$businessindexNavigationCustom);
		if(!$version) $version = '1.0';
		$navigationBarBackgroundColor = input('post.navigationBarBackgroundColor');
		$navigationBarTextStyle = input('post.navigationBarTextStyle');

        if($navigationBarBackgroundColor){
            if(!$this->isValidColor($navigationBarBackgroundColor)){
                return json(['status'=>0,'msg'=>'顶部导航背景颜色格式错误']);
            }
        }

		$menuset = Db::name('designer_menu')->where('aid',aid)->where('platform','wx')->find();
		//$daima = Db::name('daima_list')->where('aid',aid)->order('id desc')->find();
		//if(!$daima) $daima = Db::name('daima_list')->where('aid',1)->order('id desc')->find();

		//获取模板
		$url = 'https://api.weixin.qq.com/wxa/gettemplatelist?access_token='.\app\commons\Wechat::component_access_token();
		$rs = request_get($url);
		$rs = json_decode($rs,true);
		if($rs['errcode']!=0){
			return json(['status'=>0,'msg'=>\app\commons\Wechat::geterror($rs)]);
		}
		if(!$rs['template_list']){
			return json(['status'=>0,'msg'=>'请先在控制台[开放平台设置]中添加开发者小程序模板']);
		}
		//$template_data = end($rs['template_list']);
		//$templateid = $template_data['template_id'];
		$template_list = $rs['template_list'];
		$createtimes = array_column($template_list,'template_id');
		array_multisort($createtimes,SORT_DESC,$template_list);
		$templateid = $template_list[0]['template_id'];

		$ext_json = array();
		$ext_json['extAppid'] = $wxappinfo['appid'];
        $ext_json['__usePrivacyCheck__'] = true;
		$ext_json['ext'] = array(
			'aid'=>aid,
			'baseurl'=>str_replace('http://','https://',PRE_URL),
			'homeNavigationCustom'=>$homeNavigationCustom,
			'usercenterNavigationCustom'=>$usercenterNavigationCustom,
			'businessindexNavigationCustom'=>$businessindexNavigationCustom,
			"navigationBarBackgroundColor"=>$navigationBarBackgroundColor,
			"navigationBarTextStyle"=>$navigationBarTextStyle,
            "hideHomeButton"=>$hideHomeButton,
		);
		$ext_json['window'] = array(
			"navigationBarBackgroundColor"=>$navigationBarBackgroundColor,
			"navigationBarTextStyle"=>$navigationBarTextStyle,
			"navigationBarTitleText"=>$wxappinfo['nickname'],
			"backgroundColor"=>"#f8f8f8",
			"backgroundTextStyle"=>"dark",
			"enablePullDownRefresh"=>true
		);
		if(input('post.getLocationUse') == 1){
			$ext_json['requiredPrivateInfos'] = ['chooseAddress','chooseLocation','getLocation'];
		}else{
			$ext_json['requiredPrivateInfos'] = ['chooseAddress','chooseLocation'];
		}
		if($homeNavigationCustom != 0 || $usercenterNavigationCustom != 0 || $businessindexNavigationCustom != 0){
			$ext_json['extPages'] = [];
			if($homeNavigationCustom != 0){
				$ext_json['extPages']['pages/index/index'] = ['navigationStyle'=>'custom'];
			}
			if($usercenterNavigationCustom != 0){
				$ext_json['extPages']['pages/my/usercenter'] = ['navigationStyle'=>'custom'];
			}
            if(in_array($homeNavigationCustom,[4,6,8]) || $businessindexNavigationCustom != 0){
                $ext_json['extPages']['pagesExt/business/index'] = ['navigationStyle'=>'custom'];
            }
		}
		//$menudata = json_decode($menuset['menudata'],true);
		$appjson = file_get_contents(ROOT_PATH.'wxapp/app.json');
		$appjsonArr = json_decode($appjson,true);
		//$pages = $appjsonArr['pages'];
		//$newpages = array();
		//$indexpage = ltrim($menuset['indexurl'],'/');
		//$newpages[] = $indexpage;
		//foreach($pages as $v){
		//	if($v != $indexpage){
		//		$newpages[] = $v;
		//	}
		//}
		//$ext_json['pages'] = $newpages;
        $subPackages = $this->addSubpackagesPages($appjsonArr);
        if($subPackages){
            $ext_json['subPackages'] = $subPackages;
        }
		$access_token = \app\commons\Wechat::access_token(aid,'wx');

		//是否开通直播
		$url = 'https://api.weixin.qq.com/wxa/business/getliveinfo?access_token='.$access_token;
		$rs = request_post($url,jsonEncode(['start'=>0,'limit'=>1]));
		$rs = json_decode($rs,true);
		if(isset($rs['errcode']) && $rs['errcode']!=0 && $rs['errcode']!=1 && $rs['errcode']!=9410000){//未开通

		}else{
			$ext_json['plugins'] = ["live-player-plugin"=>["version"=>"1.3.4","provider"=>"wx2b03c6e691cd7370"]];
		}
		//dump($rs);
		//dump($ext_json);die;
		$ext_json = str_replace('\\/','/',jsonEncode($ext_json));
		//获取模板
		//$url = 'https://api.weixin.qq.com/wxa/gettemplatelist?access_token='.\app\commons\Wechat::component_access_token();
		//$rs = request_get($url);
		//$rs = json_decode($rs,true);
		//if(isset($rs['errcode']) && $rs['errcode']!=0){
		//	return json(['status'=>0,'msg'=>\app\commons\Wechat::geterror($rs)]);
		//}
		//$template_data = end($rs['template_list']);
		//$templateid = $template_data['template_id'];
		//dump($templateid);die;

		$url = 'https://api.weixin.qq.com/wxa/commit?access_token='.$access_token;
		$postdata = array();
		$postdata['template_id'] = $templateid;
		$postdata['ext_json'] = $ext_json;
		$postdata['user_version'] = $version;
		$postdata['user_desc'] = $desc;
		$rs = request_post($url,jsonEncode($postdata));
		$rs = json_decode($rs,true);
		if(isset($rs['errcode']) && $rs['errcode']!=0){
			return json(['status'=>0,'msg'=>\app\commons\Wechat::geterror($rs)]);
		}else{
			Db::name('admin_wxalog')->insert(['aid'=>aid,'createtime'=>time(),'user_version'=>$postdata['user_version'],'user_desc'=>$postdata['user_desc']]);
			\app\commons\System::plog('上传小程序代码');
			return json(['status'=>1,'msg'=>'上传成功','url'=>(string)url('index')]);
		}
	}
    protected function addSubpackagesPages($appjsonArr){
        $subPackages = $appjsonArr['subPackages']??[];
        return $subPackages;
    }
	//扫码上传 线传
	public function xianchuan(){
		if(request()->isAjax()){
			$version = input('post.version');
			$desc = input('post.desc');
			$homeNavigationCustom = input('post.homeNavigationCustom');
            $hideHomeButton = input('post.hideHomeButton')?:0;
			$usercenterNavigationCustom = input('post.usercenterNavigationCustom',0);
            $businessindexNavigationCustom = input('post.businessindexNavigationCustom',0);
            cache('wxfabu_version_'.aid,$version);
            cache('wxfabu_desc_'.aid,$desc);
            cache('wxfabu_homeNavigationCustom_'.aid,$homeNavigationCustom);
            cache('wxfabu_usercenterNavigationCustom_'.aid,$usercenterNavigationCustom);
            cache('wxfabu_businessindexNavigationCustom_'.aid,$businessindexNavigationCustom);
			if(!$version) $version = '1.0';
			$navigationBarBackgroundColor = input('post.navigationBarBackgroundColor');
			$navigationBarTextStyle = input('post.navigationBarTextStyle');

            if($navigationBarBackgroundColor){
                if(!$this->isValidColor($navigationBarBackgroundColor)){
                    return json(['status'=>0,'msg'=>'顶部导航背景颜色格式错误']);
                }
            }

            $config = include(ROOT_PATH.'config.php');
            $authkey = $config['authkey'];
			if(input('post.op') == 'getloginqr'){
				$time = time();
				$token = md5('zxc156wegd5gsd1!!--xx' . $time);

				$appinfo = \app\commons\System::appinfo(aid,'wx');
				$appid = $appinfo['appid'];
				$menuset = Db::name('designer_menu')->where('aid',aid)->where('platform','wx')->find();
				$set = Db::name('admin_set')->where('aid',aid)->find();
				//是否开通直播
				$url = 'https://api.weixin.qq.com/wxa/business/getliveinfo?access_token='.\app\commons\Wechat::access_token(aid,'wx');
				$rs = request_post($url,jsonEncode(['start'=>0,'limit'=>1]));
				$rs = json_decode($rs,true);
				if(isset($rs['errcode']) && $rs['errcode']!=0 && $rs['errcode']!=1 && $rs['errcode']!=9410000){//未开通
					$plugins = [];
				}else{
					$plugins = ['live-player-plugin'=>["version"=>"1.3.4","provider"=>"wx2b03c6e691cd7370"]];
				}
				$window = array(
					"navigationBarBackgroundColor"=>$navigationBarBackgroundColor,
					"navigationBarTextStyle"=>$navigationBarTextStyle,
					"navigationBarTitleText"=> $appinfo['nickname'],
					"backgroundColor"=>"#f8f8f8",
					"backgroundTextStyle"=>"dark",
					"enablePullDownRefresh"=>true
				);

				$postdata = [];
				$postdata['appid'] = $appid;
				$postdata['nickname'] = $appinfo['nickname'];
				$postdata['verson'] = $version;
				$postdata['desc'] = $desc;
				$postdata['uniacid'] = aid;
				$postdata['domain'] = PRE_URL2;
				$postdata['domain2'] = str_replace('http://','https://',request()->domain());
				$postdata['verson'] = $version;
				$postdata['window'] = jsonEncode($window);
				$postdata['plugins'] = jsonEncode($plugins);

                $postdata['indexurl'] = 'pages/index/index';
				$postdata['custom'] = jsonEncode(getcustom());
				$postdata['homeNavigationCustom'] = $homeNavigationCustom;
				$postdata['usercenterNavigationCustom'] = $usercenterNavigationCustom;
				$postdata['businessindexNavigationCustom'] = $businessindexNavigationCustom;
				$postdata['domain'] = PRE_URL2;
                $postdata['hideHomeButton'] = $hideHomeButton;
				if(input('post.getLocationReplace') == 1){
					$postdata['getLocationReplace'] = 1;
				}else{
					$postdata['getLocationReplace'] = 0;
				}
				$moduleversion = file_get_contents(ROOT_PATH.'version.php');

				$url = 'http:///index/index/shop?op=login&aid='.aid.'&time='.$time.'&authkey='.$authkey.'&token='.$token.'&appid='.$appid.'&moduleversion='.$moduleversion;
				$rs = request_post($url,$postdata,120);
				//dump($rs);
				$rs = json_decode($rs,true);
				if ($rs['status'] == 1) {
                    return json($rs);
                } else {
                    return json(['status'=>0,'msg'=>$rs['message'],'rs'=>$rs]);
                }
			}
			if(input('post.op') == 'upload'){
				$time = time();
				$token = md5('zxc156wegd5gsd1!!--xx' . $time);
				$appinfo = \app\commons\System::appinfo(aid,'wx');
				$appid = $appinfo['appid'];

				$url = 'http:///index/index/shop?op=upload&aid='.aid.'&time='.$time.'&authkey='.$authkey.'&token='.$token.'&appid='.$appid;
				$postdata = [];
				$postdata['version'] = $version;
				$postdata['desc'] = $desc;
				$rs = curl_post($url,$postdata,0,[],120);
				$rs = json_decode($rs,true);
				if($rs['info']){
					\app\commons\System::plog('扫码上传小程序代码');
					return json(['status'=>1,'msg'=>'上传成功']);
				}else{
					if(strpos($rs['message'],'需要重新登录')){
						return json(['status'=>2,'msg'=>$rs]);
					}
					return json(['status'=>0,'msg'=>$rs['message'],'rs'=>$rs]);
				}
				return $rs;
			}
			if(input('post.op') == 'preview'){
				$time = time();
				$token = md5('zxc156wegd5gsd1!!--xx' . $time);
				$appinfo = \app\commons\System::appinfo(aid,'wx');
				$appid = $appinfo['appid'];
				$url = 'http:///index/index/shop?op=preview&aid='.aid.'&time='.$time.'&token='.$token.'&authkey='.$authkey.'&appid='.$appid;
				$rs = curl_get($url,[],[],120);
				$rs = json_decode($rs,true);
				return json($rs);
			}
		}

		$setmenu = Db::name('designer_menu')->where('aid',aid)->where('platform','wx')->find();
		View::assign('setmenu',$setmenu);
        $sysset = Db::name('sysset')->where('name','component')->find();
        if($sysset){
            $sysset = json_decode($sysset['value'],true);
            View::assign('sysset',$sysset);
        }
        if(getcustom('show_location')){
            $mode = Db::name('admin_set')->where('aid',aid)->value('mode');
            View::assign('mode',$mode??0);
        }

		//$version = cache('wxfabu_version_'.aid);
		$version = file_get_contents('version.php');
		if(!$version) $version = '1.0';
		View::assign('version',$version);
		View::assign('desc',cache('wxfabu_desc_'.aid));
		return View::fetch();
	}
	//手动上传小程序代码
	public function downloadxcx(){
		if($_SERVER['HTTP_HOST'] == 'v2.diandashop.com'){
			//return json(['status'=>0,'msg'=>'演示站无下载权限']);
		}
		$sysset = Db::name('admin_set')->where('aid',aid)->find();
		$wxapp = Db::name('admin_setapp_wx')->where('aid',aid)->find();
		$menuset = Db::name('designer_menu')->where('aid',aid)->where('platform','wx')->find();

		$navigationBarBackgroundColor = input('post.navigationBarBackgroundColor');
		$navigationBarTextStyle = input('post.navigationBarTextStyle');

        if($navigationBarBackgroundColor){
            if(!$this->isValidColor($navigationBarBackgroundColor)){
                return json(['status'=>0,'msg'=>'顶部导航背景颜色格式错误']);
            }
        }

		$homeNavigationCustom = input('post.homeNavigationCustom');
		$usercenterNavigationCustom = input('post.usercenterNavigationCustom');
		$businessindexNavigationCustom = input('post.businessindexNavigationCustom',0);

		$hideHomeButton = input('post.hideHomeButton')?:0;

		//if(!$wxapp['appid'] || !$wxapp['appsecret']){
		//	return json(['status'=>0,'msg'=>'请先设置小程序AppID和AppSecret']);
		//}
        //import('file',EXTEND_PATH);
		$wxdir = ROOT_PATH.'mp-weixin';
		$copydir = ROOT_PATH.'upload/temp/wx_'.aid;
		\app\commons\File::clear_dir($copydir);
		\app\commons\File::all_copy($wxdir,$copydir);

		//配置文件 app.json
		$appconfig = array();
		$window = array(
			"navigationBarBackgroundColor"=>$navigationBarBackgroundColor,
			"navigationBarTextStyle"=>$navigationBarTextStyle,
			"navigationBarTitleText"=> $wxapp['nickname'],
			"backgroundColor"=>"#f8f8f8",
			"backgroundTextStyle"=>"dark",
			"enablePullDownRefresh"=>true
		);
		$appconfig['window'] = $window;
        $appconfig['__usePrivacyCheck__'] = true;

		//页面 pages
		$appjson = file_get_contents(ROOT_PATH.'mp-weixin/app.json');
		$appjsonArr = json_decode($appjson,true);
		$pages = $appjsonArr['pages'];
		$newpages = array();
		$indexpage = ltrim($menuset['indexurl'],'/');
		$newpages[] = $indexpage;
		foreach($pages as $v){
			if($v != $indexpage){
				$newpages[] = $v;
			}
		}
		$appconfig['pages'] = $newpages;
        $subpackages = $this->addSubpackagesPages($appjsonArr);
//        $subpackages = $appjsonArr['subPackages'];
        //页面
        if($subpackages){
            $appconfig['subPackages'] = $subpackages;
        }
		/*
		//底部导航
		$menudata = json_decode($menuset['menudata'],true);
		$tabBarUrls = [];
		if($menuset['menucount'] > 0){
			$tabBarData = ['color'=>$menudata['color'],'selectedColor'=>$menudata['selectedColor'],'backgroundColor'=>$menudata['backgroundColor'],'borderStyle'=>$menudata['borderStyle'],'position'=>$menudata['position'],'list'=>[]];
			foreach($menudata['list'] as $k=>$v){
				if($k < $menuset['menucount']){
					file_put_contents($copydir.'/images/tabbar_'.basename($v['iconPath']),file_get_contents($v['iconPath']));
					$v['iconPath'] = '/images/tabbar_'.basename($v['iconPath']);
					file_put_contents($copydir.'/images/tabbar_'.basename($v['selectedIconPath']),file_get_contents($v['selectedIconPath']));
					$v['selectedIconPath'] = '/images/tabbar_'.basename($v['selectedIconPath']);
					$tabBarData['list'][] = ['pagePath'=>ltrim($v['pagePath'],'/'),'text'=>$v['text'],'iconPath'=>$v['iconPath'],'selectedIconPath'=>$v['selectedIconPath']];
					$tabBarUrls[] = $v['pagePath'];
				}
			}
			$appconfig['tabBar'] = $tabBarData;
		}else{
			$appconfig['tabBar'] = array();
		}
		*/
		//是否开通直播
		$url = 'https://api.weixin.qq.com/wxa/business/getliveinfo?access_token='.\app\commons\Wechat::access_token(aid,'wx');
		$rs = request_post($url,jsonEncode(['start'=>0,'limit'=>1]));
		$rs = json_decode($rs,true);
		if(isset($rs['errcode']) && $rs['errcode']!=0 && $rs['errcode']!=1 && $rs['errcode']!=9410000){//未开通

		}else{
			$appconfig['plugins'] = ['live-player-plugin'=>["version"=>"1.3.4","provider"=>"wx2b03c6e691cd7370"]];
		}
		//地理位置
		$appconfig['permission'] = ["scope.userLocation"=>["desc"=>"你的位置信息将用于获取距离信息"]];
		
		if(input('post.getLocationReplace') == 1){
			$appconfig['requiredPrivateInfos'] = ['chooseAddress','chooseLocation'];
		}else{
			$appconfig['requiredPrivateInfos'] = ['chooseAddress','chooseLocation','getLocation'];
		}

		$appconfigStr = str_replace('\\/','/',jsonEncode($appconfig));
		file_put_contents($copydir.'/app.json',$appconfigStr);

		//配置信息
		$uniacid = aid;
		$siteroot = PRE_URL2;
		$vendorjscontent = file_get_contents($copydir.'/common/vendor.js');
		$vendorjscontent = preg_replace_callback('/uniacid\:\"[0-9]*\",siteroot\:\"[^"]*\"/',function($matches)use($uniacid,$siteroot,$homeNavigationCustom,$usercenterNavigationCustom,$navigationBarBackgroundColor,$navigationBarTextStyle,$hideHomeButton,$businessindexNavigationCustom){
			return 'uniacid:"'.$uniacid.'",siteroot:"'.$siteroot.'",homeNavigationCustom:"'.$homeNavigationCustom.'",hideHomeButton:"'.$hideHomeButton.'",usercenterNavigationCustom:"'.$usercenterNavigationCustom.'",navigationBarBackgroundColor:"'.$navigationBarBackgroundColor.'",navigationBarTextStyle:"'.$navigationBarTextStyle.'",businessindexNavigationCustom:"'.$businessindexNavigationCustom.'"';
		},$vendorjscontent);
		//$vendorjscontent = str_replace('{uniacid:"1",siteroot:"https://v2.diandashop.com"}','{uniacid:"'.$uniacid.'",siteroot:"'.$siteroot.'"}',$vendorjscontent);
		file_put_contents($copydir.'/common/vendor.js',$vendorjscontent);

		if(input('post.getLocationReplace') == 1){
			$mainjscontent = file_get_contents($copydir.'/common/main.js');
			$mainjscontent = str_replace('.getLocation({','.chooseLocation({',$mainjscontent);
			file_put_contents($copydir.'/common/main.js',$mainjscontent);
		}

		$projectjson = '{
			"setting": {
				"urlCheck": true
			},
			"compileType": "miniprogram",
			"appid": "'.$wxapp['appid'].'",
			"projectname": "'.$wxapp['nickname'].'"
		}';
		file_put_contents($copydir.'/project.config.json',$projectjson);

		if($homeNavigationCustom != 0){
			$indexjson = file_get_contents($copydir.'/pages/index/index.json');
			$indexjson = json_decode($indexjson,true);
			$indexjson['navigationStyle'] = 'custom';
			$indexjson = jsonEncode($indexjson);
			file_put_contents($copydir.'/pages/index/index.json',$indexjson);

            if(in_array($homeNavigationCustom,[4,6,8]) || $businessindexNavigationCustom!=0){
                $businessIndexjson = file_get_contents($copydir.'/pagesExt/business/index.json');
                $businessIndexjson = json_decode($businessIndexjson,true);
                $businessIndexjson['navigationStyle'] = 'custom';
                $businessIndexjson = jsonEncode($businessIndexjson);
                file_put_contents($copydir.'/pagesExt/business/index.json',$businessIndexjson);
            }
		}
		if($usercenterNavigationCustom != 0){
			$usercenterjson = file_get_contents($copydir.'/pages/my/usercenter.json');
			$usercenterjson = json_decode($usercenterjson,true);
			$usercenterjson['navigationStyle'] = 'custom';
			$usercenterjson = jsonEncode($usercenterjson);
			file_put_contents($copydir.'/pages/my/usercenter.json',$usercenterjson);
		}


		$zipname = uniqid().'.zip';
		$zippath = ROOT_PATH.'upload/temp/'.$zipname;
		$myfile = fopen($zippath, "w");
		fclose($myfile);
		\app\commons\File::add_file_to_zip($copydir,$zippath,'wx_'.aid);
		$url = PRE_URL.'/upload/temp/'.$zipname;
		\app\commons\File::remove_dir($copydir);
		\app\commons\System::plog('下载小程序代码');
		return json(['status'=>1,'msg'=>'打包成功','url'=>$url]);
	}

	//复用公众号主体快速注册小程序
	public function fastregxcx(){
		$mpappinfo = Db::name('admin_setapp_mp')->where('aid',aid)->find();
		if(!$mpappinfo || !$mpappinfo['appid']){
			return json(['status'=>0,'msg'=>'请先授权绑定公众号']);
		}
		if($mpappinfo['authtype']==0){
			return json(['status'=>0,'msg'=>'手动绑定的公众号无法使用该功能，请前往公众号进行快速注册小程序']);
		}
		if($mpappinfo['level']==1 || $mpappinfo['level']==2){
			return json(['status'=>0,'msg'=>'未认证的公众号无法使用该功能，请前往公众平台进行注册']);
		}
		$url = 'https://mp.weixin.qq.com/cgi-bin/fastregisterauth?component_appid='.\app\commons\Wechat::component_appid().'&appid='.$mpappinfo['appid'].'&copy_wx_verify=1&redirect_uri='.urlencode(PRE_URL.'/?s=Binding/fastregcallback');
		return json(['status'=>1,'url'=>$url]);
	}
	public function fastregcallback(){
		$ticket = input('param.ticket');
		//Log::write($ticket);
		$url = 'https://api.weixin.qq.com/cgi-bin/account/fastregister?access_token='.\app\commons\Wechat::access_token(aid,'mp');
		$data = array();
		$data['ticket'] = $ticket;
		$rs = curl_post($url,jsonEncode($data));
		$rs = json_decode($rs,true);
		if(isset($rs['errcode']) && $rs['errcode']!=0){
			die(\app\commons\Wechat::geterror($rs));
		}
		$appid = $rs['appid'];
		$authorization_code = $rs['authorization_code'];

		$rs = \app\commons\Wechat::setauthinfo(aid,$authorization_code,1);
		if($rs['status']==0) die($rs['msg']);
		return redirect((string)url('index'));
	}
	//快速注册小程序
	public function submit_reg(){
		//dump($_POST);
		$postdata = array();
		$postdata['name'] = input('post.reg_name');
		$postdata['code'] = input('post.reg_code');
		$postdata['code_type'] = input('post.reg_code_type');
		$postdata['legal_persona_wechat'] = input('post.reg_legal_persona_wechat');
		$postdata['legal_persona_name'] = input('post.reg_legal_persona_name');
		$postdata['component_phone'] = '';
		if(!$postdata['name']){
			return json(['status'=>0,'msg'=>'请填写企业名称']);
		}
		if(!$postdata['code']){
			return json(['status'=>0,'msg'=>'请填写企业代码']);
		}
		if(!$postdata['code_type']){
			return json(['status'=>0,'msg'=>'请选择企业代码类型']);
		}
		if(!$postdata['legal_persona_wechat']){
			return json(['status'=>0,'msg'=>'请填写法人微信号']);
		}
		if(!$postdata['legal_persona_name']){
			return json(['status'=>0,'msg'=>'请填写法人姓名']);
		}
		$url = 'https://api.weixin.qq.com/cgi-bin/component/fastregisterweapp?action=create&component_access_token='.\app\commons\Wechat::component_access_token();
		$rs = curl_post($url,jsonEncode($postdata));
		$rs = json_decode($rs,true);
		//dump($rs);
		if(isset($rs['errcode']) && $rs['errcode']!=0){
			return json(['status'=>0,'msg'=>\app\commons\Wechat::geterror($rs),'data'=>$postdata]);
		}else{
			Db::name('admin_wxreglog')->insert(array(
				'aid'=>aid,
				'createtime'=>time(),
				'name'=>$postdata['name'],
				'code'=>$postdata['code'],
				'code_type'=>$postdata['code_type'],
				'legal_persona_wechat'=>$postdata['legal_persona_wechat'],
				'legal_persona_name'=>$postdata['legal_persona_name'],
			));
			return json(['status'=>1,'msg'=>'提交成功,审核通过后将在微信上接收到消息通知,按照消息指引完成注册','url'=>(string)url('index')]);
		}
	}
	//查询状态
	public function get_regstatus(){
		$info = Db::name('admin_wxreglog')->where('aid',aid)->where('id',input('param.id/d'))->order('id desc')->find();
		if(!$info) return json(['status'=>0,'msg'=>'未找到申请单']);
		$url = 'https://api.weixin.qq.com/cgi-bin/component/fastregisterweapp?action=search&component_access_token='.\app\commons\Wechat::component_access_token();
		$postdata = array();
		$postdata['name'] = $info['name'];
		$postdata['legal_persona_wechat'] = $info['legal_persona_wechat'];
		$postdata['legal_persona_name'] = $info['legal_persona_name'];
		$rs = curl_post($url,jsonEncode($postdata));
		$rs = json_decode($rs,true);
		//dump($rs);
		if(isset($rs['errcode']) && $rs['errcode']==0){
			Db::name('admin_wxreglog')->where('aid',aid)->where('id',input('param.id/d'))->update(['status'=>1,'reason'=>'']);
		}
		$msg = '刷新成功';
		if($rs['errcode'] == 89252){
			$msg = '法人&企业信息一致性校验中';
		}
		if($rs['errcode'] == 89251){
			$msg = '模板消息已下发，待法人人脸核身校验';
		}
		if($rs['errcode'] == 89250){
			//$msg = '未找到该任务';
		}
		return json(['status'=>1,'msg'=>$msg,'url'=>(string)url('index'),'rs'=>$rs]);
	}




	//手动上传百度小程序代码
	public function downloadbaiduxcx(){
		if($_SERVER['HTTP_HOST'] == 'v2.diandashop.com'){
			//return json(['status'=>0,'msg'=>'演示站无下载权限']);
		}
		$navigationBarBackgroundColor = input('post.navigationBarBackgroundColor');
		$navigationBarTextStyle = input('post.navigationBarTextStyle');
		$sysset = Db::name('admin_set')->where('aid',aid)->find();
		$baiduapp = Db::name('admin_setapp_baidu')->where('aid',aid)->find();
		$menuset = Db::name('designer_menu')->where('aid',aid)->where('platform','wx')->find();

		//if(!$wxapp['appid'] || !$wxapp['appsecret']){
		//	return json(['status'=>0,'msg'=>'请先设置小程序AppID和AppSecret']);
		//}
        //import('file',EXTEND_PATH);
		$wxdir = ROOT_PATH.'mp-baidu';
		$copydir = ROOT_PATH.'upload/temp/baidu_'.aid;
		\app\commons\File::clear_dir($copydir);
		\app\commons\File::all_copy($wxdir,$copydir);

		//配置文件 app.json
		$appconfig = array();
		$window = array(
			"navigationBarBackgroundColor"=>$navigationBarBackgroundColor,
			"navigationBarTextStyle"=>$navigationBarTextStyle,
			"navigationBarTitleText"=> $sysset['name'],
			"backgroundColor"=>"#f8f8f8",
			"backgroundTextStyle"=>"dark",
			"enablePullDownRefresh"=>true
		);
		$appconfig['window'] = $window;

		//页面 pages
		$appjson = file_get_contents(ROOT_PATH.'mp-baidu/app.json');
		$appjsonArr = json_decode($appjson,true);
		$pages = $appjsonArr['pages'];
		$newpages = array();
		$indexpage = ltrim($menuset['indexurl'],'/');
		$newpages[] = $indexpage;
		foreach($pages as $v){
			if($v != $indexpage){
				$newpages[] = $v;
			}
		}
		$appconfig['pages'] = $newpages;
		$subpackages = $appjsonArr['subPackages'];
		//页面
		if($subpackages){
			$appconfig['subPackages'] = $subpackages;
		}
		/*
		//底部导航
		$menudata = json_decode($menuset['menudata'],true);
		$tabBarUrls = [];
		if($menuset['menucount'] > 0){
			$tabBarData = ['color'=>$menudata['color'],'selectedColor'=>$menudata['selectedColor'],'backgroundColor'=>$menudata['backgroundColor'],'borderStyle'=>$menudata['borderStyle'],'position'=>$menudata['position'],'list'=>[]];
			foreach($menudata['list'] as $k=>$v){
				if($k < $menuset['menucount']){
					file_put_contents($copydir.'/images/tabbar_'.basename($v['iconPath']),file_get_contents($v['iconPath']));
					$v['iconPath'] = '/images/tabbar_'.basename($v['iconPath']);
					file_put_contents($copydir.'/images/tabbar_'.basename($v['selectedIconPath']),file_get_contents($v['selectedIconPath']));
					$v['selectedIconPath'] = '/images/tabbar_'.basename($v['selectedIconPath']);
					$tabBarData['list'][] = ['pagePath'=>ltrim($v['pagePath'],'/'),'text'=>$v['text'],'iconPath'=>$v['iconPath'],'selectedIconPath'=>$v['selectedIconPath']];
					$tabBarUrls[] = $v['pagePath'];
				}
			}
			$appconfig['tabBar'] = $tabBarData;
		}else{
			$appconfig['tabBar'] = array();
		}
		*/

		//地理位置
		$appconfig['permission'] = ["scope.userLocation"=>["desc"=>"你的位置信息将用于获取距离信息"]];

		$appconfigStr = str_replace('\\/','/',jsonEncode($appconfig));
		file_put_contents($copydir.'/app.json',$appconfigStr);

		//配置信息
		$uniacid = aid;
		$siteroot = PRE_URL2;
		$vendorjscontent = file_get_contents($copydir.'/common/vendor.js');
		$vendorjscontent = preg_replace_callback('/uniacid\:\"[0-9]*\",siteroot\:\"[^"]*\"/',function($matches)use($uniacid,$siteroot){
			return 'uniacid:"'.$uniacid.'",siteroot:"'.$siteroot.'"';
		},$vendorjscontent);
		//$vendorjscontent = str_replace('{uniacid:"1",siteroot:"https://v2.diandashop.com"}','{uniacid:"'.$uniacid.'",siteroot:"'.$siteroot.'"}',$vendorjscontent);
		file_put_contents($copydir.'/common/vendor.js',$vendorjscontent);

		$projectjson = '{
		  "appid": "'.$baiduapp['appid'].'",
		  "appInfo": {},
		  "appkey": "",
		  "condition": {},
		  "setting": {
			"urlCheck": true
		  },
		  "libVersion": "",
		  "projectname": "'.$baiduapp['nickname'].'"
		}';
		file_put_contents($copydir.'/project.swan.json',$projectjson);
		$zipname = uniqid().'.zip';
		$zippath = ROOT_PATH.'upload/temp/'.$zipname;
		$myfile = fopen($zippath, "w");
		fclose($myfile);
		\app\commons\File::add_file_to_zip($copydir,$zippath,'baidu_'.aid);
		$url = PRE_URL.'/upload/temp/'.$zipname;
		\app\commons\File::remove_dir($copydir);
		\app\commons\System::plog('下载小程序代码');
		return json(['status'=>1,'msg'=>'打包成功','url'=>$url]);
	}
	//手动上传支付宝小程序代码
	public function downloadalipayxcx(){
		if($_SERVER['HTTP_HOST'] == 'v2.diandashop.com'){
			//return json(['status'=>0,'msg'=>'演示站无下载权限']);
		}
		$navigationBarBackgroundColor = input('post.navigationBarBackgroundColor');
		$navigationBarTextStyle = input('post.navigationBarTextStyle');
		$sysset = Db::name('admin_set')->where('aid',aid)->find();
		$alipayapp = Db::name('admin_setapp_alipay')->where('aid',aid)->find();
		$menuset = Db::name('designer_menu')->where('aid',aid)->where('platform','alipay')->find();

		//if(!$wxapp['appid'] || !$wxapp['appsecret']){
		//	return json(['status'=>0,'msg'=>'请先设置小程序AppID和AppSecret']);
		//}
        //import('file',EXTEND_PATH);
		$wxdir = ROOT_PATH.'mp-alipay';
		$copydir = ROOT_PATH.'upload/temp/alipay_'.aid;
		\app\commons\File::clear_dir($copydir);
		\app\commons\File::all_copy($wxdir,$copydir);

		//配置文件 app.json
		$appconfig = array();
		$window = array(
			"titleBarColor"=>$navigationBarBackgroundColor,
			"navigationBarTextStyle"=>$navigationBarTextStyle,
			"defaultTitle"=> $alipayapp['nickname'],
			"backgroundColor"=>"#f8f8f8",
			"backgroundTextStyle"=>"dark",
			"enablePullDownRefresh"=>true
		);
		$appconfig['window'] = $window;

		//页面 pages
		$appjson = file_get_contents(ROOT_PATH.'mp-alipay/app.json');
		$appjsonArr = json_decode($appjson,true);
		$pages = $appjsonArr['pages'];
		$newpages = array();
		$indexpage = ltrim($menuset['indexurl'],'/');
		$newpages[] = $indexpage;
		foreach($pages as $v){
			if($v != $indexpage){
				$newpages[] = $v;
			}
		}
		$appconfig['pages'] = $newpages;
		$subpackages = $appjsonArr['subPackages'];
		//页面
		if($subpackages){
			$appconfig['subPackages'] = $subpackages;
		}
		/*
		//底部导航
		$menudata = json_decode($menuset['menudata'],true);
		$tabBarUrls = [];
		if($menuset['menucount'] > 0){
			$tabBarData = ['color'=>$menudata['color'],'selectedColor'=>$menudata['selectedColor'],'backgroundColor'=>$menudata['backgroundColor'],'borderStyle'=>$menudata['borderStyle'],'position'=>$menudata['position'],'list'=>[]];
			foreach($menudata['list'] as $k=>$v){
				if($k < $menuset['menucount']){
					file_put_contents($copydir.'/images/tabbar_'.basename($v['iconPath']),file_get_contents($v['iconPath']));
					$v['iconPath'] = '/images/tabbar_'.basename($v['iconPath']);
					file_put_contents($copydir.'/images/tabbar_'.basename($v['selectedIconPath']),file_get_contents($v['selectedIconPath']));
					$v['selectedIconPath'] = '/images/tabbar_'.basename($v['selectedIconPath']);
					$tabBarData['list'][] = ['pagePath'=>ltrim($v['pagePath'],'/'),'text'=>$v['text'],'iconPath'=>$v['iconPath'],'selectedIconPath'=>$v['selectedIconPath']];
					$tabBarUrls[] = $v['pagePath'];
				}
			}
			$appconfig['tabBar'] = $tabBarData;
		}else{
			$appconfig['tabBar'] = array();
		}
		*/

		//地理位置
		$appconfig['permission'] = ["scope.userLocation"=>["desc"=>"你的位置信息将用于获取距离信息"]];


		$appconfigStr = str_replace('\\/','/',jsonEncode($appconfig));
		file_put_contents($copydir.'/app.json',$appconfigStr);

		//配置信息
		$uniacid = aid;
		$siteroot = PRE_URL2;
		$vendorjscontent = file_get_contents($copydir.'/common/vendor.js');
		$vendorjscontent = preg_replace_callback('/uniacid\:\"[0-9]*\",siteroot\:\"[^"]*\"/',function($matches)use($uniacid,$siteroot){
			return 'uniacid:"'.$uniacid.'",siteroot:"'.$siteroot.'"';
		},$vendorjscontent);
		//$vendorjscontent = str_replace('{uniacid:"1",siteroot:"https://v2.diandashop.com"}','{uniacid:"'.$uniacid.'",siteroot:"'.$siteroot.'"}',$vendorjscontent);
		file_put_contents($copydir.'/common/vendor.js',$vendorjscontent);
		/*
		$projectjson = '{
		  "appid": "'.$alipayapp['appid'].'",
		  "appInfo": {},
		  "appkey": "",
		  "condition": {},
		  "setting": {
			"urlCheck": true
		  },
		  "libVersion": "",
		  "projectname": "'.$alipayapp['nickname'].'"
		}';
		file_put_contents($copydir.'/project.swan.json',$projectjson);
		*/
		$zipname = uniqid().'.zip';
		$zippath = ROOT_PATH.'upload/temp/'.$zipname;
		$myfile = fopen($zippath, "w");
		fclose($myfile);
		\app\commons\File::add_file_to_zip($copydir,$zippath,'alipay_'.aid);
		$url = PRE_URL.'/upload/temp/'.$zipname;
		\app\commons\File::remove_dir($copydir);
		\app\commons\System::plog('下载小程序代码');
		return json(['status'=>1,'msg'=>'打包成功','url'=>$url]);
	}

	//手动上传头条小程序代码
	public function downloadtoutiaoxcx(){
		if($_SERVER['HTTP_HOST'] == 'v2.diandashop.com'){
			//return json(['status'=>0,'msg'=>'演示站无下载权限']);
		}
		$navigationBarBackgroundColor = input('post.navigationBarBackgroundColor');
		$navigationBarTextStyle = input('post.navigationBarTextStyle');
		$sysset = Db::name('admin_set')->where('aid',aid)->find();
		$toutiaoapp = Db::name('admin_setapp_toutiao')->where('aid',aid)->find();
		$menuset = Db::name('designer_menu')->where('aid',aid)->where('platform','toutiao')->find();

		//if(!$wxapp['appid'] || !$wxapp['appsecret']){
		//	return json(['status'=>0,'msg'=>'请先设置小程序AppID和AppSecret']);
		//}
        //import('file',EXTEND_PATH);
		$wxdir = ROOT_PATH.'mp-toutiao';
		$copydir = ROOT_PATH.'upload/temp/toutiao_'.aid;
		\app\commons\File::clear_dir($copydir);
		\app\commons\File::all_copy($wxdir,$copydir);

		//配置文件 app.json
		$appconfig = array();
		$window = array(
			"navigationBarBackgroundColor"=>$navigationBarBackgroundColor,
			"navigationBarTextStyle"=>$navigationBarTextStyle,
			"navigationBarTitleText"=> $toutiaoapp['nickname'],
			"backgroundColor"=>"#f8f8f8",
			"backgroundTextStyle"=>"dark",
			"enablePullDownRefresh"=>true
		);
		$appconfig['window'] = $window;

		//页面 pages
		$appjson = file_get_contents(ROOT_PATH.'mp-toutiao/app.json');
		$appjsonArr = json_decode($appjson,true);
		$pages = $appjsonArr['pages'];
		$newpages = array();
		$indexpage = ltrim($menuset['indexurl'],'/');
		$newpages[] = $indexpage;
		foreach($pages as $v){
			if($v != $indexpage){
				$newpages[] = $v;
			}
		}
		$appconfig['pages'] = $newpages;
		$subpackages = $appjsonArr['subPackages'];
		//页面
		if($subpackages){
			$appconfig['subPackages'] = $subpackages;
		}
		/*
		//底部导航
		$menudata = json_decode($menuset['menudata'],true);
		$tabBarUrls = [];
		if($menuset['menucount'] > 0){
			$tabBarData = ['color'=>$menudata['color'],'selectedColor'=>$menudata['selectedColor'],'backgroundColor'=>$menudata['backgroundColor'],'borderStyle'=>$menudata['borderStyle'],'position'=>$menudata['position'],'list'=>[]];
			foreach($menudata['list'] as $k=>$v){
				if($k < $menuset['menucount']){
					file_put_contents($copydir.'/images/tabbar_'.basename($v['iconPath']),file_get_contents($v['iconPath']));
					$v['iconPath'] = '/images/tabbar_'.basename($v['iconPath']);
					file_put_contents($copydir.'/images/tabbar_'.basename($v['selectedIconPath']),file_get_contents($v['selectedIconPath']));
					$v['selectedIconPath'] = '/images/tabbar_'.basename($v['selectedIconPath']);
					$tabBarData['list'][] = ['pagePath'=>ltrim($v['pagePath'],'/'),'text'=>$v['text'],'iconPath'=>$v['iconPath'],'selectedIconPath'=>$v['selectedIconPath']];
					$tabBarUrls[] = $v['pagePath'];
				}
			}
			$appconfig['tabBar'] = $tabBarData;
		}else{
			$appconfig['tabBar'] = array();
		}
		*/

		//地理位置
		$appconfig['permission'] = ["scope.userLocation"=>["desc"=>"你的位置信息将用于获取距离信息"]];


		$appconfigStr = str_replace('\\/','/',jsonEncode($appconfig));
		file_put_contents($copydir.'/app.json',$appconfigStr);

		//配置信息
		$uniacid = aid;
		$siteroot = PRE_URL2;
		$vendorjscontent = file_get_contents($copydir.'/common/vendor.js');
		$vendorjscontent = preg_replace_callback('/uniacid\:\"[0-9]*\",siteroot\:\"[^"]*\"/',function($matches)use($uniacid,$siteroot){
			return 'uniacid:"'.$uniacid.'",siteroot:"'.$siteroot.'"';
		},$vendorjscontent);
		//$vendorjscontent = str_replace('{uniacid:"1",siteroot:"https://v2.diandashop.com"}','{uniacid:"'.$uniacid.'",siteroot:"'.$siteroot.'"}',$vendorjscontent);
		file_put_contents($copydir.'/common/vendor.js',$vendorjscontent);

		$projectjson = '{
		  "setting": {
			"urlCheck": true,
			"es6": false,
			"postcss": false,
			"minified": false,
			"newFeature": true
		  },
		  "appid": "'.$toutiaoapp['appid'].'",
		  "projectname": "'.$toutiaoapp['nickname'].'"
		}';
		file_put_contents($copydir.'/project.config.json',$projectjson);
		$zipname = uniqid().'.zip';
		$zippath = ROOT_PATH.'upload/temp/'.$zipname;
		$myfile = fopen($zippath, "w");
		fclose($myfile);
		\app\commons\File::add_file_to_zip($copydir,$zippath,'toutiao_'.aid);
		$url = PRE_URL.'/upload/temp/'.$zipname;
		\app\commons\File::remove_dir($copydir);
		\app\commons\System::plog('下载小程序代码');
		return json(['status'=>1,'msg'=>'打包成功','url'=>$url]);
	}

	//手动上传QQ小程序代码
	public function downloadqqxcx(){
		if($_SERVER['HTTP_HOST'] == 'v2.diandashop.com'){
			//return json(['status'=>0,'msg'=>'演示站无下载权限']);
		}
		$navigationBarBackgroundColor = input('post.navigationBarBackgroundColor');
		$navigationBarTextStyle = input('post.navigationBarTextStyle');
		$sysset = Db::name('admin_set')->where('aid',aid)->find();
		$qqapp = Db::name('admin_setapp_qq')->where('aid',aid)->find();
		$menuset = Db::name('designer_menu')->where('aid',aid)->where('platform','qq')->find();

		//if(!$wxapp['appid'] || !$wxapp['appsecret']){
		//	return json(['status'=>0,'msg'=>'请先设置小程序AppID和AppSecret']);
		//}
        //import('file',EXTEND_PATH);
		$wxdir = ROOT_PATH.'mp-qq';
		$copydir = ROOT_PATH.'upload/temp/qq_'.aid;
		\app\commons\File::clear_dir($copydir);
		\app\commons\File::all_copy($wxdir,$copydir);

		//配置文件 app.json
		$appconfig = array();
		$window = array(
			"navigationBarBackgroundColor"=>$navigationBarBackgroundColor,
			"navigationBarTextStyle"=>$navigationBarTextStyle,
			"navigationBarTitleText"=> $qqapp['nickname'],
			"backgroundColor"=>"#f8f8f8",
			"backgroundTextStyle"=>"dark",
			"enablePullDownRefresh"=>true
		);
		$appconfig['window'] = $window;

		//页面 pages
		$appjson = file_get_contents(ROOT_PATH.'mp-qq/app.json');
		$appjsonArr = json_decode($appjson,true);
		$pages = $appjsonArr['pages'];
		$newpages = array();
		$indexpage = ltrim($menuset['indexurl'],'/');
		$newpages[] = $indexpage;
		foreach($pages as $v){
			if($v != $indexpage){
				$newpages[] = $v;
			}
		}
		$appconfig['pages'] = $newpages;
		$subpackages = $appjsonArr['subPackages'];
		//页面
		if($subpackages){
			$appconfig['subPackages'] = $subpackages;
		}
		/*
		//底部导航
		$menudata = json_decode($menuset['menudata'],true);
		$tabBarUrls = [];
		if($menuset['menucount'] > 0){
			$tabBarData = ['color'=>$menudata['color'],'selectedColor'=>$menudata['selectedColor'],'backgroundColor'=>$menudata['backgroundColor'],'borderStyle'=>$menudata['borderStyle'],'position'=>$menudata['position'],'list'=>[]];
			foreach($menudata['list'] as $k=>$v){
				if($k < $menuset['menucount']){
					file_put_contents($copydir.'/images/tabbar_'.basename($v['iconPath']),file_get_contents($v['iconPath']));
					$v['iconPath'] = '/images/tabbar_'.basename($v['iconPath']);
					file_put_contents($copydir.'/images/tabbar_'.basename($v['selectedIconPath']),file_get_contents($v['selectedIconPath']));
					$v['selectedIconPath'] = '/images/tabbar_'.basename($v['selectedIconPath']);
					$tabBarData['list'][] = ['pagePath'=>ltrim($v['pagePath'],'/'),'text'=>$v['text'],'iconPath'=>$v['iconPath'],'selectedIconPath'=>$v['selectedIconPath']];
					$tabBarUrls[] = $v['pagePath'];
				}
			}
			$appconfig['tabBar'] = $tabBarData;
		}else{
			$appconfig['tabBar'] = array();
		}
		*/

		//地理位置
		$appconfig['permission'] = ["scope.userLocation"=>["desc"=>"你的位置信息将用于获取距离信息"]];


		$appconfigStr = str_replace('\\/','/',jsonEncode($appconfig));
		file_put_contents($copydir.'/app.json',$appconfigStr);

		//配置信息
		$uniacid = aid;
		$siteroot = PRE_URL2;
		$vendorjscontent = file_get_contents($copydir.'/common/vendor.js');
		$vendorjscontent = preg_replace_callback('/uniacid\:\"[0-9]*\",siteroot\:\"[^"]*\"/',function($matches)use($uniacid,$siteroot){
			return 'uniacid:"'.$uniacid.'",siteroot:"'.$siteroot.'"';
		},$vendorjscontent);
		//$vendorjscontent = str_replace('{uniacid:"1",siteroot:"https://v2.diandashop.com"}','{uniacid:"'.$uniacid.'",siteroot:"'.$siteroot.'"}',$vendorjscontent);
		file_put_contents($copydir.'/common/vendor.js',$vendorjscontent);

		$projectjson = '{
		  "description": "项目配置文件。",
		  "packOptions": {
			"ignore": []
		  },
		  "setting": {
			"es6": true,
			"minified": true,
			"nodeModules":false
		  },
		  "compileType": "miniprogram",
		  "projectname": "'.$qqapp['nickname'].'",
		  "condition": {
			"search": {
			  "current": -1,
			  "list": []
			},
			"conversation": {
			  "current": -1,
			  "list": []
			},
			"game": {
			  "current": -1,
			  "list": []
			},
			"miniprogram": {
			  "current": -1,
			  "list": []
			}
		  },
		  "qqappid": "'.$qqapp['appid'].'",
		  "qqLibVersion": "1.6.3"
		}';
		file_put_contents($copydir.'/project.config.json',$projectjson);
		$zipname = uniqid().'.zip';
		$zippath = ROOT_PATH.'upload/temp/'.$zipname;
		$myfile = fopen($zippath, "w");
		fclose($myfile);
		\app\commons\File::add_file_to_zip($copydir,$zippath,'qq_'.aid);
		$url = PRE_URL.'/upload/temp/'.$zipname;
		\app\commons\File::remove_dir($copydir);
		\app\commons\System::plog('下载小程序代码');
		return json(['status'=>1,'msg'=>'打包成功','url'=>$url]);
	}
	//获取体验码
	public function gettyqrcode(){
		$access_token = \app\commons\Wechat::access_token(aid,'wx');
		$rs = request_get('https://api.weixin.qq.com/wxa/get_qrcode?access_token='.$access_token);
		die($rs);
	}
	//获取行业
	public function getHy(){
		$rs = request_get('https://api.weixin.qq.com/wxa/get_category?access_token='.\app\commons\Wechat::access_token(aid,'wx'));
		$rs = json_decode($rs,true);
		if(isset($rs['errcode']) && $rs['errcode']!=0){
			return json(['status'=>0,'msg'=>\app\commons\Wechat::geterror($rs)]);
		}
		$category_list = $rs['category_list'];
		return json(['status'=>1,'data'=>$category_list]);
	}
	//小程序提交审核 https://developers.weixin.qq.com/doc/oplatform/openApi/OpenApiDoc/miniprogram-management/code-management/submitAudit.html
	public function submit_audit(){
		//dump($_POST);
		//dump(input('post.autofb'));die;
		$rs = request_get('https://api.weixin.qq.com/wxa/get_category?access_token='.\app\commons\Wechat::access_token(aid,'wx'));
		$rs = json_decode($rs,true);
		if(isset($rs['errcode']) && $rs['errcode']!=0){
			return json(['status'=>0,'msg'=>\app\commons\Wechat::geterror($rs),'errcode'=>$rs['errcode'],'get_category']);
		}
		$category_list = $rs['category_list'];
		$category = $category_list[input('param.hy/d')];

		$menuset = Db::name('designer_menu')->where('aid',aid)->where('platform','wx')->find();

		$url = 'https://api.weixin.qq.com/wxa/submit_audit?access_token='.\app\commons\Wechat::access_token(aid,'wx');
		//$postdata = [];
		//$postdata['version_desc'] = input('post.version_desc');
		//$rs = request_post($url,str_replace('\\/','/',jsonEncode($postdata)));
		//$rs = json_decode($rs,true);
		//if(isset($rs['errcode']) && $rs['errcode']!=0){
		//	return json(['status'=>0,'msg'=>\app\commons\Wechat::geterror($rs)]);
		//}
		//$wxalog = Db::name('admin_wxalog')->where('aid',aid)->where('status',0)->order('id desc')->find();
		//Db::name('admin_wxalog')->where('id',$wxalog['id'])->update(['status'=>1,'autofb'=>input('post.autofb'),'version_desc'=>input('post.version_desc'),'auditid'=>$rs['auditid']]);
		//return json(['status'=>1,'msg'=>'提交成功,请等待审核','url'=>(string)url('index')]);

		$item = [];
		$item['address'] = ltrim($menuset['indexurl'],'/');
		$item['tag'] = input('post.tag');
		$item['first_class'] = $category['first_class'];
		if($category['second_class']) $item['second_class'] = $category['second_class'];
		if($category['third_class']) $item['third_class'] = $category['third_class'];
		$item['first_id'] = $category['first_id'];
		if($category['second_id']) $item['second_id'] = $category['second_id'];
		if($category['third_id']) $item['third_id'] = $category['third_id'];
		$item['title'] = '首页';
		$postdata = ['item_list'=>[$item]];
		if(input('post.version_desc')){
			$postdata['version_desc'] = input('post.version_desc');
		}
        if(input('post.order_path')){
            $postdata['order_path'] = input('post.order_path');
        }
		$postdata['privacy_api_not_use'] = true;
		//dump(str_replace('\\/','/',jsonEncode($postdata)));die;
		$rs = request_post($url,str_replace('\\/','/',jsonEncode($postdata)));
		$rs = json_decode($rs,true);
		if(isset($rs['errcode']) && $rs['errcode']!=0){
			if($rs['errcode'] == '85094'){
				$postdata['ugc_declare'] = ['scene'=>[2,3,4],'method'=>[3],'has_audit_team'=>1,'audit_desc'=>'用户发布的内容需要通过人工审核'];
				$rs = request_post($url,str_replace('\\/','/',jsonEncode($postdata)));
				$rs = json_decode($rs,true);
				if(isset($rs['errcode']) && $rs['errcode']!=0){
					return json(['status'=>0,'msg'=>\app\commons\Wechat::geterror($rs),'errcode'=>$rs['errcode'],'submit_audit']);
				}
			}else{
				return json(['status'=>0,'msg'=>\app\commons\Wechat::geterror($rs),'errcode'=>$rs['errcode'],'submit_audit']);
			}
		}
		$wxalog = Db::name('admin_wxalog')->where('aid',aid)->where('status',0)->order('id desc')->find();
		Db::name('admin_wxalog')->where('id',$wxalog['id'])->update(['status'=>1,'autofb'=>input('post.autofb'),'version_desc'=>input('post.version_desc'),'category'=>$item['first_class'].'>'.$item['second_class'].' '.$item['third_class'],'auditid'=>$rs['auditid']]);
		\app\commons\System::plog('小程序提交审核');
		return json(['status'=>1,'msg'=>'提交成功,请等待审核','url'=>(string)url('index')]);

	}
	//获取审核状态
	public function get_auditstatus(){
		$auditid = input('param.auditid');
		$data = ['auditid'=>$auditid];
		$rs = request_post('https://api.weixin.qq.com/wxa/get_auditstatus?access_token='.\app\commons\Wechat::access_token(aid,'wx'),jsonEncode($data));
		$rs = json_decode($rs,true);
		if(isset($rs['errcode']) && $rs['errcode']!=0){
			return json(['status'=>0,'msg'=>\app\commons\Wechat::geterror($rs)]);
		}else{
			if($rs['status']==0){
				Db::name('admin_wxalog')->where('aid',aid)->where('auditid',$auditid)->update(['status'=>2]);
			}
			if($rs['status']==1){
				Db::name('admin_wxalog')->where('aid',aid)->where('auditid',$auditid)->update(['status'=>4,'audit_reason'=>$rs['reason']]);
			}
			return json(['status'=>1,'msg'=>'刷新成功','rs'=>$rs]);
		}
	}
	//发布小程序
	public function fabu(){
		$rs = request_post('https://api.weixin.qq.com/wxa/release?access_token='.\app\commons\Wechat::access_token(aid,'wx'),'{}');
		$rs = json_decode($rs,true);
		if(isset($rs['errcode']) && $rs['errcode']!=0){
			if($rs['errcode'] == '85052'){
				$wxalog = Db::name('admin_wxalog')->where('aid',aid)->where('status',2)->order('id desc')->find();
				Db::name('admin_wxalog')->where('id',$wxalog['id'])->update(['status'=>3]);
			}
			return json(['status'=>0,'msg'=>\app\commons\Wechat::geterror($rs)]);
		}
		$wxalog = Db::name('admin_wxalog')->where('aid',aid)->where('status',2)->order('id desc')->find();
		Db::name('admin_wxalog')->where('id',$wxalog['id'])->update(['status'=>3]);
		\app\commons\System::plog('发布小程序');
		return json(['status'=>1,'msg'=>'发布成功','url'=>(string)url('index')]);
	}
	//撤销审核
	public function undocodeaudit(){
		$rs = request_get('https://api.weixin.qq.com/wxa/undocodeaudit?access_token='.\app\commons\Wechat::access_token(aid,'wx'));
		$rs = json_decode($rs,true);
		if(isset($rs['errcode']) && $rs['errcode']!=0){
			return json(['status'=>0,'msg'=>\app\commons\Wechat::geterror($rs)]);
		}
		Db::name('admin_wxalog')->where('aid',aid)->where('status',1)->update(['status'=>0]);
		return json(['status'=>1,'msg'=>'撤销成功','url'=>(string)url('index')]);
	}
	public function getupdatelog(){
		$list = Db::name('daima_list')->where('agid',1)->order('id desc')->select()->toArray();
		return json(['status'=>1,'data'=>$list]);
	}
	//绑定到同一个开放平台
	public function bindinopen(){
		$mpauthinfo = Db::name('admin_setapp_mp')->where('aid',aid)->find();
		$xcxauthinfo = Db::name('admin_setapp_wx')->where('aid',aid)->find();
		//if($xcxauthinfo['principal_name']!=$xcxauthinfo['principal_name']){
		//	return json(['status'=>0,'msg'=>'小程序与公众号主体不一致，请手动注册开放平台账号进行绑定']);
		//}
		$open_appid = '';
		if($xcxauthinfo['open_appid']) $open_appid = $xcxauthinfo['open_appid'];
		if($mpauthinfo['open_appid']) $open_appid = $mpauthinfo['open_appid'];
		if(!$open_appid){
			$url = 'https://api.weixin.qq.com/cgi-bin/open/create?access_token='.\app\commons\Wechat::access_token(aid,'mp');
			$postdata = array();
			$postdata['appid'] = $mpauthinfo['appid'];
			$rs = request_post($url,jsonEncode($postdata));
			$rs = json_decode($rs,true);
			if(isset($rs['errcode']) && $rs['errcode']!=0){
				return json(['status'=>0,'msg'=>\app\commons\Wechat::geterror($rs),'rscreate'=>$rs]);
			}
			$open_appid = $rs['open_appid'];
			Db::name('admin_setapp_mp')->where('aid',aid)->update(['open_appid'=>$rs['open_appid']]);
			$mpauthinfo['open_appid'] = $rs['open_appid'];
		}
		if(!$mpauthinfo['open_appid']){
			$url = 'https://api.weixin.qq.com/cgi-bin/open/bind?access_token='.\app\commons\Wechat::access_token(aid,'mp');
			$postdata = array();
			$postdata['appid'] = $mpauthinfo['appid'];
			$postdata['open_appid'] = $open_appid;
			$rs = request_post($url,jsonEncode($postdata));
			$rs = json_decode($rs,true);
			if(isset($rs['errcode']) && $rs['errcode']!=0){
				if($rs['errcode']=='48001'){
					return json(['status'=>0,'msg'=>'绑定公众号失败，请检查该公众号是否授权给其他第三方平台，登录公众号(mp.weixin.qq.com)在[公众号设置]-[授权管理]中查看，尝试取消所有授权，然后重新授权','rsbind'=>$rs]);
				}
				if($rs['errcode']=='89000'){
					return json(['status'=>0,'msg'=>'该公众号已经绑定了开放平台帐号','rsbind'=>$rs]);
				}
				if($rs['errcode']=='89003'){
					return json(['status'=>0,'msg'=>'该开放平台帐号并非通过本平台创建，请登录开放平台手动绑定(open.weixin.qq.com)','rsbind'=>$rs]);
				}
				return json(['status'=>0,'msg'=>\app\commons\Wechat::geterror($rs),'rsbind'=>$rs]);
			}
			Db::name('admin_setapp_mp')->where('aid',aid)->update(['open_appid'=>$open_appid]);
		}
		if(!$xcxauthinfo['open_appid']){
			$url = 'https://api.weixin.qq.com/cgi-bin/open/bind?access_token='.\app\commons\Wechat::access_token(aid,'wx');
			$postdata = array();
			$postdata['appid'] = $xcxauthinfo['appid'];
			$postdata['open_appid'] = $open_appid;
			$rs = request_post($url,jsonEncode($postdata));
			$rs = json_decode($rs,true);
			if(isset($rs['errcode']) && $rs['errcode']!=0){
				if($rs['errcode']=='48001'){
					return json(['status'=>0,'msg'=>'绑定小程序失败，请检查该小程序是否授权给其他第三方平台，登录小程序账号(mp.weixin.qq.com)在[设置]-[第三方设置]中查看，尝试取消所有授权，然后重新授权','rsbind'=>$rs,'data'=>$postdata]);
				}
				if($rs['errcode']=='89000'){
					return json(['status'=>0,'msg'=>'该小程序已经绑定了开放平台帐号','rsbind'=>$rs]);
				}
				if($rs['errcode']=='89003'){
					return json(['status'=>0,'msg'=>'该开放平台帐号并非通过本平台创建，请登录开放平台手动绑定(open.weixin.qq.com)','rsbind'=>$rs]);
				}
				return json(['status'=>0,'msg'=>\app\commons\Wechat::geterror($rs),'rsbind'=>$rs]);
			}
			Db::name('admin_setapp_wx')->where('aid',aid)->update(['open_appid'=>$open_appid]);
		}
		\app\commons\System::plog('开启公众号小程序数据互通');
		return json(['status'=>1,'msg'=>'开启成功','url'=>(string)url('index')]);
	}
	//上传公众号接口校验文件
	public function uploadjstxt(){
		if (empty($_FILES['file']['tmp_name'])) {
			showmsg('请选择文件');
		}
		if ($_FILES['file']['type'] != 'text/plain') {
			showmsg('文件类型错误');
		}
        // 检查文件名是否包含重命名模式
        if (preg_match('/\(\d+\)|副本/',$_FILES['file']['name'])) {
            showmsg('文件名不一致,请重新上传');
        }
		$ext = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
		if(!preg_match('/^[A-Za-z0-9]+$/', file_get_contents($_FILES['file']['tmp_name']))){
			showmsg('上传文件不合法,请重新上传');
		}
		if ('txt' == strtolower($ext)) {
			$file = $_FILES['file'];
			$file['name'] = $this->parse_path($file['name']);
			if (is_uploaded_file($file['tmp_name'])) {
				move_uploaded_file($file['tmp_name'], ROOT_PATH . '/' . $file['name']);
			} else {
				rename($file['tmp_name'], ROOT_PATH . '/' . $file['name']);
			}
		}else{
			showmsg('上传文件不合法,请重新上传');
		}

		//$file = file_get_contents($_FILES['file']['tmp_name']);
		//$file_name = 'MP_verify_' . $file . '.txt';
		//if ($_FILES['file']['name'] != $file_name || !preg_match('/^[A-Za-z0-9]+$/', $file)) {
		//	showmsg('上传文件不合法,请重新上传');
		//}
		//file_put_contents(ROOT_PATH . '/' . $_FILES['file']['name'], $file);
		\app\commons\System::plog('上传公众号校验文件');
		showmsg('上传成功',1);
	}
	function parse_path($path) {
		$danger_char = array('../', '{php', '<?php', '<%', '<?', '..\\', '\\\\', '\\', '..\\\\', '%00', '\0', '\r');
		foreach ($danger_char as $char) {
			if ($this->strexists($path, $char)) {
				return false;
			}
		}
		return $path;
	}
	function strexists($string, $find) {
		return !(false === strpos($string, $find));
	}

	//绑定支付宝小程序
	public function alipay(){
		if(input('param.op') == 'setappid'){
			$postinfo = input('post.info/a');
			$data = [];
			$data['appid'] = trim($postinfo['appid']);
			$data['appsecret'] = trim($postinfo['appsecret']);
			$data['appsecret'] = str_replace('-----BEGIN CERTIFICATE REQUEST-----','',$data['appsecret']);
			$data['appsecret'] = str_replace('-----END CERTIFICATE REQUEST-----','',$data['appsecret']);
			$data['appsecret'] = str_replace("\r",'',$data['appsecret']);
			$data['appsecret'] = str_replace("\n",'',$data['appsecret']);
			$data['appsecret'] = str_replace(" ",'',$data['appsecret']);
			$data['publickey'] = trim($postinfo['publickey']);
			$data['publickey'] = str_replace('-----BEGIN CERTIFICATE-----','',$data['publickey']);
			$data['publickey'] = str_replace('-----END CERTIFICATE-----','',$data['publickey']);
			$data['publickey'] = str_replace("\r",'',$data['publickey']);
			$data['publickey'] = str_replace("\n",'',$data['publickey']);
			$data['publickey'] = str_replace(" ",'',$data['publickey']);
			$data['nickname'] = trim($postinfo['nickname']);
			$data['headimg'] = trim($postinfo['headimg']);
			$data['qrcode'] = trim($postinfo['qrcode']);
			$data['alipay'] = $postinfo['alipay'];
			$data['openid_set'] = $postinfo['openid_set'];
			if(getcustom('sxpay_fenzhang')){
				$data['sxpay'] = $postinfo['sxpay'];
				$data['sxpay_mno'] = $postinfo['sxpay_mno'];
				$data['sxpay_mchkey'] = $postinfo['sxpay_mchkey'];
			}
            Db::name('admin_setapp_alipay')->where('aid',aid)->update($data);
			\app\commons\System::plog('绑定支付宝小程序');
			return json(['status'=>1,'msg'=>'保存成功']);
		}
		$info = Db::name('admin_setapp_alipay')->where('aid',aid)->find();
		if(!$info){
			Db::name('admin_setapp_alipay')->insert(['aid'=>aid]);
			$info = Db::name('admin_setapp_alipay')->where('aid',aid)->find();
		}
		View::assign('info',$info);
		return View::fetch();
	}
	//绑定百度小程序
	public function baidu(){
		if(input('param.op') == 'setappid'){
			$postinfo = input('post.info/a');
			$data = [];
			$data['appid'] = trim($postinfo['appid']);
			$data['appkey'] = trim($postinfo['appkey']);
			$data['appsecret'] = trim($postinfo['appsecret']);
			$data['nickname'] = trim($postinfo['nickname']);
			$data['headimg'] = trim($postinfo['headimg']);
			$data['qrcode'] = trim($postinfo['qrcode']);
			$data['baidupay'] = $postinfo['baidupay'];
			$data['pay_appid'] = trim($postinfo['pay_appid']);
			$data['pay_appkey'] = trim($postinfo['pay_appkey']);
			$data['pay_dealId'] = trim($postinfo['pay_dealId']);

			$data['pay_publickey'] = trim($postinfo['pay_publickey']);
			$data['pay_publickey'] = str_replace('-----BEGIN PUBLIC KEY-----','',$data['pay_publickey']);
			$data['pay_publickey'] = str_replace('-----END PUBLIC KEY-----','',$data['pay_publickey']);
			$data['pay_publickey'] = str_replace("\r",'',$data['pay_publickey']);
			$data['pay_publickey'] = str_replace("\n",'',$data['pay_publickey']);
			$data['pay_publickey'] = str_replace(" ",'',$data['pay_publickey']);

			$data['pay_privatekey'] = trim($postinfo['pay_privatekey']);
			$data['pay_privatekey'] = str_replace('-----BEGIN PRIVATE KEY-----','',$data['pay_privatekey']);
			$data['pay_privatekey'] = str_replace('-----END PRIVATE KEY-----','',$data['pay_privatekey']);
			$data['pay_privatekey'] = str_replace("\r",'',$data['pay_privatekey']);
			$data['pay_privatekey'] = str_replace("\n",'',$data['pay_privatekey']);
			$data['pay_privatekey'] = str_replace(" ",'',$data['pay_privatekey']);
			Db::name('admin_setapp_baidu')->where('aid',aid)->update($data);
			\app\commons\System::plog('绑定百度小程序');
			return json(['status'=>1,'msg'=>'保存成功']);
		}
		$info = Db::name('admin_setapp_baidu')->where('aid',aid)->find();
		if(!$info){
			Db::name('admin_setapp_baidu')->insert(['aid'=>aid]);
			$info = Db::name('admin_setapp_baidu')->where('aid',aid)->find();
		}
		View::assign('info',$info);
		return View::fetch();
	}
	//绑定头条小程序
	public function toutiao(){
		if(input('param.op') == 'setappid'){
			$postinfo = input('post.info/a');
			$data = [];
			$data['appid'] = trim($postinfo['appid']);
			$data['appsecret'] = trim($postinfo['appsecret']);
			$data['nickname'] = trim($postinfo['nickname']);
			$data['headimg'] = trim($postinfo['headimg']);
			$data['qrcode'] = trim($postinfo['qrcode']);
			$data['toutiaopay'] = $postinfo['toutiaopay'];
			$data['pay_token'] = trim($postinfo['pay_token']);
			$data['pay_mchid'] = trim($postinfo['pay_mchid']);
			$data['pay_salt'] = trim($postinfo['pay_salt']);

			Db::name('admin_setapp_toutiao')->where('aid',aid)->update($data);
			\app\commons\System::plog('绑定抖音小程序');
			return json(['status'=>1,'msg'=>'保存成功']);
		}
		$info = Db::name('admin_setapp_toutiao')->where('aid',aid)->find();
		if(!$info){
			Db::name('admin_setapp_toutiao')->insert(['aid'=>aid,'pay_token'=>random(10)]);
			$info = Db::name('admin_setapp_toutiao')->where('aid',aid)->find();
		}
		View::assign('info',$info);
		return View::fetch();
	}
	//绑定QQ小程序
	public function qq(){
		if(input('param.op') == 'setappid'){
			$postinfo = input('post.info/a');
			$data = [];
			$data['appid'] = trim($postinfo['appid']);
			$data['appsecret'] = trim($postinfo['appsecret']);
			$data['apptoken'] = trim($postinfo['apptoken']);
			$data['nickname'] = trim($postinfo['nickname']);
			$data['headimg'] = trim($postinfo['headimg']);
			$data['qrcode'] = trim($postinfo['qrcode']);

			$data['wxpay'] = $postinfo['wxpay'];
			$data['wxpay_type'] = $postinfo['wxpay_type'];
			$data['wxpay_appid'] = $postinfo['wxpay_appid'];
			$data['wxpay_mchid'] = trim($postinfo['wxpay_mchid']);
			$data['wxpay_mchkey'] = trim($postinfo['wxpay_mchkey']);
			$data['wxpay_sub_mchid'] = trim($postinfo['wxpay_sub_mchid']);
			$data['wxpay_apiclient_cert'] = str_replace(PRE_URL.'/','',$postinfo['wxpay_apiclient_cert']);
			$data['wxpay_apiclient_key'] = str_replace(PRE_URL.'/','',$postinfo['wxpay_apiclient_key']);
			if(!empty($data['wxpay_apiclient_cert']) && substr($data['wxpay_apiclient_cert'], -4) != '.pem'){
				return json(['status'=>0,'msg'=>'PEM证书格式错误']);
			}
			if(!empty($data['wxpay_apiclient_key']) && substr($data['wxpay_apiclient_key'], -4) != '.pem'){
				return json(['status'=>0,'msg'=>'证书密钥格式错误']);
			}
			Db::name('admin_setapp_qq')->where('aid',aid)->update($data);
			\app\commons\System::plog('绑定QQ小程序');
			return json(['status'=>1,'msg'=>'保存成功']);
		}
		$info = Db::name('admin_setapp_qq')->where('aid',aid)->find();
		if(!$info){
			Db::name('admin_setapp_qq')->insert(['aid'=>aid]);
			$info = Db::name('admin_setapp_qq')->where('aid',aid)->find();
		}
		View::assign('info',$info);
		return View::fetch();
	}
	//h5设置
    public function h5(){
		if(request()->isPost()){
			$postinfo = input('post.info/a');
			$data = [];
			$data['appid'] = trim($postinfo['appid']);
			$data['wxpay'] = $postinfo['wxpay'];
			$data['wxpay_type'] = $postinfo['wxpay_type'];
			$data['wxpay_mchid'] = trim($postinfo['wxpay_mchid']);
			$data['wxpay_mchkey'] = trim($postinfo['wxpay_mchkey']);
			$data['wxpay_sub_mchid'] = trim($postinfo['wxpay_sub_mchid']);
			$data['wxpay_apiclient_cert'] = str_replace(PRE_URL.'/','',$postinfo['wxpay_apiclient_cert']);
			$data['wxpay_apiclient_key'] = str_replace(PRE_URL.'/','',$postinfo['wxpay_apiclient_key']);

			if(!empty($data['wxpay_apiclient_cert']) && substr($data['wxpay_apiclient_cert'], -4) != '.pem'){
				return json(['status'=>0,'msg'=>'PEM证书格式错误']);
			}
			if(!empty($data['wxpay_apiclient_key']) && substr($data['wxpay_apiclient_key'], -4) != '.pem'){
				return json(['status'=>0,'msg'=>'证书密钥格式错误']);
			}

			$data['alipay'] = trim($postinfo['alipay']);
			$data['ali_appid'] = trim($postinfo['ali_appid']);
			$data['ali_privatekey'] = trim($postinfo['ali_privatekey']);
			$data['ali_publickey'] = trim($postinfo['ali_publickey']);

			Db::name('admin_setapp_h5')->where('aid',aid)->update($data);
			\app\commons\System::plog('手机H5设置');
			return json(['status'=>1,'msg'=>'设置成功','url'=>true]);
		}
		$info = Db::name('admin_setapp_h5')->where('aid',aid)->find();
		if(!$info) Db::name('admin_setapp_h5')->insert(['aid'=>aid]);
		View::assign('info',$info);
		return View::fetch();
	}
	//绑定手机APP
	public function app(){
		if(input('param.op') == 'setappid'){
			$postinfo = input('post.info/a');
			$data = [];
			$data['appid'] = trim($postinfo['appid']);
			$data['appsecret'] = trim($postinfo['appsecret']);
			$data['wxpay'] = $postinfo['wxpay'];
			$data['wxpay_type'] = $postinfo['wxpay_type'];
			$data['wxpay_mchid'] = trim($postinfo['wxpay_mchid']);
			$data['wxpay_mchkey'] = trim($postinfo['wxpay_mchkey']);
			$data['wxpay_sub_mchid'] = trim($postinfo['wxpay_sub_mchid']);
			$data['wxpay_apiclient_cert'] = str_replace(PRE_URL.'/','',$postinfo['wxpay_apiclient_cert']);
			$data['wxpay_apiclient_key'] = str_replace(PRE_URL.'/','',$postinfo['wxpay_apiclient_key']);

			if(!empty($data['wxpay_apiclient_cert']) && substr($data['wxpay_apiclient_cert'], -4) != '.pem'){
				return json(['status'=>0,'msg'=>'PEM证书格式错误']);
			}
			if(!empty($data['wxpay_apiclient_key']) && substr($data['wxpay_apiclient_key'], -4) != '.pem'){
				return json(['status'=>0,'msg'=>'证书密钥格式错误']);
			}

			$data['alipay'] = trim($postinfo['alipay']);
			$data['ali_appid'] = trim($postinfo['ali_appid']);
			$data['ali_privatekey'] = trim($postinfo['ali_privatekey']);
			$data['ali_publickey'] = trim($postinfo['ali_publickey']);
			$data['androidurl'] = trim($postinfo['androidurl']);
			$data['iosurl'] = trim($postinfo['iosurl']);

			Db::name('admin_setapp_app')->where('aid',aid)->update($data);
			\app\commons\System::plog('手机APP设置');
			return json(['status'=>1,'msg'=>'保存成功','url'=>true]);
		}
		$info = Db::name('admin_setapp_app')->where('aid',aid)->find();
		if(!$info){
			Db::name('admin_setapp_app')->insert(['aid'=>aid]);
			$info = Db::name('admin_setapp_app')->where('aid',aid)->find();
		}
		View::assign('info',$info);
		return View::fetch();
	}

    //顶部导航背景颜色校验比配16进制颜色代码、RGB(A)颜色代码 其他颜色无效
    public function isValidColor($color) {
        // 正则表达式，用于匹配16进制颜色代码
        $hexPattern = '/^#([a-fA-F0-9]{8}|[a-fA-F0-9]{6}|[a-fA-F0-9]{3})$/';

        // 正则表达式，用于匹配RGB(A)颜色代码
        //$rgbPattern = '/^rgba?\\((\\d{1,3}),\\s*(\\d{1,3}),\\s*(\\d{1,3})(,\\s*(\\d*(\\.\\d+)?))?\\)$/';

        // 首先尝试匹配16进制颜色代码
        if (preg_match($hexPattern,$color)) {
            return true;
        }

        // 如果不是16进制，尝试匹配RGB(A)颜色代码
//        if (preg_match($rgbPattern,$color, $matches)) {
//            // 检查RGB(A)中的每个值是否在0到255之间
//            foreach (array_slice($matches, 1, 3) as$value) {
//                if ($value < 0 ||$value > 255) {
//                    return false;
//                }
//            }
//            // 如果有透明度值，检查它是否在0到1之间
//            if (isset($matches[5]) && ($matches[5] < 0 || $matches[5] > 1)) {
//                return false;
//            }
//            return true;
//        }

        // 如果都不匹配，返回false
        return false;
    }
    	public function apk(){
		if(input('param.op') == 'setappid'){
			$postinfo = input('post.info/a');
			$data = [];
			$data['androidurl'] = trim($postinfo['androidurl']);
			$data['iosurl'] = trim($postinfo['iosurl']);
            $data['up_apptime'] = time();


			Db::name('admin_setapp_app')->where('aid',aid)->update($data);
			\app\commons\System::plog('手机APP设置');
			return json(['status'=>1,'msg'=>'保存成功','url'=>true]);
		}
		$info = Db::name('admin_setapp_app')->where('aid',aid)->find();
		if(!$info){
			Db::name('admin_setapp_app')->insert(['aid'=>aid]);
			$info = Db::name('admin_setapp_app')->where('aid',aid)->find();
		}
		View::assign('info',$info);
		return View::fetch();
	}
}