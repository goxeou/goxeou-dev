<?php


// +----------------------------------------------------------------------
// | 配送设置
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class Freight extends Common
{
	//运费列表
    public function index(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
				$order = 'sort desc,id';
			}
			$where = [];
			$where[] = ['aid','=',aid];
			if(bid==0 && input('param.bid')){
				$where[] = ['bid','=',input('param.bid')];
			}else{
				$where[] = ['bid','=',bid];
			}
			if(input('param.name')) $where[] = ['name','like','%'.input('param.name').'%'];
			if(input('?param.status') && input('param.status')!=='') $where[] = ['status','=',input('param.status')];
			if(input('?param.pstype') && input('param.pstype')!=='') $where[] = ['pstype','=',input('param.pstype')];
			$count = 0 + Db::name('freight')->where($where)->count();
			$data = Db::name('freight')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			$mendianArr = Db::name('mendian')->where('aid',aid)->order('id')->column('name','id');
			foreach($data as $k=>$v){
				$pricedatahtml = '';
				if($v['pstype']==0 || $v['pstype']==10){
					$pricedata = json_decode($v['pricedata'],true);
					if(!$pricedata) $pricedata = [];
					foreach($pricedata as $pv){
						$pricedatahtml .= "<b>".rtrim($pv['region'],';').":</b> <br> {$pv['fristweight']}".($v['type']==1?'克':'件')."以下{$pv['fristprice']}元，每超出{$pv['secondweight']}".($v['type']==1?'克':'件')."加{$pv['secondprice']}元<br>";
					}
				}elseif($v['pstype']==1){
					$pricedatahtml = '<b>自提门店：</b><br>';
					if($v['storetype']==0){
						$pricedatahtml .= "全部";
					}else{
						foreach(explode(',',$v['storeids']) as $sv){
							$pricedatahtml .= "".$mendianArr[$sv]."<br>";
						}
					}
				}elseif($v['pstype']==2){
					$pricedatahtml = "<b>".$v['peisong_juli1'].'公里以内'.$v['peisong_fee1'].'元</b><br>';
					$pricedatahtml .= '每超出'.$v['peisong_juli2'].'公里加收'.$v['peisong_fee2'].'元<br>';
                }elseif($v['pstype']==5){
                    $pricedatahtml = '<b>配送门店：</b><br>';
                    if($v['storetype']==0){
                        $pricedatahtml .= "全部";
                    }else{
                        foreach(explode(',',$v['storeids']) as $sv){
                            $pricedatahtml .= "".$mendianArr[$sv]."<br>";
                        }
                    }
				}elseif($v['pstype']==12){
					$pricedatahtml = "<b>".$v['peisong_juli1'].'公里以内'.$v['peisong_fee1'].'元</b><br>';
					$pricedatahtml .= '每超出'.$v['peisong_juli2'].'公里加收'.$v['peisong_fee2'].'元<br>';
				}elseif($v['pstype']==3){
					$pricedatahtml = '<b>发货信息：</b><br>';
					if($v['pscontenttype']==0){
						$pricedatahtml .= '<pre>'.$v['pscontent'].'</pre>';
					}else{
						$pricedatahtml .= '不固定信息(在线卡密)';
					}
				}
				$data[$k]['pricedatahtml'] = $pricedatahtml;
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
    }
	//编辑
	public function edit(){
		if(input('param.id')){
			$info = Db::name('freight')->where('aid',aid)->where('bid',bid)->where('id',input('param.id/d'))->find();
			$info['field_list'] = json_decode($info['field_list'],true);
            }else{
		    $peisong_rangetype = 0;
		    $peisong_lng = '';
		    $peisong_lat = '';
		    $peisong_rangepath = '';
		    $info = [
				'id'=>'',
				'pstype'=>0,
				'name'=>'普通快递',
				'psprehour'=>4,
				'pstimedata'=>'[{"day":"1","hour":"12","minute":"0","hour2":"12","minute2":"30"},{"day":"1","hour":"18","minute":"0","hour2":"18","minute2":"30"},{"day":"2","hour":"12","minute":"0","hour2":"12","minute2":"30"},{"day":"2","hour":"18","minute":"0","hour2":"18","minute2":"30"}]',
				'peisong_juli1'=>5,
				'peisong_fee1'=>3,
				'peisong_juli2'=>1,
				'peisong_fee2'=>1,
				'peisong_rangetype'=>$peisong_rangetype,
                'storetype' => 0,
                'peisong_lng'=>$peisong_lng,
                'peisong_lat'=>$peisong_lat,
                'peisong_rangepath' => $peisong_rangepath
			];
		}
		if(!$info['formdata']){
			$info['formdata'] = json_encode([
				['key'=>'input','val1'=>'备注','val2'=>'选填，请输入备注信息','val3'=>'0'],	
			]);
		}
		
		$mendianArr = Db::name('mendian')->where('aid',aid)->where('bid',bid)->order('id')->column('name','id');
		if(getcustom('mendian_hexiao_givemoney')){
			$mendianArr = Db::name('mendian')->where('aid',aid)->where("bid=".bid." or (bid=0 and business_canuse=1)")->order('id')->column('name','id');
		}
		$info['isedit']=1;
		//dump($mendianArr);
		$ptset = Db::name('freight')->where('aid',1)->where('pstype',12)->find();
		View::assign('ptset',$ptset);
		View::assign('info',$info);
		View::assign('mendianArr',$mendianArr);
		return View::fetch();
	}
	//保存
	public function save(){
		$info = input('post.info/a');
		$citys = input('post.citys/a');
		$ispeisong = input('post.ispeisong/a');
		$fristweight = input('post.fristweight/a');
		$fristprice = input('post.fristprice/a');
		$secondweight = input('post.secondweight/a');
		$secondprice = input('post.secondprice/a');
		if(!$info['freeset']) $info['freeset'] = 0;
		$pricedata = array();
		foreach($citys as $k=>$city){
			if($ispeisong[$k]!=1) $ispeisong[$k] = 2;
			$pricedata[] = array(
				'region'=>$city,
				'ispeisong'=>$ispeisong[$k],
				'fristweight'=>$fristweight[$k],
				'fristprice'=>$fristprice[$k],
				'secondweight'=>$secondweight[$k],
				'secondprice'=>$secondprice[$k],
			);
		}
		//dump($info);
		$data = [];
		$data['pstype'] = $info['pstype'];
		$data['name'] = $info['name'];
		$data['type'] = $info['type'];
		$data['status'] = $info['status'];
		$data['sort'] = $info['sort'];
		$data['freeset'] = $info['freeset'];
		$data['free_price'] = $info['free_price'];
		$data['minpriceset'] = $info['minpriceset'];
		if($data['pstype']==1 || $data['pstype']==3) $data['minpriceset'] = 0;
		$data['minprice'] = $info['minprice'];
		$data['minnumset'] = $info['minnumset'];
		if($data['pstype']==1 || $data['pstype']==3) $data['minnumset'] = 0;
		$data['minnum'] = $info['minnum'];
		$data['pricedata'] = json_encode($pricedata,JSON_UNESCAPED_UNICODE);
		$data['storetype'] = $info['storetype'];
		if($info['pstype'] == 5) {
			$data['storetype'] = input('post.storetype5');
            $data['storeids'] = implode(',',input('post.storeid5/a'));
        } else {
            $data['storeids'] = implode(',',$info['storeid']);
        }

		//$field_list = input('post.field_list/a');
		//$data['field_list'] = jsonEncode($field_list);
		$data['pscontenttype'] = $info['pscontenttype'];
		$data['pscontent'] = $info['pscontent'];
		$data['needlinkinfo'] = $info['needlinkinfo'];
		$data['fwprice'] = $info['fwprice'];

		$datatype = input('post.datatype/a');
		$dataval1 = input('post.dataval1/a');
		$dataval2 = input('post.dataval2/a');
		$dataval3 = input('post.dataval3/a');
		$dhdata = array();
		foreach($datatype as $k=>$v){
			if($dataval3[$k]!=1) $dataval3[$k] = 0;
			$dhdata[] = array('key'=>$v,'val1'=>$dataval1[$k],'val2'=>$dataval2[$k],'val3'=>$dataval3[$k]);
		}
		$data['formdata'] = json_encode($dhdata,JSON_UNESCAPED_UNICODE);


		if($info['pstype']==11){
			$type11pricedataArr = [];
			foreach(input('post.type11pricedata/a') as $k=>$v){
				$type11pricedataArr[] = json_decode($v);
			}
			$data['type11pricedata'] = jsonEncode($type11pricedataArr);
		}

		$pstimeday = input('post.pstimeday/a');
		$pstimehour = input('post.pstimehour/a');
		$pstimeminute = input('post.pstimeminute/a');
		$pstimehour2 = input('post.pstimehour2/a');
		$pstimeminute2 = input('post.pstimeminute2/a');
		$pstimedata = [];
		foreach($pstimeday as $k=>$v){
			$pstimedata[] = ['day'=>$v,'hour'=>$pstimehour[$k],'minute'=>$pstimeminute[$k],'hour2'=>$pstimehour2[$k],'minute2'=>$pstimeminute2[$k]];
		}
		$data['pstimeset'] = $info['pstimeset'];
		$data['pstimedata'] = json_encode($pstimedata);
		$data['psprehour'] = $info['psprehour'];
		
		$data['peisong_juli1'] = $info['peisong_juli1'];
		$data['peisong_fee1'] = $info['peisong_fee1'];
		$data['peisong_juli2'] = $info['peisong_juli2'];
		$data['peisong_fee2'] = $info['peisong_fee2'];

		$data['peisong_lng'] = $info['peisong_lng'];
		$data['peisong_lat'] = $info['peisong_lat'];
		$data['peisong_range'] = $info['peisong_range'];
		if($data['pstype'] ==2){
            $data['peisong_rangetype'] = $info['peisong_rangetype'];
            $data['peisong_rangepath'] = $info['peisong_rangepath'];
        }
		$data['peisong_lng2'] = $info['peisong_lng2'] ? $info['peisong_lng2'] : $info['peisong_lng'];
		$data['peisong_lat2'] = $info['peisong_lat2'] ? $info['peisong_lat2'] : $info['peisong_lat'];

		if($info['id']){
			Db::name('freight')->where('aid',aid)->where('bid',bid)->where('id',$info['id'])->update($data);
			\app\commons\System::plog('修改配送方式'.$info['id']);
		}else{
			$data['aid'] = aid;
			$data['bid'] = bid;
			$data['createtime'] = time();
			$id = Db::name('freight')->insertGetId($data);
			\app\commons\System::plog('添加配送方式'.$id);
		}
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}
	//删除
	public function del(){
		$ids = input('post.ids/a');
		Db::name('freight')->where('aid',aid)->where('bid',bid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('删除配送方式'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	//选择配送模板
	public function choosefreight(){
		$pstype = input('pstype') || input('pstype') === "0"?input('pstype'):'';
		View::assign('pstype',$pstype);
		return View::fetch();
	}

	public function getPtset(){
		$ptset = Db::name('freight')->where('aid',1)->where('pstype',12)->find();
		return json(['status'=>1,'data'=>$ptset]);
	}
}