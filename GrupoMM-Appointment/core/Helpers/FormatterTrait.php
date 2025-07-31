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
 * Essa é uma trait (característica) simples de formatação de parâmetros
 * que outras classes podem incluir.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Helpers;

trait FormatterTrait
{
  /**
   * Um formatador para campos do tipo data obtidos no padrão SQL
   * 
   * @param string $date
   *   A data no formato SQL (yyyy-mm-dd)
   * 
   * @return string
   *   A data no formato dd/mm/yyyy
   */
  protected function formatSQLDate(string $date): string
  {
    return implode('/', array_reverse(explode('-', $date)));
  }

  /**
   * Um formatador de datas no padrão DD/MM/YYYY para os campos do tipo
   * data no padrão SQL (YYYY-MM-DD)
   * 
   * @param string $date
   *   A data no formato dd/mm/yyyy
   * 
   * @return string
   *   A data no formato SQL (yyyy-mm-dd)
   */
  protected function toSQLDate(string $date): string
  {
    return implode('-', array_reverse(explode('/', $date)));
  }

  /**
   * Formata a duração em segundos em um valor de horas, minutos e
   * segundos.
   * 
   * @param int $value
   *   O valor de tempo em segundos
   * @param string $separator
   *   O caractere separador (padrão: ':')
   * 
   * @return string
   */
  protected function formatDuration(int $value,
    string $separator = ':'): string
  {
    $signal = '';
    if ($value < 0) {
      $signal = '-';
      $value = $value * -1;
    }

    return sprintf("%s%02d%s%02d%s%02d",
      $signal, floor($value/3600), $separator ,
      ($value/60)%60, $separator,
      $value%60
    );
  }
}
