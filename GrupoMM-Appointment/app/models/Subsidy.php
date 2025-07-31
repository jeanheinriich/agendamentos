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
 * Um registro de um subsídio de uma instalação do sistema.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use NumberFormatter;

class Subsidy
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
  protected $table = 'subsidies';
  
  /**
   * O nome da chave primária.
   *
   * @var string
   */
  protected $primaryKey = 'subsidyid';
  
  /**
   * Os campos da tabela.
   *
   * @var array
   */
  protected $fillable = [
    'contractorid',
    'installationid',
    'periodstartedat',
    'periodendedat',
    'bonus',
    'discounttype',
    'discountvalue',
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
    'bonus' => 'boolean',
    'periodstartedat' => 'date:d/m/Y',
    'periodendedat' => 'date:d/m/Y'
  ];

  /**
   * O formatador de valores de percentagem.
   *
   * @var NumberFormatter
   */
  protected $percentage;

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
      ->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, 4)
    ;
    $this
      ->percentage
      ->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, 4)
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
    // Converte nos atributos os valores booleanos
    if (array_key_exists('bonus', $attributes))
      $attributes['bonus'] = $this->toBoolean($attributes['bonus']);

    // Converte nos atributos os valores de data
    if (array_key_exists('periodstartedat', $attributes)) {
      if (trim($attributes['periodstartedat']) !== '') {
        $attributes['periodstartedat'] = $this->toDate($attributes['periodstartedat']);
      }
    }
    if (array_key_exists('periodendedat', $attributes)) {
      if (trim($attributes['periodendedat']) !== '') {
        $attributes['periodendedat'] = $this->toDate($attributes['periodendedat']);
      }
    }

    // Converte nos atributos os valores numéricos
    if (array_key_exists('discountvalue', $attributes))
      $attributes['discountvalue'] = $this->percentage->parse($attributes['discountvalue']);
    
    // Prossegue normalmente
    parent::fill($attributes);
  }
  
  /**
   * Recupera o valor do desconto.
   *
   * @param float $value
   *   O valor do desconto
   *
   * @return string
   *   O valor convertido para o formato brasileiro
   */
  public function getDiscountvalueAttribute($value)
  {
    return $this->percentage->format($value);
  }
}
