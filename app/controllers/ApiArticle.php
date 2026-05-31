<?php


namespace app\controllers;
use think\facade\Db;
class ApiArticle extends ApiCommon
{
   
	public function getartlist(){
		$pagenum = input('param.pagenum/d');
		if(!$pagenum) $pagenum = 1;
		$cid = input('param.cid/d');
		$bid = input('param.bid') ? input('param.bid') : 0;
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['status','=',1];
		if(getcustom('article_keyword_search')) {
			if(input('param.keyword')){
				$where[] = ['name|subname|content','like','%'.input('param.keyword').'%'];
			}
		}else{
			if(input('param.keyword')){
				$where[] = ['name','like','%'.input('param.keyword').'%'];
			}
		}

        if(getcustom('article_showtj')) {
            $where2 = "find_in_set('-1',showtj)";
            if ($this->member) {
                $where2 .= " or find_in_set('" . $this->member['levelid'] . "',showtj)";
                if ($this->member['subscribe'] == 1) {
                    $where2 .= " or find_in_set('0',showtj)";
                }
            }
            $where[] = Db::raw($where2);
        }
        if(getcustom('article_bind_area')){
            $areaArr = input('param.area');
            if($areaArr){
                $mapArea = ['province','city',"district"];
                foreach ($areaArr as $k=>$v){
                    if($v && $v!='全部') {
                        $where[] = [$mapArea[$k], '=', $v];
                    }
                }
            }
        }
		if($cid){
            $cids = [$cid];
            //子分类
            $downcids = Db::name('article_category')->where('aid',aid)->where('pid',$cid)->column('id');
            if($downcids){
                $cids = array_merge($cids,$downcids);
            }
           
            if(getcustom('article_inplate')){
                $where[] = Db::raw("cid in (".implode(',',$cids).") or pcid={$cid}");
            }else{
                $cidtype = 1;//默认一个分类
                if(getcustom('article_multi_category')) $cidtype = 2;// 多分类
                if($cidtype ==2){
                    if($cids) {
                        $whereCid = [];
                        foreach ($cids as $k => $c2) {
                            $whereCid[] = "find_in_set({$c2},cid)";
                        }
                        $where[] = Db::raw(implode(' or ',$whereCid));
                    }
                }
                if($cidtype ==1){
                    $where[] = ['cid','in',$cids];
                }
            }
            $cinfo = Db::name('article_category')->where('id',$cid)->find();
            $title = $cinfo['name'];
            $bid = $cinfo['bid'];
		}else{
			$title = '文章列表';
		}
		if(getcustom('article_inplate') && $bid==0){
		
		}else{
			$where[] = ['bid','=',$bid];
		}
		$datalist = Db::name('article')->where($where)->order('sort desc,id desc')->page($pagenum,20)->select()->toArray();
		if(!$datalist) $datalist = [];
		$set = Db::name('article_set')->where('aid',aid)->where('bid',$bid)->find();
		foreach($datalist as $k=>$v){
			$datalist[$k]['createtime'] = date('Y-m-d',$v['createtime']);
			if(getcustom('article_portion')){
				if($v['pic']){
					$pic_arr = explode(',',$v['pic']);
					if($set['listtype'] == 5){
						$pic = [];
						if($pic_arr[0]){
							array_push($pic,$pic_arr[0]);
						}
						if($pic_arr[1]){
							array_push($pic,$pic_arr[1]);
						}
						if($pic_arr[2]){
							array_push($pic,$pic_arr[2]);
						}
						$datalist[$k]['pic'] = $pic;
					}else{
						$datalist[$k]['pic'] = $pic_arr[0];
					}
				}
			}
		}
        $showlocation = false;
		if($pagenum == 1){
			$clist = Db::name('article_category')->where('aid',aid)->where('pid',0)->where('bid',$bid)->where('status',1)->order('sort desc,id')->select()->toArray();
            if(getcustom('article_bind_area')){
                $showlocation = true;
            }
		}else{
			$clist = [];
		}
		return $this->json(['status'=>1,'data'=>$datalist,'title'=>$title,'clist'=>$clist,'listtype'=>$set['listtype'],'set'=>$set,'showlocation'=>$showlocation]);
	}
	public function detail(){
		$id = input('param.id/d');
		$detail = Db::name('article')->where('id',$id)->where('status',1)->find();
		if(!$detail) return $this->json(['status'=>0,'msg'=>'文章不存在']);
        if(getcustom('article_showtj')) {
            //显示条件
            if ($detail['showtj'] > 0) {
                $this->checklogin();
                //限制等级
                $levelids = explode(',', $detail['showtj']);
                if (!in_array($this->member['levelid'], $levelids)) {
                    return $this->json(['status' => 0, 'msg' => '文章状态不可见']);
                }
            } elseif ($detail['showtj'] == 0) {
                $this->checklogin();
                //关注用户
                if ($this->member['subscribe'] != 1) {
                    return $this->json(['status' => 0, 'msg' => '文章状态不可见']);
                }
            }
        }
        if(getcustom('article_reward')){
			if($detail){
				//查询打赏会员信息
				$loglist = Db::name('article_reward_order')
					->alias('arl')
					->join('member m','m.id=arl.mid')
					->where('arl.artid',$detail['id'])
					->where('arl.status',1)
					->field('arl.id,m.headimg')
					->select()
					->toArray();
				$detail['reward_num'] = count($loglist);
				$detail['reward_log'] = [];
				if($detail['reward_num']>0 && $loglist){
					if($detail['reward_num']>24){
						$rand_num = 24;
						$rand_log = array_rand($loglist,$rand_num);
					}else{
						if($detail['reward_num'] == 1){
							array_push($detail['reward_log'],$loglist[0]['headimg']);
						}else{
							$rand_num = $detail['reward_num'];
							$rand_log = array_rand($loglist,$rand_num);
						}
					}
					if($rand_log){
						foreach($rand_log as $v){
							array_push($detail['reward_log'],$loglist[$v]['headimg']);
						}
						unset($v);
					}
				}
				if(!$detail['reward_log']){
					$detail['reward_log']='';
				}else{
					shuffle($detail['reward_log']);
				}
			}
		}
        if(getcustom('ext_give_score')){
            \app\models\Score::extGiveScore(aid,$this->mid,'article',$id,'read');
        }
		Db::name('article')->where('id',input('param.id/d'))->where('aid',aid)->inc('readcount')->update();
		$detail['readcount']++;
		$detail['createtime'] = date('Y-m-d',$detail['createtime']);
		$pagecontent = json_decode(\app\commons\System::initpagecontent($detail['content'],aid,mid,platform),true);

        $detail['content'] = '';
		//评论
		if($detail['canpl']){
			$plcount = Db::name('article_pinglun')->where('sid',$id)->where('status',1)->count();
			//是否点赞
			$zanlog = Db::name('article_zanlog')->where('sid',$detail['id'])->where('mid',mid)->find();
			if($zanlog){
				$iszan = 1;
			}else{
				$iszan = 0;
			}
		}
		$detail['article_fabu'] = 0;
        if(getcustom('article_files')){
            //查看下载资源
            $fujian = explode(',',$detail['fujian']);
            $fujian_list = Db::name('admin_upload')->field('id,name,type,url')->where('aid',aid)->where('url','in',$fujian)->select()->toArray();
            
            //查询当前等级时候有查看权限
            $member = Db::name('member')->field('id,levelid')->where('id',mid)->where('aid',aid)->find();
            $auth_resource = Db::name('member_level')->where('aid',aid)->where('id',$member['levelid'])->field('is_look_resource,is_download_resource')->find();
            $detail['fujian_list'] = $fujian_list;
            $resource = Db::name('article_resource')->where('artid',$id)->where('aid',aid)->where('bid',bid)->where('mid',mid)->find();
             if($resource){
                 $detail['is_have'] =1;
             }else{
                 $detail['is_have'] =0;
             }
            $detail['is_look_resource'] = $auth_resource['is_look_resource'];
            $detail['is_download_resource'] = $auth_resource['is_download_resource'];
            
        }
        if(getcustom('article_fabu')){
            $detail['article_fabu'] = 1;
             //查看下载资源
            $fujian = explode(',',$detail['fujian']);
            $fujian_list = Db::name('admin_upload')->field('id,name,type,url')->where('aid',aid)->where('url','in',$fujian)->select()->toArray();
            
            //查询当前等级时候有查看权限
            $member = Db::name('member')->field('id,levelid')->where('id',mid)->where('aid',aid)->find();
            $auth_resource = Db::name('member_level')->where('aid',aid)->where('id',$member['levelid'])->field('is_look_resource,is_download_resource')->find();
            $detail['fujian_list'] = $fujian_list;
            $resource = Db::name('article_resource')->where('artid',$id)->where('aid',aid)->where('bid',bid)->where('mid',mid)->find();
            if($resource){
                $detail['is_have'] =1;
            }else{
                $detail['is_have'] =0;
            }
            
    //         $checkreply = Db::name('member_level')->field('id,checktel,checkreply')->where('id',$member['levelid'])->value('checkreply');
    //         $replynum = Db::name('expert_replylog')->where('mid',mid)->count();
            
    //         if ($checkreply - $replynum > 0) {
    // 			Db::name('expert_replylog')->insert(['aid'=>aid,'mid'=>mid,'proid'=>$id,'type'=>'reply','createtime'=>time()]);
    // 			Db::name('expert_comment_order')->where('id',$id)->update(['paystatus'=>1,'paytype'=>'会员消耗次数']);
    // 			return $this->json(['status'=>1,'msg'=>'查看成功']);
    // 		}
            $detail['is_look_resource'] = 1;
            $detail['is_download_resource'] = 1;
        }
       
        
    
        if(getcustom('form_jingmo_auth')){
            if(platform == 'wx' || platform == 'mp'){
                if(!$this->member) {
                    foreach ($pagecontent as $k => $v) {
                        if ($v['temp'] == 'form') {
                            //is_jingmo 静默登录注册 1:开启 0：关闭
                            if (isset($v['params']['is_jingmo']) && $v['params']['is_jingmo'] == 1) {
                                return $this->json(['status' => -1, 'msg' => '请先登录', 'authlogin' => 2], 1);
                            }
                        }
                    }
                }
            }
        }
		$rdata = [];
		$rdata['status'] = 1;
		$rdata['plcount'] = $plcount;
		$rdata['iszan'] = $iszan;
		$rdata['status'] = 1;
		$rdata['detail'] = $detail;
		$rdata['pagecontent'] = $pagecontent;
		if(getcustom('article_reward')){
			$rdata['reward']      = false;
			$rdata['reward_data'] = [];
			if($detail['mid']>0){
				//查询用户是否存在
				$count_member = Db::name('member')->where('id',$detail['mid'])->where('aid',aid)->count();
				if($count_member){
					$artset = Db::name('article_set')->where('aid',aid)->find();
					if($artset){
						$rdata['reward'] = $artset['reward_status'];
						if($artset['money_data']){
							$rdata['reward_data']['money_data'] = explode(',',$artset['money_data']);
						}else{
							$rdata['reward_data']['money_data'] ='';
						}
						if($artset['score_data']){
							$rdata['reward_data']['score_data'] = explode(',',$artset['score_data']);
						}else{
							$rdata['reward_data']['score_data'] ='';
						}
					}
					if(!$rdata['reward_data']){
						$rdata['reward_data'] = '';
					}
				}
			}
		}
		if(getcustom('article_gather')){
			//采集样式
			if($detail['is_gather']){
				$rdata['richtype'] = 5;
			}else{
				$rdata['richtype'] = 0;
			}
		}
		return $this->json($rdata);
	}
	//获取评论数据
	public function getpllist(){
		$id = input('param.id/d');
		$pernum = 20;
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$datalist = Db::name('article_pinglun')->where('sid',$id)->where('status',1)->page($pagenum,$pernum)->order('createtime desc')->select()->toArray();
		if(!$datalist) $datalist = array();
		foreach($datalist as $k=>$v){
			$rs = Db::name('article_pzanlog')->where('pid',$v['id'])->where('mid',mid)->find();
			if($rs){
				$v['iszan'] = 1;
			}else{
				$v['iszan'] = 0;
			}
			//回复
			$replylist = Db::name('article_pinglun_reply')->field('nickname,headimg,content,createtime')->where('pid',$v['id'])->where('status',1)->order('createtime')->select()->toArray();
			foreach($replylist as $k2=>$v2){
				$v2['createtime'] = getshowtime($v2['createtime']);
				$v2['content'] = getshowcontent($v2['content']);
				$replylist[$k2] = $v2;
			}
			$v['replylist'] = $replylist;
			$v['content'] = nl2br(getshowcontent($v['content']));
			$v['createtime'] = getshowtime($v['createtime']);
			$datalist[$k] = $v;
		}
		return $this->json(['status'=>1,'data'=>$datalist]);
	}
	//点赞
	public function zan(){
		$this->checklogin();
		if(!mid){
			return $this->json(['status'=>0,'msg'=>'请先登录']);
		}
		$id = input('post.id/d');
		$detail = Db::name('article')->where('id',$id)->find();
		$zanlog = Db::name('article_zanlog')->where('sid',$id)->where('mid',mid)->find();
		if($zanlog){
			Db::name('article_zanlog')->where('sid',$id)->where('mid',mid)->delete();
			$type = 0;
			Db::name('article')->where('id',$id)->dec('zan')->update();
		}else{
			$data = [];
			$data['aid'] = aid;
			$data['bid'] = $detail['bid'];
			$data['sid'] = $id;
			$data['mid'] = mid;
			$data['createtime'] = time();
			Db::name('article_zanlog')->insert($data);
			$type = 1;
			Db::name('article')->where('id',$id)->inc('zan')->update();
		}
		$zancount = Db::name('article')->where('id',$id)->value('zan');
		return $this->json(['status'=>1,'type'=>$type,'zancount'=>$zancount]);
	}
	//评论
	public function subpinglun(){
		$this->checklogin();
		$id = input('param.id/d');
		$type = input('param.type/d');
		$hfid = input('param.hfid/d');
		$content = trim(input('param.content'));
		if(!$id){
			return $this->json(['status'=>0,'msg'=>'参数错误']);
		}
		if(!mid){
			return $this->json(['status'=>0,'msg'=>'请先登录']);
		}
		$detail = Db::name('article')->where('id',$id)->where('status',1)->find();
		if($detail['canpl']==0) return $this->json(['status'=>0,'msg'=>'评论功能未开启']);
		if($hfid && $detail['canplrp']==0) return $this->json(['status'=>0,'msg'=>'评论回复功能未开启']);

		if($content==''){
			return $this->json(['status'=>1,'msg'=>'请输入评论内容']);
		}
		$sysset =Db::name('admin_set')->where('aid',aid)->find();
		if($type==0){
			$data = [];
			$data['aid'] = aid;
			$data['mid'] = mid;
			$data['bid'] = $detail['bid'];
			$data['sid'] = $id;
			$data['headimg'] = $this->member['headimg'];
			$data['nickname'] = $this->member['nickname'];
			$data['content'] = $content;
			$data['createtime'] = time();
			if($detail['pinglun_check']==1){
				$data['status'] = 0;
				$msg = '提交成功，请等待审核';
			}else{
				$data['status'] = 1;
				$msg = '发表评论成功';
			}
			Db::name('article_pinglun')->insert($data);
		}else{
			$data = [];
			$data['aid'] = aid;
			$data['mid'] = mid;
			$data['bid'] = $detail['bid'];
			$data['sid'] = $id;
			$data['pid'] = $hfid;
			$data['headimg'] = $this->member['headimg'];
			$data['nickname'] = $this->member['nickname'];
			$data['content'] = $content;
			$data['createtime'] = time();
			if($detail['pinglun_check']==1){
				$data['status'] = 0;
				$msg = '提交成功，请等待审核';
			}else{
				$data['status'] = 1;
				$msg = '发表评论成功';
			}
			Db::name('article_pinglun_reply')->insert($data);
		}
		return $this->json(['status'=>1,'msg'=>$msg,'url'=>true]);
	}
	//评论点赞
	public function pzan(){
		$this->checklogin();
		if(!mid){
			return $this->json(['status'=>0,'msg'=>'请先登录']);
		}
		$id = input('post.id/d');
		$pinglun = Db::name('article_pinglun')->where('id',$id)->find();
		$zanlog = Db::name('article_pzanlog')->where('pid',$id)->where('mid',mid)->find();
		if($zanlog){
			Db::name('article_pzanlog')->where('pid',$id)->where('mid',mid)->delete();
			$type = 0;
			Db::name('article_pinglun')->where('id',$id)->dec('zan')->update();
		}else{
			$data = [];
			$data['aid'] = aid;
			$data['bid'] = $pinglun['bid'];
			$data['pid'] = $id;
			$data['mid'] = mid;
			$data['createtime'] = time();
			Db::name('article_pzanlog')->insert($data);
			$type = 1;
			Db::name('article_pinglun')->where('id',$id)->inc('zan')->update();
		}
		$zancount = Db::name('article_pinglun')->where('id',$id)->value('zan');
		return $this->json(['status'=>1,'type'=>$type,'zancount'=>$zancount]);
	}

