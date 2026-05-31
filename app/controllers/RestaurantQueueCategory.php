<?php


// +----------------------------------------------------------------------
// | 队列分类
// +----------------------------------------------------------------------

namespace app\controllers;

use think\facade\View;
use app\models\RestaurantQueueCategoryModel as Category;
use think\facade\Db;

class RestaurantQueueCategory extends Common
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
            }
            $where[] = ['aid', '=', aid];
            $where[] = ['bid', '=', bid];
            $data = [];
            $data = (new Category())->getList($where, $page, $limit, $order);

            return json(['code' => 0, 'msg' => '查询成功', 'count' => count($data), 'data' => $data]);
        }
        $this->defaultSet();
        return View::fetch();
    }

    public function edit()
    {
        if(input('param.id')){
            $info = Db::name('restaurant_queue_category')->where('aid',aid)->where('bid',bid)->where('id',input('param.id/d'))->find();
        }else{
            $info = array('id'=>'','per_minute'=>1,'notice_text'=>'请[排队号]号顾客前往就餐');
        }

        View::assign('info',$info);
        return View::fetch();
    }

    //保存
    public function save(){

        $info = input('post.info/a');
        if($info['id']){
            Db::name('restaurant_queue_category')->where('aid',aid)->where('bid',bid)->where('id',$info['id'])->update($info);
            \app\commons\System::plog(sprintf('编辑餐饮队列分类:%s[%s]', $info['name'], $info['id']));
        }else{
            $info['aid'] = aid;
            $info['bid'] = bid;
//            $info['create_time'] = time();
            $category = new Category();
            $category->save($info);
            \app\commons\System::plog(sprintf('添加餐饮队列分类:%s[%s]', $category->name, $category->id));
        }
        return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
    }
    //删除
    public function del(){
        $ids = input('post.ids/a');
        //todo 排队无法删除
        Db::name('restaurant_queue_category')->where('aid',aid)->where('bid',bid)->where('id','in',$ids)->delete();
        \app\commons\System::plog('删除餐饮队列分类'.implode(',',$ids));
        return json(['status'=>1,'msg'=>'删除成功']);
    }
    function defaultSet(){
        $set = Db::name('restaurant_queue_sysset')->where('aid',aid)->where('bid',bid)->find();
        if(!$set){
            $insert = ['aid'=>aid,'bid'=>bid,'screen_pic'=>PRE_URL.'/static/img/restaurant_queue_bg.jpg'];
            Db::name('restaurant_queue_sysset')->insert($insert);
        }
    }
}