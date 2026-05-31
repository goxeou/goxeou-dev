<?php


// +----------------------------------------------------------------------
// | 整点秒杀-商品管理
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;
class SeckillProduct extends Common
{
	//商品列表
    public function index(){
        $sysset = Db::name('seckill_sysset')->where('aid',aid)->find();
        $systimeset = explode(',',$sysset['timeset']);

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
			if(input('param.seckill_date')) $where[] = ['seckill_date','=',input('param.seckill_date')];
			if(input('param.seckill_time')) $where[] = ['seckill_time','=',input('param.seckill_time')];

			$count = 0 + Db::name('seckill_product')->where($where)->count();
			$data = Db::name('seckill_product')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			foreach($data as $k=>$v){
				
				$data[$k]['starttime'] = strtotime($v['seckill_date']) + $v['seckill_time']*3600;
				//下一场
				$thisindex = array_search($v['seckill_time'],$systimeset);
				if($thisindex+1 == count($systimeset)){
					$nextstarttime = strtotime($v['seckill_date'])+86400 + $systimeset[0] * 3600;
				}else{
					$nextstarttime = strtotime($v['seckill_date']) + $systimeset[$thisindex+1] * 3600;
				}
				$data[$k]['nextstarttime'] = $nextstarttime;
				if($v['stock']+$v['sales'] == 0){
					$data[$k]['salepercent'] = 0;
				}else{
					$data[$k]['salepercent'] = intval($v['sales']/($v['stock']+$v['sales']) * 10000)/100;
				}
				if($v['bid'] > 0){
					$data[$k]['bname'] = Db::name('business')->where('aid',aid)->where('id',$v['bid'])->value('name');
				}else{
					$data[$k]['bname'] = '平台自营';
				}
				}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}

