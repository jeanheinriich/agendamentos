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
 * Um período apurado de uma instalação em um contrato do cliente. É com
 * base nesta apuração que é determinado o valor da mensalidade do
 * cliente.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use NumberFormatter;

class AscertainedPeriod
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
  protected $table = 'ascertainedperiods';
  
  /**
   * O nome da chave primária.
   *
   * @var string
   */
  protected $primaryKey = 'ascertainedperiodid';
  
  /**
   * Os campos da tabela.
   *
   * @var array
   */
  protected $fillable = [
    'contractorid',
    'installationid',
    'referencemonthyear',
    'startdate',
    'enddate',
    'ascertaineddays',
    'monthprice',
    'grossvalue',
    'discountvalue',
    'finalvalue'
  ];
  
  /**
   * A informação de que não temos campos de registro de modificações.
   *
   * @var boolean
   */
  public $timestamps = false;

  protected $casts = [
    'startdate' => 'date:d/m/Y',
    'enddate' => 'date:d/m/Y'
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
    if (array_key_exists('grossvalue', $attributes))
      $attributes['grossvalue'] = $this->money->parse($attributes['grossvalue']);
    if (array_key_exists('discountvalue', $attributes))
      $attributes['discountvalue'] = $this->money->parse($attributes['discountvalue']);
    if (array_key_exists('finalvalue', $attributes))
      $attributes['finalvalue'] = $this->money->parse($attributes['finalvalue']);

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
    
    // Prossegue normalmente
    parent::fill($attributes);
  }
  
  /**
   * Recupera o valor do campo valor da mensalidade.
   *
   * @param float $value
   *   O valor da mensalidade
   *
   * @return string
   *   O valor convertido para o formato brasileiro
   */
  public function getMonthpriceAttribute($value)
  {
    return $this->money->format($value);
  }
  
  /**
   * Recupera o valor do campo valor bruto.
   *
   * @param float $value
   *   O valor bruto calculado do período
   *
   * @return string
   *   O valor convertido para o formato brasileiro
   */
  public function getGrossvalueAttribute($value)
  {
    return $this->money->format($value);
  }
  
  /**
   * Recupera o valor do campo desconto.
   *
   * @param float $value
   *   O desconto calculado do período
   *
   * @return string
   *   O valor convertido para o formato brasileiro
   */
  public function getDiscountvalueAttribute($value)
  {
    return $this->money->format($value);
  }
  
  /**
   * Recupera o valor do campo valor final a ser cobrado.
   *
   * @param float $value
   *   O valor cobrado
   *
   * @return string
   *   O valor convertido para o formato brasileiro
   */
  public function getFinalvalueAttribute($value)
  {
    return $this->money->format($value);
  }
}
