<?php


// +----------------------------------------------------------------------
// | 同城配送设置
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class Peisong extends Common
{
    public function initialize(){
		parent::initialize();
	}
    public function set(){
		if(bid > 0) showmsg('无访问权限');
		$info = Db::name('peisong_set')->where('aid',aid)->find();
		if(!$info){
			Db::name('peisong_set')->insert(['aid'=>aid]);
			$info = Db::name('peisong_set')->where('aid',aid)->find();
		}
		View::assign('info',$info);
		return View::fetch();
    }
	public function save(){
		if(bid > 0) showmsg('无访问权限');
		$info = input('post.info/a');
        Db::name('peisong_set')->where('aid',aid)->update($info);
		\app\commons\System::plog('同城配送系统设置');
		return json(['status'=>1,'msg'=>'保存成功','url'=>true]);
	}
    public function makeset(){
		if(bid > 0) showmsg('无访问权限');
		$info = Db::name('peisong_set')->where('aid',aid)->find();
		if(!$info){
			Db::name('peisong_set')->insert(['aid'=>aid]);
			$info = Db::name('peisong_set')->where('aid',aid)->find();
		}
		View::assign('info',$info);
		return View::fetch();
    }
	public function save2(){
		if(bid > 0) showmsg('无访问权限');
		$info = input('post.info/a');
        if($info['make_status']==1) {
            $set = Db::name('peisong_set')->where('aid', aid)->find();
            if($set['express_wx_status'] == 1) {
                return json(['status'=>0,'msg'=>'请先关闭即时配送']);
            }
            if(getcustom('express_maiyatian')){
                if($set['myt_status'] == 1) {
                    return json(['status'=>0,'msg'=>'请先关闭麦芽田配送']);
                }
            }
        }
		$info['make_access_token'] = '';
		$info['make_expire_time'] = '';
		Db::name('peisong_set')->where('aid',aid)->update($info);
		\app\commons\System::plog(t('码科跑腿').'设置');
		if($info['make_status']==1){
			\app\commons\Make::access_token(aid);
		}
		return json(['status'=>1,'msg'=>'保存成功','url'=>true]);
	}

	//获取配送员
	public function getpeisonguser(){
        //如果showtype==1 则不区分派单模式，直接强制派单
        $showtype = input('param.showtype',0);
		$set = Db::name('peisong_set')->where('aid',aid)->find();

		$order = Db::name(input('param.type'))->where('id',input('param.orderid'))->find();
		if($order['bid']>0){
			$business = Db::name('business')->field('name,address,tel,logo,longitude,latitude')->where('id',$order['bid'])->find();
		}else{
			$business = Db::name('admin_set')->field('name,address,tel,logo,longitude,latitude')->where('aid',aid)->find();
		}

        //查询骑行距离
        $mapqq = new \app\commons\MapQQ();
        $bicycl = $mapqq->getDirectionDistance($order['longitude'],$order['latitude'],$business['longitude'],$business['latitude'],1);
        if($bicycl && $bicycl['status']==1){
            $juli = $bicycl['distance'];
        }else{
            $juli = getdistance($order['longitude'],$order['latitude'],$business['longitude'],$business['latitude'],1);
        }
		$ticheng = \app\models\PeisongOrder::ticheng($set,$order,$juli/1000);
		if($set['make_status']==1){ //码科配送
			$rs = \app\commons\Make::getprice(aid,$order['bid'],$business['latitude'],$business['longitude'],$order['latitude'],$order['longitude']);
			if($rs['status']==0) return json($rs);
			$ticheng = $rs['price'];
			$selectArr = [];
			$set['paidantype'] = 2;
		}else{
			$selectArr = [];
			if($set['paidantype'] == 0 && $showtype!=1){ //抢单模式
				$selectArr[] = ['id'=>0,'title'=>'--配送员抢单--'];
			}else{
				$peisonguser = Db::name('peisong_user')->where('aid',aid)->where('status',1)->order('sort desc,id')->select()->toArray();
				foreach($peisonguser as $k=>$v){
					$dan = Db::name('peisong_order')->where('psid',$v['id'])->where('status','in','0,1')->count();
					$title = $v['realname'].'-'.$v['tel'].'(配送中'.$dan.'单)';
					$selectArr[] = ['id'=>$v['id'],'title'=>$title];
				}
			}
		}
		$psfee = $ticheng * (1 + $set['businessfee']*0.01);
		return json(['status'=>1,'peisonguser'=>$selectArr,'paidantype'=>$set['paidantype'],'psfee'=>$psfee,'ticheng'=>$ticheng]);
	}
	//下配送单
	public function peisong(){
		$orderid = input('post.orderid/d');
		$type = input('post.type');
		$psid = input('post.psid/d');

        $set = Db::name('peisong_set')->where('aid',aid)->find();
        if(getcustom('express_maiyatian')) {
            if($set['myt_status'] == 1){
                $psid = -2;//  -1、码科  -2、麦芽田配送
            }
        }

		if(bid == 0){
			$order = Db::name($type)->where('id',$orderid)->where('aid',aid)->find();

		}else{
			$order = Db::name($type)->where('id',$orderid)->where('aid',aid)->where('bid',bid)->find();
		}

		//如果选择了配送时间，未到配送时间内不可以进行配送
		if(!$order) return json(['status'=>0,'msg'=>'订单不存在']);
		if($order['status']!=1 && $order['status']!=12) return json(['status'=>0,'msg'=>'订单状态不符合']);

        $other = [];
        if(getcustom('express_maiyatian')){
            $other['myt_shop_id'] = input('post.myt_shop_id')?input('post.myt_shop_id'):0;
            $other['myt_weight']  = input('post.myt_weight')?input('post.myt_weight'):1;
            if(!is_numeric($other['myt_weight'])){
                return json(['status'=>0,'msg'=>'重量必须为纯数字']);
            }
            $other['myt_remark']  = input('post.myt_remark')?input('post.myt_remark'):'';
        }
		$rs = \app\models\PeisongOrder::create($type,$order,$psid,$other);
		if($rs['status']==0) return json($rs);
        //发货信息录入 微信小程序+微信支付
        if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
            \app\commons\Order::wxShipping(aid,$order,$type);
        }
		\app\commons\System::plog('订单配送'.$orderid);
		return json(['status'=>1,'msg'=>'操作成功']);
	}

	public function wxset()
    {
        if(bid > 0) showmsg('无访问权限');
        return View::fetch();
    }

    public function wxset_save(){
        if(bid > 0) showmsg('无访问权限');
        $info = input('post.info/a');
        if($info['express_wx_status'] == 1) {
            $set = Db::name('peisong_set')->where('aid',aid)->find();
            if($set['make_status'] == 1) {
                return json(['status'=>0,'msg'=>'请先关闭'.t('码科').'配送']);
            }
            if(getcustom('express_maiyatian')){
                if($set['myt_status'] == 1) {
                    return json(['status'=>0,'msg'=>'请先关闭麦芽田配送']);
                }
            }
        }
        Db::name('peisong_set')->where('aid',aid)->update($info);
        \app\commons\System::plog('即时配送设置');

        return json(['status'=>1,'msg'=>'保存成功','url'=>true]);
    }

    public function wxset_refresh(){
        if(bid > 0) showmsg('无访问权限');
        if(request()->isAjax()){
            $rs = \app\customs\ExpressWx::getBindAccount(aid,true);
            return json(['status'=>$rs['status'],'msg'=>$rs['msg'],'count'=>count($rs['shop_list']),'data'=>$rs['shop_list']]);
        }
    }

    public function wx_edit(){
        $id = input('param.id');
        $info = \app\customs\ExpressWx::getAccount(aid,$id);

        View::assign('info',$info);
        return View::fetch();
    }


    public function wx_edit_save(){
        if(bid > 0) showmsg('无访问权限');
        $info = input('post.info/a');
        $id = input('param.id');
        \app\customs\ExpressWx::updateAccount(aid,$id,$info);

        \app\commons\System::plog('即时配送编辑');
        return json(['status'=>1,'msg'=>'保存成功','url'=>true]);
    }
    public function wx_set_status() {
        $id = input('post.id');

        $rs = \app\customs\ExpressWx::accountSetStatus(aid,$id);
        return json(['status'=>$rs['status'],'msg'=>$rs['msg']]);
    }

    public function wx_addorder()
    {
        $orderid = input('post.orderid/d');
        $type = input('post.type');
        $psid = input('post.psid/d');
        if(bid == 0){
            $order = Db::name($type)->where('id',$orderid)->where('aid',aid)->find();
        }else{
            $order = Db::name($type)->where('id',$orderid)->where('aid',aid)->where('bid',bid)->find();
        }

        if(!$order) return json(['status'=>0,'msg'=>'订单不存在']);
        if($order['status']!=1 && $order['status']!=12) return json(['status'=>0,'msg'=>'订单状态不符合']);

        $rs = \app\customs\ExpressWx::addOrder($type,$order,$psid);
        if($rs['status']==0) return json($rs);
        \app\commons\System::plog('订单即时配送派单:'.$orderid);
        return json(['status'=>1,'msg'=>'操作成功']);
    }

    public function mytset()
    {
        if(getcustom('express_maiyatian')) {

            if(request()->isAjax()){
                if(input('param.field') && input('param.order')){
                    $order = input('param.field').' '.input('param.order');
                }else{
                    $order = 'id desc';
                }
                $where = [];
                $where[] = ['aid','=',aid];
                $where[] = ['is_del','=',0];
                $catelist = Db::name('peisong_myt_shop')->where('aid',aid)->where('bid',bid)->order($order)->select()->toArray();
                return json(['code'=>0,'msg'=>'查询成功','count'=>count($catelist),'data'=>$catelist]);
            }

            if(bid==0){
                $info = Db::name('peisong_set')->where('aid',aid)->find();
                if(!$info){
                    Db::name('peisong_set')->insert(['aid'=>aid]);
                    $info = Db::name('peisong_set')->where('aid',aid)->find();
                }else{
                    if($info['myt_logistic']){
                        $info['myt_logistic'] = explode(',',$info['myt_logistic']);
                    }
                }
            }else{
                $info = [];
            }

            View::assign('info',$info);
            $shop_id = 0+Db::name('peisong_myt_shop')->where('aid',aid)->where('bid',bid)->where('is_del',0)->value('id');
            View::assign('shop_id',$shop_id);
            View::assign('aid',aid);
            View::assign('bid',bid);
            if(getcustom('express_maiyatian_autopush')){
                $info2 = Db::name('peisong_myt_set')->where('aid',aid)->where('bid',bid)->find();
                $autopush_scenes = '';
                if($info2 && !empty($info2['autopush_scenes'])){
                    $autopush_scenes = explode(',',$info2['autopush_scenes']);
                }
                View::assign('autopush_scenes',$autopush_scenes);
                View::assign('info2',$info2);
            }
            return View::fetch();
        }
    }

    public function mytsave(){
        if(getcustom('express_maiyatian')) {
            if(bid > 0) showmsg('无访问权限');
            $info = input('post.info/a');
            $data = [];
            $data['myt_status'] = $info['myt_status'];

            $set = Db::name('peisong_set')->where('aid',aid)->find();
            if($info['myt_status'] == 1){
                if($set['make_status'] == 1) {
                    return json(['status'=>0,'msg'=>'请先关闭'.t('码科').'配送']);
                }
                if($set['express_wx_status'] == 1) {
                    return json(['status'=>0,'msg'=>'请先关闭即时配送']);
                }
            }
            $data['myt_appkey']       = $info['myt_appkey'];
            $data['myt_appsecret']    = $info['myt_appsecret'];
            $data['myt_dispatchmode'] = $info['myt_dispatchmode'];
            $data['myt_logistic']     = $info['myt_logistic'];
            $data['myt_issubscribe']  = !$info['myt_issubscribe'] || empty($info['myt_issubscribe'])?0:$info['myt_issubscribe'];
            $data['myt_callbackurl']  = !$info['myt_callbackurl'] || empty($info['myt_callbackurl'])?0:$info['myt_callbackurl'];
            $up = Db::name('peisong_set')->where('aid',aid)->update($data);
            //if($up){

                if($info['myt_appkey'] != $set['myt_appkey']){
                    //更改门店状
                    $shopArr = Db::name('peisong_myt_shop')->where('aid',aid)->where('bid',bid)->where('is_del',0)->field('id')->select()->toArray();
                    if($shopArr){
                        foreach($shopArr as $sv){
                            $up = Db::name('peisong_myt_shop')->where('id',$sv['id'])->update(['status'=>0]);
                            if($up){
                                \app\commons\System::plog('麦芽田appkey变动，门店状态改变'.$sv['id']);
                            }
                        }
                    }
                }
                \app\commons\System::plog('麦芽田设置');
                return json(['status'=>1,'msg'=>'保存成功','url'=>true]);
            // }else{
            //     return json(['status'=>0,'msg'=>'保存失败']);
            // }
        }
    }

    public function mytshopedit(){
        if(getcustom('express_maiyatian')) {

            $info = Db::name('peisong_myt_shop')->where('aid',aid)->where('bid',bid)->where('is_del',0)->find();
            if(!$info){
                $info = ['id'=>0];
            }

            View::assign('info',$info);
            return View::fetch();
        }
    }
    public function mytshopsave(){
        if(getcustom('express_maiyatian')) {
            
            $info = input('post.info/a');
            $id = $info['id']?$info['id']:0;
            unset($info['id']);
            //防止创建多个
            if(!$id){
                $myt_shop = Db::name('peisong_myt_shop')->where('aid',aid)->where('bid',bid)->where('is_del',0)->field('id,shop_id')->find();
                if($myt_shop){
                    $id = $myt_shop['id'];
                }
            }

            $shopdata = [];
            $shopdata['name']      = $info['name'];
            $shopdata['city']      = $info['city'];
            $shopdata['phone']     = $info['phone'];
            $shopdata['address']   = $info['address'];
            $shopdata['longitude'] = $info['longitude'];
            $shopdata['latitude']  = $info['latitude'];
            $shopdata['category']  = $info['category'];
            $shopdata['map_type']  = 1;
            if($id){

                $info['updatetime'] = time();
                $sql = Db::name('peisong_myt_shop')->where('id',$id)->where('aid',aid)->where('bid',bid)->update($info);

                $shopdata['origin_id'] = $id;
                $res = \app\customs\MaiYaTianCustom::shop_opt(aid,$shopdata,'',2);
                if($res['status'] != 1){
                    if($myt_shop && $myt_shop['shop_id'] == 0){

                        //查询shop_id
                        $res2 = \app\customs\MaiYaTianCustom::shop_detail(aid,$id);
                        if($res2['status'] != 1){
                            //如果不存在就创建
                            $shopdata['origin_id'] = $id;
                            $res3 = \app\customs\MaiYaTianCustom::shop_opt(aid,$shopdata);
                            if($res3['status'] == 1){
                                Db::name('peisong_myt_shop')->where('id',$id)->where('aid',aid)->where('bid',bid)->update(['status'=>1,'shop_id'=>$res3['data']]);
                            }else{
                                Db::name('peisong_myt_shop')->where('id',$id)->where('aid',aid)->where('bid',bid)->update(['status'=>0]);
                                return json(['status'=>0,'msg'=>$res3['msg']]);
                            }
                        }else{
                            $shop_id = $res2['data']['shop_id'];
                            Db::name('peisong_myt_shop')->where('id',$id)->where('aid',aid)->where('bid',bid)->update(['status'=>1,'shop_id'=>$shop_id]);
                            return json(['status'=>0,'msg'=>'请重新提交']);
                        }

                    }else{

                        if($res['msg'] != '门店信息未发生变化！'){
                            Db::name('peisong_myt_shop')->where('id',$id)->where('aid',aid)->where('bid',bid)->update(['status'=>0]);
                        }
                        return json(['status'=>0,'msg'=>$res['msg']]);

                    }
                }else{
                    Db::name('peisong_myt_shop')->where('id',$id)->where('aid',aid)->where('bid',bid)->update(['status'=>1]);
                }
            }else{

                $info['aid'] = aid;
                $info['bid'] = bid;
                $info['status']     = 1;
                $info['createtime'] = time();
                $sql = Db::name('peisong_myt_shop')->insertGetId($info);
                $id = $sql;

                $shopdata['origin_id'] = $id;
                $res = \app\customs\MaiYaTianCustom::shop_opt(aid,$shopdata);
                if($res['status'] == 1){
                    Db::name('peisong_myt_shop')->where('id',$id)->where('aid',aid)->where('bid',bid)->update(['status'=>1,'shop_id'=>$res['data']]);
                }else{
                    Db::name('peisong_myt_shop')->where('id',$id)->where('aid',aid)->where('bid',bid)->update(['status'=>0]);
                    return json(['status'=>0,'msg'=>$res['msg']]);
                }
            }

            if($sql){
                \app\commons\System::plog('麦芽田门店编辑'.$id);
                return json(['status'=>1,'msg'=>'保存成功','url'=>true]);
            }else{
                return json(['status'=>0,'msg'=>'保存失败']);
            }
        }
    }

    public function get_balance(){
        if(getcustom('express_maiyatian')) {
            if(bid > 0) showmsg('无访问权限');
            $set = Db::name('peisong_set')->where('aid',aid)->find();
            if(!$set || !$set['myt_appkey'] || !$set['myt_appsecret']){
                return json(['status'=>0,'msg'=>'配置不存在']);
            }
            $res = \app\customs\MaiYaTianCustom::merchant_balance(aid,$set);
            if($res['status'] != 1){
                return json(['status'=>0,'msg'=>$res['msg']]);
            }else{
                $balance = $res['data'];
                \app\commons\System::plog('麦芽田更新余额');
                return json(['status'=>1,'msg'=>'刷新成功','balance'=>$balance]);
            }
        }
    }

    public function mytprice(){
        if(getcustom('express_maiyatian')) {
            //下配送单
            $orderid = input('orderid/d');
            $type    = input('type');

            $msg    = '';//报错时显示
            $data   = '';//计价数据
            $detail = '';//配送详情

            if(bid == 0){
                $order = Db::name($type)->where('id',$orderid)->where('aid',aid)->find();
            }else{
                $order = Db::name($type)->where('id',$orderid)->where('aid',aid)->where('bid',bid)->find();
            }
            //如果选择了配送时间，未到配送时间内不可以进行配送
            if(getcustom('business_withdraw') && ($this->auth_data == 'all' || in_array('OrderSendintime',$this->auth_data))){
                if($order['freight_time']){
                    $freight_time = explode('~',$order['freight_time']);
                    $begin_time = strtotime($freight_time[0]);
                    $date = explode(' ',$freight_time[0]);
                    $end_time =strtotime($date[0].' '.$freight_time[1]);
                    if(time()<$begin_time || (time()>$end_time)){
                        $msg = '未在配送时间范围内';
                    }
                }
            }
            if(!$order) $msg = '订单不存在';
            if($order['status']!=1 && $order['status']!=12) $msg = '订单状态不符合';

            //查询重量
            $weight = \app\customs\MaiYaTianCustom::getweight(aid,$order,$type);//默认一千克

            $other = [];
            $other['myt_shop_id'] = 0;
            $other['myt_weight']  = $weight;
            $other['myt_remark']  = '';

            //预估配送费
            $set = Db::name('peisong_set')->where('aid',aid)->find();
            $res_price = \app\customs\MaiYaTianCustom::order_price(aid,$set,$order,'',$other,1);
            
            if($res_price['status']== 0){
                $msg = $res_price['msg'].'(麦芽田返回)';
            }else{
                $data = $res_price['data'];
                if($data && $data['detail']){
                    $detail = $data['detail'];
                }
            }

            View::assign('orderid',$orderid);
            View::assign('type',$type);
            View::assign('data',$data);
            View::assign('detail',$detail);
            View::assign('weight',$weight);
            View::assign('msg',$msg);
            return View::fetch();
        }
    }

    public function mytsave2(){
        }

}
