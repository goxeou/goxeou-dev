<?php


// +----------------------------------------------------------------------
// | 核销记录
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class Hexiao extends Common
{
    public function initialize(){
		parent::initialize();
	}
	//核销记录
    public function index(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = 'hexiao_order.'.input('param.field').' '.input('param.order');
			}else{
				$order = 'hexiao_order.id desc';
			}
			$where = [];
			$where[] = ['hexiao_order.aid','=',aid];
			$where[] = ['hexiao_order.bid','=',bid];
			if(input('orderid')){
                $where[] = ['hexiao_order.orderid','=',input('orderid')];
            }
			if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
			if(input('param.title')) $where[] = ['hexiao_order.title','like','%'.trim(input('param.title')).'%'];
			if(input('param.remark')) $where[] = ['hexiao_order.remark','like','%'.trim(input('param.remark')).'%'];
			if(input('param.mid')) $where[] = ['hexiao_order.mid','=',trim(input('param.mid'))];
			if(input('?param.status') && input('param.status')!=='') $where[] = ['hexiao_order.status','=',input('param.status')];
			if(getcustom('hexiao_search_mendian')){
				if(input('param.mdid')) $where[] = ['hexiao_order.mdid','=',trim(input('param.mdid'))];
				if(input('param.ctime')){
					$ctime = explode(' ~ ',input('param.ctime'));
					$where[] = ['hexiao_order.createtime','>=',strtotime($ctime[0])];
					$where[] = ['hexiao_order.createtime','<',strtotime($ctime[1])];
				}
				
			}
			if(input('param.hxmid')) $where[] = ['hexiao_order.hxmid','=',input('param.hxmid')];

            if(input('param.ctime') ){
                $ctime = explode(' ~ ',input('param.ctime'));
                $where[] = ['hexiao_order.createtime','>=',strtotime($ctime[0])];
                $where[] = ['hexiao_order.createtime','<',strtotime($ctime[1])];
            }

			$count = 0 + Db::name('hexiao_order')->alias('hexiao_order')->field('member.nickname,member.headimg,hexiao_order.*')->join('member member','member.id=hexiao_order.mid')->where($where)->count();
			$data = Db::name('hexiao_order')->alias('hexiao_order')->field('member.nickname,member.headimg,hexiao_order.*')->join('member member','member.id=hexiao_order.mid')->where($where)->page($page,$limit)->order($order)->select()->toArray();

			$typeArr = ['coupon'=>'优惠券','shop'=>'商城商品','scoreshop'=>'积分兑换商品','seckill'=>'秒杀商品','kanjia'=>'砍价商品','lucky_collage'=>'幸运拼团商品','collage'=>'拼团商品','tuangou'=>'团购商品','choujiang'=>'抽奖活动奖品','kanjia'=>'砍价商品','restaurant_takeaway'=>'外卖商品','takeaway_order_product'=>'外卖单独商品','gift_bag'=>'礼包','gift_bag_goods'=>'礼包单独活动'];

			foreach($data as $k=>$v){
				$data[$k]['typename'] = ($v['type'] ? $typeArr[$v['type']] : '');

				//门店
				$data[$k]['md_name'] = '无';
				if($v['mdid'] && $v['mdid']>0){
					$mendian = Db::name('mendian')->where('id',$v['mdid'])->field('id,name')->find();
					if($mendian){
						$data[$k]['md_name'] = $mendian['name'];
					}
				}
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		if(getcustom('hexiao_search_mendian')){
			$mdlist = Db::name('mendian')->where('aid',aid)->where('bid',bid)->where('status',1)->select()->toArray();
			View::assign('mdlist',$mdlist);
		}
        $logdel_auth = true;
        if(getcustom('business_del_auth')){
            if($this->auth_data == 'all' || in_array('Payorder/logdel',$this->auth_data)){
                $logdel_auth = true;
            }else{
                $logdel_auth = false;
            }
        }
        View::assign('logdel_auth',$logdel_auth);
		return View::fetch();
    }
	//导出
	public function excel(){
		if(input('param.field') && input('param.order')){
			$order = 'hexiao_order.'.input('param.field').' '.input('param.order');
		}else{
			$order = 'hexiao_order.id desc';
		}
        $page = input('param.page');
        $limit = input('param.limit');
		$where = array();
		$where[] = ['hexiao_order.aid','=',aid];
		$where[] = ['hexiao_order.bid','=',bid];
		
		if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
		if(input('param.title')) $where[] = ['hexiao_order.title','like','%'.trim(input('param.title')).'%'];
		if(input('param.remark')) $where[] = ['hexiao_order.remark','like','%'.trim(input('param.remark')).'%'];
		if(input('param.mid')) $where[] = ['hexiao_order.mid','=',trim(input('param.mid'))];
		if(input('?param.status') && input('param.status')!=='') $where[] = ['hexiao_order.status','=',input('param.status')];

        if(input('param.ctime') ){
            $ctime = explode(' ~ ',input('param.ctime'));
            $where[] = ['hexiao_order.createtime','>=',strtotime($ctime[0])];
            $where[] = ['hexiao_order.createtime','<',strtotime($ctime[1])];
        }

		$list = Db::name('hexiao_order')->alias('hexiao_order')->field('member.nickname,member.headimg,hexiao_order.*')
            ->join('member member','member.id=hexiao_order.mid')->where($where)->page($page,$limit)->order($order)->select()->toArray();
        $count = Db::name('hexiao_order')->alias('hexiao_order')->field('member.nickname,member.headimg,hexiao_order.*')
            ->join('member member','member.id=hexiao_order.mid')->where($where)->count();
		$title = array();
		$title[] = 'ID';
		$title[] = t('会员').'信息';
		$title[] = '订单号';
		$title[] = '订单类型';
		$title[] = '核销内容';
		$title[] = '门店';
		$title[] = '核销时间';
		$title[] = '备注信息';
		$data = array();
		$typeArr = ['coupon'=>'优惠券','shop'=>'商城商品','scoreshop'=>'积分兑换商品','seckill'=>'秒杀商品','kanjia'=>'砍价商品','lucky_collage'=>'幸运拼团商品','collage'=>'拼团商品','tuangou'=>'团购商品','choujiang'=>'抽奖活动奖品','kanjia'=>'砍价商品','kanjia'=>'砍价商品','restaurant_takeaway'=>'外卖商品','takeaway_order_product'=>'外卖单独商品','gift_bag'=>'礼包','gift_bag_goods'=>'礼包单独活动'];

		foreach($list as $v){
			//门店
			$md_name = '无';
			if($v['mdid'] && $v['mdid']>0){
				$mendian = Db::name('mendian')->where('id',$v['mdid'])->field('id,name')->find();
				if($mendian){
					$md_name = $mendian['name'];
				}
			}
			$tdata = array();
			$tdata[] = $v['id'];
			$tdata[] = $v['nickname'].'('.t('会员').'ID:'.$v['mid'].')';
			$tdata[] = ' '.$v['ordernum'];
			$tdata[] = ($v['type'] ? $typeArr[$v['type']] : '');
			$tdata[] = $v['title'];
			$tdata[] = $md_name ;
			$tdata[] = date('Y-m-d H:i:s',$v['createtime']);
			$tdata[] = $v['remark'];
			$data[] = $tdata;
		}
        return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data,'title'=>$title]);
		$this->export_excel($title,$data);
	}
	//删除
	public function del(){
		$ids = input('post.ids/a');
		Db::name('hexiao_order')->where('aid',aid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('删除核销记录'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}

	
	//计次核销记录
    public function shopproduct(){
		$bArr = Db::name('business')->where('aid',aid)->where('status',1)->column('name','id');
		$bArr['0'] = '[平台]';
		if(request()->isAjax() || input('param.excel') == 1){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.excel') == 1){
				$page = 1;$limit = 1000000000000;
			}
			if(input('param.field') && input('param.order')){
				$order = 'hexiao_shopproduct.'.input('param.field').' '.input('param.order');
			}else{
				$order = 'hexiao_shopproduct.id desc';
			}
			$where = [];
			$where[] = ['hexiao_shopproduct.aid','=',aid];
			if(bid > 0){
				$where[] = ['hexiao_shopproduct.bid','=',bid];
			}elseif(input('?param.bid') && input('param.bid')!==''){
				$where[] = ['hexiao_shopproduct.bid','=',input('param.bid')];
			}
			
			if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
			if(input('param.title')) $where[] = ['hexiao_shopproduct.title','like','%'.trim(input('param.title')).'%'];
			if(input('param.mid')) $where[] = ['hexiao_shopproduct.mid','=',trim(input('param.mid'))];
			if(input('?param.status') && input('param.status')!=='') $where[] = ['hexiao_shopproduct.status','=',input('param.status')];
			$count = 0 + Db::name('hexiao_shopproduct')->alias('hexiao_shopproduct')->field('member.nickname,member.headimg,hexiao_shopproduct.*')->join('member member','member.id=hexiao_shopproduct.mid')->where($where)->count();
			$list = Db::name('hexiao_shopproduct')->alias('hexiao_shopproduct')->field('member.nickname,member.headimg,hexiao_shopproduct.*')->join('member member','member.id=hexiao_shopproduct.mid')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			
			foreach($list as $k=>$v){
				$list[$k]['bname'] = $bArr[$v['bid']];
			}
			if(input('param.excel') == 1){
				$title = array();
				$title[] = 'ID';
				$title[] = '核销商家';
				$title[] = t('会员').'信息';
				$title[] = '订单号';
				$title[] = '商品名称';
				$title[] = '核销数量';
				$title[] = '核销时间';
				$title[] = '备注信息';
				$data = array();
				foreach($list as $v){
					$tdata = array();
					$tdata[] = $v['id'];
					$tdata[] = $v['bname'];
					$tdata[] = $v['nickname'].'('.t('会员').'ID:'.$v['mid'].')';
					$tdata[] = ' '.$v['ordernum'];
					$tdata[] = $v['title'];
					$tdata[] = $v['num'];
					$tdata[] = date('Y-m-d H:i:s',$v['createtime']);
					$tdata[] = $v['remark'];
					$data[] = $tdata;
				}
				$this->export_excel($title,$data);
			}
			if($page == 1){
				$total_num = 0 + Db::name('hexiao_shopproduct')->alias('hexiao_shopproduct')->field('member.nickname,member.headimg,hexiao_shopproduct.*')->join('member member','member.id=hexiao_shopproduct.mid')->where($where)->sum('num');
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$list,'total_num'=>$total_num]);
		}
		View::assign('bArr',$bArr);
		return View::fetch();
    }
	//删除
	public function del2(){
		$ids = input('post.ids/a');
		Db::name('hexiao_shopproduct')->where('aid',aid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('删除计次核销记录'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}

	public function restaurantproduct(){
    	if(getcustom('goods_hexiao')) {
			$bArr = Db::name('business')->where('aid',aid)->where('status',1)->column('name','id');
			$bArr['0'] = '[平台]';
			if(request()->isAjax() || input('param.excel') == 1){
				$page = input('param.page');
				$limit = input('param.limit');
				if(input('param.excel') == 1){
					$page = 1;$limit = 1000000000000;
				}
				if(input('param.field') && input('param.order')){
					$order = 'hexiao_restaurantproduct.'.input('param.field').' '.input('param.order');
				}else{
					$order = 'hexiao_restaurantproduct.id desc';
				}
				$where = [];
				$where[] = ['hexiao_restaurantproduct.aid','=',aid];
				if(bid > 0){
					$where[] = ['hexiao_restaurantproduct.bid','=',bid];
				}elseif(input('?param.bid') && input('param.bid')!==''){
					$where[] = ['hexiao_restaurantproduct.bid','=',input('param.bid')];
				}
				
				if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
				if(input('param.title')) $where[] = ['hexiao_restaurantproduct.title','like','%'.trim(input('param.title')).'%'];
				if(input('param.mid')) $where[] = ['hexiao_restaurantproduct.mid','=',trim(input('param.mid'))];
				if(input('?param.status') && input('param.status')!=='') $where[] = ['hexiao_restaurantproduct.status','=',input('param.status')];
				$count = 0 + Db::name('hexiao_restaurantproduct')->alias('hexiao_restaurantproduct')->field('member.nickname,member.headimg,hexiao_restaurantproduct.*')->join('member member','member.id=hexiao_restaurantproduct.mid')->where($where)->count();
				$list = Db::name('hexiao_restaurantproduct')->alias('hexiao_restaurantproduct')->field('member.nickname,member.headimg,hexiao_restaurantproduct.*')->join('member member','member.id=hexiao_restaurantproduct.mid')->where($where)->page($page,$limit)->order($order)->select()->toArray();
				
				foreach($list as $k=>$v){
					$list[$k]['bname'] = $bArr[$v['bid']];
				}
                $title = array();
                $title[] = 'ID';
                $title[] = '核销商家';
                $title[] = t('会员').'信息';
                $title[] = '订单号';
                $title[] = '商品名称';
                $title[] = '核销数量';
                $title[] = '核销时间';
                $title[] = '备注信息';
				if(input('param.excel') == 1){
					$data = array();
					foreach($list as $v){
						$tdata = array();
						$tdata[] = $v['id'];
						$tdata[] = $v['bname'];
						$tdata[] = $v['nickname'].'('.t('会员').'ID:'.$v['mid'].')';
						$tdata[] = ' '.$v['ordernum'];
						$tdata[] = $v['title'];
						$tdata[] = $v['num'];
						$tdata[] = date('Y-m-d H:i:s',$v['createtime']);
						$tdata[] = $v['remark'];
						$data[] = $tdata;
					}
					$this->export_excel($title,$data);
				}
				if($page == 1){
					$total_num = 0 + Db::name('hexiao_restaurantproduct')->alias('hexiao_restaurantproduct')->field('member.nickname,member.headimg,hexiao_restaurantproduct.*')->join('member member','member.id=hexiao_restaurantproduct.mid')->where($where)->sum('num');
				}
				return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$list,'total_num'=>$total_num,'title'=>$title]);
			}
			View::assign('bArr',$bArr);
			return View::fetch();
		}
    }

    //删除
	public function del3(){
		if(getcustom('goods_hexiao')) {
			$ids = input('post.ids/a');
			Db::name('hexiao_restaurantproduct')->where('aid',aid)->where('id','in',$ids)->delete();
			\app\commons\System::plog('删除外卖核销记录'.implode(',',$ids));
			return json(['status'=>1,'msg'=>'删除成功']);
		}
	}

    public function giftbagproduct(){
        if(getcustom('extend_gift_bag')){
            $bArr = Db::name('business')->where('aid',aid)->where('status',1)->column('name','id');
            $bArr['0'] = '[平台]';
            if(request()->isAjax() || input('param.excel') == 1){
                $page = input('param.page');
                $limit = input('param.limit');
                if(input('param.excel') == 1){
                    $page = 1;$limit = 1000000000000;
                }
                if(input('param.field') && input('param.order')){
                    $order = 'hxgp.'.input('param.field').' '.input('param.order');
                }else{
                    $order = 'hxgp.id desc';
                }
                $where = [];
                $where[] = ['hxgp.aid','=',aid];
                if(bid > 0){
                    $where[] = ['hxgp.bid','=',bid];
                }elseif(input('?param.bid') && input('param.bid')!==''){
                    $where[] = ['hxgp.bid','=',input('param.bid')];
                }
                
                if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
                if(input('param.title')) $where[] = ['hxgp.title','like','%'.trim(input('param.title')).'%'];
                if(input('param.mid')) $where[] = ['hxgp.mid','=',trim(input('param.mid'))];
                if(input('?param.status') && input('param.status')!=='') $where[] = ['hxgp.status','=',input('param.status')];
                $count = 0 + Db::name('hexiao_giftbagproduct')->alias('hxgp')->field('member.nickname,member.headimg,hxgp.*')->join('member member','member.id=hxgp.mid')->where($where)->count();
                $list = Db::name('hexiao_giftbagproduct')->alias('hxgp')->field('member.nickname,member.headimg,hxgp.*')->join('member member','member.id=hxgp.mid')->where($where)->page($page,$limit)->order($order)->select()->toArray();
                
                foreach($list as $k=>$v){
                    $list[$k]['bname'] = $bArr[$v['bid']];
                }
                $title = array();
                $title[] = 'ID';
                $title[] = '核销商家';
                $title[] = t('会员').'信息';
                $title[] = '订单号';
                $title[] = '商品名称';
                $title[] = '核销数量';
                $title[] = '核销时间';
                $title[] = '备注信息';
                if(input('param.excel') == 1){

                    $data = array();
                    foreach($list as $v){
                        $tdata = array();
                        $tdata[] = $v['id'];
                        $tdata[] = $v['bname'];
                        $tdata[] = $v['nickname'].'('.t('会员').'ID:'.$v['mid'].')';
                        $tdata[] = ' '.$v['ordernum'];
                        $tdata[] = $v['title'];
                        $tdata[] = $v['num'];
                        $tdata[] = date('Y-m-d H:i:s',$v['createtime']);
                        $tdata[] = $v['remark'];
                        $data[] = $tdata;
                    }
                    $this->export_excel($title,$data);
                }
                if($page == 1){
                    $total_num = 0 + Db::name('hexiao_giftbagproduct')->alias('hxgp')->field('member.nickname,member.headimg,hxgp.*')->join('member member','member.id=hxgp.mid')->where($where)->sum('num');
                }
                return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$list,'total_num'=>$total_num,'title'=>$title]);
            }
            View::assign('bArr',$bArr);
            return View::fetch();
        }
    }
}
