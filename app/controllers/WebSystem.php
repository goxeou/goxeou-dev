<?php


// +----------------------------------------------------------------------
// | 控制台 系统
// +----------------------------------------------------------------------
namespace app\controllers;
use app\commons\File;
use think\facade\View;
use think\facade\Db;

class WebSystem extends WebCommon
{
    public function initialize(){
		parent::initialize();
	}
	//首页框架
    public function index(){
		$menudata = [];
        if(!getcustom('admin_user_hide')){
            $menudata['user'] = array(
                'name'=>'用户列表',
                'path'=>'WebUser/index'
            );
        }
		$menudata['wxpayset'] = array(
			'name'=>'服务商配置',
			'path'=>'WebSystem/wxpayset'
		);
		$menudata['wxpaylog'] = array(
			'name'=>'微信支付记录',
			'path'=>'WebSystem/wxpaylog'
		);
		$menudata['component'] = array(
			'name'=>'开放平台设置',
			'path'=>'WebSystem/component'
		);
		$menudata['sysset'] = array(
			'name'=>'系统设置',
			'path'=>'WebSystem/set'
		);
		$menudata['remote'] = array(
			'name'=>'附件设置',
			'path'=>'WebSystem/remote'
		);
		$menudata['help'] = array(
			'name'=>'帮助中心',
			'path'=>'WebHelp/index'
		);
		$menudata['webmessage'] = array(
			'name'=>'网站留言',
			'path'=>'WebMessage/index'
		);
		$menudata['webnotice'] = array(
			'name'=>'通知公告',
			'path'=>'WebNotice/index'
		);
		//$menudata['Qianyi'] = array(
		//	'name'=>'数据迁移', 
		//	'path'=>'WebQianyi/index'
		//);
		//
		if(getcustom('design_template')){
            $menudata['designerpage'] = array(
				'name'=>'模板库管理',
				'path'=>'DesignerPage/index&type=1&into=1',
			);
			$menudata['designergroup'] = array(
				'name'=>'模板库分类',
				'path'=>'DesignerCategory/index&type=1&into=1',
			);
        }
        $menudata['upgrade'] = array(
			'name'=>'系统升级',
			'path'=>'WebSystem/replacedbstr'
		);
		$webinfo = json_decode(Db::name('sysset')->where('name','webinfo')->value('value'),true);
		$myversion = file_get_contents('version.php');
		View::assign('webinfo',$webinfo);
		View::assign('menudata',$menudata);
		View::assign('myversion',$myversion);
		return View::fetch();
    }
	//服务商配置
	public function wxpayset(){
		$wxpayset = Db::name('sysset')->where('name','wxpayset')->find();
		if(request()->isPost()){
			$postinfo = input('post.info/a');
			$postinfo['apiclient_cert'] = str_replace(PRE_URL.'/','',$postinfo['apiclient_cert']);
			$postinfo['apiclient_key'] = str_replace(PRE_URL.'/','',$postinfo['apiclient_key']);
			
			if(!empty($postinfo['apiclient_cert']) && substr($postinfo['apiclient_cert'], -4) != '.pem'){
				return json(['status'=>0,'msg'=>'PEM证书格式错误']);
			}
			if(!empty($postinfo['apiclient_key']) && substr($postinfo['apiclient_key'], -4) != '.pem'){
				return json(['status'=>0,'msg'=>'证书密钥格式错误']);
			}

			if(!$wxpayset){
				$info = [];
				$info['name'] = 'wxpayset';
				$info['value'] = json_encode($postinfo);
				Db::name('sysset')->insert($info);
			}else{
				 Db::name('sysset')->where('id',$wxpayset['id'])->update(['value'=>json_encode($postinfo)]);
			}
			\app\commons\System::plog('微信支付服务商配置',1);
			return json(['status'=>1,'msg'=>'设置成功','url'=>true]);
		}
		View::assign('info',json_decode($wxpayset['value'],true));
		return View::fetch();
	}
	//随行付服务商
	public function sxpayset(){
		$wxpayset = Db::name('sysset')->where('name','sxpayset')->find();
		if(request()->isPost()){
			$postinfo = input('post.info/a');
			$postinfo['publicKey'] = preg_replace('/\s*/','',$postinfo['publicKey']);
			$postinfo['privateKey'] = preg_replace('/\s*/','',$postinfo['privateKey']);
			if(!$wxpayset){
				$info = [];
				$info['name'] = 'sxpayset';
				$info['value'] = json_encode($postinfo);
				Db::name('sysset')->insert($info);
			}else{
				 Db::name('sysset')->where('id',$wxpayset['id'])->update(['value'=>json_encode($postinfo)]);
			}
			\app\commons\System::plog('随行付服务商配置',1);
			return json(['status'=>1,'msg'=>'设置成功','url'=>true]);
		}
		View::assign('info',json_decode($wxpayset['value'],true));
		return View::fetch();
	}
    //
    public function huifuset(){
        }
	//系统设置
	public function set(){
		if(request()->isPost()){
			if($_SERVER['HTTP_HOST'] == 'v2.diandashop.com'){
				return json(['status'=>0,'msg'=>'演示站无操作权限']);
			}
			$rs = Db::name('sysset')->where('name','webinfo')->find();
            $postinfo = input('post.info/a');
			$info = jsonEncode($postinfo);
			if($rs){
				Db::name('sysset')->where('name','webinfo')->update(['value'=>$info]);
			}else{
				Db::name('sysset')->insert(['name'=>'webinfo','value'=>$info]);
			}
			\app\commons\System::plog('控制台系统设置',1);
            if($postinfo['map_key_qq']){
                $rsh5 = $this->updateH5QQMapKey($postinfo['map_key_qq']);
                if($rsh5){
                    if($rsh5['status'] != 1){
                        return json($rsh5);
                    }
                }
            }

			return json(['status'=>1,'msg'=>'设置成功','url'=>true]);
		}
		$info = Db::name('sysset')->where('name','webinfo')->find();
		if(!$info){
			Db::name('sysset')->insert(['name'=>'webinfo','value'=>'[]']);
			$info = Db::name('sysset')->where('name','webinfo')->find();
		}
		if(strpos(PHP_SAPI,'apache') === 0 || $_SERVER['SERVER_SOFTWARE'] == 'Apache'){
			$servertype = 'apache';
		}else{
			$servertype = 'nginx';
		}
		$webinfo = json_decode($info['value'],true);
		$config = include(ROOT_PATH.'config.php');
		$autourl = request()->domain().'/?s=/ApiAuto/index/key/'.$config['authtoken'];

		$component = Db::name('sysset')->where('name','component')->value('value');
		$component = json_decode($component,true);
		if(!$component) $component = [];

		View::assign('autourl',$autourl);
		View::assign('kfport',$config['kfport']);
		View::assign('info',$webinfo);
		View::assign('servertype',$servertype);
		View::assign('component',$component);
        View::assign('phpversion',phpversion());
		return View::fetch();
	}
	//修改密码
	public function setpwd(){
		if(request()->isPost()){
			$rs = Db::name('admin_user')->where('id',$this->uid)->find();
			if($rs['pwd'] != md5(input('post.oldPassword'))){
				return json(['status'=>0,'msg'=>'当前密码输入错误']);
			}
			Db::name('admin_user')->where('id',$this->uid)->update(['pwd'=>md5(input('post.password'))]);
			\app\commons\System::plog('修改密码',1);
			return json(['status'=>1,'msg'=>'修改成功']);
		}
		return View::fetch();
	}
	//附件设置
	public function remote(){
		if(request()->isPost()){
			$rs = Db::name('sysset')->where('name','remote')->find();
			$info = jsonEncode(input('post.info/a'));
			if($rs){
				Db::name('sysset')->where('name','remote')->update(['value'=>$info]);
			}else{
				Db::name('sysset')->insert(['name'=>'remote','value'=>$info]);
			}
			\app\commons\System::plog('控制台附件设置',1);
			return json(['status'=>1,'msg'=>'设置成功','url'=>true]);
		}

        $config['upload_max_filesize']=ini_get('upload_max_filesize');//允许上传最大文件大小
        $config['post_max_size']=ini_get('post_max_size');//表单允许上传的最大文件大小,不能小于upload_max_filesize
        $config['max_file_uploads']=ini_get('max_file_uploads');//单个请求时，允许上传的最大文件数 ;
		$info = Db::name('sysset')->where(['name'=>'remote'])->find();
		if(!$info){
			Db::name('sysset')->insert(['name'=>'remote','value'=>'[]']);
			$info = Db::name('sysset')->where('name','remote')->find();
		}
		$webinfo = json_decode($info['value'],true);
		View::assign('info',$webinfo);
        View::assign('config',$config);
        View::assign('phpversion',phpversion());
		return View::fetch();
	}
	
