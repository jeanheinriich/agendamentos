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
 * Um mês do ano
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Models;

class Month
{
  /**
   * Os dados de mêses.
   *
   * @var array
   */
  protected static $months = [
    [ 'id' =>  1, 'name' => 'Janeiro', 'short' => 'Jan' ],
    [ 'id' =>  2, 'name' => 'Fevereiro', 'short' => 'Fev' ],
    [ 'id' =>  3, 'name' => 'Março', 'short' => 'Mar' ],
    [ 'id' =>  4, 'name' => 'Abril', 'short' => 'Abr' ],
    [ 'id' =>  5, 'name' => 'Maio', 'short' => 'Mai' ],
    [ 'id' =>  6, 'name' => 'Junho', 'short' => 'Jun' ],
    [ 'id' =>  7, 'name' => 'Julho', 'short' => 'Jul' ],
    [ 'id' =>  8, 'name' => 'Agosto', 'short' => 'Ago' ],
    [ 'id' =>  9, 'name' => 'Setembro', 'short' => 'Set' ],
    [ 'id' => 10, 'name' => 'Outubro', 'short' => 'Out' ],
    [ 'id' => 11, 'name' => 'Novembro', 'short' => 'Nov' ],
    [ 'id' => 12, 'name' => 'Dezembro', 'short' => 'Dez' ]
  ];

  /**
   * Recupera a informação de meses.
   *
   * @return array
   */
  public static function get()
  {
    return self::$months;
  }

  /**
   * Recupera a informação dos nomes dos meses.
   *
   * @return array
   */
  public static function getAsArray()
  {
    $months = [
      'Todos'
    ];

    foreach (self::$months as $month) {
      $months[] = $month['name'];
    }

    return $months;
  }

  /**
   * Recupera a informação dos nomes dos meses na forma de uma lista em
   * que o nome está associado ao número do mês.
   *
   * @return array
   */
  public static function getAsList()
  {
    $monthsList = [];

    foreach (self::$months as $month) {
      $monthsList[ $month['id'] ] = $month['name'];
    }

    return $monthsList;
  }
}
