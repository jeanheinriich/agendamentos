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
 * O model dos dados para o sistema de redefinição da senha de um
 * usuário de forma segura através de um sistema de tokens de lembrança.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Authorization\Reminders;

use Core\Hashing\Randomized;
use Illuminate\Database\Eloquent\Model;

class Reminder
  extends Model
  implements ReminderInterface
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
  protected $table = 'reminders';
  
  /**
   * O nome da chave primária.
   *
   * @var string
   */
  protected $primaryKey = 'reminderid';
  
  /**
   * Os campos da tabela.
   *
   * @var array
   */
  protected $fillable = [
    'token',
    'userid',
    'expires',
    'completed',
    'completedat',
  ];
  
  /**
   * Os campos do tipo data.
   *
   * @var array
   */
  protected $dates = [
    'expires',
    'completedat',
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
   'completed' => 'boolean'
  ];
  
  /**
   * A classe do model de usuários.
   *
   * @var string
   */
  protected static $usersClass = 'Core\Authorization\Users\User';
  
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
   * A função de preenchimento dos campos na tabela.
   *
   * @param array $attributes
   *   Os campos com seus respectivos valores
   */
  public function fill(array $attributes)
  {
    // Converte nos atributos os valores booleanos
    if (array_key_exists('completed', $attributes))
      $attributes['completed'] = $this->toBoolean($attributes['completed']);
    
    // Prossegue normalmente
    parent::fill($attributes);
  }

  /**
   * Retorna os dados do usuário associado ao código desta persistência.
   * 
   * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
   *   Os dados do usuário
   */
  public function user()
  {
    return $this->belongsTo(static::$usersClass, 'userid');
  }
  
  // -------------------------[ Implementações da ReminderInterface ]---
  
  /**
   * Retorna o ID da lembrança.
   * 
   * @return int
   *   O ID da lembrança
   */
  public function getReminderId(): int
  {
    return $this->getKey();
  }
  
  /**
   * Retorna o ID do usuário associado com esta lembrança.
   * 
   * @return int
   *   O ID do usuário
   */
  public function getUserID(): int
  {
    return $this->getAttribute('userid');
  }

  /**
   * Gera um token para permitir a identificação segura dos dados do
   * usuário, sem expor o ID do usuário.
   * 
   * @return string
   *   Um token único
   */
  public function generateToken(): string
  {
    $randomizedValue = new Randomized();
    $token = $this->encode(
      hash('sha384', $randomizedValue->generate(24), true)
    );
    
    return $token;
  }


  // ---------------------------------------[ Outras implementações ]---
  
  /**
   * Codifica o valor em base64 de maneira que possa ser utilizado numa
   * URL.
   * 
   * @param string $data
   *   Os dados a serem codificados
   * 
   * @return string
   *   O valor codificado
   */
  protected function encode($data): string
  {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
  }
}
