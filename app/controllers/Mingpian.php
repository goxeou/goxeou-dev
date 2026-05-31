<?php


// +----------------------------------------------------------------------
// | 名片
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class Mingpian extends Common
{
    public function initialize(){
		parent::initialize();
	}
	//列表
    public function index(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
				$order = 'id desc';
			}
			$where = [];
			$where[] = ['mingpian.aid','=',aid];
			if(input('param.realname')) $where[] = ['mingpian.realname','like','%'.input('param.realname').'%'];
			if(input('param.tel')) $where[] = ['mingpian.tel','like','%'.input('param.tel').'%'];
			if(input('param.nickname')) $where[] = ['member.nickname','like','%'.input('param.nickname').'%'];
			$count = 0 + Db::name('mingpian')->alias('mingpian')->field('member.nickname,member.headimg,mingpian.*')->join('member member','member.id=mingpian.mid')->where($where)->count();
			$data = Db::name('mingpian')->alias('mingpian')->field('member.nickname,member.headimg,mingpian.*')->join('member member','member.id=mingpian.mid')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		$set = Db::name('mingpian_set')->where('aid',aid)->find();
		$field_list = json_decode($set['field_list'],true);
		View::assign('field_list',$field_list);
		$this->defaultSet();
		return View::fetch();
    }
	//编辑
	public function edit(){
		if(input('param.id')){
			$info = Db::name('mingpian')->where('aid',aid)->where('id',input('param.id/d'))->find();
		}else{
			$info = [];
		}
		$set = Db::name('mingpian_set')->where('aid',aid)->find();
		$field_list = json_decode($set['field_list'],true);
		View::assign('info',$info);
		View::assign('field_list',$field_list);
		return View::fetch();
	}
	//保存
	public function save(){
		$info = input('post.info/a');
		$info['detail'] = \app\commons\Common::geteditorcontent($info['detail']);
		if($info['id']){
			Db::name('mingpian')->where('aid',aid)->where('id',$info['id'])->update($info);
		}else{
			$info['aid'] = aid;
			Db::name('mingpian')->insert($info);
		}
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}
    public function set(){
		if(request()->isPost()){
			$info = input('post.info/a');
			$field_list = input('post.field_list/a');
			$info['field_list'] = jsonEncode($field_list);
			$info['createtj'] = implode(',',$info['createtj']);

			Db::name('mingpian_set')->where('aid',aid)->update($info);
			\app\commons\System::plog('名片系统设置');
			return json(['status'=>1,'msg'=>'保存成功','url'=>true]);
		}
		$info = Db::name('mingpian_set')->where('aid',aid)->find();
		if(!$info){
			$field_list = [
				"tel"=>["isshow"=>"1","name"=>"手机号","required"=>"1","icon"=>PRE_URL.'/static/img/mingpian/tel.png'],
				"weixin"=>["isshow"=>"1","name"=>"微信号","required"=>"1","icon"=>PRE_URL.'/static/img/mingpian/weixin.png'],
				"address"=>["isshow"=>"1","name"=>"地址","required"=>"1","icon"=>PRE_URL.'/static/img/mingpian/address.png'],
				"email"=>["isshow"=>"1","name"=>"邮箱","icon"=>PRE_URL.'/static/img/mingpian/email.png'],
				"douyin"=>["name"=>"抖音号","icon"=>PRE_URL.'/static/img/mingpian/douyin.png'],
				"weibo"=>["name"=>"微博","icon"=>PRE_URL.'/static/img/mingpian/weibo.png'],
				"toutiao"=>["name"=>"头条号","icon"=>PRE_URL.'/static/img/mingpian/toutiao.png'],
				"field1"=>["name"=>"","icon"=>PRE_URL.'/static/img/mingpian/default.png'],
				"field2"=>["name"=>"","icon"=>PRE_URL.'/static/img/mingpian/default.png'],
				"field3"=>["name"=>"","icon"=>PRE_URL.'/static/img/mingpian/default.png'],
				"field4"=>["name"=>"","icon"=>PRE_URL.'/static/img/mingpian/default.png'],
				"field5"=>["name"=>"","icon"=>PRE_URL.'/static/img/mingpian/default.png']
			];
			Db::name('mingpian_set')->insert(['aid'=>aid,'field_list'=>jsonEncode($field_list)]);
			$info = Db::name('mingpian_set')->where('aid',aid)->find();
		}
		$info['createtj'] = explode(',',$info['createtj']);
		$info['field_list'] = json_decode($info['field_list'],true);
		
        $default_cid = Db::name('member_level_category')->where('aid',aid)->where('isdefault', 1)->value('id');
        $default_cid = $default_cid ? $default_cid : 0;
        $memberlevel = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->order('sort,id')->select()->toArray();
		View::assign('memberlevel',$memberlevel);

		View::assign('info',$info);
		return View::fetch();
    }
	//删除
	public function del(){
		$ids = input('post.ids/a');
		Db::name('mingpian')->where('aid',aid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('删除名片'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}

	
	//查看记录
    public function readlog(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = 'mingpian_readlog.'.input('param.field').' '.input('param.order');
			}else{
				$order = 'mingpian_readlog.id desc';
			}
			$where = array();
			$where[] = ['mingpian_readlog.aid','=',aid];
			if(input('param.mpid')){
				$where[] = ['mingpian_readlog.mpid','=',input('param.mpid/d')];
			}
			
			if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
			if(input('param.mid')) $where[] = ['mingpian_readlog.mid','=',trim(input('param.mid'))];
			
			if(input('param.ctime')){
				$ctime = explode(' ~ ',input('param.ctime'));
				$where[] = ['mingpian_readlog.createtime','>=',strtotime($ctime[0])];
				$where[] = ['mingpian_readlog.createtime','<',strtotime($ctime[1])];
			}
			$count = 0 + Db::name('mingpian_readlog')->alias('mingpian_readlog')->field('member.nickname,member.headimg,mingpian_readlog.*')->join('member member','member.id=mingpian_readlog.mid')->where($where)->count();
			$data = Db::name('mingpian_readlog')->alias('mingpian_readlog')->field('member.nickname,member.headimg,mingpian_readlog.*')->join('member member','member.id=mingpian_readlog.mid')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
    }
	//查看记录删除
	public function readlogdel(){
		$ids = input('post.ids/a');
		Db::name('mingpian_readlog')->where('aid',aid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('删除名片查看记录'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}

	
	//收藏记录
    public function favoritelog(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = 'mingpian_favorite.'.input('param.field').' '.input('param.order');
			}else{
				$order = 'mingpian_favorite.id desc';
			}
			$where = array();
			$where[] = ['mingpian_favorite.aid','=',aid];
			if(input('param.mpid')){
				$where[] = ['mingpian_favorite.mpid','=',input('param.mpid/d')];
			}
			
			if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
			if(input('param.mid')) $where[] = ['mingpian_favorite.mid','=',trim(input('param.mid'))];
			
			if(input('param.ctime')){
				$ctime = explode(' ~ ',input('param.ctime'));
				$where[] = ['mingpian_favorite.createtime','>=',strtotime($ctime[0])];
				$where[] = ['mingpian_favorite.createtime','<',strtotime($ctime[1])];
			}
			$count = 0 + Db::name('mingpian_favorite')->alias('mingpian_favorite')->field('member.nickname,member.headimg,mingpian_favorite.*')->join('member member','member.id=mingpian_favorite.mid')->where($where)->count();
			$data = Db::name('mingpian_favorite')->alias('mingpian_favorite')->field('member.nickname,member.headimg,mingpian_favorite.*')->join('member member','member.id=mingpian_favorite.mid')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
    }
	//收藏记录删除
	public function favoritelogdel(){
		$ids = input('post.ids/a');
		Db::name('mingpian_favorite')->where('aid',aid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('删除名片收藏记录'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
    function defaultSet(){
        $set = Db::name('mingpian_set')->where('aid',aid)->find();
        if(!$set){
            $field_list = [
                "tel"=>["isshow"=>"1","name"=>"手机号","required"=>"1","icon"=>PRE_URL.'/static/img/mingpian/tel.png'],
                "weixin"=>["isshow"=>"1","name"=>"微信号","required"=>"1","icon"=>PRE_URL.'/static/img/mingpian/weixin.png'],
                "address"=>["isshow"=>"1","name"=>"地址","required"=>"1","icon"=>PRE_URL.'/static/img/mingpian/address.png'],
                "email"=>["isshow"=>"1","name"=>"邮箱","icon"=>PRE_URL.'/static/img/mingpian/email.png'],
                "douyin"=>["name"=>"抖音号","icon"=>PRE_URL.'/static/img/mingpian/douyin.png'],
                "weibo"=>["name"=>"微博","icon"=>PRE_URL.'/static/img/mingpian/weibo.png'],
                "toutiao"=>["name"=>"头条号","icon"=>PRE_URL.'/static/img/mingpian/toutiao.png'],
                "field1"=>["name"=>"","icon"=>PRE_URL.'/static/img/mingpian/default.png'],
                "field2"=>["name"=>"","icon"=>PRE_URL.'/static/img/mingpian/default.png'],
                "field3"=>["name"=>"","icon"=>PRE_URL.'/static/img/mingpian/default.png'],
                "field4"=>["name"=>"","icon"=>PRE_URL.'/static/img/mingpian/default.png'],
                "field5"=>["name"=>"","icon"=>PRE_URL.'/static/img/mingpian/default.png']
            ];
            Db::name('mingpian_set')->insert(['aid'=>aid,'field_list'=>jsonEncode($field_list)]);
        }
    }
}
