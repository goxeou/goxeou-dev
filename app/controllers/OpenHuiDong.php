<?php


// +----------------------------------------------------------------------
// | 慧动对接
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\Db;
use think\facade\Log;

//@update 7-28 慧动对接移除appid 根据渠道和aid联合查询
class OpenHuiDong
{
    public $aid;
    private $app;
    private $channel;
    public function __construct()
    {
        $header = request()->header();
        $input = input();
        $this->channel = 'huidong';

        header('Content-type: application/json');


        Log::write([
            'file'=>__FILE__.__LINE__,
            '$header'=>$header,
            'param'=>input('param.')
        ]);
        if(empty($header) || empty($header['aid']) || empty($header['sign']) || empty($header['timestamp'])){
            die(json_encode(['status'=>0,'msg'=>'参数错误']));
        }
        if(($header['timestamp'] > time()+5) || ($header['timestamp'] + 300 < time())){
            die(json_encode( ['status'=>0,'msg'=>'请求已过期']));
        }
        $this->aid = intval($header['aid']);
        $appinfo = \app\commons\System::appinfo($this->aid,'wx');
        if(!$appinfo){
            die(json_encode( ['status'=>0,'msg'=>'小程序配置不存在']));
        }
        $this->app = Db::name('open_app')->where('aid',$this->aid)->where('channel', $this->channel)->find();
        if(empty($this->app)){
            die(json_encode( ['status'=>0,'msg'=>'应用不存在']));
        }
        Db::name('open_app')->where('aid',$this->aid)->where('channel', $this->channel)->update(['request_time' => time()]);

        $huidong_status = Db::name('admin_set')->where('aid',$this->aid)->value('huidong_status');
        if($huidong_status != 1){
            die(json_encode( ['status'=>0,'msg'=>'已关闭']));
        }

        //验证签名
        $sign = $this->sign($header);

        if($header['sign'] != $sign){
            die(json_encode( ['status'=>0,'msg'=>'签名错误']));
        }
    }

    public function getMemberId()
    {
        $external_userid = input('post.external_userid');
        if(empty($external_userid)){
            die(json_encode( ['status'=>0,'msg'=>'参数错误']));
        }
        $member_id = Db::name('member')->where('aid',$this->aid)->where('huidong_mid',$external_userid)->value('id');
        if($member_id)
            die(json_encode(['status'=>1,'msg'=>'ok','member_id'=>$member_id]));
        else
            die(json_encode( ['status'=>0,'msg'=>'not found']));
    }

    public function getGoodsList()
    {
        $page = input('post.page',1);
        $pagesize = input('post.pagesize',10);
        if($pagesize > 100){
            die(json_encode(['status'=>0,'msg'=>'参数错误']));
        }
        $where = [];
        $where[] = ['aid','=',$this->aid];
        $where[] = ['ischecked','=',1];

        $where[] = ['douyin_product_id','=',''];

        $where[] = ['bid','=',0];
        if(input('param.field') && input('param.order')){
            $order = input('param.field').' '.input('param.order').',sort,id desc';
        }else{
            $order = 'sort desc,id desc';
        }
        if(input('param.keyword')){
            $where[] = ['name','like','%'.input('param.keyword').'%'];
        }
        $where2 = "find_in_set('-1',showtj)";
//        if($this->member){
//            $where2 .= " or find_in_set('".$this->member['levelid']."',showtj)";
//            if($this->member['subscribe']==1){
//                $where2 .= " or find_in_set('0',showtj)";
//            }
//        }
        $where[] = Db::raw($where2);
        $field = 'id,name,pic,pics,sales,stock,market_price,sell_price,sellpoint,price_type,status,createtime,start_time,end_time,start_hours,end_hours';
        $total = Db::name('shop_product')->field($field)->where($where)->count();
        $datalist = Db::name('shop_product')->field($field)->where($where)->page($page,$pagesize)->order($order)->select()->toArray();

        if(!$datalist) $datalist = [];
        else{
            foreach ($datalist as $k => $item){
                if($item['pics'])
                    $datalist[$k]['pics'] = explode(',',$item['pics']);
                else
                    $datalist[$k]['pics'] = [];
                $datalist[$k]['createtime_format'] = date('Y-m-d H:i',$item['createtime']);
                $datalist[$k]['wxapp_url'] = '/pages/shop/product?id='.$item['id'];
            }
        }

        $rdata = [];
        $rdata['status']=1;
        $rdata['datalist'] = $datalist;
        $rdata['page'] = $page;
        $rdata['pagesize'] = $pagesize;
        $rdata['total'] = $total;
        die(json_encode($rdata));
    }

