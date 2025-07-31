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
 * As requisições ao serviço de obtenção dos dados de modelos de
 * veículos através da API do sistema Fipe.
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

/* A resposta à requisição é uma matriz com dois valores:
 *   modelos: os modelos desta marca
 *     Cada modelo contém o seguinte formato:
 *     array(2) {
 *       ["nome"]=> O nome do modelo (string)
 *       ["codigo"]=> O código do modelo no sistema Fipe (int)
 *     }
 *   anos: os anos em que esta marca fabricou estes modelos
 *     Cada ano contém o seguinte formato:
 *     array(2) {
 *       ["nome"]=> O nome do ano (ex: "1991 Gasolina") (string)
 *       ["codigo"]=> O código do ano no sistema Fipe
 *                    (ex: "1991-1") (string)
 *     }
 *     O código do ano possui o seguinte formato:
 *       "<ano>-<código combustível", onde
 *       o ano é o ano de fabricação do carro e o código do
 *       combustível é um valor que pode ter:
 *         * 1: Gasolina
 *         * 2: Etanol
 *         * 3: Diesel
 *
 * Internamente não usamos os anos e construímos o código do ano à
 * partir das informações que possuímos.
 *
 * @TODO: Analisar a possibilidade de acrescentar o período de anos
 * em que uma marca fabricou modelos e os combustíveis para nos
 * permitir ajustar os dados na interface e evitar valores incorretos
 * no preenchimento dos dados de um veículo
 */

namespace App\Providers\Fipe\Services;

use App\Models\VehicleModel;
use App\Models\VehicleTypePerBrand;
use Core\HTTP\Filters\DataFilter;
use Core\HTTP\Filters\DataFilterIterator;
use Core\HTTP\Service;
use Exception;
use Illuminate\Database\QueryException;

class VehicleModelService
  extends FipeService
  implements Service
{
  /**
   * O caminho para nosso serviço.
   *
   * @var string
   */
  protected $path = '{vehicleType}/marcas/{brandCode}/modelos';

  /**
   * Recupera as informações de tipos de veículos fabricados por marca
   * para permitir requisitar as informações do sistema Fipe.
   *
   * @return DataFilter
   *   O filtro que permite iterar sobre os tipos de veículos por marca
   */
  protected function getVehicleTypesPerBrand(): DataFilter
  {
    try {
      // Recupera as informações dos tipos de veículos por marcas
      // cadastrados para os quais temos a informação do sistema fipe
      $vehicleTypesPerBrand = VehicleTypePerBrand::join(
            'erp.vehiclebrands', 'vehicletypesperbrands.vehiclebrandid',
            '=', 'vehiclebrands.vehiclebrandid'
          )
        ->join('erp.vehicletypes',
            'vehicletypesperbrands.vehicletypeid', '=',
            'vehicletypes.vehicletypeid'
          )
        ->where('vehicletypesperbrands.contractorid', '=',
            $this->contractor->id
          )
        ->where('vehicletypesperbrands.fipeid', '>', 0)
        ->where('vehicletypes.fipename', '<>', '')
        ->orderBy('vehicletypesperbrands.vehicletypeid')
        ->orderBy('vehicletypesperbrands.vehiclebrandid')
        ->orderBy('vehicletypesperbrands.fipeid')
        ->get([
            'vehicletypesperbrands.vehicletypeperbrandid',
            'vehicletypesperbrands.vehicletypeid',
            'vehicletypes.name AS vehicletypename',
            'vehicletypes.fipename AS vehicletype',
            'vehiclebrands.name AS vehiclebrandname',
            'vehicletypesperbrands.fipeid AS brandcode'
          ])
        ->toArray()
      ;
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "tipos de veículos por marca. Erro interno no banco de "
        . "dados: {error}.",
        [ 'error'  => $exception->getMessage() ]
      );

      $vehicleTypesPerBrand = [];
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "tipos de veículos por marca. Erro interno: {error}.",
        [ 'error'  => $exception->getMessage() ]
      );

      $vehicleTypesPerBrand = [];
    }

    // Cria um novo filtro para permitir a filtragem dos dados em cada
    // requisição. Aqui apontamos o nome do parâmetro e em qual campo
    // de nossos dados ele irá obter o respectivo valor
    $filterParameters = [
      'vehicleType' => 'vehicletype',
      'brandCode' => 'brandcode'
    ];
    $typePerBrandFilter = new DataFilterIterator($filterParameters,
      $vehicleTypesPerBrand);

    return $typePerBrandFilter;
  }

  /**
   * O método responsável por executar as requisições ao serviço,
   * sincronizando os dados.
   */
  public function synchronize(): void
  {
    // Ajusta os parâmetros para sincronismo
    $this->synchronizer->setURI($this->path);
    $this->synchronizer->setFieldData('modelos');

    // Primeiramente preparamos os parâmetros de nossa requisição
    $this->synchronizer->prepareParameters();

    // Acrescentamos um filtro para as informações de tipos de veículos
    // por marca
    $this->synchronizer
      ->setFilterParameter($this->getVehicleTypesPerBrand())
    ;

    // Seta uma função para lidar com os dados recebidos
    $this->synchronizer->setOnDataProcessing([$this, 'onDataProcessing']);

    // Executa o sincronismo dos clientes com o sistema Fipe
    $this->synchronizer->synchronize();
  }

  /**
   * A função responsável por processar os dados recebidos.
   *
   * @param array $modelData
   *   Os dados obtidos do servidor Fipe
   * @param array $vehicleBrand
   *   Os dados da marca do veículo
   */
  public function onDataProcessing(array $modelData,
    array $vehicleBrand): void
  {
    // Executa a stored procedure de sincronismo dos dados
    $vehicleModel = new VehicleModel();

    $vehicleModel->contractorid     = $this->contractor->id;
    $vehicleModel->vehicletypeid    = $vehicleBrand['vehicletypeid'];
    $vehicleModel->vehiclebrandcode = $vehicleBrand['brandcode'];
    $vehicleModel->name             = $this->normalizeString($modelData['nome'], true);
    $vehicleModel->fipeid           = trim($modelData['codigo']);
    $vehicleModel->sync();
  }
}