		View::assign('sysset',$sysset);
		View::assign('systimeset',$systimeset);
		return View::fetch();
    }
	//编辑商品
	public function edit(){
		if(input('param.id')){
			$info = Db::name('seckill_product')->where('aid',aid)->where('id',input('param.id/d'))->find();
			if(!$info) showmsg('商品不存在');
			if(bid != 0 && $info['bid']!=bid) showmsg('无权限操作');
		}

		//多规格
		$newgglist = array();
		if($info){
			$gglist = Db::name('seckill_guige')->where('aid',aid)->where('proid',$info['id'])->select()->toArray();
			foreach($gglist as $k=>$v){
			    $v['givescore'] = dd_money_format($v['givescore'],$this->score_weishu);
				$v['lvprice_data'] = json_decode($v['lvprice_data']);
				if($v['ks']!==null){
					$newgglist[$v['ks']] = $v;
				}else{
					Db::name('seckill_guige')->where('aid',aid)->where('id',$v['id'])->update(['ks'=>$k]);
					$newgglist[$k] = $v;
				}

			}
            $info['give_score'] = dd_money_format($info['givescore'],$this->score_weishu);
		}

		$info['gettj'] = explode(',',$info['gettj']);
        $default_cid = Db::name('member_level_category')->where('aid',aid)->where('isdefault', 1)->value('id');
        $default_cid = $default_cid ? $default_cid : 0;
        $aglevellist = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->where('can_agent','<>',0)->order('sort,id')->select()->toArray();
		$levellist = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->order('sort,id')->select()->toArray();
		
		$sysset = Db::name('seckill_sysset')->where('aid',aid)->find();
		$systimeset = explode(',',$sysset['timeset']);

        $freightList = Db::name('freight')->where('aid',aid)->where('status',1)->order('sort desc,id')->select()->toArray();
        $freightdata = array();
		if($info && $info['freightdata']){
			$freightdata = Db::name('freight')->where('aid',aid)->where('id','in',$info['freightdata'])->order('sort desc,id')->select()->toArray();
		}
		$bset = Db::name('business_sysset')->where('aid',aid)->find();
		View::assign('bset',$bset);

		View::assign('freightdata',$freightdata);

		View::assign('sysset',$sysset);
		View::assign('systimeset',$systimeset);

		View::assign('aglevellist',$aglevellist);
		View::assign('levellist',$levellist);
		View::assign('info',$info);
		View::assign('newgglist',$newgglist);
		View::assign('freightList',$freightList);
		View::assign('freightdata',$freightdata);
		return View::fetch();
	}
	//保存商品
	public function save(){
		if(input('post.id')){
			$product = Db::name('seckill_product')->where('aid',aid)->where('id',input('post.id/d'))->find();
			if(!$product) showmsg('商品不存在');
			if(bid != 0 && $product['bid']!=bid) showmsg('无权限操作');
		}
		$info = input('post.info/a');
		$info['detail'] = \app\commons\Common::geteditorcontent($info['detail']);
		if($info['seckill_time'] ==''){
            return json(['status'=>0,'msg'=>'请选择秒杀时间']);
        }
		$data = array();
		$data['seckill_date'] = $info['seckill_date'];
		$data['seckill_time'] = $info['seckill_time'];
		$data['starttime'] = strtotime($info['seckill_date'])+$info['seckill_time']*3600;
		$data['name'] = $info['name'];
		$data['pic'] = $info['pic'];
		$data['pics'] = $info['pics'];
		//$data['fuwupoint'] = $info['fuwupoint'];
		//$data['sellpoint'] = $info['sellpoint'];
		//$data['procode'] = $info['procode'];
        $data['freightcontent'] = $info['freightcontent'];
		$data['freighttype'] = $info['freighttype'];
		$data['freightdata'] = $info['freightdata'];
        $data['contact_require'] = intval($info['contact_require']);
		$data['commissionset'] = $info['commissionset'];
		$data['commissiondata1'] = jsonEncode(input('post.commissiondata1/a'));
		$data['commissiondata2'] = jsonEncode(input('post.commissiondata2/a'));
		$data['commissiondata3'] = jsonEncode(input('post.commissiondata3/a'));
		$data['video'] = $info['video'];
		$data['video_duration'] = $info['video_duration'];
		$data['perlimit'] = $info['perlimit'];
		$data['scoredkmaxset'] = $info['scoredkmaxset'];
		$data['scoredkmaxval'] = $info['scoredkmaxval'];
		if(isset($info['detail_text'])){
			$data['detail_text'] = $info['detail_text'];
		}
		if(isset($info['detail_pics'])){
			$data['detail_pics'] = $info['detail_pics'];
		}
		
		if($info['oldsales'] != $info['sales']){
			$data['sales'] = $info['sales'];
		}
		$data['sort'] = $info['sort'];
		$data['status'] = $info['status'];
		$data['detail'] = $info['detail'];
		if(!$product) $data['createtime'] = time();
		$data['lvprice'] = $info['lvprice'];
		$data['gettj'] = implode(',',$info['gettj']);
		$data['gettjtip'] = $info['gettjtip'];
		$data['gettjurl'] = $info['gettjurl'];
		if($info['lvprice']==1){
            $default_cid = Db::name('member_level_category')->where('aid',aid)->where('isdefault', 1)->value('id');
            $default_cid = $default_cid ? $default_cid : 0;
            $levellist = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->order('sort,id')->select()->toArray();
			$defaultlvid = $levellist[0]['id'];
			$sellprice_field = 'sell_price_'.$defaultlvid;
		}else{
			$sellprice_field = 'sell_price';
		}
		$sell_price = 0;$market_price = 0;$cost_price = 0;$weight = 0;$givescore=0;$lvprice_data = [];
		foreach(input('post.option/a') as $ks=>$v){
			if($sell_price==0 || $v[$sellprice_field] < $sell_price){
				$sell_price = $v[$sellprice_field];
				$market_price = $v['market_price'];
				$cost_price = $v['cost_price'];
				$givescore = $v['givescore'];
				$weight = $v['weight'];
				if($info['lvprice']==1){
					$lvprice_data = [];
					foreach($levellist as $lv){
						$lvprice_data[$lv['id']] = $v['sell_price_'.$lv['id']];
					}
				}
			}
		}
		if($info['lvprice']==1){
			$data['lvprice_data'] = json_encode($lvprice_data);
		}
		
		$data['market_price'] = $market_price;
		$data['cost_price'] = $cost_price;
		$data['sell_price'] = $sell_price;
		$data['givescore'] = $givescore;
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
		
		if(bid != 0){
			$bset = Db::name('business_sysset')->where('aid',aid)->find();
			if($bset['commission_canset']==0){
				$data['commissionset'] = '-1';
			}
		}
		if($product){
			Db::name('seckill_product')->where('aid',aid)->where('id',$product['id'])->update($data);
			$proid = $product['id'];
			\app\commons\System::plog('商城商品编辑'.$proid);
			$proids = [];
			$proids[] = $proid;
		}else{
			$data['aid'] = aid;
			$data['bid'] = bid;
            $datelist = [];
			if($info['seckill_sjd']){
			    //计算天数
                $date_arr = explode(' ~ ',$info['seckill_sjd']);
                $dt_start = strtotime($date_arr[0]);
                $dt_end   = strtotime($date_arr[1]);
                while ($dt_start <= $dt_end) {
                    array_push($datelist, date('Y-m-d', $dt_start));
                    $dt_start = strtotime('+1 day', $dt_start);
                }
            }
			$proids = [];
			foreach($datelist as $val){
			    $data['seckill_date'] = $val;
                $data['starttime'] = strtotime($data['seckill_date'])+$info['seckill_time']*3600;
                $proid = Db::name('seckill_product')->insertGetId($data);
                $proids[] = $proid;
                \app\commons\System::plog('商城商品编辑'.$proid);
            }
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
        foreach($proids as $proid){
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
                $ggdata['givescore'] = $v['givescore'];
                $ggdata['stock'] = $v['stock']>0 ? $v['stock']:0;
                $lvprice_data = [];
                if($info['lvprice']==1){
                    $ggdata['sell_price'] = $v['sell_price_'.$levellist[0]['id']]>0 ? $v['sell_price_'.$levellist[0]['id']]:0;
                    foreach($levellist as $lv){
                        $sell_price = $v['sell_price_'.$lv['id']]>0 ? $v['sell_price_'.$lv['id']]:0;
                        $lvprice_data[$lv['id']] = $sell_price;
                    }
                    $ggdata['lvprice_data'] = json_encode($lvprice_data);
                }
    
                $guige = Db::name('seckill_guige')->where('aid',aid)->where('proid',$proid)->where('ks',$ks)->find();
                if($guige){
                    Db::name('seckill_guige')->where('aid',aid)->where('id',$guige['id'])->update($ggdata);
                    $ggid = $guige['id'];
                }else{
                    $ggdata['aid'] = aid;
                    $ggid = Db::name('seckill_guige')->insertGetId($ggdata);
                }
                $newggids[] = $ggid;
            }
            Db::name('seckill_guige')->where('aid',aid)->where('proid',$proid)->where('id','not in',$newggids)->delete();
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
		Db::name('seckill_product')->where($where)->update(['status'=>$st]);
		\app\commons\System::plog('商城商品改状态'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//审核
	public function setcheckst(){
		$st = input('post.st/d');
		$id = input('post.id/d');
		$reason = input('post.reason');
		Db::name('seckill_product')->where('aid',aid)->where('id',$id)->update(['ischecked'=>$st,'check_reason'=>$reason]);
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
		$prolist = Db::name('seckill_product')->where($where)->select();
		foreach($prolist as $pro){
			Db::name('seckill_product')->where('id',$pro['id'])->delete();
			Db::name('seckill_guige')->where('proid',$pro['id'])->delete();
		}
		\app\commons\System::plog('秒杀商品删除'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	//复制商品
	public function procopy(){
		$product = Db::name('seckill_product')->where('aid',aid)->where('bid',bid)->where('id',input('post.id/d'))->find();
		if(!$product) return json(['status'=>0,'msg'=>'商品不存在,请重新选择']);
		$gglist = Db::name('seckill_guige')->where('aid',aid)->where('proid',$product['id'])->select()->toArray();
		$data = $product;
		$data['name'] = '复制-'.$data['name'];
		unset($data['id']);
		$data['status'] = 0;
		$newproid = Db::name('seckill_product')->insertGetId($data);
		foreach($gglist as $gg){
			$ggdata = $gg;
			$ggdata['proid'] = $newproid;
			unset($ggdata['id']);
			Db::name('seckill_guige')->insert($ggdata);
		}
		\app\commons\System::plog('商城商品复制'.$newproid);
		return json(['status'=>1,'msg'=>'复制成功','proid'=>$newproid]);
	}
	//选择商品
	public function chooseproduct(){
		$sysset = Db::name('seckill_sysset')->where('aid',aid)->find();
        $systimeset = explode(',',$sysset['timeset']);

		View::assign('sysset',$sysset);
		View::assign('systimeset',$systimeset);
		return View::fetch();
	}
	//获取商品信息
	public function getproduct(){
		$proid = input('post.proid/d');
		$product = Db::name('seckill_product')->where('aid',aid)->where('id',$proid)->find();
		//多规格
		$newgglist = array();
		$gglist = Db::name('seckill_guige')->where('aid',aid)->where('proid',$product['id'])->select()->toArray();
		foreach($gglist as $k=>$v){
			$newgglist[$v['ks']] = $v;
		}
		$guigedata = json_decode($product['guigedata']);
		return json(['product'=>$product,'gglist'=>$newgglist,'guigedata'=>$guigedata]);
	}
}
