<?php

namespace App\Petkit\Storage;

use RuntimeException;

/**
 * Decrypts camera captures uploaded by PetKit cloud-storage-capable devices
 * (e.g. the D4sh). The firmware AES-128-CBC/PKCS7-encrypts the whole file as
 * a single stream, using the key it was handed in `dev_oss_sts_info_new_v2`
 * (`primaryAesKeyStr`) and the per-file IV it reports back in the
 * `dev_upload_file_info_v2` fileInfo (`aesIv`).
 */
class MediaDecryptor
{
    /**
     * @param string $key The 16 ASCII bytes of `primaryAesKeyStr`, used verbatim as the raw key - not hex-decoded.
     * @param string $iv The `aesIv` field from fileInfo, e.g. "0x6161...61" - hex-decoded after stripping the "0x" prefix.
     */
    public static function decrypt(string $ciphertext, string $key, string $iv): string
    {
        $ivBinary = hex2bin(preg_replace('/^0x/', '', $iv));

        if ($ivBinary === false || strlen($ivBinary) !== 16) {
            throw new RuntimeException('Invalid AES IV for media decryption');
        }

        $plain = openssl_decrypt($ciphertext, 'aes-128-cbc', $key, OPENSSL_RAW_DATA, $ivBinary);

        if ($plain === false) {
            throw new RuntimeException('AES decrypt failed');
        }

        return $plain;
    }
}
