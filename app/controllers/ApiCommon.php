<?php


// +----------------------------------------------------------------------
// | 公共接口
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\Db;
class ApiCommon extends ApiBase
{
	public $sysset;
    public $score_weishu = 0;
    public function initialize(){
		parent::initialize();
		if(input('param.pid')){
			$fromid = input('param.pid/d');
		}

		//后台查看h5管理端
		$uid = input('param.uid/d');
		if($uid && session("?ADMIN_LOGIN") && (session('ADMIN_UID') == $uid || session('ADMIN_AUTH_UID') == $uid)){
			$user = Db::name('admin_user')->where('id',$uid)->find();
			if(!$user) die(jsonEncode(['status'=>0,'msg'=>'管理员不存在']));
			$member = Db::name('member')->where('aid',aid)->where('id',$user['mid'])->find();
			if(!$member) die(jsonEncode(['status'=>0,'msg'=>'管理员绑定的会员不存在']));
			$mid = $member['id'];
			$this->sessionid = \think\facade\Session::getId().$uid;
			cache($this->sessionid.'_mid',$mid,7*86400);
			Db::name('session')->where('aid',aid)->where('session_id',$this->sessionid)->delete();
			$session = [
				'session_id' => $this->sessionid,
				'aid' => aid,
				'mid' => $mid,
				'user_agent' => $_SERVER['HTTP_USER_AGENT'],
				'login_time' => time(),
				'login_ip' => request()->ip(),
				'platform' => platform
			];
			Db::name('session')->insert($session);
		}else{
			if(input('param.session_id') && input('param.session_id') != 'undefined' && input('param.session_id') != 'null' && !empty(input('param.session_id'))){
				$session = Db::name('session')->where('aid',aid)->where('session_id',input('param.session_id'))->find();
				if(empty($session)) {
					$this->sessionid = input('param.session_id');//\think\facade\Session::getId();
					$mid = cache($this->sessionid.'_mid');
					$session = [
						'session_id' => $this->sessionid,
						'aid' => aid,
						'mid' => $mid,
						'user_agent' => $_SERVER['HTTP_USER_AGENT'],
						'login_time' => time(),
						'login_ip' => request()->ip(),
						'platform' => platform
					];
					Db::name('session')->insert($session);
				} else {
					$this->sessionid = input('param.session_id');
				}
                \think\facade\Session::setId($this->sessionid);
			}else{
				$this->sessionid = \think\facade\Session::getId();
				$mid = cache($this->sessionid.'_mid');
				$session = [
					'session_id' => $this->sessionid,
					'aid' => aid,
					'mid' => $mid,
					'user_agent' => $_SERVER['HTTP_USER_AGENT'],
					'login_time' => time(),
					'login_ip' => request()->ip(),
					'platform' => platform
				];
				Db::name('session')->insert($session);
			}
			$mid = cache($this->sessionid.'_mid');

			$member = [];
			if($mid){
				$member = Db::name('member')->where('aid',aid)->where('id',$mid)->find();
				//$session = Db::name('session')->where('aid',aid)->where('mid',$mid)->order('login_time desc')->find();
				//if($member && $session['session_id']) $this->sessionid = $session['session_id'];
			}elseif($this->sessionid){
				//$session = Db::name('session')->where('aid',aid)->where('session_id',$this->sessionid)->order('login_time desc')->find();
				//if($session['mid']) $member = Db::name('member')->where('aid',aid)->where('id',$session['mid'])->find();
			}
		}
		if(!$member){
			define('mid',0);
		}else{
			define('mid',$member['id']);
			if($fromid && $fromid!=mid && $fromid!=$member['pid']){
				$upuser = Db::name('member')->where('id',$fromid)->find();
                if($upuser['pid'] != mid){
                    $uplv = Db::name('member_level')->where('aid',aid)->where('id',$upuser['levelid'])->find();
                    if($upuser && $uplv['can_agent']!=0 && $uplv['agent_rule']>0){
                        if(!$member['pid'] || $uplv['agent_rule']==2){
                            //不绑定推荐关系：即使该会员有推荐人，当被另一个人推荐时，TA的推荐人就会变成后来推荐TA的人。
                            $rs = \app\models\Member::edit(aid,['id'=>mid,'pid'=>$fromid]);
                            $member['pid'] = $fromid;
                            if($rs['status'] == 1)
                                \app\commons\Common::user_tjscore(aid,mid);
                        }elseif($uplv['agent_rule']==3){ //首次消费后绑定推荐关系
                            $haspayorder = Db::name('payorder')->where('mid',mid)->where('money','>',0)->where('status',1)->find();
                            if(!$haspayorder){
                                \app\models\Member::edit(aid,['id'=>mid,'pid'=>$fromid]);
                                $member['pid'] = $fromid;
                                //\app\commons\Common::user_tjscore(aid,mid);
                            }
                        }
                    }
                }
			}
            $member['money'] = \app\commons\Member::getmoney($member);
            $member['score'] = \app\commons\Member::getscore($member);
		}
		$this->mid = mid;
		$this->member = $member;
		if($this->sessionid && !$mid){
			cache($this->sessionid.'_mid',mid,7*86400);
		}
		
		$this->sysset = Db::name('admin_set')->where('aid',aid)->find();
		$gettj = explode(',',$this->sysset['gettj']);
		if(!in_array('-1',$gettj)){ //不是所有人
			$request_action = request()->controller().'/'.request()->action();
			if($request_action=='ApiIndex/index' || !in_array(request()->controller(),['ApiIndex','ApiImageupload','ApiMaidan'])){
				$this->checklogin();
				if(!in_array($this->member['levelid'],$gettj)){
					echojson(['status'=>-4,'msg'=>$this->sysset['gettjtip']]);
				}
			}
		}
		$this->score_weishu = 0;
        //设置了进入系统必须先登录
		if($this->sysset['login_mast'] && in_array(platform,explode(',',$this->sysset['login_mast']))){
			if(platform == 'wx' || platform == 'mp'){
				$seetype = input('param.seetype')?input('param.seetype'):'';
			}else{
				$seetype = '';
			}

            //场景值
            $scene = input('param.scene')?input('param.scene'):'';
            if($scene && $scene == 1154){
                $seetype = 'circle';
            }

			if(!$seetype || empty($seetype) || $seetype !='circle' ){
				$request_action = request()->controller().'/'.request()->action();
				if($request_action=='ApiIndex/index' || !in_array(request()->controller(),['ApiIndex','ApiImageupload','ApiMaidan'])){
					$this->checklogin();
				}
			}
		}

        }
	//判断登录
	public function checklogin($authlogin = 0,$params=[]){
        if(!$this->member){
			//是否直接用授权登录
//			$authlogin = 0;
			$logintype = $this->sysset['logintype_'.platform];
			if($logintype == 3){
				$xieyi = Db::name('admin_set_xieyi')->where('aid',aid)->find();
				if(!$xieyi || $xieyi['status'] == 0){
					$authlogin = 1;
				}
			}
			return $this->json(['status'=>-1,'msg'=>'请先登录','authlogin'=>$authlogin,'data'=>$params],1);
		}elseif($this->member['checkst'] == 0){
			echojson(['status'=>-4,'msg'=>'账号审核中']);
		}elseif($this->member['checkst'] == 2){
			echojson(['status'=>-4,'msg'=>'账号审核未通过,驳回原因：'.$this->member['checkreason'],'url'=>'/pages/index/reg']);
		}elseif($this->member['isfreeze'] == 1){
			echojson(['status'=>-4,'msg'=>'账号已冻结']);
		}
		//更新访问时间
        Db::name('member')->where('aid',aid)->where('id',mid)->update(['last_visittime'=>time()]);
	}

