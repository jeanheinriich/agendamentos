<?php
/*
 * This file is part of Slim Framework 3 skeleton application.
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
 * Um manipulador de sessões seguras. Esta classe contém o manipulador
 * de sessão nativo do PHP e criptografa os dados da sessão usando uma
 * chave personalizada.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti (at) gmail (dot) com>
 */

namespace Core\Sessions;

use SessionHandler;
use RuntimeException;

class SecureSessionHandler
  extends SessionHandler
{
  /**
   * A chave de encriptação dos dados.
   * 
   * @var string
   */
  protected $key;

  /**
   * O construtor do manipulador de sessões seguras.
   *
   * @param string $key
   *   A chave de encriptação.
   */
  public function __construct(string $key)
  {
    if (!extension_loaded('openssl')) {
      throw new RuntimeException("O OpenSSL precisa estar disponível "
        . "para permitir criptografar os dados da sessão."
      );
    }

    $this->key = $key;
  }

  /**
   * Lê os dados da sessão
   *
   * @param string $session_id
   *   O identificador da sessão
   * 
   * @return string
   *   Os dados da sessão descriptografados
   */
  public function read($session_id): string
  {
    // Lê os dados da sessão
    $session_data = parent::read($session_id);

    // Se tivermos dados, desencripta primeiramente
    if ($session_data) {
      $session_data = $this->decrypt($session_data);
    }

    return $session_data;
  }

  /**
   * Grava os dados na sessão.
   *
   * @param string $session_id
   *   O identificador da sessão
   * @param string $session_data
   *   Os dados a serem gravados
   *
   * @return bool
   *   O resultado da gravação (true se os dados foram gravados com
   * sucesso)
   */
  public function write($session_id, $session_data): bool
  {
    // Encripta os dados a serem gravados
    $session_data = $this->encrypt($session_data);

    return parent::write($session_id, $session_data);
  }

  /**
   * Encripta os dados a serem gravados usando a chave de criptografia.
   *
   * @param string $session_data
   *   Os dados a serem encriptados
   * 
   * @return string
   *   Os dados encriptados
   */
  protected function encrypt(string $session_data): string
  {
    // Gera um 'sal' aleatório
    $salt = random_bytes(16);

    $salted = hash('sha512', $this->key . $salt, true);
    $key    = substr($salted, 0, 32);
    $iv     = substr($salted, 32, 16);

    $encryptedData = openssl_encrypt($session_data, 'AES-256-CBC', $key,
      OPENSSL_RAW_DATA, $iv)
    ;

    return base64_encode($salt . $encryptedData);
  }

  /**
   * Desencripta os dados de sessão.
   *
   * @param string $session_data
   *   Os dados a serem desencriptados
   * 
   * @return string
   *   Os dados desencriptados
   */
  protected function decrypt(string $session_data): string
  {
    if (empty($session_data)) {
      return false;
    }

    $session_data = base64_decode($session_data);
    $salt = substr($session_data, 0, 16);
    $session_data = substr($session_data, 16);

    $salted = hash('sha512', $this->key . $salt, true);
    $key    = substr($salted, 0, 32);
    $iv     = substr($salted, 32, 16);

    return openssl_decrypt($session_data, 'AES-256-CBC', $key,
      OPENSSL_RAW_DATA, $iv
    );
  }
}
