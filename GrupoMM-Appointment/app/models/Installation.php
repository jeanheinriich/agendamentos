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
 * O controle de uma instalação de um contrato de um cliente do sistema.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use NumberFormatter;

class Installation
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
  protected $table = 'installations';
  
  /**
   * O nome da chave primária.
   *
   * @var string
   */
  protected $primaryKey = 'installationid';
  
  /**
   * Os campos da tabela.
   *
   * @var array
   */
  protected $fillable = [
    'installationnumber',
    'contractorid',
    'customerid',
    'subsidiaryid',
    'contractid',
    'planid',
    'subscriptionplanid',
    'startdate',
    'enddate',
    'monthprice',
    'effectivepricedate',
    'dateofnextreadjustment',
    'lastdayofcalculatedperiod',
    'lastdayofbillingperiod',
    'notchargeloyaltybreak',
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
    'startdate' => 'date:d/m/Y',
    'enddate' => 'date:d/m/Y',
    'effectivepricedate' => 'date:d/m/Y',
    'dateofnextreadjustment' => 'date:d/m/Y',
    'lastdayofcalculatedperiod' => 'date:d/m/Y',
    'lastdayofbillingperiod' => 'date:d/m/Y',
    'notchargeloyaltybreak' => 'boolean'
  ];

  /**
   * O formatador de valores monetários
   *
   * @var NumberFormatter
   */
  protected $money;

  /**
   * A classe do model de reajustes ocorridos.
   *
   * @var string
   */
  protected static $readjustmentOnInstallationClass = 'App\Models\ReadjustmentOnInstallation';

  /**
   * O construtor de nossa classe.
   *
   * @param array $attributes
   *   Os atributos
   */
  public function __construct(array $attributes = [])
  {
    parent::__construct($attributes);

    $this->money = numfmt_create(
      'pt_BR', NumberFormatter::DECIMAL
    );

    // Define o valor com 2 casas decimais
    $this
      ->money
      ->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, 2)
    ;
    $this
      ->money
      ->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, 2)
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
  protected function toBoolean($value): bool
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
    // Converte nos atributos os valores numéricos
    if (array_key_exists('monthprice', $attributes))
      $attributes['monthprice'] = $this->money->parse($attributes['monthprice']);

    // Converte nos atributos os valores de data
    if (array_key_exists('startdate', $attributes)) {
      if (trim($attributes['startdate']) !== '') {
        $attributes['startdate'] = $this->toDate($attributes['startdate']);
      }
    }
    if (array_key_exists('enddate', $attributes)) {
      if (trim($attributes['enddate']) !== '') {
        $attributes['enddate'] = $this->toDate($attributes['enddate']);
      }
    }
    if (array_key_exists('effectivepricedate', $attributes)) {
      if (trim($attributes['effectivepricedate']) !== '') {
        $attributes['effectivepricedate'] = $this->toDate($attributes['effectivepricedate']);
      }
    }
    if (array_key_exists('dateofnextreadjustment', $attributes)) {
      if (trim($attributes['dateofnextreadjustment']) !== '') {
        $attributes['dateofnextreadjustment'] = $this->toDate($attributes['dateofnextreadjustment']);
      }
    }
    if (array_key_exists('lastdayofcalculatedperiod', $attributes)) {
      if (trim($attributes['lastdayofcalculatedperiod']) !== '') {
        $attributes['lastdayofcalculatedperiod'] = $this->toDate($attributes['lastdayofcalculatedperiod']);
      }
    }
    if (array_key_exists('lastdayofbillingdate', $attributes)) {
      if (trim($attributes['lastdayofbillingdate']) !== '') {
        $attributes['lastdayofbillingdate'] = $this->toDate($attributes['lastdayofbillingdate']);
      }
    }

    // Converte nos atributos os valores booleanos
    if (array_key_exists('notchargeloyaltybreak', $attributes))
      $attributes['notchargeloyaltybreak'] = $this->toBoolean($attributes['notchargeloyaltybreak']);
    
    // Prossegue normalmente
    parent::fill($attributes);
  }
  
  /**
   * Recupera o valor da mensalidade.
   *
   * @param float $value
   *   O valor da multa
   *
   * @return string
   *   O valor convertido para o formato brasileiro
   */
  public function getMonthpriceAttribute($value)
  {
    return $this->money->format($value);
  }
  
  /**
   * Retorna o relacionamento com a tabela de reajustes ocorridos por
   * instalação.
   *
   * @return Collection
   *   As informações de reajustes por instalação
   */
  public function readjustments()
  {
    return $this->hasMany(static::$readjustmentOnInstallationClass, 'installationid');
  }
  
  /**
   * Deleta em cascata todos os registros de uma instalação.
   *
   * @return bool
   */
  public function deleteCascade()
  {
    // Apaga todos os reajustes relacionados
    $this->readjustments()->delete();
    
    // Apaga o contrato
    return parent::delete();
  }
}