    public function getOrderList()
    {
        $page = input('post.page',1);
        $pagesize = input('post.pagesize',10);
        if($pagesize > 100){
            die(json_encode(['status'=>0,'msg'=>'参数错误']));
        }
        $mid = input('post.member_id');
        $st = input('param.status');
        if(!input('?param.st') || $st === ''){
            $st = 'all';
        }
        $where = [];
        $where[] = ['aid','=',$this->aid];
        $where[] = ['bid','=',0];
        if($mid)
            $where[] = ['mid','=',$mid];
        $where[] = ['delete','=',0];
        if(input('param.keyword')) $where[] = ['ordernum|title', 'like', '%'.input('param.keyword').'%'];
        if($st == 'all'){

        }elseif($st == '0'){
            $where[] = ['status','=',0];
        }elseif($st == '1'){
            $where[] = ['status','=',1];
        }elseif($st == '2'){
            $where[] = ['status','=',2];
        }elseif($st == '3'){
            $where[] = ['status','=',3];
        }elseif($st == '10'){
            $where[] = ['refund_status','>',0];
        }
        $total = Db::name('shop_order')->where($where)->count();
        $datalist = Db::name('shop_order')->field('id,aid,bid,mid,ordernum,title,totalprice,createtime,status,linkman,tel,area,address,paytype,paytime')->where($where)->page($page,$pagesize)->order('id desc')->select()->toArray();
        if(!$datalist) $datalist = array();
        foreach($datalist as $key=>$v){
            $datalist[$key]['createtime_format']=date('Y-m-d H:i',$v['createtime']);
            $datalist[$key]['paytime_format']=$v['paytime'] ? date('Y-m-d H:i',$v['paytime']) : '';
            if($v['bid']!=0){
                $datalist[$key]['binfo'] = Db::name('business')->where('aid',$this->aid)->where('id',$v['bid'])->field('name,logo')->find();
                if(!$datalist[$key]['binfo']) $datalist[$key]['binfo'] = [];
            } else {
                $datalist[$key]['binfo'] = Db::name('admin_set')->where('aid',$this->aid)->field('name,logo')->find();
            }

            $datalist[$key]['procount'] = Db::name('shop_order_goods')->where('orderid',$v['id'])->sum('num');
            $datalist[$key]['refundnum'] = Db::name('shop_order_goods')->where('orderid',$v['id'])->sum('refund_num');
            $refundOrder = Db::name('shop_refund_order')->where('refund_status','>',0)->where('aid',$this->aid)->where('orderid',$v['id'])->count();
            $datalist[$key]['refundCount'] = $refundOrder;
            $datalist[$key]['prolist'] = Db::name('shop_order_goods')->field('id,aid,bid,mid,proid,name,pic,ggid,ggname,num,sell_price,totalprice,real_totalprice,status')->where('orderid',$v['id'])->select()->toArray();
            if(!$datalist[$key]['prolist']) $datalist[$key]['prolist'] = [];
            $datalist[$key]['wxapp_url'] = '/pagesExt/order/detail?id='.$v['id'];
        }
        $rdata = [];
        $rdata['status']=1;
        $rdata['datalist'] = $datalist;
        $rdata['page'] = $page;
        $rdata['pagesize'] = $pagesize;
        $rdata['total'] = $total;
        die(json_encode($rdata));
    }

