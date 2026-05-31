<?php


// +----------------------------------------------------------------------
// | 公共 验证权限
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class Common extends Base
{
	public $aid;
    public $bid;
	public $uid;
	public $user;
    public $admin;
    public $sysset_webinfo;
	public $mdid = 0;
	public $auth_data = 'all';
	public $platform = 'mp';
	public $xcxaid = 0;
	public $score_weishu = 0;
    public function initialize(){
		parent::initialize();
		$request = request();
        $controller = $request->controller();
        if(strpos($controller,'.') !== false){
            $controllerArr = explode('.',$controller);
            $controller = $controllerArr[1];
        }
//        $file = ROOT_PATH.'runtime/log/aaa.txt';
//        file_put_contents($file,$_POST['apifrom']);
        //如果是vueapi的请求，则不执行header跳转，直接返回状态给接口前端
		if(input('param.apifrom')=='vue'){
            $url =  (string)url('CashierLogin/index');
            //测试 end
            if(!session("?ADMIN_LOGIN")){
                echojson(['status'=>-5,'msg'=>'请重新登录','url' => $url]);die();
            }
        }else{
            if(!in_array($controller,array('login')) && !session("?ADMIN_LOGIN")){
				$logincodeurl = '';
				header('Location:'.(string)url('login/index'.$logincodeurl));die;
            }
        }
		$this->aid = session('ADMIN_AID');
		if(MN == 'business'){
			$this->uid = session('ADMIN_AUTH_UID');
			$this->bid = session('ADMIN_AUTH_BID');
		}else{
			$this->uid = session('ADMIN_UID');
			$this->bid = session('ADMIN_BID');
		}
		
		if(getcustom('design_template')){
			//处理模板库账号权限切换
            $type =  input('param.type')?input('type/d'):0;
            $into =  input('param.into')?input('into/d'):0;
            if($type == 1 && $into == 1){
                //模板库必须是总管理账号
                $bst_id = session('BST_ID');
                $rs= db('admin_user')->where(['id'=>$bst_id])->find();
                if(!session('BST_ID') || !$rs || $rs['isadmin'] != 2){
                    showmsg('无访问权限');
                }
                define('aid',$rs['aid']);
				define('bid',$rs['bid']);
				define('uid',$rs['id']);
				$this->uid = $rs['id'];
            }else{
            	define('aid',$this->aid);
				define('bid',$this->bid);
				define('uid',$this->uid);
            }

		}else{
			define('aid',$this->aid);
			define('bid',$this->bid);
			define('uid',$this->uid);
		}
		$user = Db::name('admin_user')->where('id',$this->uid)->find();
		if($user['groupid']){
			$group = Db::name('admin_user_group')->where('id',$user['groupid'])->find();
			$user['auth_data'] = $group['auth_data'];
			$user['wxauth_data'] = $group['wxauth_data'];
			$user['notice_auth_data'] = $group['notice_auth_data'];
			$user['hexiao_auth_data'] = $group['hexiao_auth_data'];
			$user['mdid'] = $group['mdid'];
			$user['showtj'] = $group['showtj'];
		}
		if($user['bid'] > 0 && $user['auth_type'] == 1){
			$adminuser = Db::name('admin_user')->where('aid',$this->aid)->where('isadmin','>',0)->find();
			$user['auth_type'] = $adminuser['auth_type'];
			$user['auth_data'] = $adminuser['auth_data'];
		}
		$this->user = $user;
		$this->mdid = $user['mdid'];
        $this->sysset_webinfo = \app\commons\Common::getSysset();
		if($user['auth_type']==0){
			$auth_data = json_decode($user['auth_data'],true);
			$auth_path = \app\commons\Menu::blacklist();
			foreach($auth_data as $v){
				$auth_path = array_merge($auth_path,explode(',',$v));
			}
			$thispath = $controller .'/'.$request->action();
            $auth_path = $auth_path ?? [];
			if(!in_array($controller.'/*',$auth_path) && !in_array($thispath,$auth_path) && !session('BST_ID')){
                if(input('param.apifrom')=='vue'){
                    echojson(['status'=>0,'msg'=>'无访问权限']);die();
                }else{
                    die('无访问权限');
                }
               
			}
			$this->auth_data = $auth_path;
		}else{
			$this->auth_data = 'all';
		}
		View::assign('aid',$this->aid);
		View::assign('bid',$this->bid);
		View::assign('auth_data',$this->auth_data);
        View::assign('sysset_webinfo',$this->sysset_webinfo);

		$platform = \app\commons\Common::getplatform(aid);
		$this->platform = $platform;
		View::assign('platform',$platform);

		$admin = Db::name('admin')->where('id',aid)->find();
        //禁用权限判断
        $this->admin = $admin;
		if($admin['domain']){
			define('PRE_URL2','https://'.$admin['domain']);
		}else{
			define('PRE_URL2',PRE_URL);
		}
        //导出excel的表头配置
        $all_excel_set = \think\facade\Config::get('excel_field');;
        $excel_key = request()->controller().'/'.request()->action();
        $excel_set = $all_excel_set[$excel_key]??'';
        $excel_title = $excel_set?jsonEncode($excel_set['title']):jsonEncode([]);
        $excel_field = $excel_set?jsonEncode($excel_set['field']):jsonEncode([]);
        $excel_name = $excel_set?$excel_set['name']:'';
        View::assign('excel_title',$excel_title);
        View::assign('excel_field',$excel_field);
        View::assign('excel_name',$excel_name);
    }

    /**
     * @param array $titleArr
     * @param array $dataArr
     * @param array $typeArr 类型数组
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
	function export_excel(array $titleArr,array $dataArr,array $typeArr=[]){
		$phpexcel = new \PhpOffice\PhpSpreadsheet\Spreadsheet();//实例化
		$phpsheet = $phpexcel->getActiveSheet();
		//填充表头信息
		$widthArr = [];
		for($i = 0;$i < count($titleArr);$i++) {
			$phpsheet->setCellValue(IntToChr($i)."1","$titleArr[$i]");
			$widthArr[] = strlen($titleArr[$i]);
		}
		//填充表格信息
		for ($i = 2;$i <= count($dataArr) + 1;$i++) {
			$j = 0;
			foreach ($dataArr[$i-2] as $key=>$value) {
			    if($typeArr && $typeArr[$j] == 1)//数字
                    $phpsheet->setCellValue(IntToChr($j)."$i","$value");
			    else
				    $phpsheet->setCellValueExplicit(IntToChr($j)."$i","$value",\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				if(strlen($value) > $widthArr[$j]){
					$widthArr[$j] = strlen($value);
					if($widthArr[$j] > 25) $widthArr[$j] = 25;
				}
				$j++;
			}
		}
		//设置列宽
		foreach($widthArr as $k=>$v){
			$phpsheet->getColumnDimension(IntToChr($k))->setWidth($v+2);
		}
		$phpwriter = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($phpexcel,"Xlsx");
		header('Content-Disposition: attachment;filename="'.date('YmdHis').'.xlsx"');
		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		$phpwriter->save("php://output");
        die();
	}
	function import_excel($file='', $startrow=2,$aid=1){
		if(strpos(PRE_URL,$file)==0){
			$file = ROOT_PATH.ltrim(str_replace(PRE_URL,'',$file),'/');
		}
		$file = iconv("utf-8", "gb2312", $file);   //转码
		if(empty($file) OR !file_exists($file)) {
			echojson(['status'=>0,'msg'=>'file not exists!']);
		}
        $fileType  = 'Xlsx';
		$objRead = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');   //建立reader对象
		if(!$objRead->canRead($file)){
            $fileType  = 'Xls';
			$objRead = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xls');
			if(!$objRead->canRead($file)){
				echojson(['status'=>0,'msg'=>'No Excel!']);
			}
		}
		$PHPExcel = $objRead->load($file);
		$currentSheet = $PHPExcel->getSheet(0);  //读取excel文件中的第一个工作表
		$allColumn = $currentSheet->getHighestColumn(); //取得最大的列号
        $allColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($allColumn);
		$allRow = $currentSheet->getHighestRow(); //取得一共有多少行
		$exceldata = array();
		/**从第二行开始输出，因为excel表中第一行为列名*/
		for($currentRow = $startrow;$currentRow <= $allRow;$currentRow++){
			/**从第A列开始输出*/
			$erp_orders_id = array();  //声明数组
			for($currentColumn= 1;$currentColumn<= $allColumn; $currentColumn++){
				$val = $currentSheet->getCellByColumnAndRow($currentColumn,$currentRow)->getValue();/**ord()将字符转为十进制数*/
				$erp_orders_id[] = $val;
			}
			$exceldata[] = $erp_orders_id;
		}
        if($fileType == 'Xlsx'){
            //图片本地存储的路径
            $imageFilePath = "upload/{$aid}/" . date('Ym') . "/";
            if (!file_exists($imageFilePath)) { // 如果目录不存在则递归创建
                mkdir($imageFilePath, 0777, true);
            }

            //处理图片 PhpOffice\PhpSpreadsheet\Worksheet\Drawing类的实例;   仅支持xlsx格式文件
            foreach($currentSheet->getDrawingCollection() as $k=> $img) {
                list($startColumn,$startRow) = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::coordinateFromString($img->getCoordinates());  //获取图片所在行和列
                $startColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($startColumn);
                $filePath = $imageFilePath . date('d_His') . rand(100000, 999999) . '.' . $img->getExtension();
                switch($img->getExtension()) {
                    case 'jpg':
                    case 'jpeg':
                        imagejpeg(imagecreatefromjpeg($img->getPath()),$filePath);
                        break;
                    case 'gif':
                        imagegif(imagecreatefromgif($img->getPath()),$filePath);
                        break;
                    case 'png':
                        imagepng(imagecreatefrompng($img->getPath()),$filePath);
                        break;
                    default:
                        echojson(['status'=>0,'msg'=>$startColumn.'行存在未知格式图片']);
                }
                $exceldata[$startRow - $startrow][$startColumnIndex-1] = PRE_URL . '/' . $filePath;
            }
        }
		return $exceldata;
	}

	public function inputlockpwd(){
		$lockpwd = input('param.lockpwd');
		$realpwd = Db::name('admin_set')->where('aid',aid)->value('locking_pwd');
		if($lockpwd != $realpwd){
			session('isunlock',0);
			return json(['status'=>0,'msg'=>'密码错误']);
		}else{
			session('isunlock',1);
			return json(['status'=>1,'msg'=>'操作成功']);
		}
	}
   
}
