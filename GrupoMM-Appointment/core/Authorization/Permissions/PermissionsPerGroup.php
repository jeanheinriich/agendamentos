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
 * Um manipulador de permissões por grupo de usuários.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Authorization\Permissions;

use Illuminate\Database\Eloquent\Model;

class PermissionsPerGroup
  extends Model
{
  /**
   * O nome da conexão a ser utilizada.
   *
   * @var string
   */
  protected $connection = 'erp';
  
  /**
   * O nome da tabela.
   *
   * @var string
   */
  protected $table = 'permissionspergroups';
  
  /**
   * O nome da chave primária.
   *
   * @var string
   */
  protected $primaryKey = 'permissionpergroupid';
  
  /**
   * Os campos da tabela.
   *
   * @var array
   */
  protected $fillable = [
    'groupid',
    'permissionid',
    'httpmethod',
  ];

  /**
   * A informação de que não temos campos de registro de modificações.
   *
   * @var boolean
   */
  public $timestamps = false;
  
  /**
   * A classe do model de grupos.
   *
   * @var string
   */
  protected static $groupsClass = 'Core\Authorization\Groups\Group';
  
  /**
   * A classe do model de permissões.
   *
   * @var string
   */
  protected static $permissionsClass = 'Core\Authorization\Permissions\Permission';
  
  /**
   * Retorna a ID do relacionamento entre a permissão e o grupo de
   * usuários.
   * 
   * @return int
   *   A ID desta relação
   */
  public function getPermissionPerGroupId(): int
  {
    return $this->getKey();
  }
  
  /**
   * Retorna o relacionamento com a tabela de grupos.
   * 
   * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
   *   As informações do grupo
   */
  public function group()
  {
    return $this->belongsTo(static::$groupsClass, 'groupid');
  }
  
  /**
   * Retorna o relacionamento com a tabela de permissões.
   * 
   * @return \Illuminate\Database\Eloquent\Relations\HasOne
   *   Os dados da permissão
   */
  public function permission()
  {
    return $this->hasOne(
      static::$permissionsClass, 'permissionid', 'permissionid'
    );
  }
}
