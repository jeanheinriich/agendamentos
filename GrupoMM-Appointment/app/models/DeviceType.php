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
 * Um tipo de dispositivo.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Models;

class DeviceType
{
  /**
   * Os dados de tipos de dispositivos.
   *
   * @var array
   */
  protected static $deviceTypes = [
    [ 'id' => 'Both',      'name' => 'Qualquer dispositivo' ],
    [ 'id' => 'SimCard',   'name' => 'Apenas SIM Cards' ],
    [ 'id' => 'Equipment', 'name' => 'Apenas equipamentos' ]
  ];

  /**
   * Recupera a informação de tipos de dispositivo.
   *
   * @return array
   */
  public static function get()
  {
    return self::$deviceTypes;
  }

  /**
   * Recupera a informação dos nomes dos tipos de dispositivo.
   *
   * @return array
   */
  public static function getAsArray()
  {
    $deviceTypes = [];

    foreach (self::$deviceTypes as $deviceType) {
      $deviceTypes[] = $deviceType['name'];
    }

    return $deviceTypes;
  }

  /**
   * Recupera a informação dos tipos de dispositivo na forma de uma
   * lista em que o nome está associado ao número do armazenamento.
   *
   * @return array
   */
  public static function getAsList()
  {
    $deviceTypesList = [];

    foreach (self::$deviceTypes as $deviceType) {
      $deviceTypesList[ $deviceType['id'] ] = $deviceType['name'];
    }

    return $deviceTypesList;
  }
}
