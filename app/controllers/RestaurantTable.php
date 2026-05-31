<?php


// +----------------------------------------------------------------------
// | 餐桌
// +----------------------------------------------------------------------
namespace app\controllers;

use app\models\RestaurantTableCategoryModel as Category;
use app\models\RestaurantTableModel;
use app\service\RestaurantTableService;
use think\facade\Db;
use think\facade\View;

class RestaurantTable extends Common
{
    public function initialize(){
        parent::initialize();
    }

    //餐桌
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
            if(input('param.name')) $where[] = ['name','like','%'.$_GET['name'].'%'];
            $where[] = ['aid', '=', aid];
            $where[] = ['bid', '=', bid];
            $data = [];
            $data = (new RestaurantTableModel())->getList($where, $page, $limit, $order);

            return json(['code' => 0, 'msg' => '查询成功', 'count' => $data['count'], 'data' => $data['list']]);
        }

        View::assign('bid',bid);
        $where[] = ['aid', '=', aid];
        $where[] = ['bid', '=', bid];
        $tableCategory = (new Category())->getList($where, 1, 'all');
        View::assign('tableCategory',$tableCategory);
        $printArr = Db::name('wifiprint_set')->where('aid',aid)->where('bid',bid)->order('id')->column('name','id');
        View::assign('printArr',$printArr);
        return View::fetch();
    }

    public function edit()
    {
        if(input('param.id')){
            $info = RestaurantTableModel::with('category')->where('aid',aid)->where('bid',bid)->where('id',input('param.id/d'))->find();
            }else{
            $info = array('id'=>'','canbook' => 1);
        }

        View::assign('info',$info);
        //分类
        $where[] = ['aid', '=', aid];
        $where[] = ['bid', '=', bid];
        $tableCategory = (new Category())->getList($where, 1, 'all');
        View::assign('tableCategory',$tableCategory);

        $printArr = Db::name('wifiprint_set')->where('aid',aid)->where('bid',bid)->order('id')->column('name','id');
        View::assign('printArr',$printArr);

        return View::fetch();
    }

    //保存
    public function save(){
        $info = input('post.info/a');
        $info['print_ids'] = implode(',',$info['print_ids']);
        if($info['id']){
            $table = Db::name('restaurant_table')->where('aid',aid)->where('bid',bid)->where('id',$info['id'])->find();
            if(empty($table)) {
                return json(['status'=>0,'msg'=> '不存在的信息']);
            }
            if($info['status'] == 3) {
                $info['orderid'] = 0;
            }

//            if(empty($table['qrcode'])) {
//                //二维码 参数格式：id_1-cid_2
//                $path = 'pages/restaurant/fastbuy';
//                $scene = ['tableId' => $info['id']];
//                $qrcode = \app\commons\Wechat::getQRCode(aid,'wx',$path,$scene);
//                if ($qrcode['status'] == 1 && $qrcode['url']) {
//                    $info['qrcode'] = $qrcode['url'];
//                } else {
//                    Db::name('restaurant_table')->where('aid',aid)->where('id',$info['id'])->update($info);
//                    return json(['status'=>0,'msg'=>$qrcode['msg']]);
//                }
//            }
            //设置空闲和清台 更新进行中的订单
            if($table['orderid'] && $info['status'] != $table['status'] && ($info['status'] == 0 || $info['status'] == 3)) {
                $order = Db::name('restaurant_shop_order')->where('aid',aid)->where('bid',bid)->where('tableid',$info['id'])
                    ->where('id', $table['orderid'])->find();
                if($order){
                    if($order['status'] != 3 && $order['status'] != 4 && $order['totalprice'] > 0){
                        return json(['status'=>0,'msg'=>'请先完成点餐订单:'.$order['ordernum'].'的结算，才可以修改餐桌状态为清台或空闲']);
                    }
                    Db::name('restaurant_table')->where('aid',aid)->where('bid',bid)->where('id',$info['id'])->update(['orderid' => 0]);
                }
            }
            Db::name('restaurant_table')->where('aid',aid)->where('bid',bid)->where('id',$info['id'])->update($info);

            \app\commons\System::plog(sprintf('编辑餐饮餐桌:%s[%s]', $info['name'], $info['id']));
        }else{
            $info['aid'] = aid;
            $info['bid'] = bid;
            $table = new RestaurantTableModel();
            $table->save($info);
            //二维码 参数格式：id_1-cid_2
//            $path = 'pages/restaurant/fastbuy';
//            $scene = ['tableId' => $info['id']];
//            $qrcode = \app\commons\Wechat::getQRCode(aid,'wx',$path,$scene);
//            if ($qrcode['status'] == 1 && $qrcode['url']) {
//                $table = new RestaurantTableModel();
//                $table->where('id',$info['id'])->where('aid',aid)->save(['qrcode' => $qrcode['url']]);
//            } else {
//                return json(['status'=>0,'msg'=>$qrcode['msg']]);
//            }

            \app\commons\System::plog(sprintf('添加餐饮餐桌:%s[%s]', $table->name, $table->id));
        }
        return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
    }
    //删除
    public function del(){
        $ids = input('post.ids/a');

        Db::name('restaurant_table')->where('aid',aid)->where('bid',bid)->where('id','in',$ids)->delete();
        \app\commons\System::plog('删除餐饮餐桌'.implode(',',$ids));
        return json(['status'=>1,'msg'=>'删除成功']);
    }

    public function createTables(){
        $makecount = input('param.makecount',0);
        $cid = input('param.cid',0);
        $seat = input('param.seat');
        $prefix = input('param.prefix');
        $not_have_number = input('param.not_have_number');
        $canbook = input('param.canbook',0);
        $print_ids =input('param.print_ids');
        $print_ids_arr = implode(',',$print_ids);
        if($makecount <=0){
            return json(['status'=>0,'msg'=>'请输入桌台数量']);
        }
        if(!$cid){
            return json(['status'=>0,'msg'=>'请选择分类']);
        }
        $not_have_number_arr = explode(',',$not_have_number);
        $table_number = [];
        $start = input('param.start',1);
      
        while (count($table_number) < $makecount){
            $have = $this-> getIsInNotNumber($start,$not_have_number_arr);
            if($have){
                $start ++;
                continue;
            }
            $table_number[] = $start;
            $start ++;
        }
        $table_insert = [];
        foreach($table_number as $key=>$val){
            $val = $val<10?'0'.$val:$val;
             $insert = [
                'aid' => aid,
                'bid' =>bid,
                'cid' => $cid,
                'name'=> $prefix.$val,
                'seat' =>$seat,
                'status' =>0,
                'print_ids' =>$print_ids_arr,
                'create_time' => time(),
                'canbook' =>$canbook?$canbook:0
            ];
            $table_insert[] = $insert;
        }
        Db::name('restaurant_table')->insertAll($table_insert);
        return json(['status'=>1,'msg'=>'创建成功']);
    }
    public function getIsInNotNumber($str,$not_have_number_arr){
        if(!$not_have_number_arr){  
            return false;
        }
        for($i = 0;$i < count($not_have_number_arr);$i++){
            $is_have = strstr($str,$not_have_number_arr[$i]);
            if($is_have !==false){
                return true;
            }
        }
    }
}