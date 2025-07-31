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
 * Um perfil de envio de notificações no sistema.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MailingProfile
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
  protected $table = 'mailingprofiles';
  
  /**
   * O nome da chave primária.
   *
   * @var string
   */
  protected $primaryKey = 'mailingprofileid';
  
  /**
   * Os campos da tabela.
   *
   * @var array
   */
  protected $fillable = [
    'contractorid',
    'name',
    'description'
  ];

  /**
   * A classe do model de ações de sistema registradas por perfil.
   *
   * @var string
   */
  protected static $actionsPerProfileClass = 'App\Models\ActionPerProfile';
  
  /**
   * A informação de que não temos campos de registro de modificações.
   *
   * @var boolean
   */
  public $timestamps = false;
  
  /**
   * Retorna o relacionamento com a tabela de eventos de sistema
   * registrados por perfil.
   *
   * @return Collection
   *   As informações de eventos de sistema registrados por perfil
   */
  public function actionsPerProfile()
  {
    return $this
      ->hasMany(static::$actionsPerProfileClass, 'mailingprofileid')
    ;
  }

  /**
   * Remove um perfil de envio de notificações e todos os eventos nos
   * quais este perfil foi registrado.
   *
   * @return bool
   */
  public function deleteCascade()
  {
    // Apaga todos os eventos de sistema relacionados com este perfil
    $this->actionsPerProfile()->delete();
    
    // Apaga o perfil
    return parent::delete();
  }
}
