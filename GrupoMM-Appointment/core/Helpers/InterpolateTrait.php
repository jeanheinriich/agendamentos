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
 * Essa é uma trait (característica) simples de abstração para
 * substituição de variáveis em uma string.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Helpers;

trait InterpolateTrait
{
  /**
   * Interpola valores de contexto nos espaços reservados da mensagem.
   * 
   * @param string $message
   *   A mensagem a ser registrada
   * @param array $context
   *   Os valores de contexto
   * 
   * @return string
   *   A mensagem com os valores de contexto substituídos.
   */
  protected function interpolate(string $message,
    array $context = []): string
  {
    // Constrói uma matriz de substituição com chaves ao redor das
    // chaves de contexto
    $replace = [];
    
    foreach ($context as $key => $val) {
      // Verifica se o valor pode ser convertido em string
      if (!is_array($val) && (!is_object($val) ||
        method_exists($val, '__toString'))) {
        $replace['{' . $key . '}'] = $val;
      }
    }

    // Interpola os valores de substituição na mensagem e retorna
    return strtr($message, $replace);
  }
}
