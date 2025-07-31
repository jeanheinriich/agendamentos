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
 * As requisições ao serviço de obtenção dos dados de histórico de
 * posições através da API do sistema STC.
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

use App\Models\STC\Customer;
use App\Models\STC\Device;
use App\Models\STC\DeviceModel;
use App\Models\STC\Position;
use App\Models\STC\Vehicle;
use Core\HTTP\Filters\DataFilter;
use Core\HTTP\Filters\DataFilterIterator;
use Core\HTTP\Service;
use Core\OpenStreetMap\OpenStreetMap;
use Exception;
use Illuminate\Database\QueryException;
use LengthException;

class PositionService
  extends STCService
  implements Service
{
  /**
   * O caminho para nosso serviço.
   *
   * @var string
   */
  protected $path = 'ws/getVehiclePositionsByLimit500';

  /**
   * A ID do último cliente sendo processado.
   *
   * @var int
   */
  protected $lastClientID = 0;

  /**
   * A placa do último veículo sendo processado.
   *
   * @var int
   */
  protected $lastPlate = '';

  /**
   * A ID da última posição do veículo armazenada localmente
   *
   * @var int
   */
  protected $lastPosition = 0;

  /**
   * A ID do último equipamento rastreador analisado
   *
   * @var int
   */
  protected $lastDeviceID = 0;

  /**
   * O acesso à API do Open Street Map.
   *
   * @var OpenStreetMap
   */
  protected $openstreetmap;

  /**
   * Recupera as informações de veículos ordenada por cliente para
   * permitir requisitar as informações de histórico de posicionamento.
   *
   * @return DataFilter
   *   O filtro que permite iterar sobre os dados de veículos de cada
   *   cliente
   */
  protected function getVehiclesPerCustomerFilter(): DataFilter
  {
    try {
      $vehicles = Customer::join("stc.vehicles", "vehicles.clientid",
        '=', "customers.clientid")
        ->where("customers.contractorid", '=', $this->contractor->id)
        ->where("customers.status", "true")
        ->where("customers.getpositions", "true")
        ->where("vehicles.status", "true")
        ->orderBy("customers.name", "desc")
        ->orderBy("vehicles.plate", "desc")
        ->get([
            'customers.clientid',
            'customers.name AS customername',
            'customers.login',
            'customers.password',
            'vehicles.plate'
          ])
        ->toArray()
      ;
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "veículos por cliente. Erro interno no banco de dados: "
        . "{error}.",
        [ 'error'  => $exception->getMessage() ]
      );

      $vehicles = null;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "veículos por cliente. Erro interno: {error}.",
        [ 'error'  => $exception->getMessage() ]
      );

      $vehicles = null;
    }

    if (is_array($vehicles)) {
      if (count($vehicles) === 0) {
        throw new LengthException("Não temos veículos cujos dados de "
          . "posicionamento precisem ser obtidos.", 1)
        ;
      }
    } else {
      throw new LengthException("Não temos veículos cujos dados de "
        . "posicionamento precisem ser obtidos.", 1)
      ;
    }

    // Cria um novo filtro para permitir a filtragem dos dados em cada
    // requisição
    $filterParameters = [
      'plate' => 'plate'
    ];
    $vehiclesPerCustomerFilter = new DataFilterIterator($filterParameters,
      $vehicles)
    ;

    return $vehiclesPerCustomerFilter;
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

    // Criamos um filtro para as informações de veículos ordenada por
    // cliente
    try {
      $filterPerVehicle = $this->getVehiclesPerCustomerFilter();

      $this->synchronizer->setFilterParameter($filterPerVehicle);

      // Definimos que devemos ter um tempo de atraso entre requisições
      $this->synchronizer->setDelay(21);

      // Definimos que as requisições devem ser repetidas sempre que os
      // dados contiverem 500 posições, que é o indicativo de que
      // podemos ter ainda dados restantes, já que cada requisição é
      // limitada nesta quantidade
      $this->synchronizer->setPageSize(500);
      $this->synchronizer->setRepeatRequest(true);

      // Seta uma função para preparar os parâmetros de requisição
      $this->synchronizer->setBeforeRequest([$this, 'beforeRequest']);

      // Seta uma função para lidar com os dados recebidos
      $this->synchronizer->setOnDataProcessing([$this, 'onDataProcessing']);

      // Seta uma função para o pós-processamento
      $this->synchronizer->setAfterProcess([$this, 'afterProcess']);

      // Executa o sincronismo dos clientes com o sistema STC
      $this->synchronizer->synchronize();
    }
    catch(LengthException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "veículos por cliente. {error}.",
        [ 'error'  => $exception->getMessage() ]
      );
    }
  }

  /**
   * A função responsável por preparar os parâmetros de requisição antes
   * da requisição.
   *
   * @param array $parameters
   *   Os parâmetros pré-configurados
   * @param array $vehicle
   *   Os dados do veículo para o qual iremos obter os dados de
   * posicionamento
   *
   * @return array
   *   Os parâmetros ajustados
   */
  public function beforeRequest(array $parameters, array $vehicle): array
  {
    // Verifica se ocorreu mudança do cliente
    if ($this->lastClientID !== intval($vehicle['clientid'])) {
      // Modificamos as informações do cliente nos dados da solicitação
      // enviados à STC
      $parameters['user'] = strtolower($vehicle['login']);
      $parameters['pass'] = md5($vehicle['password']);

      // Armazenamos o ID do cliente atual
      $this->lastClientID = intval($vehicle['clientid']);

      // Registra que estamos recuperando posicionamentos para um
      // novo cliente
      $this->info("Obtendo posicionamentos para os veículos do "
        . "cliente {customer}.",
        [ 'customer' => $vehicle['customername'] ]);
    }

    if ($this->lastPlate !== $vehicle['plate']) {
      // Armazena a placa do novo veículo
      $this->lastPlate = $vehicle['plate'];

      // Obtém a última posição já recuperada deste veículo
      $this->lastPosition = Vehicle::where("contractorid", '=',
        $this->contractor->id)
        ->where("plate", '=', $vehicle['plate'])
        ->first()
        ->lastpositionid
      ;

      if ($this->lastPosition === 0) {
        // Ainda não temos nenhuma posição para este veículo, então
        // verifica se já temos dados de posicionamentos armazenados para
        // este veículo localmente
        $this->lastPosition = Position::where("contractorid", '=',
          $this->contractor->id)
          ->where("plate", '=', $vehicle['plate'])
          ->max('positionid')
        ;
      }
    }

    if ($this->lastPosition > 0) {
      // Registra que estamos recuperando posicionamentos à partir do
      // último registro armazenado
      $this->debug("Obtendo posicionamentos para o veículo "
        . "{plate} à partir da posição ID {lastPosition}.",
        [ 'plate' => $vehicle['plate'],
          'lastPosition' => $this->lastPosition ]);

      $parameters['positionId'] = strval($this->lastPosition);
    } else {
      // Registra que estamos recuperando os últimos posicionamentos
      $this->debug("Obtendo os últimos posicionamentos para o "
        . "veículo {plate}.",
        [ 'plate' => $vehicle['plate'] ]);
      unset($parameters['positionId']);
    }

    $context = $parameters;

    return [
      $parameters, $context
    ];
  }

  /**
   * A função responsável por processar os dados recebidos.
   *
   * @param array $positionData
   *   Os dados obtidos do servidor STC
   */
  public function onDataProcessing(array $positionData): void
  {
    // Atualiza o modelo do equipamento de rastreamento. Para isto,
    // recupera as informações do equipamento que estão disponíveis
    $deviceid        = intval($positionData['deviceId']);
    $deviceModelName = $this->normalizeString($positionData['deviceModel'], true);

    if ($this->lastDeviceID !== $deviceid) {
      $this->lastDeviceID = $deviceid;

      // Como temos o ID do equipamento, através dele fazemos a busca do
      // fabricante
      $device = Device::where("contractorid", '=', $this->contractor->id)
        ->where("deviceid", '=', $deviceid)
        ->get([
            'manufactureid',
            'devicemodelid'
          ])
        ->first()
      ;
      if ($device) {
        if (!$device->devicemodelid) {
          // Encontrou o dispositivo, então recupera o fabricante
          $manufactureID = $device->manufactureid;

          // Verifica se este modelo de equipamento está cadastrado
          $deviceModel = DeviceModel::where("contractorid", '=', $this->contractor->id)
            ->where("manufactureid", '=', $manufactureID)
            ->whereRaw("public.unaccented(name) ILIKE public.unaccented('{$deviceModelName}')")
            ->first()
          ;

          if ($deviceModel) {
            // Recuperamos o modelo de rastreamento
            $deviceModelID = $deviceModel->devicemodelid;
          } else {
            // Inserimos o modelo de rastreamento
            $deviceModel = new DeviceModel();
            $deviceModel->contractorid  = $this->contractor->id;
            $deviceModel->manufactureid = $manufactureID;
            $deviceModel->name          = $deviceModelName;
            $deviceModel->save();
            $deviceModelID = $deviceModel->devicemodelid;
          }

          // Agora atualizamos o modelo do equipamento de rastreamento
          $device = Device::where("contractorid", '=', $this->contractor->id)
            ->where("deviceid", '=', $deviceid)
            ->firstOrFail();
          // Atualiza os dados
          $device->devicemodelid = $deviceModelID;
          $device->save();
        }
      }
    }

    // Nós recebemos todas as informações de posicionamento, porém só
    // usamos àquelas que possui a informação de rs232. Desta forma,
    // todas as demais serão ignoradas e não serão mais inseridas
    $rs232 = $this->normalizeString($positionData['rs232'], true);

    // O ID do motorista
    $driverID = intval($positionData['driverId']);

    if (empty($rs232)) {
      // Não tem dados de RS232
      usleep(5 * 1000);
    } else {
      // Analisa o comando recebido e testa sua compatibilidade com o
      // padrão SGBRAS, pois é o único em uso no momento.
      //
      // @TODO: Adaptar esta análise para outros sistemas quando
      //        necessário
      $rs232 = $this->normalizeString($positionData['rs232'], true);
      $amount = substr_count($rs232, '|');
      $validCommand = true;
      if (($amount == 2) || ($amount == 3)) {
        // Temos a quantidade de separadores corretos, analisa cada parte
        $partsOfCommand = explode('|', $rs232);

        if (strtoupper($partsOfCommand[0]) !== 'SGBRAS') {
          // Não é um comando da SGBRAS, então ignora
          $validCommand = false;
        }

        // Introduzido apenas para corrigir problema da STC com o ID do
        // motorista zerado
        if ($driverID == 0) {
          // Temos a quantidade de separadores corretos, então
          // extraímos do comando o ID do motorista
          $driverID = intval($partsOfCommand[2]);
        }
      } else {
        // Não é um comando
        $validCommand = false;
      }

      if ($validCommand) {
        // O registro possui todos os dados, exceto o endereço do local.

        // Solicitamos o endereço usando o serviço de geocodificação
        // reversa
        $latitude  = $positionData['latitude'];
        $longitude = $positionData['longitude'];
        $address   = $this->geocode->address($latitude, $longitude);

        // Não utilizamos o comando de inserção do model, já que o ID não é
        // retornado pelo fato de estarmos utilizando o particionamento dos
        // dados nesta tabela, e isto ocasiona um erro de execução. Desta
        // forma, montamos um comando de inserção manualmente onde colocamos
        // os dados a serem adicionados.
        $position = new Position();

        $position->deviceid       = intval($positionData['deviceId']);
        $position->plate          = strtoupper(trim($positionData['plate']));
        $position->eventdate      = trim($positionData['date']);
        $position->positionid     = intval($positionData['positionId']);
        $position->ignitionstatus = strtoupper(trim($positionData['ignition']))==="ON"?'true':'false';
        $position->odometer       = intval($positionData['odometer']);
        $position->horimeter      = intval($positionData['horimeter']);
        $position->address        = $this->normalizeString($address, true);
        $position->direction      = trim($positionData['direction']);
        $position->speed          = intval($positionData['speed']);
        $position->batteryvoltage = trim($positionData['mainBattery']);
        $position->latitude       = $latitude;
        $position->longitude      = $longitude;
        $position->driverid       = $driverID;
        $position->drivername     = substr(
          $this->normalizeString($positionData['driverName'], true), 100)
        ;
        // Estes campos não estão sendo tratados, e precisamo ser analisados
        // $position->input1         = trim($positionData['input1'])
        // $position->input2         = trim($positionData['input2'])
        // $position->output1        = trim($positionData['output1'])
        // $position->output2        = trim($positionData['output1'])
        $position->rs232          = $rs232;
        $position->contractorid   = $this->contractor->id;
        $position->insert();
      }

      // Aguarda .1 segundo entre requisições
      usleep(100 * 1000);
    }

    // Atualiza a última posição deste veículo
    if ($this->lastPosition < intval($positionData['positionId'])) {
      $this->lastPosition = intval($positionData['positionId']);
    }
  }

  /**
   * A função responsável pelo pós-processamento.
   *
   * @param array $parameters
   *   Os parâmetros pré-configurados
   * @param array $vehicle
   *   Os dados do veículo para o qual obtivemos os dados de
   * posicionamento
   */
  public function afterProcess(array $parameters, array $vehicle): void
  {
    // Atualiza a última posição já recuperada deste veículo
    $vehicle = Vehicle::where("contractorid", '=', $this->contractor->id)
      ->where("plate", '=', $vehicle['plate'])
      ->update([
          'lastpositionid' => $this->lastPosition
        ])
    ;
  }
}