    /**
     * @param array $data
     * @param array $data['status'] -1跳转登录页,-2公众号或h5跳转,-3跳转到指定页,-4弹窗提示并跳转到指定页,-5弹窗提示并跳转上一页-10跳转管理员登录
     * @param $isexit
     * @return \think\response\Json
     */
	protected function json(array $data,$isexit=0){
		if(input('param.needinit') == 1){
			$platform = platform;
			$menuset = Db::name('designer_menu')->where('aid',aid)->where('platform',$platform)->find();
			$menudata = json_decode($menuset['menudata'],true);
			$menulist = array();
			foreach($menudata['list'] as $k=>$v){
				if($k < $menuset['menucount']){
					$menulist[] = $v;
				}
			}
			$menudata['list'] = $menulist;
			$indexurl = $menuset['indexurl'];
			
			$menu2datalist = [];
			//多商户默认导航
			$menubusinessset = Db::name('designer_menu_business')->where('aid',aid)->where('platform',$platform)->find();
			if($menubusinessset){
				$menubusinessdata = json_decode($menubusinessset['menudata'],true);
				$menubusinesslist = array();
				foreach($menubusinessdata['list'] as $k=>$v){
					if($k < $menubusinessset['menucount']){
						$menu2data = [];
						$menu2data['color'] = $menubusinessdata['color'];
						$menu2data['selectedColor'] = $menubusinessdata['selectedColor'];
						$menu2data['backgroundColor'] = $menubusinessdata['backgroundColor'];
						$menu2data['indexurl'] = $v['pagePath'];
						$menu2data['list'] = $menubusinessdata['list'];
						$menulist = [];
						foreach($menu2data['list'] as $k2=>$v2){
							if($k2 < $menubusinessset['menucount']){
								$menulist[] = $v2;
							}
						}
						$menu2data['list'] = $menulist;
						$menu2datalist[] = $menu2data;
					}
				}
			}

			//内页菜单
			$menu2list = Db::name('designer_menu2')->where('aid',aid)->where('status',1)->where('platform','in',['all',$platform])->order('id desc')->select()->toArray();
			if($menu2list){
				foreach($menu2list as $k=>$v){
					$menu2data = [];
					$menu2data['backgroundColor'] = $v['backgroundColor'];
					$menu2data['indexurl'] = $v['indexurl'];
					$menu2data['list'] = json_decode($v['menudata'],true);
					$menulist = [];
					foreach($menu2data['list'] as $k2=>$v2){
						if($k2 < $v['menucount']){
							$menulist[] = $v2;
						}
					}
					$menu2data['list'] = $menulist;
					$menu2datalist[] = $menu2data;
				}
			}


			$sysset = $this->sysset;
			$textset = json_decode($sysset['textset'],true);
			if(!$textset) $textset = [];

			//分享设置
			$sharelist = Db::name('designer_share')->field('title,desc,pic,url,indexurl,is_rootpath')->where('aid',aid)->where('status',1)->where('platform','in',['all',$platform])->select()->toArray();
			if(!$sharelist) $sharelist = [];
			if($sharelist){
			    //老页面链接替换，否则匹配不到分享设置内容
			    $old_pages = [
			        'commission','sign','business','lipin','order','coupon','my'
                ];
			    foreach($sharelist as $k=>$v){
			        $path_arr = explode('/',$v['indexurl']);
			        if($path_arr[1]=='pages' && in_array($path_arr[2],$old_pages)){
                        if(empty($path_arr[3]) || $path_arr[3]!='usercenter'){
                            $sharelist[$k]['indexurl'] = str_replace('/pages','/pagesExt',$v['indexurl']);
                        }
                    }
                }
            }

			if($platform == 'wx'){
				if($sysset['wxkf'] == 1){
					$sysset['kfurl'] = 'contact::';
				}else{
					$sysset['kfurl'] = $sysset['wxkfurl'];
				}
			}
			if(!$sysset['kfurl']) $sysset['kfurl'] = '';
			$logintype = $sysset['logintype_'.$platform];
			if(!$logintype){
				$logintype = [];
			}else{
				$logintype = explode(',',$logintype);
			}
            if(!getcustom('hide_home_button')){
                //默认不隐藏小程序顶部按钮
                $sysset['hide_home_button'] = 0;
            }
            $copyinfo = '';
			$data['_initdata'] = [
				'name'=>$sysset['name'],
				'logo'=>$sysset['logo'],
				'desc'=>$sysset['desc'],
				'wxkf'=>$sysset['wxkf'],
				'corpid'=>$sysset['corpid'],
				'kfurl'=>$sysset['kfurl'],
				'mid'=>$this->mid,
				'pre_url'=>PRE_URL,
				'session_id'=>$this->sessionid,
				'platform'=>platform,
				'indexurl'=>$indexurl,
				'menudata'=>$menudata,
				'menu2data'=>$menu2datalist,
				'sharelist'=>$sharelist,
				'textset'=>$textset,
				'color1'=>$sysset['color1'],
				'color2'=>$sysset['color2'],
				'color1rgb'=>hex2rgb($sysset['color1']),
				'color2rgb'=>hex2rgb($sysset['color2']),
				'logintype'=>$logintype,
				'isdouyin'=>isdouyin,
				'login_to_reg'=>$sysset['login_to_reg']??0,
				
				
				
                'loading_style'=>$sysset['loading_style']??'',
                'loading_icon'=>$sysset['loading_icon']??'',
                'encryptparamtype'=>0,//0 无参 1明文，2加密
                'encryptparam'=>'',
                'hide_home_button' => $sysset['hide_home_button'],
                'copyinfo'=>$copyinfo,
			];
            //如果需要外链跳转携带系统参数
            if(platform == 'mp'){
				$share_package = \app\commons\Wechat::share_package(aid);
				$data['share_package'] = $share_package;
			}
		}
		if((input('param.needinit') == 1 && $this->mid) || $data['mid']){
			if($data['mid']) $this->mid = $data['mid'];
			$config = include(ROOT_PATH.'config.php');
			$authtoken = $config['authtoken'];
			$token = md5(md5($authtoken.$this->mid));
			$data['socket_token'] = Db::name('member')->where('id',$this->mid)->value('random_str');
		}
		if($isexit == 1){
			echojson($data);
		}
		return json($data);
	}
    //解密
    public function getBase64DecodeParam($key,$str){
        return openssl_decrypt(base64_decode($str),'AES-128-ECB',$key,OPENSSL_RAW_DATA);
    }
    //base64加密
    public function getBase64EncodeParam($key,$data=[]){
        if (empty($data)) {
            return '';
        }
        ksort($data);
        $str = '';
        foreach ($data as $k => $v) {
            if($k && $v){
                $str.='&'.$k.'='.$v;
            }
        }
        $str = trim($str,'&');
        return base64_encode(openssl_encrypt($str,'AES-128-ECB',$key,OPENSSL_RAW_DATA));
    }

