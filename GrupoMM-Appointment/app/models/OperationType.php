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
 * Um tipo de operação num depósito.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Models;

class OperationType
{
  /**
   * Os dados de tipos de operações.
   *
   * @var array
   */
  protected static $operationTypes = [
    [ 'id' => 'Installation',   'name' => 'Instalação' ],
    [ 'id' => 'Uninstallation', 'name' => 'Retirada' ],
    [ 'id' => 'Devolution',     'name' => 'Devolução' ],
    [ 'id' => 'Maintenance',    'name' => 'Manutenção' ],
    [ 'id' => 'Replacement',    'name' => 'Substituição' ],
    [ 'id' => 'Transference',   'name' => 'Transferência' ]
  ];

  /**
   * Recupera a informação de tipos de operações.
   *
   * @return array
   */
  public static function get()
  {
    return self::$operationTypes;
  }

  /**
   * Recupera a informação dos nomes dos tipos de operação.
   *
   * @return array
   */
  public static function getAsArray()
  {
    $operationTypes = [];

    foreach (self::$operationTypes as $operationType) {
      $operationTypes[] = $operationType['name'];
    }

    return $operationTypes;
  }

  /**
   * Recupera a informação dos tipos de operação na forma de uma lista
   * em que o nome está associado ao número da operação.
   *
   * @return array
   */
  public static function getAsList()
  {
    $operationTypesList = [];

    foreach (self::$operationTypes as $operationType) {
      $operationTypesList[ $operationType['id'] ] = $operationType['name'];
    }

    return $operationTypesList;
  }
}