	//微信开放平台设置
	public function component(){
		if(request()->isPost()){
			$rs = Db::name('sysset')->where('name','component')->find();
			$postinfo = input('post.info/a');
			$info = jsonEncode($postinfo);
			if($rs){
				if(false){}else{
					Db::name('sysset')->where('name','component')->update(['value'=>$info]);
				}
			}else{
				Db::name('sysset')->insert(['name'=>'component','value'=>$info]);
			}
			$appid = $postinfo['kfappid'];
			$config = include(ROOT_PATH.'config.php');
			$authkey = $config['authkey'];
			if(input('post.op') == 'getloginqr'){
				if(!$appid) return json(['status'=>0,'msg'=>'请填写开发小程序appid']);
				$time = time();
				$token = md5('zxc156wegd5gsd1!!--xx' . $time);
				
				$window = array(
					"navigationBarBackgroundColor"=>'#333333',
					"navigationBarTextStyle"=>'white',
					"navigationBarTitleText"=>'',
					"backgroundColor"=>"#f8f8f8",
					"backgroundTextStyle"=>"dark",
					"enablePullDownRefresh"=>true
				);

				$moduleversion = file_get_contents('version.php');
				$postdata = [];
				$postdata['appid'] = $appid;
				$postdata['verson'] = $moduleversion;
				$postdata['desc'] = '于'.date('Y年m月d日 H:i:s').'上传';
				$postdata['uniacid'] = '0';
				$postdata['domain'] = request()->domain();
				$postdata['window'] = jsonEncode($window);
				$postdata['indexurl'] = 'pages/index/index';
				$postdata['custom'] = jsonEncode(getcustom());

				$url = 'http:///index/index/shop?op=login&aid=0&time='.$time.'&token='.$token.'&authkey='.$authkey.'&appid='.$appid.'&moduleversion='.$moduleversion;
				$rs = curl_post($url,$postdata,0,[],120);
				//dump($rs);
				$rs = json_decode($rs,true);
				return json($rs);
			}
			if(input('post.op') == 'upload'){
				$time = time();
				$token = md5('zxc156wegd5gsd1!!--xx' . $time);
				$moduleversion = file_get_contents(ROOT_PATH.'version.php');
				
				$url = 'http:///index/index/shop?op=upload&aid=0&time='.$time.'&token='.$token.'&authkey='.$authkey.'&appid='.$appid;
				$postdata = []; 
				$postdata['version'] = $moduleversion;
				$postdata['desc'] = '于'.date('Y年m月d日 H:i:s').'上传';
				$rs = curl_post($url,$postdata,0,[],120);
				$rs = json_decode($rs,true);
				if($rs['info']){
					$component_access_token = \app\commons\Wechat::component_access_token();
					$url = 'https://api.weixin.qq.com/wxa/gettemplatedraftlist?access_token='.$component_access_token;
					$rs = request_get($url,[],120);
					$rs = json_decode($rs,true);
					if($rs['errcode']!=0){
						return json(['status'=>1,'msg'=>\app\commons\Wechat::geterror($rs)]);
					}

					$draft_list = $rs['draft_list'];
					$createtimes = array_column($draft_list,'create_time');
					array_multisort($createtimes,SORT_DESC,$draft_list);
					$draft_id = $draft_list[0]['draft_id'];
					
					//$draft_data = end($rs['draft_list']);
					//$draft_id = $draft_data['draft_id'];
					
					$url = 'https://api.weixin.qq.com/wxa/addtotemplate?access_token='.$component_access_token;
					$data = array('draft_id'=>$draft_id);
					$rs = request_post($url,jsonEncode($data),120);
					$rs = json_decode($rs,true);
					if($rs['errcode']!=0){
						return json(['status'=>1,'msg'=>\app\commons\Wechat::geterror($rs)]);
					}
					\app\commons\System::plog('微信开放平台上传代码',1);
					return json(['status'=>1,'msg'=>'上传成功','url'=>true]);
				}else{
					if(strpos($rs['message'],'需要重新登录')){
						return json(['status'=>2,'msg'=>$rs]);
					}
					return json(['status'=>0,'msg'=>$rs['message'],'rs'=>$rs]);
				}
				return json($rs);
			}
			\app\commons\System::plog('微信开放平台设置',1);
			return json(['status'=>1,'msg'=>'设置成功','url'=>true]);
		}
		$info = Db::name('sysset')->where('name','component')->find();
		if(!$info){
			Db::name('sysset')->insert(['name'=>'component','value'=>'[]']);
			$info = Db::name('sysset')->where('name','component')->find();
		}
		$info = json_decode($info['value'],true);
		if(!$info['token']) $info['token'] = random(32);
		if(!$info['key']) $info['key'] = random(43);
		View::assign('info',$info);
		View::assign('domain',str_replace(['http://','https://'],'',request()->domain()));
		return View::fetch();
	}

