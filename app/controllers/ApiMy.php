<?php


namespace app\controllers;
use think\Exception;
use think\facade\Db;
use think\facade\Log;

class ApiMy extends ApiCommon
{
	public function initialize(){
		parent::initialize();
		$this->checklogin();
	}
	public function getMember(){
		$post = input('post.');
		$mobile = input('post.mobile');
		$mid = input('post.mid/d');
		$acc = input('post.acc');
		if (input('?post.mobile')) {
			$member = Db::name('member')->field('id,nickname,headimg')->where('aid', aid)->where('tel', $mobile)->find();
		}
		if (input('?post.mid')) {
			$member = Db::name('member')->field('id,nickname,headimg')->where('aid', aid)->where('id', $mid)->find();
		}
		if (input('?post.acc')) {
			$member = Db::name('member')->field('id,nickname,headimg')->where('aid', aid)->where('acc', $acc)->find();
		}
		if ($member['id'] == mid) {
            return $this->json(['status'=>0,'msg'=>'不能转赠给自己']);
        }
		return $this->json(['status'=>1,'msg'=>'获取成功','touser'=>$member]);
	}
	public function getWebinfo()
	{
		$rs = Db::name('admin_set')->where('aid',aid)->find();
		if($rs){
			return $this->json($rs);
		}else{
			return $this->json(['status'=>0,'msg'=>'页面不存在']);
		}
	}
	public function usercenter(){

        \app\customs\HuiDong::syncMemberSingle(aid,mid);

		if(getcustom('plug_tengrui')){
			//全部同步认证
			$tengrui = new \app\customs\TengRui(aid,mid);
			$tengrui->tb_dan_member($this->member['mpopenid']);
		}
		$pagedata = Db::name('designerpage')->where('aid',aid)->where('ishome',2)->find();
		if($pagedata){
			$pageinfo = json_decode($pagedata['pageinfo'],true);
			$pagecontent = json_decode(\app\commons\System::initpagecontent($pagedata['content'],aid,mid,platform),true);

			$rdata = [];
			$rdata['status'] = 1;
			$rdata['msg'] = '查询成功';
			$rdata['pageinfo'] = $pageinfo[0]['params'];
			$rdata['pagecontent'] = $pagecontent;
			$rdata['copyright'] = Db::name('admin')->where('id',aid)->value('copyright');
			if(getcustom('copyright_link')){
				$rdata['copyright_link'] = Db::name('admin')->where('id',aid)->value('copyright_link');
			}
			
			if(getcustom('240308.fqywl.com') && $this->member['lv_time']> 0){
			   $sysset = Db::name('admin_set')->where('aid',aid)->find();
		       if ( intval($this->member['credit1']) < $sysset['credit1_min'] && $sysset['credit1_tip']) {
		           $rdata['tips'] = $sysset['credit1_tip'];
		       }
		       
			}
			
			
			
			
			
			if(getcustom('xixie')){
				$rdata['xixie'] = false;
				if($this->member && $this->member['mdid']>0){
					//查询洗鞋插件是否开启
					$xixie_sysset = Db::name('xixie_sysset')->where('aid',aid)->find();
		            if($xixie_sysset && $xixie_sysset['all_close'] == 0){
		            	$count_mendian = Db::name('mendian')->where('id',$this->member['mdid'])->where('aid',aid)->count();
						if($count_mendian){
							$rdata['xixie'] = true;
						}
		            }
				}
			}
			if(getcustom('task_banner')){
                $task_banner_set = Db::name('task_banner_set')->where('aid',aid)->find();
			    $rdata['rewardedvideoad'] = $task_banner_set['rewardedvideoad'];
            }
			$rdata['showthqrcode'] = false;
			$rdata['thqrcode'] = '';
			$rdata['isapplymendian'] = 0;
			if(getcustom('mendian_upgrade')){  
				$mendian_upgrade_status = Db::name('admin')->where('id',aid)->value('mendian_upgrade_status');
				if($mendian_upgrade_status==1){
					$field = platform.'qrcode';
					$fields = 'id,'.$field;
					$mendian =  Db::name('mendian_memberqrcode')->field($fields)->where('mid',mid)->where('aid',aid)->find();

					if(!$mendian || !$mendian[$field]){
						if(platform=='h5'){
							$wxthqrcode = createqrcode(m_url('pagesA/mendiancenter/hxorderlist?type=shop&mid='.mid));
						}else{
						   $wxthqrcode = \app\commons\Wechat::getQRCode(aid,platform,'pagesA/mendiancenter/hxorderlist',['mid'=>mid]);
						   	if($wxthqrcode['msg'] && $rdata['status']===0){
								$rdata['status']=0;
								$rdata['msg'] = $wxthqrcode['msg'];
							}else{
								$wxthqrcode = $wxthqrcode['url'];
							}
						}
						if(!$mendian){
							$mdata = [];
							$mdata['aid'] = aid;
							$mdata['mid'] = mid;
							$mdata[$field] = $wxthqrcode;
							Db::name('mendian_memberqrcode')->insert($mdata);
							//echo db('mendian_memberqrcode')->getlastsql();
						}else{
							Db::name('mendian_memberqrcode')->where('id',$mendian['id'])->where('aid',aid)->update([$field=>$wxthqrcode]);
						}
						
					}else{
						$wxthqrcode = $mendian[$field];
					}
					$mendian = Db::name('mendian')->where('aid',aid)->where('mid',mid)->find();
					if($mendian){
						$rdata['isapplymendian'] = 1;
					}else{
						$rdata['isapplymendian'] = 2;
					}
					$rdata['thqrcode'] = $wxthqrcode;
					$rdata['showthqrcode'] = true;
				}
			}
            $greenscore_hb = [
                'show_hb' => 0,
                'hbmoney' => 0,
                'hbtext' => '',
                'log_id' => 0
            ];
            if(getcustom('greenscore_max')){
                $consumer_set = Db::name('consumer_set')->where('aid',aid)->find();
                $hbstatus = $consumer_set['hbstatus'];
                $show_greenscore_hb = Db::name('greenscore_hb_log')->where('mid',mid)
                    ->where('show_greenscore_hb',date('Ymd'))
                    ->order('id desc')
                    ->find();
                $show_hb = 0;//是否展示红包
                $add_value = 0;//绿色积分增值金额
                $log_id = 0;
                if($hbstatus && $this->member['green_score']>0 ){
                    //计算增值金额
                    if(!$show_greenscore_hb || $show_greenscore_hb['status']==0){
                        $show_hb = 1;
                    }
                    $last_price = Db::name('member_greenscore_log')
                        ->where('aid',aid)
                        ->where('value','>',0)
                        ->where('createtime','<',strtotime(date('Y-m-d')))
                        ->order('createtime','desc')
                        ->value('green_score_price');
                    if($last_price>0){
                        $add_price = bcsub($consumer_set['green_score_price'],$last_price,2);
                    }else{
                        $add_price = 0;
                    }

                    $add_value = bcmul($add_price,$this->member['green_score'],2);
                    if($add_value>0 && $show_hb==1){
                        if(!$show_greenscore_hb || $this->member['green_score']!=$show_greenscore_hb['green_score']){
                            //插入增值记录
                            $log = [];
                            $log['aid'] = aid;
                            $log['mid'] = mid;
                            $log['ordernum'] = date('ymdHis') .aid. rand(1000, 9999);
                            $log['hbmoney'] = $add_value;
                            $log['hbaccount'] = $consumer_set['hbaccount'];
                            $log['green_score'] = $this->member['green_score'];
                            $log['last_green_score_price'] = $last_price;
                            $log['green_score_price'] = $consumer_set['green_score_price'];
                            $log['createtime'] = time();
                            $log['show_greenscore_hb'] = date('Ymd');
                            $log_id = Db::name('greenscore_hb_log')->insertGetId($log);
                        }else{
                            $log_id = $show_greenscore_hb['id'];
                            $add_value = $show_greenscore_hb['hbmoney'];
                        }
                    }else{
                        $show_hb = 0;
                    }

                }
                $greenscore_hb = [
                    'show_hb' => $show_hb,
                    'hbmoney' => $add_value,
                    'hbtext' => $consumer_set['hbtext'],
                    'log_id' => $log_id
                ];
            }
            $rdata['greenscore_hb'] = $greenscore_hb;
			return $this->json($rdata);
		}else{
			return $this->json(['status'=>0,'msg'=>'页面不存在']);
		}
	}
	public function set(){
		$smsset = Db::name('admin_set_sms')->where('aid',aid)->find();
		if($smsset && $smsset['status'] == 1 && $smsset['tmpl_smscode'] && $smsset['tmpl_smscode_st']==1){
			$needsms = 1;
		}else{
			$needsms = 0;
		}
		if(request()->isPost()){
			$formdata = input('post.info/a');
			if($needsms==1){
				if(md5($formdata['tel'].'-'.$formdata['code']) != cache(input('param.session_id').'_smscode') || cache(input('param.session_id').'_smscodetimes') > 5){
					return $this->json(['status'=>0,'msg'=>'短信验证码错误']);
				}
			}
			cache(input('param.session_id').'_smscode',null);
			cache(input('param.session_id').'_smscodetimes',null);
			
			$info = [];
			$info['realname'] = $formdata['realname'];
			$info['tel'] = $formdata['tel'];
			$info['usercard'] = $formdata['usercard'];
			$info['weixin'] = $formdata['weixin'];
			$info['aliaccount'] = $formdata['aliaccount'];
			$info['bankname'] = $formdata['bankname'];
			$info['bankcarduser'] = $formdata['bankcarduser'];
			$info['bankcardnum'] = $formdata['bankcardnum'];
			$info['sex'] = $formdata['sex'];
			if($formdata['province_city']){
				$province_city = explode(' ',$formdata['province_city']);
				if($province_city){
					$info['province'] = $province_city[0];
					$info['city'] = $province_city[1];
				}
			}
			$info['birthday'] = $formdata['birthday'];
			Db::name('member')->where('id',mid)->update($info);
			return $this->json(['status'=>1,'msg'=>'修改成功']);
		}
		$field = 'id,headimg,nickname,realname,tel,usercard,weixin,aliaccount,aliaccountname,bankname,bankaddress,bankcarduser,bankcardnum,sex,province,city,birthday';
        if(getcustom('restaurant_finance_notice_switch')){
            $field .=',is_receive_finance_tmpl,is_receive_finance_sms';
        }
        if(getcustom('register_fields')){
            $field .= ',form_record_id';
        }
        if(getcustom('shop_label')){
            $field .= ',labelid';
        }
        if(getcustom('withdraw_paycode')){
            $field .= ',wxpaycode,alipaycode';
        }
		$userinfo = Db::name('member')->where('id',mid)->field($field)->find();
		if($userinfo['realname'] == null) $userinfo['realname'] = '';
		if($userinfo['tel'] == null) $userinfo['tel'] = '';
		if($userinfo['usercard'] == null) $userinfo['usercard'] = '';
		if($userinfo['weixin'] == null) $userinfo['weixin'] = '';
		if($userinfo['aliaccount'] == null) $userinfo['aliaccount'] = '';
		if($userinfo['aliaccountname'] == null) $userinfo['aliaccountname'] = '';
		if($userinfo['bankname'] == null) $userinfo['bankname'] = '';
		if($userinfo['bankcarduser'] == null) $userinfo['bankcarduser'] = '';
		if($userinfo['bankcardnum'] == null) $userinfo['bankcardnum'] = '';
		if($userinfo['bankaddress'] == null) $userinfo['bankaddress'] = '';
		if($userinfo['sex'] == null) $userinfo['sex'] = '';
		if($userinfo['province'] == null) $userinfo['province'] = '';
		if($userinfo['city'] == null) $userinfo['city'] = '';
		if($userinfo['birthday'] == null) $userinfo['birthday'] = '';
		if($this->member['pwd']==''){
			$userinfo['haspwd'] = 0;
		}else{
			$userinfo['haspwd'] = 1;
		}

		$rdata = [];
		$rdata['needsms'] = $needsms;

		$userinfo['set_alipay'] = false;
		if(getcustom('alipay_auto_transfer')){
			$userinfo['set_alipay'] = true;
		}
		$userinfo['set_bank']   = false;
        $userinfo['set_receive_notice']   = false;
        if(getcustom('restaurant_finance_notice_switch')){
            $userinfo['set_receive_notice']   = true;  
        }
        if(getcustom('shop_label')){
        	$userinfo['haslabel'] = true;
            $labelnames = '';
            if(!empty($userinfo['labelid'])){
                $labels = Db::name('shop_label')->where('id','in',$userinfo['labelid'])->where('status',1)->where('aid',aid)->order('sort desc,id desc')->column('name');
                if($labels){
                    $labelnames = implode(' ',$labels);
                }
            }
            $userinfo['labelnames'] = $labelnames;
        }
		$rdata['userinfo'] = $userinfo;
		

		if(getcustom('member_set')){
			//查询设置
			$otherdata = '';
			$set = Db::name('member_set')->where('aid',aid)->find();
			if($set){
				$setcontent = json_decode($set['content'],true);
				if($setcontent){
					foreach($setcontent as $sk=>&$sv){
						$sv['content'] = '';
						//查询用户设置
						$log = Db::name('member_set_log')->where('mid',mid)->where('formid',$set['id'])->find();
						if($log){
							$sv['content'] = $log['form'.$sk];
						}
					}
					unset($sv);
				}
				$otherdata = $setcontent;
			}
			$rdata['otherdata'] = $otherdata;
			$rdata['member_edit_switch'] = $set['member_edit_switch'];
		}

        //注册自定义
        $register_forms = [];
        if(getcustom('register_fields')){
            //系统设置参数
            $sys_forms = Db::name("register_form")->where("aid", aid)->find();
            if($sys_forms){
                $content = json_decode($sys_forms['content'], true);
                if($userinfo['form_record_id']>0){
                    $register_record = Db::name('register_form_record')->where('id', $userinfo['form_record_id'])->find();
                    if($register_record){
                        foreach ($content as $k=>$item) {
                            if(!in_array($item['key'], ['realname','sex', 'birthday'])){
                                $item['content'] = $register_record['form'.$k]??'';
                                $register_forms[$k] = $item;
                            }
                        }
                    }
                }
            }
        }
        $rdata['register_forms'] = $register_forms;

		return $this->json($rdata);
	}
	public function setfield(){
		$headimg = input('post.headimg');
		$nickname = trim(input('post.nickname'));

		// 小程序安全检测
		if(platform == 'wx' ){
			if(!empty($nickname) || !empty($headimg)){
				$openid = Db::name('member')->where('aid',aid)->where('id',mid)->value('wxopenid');
				if($openid && !empty($nickname)){
					// 昵称
					$res = \app\commons\Wechat::checkMessageSafe($nickname,$openid,aid);
					if($res['errcode'] == 0){
						if(!empty($res['result']['suggest']) && $res['result']['suggest'] == 'review'){
							return  json(['status'=>0,'msg'=>'输入文字内容违规，请重新填写']);
						}
					}else{
						return json(['status'=>0,'msg'=>$res['errmsg']]);
					}
				}
				// 头像
				if($openid && !empty($headimg)){
					$res = \app\commons\Wechat::checkImageSafe($headimg,$openid,aid);
					if($res['errcode'] == 0){
						if(!empty($res['trace_id'])){
							$data_wx = [
								'aid' => aid,
								'mid' => mid,
								'trace_id'=>$res['trace_id'],
								'headimg'=>$headimg,
								'createtime'=>time()
							];
		
							Db::name('member_wximage_log')->insert($data_wx);
						}
					}else{
						return json(['status'=>0,'msg'=>$res['errmsg']]);
					}
				}
			}
		}

		$realname = input('post.realname');
		$sex = input('post.sex');
		$birthday = input('post.birthday');
		$weixin = input('post.weixin');
		$aliaccount = input('post.aliaccount');
		$aliaccountname = input('post.aliaccountname');
		$bankname = input('post.bankname');
		$bankcarduser = input('post.bankcarduser');
		$bankcardnum = input('post.bankcardnum');
		$bankaddress = input('post.bankaddress');
		$data = [];
		if($headimg) $data['headimg'] = $headimg;
		if($nickname) $data['nickname'] = $nickname;
		if($realname) $data['realname'] = $realname;
		if($sex) $data['sex'] = $sex;
		if($birthday) $data['birthday'] = $birthday;
		if($weixin) $data['weixin'] = $weixin;
		if($aliaccount) $data['aliaccount'] = $aliaccount;
		if($aliaccountname) $data['aliaccountname'] = $aliaccountname;
		if($bankname) $data['bankname'] = $bankname;
		if($bankcarduser) $data['bankcarduser'] = $bankcarduser;
		if($bankcardnum) $data['bankcardnum'] = $bankcardnum;
		if($bankaddress) $data['bankaddress'] = $bankaddress;
		if(getcustom('withdraw_paycode')){
            if(input('post.wxpaycode')) $data['wxpaycode']   = input('post.wxpaycode');
			if(input('post.alipaycode')) $data['alipaycode'] = input('post.alipaycode');
        }
		Db::name('member')->where('id',mid)->update($data);
		if($headimg || $nickname) {
		    //删除海报
            Db::name('member_poster')->where('aid',aid)->where(['mid'=>mid])->delete();
        }
		$member = Db::name('member')->where('id',mid)->find();
		if($member['is_wanshan_score'] == 0 && $member['realname'] && $member['tel'] && $member['sex'] && $member['birthday']){
			Db::name('member')->where('id',mid)->update(['is_wanshan_score'=>1]);
			$set = Db::name('register_giveset')->where('aid',aid)->find();
			if($set['status']==1 && $set['wanshan_score']>0){
				$date = date('Y-m-d H:i:s');
				if($date >= $set['starttime'] && $date < $set['endtime']){
					\app\commons\Member::addscore(aid,mid,$set['wanshan_score'],'完善资料赠送');
				}
			}
		}
		return $this->json(['status'=>1,'msg'=>'修改成功']);
	}