	//收藏
	public function addfavorite(){
		$this->checklogin();
		$post = input('post.');
		$rs = Db::name('member_favorite')->where('aid',aid)->where('mid',mid)->where('proid',$post['proid'])->where('type',$post['type'])->find();
		if($rs){
			Db::name('member_favorite')->where('aid',aid)->where('mid',mid)->where('proid',$post['proid'])->where('type',$post['type'])->delete();
			return json(['status'=>1,'msg'=>'已取消','url'=>true]);
		}else{
			Db::name('member_favorite')->insert(['aid'=>aid,'mid'=>mid,'proid'=>$post['proid'],'type'=>$post['type'],'createtime'=>time()]);
			return json(['status'=>1,'msg'=>'已收藏','url'=>true]);
		}
	}

	//生成海报
	public function _getposter($aid,$bid,$platform,$posterdata,$page,$scene,$textReplaceArr){
		set_time_limit(0);
		$posterdata = json_decode($posterdata,true);
		$poster_bg = $posterdata['poster_bg'];
		$poster_data = $posterdata['poster_data'];
		@ini_set('memory_limit', -1);

		if(strpos($poster_bg,'http') ===false){
			$poster_bg = PRE_URL.$poster_bg;
		}
		$bg = imagecreatefromstring(request_get($poster_bg));
		if($bg){
			$bgwidth = imagesx($bg);
			$bgheight = imagesy($bg);
			if($bgheight/$bgwidth > 1.92) $bgheight = floor($bgwidth * 1.92);
			$target = imagecreatetruecolor($bgwidth, $bgheight);
			imagecopy($target, $bg, 0, 0, 0, 0,$bgwidth,$bgheight);
			imagedestroy($bg);
		}else{
			$bgwidth = 680;
			$bgheight = 1080;
			$target = imagecreatetruecolor(680, 1080);
			imagefill($target,0,0,imagecolorallocate($target, 255, 255, 255));
		}
		$huansuan = $bgwidth/340;
		//$bgwidth = imagesx($bg);
		//$bgheight = imagesy($bg);


		$font = ROOT_PATH."static/fonts/msyh.ttf";
		foreach ($poster_data as $d){

			$d['left'] = intval(str_replace('px', '', $d['left'])) * $huansuan;
			$d['top'] = intval(str_replace('px', '', $d['top'])) * $huansuan;
			$d['width'] = intval(str_replace('px', '', $d['width'])) * $huansuan;
			$d['height'] = intval(str_replace('px', '', $d['height'])) * $huansuan;
			$d['size'] = intval(str_replace('px', '', $d['size'])) * $huansuan/2*1.5;
			if ($d['type'] == 'qrwx'){
                $data = array();
                $data['scene'] = $scene;
                $data['page'] = ltrim($page,'/');
                $data['width'] = 430;//默认430，二维码的宽度，单位 px，最小 280px，最大 1280px
                $errmsg = \app\commons\Wechat::getQRCode($aid,'wx',$data['page'],$scene,$bid,false);
                $res = $errmsg['buffer'];//图片 Buffer
				if($errmsg['status'] != 1){
					if($errmsg['errcode'] == 41030){
						echojson(array('status'=>0,'msg'=>'小程序发布后才能生成分享海报'));
					}else{
						echojson(['status'=>0,'msg'=>$errmsg['msg'],'rs'=>$errmsg['rs'],'data'=>$data]);
					}
				}else{
					$img = imagecreatefromstring($res);
					imagecopyresampled($target, $img, $d['left'], $d['top'], 0, 0, $d['width'], $d['height'],imagesx($img), imagesy($img));
				}
			} else if ($d['type'] == 'qrmp') {
				$qrcode = createqrcode(PRE_URL .'/h5/'.$aid.'.html#'.$page.'?scene='.$scene.'&t='.time());
				$img = imagecreatefromstring(request_get($qrcode));
				imagecopyresampled($target, $img, $d['left'], $d['top'], 0, 0, $d['width'], $d['height'],imagesx($img), imagesy($img));
			}else if($d['type'] == 'qrgz'){
                $rs = \app\commons\Wechat::getQRCode($aid,'mp',$page,$scene,$bid);
				if($rs['url']){
					$qrcode = $rs['url'];
					$img = imagecreatefromstring(request_get($qrcode));
					imagecopyresampled($target, $img, $d['left'], $d['top'], 0, 0, $d['width'], $d['height'],imagesx($img), imagesy($img));
				}else{
					echojson($rs);
				}
			}else if ($d['type'] == 'img') {
				if($d['src'][0] == '/') $d['src'] = PRE_URL.$d['src'];
				$img = imagecreatefromstring(request_get($d['src']));
				if($img)
				imagecopyresampled($target, $img, $d['left'], $d['top'], 0, 0, $d['width'], $d['height'],imagesx($img), imagesy($img));
			} else if ($d['type'] == 'text') {
				$d['content'] = str_replace(array_keys($textReplaceArr),array_values($textReplaceArr),$d['content']);
				$colors = hex2rgb($d['color']);
				$color = imagecolorallocate($target, $colors['red'], $colors['green'], $colors['blue']);
				imagettftext($target, $d['size'], 0, $d['left'], $d['top'] + $d['size'], $color, $font,  $d['content']);
			} else if ($d['type'] == 'textarea') {
				$d['content'] = str_replace(array_keys($textReplaceArr),array_values($textReplaceArr),$d['content']);
				$colors = hex2rgb($d['color']);
				$color = imagecolorallocate($target, $colors['red'], $colors['green'], $colors['blue']);
				$string = $d['content'];
				$_string='';
				$__string='';
				$_height = 0;
				mb_internal_encoding("UTF-8"); // 设置编码
				for($i=0;$i<mb_strlen($string);$i++){
					$box = imagettfbbox($d['size'],0,$font,$_string);
					$_string_length = $box[2]-$box[0];
					$box = imagettfbbox($d['size'],0,$font,mb_substr($string,$i,1));
					if($_string_length+$box[2]-$box[0]<$d['width']*1){
						$_string.=mb_substr($string,$i,1);
					}else{
						$_height += $box[1]-$box[7]+4;
						//var_dump($_height.'--'.$d['height']);
						if($_height >= $d['height']*1){
							break;
						}
						$__string.=$_string."\n";
						$_string=mb_substr($string,$i,1);
					}
				}
				$__string.=$_string; 
				$box=imagettfbbox($d['size'],0,$font,mb_substr($__string,0,1));
				imagettftext($target,$d['size'],0,$d['left'],$d['top']+($box[3]-$box[7]),$color,$font,$__string);

			} else if ($d['type'] == 'pro_img1' ||$d['type'] == 'pro_img2' ||$d['type'] == 'pro_img3' ||$d['type'] == 'pro_img' || $d['type'] == 'business_img') {
			    
			    if ($d['type'] == 'pro_img1' || $d['type'] == 'pro_img2' || $d['type'] == 'pro_img3') {
			        if ($textReplaceArr['['.$d['type'].']']) {
			          	$imgname = $textReplaceArr['['.$d['type'].']'];
			          	$img = imagecreatefromstring(request_get($imgname));
        				if($img)
        				imagecopyresampled($target, $img, $d['left'], $d['top'], 0, 0, $d['width'], $d['height'],imagesx($img), imagesy($img));
			        }
			    } else {
			       	if($d['type'] == 'pro_img'){
    					$imgname = $textReplaceArr['[商品图片]'];
    				}else{
    					$imgname = $textReplaceArr['[商户图片]'];
    				}
    				$img = imagecreatefromstring(request_get($imgname));
    				if($img)
    				imagecopyresampled($target, $img, $d['left'], $d['top'], 0, 0, $d['width'], $d['height'],imagesx($img), imagesy($img));
    				
			    }
			    
			    
			    
			
				
				
				
				
				
			} else if ($d['type'] == 'shadow') {
				$rgba = explode(',',str_replace(array(' ','(',')','rgba'),'',$d['shadow']));
				//dump($rgba);
				$black = imagecreatetruecolor($d['width'], $d['height']);
				imagealphablending($black, false);
				imagesavealpha($black, true);
				$blackcolor = imagecolorallocatealpha($black,$rgba[0],$rgba[1],$rgba[2],(1-$rgba[3])*127);
				imagefill($black, 0, 0, $blackcolor);
				imagecopy($target, $black, $d['left'], $d['top'], 0, 0, $d['width'], $d['height']);
				imagedestroy($black);
			} else if($d['type'] == 'head' || $d['type'] == 'business_logo' ) {
				if($d['type'] == 'head'){
					$imgname = $textReplaceArr['[头像]'];
				}else{
					$imgname = $textReplaceArr['[商户LOGO]'];
				}
				$src_img = imagecreatefromstring(request_get($imgname));
				if($src_img){
					$w = imagesx($src_img);
					$h = imagesy($src_img);
					$radius = $d['radius']*0.01*$w/2;
					if($radius > 0){
						$img = imagecreatetruecolor($w, $h);
						//这一句一定要有
						imagesavealpha($img, true);
						//拾取一个完全透明的颜色,最后一个参数127为全透明
						$bg = imagecolorallocatealpha($img, 255, 255, 255, 127);
						imagefill($img, 0, 0, $bg);
						$r = $radius; //圆 角半径
						for ($x = 0; $x < $w; $x++) {
							for ($y = 0; $y < $h; $y++) {
								$rgbColor = imagecolorat($src_img, $x, $y);
								if (($x >= $radius && $x <= ($w - $radius)) || ($y >= $radius && $y <= ($h - $radius))) {
									//不在四角的范围内,直接画
									imagesetpixel($img, $x, $y, $rgbColor);
								} else {
									//在四角的范围内选择画
									//上左
									$y_x = $r; //圆心X坐标
									$y_y = $r; //圆心Y坐标
									if (((($x - $y_x) * ($x - $y_x) + ($y - $y_y) * ($y - $y_y)) <= ($r * $r))) {
										imagesetpixel($img, $x, $y, $rgbColor);
									}
									//上右
									$y_x = $w - $r; //圆心X坐标
									$y_y = $r; //圆心Y坐标
									if (((($x - $y_x) * ($x - $y_x) + ($y - $y_y) * ($y - $y_y)) <= ($r * $r))) {
										imagesetpixel($img, $x, $y, $rgbColor);
									}
									//下左
									$y_x = $r; //圆心X坐标
									$y_y = $h - $r; //圆心Y坐标
									if (((($x - $y_x) * ($x - $y_x) + ($y - $y_y) * ($y - $y_y)) <= ($r * $r))) {
										imagesetpixel($img, $x, $y, $rgbColor);
									}
									//下右
									$y_x = $w - $r; //圆心X坐标
									$y_y = $h - $r; //圆心Y坐标
									if (((($x - $y_x) * ($x - $y_x) + ($y - $y_y) * ($y - $y_y)) <= ($r * $r))) {
										imagesetpixel($img, $x, $y, $rgbColor);
									}
								}
							}
						}
						imagecopyresampled($target, $img, $d['left'], $d['top'], 0, 0, $d['width'], $d['height'],imagesx($img), imagesy($img));
					}else{
						imagecopyresampled($target, $src_img, $d['left'], $d['top'], 0, 0, $d['width'], $d['height'],imagesx($src_img), imagesy($src_img));
					}
				}
			} else if ($d['type'] == 'reginvitecode') {
				$d['content'] = str_replace(array_keys($textReplaceArr),array_values($textReplaceArr),$d['content']);
				$colors = hex2rgb($d['color']);
				$color = imagecolorallocate($target, $colors['red'], $colors['green'], $colors['blue']);
				imagettftext($target, $d['size'], 0, $d['left'], $d['top'] + $d['size'], $color, $font,  $d['content']);
			}
		}
		$url = "/upload/{$aid}/".date('Ym/d_His').rand(1000,9999).'.jpg';
		$filepath = ROOT_PATH.ltrim($url,'/');
		mk_dir(dirname($filepath));
		imagejpeg($target,$filepath,100);
		return PRE_URL.$url;
	}
	
