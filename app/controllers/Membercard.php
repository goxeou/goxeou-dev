<?php


// +----------------------------------------------------------------------
// | 会员卡
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;
use think\facade\Log;

class Membercard extends Common
{
    public function initialize(){
		parent::initialize();
		if(bid > 0) showmsg('无访问权限');
	}
	//会员卡列表
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
			$count = 0 + Db::name('membercard')->where($where)->count();
			$data = Db::name('membercard')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			foreach($data as $k=>$v){
				if($v && !$v['status']) {
					$access_token = \app\commons\Wechat::access_token(aid,'mp');
					$url='https://api.weixin.qq.com/card/get?access_token='.$access_token;
					$result = request_post($url,jsonEncode(['card_id'=>$v['card_id']]));
					$res = json_decode($result);
					if($res->errcode == 0) {
						if($res->card->member_card->base_info->status == 'CARD_STATUS_VERIFY_OK') { //审核通过
							Db::name('membercard')->where('id',$v['id'])->update(['status'=>1]);
							$data[$k]['status'] = 1;
						}elseif($res->card->member_card->base_info->status == 'CARD_STATUS_NOT_VERIFY'){ //审核中
							Db::name('membercard')->where('id',$v['id'])->update(['status'=>0]);
							$data[$k]['status'] = 0;
						}elseif($res->card->member_card->base_info->status == 'CARD_STATUS_VERIFY_FAIL'){ //审核失败
							Db::name('membercard')->where('id',$v['id'])->update(['status'=>2]);
							$data[$k]['status'] = 2;
						}elseif($res->card->member_card->base_info->status == 'CARD_STATUS_DELETE'){ //被商户删除
							Db::name('membercard')->where('id',$v['id'])->update(['status'=>3]);
							$data[$k]['status'] = 3;
						}
					}
				}
				$data[$k]['membercount'] = Db::name('membercard_record')->where('aid',aid)->where('card_id',$v['card_id'])->count();
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}

