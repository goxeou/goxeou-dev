<?php


// +----------------------------------------------------------------------
// | 团购商城-商品管理
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class TuangouProduct extends Common
{
	//商品列表
    public function index(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
				$order = 'id desc';
			}
			$where = array();
			$where[] = ['aid','=',aid];
			if(bid==0){
				if(input('param.bid')){
					$where[] = ['bid','=',input('param.bid')];
				}elseif(input('param.showtype')==2){
					$where[] = ['bid','<>',0];
                }elseif(input('param.showtype')=='all'){
                    $where[] = ['bid','>=',0];
				}else{
					$where[] = ['bid','=',0];
				}
                }else{
				$where[] = ['bid','=',bid];
			}
			if(input('param.name')) $where[] = ['name','like','%'.$_GET['name'].'%'];
			if(input('?param.status') && input('param.status')!=='') $where[] = ['status','=',input('param.status')];
			if(input('?get.cid') && input('param.cid')!=='') $where[] = ['cid','=',input('param.cid/d')];
			if(input('?get.gid') && input('param.gid')!=='') $where[] = Db::raw("find_in_set(".input('param.gid/d').",gid)");

			$count = 0 + Db::name('tuangou_product')->where($where)->count();
			$data = Db::name('tuangou_product')->where($where)->page($page,$limit)->order($order)->select()->toArray();

			$clist = Db::name('tuangou_category')->where('aid',aid)->where('bid',bid)->select()->toArray();
			$cdata = array();
			foreach($clist as $c){
				$cdata[$c['id']] = $c['name'];
			}
			foreach($data as $k=>$v){
				$data[$k]['cname'] = $cdata[$v['cid']];
				if($v['bid'] > 0){
					$data[$k]['bname'] = Db::name('business')->where('aid',aid)->where('id',$v['bid'])->value('name');
				}else{
					$data[$k]['bname'] = '平台自营';
				}
				$buynum = Db::name('tuangou_order')->where('aid',aid)->where('proid',$v['id'])->where('status','in','1,2,3')->sum('num');
				if(getcustom('yx_tuangou_vrnum')){
					$buynum += $v['vrnum'];
				}
				$data[$k]['buynum'] = $buynum;
				$data[$k]['pricedata'] = json_decode($v['pricedata'],true);
				}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		//分类
		$clist = Db::name('tuangou_category')->Field('id,name')->where('aid',aid)->where('bid',bid)->where('status',1)->where('pid',0)->order('sort desc,id')->select()->toArray(); 
		foreach($clist as $k=>$v){
			$clist[$k]['child'] = Db::name('tuangou_category')->field('id,name')->where('aid',aid)->where('bid',bid)->where('status',1)->where('pid',$v['id'])->order('sort desc,id')->select()->toArray(); 
		}
		View::assign('clist',$clist);
        $this->defaultSet();
		return View::fetch();
    }
	//编辑商品
	public function edit(){
		if(input('param.id')){
			$info = Db::name('tuangou_product')->where('aid',aid)->where('id',input('param.id/d'))->find();
			if(!$info) showmsg('商品不存在');
			if(bid != 0 && $info['bid']!=bid) showmsg('无权限操作');
		}else{
			$info = [];
			$info['starttime'] = time();
			$info['endtime'] = time() + 7*86400;
		}
		//分类
		$clist = Db::name('tuangou_category')->field('id,name')->where('aid',aid)->where('bid',bid)->where('status',1)->where('pid',0)->order('sort desc,id')->select()->toArray(); 
		foreach($clist as $k=>$v){
			$clist[$k]['child'] = Db::name('tuangou_category')->field('id,name')->where('aid',aid)->where('bid',bid)->where('status',1)->where('pid',$v['id'])->order('sort desc,id')->select()->toArray(); 
		}
		$freightdata = array();
		if($info && $info['freightdata']){
			$freightdata = Db::name('freight')->where('aid',aid)->where('id','in',$info['freightdata'])->order('sort desc,id')->select()->toArray();
		}

		$info['showtj'] = explode(',',$info['showtj']);
		$info['gettj'] = explode(',',$info['gettj']);
		
		$default_cid = Db::name('member_level_category')->where('aid',aid)->where('isdefault', 1)->value('id');
        $default_cid = $default_cid ? $default_cid : 0;
		$levellist = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->order('sort,id')->select()->toArray();
		$aglevellist = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->where('can_agent','<>',0)->order('sort,id')->select()->toArray();

		$bset = Db::name('business_sysset')->where('aid',aid)->find();
		View::assign('info',$info);
		View::assign('clist',$clist);
		View::assign('freightdata',$freightdata);
		View::assign('levellist',$levellist);
		View::assign('aglevellist',$aglevellist);
		View::assign('bset',$bset);
		return View::fetch();
	}
	//保存商品
	public function save(){
		if(input('post.id')){
			$product = Db::name('tuangou_product')->where('aid',aid)->where('id',input('post.id/d'))->find();
			if(!$product) showmsg('商品不存在');
			if(bid != 0 && $product['bid']!=bid) showmsg('无权限操作');
		}

		$info = input('post.info/a');
		$info['detail'] = \app\commons\Common::geteditorcontent($info['detail']);
		$data = array();
		$data['name'] = $info['name'];
		$data['pic'] = $info['pic'];
		$data['pics'] = $info['pics'];
		//$data['fuwupoint'] = $info['fuwupoint'];
		$data['sellpoint'] = $info['sellpoint'];
		//$data['procode'] = $info['procode'];
		$data['cid'] = $info['cid'];
		$data['perlimit'] = $info['perlimit'];
		if($info['oldsales'] != $info['sales']){
			$data['sales'] = $info['sales'];
		}
		if(getcustom('yx_tuangou_vrnum')){
			$data['vrnum'] = !empty($info['vrnum'])?$info['vrnum']:0;
		}
		$data['sort'] = $info['sort'];
		$data['status'] = $info['status'];
		$data['detail'] = $info['detail'];
		$data['freighttype'] = $info['freighttype'];
		$data['freightdata'] = $info['freightdata'];
        $data['freightcontent'] = $info['freightcontent'];
        $data['contact_require'] = intval($info['contact_require']);
		$data['showtj'] = $info['showtj']==null?-1: implode(',',$info['showtj']);
		$data['gettj'] = $info['gettj']==null?-1:  implode(',',$info['gettj']);
		 
		if(!$product) $data['createtime'] = time();
		$data['market_price'] = $info['market_price'];
		$data['cost_price'] = $info['cost_price'];
		$data['sell_price'] = $info['sell_price'];
		$data['weight'] = $info['weight'];
		$data['stock'] = $info['stock'];
		$data['starttime'] = strtotime($info['starttime']);
		$data['endtime'] = strtotime($info['endtime']);
		
		$pricedata = array();
		$tg_money = input('post.tg_money/a');
		$tg_num = input('post.tg_num/a');
		foreach($tg_money as $k=>$money){
			$pricedata[] = array(
				'money'=>$money,
				'num'=>$tg_num[$k],
			);
			if($k > 0){
				if($tg_num[$k] <= $tg_num[$k-1]){
					return json(['status'=>0,'msg'=>'团购人数请依次递增设置']);
				}
				if($tg_money[$k] > $tg_money[$k-1]){
					return json(['status'=>0,'msg'=>'团购价格请不要小于上一个价格']);
				}
			}
		}
		$data['pricedata'] = json_encode($pricedata,JSON_UNESCAPED_UNICODE);
		$data['commissionset'] = $info['commissionset'];
		$data['commissiondata1'] = jsonEncode(input('post.commissiondata1/a'));
		$data['commissiondata2'] = jsonEncode(input('post.commissiondata2/a'));
		$data['commissiondata3'] = jsonEncode(input('post.commissiondata3/a'));
		if(bid !=0 ){
			$bset = Db::name('business_sysset')->where('aid',aid)->find();
			if($bset['product_check'] == 1){
				$data['ischecked'] = 0;
			}
			if($bset['commission_canset']==0){
				$data['commissionset'] = '-1';
			}
		}
		if(bid == 0){
			$data['scoredkmaxset'] = $info['scoredkmaxset'];
			$data['scoredkmaxval'] = $info['scoredkmaxval'];
		}
		if($product){
			Db::name('tuangou_product')->where('aid',aid)->where('id',$product['id'])->update($data);
			$proid = $product['id'];
			\app\commons\System::plog('编辑团购商品'.$proid);
		}else{
			$data['aid'] = aid;
			$data['bid'] = bid;
			$proid = Db::name('tuangou_product')->insertGetId($data);
			\app\commons\System::plog('添加团购商品'.$proid);
		}
        //更新商户虚拟销量
        if($product){
            $bid = $product['bid'];
        }else{
            $bid = $info['bid']?:bid;
        }
        $vrnum = 0;
        if(getcustom('yx_tuangou_vrnum')){
            $vrnum = !empty($info['vrnum'])?$info['vrnum']:0;
            $product_vrnum = !empty($product['vrnum'])?$product['vrnum']:0;
            $vrnum = $vrnum-$product_vrnum;
        }
        $sales = $info['sales']-$info['oldsales']+$vrnum;
        if($sales!=0){
            \app\models\Payorder::addSales(0,'sales',aid,$bid,$sales);
        }
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}
	//改状态
	public function setst(){
		$st = input('post.st/d');
		$ids = input('post.ids/a');
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['id','in',$ids];
		if(bid !=0){
			$where[] = ['bid','=',bid];
		}
		Db::name('tuangou_product')->where($where)->update(['status'=>$st]);

		\app\commons\System::plog('团购商品修改状态'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//审核
	public function setcheckst(){
		$st = input('post.st/d');
		$id = input('post.id/d');
		$reason = input('post.reason');
		Db::name('tuangou_product')->where('aid',aid)->where('id',$id)->update(['ischecked'=>$st,'check_reason'=>$reason]);
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//删除
	public function del(){
		$ids = input('post.ids/a');
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['id','in',$ids];
		if(bid !=0){
			$where[] = ['bid','=',bid];
		}
		Db::name('tuangou_product')->where($where)->delete();
		\app\commons\System::plog('团购商品删除'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	//复制商品
	public function procopy(){
		$product = Db::name('tuangou_product')->where('aid',aid)->where('bid',bid)->where('id',input('post.id/d'))->find();
		if(!$product) return json(['status'=>0,'msg'=>'商品不存在,请重新选择']);
//		$gglist = Db::name('tuangou_guige')->where('aid',aid)->where('proid',$product['id'])->select()->toArray();
		$data = $product;
		$data['name'] = '复制-'.$data['name'];
		unset($data['id']);
		$data['status'] = 0;
		$newproid = Db::name('tuangou_product')->insertGetId($data);
//		foreach($gglist as $gg){
//			$ggdata = $gg;
//			$ggdata['proid'] = $newproid;
//			unset($ggdata['id']);
//			Db::name('tuangou_guige')->insert($ggdata);
//		}
		\app\commons\System::plog('复制团购商品'.$newproid);
		return json(['status'=>1,'msg'=>'复制成功','proid'=>$newproid]);
	}
	
	//选择商品
	public function chooseproduct(){
		//分类
		$clist = Db::name('tuangou_category')->Field('id,name')->where('aid',aid)->where('status',1)->where('pid',0)->order('sort desc,id')->select()->toArray(); 
		foreach($clist as $k=>$v){
			$clist[$k]['child'] = Db::name('tuangou_category')->Field('id,name')->where('aid',aid)->where('status',1)->where('pid',$v['id'])->order('sort desc,id')->select()->toArray(); 
		}
		View::assign('clist',$clist);
		return View::fetch();
	}
	//获取商品信息
	public function getproduct(){
		$proid = input('post.proid/d');
		$product = Db::name('tuangou_product')->where('aid',aid)->where('id',$proid)->find();
		
		$buynum = $product['sales'];
		$pricedata = json_decode($product['pricedata'],true);
		$nowpricedata = array('num'=>0,'money'=>$product['sell_price']);
		foreach($pricedata as $k3=>$v3){
			if($buynum >= $v3['num']){
				$nowpricedata = $v3;
			}
		}
		$product['sell_price'] = $nowpricedata['money'];
		$minpricedata = end($pricedata);
		$min_price = $minpricedata['money'];
		$product['min_price'] = $min_price;

		return json(['product'=>$product]);
	}

	//获取达成的价格
	public function gettgjage(){
		$id = input('post.id/d');
		$product = Db::name('tuangou_product')->where('aid',aid)->where('id',$id)->find();
		$buynum = Db::name('tuangou_order')->where('aid',aid)->where('proid',$product['id'])->where('status','in','1,2,3')->sum('num');
		if(getcustom('yx_tuangou_vrnum')){
			$buynum += $product['vrnum'];
		}
		$nowpricedata = [];
		$pricedata = json_decode($product['pricedata'],true);
		$nowpricedata = array('num'=>0,'money'=>$product['sell_price']);
		foreach($pricedata as $k=>$v){
			if($buynum >= $v['num']){
				$nowpricedata = $v;
			}
		}
		$minpricedata = end($pricedata);
		return json($nowpricedata);
	}
	//团购 退差价
	public function tuichajia(){
		$id = input('post.id/d');
		$product = Db::name('tuangou_product')->where('aid',aid)->where('id',$id)->find();
		$buynum = Db::name('tuangou_order')->where('aid',aid)->where('proid',$product['id'])->where('status','in','1,2,3')->sum('num');
		if(getcustom('yx_tuangou_vrnum')){
			$buynum += $product['vrnum'];
		}
		$nowpricedata = [];
		$pricedata = json_decode($product['pricedata'],true);
		$nowpricedata = array('num'=>0,'money'=>$product['sell_price']);
		foreach($pricedata as $k=>$v){
			if($buynum >= $v['num']){
				$nowpricedata = $v;
			}
		}
		$orderlist = Db::name('tuangou_order')->where('aid',aid)->where('proid',$product['id'])->where('status','in','1,2,3')->select()->toArray();
		$totaltuimoney = 0;
		$totaltuiscore = 0;
		foreach($orderlist as $order){
			if($order['product_price'] > $nowpricedata['money'] * $order['num']){
				//退金额
				$updata = [];
				$tuimoney = $order['product_price'] - $nowpricedata['money'] * $order['num'];
				if($tuimoney > $order['totalprice'] - $order['tuimoney']){
					$tuimoney = $order['totalprice'] - $order['tuimoney'];
				}
				$totaltuimoney += $tuimoney;
				
				if($tuimoney > 0){
					$rs = \app\commons\Order::refund($order,$tuimoney,'团购多付款退还');
					if($rs['status']==0){
						return json(['status'=>0,'msg'=>$rs['msg']]);
					}
				}

				$updata['product_price'] = $nowpricedata['money'] * $order['num'];
				$updata['tuimoney'] = $order['tuimoney'] + $tuimoney;
				Db::name('tuangou_order')->where('id',$order['id'])->update($updata);
			}
		}
		return json(['status'=>1,'msg'=>'共计退款：￥'.$totaltuimoney]);
	}
    function defaultSet(){
        $set = Db::name('tuangou_sysset')->where('aid',aid)->find();
        if(!$set){
            Db::name('tuangou_sysset')->insert(['aid'=>aid]);
        }
    }
}