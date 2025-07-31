<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Appointment
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
  protected $table = 'appointments';
  
  /**
   * O nome da chave primária.
   *
   * @var string
   */
  protected $primaryKey = 'appointmentid';
  
  /**
   * Os campos da tabela.
   *
   * @var array
   */
  protected $fillable = [
    'customerid',
    'technicianid',
    'scheduledat',
    'servicetype',
    'status',
    'notes',
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
   * A função de preenchimento dos campos na tabela.
   *
   * @param array $attributes
   *   Os campos com seus respectivos valores
   */
  public function fill(array $attributes)
  {
    // Prossegue normalmente
    parent::fill($attributes);
  }
}