        if(getcustom('membercard_sendscore2')){
            if($this->auth_data == 'all' || in_array('Membercard/sendscore2',$this->auth_data))
                $auth['sendscore2']=true;
        }
        View::assign('auth',$auth);
		return View::fetch();
    }
	public function edit(){
		if(input('param.id')){
			$info = Db::name('membercard')->where('aid',aid)->where('id',input('param.id/d'))->find();
			if($info && !$info['status']) {
				$access_token = \app\commons\Wechat::access_token(aid,'mp');
				$url='https://api.weixin.qq.com/card/get?access_token='.$access_token;
				$result = request_post($url,jsonEncode(['card_id'=>$info['card_id']]));
				$res = json_decode($result);
				if($res->errcode == 0) {
					if($res->card->member_card->base_info->status == 'CARD_STATUS_VERIFY_OK') { //审核通过
						Db::name('membercard')->where('id',$info['id'])->update(['status'=>1]);
					}elseif($res->card->member_card->base_info->status == 'CARD_STATUS_NOT_VERIFY'){ //审核中
						Db::name('membercard')->where('id',$info['id'])->update(['status'=>0]);
					}elseif($res->card->member_card->base_info->status == 'CARD_STATUS_VERIFY_FAIL'){ //审核失败
						Db::name('membercard')->where('id',$info['id'])->update(['status'=>2]);
					}elseif($res->card->member_card->base_info->status == 'CARD_STATUS_DELETE'){ //被商户删除
						Db::name('membercard')->where('id',$info['id'])->update(['status'=>3]);
					}
				}
			}
			$info['field_list'] = json_decode($info['field_list'],true);
			if(!$info['code_type']) $info['code_type'] = 'CODE_TYPE_TEXT';
		}else{
			//默认设置
			$set = Db::name('admin_set')->where('aid',aid)->find();
			$info = [
				'id'=>'',
				'bg_type'=>0,	//卡券封面 0颜色 1图片
				'color'=>'#63b359',
				'background_pic_url'=>PRE_URL.'/static/imgsrc/membercardbg.jpg',
				'logo_url'=>$set['logo'],
				'brand_name'=>$set['name'],
				'code_type'=>'CODE_TYPE_TEXT',
				'title'=>'微信会员卡',
				'custom_field'=>jsonEncode(['积分',t('余额'),'等级']),
				'center_title'=>'快速买单',
				'center_sub_title'=>'买单即享优惠',
				'center_url'=>m_url('pages/maidan/pay'),

				'custom_url_name'=>'微信商城',
				'custom_url_sub_title'=>'点击进入',
				'custom_url'=>m_url('pages/index/index'),
				
				'promotion_url_name'=>t('积分').'兑换',
				'promotion_url_sub_title'=>'点击进入',
				'promotion_url'=>m_url('activity/scoreshop/index'),
					
				'custom_cell1_name'=>'个人中心',
				'custom_cell1_tips'=>'点击进入',
				'custom_cell1_url'=>m_url('pages/my/usercenter'),

				'notice'=>'到店出示会员卡',
				'description'=>'每人限领1张，仅限本人使用',
				'prerogative'=>'持卡消费送积分，参与丰富的会员专享活动',
				'bonus_rules'=>'每消费1元即可获得1积分，每1积分即可抵扣0.01元',
				'date_info_type'=>'DATE_TYPE_PERMANENT',
				'field_list'=>[
					'name'=>['isshow'=>'1','name'=>'姓名','required'=>'1'],
					'mobile'=>['isshow'=>'1','name'=>'手机号','required'=>'1'],
					'sex' =>['name'=>'性别'],
					'birthday' =>['name'=>'生日'],
					'email' =>['name'=>'邮箱'],
					'idcard'=>['name'=>'身份证'],
					'education' =>['name'=>'学历'],
					'industry' =>['name'=>'行业'],
					'income' =>['name'=>'年收入'],
					'habit' =>['name'=>'爱好'],
					'location' =>['name'=>'地址'],
					'field1' =>['name'=>''],
					'field2' =>['name'=>''],
					'field3' =>['name'=>''],
					'field4' =>['name'=>''],
					'field5' =>['name'=>''],
				],
				'custom_field_customize1_name'=>'自定义1',
				'custom_field_customize1_value'=>'查看',
				'custom_field_customize1_link'=>'',
				'custom_field_customize2_name'=>'自定义2',
				'custom_field_customize2_value'=>'查看',
				'custom_field_customize2_link'=>'',
			];
		}
		//$info['bgcolor'] = $this->getcolor($info['color']);
		$info['custom_field'] = json_decode($info['custom_field'],true);
		$defaultlv = Db::name('member_level')->where('aid',aid)->where('isdefault',1)->find();
		View::assign('info',$info);
		View::assign('defaultlv',$defaultlv);
		return View::fetch();
    }
    public function save(){
        $appinfo = \app\commons\System::appinfo(aid,'mp');
        if(empty($appinfo['appid'])){
            return json(['status'=>0, 'msg'=>'请先绑定公众号']);
        }
		set_time_limit(0);
		$info = input('post.info/a');
		$field_list = input('post.field_list/a');
		$info['field_list'] = jsonEncode($field_list);
		//var_export(input('post.field_list/a'));
		//dump(input('post.field_list/a'));
		//dump($info);die;
		$info['custom_field'] = jsonEncode($info['custom_field']);
		$info['card_type'] = 'MEMBER_CARD';
		$access_token = \app\commons\Wechat::access_token(aid,'mp');
		if($info['id']){
			$oldhyk =  Db::name('membercard')->where('aid',aid)->where('id',$info['id'])->find();
			$oldhyk['custom_field'] = json_decode($oldhyk['custom_field'],true);
			// 修改会员卡信息
			Db::name('membercard')->where('aid',aid)->where('id',$info['id'])->update($info);
			$newhyk = Db::name('membercard')->where('aid',aid)->where('id',$info['id'])->find();
			$newhyk['custom_field'] = json_decode($newhyk['custom_field'],true);
				
			$data = array();
			$data['card_id'] = $newhyk['card_id'];
			$member_card = array();
			$base_info = array();
			//背景图 背景色
			if($newhyk['color']!=$oldhyk['color']) $base_info['color'] = $this->getcolor($newhyk['color']);
			if($newhyk['bg_type']==1){
				if($newhyk['background_pic_url']!=$oldhyk['background_pic_url']) $member_card['background_pic_url'] = $this->getwxpic($newhyk['background_pic_url'],aid);
			}
			//logo图
			$base_info['logo_url'] = $this->getwxpic($newhyk['logo_url'],aid);
			// Code展示类型， "CODE_TYPE_TEXT" 文本 "CODE_TYPE_BARCODE" 一维码 "CODE_TYPE_QRCODE" 二维码 "CODE_TYPE_ONLY_QRCODE" 仅显示二维码 "CODE_TYPE_ONLY_BARCODE" 仅显示一维码 "CODE_TYPE_NONE" 不显示任何码型

			if($newhyk['code_type']!=$oldhyk['code_type']){
				$base_info['code_type'] = $newhyk['code_type'];
				if($base_info['code_type'] !='CODE_TYPE_TEXT'){
					$base_info['is_pay_and_qrcode'] = true;
				}else{
					$base_info['is_pay_and_qrcode'] = false;
				}
			}
			if(getcustom('membercard_custom')){
				$base_info['is_pay_and_qrcode'] = false;
			}
			
			// 卡券名，字数上限为9个汉字
			if($newhyk['title']!=$oldhyk['title']) $base_info['title'] = $newhyk['title'];

			// 是否支持积分
			if(in_array('积分',$newhyk['custom_field'])){
				$supply_bonus = true;
				//设置跳转外链查看积分详情
				//$member_card['bonus_url'] = '';
				// 积分规则
				if($newhyk['bonus_rules']){
					$member_card['bonus_rules'] = $newhyk['bonus_rules'];
				}
			}else{
				$supply_bonus = false;
			}
			if(in_array('积分',$oldhyk['custom_field'])){
				$old_supply_bonus = true;
			}else{
				$old_supply_bonus = false;
			}
			//判断是否修改 修改积分需要审核
			if($supply_bonus != $old_supply_bonus){
				$member_card['supply_bonus'] = $supply_bonus;
			}
			//name_type FIELD_NAME_TYPE_LEVEL 等级 FIELD_NAME_TYPE_COUPON 优惠券 FIELD_NAME_TYPE_STAMP 印花 FIELD_NAME_TYPE_DISCOUNT 折扣 FIELD_NAME_TYPE_ACHIEVEMEN 成就 FIELD_NAME_TYPE_MILEAGE 里程 FIELD_NAME_TYPE_SET_POINTS 集点 FIELD_NAME_TYPE_TIMS 次数
			// 上面三个导航
			if($newhyk['custom_field'] !== $oldhyk['custom_field'] || (in_array('自定义1',$newhyk['custom_field']) && ($newhyk['custom_field_customize1_name'] != $oldhyk['custom_field_customize1_name'] || $newhyk['custom_field_customize1_value'] != $oldhyk['custom_field_customize1_value'] || $newhyk['custom_field_customize1_link'] != $oldhyk['custom_field_customize1_link'])) || (in_array('自定义2',$newhyk['custom_field']) && ($newhyk['custom_field_customize2_name'] != $oldhyk['custom_field_customize2_name'] || $newhyk['custom_field_customize2_value'] != $oldhyk['custom_field_customize2_value'] || $newhyk['custom_field_customize2_link'] != $oldhyk['custom_field_customize2_link']))){
				$k = 1;
				foreach($newhyk['custom_field'] as $item){
					if($item!='积分'){
						if($item == '余额' || $item == t('余额')){
							$member_card['custom_field'.$k] = array(
								'name'=>t('余额'),
								'name_type'=>'',
								'url'=>m_url('/pagesExt/money/recharge'),
//                                'app_brand_user_name' => 'gh_dc4cdb6d9704@app',
//                                'app_brand_pass' => 'pages/index/index',
							);
						}
						if($item == '优惠券' || $item == t('优惠券')){
							$member_card['custom_field'.$k] = array(
								//'name'=>t('优惠券'),
								'name_type'=>'FIELD_NAME_TYPE_COUPON',
								'url'=>m_url('/pagesExt/coupon/mycoupon'),
							);
						}
						if($item == '等级'){
							$member_card['custom_field'.$k] = array(
								//'name'=>'等级',
								'name_type'=>'FIELD_NAME_TYPE_LEVEL',
								'url'=>m_url('/pagesExt/my/levelinfo'),
							);
						}
						if($item == '自定义1'){
							$member_card['custom_field'.$k] = array(
								'name'=>$newhyk['custom_field_customize1_name'],
								'url'=>$newhyk['custom_field_customize1_link'],
							);
							if(strpos($newhyk['custom_field_customize1_link'],'miniProgram::') === 0){
								$wxappurls = explode('|',str_replace('miniProgram::','',$newhyk['custom_field_customize1_link']));
								$member_card['custom_field'.$k]['app_brand_user_name'] = trim($wxappurls[2]).'@app';
								$member_card['custom_field'.$k]['app_brand_pass'] = trim($wxappurls[1],'/');
							}else{
								$member_card['custom_field'.$k]['app_brand_user_name'] = '';
								$member_card['custom_field'.$k]['app_brand_pass'] = '';
							}
						}
						if($item == '自定义2'){
							$member_card['custom_field'.$k] = array(
								'name'=>$newhyk['custom_field_customize2_name'],
								'url'=>$newhyk['custom_field_customize2_link'],
							);
							if(strpos($newhyk['custom_field_customize2_link'],'miniProgram::') === 0){
								$wxappurls = explode('|',str_replace('miniProgram::','',$newhyk['custom_field_customize2_link']));
								$member_card['custom_field'.$k]['app_brand_user_name'] = trim($wxappurls[2]).'@app';
								$member_card['custom_field'.$k]['app_brand_pass'] = trim($wxappurls[1],'/');
							}else{
								$member_card['custom_field'.$k]['app_brand_user_name'] = '';
								$member_card['custom_field'.$k]['app_brand_pass'] = '';
							}
						}
						$k++;
					}
				}
				if($k<4){
					for($i=$k;$i<4;$i++){
						$member_card['custom_field'.$i] = ['name'=>'','name_type'=>'','url'=>''];
					}
				}
			}
			//$base_info['pay_info'] = ['swipe_card'=>['is_swipe_card'=>true]];

			// 中部按钮 有名称就显示
			$base_info['center_title'] = $newhyk['center_title'];
			$base_info['center_sub_title'] = $newhyk['center_sub_title'];
			$base_info['center_url'] = $newhyk['center_url'];
			if(strpos($newhyk['center_url'],'miniProgram::') === 0){
				$wxappurls = explode('|',str_replace('miniProgram::','',$newhyk['center_url']));
				$base_info['center_app_brand_user_name'] = trim($wxappurls[2]).'@app';
				$base_info['center_app_brand_pass'] = trim($wxappurls[1],'/');
			}else{
				$base_info['center_app_brand_user_name'] = '';
				$base_info['center_app_brand_pass'] = '';
			}

			// 自定义入口1 名称+链接才显示
			$base_info['custom_url_name'] = $newhyk['custom_url_name'];
			$base_info['custom_url_sub_title'] = $newhyk['custom_url_sub_title'];
			$base_info['custom_url'] = trim($newhyk['custom_url']);
			if(strpos($newhyk['custom_url'],'miniProgram::') === 0){
				$wxappurls = explode('|',str_replace('miniProgram::','',$newhyk['custom_url']));
				$base_info['custom_app_brand_user_name'] = trim($wxappurls[2]).'@app';
				$base_info['custom_app_brand_pass'] = trim($wxappurls[1],'/');
			}else{
				$base_info['custom_app_brand_user_name'] = '';
				$base_info['custom_app_brand_pass'] = '';
			}

			// 自定义入口2 有链接就显示
			$member_card['custom_cell1'] = [
				'name'=>$newhyk['custom_cell1_name'],
				'tips'=>$newhyk['custom_cell1_tips'],
				'url'=>$newhyk['custom_cell1_url']
			];
			if(strpos($newhyk['custom_cell1_url'],'miniProgram::') === 0){
				$wxappurls = explode('|',str_replace('miniProgram::','',$newhyk['custom_cell1_url']));
				$member_card['custom_cell1']['app_brand_user_name'] = trim($wxappurls[2]).'@app';
				$member_card['custom_cell1']['app_brand_pass'] = trim($wxappurls[1],'/');
			}else{
				$member_card['custom_cell1']['app_brand_user_name'] = '';
				$member_card['custom_cell1']['app_brand_pass'] = '';
			}
			
			// 自定义入口3 名称+链接才显示
			$base_info['promotion_url_name'] = $newhyk['promotion_url_name'];
			$base_info['promotion_url_sub_title'] = $newhyk['promotion_url_sub_title'];
			$base_info['promotion_url'] = trim($newhyk['promotion_url']);
			if(strpos($newhyk['promotion_url'],'miniProgram::') === 0){
				$wxappurls = explode('|',str_replace('miniProgram::','',$newhyk['promotion_url']));
				$base_info['promotion_app_brand_user_name'] = trim($wxappurls[2]).'@app';
				$base_info['promotion_app_brand_pass'] = trim($wxappurls[1],'/');
			}else{
				$base_info['promotion_app_brand_user_name'] = '';
				$base_info['promotion_app_brand_pass'] = '';
			}

			// 卡券使用提醒，字数上限为16个汉字
			if($newhyk['notice']!=$oldhyk['notice']) $base_info['notice'] = $newhyk['notice'];
			// 卡券使用说明，字数上限为1024个汉字。
			if($newhyk['description']!=$oldhyk['description']) $base_info['description'] = $newhyk['description'];
			// 会员卡特权说明
			if($newhyk['prerogative']!=$oldhyk['prerogative']) $member_card['prerogative'] = $newhyk['prerogative'];
			// 客服电话
			if($newhyk['service_phone']!=$oldhyk['service_phone']) $base_info['service_phone'] = $newhyk['service_phone'];
			// 默认是永久有效 使用日期，有效期的信息。
			$base_info['date_info'] = array(
				'type'=>$newhyk['date_info_type'],
				'begin_timestamp'=>strtotime($newhyk['date_info_begin_time']),
				'end_timestamp'=>strtotime($newhyk['date_info_end_time']),
				'fixed_term'=>$newhyk['date_info_fixed_term'],
				'fixed_begin_term'=>$newhyk['date_info_fixed_begin_term'],
			);
			//支持微信支付刷卡
			//$base_info['pay_info'] = ['swipe_card'=>['is_swipe_card'=>true]];
			
			$member_card['base_info'] = $base_info;
			$data['member_card'] = $member_card;
			//return json(['status'=>0,'msg'=>'test','data'=>$data]);

			$access_token = \app\commons\Wechat::access_token(aid,'mp');
			$url  = 'https://api.weixin.qq.com/card/update?access_token=' . $access_token;
			$result = request_post($url, jsonEncode($data));
			$rs     = json_decode($result);
			//Log::write('修改会员卡信息返回值: '.$result);
			if($rs->errcode == 0) {
				if($rs->send_check) {
					Db::name('membercard')->where('aid',aid)->where('id',$info['id'])->update(['status'=>0,'RefuseReason'=>'']);
				}
				//激活会员卡设置
				if($newhyk['field_list']!=$oldhyk['field_list']){
					$field_list = json_decode($newhyk['field_list'],true);
					$required_form = ['can_modify'=>false,'common_field_id_list'=>[],'custom_field_list'=>[]];
					$optional_form = ['can_modify'=>false,'common_field_id_list'=>[],'custom_field_list'=>[]];
					foreach($field_list as $k=>$v){
						if($v['isshow']==1){
							if($v['required']==1){
								if($k=='name'){
									$required_form['common_field_id_list'][] = 'USER_FORM_INFO_FLAG_NAME';
								}elseif($k=='mobile'){
									$required_form['common_field_id_list'][] = 'USER_FORM_INFO_FLAG_MOBILE';
								}elseif($k=='sex'){
									$required_form['common_field_id_list'][] = 'USER_FORM_INFO_FLAG_SEX';
								}elseif($k=='idcard'){
									$required_form['common_field_id_list'][] = 'USER_FORM_INFO_FLAG_IDCARD';
								}elseif($k=='birthday'){
									$required_form['common_field_id_list'][] = 'USER_FORM_INFO_FLAG_BIRTHDAY';
								}elseif($k=='email'){
									$required_form['common_field_id_list'][] = 'USER_FORM_INFO_FLAG_EMAIL';
								}elseif($k=='location'){
									$required_form['common_field_id_list'][] = 'USER_FORM_INFO_FLAG_LOCATION';
								}elseif($k=='education'){
									$required_form['common_field_id_list'][] = 'USER_FORM_INFO_FLAG_EDUCATION_BACKGROUND';
								}elseif($k=='industry'){
									$required_form['common_field_id_list'][] = 'USER_FORM_INFO_FLAG_INDUSTRY';
								}elseif($k=='income'){
									$required_form['common_field_id_list'][] = 'USER_FORM_INFO_FLAG_INCOME';
								}elseif($k=='habit'){
									$required_form['common_field_id_list'][] = 'USER_FORM_INFO_FLAG_HABIT';
								}else{
									$required_form['custom_field_list'][] = $v['name'];
								}
							}else{
								if($k=='name'){
									$optional_form['common_field_id_list'][] = 'USER_FORM_INFO_FLAG_NAME';
								}elseif($k=='mobile'){
									$optional_form['common_field_id_list'][] = 'USER_FORM_INFO_FLAG_MOBILE';
								}elseif($k=='sex'){
									$optional_form['common_field_id_list'][] = 'USER_FORM_INFO_FLAG_SEX';
								}elseif($k=='idcard'){
									$optional_form['common_field_id_list'][] = 'USER_FORM_INFO_FLAG_IDCARD';
								}elseif($k=='birthday'){
									$optional_form['common_field_id_list'][] = 'USER_FORM_INFO_FLAG_BIRTHDAY';
								}elseif($k=='email'){
									$optional_form['common_field_id_list'][] = 'USER_FORM_INFO_FLAG_EMAIL';
								}elseif($k=='location'){
									$optional_form['common_field_id_list'][] = 'USER_FORM_INFO_FLAG_LOCATION';
								}elseif($k=='education'){
									$optional_form['common_field_id_list'][] = 'USER_FORM_INFO_FLAG_EDUCATION_BACKGROUND';
								}elseif($k=='industry'){
									$optional_form['common_field_id_list'][] = 'USER_FORM_INFO_FLAG_INDUSTRY';
								}elseif($k=='income'){
									$optional_form['common_field_id_list'][] = 'USER_FORM_INFO_FLAG_INCOME';
								}elseif($k=='habit'){
									$optional_form['common_field_id_list'][] = 'USER_FORM_INFO_FLAG_HABIT';
								}else{
									$optional_form['custom_field_list'][] = $v['name'];
								}
							}
						}
					}
					$jhdata = array();
					$jhdata['card_id'] = $newhyk['card_id'];
					$jhdata['required_form'] = $required_form;
					$jhdata['optional_form'] = $optional_form;
					$url = 'https://api.weixin.qq.com/card/membercard/activateuserform/set?access_token='.$access_token;
					$result = request_post($url,jsonEncode($jhdata));
				}
				
				//修改了类目 更新已领取的会员卡类目信息
				if($newhyk['custom_field'] !== $oldhyk['custom_field']){
					//dump($member_card);
					$custom_field1 = $member_card['custom_field1'];
					$custom_field2 = $member_card['custom_field2'];
					$custom_field3 = $member_card['custom_field3'];
					$url = 'https://api.weixin.qq.com/card/membercard/updateuser?access_token='.$access_token;
					$memberlist = Db::name('member')->where('aid',aid)->where('card_id',$newhyk['card_id'])->select()->toArray();
					foreach($memberlist as $member){
						$postdata = [];
						$postdata['card_id'] = $member['card_id'];
						$postdata['code'] = $member['card_code'];
						$postdata['bonus'] = $member['score'];
						if($custom_field1){
							if($custom_field1['name_type']=='FIELD_NAME_TYPE_COUPON'){//优惠券
								$couponcount = Db::name('coupon_record')->where('aid',aid)->where('mid',$member['id'])->where('status',0)->where('endtime','>=',time())->count();
								$postdata['custom_field_value1'] = $couponcount;
							}elseif($custom_field1['name_type']=='FIELD_NAME_TYPE_LEVEL'){//等级
								$memberlv = Db::name('member_level')->where('aid',aid)->where('id',$member['levelid'])->find();
								$postdata['custom_field_value1'] = $memberlv['name'];
							}elseif($custom_field1['name']=='余额' || $custom_field1['name']==t('余额')){//余额
								$postdata['custom_field_value1'] = $member['money'];
							}elseif($custom_field1['name']==$newhyk['custom_field_customize1_name']){
								$postdata['custom_field_value1'] = $newhyk['custom_field_customize1_value'];
							}elseif($custom_field1['name']==$newhyk['custom_field_customize2_name']){
								$postdata['custom_field_value1'] = $newhyk['custom_field_customize2_value'];
							}else{
								$postdata['custom_field_value1'] = '查看';
							}
						}
						if($custom_field2){
							if($custom_field2['name_type']=='FIELD_NAME_TYPE_COUPON'){//优惠券
								$couponcount = Db::name('coupon_record')->where('aid',aid)->where('mid',$member['id'])->where('status',0)->where('endtime','>=',time())->count();
								$postdata['custom_field_value2'] = $couponcount;
							}elseif($custom_field2['name_type']=='FIELD_NAME_TYPE_LEVEL'){//等级
								$memberlv = Db::name('member_level')->where('aid',aid)->where('id',$member['levelid'])->find();
								$postdata['custom_field_value2'] = $memberlv['name'];
							}elseif($custom_field2['name']=='余额' || $custom_field2['name']==t('余额')){//余额
								$postdata['custom_field_value2'] = $member['money'];
							}elseif($custom_field2['name']==$newhyk['custom_field_customize1_name']){
								$postdata['custom_field_value2'] = $newhyk['custom_field_customize1_value'];
							}elseif($custom_field2['name']==$newhyk['custom_field_customize2_name']){
								$postdata['custom_field_value2'] = $newhyk['custom_field_customize2_value'];
							}else{
								$postdata['custom_field_value2'] = '查看';
							}
						}
						if($custom_field3){
							if($custom_field3['name_type']=='FIELD_NAME_TYPE_COUPON'){//优惠券
								$couponcount = Db::name('coupon_record')->where('aid',aid)->where('mid',$member['id'])->where('status',0)->where('endtime','>=',time())->count();
								$postdata['custom_field_value3'] = $couponcount;
							}elseif($custom_field3['name_type']=='FIELD_NAME_TYPE_LEVEL'){//等级
								$memberlv = Db::name('member_level')->where('aid',aid)->where('id',$member['levelid'])->find();
								$postdata['custom_field_value3'] = $memberlv['name'];
							}elseif($custom_field3['name']=='余额' || $custom_field3['name']==t('余额')){//余额
								$postdata['custom_field_value3'] = $member['money'];
							}elseif($custom_field3['name']==$newhyk['custom_field_customize1_name']){
								$postdata['custom_field_value3'] = $newhyk['custom_field_customize1_value'];
							}elseif($custom_field3['name']==$newhyk['custom_field_customize2_name']){
								$postdata['custom_field_value3'] = $newhyk['custom_field_customize2_value'];
							}else{
								$postdata['custom_field_value3'] = '查看';
							}
						}
						request_post($url,jsonEncode($postdata));
					}
				}
				//修改库存了
				if($newhyk['quantity'] != $oldhyk['quantity']) {
					$cdata            = array();
					$cdata['card_id'] = $newhyk['card_id'];
					if($newhyk['quantity'] > $oldhyk['quantity']) {
						$cdata['increase_stock_value'] = $newhyk['quantity'] - $oldhyk['quantity'];
					}else{
						$cdata['reduce_stock_value'] = $oldhyk['quantity'] - $newhyk['quantity'];
					}
					$url = 'https://api.weixin.qq.com/card/modifystock?access_token=' . $access_token;
					$crs = request_post($url, jsonEncode($data));
					$crs = json_decode($crs);
					if($crs->errcode == 0){
						\app\commons\System::plog('修改会员卡'.$info['id']);
						return json(['status'=>1,'msg'=>'修改成功','url'=>(string)url('index')]);
					}else{
						return json(['status'=> 0,'msg'=>\app\commons\Wechat::geterror($rs)]);
					}
				}else{
					\app\commons\System::plog('修改会员卡'.$info['id']);
					return json(['status'=>1,'msg'=>'修改成功','url'=>(string)url('index'),'data'=>$data]);
				}
			}else{
				return json(['status'=> 0,'msg'=>\app\commons\Wechat::geterror($rs)]);
			}
		}else{// 添加会员卡
			$info['aid'] = aid;
			$info['createtime'] = time();
			$hyk_id = Db::name('membercard')->insertGetId($info);
			$newhyk = Db::name('membercard')->where('aid',aid)->where('id',$hyk_id)->find();
			$newhyk['custom_field'] = json_decode($newhyk['custom_field'],true);

			$data = array();
			$data['card_type'] = 'MEMBER_CARD';
			$member_card = array();
			$base_info = array();
			// 一键开卡
			//$member_card['auto_activate'] = true;
			$member_card['wx_activate'] = true;
			$member_card['wx_activate_after_submit'] = false;
			if(getcustom('membercard_custom')){
				$member_card['activate_url'] = PRE_URL;
				//unset($member_card['wx_activate']);
				//unset($member_card['wx_activate_after_submit']);
			}
			//背景图 背景色
			$base_info['color'] = $this->getcolor($newhyk['color']);
			if($newhyk['bg_type']==1){
				$member_card['background_pic_url'] = $this->getwxpic($newhyk['background_pic_url'],aid);
			}
			//logo图
			$base_info['logo_url'] = $this->getwxpic($newhyk['logo_url'],aid);
			// Code展示类型， "CODE_TYPE_TEXT" 文本 "CODE_TYPE_BARCODE" 一维码 "CODE_TYPE_QRCODE" 二维码 "CODE_TYPE_ONLY_QRCODE" 仅显示二维码 "CODE_TYPE_ONLY_BARCODE" 仅显示一维码 "CODE_TYPE_NONE" 不显示任何码型
			$base_info['code_type'] = $newhyk['code_type'];
			if($base_info['code_type'] !='CODE_TYPE_TEXT'){
				$base_info['is_pay_and_qrcode'] = true;
			}
			if(getcustom('membercard_custom')){
				$base_info['is_pay_and_qrcode'] = false;
			}
			// 商户名字 字数上限为12个汉字。
			$base_info['brand_name'] = $newhyk['brand_name'];
			// 卡券名，字数上限为9个汉字
			$base_info['title'] = $newhyk['title'];

			
			// 是否支持积分
			if(in_array('积分',$newhyk['custom_field'])){
				$member_card['supply_bonus'] = true;
				//设置跳转外链查看积分详情
				//$member_card['bonus_url'] = '';
				// 积分规则
				if($newhyk['bonus_rules']){
					$member_card['bonus_rules'] = $newhyk['bonus_rules'];
				}
			}else{
				$member_card['supply_bonus'] = false;
			}

			// 是否支持储值 需要储值资质
			$member_card['supply_balance'] = false;
			
			//name_type FIELD_NAME_TYPE_LEVEL 等级 FIELD_NAME_TYPE_COUPON 优惠券 FIELD_NAME_TYPE_STAMP 印花 FIELD_NAME_TYPE_DISCOUNT 折扣 FIELD_NAME_TYPE_ACHIEVEMEN 成就 FIELD_NAME_TYPE_MILEAGE 里程 FIELD_NAME_TYPE_SET_POINTS 集点 FIELD_NAME_TYPE_TIMS 次数
			// 上面三个导航
			$k = 1;
			foreach($newhyk['custom_field'] as $item){
				if($item!='积分'){
					if($item == '余额' || $item == t('余额')){
						$member_card['custom_field'.$k] = array(
							'name'=>t('余额'),
							'name_type'=>'',
							'url'=>m_url('/pagesExt/money/recharge'),
						);
					}
					if($item == '优惠券' || $item == t('优惠券')){
						$member_card['custom_field'.$k] = array(
							//'name'=>t('优惠券'),
							'name_type'=>'FIELD_NAME_TYPE_COUPON',
							'url'=>m_url('/pagesExt/coupon/mycoupon'),
						);
					}
					if($item == '等级'){
						$member_card['custom_field'.$k] = array(
							'name_type'=>'FIELD_NAME_TYPE_LEVEL',
							'url'=>m_url('/pagesExt/my/levelinfo'),
						);
					}
					if($item == '自定义1'){
						$member_card['custom_field'.$k] = array(
							'name'=>$newhyk['custom_field_customize1_name'],
							'url'=>$newhyk['custom_field_customize1_link'],
						);
						if(strpos($newhyk['custom_field_customize1_link'],'miniProgram::') === 0){
							$wxappurls = explode('|',str_replace('miniProgram::','',$newhyk['custom_field_customize1_link']));
							$member_card['custom_field'.$k]['app_brand_user_name'] = trim($wxappurls[2]).'@app';
							$member_card['custom_field'.$k]['app_brand_pass'] = trim($wxappurls[1],'/');
						}
					}
					if($item == '自定义2'){
						$member_card['custom_field'.$k] = array(
							'name'=>$newhyk['custom_field_customize2_name'],
							'url'=>$newhyk['custom_field_customize2_link'],
						);
						if(strpos($newhyk['custom_field_customize2_link'],'miniProgram::') === 0){
							$wxappurls = explode('|',str_replace('miniProgram::','',$newhyk['custom_field_customize2_link']));
							$member_card['custom_field'.$k]['app_brand_user_name'] = trim($wxappurls[2]).'@app';
							$member_card['custom_field'.$k]['app_brand_pass'] = trim($wxappurls[1],'/');
						}
					}
					$k++;
				}
			}
			// 中部按钮
			if($newhyk['center_title']){
				$base_info['center_title'] = $newhyk['center_title'];
				$base_info['center_sub_title'] = $newhyk['center_sub_title'];
				$base_info['center_url'] = $newhyk['center_url'];
				if(strpos($newhyk['center_url'],'miniProgram::') === 0){
					$wxappurls = explode('|',str_replace('miniProgram::','',$newhyk['center_url']));
					$base_info['center_app_brand_user_name'] = trim($wxappurls[2]).'@app';
					$base_info['center_app_brand_pass'] = trim($wxappurls[1],'/');
				}
			}
			//$base_info['center_app_brand_user_name'] = 'gh_2d4dd1b4c8ae@app';
			//$base_info['center_app_brand_pass'] = $newhyk['center_app_brand_pass'];

			// 自定义入口1
			$base_info['custom_url_name'] = $newhyk['custom_url_name'];
			$base_info['custom_url_sub_title'] = $newhyk['custom_url_sub_title'];
			$base_info['custom_url'] = trim($newhyk['custom_url']);
			if(strpos($newhyk['custom_url'],'miniProgram::') === 0){
				$wxappurls = explode('|',str_replace('miniProgram::','',$newhyk['custom_url']));
				$base_info['custom_app_brand_user_name'] = trim($wxappurls[2]).'@app';
				$base_info['custom_app_brand_pass'] = trim($wxappurls[1],'/');
			}
			// 自定义入口2
			$member_card['custom_cell1'] = [
				'name'=>$newhyk['custom_cell1_name'],
				'tips'=>$newhyk['custom_cell1_tips'],
				'url'=>$newhyk['custom_cell1_url']
			];
			if(strpos($newhyk['custom_cell1_url'],'miniProgram::') === 0){
				$wxappurls = explode('|',str_replace('miniProgram::','',$newhyk['custom_cell1_url']));
				$member_card['custom_cell1']['app_brand_user_name'] = trim($wxappurls[2]).'@app';
				$member_card['custom_cell1']['app_brand_pass'] = trim($wxappurls[1],'/');
			}
			// 自定义入口3
			$base_info['promotion_url_name'] = $newhyk['promotion_url_name'];
			$base_info['promotion_url'] = trim($newhyk['promotion_url']);
			$base_info['promotion_url_sub_title'] = $newhyk['promotion_url_sub_title'];
			if(strpos($newhyk['promotion_url'],'miniProgram::') === 0){
				$wxappurls = explode('|',str_replace('miniProgram::','',$newhyk['promotion_url']));
				$base_info['promotion_app_brand_user_name'] = trim($wxappurls[2]).'@app';
				$base_info['promotion_app_brand_pass'] = trim($wxappurls[1],'/');
			}
			// 每人可领券的数量限制，建议会员卡每人限领一张
			$base_info['get_limit'] = 1;
			
			// 卡券使用提醒，字数上限为16个汉字
			$base_info['notice'] = $newhyk['notice'];
			// 卡券使用说明，字数上限为1024个汉字。
			$base_info['description'] = $newhyk['description'];
			// 会员卡特权说明
			$member_card['prerogative'] = $newhyk['prerogative'];
			// 客服电话
			if($newhyk['service_phone']){
				$base_info['service_phone'] = $newhyk['service_phone'];
			}
			//  商品信息 库存
			$base_info['sku'] = array('quantity'=>$newhyk['quantity']);
			// 默认是永久有效 使用日期，有效期的信息。
			$base_info['date_info'] = array(
				'type'=>$newhyk['date_info_type'],
				'begin_timestamp'=>strtotime($newhyk['date_info_begin_time']),
				'end_timestamp'=>strtotime($newhyk['date_info_end_time']),
				'fixed_term'=>$newhyk['date_info_fixed_term'],
				'fixed_begin_term'=>$newhyk['date_info_fixed_begin_term'],
			);
			
			//支持微信支付刷卡
			//$base_info['pay_info'] = ['swipe_card'=>['is_swipe_card'=>true]];
			$member_card['base_info'] = $base_info;

			$data['member_card'] = $member_card;
			$url = 'https://api.weixin.qq.com/card/create?access_token='.$access_token;
			$result = request_post($url,jsonEncode(array('card'=>$data)));
			//dump($data);
			//dump($url);
			//dump($result);
			Log::write('创建卡券解析数据: '.$result);
			$rs = json_decode($result);

			if($rs->errcode == 0){
				$update = [];
				$card_id = $rs->card_id;
				$update['card_id'] = $card_id;
				
				//激活会员卡设置
				$field_list = json_decode($newhyk['field_list'],true);
				$required_form = ['can_modify'=>false,'common_field_id_list'=>[],'custom_field_list'=>[]];
				$optional_form = ['can_modify'=>false,'common_field_id_list'=>[],'custom_field_list'=>[]];
				foreach($field_list as $k=>$v){
					if($v['isshow']==1){
						if($v['required']==1){
							if($k=='name'){
								$required_form['common_field_id_list'][] = 'USER_FORM_INFO_FLAG_NAME';
							}elseif($k=='mobile'){
								$required_form['common_field_id_list'][] = 'USER_FORM_INFO_FLAG_MOBILE';
							}elseif($k=='sex'){
								$required_form['common_field_id_list'][] = 'USER_FORM_INFO_FLAG_SEX';
							}elseif($k=='idcard'){
								$required_form['common_field_id_list'][] = 'USER_FORM_INFO_FLAG_IDCARD';
							}elseif($k=='birthday'){
								$required_form['common_field_id_list'][] = 'USER_FORM_INFO_FLAG_BIRTHDAY';
							}elseif($k=='email'){
								$required_form['common_field_id_list'][] = 'USER_FORM_INFO_FLAG_EMAIL';
							}elseif($k=='location'){
								$required_form['common_field_id_list'][] = 'USER_FORM_INFO_FLAG_LOCATION';
							}elseif($k=='education'){
								$required_form['common_field_id_list'][] = 'USER_FORM_INFO_FLAG_EDUCATION_BACKGROUND';
							}elseif($k=='industry'){
								$required_form['common_field_id_list'][] = 'USER_FORM_INFO_FLAG_INDUSTRY';
							}elseif($k=='income'){
								$required_form['common_field_id_list'][] = 'USER_FORM_INFO_FLAG_INCOME';
							}elseif($k=='habit'){
								$required_form['common_field_id_list'][] = 'USER_FORM_INFO_FLAG_HABIT';
							}else{
								$required_form['custom_field_list'][] = $v['name'];
							}
						}else{
							if($k=='name'){
								$optional_form['common_field_id_list'][] = 'USER_FORM_INFO_FLAG_NAME';
							}elseif($k=='mobile'){
								$optional_form['common_field_id_list'][] = 'USER_FORM_INFO_FLAG_MOBILE';
							}elseif($k=='sex'){
								$optional_form['common_field_id_list'][] = 'USER_FORM_INFO_FLAG_SEX';
							}elseif($k=='idcard'){
								$optional_form['common_field_id_list'][] = 'USER_FORM_INFO_FLAG_IDCARD';
							}elseif($k=='birthday'){
								$optional_form['common_field_id_list'][] = 'USER_FORM_INFO_FLAG_BIRTHDAY';
							}elseif($k=='email'){
								$optional_form['common_field_id_list'][] = 'USER_FORM_INFO_FLAG_EMAIL';
							}elseif($k=='location'){
								$optional_form['common_field_id_list'][] = 'USER_FORM_INFO_FLAG_LOCATION';
							}elseif($k=='education'){
								$optional_form['common_field_id_list'][] = 'USER_FORM_INFO_FLAG_EDUCATION_BACKGROUND';
							}elseif($k=='industry'){
								$optional_form['common_field_id_list'][] = 'USER_FORM_INFO_FLAG_INDUSTRY';
							}elseif($k=='income'){
								$optional_form['common_field_id_list'][] = 'USER_FORM_INFO_FLAG_INCOME';
							}elseif($k=='habit'){
								$optional_form['common_field_id_list'][] = 'USER_FORM_INFO_FLAG_HABIT';
							}else{
								$optional_form['custom_field_list'][] = $v['name'];
							}
						}
					}
				}
				$jhdata = array();
				$jhdata['card_id'] = $card_id;
				$jhdata['required_form'] = $required_form;
				$jhdata['optional_form'] = $optional_form;
				$url = 'https://api.weixin.qq.com/card/membercard/activateuserform/set?access_token='.$access_token;
				$result = request_post($url,jsonEncode($jhdata));
				

				//获取开卡链接
				$url = 'https://api.weixin.qq.com/card/membercard/activate/geturl?access_token='.$access_token;
				$result = request_post($url,jsonEncode(array('card_id'=>$card_id,'outer_str'=>'1')));
				$result = json_decode($result);
				if($result->errcode == 0){
					$update['ret_url'] = $result->url;
				}
				Db::name('membercard')->where('aid',aid)->where('id',$hyk_id)->update($update);
				
				//领取后调用激活会员卡接口设置初始值
				//https://api.weixin.qq.com/card/membercard/activate?access_token=TOKEN
				\app\commons\System::plog('创建会员卡'.$hyk_id);
				return json(['status'=>1, 'msg'=>'创建成功，请等待审核','url'=>(string)url('index')]);
			}else{
				Db::name('membercard')->where('aid',aid)->where('id',$hyk_id)->delete();
				if($rs->errcode == 48001){
					return json(['status'=>0, 'msg'=>'创建失败，请确保您的公众号已开通卡券功能，如未开通请登录微信公众平台(mp.weixin.qq.com) 在[功能]-[添加功能插件]-[卡券功能]中申请开通']);
				}else{
					return json(['status'=>0, 'msg'=>\app\commons\Wechat::geterror($rs),'rs'=>$rs]);
				}
			}
		}
	}
	//领卡记录
	public function record(){
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
			if(input('param.card_id')) $where[] = ['card_id','like','%'.input('param.card_id').'%'];
			if(input('param.card_code')) $where[] = ['card_code','like','%'.input('param.card_code').'%'];
			if(input('param.name')) $where[] = ['name','like','%'.input('param.name').'%'];
			if(input('param.mobile')) $where[] = ['mobile','like','%'.input('param.mobile').'%'];
			if(input('param.ctime') ){
				$ctime = explode(' ~ ',input('param.ctime'));
				$where[] = ['createtime','>=',strtotime($ctime[0])];
				$where[] = ['createtime','<',strtotime($ctime[1]) + 86400];
			}
			$count = 0 + Db::name('membercard_record')->where($where)->count();
			$data = Db::name('membercard_record')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
	}
	//导出
	public function recordexcel(){
		if(input('param.field') && input('param.order')){
			$order = input('param.field').' '.input('param.order');
		}else{
			$order = 'id desc';
		}
        $page = input('param.page');
        $limit = input('param.limit');
		$where = [];
		$where[] = ['aid','=',aid];
		if(input('param.card_id')) $where[] = ['card_id','like','%'.input('param.card_id').'%'];
		if(input('param.card_code')) $where[] = ['card_code','like','%'.input('param.card_code').'%'];
		if(input('param.name')) $where[] = ['name','like','%'.input('param.name').'%'];
		if(input('param.mobile')) $where[] = ['mobile','like','%'.input('param.mobile').'%'];
		if(input('param.ctime') ){
			$ctime = explode(' ~ ',input('param.ctime'));
			$where[] = ['createtime','>=',strtotime($ctime[0])];
			$where[] = ['createtime','<',strtotime($ctime[1]) + 86400];
		}
		$list = Db::name('membercard_record')->where($where)->page($page,$limit)->select()->toArray();
        $count = Db::name('membercard_record')->where($where)->count();
		$title = array();
		$title[] = '序号';
		$title[] = '昵称';
		$title[] = '领取时间';
		$title[] = '卡号';
		$title[] = '姓名';
		$title[] = '手机号';
		$data = array();
		foreach($list as $v){
			$tdata = array();
			$tdata[] = $v['id'];
			$tdata[] = $v['nickname'];
			$tdata[] = date('Y-m-d H:i:s',$v['createtime']);
			$tdata[] = $v['card_code'];
			$tdata[] = $v['name'];
			$tdata[] = $v['mobile'];
			$data[] = $tdata;
		}
		//dump($title);
		//dump($data);
		//die;
        return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data,'title'=>$title]);
		$this->export_excel($title,$data);
	}
	//领取信息详情
	public function getrecorddetail(){
		$id = input('post.id/d');
		$info = Db::name('membercard_record')->where('aid',aid)->where('id',$id)->find();
		return json(['status'=>1,'data'=>$info]);
	}
	public function recorddel(){
		$ids = input('post.ids/a');
		$info = Db::name('membercard_record')->where('aid',aid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('删除会员卡领取记录'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	public function setremark(){
		$id = input('post.id/d');
		$remark = input('post.remark');
		Db::name('membercard')->where('aid',aid)->where('id',$id)->update(['remark'=>$remark]);
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//删除
	public function del(){
		$ids = input('post.ids/a');
		foreach($ids as $id){
			$info = Db::name('membercard')->where('aid',aid)->where('id',$id)->find();
			$url = 'https://api.weixin.qq.com/card/delete?access_token='.\app\commons\Wechat::access_token(aid,'mp');
			if($info['card_id']){
				$rs = request_post($url,jsonEncode(['card_id'=>$info['card_id']]));
			}
			Db::name('membercard')->where('aid',aid)->where('id',$id)->delete();
		}
		\app\commons\System::plog('删除会员卡'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功','rs'=>$rs]);
	}
	//修复
	public function xiufu(){
		$recordlist = Db::name('membercard_record')->where('aid',aid)->where('status',1)->select()->toArray();
		$successnum = 0;
		foreach($recordlist as $v){
			$member = Db::name('member')->where('id',$v['mid'])->find();
			if(!$member || (!$member['card_id'] || !$member['card_code'])){
				$member = Db::name('member')->where('aid',aid)->where('mpopenid',$v['openid'])->find();
				if($member && (!$member['card_id'] || !$member['card_code'])){
					Db::name('member')->where('id',$member['id'])->update(['card_id'=>$v['card_id'],'card_code'=>$v['card_code']]);
					Db::name('membercard_record')->where('id',$v['id'])->update(['mid'=>$member['id']]);
					$successnum++;
				}
			}
		}
		return json(['status'=>1,'msg'=>'成功修复'.$successnum.'个'.t('会员')]);
	}

	//图片上传到微信
	function getwxpic($picurl){
		//return $picurl;
		$access_token = \app\commons\Wechat::access_token(aid,'mp');
		$url = \app\commons\Pic::tolocal($picurl);
		$mediapath = ROOT_PATH.str_replace(PRE_URL.'/','',$url);
		//$data = array('buffer'=>'@'.$mediapath);
		$data = [];
		$data['buffer'] = new \CurlFile($mediapath);
		$result = curl_post('https://api.weixin.qq.com/cgi-bin/media/uploadimg?access_token='.$access_token,$data);
		$res = json_decode($result,true);
		//var_dump($picurl);
		//var_dump($mediapath);
		//var_dump($result);
		if($res['url']){
			return $res['url'];
		}else{
			return json(['status'=>0,'msg'=>\app\commons\Wechat::geterror($res)]);
		}
	}
	//背景颜色转换
	function getcolor($color){
		$colordata = [
			'Color010'=>'#63b359',
			'Color020'=>'#2c9f67',
			'Color030'=>'#509fc9',
			'Color040'=>'#5885cf',
			'Color050'=>'#9062c0',
			'Color060'=>'#d09a45',
			'Color070'=>'#e4b138',
			'Color080'=>'#ee903c',
			'Color081'=>'#f08500',
			'Color082'=>'#a9d92d',
			'Color090'=>'#dd6549',
			'Color100'=>'#cc463d',
			'Color101'=>'#cf3e36',
			'Color102'=>'#5e6671',
		];
		if(strpos($color,'#') === 0){
			$colordata = array_flip($colordata);
			return $colordata[$color];
		}else{
			return $colordata[$color];
		}
	}

	//推送积分
	public function sendscore(){
        if(getcustom('membercard_sendscore')){
            set_time_limit(0);
            if(request()->isPost()){
                $id = input('post.id');
                $score = input('post.score/d');
                $remark = input('post.remark');
                $membercard = Db::name('membercard')->where('id',$id)->find();
                $membercount = Db::name('membercard_record')->where('aid',aid)->where('card_id',$membercard['card_id'])->count();
                $pagenum = input('param.pagenum/d');
                $pagelimit = input('param.pagelimit/d');
                if(input('post.logid')){
                    $logid = input('post.logid');
                }else{
                    $logid = Db::name('membercard_sendscorelog')->insertGetId([
                        'aid'=>aid,
                        'card_id'=>$membercard['card_id'],
                        'score'=>$score,
                        'remark'=>$remark,
                        'createtime'=>time(),
                        'sendcount'=>0,
                        'successcount'=>0,
                        'errorcount'=>0,
                        'endtime'=>time(),
                    ]);
                }

                $recordlist = Db::name('membercard_record')->where('aid',aid)->where('card_id',$membercard['card_id'])->page($pagenum,$pagelimit)->select()->toArray();

                $url = 'https://api.weixin.qq.com/card/membercard/updateuser?access_token='.\app\commons\Wechat::access_token(aid,'mp');
                $sendcount = 0;
                $successcount = 0;
                $errorcount = 0;
                foreach($recordlist as $record){
                    $postdata = [];
                    $postdata['card_id'] = $record['card_id'];
                    $postdata['code'] = $record['card_code'];
                    $postdata['add_bonus'] = $score;
                    if($remark) $postdata['record_bonus'] = $remark;

                    $rs = request_post($url,jsonEncode($postdata));
                    $rs = json_decode($rs,true);
                    if($rs['errcode'] ==0 ){
                        $successcount++;
                        Db::name('member')->where('aid',aid)->where('mpopenid',$record['openid'])->inc('score',$score)->update();
                    }else{
                        $errorcount++;
                        //Db::name('membercard_sendscorelog_errlog')->insert(['aid'=>aid,'logid'=>$logid,'openid'=>$openid,'createtime'=>time(),'errcode'=>$rs['errcode'],'errmsg'=>\app\commons\Wechat::geterror($rs)]);
                    }
                    $sendcount++;
                }
                Db::name('membercard_sendscorelog')->where('aid',aid)->where('id',$logid)->update([
                    'sendcount'=>Db::raw("sendcount+{$sendcount}"),
                    'successcount'=>Db::raw("successcount+{$successcount}"),
                    'errorcount'=>Db::raw("errorcount+{$errorcount}"),
                    'endtime'=>time(),
                ]);

                $sendscorelog = Db::name('membercard_sendscorelog')->where('aid',aid)->where('id',$logid)->find();
                if($membercount <= $pagelimit*$pagenum){
                    $status = 1;
                }else{ //还有下一页
                    $status = 2;
                }
                return json(['status'=>$status,'msg'=>'','logid'=>$logid,'sendcount'=>$sendscorelog['sendcount'],'successcount'=>$sendscorelog['successcount'],'errorcount'=>$sendscorelog['errorcount']]);
            }
        }
	}
    //推送积分
    public function sendscore2(){
        if(getcustom('membercard_sendscore2')){
            if(request()->isPost()){
                set_time_limit(0);
                $id = input('post.id');
                $ids = input('post.ids');
                $score = input('post.score/d');
                $remark = input('post.remark');
                $membercard = Db::name('membercard')->where('id',$id)->find();
//                $membercount = Db::name('membercard_record')->where('aid',aid)->where('card_id',$membercard['card_id'])->count();
                $pagenum = input('param.pagenum/d');
                $pagelimit = input('param.pagelimit/d');
                $datawhere = input('post.datawhere/a');
                if($datawhere['field'] && $datawhere['order']){
                    $order = $datawhere['field'].' '.$datawhere['order'];
                }else{
                    $order = 'id desc';
                }
                if(input('post.sendtype') == "0"){
                    $where = "id in(".implode(',',$_POST['ids']).")";
                }elseif(input('post.sendtype') == '1'){
                    $where = array();
                    $where[] = ['aid','=',aid];
                    if($datawhere['pid']) $where[] = ['pid','=',$datawhere['pid']];
                    if($datawhere['nickname']) $where[] = ['nickname','like','%'.$datawhere['nickname'].'%'];
                    if($datawhere['realname']) $where[] = ['realname','like','%'.$datawhere['realname'].'%'];
                    if($datawhere['levelid']) $where[] = ['levelid','=',$datawhere['levelid']];
                    if($datawhere['ctime']){
                        $ctime = explode(' ~ ',$datawhere['ctime']);
                        $where[] = ['createtime','>=',strtotime($ctime[0])];
                        $where[] = ['createtime','<',strtotime($ctime[1]) + 86400];
                    }

                    $mids = Db::name('membercard_record')->where('aid',aid)->where('card_id',$membercard['card_id'])->column('mid');
                    $where[] = ['id','in',$mids];
                }else{
                    return json(['status'=>0,'msg'=>'参数错误']);
                }
                $cpid = input('post.cpid');
                $membercount = Db::name('member')->where($where)->count();
                if(input('post.sendtype') == "0"){
                    $mids = Db::name('member')->where($where)->page($pagenum,$pagelimit)->order($order)->column('id');
                    $recordlist = Db::name('membercard_record')->where('aid',aid)->where('card_id',$membercard['card_id'])->where('mid','in',$mids)->page($pagenum,$pagelimit)->select()->toArray();
                }elseif(input('post.sendtype') == '1'){
                    $recordlist = Db::name('membercard_record')->where('aid',aid)->where('card_id',$membercard['card_id'])->page($pagenum,$pagelimit)->select()->toArray();
                }

                if(input('post.logid')){
                    $logid = input('post.logid');
                }else{
                    $logid = Db::name('membercard_sendscorelog')->insertGetId([
                        'aid'=>aid,
                        'card_id'=>$membercard['card_id'],
                        'score'=>$score,
                        'remark'=>$remark,
                        'createtime'=>time(),
                        'sendcount'=>0,
                        'successcount'=>0,
                        'errorcount'=>0,
                        'endtime'=>time(),
                    ]);
                }

//                $recordlist = Db::name('membercard_record')->where('aid',aid)->where('card_id',$membercard['card_id'])->page($pagenum,$pagelimit)->select()->toArray();

                $url = 'https://api.weixin.qq.com/card/membercard/updateuser?access_token='.\app\commons\Wechat::access_token(aid,'mp');
                $sendcount = 0;
                $successcount = 0;
                $errorcount = 0;
                foreach($recordlist as $record){
                    $postdata = [];
                    $postdata['card_id'] = $record['card_id'];
                    $postdata['code'] = $record['card_code'];
                    $postdata['add_bonus'] = $score;
                    if($remark) $postdata['record_bonus'] = $remark;

                    $rs = request_post($url,jsonEncode($postdata));
                    $rs = json_decode($rs,true);
                    if($rs['errcode'] ==0 ){
                        $successcount++;
                        Db::name('member')->where('aid',aid)->where('mpopenid',$record['openid'])->inc('score',$score)->update();
                    }else{
                        $errorcount++;
                        //Db::name('membercard_sendscorelog_errlog')->insert(['aid'=>aid,'logid'=>$logid,'openid'=>$openid,'createtime'=>time(),'errcode'=>$rs['errcode'],'errmsg'=>\app\commons\Wechat::geterror($rs)]);
                    }
                    $sendcount++;
                }
                Db::name('membercard_sendscorelog')->where('aid',aid)->where('id',$logid)->update([
                    'sendcount'=>Db::raw("sendcount+{$sendcount}"),
                    'successcount'=>Db::raw("successcount+{$successcount}"),
                    'errorcount'=>Db::raw("errorcount+{$errorcount}"),
                    'endtime'=>time(),
                ]);

                $sendscorelog = Db::name('membercard_sendscorelog')->where('aid',aid)->where('id',$logid)->find();
                if($membercount <= $pagelimit*$pagenum){
                    $status = 1;
                }else{ //还有下一页
                    $status = 2;
                }
                return json(['status'=>$status,'msg'=>'','logid'=>$logid,'sendcount'=>$sendscorelog['sendcount'],'successcount'=>$sendscorelog['successcount'],'errorcount'=>$sendscorelog['errorcount']]);
            }

            $card_id = input('param.card_id');
            $card = Db::name('membercard')->where('aid',aid)->where('card_id',$card_id)->find();
            if(!$card) showmsg('会员卡不存在');


            $default_cid = Db::name('member_level_category')->where('aid',aid)->where('isdefault', 1)->value('id');
            $default_cid = $default_cid ? $default_cid : 0;
            $levelList = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->select()->toArray();
            $levelArr = array();
            foreach($levelList as $v){
                $levelArr[$v['id']] = $v['name'];
            }

            View::assign('card',$card);
            View::assign('levelArr',$levelArr);
            return View::fetch();
        }
    }
	public function set(){
		$id = input('param.id');
		if(request()->isAjax()){
			if(getcustom('membercard_custom')){
				$id = input('post.id/d');
				$info = input('post.info');
				Db::name('membercard')->where('id',$id)->update($info);	
				\app\commons\System::plog('设置会员卡奖励'.$id);
				return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);

			}
		}
		$info  = Db::name('membercard')->where('aid',aid)->where('id',$id)->find();
		$couponList = [];
		if($info['coupon_ids']){
			$coupon_ids = explode(',', $info['coupon_ids']);
			foreach($coupon_ids as $couponid){
				$couponList[] = Db::name('coupon')->where('aid',aid)->where('id',$couponid)->find();
			}
		}
		View::assign('couponList',$couponList);
		$parentcouponList = [];
		if($info['coupon_ids']){
			$parent_coupon_ids = explode(',', $info['parent_coupon_ids']);
			foreach($parent_coupon_ids as $couponid){
				$parentcouponList[] = Db::name('coupon')->where('aid',aid)->where('id',$couponid)->find();
			}
		}
		View::assign('parentcouponList',$parentcouponList);
		View::assign('info',$info);
		return View::fetch();
	}

}