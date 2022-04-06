<?php

namespace Goodcatch\FXK;

class PKCS7Encoder
{
    public static $block_size = 32;

    /**
     * 对解密后的明文进行补位删除
     * @param
     * @return
     */
    function decode($text)
    {
        $pad = ord(substr($text, -1));
        if ($pad < 1 || $pad > 32) {
            $pad = 0;
        }
        return substr($text, 0, (strlen($text) - $pad));
    }
}