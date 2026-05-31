<?php


// +----------------------------------------------------------------------
// | 预约服务 商品服务
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class YuyueFuwu extends Common
{
    public function initialize(){
		parent::initialize();
		//if(bid > 0) showmsg('无访问权限');
	}
	//服务列表
    public function index(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
				$order = 'sort desc,id';
			}
			$where = array();
			$where[] = ['aid','=',aid];
			$where[] = ['bid','=',bid];
			if(input('param.name')) $where[] = ['name','like','%'.input('param.name').'%'];
			if(input('?param.status') && input('param.status')!=='') $where[] = ['status','=',input('param.status')];
			//dump($where);
			$count = 0 + Db::name('yuyue_fuwu')->where($where)->count();
			$data = Db::name('yuyue_fuwu')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
        $this->defaultSet();
		return View::fetch();
    }
	//编辑
	public function edit(){
		if(input('param.id')){
			$info = Db::name('yuyue_fuwu')->where('aid',aid)->where('id',input('param.id/d'))->find();
		}else{
			$info = array('id'=>'');
		}
		$pcatelist = Db::name('yuyue_fuwu')->where('aid',aid)->order('sort desc,id')->select()->toArray();
		View::assign('info',$info);
		return View::fetch();
	}
	//保存
	public function save(){
		$info = input('post.info/a');
		if($info['id']){
			Db::name('yuyue_fuwu')->where('aid',aid)->where('id',$info['id'])->update($info);
			\app\commons\System::plog('编辑 预约服务-商品服务'.$info['id']);
		}else{
			$info['aid'] = aid;
			$info['bid'] = bid;
			$info['createtime'] = time();
			$id = Db::name('yuyue_fuwu')->insertGetId($info);
			\app\commons\System::plog('添加 预约服务-商品服务'.$id);
		}
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}
	//删除
	public function del(){
		$ids = input('post.ids/a');
		Db::name('yuyue_fuwu')->where('aid',aid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('删除 预约服务-商品服务'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
    function defaultSet(){
        $set = Db::name('yuyue_set')->where('aid',aid)->where('bid',bid)->find();
        if(!$set){
            Db::name('yuyue_set')->insert(['aid'=>aid,'bid' => bid]);
        }
    }
}