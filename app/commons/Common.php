<?php


namespace app\commons;
use think\facade\Db;
use think\helper\Str;

class Common
{
   
	//获取支持的平台
	public static function getplatform($aid){
		$admin = Db::name('admin')->where('id',$aid)->find();
		$platform = explode(',',$admin['platform']);
		return $platform;
	}
	//获取编辑器的内容
	public static function geteditorcontent($content,$aid=aid){
		$contentArr = json_decode(htmlspecialchars_decode($content), true);
		if(!$contentArr){
			$content = preg_replace_callback('/(<img.*?src=[\'|\"])([^\"\']*?)([\'|\"].*?[\/]?>)/is', function($matches)use($aid){
				//dump(trim(str_replace('&quot;','',$matches[2])));die;
				$rurl = \app\commons\Pic::uploadoss(trim(str_replace('&quot;','',$matches[2])));
				return $matches[1].$rurl.$matches[3];
			}, $content);

			$content = preg_replace_callback('/(background(\-image)?\s*:\s*url\([|\"|\']?)([^\"\']*?)([|\"|\']?\))/is', function($matches)use($aid){
				$rurl = \app\commons\Pic::uploadoss(trim(str_replace('&quot;','',$matches[3])));
				return $matches[1].$rurl.$matches[4];
			}, $content);
			return $content;
		}
		if (!empty($contentArr)) {
            //非定位模式下过滤定位组件
            if(getcustom('show_location')) {
                $sysset = Db::name('admin_set')->where('aid',$aid)->field('mode,id')->find();
                if(!in_array($sysset['mode'],[2,3])) {
                    $newContentArr = [];
                    foreach ($contentArr as $k => $v) {
                        if ($v['temp'] == 'location') {
                            continue;
                        } else {
                            $newContentArr[] = $v;
                        }
                    }
                    $contentArr = $newContentArr;
                }
            }
			//dump($contentArr);die;
			foreach ($contentArr as $k => $v) {

				if ($v['temp'] == 'richtext') {
					//$richtext = unescape($v['content']);
                    $richtext = urldecode($v['content']);
					
					$richtext = preg_replace_callback('/(<img.*?src=[\'|\"])([^\"\']*?)([\'|\"].*?[\/]?>)/is', function($matches)use($aid){
						$rurl = \app\commons\Pic::uploadoss(trim(str_replace('&quot;','',$matches[2])));
						return $matches[1].$rurl.$matches[3];
					}, $richtext);

					$richtext = preg_replace_callback('/(background(\-image)?\s*:\s*url\([|\"|\']?)([^\"\']*?)([|\"|\']?\))/is', function($matches)use($aid){
						$rurl = \app\commons\Pic::uploadoss(trim(str_replace('&quot;','',$matches[3])));
						return $matches[1].$rurl.$matches[4];
					}, $richtext);

					//$richtext = str_replace(' data-src',' src',$richtext);
					$contentArr[$k]['content'] = $richtext;
				}
				if($v['temp'] == 'cube'){
					$contentArr[$k]['params']['currentLayout'] = ['isempty'=>1];
				}
			}
		}
		$content = jsonEncode($contentArr);
		return $content;
	}
	//自动发货
	public static function autofh($type,$order){
		if($order['freight_type']!=3) return ;
		$aid = $order['aid'];
		$freight = Db::name('freight')->where('aid',$aid)->where('id',$order['freightid'])->find();
		if(!$freight || $freight['pstype']!=3) return ;
		if($freight['pscontenttype'] == 0){
			$pscontent = $freight['pscontent'];
		}else{
			if(\app\commons\Order::hasOrderGoodsTable($type)){
				$num = Db::name($type.'_order_goods')->where('orderid',$order['id'])->sum('num');
			}else{
				$num = $order['num'];
			}
			$codelist = Db::name('freight_codelist')->where('aid',$aid)->where('fid',$freight['id'])->where('status',0)->order('id')->limit($num)->select()->toArray();
			if(!$codelist || count($codelist) < $num) return ;
			$memberinfo = Db::name('member')->where('id',$order['mid'])->find();
			$pscontent = [];
			foreach($codelist as $codeinfo){
				$pscontent[] = $codeinfo['content'];
				Db::name('freight_codelist')->where('id',$codeinfo['id'])->update(['orderid'=>$order['id'],'ordernum'=>$order['ordernum'],'headimg'=>$memberinfo['headimg'],'nickname'=>$memberinfo['nickname'],'buytime'=>time(),'status'=>1]);
			}
			$pscontent = implode("\r\n",$pscontent);
		}
		Db::name($order.'_order')->where('id',$order['id'])->update(['freight_content'=>$pscontent,'status'=>2,'send_time'=>time()]);
		if(\app\commons\Order::hasOrderGoodsTable($type)){
			Db::name($type.'_order_goods')->where('orderid',$order['id'])->update(['status'=>2]);
		}
	}

