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
 * A classe que gera um número de verificação (Hash) usando o algorítmo
 * SHA384 e garante sua consistência.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Hashing;

class Sha384Hasher implements HasherInterface
{
  use Hasher;
  
  // Nossa chave de criptografia (salt)
  protected $salt;

  // Cria nosso sistema de hashing
  public function __construct($salt=null)
  {
    // Armazena as configurações
    $this->salt = $salt;
  }

  // Gera o número de verificação (Hash) para o valor fornecido
  public function hash($value)
  {
    if (isset($this->salt)) {
      // Usamos o 'salt' interno para gerar o Hash
      return hash('sha384', $this->salt . $value, false);
    } else {
      // Gera um 'salt' aleatório e acrescentá-o no resultado do Hash
      $salt = $this->createSalt();
      
      return $salt . hash('sha384', $salt . $value, false);
    }
  }
  
  // Verifica se uma chave hashed fornecida (hash) corresponde à
  // chave gerada através do algorítmo de hash
  public function checkHashFromValue($value, string $hashStr)
  {
    if (isset($this->salt)) {
      // Usamos o 'salt' interno para gerar o Hash
      
      // Gera o hash pelo algorítmo interno
      $hash = hash('sha384', $this->salt . $value, false);
      
      // Compara a hash gerada pelo algorítmo com o número de verificação
      // (Hash) fornecido
      return $this->slowEquals($hash, $hashStr);
    } else {
      // Recupera a chave de criptografia (salt) a partir dos dados
      $salt = $this->getSalt($hashStr);
      
      // Gera o hash pelo algorítmo interno
      $hash = hash('sha384', $salt . $value, false);
      
      // Compara a hash gerada pelo algorítmo com o número de verificação
      // (Hash) fornecido
      return $this->slowEquals($salt . $hash, $hashStr);
    }
  }
}
