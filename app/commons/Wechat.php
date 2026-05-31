<?php


namespace app\commons;
use think\Exception;
use think\facade\Db;
use think\facade\Log;
use app\commons\System;
use think\facade\Request;

class Wechat
{
	//获取平台的access_token
    public static function component_access_token($aid=0){
		$componentinfo = Db::name('sysset')->where('name','component')->value('value');
		$componentinfo = json_decode($componentinfo,true);
		$component_access_token = cache('component_access_token');
		if(!$component_access_token){
			$url = 'https://api.weixin.qq.com/cgi-bin/component/api_component_token';
			$component_verify_ticket = cache('component_verify_ticket');
			$data = array();
			$data['component_appid'] = $componentinfo['appid'];
			$data['component_appsecret'] = $componentinfo['appsecret'];
			$data['component_verify_ticket'] = $component_verify_ticket;
			$rs = request_post($url,jsonEncode($data));
			$rs = json_decode($rs);
			if($rs->component_access_token){
				cache('component_access_token',$rs->component_access_token,7000);
				$component_access_token = $rs->component_access_token;
			}else{
				echojson(['status'=>0,'msg'=>self::geterror((array)$rs)]);
				//return false;
			}
		}
		return $component_access_token;
	}
	//获取平台appid
	public static function component_appid(){
		$componentinfo = Db::name('sysset')->where('name','component')->value('value');
		$componentinfo = json_decode($componentinfo,true);
		$component_appid = $componentinfo['appid'];
		return $component_appid;
	}
	//获取access_token $platform mp:公众号 wx:小程序
	public static function access_token($aid,$platform='wx',$iscache=true){
		$appinfo = System::appinfo($aid,$platform);
		$appid = $appinfo['appid'];
		$appsecret = $appinfo['appsecret'];
		if(!$appid) return '';
		$tokendata = Db::name('access_token')->where('appid',$appid)->find();   
		if($iscache && $tokendata && $tokendata['access_token'] && $tokendata['expires_time'] > time()){
			return $tokendata['access_token'];
		}else{
			if($appinfo['authtype']==1){ //授权接入
				//刷新调用凭证
				$url = 'https://api.weixin.qq.com/cgi-bin/component/api_authorizer_token?component_access_token='.self::component_access_token();
				$data = array();
				$data['component_appid'] = self::component_appid();
				$data['authorizer_appid'] = $appid;
				$data['authorizer_refresh_token'] = $appinfo['refresh_token'];
				$rs = request_post($url,jsonEncode($data));
				$rs = json_decode($rs);
				if($rs->authorizer_access_token){
					$access_token = $rs->authorizer_access_token;
					Db::name('access_token')->where('appid',$appid)->update(['access_token'=>$access_token,'expires_time'=>time()+7000]);
					return $access_token;
				}else{
					//\think\facade\Log::write($rs);
					//return '';
					echojson(array('status'=>0,'msg'=>self::geterror($rs)));
				}
			}else{ //普通接入
				if(!$appsecret) return '';
				$url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$appid}&secret={$appsecret}";
				$res = json_decode(request_get($url));
				$access_token = $res->access_token;
				if($access_token) {
					if($tokendata){
						Db::name('access_token')->where('appid',$appid)->update(['access_token'=>$access_token,'expires_time'=>time()+7000]);
					}else{
						Db::name('access_token')->insert(['appid'=>$appid,'access_token'=>$access_token,'expires_time'=>time()+7000]);
					}
					return $access_token;
				}else{
					//\think\facade\Log::write($res);
					//return '';
					echojson(array('status'=>0,'msg'=>self::geterror($res)));
				}
			}
		}
	}

    //第一步：用户同意授权，获取code https://developers.weixin.qq.com/doc/offiaccount/OA_Web_Apps/Wechat_webpage_authorization.html#0
    public static function getOauth2AuthorizeUrl($aid,$redirectUrl,$grant_type='snsapi_base',$state='authlogin')
    {
        $appinfo = System::appinfo($aid,'mp');
        $appid = $appinfo['appid'];
        $appsecret = $appinfo['appsecret'];

        if($appinfo['authtype']==1){ //授权接入
            $component_appid = self::component_appid();
            $authorizeUrl = "https://open.weixin.qq.com/connect/oauth2/authorize?appid={$appid}&component_appid={$component_appid}&redirect_uri={$redirectUrl}&response_type=code&scope={$grant_type}&state={$state}#wechat_redirect";
        }else{
            $authorizeUrl = "https://open.weixin.qq.com/connect/oauth2/authorize?appid={$appid}&redirect_uri={$redirectUrl}&response_type=code&scope={$grant_type}&state={$state}#wechat_redirect";
        }

        return $authorizeUrl;
    }

    //第二步：通过code换取网页授权access_token https://developers.weixin.qq.com/doc/offiaccount/OA_Web_Apps/Wechat_webpage_authorization.html#1
    public static function getAccessTokenByCode($aid,$code,$platform = 'mp')
    {
        $appinfo = System::appinfo($aid,$platform);
        $appid = $appinfo['appid'];
        $appsecret = $appinfo['appsecret'];
        if($appinfo['authtype']==1){ //授权接入
            $component_appid = self::component_appid();
            $url = "https://api.weixin.qq.com/sns/oauth2/component/access_token?appid={$appid}&code={$code}&grant_type=authorization_code&component_appid={$component_appid}&component_access_token=".self::component_access_token($aid);
        }else{
            $url = "https://api.weixin.qq.com/sns/oauth2/access_token?appid={$appid}&secret={$appsecret}&code={$code}&grant_type=authorization_code";
        }

        $result = request_get($url);
        $rs = json_decode($result,true);
        //is_snapshotuser	是否为快照页模式虚拟账号，只有当用户是快照页模式虚拟账号时返回，值为1

        return $rs;
    }

    //第四步：拉取用户信息(需scope为 snsapi_userinfo) https://developers.weixin.qq.com/doc/offiaccount/OA_Web_Apps/Wechat_webpage_authorization.html#3
    public static function getUserInfo($openid,$access_token)
    {
        $url = "https://api.weixin.qq.com/sns/userinfo?access_token={$access_token}&openid={$openid}&lang=zh_CN";
        $result = request_get($url);
        $result = json_decode($result,true);
        return $result;
    }

	public static function jsapi_ticket($aid){
		$appinfo = System::appinfo($aid,'mp');
		$appid = $appinfo['appid'];
		$tokendata = Db::name('access_token')->where('appid',$appid)->find();
		if($tokendata['jsapi_ticket'] && $tokendata['ticket_expires_time'] > time()){
			return $tokendata['jsapi_ticket'];
		}else{
			$access_token = self::access_token($aid,'mp');
			if(!$access_token) return '';
			$url = 'https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token='.$access_token.'&type=jsapi';
			$res = json_decode(request_get($url));
			$jsapi_ticket = $res->ticket;
			if ($jsapi_ticket) {
				Db::name('access_token')->where('appid',$appid)->update(['jsapi_ticket'=>$jsapi_ticket,'ticket_expires_time'=>time()+7000]);
				return $jsapi_ticket;
			}else{
				return '';
				//echojson(['status'=>0,'msg'=>self::geterror($res)]);
			}
		}
	}
	
	public static function share_package($aid){
		$set = Db::name('admin_set')->where('aid',$aid)->find();
		$appinfo = System::appinfo($aid,'mp');
		$jsapiTicket = self::jsapi_ticket($aid);
		$appid = $appinfo['appid'];
		if(!$jsapiTicket) return [
			"appId"     => '',
			"nonceStr"  => '',
			"timestamp" => time(),
			"url"       => '',
			"signature" => '',
			"rawString" => '',
		];
		// 注意 URL 一定要动态获取，不能 hardcode.
		//$url = PRE_URL.'/h5/'.$aid.'.html';
		$url = Request::header('referer');
		$timestamp = time();
		$nonceStr = random(6);
		// 这里参数的顺序要按照 key 值 ASCII 码升序排序
		$string = "jsapi_ticket=$jsapiTicket&noncestr=$nonceStr&timestamp=$timestamp&url=$url";
		$signature = sha1($string);
		$signPackage = [
		  "appId"     => $appid,
		  "nonceStr"  => $nonceStr,
		  "timestamp" => $timestamp,
		  "url"       => $url,
		  "signature" => $signature,
		  "rawString" => $string
		];
		return $signPackage;
	}
	//获取卡券js接口用的 card_ticket
	public static function card_ticket($aid){
		$appinfo = System::appinfo($aid,'mp');
		$appid = $appinfo['appid'];
		$tokendata = Db::name('access_token')->where('appid',$appid)->find();
		if($tokendata['card_ticket'] && $tokendata['card_ticket_expires_time'] > time()){
			return $tokendata['card_ticket'];
		}else{
			$url = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?type=wx_card&access_token=".self::access_token($aid,'mp');
			$res = json_decode(request_get($url));
			$jcard_ticket = $res->ticket;
			if ($jcard_ticket) {
				Db::name('access_token')->where('appid',$appid)->update(['card_ticket'=>$jcard_ticket,'card_ticket_expires_time'=>time()+7000]);
				return $jcard_ticket;
			}else{
				return '';
				//if(request()->domain() != 'http://wxtt.com')
				//echojson(['status'=>0,'msg'=>self::geterror($res)]);
			}
		}
	}
	//上传到微信素材库
	public static function getmediaid($aid,$picurl,$type="image",$description=null){
		if(strpos($picurl,'/') === false){
			return $picurl;
		}
		$material = Db::name('mp_material')->where('aid',$aid)->where('url',$picurl)->where('type',$type)->where('description',$description)->find();
		if($material){
			return $material['media_id'];
		}
		$url = \app\commons\Pic::tolocal($picurl);
		$mediapath = ROOT_PATH.str_replace(PRE_URL.'/','',$url);
		$data = array('media'=>'@'.$mediapath);
		$data = [];
		$data['media'] = new \CurlFile($mediapath); 
		if($type == 'video'){
			$data['description'] = $description ? $description : jsonEncode(['title'=>date('点击视频查看'),'introduction'=>'本视频上传于'.date('Y年m月d日')]);
		}
        $res = curl_post('https://api.weixin.qq.com/cgi-bin/material/add_material?access_token='.self::access_token($aid,'mp').'&type='.$type,$data);
		$res = json_decode($res,true);
		if($res['media_id']){
			Db::name('mp_material')->insert(['aid'=>$aid,'url'=>$picurl,'media_id'=>$res['media_id'],'type'=>$type,'description'=>$description,'createtime'=>time()]);
			return $res['media_id'];
		}else{
			return ['status'=>0,'msg'=>self::geterror($res)];
		}
	}
	public static function pictomedia($aid,$platform,$picurl,$isyj=false){
		if(strpos($picurl,'/') === false){
			return $picurl;
		}
		$url = \app\commons\Pic::tolocal($picurl);
		$mediapath = ROOT_PATH.str_replace(PRE_URL.'/','',$url);
		$data = [];
		$data['media'] = new \CurlFile($mediapath); 
		$access_token = self::access_token($aid,$platform);
		if($isyj){//上传永久素材 小程序不行
			$url = 'https://api.weixin.qq.com/cgi-bin/material/add_material?access_token='.$access_token.'&type=image';
		}else{//上传临时素材
			$url = 'https://api.weixin.qq.com/cgi-bin/media/upload?access_token='.$access_token.'&type=image';
		}
		$res = curl_post($url,$data);
		$res = json_decode($res,true);
		if($res['media_id']){
			return $res['media_id'];
		}else{
			return ['status'=>0,'msg'=>self::geterror($res)];
		}
	}
    /**
     * 生成公众号二维码和小程序码
     * @param $aid
     * @param $platform wx:小程序，mp 公众号
     * @param $page 路径,开头不能加斜杠
     * @param array $scene 格式：['id' => 1, 'name' => 'demo']，最终参数格式：id_1-cid_2
     * @param int $bid  用于获取使用商家logo
     * @param bool $isUpload 是否上传
     * @return array
     */
	public static function getQRCode($aid,$platform,$page,$scene=[],$bid=0,$isUpload = true){
        $page = ltrim($page,'/');
        if(strpos($page,'?')){
            $pageArr = explode('?',$page);
            $page = $pageArr[0];
            $params =  explode('&',$pageArr[1]);
        }
        if(!empty($params)) {
            foreach($params as $v){
                if($v!=''){
                    $vArr = explode('=',$v);
                    $scene[$vArr[0]] = $vArr[1];
                }
            }
        }
        $sceneFormat = '';
        if (is_array($scene)) {
            $i = 1;
            foreach ($scene as $key => $val) {
                $sceneFormat .= $key . '_' . $val;
                if ($i < count($scene)) {
                    $sceneFormat .= '-';
                }
                $i++;
            }
        }else{
            $sceneFormat = $scene;
        }

		if($platform=='wx'){
            //获取不限制的小程序码 https://developers.weixin.qq.com/miniprogram/dev/OpenApiDoc/qrcode-link/qr-code/getUnlimitedQRCode.html
			$url = 'https://api.weixin.qq.com/wxa/getwxacodeunlimit?access_token='.self::access_token($aid,$platform);
			$data = array();
			$data['scene'] = $sceneFormat ? $sceneFormat : '0';
			$data['page'] = $page;
			$res = request_post($url,jsonEncode($data));//图片 Buffer 二进制
			$errmsg = json_decode($res,true);
			$errmsg = $errmsg??'';
			if($errmsg){
				return ['status'=>0,'msg'=>self::geterror($errmsg),'errmsg'=>self::geterror($errmsg),'rs'=>$errmsg,'errcode'=>$errmsg['errcode']];
			}

            if($isUpload){
                $imgurl = $res;
                //获取商家logo
                $business = Db::name('business')->where('aid',$aid)->where('id',$bid)->find();
                if($business && $business['logo']){
                    $old_logo = file_get_contents($business['logo']);
                    $logo = self::yuan_img($old_logo); // 头像裁剪成圆形
                    $sharePic = self::qrcode_with_logo($res, $logo); // 头像与二维码合并
                    $imgurl = $sharePic;
                }
                //上传
                $dir = 'upload/'.$aid.'/'.date('Ym');
                if(!is_dir(ROOT_PATH.$dir)) mk_dir(ROOT_PATH.$dir);
                $filename = date('d_His').rand(1000,9999).'.jpg';
                $mediapath = $dir.'/'.$filename;
                file_put_contents(ROOT_PATH.$mediapath,$imgurl);
                $url = \app\commons\Pic::uploadoss(PRE_URL.'/'.$mediapath,false,false);
                return ['status'=>1,'url'=>$url];
            }else{
                return ['status'=>1,'url'=>'','buffer'=>$res];
            }
		}elseif($platform == 'mp'){
			$url = 'https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token='.self::access_token($aid,$platform);
			$data = array();
			$data['action_name'] = 'QR_LIMIT_STR_SCENE';
			$data['action_info'] = array('scene'=>array('scene_str'=>$sceneFormat));
			$rs = request_post($url,jsonEncode($data));
			$rs = json_decode($rs,true);
			if($rs['url']){
				$url = createqrcode($rs['url'],'',$aid);
				return ['status'=>1,'url'=>$url];
			}else{
				return ['status'=>0,'msg'=>self::geterror($rs)];
			}
		}
	}
	 /**
     * [yuan_img 剪切图片为圆形]
     * @param  [type] $picture [图片数据流 比如file_get_contents(imageurl)返回的数据]
     * @return [type]          [图片数据流]
     */
    public static function yuan_img($picture)
    {
        $src_img = imagecreatefromstring($picture);
        $w = imagesx($src_img);
        $h = imagesy($src_img);
        $w = min($w, $h);
        $h = $w;
        $img = imagecreatetruecolor($w, $h);
        imagealphablending($img, false); // 设定图像的混色模式
        imagesavealpha($img, true); // 这一句一定要有（设置标记以在保存 PNG 图像时保存完整的 alpha 通道信息）
        $bg = imagecolorallocatealpha($img, 255, 255, 255, 127); // 拾取一个完全透明的颜色,最后一个参数127为全透明
        imagefill($img, 0, 0, $bg);
        $r = $w / 2; //圆半径
        $y_x = $r; //圆心X坐标
        $y_y = $r; //圆心Y坐标
        for ($x = 0; $x < $w; $x++) {
            for ($y = 0; $y < $h; $y++) {
                $rgbColor = imagecolorat($src_img, $x, $y);
                if (((($x - $r) * ($x - $r) + ($y - $r) * ($y - $r)) < ($r * $r))) {
                    imagesetpixel($img, $x, $y, $rgbColor);
                }
            }
        }
        /**
         * 如果想要直接输出图片，应该先设header。header("Content-Type: image/png; charset=utf-8");
         * 并且去掉缓存区函数
         */
        //获取输出缓存，否则imagepng会把图片输出到浏览器
        ob_start();
        imagepng($img);
        imagedestroy($img);
        $contents = ob_get_contents();
        ob_end_clean();
        return $contents;
    }

    /**
     * [qrcode_with_logo 在二维码的中间区域镶嵌图片]
     * @param  [type] $QR   [二维码数据流。比如file_get_contents(imageurl)返回的数据,或者微信给返回的数据]
     * @param  [type] $logo [中间显示图片的数据流。比如file_get_contents(imageurl)返回的东东]
     * @return [type]       [返回图片数据流]
     */
    public static function qrcode_with_logo($QR, $logo)
    {
        $QR = imagecreatefromstring($QR);
        $logo = imagecreatefromstring($logo);
        $QR_width = imagesx($QR); // 二维码图片宽度
        $QR_height = imagesy($QR); // 二维码图片高度
        $logo_width = imagesx($logo); // logo图片宽度
        $logo_height = imagesy($logo); // logo图片高度
        $logo_qr_width = $QR_width / 2.2; // 组合之后logo的宽度(占二维码的1/2.2)
        $scale = $logo_width / $logo_qr_width; // logo的宽度缩放比(本身宽度/组合后的宽度)
        $logo_qr_height = $logo_height / $scale; // 组合之后logo的高度
        $from_width = ($QR_width - $logo_qr_width) / 2; // 组合之后logo左上角所在坐标点
        /**
         * 重新组合图片并调整大小
         * imagecopyresampled() 将一幅图像(源图象)中的一块正方形区域拷贝到另一个图像中
         */
        imagecopyresampled($QR, $logo, $from_width, $from_width, 0, 0, $logo_qr_width, $logo_qr_height, $logo_width, $logo_height);
        /**
         * 如果想要直接输出图片，应该先设header。header("Content-Type: image/png; charset=utf-8");
         * 并且去掉缓存区函数
         */
        //获取输出缓存，否则imagepng会把图片输出到浏览器
        ob_start();
        imagepng($QR);
        imagedestroy($QR);
        imagedestroy($logo);
        $contents = ob_get_contents();
        ob_end_clean();
        return $contents;
    }
	//发模板消息 https://developers.weixin.qq.com/doc/offiaccount/Message_Management/Template_Message_Interface.html#5
	public static function sendtmpl($aid=1,$mid,$tmpltype,$content,$tourl='',$contentNew=[]){
	    
        if(is_numeric($mid)){
            $member = Db::name('member')->where('id',$mid)->field('id,nickname,mpopenid')->find();
            $openid = $member['mpopenid'];
        }else{
            $openid = $mid;
        }
        if(!$openid) return ['status'=>0,'msg'=>'openid为空'];
        $set = Db::name('mp_tmplset')->where('aid',$aid)->find();
        $tmplid = $set[$tmpltype];
        $tmplMessageLink = $set['tmpl_message_link'];
        if($contentNew){
            $setnew = Db::name('mp_tmplset_new')->where('aid',$aid)->find();
            $tmplidNew = $setnew[$tmpltype];
            $tmplMessageLink = $setnew['tmpl_message_link'];
        }
        if(!$tmplid && !$tmplidNew) return ['status'=>0,'msg'=>'未配置模板ID'];
        $access_token = self::access_token($aid,'mp');
        $url = 'https://api.weixin.qq.com/cgi-bin/message/template/send?access_token='.$access_token;
        $data = [];
        $data['template_id'] = trim($tmplid);
        if($tourl){
            $data['url'] = $tourl;
        }
        //模板消息链接 mp:公众号 wx:小程序
        $miniprogram = [];
        if($tmplMessageLink == 'wx'){
            //验证权限
            $platform = \app\commons\Common::getplatform($aid);
            if(in_array('wx',$platform)){
                $appid = Db::name('admin_setapp_wx')->where('aid',$aid)->value('appid');
                if($appid){
                    $miniprogram = [
                        'appid' => $appid,
                        'pagepath' => $tourl?getWxAppPath($tourl):'pages/index/index'
                    ];
                }
            }
        }
        if($miniprogram) $data['miniprogram'] = $miniprogram;
        $newcontent = [];
        foreach($content as $k=>$v){
            if($v == "[nickname]" && $member) {
                $v = $member['nickname'];
            }
            if(is_array($v)){
                $newcontent[$k] = $v;
            }else{
                $newcontent[$k] = ['value'=>strval($v)];
            }
        }
        $data['data'] = $newcontent;
        $data['touser'] = $openid;
//        syncRequest($url,str_replace('\\/','/',jsonEncode($data)));
        if($tmplidNew && $contentNew){
            $datanew = [];
            $datanew['template_id'] = trim($tmplidNew);
            if($miniprogram) $datanew['miniprogram'] = $miniprogram;
            if($tourl){
                $datanew['url'] = $tourl;
            }
            $newcontent = [];
            foreach($contentNew as $k=>$v){
                if($v == "[nickname]" && $member) {
                    $v = $member['nickname'];
                }
                if(is_array($v)){
                    $newcontent[$k] = $v;
                }else{
                    //格式处理
                    $newcontent[$k] = ['value'=>self::formatTmplParam($k,$v)];
                }
            }
            $datanew['data'] = $newcontent;
            $datanew['touser'] = $openid;
//            Log::write([
//                'file' => __FILE__ . ' L' . __LINE__,
//                'function' => __FUNCTION__,
//                '$datanew'=>$datanew
//            ]);
            syncRequest($url,str_replace('\\/','/',jsonEncode($datanew)));
//            syncRequest($url,str_replace('\\/','/',jsonEncode($datanew)),[],true);//开启debug
        }else{
            syncRequest($url,str_replace('\\/','/',jsonEncode($data)));
        }
		return ['status'=>1,'msg'=>'发送成功'];
		/*
        $rs = request_post($url,str_replace('\\/','/',jsonEncode($data)));
        $rs = json_decode($rs,true);
        if($rs['errcode']!=0){
            return ['status'=>0,'msg'=>self::geterror($rs)];
        }else{
            return ['status'=>1,'msg'=>'发送成功'];
        }
		*/
    }

    //格式化发送数据 否则失败 文档：https://developers.weixin.qq.com/doc/offiaccount/Subscription_Messages/api.html#send%E5%8F%91%E9%80%81%E8%AE%A2%E9%98%85%E9%80%9A%E7%9F%A5
    private static function formatTmplParam($type,$value)
    {
        $value = strval($value);
        if(strpos($type,'thing') !== false){
            //事物 	20个以内字符	可汉字、数字、字母或符号组合
            if($value == '' || is_null($value)){
                $value = '无';
            }else{
                $length = mb_strlen($value,'UTF-8');
                if($length > 20)
                    $value = mb_substr($value,0,20,'UTF-8');
            }
        }elseif(strpos($type,'number') !== false && strpos($type,'phone_number') === false && strpos($type,'car_number') === false){
            //数字	32位以内数字	只能数字，可带小数
            if($value == '' || is_null($value)){
                $value = '';
            }else{
                $length = mb_strlen($value,'UTF-8');
                if($length > 32)
                    $value = mb_substr($value,0,32,'UTF-8');
            }
            $value = floatval($value);
        }elseif(strpos($type,'character_string') !== false){
            //字符串	32位以内数字、字母或符号	可数字、字母或符号组合
            if($value == '' || is_null($value)){
                $value = '无';
            }else{
                $length = mb_strlen($value,'UTF-8');
                if($length > 32)
                    $value = mb_substr($value,0,32,'UTF-8');
            }
        }elseif(strpos($type,'amount') !== false){
            //金额	1个币种符号+12位以内纯数字，可带小数，结尾可带“元”	可带小数
            if($value == '' || is_null($value)){
                $value = '0';
            }else{
                $length = mb_strlen($value,'UTF-8');
                if($length > 12)
                    $value = mb_substr($value,0,12,'UTF-8');
            }
        }elseif(strpos($type,'phone_number') !== false){
            //电话	17位以内，数字、符号	电话号码，例：+86-0766-66888866
            if($value == '' || is_null($value)){
                $value = '无';
            }else{
                $length = mb_strlen($value,'UTF-8');
                if($length > 17)
                    $value = mb_substr($value,0,17,'UTF-8');
            }
        }elseif(strpos($type,'car_number') !== false){
            //电话	车牌	8位以内，第一位与最后一位可为汉字，其余为字母或数字	车牌号码：粤A8Z888挂
            if($value == '' || is_null($value)){
                $value = '无';
            }else{
                $length = mb_strlen($value,'UTF-8');
                if($length > 8)
                    $value = mb_substr($value,0,8,'UTF-8');
            }
        }elseif(strpos($type,'name') !== false){
            //姓名	10个以内纯汉字或20个以内纯字母或符号	中文名10个汉字内；纯英文名20个字母内；中文和字母混合按中文名算，10个字内
            if($value == '' || is_null($value)){
                $value = '无';
            }else{
                $length = mb_strlen($value,'UTF-8');
                if($length > 10)
                    $value = mb_substr($value,0,10,'UTF-8');
            }
        }elseif(strpos($type,'phrase') !== false){
            //汉字	5个以内汉字	5个以内纯汉字，例如：配送中
            if($value == '' || is_null($value)) {
                $value = '无';
            }elseif(isAllChinese($value)){
                $length = mb_strlen($value,'UTF-8');
                if($length > 5)
                    $value = mb_substr($value,0,5,'UTF-8');
            }else{
                $value = '非汉字昵称';
            }
        }

        return $value;
    }

	//发订阅消息
	public static function sendwxtmpl($aid=1,$mid,$tmpltype,$contentnew,$tourl='',$content){
		if(is_numeric($mid)){
			$openid = Db::name('member')->where('id',$mid)->value('wxopenid');
		}else{
			$openid = $mid;
		}
		if(!$openid) return ['status'=>0,'msg'=>'openid为空'];
		$set = Db::name('wx_tmplset')->where('aid',$aid)->find();
		$tmpltypenew = $tmpltype.'_new';
		$tmplid = $set[$tmpltype.'_new'];
		if(!$tmplid){
			$tmplid = $set[$tmpltype];
		}else{
			$content = $contentnew;
		}
		if(!$tmplid) return ['status'=>0,'msg'=>'未配置模板ID'];
		$access_token = self::access_token($aid,'wx');
		$url = 'https://api.weixin.qq.com/cgi-bin/message/subscribe/send?access_token='.$access_token;
		$data = [];
		$data['template_id'] = trim($tmplid);
		if($tourl){
			$data['page'] = $tourl;
		}
		$newcontent = [];
		foreach($content as $k=>$v){
			if(is_array($v)){
				$newcontent[$k] = $v;
			}else{
				if(strpos($k,'thing') === 0){
					if(!$v) $v = '暂无';
					$v = mb_substr($v,0,20);
				}
				if($v=='' && strpos($k,'character_string') === 0){
					$v = 'empty';
				}
				$v = strval($v);
				$newcontent[$k] = ['value'=>$v];
			}
		}
		$data['data'] = $newcontent;
		$data['touser'] = $openid;
		//$rs = request_post($url,str_replace('\\/','/',jsonEncode($data)));
		syncRequest($url,str_replace('\\/','/',jsonEncode($data)));
		//\think\facade\Log::write($rs);
		//\think\facade\Log::write($data);
//		$rs = json_decode($rs,true);
		//if($rs['errcode']!=0){
		//	return ['status'=>0,'msg'=>self::geterror($rs)];
		//}else{
			return ['status'=>1,'msg'=>'发送成功'];
		//}
	}
    //发模板消息 后台人员
    public static function sendhttmplByUids($aid,$uids,$tmpltype,$content,$tourl='',$mdid=0){
        $where = [];
        $where[] = ['aid','=',$aid];
        $where[] = ['mid','<>',0];
        $where[] = [$tmpltype,'=',1];
        $where[] = ['id','in',$uids];
        $userlist = Db::name('admin_user')->where($where)->select()->toArray();
//        \think\facade\Log::write(Db::name('admin_user')->getlastsql());
        if(!$userlist){
            return ['status'=>0,'msg'=>'没有设置接收用户'];
        }
        $mids = [];
        foreach($userlist as $user){
            if($user['isadmin']==0){
                if($user['groupid']){
                    $group = Db::name('admin_user_group')->where('id',$user['groupid'])->find();
                    $user['notice_auth_data'] = $group['notice_auth_data'];
                    $user['mdid'] = $group['mdid'];
                    if($user['mdid'] != 0 && $user['mdid']!=$mdid) continue;
                }
                $notice_auth_data = json_decode($user['notice_auth_data'],true);
                if(!$notice_auth_data) continue;
                if(!in_array($tmpltype,$notice_auth_data)){
                    continue;
                }
            }
            self::sendtmpl($aid,$user['mid'],$tmpltype,$content,$tourl,$content);
        }
        return ['status'=>1,'msg'=>'发送成功'];
    }
	//发模板消息 后台人员
	public static function sendhttmpl($aid,$bid,$tmpltype,$content,$tourl='',$mdid=0,$contentNew=[]){
        if(!$mdid) $mdid = 0;
        if(!$bid) $bid = 0;
        if(strlen($tmpltype) == 43){
            $tmplid = $tmpltype;
            $tmpltype = '1';
        }else{
            $set = Db::name('mp_tmplset')->where('aid',$aid)->find();
            $tmplid = $set[$tmpltype];
            if($contentNew){
                $setnew = Db::name('mp_tmplset_new')->where('aid',$aid)->find();
                $tmplidNew = $setnew[$tmpltype];
            }
        }
        //if(!$tmplid) return ['status'=>0,'msg'=>'未配置模板ID'];
		if($tmplid){
			$access_token = self::access_token($aid,'mp');
			$url = 'https://api.weixin.qq.com/cgi-bin/message/template/send?access_token='.$access_token;
			$data = [];
			$data['template_id'] = trim($tmplid);
			if($tourl){
				$data['url'] = $tourl;
			}
			$newcontent = [];
			foreach($content as $k=>$v){
				if(is_array($v)){
					$newcontent[$k] = $v;
				}else{
					$newcontent[$k] = ['value'=>strval($v)];
				}
			}
			$data['data'] = $newcontent;
            if(!isset($set)){
                $set = Db::name('mp_tmplset')->where('aid',$aid)->find();
            }
            //模板消息链接 mp:公众号 wx:小程序
            if($set['tmpl_message_link'] == 'wx'){
                //验证权限
                $platform = \app\commons\Common::getplatform($aid);
                if(in_array('wx',$platform)) {
                    $appid = Db::name('admin_setapp_wx')->where('aid', $aid)->value('appid');
                    if ($appid) {
                        $data['miniprogram'] = [
                            'appid' => $appid,
                            'pagepath' => $tourl ? getWxAppPath($tourl) : 'pages/index/index'
                        ];
                    }
                }
            }
		}
        if($tmplidNew && $contentNew){
            $access_token = self::access_token($aid,'mp');
            $urlnew = 'https://api.weixin.qq.com/cgi-bin/message/template/send?access_token='.$access_token;
            $datanew = [];
            $datanew['template_id'] = trim($tmplidNew);
            if($tourl){
                $datanew['url'] = $tourl;
            }
            $newcontent = [];
            foreach($contentNew as $k=>$v){
                if(is_array($v)){
                    $newcontent[$k] = $v;
                }else{
                    $newcontent[$k] = ['value'=>self::formatTmplParam($k,$v)];
                }
            }
            $datanew['data'] = $newcontent;
            if(!isset($setnew)){
                $setnew = Db::name('mp_tmplset_new')->where('aid',$aid)->find();
            }
            //模板消息链接 mp:公众号 wx:小程序
            if($setnew['tmpl_message_link'] == 'wx'){
                //验证权限
                $platform = \app\commons\Common::getplatform($aid);
                if(in_array('wx',$platform)) {
                    $appid = Db::name('admin_setapp_wx')->where('aid',$aid)->value('appid');
                    if($appid){
                        $datanew['miniprogram'] = [
                            'appid' => $appid,
                            'pagepath' => $tourl?getWxAppPath($tourl):'pages/index/index'
                        ];
                    }
                }
            }
        }
        $userlist = Db::name('admin_user')->where("`aid`={$aid} and `bid`={$bid} and `mid`!=0 and `{$tmpltype}`=1 and (`mdid`=0 or `mdid`='{$mdid}' or `groupid`>0)")->select()->toArray();
        //\think\facade\Log::write(Db::getlastsql());
        if(getcustom('order_tongcheng',$aid) && $bid>0 && $tmpltype=='tmpl_orderpay'){
            $umids = array_column($userlist,'mid');
            $awhere = [];
            $awhere[] = ['aid','=',$aid];
//            $awhere[] = ['isadmin','>',0];
            $awhere[] = Db::raw("find_in_set('\"tmpl_orderpay\"',notice_auth_data)");
            $awhere[] = ['bid','=',0];
            $awhere[] = ['mid','<>',0];
            if($umids){
                $awhere[] = ['mid','not in',$umids];
            }
            $midsAdminlist = Db::name('admin_user')->where($awhere)->select()->toArray();
            if($midsAdminlist){
                $userlist = array_merge($userlist,$midsAdminlist);
            }
        }
        if(!$userlist){
            \think\facade\Log::write([
                'file'=>__FILE__.' '.__LINE__,
                'error'=>'没有设置接收用户'
            ]);
            return ['status'=>0,'msg'=>'没有设置接收用户'];
        }

        $mids = [];
        foreach($userlist as $user){
			if($user['isadmin']==0){
				if($user['groupid']){
					$group = Db::name('admin_user_group')->where('id',$user['groupid'])->find();
					$user['notice_auth_data'] = $group['notice_auth_data'];
					$user['mdid'] = $group['mdid'];
                    if($user['mdid'] != 0 && $user['mdid']!=$mdid) {
//                        \think\facade\Log::write([
//                            'file'=>__FILE__.' '.__LINE__,
//                            'error'=>'非门店管理员',
//                            'user'=>$user['un']
//                        ]);
                        continue;
                    }
				}
				$notice_auth_data = json_decode($user['notice_auth_data'],true);
				if(!$notice_auth_data) continue;
				if(!in_array($tmpltype,$notice_auth_data)){
//                    \think\facade\Log::write([
//                        'file'=>__FILE__.' '.__LINE__,
//                        'error'=>'没有开启通知',
//                        'user'=>$user['un']
//                    ]);
					continue;
				}
			}
            $mids[] = $user['mid'];
			if($tmplid){
				$user['mpopenid'] = Db::name('member')->where('id',$user['mid'])->value('mpopenid');
				if(!$user['mpopenid']) continue;
				
				$data['touser'] = $user['mpopenid'];
				//$rs = request_post($url,str_replace('\\/','/',jsonEncode($data)));
				syncRequest($url,str_replace('\\/','/',jsonEncode($data)));
                //\think\facade\Log::write($rs);
				//\think\facade\Log::write($user);
			}
            if($tmplidNew){
                if(!$user['mpopenid'])
                $user['mpopenid'] = Db::name('member')->where('id',$user['mid'])->value('mpopenid');
                if(!$user['mpopenid']) continue;

                $datanew['touser'] = $user['mpopenid'];
                $rs = syncRequest($urlnew,str_replace('\\/','/',jsonEncode($datanew)),[],false);
            }
        }

        if(defined('PRE_URL2') && PRE_URL2 !=''){
            $pre_url = PRE_URL2;
        }elseif(defined('PRE_URL')){
            $pre_url = PRE_URL;
        }
        if ($pre_url) {
            $urlstart = $pre_url.'/h5/'.$aid.'.html#';
            $pagepath = str_replace($urlstart,'',$tourl);
            send_socket(['type'=>'notice','data'=>['aid'=>$aid,'mids'=>$mids,'title'=>$content['first'],'desc'=>$content['remark'],'url'=>$pagepath]]);
        } else {
            if($tourl) {
                $url_arr = explode('.html#', $tourl);
                $pagepath = $url_arr[1];
                $search = '~^(([^:/?#]+):)?(//([^/?#]*))?([^?#]*)(\?([^#]*))?(#(.*))?~i';
                preg_match_all($search,$tourl,$array2);
                $local_url = $array2[1][0].$array2[3][0];
                send_socket(['type'=>'notice','data'=>['aid'=>$aid,'mids'=>$mids,'title'=>$content['first'],'desc'=>$content['remark'],'url'=>$pagepath]], $local_url);
            }
        }

        $rsArr = json_decode($rs,true);
        if($rsArr['errcode']!=0){
            \think\facade\Log::write([
                'file'=>__FILE__.' '.__LINE__,
                'error'=>self::geterror($rsArr)
            ]);
            return ['status'=>0,'msg'=>self::geterror($rsArr)];
        }else{
            return ['status'=>1,'msg'=>'发送成功'];
        }
    }
	//发订阅消息 后台人员
	public static function sendhtwxtmpl($aid,$bid,$tmpltype,$content,$tourl='',$mdid=0){
		if(!$mdid) $mdid = 0;
        if(!$bid) $bid = 0;
        if(strlen($tmpltype) == 43){
            $tmplid = $tmpltype;
            $tmpltype = '1';
        }else{
			$set = Db::name('wx_tmplset')->where('aid',$aid)->find();
			if($tmpltype == 'tmpl_orderpay'){
				$tmplid = $set['tmpl_orderconfirm'];
			}else{
				$tmplid = $set[$tmpltype];
			}
		}
		//\think\facade\Log::write($set);
		//\think\facade\Log::write($tmplid);
		//\think\facade\Log::write($tmpltype);

		if(!$tmplid) return ['status'=>0,'msg'=>'未配置模板ID'];
		$access_token = self::access_token($aid,'wx');
		$url = 'https://api.weixin.qq.com/cgi-bin/message/subscribe/send?access_token='.$access_token;
		$data = [];
		$data['template_id'] = trim($tmplid);
		if($tourl){
			$data['page'] = $tourl;
		}
		$newcontent = [];
		foreach($content as $k=>$v){
			if(is_array($v)){
				$newcontent[$k] = $v;
			}else{
				if(strpos($k,'thing') === 0){
					if(!$v) $v = '暂无';
					$v = mb_substr($v,0,20);
				}
				if(strpos($k,'name') === 0){ //字母或中文 10个字符内
					if($v && preg_match("/^[\x41-\x5a\x61-\x7a\x80-\xff]+$/", $v)){
						$v = mb_substr($v,0,10);
					}else{
						$v = '未知';
					}
				}
				if($v=='' && strpos($k,'character_string') === 0){
					$v = 'empty';
				}
				$v = strval($v);
				$newcontent[$k] = ['value'=>$v];
			}
		}
		$data['data'] = $newcontent;

		$userlist = Db::name('admin_user')->where("`aid`={$aid} and `bid`={$bid} and `mid`!=0 and `{$tmpltype}`=1 and (`mdid`=0 or `mdid`='{$mdid}' or `groupid`>0)")->select()->toArray();
        //\think\facade\Log::write(Db::getlastsql());
        if(!$userlist){
            return ['status'=>0,'msg'=>'没有设置接收用户'];
        }
        $mids = [];
        foreach($userlist as $user){
			if($user['isadmin']==0){
				if($user['groupid']){
					$group = Db::name('admin_user_group')->where('id',$user['groupid'])->find();
					$user['notice_auth_data'] = $group['notice_auth_data'];
					$user['mdid'] = $group['mdid'];
					if($user['mdid'] != 0 && $user['mdid']!=$mdid) continue;
				}
				$notice_auth_data = json_decode($user['notice_auth_data'],true);
				if(!$notice_auth_data) continue;
				if(!in_array($tmpltype,$notice_auth_data)){
					continue;
				}
			}
            $mids[] = $user['mid'];
            $user['wxopenid'] = Db::name('member')->where('id',$user['mid'])->value('wxopenid');
            if(!$user['wxopenid']) continue;
            
            $data['touser'] = $user['wxopenid'];
            //$rs = request_post($url,str_replace('\\/','/',jsonEncode($data)));
			syncRequest($url,str_replace('\\/','/',jsonEncode($data)));
            //\think\facade\Log::write($rs);
            //\think\facade\Log::write($data);
			
			$tmplnum = Db::name('member_tmplnum')->where('aid',$aid)->where('mid',$user['mid'])->where('tmplid',$tmplid)->value('num');
			if($tmplnum > 0){
				Db::name('member_tmplnum')->where('aid',$aid)->where('mid',$user['mid'])->where('tmplid',$tmplid)->dec('num')->update();
			}
        }

		//if($rs['errcode']!=0){
		//	return ['status'=>0,'msg'=>self::geterror($rs)];
		//}else{
			return ['status'=>1,'msg'=>'发送成功'];
		//}
	}

	//会员卡信息变更
	public static function updatemembercard($aid,$mid,$record_bonus=''){
		$member = Db::name('member')->where('aid',$aid)->where('id',$mid)->find();
		if($member['card_id'] && $member['card_code']){
			$url = 'https://api.weixin.qq.com/card/get?access_token='.self::access_token($aid,'mp');
			$rs = request_post($url,jsonEncode(['card_id'=>$member['card_id']]));
			$rs = json_decode($rs,true);
			//\think\facade\Log::write($rs);
			$custom_field1 = $rs['card']['member_card']['custom_field1'];
			$custom_field2 = $rs['card']['member_card']['custom_field2'];
			$custom_field3 = $rs['card']['member_card']['custom_field3'];
			$supply_bonus = $rs['card']['member_card']['supply_bonus'];

			$membercard = Db::name('membercard')->where('aid',$aid)->where('card_id',$member['card_id'])->find();

			$url = 'https://api.weixin.qq.com/card/membercard/updateuser?access_token='.self::access_token($aid,'mp');
			$postdata = [];
			$postdata['card_id'] = $member['card_id'];
			$postdata['code'] = $member['card_code'];
			if($supply_bonus){
				$postdata['bonus'] = $member['score'];
				if($record_bonus){
					$postdata['record_bonus'] = $record_bonus;
				}
			}
			$notify_optional = [];
			if($custom_field1){
				if($custom_field1['name_type']=='FIELD_NAME_TYPE_COUPON'){//优惠券
					$couponcount = Db::name('coupon_record')->where('aid',$aid)->where('mid',$member['id'])->where('status',0)->where('endtime','>=',time())->count();
					$postdata['custom_field_value1'] = $couponcount;
				}elseif($custom_field1['name_type']=='FIELD_NAME_TYPE_LEVEL'){//等级
					$memberlv = Db::name('member_level')->where('aid',$aid)->where('id',$member['levelid'])->find();
					$postdata['custom_field_value1'] = $memberlv['name'];
				}elseif($custom_field1['name']=='余额' || $custom_field1['name']==t('余额')){//余额
					$postdata['custom_field_value1'] = $member['money'];
				}elseif($custom_field1['name']==$membercard['custom_field_customize1_name']){
					$postdata['custom_field_value1'] = $membercard['custom_field_customize1_value'];
				}elseif($custom_field1['name']==$membercard['custom_field_customize2_name']){
					$postdata['custom_field_value1'] = $membercard['custom_field_customize2_value'];
				}
				$notify_optional['is_notify_custom_field1'] = true;
			}
			if($custom_field2){
				if($custom_field2['name_type']=='FIELD_NAME_TYPE_COUPON'){//优惠券
					$couponcount = Db::name('coupon_record')->where('aid',$aid)->where('mid',$member['id'])->where('status',0)->where('endtime','>=',time())->count();
					$postdata['custom_field_value2'] = $couponcount;
				}elseif($custom_field2['name_type']=='FIELD_NAME_TYPE_LEVEL'){//等级
					$memberlv = Db::name('member_level')->where('aid',$aid)->where('id',$member['levelid'])->find();
					$postdata['custom_field_value2'] = $memberlv['name'];
				}elseif($custom_field2['name']=='余额' || $custom_field2['name']==t('余额')){//余额
					$postdata['custom_field_value2'] = $member['money'];
				}elseif($custom_field2['name']==$membercard['custom_field_customize1_name']){
					$postdata['custom_field_value2'] = $membercard['custom_field_customize1_value'];
				}elseif($custom_field2['name']==$membercard['custom_field_customize2_name']){
					$postdata['custom_field_value2'] = $membercard['custom_field_customize2_value'];
				}
				$notify_optional['is_notify_custom_field2'] = true;
			}
			if($custom_field3){
				if($custom_field3['name_type']=='FIELD_NAME_TYPE_COUPON'){//优惠券
					$couponcount = Db::name('coupon_record')->where('aid',$aid)->where('mid',$member['id'])->where('status',0)->where('endtime','>=',time())->count();
					$postdata['custom_field_value3'] = $couponcount;
				}elseif($custom_field3['name_type']=='FIELD_NAME_TYPE_LEVEL'){//等级
					$memberlv = Db::name('member_level')->where('aid',$aid)->where('id',$member['levelid'])->find();
					$postdata['custom_field_value3'] = $memberlv['name'];
				}elseif($custom_field3['name']=='余额' || $custom_field3['name']==t('余额')){//余额
					$postdata['custom_field_value3'] = $member['money'];
				}elseif($custom_field2['name']==$membercard['custom_field_customize1_name']){
					$postdata['custom_field_value3'] = $membercard['custom_field_customize1_value'];
				}elseif($custom_field2['name']==$membercard['custom_field_customize2_name']){
					$postdata['custom_field_value3'] = $membercard['custom_field_customize2_value'];
				}
				$notify_optional['is_notify_custom_field3'] = true;
			}
			if($notify_optional){
				$postdata['notify_optional'] = $notify_optional;
			}
			syncRequest($url,jsonEncode($postdata));
			//\think\facade\Log::write($rs);
			return [];
		}
	}
	public static function setauthinfo($aid,$authorization_code,$createtype=0){
		//使用授权码换取公众号或小程序的接口调用凭据和授权信息
		$url = 'https://api.weixin.qq.com/cgi-bin/component/api_query_auth?component_access_token='.self::component_access_token();
		$data = array();
		$data['component_appid'] = self::component_appid();
		$data['authorization_code'] = $authorization_code;
		$rs = request_post($url,jsonEncode($data));
		//dump($rs);die; 
		$rs = json_decode($rs,true);
		if(isset($rs['errcode']) && $rs['errcode']!=0){
			return ['status'=>0,'msg'=>self::geterror($rs)];
		}
		$info = $rs['authorization_info'];
		$appid = $info['authorizer_appid'];
		$refresh_token = $info['authorizer_refresh_token'];
		if($info){
			$update = array();
			$update['appid'] = $appid;
			$update['access_token'] = $info['authorizer_access_token'];
			$update['expires_time'] = time() + 7000;
			$update['jsapi_ticket'] = '';
			$update['ticket_expires_time'] = null;
			if(Db::name('access_token')->where('appid',$update['appid'])->find()){
				Db::name('access_token')->where('appid',$update['appid'])->update($update);
			}else{
				Db::name('access_token')->insert($update);
			}
		}
		//获取授权方的帐号基本信息
		$url = 'https://api.weixin.qq.com/cgi-bin/component/api_get_authorizer_info?component_access_token='.self::component_access_token();
		$data = array();
		$data['component_appid'] = self::component_appid();
		$data['authorizer_appid'] = $appid;
		$rs = request_post($url,jsonEncode($data));
		$rs = json_decode($rs,true);
		if(isset($rs['errcode']) && $rs['errcode']!=0){
			return ['status'=>0,'msg'=>self::geterror($rs)];
		}

		$authorizer_info = $rs['authorizer_info'];
		$authorization_info = $rs['authorization_info'];

		$infodata = array();
		$infodata['aid'] = $aid;
		$infodata['authtype'] = 1;
		$infodata['appid'] = $appid;
		$infodata['nickname'] = $authorizer_info['nick_name'];
		$infodata['headimg'] = \app\commons\Pic::tolocal($authorizer_info['head_img']);
		$infodata['qrcode'] = \app\commons\Pic::tolocal($authorizer_info['qrcode_url']);
		$infodata['refresh_token'] = $refresh_token;
		if($authorizer_info['MiniProgramInfo']){
			$apptype = 'wx';
			$verify_type_info = $authorizer_info['verify_type_info']['id'];
			if($verify_type_info > -1){
				$infodata['level'] = 1; //已认证
			}else{
				$infodata['level'] = 0; //未认证
			}
			$infodata['createtype'] = $createtype;
			if(Db::name('admin_setapp_wx')->where('aid',$aid)->find()){
				Db::name('admin_setapp_wx')->where('aid',$aid)->update($infodata);
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
			if(Db::name('admin_setapp_mp')->where('aid',$aid)->find()){
				Db::name('admin_setapp_mp')->where('aid',$aid)->update($infodata);
			}else{
				Db::name('admin_setapp_mp')->insert($infodata);
			}
			Db::name('mp_material')->where('aid',$aid)->delete();
		}
		$set = Db::name('admin_set')->where('aid',$aid)->find();
		if($set['name'] == '商城系统' && $set['logo'] == request()->domain().'/static/imgsrc/logo.jpg'){
			Db::name('admin_set')->where('aid',$aid)->update(['name'=>$infodata['nickname'],'logo'=>$infodata['headimg'],'desc'=>$authorizer_info['signature']]);
		}
		$access_token = \app\commons\Wechat::access_token($aid,$apptype);
		if($apptype == 'wx'){
			//设置小程序服务器域名
			$url = 'https://api.weixin.qq.com/wxa/modify_domain?access_token='.$access_token;
			$postdata = array();
			$postdata['action'] = 'set';
			$postdata['requestdomain'] = array(str_replace('http://','https://',request()->domain()));
			$postdata['wsrequestdomain'] = array('wss://'.$_SERVER['HTTP_HOST']);
			$postdata['uploaddomain'] = array(str_replace('http://','https://',request()->domain()));
			$postdata['downloaddomain'] = array(str_replace('http://','https://',request()->domain()));
			$rs = request_post($url,jsonEncode($postdata));
			//dump($rs);
			//设置小程序业务域名
			$url = 'https://api.weixin.qq.com/wxa/setwebviewdomain?access_token='.$access_token;
			$postdata = array();
			//$postdata['action'] = 'add';
			$postdata['webviewdomain'] = array(request()->domain());
			$rs = request_post($url,jsonEncode($postdata));
			//dump($rs);die;
		}
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
			Db::name('admin_setapp_wx')->where('aid',$aid)->update(['open_appid'=>$open_appid]);
			\app\commons\System::plog('授权绑定小程序');
		}else{
			Db::name('admin_setapp_mp')->where('aid',$aid)->update(['open_appid'=>$open_appid]);
			\app\commons\System::plog('授权绑定公众号');
		}
		return ['status'=>1];
	}

    /**
     * 微信公众平台返回错误码对应含义
     * @param $rs
     * @return mixed|string
     * 公众号全局返回码说明:https://developers.weixin.qq.com/doc/offiaccount/Getting_Started/Global_Return_Code.html
     * 开放平台返回码：https://developers.weixin.qq.com/doc/oplatform/Return_codes/Return_code_descriptions_new.html
     * 微信卡券返回码：https://developers.weixin.qq.com/doc/offiaccount/Cards_and_Offer/Card_coupons_error_code.html
     * 注意！！！注意！！！注意！！！注意！！！注意！！！与公共返回码冲突的请勿加入此处，需要独立定义返回码错误转换方法
     */
	public static function geterror($rs){
		if(is_object($rs)) $rs = (array)$rs;
        $err = array(
            '-1'      => '系统繁忙，请稍候再试',
            '0'       => '请求成功',
            '40001'   => '获取 access_token 时 AppSecret 错误，或者 access_token 无效。请认真比对 AppSecret 的正确性，或查看是否正在为恰当的公众号调用接口',
            '40002'   => '不合法的凭证类型',
            '40003'   => '不合法的openid，请确认是否已关注公众号',
            '40004'   => '不合法的媒体文件类型',
            '40005'   => '不合法的文件类型',
            '40006'   => '不合法的文件大小',
            '40007'   => '不合法的media_id',
            '40008'   => '不合法的消息类型',
            '40009'   => '不合法的图片大小',
            '40010'   => '不合法的语音大小',
            '40011'   => '不合法的视频大小',
            '40012'   => '不合法的缩略图大小',
            '40013'   => '不合法的appid，检查 AppID 的正确性，避免异常字符，注意大小写',
            '40014'   => '不合法的access_token，请认真比对 access_token 的有效性（如是否过期），或查看是否正在为恰当的公众号调用接口',
            '40015'   => '不合法的菜单类型',
            '40016'   => '不合法的按钮个数',
            '40017'   => '不合法的按钮类型',
            '40018'   => '不合法的按钮名称长度',
            '40019'   => '不合法的按钮key长度',
            '40020'   => '不合法的url长度',
            '40021'   => '不合法的菜单版本号',
            '40022'   => '不合法的子菜单级数',
            '40023'   => '不合法的子菜单按钮个数',
            '40024'   => '不合法的子菜单类型',
            '40025'   => '不合法的子菜单按钮名称长度',
            '40026'   => '不合法的子菜单按钮key长度',
            '40027'   => '不合法的子菜单按钮url长度',
            '40028'   => '不合法的自定义菜单使用用户',
            '40029'   => '不合法或已过期的code',//无效的 oauth_code
            '40030'   => '不合法的refresh_token',
            '40031'   => '不合法的 openid 列表',
            '40032'   => '不合法的 openid 列表长度',
            '40033'   => '不合法的请求字符，不能包含 \uxxxx 格式的字符',
            '40035'   => '不合法的参数',
            '40036'   => '不合法的template_id长度',
            '40037'   => '不合法的template_id',
            '40038'   => '不合法的请求格式',
            '40039'   => '不合法的url长度',
            '40048'   => '不合法的url域名',
            '40050'   => '不合法的分组 id',
            '40051'   => '分组名字不合法',
            '40054'   => '不合法的子菜单url域名',
            '40055'   => '不合法的菜单url域名',

            //开放平台
            '40101'   => '缺少必填参数',
            '41001'   => '缺少AccessToken',

            //微信卡券返回码：https://developers.weixin.qq.com/doc/offiaccount/Cards_and_Offer/Card_coupons_error_code.html
            '40053'   => '不合法的actioninfo，请开发者确认参数正确',
            '40056'   => '不合法的Code码',
            '40071'   => '不合法的卡券类型',
            '40072'   => '不合法的编码方式',
            '40078'   => '不合法的卡券状态',
            '40079'   => '不合法的时间',
            '40080'   => '不合法的CardExt',
            '40099'   => '卡券已被核销',
            '40100'   => '不合法的时间区间',
            '40116'   => '不合法的Code码',
            '40122'	  => '不合法的库存数量',
            '40124'	  => '会员卡设置查过限制的 custom_field字段',
            '40127'	  => '卡券被用户删除或转赠中',
            '41012'   => '缺少cardid参数',
            '45030'	  => '该cardid无接口权限',
            '45031'	  => '库存为0',
            '41011'	  => '缺少必填字段',
            '45021'	  => '字段超过长度限制，请参考相应接口的字段说明',
            '43009'	  => '无自定义SN权限，请前往公众平台申请',
            '43010'	  => '无储值权限，请前往公众平台申请',
            //微信卡券返回码 end

            '42001'   => 'access_token已过期',
            '42002'   => 'refresh_token超时',
            '42003'   => 'oauth_code超时',
            '42004'   => 'plugin token expired',
            '42005'   => 'api usage expired',
            '42006'   => 'component_access_token expired',
            '42007'   => '用户修改微信密码， accesstoken 和 refreshtoken 失效，需要重新授权',
            '42008'   => 'voip call key expired',
            '42009'   => 'client tmp token expired',
            '42010'   => '相同 media_id 群发过快，请重试',
            '43001'   => '需要使用get方法请求',
            '43002'   => '需要使用post方法请求',
            '43003'   => '需要使用https请求',
            '43004'   => '需要接收者关注',
            '43005'   => '需要好友关系',
            '43019'   => '需要将接收者从黑名单中移除',
            '43116'   => '该模板因滥用被滥用过多，已被限制下发',
            '44001'   => '多媒体文件为空',
            '44002'   => 'post 的数据包为空',
            '44003'   => '图文消息内容为空',
            '44004'   => '文本消息内容为空',
            '44005'   => '空白的列表',
            '44008'   => '音频内容审核失败',
            '44009'   => '图文中的音频内容审核失败',
            '45001'   => '多媒体文件大小超过限制',
            '45002'   => '消息内容大小超过限制',
            '45003'   => '标题字段超过限制',
            '45004'   => '描述字段超过限制',
            '45005'   => '链接字段超过限制',
            '45006'   => '图片链接字段超过限制',
            '45007'   => '语音播放时间超过限制',
            '45008'   => '图文消息超过限制',
            '45009'   => '接口调用超过限制',
            '45010'   => '创建菜单个数超过限制',
            '45011'   => 'api 调用太频繁，请稍候再试',
            '45012'   => '模板大小超过限制',
            '45015'   => '回复时间超过限制',
            '45016'   => '不能修改默认组',
            '45017'   => '分组名字过长',
            '45018'   => '分组数量超过上限',
            '45047'   => '客服接口下行条数超过上限',
            '45064'   => '创建菜单包含未关联的小程序',
            '45065'   => '相同 clientmsgid 已存在群发记录，返回数据中带有已存在的群发任务的 msgid',
            '45066'   => '相同 clientmsgid 重试速度过快，请间隔1分钟重试',
            '45067'   => 'clientmsgid 长度超过限制',
            '45110'   => '作者字数超出限制',
            '46001'   => '不存在媒体数据',
            '46002'   => '不存在的菜单版本',
            '46003'   => '不存在的菜单数据',
            '46004'   => '指定的用户不存在',
            '47001'   => '解析 json/xml 内容错误',
            '47003'   => '参数值不符合限制要求，详情可参考参数值内容限制说明',

            '48001'   => 'api 功能未授权，请确认公众号已获得该接口，可以在公众平台官网 - 开发者中心页中查看接口权限',
            '48002'   => '粉丝拒收消息（粉丝在公众号选项中，关闭了 “ 接收消息 ” ）',
            '48003'   => '不合法的suite_id',
            '48004'   => 'api 接口被封禁或授权关系无效',
            '48005'   => 'api 禁止删除被自动回复和自定义菜单引用的素材或api接口已废弃',
            '48006'   => 'api 禁止清零调用次数，因为清零次数达到上限',
            '48008'   => '没有该类型消息的发送权限',
            '48021'   => '自动保存的草稿无法预览/发送，请先手动保存草稿',
            '50001'   => '接口未授权或redirect_url未登记可信域名',
            '50002'   => '用户受限，可能是违规后接口被封禁',
            '50003'   => '应用已禁用，user unexpected, maybe not in white list',
            '50004'   => 'user not allow to use accesstoken, maybe for punishment',
            '50005'   => '用户未关注公众号',
            '53010'   => '名称格式不合法',
            '53011'   => '名称检测命中频率限制',
            '53012'   => '禁止使用该名称',
            '53013'   => '公众号：名称与已有公众号名称重复;小程序：该名称与已有小程序名称重复',
            '53014'   => '公众号：公众号已有{名称a+}时，需与该帐号相同主体才可申请{名称a};小程序：小程序已有{名称a+}时，需与该帐号相同主体才可申请{名称a}',
            '53015'   => '公众号：该名称与已有小程序名称重复，需与该小程序帐号相同主体才可申请;小程序：该名称与已有公众号名称重复，需与该公众号帐号相同主体才可申请',
            '53016'   => '公众号：该名称与已有多个小程序名称重复，暂不支持申请;小程序：该名称与已有多个公众号名称重复，暂不支持申请',
            '53017'   => '公众号：小程序已有{名称a+}时，需与该帐号相同主体才可申请{名称a};小程序：公众号已有{名称a+}时，需与该帐号相同主体才可申请{名称a}',
            '53018'   => '名称命中微信号',
            '53019'   => '名称在保护期内',
            '53200'   => '本月功能介绍修改次数已用完',
            '53201'   => '功能介绍内容命中黑名单关键字',
            '53202'   => '本月头像修改次数已用完',
            '53300'   => '超出每月次数限制',
            '53301'   => '超出可配置类目总数限制',
            '53302'   => '当前账号主体类型不允许设置此种类目',
            '53303'   => '提交的参数不合法',
            '53304'   => '与已有类目重复',
            '53305'   => '包含未通过ipc校验的类目',
            '53306'   => '修改类目只允许修改类目资质，不允许修改类目id',
            '53307'   => '只有审核失败的类目允许修改',
            '53308'   => '审核中的类目不允许删除',
            '53309'   => '社交红包不允许删除',
            '53310'   => '类目超过上限，但是可以添加apply_reason参数申请更多类目',
            '53311'   => '需要提交资料信息',
            '53500'   => '发布功能被封禁',
            '53501'   => '频繁请求发布',
            '53502'   => 'Publish ID 无效',
            '53600'   => 'Article ID 无效',

            '61450'   => '系统错误 (system error)',
            '61451'   => '参数错误 (invalid parameter)',
            '61452'   => '无效客服账号 (invalid kf_account)',
            '61453'   => '客服帐号已存在 (kf_account exsited)',
            '61454'   => '客服帐号名长度超过限制 ( 仅允许 10 个英文字符，不包括 @ 及 @ 后的公众号的微信号 )(invalid kf_acount length)',
            '61455'   => '客服帐号名包含非法字符 ( 仅允许英文 + 数字 )(illegal character in kf_account)',
            '61456'   => '客服帐号个数超过限制 (10 个客服账号 )(kf_account count exceeded)',
            '61457'   => '无效头像文件类型 (invalid file type)',
            '61500'   => '日期格式错误',
            '63001'   => '部分参数为空',
            '63002'   => '无效的签名',
            '65301'   => '不存在此 menuid 对应的个性化菜单',
            '65302'   => '没有相应的用户',
            '65303'   => '没有默认菜单，不能创建个性化菜单',
            '65304'   => 'matchrule 信息为空',
            '65305'   => '个性化菜单数量受限',
            '65306'   => '不支持个性化菜单的帐号',
            '65307'   => '个性化菜单信息为空',
            '65308'   => '包含没有响应类型的 button',
            '65309'   => '个性化菜单开关处于关闭状态',
            '65310'   => '填写了省份或城市信息，国家信息不能为空',
            '65311'   => '填写了城市信息，省份信息不能为空',
            '65312'   => '不合法的国家信息',
            '65313'   => '不合法的省份信息',
            '65314'   => '不合法的城市信息',
            '65316'   => '该公众号的菜单设置了过多的域名外跳（最多跳转到 3 个域名的链接）',
            '65317'   => '不合法的 url',
            '72023'   => '发票已被其他公众号锁定',
            '72024'   => '发票状态错误',
            '72037'   => '存在发票不属于该用户',
            '80000'   => '系统错误，请稍后再试',

            '85001'   => '微信号不存在或微信号设置为不可搜索',
            '85002'   => '小程序绑定的体验者数量达到上限',
            '85003'   => '微信号绑定的小程序体验者达到上限',
            '85004'   => '微信号已经绑定',
            '85005'   => '可信域名未通过所有权校验',
            '85006'   => '标签格式错误',
            '85007'   => '页面路径错误',
            '85008'   => '类目填写错误',
            '85009'   => '已经有正在审核的版本',
            '85010'   => 'item_list有项目为空',
            '85011'   => '标题填写错误',
            '85012'   => '无效的审核id',
            '85013'   => '无效的自定义配置',
            '85014'   => '无效的模版编号',
            '85015'   => '该账号不是小程序账号',
            '85016'   => '域名数量超过限制',
            '85017'   => '没有新增域名，请确认小程序已经添加了域名或该域名是否没有在第三方平台添加',
            '85018'   => '域名没有在第三方平台设置',
            '85019'   => '没有审核版本',
            '85020'   => '审核状态未满足发布',
            '85023'   => '审核列表填写的项目数不在1-5以内',
            '85026'   => '微信号绑定管理员名额达到上限',
            '85027'   => '身份证绑定管理员名额达到上限',
            '85043'   => '模版错误',
            '85044'   => '代码包超过大小限制',
            '85045'   => 'ext_json有不存在的路径',
            '85046'   => 'tabbar中缺少path',
            '85047'   => 'pages字段为空',
            '85048'   => 'ext_json解析失败',
            '85051'   => 'version_desc或者preview_info超限',
            '85060'   => '无效的taskid',
            '85061'   => '手机号绑定管理员名额达到上限',
            '85062'   => '手机号黑名单',
            '85063'   => '身份证黑名单',
            '85064'   => '找不到模版',
            '85065'   => '模版库已满',
            '85066'   => '链接错误',
            '85068'   => '测试链接不是子链接',
            '85069'   => '校验文件失败',
            '85070'   => '链接为黑名单',
            '85071'   => '已添加该链接，请勿重复添加',
            '85072'   => '该链接已被占用',
            '85073'   => '二维码规则已满',
            '85074'   => '小程序未发布, 小程序必须先发布代码才可以发布二维码跳转规则',
            '85075'   => '个人类型小程序无法设置二维码规则',
            '85076'   => '链接没有icp备案',
            '85077'   => '小程序类目信息失效（类目中含有官方下架的类目，请重新选择类目）',
            '85079'   => '小程序没有线上版本，不能进行灰度',
            '85080'   => '小程序提交的审核未审核通过',
            '85081'   => '无效的发布比例',
            '85082'   => '当前的发布比例需要比之前设置的高',
            '85083'   => '搜索标记位被封禁，无法修改',
            '85084'   => '非法的status值，只能填0或者1',
            '85085'   => '小程序提审数量已达本月上限，可以通过“小程序服务商助手-我的-咨询反馈”联系人工客服进行申请更多临时额度，客服工作时间：工作日：9:00-12:00,14:00-18:00',//《自助临时申请额度》https://developers.weixin.qq.com/community/minihome/doc/00022ce7b209f09f363b9c62958401?blockType=99
            '85086'   => '提交代码审核之前需提前上传代码',
            '85087'   => '小程序已使用api navigatetominiprogram，请声明跳转appid列表后再次提交',
            '86000'   => '不是由第三方代小程序进行调用',
            '86001'   => '不存在第三方的已经提交的代码',
            '86002'   => '小程序未初始化完成，请确保已设置小程序昵称、头像、简介、服务类目',
            '86007'   => '小程序禁止提交',
            '86008'   => '服务商被处罚，限制全部代码提审能力',
            '86009'   => '服务商新增小程序代码提审能力被限制',
            '86010'   => '服务商迭代小程序代码提审能力被限制',
            '87009'   => '无效的签名',
            '87011'   => '现网已经在灰度发布，不能进行版本回退',
            '87012'   => '该版本不能回退，可能的原因：1:无上一个线上版用于回退 2:此版本为已回退版本，不能回退 3:此版本为回退功能上线之前的版本，不能回退',
            '87013'   => '撤回次数达到上限（每天一次，每个月10次）',
            '88000'   => '公众号没有开通留言功能',
            '89007'   => '小程序本月被关联的名额已用完',
            '89008'   => '小程序为海外帐号，不允许关联',
            '89009'   => '小程序关联达到上限',
            '89010'   => '已经发送关联邀请',
            '89011'   => '在附近中展示的小程序不能取消关联',
            '89012'   => '门店、小店小程序不能取消关联',
            '89013'   => '小程序被封禁',
            '89015'   => '已经关联该小程序',
            '89016'   => '公众号本月关联相同主体达到上限',
            '89017'   => '公众号本月关联不同主体达到上限',
            '89035'   => '已经从公众平台后台发起关联申请，处于小程序管理员确认中，无法从第三方重复发起关联申请',
            '89000'   => '该公众号 / 小程序 已经绑定了开放平台帐号',
            '89001'   => 'not same contractor，authorizer与开放平台帐号主体不相同',
            '89002'   => 'open not exists，该公众号/小程序未绑定微信开放平台帐号。',
            '89003'   => '该开放平台帐号并非通过api创建，不允许操作',
            '89004'   => '该开放平台帐号所绑定的公众号/小程序已达上限（100个）',
            '89019'   => '业务域名无更改，无需重复设置',
            '89020'   => '尚未设置小程序业务域名，请先在第三方平台中设置小程序业务域名后在调用本接口',
            '89021'   => '请求保存的域名不是第三方平台中已设置的小程序业务域名或子域名',
            '89029'   => '业务域名数量超过限制，最多可以添加100个业务域名',
            '89231'   => '个人小程序不支持调用setwebviewdomain 接口',
            '89236'   => '该插件不能申请',
            '89237'   => '已经添加该插件',
            '89238'   => '申请或使用的插件已经达到上限',
            '89239'   => '该插件不存在',
            '89256'   => 'token信息有误',
            '89257'   => '该插件版本不支持快速更新',
            '89258'   => '当前小程序帐号存在灰度发布中的版本，不可操作快速更新',
            '89249'   => '该主体已有任务执行中，距上次任务24h后再试',
            '89247'   => '内部错误',
            '89250'   => '未找到该任务',
            '89251'   => '待法人人脸核身校验',
            '89252'   => '法人&企业信息一致性校验中	',
            '89253'   => '缺少参数',
            '89254'   => '第三方权限集不全，补全权限集全网发布后生效',
            '89255'   => '企业代码不正确',

            '91001'   => '不是公众号快速创建的小程序',
            '91002'   => '小程序发布后不可改名',
            '91003'   => '改名状态不合法',
            '91004'   => '昵称不合法',
            '91005'   => '昵称命中主体保护',
            '91006'   => '昵称命中微信号',
            '91007'   => '昵称已被占用',
            '91008'   => '昵称命中7天侵权保护期',
            '91009'   => '需要提交材料',
            '91010'   => '其他错误',
            '91011'   => '查不到昵称修改审核单信息',
            '91012'   => '其它错误',
            '91013'   => '占用名字过多',
            '91014'   => '+号规则 同一类型关联名主体不一致',
            '91015'   => '原始名不同类型主体不一致',
            '91016'   => '名称占用者 ≥2',
            '91017'   => '+号规则 不同类型关联名主体不一致',
            '91018'   => '组织类型小程序发布后，侵权被清空昵称，需走认证改名',
            '91019'   => '小程序正在审核中',
            '91040'   => '获取ticket的类型无效',
            '94012'   => 'appid和商户号的绑定关系不存在',



            '9001001' => 'post 数据参数不合法',
            '9001002' => '远端服务不可用',
            '9001003' => 'ticket 不合法',
            '9001004' => '获取摇周边用户信息失败',
            '9001005' => '获取商户信息失败',
            '9001006' => '获取 openid 失败',
            '9001007' => '上传文件缺失',
            '9001008' => '上传素材的文件类型不合法',
            '9001009' => '上传素材的文件尺寸不合法',
            '9001010' => '上传失败',
            '9001020' => '帐号不合法',
            '9001021' => '已有设备激活率低于 50% ，不能新增设备',
            '9001022' => '设备申请数不合法，必须为大于 0 的数字',
            '9001023' => '已存在审核中的设备 id 申请',
            '9001024' => '一次查询设备 id 数量不能超过 50',
            '9001025' => '设备 id 不合法',
            '9001026' => '页面 id 不合法',
            '9001027' => '页面参数不合法',
            '9001028' => '一次删除页面 id 数量不能超过 10',
            '9001029' => '页面已应用在设备中，请先解除应用关系再删除',
            '9001030' => '一次查询页面 id 数量不能超过 50',
            '9001031' => '时间区间不合法',
            '9001032' => '保存设备与页面的绑定关系参数错误',
            '9001033' => '门店 id 不合法',
            '9001034' => '设备备注信息过长',
            '9001035' => '设备申请参数不合法',
            '9001036' => '查询起始值 begin 不合法',
            '41030'   => '小程序页面尚未发布,无法生成',//此返回码存在多个 总结就是小程序page路径不对
            '61051'   => '公众号主体类型不允许快速创建',
            '61052'   => '公众号未认证',
            '61053'   => '超过主体可注册数量上限',
            '61054'   => '主体黑名单',
            '61055'   => '超出公众号每月可快速创建限额',
            '61056'   => '政府、媒体、其他组织必须复选微信认证',
            '61057'   => '公众号仍有快速创建的账号在流程中',
            '61058'   => '用户扫码凭证校验不通过',
            '61028'   => '第三方平台未发布',
            '61029'   => '第三方平台缺少必备权限集(帐号服务权限、程序帐号管理权限、小程序开发管理与数据分析权限)',
            '61060'   => '转 uri 不合法',
            '61061'   => '海外帐号不允许快速创建',
            '86004'   => '无效微信号',
            '61070'   => '企业代码类型无效，请选择正确类型填写',

            '92000'   => '该经营资质已添加，请勿重复添加',
            '92002'   => '附近地点添加数量达到上线，无法继续添加',
            '92003'   => '地点已被其它小程序占用',
            '92004'   => '附近功能被封禁',
            '92005'   => '地点正在审核中',
            '92006'   => '地点正在展示小程序',
            '92007'   => '地点审核失败',
            '92008'   => '程序未展示在该地点',
            '93009'   => '小程序未上架或不可见',
            '93010'   => '地点不存在',
            '93011'   => '个人类型小程序不可用',
            '93012'   => '非普通类型小程序（门店小程序、小店小程序等）不可用',
            '93013'   => '从腾讯地图获取地址详细信息失败',
            '93014'   => '同一资质证件号重复添加',
            '61007'   => '无接口权限或您授权了多个平台,请前往公众平台登录小程序账号,在[设置-第三方设置]中取消所有授权,然后重新授权',
            '1003'    => '商品id不存在',
            '300001'  => '禁止创建/更新商品 或 禁止编辑&更新房间',
            '300002'  => '名称长度不符合规则',
            '300003'  => '价格输入不合规（如：现价比原价大、传入价格非数字等）',
            '300004'  => '商品名称存在违规违法内容',
            '300005'  => '商品图片存在违规违法内容',
            '300006'  => '图片上传失败（如：mediaid过期）',
            '300007'  => '线上小程序版本不存在该链接',
            '300008'  => '添加商品失败',
            '300009'  => '商品审核撤回失败',
            '300010'  => '商品审核状态不对（如：商品审核中）',
            '300011'  => '操作非法（api不允许操作非api创建的商品）',
            '300012'  => '没有提审额度（每天500次提审额度）',
            '300013'  => '提审失败',
            '300014'  => '审核中，无法删除（非零代表失败）',
            '300017'  => '商品未提审',
            '300018'  => '商品图片尺寸过大',
            '300021'  => '商品添加成功，审核失败',
            '300022'  => '此房间号不存在',
            '300023'  => '房间状态 拦截（当前房间状态不允许此操作）',
            '300024'  => '商品不存在',
            '300025'  => '商品审核未通过',
            '300026'  => '房间商品数量已经满额',
            '300027'  => '导入商品失败',
            '300028'  => '房间名称违规',
            '300029'  => '主播昵称违规',
            '300030'  => '主播微信号不合法',
            '300031'  => '直播间封面图不合规',
            '300032'  => '直播间分享图违规',
            '300033'  => '添加商品超过直播间上限',
            '300034'  => '主播微信昵称长度不符合要求',
            '300035'  => '主播微信号不存在',
            '300036'  => '主播微信号未实名认证',
            '300037'  => '购物直播频道封面图不合规',
            '300038'  => '未在小程序管理后台配置客服',
            '300039'  => '主播副号微信号不合法',
            '300040'  => '名称含有非限定字符（含有特殊字符）',
            '300041'  => '创建者微信号不合法',
            '9410000' => '直播间列表为空',
            '9410001' => '获取房间失败',
            '9410002' => '获取商品失败',
            '9410003' => '获取回放失败',
            '400001'  => '微信号不合规',
            '400002'  => '微信号需要实名认证，仅设置主播角色时可能出现',
            '400003'  => '添加角色达到上限（管理员10个，运营者500个，主播500个）',
            '400004'  => '重复添加角色',
            '400005'  => '主播角色删除失败，该主播存在未开播的直播间',
        );
        //注意！！！注意！！！注意！！！注意！！！注意！！！与公共返回码冲突的请勿加入此处，需要独立定义返回码错误转换方法
		if(is_array($rs)){
			return $err[$rs['errcode']] ? $err[$rs['errcode']] : $rs['errcode'].': '.$rs['errmsg'];
		}else{
			return $err[$rs] ? $err[$rs] : $rs;
		}
	}
	
	//服务商设置广告分账比例
    public static function setCustomShareRatio($aid,$platform,$share_ratio){
        try {
            $component_token = self::component_access_token();
            $url = "https://api.weixin.qq.com/wxa/setdefaultamsinfo?action=agency_set_custom_share_ratio&access_token={$component_token}";
            $appinfo = System::appinfo($aid,$platform);
            $appid = $appinfo['appid'];
            $data['share_ratio'] = (int)$share_ratio;
            $data['appid'] = $appid;
            $rs = request_post($url,jsonEncode($data));
            $rs = json_decode($rs,'true');
            if($rs['ret'] =='0'){
                return true;
            }else{
                return false;  
            }
        }catch (\Exception $e){
            return false;
        }
    }
    //服务商获取广告分账比例
    public static function getCustomShareRatio($aid,$platform){
        try {
            $appinfo = System::appinfo($aid,$platform);
            $appid = $appinfo['appid'];
            $component_token = self::component_access_token();
            $url = "https://api.weixin.qq.com/wxa/getdefaultamsinfo?action=agency_get_custom_share_ratio&access_token={$component_token}";
            $data['appid'] = $appid;
            $rs = request_post($url,jsonEncode($data));
            \think\facade\Log::write('获取分账比例_'.$rs);
            $rs = json_decode($rs,true);
            if($rs['ret'] =='0'){
                return ['share_ratio' => $rs['share_ratio']];
            }else{
                return false;
            }
        }catch (\Exception $e){
            return false;
        }
    }
    //是否能开启流量主 小程序端
    public static function isOpenPublisher($aid,$platform){
        try {
            $appinfo = System::appinfo($aid,$platform);
            $appid = $appinfo['appid'];
            if(!$appid ) return ['msg' => '请配置小程序'];
            if($appinfo['authtype'] ==0){
                $appsecret = $appinfo['appsecret'];
                if(!$appsecret) return ['msg' => '请配置小程序信息'];
            }
            $access_token = self::access_token($aid,$platform);
            $url = "https://api.weixin.qq.com/wxa/operationams?action=agency_check_can_open_publisher&access_token={$access_token}";
            $rs = request_post($url,jsonEncode([]));
            $rs = json_decode($rs,true);
            if($rs['ret'] =='0'){
                return ['status'=>1, 'isopen'=>$rs['status']];
            }else{
                $msgdata  =[
                    '1700' => '参数错误',
                    '1701' => '参数错误',
                    '1735' => '请完成签约操作',
                    '1737' => '等待一分钟后重新操作',
                    '1803' => '广告单元名称重复',
                    '1807' => '请开通流量主',
                    '2009' => '请开通流量主',
                    '2056' => '请在第三方平台页面的变现专区开通服务',
                ];
                $msg =  $msgdata[$rs['ret']]??$rs['errmsg'];
                throw  new Exception($msg);
            }
        }catch (\Exception $e){
            return ['status' => 0,'msg' =>  $e->getMessage()];
        }
    }
    //开通流量主
    public static function createPublisher($aid,$platform='wx'){
        try {
            $appinfo = System::appinfo($aid,$platform);
            $appid = $appinfo['appid'];
            if(!$appid ) return ['msg' => '请配置小程序'];
            if($appinfo['authtype'] ==0){
                $appsecret = $appinfo['appsecret'];
                if(!$appsecret) return ['msg' => '请配置小程序信息'];
            }
            $access_token = self::access_token($aid,$platform);
            $url = "https://api.weixin.qq.com/wxa/operationams?action=agency_create_publisher&access_token={$access_token}";
            $rs = request_post($url,jsonEncode([]));
            $rs = json_decode($rs,true);
            if($rs['ret'] =='0'){
                return true;
            }else{
                $msgdata  =[
                    '1700' => '参数错误',
                    '1701' => '参数错误',
                    '1735' => '请完成签约操作',
                    '1737' => '等待一分钟后重新操作',
                    '1803' => '广告单元名称重复',
                    '1807' => '请开通流量主',
                    '2009' => '请开通流量主',
                    '2021' => '已开通流量主',
                    '2056' => '请在第三方平台页面的变现专区开通服务',
                ];
                throw  new Exception($msgdata[$rs['ret']]);
            }
        }catch (\Exception $e){
            return ['status' =>0,'msg' =>  $e->getMessage()];
        }
    }

    //创建广告
    public static function createAdunit($aid,$platform='wx',$name,$type,$tmpl_id=''){
        try {
            $appinfo = System::appinfo($aid,$platform);
            $appid = $appinfo['appid'];
            if(!$appid ) return ['msg' => '请配置小程序'];
            if($appinfo['authtype'] ==0){
                $appsecret = $appinfo['appsecret'];
                if(!$appsecret) return ['msg' => '请配置小程序信息'];
            }
            $access_token = self::access_token($aid,$platform);
            $url = "https://api.weixin.qq.com/wxa/operationams?action=agency_create_adunit&access_token={$access_token}";
            $data['name'] = $name;
            $data['type'] = $type;
            if($tmpl_id){
                $data['tmpl_type'] = (int)$tmpl_id; 
            }
            $rs = request_post($url,jsonEncode($data));
            $rs = json_decode($rs,true);
            if($rs['ret'] =='0'){
                return ['ad_unit_id' => $rs['ad_unit_id']];
            }else{
                $msgdata  =[
                    '1700' => '参数错误',
                    '1701' => '参数错误',
                    '1735' => '请完成签约操作',
                    '1737' => '等待一分钟后重新操作',
                    '1803' => '广告单元名称重复',
                    '1807' => '请开通流量主',
                    '2009' => '请开通流量主',
                    '2056' => '请在第三方平台页面的变现专区开通服务',
                ];
                $msg = $msgdata[$rs['ret']]??$rs['msg'];
                throw  new Exception($msg);
            }
        }catch (\Exception $e){
            return ['msg' =>  $e->getMessage()];
        }
    }
    public static function editAdunit($aid,$platform='wx',$data){
        try {
            $appinfo = System::appinfo($aid,$platform);
            $appid = $appinfo['appid'];
            if(!$appid ) return ['msg' => '请配置小程序'];
            if($appinfo['authtype'] ==0){
                $appsecret = $appinfo['appsecret'];
                if(!$appsecret) return ['msg' => '请配置小程序信息'];
            }
            $access_token = self::access_token($aid,$platform);
            $url = "https://api.weixin.qq.com/wxa/operationams?action=agency_update_adunit&access_token={$access_token}";
            $rs = request_post($url,jsonEncode($data));
            \think\facade\Log::write('编辑广告_'.$rs);
            $rs = json_decode($rs,true);
            if($rs['ret'] =='0'){
                return true;
            }else{
                $msgdata  =[
                    '1700' => '参数错误',
                    '1701' => '参数错误',
                    '1735' => '请完成签约操作',
                    '1737' => '等待一分钟后重新操作',
                    '1803' => '广告单元名称重复',
                    '1807' => '请开通流量主',
                    '2009' => '请开通流量主',
                    '2056' => '请在第三方平台页面的变现专区开通服务',
                ];
                throw  new Exception($msgdata[$rs['ret']]);
            }
        }catch (\Exception $e){
            return ['msg' =>  $e->getMessage()];
        }
    }
    //获取 代码
    public static function getAdunitCode($aid,$platform='wx',$ad_unit_id){
        try {
            $appinfo = System::appinfo($aid,$platform);
            $appid = $appinfo['appid'];
            if(!$appid ) return ['msg' => '请配置小程序'];
            if($appinfo['authtype'] ==0){
                $appsecret = $appinfo['appsecret'];
                if(!$appsecret) return ['msg' => '请配置小程序信息'];
            }
            $access_token = self::access_token($aid,$platform);
            $url = "https://api.weixin.qq.com/wxa/operationams?action=agency_get_adunit_code&access_token={$access_token}";
            $data['ad_unit_id'] = $ad_unit_id;
            $rs = request_post($url,jsonEncode($data));
            \think\facade\Log::write('获取代码_'.$rs);
            $rs = json_decode($rs,true);
            if($rs['ret'] =='0'){
                return ['code' => $rs['code']];
            }else{
                $msgdata  =[
                    '1700' => '参数错误',
                    '1701' => '参数错误',
                    '1735' => '请完成签约操作',
                    '1737' => '等待一分钟后重新操作',
                    '1803' => '广告单元名称重复',
                    '1807' => '请开通流量主',
                    '2009' => '请开通流量主',
                    '2056' => '请在第三方平台页面的变现专区开通服务',
                ];
                
                throw  new Exception($msgdata[$rs['ret']]);
            }
        }catch (\Exception $e){
            return ['msg' =>  $e->getMessage()];
        }
    }
    //获取细分数据
    public static function getAdxfData($aid,$platform='wx',$data){
        try {
            $appinfo = System::appinfo($aid,$platform);
            $appid = $appinfo['appid'];
            if(!$appid ) return ['msg' => '请配置小程序'];
            if($appinfo['authtype'] ==0){
                $appsecret = $appinfo['appsecret'];
                if(!$appsecret) return ['msg' => '请配置小程序信息'];
            }
            $access_token = self::access_token($aid,$platform);
            $url = "https://api.weixin.qq.com/wxa/operationams?action=agency_get_adunit_general&access_token={$access_token}";
            $rs = request_post($url,jsonEncode($data));
            $rs = json_decode($rs,true);
            if($rs['base_resp']['ret'] =='0'){
                return ['list' =>$rs['list'],'total_num' => $rs['total_num']];
            }else{
                $msgdata  =[
                    '1700' => '参数错误',
                    '1701' => '参数错误',
                    '1735' => '请完成签约操作',
                    '1737' => '等待一分钟后重新操作',
                    '1803' => '广告单元名称重复',
                    '1807' => '请开通流量主',
                    '2009' => '请开通流量主',
                    '2056' => '请在第三方平台页面的变现专区开通服务',
                ];
                throw  new Exception($msgdata[$rs['base_resp']['ret']]);
            }
        }catch (\Exception $e){
            return ['msg' =>  $e->getMessage()];
        }
    }
    //获取小程序广告汇总数据
    public static function getAdSummaryData($aid,$platform,$data){
        try {
            $appinfo = System::appinfo($aid,$platform);
            $appid = $appinfo['appid'];
            if(!$appid ) return ['msg' => '请配置小程序'];
            if($appinfo['authtype'] ==0){
                $appsecret = $appinfo['appsecret'];
                if(!$appsecret) return ['msg' => '请配置小程序信息'];
            }
            $access_token = self::access_token($aid,$platform);
            $url = "https://api.weixin.qq.com/wxa/operationams?action=agency_get_adpos_genenral&access_token={$access_token}";
            $rs = request_post($url,jsonEncode($data));
            $rs = json_decode($rs,true);
            if($rs['base_resp']['ret'] =='0'){
                return ['list' =>$rs['list'],'total_num' => $rs['total_num'],'summary' =>$rs['summary']];
            }else{
                $msgdata  =[
                    '1700' => '参数错误',
                    '1701' => '参数错误',
                    '1735' => '请完成签约操作',
                    '1737' => '等待一分钟后重新操作',
                    '1803' => '广告单元名称重复',
                    '1807' => '请开通流量主',
                    '2009' => '请开通流量主',
                    '2056' => '请在第三方平台页面的变现专区开通服务',
                ];
                throw  new Exception($msgdata[$rs['base_resp']['ret']]);
            }
        }catch (\Exception $e){
            return ['msg' =>  $e->getMessage()];
        }
    }

    //获取小程序结算收入数据
    public static function getAdSettleData($aid,$platform,$data){
        try {
            $appinfo = System::appinfo($aid,$platform);
            $appid = $appinfo['appid'];
            if(!$appid ) return ['msg' => '请配置小程序'];
            if($appinfo['authtype'] ==0){
                $appsecret = $appinfo['appsecret'];
                if(!$appsecret) return ['msg' => '请配置小程序信息'];
            }
            $access_token = self::access_token($aid,$platform);
            $url = "https://api.weixin.qq.com/wxa/operationams?action=agency_get_settlement&access_token={$access_token}";
            $rs = request_post($url,jsonEncode($data));
            $rs = json_decode($rs,true);
            if($rs['base_resp']['ret'] =='0'){
                return ['list' =>$rs['settlement_list'],'jsdata' => ['revenue_all' =>$rs['revenue_all'],'penalty_all' => $rs['penalty_all'],'settled_revenue_all'=>$rs['settled_revenue_all'] ]];
            }else{
                $msgdata  =[
                    '1700' => '参数错误',
                    '1701' => '参数错误',
                    '1735' => '请完成签约操作',
                    '1737' => '等待一分钟后重新操作',
                    '1803' => '广告单元名称重复',
                    '1807' => '请开通流量主',
                    '2009' => '请开通流量主',
                    '2056' => '请在第三方平台页面的变现专区开通服务',
                ];
                throw  new Exception($msgdata[$rs['base_resp']['ret']]);
            }
        }catch (\Exception $e){
            return ['msg' =>  $e->getMessage()];
        }
    }
    //服务商结算数据
    public static function getComponentSettleData($aid,$platform,$data){
        try {
            $appinfo = System::appinfo($aid,$platform);
            $appid = $appinfo['appid'];
            if(!$appid ) return ['msg' => '请配置小程序'];
            if($appinfo['authtype'] ==0){
                $appsecret = $appinfo['appsecret'];
                if(!$appsecret) return ['msg' => '请配置小程序信息'];
            }
            $component_token = self::component_access_token();
            $url = "https://api.weixin.qq.com/wxa/operationams?action=get_agency_settled_revenue&access_token={$component_token}";
            $rs = request_post($url,jsonEncode($data));
            $rs = json_decode($rs,true);
            if($rs['ret'] =='0'){
                return ['list' =>$rs['settlement_list'],'jsdata' => ['revenue_all' =>$rs['revenue_all'],'penalty_all' => $rs['penalty_all'],'settled_revenue_all'=>$rs['settled_revenue_all'] ]];
            }else{
                $msgdata  =[
                    '-202' => '可在一段时间后重试',
                    '1700' => '参数错误',
                    '1701' => '参数错误',
                    '1735' => '请完成签约操作',
                    '1737' => '等待一分钟后重新操作',
                    '1803' => '广告单元名称重复',
                    '1807' => '请开通流量主',
                    '2009' => '请开通流量主',
                    '2056' => '请在第三方平台页面的变现专区开通服务',
                ];
                throw  new Exception($msgdata[$rs['ret']]);
            }
        }catch (\Exception $e){
            return ['msg' =>  $e->getMessage()];
        }
    }

    /**
     * 查询小程序是否已开通发货信息管理服务 https://developers.weixin.qq.com/miniprogram/dev/platform-capabilities/business-capabilities/order-shipping/order-shipping.html#%E4%B8%83%E3%80%81%E6%9F%A5%E8%AF%A2%E5%B0%8F%E7%A8%8B%E5%BA%8F%E6%98%AF%E5%90%A6%E5%B7%B2%E5%BC%80%E9%80%9A%E5%8F%91%E8%B4%A7%E4%BF%A1%E6%81%AF%E7%AE%A1%E7%90%86%E6%9C%8D%E5%8A%A1
     * @param $aid
     * @param $platform
     * @return array|void
     */
    public static function isTradeManaged($aid,$platform='wx'){
        try {
            $appinfo = System::appinfo($aid,$platform);
            if($appinfo){
                $appid = $appinfo['appid'];
                $token = self::access_token($aid,$platform);
                $url = "https://api.weixin.qq.com/wxa/sec/order/is_trade_managed?access_token={$token}";
                $data['appid'] = $appid;
                $rs = request_post($url,jsonEncode($data));
                \think\facade\Log::write('查询小程序是否已开通发货信息管理服务:'.$rs);
                $rs = json_decode($rs,true);
                if($rs['errcode'] =='0'){
                    return ['status' => 1, 'is_trade_managed' => $rs['is_trade_managed']];
                }else{
                    return ['status' => 0, 'msg' => self::geterror($rs['errcode']),'errcode'=>$rs['errcode']];
                }
            }
        }catch (\Exception $e){
            return ['status' => 0, 'msg' => $e->getMessage()];
        }
    }

    /**
     * //发货信息录入接口 https://developers.weixin.qq.com/miniprogram/dev/platform-capabilities/business-capabilities/order-shipping/order-shipping.html#%E4%B8%80%E3%80%81%E5%8F%91%E8%B4%A7%E4%BF%A1%E6%81%AF%E5%BD%95%E5%85%A5%E6%8E%A5%E5%8F%A3
     * @param $aid
     * @param $platform
     * @param $data
     * @return array|int[]|void
     * 报错：支付单不存在（10秒后再次请求，微信官方同步订单需要10秒）
     */
    public static function uploadShippingInfo($aid,$platform='wx',$data=[]){
        try {
            $appinfo = System::appinfo($aid,$platform);
            if($appinfo){
//                $appid = $appinfo['appid'];
                $token = self::access_token($aid,$platform);
                $url = "https://api.weixin.qq.com/wxa/sec/order/upload_shipping_info?access_token={$token}";
//                \think\facade\Log::write('发货信息录入接口:'.jsonEncode($data));
                $rs = request_post($url,jsonEncode($data));
//                \think\facade\Log::write('发货信息录入接口:'.$rs);
                $rs = json_decode($rs,true);
                if($rs['errcode'] =='0'){
                    \think\facade\Log::write('发货信息录入接口:'.jsonEncode($rs));
                    return ['status' => 1];
                }else{
                    \think\facade\Log::error('发货信息录入接口:'.jsonEncode($rs));
                    return ['status' => 0, 'msg' => self::geterror($rs['errcode']),'errcode'=>$rs['errcode']];
                }
            }
        }catch (\Exception $e){
            return ['status' => 0, 'msg' => $e->getMessage()];
        }
    }

    /**
     * 获取运力id列表 https://developers.weixin.qq.com/miniprogram/dev/platform-capabilities/industry/express/business/express_search.html
     * @param $aid
     * @param $platform
     * @param $data
     * @return array|void
     */
    public static function get_delivery_list($aid,$platform='wx'){
        $wx_get_delivery_list = cache('wx_get_delivery_list');
        if(!$wx_get_delivery_list){
            try {
                $appinfo = System::appinfo($aid,$platform);
                if($appinfo){
                    $appid = $appinfo['appid'];
                    $token = self::access_token($aid,$platform);
                    $url = "https://api.weixin.qq.com/cgi-bin/express/delivery/open_msg/get_delivery_list?access_token={$token}";
                    $rs = request_post($url,'{}');
                    \think\facade\Log::write('get_delivery_list:'.$rs);
                    $rs = json_decode($rs,true);
                    if($rs['errcode'] =='0'){
                        cache('wx_get_delivery_list', $rs['delivery_list']);
                        return ['status' => 1,'delivery_list'=>$rs['delivery_list']];
                    }else{
                        return ['status' => 0, 'msg' => self::geterror($rs['errcode']),'errcode'=>$rs['errcode']];
                    }
                }
            }catch (\Exception $e){
                return ['status' => 0, 'msg' => $e->getMessage()];
            }
        }
        return ['status' => 1,'delivery_list'=>$wx_get_delivery_list];
    }
    //申请授权 半屏小程序 https://developers.weixin.qq.com/doc/oplatform/openApi/OpenApiDoc/miniprogram-management/embedded-management/addEmbedded.html
    public static function addEmbedded($aid,$appid='',$apply_reason=''){
        try {
            $access_token =  self::access_token($aid,'wx');
            $url = 'https://api.weixin.qq.com/wxaapi/wxaembedded/add_embedded?access_token='.$access_token;

            $data['appid']  = (string)$appid;
            $data['apply_reason']  = (string) $apply_reason;
            $rs = curl_post($url,jsonEncode($data),0,array('Content-Type: application/json'));
            $res = json_decode( $rs,true);
            if($res['errcode'] == 0){
                return ['status' => 1];
            } else{    
                $error = [
                    '-1' => '系统繁忙',
                    '89408' => '半屏小程序系统错误',
                    '89410' => '添加半屏小程序appid参数错误',
                    '89411' => '添加半屏小程序appid参数为空',
                    '89412' => '添加半屏小程序申请理由不得超过30个字',
                    '89413' => '该小程序被申请次数已达今日限制',
                    '89414' => '每天仅允许申请50次半屏小程序',
                    '89420' => '不支持添加个人主体小程序',
                    '89423' => '申请次数添加到达上限',
                    '89424' => '授权次数到达上限',
                    '89425' => '申请添加已超时',
                    '89426' => '申请添加状态异常',
                    '89427' => '申请号和授权号相同',
                    '89428' => '该小程序已申请，不允许重复添加',
                    '89429' => '已到达同一小程序每日最多申请次数',
                    '89430' => '该小程序已设置自动拒绝申请',
                    '89431' => '不支持此类型小程序',
                    '89432' => '不是小程序',
                    '89418' => '添加半屏小程序每日申请次数失败',
                    '89419' => '添加半屏小程序每日授权次数失败',
                ];
                $errormsg = $error[$res['errcode']]?$error[$res['errcode']]:self::geterror($res['errcode']);
                throw new Exception($errormsg);
            }
        }catch (\Exception $e){
            return  ['status' =>0 ,'msg' => $e->getMessage()];
        }
    }
    //获取半屏小程序授权列表 https://developers.weixin.qq.com/doc/oplatform/Third-party_Platforms/2.0/api/EmbeddedMiniProgram/get_own_list.html#%E8%BF%94%E5%9B%9E%E7%A0%81%E8%AF%B4%E6%98%8E
    
    public static function getEmbeddedList($aid,$start=0,$num=0){
        try {
            $access_token =  self::access_token($aid,'wx');
            $url = "https://api.weixin.qq.com/wxaapi/wxaembedded/get_own_list?access_token={$access_token}&start={$start}";
//            if($num > 0){
//                $url.='&num='.$num;
//            }
            $res = json_decode(request_get($url),true);
            if($res['errcode'] == 0){
                return $res['wxa_embedded_list'];
            } else{
                return false;
            }
        }catch (\Exception $e){
            return false;
        }
    }

    /**
     * 获取小程序服务内容类型
     * https://developers.weixin.qq.com/doc/oplatform/openApi/OpenApiDoc/miniprogram-management/record/queryIcpServiceContentTypes.html
     * 该接口所属的权限集id为：156
     */
    public static function queryIcpServiceContentTypes($aid,$platform='wx')
    {
      return json_decode('[{"type":1,"parent_type":500,"name":"生活缴费","remark":"水电煤气话费等"},{"type":2,"parent_type":500,"name":"招聘"},{"type":3,"parent_type":500,"name":"工具","remark":"笔记、日历、记账、投票、天气、备忘录、办公、图片处理、计算器、报价\\/比价、信息查询、发票查询、预约\\/报名、字典、邮箱、输入法、投票、菜谱、ar识别、广告图文、回收租赁、寄存等"},{"type":4,"parent_type":500,"name":"家政","remark":"清洁、维修、开锁等"},{"type":5,"parent_type":500,"name":"婚庆"},{"type":6,"parent_type":500,"name":"房地产","remark":"租房、销售、中介、物业、装修等"},{"type":7,"parent_type":500,"name":"母婴"},{"type":8,"parent_type":500,"name":"共享服务","remark":"单车、充电宝等"},{"type":9,"parent_type":500,"name":"丽人","remark":"美甲、美容、美发、纹身等"},{"type":10,"parent_type":500,"name":"洗浴保健"},{"type":11,"parent_type":500,"name":"宠物"},{"type":12,"parent_type":500,"name":"法律咨询"},{"type":13,"parent_type":500,"name":"公证"},{"type":14,"parent_type":500,"name":"电子认证"},{"type":15,"parent_type":500,"name":"拍卖","remark":"文物、非文物、司法拍卖等"},{"type":16,"parent_type":500,"name":"专利\\/商标代理"},{"type":17,"parent_type":500,"name":"亲子\\/司法鉴定"},{"type":18,"parent_type":500,"name":"会计师事务所"},{"type":19,"parent_type":500,"name":"税务师事务所"},{"type":20,"parent_type":500,"name":"公共印章刻制"},{"type":21,"parent_type":500,"name":"公司转让信息服务"},{"type":22,"parent_type":500,"name":"一般财务服务"},{"type":23,"parent_type":500,"name":"公关\\/推广\\/市场调查"},{"type":24,"parent_type":500,"name":"网络代理"},{"type":25,"parent_type":500,"name":"企业管理"},{"type":26,"parent_type":500,"name":"会展服务"},{"type":27,"parent_type":500,"name":"出国移民"},{"type":28,"parent_type":500,"name":"摄影\\/扩印"},{"type":29,"parent_type":500,"name":"质量检测"},{"type":30,"parent_type":500,"name":"机械维修"},{"type":31,"parent_type":500,"name":"工商代理注册"},{"type":32,"parent_type":500,"name":"其他"},{"type":33,"parent_type":501,"name":"点餐"},{"type":34,"parent_type":501,"name":"外卖"},{"type":35,"parent_type":501,"name":"其他"},{"type":36,"parent_type":502,"name":"福利彩票"},{"type":37,"parent_type":502,"name":"游戏"},{"type":38,"parent_type":502,"name":"歌舞厅、KTV等娱乐、休闲娱乐类服务"},{"type":39,"parent_type":502,"name":"健身房\\/瑜伽\\/舞蹈\\/美体机构"},{"type":40,"parent_type":502,"name":"棋牌桌游\\/电玩网吧"},{"type":41,"parent_type":502,"name":"网络社交","remark":"熟人社交、陌生人社交、婚恋、社区\\/论坛、直播、会议、问答、直播答题等"},{"type":42,"parent_type":502,"name":"网络图书","remark":"电子书、网络小说、学术期刊等"},{"type":43,"parent_type":502,"name":"视频","remark":"在线观看、视频广场、短视频等"},{"type":44,"parent_type":502,"name":"音乐、电台、有声读物"},{"type":45,"parent_type":502,"name":"文化场馆"},{"type":46,"parent_type":502,"name":"体育","remark":"场馆、赛事、培训等"},{"type":47,"parent_type":502,"name":"游乐园、嘉年华"},{"type":48,"parent_type":502,"name":"院线影城、演出"},{"type":49,"parent_type":503,"name":"景区服务"},{"type":50,"parent_type":503,"name":"旅行社"},{"type":51,"parent_type":503,"name":"旅游管理单位"},{"type":52,"parent_type":503,"name":"旅游退税"},{"type":53,"parent_type":503,"name":"OTA（在线旅行社）","remark":"在线旅行社，例如飞猪，携程等"},{"type":54,"parent_type":503,"name":"住宿服务"},{"type":55,"parent_type":503,"name":"出境","remark":"签证、wifi、国际驾照等"},{"type":56,"parent_type":503,"name":"其他"},{"type":57,"parent_type":504,"name":"火车\\/高铁\\/动车"},{"type":58,"parent_type":504,"name":"航空"},{"type":59,"parent_type":504,"name":"公交\\/地铁"},{"type":60,"parent_type":504,"name":"打车\\/出租车"},{"type":61,"parent_type":504,"name":"自行车"},{"type":62,"parent_type":504,"name":"长途汽车"},{"type":63,"parent_type":504,"name":"船舶","remark":"港口等"},{"type":64,"parent_type":504,"name":"导航"},{"type":65,"parent_type":504,"name":"加油"},{"type":66,"parent_type":504,"name":"高速"},{"type":67,"parent_type":504,"name":"其他"},{"type":68,"parent_type":505,"name":"维修保养","remark":"维修\\/美容\\/养护\\/洗车等"},{"type":69,"parent_type":505,"name":"汽车用品","remark":"各种交通工具零部件、内饰\\/外饰等"},{"type":70,"parent_type":505,"name":"车辆销售\\/二手车销售","remark":"销售、估值、报价、查询（车、车企、经销商、4s店）、车展等"},{"type":71,"parent_type":505,"name":"代驾"},{"type":72,"parent_type":505,"name":"租车"},{"type":73,"parent_type":505,"name":"停车"},{"type":74,"parent_type":505,"name":"充电"},{"type":75,"parent_type":505,"name":"Etc"},{"type":76,"parent_type":505,"name":"道路救援"},{"type":77,"parent_type":505,"name":"车联网"},{"type":78,"parent_type":506,"name":"儿童教育"},{"type":79,"parent_type":506,"name":"培训机构","remark":"驾校、国内、国外、艺术类、线上线下、特殊教育等"},{"type":80,"parent_type":506,"name":"学校","remark":"校园服务等"},{"type":81,"parent_type":506,"name":"其他"},{"type":82,"parent_type":507,"name":"快递"},{"type":83,"parent_type":507,"name":"物流"},{"type":84,"parent_type":507,"name":"仓储"},{"type":85,"parent_type":507,"name":"快递柜"},{"type":86,"parent_type":507,"name":"其他"},{"type":87,"parent_type":508,"name":"医院","remark":"私立、公立、社区卫生站、就医服务等"},{"type":88,"parent_type":508,"name":"医疗器械"},{"type":89,"parent_type":508,"name":"医药"},{"type":90,"parent_type":508,"name":"健康护理"},{"type":91,"parent_type":508,"name":"血液、干细胞服务","remark":"献血"},{"type":92,"parent_type":508,"name":"临床试验"},{"type":93,"parent_type":509,"name":"时政信息"},{"type":94,"parent_type":509,"name":"政务服务大厅"},{"type":95,"parent_type":509,"name":"交通出行及运输","remark":"出行、运输、交管、航空"},{"type":96,"parent_type":509,"name":"户政"},{"type":97,"parent_type":509,"name":"公安（含国安）及消防"},{"type":98,"parent_type":509,"name":"出境入境及边防"},{"type":99,"parent_type":509,"name":"司法公证","remark":"司法 检查 法院"},{"type":100,"parent_type":509,"name":"纪检审计"},{"type":101,"parent_type":509,"name":"财政"},{"type":102,"parent_type":509,"name":"民政"},{"type":103,"parent_type":509,"name":"住房保障","remark":"住建 公积金"},{"type":104,"parent_type":509,"name":"党\\/团\\/组织"},{"type":105,"parent_type":509,"name":"文体及教育科研"},{"type":106,"parent_type":509,"name":"人力资源及社会保障","remark":"人力资源中积分政策合并入户籍办理"},{"type":107,"parent_type":509,"name":"环保绿化"},{"type":108,"parent_type":509,"name":"水利水务"},{"type":109,"parent_type":509,"name":"气象地质"},{"type":110,"parent_type":509,"name":"市场监督管理"},{"type":111,"parent_type":509,"name":"医疗卫生"},{"type":112,"parent_type":509,"name":"国土规划建设"},{"type":113,"parent_type":509,"name":"质量技术"},{"type":114,"parent_type":509,"name":"食品监督管理","remark":"食品监督管理部门比较多，涉及卫生部、农业部、市监局、药监局、工信部等"},{"type":115,"parent_type":509,"name":"新闻出版及广电"},{"type":116,"parent_type":509,"name":"税收财务"},{"type":117,"parent_type":509,"name":"金融"},{"type":118,"parent_type":509,"name":"知识产权"},{"type":119,"parent_type":509,"name":"信访"},{"type":120,"parent_type":509,"name":"公用事业","remark":"城管、街道居委、城市道路"},{"type":121,"parent_type":509,"name":"海关口岸","remark":"海关改为海关口岸"},{"type":122,"parent_type":509,"name":"邮政"},{"type":123,"parent_type":509,"name":"检验检疫"},{"type":124,"parent_type":509,"name":"商务贸易","remark":"商务改为商务贸易"},{"type":125,"parent_type":509,"name":"农林畜牧海洋"},{"type":126,"parent_type":509,"name":"社科档案及文物"},{"type":127,"parent_type":509,"name":"安全生产及应急"},{"type":128,"parent_type":509,"name":"科技创新"},{"type":129,"parent_type":509,"name":"统计"},{"type":130,"parent_type":509,"name":"经济发展与改革"},{"type":131,"parent_type":509,"name":"烟草"},{"type":132,"parent_type":509,"name":"网信"},{"type":133,"parent_type":509,"name":"工信"},{"type":134,"parent_type":509,"name":"能源","remark":"新增能源，包括电力、燃气等资源管理"},{"type":135,"parent_type":509,"name":"乡村振兴"},{"type":136,"parent_type":509,"name":"民族宗教"},{"type":137,"parent_type":509,"name":"信用"},{"type":138,"parent_type":509,"name":"公益"},{"type":139,"parent_type":510,"name":"保险","remark":"第三方互联网保险等"},{"type":140,"parent_type":510,"name":"银行","remark":"信用卡、电子汇票等"},{"type":141,"parent_type":510,"name":"信托"},{"type":142,"parent_type":510,"name":"公募基金"},{"type":143,"parent_type":510,"name":"私募基金"},{"type":144,"parent_type":510,"name":"证券\\/期货","remark":"投资咨询等"},{"type":145,"parent_type":510,"name":"非金融机构自营小额贷款"},{"type":146,"parent_type":510,"name":"融资担保"},{"type":147,"parent_type":510,"name":"商业保理"},{"type":148,"parent_type":510,"name":"汽车金融\\/融资租赁"},{"type":149,"parent_type":510,"name":"征信业务"},{"type":150,"parent_type":510,"name":"新三板信息服务平台"},{"type":151,"parent_type":510,"name":"股票信息服务平台（港股\\/美股）"},{"type":152,"parent_type":510,"name":"股票信息服务平台"},{"type":153,"parent_type":510,"name":"外币兑换"},{"type":154,"parent_type":510,"name":"实物黄金买卖"},{"type":155,"parent_type":510,"name":"消费金融"},{"type":156,"parent_type":510,"name":"收单商户服务"},{"type":157,"parent_type":510,"name":"区域性股权交易市场"},{"type":158,"parent_type":511,"name":"音视频设备","remark":"设备购买服务，视频线、摄像头等"},{"type":159,"parent_type":511,"name":"电信业务","remark":"运营商、业务代理、转售等"},{"type":160,"parent_type":511,"name":"多方通信"},{"type":161,"parent_type":511,"name":"软件开发"},{"type":162,"parent_type":511,"name":"网络推广"},{"type":163,"parent_type":512,"name":"零售批发","remark":"电商、超市便利店等"},{"type":164,"parent_type":512,"name":"自主售卖"},{"type":165,"parent_type":512,"name":"百货商场\\/购物中心"},{"type":166,"parent_type":513,"name":"安全生产"},{"type":167,"parent_type":513,"name":"杀毒软件","remark":"又叫反病毒软件.如，G+Data（德国）、360杀毒、卡巴斯基安全部队、小红伞、瑞星杀毒软件、金山毒霸等"},{"type":168,"parent_type":513,"name":"辅助性安全软件","remark":"主要是清理垃圾、修复漏洞、防木马的软件，比如360安全卫士、金山卫士、瑞星安全助手等等"},{"type":169,"parent_type":513,"name":"反流氓软件","remark":"主要是清理流氓软件，保护系统安全的功能.如360安全卫士、恶意软件清理助手、超级兔子、Windows清理助手等等"},{"type":170,"parent_type":513,"name":"加密软件","remark":"主要是通过对数据文件进行加密，防止外泄，从而确保信息资产的安全！加密软件按照实现的方法可划分为被动加密和主动加密。目前市面上的驱动层透明加密技术成为了最可靠、最安全的加密技术，代表厂商有广东南方信息安全产业基地"},{"type":171,"parent_type":513,"name":"其他"},{"type":172,"parent_type":514,"name":"工业研发设计"},{"type":173,"parent_type":514,"name":"生产管理"},{"type":174,"parent_type":514,"name":"其他"},{"type":175,"parent_type":515,"name":"宗教信息"},{"type":176,"parent_type":515,"name":"咨询广场"},{"type":500,"parent_type":0,"name":"生活服务"},{"type":501,"parent_type":0,"name":"餐饮"},{"type":502,"parent_type":0,"name":"休闲娱乐"},{"type":503,"parent_type":0,"name":"旅游服务"},{"type":504,"parent_type":0,"name":"交通运输"},{"type":505,"parent_type":0,"name":"汽车服务"},{"type":506,"parent_type":0,"name":"教育"},{"type":507,"parent_type":0,"name":"仓储和邮政业"},{"type":508,"parent_type":0,"name":"医疗服务"},{"type":509,"parent_type":0,"name":"政务服务"},{"type":510,"parent_type":0,"name":"金融业"},{"type":511,"parent_type":0,"name":"信息传输、软件和信息技术服务业"},{"type":512,"parent_type":0,"name":"批发和零售业"},{"type":513,"parent_type":0,"name":"安全"},{"type":514,"parent_type":0,"name":"工业互联网软件"},{"type":515,"parent_type":0,"name":"其他"}]',true);

        try {
            $appinfo = System::appinfo($aid,$platform);
            if($appinfo){
                $appid = $appinfo['appid'];
                $token = self::access_token($aid,$platform);
                $url = "https://api.weixin.qq.com/wxa/icp/query_icp_service_content_types?access_token={$token}";
                $rs = request_get($url);
                \think\facade\Log::write([
                    'file'=>__FILE__,
                    'line'=>__LINE__,
                    'fun'=>__FUNCTION__,
                    'rs'=>$rs
                ]);
                $rs = json_decode($rs,true);
                if($rs['errcode'] =='0'){
                  return $rs['items'];
                    return ['status' => 1,'items'=>$rs['items']];
                }else{
                    \think\facade\Log::error([
                        'file'=>__FILE__,
                        'line'=>__LINE__,
                        'fun'=>__FUNCTION__,
                        'rs'=>jsonEncode($rs)
                    ]);
                    return [];
                    return ['status' => 0, 'msg' => self::geterror($rs['errcode']),'errcode'=>$rs['errcode']];
                }
            }
        }catch (\Exception $e){
          return [];
            return ['status' => 0, 'msg' => $e->getMessage()];
        }
    }
    /**
     * 获取证件类型
     * https://developers.weixin.qq.com/doc/oplatform/openApi/OpenApiDoc/miniprogram-management/record/queryIcpCertificateTypes.html
     * 该接口所属的权限集id为：156
     * 证件类型与单位性质存在关联，例如：个人的证件类型有居民身份证、护照，而企业则不存在这两类证件。接口返回的证件列表中会带有 subject_type 字段指示该证件属于哪种单位，例如：政府机关的单位性质 id 为 2，那么政府机关可选的证件类型仅有 subject_type 为 2 的“统一社会信用代码证书”以及“组织机构代码证”
     */
    public static function queryIcpCertificateTypes($aid,$platform='wx')
    {

      return json_decode('[{"type":1,"subject_type":4,"name":"营业执照（个人或企业）"},{"type":2,"subject_type":5,"name":"居民身份证"},{"type":3,"subject_type":3,"name":"组织机构代码证"},{"type":4,"subject_type":3,"name":"事业单位法人证书"},{"type":5,"subject_type":1,"name":"部队代号"},{"type":6,"subject_type":6,"name":"社会团体法人登记证书"},{"type":7,"subject_type":5,"name":"护照"},{"type":9,"subject_type":2,"name":"组织机构代码证"},{"type":10,"subject_type":6,"name":"组织机构代码证"},{"type":11,"subject_type":5,"name":"台湾居民来往大陆通行证"},{"type":12,"subject_type":4,"name":"组织机构代码证"},{"type":13,"subject_type":2,"name":"统一社会信用代码证书"},{"type":14,"subject_type":5,"name":"港澳居民来往内地通行证"},{"type":15,"subject_type":3,"name":"统一社会信用代码证书"},{"type":16,"subject_type":9,"name":"组织机构代码证"},{"type":17,"subject_type":9,"name":"民办非企业单位登记证书"},{"type":18,"subject_type":10,"name":"组织机构代码证"},{"type":19,"subject_type":10,"name":"基金会法人登记证书"},{"type":20,"subject_type":11,"name":"组织机构代码证"},{"type":21,"subject_type":11,"name":"律师事务所执业许可证"},{"type":22,"subject_type":12,"name":"外国在华文化中心登记证","remark":"关于外国政府在中国设立的文化中心办理全国组织机构代码证有关事项的通知（文外函[2009]199号）"},{"type":23,"subject_type":1,"name":"军队单位对外有偿服务许可证"},{"type":24,"subject_type":13,"name":"统一社会信用代码证书"},{"type":25,"subject_type":15,"name":"宗教活动场所登记证"},{"type":27,"subject_type":4,"name":"外国企业常驻代表机构登记证","remark":"中华人民共和国国务院令第584号《外国企业常驻代表机构登记管理条例》"},{"type":28,"subject_type":14,"name":"司法鉴定许可证","remark":"司法鉴定许可证登记统一社会信用代码号"},{"type":30,"subject_type":5,"name":"外国人永久居留身份证","remark":"工信管函(2O17)1488号"},{"type":34,"subject_type":12,"name":"外国政府旅游部门常驻代表机构批准登记证"},{"type":35,"subject_type":16,"name":"境外机构证件","remark":"国外结构所持有的证件号码"},{"type":36,"subject_type":9,"name":"社会服务机构登记证书"},{"type":37,"subject_type":9,"name":"民办学校办学许可证"},{"type":38,"subject_type":17,"name":"医疗机构执业许可证"},{"type":39,"subject_type":18,"name":"公证机构执业证"},{"type":40,"subject_type":12,"name":"北京市外国驻华使馆人员子女学校办学许可证"},{"type":41,"subject_type":5,"name":"港澳居民居住证"},{"type":42,"subject_type":5,"name":"台湾居民居住证"},{"type":43,"subject_type":19,"name":"农村集体经济组织登记证"},{"type":44,"subject_type":20,"name":"仲裁委员会登记证"}]', true);
        try {
            $appinfo = System::appinfo($aid,$platform);
            if($appinfo){
                $appid = $appinfo['appid'];
                $token = self::access_token($aid,$platform);
                $url = "https://api.weixin.qq.com/wxa/icp/query_icp_certificate_types?access_token={$token}";
                $rs = request_get($url);
                \think\facade\Log::write([
                    'file'=>__FILE__,
                    'line'=>__LINE__,
                    'fun'=>__FUNCTION__,
                    'rs'=>$rs
                ]);
                $rs = json_decode($rs,true);
                if($rs['errcode'] =='0'){
                  return $rs['items'];
                    return ['status' => 1,'items'=>$rs['items']];
                }else{
                    \think\facade\Log::error([
                        'file'=>__FILE__,
                        'line'=>__LINE__,
                        'fun'=>__FUNCTION__,
                        'rs'=>jsonEncode($rs)
                    ]);
                    return [];
                    return ['status' => 0, 'msg' => self::geterror($rs['errcode']),'errcode'=>$rs['errcode']];
                }
            }
        }catch (\Exception $e){
          return [];
            return ['status' => 0, 'msg' => $e->getMessage()];
        }
    }

    /**
     * 获取单位性质
     * https://developers.weixin.qq.com/doc/oplatform/openApi/OpenApiDoc/miniprogram-management/record/queryIcpSubjectTypes.html
     * 该接口所属的权限集id为：156
     */
    public static function queryIcpSubjectTypes($aid,$platform='wx')
    {
      return json_decode('[{"type":1,"name":"国防机构"},{"type":2,"name":"政府机关"},{"type":3,"name":"事业单位"},{"type":4,"name":"企业"},{"type":5,"name":"个人"},{"type":6,"name":"社会团体"},{"type":9,"name":"民办非企业单位"},{"type":10,"name":"基金会"},{"type":11,"name":"律师执业机构"},{"type":12,"name":"外国在华文化中心","remark":"关于外国政府在中国设立的文化中心办理全国组织机构代码证有关事项的通知（文外函[2009]199号）"},{"type":13,"name":"群众性团体组织"},{"type":14,"name":"司法鉴定机构"},{"type":15,"name":"宗教团体"},{"type":16,"name":"境外机构"},{"type":17,"name":"医疗机构"},{"type":18,"name":"公证机构"},{"type":19,"name":"集体经济"},{"type":20,"name":"仲裁机构"}]',true);
        try {
            $appinfo = System::appinfo($aid,$platform);
            if($appinfo){
                $appid = $appinfo['appid'];
                $token = self::access_token($aid,$platform);
                $url = "https://api.weixin.qq.com/wxa/icp/query_icp_subject_types?access_token={$token}";
                $rs = request_get($url);
                \think\facade\Log::write([
                    'file'=>__FILE__,
                    'line'=>__LINE__,
                    'fun'=>__FUNCTION__,
                    'rs'=>$rs
                ]);
                $rs = json_decode($rs,true);
                if($rs['errcode'] =='0'){
                  return $rs['items'];
                    return ['status' => 1,'items'=>$rs['items']];
                }else{
                    \think\facade\Log::error([
                        'file'=>__FILE__,
                        'line'=>__LINE__,
                        'fun'=>__FUNCTION__,
                        'rs'=>jsonEncode($rs)
                    ]);
                    return [];
                    return ['status' => 0, 'msg' => self::geterror($rs['errcode']),'errcode'=>$rs['errcode']];
                }
            }
        }catch (\Exception $e){
          return [];
            return ['status' => 0, 'msg' => $e->getMessage()];
        }
    }

    /**
     * 获取区域信息
     * https://developers.weixin.qq.com/doc/oplatform/openApi/OpenApiDoc/miniprogram-management/record/queryIcpDistrictCode.html
     * 该接口所属的权限集id为：156
     */
    public static function queryIcpDistrictCode($aid,$platform='wx')
    {
        try {
            $appinfo = System::appinfo($aid,$platform);
            if($appinfo){
                $token = self::access_token($aid,$platform);
                $url = "https://api.weixin.qq.com/wxa/icp/query_icp_district_code?access_token={$token}";
                $rs = request_get($url);
                \think\facade\Log::write([
                    'file'=>__FILE__,
                    'line'=>__LINE__,
                    'fun'=>__FUNCTION__,
                    'rs'=>$rs
                ]);
                $rs = json_decode($rs,true);
                if($rs['errcode'] =='0'){
                    return ['status' => 1,'items'=>$rs['items']];
                }else{
                    \think\facade\Log::error([
                        'file'=>__FILE__,
                        'line'=>__LINE__,
                        'fun'=>__FUNCTION__,
                        'rs'=>jsonEncode($rs)
                    ]);
                    return ['status' => 0, 'msg' => self::geterror($rs['errcode']),'errcode'=>$rs['errcode']];
                }
            }
        }catch (\Exception $e){
            return ['status' => 0, 'msg' => $e->getMessage()];
        }
    }
    /**
     * 获取前置审批项类型
     * https://developers.weixin.qq.com/doc/oplatform/openApi/OpenApiDoc/miniprogram-management/record/queryIcpNrlxTypes.html
     * 该接口所属的权限集id为：156
     */
    public static function queryIcpNrlxTypes($aid,$platform='wx')
    {
      return json_decode('[{"type":1,"name":"新闻"},{"type":2,"name":"出版"},{"type":3,"name":"教育"},{"type":4,"name":"医疗保健"},{"type":5,"name":"药品和医疗器械"},{"type":6,"name":"电子公告服务"},{"type":9,"name":"文化"},{"type":10,"name":"广播电影电视节目"},{"type":12,"name":"网络预约车"},{"type":13,"name":"互联网金融"},{"type":14,"name":"校外培训"},{"type":15,"name":"宗教"},{"type":24,"name":"以上都不涉及"}]',true);
        try {
            $appinfo = System::appinfo($aid,$platform);
            if($appinfo){
                $token = self::access_token($aid,$platform);
                $url = "https://api.weixin.qq.com/wxa/icp/query_icp_nrlx_types?access_token={$token}";
                $rs = request_get($url);
                \think\facade\Log::write([
                    'file'=>__FILE__,
                    'line'=>__LINE__,
                    'fun'=>__FUNCTION__,
                    'rs'=>$rs
                ]);
                $rs = json_decode($rs,true);
                if($rs['errcode'] =='0'){
                  return $rs['items'];
                    return ['status' => 1,'items'=>$rs['items']];
                }else{
                    \think\facade\Log::error([
                        'file'=>__FILE__,
                        'line'=>__LINE__,
                        'fun'=>__FUNCTION__,
                        'rs'=>jsonEncode($rs)
                    ]);
                    return [];
                    return ['status' => 0, 'msg' => self::geterror($rs['errcode']),'errcode'=>$rs['errcode']];
                }
            }
        }catch (\Exception $e){
          return [];
            return ['status' => 0, 'msg' => $e->getMessage()];
        }
    }


  /**
   * https://developers.weixin.qq.com/doc/oplatform/openApi/OpenApiDoc/miniprogram-management/record/getIcpEntranceInfo.html
   * @description 小程序备案状态及驳回原因
   * @param $aid
   * @return array
   */
  public static function icpStatus($aid, $appid)
  {
    $access_token = self::access_token($aid);
    $url = "https://api.weixin.qq.com/wxa/icp/get_icp_entrance_info?access_token={$access_token}";
    $response = curl_get($url);
    $response = json_decode($response, true);
    if($response['errcode'] =='0'){
      $res = $response['info'];
      $data = [
        'status_code'=>$res['status'],
        'status_text'=>self::icpStatusText($res['status']),
        'is_canceling'=>$res['is_canceling'],
        'reason'=>[]
      ];
      if($res['status'] == 3 || $res['status'] == 5){
        $data['reason'] = $res['audit_data'];
      }
      Db::name("admin_wxicp")->where('aid', $aid)->where('appid', $appid)->save([
        "beian_status"=>$res['status'],
        "beian_reason"=>jsonEncode($res['audit_data'])
      ]);
      return ['status'=>0, 'msg'=>'', 'data'=>$data];
    }else{
      return ['status'=>1, 'msg'=>$response['errmsg']];
    }
  }

    //小程序已备案详情
    public static function icpOrder($aid)
    {
        $access_token = self::access_token($aid);
        $url = "https://api.weixin.qq.com/wxa/icp/get_online_icp_order?access_token={$access_token}";
        $response = curl_get($url);
//        $response = json_decode($response, true);
        \think\facade\Log::write([
            'type'=>'已备案信息',
            'data'=>$response
        ]);
    }

  public static function setMediaId($base_info, $icp_data, $aid, $appid)
  {
    //主体证件照片
    if($base_info['subject_certificate_photo'] != $icp_data['subject_certificate_photo'] && $icp_data['subject_certificate_photo'] != ''){
      //上传备案资料
      $media_data = self::uploadMedia($icp_data['subject_certificate_photo'] ,$icp_data['subject_certificate_type'],'icp_subject.organize_info.certificate_photo',$aid);
      if($media_data['status']==0){
        return ['status'=>0,'msg'=>'主体证件照片上传错误.错误原因：'.$media_data['data']];
      }else{
        //更新备案资料信息，避免重复上传
        Db::name("admin_wxicp")->where("aid", $aid)->where('appid', $appid)->update([
          "subject_certificate_photo_media_id" => $media_data['media_id'],
          "subject_certificate_photo" => $icp_data['subject_certificate_photo'],
        ]);
      }
    }
    //临时居住证
    if($base_info['subject_residence_permit'] != $icp_data['subject_residence_permit'] && $icp_data['subject_residence_permit'] != ''){
      //上传备案资料
      $media_data = self::uploadMedia($icp_data['subject_residence_permit'] ,'','icp_subject.personal_info.residence_permit',$aid);
      if($media_data['status']==0){
        return ['status'=>0,'msg'=>'临时居住证上传错误.错误原因：'.$media_data['data']];
      }else{
        //更新备案资料信息，避免重复上传
        Db::name("admin_wxicp")->where("aid", $aid)->where('appid', $appid)->update([
          "subject_residence_permit_media_id" => $media_data['media_id'],
          "subject_residence_permit" => $icp_data['subject_residence_permit'],
        ]);
      }
    }

    //负责人 正面
    if($base_info['principal_certificate_photo_front'] != $icp_data['principal_certificate_photo_front'] && $icp_data['principal_certificate_photo_front'] != ''){
      //上传备案资料
      $media_data = self::uploadMedia($icp_data['principal_certificate_photo_front'] ,$icp_data['principal_certificate_type'],'icp_subject.principal_info.certificate_photo_front',$aid);
      if($media_data['status']==0){
        return ['status'=>0,'msg'=>'主体负责人正面照上传错误.错误原因：'.$media_data['data']];
      }else{
        //更新备案资料信息，避免重复上传
        Db::name("admin_wxicp")->where("aid", $aid)->where('appid', $appid)->update([
          "principal_certificate_photo_front_media_id" => $media_data['media_id'],
          "principal_certificate_photo_front" => $icp_data['principal_certificate_photo_front'],
        ]);
      }
    }

    //主体负责人 背面
    if($base_info['principal_certificate_photo_back'] != $icp_data['principal_certificate_photo_back'] && $icp_data['principal_certificate_photo_back'] != ''){
      //上传备案资料
      $media_data = self::uploadMedia($icp_data['principal_certificate_photo_back'] ,$icp_data['principal_certificate_type'],'icp_subject.principal_info.certificate_photo_back',$aid);
      if($media_data['status']==0){
        return ['status'=>0,'msg'=>'主体负责人背面上传错误.错误原因：'.$media_data['data']];
      }else{
        //更新备案资料信息，避免重复上传
        Db::name("admin_wxicp")->where("aid", $aid)->where('appid', $appid)->update([
          "principal_certificate_photo_back_media_id" => $media_data['media_id'],
          "principal_certificate_photo_back" => $icp_data['principal_certificate_photo_back'],
        ]);
      }
    }

    //主体授权书
    if($base_info['principal_authorization_letter'] != $icp_data['principal_authorization_letter'] && $icp_data['principal_authorization_letter'] != ''){
      //上传备案资料
      $media_data = self::uploadMedia($icp_data['principal_authorization_letter'] ,'','icp_subject.principal_info.authorization_letter',$aid);
      if($media_data['status']==0){
        return ['status'=>0,'msg'=>'主体授权书上传错误.错误原因：'.$media_data['data']];
      }else{
        //更新备案资料信息，避免重复上传
        Db::name("admin_wxicp")->where("aid", $aid)->where('appid', $appid)->update([
          "principal_authorization_letter_media_id" => $media_data['media_id'],
          "principal_authorization_letter" => $icp_data['principal_authorization_letter'],
        ]);
      }
    }

    //小程序负责人 正面
    if($base_info['manager_certificate_photo_front'] != $icp_data['manager_certificate_photo_front'] && $icp_data['manager_certificate_photo_front'] != ''){
      //上传备案资料
      $media_data = self::uploadMedia($icp_data['manager_certificate_photo_front'] ,$icp_data['manager_certificate_type'],'icp_applets.principal_info.certificate_photo_front',$aid);
      if($media_data['status']==0){
        return ['status'=>0,'msg'=>'小程序负责人正面上传错误.错误原因：'.$media_data['data']];
      }else{
        //更新备案资料信息，避免重复上传
        Db::name("admin_wxicp")->where("aid", $aid)->where('appid', $appid)->update([
          "manager_certificate_photo_front_media_id" => $media_data['media_id'],
          "manager_certificate_photo_front" => $icp_data['manager_certificate_photo_front'],
        ]);
      }
    }
    //小程序负责人 背面
    if($base_info['manager_certificate_photo_back'] != $icp_data['manager_certificate_photo_back'] && $icp_data['manager_certificate_photo_back'] != ''){
      //上传备案资料
      $media_data = self::uploadMedia($icp_data['manager_certificate_photo_back'] ,$icp_data['manager_certificate_type'],'icp_applets.principal_info.certificate_photo_back',$aid);
      if($media_data['status']==0){
        return ['status'=>0,'msg'=>'小程序负责人背面上传错误.错误原因：'.$media_data['data']];
      }else{
        //更新备案资料信息，避免重复上传
        Db::name("admin_wxicp")->where("aid", $aid)->where('appid', $appid)->update([
          "manager_certificate_photo_back_media_id" => $media_data['media_id'],
          "manager_certificate_photo_back" => $icp_data['manager_certificate_photo_back'],
        ]);
      }
    }
    //小程序负责人 授权书
    if($base_info['manager_authorization_letter'] != $icp_data['manager_authorization_letter'] && $icp_data['manager_authorization_letter'] != ''){
      //上传备案资料
      $media_data = self::uploadMedia($icp_data['manager_authorization_letter'] ,'','icp_applets.principal_info.authorization_letter',$aid);
      if($media_data['status']==0){
        return ['status'=>0,'msg'=>'小程序负责人授权书上传错误.错误原因：'.$media_data['data']];
      }else{
        //更新备案资料信息，避免重复上传
        Db::name("admin_wxicp")->where("aid", $aid)->where('appid', $appid)->update([
          "manager_authorization_letter_media_id" => $media_data['media_id'],
          "manager_authorization_letter" => $icp_data['manager_authorization_letter'],
        ]);
      }
    }
    //互联网信息服务承诺书
    if($base_info['commitment_letter'] != $icp_data['commitment_letter'] && $icp_data['commitment_letter'] != ''){
      //上传备案资料
      $media_data = self::uploadMedia($icp_data['commitment_letter'] ,'','icp_materials.commitment_letter',$aid);
      if($media_data['status']==0){
        return ['status'=>0,'msg'=>'互联网信息服务承诺书上传错误.错误原因：'.$media_data['data']];
      }else{
        //更新备案资料信息，避免重复上传
        Db::name("admin_wxicp")->where("aid", $aid)->where('appid', $appid)->update([
          "commitment_letter_media_id" => $media_data['media_id'],
          "commitment_letter" => $icp_data['commitment_letter'],
        ]);
      }
    }
    //主体更名函
    if($base_info['business_name_change_letter'] != $icp_data['business_name_change_letter'] && $icp_data['business_name_change_letter'] != ''){
      //上传备案资料
      $media_data = self::uploadMedia($icp_data['business_name_change_letter'] ,'','icp_materials.business_name_change_letter',$aid);
      if($media_data['status']==0){
        return ['status'=>0,'msg'=>'主体更名函上传错误.错误原因：'.$media_data['data']];
      }else{
        //更新备案资料信息，避免重复上传
        Db::name("admin_wxicp")->where("aid", $aid)->where('appid', $appid)->update([
          "business_name_change_letter_media_id" => $media_data['media_id'],
          "business_name_change_letter" => $icp_data['business_name_change_letter'],
        ]);
      }
    }
      //小程序其他附件
      if($base_info['applets_other_materials'] != $icp_data['applets_other_materials'] && $icp_data['applets_other_materials'] != ''){
          //上传备案资料
          $media_data = self::uploadMedia($icp_data['applets_other_materials'] ,'','icp_materials.applets_other_materials',$aid);
          if($media_data['status']==0){
              return ['status'=>0,'msg'=>'小程序其他附件.错误原因：'.$media_data['data']];
          }else{
              //更新备案资料信息，避免重复上传
              Db::name("admin_wxicp")->where("aid", $aid)->where('appid', $appid)->update([
                  "applets_other_materials_media_id" => $media_data['media_id'],
                  "applets_other_materials" => $icp_data['applets_other_materials'],
              ]);
          }
      }
    //小程序前置审批 nrlx_details
    $nrlx_details = $icp_data['nrlx_details'];
    foreach ($nrlx_details as $k=>&$nrlx_detail) {
      $media_data = self::uploadMedia($nrlx_detail['media'] ,'','icp_applets.base_info.nrlx_details',$aid);
      if($media_data['status']==0){
        return ['status'=>0,'msg'=>'前置审批第'.$k.'项上传错误.错误原因：'.$media_data['data']];
      }else{
        $nrlx_detail['media_id'] = $media_data['media_id'];
      }
    }
    //更新备案资料信息，避免重复上传
    Db::name("admin_wxicp")->where("aid", $aid)->where('appid', $appid)->update([
      "nrlx_details" => jsonEncode($nrlx_details),
    ]);
    return ['status'=>1,'msg'=>'上传成功'];
  }

  //上传备案资料
  // https://developers.weixin.qq.com/doc/oplatform/openApi/OpenApiDoc/miniprogram-management/record/uploadIcpMedia.html
  public static function uploadMedia($url, $certificate_type, $icp_order_field, $aid, $type = "image")
  {
    if(empty($url)){
      return ['status'=>1, 'data'=>'', 'media_id'=>''];
    }
    $url = \app\commons\Pic::tolocal($url);
    $mediapath = ROOT_PATH.str_replace(PRE_URL.'/','',$url);
    $media = new \CurlFile($mediapath);
    $access_token = self::access_token($aid);
    $url = "https://api.weixin.qq.com/wxa/icp/upload_icp_media?access_token={$access_token}";
    $request_body = [
      "type"=>$type,
      "certificate_type"=>intval($certificate_type),
      "icp_order_field"=>$icp_order_field,
      "media"=>$media
    ];
//      \think\facade\Log::write([
//          'msg'=>'小程序备案-备案资料上传',
//          'data'=>jsonEncode($request_body)
//      ]);
    $response = curl_post($url, $request_body);
    $data = json_decode($response, true);
//      \think\facade\Log::write([
//          'msg'=>'小程序备案-备案资料上传响应数据',
//          'data'=>$response
//      ]);
    if($data['errcode'] =='0'){
      return ['status'=>1, 'data'=>'', 'media_id'=>$data['media_id']];
    }else{
      return ['status'=>0, 'data'=>$data['errmsg']];
    }

  }

  public static function icpStatusText($status)
  {
    $status_text = [
      "2"=>"平台审核中",
      "3"=>"平台审核驳回",
      "4"=>"管局审核中",
      "5"=>"管局审核驳回",
      "6"=>"已备案",
      "1024"=>"未备案",
      "1025"=>"未备案、小程序信息未填",
      "1026"=>"未备案、小程序类目未填",
      "1027"=>"未备案、小程序信息未填、小程序类目未填",
      "1028"=>"未备案、小程序未认证",
      "1029"=>"未备案、小程序信息未填、小程序未认证",
      "1030"=>"未备案、小程序类目未填、小程序未认证",
      "1031"=>"未备案、小程序信息未填、小程序类目未填、小程序未认证",
    ];
    return $status_text[$status]??'';
  }

  //申请备案
  // https://developers.weixin.qq.com/doc/oplatform/openApi/OpenApiDoc/miniprogram-management/record/applyIcpFiling.html
  public static function applyIcp($aid)
  {
    $appinfo = System::appinfo($aid,'wx');
    $appid = $appinfo['appid'];
    $icp_data = Db::name("admin_wxicp")->where('aid', $aid)->where('appid', $appid)->find();
    $nrlx_details = json_decode($icp_data['nrlx_details'], true);
    $apply_data = [
      "icp_subject"=>[
        "base_info"=>[
          "type"=>intval($icp_data['subject_type']),
          "name"=>$icp_data['subject_name'],
          "province"=>$icp_data['subject_province'],
          "city"=>$icp_data['subject_city'],
          "district"=>$icp_data['subject_district'],
          "address"=>$icp_data['subject_address'],
          "comment"=>$icp_data['subject_comment'],
        ],
        "organize_info"=>[
          "certificate_type"=>intval($icp_data['subject_certificate_type']),
          "certificate_number"=>$icp_data['subject_certificate_number'],
          "certificate_address"=>$icp_data['subject_certificate_address'],
          "certificate_photo"=>$icp_data['subject_certificate_photo_media_id'],
        ],
        "principal_info"=>[
          "name"=>$icp_data['principal_name'],
          "mobile"=>$icp_data['principal_mobile'],
          "email"=>$icp_data['principal_email'],
          "emergency_contact"=>$icp_data['principal_emergency_contact'],
          "certificate_type"=>intval($icp_data['principal_certificate_type']),
          "certificate_number"=>$icp_data['principal_certificate_number'],
          "certificate_validity_date_start"=>date("Ymd", strtotime($icp_data['principal_certificate_validity_date_start'])),
          "certificate_validity_date_end"=>$icp_data['principal_certificate_validity_date_cq']==1?'长期':date("Ymd", strtotime($icp_data['principal_certificate_validity_date_end'])),
          "certificate_photo_front"=>$icp_data['principal_certificate_photo_front_media_id'],
          "certificate_photo_back"=>$icp_data['principal_certificate_photo_back_media_id'],
          "verify_task_id"=>$icp_data['face_verify_task_id']
        ],
      ],
      "icp_applets"=>[
        "base_info"=>[
          "service_content_types"=>array_map(function ($val){
            $val = intval($val);
            return $val;
          }, explode(',', $icp_data['service_content_types'])),
          "nrlx_details"=>array_map(function ($nrlx_detail) {
            $nrlx_detail['media'] = $nrlx_detail['media_id'];
            $nrlx_detail['type'] = intval($nrlx_detail['type']);
            unset($nrlx_detail['media_id']);
            return $nrlx_detail;
          }, $nrlx_details),
          "comment"=>$icp_data['app_comment'],
        ],
        "principal_info"=>[
          "name"=>$icp_data['manager_name'],
          "mobile"=>$icp_data['manager_mobile'],
          "email"=>$icp_data['manager_email'],
          "emergency_contact"=>$icp_data['manager_emergency_contact'],
          "certificate_type"=>intval($icp_data['manager_certificate_type']),
          "certificate_number"=>$icp_data['manager_certificate_number'],
          "certificate_validity_date_start"=>date("Ymd", strtotime($icp_data['manager_certificate_validity_date_start'])),
          "certificate_validity_date_end"=>$icp_data['manager_certificate_validity_date_cq']==1?'长期':date("Ymd", strtotime($icp_data['manager_certificate_validity_date_end'])),
          "certificate_photo_front"=>$icp_data['manager_certificate_photo_front_media_id'],
          "certificate_photo_back"=>$icp_data['manager_certificate_photo_back_media_id'],
          "verify_task_id"=>$icp_data['face_verify_task_id']
        ]
      ]
    ];
    if($icp_data['legal_person_name']){
        $apply_data['icp_subject']['legal_person_info']['name'] = $icp_data['legal_person_name'];
        $apply_data['icp_subject']['legal_person_info']['certificate_number'] = $icp_data['legal_person_certificate_number'];
    }
    if($icp_data['subject_residence_permit_media_id']){
        $apply_data['icp_subject']['personal_info']['residence_permit'] = $icp_data['subject_residence_permit_media_id'];
    }
    if($icp_data['commitment_letter_media_id']){
      $apply_data["icp_materials"]["commitment_letter"] = $icp_data['commitment_letter_media_id'];
    }
    if($icp_data['business_name_change_letter_media_id']){
      $apply_data["icp_materials"]["business_name_change_letter"] = $icp_data['business_name_change_letter_media_id'];
    }
      if($icp_data['applets_other_materials_media_id']){
          $apply_data["icp_materials"]["applets_other_materials"] = $icp_data['applets_other_materials_media_id'];
      }
    if($icp_data['manager_authorization_letter_media_id']){
      $apply_data["icp_applets"]["principal_info"]["authorization_letter"] = $icp_data['manager_authorization_letter_media_id'];
    }
    if($icp_data['principal_authorization_letter_media_id']){
      $apply_data["icp_subject"]["principal_info"]["authorization_letter"] = $icp_data['principal_authorization_letter_media_id'];
    }
    if($icp_data['subject_residence_permit_media_id']){
      $apply_data["icp_subject"]["personal_info"]["residence_permit"] = $icp_data['subject_residence_permit_media_id'];
    }
//      \think\facade\Log::write([
//          'type'=>'申请备案-提交数据',
//          'data'=>jsonEncode($apply_data)
//      ]);
    $url = "https://api.weixin.qq.com/wxa/icp/apply_icp_filing?access_token=".self::access_token($aid);
    $response = curl_post($url, jsonEncode($apply_data));
    $res_data = json_decode($response, true);
//      \think\facade\Log::write([
//          'type'=>'申请备案-返回数据',
//          'data'=>jsonEncode($res_data)
//      ]);
    if($res_data['errcode'] =='0'){
      return ['status'=>1, 'msg'=>'提交成功，等待审核'];
    }else{
      $hints = $res_data['hints']??[];
      $img2text = [
        "icp_subject.principal_info.certificate_photo_front"=>"主体负责人证件正面照片",
        "icp_subject.principal_info.certificate_photo_back"=>"主体负责人证件背面照片",
        "icp_subject.principal_info.authorization_letter"=>"授权书",
        "icp_applets.base_info.nrlx_details"=>"前置审批文件",
        "icp_applets.principal_info.certificate_photo_front"=>"小程序负责人证件正面照片",
        "icp_applets.principal_info.certificate_photo_back"=>"小程序负责人证件背面照片",
      ];
      foreach ($hints as &$hint) {
        if(isset($img2text[$hint['err_field']])){
          $hint['errmsg'] = "【{$img2text[$hint['err_field']]}】".$hint['errmsg'];
        }
      }
      return ['status'=>0, 'msg'=>$res_data['errmsg'], 'hints'=>$hints];
    }
  }

  //发起人脸核身
  // https://developers.weixin.qq.com/doc/oplatform/openApi/OpenApiDoc/miniprogram-management/record/createIcpVerifyTask.html
  public static function launchCheck($aid)
  {
    //{"task_id":"MTI4OTE0MzQ4M180MzQ1MDcyMjY=","errcode":0,"errmsg":"ok"}
    $access_token = self::access_token($aid);
    $url = "https://api.weixin.qq.com/wxa/icp/create_icp_verifytask?access_token={$access_token}";
    $response = curl_post($url);
    $data = json_decode($response, true);
    if($data['errcode'] != 0){
      return ['status'=>0, 'msg'=>$data['errmsg']];
    }else{
      return ['status'=>1, 'msg'=>'', 'task_id'=>$data['task_id']];
    }
  }

  //查询人脸核身状态
  // https://developers.weixin.qq.com/doc/oplatform/openApi/OpenApiDoc/miniprogram-management/record/queryIcpVerifyTask.html
  public static function checkFace($aid, $task_id)
  {
    $access_token = self::access_token($aid);
    $url = "https://api.weixin.qq.com/wxa/icp/query_icp_verifytask?access_token={$access_token}";
    $response = curl_post($url,json_encode(['task_id'=>$task_id]));
    $data = json_decode($response, true);
    if($data['errcode'] != 0){
      return ['status'=>0, 'msg'=>$data['errmsg']];
    }else{
      return ['status'=>1, 'msg'=>'', 'data'=>$data];
    }
  }

  public static function getIcpMedia($aid, $media_id)
  {
    $access_token = self::access_token($aid);
    $url = "https://api.weixin.qq.com/wxa/icp/get_icp_media?access_token={$access_token}&media_id={$media_id}";
    $data = curl_get($url);
    return $data;
  }

  //获取小程序URL Scheme
    //https://developers.weixin.qq.com/miniprogram/dev/OpenApiDoc/qrcode-link/url-scheme/generateScheme.html
  public static function getUrlScheme($aid=1,$path='',$query=''){
      $accress_token = self::access_token($aid,'wx',false);
      $url = 'https://api.weixin.qq.com/wxa/generatescheme?access_token='.$accress_token;
      $data = [
          'jump_wxa' => [
              'path' => $path,
              'query' => $query,
              'env_version' => 'release',//默认值"release"。要打开的小程序版本。正式版为"release"，体验版为"trial"，开发版为"develop"，仅在微信外打开时生效。
          ],
          "is_expire" => true,
          "expire_type" => 0,//到期失效的 scheme 码的失效时间，为 Unix 时间戳。生成的到期失效 scheme 码在该时间前有效。最长有效期为30天。is_expire 为 true 且 expire_type 为 0 时必填
          'expire_time' => time()+3600,
      ];
      $json_data = json_encode($data,JSON_UNESCAPED_SLASHES  | JSON_UNESCAPED_UNICODE);
      //dump($json_data);exit;
      $response = curl_post($url, $json_data);
      $res_data = json_decode($response, true);
      if($res_data['errcode'] =='0'){
          return ['status'=>1, 'msg'=>'','url'=>$res_data['openlink']];
      }else{
          \think\facade\Log::write([
              'type'=>'getUrlScheme',
              'file'=>__FILE__,
              'line'=>__LINE__,
              'fun'=>__FUNCTION__,
              'detail'=>$response
          ]);
          return ['status'=>0, 'msg'=>$res_data['errmsg']];
      }
  }


    // 小程序文本内容安全识别2.0版本 
    public static function checkMessageSafe($message='',$openid='',$aid=0){
	  	if(!empty($message) && !empty($openid)){

			$url = 'https://api.weixin.qq.com/wxa/msg_sec_check?access_token='.self::access_token($aid);
			// 设置要发送的数据
			$data = [
			    "openid" => $openid,
			    "scene" => 1,
			    "version" => 2,
			    "content" => $message
			];
			// 将数据转换为JSON格式
			$json_data = json_encode($data,JSON_UNESCAPED_UNICODE);
			$res = curl_post($url,$json_data);
			$result = json_decode($res, true);

			return $result;
		}

    }
    // 小程序文本内容安全识别1.0版本 无需openid
    public static function checkMessageSafe2($message='',$openid='',$aid=0){
	  	if(!empty($message)){
			$url = 'https://api.weixin.qq.com/wxa/msg_sec_check?access_token='.self::access_token($aid);
			$data = [ 
			  'content' => $message,//'待识别的文本内容，文本字数的上限为2500字，需要使用UTF-8编码',
			 ]; // 发起 POST 请求 
			$res = curl_post($url,json_encode($data));
	        $result = json_decode($res, true); 
			// file_put_contents(ROOT_PATH.'runtime/wxxcx.log',date('Y-m-d H:i:s').'::'.$response."\r\n",FILE_APPEND);
			return $result;
		}
    }

    // 小程序音视频内容安全识别
    public static function checkImageSafe($image='',$openid='',$aid=0){
	  	if(!empty($image) && !empty($openid)){
	  		$url = 'https://api.weixin.qq.com/wxa/media_check_async?access_token='.self::access_token($aid);
			$data = [
				'openid'=>$openid,
				'scene'=>1,
				'version'=>2,
				'media_url'=>$image,
				'media_type'=>2
			];

			$res = curl_post($url,json_encode($data));
			$res = json_decode($res,true);
			return $res;
		}
    }
}
