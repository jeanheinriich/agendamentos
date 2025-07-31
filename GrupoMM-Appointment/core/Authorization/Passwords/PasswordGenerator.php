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
 * Classe responsável pela geração de uma senha aleatória segura.
 * 
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Authorization\Passwords;

class PasswordGenerator
{
  /**
   * Cria uma nova senha com base nos parâmetros de segurança
   * estipulados:
   *   - Ao menos 4 caracteres minúsculos
   *   - Ao menos 2 caracteres maiúsculos
   *   - Ao menos 2 números
   *   - Ao menos 2 símbolos
   * 
   * @return string
   *   A senha gerada
   */
  public static function generate(): string
  {
    // Definimos os caracteres de cada tipo
    $charsInLowerCase = 'abcdefghijklmnopqrstuvwxyz';
    $charsInUpperCase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $numbers = '1234567890';
    $symbols = '!@#$%&*?';
    $setOfCharacters = [
      $charsInLowerCase,
      $charsInUpperCase,
      $numbers,
      $symbols
    ];
    
    // Definimos as quantidades de cada tipo
    $amounts = [ 4, 2, 2, 2 ];
    
    // O resultado a ser entregue
    $result = '';
    
    // Monta uma string contendo caracteres aleatórios de cada tipo
    $i = 0;
    foreach($setOfCharacters as $set) {
      // Pegamos uma porcentagem de cada caractere
      for ($n = 0; $n < $amounts[$i]; $n++) {
        // Criamos um número aleatório de 1 até o tamanho deste conjunto
        // para pegar um dos caracteres
        $rand = mt_rand(0, strlen($set)-1);
        
        // Concatenamos um dos caracteres na variável de retorno
        $result .= $set[$rand];
      }
      
      $i++;
    }
    
    // Por último, embaralha os caracteres e retorna
    return str_shuffle($result);
  }
}
