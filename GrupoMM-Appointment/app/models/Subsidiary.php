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
 * Uma unidade/filial do sistema. Por organização, mesmo uma pessoa 
 * física possui uma "unidade" que contém os seus dados
 * pessoais.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Subsidiary
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
  protected $table = 'subsidiaries';
  
  /**
   * O nome da chave primária.
   *
   * @var string
   */
  protected $primaryKey = 'subsidiaryid';
  
  /**
   * Os campos da tabela.
   *
   * @var array
   */
  protected $fillable = [
    'entityid',
    'headoffice',
    'name',
    'regionaldocumenttype',
    'regionaldocumentnumber',
    'regionaldocumentstate',
    'nationalregister',
    'municipalinscription',
    'birthday',
    'genderid',
    'maritalstatusid',
    'postalcode',
    'address',
    'streetnumber',
    'complement',
    'district',
    'cityid',
    'personname',
    'department',
    'blocked',
    'createdbyuserid',
    'updatedbyuserid',
    'deleted',
    'deletedat',
    'deletedbyuserid'
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
    'headoffice' => 'boolean',
    'blocked' => 'boolean',
    'deleted' => 'boolean',
    'birthday' => 'date:d/m/Y'
  ];
  
  /**
   * A classe do model de contatos adicionais.
   *
   * @var string
   */
  protected static $mailingAddressClass = 'App\Models\MailingAddress';
  
  /**
   * A classe do model de e-mails.
   *
   * @var string
   */
  protected static $mailingClass = 'App\Models\Mailing';
  
  /**
   * A classe do model de telefones.
   *
   * @var string
   */
  protected static $phoneClass = 'App\Models\Phone';
  
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
  protected function toBoolean($value)
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
    // Converte nos atributos os valores de data
    if (array_key_exists('birthday', $attributes)) {
      if (trim($attributes['birthday']) !== '') {
        $attributes['birthday'] = $this->toDate($attributes['birthday']);
      }
    }

    // Converte nos atributos os valores booleanos
    if (array_key_exists('headoffice', $attributes))
      $attributes['headoffice'] = $this->toBoolean($attributes['headoffice']);
    if (array_key_exists('blocked', $attributes))
      $attributes['blocked'] = $this->toBoolean($attributes['blocked']);
    if (array_key_exists('deleted', $attributes))
      $attributes['deleted'] = $this->toBoolean($attributes['deleted']);
    
    // Prossegue normalmente
    parent::fill($attributes);
  }
  
  /**
   * Retorna o relacionamento com a tabela de informações de contatos
   * adicionais.
   *
   * @return Collection
   *   As informações de contatos adicionais
   */
  public function mailingAddresses()
  {
    return $this
      ->hasMany(static::$mailingAddressClass, 'subsidiaryid')
    ;
  }
  
  /**
   * Retorna o relacionamento com a tabela de e-mails.
   *
   * @return Collection
   *   As informações de e-mails
   */
  public function mailings()
  {
    return $this
      ->hasMany(static::$mailingClass, 'subsidiaryid')
    ;
  }
  
  /**
   * Retorna o relacionamento com a tabela de telefones.
   *
   * @return Collection
   *   As informações de telefones
   */
  public function phones()
  {
    return $this
      ->hasMany(static::$phoneClass, 'subsidiaryid')
    ;
  }
  
  /**
   * Deleta em cascata todos os registros de uma unidade/filial.
   *
   * @return bool
   */
  public function deleteCascade()
  {
    // Apaga todos os contatos relacionados
    $this->mailingAddresses()->delete();
    $this->mailings()->delete();
    $this->phones()->delete();

    // Apaga a unidade/filial
    return parent::delete();
  }
}
