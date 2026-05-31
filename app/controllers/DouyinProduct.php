<?php


// +----------------------------------------------------------------------
// | 抖店-商品管理
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;
use app\commons\Wechat;
class DouyinProduct extends Common
{
	//商品列表
    public function index(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
				$order = 'sort desc,createtime desc';
			}
			$where = array();
			$where[] = ['aid','=',aid];
			if(bid==0){
				if(input('param.bid')){
					$where[] = ['bid','=',input('param.bid')];
				}elseif(input('param.showtype')==2){
					$where[] = ['bid','>',0];
					$where[] = ['linkid','=',0];
				}elseif(input('param.showtype')==21){
					$where[] = ['bid','=',-1];
                }elseif(input('param.showtype')=='all'){
                    $where[] = ['bid','>=',0];
				}else{
					$where[] = ['bid','=',0];
				}
			}else{
				$where[] = ['bid','=',bid];
			}
			$where[] = ['douyin_product_id','<>',''];
			if(input('param.name')) $where[] = ['name','like','%'.$_GET['name'].'%'];
			if(input('?param.status') && input('param.status')!==''){
				$status = input('param.status');
				$nowtime = time();
				$nowhm = date('H:i');
				if($status==1){
					$where[] = Db::raw("`status`=1 or (`status`=2 and unix_timestamp(start_time)<=$nowtime and unix_timestamp(end_time)>=$nowtime) or (`status`=3 and ((start_hours<end_hours and start_hours<='$nowhm' and end_hours>='$nowhm') or (start_hours>=end_hours and (start_hours<='$nowhm' or end_hours>='$nowhm'))) )");
				}else{
					$where[] = Db::raw("`status`=0 or (`status`=2 and (unix_timestamp(start_time)>$nowtime or unix_timestamp(end_time)<$nowtime)) or (`status`=3 and ((start_hours<end_hours and (start_hours>'$nowhm' or end_hours<'$nowhm')) or (start_hours>=end_hours and (start_hours>'$nowhm' and end_hours<'$nowhm'))) )");
				}
			}
            if(input('?param.cid') && input('param.cid')!==''){
				$cid = input('param.cid');
				//子分类
				$clist = Db::name('shop_category')->where('aid',aid)->where('pid',$cid)->column('id');
				if($clist){
					$clist2 = Db::name('shop_category')->where('aid',aid)->where('pid','in',$clist)->column('id');
					$cCate = array_merge($clist, $clist2, [$cid]);
					if($cCate){
						$whereCid = [];
						foreach($cCate as $k => $c2){
							$whereCid[] = "find_in_set({$c2},cid)";
						}
						$where[] = Db::raw(implode(' or ',$whereCid));
					}
				} else {
					$where[] = Db::raw("find_in_set(".$cid.",cid)");
				}
			}
            if(input('?param.cid2') && input('param.cid2')!==''){
				$cid = input('param.cid2');
				//子分类
				$clist = Db::name('shop_category2')->where('aid',aid)->where('pid',$cid)->column('id');
				if($clist){
					$clist2 = Db::name('shop_category2')->where('aid',aid)->where('pid','in',$clist)->column('id');
					$cCate = array_merge($clist, $clist2, [$cid]);
					if($cCate){
						$whereCid = [];
						foreach($cCate as $k => $c2){
							$whereCid[] = "find_in_set({$c2},cid2)";
						}
						$where[] = Db::raw(implode(' or ',$whereCid));
					}
				} else {
					$where[] = Db::raw("find_in_set(".$cid.",cid2)");
				}
			}
			if(input('?param.gid') && input('param.gid')!=='') $where[] = Db::raw("find_in_set(".input('param.gid/d').",gid)");

			$count = 0 + Db::name('shop_product')->where($where)->count();
			$data = Db::name('shop_product')->where($where)->page($page,$limit)->order($order)->select()->toArray();

