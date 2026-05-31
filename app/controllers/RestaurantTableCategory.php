<?php


// +----------------------------------------------------------------------
// | 餐桌分类
// +----------------------------------------------------------------------

namespace app\controllers;


use think\facade\View;
use app\models\RestaurantTableCategoryModel as Category;
use think\facade\Db;

class RestaurantTableCategory extends Common
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
            $data = (new Category())->getList($where, $page, $limit, $order);

            return json(['code' => 0, 'msg' => '查询成功', 'count' => count($data), 'data' => $data]);
        }

        return View::fetch();
    }

    public function edit()
    {

        if(input('param.id')){
            $info = Db::name('restaurant_table_category')->where('aid',aid)->where('bid',bid)->where('id',input('param.id/d'))->find();
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
            Db::name('restaurant_table_category')->where('aid',aid)->where('bid',bid)->where('id',$info['id'])->update($info);
            \app\commons\System::plog(sprintf('编辑餐饮餐桌分类:%s[%s]', $info['name'], $info['id']));
        }else{
            $info['aid'] = aid;
            $info['bid'] = bid;
//            $info['create_time'] = time();
            $category = new Category();
            $category->save($info);
            \app\commons\System::plog(sprintf('添加餐饮餐桌分类:%s[%s]', $category->name, $category->id));
        }
        return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
    }
    //删除
    public function del(){
        $ids = input('post.ids/a');
        //todo 有餐桌无法删除
        Db::name('restaurant_table_category')->where('aid',aid)->where('bid',bid)->where('id','in',$ids)->delete();
        \app\commons\System::plog('删除餐饮餐桌分类'.implode(',',$ids));
        return json(['status'=>1,'msg'=>'删除成功']);
    }
}