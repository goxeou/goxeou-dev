<?php


// +----------------------------------------------------------------------
// | 随行付申请入驻
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class SxpayIncome extends Common
{
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
			$where[] = ['delete','=',0];
			$count = 0 + db('sxpay_income')->where($where)->count();
			$data = db('sxpay_income')->where($where)->page($page,$limit)->order($order)->select();
			
			return ['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data];
		}
		return View::fetch();
	}
	public function apply(){
		$id = input('param.id/d');
		if($id){
			$info = Db::name('sxpay_income')->where(['aid'=>aid,'id'=>$id])->find();
		}else{
			$info = [];
		}
		if(request()->isPost()){
			$data = input('post.info/a');
			if(!$data['identity_id_card_valid_time_cq']){
				$data['identity_id_card_valid_time_cq'] = 0;
			}else{
				$data['identity_id_card_valid_time_cq'] = 1;
			}
			$data['aid'] = aid;
			$data['createtime'] = time();
			if($info){
				Db::name('sxpay_income')->where(['id'=>$info['id']])->update($data);
				$id = $info['id'];
			}else{
				$id = Db::name('sxpay_income')->insertGetId($data);
			}
			
			$reqData = [];
			if($info && $info['business_code']){
				$reqData['mno'] = $info['business_code'];
			}
			$reqData['mecDisNm'] = $data['merchant_shortname'];
			$reqData['mblNo'] = $data['contact_mobile'];
			$reqData['operationalType'] = '01';
			if($data['subject_type'] == 'SUBJECT_TYPE_MICRO'){
				$reqData['haveLicenseNo'] = '01';
			}elseif($data['subject_type'] == 'SUBJECT_TYPE_INDIVIDUAL'){
				$reqData['haveLicenseNo'] = '02';
			}elseif($data['subject_type'] == 'SUBJECT_TYPE_ENTERPRISE'){
				$reqData['haveLicenseNo'] = '03';
			}
			$reqData['mecTypeFlag'] = '00';
			$reqData['cprRegAddr'] = $data['store_street'];
			$reqData['regProvCd'] = $this->getareacode($data['store_province']);
			$reqData['regCityCd'] = $this->getareacode($data['store_province'],$data['store_city']);
			$reqData['regDistCd'] = $this->getareacode($data['store_province'],$data['store_city'],$data['store_area']);
			$reqData['mccCd'] = $data['mccCd'];
			$reqData['csTelNo'] = $data['service_phone'];
			$reqData['email'] = $data['contact_email'];
			$reqData['settleType'] = '04';
			
			$reqData['callbackUrl'] = PRE_URL.'/notify.php';
			$reqData['checkCallbackUrl'] = PRE_URL.'/notify.php';

			if($data['subject_type'] != 'SUBJECT_TYPE_MICRO'){
				$reqData['cprRegNmCn'] = $data['business_merchant_name'];
				$reqData['registCode'] = $data['business_license_number'];
			}
			if($data['subject_type'] == 'SUBJECT_TYPE_ENTERPRISE'){
				$reqData['licenseMatch'] = '00';
			}
			
			$reqData['identityName'] = $data['identity_id_card_name'];
			$reqData['identityTyp'] = '00';
			$reqData['identityNo'] = $data['identity_id_card_number'];
			$reqData['legalPersonLicStt'] = str_replace('-','',$data['identity_id_card_valid_time1']);
			if($data['identity_id_card_valid_time_cq'] == 1){
				$reqData['legalPersonLicEnt'] = '29991231';
			}else{
				$reqData['legalPersonLicEnt'] = str_replace('-','',$data['identity_id_card_valid_time2']);
			}
			$reqData['actNm'] = $data['jiesuan_account_name'];
			$reqData['actTyp'] = ($data['jiesuan_bank_account_type'] == 'BANK_ACCOUNT_TYPE_CORPORATE' ? '00' : '01');
			if($reqData['actTyp'] == '01'){
				$reqData['stmManIdNo'] = $reqData['identityNo'];
				$reqData['accountLicStt'] = $reqData['legalPersonLicStt'];
				$reqData['accountLicEnt'] = $reqData['legalPersonLicEnt'];
			}
			$reqData['actNo'] = $data['jiesuan_account_number'];
			$reqData['lbnkNo'] = explode('-',$data['jiesuan_bank_name'])[0];
			$reqData['lbnkNm'] = explode('-',$data['jiesuan_bank_name'])[1];

			if($data['subject_type'] != 'SUBJECT_TYPE_MICRO'){
				$reqData['licensePic'] = \app\customs\Sxpay::uploadimg($data['business_license_copy'],'13');
                if($reqData['licensePic']['status'] === 0) {
                    return json($reqData['licensePic']);
                }
			}
			$reqData['legalPersonidPositivePic'] = \app\customs\Sxpay::uploadimg($data['identity_id_card_copy'],'02');
            if($reqData['legalPersonidPositivePic']['status'] === 0) {
                return json($reqData['legalPersonidPositivePic']);
            }
			$reqData['legalPersonidOppositePic'] = \app\customs\Sxpay::uploadimg($data['identity_id_card_national'],'03');
            if($reqData['legalPersonidOppositePic']['status'] === 0) {
                return json($reqData['legalPersonidOppositePic']);
            }
			if($reqData['actTyp'] == '00'){
				$reqData['openingAccountLicensePic'] = \app\customs\Sxpay::uploadimg($data['account_license_pic'],'04');
                if($reqData['openingAccountLicensePic']['status'] === 0) {
                    return json($reqData['openingAccountLicensePic']);
                }
			}else{
				$reqData['bankCardPositivePic'] = \app\customs\Sxpay::uploadimg($data['bank_card_pic'],'05');
                if($reqData['bankCardPositivePic']['status'] === 0) {
                    return json($reqData['bankCardPositivePic']);
                }
			}
			$reqData['storePic'] = \app\customs\Sxpay::uploadimg($data['store_entrance_pic'],'10');
            if($reqData['storePic']['status'] === 0) {
                return json($reqData['storePic']);
            }
			$reqData['insideScenePic'] = \app\customs\Sxpay::uploadimg($data['indoor_pic'],'11');
            if($reqData['insideScenePic']['status'] === 0) {
                return json($reqData['insideScenePic']);
            }

			if($data['store_other_pics']){
				$store_other_pics = explode(',',$data['store_other_pics']);
				$reqData['otherMaterialPictureFour'] = \app\customs\Sxpay::uploadimg($store_other_pics[0],'19');
                if($reqData['otherMaterialPictureFour']['status'] === 0) {
                    return json($reqData['otherMaterialPictureFour']);
                }
				if($store_other_pics[1]){
					$reqData['otherMaterialPictureFive'] = \app\customs\Sxpay::uploadimg($store_other_pics[1],'20');
                    if($reqData['otherMaterialPictureFive']['status'] === 0) {
                        return json($reqData['otherMaterialPictureFive']);
                    }
				}
				if($store_other_pics[2]){
					$reqData['otherMaterialPictureThree'] = \app\customs\Sxpay::uploadimg($store_other_pics[2],'18');
                    if($reqData['otherMaterialPictureThree']['status'] === 0) {
                        return json($reqData['otherMaterialPictureThree']);
                    }
				}
			}
			//\think\facade\Log::write($reqData);
			if($info && $info['business_code']){
				$rs = \app\customs\Sxpay::modify(aid,$reqData,$info['mchkey']);
			}else{
				$rs = \app\customs\Sxpay::income(aid,$reqData);
			}
			if($rs['status']==1){
				if($info && $info['business_code']){
					$data['taskStatus_edit'] = 0;
					$data['suggestion_edit'] = '';
					if($rs['data']['applicationId']){
						$data['applicationId_edit'] = $rs['data']['applicationId'];
					}
					if($rs['data']['mchkey']){
						$data['mchkey'] = $rs['data']['mchkey'];
					}
				}else{
					$data['taskStatus'] = 0;
					$data['suggestion'] = '';
					if($rs['data']['mno']){
						$data['business_code'] = $rs['data']['mno'];
					}
					if($rs['data']['applicationId']){
						$data['applicationId'] = $rs['data']['applicationId'];
					}
					if($rs['data']['mchkey']){
						$data['mchkey'] = $rs['data']['mchkey'];
					}
				}
			}else{
				
			}
			Db::name('sxpay_income')->where(['id'=>$id])->update($data);
			return json($rs);
		}

		$mccCdArr = \app\customs\Sxpay::mccCategoryConf();

        $dbsxpayset = \app\customs\Sxpay::getMerchantSet(aid);
        if($dbsxpayset && $dbsxpayset['orgId']){
            $feepercent = $dbsxpayset['feepercent'].'%';
        }else{
            $feepercent = '0.38%';
        }

		View::assign('info',$info);
		View::assign('feepercent',$feepercent);
		View::assign('mccCdArr',$mccCdArr);
		View::assign('banklist',\app\customs\Sxpay::getBankList());
		return View::fetch();
	}
	//入驻结果查询
	public function applyQuery(){
		$id = input('post.id/d');
		$info = Db::name('sxpay_income')->where('aid',aid)->where('id',$id)->find();
		$reqData = [];
		$reqData['applicationId'] = $info['applicationId'];
		$rs = \app\customs\Sxpay::applyQuery(aid,$reqData,$info['mchkey']);
		if($rs['status']==0){
			return json($rs);
		}
		$data = $rs['data'];
		$repoInfo = $data['repoInfo'];
		$submchid = '';
		$zfbmchid = '';
		foreach($repoInfo as $v){
			if($v['childNoType'] == 'WX'){
				$submchid = $v['childNo'];
			}
			if($v['childNoType'] == 'ZFB'){
				$zfbmchid = $v['childNo'];
			}
		}
		Db::name('sxpay_income')->where('id',$id)->update(['taskStatus'=>$data['taskStatus'],'suggestion'=>$data['suggestion'],'submchid'=>$submchid,'zfbmchid'=>$zfbmchid]);
		return json($rs);
	}
	
	//修改结果查询
	public function modifyQuery(){
		$id = input('post.id/d');
		$info = Db::name('sxpay_income')->where('aid',aid)->where('id',$id)->find();
		$reqData = [];
		$reqData['applicationId'] = $info['applicationId_edit'];
		$rs = \app\customs\Sxpay::modifyQuery(aid,$reqData,$info['mchkey']);
		if($rs['status']==0){
			return json($rs);
		}
		$data = $rs['data'];
		Db::name('sxpay_income')->where('id',$id)->update(['taskStatus_edit'=>$data['taskStatus'],'suggestion_edit'=>$data['suggestion']]);
		return json($rs);
	}

	//绑定appid
	public function bandappid(){
		$id = input('post.id/d');
		$accountType = input('post.bangdAccountType');
		$subAppid = input('post.bindSubAppid');
		$info = Db::name('sxpay_income')->where('aid',aid)->where('id',$id)->find();
		$reqData = [];
		$reqData['mno'] = $info['business_code'];
		$reqData['subMchId'] = $info['submchid'];
		$reqData['type'] = '01';
		$reqData['subAppid'] = $subAppid;
		$reqData['accountType'] = $accountType;
		$rs = \app\customs\Sxpay::addConf(aid,$reqData,$info['mchkey']);
		return json($rs);
	}
	//绑定jsapi授权目录
	public function setjsapi(){
		$id = input('post.id/d');
		$accountType = input('post.bangdAccountType');
		$jsapiPath = input('post.jsapiPath');
		$info = Db::name('sxpay_income')->where('aid',aid)->where('id',$id)->find();
		$reqData = [];
		$reqData['mno'] = $info['business_code'];
		$reqData['subMchId'] = $info['submchid'];
		$reqData['type'] = '03';
		$reqData['jsapiPath'] = $jsapiPath;
		$rs = \app\customs\Sxpay::addConf(aid,$reqData,$info['mchkey']);
		return json($rs);
	}
	//查看配置
	public function viewConf(){
		$id = input('post.id/d');
		$info = Db::name('sxpay_income')->where('aid',aid)->where('id',$id)->find();
		$reqData = [];
		$reqData['mno'] = $info['business_code'];
		$reqData['subMchId'] = $info['submchid'];
		$rs = \app\customs\Sxpay::viewConf(aid,$reqData,$info['mchkey']);
		return json($rs);
	}
	//签署协议
	public function signxieyi(){
		$id = input('param.id/d');
		$info = Db::name('sxpay_income')->where('aid',aid)->where('id',$id)->find();
		$reqData = [];
		$reqData['mno'] = $info['business_code'];
		$reqData['signType'] = '00';
		$rs = \app\customs\Sxpay::signxieyi(aid,$reqData,$info['mchkey']);
		if($rs['status'] == 1){
			return redirect($rs['signUrl']);
		}
		die($rs['msg']);
	}
	//实名认证
	public function shiming(){
		$id = input('post.id/d');
		$type = input('post.type/d')?input('post.type/d'):0;
		$info = Db::name('sxpay_income')->where('aid',aid)->where('id',$id)->find();
		if(!$type){
			if($info['shiming_status'] != 0){
				return json(['status'=>1,'shiming_status'=>$info['shiming_status'],'shiming_qrurl'=>$info['shiming_qrurl']]);
			}
		}else if($type == 1){
			if($info['alishiming_status'] != 0){
				return json(['status'=>1,'shiming_status'=>$info['alishiming_status'],'shiming_qrurl'=>$info['alishiming_qrurl']]);
			}
		}
		$reqData = [];
		$reqData['mno'] = $info['business_code'];
		$reqData['backUrl'] = PRE_URL.'/notify.php';
		$rs = \app\customs\Sxpay::shiming(aid,$reqData,$info['mchkey'],$type);
		if($rs['status']==1){
			if(!$type){
				Db::name('sxpay_income')->where('aid',aid)->where('id',$id)->update(['shiming_status'=>1]);
			}else if($type == 1){
				Db::name('sxpay_income')->where('aid',aid)->where('id',$id)->update(['alishiming_status'=>1]);
			}
		}
		$rs['shiming_status'] = 1;
		return json($rs);
	}
	//实名认证结果查询
	public function shimingQuery(){
		$id = input('post.id/d');
		$info = Db::name('sxpay_income')->where('aid',aid)->where('id',$id)->find();
		$reqData = [];
		$reqData['mno'] = $info['business_code'];

		$type = input('post.type/d')?input('post.type/d'):0;
		$rs = \app\customs\Sxpay::shimingQuery(aid,$reqData,$info['mchkey'],$type);

		if($rs['status']==1){
			$shiming_status = 1;
			if($rs['data']['idenStatus'] == 'APPLYMENT_STATE_WAITTING_FOR_AUDIT'){
				$shiming_status = 1;
			}elseif($rs['data']['idenStatus'] == 'APPLYMENT_STATE_WAITTING_FOR_CONFIRM_CONTACT'){
				$shiming_status = 2;
			}elseif($rs['data']['idenStatus'] == 'APPLYMENT_STATE_WAITTING_FOR_CONFIRM_LEGALPERSON'){
				$shiming_status = 3;
			}elseif($rs['data']['idenStatus'] == 'APPLYMENT_STATE_PASSED'){
				$shiming_status = 4;
			}elseif($rs['data']['idenStatus'] == 'APPLYMENT_STATE_REJECTED'){
				$shiming_status = 5;
			}elseif($rs['data']['idenStatus'] == 'APPLYMENT_STATE_FREEZED'){
				$shiming_status = 6;
			}elseif($rs['data']['idenStatus'] == 'APPLYMENT_STATE_CANCELED'){
				$shiming_status = 7;
			}elseif($rs['data']['idenStatus'] == 'APPLYMENT_STATE_OPENACCOUNT'){
				$shiming_status = 8;
			}
			if(!$type){
				Db::name('sxpay_income')->where('id',$id)->update(['shiming_status'=>$shiming_status,'shiming_qrurl'=>$rs['data']['infoQrcode']]);
			}else if($type == 1){
				Db::name('sxpay_income')->where('id',$id)->update(['alishiming_status'=>$shiming_status,'alishiming_qrurl'=>$rs['data']['infoQrcode']]);
			}
			$rs['shiming_status'] = $shiming_status;
			$rs['shiming_qrurl'] = $rs['data']['infoQrcode'];
		}
		return json($rs);
	}
	//查看密钥
	public function viewMchkey(){
		$id = input('post.id/d');
		$info = Db::name('sxpay_income')->where('aid',aid)->where('id',$id)->find();
		return json(['status'=>1,'data'=>['mchkey'=>$info['mchkey'],'business_code'=>$info['business_code']]]);
	}
	//重置密钥
	public function updateMchkey(){
		$id = input('post.id/d');
		$info = Db::name('sxpay_income')->where('aid',aid)->where('id',$id)->find();
		$reqData = [];
		$reqData['mno'] = $info['business_code'];
		$reqData['applicationId'] = $info['applicationId'];
		$reqData['applicationId_edit'] = $info['applicationId_edit'];
		$rs = \app\customs\Sxpay::updateMchkey(aid,$reqData,$info['mchkey']);
		if($rs['status'] == 0) return json($rs);
		Db::name('sxpay_income')->where('aid',aid)->where('id',$id)->update(['mchkey'=>$rs['data']['mchkey']]);
		return json(['status'=>1,'msg'=>'重置成功']);
	}
	//交易记录
    public function paylog(){
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
			if(input('param.mchid')){
				$where[] = ['mch_id','=',input('param.mchid')];
			}else{
				$mchidArr = Db::name('sxpay_income')->where('aid',aid)->where("business_code != '' && business_code is not null")->column('business_code');
				if($mchidArr){
					$where[] = ['mch_id','in',$mchidArr];
				}else{
					return json(['code'=>0,'msg'=>'查询成功','count'=>0,'data'=>[]]);
				}
			}
			if(input('param.ctime') ){
				$ctime = explode(' ~ ',input('param.ctime'));
				$where[] = ['createtime','>=',strtotime($ctime[0])];
				$where[] = ['createtime','<',strtotime($ctime[1]) + 86400];
			}

			$type = input('post.type/d')?input('post.type/d'):0;
			if(!$type){
				$sqlname = 'wxpay_log';
			}else{
				$sqlname = 'alipay_log';
			}

			$count = 0 + Db::name($sqlname)->where($where)->count();
			$data = Db::name($sqlname)->where($where)->page($page,$limit)->order($order)->select()->toArray();
			foreach($data as $k=>$v){
				$member = Db::name('member')->where('id',$v['mid'])->find();
				$data[$k]['nickname'] = $member['nickname'];
				$data[$k]['headimg'] = $member['headimg'];
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
    }
	//删除
	public function paylogdel(){
		$ids = input('post.ids/a');
		if(!$ids) $ids = array(input('post.id/d'));
		Db::name('wxpay_log')->where('aid',aid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('交易记录删除'.implode(',',$ids),1);
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	
	//删除
	public function del(){
		$ids = input('post.ids/a');
		Db::name('sxpay_income')->where('aid',aid)->where('id','in',$ids)->update(['delete'=>1]);
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	
	//获取分支行
	function getjiesuanbanklist(){
		$reqData = [];
		$reqData['account_bank'] = input('post.account_bank');
		$reqData['bank_province'] = input('post.bank_province');
		$reqData['bank_city'] = input('post.bank_city');
		$rs = \app\customs\Sxpay::getjiesuanbanklist(aid,$reqData);
		return json($rs);
	}
	//获取省市区编码
	function getareacode($province,$city='',$area=''){
		$reqData = [];
		$reqData['province'] = $province;
		$reqData['city'] = $city;
		$reqData['area'] = $area;
		$rs = \app\customs\Sxpay::getareacode(aid,$reqData);
		return $rs;
	}
}