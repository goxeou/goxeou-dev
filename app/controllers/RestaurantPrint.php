<?php


// +----------------------------------------------------------------------
// | 餐饮餐厅区域
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class RestaurantPrint extends Common
{
	//列表
    public function index(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
				$order = 'id';
			}
			$where = array();
			$where[] = ['aid','=',aid];
			$where[] = ['bid','=',bid];
			if(input('param.name')) $where[] = ['name','like','%'.input('param.name').'%'];
			$count = 0 + Db::name('restaurant_area')->where($where)->count();
			$data = Db::name('restaurant_area')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
    }
	//编辑
	public function edit(){
        $info = Db::name('restaurant_area')->where('aid',aid)->where('bid',bid)->where('id',input('param.id/d'))->find();
        $printArr = Db::name('wifiprint_set')->where('aid',aid)->where('bid',bid)->order('id')->column('name','id');
        View::assign('printArr',$printArr);
		View::assign('info',$info);
		return View::fetch();
	}
	public function save(){
		$info = input('post.info/a');
		$info['print_ids'] = implode(',',$info['print_ids']);
        if($info['id']){
			Db::name('restaurant_area')->where('aid',aid)->where('id',$info['id'])->update($info);
			\app\commons\System::plog('编辑餐厅区域'.$info['id']);
		}else{
			$info['aid'] = aid;
			$info['bid'] = bid;
			$info['createtime'] = time();
			$id = Db::name('restaurant_area')->insertGetId($info);
			\app\commons\System::plog('添加餐厅区域'.$id);
		}
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}

	//改状态
	public function setst(){
		$st = input('post.st/d');
		$ids = input('post.ids/a');
		Db::name('restaurant_area')->where('aid',aid)->where('id','in',$ids)->update(['status'=>$st]);
		\app\commons\System::plog('餐厅区域改状态'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//删除
	public function del(){
		$ids = input('post.ids/a');
		Db::name('restaurant_area')->where('aid',aid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('餐厅区域删除'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	//打印测试
	public function printtest(){
		$id = input('post.id/d');
		$area = Db::name('restaurant_area')->where('aid',aid)->where('id',$id)->find();
		if(!$area){
			return json(['status'=>0,'error'=>1,'msg'=>'未找到信息']);
		}
		if(empty($area['print_ids'])) {
            return json(['status'=>0,'error'=>1,'msg'=>'未找到关联打印机']);
        }
        $machineList = Db::name('wifiprint_set')->where('aid',aid)->where('bid',bid)->whereFindInSet('id',$area['print_ids'])->select()->toArray();
		foreach ($machineList as $machine) {
			$num = 1;
            if(getcustom('sys_print_set')){
            	//打印次数
                $num =  $machine['print_num']?$machine['print_num']:1;
            }
            for($i=0;$i<$num;$i++){
	            $content = \app\customs\Restaurant::restaurantPrintContent($machine['title'],$machine['type'],$area, 'test',$machine);
	            if($machine['type']==0 && $content){
	                $rs = \app\commons\Wifiprint::yilianyun_print($machine['client_id'],$machine['client_secret'],$machine['access_token'],$machine['machine_code'],$machine['msign'],$content);
	                return json($rs);
	            }elseif($machine['type']==1 && $content){
	                $rs = \app\commons\Wifiprint::feie_print($machine['client_id'],$machine['client_secret'],$machine['machine_code'],$machine['msign'],$content,$machine['machine_type']);
	                return json($rs);
	            } elseif($machine['type']==4 && $content){
                    $voice = 1;//默认静音
                    $rs = \app\commons\Wifiprint::xinye_print($machine['client_id'],$machine['client_secret'],$machine['machine_code'],$content,$voice,$machine['machine_type']);
                    return json($rs);
                }
	            
            }
        }
		return json(['status'=>0,'msg'=>'未找到打印机类型']);
	}
}
