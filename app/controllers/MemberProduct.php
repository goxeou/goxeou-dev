<?php
// +----------------------------------------------------------------------
// | 一客一价 custom_file(member_product_price)
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;
use app\models\Member as m;
class MemberProduct extends Common
{
    public function initialize(){
		parent::initialize();
		if(bid > 0) showmsg('无访问权限');
	}
	//会员列表
    public function index(){
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
			if(input('param.mid')) {
			    $where[] = ['id','=',input('param.mid')];
            }else{
                if(input('param.card_id')) {
                    $mids = Db::name('membercard_record')->where('aid',aid)->where('card_id',input('param.card_id'))->column('mid');
                    $where[] = ['id','in',$mids];
                }
                
            }

			if(input('param.pid')) $where[] = ['pid','=',input('param.pid')];
			if(input('param.nickname')) $where[] = ['nickname|tel|realname|card_code','like','%'.input('param.nickname').'%'];
           
            if(input('param.realname')) $where[] = ['realname','like','%'.input('param.realname').'%'];
			//其他分组等级的筛选

			if(input('param.ctime') ){
				$ctime = explode(' ~ ',input('param.ctime'));
				$where[] = ['createtime','>=',strtotime($ctime[0])];
				$where[] = ['createtime','<',strtotime($ctime[1]) + 86400];
			}
            if(input('param.goodsnum')){
                if(input('param.goodsnum') ==1){
                    $mids = Db::name('member_product')->group('mid')->column('mid');
                    $where[] = ['id','in',$mids];
                }
                if(input('param.goodsnum') ==2){
                    $mids = Db::name('member_product')->group('mid')->column('mid');
                    $where[] = ['id','not in',$mids];
                }
            }
			$count = 0 + Db::name('member')->where($where)->count();
			$data = Db::name('member')
                ->field('*,(select count(id) from ddwx_member_product where mid=ddwx_member.id) goodsnum')
                ->where($where)->page($page,$limit)->order($order)->select()->toArray();
            $moeny_weishu = 2;
         
			foreach($data as $k=>$v){
				if($v['pid']){
					$parent = Db::name('member')->where('aid',aid)->where('id',$v['pid'])->find();
				}else{
					$parent = array();
				}
                if($v['pid_origin']){
                    $parent_origin = Db::name('member')->where('aid',aid)->where('id',$v['pid_origin'])->find();
                }else{
                    $parent_origin = array();
                }

				$data[$k]['parent'] = $parent;
                $data[$k]['parent_origin'] = $parent_origin;
                $data[$k]['money'] = \app\commons\Member::getmoney($v);
                $data[$k]['score'] = \app\commons\Member::getscore($v);
				$data[$k]['commission'] = dd_money_format($v['commission'],$moeny_weishu);
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
    }
    //商品列表
    public function product(){
        if(request()->isAjax()){
            $page = input('param.page');
            $limit = input('param.limit');
            $id = input('param.id');
            if(input('param.field') && input('param.order')){
                $order = input('param.field').' '.input('param.order');
            }else{
                $order = 'id desc';
            }
            $where = [];
            $where[] = ['aid','=',aid];
            $where[] = ['mid','=',$id];
            if(input('param.name')) $where[] = ['proname','like','%'.input('param.name').'%'];
            $count = 0 + Db::name('member_product')->where($where)->count();
            $data = Db::name('member_product')->where($where)->page($page,$limit)->order($order)->select()->toArray();
            foreach($data as $key=>$val){
                //查询商品
                $product = Db::name('shop_product')->where('id',$val['proid'])->field('id,name,pic')->find();
                $guige = Db::name('shop_guige')->where('proid',$val['proid'])->where('id',$val['ggid'])->find();
              
                $pic =$guige['pic']==''?$product['pic']:$guige['pic'];
                
                $goodshtml = '<div style="font-size:12px;float:left;clear:both;margin:1px 0">'.
                    '<div class="table-imgbox"><img lay-src="'.$pic.'" src="'.PRE_URL.'/static/admin/layui/css/modules/layer/default/loading-2.gif"></div>'.
                    '<div style="float: left;width:160px;margin-left: 10px;white-space:normal;line-height:16px;">'.
                    '<div style="width:100%;min-height:25px;max-height:32px;overflow:hidden">'.$val['proname'].'</div>'.
                    '<div style="padding-top:0px;color:#f60"><span style="color:#888">'.$guige['name'].'</span></div>';
                $goodshtml.='<div style="padding-top:0px;color:#f60;">￥'.$val['sell_price'].'</div>';
                $goodshtml.='</div>';
                $goodshtml.='</div>';
               $data[$key]['goodsdata'] = $goodshtml; 
               $data[$key]['createtime'] = date('Y-m-d H:i:s',$val['createtime']);
            }
            return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
        }
        $id = input('param.id');
        View::assign('id',$id);
        return View::fetch();
    }
    //保存专享商品
    public function saveproduct(){
        $mid = input('param.mid');
        $ggids = input('param.ggid');
        $proid = input('param.proid');
        if(!$ggids){
            return json(['status'=>0,'msg'=>'请选择数据']);
        }
        $insertall = [];
        foreach ($ggids as $key=>$ggid){
            $member_product = Db::name('member_product')->where('aid',aid)->where('mid',$mid)->where('proid',$proid)->where('ggid',$ggid)->find();
            if(!$member_product){
                $shop_guige = Db::name('shop_guige')->where('id',$ggid)->find();
                $product = Db::name('shop_product')->where('id',$proid)->find();
                $insertall[] = [
                    'aid' => aid,
                    'mid' =>$mid,
                    'proid' =>$proid,
                    'proname' =>$product['name'],
                    'ggid' =>$ggid,
                    'ggname' =>$shop_guige['name'],
                    'pic' => $shop_guige['pic']?$shop_guige['pic']:$product['pic'],
                    'market_price' => $shop_guige['market_price'],
                    'cost_price' => $shop_guige['cost_price'],
                    'sell_price' => $shop_guige['sell_price'],
                    'createtime' => time()
                ];
            }
        }
        Db::name('member_product')->insertAll($insertall);
        return json(['status'=>1,'msg'=>'添加成功']);
    }
    //删除
    public function del(){
        $ids = input('post.ids/a');
        if(bid == 0){
            Db::name('member_product')->where('aid',aid)->where('id','in',$ids)->delete();
        }else{
            Db::name('member_product')->where('aid',aid)->where('bid',bid)->where('id','in',$ids)->delete();
        }
        return json(['status'=>1,'msg'=>'删除成功']);
    }
    
    public function priceChange(){
        $changeid = input('param.changeid');
        $sell_price = input('param.sell_price');
        $data=  Db::name('member_product')->where('aid',aid)->where('id',$changeid)->find();
        if(!$data){
            return json(['status'=>0,'msg'=>'不存在该数据']);
        }
        Db::name('member_product')->where('aid',aid)->where('id',$changeid)->update(['sell_price' => $sell_price]);
        //增加记录
        $log = [
            'aid' =>aid,
            'proid' => $changeid,
            'before' =>$data['sell_price'],
            'after' => $sell_price,
            'createtime' => time()
        ];
        Db::name('member_product_pricelog')->insert($log);
        return json(['status'=>1,'msg'=>'修改成功']);
    }
    public function scoreChange(){
        $changeid = input('param.scoreid');
        $givescore = input('param.givescore');
        $data=  Db::name('member_product')->where('aid',aid)->where('id',$changeid)->find();
        if(!$data){
            return json(['status'=>0,'msg'=>'不存在该数据']);
        }
        Db::name('member_product')->where('aid',aid)->where('id',$changeid)->update(['givescore' => $givescore]);
        return json(['status'=>1,'msg'=>'修改成功']);
    }

    //调价记录
    public function changepricelog(){
        if(request()->isAjax()){
            $page = input('param.page');
            $limit = input('param.limit');
            $id = input('param.id');
            if(input('param.field') && input('param.order')){
                $order = input('param.field').' '.input('param.order');
            }else{
                $order = 'id desc';
            }
            $where = [];
            $where[] = ['aid','=',aid];
            $where[] = ['proid','=',$id];
            $count = 0 + Db::name('member_product_pricelog')->where($where)->count();
            $data = Db::name('member_product_pricelog')->where($where)->page($page,$limit)->order($order)->select()->toArray();
            foreach($data as $k=>$v){
                $data[$k]['createtime'] = date('Y-m-d H:i:s',$v['createtime']);
            }
            return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
        }
        return View::fetch();
    }
    //购买记录
    public function buylog(){
        if(request()->isAjax()){
            $page = input('param.page');
            $limit = input('param.limit');
            $id = input('param.id');
            if(input('param.field') && input('param.order')){
                $order = input('param.field').' '.input('param.order');
            }else{
                $order = 'id desc';
            }
            $member_product = Db::name('member_product')->where('aid',aid)->where('id',$id)->find();
            $where = [];
            $where[] = ['aid','=',aid];
            $where[] = ['proid','=',$member_product['proid']];
            $where[] = ['ggid','=',$member_product['ggid']];
            $where[] = ['mid','=',$member_product['mid']];
            $count = 0 + Db::name('member_product_buylog')->where($where)->count();
            $data = Db::name('member_product_buylog')->where($where)->page($page,$limit)->order($order)->select()->toArray();
            foreach($data as $k=>$v){
                $member = Db::name('member')->where('id',$v['mid'])->find();
                if($member){
                    $data[$k]['nickname'] = $member['nickname'];
                    $data[$k]['headimg'] = $member['headimg'];
                }else{
                    $data[$k]['nickname'] = '';
                    $data[$k]['headimg'] = '';
                }
                $product = Db::name('shop_product')->where('id',$v['proid'])->field('id,name,pic')->find();
                $guige = Db::name('shop_guige')->where('proid',$v['proid'])->where('id',$v['ggid'])->find();
                $pic =$guige['pic']==''?$product['pic']:$guige['pic'];
                
                $goodshtml = '<div style="font-size:12px;float:left;clear:both;margin:1px 0">'.
                    '<div class="table-imgbox"><img src="'.$pic.'" ></div>'.
                    '<div style="float: left;width:160px;margin-left: 10px;white-space:normal;line-height:16px;">'.
                    '<div style="width:100%;min-height:25px;max-height:32px;overflow:hidden">'.$product['name'].'</div>'.
                    '<div style="padding-top:0px;color:#f60"><span style="color:#888">'.$guige['name'].'</span></div>';
                $goodshtml.='<div style="padding-top:0px;color:#f60;">￥'.$v['sell_price'].' x '.$v['num'].'</div>';
                $goodshtml.='</div>';
                $goodshtml.='</div>';
                $data[$k]['goodsdata'] = $goodshtml;
                //状态
                if($v['type']=='shop'|| $v['type']=='admin' ){
                    $status_arr = ['0' => '未支付','1'=>'已支付','2' => '已发货','3' =>'已收货','4'=>'已关闭'];
                    $status = Db::name('shop_order')->where('id',$v['orderid'])->value('status');
                    $data[$k]['status']= $status_arr[$status]; 
                }else{
                    $status_arr = ['0' => '结算中','1'=>'已完成','2' => '挂单中','10'=>'已退款'];
                    $status = Db::name('cashier_order')->where('id',$v['orderid'])->value('status');
                    $data[$k]['status']= $status_arr[$status];
                }
                //来源
                $type_arr = ['cashier' =>'收银台下单','shop' => '商城下单','admin'=>'后台录入'];
                $data[$k]['type_name'] =$type_arr[$v['type']]; 
                $data[$k]['createtime'] = date('Y-m-d H:i:s',$v['createtime']);
            }
            return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
        }
        return View::fetch();
    }
	//导出
	public function excel(){
		$levelList = Db::name('member_level')->where('aid',aid)->select()->toArray();
		$levelArr = array();
		foreach($levelList as $v){
			$levelArr[$v['id']] = $v['name'];
		}

		if(input('param.field') && input('param.order')){
			$order = input('param.field').' '.input('param.order');
		}else{
			$order = 'id desc';
		}
		$where = [];
		$where[] = ['aid','=',aid];
		if(input('param.mid')) $where[] = ['id','=',input('param.mid')];
		if(input('param.pid')) $where[] = ['pid','=',input('param.pid')];
		if(input('param.isgetcard')){
			if(input('param.isgetcard') == 1){
				$where[] = ['','exp',Db::raw('card_code is not null')];
			}else{
				$where[] = ['','exp',Db::raw('card_code is null')];
			}
		}
		if(input('param.nickname')) $where[] = ['nickname|tel|realname','like','%'.input('param.nickname').'%'];
		if(input('param.realname')) $where[] = ['realname','like','%'.input('param.realname').'%'];
		if(input('param.levelid')) $where[] = ['levelid','=',input('param.levelid')];
		if(input('param.ctime') ){
			$ctime = explode(' ~ ',input('param.ctime'));
			$where[] = ['createtime','>=',strtotime($ctime[0])];
			$where[] = ['createtime','<',strtotime($ctime[1]) + 86400];
		}

		if(input('param.fhid')){
			$midlist = Db::name('shop_order_fenhong')->where('aid',aid)->where('id',input('param.fhid'))->value('midlist');
			$where[] = ['id','in',$midlist];
		}

        if(getcustom('plug_tengrui')){
            if(input('?param.tr_is_rzh') && input('param.tr_is_rzh')!==''){
                $where[] = ['tr_is_rzh','=',input('param.tr_is_rzh')];
            }
        }
        if(getcustom('xixie')){
            if(input('?param.mdid') && input('param.mdid')!==''){
                $where[] = ['mdid','=',input('param.mdid')];
            }
        }
		

		if(input('param.fxmid')){
			$fxmid = input('param.fxmid/d');
			if(input('param.deep') == 1){
				$where[] = ['pid','=',$fxmid];
			}elseif(input('param.deep') == 2){
				$where[] = Db::raw("pid in(select id from ".table_name('member')." where pid=".$fxmid.")");
			}elseif(input('param.deep') == 3){
				$where[] = Db::raw("pid in(select id from ".table_name('member')." where pid in(select id from ".table_name('member')." where pid=".$fxmid."))");
			}
		}

		$list = Db::name('member')->where($where)->order($order)->select()->toArray();
        if(getcustom('pay_yuanbao')){
            $title = array('ID','头像','昵称','来源','推荐人','省份','城市','姓名','电话',t('余额'),t('积分'),t('佣金'),t('元宝'),'等级','加入时间','关注状态','公众号openid','小程序openid');
        }else{
            $title = array('ID','头像','昵称','来源','推荐人','省份','城市','姓名','电话',t('余额'),t('积分'),t('佣金'),'等级','加入时间','关注状态','公众号openid','小程序openid');
        }
        if(getcustom('plug_tengrui')){
            $title = array('ID','头像','昵称','来源','省份','城市','姓名','电话',t('余额'),t('积分'),t('佣金'),'等级','加入时间','关注状态','是否认证','小区','公众号openid','小程序openid');
        }
        if(getcustom('xixie')){
            $title = array('ID','头像','昵称','门店','来源','省份','城市','姓名','电话',t('余额'),t('积分'),t('佣金'),'等级','加入时间','关注状态','是否认证','小区','公众号openid','小程序openid');
        }
        $moeny_weishu = 2;
        if(getcustom('fenhong_money_weishu')){
            $moeny_weishu = Db::name('admin_set')->where('aid',aid)->value('fenhong_money_weishu');
        }
		$data = array();
		foreach($list as $k=>$vo){
			if($vo['platform']=='wx'){
				$vo['platform'] = '小程序';
			}elseif($vo['platform']=='mp'){
				$vo['platform'] = '公众号';
			}elseif($vo['platform']=='h5'){
				$vo['platform'] = 'H5';
			}
			if($vo['subscribe']==1){
				$vo['subscribe'] = '已关注';
			}else{
				$vo['subscribe'] = '未关注';
			}
            $vo['money'] = \app\commons\Member::getmoney($vo);
            $vo['score'] = \app\commons\Member::getscore($vo);
			$vo['levelname'] = $levelArr[$vo['levelid']];
			//上级
            $pmember = Db::name('member')->where('id',$vo['pid'])->field('id,nickname')->find();
            if($pmember){
                $vo['pmember'] = $pmember['nickname'].'(ID:'.$pmember['id'].')';
            }else{
                $vo['pmember'] = '暂无';
            }
            $vo['commission'] = dd_money_format($vo['commission'],$moeny_weishu);
            if(getcustom('xixie')) {
                $vo['mendian_infor'] = '无';
                if($vo['mdid']){
                    $mendian = Db::name('mendian')->where('id',$vo['mdid'])->where('aid',aid)->field('id,name')->find();
                    if($mendian){
                        $vo['mendian_infor'] = 'ID:'.$mendian['id']."\n\r"."名称:".$mendian['name'];
                    }
                }
                $data[] = array($vo['id'],$vo['headimg'],$vo['nickname'],$vo['mendian_infor'],$vo['platform'],$vo['province'],$vo['city'],$vo['realname'],$vo['tel'],$vo['money'],$vo['score'],$vo['commission'],$vo['levelname'],date('Y-m-d H:i',$vo['createtime']),$vo['subscribe'],$vo['mpopenid'],$vo['wxopenid']);
            }else if(getcustom('plug_tengrui')){
                if($vo['tr_is_rzh']==1){
                    $vo['tr_is_rzh'] = '已认证';
                }else{
                    $vo['tr_is_rzh'] = '未认证';
                }
                //查询对应的小区
                $community_room = Db::name('member_community_room')
                    ->where('mid',$vo['id'])
                    ->where('is_del',0)
                    ->field('tr_roomName,tr_relationType')
                    ->select()
                    ->toArray();
                if($community_room){
                    foreach($community_room as $cv){
                        if($community_infor){
                            $community_infor .= " \n\r ".$cv['tr_roomName'];
                        }else{
                            $community_infor = $cv['tr_roomName'];
                        }
                        if($cv['tr_relationType'] == 0){
                            $community_infor .= " 业主";
                        }else if($cv['tr_relationType'] == 1){
                            $community_infor .= " 家属";
                        }else if($cv['tr_relationType'] == 2){
                            $community_infor .= " 租户";
                        }else if($cv['tr_relationType'] == 3){
                            $community_infor .= " 买断";
                        }else if($cv['tr_relationType'] == 4){
                            $community_infor .= " 租用";
                        }
                    }
                    unset($cv);
                }else{
                    $community_infor = '';
                }
                $data[] = array($vo['id'],$vo['headimg'],$vo['nickname'],$vo['platform'],$vo['province'],$vo['city'],$vo['realname'],$vo['tel'],$vo['money'],$vo['score'],$vo['commission'],$vo['levelname'],date('Y-m-d H:i',$vo['createtime']),$vo['subscribe'],$vo['tr_is_rzh'],$community_infor,$vo['mpopenid'],$vo['wxopenid']);
            }else if(getcustom('pay_yuanbao')){
                $data[] = array($vo['id'],$vo['headimg'],$vo['nickname'],$vo['platform'],$vo['pmember'],$vo['province'],$vo['city'],$vo['realname'],$vo['tel'],$vo['money'],$vo['score'],$vo['commission'],$vo['yuanbao'],$vo['levelname'],date('Y-m-d H:i',$vo['createtime']),$vo['subscribe'],$vo['mpopenid'],$vo['wxopenid']);
            }else{
                
                $data[] = array($vo['id'],$vo['headimg'],$vo['nickname'],$vo['platform'],$vo['pmember'],$vo['province'],$vo['city'],$vo['realname'],$vo['tel'],$vo['money'],$vo['score'],$vo['commission'],$vo['levelname'],date('Y-m-d H:i',$vo['createtime']),$vo['subscribe'],$vo['mpopenid'],$vo['wxopenid']);
            }
		}
		$this->export_excel($title,$data);
	}
	
}
