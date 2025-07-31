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
 * As requisições ao serviço de obtenção dos dados de iButtons
 * cadastrados por dispositivo de rastreamento através da API do sistema
 * STC.
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
use App\Models\STC\Driver;
use Core\HTTP\Filters\DataFilter;
use Core\HTTP\Filters\DataFilterIterator;
use Core\HTTP\Service;
use Exception;
use Illuminate\Database\QueryException;

class IButtonPerDeviceService
  extends STCService
  implements Service
{
  /**
   * O caminho para nosso serviço.
   *
   * @var string
   */
  protected $path = 'ws/device/sgbras/listdriveridenabled';

  /**
   * O caminho para nosso serviço de requisição dos IDs de motoristas
   * cadastrados no equipamento.
   *
   * @var string
   */
  protected $requestPath = 'ws/device/sgbras/getalldriverid';

  /**
   * O caminho para nosso serviço de inserção de IDs de motoristas.
   *
   * @var string
   */
  protected $insertPath = 'ws/device/sgbras/adddriverid';

  /**
   * O caminho para nosso serviço de remoção de IDs de motoristas.
   *
   * @var string
   */
  protected $deletePath = 'ws/device/sgbras/deletedriverid';

  /**
   * O caminho para nosso serviço de remoção de todos os IDs de
   * motoristas (limpar a memória).
   *
   * @var string
   */
  protected $deleteAllPath = 'ws/device/sgbras/deletealldriverid';

  /**
   * O caminho para nosso serviço de verificação do complemento de um
   * comando.
   *
   * @var string
   */
  protected $checkPath = 'ws/device/sgbras/getcommandpendent';

  /**
   * A matriz que armazena as informações de ID's de motoristas por
   * dispositivo de rastreamento.
   *
   * @var array
   */
  protected $driversPerDevice = [];

  /**
   * A matriz que armazena as informações de ID's de motoristas por
   * cliente.
   *
   * @var array
   */
  protected $driversPerClient = [];

  /**
   * Flag que habilita o envio de comandos de modificação para o teclado
   * acoplado ao rastreador.
   *
   * @var boolean
   */
  protected $sendModificationCommands = true;

  /**
   * A ID do dispositivo para o qual enviaremos os comandos. (0 = todos)
   *
   * @var integer
   */
  protected $deviceID = 0;

  /**
   * Recupera as informações de IDs de motoristas para um determinado
   * cliente.
   *
   * @param int $clientID
   *   A ID do cliente desejada
   *
   * @return array
   *   A matriz com as informações de motoristas
   */
  protected function getDriversOnCustomer(int $clientID): array
  {
    try {
      $this->debug("Solicitando as informações de motoristas para "
        . "o cliente {clientID}",
        [ 'clientID'  => $clientID ]
      );

      $this->DB->reconnect();
      $drivers = Driver::where("drivers.contractorid", '=', $this->contractor->id)
                       ->where("drivers.clientid", '=', $clientID)
                       ->orderBy('drivers.driverid')
                       ->get([
                             'drivers.driverid',
                             ])
                       ->toArray()
      ;
      $this->debug("Solicitada as informações de motoristas para "
        . "o cliente {clientID} e retornado {count}",
        [ 'clientID'  => $clientID,
          'count' => count($drivers) ]
      );
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "motoristas. Erro interno no banco de dados: {error}.",
        [ 'error'  => $exception->getMessage() ]
      );

      $drivers = null;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "motoristas. Erro interno: {error}.",
        [ 'error'  => $exception->getMessage() ]
      );

      $drivers = null;
    }

    if ($drivers) {
      return array_column($drivers, 'driverid');
    }

    return [];
  }

  /**
   * Recupera as informações de equipamentos de rastreamento que estejam
   * instalados em veículos de empresas para as quais estamos obtendo
   * dados de posicionamento e para os quais precisamos analisar as
   * informações de iButtons cadastrados.
   *
   * @return DataFilter
   *   O filtro que permite iterar sobre os dados de dispositivos de
   *   cada cliente
   */
  protected function getDeviceFilter(): DataFilter
  {
    try {
      $this->DB->reconnect();
      $deviceID = $this->deviceID;
      $devices = Customer::join("stc.vehicles", function($join) {
            $join->on("customers.clientid", '=', "vehicles.clientid");
            $join->on("customers.contractorid", '=', "vehicles.contractorid");
          })
        ->join("stc.devices", function($join) {
            $join->on("vehicles.deviceid", '=', "devices.deviceid");
            $join->on("vehicles.contractorid", '=', "devices.contractorid");
          })
        ->join("stc.devicemodels", function($join) {
            $join->on("devices.devicemodelid", '=', "devicemodels.devicemodelid");
            $join->on("devices.contractorid", '=', "devicemodels.contractorid");
          })
        ->where("customers.contractorid", '=', $this->contractor->id)
        ->where("customers.status", "true")
        ->where("customers.getpositions", "true")
        ->where("vehicles.status", "true")
        ->when($deviceID > 0, function($query) use ($deviceID) {
            return $query->where("devices.deviceid", '=', $deviceID);
          })
        ->where("devicemodels.abletokeyboard", "true")
        ->orderBy("customers.name", "desc")
        ->orderBy("vehicles.plate", "desc")
        ->get([
            'customers.clientid',
            'customers.name AS customername',
            'vehicles.plate',
            'vehicles.deviceid'
          ])
        ->toArray()
      ;

      //foreach ($devices as $key => $value) {
      //  echo $value['plate'] . ": [" . $value['clientid'] . "] ".  $value['deviceid'] . "\n";
      //}
      
      // Forçamos a conversão do ID do dispositivo para o padrão
      foreach ($devices as $count => $row) {
        $devices[$count]['deviceid'] = $this->formatDeviceID($row['deviceid']);
      }
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "dispositivos instalados em veículos. Erro interno no banco "
        . "de dados: {error}.",
        [ 'error'  => $exception->getMessage() ]
      );

      $devices = [];
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "dispositivos instalados em veículos. Erro interno: {error}.",
        [ 'error'  => $exception->getMessage() ]
      );

      $devices = [];
    }

    // Cria um novo filtro para permitir a filtragem dos dados em cada
    // requisição
    $filterParameters = [
      'deviceId' => 'deviceid'
    ];
    $devicesFilter = new DataFilterIterator($filterParameters, $devices);

    return $devicesFilter;
  }

  /**
   * Formata o ID do dispositivo para o padrão necessário pelo sistema de
   * rastreamento.
   *
   * @param int $deviceID
   *   O ID do dispositivo
   *
   * @return string
   *   O ID do dispositivo formatado
   */
  protected function formatDeviceID(int $deviceID): string
  {
    if (strlen(strval($deviceID)) > 6) {
      $result = sprintf("%09d", $deviceID);
    } else {
      $result = sprintf("%06d", $deviceID);
    }

    return $result;
  }

  /**
   * Sinaliza que não deve enviar comandos para os dispositivos de
   * inserção e/ou remoção de ID's de motoristas.
   *
   * @return void
   */
  public function onlyCheck()
  {
    $this->sendModificationCommands = false;
  }

  /**
   * Informa o ID do dispositivo para o qual enviaremos os comandos
   *
   * @param integer $deviceID
   *   O ID do dispositivo para o qual enviaremos os comandos
   * 
   * @return void
   */
  public function onlyDevice(int $deviceID)
  {
    $this->deviceID = $deviceID;
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

    // Criamos um filtro para as informações de dispositivos de
    // rastreamento (deviceID)
    $filterPerDeviceID = $this->getDeviceFilter();
    $this->synchronizer->setFilterParameter($filterPerDeviceID);

    // Seta uma função antes da requisição
    $this->synchronizer->setBeforeRequest([$this, 'beforeRequest']);

    // Seta uma função para lidar com os dados recebidos
    $this->synchronizer->setOnDataProcessing([$this, 'onDataProcessing']);

    // Seta uma função para o pós-processamento
    $this->synchronizer->setAfterProcess([$this, 'afterProcess']);

    // Inicializa nossa matriz que armazena os dados de iButton por
    // dispositivo
    $this->driversPerDevice = [];

    // Inicializa nossa matriz com os dados de motoristas por cliente
    $this->driversPerClient = [];

    // Executa o sincronismo das informações de iButtons cadastradas
    // em cada equipamento de rastreamento com o sistema STC
    $this->synchronizer->synchronize();
  }

  /**
   * A função responsável por pré-processar os dados. Ela faz uma
   * requisição para o dispositivo para requisitar todas as matrículas.
   *
   * @param array $parameters
   *   Os parâmetros
   * @param array $device
   *   Os dados do dispositivo
   *
   * @return array
   *   Uma matriz contendo os parâmetros e os valores de contexto (os
   *   que serão usados na requisição)
   */
  public function beforeRequest(
    array $parameters,
    array $device
  ): array
  {
    $this->synchronizer->updateProgress("Removendo os ID's dos "
      . "motoristas existentes..."
    );

    // Recupera os dados
    $deviceID = $device['deviceid'];

    // Faz uma pré-requisição dos parâmetros ao dispositivo
    list($response, $message) = $this->sendCommand($this->deleteAllPath,
      $deviceID)
    ;

    if ($response) {
      $this->debug("Solicitado remoção de todas as ID's dos motoristas "
        . "registradas no equipamento {deviceID}",
        [ 'deviceID'  => $deviceID ]
      );
    } else {
      // Registra o erro
      $this->error("Não foi possível solicitar remoção de todas as IDs "
        . "dos motoristas no equipamento ID {deviceID}. {error}.",
        [ 'deviceID' => $deviceID,
          'error'  => $message ]
      );
    }

    // Aguarda um tempo entre esta requisição e a remoção dos dados do
    // equipamento. Este tempo é de normalmente 1 min.
    $this->synchronizer->waitingTimeBetweenRequisitions(1 * 60);

    $this->synchronizer->updateProgress("Requisitando ID's dos "
      . "motoristas..."
    );

    // Faz uma pré-requisição dos parâmetros ao dispositivo
    list($response, $message) = $this->sendCommand($this->requestPath,
      $deviceID)
    ;

    if ($response) {
      $this->debug("Solicitado todas as ID's dos motoristas "
        . "registradas no equipamento {deviceID}",
        [ 'deviceID'  => $deviceID ]
      );
    } else {
      // Registra o erro
      $this->error("Não foi possível solicitar todas as IDs dos "
        . "motoristas no equipamento ID {deviceID}. {error}.",
        [ 'deviceID' => $deviceID,
          'error'  => $message ]
      );
    }

    // Aguarda um tempo entre esta requisição e a leitura dos dados de
    // equipamentos. Este tempo é de normalmente 4 min (em média). Estou
    // acrescentando 2 minutos de tolerância para lidar com comunicações
    // mais lentas
    $this->synchronizer->waitingTimeBetweenRequisitions(6 * 60);

    $context = $parameters;

    return [
      $parameters, $context
    ];
  }

  /**
   * A função responsável por processar os dados recebidos.
   *
   * @param array $driverData
   *   Os dados obtidos do servidor STC
   * @param array $device
   *   Os dados do dispositivo
   *
   * @return void
   */
  public function onDataProcessing(
    array $driverData,
    array $device
  ): void
  {
    // Recupera os dados
    $driverID = intval($driverData['ibuttonId']);
    $deviceID = $device['deviceid'];
    $plate    = $device['plate'];
    $clientID = $device['clientid'];
    $memoryID = $driverData['position'];

    // Usamos uma estrutura em memória que armazena os ID's de
    // motoristas por equipamento. Usaremos no final para gerar uma
    // lista de ID's de motoristas que precisam ser adicionados à este
    // equipamento e àqueles que precisam ser removidos para então
    // executar os comandos responsáveis por atualizar estas informações

    // Verifica se o dispositivo atual já foi adicionado
    if (!array_key_exists($deviceID, $this->driversPerDevice)) {
      // Adicionamos este dispositivo e criamos uma lista vazia de
      // IDs de motoristas armazenados
      $this->driversPerDevice[$deviceID] = (object) [
        'plate' => $plate,
        'clientID' => $clientID,
        'drivers' => []
      ];
    }

    // Verifica se o ID do motorista já foi adicionado a este dispositivo
    if (!array_key_exists($memoryID,
          $this->driversPerDevice[$deviceID]->drivers)) {
      // Armazenamos na posição de memória a informação do iButton
      $this->driversPerDevice[$deviceID]->drivers[$memoryID] = $driverID;
    }

    // $driverData contém:
    //   deviceId: o ID do dispositivo solicitado
    //   position: a posição de memória onde está armazenado
    //   ibuttonId: o ID do iButton (ID do motorista) (14 caracteres)
    //   systemDate: a data/hora
    //   driverName: o nome do motorista
  }

  /**
   * A função responsável pelo pós-processamento.
   *
   * @param array $parameters
   *   Os parâmetros pré-configurados
   * @param array $device
   *   Os dados do dispositivo para o qual obtivemos os dados de
   *   posicionamento
   *
   * @return void
   */
  public function afterProcess(
    array $parameters,
    array $device
  ): void
  {
    // Recupera os dados
    $deviceID = $device['deviceid'];
    $clientID = $device['clientid'];
    $plate    = $device['plate'];

    $this->debug("Iniciando pós-processamento");

    // Verifica se temos informações de ID's de motoristas para este
    // equipamento
    if (array_key_exists($deviceID, $this->driversPerDevice)) {
      // Verifica se temos ao menos um registro
      if (count($this->driversPerDevice[$deviceID]->drivers) > 0) {
        // O equipamento possui informações de ID's de motoristas, então
        // analisa se temos as informações de motoristas deste cliente
        if (!array_key_exists($clientID, $this->driversPerClient)) {
          $this->debug("Obtendo as informações de motoristas para o "
            . "cliente {clientID}",
            [ 'clientID'  => $clientID ]
          );
          // Obtemos os dados de motoristas deste cliente
          $driverList = $this->getDriversOnCustomer($clientID);

          // Adicionamos
          $this->driversPerClient[$clientID] = $driverList;
          $this->debug("Retornado {N} motoristas cadastrados para o "
            . "cliente {clientID}",
            [ 'N'  => count($driverList),
              'clientID'  => $clientID ]
          );
        }

        // Verifica se temos ao menos um ID de motorista cadastrado
        if (count($this->driversPerClient[$clientID]) > 0) {
          // Determinamos se existem ID's de motoristas que estão
          // armazenados no dispositivo em duplicidade
          $driversDuplicated = array_diff_assoc(
            $this->driversPerDevice[$deviceID]->drivers,
            array_unique($this->driversPerDevice[$deviceID]->drivers)
          );

          // Verifica se temos ID's de motoristas a serem removidos do
          // equipamento
          if (count($driversDuplicated) > 0) {
            $this->info("Removendo as informações dos motoristas "
              . "ID's {duplicated} cadastrados em duplicidade no "
              . "dispositivo {deviceID}",
              [ 'duplicated' => implode(', ', array_unique($driversDuplicated)),
                'deviceID'  => $deviceID ]
            );
            if ($this->sendModificationCommands) {
              // Envia o comando
              $this->deleteDrivers($deviceID, $driversDuplicated);
              
              // Aguarda um tempo entre esta requisição e a inserção de
              // novos IDs de motoristas para o equipamento processar
              $this->synchronizer->waitingTimeBetweenRequisitions(
                count($driversDuplicated) * 30
              );
            }
          } else {
            $this->debug("Não temos informações de motoristas "
              . "cadastrados em duplicidade no dispositivo {deviceID}",
              [ 'deviceID'  => $deviceID ]
            );
          }

          // Agora determinamos os ID's de motoristas que estão armazenados
          // no dispositivo e que não estão cadastrados no sistema, podendo
          // ser descartados
          $driversNotRegistered = array_diff(
            array_unique($this->driversPerDevice[$deviceID]->drivers),
            $this->driversPerClient[$clientID]
          );

          // Verifica se temos ID's de motoristas a serem removidos do
          // equipamento
          if (count($driversNotRegistered) > 0) {
            $this->info("Removendo as informações dos motoristas "
              . "ID's {deleted} cadastrados no dispositivo {deviceID} "
              . "mas que não constam no cadastro do cliente",
              [ 'deleted' => implode(', ', array_unique($driversNotRegistered)),
                'deviceID'  => $deviceID ]
            );
            if ($this->sendModificationCommands) {
              // Envia o comando
              $this->deleteDrivers($deviceID, array_unique($driversNotRegistered));

              // Aguarda um tempo entre esta requisição e a inserção de
              // novos IDs de motoristas para o equipamento processar
              $this->synchronizer->waitingTimeBetweenRequisitions(
                count(array_unique($driversNotRegistered)) * 30
              );
            }
          } else {
            $this->debug("Não temos informações de motoristas "
              . "cadastrados no dispositivo {deviceID} e que não constam "
              . "no cadastro do cliente",
              [ 'deviceID'  => $deviceID ]
            );
          }

          // Por último determinamos os ID's de motoristas que ainda não
          // estão cadastrados e que precisam ser enviados ao equipamento

          // Primeiramente, obtemos os ID's de motoristas que estão
          // cadastrados mas não estão registrados no dispositivo
          $driversUnregistered = array_diff(
            $this->driversPerClient[$clientID],
            $this->driversPerDevice[$deviceID]->drivers
          );

          // Agora obtemos os ID's dos motoristas que estavam em
          // duplicidade, e que foram removidos, mas que pertencem ao
          // cadastro do cliente e precisam ser reinseridos
          $driversToReinsert = array_intersect(
            $this->driversPerClient[$clientID],
            $driversDuplicated
          );

          // O resultado final é a soma destes dois
          $driversUnregistered = array_unique(
            array_merge(
              $driversUnregistered,
              $driversToReinsert
            )
          );

          // Verifica se temos ID's de motoristas faltando no equipamento
          if (count($driversUnregistered) > 0) {
            $this->info("Inserindo as informações dos motoristas "
              . "ID's {inserted} no dispositivo {deviceID}",
              [ 'inserted' => implode(', ', array_unique($driversUnregistered)),
                'deviceID'  => $deviceID ]
            );
            if ($this->sendModificationCommands) {
              // Envia o comando
              $this->insertDrivers($deviceID, $driversUnregistered);
            }
          } else {
            $this->debug("Não temos informações de novos motoristas "
              . "a serem cadastrados no dispositivo {deviceID}",
              [ 'deviceID'  => $deviceID ]
            );
          }
        } else {

        }
      } else {
        // Não mexemos em dispositivos para os quais não temos as
        // informações de motoristas cadastradas
      }
    } else {
      // Ignoramos equipamentos que não tenham ao menos um motorista
      // cadastrado pois este pode ser um equipamento não compatível
    }
  }


  // ================================[ Manipulação dos Motoristas ]=====

  /**
   * Envia um comando ao dispositivo para atualizar as informações de
   * IDs de motoristas registrador (excluíndo ou inserindo).
   *
   * @param string $path
   *   A URI para o serviço desejado
   * @param int $deviceID
   *   A ID dos dispositivos para o qual enviaremos a requisição
   * @param array $drivers
   *   A relação de IDs de motoristas
   *
   * @return array
   *   Uma matriz com o resultado da operação e uma mensagem em caso de
   *   erro
   */
  protected function sendCommand(
    string $path,
    int $deviceID,
    array $drivers = []
  ): array
  {
    // Convertemos as ID's para strings
    $driverList = [];

    foreach ($drivers as $key => $value) {
      $driverList[] = intval($value);
    }

    // Preparamos os parâmetros
    $params = [
      'deviceId' => $this->formatDeviceID($deviceID)
    ];

    if (count($driverList) > 0) {
      $drivers = [];
      foreach ($driverList as $key => $value) {
        $drivers[] = sprintf("%09d", $value);
      }
      $params['driverId'] = $drivers;
    }

    $response = $this->synchronizer->sendRequest($path, $params);
    var_dump($response);
    if ($path==='ws/device/sgbras/adddriverid') {
      exit();
    }
    if ($path==='ws/device/sgbras/deletedriverid') {
      exit();
    }

    // 1. Valida se temos as chaves necessárias
    if ( !array_key_exists('success', $response) ||
         !array_key_exists('error', $response) ) {
      return [
        false, "A requisição não retornou uma resposta válida"
      ];
    }

    // 2. Validamos o campo success
    if (!is_bool($response['success'])) {
      return [
        false, "A requisição não retornou uma resposta válida"
      ];
    }

    // 3. Validamos o campo error
    if (!is_int($response['error'])) {
      return [
        false, "A requisição não retornou uma resposta válida"
      ];
    }

    // O conteúdo retornado é uma resposta STC válida, então analisamos
    // o valor do campo 'success'
    if ($response['success'] === true) {
      return [
        true, "A requisição foi realizada com sucesso"
      ];
    } else {
      // Ocorreu algum erro na requisição, e a mensagem contém o
      // possível erro
      if (is_array($response['msg'])) {
        $concatenatedMessage = '';
        foreach ($response['msg'] as $message) {
          $concatenatedMessage .= ' ' . $message;
        }

        $errorMsg = trim($concatenatedMessage);
      } else {
        $errorMsg = trim($response['msg']);
      }

      return [
        false, $errorMsg
      ];
    }
  }

  /**
   * Envia um comando inserindo as IDs dos motoristas que ainda não
   * constam no equipamento.
   *
   * @param int $deviceID
   *   O ID do dispositivo
   * @param array $drivers
   *   Os IDs dos motoristas
   *
   * @return bool
   */
  protected function insertDrivers(
    int $deviceID,
    array $drivers
  ): bool
  {
    $this->synchronizer->updateProgress("Inserindo ID's motoristas...");

    $pages = array_chunk($drivers, 20);
    $total = count($pages);

    foreach ($pages as $page) {
      list($response, $message) = $this->sendCommand($this->insertPath,
        $deviceID, $page)
      ;

      if ($response) {
        $this->debug("Enviado comando de inserção das ID's de "
          . "motorista {driverList} para o dispositivo {deviceID}",
          [ 'deviceID'  => $deviceID,
            'driverList' => implode(', ', $page) ]
        );

        $page--;

        if ($page > 0) {
          // Aguarda um tempo entre estas requisições. Este tempo é de
          // normalmente 1 min.
          $this->synchronizer->waitingTimeBetweenRequisitions(1 * 60);
        } else {
          return true;
        }
      } else {
        // Registra o erro
        $this->error("Não foi possível enviar ao dispositivo "
          . "{deviceID} as ID's de motorista {driverList}. {error}.",
          [ 'deviceID'  => $deviceID,
            'driverList' => implode(', ', $drivers),
            'error'  => $message ]
        );
      }

      return false;
    }

    return false;
  }

  /**
   * Envia um comando removendo as IDs dos motoristas que ainda não
   * constam no equipamento.
   *
   * @param int $deviceID
   *   O ID do dispositivo
   * @param array $drivers
   *   Os IDs dos motoristas
   *
   * @return bool
   */
  protected function deleteDrivers(
    int $deviceID,
    array $drivers
  ): bool
  {
    $this->synchronizer->updateProgress("Removendo ID's motoristas...");

    list($response, $message) = $this->sendCommand($this->deletePath,
      $deviceID, $drivers)
    ;

    if ($response) {
      $this->debug("Enviado comando de solicitação da remoção dos "
        . "IDs de motoristas {driverList} do dispositivo {deviceID}",
        [ 'driverList' => implode(', ', $drivers),
          'deviceID'  => $deviceID ]
      );

      return true;
    } else {
      // Registra o erro
      $this->error("Não foi possível enviar comando para remover "
        . "do dispositivo {deviceID} as ID's de motorista {driverList}."
        . " {error}.",
        [ 'deviceID'  => $deviceID,
          'driverList' => implode(', ', $drivers),
          'error'  => $message ]
      );
    }

    return false;
  }
}
