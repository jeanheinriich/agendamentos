<?php
/*
 * This file is part of STC Integration Library.
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
 * As requisições ao serviço de obtenção dos dados de cidades através da
 * API do sistema STC.
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

use App\Models\State;
use App\Models\STC\City;
use Core\HTTP\Filters\DataFilter;
use Core\HTTP\Filters\DataFilterIterator;
use Core\HTTP\Service;
use Exception;
use Illuminate\Database\QueryException;

class CityService
  extends STCService
  implements Service
{
  /**
   * O caminho para nosso serviço.
   *
   * @var string
   */
  protected $path = 'ws/client/getcities';

  /**
   * Recupera as informações de Unidades da Federação (Estados) para
   * permitir requisitar as informações de cidades.
   *
   * @return DataFilter
   *   O filtro que permite iterar sobre as UFs
   */
  protected function getStatesFilter(): DataFilter
  {
    try {
      $states = State::orderBy('state')
                     ->get([
                         'state AS uf',
                         'name'
                       ])
                     ->toArray()
      ;
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "UFs. Erro interno no banco de dados: {error}.",
        [ 'error'  => $exception->getMessage() ]
      );

      $states = [];
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "UFs. Erro interno: {error}.",
        [ 'error'  => $exception->getMessage() ]
      );

      $states = [];
    }

    // Cria um novo filtro para permitir a filtragem dos dados em cada
    // requisição
    $filterParameters = [
      'state' => 'uf'
    ];
    $statesFilter = new DataFilterIterator($filterParameters, $states);

    return $statesFilter;
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

    // Criamos um filtro para as informações de estados (UFs)
    $filterPerState = $this->getStatesFilter();
    $this->synchronizer->setFilterParameter($filterPerState);

    // Seta uma função para lidar com os dados recebidos
    $this->synchronizer->setOnDataProcessing([$this, 'onDataProcessing']);

    // Executa o sincronismo das cidades com o sistema STC
    $this->synchronizer->synchronize();
  }

  /**
   * A função responsável por processar os dados recebidos.
   *
   * @param array $cityData
   *   Os dados obtidos do servidor STC
   */
  public function onDataProcessing(array $cityData, array $state): void
  {
    // Primeiro, verifica se esta cidade não está cadastrada
    if (City::where("contractorid", '=', $this->contractor->id)
            ->where("cityid", '=', intval($cityData['id']))
            ->count() === 0) {
      $city = new City();

      $city->cityid       = intval($cityData['id']);
      $city->name         = $this->normalizeString($cityData['city']);
      $city->state        = $state['uf'];
      $city->contractorid = $this->contractor->id;
      $city->save();
    } else {
      // Precisa atualizar apenas, então recupera as informações da
      // cidade
      $city = City::where("contractorid", '=', $this->contractor->id)
                  ->where("cityid", '=', intval($cityData['id']))
                  ->firstOrFail();

      // Atualiza os dados
      $city->name = $this->normalizeString($cityData['city']);
      $city->save();
    }
  }
}
