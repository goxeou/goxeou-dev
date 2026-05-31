<?php


// +----------------------------------------------------------------------
// | 模板消息推送
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class Mptmpl extends Common
{	
    public function initialize(){
		parent::initialize();
		if(bid > 0) showmsg('无访问权限');
	}
	//我的模板
	public function mytmpl(){
		$access_token = \app\commons\Wechat::access_token(aid,'mp');
		if(request()->isAjax()){
			$url = 'https://api.weixin.qq.com/cgi-bin/template/get_all_private_template?access_token='.$access_token;
			$res = request_get($url);
			$res = json_decode($res,true);
			if($res['errcode']!=0){
				return json(['status'=>0,'msg'=>\app\commons\Wechat::geterror($res)]);
			}
			$list = $res['template_list'];
			return json(['code'=>0,'msg'=>'查询成功','count'=>count($list),'data'=>$list]);
		}

		//获取设置的行业信息
		$url = 'https://api.weixin.qq.com/cgi-bin/template/get_industry?access_token='.$access_token;
		$rs = request_get($url);
		$rs = json_decode($rs,true);
		if($rs['errcode']){
			if($rs['errcode']==48001){
				showmsg('只有认证的服务号才有此权限');
			}else{
				showmsg(\app\commons\Wechat::geterror($rs));
			}
		}
		if($rs['primary_industry']['second_class'] !='互联网|电子商务' && $rs['secondary_industry']['second_class'] !='互联网|电子商务'){
			//设置行业 互联网|电子商务
			$industrylist = [
				'IT科技/互联网|电子商务'=>1,
				'IT科技/IT软件与服务'=>2,
				'IT科技/IT硬件与设备'=>3,
				'IT科技/电子技术'=>4,
				'IT科技/通信与运营商'=>5,
				'IT科技/网络游戏'=>6,
				'金融业/银行'=>7,
				'金融业/基金理财信托'=>8,
				'金融业/保险'=>9,
				'餐饮/餐饮'=>10,
				'酒店旅游/酒店'=>11,
				'酒店旅游/旅游'=>12,
				'运输与仓储/快递'=>13,
				'运输与仓储/物流'=>14,
				'运输与仓储/仓储'=>15,
				'教育/培训'=>16,
				'教育/院校'=>17,
				'政府与公共事业/学术科研'=>18,
				'政府与公共事业/交警'=>19,
				'政府与公共事业/博物馆'=>20,
				'政府与公共事业/公共事业非盈利机构'=>21,
				'医药护理/医药医疗'=>22,
				'医药护理/护理美容'=>23,
				'医药护理/保健与卫生'=>24,
				'交通工具/汽车相关'=>25,
				'交通工具/摩托车相关'=>26,
				'交通工具/火车相关'=>27,
				'交通工具/飞机相关'=>28,
				'房地产/建筑'=>29,
				'房地产/物业'=>30,
				'消费品/消费品'=>31,
				'商业服务/法律'=>32,
				'商业服务/会展'=>33,
				'商业服务/中介服务'=>34,
				'商业服务/认证'=>35,
				'商业服务/审计'=>36,
				'文体娱乐/传媒'=>37,
				'文体娱乐/体育'=>38,
				'文体娱乐/娱乐休闲'=>39,
				'印刷/印刷'=>40,
				'其它/其它'=>41,	
			];
			if($rs['primary_industry']['first_class']){
				$industry_id1 = $industrylist[$rs['primary_industry']['first_class'].'/'.$rs['primary_industry']['second_class']];
				$industry_id2 = 1;
			}else{
				$industry_id1 = 1;
				$industry_id2 = 2;
			}
			$url = 'https://api.weixin.qq.com/cgi-bin/template/api_set_industry?access_token='.$access_token;
			$rs = request_post($url,jsonEncode(['industry_id1'=>$industry_id1,'industry_id2'=>$industry_id2]));
			$url = 'https://api.weixin.qq.com/cgi-bin/template/get_industry?access_token='.$access_token;
			$rs = request_get($url);
			$rs = json_decode($rs,true);
		}
		View::assign('primary_industry',$rs['primary_industry']);
		View::assign('secondary_industry',$rs['secondary_industry']);
		return View::fetch();
	}
	//模板消息设置
	public function tmplset(){
		if(request()->isPost()){
			$rs = Db::name('mp_tmplset')->where('aid',aid)->find();
			$info = input('post.info/a');
			if($rs){
				Db::name('mp_tmplset')->where('aid',aid)->update($info);
			}else{
				$info['aid'] = aid;
				Db::name('mp_tmplset')->insert($info);
			}
			//$uinfo = input('post.uinfo/a');
			//Db::name('admin_user')->where('aid',aid)->where('id',$this->uid)->update($uinfo);
			\app\commons\System::plog('公众号模板消息设置');
			return json(['status'=>1,'msg'=>'设置成功','url'=>(string)url()]);
		}
		$info = Db::name('mp_tmplset')->where('aid',aid)->find();
		if(!$info){
			$set = Db::name('admin_set')->where('aid',aid)->find();
			Db::name('mp_tmplset')->insert(['aid'=>aid]);
			$info = Db::name('mp_tmplset')->where('aid',aid)->find();
		}
		$uinfo = Db::name('admin_user')->where('aid',aid)->where('id',$this->uid)->find();
		
		//$platform = Db::name('admin')->where('id',aid)->value('platform');
		//View::assign('platform',$platform);
		View::assign('info',$info);
		View::assign('uinfo',$uinfo);
        View::assign('restaurant',getcustom('restaurant'));
		return View::fetch();
	}
	//获取模板ID
	public function gettmplid(){
		
		$template_no = input('post.template_no');
		$url = 'https://api.weixin.qq.com/cgi-bin/template/api_add_template?access_token='.\app\commons\Wechat::access_token(aid,'mp');
		$data = [];
		$data['template_id_short'] = $template_no;
		$rs = request_post($url,jsonEncode($data));
		$rs = json_decode($rs,true);
		if($rs['errcode']){
			if($rs['errcode'] == '45026'){
				return json(['status'=>0,'msg'=>'已添加的模板已达到数量限制，请先删除一些不用的模板再试','rs'=>$rs]);
			}
			return json(['status'=>0,'msg'=>\app\commons\Wechat::geterror($rs),'rs'=>$rs]);
		}else{
			return json(['status'=>1,'data'=>$rs['template_id'],'msg'=>'添加成功']);
		}
	}

    //类目模板消息设置
    public function tmplsetNew(){
        if(request()->isPost()){
            $rs = Db::name('mp_tmplset_new')->where('aid',aid)->find();
            $info = input('post.info/a');
            if($rs){
                Db::name('mp_tmplset_new')->where('aid',aid)->update($info);
            }else{
                $info['aid'] = aid;
                Db::name('mp_tmplset_new')->insert($info);
            }

            \app\commons\System::plog('公众号模板消息设置');
            return json(['status'=>1,'msg'=>'设置成功','url'=>(string)url()]);
        }
        $info = Db::name('mp_tmplset_new')->where('aid',aid)->find();
        if(!$info){
            $set = Db::name('admin_set')->where('aid',aid)->find();
            Db::name('mp_tmplset_new')->insert(['aid'=>aid]);
            $info = Db::name('mp_tmplset_new')->where('aid',aid)->find();
        }
        $uinfo = Db::name('admin_user')->where('aid',aid)->where('id',$this->uid)->find();

        View::assign('info',$info);
        View::assign('uinfo',$uinfo);
        View::assign('restaurant',getcustom('restaurant'));
        return View::fetch();
    }
    //获取模板ID 类目模板 update230710
    //https://developers.weixin.qq.com/doc/offiaccount/Message_Management/Template_Message_Interface.html#%E8%8E%B7%E5%BE%97%E6%A8%A1%E6%9D%BFID
    public function gettmplidNew(){
        $template_no = input('post.template_no');
        $url = 'https://api.weixin.qq.com/cgi-bin/template/api_add_template?access_token='.\app\commons\Wechat::access_token(aid,'mp');
        $data = [];
        $data['template_id_short'] = $template_no;
        $data['keyword_name_list'] = $this->getKeywordsList($template_no);
        $rs = request_post($url,jsonEncode($data));
        $rs = json_decode($rs,true);
        if($rs['errcode']){
            if($rs['errcode'] == '45026'){
                return json(['status'=>0,'msg'=>'已添加的模板已达到数量限制，请先删除一些不用的模板再试','rs'=>$rs]);
            }
            return json(['status'=>0,'msg'=>\app\commons\Wechat::geterror($rs),'rs'=>$rs]);
        }else{
            return json(['status'=>1,'data'=>$rs['template_id'],'msg'=>'添加成功']);
        }
    }

	//删除模板
	public function mytpldel(){
		
		$template_ids = input('post.template_ids/a');
		$access_token = \app\commons\Wechat::access_token(aid,'mp');
		$url = 'https://api.weixin.qq.com/cgi-bin/template/del_private_template?access_token='.$access_token;
		foreach($template_ids as $template_id){
			$res = request_post($url,jsonEncode(array('template_id'=>$template_id)));
			if($res['errcode']!=0)  return json(['status'=>0,'msg'=>$res['errmsg']]);
		}
		return json(['status'=>1,'msg'=>'删除成功']);
	}


    private function getKeywordsList($template_no)
    {
        switch ($template_no){
            case 45764: //订单提交成功通知,下单门店{{thing8.DATA}}订单号{{character_string2.DATA}}商品名称{{thing3.DATA}}下单金额{{amount7.DATA}}提交时间{{time4.DATA}}
                $list = ['订单号','下单门店','商品名称','下单金额','提交时间'];
                break;
            case 43216: //订单支付成功通知,门店{{thing8.DATA}}订单号{{character_string2.DATA}}商品名称{{thing3.DATA}}会员名称{{phrase18.DATA}}支付金额{{amount5.DATA}}
                $list = ['门店','订单号','商品名称','会员名称','支付金额'];
                break;
            case 47568: //买单支付成功通知,门店{{thing13.DATA}}订单编号{{character_string4.DATA}}商品名称{{thing11.DATA}}付款人{{thing12.DATA}}交易金额{{amount15.DATA}}
                $list = ['门店','订单编号','商品名称','付款人','交易金额'];
                break;
            case 42984: //订单发货通知,商品名称{{thing4.DATA}}快递公司{{thing13.DATA}}快递单号{{character_string14.DATA}}收货人{{thing16.DATA}}
                $list = ['商品名称','快递公司','快递单号','收货人'];
                break;
            case 46234: //订单完成通知,收货人{{thing3.DATA}} 订单号{{character_string7.DATA}}送达时间{{time8.DATA}}
                $list = ['收货人','订单号','送达时间'];
                break;
            case 46044: //退款申请通知，订单号{{number2.DATA}}退款金额{{amount4.DATA}}
                $list = ['订单号','退款金额'];
                break;
            case 46622: //退款成功通知，订单编号{{character_string1.DATA}}商品名称{{thing2.DATA}}退款金额{{amount3.DATA}}
                $list = ['订单编号','商品名称','退款金额'];
                break;
            case 46623: //退款驳回通知，订单编号{{character_string1.DATA}}商品名称{{thing2.DATA}}退款金额{{amount3.DATA}}
                $list = ['订单编号','商品名称','退款金额'];
                break;
             case 42830: //消费成功通知，消费项目{{thing2.DATA}}消费金额{{amount3.DATA}}卡内余额{{amount4.DATA}}消费时间{{time6.DATA}}
                 $list = ['消费项目','消费金额','卡内余额','消费时间'];
                 break;
            case 48089: //收到客户新订单通知，订单编号{{character_string1.DATA}}门店名称{{thing16.DATA}}商品名称{{thing8.DATA}}客户地址{{thing5.DATA}}订单时间{{time2.DATA}}
                $list = ['订单编号','门店名称','商品名称','客户地址','订单时间'];
                break;
            case 52381: //提现成功通知，提现金额{{amount2.DATA}}，提现时间{{time3.DATA}}
                $list = ['提现金额','提现时间'];
                break;
            case 46046: //收到报名申请通知，报名名称{{thing3.DATA}} 申请时间{{time5.DATA}}
                $list = ['报名名称','申请时间'];
                break;
            case 48306: //报名审核结果通知，报名名称{{thing9.DATA}} 审核结果{{thing2.DATA}} 审核时间{{time3.DATA}}
                $list = ['报名名称','审核结果','审核时间'];
                break;
            case 57863: //订单缺货提醒，订单编号{{character_string1.DATA}} 下单时间{{time2.DATA}} 商品名称{{thing3.DATA}}
                $list = ['订单编号','下单时间','商品名称'];
                break;
            case 48189: //账单结算成功通知，店铺名称{{{thing6.DATA}} 结算时间{{{time11.DATA}} 结算金额{{amount9.DATA}}   可用金额{{amount5.DATA}}  
                $list = ['店铺名称','结算时间','结算金额','可用余额'];
                break;
            case 53349: //设备缺货提醒   机器名称{{thing11.DATA}}  地点{{thing12.DATA}}
                $list = ['机器名称','地点'];
                break;
            case 46230: //会员购买成功通知   到期时间{{time5.DATA}}  购买类型{{thing3.DATA}}
                $list = ['到期时间','购买类型'];
            case 52660: //售后订单处理通知   商品名称{{thing5.DATA}} 商品名称{{thing11.DATA}}
                $list = ['商品名称','客户名称'];
                break;
            default:$list = [];
        }
        return $list;
    }
}