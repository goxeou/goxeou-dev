<?php


// +----------------------------------------------------------------------
// | 导入收货地址
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;
use app\commons\Wechat;
class MemberAddressdr extends Common
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

			$indata = [];
			$indata['aid'] = aid;
			$indata['mid'] = trim($data[0]);
			$indata['name'] = trim($data[1]);
			$indata['tel'] = trim($data[2]);
			$indata['province'] = trim($data[3]);
			$indata['city'] = trim($data[4]);
			$indata['district'] = trim($data[5]);
			$indata['area'] = $indata['province'].$indata['city'].$indata['district'];
			$indata['address'] = trim($data[6]);
			$indata['createtime'] = time();
			if(!$indata['mid']) continue;
			Db::name('member_address')->insert($indata);

			//var_dump($data);
			//var_dump($product);
			//var_dump($guige);
			//var_dump($ggstock);

			$updatenum++;
		}
		\app\commons\System::plog('导入会员收货地址');
		return json(['status'=>1,'msg'=>'成功导入'.$updatenum.'条数据']);
	}

}