	public static function getwuliu($express_no,$express,$express_type='', $aid=''){
        if(empty($express_no))
            return  [
                "time"=>"",
                "status"=>"物流单号不能为空",
                "context"=>"物流单号不能为空"
            ];
		if($express == '同城配送'){
		    if($express_type == 'express_wx'){
                $psorderinfo =Db::name('express_wx_order')->where('id',$express_no)->find();
                $psuser=['realname'=>$psorderinfo['rider_name'],'tel'=>$psorderinfo['rider_phone']];
            }else{
                $psorderinfo =Db::name('peisong_order')->where('id',$express_no)->find();
                if($psorderinfo['psid'] == -1){
                    $psuser=['realname'=>$psorderinfo['make_rider_name'],'tel'=>$psorderinfo['make_rider_mobile']];
                }else{
                    $psuser = Db::name('peisong_user')->where('id',$psorderinfo['psid'])->find();
                }
            }

			$list = [];
			if($psorderinfo['createtime']){
				$list[] = ['time'=>date('Y-m-d H:i',$psorderinfo['createtime']),'context'=>'已发布配送单'];
			}
			if($psorderinfo['starttime']){
				$list[] = ['time'=>date('Y-m-d H:i',$psorderinfo['starttime']),'context'=>'配送员'.$psuser['realname'].'('.$psuser['tel'].')'.'正在为您配送'];
			}
			if($psorderinfo['daodiantime']){
				$list[] = ['time'=>date('Y-m-d H:i',$psorderinfo['daodiantime']),'context'=>'配送员已到店'];
			}
			if($psorderinfo['quhuotime']){
				$list[] = ['time'=>date('Y-m-d H:i',$psorderinfo['quhuotime']),'context'=>'配送员已取货'];
			}
			if($psorderinfo['endtime']){
				$list[] = ['time'=>date('Y-m-d H:i',$psorderinfo['endtime']),'context'=>'配送完成'];
			}
			$list = array_reverse($list);
		}else{
            if($aid=='')
                $aid=aid;
            $sys = Db::name('admin_set')->where('aid',$aid)->find();

            //是否开启物流助手、是否优先使用物流助手查询轨迹
            //如果开启物流助手，并且设置为优先，则判断查询单号是否为微信物流运单，是则直接调用微信物流订单 否继续使用原查询接口
            $list = [];
            if($sys['miandanst'] == 1 && $sys['miandan_wx'] == 1){
                if(Str::contains($express_no, ':')){
                    $express_new = explode(":", $express_no)[0];
                }else{
                    $express_new = $express_no;
                }
                $miandan = Db::name("miandan_order")
                    ->where('waybill_id', $express_new)
                    ->find();
                if($miandan){
                    $delivery_id = $miandan['delivery_id'];
                    $waybill_id = $miandan['waybill_id'];
                    $wx_res = self::wx_getwuliu($delivery_id, $waybill_id, $aid);
                    if(!isset($wx_res['errcode']) || $wx_res['errcode'] == 0){
                        $path_list = $wx_res['path_item_list'];
                        $path_item_num = $wx_res['path_item_num'];
                        if($path_item_num == 0){
                            $list[] = [
                                "time"=>"",
                                "status"=>"暂无物流信息",
                                "context"=>"暂无物流信息"
                            ];
                        }else{
                            foreach ($path_list as $item) {
                                $list[] = [
                                    "time" => date("Y-m-d H:i:s", $item['action_time']),
                                    "status" => $item['action_msg'],
                                    "context" => $item['action_msg']
                                ];
                            }
                        }
                    }
                }
            }
            if (!empty($list)) {
                return $list;
            }

			$content = self::ali_getwuliu($express_no,$express,$aid);
			$data = json_decode($content,true);
			if(!$data || $data['msg']!='ok'){
				$list = [];
			}else{
				$list = $data['result']['list'];
				foreach($list as $k=>$v){
					$list[$k]['context'] = $v['status'];
				}
			}
		}
		return $list;
	}