	public function reward(){
        if(getcustom('article_reward')){
            $this->checklogin();
            if(!mid){
                return $this->json(['status'=>0,'msg'=>'请先登录']);
            }
            $id = input('post.id/d');
            $detail = Db::name('article')->where('id',$id)->where('status',1)->find();
            if(!$detail){
            	return $this->json(['status'=>0,'msg'=>'文章不存在']);
            }
            if(!$detail['mid'] ||$detail['mid']<=0){
            	return $this->json(['status'=>0,'msg'=>'打赏功能暂未开启']);
            }
            //查询绑定的打赏用户是否存在
            $count_member = Db::name('member')->where('id',$detail['mid'])->where('aid',aid)->count();
            if(!$count_member){
            	return $this->json(['status'=>0,'msg'=>'打赏功能暂未开启']);
            }

            $artset = Db::name('article_set')->where('aid',aid)->find();
            if(!$artset){
                return $this->json(['status'=>0,'msg'=>'系统设置不存在']);
            }
            if(!$artset['reward_status']){
                return $this->json(['status'=>0,'msg'=>'打赏功能暂未开启']);
            }

            $reward_type = input('post.reward_type/d');
            if($reward_type != 1 && $reward_type != 2){
                return $this->json(['status'=>0,'msg'=>'打赏类型错误']);
            }

            $reward_num  = trim(input('post.reward_num'));
            if(!is_numeric($reward_num)){
                return $this->json(['status'=>0,'msg'=>'打赏数额错误']);
            }
            $reward_num = round($reward_num,2);
            if($reward_num<=0){
                return $this->json(['status'=>0,'msg'=>'打赏数额必须大于0']);
            }

            $data = [];
            $data['aid']      = aid;
            $data['bid']      = $detail['bid'];
            $data['ordernum'] = 'D'.date("YmdHis").rand(111111,999999);
            $data['mid']      = mid;
            $data['send_mid'] = $detail['mid'];
            $data['artid']    = $id;
            $data['type']     = $reward_type;
            $data['num']      = $reward_num;

            if($reward_type!=1){
                if($detail['bid']>0){
                    return $this->json(['status'=>0,'msg'=>'打赏失败，商家暂不支持'.t('积分').'打赏']);
                }
                if($this->member['score']<$reward_num){
                    return $this->json(['status'=>0,'msg'=>t('积分').'不足']);
                }
                //必须为整数
                $pos = strpos($reward_num,'.');
                if($pos ===0 || $pos>0){
                    return $this->json(['status'=>0,'msg'=>t('积分').'必须为整数']);
                }

                $dec  = false;//用户减少默认
                $b_inc= true;//打赏用户增加默认

                Db::startTrans();
                try{
                    $dec = \app\commons\Member::addscore(aid,mid,-$reward_num,'文章打赏');
                    if($dec && $dec['status'] == 1){
                        $dec = \app\commons\Member::addscore(aid,$detail['mid'],$reward_num,'文章打赏');
                    }
                    Db::commit();
                }catch(Exception $e){
                    Db::rollback();
                }
                //用户打赏情况
                if(!$dec){
                    Db::rollback();
                    return $this->json(['status'=>0,'msg'=>'打赏失败']);
                }
                if($dec && $dec['status'] != 1){
                    Db::rollback();
                    return $this->json(['status'=>0,'msg'=>$dec['msg']]);
                }

                //打赏用户情况
                if(!$b_inc){
                    Db::rollback();
                    return $this->json(['status'=>0,'msg'=>'打赏失败']);
                }
                if($detail['bid']>0 && $b_inc && $b_inc['status'] != 1){
                    Db::rollback();
                    return $this->json(['status'=>0,'msg'=>'打赏失败，商家暂不支持'.t('积分').'打赏']);
                }

                $data['status']  = 1;
                $data['paytime'] = time();
            }

            $data['createtime'] = time();
            $orderid = Db::name('article_reward_order')->insertGetId($data);
            if($reward_type == 2){
                return $this->json(['status'=>1,'msg'=>'打赏成功']);
            }else{
            	$payorderid = \app\models\Payorder::createorder(aid,bid,$data['mid'],'article_reward',$orderid,$data['ordernum'],$detail['title'],$data['num']);
                return $this->json(['status'=>1,'payorderid'=>$payorderid,'msg'=>'操作成功']);
            }
        }
    }
    
