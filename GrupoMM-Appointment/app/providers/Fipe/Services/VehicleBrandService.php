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
 * As requisições ao serviço de obtenção dos dados de tipos de veículos
 * através da API do sistema Fipe.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

/**
 * API Fipe
 * https://deividfortuna.github.io/fipe/
 *
 * Copyright (c) 2015 - Deivid Fortuna <deividfortuna@gmail.com>
 */

namespace App\Providers\Fipe\Services;

use App\Models\VehicleBrand;
use App\Models\VehicleType;
use Core\HTTP\Filters\DataFilter;
use Core\HTTP\Filters\DataFilterIterator;
use Core\HTTP\Service;
use Exception;
use Illuminate\Database\QueryException;

class VehicleBrandService
  extends FipeService
  implements Service
{
  /**
   * O caminho para nosso serviço.
   *
   * @var string
   */
  protected $path = '{vehicleType}/marcas';

  /**
   * Recupera as informações de tipos de veículos para permitir
   * requisitar as informações do sistema Fipe.
   *
   * @return DataFilter
   *   O filtro que permite iterar sobre os tipos de veículos
   */
  protected function getVehicleTypes(): DataFilter
  {
    try {
      // Recupera as informações dos tipos de veículos cadastrados para
      // os quais temos a informação do sistema fipe
      $vehicleTypes = VehicleType::where('fipename', '<>', '')
        ->orderBy('vehicletypeid')
        ->get([
            'vehicletypeid AS id',
            'name',
            'singular',
            'fipename'
          ])
        ->toArray()
      ;
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "tipos de veículos. Erro interno no banco de dados: {error}.",
        [ 'error'  => $exception->getMessage() ]
      );

      $vehicleTypes = [];
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "tipos de veículos. Erro interno: {error}.",
        [ 'error'  => $exception->getMessage() ]
      );

      $vehicleTypes = [];
    }

    // Cria um novo filtro para permitir a filtragem dos dados em cada
    // requisição
    $filterParameters = [
      'vehicleType' => 'fipename'
    ];
    $vehicleTypeFilter = new DataFilterIterator($filterParameters,
      $vehicleTypes);

    return $vehicleTypeFilter;
  }

  /**
   * O método responsável por executar as requisições ao serviço,
   * sincronizando os dados.
   */
  public function synchronize(): void
  {
    // Ajusta os parâmetros para sincronismo
    $this->synchronizer->setURI($this->path);

    // Primeiramente preparamos os parâmetros de nossa requisição
    $this->synchronizer->prepareParameters();

    // Acrescentamos um filtro para as informações de tipos de veículos
    $this->synchronizer->setFilterParameter($this->getVehicleTypes());

    // Seta uma função para lidar com os dados recebidos
    $this->synchronizer
      ->setOnDataProcessing([$this, 'onDataProcessing'])
    ;

    // Executa o sincronismo dos clientes com o sistema Fipe
    $this->synchronizer->synchronize();
  }

  /**
   * A função responsável por processar os dados recebidos.
   *
   * @param array $brandData
   *   Os dados obtidos do servidor Fipe
   * @param array $vehicleType
   *   Os dados do tipo de veículo para o qual as marcas foram
   * requisitadas
   */
  public function onDataProcessing(array $brandData,
    array $vehicleType): void
  {
    // Executa a stored procedure de sincronismo dos dados
    $vehicleBrand = new VehicleBrand();

    $vehicleBrand->contractorid  = $this->contractor->id;
    $vehicleBrand->vehicletypeid = $vehicleType['id'];
    $vehicleBrand->fipeid        = intval($brandData['codigo']);
    $vehicleBrand->fipename      = $this->normalizeString($brandData['nome'], true);
    $vehicleBrand->sync();
  }
}
