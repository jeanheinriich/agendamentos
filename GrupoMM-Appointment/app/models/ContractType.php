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
 * Um tipo (modelo) de contrato do sistema
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use NumberFormatter;

class ContractType
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
  protected $table = 'contracttypes';
  
  /**
   * O nome da chave primária.
   *
   * @var string
   */
  protected $primaryKey = 'contracttypeid';
  
  /**
   * Os campos da tabela.
   *
   * @var array
   */
  protected $fillable = [
    'contractorid',
    'name',
    'duration',
    'banktariff',
    'banktariffforreissuing',
    'finetype',
    'finevalue',
    'interesttype',
    'interestvalue',
    'active',
    'allowextendingdeadline',
    'prorata',
    'duedateonlyinworkingdays',
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
   'active' => 'boolean',
   'allowextendingdeadline' => 'boolean',
   'prorata' => 'boolean',
   'duedateonlyinworkingdays' => 'boolean'
  ];

  /**
   * O formatador de valores de percentagem.
   *
   * @var NumberFormatter
   */
  protected $percentage;
  
  /**
   * A classe do model de valores cobrados por tipo de contrato.
   *
   * @var string
   */
  protected static $contractTypeChargeClass = 'App\Models\ContractTypeCharge';

  /**
   * O construtor de nossa classe.
   *
   * @param array $attributes
   *   Os atributos
   */
  public function __construct(array $attributes = [])
  {
    parent::__construct($attributes);

    $this->percentage = numfmt_create(
      'pt_BR', NumberFormatter::DECIMAL
    );

    // Define o valor com 2 casas decimais
    $this
      ->percentage
      ->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, 3)
    ;
    $this
      ->percentage
      ->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, 3)
    ;
  }

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
    // Converte nos atributos os valores numéricos
    if (array_key_exists('finevalue', $attributes))
      $attributes['finevalue'] = $this->percentage->parse($attributes['finevalue']);
    if (array_key_exists('interestvalue', $attributes))
      $attributes['interestvalue'] = $this->percentage->parse($attributes['interestvalue']);
    if (array_key_exists('banktariff', $attributes))
      $attributes['banktariff'] = $this->percentage->parse($attributes['banktariff']);
    if (array_key_exists('banktariffforreissuing', $attributes))
      $attributes['banktariffforreissuing'] = $this->percentage->parse($attributes['banktariffforreissuing']);

    // Converte nos atributos os valores booleanos
    if (array_key_exists('active', $attributes))
      $attributes['active'] = $this->toBoolean($attributes['active']);
    if (array_key_exists('allowextendingdeadline', $attributes))
      $attributes['allowextendingdeadline'] = $this->toBoolean($attributes['allowextendingdeadline']);
    if (array_key_exists('prorata', $attributes))
      $attributes['prorata'] = $this->toBoolean($attributes['prorata']);
    if (array_key_exists('duedateonlyinworkingdays', $attributes))
      $attributes['duedateonlyinworkingdays'] = $this->toBoolean($attributes['duedateonlyinworkingdays']);
    
    // Prossegue normalmente
    parent::fill($attributes);
  }
  
  /**
   * Recupera o valor do campo valor da multa.
   *
   * @param float $value
   *   O valor da multa
   *
   * @return string
   *   O valor convertido para o formato brasileiro
   */
  public function getFinevalueAttribute($value)
  {
    return $this->percentage->format($value);
  }

  /**
   * Recupera o valor do campo valor dos juros de mora.
   *
   * @param float $value
   *   O valor dos juros de mora
   *
   * @return string
   *   O valor convertido para o formato brasileiro
   */
  public function getInterestvalueAttribute($value)
  {
    return $this->percentage->format($value);
  }

  /**
   * Recupera o valor do campo tarifa cobrada para emissão de título.
   *
   * @param float $value
   *   O valor da tarifa cobrada para emissão de título
   *
   * @return string
   *   O valor convertido para o formato brasileiro
   */
  public function getBanktariffAttribute($value)
  {
    return $this->percentage->format($value);
  }

  /**
   * Recupera o valor do campo tarifa cobrada para reemissão de título.
   *
   * @param float $value
   *   O valor da tarifa cobrada para reemissão de título
   *
   * @return string
   *   O valor convertido para o formato brasileiro
   */
  public function getBanktariffforreissuingAttribute($value)
  {
    return $this->percentage->format($value);
  }

  /**
   * Retorna o relacionamento com a tabela de valores cobrados por tipo
   * de contrato.
   *
   * @return Collection
   *   As informações de valores cobrados por por tipo de contrato
   */
  public function contractTypesCharges()
  {
    return $this->hasMany(static::$contractTypeChargeClass, 'contracttypeid');
  }
  
  /**
   * Deleta em cascata todos os registros de um tipo de contrato.
   *
   * @return bool
   */
  public function deleteCascade()
  {
    // Apaga todas as informações de valores cobrados relacionados
    $this->contractTypesCharges()->delete();
    
    // Apaga o tipo de contrato
    return parent::delete();
  }
}
