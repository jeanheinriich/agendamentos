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
 * O histórico de posicionamento do sistema de rastreamento STC.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Models\STC;

use Illuminate\Database\Eloquent\Model;

class Position
  extends Model
{
  /**
   * O nome da conexão a ser utilizada.
   *
   * @var string
   */
  protected $connection = 'erp';
  
  /**
   * O nome da tabela
   *
   * @var string
   */
  protected $table = 'stc.positions';
  
  /**
   * O nome da chave primária.
   *
   * @var string
   */
  protected $primaryKey = 'positionid';
  
  /**
   * Os campos da tabela.
   *
   * @var array
   */
  protected $fillable = [
    'contractorid',
    'positionid',
    'deviceid',
    'plate',
    'eventdate',
    'ignitionstatus',
    'odometer',
    'horimeter',
    'address',
    'direction',
    'speed',
    'batteryvoltage',
    'latitude',
    'longitude',
    'driverid',
    'drivername',
    'rs232'
  ];
  
  /**
   * A informação de que não temos campos de registro de modificações.
   *
   * @var boolean
   */
  public $timestamps = false;

  /**
   * Os campos que precisam de conversão em tipos de dados comuns.
   *
   * @var array
   */
  protected $casts = [
   'direction' => 'boolean'
  ];
  
  /**
   * Força a formatação de Data/Hora para o Eloquent.
   * 
   * @return string
   *   O formato de data/hora
   */
  public function getDateFormat()
  {
    return 'Y-m-d H:i:s.u';
  }
  
  /**
   * Converte um valor para booleano.
   *
   * @param mixed $value
   *   O valor a ser convertido
   *
   * @return bool
   */
  protected function toBoolean($value)
  {
    return filter_var($value, FILTER_VALIDATE_BOOLEAN);
  }

  /**
   * A função de preenchimento dos campos na tabela.
   *
   * @param array $attributes
   *   Os campos com seus respectivos valores
   */
  public function fill(array $attributes)
  {
    // Converte nos atributos os valores booleanos
    if (array_key_exists('direction', $attributes))
      $attributes['direction'] = $this->toBoolean($attributes['direction']);
    
    // Prossegue normalmente
    parent::fill($attributes);
  }

  /**
   * A função responsável pela inserção dos dados, responsável por
   * montar a query com base nos valores dos campos. Não utilizamos o
   * comando de inserção do model, já que o ID não é retornado pelo fato
   * de estarmos utilizando o particionamento dos dados nesta tabela, e
   * isto ocasiona um erro de execução. Desta forma, montamos um comando
   * de inserção manualmente onde colocamos os dados a serem
   * adicionados.
   */
  public function insert() {
    $sql = sprintf(""
      . "INSERT INTO stc.positions "
      . "(deviceid, plate, eventdate, positionid, "
      . "ignitionstatus, odometer, horimeter, address, "
      . "direction, speed, batteryvoltage, latitude, "
      . "longitude, driverid, drivername, rs232, "
      . "contractorid) VALUES (%d, '%s', '%s', %d, %s, %d, "
      . "%d, '%s', %s, %d, %s, %s, %s, %d, '%s', '%s', %d);",
      $this->deviceid,
      $this->plate,
      $this->eventdate,
      $this->positionid,
      $this->ignitionstatus,
      $this->odometer,
      $this->horimeter,
      $this->address,
      $this->direction,
      $this->speed,
      $this->batteryvoltage,
      $this->latitude,
      $this->longitude,
      $this->driverid,
      $this->drivername,
      $this->rs232,
      $this->contractorid
    );
    
    $connection = $this->getConnection();
    $connection->statement($sql);
  }
}
