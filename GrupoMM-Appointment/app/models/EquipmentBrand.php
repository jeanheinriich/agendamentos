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
 * Uma marca de equipamento do sistema.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EquipmentBrand
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
  protected $table = 'equipmentbrands';
  
  /**
   * O nome da chave primária.
   *
   * @var string
   */
  protected $primaryKey = 'equipmentbrandid';
  
  /**
   * A classe do model de modelos de equipamentos fabricados por esta
   * marca.
   *
   * @var string
   */
  protected static $equipmentModelClass = 'App\Models\EquipmentModel';
  
  /**
   * Os campos da tabela.
   *
   * @var array
   */
  protected $fillable = [
    'contractorid',
    'name',
    'madetracker',
    'madeaccessory'
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
   'madetracker' => 'boolean',
   'madeaccessory' => 'boolean'
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
    if (array_key_exists('madetracker', $attributes))
      $attributes['madetracker'] = $this->toBoolean($attributes['madetracker']);
    if (array_key_exists('madeaccessory', $attributes))
      $attributes['madeaccessory'] = $this->toBoolean($attributes['madeaccessory']);
    
    // Prossegue normalmente
    parent::fill($attributes);
  }
  
  /**
   * Retorna o relacionamento com a tabela de modelos de equipamentos
   * fabricados por marca.
   *
   * @return Collection
   *   As informações de modelos de equipamentos fabricados por marca
   */
  public function equipmentModelsPerBrand()
  {
    return $this->hasMany(static::$equipmentModelClass, 'equipmentbrandid');
  }

  /**
   * Deleta em cascata todos os registros de um modelo de equipamento.
   *
   * @return bool
   */
  public function deleteCascade()
  {
    // Apaga todos os tipos de equipamentos fabricados por esta marca
    $this->equipmentModelsPerBrand()->delete();
    
    // Apaga a marca de equipamento
    return parent::delete();
  }
}
