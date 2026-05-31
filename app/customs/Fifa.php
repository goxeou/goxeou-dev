<?php


namespace app\customs;
use think\facade\Db;
class Fifa
{
    public function initdata(){

		$config = include('config.php');
		$authkey = $config['authkey'];
		$domain = $_SERVER['HTTP_HOST'];

	//	$rs = request_post('https://www.diandashop.com/index/api/fifa2022',['authkey'=>$authkey,'domain'=>$domain]);
		$rs = json_decode($rs,true);

		$dataList = $rs['data']['dataList'];

		$logoArr = [
			'卡塔尔'=>'A1','厄瓜多尔'=>'A2','塞内加尔'=>'A3','荷兰'=>'A4',
			'英格兰'=>'B1','伊朗'=>'B2','美国'=>'B3','威尔士'=>'B4',
			'阿根廷'=>'C1','沙特阿拉伯'=>'C2','墨西哥'=>'C3','波兰'=>'C4',
			'法国'=>'D1','丹麦'=>'D2','突尼斯'=>'D3','澳大利亚'=>'D4',
			'西班牙'=>'E1','德国'=>'E2','日本'=>'E3','哥斯达黎加'=>'E4',
			'比利时'=>'F1','加拿大'=>'F2','摩洛哥'=>'F3','克罗地亚'=>'F4',
			'巴西'=>'G1','塞尔维亚'=>'G2','瑞士'=>'G3','喀麦隆'=>'G4',
			'葡萄牙'=>'H1','加纳'=>'H2','乌拉圭'=>'H3','韩国'=>'H4',
		];
		if($dataList){
			$i = 0;
			foreach($dataList as $data){
				foreach($data as $k=>$v){
					$i++;
					if($v['matchId']){
						$detail = Db::name('fifa')->where('matchId',$v['matchId'])->find();
					}else{
						$detail = Db::name('fifa')->where('id',$i)->find();
					}
					$indata = [];
					$indata['matchId'] = $v['matchId'];
					$indata['startDate'] = $v['startDate'];
					$indata['startTime'] = $v['startTime'];
					$indata['matchStage'] = $v['matchStage'];
					$indata['matchDesc'] = $v['matchDesc'];
					$indata['matchStatus'] = $v['matchStatus'];
					$indata['matchStatusText'] = $v['matchStatusText'];
					$indata['leftTeam_name'] = $v['leftTeam']['name'];
					$indata['leftTeam_logo'] = PRE_URL.'/static/img/fifa2022/logo/'.($logoArr[$indata['leftTeam_name']] ?? '00').'.png';
					$indata['leftTeam_score'] = $v['leftTeam']['score'];
					$indata['leftTeam_BigScore'] = $v['leftTeam']['BigScore'];
					$indata['leftTeam_penaltyScore'] = $v['leftTeam']['penaltyScore'];
					$indata['rightTeam_name'] = $v['rightTeam']['name'];
					$indata['rightTeam_logo'] = PRE_URL.'/static/img/fifa2022/logo/'.($logoArr[$indata['rightTeam_name']] ?? '00').'.png';
					$indata['rightTeam_score'] = $v['rightTeam']['score'];
					$indata['rightTeam_BigScore'] = $v['rightTeam']['BigScore'];
					$indata['rightTeam_penaltyScore'] = $v['rightTeam']['penaltyScore'];
					$indata['startTimestamp'] = $v['startTimestamp'];
					if(!$detail){
						Db::name('fifa')->insert($indata);
					}else{
						Db::name('fifa')->where('id',$detail['id'])->update($indata);
					}
				}
			}
		}
    }
}