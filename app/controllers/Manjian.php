<?php


// +----------------------------------------------------------------------
// | 满减活动
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class Manjian extends Common
{
    public function initialize(){
		parent::initialize();
		if(bid > 0) showmsg('无访问权限');
	}
	public function set(){
		if(request()->isAjax()){
			$info = input('post.info/a');
			$mjdata = array();
			$postmoney = input('post.money/a');
			$postjian = input('post.jian/a');
			foreach($postmoney as $k=>$money){
				$mjdata[] = array(
					'money'=>$money,
					'jian'=>$postjian[$k]
				);
			}
			$info['mjdata'] = json_encode($mjdata,JSON_UNESCAPED_UNICODE);
			if(Db::name('manjian_set')->where('aid',aid)->find()){
				Db::name('manjian_set')->where('aid',aid)->update($info);
				\app\commons\System::plog('满减活动设置');
			}else{
				$info['aid'] = aid;
				$info['createtime'] = time();
				Db::name('manjian_set')->insert($info);
			}
			return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('set')]);
		}
		$info = Db::name('manjian_set')->where('aid',aid)->find();
		View::assign('info',$info);
		
		$categorydata = array();
        if($info && $info['categoryids']){
            $categorydata = Db::name('shop_category')->where('aid',aid)->where('id','in',$info['categoryids'])->order('sort desc,id')->select()->toArray();
        }
        View::assign('categorydata',$categorydata);
        $productdata = array();
        if($info && $info['productids']){
            $info['productids'] = array_filter(explode(',',$info['productids']));
            $info['productids'] = implode(',',$info['productids']);
            $order = Db::raw('field(id,'.$info['productids'].')');
            $productdata = Db::name('shop_product')->where('aid',aid)->where('id','in',$info['productids'])->order($order)->select()->toArray();
        }
        View::assign('productdata',$productdata);
        
        return View::fetch();
	}
}
