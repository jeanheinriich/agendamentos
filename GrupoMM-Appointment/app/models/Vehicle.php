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
 * Um veículo do sistema.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Vehicle
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
  protected $table = 'vehicles';

  /**
   * O nome da chave primária.
   *
   * @var string
   */
  protected $primaryKey = 'vehicleid';

  /**
   * Os campos da tabela.
   *
   * @var array
   */
  protected $fillable = [
    'contractorid',
    'customerid',
    'subsidiaryid',
    'plate',
    'vehicletypeid',
    'vehiclebrandid',
    'vehiclemodelid',
    'yearfabr',
    'yearmodel',
    'vehiclecolorid',
    'carnumber',
    'fueltype',
    'renavam',
    'vin',
    'customeristheowner',
    'ownername',
    'regionaldocumenttype',
    'regionaldocumentnumber',
    'regionaldocumentstate',
    'nationalregister',
    'address',
    'streetnumber',
    'complement',
    'district',
    'postalcode',
    'cityid',
    'email',
    'phonenumber',
    'customerpayerid',
    'subsidiarypayerid',
    'atsamecustomeraddress',
    'atsameowneraddress',
    'atanotheraddress',
    'anothername',
    'anotheraddress',
    'anotherstreetnumber',
    'anothercomplement',
    'anotherdistrict',
    'anotherpostalcode',
    'anothercityid',
    'note',
    'blocked',
    'blocknotices',
    'blockeddays',
    'remainingdays',
    'blockedstartat',
    'blockedendat',
    'monitored',
    'createdbyuserid',
    'updatedbyuserid'
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
    'customeristheowner' => 'boolean',
    'atsamecustomeraddress' => 'boolean',
    'atsameowneraddress' => 'boolean',
    'atanotheraddress' => 'boolean',
    'blocked' => 'boolean',
    'blocknotices' => 'boolean',
    'blockedstartat' => 'date:d/m/Y',
    'blockedendat' => 'date:d/m/Y'
  ];

  /**
   * A classe do model de documentos anexados.
   *
   * @var string
   */
  protected static $vehicleAttachmentsClass = 'App\Models\VehicleAttachment';

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
    // Converte nos atributos os valores booleanos
    if (array_key_exists('customeristheowner', $attributes))
      $attributes['customeristheowner'] = $this->toBoolean($attributes['customeristheowner']);
    if (array_key_exists('atsamecustomeraddress', $attributes))
      $attributes['atsamecustomeraddress'] = $this->toBoolean($attributes['atsamecustomeraddress']);
    if (array_key_exists('atsameowneraddress', $attributes))
      $attributes['atsameowneraddress'] = $this->toBoolean($attributes['atsameowneraddress']);
    if (array_key_exists('atanotheraddress', $attributes))
      $attributes['atanotheraddress'] = $this->toBoolean($attributes['atanotheraddress']);
    if (array_key_exists('blocked', $attributes))
      $attributes['blocked'] = $this->toBoolean($attributes['blocked']);
    if (array_key_exists('blocknotices', $attributes))
      $attributes['blocknotices'] = $this->toBoolean($attributes['blocknotices']);

    // Converte nos atributos os valores de data
    if (array_key_exists('blockedstartat', $attributes)) {
      if (trim($attributes['blockedstartat']) !== '') {
        $attributes['blockedstartat'] = $this->toDate($attributes['blockedstartat']);
      }
    }
    if (array_key_exists('blockedendat', $attributes)) {
      if (trim($attributes['blockedendat']) !== '') {
        $attributes['blockedendat'] = $this->toDate($attributes['blockedendat']);
      }
    }

    // Prossegue normalmente
    parent::fill($attributes);
  }

  /**
   * Retorna o relacionamento com a tabela de documentos anexados.
   *
   * @return Collection
   *   As informações de documentos anexados
   */
  public function vehicleAttachments()
  {
    return $this->hasMany(static::$vehicleAttachmentsClass, 'vehicleid', 'vehicleid');
  }

  /**
   * Deleta em cascata todos os registros de um veículo.
   *
   * @return bool
   */
  public function deleteCascade(String $path)
  {
    // Localiza todos os arquivos anexados aos dados deste veículo
    $attachments = VehicleAttachment::where('contractorid', '=', $this->contractorid)
                                    ->where('vehicleid', '=', $this->vehicleid)
                                    ->get();

    // Removemos cada arquivo anexado à este veículo
    foreach ($attachments as $count => $attachment) {
      // Passamos à rotina de exclusão a informação do path onde estão
      // localizados os arquivos anexos
      $attachment->deleteAndRemoveAttachment($path);
    }
    
    // Apaga o veículo
    return parent::delete();
  }
}
