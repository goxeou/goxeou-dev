<?php


// +----------------------------------------------------------------------
// | 周期购商城-商品管理
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class CycleProduct extends Common
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

			$count = 0 + Db::name('cycle_product')->where($where)->count();
			$data = Db::name('cycle_product')->where($where)->page($page,$limit)->order($order)->select()->toArray();

			$clist = Db::name('cycle_category')->where('aid',aid)->select()->toArray();
			$cdata = array();
			foreach($clist as $c){
				$cdata[$c['id']] = $c['name'];
			}
			foreach($data as $k=>$v){
				$gglist = Db::name('cycle_guige')->where('aid',aid)->where('proid',$v['id'])->select()->toArray();
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
		$clist = Db::name('cycle_category')->Field('id,name')->where('aid',aid)->where('status',1)->where('pid',0)->order('sort desc,id')->select()->toArray();
		foreach($clist as $k=>$v){
			$clist[$k]['child'] = Db::name('cycle_category')->field('id,name')->where('aid',aid)->where('status',1)->where('pid',$v['id'])->order('sort desc,id')->select()->toArray();
		}
		View::assign('clist',$clist);
        $this->defaultSet();
		return View::fetch();
    }
	//编辑商品
	public function edit(){
		if(input('param.id')){
			$info = Db::name('cycle_product')->where('aid',aid)->where('id',input('param.id/d'))->find();
			if(!$info) showmsg('商品不存在');
			if(bid != 0 && $info['bid']!=bid) showmsg('无权限操作');
		}  else{
		    $info['ps_cycle'] =1;
        }
		//多规格
		$newgglist = array();
		if($info){
			$gglist = Db::name('cycle_guige')->where('aid',aid)->where('proid',$info['id'])->select()->toArray();
			foreach($gglist as $k=>$v){
				if($v['ks']!==null){
					$newgglist[$v['ks']] = $v;
				}else{
					Db::name('cycle_guige')->where('aid',aid)->where('id',$v['id'])->update(['ks'=>$k]);
					$newgglist[$k] = $v;
				}
			}
		}
		//分类
		$clist = Db::name('cycle_category')->field('id,name')->where('aid',aid)->where('status',1)->where('pid',0)->order('sort desc,id')->select()->toArray();
		foreach($clist as $k=>$v){
			$clist[$k]['child'] = Db::name('cycle_category')->field('id,name')->where('aid',aid)->where('status',1)->where('pid',$v['id'])->order('sort desc,id')->select()->toArray();
		}
		$freightdata = array();
		if($info && $info['freightdata']){
			$freightdata = Db::name('freight')->where('aid',aid)->where('id','in',$info['freightdata'])->order('sort desc,id')->select()->toArray();
		}

        $aglevellist = Db::name('member_level')->where('aid',aid)->where('can_agent','<>',0)->order('sort,id')->select()->toArray();
        $levellist = Db::name('member_level')->where('aid',aid)->order('sort,id')->select()->toArray();

        $info['everyday_item'] = json_decode($info['everyday_item'],true) ;
        $e_data= [];
        foreach($info['everyday_item'] as $key=>$val){
            $e_data[] = $val['value'];
        }
        $info['everyday_item'] = $e_data;

        //配送周期配置
        $every_day = [
            ['value' => 1,'label' => '每天配送'] ,
            ['value' => 2,'label' => '工作日配送'],
            ['value' => 3,'label' => '周末配送'],
            ['value' => 4,'label' =>'隔天配送']
        ];
        View::assign('every_day',$every_day);
		View::assign('aglevellist',$aglevellist);
		View::assign('levellist',$levellist);
		View::assign('info',$info);
		View::assign('newgglist',$newgglist);
		View::assign('clist',$clist);
		View::assign('freightdata',$freightdata);
		return View::fetch();
	}
	//保存商品
	public function save(){
		if(input('post.id')){
			$product = Db::name('cycle_product')->where('aid',aid)->where('id',input('post.id/d'))->find();
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
		//$data['sellpoint'] = $info['sellpoint'];
		//$data['procode'] = $info['procode'];
		$data['cid'] = $info['cid'];
//		$data['teamnum'] = $info['teamnum'];
//		$data['buymax'] = $info['buymax'];
//		$data['teamhour'] = $info['teamhour'];
//		$data['leadermoney'] = $info['leadermoney'];
//		if(bid == 0){
//			$data['leaderscore'] = $info['leaderscore'];
//		}
		if($info['oldsales'] != $info['sales']){
			$data['sales'] = $info['sales'];
		}
		$data['sort'] = $info['sort'];
		$data['status'] = $info['status'];
		$data['detail'] = $info['detail'];
		$data['is_user_defined'] = $info['is_user_defined'];//配送日期自定义
		$data['freighttype'] = $info['freighttype'];
		$data['freightdata'] = $info['freightdata'];
		if($info['gid']){
			$data['gid'] = implode(',',$info['gid']);
		}

		$data['ps_cycle'] = $info['ps_cycle'];
        $data['advance_pay_days'] = $info['advance_pay_days'];
        $data['advance_pay_time'] = $info['advance_pay_time'];
        $data['advance_extend_days'] = $info['advance_extend_days'];
        $data['min_num'] = $info['min_num']?$info['min_num']:1;
        $data['min_qsnum'] = $info['min_qsnum']?$info['min_qsnum']:1;;

        ///配送周期和提前支付等

        if($info['ps_cycle'] == 1){
            $everyday_item =  input('post.everyday_item');
            if(empty($everyday_item)){
                return json(['status'=>0,'msg'=>'请选择每日一期的配置项']);
            }
            $every_day = ['1' => '每天配送','2' => '工作日配送','3' => '周末配送','4' => '隔天配送'];
            $e_days = [];
            foreach($everyday_item as $key=> $val){
                $e_days[$key]['value'] =$val;
                $e_days[$key]['label'] =$every_day[$val];
            }
            $data['everyday_item'] = json_encode($e_days,JSON_UNESCAPED_UNICODE);
        }

		$data['commissionset'] = $info['commissionset'];
//		$data['commissiondata1'] = jsonEncode(input('post.commissiondata1/a'));
//		$data['commissiondata2'] = jsonEncode(input('post.commissiondata2/a'));
//		$data['commissiondata3'] = jsonEncode(input('post.commissiondata3/a'));


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
		} else {
            $data['feepercent'] = $info['feepercent'] == '' || $info['feepercent'] < 0 ? null : $info['feepercent'];//商品独立抽成费率
        }
		if($product){
			Db::name('cycle_product')->where('aid',aid)->where('id',$product['id'])->update($data);
			$proid = $product['id'];
			\app\commons\System::plog('编辑周期购商品'.$proid);
		}else{
			$data['aid'] = aid;
			$data['bid'] = bid;
			$proid = Db::name('cycle_product')->insertGetId($data);
			\app\commons\System::plog('添加周期购商品'.$proid);
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
			$guige = Db::name('cycle_guige')->where('aid',aid)->where('proid',$proid)->where('ks',$ks)->find();
			if($guige){
				Db::name('cycle_guige')->where('aid',aid)->where('id',$guige['id'])->update($ggdata);
				$ggid = $guige['id'];
			}else{
				$ggdata['aid'] = aid;
				$ggid = Db::name('cycle_guige')->insertGetId($ggdata);
			}
			$newggids[] = $ggid;
		}
		Db::name('cycle_guige')->where('aid',aid)->where('proid',$proid)->where('id','not in',$newggids)->delete();
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
		Db::name('cycle_product')->where($where)->update(['status'=>$st]);

		\app\commons\System::plog('周期购商品修改状态'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//审核
	public function setcheckst(){
		$st = input('post.st/d');
		$id = input('post.id/d');
		$reason = input('post.reason');
		Db::name('cycle_product')->where('aid',aid)->where('id',$id)->update(['ischecked'=>$st,'check_reason'=>$reason]);
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
		Db::name('cycle_product')->where($where)->delete();
		\app\commons\System::plog('周期购商品删除'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	//复制商品
	public function procopy(){
		$product = Db::name('cycle_product')->where('aid',aid)->where('bid',bid)->where('id',input('post.id/d'))->find();
		if(!$product) return json(['status'=>0,'msg'=>'商品不存在,请重新选择']);
		$gglist = Db::name('cycle_guige')->where('aid',aid)->where('proid',$product['id'])->select()->toArray();
		$data = $product;
		$data['name'] = '复制-'.$data['name'];
		unset($data['id']);
		$data['status'] = 0;
		$newproid = Db::name('cycle_product')->insertGetId($data);
		foreach($gglist as $gg){
			$ggdata = $gg;
			$ggdata['proid'] = $newproid;
			unset($ggdata['id']);
			Db::name('cycle_guige')->insert($ggdata);
		}
		\app\commons\System::plog('复制周期购商品'.$newproid);
		return json(['status'=>1,'msg'=>'复制成功','proid'=>$newproid]);
	}

	//选择商品
	public function chooseproduct(){
		//分类
		$clist = Db::name('cycle_category')->Field('id,name')->where('aid',aid)->where('status',1)->where('pid',0)->order('sort desc,id')->select()->toArray();
		foreach($clist as $k=>$v){
			$clist[$k]['child'] = Db::name('cycle_category')->Field('id,name')->where('aid',aid)->where('status',1)->where('pid',$v['id'])->order('sort desc,id')->select()->toArray();
		}
		View::assign('clist',$clist);
		return View::fetch();
	}
	//获取商品信息
	public function getproduct(){
		$proid = input('post.proid/d');
		$product = Db::name('cycle_product')->where('aid',aid)->where('id',$proid)->find();
		//多规格
		$newgglist = array();
		$gglist = Db::name('cycle_guige')->where('aid',aid)->where('proid',$product['id'])->select()->toArray();
		foreach($gglist as $k=>$v){
			$newgglist[$v['ks']] = $v;
		}
		$guigedata = json_decode($product['guigedata']);
		return json(['product'=>$product,'gglist'=>$newgglist,'guigedata'=>$guigedata]);
	}
    function defaultSet(){
        $set = Db::name('cycle_sysset')->where('aid',aid)->find();
        if(!$set){
            Db::name('cycle_sysset')->insert(['aid'=>aid]);
        }
    }
}
