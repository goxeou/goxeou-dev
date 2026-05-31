<?php


// +----------------------------------------------------------------------
// | 预约服务-商品管理
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;
class Yuyuelist extends Common
{
	//服务列表
    public function index(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
				$order = 'sort desc,id desc';
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

			$count = 0 + Db::name('yuyue_product')->where($where)->count();
			$data = Db::name('yuyue_product')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			$clist = Db::name('yuyue_category')->where('aid',aid)->select()->toArray();
			$cdata = array();
			foreach($clist as $c){
				$cdata[$c['id']] = $c['name'];
			}

			foreach($data as $k=>$v){
				$gglist = Db::name('yuyue_guige')->where('aid',aid)->where('proid',$v['id'])->select()->toArray();
				$ggdata = array();
				foreach($gglist as $gg){
					$ggdata[] = $gg['name'].' × '.$gg['stock'] .' <button class="layui-btn layui-btn-xs layui-btn-disabled">￥'.$gg['sell_price'].'</button>';
				}
				$v['cid'] = explode(',',$v['cid']);
                $data[$k]['cname'] = null;
                if ($v['cid']) {
                    foreach ($v['cid'] as $cid) {
                        if($data[$k]['cname'])
                            $data[$k]['cname'] .= ' ' . $cdata[$cid];
                        else
                            $data[$k]['cname'] .= $cdata[$cid];
                    }
                }
				$data[$k]['ggdata'] = implode('<br>',$ggdata);
				if($v['bid'] > 0){
					$data[$k]['bname'] = Db::name('business')->where('aid',aid)->where('id',$v['bid'])->value('name');
				}else{
					$data[$k]['bname'] = '平台自营';
				}
				if($v['status']==2){ //设置上架时间
					if(strtotime($v['start_time']) <= time() && strtotime($v['end_time']) >= time()){
						$data[$k]['status'] = 1;
					}else{
						$data[$k]['status'] = 0;
					}
				}
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		//分类
		$clist = Db::name('yuyue_category')->Field('id,name')->where('aid',aid)->where('bid',bid)->where('pid',0)->order('sort desc,id')->select()->toArray(); 
		foreach($clist as $k=>$v){
			$clist[$k]['child'] = Db::name('yuyue_category')->Field('id,name')->where('aid',aid)->where('bid',bid)->where('pid',$v['id'])->order('sort desc,id')->select()->toArray(); 
		}

		$set = Db::name('yuyue_set')->where('aid',aid)->where('bid',bid)->find();
		if(!$set){
			Db::name('yuyue_set')->insert(['aid'=>aid,'bid'=>bid]);
		}

		View::assign('clist',$clist);
		View::assign('sysset',$sysset);
		View::assign('systimeset',$systimeset);

		$this->defaultSet();
		return View::fetch();
    }
	//编辑商品
	public function edit(){
		if(input('param.id')){
			$info = Db::name('yuyue_product')->where('aid',aid)->where('id',input('param.id/d'))->find();
			$info['timepoint'] = explode(',',$info['timepoint']);
			if(!$info) showmsg('商品不存在');
			if(bid != 0 && $info['bid']!=bid) showmsg('无权限操作');
			$bid = $info['bid'];
		}else{
			$bid = bid;
		}
		$set = Db::name('yuyue_set')->where('aid',aid)->where('bid',$bid)->find();
		//多规格
		$newgglist = array();
		if($info){
			$gglist = Db::name('yuyue_guige')->where('aid',aid)->where('bid',$bid)->where('proid',$info['id'])->select()->toArray();
			foreach($gglist as $k=>$v){
				if($v['ks']!==null){
					$newgglist[$v['ks']] = $v;
				}else{
					Db::name('yuyue_guige')->where('aid',aid)->where('bid',$bid)->where('id',$v['id'])->update(['ks'=>$k]);
					$newgglist[$k] = $v;
				}
			}
		}else{
			$info = [];
			$info['danwei'] = '次';
			$info['yyzhouqi'] = '1,2,3,4,5,6,0';
			$info['fwtype'] = '1,2';
		}
		$info['gettj'] = explode(',',$info['gettj']);
		$info['fwtype'] = explode(',',$info['fwtype']);
        $default_cid = Db::name('member_level_category')->where('aid',aid)->where('isdefault', 1)->value('id');
        $default_cid = $default_cid ? $default_cid : 0;
        $aglevellist = Db::name('member_level')->where('aid',aid)->where('cid',$default_cid)->where('can_agent','<>',0)->order('sort,id')->select()->toArray();
		$levellist = Db::name('member_level')->where('aid',aid)->where('cid',$default_cid)->order('sort,id')->select()->toArray();
			
		



		//分类
		$clist = Db::name('yuyue_category')->Field('id,name,pid,appid')->where('aid',aid)->where('bid',$bid)->where('pid',0)->order('sort desc,id')->select()->toArray(); 
		foreach($clist as $k=>$v){
			if(false){}else{
				$pid = $v['id'];
			}
			$child = Db::name('yuyue_category')->Field('id,name')->where('aid',aid)->where('bid',$bid)->where('pid',$pid)->order('sort desc,id')->select()->toArray();
			
			$clist[$k]['child'] = $child;
		}
		//echo db('yuyue_category')->getlastsql();
		//服务分类
        $fwmap = [];
        $fwmap[] = ['aid','=',aid];
        $fwmap[] = ['status','=',1];
        //多商户使用平台服务人员
        $bid = bid;
        //使用平台的配送
        $fwmap[] = ['bid','=',$bid];
		$fwlist = Db::name('yuyue_worker')->Field('id,realname')->where($fwmap)->order('sort desc,id')->select()->toArray();

		$sysset = Db::name('yuyue_set')->where('aid',aid)->find();
        $info['cid'] = explode(',',$info['cid']);
		$info['fwpeoid'] = explode(',',$info['fwpeoid']);
		$info['yyzhouqi'] = explode(',',$info['yyzhouqi']);
		$info['couponids'] = explode(',',$info['couponids']);
		if(!$info['formdata']){
			$info['formdata'] = json_encode([
				['key'=>'input','val1'=>'备注','val2'=>'选填，请输入备注信息','val3'=>'0'],	
			]);
		}

	
		$fuwulist = Db::name('yuyue_fuwu')->where('aid',aid)->where('bid',$bid)->order('sort desc,id')->select()->toArray();
		if(!$info['yytimeday']){
			$info['yytimeday'] = ['1'];
		}else{
			$info['yytimeday'] = explode(',',$info['yytimeday']);
		}
		if(getcustom('yuyue_fenhong')){
			$gdlevellist = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->where('fenhong','>','0')->order('sort,id')->select()->toArray();
			$teamlevellist = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->where('teamfenhonglv','>','0')->order('sort,id')->select()->toArray();
			$areafhlevellist = Db::name('member_level')->where('aid',aid)->where('areafenhong','>','0')->select()->toArray();
			View::assign('gdlevellist',$gdlevellist);
			View::assign('teamlevellist',$teamlevellist);
			View::assign('areafhlevellist',$areafhlevellist);
		}

		$bset = Db::name('business_sysset')->where('aid',aid)->find();

		$business_selfscore = 0;
		View::assign('business_selfscore',$business_selfscore);
		View::assign('fuwulist',$fuwulist);
		View::assign('sysset',$sysset);
		View::assign('clist',$clist);
		View::assign('fwlist',$fwlist);
		View::assign('aglevellist',$aglevellist);
		View::assign('levellist',$levellist);
		View::assign('info',$info);
		View::assign('newgglist',$newgglist);
		View::assign('set',$set);
		View::assign('bset',$bset);
		View::assign('bid',bid);
		$text = ['上门服务'=>'上门服务','到店服务'=>'到店服务'];
		View::assign('text',$text);
		return View::fetch();
	}
	//保存商品
	public function save(){
		if(input('post.id')){
			$product = Db::name('yuyue_product')->where('aid',aid)->where('id',input('post.id/d'))->find();
			if(!$product) showmsg('商品不存在');
			if(bid != 0 && $product['bid']!=bid) showmsg('无权限操作');
			$bid = $product['bid'];
		}else{
			$bid = bid;
		}
		$info = input('post.info/a');
	
		$timepoint = implode(',',$info['timepoint']);
		$info['detail'] = \app\commons\Common::geteditorcontent($info['detail']);
		$data = array();
		$data['name'] = $info['name'];
		$data['pic'] = $info['pic'];
		$data['pics'] = $info['pics'];
		$data['fuwupoint'] = $info['fuwupoint'];
		$data['sellpoint'] = $info['sellpoint'];
		$data['procode'] = $info['procode'];
		$data['cid'] = $info['cid'];
		$data['perlimit'] = $info['perlimit'];
		if(isset($info['detail_text'])){
			$data['detail_text'] = $info['detail_text'];
		}
		if(isset($info['detail_pics'])){
			$data['detail_pics'] = $info['detail_pics'];
		}
		if($info['oldsales'] != $info['sales']){
			$data['sales'] = $info['sales'];
		}
		if($info['fwid']){
			$data['fwid'] = implode(',',$info['fwid']);
		}else{
			$data['fwid'] = '';
		}
		if(!$info['fwtype']) return json(['status'=>0,'msg'=>'请选择服务方式']);
		if($info['datetype'] == 1 && $info['timejg'] <= 0) return json(['status'=>0,'msg'=>'时间间隔必须大于0']);
		$data['sort'] = $info['sort'];
		$data['status'] = $info['status'];
		$data['detail'] = $info['detail'];
		$data['balance'] = $info['balance'];
		$data['zaohour'] = $info['zaohour'];
		$data['wanhour'] = $info['wanhour'];
		$data['fwtype'] =  implode(',',$info['fwtype']);
		$data['fwpeoid'] =  $info['fwpeoid'];
		$data['fwpeople'] = $info['fwpeople'];
		$data['fwlong'] = $info['fwlong'];
		$data['timejg'] = $info['timejg'];
		$data['yynum'] = $info['yynum'];
		if(!$product) $data['createtime'] = time();
		$data['jiesuantype'] = $info['jiesuantype'];
		$data['tcmoney'] = $info['tcmoney'];
		$data['tc_bfb'] = $info['tc_bfb'];
		$data['pdprehour'] = $info['pdprehour'];
		$data['timepoint'] = $timepoint;
		$data['datetype'] = $info['datetype'];
		$data['yyzhouqi'] =  implode(',',$info['yyzhouqi']);
		$data['start_time'] = $info['start_time'];
		$data['end_time'] = $info['end_time'];
		$data['rqtype'] = $info['rqtype'];
		$data['yybegintime'] = $info['yybegintime'];
		$data['yyendtime'] = $info['yyendtime'];
		$data['isareaxz'] = $info['isareaxz'];
		$data['tichengtype'] = $info['tichengtype'];
		if($info['citys']) $data['areadata'] = $info['citys'];

		$data['yytimeday'] = implode(',',$info['timeday']);
		if(getcustom('yuyue_commission')){  
			$data['minbuynum'] = $info['minbuynum']?$info['minbuynum']:1;
		}
		if(getcustom('yuyue_fenhong') && bid == 0){
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
		if(false){}else{
			$sellprice_field = 'sell_price';
		}
		$sell_price = 0;$market_price = 0;$cost_price = 0;$weight = 0;$givescore=0;
		foreach(input('post.option/a') as $ks=>$v){
			if($sell_price==0 || $v[$sellprice_field] < $sell_price){
				$sell_price = $v[$sellprice_field];
				$market_price = $v['market_price'];
				$givescore = $v['givescore'];
				}
		}
		$data['sell_price'] = $sell_price;
		$data['givescore'] = $givescore;
		$data['danwei'] = $info['danwei'];
		$data['stock'] = 0;
		foreach(input('post.option/a') as $v){
			$data['stock'] += $v['stock'];
		}
		//多规格 规格项
		$data['guigedata'] = input('post.specs');
		if(bid !=0 ){
			$bset = Db::name('business_sysset')->where('aid',aid)->find();
			if($bset['product_check'] == 1){
				$data['ischecked'] = 0;
			}
			}
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
		if(getcustom('yuyue_commission')){
			$data['commissionset'] = $info['commissionset'];
			$data['commissiondata1'] = jsonEncode(input('post.commissiondata1/a'));
			$data['commissiondata2'] = jsonEncode(input('post.commissiondata2/a'));
			$data['commissiondata3'] = jsonEncode(input('post.commissiondata3/a'));
		}

		$bcansetscore = false;//商家能否修改积分
		$data['is_open']   = $info['is_open']??0;
		$data['opentip']   = $info['opentip']??'';
		$data['noopentip'] = $info['noopentip']??'';
		if($product){
			Db::name('yuyue_product')->where('aid',aid)->where('id',$product['id'])->update($data);
			$proid = $product['id'];
			\app\commons\System::plog('服务商品编辑'.$proid);
		}else{
			$data['aid'] = aid;
			$data['bid'] = $bid;
			$proid = Db::name('yuyue_product')->insertGetId($data);
			\app\commons\System::plog('服务商品编辑'.$proid);
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
		$newggids = array();
		foreach(input('post.option/a') as $ks=>$v){
			$ggdata = array();
			$ggdata['bid'] = $bid;
			$ggdata['proid'] = $proid;
			$ggdata['ks'] = $ks;
			$ggdata['name'] = $v['name'];
			$ggdata['pic'] = $v['pic'] ? $v['pic'] : '';
			$ggdata['cost_price'] = $v['cost_price']>0 ? $v['cost_price']:0;
			$ggdata['sell_price'] = $v['sell_price']>0 ? $v['sell_price']:0;
			$ggdata['danwei'] = $v['danwei'];
			$ggdata['procode'] = $v['procode'];
			$ggdata['givescore'] = $v['givescore'];
			$ggdata['stock'] = $v['stock']>0 ? $v['stock']:0;
			$guige = Db::name('yuyue_guige')->where('aid',aid)->where('proid',$proid)->where('ks',$ks)->find();
			if($guige){
				Db::name('yuyue_guige')->where('aid',aid)->where('id',$guige['id'])->update($ggdata);
				$ggid = $guige['id'];
			}else{
				$ggdata['aid'] = aid;
				$ggid = Db::name('yuyue_guige')->insertGetId($ggdata);
			}
			$newggids[] = $ggid;
		}
		Db::name('yuyue_guige')->where('aid',aid)->where('proid',$proid)->where('id','not in',$newggids)->delete();
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
		Db::name('yuyue_product')->where($where)->update(['status'=>$st]);
		\app\commons\System::plog('服务商品改状态'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//审核
	public function setcheckst(){
		$st = input('post.st/d');
		$id = input('post.id/d');
		$reason = input('post.reason');
		Db::name('yuyue_product')->where('aid',aid)->where('id',$id)->update(['ischecked'=>$st,'check_reason'=>$reason]);
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	
	//删除
	public function del(){
		$ids = input('post.ids/a');
		if(!$ids) $ids = array(input('post.id/d'));
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['id','in',$ids];
		if(bid !=0){
			$where[] = ['bid','=',bid];
		}
		$prolist = Db::name('yuyue_product')->where($where)->select();
		foreach($prolist as $pro){
			Db::name('yuyue_product')->where('id',$pro['id'])->delete();
			Db::name('yuyue_guige')->where('proid',$pro['id'])->delete();
		}
		\app\commons\System::plog('服务商品删除'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	//复制商品
	public function procopy(){
		$product = Db::name('yuyue_product')->where('aid',aid)->where('bid',bid)->where('id',input('post.id/d'))->find();
		if(!$product) return json(['status'=>0,'msg'=>'商品不存在,请重新选择']);
		$gglist = Db::name('yuyue_guige')->where('aid',aid)->where('proid',$product['id'])->select()->toArray();
		$data = $product;
		$data['name'] = '复制-'.$data['name'];
		unset($data['id']);
		$data['status'] = 0;
		$newproid = Db::name('yuyue_product')->insertGetId($data);
		foreach($gglist as $gg){
			$ggdata = $gg;
			$ggdata['proid'] = $newproid;
			unset($ggdata['id']);
			Db::name('yuyue_guige')->insert($ggdata);
		}
		\app\commons\System::plog('服务商品复制'.$newproid);
		return json(['status'=>1,'msg'=>'复制成功','proid'=>$newproid]);
	}
	//选择商品
	public function chooseproduct(){
		//分类
		$clist = Db::name('yuyue_category')->Field('id,name')->where('aid',aid)->where('status',1)->where('pid',0)->order('sort desc,id')->select()->toArray(); 
		foreach($clist as $k=>$v){
			$clist[$k]['child'] = Db::name('yuyue_category')->Field('id,name')->where('aid',aid)->where('status',1)->where('pid',$v['id'])->order('sort desc,id')->select()->toArray(); 
		}
		View::assign('clist',$clist);
		return View::fetch();
	}
	//获取商品信息
	public function getproduct(){
		$proid = input('post.proid/d');
		$product = Db::name('yuyue_product')->where('aid',aid)->where('id',$proid)->find();
		//多规格
		$newgglist = array();
		$gglist = Db::name('yuyue_guige')->where('aid',aid)->where('proid',$product['id'])->select()->toArray();
		foreach($gglist as $k=>$v){
			$newgglist[$v['ks']] = $v;
		}
		$guigedata = json_decode($product['guigedata']);
		return json(['product'=>$product,'gglist'=>$newgglist,'guigedata'=>$guigedata]);
	}
    function defaultSet(){
        $set = Db::name('yuyue_set')->where('aid',aid)->where('bid',bid)->find();
        if(!$set){
            Db::name('yuyue_set')->insert(['aid'=>aid,'bid' => bid]);
        }
    }
}