    public function setHuifuField(){
        if(getcustom('pay_huifu')){
            if(request()->isPost()){
//                dd(input('post.formdata'));
                $realname = input('post.formdata.realname');
                $usercard = input('post.formdata.usercard');
                $bankname = input('post.formdata.bankname');
//                $bankcarduser = input('post.formdata.bankcarduser');
                $bankcardnum = input('post.formdata.bankcardnum');
                $bankaddress = input('post.formdata.bankaddress');
                $data = [];
                if($realname) {
                    $data['realname'] = $realname;
                }else{
                    return $this->json(['status'=>0,'msg'=>'请输入姓名']);
                }
                if($usercard) {
                    $data['usercard'] = $usercard;
                }else{
                    return $this->json(['status'=>0,'msg'=>'请输入身份证号']);
                }
                if(input('post.formdata.usercard_begin_date')) {
                    $data['usercard_begin_date'] = input('post.formdata.usercard_begin_date');
                }else{
                    return $this->json(['status'=>0,'msg'=>'请选择身份证开始日期']);
                }
//                dd(input('post.formdata.usercard_date_type.0'));
                if(input('post.formdata.usercard_end_date') || input('post.formdata.usercard_date_type.0') == 1) {
                    if(input('post.formdata.usercard_date_type.0') == 1){
                        $data['usercard_end_date'] = '';
                        $data['usercard_date_type'] = 1;
                    }else{
                        $data['usercard_date_type'] = 0;
                        $data['usercard_end_date'] = input('post.formdata.usercard_end_date');
                    }
                }else{
                    return $this->json(['status'=>0,'msg'=>'请选择身份证结束日期']);
                }
                if($bankname) {
                    $data['bankname'] = $bankname;
                }else{
                    return $this->json(['status'=>0,'msg'=>'请选择开户行']);
                }

                $data['bankcarduser'] = $realname;
//                if($bankcarduser) {
//                    $data['bankcarduser'] = $bankcarduser;
//                }else{
//                    return $this->json(['status'=>0,'msg'=>'请输入持卡人姓名']);
//                }
                if($bankcardnum) {
                    $data['bankcardnum'] = $bankcardnum;
                }else{
                    return $this->json(['status'=>0,'msg'=>'请输入银行卡号']);
                }
                if($bankaddress) $data['bankaddress'] = $bankaddress;
                if(input('post.formdata.areaval')) {
                    $data['bank_province'] = input('post.formdata.areaname.0');
                    $data['bank_province_code'] = input('post.formdata.areaval.0');
                    $data['bank_city'] = input('post.formdata.areaname.1');
                    $data['bank_city_code'] = input('post.formdata.areaval.1');
                }else{
                    return $this->json(['status'=>0,'msg'=>'请选择银行所属地区']);
                }
                if(input('post.formdata.tel')) {
                    $data['tel'] = input('post.formdata.tel');
                }else{
                    return $this->json(['status'=>0,'msg'=>'请输入手机号']);
                }
                if($this->member['huifu_id']){
                    //用户进件
                    $huifu = new \app\customs\Huifu([],aid,0,mid);
                    $rs = $huifu->userBasicdataIndv($data);
                    if($rs['status'] != 1 && empty($rs['resp']['huifu_id'])){
                        return $this->json($rs);
                    }
                    $rs2 = $huifu->userBusiOpen($rs['resp']['huifu_id'],$data);
                    if($rs2['status'] != 1){
                        return $this->json($rs2);
                    }
                    $data['huifu_id'] = $rs['resp']['huifu_id'];
                    $data['huifu_token_no'] = $rs2['resp']['token_no'];
                    Db::name('member')->where('id',mid)->update($data);
                }else{
                    //用户进件
                    $huifu = new \app\customs\Huifu([],aid,0,mid);
                    $rs = $huifu->userBasicdataIndv($data);
                    if($rs['status'] != 1 && empty($rs['resp']['huifu_id'])){
                        return $this->json($rs);
                    }
                    $rs2 = $huifu->userBusiOpen($rs['resp']['huifu_id'],$data);
                    if($rs2['status'] != 1){
                        return $this->json($rs2);
                    }
                    $data['huifu_id'] = $rs['resp']['huifu_id'];
                    $data['huifu_token_no'] = $rs2['resp']['token_no'];
                    Db::name('member')->where('id',mid)->update($data);
                }

                return $this->json(['status'=>1,'msg'=>'修改成功']);
            }else{
                $userinfo = Db::name('member')->where('id',mid)->field('huifu_id,usercard_begin_date,usercard_end_date,usercard_date_type,bank_province,bank_province_code,bank_city,bank_city_code,id,headimg,nickname,realname,tel,usercard,bankname,bankaddress,bankcarduser,bankcardnum,sex,province,city,birthday')->find();
                if($userinfo['realname'] == null) $userinfo['realname'] = '';
                if($userinfo['tel'] == null) $userinfo['tel'] = '';
                if($userinfo['usercard'] == null) $userinfo['usercard'] = '';
                if($userinfo['bankname'] == null) $userinfo['bankname'] = '';
                if($userinfo['bankcarduser'] == null) $userinfo['bankcarduser'] = '';
                if($userinfo['bankcardnum'] == null) $userinfo['bankcardnum'] = '';
                if($userinfo['bankaddress'] == null) $userinfo['bankaddress'] = '';
                if($userinfo['usercard_begin_date'] == null) $userinfo['usercard_begin_date'] = '';
                if($userinfo['usercard_end_date'] == null) $userinfo['usercard_end_date'] = '';

                $rdata = [];
                $rdata['status'] = 1;
                $rdata['userinfo'] = $userinfo;

                return $this->json($rdata);
            }
        }

    }
	//编辑手机号
	public function settel(){
		$smsset = Db::name('admin_set_sms')->where('aid',aid)->find();
		if($smsset && $smsset['status'] == 1 && $smsset['tmpl_smscode'] && $smsset['tmpl_smscode_st']==1){
			$needsms = true;
		}else{
			$needsms = false;
		}
		$rdata = [];
		$rdata['status'] = 1;
		$rdata['needsms'] = $needsms;
		return $this->json($rdata);
	}
	public function settelsub(){
	     $sysset = Db::name('admin_set')->where('aid',aid)->find();
		$tel = input('param.tel');
		$smscode = input('param.smscode');
		$smsset = Db::name('admin_set_sms')->where('aid',aid)->find();
		if($smsset && $smsset['status'] == 1 && $smsset['tmpl_smscode'] && $smsset['tmpl_smscode_st']==1){
			$needsms = true;
		}else{
			$needsms = false;
		}
        if(!checkTel($tel)){
            //return $this->json(['status'=>0, 'msg'=>'请填写正确的手机号']);
        }
		if($needsms && md5($tel.'-'.$smscode) != cache($this->sessionid.'_smscode') || cache($this->sessionid.'_smscodetimes')>5){
			cache($this->sessionid.'_smscodetimes',cache($this->sessionid.'_smscodetimes')+1);
			return $this->json(['status'=>0,'msg'=>'短信验证码错误']);
		}
		
    	if ($sysset['reg_acc_tel']==1) {
    	    $exist = Db::name('member')->where('aid',aid)->where('id','<>',mid)->where('tel',$tel)->count('id');
            if($exist>=$sysset['maxtel']){
                return $this->json(['status'=>0,'msg'=>'同一手机号最多绑定'.$sysset['maxtel'].'个账号']);
            }
		}else {
		    $member = Db::name('member')->where('aid',aid)->where('id','<>',mid)->where('tel',$tel)->find();
		}
		if($member){
			return $this->json(['status'=>0,'msg'=>'该手机号已绑定其他账号']);
		}

		cache($this->sessionid.'_smscode',null);
		cache($this->sessionid.'_smscodetimes',null);
		Db::name('member')->where('id',mid)->update(['tel'=>$tel]);
		return $this->json(['status'=>1,'msg'=>'修改成功']);

	}
	//修改密码
	public function setpwd(){
		if($this->member['pwd']==''){
			$haspwd = 0;
		}else{
			$haspwd = 1;
		}
		if(request()->isPost()){
			$pwd = input('post.pwd');
			$oldpwd = input('post.oldpwd');
			$tel = input('post.tel');
			$smscode = input('post.smscode');
			if($this->member['pwd'] && $this->member['pwd'] != md5($oldpwd)){
				return $this->json(['status'=>0,'msg'=>'原密码输入错误']);
			}
			if( md5($tel.'-'.$smscode) != cache($this->sessionid.'_smscode') || cache($this->sessionid.'_smscodetimes')>5){
    			cache($this->sessionid.'_smscodetimes',cache($this->sessionid.'_smscodetimes')+1);
    			return $this->json(['status'=>0,'msg'=>'短信验证码错误']);
		    }
	    	cache($this->sessionid.'_smscode',null);
        	cache($this->sessionid.'_smscodetimes',null);
			Db::name('member')->where('aid',aid)->where('id',mid)->update(['pwd'=>md5($pwd)]);
			return $this->json(['status'=>1,'msg'=>'修改成功']);
		}
		$rdata = [];
		$rdata['tel'] = $this->member['tel'];
		$rdata['haspwd'] = $haspwd;
		return $this->json($rdata);
	}
	//修改密码
// 	public function setpwd(){
// 		if($this->member['pwd']==''){
// 			$haspwd = 0;
// 		}else{
// 			$haspwd = 1;
// 		}
// 		if(request()->isPost()){
// 			$pwd = input('post.pwd');
// 			$oldpwd = input('post.oldpwd');
// 			if($this->member['pwd'] && $this->member['pwd'] != md5($oldpwd)){
// 				return $this->json(['status'=>0,'msg'=>'原密码输入错误']);
// 			}
// 			Db::name('member')->where('aid',aid)->where('id',mid)->update(['pwd'=>md5($pwd)]);
// 			return $this->json(['status'=>1,'msg'=>'修改成功']);
// 		}
// 		$rdata = [];
// 		$rdata['haspwd'] = $haspwd;
// 		return $this->json($rdata);
// 	}
		//支付密码设置
	public function paypwd2(){
		if($this->member['paypwd']==''){
			$haspwd = 0;
		}else{
			$haspwd = 1;
		}
		if(request()->isPost()){
			$paypwd = input('post.paypwd');
			$oldpaypwd = input('post.oldpaypwd');
			$tel = input('post.tel');
			$smscode = input('post.smscode');
			if($this->member['paypwd']){
			    if ($oldpaypwd) {
			        if ($this->member['paypwd'] != $oldpaypwd) {
			            return $this->json(['status'=>0,'msg'=>'原密码输入错误']);
			        }
			    }else {
			        if( md5($tel.'-'.$smscode) != cache($this->sessionid.'_smscode') || cache($this->sessionid.'_smscodetimes')>5){
            			cache($this->sessionid.'_smscodetimes',cache($this->sessionid.'_smscodetimes')+1);
            			return $this->json(['status'=>0,'msg'=>'短信验证码错误']);
        		    }
    		    	cache($this->sessionid.'_smscode',null);
                	cache($this->sessionid.'_smscodetimes',null);
			    }
			}
			Db::name('member')->where('aid',aid)->where('id',mid)->update(['paypwd'=>$paypwd]);
			return $this->json(['status'=>1,'msg'=>'修改成功']);
		}
		$rdata = [];
		$rdata['tel'] = $this->member['tel'];
		$rdata['haspwd'] = $haspwd;
		return $this->json($rdata);
	}
	//支付密码设置
	public function paypwd(){
		if($this->member['paypwd']==''){
			$haspwd = 0;
		}else{
			$haspwd = 1;
		}
		if(request()->isPost()){
			$paypwd = input('post.paypwd');
			$oldpaypwd = input('post.oldpaypwd');
			$tel = input('post.tel');
			$smscode = input('post.smscode');
			$data_u = [];
			//验证原支付密码
			if($this->member['paypwd']){
                if ($oldpaypwd) {
		            if(!\app\commons\Member::checkPayPwd($this->member,$oldpaypwd )){
                        return $this->json(['status'=>0,'msg'=>'原密码输入错误']);
                    }
			    }else {
			        if(md5($tel.'-'.$smscode) != cache($this->sessionid.'_smscode') || cache($this->sessionid.'_smscodetimes')>5){
            			cache($this->sessionid.'_smscodetimes',cache($this->sessionid.'_smscodetimes')+1);
            			return $this->json(['status'=>0,'msg'=>'短信验证码错误']);
        		    }
    		    	cache($this->sessionid.'_smscode',null);
                	cache($this->sessionid.'_smscodetimes',null);
			    }
               
            }
			//设置过MD5加密的
			if($this->member['paypwd_rand']){
                $paypwd = md5($paypwd.$this->member['paypwd_rand']);
                $data_u['paypwd'] = $paypwd;
            }else{
			    //未设置过MD5加密的自动生成MD5随机数
			    $rand_str = make_rand_code(2,4);
			    $paypwd = md5($paypwd.$rand_str);
                $data_u['paypwd_rand'] = $rand_str;
                $data_u['paypwd'] = $paypwd;
            }
			Db::name('member')->where('aid',aid)->where('id',mid)->update($data_u);
			return $this->json(['status'=>1,'msg'=>'修改成功']);
		}
		$rdata = [];
		$rdata['tel'] = $this->member['tel'];
		$rdata['haspwd'] = $haspwd;
		return $this->json($rdata);
	}
	public function withdraw(){
		$field = 'withdraw_autotransfer,withdraw,withdrawmin,withdrawfee,withdraw_weixin,withdraw_aliaccount,withdraw_bankcard,withdraw_desc,withdrawmax,day_withdraw_num';
        if(getcustom('comwithdrawdate')){
            $field .= ',comwithdrawdate_money';
        }
		if(getcustom('alipay_auto_transfer')){
			$field .= ',ali_withdraw_autotransfer';
		}
        if(getcustom('pay_adapay')){
            $field .= ',withdraw_adapay';
        }
        if(getcustom('transfer_farsion')){
            $field .= ',withdraw_bankcard_xiaoetong,withdraw_aliaccount_xiaoetong';
        }
        if(getcustom('pay_huifu')){
            $field .= ',withdraw_huifu';
        }
		if(getcustom('money_withdraw_level_sxf')){
			$field .= ',withdrawfee_level';
		}
    if(getcustom('withdraw_mul')){
      $field .= ',withdrawmul';
    }
        if(getcustom('product_givetongzheng')){
            $field .= ',withdraw2tongzheng';
        }
        if(getcustom('extend_linghuoxin')){
            $field .= ',withdraw_aliaccount_linghuoxin,withdraw_bankcard_linghuoxin';
        }
        if(getcustom('withdraw_paycode')){
            $field .= ',withdraw_paycode';
        }
		$set = Db::name('admin_set')->where('aid',aid)->field($field)->find();

        if($set['withdraw'] == 0){
            return $this->json(['status'=>0,'msg'=>t('余额').'提现功能未开启']);
        }

        if(getcustom('member_realname_verify')) {
            $realname_set = Db::name('member_realname_set')->where('aid', aid)->find();
            if ($realname_set['status'] == 1 && $realname_set['withdraw_status'] == 0 && $this->member['realname_status'] != 1){
                return $this->json(['status'=>-4,'msg'=>'未实名认证不可提现','url'=>'/pagesExt/my/setrealname']);
            }
        }
		if(getcustom('transfer_farsion') && ($set['withdraw_bankcard_xiaoetong'] == 1 || $set['withdraw_aliaccount_xiaoetong'] == 1)){
			$xetService = new  \app\commons\Xiaoetong();
			$res_sign = $xetService->getXiaoetongSigning();
			if ($res_sign['status'] == 0){
                return $this->json(['status'=>-4,'msg'=>'需要签约才可提现','url'=>'/pagesA/my/withdrawXiaoetong']);
            }
		}

		if(getcustom('money_withdraw_level_sxf')){
			$withdrawfee_level = json_decode($set['withdrawfee_level'],true);
			$set['withdrawfee'] = $withdrawfee_level[$this->member['levelid']]['sxf'];
		}

		if(request()->isPost()){
			$post = input('post.');

			//验证今天提现了几次
            $nowtime = strtotime(date("Y-m-d",time()));//今日时间戳
            $daywithdrawnum   = 'daywithdrawnum'.mid.$nowtime;//会员今日时间参数
            $day_withdraw_num = cache($daywithdrawnum);//获取会员提现次数
            if($set['day_withdraw_num']<0){
                return $this->json(['status'=>0,'msg'=>'暂时不可提现']);
            }else if($set['day_withdraw_num']>0){
                if($day_withdraw_num && !empty($day_withdraw_num)){
                    $daynum = $day_withdraw_num+1;
                    if($daynum>$set['day_withdraw_num']){
                        return $this->json(['status'=>0,'msg'=>'今日申请提现次数已满，请明天继续申请提现']);
                    }
                }
            }
			if(getcustom('member_lock')){
				$field = 'lock_withdraw_givemoney';
				$userinfo = Db::name('member')->where('id',mid)->field($field)->find();
				if($userinfo['lock_withdraw_givemoney'] == 1){                
					return $this->json(['status'=>0,'msg'=>'账号已锁定，请联系管理员处理！']);
				}            
			}

            if(getcustom('comwithdrawdate') && $set['comwithdrawdate_money'] && $set['comwithdrawdate_money']!='0'){
                $comwithdrawdate = explode(',',$set['comwithdrawdate_money']);
                $indate = false;
                $nowdata = date('d');
                foreach($comwithdrawdate as $date){
                    if($date == $nowdata || '0'.$date == $nowdata){
                        $indate = true;
                        break;
                    }
                }
                if(!$indate) return $this->json(['status'=>0,'msg'=>'不在可提现日期内']);
            }

			if($post['paytype']=='支付宝'){
                if($set['withdraw_aliaccount'] == 0){
                    return $this->json(['status'=>0,'msg'=>'支付宝提现功能未开启']);
                }

                if(!$this->member['aliaccount'] || !$this->member['aliaccountname']){
                	return $this->json(['status'=>0,'msg'=>'请先设置支付宝账号']);
                }
			}
			if(getcustom('transfer_farsion')){
				if($post['paytype']=='小额通支付宝'){
					if(!$this->member['aliaccount'] || !$this->member['aliaccountname']){
						return $this->json(['status'=>0,'msg'=>'请先设置支付宝账号']);
					}
				}
				if($post['paytype']=='小额通银行卡' && ($this->member['bankname']==''||$this->member['bankcarduser']==''||$this->member['bankcardnum']=='')){
					if($set['withdraw_bankcard'] == 0)
						return $this->json(['status'=>0,'msg'=>'银行卡提现功能未开启']);
                    return $this->json(['status'=>0,'msg'=>'请先设置完整银行卡信息']);
				}
			}
            if(getcustom('extend_linghuoxin')){
                if($post['paytype']=='灵活薪支付宝' || $post['paytype']=='灵活薪银行卡'){
                    if($post['paytype']=='灵活薪支付宝'){
                        if($set['withdraw_aliaccount_linghuoxin'] != 1){
                            return $this->json(['status'=>0,'msg'=>'灵活薪支付宝提现功能未开启']);
                        }
                        if(empty($this->member['aliaccount']) || empty($this->member['aliaccountname'])){
                            return $this->json(['status'=>0,'msg'=>'请先设置支付宝账号']);
                        }
                    }
                    if($post['paytype']=='灵活薪银行卡'){
                        if($set['withdraw_bankcard_linghuoxin'] != 1){
                            return $this->json(['status'=>0,'msg'=>'灵活薪银行卡提现功能未开启']);
                        }
                        if(empty($this->member['bankname']) || empty($this->member['bankcarduser'])|| empty($this->member['bankcardnum'])){
                            return $this->json(['status'=>0,'msg'=>'请先设置完整银行卡信息']);
                        }
                    }
                    //查看是否签约
                    if(!empty($this->member['usercard'])){
                        $getchecksign = \app\customs\LinghuoxinCustom::getchecksign(aid,0,$this->member['usercard']);
                        if($getchecksign && $getchecksign['status'] == 1){
                        	Db::name('member_linghuoxin_signlog')->where('mid',mid)->where('usercard',$this->member['usercard'])->update(['status'=>$getchecksign['data']['status'],'updatetime'=>time()]);
                            if($getchecksign['data']['status'] == 0){
                                return $this->json(['status'=>-4,'msg'=>'需要签约才可提现','url'=>'/pagesB/my/linghuoxinsign']);
                            }else if($getchecksign['data']['status'] == 1){
                                return $this->json(['status'=>0,'msg'=>'已实名认证，等待签约中']);
                            }
                        }else{
                            //return $this->json($getchecksign);
                            return $this->json(['status'=>-4,'msg'=>'需要签约才可提现','url'=>'/pagesB/my/linghuoxinsign']);
                        }
                    }else{
                        return $this->json(['status'=>-4,'msg'=>'需要签约才可提现','url'=>'/pagesB/my/linghuoxinsign']);
                    }
                }
            }
            if(getcustom('withdraw_paycode')){
                if($post['paytype']=='收款码'){
                    if(!$this->member['wxpaycode'] && !$this->member['alipaycode']){
                        return $this->json(['status'=>0,'msg'=>'请先设置一个收款码']);
                    }
                }
            }

			if($post['paytype']=='银行卡' &&  getcustom('yx_gift_pack')){
				$bank = Db::name('member_bank')->where('aid',aid)->where('mid',mid)->where('isdefault',1)->find();
				if(!$bank){
					return $this->json(['status'=>0,'msg'=>'请先设置完整银行卡信息']);
				}
				$this->member['bankname'] = $bank['bankname'];
				$this->member['bankcarduser'] = $bank['bankcarduser'];
				$this->member['bankcardnum'] = $bank['bankcardnum'];
				$this->member['bankaddress'] = $bank['bankaddress'];
			}else{
				if($post['paytype']=='银行卡' && ($this->member['bankname']==''||$this->member['bankcarduser']==''||$this->member['bankcardnum']=='')){
					if($set['withdraw_bankcard'] == 0)
						return $this->json(['status'=>0,'msg'=>'银行卡提现功能未开启']);
					return $this->json(['status'=>0,'msg'=>'请先设置完整银行卡信息']);
				}
			}
            if($post['paytype']=='银行卡' && $set['withdraw_huifu'] == 1 && ($this->member['realname']==''||$this->member['tel']==''||$this->member['usercard']==''||$this->member['huifu_id']==''||$this->member['bankname']==''||$this->member['bankcarduser']==''||$this->member['bankcardnum']=='')){
                return $this->json(['status'=>0,'msg'=>'请先设置完整银行卡信息']);
            }
            if($post['paytype'] == '微信钱包' && $set['withdraw_weixin'] == 0){
                return $this->json(['status'=>0,'msg'=>'微信钱包提现功能未开启']);
            }
           if(getcustom('withdraw_mul')){
                if($set['withdrawmul']>0 && !isMulInt($money, $set['withdrawmul'])){
                  return $this->json(['status'=>0,'msg'=>'提现金额必须为'.$set['withdrawmul'].'整数倍']);
                }
              }
            $pwd_check = $this->sysset['money_transfer_pwd'];
			if($pwd_check) {
				if(!$this->member['paypwd']) {
					return $this->json(['status'=>0,'msg'=>'请先设置支付密码','set_paypwd'=>1]);
				}
				$pay_pwd = input('paypwd')?:'';
				if(!\app\commons\Member::checkPayPwd($this->member,$pay_pwd )) {
					return $this->json(['status'=>0,'msg'=>'支付密码输入错误']);
				}
			}

			$money = $post['money'];
			if($money<=0 || $money < $set['withdrawmin']){
				return $this->json(['status'=>0,'msg'=>'提现金额必须大于'.($set['withdrawmin']?$set['withdrawmin']:0)]);
			}
			if($money > $this->member['money']){
				return $this->json(['status'=>0,'msg'=>'可提现'.t('余额').'不足']);
			}
			if($set['withdrawmax']>0 && $money > $set['withdrawmax']){
				return $this->json(['status'=>0,'msg'=>'提现金额过大，单笔'.t('余额').'提现最高金额为'.$set['withdrawmax'].'元']);
			}
			

			//验证小数点后两位
            $money_arr = explode('.',$money);
            if($money_arr && $money_arr[1]){
                $dot_len = strlen($money_arr[1]);
                if($dot_len>2){
                    return $this->json(['status'=>0,'msg'=>'提现金额最小位数为小数点后两位']);
                }
            }
        	if($this->sysset['withdraw_rate']>0 ){
        	    $withdraw_rate = $this->sysset['withdraw_rate'];
		    	if($money%$withdraw_rate!=0)return $this->json(['status'=>0,'msg'=>'金额需是'.$withdraw_rate.'的倍数']);
			}
			
		   $comwithdrawnum = 100;
		   if ($comwithdrawnum > 0) {
			    $tim = strtotime(date('Y-m-d',time()));
			    $count = Db::name('member_withdrawlog')->where('mid',mid)->where('createtime','>=',$tim)->count();
			    if ($count >= $comwithdrawnum) {
			       	return $this->json(['status'=>0,'msg'=>'每日最多提现'.$comwithdrawnum.'次']);
			    }
			}
			$ordernum = date('ymdHis').aid.rand(1000,9999);
			$record['aid'] = aid;
			$record['mid'] = mid;
			$record['createtime']= time();
            $real_money = $money*(1-$set['withdrawfee']*0.01);
			if($real_money <= 0) {
                return $this->json(['status'=>0,'msg'=>'提现金额有误']);
            }
       //提现到账通证
            if(getcustom('product_givetongzheng')){
                $withdraw2tongzheng = $set['withdraw2tongzheng'];
                $tongzheng_num = bcmul($money,$withdraw2tongzheng/100,3);
                $real_money = bcsub($real_money,$tongzheng_num,2);
                if($real_money <= 0) {
                    return $this->json(['status'=>0,'msg'=>'提现金额有误']);
                }
                $record['tongzheng'] = $tongzheng_num;
            }
            $record['money'] = round($real_money,2);
			$record['txmoney'] = $money;
			if($post['paytype']=='支付宝'){
				$record['aliaccountname'] = $this->member['aliaccountname'];
				$record['aliaccount'] = $this->member['aliaccount'];
			}
			if($post['paytype']=='银行卡'){
				$record['bankname'] = $this->member['bankname'] . ($this->member['bankaddress'] ? ' '.$this->member['bankaddress'] : '');
				$record['bankcarduser'] = $this->member['bankcarduser'];
				$record['bankcardnum'] = $this->member['bankcardnum'];
			}
            if($post['paytype']=='银行卡' && $set['withdraw_huifu'] == 1){
                $record['huifu_id'] = $this->member['huifu_id'];
            }
			if(getcustom('transfer_farsion')){
				$account_no = '';
				$xiaoetong_type = 1;
				if($post['paytype']=='小额通支付宝'){
					$record['aliaccountname'] = $this->member['aliaccountname'];
					$record['aliaccount'] = $this->member['aliaccount'];
					$account_no = $record['aliaccount'];
					$xiaoetong_type = 2;
				}
				if($post['paytype']=='小额通银行卡'){
					$record['bankname'] = $this->member['bankname'] . ($this->member['bankaddress'] ? ' '.$this->member['bankaddress'] : '');
					$record['bankcarduser'] = $this->member['bankcarduser'];
					$record['bankcardnum'] = $this->member['bankcardnum'];
					$account_no = $record['bankcardnum'];
					$xiaoetong_type = 1;
				}
			}
            if(getcustom('extend_linghuoxin')){
                if($post['paytype']=='灵活薪支付宝' || $post['paytype']=='灵活薪银行卡'){
                    //查看账号余额
                    // $getbalance = \app\customs\LinghuoxinCustom::getbalance(aid);
                    // if($getbalance['status'] == 0){
                    //     return $this->json($getchecksign);
                    // }
                    // if($record['money']>$getbalance['data']['availableBalance']){
                    //     return $this->json(['status'=>0,'msg'=>'灵活薪账号金额不足，请减少提现金额后重试']);
                    // }
                    if($post['paytype']=='灵活薪支付宝'){
                        $record['aliaccountname'] = $this->member['aliaccountname'];
                        $record['aliaccount'] = $this->member['aliaccount'];
                    }
                    if($post['paytype']=='灵活薪银行卡'){
                        $record['bankname']    = $this->member['bankname'] . ($this->member['bankaddress'] ? ' '.$this->member['bankaddress'] : '');
                        $record['bankcarduser']= $this->member['bankcarduser'];
                        $record['bankcardnum'] = $this->member['bankcardnum'];
                    }
                }
            }
            if(getcustom('withdraw_paycode')){
                if($post['paytype']=='收款码'){
                    if($this->member['wxpaycode']){
                        $record['wxpaycode'] = $this->member['wxpaycode'];
                    }
                    if($this->member['alipaycode']){
                        $record['alipaycode'] = $this->member['alipaycode'];
                    }
                }
            }
			$record['ordernum'] = $ordernum;
			$record['paytype'] = $post['paytype'];
			$record['platform'] = platform;
			$recordid = Db::name('member_withdrawlog')->insertGetId($record);
			if($recordid){
                //记录今天提现了几次
                if(!$day_withdraw_num || empty($day_withdraw_num)){
                    cache($daywithdrawnum,1,86400);
                }else{
                    $daynum = $day_withdraw_num+1;
                    cache($daywithdrawnum,$daynum,86400);
                }
            }

			\app\commons\Member::addmoney(aid,mid,-$money,t('余额').'提现');

			$tmplcontent = array();
			$tmplcontent['first'] = '有客户申请'.t('余额').'提现';
			$tmplcontent['remark'] = '点击进入查看~';
			$tmplcontent['keyword1'] = $this->member['nickname'];
			$tmplcontent['keyword2'] = date('Y-m-d H:i');
			$tmplcontent['keyword3'] = $money.'元';
			$tmplcontent['keyword4'] = $post['paytype'];
			\app\commons\Wechat::sendhttmpl(aid,0,'tmpl_withdraw',$tmplcontent,m_url('admin/finance/withdrawlog'));
			
			$tmplcontent = [];
			$tmplcontent['name3'] = $this->member['nickname'];
			$tmplcontent['amount1'] = $money.'元';
			$tmplcontent['date2'] = date('Y-m-d H:i');
			$tmplcontent['thing4'] = '提现到'.$post['paytype'];
			\app\commons\Wechat::sendhtwxtmpl(aid,0,'tmpl_withdraw',$tmplcontent,'admin/finance/withdrawlog');
			//小额通提现
			if(getcustom('transfer_farsion')){
				if($set['withdraw_autotransfer'] &&  ($post['paytype'] == '小额通支付宝' || $post['paytype'] == '小额通银行卡' )){
					
					$xetService = new  \app\commons\Xiaoetong();
					//导入数据
                    $record['id'] = $recordid;
					$xet_res = $xetService->sendData($record,$this->member,'余额提现');	
					//print_r($res);die;
					if($xet_res['code'] == 0){
						Db::name('member_withdrawlog')->where('id',$recordid)->update(['status' => 1]);			
						return $this->json(['status'=>1,'msg'=>'提交成功,请等待打款','data'=>[]]);					
					}else{
						\app\commons\Member::addmoney(aid,mid,$money,t('余额').'提现返还');
						Db::name('member_withdrawlog')->where('id',$recordid)->update(['status' => 2,'reason'=>'快商小额通推送失败'.$xet_res['msg']]);
						return $this->json(['status'=>1,'msg'=>'提现失败','data'=>[]]);
					}
				}
            }

            if(getcustom('extend_linghuoxin')){
                //灵活薪提现
                if($set['withdraw_autotransfer'] && ($post['paytype'] == '灵活薪支付宝' || $post['paytype'] == '灵活薪银行卡' )){
                    $gopay = \app\customs\LinghuoxinCustom::gopay(aid,0,$this->member,$recordid,$record,$post['paytype'],1);
                    if($gopay && $gopay['status'] == 1){
                    	$updata = [];
                        $updata['status']   = 1;
                        $updata['taskNo']   = $gopay['data']['taskNo'];
                        $updata['taskdata'] = json_encode($gopay['data']);
                    	Db::name('member_withdrawlog')->where('id',$recordid)->update($updata);
                        return $this->json(['status'=>1,'msg'=>'提交成功,请等待打款','data'=>[]]);
                    }else{
                        $msg = $gopay && $gopay['msg']?$gopay['msg']:'';
                        Db::name('member_withdrawlog')->where('id',$recordid)->update(['reason'=>'灵活薪推送失败'.$msg]);
                        return $this->json(['status'=>1,'msg'=>'提交成功,请等待打款']);
                        // $msg = $gopay && $gopay['msg']?$gopay['msg']:'';
                        // \app\commons\Member::addmoney(aid,mid,$money,t('余额').'提现返还');
                        // Db::name('member_withdrawlog')->where('id',$recordid)->update(['status' => 2,'reason'=>'灵活薪推送失败'.$msg]);
                        // return $this->json(['status'=>0,'msg'=>'提现失败','data'=>[]]);
                    }
                }
            }

			if($set['withdraw_autotransfer'] && ($post['paytype'] == '微信钱包' || $post['paytype'] == '银行卡')){
                Db::name('member_withdrawlog')->where('id',$recordid)->update(['status' => 1]);
				$rs = \app\commons\Wxpay::transfers(aid,mid,$record['money'],$record['ordernum'],platform,t('余额').'提现');
				if($rs['status']==0){
					return $this->json(['status'=>1,'msg'=>'提交成功,请等待打款']);
				}else{
                    Db::name('member_withdrawlog')->where('id',$recordid)->update(['status' => 3]);
					Db::name('member_withdrawlog')->where('aid',aid)->where('id',$recordid)->update(['status'=>3,'paytime'=>time(),'paynum'=>$rs['resp']['payment_no']]);
					//提现成功通知
					$tmplcontent = [];
					$tmplcontent['first'] = '您的提现申请已打款，请留意查收';
					$tmplcontent['remark'] = '请点击查看详情~';
					$tmplcontent['money'] = (string) round($record['money'],2);
					$tmplcontent['timet'] = date('Y-m-d H:i',$record['createtime']);
                    $tempconNew = [];
                    $tempconNew['amount2'] = (string) round($record['money'],2);//提现金额
                    $tempconNew['time3'] = date('Y-m-d H:i',$record['createtime']);//提现时间
					\app\commons\Wechat::sendtmpl(aid,$record['mid'],'tmpl_tixiansuccess',$tmplcontent,m_url('pages/my/usercenter'),$tempconNew);
					//订阅消息
					$tmplcontent = [];
					$tmplcontent['amount1'] = $record['money'];
					$tmplcontent['thing3'] = '微信打款';
					$tmplcontent['time5'] = date('Y-m-d H:i');
					
					$tmplcontentnew = [];
					$tmplcontentnew['amount3'] = $record['money'];
					$tmplcontentnew['phrase9'] = '微信打款';
					$tmplcontentnew['date8'] = date('Y-m-d H:i');
					\app\commons\Wechat::sendwxtmpl(aid,$record['mid'],'tmpl_tixiansuccess',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);
					//短信通知
					if($this->member['tel']){
						\app\commons\Sms::send(aid,$this->member['tel'],'tmpl_tixiansuccess',['money'=>$record['money']]);
					}
					return $this->json(['status'=>1,'msg'=>$rs['msg']]);
				}
			}
			if(getcustom('alipay_auto_transfer')){
				if($set['ali_withdraw_autotransfer'] && $post['paytype'] == '支付宝'){
	                //Db::name('member_withdrawlog')->where('id',$recordid)->update(['status' => 1]);
					$rs = \app\commons\Alipay::transfers(aid,$record['ordernum'],$record['money'],t('余额').'提现',$this->member['aliaccount'],$this->member['aliaccountname'],t('余额').'提现');
					if($rs['status']==0){
						$sub_msg = $rs['sub_msg']?$rs['sub_msg']:'';
	                    if($sub_msg){
	                       Db::name('member_withdrawlog')->where('aid',aid)->where('id',$recordid)->update(['reason'=>$sub_msg]);
	                    }
						return $this->json(['status'=>1,'msg'=>'提交成功,请等待打款']);
					}else{
	                    Db::name('member_withdrawlog')->where('id',$recordid)->update(['status' => 3]);
						Db::name('member_withdrawlog')->where('aid',aid)->where('id',$recordid)->update(['status'=>3,'paytime'=>time(),'paynum'=>$rs['pay_fund_order_id']]);
						//提现成功通知
						$tmplcontent = [];
						$tmplcontent['first'] = '您的提现申请已打款，请留意查收';
						$tmplcontent['remark'] = '请点击查看详情~';
						$tmplcontent['money'] = (string) round($record['money'],2);
						$tmplcontent['timet'] = date('Y-m-d H:i',$record['createtime']);
	                    $tempconNew = [];
	                    $tempconNew['amount2'] = (string) round($record['money'],2);//提现金额
	                    $tempconNew['time3'] = date('Y-m-d H:i',$record['createtime']);//提现时间
						\app\commons\Wechat::sendtmpl(aid,$record['mid'],'tmpl_tixiansuccess',$tmplcontent,m_url('pages/my/usercenter'),$tempconNew);
						//订阅消息
						$tmplcontent = [];
						$tmplcontent['amount1'] = $record['money'];
						$tmplcontent['thing3'] = '支付宝打款';
						$tmplcontent['time5'] = date('Y-m-d H:i');
						$tmplcontentnew = [];
						$tmplcontentnew['amount3'] = $record['money'];
						$tmplcontentnew['phrase9'] = '支付宝打款';
						$tmplcontentnew['date8'] = date('Y-m-d H:i');
						\app\commons\Wechat::sendwxtmpl(aid,$record['mid'],'tmpl_tixiansuccess',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);
						//短信通知
						if($this->member['tel']){
							\app\commons\Sms::send(aid,$this->member['tel'],'tmpl_tixiansuccess',['money'=>$record['money']]);
						}
						return $this->json(['status'=>1,'msg'=>$rs['msg']]);
					}
				}
			}

			return $this->json(['status'=>1,'msg'=>'提交成功,请等待打款']);
		}

        $member_field = 'id,money,aliaccount,bankname,bankcarduser,bankcardnum';
        if(getcustom('pay_huifu')){
            $member_field .= ',realname,usercard,usercard_begin_date,bank_province_code,bank_city_code,tel';
        }
        $userinfo = Db::name('member')->where('id',mid)->field($member_field)->find();
		//订阅消息
		$wx_tmplset = Db::name('wx_tmplset')->where('aid',aid)->find();
		$tmplids = [];
		if($wx_tmplset['tmpl_tixiansuccess_new']){
			$tmplids[] = $wx_tmplset['tmpl_tixiansuccess_new'];
		}elseif($wx_tmplset['tmpl_tixiansuccess']){
			$tmplids[] = $wx_tmplset['tmpl_tixiansuccess'];
		}
		if($wx_tmplset['tmpl_tixianerror_new']){
			$tmplids[] = $wx_tmplset['tmpl_tixianerror_new'];
		}elseif($wx_tmplset['tmpl_tixianerror']){
			$tmplids[] = $wx_tmplset['tmpl_tixianerror'];
		}
        if(getcustom('pay_adapay')){
            $adapay = Db::name('adapay_member')->where('aid',aid)->where('mid',mid)->find();
            $userinfo['to_set_adapay'] = 0;
            if(!$adapay ||  !$adapay['settle_account_id']){
                $userinfo['to_set_adapay'] = 1;
            }
        }
		$rdata = [];

		$selectbank = false;
		if(getcustom('yx_gift_pack')){
			$selectbank = true;
			//选择默认银行卡
			$bank = Db::name('member_bank')->where('aid',aid)->where('mid',mid)->where('isdefault',1)->find();
			if($bank) $bank['bankcardnum'] = substr($bank['bankcardnum'],0,3).'******'.substr($bank['bankcardnum'],-4);
			$rdata['bank'] = $bank;
		}
		$rdata['selectbank'] =  $selectbank;
			$moeny_weishu = 2;
		if(getcustom('member_money_weishu')){
            $moeny_weishu = Db::name('admin_set')->where('aid',aid)->value('member_money_weishu');
        }
        $userinfo['money'] = dd_money_format($userinfo['money'],$moeny_weishu);
        if($this->member['paypwd']=='') {
			$userinfo['haspwd'] = 0;
		} else {
			$userinfo['haspwd'] = 1;
		}
		$userinfo['paycheck'] = $this->sysset['money_transfer_pwd'];
		
		$rdata['userinfo'] = $userinfo;
		$rdata['sysset'] = $set;
		$rdata['tmplids'] = $tmplids;
		return $this->json($rdata);
	}

	public function moneylog(){
		$st = input('param.st');
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$pernum = 20;
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['mid','=',mid];
		if($st == 1){//充值记录
			$datalist = Db::name('recharge_order')->field("id,money,`status`,from_unixtime(createtime) createtime")->where($where)->where('status=1')->page($pagenum,$pernum)->order('id desc')->select()->toArray();
			if(!$datalist) $datalist = [];
		}elseif($st ==2){//提现记录
			$datalist = Db::name('member_withdrawlog')->field("id,money,txmoney,`status`,from_unixtime(createtime) createtime,reason")->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
			if(!$datalist) $datalist = [];
		}else{ //余额明细
			$datalist = Db::name('member_moneylog')->field("id,money,`after`,from_unixtime(createtime) createtime,remark")->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
			if(!$datalist) $datalist = [];
			foreach($datalist as $k=>$v){
				if(strpos($v['remark'],'商家充值，') === 0){
					$datalist[$k]['remark'] = '商家充值';
				}
				$moeny_weishu = 2;
				if(getcustom('member_money_weishu')){
		            $moeny_weishu = Db::name('admin_set')->where('aid',aid)->value('member_money_weishu');
		        }
		        $datalist[$k]['money'] = dd_money_format($v['money'],$moeny_weishu);
		        $datalist[$k]['after'] = dd_money_format($v['after'],$moeny_weishu);
			}
		}
		if($pagenum == 1){
			$canwithdraw = Db::name('admin_set')->where('aid',aid)->value('withdraw');
		}
        $admin_set = Db::name('admin_set')->field('moneypay,recharge,withdraw')->where('aid',aid)->find();

        $showstatus = [];
        $showstatus[] = $admin_set['moneypay'] ;
        $showstatus[] = $admin_set['recharge'];
        $showstatus[] = $admin_set['withdraw'];
       
		return $this->json(['status'=>1,'data'=>$datalist,'canwithdraw'=>$canwithdraw,'showstatus' => $showstatus]);
	}
    public function gongxian_log(){
        if(getcustom('member_gongxian')){
            $pagenum = input('post.pagenum');
            $st = input('post.st');
            if(!$pagenum) $pagenum = 1;
            $pernum = 20;
            $where = [];
            $where[] = ['aid','=',aid];
            $where[] = ['mid','=',mid];
            $datalist = Db::name('member_gongxianlog')->field('id,value,after,remark,from_unixtime(createtime)createtime')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
            if(!$datalist) $datalist = [];
            if(request()->isPost()){

                return $this->json(['status'=>1,'data'=>$datalist] );
            }

            $count = Db::name('member_gongxianlog')->where($where)->count();

            $rdata = [];
            $rdata['count'] = $count;
            $rdata['datalist'] = $datalist;
            $rdata['pernum'] = $pernum;
            $rdata['st'] = $st;
            return $this->json($rdata);
        }
    }
	public function scorelog(){
		$pagenum = input('post.pagenum');
        $st = input('post.st');
		if(!$pagenum) $pagenum = 1;
		$pernum = 20;
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['mid','=',mid];
		$datalist = Db::name('member_scorelog')->field('id,score,after,remark,from_unixtime(createtime)createtime')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
		if(!$datalist) $datalist = [];
        $score_weishu = 0;
        if(getcustom('score_weishu')){
            $score_weishu = Db::name('admin_set')->where('aid',aid)->value('score_weishu');
            $score_weishu = $score_weishu?$score_weishu:0;
        }
		if($datalist){
		    foreach($datalist as $k=>$v){
		        $datalist[$k]['score'] = dd_money_format($v['score'],$score_weishu);
            }
        }
		if(request()->isPost()){
		    if($pagenum == 1) {
                $scoreTransfer = false;
                $set = [];
                $set = Db::name('admin_set')->where('aid', aid)->find();
                if(getcustom('score_transfer') && $set['score_transfer']){
                    $gettj = explode(',',$set['score_transfer_gettj']);
                    if(in_array('-1',$gettj) || in_array($this->member['levelid'],$gettj)){
                        $scoreTransfer = true;
                    }
                }elseif ($set['score_transfer']==1) {
                     $scoreTransfer = true;
                }
                $scoreWithdraw = $set['score_withdraw'] ? true : false;
            }
            $member_score = dd_money_format($this->member['score'],$score_weishu);
		    $score_withdraw = dd_money_format($this->member['score_withdraw'],$score_weishu);
            return $this->json(['status'=>1,'data'=>$datalist,'myscore'=>$member_score,
                'score_withdraw'=>$score_withdraw, 'scoreTransfer' => $scoreTransfer,'scoreWithdraw' => $scoreWithdraw, 'set' => $set] );
		}

		$count = Db::name('member_scorelog')->where($where)->count();

		$rdata = [];
		$rdata['count'] = $count;
		$rdata['datalist'] = $datalist;
		$rdata['pernum'] = $pernum;
		$rdata['st'] = $st;
		$member_score = dd_money_format($this->member['score'],$score_weishu);
		$rdata['myscore'] = $member_score;
        return $this->json($rdata);
	}
	
