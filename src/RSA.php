<?php

namespace Goletter\Utils;

use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Crypt\RSA as phpseclibRSA;

class RSA
{
    const ECB_OAEP = 'ECB_OAEP';

    /**
     * Sign data with your RSA private key
     * @param string $data
     * @param string $privateKeyPath PEM format string or file path
     * @return string Base64 encoded signature
     * @throws \Exception
     */
    public static function signParamsWithRSA(string $data, string $privateKeyPath): string
    {
        $privateKey = self::loadPrivateKey($privateKeyPath);
        
        // Go version: 
        // hashed := sha256.Sum256([]byte(data))
        // signature, err := rsa.SignPKCS1v15(rand.Reader, privateKey, crypto.SHA256, hashed[:])
        // 
        // rsa.SignPKCS1v15 adds ASN.1 structure with hash algorithm identifier
        // PHP's openssl_sign does the same: hashes the data and adds ASN.1 structure
        // So we pass the original data and let openssl_sign handle hashing
        $signature = '';
        if (!\openssl_sign($data, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new \Exception('Failed to sign data: ' . \openssl_error_string());
        }
        
        return \base64_encode($signature);
    }

    /**
     * Decrypt with RSA OAEP
     * @param string $base64Data
     * @param string $privateKeyPath PEM format string or file path
     * @return string
     * @throws \Exception
     */
    public static function decryptWithOAEP(string $base64Data, string $privateKeyPath): string
    {
        $data = \base64_decode($base64Data);
        
        // Go version uses: rsa.DecryptOAEP(sha256.New(), rand.Reader, privateKey, data, nil)
        // This means OAEP with SHA256 hash function
        // Use phpseclib to support SHA-256 OAEP
        $keyContent = self::getKeyContent($privateKeyPath);
        $privateKey = PublicKeyLoader::load($keyContent);
        
        // Set OAEP padding with SHA-256
        $privateKey = $privateKey->withHash('sha256')
                                  ->withMGFHash('sha256')
                                  ->withPadding(phpseclibRSA::ENCRYPTION_OAEP);
        
        $decrypted = $privateKey->decrypt($data);
        
        if ($decrypted === false) {
            throw new \Exception('Failed to decrypt with RSA OAEP');
        }
        
        return $decrypted;
    }

    /**
     * Decrypt with RSA PKCS1v15
     * @param string $base64Data
     * @param string $privateKeyPath PEM format string or file path
     * @return string
     * @throws \Exception
     */
    public static function decryptWithRSA(string $base64Data, string $privateKeyPath): string
    {
        $privateKey = self::loadPrivateKey($privateKeyPath);
        $data = \base64_decode($base64Data);
        
        $decrypted = '';
        if (!\openssl_private_decrypt($data, $decrypted, $privateKey, OPENSSL_PKCS1_PADDING)) {
            throw new \Exception('Failed to decrypt with RSA');
        }
        
        return $decrypted;
    }

    /**
     * Encrypt with RSA OAEP
     * @param string $data
     * @param string $publicKeyPath PEM format string or file path
     * @return string Base64 encoded ciphertext
     * @throws \Exception
     */
    public static function encryptWithOAEP(string $data, string $publicKeyPath): string
    {
        // Go version uses: rsa.EncryptOAEP(sha256.New(), rand.Reader, pubKey, data, nil)
        // This means OAEP with SHA256 hash function
        // Use phpseclib to support SHA-256 OAEP
        $keyContent = self::getKeyContent($publicKeyPath);
        $publicKey = PublicKeyLoader::load($keyContent);
        
        // Set OAEP padding with SHA-256
        $publicKey = $publicKey->withHash('sha256')
                               ->withMGFHash('sha256')
                               ->withPadding(phpseclibRSA::ENCRYPTION_OAEP);
        
        $encrypted = $publicKey->encrypt($data);
        
        if ($encrypted === false) {
            throw new \Exception('Failed to encrypt with RSA OAEP');
        }
        
        return \base64_encode($encrypted);
    }

    /**
     * Verify sign with RSA
     * @param string $data
     * @param string $base64Sign
     * @param string $publicKeyPath PEM format string or file path
     * @return bool
     */
    public static function verifySignWithRSA(string $data, string $base64Sign, string $publicKeyPath): bool
    {
        try {
            $sign = \base64_decode($base64Sign);
            $publicKey = self::loadPublicKey($publicKeyPath);
            
            // Go version:
            // hashed := sha256.Sum256([]byte(data))
            // err = rsa.VerifyPKCS1v15(publicKey, crypto.SHA256, hashed[:], sign)
            //
            // rsa.VerifyPKCS1v15 expects ASN.1 structure with hash algorithm identifier
            // PHP's openssl_verify does the same: hashes the data and verifies with ASN.1 structure
            // So we pass the original data and let openssl_verify handle hashing
            $result = \openssl_verify($data, $sign, $publicKey, OPENSSL_ALGO_SHA256);
            return $result === 1;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get key content from PEM string or file path
     * @param string $keyPath
     * @return string
     * @throws \Exception
     */
    private static function getKeyContent(string $keyPath): string
    {
        // Check if it's a file path or PEM string
        if (\strpos($keyPath, '-----BEGIN') !== false) {
            return $keyPath;
        } else {
            if (!\file_exists($keyPath)) {
                throw new \Exception("Key file not found: {$keyPath}");
            }
            return \file_get_contents($keyPath);
        }
    }

    /**
     * Load private key from PEM string or file path
     * @param string $privateKeyPath
     * @return resource
     * @throws \Exception
     */
    private static function loadPrivateKey(string $privateKeyPath)
    {
        $keyContent = self::getKeyContent($privateKeyPath);
        
        $privateKey = \openssl_pkey_get_private($keyContent);
        if ($privateKey === false) {
            throw new \Exception('Failed to load private key: ' . \openssl_error_string());
        }
        
        return $privateKey;
    }

    /**
     * Load public key from PEM string or file path
     * @param string $publicKeyPath
     * @return resource
     * @throws \Exception
     */
    private static function loadPublicKey(string $publicKeyPath)
    {
        $keyContent = self::getKeyContent($publicKeyPath);
        
        $publicKey = \openssl_pkey_get_public($keyContent);
        if ($publicKey === false) {
            throw new \Exception('Failed to load public key: ' . \openssl_error_string());
        }
        
        return $publicKey;
    }
}