    public function getCartList()
    {
        $mid = input('post.member_id');
        if(empty($mid)){
            die(json_encode(['status'=>0,'msg'=>'参数错误']));
        }
        $cartlist = Db::name('shop_cart')->field('id,bid,proid,ggid,num')->where('aid',$this->aid)
            ->where('bid',0)->where('mid',$mid)->order('createtime desc')->select()->toArray();

        if(!$cartlist) $cartlist = [];
        $newcartlist = [];
//        dd($cartlist);

        $field = 'id,name,pic,pics,sales,market_price,sell_price,sellpoint,fuwupoint,price_type,status,createtime,perlimitdan';
        $total = 0;
        foreach($cartlist as $k => $gwc){
            $product = Db::name('shop_product')->field($field)->where('aid',$this->aid)->where('status','<>',0)->where('id',$gwc['proid'])->find();
            if(!$product){
                Db::name('shop_cart')->where('aid',$this->aid)->where('proid',$gwc['proid'])->delete();continue;
            }else{
                $product['pics'] = $product['pics'] ? explode(',',$product['pics']) : [];
            }
            $guige = Db::name('shop_guige')->field('id,name,pic,market_price,sell_price,stock')->where('id',$gwc['ggid'])->find();
            if(!$guige){
                Db::name('shop_cart')->where('aid',$this->aid)->where('ggid',$gwc['ggid'])->delete();continue;
            }
//                if($product['lvprice']==1){
//                    $guige = $this->formatguige($guige, $product['bid']);
//                }
            if($product['perlimitdan'] > 0 && $gwc['num'] > $product['perlimitdan']){
                $gwc['num'] = $product['perlimitdan'];
                Db::name('shop_cart')->where('aid',$this->aid)->where('id',$gwc['id'])->update(['num'=>$gwc['num']]);
            }
            $newcartlist[] = [
                'id' => $gwc['id'],
                'is_selected' => 1,
                'product' => $product,
                'guige' => $guige,
                'num' => $gwc['num'],
                'wxapp_url'=>'/pages/shop/product?id='.$product['id']
            ];
            $total+=$gwc['num'];
        }

        $rdata = [];
        $rdata['status']=1;
        $rdata['datalist'] = $newcartlist;
        $rdata['total'] = $total;
        die(json_encode($rdata));
    }

