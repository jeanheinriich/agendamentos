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
 * Um valor a ser pago ao prestador de serviços em caso de deslocamento
 * do técnico.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use NumberFormatter;

class DisplacementPaid
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
  protected $table = 'displacementpaids';
  
  /**
   * O nome da chave primária.
   *
   * @var string
   */
  protected $primaryKey = 'displacementpaidid';
  
  /**
   * Os campos da tabela.
   *
   * @var array
   */
  protected $fillable = [
    'serviceproviderid',
    'distance',
    'value'
  ];
  
  /**
   * A informação de que não temos campos de registro de modificações.
   *
   * @var boolean
   */
  public $timestamps = false;

  /**
   * O formatador de valores de monetários.
   *
   * @var NumberFormatter
   */
  protected $monetary;

  /**
   * O construtor de nossa classe.
   *
   * @param array $attributes
   *   Os atributos
   */
  public function __construct(array $attributes = [])
  {
    parent::__construct($attributes);

    $this->monetary = numfmt_create(
      'pt_BR', NumberFormatter::DECIMAL
    );

    // Define o valor com 2 casas decimais
    $this
      ->monetary
      ->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, 2)
    ;
    $this
      ->monetary
      ->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, 2)
    ;
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
      $attributes['value'] = $this->monetary->parse($attributes['value']);
    
    // Prossegue normalmente
    parent::fill($attributes);
  }
  
  /**
   * Recupera o valor do campo valor cobrado em um deslocamento.
   *
   * @param float $value
   *   O valor cobrado
   *
   * @return string
   *   O valor convertido para o formato brasileiro
   */
  public function getValueAttribute($value)
  {
    return $this->monetary->format($value);
  }
}