			$cdata = Db::name('shop_category')->where('aid',aid)->column('name','id');
			if(bid > 0){
				$cdata2 = Db::name('shop_category2')->Field('id,name')->where('aid',aid)->where('bid',bid)->order('sort desc,id')->column('name','id');
			}
			foreach($data as $k=>$v){
				$gglist = Db::name('shop_guige')->where('aid',aid)->where('proid',$v['id'])->select()->toArray();
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
				if($v['bid'] > 0){
					$v['cid2'] = explode(',',$v['cid2']);
					$data[$k]['cname2'] = null;
					if ($v['cid2']) {
						foreach ($v['cid2'] as $cid) {
							if($data[$k]['cname2'])
								$data[$k]['cname2'] .= ' ' . $cdata2[$cid];
							else
								$data[$k]['cname2'] .= $cdata2[$cid];
						}
					}
					$data[$k]['bname'] = Db::name('business')->where('aid',aid)->where('id',$v['bid'])->value('name');
				}else{
					$data[$k]['cname2'] = '';
					$data[$k]['bname'] = '平台自营';
				}
				$data[$k]['ggdata'] = implode('<br>',$ggdata);
				$data[$k]['realsalenum'] = Db::name('shop_order_goods')->where('aid',aid)->where('proid',$v['id'])->where('status','in','1,2,3')->sum('num');
				if($v['status']==2){ //设置上架时间
					if(strtotime($v['start_time']) <= time() && strtotime($v['end_time']) >= time()){
						$data[$k]['status'] = 1;
					}else{
						$data[$k]['status'] = 0;
					}
				}
				if($v['status']==3){ //设置上架周期
					$start_time = strtotime(date('Y-m-d '.$v['start_hours']));
					$end_time = strtotime(date('Y-m-d '.$v['end_hours']));
					if(($start_time < $end_time && $start_time <= time() && $end_time >= time()) || ($start_time >= $end_time && ($start_time <= time() || $end_time >= time()))){
						$data[$k]['status'] = 1;
					}else{
						$data[$k]['status'] = 0;
					}
				}
				if($v['bid'] == -1) $data[$k]['sort'] = $v['sort'] - 1000000;
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		//分类
		$clist = Db::name('shop_category')->Field('id,name')->where('aid',aid)->where('pid',0)->order('sort desc,id')->select()->toArray(); 
		foreach($clist as $k=>$v){
			$clist[$k]['child'] = Db::name('shop_category')->Field('id,name')->where('aid',aid)->where('pid',$v['id'])->order('sort desc,id')->select()->toArray(); 
		}
		if(bid > 0){
			//商家的商品分类
			$clist2 = Db::name('shop_category2')->Field('id,name')->where('aid',aid)->where('bid',bid)->where('pid',0)->order('sort desc,id')->select()->toArray(); 
			foreach($clist2 as $k=>$v){
				$clist2[$k]['child'] = Db::name('shop_category')->Field('id,name')->where('aid',aid)->where('pid',$v['id'])->order('sort desc,id')->select()->toArray(); 
			}
			View::assign('clist2',$clist2);
		}
		//分组
		$glist = Db::name('shop_group')->where('aid',aid)->order('sort desc,id')->select()->toArray();
		View::assign('clist',$clist);
		View::assign('glist',$glist);

		return View::fetch();
    }
	//编辑商品
	public function edit(){
		if(input('param.id')){
			$info = Db::name('shop_product')->where('aid',aid)->where('id',input('param.id/d'))->find();
			if(!$info) showmsg('商品不存在');
			if(bid != 0 && $info['bid']!=bid) showmsg('无权限操作');
			if(bid != 0 && $info['linkid']!=0) showmsg('无权限操作');
		}
		//分类
		$clist = Db::name('shop_category')->Field('id,name')->where('aid',aid)->where('pid',0)->order('sort desc,id')->select()->toArray(); 
		foreach($clist as $k=>$v){
			$child = Db::name('shop_category')->Field('id,name')->where('aid',aid)->where('pid',$v['id'])->order('sort desc,id')->select()->toArray();
			foreach($child as $k2=>$v2){
				$child2 = Db::name('shop_category')->Field('id,name')->where('aid',aid)->where('pid',$v2['id'])->order('sort desc,id')->select()->toArray();
				$child[$k2]['child'] = $child2;
			}
			$clist[$k]['child'] = $child;
		}
		//分组
		$glist = Db::name('shop_group')->where('aid',aid)->order('sort desc,id')->select()->toArray();
		$freightdata = array();
		if($info && $info['freightdata']){
			$freightdata = Db::name('freight')->where('aid',aid)->where('id','in',$info['freightdata'])->order('sort desc,id')->select()->toArray();
		}
		$info['cid'] = explode(',',$info['cid']);

		View::assign('info',$info);
		View::assign('clist',$clist);
		View::assign('glist',$glist);

		return View::fetch();
	}
	//保存商品
	public function save(){
		if(input('post.id')){
			$product = Db::name('shop_product')->where('aid',aid)->where('id',input('post.id/d'))->find();
			if(!$product) showmsg('商品不存在');
			if(bid != 0 && $product['bid']!=bid) showmsg('无权限操作');
		}
		$info = input('post.info/a');
		$data = array();
		
		$data['cid'] = $info['cid'];
		$data['sort'] = $info['sort'];
		$data['status'] = $info['status'];
		$data['start_time'] = $info['start_time'];
		$data['end_time'] = $info['end_time'];
		$data['start_hours'] = $info['start_hours'];
		$data['end_hours'] = $info['end_hours'];

		if($info['gid']){
			$data['gid'] = implode(',',$info['gid']);
		}else{
			$data['gid'] = '';
		}
		if(!$product) $data['createtime'] = time();
		
		$data['sales'] = $info['sales'];
		$data['stock'] = $info['stock'];
		if($product){
			Db::name('shop_product')->where('aid',aid)->where('id',$product['id'])->update($data);
		}else{
			$data['aid'] = aid;
			$data['bid'] = bid;
			$proid = Db::name('shop_product')->insertGetId($data);
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
			$where[] = ['linkid','=',0];
		}
		Db::name('shop_product')->where($where)->update(['status'=>$st]);
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//审核
	public function setcheckst(){
		$st = input('post.st/d');
		$id = input('post.id/d');
		$reason = input('post.reason');
		Db::name('shop_product')->where('aid',aid)->where('id',$id)->update(['ischecked'=>$st,'check_reason'=>$reason]);
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
			$where[] = ['linkid','=',0];
		}
		$prolist = Db::name('shop_product')->where($where)->select();
		foreach($prolist as $pro){
			Db::name('shop_product')->where('id',$pro['id'])->delete();
			Db::name('shop_guige')->where('proid',$pro['id'])->delete();
			}
		\app\commons\System::plog('商城商品删除'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	//复制商品
	public function procopy(){
		$product = Db::name('shop_product')->where('aid',aid)->where('bid',bid)->where('id',input('post.id/d'))->find();
		if(!$product) return json(['status'=>0,'msg'=>'商品不存在,请重新选择']);
		$gglist = Db::name('shop_guige')->where('aid',aid)->where('proid',$product['id'])->select()->toArray();
		$data = $product;
		$data['name'] = '复制-'.$data['name'];
		unset($data['id']);
		unset($data['wxvideo_product_id']);
		unset($data['wxvideo_edit_status']);
		unset($data['wxvideo_status']);
		unset($data['wxvideo_reject_reason']);
		$data['status'] = 0;
		$newproid = Db::name('shop_product')->insertGetId($data);
		foreach($gglist as $gg){
			$ggdata = $gg;
			$ggdata['proid'] = $newproid;
			unset($ggdata['id']);
			unset($ggdata['linkid']);
			Db::name('shop_guige')->insert($ggdata);
		}
		$this->tongbuproduct($newproid);
		\app\commons\System::plog('商城商品复制'.$newproid);
		return json(['status'=>1,'msg'=>'复制成功','proid'=>$newproid]);
	}
	//获取分类信息
	public function getcategory(){
		if(!session('BST_ID')) return json(['status'=>0,'msg'=>'无权限操作']);
		$toaid = input('param.toaid/d');
		//分类
		$clist = Db::name('shop_category')->Field('id,name')->where('aid',$toaid)->where('pid',0)->order('sort desc,id')->select()->toArray(); 
		foreach($clist as $k=>$v){
			$child = Db::name('shop_category')->Field('id,name')->where('aid',$toaid)->where('pid',$v['id'])->order('sort desc,id')->select()->toArray();
			foreach($child as $k2=>$v2){
				$child2 = Db::name('shop_category')->Field('id,name')->where('aid',$toaid)->where('pid',$v2['id'])->order('sort desc,id')->select()->toArray();
				$child[$k2]['child'] = $child2;
			}
			$clist[$k]['child'] = $child;
		}
		return json(['status'=>1,'data'=>$clist]);
	}
	
	//选择商品
	public function chooseproduct(){
		//分类
		$clist = Db::name('shop_category')->Field('id,name')->where('aid',aid)->where('pid',0)->order('sort desc,id')->select()->toArray(); 
		foreach($clist as $k=>$v){
			$clist[$k]['child'] = Db::name('shop_category')->Field('id,name')->where('aid',aid)->where('pid',$v['id'])->order('sort desc,id')->select()->toArray(); 
		}
		//分组
		$glist = Db::name('shop_group')->where('aid',aid)->order('sort desc,id')->select()->toArray();
		//商户
		$blist = Db::name('business')->where('aid',aid)->order('sort desc,id desc')->select()->toArray();
		View::assign('blist',$blist);
		View::assign('clist',$clist);
		View::assign('glist',$glist);
		return View::fetch();
	}
	//获取商品信息
	public function getproduct(){
		$proid = input('post.proid/d');
		$product = Db::name('shop_product')->where('aid',aid)->where('id',$proid)->find();
		//多规格
		$newgglist = array();
		$gglist = Db::name('shop_guige')->where('aid',aid)->where('proid',$product['id'])->select()->toArray();
		foreach($gglist as $k=>$v){
			$newgglist[$v['ks']] = $v;
		}
		$guigedata = json_decode($product['guigedata']);
		return json(['product'=>$product,'gglist'=>$newgglist,'guigedata'=>$guigedata]);
	}
	
	public function getDouyinCategory(){
		$cid = input('param.cid');
		if(!$cid) $cid = 0;
		$rs = \app\commons\Douyin::get_shop_category(aid,$cid);
		return json($rs);
	}
	public function getDouyinCategoryProperty(){
		$cid = input('param.cid');
		if(!$cid) $cid = 0;
		$rs = \app\commons\Douyin::get_category_property(aid,$cid);
		return json($rs);
	}
	//抖音上架
	public function douyin_setOnline(){
		$proid = input('param.proid/d');
		$rs = \app\commons\Douyin::setOnline(aid,$proid);
		return json($rs);
	}
	//抖音下架
	public function douyin_setOffline(){
		$proid = input('param.proid/d');
		$rs = \app\commons\Douyin::setOffline(aid,$proid);
		return json($rs);
	}
	//抖音删除商品
	public function douyin_del(){
		$proid = input('param.proid/d');
		$rs = \app\commons\Douyin::delProduct(aid,$proid);
		return json($rs);
	}
	//从抖店同步
	public function updatefromdouyin(){
		$rs = \app\commons\Douyin::updatefromdouyin(aid);
		if($rs['status'] == 0) return json($rs);
		var_dump($rs['data']);
		foreach($rs['data']['data'] as $info){
			if($info['status'] == 2) continue;
			$data = [];
			$data['aid'] = aid;
			$data['name'] = $info['name'];
			$data['pic'] = $info['img'];
			$data['douyin_product_id'] = $info['product_id'];
			$data['douyin_status'] = ($info['status'] == 0 ? 1 : 0);
			$data['douyin_check_status'] = $info['check_status'];
			$data['market_price'] = $info['market_price']/100;
			$data['sell_price'] = $info['discount_price']/100;
			$data['createtime'] = $info['create_time'];
			$data['detail'] = jsonEncode([[
				'id'=>'M0000000000000',
				'temp'=>'richtext',
				'params'=>['bgcolor'=>'#FFFFFF','margin_x'=>0,'margin_y'=>0,'padding_x'=>0,'padding_y'=>0,'quanxian'=>['all'=>true],'platform'=>['all'=>true]],
				'data'=>'',
				'other'=>'',
				'content'=>$info['description']
			]]);
			$data['sellpoint'] = $info['recommend_remark'];
			$data['status'] = ($info['status'] == 0 ? 1 : 0);
			//var_dump($data);die;
			
			$product = Db::name('shop_product')->where('aid',aid)->where('douyin_product_id',$data['douyin_product_id'])->find();
			if($product){
				Db::name('shop_product')->where('aid',aid)->where('douyin_product_id',$data['douyin_product_id'])->update($data);
			}else{
				Db::name('shop_product')->insert($data);
			}
			//var_dump(Db::getlastsql());
		}
	}
}