	//推荐积分
	public static function user_tjscore($aid,$mid){
		$member = Db::name('member')->where('aid',$aid)->where('id',$mid)->find();
		if(!$member) return;
		if($member['pid']){
			//成员加入提醒 OPENTM207685059
			$tmplcontent = [];
			$tmplcontent['first'] = '恭喜您推荐新成员加入成功';
			$tmplcontent['remark'] = '点击进入查看~';
			$tmplcontent['keyword1'] = $member['nickname']; //姓名
			$tmplcontent['keyword2'] = date('Y-m-d H:i');//时间
			$rs = \app\commons\Wechat::sendtmpl($aid,$member['pid'],'tmpl_joinin',$tmplcontent,m_url('pages/my/usercenter', $aid));
			
			$parent1 = Db::name('member')->where('aid',$aid)->where('id',$member['pid'])->find();
			if($parent1){
				$agleveldata1 = Db::name('member_level')->where('aid',$aid)->where('id',$parent1['levelid'])->find();
				if($agleveldata1['can_agent']!=0 && $agleveldata1['score1']>0){
					if(!Db::name('member_tjscore')->where('mid',$parent1['id'])->where('frommid',$mid)->find()){
						$givescore = $agleveldata1['score1'];
						if($agleveldata1['scoremax'] > 0){
							$sumscore = Db::name('member_tjscore')->where('mid',$parent1['id'])->sum('score');
							if($sumscore >= $agleveldata1['scoremax']){
								$givescore = 0;
							}elseif($agleveldata1['scoremax'] - $sumscore < $givescore){
								$givescore = $agleveldata1['scoremax'] - $sumscore;
							}
						}
						if($givescore > 0){
							Db::name('member_tjscore')->insert(['aid'=>$aid,'mid'=>$parent1['id'],'frommid'=>$mid,'score'=>$givescore,'createtime'=>time()]);
							\app\commons\Member::addscore($aid,$parent1['id'],$givescore,'推荐奖励');
						}
					}
				}
				\app\commons\Member::uplv($aid,$parent1['id']);
			}
			if($parent1['pid']){
				$parent2 = Db::name('member')->where('aid',$aid)->where('id',$parent1['pid'])->find();
				if($parent2){
					$agleveldata2 = Db::name('member_level')->where('aid',$aid)->where('id',$parent2['levelid'])->find();
					if($agleveldata2['can_agent']>1&& $agleveldata2['score2']>0){
						if(!Db::name('member_tjscore')->where('mid',$parent2['id'])->where('frommid',$mid)->find()){
							$givescore = $agleveldata2['score2'];
							if($agleveldata2['scoremax'] > 0){
								$sumscore = Db::name('member_tjscore')->where('mid',$parent2['id'])->sum('score');
								if($sumscore >= $agleveldata2['scoremax']){
									$givescore = 0;
								}elseif($agleveldata2['scoremax'] - $sumscore < $givescore){
									$givescore = $agleveldata2['scoremax'] - $sumscore;
								}
							}
							if($givescore > 0){
								Db::name('member_tjscore')->insert(['aid'=>$aid,'mid'=>$parent2['id'],'frommid'=>$mid,'score'=>$givescore,'createtime'=>time()]);
								\app\commons\Member::addscore($aid,$parent2['id'],$givescore,t('下级').'推荐奖励');
							}
						}
					}
				}
			}
			if($parent2['pid']){
				$parent3 = Db::name('member')->where('aid',$aid)->where('id',$parent2['pid'])->find();
				if($parent3){
					$agleveldata3 = Db::name('member_level')->where('aid',$aid)->where('id',$parent3['levelid'])->find();
					if($agleveldata3['can_agent']>2&& $agleveldata3['score3']>0){
						if(!Db::name('member_tjscore')->where('mid',$parent3['id'])->where('frommid',$mid)->find()){
							$givescore = $agleveldata3['score3'];
							if($agleveldata3['scoremax'] > 0){
								$sumscore = Db::name('member_tjscore')->where('mid',$parent3['id'])->sum('score');
								if($sumscore >= $agleveldata3['scoremax']){
									$givescore = 0;
								}elseif($agleveldata3['scoremax'] - $sumscore < $givescore){
									$givescore = $agleveldata3['scoremax'] - $sumscore;
								}
							}
							if($givescore > 0){
								Db::name('member_tjscore')->insert(['aid'=>$aid,'mid'=>$parent3['id'],'frommid'=>$mid,'score'=>$givescore,'createtime'=>time()]);
								\app\commons\Member::addscore($aid,$parent3['id'],$givescore,t('下二级').'推荐奖励');
							}
						}
					}
				}
			}
		}
        }

