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
 * Uma jornada de trabalho a ser cumprida pelos motoristas do sistema.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Models\STC;

use Illuminate\Database\Eloquent\Model;

class Journey
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
  protected $table = 'stc.journeys';
  
  /**
   * O nome da chave primária.
   *
   * @var string
   */
  protected $primaryKey = 'journeyid';
  
  /**
   * Os campos da tabela.
   *
   * @var array
   */
  protected $fillable = [
    'contractorid',
    'clientid',
    'name',
    'startdaytime',
    'startnighttime',
    'computeovertime',
    'discountworkedlesshours',
    'asdefault',
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
   'computeovertime' => 'boolean',
   'discountworkedlesshours' => 'boolean',
   'asdefault' => 'boolean'
  ];
  
  /**
   * A classe do model de valores de jornada diárias.
   *
   * @var string
   */
  protected static $journeyPerDayClass = 'App\Models\STC\JourneyPerDay';

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
    if (array_key_exists('computeovertime', $attributes))
      $attributes['computeovertime'] = $this->toBoolean($attributes['computeovertime']);
    if (array_key_exists('discountworkedlesshours', $attributes))
      $attributes['discountworkedlesshours'] = $this->toBoolean($attributes['discountworkedlesshours']);
    if (array_key_exists('asdefault', $attributes))
      $attributes['asdefault'] = $this->toBoolean($attributes['asdefault']);
    
    // Prossegue normalmente
    parent::fill($attributes);
  }
  
  /**
   * Retorna o relacionamento com a tabela de valores de jornada por
   * dia da semana.
   *
   * @return Collection
   *   As informações de valores de jornada por dia da semana
   */
  public function journeyPerDay()
  {
    return $this->hasMany(static::$journeyPerDayClass, 'journeyid');
  }
  
  /**
   * Deleta em cascata todos os registros de uma jornada de trabalho.
   *
   * @return bool
   */
  public function deleteCascade()
  {
    // Apaga todas as informações de valores de jornada por dia
    // relacionados
    $this->journeyPerDay()->delete();
    
    // Apaga a jornada de trabalho
    return parent::delete();
  }
}
