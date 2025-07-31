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
 * Essa é uma classe para gerar uma sequência aleatória, usando um
 * gerador de números pseudoaleatórios criptograficamente seguros
 * (random_int).
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Hashing;

use RangeException;

class Randomized
{
  /**
   * Essa é uma função que gera uma sequência aleatória, usando um
   * gerador de números pseudoaleatórios criptograficamente seguros
   * (random_int). Requer o PHP 7, pois 'random_int' é uma função
   * que foi implementada apenas à partir desta versão.
   * 
   * Caso seja necessário utilizar o PHP 5.x, será necessário instalar a
   * dependência em https://github.com/paragonie/random_compat
   * 
   * @param int $length       Quantos caracteres queremos?
   * @param string $keyspace  Uma sequência de todos os caracteres
   *                          possíveis para formar nossa sequência
   *                          aleatória
   * 
   * @return string
   *
   * @throws RangeException
   */
  public function generate(int $length = 64,
    string $keyspace = ''): string
  {
    if ($length < 1) {
      throw new RangeException("O comprimento da sequência aleatória a "
        . "ser gerada deve ser um número inteiro positivo")
      ;
    }
    if (empty($keyspace)) {
      // Se não for fornecido um conjunto de caracteres, utilizamos
      // números e letras maiúsculas e minúsculas
      $keyspace = '0123456789'
        . 'abcdefghijklmnopqrstuvwxyz'
        . 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'
      ;
    }

    $pieces = [];
    $max = mb_strlen($keyspace, '8bit') - 1;
    for ($i = 0; $i < $length; ++$i) {
      $pieces []= $keyspace[random_int(0, $max)];
    }

    return implode('', $pieces);
  }
}
