<?php

//decode by http://www.yunlu99.com/
namespace app\controllers;

class Base extends \app\BaseController
{
	public $aid;
	public $uid;
	public $user;
	public $mdid = 0;
	public $auth_data = "all";
	public $platform = "mp";
	public $xcxaid = 0;
	public function initialize()
	{
	    
	    require('upgrade_self.php');
		$request = request();
		if (!in_array($request->controller(), ["login"]) && !session("?ADMIN_LOGIN")) {
			header("Location:" . \strval(url("login/index")));
			exit;
		}
		$this->aid = session("ADMIN_AID");
		if (MN == "business") {
			$this->uid = session("ADMIN_AUTH_UID");
			$this->bid = session("ADMIN_AUTH_BID");
		} else {
			$this->uid = session("ADMIN_UID");
			$this->bid = session("ADMIN_BID");
		}
		define("aid", $this->aid);
		define("bid", $this->bid);
		define("uid", $this->uid);
		$user = \think\facade\Db::name("admin_user")->where("id", $this->uid)->find();
		if ($user && $user["groupid"]) {
			$group = \think\facade\Db::name("admin_user_group")->where("id", $user["groupid"])->find();
			$user["auth_data"] = $group["auth_data"];
			$user["wxauth_data"] = $group["wxauth_data"];
			$user["notice_auth_data"] = $group["notice_auth_data"];
			$user["hexiao_auth_data"] = $group["hexiao_auth_data"];
			$user["mdid"] = $group["mdid"];
			$user["showtj"] = $group["showtj"];
		}
		$this->user = $user;
		$this->mdid = $user ? $user["mdid"] : null;
		if ($user && $user["auth_type"] == 0 && !session("BST_ID")) {
			$auth_data = json_decode($user["auth_data"], true) ?: [];
			$auth_path = \app\commons\Menu::blacklist();
			foreach ($auth_data as $v) {
				$auth_path = array_merge($auth_path, explode(",", $v));
			}
			$thispath = $request->controller() . "/" . $request->action();
			if (!in_array($request->controller() . "/*", $auth_path) && !in_array($thispath, $auth_path)) {
			 echojson(['status'=>0,'msg'=>'无访问权限!']);die();
				exit("无访问权限");
			}
			$this->auth_data = $auth_path;
		} else {
			$this->auth_data = "all";
		}
		\think\facade\View::assign("aid", $this->aid);
		\think\facade\View::assign("bid", $this->bid);
		\think\facade\View::assign("auth_data", $this->auth_data);
		$platform = \app\commons\Common::getplatform(aid);
		$this->platform = $platform;
		\think\facade\View::assign("platform", $platform);
		$admin = \think\facade\Db::name("admin")->where("id", aid)->find();
		if ($admin && $admin["domain"]) {
			define("PRE_URL2", "https://" . $admin["domain"]);
		} else {
			define("PRE_URL2", PRE_URL);
		}
		if ($request->controller() . "/" . $request->action() == "Backstage/index") {
			$this->checkauthkey();
		}
	}
	public function checkauthkey()
	{
		$domain = 'www.quanwangjuzhen.cn';
		return true;
		$client = new \GuzzleHttp\Client(["timeout" => 5, "verify" => false]);
		try {
			$response = $client->request("POST", "https://www.zzxxx.com/index/install2/checkdomain", ["form_params" => ["domain" => $domain]]);
			$rs = $response->getBody()->getContents();
			$rs = json_decode($rs, true);
			if ($rs && $rs["status"] == 0) {
				exit($rs["msg"]);
			}
		} catch (\Throwable $e) {
		}
	}
	
	public function import_excel($file = "", $startrow = 2)
	{
		if (strpos(PRE_URL, $file) == 0) {
			$file = ROOT_PATH . ltrim(str_replace(PRE_URL, "", $file), "/");
		}
		$file = iconv("utf-8", "gb2312", $file);
		if (empty($file) || !file_exists($file)) {
			echojson(["status" => 0, "msg" => "file not exists!"]);
		}
		$objRead = \PhpOffice\PhpSpreadsheet\IOFactory::createReader("Xlsx");
		if (!$objRead->canRead($file)) {
			$objRead = \PhpOffice\PhpSpreadsheet\IOFactory::createReader("Xls");
			if (!$objRead->canRead($file)) {
				echojson(["status" => 0, "msg" => "No Excel!"]);
			}
		}
		$PHPExcel = $objRead->load($file);
		$currentSheet = $PHPExcel->getSheet(0);
		$allColumn = $currentSheet->getHighestColumn();
		$allRow = $currentSheet->getHighestRow();
		$exceldata = [];
		for ($currentRow = $startrow; $currentRow <= $allRow; $currentRow++) {
			$erp_orders_id = [];
			for ($currentColumn = "A"; $currentColumn <= $allColumn; $currentColumn++) {
				$val = $currentSheet->getCellByColumnAndRow(ord($currentColumn) - 64, $currentRow)->getValue();
				$erp_orders_id[] = $val;
			}
			$exceldata[] = $erp_orders_id;
		}
		return $exceldata;
	}
}
