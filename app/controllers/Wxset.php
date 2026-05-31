<?php


// +----------------------------------------------------------------------
// | 小程序设置
// +----------------------------------------------------------------------
namespace app\controllers;
use app\commons\System;
use app\commons\Wechat;
use think\facade\View;
use think\facade\Log;
use think\facade\Db;

class Wxset extends Common
{	
	//列表
    public function index(){
		$set = Db::name('admin_setapp_wx')->where('aid',aid)->find();
		if(!$set || $set['appid']==''){
			showmsg('请先创建或授权小程序');
		}
		$access_token = \app\commons\Wechat::access_token(aid,'wx');
		$info = curl_get('https://api.weixin.qq.com/cgi-bin/account/getaccountbasicinfo?access_token='.$access_token);
		$info = json_decode($info,true);
		//dump($info);
		if($info['errcode']){
			if($info['errcode']=='41033'){
				showmsg('非本平台创建的小程序无法设置，请前往微信公众平台进行设置(mp.weixin.qq.com)');
			}else{
				showmsg(\app\commons\Wechat::geterror($info));
			}
		}else{
			//已设置的类目
			$category = curl_get('https://api.weixin.qq.com/cgi-bin/wxopen/getcategory?access_token='.$access_token);
			$category = json_decode($category,true);
			//dump($category);
			//是否可被搜索到
			$cansearch = curl_get('https://api.weixin.qq.com/wxa/getwxasearchstatus?access_token='.$access_token);
			$cansearch = json_decode($cansearch,true);
			//附近小程序地点列表
			$nearbypoidata = curl_get('https://api.weixin.qq.com/wxa/getnearbypoilist?page=1&page_rows=20&access_token='.$access_token);
			$nearbypoidata = json_decode($nearbypoidata,true);
			$poi_list = json_decode($nearbypoidata['data']['data'],true);

			//是否开通直播
			$url = 'https://api.weixin.qq.com/wxa/business/getliveinfo?access_token='.$access_token;
			$rs = request_post($url,jsonEncode(['start'=>0,'limit'=>1]));
			$rs = json_decode($rs,true);
			if(isset($rs['errcode']) && $rs['errcode']!=0 && $rs['errcode']!=1){//未开通
				$info['livestatus'] = 0;
			}else{
				$info['livestatus'] = 1;
			}


			$info['user_name'] = $set['nickname'];

			View::assign('info',$info);
			View::assign('category',$category);
			View::assign('cansearch',$cansearch);
			View::assign('nearbypoidata',$nearbypoidata);
			View::assign('poi_list',$poi_list);
			return View::fetch();
		}
    }
	//设置小程序名称
    public function setnickname(){
        $access_token = \app\commons\Wechat::access_token(aid,'wx');
        $data = [];
        $data['nick_name'] = input('post.set_nickname_nickname');
        if(!$data['nick_name']){
            return json(['status'=>0,'msg'=>'请填写名称']);
        }
        if(input('post.set_nickname_id_card')){
            $data['id_card'] = \app\commons\Wechat::pictomedia(aid,'wx',input('post.set_nickname_id_card'));
        }
        if(input('post.set_nickname_license')){
            $data['license'] = \app\commons\Wechat::pictomedia(aid,'wx',input('post.set_nickname_license'));
        }
        if(input('post.naming_other_stuff_1')){
            $data['naming_other_stuff_1'] = \app\commons\Wechat::pictomedia(aid,'wx',input('post.naming_other_stuff_1'));
        }
        $rs = curl_post('https://api.weixin.qq.com/wxa/setnickname?access_token='.$access_token,jsonEncode($data));
        $rs = json_decode($rs,true);
        if($rs['errcode']!=0){
            return json(['status'=>0,'msg'=>\app\commons\Wechat::geterror($rs).($rs['wording']?'-'.$rs['wording']:'')]);
        }else{
            Db::name('admin_setapp_wx')->where('aid',aid)->update(['nickname'=>$data['nick_name']]);
            if($rs['audit_id']){
                return json(['status'=>1,'msg'=>'名称已提交,请等待审核','url'=>(string)url('index')]);
            }else{
                return json(['status'=>1,'msg'=>'修改成功','url'=>(string)url('index')]);
            }
        }
    }
	//设置小程序头像
	public function setheadimg(){
		$access_token = \app\commons\Wechat::access_token(aid,'wx');
		$headimg = input('post.set_headimg');
		if(!$headimg) return json(['status'=>0,'msg'=>'请上传头像']);
		$data = array();
		$data['head_img_media_id'] = \app\commons\Wechat::pictomedia(aid,'wx',$headimg);
		$data['x1'] = 0;
		$data['y1'] = 0;
		$data['x2'] = 1;
		$data['y2'] = 1;
		$rs = curl_post('https://api.weixin.qq.com/cgi-bin/account/modifyheadimage?access_token='.$access_token,jsonEncode($data));
		$rs = json_decode($rs,true);
		//dump($data);
		if($rs['errcode']!=0){
			return json(['status'=>0,'msg'=>\app\commons\Wechat::geterror($rs)]);
		}else{
			$info = curl_get('https://api.weixin.qq.com/cgi-bin/account/getaccountbasicinfo?access_token='.$access_token);
			$info = json_decode($info,true);
			$head_image_url = \app\commons\Pic::uploadoss($info['head_image_info']['head_image_url']);
			Db::name('admin_setapp_wx')->where('aid',aid)->update(['headimg'=>$head_image_url]);
			return json(['status'=>1,'msg'=>'修改成功','url'=>(string)url('index')]);
		}
	}
	//设置小程序简介
	public function setsignature(){
		$access_token = \app\commons\Wechat::access_token(aid,'wx');
		$signature = input('post.set_signature');
		$data = array('signature'=>$signature);
		$rs = curl_post('https://api.weixin.qq.com/cgi-bin/account/modifysignature?access_token='.$access_token,jsonEncode($data));
		$rs = json_decode($rs,true);
		//dump($data);
		if($rs['errcode']!=0){
			return json(['status'=>0,'msg'=>\app\commons\Wechat::geterror($rs)]);
		}else{
			Db::name('admin_setapp_wx')->where('aid',aid)->update(['signature'=>$signature]);
			return json(['status'=>1,'msg'=>'修改成功','url'=>(string)url('index')]);
		}
	}
	public function closesearch(){
		$access_token = \app\commons\Wechat::access_token(aid,'wx');
		$data = array('status'=>1);
		$rs = curl_post('https://api.weixin.qq.com/wxa/changewxasearchstatus?access_token='.$access_token,jsonEncode($data));
		$rs = json_decode($rs,true);
		if($rs['errcode']!=0){
			return json(['status'=>0,'msg'=>\app\commons\Wechat::geterror($rs)]);
		}else{
			return json(['status'=>1,'msg'=>'修改成功','url'=>(string)url('index')]);
		}
	}
	public function opensearch(){
		$access_token = \app\commons\Wechat::access_token(aid,'wx');
		$data = array('status'=>0);
		$rs = curl_post('https://api.weixin.qq.com/wxa/changewxasearchstatus?access_token='.$access_token,jsonEncode($data));
		$rs = json_decode($rs,true);
		if($rs['errcode']!=0){
			return json(['status'=>0,'msg'=>\app\commons\Wechat::geterror($rs)]);
		}else{
			return json(['status'=>1,'msg'=>'修改成功','url'=>(string)url('index')]);
		}
	}
	public function poiadd(){
		$access_token = \app\commons\Wechat::access_token(aid,'wx');
		$related_proof_material = input('post.related_proof_material');
		$data = array();
		$data['related_name'] = $_POST['related_name'];
		$data['related_credential'] = $_POST['related_credential'];
		$data['related_address'] = $_POST['related_address'];
		if($related_proof_material){
			$data['related_proof_material'] = \app\commons\Wechat::pictomedia(aid,'wx',$related_proof_material);
		}
		$rs = curl_post('https://api.weixin.qq.com/wxa/addnearbypoi?access_token='.$access_token,jsonEncode($data));
		$rs = json_decode($rs,true);
		if($rs['errcode']!=0){
			return json(['status'=>0,'msg'=>\app\commons\Wechat::geterror($rs)]);
		}else{
			return json(['status'=>1,'msg'=>'已提交,请等待审核','url'=>(string)url('index')]);
		}
	}
	public function setpoist(){
		$access_token = \app\commons\Wechat::access_token(aid,'wx');
		$data = array();
		$data['poi_id'] = $_POST['poi_id'];
		$data['status'] = $_POST['st'];
		$rs = curl_post('https://api.weixin.qq.com/wxa/setnearbypoishowstatus?access_token='.$access_token,jsonEncode($data));
		$rs = json_decode($rs,true);
		if($rs['errcode']!=0){
			return json(['status'=>0,'msg'=>\app\commons\Wechat::geterror($rs)]);
		}else{
			return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
		}
	}
	//申请开通小程序直播
	public function applylive(){
		$access_token = \app\commons\Wechat::access_token(aid,'wx');
		$data = array();
		$data['action'] = 'apply';
		$rs = curl_post('https://api.weixin.qq.com/wxa/business/applyliveinfo?access_token='.$access_token,jsonEncode($data));
		$rs = json_decode($rs,true);
		if($rs['errcode']!=0){
			if($rs['errcode']==2){
				return json(['status'=>0,'msg'=>'小程序近90天没有存在支付行为，不能申请开通直播能力（数据生效时间为T+1，请耐心等待）']);
			}
			return json(['status'=>0,'msg'=>\app\commons\Wechat::geterror($rs)]);
		}else{
			return json(['status'=>1,'msg'=>'请小程序管理员在微信端点击消息通知卡片进入功能开通页面','url'=>(string)url('index')]);
		}
	}