	public function scoreTransfer() {
         $mid = input('param.mid/d',0);
            //给他人充值 转账
            //        $info = Db::name('member_recharge')->where('aid', aid)->where('mid', mid)->find();
//        if(empty($info)) {
//            return $this->json(['status'=>0,'msg'=>'您无此权限操作']);
//        }
        $score_weishu = 0;
        if(getcustom('score_weishu')){
            $score_weishu = Db::name('admin_set')->where('aid',aid)->value('score_weishu');
            $score_weishu = $score_weishu?$score_weishu:0;
        }
        $set = Db::name('admin_set')->where('aid',aid)->find();
        if($set['score_transfer'] != 1) {
            return $this->json(['status'=>0,'msg'=>'未开启此功能']);
        }
        if(request()->isPost()){
            $mobile = input('post.mobile');
            $mid = input('post.mid/d');
            $score = input('post.score');
            $score = dd_money_format($score,$score_weishu);
            if ($score < 0.01){
                return $this->json(['status'=>0,'msg'=>'请输入正确的数量，最小数量为：0.01']);
            }
            if($this->sysset['transfer_rate']>0 ){
        	    $transfer_rate = $this->sysset['transfer_rate'];
		    	if($score%$transfer_rate!=0)return $this->json(['status'=>0,'msg'=>'数量需是'.$transfer_rate.'的倍数']);
			}
            if (input('?post.mobile') && !empty($mobile)) {
                $member = Db::name('member')->where('aid', aid)->where('tel', $mobile)->find();
            }
            if (input('?post.mid') && $mid > 0) {
                $member = Db::name('member')->where('aid', aid)->where('id', $mid)->find();
            }
            if(!$member) return $this->json(['status'=>0,'msg'=>'未找到该'.t('会员')]);
            $user_id = $member['id'];

            if ($user_id == mid) {
                return $this->json(['status'=>0,'msg'=>'不能转账给自己']);
            }
            if($set['transfer_range'] == 1) {
                //所有上下级
                $isparent = false;
                if(in_array($user_id,explode(',',$this->member['path']))){
                    $isparent = true;
                }
                if(!$isparent){
                    if(!in_array(mid,explode(',',$member['path']))){
                        return $this->json(['status'=>0,'msg'=>'仅限转账给上下级'.t('会员')]);
                    }
                }
            }
            
            
            $score_transfer_fee = round($score*$set['score_transfer_fee']*0.01,2);
			if ($this->sysset['transfer_fee_type']==1) {
			   $toscore = round($score - $score_transfer_fee,2);
			} else {
			   $toscore = $score;
			   $score = round($score + $score_transfer_fee,2);
			}
		
            if ($score > $this->member['score']){
                return $this->json(['status'=>0,'msg'=>'您的'.t('积分').'不足']);
            }
            //验证支付密码
            $pwd_check = $set['transfer_pwd'];
            if($pwd_check){
                if(!$this->member['paypwd']){
                    return $this->json(['status'=>0,'msg'=>'请先设置支付密码','set_paypwd'=>1]);
                }
                $pay_pwd = input('paypwd')?:'';
                if(!\app\commons\Member::checkPayPwd($this->member,$pay_pwd )){
                    return $this->json(['status'=>0,'msg'=>'支付密码输入错误']);
                }
            }
            $midMsg = sprintf("转账给：%s",$member['nickname']);
            $toMidMsg = sprintf("来自%s的转账", $this->member["nickname"]);
            if ($set['transfer_range'] == 2) {
                $midMsg = sprintf("转给：%s",$member['nickname']);
                $toMidMsg = sprintf("来自%s的转账", $this->member["nickname"]);
            }
			$rs = \app\commons\Member::addscore(aid,mid,$score * -1, $midMsg);
            if ($rs['status'] == 1) {
                \app\commons\Member::addscore(aid,$user_id,$toscore,$toMidMsg);
                //, '', 0, $this->mid
            }else{
				 return $this->json(['status'=>0, 'msg' => '转账失败']);
			}
            return $this->json(['status'=>1, 'msg' => '转账成功', 'url'=>'/pages/my/usercenter']);
        }
        $tomember = [];
        if($mid){
            $tomember = Db::name('member')->where('aid',aid)->where('id',$mid)->field('id,score,nickname,headimg')->find();
        }
        if($this->member['paypwd']=='') {
			$rdata['haspwd'] = 1;
		} else {
			$rdata['haspwd'] = 0;
		}
        $rdata['paycheck'] = $set['transfer_pwd'] ? true : false;
        $rdata['status'] = 1;
        $rdata['myscore'] = dd_money_format($this->member['score'],$score_weishu);;
        $rdata['scoreList'] = [];//可选金额列表
        $rdata['tomember'] = $tomember?$tomember:['nickname'=>''];//转给谁
       
        $rdata['transfer_type'] = $set['transfer_type'] ? explode(',',$set['transfer_type']) : [];
        $rdata['score_transfer_fee'] =$set['score_transfer_fee'];
        return $this->json($rdata);
	}
	
    public function scoreTransfernew()
    {
        $setrs = [];
        if(getcustom('score_transfer_wxqrcode')){
            $setrs['score_transfer_wxqrcode'] = true;
        }
        $score_weishu = 0;
        if(getcustom('score_weishu')){
            $score_weishu = Db::name('admin_set')->where('aid',aid)->value('score_weishu');
            $score_weishu = $score_weishu?$score_weishu:0;
        }
        if(getcustom('score_transfer') || getcustom('score_friend_transfer')){
            $mid = input('param.mid/d',0);
            //积分转送
            $set = Db::name('admin_set')->where('aid', aid)->find();
            if ($set['score_transfer'] != 1) {
                return $this->json(['status'=>0,'msg'=>t('积分').'转赠未开启']);
            }
            $gettj = explode(',',$set['score_transfer_gettj']);
            if(!in_array('-1',$gettj) && !in_array($this->member['levelid'],$gettj)){ //不是所有人
                return $this->json(['status'=>0,'msg'=>'您没有权限']);
            }
            if(getcustom('score_friend_transfer') && $set['score_transfer_range'] == 2) {
                //是不是好友
                $hasFriend  = Db::name('friend')->where('aid',aid)->where('mid',$this->mid)->where('fmid',$mid)->count();
                if(!$hasFriend){
                    return $this->json(['status'=>0,'msg'=>'非好友，不可转增']);
                }
            }
            $sxf_ratio = 0; //积分手续费比例
            $score_transfer_sxf = 0; //积分手续费开关
            if(getcustom('score_transfer_sxf')){
                $score_transfer_sxf = $set['score_transfer_sxf'];
                $sxf_ratio = Db::name('member_level')->where('id',$this->member['levelid'])->value('score_transfer_sxf_ratio');
                $sxf_ratio = $sxf_ratio/100;
            }
            if(request()->isPost()){
                $mobile = input('post.mobile');
                $mid = input('post.mid/d');
                $integral = input('post.integral');
                $integral = dd_money_format($integral,$score_weishu);
                if ($integral <= 0){
                    return $this->json(['status'=>0,'msg'=>'请输入正确的'.t('积分').'数量']);
                }
                if (input('?post.mobile')) {
                    $info = Db::name('member')->where('aid', aid)->where('tel', $mobile)->find();
                }
                if (input('?post.mid')) {
                    $info = Db::name('member')->where('aid', aid)->where('id', $mid)->find();
                }

                if(!$info) return $this->json(['status'=>0,'msg'=>'未找到该'.t('会员')]);
                $user_id = $info['id'];

                if ($info['id'] == mid) {
                    return $this->json(['status'=>0,'msg'=>'不能转赠给自己']);
                }
                if($set['score_transfer_range'] == 1) {
                    //所有上下级
                    $isparent = false;
                    if(in_array($user_id,explode(',',$this->member['path']))){
                        $isparent = true;
                    }
                    if(!$isparent){
                        if(!in_array(mid,explode(',',$info['path']))){
                            return $this->json(['status'=>0,'msg'=>'仅限转赠给上下级'.t('会员')]);
                        }
                    }
                }
                if ($integral > $this->member['score']){
                    return $this->json(['status'=>0,'msg'=>'您的'.t('积分').'数量不足']);
                }
                //验证支付密码
                $pwd_check = $set['score_transfer_pwd'];
                if($pwd_check){
                    if(!$this->member['paypwd']){
                        return $this->json(['status'=>0,'msg'=>'请先设置支付密码','set_paypwd'=>1]);
                    }
                    $pay_pwd = input('paypwd')?:'';
                    if(!\app\commons\Member::checkPayPwd($this->member,$pay_pwd )){
                        return $this->json(['status'=>0,'msg'=>'支付密码输入错误']);
                    }
                }

                //积分手续费
                if(getcustom('score_transfer_sxf')){
                    //0:关闭 1:开启
                    if(isset($this->sysset['score_transfer_sxf']) && $this->sysset['score_transfer_sxf'] == 1){
                        $ordernum = date('YmdHis').rand(1000,9999);
                        //计算手续费
                        $sxf = bcmul($integral,$sxf_ratio,2);
                        $dataOrder = [];
                        $dataOrder['aid'] = aid;
                        $dataOrder['mid'] = mid;
                        $dataOrder['receive_mid'] = $mid;
                        $dataOrder['score_num'] = $integral;
                        $dataOrder['transfer_sxf'] = $sxf;
                        $dataOrder['ordernum'] = $ordernum;
                        $dataOrder['createtime'] = time();
                        $orderid = Db::name('score_transfer_order')->insertGetId($dataOrder);
                        //扣除积分
                        $rs = \app\commons\Member::addscore(aid,mid,$integral * -1, sprintf(t('积分')."转赠给：%s",$info['nickname']));
                        if ($rs['status'] == 1) {
                            //创建支付订单
                            $payorderid = \app\models\Payorder::createorder(aid,0,mid,'score_transfer',$orderid,$dataOrder['ordernum'],'积分转赠',$sxf);
                            return $this->json(['status' => 2,'msg' => '需要支付','orderid' => $orderid,'payorderid' => $payorderid]);
                        }else{
                            return $this->json(['status' => 0, 'msg' => '转赠失败']);
                        }
                    }
                }
                $where = [];
                $where['aid'] = aid;
                $where['id'] = $user_id;
                $rs = \app\commons\Member::addscore(aid,mid,$integral * -1, sprintf(t('积分')."转赠给：%s",$info['nickname']));
                if ($rs['status'] == 1) {
                    \app\commons\Member::addscore(aid,$user_id,$integral,sprintf("来自%s的".t('积分')."转赠", $this->member["nickname"]), '', 0, $this->mid);
                }else{
                    return $this->json(['status'=>0, 'msg' => '转赠失败']);
                }
                return $this->json(['status'=>1, 'msg' => '转赠成功', 'url'=>'/pages/my/usercenter']);
            }
            $tomember = [];
            if($mid){
                $tomember = Db::name('member')->where('aid',aid)->where('id',$mid)->field('id,money,nickname,headimg')->find();
            }
            $rdata['paycheck'] = $set['score_transfer_pwd'] ? true : false;
            $rdata['status'] = 1;
            $rdata['myscore'] = dd_money_format($this->member['score'],$score_weishu);
            $rdata['set'] = $setrs;
            $rdata['sxf_ratio'] = $sxf_ratio;
            $rdata['transfer_sxf'] = $score_transfer_sxf;
            $rdata['tomember'] = $tomember?$tomember:['nickname'=>''];//转给谁
            return $this->json($rdata);
        }
    }

    public function scoreTransferWxqrcode()
    {
        if(getcustom('score_transfer_wxqrcode')) {
            $poster = \app\commons\Wechat::getQRCode(aid,'wx','pagesExt/my/scoreTransfer',['mid'=>mid,'pid'=>mid]);
            return $this->json(['status'=>1,'poster'=>$poster['url']]);
        }
    }

    public function scoreWithdraw()
    {
        $score_weishu = 0;
        if(getcustom('score_weishu')){
            $score_weishu = Db::name('admin_set')->where('aid',aid)->value('score_weishu');
            $score_weishu = $score_weishu?$score_weishu:0;
        }
        if(getcustom('score_withdraw')){
            //积分转到余额
            $set = Db::name('admin_set')->where('aid', aid)->find();
            if ($set['score_withdraw'] != 1) {
                return $this->json(['status'=>0,'msg'=>t('积分').'提现未开启']);
            }
            if(request()->isPost()){
                $integral = input('post.integral');
                $integral =  dd_money_format($integral,$score_weishu);
                if ($integral <= 0){
                    return $this->json(['status'=>0,'msg'=>'请输入正确的'.t('积分').'数量']);
                }
                if ($integral > $this->member['score_withdraw']){
                    return $this->json(['status'=>0,'msg'=>'您的'.t('积分').'数量不足']);
                }
                $where = [];
                $where['aid'] = aid;
                $where['id'] = mid;
                $money = round($integral * $set['score_to_money_percent'],2);
                if($money < 0.01) {
                    return $this->json(['status'=>0,'msg'=>'提现金额不足0.01']);
                }
                $rs = \app\commons\Member::addmoney(aid,mid,$money,'允提'.t('积分').'提现');
                if ($rs['status'] == 1) {
                    \app\commons\Member::addscore_withdraw(aid,mid,$integral * -1, '允提'.t('积分').'提现');
                }
                return $this->json(['status'=>1, 'msg' => '操作成功', 'url'=>'/pages/my/usercenter']);
            }

            $rdata['status'] = 1;
            $rdata['myscore'] = dd_money_format($this->member['score_withdraw'],$score_weishu);
            $rdata['score_to_money_percent'] = $set['score_to_money_percent']+0;
            return $this->json($rdata);
        }
    }

	public function bscore(){
		$pagenum = input('post.pagenum');
        $st = input('post.st');
		if(!$pagenum) $pagenum = 1;
		$pernum = 20;
		$where = [];
		$where[] = ['business_memberscore.aid','=',aid];
		$where[] = ['business_memberscore.mid','=',mid];
		$datalist = Db::name('business_memberscore')->alias('business_memberscore')->field('business.logo,business.name,business_memberscore.*')->join('business business','business.id=business_memberscore.bid')->where($where)->page($pagenum,$pernum)->order('business_memberscore.score desc')->select()->toArray();
		if(!$datalist) $datalist = [];

		$rdata = [];
		$rdata['status'] = 1;
		$rdata['data'] = $datalist;
		$rdata['mybscore'] = $this->member['bscore'];
		$rdata['myscore'] = $this->member['score'];
		$rdata['status'] = 1;
		return $this->json($rdata);
	}
	public function bscorelog(){
		$bid = input('param.bid/d');
		$pagenum = input('post.pagenum');
        $st = input('post.st');
		if(!$pagenum) $pagenum = 1;
		$pernum = 20;
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['bid','=',$bid];
		$where[] = ['mid','=',mid];
		$datalist = Db::name('business_member_scorelog')->field('id,score,after,remark,createtime')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
		if(!$datalist) $datalist = [];
		
		if($pagenum == 1){
			$memberscore = Db::name('business_memberscore')->where('aid',aid)->where('bid',$bid)->where('mid',mid)->find();
			$bname = Db::name('business')->where('aid',aid)->where('id',$bid)->value('name');
		}

		$rdata = [];
		$rdata['status'] = 1;
		$rdata['data'] = $datalist;
		$rdata['status'] = 1;
		$rdata['mybscore'] = $memberscore['score'] ?? 0;
		$rdata['bname'] = $bname;
		return $this->json($rdata);
	}

