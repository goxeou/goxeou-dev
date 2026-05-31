<?php


// +----------------------------------------------------------------------
// | 大区设置
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class Largearea extends Common
{
    public function initialize(){
		parent::initialize();
		if(bid > 0) showmsg('无访问权限');
	}
	//大区列表
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
			if(input('param.name')) $where[] = ['name','like','%'.input('param.name').'%'];
			if(input('?param.status') && input('param.status')!=='') $where[] = ['status','=',input('param.status')];
			//dump($where);
			$count = 0 + Db::name('largearea')->where($where)->count();
			$data = Db::name('largearea')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
    }
	//编辑
	public function edit(){
		if(input('param.id')){
			$info = Db::name('largearea')->where('aid',aid)->where('id',input('param.id/d'))->find();
		}else{
			$info = array('id'=>'','province'=>'');
		}
		$info['province'] = $info['province'] ? explode(',',$info['province']) : [];
		$provinceList = ['北京市','天津市','河北省','山西省','内蒙古自治区','辽宁省','吉林省','黑龙江省','上海市','江苏省','浙江省','安徽省','福建省','江西省','山东省','河南省','湖北省','湖南省','广东省','广西壮族自治区','海南省','重庆市','四川省','贵州省','云南省','西藏自治区','陕西省','甘肃省','青海省','宁夏回族自治区','新疆维吾尔自治区','台湾省','香港特别行政区','澳门特别行政区'];
		View::assign('info',$info);
		View::assign('provinceList',$provinceList);
		return View::fetch();
	}
	//保存
	public function save(){
		$info = input('post.info/a');
		$info['province'] = implode(',',$info['province']);
		if($info['id']){
			Db::name('largearea')->where('aid',aid)->where('id',$info['id'])->update($info);
		}else{
			$info['aid'] = aid;
			$info['createtime'] = time();
			$id = Db::name('largearea')->insertGetId($info);
		}
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}
	//删除
	public function del(){
		$ids = input('post.ids/a');
		Db::name('largearea')->where('aid',aid)->where('id','in',$ids)->delete();
		return json(['status'=>1,'msg'=>'删除成功']);
	}
}