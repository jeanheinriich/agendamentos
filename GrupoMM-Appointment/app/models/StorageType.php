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
 * Os tipos de armazenamentos possíveis para um dispositivo.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Models;

class StorageType
{
  /**
   * Os dados de tipos de armazenamento.
   *
   * @var array
   */
  protected static $storageTypes = [
    [ 'id' => 'StoredOnDeposit',           'name' => 'Armazenado em depósito' ],
    [ 'id' => 'Installed',                 'name' => 'Instalado' ],
    [ 'id' => 'StoredWithTechnician',      'name' => 'De posse do técnico' ],
    [ 'id' => 'StoredWithServiceProvider', 'name' => 'De posse do prestador de serviços' ],
    [ 'id' => 'UnderMaintenance',          'name' => 'Em manutenção' ],
    [ 'id' => 'ReturnedToSupplier',        'name' => 'Devolvido para o fornecedor' ]
  ];

  /**
   * Recupera a informação de tipos de armazenamento.
   *
   * @return array
   */
  public static function get()
  {
    return self::$storageTypes;
  }

  /**
   * Recupera a informação dos nomes dos tipos de armazenamento.
   *
   * @return array
   */
  public static function getAsArray()
  {
    $storageTypes = [];

    foreach (self::$storageTypes as $storageType) {
      $storageTypes[] = $storageType['name'];
    }

    return $storageTypes;
  }

  /**
   * Recupera a informação dos tipos de armazenamento na forma de uma
   * lista em que o nome está associado ao número do armazenamento.
   *
   * @return array
   */
  public static function getAsList()
  {
    $storageTypesList = [];

    foreach (self::$storageTypes as $storageType) {
      $storageTypesList[ $storageType['id'] ] = $storageType['name'];
    }

    return $storageTypesList;
  }
}
