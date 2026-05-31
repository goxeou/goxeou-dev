<?php


// +----------------------------------------------------------------------
// | 首页
// +----------------------------------------------------------------------
namespace app\controllers;
use app\BaseController;
use think\facade\View;
use think\facade\Db;

class Index extends BaseController
{
	public $webinfo;
	public function initialize(){
		if(MN == 'notify' || MN == 'notify2' || MN == 'notify3' || MN == 'linghuoxinpay' || MN == 'linghuoxinsign'){

		}else{
			$this->webinfo = Db::name('sysset')->where(['name'=>'webinfo'])->value('value');
			$this->webinfo = json_decode($this->webinfo,true);
			if(!$this->webinfo['showweb'] && request()->action() != 'downloadapp'){
				header('Location:'.(string)url('Backstage/index'));die;
			}
			View::assign('webinfo',$this->webinfo);
			//开启注册
			$reg_open = isset($this->webinfo['reg_open']) ? $this->webinfo['reg_open'] : 0;
			View::assign('reg_open',$reg_open);
		}
	}
	//首页框架
    public function index(){
		if(MN == 'notify'){
			$notify = new \app\commons\Notify();
			$notify->index();
		}elseif(MN == 'notify2'){
			$notify = new \app\commons\Notify2();
			$notify->index();
		}elseif(MN == 'notify3'){
           \app\customs\Chain::notify();
        }elseif(MN == 'linghuoxinpay' || MN == 'linghuoxinsign'){
          }else{
			if($this->isMobile()){
				return View::fetch('index/wap/index');
			}
            if($this->webinfo['showweb']==2 && request()->action() != 'downloadapp'){
				return View::fetch('index2/index');
			}
			return View::fetch();
		}
    }
	
	public function lianxi(){

		\think\facade\Request::filter(['strip_tags','htmlspecialchars']);

		if(request()->isPost()){
			$realname = input('post.realname');
			$tel = input('post.tel');
			$content = input('post.content');
            $captcha = trim(input('post.captcha'));
            if($captcha == ''){
                return json(['status'=>0,'msg'=>'验证码不能为空']);
            }elseif(!captcha_check($captcha)){
                return json(['status'=>0,'msg'=>'验证码错误']);
            }
			$ip = request()->ip();
			db('webmessage')->insert(['realname'=>$realname,'tel'=>$tel,'content'=>$content,'ip'=>$ip,'createtime'=>time()]);
			return json(['status'=>1,'msg'=>'提交成功']);
		}
		if($this->isMobile()){
			return View::fetch('index/wap/lianxi');
		}
		if($this->webinfo['showweb']==2 && request()->action() != 'downloadapp'){
			return View::fetch('index2/lianxi');
		}
		return View::fetch();
	}
	//是否是移动端
	function isMobile(){
		if (isset ($_SERVER['HTTP_X_WAP_PROFILE'])){
			return true;
		}
		if (isset ($_SERVER['HTTP_USER_AGENT'])){
			$clientkeywords = array ('nokia','sony','ericsson','mot','samsung','htc','sgh','lg','sharp','sie-','philips','panasonic','alcatel','lenovo','iphone','ipod','blackberry','meizu','android','netfront','symbian','ucweb','windowsce','palm','operamini','operamobi','openwave','nexusone','cldc','midp','wap','mobile');
			if (preg_match("/(" . implode('|', $clientkeywords) . ")/i", strtolower($_SERVER['HTTP_USER_AGENT']))){
				return true;
			}
		}
		if (isset ($_SERVER['HTTP_ACCEPT'])){
			if ((strpos($_SERVER['HTTP_ACCEPT'], 'vnd.wap.wml') !== false) && (strpos($_SERVER['HTTP_ACCEPT'], 'text/html') === false || (strpos($_SERVER['HTTP_ACCEPT'], 'vnd.wap.wml') < strpos($_SERVER['HTTP_ACCEPT'], 'text/html')))){
				return true;
			}
		}
		if (isset ($_SERVER['HTTP_VIA'])){
			return stristr($_SERVER['HTTP_VIA'], "wap") ? true : false;
		}
		return false;
	}
	public function news(){
		$cid = $_GET['id'] ? $_GET['id'] : 1;
		$clist = db('help_category')->where(array('status'=>1))->order('sort desc,id')->select();
		$where = [];
		$where[] = ['cid','=',$cid];
		$where[] = ['status','=',1];
		$list = db('help')->where($where)->order('sort desc,sendtime desc')->limit(10)->select();
		View::assign('clist',$clist);
		View::assign('list',$list);
		return View::fetch();
	}
	public function newsdetail(){
		$id = intval($_GET['id']);
		$where = [];
		$where[] = ['id','=',$id];
		$where[] = ['status','=',1];
		$info = db('help')->where($where)->find();
		db('help')->where($where)->inc('readcount')->update();
		View::assign('info',$info);
		return View::fetch();
	}
	public function help(){
		$where = [];
		$where[] = ['status','=',1];
		if(input('param.keyword')){
			$where[] = ['name','like','%'.input('param.keyword').'%'];
		}
		$list = db('help')->where($where)->order('sort desc')->paginate(['list_rows'=>20,'query'=>['s'=>'/index/help']]);
		// 获取分页显示
		$page = $list->render();
		// 模板变量赋值
		View::assign('list', $list);
		View::assign('page', $page);

		if($this->webinfo['showweb']==2 && request()->action() != 'downloadapp'){
			return View::fetch('index2/help');
		}
		return View::fetch();
	}
	public function helpdetail(){
		$id = input('param.id/d');
		$where = [];
		$where[] = ['id','=',$id];
		$where[] = ['status','=',1];
		$info = db('help')->where($where)->find();
		Db::name('help')->where($where)->inc('readcount')->update();
		View::assign('info',$info);
		if($this->webinfo['showweb']==2 && request()->action() != 'downloadapp'){
			return View::fetch('index2/helpdetail');
		}
		return View::fetch();
	}
	public function funshow(){
		if($this->webinfo['showweb']==2 && request()->action() != 'downloadapp'){
			return View::fetch('index2/funshow');
		}
		return View::fetch('index');
	}