	//注册赠送
	public static function registerGive($aid,$member)
    {
        $mid = $member['id'];
        $set = Db::name('register_giveset')->where('aid',$aid)->find();
        if($set['status']) {
            $date = date('Y-m-d H:i:s');
            if($date > $set['endtime'] || $date < $set['starttime']) {
                return ;
            }

            $tmpl_remark = [];
            if($set['money'] > 0) {
                \app\commons\Member::addmoney($aid,$mid,$set['money'],'注册赠送');
                $tmpl_remark[] = "赠送".t('余额').$set['money'].'元';
            }

            if($set['score'] > 0) {
                \app\commons\Member::addscore($aid,$mid,$set['score'],'注册赠送');
                $tmpl_remark[] = "赠送积分".$set['score'].'个';
            }

            if($set['coupon_ids']) {
                $coupon_ids = explode(',', $set['coupon_ids']);
                if($coupon_ids) {
                    foreach($coupon_ids as $coupon_id){
                        \app\commons\Coupon::send($aid,$mid,$coupon_id);
                    }
                    $tmpl_remark[] = "赠送优惠券".count($coupon_ids).'张';
                }
            }

            if(!empty($tmpl_remark)) {
                //成员加入提醒
                $tmplcontent = [];
                $tmplcontent['first'] = '欢迎您的加入';
                $tmplcontent['keyword1'] = $member['nickname']; //姓名
                $tmplcontent['keyword2'] = date('Y-m-d H:i');//时间
                $tmplcontent['remark'] = implode('，',$tmpl_remark);
                \app\commons\Wechat::sendtmpl(aid,$mid,'tmpl_joinin',$tmplcontent,m_url('pages/my/usercenter', $aid));
            }
        }
    }

