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
 * O controlador do gerenciamento dos equipamentos do sistema. Este
 * controle permite gerenciar os equipamentos associados aos veículos e
 * os SIM Cards associados à cada equipamento. Cada SIM Card associa uma
 * linha telefônica a um equipamento e permite a comunicação por
 * GPRS no mesmo. Podem existir mais de um SIM Card por equipamento,
 * dependendo do modelo do mesmo.
 *
 * O controle de SIM Cards é opcional.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Controllers\ERP\Devices;

use App\Models\AuthorizedEquipment;
use App\Models\Deposit;
use App\Models\Entity;
use App\Models\Equipment;
use App\Models\EquipmentsToGetHistory;
use App\Models\InstallationRecord;
use App\Models\LeasedEquipment;
use App\Models\LeasedSimcard;
use App\Models\OwnershipType;
use App\Models\SimCard;
use App\Models\SimCardType;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Core\Controllers\Controller;
use Core\Controllers\HandleFileTrait;
use Core\Controllers\QueryTrait;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Mpdf\Mpdf;
use Respect\Validation\Validator as V;
use RuntimeException;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\Stream;

class EquipmentsController
  extends Controller
{
  /**
   * Os métodos para manipular queries.
   */
  use QueryTrait;

  /**
   * Os métodos para manipular o recebimento de arquivos. Necessário
   * para se obter a logomarca do contratante no PDF
   */
  use HandleFileTrait;

  /**
   * Recupera as regras de validação.
   *
   * @param bool $addition
   *   Se a validação é para um formulário de adição
   *
   * @return array
   */
  protected function getValidationRules(
    bool $addition = false
  ): array
  {
    $validationRules = [
      'equipmentid' => V::notBlank()
        ->intVal()
        ->setName('ID do equipamento'),
      'equipmentmodelname' => V::notBlank()
        ->length(2, 50)
        ->setName('Modelo de equipamento'),
      'equipmentmodelid' => V::notBlank()
        ->intVal()
        ->setName('ID do modelo de equipamento'),
      'equipmentbrandname' => V::notBlank()
        ->length(2, 30)
        ->setName('Marca do equipamento'),
      'maxsimcards' => V::intVal()
        ->min(1)
        ->setName('Slots disponíveis'),
      'simcardtypename' => V::notBlank()
        ->length(1, 50)
        ->setName('Modelo do slot'),
      'serialnumber' => V::notEmpty()
        ->length(2, 30)
        ->setName('Número de série'),
      'imei' => V::optional(
          V::notEmpty()
            ->imei()
          )
        ->setName('IMEI'),
      'storedlocationname' => V::optional(
            V::notEmpty()
          )
        ->setName('Situação atual'),
      'ownershiptypeid' => V::intVal()
        ->setName('Tipo de propriedade'),
      'suppliername' => V::notBlank()
        ->length(2, 100)
        ->setName('Nome do fornecedor'),
      'supplierid' => V::notBlank()
        ->intVal()
        ->setName('ID do fornecedor'),
      'subsidiaryname' => V::notBlank()
        ->length(2, 50)
        ->setName('Unidade/Filial'),
      'subsidiaryid' => V::notBlank()
        ->intVal()
        ->setName('ID da unidade/filial'),
      'installationsite' => V::optional(
          V::notBlank()
            ->length(2, 100)
          )
        ->setName('Local de instalação do rastreador'),
      'assetnumber' => V::optional(
          V::notBlank()
            ->length(1, 20)
          )
        ->setName('Nº de patrimônio'),
      'leasedingequipment' => V::boolVal()
        ->setName('Realizar comodato deste equipamento'),
      'leasedequipment' => [
        'leasedequipmentid' => V::intVal()
          ->setName('ID do comodato do equipamento'),
        'assignedtoname' => V::optional(
              V::notBlank()
                ->length(2, 100)
            )
          ->setName('Nome do comodatário'),
        'assignedto' => V::intVal()
          ->setName('ID do fornecedor'),
        'startdate' => V::notEmpty()
          ->date('d/m/Y')
          ->setName('Data de início do comodato'),
        'graceperiod' => V::intVal()
          ->setName('Tempo de carência'),
        'enddate' => V::optional(
              V::notEmpty()
                ->date('d/m/Y')
            )
          ->setName('Data do encerramento do comodato'),
      ],
    ];

    if ($addition) {
      // Ajusta as regras para adição
      unset($validationRules['equipmentid']);
      $validationRules['depositid'] = V::intVal()
        ->setName('Local de armazenamento')
      ;
      unset($validationRules['leasedingequipment']);
      unset($validationRules['leasedequipment']);
    } else {
      // Ajusta as regras para edição
      $validationRules['blocked'] = V::boolVal()
        ->setName('Inativar este equipamento')
      ;
      $validationRules['storagelocation'] = V::notBlank()
        ->setName('Local de armazenamento')
      ;
      $validationRules['installedat'] = V::optional(
            V::date('d/m/Y')
          )
        ->setName('Data da instalação')
      ;
    }

    return $validationRules;
  }

  /**
   * Recupera as regras de validação para um vínculo entre equipamento e
   * SIM Card.
   *
   * @param bool $addition
   *   Se a validação é para um formulário de adição de novo vínculo
   *
   * @return array
   */
  protected function getValidationRulesForAttach(): array
  {
    $validationRules = [
      'simcardid' => V::notBlank()
        ->intVal()
        ->setName('ID do SIM Card'),
      'iccid' => V::notEmpty()
        ->length(19, 20)
        ->iccid()
        ->setName('Nº de série'),
      'phonenumber' => V::oneOf(
            V::equals('Telefone não disponível'),
            V::notEmpty()
               ->length(1, 20)
          )
        ->setName('Telefone'),
      'mobileoperatorname' => V::optional(
          V::notEmpty()
            ->length(1, 20)
          )
        ->setName('Operadora de telefonia'),
      'simcardtypename' => V::optional(
          V::notEmpty()
            ->length(1, 20)
          )
        ->setName('Modelo de SIM Card'),
      'simcardtypeid' => V::intVal()
        ->setName('ID do modelo de SIM Card'),
      'equipmentid' => V::intVal()
        ->setName('ID do equipamento'),
      'serialnumber' => V::notEmpty()
        ->length(2, 30)
        ->setName('Número de série'),
      'equipmentmodelname' => V::notBlank()
        ->length(2, 50)
        ->setName('Modelo de equipamento'),
      'equipmentmodelid' => V::intVal()
        ->setName('ID do modelo de equipamento'),
      'slotnumber' => V::intVal()
        ->setName('Número do slot'),
      'slottypename' => V::optional(
          V::notEmpty()
            ->length(1, 20)
          )
        ->setName('Modelo do slot'),
      'slottypeid' => V::intVal()
        ->setName('ID do modelo do slot')
    ];

    return $validationRules;
  }

  /**
   * Recupera as informações de tipos de SIM Cards.
   *
   * @return Collection
   *   A matriz com as informações de tipos de SIM Cards
   *
   * @throws RuntimeException
   *   Em caso de não termos tipos de SIM Cards
   */
  protected function getSimCardTypes(): Collection
  {
    try {
      // Recupera as informações de tipos de SIM Cards
      $simcardTypes = SimCardType::orderBy('simcardtypeid')
        ->get([
            'simcardtypeid AS id',
            'name'
          ])
      ;

      if ( $simcardTypes->isEmpty() ) {
        throw new Exception("Não temos nenhum tipo de SIM Card "
          . "cadastrado"
        );
      }
    }
    catch (Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível obter as informações de tipos de "
        . "SIM Cards. Erro: {error}.",
        [
          'error' => $exception->getMessage()
        ]
      );

      throw new RuntimeException("Não foi possível obter os tipos de "
        . "SIM Cards"
      );
    }

    return $simcardTypes;
  }

  /**
   * Recupera as informações de tipos de propriedade.
   *
   * @return Collection
   *   A matriz com as informações de tipos de propriedade
   *
   * @throws RuntimeException
   *   Em caso de não termos tipos de propriedade
   */
  protected function getOwnershipTypes(): Collection
  {
    try {
      // Recupera as informações de tipos de propriedade
      $ownershipTypes = OwnershipType::orderBy('ownershiptypeid')
        ->get([
            'ownershiptypeid AS id',
            'name'
          ])
      ;

      if ( $ownershipTypes->isEmpty() ) {
        throw new Exception("Não temos nenhum tipo de propriedade "
          . "cadastrado"
        );
      }
    }
    catch (Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível obter as informações de tipos de "
        . "propriedade. Erro: {error}.",
        [
          'error' => $exception->getMessage()
        ]
      );

      throw new RuntimeException("Não foi possível obter os tipos de "
        . "propriedade"
      );
    }

    return $ownershipTypes;
  }

  /**
   * Recupera as informações de depósitos.
   *
   * @param int $contractorID
   *   A ID do contratante para o qual desejamos obter os depósitos
   *   disponíveis
   *
   * @return Collection
   *   A matriz com as informações de depósitos
   *
   * @throws RuntimeException
   *   Em caso de não termos depósitos definidos
   */
  protected function getDeposits(
    int $contractorID
  ): Collection
  {
    try {
      // Recupera as informações de depósitos que sejam deste
      // contratante
      $deposits = Deposit::where("contractorid", '=', $contractorID)
        ->whereRaw("devicetype IN ('Equipment', 'Both')")
        ->orderBy('name')
        ->get([
            'depositid AS id',
            'name'
          ])
      ;

      if ( $deposits->isEmpty() ) {
        throw new Exception("Não temos nenhum depósito cadastrado");
      }
    }
    catch (Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível obter as informações de "
        . "depósitos. Erro: {error}.",
        [
          'error' => $exception->getMessage()
        ]
      );

      throw new RuntimeException("Não foi possível obter os depósitos");
    }

    return $deposits;
  }

  /**
   * Exibe a página inicial do gerenciamento de equipamentos.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   *
   * @return Response $response
   */
  public function show(
    Request $request,
    Response $response
  ): Response
  {
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ERP\Home')
    );
    $this->breadcrumb->push('Dispositivos', '');
    $this->breadcrumb->push('Equipamentos',
      $this->path('ERP\Devices\Equipments')
    );

    // Registra o acesso
    $this->info("Acesso ao gerenciamento de equipamentos de "
      . "rastreamento."
    );
    
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
    $equipment = $this->session->get('equipment',
      [ 'searchField' => 'serialNumber',
        'searchValue' => '',
        'model' => [
          'id' => 0,
          'name'  => ''
        ],
        'storageLocation' => 'Any',
        'deposit' => [
          'id' => 0
        ],
        'technician' => [
          'id' => 0
        ],
        'serviceProvider' => [
          'id' => 0
        ]
      ])
    ;
    
    // Renderiza a página
    return $this->render($request, $response,
      'erp/devices/equipments/equipments.twig',
      [
        'equipment' => $equipment,
        'deposits' => $deposits,
        'defaultDepositID' => $defaultDepositID,
        'technicians' => $technicians,
        'serviceproviders' => $serviceproviders
      ]
    );
  }

  /**
   * Recupera a relação dos equipamentos em formato JSON
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   *
   * @return Response $response
   */
  public function get(
    Request $request,
    Response $response
  ): Response
  {
    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();

    $this->debug("Acesso à relação de equipamentos.");

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
    $orderBy = $columns[$order['column']]['name'];
    $orderDir = strtoupper($order['dir']);

    // A posição inicial (0 = início) da paginação
    $start = $postParams['start'];

    // O comprimento de cada página
    $length = $postParams['length'];

    // Lida com as informações adicionais de filtragem
    
    // O campo de pesquisa selecionado
    $searchField         = $postParams['searchField'];
    $searchValue         = trim($postParams['searchValue']);
    $equipmentModelID    = intval($postParams['modelID']);
    $equipmentModelName  = $postParams['modelName'];

    // Os campos de filtragem adicional
    $storageLocation     = $postParams['storageLocation'];
    $storageID           = 0;
    $depositID           = 0;
    $technicianID        = 0;
    $serviceProviderID   = 0;
    switch ($storageLocation) {
      case 'Installed':
        // Apenas os instalados

        break;
      case 'StoredOnDeposit':
        // Apenas os armazenados em um depósito. Recupera o ID do
        // depósito
        $depositID = $postParams['depositID'];
        $storageID = $depositID;

        break;
      case 'StoredWithTechnician':
        // Apenas os de posse de um técnico. Recupera o ID do técnico
        $technicianID = $postParams['technicianID'];
        $storageID = $technicianID;

        break;
      case 'StoredWithServiceProvider':
        // Apenas os de posse de um prestador de serviços. Recupera o
        // ID do prestador de serviços
        $serviceProviderID = $postParams['serviceProviderID'];
        $storageID = $serviceProviderID;

        break;
      default:
        // Todos os registros
        $storageLocation = 'Any';
        $storageID = 0;

        break;
    }

    // Seta os valores da última pesquisa na sessão
    $this->session->set('equipment',
      [ 'searchField' => $searchField,
        'searchValue' => $searchValue,
        'model' => [
          'id' => $equipmentModelID,
          'name'  => $equipmentModelName
        ],
        'storageLocation' => $storageLocation,
        'deposit' => [
          'id' => $depositID
        ],
        'technician' => [
          'id' => $technicianID
        ],
        'serviceProvider' => [
          'id' => $serviceProviderID
        ]
      ]
    );
    
    // Corrige o escape dos campos
    $searchValue = addslashes($searchValue);
    
    try
    {
      // Formata o ordenamento dos campos
      $ORDER = $this->formatOrderBy($orderBy, $orderDir);

      // Monta a consulta
      $contractorID = $this->authorization->getContractor()->id;
      $sql = "SELECT E.equipmentid AS id,
                     E.supplierid,
                     E.suppliername,
                     E.supplierblocked,
                     E.juridicalperson,
                     E.subsidiaryid,
                     E.subsidiaryname,
                     E.subsidiaryblocked,
                     E.leasedequipment,
                     E.leasedingequipment,
                     E.serialnumber,
                     E.imei,
                     E.equipmentmodelid,
                     E.equipmentmodelname AS modelname,
                     E.equipmentbrandid,
                     E.equipmentbrandname AS brandname,
                     E.maxsimcards,
                     E.assetnumber,
                     E.attached,
                     E.vehicleID,
                     E.plate,
                     E.equipmentblocked,
                     E.stateid,
                     E.statename,
                     E.blockedlevel,
                     E.createdat,
                     E.updatedat,
                     E.fullcount
                FROM erp.getEquipmentsData({$contractorID}, 0, 0, 0,
                  '{$searchValue}', '{$searchField}', {$equipmentModelID},
                  '{$storageLocation}', {$storageID}, '{$ORDER}',
                  {$start}, {$length}) AS E;"
      ;
      $equipments = $this->DB->select($sql);
      
      if (count($equipments) > 0) {
        $rowCount = $equipments[0]->fullcount;

        // Para cada equipamento, recupera os dados dos SimCards
        foreach ($equipments as $number => $equipment) {
          // Precisamos recuperar a informação dos SIM Cards associados
          // com este equipamento
          $sql = "SELECT SLOT.equipmentID,
                         SLOT.slotnumber AS number,
                         SLOT.simcardID,
                         SLOT.iccid,
                         SLOT.phonenumber,
                         SLOT.mobileOperatorName
                    FROM erp.getSlotData({$contractor->id}, {$equipment->id}) AS SLOT;"
          ;
          $simCardPerSlot = (array) $this->DB->select($sql);

          // Contamos quantos SIM Cards estão associados nos Slots
          $amountOfSimcards = 0;
          foreach ($simCardPerSlot as $slot) {
            if ($slot->simcardid > 0)
              $amountOfSimcards++;
          }

          $equipments[$number]->simcarddata = $simCardPerSlot;
          $equipments[$number]->amountofsimcards = $amountOfSimcards;
        }

        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'draw' => $draw,
              'recordsTotal' => $rowCount,
              'recordsFiltered' => $rowCount,
              'data' => $equipments
            ])
        ;
      } else {
        switch ($this->binaryFlags(empty($searchValue), empty($equipmentModelID))) {
          case 1:
            // Informado apenas um valor de pesquisa
            switch ($searchField) {
              case 'serialNumber':
                $error = "Não temos equipamentos cadastrados cujo "
                  . "número de série contém <i>{$searchValue}</i>."
                ;

                break;
              case 'imei':
                $error = "Não temos equipamentos cadastrados cujo IMEI "
                  . "contém <i>{$searchValue}</i>."
                ;

                break;
              case 'assetNumber':
                $error = "Não temos equipamentos cadastrados cujo "
                  . "número de patrimônio contém <i>{$searchValue}</i>."
                ;

                break;
              default:
                $error = "Não temos equipamentos cadastrados que "
                  . "contenham <i>{$searchValue}</i>."
                ;

                break;
            }

            break;
          case 2:
            // Informado apenas o modelo de equipamento
            $error = "Não temos equipamentos cadastrados que sejam do "
              . "modelo <i>{$equipmentModelName}</i>."
            ;

            break;
          case 3:
            // Informado tanto um valor de pesquisa quanto um modelo de
            // equipamento
            switch ($searchField) {
              case 'serialNumber':
                $error = "Não temos equipamentos cadastrados cujo "
                  . "número de série contém <i>{$searchValue}</i> e "
                  . "que sejam do modelo <i>{$equipmentModelName}</i>."
                ;

                break;
              case 'imei':
                $error = "Não temos equipamentos cadastrados cujo IMEI "
                  . "contém <i>{$searchValue}</i> e que sejam do "
                  . "modelo <i>{$equipmentModelName}</i>."
                ;

                break;
              case 'assetNumber':
                $error = "Não temos equipamentos cadastrados cujo "
                  . "número de patrimônio contém <i>{$searchValue}</i> "
                  . "e que sejam do modelo <i>{$equipmentModelName}</i>."
                ;

                break;
              default:
                $error = "Não temos equipamentos cadastrados que "
                  . "contenham <i>{$searchValue}</i> e que sejam do "
                  . "modelo <i>{$equipmentModelName}</i>."
                ;

                break;
            }

            break;
          default:
            $error = "Não temos equipamentos cadastrados.";
        }
      }
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno no banco de dados: {error}",
        [
          'module' => 'equipamentos',
          'error'  => $exception->getMessage()
        ]
      );

      $error = "Não foi possível recuperar as informações de "
        . "equipamentos. Erro interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno: {error}",
        [
          'module' => 'equipamentos',
          'error'  => $exception->getMessage()
        ]
      );

      $error = "Não foi possível recuperar as informações de "
        . "equipamentos. Erro interno."
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
   * Exibe um formulário para adição de um equipamento, quando
   * solicitado, e confirma os dados enviados.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   *
   * @return Response $response
   */
  public function add(
    Request $request,
    Response $response
  ): Response
  {
    try {
      // Recupera os dados do contratante
      $contractor = $this->authorization->getContractor();

      // Recupera as informações de tipos de propriedade
      $ownershipTypes = $this->getOwnershipTypes();

      // Recupera as informações de depósitos
      $deposits = $this->getDeposits($contractor->id);
    }
    catch (RuntimeException $exception)
    {
      // Alerta o usuário
      $this->flash("error", $exception->getMessage());

      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [
          'routeName' => 'ERP\Devices\Equipments'
        ]
      );

      // Redireciona para a página de gerenciamento de equipamentos
      return $this->redirect($response, 'ERP\Devices\Equipments');
    }

    // Verifica se estamos postando os dados
    if ($request->isPost()) {
      // Os dados estão sendo postados

      // Registra o acesso
      $this->debug("Processando à adição de equipamento.");

      // Valida os dados
      $this->validator->validate($request,
        $this->getValidationRules(true)
      );

      if ($this->validator->isValid()) {
        $this->debug('Os dados do equipamento são VÁLIDOS');

        // Recupera os dados do equipamento
        $equipmentData = $this->validator->getValues();

        try
        {
          // Lidamos com validações adicionais
          $allHasValid = true;
          // Verifica se foi informado modelo do equipamento
          if (intval($equipmentData['equipmentmodelid']) === 0) {
            // Seta o erro neste campo
            $this->validator->setErrors([
                'equipmentmodelname' =>
                  'O modelo do equipamento é obrigatório'
              ],
              "equipmentmodelname")
            ;
            $allHasValid = false;
          }

          if (Equipment::where("contractorid", '=', $contractor->id)
                ->whereRaw("equipments.serialnumber = '{$equipmentData['serialnumber']}'")
                ->count() > 0) {
            $this->validator->addError('serialnumber',
              'Já existe um equipamento com o mesmo número de série.'
            );
            $allHasValid = false;
          }

          // Verifica se não temos um equipamento com o mesmo IMEI e/ou
          // número de série
          if ($allHasValid) {
            // Grava o novo equipamento
            $userID = $this->authorization->getUser()->userid;

            // Iniciamos a transação
            $this->DB->beginTransaction();

            // Incluímos um novo equipamento
            $equipment = new Equipment();
            $equipment->fill($equipmentData);
            // Adicionamos as informações do contratante
            $equipment->contractorid = $contractor->id;
            $equipment->createdbyuserid = $userID;
            $equipment->updatedbyuserid = $userID;
            $equipment->save();

            // Efetiva a transação
            $this->DB->commit();

            // Registra o sucesso
            $this->info("Cadastrado o equipamento nº de série "
              . "'{serialnumber}' com sucesso.",
              [
                'serialnumber'  => $equipmentData['serialnumber']
              ]
            );

            // Alerta o usuário
            $this->flash("success", "O equipamento nº de série <i>"
              . "'{serialnumber}'</i> foi cadastrado com sucesso.",
              [ 'serialnumber'  => $equipmentData['serialnumber'] ]
            );

            // Registra o evento
            $this->debug("Redirecionando para {routeName}",
              [
                'routeName' => 'ERP\Devices\Equipments'
              ]
            );

            // Redireciona para a página de gerenciamento de equipamentos
            return $this->redirect($response, 'ERP\Devices\Equipments');
          } else {
            $this->debug('Os dados do equipamento são INVÁLIDOS');
            $messages = $this->validator->getFormatedErrors();
            foreach ($messages AS $message) {
              $this->debug($message);
            }
          }
        }
        catch(QueryException $exception)
        {
          // Reverte (desfaz) a transação
          $this->DB->rollBack();

          // Registra o erro
          $this->error("Não foi possível inserir as informações do "
            . "equipamento nº de série '{serialnumber}'. Erro interno "
            . "no banco de dados: {error}",
            [
              'serialnumber'  => $equipmentData['serialnumber'],
              'error' => $exception->getMessage()
            ]
          );

          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações do equipamento. Erro interno no banco de "
            . "dados."
          );
        }
        catch(Exception $exception)
        {
          // Reverte (desfaz) a transação
          $this->DB->rollBack();

          // Registra o erro
          $this->error("Não foi possível inserir as informações do "
            . "equipamento nº de série '{serialnumber}'. Erro interno: "
            . "{error}",
            [
              'serialnumber'  => $equipmentData['serialnumber'],
              'error' => $exception->getMessage()
            ]
          );

          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações do equipamento. Erro interno."
          );
        }
      } else {
        $this->debug('Os dados do equipamento são INVÁLIDOS');
        $messages = $this->validator->getFormatedErrors();
        foreach ($messages AS $message) {
          $this->debug($message);
        }
      }
    } else {
      // Carrega os dados iniciais para simplificar a digitação
      $this->validator->setValues(
      [
        'mobileoperatorid' => 0,
        'equipmentmodelid' => 0,
        'equipmentbrandid' => 0,
        'ownershiptype' => 0
      ]);
    }

    // Exibe um formulário para adição de um equipamento

    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ERP\Home')
    );
    $this->breadcrumb->push('Dispositivos', '');
    $this->breadcrumb->push('Equipamentos',
      $this->path('ERP\Devices\Equipments')
    );
    $this->breadcrumb->push('Adicionar',
      $this->path('ERP\Devices\Equipments\Add')
    );

    // Registra o acesso
    $this->info("Acesso à adição de equipamento.");

    return $this->render($request, $response,
      'erp/devices/equipments/equipment.twig',
      [ 'formMethod' => 'POST',
        'deposits' => $deposits,
        'ownershipTypes' => $ownershipTypes ])
    ;
  }

  /**
   * Exibe um formulário para edição de um equipamento, quando
   * solicitado, e confirma os dados enviados.
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
  public function edit(
    Request $request,
    Response $response,
    array $args
  ): Response
  {
    $equipmentID = $args['equipmentID'];

    try {
      // Recupera os dados do contratante
      $contractor = $this->authorization->getContractor();

      // Recupera as informações de tipos de propriedade
      $ownershipTypes = $this->getOwnershipTypes();
    }
    catch (RuntimeException $exception)
    {
      // Alerta o usuário
      $this->flash("error", $exception->getMessage());

      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [
          'routeName' => 'ERP\Devices\Equipments'
        ]
      );

      // Redireciona para a página de gerenciamento de equipamentos
      return $this->redirect($response, 'ERP\Devices\Equipments');
    }

    try
    {
      // Recupera as informações do equipamento
      $equipment = Equipment::join('equipmentmodels',
            'equipments.equipmentmodelid', '=',
            'equipmentmodels.equipmentmodelid'
          )
        ->join('equipmentbrands', 'equipmentmodels.equipmentbrandid',
            '=', 'equipmentbrands.equipmentbrandid'
          )
        ->join('simcardtypes', 'equipmentmodels.simcardtypeid',
            '=', 'simcardtypes.simcardtypeid'
          )
        ->join('ownershiptypes', 'equipments.ownershiptypeid',
            '=', 'ownershiptypes.ownershiptypeid'
          )
        ->join('entities AS contractor', 'equipments.contractorid',
            '=', 'contractor.entityid'
          )
        ->join('entities AS supplier', 'equipments.supplierid',
            '=', 'supplier.entityid'
          )
        ->join('subsidiaries AS subsidiary', 'equipments.subsidiaryid',
            '=', 'subsidiary.subsidiaryid'
          )
        ->join('users AS createduser', 'equipments.createdbyuserid',
            '=', 'createduser.userid'
          )
        ->join('users AS updateduser', 'equipments.updatedbyuserid',
            '=', 'updateduser.userid'
          )
        ->whereRaw("(equipments.contractorid = {$contractor->id} OR equipments.assignedtoid = {$contractor->id})")
        ->where('equipments.equipmentid', $equipmentID)
        ->get([
            'equipments.equipmentid',
            'equipments.contractorid',
            'equipments.assignedtoid',
            'equipments.equipmentmodelid',
            'equipmentmodels.name AS equipmentmodelname',
            'equipmentmodels.equipmentbrandid',
            'equipmentbrands.name AS equipmentbrandname',
            'equipmentmodels.maxsimcards',
            'equipmentmodels.simcardtypeid',
            'simcardtypes.name AS simcardtypename',
            'equipments.serialnumber',
            'equipments.imei',
            'equipments.equipmentstateid',
            'equipments.storagelocation',
            $this->DB->raw("getStorageLocation(equipments.storagelocation, "
              . "equipments.depositid, 'Equipment', equipments.vehicleid) AS storedlocationname"
            ),
            'equipments.technicianid',
            'equipments.serviceproviderid',
            'equipments.depositid',
            'equipments.vehicleid',
            'equipments.main',
            'equipments.installedat',
            'equipments.installationsite',
            'equipments.installationid',
            $this->DB->raw(""
              . "CASE "
              .   "WHEN equipments.assignedtoid = {$contractor->id} THEN 2 "
              .   "ELSE equipments.ownershiptypeid "
              . "END AS ownershiptypeid"
            ),
            $this->DB->raw(""
              . "CASE "
              .   "WHEN equipments.assignedtoid = {$contractor->id} THEN 'Comodato' "
              .   "ELSE ownershiptypes.name "
              . "END AS ownershiptypename"
            ),
            $this->DB->raw(""
              . "CASE "
              .   "WHEN equipments.assignedtoid = {$contractor->id} THEN equipments.contractorid "
              .   "ELSE equipments.supplierid "
              . "END AS supplierid"
            ),
            $this->DB->raw(""
              . "CASE "
              .   "WHEN equipments.assignedtoid = {$contractor->id} THEN contractor.name "
              .   "ELSE supplier.name "
              . "END AS suppliername"
            ),
            $this->DB->raw(""
              . "CASE "
              .   "WHEN equipments.assignedtoid = {$contractor->id} THEN 0 "
              .   "ELSE equipments.subsidiaryid "
              . "END AS subsidiaryid"
            ),
            $this->DB->raw(""
              . "CASE "
              .   "WHEN equipments.assignedtoid = {$contractor->id} THEN '' "
              .   "ELSE subsidiary.name "
              . "END AS subsidiaryname"
            ),
            $this->DB->raw(""
              . "CASE "
              .   "WHEN equipments.assignedtoid = {$contractor->id} THEN '' "
              .   "ELSE equipments.assetnumber "
              . "END AS assetnumber"
            ),
            $this->DB->raw(""
              . "CASE "
              .   "WHEN equipments.assignedtoid = {$contractor->id} THEN TRUE "
              .   "ELSE FALSE "
              . "END AS isleasedequipment"
            ),
            $this->DB->raw(""
              . "CASE "
              .   "WHEN equipments.assignedtoid IS NOT NULL AND equipments.assignedtoid <> {$contractor->id} THEN TRUE "
              .   "ELSE FALSE "
              . "END AS leasedingequipment"
            ),
            'equipments.createdat',
            'createduser.name AS createdbyusername',
            'equipments.updatedat',
            'updateduser.name AS updatedbyusername'
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

      // Recupera a informação dos SIM Cards associados com este
      // equipamento
      $sql = ""
        . "SELECT SLOT.simcardID AS id,"
        . "       SLOT.contractorID,"
        . "       SLOT.assignedToID,"
        . "       SLOT.slotnumber AS number,"
        . "       SLOT.iccid,"
        . "       SLOT.phonenumber,"
        . "       SLOT.mobileOperatorName"
        . "  FROM erp.getSlotData({$contractor->id}, {$equipmentID}) AS SLOT;"
      ;
      $simcardPerSlot = (array) $this->DB->select($sql);
      $equipment['simcardPerSlot'] = $simcardPerSlot;

      // Agora recupera as informações do veículo no qual está instalado
      $vehicle = [];
      if ($equipment['storagelocation'] === 'Installed') {
        $this->debug("Recuperando dados do veículo");
        // Recupera as informações do veículo onde se está instalando o
        // equipamento
        $vehicleID = $equipment['vehicleid'];
        $vehicle = Vehicle::join('vehicletypes', 'vehicles.vehicletypeid',
              '=', 'vehicletypes.vehicletypeid'
            )
          ->join('vehiclemodels', 'vehicles.vehiclemodelid',
              '=', 'vehiclemodels.vehiclemodelid'
            )
          ->join('vehiclebrands', 'vehicles.vehiclebrandid',
              '=', 'vehiclebrands.vehiclebrandid'
            )
          ->leftJoin('vehiclesubtypes', 'vehiclemodels.vehiclesubtypeid',
              '=', 'vehiclesubtypes.vehiclesubtypeid'
            )
          ->join('vehiclecolors', 'vehicles.vehiclecolorid',
              '=', 'vehiclecolors.vehiclecolorid'
            )
          ->join('entities AS contractor', 'vehicles.contractorid', '=',
              'contractor.entityid'
            )
          ->where('vehicles.vehicleid', '=', $vehicleID)
          ->get([
              'vehicles.vehicleid',
              'vehicles.contractorid',
              'contractor.name AS contractorname',
              'vehicles.plate',
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
              'vehicles.carnumber',
              'vehicles.renavam',
              'vehicles.vin'
            ])
        ;

        if ( $vehicle->isEmpty() ) {
          $this->debug("Não achei o veículo com o código {$vehicleID}");
          throw new ModelNotFoundException("Não temos nenhum veículo "
            . "com o código {$vehicleID} cadastrado."
          );
        }

        $vehicle = $vehicle
          ->first()
          ->toArray()
        ;

        // Adiciona a informação se o veículo pertence a este
        // contratante ou ao contratante ao qual o equipamento está
        // comodatado
        $vehicle['isRentedVehicle'] = ($vehicle['contractorid'] !== $contractor->id);
      }

      $equipment['vehicle'] = $vehicle;

      // Agora recupera as informações do cliente ao qual o equipamento
      // foi comodatado
      $leasedequipment = [];
      if ($equipment['leasedingequipment']) {
        $this->debug("Recuperando dados do comodato");
        // Recupera as informações do contratante para quem o
        // equipamento foi comodatado
        $assignedToID = $equipment['assignedtoid'];
        $leasedequipment = LeasedEquipment::join('entities as assigned', 'leasedequipments.assignedto',
              '=', 'assigned.entityid'
            )
          ->where('leasedequipments.contractorid', '=', $contractor->id)
          ->where('leasedequipments.equipmentid', '=', $equipmentID)
          ->where('leasedequipments.assignedto', '=', $assignedToID)
          ->whereNull('leasedequipments.enddate')
          ->get([
              'leasedequipments.leasedequipmentid',
              'leasedequipments.assignedto',
              'assigned.name AS assignedtoname',
              'leasedequipments.startdate',
              'leasedequipments.graceperiod',
              $this->DB->raw(""
                . "CASE "
                .   "WHEN leasedequipments.graceperiod > 0 THEN TO_CHAR(leasedequipments.startdate + leasedequipments.graceperiod * interval '1 month', 'DD/MM/YYYY') "
                .   "ELSE NULL "
                . "END AS endofgraceperiod"
              ),
              'leasedequipments.enddate'
            ])
        ;

        if ( $leasedequipment->isEmpty() ) {
          throw new ModelNotFoundException("Não temos os dados de "
            . "comodato do equipamento com o código {$equipmentID} "
            . "cadastrado."
          );
        }

        $leasedequipment = $leasedequipment
          ->first()
          ->toArray()
        ;
      } else {
        // Não temos um comodato para este equipamento, então colocamos
        // os valores padrão

        // Obtém a data atual
        $today = Carbon::now();
        $currentDate = $today->format('d/m/Y');

        $leasedequipment = [
          'leasedequipmentid' => 0,
          'assignedto' => 0,
          'assignedtoname' => '',
          'startdate' => $currentDate,
          'graceperiod' => 0,
          'endofgraceperiod' => '',
          'enddate' => ''
        ];
      }
      $equipment['leasedequipment'][] = $leasedequipment;
    } catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar o equipamento código "
        . "{equipmentID}.",
        [
          'equipmentID' => $equipmentID
        ]
      );

      // Alerta o usuário
      $this->flash("error", "Não foi possível localizar este "
        . "equipamento."
      );

      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [
          'routeName' => 'ERP\Devices\Equipments'
        ]
      );

      // Redireciona para a página de gerenciamento de equipamentos
      return $this->redirect($response, 'ERP\Devices\Equipments');
    }
      
    // Verifica se estamos modificando os dados
    if ($request->isPut()) {
      // Os dados estão sendo modificados

      // Registra o acesso
      $this->debug("Processando à edição do equipamento nº de série "
        . "'{serialnumber}'.",
        [
          'serialnumber' => $equipment['serialnumber']
        ]
      );

      // Valida os dados
      $this->validator->validate($request,
        $this->getValidationRules(false)
      );

      if ($this->validator->isValid()) {
        $this->debug('Os dados do equipamento são VÁLIDOS');

        // Recupera os dados modificados do equipamento
        $equipmentData = $this->validator->getValues();

        try
        {
          $allHasValid = true;

          // Verifica se o nº de série foi modificado
          if ($equipmentData['serialnumber'] !== $equipment['serialnumber']) {
            // Verifica não temos um equipamento com o mesmo nº de série
            // independente do fornecedor ao qual ele está vinculado
            if (Equipment::where("contractorid", '=', $contractor->id)
                  ->whereRaw("equipments.serialnumber = '{$equipmentData['serialnumber']}'")
                  ->count() > 0) {
              // Registra o erro
              $this->debug("Não foi possível modificar as "
                . "informações do equipamento do nº de série "
                . "'{serialnumber}'. Já existe outro equipamento com o "
                . "mesmo número de série.",
                [
                  'serialnumber' => $equipment['serialnumber']
                ]
              );

              // Alerta o usuário
              $this->flashNow("error", "Já existe um equipamento com o "
                . "mesmo número de série."
              );

              $allHasValid = false;
            }
          }

          // Verifica se o equipamento está sendo colocado em comodato
          $leasedingequipment = filter_var(
            $equipmentData['leasedingequipment'],
            FILTER_VALIDATE_BOOLEAN
          );
          if ($leasedingequipment == true) {
            $this->debug(
              "Verifica os parâmetros quando o equipamento está sendo colocado em comodato."
            );

            $assignedToID = intval($equipmentData['leasedequipment'][0]['assignedto']);
            if ($assignedToID > 0) {
              // Verifica se o equipamento está sendo colocado em
              // comodato para o mesmo contratante
              if ($assignedToID === $contractor->id) {
                // Seta o erro neste campo
                $this->validator->setErrors([
                    'leasedequipment[0][assignedtoname]' =>
                      'Você não pode comodatar um equipamento para si '
                      . 'mesmo.'
                  ],
                  "leasedequipment[0][assignedtoname]"
                );
                $allHasValid = false;
              }
            } else {
              // Seta o erro neste campo
              $this->validator->setErrors([
                  'leasedequipment[0][assignedtoname]' =>
                    'Informe o nome do comodatário.'
                ],
                "leasedequipment[0][assignedtoname]"
              );
              $allHasValid = false;
            }

            if ($equipment['leasedingequipment'] === false) {
              // O equipamento não estava em comodato, e estamos o
              // colocando neste momento, então verifica se a data de
              // término foi informada
              if (!empty($equipmentData['leasedequipment'][0]['enddate'])) {
                // Seta o erro neste campo
                $this->validator->setErrors([
                    'leasedequipment[0][enddate]' =>
                      'A data de término do comodato somente deve ser '
                      . 'informada se o equipamento estiver em '
                      . 'comodato.'
                  ],
                  "leasedequipment[0][enddate]"
                );
                $allHasValid = false;
              }
            }
          }

          if ($allHasValid) {
            // Grava as modificações dos dados do equipamento

            // Retira as informações do veículo e simcards
            unset($equipmentData['vehicle']);
            unset($equipmentData['simcardPerSlot']);

            // Retira as informações do comodato
            $beforeLeasedEquipment = filter_var($equipment['leasedingequipment'], FILTER_VALIDATE_BOOLEAN);
            $leasedEquipmentData = array_key_exists('leasedequipment', $equipmentData)
              ? $equipmentData['leasedequipment'][0]
              : null
            ;
            if ($leasedEquipmentData) {
              // Verifica se a data de término do comodato foi informada
              if (empty($leasedEquipmentData['enddate'])) {
                // Retira a data de término do comodato
                unset($leasedEquipmentData['enddate']);
              } else {
                // Força a indicação de que estamos encerrando o
                // comodato do equipamento
                $leasedingequipment = false;
              }
            }
            unset($equipmentData['leasedequipment']);

            // Iniciamos a transação
            $this->info("Iniciamos a transação");
            $this->DB->beginTransaction();

            // Obtemos o ID do usuário que está realizando a modificação
            $userID = $this->authorization->getUser()->userid;

            if ($beforeLeasedEquipment !== $leasedingequipment) {
              // Houve uma mudança no estado de comodato, então
              // determina para qual depósito de contratante este
              // equipamento será transferido
              $this->debug("Houve uma mudança no estado de comodato, "
                . "então determina para qual depósito de contratante "
                . "este equipamento será transferido"
              );
              $depositContractorID = $beforeLeasedEquipment == false
                ? $leasedEquipmentData['assignedto']
                : $contractor->id
              ;

              // Recupera as informações de depósitos
              $deposits = Deposit::where("contractorid", '=', $depositContractorID)
                ->whereRaw("devicetype IN ('Equipment', 'Both')")
                ->orderBy('name')
                ->get([
                    'depositid AS id',
                    'name',
                    'master'
                  ])
              ;

              // Obtem o depósito principal deste contratante
              $depositID = $deposits[0]->id;
              foreach ($deposits as $deposit) {
                if ($deposit->master) {
                  $depositID = $deposit->id;

                  break;
                }
              }

              $this->info("O depósito principal do contratante ID "
                . "{contractorID} é o depósito nº {depositID}.",
                [
                  'contractorID' => $depositContractorID,
                  'depositID' => $depositID
                ]
              );

              // Adicionamos as informações do local onde o
              // equipamento estará armazenado
              $equipmentData['storagelocation'] = 'StoredOnDeposit';
              $equipmentData['depositid'] = $depositID;
            }

            // Verifica se precisa desvincular o equipamento do veículo
            if ($equipment['storagelocation'] === 'Installed') {
              $this->debug("O equipamento está vinculado");
              if ($beforeLeasedEquipment !== $leasedingequipment) {
                // Houve uma mudança no estado de comodato, então força
                // a desvinculação do equipamento do veículo
                $this->debug("O equipamento será desvinculado");
                $vehicleID = $equipment['vehicleid'];
                $installationID = $equipment['installationid'];
                $equipmentData['vehicleid'] = null;

                // Retiramos as informações do pagante
                $equipmentData['customerpayerid'] = null;
                $equipmentData['subsidiarypayerid'] = null;

                // Não precisamos colocar os dados do depósito pois
                // estes já foram incluídos acima

                // Retira o equipamento da tabela de integração com a
                // STC
                EquipmentsToGetHistory::where('equipmentid', $equipmentID)
                  ->where('contractorid', $contractor->id)
                  ->where('platform', 'STC')
                  ->delete()
                ;

                // Retira o equipamento de usuários para os quais foi
                // autorizado
                AuthorizedEquipment::where('equipmentid', $equipmentID)
                  ->where('contractorid', $contractor->id)
                  ->delete()
                ;

                $today = Carbon::now();
                $endDate = $today->format('Y-m-d');

                $installationRecord = InstallationRecord::where('equipmentid', $equipmentID)
                  ->where('contractorid', $contractor->id)
                  ->where('vehicleid', $vehicleID)
                  ->where('installationid', $installationID)
                  ->orderBy('uninstalledat')
                  ->latest()
                  ->first()
                ;

                if ($installationRecord) {
                  $installationRecord->uninstalledat = $endDate;
                  $installationRecord->updatedat = Carbon::now();
                  $installationRecord->updatedbyuserid = $userID;
                  $installationRecord->save();
                }
              }
            }

            // Os simcards que precisamos comodatar
            $simcardsToLeased = [];
            // Os simcards que precisamos descomodatar
            $simcardsToEndLeased = [];
            // Os simcards que precisamos desvincular
            $simcardsToUnlink = [];
            if ($beforeLeasedEquipment !== $leasedingequipment) {
              // Verifica se o equipamento possui SIM Cards associados
              $this->debug("Verifica se o equipamento possui SIM Cards associados");
              if (count($simcardPerSlot) > 0) {
                // Analisa os SIM Cards associados com este equipamento,
                // de forma a verificar se os mesmos pertencem ao
                // contratante dono do equipamento
                $this->debug("Analisando os SIM Cards associados com "
                  . "este equipamento"
                );

                foreach ($simcardPerSlot as $slot) {
                  if ($slot->id > 0) {
                    if ($beforeLeasedEquipment) {
                      // O equipamento estava em comodato e será
                      // retornado ao estoque do comodante
                      if ($slot->assignedtoid === $leasedEquipmentData['assignedto']) {
                        // O SIM Card está em comodato também para o
                        // contratante ao qual o equipamento estáva
                        // sendo comodatado, então encerramos o comodato
                        $simcardsToEndLeased[] = $slot->id;
                      } elseif ($slot->contractorid === $contractor->id) {
                        // O SIM Card pertence ao contratante dono do
                        // equipamento, não fazemos nada
                      } else {
                        // O SIM Card pertence ao outro contratante,
                        // então desvinculamos ele
                        $simcardsToUnlink[] = $slot->id;
                      }
                    } else {
                      if ($slot->assignedtoid === $leasedEquipmentData['assignedto']) {
                        // O SIM Card já está em comodato para o
                        // contratante ao qual o equipamento está sendo
                        // comodatado, então não faz nada
                      } elseif ($slot->contractorid === $contractor->id) {
                        // O SIM Card pertence ao contratante dono do
                        // equipamento, então comodatamos ele também
                        $simcardsToLeased[] = $slot->id;
                      } else {
                        // O SIM Card pertence a outro contratante,
                        // então desvinculamos ele
                        $simcardsToUnlink[] = $slot->id;
                      }
                    }
                  }
                }
              }
            }

            // Verifica se o equipamento está sendo colocado em comodato
            if ($leasedingequipment) {
              $this->debug("O equipamento está sendo colocado em comodato");
              // Verifica se o equipamento estava em comodato
              if ($beforeLeasedEquipment === false) {
                // O equipamento não estava em comodato, então incluímos
                // as informações de comodato
                $leasedEquipmentData['equipmentid'] = $equipmentID;
                $leasedEquipmentData['contractorid'] = $contractor->id;

                // Retira o ID do comodato
                unset($leasedEquipmentData['leasedequipmentid']);

                // Incluímos as informações de comodato
                $leasedEquipment = new LeasedEquipment();
                $leasedEquipment->fill($leasedEquipmentData);
                $leasedEquipment->save();

                if (count($simcardsToLeased) > 0) {
                  // Comodatamos os SIM Cards
                  $this->debug("Comodatando os SIM Cards vinculados");
                  foreach ($simcardsToLeased as $simcardID) {
                    // Copia $leasedEquipmentData para $leasedSimcardData
                    $leasedSimcardData = $leasedEquipmentData;
                    unset($leasedSimcardData['equipmentid']);
                    $leasedSimcardData['simcardid'] = $simcardID;

                    // Incluímos as informações de comodato
                    $leasedSimcard = new LeasedSimcard();
                    $leasedSimcard->fill($leasedSimcardData);
                    $leasedSimcard->save();
                  }
                }
                
                if (count($simcardsToUnlink) > 0) {
                  // Desvinculamos os SIM Cards
                  $this->debug("Desvinculado os SIM Cards vinculados");
                  foreach ($simcardsToUnlink as $simcardID) {
                    // Desvincula o SIM Card
                    $simcardChanged = Simcard::findOrFail($simcardID);
                    $simcardChanged->storagelocation = 'StoredOnDeposit';
                    $simcardChanged->depositid = $depositID;
                    $simcardChanged->equipmentid = null;
                    $simcardChanged->slotnumber = 0;
                    $simcardChanged->updatedbyuserid = $userID;
                    $simcardChanged->save();
                  }
                }
              } else {
                // O equipamento já estava em comodato, então apenas
                // atualizamos as informações de comodato
                $leasedEquipmentID = $leasedEquipmentData['leasedequipmentid'];
                $leasedEquipment = LeasedEquipment::findOrFail($leasedEquipmentID);
                $leasedEquipment->fill($leasedEquipmentData);
                $leasedEquipment->save();
              }
            } else {
              if ($beforeLeasedEquipment) {
                // Encerramos o comodato do equipamento. Verifica se
                // temos os dados do comodato
                if ($leasedEquipmentData) {
                  // Temos os dados do comodato, então utilizamos as
                  // informações de término nele contidas
                  $leasedEquipmentID = $leasedEquipmentData['leasedequipmentid'];
                  $endDate = $leasedEquipmentData['enddate'];
                  if (empty($endDate)) {
                    $endDate = Carbon::now();
                  } else {
                    $endDate = Carbon::createFromFormat('d/m/Y', $endDate);
                  }
    
                  $leasedEquipment = LeasedEquipment::findOrFail($leasedEquipmentID);
                  $leasedEquipment->enddate = $endDate;
                  $leasedEquipment->save();
                } else {
                  // Não temos os dados do comodato, então localizamos
                  // ele para encerrar

                  // Localiza o comodato do equipamento
                  $leasedEquipment = LeasedEquipment::where('equipmentid', $equipmentID)
                    ->where('contractorid', $contractor->id)
                    ->where('equipmentid', $equipmentID)
                    ->where('assignedto', $equipment['assignedtoid'])
                    ->whereNull('enddate')
                    ->first()
                  ;

                  // Utiliza a data atual
                  $endDate = Carbon::now();

                  if ($leasedEquipment) {
                    $leasedEquipment->enddate = $endDate;
                    $leasedEquipment->save();
                  }
                }

                if (count($simcardsToEndLeased) > 0) {
                  // Retiramos do comodato os SIM Cards
                  $this->debug("Retirando os SIM Cards do comodato");
                  foreach ($simcardsToEndLeased as $simcardID) {
                    // Localiza o registro de comodato do SIM Card
                    $leasedSimcard = LeasedSimcard::where('simcardid', $simcardID)
                      ->where('contractorid', $contractor->id)
                      ->where('assignedto', $leasedEquipmentData['assignedto'])
                      ->whereNull('enddate')
                      ->first()
                    ;

                    if ($leasedSimcard) {
                      $leasedSimcard->update([
                          'enddate' => $endDate
                        ])
                      ;
                    }
                  }
                }

                if (count($simcardsToUnlink) > 0) {
                  // Desvinculamos os SIM Cards
                  $this->debug("Desvinculado os SIM Cards vinculados");
                  foreach ($simcardsToUnlink as $simcardID) {
                    // Desvincula o SIM Card
                    $simcardChanged = Simcard::findOrFail($simcardID);
                    $simcardChanged->equipmentid = null;
                    $simcardChanged->slotnumber = 0;
                    $simcardChanged->updatedbyuserid = $userID;
                    $simcardChanged->save();
                  }
                }
              }
            }

            // Modificamos os dados do equipamento
            $equipmentChanged = Equipment::findOrFail($equipmentID);
            $equipmentChanged->fill($equipmentData);
            // Adicionamos as informações do responsável pela modificação
            $equipmentChanged->updatedbyuserid = $userID;
            $equipmentChanged->save();

            // Efetiva a transação
            $this->DB->commit();

            // Registra o sucesso
            $this->info("O equipamento nº de série '{serialnumber}' "
              . "foi modificado com sucesso.",
              [
                'serialnumber' => $equipmentData['serialnumber']
              ]
            );

            // Alerta o usuário
            $this->flash("success", "O equipamento nº de série "
              . "<i>'{serialnumber}'</i> foi modificado com sucesso.",
              [ 'serialnumber' => $equipmentData['serialnumber'] ]
            );

            // Registra o evento
            $this->debug("Redirecionando para {routeName}",
              [
                'routeName' => 'ERP\Devices\Equipments'
              ]
            );

            // Redireciona para a página de gerenciamento de
            // equipamentos
            return $this->redirect($response,
              'ERP\Devices\Equipments')
            ;
          } else {
            $this->debug('Os dados do equipamento são INVÁLIDOS');
            $messages = $this->validator->getFormatedErrors();
            foreach ($messages AS $message) {
              $this->debug($message);
            }

            $this->validator->setValue('simcardPerSlot', $simcardPerSlot);
            $this->validator->setValue('vehicle', $vehicle);
            $this->validator->setValue('leasedequipment', [$leasedequipment]);
          }
        }
        catch(QueryException $exception)
        {
          // Reverte (desfaz) a transação
          $this->DB->rollBack();

          // Registra o erro
          $this->error("Não foi possível modificar as informações do "
            . "equipamento nº de série '{serialnumber}'. Erro "
            . "interno no banco de dados: {error}",
            [
              'serialnumber' => $equipmentData['serialnumber'],
              'error' => $exception->getMessage()
            ]
          );

          // Alerta o usuário
          $this->flashNow("error", "Não foi possível modificar as "
            . "informações do equipamento. Erro interno no banco de "
            . "dados."
          );
        }
        catch(Exception $exception)
        {
          // Reverte (desfaz) a transação
          $this->DB->rollBack();

          // Registra o erro
          $this->error("Não foi possível modificar as informações do "
            . "equipamento nº de série '{serialnumber}'. Erro "
            . "interno: {error}",
            [
              'serialnumber'  => $equipmentData['serialnumber'],
              'error' => $exception->getMessage()
            ]
          );

          // Alerta o usuário
          $this->flashNow("error", "Não foi possível modificar as "
            . "informações do equipamento. Erro interno."
          );
        }
      } else {
        $this->debug('Os dados do equipamento são INVÁLIDOS');
        $messages = $this->validator->getFormatedErrors();
        foreach ($messages AS $message) {
          $this->debug($message);
        }

        $this->validator->setValue('simcardPerSlot', $simcardPerSlot);
        $this->validator->setValue('vehicle', $vehicle);
        $this->validator->setValue('leasedequipment', [$leasedequipment]);
      }
    } else {
      // Carrega os dados atuais
      $this->validator->setValues($equipment);
    }

    // Exibe um formulário para edição de um equipamento

    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ERP\Home')
    );
    $this->breadcrumb->push('Dispositivos', '');
    $this->breadcrumb->push('Equipamentos',
      $this->path('ERP\Devices\Equipments')
    );
    $this->breadcrumb->push('Editar',
      $this->path('ERP\Devices\Equipments\Edit', [
        'equipmentID' => $equipmentID
      ])
    );

    // Registra o acesso
    $this->info("Acesso à edição do equipamento nº de série "
      . "'{serialnumber}'.",
      [
        'serialnumber' => $equipment['serialnumber']
      ]
    );

    // Renderiza a página
    return $this->render($request, $response,
      'erp/devices/equipments/equipment.twig',
      [ 'formMethod' => 'PUT',
        'ownershipTypes' => $ownershipTypes ])
    ;
  }

  /**
   * Exibe um formulário para permitir informar a instalação de um Sim
   * Card em um slot de um equipamento, quando solicitado, e confirmar
   * os dados enviados. O número do slot e o equipamento são informados
   * previamente, já que este diálogo é acionado através da interface
   * pelo click em um slot livre do equipamento.
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
  public function slotAttach(
    Request $request,
    Response $response,
    array $args
  ): Response
  {
    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();

    // Recupera as informações do equipamento e o número do slot onde o
    // SIM Card será instalado
    $equipmentID  = $args['equipmentID'];
    $slotNumber   = $args['slotNumber'];

    try
    {
      // Recupera as informações do equipamento onde se está instalando
      // o SIM Card
      $equipment = Equipment::join('equipmentmodels',
            'equipments.equipmentmodelid', '=',
            'equipmentmodels.equipmentmodelid'
          )
        ->join('simcardtypes AS slottype',
            'equipmentmodels.simcardtypeid', '=',
            'slottype.simcardtypeid'
          )
        ->join('entities AS supplier', 'equipments.supplierid',
            '=', 'supplier.entityid'
          )
        ->join('subsidiaries AS subsidiary', 'equipments.subsidiaryid',
            '=', 'subsidiary.subsidiaryid'
          )
        ->whereRaw("(equipments.contractorid = {$contractor->id} OR equipments.assignedtoid = {$contractor->id})")
        ->where('equipments.equipmentid', $equipmentID)
        ->get([
            'equipments.equipmentid',
            'equipments.serialnumber',
            'equipments.imei',
            'equipments.equipmentmodelid',
            'equipments.blocked',
            'supplier.blocked AS supplierblocked',
            'subsidiary.blocked AS subsidiaryblocked',
            'equipmentmodels.name AS equipmentmodelname',
            'equipmentmodels.simcardtypeid AS slottypeid',
            'slottype.name AS slottypename'
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

      // Adiciona a informação do slot
      $equipment['slotnumber'] = $slotNumber;
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

      // Alerta o usuário
      $this->flash("error", "Não foi possível localizar este "
        . "equipamento."
      );

      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [
          'routeName' => 'ERP\Devices\Equipments'
        ]
      );

      // Redireciona para a página de gerenciamento de equipamentos
      return $this->redirect($response, 'ERP\Devices\Equipments');
    }

    // Verifica se temos algum bloqueio
    if ( $equipment['supplierblocked'] ||
         $equipment['subsidiaryblocked'] ||
         $equipment['blocked'] ) {
      // Não permite associar um SIM Card à um equipamento bloqueado
      // e/ou de fornecedores bloqueados
      
      // Analisa o motivo do bloqueio
      if ($equipment['blocked']) {
        $whyIsBlocked = "O equipamento está bloqueado para uso.";
      } else {
        if ($equipment['supplierblocked']) {
          $whyIsBlocked = "O equipamento não está disponível para "
            . "uso pois o seu fornecedor encontra-se bloqueado."
          ;
        } else {
          $whyIsBlocked = "O equipamento não está disponível para "
            . "uso pois a unidade/filial do seu fornecedor "
            . "encontra-se bloqueada."
          ;
        }
      }

      // Registra o erro
      $this->debug("Não foi possível associar um SIM Card ao slot "
        . "{slotNumber} do equipamento nº de série '{serialnumber}'. "
        . "{why}",
        [
          'serialnumber' => $equipment['serialnumber'],
          'slotNumber' => $slotNumber,
          'why' => $whyIsBlocked
        ]
      );

      // Alerta o usuário
      $this->flash("error", "O equipamento nº de série <i>"
        . "'{serialnumber}'</i> está bloqueado para uso e, por esta "
        . "razão, não pode ter SIM Cards associados à ele.",
        [ 'serialnumber' => $equipment['serialnumber'] ]
      );

      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [
          'routeName' => 'ERP\Devices\Equipments'
        ]
      );

      // Redireciona para a página de gerenciamento de equipamentos
      return $this->redirect($response, 'ERP\Devices\Equipments');
    }

    // Verifica se estamos adicionando os dados
    if ($request->isPost()) {
      // Os dados estão sendo adicionados

      // Registra o acesso
      $this->debug("Processando à instalação do SIM Card no slot "
        . "{slotNumber} do equipamento nº de série '{serialnumber}'.",
        [
          'slotNumber' => $slotNumber,
          'serialnumber' => $equipment['serialnumber']
        ]
      );

      // Valida os dados
      $this->validator->validate($request, 
        $this->getValidationRulesForAttach()
      );

      if ($this->validator->isValid()) {
        $this->debug('Os dados do vínculo são VÁLIDOS');

        try
        {
          // Recupera os dados para a associação do SIM Card ao slot
          // do equipamento
          $attachmentData = $this->validator->getValues();
          $simcardID = $attachmentData['simcardid'];

          // Associa o SIM Card ao slot do equipamento e indica que a
          // operação é de instalação
          $simcardChanged = SimCard::findOrFail($simcardID);
          $simcardChanged->equipmentid      = $equipmentID;
          $simcardChanged->slotnumber       = $slotNumber;
          $simcardChanged->storagelocation  = 'Installed';
          // Adicionamos as informações do responsável pela instalação
          $simcardChanged->updatedbyuserid =
            $this->authorization->getUser()->userid
          ;
          $simcardChanged->save();

          // Registra o sucesso
          $this->info("O SIM Card ICCID '{iccid}' foi associado com "
            . "sucesso ao slot {slotNumber} do equipamento nº de "
            . "série '{serialnumber}'.",
            [
              'iccid' => $attachmentData['iccid'],
              'slotNumber' => $slotNumber,
              'serialnumber' => $attachmentData['serialnumber']
            ]
          );
          
          // Alerta o usuário
          $this->flash("success", "O SIM Card ICCID <i>'{iccid}'</i> "
            . "foi associado com sucesso ao slot {slotNumber} do "
            . "equipamento nº de série <i>'{serialnumber}'</i>.",
            [ 'iccid' => $attachmentData['iccid'],
              'slotNumber' => $slotNumber,
              'serialnumber' => $attachmentData['serialnumber'] ]
          );

          // Registra o evento
          $this->debug("Redirecionando para {routeName}",
            [
              'routeName' => 'ERP\Devices\Equipments'
            ]
          );

          // Redireciona para a página de gerenciamento de equipamentos
          return $this->redirect($response, 'ERP\Devices\Equipments');
        }
        catch(QueryException $exception)
        {
          // Registra o erro
          $this->error("Não foi possível associar o SIM Card ICCID "
            . "'{iccid}' ao slot {slotNumber} do equipamento nº de "
            . "série '{serialnumber}'. Erro interno no banco de "
            . "dados: {error}",
            [
              'iccid' => $attachmentData['iccid'],
              'slotNumber' => $slotNumber,
              'serialnumber' => $attachmentData['serialnumber'],
              'error' => $exception->getMessage()
            ]
          );

          if ($exception->getCode() == 23505) {
            // Erro de violação de unicidade
            $this->flashNow("error", "Não foi possível associar o "
              . "SIM Card ICCID <i>'{iccid}'</i> ao slot "
              . "<i>{slotNumber}</i> deste equipamento. Este Sim "
              . "Card e/ou o slot do equipamento já está em uso.",
              [ 'iccid' => $attachmentData['iccid'],
                'slotNumber' => $slotNumber ]
            );
          } else {
            // Alerta o usuário
            $this->flashNow("error", "Não foi possível associar o "
              . "SIM Card ICCID <i>'{iccid}'</i> ao slot "
              . "<i>{slotNumber}</i> deste equipamento. Erro interno "
              . "no banco de dados.",
              [ 'iccid' => $attachmentData['iccid'],
                'slotNumber' => $slotNumber ]
            );
          }
        }
        catch(Exception $exception)
        {
          // Registra o erro
          $this->error("Não foi possível associar o SIM Card ICCID "
            . "'{iccid}' ao slot {slotNumber} do equipamento nº de "
            . "série '{serialnumber}'. Erro interno: {error}",
            [
              'iccid' => $attachmentData['iccid'],
              'slotNumber' => $slotNumber,
              'serialnumber' => $attachmentData['serialnumber'],
              'error' => $exception->getMessage()
            ]
          );

          // Alerta o usuário
          $this->flashNow("error", "Não foi possível associar o Sim "
            . "Card ICCID <i>'{iccid}'</i> ao slot "
            . "<i>{slotNumber}</i> deste equipamento. Erro interno.",
            [ 'iccid' => $attachmentData['iccid'],
              'slotNumber' => $slotNumber ]
          );
        }
      } else {
        $this->debug('Os dados do vínculo são INVÁLIDOS');
        $messages = $this->validator->getFormatedErrors();
        foreach ($messages AS $message) {
          $this->debug($message);
        }
      }
    } else {
      // Carrega os dados atuais
      $this->validator->setValues($equipment);
    }

    // Exibe um formulário para associação de um SIM Card com um slot de
    // um equipamento

    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ERP\Home')
    );
    $this->breadcrumb->push('Dispositivos', '');
    $this->breadcrumb->push('Equipamentos',
      $this->path('ERP\Devices\Equipments')
    );
    $this->breadcrumb->push('Associar SIM Card a um slot',
      $this->path('ERP\Devices\Equipments\Slot\Attach', [
        'equipmentID' => $equipmentID, 'slotNumber' => $slotNumber
      ])
    );

    // Registra o acesso
    $this->info("Acesso à associação de SIM Card ao slot {slotNumber} "
      . "do equipamento nº de série '{serialnumber}'.",
      [
        'slotNumber' => $slotNumber,
        'serialnumber' => $equipment['serialnumber']
      ]
    );

    // Renderiza a página
    return $this->render($request, $response,
      'erp/devices/equipments/slotattach.twig',
      [ 'formMethod' => 'POST' ])
    ;
  }

  /**
   * Exibe um formulário para permitir informar a desinstalação de um
   * SIM Card de um slot de um equipamento, quando solicitado, e
   * confirmar os dados enviados. O número do slot e o equipamento são
   * informados previamente, já que este diálogo é acionado através da
   * interface pelo click em um slot ocupado do equipamento.
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
  public function slotDetach(
    Request $request,
    Response $response,
    array $args
  ): Response
  {
    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();

    // Recupera as informações do equipamento e o número do slot onde
    // o SIM Card está instalado
    $equipmentID  = $args['equipmentID'];
    $slotNumber   = $args['slotNumber'];

    // Recupera os dados da requisição
    $postParams   = $request->getParsedBody();

    // Recupera as informações do local de armazenamento
    $storageLocation   = $postParams['storageLocation'];
    $depositID         = $postParams['depositID'];
    $technicianID      = $postParams['technicianID'];
    $serviceproviderID = $postParams['serviceproviderID'];

    try
    {
      // Recupera as informações do equipamento de onde está sendo
      // desinstalado o SIM Card
      $equipment = Equipment::join('equipmentmodels',
            'equipments.equipmentmodelid', '=',
            'equipmentmodels.equipmentmodelid'
          )
        ->join('simcards',
            'equipments.equipmentid', '=',
            'simcards.equipmentid'
          )
        ->join('simcardtypes AS slottype',
            'equipmentmodels.simcardtypeid', '=',
            'slottype.simcardtypeid'
          )
        ->join('entities AS supplier', 'equipments.supplierid',
            '=', 'supplier.entityid'
          )
        ->join('subsidiaries AS subsidiary', 'equipments.subsidiaryid',
            '=', 'subsidiary.subsidiaryid'
          )
        ->whereRaw("(equipments.contractorid = {$contractor->id} OR equipments.assignedtoid = {$contractor->id})")
        ->where('equipments.equipmentid', $equipmentID)
        ->where('simcards.slotnumber', $slotNumber)
        ->get([
            'equipments.equipmentid',
            'equipments.serialnumber',
            'equipments.imei',
            'equipments.equipmentmodelid',
            'equipments.blocked',
            'simcards.slotnumber',
            'simcards.simcardid',
            'simcards.iccid',
            'supplier.blocked AS supplierblocked',
            'subsidiary.blocked AS subsidiaryblocked',
            'equipmentmodels.name AS equipmentmodelname',
            'equipmentmodels.simcardtypeid AS slottypeid',
            'slottype.name AS slottypename'
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
            'message' => "Não foi possível localizar o equipamento de "
              . "onde se está desinstalando o SIM Card.",
            'data' => null
          ])
      ;
    }

    // Registra o acesso
    $this->debug("Processando à desinstalação do SIM Card ICCID "
      . "'{iccid}' do slot {slotNumber} do equipamento nº de série "
      . "'{serialnumber}'.",
      [
        'iccid' => $equipment['iccid'],
        'slotNumber' => $slotNumber,
        'serialnumber' => $equipment['serialnumber']
      ]
    );
      
    // Verifica se temos algum bloqueio
    if ( $equipment['supplierblocked'] ||
         $equipment['subsidiaryblocked'] ||
         $equipment['blocked'] ) {
      // Não permite desassociar um SIM Card de um equipamento bloqueado
      // e/ou de fornecedores bloqueados
      
      // Analisa o motivo do bloqueio
      if ($equipment['blocked']) {
        $whyIsBlocked = "O equipamento está bloqueado para uso.";
      } else {
        if ($equipment['supplierblocked']) {
          $whyIsBlocked = "O equipamento não está disponível para "
            . "uso pois o seu fornecedor encontra-se bloqueado."
          ;
        } else {
          $whyIsBlocked = "O equipamento não está disponível para "
            . "uso pois a unidade/filial do seu fornecedor "
            . "encontra-se bloqueada."
          ;
        }
      }

      // Registra o erro
      $this->info("Não foi possível desassociar o SIM Card ICCID "
        . "'{iccid}' do slot {slotNumber} do equipamento nº de série "
        . "'{serialnumber}'. "
        . "{why}",
        [
          'iccid' => $equipment['iccid'],
          'slotNumber' => $slotNumber,
          'serialnumber' => $equipment['serialnumber'],
          'why' => $whyIsBlocked
        ]
      );
      $message = "Não foi possível desassociar o SIM Card deste "
        . "equipamento. " . $whyIsBlocked
      ;

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

    try
    {
      $userID = $this->authorization->getUser()->userid;

      // Recupera o SIM Card sendo desinstalado
      $simcardChanged = SimCard::findOrFail($equipment['simcardid']);


      // Retiramos as informações do equipamento
      $simcardChanged->equipmentid = null;
      $simcardChanged->slotnumber = 0;
      $simcardChanged->updatedbyuserid = $userID;

      // Adicionamos as informações do local onde o SIM Card estará
      // armazenado
      $simcardChanged->storagelocation = $storageLocation;
      switch ($storageLocation) {
        case 'StoredWithTechnician':
          // Ficará de posse do técnico, então informa o seu ID
          $simcardChanged->technicianid = $technicianID;

          break;
        case 'StoredWithServiceProvider':
          // Ficará de posse do prestador de serviços, então informa o
          // seu ID
          $simcardChanged->serviceproviderid = $serviceproviderID;

          break;
        
        default:
          // Ficará armazenado num depósito
          $simcardChanged->depositid = $depositID;

          break;
      }
      $simcardChanged->save();

      // Registra o sucesso
      $this->info("O SIM Card ICCID '{iccid}' foi desinstalado do "
        . "slot {slotnumber} do equipamento nº de série "
        . "'{serialnumber}' com sucesso.",
        [
          'iccid' => $equipment['iccid'],
          'slotnumber' => $slotNumber,
          'serialnumber' => $equipment['serialnumber']
        ]
      );

      // Informa que a desinstalação foi realizada com sucesso
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getParams(),
            'message' => "O SIM Card foi desinstalado do equipamento "
              . "IMEI {$simcardChanged['imei']}",
            'data' => "Delete"
          ])
      ;
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível desinstalar o SIM Card ICCID "
        . "'{iccid}' do slot {slotnumber} do equipamento nº de série "
        . "'{serialnumber}'. Erro interno no banco de dados: {error}",
        [
          'iccid' => $equipment['iccid'],
          'slotnumber' => $slotNumber,
          'serialnumber' => $equipment['serialnumber'],
          'error' => $exception->getMessage()
        ]
      );

      $message = "Não foi possível desinstalar o SIM Card. Erro "
        . "interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível desinstalar o SIM Card ICCID "
        . "'{iccid}' do slot {slotnumber} do equipamento nº de série "
        . "'{serialnumber}'. Erro interno: {error}",
        [
          'iccid' => $equipment['iccid'],
          'slotnumber' => $slotNumber,
          'serialnumber' => $equipment['serialnumber'],
          'error' => $exception->getMessage()
        ]
      );

      $message = "Não foi possível desinstalar o SIM Card. Erro "
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
   * Remove o equipamento.
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
  ): Response
  {
    // Recupera o ID do equipamento a ser removido
    $equipmentID = $args['equipmentID'];

    // Registra o acesso
    $this->debug("Processando à remoção de equipamento.");

    try
    {
      // Recupera as informações do equipamento
      $equipment = Equipment::findOrFail($equipmentID);

      // Iniciamos a transação
      $this->DB->beginTransaction();

      // Apaga o equipamento e as informações de vinculação dos SIM Cards
      $equipment->deleteCascade();
      
      // Efetiva a transação
      $this->DB->commit();

      // Registra o sucesso
      $this->info("O equipamento nº de série '{serialnumber}' foi "
        . "removido com sucesso.",
        [
          'serialnumber' => $equipment->serialnumber
        ]
      );

      // Informa que a remoção foi realizada com sucesso
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getParams(),
            'message' => "Removido o equipamento nº de série "
              . "{$equipment->serialnumber}",
            'data' => "Delete"
          ])
      ;
    }
    catch(ModelNotFoundException $exception)
    {
      // Reverte (desfaz) a transação
      $this->DB->rollBack();

      // Registra o erro
      $this->error("Não foi possível localizar o equipamento código "
        . "{equipmentID} para remoção.",
        [
          'equipmentID' => $equipmentID
        ]
      );

      $message = "Não foi possível localizar o equipamento para "
        . "remoção."
      ;
    }
    catch(QueryException $exception)
    {
      // Reverte (desfaz) a transação
      $this->DB->rollBack();

      // Registra o erro
      $this->error("Não foi possível remover as informações do "
        . "equipamento ID {equipmentID}. Erro interno no "
        . "banco de dados: {error}",
        [
          'equipmentID' => $equipmentID,
          'error' => $exception->getMessage()
        ]
      );

      $message = "Não foi possível remover o equipamento. Erro interno "
        . "no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Reverte (desfaz) a transação
      $this->DB->rollBack();

      // Registra o erro
      $this->error("Não foi possível remover as informações do "
        . "equipamento ID {equipmentID}. Erro interno: "
        . "{error}",
        [
          'equipmentID' => $equipmentID,
          'error' => $exception->getMessage()
        ]
      );

      $message = "Não foi possível remover o equipamento. Erro "
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
   * Alterna o estado do bloqueio de um equipamento e/ou de uma
   * unidade/filial deste equipamento.
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
  ): Response
  {
    // Registra o acesso
    $this->debug("Processando à mudança do estado de bloqueio do "
      . "equipamento."
    );

    // Recupera o ID
    $equipmentID = $args['equipmentID'];

    try
    {
      $userID = $this->authorization->getUser()->userid;

      // Recupera as informações do equipamento

      // Desbloqueia o equipamento
      $equipment = Equipment::findOrFail($equipmentID);
      $action = $equipment->blocked
        ? "desbloqueado"
        : "bloqueado"
      ;
      $equipment->blocked = !$equipment->blocked;
      $equipment->updatedbyuserid = $userID;
      $equipment->save();

      $message = "O equipamento nº de série "
        . "'{$equipment->serialnumber}' foi {$action} com sucesso."
      ;

      // Registra o sucesso
      $this->info($message);

      // Informa que a mudança foi realizada com sucesso
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getParams(),
            'message' => $message,
            'data' => 'Delete'
          ])
      ;
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar o equipamento código "
        . "{equipmentID} para alternar o estado do bloqueio.",
        [
          'equipmentID' => $equipmentID
        ]
      );

      $message = "Não foi possível localizar o equipamento para "
        . "alternar o estado do bloqueio."
      ;
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível alternar o estado do bloqueio do "
        . "equipamento nº de série '{serialnumber}'. Erro interno no "
        . "banco de dados: {error}.",
        [
          'serialnumber'  => $equipment->serialnumber,
          'error' => $exception->getMessage()
        ]
      );

      $message = "Não foi possível alternar o estado do bloqueio do "
        . "equipamento. Erro interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível alternar o estado do bloqueio do "
        . "equipamento nº de série '{serialnumber}'. Erro interno: "
        . "{error}.",
        [
          'serialnumber'  => $equipment->serialnumber,
          'error' => $exception->getMessage()
        ]
      );

      $message = "Não foi possível alternar o estado do bloqueio do "
        . "equipamento. Erro interno."
      ;
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
   * Gera um PDF para impressão das informações de um equipamento.
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
  ): Response
  {
    // Registra o acesso
    if (array_key_exists('equipmentID', $args)) {
      $this->debug("Processando à geração de PDF com as informações "
        . "cadastrais de um equipamento."
      );
    } else {
      $this->debug("Processando à geração de PDF com a relação de "
        . "equipamentos de um fornecedor."
      );
    }
    
    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();
    
    // Recupera as informações do fornecedor
    $contractorID = $contractor->id;
    $supplierID   = $args['supplierID'];
    $subsidiaryID = $args['subsidiaryID'];
    
    if (array_key_exists('equipmentID', $args)) {
      // Recuperamos as informações do equipamento
      $equipmentID = $args['equipmentID'];
      $equipment = Equipment::join('equipmentmodels',
            'equipments.equipmentmodelid', '=',
            'equipmentmodels.equipmentmodelid'
          )
        ->join('equipmentbrands', 'equipmentmodels.equipmentbrandid',
            '=', 'equipmentbrands.equipmentbrandid'
          )
        ->join('simcardtypes AS slottype', 'equipmentmodels.simcardtypeid',
            '=', 'slottype.simcardtypeid'
          )
        ->join('ownershiptypes', 'equipments.ownershiptypeid',
            '=', 'ownershiptypes.ownershiptypeid'
          )
        ->join('entities AS supplier', 'equipments.supplierid',
            '=', 'supplier.entityid'
          )
        ->join('subsidiaries AS subsidiary', 'equipments.subsidiaryid',
            '=', 'subsidiary.subsidiaryid'
          )
        ->join('users AS createduser', 'equipments.createdbyuserid',
            '=', 'createduser.userid'
          )
        ->join('users AS updateduser', 'equipments.updatedbyuserid',
            '=', 'updateduser.userid'
          )
        ->whereRaw("(equipments.contractorid = {$contractor->id} OR equipments.assignedtoid = {$contractor->id})")
        ->where('equipments.equipmentid', $equipmentID)
        ->get([ 
            'equipments.equipmentid AS id',
            'equipments.equipmentmodelid',
            'equipmentmodels.name AS equipmentmodelname',
            'equipmentmodels.equipmentbrandid',
            'equipmentbrands.name AS equipmentbrandname',
            'equipmentmodels.maxsimcards',
            'equipmentmodels.simcardtypeid AS slottypeid',
            'slottype.name AS slottypename',
            'equipments.serialnumber',
            'equipments.imei',
            'equipments.ownershiptypeid',
            'ownershiptypes.name AS ownershiptypename',
            'equipments.supplierid',
            'supplier.name AS suppliername',
            'equipments.subsidiaryid',
            'subsidiary.name AS subsidiaryname',
            'equipments.storagelocation',
            'equipments.vehicleid',
            'equipments.installedat',
            'equipments.installationsite',
            'createduser.name AS createdbyusername',
            'updateduser.name AS updatedbyusername',
            $this->DB->raw('(equipments.storageLocation = \'Installed\') AS attached')
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

      // Precisamos recuperar a informação dos SIM Cards associados
      // com este equipamento
      $sql = "SELECT SLOT.equipmentID,
                     SLOT.slotnumber AS number,
                     SLOT.simcardID,
                     SLOT.iccid,
                     SLOT.phonenumber,
                     SLOT.mobileOperatorName
                FROM erp.getSlotData({$contractor->id}, {$equipment['id']}) AS SLOT;"
      ;
      $slots = (array) $this->DB->select($sql);

      // Agora recupera as informações do veículo no qual está instalado
      $vehicle = [];
      if ($equipment['storagelocation'] === 'Installed') {
        // Recupera as informações do veículo onde se está instalando o
        // equipamento
        $vehicleID = $equipment['vehicleid'];
        $vehicle = Vehicle::join('vehicletypes', 'vehicles.vehicletypeid',
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
          ->where('vehicles.contractorid', '=', $contractor->id)
          ->get([
              'vehicles.vehicleid',
              'vehicles.plate',
              'vehicles.vehicletypeid',
              'vehicletypes.name AS vehicletypename',
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
              'vehicles.vehiclebrandid',
              'vehiclebrands.name AS vehiclebrandname',
              'vehicles.vehiclemodelid',
              'vehiclemodels.name AS vehiclemodelname',
              'vehicles.vehiclecolorid',
              'vehiclecolors.name AS vehiclecolorname',
              'vehicles.carnumber',
              'vehicles.renavam',
              'vehicles.vin'
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

      // Renderiza a página para poder converter em PDF
      $title = "Dados cadastrais de equipamento";
      $PDFFileName = "Equipment_SerialNumber_{$equipment['serialnumber']}.pdf";
      $page = $this->renderPDF('erp/devices/equipments/PDFequipment.twig',
        [ 'equipment' => $equipment,
          'slots' => $slots,
          'vehicle' => $vehicle ])
      ;
    } else {
      if ($subsidiaryID === 'any') {
        // Recuperamos a relação dos equipamentos deste fornecedor
        // independente da unidade/filial
        $subsidiaryID = 0;
      }

      $sql = "SELECT E.equipmentid AS id,
                     E.supplierid,
                     E.suppliername,
                     E.supplierblocked,
                     E.juridicalperson,
                     E.subsidiaryid,
                     E.subsidiaryname,
                     E.subsidiaryblocked,
                     E.serialnumber,
                     E.imei,
                     E.equipmentmodelid,
                     E.equipmentmodelname,
                     E.equipmentbrandid,
                     E.equipmentbrandname,
                     E.maxsimcards,
                     E.assetnumber,
                     E.attached,
                     E.equipmentblocked,
                     E.blockedlevel,
                     E.createdat,
                     E.updatedat,
                     E.fullcount
                FROM erp.getEquipmentsData({$contractorID}, {$supplierID},
                     {$subsidiaryID}, 0, null, 'Any', 0, 'Any', 0,
                     'subsidiary.name, equipments.serialnumber', 0, 0) AS E;"
      ;
      $equipments = $this->DB->select($sql);
      
      // Para cada equipamento, recupera os dados dos SimCards
      foreach ($equipments as $number => $equipment) {
        // Precisamos recuperar a informação dos SIM Cards associados
        // com este equipamento
        $sql = "SELECT SLOT.equipmentID,
                       SLOT.slotnumber AS number,
                       SLOT.simcardID,
                       SLOT.iccid,
                       SLOT.phonenumber,
                       SLOT.mobileOperatorName
                  FROM erp.getSlotData({$contractor->id}, {$equipment->id}) AS SLOT;"
        ;
        $simCardPerSlot = (array) $this->DB->select($sql);

        // Contamos quantos SIM Cards estão associados nos Slots
        $amountOfSimcards = 0;
        foreach ($simCardPerSlot as $slot) {
          if ($slot->simcardid > 0) {
            $amountOfSimcards++;
          }
        }

        $equipments[$number]->simcarddata = $simCardPerSlot;
        $equipments[$number]->amountofsimcards = $amountOfSimcards;
      }

      // Renderiza a página para poder converter em PDF
      $page = $this->renderPDF('erp/devices/equipments/PDFequipments.twig',
        [ 'equipments' => $equipments ]
      );
      $title = "Relação dos equipamentos de fornecedor";
      $PDFFileName = "EquipmentsOfSupplier{$supplierID}.pdf";
    }

    // Renderiza as partes comuns do PDF
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
    $mpdf->SetSubject('Controle de equipamentos de rastreamento');
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
    $pdfData = ob_get_contents();
    ob_end_clean();
    fwrite($stream, $pdfData);
    rewind($stream);

    // Registra o acesso
    if (array_key_exists('equipmentID', $args)) {
      $this->info("Acesso ao PDF com as informações cadastrais do "
        . "equipamento nº de série '{serialnumber}'.",
        [
          'serialnumber' => $equipment['serialnumber']
        ]
      );
    } else {
      if ($subsidiaryID === 0) {
        $this->info("Acesso ao PDF com a relação de equipamentos do "
          . "fornecedor '{supplierName}'.", 
          [
            'supplierName' => $equipments[0]->suppliername
          ]
        );
      } else {
        $this->info("Acesso ao PDF com a relação de equipamentos da "
          . "unidade '{subsidiaryName}' do fornecedor "
          . "'{supplierName}'.", 
          [
            'subsidiaryName' => $equipments[0]->subsidiaryname,
            'supplierName' => $equipments[0]->suppliername
          ]
        );
      }
    }

    return $response
      ->withBody(new Stream($stream))
      ->withHeader('Content-Type', 'application/pdf')
      ->withHeader('Content-Description', 'File Transfer')
      ->withHeader('Cache-Control', 'no-store, no-cache, '
          . 'must-revalidate'
        )
      ->withHeader('Expires', 'Sun, 1 Jan 2000 12:00:00 GMT')
      ->withHeader('Last-Modified', gmdate('D, d M Y H:i:s') . 'GMT')
    ;
  }

  /**
   * Recupera a relação dos equipamentos em formato JSON no padrão dos
   * campos de preenchimento automático.
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
  public function getAutocompletionData(
    Request $request,
    Response $response
  ): Response
  {
    $this->debug("Relação de equipamentos para preenchimento "
      . "automático despachada."
    );

    // --------------------------[ Recupera os dados requisitados ]-----

    // Recupera os dados da requisição
    $postParams = $request->getParsedBody();

    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();

    // Lida com as informações provenientes do searchbox
    $name   = addslashes($postParams['searchTerm']);
    $mode   = $postParams['mode'];

    // Determina os limites e parâmetros da consulta
    $start  = 0;
    $length = $postParams['limit'];
    $ORDER  = 'serialnumber ASC';
    
    // Registra o acesso
    $this->debug("Acesso aos dados de preenchimento automático dos "
      . "equipamentos cujo número de série contenha '{name}'",
      [
        'name' => $name
      ]
    );
    
    try
    {
      $message = "Equipamentos cujo número de série contém '{$name}'";
      if ($mode === "toSimCard") {
        // Localiza os equipamentos na base de dados que estejam ativos
        // (não possuam bloqueios) para uso na vinculação com SIM Cards
        $equipments = Equipment::join("equipmentmodels",
              "equipments.equipmentmodelid", '=',
              "equipmentmodels.equipmentmodelid"
            )
          ->join("equipmentbrands", "equipmentmodels.equipmentbrandid",
              '=', "equipmentbrands.equipmentbrandid"
            )
          ->join("simcardtypes", "equipmentmodels.simcardtypeid",
              '=', "simcardtypes.simcardtypeid"
            )
          ->join("entities AS supplier", "equipments.supplierid",
              '=', "supplier.entityid"
            )
          ->join("subsidiaries AS subsidiary", "equipments.subsidiaryid",
              '=', "subsidiary.subsidiaryid"
            )
          ->whereRaw("((equipments.contractorid = {$contractor->id} AND equipments.assignedtoid IS NULL) OR (equipments.assignedtoid = {$contractor->id}))")
          ->whereRaw("public.unaccented(equipments.serialnumber) "
              . "ILIKE public.unaccented(E'%{$name}%')"
            )
          ->where("equipments.blocked", "false")
          ->where("supplier.blocked", "false")
          ->where("subsidiary.blocked", "false")
          ->skip($start)
          ->take($length)
          ->orderByRaw($ORDER)
          ->get([
              'equipments.equipmentid AS id',
              'equipments.serialnumber AS name',
              'equipments.imei',
              'equipments.equipmentmodelid',
              'equipmentmodels.name AS equipmentmodelname',
              'equipmentbrands.equipmentbrandid AS equipmentbrandid',
              'equipmentbrands.name AS equipmentbrandname',
              'equipmentmodels.maxsimcards',
              'equipmentmodels.simcardtypeid',
              $this->DB->raw("getStorageLocation(equipments.storagelocation, "
                . "equipments.depositid, 'Equipment', equipments.vehicleid) AS storedlocationname"
              ),
              'simcardtypes.name AS simcardtypename'
            ])
        ;

        // Para cada equipamento, recupera os dados dos SimCards
        foreach ($equipments as $number => $equipment) {
          // Precisamos recuperar a informação dos SIM Cards associados
          // com este equipamento
          $sql = "SELECT SLOT.equipmentID,
                         SLOT.slotnumber AS number,
                         SLOT.simcardID
                    FROM erp.getSlotData({$contractor->id}, {$equipment->id}) AS SLOT;"
          ;
          $simCardPerSlot = (array) $this->DB->select($sql);

          $equipments[$number]->simcarddata = $simCardPerSlot;
        }
      } else {
        // Localiza os equipamentos na base de dados que estejam ativos
        // (não possuam bloqueios) e que não estejam vinculados para uso
        // na vinculação com veículos ou não estejam em comodado para
        // outro cliente
        $equipments = Equipment::join("equipmentmodels",
              "equipments.equipmentmodelid", '=',
              "equipmentmodels.equipmentmodelid"
            )
          ->join("equipmentbrands", "equipmentmodels.equipmentbrandid",
              '=', "equipmentbrands.equipmentbrandid"
            )
          ->join("entities AS supplier", "equipments.supplierid",
              '=', "supplier.entityid"
            )
          ->join("subsidiaries AS subsidiary", "equipments.subsidiaryid",
              '=', "subsidiary.subsidiaryid"
            )
          ->whereRaw("((equipments.contractorid = {$contractor->id} AND equipments.assignedtoid IS NULL) OR (equipments.assignedtoid = {$contractor->id}))")
          ->whereRaw("public.unaccented(equipments.serialnumber) "
              . "ILIKE public.unaccented(E'%{$name}%')"
            )
          ->where("equipments.blocked", "false")
          ->where("supplier.blocked", "false")
          ->where("subsidiary.blocked", "false")
          ->skip($start)
          ->take($length)
          ->orderByRaw($ORDER)
          ->get([
              'equipments.equipmentid AS id',
              'equipments.serialnumber AS name',
              'equipments.imei',
              'equipments.equipmentmodelid',
              $this->DB->raw("equipments.storageLocation = 'Installed' AS attached"),
              'equipmentmodels.name AS equipmentmodelname',
              'equipmentbrands.equipmentbrandid AS equipmentbrandid',
              'equipmentbrands.name AS equipmentbrandname',
              $this->DB->raw("getStorageLocation(equipments.storagelocation, "
                . "equipments.depositid, 'Equipment', equipments.vehicleid) AS storedlocationname"
              )
            ])
        ;
      }
      
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getQueryParams(),
            'message' => $message,
            'data' => $equipments
          ])
      ;
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module} para preenchimento automático. Erro interno no "
        . "banco de dados: {error}.",
        [
          'module' => 'equipamentos',
          'error'  => $exception->getMessage()
        ]
      );

      $error = "Não foi possível recuperar as informações de "
        . "equipamentos para preenchimento automático. Erro interno "
        . "no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module} para preenchimento automático. Erro interno: "
        . "{error}.",
        [
          'module' => 'equipamentos',
          'error'  => $exception->getMessage()
        ]
      );

      $error = "Não foi possível recuperar as informações de "
        . "equipamentos para preenchimento automático. Erro interno."
      ;
    }
    
    // Retorna o erro
    return $response
      ->withHeader('Content-type', 'application/json')
      ->withJson([
          'result' => 'NOK',
          'params' => $request->getQueryParams(),
          'message' => $error,
          'data' => []
        ])
    ;
  }

  /**
   * Exibe a página de histórico de movimentações em um equipamento.
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
  public function showHistory(
    Request $request,
    Response $response,
    array $args
  ): Response
  {
    // Recupera o ID do SIM Card
    $equipmentID = $args['equipmentID'];

    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ERP\Home')
    );
    $this->breadcrumb->push('Dispositivos', '');
    $this->breadcrumb->push('Equipamentos',
      $this->path('ERP\Devices\Equipments')
    );
    $this->breadcrumb->push('Histórico',
      $this->path('ERP\Devices\Equipments\History', [
        'equipmentID' => $equipmentID
      ])
    );

    // Registra o acesso
    $this->info("Acesso à visualização do histórico de movimentações "
      . "de um equipamento."
    );

    // Recupera os dados da sessão
    $history = $this->session->get('history',
      [ 'period' => 0 ])
    ;
    $history['equipmentID'] = $equipmentID;

    // Renderiza a página
    return $this->render($request, $response,
      'erp/devices/equipments/history.twig',
      [ 'history' => $history ])
    ;
  }

  /**
   * Recupera a relação do histórico de movimentações do equipamento em
   * formato JSON.
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
  public function getHistory(
    Request $request,
    Response $response
  ): Response
  {
    $this->debug("Acesso à relação do histórico de movimentações de um "
      . "equipamento."
    );

    // --------------------------[ Recupera os dados requisitados ]-----

    // Recupera os dados da requisição
    $postParams = $request->getParsedBody();

    // Lida com as informações provenientes do Datatables

    // O número da requisição sequencial
    $draw = $postParams['draw'];

    // A posição inicial (0 = início) da paginação
    $start = $postParams['start'];

    // O comprimento de cada página
    $length = $postParams['length'];

    // Lida com as informações adicionais de filtragem

    // O período e o SIM Card que estamos consultando
    $equipmentID = $postParams['equipmentID'];
    $period = $postParams['period'];

    // Seta os valores da última pesquisa na sessão
    $history = $this->session->set('history',
      [ 'period' => $period ])
    ;
    
    try
    {
      // Monta a consulta
      $contractorID = $this->authorization->getContractor()->id;
      $sql = "SELECT log.logid AS id,
                     log.performedat,
                     log.operation,
                     log.description,
                     log.stateid,
                     log.statename,
                     log.performedbyuserid,
                     log.performedbyusername,
                     log.fullcount
                FROM erp.getHistoryData({$contractorID}, 'Equipment',
                       '{$equipmentID}', '{$period}', {$start},
                       {$length}) AS log;"
      ;
      $history = $this->DB->select($sql);
      
      if (count($history) > 0) {
        $rowCount = $history[0]->fullcount;

        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'draw' => $draw,
              'recordsTotal' => $rowCount,
              'recordsFiltered' => $rowCount,
              'data' => $history
            ])
        ;
      } else {
        $error = "Não temos histórico de movimentos para o equipamento "
          . "cadastrados" . (($period = 0)?".":" no período indicado.")
        ;
      }
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno no banco de dados: {error}",
        [
          'module' => 'histórico de movimentos de um equipamento',
          'error'  => $exception->getMessage()
        ]
      );

      $error = "Não foi possível recuperar as informações de histórico "
        . "de movimentos de um equipamento. Erro interno no banco de "
        . "dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno: {error}",
        [
          'module' => 'histórico de movimentos de um equipamento',
          'error'  => $exception->getMessage()
        ]
      );

      $error = "Não foi possível recuperar as informações de histórico "
        . "de movimentos de um equipamento. Erro interno."
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
}
