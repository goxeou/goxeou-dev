<?php


// +----------------------------------------------------------------------
// | 登录页面配置
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class DesignerLogin extends Common
{
	public function initialize(){
		parent::initialize();
		if(bid > 0) showmsg('无访问权限');
	}
    public function index(){

		$info = Db::name('designer_login')->where('aid',aid)->find();
		if(!$info){

			$logo    = PRE_URL.'/static/imgsrc/logo.jpg';
			$sysname = '';
			$bgcolor = '';

			$sysset  = Db::name('admin_set')->where('aid',aid)->field('id,name,logo,color1')->find();
			if($sysset){
				if(!empty($sysset['logo'])){
					$logo = $sysset['logo'];
				}

				$sysname = !empty($sysset['name'])?$sysset['name']:'';
				$bgcolor = !empty($sysset['color1'])?$sysset['color1']:'';

			}

			$info = array(
				'aid'=>aid,
				'type'=>1,
				'updatetime'=>time(),
				'data'=>jsonEncode([
					"logo"      => $logo,
					"bgtype"    => 1,
					"bgcolor"   => $bgcolor,
					"bgimg"     => PRE_URL.'/static/admin/img/login/bg1.png',
					"cardcolor" => '#FFFFFF',
					"titletype" => 'center',
					"title"     => '欢迎使用'.$sysname,
					"titlecolor"=> '#000000',
					"subhead"   => '推荐使用手机号验证码登录',
					"subheadcolor" => '#A8B5D3',
					"btntype"   => 1,
					"btncolor"   => '#0256FF',
					"btnwordcolor" => '#FFFFFF',
					"codecolor" => '#0256FF',
					"regtipcolor"  => '#5E6066',
					"regpwdbtncolor"  => '#666666',
					"xytipword"    => '我已阅读并同意',
					"xytipcolor"   => '#D8D8D8',
					"xycolor"  => '#0256FF'
				])
			);
			$id = Db::name('designer_login')->insertGetId($info);
			$info['id'] = $id;
		}

		$data = json_decode($info['data'],true);
		View::assign('data',$data);
		View::assign('info',$info);
		View::assign('type',$info['type']);

		View::assign('xyname',$xyname);
		$xyname = "《无》";
		$xieyi = Db::name('admin_set_xieyi')->where('aid',aid)->field('id,name')->find();
		if($xieyi){
			$xyname = !empty($xieyi['name'])?$xieyi['name']:'';
		}
		View::assign('xyname',$xyname);
		return View::fetch();
    }
	public function save(){

		$info = input('post.info/a');
		$id = $info['id']?$info['id']:0;
		$info['data'] = jsonEncode($info['data']);
		$info['updatetime'] = time();

		unset($info['id']);
		if($id){
			Db::name('designer_login')->where('id',$id)->update($info);
		}else{
			Db::name('designer_login')->insert($info);
		}

		\app\commons\System::plog('设置登录页面');
		return json(['status'=>1,'msg'=>'保存成功','url'=>(string)url('index')]);
	}

	//内页导航
    public function menu2(){
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
			$where[] = ['bid','=',bid];
			if(input('param.name')) $where[] = ['name','=',input('param.name')];
			if(input('param.platform')) $where[] = ['platform','=',input('param.platform')];
			if(input('param.indexurl')) $where[] = ['indexurl','like','%'.input('param.indexurl').'%'];
			if(input('param.indexurlname')) $where[] = ['indexurlname','like','%'.input('param.indexurlname').'%'];
			if(input('?param.status') && input('param.status')!=='') $where[] = ['status','=',input('param.status')];
			$count = 0 + Db::name('designer_login2')->where($where)->count();
			$data = Db::name('designer_login2')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			foreach($data as $k=>$v){
				if($v['platform'] == 'all'){
					$data[$k]['platform'] = '全部';
				}else{
					$data[$k]['platform'] = getplatformname($v['platform']);
				}
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
    }
	//编辑
	public function menu2edit(){
		if(input('param.id')){
			$info = Db::name('designer_login2')->where('aid',aid)->where('bid',bid)->where('id',input('param.id/d'))->find();
		}else{
			$info = array(
				'id'=>'',
				'platform'=>'all',
				'name'=>'菜单名称',
				'indexurl'=>'',
				'backgroundColor'=>'#ffffff',
				'menucount'=>4,
				'data'=>jsonEncode([
					["pagePath"=>'',"text"=>"菜单一","iconPath"=>PRE_URL."/static/img/tabbar/category.png"],
					["pagePath"=>'',"text"=>"菜单二","iconPath"=>PRE_URL."/static/img/tabbar/category.png"],
					["pagePath"=>'',"text"=>"菜单三","iconPath"=>PRE_URL."/static/img/tabbar/category.png"],
					["pagePath"=>'',"text"=>"菜单四","iconPath"=>PRE_URL."/static/img/tabbar/category.png"],
					["pagePath"=>'',"text"=>"菜单五","iconPath"=>PRE_URL."/static/img/tabbar/category.png"],
				])
			);
		}
		View::assign('info',$info);
		return View::fetch();
	}
	//保存
	public function menu2save(){
		$info = input('post.info/a');
		$info['data'] = jsonEncode($info['data']);
		if($info['id']){
			Db::name('designer_login2')->where('aid',aid)->where('bid',bid)->where('id',$info['id'])->update($info);
			\app\commons\System::plog('编辑内页导航'.$info['id']);
		}else{
			$info['aid'] = aid;
			$info['bid'] = bid;
			$info['createtime'] = time();
			$id = Db::name('designer_login2')->insertGetId($info);
			\app\commons\System::plog('添加内页导航'.$id);
		}
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}
	//改状态
	public function setst(){
		$st = input('post.st/d');
		$ids = input('post.ids/a');
		Db::name('designer_login2')->where('aid',aid)->where('bid',bid)->where('id','in',$ids)->update(['status'=>$st]);
		\app\commons\System::plog('内页导航改状态'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//删除
	public function menu2del(){
		$ids = input('post.ids/a');
		Db::name('designer_login2')->where('aid',aid)->where('bid',bid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('删除内页导航'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}

	//多商户的默认登录页面
    public function business(){
		$type = input('param.type') ? input('param.type') : $this->platform[0];
		$info = Db::name('designer_login_business')->where('aid',aid)->where('platform',$type)->find();
		if(!$info){
			$insertdata = [];
			$insertdata['aid'] = aid;
			$insertdata['menucount'] = 0;
			$insertdata['indexurl'] = '/pagesExt/business/index?bid=[bid]';
			$insertdata['data'] = jsonEncode([
				"color"=>"#BBBBBB",
				"selectedColor"=>"#FD4A46",
				"backgroundColor"=>"#ffffff",
				"borderStyle"=>"black",
				"position"=>"bottom",
				"list"=>[
					["text"=>"店铺","pagePath"=>"/pagesExt/business/index?id=[bid]","iconPath"=>PRE_URL.'/static/img/tabbar/home.png',"selectedIconPath"=>PRE_URL.'/static/img/tabbar/home2.png',"pagePathname"=>"基础功能>首页"
					],
					["text"=>"商品","pagePath"=>"/pages/shop/prolist?bid=[bid]","iconPath"=>PRE_URL.'/static/img/tabbar/category.png',"selectedIconPath"=>PRE_URL.'/static/img/tabbar/category2.png',"pagePathname"=>"基础功能>商品列表"
					],
					["text"=>"购物车","pagePath"=>"/pages/shop/cart?bid=[bid]","iconPath"=>PRE_URL.'/static/img/tabbar/cart.png',"selectedIconPath"=>PRE_URL.'/static/img/tabbar/cart2.png',"pagePathname"=>"基础功能>购物车"
					],
					["text"=>"我的","pagePath"=>"/pages/my/usercenter?bid=[bid]","iconPath"=>PRE_URL.'/static/img/tabbar/my.png',"selectedIconPath"=>PRE_URL.'/static/img/tabbar/my2.png',"pagePathname"=>"基础功能>会员中心"
					],
					["text"=>"导航名称","pagePath"=>"","iconPath"=>PRE_URL.'/static/img/tabbar/category.png',"selectedIconPath"=>PRE_URL.'/static/img/tabbar/category2.png',"pagePathname"=>""
					],
				]
			]);
			$insertdata['platform'] = 'mp';
			Db::name('designer_login_business')->insert($insertdata);
			$insertdata['platform'] = 'wx';
			Db::name('designer_login_business')->insert($insertdata);
			$insertdata['platform'] = 'alipay';
			Db::name('designer_login_business')->insert($insertdata);
			$insertdata['platform'] = 'baidu';
			Db::name('designer_login_business')->insert($insertdata);
			$insertdata['platform'] = 'toutiao';
			Db::name('designer_login_business')->insert($insertdata);
			$insertdata['platform'] = 'qq';
			Db::name('designer_login_business')->insert($insertdata);
			$insertdata['platform'] = 'h5';
			Db::name('designer_login_business')->insert($insertdata);
			$insertdata['platform'] = 'app';
			Db::name('designer_login_business')->insert($insertdata);
			$info = Db::name('designer_login_business')->where('aid',aid)->where('platform',$type)->find();
		}
		$data = json_decode($info['data'],true);
		View::assign('data',$data);
		View::assign('info',$info);
		View::assign('type',$type);
		return View::fetch();
    }
	public function businesssave(){
		$type = input('param.type') ? input('param.type') : $this->platform[0];
		$data = input('post.info/a');
		$data['data'] = jsonEncode($data['data']);
		$data['updatetime'] = time();

		Db::name('designer_login_business')->where('aid',aid)->where('platform',$type)->update($data);

		\app\commons\System::plog('设置多商户登录页面');
		return json(['status'=>1,'msg'=>'保存成功','url'=>(string)url('business').'/type/'.$type]);
	}
	//复制内页导航
	public function menu2copy(){
		$menu2 = Db::name('designer_login2')->where('aid',aid)->where('bid',bid)->where('id',input('post.id/d'))->find();
		if(!$menu2) return json(['status'=>0,'msg'=>'导航不存在,请重新选择']);
		$data = $menu2;
		unset($data['id']);
		$newid = Db::name('designer_login2')->insertGetId($data);
		return json(['status'=>1,'msg'=>'复制成功','newid'=>$newid]);
	}
}