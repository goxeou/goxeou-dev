<?php


// +----------------------------------------------------------------------
// | 菜品分类
// +----------------------------------------------------------------------

namespace app\controllers;


use think\facade\View;
use app\models\RestaurantProductCategoryModel as Category;
use think\facade\Db;

class RestaurantProductCategory extends Common
{
    public function initialize()
    {
        parent::initialize();
    }

    public function index()
    {
        if (request()->isAjax()) {
            $page = input('param.page');
            $limit = input('param.limit');

            if (input('param.field') && input('param.order')) {
                $order = input('param.field') . ' ' . input('param.order');
            } else {
                $order = 'sort desc,id desc';
            }
            $where[] = ['aid', '=', aid];
            $where[] = ['bid', '=', bid];
            $data = [];
            $count = (new Category())->where($where)->count();
            $data = (new Category())->getList($where, $page, $limit, $order);
            return json(['code' => 0, 'msg' => '查询成功', 'count' => $count, 'data' => $data]);
        }

        return View::fetch();
    }

    public function edit()
    {

        if(input('param.id')){
            $info = Db::name('restaurant_product_category')->where('aid',aid)->where('bid',bid)->where('id',input('param.id/d'))->find();
            }else{
            $info = array('id'=>'');
        }

        View::assign('info',$info);
        return View::fetch();
    }

    //保存
    public function save(){

        $info = input('post.info/a');
        if($info['id']){
            Db::name('restaurant_product_category')->where('aid',aid)->where('bid',bid)->where('id',$info['id'])->update($info);
            \app\commons\System::plog(sprintf('编辑餐饮菜品分类:%s[%s]', $info['name'], $info['id']));
        }else{
            $info['aid'] = aid;
            $info['bid'] = bid;
//            $info['create_time'] = time();
            $category = new Category();
            $category->save($info);
            \app\commons\System::plog(sprintf('添加餐饮菜品分类:%s[%s]', $category->name, $category->id));
        }
        return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
    }
    //删除
    public function del(){
        $ids = input('post.ids/a');
        //todo 有菜品无法删除
        Db::name('restaurant_product_category')->where('aid',aid)->where('bid',bid)->where('id','in',$ids)->delete();
        \app\commons\System::plog('删除餐饮菜品分类'.implode(',',$ids));
        return json(['status'=>1,'msg'=>'删除成功']);
    }
	
	//选择分类弹窗
    public function choosecategory(){
		if(request()->isAjax()){
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
				$order = 'sort desc,id';
			}
			$where = [];
			$where[] = ['aid','=',aid];
			$data = [];
			$cate0 = Db::name('restaurant_product_category')->where('aid',aid)->where('bid',bid)->where('pid',0)->order($order)->select()->toArray();
			foreach($cate0 as $c0){
				$c0['showname'] = $c0['name'];
				$c0['deep'] = 0;
				$data[] = $c0;
				$cate1 = Db::name('restaurant_product_category')->where('aid',aid)->where('bid',bid)->where('pid',$c0['id'])->order($order)->select()->toArray();
				foreach($cate1 as $k1=>$c1){
					if($k1 < count($cate1)-1){
						$c1['showname'] = '<span style="color:#aaa">&nbsp;&nbsp;&nbsp;&nbsp;├ </span>'.$c1['name'];
					}else{
						$c1['showname'] = '<span style="color:#aaa">&nbsp;&nbsp;&nbsp;&nbsp;└ </span>'.$c1['name'];
					}
					$c1['deep'] = 1;
					$data[] = $c1;
					$cate2 = Db::name('restaurant_product_category')->where('aid',aid)->where('bid',bid)->where('pid',$c1['id'])->order($order)->select()->toArray();
					foreach($cate2 as $k2=>$c2){
						if($k2 < count($cate2)-1){
							$c2['showname'] = '<span style="color:#aaa">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;├ </span>'.$c2['name'];
						}else{
							$c2['showname'] = '<span style="color:#aaa">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;└ </span>'.$c2['name'];
						}
						$c2['deep'] = 2;
						$data[] = $c2;
					}
				}
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
    }
}