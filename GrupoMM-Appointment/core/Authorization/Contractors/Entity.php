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
 * O model dos dados da entidade contratante.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Authorization\Contractors;

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
    'contractorid',
    'name',
    'uuid'
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
   * Força a formatação de Data/Hora para o Eloquent.
   * 
   * @return string
   *   O formato de data/hora
   */
  public function getDateFormat()
  {
    return 'Y-m-d H:i:s.u';
  }
  
  // -----------------------[ Implementações da ContractorInterface ]---
  
  /**
   * Retorna a ID do contratante.
   * 
   * @return int
   *   A ID do contratante
   */
  public function getContractorId(): int
  {
    return $this->getAttribute('contractorid');
  }
  
  /**
   * Retorna o atributo nome do contratante.
   * 
   * @return string
   *   O nome do contratante
   */
  public function getEntityName(): string
  {
    return $this->getAttribute('name');
  }
  
  // ---------------------------------------[ Outras implementações ]---
  
  /**
   * Retorna a ID da entidade.
   * 
   * @return int
   *   A ID da entidade
   */
  public function getEntityId(): int
  {
    return $this->getKey();
  }
}