    public function getCouponList()
    {
        $page = input('post.page',1);
        $pagesize = input('post.pagesize',10);
        if($pagesize > 100){
            die(json_encode(['status'=>0,'msg'=>'参数错误']));
        }
        $mid = input('post.member_id');

        $where=[];
        $where[] = ['aid','=',$this->aid];
        $where[] = ['bid','=',0];
        $where[] = ['tolist','=',1];
        $where[] = ['starttime','<=',date('Y-m-d H:i:s')];
        $where[] = ['endtime','>=',date('Y-m-d H:i:s')];
//        $where = "aid=".$this->aid." and bid=0 and tolist=1 and starttime<='".date('Y-m-d H:i:s')."' and endtime>='".date('Y-m-d H:i:s')."'";
        $where[] = ['type','in',[1,2,3,4]];//1代金券2礼品券3计次券4运费抵扣
        if(input('param.keyword')){
            $where[] = ['name','like','%'.input('param.keyword').'%'];
        }
        $total = Db::name('coupon')->where($where)->count();
        $datalist = Db::name('coupon')->field('id,aid,bid,type,name,starttime,endtime,yxqtype,yxqdate,yxqtime,money,minprice,price,score,limit_count,limit_perday,stock,perlimit,usetips,createtime,isgive,fwtype')
            ->where($where)->order('sort desc,id desc')->page($page,$pagesize)->select()->toArray();

        if(!$datalist) $datalist = [];
        foreach($datalist as $k=>$v){
            $datalist[$k]['getnum'] = 0;
            if($mid){
                $getnum = Db::name('coupon_record')->where('aid',$this->aid)->where('mid',$mid)->where('couponid',$v['id'])->count();
                $datalist[$k]['getnum'] = $getnum;
            }
            $datalist[$k]['starttime'] = date('Y-m-d H:i',strtotime($v['starttime']));
            $datalist[$k]['endtime'] = date('Y-m-d H:i',strtotime($v['endtime']));
            if($v['yxqtype'] == 1){
                $yxqtime = explode(' ~ ',$v['yxqtime']);
                $datalist[$k]['yxqdate'] = date('Y-m-d',strtotime($yxqtime[1]));
            }elseif($v['yxqtype'] == 2){
                //领取后x天有效
                $datalist[$k]['yxqdate'] = date('Y-m-d',time() + 86400 * $v['yxqdate']);
            }elseif($v['yxqtype'] == 3){
                //次日起计算有效期
                $datalist[$k]['yxqdate'] = date('Y-m-d',strtotime(date('Y-m-d')) + 86400 * ($v['yxqdate'] + 1) - 1);
            }
            if($v['bid'] > 0){
                $binfo = Db::name('business')->where('aid',$this->aid)->where('id',$v['bid'])->find();
                $datalist[$k]['bname'] = $binfo['name'];
            }
            $datalist[$k]['createtime_format'] = date('Y-m-d H:i',$v['createtime']);
            $datalist[$k]['wxapp_url'] = '/pages/coupon/coupondetail?id='.$v['id'];
        }

        $rdata = [];
        $rdata['status']=1;
        $rdata['datalist'] = $datalist;
        $rdata['page'] = $page;
        $rdata['pagesize'] = $pagesize;
        $rdata['total'] = $total;
        die(json_encode($rdata));
    }

    //修改积分
    public function updateScore(){
        $mid = input('post.member_id');
        $remark = input('post.remark');
        $num = input('post.num/d');//正值增加，负值减少
        $change_type = input('post.change_type');//1增加，2减少
        if($mid < 0 || empty($remark)){
            die(json_encode(['status'=>0,'msg'=>'参数错误']));
        }
        //查询会员是否存在
        $member = Db::name('member')
            ->where('aid',$this->aid)
            ->where('id',$mid)
            ->field('id,aid,score')
            ->find();
        if($member){
            $remark = strip_tags((string)$remark);
            if($num < 0 && $num + $member['score'] < 0){
                die(json_encode(['status'=>0,'msg'=>'用户积分不足']));
            }
            $add = \app\commons\Member::addscore($member['aid'],$member['id'],$num,$remark,'huidong');
            if($add && $add['status'] ==1){
                die(json_encode(['status'=>1,'msg'=>'修改成功','score'=>$member['score']+$num]));
            }else{
                die(json_encode(['status'=>0,'msg'=>'fail']));
            }
        }else{
            die(json_encode(['status'=>0,'msg'=>'用户不存在']));
        }
    }

    /* 1.将数组内非空参数值的参数按照参数名从小到大排序（ASCII码字典序）
     * 2.然后使URL键值对的格式（即key1=value1&key2=value2…）拼接成字符串
     */
    private function sign($header){
        $params = input('post.');
        //ksort()对数组按照键名进行升序排序
        ksort($params);
        //reset()内部指针指向数组中的第一个元素
        reset($params);
        $paramsStr = '';//初始化
        if($params){
            foreach ($params AS $key => $val) { //遍历POST参数
                if ($val == ''||$key == 'sign'||$key == 'param') continue; //跳过这些不签名
                if ($paramsStr) $paramsStr .= '&'; //第一个字符串签名不加& 其他加&连接起来参数
                $paramsStr .= $key.'='.$val; //拼接为url参数形式
            }
        }

        $md_data = $paramsStr."&Aid=".$this->aid."&AppSecret=".$this->app['appsecret']."&Version=".$header['version']."&Timestamp=".$header['timestamp'];
        $sign = md5($md_data);
        return $sign;
    }
}