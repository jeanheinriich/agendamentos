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
 * O model dos dados da entidade ao qual um usuário pertence.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Authorization\Entities;

use Illuminate\Database\Eloquent\Model;

class Entity
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
  protected $table = 'entities';
  
  /**
   * O nome da chave primária.
   *
   * @var string
   */
  protected $primaryKey = 'entityid';
  
  /**
   * Os campos da tabela.
   *
   * @var array
   */
  protected $fillable = [
    'contractor',
    'contractorid',
    'customer',
    'supplier',
    'serviceprovider',
    'seller',
    'name',
    'blocked',
    'deleted'
  ];
  
  /**
   * Os campos do tipo data.
   *
   * @var array
   */
  protected $dates = [
    'createdat',
    'updatedat'
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
   'contractor' => 'boolean',
   'customer' => 'boolean',
   'supplier' => 'boolean',
   'serviceprovider' => 'boolean',
   'seller' => 'boolean',
   'blocked' => 'boolean',
   'deleted' => 'boolean'
  ];

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
   * Retorna o tipo da entidade.
   * 
   * @return string
   *   O tipo de entidade
   */
  public function getEntityType(): string
  {
    if ($this->getAttribute('contractor')) {
      return 'contratante';
    } elseif ($this->getAttribute('customer')) {
      return 'cliente';
    } elseif ($this->getAttribute('supplier')) {
      return 'fornecedor';
    } elseif ($this->getAttribute('serviceprovider')) {
      return 'prestador de serviços';
    } elseif ($this->getAttribute('seller')) {
      return 'vendedor';
    }
    
    return 'inválido';
  }
}
