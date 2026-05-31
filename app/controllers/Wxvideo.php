<?php


// +----------------------------------------------------------------------
// | 视频号接入
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;
use app\commons\Wechat;

class Wxvideo extends Common
{
	public function initialize(){
		parent::initialize();
		if(bid > 0) showmsg('无操作权限');
	}
	//申请接入
	public function apply(){
		if(request()->isAjax()){
			$postdata = [];
			$postdata['scene_group_id'] = 1;
			$rs = curl_post('https://api.weixin.qq.com/shop/register/apply_scene?access_token='.Wechat::access_token(aid,'wx'),jsonEncode($postdata));
			//var_dump($rs);die;
			$rs = json_decode($rs,true);
			if($rs['errcode']!=0){
				if($rs['errcode'] == 48001){
					return json(['status'=>0,'msg'=>'api功能未授权，请前往小程序后台申请开通[交易组件]-[自定义版交易组件]']);
				}
				return json(['status'=>0,'msg'=>Wechat::geterror($rs)]);
			}
			return json(['status'=>1,'msg'=>'接入成功']);
		}
		//获取接入状态
		$rs = curl_post('https://api.weixin.qq.com/shop/register/check?access_token='.Wechat::access_token(aid,'wx'),jsonEncode([]));
		//var_dump($rs);
		$rs = json_decode($rs,true);
		if($rs['errcode']!=0 && $rs['errcode']!=1040003){
			showmsg(Wechat::geterror($rs));
		}
		$statustxt = '未开通';
		if($rs['data']['status']==2) $statustxt = '已接入';
		if($rs['data']['status']==3) $statustxt = '封禁中';

		$access_info = $rs['data']['access_info'];
		$service_agent_path = '';
		$service_agent_phone = '';
		if($access_info){
			$rs = curl_post('https://api.weixin.qq.com/shop/account/get_info?access_token='.Wechat::access_token(aid,'wx'),jsonEncode([]));
			$rs = json_decode($rs,true);
			$service_agent_path = $rs['data']['service_agent_path'];
			$service_agent_phone = $rs['data']['service_agent_phone'];
			//var_dump($rs);
		}
		$appinfo = \app\commons\System::appinfo($aid,'wx');
		View::assign('authtype',$appinfo['authtype']);
		View::assign('statustxt',$statustxt);
		View::assign('access_info',$access_info);
		View::assign('service_agent_path',$service_agent_path);
		View::assign('service_agent_phone',$service_agent_phone);

		return View::fetch();
	}
	//设置商家信息
	public function setinfo(){
		if(request()->isPost()){
			$postinfo = input('post.info/a');
			$postdata = [];
			$service_agent_type = [];
			if($postinfo['service_agent_wx']==1){
				$service_agent_type[] = 0;
			}
			if($postinfo['service_agent_path']){
				$service_agent_type[] = 1;
				$postdata['service_agent_path'] = $postinfo['service_agent_path'];
			}
			if($postinfo['service_agent_phone']){
				$service_agent_type[] = 2;
				$postdata['service_agent_phone'] = $postinfo['service_agent_phone'];
			}
			$postdata['service_agent_type'] = $service_agent_type;
			$postdata['default_receiving_address'] = [
				'receiver_name'=>$postinfo['receiver_name'],
				'detailed_address'=>$postinfo['detailed_address'],
				'tel_number'=>$postinfo['tel_number'],
				'province'=>$postinfo['province'],
				'city'=>$postinfo['city'],
				'town'=>$postinfo['area']
			];
			$rs = curl_post('https://api.weixin.qq.com/shop/account/update_info?access_token='.Wechat::access_token(aid,'wx'),jsonEncode($postdata));
			$rs = json_decode($rs,true);
			if($rs['errcode']!=0){
				return json(['status'=>0,'msg'=>Wechat::geterror($rs),'$postdata'=>$postdata]);
			}
			Db::name('shop_sysset')->where('aid',aid)->update([
				'receiving_address_name'=>$postinfo['receiver_name'],
				'receiving_address_tel'=>$postinfo['tel_number'],
				'receiving_address_province'=>$postinfo['province'],
				'receiving_address_city'=>$postinfo['city'],
				'receiving_address_area'=>$postinfo['area'],
				'receiving_address_address'=>$postinfo['detailed_address']
			]);
			return json(['status'=>1,'msg'=>'设置成功']);
		}
		$rs = curl_post('https://api.weixin.qq.com/shop/account/get_info?access_token='.Wechat::access_token(aid,'wx'),jsonEncode([]));
		$rs = json_decode($rs,true);
		if($rs['errcode']!=0){
			showmsg(Wechat::geterror($rs));
		}
		$shopset = Db::name('shop_sysset')->where('aid',aid)->find();
		View::assign('service_data',$rs['data']);
		View::assign('shopset',$shopset);
		return View::fetch();
	}
	//完成接入任务
	public function finish_access_info(){
		$access_info_item = input('param.access_info_item');
		$rs = curl_post('https://api.weixin.qq.com/shop/register/finish_access_info?access_token='.Wechat::access_token(aid,'wx'),jsonEncode(['access_info_item'=>$access_info_item]));
		$rs = json_decode($rs,true);
		if($rs['errcode']!=0){
			return json(['status'=>0,'msg'=>Wechat::geterror($rs)]);
		}
		return json(['status'=>1,'msg'=>'操作成功']);
	}

