<?php

namespace Goodcatch\FXK;

use Exception;

class MsgCrypt
{
    private $token;
    private $encodingAesKey;

    /**
     * 构造函数
     * @param $token string 开放平台上，开发者设置的token
     * @param $encodingAesKey string 开放平台上，开发者设置的EncodingAESKey
     */
    public function __construct($token, $encodingAesKey)
    {
        $this->token = $token;
        $this->encodingAesKey = $encodingAesKey;
    }

    /**
     * 签名验证.
     * @param $msgSignature string 签名串
     * @param $timestamp string 时间戳
     * @param $nonce string 随机串
     * @param $content string 消息内容
     *
     * @return boolean
     */
    function verifySignature($msgSignature, $timestamp, $nonce, $content){
        //验证安全签名
        $sha1 = new SHA1;
        $signature = $sha1->getSHA1($this->token, $timestamp, $nonce, $content);
        if ($signature == -1) {
            return false;
        }


        //签名检验失败
        if ($signature != $msgSignature) {
            return false;
        }

        return true;
    }

    /*
    * 验证带身份态跳转的URL
    * @param codeSignature: 签名串，对应GET请求中URL参数的codeSig
    * @param timestamp: 时间戳，对应GET请求中URL参数的timestamp
    * @param nonce: 随机串，对应GET请求中URL参数的nonce
    * @param code: 临时身份态，对应GET请求中URL参数的code
    * @return：成功0，失败返回-1
    */
    public function validateURL($codeSignature, $timestamp = null, $nonce, $code)
    {
        if(!$this->verifySignature($codeSignature, $timestamp, $nonce, $code)){
            return -1;
        }

        return 0;
    }

    /**
     * 检验消息的真实性，并且获取解密后的明文.
     * <ol>
     *    <li>利用收到的密文生成安全签名，进行签名验证</li>
     *    <li>若验证通过，对消息进行解密</li>
     * </ol>
     *
     * @param $msgSignature string 签名串，对应POST请求的数据中的sig
     * @param $timestamp string 时间戳 对应POST请求的数据中的timeStamp
     * @param $nonce string 随机串，对应POST请求的数据中的nonce
     * @param $content string 密文，对应POST请求的数据中的content
     *
     * @return string， 失败-1，成功则返回解密后的明文
     */
    public function decryptMsg($msgSignature, $timestamp = null, $nonce, $content)
    {
        if (strlen($this->encodingAesKey) != 43) {
            return -1;
        }

        //当前时间上下50s 都认为是时间误差接受该请求
        #if ($timestamp == null || abs($timestamp - time())>500000) {
        #	return -1;
        #}

        //验证安全签名
        if(!$this->verifySignature($msgSignature, $timestamp, $nonce, $content)){
            return -1;
        }

        return $this->decrypt($content);
    }

    /**
     * 对密文进行解密
     * @param string $content 需要解密的密文
     * @return string 解密得到的明文
     */
    public function decrypt($encryptedContent)
    {
        $ciphertextBytes = base64_decode($encryptedContent);
        $aesKeyBytes=base64_decode($this->encodingAesKey);



        $iv = substr($aesKeyBytes, 0, 16);
        $decrypted = openssl_decrypt($ciphertextBytes, 'AES-256-CBC', $aesKeyBytes, OPENSSL_RAW_DATA|OPENSSL_ZERO_PADDING, $iv);
        
        try {
            //去除补位字符
            $pkc_encoder = new PKCS7Encoder;
            $result = $pkc_encoder->decode($decrypted);
            return $result;
//            //去除16位随机字符串,网络字节序
            if (strlen($result) < 16){
                return "";
            }
            $content = substr($result, 16, strlen($result));

            $len_list = unpack("N", substr($content, 0, 4));
            $data_len = $len_list[1];
            return substr($content, 4, $data_len);

        } catch (Exception $e) {
            return -1;
        }

    }

}


/**
 * SHA1 class
 *
 * 计算消息签名接口.
 */
class SHA1
{
    /**
     * 用SHA1算法生成安全签名
     * @param string $token 票据
     * @param string $timestamp 时间戳
     * @param string $nonce 随机字符串
     * @param string $encrypt 密文消息
     */
    public function getSHA1($token, $timestamp, $nonce, $encrypt)
    {
        //排序
        try {
            $array = array($encrypt, $token, $timestamp, $nonce);
            sort($array, SORT_STRING);
            $str = implode($array);
            return sha1($str);
        } catch (Exception $e) {
            //签名异常;
            return -1;
        }
    }
}