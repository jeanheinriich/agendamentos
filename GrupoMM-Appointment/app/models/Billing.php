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
 * Um valor cobrado lançado em uma instalação de um contrato de cliente
 * do sistema. Os valores cobrados são posteriormente faturados para
 * gerarem uma fatura.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use NumberFormatter;

class Billing
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
  protected $table = 'billings';
  
  /**
   * O nome da chave primária.
   *
   * @var string
   */
  protected $primaryKey = 'billingid';
  
  /**
   * Os campos da tabela.
   *
   * @var array
   */
  protected $fillable = [
    'contractorid',
    'contractid',
    'installationid',
    'contractchargeid',
    'billingdate',
    'name',
    'value',
    'installmentnumber',
    'numberofinstallments',
    'granted',
    'reasonforgranting',
    'renegotiated',
    'renegotiationid',
    'ascertainedperiodid',
    'billedperiodid',
    'addmonthlyautomatic',
    'ismonthlypayment',
    'invoiced',
    'invoiceid',
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

  protected $casts = [
    'billingdate' => 'date:d/m/Y',
    'granted' => 'boolean',
    'renegotiated' => 'boolean',
    'addmonthlyautomatic' => 'boolean',
    'ismonthlypayment' => 'boolean',
    'invoiced' => 'boolean'
  ];

  /**
   * O formatador de valores monetários
   *
   * @var NumberFormatter
   */
  protected $money;

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
    if (array_key_exists('value', $attributes))
      $attributes['value'] = $this->money->parse($attributes['value']);

    // Converte nos atributos os valores booleanos
    if (array_key_exists('granted', $attributes))
      $attributes['granted'] = $this->toBoolean($attributes['granted']);
    if (array_key_exists('renegotiated', $attributes))
      $attributes['renegotiated'] = $this->toBoolean($attributes['renegotiated']);
    if (array_key_exists('addmonthlyautomatic', $attributes))
      $attributes['addmonthlyautomatic'] = $this->toBoolean($attributes['addmonthlyautomatic']);
    if (array_key_exists('ismonthlypayment', $attributes))
      $attributes['ismonthlypayment'] = $this->toBoolean($attributes['ismonthlypayment']);
    if (array_key_exists('invoiced', $attributes))
      $attributes['invoiced'] = $this->toBoolean($attributes['invoiced']);

    // Converte nos atributos os valores de data
    if (array_key_exists('billingdate', $attributes)) {
      if (trim($attributes['billingdate']) !== '') {
        $attributes['billingdate'] = $this->toDate($attributes['billingdate']);
      }
    }
    
    // Prossegue normalmente
    parent::fill($attributes);
  }
  
  /**
   * Recupera o valor do campo valor cobrado.
   *
   * @param float $value
   *   O valor cobrado
   *
   * @return string
   *   O valor convertido para o formato brasileiro
   */
  public function getValueAttribute($value)
  {
    return $this->money->format($value);
  }
}