    //上传公众号小程序域名校验文件
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
        \app\commons\System::plog('上传开放平台域名校验文件');
        showmsg('上传成功',1);
    }

    private function parse_path($path) {
        $danger_char = array('../', '{php', '<?php', '<%', '<?', '..\\', '\\\\', '\\', '..\\\\', '%00', '\0', '\r');
        foreach ($danger_char as $char) {
            if ($this->strexists($path, $char)) {
                return false;
            }
        }
        return $path;
    }

    public function updateH5QQMapKey($qqmapkey='')
    {
        $from = input('param.from');
        if(empty($qqmapkey)) {
            $info = Db::name('sysset')->where('name','webinfo')->find();
            $webinfo = json_decode($info['value'],true);
            $qqmapkey = $webinfo['map_key_qq'];
        }

        $directory = "h5/static/js";

        // 获取文件夹内的文件列表
//        $files = scandir($directory);

        $indexFils = [];
        // 遍历文件列表并输出文件名
//        foreach($files as $file) {
//            if(preg_match('/^index\.([^\.]+)\.js$/', $file)){
//                $indexFils[] = $file;
//            }
//        }

        $h5Index = 'h5/index.html';
        $filecontent = File::get($h5Index);
        preg_match('/index\.([^\.]+)\.js/', $filecontent,$matches);
        $indexFils[] = $matches[0];

        if($indexFils){
            foreach($indexFils as $file) {
                $filepath = $directory.'/'.$file;
                if (!is_writable($filepath)) {
                    $return = ['status' => 0, 'msg' => $filepath.'没有写权限，H5地图key更新失败'];
                    if($from == 'update'){
                        $return = json($return);
                    }
                    return $return;
                }
                $filecontent = File::get($filepath);
                $newfilecontent = preg_replace('/qqMapKey="[^"]*"/','qqMapKey="'.$qqmapkey.'"',$filecontent);
                $newfilecontent = preg_replace('/qqmap:\{key:"([^"]+)"/','qqmap:{key:"'.$qqmapkey.'"',$newfilecontent);
                $rs = File::put($filepath,$newfilecontent);
                if(!$rs){
                    $return = ['status' => 0, 'msg' => 'H5地图key更新失败'];
                    if($from == 'update'){
                        $return = json($return);
                    }
                    return $return;
                }
            }
        }

        $return = ['status' => 1, 'msg' => '成功更新'];
        if($from == 'update'){
            $return = json($return);
        }
        return $return;
    }

    private function strexists($string, $find) {
        return !(false === strpos($string, $find));
    }

	//微支付日志
    public function wxpaylog(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
				$order = 'id desc';
			}
			$where = [];
			if(input('param.ctime') ){
				$ctime = explode(' ~ ',input('param.ctime'));
				$where[] = ['createtime','>=',strtotime($ctime[0])];
				$where[] = ['createtime','<',strtotime($ctime[1]) + 86400];
			}
			$count = 0 + Db::name('wxpay_log')->where($where)->count();
			$data = Db::name('wxpay_log')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			foreach($data as $k=>$v){
				$member = Db::name('member')->where('id',$v['mid'])->find();
				$data[$k]['nickname'] = $member['nickname'];
				$data[$k]['headimg'] = $member['headimg'];
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
    }
	//删除
	public function wxpaylogdel(){
		$ids = input('post.ids/a');
		if(!$ids) $ids = array(input('post.id/d'));
		Db::name('wxpay_log')->where('id','in',$ids)->delete();
		\app\commons\System::plog('微信支付日志删除'.implode(',',$ids),1);
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	//改为未分账
	public function wxpaylogsetst(){
		$ids = input('post.ids/a');
		$st = input('post.st/d');
		if(!$ids) $ids = array(input('post.id/d'));
		Db::name('wxpay_log')->where('id','in',$ids)->update(['isfenzhang'=>$st,'fz_errmsg'=>'']);
		if($st == 4){//取消分账
			$loglist = Db::name('wxpay_log')->where('id','in',$ids)->select();
			$dbwxpayset = Db::name('sysset')->where('name','wxpayset')->value('value');
			$dbwxpayset = json_decode($dbwxpayset,true);
			$sslcert = ROOT_PATH.$dbwxpayset['apiclient_cert'];
			$sslkey = ROOT_PATH.$dbwxpayset['apiclient_key'];
			$mchkey = $dbwxpayset['mchkey'];
			foreach($loglist as $log){

				$sub_mchid = $log['sub_mchid'];
				if($log['bid'] > 0){
					$bset = Db::name('business_sysset')->where('aid',$log['aid'])->find();
					$dbwxpayset = [
						'mchname'=>$bset['wxfw_mchname'],
						'appid'=>$bset['wxfw_appid'],
						'mchid'=>$bset['wxfw_mchid'],
						'mchkey'=>$bset['wxfw_mchkey'],
						'apiclient_cert'=>$bset['wxfw_apiclient_cert'],
						'apiclient_key'=>$bset['wxfw_apiclient_key'],
					];
				}

				$pars = [];
				$pars['mch_id'] = $dbwxpayset['mchid'];
				$pars['sub_mch_id'] = $sub_mchid;
				$pars['appid'] = $dbwxpayset['appid'];
				$pars['nonce_str'] = random(32);
				$pars['transaction_id'] = $log['transaction_id'];
				$pars['out_order_no'] = 'P'.date('YmdHis').rand(1000,9999);
				$pars['description'] = '分账已完成';
				//$pars['sign_type'] = 'MD5';
				ksort($pars, SORT_STRING);
				$string1 = '';
				foreach ($pars as $k => $v) {
					$string1 .= "{$k}={$v}&";
				} 
				$string1 .= "key=" . $mchkey;
				//$pars['sign'] = strtoupper(md5($string1));
				$pars['sign'] = strtoupper(hash_hmac("sha256",$string1 ,$mchkey));
				$dat = array2xml($pars);
				$client = new \GuzzleHttp\Client(['timeout'=>30,'verify'=>false]);
				$response = $client->request('POST',"https://api.mch.weixin.qq.com/secapi/pay/profitsharingfinish",['body'=>$dat,'cert'=>$sslcert,'ssl_key'=>$sslkey]);
				$info = $response->getBody()->getContents();
				
				$resp = (array)(simplexml_load_string($info,'SimpleXMLElement', LIBXML_NOCDATA));
				//Log::write($resp);
				if($resp['return_code'] == 'SUCCESS' && $resp['result_code']=='SUCCESS'){
					$msg = '取消成功';
				}else{
					$msg = '未知错误';
					if ($resp['return_code'] == 'FAIL') {
						$msg = $resp['return_msg'];
					} 
					if ($resp['result_code'] == 'FAIL') {
						$msg = $resp['err_code_des'];
					}
				}
			}
			return json(['status'=>1,'msg'=>$msg,'resp'=>$resp,'ordernum'=>$pars['out_order_no']]);
		}
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	

	//批量替换数据库字符串
	public function replacedbstr(){
		if(request()->isPost()){
			set_time_limit(0);
			ini_set('memory_limit','-1');
			$startime = time();
			$fromstr = input('post.fromstr');
			$tostr = input('post.tostr');
			if($fromstr == '' || $tostr == '') return json(['status'=>0,'msg'=>'请填写替换文本']);
            $tables = Db::query("show tables from `".\think\facade\Config::get('database.connections.mysql.database')."`");
			foreach($tables as $k=>$v){
				//var_dump(array_values($v)[0]);
				$table = array_values($v)[0];
				$fields = Db::query("SHOW COLUMNS FROM `{$table}`");
				foreach($fields as $field){
					if(strpos($field['Type'],'varchar') !== false || strpos($field['Type'],'text') !== false){
						$fieldname = $field['Field'];
						Db::execute("update `{$table}` set `{$fieldname}`=replace(`$fieldname`,'{$fromstr}','{$tostr}') where `{$fieldname}` like '%{$fromstr}%'");
					}
				}
				//var_dump($fields);
			}
			//var_dump($tables);
			$usetime = time() - $startime;
			return json(['status'=>1,'msg'=>'替换完成 用时'.$usetime.'秒','url'=>true]);
		}
		return View::fetch();
	}

	//保持连接
	public function linked(){
		return json(['status'=>1]);
	}
}