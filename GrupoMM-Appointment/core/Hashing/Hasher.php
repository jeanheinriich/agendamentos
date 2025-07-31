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
 * Essa é uma trait (característica) simples de inclusão de métodos para
 * manipulação de números de verificação (Hash) que outras classes podem
 * incluir.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Hashing;

trait Hasher
{
  // O comprimento da chave de criptografia (salt)
  protected $saltLength = 26;
  
  // Cria uma string aleatória para ser usada como chave de criptografia
  protected function createSalt()
  {
    // Utilizaremos como conjunto de caracteres os números, letras
    // maiúsculas e minúsculas e alguns símbolos
    $pool = '0123456789'
      . 'abcdefghijklmnopqrstuvwxyz'
      . 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'
      . './'
    ;

    // Determinamos o tamanho de nossa string como sendo o tamanho do
    // conjunto de caracteres que estamos utilizando como base
    $max = strlen($pool) - 1;

    $randomizedValue = new Randomized();
    
    return $randomizedValue->generate($this->saltLength, $pool);
  }

  // Recupera a chave de criptografia (salt) a partir dos dados
  protected function getSalt(string $data)
  {
    return substr($data, 0, $this->saltLength);
  }
  
  // Compara duas strings $a e $b em tempo de comprimento constante
  protected function slowEquals($a, $b)
  {
    $diff = strlen($a) ^ strlen($b);
    
    for ($i = 0; $i < strlen($a) && $i < strlen($b); $i++)
    {
      $diff |= ord($a[$i]) ^ ord($b[$i]);
    }
    
    return $diff === 0;
  }
}
