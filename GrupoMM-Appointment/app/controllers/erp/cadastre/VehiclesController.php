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
 * O controlador do gerenciamento dos veículos do sistema. Um veículo é
 * uma entidade que pode conter um rastreador e obrigatoriamente pertence
 * a um cliente.
 *
 * Os arquivos anexados são armazenados na pasta de anexos (configurável
 * através da propriedade 'attachments' no arquivo de configuração dos
 * serviços), usando a seguinte estrutura:
 *   $path/{ID do Contratante}/{Arquivo}
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Controllers\ERP\Cadastre;

use App\Models\Affiliation;
use App\Models\AnotherPhone;
use App\Models\AuthorizedEquipment;
use App\Models\Contract;
use App\Models\Deposit;
use App\Models\Entity;
use App\Models\Equipment;
use App\Models\EquipmentModel;
use App\Models\EquipmentsToGetHistory;
use App\Models\Installation;
use App\Models\InstallationRecord;
use App\Models\OwnerPhone;
use App\Models\Phone;
use App\Models\Subsidiary;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleAttachment;
use App\Models\VehicleModel;
use App\Models\VehicleTypePerBrand;
use Carbon\Carbon;
use Core\Controllers\Controller;
use Core\Controllers\HandleFileTrait;
use Core\Controllers\QueryTrait;
use Core\Exceptions\UploadFileException;
use Core\Helpers\FormatterTrait;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Mpdf\Mpdf;
use RuntimeException;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\Stream;
use UnexpectedValueException;

