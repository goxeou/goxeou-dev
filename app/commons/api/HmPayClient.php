<?php
date_default_timezone_set('Asia/Shanghai');
header('Content-type:text/html;charset=utf-8');

class HmPayClient
{
    //网关地址
    public $gatewayUrl = "https://hmpay.sandpay.com.cn/gateway/api";
    public $gatewayUrl1 = "https://cashier.sandgate.cn/gateway/trade";
    //应用ID -- 代理商或商户ID
    public $appId = "66330700003024600";
    //子应用ID -- 代理商下的商户ID 非代理商不填
    public $subAppId = "";
    //私钥值
    public $rsaPrivateKey = "MIICXgIBAAKBgQCOt9B0mz7uHDr+sHD1MM6Gl6xZhb4RFY5C35MyYrSxNJXxTCITjSC63X7gSwZtmO9Re+DUpcLKUb1eIFDuXPziTBHBjTFqj9dbpcP3KnmFh2qRt88C4smle8JfmVyKzZUDaaxPE126eXr/FMVCqyomiuyvQL/GfUkfHDlZZSY64wIDAQABAoGAc7BTXUK/R3tA41YZqtgugfIPNt5wTR8BG/pqMszKll7/MQO7F8guAOwtvhlzE4KGdLILdbUM6r5J2DQwBpkAMCbQZ0bXUTb4gQsSHT68Tf5skhz4bN9ajfL/Psl4E62qyzV8615fRWuyAWqn0utFsOudTqnGrrrc8/jPyLm68bkCQQDF3G2WjMx3OIK515L28xzLjHLuAyBo/p0o5WJxvZpIkhWfK26m2DxfB49H1p0HCoDpS27SVca8DGofAeyGgjtnAkEAuKdmE4O6lC+l6pe35TO5Zvs6arL8j6T3mEq6n2Xv3FJJLbyheWYqmlhjXCWAcu9Y1RHaD1Aa5Yny3mf3rt0TJQJBALilhP/CVe3MpvKX42biajTq1TaZZF2Xf0LzDnPm5VxkOBlahuDdWzmz7Fq2RcSUYMlWxA02T0ierUpSmVDNqHECQQC3Vo4FJi+kXkLQT99olRiqZRq1Qg192f9zmA7/oMis6o55+OYikQwPv1636QxGEdsTguwNdC2gZn1b0cKcPih5AkEAlGGsXnWGPsw4D4CFmWsQcSGxWJFeuyD1opkW7YYwHkJPxpB97LV8GFCh7x5UasqiL32+dtArVV4J4b8PbAL3JA==";
    //平台公钥
    public $platRsaPublicKey = "MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQCAr2G3im37VTZUuYNLkPfmfIAJR38jGk3F27PBbZMKerBSYUoG2eVk8tmqO/ZJQaVFaSlTx1RgX0MOIJKwr/0yp659lUElLL7Uw+i4WtVOCsJDbADmYf4MsbNHrgIivQssvtawnpcNlnWlMAu2A80QJ6C801FaFTGcPl8cvIfYPwIDAQAB";
    //字符集编码
    public $charset = "UTF-8";
    //签名类型
    public $signType = "RSA";
    //数据格式
    public $format = "JSON";
    //api版本
    public $apiVersion = "1.0";

   public function build_pay($method,$bizContent,$u=0)
    {
    
        $url = $this->gatewayUrl;
        if ($u==1) {
            $url = $this->gatewayUrl1;
        }
        $appId = $this->appId;
        $subAppId = $this->subAppId;
        $method = $method;
        $format = $this->format;
        $charset = $this->charset;
        $signType = $this->signType;
        $version = $this->apiVersion;
        $timestamp = date("Y-m-d h:i:s");
        $nonce = $this->getRandStr(8);
        $privateKey = $this->rsaPrivateKey;
        //支付方式
        $client = $this;
        $response = $client->execute($url, $client->request($appId, $subAppId, $method, $format, $charset, $signType, $timestamp, $nonce, $version, $bizContent, $privateKey));
        $jsonResp = json_decode($response, JSON_UNESCAPED_UNICODE);
        return $jsonResp;
        try {
            $client->checkResponseSign($jsonResp, $this->platRsaPublicKey, $signType);
        } catch (Exception $e) {
            error_log($e->getMessage());
            //todo 验签异常或失败
        }
    }

