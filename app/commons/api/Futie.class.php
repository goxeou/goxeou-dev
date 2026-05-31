<?php
date_default_timezone_set('Asia/Shanghai');
header('Content-type:text/html;charset=utf-8');

class Futie
{
  
    public $gatewayUrl = "https://a.xfpay.cn/Api/Api.ashx";
    public $wayUrl = "https://agent.mobile.xfpay.cn/#/mch";
    //应用ID -- 代理商或商户ID
    public $p1_plat_id = "100000001";
    public $mchkey = "2FFE2BCF56C4F99C77A1FB2EB8EAADC0";
    // curl请求接口
    public function request2($postData,$mchkey)
    {
        $url    = $this->wayUrl;
        $params['hmac'] = $this->sign($postData,$mchkey);
        $postData = array_merge($postData,$params);
        $ret    = $this->curl_get($url, $postData);
        return $ret;
    }
    public function request($postData,$mchkey)
    {
        $url    = $this->gatewayUrl;
       
        $params['hmac'] = $this->sign($postData,$mchkey);
        $postData = array_merge($postData,$params);
        $path = '/www/wwwroot/cece.xcx66.top/app/Log/mchkey.txt';
        $s = print_r($postData, true);
        file_put_contents($path, $s);
        $ret    = $this->httpPost($url, $postData);
        return $ret;
    }
    function curl_get($url,$keysArr=array(),$headers=array()){
    	if(!empty($keysArr)){
    		$url = strpos($url,'?')===false ? ($url."?") : ($url."&");
    		$valueArr = array();
    		foreach($keysArr as $key => $val){
    			$valueArr[] = "$key=$val";
    		}
    		$keyStr = implode("&",$valueArr);
    		$url .= ($keyStr);
    	}
    	$ch = curl_init();
    	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    	curl_setopt($ch, CURLOPT_URL, $url);
    	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    	if($headers){
    		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    	}
    	try {
    		$response = curl_exec($ch);
    	} catch (\Exception $e) {
           return null;
        }
    	$curlError = curl_error($ch);
        if(!empty($curlError)) {
           return null;
        }
    	curl_close($ch);
    	//-------请求为空
    	if(empty($response)){
    		return null;
    	}
    	return $response;
    }
    // curl. 发送请求
    public function httpPost($url, $params)
    {
        if (empty($url) || empty($params)) {
            throw new \Exception('请求参数错误');
        }
        $params = http_build_query($params);
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            $data  = curl_exec($ch);
            $err   = curl_error($ch);
            $errno = curl_errno($ch);
            if ($errno) {
                $msg = 'curl errInfo: ' . $err . ' curl errNo: ' . $errno;
                throw new \Exception($msg);
            }
            curl_close($ch);
            return $data;
        } catch (\Exception $e) {
            if ($ch) curl_close($ch);
            throw $e;
        }
    }
  
    // 私钥加签
    public function sign($package,$mchkey)
    {
       // ksort($package, SORT_STRING);
		$string1 = '';
		foreach ($package as $key => $v) {
			if(is_array($v)){
				$string1 .= "{$key}=".json_encode($v)."&";
			}else{
				$string1 .= "{$key}={$v}&";
			}
		}
		$string1 .= "key=".$mchkey;
		return strtoupper(md5($string1));
    }
    function getRandStr($length = 8)
    {
        // 随机字符集，可任意添加你需要的字符
        $chars = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9',
            'a', 'b', 'c', 'd', 'e', 'f', 'g',
            'h', 'i', 'j', 'k', 'l', 'm', 'n',
            'o', 'p', 'q', 'r', 's', 't',
            'u', 'v', 'w', 'x', 'y', 'z',
            'A', 'B', 'C', 'D', 'E', 'F', 'G',
            'H', 'I', 'J', 'K', 'L', 'M', 'N',
            'O', 'P', 'Q', 'R', 'S', 'T',
            'U', 'V', 'W', 'X', 'Y', 'Z');
        // 在 $chars 中随机取 $length 个数组元素键名
        $keys = array_rand($chars, $length);
        $randStr = '';
        for ($i = 0; $i < $length; $i++) {
            // 将 $length 个数组元素连接成字符串
            $randStr .= $chars[$keys[$i]];
        }
        return $randStr;
    }
}


