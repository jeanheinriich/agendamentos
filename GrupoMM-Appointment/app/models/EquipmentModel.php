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
 * Um modelo de equipamento do sistema
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EquipmentModel
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
  protected $table = 'equipmentmodels';
  
  /**
   * O nome da chave primária.
   *
   * @var string
   */
  protected $primaryKey = 'equipmentmodelid';
  
  /**
   * Os campos da tabela.
   *
   * @var array
   */
  protected $fillable = [
    'contractorid',
    'name',
    'equipmentbrandid',
    'maxsimcards',
    'simcardtypeid',
    'protocolid',
    'protocolvariantid',
    'operatingfrequenceid',
    'serialnumbersize',
    'reducednumbersize',
    'analoginput',
    'analogoutput',
    'digitalinput',
    'digitaloutput',
    'hasrfmodule',
    'hasonoffbutton',
    'hasboxopensensor',
    'hasrs232interface',
    'hasibuttoninput',
    'ibuttonsmemsize',
    'hasantijammer',
    'hasrpminput',
    'hasodometerinput',
    'hasaccelerometer',
    'createdbyuserid',
    'updatedbyuserid'
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
    'hasrfmodule' => 'boolean',
    'hasonoffbutton' => 'boolean',
    'hasboxopensensor' => 'boolean',
    'hasrs232interface' => 'boolean',
    'hasibuttoninput' => 'boolean',
    'hasantijammer' => 'boolean',
    'hasrpminput' => 'boolean',
    'hasodometerinput' => 'boolean',
    'hasaccelerometer' => 'boolean'
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
    if (array_key_exists('hasrfmodule', $attributes))
      $attributes['hasrfmodule'] = $this->toBoolean($attributes['hasrfmodule']);
    if (array_key_exists('hasonoffbutton', $attributes))
      $attributes['hasonoffbutton'] = $this->toBoolean($attributes['hasonoffbutton']);
    if (array_key_exists('hasboxopensensor', $attributes))
      $attributes['hasboxopensensor'] = $this->toBoolean($attributes['hasboxopensensor']);
    if (array_key_exists('hasrs232interface', $attributes))
      $attributes['hasrs232interface'] = $this->toBoolean($attributes['hasrs232interface']);
    if (array_key_exists('hasibuttoninput', $attributes))
      $attributes['hasibuttoninput'] = $this->toBoolean($attributes['hasibuttoninput']);
    if (array_key_exists('hasantijammer', $attributes))
      $attributes['hasantijammer'] = $this->toBoolean($attributes['hasantijammer']);
    if (array_key_exists('hasrpminput', $attributes))
      $attributes['hasrpminput'] = $this->toBoolean($attributes['hasrpminput']);
    if (array_key_exists('hasodometerinput', $attributes))
      $attributes['hasodometerinput'] = $this->toBoolean($attributes['hasodometerinput']);
    if (array_key_exists('hasaccelerometer', $attributes))
      $attributes['hasaccelerometer'] = $this->toBoolean($attributes['hasaccelerometer']);
    
    // Prossegue normalmente
    parent::fill($attributes);
  }
}