    public function notifyVerifyTest()
    {
        $str = "商户回调获取到GET请求参数";
        parse_str($str, $arr);
        //遍历打印
        var_export($arr);
        //sign_type参与签名调用V2
        $verifyB = $this->rsaCheckV2($arr, $this->platRsaPublicKey, $this->signType);
        //echo "验签" . ($verifyB ? '通过' : '不通过');
    }

    public function execute($url, $request)
    {
       // echo $request . "\n";
        $response = null;
        try {
            $response = $this->curl($url, $request);
        } catch (Exception $e) {
            error_log($e->getmessage());
        }
      //  echo $response . "\n";
        return $response;
    }

    public function request($appId, $subAppId, $method, $format, $charset, $signType, $timestamp, $nonce, $version, $bizContent, $privateKey)
    {
        $tradeRequest = array();
        $tradeRequest['app_id'] = $appId;
        $tradeRequest['sub_app_id'] = $subAppId;
        $tradeRequest['method'] = $method;
        $tradeRequest['format'] = $format;
        $tradeRequest['charset'] = $charset;
        $tradeRequest['sign_type'] = $signType;
        $tradeRequest['timestamp'] = $timestamp;
        $tradeRequest['nonce'] = $nonce;
        $tradeRequest['version'] = $version;
        $tradeRequest['biz_content'] = $bizContent;
        $tradeRequest['sign'] = $this->rsaSign($tradeRequest, $privateKey, $signType);
        $request = json_encode($tradeRequest, JSON_UNESCAPED_UNICODE);
        return $request;
    }
    /**
     * 验签
     * @param $respObject
     * @param $rsaPublicKey
     * @param string $signType
     * @throws Exception
     */
    public function checkResponseSign($respObject, $rsaPublicKey, $signType = 'RSA')
    {
        if (!$this->checkEmpty($rsaPublicKey) && !empty($respObject)) {
            //获取结果code
            $respCode = array_key_exists('code', $respObject) ? $respObject['code'] : null;
            $sign = array_key_exists('sign', $respObject) ? $respObject['sign'] : null;
            if ((!$this->checkEmpty($respCode) && $respCode === 200) || !$this->checkEmpty($sign)) {
                $checkResult = $this->rsaCheckV2($respObject, $rsaPublicKey, $signType);
                return "验签" . ($checkResult ? '通过' : '不通过');
                if (!$checkResult) {
                    throw new Exception("check sign Fail!");
                }
            }
        }
    }

    public function rsaSign($params, $privateKey, $signType = "RSA")
    {
        return $this->sign($this->getSignContent($params), $privateKey, $signType);
    }

    public function getSignContent($params)
    {
        ksort($params);

        $stringToBeSigned = "";
        $i = 0;
        foreach ($params as $k => $v) {
            if (false === $this->checkEmpty($v) && "@" != substr($v, 0, 1)) {
                // 转换成目标字符集
                //$v = $this->doCharset($v, $this->charset);

                if ($i == 0) {
                    $stringToBeSigned .= "$k" . "=" . "$v";
                } else {
                    $stringToBeSigned .= "&" . "$k" . "=" . "$v";
                }
                $i++;
            }
        }

        unset ($k, $v);
        return $stringToBeSigned;
    }

    protected function sign($data, $privateKey, $signType = "RSA")
    {
        $res = "-----BEGIN RSA PRIVATE KEY-----\n" .
            wordwrap($privateKey, 64, "\n", true) .
            "\n-----END RSA PRIVATE KEY-----";

        ($res) or die('您使用的私钥格式错误，请检查RSA私钥配置');

        if ("RSA2" == $signType) {
            openssl_sign($data, $sign, $res, OPENSSL_ALGO_SHA256);
        } else {
            openssl_sign($data, $sign, $res);
        }

        $sign = base64_encode($sign);
        return $sign;
    }