	//升级费用分销
	public static function applypayfenxiao($aid,$orderid){
		$order = db('member_levelup_order')->where('aid',$aid)->where('id',$orderid)->find();
		$member = db('member')->where('id',$order['mid'])->find();
		$ogdata = [];
		if($member['pid']){
			$parent1 = db('member')->where('aid',$aid)->where('id',$member['pid'])->find();
			if($parent1){
				$agleveldata1 = db('member_level')->where('aid',$aid)->where('id',$parent1['levelid'])->find();
				if($agleveldata1['can_agent']!=0){
					$ogdata['parent1'] = $parent1['id'];
				}
			}
		}
		if($parent1['pid']){
			$parent2 = db('member')->where('aid',$aid)->where('id',$parent1['pid'])->find();
			if($parent2){
				$agleveldata2 = db('member_level')->where('aid',$aid)->where('id',$parent2['levelid'])->find();
				if($agleveldata2['can_agent']>1){
					$ogdata['parent2'] = $parent2['id'];
				}
			}
		}
		if($parent2['pid']){
			$parent3 = db('member')->where('aid',$aid)->where('id',$parent2['pid'])->find();
			if($parent3){
				$agleveldata3 = db('member_level')->where('aid',$aid)->where('id',$parent3['levelid'])->find();
				if($agleveldata3['can_agent']>2){
					$ogdata['parent3'] = $parent3['id'];
				}
			}
		}

		if($agleveldata1){
			if($agleveldata1['commissiontype']==1){ //固定金额按单
				$ogdata['parent1commission'] = $agleveldata1['commission1'];
			}else{
				$ogdata['parent1commission'] = $agleveldata1['commission1'] * $order['totalprice'] * 0.01;
			}
		}
		if($agleveldata2){
			if($agleveldata2['commissiontype']==1){
				$ogdata['parent2commission'] = $agleveldata2['commission2'];
			}else{
				$ogdata['parent2commission'] = $agleveldata2['commission2'] * $order['totalprice'] * 0.01;
			}
		}
		if($agleveldata3){
			if($agleveldata3['commissiontype']==1){
				$ogdata['parent3commission'] = $agleveldata3['commission3'];
			}else{
				$ogdata['parent3commission'] = $agleveldata3['commission3'] * $order['totalprice'] * 0.01;
			}
		}

		if($ogdata['parent1'] && $ogdata['parent1commission'] > 0){
			\app\commons\Member::addcommission($aid,$ogdata['parent1'],$order['mid'],$ogdata['parent1commission'],t('下级').t('会员').'升级奖励');

			Db::name('member_commission_record')->insert(['aid'=>$aid,'mid'=>$ogdata['parent1'],'frommid'=>$order['mid'],'orderid'=>$orderid,'ogid'=>0,'type'=>'levelup','commission'=>$ogdata['parent1commission'],'score'=>0,'remark'=>t('下级').t('会员').'升级奖励','createtime'=>time(),'status'=>1,'endtime'=>time()]);

			//公众号通知 分销成功提醒
			$parent1 = db('member')->where('aid',$aid)->where('id',$ogdata['parent1'])->find();
			$tmplcontent = [];
			$tmplcontent['first'] = '恭喜您，成功分销获得'.t('佣金').'：￥'.$ogdata['parent1commission'];
			$tmplcontent['remark'] = '点击进入查看~';
			$tmplcontent['keyword1'] = $order['title']; //商品信息
			$tmplcontent['keyword2'] = $order['totalprice'];//商品单价
			$tmplcontent['keyword3'] = $ogdata['parent1commission'].'元';//商品佣金
			$tmplcontent['keyword4'] = date('Y-m-d H:i:s',$order['createtime']);//分销时间
			$rs = \app\commons\Wechat::sendtmpl($aid,$parent1['id'],'tmpl_fenxiaosuccess',$tmplcontent,m_url('pages/my/usercenter', $aid));
			//短信通知
			$rs = \app\commons\Sms::send($aid,$parent1['tel'],'tmpl_fenxiaosuccess',['money'=>$ogdata['parent1commission']]);
		}
		if($ogdata['parent2'] && $ogdata['parent2commission'] > 0){
			\app\commons\Member::addcommission($aid,$ogdata['parent2'],$order['mid'],$ogdata['parent2commission'],t('下二级').t('会员').'升级奖励');

			Db::name('member_commission_record')->insert(['aid'=>$aid,'mid'=>$ogdata['parent2'],'frommid'=>$order['mid'],'orderid'=>$orderid,'ogid'=>0,'type'=>'levelup','commission'=>$ogdata['parent2commission'],'score'=>0,'remark'=>t('下二级').t('会员').'升级奖励','createtime'=>time(),'status'=>1,'endtime'=>time()]);

			//公众号通知 分销成功提醒
			$parent2 = db('member')->where('aid',$aid)->where('id',$ogdata['parent2'])->find();
			$tmplcontent = [];
			$tmplcontent['first'] = '恭喜您，成功分销获得'.t('佣金').'：￥'.$ogdata['parent2commission'];
			$tmplcontent['remark'] = '点击进入查看~';
			$tmplcontent['keyword1'] = $order['title']; //商品信息
			$tmplcontent['keyword2'] = $order['totalprice'];//商品单价
			$tmplcontent['keyword3'] = $ogdata['parent2commission'].'元';//商品佣金
			$tmplcontent['keyword4'] = date('Y-m-d H:i:s',$order['createtime']);//分销时间
			$rs = \app\commons\Wechat::sendtmpl($aid,$parent2['id'],'tmpl_fenxiaosuccess',$tmplcontent,m_url('pages/my/usercenter', $aid));
			//短信通知
			$rs = \app\commons\Sms::send($aid,$parent2['tel'],'tmpl_fenxiaosuccess',['money'=>$ogdata['parent2commission']]);
		}
		if($ogdata['parent3'] && $ogdata['parent3commission'] > 0){
			\app\commons\Member::addcommission($aid,$ogdata['parent3'],$order['mid'],$ogdata['parent3commission'],t('下三级').t('会员').'升级奖励');

			Db::name('member_commission_record')->insert(['aid'=>$aid,'mid'=>$ogdata['parent3'],'frommid'=>$order['mid'],'orderid'=>$orderid,'ogid'=>0,'type'=>'levelup','commission'=>$ogdata['parent3commission'],'score'=>0,'remark'=>t('下三级').t('会员').'升级奖励','createtime'=>time(),'status'=>1,'endtime'=>time()]);

			//公众号通知 分销成功提醒
			$parent3 = db('member')->where('aid',$aid)->where('id',$ogdata['parent3'])->find();
			$tmplcontent = [];
			$tmplcontent['first'] = '恭喜您，成功分销获得'.t('佣金').'：￥'.$ogdata['parent3commission'];
			$tmplcontent['remark'] = '点击进入查看~';
			$tmplcontent['keyword1'] = $order['title']; //商品信息
			$tmplcontent['keyword2'] = $order['totalprice'];//商品单价
			$tmplcontent['keyword3'] = $ogdata['parent3commission'].'元';//商品佣金
			$tmplcontent['keyword4'] = date('Y-m-d H:i:s',$order['createtime']);//分销时间
			$rs = \app\commons\Wechat::sendtmpl($aid,$parent3['id'],'tmpl_fenxiaosuccess',$tmplcontent,m_url('pages/my/usercenter', $aid));
			//短信通知
			$rs = \app\commons\Sms::send($aid,$parent3['tel'],'tmpl_fenxiaosuccess',['money'=>$ogdata['parent3commission']]);
		}
	}