    /**
     * 赠送积分 
     */
    public function giveScorenum(){
        \think\facade\Log::write('文章分享赠送');
        if(mid <= 0) return $this->json(['status'=>0,'msg'=>'未增加']);
        $set = Db::name('article_set')->where('aid',aid)->where('bid',0)->find();
         //判断上限
        $start_time =strtotime(date('Y-m-d 00:00:00',time()));
        $end_time = $start_time+86399;
       
        $totalSocre =  Db::name('member_scorelog')
            ->where('aid',aid)
            ->where('mid',mid)
            ->where('createtime','between',[$start_time,$end_time])
            ->where('score','>',0)
            ->where('remark','文章转发赠送')
            ->sum('score');
        $sy_score = $set['share_score_max_perday'] -  $totalSocre ;
        if($sy_score >=$set['share_score'] &&  $totalSocre < $set['share_score_max_perday'] && $set['share_score'] > 0){
            $r =  \app\commons\Member::addscore(aid,mid,$set['share_score'],'文章转发赠送');
            if($r){
                return $this->json(['status'=>1,'msg'=>'增加积分成功']);
            }else{
                return $this->json(['status'=>0,'msg'=>'未增加']);
            }
        }else{
            return $this->json(['status'=>0,'msg'=>'未增加']);
        }
      
    }
    
