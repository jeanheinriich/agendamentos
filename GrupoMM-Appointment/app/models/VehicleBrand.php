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
 * Uma marca de veículo do sistema
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleBrand
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
  protected $table = 'vehiclebrands';
  
  /**
   * O nome da chave primária.
   *
   * @var string
   */
  protected $primaryKey = 'vehiclebrandid';
  
  /**
   * Os campos da tabela.
   *
   * @var array
   */
  protected $fillable = [
    'contractorid',
    'name',
    'fipename'
  ];
  
  /**
   * A informação de que não temos campos de registro de modificações.
   *
   * @var boolean
   */
  public $timestamps = false;
  
  /**
   * A classe do model de tipos de veículos fabricados por marca.
   *
   * @var string
   */
  protected static $vehicleTypesPerBrandClass = 'App\Models\VehicleTypePerBrand';

  /**
   * A função responsável pela inserção/atualização dos dados durante o
   * processo de sincronismo.
   *
   * @return void
   */
  public function sync() {
    // Executamos à stored procedure para sincronizar os dados
    $sql = sprintf(""
      . "SELECT synchronizevehicletypeperbrand(%d, 0, %d, '%s', '%s');",
      $this->contractorid,
      $this->vehicletypeid,
      $this->fipename,
      $this->fipeid)
    ;
    $connection = $this->getConnection();
    $connection->statement($sql);
  }
  
  /**
   * Retorna o relacionamento com a tabela de tipos de veículos
   * fabricados por marca.
   *
   * @return Collection
   *   As informações de tipos de veículos
   */
  public function vehicleTypesPerBrand()
  {
    return $this->hasMany(
      static::$vehicleTypesPerBrandClass, 'vehiclebrandid'
    );
  }
  
  /**
   * Deleta em cascata todos os registros de marcas de veículos.
   *
   * @return bool
   */
  public function deleteCascade()
  {
    // Apaga todos os tipos de veículos fabricados relacionados com esta
    // marca
    $this->vehicleTypesPerBrand()->delete();
    
    // Apaga a marca de veículo
    return parent::delete();
  }
}
