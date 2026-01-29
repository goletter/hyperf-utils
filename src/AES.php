<?php

namespace Goletter\Utils;

class AES
{
    const GCM = 'GCM_NOPADDING';

    /**
     * Encrypt content with AES GCM
     * @param string $data
     * @param string $aesKey 32 bytes key
     * @param string $aesIv 16 bytes IV
     * @return string Base64 encoded ciphertext
     * @throws \Exception
     */
    public static function encryptContentWithAESGCM(string $data, string $aesKey, string $aesIv): string
    {
        if (\strlen($aesKey) !== 32) {
            throw new \Exception('AES key must be 32 bytes');
        }
        if (\strlen($aesIv) !== 16) {
            throw new \Exception('AES IV must be 16 bytes');
        }
        
        $tag = '';
        $ciphertext = \openssl_encrypt(
            $data,
            'aes-256-gcm',
            $aesKey,
            OPENSSL_RAW_DATA,
            $aesIv,
            $tag
        );
        
        if ($ciphertext === false) {
            throw new \Exception('Failed to encrypt with AES GCM: ' . \openssl_error_string());
        }
        
        // Append tag to ciphertext (tag is 16 bytes for GCM)
        // This matches Go's aesGCM.Seal behavior
        return \base64_encode($ciphertext . $tag);
    }

    /**
     * Decrypt with AES GCM
     * @param string $aesKey 32 bytes key
     * @param string $aesIv 16 bytes IV
     * @param string $ciphertext Base64 encoded ciphertext with tag
     * @return string
     * @throws \Exception
     */
    public static function newGCMDecrypter(string $aesKey, string $aesIv, string $ciphertext): string
    {
        if (\strlen($aesKey) !== 32) {
            throw new \Exception('AES key must be 32 bytes');
        }
        if (\strlen($aesIv) !== 16) {
            throw new \Exception('AES IV must be 16 bytes');
        }
        
        $data = \base64_decode($ciphertext);
        
        // Extract tag (last 16 bytes) and ciphertext
        $tag = \substr($data, -16);
        $encrypted = \substr($data, 0, -16);
        
        $decrypted = \openssl_decrypt(
            $encrypted,
            'aes-256-gcm',
            $aesKey,
            OPENSSL_RAW_DATA,
            $aesIv,
            $tag
        );
        
        if ($decrypted === false) {
            throw new \Exception('Failed to decrypt with AES GCM');
        }
        
        return $decrypted;
    }

    /**
     * Decrypt with AES CBC
     * @param string $aesKey
     * @param string $aesIv
     * @param string $ciphertext Base64 encoded ciphertext
     * @return string
     * @throws \Exception
     */
    public static function newCBCDecrypter(string $aesKey, string $aesIv, string $ciphertext): string
    {
        $data = \base64_decode($ciphertext);
        
        $decrypted = \openssl_decrypt(
            $data,
            'aes-256-cbc',
            $aesKey,
            OPENSSL_RAW_DATA,
            $aesIv
        );
        
        if ($decrypted === false) {
            throw new \Exception('Failed to decrypt with AES CBC');
        }
        
        return self::unpadding($decrypted);
    }

    /**
     * Remove PKCS7 padding
     * @param string $src
     * @return string
     */
    private static function unpadding(string $src): string
    {
        $n = \strlen($src);
        $unPadNum = \ord($src[$n - 1]);
        return \substr($src, 0, $n - $unPadNum);
    }
}

