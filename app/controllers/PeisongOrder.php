<?php


// +----------------------------------------------------------------------
// | 配送订单
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class PeisongOrder extends Common
{
    public function initialize(){
		parent::initialize();
	}
	//配送记录
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
			if(bid > 0){
				$where[] = ['bid','=',bid];
			}
			if(input('param.psid')) $where[] = ['psid','=',input('param.psid')];
			if(input('param.ordernum')) $where[] = ['ordernum','=',input('param.ordernum')];
			if(input('?param.status') && input('param.status')!=='') $where[] = ['status','=',input('param.status')];
            if(input('?param.pstype') && input('param.pstype')!==''){
                $pstype = input('param.pstype');
                if($pstype == 1){
                    $where[] = ['psid','>=',0];
                }else if($pstype == 2){
                    $where[] = ['psid','=',-1];
                }else if($pstype == 3){
                    $where[] = ['psid','=',-2];
                }
            }
			$count = 0 + Db::name('peisong_order')->where($where)->count();
			$data = Db::name('peisong_order')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			foreach($data as $k=>$v){
				$data[$k]['member'] = Db::name('member')->where('id',$v['mid'])->field('headimg,nickname')->find();
                if($v['psid']==-2){
                    $psuser = ['realname'=>'','tel'=>'','latitude'=>'','longitude'=>''];
                    $data[$k]['reject_code'] = '';
                	$data[$k]['reject_msg']  = '';
                    if(getcustom('express_maiyatian')) {
                        //查询关联表
                        $myt = Db::name('peisong_order_myt')->where('poid',$v['id'])->where('aid',aid)->find();
                        if($myt){
                            $psuser = [];
                            $psuser['realname'] = $myt['rider_name'];
                            $psuser['tel']      = $myt['rider_phone'];
                            $psuser['latitude'] = $myt['rider_latitude'];
                            $psuser['longitude']= $myt['rider_longitude'];
                            $data[$k]['reject_code'] = $myt['reject_code'];//拒绝码
                    		$data[$k]['reject_msg']  = $myt['reject_msg'];//拒绝原因
                        }
                    }
                    $data[$k]['psuser'] = $psuser;
                }else if($v['psid']==-1){
					$data[$k]['psuser'] = ['realname'=>$v['make_rider_name'],'tel'=>$v['make_rider_mobile']];
				}else{
					$data[$k]['psuser'] = Db::name('peisong_user')->where('id',$v['psid'])->field('realname,tel')->find();
				}
				$data[$k]['orderinfo'] = json_decode($v['orderinfo'],true);
				$data[$k]['binfo'] = json_decode($v['binfo'],true);
                if($v['type'] == 'paotui_order'){
                    $data[$k]['binfo']['name'] = '（跑腿）'.$data[$k]['binfo']['name'];
                }
				$data[$k]['prolist'] = json_decode($v['prolist'],true);

				$goodsdata=array();
				foreach($data[$k]['prolist'] as $og){
					if($v['type'] == 'paotui_order'){
						$goodsdata[] = '<div style="font-size:12px;float:left;clear:both;margin:1px 0">'.
							'<img src="'.$og['pic'].'" style="max-width:60px;float:left">'.
							'<div style="float: left;width:160px;margin-left: 10px;white-space:normal;line-height:16px;">'.
								'<div style="width:100%;min-height:25px;max-height:32px;overflow:hidden">'.$og['name'].'</div>'.
								'<div style="padding-top:0px;color:#f60"><span style="color:#888">'.$og['ggname'].'</span></div>'.
								'<div style="padding-top:0px;color:#f60;"> × '.$og['num'].'</div>'.
							'</div>'.
						'</div>';
					}else{
						$goodsdata[] = '<div style="font-size:12px;float:left;clear:both;margin:1px 0">'.
							'<img src="'.$og['pic'].'" style="max-width:60px;float:left">'.
							'<div style="float: left;width:160px;margin-left: 10px;white-space:normal;line-height:16px;">'.
								'<div style="width:100%;min-height:25px;max-height:32px;overflow:hidden">'.$og['name'].'</div>'.
								'<div style="padding-top:0px;color:#f60"><span style="color:#888">'.$og['ggname'].'</span></div>'.
								'<div style="padding-top:0px;color:#f60;">￥'.$og['sell_price'].' × '.$og['num'].'</div>'.
							'</div>'.
						'</div>';
					}
					
				}
				$data[$k]['goodsdata'] = implode('',$goodsdata);

                $data[$k]['pstype'] = '';
                if($v['psid']>=0){
                    $data[$k]['pstype'] = '系统配送';
                }else if($v['psid'] == -1){
                    $data[$k]['pstype'] = t('码科').'配送';
                }else{
                    if(getcustom('express_maiyatian')){
                        if($v['psid'] == -2){
                            $data[$k]['pstype'] = '麦芽田配送';
                        }
                    }
                }
                if(getcustom('paotui')){
		            if($v['tip_fee']>0){
		            	$data[$k]['ticheng'] = $v['ticheng'].'+'.$v['tip_fee'].'元小费';
		            }
		        }
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		$psusers = Db::name('peisong_user')->where('aid',aid)->order('sort desc,id')->select()->toArray();
		View::assign('psusers',$psusers);
        $this->defaultSet();
		return View::fetch();
    }
	//配送记录导出
	public function excel(){
		if(input('param.field') && input('param.order')){
			$order = input('param.field').' '.input('param.order');
		}else{
			$order = 'id desc';
		}
        $page = input('param.page');
        $limit = input('param.limit');
		$where = array();
		$where[] = ['aid','=',aid];
		if(bid > 0){
			$where[] = ['bid','=',bid];
		}
		if(input('param.psid')) $where[] = ['psid','=',input('param.psid')];
		if(input('param.ordernum')) $where[] = ['ordernum','=',input('param.ordernum')];
		if(input('?param.status') && input('param.status')!=='') $where[] = ['status','=',input('param.status')];
        if(input('?param.pstype') && input('param.pstype')!==''){
            $pstype = input('param.pstype');
            if($pstype == 1){
                $where[] = ['psid','>=',0];
            }else if($pstype == 2){
                $where[] = ['psid','=',-1];
            }else if($pstype == 3){
                $where[] = ['psid','=',-2];
            }
        }
		$list = Db::name('peisong_order')->where($where)->order($order)->page($page,$limit)->select()->toArray();
        $count = Db::name('peisong_order')->where($where)->order($order)->count();
		
		$title = array('订单号','配送员','下单人','所属商家','商品信息','总价','实付款','收货地址','支付方式','配送状态','配送类型','配送提成');
		$data = [];
		foreach($list as $k=>$vo){
			$member = Db::name('member')->where('id',$vo['mid'])->field('headimg,nickname')->find();
            if($vo['psid']==-2){
                $psuser = ['realname'=>'','tel'=>'','latitude'=>'','longitude'=>''];
                if(getcustom('express_maiyatian')) {
                    //查询关联表
                    $myt = Db::name('peisong_order_myt')->where('poid',$vo['id'])->where('aid',aid)->find();
                    if($myt){
                        $psuser = [];
                        $psuser['realname'] = $myt['rider_name'];
                        $psuser['tel']      = $myt['rider_phone'];
                        $psuser['latitude'] = $myt['rider_latitude'];
                        $psuser['longitude']= $myt['rider_longitude'];
                    }
                }
            }else if($vo['psid']==-1){
                $psuser['psuser'] = ['realname'=>$vo['make_rider_name'],'tel'=>$vo['make_rider_mobile']];
            }else{
                $psuser = Db::name('peisong_user')->where('id',$vo['psid'])->field('realname,tel')->find();
            }
			$orderinfo = json_decode($vo['orderinfo'],true);
			$binfo = json_decode($vo['binfo'],true);
			$prolist = json_decode($vo['prolist'],true);
			$xm=array();
			foreach($prolist as $gg){
				$xm[] = $gg['name']."/".$gg['ggname']." × ".$gg['num']."";
			}
			$status='';
			if($vo['status']==0){
				$status = '待接单';
			}elseif($vo['status']==1){
				$status = '已接单';
			}elseif($vo['status']==2){
				$status = '已到店';
			}elseif($vo['status']==3){
				$status = '已取货';
			}elseif($vo['status']==4){
				$status = '已完成';
			}elseif($vo['status']==10){
				$status = '已取消';
			}
			$psstatus = '';
			if($vo['status']==0){
				$psstatus = '待配送';
			}else if($vo['status']==1){
				$psstatus = '配送中';
			}else if($vo['status']==2){
				$psstatus = '已完成';
			}

            $pstype = '';
            if($vo['psid']>=0){
                $pstype = '系统配送';
            }else if($vo['psid'] == -1){
                $pstype = t('码科').'配送';
            }else{
                if(getcustom('express_maiyatian')){
                    if($vo['psid'] == -2){
                        $pstype = '麦芽田配送';
                    }
                }
            }
            if($vo['type'] == 'paotui_order'){
                $b_name = '（跑腿）'.$binfo['name'];
            }else{
                $b_name = $binfo['name'];
            }
            $ticheng = $vo['ticheng'];
            if(getcustom('paotui')){
	            if($vo['tip_fee']>0){
	            	$ticheng = $vo['ticheng'].'+'.$vo['tip_fee'].'元小费';
	            }
	        }
			$data[] = [
				' '.$orderinfo['ordernum'],
				$psuser['realname'].' '.$psuser['tel'],
				$member['nickname'],
				$b_name,
				implode("\r\n",$xm),
				$orderinfo['product_price'],
				$orderinfo['totalprice'],
				$orderinfo['linkman'].'('.$orderinfo['tel'].') '.$orderinfo['area'].' '.$orderinfo['address'],
				$orderinfo['paytype'],
				$status,
                $pstype,
				$ticheng
			]; 
		}
        return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data,'title'=>$title]);
		$this->export_excel($title,$data);
	}
	//删除
	public function del(){
		$ids = input('post.ids/a');
		$where = [];
		$where[] = ['aid','=',aid];
		if(bid > 0){
			$where[] = ['bid','=',bid];
		}
		$where[] = ['id','in',$ids];
		Db::name('peisong_order')->where($where)->delete();
		\app\commons\System::plog('删除配送记录'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	//改状态
	public function setst(){
		$ids = input('post.ids/a');
		$st = input('post.st/d');
		$where = [];
		$where[] = ['aid','=',aid];
		if(bid > 0){
			$where[] = ['bid','=',bid];
		}
		$where[] = ['id','in',$ids];
		$psorderlist = Db::name('peisong_order')->where($where)->select()->toArray();
		foreach($psorderlist as $k=>$v){
			if($st == 10){ //取消
				\app\models\PeisongOrder::quxiao($v);
			}else{
				Db::name('peisong_order')->where('aid',aid)->where('id',$v['id'])->update(['status'=>$st]);
			}
		}
		\app\commons\System::plog('取消配送单'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'取消成功']);
	}


    //配送记录
    public function wxOrder(){
    	
        if(request()->isAjax()){
        	if(bid > 0){
				return json(['code'=>0,'msg'=>'查询成功','count'=>0,'data'=>'']);
			}
            $page = input('param.page');
            $limit = input('param.limit');
            if(input('param.field') && input('param.order')){
                $order = input('param.field').' '.input('param.order');
            }else{
                $order = 'id desc';
            }
            $where = array();
            $where[] = ['aid','=',aid];
            if(input('param.psid')) $where[] = ['psid','=',input('param.psid')];
            if(input('param.ordernum')) $where[] = ['ordernum','=',input('param.ordernum')];
            if(input('?param.status') && input('param.status')!=='') $where[] = ['status','=',input('param.status')];
            $count = 0 + Db::name('express_wx_order')->where($where)->count();
            $data = Db::name('express_wx_order')->where($where)->page($page,$limit)->order($order)->select()->toArray();
            foreach($data as $k=>$v){
                $data[$k]['member'] = Db::name('member')->where('id',$v['mid'])->field('headimg,nickname')->find();
//                if($v['psid']==-1){
//                    $data[$k]['psuser'] = ['realname'=>$v['make_rider_name'],'tel'=>$v['make_rider_mobile']];
//                }else{
//                    $data[$k]['psuser'] = Db::name('peisong_user')->where('id',$v['psid'])->field('realname,tel')->find();
//                }
                //查状态
                $orderStatusEnd = \app\customs\ExpressWx::$orderStatusEnd;
                if($v['order_status'] && !in_array($v['order_status'],$orderStatusEnd)) {
                    $rs = \app\customs\ExpressWx::getOrder($v);
                    if($rs['status'] == 1) {
                        $data[$k] = $v = $rs['order'];
                    };
                }

                $data[$k]['orderinfo'] = json_decode($v['orderinfo'],true);
                $data[$k]['binfo'] = json_decode($v['binfo'],true);
                $data[$k]['prolist'] = json_decode($v['prolist'],true);

                $goodsdata=array();
                foreach($data[$k]['prolist'] as $og){
                    $goodsdata[] = '<div style="font-size:12px;float:left;clear:both;margin:1px 0">'.
                        '<img src="'.$og['pic'].'" style="max-width:60px;float:left">'.
                        '<div style="float: left;width:160px;margin-left: 10px;white-space:normal;line-height:16px;">'.
                        '<div style="width:100%;min-height:25px;max-height:32px;overflow:hidden">'.$og['name'].'</div>'.
                        '<div style="padding-top:0px;color:#f60"><span style="color:#888">'.$og['ggname'].'</span></div>'.
                        '<div style="padding-top:0px;color:#f60;">￥'.$og['sell_price'].' × '.$og['num'].'</div>'.
                        '</div>'.
                        '</div>';
                }
                $data[$k]['goodsdata'] = implode('',$goodsdata);
            }
            return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
        }
        return View::fetch();
    }

    public function wxOrderExcel(){

        if(bid > 0){
            return json(['code'=>0,'msg'=>'查询成功','count'=>0,'data'=>'']);
        }
        $page = input('param.page');
        $limit = input('param.limit');
        if(input('param.field') && input('param.order')){
            $order = input('param.field').' '.input('param.order');
        }else{
            $order = 'id desc';
        }
        $where = array();
        $where[] = ['aid','=',aid];
        if(input('param.psid')) $where[] = ['psid','=',input('param.psid')];
        if(input('param.ordernum')) $where[] = ['ordernum','=',input('param.ordernum')];
        if(input('?param.status') && input('param.status')!=='') $where[] = ['status','=',input('param.status')];
        $count = 0 + Db::name('express_wx_order')->where($where)->count();
        $data = Db::name('express_wx_order')->where($where)->page($page,$limit)->order($order)->select()->toArray();
        foreach($data as $k=>$v){
            $data[$k]['member'] = Db::name('member')->where('id',$v['mid'])->field('headimg,nickname')->find();
//                if($v['psid']==-1){
//                    $data[$k]['psuser'] = ['realname'=>$v['make_rider_name'],'tel'=>$v['make_rider_mobile']];
//                }else{
//                    $data[$k]['psuser'] = Db::name('peisong_user')->where('id',$v['psid'])->field('realname,tel')->find();
//                }
            //查状态
            $orderStatusEnd = \app\customs\ExpressWx::$orderStatusEnd;
            if($v['order_status'] && !in_array($v['order_status'],$orderStatusEnd)) {
                $rs = \app\customs\ExpressWx::getOrder($v);
                if($rs['status'] == 1) {
                    $data[$k] = $v = $rs['order'];
                };
            }

            $data[$k]['orderinfo'] = json_decode($v['orderinfo'],true);
            $data[$k]['binfo'] = json_decode($v['binfo'],true);
            $data[$k]['prolist'] = json_decode($v['prolist'],true);

            $goodsdata=array();
            foreach($data[$k]['prolist'] as $og){
                $goodsdata[] = '<div style="font-size:12px;float:left;clear:both;margin:1px 0">'.
                    '<img src="'.$og['pic'].'" style="max-width:60px;float:left">'.
                    '<div style="float: left;width:160px;margin-left: 10px;white-space:normal;line-height:16px;">'.
                    '<div style="width:100%;min-height:25px;max-height:32px;overflow:hidden">'.$og['name'].'</div>'.
                    '<div style="padding-top:0px;color:#f60"><span style="color:#888">'.$og['ggname'].'</span></div>'.
                    '<div style="padding-top:0px;color:#f60;">￥'.$og['sell_price'].' × '.$og['num'].'</div>'.
                    '</div>'.
                    '</div>';
            }
            $data[$k]['goodsdata'] = implode('',$goodsdata);
        }
        $status_arr = [
            '101' => '等待分配骑手',
            '102' => '已分配骑手',
            '103' => '商家取消订单',
            '201' => '骑手到店',
            '202' => '骑手取货成功',
            '203' => '取货失败-商家取消',
            '204' => '取货失败-骑手原因',
            '205' => '取货失败-骑手因商家取消',
            '301' => '配送中',
            '302' => '配送完成',
            '303' => '返还-商家取消',
            '304' => '返还-无法联系收货人',
            '305' => '返还-收货人拒收',
            '401' => '返还-成功',
            '501' => '运力系统原因取消',
            '502' => '不可抗拒因素（天气，道路管制等原因）取消',
        ];
        $title = array('ID','配送单号','下单人','所属商家','商品信息','订单号','总价','实付款','收货地址','支付方式','配送状态','配送费');
        $list = [];
        foreach($data as $k=>$vo){
            if($vo['psid']==-2){
                $psuser = ['realname'=>'','tel'=>'','latitude'=>'','longitude'=>''];
                if(getcustom('express_maiyatian')) {
                    //查询关联表
                    $myt = Db::name('peisong_order_myt')->where('poid',$vo['id'])->where('aid',aid)->find();
                    if($myt){
                        $psuser = [];
                        $psuser['realname'] = $myt['rider_name'];
                        $psuser['tel']      = $myt['rider_phone'];
                        $psuser['latitude'] = $myt['rider_latitude'];
                        $psuser['longitude']= $myt['rider_longitude'];
                    }
                }
            }else if($vo['psid']==-1){
                $psuser['psuser'] = ['realname'=>$vo['make_rider_name'],'tel'=>$vo['make_rider_mobile']];
            }else{
                $psuser = Db::name('peisong_user')->where('id',$vo['psid'])->field('realname,tel')->find();
            }
            $binfo = json_decode($vo['binfo'],true);
            $prolist = json_decode($vo['prolist'],true);
            $xm=array();
            foreach($prolist as $gg){
                $xm[] = $gg['name']."/".$gg['ggname']." × ".$gg['num']."";
            }

            if($vo['type'] == 'paotui_order'){
                $b_name = '（跑腿）'.$binfo['name'];
            }else{
                $b_name = $binfo['name'];
            }
            if(getcustom('paotui')){
                if($vo['tip_fee']>0){
                    $ticheng = $vo['ticheng'].'+'.$vo['tip_fee'].'元小费';
                }
            }
            $list[] = [
                $vo['id'],
                $vo['waybill_id'],
                $vo['member']['nickname'].'('.$vo['member']['id'].')',
                $b_name,
                $vo['goodsdata'],
                $vo['orderinfo']['ordernum'],
                $vo['orderinfo']['product_price'],
                $vo['orderinfo']['totalprice'],
                $vo['orderinfo']['linkman'].$vo['orderinfo']['tel'].$vo['orderinfo']['area'].$vo['orderinfo']['address'],
                $vo['orderinfo']['paytype'],
                $status_arr[$vo['order_status']],
                $vo['orderinfo']['freight_price'],
            ];
        }
        return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$list,'title'=>$title]);
    }

    public function wxOrderCancel()
    {
    	if(bid > 0){
			return json(['status'=>0,'msg'=>'操作失败']);
		}
        $id = input('post.orderid/d');
        $cancel_reason_id = input('post.cancel_reason_id/d');
        $psorder = Db::name('express_wx_order')->where('aid',aid)->where('id',$id)->find();
        //取消
        $rs = \app\customs\ExpressWx::cancelOrder($psorder,$cancel_reason_id);
        if($rs['status']==1){
            \app\commons\System::plog('取消即时配送单:'.$id);
            return json(['status'=>1,'msg'=>$rs['msg']]);
        }else{
            return json($rs);
        }

    }
    function defaultSet(){
        $set = Db::name('peisong_set')->where('aid',aid)->find();
        if(!$set){
            Db::name('peisong_set')->insert(['aid'=>aid]);
        }
    }
}