	//下载app
	public function downloadapp(){
		$aid = input('param.aid/d');
		if(!$aid) $aid = '1';
		$set = Db::name('admin_set')->where('aid',$aid)->find();
		$appinfo = Db::name('admin_setapp_app')->where('aid',$aid)->find();
	    $systemtype = '';
		$androidurl = '';
		$iosurl = '';
		if($appinfo['androidurl']){
			$androidurl = $appinfo['androidurl'];
		}elseif($set['androidurl']){
			$androidurl = $set['androidurl'];
		}else{
			$androidurl = PRE_URL.'/'.$aid.'.apk';
		}
		if($appinfo['iosurl']){
			$iosurl = $appinfo['iosurl'];
		}elseif($set['iosurl']){
			$iosurl = $set['iosurl'];
		}
	    //$iosurl = PRE_URL.'/'.$aid.'.ipa';
	    
	    if(strpos($_SERVER['HTTP_USER_AGENT'], 'iPhone')||strpos($_SERVER['HTTP_USER_AGENT'], 'iPad')){ 
	        $systemtype = 'ios';
			//$androidurl = '';
	    }else if(strpos($_SERVER['HTTP_USER_AGENT'], 'Android')){ 
	         $systemtype = 'Android';
			 //$iosurl = '';
	    }
	    
	    
	    
	    $saveData= [];
	    $filePath = ROOT_PATH.str_replace(PRE_URL.'/','',$androidurl);
	    $filePath = str_replace('/..','',$filePath);
	    if (file_exists($filePath)) {
            $apk = new \ApkParser\Parser($filePath,['manifest_only' => false]);
            $manifest = $apk->getManifest();
            $saveData['app_bid'] = $manifest->getPackageName();//应用唯一标识
            $label = $manifest->getApplication()->getLabel();
            $saveData['app_name'] = $apk->getResources($label);
            $saveData['version_code'] = $manifest->getVersionCode();
            $saveData['version_name']= $manifest->getVersionName();
            $saveData['app_min_level'] = $manifest->getTargetSdk()->getLevel();
            $fileSize = filesize($filePath);
            $saveData['fileSize'] = round($fileSize / 1024 / 1024, 2) . ' MB';
        } else {
            // 文件不存在
        }
	    
	    
	    
	    ll($saveData,'$saveData');
	    
	    
	    
	    View::assign('saveData',$saveData);
	    $isweixin = is_weixin();
	    View::assign('appinfo',$appinfo);
	    View::assign('aid',$aid);
	    View::assign('systemtype',$systemtype);
	    View::assign('isweixin',$isweixin);
	    View::assign('iosurl',$iosurl);
	    View::assign('androidurl',$androidurl);
	    View::assign('set',$set);
	    return View::fetch();
	}
    public function newslist(){
	}
    public function newscontent(){
    }
}
