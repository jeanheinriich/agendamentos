<?php
/*
 * This file is part of STC Integration Library.
 *
 * (c) Emerson Cavalcanti
 *
 * LICENSE: The MIT License (MIT)
 *
 * Permission is hereby granted, free of charge, to any person obtaining
 * a copy of this softw
 * are and associated documentation files (the
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
 * As requisições ao serviço de obtenção dos dados de modelos de
 * veículos através da API do sistema STC.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

/**
 * API STC
 *
 * http://ap1.stc.srv.br/docs/
 *
 * Copyright (c) 2017 - STC Tecnologia <www.stctecnologia.com.br>
 */

namespace App\Providers\STC\Services;

use App\Models\STC\VehicleBrand;
use App\Models\STC\VehicleModel;
use Core\HTTP\Filters\DataFilter;
use Core\HTTP\Filters\DataFilterIterator;
use Core\HTTP\Service;
use Exception;
use Illuminate\Database\QueryException;

class VehicleModelService
  extends STCService
  implements Service
{
  /**
   * O caminho para nosso serviço.
   *
   * @var string
   */
  protected $path = 'ws/vehicle/listmodel';

  /**
   * Recupera as informações de fabricantes de veículos para permitir
   * requisitar as informações de modelos.
   *
   * @return DataFilter
   *   O filtro que permite iterar sobre os fabricantes de veículos
   */
  protected function getBrandsFilter(): DataFilter
  {
    try {
      $brands = VehicleBrand::where("contractorid", '=',
        $this->contractor->id)
        ->get()
        ->toArray()
      ;
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "marcas de veículos. Erro interno no banco de dados: "
        . "{error}.",
        [ 'error'  => $exception->getMessage() ]);

      $brands = [];
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "marcas de veículos. Erro interno: {error}.",
        [ 'error'  => $exception->getMessage() ]);

      $brands = [];
    }

    // Cria um novo filtro para permitir a filtragem dos dados em cada
    // requisição
    $filterParameters = [
      'modelid' => 'vehiclebrandid'
    ];
    $brandsFilter = new DataFilterIterator($filterParameters, $brands);

    return $brandsFilter;
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

    // Criamos um filtro para as informações de fabricantes
    $filterPerBrand = $this->getBrandsFilter();
    $this->synchronizer->setFilterParameter($filterPerBrand);

    // Seta uma função para lidar com os dados recebidos
    $this->synchronizer->setOnDataProcessing([$this, 'onDataProcessing']);

    // Executa o sincronismo dos modelos de veículos com o sistema STC
    $this->synchronizer->synchronize();
  }

  /**
   * A função responsável por processar os dados recebidos.
   *
   * @param array $vehicleModelData
   *   Os dados obtidos do servidor STC
   */
  public function onDataProcessing(array $vehicleModelData,
    array $brand): void
  {
    // Determina o ID do fabricante
    $brandID = intval($brand['vehiclebrandid']);

    // Verifica se este modelo de veículo não está cadastrado
    if (VehicleModel::where("contractorid", '=', $this->contractor->id)
          ->where("vehiclebrandid", '=', $brandID)
          ->where("vehiclemodelid", '=', intval($vehicleModelData['id']))
          ->count() === 0) {
      $vehicleModel = new VehicleModel();

      $vehicleModel->vehiclemodelid = intval($vehicleModelData['id']);
      $vehicleModel->vehiclebrandid = $brandID;
      $vehicleModel->name           = $this->normalizeString($vehicleModelData['name']);
      $vehicleModel->contractorid   = $this->contractor->id;
      $vehicleModel->save();
    } else {
      // Precisa atualizar apenas, então recupera o modelo
      // de veículo
      $vehicleModel = VehicleModel::where("contractorid", '=', $this->contractor->id)
        ->where("vehiclebrandid", '=', $brandID)
        ->where("vehiclemodelid", '=', intval($vehicleModelData['id']))
        ->firstOrFail()
      ;

      // Atualiza os dados
      $vehicleModel->name = $this->normalizeString($vehicleModelData['name']);
      $vehicleModel->save();
    }
  }
}
