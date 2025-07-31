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
 * Um equipamento de rastreamento do sistema
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Equipment
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
  protected $table = 'equipments';
  
  /**
   * O nome da chave primária.
   *
   * @var string
   */
  protected $primaryKey = 'equipmentid';
  
  /**
   * Os campos da tabela.
   *
   * @var array
   */
  protected $fillable = [
    'contractorid',
    'assignedtoid',
    'equipmentmodelid',
    'imei',
    'serialnumber',
    'ownershiptypeid',
    'supplierid',
    'subsidiaryid',
    'assetnumber',
    'equipmentstateid',
    'storagelocation',
    'technicianid',
    'serviceproviderid',
    'depositid',
    'vehicleid',
    'installationid',
    'installedat',
    'main',
    'hiddenfromcustomer',
    'installationsite',
    'hasblocking',
    'blockingsite',
    'hasibutton',
    'ibuttonsite',
    'ibuttonsmemsize',
    'hassiren',
    'sirensite',
    'panicbuttonsite',
    'customerpayerid',
    'subsidiarypayerid',
    'blocked',
    //'lastcommunication',
    'createdbyuserid',
    'updatedbyuserid'
  ];
  
  /**
   * Os campos do tipo data.
   *
   * @var array
   */
  protected $dates = [
    'createdat',
    'updatedat',
    'installedat',
    //'lastcommunication'
  ];

  /**
   * A informação de que temos campos de registro de modificações.
   *
   * @var boolean
   */
  public $timestamps = true;
  
  /**
   * Os campos de criação e modificação.
   *
   * @const string
   */
  const CREATED_AT = 'createdat';
  const UPDATED_AT = 'updatedat';

  /**
   * Os campos que precisam de conversão em tipos de dados comuns.
   *
   * @var array
   */
  protected $casts = [
   'blocked' => 'boolean',
   'main' => 'boolean',
   'hiddenfromcustomer' => 'boolean',
   'installedat' => 'date:d/m/Y',
   'hasblocking' => 'boolean',
   'hasibutton' => 'boolean',
   'hassiren' => 'boolean',
   //'lastcommunication' => 'date:d/m/Y H:i:s'
  ];
  
  /**
   * A classe do model de SIM Cards por equipamento.
   *
   * @var string
   */
  protected static $simCardPerEquipmentClass = 'App\Models\SimCardPerEquipment';
    
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
   * Converte o valor de data para o formato correto para armazenamento
   * no banco de dados.
   * 
   * @param string $value
   *   O valor da data
   * 
   * @return Carbon
   *   O valor para armazenamento no banco de dados
   */
  protected function toDate($value) {
    return Carbon::createFromFormat('d/m/Y', $value);
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
    if (array_key_exists('blocked', $attributes))
      $attributes['blocked'] = $this->toBoolean($attributes['blocked']);
    if (array_key_exists('main', $attributes))
      $attributes['main'] = $this->toBoolean($attributes['main']);
    if (array_key_exists('hiddenfromcustomer', $attributes))
      $attributes['hiddenfromcustomer'] = $this->toBoolean($attributes['hiddenfromcustomer']);

    // Converte nos atributos os valores de data
    if (array_key_exists('installedat', $attributes)) {
      if (trim($attributes['installedat']) !== '') {
        $attributes['installedat'] = $this->toDate($attributes['installedat']);
      }
    }
    
    // Prossegue normalmente
    parent::fill($attributes);
  }
  
  /**
   * Retorna o relacionamento com a tabela de SIM Cards por equipamento.
   *
   * @return Collection
   *   As informações de SIM Cards por equipamento
   */
  public function simCardsPerEquipment()
  {
    return $this->hasMany(static::$simCardPerEquipmentClass, 'equipmentid');
  }
  
  /**
   * Deleta em cascata todos os registros de um equipamento de
   * rastreamento.
   *
   * @return bool
   */
  public function deleteCascade()
  {
    // Apaga todas as informações de SIM Cards vinculadas à este
    // equipamento
    $this->simCardsPerEquipment()->delete();
    
    // Apaga o equipamento
    return parent::delete();
  }
}