	//商品列表数据会员价处理
	public function formatprolist($datalist){
        foreach($datalist as $k=>$v){
            if($this->member) {
                $datalist[$k]['sell_price_origin'] = $v['sell_price'] ? $v['sell_price'] : 0;
                if ($v['lvprice'] == 1) {
                    $lvprice_data = json_decode($v['lvprice_data'], true);
                    if ($lvprice_data && isset($lvprice_data[$this->member['levelid']])) {
                        $datalist[$k]['sell_price'] = $lvprice_data[$this->member['levelid']];
                        }
                } else {
                    }
                }
            $fwnames = [];
            if($v['fwid']){
                $fwid = explode(',',$v['fwid']);
                $fwnames = Db::name('shop_fuwu')->where('aid',aid)->whereIn('id',$fwid)->column('name');
                if(empty($fwnames)) $fwnames = [];
            }
            $datalist[$k]['fwlist'] = $fwnames;
        }
		$shopset = Db::name('shop_sysset')->where('aid',aid)->find();
        $price_tag = $cost_tag = '￥';
        $price_color = $cost_color = '';
        $show_sellprice = true;
        $show_cost = false;
        $hidecart = false;
        if(getcustom('product_cost_show') || getcustom('product_sellprice_show') || getcustom('product_list_nocart')){
            if(isset($shopset['hide_sellprice']) && $shopset['hide_sellprice']==1){
                $show_sellprice = false;
            }
            if(isset($shopset['hide_cost']) && $shopset['hide_cost']==0){
                $show_cost = true;
            }
            if($shopset['sellprice_name']){
                $price_tag = $shopset['sellprice_name'];
            }
            if($shopset['sellprice_color']){
                $price_color = $shopset['sellprice_color'];
            }
            if($shopset['cost_name']){
                $cost_tag = $shopset['cost_name'];
            }
            if($shopset['sellprice_color']){
                $cost_color = $shopset['cost_color'];
            }
            }
		if($shopset){
			foreach($datalist as $k=>$v){
				if($shopset['hide_sales']==1){
					$datalist[$k]['sales'] = 0;
				}
                $datalist[$k]['price_tag'] = $price_tag;
                $datalist[$k]['cost_tag'] = $cost_tag;
                $datalist[$k]['price_color'] = $price_color;
                $datalist[$k]['cost_color'] = $cost_color;
                $datalist[$k]['show_sellprice'] = $show_sellprice;
                $datalist[$k]['show_cost'] = $show_cost;
                $datalist[$k]['hide_cart'] = $hidecart;
                if($v['price_type']==1){
                    $datalist[$k]['xunjia_type'] = 0;//系统默认
                    }
			}
		}
		return $datalist;
	}
	//商品数据会员价处理
	public function formatproduct($product){
        $product['sell_price_origin'] = $product['sell_price'];
		if(!$this->member) return $product;
		if($product['lvprice']==1){
			$lvprice_data = json_decode($product['lvprice_data'],true);
			if($lvprice_data && isset($lvprice_data[$this->member['levelid']])){
				$product['sell_price'] = $lvprice_data[$this->member['levelid']];
                }
		}else{
            }
        return $product;
	}
    //商品数据会员价处理
    public function formatScoreProduct($product){
        if(!$this->member) return $product;
        if($product['lvprice']==1){
            $lvprice_data = json_decode($product['lvprice_data'],true);
            if($lvprice_data && isset($lvprice_data[$this->member['levelid']])){
                if(isset($lvprice_data[$this->member['levelid']]['money']))
                    $product['money_price'] = $lvprice_data[$this->member['levelid']]['money'];
                if(isset($lvprice_data[$this->member['levelid']]['score']))
                    $product['score_price'] = $lvprice_data[$this->member['levelid']]['score'];
            }
        }
        return $product;
    }
	//商品规格列表数据会员价处理
	public function formatgglist($gglist, $bid = 0, $lvprice = 0,$product_type=0){
		if(!$this->member && $product_type!=2) return $gglist;
		foreach($gglist as $k=>$v){
            $gglist[$k]['sell_price_origin'] = $v['sell_price'];
			$lvprice_data = json_decode($v['lvprice_data'],true);
            if($this->member){
                if($lvprice && $lvprice_data && isset($lvprice_data[$this->member['levelid']])){
                    if($bid && getcustom('plug_businessqr') && $lvprice_data[$this->member['levelid']] == 0) {
                        $show_business = Db::name('member_level')->where('id', $this->member['levelid'])->value('show_business');
                        if($show_business) {
                            $gglist[$k]['sell_price'] = $lvprice_data[$this->member['levelid']];
                        }
                    } else {
                        $gglist[$k]['sell_price'] = $lvprice_data[$this->member['levelid']];
                    }
                }else{
                    
                
                }
                if(getcustom('member_product_price')){
                    //一客一价,存在用户时，存在设置的专享商品时
                    $member_product = Db::name('member_product')->where('aid',aid)->where('mid',mid)->where('proid',$v['proid'])->where('ggid',$v['id'])->find();
                    if($member_product){
                        $gglist[$k]['sell_price'] = $member_product['sell_price'];
                        $gglist[$k]['givescore'] = $member_product['givescore'];
                    }
                }
            }
        }
		return $gglist;
	}
	public function formatguige($guige, $bid = 0, $lvprice = 0){
		if(!$this->member) return $guige;
		$lvprice_data = json_decode($guige['lvprice_data'],true);
		if($lvprice && $lvprice_data && isset($lvprice_data[$this->member['levelid']])){
            if($bid && getcustom('plug_businessqr') && $lvprice_data[$this->member['levelid']] == 0) {
                $show_business = Db::name('member_level')->where('id', $this->member['levelid'])->value('show_business');
                if($show_business) {
                    $guige['sell_price'] = $lvprice_data[$this->member['levelid']];
                }
            } else {
                $guige['sell_price'] = $lvprice_data[$this->member['levelid']];
            }
        }else{
            
        }
        if(getcustom('product_service_fee')){
            $service_fee_data = json_decode($guige['service_fee_data'],true);
            if($lvprice && $service_fee_data && isset($service_fee_data[$this->member['levelid']])){
                $guige['service_fee'] = $service_fee_data[$this->member['levelid']];
            }
        }
        if(getcustom('member_product_price')){
            //一客一价,存在用户时，存在设置的专享商品时 ,不查询规格的 默认一条
            $member_product = Db::name('member_product')->where('aid',aid)->where('mid',mid)->where('proid',$guige['proid'])->where('ggid',$guige['id'])->find();
            $guige['is_member_product'] = 0;
            if($member_product){
                $guige['sell_price'] = $member_product['sell_price'];
                $guige['givescore'] = $member_product['givescore'];
                $guige['is_member_product'] = 1;
            }
        }
        return $guige;
	}
}