    /**
     * RSA单独签名方法，未做字符串处理,字符串处理见getSignContent()
     * @param string $data 待签名字符串
     * @param string $privateKey 商户私钥
     * @param string $signType 签名方式，RSA:SHA1     RSA2:SHA256
     * @return string
     */
    public function aloneRsaSign($data, $privateKey, $signType = "RSA")
    {
        $priKey = $privateKey;
        $res = "-----BEGIN RSA PRIVATE KEY-----\n" .
            wordwrap($priKey, 64, "\n", true) .
            "\n-----END RSA PRIVATE KEY-----";

        ($res) or die('您使用的私钥格式错误，请检查RSA私钥配置');

        if ("RSA2" == $signType) {
            openssl_sign($data, $sign, $res, OPENSSL_ALGO_SHA256);
        } else {
            openssl_sign($data, $sign, $res);
        }

        $sign = base64_encode($sign);
        return $sign;
    }

    /**
     * 校验$value是否非空
     * if not set ,return true;
     * if is null , return true;
     **/
    protected function checkEmpty($value)
    {
        if (!isset($value))
            return true;
        if ($value === null)
            return true;
        if (trim($value) === "")
            return true;

        return false;
    }

    /**
     * rsaCheckV2
     * 验证签名
     * sign_type参与签名
     **/
    public function rsaCheckV2($params, $rsaPublicKey, $signType = 'RSA')
    {
        $sign = $params['sign'];

        unset($params['sign']);
        return $this->verify($this->getCheckSignContent($params), $sign, $rsaPublicKey, $signType);
    }

    function getCheckSignContent($params)
    {
        ksort($params);

        $stringToBeSigned = "";
        $i = 0;
        foreach ($params as $k => $v) {
            // 转换成目标字符集
            // $v = $this->doCharset($v, $this->charset);

            if ($i == 0) {
                $stringToBeSigned .= "$k" . "=" . "$v";
            } else {
                $stringToBeSigned .= "&" . "$k" . "=" . "$v";
            }
            $i++;
        }

        unset ($k, $v);
        return $stringToBeSigned;
    }

    function verify($data, $sign, $rsaPublicKey, $signType = 'RSA')
    {
        $pubKey = $rsaPublicKey;
        $res = "-----BEGIN PUBLIC KEY-----\n" .
            wordwrap($pubKey, 64, "\n", true) .
            "\n-----END PUBLIC KEY-----";

        ($res) or die('RSA公钥错误。请检查公钥文件格式是否正确');

        //调用openssl内置方法验签，返回bool值
        $result = FALSE;
        if ("RSA2" == $signType) {
            $result = (openssl_verify($data, base64_decode($sign), $res, OPENSSL_ALGO_SHA256) === 1);
        } else {
            $result = (openssl_verify($data, base64_decode($sign), $res) === 1);
        }

        return $result;
    }

    /**
     * 转换字符集编码
     * @param $data
     * @param $targetCharset
     * @return string
     */
    function doCharset($data, $targetCharset)
    {

        if (!empty($data)) {
            $fileType = $this->charset;
            if (strcasecmp($fileType, $targetCharset) != 0) {
                $data = mb_convert_encoding($data, $targetCharset, $fileType);
                //$data = iconv($fileType, $targetCharset.'//IGNORE', $data);
            }
        }
        return $data;
    }

    /**
     * 生成0-9a-ZA-Z随机数
     * @param int $length 输出长度
     * @return string
     */
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

    /**
     * 利用php的uniqid函数 规定前缀为yyyyMM，返回值末尾带熵的值并替换小数点
     * 若是分布式服务，可以参考使用SnowFlake的PHP实现
     * @return string
     */
    function buildOrderNo()
    {
        $uniqid = uniqid(date("Ym"), true);
        return str_replace(".", "", $uniqid);
    }

    /**
     * post 请求
     * @param $url
     * @param $data
     * @param int $timeout
     * @return string
     * @throws Exception
     */
    protected function curl($url, $data, $timeout = 30)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_FAILONERROR, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if (substr($url, 0, 5) === "https") {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        }
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $headers = array('content-type: application/json', 'Request-Trace-Id:' . $this->getRandStr(9));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        $reponse = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new Exception(curl_error($ch), 0);
        } else {
            $httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if (200 !== $httpStatusCode) {
                throw new Exception($reponse, $httpStatusCode);
            }
        }

        curl_close($ch);
        return $reponse;
    }
}