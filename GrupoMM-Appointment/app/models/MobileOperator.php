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
 * Uma operadora de telefonia móvel do sistema
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MobileOperator
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
  protected $table = 'mobileoperators';
  
  /**
   * O nome da chave primária.
   *
   * @var string
   */
  protected $primaryKey = 'mobileoperatorid';
  
  /**
   * Os campos da tabela.
   *
   * @var array
   */
  protected $fillable = [
    'name',
    'logo'
  ];
  
  /**
   * A informação de que não temos campos de registro de modificações.
   *
   * @var boolean
   */
  public $timestamps = false;
  
  /**
   * A classe do model de códigos de rede por operadora.
   *
   * @var string
   */
  protected static $mobileNetworkCodeClass = 'App\Models\MobileNetworkCode';
  
  /**
   * Retorna o relacionamento com a tabela de códigos de rede por
   * operadora.
   *
   * @return Collection
   *   As informações de códigos de rede por operadora
   */
  public function mobileNetworkCodes()
  {
    return $this->hasMany(static::$mobileNetworkCodeClass, 'mobileoperatorid');
  }
  
  /**
   * Deleta em cascata todos os registros de uma operadora de telefonia
   * móvel.
   *
   * @return bool
   */
  public function deleteCascade()
  {
    // Apaga todos os códigos de rede relacionados com esta operadora de
    // telefonia móvel
    $this->mobileNetworkCodes()->delete();
    
    // Apaga a operadora de telefonia móvel
    return parent::delete();
  }
}