    //会员获取附件资源存入
    public function getResourceSave(){
        if(getcustom('article_files')){
            $this->checklogin();
            $id = input('param.id');
            $detail = Db::name('article')->where('id',$id)->where('status',1)->find();
            if(!$detail) return $this->json(['status'=>0,'msg'=>'文章不存在']);
            if(empty($detail['fujian'])){
                return $this->json(['status'=>0,'msg'=>'无资源可获取']);
            }
            //查询是否已存在
            $resource = Db::name('article_resource')->where('artid',$id)->where('aid',aid)->where('bid',$detail['bid'])->where('mid',mid)->find();
            if(empty($resource)){
                $data = [
                    'aid' => aid,
                    'bid' => $detail['bid'],
                    'mid' => mid,
                    'artid' => $id,
                    'createtime' => time()
                ];
                $res = Db::name('article_resource')->insert($data);
                if(!$res){
                    return $this->json(['status'=>0,'msg'=>'获取资源失败']); 
                }
            }
            return $this->json(['status'=>1,'msg'=>'获取成功']);
        }
        if(getcustom('article_fabu')){
            $this->checklogin();
            $id = input('param.id');
            $detail = Db::name('article')->where('id',$id)->where('status',1)->find();
            if(!$detail) return $this->json(['status'=>0,'msg'=>'文章不存在']);
            if(empty($detail['fujian'])){
                return $this->json(['status'=>0,'msg'=>'无资源可获取']);
            }
            //查询是否已存在
            $resource = Db::name('article_resource')->where('artid',$id)->where('aid',aid)->where('bid',$detail['bid'])->where('mid',mid)->find();
            if(empty($resource)){
                if ($detail['money'] <= 0) {
                   	$data = [
                        'aid' => aid,
                        'bid' => $detail['bid'],
                        'mid' => mid,
                        'artid' => $id,
                        'createtime' => time()
                    ];
                    $res = Db::name('article_resource')->insert($data);
            		if(!$res){
                        return $this->json(['status'=>0,'msg'=>'获取资源失败']); 
                    }
        			return $this->json(['status'=>1,'msg'=>'获取成功']);
                }
                $replynum = Db::name('expert_replylog')->where('mid',mid)->count();
        		$article_resource = Db::name('article_resource')->where('mid',mid)->count();
        		$replynum = $replynum+$article_resource;
        		$checkreply = Db::name('member_level')->field('id,checktel,checkreply')->where('id',$this->member['levelid'])->value('checkreply');
                if ($checkreply - $replynum > 0) {
            		$data = [
                        'aid' => aid,
                        'bid' => $detail['bid'],
                        'mid' => mid,
                        'artid' => $id,
                        'createtime' => time()
                    ];
                    $res = Db::name('article_resource')->insert($data);
            		if(!$res){
                        return $this->json(['status'=>0,'msg'=>'获取资源失败']); 
                    }
        			return $this->json(['status'=>1,'msg'=>'获取成功']);
        		}else {
        		    //生成保证金订单，跳转支付
        	    	$ordernum = date('ymdHis').aid.rand(1000,9999);
					$money = $detail['money'];
					$orderdata = [];
					$orderdata['aid'] = aid;
					$orderdata['mid'] = mid;
					$orderdata['bid'] = $detail['bid'];
					$orderdata['artid'] = $id;
					$orderdata['createtime']= time();
					$orderdata['money'] = $money;
					$orderdata['ordernum'] = $ordernum;
					$orderid = Db::name('article_resource_order')->insertGetId($orderdata);
        			$payorderid = \app\models\Payorder::createorder(aid,0,mid,'article_resource',$orderid,$ordernum,'文章附件下载费',$money);
        			return $this->json(['status'=>-3,'msg'=>'提交成功，请支付￥ '.$money.'后查看','url'=>'/pagesExt/pay/pay?id='.$payorderid,'orderid'=>$orderid,'payorderid'=>$payorderid]);
        		}   
                if(!$res){
                    return $this->json(['status'=>0,'msg'=>'获取资源失败']); 
                }
            }
            return $this->json(['status'=>1,'msg'=>'获取成功']);
        }
    }
    //获取已获取资源列表
    public function getResourceList(){
        if(getcustom('article_files') || getcustom('article_fabu')){
            $this->checklogin();
            $pernum = 20;
            $pagenum = input('post.pagenum');
            $where[] = ['ar.aid','=',aid];
            $where[] = ['ar.mid','=',mid];
            if(input('param.keyword')){
                $where[] = ['a.name|a.subname','like','%'.input('param.keyword').'%'];
            }
            $list = Db::name('article_resource')->alias('ar')
                ->join('article a','a.id = ar.artid')
                ->where($where)
                ->page($pagenum,$pernum)
                ->order('ar.createtime desc')
                ->field('ar.*,a.name,a.pic,a.subname')
                ->select()->toArray();
            foreach ($list as $key=>&$val){
                $val['createtime'] = date('Y-m-d',$val['createtime']);
            }
            return $this->json(['status'=>1,'data'=>$list]);
        }
    }
    //获取附件
    public function getFujian(){
        if(getcustom('article_files') || getcustom('article_fabu')){
            $id = input('post.id');
            $data = Db::name('article_resource')->where('id',$id)->find();
            if(!$data){
                return $this->json(['status'=>0,'msg'=>'资源不存在']);
            }
            $fujian_str = Db::name('article')->where('id',$data['artid'])->value('fujian');
            $fujian_arr = explode(',',$fujian_str);
            $fujian = Db::name('admin_upload')->field('id,name,type,url')->where('aid',aid)->where('url','in',$fujian_arr)->select()->toArray();
            
            //查询当前等级时候有查看权限
            $member = Db::name('member')->field('id,levelid')->where('id',mid)->where('aid',aid)->find();
            
            $auth_resource = Db::name('member_level')->where('aid',aid)->where('id',$member['levelid'])->field('is_look_resource,is_download_resource')->find();
            $rdata = [];
            $rdata['fujian'] = $fujian?$fujian:[];
            $rdata['is_download_resource'] =$auth_resource['is_download_resource']?$auth_resource['is_download_resource']:0; 
            $rdata['is_look_resource'] =$auth_resource['is_look_resource']?$auth_resource['is_look_resource']:0; 
            return $this->json(['status'=>1,'data'=>$rdata]);
        }
    }
}