    //生成订单号
    public static function generateOrderNo($aid,$order_type = 'shop_order')
    {
        $prefix = '';
        if ($order_type == 'shop_order' || $order_type == 'shop') {

        } elseif ($order_type == 'booking_order' || $order_type == 'booking') {
            $prefix = '50';
        }
        elseif ($order_type == 'restaurant_shop_order' || $order_type == 'restaurant_shop') {
            $prefix = '51';
        }elseif($order_type=='weight_order' || $order_type == 'weight'){
            $prefix = 'W';
        }
        $date = date('ymdHis');
        $rand = rand(100000,999999);
        $order_no = $prefix . $date . $rand;
        return $order_no;
    }

    /**
     * 获取sysset数据
     * @param $name name值，对应查询数据
     * @param $field 字段名，指定后返回具体数据
     * @return array|mixed
     * todo 缓存
     */
    public static function getSysset($name = 'webinfo', $field = '')
    {
        $value = Db::name('sysset')->where('name',$name)->value('value');
        if($value){
            $rs = json_decode($value,true);
            if($field)
                return $rs[$field];
            return $rs;
        }
        else
            return [];
    }

	//阿里云查物流接口 https://market.aliyun.com/products/57126001/cmapi021863.html#sku=yuncode1586300006
	public static function ali_getwuliu($no,$typename,$aid='',$bid=''){
		if($aid == '') $aid = aid;
		if($bid == ''){
			if (defined('bid')) {
				$bid = bid;
			}else{
				$bid = 0;
			}
		}

		$appcode = '';
		$ainfo = db('admin')->where('id',$aid)->find();	
		if($ainfo['ali_appcode_choose'] != 1){
			$admin_set = db('admin_set')->where('aid',$aid)->find();
			if($admin_set['ali_appcode']){
				$appcode = $admin_set['ali_appcode'];
			}
		}else{
			$info = db('sysset')->where('name','webinfo')->find();
			$webinfo = json_decode($info['value'],true);
			if($webinfo['ali_appcode']){
				$appcode = $webinfo['ali_appcode'];
			}
		}
        $reslist = [
			'status'=>0,
			'msg'=>'ok',
			'result'=>[
				'number'=>$no,
				'type'=>$typename,
				'list'=>[
					0=>[
						'time'=>date("Y-m-d H:i:s"),
						'status'=>'未查询到数据',
					]
				]
			]
		];
		//if(!$appcode) return [];
		if(!$appcode){
			$reslist['result']['list'][0]['status'] = '商家未配置快递查询AppCode，请配置后重试';
			return json_encode($reslist);
		}
		
		$typeArr = express_data();
		$type = '';
		if($typename){
			$typename = str_replace('京东快递','京东',$typename);
			$typename = str_replace('京东物流','京东',$typename);
			$type = $typeArr[$typename]?$typeArr[$typename]:$typename;
		}
		//if(!$type || !$no) return [];
		if(!$no) {
			$reslist['result']['list'][0]['status'] = '物流单号、物流名称不能为空';
			return json_encode($reslist);
		}
		$no = trim($no);
		$ali_wuliu = db('ali_wuliu')->where(['aid'=>$aid,'bid'=>$bid,'type'=>$type,'no'=>$no])->order('id desc')->find();
		$now_time = time()-(30*60);
		if($ali_wuliu && $now_time < $ali_wuliu['createtime']){
			return $ali_wuliu['content'];
		}
		$host = "https://wuliu.market.alicloudapi.com";//api访问链接
		$path = "/kdi";//API访问后缀
		$method = "GET";
		$appcode = $appcode;//替换成自己的阿里云appcode
		$headers = array();
		array_push($headers, "Authorization:APPCODE " . $appcode);
        $querys = "no={$no}";  //参数写在这里
		if($type){
            $querys .= "&type={$type}";
        }

		$bodys = "";
		$url = $host . $path . "?" . $querys;//url拼接

		$curl = curl_init();
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($curl, CURLOPT_FAILONERROR, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	//	curl_setopt($curl, CURLOPT_HEADER, false);
		curl_setopt($curl, CURLOPT_HEADER, true); //如不输出json, 请打开这行代码，打印调试头部状态码。
		//状态码: 200 正常；400 URL无效；401 appCode错误； 403 次数用完； 500 API网管错误
		if (1 == strpos("$".$host, "https://"))
		{
			curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
		}
		$out_put = curl_exec($curl);

		$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

		list($header, $body) = explode("\r\n\r\n", $out_put, 2);
		if ($httpCode == 200) {
			//print("正常请求计费(其他均不计费)<br>");
			$data = json_decode($body,true);
			if(!$data || $data['msg']!='ok'){
				$reslist['result']['list'][0]['status'] = $data['msg'];
				$body = json_encode($reslist);
			}
			$aliinfo = [];
			$aliinfo['createtime'] = time();
			$aliinfo['type'] = $type;
			$aliinfo['no'] = $no;
			$aliinfo['aid'] = $aid;
			$aliinfo['bid'] = $bid;
			$aliinfo['content'] = $body;
			$rs = db('ali_wuliu')->insertGetId($aliinfo);
			return $body;
		} else {
			//$reslist['result']['list'][0]['status'] = '数据查询异常，请稍后重试'.$header;
			if ($httpCode == 400 && strpos($header, "Invalid Param Location") !== false) {
				$reslist['result']['list'][0]['status'] = '参数错误';
				//print("参数错误");
			} elseif ($httpCode == 400 && strpos($header, "Invalid AppCode") !== false) {
				$reslist['result']['list'][0]['status'] = 'AppCode错误';
				//print("AppCode错误");
			} elseif ($httpCode == 400 && strpos($header, "Invalid Url") !== false) {
				$reslist['result']['list'][0]['status'] = '请求的 Method、Path 或者环境错误';
				//print("请求的 Method、Path 或者环境错误");
			} elseif ($httpCode == 403 && strpos($header, "Unauthorized") !== false) {
				$reslist['result']['list'][0]['status'] = '服务未被授权（或URL和Path不正确）';
				//print("服务未被授权（或URL和Path不正确）");
			} elseif ($httpCode == 403 && strpos($header, "Quota Exhausted") !== false) {
				$reslist['result']['list'][0]['status'] = '套餐包次数用完';
				//print("套餐包次数用完");
			} elseif ($httpCode == 403 && strpos($header, "Api Market Subscription quota exhausted") !== false) {
				$reslist['result']['list'][0]['status'] = '快递查询额度不足，请充值';
				//print("套餐包次数用完，请续购套餐");
			} elseif ($httpCode == 500) {
				$reslist['result']['list'][0]['status'] = 'API网关错误';
				//print("API网关错误");
			} elseif ($httpCode == 0) {
				$reslist['result']['list'][0]['status'] = 'URL错误';
				//print("URL错误");
			} else {
				//\think\facade\Log::write($header);
				$reslist['result']['list'][0]['status'] = '参数名错误 或 其他错误'.$header;
				//print("参数名错误 或 其他错误");
				// print($httpCode);
				// $headers = explode("\r\n", $header);
				// $headList = array();
				// foreach ($headers as $head) {
				// 	$value = explode(':', $head);
				// 	$headList[$value[0]] = $value[1];
				// }
				// print($headList['x-ca-error-message']);
			}
			
			return json_encode($reslist);
		}
	}
    public static function wx_getwuliu($delivery_id, $waybill_id, $aid = '')
    {
        if ($aid == '') $aid = aid;
        $postdata = [
            'delivery_id' => $delivery_id,
            'waybill_id' => $waybill_id,
        ];
        $access_token = Wechat::access_token($aid, 'wx');
        $url = 'https://api.weixin.qq.com/cgi-bin/express/business/path/get?access_token=' . $access_token;
        $res = request_post($url, jsonEncode($postdata));
        return json_decode($res, true);
    }

}