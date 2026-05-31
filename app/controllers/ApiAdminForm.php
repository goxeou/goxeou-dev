<?php


//表单提交记录
namespace app\controllers;
use think\facade\Db;
class ApiAdminForm extends ApiAdmin
{	
	
	public function formlog(){
		$pagenum = input('post.pagenum');
        $st = input('post.st');
		if(!$pagenum) $pagenum = 1;
		$pernum = 20;
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['bid','=',bid];
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
		
		if(input('post.keyword')){
			$where[] = ['title|form0|form1|form2|form3|form4|form5|form6|form7|form8|form9|form10','like','%'.input('param.keyword').'%'];
		}

		//$where['status'] = 1;
		$datalist = Db::name('form_order')->field('*,from_unixtime(createtime)createtime,from_unixtime(paytime)paytime')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
		if(!$datalist) $datalist = [];
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
	//表单提交记录
	public function formdetail(){
		$id = input('param.id/d');

		$where = [];
		$where[] = ['id','=',$id];
		$where[] = ['aid','=',aid];
		$where[] = ['bid','=',bid];
		$detail = Db::name('form_order')->where($where)->find();
		if(!$detail) return $this->json(['status'=>-4,'msg'=>'记录不存在']);
		$detail['paytime'] = date('Y-m-d H:i:s',$detail['paytime']);
		$detail['createtime'] = date('Y-m-d H:i:s',$detail['createtime']);
		$member = Db::name('member')->where('id',$detail['mid'])->find();
		$detail['headimg'] = $member['headimg'];
		$detail['nickname'] = $member['nickname'];
		$form = Db::name('form')->where('aid',aid)->where('bid',bid)->where('id',$detail['formid'])->find();
		$formcontent = json_decode($form['content'],true);
		if($formcontent){
			foreach($formcontent as $k=>$v){
	            if($v['key'] == 'upload_pics'){
					$pics = $detail['form'.$k];
					if($pics){
						$detail['form'.$k] = explode(",",$pics);
					}
				}
			}
		}
		$rdata = [];
		$rdata['form'] = $form;
		$rdata['formcontent'] = $formcontent;
		$rdata['detail'] = $detail;
		return $this->json($rdata);
	}
	//改状态
	public function formsetst(){
		$id = input('param.id/d');
		$st = input('param.st/d');
		$istuikuan = input('post.istuikuan/d');
		$istuikuan = 1;

		$where = [];
		$where[] = ['id','=',$id];
		$where[] = ['aid','=',aid];
		$where[] = ['bid','=',bid];
		$order = Db::name('form_order')->where($where)->find();
		if(!$order) return json(['status'=>1,'msg'=>'操作失败']);
			
		if($st == 2 && $istuikuan == 1){
			$order['totalprice'] = $order['money'];
			$rs = \app\commons\Order::refund($order,$order['money'],input('post.reason'));
			if($rs['status']==0){
				return json(['status'=>0,'msg'=>$rs['msg']]);
			}
			Db::name('form_order')->where('aid',aid)->where('bid',bid)->where('id',$order['id'])->update(['isrefund'=>1]);
		}
		if($st == 2){
			$reason = input('post.reason');
			Db::name('form_order')->where('aid',aid)->where('bid',bid)->where('id',$order['id'])->update(['status'=>$st,'reason'=>$reason]);
		}else{
			Db::name('form_order')->where('aid',aid)->where('bid',bid)->where('id',$order['id'])->update(['status'=>$st]);
			if(getcustom('form_option_givescore')){
                //赠送选项积分（暂单选选择）
                if($order['paystatus'] == 0){
                    $form = Db::name('form')->where('aid',aid)->where('id',$order['formid'])->field('id,content')->find();
                    if($form && $form['content']){
                        //查询表设置的参数
                        $formcontent = json_decode($form['content'],true);
                        if($formcontent){
                            //$givescore = 0;//赠送积分
                            //查询选择的选项
                            foreach($formcontent as $k=>$v){
                                if($v['key']=='radio' || $v['key']=='selector'){
                                    if(isset($order['form'.$k])){
                                    	$val = $order['form'.$k];//获取选项值;
                                    	$i = -1;//对应的序号
                                        if($v['val2']){
                                            foreach($v['val2'] as $k2=>$v2){
                                                if($v2 == $val){
                                                    $i = $k2;
                                                }
                                            }
                                            unset($v2);
                                        }
                                        if($i>=0 && $v['val16']){
                                            $givescore = $v['val16'][$i]?$v['val16'][$i]:0;
                                            if($givescore >0){
			                                    $res = \app\commons\Member::addscore(aid,$order['mid'],$givescore,$val.'赠送'.t('积分'));
			                                    if($res && $res['status'] == 1){
			                                        Db::name('form_order')->where('aid',aid)->where('id',$order['id'])->update(['issend_opscore'=>1,'send_opscoretime'=>time()]);
			                                    }
			                                }
                                        }
                                    }
                                }
                            }
                            unset($v);
                            
                        }
                    }
                }
            }
		}
		//审核结果通知
		$tmplcontent = [];
		$tmplcontent['first'] = ($st == 1 ? '恭喜您的提交审核通过' : '抱歉您的提交未审核通过');
		$tmplcontent['remark'] = ($st == 1 ? '' : ($reason.'，')) .'请点击查看详情~';
		$tmplcontent['keyword1'] = $order['title'];
		$tmplcontent['keyword2'] = ($st == 1 ? '已通过' : '未通过');
		$tmplcontent['keyword3'] = date('Y年m月d日 H:i');
        $tempconNew = [];
        $tempconNew['thing9'] = $order['title'];
        $tempconNew['thing2'] = ($st == 1 ? '已通过' : '未通过');
        $tempconNew['time3'] = date('Y年m月d日 H:i');
		\app\commons\Wechat::sendtmpl(aid,$order['mid'],'tmpl_shenhe',$tmplcontent,m_url('pagesA/form/formlog'),$tempconNew);
		//订阅消息
		$tmplcontent = [];
		$tmplcontent['thing8'] = $order['title'];
		$tmplcontent['phrase2'] = ($st == 1 ? '已通过' : '未通过');
		$tmplcontent['thing4'] = $reason;
		
		$tmplcontentnew = [];
		$tmplcontentnew['thing2'] = $order['title'];
		$tmplcontentnew['phrase1'] = ($st == 1 ? '已通过' : '未通过');
		$tmplcontentnew['thing5'] = $reason;
		\app\commons\Wechat::sendwxtmpl(aid,$order['mid'],'tmpl_shenhe',$tmplcontentnew,'pagesA/form/formlog',$tmplcontent);

	}
	//删除
	public function formdel(){
		$id = input('param.id/d');
		$where = [];
		$where[] = ['id','=',$id];
		$where[] = ['aid','=',aid];
		$where[] = ['bid','=',bid];
		Db::name('form_order')->where($where)->delete();
		return json(['status'=>1,'msg'=>'删除成功']);
	}
}