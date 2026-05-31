<?php


// +----------------------------------------------------------------------
// | 拼团商城-商品管理
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class LuckyCollageProduct extends Common
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

			$count = 0 + Db::name('lucky_collage_product')->where($where)->count();
			$data = Db::name('lucky_collage_product')->where($where)->page($page,$limit)->order($order)->select()->toArray();

			$clist = Db::name('lucky_collage_category')->where('aid',aid)->select()->toArray();
			$cdata = array();
			foreach($clist as $c){
				$cdata[$c['id']] = $c['name'];
			}
			foreach($data as $k=>$v){
				$gglist = Db::name('lucky_collage_guige')->where('aid',aid)->where('proid',$v['id'])->select()->toArray();
				$ggdata = array();
				foreach($gglist as $gg){
					$ggdata[] = $gg['name'].' × '.$gg['stock'] .' <button class="layui-btn layui-btn-xs layui-btn-disabled">￥'.$gg['sell_price'].'</button>';
				}
				$data[$k]['cname'] = $cdata[$v['cid']];
				$data[$k]['ggdata'] = implode('<br>',$ggdata);
				if($v['bid'] > 0){
					$data[$k]['bname'] = Db::name('business')->where('aid',aid)->where('id',$v['bid'])->value('name');
				}else{
					$data[$k]['bname'] = '平台自营';
				}
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		//分类
		$clist = Db::name('lucky_collage_category')->Field('id,name')->where('aid',aid)->where('status',1)->where('pid',0)->order('sort desc,id')->select()->toArray(); 
		foreach($clist as $k=>$v){
			$clist[$k]['child'] = Db::name('lucky_collage_category')->field('id,name')->where('aid',aid)->where('status',1)->where('pid',$v['id'])->order('sort desc,id')->select()->toArray(); 
		}
		View::assign('clist',$clist);
        $this->defaultSet();
		return View::fetch();
    }
	//编辑商品
	public function edit(){
		if(input('param.id')){
			$info = Db::name('lucky_collage_product')->where('aid',aid)->where('id',input('param.id/d'))->find();
			if(!$info) showmsg('商品不存在');
			if(bid != 0 && $info['bid']!=bid) showmsg('无权限操作');
		}
		//多规格
		$newgglist = array();
		if($info){
			$gglist = Db::name('lucky_collage_guige')->where('aid',aid)->where('proid',$info['id'])->select()->toArray();
			foreach($gglist as $k=>$v){
				if($v['ks']!==null){
					$newgglist[$v['ks']] = $v;
				}else{
					Db::name('lucky_collage_guige')->where('aid',aid)->where('id',$v['id'])->update(['ks'=>$k]);
					$newgglist[$k] = $v;
				}
			}
            $info['leaderscore'] = dd_money_format($info['leaderscore'],$this->score_weishu);
            $commissiondata3 = json_decode($info['commissiondata3'],true);
            foreach($commissiondata3 as $levelid=>$commission){
                $commissiondata3[$levelid]['commission1'] = dd_money_format($commission['commission1'],$this->score_weishu);
                $commissiondata3[$levelid]['commission2'] = dd_money_format($commission['commission2'],$this->score_weishu);
                $commissiondata3[$levelid]['commission3'] = dd_money_format($commission['commission3'],$this->score_weishu);
            }
            $info['commissiondata3'] = jsonEncode($commissiondata3);
		}
		//分类
		$clist = Db::name('lucky_collage_category')->field('id,name')->where('aid',aid)->where('status',1)->where('pid',0)->order('sort desc,id')->select()->toArray(); 
		foreach($clist as $k=>$v){
			$clist[$k]['child'] = Db::name('lucky_collage_category')->field('id,name')->where('aid',aid)->where('status',1)->where('pid',$v['id'])->order('sort desc,id')->select()->toArray(); 
		}
		$freightdata = array();
		if($info && $info['freightdata']){
			$freightdata = Db::name('freight')->where('aid',aid)->where('id','in',$info['freightdata'])->order('sort desc,id')->select()->toArray();
		}

		if(false){}else {
            $aglevellist = Db::name('member_level')->where('aid',aid)->where('can_agent','<>',0)->order('sort,id')->select()->toArray();
            $levellist = Db::name('member_level')->where('aid',aid)->order('sort,id')->select()->toArray();
        }

		$info['kaituan_time'] = explode(',',$info['kaituan_time']);
		
		$teamnumCanedit = true;
		if($info['id'] && Db::name('lucky_collage_order')->where('proid',$info['id'])->find()){
			$teamnumCanedit = false;
		}
		
		
		View::assign('teamnumCanedit',$teamnumCanedit);

 		View::assign('aglevellist',$aglevellist);
		View::assign('levellist',$levellist);
		View::assign('info',$info);
		View::assign('newgglist',$newgglist);
		View::assign('clist',$clist);
		View::assign('freightdata',$freightdata);
	
        View::assign('tzcouponList',$tzcouponList);
		return View::fetch();
	}
	//保存商品
	public function save(){
		if(input('post.id')){
			$product = Db::name('lucky_collage_product')->where('aid',aid)->where('id',input('post.id/d'))->find();
			if(!$product) showmsg('商品不存在');
			if(bid != 0 && $product['bid']!=bid) showmsg('无权限操作');
		}

		$info = input('post.info/a');
		$info['detail'] = \app\commons\Common::geteditorcontent($info['detail']);
		$data = array();
		$data['name'] = $info['name'];
		$data['pic'] = $info['pic'];
		$data['pics'] = $info['pics'];
		$data['fy_type'] = $info['fy_type'];
		$data['fy_money'] = $info['fy_money'];
		$data['fy_money_val'] = $info['fy_money_val'];
		$data['gua_num'] = $info['gua_num'];
		$data['red_give_mode'] = $info['red_give_mode'];
		$data['tklx'] = $info['tklx'];
		$data['failtklx'] = $info['failtklx'];
		$data['cid'] = $info['cid'];
		$data['teamnum'] = $info['teamnum'];
		$data['buymax'] = $info['buymax'];
		$data['teamhour'] = $info['teamhour'];
		$data['leadermoney'] = $info['leadermoney'];
		$data['leaderscore'] = $info['leaderscore'];
		if($info['oldsales'] != $info['sales']){
			$data['sales'] = $info['sales'];
		}
		$data['sort'] = $info['sort'];
		$data['status'] = $info['status'];
		$data['detail'] = $info['detail'];
        $data['freightcontent'] = $info['freightcontent'];
        $data['freighttype'] = $info['freighttype'];
		$data['freightdata'] = $info['freightdata'];
        $data['contact_require'] = intval($info['contact_require']);
		if($info['gid']){
			$data['gid'] = implode(',',$info['gid']);
		}
		$data['commissionset'] = $info['commissionset'];
		$data['commissiondata1'] = jsonEncode(input('post.commissiondata1/a'));
		$data['commissiondata2'] = jsonEncode(input('post.commissiondata2/a'));
		$data['commissiondata3'] = jsonEncode(input('post.commissiondata3/a'));
		
		$data['perlimitdan'] = $info['perlimitdan'];
		if(!$product) $data['createtime'] = time();
		$sell_price = 0;$market_price = 0;$cost_price = 0;$weight = 0;
		foreach(input('post.option/a') as $ks=>$v){
			if($sell_price==0 || $v['sell_price'] < $sell_price){
				$sell_price = $v['sell_price'];
				$market_price = $v['market_price'];
				$cost_price = $v['cost_price'];
				$weight = $v['weight'];
			}
		}
		$data['market_price'] = $market_price;
		$data['cost_price'] = $cost_price;
		$data['sell_price'] = $sell_price;
		$data['weight'] = $weight;
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
		if($product){
			Db::name('lucky_collage_product')->where('aid',aid)->where('id',$product['id'])->update($data);
			$proid = $product['id'];
			\app\commons\System::plog('编辑拼团商品'.$proid);
		}else{
			$data['aid'] = aid;
			$data['bid'] = bid;
			$proid = Db::name('lucky_collage_product')->insertGetId($data);
			\app\commons\System::plog('添加拼团商品'.$proid);
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
		//dump(input('post.option/a'));die;
		//多规格
		$newggids = array();
		foreach(input('post.option/a') as $ks=>$v){
			$ggdata = array();
			$ggdata['proid'] = $proid;
			$ggdata['ks'] = $ks;
			$ggdata['name'] = $v['name'];
			$ggdata['pic'] = $v['pic'] ? $v['pic'] : '';
			$ggdata['market_price'] = $v['market_price']>0 ? $v['market_price']:0;
			$ggdata['cost_price'] = $v['cost_price']>0 ? $v['cost_price']:0;
			$ggdata['sell_price'] = $v['sell_price']>0 ? $v['sell_price']:0;
			$ggdata['weight'] = $v['weight']>0 ? $v['weight']:0;
			$ggdata['procode'] = $v['procode'];
			$ggdata['stock'] = $v['stock']>0 ? $v['stock']:0;
			$guige = Db::name('lucky_collage_guige')->where('aid',aid)->where('proid',$proid)->where('ks',$ks)->find();
			if($guige){
				Db::name('lucky_collage_guige')->where('aid',aid)->where('id',$guige['id'])->update($ggdata);
				$ggid = $guige['id'];
			}else{
				$ggdata['aid'] = aid;
				$ggid = Db::name('lucky_collage_guige')->insertGetId($ggdata);
			}
			$newggids[] = $ggid;
		}
		Db::name('lucky_collage_guige')->where('aid',aid)->where('proid',$proid)->where('id','not in',$newggids)->delete();
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
		Db::name('lucky_collage_product')->where($where)->update(['status'=>$st]);

		\app\commons\System::plog('拼团商品修改状态'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//审核
	public function setcheckst(){
		$st = input('post.st/d');
		$id = input('post.id/d');
		$reason = input('post.reason');
		Db::name('lucky_collage_product')->where('aid',aid)->where('id',$id)->update(['ischecked'=>$st,'check_reason'=>$reason]);
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
		Db::name('lucky_collage_product')->where($where)->delete();
		\app\commons\System::plog('拼团商品删除'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	//复制商品
	public function procopy(){
		$product = Db::name('lucky_collage_product')->where('aid',aid)->where('bid',bid)->where('id',input('post.id/d'))->find();
		if(!$product) return json(['status'=>0,'msg'=>'商品不存在,请重新选择']);
		$gglist = Db::name('lucky_collage_guige')->where('aid',aid)->where('proid',$product['id'])->select()->toArray();
		$data = $product;
		$data['name'] = '复制-'.$data['name'];
		unset($data['id']);
		$data['status'] = 0;
		$newproid = Db::name('lucky_collage_product')->insertGetId($data);
		foreach($gglist as $gg){
			$ggdata = $gg;
			$ggdata['proid'] = $newproid;
			unset($ggdata['id']);
			Db::name('lucky_collage_guige')->insert($ggdata);
		}
		\app\commons\System::plog('复制拼团商品'.$newproid);
		return json(['status'=>1,'msg'=>'复制成功','proid'=>$newproid]);
	}
	
	//选择商品
	public function chooseproduct(){
		//分类
		$clist = Db::name('lucky_collage_category')->Field('id,name')->where('aid',aid)->where('status',1)->where('pid',0)->order('sort desc,id')->select()->toArray(); 
		foreach($clist as $k=>$v){
			$clist[$k]['child'] = Db::name('lucky_collage_category')->Field('id,name')->where('aid',aid)->where('status',1)->where('pid',$v['id'])->order('sort desc,id')->select()->toArray(); 
		}
		View::assign('clist',$clist);
		return View::fetch();
	}
	//获取商品信息
	public function getproduct(){
		$proid = input('post.proid/d');
		$product = Db::name('lucky_collage_product')->where('aid',aid)->where('id',$proid)->find();
		$product['money'] = $product['fy_money_val'];
		if($product['fy_type']==1){
			$product['money'] = $product['sell_price']*$product['fy_money'];
		}
		//多规格
		$newgglist = array();
		$gglist = Db::name('lucky_collage_guige')->where('aid',aid)->where('proid',$product['id'])->select()->toArray();
		foreach($gglist as $k=>$v){
			$newgglist[$v['ks']] = $v;
		}
		$guigedata = json_decode($product['guigedata']);
		return json(['product'=>$product,'gglist'=>$newgglist,'guigedata'=>$guigedata]);
	}
	public function kaituan(){
		$buytype = input('param.buytype');
		if($buytype==2){
			$proid = input('param.id');
			$product = Db::name('lucky_collage_product')->where('aid',aid)->where('id',$proid)->find();
		}
		if($buytype==3){
			$teamid = input('param.teamid');	
			$orderlist = Db::name('lucky_collage_order')->where(['teamid'=>$teamid])->where('status',1)->select()->toArray();
			$tuan = Db::name('lucky_collage_order_team')->where('aid',aid)->where('id',$teamid)->find();
			$product = Db::name('lucky_collage_product')->where('aid',aid)->where('id',$tuan['proid'])->find();
			//把已经参加的机器人读取出来
			$jiqirenlist = Db::name('lucky_collage_order')->field('mid')->where('aid',aid)->where('teamid',$teamid)->where('isjiqiren',1)->select()->toArray();
			$jiqirenlist1 = [];
			if($jiqirenlist){
				$jiqirenlist1 = implode(',',array_column($jiqirenlist, 'mid'));		
			}
			$jiqiren = Db::name('lucky_collage_jiqilist')->where('aid',aid)->where('id','not in',$jiqirenlist1)->orderRaw('rand()')->find();
            if(empty($jiqiren)){
                return json(['status'=>0,'msg'=>'机器人未设置或可使用数量不足']);
            }
			if(!$tuan || $tuan['status']==0){
				return json(['status'=>0,'msg'=>'没有找到该团']);
			}
			if($tuan['status']==3){
				return json(['status'=>0,'msg'=>'该团已失败']);
			}
			if($tuan['num'] >= $tuan['teamnum']){
				return json(['status'=>0,'msg'=>'该团已满员']);
			}
			$tdata = [];
			$tdata['num'] = $tuan['num']+1;
			Db::name('lucky_collage_order_team')->where('aid',aid)->where('id',$teamid)->update($tdata);
		}
		$jiqiren = Db::name('lucky_collage_jiqilist')->where('aid',aid)->orderRaw('rand()')->find();
        if(empty($jiqiren)){
            return json(['status'=>0, 'msg'=>'请前去机器人管理处先添加机器人']);
        }
		$guige = Db::name('lucky_collage_guige')->where('aid',aid)->where('proid',$product['id'])->find();
		if($buytype ==2){//创建团
			$tdata = [];
			$tdata['aid'] = aid;
			$tdata['bid'] = bid;
			$tdata['mid'] = $jiqiren['id'];
			$tdata['proid'] = $product['id'];
			$tdata['teamhour'] = $product['teamhour'];
			$tdata['teamnum'] = $product['teamnum'];
			$tdata['status'] = 1;
			$tdata['num'] = 1;
			$tdata['createtime'] = time();
			$teamid = Db::name('lucky_collage_order_team')->insertGetId($tdata);
		}

		$orderdata = [];
		$orderdata['aid'] = aid;
		$orderdata['bid'] = bid;
		$orderdata['mid'] = $jiqiren['id'];
		$ordernum = date('ymdHis').aid.rand(1000,9999);
		$orderdata['ordernum'] = $ordernum;
		$orderdata['title'] = $product['name'];
		$orderdata['proid'] = $product['id'];
		$orderdata['proname'] = $product['name'];
		$orderdata['propic'] = $product['pic'];
		$orderdata['ggid'] = $guige['id'];
		$orderdata['ggname'] = $guige['name'];
		$orderdata['cost_price'] = $guige['cost_price'];
		$orderdata['sell_price'] = $guige['sell_price'];
		$orderdata['num'] = 1;
		$orderdata['linkman'] = '';
		$orderdata['tel'] = '';
		$orderdata['area'] = '';
		$orderdata['area2'] ='';
		$orderdata['address'] = '';
		$orderdata['longitude'] = '';
		$orderdata['latitude'] = '';
		$orderdata['totalprice'] =  $product['sell_price'];
		$orderdata['product_price'] = $product['sell_price'];
		$orderdata['freight_price'] = 0; //运费
		$orderdata['leveldk_money'] = 0;  //会员折扣
		$orderdata['scoredk_money'] = 0;	//积分抵扣
		$orderdata['scoredkscore'] = 0;	//抵扣的积分
		$orderdata['createtime'] = time();
		$freight = Db::name('freight')->where('aid',aid)->where('bid',bid)->find();
		if($freight && ($freight['pstype']==0 || $freight['pstype']==10)){
			$orderdata['freight_text'] = $freight['name'].'('.$freight_price.'元)';
			$orderdata['freight_type'] = $freight['pstype'];
		}elseif($freight && $freight['pstype']==1){
			$storename = Db::name('mendian')->where('aid',aid)->value('name');
			$orderdata['freight_text'] = $freight['name'].'['.$storename.']';
			$orderdata['freight_type'] = 1;
		}elseif($freight && $freight['pstype']==2){
			$orderdata['freight_text'] = $freight['name'].'('.$freight_price.'元)';
			$orderdata['freight_type'] = 2;
		}elseif($freight && ($freight['pstype']==3 || $freight['pstype']==4)){ //自动发货 在线卡密
			$orderdata['freight_text'] = $freight['name'];
			$orderdata['freight_type'] = $freight['pstype'];
		}else{
			$orderdata['freight_text'] = '包邮';
		}
		$orderdata['isjiqiren'] = 1;
		$orderdata['status'] = 1;
		$orderdata['paytype'] = '后台支付';
		$orderdata['freight_id'] = $freight['id'];
		$orderdata['freight_time'] = ''; //配送时间
		$orderdata['buytype'] = $buytype; //1单买 2发团 3参团
		$orderdata['teamid'] = $teamid;
		$orderdata['hexiao_code'] = random(16);
		$orderdata['hexiao_qr'] = createqrcode(m_url('admin/hexiao/hexiao?type=lucky_collage&co='.$orderdata['hexiao_code']));
		$orderdata['platform'] = 'wx';
		$orderid = Db::name('lucky_collage_order')->insertGetId($orderdata);
		$team = Db::name('lucky_collage_order_team')->where('id',$teamid)->find();
		$tdata = [];
		if($team['num'] >= $team['teamnum']){	
			$tdata['status'] = 2;
			Db::name('lucky_collage_order_team')->where('aid',aid)->where('id',$teamid)->update($tdata);
			$order = Db::name('lucky_collage_order')->where('aid',aid)->where('teamid',$teamid)->where('status',1)->order('id desc')->find();
			\app\models\LuckyCollage::kaijiang($order);

		}else{
			$tdata['status'] = 1;
			Db::name('lucky_collage_order_team')->where('aid',aid)->where('id',$teamid)->update($tdata);
		}
		
		$msg = $buytype==2?'开团成功':'参团成功';
		return json(['status'=>1,'orderid'=>$orderid,'msg'=>$msg]);

	}
    function defaultSet(){
        $set = Db::name('lucky_collage_sysset')->where('aid',aid)->find();
        if(!$set){
            Db::name('lucky_collage_sysset')->insert(['aid'=>aid]);
        }
    }
}