	public function formlog(){
		$pagenum = input('post.pagenum');
        $st = input('post.st');
		if(!$pagenum) $pagenum = 1;
		$pernum = 20;
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['mid','=',mid];
		$where[] = ['isudel','=',0];
		
		if(input('post.keyword')){
			$where[] = ['title|form0|form1|form2|form3|form4|form5|form6|form7|form8|form9|form10','like','%'.input('param.keyword').'%'];
		}

		if(!input('?param.st') || $st === ''){
			$st = 'all';
		}
		if($st == 'all'){

		}elseif($st == '0'){
			$where[] = ['status','=',0];
			$where[] = Db::raw('payorderid is null or paystatus=1');
		}elseif($st == '1'){
			$where[] = ['status','=',1];
		}elseif($st == '2'){
			$where[] = ['status','=',2];
		}elseif($st == '10'){
			$where[] = ['status','=',0];
			$where[] = ['paystatus','=',0];
			$where[] = ['payorderid','<>',''];
		}

		//$where['status'] = 1;
		$datalist = Db::name('form_order')->field('*,from_unixtime(createtime)createtime,from_unixtime(paytime)paytime')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
		if(!$datalist) $datalist = [];

		if($datalist){
		    foreach($datalist as $dk=>$detail){
                $form = Db::name('form')->where('aid',aid)->where('id',$detail['formid'])->find();
                $formcontent = json_decode($form['content'],true);

                $linkitemArr = [];
                foreach($formcontent as $k=>$v){
                    if(($v['key'] == 'radio' || $v['key'] == 'selector') && $detail['form'.$k]!==''){
                        $linkitemArr[] = $v['val1'].'|'.$detail['form'.$k];
                    }
                }
                foreach($formcontent as $k=>$v){
                    if($v['linkitem'] && !in_array($v['linkitem'],$linkitemArr)){
                        $formcontent[$k]['hidden'] = true;
                    }
                    if(!getcustom('form_map')){
                        $formcontent[$k]['val12'] = 1;
                    }
                }
                //距离
                $detail['distance'] = '';
                $detail['show_distance'] = 0;
                if(getcustom('form_map')){
                    if($detail['adr_lon'] && $detail['adr_lat']){
                        $detail['show_distance'] = 1;
                        if( input('longitude') && input('latitude')){
                            $distance = getdistance($detail['adr_lon'], $detail['adr_lat'], input('longitude'), input('latitude'));
                            $distance = bcdiv($distance,1000,2);
                            $detail['distance'] = $distance.'km';
                        }
                    }
                }
                $detail['formcontent'] = $formcontent;
                if(getcustom('form_custom_number')){
                    $detail['custom_number_text'] = $form['custom_number_text'];
                }
                $datalist[$dk] = $detail;
            }
        }
		if(request()->isPost()){
			return $this->json(['status'=>1,'data'=>$datalist]);
		}
		$count = Db::name('form_order')->where($where)->count();
		$rdata = [];
		$rdata['count'] = $count;
		$rdata['datalist'] = $datalist;
		$rdata['pernum'] = $pernum;
		$rdata['st'] = $st;
		return $this->json($rdata);
	}
    public function formlog2(){
	    if(!getcustom('form_log_plug')){
	        return false;
        }
        $pagenum = input('post.pagenum');
        $st = input('post.st');
        $id = input('post.id');
        $query_form = Db::name('form')->where('id',$id)->find();

        if(getcustom('form_match')){
            //验证权限
            $quanxian = json_decode($query_form['quanxian'],true);
            if(!empty($quanxian) && $quanxian['all']!=='on'){
                $levelid = $this->member['levelid'];
                if($quanxian[$levelid]!=='on'){
                    $noauth_text = $query_form['noauth_text']?:'无权限访问';
                    $noauth_url = $query_form['noauth_url']?:'/pages/my/usercenter';
                    return $this->json(['status'=>0,'msg'=>$noauth_text,'redirect_url'=>$noauth_url]);
                }
            }
            $query_form['custom_search'] = 1;
        }
        if(!$pagenum) $pagenum = 1;
        $pernum = 20;
        $order_sort = 'id desc';
        $where = [];
        $where[] = ['aid','=',aid];
        $where[] = ['isudel','=',0];
        $where[] = ['formid','=',$id];

		if(input('post.keyword')){
		    if(getcustom('form_match') && !empty($query_form['form_match'])){
		        //开启数据匹配模式
		        $formcontent = json_decode($query_form['content'],true);
		        $match_key = 0;
		        foreach($formcontent as $k=>$v){
		            if(!empty($v['val13'])){
                        $match_key = $k;
                    }
                }
		        //查询匹配数据
		        $keyword = input('keyword')?:0;
                $keyword = intval($keyword);

		        $form_match = Db::name('form_match')->where('search_val',$keyword)->find();
		        if(!$form_match){
                    return $this->json(['status'=>1,'data'=>[],'queryform'=>$query_form]);
                }
		        //计算百分比
		        $match_data = round(bcdiv($form_match['div_val'],$form_match['total'],5),4);
		        //向下匹配
                $match_limit = $query_form['match_limit']?:1;
                $where_match = [];
                $where_match[] = ['formid','=',$query_form['id']];
                $where_match[] = ['form'.$match_key,'>=',$match_data];
                $order_sort = 'form'.$match_key.' asc,id desc';
                $order_ids1 = Db::name('form_order')->where($where_match)->limit($match_limit)->order($order_sort)->column('id');
                //再向上匹配三条数据
                $match_limit_down = $query_form['match_limit_up']?:1;
                $where_match = [];
                $where_match[] = ['formid','=',$query_form['id']];
                $where_match[] = ['form'.$match_key,'<',$match_data];
                $order_ids2 = Db::name('form_order')->where($where_match)->limit($match_limit_down)->order('form'.$match_key.' desc,id desc')->column('id');
                $order_ids = array_merge($order_ids1,$order_ids2);
                $where[] = ['id','in',$order_ids];

            }else{
                $where[] = ['title|form0|form1|form2|form3|form4|form5|form6|form7|form8|form9|form10','like','%'.input('param.keyword').'%'];
            }

		}

        if(!input('?param.st') || $st === ''){
            $st = 'all';
        }
        if($st == 'all'){

        }elseif($st == '0'){
            $where[] = ['status','=',0];
            $where[] = Db::raw('payorderid is null or paystatus=1');
        }elseif($st == '1'){
            $where[] = ['status','=',1];
        }elseif($st == '2'){
            $where[] = ['status','=',2];
        }elseif($st == '10'){
            $where[] = ['status','=',0];
            $where[] = ['paystatus','=',0];
            $where[] = ['payorderid','<>',''];
        }

        //$where['status'] = 1;
        $datalist = Db::name('form_order')->field('*,from_unixtime(createtime)createtime,from_unixtime(paytime)paytime')->where($where)->page($pagenum,$pernum)->order($order_sort)->select()->toArray();
        if(!$datalist) $datalist = [];
        else {
            foreach ($datalist as $k => $item) {
            	//处理前四条提交类型
            	$datalist[$k]['form0_key'] = '';
            	$datalist[$k]['form1_key'] = '';
            	$datalist[$k]['form2_key'] = '';
            	$datalist[$k]['form3_key'] = '';

            	$datalist[$k]['form0_val'] = '';
            	$datalist[$k]['form1_val'] = '';
            	$datalist[$k]['form2_val'] = '';
            	$datalist[$k]['form3_val'] = '';
            	$datalist[$k]['member'] = '';
            	if($item['mid']){
            		 $datalist[$k]['member'] = \db('member')->where('aid',aid)->where('id',$item['mid'])->field('nickname,realname')->find();
            		 $datalist[$k]['member']['nickname'] = $datalist[$k]['member']['nickname'] && !is_null($datalist[$k]['member']['nickname'])?$datalist[$k]['member']['nickname']:'';
            		 $datalist[$k]['member']['realname'] = $datalist[$k]['member']['realname'] && !is_null($datalist[$k]['member']['realname'])?$datalist[$k]['member']['realname']:'';
            	}else{
                    $datalist[$k]['member'] = ['nickname'=>'','realname'=>''];
                }
                $form = Db::name('form')->where('aid',aid)->where('id',$item['formid'])->find();
				$formcontent = json_decode($form['content'],true);
				
				if($formcontent){
					foreach($formcontent as $k2=>$v){
						if($v['key'] == 'upload_pics'){
							$pics = $item['form'.$k];
							if($pics){
								$datalist[$k]['form'.$k] = explode(",",$pics);
							}
						}
					}
					if($formcontent[0]){
						$datalist[$k]['form0_key'] = $formcontent[0]['key'];
						$datalist[$k]['form0_val'] = $formcontent[0]['val1'];
                        $datalist[$k]['form0_show'] = 1;
					}
					if($formcontent[1]){
						$datalist[$k]['form1_key'] = $formcontent[1]['key'];
						$datalist[$k]['form1_val'] = $formcontent[1]['val1'];
                        $datalist[$k]['form1_show'] = 1;
					}
					if($formcontent[2]){
						$datalist[$k]['form2_key'] = $formcontent[2]['key'];
						$datalist[$k]['form2_val'] = $formcontent[2]['val1'];
                        $datalist[$k]['form2_show'] = 1;
					}
					if($formcontent[3]){
						$datalist[$k]['form3_key'] = $formcontent[3]['key'];
						$datalist[$k]['form3_val'] = $formcontent[3]['val1'];
                        $datalist[$k]['form3_show'] = 1;
					}
                    $show_distance = 0;
					if(getcustom('form_map')){
					    //是否展示字段、是否显示距离
                        foreach($formcontent as $c_key=>$c_val){
                            $datalist[$k]['form'.$c_key.'_show'] = $c_val['val12'];
                            if($item['adr_lat'] && $item['adr_lon']){
                                $show_distance = 1;
                                if( input('longitude') && input('latitude')){
                                    $distance = getdistance($item['adr_lon'], $item['adr_lat'], input('longitude'), input('latitude'))?:0;
                                    $distance = bcdiv($distance,1000,2);
                                    $datalist[$k]['distance'] = $distance.'km';
                                }
                            }
                        }
                        //是否显示距离
                    }
                    $datalist[$k]['show_distance'] = $show_distance;
				}
                $datalist[$k]['background_color'] = '#fff';
                if(getcustom('form_match') && !empty($query_form['form_match'])){
                    if(in_array($datalist[$k]['id'],$order_ids1)){
                        $datalist[$k]['background_color'] = $query_form['background_color_down'];
                    }
                    if(in_array($datalist[$k]['id'],$order_ids2)){
                        $datalist[$k]['background_color'] = $query_form['background_color_up'];
                    }
                }
            }
        }
        if(!getcustom('form_match')){
            $query_form['show_title'] = 1;//是否显示页面标题 1显示 0不显示
            $query_form['log_title'] = '';//页面标题内容自定义
            $query_form['show_name'] = 1;//是否显示表单记录中表单名称 1显示 0不显示
            $query_form['show_time'] = 1;//是否显示提交时间 1显示 0不显示
            $query_form['show_audit'] = 1;//是否显示审核状态 1显示 0不显示
            $query_form['desc'] = '';//表单说明
            $query_form['search_title'] = '输入关键字搜索';//搜索框提示语自定义
            $query_form['custom_search'] = 0;//定制搜索框样式
        }
        if(request()->isPost()){
            return $this->json(['status'=>1,'data'=>$datalist,'queryform'=>$query_form]);
        }
        $count = Db::name('form_order')->where($where)->count();
        $rdata = [];
        $rdata['count'] = $count;
        $rdata['datalist'] = $datalist;
        $rdata['pernum'] = $pernum;
        $rdata['queryform'] = $query_form;
        $rdata['st'] = $st;
        return $this->json($rdata);
    }
	public function formdetail(){
		$id = input('param.id/d');
        $op = input('param.op');
        if($op == 'view' && (getcustom('form_log_plug') || cache($this->sessionid.'_formquery') == $id))
            $detail = Db::name('form_order')->where('aid',aid)->where('id',$id)->find();
        else
		    $detail = Db::name('form_order')->where('aid',aid)->where('mid',mid)->where('id',$id)->find();

        $detail['is_other_fee'] = 0;
        if(getcustom('form_other_money')){
            if($detail['fee_items']){
                $detail['is_other_fee'] = 1;
                $detail['fee_items'] = json_decode($detail['fee_items'],true);
            }
        }
		$detail['paytime'] = date('Y-m-d H:i:s',$detail['paytime']);
		$detail['createtime'] = date('Y-m-d H:i:s',$detail['createtime']);
        $detail['distance'] = '';
        $detail['show_distance'] = 0;
        if(getcustom('form_map')){
            if($detail['adr_lon'] && $detail['adr_lat'] ){
                $detail['show_distance'] = 1;
                if(input('longitude') && input('latitude')){
                    $distance = getdistance($detail['adr_lon'], $detail['adr_lat'], input('longitude'), input('latitude'));
                    $distance = bcdiv($distance,1000,2);
                    $detail['distance'] = $distance.'km';
                }
            }
        }


		$form = Db::name('form')->where('aid',aid)->where('id',$detail['formid'])->find();
		$formcontent = json_decode($form['content'],true);

        if(getcustom('form_match')){
            //验证权限
            $quanxian = json_decode($form['quanxian'],true);
            if(!empty($quanxian) && $quanxian['all']!=='on'){
                $levelid = $this->member['levelid'];
                if($quanxian[$levelid]!=='on'){
                    $noauth_text = $form['noauth_text']?:'无权限访问';
                    $noauth_url = $form['noauth_url']?:'/pages/my/usercenter';
                    return $this->json(['status'=>0,'msg'=>$noauth_text,'redirect_url'=>$noauth_url]);
                }
            }
        }
		
		$linkitemArr = [];
		foreach($formcontent as $k=>$v){
			if(($v['key'] == 'radio' || $v['key'] == 'selector') && $detail['form'.$k]!==''){
				$linkitemArr[] = $v['val1'].'|'.$detail['form'.$k];
			}
		}
		foreach($formcontent as $k=>$v){
			if($v['linkitem'] && !in_array($v['linkitem'],$linkitemArr)){
				$formcontent[$k]['hidden'] = true;
			}
			if(!getcustom('form_map')){
                $formcontent[$k]['val12'] = 1;
            }
            if($v['key'] == 'upload_pics'){
				$pics = $detail['form'.$k];
				if($pics){
					$detail['form'.$k] = explode(",",$pics);
				}
			}
		}

		$againname = '再次提交';
		$detail['againsubmit'] = true;
		if(getcustom('article_portion') || getcustom('form_edit')){

			//是否能编辑
			$detail['edit_status'] = false;
			if($form['edit_status'] == 1 && $detail['mid']==mid){
				$detail['edit_status'] = true;
			}

			$detail['edit_name'] = '编辑';
		}
		if(getcustom('businessindex_showfw')){
			$againname = '复制档案';
		}
        if(!getcustom('form_match')){
            $form['show_title'] = 1;
            $form['log_title'] = '';
            $form['show_name'] = 1;
            $form['show_time'] = 1;
            $form['show_audit'] = 1;
        }
        if(getcustom('form_data')){
			$detail['edit_name']   = '修改';
			$detail['againsubmit'] = false;
		}

		$rdata = [];
		$rdata['form'] = $form;
		$rdata['formcontent'] = $formcontent;
		$rdata['detail'] = $detail;
		$rdata['againname'] = $againname;
		return $this->json($rdata);

	}
	public function formdelete(){
		$id = input('param.id/d');
		Db::name('form_order')->where('aid',aid)->where('mid',mid)->where('id',$id)->update(['isudel'=>1]);
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	public function favorite(){
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$pernum = 20;
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['mid','=',mid];
		$datalist = Db::name('member_favorite')->field('id,proid,from_unixtime(createtime)createtime,type')->where($where)->page($pagenum,$pernum)->order('createtime desc')->select()->toArray();
		if(!$datalist) $datalist = [];
		foreach($datalist as $k=>$v){
			$product = [];
			if($v['type'] == 'shop'){
				$product = Db::name('shop_product')->where('id',$v['proid'])->find();
			}elseif($v['type'] == 'yuyue'){
				$product = Db::name('yuyue_product')->where('id',$v['proid'])->find();
			}elseif($v['type'] == 'seckill'){
				$product = Db::name('seckill_product')->where('id',$v['proid'])->find();
			}elseif($v['type'] == 'collage'){
				$product = Db::name('collage_product')->where('id',$v['proid'])->find();
			}elseif($v['type'] == 'scoreshop'){
				$product = Db::name('scoreshop_product')->where('id',$v['proid'])->find();
			}elseif($v['type'] == 'choujiang'){
				$product = Db::name('choujiang_product')->where('id',$v['proid'])->find();
				$product['market_price'] = $product['sell_price'];
				$product['sell_price'] = $product['min_price'];
			}elseif($v['type'] == 'kecheng'){
				$product = Db::name('kecheng_list')->where('id',$v['proid'])->find();
			}elseif($v['type'] == 'luckycollage'){
				$product = Db::name('lucky_collage_product')->where('id',$v['proid'])->find();
			}elseif($v['type'] == 'zhaopin'){
                $product = Db::name('zhaopin')->where('id',$v['proid'])->find();
            }elseif($v['type'] == 'qiuzhi'){
                $product = Db::name('zhaopin_qiuzhi')->where('id',$v['proid'])->find();
            }elseif($v['type'] == 'yueke'){
                $product = Db::name('yueke_product')->where('id',$v['proid'])->find();
            } elseif($v['type'] == 'cycle'){
                $product = Db::name('cycle_product')->where('id',$v['proid'])->find();
            }   elseif($v['type'] == 'car_hailing'){
                $product = Db::name('car_hailing_product')->where('id',$v['proid'])->find();
               
                $date = date('Y-m-d',time() + 86400 * 0);
                $yyorderlist = Db::name('car_hailing_order')->alias('car_hailing_order')->field('member.headimg,member.nickname')->join('member member','member.id=car_hailing_order.mid')->where('car_hailing_order.proid',$product['id'])->where('car_hailing_order.yy_date',$date)->where('car_hailing_order.status','in',[1,2,3])->select()->toArray();
                if(!$yyorderlist) $yyorderlist = [];
                if($product['cid'] ==2){
                    $product['leftnum'] = $product['yynum'] - count($yyorderlist);
                    $starttime = strtotime($date.' '.$v['starttime']);
                    if($starttime < time() + $v['prehour']*3600 && $v['cid'] !=1){
                        $product['isend'] = true;
                    }else{
                        $product['isend'] = false;
                    }
                }
                
                $product['yyorderlist'] = $yyorderlist;
            }
			if(!$product){
				Db::name('member_favorite')->where('id',$v['id'])->delete();
				unset($datalist[$k]);
			}else{
				$datalist[$k]['product'] = $product;
			}
		}
		if(request()->isPost()){
			return $this->json(['status'=>1,'data'=>$datalist]);
		}
		$count = Db::name('member_favorite')->where($where)->count();

		$rdata = [];
		$rdata['count'] = $count;
		$rdata['datalist'] = $datalist;
		$rdata['pernum'] = $pernum;
		return $this->json($rdata);
	}
	public function favoritedel(){
		$post = input('post.');
		Db::name('member_favorite')->where('aid',aid)->where('mid',mid)->where('id',$post['id'])->delete();
		return $this->json(['status'=>1,'msg'=>'已取消','url'=>true]);
	}

	public function history(){
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$pernum = 20;
		$where = [];
		$where[] = ['aid','=',aid];
        $mid = $this->mid;
        if(getcustom('team_member_history')){
            //可以查看下级的足迹
            $mid = input('param.mid/d',0);
            if(empty($mid)) $mid = $this->mid;
            //我所有的下级
            $myallDownMids = \app\commons\Member::getdownmids(aid,mid);
            if ($myallDownMids){
                $myallDownMids[] = $this->mid;//自己和下级
            }else{
                $myallDownMids = [$this->mid];
            }
            if(!in_array($mid,$myallDownMids)){
                return $this->json(['status'=>0,'msg'=>'无权查看该会员足迹','data'=>[]]);
            }
        }
		$where[] = ['mid','=',$mid];
		$datalist = Db::name('member_history')->field('id,proid,from_unixtime(createtime)createtime,type,mid')->where($where)->page($pagenum,$pernum)->order('createtime desc')->select()->toArray();
		if(!$datalist) $datalist = [];
		foreach($datalist as $k=>$v){
			$product = [];
			if($v['type'] == 'shop'){
				$product = Db::name('shop_product')->where('id',$v['proid'])->find();
			}elseif($v['type'] == 'yuyue'){
				$product = Db::name('yuyue_product')->where('id',$v['proid'])->find();
			}elseif($v['type'] == 'collage'){
				$product = Db::name('collage_product')->where('id',$v['proid'])->find();
			}elseif($v['type'] == 'scoreshop'){
				$product = Db::name('scoreshop_product')->where('id',$v['proid'])->find();
			}elseif($v['type'] == 'choujiang'){
				$product = Db::name('choujiang_product')->where('id',$v['proid'])->find();
				$product['market_price'] = $product['sell_price'];
				$product['sell_price'] = $product['min_price'];
			}elseif($v['type'] == 'zhaopin'){
                $product = Db::name('zhaopin')->where('id',$v['proid'])->find();
            }elseif($v['type'] == 'qiuzhi'){
                $product = Db::name('zhaopin_qiuzhi')->where('id',$v['proid'])->find();
            }
			if(!$product){
				Db::name('member_history')->where('id',$v['id'])->delete();
				unset($datalist[$k]);
			}else{
				$datalist[$k]['product'] = $product;
			}
		}
        foreach ($datalist as $k=>$v){
            $datalist[$k]['ismine'] = true;
            if($v['mid']!=$this->mid){
                $datalist[$k]['ismine'] = false;
            }
        }
		if(request()->isPost()){
			return $this->json(['status'=>1,'data'=>$datalist]);
		}
		$count = Db::name('member_history')->where($where)->count();

		$rdata = [];
		$rdata['count'] = $count;
		$rdata['datalist'] = $datalist;
		$rdata['pernum'] = $pernum;
		return $this->json($rdata);
	}
	public function historydel(){
		$post = input('post.');
		if($post['proid'] =='all'){
			$rs = Db::name('member_history')->where('aid',aid)->where('mid',mid)->delete();
		}else{
			$rs = Db::name('member_history')->where('aid',aid)->where('mid',mid)->where('proid',$post['proid'])->where('type',$post['type'])->delete();
		}
		return $this->json(['status'=>1,'msg'=>'已删除','url'=>true]);
	}
	//等级说明
	public function levelinfo(){
        $id = input('param.id/d', $this->member['levelid']);
        if(empty($id)) $id = $this->member['levelid'];
		$userinfo = Db::name('member')->where('id',mid)->field('id,nickname,headimg,sex,levelid,levelendtime,areafenhong,areafenhongbl,areafenhong_province province,areafenhong_city city,areafenhong_area area,areafenhong_largearea largearea,totalcommission')->find();
		if($id != $this->member['levelid']) {
            $levelids = Db::name('member_level_record')->where('aid', aid)->where('mid',mid)->column('levelid');
            if(!in_array($id,$levelids)) {
                return $this->json(['status'=>0,'msg'=>'不存在的等级','url'=>true]);
            }
        }

		$userlevel = Db::name('member_level')->where('id',$id)->find();
		if($userinfo['areafenhong'] == 1) $userlevel['areafenhong'] = 1;
		if($userinfo['areafenhong'] == 2) $userlevel['areafenhong'] = 2;
		if($userinfo['areafenhong'] == 3) $userlevel['areafenhong'] = 3;
		if($userinfo['areafenhong'] == 4) $userlevel['areafenhong'] = 0;
		if($userlevel){
			$nextlevel = Db::name('member_level')->where("`sort`>{$userlevel['sort']} or (`sort`={$userlevel['sort']} and id>{$userlevel['id']})")->where('cid', $userlevel['cid'])->where('aid',aid)->field('id,sort,name,up_fxordermoney,up_fxordermoney_removemax,up_fxorderlevelnum,up_fxorderlevelid')->find();
		}
		$showprogress = 0;
		if($nextlevel){
			$hasnext = 1;
			if(getcustom('team_yeji_uplv_pace')){
				//查找当前业绩
				//最后一次降级时间
				$fxordermoney = 0;
		        $down_level_time = Db::name('member_levelup_order')->where('mid',mid)->where('type',1)->order('createtime desc')->value('createtime');
		        $down_level_time = $down_level_time?:0;
				if($nextlevel['up_fxordermoney_removemax'] ==1){
	                $downmids = \app\commons\Member::getdownmids_removemax(aid,mid,$nextlevel['up_fxorderlevelnum'],$nextlevel['up_fxorderlevelid']);
	            }else{
	                $downmids = \app\commons\Member::getdownmids(aid,mid,$nextlevel['up_fxorderlevelnum'],$nextlevel['up_fxorderlevelid'],0,1,$down_level_time);
	            }
	            if($downmids){
	                $fxordermoney = 0 + Db::name('shop_order_goods')->where('status','in','1,2,3')->where('mid','in',$downmids)->where('createtime','>',$down_level_time)->sum('totalprice');

	                // 餐饮订单计入团队业绩，参与升级条件统计
	                if(getcustom('restaurant_team_yeji')){
	                    // 外卖
	                    $rtakeaway_fxordermoney = Db::name('restaurant_takeaway_order_goods')->where('status','in','1,2,3,12')->where('mid','in',$downmids)->where('createtime','>',$down_level_time)->sum('totalprice');
	                    $fxordermoney += $rtakeaway_fxordermoney;
	                    // 店内点餐
	                    $rshop_fxordermoney = Db::name('restaurant_shop_order_goods')->where('status','in','1,2,3')->where('mid','in',$downmids)->where('createtime','>',$down_level_time)->sum('totalprice');
	                    $fxordermoney += $rshop_fxordermoney;

	                }
	            }

	            $nextlevel['now_team_yeji'] = $fxordermoney;
	            $nextlevel['up_team_yeji'] = $nextlevel['up_fxordermoney'];
	            if($nextlevel['up_fxordermoney'] > 0){
	            	$nextlevel['progress'] = floatval($fxordermoney / $nextlevel['up_fxordermoney']*100);
	            	$showprogress = 1;
	            }
	        }
			
		}else{
			$hasnext = 0;
		}
		
		$showleveldown= false;
		$leveldowncommission=0;
		if(getcustom('member_level_down_commission') && $userlevel['down_level_totalcommission']>0){
			$member = Db::name('member')->where('id',mid)->field('id,isauto_down,totalcommission,down_commission')->find();
			$userinfo['isauto_down'] = $member['isauto_down'];
			$showleveldown=true;
			if(!$member['isauto_down']){
				$leveldowncommission =$userlevel['down_level_totalcommission']-($userinfo['totalcommission']-$member['down_commission']);
				$userinfo['leveldowncommission'] = $leveldowncommission;
				$leveldown = Db::name('member_level')->field('id,name')->where('id',$userlevel['down_level_id2'])->find();
				$userinfo['leveldownname'] = $leveldown['name'];
			}else{
				$pro = Db::name('shop_product')->field('id,name')->where('id',$userlevel['recovery_level_proid'])->find();
				$userinfo['buyproname'] = $pro['name'];
			}
		}
		$rdata = [];
		$rdata['userinfo'] = $userinfo;
		$rdata['nextlevel'] = $nextlevel??[];
		$rdata['userlevel'] = $userlevel;
		$rdata['hasnext'] = $hasnext;
		$rdata['showprogress'] = $showprogress;
		$rdata['showleveldown'] = $showleveldown;
		$rdata['team_yeji_name'] = t('团队业绩');
		return $this->json($rdata);
	}
	//升级
	public function levelup(){
		if(request()->isPost()){
			$post = input('post.');
			$formdata = $post;
			if(!$this->member){
				return $this->json(['status'=>0,'msg'=>'参数错误,请重新操作']);
			}
			$this->member['ordercount'] = 0 + Db::name('shop_order')->where('aid',aid)->where('mid',mid)->where('status',3)->count();
			$this->member['ordermoney'] = 0 + Db::name('shop_order')->where('aid',aid)->where('mid',mid)->where('status',3)->sum('totalprice');
			$this->member['rechargemoney'] = 0 + Db::name('recharge_order')->where('aid',aid)->where('mid',mid)->where('status',1)->sum('money');

			$leveldata = Db::name('member_level')->where('aid',aid)->where('id',$formdata['levelid'])->find();
			if(!$leveldata['apply_paytxt']) $leveldata['apply_paytxt'] = '升级费用';
			//dump($formdata);
			if(!$leveldata){
				return $this->json(['status'=>0,'msg'=>'参数错误,请重新操作!']);
			}
            if(getcustom('taocan_product')){
                //查询会有最后一次降级时间
                //$down_level_time = Db::name('member_leveldown_record')->where('mid',mid)->order('createtime desc')->value('createtime');
                $down_level_time = Db::name('member_levelup_order')->where('mid',mid)->where('type',1)->order('createtime desc')->value('createtime');
                if( $leveldata['apply_taocan_proid']>0){
                    //查找套餐订单
                    $buy_taocan = Db::name('taocan_order_goods')
                        ->where('mid','=',mid)
                        ->where('proid','=',$leveldata['apply_taocan_proid'])
                        ->where('status','in','1,2,3')
                        ->where('createtime','>',$down_level_time?:0)
                        ->find();
                    if($buy_taocan){
                        $leveldata['apply_paymoney'] = 0;
                    }
                }
            }
			$canapply = 0;
			if($leveldata['apply_ordermoney'] <= 0 && $leveldata['apply_rechargemoney'] <= 0){
				$canapply = 1;
			}
			if ($leveldata['apply_ordermoney']>0 && $this->member['ordermoney'] >= $leveldata['apply_ordermoney']){
				$canapply = 1;
			}
			if ($leveldata['apply_rechargemoney']>0 && $this->member['rechargemoney'] >= $leveldata['apply_rechargemoney']){
				$canapply = 1;
			}
            $data = [];
            $data['aid'] = aid;
            $data['mid'] = mid;
			if(getcustom('levelup_code')){
                $code = $formdata['code']?trim($formdata['code']):'';
                if($code){
                	if($leveldata['can_apply'] != 1){
                		return $this->json(['status'=>0,'msg'=>'此等级申请功能暂未开启']);
                	}else{
                		if($code != $leveldata['apply_code']){
	                        return $this->json(['status'=>0,'msg'=>'验证码错误']);
	                    }else{
	                        $canapply = 1;
	                    }
                	}
                }
                if(getcustom('school_product')){
                    $data['school_id'] = input('post.school_id/d',0);
                    $data['grade_id'] = input('post.grade_id/d',0);
                    $data['class_id'] = input('post.class_id/d',0);
                }
            }
			if(!$canapply){
				return $this->json(['status'=>0,'msg'=>'不满足申请条件']);
			}
            if(getcustom('member_up_binding_tel')){
                $smscode = $formdata['smscode'];
                $tel = input('param.tel');
                if($smscode && $tel){
                    if(md5($tel.'-'.$smscode) != cache($this->sessionid.'_smscode') || cache($this->sessionid.'_smscodetimes')>5){
                        cache($this->sessionid.'_smscodetimes',cache($this->sessionid.'_smscodetimes')+1);
                        return $this->json(['status'=>0,'msg'=>'短信验证码错误']);
                    }
                }
            }
			//是否有待审核的记录
			$hasds = Db::name('member_levelup_order')->where('aid',aid)->where('mid',mid)->where('levelid',$leveldata['id'])->where('status',1)->find();
			if($hasds){
				return $this->json(['status'=>0,'msg'=>'您已经提交过了,请等待审核']);
			}
			if(getcustom('plug_xiongmao')){
				$hasds = Db::name('member_levelup_order')->where('aid',aid)->where('mid',mid)->find();
				if($hasds){
					return $this->json(['status'=>0,'msg'=>'您已经提交过了']);
				}
			}

			if($leveldata['maxnum'] > 0){
				$hascount = Db::name('member')->where('aid',aid)->where('levelid',$leveldata['id'])->count();
				if($hascount >= $leveldata['maxnum']) return $this->json(['status'=>0,'msg'=>'该等级申请名额已满']);
			}

			$return = [];
			$data['levelid'] = $leveldata['id'];
			$data['beforelevelid'] = $this->member['levelid'];

			$ordernum = date('ymdHis').aid.rand(1000,9999);
			$data['ordernum'] = $ordernum;
			$data['title'] = '升级成为'.$leveldata['name'];

			$apply_formdata = json_decode($leveldata['apply_formdata'],true);

			foreach($apply_formdata as $k=>$v){
				$value = $formdata['form'.$k];
				if(is_array($value)){
					$value = implode(',',$value);
				}
				$value = strval($value);
				$data['form'.$k] = $v['val1'] . '^_^' .$value . '^_^' .$v['key'];
				if($v['val3']==1 && $value===''){
					return $this->json(['status'=>0,'msg'=>$v['val1'].' 必填']);
				}
				if($v['key']=='region' && getcustom('buy_selectmember')){
					$region = explode(',',$value);
					Db::name('member')->where('aid',aid)->where('id',mid)->update(['province'=>$region[0],'city'=>$region[1],'area'=>$region[2]]);
				}
			}
			if(getcustom('plug_xiongmao')){
				Db::name('member')->where('id',mid)->update(['realname'=>explode('^_^',$data['form0'])[1],'tel'=>explode('^_^',$data['form1'])[1]]);
			}
			if($leveldata['areafenhong']==1){
				$data['areafenhong_province'] = $post['areafenhong_province'];
				if($leveldata['areafenhongmaxnum'] > 0){
					$hascount = Db::name('member')->where('aid',aid)->where('levelid',$leveldata['id'])->where('areafenhong_province',$data['areafenhong_province'])->count();
					if($hascount >= $leveldata['areafenhongmaxnum']) return $this->json(['status'=>0,'msg'=>'该区域名额已满']);
				}
			}elseif($leveldata['areafenhong']==2){
				$data['areafenhong_province'] = $post['areafenhong_province'];
				$data['areafenhong_city'] = $post['areafenhong_city'];
				if($leveldata['areafenhongmaxnum'] > 0){
					$hascount = Db::name('member')->where('aid',aid)->where('levelid',$leveldata['id'])->where('areafenhong_province',$data['areafenhong_province'])->where('areafenhong_city',$data['areafenhong_city'])->count();
					if($hascount >= $leveldata['areafenhongmaxnum']) return $this->json(['status'=>0,'msg'=>'该区域名额已满']);
				}
			}elseif($leveldata['areafenhong']==3){
				$data['areafenhong_province'] = $post['areafenhong_province'];
				$data['areafenhong_city'] = $post['areafenhong_city'];
				$data['areafenhong_area'] = $post['areafenhong_area'];
				if($leveldata['areafenhongmaxnum'] > 0){
					$hascount = Db::name('member')->where('aid',aid)->where('levelid',$leveldata['id'])->where('areafenhong_province',$data['areafenhong_province'])->where('areafenhong_city',$data['areafenhong_city'])->where('areafenhong_area',$data['areafenhong_area'])->count();
					if($hascount >= $leveldata['areafenhongmaxnum']) return $this->json(['status'=>0,'msg'=>'该区域名额已满']);
				}
			}elseif($leveldata['areafenhong']==10){
				$data['areafenhong_largearea'] = $post['areafenhong_largearea'];
				if($leveldata['areafenhongmaxnum'] > 0){
					$hascount = Db::name('member')->where('aid',aid)->where('levelid',$leveldata['id'])->where('areafenhong_largearea',$data['areafenhong_largearea'])->count();
					if($hascount >= $leveldata['areafenhongmaxnum']) return $this->json(['status'=>0,'msg'=>'该区域名额已满']);
				}
			}

			$data['totalprice'] = $leveldata['apply_paymoney'];
			$data['createtime'] = time();
            $data['pid'] = $this->member['pid'];
			if($leveldata['apply_paymoney'] > 0){
				$data['status'] = 0;
				$orderid = Db::name('member_levelup_order')->insertGetId($data);
				$payorderid = \app\models\Payorder::createorder(aid,0,$data['mid'],'member_levelup',$orderid,$data['ordernum'],$data['title'],$data['totalprice']);
				$return = ['status'=>1,'msg'=>'提交成功，正在跳转到支付','url'=>'/pagesExt/pay/pay?id='.$payorderid];
			}else{
				$data['status'] = 1;
				$orderid = Db::name('member_levelup_order')->insertGetId($data);
				\app\models\Payorder::member_levelup_pay($orderid);
                if($leveldata['apply_check']){
                    $return = ['status'=>1,'msg'=>'提交成功请等待审核','url'=>'/pages/my/usercenter'];
                }else{
                    $return = ['status'=>1,'msg'=>'申请成功','url'=>'/pages/my/usercenter'];
                }
			}
			$return['orderid'] = $orderid;
			return $this->json($return);
		}
		$member = Db::name('member')->field('realname,tel,weixin,aliaccount,bankcardnum,bankname,bankcarduser,levelid')->where('aid',aid)->where('id',mid)->find();

		$member['ordermoney'] = Db::name('shop_order')->where('aid',aid)->where('mid',mid)->where('status',3)->sum('totalprice');
		$member['rechargemoney'] = Db::name('recharge_order')->where('aid',aid)->where('mid',mid)->where('status',1)->sum('money');

        $id = input('param.id/d');//传id为其他分组等级升级
        $cid = input('param.cid/d');//传cid为指定分组升级，

        if($cid && !$id) {
            $level_record = Db::name('member_level_record')->where('aid',aid)->where('mid', mid)->where('cid', $cid)->find();
            if(!empty($level_record)) {
                $id = $level_record['levelid'];
            }
        }
		if(!$id) $id = $member['levelid'];
		//我的等级
		$mylevel = Db::name('member_level')->where('aid',aid)->where('id',$id)->find();
		$member['levelid'] = $mylevel['id'];
		//等级列
		$where = [];
		$where[] = ['aid','=',aid];
		//如果可跨级 则允许展示所有等级 如果不允许跨级 则只能显示比当前等级高的
        $skip_level = 0;
        if(getcustom('skip_levelup')){
            $skip_level = Db::name('member_level_bgset')->where('aid',aid)->value('skip_level');
        }
        if($skip_level==1){
            //不等于当前等级
//            $where[] = ['id','<>',$mylevel['id']];
        }else{
            $where[] = ['sort','>',$mylevel['sort']];
        }
		if(getcustom('levelup_code')){
			$levelid =  input('param.levelid')?input('param.levelid/d'):0;//等级id
			if($levelid>0){
				$where[] = ['id','=',$levelid];
			}
			$ycode =  input('param.ycode');//验证码
			if(isset($ycode)){
				$ycode = trim($ycode);
				if($ycode){
					$where[] = ['apply_code','=',$ycode];
					$where[] = ['can_apply','=',1];
				}else{
					$where[] = ['id','=',-1];
				}
			}else{
				$where[] = Db::raw("can_apply=1 || (can_up=1 && (up_wxpaymoney>0 || up_ordermoney>0 || up_rechargemoney>0 || up_getmembercard>0 || up_fxordermoney>0 || up_fxdowncount>0 || up_proid>0))");
			}
		}else{
			$where[] = Db::raw("can_apply=1 || (can_up=1 && (up_wxpaymoney>0 || up_ordermoney>0 || up_rechargemoney>0 || up_getmembercard>0 || up_fxordermoney>0 || up_fxdowncount>0 || up_proid>0))");
		}
		$aglevelList = Db::name('member_level')->where($where)->where('cid', $cid ? $cid : $mylevel['cid'])->order('sort')->select()->toArray();
		if(!$aglevelList) $aglevelList = [];
        //查询会有最后一次降级时间
        //$down_level_time = Db::name('member_leveldown_record')->where('mid',mid)->order('createtime desc')->value('createtime');
        $down_level_time = Db::name('member_levelup_order')->where('mid',mid)->where('type',1)->order('createtime desc')->value('createtime');
		foreach($aglevelList as $k=>$lv){
			if(getcustom('levelup_code')){
				$aglevelList[$k]['applycode']  = '';//增加一个额外的参数
				$aglevelList[$k]['apply_code'] = $aglevelList[$k]['apply_code']?true:false;//去掉验证码数据
			}
			if($lv['up_proid']){
                $aglevelList[$k]['up_proname'] = '';
			    $up_proid = explode(',', $lv['up_proid']);
                $up_pronum = explode(',', $lv['up_pronum']);
                if($up_proid)
				    $up_pro = Db::name('shop_product')->whereIn('id',$up_proid)->field('name')->select()->toArray();
                if($up_pro) {
                    foreach ($up_pro as $pk => $pro) {
                        if($pro['name'] && $up_pronum[$pk]) {
                            if($pk) {
                                $aglevelList[$k]['up_proname'] .= ' + ';
                            }
                            $aglevelList[$k]['up_proname'] .= $pro['name'].'x'.$up_pronum[$pk];
                        }
                    }
                }
			}
            $atj = array();
			//申请
            $aglevelList[$k]['applytj_reach'] = 0;
            $aglevelList[$k]['applytj'] = '';
            if($lv['can_apply'] == 1){
                if (empty($lv['apply_ordermoney'])) $lv['apply_ordermoney'] = 0;
                if (empty($lv['apply_rechargemoney'])) $lv['apply_rechargemoney'] = 0;
                if ($lv['apply_ordermoney'] <= 0 && $lv['apply_rechargemoney'] <= 0) {
                    $aglevelList[$k]['applytj_reach'] = 1;
                }
                if($lv['apply_ordermoney'] > 0) {
                    $atj[]='累计订单金额满'.$lv['apply_ordermoney'].'元';
                    if($member['ordermoney'] >= $lv['apply_ordermoney']) {
                        $aglevelList[$k]['applytj_reach'] = 1;
                    }
                }
                if($lv['apply_rechargemoney'] > 0) {
                    $atj[]='累计充值金额满'.$lv['apply_rechargemoney'].'元';
                    if($member['rechargemoney'] >= $lv['apply_rechargemoney']) {
                        $aglevelList[$k]['applytj_reach'] = 1;
                    }
                }
                if(getcustom('taocan_product')){
                    if( $lv['apply_taocan_proid']>0){
                        $atj[]='购买套餐商品ID'.$lv['apply_taocan_proid'];
                        //查找套餐订单
                        $buy_taocan = Db::name('taocan_order_goods')
                            ->where('mid','=',mid)
                            ->where('proid','=',$lv['apply_taocan_proid'])
                            ->where('status','in','1,2,3')
                            ->where('createtime','>',$down_level_time)
                            ->find();
                    }
                }
                if($atj){
                    $aglevelList[$k]['applytj'] = implode(' 或 ',$atj);
                }
            }
            //升级
//            if($lv['can_up'] == 1 && $lv['up_condition_show'] == 1){
//                $tj = array();
//                $must = array();
//                //if($lv['up_ordercount'] > 0) $tj[]='订单满'.$lv['up_ordercount'].'个';
//                if($lv['up_wxpaymoney'] > 0) $tj['up_wxpaymoney']='微信支付金额满'.$lv['up_wxpaymoney'].'元';
//                if($lv['up_ordermoney'] > 0) $tj['up_ordermoney']='订单金额满'.$lv['up_ordermoney'].'元';
//                if($lv['up_rechargemoney'] > 0) $tj['up_rechargemoney']='充值金额满'.$lv['up_rechargemoney'].'元';
//                if($lv['up_fxordermoney'] > 0) {
//                    if($lv['up_fxorder_condition'] == 'and'){
//                        $must['up_fxordermoney']='分销订单满'.$lv['up_fxordermoney'].'元';
//                    }else{
//                        $tj['up_fxordermoney']='分销订单满'.$lv['up_fxordermoney'].'元';
//                    }
//
//                }
//                if(getcustom('member_levelup_businessnum')){
//					if($lv['up_businessnum'] > 0) $tj['up_businessnum']='推荐商家成功入驻数量满'.$lv['up_businessnum'].'个';
//				}
//                if($lv['up_fxdowncount'] > 0) {
//                    $up_fxdownlevelnum = '';
//                    $levelnameStr = '';
//                    if($lv['up_fxdownlevelnum'] > 0) {
//                        $up_fxdownlevelnum = $lv['up_fxdownlevelnum'];
//                    }
//                    if(!empty($lv['up_fxdownlevelid'])) {
//                        $levelNames = Db::name('member_level')->where('aid', aid)->where('id', 'in', $lv['up_fxdownlevelid'])->column('name');
//                        $levelnameStr = '['.implode(',',$levelNames).']';
//                    }
//                    if($lv['up_fxorder_condition'] == 'and')
//                        $must['up_fxdowncount']='下'.$up_fxdownlevelnum.'级'.$levelnameStr.'总人数满'.$lv['up_fxdowncount'].'个';
//                    else
//                        $tj['up_fxdowncount']='下'.$up_fxdownlevelnum.'级'.$levelnameStr.'总人数满'.$lv['up_fxdowncount'].'个';
//                }
//                if($lv['up_fxdowncount2'] > 0) {
//                    $up_fxdownlevelnum = '';
//                    $levelnameStr = '';
//                    if($lv['up_fxdownlevelnum2'] > 0) {
//                        $up_fxdownlevelnum = $lv['up_fxdownlevelnum2'];
//                    }
//                    if(!empty($lv['up_fxdownlevelid2'])) {
//                        $levelNames = Db::name('member_level')->where('aid', aid)->where('id', 'in', $lv['up_fxdownlevelid2'])->column('name');
//                        $levelnameStr = '['.implode(',',$levelNames).']';
//                    }
//                    //if($lv['up_fxorder_condition'] == 'and')
//                        $must['up_fxdowncount'].=' 且 下'.$up_fxdownlevelnum.'级'.$levelnameStr.'总人数满'.$lv['up_fxdowncount2'].'个';
//                   // else
//                    //    $tj['up_fxdowncount'].=' 且 下'.$up_fxdownlevelnum.'级'.$levelnameStr.'总人数满'.$lv['up_fxdowncount2'].'个';
//                }
//                if($lv['up_fxdowncount3'] > 0) {
//                    $up_fxdownlevelnum = '';
//                    $levelnameStr = '';
//                    if($lv['up_fxdownlevelnum3'] > 0) {
//                        $up_fxdownlevelnum = $lv['up_fxdownlevelnum3'];
//                    }
//                    if(!empty($lv['up_fxdownlevelid3'])) {
//                        $levelNames = Db::name('member_level')->where('aid', aid)->where('id', 'in', $lv['up_fxdownlevelid3'])->column('name');
//                        $levelnameStr = '['.implode(',',$levelNames).']';
//                    }
//                    if($lv['up_fxorder_condition'] == 'and')
//                        $must['up_fxdowncount'].=' 且 下'.$up_fxdownlevelnum.'级'.$levelnameStr.'总人数满'.$lv['up_fxdowncount3'].'个';
//                    else
//                        $tj['up_fxdowncount'].=' 且 下'.$up_fxdownlevelnum.'级'.$levelnameStr.'总人数满'.$lv['up_fxdowncount3'].'个';
//                }
////                if($lv['up_fxdowncount2'] > 0 || $lv['up_fxdowncount3'] > 0) {
////                    $tj['up_fxdowncount'] = '（'.$tj['up_fxdowncount'].'）';
////                }
//                if($lv['up_fxdowncount_and'] > 0) {
//                    $up_fxdownlevelnum = '';
//                    $levelnameStr = '';
//                    if($lv['up_fxdownlevelnum_and'] > 0) {
//                        $up_fxdownlevelnum = $lv['up_fxdownlevelnum_and'];
//                    }
//                    if(!empty($lv['up_fxdownlevelid_and'])) {
//                        $levelNames = Db::name('member_level')->where('aid', aid)->where('id', 'in', $lv['up_fxdownlevelid_and'])->column('name');
//                        $levelnameStr = '['.implode(',',$levelNames).']';
//                    }
//                    $must['up_fxdowncount_and']='下'.$up_fxdownlevelnum.'级'.$levelnameStr.'总人数满'.$lv['up_fxdowncount_and'].'个';
//                }
//                if($lv['up_fxdowncount2_and'] > 0) {
//                    $up_fxdownlevelnum = '';
//                    $levelnameStr = '';
//                    if($lv['up_fxdownlevelnum2_and'] > 0) {
//                        $up_fxdownlevelnum = $lv['up_fxdownlevelnum2_and'];
//                    }
//                    if(!empty($lv['up_fxdownlevelid2_and'])) {
//                        $levelNames = Db::name('member_level')->where('aid', aid)->where('id', 'in', $lv['up_fxdownlevelid2_and'])->column('name');
//                        $levelnameStr = '['.implode(',',$levelNames).']';
//                    }
//                    $must['up_fxdowncount_and'].=' 且 下'.$up_fxdownlevelnum.'级'.$levelnameStr.'总人数满'.$lv['up_fxdowncount2_and'].'个';
//                }
////                if($lv['up_fxdowncount2_and'] > 0) {
////                    $tj['up_fxdowncount_and'] = '（'.$tj['up_fxdowncount_and'].'）';
////                }
//                if($lv['up_proid'] && $lv['up_pronum']) {
//                    $proids = explode(',',$lv['up_proid']);
//                    $numArr = explode(',',$lv['up_pronum']);
//                    $products = Db::name('shop_product')->whereIn('id',$proids)->column('name');
//					if($lv['up_buygoods_condition'] == 'and') {
//						$tj['up_proid'] .= ' 且 ';
//					}
//                    if(count($products) > 1 && count($numArr) > 1) {
//                        $tj['up_proid'] .='购买商品';
//                        foreach($products as $key => $proname) {
//                            $tj['up_proid'].='['.$proname.']*'.$numArr[$key].' ';
//                        }
//                    } elseif(count($products) > 1 && count($numArr) ==1) {
//                        $tj['up_proid'] .='购买商品';
//                        foreach($products as $key => $proname) {
//                            $tj['up_proid'] .='['.$proname.']';
//                        }
//                        $tj['up_proid'] .= '累计满'.$numArr[0].'件';
//                    } else {
//                        $tj['up_proid']='购买商品['.$products[0].']*'.$numArr[0];
//                    }
//                }
//                if($lv['up_getmembercard']==1) $tj[]='领取微信会员卡';
////                dd($tj);
////                if($tj){
////                    $i = 1;
////                    $aglevelList[$k]['uptj'] = '';
////                    foreach($tj as $key => $item) {
////                        if($i == 1) {
////                            $aglevelList[$k]['autouptj'] .= $item;
////                        } else {
////                            $realtion = ' 或 ';
////                            if($lv['up_fxorder_condition'] == 'and' && $key == 'up_fxordermoney') {
////                                $realtion = ' 且 ';
////                            }
////                            if($key == 'up_fxdowncount_and') {
////                                $realtion = ' 且 ';
////                            }
////                            $aglevelList[$k]['autouptj'] .= $realtion.$item;
////                        }
////                        $i++;
////                    }
////                }
//                if($must){
//                    $i = 1;
//                    $aglevelList[$k]['uptj'] = '';
//                    foreach($must as $key => $item)
//                    {
//                        $aglevelList[$k]['autouptj'] .= $i.'.'.$item."<br>";
//                        $i++;
//                    }
//                }
//                if($tj){
//                    $i = count($must)+1;
//                    $aglevelList[$k]['uptj'] = '';
//                    $aglevelList[$k]['autouptj'] .= "<p style='color: #333;margin: 10px 0 5px;'>以下条件任意满足一个即可</p>";
//                    foreach($tj as $key => $item)
//                    {
//                        $aglevelList[$k]['autouptj'] .= $i.'.'.$item."<br>";
//                        $i++;
//                    }
//                }
//            }

            //升级
            if($lv['can_up']){
                $tj = array();
                //if($v['up_ordercount'] > 0) $tj[]='订单满'.$v['up_ordercount'].'个';
                if($lv['up_wxpaymoney'] > 0) $tj['up_wxpaymoney']='微信支付金额满'.$lv['up_wxpaymoney'].'元';
                if($lv['up_ordermoney'] > 0) $tj['up_ordermoney']='订单金额满'.$lv['up_ordermoney'].'元';
                if($lv['up_rechargemoney'] > 0) $tj['up_rechargemoney']='充值金额满'.$lv['up_rechargemoney'].'元';
                if($lv['up_perpaymoney'] > 0) $tj['up_perpaymoney']='单次消费满'.$lv['up_perpaymoney'].'元';
                if(getcustom('member_levelup_orderprice')){
                    if($lv['up_orderprice'] > 0) $tj['up_orderprice']='单次订单满'.$lv['up_orderprice'].'元';
                }
                if($lv['up_fxordermoney'] > 0) $tj['up_fxordermoney']='分销订单满'.$lv['up_fxordermoney'].'元';
                if($lv['up_fxdowncount'] > 0) $tj['up_fxdowncount']='下级总人数满'.$lv['up_fxdowncount'].'个';
                if($lv['up_fxdowncount_and'] > 0) $tj['up_fxdowncount_and']='下级总人数满'.$lv['up_fxdowncount_and'].'个';
                if($lv['up_proid'] > 0 && $lv['up_pronum'] > 0) $tj['up_proid']='购买商品['.Db::name('shop_product')->where('id',$lv['up_proid'])->value('name').']*'.$lv['up_pronum'];
                if($lv['up_getmembercard']==1) $tj[]='领取微信会员卡';
                if(getcustom('member_levelup_businessnum')){
                    if($lv['up_businessnum'] > 0) $tj['up_businessnum']='推荐商家成功入驻数量满'.$lv['up_businessnum'].'个';
                }
                if(getcustom('member_up_binding_tel')){
                    if($lv['up_binding_tel']>0)$tj['up_binding_tel']='绑定手机号';
                }
                if(getcustom('levelup_teamnum_peoplenum')){
                    $up_team_path_num_tj = '';
                    if($lv['up_team_path_num']>0) {
                        $up_team_path_num_tj .='团队满'.$lv['up_team_path_num'].'条线';
                        if($lv['up_team_people_num']>0){
                            $up_team_path_num_tj .='，每条线超'.$lv['up_team_people_num'].'人';
                            if($lv['up_team_path_level']){
                                $up_team_path_num_tj .='等级ID：'.$lv['up_team_path_level'];
                            }
                        }
                    }
                    if($up_team_path_num_tj){
                        $tj['up_team_path_num']=$up_team_path_num_tj;
                    }
                }

                if($tj){
                    $i = 1;
                    $aglevelList[$k]['autouptj'] = '';
                    foreach($tj as $key => $item) {
                        if($i == 1) {
                            $aglevelList[$k]['autouptj'] .= $item;
                        } else {
                            $realtion = ' 或 ';
                            if($lv['up_fxorder_condition'] == 'and' && $key == 'up_fxordermoney') {
                                $realtion = ' 且 ';
                            }
                            if (getcustom('member_levelup_orderprice') && $lv['up_orderprice_condition'] == 'and' && $key == 'up_orderprice') {
                                $realtion = ' 且 ';
                            }
                            if($key == 'up_fxdowncount_and') {
                                $realtion = ' 且 ';
                            }
                            if($lv['up_buygoods_condition'] == 'and' && $key == 'up_proid') {
                                $realtion = ' 且 ';
                            }
                            if(getcustom('member_up_binding_tel')){
                                if($lv['up_binding_tel_condition'] == 'and' && $key == 'up_binding_tel') {
                                    $realtion = ' 且 ';
                                }
                            }
                            if(getcustom('levelup_teamnum_peoplenum')){
                                if($lv['up_team_path_condition'] == 'and' && $key == 'up_team_path_num') {
                                    $realtion = ' 且 ';
                                }
                            }


                            $aglevelList[$k]['autouptj'] .= $realtion.$item;
                        }
                        $i++;
                    }
                }else{
                    $aglevelList[$k]['autouptj'] = '不自动升级';
                }

            }else{
                $aglevelList[$k]['autouptj'] = '不自动升级';
                if($lv['isdefault']){
                    $aglevelList[$k]['autouptj'] = '默认等级无需升级';
                }
            }

			$aglevelList[$k]['apply_formdata'] = json_decode($lv['apply_formdata'],true);
			if(!$aglevelList[$k]['apply_paytxt']) $aglevelList[$k]['apply_paytxt'] = '升级费用';
			if(getcustom('up_level_agree3')){
                $aglevelList[$k]['is_agree'] = $lv['is_agree'];
                $aglevelList[$k]['agree_content'] = $lv['agree_content'];
            }else{
                $aglevelList[$k]['is_agree'] = 0;
            }
		}
		$set = Db::name('admin_set')->where('aid',aid)->field('name,logo,desc,banner_levelup')->find();
		
		$rdata = [];
		$rdata['sysset'] = $set;
		$rdata['userinfo'] = $member;
		$rdata['aglevelList'] = $aglevelList;
		$rdata['userlevel'] = $mylevel;
		if(getcustom('areafenhong_jiaquan')){
			$largearea = Db::name('largearea')->where('aid',aid)->where('status',1)->order('sort desc,id')->column('name');
			$rdata['largearea'] = $largearea;
		}
		if(getcustom('levelup_code')){
			$rdata['levelupcode'] = true;
			$rdata['bgset'] = '';

			//查询背景设置
			$bgset = Db::name('member_level_bgset')->where('aid',aid)->find();
			if($bgset){
				$rdata['bgset'] = [
					'title'      => $bgset['title'],
					'level_name' => $bgset['level_name'],
					'bgcolor'    => $bgset['bgcolor'],
					'bgimg'      => $bgset['bgimg'],
				];
			}
			if(getcustom('school_product')){
			    if($ycode){
                    $need_school = Db::name('admin')->where('id',aid)->value('need_school');
                    if($need_school==1){
                        //查询该学校下面的班级
                        $school = Db::name('school')->where('aid',aid)->where('number',$ycode)->find();
                        if(empty($school)){
                            return $this->json(['status'=>0,'msg'=>'错误的学校编码']);
                        }
                        $gradelist = Db::name('school_class')->where('aid',aid)->where('sid',$school['id'])->where('pid',0)->select()->toArray();
                        foreach ($gradelist as $sk=>$sv){
                            $classlist = Db::name('school_class')->where('aid',aid)->where('sid',$school['id'])->where('pid',$sv['id'])->select()->toArray();
                            $gradelist[$sk]['classlist'] = $classlist??[];
                        }
                        $rdata['need_school'] = 1;
                        $rdata['school_id'] = $school['id'];
                        $rdata['gradelist'] = $gradelist;
                        return $this->json($rdata);
                    }
                }
            }
	    }
		return $this->json($rdata);
	}
	function getPayCommissionApplyOrder(){
		$post = input('post.');
		$order = Db::name('member_levelup_order')->field('title,totalprice,ordernum,id')->where('id',$post['orderid'])->where('aid',aid)->where('mid',mid)->find();
		if(!$order){
			return $this->json(['status'=>0,'msg'=>'该订单不存在']);
		}
		if($order['status']){
			return $this->json(['status'=>0,'msg'=>'该订单已支付']);
		}
		return $this->json(['status'=>1,'msg'=>'获取成功','orderinfo'=>$order]);
	}
	function payCommissionApplyOrder(){
		$post = input('post.');
		$order = Db::name('member_levelup_order')->where('id',$post['orderid'])->where('aid',aid)->where('mid',mid)->find();
		if(!$order){
			return $this->json(['status'=>0,'msg'=>'该订单不存在']);
		}
		if($order['status'] > 0){
			return $this->json(['status'=>0,'msg'=>'该订单已支付']);
		}
		$leveldata = Db::name('member_level')->where('aid',aid)->where('id',$order['levelid'])->find();

		$levelid = $leveldata['id'];

		if($order['totalprice'] <=0){
			Db::name('member_levelup_order')->where('id',$order['id'])->update(['status'=>1]);
			if($leveldata['apply_check']){
				Db::name('member')->where('aid',aid)->where('id',mid)->update(['realname'=>$order['realname'],'tel'=>$order['tel'],'weixin'=>$order['weixin'],'aliaccount'=>$order['aliaccount'],'bankcardnum'=>$order['bankcardnum'],'bankcarduser'=>$order['bankcarduser'],'bankname'=>$order['bankname']]);
				$return = ['status'=>2,'msg'=>'付款成功请等待审核'];
			}else{
				Db::name('member')->where('aid',aid)->where('id',mid)->update(['realname'=>$order['realname'],'tel'=>$order['tel'],'weixin'=>$order['weixin'],'aliaccount'=>$order['aliaccount'],'bankcardnum'=>$order['bankcardnum'],'bankcarduser'=>$order['bankcarduser'],'bankname'=>$order['bankname'],'levelid'=>$levelid]);
				Db::name('member_levelup_order')->where('id',$order['id'])->update(['status'=>2]);
				$return = ['status'=>3,'msg'=>'申请成功'];
			}
			return $return;
		}
		//余额支付
		if($post['typeid']==2){
			if($this->member['money'] < $order['totalprice']){
				return $this->json(['status'=>0,'msg'=>t('余额').'不足,请充值']);
			}
			Db::name('member_levelup_order')->where('id',$order['id'])->update(['paytype'=>t('余额').'支付','paytime'=>time(),'status'=>1]);
			//减去会员的余额
			\app\commons\Member::addmoney(aid,mid,-$order['totalprice'],$order['title']);

			if($leveldata['apply_check']){
				Db::name('member')->where('aid',aid)->where('id',mid)->update(['realname'=>$order['realname'],'tel'=>$order['tel'],'weixin'=>$order['weixin'],'aliaccount'=>$order['aliaccount'],'bankcardnum'=>$order['bankcardnum'],'bankcarduser'=>$order['bankcarduser'],'bankname'=>$order['bankname']]);
				$return = ['status'=>2,'msg'=>'付款成功请等待审核'];
			}else{
				Db::name('member')->where('aid',aid)->where('id',mid)->update(['realname'=>$order['realname'],'tel'=>$order['tel'],'weixin'=>$order['weixin'],'aliaccount'=>$order['aliaccount'],'bankcardnum'=>$order['bankcardnum'],'bankcarduser'=>$order['bankcarduser'],'bankname'=>$order['bankname'],'levelid'=>$levelid]);
				Db::name('member_levelup_order')->where('id',$order['id'])->update(['status'=>2]);
				$return = ['status'=>3,'msg'=>'申请成功'];
			}
			return $return;
		}else{
			$rs = \app\commons\Wxpay::build(aid,mid,$this->member['openid'],'升级['.$leveldata['name'].']',$order['ordernum'],$order['totalprice'],'member_levelup_order');
			$rs['apply_check'] = $leveldata['apply_check'];
			return $rs;
		}
	}
	//领卡回调
	public function usergetcard(){
		$post = input('post.');
		Log::write('领取会员卡---'.mid.'---'.print_r($post,true));
		$extraData = $post['extraData'];
		$code = $extraData['code'];
		$activate_ticket = $extraData['activate_ticket'];
		$card_id = $extraData['card_id'];
		if($this->member['activate_ticket'] && $this->member['activate_ticket'] == $activate_ticket){
			return $this->json(['status'=>1,'msg'=>'已开卡']);
		}
		$url = urldecode($extraData['wx_activate_after_submit_url']);
		$params =  explode('&',explode('?',$url)[1]);
		$mpopenid = '';
		foreach($params as $v){
			if($v!=''){
				$vArr = explode('=',$v);
				if($vArr[0] == 'openid'){
					$mpopenid = $vArr[1];
				}
			}
		}
		Log::write('公众号openid '.$mpopenid);

		$mdata = [];
		$mdata['mpopenid'] = $mpopenid;
		$mdata['card_id'] = $card_id;
		$mdata['card_code'] = $code;
		$mdata['activate_ticket'] = $activate_ticket;

		Db::name('member')->where('aid',aid)->where('id',mid)->update($mdata);
		return $this->json(['status'=>1,'msg'=>'开卡成功']);
		die;
		$access_token = access_token(aid,'mp');
		$url = 'https://api.weixin.qq.com/card/membercard/activatetempinfo/get?access_token='.$access_token;
		$rs = request_post($url,jsonEncode(['activate_ticket'=>$activate_ticket]));
		$rs = json_decode($rs,true);
		Log::write('获取领卡信息---'.mid.'---'.print_r($rs,true));
		if($rs['errcode'] == 0){
			if($rs['info']){
				foreach($rs['info']['common_field_list'] as $key=>$v){
					if($v['name'] == 'USER_FORM_INFO_FLAG_MOBILE'){
						$mdata['tel']=$v['value'];
					}
					if($v['name'] == 'USER_FORM_INFO_FLAG_NAME'){
						$mdata['realname']=$v['value'];
					}
					if($v['name'] == 'USER_FORM_INFO_FLAG_BIRTHDAY'){
						$mdata['birthday'] = $v['value'];
					}
					if($v['name'] == 'USER_FORM_INFO_FLAG_LOCATION'){
						$mdata['location']=$v['value'];
					}
					if($v['name'] == 'USER_FORM_INFO_FLAG_IDCARD'){
						$mdata['usercard']=$v['value'];
					}
				}
			}
		}else{
			Log::write('领卡时获取开卡的信息失败:'.$rs);
		}
	}

	//余额宝提现
	public function yuebao_withdraw(){
		if(!getcustom('plug_yuebao')) {
			die;
		}
		$set = Db::name('admin_set')
		     ->where('aid',aid)
		     ->field('open_yuebao,yuebao_withdraw_time,yuebao_withdraw,yuebao_withdrawmin,yuebao_withdrawfee,withdraw_autotransfer,withdraw_weixin,withdraw_aliaccount,withdraw_bankcard,yuebao_turn_yue')
		     ->find();
		if($set['open_yuebao'] == 0){
			return $this->json(['status'=>0,'msg'=>t('余额宝').'功能未开启']);
		}
		if(request()->isPost()){
			$post = input('post.');

			if($set['yuebao_withdraw'] == 0){
				return $this->json(['status'=>0,'msg'=>t('余额宝').'收益提现功能未开启']);
			}

			//查询上次提现、转余额时间
			$find_ytime = Db::name('member_yuebao_moneylog')
				->where('aid',aid)
				->where('mid',mid)
				->where('money','<',0)
				->where('type','>=',2)
				->where('type','<=',3)
				->field('createtime')
				->order('createtime desc')
				->find();
			if($find_ytime){
				//现在时间与上次时间差
				$cha = time()-$find_ytime['createtime'];

				//限制提现天数
				$wday  = 0;
				//限制提现秒数
				$wtime = 0;

				//如果单独设置天数
				if($this->member['yuebao_withdraw_time']>0){
					$wday = $this->member['yuebao_withdraw_time'];
					//转换天为秒
					$wtime = $this->member['yuebao_withdraw_time']*24*60*60;
				}else{
					//如果单独设置天数为负数，且总天数设置大于0
					if($this->member['yuebao_withdraw_time'] <0 && $set['yuebao_withdraw_time']>0){
						$wday = $set['yuebao_withdraw_time'];
						//转换天为秒
						$wtime = $set['yuebao_withdraw_time']*24*60*60;
					}
				}

				if($wtime>0 && $cha<$wtime){
					return $this->json(['status'=>0,'msg'=>t('余额宝').'收益'.$wday.'天可提现一次']);
				}

			}
			if($post['paytype']=='支付宝' && $this->member['aliaccount']==''){
				return $this->json(['status'=>0,'msg'=>'请先设置支付宝账号']);
			}
			if($post['paytype']=='银行卡' && ($this->member['bankname']==''||$this->member['bankcarduser']==''||$this->member['bankcardnum']=='')){
				return $this->json(['status'=>0,'msg'=>'请先设置完整银行卡信息']);
			}

			$money = $post['money'];
			if($money<=0 || $money < $set['yuebao_withdrawmin']){
				return $this->json(['status'=>0,'msg'=>'提现金额必须大于'.($set['yuebao_withdrawmin']?$set['yuebao_withdrawmin']:0)]);
			}
			if($money > $this->member['yuebao_money']){
				return $this->json(['status'=>0,'msg'=>'可提现'.t('余额宝').'收益不足']);
			}

			$ordernum = date('ymdHis').aid.rand(1000,9999);
			$record['aid'] = aid;
			$record['mid'] = mid;
			$record['createtime']= time();
			$record['money'] = $money*(1-$set['yuebao_withdrawfee']*0.01);
			if($record['money'] <= 0) {
                return $this->json(['status'=>0,'msg'=>'提现金额有误']);
            }
            $record['money'] = round($record['money'],2);
			$record['txmoney'] = $money;
			if($post['paytype']=='支付宝'){
				$record['aliaccountname'] = $this->member['aliaccountname'];
				$record['aliaccount'] = $this->member['aliaccount'];
			}
			if($post['paytype']=='银行卡'){
				$record['bankname'] = $this->member['bankname'] . ($this->member['bankaddress'] ? ' '.$this->member['bankaddress'] : '');
				$record['bankcarduser'] = $this->member['bankcarduser'];
				$record['bankcardnum'] = $this->member['bankcardnum'];
			}
			$record['ordernum'] = $ordernum;
			$record['paytype']  = $post['paytype'];
			$record['platform'] = platform;
			$recordid = Db::name('member_yuebao_withdrawlog')->insertGetId($record);

			\app\commons\Member::addyuebaomoney(aid,mid,-$money,t('余额宝').'收益提现',2);

			if($post['paytype'] != ''){
				$tmplcontent = array();
				$tmplcontent['first'] = '有客户申请'.t('余额宝').'收益提现';
				$tmplcontent['remark'] = '点击进入查看~';
				$tmplcontent['keyword1'] = $this->member['nickname'];
				$tmplcontent['keyword2'] = date('Y-m-d H:i');
				$tmplcontent['keyword3'] = $money.'元';
				$tmplcontent['keyword4'] = $post['paytype'];
				\app\commons\Wechat::sendhttmpl(aid,0,'tmpl_withdraw',$tmplcontent,m_url('admin/finance/yuebaowithdrawlog'));
				
				$tmplcontent = [];
				$tmplcontent['name3'] = $this->member['nickname'];
				$tmplcontent['amount1'] = $money.'元';
				$tmplcontent['date2'] = date('Y-m-d H:i');
				$tmplcontent['thing4'] = '提现到'.$post['paytype'];
				\app\commons\Wechat::sendhtwxtmpl(aid,0,'tmpl_withdraw',$tmplcontent,'admin/finance/yuebaowithdrawlog');
			}

			if($set['withdraw_autotransfer'] && ($post['paytype'] == '微信钱包' || $post['paytype'] == '银行卡')){
                Db::name('member_yuebao_withdrawlog')->where('id',$recordid)->update(['status' => 1]);
				$rs = \app\commons\Wxpay::transfers(aid,mid,$record['money'],$record['ordernum'],platform,t('余额宝').'收益提现');
				if($rs['status']==0){
					return json(['status'=>1,'msg'=>'提交成功,请等待打款']);
				}else{
                    Db::name('member_yuebao_withdrawlog')->where('id',$recordid)->update(['status' => 3]);
					Db::name('member_yuebao_withdrawlog')->where('aid',aid)->where('id',$recordid)->update(['status'=>3,'paytime'=>time(),'paynum'=>$rs['resp']['payment_no']]);
					//提现成功通知
					$tmplcontent = [];
					$tmplcontent['first'] = '您的提现申请已打款，请留意查收';
					$tmplcontent['remark'] = '请点击查看详情~';
					$tmplcontent['money'] = (string) round($record['money'],2);
					$tmplcontent['timet'] = date('Y-m-d H:i',$record['createtime']);
                    $tempconNew = [];
                    $tempconNew['amount2'] = (string) round($record['money'],2);//提现金额
                    $tempconNew['time3'] = date('Y-m-d H:i',$record['createtime']);//提现时间
					\app\commons\Wechat::sendtmpl(aid,$record['mid'],'tmpl_tixiansuccess',$tmplcontent,m_url('pages/my/usercenter'),$tempconNew);
					//订阅消息
					$tmplcontent = [];
					$tmplcontent['amount1'] = $record['money'];
					$tmplcontent['thing3'] = '微信打款';
					$tmplcontent['time5'] = date('Y-m-d H:i');
					
					$tmplcontentnew = [];
					$tmplcontentnew['amount3'] = $record['money'];
					$tmplcontentnew['phrase9'] = '微信打款';
					$tmplcontentnew['date8'] = date('Y-m-d H:i');
					\app\commons\Wechat::sendwxtmpl(aid,$record['mid'],'tmpl_tixiansuccess',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);
					//短信通知
					if($this->member['tel']){
						\app\commons\Sms::send(aid,$this->member['tel'],'tmpl_tixiansuccess',['money'=>$record['money']]);
					}
					return json(['status'=>1,'msg'=>$rs['msg']]);
				}
			}

			return $this->json(['status'=>1,'msg'=>'提交成功,请等待打款']);
		}
		$userinfo = Db::name('member')->where('id',mid)->field('id,yuebao_money,aliaccount,bankname,bankcarduser,bankcardnum')->find();
		//订阅消息
		$wx_tmplset = Db::name('wx_tmplset')->where('aid',aid)->find();
		$tmplids = [];
		if($wx_tmplset['tmpl_tixiansuccess_new']){
			$tmplids[] = $wx_tmplset['tmpl_tixiansuccess_new'];
		}elseif($wx_tmplset['tmpl_tixiansuccess']){
			$tmplids[] = $wx_tmplset['tmpl_tixiansuccess'];
		}
		if($wx_tmplset['tmpl_tixianerror_new']){
			$tmplids[] = $wx_tmplset['tmpl_tixianerror_new'];
		}elseif($wx_tmplset['tmpl_tixianerror']){
			$tmplids[] = $wx_tmplset['tmpl_tixianerror'];
		}
		$rdata = [];
		$rdata['status'] = 1;
		$rdata['userinfo'] = $userinfo;
		$rdata['sysset'] = $set;
		$rdata['tmplids'] = $tmplids;
		return $this->json($rdata);
	}

	//余额宝收益转到余额
    public function yuebao_turn_money()
    {
    	if(!getcustom('plug_yuebao')) {
			die;
		}
		$set = Db::name('admin_set')->where('aid',aid)->field('open_yuebao,yuebao_withdraw_time,yuebao_turn_yue')->find();
		if($set['open_yuebao'] !=1){
			return $this->json(['status'=>0,'msg'=>'余额宝功能未启用']);
		}
		if($set['yuebao_turn_yue'] !=1){
			return $this->json(['status'=>0,'msg'=>'该功能未启用']);
		}
		//查询上次提现、转余额时间
		$find_ytime = Db::name('member_yuebao_moneylog')
			->where('aid',aid)
			->where('mid',mid)
			->where('money','<',0)
			->where('type','>=',2)
			->where('type','<=',3)
			->field('createtime')
			->order('createtime desc')
			->find();
		if($find_ytime){
			//现在时间与上次时间差
			$cha = time()-$find_ytime['createtime'];

			//限制提现天数
			$wday  = 0;
			$wtime = 0;
			//单独设置天数
			if($this->member['yuebao_withdraw_time']>0){
				$wday = $this->member['yuebao_withdraw_time'];
				//转换天为秒
				$wtime = $this->member['yuebao_withdraw_time']*24*60*60;
			}else{
				//如果单独设置天数为负数，且总天数设置大于0
				if($this->member['yuebao_withdraw_time'] <0 && $set['yuebao_withdraw_time']>0){
					$wday = $set['yuebao_withdraw_time'];
					//转换天为秒
					$wtime = $set['yuebao_withdraw_time']*24*60*60;
				}
			}

			if($wtime>0 && $cha<$wtime){
				return $this->json(['status'=>0,'msg'=>t('余额宝').'收益'.$wday.'天可转'.t('余额').'一次']);
			}
		}
		$post = input('post.');

		$money = floatval($post['money']);
		if($money <= 0 || $money > $this->member['yuebao_money']){
			return $this->json(['status'=>0,'msg'=>'转入金额不正确']);
		}
		\app\commons\Member::addmoney(aid,mid,$money,t('余额宝').'收益转'.t('余额'));
		\app\commons\Member::addyuebaomoney(aid,mid,-$money,t('余额宝').'收益转'.t('余额'),3);
		return $this->json(['status'=>1,'msg'=>'转入成功']);
	}
	//余额宝明细
	public function yuebaolog(){
		$st = input('param.st');
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$pernum = 20;
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['mid','=',mid];
		if($st ==1){//提现记录
			$datalist = Db::name('member_yuebao_withdrawlog')->field("id,money,txmoney,`status`,from_unixtime(createtime) createtime")->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
			if(!$datalist) $datalist = [];
		}else{ //余额明细
			$datalist = Db::name('member_yuebao_moneylog')->field("id,money,`after`,from_unixtime(createtime) createtime,remark")->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
			if(!$datalist) $datalist = [];
			foreach($datalist as $k=>$v){
				if(strpos($v['remark'],'商家充值，') === 0){
					$datalist[$k]['remark'] = '商家充值';
				}
			}
		}
		if($pagenum == 1){
			$canwithdraw = Db::name('admin_set')->where('aid',aid)->value('yuebao_withdraw');
		}
		return $this->json(['status'=>1,'data'=>$datalist,'canwithdraw'=>$canwithdraw]);
	}

	//注销账号
	public function delaccount(){
		$mid = mid.'';
		\app\models\Member::del(aid,$mid);
		sleep(2);
		return $this->json(['status'=>1,'msg'=>'账号已注销']);
	}

	public function setAgentCard()
    {
        if(request()->isPost()){
            $formdata = input('post.formdata/a');

            $info = [];
            $info['aid'] = aid;
            $info['mid'] = mid;
            $info['name'] = $formdata['name'];
            $info['shopname'] = $formdata['shopname'];
            $info['address'] = $formdata['address'];
            $info['tel'] = $formdata['tel'];
            $info['logo'] = $formdata['logo'];
            $info['pagecontent'] = json_encode(input('post.pagecontent'));
            $info['latitude'] = $formdata['latitude'];
            $info['longitude'] = $formdata['longitude'];

            $info['createtime'] = time();

            if($formdata['id']){
                Db::name('member_agent_card')->where('aid',aid)->where('mid',mid)->where('id',$formdata['id'])->update($info);
            }else{
                Db::name('member_agent_card')->insertGetId($info);
            }
            return $this->json(['status'=>1,'msg'=>'提交成功']);
        }
        $info = Db::name('member_agent_card')->where('aid',aid)->where('mid',mid)->find();
        $pagecontent = json_decode(\app\commons\System::initpagecontent($info['pagecontent'],aid),true);
        if(!$pagecontent) $pagecontent = [];
        $rdata = [];
        $rdata['info'] = $info ? $info : [];
        $rdata['pagecontent'] = $pagecontent;
        return $this->json($rdata);
    }

    //元宝记录
    public function yuanbaolog(){
        if(getcustom('pay_yuanbao')) {
            $pagenum = input('post.pagenum');
            $st = input('post.st');
            if(!$pagenum) $pagenum = 1;
            $pernum = 20;
            $where = [];
            $where[] = ['aid','=',aid];
            $where[] = ['mid','=',mid];
            $datalist = Db::name('member_yuanbaolog')->field('id,yuanbao,after,remark,from_unixtime(createtime)createtime')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
            if(!$datalist) $datalist = [];
            if(request()->isPost()){
                if($pagenum == 1) {
                    $set = Db::name('admin_set')->where('aid', aid)->find();
                    $yuanbaoTransfer = $set['yuanbao_transfer'] ? true : false;
                }
                return $this->json(['status'=>1,'data'=>$datalist,'myyuanbao'=>$this->member['yuanbao'],'yuanbaoTransfer' => $yuanbaoTransfer] );
            }

            $count = Db::name('member_yuanbaolog')->where($where)->count();

            $rdata = [];
            $rdata['count'] = $count;
            $rdata['datalist'] = $datalist;
            $rdata['pernum'] = $pernum;
            $rdata['st'] = $st;
            $rdata['myyuanbao'] = $this->member['yuanbao'];
            $rdata['title'] = t('元宝')."明细";
            return $this->json($rdata);
        }
        
    }

    //元宝转送
    public function yuanbaoTransfer()
    {
        if(getcustom('pay_yuanbao')) {
            $set = Db::name('admin_set')->where('aid', aid)->find();
            if ($set['yuanbao_transfer'] != 1) {
                return $this->json(['status'=>0,'msg'=>t('元宝').'转赠未开启']);
            }
            //元宝转账现金比例
            $yuanbao_money_ratio = $set['yuanbao_money_ratio'];
            
            if(request()->isPost()){
                $mobile = input('post.mobile');
                $mid = input('post.mid/d');
                $integral = input('post.integral');
                if ($integral <= 0){
                    return $this->json(['status'=>0,'msg'=>'请输入正确的'.t('元宝').'数量']);
                }
                if (input('?post.mobile')) {
                    $info = Db::name('member')->where('aid', aid)->where('tel', $mobile)->find();
                }
                if (input('?post.mid')) {
                    $info = Db::name('member')->where('aid', aid)->where('id', $mid)->find();
                }

                if(!$info) return $this->json(['status'=>0,'msg'=>'未找到该'.t('会员')]);
                $user_id = $info['id'];

                if ($info['id'] == mid) {
                    return $this->json(['status'=>0,'msg'=>'不能转赠给自己']);
                }
                if ($integral > $this->member['yuanbao']){
                    return $this->json(['status'=>0,'msg'=>'您的'.t('元宝').'数量不足']);
                }

                $money = $integral*$yuanbao_money_ratio/100;
                $money = round($money,2);
                if($money<=0){
                    //直接转账
                    $rs = \app\commons\Member::addyuanbao(aid,$user_id,$integral,sprintf("来自%s的".t('元宝')."转赠", $this->member["nickname"]));
                    if ($rs['status'] == 1) {
                        \app\commons\Member::addyuanbao(aid,mid,$integral * -1, sprintf(t('元宝')."转赠给：%s",$info['nickname']));
                    }
                    return $this->json(['status'=>1, 'msg' => '转赠成功', 'url'=>'/pages/my/usercenter']);
                }else{
                    $data = [];
                    $data['aid'] = aid;
                    $data['mid'] = mid;
                    $data['to_mid']   = $user_id;
                    $data['ordernum'] = 'Z'.date('ymdHis').rand(100000,999999);
                    $data['money']    = $money;
                    $data['yuanbao']  = $integral;
                    $data['create_time'] = time();

                    if($this->member['pid']){
                        $parent1 = Db::name('member')->where('aid',aid)->where('id',$this->member['pid'])->find();
                        if($parent1){
                            $agleveldata1 = Db::name('member_level')->where('aid',aid)->where('id',$parent1['levelid'])->find();
                            if($agleveldata1 && $agleveldata1['can_agent']!=0){
                                $data['parent1'] = $parent1['id'];
                                if($agleveldata1['commissiontype']==1){ //固定金额按单
                                    $data['parent1commission'] = $agleveldata1['commission1'];
                                }else{
                                    $data['parent1commission'] = $agleveldata1['commission1'] * $money * 0.01;
                                }
                            }
                        }
                    }
                    if($parent1['pid']){
                        $parent2 = Db::name('member')->where('aid',aid)->where('id',$parent1['pid'])->find();
                        if($parent2){
                            $agleveldata2 = Db::name('member_level')->where('aid',aid)->where('id',$parent2['levelid'])->find();
                            if($agleveldata2 && $agleveldata2['can_agent']>1){
                                $data['parent2'] = $parent2['id'];
                                if($agleveldata2['commissiontype']==1){ //固定金额按单
                                    $data['parent2commission'] = $agleveldata2['commission2'];
                                }else{
                                    $data['parent2commission'] = $agleveldata2['commission2'] * $money * 0.01;
                                }
                            }
                        }
                    }
                    if($parent2['pid']){
                        $parent3 = Db::name('member')->where('aid',aid)->where('id',$parent2['pid'])->find();
                        if($parent3){
                            $agleveldata3 = Db::name('member_level')->where('aid',aid)->where('id',$parent3['levelid'])->find();
                            if($agleveldata3 && $agleveldata3['can_agent']>2){
                                $data['parent3'] = $parent3['id'];
                                if($agleveldata3['commissiontype']==1){ //固定金额按单
                                    $data['parent3commission'] = $agleveldata3['commission3'];
                                }else{
                                    $data['parent3commission'] = $agleveldata3['commission3'] * $money * 0.01;
                                }
                            }
                        }
                    }

                    $insert_id = Db::name('member_yuanbao_transfer_order')->insertGetId($data);
                    if($insert_id){
                        $payorderid = \app\models\Payorder::createorder(aid,0,mid,'member_yuanbao_transfer',$insert_id,$data['ordernum'],t('元宝')."转赠给".$info['nickname'],$money,0);
                        $up = Db::name('member_yuanbao_transfer_order')->where('id',$insert_id)->update(['payorderid'=>$payorderid]);
                        return $this->json(['status'=>2, 'msg' => '提交成功','payorderid'=>$payorderid]);
                    }else{
                        return $this->json(['status'=>0, 'msg' => '提交失败']);
                    }
                }
            }

            $rdata['status'] = 1;
            $rdata['myyuanbao'] = $this->member['yuanbao'];
            $rdata['yuanbao_money_ratio'] = $yuanbao_money_ratio;
            $rdata['title'] = t('元宝')."转账";
            return $this->json($rdata);
        }
    }

    public function othermoneylog(){
    	if(getcustom('other_money')){
    		//是否有多账户权限
            $othermoney_status = Db::name('admin')->where('id',aid)->value('othermoney_status');
            if($othermoney_status != 1){
                return json(['status'=>0,'msg'=>'无权限操作']);
            }
	    	$type   = input('post.type');
	    	$st = input('param.st');
	        if($type == 'money2'){
	            $type_name = t('余额2');
	            $log_type  = 2;
	        }else if($type == 'money3'){
	            $type_name = t('余额3');
	            $log_type  = 3;
	        }else if($type == 'money4'){
	            $type_name = t('余额4');
	            $log_type  = 4;
	        }else if($type == 'money5'){
	            $type_name = t('余额5');
	            $log_type  = 5;
	        }else if($type == 'frozen_money'){
	            $type_name = t('冻结金额');
	            $log_type  = 0;
	        }else{
	            return json(['status'=>0,'msg'=>'操作类型错误']);
	        }

	        $pagenum = input('post.pagenum');
	        if(!$pagenum) $pagenum = 1;
	        $pernum = 20;
	        $where = [];
	        $where[] = ['aid','=',aid];
	        $where[] = ['mid','=',mid];
	        $where[] = ['type','=',$log_type];

	        if($st ==1){//提现记录
	            $datalist = Db::name('member_otherwithdrawlog')->field("id,money,txmoney,`status`,from_unixtime(createtime) createtime")->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
	            if(!$datalist) $datalist = [];
	        }else{ //明细
	            $datalist = Db::name('member_othermoneylog')->field("id,money,`after`,from_unixtime(createtime) createtime,remark")->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
	            if(!$datalist) $datalist = [];
	            foreach($datalist as $k=>$v){
	                if(strpos($v['remark'],'商家充值，') === 0){
	                    $datalist[$k]['remark'] = '商家充值';
	                }
	            }
	        }
	        if($pagenum == 1){
	            $canwithdraw = Db::name('admin_set')->where('aid',aid)->value('withdraw');
	        }
	        if($type == 'frozen_money'){
	             return $this->json(['status'=>1,'data'=>$datalist,'canwithdraw'=>$canwithdraw,'type_name'=>$type_name,'money'=>$this->member['frozen_money']]);
	        }else{
	             return $this->json(['status'=>1,'data'=>$datalist,'canwithdraw'=>$canwithdraw,'type_name'=>$type_name,]);
	        }
	    }
    }

    public function otherwithdraw(){
    	if(getcustom('other_money')){
    		//是否有多账户权限
            $othermoney_status = Db::name('admin')->where('id',aid)->value('othermoney_status');
            if($othermoney_status != 1){
                return json(['status'=>0,'msg'=>'无权限操作']);
            }
	        $set = Db::name('admin_set')->where('aid',aid)->field('withdraw_autotransfer,withdraw,withdrawmin,withdrawfee,withdraw_weixin,withdraw_aliaccount,withdraw_bankcard,othermoney_withdraw')->find();
	        $type   = input('type');
	        if($type == 'money2'){
	            $type_name    = t('余额2');
	            $log_type     = 2;
	            $field 	      = 'id,money2 as money,aliaccount,bankname,bankcarduser,bankcardnum';
	            $member_money = $this->member['money2'];
	        }else if($type == 'money3'){
	            $type_name    = t('余额3');
	            $log_type     = 3;
	            $field 	      = 'id,money3 as money,aliaccount,bankname,bankcarduser,bankcardnum';
	            $member_money = $this->member['money3'];
	        }else if($type == 'money4'){
	            $type_name    = t('余额4');
	            $log_type     = 4;
	            $field 	      = 'id,money4 as money,aliaccount,bankname,bankcarduser,bankcardnum';
	            $member_money = $this->member['money4'];
	        }else if($type == 'money5'){
	            $type_name    = t('余额5');
	            $log_type     = 5;
	            $field 	      = 'id,money5 as money,aliaccount,bankname,bankcarduser,bankcardnum';
	            $member_money = $this->member['money5'];
	        }else{
	            return json(['status'=>0,'msg'=>'操作类型错误']);
	        }
	        if(request()->isPost()){
	            $post = input('post.');

	            if($set['withdraw'] == 0 ){
	                return $this->json(['status'=>0,'msg'=>$type_name.'提现功能未开启']);
	            }
	            if(!$set['othermoney_withdraw']){
	                return $this->json(['status'=>0,'msg'=>$type_name.'提现功能未开启']);
	            }
	            if($post['paytype']=='支付宝' && $this->member['aliaccount']==''){
	                if($set['withdraw_aliaccount'] == 0)
	                    return $this->json(['status'=>0,'msg'=>'支付宝提现功能未开启']);
	                return $this->json(['status'=>0,'msg'=>'请先设置支付宝账号']);
	            }
	            if($post['paytype']=='银行卡' && ($this->member['bankname']==''||$this->member['bankcarduser']==''||$this->member['bankcardnum']=='')){
	                if($set['withdraw_bankcard'] == 0)
	                    return $this->json(['status'=>0,'msg'=>'银行卡提现功能未开启']);
	                return $this->json(['status'=>0,'msg'=>'请先设置完整银行卡信息']);
	            }
	            if($post['paytype'] == '微信钱包' && $set['withdraw_weixin'] == 0){
	                return $this->json(['status'=>0,'msg'=>'微信钱包提现功能未开启']);
	            }

	            $money = $post['money'];
	            if($money<=0 || $money < $set['withdrawmin']){
	                return $this->json(['status'=>0,'msg'=>'提现金额必须大于'.($set['withdrawmin']?$set['withdrawmin']:0)]);
	            }
	            if($money > $member_money){
	                return $this->json(['status'=>0,'msg'=>'可提现'.$type_name.'不足']);
	            }

	            $ordernum = date('ymdHis').aid.rand(1000,9999);
	            $record['aid'] = aid;
	            $record['mid'] = mid;
	            $record['createtime']= time();
	            $record['money'] = $money*(1-$set['withdrawfee']*0.01);
	            if($record['money'] <= 0) {
	                return $this->json(['status'=>0,'msg'=>'提现金额有误']);
	            }
	            $record['money'] = round($record['money'],2);
	            $record['txmoney'] = $money;
	            if($post['paytype']=='支付宝'){
	                $record['aliaccountname'] = $this->member['aliaccountname'];
	                $record['aliaccount'] = $this->member['aliaccount'];
	            }
	            if($post['paytype']=='银行卡'){
	                $record['bankname'] = $this->member['bankname'] . ($this->member['bankaddress'] ? ' '.$this->member['bankaddress'] : '');
	                $record['bankcarduser'] = $this->member['bankcarduser'];
	                $record['bankcardnum'] = $this->member['bankcardnum'];
	            }
	            $record['ordernum'] = $ordernum;
	            $record['paytype']  = $post['paytype'];
	            $record['platform'] = platform;
	            $record['type']     = $log_type;
	            $recordid = Db::name('member_otherwithdrawlog')->insertGetId($record);

	            \app\commons\Member::addOtherMoney(aid,mid,$type,-$money,$type_name.'提现');

	            /*$tmplcontent = array();
	            $tmplcontent['first'] = '有客户申请'.$type_name.'提现';
	            $tmplcontent['remark'] = '点击进入查看~';
	            $tmplcontent['keyword1'] = $this->member['nickname'];
	            $tmplcontent['keyword2'] = date('Y-m-d H:i');
	            $tmplcontent['keyword3'] = $money.'元';
	            $tmplcontent['keyword4'] = $post['paytype'];
	            \app\commons\Wechat::sendhttmpl(aid,0,'tmpl_withdraw',$tmplcontent,m_url('admin/finance/withdrawlog'));*/

	            /*$tmplcontent = [];
	            $tmplcontent['name3'] = $this->member['nickname'];
	            $tmplcontent['amount1'] = $money.'元';
	            $tmplcontent['date2'] = date('Y-m-d H:i');
	            $tmplcontent['thing4'] = '提现到'.$post['paytype'];
	            \app\commons\Wechat::sendhtwxtmpl(aid,0,'tmpl_withdraw',$tmplcontent,'admin/finance/withdrawlog');*/

	            if($set['withdraw_autotransfer'] && ($post['paytype'] == '微信钱包' || $post['paytype'] == '银行卡')){
	                Db::name('member_otherwithdrawlog')->where('id',$recordid)->where('type',$log_type)->update(['status' => 1]);
	                $rs = \app\commons\Wxpay::transfers(aid,mid,$record['money'],$record['ordernum'],platform,$type_name.'提现');
	                if($rs['status']==0){
	                    return json(['status'=>1,'msg'=>'提交成功,请等待打款']);
	                }else{
	                    Db::name('member_otherwithdrawlog')->where('id',$recordid)->where('type',$log_type)->update(['status' => 3]);
	                    Db::name('member_otherwithdrawlog')->where('aid',aid)->where('id',$recordid)->where('type',$log_type)->update(['status'=>3,'paytime'=>time(),'paynum'=>$rs['resp']['payment_no']]);
	                    //提现成功通知
	                    $tmplcontent = [];
	                    $tmplcontent['first'] = '您的'.$type_name.'提现申请已打款，请留意查收';
	                    $tmplcontent['remark'] = '请点击查看详情~';
	                    $tmplcontent['money'] = (string) round($record['money'],2);
	                    $tmplcontent['timet'] = date('Y-m-d H:i',$record['createtime']);
                        $tempconNew = [];
                        $tempconNew['amount2'] = (string) round($record['money'],2);//提现金额
                        $tempconNew['time3'] = date('Y-m-d H:i',$record['createtime']);//提现时间
	                    \app\commons\Wechat::sendtmpl(aid,$record['mid'],'tmpl_tixiansuccess',$tmplcontent,m_url('pages/my/usercenter'),$tempconNew);
	                    //订阅消息
	                    $tmplcontent = [];
	                    $tmplcontent['amount1'] = $record['money'];
	                    $tmplcontent['thing3'] = '微信打款';
	                    $tmplcontent['time5'] = date('Y-m-d H:i');

	                    $tmplcontentnew = [];
	                    $tmplcontentnew['amount3'] = $record['money'];
	                    $tmplcontentnew['phrase9'] = '微信打款';
	                    $tmplcontentnew['date8'] = date('Y-m-d H:i');
	                    \app\commons\Wechat::sendwxtmpl(aid,$record['mid'],'tmpl_tixiansuccess',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);
	                    //短信通知
	                    if($this->member['tel']){
	                        \app\commons\Sms::send(aid,$this->member['tel'],'tmpl_tixiansuccess',['money'=>$record['money']]);
	                    }
	                    return json(['status'=>1,'msg'=>$rs['msg']]);
	                }
	            }

	            return $this->json(['status'=>1,'msg'=>'提交成功,请等待打款']);
	        }
	        $userinfo = Db::name('member')->where('id',mid)->field($field)->find();
	        //订阅消息
	        $wx_tmplset = Db::name('wx_tmplset')->where('aid',aid)->find();
	        $tmplids = [];
	        if($wx_tmplset['tmpl_tixiansuccess_new']){
	            $tmplids[] = $wx_tmplset['tmpl_tixiansuccess_new'];
	        }elseif($wx_tmplset['tmpl_tixiansuccess']){
	            $tmplids[] = $wx_tmplset['tmpl_tixiansuccess'];
	        }
	        if($wx_tmplset['tmpl_tixianerror_new']){
	            $tmplids[] = $wx_tmplset['tmpl_tixianerror_new'];
	        }elseif($wx_tmplset['tmpl_tixianerror']){
	            $tmplids[] = $wx_tmplset['tmpl_tixianerror'];
	        }
	        $rdata = [];
	        $rdata['status']    = 1;
	        $rdata['userinfo']  = $userinfo;
	        $rdata['sysset']    = $set;
	        $rdata['tmplids']   = $tmplids;
	        $rdata['type_name'] = $type_name;

	        $rdata['canWithdraw'] = $set['othermoney_withdraw']?true:false;
	        return $this->json($rdata);
	    }
    }

    public function otherset(){
        if(getcustom('register_fields')){
            $index = input('index');
            if(!$index && $index !== '0'){
                return $this->json(['status'=>0,'msg'=>'请选择要设置的信息']);
            }
            $form_record_id = $this->member['form_record_id'];

            if(!$form_record_id){
                return $this->json(['status'=>0,'msg'=>'设置信息不存在']);
            }

            $set = Db::name('register_form')->where('aid',aid)->find();
            if(!$set){
                return $this->json(['status'=>0,'msg'=>'设置信息不存在']);
            }
            $setcontent = json_decode($set['content'],true);
            if(!$setcontent){
                return $this->json(['status'=>0,'msg'=>'设置信息不存在']);
            }
            //查询用户设置
            $log = Db::name('register_form_record')->where('aid',aid)->where('id',$form_record_id)->find();

            if(request()->isPost()){
                $content = input('content')?input('content'):'';
                if($content){
                    if(is_array($content)){
                        $content = implode(',',$content);
                    }
                }
                //查询是否已添加过
                if($log){
                    $up_data = [];
                    $up_data['form'.$index] = $content;
                    $recordid = Db::name('register_form_record')->where('id',$log['id'])->update($up_data);
                }else{
                    if(!empty($content)){
                        $logdata = [];
                        $logdata['aid'] = aid;
                        $logdata['formid'] = $set['id'];
                        $logdata['form'.$index]= $content;
                        $logdata['createtime'] = time();
                        $recordid = Db::name('register_form_record')->insertGetId($logdata);
                        $data = [];
                        $data['form_record_id'] = $recordid;
                        Db::name('member')->where('id',mid)->update($data);
                        
                    }else{
                        return $this->json(['status'=>0,'msg'=>'内容不能为空']);
                    }
                }
                if($recordid){
                    return $this->json(['status'=>1,'msg'=>'保存成功']);
                }else{
                    return $this->json(['status'=>0,'msg'=>'保存失败']);
                }
            }else{
                $detail = '';
                foreach($setcontent as $sk=>&$sv){
                    if($sk == $index){
                        $sv['content'] = '';
                        if($log){
                            $sv['content'] = $log['form'.$sk];
                        }
                        $detail = $sv;
                    }
                }
                unset($sv);
                return $this->json(['status'=>1,'detail'=>$detail]);
            }
        }
        if(getcustom('member_set')){

            $index = input('index');
            if(!$index && $index !== '0'){
                return $this->json(['status'=>0,'msg'=>'请选择要设置的信息']);
            }

            $set = Db::name('member_set')->where('aid',aid)->find();
            if(!$set){
                return $this->json(['status'=>0,'msg'=>'设置信息不存在']);
            }
            $setcontent = json_decode($set['content'],true);
            if(!$setcontent){
                return $this->json(['status'=>0,'msg'=>'设置信息不存在']);
            }
            //查询用户设置
            $log = Db::name('member_set_log')->where('mid',mid)->where('aid',aid)->where('formid',$set['id'])->find();

            if(request()->isPost()){
                $content = input('content')?input('content'):'';
                if($content){
                    if(is_array($content)){
                        $content = implode(',',$content);
                    }
                }
                //查询是否已添加过
                if($log){
                    $up_data = [];
                    $up_data['form'.$index] = $content;
                    $up = Db::name('member_set_log')->where('id',$log['id'])->update($up_data);
                }else{
                    if(!empty($content)){
                        $logdata = [];
                        $logdata['aid'] = aid;
                        $logdata['mid'] = mid;
                        $logdata['formid'] = $set['id'];
                        $logdata['form'.$index]= $content;
                        $logdata['createtime'] = time();
                        $up = Db::name('member_set_log')->insert($logdata);
                    }else{
                        return $this->json(['status'=>0,'msg'=>'内容不能为空']);
                    }
                }
                if($up){
                    return $this->json(['status'=>1,'msg'=>'保存成功']);
                }else{
                    return $this->json(['status'=>0,'msg'=>'保存失败']);
                }
            }else{
                $detail = '';
                foreach($setcontent as $sk=>&$sv){
                    if($sk == $index){
                        $sv['content'] = '';
                        // if($log){
                        //     $sv['content'] = $log['form'.$sk];
                        // }
                        $detail = $sv;
                    }
                }
                unset($sv);
                return $this->json(['status'=>1,'detail'=>$detail]);
            }
        }
    }


    public function registset()
    {
        if (getcustom('register_fields')) {

            $index = input('index');
            if (!$index && $index !== '0') {
                return $this->json(['status' => 0, 'msg' => '请选择要设置的信息']);
            }
            $member = Db::name("member")->where('id', mid)->find();
            if (!$member) {
                return $this->json(['status' => 0, 'msg' => '会员不存在']);
            }
            $set = Db::name('register_form')->where('aid', aid)->find();
            if ($member['form_record_id'] > 0) {
                $form_record = Db::name('register_form_record')->where('id', $member['form_record_id'])->where('aid', aid)->find();
            }
            if (!$set) {
                return $this->json(['status' => 0, 'msg' => '设置信息不存在']);
            }
            $setcontent = json_decode($set['content'], true);
            if (!$setcontent) {
                return $this->json(['status' => 0, 'msg' => '设置信息不存在']);
            }

            if (request()->isPost()) {
                Db::startTrans();
                try {
                    $content = input('content') ? input('content') : '';
                    if ($content) {
                        if (is_array($content)) {
                            $content = implode(',', $content);
                        }
                    }
                    $item_content = $setcontent[$index];
                    //查询是否已添加过
                    if ($member['form_record_id']) {
                        $up_data = [];
                        $up_data['form' . $index] = $content;
                        $up = Db::name('register_form_record')->where('id', $member['form_record_id'])->update($up_data);
                    } else {
                        if (!empty($content)) {
                            $logdata = [];
                            $logdata['aid'] = aid;
                            $logdata['bid'] = bid;
                            $logdata['formid'] = $set['id'];
                            $logdata['form' . $index] = $content;
                            $logdata['createtime'] = time();
                            $up = Db::name('register_form_record')->insertGetId($logdata);
                            Db::name("member")->where('id', mid)->save([
                                "form_record_id"=>$up
                            ]);
                        } else {
                            throw new Exception('内容不能为空');
                        }
                    }
                    if ($up) {
                        if($item_content['key'] == "usercard"){
                            //
                            if(!checkIdCard($content)){
                                throw new Exception('请输入正确的身份证号');
                            }
                            Db::name("member")->where('id', mid)->save([
                                "usercard"=>$content
                            ]);
                        }
                        Db::commit();
                        return $this->json(['status' => 1, 'msg' => '保存成功']);
                    } else {
                        throw new Exception('保存失败');
                    }
                }catch (\Throwable $e){
                    Db::rollback();
                    return $this->json(['status' => 0, 'msg' => $e->getMessage()]);
                }
            } else {
                $detail = '';
                foreach ($setcontent as $sk => &$sv) {
                    if ($sk == $index) {
                        $sv['content'] = '';
                        $detail = $sv;
                    }
                }
                unset($sv);
                return $this->json(['status'=>1,'detail'=>$detail]);
            }
        }
    }

	public function rechargeyjlog(){
		if(getcustom('member_recharge_yj')){
			//充值业绩记录
			$st = input('param.st');
			$pagenum = input('post.pagenum');
			if(!$pagenum) $pagenum = 1;
			$pernum = 20;
			$where = [];
			$where[] = ['aid','=',aid];
			$where[] = ['mid','=',mid];
			if($st ==1){//提现记录
				$datalist = Db::name('member_recharge_yj_withdrawlog')->field("id,rechargeyj_money,money,txmoney,`status`,from_unixtime(createtime) createtime")->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
				if(!$datalist) $datalist = [];
			}else{ 
				//业绩明细
				$datalist = Db::name('member_recharge_yj_log')->field("id,money,get_yj,remark,from_unixtime(createtime) createtime")->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
				if(!$datalist) $datalist = [];
			}
			return $this->json(['status'=>1,'data'=>$datalist]);
		}
	}

	public function rechargeyj_withdraw(){
		if(getcustom('member_recharge_yj')) {
			$set = Db::name('admin_set')
			     ->where('aid',aid)
			     ->field('rechargeyj_withdraw,rechargeyj_withdrawmin,rechargeyj_withdrawfee,withdraw_autotransfer,withdraw_weixin,withdraw_aliaccount,withdraw_bankcard')
			     ->find();
			$levelyj = Db::name('member_level')
	            ->where('id',$this->member['levelid'])
	            ->where('aid',aid)
	            ->field('id,open_yj,recharge_yj_ratio,recharge_yj_ratio,yj_datas,yj_moneys_after,yj_ratios_after')
	            ->find();
	        $userinfo = Db::name('member')->where('id',mid)->field('id,levelid,rechargeyj_money,aliaccount,bankname,bankcarduser,bankcardnum')->find();
			if(request()->isPost()){
				$post = input('post.');

				if($set['rechargeyj_withdraw'] == 0){
					return $this->json(['status'=>0,'msg'=>'业绩提现功能未开启']);
				}
				if($post['paytype']=='支付宝' && $this->member['aliaccount']==''){
					return $this->json(['status'=>0,'msg'=>'请先设置支付宝账号']);
				}
				if($post['paytype']=='银行卡' && ($this->member['bankname']==''||$this->member['bankcarduser']==''||$this->member['bankcardnum']=='')){
					return $this->json(['status'=>0,'msg'=>'请先设置完整银行卡信息']);
				}
				//$money = $post['money'];
				$money = 0;
				$ratio = 0;
				if($levelyj){
		        	if(!$levelyj['open_yj']){
		        		return $this->json(['status'=>0,'msg'=>'当前等级暂未开启业绩']);
		        	}else{
		        		if($levelyj['yj_datas']){
		        			
	        				//业绩数据
			                $yj_datas = json_decode($levelyj['yj_datas'],true);
			                if($yj_datas){
			                	$ratio = 0;
			                    $yjlist = $yj_datas['yj_data']?$yj_datas['yj_data']:[];
			                    if($yjlist){
			                    	foreach($yjlist as $yv){
			                    		if($userinfo['rechargeyj_money']<=$yv['money']){
			                    			$ratio = $yv['ratio'];
			                    			break;
			                    		}
			                    	}
			                    	unset($yv);
			                    }
			                    if($ratio == 0 && $userinfo['rechargeyj_money']>=$levelyj['yj_moneys_after'] && $levelyj['yj_ratios_after']>0){
		        					$ratio = $levelyj['yj_ratios_after'];
		        				}
			                }else{
			                	if($ratio == 0 && $userinfo['rechargeyj_money']>=$levelyj['yj_moneys_after'] && $levelyj['yj_ratios_after']>0){
		        					$ratio = $levelyj['yj_ratios_after'];
		        				}
			                }
			                if($ratio<=0){
			                	return $this->json(['status'=>0,'msg'=>'当前等级业绩转换提现金额比例为0']);
			                }
			                //计算可提现金额
			                $money = $userinfo['rechargeyj_money'] * $ratio/100;
			            }else{
			            	return $this->json(['status'=>0,'msg'=>'当前等级暂未设置业绩转换提现金额比例']);
			            }
		        	}
		        }else{
		        	$yj_tip = '当前等级不可提现';
		        	$set['rechargeyj_withdraw'] = 0;
		        }
				
				if($money<=0 || $money < $set['rechargeyj_withdrawmin']){
					return $this->json(['status'=>0,'msg'=>'转换提现金额为'.$money.'元，提现金额必须大于'.($set['rechargeyj_withdrawmin']?$set['rechargeyj_withdrawmin']:0)]);
				}

				$ordernum = date('ymdHis').aid.rand(1000,9999);
				$record['aid'] = aid;
				$record['mid'] = mid;
				$record['createtime']= time();
				$record['rechargeyj_withdrawfee'] = !empty($set['rechargeyj_withdrawfee'])?$set['rechargeyj_withdrawfee']:0;
	            $record['rechargeyj_money'] 	  = $userinfo['rechargeyj_money'];
	            $record['ratio'] 	  			  = $ratio;
				$record['money'] = $money*(1-$set['rechargeyj_withdrawfee']*0.01);
				if($record['money'] <= 0) {
	                return $this->json(['status'=>0,'msg'=>'提现金额有误']);
	            }
	            $record['money'] = round($record['money'],2);
				$record['txmoney'] = $money;
				if($post['paytype']=='支付宝'){
					$record['aliaccountname'] = $this->member['aliaccountname'];
					$record['aliaccount'] = $this->member['aliaccount'];
				}
				if($post['paytype']=='银行卡'){
					$record['bankname'] = $this->member['bankname'] . ($this->member['bankaddress'] ? ' '.$this->member['bankaddress'] : '');
					$record['bankcarduser'] = $this->member['bankcarduser'];
					$record['bankcardnum'] = $this->member['bankcardnum'];
				}
				$record['ordernum'] = $ordernum;
				$record['paytype']  = $post['paytype'];
				$record['platform'] = platform;
				$recordid = Db::name('member_recharge_yj_withdrawlog')->insertGetId($record);

				\app\customs\RechargeYj::changeyj(aid,mid,-$userinfo['rechargeyj_money'],'提现减少');

				if($set['withdraw_autotransfer'] && ($post['paytype'] == '微信钱包' || $post['paytype'] == '银行卡')){
	                Db::name('member_recharge_yj_withdrawlog')->where('id',$recordid)->update(['status' => 1]);
					$rs = \app\commons\Wxpay::transfers(aid,mid,$record['money'],$record['ordernum'],platform,t('余额宝').'收益提现');
					if($rs['status']==0){
						return json(['status'=>1,'msg'=>'提交成功,请等待打款']);
					}else{
	                    Db::name('member_recharge_yj_withdrawlog')->where('id',$recordid)->update(['status' => 3]);
						Db::name('member_recharge_yj_withdrawlog')->where('aid',aid)->where('id',$recordid)->update(['status'=>3,'paytime'=>time(),'paynum'=>$rs['resp']['payment_no']]);
						//提现成功通知
						$tmplcontent = [];
						$tmplcontent['first'] = '您的提现申请已打款，请留意查收';
						$tmplcontent['remark'] = '请点击查看详情~';
						$tmplcontent['money'] = (string) round($record['money'],2);
						$tmplcontent['timet'] = date('Y-m-d H:i',$record['createtime']);
                        $tempconNew = [];
                        $tempconNew['amount2'] = (string) round($record['money'],2);//提现金额
                        $tempconNew['time3'] = date('Y-m-d H:i',$record['createtime']);//提现时间
						\app\commons\Wechat::sendtmpl(aid,$record['mid'],'tmpl_tixiansuccess',$tmplcontent,m_url('pages/my/usercenter'),$tempconNew);
						//订阅消息
						$tmplcontent = [];
						$tmplcontent['amount1'] = $record['money'];
						$tmplcontent['thing3'] = '微信打款';
						$tmplcontent['time5'] = date('Y-m-d H:i');
						
						$tmplcontentnew = [];
						$tmplcontentnew['amount3'] = $record['money'];
						$tmplcontentnew['phrase9'] = '微信打款';
						$tmplcontentnew['date8'] = date('Y-m-d H:i');
						\app\commons\Wechat::sendwxtmpl(aid,$record['mid'],'tmpl_tixiansuccess',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);
						//短信通知
						if($this->member['tel']){
							\app\commons\Sms::send(aid,$this->member['tel'],'tmpl_tixiansuccess',['money'=>$record['money']]);
						}
						return json(['status'=>1,'msg'=>$rs['msg']]);
					}
				}

				return $this->json(['status'=>1,'msg'=>'提交成功,请等待打款']);
			}
			$yj_tip = '';
	        if($levelyj){
	        	if(!$levelyj['open_yj']){
	        		$yj_tip = '当前等级暂未开启业绩';
	        		$set['rechargeyj_withdraw'] = 0;
	        	}else{
	        		$yj_tip = '当前等级暂未设置业绩转换提现金额比例';
	        		if($levelyj['yj_datas']){
        				//业绩数据
		                $yj_datas = json_decode($levelyj['yj_datas'],true);
		                if($yj_datas){
		                	$ratio = 0;
		                    $yjlist = $yj_datas['yj_data']?$yj_datas['yj_data']:[];
		                    if($yjlist){
		                    	foreach($yjlist as $yv){
		                    		if($userinfo['rechargeyj_money']<=$yv['money']){
		                    			$ratio = $yv['ratio'];
		                    			break;
		                    		}
		                    	}
		                    	unset($yv);
		                    	$yj_tip = '当前等级业绩可转换提现金额比例为'.$ratio.'%';
		                    }
		                    if($ratio == 0 && $userinfo['rechargeyj_money']>=$levelyj['yj_moneys_after'] && $levelyj['yj_ratios_after']>0){
	        					$yj_tip = '当前等级业绩可转换提现金额比例为'.$levelyj['yj_ratios_after'].'%';
	        				}
		                }else{
		                	if($userinfo['rechargeyj_money']>=$levelyj['yj_moneys_after'] && $levelyj['yj_ratios_after']>0){
	        					$yj_tip = '当前等级业绩可转换提现金额比例为'.$levelyj['yj_ratios_after'].'%';
	        				}
		                }
	        			
		            }
	        	}
	        }else{
	        	$yj_tip = '当前等级不可提现';
	        	$set['rechargeyj_withdraw'] = 0;
	        }
			$rdata = [];
			$rdata['status'] = 1;
			$rdata['userinfo'] = $userinfo;
			$rdata['sysset']   = $set;

			$rdata['yj_tip']   = $yj_tip;
			//订阅消息
			$wx_tmplset = Db::name('wx_tmplset')->where('aid',aid)->find();
			$tmplids = [];
			if($wx_tmplset['tmpl_tixiansuccess_new']){
				$tmplids[] = $wx_tmplset['tmpl_tixiansuccess_new'];
			}elseif($wx_tmplset['tmpl_tixiansuccess']){
				$tmplids[] = $wx_tmplset['tmpl_tixiansuccess'];
			}
			if($wx_tmplset['tmpl_tixianerror_new']){
				$tmplids[] = $wx_tmplset['tmpl_tixianerror_new'];
			}elseif($wx_tmplset['tmpl_tixianerror']){
				$tmplids[] = $wx_tmplset['tmpl_tixianerror'];
			}
			$rdata['tmplids']  = $tmplids;
			
			return $this->json($rdata);
		}
		
	}

	public function getRechargeyj(){
		if(getcustom('member_recharge_yj')) {
		    $field = 'id,headimg,nickname,pid,levelid,rechargeyj_money';
			$userinfo = Db::name('member')->field($field)->where('aid',aid)->where('id',mid)->find();

			$txmoney = 0 + Db::name('member_commission_withdrawlog')->where('aid',aid)->where('mid',mid)->where('status',3)->sum('txmoney');

			if($userinfo['pid']){
				$userinfo['pnickname'] = Db::name('member')->where('aid',aid)->where('id',$userinfo['pid'])->value('nickname');
			}
			$set = Db::name('admin_set')->where('aid',aid)->field('parent_show,rechargeyj_withdraw')->find();
	        $rsset = [];
	        $rsset['parent_show'] = $set['parent_show'];
			$rdata = [];
			$rdata['txmoney']     = $txmoney;
			$rdata['rechargeyj_withdraw'] = $set['rechargeyj_withdraw'];
	        $rdata['set'] 		  = $rsset;
			$rdata['userinfo']    = $userinfo;
			return $this->json($rdata);
		}
	}

    public function getMemberBase()
    {
        $mid = input('param.mid/d');
        $tel = input('param.tel');
        $where = [];
        $where[] = ['aid','=',aid];
        if($mid){
            $where[] = ['id','=',$mid];
        }
        if($tel){
            $where[] = ['tel','=',$tel];
        }
        if(!$mid && !$tel){
            return $this->json(['status'=>0,'msg'=>'参数错误']);
        }
        $member = Db::name('member')->where($where)->find();
        if($member){
            return $this->json(['status'=>1,'data'=>['nickname'=>$member['nickname'],'id'=>$member['id'],'headimg'=>$member['headimg']]]);
        }else{
            return $this->json(['status'=>0,'msg'=>'无数据']);
        }

    }

    public function getMemberCode()
    {
        if(getcustom('member_code')){
            $showmeberinfo = false;
            $tablist = [];
            $set = Db::name('member_code_set')->where('aid',aid)->find();
            if($set['status'] != 1) return $this->json(['status'=>0,'msg'=>'功能未开启']);
            if(!isset($set['code_type'])) $set['code_type'] = 0;//兼容之前没有type的情况
            $field = 'id,headimg,nickname,pid,levelid,member_code,member_code_img,tel';
            if(getcustom('member_code_paycode')){
                $field.= ',member_barcode_img,money,score';
            }
            if(getcustom('member_overdraft_money')){
                $field.= ',overdraft_money';
            }
            $userinfo = Db::name('member')->field($field)->where('aid',aid)->where('id',mid)->find();
            if(empty($userinfo['member_code']))
            {
                $membercode = \app\commons\Member::createMemberCode(aid,mid);
                $userinfo['member_code'] = $membercode['member_code'];
                $userinfo['member_code_img'] = $membercode['member_code_img'];
            }
            if($userinfo['tel']){
                $userinfo['tel'] = hidePhoneNumber($userinfo['tel']);
            }
            if(getcustom('member_code_paycode')){
                $showmeberinfo = true;
                //开启条形码
                if(($set['code_type'] == 1 || $set['code_type'] == 2)  && empty($userinfo['member_barcode_img']) && $userinfo['member_code']){
                    $member_barcode_img = createbarcode($userinfo['member_code'],'',aid);
                    if($member_barcode_img){
                        $userinfo['member_barcode_img'] = $member_barcode_img;
                        Db::name('member')->where('aid',aid)->where('id',mid)->update(['member_barcode_img'=>$member_barcode_img]);
                    }
                }
                $couponcount = Db::name('coupon_record')->where('aid',aid)->where('mid',$this->mid)->where('status',0)->where('endtime','>=',time())->count();
                $tablist[] = ['name'=>t('优惠券'),'value'=>$couponcount,'tag'=>'','path'=>'/pagesExt/coupon/mycoupon'];
                $tablist[] = ['name'=>t('余额'),'value'=>$userinfo['money'],'tag'=>'￥','path'=>'/pagesExt/money/recharge'];
                $tablist[] = ['name'=>t('积分'),'value'=>round($userinfo['score'],$this->score_weishu),'tag'=>'','path'=>'/pagesExt/my/scorelog'];
            }
            if(getcustom('member_overdraft_money')){
                $tablist[] = ['name'=>t('信用额度'),'value'=>$userinfo['overdraft_money'],'tag'=>'','path'=>'/pagesA/overdraft/detail'];
            }

            $rdata['set'] 		  = $set;
            $rdata['userinfo']    = $userinfo;
            $rdata['tablist']    = $tablist;
            $rdata['showmeberinfo']    = $showmeberinfo;
            return $this->json($rdata);
        }
    }
    public function wxOfflinePayView(){
        $data = [];
        $appinfo = \app\commons\System::appinfo(aid,platform);
        $data['appId'] = $appinfo['appid'];
        $data['timeStamp'] = time();
        $data['nonceStr'] = random(8);
        $data['package'] = 'package:mch_id='.$appinfo['wxpay_mchid'];
        $data['signType'] = 'MD5';
        ksort($data, SORT_STRING);
        $string1 = '';
        foreach ($data as $key => $v){
            if (empty($v)) {
                continue;
            }
            $string1 .= "{$key}={$v}&";
        }
        $mchkey = $appinfo['wxpay_mchkey'];
        $string1 .= "key=".$mchkey;
        $data['paySign'] =  strtoupper(md5($string1));
        return $this->json(['status'=>1,'data'=>$data]);
    }

    //获取升级协议
    public function getUpAgree(){
        //升级协议
        $uplv_agree = 0;
        $agree_content = '';
        if(getcustom('up_level_agree')){
            $map_a = [];
            $map_a[] = ['mid','=',mid];
            $map_a[] = ['status','=',0];
            $exit = Db::name('member_level_agree')->where($map_a)->order('sort desc')->find();
            if($exit){
                $uplv_agree = 1;
                $agree_content = Db::name('member_level')->where('id',$exit['newlv_id'])->value('agree_content');
            }
        }
        return $this->json(['status'=>1,'data'=>['uplv_agree'=>$uplv_agree,'agree_content'=>$agree_content]]);
    }
    //同意升级
    public function agreeUplv(){
        if(getcustom('up_level_agree')){
            Db::startTrans();
            //执行升级
            $mid = mid;
            $map_a = [];
            $map_a[] = ['mid','=',$mid];
            $map_a[] = ['status','=',0];
            $exit = Db::name('member_level_agree')->where($map_a)->order('sort desc')->find();
            $newlv = Db::name('member_level')->where('id',$exit['newlv_id'])->find();
            $member = Db::name('member')->where('id',$mid)->find();
            \app\commons\Member::handleUpLevel($exit['aid'],$exit['mid'],$newlv,$member,$member,$exit['cid']);

            //更新记录状态
            $map = [];
            $map[] = ['mid','=',$mid];
            $map[] = ['sort','<=',$exit['sort']];
            Db::name('member_level_agree')->where($map)->update(['status'=>1]);
            Db::commit();
            return $this->json(['status'=>1,'msg'=>'']);
        }
    }

    //汇付天下
	public function getAdapay(){
        if(getcustom('pay_adapay')){
            $adapay = Db::name('adapay_member')->where('aid',aid)->where('mid',mid)->find();
            $smscode_show =0;
            if(empty($adapay)){
                $adapay['bankcardnum'] =  $this->member['bankcardnum'];
                $adapay['bankname'] =  $this->member['bankname'];
            }else{
                if( $adapay['card_id'] && !$adapay['token_no']){
                    $smscode_show =1;
                }
            }
            return json(['status' => 1,'data'=>$adapay??[],'smscode_show' => $smscode_show]);
        }
    }
    //设置汇付天下银行卡
    public function setAdapay(){
        if(getcustom('pay_adapay')){
            $bankname = input('param.bankname');
            $bankcardnum = input('param.bankcardnum');
            $tel_no = input('param.tel_no');
            $realname = input('param.realname');
            $idcard = input('param.idcard');
            if(!$bankcardnum){
                return json(['status' => 0,'msg' => '请输入银行卡号']);
            }
            if(!$tel_no || !checkTel($tel_no)){
                return json(['status' => 0,'msg' => '请检查手机号格式']);
            }
            $member_id = aid.'_'.mid;
            //查询adapay下 是否存在用户，不存在创建，并且判断用户记录中是否存在不存在创建
            $query_res = \app\customs\AdapayPay::queryMember(aid,'h5',$member_id);
            if($query_res['status'] ==0){
                $rs = \app\customs\AdapayPay::createRealnameMember(aid,'h5',$member_id,$realname,$tel_no,$idcard);
                if(!$rs['data']){
                    return json($rs['msg']);
                }
            }
            $adapay= Db::name('adapay_member')->where('member_id',$member_id)->find();
            if(!$adapay){
                $card_rs = \app\customs\AdapayPay::createSettleAccount(aid,'h5',$member_id,$bankcardnum,
                    $realname,$tel_no,$idcard,$bankname);
                if($card_rs['status'] ==1){
                    $insert = [
                        'aid' => aid,
                        'mid' => mid,
                        'member_id' => $member_id,
                        'appid' => $card_rs['data']['app_id'],
                        'realname' => $realname,
                        'idcard' => $idcard,
                        'createtime' => time(),
                        'bank_name' => $bankname,
                        'card_id' => $bankcardnum,
                        'tel_no' => $tel_no,
                        'settle_account_id' =>$card_rs['data']['id'],
                        'account_info' =>json_encode($card_rs['data']['account_info'],JSON_UNESCAPED_UNICODE),
                        'apply_id' => $card_rs['data']['id'],
                    ];
                    $res = Db::name('adapay_member')->insert($insert);
                    if($res){
                        return  json(['status' => 1,'msg' => '设置成功','smscode_show' =>1]);
                    }else{
                        return  json(['status' => 0,'msg' => '设置失败']);
                    }
                }else{
                    return json($card_rs);
                }
            }else{
                if($adapay['idcard'] && $idcard !=$adapay['idcard']){
                    return  json(['status' => 0,'msg' => '与原创建结算账户使用的身份证不同']);
                }
                if( $realname!=$adapay['realname'] && $adapay['realname']){
                    return  json(['status' => 0,'msg' => '与原创建结算账户使用的银行卡户名不同']);
                }
                //如果新银行卡号和旧银行卡号不一样 再进行删除 重新创建，否则就直接是成功
                if($adapay['idcard'] !=$idcard || !$adapay['settle_account_id']){
                    if($adapay['settle_account_id'] ){
                        //先查询是否存在，再进行删除 再添加
                        $settledata = \app\customs\AdapayPay::querySettleAccount(aid,'h5',$adapay['apply_id'],$member_id,$adapay['settle_account_id']);
                        if($settledata){
                            $delres = \app\customs\AdapayPay::deleteSettleAccount(aid,'h5',$adapay['apply_id'],$adapay['member_id'],$adapay['settle_account_id']);
                            if($delres['status'] ==0){
                                return json($delres['msg']);
                            } 
                        }
                    }
                    $card_rs = \app\customs\AdapayPay::createSettleAccount(aid,'h5',$member_id,$bankcardnum, $realname,$tel_no,$idcard,$bankname);
                    print_r($card_rs);
                    if($card_rs['status'] ==1){
                        $update = [
                            'card_id' => $bankcardnum,
                            'settle_account_id' =>$card_rs['data']['id'],
                            'account_info' =>json_encode($card_rs['data']['account_info'],JSON_UNESCAPED_UNICODE),
                        ];
                        $res = Db::name('adapay_member')->where('id',$adapay['id'])->update($update);
                        print_r($res);
                        return  json(['status' => 1,'msg' => '设置成功']);
                    } else{
                        return  json($card_rs['msg']);
                    }
                } else{
                    return  json(['status' => 1,'msg' => '设置成功']);
                }
   
            }
           
        }
    }

    public function xiaofeimoneylog(){
	    if(getcustom('commission_xiaofei')){
            $pagenum = input('post.pagenum');
            $st = input('post.st');
            if(!$pagenum) $pagenum = 1;
            $pernum = 20;
            $where = [];
            $where[] = ['aid','=',aid];
            $where[] = ['mid','=',mid];
            $datalist = Db::name('member_xiaofei_money_log')->field('id,commission,after,remark,from_unixtime(createtime)createtime')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
            if(!$datalist) $datalist = [];
            if(request()->isPost()){

                return $this->json(['status'=>1,'data'=>$datalist,'myxiaofei'=>$this->member['xiaofei_money']] );
            }

            $count = Db::name('member_xiaofei_money_log')->where($where)->count();

            $rdata = [];
            $rdata['count'] = $count;
            $rdata['datalist'] = $datalist;
            $rdata['pernum'] = $pernum;
            $rdata['st'] = $st;
            $rdata['myxiaofei'] = $this->member['xiaofei_money'];
            return $this->json($rdata);
        }

    }

    public function invitecashbacklog(){
        if(getcustom('yx_invite_cashback')){
            if(request()->isPost()){
                $pagenum = input('post.pagenum');
                $st      = input('post.st');
                $pernum = 15;
                if(!$pagenum) $pagenum = 1;
                if($pagenum == 1){
                    cache(mid.'pagenum2',null);
                }

                $where = [];
                $where[] = ['aid','=',aid];
                $where[] = ['mid','=',mid];
                //$where[] = ['status','>=',0];
                $datalist = Db::name('member_invite_cashback_log')->where($where)->page($pagenum,$pernum)->order('update_time desc')->select()->toArray();

                if(!$datalist){
                    $datalist = [];
                }
                if($datalist){
                    foreach($datalist as &$dv){
                        $dv['tipinfor'] = '';
                        if($dv['status'] == 0){
                            $dv['tipinfor'] = '此为预估返现，具体发放金额以下级确认收货时间为先后顺序，重新计算返现金额';
                        }
                        //查询商品
                        $proname = Db::name('shop_product')->where('id',$dv['proid'])->value('name');
                        $dv['proname'] = !empty($proname)?$proname:'';
                        $dv['create_time'] = date("Y-m-d H:i:s",$dv['update_time']);
                        if($dv['status'] == 0){
                            //重新计算他的预估金额
                            $count_sendback = \app\customs\OrderCustom::count_sendback(aid,$dv);
                            $dv['allmoney']      = $count_sendback['allmoney'];
                            $dv['allscore']      = $count_sendback['allscore'];
                            $dv['allcommission'] = $count_sendback['allcommission'];
                            $dv['status']        = $count_sendback['status'];
                        }else if($dv['status'] == -1){
                            $dv['allmoney']      = 0;
                            $dv['allscore']      = 0;
                            $dv['allcommission'] = 0;
                            $dv['tipinfor']      = $dv['reason'];
                        }
                    }
                }
                return $this->json(['status'=>1,'data'=>$datalist]);
            }
        }
    }

    public function getRealnameSet()
    {
        if(getcustom('member_realname_verify')){
            $set = Db::name('member_realname_set')->field('status,idno_area_range')->where('aid',aid)->find();

            $field = 'id,headimg,nickname,pid,levelid,realname,usercard,realname_status';
            $userinfo = Db::name('member')->field($field)->where('aid',aid)->where('id',mid)->find();
            if($userinfo['realname']){
                $userinfo['realname'] = mb_substr($userinfo['realname'],0,1).'**';
            }
            if($userinfo['usercard']){
                $userinfo['usercard'] = substr($userinfo['usercard'],0,4).'********'.substr($userinfo['usercard'],-4);
            }

            $rdata['set'] 		  = $set ? $set : ['status'=>0];
            $rdata['userinfo']    = $userinfo;
            return $this->json($rdata);
        }
        return $this->json(['set'=>['status'=>0],'userinfo'=>[]]);
    }

    public function saveRealname()
    {
        if(getcustom('member_realname_verify')){
            $set = Db::name('member_realname_set')->where('aid',aid)->find();
            if($set['status'] != 1) return $this->json(['status'=>0,'msg'=>'功能未开启']);

            $field = 'id,headimg,nickname,pid,levelid,realname,usercard,realname_status';
            $userinfo = Db::name('member')->field($field)->where('aid',aid)->where('id',mid)->find();
            if($userinfo['realname_status']) return $this->json(['status'=>0,'msg'=>'认证通过不可修改']);

            $post = input('post.info');
            if(empty($post['idcard'])){
                return $this->json(['status'=>0,'msg'=>'请上传身份证头像面']);
            }
            if(empty($post['idcard_back'])){
                return $this->json(['status'=>0,'msg'=>'请上传身份证国徽面']);
            }
            //腾讯云识别
            $rs = \app\customs\OCR\Tencent::IDCard($set['tencent_secret_id'],$set['tencent_secret_key'],['ImageUrl'=>$post['idcard']]);
            Log::write($rs);
            if($rs['status'] != 1){
                return $this->json($rs);
            }
            $AdvancedInfo = json_decode($rs['info']['AdvancedInfo'],true);
            if(!empty($AdvancedInfo)){
                return $this->json(['status'=>0,'msg'=>'身份证照片识别失败，请勿翻拍、使用复印件、遮挡、反光、边框不完整，保持证件在有效期内']);
            }
            $rs2 = \app\customs\OCR\Tencent::IDCard($set['tencent_secret_id'],$set['tencent_secret_key'],['ImageUrl'=>$post['idcard_back']]);
            Log::write($rs2);
            if($rs2['status'] != 1){
                return $this->json($rs2);
            }
            $AdvancedInfo = json_decode($rs2['info']['AdvancedInfo'],true);
            if(!empty($AdvancedInfo)){
                return $this->json(['status'=>0,'msg'=>'身份证照片识别失败，请勿翻拍、使用复印件、遮挡、反光、边框不完整，保持证件在有效期内']);
            }
            $idno = $rs['info']['IdNum'];
            //校验
            if($set['idno_area_range']){
                $area_arr = explode(',',$set['idno_area_range']);
                $idno_area = substr($idno,0,6);
                if(!in_array($idno_area,$area_arr)){
                    return $this->json(['status'=>0,'msg'=>'身份证超出区域限制，认证失败']);
                }
            }
            //绑定数量bind_member_num
            if($set['bind_member_num'] > 0){
                $count = Db::name('member')->where('aid',aid)->where('id','<>',mid)->where('usercard',$rs['info']['IdNum'])->count();
                if($count >= $set['bind_member_num']){
                    return $this->json(['status'=>0,'msg'=>'该身份证已被其他人绑定，认证失败']);
                }
            }

            //保存
            $log = [
                'aid'=>aid,
                'mid'=>mid,
                'name'=>$rs['info']['Name'],
                'sex'=>$rs['info']['Sex'],
                'nation'=>$rs['info']['Nation'],
                'birth'=>$rs['info']['Birth'],
                'address'=>$rs['info']['Address'],
                'id_num'=>$rs['info']['IdNum'],
                'authority'=>$rs['info']['Authority'],
                'valid_date'=>$rs['info']['ValidDate'],
                'idcard'=>$post['idcard'],
                'idcard_back'=>$post['idcard_back'],
            ];
            Db::name('member_realname_log')->insert($log);

            $udpate = [
                'realname'=>$rs['info']['Name'],
                'usercard'=>$idno,
                'realname_status'=>1,
                'sex'=>$rs['info']['Sex'] == '男' ? 1 : 2,
//                'birthday'=>$rs['info']['Birth'],
//                'Address'=>$rs['info']['Address']
            ];
            Db::name('member')->where('aid',aid)->where('id',mid)->update($udpate);

            //后置-奖励
            if($userinfo['pid']){
                $field = 'id,headimg,nickname,pid,levelid,realname,usercard,realname_status';
                $parent = Db::name('member')->field($field)->where('aid',aid)->where('id',$userinfo['pid'])->find();
                $plevel = Db::name('member')->where('aid',aid)->where('id',$parent['levelid'])->find();
                if($plevel['can_agent'] > 0 && $plevel['realname_commission1'] > 0) {
                    \app\commons\Member::addcommission(aid,$parent['id'],$plevel['realname_commission1'],'下级实名认证奖励');
                }
            }

            return $this->json(['status'=>1,'msg'=>'认证成功']);
        }
    }

	/**
	 * 小额同银行的打款要推送信息到平台签约获取签约地址
	 */
	public function saveRealnameCard()
    {
		if(getcustom('transfer_farsion')){
			$field = 'id,headimg,nickname,pid,levelid,realname,usercard,tel';
            $userinfo = Db::name('member')->field($field)->where('aid',aid)->where('id',mid)->find();
            //if($userinfo['xiaoetong_signing_status'] == 1) return $this->json(['status'=>0,'msg'=>'认证已通过']);
			$xetService = new  \app\commons\Xiaoetong();
			$post = input('post.');
			if(empty($post['realname']) || empty($post['usercard']) || empty($post['tel'])){
				return $this->json(['status'=>0,'msg'=>'请填写完整信息','data'=>[]]);
			}
			$udpate = [
                'realname'=>$post['realname'],
                'usercard'=>$post['usercard'],
                'tel'=>$post['tel']
            ];
            Db::name('member')->where('aid',aid)->where('id',mid)->update($udpate);
			$rs = Db::name('transfer_farsion_set')->where('aid',aid)->where('bid',bid)->find();
			$url = trim($rs['domain_url'],'/').'/front/mobile/#/sign?appid='.$rs['appid'].'&idcard='.$post['usercard'];
			$apidata = [
				'params'=>[[
					'name' => $post['realname'],
					'idcard' => $post['usercard'],
					'phone' => $post['tel'],
					'third_id' => $userinfo['id']
					]
				]
			];
			//查询是否导入
			$personList = $xetService->getPerson([
			   'idcard' => $post['usercard']
			]);
			$res = $xetService->getdata($personList);
			if($res['code'] == 0){
				//是否签约成功	
				if($res['data'][0]['real_status'] == 0 || empty($res['data']['sign'])){
					return $this->json(['status'=>1,'msg'=>'操作成功','data'=>['url'=>$url]]);
				}
			}
			//导入数据
			$personList = $xetService->importPerson($apidata);
			$res = $xetService->getdata($personList);
			if($res['code'] == 0 || $res['code'] == 1005){
				//继续调用签约				
				return $this->json(['status'=>1,'msg'=>'操作成功','data'=>['url'=>$url]]);
			}else{
				return $this->json(['status'=>1,'msg'=>'操作失败'.$res['code'],'data'=>['url'=>$url]]);
			}
		}

	}
	/**
	 * 小额通是否已签约签约获取签约地址
	 */
	public function getXiaoetongSigning()
    {
		if(getcustom('transfer_farsion')){
			$xetService = new  \app\commons\Xiaoetong();
			$res = $xetService->getXiaoetongSigning();
			return $this->json($res);
		}

	}

    //分红份数
    public function fhcopieslog(){
        if(getcustom('fenhong_jiaquan_bylevel')) {
            $pagenum = input('param.pagenum');
            if (!$pagenum) $pagenum = 1;
            $mycopies = 0;
            if($pagenum==1){
                $mycopies = Db::name('member')->where('aid',aid)->where('id',$this->mid)->value('fhcopies');
            }
            $pernum = 20;
            $where = [];
            $where[] = ['aid', '=', aid];
            $where[] = ['mid', '=', mid];
            $datalist = Db::name('member_fhcopies_log')->field('id,copies,after,remark,from_unixtime(createtime)createtime')->where($where)->page($pagenum, $pernum)->order('id desc')->select()->toArray();
            if (!$datalist) $datalist = [];
            $rdata = [];
            $rdata['datalist'] = $datalist;
            $rdata['mycopies'] = $mycopies;
            return $this->json($rdata);
        }
        return $this->json(['status'=>1,'datalist'=>[]]);
    }

	public function getmemberinfo(){
		$member = Db::name('member')->field('id,nickname,headimg,tel')->where('id',mid)->find();
		return $this->json(['status'=>1,'data'=>$member]);
	}

    public function tongzhenglog(){
	    if(getcustom('product_givetongzheng')){
            $pagenum = input('post.pagenum');
            $st = input('post.st');
            if(!$pagenum) $pagenum = 1;
            $pernum = 20;
            $where = [];
            $where[] = ['aid','=',aid];
            $where[] = ['mid','=',mid];
            $datalist = Db::name('member_tongzhenglog')->field('id,money,after,remark,from_unixtime(createtime)createtime')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
            if(!$datalist) $datalist = [];
            if($datalist){
                foreach($datalist as $k=>$v){
                    //$datalist[$k]['tonzgheng'] = dd_money_format($v['score'],$score_weishu);
                }
            }
            $member_tongzheng = $this->member['tongzheng'];
            $release_total = Db::name('tongzheng_order_log')->where('mid',mid)->sum('remain');
            $tongzheng_transfer = 0;

            if($pagenum == 1) {
                $set = Db::name('admin_set')->where('aid', aid)->find();
                $tongzheng_transfer = $set['tongzheng_transfer'] ? true : false;
            }
            if(request()->isPost()){

                return $this->json(['status'=>1,'data'=>$datalist,'mytongzheng'=>$member_tongzheng,'release_total'=>$release_total,'tongzheng_transfer'=>$tongzheng_transfer] );
            }
            $count = Db::name('member_tongzhenglog')->where($where)->count();
            $rdata = [];
            $rdata['count'] = $count;
            $rdata['datalist'] = $datalist;
            $rdata['pernum'] = $pernum;
            $rdata['st'] = $st;
            $rdata['mytongzheng'] = $member_tongzheng;
            $rdata['release_total'] = $release_total;
            return $this->json($rdata);
        }

    }
    public function tongzheng_releaselog(){
        if(getcustom('product_givetongzheng')){
            $pagenum = input('post.pagenum');
            $st = input('post.st');
            $pid = input('pid');
            if(!$pagenum) $pagenum = 1;
            $pernum = 20;
            $where = [];
            $where[] = ['aid','=',aid];
            $where[] = ['mid','=',mid];
            if($pid){
                $where[] = ['pid','=',$pid];
            }
            $datalist = Db::name('tongzheng_release_log')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
            if(!$datalist) $datalist = [];
            if($datalist){
                foreach($datalist as $k=>$v){
                    $datalist[$k]['createtime'] = date('Y-m-d H:i:s',$v['createtime']);
                }
            }
            if(request()->isPost()){
                return $this->json(['status'=>1,'data'=>$datalist] );
            }
            $count = Db::name('tongzheng_release_log')->where($where)->count();
            $rdata = [];
            $rdata['count'] = $count;
            $rdata['datalist'] = $datalist;
            $rdata['pernum'] = $pernum;
            $rdata['st'] = $st;
            return $this->json($rdata);
        }
    }
    public function tongzheng_transfer()
    {
        if(getcustom('product_givetongzheng')) {
            $mid = input('param.mid/d',0);
            $set = Db::name('admin_set')->where('aid',aid)->find();
            if($set['tongzheng_transfer'] != 1) {
                return $this->json(['status'=>0,'msg'=>'未开启此功能']);
            }

            if(request()->isPost()){
                $mobile = input('post.mobile');
                $mid = input('post.mid/d');
                $money = input('post.money/f');
                if ($money < 0.001){
                    return $this->json(['status'=>0,'msg'=>'请输入正确的金额，最小金额为：0.001']);
                }
                if (input('?post.mobile')) {
                    $member = Db::name('member')->where('aid', aid)->where('tel', $mobile)->find();
                }
                if (input('?post.mid')) {
                    $member = Db::name('member')->where('aid', aid)->where('id', $mid)->find();
                }
                if(!$member) return $this->json(['status'=>0,'msg'=>'未找到该'.t('会员')]);
                $user_id = $member['id'];

                if ($user_id == mid) {
                    return $this->json(['status'=>0,'msg'=>'不能转账给自己']);
                }

                if ($money > $this->member['tongzheng']){
                    return $this->json(['status'=>0,'msg'=>'您的'.t('通证').'不足']);
                }
                //验证支付密码
                $pwd_check = $set['tongzheng_transfer_pwd'];
                if($pwd_check){
                    if(!$this->member['paypwd']){
                        return $this->json(['status'=>0,'msg'=>'请先设置支付密码','set_paypwd'=>1]);
                    }
                    $pay_pwd = input('paypwd')?:'';
                    if(!\app\commons\Member::checkPayPwd($this->member,$pay_pwd )){
                        return $this->json(['status'=>0,'msg'=>'支付密码输入错误']);
                    }
                }
                $midMsg = sprintf("转账给：%s",$member['nickname']);
                $toMidMsg = sprintf("来自%s的转账", $this->member["nickname"]);

                $rs = \app\commons\Member::addtongzheng(aid,mid,$money * -1, $midMsg);
                if ($rs['status'] == 1) {
                    \app\commons\Member::addtongzheng(aid,$user_id,$money,$toMidMsg,$this->mid);
                }else{
                    return $this->json(['status'=>0, 'msg' => '转账失败']);
                }
                return $this->json(['status'=>1, 'msg' => '转账成功', 'url'=>'/pages/my/usercenter']);
            }
            $tomember = [];
            if($mid){
                $tomember = Db::name('member')->where('aid',aid)->where('id',$mid)->field('id,money,nickname,headimg')->find();
            }
            $rdata['paycheck'] = $set['tongzheng_transfer_pwd'] ? true : false;
            $rdata['status'] = 1;
            $rdata['mytongzheng'] = $this->member['tongzheng'];
            $rdata['tomember'] = $tomember?$tomember:['nickname'=>''];//转给谁
            return $this->json($rdata);
        }
    }
    public function tongzheng_orderlog(){
        if(getcustom('product_givetongzheng')){
            $pagenum = input('post.pagenum');
            $st = input('post.st');
            if(!$pagenum) $pagenum = 1;
            $pernum = 20;
            $where = [];
            $where[] = ['aid','=',aid];
            $where[] = ['mid','=',mid];
            $datalist = Db::name('tongzheng_order_log')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
            if(!$datalist) $datalist = [];
            if($datalist){
                $status_arr = [0=>'释放中',1=>'释放完成',2=>'订单删除'];
                foreach($datalist as $k=>$v){
                    $datalist[$k]['createtime'] = date('Y-m-d H:i:s',$v['createtime']);
                    $datalist[$k]['status_str'] = $status_arr[$v['status']];
                }
            }
            if(request()->isPost()){
                return $this->json(['status'=>1,'data'=>$datalist] );
            }
            $count = Db::name('tongzheng_order_log')->where($where)->count();
            $rdata = [];
            $rdata['count'] = $count;
            $rdata['datalist'] = $datalist;
            $rdata['pernum'] = $pernum;
            $rdata['st'] = $st;
            return $this->json($rdata);
        }
    }

	public function commission_withdraw_scorelog(){
		if(getcustom('commission_duipeng_score_withdraw')){		
			$pagenum = input('post.pagenum');
			if(!$pagenum) $pagenum = 1;
			$pernum = 20;
			$where = [];
			$where[] = ['aid','=',aid];
			$where[] = ['mid','=',mid];
			$datalist = Db::name('member_commission_withdraw_scorelog')->field('id,score,after,remark,from_unixtime(createtime)createtime')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
			if(!$datalist) $datalist = [];

			$count = Db::name('member_commission_withdraw_scorelog')->where($where)->count();

			$rdata = [];
			$rdata['count'] = $count;
			$rdata['datalist'] = $datalist;
			$rdata['pernum'] = $pernum;
			$rdata['myscore'] = $this->member['commission_withdraw_score'];
			return $this->json($rdata);
		}
	}
	//消费通知开关
	public function  setFinanceNoticeSwitch(){
	    if(getcustom('restaurant_finance_notice_switch')){
            $field = input('param.field');//tmpl 公众号通知   sms短信通知
            $value = input('param.value');
            if($field =='tmpl' || $field =='sms' ){
                $update = ['is_receive_finance_'.$field => $value];
                Db::name('member')->where('aid',aid)->where('id',mid)->update($update);
            }
            return $this->json(['status' =>1,'msg' =>'']);
        }
    }

    public function crk_stcoklog(){
        if(getcustom('ciruikang_fenxiao')){
            if(request()->isPost()){
                //统计购买自己及下级购买的商品数量
                $pagenum = input('post.pagenum');
                $st = input('post.st');
                if(!$pagenum) $pagenum = 1;
                $pernum = 20;
                $where = [];
                $where[] = ['m.pid','=',mid];
                $where[] = ['m.aid','=',aid];
                $datalist = Db::name('member')->alias('m')->join('member_product_stock ps','ps.mid = m.id')
                    ->where($where)->group('m.id')
                    ->page($pagenum,$pernum)->order('allnum desc')
                    ->field('m.id,m.headimg,m.nickname,sum(ps.num) as allnum')
                    ->select()->toArray();
                if(!$datalist) $datalist = [];
                //统计自己购买的鞋数
                $allnum = Db::name('member_product_stock')->where('mid',mid)->where('aid',aid)->sum('num');
                return $this->json(['status'=>1,'data'=>$datalist,'mynum'=>$allnum??0] );
            }
        }
    }

    public function serviceFeeLog(){
        $st = input('param.st');
        $pagenum = input('post.pagenum');
        if(!$pagenum) $pagenum = 1;
        $pernum = 20;
        $where = [];
        $where[] = ['aid','=',aid];
        $where[] = ['mid','=',mid];
        if($st == 1){//充值记录
            $datalist = Db::name('servicefee_recharge_order')->field("id,money,`status`,from_unixtime(createtime) createtime")->where($where)->where('status=1')->page($pagenum,$pernum)->order('id desc')->select()->toArray();
            if(!$datalist) $datalist = [];
        }else{ //余额明细
            $datalist = Db::name('member_servicefee_log')->field("id,service_fee as money,`after`,from_unixtime(createtime) createtime,remark")->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
            if(!$datalist) $datalist = [];
        }

        $admin_set = Db::name('admin_set')->field('moneypay,recharge,withdraw')->where('aid',aid)->find();

        $showstatus = [];
        $showstatus[] = $admin_set['moneypay'] ;
        $showstatus[] = $admin_set['recharge'];

        return $this->json(['status'=>1,'data'=>$datalist,'showstatus' => $showstatus]);
    }

    public function activecoinlog(){
	    if(getcustom('active_coin')){
            $pagenum = input('post.pagenum');
            $st = input('post.st');
            if(!$pagenum) $pagenum = 1;
            $pernum = 20;
            $where = [];
            $where[] = ['aid','=',aid];
            $where[] = ['mid','=',mid];
            $datalist = Db::name('member_activecoin_log')->field('id,value,after,remark,from_unixtime(createtime)createtime')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
            if(!$datalist) $datalist = [];
            $score_weishu = 2;

            if($datalist){
                foreach($datalist as $k=>$v){
                    $datalist[$k]['value'] = dd_money_format($v['value'],$score_weishu);
                }
            }
            if(request()->isPost()){
                if($pagenum == 1) {
                    $set = Db::name('consumer_set')->where('aid', aid)->find();
                }
                $member_score = dd_money_format($this->member['active_coin'],$score_weishu);

                return $this->json(['status'=>1,'data'=>$datalist,'myscore'=>$member_score,
                    'set' => $set] );
            }

            $count = Db::name('member_activecoin_log')->where($where)->count();

            $rdata = [];
            $rdata['count'] = $count;
            $rdata['datalist'] = $datalist;
            $rdata['pernum'] = $pernum;
            $rdata['st'] = $st;
            $active_coin = Db::name('member')->where('id',mid)->value('active_coin');
            $member_score = dd_money_format($active_coin,$score_weishu);
            $rdata['myscore'] = $member_score;
            return $this->json($rdata);
        }
    }
    public function scoreweightlog(){
        if(getcustom('yx_buy_fenhong')){
            $pagenum = input('post.pagenum');
            $st = input('post.st');
            if(!$pagenum) $pagenum = 1;
            $pernum = 20;
            $where = [];
            $where[] = ['aid','=',aid];
            $where[] = ['mid','=',mid];
            $datalist = Db::name('buy_fenhong_log')->field('id,score_weight,score,from_unixtime(createtime)createtime,remark')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
            if(!$datalist) $datalist = [];
            $score_weishu = 2;

            if($datalist){
                foreach($datalist as $k=>$v){
                    $datalist[$k]['value'] = dd_money_format($v['value'],$score_weishu);
                }
            }
            if(request()->isPost()){
               
                $member_score_weight = $this->member['buy_fenhong_score_weight'];

                return $this->json(['status'=>1,'data'=>$datalist,'myscoreweight'=>$member_score_weight] );
            }

            $count = Db::name('buy_fenhong_log')->where($where)->count();

            $rdata = [];
            $rdata['count'] = $count;
            $rdata['datalist'] = $datalist;
            $rdata['pernum'] = $pernum;
            $rdata['st'] = $st;
            $active_coin = Db::name('member')->where('id',mid)->value('active_coin');
            $member_score = dd_money_format($active_coin,$score_weishu);
            $rdata['myscore'] = $member_score;
            return $this->json($rdata);
        }
    }
    
    public function commissionmaxtoscore(){
        $add_commission_max = getcustom('add_commission_max')?:0;
        if(getcustom('member_commission_max_toscore')){
            $userinfo = [];
//            $commission_max = $this->member['commission_max']?:0;
//            $totalcommission = $this->member['totalcommission']?:0;
//            $userinfo['commission_max'] = bcsub($commission_max,$totalcommission,2);
            $userinfo['commission_max'] =  $this->member['commission_max']?:0;
            if($add_commission_max){
                $userinfo['commission_max_self'] =  $this->member['commission_max_self']?:0;
                $userinfo['commission_max_plate'] =  $this->member['commission_max_plate']?:0;
            }else{
                $userinfo['commission_max_self'] =  $userinfo['commission_max']?:0;
                $userinfo['commission_max_plate'] =  0;
            }

            $sysset = Db::name('admin_set')->where('aid',aid)->field('member_commission_max,member_commission_max_toscore_st,member_commission_max_toscore_ratio')->find();
            if(!$sysset['member_commission_max_toscore_st'] || !$sysset['member_commission_max'])return $this->json(['status'=>0,'msg'=>'功能未开启']);
            $userinfo['member_commission_max'] = $sysset['member_commission_max'];
            $userinfo['member_commission_max_toscore_st'] = $sysset['member_commission_max_toscore_st'];
            $userinfo['member_commission_max_toscore_ratio'] = $sysset['member_commission_max_toscore_ratio'];
            $rdata['userinfo'] = $userinfo;
            if(request()->isPost()){
                Db::startTrans();
                if(!$sysset['member_commission_max_toscore_st'] || !$sysset['member_commission_max'])return $this->json(['status'=>0,'msg'=>'功能未开启']);
                $money =  input('param.money');
                if($money <= 0)return $this->json(['status'=>0,'msg'=>'请输入需要转换的'.t('佣金上限').'值']);
                if($money > $userinfo['commission_max_self']) return $this->json(['status'=>0,'msg'=>t('佣金上限').'值不足']);
                $score = dd_money_format( $money * $sysset['member_commission_max_toscore_ratio'] * 0.01,0);
                if($score < 1)return $this->json(['status'=>0,'msg'=>'输入的'.t('佣金上限').'值过低']);
                \app\commons\Member::addscore(aid,mid,$score,t('佣金上限').'转'.t('积分'));
                $res = \app\commons\Member::addcommissionmax(aid,mid,$money*-1,t('佣金上限').'转'.t('积分'));
                if(getcustom('greenscore_max')){
                    $maximum = $money>$this->member['maximum']?$this->member['maximum']:$money;
                    $rs = \app\commons\Member::addmaximum(aid,mid,$maximum*-1,t('佣金上限').'转入'.t('积分').'扣除');
                }
                Db::commit();
                return $this->json(['status'=>1,'msg'=>'提交成功']);
            }
            return $this->json($rdata);
        }
    }
    public function getcommissionmaxlog(){
	    if(getcustom('member_commission_max_toscore')){
            $pagenum = input('post.pagenum');
            $st = input('post.st');
            if(!$pagenum) $pagenum = 1;
            $pernum = 20;
            $where = [];
            $where[] = ['aid','=',aid];
            $where[] = ['mid','=',mid];
            $datalist = Db::name('member_commissionmax_log')->field('id,value,after,remark,from_unixtime(createtime)createtime')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
            if(!$datalist) $datalist = [];
            if($datalist){
                foreach($datalist as $k=>$v){
                    $datalist[$k]['value'] = $v['value'];
                }
            }
            $count = Db::name('member_commissionmax_log')->where($where)->count();
            $rdata = [];
            $rdata['count'] = $count;
            $rdata['data'] = $datalist;
            $rdata['pernum'] = $pernum;
            $rdata['st'] = $st;
            $commission_max = $this->member['commission_max']?:0;
            //$totalcommission = $this->member['totalcommission']?:0;
            //$commission_max = bcsub($commission_max,$totalcommission,2);
            $rdata['commission_max'] = $commission_max;
            return $this->json($rdata);
        }
    }
    
    //获取商品的收藏
    public function getShopFavorite(){
	    if(getcustom('member_shop_favorite')) {
            $pagenum = input('post.pagenum');
            if (!$pagenum) $pagenum = 1;
            $pernum = 20;
            $where = [];
            $where[] = ['f.aid', '=', aid];
            $where[] = ['f.mid', '=', mid];
            $where[] = ['f.type', '=', 'shop'];
            if (input('param.keyword')) $where[] = ['s.name', 'like', '%' . input('param.keyword') . '%'];
            if (input('param.cid')) {
                $cid = input('post.cid') ? input('post.cid/d') : input('param.cid/d');
                $where2 = "find_in_set('-1',showtj)";
                if ($this->member) {
                    $where2 .= " or find_in_set('" . $this->member['levelid'] . "',showtj)";
                    if ($this->member['subscribe'] == 1) {
                        $where2 .= " or find_in_set('0',showtj)";
                    }
                }
                $tjwhere[] = Db::raw($where2);
                //子分类
                $clist = Db::name('shop_category')->where($tjwhere)->where('aid', aid)->where('pid', $cid)->column('id');
                if ($clist) {
                    $clist2 = Db::name('shop_category')->where($tjwhere)->where('aid', aid)->where('pid', 'in', $clist)->column('id');
                    $cCate = array_merge($clist, $clist2, [$cid]);
                    if ($cCate) {
                        $whereCid = [];
                        foreach ($cCate as $k => $c2) {
                            $whereCid[] = "find_in_set({$c2},s.cid)";
                        }
                        $where[] = Db::raw(implode(' or ', $whereCid));
                    }
                } else {
                    $where[] = Db::raw("find_in_set(" . $cid . ",s.cid)");
                }
            }
            //商家的商品分类 
            if (input('param.cid2')) {
                $cid2 = input('post.cid2') ? input('post.cid2/d') : input('param.cid2/d');
                //子分类
                $clist = Db::name('shop_category2')->where('aid', aid)->where('pid', $cid2)->column('id');
                if ($clist) {
                    $clist2 = Db::name('shop_category2')->where('aid', aid)->where('pid', 'in', $clist)->column('id');
                    $cCate = array_merge($clist, $clist2, [$cid2]);
                    if ($cCate) {
                        $whereCid = [];
                        foreach ($cCate as $k => $c2) {
                            $whereCid[] = "find_in_set({$c2},s.cid2)";
                        }
                        $where[] = Db::raw(implode(' or ', $whereCid));
                    }
                } else {
                    $where[] = Db::raw("find_in_set(" . $cid2 . ",s.cid2)");
                }
            }
            if (input('param.field') && input('param.order')) {
                $order = 's.' . input('param.field') . ' ' . input('param.order') . ',s.sort desc,s.id desc';
            } else {
                $order = 's.sort desc,s.id desc';
            }
            $shop_set = Db::name('shop_sysset')->where('aid',aid)->field('sellprice_name,sellprice_color')->find();
            $datalist = Db::name('member_favorite')->alias('f')
                ->join('shop_product s', 's.id = f.proid')
                ->where($where)
                ->field('f.*,s.id,s.name,s.pic,s.sales,s.cid,s.cid2,s.sort,s.sell_price,s.lvprice_data')
                ->page($pagenum, $pernum)
                ->order($order)
                ->select()->toArray();
            foreach($datalist as $key=>$val){
                $lvprice_data = json_decode($val['lvprice_data'], true);
                if($lvprice_data){
                    $datalist[$key]['sell_price'] = floatval( $lvprice_data[$this->member['levelid']]);
                }else{
                    $datalist[$key]['sell_price'] = floatval( $val['sell_price']);
                }
                $datalist[$key]['price'] = floatval( $val['price']);
                $datalist[$key]['sellprice_color'] = $shop_set['sellprice_color'];
                $datalist[$key]['sellprice_name'] = $shop_set['sellprice_name'];
            }
            $count = Db::name('member_favorite')->alias('f')
                ->join('shop_product s', 's.id = f.proid')
                ->where($where)->count();
            $rdata = [];
            $rdata['count'] = $count;
            $rdata['data'] = $datalist;
            $rdata['pernum'] = $pernum;
            return $this->json($rdata);
        }
    }

    public function staffcommissionlog(){
        if(getcustom('extend_staff')){
            if(request()->isPost()){
                $staff = Db::name('staff')->where('mid',mid)->where('aid',aid)->find();
                if(!$staff){
                    return $this->json(['status'=>1,'data'=>[],'mycommission'=>0] );
                }
                $pagenum = input('post.pagenum');
                $st = input('post.st');
                if(!$pagenum) $pagenum = 1;
                $pernum = 20;
                $where = [];
                $where[] = ['aid','=',aid];
                $where[] = ['sid','=',$staff['id']];
                $datalist = Db::name('staff_commission_log')->field('id,commission,remark,from_unixtime(createtime)createtime')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
                if(!$datalist) $datalist = [];
                return $this->json(['status'=>1,'data'=>$datalist,'mycommission'=>$staff['commission']] );
            }
        }
    }

    public function silvermoneylog(){
	    if(getcustom('member_goldmoney_silvermoney')){
            $pagenum = input('post.pagenum');
            $st = input('post.st');
            if(!$pagenum) $pagenum = 1;
            $pernum = 20;
            $where = [];
            $where[] = ['aid','=',aid];
            $where[] = ['mid','=',mid];
            $datalist = Db::name('member_silvermoneylog')->field('id,silvermoney,after,remark,from_unixtime(createtime)createtime')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
            if(!$datalist) $datalist = [];
            if(request()->isPost()){
                return $this->json(['status'=>1,'data'=>$datalist,'mysilvermoney'=>$this->member['silvermoney']] );
            }
            $count = Db::name('member_silvermoneylog')->where($where)->count();

            $rdata = [];
            $rdata['count'] = $count;
            $rdata['datalist'] = $datalist;
            $rdata['pernum'] = $pernum;
            $rdata['st'] = $st;
            $rdata['mysilvermoney'] = $this->member['silvermoney'];
            return $this->json($rdata);
        }
    }

    public function goldmoneylog(){
	    if(getcustom('member_goldmoney_silvermoney')){
            $pagenum = input('post.pagenum');
            $st = input('post.st');
            if(!$pagenum) $pagenum = 1;
            $pernum = 20;
            $where = [];
            $where[] = ['aid','=',aid];
            $where[] = ['mid','=',mid];
            $datalist = Db::name('member_goldmoneylog')->field('id,goldmoney,after,remark,from_unixtime(createtime)createtime')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
            if(!$datalist) $datalist = [];
            if(request()->isPost()){
                return $this->json(['status'=>1,'data'=>$datalist,'mygoldmoney'=>$this->member['goldmoney']] );
            }
            $count = Db::name('member_goldmoneylog')->where($where)->count();

            $rdata = [];
            $rdata['count'] = $count;
            $rdata['datalist'] = $datalist;
            $rdata['pernum'] = $pernum;
            $rdata['st'] = $st;
            $rdata['mygoldmoney'] = $this->member['goldmoney'];
            return $this->json($rdata);
        }
    }

    public function linghuoxinsign()
    {
        if(getcustom('extend_linghuoxin')){
            $set = Db::name('linghuoxin_set')->where('aid',aid)->find();
            if(!$set){
                return $this->json(['status'=>0,'msg'=>'系统设置不存在，暂不能使用']);
            }
            if(empty($set['app_id']) || empty($set['secret']) || empty($set['corpid']) || empty($set['taskid']) || empty($set['apiurl'])){
                return $this->json(['status'=>0,'msg'=>'系统设置不完善，暂不能使用']);
            }
            if(request()->isPost()){
                $realname = input('?param.realname')?input('realname'):'';
                $tel      = input('?param.tel')?input('tel'):'';
                $usercard = input('?param.usercard')?input('usercard'):'';
                if(!$realname || !$tel || !$usercard){
                    return $this->json(['status'=>0,'msg'=>'请填写完整信息']);
                }
                if(!checkIdCard($usercard)){
                    return $this->json(['status'=>0,'msg'=>'身份证号码错误']);
                }
            }else{
                $usercard = $this->member['usercard'];
            }

            if($usercard){
                //查看是否签约
                $getchecksign = \app\customs\LinghuoxinCustom::getchecksign(aid,0,$usercard,$set);
                if($getchecksign && $getchecksign['status'] == 1){
                    if($getchecksign['data']['status'] == 2){
                        return $this->json(['status'=>0,'msg'=>'已签约成功']);
                    }else if($getchecksign['data']['status'] == 1){
                        return $this->json(['status'=>0,'msg'=>'已实名认证，等待签约中']);
                    }
                }else{
                    //return $this->json($getchecksign);
                }
            }
            //验证身份证是否提交过
            if($this->member['linghuoxin_signlogid']){
                $signlog = Db::name('member_linghuoxin_signlog')->where('id',$this->member['linghuoxin_signlogid'])->where('mid',mid)->where('usercard',$usercard)->field('id,status')->find();
            }else{
                $signlog = [];
            }
            if(request()->isPost()){
                //记录签约
                $log = [];
                $log['realname'] = $realname;
                $log['tel']      = $tel;
                $log['usercard'] = $usercard;
                if(!$signlog){
                    $log['aid'] = aid;
                    $log['mid'] = mid;
                    $log['createtime'] = time();
                    $logid = Db::name('member_linghuoxin_signlog')->insertGetId($log);
                    if(!$logid){
                        return $this->json(['status'=>0,'msg'=>'操作失败']);
                    }
                }else{
                    $log['updatetime'] = time();
                    $up = Db::name('member_linghuoxin_signlog')->where('id',$signlog['id'])->update($log);
                    if(!$up){
                        return $this->json(['status'=>0,'msg'=>'操作失败']);
                    }
                    $logid = $signlog['id'];
                }

                $udpate = [
                    'realname'=>$realname,
                    'tel'=>$tel,
                    'usercard'=>$usercard,
                    'linghuoxin_signlogid'=>$logid,
                ];
                //更新信息
                $up = Db::name('member')->where('aid',aid)->where('id',mid)->update($udpate);

                //h5签约链接:二要素（身份证+人脸）
                $signurl = $set['apiurl'].'/faquick/face?companyId='.$set['app_id'].'&thirdId='.mid.'&name='.$realname.'&idcard='.$usercard.'&phone='.$tel;
                //小程序
                $signurl2 = 'https://sign.linghuoxin.com/faindex?companyId='.$set['app_id'].'&thirdId='.mid.'&name='.$realname.'&idcard='.$usercard.'&phone='.$tel.'&env=miniprogram';
                return $this->json(['status'=>1,'msg'=>'操作成功','signurl'=>$signurl,'signurl2'=>$signurl2,'signappid'=>'wx01e9e17c8c07189c','signstatus'=>true]);
            }else{
                //灵活新签约
                $userinfo = [];
                $userinfo['realname'] = $this->member['realname'];
                $userinfo['tel']      = $this->member['tel'];
                $userinfo['usercard'] = $this->member['usercard'];

                $rdata = [];
                $rdata['status']     = 1;
                $rdata['signstatus'] = false;
                $rdata['userinfo']   = $userinfo;
                return $this->json($rdata);
            }
        }
    }
}