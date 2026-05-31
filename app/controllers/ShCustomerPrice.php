<?php
// +----------------------------------------------------------------------
// | custom_file(product_weight) 客户定价
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;
class ShCustomerPrice extends Common
{
    public function index(){
        $cwhere = $bwhere =[];
        $bwhere[] = ['aid','=',aid];
        $cwhere[] = ['aid','=',aid];
        $cwhere[] = ['pid','=',0];
        $bid = bid;
        if(bid>0){
            $cwhere[] = Db::raw("bid={$bid} OR find_in_set({$bid},`ext_bids`)");
            $bwhere[] = ['id','=',bid];
        }
        $keyCustomerArr = [];
        $customerArr = Db::name('sh_customer')->where($cwhere)->select()->toArray();
        $businessArr = Db::name('business')->where($bwhere)->column('name','id');
        foreach ($customerArr as $k=>$v){
            $keyCustomerArr[$v['id']] = $v;
        }
        if(request()->isAjax()){
            $page = input('param.page');
            $limit = input('param.limit');
            $where = array();
            $where[] = ['p.aid','=',aid];
            if(bid>0){
                $where[] = ['p.bid','=',bid];
            }
            if(input('param.customer_id')){
                $where[] = ['p.customer_id','=',input('param.customer_id')];
            }
            if(input('param.name')) $where[] = ['prod.name|g.name','like','%'.trim(input('param.name')).'%'];
            $count = 0 + Db::name('customer_price')->alias('p')
                    ->join('shop_guige g','p.ggid=g.id')
                    ->join('shop_product prod','p.proid=prod.id')
                    ->where($where)
                    ->count('p.id');
            $data = Db::name('customer_price')->alias('p')
                ->join('shop_guige g','p.ggid=g.id')
                ->join('shop_product prod','g.proid=prod.id')
                ->field('p.id,p.price,p.customer_id,p.createtime,p.bid,prod.name pname,g.name ggname,g.weight,g.sell_price')->where($where)->page($page,$limit)->order('p.ggid')->select()->toArray();
            foreach ($data as $k=>$v){
                if($v['weight']){
                    $price = '￥'.$v['price'].'/'.$v['weight'].'g';
                }else{
                    $price = '￥'.$v['price'];
                }
                $bname = '平台';
                if($v['bid']>0){
                    $bname = isset($businessArr[$v['bid']])?$businessArr[$v['bid']]:$v['bid'];
                }
                $customer = [];
                if(isset($keyCustomerArr[$v['customer_id']])){
                    $customer = $keyCustomerArr[$v['customer_id']];
                }
                $data[$k]['bname'] = $bname;
                $data[$k]['show_name'] = $v['pname'].' '.$v['ggname'];
                $data[$k]['show_price'] = $price;
                $data[$k]['customer_name'] = $customer['name']??'';
                $data[$k]['customer_number'] = $customer['number']??'';
            }
            return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
        }
        $where = input('param.',['time'=>time()]);
        View::assign('customerlist',$customerArr);
        View::assign('wheredata',json_encode($where));
        return View::fetch();
    }
    public function edit(){
        $id = input('param.id/d');
        if(request()->isPost()){
            $price = input('param.price');
            $weight = input('param.weight');
            $where = array();
            $where[] = ['aid','=',aid];
            if(bid>0){
                $where[] = ['bid','=',bid];
            }
            $where[] = ['id','=',$id];
            Db::name('customer_price')->where($where)->update(['price'=>$price,'weight'=>$weight]);
            return json(['status'=>1,'msg'=>'修改成功']);
        }else{
            $where = array();
            $where[] = ['p.aid','=',aid];
            if(bid>0){
                $where[] = ['p.bid','=',bid];
            }
            $where[] = ['p.id','=',$id];
            $info = Db::name('customer_price')->alias('p')
                ->join('shop_guige g','p.ggid=g.id')
                ->join('shop_product prod','g.proid=prod.id')
                ->field('p.id,p.price,p.customer_id,p.createtime,p.bid,prod.name pname,g.name ggname,g.weight,g.sell_price')->where($where)->find();
            $customer_name = Db::name('sh_customer')->where('aid',aid)->where('id',$info['customer_id'])->value('name');
            $info['customer_name'] = $customer_name??$info['customer_id'];
            View::assign('info', $info);
            return View::fetch();
        }
    }
    //客户定价【一规格一定价】
    public function add(){
        $proid = input('param.proid', 0);
        if(empty($proid)){
            showmsg('请先选择商品');
        }
        $where = array();
        $where[] = ['aid', '=', aid];
        $where[] = ['id', '=', $proid];
        $where[] = ['product_type', '=', 2];//称重商品
        if (bid > 0) {
            $where[] = ['bid', '=', bid];
        }
        $product = Db::name('shop_product')->where($where)->find();
        $gglist = Db::name('shop_guige')->where('aid', aid)->where('proid', $proid)->field("id,name,sell_price,weight,'{$product['name']}' as pname")->select()->toArray();
        if(request()->isPost()){
            $priceGg = [];
            foreach ($gglist as $k=>$v){
                $priceGg[$v['id']] = $v['sell_price'];
            }
            $weight = input('post.weight/a',[]);
            $prices = input('post.prices/a',[]);
            $ggids = input('post.ggids/a',[]);
            $ctmids = input('post.ctmids/a',[]);
            if($prices){
                foreach ($prices as $key=>$price){
                    if(empty($ctmids[$key]) || empty($ggids[$key]) || ($price-$priceGg[$ggids[$key]])==0){
                        continue;
                    }
                    $data = [
                        'price'=>$price,
                        'ggid'=>$ggids[$key],
                        'proid'=>$proid,
                        'weight'=>$weight[$key],
                        'customer_id'=>$ctmids[$key],
                        'createtime'=>time(),
                        'aid'=>aid,
                        'bid'=>bid
                    ];
                    $info = Db::name('customer_price')->where('aid',aid)->where('customer_id',$ctmids[$key])->where('ggid',$ggids[$key])->find();
                    if($info){
                        Db::name('customer_price')->where('id',$info['id'])->update($data);
                    }else{
                        Db::name('customer_price')->insert($data);
                    }
                }
            }
            return json(['status'=>1,'msg'=>'操作成功']);

        }else {
            if (empty($product)) {
                showmsg('数据有误');
            }
            View::assign('product', $product);
            View::assign('gglistArr', $gglist);
            View::assign('gglist', json_encode($gglist));
            View::assign('isopen', input('param.isopen',0));
            return View::fetch();
        }
    }
    public function excel(){
        set_time_limit(0);
        ini_set('memory_limit', '2000M');
        $page = input('param.page')?:1;
        $limit = input('param.limit')?:10;
        $title = array();
        $title[] = '商品标识（导入时不可修改）';
        $title[] = '客户ID';
        $title[] = '客户姓名';
        $title[] = '客户编号';
        $title[] = '销售商户';
        $title[] = '商品';
        $title[] = '价格(元)';
        $title[] = '重量(g)';
        $cwhere = $bwhere = [];
        $bwhere[] = ['aid','=',aid];
        $cwhere[] = ['aid','=',aid];
        $cwhere[] = ['pid','=',0];
        $bid = bid;
        if(bid>0){
            $bwhere[] = ['id','=',bid];
            $cwhere[] = Db::raw("bid={$bid} OR find_in_set({$bid},`ext_bids`)");
        }
        $keyCustomerArr = Db::name('sh_customer')->where($cwhere)->column('*','id');
        $businessArr = Db::name('business')->where($bwhere)->column('name','id');
        $where = array();
        $where[] = ['p.aid','=',aid];
        if(bid>0){
            $where[] = ['p.bid','=',bid];
        }
        if(input('param.customer_id')){
            $where[] = ['p.customer_id','=',input('param.customer_id')];
        }
        if(input('param.name')) $where[] = ['prod.name|g.name','like','%'.trim(input('param.name')).'%'];
        $data = Db::name('customer_price')->alias('p')
            ->join('shop_guige g','p.ggid=g.id')
            ->join('shop_product prod','g.proid=prod.id')
            ->field('p.id,p.proid,p.ggid,p.price,p.customer_id,p.bid,p.createtime,prod.name pname,g.name ggname,g.weight,g.sell_price')->where($where)->order('p.ggid')->page($page,$limit)->select()->toArray();
        $count = Db::name('customer_price')->alias('p')
            ->join('shop_guige g','p.ggid=g.id')
            ->join('shop_product prod','g.proid=prod.id')
            ->field('p.id,p.proid,p.ggid,p.price,p.customer_id,p.bid,p.createtime,prod.name pname,g.name ggname,g.weight,g.sell_price')->where($where)->count();
        $list = [];
        foreach ($data as $k=>$v){
            $showname = $v['pname'].' '.$v['ggname'];
            $customer = [];
            if($keyCustomerArr[$v['customer_id']]){
                $customer = $keyCustomerArr[$v['customer_id']];
            }
            $bname = '平台';
            if($v['bid']>0){
                $bname = isset($businessArr[$v['bid']])?$businessArr[$v['bid']]:$v['bid'];
            }
            $tdata = array();
            $tdata[] = $v['proid'].'_'.$v['ggid'].'_'.$v['customer_id'];
            $tdata[] = $customer['id']??'';
            $tdata[] = $customer['name']??'';
            $tdata[] = $customer['number']??'';
            $tdata[] = $bname;
            $tdata[] = $showname;
            $tdata[] = $v['price'];
            $tdata[] = $v['weight'];
            $list[] = $tdata;
        }
        \app\commons\System::plog('导出客户定价');
        return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data,'title'=>$title]);
        $this->export_excel($title,$list);
    }
    //删除
    public function del(){
        $ids = input('post.ids/a');
        if(!$ids) $ids = array(input('post.id/d'));
        $where = [];
        $where[] = ['aid','=',aid];
        $where[] = ['id','in',$ids];
        if(bid>0){
            $where[] = ['bid','=',bid];
        }
        $res  = Db::name('customer_price')->where($where)->delete();
        if($res){
            \app\commons\System::plog('删除客户定价'.implode(',',$ids));
            return json(['status'=>1,'msg'=>'删除成功']);
        }else{
            return json(['status'=>0,'msg'=>'删除失败']);
        }
    }

    public function getCustomerPrice(){
        $ggid = input('param.ggid/d');
        $customer_id = input('param.customer_id/d');
        $price = Db::name('customer_price')->where('ggid',$ggid)->where('customer_id',$customer_id)->value('price');
        if(empty($price)){
            $price=0;
        }
        return json(['msg'=>'ok','price'=>number_format($price,2)]);
    }

    //定价导入
    public function importexcel(){
        set_time_limit(0);
        ini_set('memory_limit',-1);
        $file = input('post.upload_file');
        $exceldata = $this->import_excel($file);
        $insertnum = 0;
        $chongfunum = 0;
        /*
         * data[0] 识别码
         * ....
         * data[6] 价格
         */
        foreach($exceldata as $data){
            $code = $data[0];//识别码
            $price = $data[6];//价格
            if(empty($code) || empty($price)){
                continue;
            }
            $codeArr = explode('_',$code);
            if(count($codeArr)!=3){
                continue;
            }
            $proid = $codeArr[0];
            $ggid = $codeArr[1];
            $customer_id = $codeArr[2];
            if(empty($ggid) || empty($customer_id)){
                continue;
            }
            $where = [];
            $where[] = ['aid','=',aid];
            $where[] = ['ggid','=',$ggid];
            $where[] = ['customer_id','=',$customer_id];
            if(bid>0){
                $where[] = ['bid','=',bid];
            }
            //有效数据执行修改
            $res = Db::name('customer_price')->where($where)->update(['price'=>$price,'createtime'=>time()]);
            $insertnum++;
        }
        return json(['status'=>1,'msg'=>'成功修改'.$insertnum.'条数据']);
    }
}