class VehiclesController
  extends Controller
{
  /**
   * Os métodos para manipular queries.
   */
  use QueryTrait;

  /**
   * Os métodos para manipular o recebimento de arquivos.
   */
  use HandleFileTrait;

  /**
   * As funções de formatação especiais
   */
  use FormatterTrait;

  /**
   * As regras de validação das informações de veículos.
   */
  use VehiclesValidationRulesTrait;

  /**
   * Os recursos necessários para o cadastro de veículos.
   */
  use VehiclesResourcesTrait;

  /**
   * Exibe a página inicial do gerenciamento de veículos.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   *
   * @return Response $response
   */
  public function show(Request $request, Response $response)
  {
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ERP\Home')
    );
    $this->breadcrumb->push('Cadastro', '');
    $this->breadcrumb->push('Veículos',
      $this->path('ERP\Cadastre\Vehicles')
    );

    // Registra o acesso
    $this->info("Acesso ao gerenciamento de veículos.");
    
    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();

    // Recupera as informações de depósitos
    $deposits = Deposit::where("contractorid", '=', $contractor->id)
      ->whereRaw("devicetype IN ('Equipment', 'Both')")
      ->orderBy('name')
      ->get([
          'depositid AS id',
          'name',
          'master'
        ])
    ;
    $defaultDepositID = $deposits[0]->id;
    foreach ($deposits as $deposit) {
      if ($deposit->master) {
        $defaultDepositID = $deposit->id;

        break;
      }
    }

    // Recupera as informações de técnicos
    $user = $this->authorization->getUser();
    if ($user->groupid < 5) {
      // Recuperamos todos os técnicos disponíveis
      $technicians = User::where("contractorid", '=', $contractor->id)
        ->where("groupid", '=', 5)
        ->orderBy('name')
        ->get([
            'userid AS id',
            'name'
          ])
      ;
    } else {
      // Recuperamos todos os técnicos da mesma empresa ao qual pertence
      // o usuário atual
      $technicians = User::where("contractorid", '=', $contractor->id)
        ->where("groupid", '=', 5)
        ->where("entityid", '=', $user->entityid)
        ->orderBy('name')
        ->get([
            'userid AS id',
            'name'
          ])
      ;
    }

    // Recupera as informações de prestadores de serviços
    if ($user->groupid < 5) {
      // Recuperamos todos os prestadores de serviços disponíveis
      $serviceproviders = Entity::where("contractorid",
            '=', $contractor->id
          )
        ->where("supplier", "true")
        ->where("serviceprovider", "true")
        ->orderBy('name')
        ->get([
            'entityid AS id',
            'name'
          ])
      ;
    } else {
      // Recuperamos o prestador de serviços no qual o usuário trabalha
      $serviceproviders = Entity::where("contractorid",
            '=', $contractor->id
          )
        ->where("supplier", "true")
        ->where("serviceprovider", "true")
        ->where("entityid", '=', $user->entityid)
        ->orderBy('name')
        ->get([
            'entityid AS id',
            'name'
          ])
      ;
    }

    // Recupera os dados da sessão
    $vehicle = $this->session->get('vehicle',
      [ 'searchField' => 'name',
        'searchValue' => '',
        'customer' => [
          'id' => 0,
          'name' => '',
          'subsidiaryID' => 0
        ],
        'filter' => [
          'type' => 0
        ],
        'displayStart' => 0
      ]
    );

    // Verifica se precisa limitar o que estamos exibindo em função
    // das permissões deste usuário
    if ($this->authorization->getUser()->groupid > 5) {
      // Recupera a informação do contratante
      $contractor = $this->authorization->getContractor();

      // Recuperamos o código do cliente
      $customerID = $this->authorization->getUser()->entityid;

      // Recuperamos os dados do cliente
      $customer = Entity::where('contractorid', '=', $contractor->id)
        ->where("entityid", '=', $customerID)
        ->where("customer", "true")
        ->get([
            'entityid AS id',
            'name'
          ])
        ->first()
      ;
      
      // Força a seleção dos dados deste cliente
      $vehicle['customer'] = [
        'id'   => $customer->id,
        'name' => $customer->name
      ];
    }

    $filters = [
      [ 'id' => 0,
        'name' => 'Todos veículos (ativos e inativos)'
      ],
      [ 'id' => 1,
        'name' => 'Veículos ativos'
      ],
      [ 'id' => 2,
        'name' => 'Veículos inativos'
      ]
    ];

    // Renderiza a página
    return $this->render($request, $response,
      'erp/cadastre/vehicles/vehicles.twig',
      [
        'vehicle' => $vehicle,
        'deposits' => $deposits,
        'defaultDepositID' => $defaultDepositID,
        'technicians' => $technicians,
        'serviceproviders' => $serviceproviders,
        'filters' => $filters
      ]
    );
  }

  /**
   * Recupera a relação dos veículos em formato JSON.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   *
   * @return Response $response
   */
  public function get(Request $request, Response $response)
  {
    // --------------------------[ Recupera os dados requisitados ]-----
    $contractorID = $this->authorization->getContractor()->id;

    // Recupera os dados da requisição
    $postParams = (array) $request->getParsedBody();

    if (isset($postParams['plate'])) {
      // Lida com a obtenção de dados de uma placa
      $includeInstalled = false;
      $plate = $postParams['plate'];

      $this->debug("Acesso à obtenção dos dados do veículo placa "
        . "{plate}.",
        [ 'plate' => $plate ]
      );

      // Obtemos os dados do veículo e do cliente ao qual está vinculado
      $vehicle = Vehicle::join('entities AS customer',
            'vehicles.customerid', '=', 'customer.entityid'
          )
        ->join('subsidiaries AS subsidiary', function($join) {
            $join->on('vehicles.customerid', '=',
              'subsidiary.entityid'
            );
            $join->on('vehicles.subsidiaryid', '=',
              'subsidiary.subsidiaryid'
            );
          })
        ->join('vehicletypes', 'vehicles.vehicletypeid',
            '=', 'vehicletypes.vehicletypeid'
          )
        ->join('vehiclebrands', 'vehicles.vehiclebrandid',
            '=', 'vehiclebrands.vehiclebrandid'
          )
        ->join('vehiclemodels', 'vehicles.vehiclemodelid',
            '=', 'vehiclemodels.vehiclemodelid'
          )
        ->leftJoin('vehiclesubtypes', 'vehiclemodels.vehiclesubtypeid',
            '=', 'vehiclesubtypes.vehiclesubtypeid'
          )
        ->join('vehiclecolors', 'vehicles.vehiclecolorid',
            '=', 'vehiclecolors.vehiclecolorid'
          )
        ->whereRaw("((vehicles.plate ILIKE '%%{$plate}%%') OR "
            . "(vehicles.plate ILIKE '%%' || "
            . " public.getPlateVariant('{$plate}') || '%%'))"
          )
        ->where('customer.contractorid', '=', $contractorID)
        ->get([
            'vehicles.*',
            'vehicles.customerid',
            'customer.name AS customername',
            'customer.entitytypeid',
            'customer.blocked AS customerblocked',
            'vehicles.subsidiaryid',
            'subsidiary.name AS subsidiaryname',
            'subsidiary.blocked AS subsidiaryblocked',
            $this->DB->raw(""
              . "CASE"
              . "  WHEN vehiclemodels.vehiclesubtypeid IS NULL THEN vehicletypes.name"
              . "  ELSE vehiclesubtypes.name "
              . "END AS vehicletypename"
            ),
            'vehiclebrands.name AS vehiclebrandname',
            'vehiclemodels.name AS vehiclemodelname',
            'vehiclecolors.name AS vehiclecolorname',
            'vehicles.blocked',
          ])
      ;

      if ( $vehicle->isEmpty() ) {
        // Retorna uma mensagem informando que não foi localizado os
        // dados do veículo através da placa
        $this->debug("Veículo placa {plate} não localizado.",
          [ 'plate' => $plate ]
        );

        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'result' => 'NOK',
              'params' => $request->getQueryParams(),
              'message' => 'Não foi localizado um veículo com a placa '
                . $plate,
              'data' => null
            ])
        ;
      }

      $vehicle = $vehicle
        ->first()
        ->toArray()
      ;

      // Agora recupera as informações de equipamento(s) já vinculado(s)
      // a este veículo
      $equipments = Equipment::join("equipmentmodels",
            "equipments.equipmentmodelid", '=',
            "equipmentmodels.equipmentmodelid"
          )
        ->join("equipmentbrands", "equipmentmodels.equipmentbrandid",
            '=', "equipmentbrands.equipmentbrandid"
          )
        ->join("entities AS customer", "equipments.customerpayerid",
            '=', "customer.entityid"
          )
        ->join("subsidiaries AS subsidiary", "equipments.subsidiarypayerid",
            '=', "subsidiary.subsidiaryid"
          )
        ->join("installations", "equipments.installationid",
            '=', "installations.installationid"
          )
        ->join("contracts", "installations.contractid",
            '=', "contracts.contractid"
          )
        ->join("plans", "installations.planid",
            '=', "plans.planid"
          )
        ->where("equipments.contractorid", '=', $contractorID)
        ->where("equipments.vehicleid", '=', $vehicle['vehicleid'])
        ->where("equipments.storagelocation", '=', 'Installed')
        ->get([
            'equipments.equipmentid',
            'equipments.serialnumber',
            'equipmentmodels.equipmentbrandid',
            'equipmentbrands.name AS equipmentbrandname',
            'equipments.equipmentmodelid',
            'equipmentmodels.name AS equipmentmodelname',
            'equipments.customerpayerid',
            'customer.entitytypeid AS payerentitytypeid',
            'customer.name AS customerpayername',
            'equipments.subsidiarypayerid',
            'subsidiary.name AS subsidiarypayername',
            'equipments.installedat',
            'equipments.installationid',
            $this->DB->raw('not(contracts.active) AS contractsuspended'),
            'installations.contractid',
            'installations.startdate',
            'plans.loyaltyperiod',
            $this->DB->raw(""
              . "CASE"
              . "  WHEN plans.loyaltyperiod > 0 AND installations.startdate IS NOT NULL THEN installations.startdate + plans.loyaltyperiod * interval '1 month'"
              . "  ELSE NULL  "
              . "END AS endloyaltyperiod"
            ),
            $this->DB->raw(""
              . "CASE"
              . "  WHEN contracts.notchargeloyaltybreak = TRUE THEN TRUE"
              . "  WHEN installations.notchargeloyaltybreak = TRUE THEN TRUE"
              . "  WHEN plans.loyaltyperiod > 0 AND installations.startdate IS NOT NULL THEN (installations.startdate + plans.loyaltyperiod * interval '1 month') < CURRENT_DATE"
              . "  ELSE FALSE "
              . "END AS disablechargeloyaltybreak"
            )
          ])
      ;

      if ( $equipments->isEmpty() ) {
        $vehicle['equipments'] = [];
      } else {
        $vehicle['equipments'] = $equipments
          ->toArray()
        ;
      }

      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getQueryParams(),
            'message' => 'Dados do veículo obtidos através da placa '
              . $plate,
            'data' => $vehicle
          ])
      ;
    }

    // Lida com as informações provenientes do Datatables
    $this->debug("Acesso à relação de veículos.");
    
    // O número da requisição sequencial
    $draw = $postParams['draw'];

    // Desativado pois não estamos mudando ordenamento
    // As definições das colunas
    // 
    // $columns = $postParams['columns'];
    // O ordenamento, onde:
    //   column: id da coluna
    //      dir: direção
    // $order    = $postParams['order'][0];
    // $orderBy  = $columns[$order['column']]['name'];
    // $orderDir = strtoupper($order['dir']);

    // A posição inicial (0 = início) da paginação
    $start = $postParams['start'];

    // O comprimento de cada página
    $length = $postParams['length'];

    // Lida com as informações adicionais de filtragem

    // O campo de pesquisa selecionado
    $searchField  = $postParams['searchField'];
    $searchValue  = trim($postParams['searchValue']);
    $customerID   = $postParams['customerID'];
    $customerName = $postParams['customerName'];
    $subsidiaryID = array_key_exists('subsidiaryID', $postParams)
      ? intval($postParams['subsidiaryID'])
      : 0
    ;

    $filterType = intval($request->getParam('filterType', 1));

    // Seta os valores da última pesquisa na sessão
    $this->session->set('vehicle',
      [ 'searchField' => $searchField,
        'searchValue' => $searchValue,
        'customer' => [
          'id' => $customerID,
          'name' => $customerName,
          'subsidiaryID' => $subsidiaryID
        ],
        'filter' => [
          'type' => $filterType
        ],
        'displayStart' => $start
      ]
    );

    // Corrige o escape dos campos
    $searchValue = addslashes($searchValue);

    try
    {
      // Verifica se precisa limitar o que estamos exibindo
      if ($this->authorization->getUser()->groupid > 5) {
        $customerID = $this->authorization->getUser()->entityid;
      } else {
        // Garante que tenhamos um ID válido dos campos de pesquisa
        $customerID = $customerID?$customerID:0;
      }

      // O filtro de elementos
      $Fstatus = 0;
      $Ftype   = 0;
      switch ($filterType) {
        case 1:
          // Apens veículos ativos
          $Fstatus = 2;

          break;
        case 2:
          // Apenas veículos inativos
          $Fstatus = 1;

          break;
        default:
          // code...
          break;
      }
      $this->debug('Status' . $Fstatus);

      // Realiza a consulta
      $sql = "SELECT E.vehicleID AS id,
                     E.customerID,
                     E.subsidiaryID,
                     E.associationID,
                     E.associationUnityID,
                     E.hasMonitoring,
                     E.juridicalperson,
                     E.cooperative,
                     E.headOffice,
                     E.type,
                     E.level,
                     E.active,
                     E.activeAssociation,
                     E.name,
                     E.tradingName,
                     E.ownerName,
                     E.blocked,
                     E.vehicleTypeName,
                     E.vehicleSubtypeID,
                     E.vehicleSubtypeName,
                     E.vehicleBrandName,
                     E.vehicleModelName,
                     E.vehicleColor,
                     E.active,
                     E.monitored,
                     E.withoutMainTracker,
                     E.blockedLevel,
                     erp.getMailStatusForVehicle(
                       {$contractorID}, E.customerID, E.vehicleID
                     ) AS sentMailStatus,
                     E.fullcount
                FROM erp.getVehiclesData({$contractorID}, {$customerID},
                  {$subsidiaryID}, 0, '{$searchValue}', '{$searchField}',
                  NULL, NULL, {$Fstatus}, {$Ftype}, {$start},
                  {$length}) AS E;"
      ;
      $vehicles = $this->DB->select($sql);
      
      if (count($vehicles) > 0) {
        $rowCount = $vehicles[0]->fullcount;

        // Para cada veículo, recupera os dados dos equipamentos
        foreach ($vehicles as $number => $vehicle) {
          if (intval($vehicle->type) === 3) {
            // Precisamos recuperar a informação dos equipamentos
            // associados com este veículo
            $sql = "SELECT EQPTO.equipmentID,
                           EQPTO.vehicleID,
                           EQPTO.brandName,
                           EQPTO.modelName,
                           EQPTO.imei,
                           EQPTO.serialNumber,
                           EQPTO.installationID,
                           EQPTO.installationNumber,
                           EQPTO.customerPayerID,
                           EQPTO.customerPayerName,
                           EQPTO.subsidiaryPayerID,
                           EQPTO.subsidiaryPayerName,
                           EQPTO.nationalRegister,
                           to_char(EQPTO.installedAt, 'DD/MM/YYYY') AS installedAt,
                           EQPTO.main,
                           EQPTO.installationSite,
                           EQPTO.hasBlocking,
                           EQPTO.blockingSite,
                           EQPTO.hasIButton,
                           EQPTO.iButtonSite,
                           EQPTO.hasSiren,
                           EQPTO.sirenSite,
                           EQPTO.panicButtonSite
                      FROM erp.getEquipmentsPerVehicleData({$contractorID},
                        {$vehicle->id}) AS EQPTO;"
            ;
            $equipmentsPerVehicle = (array) $this->DB->select($sql);
            $vehicles[$number]->equipmentdata = $equipmentsPerVehicle;
            $vehicles[$number]->amountofequipments =
              count($equipmentsPerVehicle)
            ;
          } else {
            $vehicles[$number]->equipmentdata = [];
            $vehicles[$number]->amountofequipments = 0;
          }
        }

        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'draw' => $draw,
              'recordsTotal' => $rowCount,
              'recordsFiltered' => $rowCount,
              'data' => $vehicles
            ])
        ;
      } else {
        if (empty($searchValue)) {
          $error = "Não temos veículos cadastrados.";
        } else {
          switch ($searchField) {
            case 'subsidiaryname':
              $error = "Não temos veículos cadastrados cujo nome da "
                . "unidade/filial contém <i>{$searchValue}</i>."
              ;
              
              break;
            case 'nationalregister':
              $error = "Não temos veículos cadastrados cujo CPF/CNPJ "
                . "da unidade/filial contém <i>{$searchValue}</i>."
              ;
              
              break;
            case 'name':
              $error = "Não temos veículos cadastrados cujo nome "
                . "contém <i>{$searchValue}</i>."
              ;
              
              break;
            case 'tradingname':
              $error = "Não temos veículos cadastrados cujo "
                . "apelido/nome fantasia contém <i>{$searchValue}</i>."
              ;
              
              break;
            default:
              $error = "Não temos veículos cadastrados que contém "
                . "<i>{$searchValue}</i>."
              ;

              break;
          }
        }
      }
    }
    catch(QueryException | Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno: {error}",
        [
          'module' => 'veículos',
          'error'  => $exception->getMessage()
        ]
      );

      $error = "Não foi possível recuperar as informações de veículos. "
        . "Erro interno."
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
   * Exibe um formulário para adição de um veículo, quando solicitado,
   * e confirma os dados enviados.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   *
   * @return Response $response
   */
  public function add(Request $request, Response $response)
  {
    try {
      // Recupera os dados do contratante
      $contractor = $this->authorization->getContractor();

      // Recupera as informações de tipos de veículos
      $vehicleTypes = $this->getVehiclesTypes();

      // Recupera as informações de subtipos de veículos
      $vehicleSubtypesPerType = $this->getVehicleSubtypes();

      // Recupera as informações de cores predominantes
      $vehicleColors = $this->getVehiclesColors();

      // Recupera as informações de combustíveis
      $fuelTypes = $this->getFuelTypes();

      // Recupera as informações de tipos de documentos
      $documentTypes = $this->getDocumentTypes();

      // Recupera as informações de tipos de telefones
      $phoneTypes = $this->getPhoneTypes();
    }
    catch (RuntimeException $exception)
    {
      // Alerta o usuário
      $this->flash(
        "error",
        $exception->getMessage()
      );

      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [
          'routeName' => 'ERP\Cadastre\Vehicles'
        ]
      );

      // Redireciona para a página de gerenciamento de veículos
      return $this->redirect($response, 'ERP\Cadastre\Vehicles');
    }

    // Verifica se estamos postando os dados
    if ($request->isPost()) {
      // Os dados estão sendo postados

      // Registra o acesso
      $this->debug("Processando à adição de veículo.");

      // Valida os dados
      $this->validator->validate($request,
        $this->getValidationRules(true)
      );

      if ($this->validator->isValid()) {
        $this->debug('Os dados do veículo são VÁLIDOS');

        // Recupera os dados do veículo
        $vehicleData = $this->validator->getValues();

        // Obtemos a informação do número do chassi, ano e modelo para
        // analisarmos outras condições
        $madeAt    = $vehicleData['yearfabr'];
        //$modelYear = $vehicleData['yearmodel'];
        $vin       = $vehicleData['vin'];

        if ($this->validVIN($madeAt, $vin)) {
          // Os arquivos anexados
          $attachmentsData = [ ];

          // Recupera o local de armazenamento dos anexos
          $uploadDirectory =
            $this->container['settings']['storage']['attachments']
              . DIRECTORY_SEPARATOR . $contractor->id
          ;

          try
          {
            // Verifica se não temos um veículo com a mesma placa, independente
            // do cliente ao qual ele está vinculado
            if (Vehicle::where("contractorid", '=', $contractor->id)
                  ->where("plate", $vehicleData['plate'])
                  ->count() === 0) {
              // Grava o novo veículo
              $this->info("Processando à adição do veículo placa "
                . "{plate}.",
                [ 'plate' => $vehicleData['plate'] ]
              );

              // Primeiramente lida com os documentos anexados

              // Recupera os arquivos enviados
              $uploadedFiles   = $request->getUploadedFiles();

              // Lida com uma entrada única que permite informar vários
              // arquivos anexados
              foreach ($uploadedFiles['attachments'] as $uploadedFile) {
                // Lida com cada arquivo anexado individualmente
                if ($this->fileHasBeenTransferred($uploadedFile)) {
                  // Move o arquivo para a pasta de armazenamento e
                  // armazena o nome do arquivo
                  $attachmentFilename = $this->moveFile(
                    $uploadDirectory, $uploadedFile
                  );

                  // Registra o arquivo armazenado
                  $attachmentsData[] = [
                    'filename' => $attachmentFilename,
                    'realfilename' => $uploadedFile->getClientFilename()
                  ];
                } else {
                  // Não foi enviado nenhum arquivo, então ignora
                }
              }

              // Analisa se o cliente é o proprietário do veículo
              if ($vehicleData['customeristheowner'] == "true") {
                // Retira as informações do proprietário, já que o
                // cliente é o próprio dono deste veículo
                unset($vehicleData['ownername']);
                unset($vehicleData['regionaldocumenttype']);
                unset($vehicleData['regionaldocumentnumber']);
                unset($vehicleData['regionaldocumentstate']);
                unset($vehicleData['nationalregister']);
                unset($vehicleData['address']);
                unset($vehicleData['district']);
                unset($vehicleData['postalcode']);
                unset($vehicleData['cityname']);
                unset($vehicleData['cityid']);
                unset($vehicleData['state']);
                $ownerPhonesData = [];
              } else {
                // Separamos as informações dos dados de telefones dos
                // demais dados desta unidade/filial
                $ownerPhonesData = $vehicleData['ownerPhones'];
              }
              unset($vehicleData['ownerPhones']);

              // Determina o local de permanência do veículo
              $vehicleData['atsamecustomeraddress'] =
                ($vehicleData['placeOfStay'] === 'atsamecustomeraddress')
                  ? true
                  : false
              ;
              $vehicleData['atsameowneraddress'] =
                ($vehicleData['placeOfStay'] === 'atsameowneraddress')
                  ? true
                  : false
              ;
              $vehicleData['atanotheraddress'] =
                ($vehicleData['placeOfStay'] === 'atanotheraddress')
                  ? true
                  : false
              ;

              if ($vehicleData['placeOfStay'] !== 'atanotheraddress') {
                // Limpamos os dados do endereço alternativo
                $vehicleData['anothername'] = null;
                $vehicleData['anotheraddress'] = null;
                $vehicleData['anotherstreetnumber'] = null;
                $vehicleData['anothercomplement'] = null;
                $vehicleData['anotherdistrict'] = null;
                $vehicleData['anotherpostalcode'] = null;
                $vehicleData['anothercityid'] = null;
                $anotherPhonesData = [];
              } else {
                // Separamos as informações dos dados de telefones dos
                // demais dados desta unidade/filial
                $anotherPhonesData = $vehicleData['anotherPhones'];
              }
              unset($vehicleData['anotherPhones']);

              // Iniciamos a transação
              $this->DB->beginTransaction();

              // Analisamos se o modelo do veículo foi informado
              if (intval($vehicleData['vehiclemodelid']) === 0) {
                $this->info('Adicionando o novo modelo de veículo '
                  . '{model}',
                  [ 'model', $vehicleData['vehiclemodelname'] ]
                );
                // Precisamos adicionar o modelo do veículo antes de
                // concluir a edição do veículo
                $vehicleModelData = [
                  'vehicletypeperbrandid' => 0,
                  'vehicletypeid' => $vehicleData['vehicletypeid'],
                  'vehiclesubtypeid' => $vehicleData['vehiclesubtypeid'],
                  'vehiclebrandid' => $vehicleData['vehiclebrandid'],
                  'name' => $vehicleData['vehiclemodelname'],
                  'fipeid' => 0,
                ];

                // Determinamos o código do tipo de veículo por marca
                $vehicleTypePerBrands = VehicleTypePerBrand::where(
                      "contractorid", '=', $contractor->id
                    )
                  ->where("vehiclebrandid", '=', $vehicleData['vehiclebrandid'])
                  ->where("vehicletypeid", '=', $vehicleData['vehicletypeid'])
                  ->get([
                      'vehicletypeperbrandid'
                    ])
                ;

                if ( $vehicleTypePerBrands->isEmpty() ) {
                  // Adicionamos o tipo de veículo fabricado por esta
                  // marca
                  $vehicleTypePerBrand = new VehicleTypePerBrand();
                  $vehicleTypePerBrand->contractorid = $contractor->id;
                  $vehicleTypePerBrand->vehicletypeid = $vehicleData['vehicletypeid'];
                  $vehicleTypePerBrand->vehiclebrandid = $vehicleData['vehiclebrandid'];
                  $vehicleTypePerBrand->fipeid = 0;
                  $vehicleTypePerBrand->save();

                  // Informa o ID do tipo de veículo fabricado por esta
                  // marca
                  $vehicleModelData['vehicletypeperbrandid'] = $vehicleTypePerBrand->vehicletypeperbrandid;
                } else {
                  $vehicleTypePerBrand = $vehicleTypePerBrands
                    ->first()
                  ;
                  $vehicleModelData['vehicletypeperbrandid'] = $vehicleTypePerBrand->vehicletypeperbrandid;
                }
                
                // Grava a novo modelo de veículo
                $vehicleModel = new VehicleModel();
                $vehicleModel->fill($vehicleModelData);
                if (intval($vehicleModelData['vehiclesubtypeid']) === 0) {
                  $vehicleModel->vehiclesubtypeid = null;
                }
                // Adiciona o contratante
                $vehicleModel->contractorid = $contractor->id;
                $vehicleModel->save();

                // Informa o ID do novo modelo de veículo
                $vehicleData['vehiclemodelid'] = $vehicleModel->vehiclemodelid;
              } else {
                // Obtemos o subtipo do modelo do veículo
                $vehicleModel = VehicleModel::findOrFail(
                  $vehicleData['vehiclemodelid']
                );

                // Verifica se o subtipo do veículo foi modificado
                if ( (is_null($vehicleModel->vehiclesubtypeid)) &&
                     ($vehicleData['vehiclesubtypeid'] > 0) ) {
                  $this->debug('Atualizar subtipo do modelo de veículo');
                  // Foi informado o subtipo do modelo do veículo, então
                  // fazemos a atualização do modelo
                  $vehicleModel->vehiclesubtypeid = $vehicleData['vehiclesubtypeid'];
                  $vehicleModel->save();
                }
              }

              // Incluímos um novo veículo
              $vehicle = new Vehicle();
              $vehicle->fill($vehicleData);
              // Adicionamos as informações do contratante
              $vehicle->contractorid = $contractor->id;
              $vehicle->createdbyuserid =
                $this->authorization->getUser()->userid
              ;
              $vehicle->updatedbyuserid =
                $this->authorization->getUser()->userid
              ;
              $vehicle->save();
              $vehicleID = $vehicle->vehicleid;

              // Incluímos todos os documentos anexados deste veículo
              foreach($attachmentsData AS $attachment) {
                // Incluímos um documento anexado deste veículo
                $vehicleattachment = new VehicleAttachment();
                $vehicleattachment->filename =
                  $attachment['filename']
                ;
                $vehicleattachment->realfilename =
                  $attachment['realfilename']
                ;
                $vehicleattachment->vehicleid = $vehicleID;
                $vehicleattachment->contractorid = $contractor->id;
                $vehicleattachment->save();
              }

              // Incluímos os dados de telefones do proprietário deste
              // veículo, se disponíveis
              foreach($ownerPhonesData as $phoneData)
              {
                // Retiramos o campo de ID do telefone, pois os
                // dados tratam de um novo registro
                unset($phoneData['onwerphoneid']);

                // Incluímos um novo telefone desta unidade/filial
                $phone = new OwnerPhone();
                $phone->fill($phoneData);
                $phone->vehicleid = $vehicleID;
                $phone->save();
              }

              // Incluímos os dados de telefones do local de permanência
              // deste veículo, se disponíveis
              foreach($anotherPhonesData as $phoneData)
              {
                // Retiramos o campo de ID do telefone, pois os
                // dados tratam de um novo registro
                unset($phoneData['anotherphoneid']);

                // Incluímos um novo telefone desta unidade/filial
                $phone = new AnotherPhone();
                $phone->fill($phoneData);
                $phone->vehicleid = $vehicleID;
                $phone->save();
              }

              // Efetiva a transação
              $this->DB->commit();

              // Registra o sucesso
              $this->info("Cadastrado o veículo placa '{plate}' com "
                . "sucesso.",
                [
                  'plate'  => $vehicleData['plate']
                ]
              );

              // Alerta o usuário
              $this->flash(
                "success",
                "O veículo <i>'{plate}'</i> foi cadastrado com sucesso.",
                [
                  'plate'  => $vehicleData['plate']
                ]
              );

              // Registra o evento
              $this->debug("Redirecionando para {routeName}",
                [
                  'routeName' => 'ERP\Cadastre\Vehicles'
                ]
              );

              // Redireciona para a página de gerenciamento de veículos
              return $this->redirect(
                $response,
                'ERP\Cadastre\Vehicles'
              );
            } else {
              // Registra o erro
              $this->debug("Não foi possível inserir as informações do "
                . "veículo placa '{plate}'. Já existe outro veículo "
                . "com a mesma placa.",
                [
                  'plate'  => $vehicleData['plate']
                ]
              );

              // Remove os arquivos anexados, caso ainda não tenham sido
              // removidos
              foreach ($attachmentsData as $attachment) {
                // Apaga os arquivos enviados/criados
                $this->deleteFile($uploadDirectory,
                  $attachment['filename']
                );
              }

              // Alerta o usuário
              $this->flashNow(
                "error",
                "Já existe um veículo com a placa <i>'{plate}'</i>.",
                [
                  'plate' => $vehicleData['plate']
                ]
              );
            }
          }
          catch(UploadFileException $exception)
          {
            // Reverte (desfaz) a transação
            $this->DB->rollBack();

            // Registra o erro
            $this->error("Não foi possível inserir as informações do "
              . "veículo placa '{plate}'. {error}",
              [
                'plate'  => $vehicleData['plate'],
                'error' => $exception->getMessage()
              ]
            );

            // Remove os arquivos anexados, caso ainda não tenham sido
            // removidos
            foreach ($attachmentsData as $attachment) {
              // Apaga os arquivos enviados/criados
              $this->deleteFile($uploadDirectory,
                $attachment['filename']
              );
            }

            // Alerta o usuário
            $this->flashNow(
              "error",
              "Não foi possível inserir as informações do veículo. "
              . "{error}",
              [
                'error' => $exception->getMessage()
              ]
            );
          }
          catch(QueryException | Exception $exception)
          {
            // Reverte (desfaz) a transação
            $this->DB->rollBack();

            // Remove os arquivos anexados, caso ainda não tenham sido
            // removidos
            foreach ($attachmentsData as $attachment) {
              // Apaga os arquivos enviados/criados
              $this->deleteFile($uploadDirectory,
                $attachment['filename']
              );
            }

            // Registra o erro
            $this->error("Não foi possível inserir as informações do "
              . "veículo placa '{plate}'. Erro interno: {error}",
              [
                'plate'  => $vehicleData['plate'],
                'error' => $exception->getMessage()
              ]
            );

            // Alerta o usuário
            $this->flashNow(
              "error",
              "Não foi possível inserir as informações do veículo. "
              . "Erro interno."
            );
          }
        } else {
          // Registra o erro
          $this->debug("Não foi possível inserir as informações do "
            . "veículo placa '{plate}'. O número do chassi não é "
            . "válido.",
            [
              'plate'  => $vehicleData['plate']
            ]
          );

          // Seta o erro neste campo
          $this->validator->setErrors([
              'vin' => ((strlen($vehicleData['vin']) < 17)
                ? 'O número do chassi precisa ter 17 dígitos'
                : 'O número do chassi é inválido')
            ],
            "vin")
          ;
        }
      } else {
        $this->debug('Os dados do veículo são INVÁLIDOS');
        $messages = $this->validator->getFormatedErrors();
        foreach ($messages AS $message) {
          $this->debug($message);
        }
      }
    } else {
      // Carrega os dados iniciais para simplificar a digitação
      $values = [
        'vehiclemodelid' => 0,
        'vehiclemodelname' => '',
        'vehiclebrandid' => 0,
        'vehiclebrandname' => '',
        'vehicletypeid' => 0,
        'vehicletypename' => '',
        'vehiclesubtypeid' => 0,
        'vehiclesubtypename' => '',
        'regionaldocumenttype' => 4,
        'cityid' => 0,
        'cityname' => '',
        'state' => '',
        'ownerPhones' => [[
          'ownerphoneid' => 0,
          'phonenumber' => '',
          'phonetypeid' => 1
        ]],
        'anotherPhones' => [[
          'anotherphoneid' => 0,
          'phonenumber' => '',
          'phonetypeid' => 1
        ]],
        'placeOfStay' => 'atsamecustomeraddress',
        'anothercityid' => 0,
        'anothercityname' => '',
        'anotherstate' => ''
      ];

      if ($this->authorization->getUser()->groupid > 5) {
        // Recuperamos a empresa à qual o usuário atual está vinculado
        $customerID = $this->authorization->getUser()->entityid;

        $customer = Entity::join('subsidiaries', 'entities.entityid',
              '=', 'subsidiaries.entityid'
            )
          ->where("entities.customer", "true")
          ->where('entities.entityid', $customerID)
          ->get([
              'entities.name',
              'subsidiaries.subsidiaryid AS subsidiaryid',
              'subsidiaries.name AS subsidiaryname'
            ])
          ->first()
        ;

        // Adicionamos a informação da empresa
        $values['customerid']   = $customerID;
        $values['customername'] = $customer->name;
        $values['subsidiaryid']   = $customer->subsidiaryid;
        $values['subsidiaryname'] = $customer->subsidiaryname;
      };

      $this->validator->setValues($values);
    }

    // Exibe um formulário para adição de um veículo

    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ERP\Home')
    );
    $this->breadcrumb->push('Cadastro', '');
    $this->breadcrumb->push('Veículos',
      $this->path('ERP\Cadastre\Vehicles')
    );
    $this->breadcrumb->push('Adicionar',
      $this->path('ERP\Cadastre\Vehicles\Add')
    );

    // Registra o acesso
    $this->info("Acesso à adição de veículo.");

    return $this->render($request, $response,
        'erp/cadastre/vehicles/vehicle.twig',
        [ 'formMethod' => 'POST',
          'vehicleTypes' => $vehicleTypes,
          'vehicleSubtypesPerType' => $vehicleSubtypesPerType,
          'vehicleColors' => $vehicleColors,
          'fuelTypes' => $fuelTypes,
          'documentTypes' => $documentTypes,
          'phoneTypes' => $phoneTypes ]
      )
    ;
  }

  /**
   * Exibe um formulário para edição de um veículo, quando solicitado,
   * e confirma os dados enviados.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   * @param array $args
   *   Os argumentos da requisição
   *
   * @return Response $response
   */
  public function edit(Request $request, Response $response,
    array $args)
  {
    $vehicleID = $args['vehicleID'];

    try {
      // Recupera os dados do contratante
      $contractor = $this->authorization->getContractor();

      // Recupera as informações de tipos de veículos
      $vehicleTypes = $this->getVehiclesTypes();

      // Recupera as informações de subtipos de veículos
      $vehicleSubtypesPerType = $this->getVehicleSubtypes();

      // Recupera as informações de cores predominantes
      $vehicleColors = $this->getVehiclesColors();

      // Recupera as informações de combustíveis
      $fuelTypes = $this->getFuelTypes();

      // Recupera as informações de tipos de documentos
      $documentTypes = $this->getDocumentTypes();

      // Recupera as informações de tipos de telefones
      $phoneTypes = $this->getPhoneTypes();
    }
    catch (RuntimeException $exception)
    {
      // Alerta o usuário
      $this->flash("error", $exception->getMessage());

      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [
          'routeName' => 'ERP\Cadastre\Vehicles'
        ]
      );

      // Redireciona para a página de gerenciamento de veículos
      return $this->redirect($response, 'ERP\Cadastre\Vehicles');
    }

    try
    {
      // Inicializa as variáveis que irão armazenar os contratos e itens
      // de contrato dos responsáveis pelos equipamentos
      $contracts = [];
      $installations = [];

      // Recupera as informações do veículo
      $vehicle = Vehicle::join('entities AS customer',
            'vehicles.customerid', '=', 'customer.entityid'
          )
        ->join('subsidiaries AS subsidiary', function($join) {
            $join->on('vehicles.customerid', '=',
              'subsidiary.entityid'
            );
            $join->on('vehicles.subsidiaryid', '=',
              'subsidiary.subsidiaryid'
            );
          })
        ->join('vehicletypes', 'vehicles.vehicletypeid',
            '=', 'vehicletypes.vehicletypeid'
          )
        ->join('vehiclebrands', 'vehicles.vehiclebrandid',
            '=', 'vehiclebrands.vehiclebrandid'
          )
        ->join('vehiclemodels', 'vehicles.vehiclemodelid',
            '=', 'vehiclemodels.vehiclemodelid'
          )
        ->leftJoin('vehiclesubtypes', 'vehiclemodels.vehiclesubtypeid',
            '=', 'vehiclesubtypes.vehiclesubtypeid'
          )
        ->join('vehiclecolors', 'vehicles.vehiclecolorid',
            '=', 'vehiclecolors.vehiclecolorid'
          )
        ->join('fueltypes', 'vehicles.fueltype',
            '=', 'fueltypes.fueltype'
          )
        ->leftJoin('documenttypes', 'vehicles.regionaldocumenttype',
            '=', 'documenttypes.documenttypeid'
          )
        ->leftJoin('cities', 'vehicles.cityid',
            '=', 'cities.cityid'
          )
        ->leftJoin('cities AS anothercity', 'vehicles.anothercityid',
            '=', 'anothercity.cityid'
          )
        ->join('users AS createduser', 'vehicles.createdbyuserid',
            '=', 'createduser.userid'
          )
        ->join('users AS updateduser', 'vehicles.updatedbyuserid',
            '=', 'updateduser.userid'
          )
        ->where('vehicles.vehicleid', $vehicleID)
        ->where('customer.contractorid', '=', $contractor->id)
        ->get([
            'vehicles.*',
            'vehicles.customerid AS originalcustomerid',
            'vehicles.subsidiaryid AS originalsubsidiaryid',
            'customer.name AS customername',
            'customer.entitytypeid',
            'subsidiary.name AS subsidiaryname',
            'vehicletypes.name AS vehicletypename',
            $this->DB->raw(""
              . "CASE"
              . "  WHEN vehiclemodels.vehiclesubtypeid IS NULL THEN 0"
              . "  ELSE vehiclemodels.vehiclesubtypeid "
              . "END AS vehiclesubtypeid"
            ),
            $this->DB->raw(""
              . "CASE"
              . "  WHEN vehiclemodels.vehiclesubtypeid IS NULL THEN 'Não informado'"
              . "  ELSE vehiclesubtypes.name "
              . "END AS vehiclesubtypename"
            ),
            //$this->DB->raw("TO_CHAR(CURRENT_DATE, 'DD-MM-YYYY') AS transferat"),
            'vehiclebrands.name AS vehiclebrandname',
            'vehiclemodels.name AS vehiclemodelname',
            'vehiclecolors.name AS vehiclecolorname',
            'fueltypes.name AS fueltypename',
            'documenttypes.name AS regionaldocumenttypename',
            'cities.name AS cityname',
            'cities.state',
            'anothercity.name AS anothercityname',
            'anothercity.state AS anotherstate',
            'createduser.name AS createdbyusername',
            'updateduser.name AS updatedbyusername'
          ])
      ;

      if ( $vehicle->isEmpty() ) {
        throw new ModelNotFoundException("Não temos nenhum veículo com "
          . "o código {$vehicleID} cadastrado"
        );
      }

      $vehicle = $vehicle
        ->first()
        ->toArray()
      ;

      // Agora recupera as informações de equipamento(s) já vinculado(s)
      // a este veículo
      $equipments = Equipment::join("equipmentmodels",
            "equipments.equipmentmodelid", '=',
            "equipmentmodels.equipmentmodelid"
          )
        ->join("equipmentbrands", "equipmentmodels.equipmentbrandid",
            '=', "equipmentbrands.equipmentbrandid"
          )
        ->join("entities AS customer", "equipments.customerpayerid",
            '=', "customer.entityid"
          )
        ->join("subsidiaries AS subsidiary", "equipments.subsidiarypayerid",
            '=', "subsidiary.subsidiaryid"
          )
        ->join("installations", "equipments.installationid",
            '=', "installations.installationid"
          )
        ->join("contracts", "installations.contractid",
            '=', "contracts.contractid"
          )
        ->join("plans", "installations.planid",
            '=', "plans.planid"
          )
        ->where("equipments.contractorid", '=', $contractor->id)
        ->where("equipments.vehicleid", '=', $vehicleID)
        ->where("equipments.storagelocation", '=', 'Installed')
        ->get([
            'equipments.equipmentid',
            'equipments.serialnumber',
            'equipmentmodels.equipmentbrandid',
            'equipmentbrands.name AS equipmentbrandname',
            'equipments.equipmentmodelid',
            'equipmentmodels.name AS equipmentmodelname',
            'equipments.customerpayerid',
            'equipments.customerpayerid AS originalcustomerpayerid',
            'customer.entitytypeid AS payerentitytypeid',
            'customer.name AS customerpayername',
            'equipments.subsidiarypayerid',
            'equipments.subsidiarypayerid AS originalsubsidiarypayerid',
            'subsidiary.name AS subsidiarypayername',
            'equipments.installedat',
            'equipments.installationid',
            $this->DB->raw('not(contracts.active) AS contractsuspended'),
            'installations.contractid',
            'installations.startdate',
            'plans.loyaltyperiod',
            $this->DB->raw(""
              . "CASE"
              . "  WHEN plans.loyaltyperiod > 0 AND installations.startdate IS NOT NULL THEN (installations.startdate + plans.loyaltyperiod * interval '1 month')::date"
              . "  ELSE NULL  "
              . "END AS endloyaltyperiod"
            ),
            $this->DB->raw("true AS notchargeloyaltybreak"),
            $this->DB->raw(""
              . "CASE"
              . "  WHEN contracts.notchargeloyaltybreak = TRUE THEN TRUE"
              . "  WHEN installations.notchargeloyaltybreak = TRUE THEN TRUE"
              . "  WHEN plans.loyaltyperiod > 0 AND installations.startdate IS NOT NULL THEN (installations.startdate + plans.loyaltyperiod * interval '1 month') < CURRENT_DATE"
              . "  ELSE FALSE "
              . "END AS disablechargeloyaltybreak"
            )
          ])
      ;

      if ( $equipments->isEmpty() ) {
        $vehicle['equipments'] = [];
      } else {
        $vehicle['equipments'] = $equipments
          ->toArray()
        ;

        // Para cada equipamento, recupera a relação de contratos do
        // cliente pagante
        foreach ($vehicle['equipments'] as $count => $equipment) {
          $customerID     = $equipment['customerpayerid'];
          $subsidiaryID   = $equipment['subsidiarypayerid'];
          $contractID     = $equipment['contractid'];
          $installationID = $equipment['installationid'];
          
          // Recupera as informações de contratos do cliente pagante
          if (array_key_exists($customerID, $contracts)) {
            if (! array_key_exists($subsidiaryID, $contracts[$customerID]) ) {
              // Obtemos os contratos deste cliente nesta unidade/filial
              $contracts[$customerID][$subsidiaryID] =
                $this->getContracts(
                  $customerID,
                  $subsidiaryID,
                  false,
                  $contractID
                )
              ;
            }
          } else {
            // Obtemos os contratos deste cliente
            $contracts[$customerID] = [];
            $contracts[$customerID][$subsidiaryID] =
              $this->getContracts(
                $customerID,
                $subsidiaryID,
                false,
                $contractID
              )
            ;
          }

          // Recupera as informações dos itens do contrato
          if (! array_key_exists($contractID, $installations) ) {
            $installations[$contractID] =
              $this->getInstallations(
                $contractID,
                false,
                $installationID
              )
            ;
          }

          // Informa a quantidade de contratos deste cliente
          $amountOfPayerContracts = count(
            $contracts[$customerID][$subsidiaryID]
          );
          $vehicle['equipments'][$count]['amountOfPayerContracts'] = 
            $amountOfPayerContracts
          ;

          // Informa a quantidade de itens no contrato do pagante do
          // equipamento
          $amountOfItensInContract = count(
            $installations[$contractID]
          );
          $vehicle['equipments'][$count]['amountOfItensInContract'] = 
            $amountOfItensInContract
          ;

          // Indica se o contrato deve ser encerrado
          $vehicle['equipments'][$count]['terminate'] = 
            (($amountOfPayerContracts == 1) && ($amountOfItensInContract == 1))
          ;
        }
      }

      // Agora recupera as informações de documentos anexados
      $vehicle['attachments'] = VehicleAttachment::where('vehicleid',
            $vehicleID
          )
        ->where('contractorid', '=', $contractor->id)
        ->get([
            'vehicleattachmentid AS id',
            'filename',
            'realfilename'
          ])
        ->toArray()
      ;

      // Recupera o local de armazenamento dos anexos
      $uploadDirectory =
        $this->container['settings']['storage']['attachments']
        . DIRECTORY_SEPARATOR . $contractor->id
      ;

      // Para cada documento, recupera seu respectivo tamanho
      foreach ($vehicle['attachments'] as $pos => $attachment) {
        $attachmentFile = $uploadDirectory . DIRECTORY_SEPARATOR
          . $attachment['filename']
        ;

        // Recupera o tipo Mime do arquivo
        $mimeType = mime_content_type($attachmentFile);

        // Conforme o tipo do arquivo, permitimos o zoom
        switch ($mimeType) {
          case "image/png":
          case "image/jpeg":
            $vehicle['attachments'][$pos]['zoom'] = true;
            $vehicle['attachments'][$pos]['pdf'] = true;

            break;
          case "application/pdf":
            $vehicle['attachments'][$pos]['zoom'] = false;
            $vehicle['attachments'][$pos]['pdf'] = true;

            break;
          default;
            $vehicle['attachments'][$pos]['zoom'] = false;
            $vehicle['attachments'][$pos]['pdf'] = false;
        }

        // Determina o tamanho do arquivo
        $vehicle['attachments'][$pos]['size'] = $this->humanFilesize(
          filesize($attachmentFile)
        );
      }

      // Precisamos acrescentar as informações de telefones do
      // proprietário, se necessário
      $ownerPhones = OwnerPhone::join('phonetypes',
            'ownerphones.phonetypeid', '=', 'phonetypes.phonetypeid'
          )
        ->where('vehicleid', $vehicleID)
        ->get([
            'ownerphones.ownerphoneid',
            'ownerphones.phonetypeid',
            'phonetypes.name as phonetypename',
            'ownerphones.phonenumber'
          ])
      ;
      if ( $ownerPhones->isEmpty() ) {
        // Criamos os dados de telefone em branco
        $vehicle['ownerPhones'] = [
          [
            'ownerphoneid' => 0,
            'phonetypeid' => 1,
            'phonenumber' => ''
          ]
        ];
      } else {
        $vehicle['ownerPhones'] =
          $ownerPhones ->toArray()
        ;
      }

      // Precisamos acrescentar as informações de telefones do
      // outro local
      $anotherPhones = AnotherPhone::join('phonetypes',
            'anotherphones.phonetypeid', '=', 'phonetypes.phonetypeid'
          )
        ->where('vehicleid', $vehicleID)
        ->get([
            'anotherphones.anotherphoneid',
            'anotherphones.phonetypeid',
            'phonetypes.name as phonetypename',
            'anotherphones.phonenumber'
          ])
      ;
      if ( $anotherPhones->isEmpty() ) {
        // Criamos os dados de telefone em branco
        $vehicle['anotherPhones'] = [
          [
            'anotherphoneid' => 0,
            'phonetypeid' => 1,
            'phonenumber' => ''
          ]
        ];
      } else {
        $vehicle['anotherPhones'] =
          $anotherPhones ->toArray()
        ;
      }
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar o veículo código "
        . "{vehicleID}.",
        [
          'vehicleID' => $vehicleID
        ]
      );

      // Alerta o usuário
      $this->flash("error", "Não foi possível localizar este veículo.");

      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [
          'routeName' => 'ERP\Cadastre\Vehicles'
        ]
      );

      // Redireciona para a página de gerenciamento de veículos
      return $this->redirect($response, 'ERP\Cadastre\Vehicles');
    }

    // Verifica se estamos modificando os dados
    if ($request->isPut()) {
      // Os dados estão sendo modificados

      // Registra o acesso
      $this->info("Processando à edição do veículo placa '{plate}'.",
        [
          'plate' => $vehicle['plate']
        ]
      );

      // Valida os dados
      $this->validator->validate($request,
        $this->getValidationRules(false)
      );

      if ($this->validator->isValid()) {
        $this->debug('Os dados do veículo são VÁLIDOS');

        // Recupera os dados modificados do veículo
        $vehicleData = $this->validator->getValues();

        // Obtemos a informação do número do chassi, ano e modelo para
        // analisarmos outras condições
        $madeAt    = $vehicleData['yearfabr'];
        //$modelYear = $vehicleData['yearmodel'];
        $vin       = $vehicleData['vin'];

        if ($this->validVIN($madeAt, $vin)) {
          // Os arquivos anexados
          $attachmentsData = [ ];

          // Recupera o local de armazenamento dos anexos
          $uploadDirectory =
            $this->container['settings']['storage']['attachments']
            . DIRECTORY_SEPARATOR . $contractor->id
          ;

          try
          {
            // Verifica se a placa foi modificada
            $proceed = false;
            if ($vehicleData['plate'] == $vehicle['plate']) {
              $proceed = true;
            } else {
              // Verifica não temos um veículo com a mesma placa,
              // independente do cliente ao qual ele está vinculado
              if (Vehicle::where("contractorid", '=', $contractor->id)
                    ->where("plate", $vehicleData['plate'])
                    ->count() === 0) {
                $proceed = true;
              } else {
                // Registra o erro
                $this->debug(
                  "Não foi possível modificar as informações do "
                  . "veículo da placa '{plate}' para a placa "
                  . "'{newplate}'. Já existe outro veículo com a "
                  . "mesma placa.",
                  [
                    'plate'    => $vehicle['plate'],
                    'newplate' => $vehicleData['plate']
                  ]
                );

                // Alerta o usuário
                $this->flashNow(
                  "error",
                  "Já existe um veículo com a placa <i>'{plate}'</i>.",
                  [
                    'plate' => $vehicleData['plate']
                  ]
                );
              }
            }

            if ($proceed) {
              // Grava as modificações dos dados do veículo

              // Primeiramente lida com os documentos anexados

              // Recupera os arquivos enviados
              $uploadedFiles   = $request->getUploadedFiles();

              // Lida com uma entrada única que permite informar vários
              // arquivos anexados
              foreach ($uploadedFiles['attachments'] as $uploadedFile) {
                // Lida com cada arquivo anexado individualmente
                if ($this->fileHasBeenTransferred($uploadedFile)) {
                  // Move o arquivo para a pasta de armazenamento e
                  // armazena o nome do arquivo
                  $attachmentFilename = $this->moveFile(
                    $uploadDirectory, $uploadedFile
                  );

                  // Registra o arquivo armazenado
                  $attachmentsData[] = [
                    'filename' => $attachmentFilename,
                    'realfilename' =>
                      $uploadedFile->getClientFilename()
                  ];
                }
              }

              // Analisa se o cliente é o proprietário do veículo
              if ($vehicleData['customeristheowner'] === "true") {
                // Retira as informações do proprietário, já que o
                // cliente é o próprio dono deste veículo
                unset($vehicleData['ownername']);
                unset($vehicleData['regionaldocumenttype']);
                unset($vehicleData['regionaldocumentnumber']);
                unset($vehicleData['regionaldocumentstate']);
                unset($vehicleData['nationalregister']);
                unset($vehicleData['address']);
                unset($vehicleData['district']);
                unset($vehicleData['postalcode']);
                unset($vehicleData['cityname']);
                unset($vehicleData['cityid']);
                unset($vehicleData['state']);
                $ownerPhonesData = [];
              } else {
                // Separamos as informações dos dados de telefones dos
                // demais dados desta unidade/filial
                $ownerPhonesData = $vehicleData['ownerPhones'];
              }
              unset($vehicleData['ownerPhones']);

              // Determina o local de permanência do veículo
              $vehicleData['atsamecustomeraddress'] =
                ($vehicleData['placeOfStay'] === 'atsamecustomeraddress')
                  ? true
                  : false
              ;
              $vehicleData['atsameowneraddress'] =
                ($vehicleData['placeOfStay'] === 'atsameowneraddress')
                  ? true
                  : false
              ;
              $vehicleData['atanotheraddress'] =
                ($vehicleData['placeOfStay'] === 'atanotheraddress')
                  ? true
                  : false
              ;

              if ($vehicleData['placeOfStay'] !== 'atanotheraddress') {
                // Limpamos os dados do endereço alternativo
                $vehicleData['anothername'] = null;
                $vehicleData['anotheraddress'] = null;
                $vehicleData['anotherstreetnumber'] = null;
                $vehicleData['anothercomplement'] = null;
                $vehicleData['anotherdistrict'] = null;
                $vehicleData['anotherpostalcode'] = null;
                $vehicleData['anothercityid'] = null;
                $anotherPhonesData = [];
              } else {
                // Separamos as informações dos dados de telefones dos
                // demais dados desta unidade/filial
                $anotherPhonesData = $vehicleData['anotherPhones'];
              }
              unset($vehicleData['anotherPhones']);

              // Separamos as informações dos equipamentos de
              // rastreamento do restante dos dados do veículo
              $equipmentsData = $vehicleData['equipments'];
              unset($vehicleData['equipments']);

              // Separamos a informação do bloqueio do envio de
              // mensagens de aviso do restante dos dados
              $blockNotices = $vehicleData['blocknotices'];
              if ($blockNotices === "true") {
                // Determina os valores de bloqueio
                $startAt = empty($vehicleData['blockedstartat'])
                  ? Carbon::today()->startOfDay()
                  : Carbon::createFromFormat('d/m/Y H:i:s',
                      $vehicleData['blockedstartat'] . ' 00:00:00'
                    )
                ;
                $blockedStartAt = "'{$startAt->format('Y-m-d')}'";
                $blockedDays = intval($vehicleData['blockeddays']);
                if ($blockedDays > 0) {
                  $endAt = $startAt
                    ->copy()
                    ->addDays($blockedDays)
                  ;
                  $blockedEndAt = "'{$endAt->format('Y-m-d')}'";
                  $remainingDays = $endAt->diffInDays($startAt);
                } else {
                  $blockedEndAt = 'NULL';
                  $remainingDays = 0;
                }
              } else {
                // Deixa os valores zerados
                $blockedDays = 'NULL';
                $remainingDays = 0;
                $blockedStartAt = 'NULL';
                $blockedEndAt = 'NULL';
              }
              unset($vehicleData['blocknotices']);
              unset($vehicleData['blockeddays']);
              unset($vehicleData['blockedstartat']);

              // Iniciamos a transação
              $this->DB->beginTransaction();

              $userID = $this->authorization->getUser()->userid;

              // Analisamos se o modelo do veículo foi informado
              if (intval($vehicleData['vehiclemodelid']) === 0) {
                $this->debug('Adicionando novo modelo de veículo');
                // Precisamos adicionar o modelo do veículo antes de
                // concluir a edição do veículo
                $vehicleModelData = [
                  'vehicletypeperbrandid' => 0,
                  'vehicletypeid' => $vehicleData['vehicletypeid'],
                  'vehiclesubtypeid' => $vehicleData['vehiclesubtypeid'],
                  'vehiclebrandid' => $vehicleData['vehiclebrandid'],
                  'name' => $vehicleData['vehiclemodelname'],
                  'fipeid' => 0,
                ];

                // Determinamos o código do tipo de veículo por marca
                $vehicleTypePerBrands = VehicleTypePerBrand::where(
                      "contractorid", '=', $contractor->id
                    )
                  ->where("vehiclebrandid", '=', $vehicleData['vehiclebrandid'])
                  ->where("vehicletypeid", '=', $vehicleData['vehicletypeid'])
                  ->get([
                      'vehicletypeperbrandid'
                    ])
                ;
                if ( $vehicleTypePerBrands->isEmpty() ) {
                  // Adicionamos o tipo de veículo fabricado por esta
                  // marca
                  $vehicleTypePerBrand = new VehicleTypePerBrand();
                  $vehicleTypePerBrand->contractorid = $contractor->id;
                  $vehicleTypePerBrand->vehicletypeid = $vehicleData['vehicletypeid'];
                  $vehicleTypePerBrand->vehiclebrandid = $vehicleData['vehiclebrandid'];
                  $vehicleTypePerBrand->fipeid = 0;
                  $vehicleTypePerBrand->save();

                  // Informa o ID do tipo de veículo fabricado por esta
                  // marca
                  $vehicleModelData['vehicletypeperbrandid'] = $vehicleTypePerBrand->vehicletypeperbrandid;
                } else {
                  $vehicleTypePerBrand = $vehicleTypePerBrands
                    ->first()
                  ;
                  $vehicleModelData['vehicletypeperbrandid'] = $vehicleTypePerBrand->vehicletypeperbrandid;
                }
                
                // Grava a novo modelo de veículo
                $vehicleModel = new VehicleModel();
                $vehicleModel->fill($vehicleModelData);
                if (intval($vehicleModelData['vehiclesubtypeid']) === 0) {
                  $vehicleModel->vehiclesubtypeid = null;
                }
                // Adiciona o contratante
                $vehicleModel->contractorid = $contractor->id;
                $vehicleModel->save();

                // Informa o ID do novo modelo de veículo
                $vehicleData['vehiclemodelid'] = $vehicleModel->vehiclemodelid;
              } else {
                // Verifica se o subtipo do veículo foi modificado
                if ( (intval($vehicle['vehiclemodelid']) === intval($vehicleData['vehiclemodelid'])) &&
                     (intval($vehicle['vehiclesubtypeid']) === 0) &&
                     (intval($vehicleData['vehiclesubtypeid']) > 0) ) {
                  $this->debug('Atualizar subtipo do modelo de veículo');
                  // Foi informado o subtipo do modelo do veículo, então
                  // fazemos a atualização do modelo
                  $vehicleModel = VehicleModel::findOrFail(
                    $vehicleData['vehiclemodelid']
                  );
                  $vehicleModel->vehiclesubtypeid = $vehicleData['vehiclesubtypeid'];
                  $vehicleModel->save();
                }
              }

              // ==============================[ Dados do veículo ]=====

              // Modificamos os dados do veículo
              $vehicleChanged = Vehicle::findOrFail($vehicleID);
              $vehicleChanged->fill($vehicleData);
              // Adicionamos as informações do responsável pela
              // modificação
              $vehicleChanged->updatedbyuserid = $userID;
              $vehicleChanged->save();

              // =====================[ Telefones do Proprietário ]=====
              // Recupera as informações de telefones do proprietário do
              // veículo e separa os dados para as operações de
              // inserção, atualização e remoção dos mesmos.
              // =======================================================

              // -----------------------------[ Pré-processamento ]-----

              // Matrizes que armazenarão os dados dos telefones a serem
              // adicionados, atualizados e removidos
              $newPhones = [ ];
              $updPhones = [ ];
              $delPhones = [ ];

              // Os IDs dos telefones mantidos para permitir determinar
              // àqueles a serem removidos
              $heldPhones = [ ];

              // Determina quais telefones serão mantidos (e atualizados)
              // e os que precisam ser adicionados (novos)
              foreach ($ownerPhonesData as $phoneData) {
                if (empty($phoneData['ownerphoneid'])) {
                  // Telefone novo
                  unset($phoneData['ownerphoneid']);
                  $newPhones[] = $phoneData;
                } else {
                  // Telefone existente
                  $heldPhones[] = $phoneData['ownerphoneid'];
                  $updPhones[]  = $phoneData;
                }
              }

              // Recupera os telefones armazenados atualmente
              $currentPhones = OwnerPhone::where('vehicleid', $vehicleID)
                ->get(['ownerphoneid'])
                ->toArray()
              ;
              $actPhones = [ ];
              foreach ($currentPhones as $phoneData) {
                $actPhones[] = $phoneData['ownerphoneid'];
              }

              // Verifica quais os telefones estavam na base de dados e
              // precisam ser removidos
              $delPhones = array_diff($actPhones, $heldPhones);

              // --------------------------------------[ Gravação ]-----

              // Primeiro apagamos os telefones removidos pelo usuário
              // durante a edição
              foreach ($delPhones as $phoneID) {
                // Apaga cada telefone
                $this->debug('Apaga o telefone ID {phoneID}',
                  [ 'phoneID' => $phoneID ]
                );
                $phone = OwnerPhone::findOrFail($phoneID);
                $phone->delete();
              }

              // Agora inserimos os novos telefones
              foreach ($newPhones as $phoneData) {
                // Incluímos um novo telefone do proprietário do veículo
                $this->debug('Inclui o telefone {phoneNumber}',
                  [ 'phoneNumber' => $phoneData['phonenumber'] ]
                );
                unset($phoneData['ownerphoneid']);
                $phone = new OwnerPhone();
                $phone->fill($phoneData);
                $phone->vehicleid = $vehicleID;
                $phone->save();
              }

              // Por último, modificamos os telefones mantidos
              foreach($updPhones as $phoneData) {
                // Retira a ID do telefone
                $phoneID = $phoneData['ownerphoneid'];
                unset($phoneData['ownerphoneid']);
                $this->debug('Atualza o telefone ID {phoneID}',
                  [ 'phoneID' => $phoneID ]
                );

                // Grava as informações do telefone
                $phone = OwnerPhone::findOrFail($phoneID);
                $phone->fill($phoneData);
                $phone->save();
              }

              // =============[ Telefones do local de permanência ]=====
              // Recupera as informações de telefones do local de
              // permanência do veículo quando o mesmo é um outro local
              // e separa os dados para as operações de inserção,
              // atualização e remoção dos mesmos.
              // =======================================================

              // -----------------------------[ Pré-processamento ]-----

              // Matrizes que armazenarão os dados dos telefones a serem
              // adicionados, atualizados e removidos
              $newPhones = [ ];
              $updPhones = [ ];
              $delPhones = [ ];

              // Os IDs dos telefones mantidos para permitir determinar
              // àqueles a serem removidos
              $heldPhones = [ ];

              // Determina quais telefones serão mantidos (e atualizados)
              // e os que precisam ser adicionados (novos)
              foreach ($anotherPhonesData as $phoneData) {
                if (empty($phoneData['anotherphoneid'])) {
                  // Telefone novo
                  unset($phoneData['anotherphoneid']);
                  $newPhones[] = $phoneData;
                } else {
                  // Telefone existente
                  $heldPhones[] = $phoneData['anotherphoneid'];
                  $updPhones[]  = $phoneData;
                }
              }

              // Recupera os telefones armazenados atualmente
              $currentPhones = AnotherPhone::where('vehicleid', $vehicleID)
                ->get(['anotherphoneid'])
                ->toArray()
              ;
              $actPhones = [ ];
              foreach ($currentPhones as $phoneData) {
                $actPhones[] = $phoneData['anotherphoneid'];
              }

              // Verifica quais os telefones estavam na base de dados e
              // precisam ser removidos
              $delPhones = array_diff($actPhones, $heldPhones);

              // --------------------------------------[ Gravação ]-----

              // Primeiro apagamos os telefones removidos pelo usuário
              // durante a edição
              foreach ($delPhones as $phoneID) {
                // Apaga cada telefone
                $phone = AnotherPhone::findOrFail($phoneID);
                $phone->delete();
              }

              // Agora inserimos os novos telefones
              foreach ($newPhones as $phoneData) {
                // Incluímos um novo telefone do local de permanência do
                // veículo
                unset($phoneData['anotherphoneid']);
                $phone = new AnotherPhone();
                $phone->fill($phoneData);
                $phone->vehicleid = $vehicleID;
                $phone->save();
              }

              // Por último, modificamos os telefones mantidos
              foreach($updPhones as $phoneData) {
                // Retira a ID do telefone
                $phoneID = $phoneData['anotherphoneid'];
                unset($phoneData['anotherphoneid']);

                // Grava as informações do telefone
                $phone = AnotherPhone::findOrFail($phoneID);
                $phone->fill($phoneData);
                $phone->save();
              }

              // ============================[ Bloqueio de avisos ]=====

              // Modificamos a informação de bloqueio de avisos
              $sql = "UPDATE erp.vehicles
                         SET blockNotices = $blockNotices,
                             blockedDays = $blockedDays,
                             remainingDays = $remainingDays,
                             blockedStartAt = $blockedStartAt,
                             blockedEndAt = $blockedEndAt
                       WHERE vehicleID = $vehicleID;"
              ;
              $this->DB->select($sql);

              // ====================[ Substituição do rastreador ]=====

              //if ($replace) {
              //  if ($vehicleData['replacedat']) {
              //    $replacedAt = Carbon::createFromFormat('d/m/Y H:i:s',
              //      $vehicleData['replacedat'] . ' 00:00:00'
              //    );
              //  } else {
              //    $replacedAt = Carbon::now()->startOfDay();
              //  }
              //}

              // ======================[ Transferência do veículo ]=====

              // Lidamos com questões de transferência
              if ( (intval($vehicle['customerid']) !== intval($vehicleData['customerid'])) ||
                   (intval($vehicle['subsidiaryid']) !== intval($vehicleData['subsidiaryid'])) ) {
                // Ocorreu a mudança do proprietário do veículo, então
                // precismos lidar com questões relativas a equipamentos
                // vinculados bem como com relação à vinculos com alguma
                // associação, de forma a atualizar o que foi modificado

                if ($vehicleData['transferat']) {
                  $transferAt = Carbon::createFromFormat('d/m/Y H:i:s',
                    $vehicleData['transferat'] . ' 00:00:00'
                  );
                } else {
                  $transferAt = Carbon::now()->startOfDay();
                }

                // Analisa os equipamentos vinculados para verificar se
                // ocorreu alguma mudança
                foreach ($equipmentsData AS $key => $equipmentData) {
                  // Primeiramente, precisamos verificar se ocorreu a
                  // modificação do contrato
                  $actualEquipment = $vehicle['equipments'][$key];
                  if ( (intval($actualEquipment['customerpayerid']) !== intval($equipmentData['customerpayerid'])) ||
                       (intval($actualEquipment['subsidiarypayerid']) !== intval($equipmentData['subsidiarypayerid'])) ||
                       (intval($actualEquipment['contractid']) !== intval($equipmentData['contractid'])) ||
                       (intval($actualEquipment['installationid']) !== intval($equipmentData['installationid'])) ) {
                    // Ocorreu a mudança de item de contrato deste
                    // equipamento, então analisa as condições
                    
                    // Determina se o contrato deve ser encerrado
                    $terminate = filter_var(
                      $equipmentData['terminate'],
                      FILTER_VALIDATE_BOOLEAN
                    );
                    $close = ($terminate === true)
                      ? true
                      : (
                          (filter_var($equipmentData['notclose'], FILTER_VALIDATE_BOOLEAN))
                            ? false
                            : true
                        )
                    ;

                    // Determina se não deve ser cobrada multa por
                    // quebra do período de fidelidade
                    $notChargeLoyaltyBreak = null;
                    if (array_key_exists('notchargeloyaltybreak', $equipmentData)) {
                      $notChargeLoyaltyBreak = $equipmentData['notchargeloyaltybreak'];
                    }

                    // Determina outros valores sobre o contrato para
                    // subsídio de nossas análises
                    $amountOfPayerContracts = intval($equipmentData['amountOfPayerContracts']);
                    $amountOfItensInContract = intval($equipmentData['amountOfItensInContract']);

                    // Determina se o cliente anterior estava vinculado
                    // a uma associação
                    if (intval($actualEquipment['payerentitytypeid']) === 3) {
                      // O cliente anterior estava vinculado a uma
                      // associação, então lida com o vínculo com ela
                      $this->unjoinFromAssociation(
                        $vehicleID,
                        $actualEquipment['customerpayerid'],
                        $actualEquipment['subsidiarypayerid'],
                        $vehicle['customerid'],
                        $vehicle['subsidiaryid'],
                        $transferAt
                      );
                    }

                    // Registramos a informação de término do contrato
                    // do cliente pagante atual. Localizamos o registro
                    // de instalação
                    $lastInstallationRecord =
                      InstallationRecord::where('equipmentid', $actualEquipment['equipmentid'])
                                        ->where('contractorid', $contractor->id)
                                        ->where('vehicleid', $vehicleID)
                                        ->where('installationid', $actualEquipment['installationid'])
                                        ->whereNull('uninstalledat')
                                        ->first()
                    ;

                    // Informamos que a cobrança deve ser realizada até
                    // um dia antes da transferência
                    $installationRecordID = $lastInstallationRecord->installationrecordid;
                    $installedAt = Carbon::createFromFormat('d/m/Y H:i:s',
                      $actualEquipment['installedat'] . ' 00:00:00'
                    );
                    if ($installedAt->greaterThan($transferAt->copy()->subDay())) {
                      // Isto ocorre quando a data de instalação e
                      // desinstalação são do mesmo dia, então evitamos
                      // que ocorra a inserção de uma data de
                      // desinstalação menor do que a data de instalação
                      $endDate = $installedAt
                        ->format('Y-m-d')
                      ;
                    } else {
                      $endDate = $transferAt
                        ->copy()
                        ->subDay()
                        ->format('Y-m-d')
                      ;
                    }
                    $sql = ""
                      . "UPDATE erp.installationrecords
                            SET uninstalledAt = '{$endDate}'::Date,
                                updatedAt = CURRENT_TIMESTAMP,
                                updatedByUserID = {$userID}
                          WHERE installationRecordID = {$installationRecordID};"
                    ;
                    $this->DB->select($sql);

                    if ($close) {
                      // Encerra o item de contrato do cliente do qual
                      // estamos transferindo o equipamento
                      $complement = '';
                      if ($notChargeLoyaltyBreak) {
                        $complement = "notChargeLoyaltyBreak = {$notChargeLoyaltyBreak},";
                      }
                      $sql = ""
                        . "UPDATE erp.installations
                              SET endDate = '{$endDate}'::Date,
                                  updatedAt = CURRENT_TIMESTAMP, {$complement}
                                  updatedByUserID = {$userID}
                            WHERE installationID = {$actualEquipment['installationid']};"
                      ;
                      $this->DB->select($sql);
                    }

                    if ($terminate) {
                      // Desativamos o contrato do cliente do qual
                      // estamos transferindo a cobrança
                      $sql = ""
                        . "UPDATE erp.contracts
                              SET endDate = '{$endDate}'::Date,
                                  updatedAt = CURRENT_TIMESTAMP,
                                  updatedByUserID = {$userID}
                            WHERE contractID = {$actualEquipment['contractid']};"
                      ;
                      $this->DB->select($sql);

                      // Verifica se o cliente possui um ou mais
                      // veículos ainda ativos e/ou se ele possui
                      // veículos para os quais ele é pagante
                      $remainingVehicles = Vehicle::where('customerid', '=',
                            $actualEquipment['customerpayerid']
                          )
                        ->where('blocked', 'false')
                        ->count()
                      ;
                      if ( ($remainingVehicles == 0) &&
                           ($amountOfPayerContracts == 1) &&
                           ($amountOfItensInContract == 1) ) {
                        // Desativamos o cliente também
                        $customer = Entity::findOrFail($actualEquipment['customerpayerid']);
                        $customer->blocked = true;
                        $customer->updatedbyuserid = $userID;
                        $customer->save();
                      }
                    }

                    // Agora inserimos a informação do início do
                    // relacionamento com o novo cliente
                    $installationRecord = new InstallationRecord();
                    $installationRecord->contractorid = $contractor->id;
                    $installationRecord->installedat = $transferAt
                      ->copy()
                    ;
                    $installationRecord->equipmentid = $actualEquipment['equipmentid'];
                    $installationRecord->vehicleid = $vehicleID;
                    $installationRecord->installationid = $equipmentData['installationid'];
                    $installationRecord->createdbyuserid = $userID;
                    $installationRecord->updatedbyuserid = $userID;
                    $installationRecord->save();

                    // Modificamos o equipamento para refletir isto
                    // também
                    $equipment = Equipment::findOrFail($actualEquipment['equipmentid']);
                    $equipment->customerpayerid = $equipmentData['customerpayerid'];
                    $equipment->subsidiarypayerid = $equipmentData['subsidiarypayerid'];
                    $equipment->installationid = $equipmentData['installationid'];
                    $equipment->installedat = $transferAt
                      ->copy()
                    ;
                    $equipment->updatedbyuserid = $userID;
                    $equipment->save();

                    // Analisamos o item de contrato para modificar
                    $newInstallation = Installation::findOrFail(
                      $equipmentData['installationid']
                    );
                    if ($newInstallation->startdate == NULL) {
                      // Modificamos o item de contrato para refletir
                      // isto também
                      $startDate = $transferAt
                        ->copy()
                        ->format('Y-m-d')
                      ;
                      $sql = ""
                        . "UPDATE erp.installations
                              SET startDate = '{$startDate}'::Date,
                                  updatedAt = CURRENT_TIMESTAMP,
                                  updatedByUserID = {$userID}
                            WHERE installationID = {$equipmentData['installationid']};"
                      ;
                      $this->DB->select($sql);
                    }

                    // Determina se o novo cliente precisa ser vinculado
                    // a associação
                    if (intval($equipmentData['payerentitytypeid']) === 3) {
                      // Vincula o novo cliente a associação
                      $this->joinAnAssociation(
                        $equipmentData['customerpayerid'],
                        $equipmentData['subsidiarypayerid'],
                        $vehicleData['customerid'],
                        $vehicleData['subsidiaryid'],
                        $transferAt
                      );
                    }
                  } else {
                    // Determina se o cliente anterior estava vinculado
                    // a uma associação
                    if (intval($actualEquipment['payerentitytypeid']) === 3) {
                      // O cliente anterior estava vinculado a uma
                      // associação, então lida com o vínculo com ela
                      $this->unjoinFromAssociation(
                        $vehicleID,
                        $actualEquipment['customerpayerid'],
                        $actualEquipment['subsidiarypayerid'],
                        $vehicle['customerid'],
                        $vehicle['subsidiaryid'],
                        $transferAt
                      );
                    }

                    // Determina se o novo cliente precisa ser vinculado
                    // a associação
                    if (intval($equipmentData['payerentitytypeid']) === 3) {
                      // Vincula o novo cliente a associação
                      $this->joinAnAssociation(
                        $equipmentData['customerpayerid'],
                        $equipmentData['subsidiarypayerid'],
                        $vehicleData['customerid'],
                        $vehicleData['subsidiaryid'],
                        $transferAt
                      );
                    }
                  }

                  // Força a atualização do equipamento no grid para
                  // refletir as mudanças aqui realizadas
                  $this->updateVehicleOnGrid(
                    $actualEquipment['equipmentid'],
                    $vehicleID,
                    $vehicleData['plate'],
                    $vehicleData['customerid'],
                    $vehicleData['subsidiaryid'],
                    $equipmentData['customerpayerid'],
                    $equipmentData['subsidiarypayerid']
                  );
                }
              } else {
                // Não foi feita uma transferência
                if ($vehicleData['plate'] !== $vehicle['plate']) {
                  // Alterada a placa, então propaga as informações para
                  // o grid do sistema de monitoramento
                  foreach ($equipmentsData AS $key => $equipmentData) {
                    // Força a atualização do equipamento no grid para
                    // refletir as mudanças aqui realizadas
                    $this->updateVehicleOnGrid(
                      $vehicle['equipments'][$key]['equipmentid'],
                      $vehicleID,
                      $vehicleData['plate'],
                      $vehicleData['customerid'],
                      $vehicleData['subsidiaryid'],
                      $equipmentData['customerpayerid'],
                      $equipmentData['subsidiarypayerid']
                    );
                  }
                }
              }

              // =============================[ Documentos anexos ]=====

              // Incluímos todos os documentos anexados deste veículo
              foreach($attachmentsData AS $attachment) {
                // Incluímos um documento anexado deste veículo
                $vehicleattachment = new VehicleAttachment();
                $vehicleattachment->filename =
                  $attachment['filename']
                ;
                $vehicleattachment->realfilename =
                  $attachment['realfilename']
                ;
                $vehicleattachment->vehicleid = $vehicleID;
                $vehicleattachment->contractorid = $contractor->id;
                $vehicleattachment->save();
              }

              // Efetiva a transação
              $this->DB->commit();

              // Registra o sucesso
              $this->info("O veículo placa '{plate}' foi modificado "
                . "com sucesso.",
                [
                  'plate' => $vehicleData['plate']
                ]
              );

              // Alerta o usuário
              $this->flash(
                "success",
                "O veículo placa <i>'{plate}'</i> foi modificado com "
                . "sucesso.",
                [
                  'plate' => $vehicleData['plate']
                ]
              );

              // Registra o evento
              $this->debug("Redirecionando para {routeName}",
                [
                  'routeName' => 'ERP\Cadastre\Vehicles'
                ]
              );

              // Redireciona para a página de gerenciamento de veículos
              return $this->redirect($response,
                'ERP\Cadastre\Vehicles'
              );
            }
          }
          catch(UploadFileException $exception)
          {
            // Reverte (desfaz) a transação
            $this->DB->rollBack();

            // Registra o erro
            $this->error("Não foi possível modificar as informações do "
              . "veículo placa '{plate}'. {error}",
              [
                'plate'  => $vehicleData['plate'],
                'error' => $exception->getMessage()
              ]
            );

            // Remove os arquivos anexados, caso ainda não tenham sido
            // removidos
            foreach ($attachmentsData as $attachment) {
              // Apaga os arquivos enviados/criados
              $this->deleteFile($uploadDirectory,
                $attachment['filename']
              );
            }

            // Alerta o usuário
            $this->flashNow(
              "error",
              "Não foi possível modificar as informações do veículo. "
              . "{error}",
              [
                'error' => $exception->getMessage()
              ]
            );
          }
          catch(QueryException | Exception $exception)
          {
            // Reverte (desfaz) a transação
            $this->DB->rollBack();

            // Registra o erro
            $this->error("Não foi possível modificar as informações do "
              . "veículo placa '{plate}'. Erro interno: {error}",
              [
                'plate'  => $vehicleData['plate'],
                'error' => $exception->getMessage()
              ]
            );

            // Remove os arquivos anexados, caso ainda não tenham sido
            // removidos
            foreach ($attachmentsData as $attachment) {
              // Apaga os arquivos enviados/criados
              $this->deleteFile($uploadDirectory,
                $attachment['filename']
              );
            }

            // Alerta o usuário
            $this->flashNow(
              "error",
              "Não foi possível modificar as informações do veículo. "
              . "Erro interno."
            );
          }
        } else {
          // Registra o erro
          $this->debug("Não foi possível modificar as informações do "
            . "veículo placa '{plate}'. O número do chassi não é "
            . "válido.",
            [
              'plate'  => $vehicle['plate']
            ]
          );

          // Seta o erro neste campo
          $this->validator->setErrors([
              'vin' => ((strlen($vin) < 17)
                ? 'O número do chassi precisa ter 17 dígitos'
                : 'O número do chassi é inválido')
            ],
            "vin")
          ;
        }
      } else {
        $this->info('Os dados do veículo são INVÁLIDOS');
        $messages = $this->validator->getFormatedErrors();
        foreach ($messages AS $message) {
          $this->info($message);
        }
      }
    } else {
      // Acrescenta o valor do local de permanência do veículo
      if ($vehicle['atsamecustomeraddress']) {
        $vehicle['placeOfStay'] = 'atsamecustomeraddress';
      } else {
        if ($vehicle['atsameowneraddress']) {
          $vehicle['placeOfStay'] = 'atsameowneraddress';
        } else {
          $vehicle['placeOfStay'] = 'atanotheraddress';
        }
      }

      // Limpa os dados do outro endereço, caso não esteja em uso
      if ($vehicle['placeOfStay'] !== 'atanotheraddress') {
        $vehicle['anothername'] = '';
        $vehicle['anotherpostalcode'] = '';
        $vehicle['anotheraddress'] = '';
        $vehicle['anotherstreetnumber'] = '';
        $vehicle['anothercomplement'] = '';
        $vehicle['anotherdistrict'] = '';
        $vehicle['anothercityid'] = 0;
        $vehicle['anothercityname'] = '';
        $vehicle['anotherstate'] = '';
      }

      // Seta o dia atual como data de transferência e substituição
      $vehicle['replacedat'] = Carbon::now()->format('d/m/Y');
      $vehicle['transferat'] = Carbon::now()->format('d/m/Y');

      // Carrega os dados atuais
      $this->validator->setValues($vehicle);
    }

    // Exibe um formulário para edição de um veículo

    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início', 
      $this->path('ERP\Home')
    );
    $this->breadcrumb->push('Cadastro', '');
    $this->breadcrumb->push('Veículos',
      $this->path('ERP\Cadastre\Vehicles')
    );
    $this->breadcrumb->push('Editar',
      $this->path('ERP\Cadastre\Vehicles\Edit', [
        'vehicleID' => $vehicleID]
      )
    );

    // Registra o acesso
    $this->info("Acesso à edição do veículo placa '{plate}'.",
      [
        'plate' => $vehicle['plate']
      ]
    );

    // Renderiza a página
    return $this->render($request, $response,
        'erp/cadastre/vehicles/vehicle.twig',
        [
          'formMethod' => 'PUT',
          'vehicleTypes' => $vehicleTypes,
          'vehicleSubtypesPerType' => $vehicleSubtypesPerType,
          'vehicleColors' => $vehicleColors,
          'fuelTypes' => $fuelTypes,
          'documentTypes' => $documentTypes,
          'phoneTypes' => $phoneTypes,
          'contracts' => $contracts,
          'installations' => $installations
        ]
      )
    ;
  }

  /**
   * Exibe um formulário para vinculação de um veículo com um
   * equipamento, quando solicitado, e confirma os dados enviados. O
   * ID do veículo é informado previamente, já que este diálogo é
   * acionado através da interface pelo click em um veículo.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   * @param array $args
   *   Os argumentos da requisição
   *
   * @return Response $response
   */
  public function attach(
    Request $request,
    Response $response,
    array $args
  )
  {
    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();

    // Recupera as informações do veículo onde estamos instalando o
    // rastreador
    $vehicleID = $args['vehicleID'];

    // Determina se passamos a informação do equipamento
    $equipmentID = 0;
    if (array_key_exists('equipmentID', $args)) {
      $equipmentID = $args['equipmentID'];
    }

    try
    {
      // Recupera as informações do veículo onde se está instalando o
      // equipamento
      $vehicle = Vehicle::join('entities', 'vehicles.customerid',
            '=', 'entities.entityid'
          )
        ->join('entities AS customer', 'vehicles.customerid',
            '=', 'customer.entityid'
          )
        ->join('subsidiaries AS subsidiary', 'vehicles.subsidiaryid',
            '=', 'subsidiary.subsidiaryid'
          )
        ->join('vehicletypes', 'vehicles.vehicletypeid',
            '=', 'vehicletypes.vehicletypeid'
          )
        ->join('vehiclebrands', 'vehicles.vehiclebrandid',
            '=', 'vehiclebrands.vehiclebrandid'
          )
        ->join('vehiclemodels', 'vehicles.vehiclemodelid',
            '=', 'vehiclemodels.vehiclemodelid'
          )
        ->leftJoin('vehiclesubtypes', 'vehiclemodels.vehiclesubtypeid',
            '=', 'vehiclesubtypes.vehiclesubtypeid'
          )
        ->join('vehiclecolors', 'vehicles.vehiclecolorid',
            '=', 'vehiclecolors.vehiclecolorid'
          )
        ->where('vehicles.vehicleid', $vehicleID)
        ->where('entities.contractorid', '=', $contractor->id)
        ->get([
            'vehicles.vehicleid',
            'vehicles.plate',
            'vehicles.customerid',
            'customer.name AS customername',
            'customer.blocked AS customerblocked',
            'customer.entitytypeid',
            'vehicles.subsidiaryid',
            'subsidiary.name AS subsidiaryname',
            'subsidiary.blocked AS subsidiaryblocked',
            'vehicles.vehicletypeid',
            $this->DB->raw("CASE "
              .   "WHEN vehiclemodels.vehiclesubtypeid IS NULL THEN vehicletypes.name "
              .   "ELSE vehiclesubtypes.name "
              . "END AS vehicletypename"
            ),
            'vehicles.vehiclebrandid',
            'vehiclebrands.name AS vehiclebrandname',
            'vehicles.vehiclemodelid',
            'vehiclemodels.name AS vehiclemodelname',
            'vehicles.vehiclecolorid',
            'vehiclecolors.name AS vehiclecolorname',
            'vehicles.yearfabr',
            'vehicles.yearmodel',
            'vehicles.carnumber',
            'vehicles.renavam',
            'vehicles.vin',
            'vehicles.blocked',
            $this->DB->raw("to_char(CURRENT_DATE, 'DD/MM/YYYY') AS installedat")
          ])
      ;

      if ( $vehicle->isEmpty() ) {
        throw new ModelNotFoundException("Não temos nenhum veículo "
          . "com o código {$vehicleID} cadastrado."
        );
      }

      $vehicle = $vehicle
        ->first()
        ->toArray()
      ;
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar o veículo código "
        . "{vehicleID}.",
        [
          'vehicleID' => $vehicleID
        ]
      );

      // Alerta o usuário
      $this->flash(
        "error",
        "Não foi possível localizar este veículo."
      );

      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [
          'routeName' => 'ERP\Cadastre\Vehicles'
        ]
      );

      // Redireciona para a página de gerenciamento de veículos
      return $this->redirect($response, 'ERP\Cadastre\Vehicles');
    }

    // Verifica se temos algum bloqueio
    if ( $vehicle['customerblocked'] ||
         $vehicle['subsidiaryblocked'] ||
         $vehicle['blocked'] ) {
      // Não permite associar veículos bloqueados e/ou de clientes
      // bloqueados
      
      // Analisa o motivo do bloqueio
      if ($vehicle['blocked']) {
        $whyIsBlocked = "O veículo está bloqueado para uso.";
      } else {
        if ($vehicle['customerblocked']) {
          $whyIsBlocked = "O veículo não está disponível para uso "
            . "pois o seu cliente encontra-se bloqueado."
          ;
        } else {
          $whyIsBlocked = "O veículo não está disponível para uso "
            . "pois a unidade/filial do seu cliente encontra-se "
            . "bloqueada."
          ;
        }
      }

      // Registra o erro
      $this->debug("Não foi possível associar o veículo placa "
        . "'{plate}' à um equipamento. {why}",
        [
          'plate' => $vehicle['plate'],
          'why'   => $whyIsBlocked
        ]
      );

      // Alerta o usuário
      $this->flash(
        "error",
        "O veículo placa <i>'{plate}'</i> está bloqueado para uso e "
        . "não pode ser associado à um equipamento.",
        [
          'plate' => $vehicle['plate']
        ]
      );

      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [
          'routeName' => 'ERP\Cadastre\Vehicles'
        ]
      );

      // Redireciona para a página de gerenciamento de veículos
      return $this->redirect($response, 'ERP\Cadastre\Vehicles');
    }

    $attached = false;
    if ($equipmentID > 0) {
      try
      {
        // Recupera as informações do equipamento já vinculado ao
        // veículo
        $equipment = Equipment::join("equipmentmodels",
              "equipments.equipmentmodelid", '=',
              "equipmentmodels.equipmentmodelid"
            )
          ->join("equipmentbrands", "equipmentmodels.equipmentbrandid",
              '=', "equipmentbrands.equipmentbrandid"
            )
          ->join("entities AS customer", "equipments.customerpayerid",
              '=', "customer.entityid"
            )
          ->join("subsidiaries AS subsidiary", "equipments.subsidiarypayerid",
              '=', "subsidiary.subsidiaryid"
            )
          ->join("installations", "equipments.installationid",
              '=', "installations.installationid"
            )
          ->join("contracts", "installations.contractid",
            '=', "contracts.contractid"
          )
          ->join("plans", "installations.planid",
              '=', "plans.planid"
            )
          ->whereRaw("(equipments.contractorid = {$contractor->id} OR equipments.assignedtoid = {$contractor->id})")
          ->where("equipments.equipmentid", '=', $equipmentID)
          ->get([
              'equipments.equipmentid AS id',
              'equipments.serialnumber',
              'equipmentmodels.equipmentbrandid',
              'equipmentbrands.name AS equipmentbrandname',
              'equipments.equipmentmodelid',
              'equipmentmodels.name AS equipmentmodelname',
              $this->DB->raw("getStorageLocation(equipments.storagelocation, "
                . "equipments.depositid, 'Equipment', equipments.vehicleid) AS storedlocationname"
              ),
              'equipments.customerpayerid',
              'customer.name AS customerpayername',
              'customer.entitytypeid AS payerentitytypeid',
              'equipments.subsidiarypayerid',
              'subsidiary.name AS subsidiarypayername',
              'equipments.installedat',
              'equipments.installationid',
              $this->DB->raw('not(contracts.active) AS contractsuspended'),
              'installations.contractid',
              'equipments.main',
              'equipments.hiddenfromcustomer',
              'equipments.installationsite',
              'equipments.hasblocking',
              'equipments.blockingsite',
              'equipments.hasibutton',
              'equipments.ibuttonsite',
              'equipments.hassiren',
              'equipments.sirensite',
              'equipments.panicbuttonsite',
              'plans.loyaltyperiod',
              $this->DB->raw(""
                . "CASE"
                . "  WHEN plans.loyaltyperiod > 0 AND installations.startdate IS NOT NULL THEN (installations.startdate + plans.loyaltyperiod * interval '1 month')::date"
                . "  ELSE NULL "
                . "END AS endloyaltyperiod"
              ),
              $this->DB->raw(""
                . "CASE"
                . "  WHEN contracts.notchargeloyaltybreak = TRUE THEN TRUE"
                . "  WHEN installations.notchargeloyaltybreak = TRUE THEN TRUE"
                . "  WHEN plans.loyaltyperiod > 0 AND installations.startdate IS NOT NULL THEN (installations.startdate + plans.loyaltyperiod * interval '1 month') < CURRENT_DATE"
                . "  ELSE FALSE "
                . "END AS disablechargeloyaltybreak"
              )
            ])
        ;

        if ( $equipment->isEmpty() ) {
          throw new ModelNotFoundException("Não temos nenhum equipamento "
            . "com o código {$equipmentID} cadastrado."
          );
        }

        $equipment = $equipment
          ->first()
          ->toArray()
        ;

        // Acrescentamos as informações do equipamento
        $vehicle['equipmentid'] = $equipment['id'];
        $vehicle['serialnumber'] = $equipment['serialnumber'];
        $vehicle['equipmentbrandid'] = $equipment['equipmentbrandid'];
        $vehicle['equipmentbrandname'] = $equipment['equipmentbrandname'];
        $vehicle['equipmentmodelid'] = $equipment['equipmentmodelid'];
        $vehicle['equipmentmodelname'] = $equipment['equipmentmodelname'];
        $vehicle['storedlocationname'] = $equipment['storedlocationname'];
        $vehicle['installedat'] = $equipment['installedat'];
        $vehicle['customerpayerid'] = $equipment['customerpayerid'];
        $vehicle['customerpayername'] = $equipment['customerpayername'];
        $vehicle['payerentitytypeid'] = $equipment['payerentitytypeid'];
        $vehicle['subsidiarypayerid'] = $equipment['subsidiarypayerid'];
        $vehicle['subsidiarypayername'] = $equipment['subsidiarypayername'];
        $vehicle['contractid'] = $equipment['contractid'];
        $vehicle['installationid'] = $equipment['installationid'];
        $vehicle['loyaltyperiod'] = $equipment['loyaltyperiod'];
        $vehicle['endloyaltyperiod'] = $equipment['endloyaltyperiod'];
        $vehicle['disablechargeloyaltybreak'] = $equipment['disablechargeloyaltybreak'];
        $vehicle['main'] = ( $equipment['main'] )
          ? 'true'
          : 'false'
        ;
        $vehicle['keepAsContingency'] = 'false';
        $vehicle['hiddenfromcustomer'] = ( $equipment['hiddenfromcustomer'] )
          ? 'true'
          : 'false'
        ;
        $vehicle['blocktype'] = true;
        $vehicle['informPayingCustomer'] =
          ( (intval($vehicle['customerid']) !== intval($vehicle['customerpayerid'])) ||
             (intval($vehicle['subsidiaryid']) !== intval($vehicle['subsidiarypayerid'])) )
        ;

        // Deixamos as informações do local de instalação no padrão caso
        // estejam em branco
        $vehicle['installationsite'] = ($equipment['installationsite'])
          ? $equipment['installationsite']
          : ''
        ;
        $vehicle['hasblocking'] = $equipment['hasblocking'];
        $vehicle['blockingsite'] = ($equipment['blockingsite'])
          ? $equipment['blockingsite']
          : ''
        ;
        $vehicle['hasibutton'] = $equipment['hasibutton'];
        $vehicle['ibuttonsite'] = ($equipment['ibuttonsite'])
          ? $equipment['ibuttonsite']
          : ''
        ;
        $vehicle['hassiren'] = $equipment['hassiren'];
        $vehicle['sirensite'] = ($equipment['sirensite'])
          ? $equipment['sirensite']
          : ''
        ;
        $vehicle['panicbuttonsite'] = ($equipment['panicbuttonsite'])
          ? $equipment['panicbuttonsite']
          : ''
        ;

        // Recuperamos os contratos do atual pagante do equipamento
        $contracts = $this->getContracts(
          $vehicle['customerpayerid'],
          $vehicle['subsidiarypayerid'],
          false,
          $vehicle['contractid']
        );
        $contracts = $contracts
          ->toArray()
        ;

        // Determina a quantidade de contratos ativos que ele têm
        $amountOfPayerContracts = count($contracts);
        $vehicle['amountOfPayerContracts'] = $amountOfPayerContracts;

        // Recupramos os itens de contrato existentes para o contrato
        // do atual pagante do equipamento
        $installations = $this->getInstallations(
          $vehicle['contractid'],
          false,
          $vehicle['installationid']
        );

        // Informa a quantidade de itens no contrato do pagante do
        // equipamento
        $amountOfItensInContract = count($installations);
        $vehicle['amountOfItensInContract'] = $amountOfItensInContract;

        // Indica se o contrato deve ser encerrado
        $vehicle['terminate'] = (
          ($amountOfPayerContracts == 1)
          && ($amountOfItensInContract == 1)
        );
      
        // Informa que o veículo está vinculado
        $attached = true;
      }
      catch(ModelNotFoundException $exception)
      {
        // Registra o erro
        $this->error("Não foi possível carregar as informações de "
          . "vínculo com o veículo código {vehicleID}. Erro: {error}.",
          [
            'vehicleID' => $vehicleID,
            'error' => $exception->getMessage()
          ]
        );

        // Alerta o usuário
        $this->flash(
          "error",
          "Não foi possível localizar este veículo."
        );

        // Registra o evento
        $this->debug("Redirecionando para {routeName}",
          [
            'routeName' => 'ERP\Cadastre\Vehicles'
          ]
        );

        // Redireciona para a página de gerenciamento de veículos
        return $this->redirect($response, 'ERP\Cadastre\Vehicles');
      }
    } else {
      // É uma nova vinculação de equipamento com este veículo

      // Não selecionamos nada e deixamos o cliente decidir
      $vehicle['customerpayerid'] = 0;
      $vehicle['customerpayername'] = '';
      $vehicle['subsidiarypayerid'] = 0;
      $vehicle['subsidiarypayername'] = '';
      $contracts = [];
      $vehicle['contractid'] = 0;
      $installations = [];
      $vehicle['installationid'] = 0;

      // Deixamos as informações do local de instalação no padrão
      $vehicle['installationsite'] = '';
      $vehicle['hasblocking'] = 'false';
      $vehicle['blockingsite'] = '';
      $vehicle['hasibutton'] = 'false';
      $vehicle['ibuttonsite'] = '';
      $vehicle['hassiren'] = 'false';
      $vehicle['sirensite'] = '';
      $vehicle['panicbuttonsite'] = '';
      $vehicle['amountOfPayerContracts'] = 0;
      $vehicle['amountOfItensInContract'] = 0;
      $vehicle['notchargeloyaltybreak'] = 'true';
      $vehicle['loyaltyperiod'] = 0;
      $vehicle['endloyaltyperiod'] = '';

      // Determinamos se este é o rastreador principal ou o de
      // contingência e se existem outros rastreadores vinculados
      $sql = "SELECT COUNT(*) > 0 AS maintracker
                FROM equipments
               WHERE main = true
                 AND vehicleID = {$vehicleID};";
      $equipmentInfo = $this->DB->select($sql)[0];

      $vehicle['main'] = $equipmentInfo->maintracker
        ? 'false'
        : 'true'
      ;
      $vehicle['keepAsContingency'] = $equipmentInfo->maintracker
        ? 'true'
        : 'false'
      ;
      $vehicle['hiddenfromcustomer'] = 'false';
    }

    $newContracts = [];
    $newInstallations = [];

    // Verifica se estamos modificando os dados
    if ($request->isPut()) {
      // Os dados estão sendo modificados

      // Registra o acesso
      $this->info("Processando à associação do veículo placa "
        . "'{plate}' à um equipamento.",
        [
          'plate' => $vehicle['plate']
        ]
      );

      $replace = false;
      $transfer = false;
      if ($attached) {
        // Determina se estamos substituindo
        $replace = ($request->getParam('replace') == 'true');

        // Determina se estamos transferindo
        $transfer = ($request->getParam('transfer') == 'true');
      }

      // Valida os dados
      $this->validator->validate($request,
        $this->getValidationRulesForAttach($attached, $replace, $transfer)
      );

      if ($this->validator->isValid()) {
        $this->debug('Os dados do vínculo são VÁLIDOS');

        try
        {
          // Iniciamos a transação
          $this->DB->beginTransaction();

          $userID = $this->authorization->getUser()->userid;

          // Recupera os dados para a associação do veículo à um
          // equipamento
          $attachmentData = $this->validator->getValues();

          // Retiramos o ID do equipamento
          $equipmentID = $attachmentData['equipmentid'];
          unset($attachmentData['equipmentid']);

          // Analisamos se estamos modificando um equipamento
          $replace = false;
          $replaceData = [];
          if ($attached) {
            // Obtemos a informação se estamos substituíndo
            $replace = ($attachmentData['replace'] == 'true');

            if ($replace) {
              // Separamos as informações dos locais de instalação e da
              // data de instalação do restante dos dados do equipamento
              $replaceFields = [
                'newequipmentid',
                'newserialnumber',
                'newequipmentbrandname',
                'newequipmentbrandid',
                'newequipmentmodelname',
                'newequipmentmodelid',
                'newstoredlocationname',
                'replacedat'
              ];
              $replaceData = array_filter($attachmentData,
                function ($key) use ($replaceFields) {
                  return in_array($key, $replaceFields);
                },
                ARRAY_FILTER_USE_KEY
              );
            }

            // Sempre removemos os campos de substituição
            unset($attachmentData['replace']);
            unset($attachmentData['newequipmentid']);
            unset($attachmentData['newserialnumber']);
            unset($attachmentData['newequipmentbrandname']);
            unset($attachmentData['newequipmentbrandid']);
            unset($attachmentData['newequipmentmodelname']);
            unset($attachmentData['newequipmentmodelid']);
            unset($attachmentData['newstoredlocationname']);
            unset($attachmentData['replacedat']);

            // Obtemos a informação se estamos transferindo
            $transfer = ($attachmentData['transfer'] == 'true');

            if ($transfer) {
              // Separamos as informações dos locais de instalação e da
              // data de instalação do restante dos dados do equipamento
              $transferFields = [
                'amountOfPayerContracts',
                'amountOfItensInContract',
                'terminate',
                'notclose',
                'notchargeloyaltybreak',
                'newcustomerpayername',
                'newcustomerpayerid',
                'newpayerentitytypeid',
                'newsubsidiarypayername',
                'newsubsidiarypayerid',
                'newcontractid',
                'newinstallationid',
                'transferat'
              ];
              $transferData = array_filter($attachmentData,
                function ($key) use ($transferFields) {
                  return in_array($key, $transferFields);
                },
                ARRAY_FILTER_USE_KEY
              );
            }

            // Sempre removemos os campos de transferência
            unset($attachmentData['transfer']);
            unset($attachmentData['amountOfPayerContracts']);
            unset($attachmentData['amountOfItensInContract']);
            unset($attachmentData['terminate']);
            unset($attachmentData['notclose']);
            unset($attachmentData['notchargeloyaltybreak']);
            unset($attachmentData['newcustomerpayername']);
            unset($attachmentData['newcustomerpayerid']);
            unset($attachmentData['newpayerentitytypeid']);
            unset($attachmentData['newsubsidiarypayername']);
            unset($attachmentData['newsubsidiarypayerid']);
            unset($attachmentData['newcontractid']);
            unset($attachmentData['newinstallationid']);
            unset($attachmentData['transferat']);
          }

          // Obtemos a quantidade de iButtons armazenáveis neste
          // modelo de equipamento
          $equipmentModel = EquipmentModel::findOrFail($attachmentData['equipmentmodelid']);
          $ibuttonsMemSize = $equipmentModel->ibuttonsmemsize;
          $this->info("O modelo de equipamento '{model}' tem '{count}' "
            . "espaços em memória.",
            [
              'model' => $attachmentData['equipmentmodelid'],
              'count' => $ibuttonsMemSize
            ]
          );

          // ========================================[ Rastreador ]=====
          // Associa o veículo ao equipamento. Isto se faz modificando o
          // equipamento e indicando em qual veículo ele se encontra
          // ===========================================================
          if ($attached === false) {
            // Vinculamos o veículo ao equipamento
            $installedAt = Carbon::createFromFormat('d/m/Y',
              $attachmentData['installedat']
            );

            // Determina se precisa criar um novo item de instalação no
            // contrato
            $installationID = intval($attachmentData['installationid']);
            if ($installationID === 0) {
              // Cria um novo item de instalação no contrato
              $contractID = intval($attachmentData['contractid']);
              $contract = Contract::join('entities AS customers',
                    'contracts.customerid', '=', 'customers.entityid'
                  )
                ->where('contracts.contractorid', '=', $contractor->id)
                ->where('contracts.contractid', '=', $contractID)
                ->get([
                    'contracts.contractid',
                    $this->DB->raw('getContractNumber(contracts.createdat) AS contractnumber'),
                    'contracts.planid',
                    'contracts.subscriptionplanid',
                    'contracts.monthprice',
                    'contracts.customerid',
                    'customers.name AS customername',
                    'contracts.subsidiaryid'
                  ])
              ;

              if ( $contract->isEmpty() ) {
                throw new ModelNotFoundException("Não temos nenhum contrato com "
                  . "o código {$contractID} cadastrado"
                );
              }

              $installation = new Installation();
              $installation->contractorid = $contractor->id;
              $installation->customerid = $contract[0]->customerid;
              $installation->subsidiaryid = $contract[0]->subsidiaryid;
              $installation->contractid = $contractID;
              $installation->planid = $contract[0]->planid;
              $installation->subscriptionplanid = $contract[0]->subscriptionplanid;
              $installation->monthprice = $contract[0]->monthprice;
              $installation->createdbyuserid = $userID;
              $installation->updatedbyuserid = $userID;
              $installation->save();

              // Recupera o ID do novo item de instalação
              $installationID = $installation->installationid;

              // Atualizamos o número da instalação
              $sql = ""
                . "UPDATE erp.installations
                      SET installationnumber = erp.generateInstallationNumber(contractorID, contractID, installationID)
                    WHERE installations.installationID = {$installationID};"
              ;
              $this->DB->select($sql);
            }

            $equipmentChanged = Equipment::findOrFail($equipmentID);
            $equipmentChanged->vehicleid = $vehicleID;
            $equipmentChanged->storagelocation    = 'Installed';
            $equipmentChanged->customerpayerid    = $attachmentData['customerpayerid'];
            $equipmentChanged->subsidiarypayerid  = $attachmentData['subsidiarypayerid'];
            $equipmentChanged->subsidiarypayerid  = $attachmentData['subsidiarypayerid'];
            $equipmentChanged->installationid     = $installationID;
            $equipmentChanged->installedat        = $installedAt->copy();
            $equipmentChanged->main               = $attachmentData['main'] === 'true';
            $equipmentChanged->hiddenfromcustomer = $attachmentData['hiddenfromcustomer'];
            $equipmentChanged->installationsite   = $attachmentData['installationsite'];
            $equipmentChanged->hasblocking        = $attachmentData['hasblocking'] === 'true';
            $equipmentChanged->blockingsite       = $attachmentData['blockingsite'];
            $equipmentChanged->hasibutton         = $attachmentData['hasibutton'] === 'true';
            $equipmentChanged->ibuttonsite        = $attachmentData['ibuttonsite'];
            $equipmentChanged->ibuttonsmemsize    = $ibuttonsMemSize;
            $equipmentChanged->ibuttonactive      = $attachmentData['hasibutton'] === 'true';
            $equipmentChanged->hassiren           = $attachmentData['hassiren'] === 'true';
            $equipmentChanged->sirensite          = $attachmentData['sirensite'];
            $equipmentChanged->panicbuttonsite    = $attachmentData['panicbuttonsite'];
            $equipmentChanged->updatedbyuserid    = $userID;
            $equipmentChanged->save();

            // Determina se o novo cliente precisa ser vinculado a
            // associação
            if (intval($attachmentData['payerentitytypeid']) === 3) {
              // Vincula o novo cliente a associação
              $this->joinAnAssociation(
                $attachmentData['customerpayerid'],
                $attachmentData['subsidiarypayerid'],
                $attachmentData['customerid'],
                $attachmentData['subsidiaryid'],
                $installedAt
              );
            }

            // Força a atualização do equipamento no grid para
            // refletir as mudanças aqui realizadas
            $this->updateVehicleOnGrid(
              $equipmentID,
              $vehicleID,
              $attachmentData['plate'],
              $attachmentData['customerid'],
              $attachmentData['subsidiaryid'],
              $attachmentData['customerpayerid'],
              $attachmentData['subsidiarypayerid'],
              $attachmentData['main'] === 'true'
            );            
          } else {
            if ($replace) {
              // Estamos substituindo o equipamento, então analisa as
              // condições
              $replacedAt = Carbon::createFromFormat('d/m/Y H:i:s',
                $replaceData['replacedat'] . ' 00:00:00'
              );
              $uninstalledAtDate = $replacedAt->copy();

              $deposits = Deposit::where("contractorid", '=', $contractor->id)
                ->whereRaw("devicetype IN ('Equipment', 'Both')")
                ->orderBy('name')
                ->get([
                    'depositid AS id',
                    'name',
                    'master'
                  ])
              ;
              $depositID = $deposits[0]->id;
              foreach ($deposits as $deposit) {
                if ($deposit->master) {
                  $depositID = $deposit->id;
          
                  break;
                }
              }
              $storageLocation   = 'StoredOnDeposit';
              $newEquipmentID = $replaceData['newequipmentid'];

              // Desinstala o equipamento sendo substituído
              $uninstalledEquipment = Equipment::findOrFail($equipmentID);
              $installationID = $uninstalledEquipment->installationid;
              $uninstalledEquipment->vehicleid = null;
              $uninstalledEquipment->installationid = null;
              $uninstalledEquipment->storagelocation = $storageLocation;
              $uninstalledEquipment->depositid = $depositID;
              $uninstalledEquipment->customerpayerid = null;
              $uninstalledEquipment->subsidiarypayerid = null;
              $uninstalledEquipment->updatedbyuserid = $userID;
              $uninstalledEquipment->save();

              // Associa o novo equipamento
              $newEquipment = Equipment::findOrFail($newEquipmentID);
              $newEquipment->vehicleid = $vehicleID;
              $newEquipment->storagelocation    = 'Installed';
              $newEquipment->customerpayerid    = $attachmentData['customerpayerid'];
              $newEquipment->subsidiarypayerid  = $attachmentData['subsidiarypayerid'];
              $newEquipment->subsidiarypayerid  = $attachmentData['subsidiarypayerid'];
              $newEquipment->installationid     = $installationID;
              $newEquipment->installedat        = $replacedAt->copy();
              $newEquipment->main               = $attachmentData['main'] === 'true';
              $newEquipment->hiddenfromcustomer = $attachmentData['hiddenfromcustomer'];
              $newEquipment->installationsite   = $attachmentData['installationsite'];
              $newEquipment->hasblocking        = $attachmentData['hasblocking'] === 'true';
              $newEquipment->blockingsite       = $attachmentData['blockingsite'];
              $newEquipment->hasibutton         = $attachmentData['hasibutton'] === 'true';
              $newEquipment->ibuttonsite        = $attachmentData['ibuttonsite'];
              $newEquipment->ibuttonsmemsize    = $ibuttonsMemSize;
              $newEquipment->ibuttonactive      = $attachmentData['hasibutton'] === 'true';
              $newEquipment->hassiren           = $attachmentData['hassiren'] === 'true';
              $newEquipment->sirensite          = $attachmentData['sirensite'];
              $newEquipment->panicbuttonsite    = $attachmentData['panicbuttonsite'];
              $newEquipment->updatedbyuserid    = $userID;
              $newEquipment->save();

              // Substituímos o equipamento se estiver na tabela de
              // integração com serviços de terceiros
              EquipmentsToGetHistory::where('equipmentid', $equipmentID)
                ->update([
                  'equipmentid' => $newEquipmentID
                ])
              ;

              // Atualiza o ID do equipamento na tabela de equipamentos
              // autorizados para outros ususários
              AuthorizedEquipment::where('equipmentid', $equipmentID)
                ->update([
                  'equipmentid' => $newEquipmentID
                ])
              ;

              // Descobrimos o registro que armazena as informações da
              // instalação
              $installationRecord =
                InstallationRecord::where('equipmentid', $equipmentID)
                                  ->where('contractorid', $contractor->id)
                                  ->where('vehicleid', $vehicleID)
                                  ->where('installationid', $installationID)
                                  ->orderBy('uninstalledat')
                                  ->latest()
                                  ->first()
              ;
              $installationRecordID = $installationRecord->installationrecordid;
              $installationID = $installationRecord->installationid;
              $endDate = $uninstalledAtDate
                ->format('Y-m-d')
              ;

              // Inserimos a data de desinstalação do rastreador que foi
              // retirado
              $sql = "UPDATE erp.installationRecords
                        SET uninstalledat = '{$endDate}'::Date,
                            updatedat = CURRENT_TIMESTAMP,
                            updatedByUserID = {$userID}
                      WHERE installationRecordID = {$installationRecordID};";
              $this->DB->select($sql);

              // Retira a informação do veículo do equipamento no grid
              // para refletir as mudanças aqui realizadas
              $sql = ""
                . "UPDATE public.lastPositions"
                . "   SET customerID = NULL,"
                . "       subsidiaryID = NULL,"
                . "       customerPayerID = NULL,"
                . "       subsidiaryPayerID = NULL,"
                . "       vehicleID = NULL,"
                . "       plate = NULL"
                . " WHERE equipmentID = {$equipmentID};"
              ;
              $this->DB->select($sql);

              // Força a atualização do equipamento no grid para
              // refletir as mudanças aqui realizadas
              $this->updateVehicleOnGrid(
                $newEquipmentID,
                $vehicleID,
                $attachmentData['plate'],
                $attachmentData['customerid'],
                $attachmentData['subsidiaryid'],
                $attachmentData['customerpayerid'],
                $attachmentData['subsidiarypayerid'],
                $attachmentData['main'] === 'true'
              );            
            } else if ($transfer) {
              // Estamos transferindo o equipamento, então analisa as
              // condições
              $transferAt = Carbon::createFromFormat('d/m/Y H:i:s',
                $transferData['transferat'] . ' 00:00:00'
              );
              
              // Determina se o contrato deve ser encerrado
              $terminate = filter_var(
                $transferData['terminate'],
                FILTER_VALIDATE_BOOLEAN
              );
              $close = ($terminate === true)
                ? true
                : (
                    (filter_var($transferData['notclose'], FILTER_VALIDATE_BOOLEAN))
                      ? false
                      : true
                  )
              ;

              // Determina se não deve ser cobrada multa por quebra do
              // período de fidelidade
              $notChargeLoyaltyBreak = null;
              if (array_key_exists('notchargeloyaltybreak', $transferData)) {
                $notChargeLoyaltyBreak = $transferData['notchargeloyaltybreak'];
              }
              
              // Determina outros valores sobre o contrato para
              // subsídio de nossas análises
              $amountOfPayerContracts = intval($transferData['amountOfPayerContracts']);
              $amountOfItensInContract = intval($transferData['amountOfItensInContract']);

              // Determina se o cliente anterior estava vinculado a uma
              // associação
              if (intval($attachmentData['payerentitytypeid']) === 3) {
                // O cliente anterior estava vinculado a uma
                // associação, então lida com o vínculo com ela
                $this->unjoinFromAssociation(
                  $vehicleID,
                  $attachmentData['customerpayerid'],
                  $attachmentData['subsidiarypayerid'],
                  $vehicle['customerid'],
                  $vehicle['subsidiaryid'],
                  $transferAt
                );
              }

              // Registramos a informação de término do contrato do
              // cliente pagante atual. Localizamos o registro de
              // instalação
              $lastInstallationRecord =
                InstallationRecord::where('equipmentid', $equipmentID)
                  ->where('contractorid', $contractor->id)
                  ->where('vehicleid', $vehicleID)
                  ->where('installationid', $attachmentData['installationid'])
                  ->whereNull('uninstalledat')
                  ->first()
              ;

              // Informamos que a cobrança deve ser realizada até um dia
              // antes da transferência
              $installationRecordID = $lastInstallationRecord->installationrecordid;
              $installedAt = Carbon::createFromFormat('d/m/Y H:i:s',
                $attachmentData['installedat'] . ' 00:00:00'
              );
              if ($installedAt->greaterThan($transferAt->copy()->subDay())) {
                // Isto ocorre quando a data de instalação e
                // desinstalação são do mesmo dia, então evitamos que
                // ocorra a inserção de uma data de desinstalação menor
                // do que a data de instalação
                $endDate = $installedAt
                  ->format('Y-m-d')
                ;
              } else {
                $endDate = $transferAt
                  ->copy()
                  ->subDay()
                  ->format('Y-m-d')
                ;
              }
              $sql = ""
                . "UPDATE erp.installationrecords
                      SET uninstalledAt = '{$endDate}'::Date,
                          updatedAt = CURRENT_TIMESTAMP,
                          updatedByUserID = {$userID}
                    WHERE installationRecordID = {$installationRecordID};"
              ;
              $this->DB->select($sql);

              if ($close) {
                // Encerra o item de contrato do cliente do qual estamos
                // transferindo o equipamento
                $complement = '';
                if ($notChargeLoyaltyBreak) {
                  $complement = "notChargeLoyaltyBreak = {$notChargeLoyaltyBreak},";
                }
                $sql = ""
                  . "UPDATE erp.installations
                        SET endDate = '{$endDate}'::Date,
                            updatedAt = CURRENT_TIMESTAMP, {$complement}
                            updatedByUserID = {$userID}
                      WHERE installationID = {$attachmentData['installationid']};"
                ;
                $this->DB->select($sql);
              }

              if ($terminate) {
                // Desativamos o contrato do cliente do qual estamos
                // transferindo a cobrança
                $contractID = intval($attachmentData['contractid']);
                $sql = ""
                  . "UPDATE erp.contracts
                        SET endDate = '{$endDate}'::Date,
                            updatedAt = CURRENT_TIMESTAMP,
                            updatedByUserID = {$userID}
                      WHERE contractID = {$contractID};"
                ;
                $this->DB->select($sql);

                // Verifica se o cliente possui um ou mais
                // veículos ainda ativos e/ou se ele possui
                // veículos para os quais ele é pagante
                $remainingVehicles = Vehicle::where('customerid', '=',
                      $attachmentData['customerpayerid']
                    )
                  ->where('blocked', 'false')
                  ->count()
                ;
                if ( ($remainingVehicles == 0) &&
                     ($amountOfPayerContracts == 1) &&
                     ($amountOfItensInContract == 1) ) {
                  // Desativamos o cliente também
                  $customer = Entity::findOrFail($attachmentData['customerpayerid']);
                  $customer->blocked = true;
                  $customer->updatedbyuserid = $userID;
                  $customer->save();
                }
              }

              // Determina se precisa criar um novo item de instalação
              // no contrato
              $newInstallationID = intval($transferData['newinstallationid']);
              if ($newInstallationID === 0) {
                // Cria um novo item de instalação no contrato
                $contractID = intval($transferData['newcontractid']);
                $contract = Contract::join('entities AS customers',
                      'contracts.customerid', '=', 'customers.entityid'
                    )
                  ->where('contracts.contractorid', '=', $contractor->id)
                  ->where('contracts.contractid', '=', $contractID)
                  ->get([
                      'contracts.contractid',
                      $this->DB->raw('getContractNumber(contracts.createdat) AS contractnumber'),
                      'contracts.planid',
                      'contracts.subscriptionplanid',
                      'contracts.monthprice',
                      'contracts.customerid',
                      'customers.name AS customername',
                      'contracts.subsidiaryid'
                    ])
                ;

                if ( $contract->isEmpty() ) {
                  throw new ModelNotFoundException("Não temos nenhum contrato com "
                    . "o código {$contractID} cadastrado"
                  );
                }

                $newInstallation = new Installation();
                $newInstallation->contractorid = $contractor->id;
                $newInstallation->customerid = $contract[0]->customerid;
                $newInstallation->subsidiaryid = $contract[0]->subsidiaryid;
                $newInstallation->contractid = $contractID;
                $newInstallation->planid = $contract[0]->planid;
                $newInstallation->subscriptionplanid = $contract[0]->subscriptionplanid;
                $newInstallation->monthprice = $contract[0]->monthprice;
                $newInstallation->createdbyuserid = $userID;
                $newInstallation->updatedbyuserid = $userID;
                $newInstallation->save();

                // Recupera o ID do novo item de instalação
                $newInstallationID = $newInstallation->installationid;
              
                // Atualizamos o número da instalação
                $sql = ""
                  . "UPDATE erp.installations
                        SET installationnumber = erp.generateInstallationNumber(contractorID, contractID, installationID)
                      WHERE installations.installationID = {$newInstallationID};"
                ;
                $this->DB->select($sql);
              }

              // Agora inserimos a informação do início do
              // relacionamento com o novo cliente
              $installationRecord = new InstallationRecord();
              $installationRecord->contractorid = $contractor->id;
              $installationRecord->installedat = $transferAt
                ->copy()
              ;
              $installationRecord->equipmentid = $equipmentID;
              $installationRecord->vehicleid = $vehicleID;
              $installationRecord->installationid = $newInstallationID;
              $installationRecord->createdbyuserid = $userID;
              $installationRecord->updatedbyuserid = $userID;
              $installationRecord->save();

              // Modificamos o equipamento para refletir isto também
              $equipmentChanged = Equipment::findOrFail($equipmentID);
              $equipmentChanged->main               = $attachmentData['main'] === 'true';
              $equipmentChanged->hiddenfromcustomer = $attachmentData['hiddenfromcustomer'] === 'true';
              $equipmentChanged->customerpayerid    = $transferData['newcustomerpayerid'];
              $equipmentChanged->subsidiarypayerid  = $transferData['newsubsidiarypayerid'];
              $equipmentChanged->installationid     = $newInstallationID;
              $equipmentChanged->installedat        = $transferAt
                ->copy()
              ;
              $equipmentChanged->installationsite  = $attachmentData['installationsite'];
              $equipmentChanged->hasblocking       = $attachmentData['hasblocking'] === 'true';
              $equipmentChanged->blockingsite      = $attachmentData['blockingsite'];
              $equipmentChanged->hasibutton        = $attachmentData['hasibutton'] === 'true';
              $equipmentChanged->ibuttonsite       = $attachmentData['ibuttonsite'];
              $equipmentChanged->hassiren          = $attachmentData['hassiren'] === 'true';
              $equipmentChanged->sirensite         = $attachmentData['sirensite'];
              $equipmentChanged->panicbuttonsite   = $attachmentData['panicbuttonsite'];
              $equipmentChanged->updatedbyuserid   = $userID;
              $equipmentChanged->save();

              // Analisamos o item de contrato para modificar
              $newInstallation = Installation::findOrFail(
                $newInstallationID
              );
              if ($newInstallation->startdate == NULL) {
                // Modificamos o item de contrato para refletir isto
                // também
                $startDate = $transferAt
                  ->copy()
                  ->format('Y-m-d')
                ;
                $sql = ""
                  . "UPDATE erp.installations
                        SET startDate = '{$startDate}'::Date,
                            updatedAt = CURRENT_TIMESTAMP,
                            updatedByUserID = {$userID}
                      WHERE installationID = {$newInstallationID};"
                ;
                $this->DB->select($sql);
              }

              // Determina se o novo cliente precisa ser vinculado a
              // associação
              if (intval($transferData['newpayerentitytypeid']) === 3) {
                // Vincula o novo cliente a associação
                $this->joinAnAssociation(
                  $transferData['newcustomerpayerid'],
                  $transferData['newsubsidiarypayerid'],
                  $attachmentData['customerid'],
                  $attachmentData['subsidiaryid'],
                  $transferAt
                );
              }

              // Força a atualização do equipamento no grid para
              // refletir as mudanças aqui realizadas
              $this->updateVehicleOnGrid(
                $equipmentID,
                $vehicleID,
                $attachmentData['plate'],
                $attachmentData['customerid'],
                $attachmentData['subsidiaryid'],
                $transferData['newcustomerpayerid'],
                $transferData['newsubsidiarypayerid'],
                $attachmentData['main'] === 'true'
              );
            } else {
              // Não houve transferência, analisa outras modificações
              // que possam ter ocorrido

              if ($attachmentData['main'] !== $vehicle['main']) {
                // O tipo de instalação foi modificada, então atualiza
                
                // Determinamos se existem outros rastreadores
                // vinculados ao veículo para lidar com a questão do
                // rastreador principal
                $sql = "SELECT COUNT(*) FILTER (WHERE main = true) AS maincount,
                               COUNT(*) FILTER (WHERE main = false) AS contingencycount
                          FROM equipments
                         WHERE vehicleID = {$vehicleID}
                           AND equipmentID <> {$equipmentID};";
                $equipmentInfo = $this->DB->select($sql)[0];

                if (($equipmentInfo->maincount > 0) && $attachmentData['main'] === 'true') {
                  // Existem outro rastreador vinculado ao veículo que
                  // era o principal, então modificamos ele para ser o
                  // rastreador de contingência e este para ser o
                  // principal
                  $sql = ""
                    . "UPDATE erp.equipments
                          SET main = false,
                              updatedAt = CURRENT_TIMESTAMP,
                              updatedByUserID = {$userID}
                        WHERE vehicleID = {$vehicleID}
                          AND main = true;"
                  ;
                  $this->DB->select($sql);

                  // Também atualizamos a tabela de última posição para
                  // refletir a mudança no monitoramento
                  $sql = ""
                    . "UPDATE public.lastpositions
                          SET mainTracker = false
                        WHERE vehicleID = {$vehicleID}
                          AND mainTracker = true;"
                  ;
                  $this->DB->select($sql);
                }
              }

              // Modifica apenas a data de instalação e o local de
              // instalação
              $equipmentChanged = Equipment::findOrFail($equipmentID);
              $equipmentChanged->main               = $attachmentData['main'] === 'true';
              $equipmentChanged->hiddenfromcustomer = $attachmentData['hiddenfromcustomer'] === 'true';
              $equipmentChanged->installedat        = Carbon::createFromFormat('d/m/Y',
                $attachmentData['installedat']
              );
              $equipmentChanged->installationsite   = $attachmentData['installationsite'];
              $equipmentChanged->hasblocking        = $attachmentData['hasblocking'] === 'true';
              $equipmentChanged->blockingsite       = $attachmentData['blockingsite'];
              $equipmentChanged->hasibutton         = $attachmentData['hasibutton'] === 'true';
              $equipmentChanged->ibuttonsite        = $attachmentData['ibuttonsite'];
              $equipmentChanged->hassiren           = $attachmentData['hassiren'] === 'true';
              $equipmentChanged->sirensite          = $attachmentData['sirensite'];
              $equipmentChanged->panicbuttonsite    = $attachmentData['panicbuttonsite'];
              $equipmentChanged->updatedbyuserid    = $userID;
              $equipmentChanged->save();
            }
          }

          // =========================================================

          // Efetiva a transação
          $this->DB->commit();

          if ($attached) {
            // Registra o sucesso
            $this->info("O veículo placa '{plate}' teve sua associação "
              . "com o equipamento número de série '{serialnumber}' "
              . "modificada com sucesso.",
              [
                'plate' => $attachmentData['plate'],
                'serialnumber' => $attachmentData['serialnumber']
              ]
            );

            // Alerta o usuário
            $this->flash(
              "success",
              "O veículo placa <i>'{plate}'</i> teve sua associação "
              . "com o equipamento número de série "
              . "<i>'{serialnumber}'</i> modificada com sucesso.",
              [
                'plate' => $attachmentData['plate'],
                'serialnumber' => $attachmentData['serialnumber']
              ]
            );
          } else {
            // Registra o sucesso
            $this->info("O veículo placa '{plate}' foi associado "
              . "com sucesso ao equipamento número de série "
              . "'{serialnumber}'.",
              [
                'plate' => $attachmentData['plate'],
                'serialnumber' => $attachmentData['serialnumber']
              ]
            );

            // Alerta o usuário
            $this->flash(
              "success",
              "O veículo placa <i>'{plate}'</i> foi associado com "
              . "sucesso ao equipamento número de série "
              . "<i>'{serialnumber}'</i>.",
              [
                'plate' => $attachmentData['plate'],
                'serialnumber' => $attachmentData['serialnumber']
              ]
            );
          }

          // Registra o evento
          $this->debug("Redirecionando para {routeName}",
            [
              'routeName' => 'ERP\Cadastre\Vehicles'
            ]
          );

          // Redireciona para a página de gerenciamento de veículos
          return $this->redirect($response, 'ERP\Cadastre\Vehicles');
        }
        catch(QueryException | Exception $exception)
        {
          // Reverte (desfaz) a transação
          $this->DB->rollBack();

          if ($attached) {
            // Registra o erro
            $this->error("Não foi possível modificar a associação do "
              . "veículo placa '{plate}' com o equipamento número de "
              . "série '{serialnumber}'. Erro interno: {error}",
              [
                'plate' => $attachmentData['plate'],
                'serialnumber' => $attachmentData['serialnumber'],
                'error' => $exception->getMessage()
              ]
            );

            // Alerta o usuário
            $this->flashNow(
              "error",
              "Não foi possível modificar a associação do veículo com "
              . "o equipamento número de série <i>'{serialnumber}'</i>. "
              . "Erro interno.",
              [
                'serialnumber' => $attachmentData['serialnumber']
              ]
            );
          } else {
            // Registra o erro
            $this->error("Não foi possível associar o veículo placa "
              . "'{plate}' ao equipamento número de série "
              . "'{serialnumber}'. Erro interno: {error}",
              [
                'plate' => $attachmentData['plate'],
                'serialnumber' => $attachmentData['serialnumber'],
                'error' => $exception->getMessage()
              ]
            );

            if ($exception->getCode() == 23505) {
              // Alerta o usuário
              $this->flashNow(
                "error",
                "Não foi possível associar o veículo ao equipamento "
                . "número de série <i>'{serialnumber}'</i>. Este "
                . "equipamento já está vinculado à outro veículo.",
                [
                  'serialnumber' => $attachmentData['serialnumber']
                ]
              );
            } else {
              // Alerta o usuário
              $this->flashNow(
                "error",
                "Não foi possível associar o veículo ao equipamento "
                . "número de série <i>'{serialnumber}'</i>. Erro "
                . "interno.",
                [
                  'serialnumber' => $attachmentData['serialnumber']
                ]
              );
            }
          }
        }
      } else {
        $this->debug('Os dados do vínculo são INVÁLIDOS');
        $messages = $this->validator->getFormatedErrors();
        foreach ($messages AS $message) {
          $this->debug($message);
        }

        // Recupera os dados para a associação do veículo à um
        // equipamento
        $attachmentData = $this->validator->getValues();
        $transfer = ($attachmentData['transfer'] == 'true');

        if ($transfer) {
          $newCustomerPayerID = intval($attachmentData['newcustomerpayerid']);
          $newSubsidiaryPayerID = intval($attachmentData['newsubsidiarypayerid']);

          if ( ($newCustomerPayerID > 0) && ($newSubsidiaryPayerID > 0) ) {
            // Recuperamos os contratos deste novo cliente
            $newContracts = $this->getContracts(
              $newCustomerPayerID,
              $newSubsidiaryPayerID,
              false,
              null
            );
            $newContracts = $newContracts
              ->toArray()
            ;

            $newContractID = intval($attachmentData['newcontractid']);

            if ($newContractID > 0) {
              $newInstallations = $this->getInstallations(
                $newContractID,
                false,
                0
              );
            }
          }
        }
      }
    } else {
      // Carrega os dados atuais
      $this->validator->setValues($vehicle);

      if ($attached) {
        // Define inicialmente que não estamos substituindo
        $this->validator->setValue('replace', 'false');

        // Define inicialmente que não estamos transferindo
        $this->validator->setValue('transfer', 'false');

        // Define a data atual como data de transferência
        $today = Carbon::now()->format('d/m/Y');
        $this->validator->setValue('replacedat', $today);
        $this->validator->setValue('transferat', $today);
      }
    }

    // Exibe um formulário para associação de um veículo com um
    // equipamento

    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ERP\Home')
    );
    $this->breadcrumb->push('Cadastro', '');
    $this->breadcrumb->push('Veículos', 
      $this->path('ERP\Cadastre\Vehicles')
    );
    if ($attached) {
      $this->breadcrumb->push('Modificar associação',
        $this->path('ERP\Cadastre\Vehicles\Attach', [
          'vehicleID' => $vehicleID ]
        )
      );
    } else {
      $this->breadcrumb->push('Associar',
        $this->path('ERP\Cadastre\Vehicles\Attach', [
          'vehicleID' => $vehicleID ]
        )
      );
    }

    // Registra o acesso
    if ($attached) {
      $this->info("Acesso à modificação da associação do veículo placa "
        . "'{plate}' ao equipamento número de série '{serialnumber}'.",
        [
          'plate' => $vehicle['plate'],
          'serialnumber' => $vehicle['serialnumber']
        ]
      );
    } else {
      $this->info("Acesso à associação do veículo placa '{plate}' a um "
        . "equipamento.",
        [
          'plate' => $vehicle['plate']
        ]
      );
    }

    // Renderiza a página
    return $this->render($request, $response,
      'erp/cadastre/vehicles/attach.twig',
      [
        'formMethod' => 'PUT',
        'attached' => $attached,
        'contracts' => $contracts,
        'installations' => $installations,
        'newContracts' => $newContracts,
        'newInstallations' => $newInstallations
      ]
    );
  }

  /**
   * Desvincula o veículo do equipamento.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   * @param array $args
   *   Os argumentos da requisição
   *
   * @return Response $response
   */
  public function detach(
    Request $request,
    Response $response,
    array $args
  )
  {
    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();
    $contractorID = $contractor->id;

    // Recupera as informações do equipamento que está sendo desinstalado
    $equipmentID = $args['equipmentID'];

    // Recupera os dados da requisição
    $postParams   = $request->getParsedBody();

    // Recupera as informações do local de armazenamento
    $uninstalledAtDate = $postParams['uninstalledAt'];
    $terminate         = $postParams['terminate'];
    $storageLocation   = $postParams['storageLocation'];
    $depositID         = $postParams['depositID'];
    $technicianID      = $postParams['technicianID'];
    $serviceproviderID = $postParams['serviceproviderID'];

    try
    {
      // Recupera as informações do veículo de onde está sendo
      // desinstalado o equipamento e do próprio equipamento
      $link = Equipment::join('vehicles',
          function($join) {
            $join->on('equipments.vehicleid', '=',
              'vehicles.vehicleid'
            );
            $join->on('equipments.contractorid', '=',
              'vehicles.contractorid'
            );
          })
        ->join("entities AS customer", "equipments.customerpayerid",
            '=', "customer.entityid"
          )
        ->where('equipments.equipmentid', '=', $equipmentID)
        ->where('equipments.contractorid', '=', $contractorID)
        ->get([
            'equipments.serialnumber',
            'vehicles.vehicleid',
            'vehicles.plate',
            'equipments.installationid',
            'equipments.customerpayerid',
            'customer.entitytypeid AS payerentitytypeid',
            'equipments.subsidiarypayerid',
            'vehicles.customerid',
            'vehicles.subsidiaryid'
          ])
      ;

      if ( $link->isEmpty() ) {
        throw new ModelNotFoundException("Não temos nenhum equipamento "
          . "com o código {$equipmentID} cadastrado."
        );
      }

      $link = $link
        ->first()
        ->toArray()
      ;
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar o equipamento código "
        . "{equipmentID}.",
        [
          'equipmentID' => $equipmentID
        ]
      );

      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'NOK',
            'params' => $request->getParams(),
            'message' => "Não foi possível localizar os dados "
              . "equipamento que se está desinstalando.",
            'data' => null
          ])
      ;
    }

    // Registra o acesso
    $this->info("Processando à desinstalação do equipamento nº de "
      . "série '{serialnumber}' do veículo placa '{plate}' ocorrido em "
      . "{uninstalledAt}.",
      [
        'serialnumber' => $link['serialnumber'],
        'plate' => $link['plate'],
        'uninstalledAt' => $uninstalledAtDate
      ]
    );

    try
    {
      // Iniciamos a transação
      $this->DB->beginTransaction();

      // Recupera os dados do equipamento sendo desinstalado
      $equipment = Equipment::findOrFail($equipmentID);

      // Retiramos as informações do veículo
      $vehicleID = $link['vehicleid'];
      $equipment->vehicleid = null;

      // Adicionamos as informações do responsável pela desinstalação
      $equipment->updatedbyuserid =
        $this->authorization->getUser()->userid
      ;

      // Adicionamos as informações do local onde o equipamento estará
      // armazenado
      $equipment->storagelocation = $storageLocation;
      switch ($storageLocation) {
        case 'StoredWithTechnician':
          // Ficará de posse do técnico, então informa o seu ID
          $equipment->technicianid = $technicianID;

          break;
        case 'StoredWithServiceProvider':
          // Ficará de posse do prestador de serviços, então informa o
          // seu ID
          $equipment->serviceproviderid = $serviceproviderID;

          break;
        
        default:
          // Ficará armazenado num depósito
          $equipment->depositid = $depositID;

          break;
      }

      // Retiramos as informações do pagante
      $equipment->customerpayerid = null;
      $equipment->subsidiarypayerid = null;

      $equipment->save();

      // Retira o equipamento da tabela de integração com a STC
      EquipmentsToGetHistory::where('equipmentid', $equipmentID)
        ->where('contractorid', $contractorID)
        ->where('platform', 'STC')
        ->delete()
      ;

      // Retira o equipamento de usuários para os quais foi autorizado
      AuthorizedEquipment::where('equipmentid', $equipmentID)
        ->where('contractorid', $contractorID)
        ->delete()
      ;

      // Descobrimos o registro que armazena as informações da instalação
      $installationRecord =
        InstallationRecord::where('equipmentid', $equipmentID)
                          ->where('contractorid', $contractorID)
                          ->where('vehicleid', $vehicleID)
                          ->where('installationid', $link['installationid'])
                          ->orderBy('uninstalledat')
                          ->latest()
                          ->first()
      ;
      $installationRecordID = $installationRecord->installationrecordid;
      $installationID = $installationRecord->installationid;
      $uninstalledAt = Carbon::createFromFormat('d/m/Y H:i:s',
        $uninstalledAtDate . ' 00:00:00'
      );
      $endDate = $uninstalledAt
        ->format('Y-m-d')
      ;
      $userID = $this->authorization->getUser()->userid;

      // Modificamos a data de desinstalação deste rastreador
      $sql = "UPDATE erp.installationRecords
                 SET uninstalledat = '{$endDate}'::Date,
                     updatedat = CURRENT_TIMESTAMP,
                     updatedByUserID = {$userID}
               WHERE installationRecordID = {$installationRecordID};";
      $this->DB->select($sql);

      if ($terminate == "true") {
        // Encerra o item de contrato do qual desvinculamos este
        // rastreador
        $sql = ""
          . "UPDATE erp.installations
                SET endDate = '{$endDate}'::Date,
                    updatedAt = CURRENT_TIMESTAMP,
                    updatedByUserID = {$userID}
              WHERE installationID = {$installationID};"
        ;
        $this->DB->select($sql);

        // Verifica se o contrato para o qual este item pertence possui
        // outros itens de contrato ativo
        $installation = Installation::findOrFail($installationID);
        $contractID   = $installation->contractid;
        $remainingInstallations = Installation::where('contractid', '=',
              $contractID
            )
          ->whereNull('enddate')
          ->count()
        ;
        if ($remainingInstallations == 0) {
          // Desativamos o contrato também
          $sql = ""
            . "UPDATE erp.contracts
                  SET endDate = '{$endDate}'::Date,
                      updatedAt = CURRENT_TIMESTAMP,
                      updatedByUserID = {$userID}
                WHERE contractID = {$contractID};"
          ;
          $this->DB->select($sql);
        }
      }

      // Determina se o cliente estava vinculado a uma associação
      if (intval($link['payerentitytypeid']) === 3) {
        // O cliente estava vinculado a uma associação, então lida com o
        // vínculo com ela
        $this->unjoinFromAssociation(
          $vehicleID,
          $link['customerpayerid'],
          $link['subsidiarypayerid'],
          $link['customerid'],
          $link['subsidiaryid'],
          $uninstalledAt
        );
      }

      $this->clearVehicleOnGrid($equipmentID);

      // Efetiva a transação
      $this->DB->commit();

      // Registra o sucesso
      $this->info("O equipamento nº de série '{serialnumber}' foi "
        . "desassociado do veículo placa '{plate}' com sucesso.",
        [
          'serialnumber' => $link['serialnumber'],
          'plate' => $link['plate']
        ]
      );

      // Informa que a desassociação foi realizada com sucesso
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getParams(),
            'message' => "O equipamento foi desassociado do veículo "
              . "placa {$link['plate']}",
            'data' => "Delete"
          ])
      ;
    }
    catch(ModelNotFoundException | QueryException | Exception $exception)
    {
      // Reverte (desfaz) a transação
      $this->DB->rollBack();

      // Registra o erro
      $this->error("Não foi possível desassociar o equipamento nº de "
        . "série '{serialnumber}' do veículo placa '{plate}'. Erro "
        . "interno: {error}",
        [
          'serialnumber' => $link['serialnumber'],
          'plate' => $link['plate'],
          'error' => $exception->getMessage()
        ]
      );

      $message = "Não foi possível desassociar o equipamento. Erro "
        . "interno."
      ;
    }

    return $response
      ->withHeader('Content-type', 'application/json')
      ->withJson([
          'result' => 'NOK',
          'params' => $request->getParams(),
          'message' => $message,
          'data' => null
        ])
    ;
  }

  /**
   * Remove o veículo.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   * @param array $args
   *   Os argumentos da requisição
   *
   * @return Response $response
   */
  public function delete(
    Request $request,
    Response $response,
    array $args
  )
  {
    // Recupera as informações do veículo a ser removido
    $vehicleID = $args['vehicleID'];

    // Registra o acesso
    $this->debug("Processando à remoção de veículo.");

    try
    {
      // Recupera o veículo
      $vehicle = Vehicle::findOrFail($vehicleID);

      // Recupera o local de armazenamento dos anexos
      $uploadDirectory =
        $this->container['settings']['storage']['attachments']
      ;

      // Agora apaga o veículo
      
      // Iniciamos a transação
      $this->DB->beginTransaction();

      // Remove o veículo e seus anexos
      $vehicle->deleteCascade($uploadDirectory);

      // Efetiva a transação
      $this->DB->commit();

      // Registra o sucesso
      $this->info("O veículo placa '{plate}' foi removido com sucesso.",
        [
          'plate' => $vehicle->plate
        ]
      );

      // Informa que a remoção foi realizada com sucesso
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getParams(),
            'message' => "Removido o veículo placa {$vehicle->plate}",
            'data' => "Delete"
          ])
      ;
    }
    catch(ModelNotFoundException $exception)
    {
      // Reverte (desfaz) a transação
      $this->DB->rollBack();

      // Registra o erro
      $this->error("Não foi possível localizar o veículo código "
        . "{vehicleID} para remoção.",
        [
          'vehicleID' => $vehicleID
        ]
      );

      $message = "Não foi possível localizar o veículo para remoção.";
    }
    catch(QueryException | Exception $exception)
    {
      // Reverte (desfaz) a transação
      $this->DB->rollBack();

      // Registra o erro
      $this->error("Não foi possível remover as informações do veículo "
        . "placa '{plate}'. Erro interno: {error}",
        [
          'plate' => $vehicle->plate,
          'error' => $exception->getMessage()
        ]
      );

      $message = "Não foi possível remover o veículo. Erro interno.";
    }

    return $response
      ->withHeader('Content-type', 'application/json')
      ->withJson([
          'result' => 'NOK',
          'params' => $request->getParams(),
          'message' => $message,
          'data' => null
        ]
    );
  }

  /**
   * Alterna o estado do bloqueio de um veículo e/ou de uma
   * unidade/filial deste veículo.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   * @param array $args
   *   Os argumentos da requisição
   *
   * @return Response $response
   */
  public function toggleBlocked(
    Request $request,
    Response $response,
    array $args
  )
  {
    // Registra o acesso
    $this->debug(
      "Processando à mudança do estado de bloqueio de veículo."
    );

    // Recupera o ID
    $vehicleID = $args['vehicleID'];
    $subsidiaryID = isset($args['subsidiaryID'])
      ? $args['subsidiaryID']
      : null
    ;

    try
    {
      $userID = $this->authorization->getUser()->userid;

      // Recupera as informações do veículo
      if (is_null($subsidiaryID)) {
        // Desbloqueia o veículo
        $vehicle = Vehicle::findOrFail($vehicleID);
        $action = $vehicle->blocked
          ? "desbloqueado"
          : "bloqueado"
        ;
        $vehicle->blocked = !$vehicle->blocked;
        $vehicle->updatedbyuserid = $userID;
        $vehicle->save();

        $message = "O veículo placa '{$vehicle->plate}' foi {$action} "
          . "com sucesso."
        ;
      } else {
        // Desbloqueia a unidade/filial
        $vehicle = Vehicle::findOrFail($vehicleID);
        $subsidiary = Subsidiary::findOrFail($subsidiaryID);
        $action     = $subsidiary->blocked?"desbloqueada":"bloqueada";
        $subsidiary->blocked = !$subsidiary->blocked;
        $subsidiary->updatedbyuserid = $this->authorization->getUser()->userid;
        $subsidiary->save();

        $message = "A unidade/filial '{$subsidiary->name}' do veículo "
          . "placa '{$vehicle->plate}' foi {$action} com sucesso."
        ;
      }

      // Registra o sucesso
      $this->info($message);

      // Informa que a mudança foi realizada com sucesso
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getParams(),
            'message' => $message,
            'data' => "Delete"
          ]
      );
    }
    catch(ModelNotFoundException $exception)
    {
      if (is_null($subsidiaryID)) {
        // Registra o erro
        $this->error("Não foi possível localizar o veículo código "
          . "{vehicleID} para alternar o estado do bloqueio.",
          [
            'vehicleID' => $vehicleID
          ]
        );
        $message = "Não foi possível localizar o veículo para alternar "
          . "o estado do bloqueio."
        ;
      } else {
        // Registra o erro
        $this->error("Não foi possível localizar a unidade/filial "
          . "código {subsidiaryID} do veículo código {vehicleID} para "
          . "alternar o estado do bloqueio.",
          [
            'vehicleID' => $vehicleID,
            'subsidiaryID' => $subsidiaryID
          ]
        );

        $message = "Não foi possível localizar a unidade/filial do "
          . "veículo para alternar o estado do bloqueio."
        ;
      }
    }
    catch(QueryException | Exception $exception)
    {
      if (is_null($subsidiaryID)) {
        // Registra o erro
        $this->error("Não foi possível alternar o estado do bloqueio "
          . "do veículo placa '{placa}'. Erro interno: {error}.",
          [
            'placa'  => $vehicle->placa,
            'error' => $exception->getMessage()
          ]
        );

        $message = "Não foi possível alternar o estado do bloqueio do "
          . "veículo. Erro interno."
        ;
      } else {
        // Registra o erro
        $this->error("Não foi possível alternar o estado do bloqueio "
          . "da unidade/filial {subsidiaryName} do veículo placa "
          . "'{plate}'. Erro interno: {error}.",
          [
            'subsidiaryName'  => $subsidiary->name,
            'plate'  => $vehicle->plate,
            'error' => $exception->getMessage()
          ]
        );

        $message = "Não foi possível alternar o estado do bloqueio da "
          . "unidade/filial do veículo. Erro interno no banco de dados."
        ;
      }
    }

    return $response
      ->withHeader('Content-type', 'application/json')
      ->withJson([
          'result' => 'NOK',
          'params' => $request->getParams(),
          'message' => $message,
          'data' => null
        ]
    );
  }


  /**
   * Alterna o estado do monitoramento de um veículo e/ou de um
   * cliente ou ainda de uma unidade/filial deste cliente.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   * @param array $args
   *   Os argumentos da requisição
   *
   * @return Response $response
   */
  public function toggleMonitored(
    Request $request,
    Response $response,
    array $args
  )
  {
    // Registra o acesso
    $this->debug(
      "Processando à mudança do estado de monitoramento de veículo."
    );

    // Recupera o ID
    $vehicleID = $args['vehicleID'];
    $customerID = isset($args['customerID'])
      ? $args['customerID']
      : null
    ;
    $subsidiaryID = isset($args['subsidiaryID'])
      ? $args['subsidiaryID']
      : null
    ;
    $associationID = isset($args['associationID'])
      ? $args['associationID']
      : null
    ;
    $associationUnityID = isset($args['associationUnityID'])
      ? $args['associationUnityID']
      : null
    ;

    try
    {
      $userID = $this->authorization->getUser()->userid;
      $message = "";

      if (is_null($customerID) && is_null($subsidiaryID)) {
        // Desbloqueia o veículo
        $vehicle = Vehicle::findOrFail($vehicleID);
        $action = $vehicle->monitored
          ? "desabilitado"
          : "habilitado"
        ;
        $vehicle->monitored = !$vehicle->monitored;
        $vehicle->updatedbyuserid = $userID;
        $vehicle->save();

        $message = "O monitoramento do veículo placa "
          . "'{$vehicle->plate}' foi {$action} com sucesso."
        ;
      } else {
        $ativate = $vehicleID == 1;
        $action = $ativate
          ? "habilitados"
          : "desabilitados"
        ;
        $params = [
          'monitored' => $ativate,
        ];

        $sql = ""
          . "UPDATE erp.vehicles AS V"
          . "   SET monitored = :monitored"
          . "  FROM erp.equipments AS E"
          . " WHERE V.vehicleID = E.vehicleID"
        ;
        if ($customerID > 0) {
          $sql .= " AND V.customerID = :customerID";
          $params['customerID'] = $customerID;
        }
        if ($subsidiaryID > 0) {
          $sql .= " AND V.subsidiaryID = :subsidiaryID";
          $params['subsidiaryID'] = $subsidiaryID;
        }
        if ($associationID > 0) {
          $sql .= " AND E.customerPayerID = :customerPayerID";
          $params['customerPayerID'] = $associationID;
        }
        if ($associationUnityID > 0) {
          $sql .= " AND E.subsidiaryPayerID = :subsidiaryPayerID";
          $params['subsidiaryPayerID'] = $associationUnityID;
        }

        $message = ($associationID > 0)
          ? "O monitoramento dos veículos do associado foram {$action} com sucesso."
          : "O monitoramento dos veículos do cliente foram {$action} com sucesso."
        ;

        $this->DB->select($sql, $params);
      }

      // Registra o sucesso
      $this->info($message);

      // Informa que a mudança foi realizada com sucesso
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getParams(),
            'message' => $message,
            'data' => "Delete"
          ]
      );
    }
    catch(ModelNotFoundException $exception)
    {
      if (is_null($associationID)) {
        // Registra o erro
        $this->error("Não foi possível localizar o veículo código "
          . "{vehicleID} para alternar o estado do bloqueio.",
          [
            'vehicleID' => $vehicleID
          ]
        );
        $message = "Não foi possível localizar o veículo para alternar "
          . "o estado do bloqueio."
        ;
      } else {
        if ($associationID > 0) {
          // Registra o erro
          $this->error("Não foi possível atualizar o monitoramento dos "
            . "veículos do associado código {customerID} da associação "
            . "código {associationID}.",
            [
              'customerID' => $customerID,
              'associationID' => $associationID
            ]
          );
  
          $message = "Não foi possível localizar o associado para "
            . "alternar o estado do monitoramento."
          ;
        } else {
          // Registra o erro
          $this->error("Não foi possível atualizar o monitoramento dos "
            . "veículos do cliente código {customerID}.",
            [
              'customerID' => $customerID
            ]
          );
  
          $message = "Não foi possível localizar o cliente para "
            . "alternar o estado do monitoramento."
          ;
        }
      }
    }
    catch(QueryException | Exception $exception)
    {
      if (is_null($subsidiaryID)) {
        // Registra o erro
        $this->error("Não foi possível alternar o estado do bloqueio "
          . "do veículo placa '{placa}'. Erro interno: {error}.",
          [
            'placa'  => $vehicle->placa,
            'error' => $exception->getMessage()
          ]
        );

        $message = "Não foi possível alternar o estado do bloqueio do "
          . "veículo. Erro interno."
        ;
      } else {
        // Registra o erro
        $this->error("Não foi possível alternar o estado do bloqueio "
          . "da unidade/filial ID {subsidiaryID} do veículo placa "
          . "'{plate}'. Erro interno: {error}.",
          [
            'subsidiaryID'  => $subsidiaryID,
            'plate'  => $vehicle->plate,
            'error' => $exception->getMessage()
          ]
        );

        $message = "Não foi possível alternar o estado do bloqueio da "
          . "unidade/filial do veículo. Erro interno no banco de dados."
        ;
      }
    }

    return $response
      ->withHeader('Content-type', 'application/json')
      ->withJson([
          'result' => 'NOK',
          'params' => $request->getParams(),
          'message' => $message,
          'data' => null
        ]
    );
  }

  /**
   * Gera um PDF para impressão das informações de um veículo.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   * @param array $args
   *   Os argumentos da requisição
   *
   * @return Response $response
   */
  public function getPDF(
    Request $request,
    Response $response,
    array $args
  )
  {
    // Registra o acesso
    if (array_key_exists('vehicleID', $args)) {
      $this->debug("Processando à geração de PDF com as informações "
        . "cadastrais de um veículo."
      );
    } else {
      $this->debug("Processando à geração de PDF com a relação de "
        . "veículos de um cliente."
      );
    }
    
    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();
    
    // Recupera as informações do cliente
    $contractorID = $contractor->id;
    $customerID   = $args['customerID'];
    $subsidiaryID = $args['subsidiaryID'];
    $vehicleID    = 0;
    if (array_key_exists('vehicleID', $args)) {
      // Recuperamos as informações do último parâmetro
      if (is_numeric($args['vehicleID'])) {
        $vehicleID = $args['vehicleID'];
      } else {
        $reportType = $args['vehicleID'];
      }
    }

    if ($vehicleID > 0) {
      try {
        $vehicle = Vehicle::join('entities AS customer', 'vehicles.customerid',
              '=', 'customer.entityid'
            )
          ->join("entitiestypes", "customer.entitytypeid",
              '=', "entitiestypes.entitytypeid"
            )
          ->join('subsidiaries AS subsidiary', 'vehicles.subsidiaryid',
              '=', 'subsidiary.subsidiaryid'
            )
          ->join('cities AS cityofcustomer', 'subsidiary.cityid',
              '=', 'cityofcustomer.cityid'
            )
          ->leftJoin('cities AS cityofowner', 'vehicles.cityid',
              '=', 'cityofowner.cityid'
            )
          ->join('vehicletypes', 'vehicles.vehicletypeid',
              '=', 'vehicletypes.vehicletypeid'
            )
          ->join('vehiclebrands', 'vehicles.vehiclebrandid',
              '=', 'vehiclebrands.vehiclebrandid'
            )
          ->join('vehiclemodels', 'vehicles.vehiclemodelid',
              '=', 'vehiclemodels.vehiclemodelid'
            )
          ->leftJoin('vehiclesubtypes', 'vehiclemodels.vehiclesubtypeid',
              '=', 'vehiclesubtypes.vehiclesubtypeid'
            )
          ->join('vehiclecolors', 'vehicles.vehiclecolorid',
              '=', 'vehiclecolors.vehiclecolorid'
            )
          ->join('fueltypes', 'vehicles.fueltype',
              '=', 'fueltypes.fueltype'
            )
          ->join('documenttypes AS documenttypeofcustomer',
              'subsidiary.regionaldocumenttype', '=',
              'documenttypeofcustomer.documenttypeid'
            )
          ->leftJoin('documenttypes AS documenttypeofowner',
              'vehicles.regionaldocumenttype', '=',
              'documenttypeofowner.documenttypeid'
            )
          ->join('users AS createduser', 'vehicles.createdbyuserid',
              '=', 'createduser.userid'
            )
          ->join('users AS updateduser', 'vehicles.updatedbyuserid',
              '=', 'updateduser.userid'
            )
          ->where('vehicles.vehicleid', $vehicleID)
          ->where('vehicles.contractorid', '=', $contractor->id)
          ->get([
              'vehicles.vehicleid',
              'vehicles.blocked',
              'vehicles.plate',
              'vehicles.yearfabr',
              'vehicles.yearmodel',
              'vehicles.carnumber',
              'vehicles.fueltype',
              'vehicles.renavam',
              'vehicles.vin',
              'vehicles.customeristheowner',
              'vehicles.subsidiaryid',
              $this->DB->raw("CASE "
                .   "WHEN vehicles.customeristheowner "
                .    "AND entitiestypes.cooperative "
                .   "THEN subsidiary.name "
                .   "WHEN vehicles.customeristheowner "
                .    "AND entitiestypes.juridicalperson "
                .   "THEN customer.name || ' (' || subsidiary.name || ')' "
                .   "WHEN vehicles.customeristheowner "
                .    "AND NOT entitiestypes.juridicalperson "
                .   "THEN customer.name "
                .   "ELSE vehicles.ownername "
                . "END AS ownername"
              ),
              $this->DB->raw("CASE "
                .   "WHEN vehicles.customeristheowner "
                .   "THEN subsidiary.regionaldocumenttype "
                .   "ELSE vehicles.regionaldocumenttype "
                . "END AS regionaldocumenttype"
              ),
              $this->DB->raw("CASE "
                . "  WHEN vehicles.customeristheowner "
                . "    THEN documentTypeOfCustomer.name "
                . "  ELSE documentTypeOfOwner.name "
                . "END AS regionaldocumentname"
              ),
              $this->DB->raw("CASE "
                . "  WHEN vehicles.customeristheowner "
                . "    THEN subsidiary.regionaldocumentnumber "
                . "  ELSE vehicles.regionaldocumentnumber "
                . "END AS regionaldocumentnumber"
              ),
              $this->DB->raw("CASE "
                . "  WHEN vehicles.customeristheowner "
                . "    THEN subsidiary.regionaldocumentstate "
                . "  ELSE vehicles.regionaldocumentstate "
                . "END AS regionaldocumentstate"
              ),
              $this->DB->raw("CASE "
                . "  WHEN vehicles.customeristheowner "
                . "    THEN subsidiary.nationalregister "
                . "  ELSE vehicles.nationalregister "
                . "END AS nationalregister"
              ),
              $this->DB->raw("CASE "
                . "  WHEN vehicles.customeristheowner "
                . "    THEN subsidiary.address "
                . "  ELSE vehicles.address "
                . "END AS address"
              ),
              $this->DB->raw("CASE "
                . "  WHEN vehicles.customeristheowner "
                . "    THEN subsidiary.streetnumber "
                . "  ELSE vehicles.streetnumber "
                . "END AS streetnumber"
              ),
              $this->DB->raw("CASE "
                . "  WHEN vehicles.customeristheowner "
                . "    THEN subsidiary.complement "
                . "  ELSE vehicles.complement "
                . "END AS complement"
              ),
              $this->DB->raw("CASE "
                . "  WHEN vehicles.customeristheowner "
                . "    THEN subsidiary.district "
                . "  ELSE vehicles.district "
                . "END AS district"
              ),
              $this->DB->raw("CASE "
                . "  WHEN vehicles.customeristheowner "
                . "    THEN subsidiary.postalcode "
                . "  ELSE vehicles.postalcode "
                . "END AS postalcode"
              ),
              $this->DB->raw("CASE "
                . "  WHEN vehicles.customeristheowner "
                . "    THEN subsidiary.cityid "
                . "  ELSE vehicles.cityid "
                . "END AS cityid"
              ),
              $this->DB->raw("CASE "
                . "  WHEN vehicles.customeristheowner "
                . "    THEN cityofcustomer.name "
                . "  ELSE cityofowner.name "
                . "END AS cityname"
              ),
              $this->DB->raw("CASE "
                . "  WHEN vehicles.customeristheowner "
                . "    THEN cityofcustomer.state "
                . "  ELSE cityofowner.state "
                . "END AS state"
              ),
              $this->DB->raw("CASE "
                . "  WHEN vehicles.customeristheowner "
                . "    THEN '' "
                . "  ELSE vehicles.email "
                . "END AS email"
              ),
              'vehicles.phonenumber',
              'customer.name AS customername',
              "entitiestypes.juridicalperson",
              "entitiestypes.cooperative",
              'subsidiary.name AS subsidiaryname',
              'vehicletypes.name AS vehicletypename',
              'vehiclebrands.name AS vehiclebrandname',
              'vehiclemodels.name AS vehiclemodelname',
              $this->DB->raw("CASE "
                .   "WHEN vehiclemodels.vehiclesubtypeid IS NULL THEN 0 "
                .   "ELSE vehiclemodels.vehiclesubtypeid "
                . "END AS vehiclesubtypeid"
              ),
              $this->DB->raw("CASE "
                .   "WHEN vehiclemodels.vehiclesubtypeid IS NULL THEN 'Não informado' "
                .   "ELSE vehiclesubtypes.name "
                . "END AS vehiclesubtypename"
              ),
              'vehiclecolors.name AS vehiclecolorname',
              'fueltypes.name AS fueltypename',
              'createduser.name AS createdbyusername',
              'updateduser.name AS updatedbyusername'
            ])
        ;

        if ( $vehicle->isEmpty() ) {
          throw new ModelNotFoundException("Não temos nenhum veículo "
            . "do contratante {$contractor->name} com o código "
            . "{$vehicleID} cadastrado"
          );
        }

        // Convertemos para matriz
        $vehicle = $vehicle
          ->first()
          ->toArray()
        ;

        if ($vehicle['customeristheowner']) {
          // Anexamos a informação do número de telefone principal do
          // cliente
          $phones = Phone::join('phonetypes',
                'phones.phonetypeid', '=', 'phonetypes.phonetypeid'
              )
            ->where('subsidiaryid', $vehicle['subsidiaryid'])
            ->orderBy('phones.phoneid')
            ->get([
                'phones.phonetypeid',
                'phonetypes.name as phonetypename',
                'phones.phonenumber'
              ])
          ;
          if ( $phones->isEmpty() ) {
            // Criamos os dados de telefone em branco
            //$vehicle['phonetypeid'] = 1;
            //$vehicle['phonetypename'] = 'Fixo';
            $vehicle['phonenumber'] = '';
          } else {
            $phone = $phones
              ->first()
            ;
            //$vehicle['phonetypeid'] = $phone->phonetypeid;
            //$vehicle['phonetypename'] = $phone->phonetypename;
            $vehicle['phonenumber'] = $phone->phonenumber;
          }
        }
        
        // Recuperamos as informações de documentos anexados
        $vehicle['attachments'] = VehicleAttachment::where('vehicleid',
              $vehicleID
            )
          ->where('contractorid', '=', $contractorID)
          ->get([
              'vehicleattachmentid AS id',
              'filename',
              'realfilename'
            ])
          ->toArray()
        ;
        
        // Recupera o local de armazenamento dos anexos
        $uploadDirectory =
          $this->container['settings']['storage']['attachments']
          . DIRECTORY_SEPARATOR . $contractor->id
        ;
        
        // Para cada documento, analisamos seu tipo
        foreach ($vehicle['attachments'] as $pos => $attachment) {
          $attachmentFile = $uploadDirectory . DIRECTORY_SEPARATOR
            . $attachment['filename']
          ;

          // Recupera o tipo Mime do arquivo
          $mimeType = mime_content_type($attachmentFile);

          // Conforme o tipo do arquivo, convertemos seu conteúdo
          switch ($mimeType) {
            case "image/png":
            case "image/jpeg":
              // Converte a imagem para base 64
              $imagedata = file_get_contents($attachmentFile);
              $base64 = base64_encode($imagedata);

              // Atribui os dados da imagem
              $vehicle['attachments'][$pos]['encodedImage'] =
                "data: {$mimeType};base64,{$base64}"
              ;
              
              break;
            default;
              $vehicle['attachments'][$pos]['encodedImage'] = null;
          }

          // Determina o tamanho do arquivo
          $vehicle['attachments'][$pos]['size'] =
            $this->humanFilesize(filesize($attachmentFile))
          ;
        }

        // Recuperamos as informações de equipamentos vinculados
        $sql = "SELECT E.vehicleID,
                       E.equipmentID,
                       E.brandName,
                       E.modelName,
                       E.imei,
                       E.serialNumber,
                       E.installedAt,
                       E.installationNumber,
                       E.main,
                       E.installationSite,
                       E.hasBlocking,
                       E.blockingSite,
                       E.hasIButton,
                       E.iButtonSite,
                       E.hasSiren,
                       E.sirenSite,
                       E.panicButtonSite
                  FROM erp.getEquipmentsPerVehicleData({$contractor->id}, {$vehicleID}) AS E;"
        ;
        $vehicle['equipments'] = (array) $this->DB->select($sql);

        // Renderiza a página para poder converter em PDF
        $title = "Dados cadastrais de veículo";
        $PDFFileName = "Vehicle_{$vehicle['plate']}.pdf";
        $page = $this->renderPDF('erp/cadastre/vehicles/PDFvehicle.twig',
          [ 'vehicle' => $vehicle ]
        );
      } catch (QueryException | ModelNotFoundException $exception) {
        // Retornamos um erro como resposta
        return $response
          ->withHeader('Content-type', 'application/json')
          ->withStatus(404)
          ->withJson([
              'result' => 'NOK',
              'params' => $args,
              'message' => "Não foi possível gerar o arquivo PDF com "
                . "os dados cadastrais do veículo. "
                . $exception->getMessage(),
              'data' => null
            ])
        ;
      }
    } else {
      if ($subsidiaryID === 'any') {
        // Recuperamos a relação dos veículos deste cliente independente
        // da unidade/filial
        $subsidiaryID = 0;
      }

      $this->debug("Tipo de relatório: {$reportType}");
      if ($reportType === "equipmentList") {
        $this->debug("Processando à geração de PDF com a relação de "
          . "veículos rastreados de um cliente."
        );

        try {
          $filter = '';
          if ($subsidiaryID > 0) {
            $filter = "AND V.subsidiaryID = {$subsidiaryID}";
          }

          // Relação dos veículos vinculados à um equipamento
          $sql = "SELECT vehicle.customerid,
                         customer.name AS customername,
                         customerType.entityTypeID AS customertypeid,
                         customerType.juridicalperson,
                         customerType.cooperative,
                         vehicle.subsidiaryid,
                         unit.name AS subsidiaryname,
                         vehicle.plate,
                         brand.name AS vehiclebrandname,
                         model.name AS vehiclemodelname,
                         equipment.customerPayerID,
                         payer.tradingName AS customerPayerName,
                         to_char(record.installedAt, 'DD/MM/YYYY') AS installedAt
                    FROM erp.vehicles AS vehicle
                   INNER JOIN erp.equipments AS equipment ON (vehicle.vehicleID = equipment.vehicleID AND equipment.storageLocation = 'Installed')
                   INNER JOIN erp.entities AS customer ON (vehicle.customerID = customer.entityID)
                   INNER JOIN erp.entitiesTypes AS customerType ON (customer.entityTypeID = customerType.entityTypeID)
                   INNER JOIN erp.subsidiaries AS unit ON (vehicle.subsidiaryID = unit.subsidiaryID)
                   INNER JOIN erp.vehicleBrands AS brand ON (vehicle.vehicleBrandID = brand.vehicleBrandID)
                   INNER JOIN erp.vehicleModels AS model ON (vehicle.vehicleModelID = model.vehicleModelID)
                   INNER JOIN erp.entities AS payer ON (equipment.customerPayerID = payer.entityID)
                   INNER JOIN erp.installationRecords AS record
                           ON (equipment.vehicleid = record.vehicleid AND
                               equipment.equipmentID = record.equipmentID AND
                               record.uninstalledAt IS NULL)
                   WHERE vehicle.blocked = FALSE
                     AND equipment.contractorid = {$contractorID}
                     AND vehicle.customerID = {$customerID} {$filter}
                   ORDER BY
                    CASE
                      WHEN customerType.cooperative THEN unit.name
                      ELSE unit.subsidiaryid::varchar
                    END,
                    vehicle.plate,
                    equipment.serialnumber;"
          ;

          $vehicles = $this->DB->select($sql);
          if (count($vehicles) > 0) {
            $name = $vehicles[0]->customername;
            $name = preg_replace('/\s+/', '_', $name);

            $subsidiaries = array_column($vehicles, 'subsidiaryid');
            $vehiclesPerSubsidiary = array_count_values($subsidiaries);
          } else {
            $name = "Cliente_ID_{$customerID}";
          }

          // Renderiza a página para poder converter em PDF
          $title = "Relação de veículos rastreados do cliente";
          $PDFFileName = "{$name}_Veiculos.pdf";
          $page = $this->renderPDF('erp/cadastre/vehicles/PDFtrackedvehicles.twig',
            [ 'vehicles' => $vehicles,
              'vehiclesPerSubsidiary' => $vehiclesPerSubsidiary ]
          );
        } catch (Exception $exception) {
          // Retornamos um erro como resposta
          return $response
            ->withHeader('Content-type', 'application/json')
            ->withStatus(404)
            ->withJson([
                'result' => 'NOK',
                'params' => $args,
                'message' => "Não foi possível gerar o arquivo PDF com "
                  . "a relação de veículos rastreados do cliente. "
                  . $exception->getMessage(),
                'data' => null
              ])
          ;
        }
      } else {
        try {
          // Relação simples dos veículos
          $sql = "SELECT V.vehicleID AS id,
                         V.customerID,
                         C.name AS customerName,
                         S.name AS subsidiaryName,
                         V.subsidiaryID,
                         V.juridicalperson,
                         V.cooperative,
                         V.headOffice,
                         V.type,
                         V.level,
                         V.active,
                         V.activeAssociation,
                         V.name AS plate,
                         V.tradingName,
                         V.blocked,
                         V.vehicleTypeName,
                         V.vehicleSubtypeID,
                         V.vehicleSubtypeName,
                         V.vehicleBrandName,
                         V.vehicleModelName,
                         V.vehicleColor,
                         V.active,
                         V.blockedLevel,
                         V.fullcount
                    FROM erp.getVehiclesData({$contractorID}, {$customerID},
                      {$subsidiaryID}, 0, '', 'plate', NULL, NULL, 2, 0, 0, 0) AS V
                   INNER JOIN erp.entities AS C ON (V.customerID = C.entityID)
                   INNER JOIN erp.subsidiaries AS S ON (V.subsidiaryID = S.subsidiaryID)
                   WHERE V.type = 3;"
          ;
          $vehicles = $this->DB->select($sql);
          if (count($vehicles) > 0) {
            $name = $vehicles[0]->customername;
            $name = preg_replace('/\s+/', '_', $name);
          } else {
            $name = "Cliente_ID_{$customerID}";
          }

          // Renderiza a página para poder converter em PDF
          $title = "Relação de veículos do cliente";
          $PDFFileName = "Lista_Veiculos_{$name}.pdf";
          $page = $this->renderPDF('erp/cadastre/vehicles/PDFvehicles.twig',
            [ 'vehicles' => $vehicles ]
          );
        } catch (Exception $exception) {
          // Retornamos um erro como resposta
          return $response
            ->withHeader('Content-type', 'application/json')
            ->withStatus(404)
            ->withJson([
                'result' => 'NOK',
                'params' => $args,
                'message' => "Não foi possível gerar o arquivo PDF com "
                  . "a relação de veículos do cliente. "
                  . $exception->getMessage(),
                'data' => null
              ])
          ;
        }
      }
    }

    // Renderiza as partes comuns aos dois tipos de PDF's
    $logo   = $this->getContractorLogo($contractor->uuid, 'normal');
    $header = $this->renderPDFHeader($title, $logo);
    $footer = $this->renderPDFFooter();

    // Cria um novo mPDF e define a página no tamanho A4 com orientação
    // portrait
    $mpdf = new Mpdf($this->generatePDFConfig('A4', 'Portrait'));

    // Permite a conversão (opcional)
    $mpdf->allow_charset_conversion=true;

    // Permite a compressão
    $mpdf->SetCompression(true);

    // Define os metadados do documento
    $mpdf->SetTitle($title);
    $mpdf->SetAuthor($this->authorization->getUser()->name);
    $mpdf->SetSubject('Controle de veículos');
    $mpdf->SetCreator('TrackerERP');

    // Define os cabeçalhos e rodapés
    $mpdf->SetHTMLHeader($header);
    $mpdf->SetHTMLFooter($footer);

    // Seta modo tela cheia
    $mpdf->SetDisplayMode('fullpage');
    $mpdf->showImageErrors = false;
    $mpdf->debug = false;

    // Inclui o conteúdo
    $mpdf->WriteHTML($page);

    // Envia o relatório para o browser no modo Inline
    $stream = fopen('php://memory','r+');
    ob_start();
    $mpdf->Output($PDFFileName,'I');
    $fileSize = ob_get_length();
    $pdfData  = ob_get_contents();
    ob_end_clean();
    fwrite($stream, $pdfData);
    rewind($stream);

    // Registra o acesso
    if (array_key_exists('vehicleID', $args)) {
      $this->info("Acesso ao PDF com as informações cadastrais do "
        . "veículo '{plate}'.",
        [
          'plate' => $vehicle['plate']
        ]
      );
    } else {
      $this->info("Acesso ao PDF com a relação de veículos do "
        . "cliente '{customerName}'.", 
        [
          'customerName' => $vehicles[0]->customername
        ]
      );
    }

    return $response
      ->withBody(new Stream($stream))
      ->withHeader('Content-Type', 'application/pdf')
      ->withHeader('Content-Length', $fileSize)
      ->withHeader('Content-Disposition', "name='{$PDFFileName}'")
      ->withHeader('Cache-Control', 'no-store, no-cache, '
          . 'must-revalidate'
        )
      ->withHeader('Expires', 'Sun, 1 Jan 2000 12:00:00 GMT')
      ->withHeader('Last-Modified', gmdate('D, d M Y H:i:s') . 'GMT')
    ;
  }

  /**
   * Recupera um anexo armazenado, seja para download e/ou visualização.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   * @param array $args
   *   Os argumentos da requisição
   *
   * @return Response $response
   */
  public function getAttachment(
    Request $request,
    Response $response,
    array $args
  )
  {
    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();

    // Recupera o ID do documento anexo
    $attachmentID = $args['attachmentID'];

    // Recupera o local de armazenamento dos anexos
    $uploadDirectory =
      $this->container['settings']['storage']['attachments']
      . DIRECTORY_SEPARATOR . $contractor->id
    ;

    // Recupera as informações armazenadas do documento anexo
    $attachmentData = VehicleAttachment::join('vehicles',
          'vehicleattachments.vehicleid', '=', 'vehicles.vehicleid'
        )
      ->where('vehicleattachments.vehicleattachmentid',
          '=', $attachmentID
        )
      ->where('vehicleattachments.contractorid',
          '=', $contractor->id
        )
      ->get([
          'vehicleattachments.realfilename',
          'vehicleattachments.filename',
          'vehicles.plate'
        ])
      ->first()
    ;

    // Se tivermos resultados
    if ($attachmentData) {
      // Registra o acesso
      $this->debug("Processando à solicitação do anexo {filename} "
        . "do veículo placa {plate}.",
        [
          'filename' => $attachmentData->realfilename,
          'plate' => $attachmentData->plate
        ]
      );

      // Recupera o nome do arquivo do anexo
      $attachmentFile = $uploadDirectory . DIRECTORY_SEPARATOR
        . $attachmentData->filename
      ;
      
      // Verifica se o arquivo existe
      if (file_exists($attachmentFile)) {
        // Recupera o tipo Mime do arquivo
        $mimeType = mime_content_type($attachmentFile);

        // Independente do tipo do arquivo, como é um download, enviamos
        // ao cliente

        // O código de retorno é '200 - OK'
        $statusCode = 200;

        // Converte a imagem para uma stream em memória
        $stream     = fopen($attachmentFile,'r+');
        $filesize   = filesize($attachmentFile);
      } else {
        // Registra o erro
        $this->error("Não foi possível localizar o arquivo {filename} "
          . "correspondente ao anexo '{realFileName}' cujo código é "
          . "{attachmentID}.",
          [
            'filename' => $attachmentFile,
            'realFileName' => $attachmentData->realfilename,
            'attachmentID' => $attachmentID
          ]
        );
        
        // O código de retorno é '204 - Nenhum conteúdo'
        $statusCode   = 404;

        // Define o tipo mime e a mensagem de retorno
        $mimeType     = "text/plain; charset=UTF-8";
        $errorMessage = "Arquivo não encontrado.\n";
        $filesize     = strlen($errorMessage);

        // Converte a mensagem de erro para uma stream em memória
        $stream = fopen('php://memory','r+');
        fwrite($stream, $errorMessage);
      }
    } else {
      // Registra o erro
      $this->error("Não foi possível localizar as informações do anexo "
        . "ID {attachmentID}.",
        [
          'attachmentID' => $attachmentID
        ]
      );
      
      // O código de retorno é '204 - Nenhum conteúdo'
      $statusCode   = 404;

      // Define o tipo mime e a mensagem de retorno
      $mimeType     = "text/plain; charset=UTF-8";
      $errorMessage = "Informações do anexo não encontrada.\n";
      $filesize     = strlen($errorMessage);

      // Converte a mensagem de erro para uma stream em memória
      $stream = fopen('php://memory','r+');
      fwrite($stream, $errorMessage);
    }

    if ($args['operation'] === 'download') {
      // Força o download da imagem
      return $response
        ->withBody(new Stream($stream))
        ->withHeader('Content-Description', 'File Transfer')
        ->withHeader('Content-Transfer-Encoding', 'binary')
        ->withHeader('Content-Type', 'application/octet-stream')
        ->withHeader('Content-Disposition', 'attachment;filename="'
            . $attachmentData->realfilename . '"'
          )
        ->withHeader('Content-Length', $filesize)
        ->withHeader('Expires', '0')
        ->withHeader('Cache-Control', 'must-revalidate, post-check=0, '
           . 'pre-check=0'
         )
        ->withHeader('Pragma', 'public')
        ->withStatus($statusCode)
      ;
    } else {
      // Retorna a imagem gerada para exibição
      return $response
        ->withBody(new Stream($stream))
        ->withHeader('Content-Type', $mimeType)
        ->withHeader('Content-Disposition', 'inline;filename="'
            . $attachmentData->realfilename . '"'
          )
        ->withHeader('Content-Length', $filesize)
        ->withHeader('Cache-Control', 'no-store, no-cache, '
           . 'must-revalidate'
         )
        ->withHeader('Expires', '0')
        ->withHeader('Cache-Control', 'must-revalidate, post-check=0, '
            . 'pre-check=0'
          )
        ->withHeader('Last-Modified', gmdate('D, d M Y H:i:s') . 'GMT')
        ->withStatus($statusCode)
      ;
    }
  }

  /**
   * Gera um PDF para impressão das informações de um anexo de um
   * veículo.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   * @param array $args
   *   Os argumentos da requisição
   *
   * @return Response $response
   */
  public function getAttachmentPDF(
    Request $request,
    Response $response,
    array $args
  )
  {
    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();

    // Recupera o ID do anexo
    $attachmentID = $args['attachmentID'];

    // Recupera o local de armazenamento dos anexos
    $uploadDirectory =
      $this->container['settings']['storage']['attachments']
      . DIRECTORY_SEPARATOR . $contractor->id
    ;

    try {
      // Recupera as informações do anexo
      $attachmentData = VehicleAttachment::join('vehicles',
            'vehicleattachments.vehicleid', '=', 'vehicles.vehicleid'
          )
        ->where('vehicleattachments.vehicleattachmentid',
            '=', $attachmentID
          )
        ->where('vehicleattachments.contractorid', '=', $contractor->id)
        ->get([
            'vehicleattachments.realfilename',
            'vehicleattachments.filename',
            'vehicles.plate'
          ])
        ->first()
      ;
    } catch (ModelNotFoundException $exception) {
      // Retornamos um erro como resposta
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withStatus(404)
        ->withJson([
            'result' => 'NOK',
            'params' => $args,
            'message' => "Não foi possível gerar o arquivo PDF com a "
              . "imagem de anexo. "
              . $exception->getMessage(),
            'data' => null
          ])
      ;
    }

    // Se tivermos resultados
    if ($attachmentData) {
      // Registra o acesso
      $this->debug("Processando à solicitação do PDF do anexo "
        . "{filename} do veículo placa {plate}.",
        [
          'filename' => $attachmentData->realfilename,
          'plate' => $attachmentData->plate
        ]
      );

      // Recupera o nome do arquivo do anexo
      $attachmentFile = $uploadDirectory . DIRECTORY_SEPARATOR
        . $attachmentData->filename
      ;
      
      // Verifica se o arquivo existe
      if (file_exists($attachmentFile)) {
        // Recupera o tipo Mime do arquivo
        $mimeType = mime_content_type($attachmentFile);

        // Em função do tipo mime toma as devidas ações
        switch ($mimeType) {
          case "image/png":
          case "image/jpeg":
            // Converte a imagem para base 64
            $imageData = file_get_contents($attachmentFile);

            // Determina o tamanho do arquivo
            $attachmentData->size = $this->humanFilesize(
              filesize($attachmentFile)
            );

            // Acrescenta a imagem nos dados cadastrais do anexo
            $attachmentData->encodedImage = 'data:' . $mimeType
              . ';base64,' . base64_encode($imageData)
            ;

            // Renderiza a página para poder converter em PDF
            $title = "Documento anexo do cadastro de um veículo";
            $PDFFileName = $attachmentData->realfilename . ".pdf";
            $page = $this->renderPDF(
              'erp/cadastre/vehicles/PDFattachment.twig',
              [ 'attachment' => $attachmentData ]
            );
            $logo   = $this->getContractorLogo($contractor->uuid,
              'normal'
            );
            $header = $this->renderPDFHeader($title, $logo);
            $footer = $this->renderPDFFooter();

            // Cria um novo mPDF e define a página no tamanho A4 com orientação
            // portrait
            $mpdf = new Mpdf($this->generatePDFConfig('A4',
              'Portrait')
            );

            // Permite a conversão (opcional)
            $mpdf->allow_charset_conversion=true;

            // Permite a compressão
            $mpdf->SetCompression(true);

            // Define os metadados do documento
            $mpdf->SetTitle($title);
            $mpdf->SetAuthor($this->authorization->getUser()->name);
            $mpdf->SetSubject('Controle de veículos');
            $mpdf->SetCreator('TrackerERP');

            // Define os cabeçalhos e rodapés
            $mpdf->SetHTMLHeader($header);
            $mpdf->SetHTMLFooter($footer);

            // Seta modo tela cheia
            $mpdf->SetDisplayMode('fullpage');
            $mpdf->showImageErrors = false;
            $mpdf->debug = false;

            // Inclui o conteúdo
            $mpdf->WriteHTML($page);

            // Envia o relatório para o browser no modo Inline
            $stream = fopen('php://memory','r+');
            ob_start();
            $mpdf->Output($PDFFileName,'I');
            $fileSize = ob_get_length();
            $pdfData = ob_get_contents();
            ob_end_clean();
            fwrite($stream, $pdfData);
            rewind($stream);

            // Registra o acesso
            $this->info("Acesso ao PDF com o conteúdo do anexo "
              . "'{realFileName}' do veículo placa '{plate}'.",
              [
                'plate' => $attachmentData->plate,
                'realFileName' => $attachmentData->realfilename
              ]
            );

            return $response
              ->withBody(new Stream($stream))
              ->withHeader('Content-Type', 'application/pdf')
              ->withHeader('Content-Length', $fileSize)
              ->withHeader('Content-Disposition', "name='{$PDFFileName}'")
              ->withHeader('Cache-Control', 'no-store, no-cache, '
                  . 'must-revalidate'
                )
              ->withHeader('Expires', 'Sun, 1 Jan 2000 12:00:00 GMT')
              ->withHeader('Last-Modified', gmdate('D, d M Y H:i:s') . 'GMT')
            ;

            break;
          case "application/pdf":
            // Apenas retorna o próprio arquivo
            $stream     = fopen($attachmentFile,'r+');
            $filesize   = filesize($attachmentFile);
            $mimeType   = mime_content_type($attachmentFile);

            // Retorna o anexo para exibição
            return $response
              ->withBody(new Stream($stream))
              ->withHeader('Content-Type', $mimeType)
              ->withHeader('Content-Length', $filesize)
              ->withHeader('Content-Disposition', 'inline;filename="'
                  . $attachmentData->realfilename . '"'
                )
              ->withHeader('Cache-Control', 'no-store, no-cache, '
                  . 'must-revalidate'
                )
              ->withHeader('Cache-Control', 'must-revalidate, '
                  . 'post-check=0, pre-check=0'
                )
              ->withHeader('Expires', 'Sun, 1 Jan 2000 12:00:00 GMT')
              ->withHeader('Last-Modified', gmdate('D, d M Y H:i:s') . 'GMT')
            ;

            break;
          default;
            // Registra o erro
            $this->error("Não foi possível converter o arquivo "
              . "{filename} correspondente ao anexo '{realFileName}' "
              . "cujo código é {attachmentID} para PDF.",
              [
                'filename' => $attachmentFile,
                'realFileName' => $attachmentData->realfilename,
                'attachmentID' => $attachmentID
              ]
            );

            // Registra o erro '415 - Tipo de mídia inválida'
            $errorMessage = "Tipo de arquivo inválido.";

            // Retornamos um erro como resposta
            return $response
              ->withHeader('Content-type', 'application/json')
              ->withStatus(415)
              ->withJson([
                  'result' => 'NOK',
                  'params' => $args,
                  'message' => "Não foi possível gerar o arquivo PDF "
                    . "com a imagem de anexo. " . $errorMessage,
                  'data' => null
                ])
            ;
        }
      } else {
        // O arquivo não existe

        // Registra o erro
        $this->error("Não foi possível localizar o arquivo {filename} "
          . "correspondente ao anexo '{realFileName}' cujo código é "
          . "{attachmentID}.",
          [
            'filename' => $attachmentFile,
            'realFileName' => $attachmentData->realfilename,
            'attachmentID' => $attachmentID
          ]);

        $errorMessage = "Arquivo não encontrado.";

        // Retornamos um erro como resposta
        return $response
          ->withHeader('Content-type', 'application/json')
          ->withStatus(404)
          ->withJson([
              'result' => 'NOK',
              'params' => $args,
              'message' => "Não foi possível gerar o arquivo PDF com a "
                . "imagem de anexo. " . $errorMessage,
              'data' => null
            ])
        ;
      }
    } else {
      // Registra o erro
      $this->error("Não foi possível localizar as informações do anexo "
        . "ID {attachmentID}.",
        [
          'attachmentID' => $attachmentID
        ]
      );

      $errorMessage = "Anexo não encontrado.";

      // Retornamos um erro como resposta
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withStatus(405)
        ->withJson([
            'result' => 'NOK',
            'params' => $args,
            'message' => "Não foi possível gerar o arquivo PDF com a "
              . "imagem de anexo. " . $errorMessage,
            'data' => null
          ])
      ;
    }
  }

  /**
   * Apaga uma imagem anexada armazenada.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   * @param array $args
   *   Os argumentos da requisição
   *
   * @return Response $response
   */
  public function deleteAttachment(
    Request $request,
    Response $response,
    array $args
  )
  {
    // Registra o acesso
    $this->debug("Processando à remoção de anexo de veículo.");

    // Recupera as informações do contratante
    $contractor = $this->authorization->getContractor();

    // Recupera o ID do anexo
    $attachmentID = $args['attachmentID'];

    try
    {
      // Recupera as informações do anexo e veículo
      $attachmentData = VehicleAttachment::join('vehicles',
            'vehicleattachments.vehicleid', '=', 'vehicles.vehicleid'
          )
        ->where('vehicleattachments.vehicleattachmentid',
            '=', $attachmentID
          )
        ->where('vehicleattachments.contractorid', '=', $contractor->id)
        ->get([
            'vehicleattachments.realfilename',
            'vehicleattachments.filename',
            'vehicles.plate'
          ])
        ->toArray()[0]
      ;

      // Recupera as informações do anexo
      $attachment = VehicleAttachment::findOrFail($attachmentID);
      
      // Recupera o local de armazenamento dos arquivos
      $uploadDirectory =
        $this->container['settings']['storage']['attachments']
      ;

      // Agora apaga o anexo

      // Iniciamos a transação
      $this->DB->beginTransaction();

      // Remove recursivamente
      $attachment->deleteAndRemoveAttachment($uploadDirectory);

      // Efetiva a transação
      $this->DB->commit();

      // Registra o sucesso
      $this->info("O documento '{filename}' do veículo placa '{plate}' "
        . "foi removido com sucesso.",
        [
          'filename' => $attachmentData['realfilename'],
          'plate' => $attachmentData['plate']
        ]
      );

      // Informa que a remoção foi realizada com sucesso
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getParams(),
            'message' => "Removido o documento "
              . "'{$attachmentData['realfilename']}' do veículo "
              . "placa {$attachmentData['plate']}",
            'data' => "Delete" ]
      );

      // Registra o acesso
      $this->debug("Processando à remoção do anexo {filename} do "
        . "veículo placa {plate}.",
        [
          'filename' => $attachmentData['realfilename'],
          'plate' => $attachmentData['plate']
        ]
      );
    }
    catch(ModelNotFoundException $exception)
    {
      // Reverte (desfaz) a transação
      $this->DB->rollBack();

      // Registra o erro
      $this->error("Não foi possível localizar o documento código "
        . "{attachmentID} para remoção.",
        [
          'attachmentID' => $attachmentID
        ]
      );

      $message = "Não foi possível localizar o documento anexado para "
        . "remoção."
      ;
    }
    catch(QueryException | Exception $exception)
    {
      // Reverte (desfaz) a transação
      $this->DB->rollBack();

      // Registra o erro
      $this->error("Não foi possível remover as informações do anexo "
        . "'{filename}' do veículo placa '{plate}'. Erro interno: "
        . "{error}",
        [
          'filename' => $attachmentData['realfilename'],
          'plate' => $attachmentData['plate'],
          'error' => $exception->getMessage()
        ]
      );

      $message = "Não foi possível remover o veículo. Erro interno.";
    }

    return $response
      ->withHeader('Content-type', 'application/json')
      ->withJson([
          'result' => 'NOK',
          'params' => $request->getParams(),
          'message' => $message,
          'data' => null ]
        )
    ;
  }

  /**
   * Gera a miniatura de uma imagem armazenada.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   * @param array $args
   *   Os argumentos da requisição
   *
   * @return Response $response
   */
  public function thumbnailAttachment(
    Request $request,
    Response $response,
    array $args
  )
  {
    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();

    // Recupera o local de armazenamento dos anexos
    $uploadDirectory =
      $this->settings['storage']['attachments']
        . DIRECTORY_SEPARATOR . $contractor->id
    ;

    // Recupera o nome do arquivo da imagem
    $filename = $args['filename'];
    $originalImage = $uploadDirectory . DIRECTORY_SEPARATOR
      . $filename
    ;
    $this->debug("Gerada a miniatura da imagem '{filename}'.",
      [
        'filename' => $originalImage
      ]
    );

    // Verifica se o arquivo existe
    $mimeDir  = $this->app->getPublicDir()
      . DIRECTORY_SEPARATOR . '/images/mimetypes'
    ;
    if (file_exists($originalImage)) {
      // Renderiza a miniatura da imagem

      // Recupera o tipo Mime do arquivo
      $mimeType = mime_content_type($originalImage);

      // Conforme o tipo do arquivo, realizamos a correta renderização
      switch ($mimeType) {
        case "image/png":
        case "image/jpeg":
          // Apenas renderiza a miniatura do arquivo
          $imageRealSize = $originalImage;

          break;
        case "application/msword":
          // Renderiza um ícone
          $mimeType = 'image/png';
          $imageRealSize = $mimeDir . DIRECTORY_SEPARATOR . 'doc.png';

          break;
        case "application/vnd.ms-excel":
        case "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet":
          // Renderiza um ícone
          $mimeType = 'image/png';
          $imageRealSize = $mimeDir . DIRECTORY_SEPARATOR . 'xls.png';

          break;
        case "application/vnd.oasis.opendocument.text":
          // Renderiza um ícone
          $mimeType = 'image/png';
          $imageRealSize = $mimeDir . DIRECTORY_SEPARATOR . 'odt.png';

          break;
        case "application/vnd.oasis.opendocument.spreadsheet":
          // Renderiza um ícone
          $mimeType = 'image/png';
          $imageRealSize = $mimeDir . DIRECTORY_SEPARATOR . 'ods.png';

          break;
        case "application/zip":
          // Renderiza um ícone
          $mimeType = 'image/png';
          $imageRealSize = $mimeDir . DIRECTORY_SEPARATOR . 'zip.png';

          break;
        case "application/x-rar-compressed":
          // Renderiza um ícone
          $mimeType = 'image/png';
          $imageRealSize = $mimeDir . DIRECTORY_SEPARATOR . 'rar.png';

          break;
        case "application/pdf":
          // Renderiza um ícone
          $mimeType = 'image/png';
          $imageRealSize = $mimeDir . DIRECTORY_SEPARATOR . 'pdf.png';

          break;
        default:
          // Outro tipo não conhecido, então exibimos um ícone
          $mimeType = 'image/png';
          $imageRealSize = $mimeDir . DIRECTORY_SEPARATOR . 'file.png';
      }
    } else {
      // Recupera o nome do arquivo da imagem de erro
      $mimeType = 'image/png';
      $imageRealSize = $mimeDir . DIRECTORY_SEPARATOR . 'notfound.png';
    }

    // Converte a imagem para uma stream em memória
    try {
      // Primeiro geramos uma miniatura da imagem
      $thumbnailImage = $this->generateThumbnail($imageRealSize);

      // Convertemos esta miniatura para uma stream
      $stream = fopen('php://memory','r+');
      ob_start();
      imagejpeg($thumbnailImage);
      $imageData = ob_get_contents();
      $imageSize = strlen($imageData);
      ob_end_clean();
      fwrite($stream, $imageData);
      rewind($stream);
    }
    catch(UnexpectedValueException $exception) {
      // Registra o erro
      $this->error("Não foi possível gerar a miniatura da imagem "
        . "'{filename}'. {error}",
        [
          'filename' =>$filename,
          'error' => $exception->getMessage()
        ]
      );
    }
    catch(RuntimeException $exception) {
      // Registra o erro
      $this->error("Não foi possível gerar a miniatura da imagem "
        . "'{filename}'. {error}",
        [
          'filename' =>$filename,
          'error' => $exception->getMessage()
        ]
      );
    }

    $this->debug("Gerada a miniatura da imagem '{filename}'.",
      [
        'filename' => $filename
      ]
    );

    // Retorna a miniatura da imagem gerada
    return $response
      ->withBody(new Stream($stream))
      ->withHeader('Content-Type', $mimeType)
      ->withHeader('Content-Disposition', 'name="'
          . $args['filename'] . '"'
        )
      ->withHeader('Content-Length', $imageSize)
      ->withHeader('Cache-Control', 'no-store, no-cache, '
          . 'must-revalidate'
        )
      ->withHeader('Expires', 'Sun, 1 Jan 2000 12:00:00 GMT')
      ->withHeader('Last-Modified', gmdate('D, d M Y H:i:s') . 'GMT')
    ;
  }

  /**
   * Recupera as informações dos e-mails enviados em relação ao um
   * determinado pagamento.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   *
   * @return Response $response
   */
  public function getMailData(
    Request $request,
    Response $response
  ): Response
  {
    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();
    $contractorID = $contractor->id;

    // --------------------------[ Recupera os dados requisitados ]-----

    // Recupera os dados da requisição
    $postParams = $request->getParsedBody();

    // Lida com as informações provenientes do searchbox
    $customerID = $postParams['customerID'];
    $vehicleID = $postParams['vehicleID'];
    $plate = $postParams['plate'];

    // Registra o acesso
    $this->info(
      "Requisitando informações de e-mails enviados relativos ao "
      . "veículo {vehicleID} placa {plate}",
      [
        'vehicleID' => $vehicleID,
        'plate' => $plate
      ]
    );

    try {
      $sql = ""
        . "WITH installedEquipments AS ("
        . "  SELECT equipmentID,"
        . "         installedAt,"
        . "         COALESCE(uninstalledAt, CURRENT_DATE) AS uninstalledAt"
        . "    FROM erp.installationrecords"
        . "   WHERE vehicleID = {$vehicleID}"
        . "     AND contractorID = {$contractorID}"
        . ")"
        . "SELECT queue.queueid,"
        . "       TO_CHAR(queue.requestedat, 'DD/MM/YYYY HH:MM:SS') AS requestedat,"
        . "       queue.maileventid,"
        . "       event.name AS maileventname,"
        . "       queue.attempts,"
        . "       queue.sentto,"
        . "       queue.sentstatus,"
        . "       TO_CHAR(queue.statusat, 'DD/MM/YYYY HH:MM:SS') AS statusat,"
        . "       queue.reasons"
        . "  FROM installedEquipments AS equipment"
        . " INNER JOIN erp.emailsqueue AS queue"
        . "    ON ("
        . "         queue.recordsonscope @> array_prepend(equipment.equipmentID, '{}'::int[])"
        . "         AND DATE(queue.requestedAt) BETWEEN equipment.installedAt AND equipment.uninstalledat"
        . "         AND queue.originRecordID = {$customerID}"
        . "       )"
        . " INNER JOIN erp.mailevents AS event USING (mailEventID)"
        . " ORDER BY queue.statusat;"
      ;
      $emails = $this->DB->select($sql);

      if ($emails) {
        // Retorna a relação de e-mails
        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'result' => 'OK',
              'params' => $request->getQueryParams(),
              'message' => "Obtido os e-mails enviados para o veículo "
                . "placa {$plate}",
              'data' => $emails
            ])
        ;
      } else {
        $error = "Não foi possível obter os e-mails enviados do "
          . "veículo placa {$plate}"
        ;
      }
    } catch (QueryException | Exception $exception) {
      // Registra o erro
      $this->error(
        "Não foi possível recuperar as informações de {module}. Erro "
          . "interno: {error}.",
        [
          'module' => 'e-mails enviados',
          'error'  => $exception->getMessage()
        ]
      );

      $error = "Não foi possível recuperar as informações de e-mails "
        . "enviados. Erro interno."
      ;
    }

    // Retorna o erro
    return $response
      ->withHeader('Content-type', 'application/json')
      ->withJson([
          'result' => 'NOK',
          'params' => $request->getQueryParams(),
          'message' => $error,
          'data' => null
        ])
    ;
  }

  /**
   * Vincula o novo cliente à associação, lidando com este processo.
   *
   * @param integer $associationID
   *   O ID da associação
   * @param integer $associationUnityID
   *   O ID da unidade da associação
   * @param integer $customerID
   *   O ID do cliente
   * @param integer $subsidiaryID
   *   O ID da unidade/filial do cliente
   * @param Carbon $transferAt
   *   A data de transferência
   * 
   * @return void
   */
  private function joinAnAssociation(
    int $associationID,
    int $associationUnityID,
    int $customerID,
    int $subsidiaryID,
    Carbon $transferAt
  )
  {
    $transferAtDate = $transferAt->copy()->format('Y-m-d');
    // Precisamos garantir que o novo cliente esteja
    // vinculado a associação
    $sql = ""
      . "SELECT affiliationID,"
      . "       joinedAt,"
      . "       unjoinedAt"
      . "  FROM erp.affiliations"
      . " WHERE associationID = {$associationID}"
      . "   AND associationUnityID = {$associationUnityID}"
      . "   AND customerID = {$customerID}"
      . "   AND subsidiaryID = {$subsidiaryID}"
      . "   AND (unjoinedAt IS NULL"
      . "       OR unjoinedAt >= '{$transferAtDate}'::date - interval '30 days'"
      . "       OR joinedAt >= '{$transferAtDate}'::date)"
      . " LIMIT 1;"
    ;
    $existingAffiliations = $this->DB->select($sql);
    if ($existingAffiliations) {
      $currentAffiliation = $existingAffiliations[0];
      $joinedAt = Carbon::createFromFormat('Y-m-d',
        $currentAffiliation->joinedat
      );
      if ($joinedAt->greaterThan($transferAt)) {
        // A afiliação foi iniciada depois do período
        // de transferência, então ajustamos
        $joinedAt = $transferAt->copy();
      }
      $joinedAtDate = $joinedAt->format('Y-m-d');

      $sql = ""
        . "UPDATE erp.affiliations"
        . "   SET joinedAt = '$joinedAtDate'::date,"
        . "       unjoinedAt = NULL"
        . " WHERE affiliationID = {$currentAffiliation->affiliationid};"
      ;
      $this->DB->select($sql);
    } else {
      // Não temos uma afiliação, então precisamos
      // criar uma
      $sql = ""
        . "INSERT INTO erp.affiliations"
        . "            (associationID,"
        . "             associationUnityID,"
        . "             customerID,"
        . "             subsidiaryID,"
        . "             joinedAt)"
        . "     VALUES ({$associationID},"
        . "             {$associationUnityID},"
        . "             {$customerID},"
        . "             {$subsidiaryID},"
        . "             '{$transferAtDate}'::date);"
      ;
      $this->DB->select($sql);
    }
  }

  /**
   * Desvincula o cliente da associação, se necessário, lidando com este
   * processo.
   *
   * @param integer $currentVehicleID
   *   O ID do veículo atual
   * @param integer $associationUnityID
   *   O ID da unidade da associação
   * @param integer $customerID
   *   O ID do cliente
   * @param integer $subsidiaryID
   *   O ID da unidade/filial do cliente
   * @param Carbon $transferAt
   *   A data de transferência
   * 
   * @return void
   */
  private function unjoinFromAssociation(
    int $currentVehicleID,
    int $associationID,
    int $associationUnityID,
    int $customerID,
    int $subsidiaryID,
    Carbon $transferAt
  )
  {
    // Analisamos se o cliente tem mais veículos para determinar se deve
    // ser mantido o vínculo com a associação
    $transferAtDate = $transferAt->copy()->format('Y-m-d');
    $sql = ""
      . "SELECT affiliationID,"
      . "       joinedAt,"
      . "       EXISTS (SELECT 1"
      . "                 FROM erp.equipments"
      . "                INNER JOIN erp.vehicles USING (vehicleID)"
      . "                WHERE equipments.customerPayerID = affiliations.associationID"
      . "                  AND equipments.subsidiaryPayerID = affiliations.associationUnityID"
      . "                  AND vehicles.customerID = affiliations.customerID"
      . "                  AND vehicles.subsidiaryid = affiliations.subsidiaryID"
      . "                  AND vehicles.vehicleID <> {$currentVehicleID}) AS hasMoreThan"
      . "  FROM erp.affiliations"
      . " WHERE associationID = {$associationID}"
      . "   AND associationUnityID = {$associationUnityID}"
      . "   AND customerID = {$customerID}"
      . "   AND subsidiaryID = {$subsidiaryID}"
      . "   AND unjoinedAt IS NULL"
      . " LIMIT 1;"
    ;
    $existingAffiliations = $this->DB->select($sql);
    if ($existingAffiliations) {
      $currentAffiliation = $existingAffiliations[0];
      if (!$currentAffiliation->hasMoreThan) {
        // Este cliente não têm mais outros veículos
        // nesta associação, então precisamos retirar
        // o vínculo
        $joinedAt = Carbon::createFromFormat('Y-m-d',
          $currentAffiliation->joinedat
        );
        if ($joinedAt->greaterThan($transferAt)) {
          // A afiliação foi iniciada depois do período
          // de transferência, então ajustamos
          $joinedAt = $transferAt->copy()->subDay();
        }
  
        $joinedAtDate = $joinedAt->format('Y-m-d');
        $sql = ""
          . "UPDATE erp.affiliations"
          . "   SET joinedAt = '{$joinedAtDate}'::date,"
          . "       unjoinedAt = '{$transferAtDate}'::date - interval '1 day'"
          . " WHERE affiliationID = {$currentAffiliation->affiliationid};"
        ;
        $this->DB->select($sql);
      }
    }
  }

  /**
   * Retira as informações do veículo da grade de última posição.
   * 
   * @param integer $equipmentID
   *   O ID do equipamento
   * 
   * @return void
   */
  private function clearVehicleOnGrid(
    int $equipmentID
  )
  {
    $sql = ""
      . "UPDATE public.lastPositions"
      . "   SET customerID = NULL,"
      . "       subsidiaryID = NULL,"
      . "       customerPayerID = NULL,"
      . "       subsidiaryPayerID = NULL,"
      . "       vehicleID = NULL,"
      . "       plate = NULL,"
      . "       mainTracker = FALSE"
      . " WHERE equipmentID = {$equipmentID};"
    ;
    $this->DB->select($sql);
  }

  /**
   * Atualiza o veículo na grade de última posição.
   * 
   * @param integer $equipmentID
   *   O ID do equipamento
   * @param string $vehicleID
   *   A ID do veículo
   * @param string $plate
   *   A placa do veículo
   * @param integer $customerID
   *   O ID do cliente
   * @param integer $subsidiaryID
   *   O ID da unidade/filial do cliente
   * @param integer $customerPayerID
   *   O ID do pagador do contrato ao qual está vinculado
   * @param integer $subsidiaryPayerID
   *   O ID da unidade/filial do pagador do contrato ao qual está
   *   vinculado
   * 
   * @return void
   */
  private function updateVehicleOnGrid(
    int $equipmentID,
    int $vehicleID,
    string $plate,
    int $customerID,
    int $subsidiaryID,
    int $customerPayerID,
    int $subsidiaryPayerID,
    ?bool $mainTracker = NULL
  )
  {
    $complement = '';
    if ($mainTracker !== NULL) {
      $mainTracker = $mainTracker ? 'TRUE' : 'FALSE';
      $complement = ", mainTracker = {$mainTracker}";
    }

    $mainTracker = $mainTracker ? 'TRUE' : 'FALSE';
    $sql = ""
      . "UPDATE public.lastPositions"
      . "   SET customerID = {$customerID},"
      . "       subsidiaryID = {$subsidiaryID},"
      . "       customerPayerID = {$customerPayerID},"
      . "       subsidiaryPayerID = {$subsidiaryPayerID},"
      . "       vehicleID = {$vehicleID},"
      . "       plate = '{$plate}'{$complement}"
      . " WHERE equipmentID = {$equipmentID};"
    ;
    $this->DB->select($sql);
  }

  /**
 * Recupera a relação das placas em formato JSON no padrão dos campos
 * de preenchimento automático. As placas são filtradas pelo código de
 * um cliente informado na requisição.
 * 
 * @param Request $request
 *   A requisição HTTP
 * @param Response $response
 *   A resposta HTTP
 *
 * @return Response $response
 */
