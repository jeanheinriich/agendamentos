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
 * Um arquivo anexado de veículo do sistema
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Models;

use RuntimeException;
use InvalidArgumentException;
use Illuminate\Database\Eloquent\Model;

class VehicleAttachment
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
  protected $table = 'vehicleattachments';

  /**
   * O nome da chave primária.
   *
   * @var string
   */
  protected $primaryKey = 'vehicleattachmentid';

  /**
   * Os campos da tabela.
   *
   * @var array
   */
  protected $fillable = [
    'contractorid',
    'vehicleid',
    'filename',
    'realfilename'
  ];

  /**
   * A informação de que não temos campos de registro de modificações.
   *
   * @var boolean
   */
  public $timestamps = false;
  
  /**
   * Deleta em cascata todos os registros de um anexo de dados de um
   * veículo.
   *
   * @return bool
   */
  public function deleteAndRemoveAttachment(String $targetPath)
  {
    // Monta o nome do arquivo do anexo
    $filename = $targetPath .
                DIRECTORY_SEPARATOR .
                $this->contractorid .
                DIRECTORY_SEPARATOR .
                $this->filename;

    // Remove o arquivo anexado fisicamente, se existir
    if (file_exists($filename)) {
      // Verifica se o diretório é gravável
      if (!is_writable($targetPath))
        throw new InvalidArgumentException('O caminho de destino dos uploads não é gravável');
      
      // Remove o arquivo
      if (@unlink($filename) === true) {
        return parent::delete();
      } else {
        throw new RuntimeException("Não foi possível remover o arquivo '{$filename}'");
      }
    }
    
    // Apaga o registro do arquivo anexado
    return parent::delete();
  }
}
