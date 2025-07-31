<?php
/*
 * This file is part of Extension Library.
 *
 * (c) Emerson Cavalcanti
 *
 * LICENSE: The MIT License (MIT)
 *
 * Permission is hereby granted, free of charge, to any person obtaining
 * a copy of this software and associated documentation files (the 
 * "Software"), to deal in the Software without restriction, including
 * without limitation the rights to use, copy, modify, merge, publish,
 * distribute, sublicense, and/or sell copies of the Software, and to
 * permit persons to whom the Software is furnished to do so, subject
 * to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.
 * IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY
 * CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT,
 * TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE
 * SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 * 
 * ---------------------------------------------------------------------
 * Descrição:
 * 
 * Um sistema de codificação e decodificação de dados para permitir o
 * envio de informações através de uma URL.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Codec;

use ValueError;

class URLCodec
  implements CodecInterface
{
  /**
   * A chave de criptografia.
   * 
   * @var string
   */
  private $encryptionKey;

  /**
   * O algoritmo de criptografia.
   * 
   * @var string
   */
  private $algorithm;

  /**
   * Construtor.
   * 
   * @param string $encryptionKey
   *   A chave de criptografia
   * @param string $algorithm
   *   O algoritmo de criptografia
   */
  public function __construct(
    string $encryptionKey,
    string $algorithm = 'AES-128-CBC'
  )
  {
    $this->encryptionKey = $encryptionKey;
    $this->algorithm     = $algorithm;
  }

  /**
   * Codifica os dados fornecidos.
   * 
   * @param array $data
   *   Os dados a serem codificados
   * 
   * @return string
   *   Os dados codificados
   */
  public function encode(array $data): string
  {
    // Determina os parâmetros de criptografia
    $secretKey = openssl_digest(
      $this->encryptionKey,
      'SHA256',
      true
    );
    $plainText = serialize($data);
    $cipher = $this->algorithm;
    $ivlen = openssl_cipher_iv_length($cipher);
    $iv = openssl_random_pseudo_bytes($ivlen);

    // Criptografa os dados
    $ciphertextRaw = openssl_encrypt(
      $plainText,       // Os dados a serem criptografados
      $cipher,          // O algoritmo de criptografia
      $secretKey,       // A chave secreta
      OPENSSL_RAW_DATA, // Opções de criptografia
      $iv               // Vetor de inicialização
    );

    // Aumentamos a segurança com o cifrado hash
    $hmac = hash_hmac(
      'sha256',
      $ciphertextRaw,
      $secretKey,
      true
    );
    
    $ciphertext = base64_encode( $iv.$hmac.$ciphertextRaw );

    return $ciphertext;
  }
  
  /**
   * Decodifica os dados fornecidos.
   * 
   * @param string $ciphertext
   *   Os dados a serem decodificados
   * 
   * @throws ValueError
   *   Se os dados forem inválidos
   * 
   * @return array
   */
  public function decode(string $ciphertext): array
  {
    // Decodifica as informações
    $cipherData = base64_decode($ciphertext);

    // Determina os parâmetros de criptografia
    $secretKey = openssl_digest(
      $this->encryptionKey,
      'SHA256',
      true
    );
    $cipher = $this->algorithm;
    $ivlen = openssl_cipher_iv_length($cipher);
    $iv = substr($cipherData, 0, $ivlen);
    $hmac = substr($cipherData, $ivlen, $sha2len=32);
    $ciphertextRaw = substr($cipherData, $ivlen + $sha2len);

    // Decifra os dados
    $plainText = openssl_decrypt(
      $ciphertextRaw,
      $cipher,
      $secretKey,
      OPENSSL_RAW_DATA,
      $iv
    );
    $calcmac = hash_hmac(
      'sha256',
      $ciphertextRaw,
      $secretKey,
      true
    );

    if (hash_equals($hmac, $calcmac)) {
      $data = unserialize($plainText);

      return $data;
    } else {
      throw new ValueError('Dados inválidos');
    }
  }
}