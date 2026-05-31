<?php


//custom_file(sxpay_fenzhang)
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class SxpayFenzhang extends Common
{
    public function initialize(){
		parent::initialize();
		if(bid > 0) showmsg('无访问权限');
	}
	//列表
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
			if(input('param.business_code')) $where[] = ['business_code','like','%'.input('param.business_code').'%'];
			if(input('?param.status') && input('param.status')!=='') $where[] = ['status','=',input('param.status')];
			//dump($where);
			$count = 0 + Db::name('sxpay_fenzhang')->where($where)->count();
			$data = Db::name('sxpay_fenzhang')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			foreach($data as $k=>$v){
				if($v['fenzhangdata']){
					$data[$k]['fenzhangdata'] = json_decode($v['fenzhangdata'],true);
				}else{
					$data[$k]['fenzhangdata'] = [];
				}
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
    }
	//编辑
	public function edit(){
		if(input('param.id')){
			$info = Db::name('sxpay_fenzhang')->where('aid',aid)->where('id',input('param.id/d'))->find();
		}else{
			$info = array('id'=>'');
		}
//		$pcatelist = Db::name('sxpay_fenzhang')->where('aid',aid)->order('sort desc,id')->select()->toArray();
		View::assign('info',$info);
		return View::fetch();
	}
	//保存
	public function save(){
		$info = input('post.info/a');
		$hasun = Db::name('sxpay_fenzhang')->where('id','<>',$info['id'])->where('business_code',$info['business_code'])->find();
		if($hasun){
			return json(['status'=>0,'msg'=>'该商户编号已被存在分账设置记录']);
		}

		$business_codes = input('post.business_code/a');
		$percents = input('post.percent/a');
        $split_cycle = input('post.split_cycle/a');
        $relation_ship = input('post.relation_ship/a');
        $scenes = input('post.scenes/a');
        $apply = input('post.apply');
		$fenzhangdata = [];
		foreach($business_codes as $k=>$business_code){
			$fenzhangdata[] = array(
				'business_code'=>$business_code,
				'percent'=>$percents[$k],
                'split_cycle'=>$split_cycle[$k],
                'relation_ship'=>$relation_ship[$k],
                'scenes'=>$scenes[$k]
			);
		}
		$info['fenzhangdata'] = json_encode($fenzhangdata,JSON_UNESCAPED_UNICODE);

		if($info['id']){
			if($info['status'] == 1){
				$mchkey = Db::name('sxpay_income')->where('aid',aid)->where('business_code',$info['business_code'])->value('mchkey');
				$reqData = [];
				$reqData['mno'] = $info['business_code'];
				$reqData['mnoArray'] = implode(',',$business_codes);
				$rs = \app\customs\Sxpay::setMnoArrayfz(aid,$reqData,$mchkey);//https://paas.tianquetech.com/docs/#/api/fzsz
				if($rs['status'] == 0){
					return json(['status'=>0,'msg'=>$rs['msg']]);
				}
                //商户特殊申请提交 接口：https://paas.tianquetech.com/docs/#/api/shtssqtj
                if($apply == 1){
                    $rs = $this->specialApplication($info,$mchkey,$fenzhangdata);
                    if($rs['status'] == 0){
                        return json(['status'=>0,'msg'=>$rs['msg']]);
                    }
                }
			}

			Db::name('sxpay_fenzhang')->where('aid',aid)->where('id',$info['id'])->update($info);
		}else{
			$info['aid'] = aid;
			$info['createtime'] = time();
			$id = Db::name('sxpay_fenzhang')->insertGetId($info);

            //商户特殊申请提交 接口：https://paas.tianquetech.com/docs/#/api/shtssqtj
            if($apply == 1){
                $info['id'] = $id;
                $mchkey = Db::name('sxpay_income')->where('aid',aid)->where('business_code',$info['business_code'])->value('mchkey');
                $rs = $this->specialApplication($info,$mchkey,$fenzhangdata);
                if($rs['status'] == 0){
                    return json(['status'=>0,'msg'=>$rs['msg']]);
                }
            }
		}
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}
	//删除
	public function del(){
		$ids = input('post.ids/a');
		Db::name('sxpay_fenzhang')->where('aid',aid)->where('id','in',$ids)->delete();
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	//开启
	public function setst(){
		$id = input('post.id/d');
		$st = input('post.st/d');
		$info = Db::name('sxpay_fenzhang')->where('aid',aid)->where('id',$id)->find();
		if($st == 1){
			$fenzhangdata = json_decode($info['fenzhangdata'],true);
			$business_codes = [];
			foreach($fenzhangdata as $fz){
				$business_codes[] = $fz['business_code'];
			}
			$mchkey = Db::name('sxpay_income')->where('aid',aid)->where('business_code',$info['business_code'])->value('mchkey');
			$reqData = [];
			$reqData['mno'] = $info['business_code'];
			$reqData['mnoArray'] = implode(',',$business_codes);
			$rs = \app\customs\Sxpay::setMnoArrayfz(aid,$reqData,$mchkey);//分账设置 https://paas.tianquetech.com/docs/#/api/fzsz
			if($rs['status'] == 0){
				return json(['status'=>0,'msg'=>$rs['msg']]);
			}
		}
		Db::name('sxpay_fenzhang')->where('aid',aid)->where('id',$id)->update(['status'=>$st]);
		return json(['status'=>1,'msg'=>'操作成功']);
	}

	//签署协议
	public function signxieyi(){
		$id = input('param.id/d');
		$info = Db::name('sxpay_fenzhang')->where('aid',aid)->where('id',$id)->find();
		$mchkey = Db::name('sxpay_income')->where('aid',aid)->where('business_code',$info['business_code'])->value('mchkey');

		$reqData = [];
		$reqData['mno'] = $info['business_code'];
		$reqData['signType'] = '00';
		$rs = \app\customs\Sxpay::signxieyifz(aid,$reqData,$mchkey);
		if($rs['status'] == 1){
			return redirect($rs['retUrl']);
		}
		die($rs['msg']);
	}

    public function specialApplicationLog()
    {
        $id = input('param.id/d');
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
            if($id)
                $where[] = ['fenzhang_id','=',$id];
            $count = 0 + db('sxpay_special_apply')->where($where)->count();
            $data = db('sxpay_special_apply')->where($where)->page($page,$limit)->order($order)->select()->toArray();
            foreach($data as $k=>$v){
                if($v['split_accounts']){
                    $data[$k]['split_accounts'] = json_decode($v['split_accounts'],true);
                }else{
                    $data[$k]['split_accounts'] = [];
                }
            }

            return ['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data];
        }
        return View::fetch();
    }

    public function specialApplicationLogDel()
    {

    }

    //商户特殊申请 撤销申请
    public function specialApplicationLogCancel()
    {
        $id = input('post.id/d');
        $info = Db::name('sxpay_special_apply')->where('aid',aid)->where('id',$id)->find();

        $reqData = [];
        $reqData['id'] = $info['respid'];
        $mchkey = Db::name('sxpay_income')->where('aid',aid)->where('business_code',$info['business_code'])->value('mchkey');
        $rs = \app\customs\Sxpay::specialApplicationApplyBack(aid,$reqData,$mchkey);
        if($rs['status']==0){
            return json($rs);
        }

        Db::name('sxpay_special_apply')->where('id',$id)->update(['status'=>3]);
        return json($rs);
    }

    //商户特殊申请结果查询
    public function applyQuery(){
        $id = input('post.id/d');
        $info = Db::name('sxpay_special_apply')->where('aid',aid)->where('id',$id)->find();
        $reqData = [];
        $reqData['id'] = $info['respid'];
        $mchkey = Db::name('sxpay_income')->where('aid',aid)->where('business_code',$info['business_code'])->value('mchkey');
        $rs = \app\customs\Sxpay::specialApplicationApplyQuery(aid,$reqData,$mchkey);
        if($rs['status']==0){
            return json($rs);
        }
        $data = $rs['data'];

        Db::name('sxpay_special_apply')->where('id',$id)->update(['status'=>$data['applyStatus'],'resp_explain'=>$data['handleExplain'],'resp_account_ratio'=>$data['accountRatio']]);
        return json($rs);
    }

    //商户特殊申请提交 接口：https://paas.tianquetech.com/docs/#/api/shtssqtj
    private function specialApplication($info,$mchkey,$fenzhangdata)
    {
        $reqData = [];
        $reqData['mno'] = $info['business_code'];
        $reqData['applicationType'] = 2;//申请类型，枚举：1 分时结算申请,2 分账申请
        $reqData['accountRatio'] = $info['account_ratio'];//商户最大分账比例，需为1~100的整数
        foreach ($fenzhangdata as $item){
            $splitAccounts[] = [
                'mno'=> $item['business_code'],//分账接收方商编
                'splitCycle'=>$item['split_cycle'],//分账周期，按实际情况填写
                'relationShip'=>$item['relation_ship'],//分账双方关系，按实际情况填写
                'scenes'=>$item['scenes']//分账场景说明，按实际情况填写
            ];
        }
        $reqData['splitAccounts'] = $splitAccounts;
        if($info['agreement_pic_str']){
            $agreement_pic_str = explode(',',$info['agreement_pic_str']);
            if($agreement_pic_str){
                foreach ($agreement_pic_str as $value){
                    $rsupload = \app\customs\Sxpay::uploadimg($value,'86');
                    if($rsupload['status'] === 0) {
                        return $rsupload;
                    }
                    $agreement_pic_arr[] = $rsupload;
                }
            }
        }
        if($info['scenes_pic_str']){
            $scenes_pic_str = explode(',',$info['scenes_pic_str']);
            if($scenes_pic_str){
                foreach ($scenes_pic_str as $value){
                    $rsupload = \app\customs\Sxpay::uploadimg($value,'87');
                    if($rsupload['status'] === 0) {
                        return $rsupload;
                    }
                    $scenes_pic_arr[] = $rsupload;
                }
            }
        }
        $reqData['agreementPicStr'] = empty($agreement_pic_arr)?'':implode(',',$agreement_pic_arr);//分账情况说明函，最多上传6张，以英文逗号隔开
        $reqData['scenesPicStr'] = empty($scenes_pic_arr)?'':implode(',',$scenes_pic_arr);//分账场景图片，最多上传6张，以英文逗号隔开
        $reqData['otherPicStr'] = '';//其他附件，最多上传6张，以英文逗号隔开
        $reqData['remark'] = '';//分账申请备注
        $rs = \app\customs\Sxpay::specialApplication(aid,$reqData,$mchkey);
        if($rs['status'] == 1){
            $dataInsert = [
                'aid'=>aid,
                'fenzhang_id'=>$info['id'],
                'business_code'=>$info['business_code'],
                'type'=>$reqData['applicationType'],
                'account_ratio'=>$reqData['accountRatio'],
                'split_accounts'=>json_encode($splitAccounts,JSON_UNESCAPED_UNICODE),
                'agreement_pic_str'=>$info['agreement_pic_str'],
                'scenes_pic_str'=>$info['agreement_pic_str'],
                'other_pic_str'=>$info['other_pic_str'],
                'remark'=>$reqData['remark'],
                'resp'=>json_encode($rs),
                'respid'=>$rs['id'],
                'status'=>0,
                'createtime'=>time()
            ];
            db('sxpay_special_apply')->insert($dataInsert);
        }
        return $rs;
    }

}