<?php


// +----------------------------------------------------------------------
// | 商城 商城设置
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class Douyin extends Common
{
    public function initialize(){
		parent::initialize();
		if(bid > 0) showmsg('无访问权限');
	}
	//所有类目
	public function allcategory(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			$rs = \app\commons\Douyin::get_shop_category(aid,0);
			
			$data = [];
			foreach($rs['data'] as $k=>$v){
				if($v['is_leaf']){
					$v['title'] = $v['name'];
					$data[] = $v;
				}else{
					$rs2 = \app\commons\Douyin::get_shop_category(aid,$v['id']);
					foreach($rs2['data'] as $k2=>$v2){
						$v2['title'] = $v['name'].'>'.$v2['name'];
						if($v2['is_leaf']){
							$data[] = $v2;
						}else{
							$rs3 = \app\commons\Douyin::get_shop_category(aid,$v2['id']);
							foreach($rs3['data'] as $k3=>$v3){
								$v3['title'] = $v['name'].'>'.$v2['name'].'>'.$v3['name'];
								$data[] = $v3;
							}
						}
					}
				}
			}

			
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		
		return View::fetch();
	}
	

	//资质列表
    public function qualityList(){
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
			if(input('param.name')) $where[] = ['quality_name','like','%'.input('param.name').'%'];
			if(input('?param.status') && input('param.status')!=='') $where[] = ['status','=',input('param.status')];
			//dump($where);
			$count = 0 + Db::name('douyin_quality_list')->where($where)->count();
			$data = Db::name('douyin_quality_list')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
    }
	//编辑
	public function qualityEdit(){
		if(input('param.id')){
			$info = Db::name('douyin_quality_list')->where('aid',aid)->where('id',input('param.id/d'))->find();
		}else{
			$info = array('id'=>'');
		}
		$pcatelist = Db::name('douyin_quality_list')->where('aid',aid)->order('sort desc,id')->select()->toArray();
		View::assign('info',$info);
		return View::fetch();
	}
	//保存
	public function qualitySave(){
		$info = input('post.info/a');
		if($info['id']){
			Db::name('douyin_quality_list')->where('aid',aid)->where('id',$info['id'])->update($info);
		}else{
			$info['aid'] = aid;
			$info['createtime'] = time();
			$id = Db::name('douyin_quality_list')->insertGetId($info);
		}
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('qualityList')]);
	}
	//删除
	public function qualityDel(){
		$ids = input('post.ids/a');
		Db::name('douyin_quality_list')->where('aid',aid)->where('id','in',$ids)->delete();
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	//选择资质信息
	public function qualityChoose(){
		return View::fetch();
	}
}