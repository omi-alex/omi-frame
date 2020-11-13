<?php

final class QEncrypt
{
	public static function Encrypt_With_Hash(string $data, string $key = Q_DEFAULT_ENCRYPT_KEY, string $cipher = 'aes-256-cbc')
	{
		$ivlen = openssl_cipher_iv_length($cipher);
		$iv = openssl_random_pseudo_bytes($ivlen);
		$ciphertext_raw = openssl_encrypt($data, $cipher, $key, $options = OPENSSL_RAW_DATA, $iv);
		$hmac = hash_hmac('sha256', $ciphertext_raw, $key, $as_binary=true);
		return $iv.$hmac.$ciphertext_raw;
	}
	
	public static function Decrypt_With_Hash(string $data, string $key = Q_DEFAULT_ENCRYPT_KEY, string $cipher = 'aes-256-cbc')
	{
		$ivlen = openssl_cipher_iv_length($cipher);
		$iv = substr($data, 0, $ivlen);
		$hmac = substr($data, $ivlen, $sha2len = 32);
		$ciphertext_raw = substr($data, $ivlen + $sha2len);
		$original_plaintext = openssl_decrypt($ciphertext_raw, $cipher, $key, $options = OPENSSL_RAW_DATA, $iv);
		$calcmac = hash_hmac('sha256', $ciphertext_raw, $key, $as_binary=true);
		return hash_equals($hmac, $calcmac) ? $original_plaintext : null;
	}
	
	public static function Generate_Key(string $cipher = 'aes-256-cbc')
	{
		return openssl_random_pseudo_bytes(openssl_cipher_iv_length($cipher));
	}
}
