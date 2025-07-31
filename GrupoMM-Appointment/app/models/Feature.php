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
 * Uma característica técnica que um equipamento de rastreamento deve
 * ter para que o mesmo atenda os requisitos de um contrato do sistema.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Feature
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
  protected $table = 'features';
  
  /**
   * O nome da chave primária.
   *
   * @var string
   */
  protected $primaryKey = 'featureid';
  
  /**
   * Os campos da tabela.
   *
   * @var array
   */
  protected $fillable = [
    'name',
    'needanaloginput',
    'needanalogoutput',
    'needdigitalinput',
    'needdigitaloutput',
    'needrfmodule',
    'needonoffbutton',
    'needboxopensensor',
    'needrs232interface',
    'needibuttoninput',
    'needantijammer',
    'needrpminput',
    'needodometerinput',
    'needaccelerometer',
    'needaccessory',
    'accessorytypeid'
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
    'needanaloginput' => 'boolean',
    'needanalogoutput' => 'boolean',
    'needdigitalinput' => 'boolean',
    'needdigitaloutput' => 'boolean',
    'needrfmodule' => 'boolean',
    'needonoffbutton' => 'boolean',
    'needboxopensensor' => 'boolean',
    'needrs232interface' => 'boolean',
    'needibuttoninput' => 'boolean',
    'needantijammer' => 'boolean',
    'needrpminput' => 'boolean',
    'needodometerinput' => 'boolean',
    'needaccelerometer' => 'boolean',
    'needaccessory' => 'boolean'
  ];
  
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
    if (array_key_exists('needanaloginput', $attributes))
      $attributes['needanaloginput'] = $this->toBoolean($attributes['needanaloginput']);
    if (array_key_exists('needanalogoutput', $attributes))
      $attributes['needanalogoutput'] = $this->toBoolean($attributes['needanalogoutput']);
    if (array_key_exists('needdigitalinput', $attributes))
      $attributes['needdigitalinput'] = $this->toBoolean($attributes['needdigitalinput']);
    if (array_key_exists('needdigitaloutput', $attributes))
      $attributes['needdigitaloutput'] = $this->toBoolean($attributes['needdigitaloutput']);
    if (array_key_exists('needrfmodule', $attributes))
      $attributes['needrfmodule'] = $this->toBoolean($attributes['needrfmodule']);
    if (array_key_exists('needonoffbutton', $attributes))
      $attributes['needonoffbutton'] = $this->toBoolean($attributes['needonoffbutton']);
    if (array_key_exists('needboxopensensor', $attributes))
      $attributes['needboxopensensor'] = $this->toBoolean($attributes['needboxopensensor']);
    if (array_key_exists('needrs232interface', $attributes))
      $attributes['needrs232interface'] = $this->toBoolean($attributes['needrs232interface']);
    if (array_key_exists('needibuttoninput', $attributes))
      $attributes['needibuttoninput'] = $this->toBoolean($attributes['needibuttoninput']);
    if (array_key_exists('needantijammer', $attributes))
      $attributes['needantijammer'] = $this->toBoolean($attributes['needantijammer']);
    if (array_key_exists('needrpminput', $attributes))
      $attributes['needrpminput'] = $this->toBoolean($attributes['needrpminput']);
    if (array_key_exists('needodometerinput', $attributes))
      $attributes['needodometerinput'] = $this->toBoolean($attributes['needodometerinput']);
    if (array_key_exists('needaccelerometer', $attributes))
      $attributes['needaccelerometer'] = $this->toBoolean($attributes['needaccelerometer']);
    if (array_key_exists('needaccessory', $attributes))
      $attributes['needaccessory'] = $this->toBoolean($attributes['needaccessory']);
    
    // Prossegue normalmente
    parent::fill($attributes);
  }
}