	//设置用户隐私指引
	public function yinsi(){
		$access_token = \app\commons\Wechat::access_token(aid,'wx');
		if(request()->isPost()){
			$owner_setting = input('post.owner_setting/a');
			$setting_list = input('post.setting_list/a');
			
			$new_setting_list = [];
            foreach($setting_list as $k=>$v){
                if($v)
                    $new_setting_list[] = ['privacy_key'=>$k,'privacy_text'=>$v];
            }
			$postdata = [];
			$postdata['owner_setting'] = $owner_setting;
			$postdata['setting_list'] = $new_setting_list;
			$rs = curl_post('https://api.weixin.qq.com/cgi-bin/component/setprivacysetting?access_token='.$access_token,jsonEncode($postdata));
			$rs = json_decode($rs,true);
			if($rs['errcode']!=0){
				return json(['status'=>0,'msg'=>\app\commons\Wechat::geterror($rs)]);
			}else{
				return json(['status'=>1,'msg'=>'设置成功','url'=>true]);
			}
		}
        //https://developers.weixin.qq.com/doc/oplatform/Third-party_Platforms/2.0/api/privacy_config/get_privacy_setting.html privacy_ver	int	否	1表示现网版本，即，传1则该接口返回的内容是现网版本的；2表示开发版，即，传2则该接口返回的内容是开发版本的。默认是2。
		$rs = curl_post('https://api.weixin.qq.com/cgi-bin/component/getprivacysetting?access_token='.$access_token,'{"privacy_ver":1}');
		$rs = json_decode($rs,true);
//        if(input('get.tt')){
//            dd($rs);
//        }
		if($rs['errcode']!=0){
			return json(['status'=>0,'msg'=>\app\commons\Wechat::geterror($rs)]);
		}

		$privacy_list = $rs['privacy_list'];
		$setting_list = $rs['setting_list'];
		$owner_setting = $rs['owner_setting'];
		$privacy_desc_list = $rs['privacy_desc']['privacy_desc_list'];

		$privacyArr = [];
		if($privacy_list){
			foreach($privacy_list as $k=>$v){
				$privacy_text = '';
				$privacy_desc = '';
				foreach($setting_list as $k2=>$v2){
					if($v2['privacy_key'] == $v){
						$privacy_text = $v2['privacy_text'];
					}
				}
				foreach($privacy_desc_list as $k3=>$v3){
					if($v3['privacy_key'] == $v){
						$privacy_desc = $v3['privacy_desc'];
					}
				}
				$privacyArr[$v] = ['desc'=>$privacy_desc,'text'=>$privacy_text];
			}
		}
        $newKey = 'privacy_key';
        $setting_listFormat = collect($setting_list)->dictionary(null, $newKey);
        $privacy_list_values = array_values($privacy_list);
        foreach($privacy_desc_list as $k3=>$v3){
            if(!in_array($v3['privacy_key'],$privacy_list_values)){
                $privacyArr[$v3['privacy_key']] = ['desc'=>$v3['privacy_desc'],'text'=>$setting_listFormat[$v3['privacy_key']]['privacy_text'] ?? ''];
            }
        }
//        if(input('get.pp')){
//            dd($privacyArr);
//        }
		View::assign('privacyArr',$privacyArr);
		View::assign('owner_setting',$owner_setting);
		return View::fetch();
	}

