<?php


// +----------------------------------------------------------------------
// | 积分商城-商品管理
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class ScoreshopProduct extends Common
{
    public function initialize(){
		parent::initialize();
		//if(bid > 0) showmsg('无访问权限');
	}
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
            if(input('param.cid')) $where[] = ['cid','=',$_GET['cid']];
			if(input('?param.status') && input('param.status')!=='') $where[] = ['status','=',input('param.status')];

			$clist = Db::name('scoreshop_category')->where('aid',aid)->select()->toArray();
			$cdata = array();
			foreach($clist as $c){
				$cdata[$c['id']] = $c['name'];
			}
			$count = 0 + Db::name('scoreshop_product')->where($where)->count();
			$data = Db::name('scoreshop_product')->where($where)->page($page,$limit)->order($order)->select()->toArray();
            $score_weishu = 0;
            foreach($data as $k=>$v){
				$data[$k]['cname'] = $cdata[$v['cid']];
				if($v['bid'] > 0){
					$data[$k]['bname'] = Db::name('business')->where('aid',aid)->where('id',$v['bid'])->value('name');
				}else{
					$data[$k]['bname'] = '平台自营';
				}
                $data[$k]['score_price'] = dd_money_format($v['score_price'],$score_weishu);
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		//分类
		$clist = Db::name('scoreshop_category')->Field('id,name')->where('aid',aid)->where('status',1)->where('pid',0)->order('sort desc,id')->select()->toArray(); 
		foreach($clist as $k=>$v){
			$clist[$k]['child'] = Db::name('scoreshop_category')->Field('id,name')->where('aid',aid)->where('status',1)->where('pid',$v['id'])->order('sort desc,id')->select()->toArray(); 
		}
		View::assign('clist',$clist);
		
		if(session('BST_ID') && bid==0){
			$userlist = Db::name('admin_user')->field('id,aid,un')->where('id','<>',$this->user['id'])->where('bid',0)->where('isadmin',1)->select()->toArray();
			View::assign('cancopy',true);
		}else{
			$userlist = [];
			View::assign('cancopy',false);
		}
		View::assign('userlist',$userlist);

		return View::fetch();
    }
	//编辑商品
	public function edit(){
        $score_weishu = 0;
        if(input('param.id')){
			$info = Db::name('scoreshop_product')->where('aid',aid)->where('id',input('param.id/d'))->find();
			if(!$info) showmsg('商品不存在');
			if(bid != 0 && $info['bid']!=bid) showmsg('无权限操作');
            $info['score_price'] = dd_money_format($info['score_price'],$score_weishu);
		}
		//分类
		$clist = Db::name('scoreshop_category')->Field('id,name')->where('aid',aid)->where('status',1)->where('pid',0)->order('sort desc,id')->select()->toArray(); 
		foreach($clist as $k=>$v){
			$clist[$k]['child'] = Db::name('scoreshop_category')->Field('id,name')->where('aid',aid)->where('status',1)->where('pid',$v['id'])->order('sort desc,id')->select()->toArray(); 
		}
		$freightList = Db::name('freight')->where('aid',aid)->where('bid',bid)->where('status',1)->order('sort desc,id')->select()->toArray();
		$freightdata = array();
		if($info && $info['freightdata']){
			$freightdata = Db::name('freight')->where('aid',aid)->where('bid',bid)->where('id','in',$info['freightdata'])->order('sort desc,id')->select()->toArray();
		}
		$info['lvprice_data'] = json_decode($info['lvprice_data'], true);

		//多规格
		$newgglist = array();
		if($info){
			if($info['guigeset'] == 1){
				$gglist = Db::name('scoreshop_guige')->where('aid',aid)->where('proid',$info['id'])->select()->toArray();
				foreach($gglist as $k=>$v){
                    $v['score_price'] = dd_money_format($v['score_price'],$score_weishu);
					$v['lvprice_data'] = json_decode($v['lvprice_data'], true);
					if($v['ks']!==null){
						$newgglist[$v['ks']] = $v;
					}else{
						Db::name('scoreshop_guige')->where('aid',aid)->where('id',$v['id'])->update(['ks'=>$k]);
						$newgglist[$k] = $v;
					}
				}
			}else{
				$newgglist = [
					['name'=>'','pic'=>'','market_price'=>$info['sell_price'],'cost_price'=>$info['cost_price'],'money_price'=>$info['money_price'],'score_price'=>$info['score_price'],'weight'=>$info['weight'],'stock'=>$info['stock'],'ks'=>'0','lvprice_data'=>$info['lvprice_data']]
				];
			}
		}

		//分成结算类型
        $sysset = Db::name('admin_set')->where('aid',aid)->find();
        if($sysset['fxjiesuantype'] == 1) {
            $jiesuantypeDesc = '成交价';
        }elseif($sysset['fxjiesuantype'] == 2) {
            $jiesuantypeDesc = '销售利润';
        } else {
            $jiesuantypeDesc = '销售价';
        }

        $default_cid = Db::name('member_level_category')->where('aid',aid)->where('isdefault', 1)->value('id');
        $default_cid = $default_cid ? $default_cid : 0;
        $aglevellist = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->where('can_agent','<>',0)->order('sort,id')->select()->toArray();
        $levellist = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->order('sort,id')->select()->toArray();

        if(getcustom('scoreshop_fenhong')){
            $gdlevellist = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->where('fenhong','>','0')->order('sort,id')->select()->toArray();
            $teamlevellist = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->where('teamfenhonglv','>','0')->order('sort,id')->select()->toArray();
            $areafhlevellist = Db::name('member_level')->where('aid',aid)->where('areafenhong','>','0')->select()->toArray();
            View::assign('gdlevellist',$gdlevellist);
            View::assign('teamlevellist',$teamlevellist);
            View::assign('areafhlevellist',$areafhlevellist);
        }
		
		$info['showtj'] = explode(',',$info['showtj']);
		$info['gettj'] = explode(',',$info['gettj']);

		$bset = Db::name('business_sysset')->where('aid',aid)->find();
        View::assign('aglevellist',$aglevellist);
        View::assign('levellist',$levellist);
		View::assign('freightList',$freightList);
		View::assign('freightdata',$freightdata);
		View::assign('clist',$clist);
		View::assign('info',$info);
        View::assign('jiesuantypeDesc',$jiesuantypeDesc);
        View::assign('bset',$bset);
		View::assign('newgglist',$newgglist);
        View::assign('bid',bid);
		return View::fetch();
	}
	//保存商品
	public function save(){
		if(input('post.id')){
			$product = Db::name('scoreshop_product')->where('aid',aid)->where('id',input('post.id/d'))->find();
			if(!$product) showmsg('商品不存在');
			if(bid != 0 && $product['bid']!=bid) showmsg('无权限操作');
		}
		$info = input('post.info/a');

		$info['detail'] = \app\commons\Common::geteditorcontent($info['detail']);
		$data = array();
		$data['cid'] = $info['cid'];
		$data['name'] = $info['name'];
		$data['pic'] = $info['pic'];
		$data['pics'] = $info['pics'];
		//$data['fuwupoint'] = $info['fuwupoint'];
		//$data['sellpoint'] = $info['sellpoint'];
		//$data['procode'] = $info['procode'];
		//$data['sell_price'] = $info['sell_price'];
        //$data['cost_price'] = $info['cost_price'];
        //$data['score_price'] = $info['score_price'];
        //$data['money_price'] = $info['money_price'];
		//$data['weight'] = $info['weight'];
		//$data['stock'] = $info['stock'];
		if($info['oldsales'] != $info['sales']){
			$data['sales'] = $info['sales'];
		}
		$data['sort'] = $info['sort'];
		$data['buymax'] = $info['buymax'];
		$data['status'] = $info['status'];
		$data['detail'] = $info['detail'];
		$data['freighttype'] = $info['freighttype'];
        $data['everyday_buymax'] = $info['everyday_buymax'];
        $data['freightdata'] = $info['freightdata'];
		$data['freightcontent'] = $info['freightcontent'];
        $data['contact_require'] = intval($info['contact_require']);
        $data['lvprice'] = $info['lvprice'] ?? 0;
        $data['guigeset'] = $info['guigeset'] ?? 0;

		if(isset($info['showtj'])){
			$data['showtj'] = implode(',',$info['showtj']);
		}
		if(isset($info['gettj'])){
			$data['gettj'] = implode(',',$info['gettj']);
		}
		

        $data['commissionset'] = $info['commissionset'];
        $data['commissiondata1'] = jsonEncode(input('post.commissiondata1/a'));
        $data['commissiondata2'] = jsonEncode(input('post.commissiondata2/a'));
        $data['commissiondata3'] = jsonEncode(input('post.commissiondata3/a'));
        $data['commissiondata5'] = jsonEncode(input('post.commissiondata5/a'));

        if(getcustom('scoreshop_fenhong') && bid == 0){
            $data['fenhongset'] = $info['fenhongset'];
            $data['gdfenhongset'] = $info['gdfenhongset'];
            $data['gdfenhongdata1'] = jsonEncode(input('post.gdfenhongdata1/a'));
            $data['gdfenhongdata2'] = jsonEncode(input('post.gdfenhongdata2/a'));
            $data['teamfenhongset'] = $info['teamfenhongset'];
            $data['teamfenhongdata1'] = jsonEncode(input('post.teamfenhongdata1/a'));
            $data['teamfenhongdata2'] = jsonEncode(input('post.teamfenhongdata2/a'));
            $data['areafenhongset'] = $info['areafenhongset'];
            $data['areafenhongdata1'] = jsonEncode(input('post.areafenhongdata1/a'));
            $data['areafenhongdata2'] = jsonEncode(input('post.areafenhongdata2/a'));
        }

		if($info['lvprice']==1){
            $default_cid = Db::name('member_level_category')->where('aid',aid)->where('isdefault', 1)->value('id');
            $default_cid = $default_cid ? $default_cid : 0;
            $levellist = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->order('sort,id')->select()->toArray();
			$defaultlvid = $levellist[0]['id'];
			$moneyprice_field = 'money_price_'.$defaultlvid;
			$scoreprice_field = 'score_price_'.$defaultlvid;
		}else{
			$moneyprice_field = 'money_price';
			$scoreprice_field = 'score_price';
		}
		$money_price = 0;$score_price = 0;$market_price = 0;$cost_price = 0;$weight = 0;$givescore=0;$lvprice_data = [];
		$i = 0;
		foreach(input('post.option/a') as $ks=>$v){
			if($i == 0){
				$money_price = $v[$moneyprice_field];
				$score_price = $v[$scoreprice_field];
				$market_price = $v['market_price'];
				$cost_price = $v['cost_price'];
				$weight = $v['weight'];
				if($info['lvprice']==1){
					$lvprice_data = [];
					foreach($levellist as $lv){
						$lvprice_data[$lv['id']]['money'] = $v['money_price_'.$lv['id']];
						$lvprice_data[$lv['id']]['score'] = $v['score_price_'.$lv['id']];
					}
				}
			}
			$i++;
		}
		if($info['lvprice']==1){
			$data['lvprice_data'] = json_encode($lvprice_data);
		}
		$data['sell_price'] = $market_price;
		$data['cost_price'] = $cost_price;
		$data['money_price'] = $money_price;
		$data['score_price'] = $score_price;
		$data['weight'] = $weight;
		$data['stock'] = 0;
		foreach(input('post.option/a') as $v){
			$data['stock'] += $v['stock'];
		}
		$data['guigedata'] = input('post.specs');


		if(!$product) $data['createtime'] = time();
        if(bid !=0 ){
			$bset = Db::name('business_sysset')->where('aid',aid)->find();
			if($bset['product_check'] == 1){
				$data['ischecked'] = 0;
			}
			if($bset['commission_canset']==0){
				$data['commissionset'] = '-1';
			}
			if($bset['product_showset']==0){
				$data['showtj'] = '-1';
				$data['gettj'] = '-1';
				$data['lvprice'] = 0;
			}
		}
		if($product){
			Db::name('scoreshop_product')->where('aid',aid)->where('id',$product['id'])->update($data);
			$proid = $product['id'];
			\app\commons\System::plog('编辑积分商城商品'.$product['id']);
		}else{
			$data['aid'] = aid;
			$data['bid'] = bid;
			$proid = Db::name('scoreshop_product')->insertGetId($data);
			\app\commons\System::plog('添加积分商城商品'.$proid);
		}
        //更新商户虚拟销量
        if($product){
            $bid = $product['bid'];
        }else{
            $bid = $info['bid']?:bid;
        }
        $sales = $info['sales']-$info['oldsales'];
        if($sales!=0){
            \app\models\Payorder::addSales(0,'sales',aid,$bid,$sales);
        }

		//多规格
		if($data['guigeset'] == 1){
			$newggids = array();
			foreach(input('post.option/a') as $ks=>$v){
				$ggdata = array();
				$ggdata['proid'] = $proid;
				$ggdata['ks'] = $ks;
				$ggdata['name'] = $v['name'];
				$ggdata['pic'] = $v['pic'] ? $v['pic'] : '';
				$ggdata['market_price'] = $v['market_price']>0 ? $v['market_price']:0;
				$ggdata['cost_price'] = $v['cost_price']>0 ? $v['cost_price']:0;
				$ggdata['money_price'] = $v['money_price']>0 ? $v['money_price']:0;
				$ggdata['score_price'] = $v['score_price']>0 ? $v['score_price']:0;
				$ggdata['weight'] = $v['weight']>0 ? $v['weight']:0;
				//$ggdata['procode'] = $v['procode'];
				//$ggdata['barcode'] = $v['barcode'];
				$ggdata['stock'] = $v['stock']>0 ? $v['stock']:0;
				//$ggdata['limit_start'] = $v['limit_start']>0 ? $v['limit_start']:0;
				$lvprice_data = [];
				if($info['lvprice']==1){
					$ggdata['money_price'] = $v['money_price_'.$levellist[0]['id']]>0 ? $v['money_price_'.$levellist[0]['id']]:0;
					$ggdata['score_price'] = $v['score_price_'.$levellist[0]['id']]>0 ? $v['score_price_'.$levellist[0]['id']]:0;
					foreach($levellist as $lv){
						$money_price = $v['money_price_'.$lv['id']]>0 ? $v['money_price_'.$lv['id']]:0;
						$score_price = $v['score_price_'.$lv['id']]>0 ? $v['score_price_'.$lv['id']]:0;
						$lvprice_data[$lv['id']]['money'] = $money_price;
						$lvprice_data[$lv['id']]['score'] = $score_price;
					}
					$ggdata['lvprice_data'] = json_encode($lvprice_data);
				}

				$guige = Db::name('scoreshop_guige')->where('aid',aid)->where('proid',$proid)->where('ks',$ks)->find();
				if($guige){
					Db::name('scoreshop_guige')->where('aid',aid)->where('id',$guige['id'])->update($ggdata);
					$ggid = $guige['id'];
				}else{
					$ggdata['aid'] = aid;
					$ggid = Db::name('scoreshop_guige')->insertGetId($ggdata);
				}
				$newggids[] = $ggid;
			}
			Db::name('scoreshop_guige')->where('aid',aid)->where('proid',$proid)->where('id','not in',$newggids)->delete();
		}
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}
	//改状态
	public function setst(){
		$st = input('post.st/d');
		$ids = input('post.ids/a');
		Db::name('scoreshop_product')->where('aid',aid)->where('id','in',$ids)->update(['status'=>$st]);
		\app\commons\System::plog('积分商城商品改状态'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//审核
	public function setcheckst(){
		if(bid != 0) showmsg('无权限操作');
		$st = input('post.st/d');
		$id = input('post.id/d');
		$reason = input('post.reason');
		Db::name('scoreshop_product')->where('aid',aid)->where('id',$id)->update(['ischecked'=>$st,'check_reason'=>$reason]);
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//删除
	public function del(){
		$ids = input('post.ids/a');
		Db::name('scoreshop_product')->where('aid',aid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('积分商城商品删除'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	//选择商品
	public function chooseproduct(){
		//分类
		$clist = Db::name('scoreshop_category')->Field('id,name')->where('aid',aid)->where('status',1)->where('pid',0)->order('sort desc,id')->select()->toArray(); 
		foreach($clist as $k=>$v){
			$clist[$k]['child'] = Db::name('scoreshop_category')->Field('id,name')->where('aid',aid)->where('status',1)->where('pid',$v['id'])->order('sort desc,id')->select()->toArray(); 
		}
		View::assign('clist',$clist);

		$blist = [];
		View::assign('blist',$blist);

		return View::fetch();
	}
	//获取商品信息
	public function getproduct(){
		$proid = input('post.proid/d');
		$product = Db::name('scoreshop_product')->where('aid',aid)->where('id',$proid)->find();

		//多规格
		$newgglist = [];
		$guigedata = [];
		if($product['guigeset'] == 1){
			$gglist = Db::name('scoreshop_guige')->where('aid',aid)->where('proid',$product['id'])->select()->toArray();
			foreach($gglist as $k=>$v){
				$newgglist[$v['ks']] = $v;
			}
			$guigedata = json_decode($product['guigedata']);
		}else{
			$newgglist = [
				'0'=>['id'=>'0','name'=>'','pic'=>'','market_price'=>$product['sell_price'],'cost_price'=>$product['cost_price'],'money_price'=>$product['money_price'],'score_price'=>$product['score_price'],'weight'=>$product['weight'],'stock'=>$product['stock'],'ks'=>'0']
			];
			$guigedata = json_decode('[{"k":0,"title":"规格","items":[{"k":0,"title":"默认规格"}]}]',true);
		}
		$product['gglist'] = $newgglist;
		$product['guigedata'] = $guigedata;

		return json(['product'=>$product]);
	}

	//获取分类信息
	public function getcategory(){
		if(!session('BST_ID')) return json(['status'=>0,'msg'=>'无权限操作']);
		$toaid = input('param.toaid/d');
		//分类
		$clist = Db::name('scoreshop_category')->Field('id,name')->where('aid',$toaid)->where('pid',0)->order('sort desc,id')->select()->toArray(); 
		foreach($clist as $k=>$v){
			$child = Db::name('scoreshop_category')->Field('id,name')->where('aid',$toaid)->where('pid',$v['id'])->order('sort desc,id')->select()->toArray();
			$clist[$k]['child'] = $child;
		}
		return json(['status'=>1,'data'=>$clist]);
	}
	//复制到其他账号商品
	public function userProcopy(){
		if(!session('BST_ID')) return json(['status'=>0,'msg'=>'无权限操作']);
		$ids = input('post.ids/a');
		$toaid = input('param.toaid');
		$tocid = input('param.tocid');
		if(!$toaid) return json(['status'=>0,'msg'=>'请选择账号']);
		//if(!$tocid) return json(['status'=>0,'msg'=>'请选择分类']);
		if(!$tocid) $tocid = 0;
		if(!$ids) $ids = array(input('post.id/d'));
		$where = [];
		$where[] = ['aid','=',aid];
		//$where[] = ['bid','=',bid];
		$where[] = ['id','in',$ids];
		$prolist = Db::name('scoreshop_product')->where($where)->select()->toArray();
		if(!$prolist) return json(['status'=>0,'msg'=>'商品不存在,请重新选择']);

		foreach($prolist as $product){
			$data = $product;
			$data['aid'] = $toaid;
			$data['name'] = $data['name'];
			$data['status'] = 0;
			$data['cid'] = $tocid;
			unset($data['id']);
			$newproid = Db::name('scoreshop_product')->insertGetId($data);
		}
		return json(['status'=>1,'msg'=>'复制成功']);
	}
}