	//已添加的类目
	public function category(){
		if(request()->isAjax()){
			$rs = curl_post('https://api.weixin.qq.com/shop/account/get_category_list?access_token='.Wechat::access_token(aid,'wx'),'{}');
			$rs = json_decode($rs,true);
			$category_list = $rs['data'];
			return json(['code'=>0,'msg'=>'查询成功','count'=>count($category_list),'data'=>$category_list]);
		}
		return View::fetch();
	}
	//所有类目
	public function allcategory(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
				$order = 'first_cat_name,second_cat_name,third_cat_name';
			}
			$where = array();
			if(input('param.keyword')) $where[] = ['third_cat_name|second_cat_name|first_cat_name','like','%'.input('param.keyword').'%'];
			$count = 0 + Db::name('wxvideo_catelist')->where($where)->count();
			$data = Db::name('wxvideo_catelist')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		$rs = Db::name('wxvideo_catelist')->where('1=1')->find();
		if(!$rs) $this->updatecategory();
		
		//已申请的类目
		$rs = curl_post('https://api.weixin.qq.com/shop/account/get_category_list?access_token='.Wechat::access_token(aid,'wx'),'{}');
		$rs = json_decode($rs,true);
		$category_list = $rs['data'];

		$mycateids = [];
		foreach($category_list as $v){
			$mycateids[] = $v['third_cat_id'];
		}
		View::assign('mycateids',$mycateids);
		View::assign('isadmin',$this->user['isadmin']);
		return View::fetch();
	}
	//更新所有类目
	public function updatecategory(){
		$rs = curl_post('https://api.weixin.qq.com/shop/cat/get?access_token='.Wechat::access_token(aid,'wx'),'{}');
		$rs = json_decode($rs,true);
		if($rs['errcode']!=0){
			return json(['status'=>0,'msg'=>Wechat::geterror($rs)]);
		}
		Db::name('wxvideo_catelist')->where('1=1')->delete();
		$alllist = $rs['third_cat_list'];
		foreach($alllist as $cate){
			$data = [];
			$data['third_cat_id'] = $cate['third_cat_id'];
			$data['third_cat_name'] = $cate['third_cat_name'];
			$data['qualification'] = $cate['qualification'];
			$data['qualification_type'] = $cate['qualification_type'];
			$data['product_qualification'] = $cate['product_qualification'];
			$data['product_qualification_type'] = $cate['product_qualification_type'];
			$data['second_cat_id'] = $cate['second_cat_id'];
			$data['second_cat_name'] = $cate['second_cat_name'];
			$data['first_cat_id'] = $cate['first_cat_id'];
			$data['first_cat_name'] = $cate['first_cat_name'];
			Db::name('wxvideo_catelist')->insert($data);
		}
		return json(['status'=>1,'msg'=>'更新成功']);
	}

	//申请类目
	public function addcategory(){
		$postinfo = input('post.info/a');
		$audit_req = [];
		$audit_req['license'] = [];
		$audit_req['license'][] = \app\commons\Wxvideo::uploadimg($postinfo['license']);
		$category_info = [];
		$category_info['level1'] = $postinfo['first_cat_id'];
		$category_info['level2'] = $postinfo['second_cat_id'];
		$category_info['level3'] = $postinfo['third_cat_id'];
		$category_info['certificate'] = [];
		foreach(explode(',',$postinfo['certificate']) as $v){
			$category_info['certificate'][] = \app\commons\Wxvideo::uploadimg($v);
		}
		$audit_req['category_info'] = $category_info;
		$audit_req['scene_group_list'] = [1];

		$postdata = ['audit_req'=>$audit_req];

		$rs = curl_post('https://api.weixin.qq.com/shop/audit/audit_category?access_token='.Wechat::access_token(aid,'wx'),jsonEncode($postdata));
		$rs = json_decode($rs,true);
		if($rs['errcode']!=0){
			return json(['status'=>0,'msg'=>Wechat::geterror($rs),'$postdata'=>$postdata]);
		}
		$applyinfo = [];
		$applyinfo['aid'] = aid;
		$applyinfo['license'] = $postinfo['license'];
		$applyinfo['first_cat_id'] = $postinfo['first_cat_id'];
		$applyinfo['first_cat_name'] = $postinfo['first_cat_name'];
		$applyinfo['second_cat_id'] = $postinfo['second_cat_id'];
		$applyinfo['second_cat_name'] = $postinfo['second_cat_name'];
		$applyinfo['third_cat_id'] = $postinfo['third_cat_id'];
		$applyinfo['third_cat_name'] = $postinfo['third_cat_name'];
		$applyinfo['certificate'] = $postinfo['certificate'];
		$applyinfo['createtime'] = time();
		$applyinfo['audit_id'] = $rs['audit_id'];
		$applyinfo['status'] = 0;
		Db::name('wxvideo_category_apply')->insert($applyinfo);
		return json(['status'=>1,'msg'=>'提交申请成功']);
	}

	//类目申请记录
	public function categoryapplylog(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
				$order = 'id desc';
			}
			$where = array();
			$where['aid'] = aid;
			if(input('param.keyword')) $where[] = ['third_cat_name|second_cat_name|first_cat_name','like','%'.input('param.keyword').'%'];
			$count = 0 + Db::name('wxvideo_category_apply')->where($where)->count();
			$data = Db::name('wxvideo_category_apply')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			foreach($data as $k=>$v){
				if($v['status'] == 0){
					$url = 'https://api.weixin.qq.com/shop/audit/result?access_token='.Wechat::access_token(aid,'wx');
					$postdata = ['audit_id'=>$v['audit_id']];
					$rs = curl_post($url,jsonEncode($postdata));
					$rs = json_decode($rs,true);
					if($rs['errcode'] == 0){
						if($rs['data']['status'] == 9){
							Db::name('wxvideo_category_apply')->where('id',$v['id'])->update(['status'=>2,'reject_reason'=>$rs['data']['reject_reason']]);
							$data[$k]['status'] = 2;
							$data[$k]['reject_reason'] = $rs['data']['reject_reason'];
						}elseif($rs['data']['status'] == 1){
							Db::name('wxvideo_category_apply')->where('id',$v['id'])->update(['status'=>1,'reject_reason'=>'']);
							$data[$k]['status'] = 1;
							$data[$k]['reject_reason'] = '';
						}
					}
				}
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
	}
	//删除申请记录
	public function categoryapplydel(){
		$id = input('param.id/d');
		Db::name('wxvideo_category_apply')->where('aid',aid)->where('id',$id)->delete();
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	
	//我的品牌
	public function brand(){
		if(request()->isAjax()){
			$rs = curl_post('https://api.weixin.qq.com/shop/account/get_brand_list?access_token='.Wechat::access_token(aid,'wx'),'{}');
			$rs = json_decode($rs,true);
			$brand_list = $rs['data'];
			return json(['code'=>0,'msg'=>'查询成功','count'=>count($brand_list),'data'=>$brand_list]);
		}
		return View::fetch();
	}
	//品牌申请记录
	public function brandapplylog(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
				$order = 'id desc';
			}
			$where = array();
			$where['aid'] = aid;
			if(input('param.keyword')) $where[] = ['third_cat_name|second_cat_name|first_cat_name','like','%'.input('param.keyword').'%'];
			$count = 0 + Db::name('wxvideo_brand_apply')->where($where)->count();
			$data = Db::name('wxvideo_brand_apply')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			foreach($data as $k=>$v){
				if($v['status'] == 0){
					$url = 'https://api.weixin.qq.com/shop/audit/result?access_token='.Wechat::access_token(aid,'wx');
					$postdata = ['audit_id'=>$v['audit_id']];
					$rs = curl_post($url,jsonEncode($postdata));
					$rs = json_decode($rs,true);
					if($rs['errcode'] == 0){
						if($rs['data']['status'] == 9){
							Db::name('wxvideo_brand_apply')->where('id',$v['id'])->update(['status'=>2,'reject_reason'=>$rs['data']['reject_reason']]);
							$data[$k]['status'] = 2;
							$data[$k]['reject_reason'] = $rs['data']['reject_reason'];
						}elseif($rs['data']['status'] == 1){
							Db::name('wxvideo_brand_apply')->where('id',$v['id'])->update(['status'=>1,'reject_reason'=>'']);
							$data[$k]['status'] = 1;
							$data[$k]['reject_reason'] = '';
						}
					}
				}
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
	}
	//添加品牌
	public function brandadd(){
		if(request()->isAjax()){
			$postinfo = input('post.info/a');
			$audit_req = [];
			$audit_req['license'] = \app\commons\Wxvideo::uploadimg($postinfo['license']);
			$brand_info = [];
			$brand_info['brand_audit_type'] = $postinfo['brand_audit_type'];
			$brand_info['trademark_type'] = $postinfo['trademark_type'];
			$brand_info['brand_management_type'] = $postinfo['brand_management_type'];
			$brand_info['commodity_origin_type'] = $postinfo['commodity_origin_type'];
			$brand_info['brand_wording'] = $postinfo['brand_wording'];
			if($postinfo['brand_management_type'] == 2){
				$brand_info['sale_authorization'] = $postinfo['sale_authorization'] ? explode(',',$postinfo['sale_authorization']) : [];
			}
			if($postinfo['brand_audit_type'] == 1 || $postinfo['brand_audit_type'] == 3){
				$brand_info['trademark_registrant'] = $postinfo['trademark_registrant'];
				$brand_info['trademark_authorization_period'] = $postinfo['trademark_authorization_period'];
				$brand_info['trademark_registration_certificate'] = [];
				foreach(explode(',',$postinfo['trademark_registration_certificate']) as $v){
					$brand_info['trademark_registration_certificate'][] = \app\commons\Wxvideo::uploadimg($v);
				}
			}else{
				$brand_info['trademark_applicant'] = $postinfo['trademark_applicant'];
				$brand_info['trademark_application_time'] = $postinfo['trademark_application_time'];
				$brand_info['trademark_registration_application'] = [];
				foreach(explode(',',$postinfo['trademark_registration_application']) as $v){
					$brand_info['trademark_registration_application'][] = \app\commons\Wxvideo::uploadimg($v);
				}
			}
			if($postinfo['trademark_change_certificate']){
				$brand_info['trademark_change_certificate'] = [];
				foreach(explode(',',$postinfo['trademark_change_certificate']) as $v){
					$brand_info['trademark_change_certificate'][] = \app\commons\Wxvideo::uploadimg($v);
				}
			}
			
			$brand_info['trademark_registrant_nu'] = $postinfo['trademark_registrant_nu'];
			$brand_info['imported_goods_form'] = $postinfo['imported_goods_form'] ? explode(',',$postinfo['imported_goods_form']) : [];
			$audit_req['brand_info'] = $brand_info;

			$postdata = ['audit_req'=>$audit_req];

			$rs = curl_post('https://api.weixin.qq.com/shop/audit/audit_brand?access_token='.Wechat::access_token(aid,'wx'),jsonEncode($postdata));
			$rs = json_decode($rs,true);
			if($rs['errcode']!=0){
				return json(['status'=>0,'msg'=>Wechat::geterror($rs),'$postdata'=>$postdata]);
			}
			$applyinfo = [];
			$applyinfo['aid'] = aid;
			$applyinfo['license'] = $postinfo['license'];
			$applyinfo['brand_audit_type'] = $postinfo['brand_audit_type'];
			$applyinfo['trademark_type'] = $postinfo['trademark_type'];
			$applyinfo['brand_management_type'] = $postinfo['brand_management_type'];
			$applyinfo['commodity_origin_type'] = $postinfo['commodity_origin_type'];
			$applyinfo['brand_wording'] = $postinfo['brand_wording'];
			$applyinfo['sale_authorization'] = $postinfo['sale_authorization'];
			$applyinfo['trademark_registration_certificate'] = $postinfo['trademark_registration_certificate'];
			$applyinfo['trademark_change_certificate'] = $postinfo['trademark_change_certificate'];
			$applyinfo['trademark_registrant'] = $postinfo['trademark_registrant'];
			$applyinfo['trademark_registrant_nu'] = $postinfo['trademark_registrant_nu'];
			$applyinfo['trademark_authorization_period'] = $postinfo['trademark_authorization_period'];
			$applyinfo['trademark_registration_application'] = $postinfo['trademark_registration_application'];
			$applyinfo['trademark_applicant'] = $postinfo['trademark_applicant'];
			$applyinfo['trademark_application_time'] = $postinfo['trademark_application_time'];
			$applyinfo['imported_goods_form'] = $postinfo['imported_goods_form'];
			$applyinfo['createtime'] = time();
			$applyinfo['audit_id'] = $rs['audit_id'];
			$applyinfo['status'] = 0;
			Db::name('wxvideo_brand_apply')->insert($applyinfo);
			return json(['status'=>1,'msg'=>'提交申请成功']);
		}
		if(input('param.id')){
			$info = Db::name('wxvideo_brand_apply')->where('aid',aid)->where('id',input('param.id/d'))->find();
		}else{
			$info = ['brand_audit_type'=>1,'brand_management_type'=>1,'commodity_origin_type'=>2];
		}
		View::assign('info',$info);
		return View::fetch();
	}
	//删除申请记录
	public function brandapplydel(){
		$id = input('param.id/d');
		Db::name('wxvideo_brand_apply')->where('aid',aid)->where('id',$id)->delete();
		return json(['status'=>1,'msg'=>'删除成功']);
	}

	//获取订单
	public function getorder(){
		$orderid = input('param.order_id');
		$order = Db::name('shop_order')->where('aid',aid)->where('id',$orderid)->find();
		$postdata = [];
		$postdata['out_order_id'] = $orderid;
		$postdata['openid'] = Db::name('member')->where('id',$order['mid'])->value('wxopenid');

		$url = 'https://api.weixin.qq.com/shop/order/get?access_token='.Wechat::access_token(aid,'wx');
		$rs = curl_post($url,jsonEncode($postdata));
		$rs = json_decode($rs,true);
		//if($rs['errcode']!=0){
		//	return json(['status'=>0,'msg'=>Wechat::geterror($rs),'$postdata'=>$postdata]);
		//}
		dump($rs);
	}
	public function scenecheck(){
		$url = 'https://api.weixin.qq.com/shop/scene/check?access_token='.Wechat::access_token(aid,'wx');
		$rs = curl_post($url,jsonEncode(['scene'=>1177]));
		$rs = json_decode($rs,true);
		dump($rs);
	}
	public function getdeliverycompany(){
		$url = 'https://api.weixin.qq.com/shop/delivery/get_company_list?access_token='.Wechat::access_token(aid,'wx');
		$rs = curl_post($url,jsonEncode(['scene'=>1177]));
		$rs = json_decode($rs,true);
		var_dump($rs);
	}
	public function getprolist(){
		$url = 'https://api.weixin.qq.com/shop/spu/get_list?access_token='.Wechat::access_token(aid,'wx');
		$rs = curl_post($url,jsonEncode(['page'=>1,'page_size'=>100]));
		$rs = json_decode($rs,true);
		var_dump($rs);
	}
	

	public function luru(){
		if(request()->isPost()){
			$data = input('post.');
			$prodata = explode('-',$data['prodata']);
			$member = Db::name('member')->where('aid',aid)->where('id',$data['mid'])->find();
			if(!$member) return json(['status'=>0,'msg'=>'未找到该'.t('会员')]);
			$prolist = [];
			$pstype = 0;
			foreach($prodata as $key=>$pro){
				$sdata = explode(',',$pro);
				$product = Db::name('shop_product')->where('aid',aid)->where('id',$sdata[0])->find();
				if(!$product) return json(['status'=>0,'msg'=>'产品不存在或已下架']);
				$guige = Db::name('shop_guige')->where('aid',aid)->where('id',$sdata[1])->find();
				if(!$guige) return json(['status'=>0,'msg'=>'产品规格不存在或已下架']);
				if($guige['stock'] < $sdata[2]){
					return json(['status'=>0,'msg'=>'库存不足']);
				}
				if($key==0) $title = $product['name'];
				$prolist[] = ['product'=>$product,'guige'=>$guige,'num'=>$sdata[2]];

				if($product['freighttype']==3){
					$pstype = 3;
				}elseif($product['freighttype']==4){
					$pstype = 4;
				}
			}
			if($pstype != 0 && count($prolist) > 1){
				return json(['status'=>0,'msg'=>($pstype==3 ? '自动发货' : '在线卡密').'商品需要单独录入']);
			}
			$sysset = Db::name('admin_set')->where('aid',aid)->find();
			
			$ordernum = date('ymdHis').rand(100000,999999);

			$orderdata = [];
			$orderdata['aid'] = aid;
			$orderdata['mid'] = $data['mid'];
			$orderdata['bid'] = bid;
			$orderdata['ordernum'] = $ordernum;
			$orderdata['title'] = $title.(count($prodata)>1?'等':'');
			
			$orderdata['linkman'] = $data['linkman'];
			$orderdata['tel'] = $data['tel'];
			$orderdata['area'] = '';
			$orderdata['address'] = $data['address'];
			$orderdata['totalprice'] = $data['totalprice'];
			$orderdata['product_price'] = $data['goodsprice'];
			$orderdata['leveldk_money'] = 0;  //会员折扣
			$orderdata['scoredk_money'] = 0;	//积分抵扣
			$orderdata['scoredkscore'] = 0;	//抵扣掉的积分
			$orderdata['freight_price'] = $data['freightprice']; //运费
			$orderdata['message'] = '';
			$orderdata['freight_text'] = $data['freight'];
			$orderdata['freight_id'] = 0;
			$orderdata['freight_type'] = 0;
			$orderdata['createtime'] = time();
			$orderdata['platform'] = 'wx';
			$orderdata['hexiao_code'] = random(16);
			$orderdata['hexiao_qr'] = createqrcode(m_url('admin/hexiao/hexiao?type=shop&co='.$orderdata['hexiao_code']));
			$orderdata['status'] = 1;
			$orderdata['paytype'] = $data['paytype'];
			$orderdata['paytypeid'] = 2;
			$orderdata['remark'] = '后台录入';
			$orderdata['fromwxvideo'] = 1;
			$orderdata['scene'] = 1177;
			$orderid = Db::name('shop_order')->insertGetId($orderdata);
			
			var_dump('$orderid');
			var_dump($orderid);
			foreach($prolist as $key=>$v){
				$product = $v['product'];
				$guige = $v['guige'];
				$num = $v['num'];
				$ogdata = [];
				$ogdata['aid'] = aid;
				$ogdata['bid'] = $product['bid'];
				$ogdata['mid'] = $data['mid'];
				$ogdata['orderid'] = $orderid;
				$ogdata['ordernum'] = $orderdata['ordernum'];
				$ogdata['proid'] = $product['id'];
				$ogdata['name'] = $product['name'];
				$ogdata['pic'] = $product['pic'];
				$ogdata['procode'] = $product['procode'];
				$ogdata['ggid'] = $guige['id'];
				$ogdata['ggname'] = $guige['name'];
				$ogdata['cid'] = $product['cid'];
				$ogdata['num'] = $num;
				$ogdata['cost_price'] = $guige['cost_price'];
				$ogdata['sell_price'] = $guige['sell_price'];
				$ogdata['totalprice'] = $num * $guige['sell_price'];
				$ogdata['status'] = 1;
				$ogdata['createtime'] = time();
				$ogid = Db::name('shop_order_goods')->insertGetId($ogdata);
			}
			$rs = \app\commons\Wxvideo::createorder($orderid);
			if($rs['status']==0){
				return json($rs);
			}
			var_dump($rs);
			//生成支付参数
			$aid = aid;
			$order = Db::name('shop_order')->where('id',$orderid)->find();
			$openid = Db::name('member')->where('id',$order['mid'])->value('wxopenid');
			$url = 'https://api.weixin.qq.com/shop/order/getpaymentparams?access_token='.\app\commons\Wechat::access_token($aid,'wx');
			$rs = curl_post($url,jsonEncode(['out_order_id'=>strval($order['id']),'openid'=>$openid]));
			$rs = json_decode($rs,true);
			var_dump($rs);
			\think\facade\Log::write($rs);
			if($rs['errcode'] == 0 && $rs['payment_params']){
				$wOpt = $rs['payment_params'];
				//return ['status'=>1,'data'=>$wOpt,'fromwxvideo'=>1];
			}else{
				return json(['status'=>0,'msg'=>\app\commons\Wechat::geterror($rs)]);
			}


			$ordernum = date('ymdHis').rand(100000,999999);

			$orderdata = [];
			$orderdata['aid'] = aid;
			$orderdata['mid'] = $data['mid'];
			$orderdata['bid'] = bid;
			$orderdata['ordernum'] = $ordernum;
			$orderdata['title'] = $title.(count($prodata)>1?'等':'');
			
			$orderdata['linkman'] = $data['linkman'];
			$orderdata['tel'] = $data['tel'];
			$orderdata['area'] = '';
			$orderdata['address'] = $data['address'];
			$orderdata['totalprice'] = $data['totalprice'];
			$orderdata['product_price'] = $data['goodsprice'];
			$orderdata['leveldk_money'] = 0;  //会员折扣
			$orderdata['scoredk_money'] = 0;	//积分抵扣
			$orderdata['scoredkscore'] = 0;	//抵扣掉的积分
			$orderdata['freight_price'] = $data['freightprice']; //运费
			$orderdata['message'] = '';
			$orderdata['freight_text'] = $data['freight'];
			$orderdata['freight_id'] = 0;
			$orderdata['freight_type'] = $pstype;
			$orderdata['createtime'] = time();
			$orderdata['platform'] = 'wx';
			$orderdata['hexiao_code'] = random(16);
			$orderdata['hexiao_qr'] = createqrcode(m_url('admin/hexiao/hexiao?type=shop&co='.$orderdata['hexiao_code']));
			$orderdata['status'] = 1;
			$orderdata['paytype'] = $data['paytype'];
			$orderdata['paytypeid'] = 1;
			$orderdata['remark'] = '后台录入';
			$orderdata['fromwxvideo'] = 1;
			$orderdata['scene'] = 1177;
			$orderid = Db::name('shop_order')->insertGetId($orderdata);

			var_dump('$orderid');
			var_dump($orderid);

			foreach($prolist as $key=>$v){
				$product = $v['product'];
				$guige = $v['guige'];
				$num = $v['num'];
				$ogdata = [];
				$ogdata['aid'] = aid;
				$ogdata['bid'] = $product['bid'];
				$ogdata['mid'] = $data['mid'];
				$ogdata['orderid'] = $orderid;
				$ogdata['ordernum'] = $orderdata['ordernum'];
				$ogdata['proid'] = $product['id'];
				$ogdata['name'] = $product['name'];
				$ogdata['pic'] = $product['pic'];
				$ogdata['procode'] = $product['procode'];
				$ogdata['ggid'] = $guige['id'];
				$ogdata['ggname'] = $guige['name'];
				$ogdata['cid'] = $product['cid'];
				$ogdata['num'] = $num;
				$ogdata['cost_price'] = $guige['cost_price'];
				$ogdata['sell_price'] = $guige['sell_price'];
				$ogdata['totalprice'] = $num * $guige['sell_price'];
				$ogdata['status'] = 1;
				$ogdata['createtime'] = time();
				$ogid = Db::name('shop_order_goods')->insertGetId($ogdata);
			}
			$rs = \app\commons\Wxvideo::createorder($orderid);
			var_dump('createorder');
			var_dump($rs);
			if($rs['status']==0){
				return json($rs);
			}

			$rs = \app\commons\Wxvideo::getorder($orderid);
			var_dump('getorder');
			var_dump($rs);

			$rs = \app\commons\Wxvideo::orderpay($orderid);
			var_dump('orderpay');
			var_dump($rs);
			if($rs['status']==0){
				return json($rs);
			}

			//物流接口调用
			Db::name('shop_order')->where('id',$orderid)->update(['status'=>2,'express_com'=>'申通快递','express_no'=>'1111111111','send_time'=>time()]);
			$rs = \app\commons\Wxvideo::deliverysend($orderid);
			var_dump('deliverysend');
			var_dump($rs);
			if($rs['status']==0){
				return json($rs);
			}
			//创建售后
			$order = Db::name('shop_order')->where('id',$orderid)->find();
			$refund_ordernum = date('YmdHis').rand(1000,9999);
			$refund_id = Db::name('shop_refund_order')->insertGetId([
				'aid'=>aid,
				'mid'=>$order['mid'],
				'orderid'=>$orderid,
				'ordernum'=>$order['ordernum'],
				'refund_ordernum'=>$refund_ordernum,
				'refund_type'=>'refund',
				'refund_money'=>$order['totalprice'],
				'refund_time'=>time(),
				'createtime'=>time(),
				'refund_status'=>3,
			]);
			$refund_ogid = Db::name('shop_refund_order_goods')->insertGetId([
				'aid'=>aid,
				'mid'=>$order['mid'],
				'refund_orderid'=>$refund_id,
				'refund_ordernum'=>$refund_ordernum,
				'refund_num'=>1,
				'refund_money'=>$order['totalprice'],
				'orderid'=>$orderid,
				'ordernum'=>$order['ordernum'],
				'ogid'=>$ogid,
				'proid'=>$ogdata['proid'],
				'name'=>$ogdata['name'],
				'pic'=>$ogdata['pic'],
				'ggid'=>$ogdata['ggid'],
				'ggname'=>$ogdata['ggname'],
				'sell_price'=>$ogdata['sell_price'],
				'createtime'=>time(),
			]);
			$rs = \app\commons\Wxvideo::aftersaleadd($orderid,$refund_id);
			var_dump('aftersaleadd');
			var_dump($rs);
			if($rs['status']==0){
				return json($rs);
			}
			var_dump($rs);
			$rs = \app\commons\Wxvideo::aftersaleupdate($orderid,$refund_id);
			var_dump('aftersaleupdate');
			var_dump($rs);
			if($rs['status']==0){
				return json($rs);
			}
			
			//Db::name('shop_order')->where('id',$orderid)->delete();
			//Db::name('shop_order_goods')->where('orderid',$orderid)->delete();
			//Db::name('shop_refund_order')->where('id',$refund_id)->delete();
			//Db::name('shop_refund_order_goods')->where('id',$refund_ogid)->delete();

			return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('apply')]);
		}
		return View::fetch();
	}
	
	public function deliverytongbu(){
		if(request()->isPost()){
			$orderid = input('param.orderid');
			$order = Db::name('shop_order')->where('aid',aid)->where('id',$orderid)->find();
			if(!$order) return json(['status'=>0,'msg'=>'订单不存在']);
			if(input('param.type') == 0){
				$express_com = input('param.express_com');
				$express_no = input('param.express_no');
				if($express_com){
					$aid = $order['aid'];
					$postdata = [];
					$postdata['out_order_id'] = strval($order['id']);
					$postdata['openid'] = Db::name('member')->where('id',$order['mid'])->value('wxopenid');
					$postdata['finish_all_delivery'] = 1;
					if($order['freight_type'] == 0){
						$delivery_list = [];
						$delivery_list['delivery_id'] = \app\commons\Wxvideo::get_delivery_id($express_com);
						$delivery_list['waybill_id'] = $express_no;
						$product_info_list = [];
						$oglist = Db::name('shop_order_goods')->where('orderid',$order['id'])->select()->toArray();
						foreach($oglist as $og){
							$product_info_list[] = ['out_product_id'=>strval($og['proid']),'out_sku_id'=>strval($og['ggid']),'product_cnt'=>$og['num']];
						}
						$delivery_list['product_info_list'] = $product_info_list;
						$postdata['delivery_list'] = [$delivery_list];
					}
					$postdata['ship_done_time'] = date('Y-m-d H:i:s');
					//\think\facade\Log::write($postdata);
					$rs = curl_post('https://api.weixin.qq.com/shop/delivery/send?access_token='.\app\commons\Wechat::access_token($aid,'wx'),jsonEncode($postdata));
					$rs = json_decode($rs,true);
					if($rs['errcode']==0){
						return json(['status'=>1,'msg'=>'操作成功']);
					}else{
						return json(['status'=>0,'msg'=>Wechat::geterror($rs)]);
					}
				}else{
					$rs = \app\commons\Wxvideo::deliverysend($orderid);
					return json($rs);
				}
			}else{
				$rs = \app\commons\Wxvideo::deliveryrecieve($orderid);
				return json($rs);
			}
		}
		View::assign('express_data',express_data(['aid'=>aid,'bid'=>bid]));
		return View::fetch();
	}
	//同步订单发货
	public function deliverysend(){
		$orderid = input('param.orderid');
		$express_com = input('param.express_com');
		$express_no = input('param.express_no');
		if($express_com){
			$order = Db::name('shop_order')->where('id',$orderid)->find();
			$aid = $order['aid'];
			$postdata = [];
			$postdata['out_order_id'] = strval($order['id']);
			$postdata['openid'] = Db::name('member')->where('id',$order['mid'])->value('wxopenid');
			$postdata['finish_all_delivery'] = 1;
			if($order['freight_type'] == 0){
				$delivery_list = [];
				$delivery_list['delivery_id'] = \app\commons\Wxvideo::get_delivery_id($express_com);
				$delivery_list['waybill_id'] = $express_no;
				$product_info_list = [];
				$oglist = Db::name('shop_order_goods')->where('orderid',$order['id'])->select()->toArray();
				foreach($oglist as $og){
					$product_info_list[] = ['out_product_id'=>strval($og['proid']),'out_sku_id'=>strval($og['ggid']),'product_cnt'=>$og['num']];
				}
				$delivery_list['product_info_list'] = $product_info_list;
				$postdata['delivery_list'] = [$delivery_list];
			}
			$postdata['ship_done_time'] = date('Y-m-d H:i:s');
			//\think\facade\Log::write($postdata);
			$rs = curl_post('https://api.weixin.qq.com/shop/delivery/send?access_token='.\app\commons\Wechat::access_token($aid,'wx'),jsonEncode($postdata));
			$rs = json_decode($rs,true);
			var_dump($rs);
		}else{
			$rs = \app\commons\Wxvideo::deliverysend($orderid);
			var_dump($rs);
		}
	} 
	//同步订单收货
	public function deliveryrecieve(){
		$orderid = input('param.orderid');
		$rs = \app\commons\Wxvideo::deliveryrecieve($orderid);
		var_dump($rs);
	}
}
