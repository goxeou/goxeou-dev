<?php


// +----------------------------------------------------------------------
// | 收银台订单
// +----------------------------------------------------------------------
namespace app\controllers;
use think\db\Where;
use think\facade\View;
use think\facade\Db;

class CashierOrder extends Common
{
    public function initialize(){
		parent::initialize();
	}
	//列表
	public function index(){
        $cashier_id = input('param.cashier_id/d',0);
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
                if(input('param.showtype')=='all'){
                    $where[] = ['bid','>=',0];
                }else{
                    $where[] = ['bid','=',0];
                }
            }else{
                $where[] = ['bid','=',bid];
            }
//            $where[] = ['bid','=',bid];
            if($cashier_id){
                $where[] = ['cashier_id','=',$cashier_id];
            }
            if(input('param.orderid')){
                $where[] = ['id','=',input('param.orderid')];
            }
            if(input('param.mid')){
                $where[] = ['mid','=',input('param.mid')];
            }
            if(input('?param.ogid')){
                if(input('param.ogid')==''){
                    $where[] = ['1','=',0];
                }else{
                    $ids = Db::name('cashier_order_goods')->where('id','in',input('param.ogid'))->column('orderid');
                    $where[] = ['id','in',$ids];
                }
            }
            if(input('param.ordernum')) $where[] = ['ordernum','like','%'.input('param.ordernum').'%'];
            if(input('?param.status') && input('param.status')!=='') $where[] = ['status','=',input('param.status')];
            if(input('param.ctime')){
                $ctime = explode(' ~ ',input('param.ctime'));
                $where[] = ['createtime','>=',strtotime($ctime[0])];
                $where[] = ['createtime','<',strtotime($ctime[1]) + 86400];
            }
            $count = 0 + Db::name('cashier_order')->where($where)->count();
            $list = Db::name('cashier_order')->where($where)->page($page,$limit)->order($order)->select()->toArray();
            foreach($list as $k=>$vo) {
                $member = Db::name('member')->where('id', $vo['mid'])->find();
                $list[$k]['nickname'] = $member['nickname'];
                $list[$k]['headimg'] = $member['headimg'];
                if($vo['uid'] > 0){
                    $admin_user_name = Db::name('admin_user')->where('id',$vo['uid'])->value('un');
                    $list[$k]['admin_user'] = $admin_user_name??'超级管理员';
                }else{
                    $list[$k]['admin_user'] = '超级管理员';
                }
            }
                return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$list]);
		}
        $machinelist = Db::name('wifiprint_set')->where('aid',aid)->where('status',1)->where('bid',bid)->select()->toArray();
        $hasprint = 0;
        if($machinelist){
            $hasprint = 1;
        }
        View::assign('hasprint',$hasprint);
        $where = [];
        if(input('param.')) $where = input('param.');
        View::assign('where',json_encode($where));
		return View::fetch();
	}
    //打印小票
    public function wifiprint(){
        $id = input('post.id/d');
        $rs = \app\commons\Wifiprint::print(aid,'cashier',$id,0);
        return json($rs);
    }
    //编辑
    public function edit(){
        if(input('param.id')){
            $info = Db::name('cashier_order')->where('aid',aid)->where('bid',bid)->where('id',input('param.id/d'))->find();
            $goods = Db::name('cashier_order_goods')->where('orderid',$info['id'])->select()->toArray();
           
            if(getcustom('cashdesk_commission')){
                $comdata = array();
                $comdata['parent1'] = ['mid'=>'','nickname'=>'','headimg'=>'','money'=>0,'score'=>0];
                $comdata['parent2'] = ['mid'=>'','nickname'=>'','headimg'=>'','money'=>0,'score'=>0];
                $comdata['parent3'] = ['mid'=>'','nickname'=>'','headimg'=>'','money'=>0,'score'=>0];
                if($goods){
                    foreach($goods as $gk=>$v){
                        if($v['parent1']){
                            $parent1 = Db::name('member')->where('id',$v['parent1'])->find();
                            $comdata['parent1']['mid'] = $v['parent1'];
                            $comdata['parent1']['nickname'] = $parent1['nickname'];
                            $comdata['parent1']['headimg'] = $parent1['headimg'];
                            $comdata['parent1']['money'] += $v['parent1commission'];
                            $comdata['parent1']['score'] += $v['parent1score'];
                        }
                        if($v['parent2']){
                            $parent2 = Db::name('member')->where('id',$v['parent2'])->find();
                            $comdata['parent2']['mid'] = $v['parent2'];
                            $comdata['parent2']['nickname'] = $parent2['nickname'];
                            $comdata['parent2']['headimg'] = $parent2['headimg'];
                            $comdata['parent2']['money'] += $v['parent2commission'];
                            $comdata['parent2']['score'] += $v['parent2score'];
                        }
                        if($v['parent3']){
                            $parent3 = Db::name('member')->where('id',$v['parent3'])->find();
                            $comdata['parent3']['mid'] = $v['parent3'];
                            $comdata['parent3']['nickname'] = $parent3['nickname'];
                            $comdata['parent3']['headimg'] = $parent3['headimg'];
                            $comdata['parent3']['money'] += $v['parent3commission'];
                            $comdata['parent3']['score'] += $v['parent3score'];
                        }
                    }
                    $info['comdata'] = $comdata;
                } 
            }
           
            $info['prolist'] = $goods??[];
        }else{
            $info = array('id'=>'','prolist'=>[]);
        }
        View::assign('info',$info);
        return View::fetch();
    }
	

	//删除
	public function del(){
		$ids = input('post.ids/a');
		$where[] = ['aid','=',aid];
		if(bid>0){
		    $where[] = ['bid','=',bid];
        }
		Db::name('cashier_order')->where($where)->where('id','in',$ids)->delete();
		\app\commons\System::plog('删除收银台订单'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}


	//退款
    public function refund(){
        $orderid = input('id');
        $remark = input('param.remark','');
        $refund_money = input('param.refund_money');
        $order = Db::name('cashier_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->find();
        if(empty($order) || $order['status']!=1 || !in_array($order['refund_status'],[0,3])){
            return json(['status'=>0,'msg'=>'订单信息有误']);
        }
        if(empty($refund_money)){
            return json(['status'=>0,'msg'=>'退款金额有误']);
        }
        if($refund_money>($order['totalprice']-$order['refund_money'])){
            return json(['status'=>0,'msg'=>'退款金额有误']);
        }
        //直接退款
        if($order['paytypeid']==5 || $order['paytypeid']==81){
            $rs = \app\customs\Sxpay::refund($order['aid'],'cashdesk',$order['ordernum'],$order['totalprice'],$refund_money,$remark,$order['bid']);
            //更改payorder 
            $payorder = Db::name('payorder')->where('aid',$order['aid'])->where('ordernum',$order['ordernum'])->find();
            Db::name('payorder')->where('aid',$order['aid'])->where('id',$payorder['id'])->update(['refund_money' => $payorder['refund_money'] + $refund_money,'refund_time' => time()]);
        }elseif ($order['paytypeid'] ==0){//现金退款
            $rs = ['status'=>1,'msg'=>''];
            //更新payorder表退款信息
            $payorder = Db::name('payorder')->where('aid',$order['aid'])->where('ordernum',$order['ordernum'])->find();
            Db::name('payorder')->where('aid',$order['aid'])->where('id',$payorder['id'])->update(['refund_money' => $payorder['refund_money'] + $refund_money,'refund_time' => time()]);
            } else{
            $rs = \app\commons\Order::refund($order,$refund_money,$remark);
        }
        if($rs && $rs['status']==1){
            $orderup = [
                'refund_money'=>$refund_money,
                'refund_reason'=>$remark,
                'refund_status'=>1,
                'status'=>10,//退款
                'refund_time'=>time()
            ];
            Db::name('cashier_order')->where('id',$orderid)->update($orderup);
            if($order['bid'] > 0){
                $sysset = Db::name('business_sysset')->where('aid',$order['aid'])->find();
                $add_business_money = false;
                if($order['paytypeid'] ==2 && $sysset &&  $sysset['business_cashdesk_wxpay_type'] ==2){//微信支付
                    $add_business_money = true;
                }elseif ($order['paytypeid'] ==3 && $sysset && $sysset['business_cashdesk_alipay_type'] ==2){//支付宝
                    $add_business_money = true;
                }elseif (($order['paytypeid'] ==5 ||$order['paytypeid'] ==81 ) && $sysset && $sysset['business_cashdesk_sxpay_type'] ==2){//随行付
                    $add_business_money = true;
                }elseif ($order['paytypeid'] ==1 && $sysset && $sysset['business_cashdesk_yue']){//随行付
                    $add_business_money = true;
                }
                //todo 收银台退款 扣除佣金
                if($add_business_money){
                    $log = Db::name('business_moneylog')->where('aid',$order['aid'])->where('bid',$order['bid'])->where('type','cashdesk')->where('ordernum',$order['ordernum'])->find();
                    if($log['money'] > 0){
                        \app\commons\Business::addmoney($order['aid'],$order['bid'],-$log['money'],'收银台退款，订单号：'.$order['ordernum'],true,'cashdesk',$order['ordernum']);
                    }
                }
            }
			//退款退还佣金
			return json(['status'=>1,'msg'=>'退款成功']);
        }else{
            return json(['status'=>0,'msg'=>$rs['msg']??'退款失败']);
        }
    }

	
	//订单统计
	public function tongji(){
		if(request()->isAjax() || input('param.excel') == 1){
			if(input('param.type')==3){
				$year = date('Y');
				$month = '';
				$day = '';
				$tjtype = 1;
				if(input('param.year')) $year = input('param.year');
				if(input('param.month')) $month = input('param.month');
				if(input('param.day')) $day = input('param.day');
				if(input('param.tjtype')) $tjtype = input('param.tjtype');

				$data = [];
				$totalval = 0;
				$maxval = 0;
				$maxdate = '';
				if(!$month){
					for($i=1;$i<13;$i++){
						$thismonth = $i >=10 ? ''.$i : '0'.$i;
						$starttime = strtotime($year.'-'.$thismonth.'-01');
						if($thismonth == 12){
							$endtime = strtotime(($year+1).'-01-01');
						}else{
							$nextmonth = $thismonth+1;
							$nextmonth = $nextmonth >=10 ? ''.$nextmonth : '0'.$nextmonth;
							$endtime = strtotime($year.'-'.$nextmonth.'-01');
						}
						
						$thisdata = [];
						$thisdata['date'] = $i;
						if($tjtype == 1){ //成交额
							if(bid == 0){
								$val = 0 + Db::name('cashier_order')->where('aid',aid)->where('paytime','>=',$starttime)->where('paytime','<',$endtime)->where('status','in','1,2,3')->sum('totalprice');
							}else{
								$val = 0 + Db::name('cashier_order')->where('aid',aid)->where('bid',bid)->where('paytime','>=',$starttime)->where('paytime','<',$endtime)->where('status','in','1,2,3')->sum('totalprice');
							}
						}else{ //成交量
							if(bid == 0){
								$val = 0 + Db::name('cashier_order')->where('aid',aid)->where('paytime','>=',$starttime)->where('paytime','<',$endtime)->where('status','in','1,2,3')->count();
							}else{
								$val = 0 + Db::name('cashier_order')->where('aid',aid)->where('bid',bid)->where('paytime','>=',$starttime)->where('paytime','<',$endtime)->where('status','in','1,2,3')->count();
							}
						}
						$val = round($val,2);
						$totalval += $val;
						$thisdata['val'] = $val;
						
						if($maxval < $val){
							$maxval = $val;
							$maxdate = $thismonth.'月';
						}
						$data[] = $thisdata;
					}
					$title = array('月份',$tjtype == 1 ? '交易额' : '交易量','占比');
				}elseif(!$day){
					$month = $month>9?''.$month:'0'.$month;
					$ts = date('t',strtotime($year.'-'.$month.'-01'));
					for($i=1;$i<$ts;$i++){
						$thisday = $i >=10 ? ''.$i : '0'.$i;
						$starttime = strtotime($year.'-'.$month.'-'.$thisday);
						$endtime = $starttime + 86400;
						
						$thisdata = [];
						$thisdata['date'] = $i;
						if($tjtype == 1){ //成交额
							if(bid == 0){
								$val = 0 + Db::name('cashier_order')->where('aid',aid)->where('paytime','>=',$starttime)->where('paytime','<',$endtime)->where('status','in','1,2,3')->sum('totalprice');
							}else{
								$val = 0 + Db::name('cashier_order')->where('aid',aid)->where('bid',bid)->where('paytime','>=',$starttime)->where('paytime','<',$endtime)->where('status','in','1,2,3')->sum('totalprice');
							}
						}else{ //成交量
							if(bid == 0){
								$val = 0 + Db::name('cashier_order')->where('aid',aid)->where('paytime','>=',$starttime)->where('paytime','<',$endtime)->where('status','in','1,2,3')->count();
							}else{
								$val = 0 + Db::name('cashier_order')->where('aid',aid)->where('bid',bid)->where('paytime','>=',$starttime)->where('paytime','<',$endtime)->where('status','in','1,2,3')->count();
							}
						}
						$val = round($val,2);
						$totalval += $val;
						$thisdata['val'] = $val;

						if($maxval < $val){
							$maxval = $val;
							$maxdate = $thisday.'日';
						}
						$data[] = $thisdata;
					}
					$title = array('日期',$tjtype == 1 ? '交易额' : '交易量','占比');
				}else{
					$month = $month>9?''.$month:'0'.$month;
					$day = $day >6 ? ''.$day : '0'.$day;
					for($i=0;$i<24;$i++){
						$starttime = strtotime($year.'-'.$month.'-'.$day) + $i*3600;
						$endtime = $starttime + 3600;
						
						$thisdata = [];
						$thisdata['date'] = $i.'点-'.($i+1).'点';
						if($tjtype == 1){ //成交额
							if(bid == 0){
								$val = 0 + Db::name('cashier_order')->where('aid',aid)->where('paytime','>=',$starttime)->where('paytime','<',$endtime)->where('status','in','1,2,3')->sum('totalprice');
							}else{
								$val = 0 + Db::name('cashier_order')->where('aid',aid)->where('bid',bid)->where('paytime','>=',$starttime)->where('paytime','<',$endtime)->where('status','in','1,2,3')->sum('totalprice');
							}
						}else{ //成交量
							if(bid == 0){
								$val = 0 + Db::name('cashier_order')->where('aid',aid)->where('paytime','>=',$starttime)->where('paytime','<',$endtime)->where('status','in','1,2,3')->count();
							}else{
								$val = 0 + Db::name('cashier_order')->where('aid',aid)->where('bid',bid)->where('paytime','>=',$starttime)->where('paytime','<',$endtime)->where('status','in','1,2,3')->count();
							}
						}
						$val = round($val,2);
						$totalval += $val;
						$thisdata['val'] = $val;

						if($maxval < $val){
							$maxval = $val;
							$maxdate =$i.'点-'.($i+1).'点';
						}
						$data[] = $thisdata;
					}
					$title = array('时间',$tjtype == 1 ? '交易额' : '交易量','占比');
				}
				$totalval = round($totalval,2);
				foreach($data as $k=>$v){
					if($totalval == 0){
						$data[$k]['percent'] = 0;
					}else{
						$data[$k]['percent'] = round($v['val'] / $totalval * 100,2);
					}
				}

				if(input('param.excel') == 1){
					$data[] = ['date'=>'总数','val'=>$totalval,'percent'=>''];
					$data[] = ['date'=>'最高','val'=>$maxval,'percent'=>''];
					$this->export_excel($title,$data);
				}
				return json(['code'=>0,'msg'=>'查询成功','count'=>count($data),'data'=>$data,'tjtype'=>$tjtype,'totalval'=>$totalval,'maxval'=>$maxval,'maxdate'=>$maxdate]);
			}elseif(input('param.type')==5){
				if(input('param.excel') == 1){
					$page = 1;
					$limit = 10000000;
				}else{
					$page = input('param.page');
					$limit = input('param.limit');
				}
				if(input('param.field') && input('param.order')){
					$order = input('param.field').' '.input('param.order');
				}else{
					$order = 'sort desc,id desc';
				}
				$where = array();
				$where[] = ['aid','=',aid];
				$where[] = ['bid','=',bid];
				if(input('param.name')) $where[] = ['name','like','%'.$_GET['name'].'%'];
				$count = 0 + Db::name('shop_product')->where($where)->count();
				$data = Db::name('shop_product')->field('*,(select sum(num) from ddwx_cashier_order_goods where proid=ddwx_shop_product.id and status in(1,2,3)) buynum,((select sum(num) from ddwx_cashier_order_goods where proid=ddwx_shop_product.id and status in(1,2,3)) / viewnum) buypercent')->where($where)->page($page,$limit)->order($order)->select()->toArray();

				$exceldata = [];
				foreach($data as $k=>$v){
					//$data[$k]['buynum'] = Db::name('cashier_order_goods')->where('aid',aid)->where('proid',$v['id'])->where('status','in','1,2,3')->sum('num');
					if(!$v['buynum']){
						$data[$k]['buynum'] = 0;
					}
					$data[$k]['buypercent'] = $v['viewnum'] > 0 ? round( $v['buynum'] / $v['viewnum'] * 100 ,2) : 0;
					$exceldata[] = [$v['name'],$v['viewnum'],$data[$k]['buynum'],$data[$k]['buypercent'].'%'];
				}
				if(input('param.excel') == 1){
					$title = ['商品名称','访问次数','购买件数','转化率'];
					$this->export_excel($title,$exceldata);
				}

				return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
			}else{
				$page = input('param.page');
				$limit = input('param.limit');
				if(input('param.field') && input('param.order')){
					$order = input('param.field').' '.input('param.order');
				}else{
					$order = 'totalprice desc';
				}
				$where = [];
				$where[] = ['og.aid','=',aid];
				$where[] = ['og.bid','=',bid];
				//$where[] = ['og.status','in','1,2,3'];
//				if($this->mdid){
//					$where[] = ['mdid','=',$this->mdid];
//				}
				if(input('param.ctime') ){
					$ctime = explode(' ~ ',input('param.ctime'));
					$where[] = ['og.createtime','>=',strtotime($ctime[0])];
					$where[] = ['og.createtime','<',strtotime($ctime[1]) + 86400];
				}
				if(input('param.paytime') ){
					$ctime = explode(' ~ ',input('param.paytime'));
					$where[] = ['cashier_order.paytime','>=',strtotime($ctime[0])];
					$where[] = ['cashier_order.paytime','<',strtotime($ctime[1]) + 86400];
				}
				if(input('param.proname')){
					$where[] = ['og.proname','like','%'.input('param.proname').'%'];
				}
				if(input('param.cid') && input('param.cid')!==''){
					//取出cid 在的商品
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
					

					//$cprolist = Db::name('shop_product')->where('aid',aid)->where("find_in_set(".input('param.cid').",cid)")->column('id');
					//var_dump($cprolist);
					//if($cprolist){
					//	$where[] =  ['og.proid','in',$cprolist];
					//}
				}
				$fields = 'og.proid,og.proname name,og.propic pic,og.ggname,sum(og.num) num,sum(og.totalprice) totalprice,sum(og.totalprice)/sum(og.num) as avgprice,sum(og.cost_price*og.num) as chengben,sum(og.totalprice-og.cost_price*og.num) lirun';
				if(input('param.type')==2){
					$count = 0 + Db::name('cashier_order_goods')->alias('og')->join('cashier_order','cashier_order.id=og.orderid')->fieldRaw('og.proid')->where($where)->group('ggid')->count();
					$list = Db::name('cashier_order_goods')->alias('og')->join('cashier_order','cashier_order.id=og.orderid')->fieldRaw($fields)->where($where)->group('ggid')->page($page,$limit)->order($order)->select()->toArray();
				}elseif(input('param.type')==7){
                    }else{
					$count = 0 + Db::name('cashier_order_goods')->alias('og')->join('cashier_order','cashier_order.id=og.orderid')->fieldRaw('og.proid')->where($where)->group('proid2')->count();
					$list = Db::name('cashier_order_goods')->alias('og')->join('cashier_order','cashier_order.id=og.orderid')->fieldRaw($fields)->where($where)->group('proid2')->page($page,$limit)->order($order)->select()->toArray();
					//var_dump(db('cashier_order_goods')->getlastsql());
				}
				if($page == 1){
					$totaldata = Db::name('cashier_order_goods')->alias('og')->join('cashier_order','cashier_order.id=og.orderid')->fieldRaw($fields)->where($where)->find();
				}
				foreach($list as $k=>$v){
					$list[$k]['ph'] = ($k+1) + ($page-1)*$limit;
					$list[$k]['avgprice'] = number_format($v['avgprice'],2,'.','');
					//计算平均售价
                  
                    if(input('param.type')==1 || !input('param.type')){
                       $oglist =  Db::name('cashier_order_goods')->alias('og')
                           ->join('cashier_order o','o.id=og.orderid')
                           ->field('og.id,og.totalprice,og.num,o.totalprice as ototalprice,o.pre_totalprice,leveldk_money')->where('proid2',$v['proid'])->select()->toArray();
                    
                       $ototalprice = 0;
                       $ototalnum = 0;
                       foreach($oglist as $key=>$val){
                           if($val['leveldk_money'] > 0){
                               $real_totalprice = dd_money_format($val['totalprice'] *  $val['ototalprice']/$val['pre_totalprice']);
                             
                               $ototalprice += $real_totalprice;
                           } else{
                               $ototalprice += $val['totalprice'];
                           }
                           $ototalnum +=$val['num'];
                       }
                       $avgprice = $ototalnum>0?dd_money_format($ototalprice/$ototalnum,2) :0;
                        $list[$k]['avgprice'] =$avgprice;
                        $list[$k]['totalprice'] =dd_money_format($ototalprice,2);
                    }
                  
				}
				return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$list,'totaldata'=>$totaldata]);
			}
		}
		if(input('param.type')==3){
			return View::fetch('tongji3');
		}
		if(input('param.type')==4){
			$membercount = Db::name('member')->where('aid',aid)->count(); //总会员数
			if(bid == 0){
				$totalprice = Db::name('cashier_order')->where('aid',aid)->where('status','in','1,2,3')->sum('totalprice'); //总订单金额
				$totalnum = Db::name('cashier_order')->where('aid',aid)->where('status','in','1,2,3')->count(); //总订单数
				$totalview = Db::name('shop_product')->where('aid',aid)->sum('viewnum'); //总访问数
				$memberxf = Db::name('cashier_order')->where('aid',aid)->group('mid')->where('status','in','1,2,3')->count(); //消费会员数
			}else{
				$totalprice = Db::name('cashier_order')->where('aid',aid)->where('bid',bid)->where('status','in','1,2,3')->sum('totalprice'); //总订单金额
				$totalnum = Db::name('cashier_order')->where('aid',aid)->where('bid',bid)->where('status','in','1,2,3')->count(); //总订单数
				$totalview = Db::name('shop_product')->where('aid',aid)->where('bid',bid)->sum('viewnum'); //总访问数
				$memberxf = Db::name('cashier_order')->where('aid',aid)->where('bid',bid)->group('mid')->where('status','in','1,2,3')->count(); //消费会员数
			}

			$percent1 = $membercount > 0 ? round($totalprice / $membercount,2) : 0; //会员人均消费
			$percent2 = $totalview > 0 ? round($totalprice / $totalview * 100,2) : 0; //访问转换率
			$percent3 = $totalview > 0 ? round($totalnum / $totalview * 100,2) : 0; //订单转化率
			$percent4 = $membercount > 0 ? round($memberxf / $membercount * 100,2) : 0; //会员消费率
			$percent5 = $membercount > 0 ? round($totalnum / $membercount * 100,2) : 0; //订单购买率

			View::assign('membercount',$membercount);
			View::assign('totalprice',round($totalprice,2));
			View::assign('totalnum',$totalnum);
			View::assign('totalview',$totalview);
			View::assign('memberxf',$memberxf);
			View::assign('percent1',$percent1);
			View::assign('percent2',$percent2);
			View::assign('percent3',$percent3);
			View::assign('percent4',$percent4);
			View::assign('percent5',$percent5);
			return View::fetch('tongji4');
		}
		if(input('param.type')==5){
			return View::fetch('tongji5');
		}
		if(input('param.type')==6){
			//取出商城分类
			$catelist =  Db::name('shop_category')->where('aid',aid)->where('pid','=',0)->select()->toArray();
			View::assign('paytime',input('param.paytime'));
			View::assign('catelist',$catelist);
			return View::fetch('tongji6');
		}
		return View::fetch();
	}
    //导出
    public function excel (){
        set_time_limit(0);
        ini_set('memory_limit', '2000M');
        $cashier_id = input('param.cashier_id/d',0);
        if(input('param.field') && input('param.order')){
            $order = input('param.field').' '.input('param.order');
        }else{
            $order = 'id desc';
        }
        $page = input('param.page');
        $limit = input('param.limit');
        $where = array();
        $where[] = ['aid','=',aid];
        $where[] = ['bid','=',bid];
        if($cashier_id){
            $where[] = ['cashier_id','=',$cashier_id];
        }
        if(input('param.mid')){
            $where[] = ['mid','=',input('param.mid')];
        }
        if(input('param.ordernum')) $where[] = ['ordernum','like','%'.input('param.ordernum').'%'];
        if(input('?param.status') && input('param.status')!=='') $where[] = ['status','=',input('param.status')];
        if(input('param.ctime')){
            $ctime = explode(' ~ ',input('param.ctime'));
            $where[] = ['createtime','>=',strtotime($ctime[0])];
            $where[] = ['createtime','<',strtotime($ctime[1]) + 86400];
        }
        $list = Db::name('cashier_order')->where($where)->order($order)->page($page,$limit)->select()->toArray();
        $count = Db::name('cashier_order')->where($where)->order($order)->count();
        $title = array('ID','订单编号',t('会员').'信息','实付金额','创建时间','付款时间','支付方式','收银员','状态','备注');
        $data = [];
        foreach($list as $k=>$vo) {
            $member = Db::name('member')->where('id', $vo['mid'])->find();
            if($vo['uid'] > 0){
                $admin_user_name = Db::name('admin_user')->where('id',$vo['uid'])->value('un');
                $admin_user = $admin_user_name??'超级管理员';
            }else{
                $admin_user = '超级管理员';
            }
            $status_arr = ['0' => '已支付','1' => '已支付','2' => '挂单','4' =>'已关闭','10' => '已退款'];
            $data[] = [
                $vo['id'],
                $vo['ordernum'],
                $vo['mid']?$member['nickname'].'('.t('会员').'ID:'.$member['id'].')':'暂无',
                $vo['totalprice'],
                date('Y-m-d H:i:s',$vo['createtime']),
                $vo['paytime']?date('Y-m-d H:i:s',$vo['paytime']):'暂无',
                $vo['paytype'],
                $admin_user,
                $status_arr[$vo['status']],
                $vo['remark']
            ];
        }
        return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data,'title'=>$title]);
        $this->export_excel($title,$data);
    }
	//导出
	public function tjexcel(){
		set_time_limit(0);
		ini_set('memory_limit', '2000M');
		if(input('param.field') && input('param.order')){
			$order = input('param.field').' '.input('param.order');
		}else{
			$order = 'totalprice desc';
		}
        $page = input('param.page');
        $limit = input('param.limit');
		$where = [];
		$where[] = ['og.aid','=',aid];
		$where[] = ['og.bid','=',bid];
		//$where[] = ['og.status','in','1,2,3'];
//		if($this->mdid){
//			$where[] = ['mdid','=',$this->mdid];
//		}
		if(input('param.ctime') ){
			$ctime = explode(' ~ ',input('param.ctime'));
			$where[] = ['og.createtime','>=',strtotime($ctime[0])];
			$where[] = ['og.createtime','<',strtotime($ctime[1]) + 86400];
		}
		if(input('param.paytime') ){
			$ctime = explode(' ~ ',input('param.paytime'));
			$where[] = ['cashier_order.paytime','>=',strtotime($ctime[0])];
			$where[] = ['cashier_order.paytime','<',strtotime($ctime[1]) + 86400];
		}
		if(input('param.proname')){
			$where[] = ['og.proname','like','%'.input('param.proname').'%'];
		}
		if(input('param.cid')){
			//取出cid 在的商品
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
			//$where[] = ['og.cid','=',input('param.cid')];
		}
		$fields = 'og.proid,og.proname name,og.propic pic,og.ggname,sum(og.num) num,sum(og.totalprice) totalprice,sum(og.totalprice)/sum(og.num) as avgprice';
		if(input('param.type')==2){
			$list = Db::name('cashier_order_goods')->alias('og')->join('cashier_order','cashier_order.id=og.orderid')->field($fields)->where($where)->group('ggid')->order($order)->page($page,$limit)->select()->toArray();
            $count = Db::name('cashier_order_goods')->alias('og')->join('cashier_order','cashier_order.id=og.orderid')->field($fields)->where($where)->group('ggid')->count();
        }else{
			$list = Db::name('cashier_order_goods')->alias('og')->join('cashier_order','cashier_order.id=og.orderid')->field($fields)->where($where)->group('proid')->order($order)->page($page,$limit)->select()->toArray();
            $count = Db::name('cashier_order_goods')->alias('og')->join('cashier_order','cashier_order.id=og.orderid')->field($fields)->where($where)->group('proid')->count();
        }
		foreach($list as $k=>$v){
			$list[$k]['ph'] = ($k+1);
			$list[$k]['avgprice'] = number_format($v['avgprice'],2,'.','');
		}
		if(input('param.type')==2){
			$title = array('排名','商品名称','商品规格','销售数量','销售金额','平均单价');
			$data = [];
			foreach($list as $k=>$vo){
				$data[] = [
					$vo['ph'],
					$vo['name'],
					$vo['ggname'],
					$vo['num'],
					$vo['totalprice'],
					$vo['avgprice'],
				];

			}
		}else{
			$title = array('排名','商品名称','销售数量','销售金额','平均单价');
			$data = [];
			foreach($list as $k=>$vo){
				$data[] = [
					$vo['ph'],
					$vo['name'],
					$vo['num'],
					$vo['totalprice'],
					$vo['avgprice'],
				]; 
			}
		}
        return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data,'title'=>$title]);
		$this->export_excel($title,$data);
	}
}