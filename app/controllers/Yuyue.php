<?php


// +----------------------------------------------------------------------
// | 配送员
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class Yuyue extends Common
{
    public function initialize(){
		parent::initialize();
	}
	//列表
    public function index(){
		$set = Db::name('yuyue_set')->where('aid',aid)->where('bid',bid)->find();
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
			$where[] = ['pid','=',0];
			if(input('param.realname')) $where[] = ['realname','like','%'.input('param.realname').'%'];
			if(input('?param.status') && input('param.status')!=='') $where[] = ['status','=',input('param.status')];
			$count = 0 + Db::name('yuyue_category')->where($where)->count();
			$data = [];
			$cate0 = Db::name('yuyue_category')->where($where)->order($order)->select()->toArray();
			foreach($cate0 as $c0){
				$c0['deep'] = 0;
				$data[] = $c0;
				if(false){}else{
					$cate1 = Db::name('yuyue_category')->where('aid',aid)->where('bid',bid)->where('pid',$c0['id'])->order($order)->select()->toArray();
				}
				foreach($cate1 as $k1=>$c1){
					if($k1 < count($cate1)-1){
						$c1['name'] = '<span style="color:#aaa">&nbsp;&nbsp;&nbsp;&nbsp;├ </span>'.$c1['name'];
					}else{
						$c1['name'] = '<span style="color:#aaa">&nbsp;&nbsp;&nbsp;&nbsp;└ </span>'.$c1['name'];
					}
					$c1['deep'] = 1;
					$data[] = $c1;
				}
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		View::assign('set',$set);
        $this->defaultSet();
		return View::fetch();
    }
	//编辑
	public function edit(){
		if(input('param.id')){
			$info = Db::name('yuyue_category')->where('aid',aid)->where('bid',bid)->where('id',input('param.id/d'))->find();
		}else{
			$info = array('id'=>'');
		}
		if(input('param.pid')) $info['pid'] = input('param.pid');
		$pcatelist = Db::name('yuyue_category')->where('aid',aid)->where('bid',bid)->where('pid',0)->where('id','<>',$info['id'])->order('sort desc,id')->select()->toArray();
//		foreach($pcatelist as $k=>$v){
//			$pcatelist[$k]['child'] = Db::name('yuyue_category')->Field('id,name')->where('aid',aid)->where('bid',bid)->where('pid',$v['id'])->where('id','<>',$info['id'])->order('sort desc,id')->select()->toArray();
//		}
		View::assign('pcatelist',$pcatelist);
		View::assign('info',$info);
		return View::fetch();
	}
	public function save(){
		$info = input('post.info/a');
		if($info['id']){
			Db::name('yuyue_category')->where('aid',aid)->where('bid',bid)->where('id',$info['id'])->update($info);
			\app\commons\System::plog('编辑预约服务类型'.$info['id']);
		}else{
			$info['aid'] = aid;
			$info['bid'] = bid;
			$info['createtime'] = time();
			$id = Db::name('yuyue_category')->insertGetId($info);
			\app\commons\System::plog('添加预约服务类型'.$id);
		}
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}
	//改状态
	public function setst(){
		$st = input('post.st/d');
		$ids = input('post.ids/a');
		Db::name('yuyue_category')->where('aid',aid)->where('bid',bid)->where('id','in',$ids)->update(['status'=>$st]);
		\app\commons\System::plog('预约服务类型改状态'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//删除
	public function del(){
		$ids = input('post.ids/a');
		Db::name('yuyue_category')->where('aid',aid)->where('bid',bid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('预约服务类型删除'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}


	//同步分类  红蚂蚁定制
	public function tongbu(){
		$url = 'http://shifu.api.kkgj123.cn/api/1/category/list';
		$config = include(ROOT_PATH.'config.php');
		$appId=$config['hmyyuyue']['appId'];
		$appSecret=$config['hmyyuyue']['appSecret'];
		$headrs = array('appid:'.$appId,'appSecret:'.$appSecret);
		$res = curl_get($url,'',$headrs);
		$res = json_decode($res,true);
		if($res['code']==200){
			//原来的分类数据
			$catearr = [];
			
			foreach($res['data'] as $v){
				$catearr[] = $v['id'];
				$data = array();
				$data['aid'] = aid;
				$data['bid'] = bid;
				$data['name'] = $v['name'];
				$data['appid'] = $v['id'];
				$data['pic'] = $v['image'];
				$data['createtime'] = time();
				$rs = Db::name('yuyue_category')->where('aid',aid)->where('bid',bid)->where('appid',$v['id'])->find();
				if($rs){
					Db::name('yuyue_category')->where('aid',aid)->where('bid',bid)->where('appid',$v['id'])->update($data);
				}else{
					Db::name('yuyue_category')->insert($data);
				}
				if($v['children']){

					foreach($v['children'] as $d){
						$catearr[] = $d['id'];
						$data2 = array();
						$data2['aid'] = aid;
						$data2['bid'] = bid;
						$data2['name'] = $d['name'];
						$data2['appid'] = $d['id'];
						$data2['pic'] = $d['image'];
						$data2['pid'] = $d['parentId'];
						$data2['createtime'] = time();
						$rs1 = Db::name('yuyue_category')->where('aid',aid)->where('bid',bid)->where('appid',$d['id'])->find();
						if($rs1){
							Db::name('yuyue_category')->where('aid',aid)->where('bid',bid)->where('appid',$d['id'])->update($data2);
						}else{
							Db::name('yuyue_category')->insert($data2);
						}
					}
					//Db::name('yuyue_category')->where('aid',aid)->where('pid','>','0')->where('appid','not in',$catearr)->delete();
				}
				//var_dump($catearr);
			}
			Db::name('yuyue_category')->where('aid',aid)->where('bid',bid)->where('appid','not in',$catearr)->delete();
			return json(['status'=>1,'msg'=>'同步成功']);
			\app\commons\System::plog('同步预约服务类型');
		}else{
			return json(['status'=>0,'msg'=>'获取数据失败，错误信息：'.$res['msg']]);
		}
	}
    function defaultSet(){
        $set = Db::name('yuyue_set')->where('aid',aid)->where('bid',bid)->find();
        if(!$set){
            Db::name('yuyue_set')->insert(['aid'=>aid,'bid' => bid]);
        }
    }
}