public function getAutocompletionData(
  Request $request,
  Response $response
): Response
{
  $this->debug("Relação de placas para preenchimento automático despachada.");

  // Recupera os dados da requisição
  $postParams = $request->getParsedBody();

  // Recupera os dados do contratante
  $contractor   = $this->authorization->getContractor();
  $contractorID = $contractor->id;
  
  // O termo de pesquisa
  $searchTerm = $postParams['searchTerm'];

  // O código do cliente
  $customerID = $postParams['customerID'] ?? 0;
  
  // CORREÇÃO: Aceita tanto boolean quanto string
  $detailed = false;
  if (isset($postParams['detailed'])) {
    $detailed = filter_var($postParams['detailed'], FILTER_VALIDATE_BOOLEAN);
  }
  
  // Debug para verificar
  $this->debug("Parâmetro detailed recebido: " . ($detailed ? 'true' : 'false'));
  
  // Determina os limites e parâmetros da consulta
  $length = isset($postParams['limit']) ? $postParams['limit'] : 20;

  $this->debug("Acesso aos dados de placas que contém '{$searchTerm}' - Detailed: " . ($detailed ? 'SIM' : 'NÃO'));
  
  try
  {
    // Query para buscar veículos
    $sql = ""
      . "SELECT V.vehicleID AS id,"
      . "       V.plate,"
      . "       T.name AS type,"
      . "       B.name AS brand,"
      . "       M.name AS model,"
      . "       C.name AS color,"
      . "       V.blocked AS blocked,"
      . "       EXISTS("
      . "         SELECT 1 FROM erp.equipments AS E"
      . "          WHERE E.vehicleID = V.vehicleID"
      . "            AND E.storagelocation = 'Installed'"
      . "       ) AS inUse"
      . "  FROM erp.vehicles AS V"
      . " INNER JOIN erp.vehicleTypes AS T USING (vehicleTypeID)"
      . " INNER JOIN erp.vehicleBrands AS B USING (vehicleBrandID)"
      . " INNER JOIN erp.vehicleModels AS M USING (vehicleModelID)"
      . " INNER JOIN erp.vehicleColors AS C USING (vehicleColorID)"
      . " WHERE V.contractorID = {$contractorID}"
      . "   AND V.customerID = {$customerID}"
      . "   AND ((V.plate ILIKE '%%{$searchTerm}%%') OR (V.plate ILIKE '%%' || public.getPlateVariant('{$searchTerm}') || '%%'))"
      . " ORDER BY V.plate ASC"
      . " LIMIT {$length}"
    ;
    
    $vehicles = $this->DB->select($sql);
    
    // Se solicitado dados detalhados, busca informações dos rastreadores
    if ($detailed && count($vehicles) > 0) {
      $this->debug("Buscando equipamentos para " . count($vehicles) . " veículo(s)");
      
      foreach ($vehicles as &$vehicle) {
        // Converte para boolean se veio como string 't' ou 'f'
        $vehicle->inuse = ($vehicle->inuse === 't' || $vehicle->inuse === true);
        
        if ($vehicle->inuse) {
          $this->debug("Buscando equipamentos do veículo {$vehicle->plate} (ID: {$vehicle->id})");
          
          // Query para buscar equipamentos
          $sqlEquipments = ""
            . "SELECT E.equipmentID,"
            . "       E.serialNumber,"
            . "       E.main,"
            . "       TO_CHAR(E.installedAt, 'YYYY-MM-DD') AS installedAt,"
            . "       E.installationSite,"
            . "       E.hasBlocking,"
            . "       E.blockingSite,"
            . "       E.hasIButton,"
            . "       E.iButtonSite,"
            . "       E.hasSiren,"
            . "       E.sirenSite,"
            . "       E.panicButtonSite,"
            . "       EB.name AS equipmentBrandName,"
            . "       EM.name AS equipmentModelName"
            . "  FROM erp.equipments AS E"
            . " INNER JOIN erp.equipmentModels AS EM ON E.equipmentModelID = EM.equipmentModelID"
            . " INNER JOIN erp.equipmentBrands AS EB ON EM.equipmentBrandID = EB.equipmentBrandID"
            . " WHERE E.vehicleID = {$vehicle->id}"
            . "   AND E.contractorID = {$contractorID}"
            . "   AND E.storageLocation = 'Installed'"
            . " ORDER BY E.main DESC, E.equipmentID"
          ;
          
          try {
            $equipments = $this->DB->select($sqlEquipments);
            
            // Processa os booleanos
            foreach ($equipments as &$equipment) {
              $equipment->main = ($equipment->main === 't' || $equipment->main === true);
              $equipment->hasblocking = ($equipment->hasblocking === 't' || $equipment->hasblocking === true);
              $equipment->hasibutton = ($equipment->hasibutton === 't' || $equipment->hasibutton === true);
              $equipment->hassiren = ($equipment->hassiren === 't' || $equipment->hassiren === true);
            }
            
            $vehicle->equipments = $equipments;
            
            $this->debug("Veículo {$vehicle->plate} tem " . count($equipments) . " rastreador(es)");
          } catch (Exception $e) {
            $this->error("Erro ao buscar equipamentos do veículo {$vehicle->id}: " . $e->getMessage());
            $vehicle->equipments = [];
          }
        } else {
          $vehicle->equipments = [];
        }
      }
    }

    return $response
      ->withHeader('Content-type', 'application/json')
      ->withJson([
          'result' => 'OK',
          'params' => $request->getQueryParams(),
          'message' => "Placas que contém '{$searchTerm}'",
          'data' => $vehicles
        ])
    ;
  }
  catch(Exception $exception)
  {
    $this->error("Erro ao recuperar placas: " . $exception->getMessage());
    
    return $response
      ->withHeader('Content-type', 'application/json')
      ->withJson([
          'result' => 'NOK',
          'params' => $request->getQueryParams(),
          'message' => "Erro ao localizar placas",
          'data' => []
        ])
    ;
  }
}
}