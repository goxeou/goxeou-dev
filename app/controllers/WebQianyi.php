<?php


// +----------------------------------------------------------------------
// | 数据迁移
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class WebQianyi extends Common
{
    public function initialize(){
		parent::initialize();
		$this->uid = session('BST_ID');
		$this->user = db('admin_user')->where(['id'=>$this->uid])->find();
		if(!session('BST_ID') || !$this->user || $this->user['isadmin'] != 2){
			showmsg('无访问权限');
		}
	}
	public function index(){
		set_time_limit(0);
		ini_set('memory_limit','-1');
		if(request()->isPost()){
			$fromtype = input('post.fromtype');
			$localhost = explode(':',input('post.localhost'))[0];
			$hostport = explode(':',input('post.localhost'))[1];
			$db_username = input('post.db_username');
			$db_password = input('post.db_password');
			$db_name = input('post.db_name');
			$fromid = input('post.fromid');
			$toid = input('post.toid');
			if($fromtype == 2){ //其他数据库
				Config::set([
					'connections' => [
						'mysql'=>['type'=>'mysql','hostname'=>$localhost,'database'=>$db_name,'username'=>$db_username,'password'=>$db_password,'hostport'=>$hostport,'charset'=>'utf8']
					]
				], 'database');
			}
			
			$data = Db::query('select * from '.table_name('admin_set').' where aid='.$fromid);
			db('admin_set')->where(['aid'=>$toid])->delete();
			foreach($data as $newdata){
				$newdata['id'] = '';
				$newdata['aid'] = $toid;
				if(isset($newdata['w7moneyscore'])) unset($newdata['w7moneyscore']);
				db('admin_set')->insert($newdata);
			}

			$data = Db::query('select * from '.table_name('designer_menu').' where aid='.$fromid);
			db('designer_menu')->where(['aid'=>$toid])->delete();
			foreach($data as $newdata){
				$newdata['id'] = '';
				$newdata['aid'] = $toid;
				if($newdata['platform'] == 'mp' && strpos($newdata['indexurl'],'http')===0){
					$newdata['indexurl'] = PRE_URL.explode('/',str_replace(['https://','http://'],'',$newdata['indexurl']))[0];
				}
				db('designer_menu')->insert($newdata);
			}

			$data = Db::query('select * from '.table_name('admin_set_poster').' where aid='.$fromid);
			db('admin_set_poster')->where(['aid'=>$toid])->delete();
			foreach($data as $newdata){
				$newdata['id'] = '';
				$newdata['aid'] = $toid;
				db('admin_set_poster')->insert($newdata);
			}
			
			$data = Db::query('select * from '.table_name('admin_set_sms').' where aid='.$fromid);
			db('admin_set_sms')->where(['aid'=>$toid])->delete();
			foreach($data as $newdata){
				$newdata['id'] = '';
				$newdata['aid'] = $toid;
				db('admin_set_sms')->insert($newdata);
			}
			$data = Db::query('select * from '.table_name('admin_set_usercenter').' where aid='.$fromid);
			db('admin_set_usercenter')->where(['aid'=>$toid])->delete();
			foreach($data as $newdata){
				$newdata['id'] = '';
				$newdata['aid'] = $toid;
				db('admin_set_usercenter')->insert($newdata);
			}

			
			$data = Db::query('select * from '.table_name('member_level').' where aid='.$fromid);
			db('member_level')->where(['aid'=>$toid])->delete();
			$member_level_ids_map = [];
			foreach($data as $newdata){
				$oldid = $newdata['id'];
				$newdata['id'] = '';
				$newdata['aid'] = $toid;
				if(isset($newdata['notshowbtel'])) unset($newdata['notshowbtel']);
				$member_level_ids_map[$oldid] = db('member_level')->insertGetId($newdata);
			}
			$data = Db::query('select * from '.table_name('member').' where aid='.$fromid);
			db('member')->where(['aid'=>$toid])->delete();
			$member_ids_map = [];
			foreach($data as $newdata){
				$oldid = $newdata['id'];
				$newdata['id'] = '';
				$newdata['aid'] = $toid;
				$newdata['levelid'] = $member_level_ids_map[$newdata['levelid']];
				$member_ids_map[$oldid] = db('member')->insertGetId($newdata);
			}
			$data = Db::query('select * from '.table_name('member_address').' where aid='.$fromid);
			db('member_address')->where(['aid'=>$toid])->delete();
			foreach($data as $newdata){
				$oldid = $newdata['id'];
				$newdata['id'] = '';
				$newdata['aid'] = $toid;
				$newdata['mid'] = $member_ids_map[$newdata['mid']];
				db('member_address')->insertGetId($newdata);
			}
			$data = Db::query('select * from '.table_name('member_commissionlog').' where aid='.$fromid);
			db('member_commissionlog')->where(['aid'=>$toid])->delete();
			foreach($data as $newdata){
				$oldid = $newdata['id'];
				$newdata['id'] = '';
				$newdata['aid'] = $toid;
				$newdata['mid'] = $member_ids_map[$newdata['mid']];
				$newdata['frommid'] = $member_ids_map[$newdata['frommid']];
				db('member_commissionlog')->insertGetId($newdata);
			}
			$data = Db::query('select * from '.table_name('member_commission_withdrawlog').' where aid='.$fromid);
			db('member_commission_withdrawlog')->where(['aid'=>$toid])->delete();
			foreach($data as $newdata){
				$oldid = $newdata['id'];
				$newdata['id'] = '';
				$newdata['aid'] = $toid;
				$newdata['mid'] = $member_ids_map[$newdata['mid']];
				db('member_commission_withdrawlog')->insertGetId($newdata);
			}
			$data = Db::query('select * from '.table_name('member_levelup_order').' where aid='.$fromid);
			db('member_levelup_order')->where(['aid'=>$toid])->delete();
			foreach($data as $newdata){
				$oldid = $newdata['id'];
				$newdata['id'] = '';
				$newdata['aid'] = $toid;
				$newdata['mid'] = $member_ids_map[$newdata['mid']];
				$newdata['levelid'] = $member_level_ids_map[$newdata['levelid']];
				db('member_levelup_order')->insertGetId($newdata);
			}
			$data = Db::query('select * from '.table_name('member_moneylog').' where aid='.$fromid);
			db('member_moneylog')->where(['aid'=>$toid])->delete();
			foreach($data as $newdata){
				$oldid = $newdata['id'];
				$newdata['id'] = '';
				$newdata['aid'] = $toid;
				$newdata['mid'] = $member_ids_map[$newdata['mid']];
				db('member_moneylog')->insertGetId($newdata);
			}
			$data = Db::query('select * from '.table_name('recharge_order').' where aid='.$fromid);
			db('recharge_order')->where(['aid'=>$toid])->delete();
			foreach($data as $newdata){
				$oldid = $newdata['id'];
				$newdata['id'] = '';
				$newdata['aid'] = $toid;
				$newdata['mid'] = $member_ids_map[$newdata['mid']];
				db('recharge_order')->insertGetId($newdata);
			}
			$data = Db::query('select * from '.table_name('member_scorelog').' where aid='.$fromid);
			db('member_scorelog')->where(['aid'=>$toid])->delete();
			foreach($data as $newdata){
				$oldid = $newdata['id'];
				$newdata['id'] = '';
				$newdata['aid'] = $toid;
				$newdata['mid'] = $member_ids_map[$newdata['mid']];
				db('member_scorelog')->insertGetId($newdata);
			}
			$data = Db::query('select * from '.table_name('member_tjscore').' where aid='.$fromid);
			db('member_tjscore')->where(['aid'=>$toid])->delete();
			foreach($data as $newdata){
				$oldid = $newdata['id'];
				$newdata['id'] = '';
				$newdata['aid'] = $toid;
				$newdata['mid'] = $member_ids_map[$newdata['mid']];
				$newdata['frommid'] = $member_ids_map[$newdata['frommid']];
				db('member_tjscore')->insertGetId($newdata);
			}
			$data = Db::query('select * from '.table_name('member_withdrawlog').' where aid='.$fromid);
			db('member_withdrawlog')->where(['aid'=>$toid])->delete();
			foreach($data as $newdata){
				$oldid = $newdata['id'];
				$newdata['id'] = '';
				$newdata['aid'] = $toid;
				$newdata['mid'] = $member_ids_map[$newdata['mid']];
				db('member_withdrawlog')->insertGetId($newdata);
			}
			$data = Db::query('select * from '.table_name('membercard').' where aid='.$fromid);
			db('membercard')->where(['aid'=>$toid])->delete();
			foreach($data as $newdata){
				$oldid = $newdata['id'];
				$newdata['id'] = '';
				$newdata['aid'] = $toid;
				db('membercard')->insertGetId($newdata);
			}
			$data = Db::query('select * from '.table_name('membercard_record').' where aid='.$fromid);
			db('membercard_record')->where(['aid'=>$toid])->delete();
			foreach($data as $newdata){
				$oldid = $newdata['id'];
				$newdata['id'] = '';
				$newdata['aid'] = $toid;
				$newdata['mid'] = $member_ids_map[$newdata['mid']];
				db('membercard_record')->insertGetId($newdata);
			}
			$data = Db::query('select * from '.table_name('mendian').' where aid='.$fromid);
			db('mendian')->where(['aid'=>$toid])->delete();
			foreach($data as $newdata){
				$oldid = $newdata['id'];
				$newdata['id'] = '';
				$newdata['aid'] = $toid;
				db('mendian')->insertGetId($newdata);
			}
			$data = Db::query('select * from '.table_name('mp_tmplset').' where aid='.$fromid);
			db('mp_tmplset')->where(['aid'=>$toid])->delete();
			foreach($data as $newdata){
				$oldid = $newdata['id'];
				$newdata['id'] = '';
				$newdata['aid'] = $toid;
				db('mp_tmplset')->insertGetId($newdata);
			}

			
			$data = Db::query('select * from '.table_name('scoreshop_sysset').' where aid='.$fromid);
			db('scoreshop_sysset')->where(['aid'=>$toid])->delete();
			foreach($data as $newdata){
				$oldid = $newdata['id'];
				$newdata['id'] = '';
				$newdata['aid'] = $toid;
				db('scoreshop_sysset')->insertGetId($newdata);
			}

			$data = Db::query('select * from '.table_name('scoreshop_category').' where aid='.$fromid);
			db('scoreshop_category')->where(['aid'=>$toid])->delete();
			$scoreshop_category_ids_map = [];
			$scoreshop_category_ids_map['0'] = '0';
			foreach($data as $newdata){
				$oldid = $newdata['id'];
				$newdata['id'] = '';
				$newdata['aid'] = $toid;
				$scoreshop_category_ids_map[$oldid] = db('scoreshop_category')->insertGetId($newdata);
			}
			$data = Db::query('select * from '.table_name('scoreshop_freight').' where aid='.$fromid);
			db('scoreshop_freight')->where(['aid'=>$toid])->delete();
			$scoreshop_freight_ids_map = [];
			foreach($data as $newdata){
				$oldid = $newdata['id'];
				$newdata['id'] = '';
				$newdata['aid'] = $toid;
				$scoreshop_freight_ids_map[$oldid] = db('scoreshop_freight')->insertGetId($newdata);
			}

			$data = Db::query('select * from '.table_name('scoreshop_freight_codelist').' where aid='.$fromid);
			db('scoreshop_freight_codelist')->where(['aid'=>$toid])->delete();
			$scoreshop_freight_codelist_ids_map = [];
			foreach($data as $newdata){
				$oldid = $newdata['id'];
				$newdata['id'] = '';
				$newdata['aid'] = $toid;
				$newdata['fid'] = $scoreshop_freight_ids_map[$newdata['fid']];
				$scoreshop_freight_codelist_ids_map[$oldid] = db('scoreshop_freight_codelist')->insertGetId($newdata);
			}

			
			$data = Db::query('select * from '.table_name('scoreshop_product').' where aid='.$fromid);
			db('scoreshop_product')->where(['aid'=>$toid])->delete();
			$scoreshop_product_ids_map = [];
			foreach($data as $newdata){
				$oldid = $newdata['id'];
				$newdata['id'] = '';
				$newdata['aid'] = $toid;
				$newdata['cid'] = $scoreshop_category_ids_map[$newdata['cid']];
				$scoreshop_product_ids_map[$oldid] = db('scoreshop_product')->insertGetId($newdata);
			}

			$data = Db::query('select * from '.table_name('scoreshop_order').' where aid='.$fromid);
			db('scoreshop_order')->where(['aid'=>$toid])->delete();
			foreach($data as $newdata){
				$oldid = $newdata['id'];
				$newdata['id'] = '';
				$newdata['aid'] = $toid;
				$newdata['mid'] = $member_ids_map[$newdata['mid']];
				$newdata['proid'] = $scoreshop_product_ids_map[$newdata['proid']];
				$newdata['code'] = random(16);
				db('scoreshop_order')->insertGetId($newdata);
			}

			$data = Db::query('select * from '.table_name('article_category').' where aid='.$fromid);
			db('article_category')->where(['aid'=>$toid])->delete();
			$article_category_ids_map = [];
			foreach($data as $newdata){
				$oldid = $newdata['id'];
				$newdata['id'] = '';
				$newdata['aid'] = $toid;
				$article_category_ids_map[$oldid] = db('article_category')->insertGetId($newdata);
			}

			$data = Db::query('select * from '.table_name('article').' where aid='.$fromid);
			db('article')->where(['aid'=>$toid])->delete();
			$article_ids_map = [];
			foreach($data as $newdata){
				$oldid = $newdata['id'];
				$newdata['id'] = '';
				$newdata['aid'] = $toid;
				$newdata['cid'] = $article_category_ids_map[$newdata['cid']];
				$article_ids_map[$oldid] = db('article')->insertGetId($newdata);
			}

			$data = Db::query('select * from '.table_name('article_pinglun').' where aid='.$fromid);
			db('article_pinglun')->where(['aid'=>$toid])->delete();
			$article_pinglun_ids_map = [];
			foreach($data as $newdata){
				$oldid = $newdata['id'];
				$newdata['id'] = '';
				$newdata['aid'] = $toid;
				$newdata['sid'] = $article_ids_map[$newdata['sid']];
				$newdata['mid'] = $member_ids_map[$newdata['mid']];
				$article_pinglun_ids_map[$oldid] = db('article_pinglun')->insertGetId($newdata);
			}

			$data = Db::query('select * from '.table_name('article_pinglun_reply').' where aid='.$fromid);
			db('article_pinglun_reply')->where(['aid'=>$toid])->delete();
			$article_pinglun_reply_ids_map = [];
			foreach($data as $newdata){
				$oldid = $newdata['id'];
				$newdata['id'] = '';
				$newdata['aid'] = $toid;
				$newdata['sid'] = $article_ids_map[$newdata['sid']];
				$newdata['pid'] = $article_pinglun_ids_map[$newdata['pid']];
				$newdata['mid'] = $member_ids_map[$newdata['mid']];
				$article_pinglun_reply_ids_map[$oldid] = db('article_pinglun_reply')->insertGetId($newdata);
			}
			
			$data = Db::query('select * from '.table_name('recharge_giveset').' where aid='.$fromid);
			db('recharge_giveset')->where(['aid'=>$toid])->delete();
			foreach($data as $newdata){
				$newdata['id'] = '';
				$newdata['aid'] = $toid;
				db('recharge_giveset')->insertGetId($newdata);
			}
			$data = Db::query('select * from '.table_name('signset').' where aid='.$fromid);
			db('signset')->where(['aid'=>$toid])->delete();
			foreach($data as $newdata){
				$newdata['id'] = '';
				$newdata['aid'] = $toid;
				db('signset')->insertGetId($newdata);
			}
			
			$data = Db::query('select * from '.table_name('sign_record').' where aid='.$fromid);
			db('sign_record')->where(['aid'=>$toid])->delete();
			foreach($data as $newdata){
				$newdata['id'] = '';
				$newdata['aid'] = $toid;
				$newdata['mid'] = $member_ids_map[$newdata['mid']];
				db('sign_record')->insertGetId($newdata);
			}


			$data = Db::query('select * from '.table_name('shop_sysset').' where aid='.$fromid);
			db('shop_sysset')->where(['aid'=>$toid])->delete();
			foreach($data as $newdata){
				$newdata['id'] = '';
				$newdata['aid'] = $toid;
				db('shop_sysset')->insertGetId($newdata);
			}
			$data = Db::query('select * from '.table_name('shop_category').' where aid='.$fromid);
			db('shop_category')->where(['aid'=>$toid])->delete();
			$shop_category_ids_map = [];
			$shop_category_ids_map['0'] = '0';
			foreach($data as $newdata){
				$oldid = $newdata['id'];
				$newdata['id'] = '';
				$newdata['aid'] = $toid;
				$newdata['pid'] = $shop_category_ids_map[$newdata['pid']];
				$shop_category_ids_map[$oldid] = db('shop_category')->insertGetId($newdata);
			}

			$data = Db::query('select * from '.table_name('shop_group').' where aid='.$fromid);
			db('shop_group')->where(['aid'=>$toid])->delete();
			$shop_group_ids_map = [];
			foreach($data as $newdata){
				$oldid = $newdata['id'];
				$newdata['id'] = '';
				$newdata['aid'] = $toid;
				$shop_group_ids_map[$oldid] = db('shop_group')->insertGetId($newdata);
			}
			$data = Db::query('select * from '.table_name('shop_freight').' where aid='.$fromid);
			db('shop_freight')->where(['aid'=>$toid])->delete();
			$shop_freight_ids_map = [];
			foreach($data as $newdata){
				$oldid = $newdata['id'];
				$newdata['id'] = '';
				$newdata['aid'] = $toid;
				$shop_freight_ids_map[$oldid] = db('shop_freight')->insertGetId($newdata);
			}

			$data = Db::query('select * from '.table_name('shop_freight_codelist').' where aid='.$fromid);
			db('shop_freight_codelist')->where(['aid'=>$toid])->delete();
			$shop_freight_codelist_ids_map = [];
			foreach($data as $newdata){
				$oldid = $newdata['id'];
				$newdata['id'] = '';
				$newdata['aid'] = $toid;
				$newdata['fid'] = $shop_freight_ids_map[$newdata['fid']];
				$shop_freight_codelist_ids_map[$oldid] = db('shop_freight_codelist')->insertGetId($newdata);
			}


			$data = Db::query('select * from '.table_name('business_category').' where aid='.$fromid);
			db('business_category')->where(['aid'=>$toid])->delete();
			$business_category_ids_map = [];
			foreach($data as $newdata){
				$oldid = $newdata['id'];
				$newdata['id'] = '';
				$newdata['aid'] = $toid;
				$business_category_ids_map[$oldid] = db('business_category')->insertGetId($newdata);
			}
			$data = Db::query('select * from '.table_name('business').' where aid='.$fromid);
			db('business')->where(['aid'=>$toid])->delete();
			$business_ids_map = [];
			$business_ids_map['0'] = '0';
			foreach($data as $newdata){
				$oldid = $newdata['id'];
				$newdata['id'] = '';
				$newdata['aid'] = $toid;
				$newdata['mid'] = $member_ids_map[$newdata['mid']];
				$newdata['cid'] = $business_category_ids_map[$newdata['cid']];
				$business_ids_map[$oldid] = db('business')->insertGetId($newdata);
			}
			
			$data = Db::query('select * from '.table_name('shop_product').' where aid='.$fromid);
			db('shop_product')->where(['aid'=>$toid])->delete();
			$shop_product_ids_map = [];
			foreach($data as $newdata){
				$oldid = $newdata['id'];
				$newdata['id'] = '';
				$newdata['aid'] = $toid;
				$newdata['cid'] = $shop_category_ids_map[$newdata['cid']];
				$newdata['gid'] = $shop_group_ids_map[$newdata['gid']];
				$newdata['bid'] = $business_ids_map[$newdata['bid']];
				$shop_product_ids_map[$oldid] = db('shop_product')->insertGetId($newdata);
			}
			$data = Db::query('select * from '.table_name('shop_guige').' where aid='.$fromid);
			db('shop_guige')->where(['aid'=>$toid])->delete();
			$shop_guige_ids_map = [];
			foreach($data as $newdata){
				$oldid = $newdata['id'];
				$newdata['id'] = '';
				$newdata['aid'] = $toid;
				$newdata['proid'] = $shop_product_ids_map[$newdata['proid']];
				$shop_guige_ids_map[$oldid] = db('shop_guige')->insertGetId($newdata);
			}
			$data = Db::query('select * from '.table_name('shop_order').' where aid='.$fromid);
			db('shop_order')->where(['aid'=>$toid])->delete();
			$shop_order_ids_map = [];
			foreach($data as $newdata){
				$oldid = $newdata['id'];
				$newdata['id'] = '';
				$newdata['aid'] = $toid;
				$newdata['mid'] = $member_ids_map[$newdata['mid']];
				$newdata['bid'] = $business_ids_map[$newdata['bid']];
				$newdata['freightid'] = $shop_freight_ids_map[$newdata['freightid']];
				$newdata['code'] = random(16);
				$shop_order_ids_map[$oldid] = db('shop_order')->insertGetId($newdata);
			}
			$data = Db::query('select * from '.table_name('shop_order_goods').' where aid='.$fromid);
			db('shop_order_goods')->where(['aid'=>$toid])->delete();
			$shop_order_goods_ids_map = [];
			foreach($data as $newdata){
				$oldid = $newdata['id'];
				$newdata['id'] = '';
				$newdata['aid'] = $toid;
				$newdata['mid'] = $member_ids_map[$newdata['mid']];
				$newdata['orderid'] = $shop_order_ids_map[$newdata['orderid']];
				$newdata['proid'] = $shop_product_ids_map[$newdata['proid']];
				$newdata['ggid'] = $shop_guige_ids_map[$newdata['ggid']];
				$newdata['bid'] = $business_ids_map[$newdata['bid']];
				$shop_order_goods_ids_map[$oldid] = db('shop_order_goods')->insertGetId($newdata);
			}


			$data = Db::query('select * from '.table_name('business_shop_category').' where aid='.$fromid);
			db('business_shop_category')->where(['aid'=>$toid])->delete();
			$business_shop_category_ids_map = [];
			$business_shop_category_ids_map['0'] = '0';
			foreach($data as $newdata){
				$oldid = $newdata['id'];
				$newdata['id'] = '';
				$newdata['aid'] = $toid;
				$newdata['bid'] = $business_ids_map[$newdata['bid']];
				$newdata['pid'] = $business_shop_category_ids_map[$newdata['pid']];
				$business_shop_category_ids_map[$oldid] = db('business_shop_category')->insertGetId($newdata);
			}
			$data = Db::query('select * from '.table_name('business_sysset').' where aid='.$fromid);
			db('business_sysset')->where(['aid'=>$toid])->delete();
			foreach($data as $newdata){
				$newdata['id'] = '';
				$newdata['aid'] = $toid;
				db('business_sysset')->insertGetId($newdata);
			}
			$data = Db::query('select * from '.table_name('business_user').' where aid='.$fromid);
			db('business_user')->where(['aid'=>$toid])->delete();
			$business_user_ids_map = [];
			foreach($data as $newdata){
				$oldid = $newdata['id'];
				$newdata['id'] = '';
				$newdata['aid'] = $toid;
				$newdata['bid'] = $business_ids_map[$newdata['bid']];
				$business_user_ids_map[$oldid] = db('business_user')->insertGetId($newdata);
			}
			$data = Db::query('select * from '.table_name('business_moneylog').' where aid='.$fromid);
			db('business_moneylog')->where(['aid'=>$toid])->delete();
			foreach($data as $newdata){
				$newdata['id'] = '';
				$newdata['aid'] = $toid;
				$newdata['bid'] = $business_ids_map[$newdata['bid']];
				db('business_moneylog')->insertGetId($newdata);
			}
			$data = Db::query('select * from '.table_name('business_withdrawlog').' where aid='.$fromid);
			db('business_withdrawlog')->where(['aid'=>$toid])->delete();
			foreach($data as $newdata){
				$newdata['id'] = '';
				$newdata['aid'] = $toid;
				$newdata['bid'] = $business_ids_map[$newdata['bid']];
				db('business_withdrawlog')->insertGetId($newdata);
			}
			$data = Db::query('select * from '.table_name('business_hexiao_order').' where aid='.$fromid);
			db('business_hexiao_order')->where(['aid'=>$toid])->delete();
			foreach($data as $newdata){
				$newdata['id'] = '';
				$newdata['aid'] = $toid;
				$newdata['bid'] = $business_ids_map[$newdata['bid']];
				$newdata['buid'] = $business_user_ids_map[$newdata['buid']];
				$newdata['mid'] = $member_ids_map[$newdata['mid']];
				$newdata['orderid'] = $shop_order_ids_map[$newdata['orderid']];
				db('business_hexiao_order')->insertGetId($newdata);
			}

			$data = Db::query('select * from '.table_name('shop_comment').' where aid='.$fromid);
			db('shop_comment')->where(['aid'=>$toid])->delete();
			foreach($data as $newdata){
				$newdata['id'] = '';
				$newdata['aid'] = $toid;
				$newdata['mid'] = $member_ids_map[$newdata['mid']];
				$newdata['orderid'] = $shop_order_ids_map[$newdata['orderid']];
				$newdata['ogid'] = $shop_order_goods_ids_map[$newdata['ogid']];
				$newdata['proid'] = $shop_product_ids_map[$newdata['proid']];
				$newdata['ggid'] = $shop_guige_ids_map[$newdata['ggid']];
				$newdata['bid'] = $business_ids_map[$newdata['bid']];
				db('shop_comment')->insertGetId($newdata);
			}
			
			$data = Db::query('select * from '.table_name('collage_sysset').' where aid='.$fromid);
			db('collage_sysset')->where(['aid'=>$toid])->delete();
			foreach($data as $newdata){
				$newdata['id'] = '';
				$newdata['aid'] = $toid;
				db('collage_sysset')->insertGetId($newdata);
			}
			$data = Db::query('select * from '.table_name('collage_category').' where aid='.$fromid);
			db('collage_category')->where(['aid'=>$toid])->delete();
			$collage_category_ids_map = [];
			$collage_category_ids_map['0'] = '0';
			foreach($data as $newdata){
				$oldid = $newdata['id'];
				$newdata['id'] = '';
				$newdata['aid'] = $toid;
				$collage_category_ids_map[$oldid] = db('collage_category')->insertGetId($newdata);
			}

			$data = Db::query('select * from '.table_name('collage_freight').' where aid='.$fromid);
			db('collage_freight')->where(['aid'=>$toid])->delete();
			$collage_freight_ids_map = [];
			foreach($data as $newdata){
				$oldid = $newdata['id'];
				$newdata['id'] = '';
				$newdata['aid'] = $toid;
				$collage_freight_ids_map[$oldid] = db('collage_freight')->insertGetId($newdata);
			}
			$data = Db::query('select * from '.table_name('collage_product').' where aid='.$fromid);
			db('collage_product')->where(['aid'=>$toid])->delete();
			$collage_product_ids_map = [];
			foreach($data as $newdata){
				$oldid = $newdata['id'];
				$newdata['id'] = '';
				$newdata['aid'] = $toid;
				$newdata['cid'] = $collage_category_ids_map[$newdata['cid']];
				$collage_product_ids_map[$oldid] = db('collage_product')->insertGetId($newdata);
			}
			$data = Db::query('select * from '.table_name('collage_guige').' where aid='.$fromid);
			db('collage_guige')->where(['aid'=>$toid])->delete();
			$collage_guige_ids_map = [];
			foreach($data as $newdata){
				$oldid = $newdata['id'];
				$newdata['id'] = '';
				$newdata['aid'] = $toid;
				$newdata['proid'] = $collage_product_ids_map[$newdata['proid']];
				$collage_guige_ids_map[$oldid] = db('collage_guige')->insertGetId($newdata);
			}
			
			$data = Db::query('select * from '.table_name('collage_order_team').' where aid='.$fromid);
			db('collage_order_team')->where(['aid'=>$toid])->delete();
			$collage_order_team_ids_map = [];
			foreach($data as $newdata){
				$oldid = $newdata['id'];
				$newdata['id'] = '';
				$newdata['aid'] = $toid;
				$newdata['mid'] = $member_ids_map[$newdata['mid']];
				$newdata['bid'] = $business_ids_map[$newdata['bid']];
				$newdata['proid'] = $collage_product_ids_map[$newdata['proid']];
				$collage_order_team_ids_map[$oldid] = db('collage_order_team')->insertGetId($newdata);
			}

			$data = Db::query('select * from '.table_name('collage_order').' where aid='.$fromid);
			db('collage_order')->where(['aid'=>$toid])->delete();
			$collage_order_ids_map = [];
			foreach($data as $newdata){
				$oldid = $newdata['id'];
				$newdata['id'] = '';
				$newdata['aid'] = $toid;
				$newdata['mid'] = $member_ids_map[$newdata['mid']];
				$newdata['bid'] = $business_ids_map[$newdata['bid']];
				$newdata['teamid'] = $collage_order_team_ids_map[$newdata['teamid']];
				$newdata['code'] = random(16);
				$collage_order_ids_map[$oldid] = db('collage_order')->insertGetId($newdata);
			}
			$data = Db::query('select * from '.table_name('collage_comment').' where aid='.$fromid);
			db('collage_comment')->where(['aid'=>$toid])->delete();
			foreach($data as $newdata){
				$newdata['id'] = '';
				$newdata['aid'] = $toid;
				$newdata['mid'] = $member_ids_map[$newdata['mid']];
				$newdata['orderid'] = $collage_order_ids_map[$newdata['orderid']];
				$newdata['proid'] = $collage_product_ids_map[$newdata['proid']];
				$newdata['ggid'] = $collage_guige_ids_map[$newdata['ggid']];
				$newdata['bid'] = $business_ids_map[$newdata['bid']];
				db('collage_comment')->insertGetId($newdata);
			}
			

			$data = Db::query('select * from '.table_name('coupon').' where aid='.$fromid);
			db('coupon')->where(['aid'=>$toid])->delete();
			$coupon_ids_map = [];
			foreach($data as $newdata){
				$oldid = $newdata['id'];
				$newdata['id'] = '';
				$newdata['aid'] = $toid;
				$newdata['bid'] = $business_ids_map[$newdata['bid']];
				$coupon_ids_map[$oldid] = db('coupon')->insertGetId($newdata);
			}

			$data = Db::query('select * from '.table_name('coupon_record').' where aid='.$fromid);
			db('coupon_record')->where(['aid'=>$toid])->delete();
			$coupon_record_ids_map = [];
			foreach($data as $newdata){
				$oldid = $newdata['id'];
				$newdata['id'] = '';
				$newdata['aid'] = $toid;
				$newdata['mid'] = $member_ids_map[$newdata['mid']];
				$newdata['couponid'] = $coupon_ids_map[$newdata['couponid']];
				$coupon_record_ids_map[$oldid] = db('coupon_record')->insertGetId($newdata);
			}
			$data = Db::query('select * from '.table_name('designerpage').' where aid='.$fromid);
			db('designerpage')->where(['aid'=>$toid])->delete();
			foreach($data as $newdata){
				$oldid = $newdata['id'];
				$newdata['id'] = '';
				$newdata['aid'] = $toid;
				db('designerpage')->insertGetId($newdata);
			}
			
			\app\commons\System::plog('数据迁移',1);
			return ['status'=>1,'msg'=>'迁移完成'];
			dump($admin_set);die;
		}
		$userlist = db('admin_user')->where(['isadmin'=>['gt',0]])->order('id desc')->select();
		View::assign('userlist',$userlist);
		return View::fetch();
	}
}