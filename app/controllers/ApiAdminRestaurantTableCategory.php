<?php


//管理员中心 - 餐桌分类
namespace app\controllers;
use think\facade\Db;
class ApiAdminRestaurantTableCategory extends ApiAdmin
{
	public function index(){
		$where = ['aid'=>aid,'bid'=>bid];

		$pernum = 20;
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
        if(input('post.status')) $where[] = ['status', input('post.status')];
		$datalist = Db::name('restaurant_table_category')->where($where)->page($pagenum,$pernum)->order('sort desc,id desc')->select();
		if(!$datalist) $datalist = array();

		$rdata = [];
        $rdata['status'] = 1;
		$rdata['datalist'] = $datalist;
		return $this->json($rdata);
	}

    //编辑
    public function edit(){
        if(input('param.id')){
            $info = Db::name('restaurant_table_category')->where('aid',aid)->where('bid',bid)->where('id',input('param.id/d'))->find();
        }else{
            $info = ['id'=>'','status'=>1, 'service_fee' => 0,'limit_fee' =>0,'booking_fee'=>0, 'sort' => 0];
        }

        $rdata = [];
        $rdata['info'] = $info;
        return $this->json($rdata);
    }

    //编辑
    public function save(){
        if(input('post.id')) $cate = Db::name('restaurant_table_category')->where('aid',aid)->where('bid',bid)->where('id',input('post.id/d'))->find();
        $info = input('post.info/a');
        $data = array();
        $data['name'] = $info['name'];
        $data['pic'] = $info['pic'];
        $data['service_fee'] = $info['service_fee'];
        $data['limit_fee'] = $info['limit_fee'];
        $data['booking_fee'] = $info['booking_fee'];
        $data['seat'] = $info['seat'];
        $data['sort'] = $info['sort'];
        $data['status'] = $info['status'];

        if($cate){
            $data['update_time'] = time();
            Db::name('restaurant_table_category')->where('aid',aid)->where('bid',bid)->where('id',$cate['id'])->update($data);
            $id = $cate['id'];
            \app\commons\System::plog('餐饮餐桌分类编辑'.$id);
        }else{
            $data['aid'] = aid;
            $data['bid'] = bid;
            $data['create_time'] = time();
            $id = Db::name('restaurant_table_category')->insertGetId($data);
            \app\commons\System::plog('餐饮餐桌分类编辑'.$id);
        }

        return json(['status'=>1,'msg'=>'操作成功']);
    }

    //删除
    public function del(){
        $id = input('post.id/d');
        Db::name('restaurant_table_category')->where(['aid'=>aid,'bid'=>bid,'id'=>$id])->delete();
        Db::name('restaurant_table')->where(['aid'=>aid,'bid'=>bid,'cid'=>$id])->delete();
        return $this->json(['status'=>1,'msg'=>'操作成功']);
    }
}