<?php


// +----------------------------------------------------------------------
// | 礼品卡
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class Lipin extends Common
{	
	public function initialize(){
		parent::initialize();
		if(bid > 0) showmsg('无操作权限');
	}
	//活动列表
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
			if(input('param.name')) $where[] = ['name','like','%'.input('param.name').'%'];
            if(input('param.cid')) $where[] = ['cid','=',input('param.cid')];
            $count = 0 + Db::name('lipin')->where($where)->count();
			$data = Db::name('lipin')->where($where)->page($page,$limit)->order($order)->select()->toArray();
            $clist = Db::name('lipin_category')->where('aid',aid)->where('bid',bid)->column('name','id');
			foreach($data as $k=>$v){
				$data[$k]['totalcount'] = Db::name('lipin_codelist')->where('aid',aid)->where('hid',$v['id'])->count();
				$data[$k]['usedcount'] = Db::name('lipin_codelist')->where(['aid'=>aid,'hid'=>$v['id'],'status'=>1])->count();
                $data[$k]['cname'] = $clist[$v['cid']];
                }
			
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
        //分类
        $clist = Db::name('lipin_category')->field('id,name')->where('aid',aid)->where('bid',bid)->where('status',1)->where('pid',0)->order('sort desc,id')->select()->toArray();
//        foreach($clist as $k=>$v){
//            $clist[$k]['child'] = Db::name('lipin_category')->field('id,name')->where('aid',aid)->where('bid',bid)->where('status',1)->where('pid',$v['id'])->order('sort desc,id')->select()->toArray();
//        }
        View::assign('clist',$clist);

        if($this->auth_data == 'all' || in_array('Lipin/makecode',$this->auth_data)){
            $auth['makecode'] = true;
        }
        if($this->auth_data == 'all' || in_array('Lipin/importexcel',$this->auth_data)){
            $auth['importexcel'] = true;
        }
        View::assign('auth',$auth);
		return View::fetch();
	}
	//编辑
	public function edit(){
		if(input('param.id')){
			$info = Db::name('lipin')->where('aid',aid)->where('id',input('param.id/d'))->find();
			if($info['type']==1){
				$prodata = explode('-',$info['prodata']);
				$prodatalist = [];
				foreach($prodata as $k=>$v){
					$thisv = explode(',',$v);
					$product = Db::name('shop_product')->where('id',$thisv[0])->find();
					$guige = Db::name('shop_guige')->where('id',$thisv[1])->find();
					$proinfo = [];
					$proinfo['name'] = $product['name'];
					$proinfo['pic'] = $product['pic'];
					$proinfo['id'] = $product['id'];
					$proinfo['ggname'] = $guige['name'];
					$proinfo['ggid'] = $guige['id'];
					$proinfo['sell_price'] = $guige['sell_price'];
					$proinfo['buynum'] = $thisv[2];
					$prodatalist[] = $proinfo;
				}
			}
			if($info['type']==3){
				$coupon_ids = explode(',', $info['coupon_ids']);
				$couponList = Db::name('coupon')->where('aid',aid)->where('bid', 0)->whereIn('id', $coupon_ids)->select()->toArray();
				View::assign('couponList',$couponList);
			}
			if($info['type']==4){
				$prodata = explode('-',$info['prodata4']);
				$scoreshop_prodatalist = [];
				foreach($prodata as $k=>$v){
					$thisv = explode(',',$v);
					$product = Db::name('scoreshop_product')->where('id',$thisv[0])->find();
					if($thisv[1]){
						$guige = Db::name('scoreshop_guige')->where('id',$thisv[1])->find();
					}else{
						$guige = ['id'=>'0','name'=>'','money_price'=>$product['money_price'],'score_price'=>$product['score_price']];
					}
					$proinfo = [];
					$proinfo['name'] = $product['name'];
					$proinfo['pic'] = $product['pic'];
					$proinfo['id'] = $product['id'];
					$proinfo['ggname'] = $guige['name'];
					$proinfo['ggid'] = $guige['id'];
					$proinfo['money_price'] = $guige['money_price'];
					$proinfo['score_price'] = $guige['score_price'];
					$proinfo['buynum'] = $thisv[2];
					$scoreshop_prodatalist[] = $proinfo;
				}
			}
			}else{
			$info = array(
				'id'=>'',
				'name'=>'',
				'starttime'=>time(),
				'endtime'=>time()+30*86400,
				'status'=>1,
				'type'=>0,
                'num_type'=>0
			);
		}
		if(!$prodatalist) $prodatalist = [];
		if(!$scoreshop_prodatalist) $scoreshop_prodatalist = [];
		View::assign('info',$info);
		View::assign('prodatalist',$prodatalist);
		View::assign('scoreshop_prodatalist',$scoreshop_prodatalist);
        //分类
        $clist = Db::name('lipin_category')->field('id,name')->where('aid',aid)->where('bid',bid)->where('status',1)->where('pid',0)->order('sort desc,id')->select()->toArray();
//        foreach($clist as $k=>$v){
//            $clist[$k]['child'] = Db::name('lipin_category')->field('id,name')->where('aid',aid)->where('bid',bid)->where('status',1)->where('pid',$v['id'])->order('sort desc,id')->select()->toArray();
//        }
        View::assign('clist',$clist);
		return View::fetch();
	}
	//保存
	public function save(){
		$info = input('post.info/a');
		$info['starttime'] = strtotime($info['starttime']);
		$info['endtime'] = strtotime($info['endtime']);
		if($info['id']){
			Db::name('lipin')->where('aid',aid)->where('id',$info['id'])->update($info);
			\app\commons\System::plog('编辑礼品卡兑换'.$info['id']);
		}else{
			$info['aid'] = aid;
			$info['createtime'] = time();
			$id = Db::name('lipin')->insertGetId($info);
			\app\commons\System::plog('添加礼品卡兑换'.$id);
		}
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}
	//删除
	public function del(){
		$ids = input('post.ids/a');
		Db::name('lipin')->where('aid',aid)->where('id','in',$ids)->delete();
		Db::name('lipin_codelist')->where('aid',aid)->where('hid','in',$ids)->delete();
		\app\commons\System::plog('删除礼品卡兑换'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	//兑换码
	public function codelist(){

        if(!input('param.hid')){
            $where = array();
            $where[] = ['aid','=',aid];
            $lipin = Db::name('lipin')->where($where)->order('id','desc')->column('name','id');
            View::assign('lipin',$lipin);
        }
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
				$order = 'id desc';
			}
			$where = [];
			$where[] = ['aid','=',aid];
			if(input('param.hid')){
				$where[] = ['hid','=',input('param.hid/d')];
			}
            if(input('param.cardno') && empty(input('param.cardno2'))) {
                $where[] = ['cardno','=',input('param.cardno')];
            }elseif(input('param.cardno2') && empty(input('param.cardno'))) {
                $where[] = ['cardno','=',input('param.cardno2')];
            }elseif(input('param.cardno') && input('param.cardno2')){
                $where[] = ['cardno','between',[input('param.cardno'),input('param.cardno2')]];
            }
            if(input('param.mdid')) $where[] = ['sale_mdid|hexiao_mdid','=',input('param.mdid')];
			if(input('param.code')) $where[] = ['code','=',input('param.code')];
            if(input('param.pid')) $where[] = ['pid','=',input('param.pid')];
            if(input('param.mid')) $where[] = ['mid','=',input('param.mid')];
			if(input('param.nickname')) $where[] = ['nickname','like','%'.input('param.nickname').'%'];
			if(input('?param.status') && input('param.status')!=='') $where[] = ['status','=',input('param.status')];
			if(input('param.ctime') ){
				$ctime = explode(' ~ ',input('param.ctime'));
				$where[] = ['createtime','>=',strtotime($ctime[0])];
				$where[] = ['createtime','<',strtotime($ctime[1]) + 86400];
			}
            $count = 0 + Db::name('lipin_codelist')->where($where)->count();
			$data = Db::name('lipin_codelist')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			foreach($data as $k=>$v){
				$data[$k]['name'] = Db::name('lipin')->where('id',$v['hid'])->value('name');
                if($v['pid']){
                    $data[$k]['parent'] = Db::name('member')->field('id,aid,nickname,headimg')->where('aid',aid)->where('id',$v['pid'])->find();
                }
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
        if($this->auth_data == 'all' || in_array('Lipin/jihuo',$this->auth_data)){
            $auth['jihuo'] = true;
        }
        if($this->auth_data == 'all' || in_array('Lipin/makecode',$this->auth_data)){
            $auth['makecode'] = true;
        }
        if($this->auth_data == 'all' || in_array('Lipin/importexcel',$this->auth_data)){
            $auth['importexcel'] = true;
        }
        if($this->auth_data == 'all' || in_array('Lipin/codelistexcel',$this->auth_data)){
            $auth['codelistexcel'] = true;
        }
        if($this->auth_data == 'all' || in_array('Lipin/tiaoka',$this->auth_data)){
            $auth['tiaoka'] = true;
        }
        if($this->auth_data == 'all' || in_array('Lipin/setst',$this->auth_data)){
            $auth['setst'] = true;
        }
        if($this->auth_data == 'all' || in_array('Lipin/codelistdel',$this->auth_data)){
            $auth['codelistdel'] = true;
        }
        View::assign('auth',$auth);
		return View::fetch();
	}
    public function jihuo()
    {
        }
    public function tiaoka()
    {
        }
	//兑换码导出
	public function codelistexcel(){
        $page = input('param.page');
        $limit = input('param.limit');
		$where = [];
		$where[] = ['aid','=',aid];
		if(input('param.hid')){
			$where[] = ['hid','=',input('param.hid/d')];
		}
		$list = Db::name('lipin_codelist')->where($where)->page($page,$limit)->select()->toArray();
        $count = Db::name('lipin_codelist')->where($where)->count();
		
		$title = array();
		$title[] = '序号';
		$title[] = '活动ID';
		//$title[] = '活动名称';
        $title[] = '兑换码';
		$title[] = '图形码链接';
		$title[] = '使用人';
		$title[] = '兑换时间';
		$title[] = '状态';
		$title[] = '备注';
		$data = array();
		foreach($list as $v){
			$tdata = array();
			$tdata[] = $v['id'];
			$tdata[] = $v['hid'];
			//$tdata[] = $v['name'];
            $tdata[] = $v['code'];
			$tdata[] = $v['qr'];
			$tdata[] = $v['nickname'];
			$tdata[] = date('Y-m-d H:i:s',$v['createtime']);
			$status = '';
			if($v['status']==1){
				$status = '已使用';
			}elseif($v['status']==0){
				$status = '未使用';
				}elseif($v['status']==2){
				$status = '已售';
			}
			$tdata[] = $status;
			$tdata[] = $v['remark'];
			$data[] = $tdata;
		}
        return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data,'title'=>$title]);
		$this->export_excel($title,$data);
	}
	//激活
	public function jihuo2(){
		$ids = input('post.ids/a');
		$st = input('post.st/d');
		Db::name('lipin_codelist')->where('aid',aid)->where('id','in',$ids)->update(['jhstatus'=>$st]);
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//改状态
	public function setst(){
		$ids = input('post.ids/a');
		$st = input('post.st/d');
		Db::name('lipin_codelist')->where('aid',aid)->where('id','in',$ids)->update(['status'=>$st]);
		\app\commons\System::plog('礼品卡兑换码改状态'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//删除
	public function codelistdel(){
		$ids = input('post.ids/a');
		Db::name('lipin_codelist')->where('aid',aid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('礼品卡兑换码删除'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	
	//导入
	public function importexcel(){
		$hid = input('param.hid');
		set_time_limit(0);
		ini_set('memory_limit',-1);
		$file = input('post.upload_file');
		$exceldata = $this->import_excel($file);

		$insertnum = 0;
		$chongfunum = 0;
		foreach($exceldata as $data){
			$indata = [];
			$indata['aid'] = aid;
			
			if(false){}else{
				$indata['code'] = trim($data[0]);
			}
            $hasinfo = Db::name('lipin_codelist')->where($indata)->find();
			if($hasinfo){
				$chongfunum++;
			}else{
				$indata['hid'] = $hid;
				$indata['createtime'] = time();
				Db::name('lipin_codelist')->insert($indata);
				$insertnum++;
			}
			//dump($indata);
		}
		\app\commons\System::plog('礼品卡兑换码导入'.$hid);
		if($chongfunum > 0){
			return json(['status'=>1,'msg'=>'成功新增'.$insertnum.'条数据，重复'.$chongfunum.'条数据']);
		}else{
			return json(['status'=>1,'msg'=>'成功新增'.$insertnum.'条数据']);
		}
	}
	//生成兑换码
	public function makecode(){
		$hid = input('post.hid');
		$makecount = input('post.makecount/d');
		$codelength = input('post.codelength/d');
		$codetype = input('post.codetype');
		$qrtype = input('post.qrtype');
        $pid = input('post.pid');
		if($makecount < 1 || $makecount > 5000){
			return json(['status'=>0,'msg'=>'每次生成数量须在5000以内']);
		}
		if($codelength < 1 || $codelength > 10){
			return json(['status'=>0,'msg'=>'抽奖码长度须小于10']);
		}
		$successnum = 0;
        for($i=0;$i<$makecount;$i++){
            $data = [];
            $data['aid'] = aid;
            $randstr = make_rand_code($codetype, $codelength);
            $data['code'] = $randstr;
			if($qrtype == 1){ //二维码
				$data['qr'] = createqrcode($randstr);
			}elseif($qrtype == 2){ //条形码
				$data['qr'] = createbarcode($randstr);
			}elseif($qrtype == 3){ //链接二维码
                $path = '/pagesExt/lipin/index?dhcode='.$randstr;
                $data['qr'] = createqrcode(m_url($path));
			}elseif($qrtype == 4){ //小程序码
                $path = 'pagesExt/lipin/index?dhcode='.$randstr;
                $rs = \app\commons\Wechat::getQRCode(aid,'wx',$path);
				if($rs['status'] == 0){
					return json($rs);
				}
				$data['qr'] = $rs['url'];
			}
			$hasinfo = Db::name('lipin_codelist')->where($where)->find();
            if(!$hasinfo){
                $data['hid'] = $hid;
                $data['createtime'] = time();
                Db::name('lipin_codelist')->insert($data);
                $successnum++;
            }
        }
        \app\commons\System::plog('礼品卡兑换码生成'.$hid);
        return json(['status'=>1,'msg'=>'成功生成'.$successnum.'个兑换码']);
	}
	
	//设置
	public function set(){
		if(request()->isAjax()){
			$signset = Db::name('lipin_set')->where('aid',aid)->find();
			$info = input('post.info/a');
			Db::name('lipin_set')->where('aid',aid)->update($info);

			\app\commons\System::plog('礼品卡兑换设置');
			return json(['status'=>1,'msg'=>'操作成功','url'=>true]);
		}
		$info = Db::name('lipin_set')->where('aid',aid)->find();
		if(!$info){
			Db::name('lipin_set')->insert(['aid'=>aid]);
			$info = Db::name('lipin_set')->where('aid',aid)->find();
		}
		View::assign('info',$info);
      
		return View::fetch();
	}
}