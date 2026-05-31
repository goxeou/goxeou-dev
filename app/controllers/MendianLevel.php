<?php
// +----------------------------------------------------------------------
// | 门店 门店等级     custom_file(mendian_upgrade)
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class MendianLevel extends Common
{
    public function initialize(){
		parent::initialize();

        $request = request();
        $action = $request->action();
        if($action != 'chooselevel')
		    if(bid > 0) showmsg('无访问权限');
	}
	//列表
    public function index(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
				$order = 'sort,id';
			}
			$where = [['aid','=',aid]];
			$count = 0 + Db::name('mendian_level')->where($where)->count();
			$data = Db::name('mendian_level')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			foreach($data as $k=>$v){
				if($v['can_up']){
					$tj = array();
					//if($v['up_ordercount'] > 0) $tj[]='订单满'.$v['up_ordercount'].'个';
					if($v['up_ordermoney'] > 0) $tj['up_ordermoney']='订单金额满'.$v['up_ordermoney'].'元';
					if($tj){
					  $data[$k]['uptj'] = $tj['up_ordermoney'];
					}else{
						$data[$k]['uptj'] = '不自动升级';
					}
				}else{
					$data[$k]['uptj'] = '不自动升级';
					if($v['isdefault']){
						$data[$k]['uptj'] = '默认等级无需升级';
					}
				}
				$data[$k]['commission'] = $v['commission'].($v['commissiontype']==1?'元':'%');
			}

			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}

		$haslevel = Db::name('mendian_level')->where('aid',aid)->where('isdefault',1)->find();
		if(!$haslevel){
			Db::name('mendian_level')->insert(array('aid'=>aid,'isdefault'=>1,'name'=>'默认等级','commissiontype'=>1,'commission'=>0));
		}
		return View::fetch();
	}
	//编辑
	public function edit(){
		if(input('param.id')){
			$info = Db::name('mendian_level')->where('aid',aid)->where('id',input('param.id/d'))->find();
		}else{
			$info['sort'] = 1 + Db::name('mendian_level')->where('aid',aid)->max('sort');
			$level = 1 + Db::name('mendian_level')->where('aid',aid)->count();
		}



		View::assign('info',$info);
        return View::fetch();
	}
	public function save(){
		$info = input('post.info/a');
		if($info['id']){
			Db::name('mendian_level')->where('aid',aid)->where('id',$info['id'])->update($info);
			\app\commons\System::plog('编辑门店等级'.$info['id']);
		}else{
			$info['aid'] = aid;
			$info['createtime'] = time();
			$id = Db::name('mendian_level')->insertGetId($info);
			\app\commons\System::plog('添加门店等级'.$id);
		}
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}
	//删除
	public function del(){
		$ids = input('post.ids/a');
		Db::name('mendian_level')->where('aid',aid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('删除门店等级'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
}