	//申请地理位置接口
	public function privacyInterfaceList(){
		
		if(request()->isAjax()){
			$access_token = \app\commons\Wechat::access_token(aid,'wx');
			$rs = curl_get('https://api.weixin.qq.com/wxa/security/get_privacy_interface?access_token='.$access_token);
			$rs = json_decode($rs,true);
			if($rs['errcode']!=0){
				return json(['status'=>0,'msg'=>\app\commons\Wechat::geterror($rs)]);
			}
			$interface_list = $rs['interface_list'];
			$chooseLocation = [];
			$chooseAddress = [];
			$getLocation = [];
			$new_interface_list = [];
			foreach($interface_list as $k=>$v){
				if($v['api_name'] == 'wx.chooseLocation'){
					$chooseLocation = $v;
				}elseif($v['api_name'] == 'wx.chooseAddress'){
					$chooseAddress = $v;
				}elseif($v['api_name'] == 'wx.getLocation'){
					$getLocation = $v;
				}else{
					$new_interface_list[] = $v;
				}
			}
			if($getLocation){
				$new_interface_list = array_merge([$getLocation],$new_interface_list);
			}
			if($chooseAddress){
				$new_interface_list = array_merge([$chooseAddress],$new_interface_list);
			}
			if($chooseLocation){
				$new_interface_list = array_merge([$chooseLocation],$new_interface_list);
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>count($new_interface_list),'data'=>$new_interface_list]);
		}
		return View::fetch();
	}
	public function applyPrivacyInterface(){
		$info = input('post.info/a');
		$postdata = [];
		$postdata['api_name'] = $info['api_name'];
		$postdata['content'] = $info['content'];
		$postdata['pic_list'] = $info['pic_list'] ? explode(',',$info['pic_list']) : [];
		$postdata['video_list'] = $info['video'] ? [$info['video']] : [];
		$postdata['url_list'] = $info['url'] ? [$info['url']] : [];
		//var_dump($postdata);
		if(!$postdata['content']) return json(['status'=>0,'msg'=>'请填写申请原因']);
		$access_token = \app\commons\Wechat::access_token(aid,'wx');
		$rs = curl_post('https://api.weixin.qq.com/wxa/security/apply_privacy_interface?access_token='.$access_token,jsonEncode($postdata));
		$rs = json_decode($rs,true);
		if($rs['errcode']!=0){
			return json(['status'=>0,'msg'=>\app\commons\Wechat::geterror($rs)]);
		}
		return json(['status'=>1,'msg'=>'提交成功']);
	}



  /**
   * 流程：是否人脸核身，否-》发起人脸核身
   * 是
   * 申请备案 https://developers.weixin.qq.com/doc/oplatform/openApi/OpenApiDoc/miniprogram-management/record/applyIcpFiling.html
   */
    public function icp(){
      $appinfo = System::appinfo(aid,'wx');
      $appid = $appinfo['appid'];
      $info = Db::name('admin_wxicp')->where('aid', aid)->where('appid', $appid)->find();
      if(request()->isPost()){
        //验证人脸核身结果
        if($info['face_status'] != 3){
          return json(['status'=>0, 'msg'=>'请先完成人脸核身再提交备案']);
        }
        if($info['face_finish_time'] < time()){
          return json(['status'=>0, 'msg'=>'抱歉，人脸核身结果已过期，请重新核验']);
        }
        $post_data = input('post.');
        //
        $icp_data = $this->formatData($post_data);
//          \think\facade\Log::write([
//           'type'=>'申请备案1',
//           'data'=>$icp_data
//        ]);

        //根据历史记录，判断是否需要重新上传备案资料
        $media_res = Wechat::setMediaId($info, $icp_data, aid, $appid);
        if($media_res['status'] == 0){
          return json(['status'=>0, 'msg'=>$media_res['msg']]);
        }
        unset($icp_data['nrlx_details']);
        Db::name("admin_wxicp")->where('aid', aid)->where('appid', $appid)->update($icp_data);
        //提交微信审核
        $apply_res = Wechat::applyIcp(aid);
        if($apply_res['status'] == 1){
          System::plog("提交小程序备案操作成功，等待审核", aid);
          return json(['status'=>1,'msg'=>'提交成功，等待审核']);
        }else{
          System::plog("提交小程序备案失败", aid);
          return json(['status'=>0,'msg'=>$apply_res['msg'], 'hints'=>$apply_res['hints']]);
        }
      }
      $ContentTypes = \app\commons\Wechat::queryIcpServiceContentTypes(aid);
      View::assign('ContentTypes',$this->formatContentTypes($ContentTypes));
      $CertificateTypes = \app\commons\Wechat::queryIcpCertificateTypes(aid);
      View::assign('CertificateTypes',$CertificateTypes);
      $NrlxTypes = \app\commons\Wechat::queryIcpNrlxTypes(aid);//前置审批项
      View::assign('NrlxTypes',$NrlxTypes);
      $SubjectTypes = \app\commons\Wechat::queryIcpSubjectTypes(aid);
      View::assign('SubjectTypes',$SubjectTypes);

      $subject_certificate_types = [];
      if($info['subject_certificate_type']){
        foreach ($CertificateTypes as $certificateType) {
          if($certificateType['subject_type'] == $info['subject_type']){
            $subject_certificate_types[] = $certificateType;
          }
        }
      }
      View::assign('subject_certificate_types',$subject_certificate_types);
      //获取已提交信息
      $icp_status = Wechat::icpStatus(aid, $appid);
      View::assign('icp_status',$icp_status);

      $face_status_text = [
        -1=>'待发起',
        0=>'等待管理员开始核验',
        1=>'核验进行中',
        2=>'核验失败，重新发起',
        3=>'核验已通过',
        4=>'已过期，请重新发起'
      ];
      View::assign("face_status_text",$face_status_text);
      //
      if(!$info){
        Db::name("admin_wxicp")->insert([
          "create_time"=>time(),
          "aid"=>aid,
          "appid"=>$appid,
          "nrlx_details"=>jsonEncode([]),
          "face_status"=>-1
        ]);
        $info = Db::name('admin_wxicp')->where('aid', aid)->where("appid", $appid)->find();
      }
      if($info['face_finish_time']<time()){
        $info['face_status'] = 4;
        $face_percent="0/4";
      }else{
        $face_percent = ($info['face_status']+1).'/4';
      }
      View::assign('face_percent', $face_percent);
      View::assign('info',$info);
      $province_data = file_get_contents(ROOT_PATH.'/static/area_wechat.json');
      View::assign("province_data", $province_data);
      return View::fetch();
    }

    //发起人脸核身
  // https://developers.weixin.qq.com/doc/oplatform/openApi/OpenApiDoc/miniprogram-management/record/createIcpVerifyTask.html
  public function launchCheck()
  {
    $aid = aid;
    $info = Db::name('admin_wxicp')->where('aid', aid)->find();
    if($info['face_is_finish'] == 0 && $info['face_send_time']+86400>time() && $info['face_status']<2){
      return json(['status'=>0, 'msg'=>'微信核验通知在有效期内，无需重复发起，请点击通知进行核身']);
    }
    if ($info['face_status'] == 3 && $info['face_finish_time'] > time()) {
      return json(['status'=>0, 'msg'=>'人脸核身已通过，无需再次发起']);
    }
    $data = Wechat::launchCheck($aid);
    if($data['status'] == 0){
      return json($data);
    }else{
      Db::name("admin_wxicp")->where('aid', aid)->update([
        "face_verify_task_id"=>$data['task_id'],
        "face_is_finish"=>0,
        "face_status"=>0,
        "face_send_time"=>time(),
        "face_finish_time"=>time()+3*86400
      ]);
      return json(['status'=>1, 'msg'=>'核验通知已发送至管理员微信，请及时处理，通知有效期24小时']);
    }
  }

  //拉取核身结果
  // https://developers.weixin.qq.com/doc/oplatform/openApi/OpenApiDoc/miniprogram-management/record/queryIcpVerifyTask.html
  public function checkFace()
  {
    $aid = aid;
    $info = Db::name('admin_wxicp')->where('aid', aid)->find();
    if (!$info['face_verify_task_id'] || ($info['face_status'] != 3 && $info['face_send_time'] + 86400 < time())) {
      //未发起过 或者通知已经过期
      return json(['status' => 2, 'msg' => '未发起', 'percent'=>"0/4"]);
    }
    if($info['face_status'] == 3){
      return json(['status' => 3, 'msg' => '核验已通过', 'percent'=>"4/4"]);
    }
    if ($info['face_verify_task_id'] && $info['face_status'] != 3 && $info['face_finish_time'] < time()) {
      //未发起过 或者通知已经过期
      return json(['status' => 2, 'msg' => '核验已过期，请重新发起', 'percent'=>"0/4"]);
    }
    $task_id = $info['face_verify_task_id'];
    $data = Wechat::checkFace($aid, $task_id);
    if($data['status'] == 1){
      $face_status = $data['data']['face_status'];
//      if($data['data']['is_finish']){
        Db::name("admin_wxicp")->where('aid', aid)->update([
          "face_is_finish"=>intval($data['data']['is_finish']),
          "face_status"=>$face_status,
        ]);
//      }
      $face_status_text = [
        0=>'等待管理员开始核验',
        1=>'核验进行中',
        2=>'核验失败，重新发起',
        3=>'核验已通过'
      ];
      return json(['status'=>1, 'msg'=>$face_status_text[$face_status]??'发起核验', 'percent'=>($face_status+1)."/4"]);
    }else{
      return json(['status'=>0, 'msg'=>'']);
    }
  }

    private function formatContentTypes($ContentTypes)
    {
        $newArr = [];
        foreach ($ContentTypes as $item){
            if($item['parent_type'] == 0)
                $newArr[$item['type']] = $item;
        }
        foreach ($ContentTypes as $item){
            if($item['parent_type'] > 0)
                $newArr[$item['parent_type']]['child'][] = $item;
        }
        return $newArr;
    }

  private function formatData($data)
  {
    $newData = [];
    foreach ($data['subject'] as $k=>$item){
      $newData["subject_{$k}"] = $item;
    }
    foreach ($data['principal'] as $k=>$item){
      $newData["principal_{$k}"] = $item;
    }
    if (!isset($newData['principal_certificate_validity_date_cq']))
      $newData['principal_certificate_validity_date_cq'] = 0;
    foreach ($data['legal_person'] as $k=>$item){
      $newData["legal_person_{$k}"] = $item;
    }
    $newData['service_content_types'] = $data['app']['service_content_types'];
    $nrlx_detail = [];
    if(isset($data['app']['nrlx_details']['type'])){
      foreach ($data['app']['nrlx_details']['type'] as $k=>$v) {
        $nrlx_detail[] = [
          'type'=>$data['app']['nrlx_details']['type'][$k],
          'code'=>$data['app']['nrlx_details']['code'][$k],
          'media'=>$data['app']['nrlx_details']['media'][$k],
        ];
      }
    }
    $newData['nrlx_details'] = $nrlx_detail;
    $newData['app_comment'] = $data['app']['comment'];
    foreach ($data['manager'] as $k=>$item) {
      $newData["manager_{$k}"] = $item;
    }
    if (!isset($newData['manager_certificate_validity_date_cq']))
      $newData['manager_certificate_validity_date_cq'] = 0;
    $newData['commitment_letter'] = $data['commitment_letter'];
    $newData['business_name_change_letter'] = $data['business_name_change_letter'];
    $newData['applets_other_materials'] = $data['applets_other_materials'];
    return $newData;
  }

  public function mediaData()
  {
    $media_id = input('media_id');
    $data = Wechat::getIcpMedia(aid, $media_id);
    file_put_contents(ROOT_PATH."/upload/".aid."/{$media_id}.png", $data);
    exit();
  }

}