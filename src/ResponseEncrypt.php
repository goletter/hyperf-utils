<?php

namespace Goletter\Utils;

use function Hyperf\Support\env;

class ResponseEncrypt
{
    /**
     * 加密响应数据
     * @param string $jsonData JSON 字符串
     * @return array 返回加密后的数据结构
     */
    public static function encrypt(string $jsonData): array
    {
        // 从配置或环境变量获取 AES key（32字节）
        $aesKey = env('RESPONSE_AES_KEY', 'your-32-byte-aes-key-here-123456');

        // 确保 key 是 32 字节
        if (strlen($aesKey) !== 32) {
            $aesKey = substr(hash('sha256', $aesKey), 0, 32);
        }

        // 生成随机 IV（16字节）
        $aesIv = random_bytes(16);

        // 使用 AES-GCM 加密
        $encrypted = AES::encryptContentWithAESGCM($jsonData, $aesKey, $aesIv);

        // 返回加密后的数据（前端需要知道 IV 才能解密）
        return [
            'encrypted' => true,
            'data' => $encrypted,
            'iv' => base64_encode($aesIv), // Base64 编码 IV，前端需要这个来解密
        ];
    }

    /**
     * 解密响应数据（用于测试）
     */
    public static function decrypt(string $encryptedData, string $ivBase64): string
    {
        $aesKey = env('RESPONSE_AES_KEY', 'your-32-byte-aes-key-here-123456');
        if (strlen($aesKey) !== 32) {
            $aesKey = substr(hash('sha256', $aesKey), 0, 32);
        }

        $aesIv = base64_decode($ivBase64);
        return AES::newGCMDecrypter($aesKey, $aesIv, $encryptedData);
    }
}