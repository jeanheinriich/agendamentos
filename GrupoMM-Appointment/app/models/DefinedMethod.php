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
 * Uma configuração de um meio de pagamento do sistema.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class DefinedMethod
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
  protected $table = 'definedmethods';
  
  /**
   * O nome da chave primária.
   *
   * @var string
   */
  protected $primaryKey = 'definedmethodid';
  
  /**
   * Os campos da tabela.
   *
   * @var array
   */
  protected $fillable = [
    'contractorid',
    'name',
    'paymentmethodid',
    'accountid',
    'parameters',
    'billingcounter',
    'shippingcounter',
    'daycounter',
    'counterdate',
    'blocked'
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
    'counterdate' => 'date:d/m/Y',
    'blocked' => 'boolean'
  ];
  
  /**
   * A classe do model de tarifas cobradas.
   *
   * @var string
   */
  protected static $definedMethodTariffClass = 'App\Models\DefinedMethodTariff';
  
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

    // Converte nos atributos os valores de data
    if (array_key_exists('counterdate', $attributes)) {
      if (trim($attributes['counterdate']) !== '') {
        $attributes['counterdate'] = $this->toDate($attributes['counterdate']);
      }
    }

    // Prossegue normalmente
    parent::fill($attributes);
  }

  /**
   * Retorna o relacionamento com a tabela de tarifas por meio de
   * pagamento configurado.
   *
   * @return Collection
   *   As informações de tarifas deste meio de pagamento configurado
   */
  public function definedMethodTariffs()
  {
    return $this->hasMany(static::$definedMethodTariffClass, 'definedmethodid');
  }
  
  /**
   * Deleta em cascata todos os registros de um meio de pagamento
   * configurado.
   *
   * @return bool
   */
  public function deleteCascade()
  {
    // Apaga todas as informações de tarifas relacionadas
    $this->definedMethodTariffs()->delete();
    
    // Apaga o meio de pagamento configurado
    return parent::delete();
  }
}
