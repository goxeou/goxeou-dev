<?php


// +----------------------------------------------------------------------
// | 导入库存
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;
use app\commons\Wechat;
class ShopKucundr extends Common
{
	//
	public function index(){
		return View::fetch();	
	}
	//导入
	public function importexcel(){
		set_time_limit(0);
		ini_set('memory_limit',-1);
		$file = input('post.upload_file');
		if(!$file) return json(['status'=>0,'msg'=>'请上传excel文件']);
		$exceldata = $this->import_excel($file);
		$updatenum = 0;
		foreach($exceldata as $data){
			$procode = trim($data[1]);
			if(!$procode) continue;
			$ggname = trim($data[4]);
			$ggstock = intval(trim($data[5]));
			$product = Db::name('shop_product')->where('aid',aid)->where('bid',bid)->where('procode',$procode)->find();
			if(!$product) continue;
			$guige = Db::name('shop_guige')->where('proid',$product['id'])->where('name',$ggname)->find();
			if(!$guige) $guige = Db::name('shop_guige')->where('proid',$product['id'])->find();
			if(!$guige) continue;
			
			Db::name('shop_guige')->where('id',$guige['id'])->update(['stock'=>$ggstock]);

			$prostock = Db::name('shop_guige')->where('proid',$product['id'])->sum('stock');
			Db::name('shop_product')->where('id',$product['id'])->update(['stock'=>$prostock]);

			//var_dump($data);
			//var_dump($product);
			//var_dump($guige);
			//var_dump($ggstock);

			$updatenum++;
		}
		\app\commons\System::plog('导入商品库存');
		return json(['status'=>1,'msg'=>'成功更新'.$updatenum.'条数据']);
	}

}
