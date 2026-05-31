<?php


// +----------------------------------------------------------------------
// | 表单
// +----------------------------------------------------------------------
namespace app\controllers;
use app\commons\File;
use think\facade\View;
use think\facade\Db;

class Form extends Common
{
	//表单列表
	public function index(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
				$order = 'sort desc,id desc';
			}
			$where = [];
			$where[] = ['aid','=',aid];
			if(bid==0){
				if(input('param.bid')){
					$where[] = ['bid','=',input('param.bid')];
				}elseif(input('param.showtype')==2){
					$where[] = ['bid','<>',0];
                }elseif(input('param.showtype')=='all'){
                    $where[] = ['bid','>=',0];
				}else{
					$where[] = ['bid','=',0];
				}
                if($this->user['bids']){
                    $bids = explode(',',$this->user['bids']);
                    if(!in_array('0',$bids)){
                        $where[] = ['bid','in',$bids];
                    }
                }
                if(getcustom('user_area_agent') && $this->user['isadmin']==3){
                    $areaBids = \app\commons\Business::getUserAgentBids(aid,$this->user);
                    $where[] = ['bid','in',$areaBids];
                }
			}else{
				$where[] = ['bid','=',bid];
			}
			if(getcustom('form_data')){
				if(input('param.isopen')){
					$where[] = ['isopen','=',1];
				}
		    }

