<?php


// +----------------------------------------------------------------------
// | 菜单配置
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class DesignerMenu extends Common
{
    public function index(){
		$type = input('param.type') ? input('param.type') : $this->platform[0];
		$info = Db::name('designer_menu')->where('aid',aid)->where('platform',$type)->find();
		$menudata = json_decode($info['menudata'],true);
		View::assign('menudata',$menudata);
		View::assign('info',$info);
		View::assign('type',$type);
		return View::fetch();
    }
	/**
	 * 商城详情页导航
	 */
	public function save(){
		$type = input('param.type') ? input('param.type') : $this->platform[0];
		$data = input('post.info/a');
		$data['menudata'] = jsonEncode($data['menudata']);
		$data['updatetime'] = time();
		
		if($data['tongbu']){
			Db::name('designer_menu')->where('aid',aid)->update($data);
		}else{
			Db::name('designer_menu')->where('aid',aid)->where('platform',$type)->update($data);
		}
		\app\commons\System::plog('设置底部导航');
		return json(['status'=>1,'msg'=>'保存成功','url'=>(string)url('index').'/type/'.$type]);
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
			$count = 0 + Db::name('designer_menu2')->where($where)->count();
			$data = Db::name('designer_menu2')->where($where)->page($page,$limit)->order($order)->select()->toArray();
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
			$info = Db::name('designer_menu2')->where('aid',aid)->where('bid',bid)->where('id',input('param.id/d'))->find();
		}else{
			$info = array(
				'id'=>'',
				'platform'=>'all',
				'name'=>'菜单名称',
				'indexurl'=>'',
				'backgroundColor'=>'#ffffff',
				'menucount'=>4,
				'menudata'=>jsonEncode([
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
		$info['menudata'] = jsonEncode($info['menudata']);
		if($info['id']){
			Db::name('designer_menu2')->where('aid',aid)->where('bid',bid)->where('id',$info['id'])->update($info);
			\app\commons\System::plog('编辑内页导航'.$info['id']);
		}else{
			$info['aid'] = aid;
			$info['bid'] = bid;
			$info['createtime'] = time();
			$id = Db::name('designer_menu2')->insertGetId($info);
			\app\commons\System::plog('添加内页导航'.$id);
		}
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}
	//改状态
	public function setst(){
		$st = input('post.st/d');
		$ids = input('post.ids/a');
		Db::name('designer_menu2')->where('aid',aid)->where('bid',bid)->where('id','in',$ids)->update(['status'=>$st]);
		\app\commons\System::plog('内页导航改状态'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//删除
	public function menu2del(){
		$ids = input('post.ids/a');
		Db::name('designer_menu2')->where('aid',aid)->where('bid',bid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('删除内页导航'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}

	//多商户的默认底部导航
    public function business(){
		$type = input('param.type') ? input('param.type') : $this->platform[0];
		$info = Db::name('designer_menu_business')->where('aid',aid)->where('platform',$type)->find();
		if(!$info){
			$insertdata = [];
			$insertdata['aid'] = aid;
			$insertdata['menucount'] = 0;
			$insertdata['indexurl'] = '/pagesExt/business/index?bid=[bid]';
			$insertdata['menudata'] = jsonEncode([
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
			Db::name('designer_menu_business')->insert($insertdata);
			$insertdata['platform'] = 'wx';
			Db::name('designer_menu_business')->insert($insertdata);
			$insertdata['platform'] = 'alipay';
			Db::name('designer_menu_business')->insert($insertdata);
			$insertdata['platform'] = 'baidu';
			Db::name('designer_menu_business')->insert($insertdata);
			$insertdata['platform'] = 'toutiao';
			Db::name('designer_menu_business')->insert($insertdata);
			$insertdata['platform'] = 'qq';
			Db::name('designer_menu_business')->insert($insertdata);
			$insertdata['platform'] = 'h5';
			Db::name('designer_menu_business')->insert($insertdata);
			$insertdata['platform'] = 'app';
			Db::name('designer_menu_business')->insert($insertdata);
			$info = Db::name('designer_menu_business')->where('aid',aid)->where('platform',$type)->find();
		}
		$menudata = json_decode($info['menudata'],true);
		View::assign('menudata',$menudata);
		View::assign('info',$info);
		View::assign('type',$type);
		return View::fetch();
    }
	public function businesssave(){
		$type = input('param.type') ? input('param.type') : $this->platform[0];
		$data = input('post.info/a');
		$data['menudata'] = jsonEncode($data['menudata']);
		$data['updatetime'] = time();
		
		if($data['tongbu']){
			Db::name('designer_menu_business')->where('aid',aid)->update($data);
		}else{
			Db::name('designer_menu_business')->where('aid',aid)->where('platform',$type)->update($data);
		}
		\app\commons\System::plog('设置多商户底部导航');
		return json(['status'=>1,'msg'=>'保存成功','url'=>(string)url('business').'/type/'.$type]);
	}
	//复制内页导航
	public function menu2copy(){
		$menu2 = Db::name('designer_menu2')->where('aid',aid)->where('bid',bid)->where('id',input('post.id/d'))->find();
		if(!$menu2) return json(['status'=>0,'msg'=>'导航不存在,请重新选择']);
		$data = $menu2;
		unset($data['id']);
		$newid = Db::name('designer_menu2')->insertGetId($data);
		return json(['status'=>1,'msg'=>'复制成功','newid'=>$newid]);
	}
}