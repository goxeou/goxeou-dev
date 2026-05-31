<?php
date_default_timezone_set('Asia/Shanghai');
header('Content-type:text/html;charset=utf-8');

class sdpay
{
 
    /*
    |--------------------------------------------------------------------------
    | step2. 请求
    |--------------------------------------------------------------------------
    */
    // curl请求接口
    public function request($apiMap,$body)
    {
        $config = include(__DIR__ .'/config.php');
        $url      = $config['apiUrl'] . $apiMap['url'];
        $config = $config['variable'];
        $data = array(
           'head'=>array(
                'version'     => '1.0',
                'method'      => $apiMap['method'],
                'productId'   => $apiMap['productId'],
                'accessType'  => $config['accessType'],
                'mid'         => $config['mid'],
                'plMid'       => $config['plMid'],
                'channelType' => $config['channelType'],
                'reqTime'     => date('YmdHis', time()),
            ),
            'body' => $body
        );
        $postData = array(
            'charset'  => 'utf-8',
            'signType' => '01',
            'data'     => json_encode($data),
            'sign'     =>  $this->sign($data),
        );
        $ret    = $this->httpPost($url, $postData);
        $retAry = $this->parseResult($ret);
        return $retAry;
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
    // curl.解析返回数据
    protected function parseResult($result)
    {
        $arr      = array();
        $response = urldecode($result);
        $arrStr   = explode('&', $response);
        foreach ($arrStr as $str) {
            $p         = strpos($str, "=");
            $key       = substr($str, 0, $p);
            $value     = substr($str, $p + 1);
            $arr[$key] = $value;
        }

        return $arr;
    }
    
   
    /*
    |--------------------------------------------------------------------------
    | step3.签名 + 验签
    |--------------------------------------------------------------------------
    */

    // 公钥
    private function publicKey()
    {
        try {
            $config = include(__DIR__ .'/config.php');
            $file = file_get_contents($config['publicKeyPath']);
            if (!$file) {
                throw new \Exception('getPublicKey::file_get_contents ERROR 公钥文件读取有误,config文件夹中进行修改');
            }
            $cert   = chunk_split(base64_encode($file), 64, "\n");
            $cert   = "-----BEGIN CERTIFICATE-----\n" . $cert . "-----END CERTIFICATE-----\n";
            $res    = openssl_pkey_get_public($cert);
            $detail = openssl_pkey_get_details($res);
            openssl_free_key($res);
            if (!$detail) {
                throw new \Exception('getPublicKey::openssl_pkey_get_details ERROR 公钥文件解析有误');
            }
  
            return $detail['key'];
        } catch (\Exception $e) {
            throw $e;
        }
    }

    // 私钥
    private function privateKey()
    {
        try {
            $config = include(__DIR__.'/config.php');
            
            $file = file_get_contents($config['privateKeyPath']);
            if (!$file) {
                throw new \Exception('getPrivateKey::file_get_contents 私钥文件读取有误,config文件夹中进行修改');
            }
            if (!openssl_pkcs12_read($file, $cert, $config['privateKeyPwd'])) {
                throw new \Exception('getPrivateKey::openssl_pkcs12_read ERROR 私钥密码错误，config文件夹中进行修改');
            }
            return $cert['pkey'];
        } catch (\Exception $e) {
            throw $e;
        }
    }

    // 私钥加签
    public function sign($plainText)
    {
        $plainText = json_encode($plainText);
        try {
            $resource = openssl_pkey_get_private($this->privateKey());
            $result   = openssl_sign($plainText, $sign, $resource);
            openssl_free_key($resource);
            if (!$result) throw new \Exception('sign error');
            return base64_encode($sign);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    // 公钥验签
    public function verify($plainText, $sign)
    {
        $resource = openssl_pkey_get_public($this->publicKey());
        $result   = openssl_verify($plainText, base64_decode($sign), $resource);
        openssl_free_key($resource);
        // if (!$result) {
        //      return 0;
        //     throw new \Exception('签名验证未通过,plainText:' . $plainText . '。sign:' . $sign);
        // }
        return $result;
    }
}


