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
 * O model dos dados do usuário.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Authorization\Users;

use Core\Authorization\Groups\GroupeableInterface;
use Core\Authorization\Persistences\PersistableInterface;
use Core\Authorization\Persistences\PersistenceInterface;
use Core\Hashing\Randomized;
use Illuminate\Database\Eloquent\Model;

class User
  extends Model
  implements
    UserInterface,
    PersistableInterface,
    PersistenceInterface,
    GroupeableInterface
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
  protected $table = 'users';
  
  /**
   * O nome da chave primária.
   *
   * @var string
   */
  protected $primaryKey = 'userid';
  
  /**
   * Os campos da tabela.
   *
   * @var array
   */
  protected $fillable = [
    'entityid',
    'email',
    'password',
    'name',
    'username',
    'role',
    'phonenumber',
    'expires',
    'expiresat',
    'forcenewpassword'
  ];
  
  /**
   * Os campos do tipo data.
   *
   * @var array
   */
  protected $dates = [
    'createdat',
    'updatedat',
    'lastlogin',
    'lastFailedLogin'
  ];
  
  /**
   * Os campos ocultos.
   *
   * @var array
   */
  protected $hidden = [
    'password'
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
   * O relacionamento com a tabela para persistir os dados do usuário.
   *
   * @var string
   */
  protected $persistableKey = 'userid';
  protected $persistableRelationship = 'persistences';
  
  /**
   * A classe do model de grupos.
   *
   * @var string
   */
  protected static $groupsClass = 'Core\Authorization\Groups\Group';
  
  /**
   * A classe do model de entidades.
   *
   * @var string
   */
  protected static $entitiesClass = 'Core\Authorization\Entities\Entity';
  
  /**
   * A classe do model de persistência.
   *
   * @var string
   */
  protected static $persistencesClass = 'Core\Authorization\Persistences\Persistence';
  
  /**
   * A classe do model de permissões.
   *
   * @var string
   */
  protected static $permissionsClass = 'Core\Authorization\Permissions\Permission';
  
  /**
   * A classe do model de ativação do usuário.
   *
   * @var string
   */
  protected static $activationsClass = 'Core\Authorization\Activations\Activation';
  
  /**
   * A classe do model de memorizar a autenticação no navegador.
   *
   * @var string
   */
  protected static $remindersClass = 'Core\Authorization\Reminders\Reminder';
  
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
  

  // -----------------------------[ Implementações da UserInterface ]---
  
  /**
   * Retorna a ID do usuário.
   * 
   * @return int
   *   A id do usuário
   */
  public function getUserId(): int
  {
    return $this->getKey();
  }
  
  /**
   * Retorna o nome de login do usuário (nome do usuário).
   * 
   * @return string
   *   O nome de login do usuário
   */
  public function getUserLogin(): string
  {
    return $this->getAttribute('username');
  }
  
  /**
   * Retorna o atributo nome do usuário (nome completo).
   * 
   * @return string
   *   O nome do usuário
   */
  public function getUserName(): string
  {
    return $this->getAttribute('name');
  }
  
  /**
   * Retorna o atributo senha do usuário (encriptada).
   * 
   * @return string
   *   A senha do usuário encriptada
   */
  public function getUserPassword(): string
  {
    return $this->getAttribute('password');
  }
  
  /**
   * Remove um usuário do sistema.
   * 
   * @return bool
   *   O resultado da operação
   */
  public function delete(): bool
  {
    if ($this->exists) {
      $this->activations()->delete();
      $this->persistences()->delete();
      $this->reminders()->delete();
    }
    
    return parent::delete();
  }
  

  // ----------------------[ Implementações da PersistableInterface ]---
  // Aqui é gerenciado o token de persistência relacionado com o usuário
  // -------------------------------------------------------------------
  
  /**
   * Retorna o ID da persistência.
   * 
   * @return int
   *   O ID da persistência
   */
  public function getPersistableId(): int
  {
    return $this->getKey();
  }
  
  /**
   * Retorna o nome da chave de persistência.
   * 
   * @return string
   *   O nome da chave de persistência
   */
  public function getPersistableKey(): string
  {
    return $this->persistableKey;
  }
  
  /**
   * Define o nome da chave de persistência.
   * 
   * @param string $key
   *   O novo nome da chave de persistência
   */
  public function setPersistableKey(string $key): void
  {
    $this->persistableKey = $key;
  }
  
  /**
   * Gera um seletor de 32 dígitos para localizar o token na base de
   * dados. Isto evita que o ID do usuário seja enviado para o
   * navegador.
   * 
   * @return string
   *   O seletor
   */
  public function generateSelectorCode(): string
  {
    $selector = md5(uniqid(rand(), true));
    
    return $selector;
  }
  
  /**
   * Gera um código de validação aleatório para permitir a persistência
   * segura dos dados do usuário.
   * 
   * @return string
   *   O código de validação
   */
  public function generateValidatorCode(): string
  {
    $randomizedValue = new Randomized();
    $validator = base64_encode($randomizedValue->generate(24));
    
    return $validator;
  }
  
  /**
   * Retorna o nome da tabela que contém o relacionamento com as
   * informações de persistência para cada usuário.
   * 
   * @return string
   *   O nome da tabela relacionada com as informações de persistência
   */
  public function getPersistableRelationship(): string
  {
    return $this->persistableRelationship;
  }

  // ----------------------[ Implementações da PersistenceInterface ]---
  
  
  // -----------------------[ Implementações da GroupeableInterface ]---
  
  /**
   * @todo Desativamos os códigos de grupos, limitando o usuário à um
   * único grupo possível. Precisamos reimplementar esta parte.
   */
  
  /**
   * Recupera os grupos dos quais o usuário faz parte.
   * 
   * @return \Illuminate\Database\Eloquent\Relations\HasMany
   *   As informações dos grupos dos quais o usuário faz parte
   */
  public function getGroups()
  {
    return $this->group();
  }
  
  /**
   * Verifica se o usuário está no grupo indicado.
   * 
   * @param mixed $group
   *   Um objeto com as informações do grupo e/ou o ID do grupo para o
   * qual desejamos verificar
   *                       
   * @return bool
   */
  public function inGroup($group): bool
  {
    //if ($group instanceof GroupInterface) {
    //  $groupId = $group->getGroupId();
    //}
    //
    //foreach ($this->groups AS $instance) {
    //  if ($group instanceof GroupInterface) {
    //    if ($instance->getGroupId() === $groupId) {
    //      return true;
    //    }
    //  } else {
    //    if ($instance->getGroupId() == $group) {
    //      return true;
    //    }
    //  }
    //}
    return false;
  }

  /**
   * Retorna o relacionamento com a tabela de grupos.
   *
   * @return \Illuminate\Database\Eloquent\Relations\HasOne
   *   As informações de grupos
   */
  public function group()
  {
    return $this->hasOne(static::$groupsClass, 'groupid', 'groupid');
  }
  
  
  // --------------------------[ Relacionamentos com outras tabelas ]---
  
  /**
   * Retorna o relacionamento com a tabela de entidades.
   * 
   * @return \Illuminate\Database\Eloquent\Relations\HasOne
   *   As informações de entidades
   */
  public function entity()
  {
    return $this->hasOne(static::$entitiesClass, 'entityid', 'entityid');
  }
  
  /**
   * Retorna o relacionamento com a tabela de ativações.
   * 
   * @return \Illuminate\Database\Eloquent\Relations\HasMany
   *   As informações de ativações
   */
  public function activations()
  {
    return $this->hasMany(static::$activationsClass, 'userid');
  }
  
  /**
   * Retorna o relacionamento com a tabela de tokens de persistência.
   * 
   * @return \Illuminate\Database\Eloquent\Relations\HasMany
   *   As informações de persistência
   */
  public function persistences()
  {
    return $this->hasMany(static::$persistencesClass, 'userid');
  }
  
  /**
   * Retorna o relacionamento com a tabela de permissões por grupo.
   * 
   * @return \Illuminate\Database\Eloquent\Relations\HasMany
   *   As informações de permissões por grupo
   */
  public function permissions()
  {
    return $this->hasMany(static::$permissionsClass, 'groupid');
  }
  
  /**
   * Retorna o relacionamento com a tabela de tokens de recuperação de
   * senha.
   * 
   * @return \Illuminate\Database\Eloquent\Relations\HasMany
   *   As informações do token de recuperação de senha
   */
  public function reminders()
  {
    return $this->hasMany(static::$remindersClass, 'userid');
  }
}