			if(input('param.name')) $where[] = ['name','like','%'.input('param.name').'%'];
			$count = 0 + Db::name('form')->where($where)->count();
			$data = Db::name('form')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			foreach($data as $k=>$v){
				if(strtotime($v['starttime']) > time()){
					$data[$k]['status'] = '<button class="layui-btn layui-btn-sm" style="background-color:#888">未开始</button>';
				}elseif(strtotime($v['endtime']) < time()){
					$data[$k]['status'] = '<button class="layui-btn layui-btn-sm layui-btn-disabled">已结束</button>';
				}else{
					$data[$k]['status'] = '<button class="layui-btn layui-btn-sm" style="background-color:#3e5">进行中</button>';
				}

				$where1 = [];
				$where1[] = ['formid','=',$v['id']];
				$where1[] = ['status','=',0];
				if(getcustom('form_option_adminuser')){
					if(!bid && $this->user && $this->user['isadmin']<=0){
						$where1[] = ['uid','in',"0,".$this->user['id']];
					}
				}
				if($v['payset'] == 1){
					$where1[] = ['paystatus','=',1];
					$st0count = Db::name('form_order')->where($where1)->count();
				}else{
					$st0count = Db::name('form_order')->where($where1)->count();
				}

				$data[$k]['st0count'] = $st0count;
				if($v['bid'] > 0){
					$data[$k]['bname'] = Db::name('business')->where('aid',aid)->where('id',$v['bid'])->value('name');
				}else{
					$data[$k]['bname'] = '平台';
				}
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		
		$bids = explode(',',$this->user['bids']);
		if(!in_array('0',$bids)){
			$blist = Db::name('business')->where('aid',aid)->where('id','in',$bids)->order('sort desc,id desc')->column('id,name');
		}else{
			$blist = Db::name('business')->where('aid',aid)->order('sort desc,id desc')->column('id,name');
		}
		View::assign('blist',$blist);
		return View::fetch();
	}
	//编辑
	public function edit(){
		if(input('param.id')){
			if(bid == 0){
				$info = Db::name('form')->where('aid',aid)->where('id',input('param.id/d'))->find();
			}else{
				$info = Db::name('form')->where('aid',aid)->where('bid',bid)->where('id',input('param.id/d'))->find();
			}
            $info['submit_tourl'] = explode(',',$info['submit_tourl']);
            if(getcustom('form_other_money')){
                $info['fee_items'] = $info['fee_items']?json_decode($info['fee_items'],true):[];
            }
		}else{
			$info = array('id'=>'','content'=>'[]','commissionset'=>'-1');
		}
        $default_cid = Db::name('member_level_category')->where('aid',aid)->where('isdefault', 1)->value('id');
        $default_cid = $default_cid ? $default_cid : 0;
        $aglevellist = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->where('can_agent','<>',0)->order('sort,id')->select()->toArray();
		$levellist = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->order('sort,id')->select()->toArray();
		View::assign('aglevellist',$aglevellist);
		View::assign('levellist',$levellist);
		View::assign('info',$info);
		if(getcustom('form_print')){
			$printlist = Db::name('wifiprint_set')->where('aid',aid)->where('bid',bid)->order('id desc')->select()->toArray();
			View::assign('printlist',$printlist);
		}
		if(getcustom('form_match')){
            $memberlevelList = Db::name('member_level')->where('aid',aid)->field('id,name')->select()->toArray();
            View::assign('memberlevelList',$memberlevelList);
            $quanxian = [];
            if(!empty($info['quanxian'])){
                $quanxian = json_decode($info['quanxian'],true);
            }
            View::assign('quanxian',$quanxian);
        }

        if(getcustom('form_data')){
	        $formcontent = $info['content']?json_decode($info['content'],true):'';
	        View::assign('formcontent',$formcontent);
	        $color1 = Db::name('admin_set')->where('aid',aid)->value('color1');
	        View::assign('color1',$color1);
        }
        $users = Db::name('admin_user')->where('aid',aid)->where('bid',bid)->field('id,un')->select();
        $users = $users?$users:'';
        View::assign('users',$users);
        View::assign('bid',bid);
		return View::fetch();
	}
	//保存
	public function save(){
		$info = input('post.info/a');
		$info['commissiondata1'] = jsonEncode(input('post.commissiondata1/a'));
		$info['commissiondata2'] = jsonEncode(input('post.commissiondata2/a'));
        if(input('post.submit_tourl/a')){
            $submit_tourl = array_filter(input('post.submit_tourl/a'));
            $info['submit_tourl'] = implode(',',$submit_tourl);
        }
		$datatype = input('post.datatype/a');
		$dataval1 = input('post.dataval1/a');
		$dataval2 = input('post.dataval2/a');
		$dataval3 = input('post.dataval3/a');
		$dataval4 = input('post.dataval4/a');
		$dataval5 = input('post.dataval5/a');
        $dataval6 = [];
        $dataval7 = [];
		$dataval8 = input('post.dataval8/a');
		$dataval9 = input('post.dataval9/a');
		$dataval10 = input('post.dataval10/a');
		$dataval11 = input('post.dataval11/a');
        $dataval12 = [];
        $dataval13 = [];
        $dataval14 = [];
        $dataval15 = [];
        $dataval16 = [];
        $dataval17 = [];
        $dataval18 = [];
        $dataval19 = [];
		$linkitem = input('post.linkitem/a');
		$bgcolor = input('post.bgcolor/a');
		if(getcustom('form_auth_tel')){
            $dataval6 = input('post.dataval6/a');
        }
		if(getcustom('form_other_money')){
		    $otheritem = input('post.otheritem/a',[]);
		    $otherfee = input('post.otherfee/a',[]);
            $feeitems = [];
		    if($otheritem){
		        foreach ($otheritem as $ik=>$iv){
		            if(empty($iv)){
		                continue;
                    }
		            if(empty($otherfee[$ik])){
                      return  json(['status'=>0,'msg'=>$iv.'未设置金额']);
                    }
                    $feeitems[] = [
                        'name'=>$iv,
                        'money'=>$otherfee[$ik]
                    ];
                }
            }
            $info['fee_items'] = $feeitems?json_encode($feeitems):'';
        }
		//附件下载压缩包名称
        if(getcustom('form_attachment_alias')){
            $attachment_alias_type = $info['attachment_alias_type'];
            if($attachment_alias_type==1){
                $attachment_alias = $info['attachment_alias'];
                if(empty($attachment_alias)){
                    return  json(['status'=>0,'msg'=>'请填写压缩包名称']);
                }
               $info['attachment_alias'] = $attachment_alias;
            }else{
                $info['attachment_alias'] = '';
            }
            $dataval7 = input('post.dataval7/a',[]);
        }

        if(getcustom('form_map')){
            //前端是否展示
            $dataval12 = input('post.dataval12/a');
        }
        if(getcustom('form_match')){
            //是否用做数据匹配
            $dataval13 = input('post.dataval13/a');
            $info['quanxian'] = jsonEncode(input('post.quanxian/a'));
        }
        if(getcustom('form_data')){
            //详情页是否展示
            $dataval14 = input('post.dataval14/a');
        }
        $dataval15 = input('post.dataval15/a');
        if(getcustom('form_option_givescore')){
	        if(!bid){
	        	$dataval16 = input('post.dataval16/a');
	        	$dataval17 = input('post.dataval17/a');
	        	$dataval18 = input('post.dataval18/a');
	        }
        }
        if(getcustom('form_radio_paymoney')){
        	$dataval19 = input('post.dataval19/a');
        }
		$dataval_query = input('post.dataval_query/a');
		$dhdata = array();
		if(getcustom('form_option_adminuser')){
			$unum = 0;//普通选项设置管理员的个数
			$uk   = -1;//哪一项设置了
		}
		foreach($datatype as $k=>$v){
			if($dataval3[$k]!=1) $dataval3[$k] = 0;
			$val19 = '';
			if(getcustom('form_radio_paymoney')){
				if($v == 'radio'){
					$val19 = $dataval19[$k]??'';
					if($val19){
						//是否设置价格
						$val19money = 0;
						foreach($val19 as $v19){
							if($v19<0){
								return  json(['status'=>0,'msg'=>"单选选项价格设置不能小于0"]);
							}
							$val19money += $v19;
						}
						if($val19money<=0){
							$val19 = '';
						}
					}
				}
	        }
			$dhdata[] = [
			'key'=>$v,
			'val1'=>$dataval1[$k],
			'val2'=>$dataval2[$k],
			'val3'=>$dataval3[$k],
			'val4'=>$dataval4[$k],
			'val5'=>($dataval5 ? $dataval5[$k] : ''),
			'val6'=>$dataval6[$k]??0,
			'val7'=>$dataval7[$k]??0,
			'val8'=>$dataval8[$k]??0,
			'val9'=>$dataval9[$k]??0,
			'val10'=>$dataval10[$k]??0,
			'val11'=>$dataval11[$k]??0,
            'val12'=>$dataval12[$k]??0,
            'val13'=>$dataval13[$k]??0,
            'val14'=>$dataval14[$k]??0,
            'val15'=>$dataval15[$k]??0,
            'val16'=>$dataval16[$k]??'',
            'val17'=>$dataval17[$k]??'',
            'val18'=>$dataval18[$k]??0,
            'val19'=>$val19,
			'query'=>($dataval_query[$k] ? $dataval_query[$k] : '0'),
			'linkitem'=>$linkitem[$k]??'',
			'bgcolor'=>$bgcolor[$k]??'',
			];

			if(getcustom('form_option_adminuser')){
				if(!bid && $v == 'selector'){
					$val18 = $dataval18[$k]??0;
					if($val18 == 1){
						$unum ++;
						$uk = $k;
					}
				}
			}
		}
		if(getcustom('form_option_adminuser')){
			if($unum>1){
				return  json(['status'=>0,'msg'=>"普通选项设置管理员重复，请设置仅一项为管理员项"]);
			}
			$info['uk'] = $uk;
		}
		$info['content'] = json_encode($dhdata,JSON_UNESCAPED_UNICODE);
		if(bid!=0){
			$info['commissionset'] = -1;
		}
		if(getcustom('form_print')){
			if(!$info['print_status']){
				$info['print_status'] = 0;
			}
			if(!$info['print_auto']){
				$info['print_auto'] = 0;
			}
		}
		if($info['id']){
			if(bid == 0){
				Db::name('form')->where('aid',aid)->where('id',$info['id'])->update($info);
			}else{
				Db::name('form')->where('aid',aid)->where('bid',bid)->where('id',$info['id'])->update($info);
			}
			\app\commons\System::plog('编辑自定义表单'.$info['id']);
		}else{
			$info['aid'] = aid;
			$info['bid'] = bid;
			$info['createtime'] = time();
			$id = Db::name('form')->insertGetId($info);
			\app\commons\System::plog('添加自定义表单'.$id);
		}
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}
	//删除
	public function del(){
		$ids = input('post.ids/a');
		if(bid == 0){
			Db::name('form')->where('aid',aid)->where('id','in',$ids)->delete();
		}else{
			Db::name('form')->where('aid',aid)->where('bid',bid)->where('id','in',$ids)->delete();
		}
		\app\commons\System::plog('删除自定义表单'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	
	//复制
	public function copyform(){
		$id = input('post.id/d');
		$form = Db::name('form')->where('aid',aid)->where('bid',bid)->where('id',$id)->find();
		if(!$form){
			return json(['status'=>0,'msg'=>'表单不存在']);
		}
		$form['id'] = '';
        $form['name'] = $form['name'].'-复制';
		$newid = Db::name('form')->insertGetId($form);
		return json(['status'=>1,'msg'=>'操作成功','newid'=>$newid]);
	}
	

	//表单数据
	public function record(){
		$form = Db::name('form')->where('aid',aid)->where('id',input('param.formid/d'))->find();
		$formcontent = json_decode($form['content'],true);
		if(getcustom('form_option_adminuser')){
			if(!bid){
				$options = '';
				$opk = -1;
				foreach($formcontent as $k=>$v){
					//普通选项绑定管理员
					if($v['key'] == 'selector' && $v['val18'] == 1 && $form['uk'] == $k){
						$opk = $k;
						if($this->user && $this->user['isadmin']<=0){
							$users = $v['val17'];

							if($users){
								$uks = [];
								foreach($users as $uk=>$uv){
									if(!$uv || $uv == $this->user['id']){
										if(!in_array($uk,$uks)){
											array_push($uks,$uk);
										}
									}
								}
								if($v['val2']){
									$options = [];
									foreach($v['val2'] as $k=>$v){
										if(in_array($k,$uks)){
											array_push($options,$v);
										}
									}
									unset($k);
									unset($v);
								}
							}else{
								$options = $v['val2'];
							}
						}else{
							$options = $v['val2'];
						}
					}
				}
			}
		}
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
				if(getcustom('form_data')){
					$order = 'sort desc,id desc';
				}else{
					$order = 'id desc';
				}
			}
			$where = [];
			$where[] = ['aid','=',aid];
			if(bid != 0){
				$where[] = ['bid','=',bid];
			}
			if(getcustom('form_option_adminuser')){
				if(!bid){
					if($this->user && $this->user['isadmin']<=0){
						$where[] = ['uid','in',"0,".$this->user['id']];
					}
					if(input('?param.opv') && input('param.opv')!==''){
						$opv = input('param.opv');
						if($opk>=0){
							$where[] = ['form'.$opk,'=',$opv];
						}else{
							$where[] = ['id','=',0];
						}
					}
				}
			}
			if(input('param.formid')) $where[] = ['formid','=',input('param.formid/d')];
			if(input('param.name')) $where[] = ['name','like','%'.input('param.name').'%'];
			if(input('param.ctime') ){
				$ctime = explode(' ~ ',input('param.ctime'));
				$where[] = ['createtime','>=',strtotime($ctime[0])];
				$where[] = ['createtime','<',strtotime($ctime[1]) + 86400];
			}
			if(input('?param.status') && input('param.status')!==''){
				$where[] = ['status','=',input('param.status')];
			}
			if(input('param.tel')){
				$where[] = ['form0|form1|form2|form3|form4|form5|form6|form7|form8|form9|form10','=',input('param.tel')];
			}
			if(input('param.keyword')){
				$where[] = ['form0|form1|form2|form3|form4|form5|form6|form7|form8|form9|form10','like','%'.input('param.keyword').'%'];
			}
			
			$form = Db::name('form')->where('aid',aid)->where('id',input('param.formid/d'))->find();
			$formcontent = json_decode($form['content'],true);

			$count = 0 + Db::name('form_order')->where($where)->count();
			$data = Db::name('form_order')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			foreach($data as $k=>$v){
				$data[$k]['headimg'] = '';
				$data[$k]['nickname'] = '';
				if($v['mid']){
					$member = Db::name('member')->where('id',$v['mid'])->find();
					if($member){
						$data[$k]['headimg'] = $member['headimg'];
						$data[$k]['nickname'] = $member['nickname'];
					}
				}
				$pics = [];
				foreach($formcontent as $k2=>$field){
					if($field['key']=='upload'){
						$pics1 = $v['form'.$k2];
						if($pics1){
							if(in_array($pics1)){
								$pics[] = $pics1[0];
								$data[$k]['form'.$k2] = $pics1[0];
							}else{
								$pics[] = $pics1;
							}
						}
					}else if($field['key'] == 'upload_pics'){
						$pics1 = $v['form'.$k2];
						if($pics1){
							if(in_array($pics1)){
								foreach($pics1 as $pv){
									$pics[] = $pv;
								}
							}else{
								$data[$k]['form'.$k2] = explode(",",$pics1);
								foreach($data[$k]['form'.$k2] as $pv){
									$pics[] = $pv;
								}
							}
						}
					}
				}
				$data[$k]['pics'] = implode(',',$pics);
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		
		View::assign('form',$form);
		View::assign('formcontent',$formcontent);
		View::assign('bid',bid);

		if(getcustom('form_option_adminuser')){
			if(!bid){

				View::assign('options',$options);
			}
		}
		if(getcustom('form_custom_number')){
            View::assign('custom_number_text',$form['custom_number_text']);
        }
		return View::fetch();
	}
	//表单数据导出
	public function recordexcel(){
		$form = Db::name('form')->where('aid',aid)->where('id',input('param.formid/d'))->find();
		$formcontent = json_decode($form['content'],true);
		if(getcustom('form_option_adminuser')){
			if(!bid){
				$options = '';
				$opk = -1;
				foreach($formcontent as $k=>$v){
					//普通选项绑定管理员
					if($v['key'] == 'selector' && $v['val18'] == 1 && $form['uk'] == $k){
						$opk = $k;
						$options = $v['val2'];
					}
				}
			}
		}
		if(input('param.field') && input('param.order')){
			$order = input('param.field').' '.input('param.order');
		}else{
			if(getcustom('form_data')){
				$order = 'sort desc,id desc';
			}else{
				$order = 'id desc';
			}
		}
		$where = [];
		$where[] = ['aid','=',aid];
		if(bid != 0){
			$where[] = ['bid','=',bid];
		}
		if(getcustom('form_option_adminuser')){
			if(!bid){
				if($this->user && $this->user['isadmin']<=0){
					$where[] = ['uid','in',"0,".$this->user['id']];
				}
				if(input('?param.opv') && input('param.opv')!==''){
					$opv = input('param.opv');
					if($opk>=0){
						$where[] = ['form'.$opk,'=',$opv];
					}else{
						$where[] = ['id','=',0];
					}
				}
			}
		}
		$where[] = ['formid','=',input('param.formid/d')];
		if(input('param.name')) $where[] = ['name','like','%'.input('param.name').'%'];

		if(input('param.ctime') ){
			$ctime = explode(' ~ ',input('param.ctime'));
			$where[] = ['createtime','>=',strtotime($ctime[0])];
			$where[] = ['createtime','<',strtotime($ctime[1]) + 86400];
		}
		if(input('?param.status') && input('param.status')!==''){
			$where[] = ['status','=',input('param.status')];
		}
		if(input('param.keyword')){
			$where[] = ['form0|form1|form2|form3|form4|form5|form6|form7|form8|form9|form10','like','%'.input('param.keyword').'%'];
		}

		$list = Db::name('form_order')->where($where)->order($order)->select()->toArray();
		
		$title = array();
		$title[] = '序号';
		$title[] = '昵称';
		$type = [];
        $type[] = 1;
		foreach($formcontent as $k=>$v){
			$title[]=$v['val1'];
            $type[]=$v['val4'];
		}
		if($form['payset']==1){
			$title[] = '支付状态';
			$title[] = '支付金额';
			$title[] = '支付单号';
			$title[] = '支付时间';
		}
        if(getcustom('form_other_money')){
            $feeNames = [];
            $itemfee = $form['fee_items']?json_decode($form['fee_items'],true):[];
            foreach ($itemfee as $ik=>$iv){
                $title[] = $iv['name'];
                $feeNames[] = $iv['name'];
            }
        }
        if(getcustom('form_custom_number')){
            $title[] = $form['custom_number_text'];
        }
		$title[] = '提交时间';
		$title[] = '状态';
		$title[] = '驳回原因';
		$data = array();
		foreach($list as $v){
			$tdata = array();
			$tdata[] = $v['id'];

			$nickname = '';
			if($v['mid']){
				$member = Db::name('member')->where('id',$v['mid'])->find();
				if($member){
					$nickname = $member['nickname']."(ID:".$v['mid'].")";
				}
			}
			$tdata[] = $nickname;

			foreach($formcontent as $k=>$d){
				$tdata[] = $v['form'.$k];
			}
			if($form['payset']==1){
				$tdata[] = $v['paystatus'] == 1?'已支付':'未支付';
				$tdata[] = $v['money'];
				$tdata[] = $v['paynum'];
				$tdata[] =  $v['paytime']? date("Y-m-d H:i:s",$v['paytime']) : '';
			}
            if(getcustom('form_other_money')){
                $_feelist = $v['fee_items']?json_decode($v['fee_items'],true):[];
                foreach ($feeNames as $fk=>$fname){
                    $feemoney = 0;
                    foreach ($_feelist as $fee){
                        if($fee['name']==$fname){
                            $feemoney = $fee['money'];
                            break;
                        }
                    }
                    $tdata[] = $feemoney;
                }
            }
            if(getcustom('form_custom_number')){
                $tdata[] = $v['custom_number'];
            }
			$tdata[] = date('Y-m-d H:i:s',$v['createtime']);
			$status = '';
			if($v['status']==0){
				$status = '待处理';
			}elseif($v['status']==1){
				$status = '已确认';
			}elseif($v['status']==2){
				$status = '已驳回';
			}
			if($v['isudel']==1){
				$status.=',用户已删除';
			}
			$tdata[] = $status;
			$tdata[] = $v['reason'];
			$data[] = $tdata;
		}
		$this->export_excel($title,$data,$type);
	}
	//改状态
	public function recordsetst(){
		$ids = input('post.ids/a');
		$st = input('post.st/d');
		$istuikuan = input('post.istuikuan/d');

		$where = [];
		$where[] = ['aid','=',aid];
		if(bid != 0){
			$where[] = ['bid','=',bid];
		}
		if(getcustom('form_option_adminuser')){
			if(!bid && $this->user && $this->user['isadmin']<=0){
				$where[] = ['uid','in',"0,".$this->user['id']];
			}
		}
		$where[] = ['id','in',$ids];
		$orderlist = Db::name('form_order')->where($where)->select()->toArray();

		foreach($orderlist as $order){
			if($st == 2 && $istuikuan == 1){
				$order['totalprice'] = $order['money'];
				$rs = \app\commons\Order::refund($order,$order['money'],input('post.reason'));
				if($rs['status']==0){
					return json(['status'=>0,'msg'=>$rs['msg']]);
				}
				Db::name('form_order')->where('aid',aid)->where('id',$order['id'])->update(['isrefund'=>1]);
			}
			if($st == 2){
				$reason = input('post.reason');
				Db::name('form_order')->where('aid',aid)->where('id',$order['id'])->update(['status'=>$st,'reason'=>$reason]);
			}else{
				Db::name('form_order')->where('aid',aid)->where('id',$order['id'])->update(['status'=>$st]);
                if(getcustom('form_give_money')){
                    $form = Db::name('form')->where('aid',aid)->where('id',$order['formid'])->find();
                    if($form['give_score'] >0){
                        \app\commons\Member::addscore($form['aid'],$order['mid'],$form['give_score'],'提交表单赠送'.t('积分'));
                    }
                    if($form['give_money'] >0){
                        \app\commons\Member::addmoney($form['aid'],$order['mid'],$form['give_money'],'提交表单赠送'.t('余额'));
                    }
                }
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
		\app\commons\System::plog('修改表单数据状态'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//编辑表单数据
	public function recordedit(){
		$where = [];
		$where[] = ['id','=',input('param.formid/d')];
		$where[] = ['aid','=',aid];
		if(bid != 0){
			$where[] = ['bid','=',bid];
		}
		if(getcustom('form_option_adminuser')){
			if(!bid && $this->user && $this->user['isadmin']<=0){
				$where[] = ['uid','in',"0,".$this->user['id']];
			}
		}
		$form = Db::name('form')->where($where)->find();
		if(!$form) return json(['status'=>0,'msg'=>'表单不存在']);
		if(bid !=0 && $form['bid']!=bid) return json(['status'=>0,'msg'=>'表单不存在']);
		$formcontent = json_decode($form['content'],true);
		$id = input('param.id/d');
		if($id){
			$order = Db::name('form_order')->where('aid',aid)->where('formid',$form['id'])->where('id',$id)->find();
			if(!$order) return json(['status'=>0,'msg'=>'数据不存在']);
			if($formcontent){
				foreach($formcontent as $k=>$v){
		            if($v['key'] == 'upload_pics'){
						$pics = $order['form'.$k];
						if($pics){
							$order['form'.$k.'_pics'] = explode(",",$pics);
						}
					}
				}
			}
		}else{
			$order = ['formid'=>$form['id']];
		}
		View::assign('form',$form);
		View::assign('formcontent',$formcontent);
		View::assign('info',$order);
		return View::fetch();
	}
	public function recordsave(){
		$info = input('post.info/a');
		$where = [];
		$where[] = ['id','=',input('param.formid/d')];
		$where[] = ['aid','=',aid];
		if(bid != 0){
			$where[] = ['bid','=',bid];
		}
		if(getcustom('form_option_adminuser')){
			if(!bid && $this->user && $this->user['isadmin']<=0){
				$where[] = ['uid','in',"0,".$this->user['id']];
			}
		}
		$form = Db::name('form')->where($where)->find();
		if(!$form) return json(['status'=>0,'msg'=>'表单不存在']);
		if(bid !=0 && $form['bid']!=bid) return json(['status'=>0,'msg'=>'表单不存在']);
		
		$formcontent = json_decode($form['content'],true);

		$data =[];
		$data['mid'] = $info['mid'];
		$data['status'] = $info['status'];
		if(getcustom('form_map')){
            $data['adr_lon'] = $info['adr_lon']??'';
            $data['adr_lat'] = $info['adr_lat']??'';
        }
		foreach($formcontent as $k=>$v){
			$value = $info['form'.$k];
			if(is_array($value)){
				$value = implode(',',$value);
			}
			$data['form'.$k] = strval($value);
			if($v['val3']==1 && $data['form'.$k]==='' && $v['key']!='map'){
				return json(['status'=>0,'msg'=>$v['val1'].' 必填']);
			}
		}

		if($form['payset']==1){
			$price = input('post.price/f');
			$data['money'] = $price;
			$data['paystatus'] = $info['paystatus'];
		}
		if(getcustom('form_data')){
            $data['sort'] = $info['sort']??0;
        }

		if($info['id']){
			$oldinfo = Db::name('form_order')->where('formid',$form['id'])->where('id',$info['id'])->find();
			if(!$oldinfo) return json(['status'=>0,'msg'=>'数据不存在']);
			Db::name('form_order')->where('formid',$form['id'])->where('id',$info['id'])->update($data);
            //如果价格变动 则改支付价格
            if($form['payset']==1 && $form['paystatus']==0 && $price!=$oldinfo['money'] && $oldinfo['payorderid']){
                Db::name('payorder')->where('aid',aid)->where('id',$oldinfo['payorderid'])->update(['money'=>$price,'status'=>$info['paystatus']]);
            }
			if($data['status']!= 0 && $data['status'] != $oldinfo['status']){
				$st = $data['status'];
				//审核结果通知
				$tmplcontent = [];
				$tmplcontent['first'] = ($st == 1 ? '恭喜您的提交审核通过' : '抱歉您的提交未审核通过');
				$tmplcontent['remark'] = ($st == 1 ? '' : ($info['reason'].'，')) .'请点击查看详情~';
				$tmplcontent['keyword1'] = $form['name'];
				$tmplcontent['keyword2'] = ($st == 1 ? '已通过' : '未通过');
				$tmplcontent['keyword3'] = date('Y年m月d日 H:i');
                $tempconNew = [];
                $tempconNew['thing9'] = $form['name'];
                $tempconNew['thing2'] = ($st == 1 ? '已通过' : '未通过');
                $tempconNew['time3'] = date('Y年m月d日 H:i');
				\app\commons\Wechat::sendtmpl(aid,$oldinfo['mid'],'tmpl_shenhe',$tmplcontent,m_url('pagesA/form/formlog'),$tempconNew);
			}
		}else{
			$data['aid'] = aid;
			$data['bid'] = $form['bid'];
			$data['formid'] = $form['id'];
			$data['title'] = $form['name'];
			$data['createtime'] = time();
			$data['ordernum'] = date('ymdHis').aid.rand(1000,9999);
			$orderid = Db::name('form_order')->insertGetId($data);
			if($form['payset']==1 && $data['money'] > 0 && $data['paystatus']!=1){
				$payorderid = \app\models\Payorder::createorder(aid,$data['bid'],$data['mid'],'form',$orderid,$data['ordernum'],$data['title'],$data['money']);
			}
		}
		return json(['status'=>1,'msg'=>'操作成功']);
	}

    //表单数据
    public function recordAll(){
        if(getcustom('form_showall')){
            $where = [];
            $where[] = ['aid','=',aid];
            if(bid != 0){
                $where[] = ['bid','=',bid];
            }
            $formlist = Db::name('form')->where($where)->order('id desc')->column('name','id');
            foreach ($formlist as $k => $v){
                $formid0 = $k;
                break;
            }

            $formid = input('param.formid/d') ? input('param.formid/d') : $formid0;
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
                if(bid != 0){
                    $where[] = ['bid','=',bid];
                }
                if(input('param.formid')) $where[] = ['formid','=',input('param.formid/d')];
                if(input('?param.paystatus') && input('param.paystatus')!=='') $where[] = ['paystatus','=',input('param.paystatus/d')];
                if(input('param.name')) $where[] = ['name','like','%'.input('param.name').'%'];
                if(input('param.ctime') ){
                    $ctime = explode(' ~ ',input('param.ctime'));
                    $where[] = ['createtime','>=',strtotime($ctime[0])];
                    $where[] = ['createtime','<',strtotime($ctime[1]) + 86400];
                }
                if(input('?param.status') && input('param.status')!==''){
                    $where[] = ['status','=',input('param.status')];
                }
                if(input('param.tel')){
                    $where[] = ['form0|form1|form2|form3|form4|form5|form6|form7|form8|form9|form10','=',input('param.tel')];
                }
                if(input('param.keyword')){
                    $where[] = ['form0|form1|form2|form3|form4|form5|form6|form7|form8|form9|form10','like','%'.input('param.keyword').'%'];
                }

                $form = Db::name('form')->where('aid',aid)->where('id',$formid)->find();
                $formcontent = json_decode($form['content'],true);

                $count = 0 + Db::name('form_order')->where($where)->count();
                $data = Db::name('form_order')->where($where)->page($page,$limit)->order($order)->select()->toArray();
                foreach($data as $k=>$v){
                    $data[$k]['headimg'] = '';
                    $data[$k]['nickname'] = '';
                    $data[$k]['formname'] = $formlist[$v['formid']];
                    if($v['mid']){
                        $member = Db::name('member')->where('id',$v['mid'])->find();
                        if($member){
                            $data[$k]['headimg'] = $member['headimg'];
                            $data[$k]['nickname'] = $member['nickname'];
                        }
                    }
                    $pics = [];
                    foreach($formcontent as $k2=>$field){
                        if($field['key']=='upload'){
                            $pics[] = $v['form'.$k2];
                        }
                    }
                    $data[$k]['pics'] = implode(',',$pics);
                }
                return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
            }
            $form = Db::name('form')->where('aid',aid)->where('id',$formid)->find();
            $formcontent = json_decode($form['content'],true);
            View::assign('form',$form);
            View::assign('formcontent',$formcontent);
            View::assign('formlist',$formlist);
            return View::fetch();
        }
    }
    //表单数据导出
    public function recordAllExcel(){
        if(getcustom('form_showall')){
            $where = [];
            $where[] = ['aid','=',aid];
            if(bid != 0){
                $where[] = ['bid','=',bid];
            }
            $formlist = Db::name('form')->where($where)->order('id desc')->column('name','id');
            foreach ($formlist as $k => $v){
                $formid0 = $k;
                break;
            }

            $formid = input('param.formid/d') ? input('param.formid/d') : $formid0;

            $where[] = ['id','=',$formid];
            $form = Db::name('form')->where($where)->find();
            $formcontent = json_decode($form['content'],true);
            if(input('param.field') && input('param.order')){
                $order = input('param.field').' '.input('param.order');
            }else{
                $order = 'id desc';
            }
            $where = [];
            $where[] = ['aid','=',aid];
            if(bid != 0){
                $where[] = ['bid','=',bid];
            }
            if(input('param.formid'))$where[] = ['formid','=',input('param.formid/d')];
            if(input('param.name')) $where[] = ['name','like','%'.input('param.name').'%'];

            if(input('param.ctime') ){
                $ctime = explode(' ~ ',input('param.ctime'));
                $where[] = ['createtime','>=',strtotime($ctime[0])];
                $where[] = ['createtime','<',strtotime($ctime[1]) + 86400];
            }
            if(input('?param.status') && input('param.status')!==''){
                $where[] = ['status','=',input('param.status')];
            }
            if(input('?param.paystatus') && input('param.paystatus')!==''){
                $where[] = ['paystatus','=',input('param.paystatus')];
            }
            if(input('param.keyword')){
                $where[] = ['form0|form1|form2|form3|form4|form5|form6|form7|form8|form9|form10','like','%'.input('param.keyword').'%'];
            }

            $list = Db::name('form_order')->where($where)->order($order)->select()->toArray();
            $title = array();
            $title[] = '序号';
            $title[] = '表单名称';
            $type = [];
            $type[] = 1;
            foreach($formcontent as $k=>$v){
                $title[]=$v['val1'];
                $type[]=$v['val4'];
            }
            if($form['payset']==1){
                $title[] = '支付状态';
                $title[] = '支付金额';
                $title[] = '支付单号';
                $title[] = '支付时间';
            }
            $title[] = '提交时间';
            $title[] = '状态';
            $title[] = '驳回原因';
            $data = array();
            foreach($list as $v){
                $tdata = array();
                $tdata[] = $v['id'];
                $tdata[] = $formlist[$v['formid']];
                foreach($formcontent as $k=>$d){
                    $tdata[] = $v['form'.$k];
                }
                if($form['payset']==1){
                    $tdata[] = $v['paystatus'] == 1?'已支付':'未支付';
                    $tdata[] = $v['money'];
                    $tdata[] = $v['paynum'];
                    $tdata[] =  $v['paytime']? date("Y-m-d H:i:s",$v['paytime']) : '';
                }
                $tdata[] = date('Y-m-d H:i:s',$v['createtime']);
                $status = '';
                if($v['status']==0){
                    $status = '待处理';
                }elseif($v['status']==1){
                    $status = '已确认';
                }elseif($v['status']==2){
                    $status = '已驳回';
                }
                if($v['isudel']==1){
                    $status.=',用户已删除';
                }
                $tdata[] = $status;
                $tdata[] = $v['reason'];
                $data[] = $tdata;
            }
            $this->export_excel($title,$data,$type);
        }
    }

	//复制
	public function copyinfo(){
		$id = input('post.id/d');
		$order = Db::name('form_order')->where('aid',aid)->where('bid',bid)->where('id',$id)->find();
		if(!$order){
			return json(['status'=>0,'msg'=>'数据不存在']);
		}
		$order['id'] = '';
		$newid = Db::name('form_order')->insertGetId($order);
		return json(['status'=>1,'msg'=>'操作成功','newid'=>$newid]);
	}
	
	//删除
	public function recorddel(){
		$ids = input('post.ids/a');
		$where = [];
		$where[] = ['id','in',$ids];
		$where[] = ['aid','=',aid];
		if(bid != 0){
			$where[] = ['bid','=',bid];
		}
		if(getcustom('form_option_adminuser')){
			if(!bid && $this->user && $this->user['isadmin']<=0){
				$where[] = ['uid','in',"0,".$this->user['id']];
			}
		}
		Db::name('form_order')->where($where)->delete();
		\app\commons\System::plog('删除表单数据'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	public function chooseform(){
		if(request()->isPost()){
			$data = Db::name('form')->where('aid',aid)->where('id',input('post.id/d'))->find();
            if(getcustom('form_show_submember')){
                $submemberwhere = [
                    ['fo.aid','=',aid],
                    ['fo.bid','=',bid],
                    ['fo.status','<',2], //0 未处理 1确认 2驳回
                    ['fo.formid','=',$data['id']]
                ];
                $submemberData = Db::name('form_order')->alias('fo')->field('fo.*,m.headimg')->leftJoin('member m','m.id = fo.mid')->where($submemberwhere)->limit(20)->select()->toArray();
                $submemberDataSum = Db::name('form_order')->alias('fo')->field('fo.*,m.headimg')->leftJoin('member m','m.id = fo.mid')->where($submemberwhere)->count();
                $data['submember_data'] = $submemberData;
                $data['submember_data_sum'] = $submemberDataSum;
            }
            return json(['status'=>1,'msg'=>'查询成功','data'=>$data]);
		}
        if(bid==0){
            //商户
            $blist = Db::name('business')->where('aid',aid)->order('sort desc,id desc')->select()->toArray();
            View::assign('blist',$blist);
        }
        if(getcustom('form_data')){
	        $isopen = input('isopen')?true:false;
	        View::assign('isopen',$isopen);
	    }else{
	    	View::assign('isopen',false);
	    }
		return View::fetch();
	}

    //批量打包下载
    public function downloadPics(){
	    if(getcustom('form_attachment_alias')) {
            $id = input('post.id/d');
            $order = Db::name('form_order')->where('aid', aid)->where('bid', bid)->where('id', $id)->find();
            if (!$order) {
                return json(['status' => 0, 'msg' => '数据不存在']);
            }
            //读取附件名规则
            $formdata = Db::name('form')->where('aid', aid)->where('id', $order['formid'])->find();
            $attachment_name = '';
            $attachment_name_field = '';
            $pics = [];
            if ($formdata) {
                $content = $formdata['content'] ? json_decode($formdata['content'], true) : [];
                foreach ($content as $k => $field) {
                    if ($field['key'] == 'upload') {
                        if($order['form' . $k]){
                            $pics[] = $order['form' . $k];
                        }
                    }
                    if ($field['val7'] == 1) {
                        $attachment_name_field = 'form' . $k;
                    }
                }
                if ($formdata['attachment_alias_type'] == 1) {
                    $attachment_name = $formdata['attachment_alias'];
                } elseif ($formdata['attachment_alias_type'] == 2) {
                    $attachment_name = $order[$attachment_name_field];
                }
            }
            if (count($pics) < 1) {
                return json(['status' => 0, 'msg' => '无图片数据可下载']);
            }
            //没有命名规则 则用当前日期
            if (empty($attachment_name)) {
                $attachment_name = date('YmdHis', time());
            }
            //打包,先把图片存在本地文件夹，然后文件夹内的图片一起打包
            $zipname = $attachment_name . '.zip';
            $savepath = "upload/temp/" . aid . "_form_" . $id;
            $localdir = ROOT_PATH . $savepath;
            if (!file_exists($localdir)) {
                File::creat_dir($localdir);
            } else {
                \app\commons\File::clear_dir($localdir);//清空
            }
            foreach ($pics as $k => $pic) {
                \app\commons\Pic::tolocal($pic, aid,false, $savepath);
            }
            $zippath = ROOT_PATH . 'upload/temp/' . $zipname;
            $myfile = fopen($zippath, "w");
            fclose($myfile);
            \app\commons\File::add_file_to_zip($localdir, $zippath);
            $url = PRE_URL . '/upload/temp/' . $zipname;
            \app\commons\File::remove_dir($localdir);
            return json(['status' => 1, 'msg' => '操作成功', 'name' => $attachment_name, 'pics' => $pics, 'url' => $url]);
        }
        return json(['status' => 0, 'msg' => '未获得该功能权限']);
    }

    public function print(){
    	if(getcustom('form_print')) {
    		$id = input('post.id/d');
    		$res = \app\commons\Wifiprint::print2(aid,$id);
    		return json($res);
    	}
    }

    public function demoexcel(){
	    if(getcustom('form_import')){
            $id = input('id');
            $form = Db::name('form')->where('id',$id)->find();
            $content = json_decode($form['content'],true);
            $title = [
                '会员ID',
            ];
            foreach($content as $v){
                $title[] = $v['val1'];
            }
            $this->export_excel($title,[],[]);
        }
    }
    //导入
    public function importexcel(){
	    if(getcustom('form_import')) {
            set_time_limit(0);
            ini_set('memory_limit', -1);
            Db::startTrans();
            $file = input('post.file');
            $exceldata = $this->import_excel($file, 1);

            $id = input('id');
            $form = Db::name('form')->where('id',$id)->find();
            $content = json_decode($form['content'],true);
            $header_config = [
                '会员ID',
            ];
            foreach($content as $v){
                $header_config[] = $v['val1'];
            }

            //验证导入的数据表格头是否匹配
            $data_title = $exceldata[0];
            if (count($data_title) != count($header_config)) {
                return json(['status' => 0, 'msg' => '导入列数不匹配']);
            }
            foreach ($data_title as $k => $v) {
                if ($v != $header_config[$k]) {
                    $lie = $k + 1;
                    return json(['status' => 0, 'msg' => '导入第' . $lie . '列名称' . $v . '与模板' . $header_config[$k] . '不匹配']);
                }
            }
//
//            dump($exceldata);exit;
            $insertnum = 0;
            $insert_all = [];

            foreach ($exceldata as $k => $data) {
                if ($k == 0) {
                    continue;
                }
                $indata = [];
                $indata['aid'] = aid;
                $indata['formid'] = $id;
                $indata['title'] = $form['name'];
                $indata['mid'] = trim($data[0]);
                foreach($content as $index=>$v){
                    $indata['form'.$index] = trim($data[$index+1]);
                    if($v['key']=='map'){
                        $indata['adr_lon'] = '';
                        $indata['adr_lat'] = '';
                        $address = trim($data[$index+1]);
                        //地图类型获取地址经纬度
                        $mapqq = new \app\commons\MapQQ();
                        $res = $mapqq->addressToLocation($address);
                        if($res['status'] == 1 && $res['latitude']){
                            $indata['adr_lon'] = $res['longitude'];
                            $indata['adr_lat'] = $res['latitude'];
                        }
                    }
                }
                $indata['status'] = 1;
                $indata['createtime'] = time();
                $indata['fromurl'] = '/pages/index/main?id='.$id;
                $insert_all[] = $indata;
                $insertnum++;
            }
            Db::name('form_order')->insertAll($insert_all);
            Db::commit();
            \app\commons\System::plog('导入表单数据');
            return json(['status' => 1, 'msg' => '成功新增' . $insertnum . '条数据']);
        }
    }

    //地图搜索
    public function searchFormMap(){
        $keyword = input('post.keywords');
        $lat = input('post.lat');
        $lng = input('post.lng');

        if(empty($keyword) || empty($lat) || empty($lng)){
            return json(['status' => 0, 'msg' => '参数错误']);
        }
        $mapqq = new \app\commons\MapQQ();
        $results = $mapqq->searchNearbyPlace($keyword,['type'=>'city','lat'=>$lat,'lng'=>$lng],1000,1);
        if($results['status'] == 1){
            if(empty($results['data']) && isset($results['cluster'])){
                return json(['status' => 0, 'msg' => '请输入详细地址']);
            }
            return json(['status' => 1, 'data' => $results['data']]);
        }
        return json(['status' => 0, 'msg' => '请求失败']);
    }
}