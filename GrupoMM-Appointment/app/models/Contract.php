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
 * Um contrato de um cliente do sistema.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use NumberFormatter;

class Contract
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
  protected $table = 'contracts';
  
  /**
   * O nome da chave primária.
   *
   * @var string
   */
  protected $primaryKey = 'contractid';
  
  /**
   * Os campos da tabela.
   *
   * @var array
   */
  protected $fillable = [
    'contractorid',
    'customerid',
    'subsidiaryid',
    'customerpayerid',
    'subsidiarypayerid',
    'planid',
    'subscriptionplanid',
    'signaturedate',
    'enddate',
    'monthprice',
    'effectivepricedate',
    'dateofnextreadjustment',
    'duedayid',
    'paymentconditionid',
    'prepaid',
    'additionalpaymentconditionid',
    'chargeanytariffs',
    'unifybilling',
    'starttermafterinstallation',
    'manualreadjustment',
    'active',
    'notchargeloyaltybreak',
    'maxwaitingtime',
    'unproductivevisit',
    'unproductivevisittype',
    'minimumtime',
    'minimumtimetype',
    'frustratedvisit',
    'frustratedvisittype',
    'geographiccoordinateid',
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
   * O formatador de valores monetários
   *
   * @var NumberFormatter
   */
  protected $money;
  
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
    'prepaid' => 'boolean',
    'chargeanytariffs' => 'boolean',
    'unifybilling' => 'boolean',
    'starttermafterinstallation' => 'boolean',
    'manualreadjustment' => 'boolean',
    'active' => 'boolean',
    'notchargeloyaltybreak' => 'boolean',
    'signaturedate' => 'date:d/m/Y',
    'enddate' => 'date:d/m/Y',
    'dateofnextreadjustment' => 'date:d/m/Y'
  ];
  
  /**
   * A classe do model de reajustes ocorridos.
   *
   * @var string
   */
  protected static $readjustmentClass = 'App\Models\Readjustment';
  
  /**
   * A classe do model de valores cobrados por contrato.
   *
   * @var string
   */
  protected static $contractChargeClass = 'App\Models\ContractCharge';
  
  /**
   * A classe do model de características técnicas adicionais por
   * contrato.
   *
   * @var string
   */
  protected static $contractFeatureClass = 'App\Models\ContractFeature';
  
  /**
   * A classe do model de instalações por contrato.
   *
   * @var string
   */
  protected static $installationClass = 'App\Models\Installation';

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
    if (array_key_exists('unproductivevisit', $attributes))
      $attributes['unproductivevisit'] = $this->money->parse($attributes['unproductivevisit']);
    if (array_key_exists('frustratedvisit', $attributes))
      $attributes['frustratedvisit'] = $this->money->parse($attributes['frustratedvisit']);

    // Converte nos atributos os valores booleanos
    if (array_key_exists('prepaid', $attributes))
      $attributes['prepaid'] = $this->toBoolean($attributes['prepaid']);
    if (array_key_exists('chargeanytariffs', $attributes))
      $attributes['chargeanytariffs'] = $this->toBoolean($attributes['chargeanytariffs']);
    if (array_key_exists('unifybilling', $attributes))
      $attributes['unifybilling'] = $this->toBoolean($attributes['unifybilling']);
    if (array_key_exists('starttermafterinstallation', $attributes))
      $attributes['starttermafterinstallation'] = $this->toBoolean($attributes['starttermafterinstallation']);
    if (array_key_exists('manualreadjustment', $attributes))
      $attributes['manualreadjustment'] = $this->toBoolean($attributes['manualreadjustment']);
    if (array_key_exists('active', $attributes))
      $attributes['active'] = $this->toBoolean($attributes['active']);
    if (array_key_exists('notchargeloyaltybreak', $attributes))
      $attributes['notchargeloyaltybreak'] = $this->toBoolean($attributes['notchargeloyaltybreak']);

    // Converte nos atributos os valores de data
    if (array_key_exists('signaturedate', $attributes)) {
      if (trim($attributes['signaturedate']) !== '') {
        $attributes['signaturedate'] = $this->toDate($attributes['signaturedate']);
      }
    }
    if (array_key_exists('enddate', $attributes)) {
      if (trim($attributes['enddate']) !== '') {
        $attributes['enddate'] = $this->toDate($attributes['enddate']);
      }
    }
    if (array_key_exists('dateofnextreadjustment', $attributes)) {
      if (trim($attributes['dateofnextreadjustment']) !== '') {
        $attributes['dateofnextreadjustment'] = $this->toDate($attributes['dateofnextreadjustment']);
      }
    }
    
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
    if (is_string($value)) {
      // O valor já está formatado
      return $value;
    }

    return $this->money->format($value);
  }
  
  /**
   * Recupera o valor da taxa por visita improdutiva.
   *
   * @param float $value
   *   O valor da taxa
   *
   * @return string
   *   O valor convertido para o formato brasileiro
   */
  public function getUnproductivevisitAttribute($value)
  {
    return $this->money->format($value);
  }
  
  /**
   * Recupera o valor da taxa por visita frustrada.
   *
   * @param float $value
   *   O valor da taxa
   *
   * @return string
   *   O valor convertido para o formato brasileiro
   */
  public function getFrustratedvisitAttribute($value)
  {
    return $this->money->format($value);
  }
  
  /**
   * Retorna o relacionamento com a tabela de reajustes ocorridos por
   * contrato.
   *
   * @return Collection
   *   As informações de reajustes por contrato
   */
  public function readjustments()
  {
    return $this->hasMany(static::$readjustmentClass, 'contractid');
  }
  
  /**
   * Retorna o relacionamento com a tabela de valores cobrados por
   * contrato.
   *
   * @return Collection
   *   As informações de valores cobrados por contrato
   */
  public function contractCharges()
  {
    return $this->hasMany(static::$contractChargeClass, 'contractid');
  }
  
  /**
   * Retorna o relacionamento com a tabela de características técnicas
   * adicionais por contrato.
   *
   * @return Collection
   *   As informações de características técnicas adicionais por
   *   contrato
   */
  public function contractFeatures()
  {
    return $this->hasMany(static::$contractFeatureClass, 'contractid');
  }
  
  /**
   * Retorna o relacionamento com a tabela de instalações por contrato.
   *
   * @return Collection
   *   As informações de instalações por contrato
   */
  public function installations()
  {
    return $this->hasMany(static::$installationClass, 'contractid');
  }
  
  /**
   * Deleta em cascata todos os registros de um contrato.
   *
   * @return bool
   */
  public function deleteCascade()
  {
    // Remove todos os reajustes relacionados
    $this->readjustments()->delete();

    // Remove todos os valores cobrados relacionados
    $this->contractCharges()->delete();
    
    // Remove todas as características técnicas relacionadas
    $this->contractFeatures()->delete();
    
    // Remove as informações de instalações
    
    // Localiza todas as instalações definidas neste contrato
    $installations = $this->installations()->get();

    // Para cada instalação, remove todos os dados relacionados
    foreach ($installations as $installation) {
      // Remove a instalação e todos os dados relacionados
      $installation->deleteCascade();
    }
    
    // Apaga o contrato
    return parent::delete();
  }
}
