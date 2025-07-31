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
 * Um manipulador de grupos de usuários.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Authorization\Groups;

use Illuminate\Database\Eloquent\Model;

class Group
  extends Model
  implements GroupInterface
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
  protected $table = 'groups';
  
  /**
   * O nome da chave primária.
   *
   * @var string
   */
  protected $primaryKey = 'groupid';
  
  /**
   * Os campos da tabela.
   *
   * @var array
   */
  protected $fillable = [
    'name',
    'permissions',
  ];
  
  /**
   * A informação de que não temos campos de registro de modificações.
   *
   * @var boolean
   */
  public $timestamps = false;
  
  /**
   * A classe do model de usuários.
   *
   * @var string
   */
  protected static $usersClass = 'Core\Authorization\Users\User';
  
  /**
   * A classe do model de permissões do grupo.
   *
   * @var string
   */
  protected static $permissionsPerGroupClass = 'Core\Authorization\Permissions\PermissionsPerGroup';
  
  // ----------------------------[ Implementações da GroupInterface ]---
  
  /**
   * Retorna a ID do grupo de usuários.
   * 
   * @return int
   *   A ID do grupo
   */
  public function getGroupId(): int
  {
    return $this->getKey();
  }
  
  /**
   * Retorna o atributo nome do grupo.
   * 
   * @return string
   *   O nome do grupo
   */
  public function getGroupName(): string
  {
    return $this->getAttribute('name');
  }
  
  /**
   * Obtém os usuários associados a este grupo.
   * 
   * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
   *   Os usuários que pertencem à este grupo
   */
  public function getUsers()
  {
    return $this->belongsTo(static::$usersClass, 'groupid');
  }


  // ---------------------------------------[ Outras implementações ]---
  
  /**
   * Obtém as permissões associadas a este grupo.
   *
   * @return \Illuminate\Database\Eloquent\Relations\hasMany
   *   As permissões associadas a este grupo
   */
  public function permissions()
  {
    return $this->hasMany(static::$permissionsPerGroupClass, 'groupid');
  }
}
