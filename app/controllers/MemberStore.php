<?php

namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class MemberStore extends Common
{
	//
    public function index(){
		if(request()->isAjax()){
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
				$order = 'sort,id';
			}
			$where = [];
			$where[] = ['aid','=',aid];
			$data = [];
			$data = Db::name('member_store')->where('aid',aid)->order($order)->select()->toArray();
			foreach($data as $k=>$v){
             	$tj = array();
             		
             
             	if($v['up_self_storeid']) $tj['up_self_storeid']='自身会员等级达['.Db::name('member_level')->where('id',$v['up_self_storeid'])->value('name').']';
				if($v['up_fxdowncount'] > 0) $tj['up_fxdowncount']='下级总人数满'.$v['up_fxdowncount'].'个['. Db::name('member_level')->where('id',$v['up_fxdownlevelid'])->value('name').']';
				
				if($v['up_proid'] > 0 && $v['up_pronum'] > 0) $tj['up_proid']='购买商品['.Db::name('shop_product')->where('id',$v['up_proid'])->value('name').']*'.$v['up_pronum'];
				if($v['up_fxdowncount2'] > 0) $tj['up_fxdowncount2']='下级总人数满'.$v['up_fxdowncount2'].'个['. Db::name('member_level')->where('id',$v['up_fxdownlevelid2'])->value('name').']';
				if($tj){
				    $i = 0;
                    $data[$k]['uptj'] = '';
				    foreach($tj as $key => $item) {
				        if($i > 0) {
                            $data[$k]['uptj'] .= ' 且 '.$item;
                        }else {
                             $data[$k]['uptj'] .= ' '.$item;
                        }
                        $i++;
                    }
				}
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>count($data),'data'=>$data]);
		}
		return View::fetch();
    }
	//编辑
	public function edit(){
		if(input('param.id')){
			$info = Db::name('member_store')->where('aid',aid)->where('id',input('param.id/d'))->find();
		}else{
			$info = array('id'=>'','rate1'=>0);
		}
	
		View::assign('info',$info);
		return View::fetch();
	}
	//保存
	public function save(){
		$info = input('post.info/a');
		if($info['id']){
			Db::name('member_store')->where('aid',aid)->where('id',$info['id'])->update($info);
            \app\commons\System::plog('编辑会员团队等级'.$info['id']);
		}else{
			$info['aid'] = aid;
			$info['createtime'] = time();
			$id = Db::name('member_store')->insertGetId($info);
            \app\commons\System::plog('添加会员团队等级'.$id);
		}
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}
	//删除
	public function del(){
		$ids = input('post.ids/a');
		if(empty($ids)) return json(['status'=>0,'msg'=>'请选择要删除的数据']);
	 	Db::name('member_store')->where('aid',aid)->where('id','in',$ids)->delete();
        \app\commons\System::plog('删除团队等级'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
}