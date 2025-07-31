<?php
/*
 * Este arquivo é parte do Sistema de ERP do Grupo M&M
 *
 * (c) Grupo M&M
 *
 * Para obter informações completas sobre direitos autorais e licenças,
 * consulte o arquivo LICENSE que foi distribuído com este código-fonte.
 * ---------------------------------------------------------------------
 * Descrição:
 *
 * O controlador do gerenciamento dos movimentações de dispositivos do
 * sistema. Permite gerenciar a movimentação de dispositivos entre
 * depósitos e o envio para técnicos e/ou prestadores de serviços.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Controllers\ERP\Devices;

use App\Models\Deposit;
use App\Models\Entity;
use App\Models\Equipment;
use App\Models\SimCard;
use App\Models\User;
use Core\Controllers\Controller;
use Core\Controllers\QueryTrait;
use Exception;
use Illuminate\Database\QueryException;
use Respect\Validation\Validator as V;
use Slim\Http\Request;
use Slim\Http\Response;

class MovimentationsController
  extends Controller
{
  /**
   * Os métodos para manipular queries.
   */
  use QueryTrait;

  /**
   * Exibe um formulário para transferência de dispositivos entre 
   * depósitos e/ou para um técnico ou prestador de serviços.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   *
   * @return Response $response
   */
  public function transfer(Request $request, Response $response)
  {
    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();

    // Determina os tipos de dispositivos
    $deviceTypes = [
      [ 'id' => 'Equipment', 'name' => 'Equipamentos'],
      [ 'id' => 'SimCard', 'name' => 'SIM Cards']
    ];

    // Determina os possíveis locais de armazenamento
    $storageLocations = [
      [ 'id' => 'StoredOnDeposit',
        'name' => 'Armazenados num depósito' ],
      [ 'id' => 'StoredWithTechnician',
        'name' => 'De posse de um técnico' ],
      [ 'id' => 'StoredWithServiceProvider',
        'name' => 'De posse de um prestador de serviços' ]
    ];
    $contractorID = $contractor->id;

    // Recupera as informações de depósitos
    $deposits = Deposit::where("contractorid", '=', $contractor->id)
      ->whereRaw("devicetype IN ('SimCard', 'Both')")
      ->orderBy('name')
      ->get([
          'depositid AS id',
          'name',
          'master'
        ])
    ;
    if ($deposits->count() > 0) {
      $defaultDepositID = $deposits[0]->id;
      foreach ($deposits as $key => $deposit) {
        if ($deposit->master) {
          $defaultDepositID = $deposit->id;

          break;
        }
      }
    } else {
      $defaultDepositID = 0;

      // Elimina da seleção a opção para depósitos
      unset($storageLocations[0]);
    }

    // Recuperamos todos os técnicos disponíveis
    $sql = "SELECT technician.id,
                   technician.name,
                   technician.serviceProviderID
              FROM erp.getTechnicians({$contractorID}, '', 0) AS technician;"
    ;
    $technicians = $this->DB->select($sql);
    if (count($technicians) == 0) {
      // Elimina da seleção a opção para técnicos
      unset($storageLocations[1]);
    }

    // Recuperamos todos os prestadores de serviços disponíveis
    $sql = "SELECT serviceprovider.id,
                   serviceprovider.name
              FROM erp.getServiceProviders({$contractorID}, '', 0) AS serviceprovider;"
    ;
    $serviceproviders = $this->DB->select($sql);
    if (count($serviceproviders) == 0) {
      // Elimina da seleção a opção para prestadores de serviços
      unset($storageLocations[2]);
    }

    // Verifica se estamos postando os dados
    if ($request->isPut()) {
      // Os dados estão sendo postados

      // Registra o acesso
      $this->debug("Processando à transferência de dispositivos.");

      // Determina o passo em que estamos
      $step = intval($request->getParam('step'));

      // Determina a ação escolhida
      $action =$request->getParam('action');

      if ($action === 'Previous') {
        // Retrocedemos a página no formulário
        $step--;

        // Recupera os valores até o momento
        $values = $request->getParams();
        $values['step'] = $step;

        // Carrega os valores iniciais
        $this->validator->setValues($values);
      } else {
        // Determina o passo que estamos
        $step = $request->getParam('step');

        // Determina o local de armazenamento
        $storageLocation    = $request->getParam('storageLocation');
        $newStorageLocation = '';
        if ($step == 3) {
          $newStorageLocation = $request->getParam('newStorageLocation');
        }

        // Monta uma matriz para validação dos tipos de dispositivos
        $deviceTypesValues = [ ];
        foreach ($deviceTypes AS $deviceType) {
          $deviceTypesValues[] = $deviceType['id'];
        }

        // Monta uma matriz para validação dos locais de armazenamento
        $storageLocationsValues = [ ];
        foreach ($storageLocations AS $location) {
          $storageLocationsValues[] = $location['id'];
        }

        // Valida os dados
        $this->validator->validate($request, [
          'step' => V::notBlank()
            ->intVal()
            ->setName('Passo'),
          'deviceType' => V::notBlank()
            ->in($deviceTypesValues)
            ->setName('Tipo de dispositivo'),
          'storageLocation' => V::notBlank()
            ->in($storageLocationsValues)
            ->setName('Local de armazenamento'),
          'depositID' => V::ifThis(
                $storageLocation === 'StoredOnDeposit',
                V::notEmpty()
                  ->intVal(),
                V::optional(V::intVal())
              )
            ->setName('Depósito'),
          'technicianID' => V::ifThis(
                $storageLocation === 'StoredWithTechnician',
                V::notEmpty()
                  ->intVal(),
                V::optional(V::intVal())
              )
            ->setName('Técnico'),
          'serviceProviderID' => V::ifThis(
                $storageLocation === 'StoredWithServiceProvider',
                V::notEmpty()
                  ->intVal(),
                V::optional(V::intVal())
              )
            ->setName('Prestador de serviços'),
          'devices' => V::ifThis(
                $step > 1,
                V::arrayType()
                  ->notEmpty(),
                V::not(V::arrayType()
                  ->notEmpty()
                )
              )
            ->setName('Dispositivos selecionados'),
          'newStorageLocation' => V::ifThis(
                $step == 3,
                V::notBlank()
                  ->in($storageLocationsValues),
                V::optional(V::in($storageLocationsValues))
              )
            ->setName('Novo local de armazenamento'),
          'newDepositID' => V::ifThis(
                $newStorageLocation === 'StoredOnDeposit',
                V::notEmpty()
                  ->intVal(),
                V::optional(V::intVal())
              )
            ->setName('Depósito'),
          'newTechnicianID' => V::ifThis(
                $newStorageLocation === 'StoredWithTechnician',
                V::notEmpty()
                  ->intVal(),
                V::optional(V::intVal())
              )
            ->setName('Técnico'),
          'newServiceProviderID' => V::ifThis(
                $newStorageLocation === 'StoredWithServiceProvider',
                V::notEmpty()
                  ->intVal(),
                V::optional(V::intVal())
              )
            ->setName('Prestador de serviços'),
        ]);

        if ($this->validator->isValid()) {
          // Recupera os dados da transferência
          $transferData = $this->validator->getValues();

          if ($action === 'Next') {
            $this->info("Avançando para o próximo passo da "
              . "transferência de dispositivos."
            );

            // Avança para a próxima página
            $step++;

            $transferData['step'] = $step;

            // Carrega os valores iniciais
            $this->validator->setValues($transferData);

            // Ajusta, conforme a seleção do usuário, as opções para
            // transferência. Deve ocultar o próprio local onde o
            // dispositivo se encontra e limitar as transferências
            switch ($storageLocation) {
              case 'StoredOnDeposit':
                // Recupera o depósito onde o dispositivo se encontra
                $depositID = $request->getParam('depositID');

                if (count($deposits) > 1) {
                  // Percorre os itens dos depósitos e exclui ele
                  foreach ($deposits as $key => $deposit) {
                    if ($deposit->id == $depositID) {
                      // Elimina este item e interrompe a pesquisa
                      unset($deposits[$key]);

                      break;
                    }
                  }
                } else {
                  // Deixa em branco
                  $deposits = [ ];

                  // Remove a opção para transferir para outro depósito
                  unset($storageLocations[0]);
                }

                break;
              case 'StoredWithTechnician':
                // Recupera o técnico com o qual o dispositivo se encontra
                $technicianID = $request->getParam('technicianID');
                
                if (count($technicians) > 1) {
                  // Percorre os itens dos depósitos e exclui ele
                  foreach ($technicians as $key => $technician) {
                    if ($technician->id == $technicianID) {
                      // Elimina este item e interrompe a pesquisa
                      unset($technicians[$key]);

                      break;
                    }
                  }
                } else {
                  $technicians = [ ];

                  // Remove a opção para transferir para outro técnico
                  unset($storageLocations[1]);
                }

                break;
              case 'StoredWithServiceProvider':
                // Recupera o técnico com o qual o dispositivo se
                // encontra
                $serviceProviderID =
                  $request->getParam('serviceProviderID')
                ;
                
                if (count($serviceproviders) > 1) {
                  // Percorre os itens dos depósitos e exclui ele
                  foreach ($serviceproviders as $key => $serviceprovider) {
                    if ($serviceprovider->id == $serviceProviderID) {
                      // Elimina este item e interrompe a pesquisa
                      unset($serviceproviders[$key]);

                      break;
                    }
                  }
                } else {
                  $serviceproviders = [ ];

                  // Remove a opção para transferir para outro prestador
                  // de serviços
                  unset($storageLocations[2]);
                }

                break;
              default:
                // Não faz nada
                
                break;
            }
          } else {
            if ($action === 'Transfer') {
              // Realiza a transferência de dispositivos para o local
              // informado
              
              // Recupera as informações base da transferência
              $deviceType = $transferData['deviceType'];
              $newStorageLocation = $transferData['newStorageLocation'];

              // Monta uma matriz com os nomes dos depósitos
              $depositData = [ ];
              foreach ($deposits as $key => $deposit) {
                $depositData[$deposit['id']] = $deposit['name'];
              }

              // Monta uma matriz com os nomes dos técnicos
              $technicianData = [ ];
              foreach ($technicians as $key => $technician) {
                $technicianData[$technician->id] = $technician->name;
              }

              // Monta uma matriz com os nomes dos prestadores de
              // serviços
              $serviceproviderData = [ ];
              foreach ($serviceproviders as $key => $serviceprovider) {
                $serviceproviderData[$serviceprovider->id] =
                  $serviceprovider->name
                ;
              }

              // Determina os textos para registro do local de
              // origem desta operação
              switch ($storageLocation) {
                case 'StoredOnDeposit':
                  $from = 'depósito '
                    . $depositData[$transferData['depositID']]
                  ;

                  break;
                case 'StoredWithTechnician':
                  $from = 'técnico '
                    . $technicianData[$transferData['technicianID']]
                  ;

                  break;
                case 'StoredWithServiceProvider':
                  $from = 'prestador de serviços '
                    . $serviceproviderData[$transferData['serviceProviderID']]
                  ;

                  break;
              }

              // Determina os textos para registro do local de
              // destino desta operação
              switch ($newStorageLocation) {
                case 'StoredOnDeposit':
                  $to = 'depósito '
                    . $depositData[$transferData['newDepositID']]
                  ;

                  break;
                case 'StoredWithTechnician':
                  $to = 'técnico '
                    . $technicianData[$transferData['newTechnicianID']]
                  ;

                  break;
                case 'StoredWithServiceProvider':
                  $to = 'prestador de serviços '
                    . $serviceproviderData[$transferData['newServiceProviderID']]
                  ;

                  break;
              }

              // Lida com a transferência de acordo com o tipo de
              // dispositivo
              switch ($deviceType) {
                case 'SimCard':
                  // Faz a transferência dos SIM Cards
                  try {
                    // Iniciamos a transação
                    $this->DB->beginTransaction();

                    foreach ($transferData['devices'] as $key => $simcardID) {
                      // Repete o processo para cada SIM Card selecionado
                      
                      // Recupera as informações do SIM Card
                      $simcard = SimCard::findOrFail($simcardID);

                      // Adicionamos as informações do responsável pela
                      // transferência
                      $simcard->updatedbyuserid =
                        $this->authorization->getUser()->userid
                      ;

                      // Adicionamos as informações do local onde o Sim
                      // Card será armazenado
                      $simcard->storagelocation = $newStorageLocation;
                      switch ($newStorageLocation) {
                        case 'StoredWithTechnician':
                          // Ficará de posse do técnico, então informa o
                          // seu ID
                          $simcard->technicianid =
                            $transferData['newTechnicianID']
                          ;

                          break;
                        case 'StoredWithServiceProvider':
                          // Ficará de posse do prestador de serviços,
                          // então informa o seu ID
                          $simcard->serviceproviderid =
                            $transferData['newServiceProviderID']
                          ;

                          break;
                        
                        default:
                          // Ficará armazenado num depósito
                          $simcard->depositid =
                            $transferData['newDepositID']
                          ;

                          break;
                      }

                      // Efetiva a transferência
                      $simcard->save();

                      // Registra o sucesso
                      $this->info("O SIM Card ICCID '{iccid}' foi "
                        . "transferido do {from} para o '{to}' com "
                        . "sucesso.",
                        [ 'iccid' => $simcard->iccid,
                          'from' => $from,
                          'to' => $to ]
                      );
                    }

                    // Efetiva a transação
                    $this->DB->commit();

                    // Registra o sucesso
                    $this->info("A transferência de SIM Card foi "
                      . "concluída com sucesso."
                    );

                    // Alerta o usuário
                    $this->flash("success", "A transferência de "
                      . "<i>'SIM Card'</i> foi concluída com sucesso."
                    );

                    // Registra o evento
                    $this->debug("Redirecionando para {routeName}",
                      [ 'routeName' => 'ERP\Devices\Movimentations\Transfer' ]
                    );

                    // Redireciona para a página de transferência
                    return $this->redirect($response,
                      'ERP\Devices\Movimentations\Transfer')
                    ;
                  }
                  catch(QueryException $exception)
                  {
                    // Reverte (desfaz) a transação
                    $this->DB->rollBack();

                    // Registra o erro
                    $this->error("Não foi possível transferir o(s) "
                      . "SIM Card(s) do {from} para o '{to}'. Erro "
                      . "interno no banco de dados: {error}",
                      [ 'from' => $from,
                        'to' => $to,
                        'error' => $exception->getMessage() ]
                    );

                    // Alerta o usuário
                    $this->flashNow("error", "Não foi possível "
                      . "transferir o(s) SIM Card(s). Erro interno no "
                      . "banco de dados."
                    );
                  }
                  catch(Exception $exception)
                  {
                    // Reverte (desfaz) a transação
                    $this->DB->rollBack();

                    // Registra o erro
                    $this->error("Não foi possível transferir o(s) "
                      . "SIM Card(s) do {from} para o '{to}'. Erro "
                      . "interno: {error}",
                      [ 'from' => $from,
                        'to' => $to,
                        'error' => $exception->getMessage() ]
                    );

                    // Alerta o usuário
                    $this->flashNow("error", "Não foi possível "
                      . "transferir o(s) SIM Card(s). Erro interno."
                    );
                  }

                  break;
                default:
                  // Faz a transferência dos equipamentos
                  try {
                    // Iniciamos a transação
                    $this->DB->beginTransaction();

                    foreach ($transferData['devices'] as $key => $equipmentID) {
                      // Repete o processo para cada equipamento
                      // selecionado
                      
                      // Recupera as informações do equipamento
                      $equipment = Equipment::findOrFail($equipmentID);

                      // Adicionamos as informações do responsável pela
                      // transferência
                      $equipment->updatedbyuserid = $this->authorization->getUser()->userid;

                      // Adicionamos as informações do local onde o
                      // equipamento será armazenado
                      $equipment->storagelocation = $newStorageLocation;
                      switch ($newStorageLocation) {
                        case 'StoredWithTechnician':
                          // Ficará de posse do técnico, então informa o
                          // seu ID
                          $equipment->technicianid =
                            $transferData['technicianID']
                          ;

                          break;
                        case 'StoredWithServiceProvider':
                          // Ficará de posse do prestador de serviços,
                          // então informa o seu ID
                          $equipment->serviceproviderid =
                            $transferData['serviceProviderID']
                          ;

                          break;
                        
                        default:
                          // Ficará armazenado num depósito
                          $equipment->depositid =
                            $transferData['depositID']
                          ;

                          break;
                      }

                      // Efetiva a transferência
                      $equipment->save();

                      // Registra o sucesso
                      $this->info("O equipamento IMEI '{imei}' foi "
                        . "transferido do {from} para o '{to}' com "
                        . "sucesso.",
                        [ 'imei' => $equipment->imei,
                          'from' => $from,
                          'to' => $to ]
                      );
                    }

                    // Efetiva a transação
                    $this->DB->commit();

                    // Registra o sucesso
                    $this->info("A transferência de equipamento foi "
                      . "concluída com sucesso."
                    );

                    // Alerta o usuário
                    $this->flash("success", "A transferência de "
                      . "<i>'equipamento'</i> foi concluída com "
                      . "sucesso."
                    );

                    // Registra o evento
                    $this->debug("Redirecionando para {routeName}",
                      [ 'routeName' => 'ERP\Devices\Movimentations\Transfer' ]
                    );

                    // Redireciona para a página de transferência
                    return $this->redirect($response,
                      'ERP\Devices\Movimentations\Transfer')
                    ;
                  }
                  catch(QueryException $exception)
                  {
                    // Reverte (desfaz) a transação
                    $this->DB->rollBack();

                    // Registra o erro
                    $this->error("Não foi possível transferir o(s) "
                      . "equipamento(s) do {from} para o '{to}'. Erro "
                      . "interno no banco de dados: {error}",
                      [ 'from' => $from,
                        'to' => $to,
                        'error' => $exception->getMessage() ]
                    );

                    // Alerta o usuário
                    $this->flashNow("error", "Não foi possível "
                      . "transferir o(s) equipamento(s). Erro interno "
                      . "no banco de dados."
                    );
                  }
                  catch(Exception $exception)
                  {
                    // Reverte (desfaz) a transação
                    $this->DB->rollBack();

                    // Registra o erro
                    $this->error("Não foi possível transferir o(s) "
                      . "equipamento(s) do {from} para o '{to}'. Erro "
                      . "interno: {error}",
                      [ 'from' => $from,
                        'to' => $to,
                        'error' => $exception->getMessage() ]
                    );

                    // Alerta o usuário
                    $this->flashNow("error", "Não foi possível "
                      . "transferir o(s) equipamento(s). Erro interno."
                    );
                  }

                  break;
              }
            }
          }
        }
      }
    } else {
      // Carrega os valores iniciais
      $this->validator->setValues([
        'step' => 1,
        'deviceType' => $deviceTypes[0]['id'],
        'depositID' => $defaultDepositID,
        'technicianID' => 0,
        'serviceProviderID' => 0,
        'newDepositID' => 0,
        'newTechnicianID' => 0,
        'newServiceProviderID' => 0
      ]);
    }

    // Exibe um formulário para transferência de um dispositivo

    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ERP\Home')
    );
    $this->breadcrumb->push('Movimentação', null);
    $this->breadcrumb->push('Transferência',
      $this->path('ERP\Devices\Movimentations\Transfer')
    );

    // Registra o acesso
    $this->info("Acesso à transferência de dispositivos.");

    return $this->render($request, $response,
      'erp/devices/movimentations/transfer.twig',
      [ 'formMethod' => 'PUT',
        'deviceTypes' => $deviceTypes,
        'storageLocations' => $storageLocations,
        'deposits' => $deposits,
        'technicians' => $technicians,
        'serviceproviders' => $serviceproviders ])
    ;
  }

  /**
   * Recupera a relação dos dispositivos em formato JSON.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   *
   * @return Response $response
   */
  public function getDevices(Request $request, Response $response)
  {
    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();

    $this->debug("Acesso à relação de dispositivos.");

    // --------------------------[ Recupera os dados requisitados ]-----

    // Recupera os dados da requisição
    $postParams = $request->getParsedBody();
    
    // Lida com as informações provenientes do Datatables
    
    // O número da requisição sequencial
    $draw = $postParams['draw'];

    // As definições das colunas
    $columns = $postParams['columns'];

    // O ordenamento, onde:
    //   column: id da coluna
    //      dir: direção
    $order = $postParams['order'][0];
    $orderBy  = $columns[$order['column']]['name'];
    $orderDir = strtoupper($order['dir']);

    // A posição inicial (0 = início) da paginação
    $start = $postParams['start'];

    // O comprimento de cada página
    $length = $postParams['length'];

    // Lida com as informações adicionais de filtragem
    
    // O campo de pesquisa selecionado
    $deviceType = $postParams['deviceType'];
    if (array_key_exists('storageLocation', $postParams)) {
      $storageLocation    = $postParams['storageLocation'];
      $depositID          = $postParams['depositID'];
      $technicianID       = $postParams['technicianID'];
      $serviceProviderID  = $postParams['serviceProviderID'];
    } else {
      $storageLocation    = 'StoredOnDeposit';
      $depositID          = 0;
      $technicianID       = 0;
      $serviceProviderID  = 0;
    }

    // Determina o tipo de dispositivo que estamos lidando
    switch ($deviceType) {
      case 'SimCard':
        $deviceName = 'SIM Cards';

        break;
      case 'Equipment':
        $deviceName = 'equipamentos';
        
        break;
      default:
        $deviceName = 'dispositivos';

        break;
    }
    // Determina o ID do dispositivo
    switch ($storageLocation) {
      case 'StoredOnDeposit':
        // Apenas os armazenados em um depósito. Recupera o ID do
        // depósito
        $storageID = $depositID;

        break;
      case 'StoredWithTechnician':
        // Apenas os de posse de um técnico. Recupera o ID do técnico
        $storageID = $technicianID;

        break;
      case 'StoredWithServiceProvider':
        // Apenas os de posse de um prestador de serviços. Recupera o
        // ID do prestador de serviços
        $storageID = $serviceProviderID;

        break;
      default:
        // Não foi informado um local válido onde estão estes dispositivos
        $error = "Informe um local válido onde estão estes "
          . "{$deviceName}";
        
        // Registra o erro
        $this->error("Não foi possível recuperar as informações de "
          . "{module}. {error}.",
          [ 'module' => $deviceName,
            'error' => $error ]
        );

        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'draw' => $draw,
              'recordsTotal' => 0,
              'recordsFiltered' => 0,
              'data' => [ ],
              'error' => $error
            ])
        ;

        break;
    }
    
    try
    {
      // Formata o ordenamento dos campos
      $ORDER = $this->formatOrderBy($orderBy, $orderDir);

      // Monta a consulta
      $contractorID = $this->authorization->getContractor()->id;
      if ($deviceType == 'SimCard') {
        // Recupera os SIM Cards
        $sql = "SELECT '' AS selected,
                       SC.simcardid AS id,
                       SC.iccid,
                       SC.imsi,
                       SC.phonenumber,
                       SC.mobileoperatorid,
                       SC.mobileoperatorname,
                       SC.mobileoperatorlogo,
                       SC.simcardtypename,
                       SC.assetnumber,
                       SC.fullcount
                  FROM erp.getSimCardsData({$contractorID}, 0, 0, 0,
                    null, null, 0, 0, '{$storageLocation}', {$storageID},
                    '{$ORDER}', {$start}, {$length}) AS SC
                 WHERE SC.blockedlevel = 0
                   AND SC.attached = false;"
        ;
      } else {
        // Recupera os equipamentos
        $sql = "SELECT '' AS selected,
                       E.equipmentid AS id,
                       E.imei,
                       E.serialnumber,
                       E.equipmentmodelid,
                       E.equipmentmodelname AS modelname,
                       E.equipmentbrandid,
                       E.equipmentbrandname AS brandname,
                       E.stateid,
                       E.statename,
                       E.assetnumber,
                       E.fullcount
                  FROM erp.getEquipmentsData({$contractorID}, 0, 0, 0,
                    null, 'Any', 0, '{$storageLocation}', {$storageID},
                    '{$ORDER}', {$start}, {$length}) AS E
                 WHERE E.blockedlevel = 0
                   AND E.attached = false;"
        ;
      }
      $devices = $this->DB->select($sql);
      
      $rowCount = 0;
      if (count($devices) > 0) {
        $rowCount = $devices[0]->fullcount;

        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'draw' => $draw,
              'recordsTotal' => $rowCount,
              'recordsFiltered' => $rowCount,
              'data' => $devices
            ])
        ;
      } else {
        if ($storageID > 0) {
          $error = "Não localizamos {$deviceName} no local informado.";
        } else {
          $error = "Não localizamos {$deviceName} que estejam "
            . "disponíveis para devolução."
          ;
        }
      }
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{deviceName}. Erro interno no banco de dados: {error}",
        [ 'deviceName' => $deviceName,
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de "
        . "{$deviceName}. Erro interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{deviceName}. Erro interno: {error}",
        [ 'deviceName' => $deviceName,
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de "
        . "{$deviceName}. Erro interno."
      ;
    }

    return $response
      ->withHeader('Content-type', 'application/json')
      ->withJson([
          'draw' => $draw,
          'recordsTotal' => 0,
          'recordsFiltered' => 0,
          'data' => [ ],
          'error' => $error
        ])
    ;
  }

  /**
   * Exibe um formulário para devolução de dispositivos para seu
   * fornecedor.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   *
   * @return Response $response
   */
  public function return(Request $request, Response $response)
  {
    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();
    $contractorID = $contractor->id;

    // Determina os tipos de dispositivos
    $deviceTypes = [
      [ 'id' => 'Equipment', 'name' => 'Equipamentos'],
      [ 'id' => 'SimCard', 'name' => 'SIM Cards']
    ];

    // Verifica se estamos postando os dados
    if ($request->isPut()) {
      // Os dados estão sendo postados

      // Registra o acesso
      $this->debug("Processando à devolução de dispositivos para seu "
        . "fornecedor."
      );

      // Determina o passo em que estamos
      $step = intval($request->getParam('step'));

      // Determina a ação escolhida
      $action =$request->getParam('action');

      if ($action === 'Previous') {
        // Retrocedemos a página no formulário
        $step--;

        // Recupera os valores até o momento
        $values = $request->getParams();
        $values['step'] = $step;

        // Carrega os valores iniciais
        $this->validator->setValues($values);
      } else {
        // Determina o passo que estamos
        $step = $request->getParam('step');

        // Monta uma matriz para validação dos tipos de dispositivos
        $deviceTypesValues = [ ];
        foreach ($deviceTypes AS $deviceType) {
          $deviceTypesValues[] = $deviceType['id'];
        }

        // Valida os dados
        $this->validator->validate($request, [
          'step' => V::notBlank()
            ->intVal()
            ->setName('Passo'),
          'deviceType' => V::notBlank()
            ->in($deviceTypesValues)
            ->setName('Tipo de dispositivo'),
          'devices' => V::ifThis(
                $step > 1,
                V::arrayType()
                  ->notEmpty(),
                V::not(V::arrayType()
                    ->notEmpty())
              )
            ->setName('Dispositivos selecionados'),
              'devicesData' => V::ifThis(
                $step == 2,
                V::notEmpty(),
                V::not(V::notEmpty())
              )
            ->setName('Dispositivos selecionados'),
        ]);

        if ($this->validator->isValid()) {
          // Recupera os dados da devolução
          $returnData = $this->validator->getValues();

          if ($action === 'Next') {
            $this->info("Avançando para o próximo passo da devolução "
              . "de dispositivos."
            );

            // Avança para a próxima página
            $step++;

            if ($step == 3) {
              eval('$devicesData = ' . $returnData['devicesData'] . ';');
              $returnData['devicesData'] = $devicesData;
            }

            $returnData['step'] = $step;

            // Carrega os valores iniciais
            $this->validator->setValues($returnData);
          } else {
            if ($action === 'Return') {
              // Realiza a devolução de dispositivos para seu fornecedor
              
              // Recupera as informações base da devolução
              $deviceType = $returnData['deviceType'];

              // Lida com a devolução de acordo com o tipo de dispositivo
              switch ($deviceType) {
                case 'SimCard':
                  // Faz a devolução dos SIM Cards
                  try {
                    // Iniciamos a transação
                    $this->DB->beginTransaction();

                    foreach ($returnData['devices'] as $key => $simcardID) {
                      // Repete o processo para cada SIM Card selecionado
                      
                      // Recupera as informações do SIM Card
                      $simcard = SimCard::findOrFail($simcardID);

                      // Adicionamos as informações do responsável pela
                      // devolução
                      $simcard->updatedbyuserid =
                        $this->authorization->getUser()->userid
                      ;

                      // Adicionamos as informações da devolução
                      $simcard->storagelocation = 'ReturnedToSupplier';
                      $simcard->depositid = null;

                      // Efetiva a devolução
                      $simcard->save();

                      // Registra o sucesso
                      $this->info("O SIM Card ICCID '{iccid}' foi "
                        . "devolvido para o fornecedor com sucesso.",
                        [ 'iccid' => $simcard->iccid ]
                      );
                    }

                    // Efetiva a transação
                    $this->DB->commit();

                    // Registra o sucesso
                    $this->info("A devolução de SIM Card foi concluída "
                      . "com sucesso."
                    );

                    // Alerta o usuário
                    $this->flash("success", "A devolução de "
                      . "<i>'SIM Card'</i> foi concluída com sucesso."
                    );

                    // Registra o evento
                    $this->debug("Redirecionando para {routeName}",
                      [ 'routeName' => 'ERP\Devices\Movimentations\Devolve' ]
                    );

                    // Redireciona para a página de devolução
                    return $this->redirect($response,
                      'ERP\Devices\Movimentations\Return')
                    ;
                  }
                  catch(QueryException $exception)
                  {
                    // Reverte (desfaz) a transação
                    $this->DB->rollBack();

                    // Registra o erro
                    $this->error("Não foi possível devolver o(s) Sim "
                      . "Card(s) para seu fornecedor. Erro interno no "
                      . "banco de dados: {error}",
                      [ 'error' => $exception->getMessage() ]
                    );

                    // Alerta o usuário
                    $this->flashNow("error", "Não foi possível "
                      . "devolver o(s) SIM Card(s) para seu "
                      . "fornecedor. Erro interno no banco de dados."
                    );
                  }
                  catch(Exception $exception)
                  {
                    // Reverte (desfaz) a transação
                    $this->DB->rollBack();

                    // Registra o erro
                    $this->error("Não foi possível devolver o(s) Sim "
                      . "Card(s) para seu fornecedor. Erro interno: "
                      . "{error}",
                      [ 'error' => $exception->getMessage() ]
                    );

                    // Alerta o usuário
                    $this->flashNow("error", "Não foi possível "
                      . "devolver o(s) SIM Card(s) para seu "
                      . "fornecedor. Erro interno."
                    );
                  }

                  break;
                default:
                  // Faz a devolução dos equipamentos
                  try {
                    // Iniciamos a transação
                    $this->DB->beginTransaction();

                    foreach ($returnData['devices'] as $key => $equipmentID) {
                      // Repete o processo para cada equipamento
                      // selecionado
                      
                      // Recupera as informações do equipamento
                      $equipment = Equipment::findOrFail($equipmentID);

                      // Adicionamos as informações do responsável pela
                      // devolução
                      $equipment->updatedbyuserid = $this->authorization->getUser()->userid;

                      // Adicionamos as informações da devolução
                      $equipment->storagelocation = 'ReturnedToSupplier';
                      $equipment->depositid = 0;
                      
                      // Efetiva a devolução
                      $equipment->save();

                      // Registra o sucesso
                      $this->info("O equipamento IMEI '{imei}' foi "
                        . "devolvido para seu fornecedor com sucesso.",
                        [ 'imei' => $equipment->imei ]
                      );
                    }

                    // Efetiva a transação
                    $this->DB->commit();

                    // Registra o sucesso
                    $this->info("A devolução de equipamento foi "
                      . "concluída com sucesso."
                    );

                    // Alerta o usuário
                    $this->flash("success", "A devolução de "
                      . "<i>'equipamento'</i> foi concluída com "
                      . "sucesso."
                    );

                    // Registra o evento
                    $this->debug("Redirecionando para {routeName}",
                      [ 'routeName' => 'ERP\Devices\Movimentations\Return' ]
                    );

                    // Redireciona para a página de devolução
                    return $this->redirect($response,
                      'ERP\Devices\Movimentations\Return')
                    ;
                  }
                  catch(QueryException $exception)
                  {
                    // Reverte (desfaz) a transação
                    $this->DB->rollBack();

                    // Registra o erro
                    $this->error("Não foi possível devolver o(s) "
                      . "equipamento(s) para seu fornecedor. Erro "
                      . "interno no banco de dados: {error}",
                      [ 'error' => $exception->getMessage() ]
                    );

                    // Alerta o usuário
                    $this->flashNow("error", "Não foi possível "
                      . "devolver o(s) equipamento(s) para seu "
                      . "fornecedor. Erro interno no banco de dados."
                    );
                  }
                  catch(Exception $exception)
                  {
                    // Reverte (desfaz) a transação
                    $this->DB->rollBack();

                    // Registra o erro
                    $this->error("Não foi possível devolver o(s) "
                      . "equipamento(s) para seu fornecedor. Erro "
                      . "interno: {error}",
                      [ 'error' => $exception->getMessage() ]
                    );

                    // Alerta o usuário
                    $this->flashNow("error", "Não foi possível "
                      . "devolver o(s) equipamento(s) para seu "
                      . "fornecedor. Erro interno."
                    );
                  }

                  break;
              }
            }
          }
        }
      }
    } else {
      // Carrega os valores iniciais
      $this->validator->setValues([
        'step' => 1,
        'deviceType' => $deviceTypes[0]['id']
      ]);
    }

    // Exibe um formulário para devolução de um dispositivo

    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ERP\Home')
    );
    $this->breadcrumb->push('Movimentação', null);
    $this->breadcrumb->push('Devolução',
      $this->path('ERP\Devices\Movimentations\Return')
    );

    // Registra o acesso
    $this->info("Acesso à devolução de dispositivos para o "
      . "fornecedor."
    );

    return $this->render($request, $response,
      'erp/devices/movimentations/return.twig',
      [ 'formMethod' => 'PUT',
        'deviceTypes' => $deviceTypes ])
    ;
  }
}