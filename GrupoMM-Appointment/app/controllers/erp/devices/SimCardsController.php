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
 * O controlador do gerenciamento dos SIM Cards do sistema. Um SIM Card
 * é uma entidade que pode estar contida em um rastreador. Ela associa
 * uma linha telefônica a um equipamento e permite a comunicação por
 * GPRS no mesmo. Podem existir mais de uma linha por equipamento.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Controllers\ERP\Devices;

use App\Models\Deposit;
use App\Models\Entity;
use App\Models\LeasedSimcard;
use App\Models\MobileOperator;
use App\Models\OwnershipType;
use App\Models\SimCard;
use App\Models\SimCardType;
use App\Models\User;
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

class SimCardsController
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
      'simcardid' => V::notBlank()
        ->intVal()
        ->setName('ID do SIM Card'),
      'iccid' => V::notEmpty()
        ->length(19, 20)
        ->iccid()
        ->setName('Nº de série'),
      'imsi' => V::optional(
          V::notEmpty()
            ->length(15, null)
          )
        ->setName('IMSI'),
      'phonenumber' => V::optional(
          V::notEmpty()
            ->length(14, 20)
          )
        ->setName('Telefone'),
      'mobileoperatorid' => V::optional(
            V::notBlank()
              ->intVal()
          )
        ->setName('Operadora de telefonia'),
      'simcardtypeid' => V::notEmpty()
        ->intVal()
        ->setName('Modelo de SIM Card'),
      'pincode' => V::optional(
          V::notEmpty()
            ->length(4, null)
          )
        ->setName('Código PIN'),
      'pukcode' => V::optional(
          V::notEmpty()
            ->length(8, null)
          )
        ->setName('Código PUK'),
      'storedlocationname' => V::optional(
            V::notEmpty()
          )
        ->setName('Situação atual'),
      'ownershiptypeid' => V::intVal()
        ->setName('Tipo de propriedade'),
      'suppliername' => V::optional(
            V::notEmpty()
              ->length(2, 100)
          )
        ->setName('Nome do fornecedor'),
      'supplierid' => V::optional(
            V::intVal()
          )
        ->setName('ID do fornecedor'),
      'subsidiaryname' => V::optional(
            V::notEmpty()
              ->length(2, 50)
          )
        ->setName('Unidade/Filial'),
      'subsidiaryid' => V::optional(
            V::intVal()
          )
        ->setName('ID da unidade/filial'),
      'assetnumber' => V::optional(
          V::notBlank()
            ->length(1, 20)
          )
        ->setName('Nº de patrimônio'),
      'blocked' => V::boolVal()
        ->setName('Bloquear este SIM Card para uso no sistema'),
      'leasedingsimcard' => V::boolVal()
        ->setName('Realizar comodato deste simcard'),
      'leasedsimcard' => [
        'leasedsimcardid' => V::intVal()
          ->setName('ID do comodato do simcard'),
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
      unset($validationRules['simcardid']);
      $validationRules['depositid'] = V::intVal()
        ->setName('Local de armazenamento')
      ;
      unset($validationRules['leasedingsimcard']);
      unset($validationRules['leasedsimcard']);
      unset($validationRules['blocked']);
    } else {
      // Ajusta as regras para edição
      $validationRules['blocked'] = V::boolVal()
        ->setName('Inativar este equipamento')
      ;
      $validationRules['storagelocation'] = V::notBlank()
        ->setName('Local de armazenamento')
      ;
    }

    return $validationRules;
  }

  /**
   * Recupera as informações de operadoras de telefonia móvel.
   * 
   * @return Collection
   *   A coleção de operadoras de telefonia móvel
   * 
   * @throws RuntimeException
   *   Se não for possível recuperar as informações
   */
  protected function getMobileOperators(): Collection
  {
    try {
      // Recupera as informações de operadoras de telefonia
      $mobileOperators = MobileOperator::orderBy('name')
        ->get([
            'mobileoperatorid AS id',
            'name',
            'logo'
          ])
      ;

      if ( $mobileOperators->isEmpty() ) {
        throw new Exception("Não temos nenhuma operadora de telefonia "
          . "móvel cadastrada"
        );
      }
    }
    catch (Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível obter as informações de "
        . "operadoras de telefonia móvel. Erro: {error}.",
        [
          'error' => $exception->getMessage()
        ]
      );

      throw new RuntimeException("Não foi possível obter os tipos de "
        . "SIM Cards"
      );
    }

    return $mobileOperators;
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
        [ 'error' => $exception->getMessage() ]
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
        ->whereRaw("devicetype IN ('SimCard', 'Both')")
        ->orderBy('name')
        ->get([
            'depositid AS id',
            'name',
            'master'
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
   * Exibe a página inicial do gerenciamento de SIM Cards.
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
    $this->breadcrumb->push('SIM Cards',
      $this->path('ERP\Devices\SimCards')
    );

    // Registra o acesso
    $this->info("Acesso ao gerenciamento de SIM Cards.");
    
    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();
    
    // Recupera as informações de tipos de SIM Cards
    $simCardTypes = $this->getSimCardTypes();

    // Recupera as informações de operadoras de telefonia móvel
    $mobileOperators = $this->getMobileOperators();

    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();

    // Recupera as informações de depósitos
    $deposits = $this->getDeposits($contractor->id);
    $defaultDepositID = $deposits[0]->id;
    foreach ($deposits as $key => $deposit) {
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
      $serviceProviders = Entity::where("contractorid",
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
      // Recuperamos apenas a empresa ao qual pertence o usuário atual
      $serviceProviders = Entity::where("contractorid",
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
    $simcard = $this->session->get('simcard',
      [ 'searchField' => 'iccID',
        'searchValue' => '',
        'type' => [
          'id' => 0
        ],
        'operator' => [
          'id' => 0
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
      'erp/devices/simcards/simcards.twig',
      [ 'simcard' => $simcard,
        'simcardTypes' => $simCardTypes,
        'mobileOperators' => $mobileOperators,
        'deposits' => $deposits,
        'defaultDepositID' => $defaultDepositID,
        'technicians' => $technicians,
        'serviceProviders' => $serviceProviders ])
    ;
  }

  /**
   * Recupera a relação dos SIM Cards em formato JSON.
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

    $this->debug("Acesso à relação de SIM Cards.");

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
    $order    = $postParams['order'][0];
    $orderBy  = $columns[$order['column']]['name'];
    $orderDir = strtoupper($order['dir']);

    // A posição inicial (0 = início) da paginação
    $start = $postParams['start'];

    // O comprimento de cada página
    $length = $postParams['length'];

    // Lida com as informações adicionais de filtragem

    // O campo de pesquisa selecionado
    $searchField      = $postParams['searchField'];
    $searchValue      = $postParams['searchValue'];
    $simcardTypeID    = $postParams['simcardTypeID'];
    $mobileOperatorID = $postParams['mobileOperatorID'];

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
    $this->session->set('simcard',
      [ 'searchField' => $searchField,
        'searchValue' => $searchValue,
        'type' => [
          'id' => $simcardTypeID
        ],
        'operator' => [
          'id' => $mobileOperatorID
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
      $sql = "SELECT SC.simcardid AS id,
                     SC.supplierid,
                     SC.suppliername,
                     SC.supplierblocked,
                     SC.leasedsimcard,
                     SC.leasedingsimcard,
                     SC.juridicalperson,
                     SC.subsidiaryid,
                     SC.subsidiaryname,
                     SC.subsidiaryblocked,
                     SC.iccid,
                     SC.imsi,
                     SC.phonenumber,
                     SC.mobileoperatorid,
                     SC.mobileoperatorname,
                     SC.mobileoperatorlogo,
                     SC.simcardtypename,
                     SC.assetnumber,
                     SC.attached,
                     SC.slotNumber,
                     SC.serialnumber,
                     SC.imei,
                     SC.simcardblocked,
                     SC.blockedlevel,
                     SC.createdat,
                     SC.updatedat,
                     SC.fullcount
                FROM erp.getSimCardsData({$contractorID}, 0, 0, 0,
                  '{$searchValue}', '{$searchField}', {$simcardTypeID},
                  $mobileOperatorID, '{$storageLocation}', {$storageID},
                  '{$ORDER}', {$start}, {$length}) AS SC;";
      $simcards = $this->DB->select($sql);
      
      if (count($simcards) > 0) {
        $rowCount = $simcards[0]->fullcount;

        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'draw' => $draw,
              'recordsTotal' => $rowCount,
              'recordsFiltered' => $rowCount,
              'data' => $simcards
            ])
        ;
      } else {
        if (empty($searchValue)) {
          $error = "Não temos SIM Cards cadastrados.";
        } else {
          switch ($searchField) {
            case 'iccid':
              $error = "Não temos SIM Cards cadastrados cujo ICCID "
                . "contém <i>{$searchValue}</i>."
              ;

              break;
            case 'imsi':
              $error = "Não temos SIM Cards cadastrados cujo IMSI "
                . "contém <i>{$searchValue}</i>."
              ;

              break;
            case 'phoneNumber':
              $error = "Não temos SIM Cards cadastrados cujo telefone "
                . "contém <i>{$searchValue}</i>."
              ;

              break;
            case 'assetNumber':
              $error = "Não temos SIM Cards cadastrados cujo número de "
                . "patrimônio contém <i>{$searchValue}</i>."
              ;

              break;
            default:
              $error = "Não temos SIM Cards cadastrados que contém "
                . "<i>{$searchValue}</i>."
              ;

              break;
          }
        }
      }
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno no banco de dados: {error}",
        [ 'module' => 'SIM Cards',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de Sim "
        . "Cards. Erro interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno: {error}",
        [ 'module' => 'SIM Cards',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de Sim "
        . "Cards. Erro interno."
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
   * Exibe um formulário para adição de um SIM Card, quando solicitado,
   * e confirma os dados enviados.
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

      // Recupera as informações de operadoras de telefonia
      $mobileOperators = $this->getMobileOperators();

      // Recupera as informações de tipos de SIM Cards
      $simcardTypes = $this->getSimCardTypes();

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
          'routeName' => 'ERP\Devices\SimCards'
        ]
      );

      // Redireciona para a página de gerenciamento de equipamentos
      return $this->redirect($response, 'ERP\Devices\SimCards');
    }

    // Verifica se estamos postando os dados
    if ($request->isPost()) {
      // Os dados estão sendo postados

      // Registra o acesso
      $this->debug("Processando à adição de SIM Card.");

      // Valida os dados
      $this->validator->validate($request,
        $this->getValidationRules(true)
      );

      if ($this->validator->isValid()) {
        $this->debug('Os dados do SIM Card são VÁLIDOS');

        // Recupera os dados do SIM Card
        $simcardData = $this->validator->getValues();

        try
        {
          $allHasValid = true;

          // Verifica se não temos um SIM Card com o mesmo ICCID
          if (SimCard::where("contractorid", '=', $contractor->id)
               ->where("iccid", $simcardData['iccid'])
               ->count() > 0) {
            // Registra o erro
            $this->debug("Não foi possível inserir as informações do "
              . "simcard ICCID '{iccid}'. Já existe outro simcard com "
              . "o mesmo ICCID.",
              [
                'iccid' => $simcardData['iccid']
              ]
            );

            // Alerta o usuário
            $this->flashNow("error", "Já existe um simcard com o "
              . "mesmo ICCID."
            );

            $allHasValid = false;
          }

          // Verifica se foi informado o tipo de propriedade
          if (empty($simcardData['ownershiptypeid'])) {
            // Retira as informações de propriedade
            unset($simcardData['ownershiptypeid']);
            unset($simcardData['supplierid']);
            unset($simcardData['suppliername']);
            unset($simcardData['subsidiaryid']);
            unset($simcardData['subsidiaryname']);
          } else {
            // Verifica se foi informado o fornecedor
            if (empty($simcardData['supplierid'])) {
              // Seta o erro neste campo
              $this->validator->setErrors([
                  'suppliername' =>
                    'O fornecedor deve ser informado.'
                ],
                "suppliername")
              ;

              $allHasValid = false;
            }
          }
          
          if ($allHasValid) {
            // Grava o novo SIM Card

            // Incluímos um novo SIM Card
            $simcard = new SimCard();
            $simcard->fill($simcardData);
            // Adicionamos as informações do contratante
            $simcard->contractorid = $contractor->id;
            $simcard->createdbyuserid =
              $this->authorization->getUser()->userid
            ;
            $simcard->updatedbyuserid =
              $this->authorization->getUser()->userid
            ;
            $simcard->save();

            // Registra o sucesso
            $this->info("Cadastrado o SIM Card ICCID '{iccid}' com "
              . "sucesso.",
              [ 'iccid'  => $simcardData['iccid'] ]
            );

            // Alerta o usuário
            $this->flash("success", "O SIM Card ICCID <i>'{iccid}'</i> "
              . "foi cadastrado com sucesso.",
              [ 'iccid'  => $simcardData['iccid'] ]
            );

            // Registra o evento
            $this->debug("Redirecionando para {routeName}",
              [ 'routeName' => 'ERP\Devices\SimCards' ]
            );

            // Redireciona para a página de gerenciamento de SIM Cards
            return $this->redirect($response, 'ERP\Devices\SimCards');
          } else {
            // Registra o erro
            $this->debug("Não foi possível inserir as informações "
              . "do SIM Card ICCID '{iccid}'. Já existe outro SIM Card "
              . "com o mesmo ICCID (Número de série).",
              [ 'iccid'  => $simcardData['iccid'] ]
            );

            // Alerta o usuário
            $this->flashNow("error", "Já existe um SIM Card com o "
              . "ICCID <i>'{iccid}'</i>.",
              [ 'iccid' => $simcardData['iccid'] ]
            );
          }
        }
        catch(QueryException $exception)
        {
          // Registra o erro
          $this->error("Não foi possível inserir as informações do "
            . "SIM Card ICCID '{iccid}'. Erro interno no banco de "
            . "dados: {error}",
            [ 'iccid'  => $simcardData['iccid'],
              'error' => $exception->getMessage() ]
          );

          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações do SIM Card. Erro interno no banco de dados."
          );
        }
        catch(Exception $exception)
        {
          // Registra o erro
          $this->error("Não foi possível inserir as informações do "
            . "SIM Card ICCID '{iccid}'. Erro interno: {error}",
            [ 'iccid'  => $simcardData['iccid'],
              'error' => $exception->getMessage() ]
          );

          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações do SIM Card. Erro interno."
          );
        }
      } else {
        $this->debug('Os dados do SIM Card são INVÁLIDOS');
        $messages = $this->validator->getFormatedErrors();
        foreach ($messages AS $message) {
          $this->debug($message);
        }
      }
    } else {
      // Carrega os dados iniciais para simplificar a digitação
      $this->validator->setValues([
        'mobileoperatorid' => 0,
        'simcardtypeid' => 1,
        'ownershiptypeid' => 0,
        'supplierid' => 0,
        'subsidiaryid' => 0,
      ]);
    }

    // Exibe um formulário para adição de um SIM Card

    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ERP\Home')
    );
    $this->breadcrumb->push('Dispositivos', '');
    $this->breadcrumb->push('SIM Cards',
      $this->path('ERP\Devices\SimCards')
    );
    $this->breadcrumb->push('Adicionar',
      $this->path('ERP\Devices\SimCards\Add')
    );

    // Registra o acesso
    $this->info("Acesso à adição de SIM Card.");

    return $this->render($request, $response,
      'erp/devices/simcards/simcard.twig',
      [ 'formMethod' => 'POST',
        'mobileOperators' => $mobileOperators,
        'simcardTypes' => $simcardTypes,
        'deposits' => $deposits,
        'ownershipTypes' => $ownershipTypes ])
    ;
  }

  /**
   * Exibe um formulário para edição de um SIM Card, quando solicitado,
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
  public function edit(
    Request $request,
    Response $response,
    array $args
  ): Response
  {
    $simcardID = $args['simcardID'];

    try {
      // Recupera os dados do contratante
      $contractor = $this->authorization->getContractor();

      // Recupera as informações de operadoras de telefonia
      $mobileOperators = $this->getMobileOperators();

      // Recupera as informações de tipos de SIM Cards
      $simcardTypes = $this->getSimCardTypes();

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
          'routeName' => 'ERP\Devices\SimCards'
        ]
      );

      // Redireciona para a página de gerenciamento de equipamentos
      return $this->redirect($response, 'ERP\Devices\SimCards');
    }

    try
    {
      // Recupera as informações do SIM Card
      $simcard = SimCard::join('mobileoperators',
            'simcards.mobileoperatorid', '=',
            'mobileoperators.mobileoperatorid'
          )
        ->join('simcardtypes', 'simcards.simcardtypeid',
            '=', 'simcardtypes.simcardtypeid'
          )
        ->join('entities AS contractor', 'simcards.contractorid',
            '=', 'contractor.entityid'
          )
        ->leftJoin('ownershiptypes', 'simcards.ownershiptypeid',
            '=', 'ownershiptypes.ownershiptypeid'
          )
        ->leftJoin('entities AS supplier', 'simcards.supplierid',
            '=', 'supplier.entityid'
          )
        ->leftJoin('subsidiaries AS subsidiary', 'simcards.subsidiaryid',
            '=', 'subsidiary.subsidiaryid'
          )
        ->join('users AS createduser', 'simcards.createdbyuserid',
            '=', 'createduser.userid'
          )
        ->join('users AS updateduser', 'simcards.updatedbyuserid',
            '=', 'updateduser.userid'
          )
        ->leftJoin('deposits', 'simcards.depositid',
            '=', 'deposits.depositid'
          )
        ->whereRaw("(simcards.contractorid = {$contractor->id} OR simcards.assignedtoid = {$contractor->id})")
        ->where('simcards.simcardid', $simcardID)
        ->get([
            'simcards.simcardid',
            'simcards.contractorid',
            'simcards.assignedtoid',
            'simcards.iccid',
            'simcards.imsi',
            'simcards.phonenumber',
            'simcards.mobileoperatorid',
            'mobileoperators.name AS mobileoperatorname',
            'simcards.simcardtypeid',
            'simcardtypes.name AS simcardtypename',
            'simcards.storagelocation',
            $this->DB->raw("getStorageLocation(simcards.storagelocation, "
              . "simcards.depositid, 'SimCard', simcards.equipmentid) AS storedlocationname"
            ),
            'simcards.technicianid',
            'simcards.serviceproviderid',
            'simcards.depositid',
            'simcards.equipmentid',
            'simcards.slotnumber',
            'simcards.pincode',
            'simcards.pukcode',
            $this->DB->raw(""
              . "CASE "
              .   "WHEN simcards.assignedtoid = {$contractor->id} THEN 2 "
              .   "ELSE simcards.ownershiptypeid "
              . "END AS ownershiptypeid"
            ),
            $this->DB->raw(""
              . "CASE "
              .   "WHEN simcards.assignedtoid = {$contractor->id} THEN 'Comodato' "
              .   "ELSE ownershiptypes.name "
              . "END AS ownershiptypename"
            ),
            $this->DB->raw(""
              . "CASE "
              .   "WHEN simcards.assignedtoid = {$contractor->id} THEN simcards.contractorid "
              .   "ELSE simcards.supplierid "
              . "END AS supplierid"
            ),
            $this->DB->raw(""
              . "CASE "
              .   "WHEN simcards.assignedtoid = {$contractor->id} THEN contractor.name "
              .   "ELSE supplier.name "
              . "END AS suppliername"
            ),
            $this->DB->raw(""
              . "CASE "
              .   "WHEN simcards.assignedtoid = {$contractor->id} THEN 0 "
              .   "ELSE simcards.subsidiaryid "
              . "END AS subsidiaryid"
            ),
            $this->DB->raw(""
              . "CASE "
              .   "WHEN simcards.assignedtoid = {$contractor->id} THEN '' "
              .   "ELSE subsidiary.name "
              . "END AS subsidiaryname"
            ),
            $this->DB->raw(""
              . "CASE "
              .   "WHEN simcards.assignedtoid = {$contractor->id} THEN '' "
              .   "ELSE simcards.assetnumber "
              . "END AS assetnumber"
            ),
            $this->DB->raw(""
              . "CASE "
              .   "WHEN simcards.assignedtoid = {$contractor->id} THEN TRUE "
              .   "ELSE FALSE "
              . "END AS isleasedsimcard"
            ),
            $this->DB->raw(""
              . "CASE "
              .   "WHEN simcards.assignedtoid IS NOT NULL AND simcards.assignedtoid <> {$contractor->id} THEN TRUE "
              .   "ELSE FALSE "
              . "END AS leasedingsimcard"
            ),
            'simcards.blocked',
            'simcards.createdat',
            'simcards.createdbyuserid',
            'createduser.name AS createdbyusername',
            'simcards.updatedat',
            'simcards.updatedbyuserid',
            'updateduser.name AS updatedbyusername'
          ])
      ;

      if ( $simcard->isEmpty() ) {
        throw new ModelNotFoundException("Não temos nenhum SIM Card "
          . "com o código {$simcardID} cadastrado."
        );
      }

      $simcard = $simcard
        ->first()
        ->toArray()
      ;

      // Por razões de compatibilidade, limpa espaços desnecessários
      $simcard['pincode'] = trim($simcard['pincode']);
      $simcard['pukcode'] = trim($simcard['pukcode']);
      $simcard['ownershiptypeid'] = (int) $simcard['ownershiptypeid'];
      $simcard['supplierid'] = (int) $simcard['supplierid'];
      $simcard['subsidiaryid'] = (int) $simcard['subsidiaryid'];

      // Agora recupera as informações do cliente ao qual o SIM Card
      // foi comodatado
      $leasedsimcard = [];
      if ($simcard['leasedingsimcard']) {
        $this->debug("Recuperando dados do comodato");
        // Recupera as informações do contratante para quem o
        // SIM Card foi comodatado
        $assignedToID = $simcard['assignedtoid'];
        $leasedsimcard = LeasedSimcard::join('entities as assigned', 'leasedsimcards.assignedto',
              '=', 'assigned.entityid'
            )
          ->where('leasedsimcards.contractorid', '=', $contractor->id)
          ->where('leasedsimcards.simcardid', '=', $simcardID)
          ->where('leasedsimcards.assignedto', '=', $assignedToID)
          ->whereNull('leasedsimcards.enddate')
          ->get([
              'leasedsimcards.leasedsimcardid',
              'leasedsimcards.assignedto',
              'assigned.name AS assignedtoname',
              'leasedsimcards.startdate',
              'leasedsimcards.graceperiod',
              $this->DB->raw(""
                . "CASE "
                .   "WHEN leasedsimcards.graceperiod > 0 THEN TO_CHAR(leasedsimcards.startdate + leasedsimcards.graceperiod * interval '1 month', 'DD/MM/YYYY') "
                .   "ELSE NULL "
                . "END AS endofgraceperiod"
              ),
              'leasedsimcards.enddate'
            ])
        ;

        if ( $leasedsimcard->isEmpty() ) {
          throw new ModelNotFoundException("Não temos os dados de "
            . "comodato do SIM Card com o código {$simcardID} "
            . "cadastrado."
          );
        }

        $leasedsimcard = $leasedsimcard
          ->first()
          ->toArray()
        ;
      } else {
        // Não temos um comodato para este SIM Card, então colocamos
        // os valores padrão

        // Obtém a data atual
        $today = Carbon::now();
        $currentDate = $today->format('d/m/Y');

        $leasedsimcard = [
          'leasedsimcardid' => 0,
          'assignedto' => 0,
          'assignedtoname' => '',
          'startdate' => $currentDate,
          'graceperiod' => 0,
          'endofgraceperiod' => '',
          'enddate' => ''
        ];
      }
      $simcard['leasedsimcard'][] = $leasedsimcard;
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar o SIM Card código "
        . "{simcardID}.",
        [ 'simcardID' => $simcardID ]
      );

      // Alerta o usuário
      $this->flash("error", "Não foi possível localizar este Sim "
        . "Card."
      );

      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [ 'routeName' => 'ERP\Devices\SimCards' ]
      );

      // Redireciona para a página de gerenciamento de SIM Cards
      return $this->redirect($response, 'ERP\Devices\SimCards');
    }
      
    // Verifica se estamos modificando os dados
    if ($request->isPut()) {
      // Os dados estão sendo modificados

      // Registra o acesso
      $this->debug("Processando à edição do SIM Card ICCID '{iccid}'.",
        [ 'iccid' => $simcard['iccid'] ]
      );

      // Valida os dados
      $this->validator->validate($request, 
        $this->getValidationRules(false)
      );

      if ($this->validator->isValid()) {
        $this->debug('Os dados do SIM Card são VÁLIDOS');

        // Recupera os dados modificados do SIM Card
        $simcardData = $this->validator->getValues();

        try
        {
          $allHasValid = true;

          // Verifica se o ICCID foi modificado
          if ($simcardData['iccid'] !== $simcard['iccid']) {
            // Verifica não temos um SIM Card com o mesmo ICCID,
            // independente do fornecedor ao qual ele está vinculado
            if (SimCard::where("contractorid", '=', $contractor->id)
                  ->where("iccid", $simcardData['iccid'])
                  ->count() > 0) {
              // Registra o erro
              $this->debug("Não foi possível modificar as "
                . "informações do SIM Card do ICCID '{iccid}' para "
                . "'{newiccid}'. Já existe outro SIM Card com o "
                . "mesmo ICCID.",
                [ 'iccid'    => $simcard['iccid'],
                  'newiccid' => $simcardData['iccid'] ]
              );

              // Alerta o usuário
              $this->flashNow("error", "Já existe um SIM Card com "
                . "o ICCID <i>'{iccid}'</i>.",
                [ 'iccid' => $simcardData['iccid'] ]
              );

              $allHasValid = false;
            }
          }

          // Verifica se foi informado o tipo de propriedade
          if (empty($simcardData['ownershiptypeid'])) {
            // Retira as informações de propriedade
            unset($simcardData['ownershiptypeid']);
            unset($simcardData['supplierid']);
            unset($simcardData['suppliername']);
            unset($simcardData['subsidiaryid']);
            unset($simcardData['subsidiaryname']);
          } else {
            // Verifica se foi informado o fornecedor
            if (empty($simcardData['supplierid'])) {
              // Seta o erro neste campo
              $this->validator->setErrors([
                  'suppliername' =>
                    'O fornecedor deve ser informado.'
                ],
                "suppliername")
              ;

              $allHasValid = false;
            }
          }

          // Verifica se o SIM Card está sendo colocado em comodato
          $leasedingsimcard = filter_var(
            $simcardData['leasedingsimcard'],
            FILTER_VALIDATE_BOOLEAN
          );
          if ($leasedingsimcard === true) {
            $this->debug(
              "Verifica os parâmetros quando o SIM Card está sendo colocado em comodato."
            );

            $assignedToID = intval($simcardData['leasedsimcard'][0]['assignedto']);

            if ($assignedToID > 0) {
              // Verifica se o SIM Card está sendo colocado em comodato
              // para o mesmo contratante
              if ($assignedToID === $contractor->id) {
                // Seta o erro neste campo
                $this->validator->setErrors([
                    'leasedsimcard[0][assignedtoname]' =>
                      'Você não pode comodatar um SIM Card para si '
                      . 'mesmo.'
                  ],
                  "leasedsimcard[0][assignedtoname]"
                );
                $allHasValid = false;
              }
            } else {
              // Seta o erro neste campo
              $this->validator->setErrors([
                  'leasedsimcard[0][assignedtoname]' =>
                    'Informe o nome do comodatário.'
                ],
                "leasedsimcard[0][assignedtoname]"
              );
              $allHasValid = false;
            }

            if ($simcard['leasedingsimcard'] === false) {
              // O SIM Card não estava em comodato, e estamos o
              // colocando neste momento, então verifica se a data de
              // término foi informada
              if (!empty($simcardData['leasedsimcard'][0]['enddate'])) {
                // Seta o erro neste campo
                $this->validator->setErrors([
                    'leasedsimcard[0][enddate]' =>
                      'A data de término do comodato somente deve ser '
                      . 'informada se o SIM Card estiver em comodato.'
                  ],
                  "leasedsimcard[0][enddate]"
                );
                $allHasValid = false;
              }
            }
          }

          if ($allHasValid) {
            // Grava as modificações dos dados do SIM Card

            // Retira os campos não necessários
            unset($simcardData['suppliername']);
            unset($simcardData['subsidiaryname']);
            unset($simcardData['createdat']);
            unset($simcardData['createdbyuserid']);
            unset($simcardData['createdbyusername']);
            unset($simcardData['updatedat']);
            unset($simcardData['updatedbyuserid']);
            unset($simcardData['updatedbyusername']);
            
            // Retira as informações do comodato
            $beforeLeasedSimcard = filter_var($simcard['leasedingsimcard'], FILTER_VALIDATE_BOOLEAN);
            $leasedSimcardData = array_key_exists('leasedsimcard', $simcardData)
              ? $simcardData['leasedsimcard'][0]
              : null
            ;
            if ($leasedSimcardData) {
              // Verifica se a data de término do comodato foi informada
              if (empty($leasedSimcardData['enddate'])) {
                // Retira a data de término do comodato
                unset($leasedSimcardData['enddate']);
              } else {
                // Força a indicação de que estamos encerrando o
                // comodato do SIM Card
                $leasedingsimcard = false;
              }
            }
            unset($simcardData['leasedsimcard']);

            // Iniciamos a transação
            $this->info("Iniciamos a transação");
            $this->DB->beginTransaction();

            // Obtemos o ID do usuário que está realizando a modificação
            $userID = $this->authorization->getUser()->userid;

            if ($beforeLeasedSimcard !== $leasedingsimcard) {
              // Houve uma mudança no estado de comodato, então
              // determina para qual depósito de contratante este
              // SIM Card será transferido
              $this->debug("Houve uma mudança no estado de comodato, "
                . "então determina para qual depósito de contratante "
                . "este SIM Card será transferido"
              );
              $depositContractorID = $beforeLeasedSimcard == false
                ? $leasedSimcardData['assignedto']
                : $contractor->id
              ;

              // Recupera as informações de depósitos
              $deposits = Deposit::where("contractorid", '=', $depositContractorID)
                ->whereRaw("devicetype IN ('SimCard', 'Both')")
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
              // SIM Card estará armazenado
              $simcardData['storagelocation'] = 'StoredOnDeposit';
              $simcardData['depositid'] = $depositID;
            }

            // Verifica se precisa desvincular o SIM Card do equipamento
            if ($simcard['storagelocation'] === 'Installed') {
              if ($beforeLeasedSimcard !== $leasedingsimcard) {
                // Houve uma mudança no estado de comodato, então força
                // a desvinculação do SIM Card do equipamento
                $simcardData['equipmentid'] = null;
                $simcardData['slotNumber'] = null;

                // Não precisamos colocar os dados do depósito pois
                // estes já foram incluídos acima
              }
            }

            // Verifica se o SIM Card está sendo colocado em comodato
            if ($leasedingsimcard) {
              $this->debug("O SIM Card está sendo colocado em comodato");
              // Verifica se o SIM Card estava em comodato
              if ($beforeLeasedSimcard === false) {
                // O SIM Card não estava em comodato, então incluímos
                // as informações de comodato
                $leasedSimcardData['simcardid'] = $simcardID;
                $leasedSimcardData['contractorid'] = $contractor->id;

                // Retira o ID do comodato
                unset($leasedSimcardData['leasedsimcardid']);

                // Incluímos as informações de comodato
                $leasedSimcard = new LeasedSimcard();
                $leasedSimcard->fill($leasedSimcardData);
                $leasedSimcard->save();
              } else {
                // O SIM Card já estava em comodato, então apenas
                // atualizamos as informações de comodato
                $leasedSimcardID = $leasedSimcardData['leasedsimcardid'];
                $leasedSimcard = LeasedSimcard::findOrFail($leasedSimcardID);
                $leasedSimcard->fill($leasedSimcardData);
                $leasedSimcard->save();
              }
            } else {
              if ($beforeLeasedSimcard) {
                // Encerramos o comodato do SIM Card
                if ($leasedSimcardData) {
                  // Temos os dados do comodato, então utilizamos as
                  // informações de término do comodato
                  $leasedSimcardID = $leasedSimcardData['leasedsimcardid'];
                  $endDate = $leasedSimcardData['enddate'];
                  if (empty($endDate)) {
                    $endDate = Carbon::now();
                  } else {
                    $endDate = Carbon::createFromFormat('d/m/Y', $endDate);
                  }
    
                  $leasedSimcard = LeasedSimcard::findOrFail($leasedSimcardID);
                  $leasedSimcard->enddate = $endDate;
                  $leasedSimcard->save();
                } else {
                  // Não temos os dados do comodato, então localizamos
                  // ele para encerrar

                  // Localiza o comodato do SIM Card
                  $leasedSimcard = LeasedSimcard::where('simcardid', $simcardID)
                    ->where('contractorid', $contractor->id)
                    ->where('simcardid', $simcardID)
                    ->where('assignedto', $simcard['assignedtoid'])
                    ->whereNull('enddate')
                    ->first()
                  ;

                  // Utiliza a data atual
                  $endDate = Carbon::now();

                  if ($leasedSimcard) {
                    $leasedSimcard->update([
                        'enddate' => $endDate
                      ])
                    ;
                  }
                }
              }
            }

            // Modificamos os dados do SIM Card
            $simcardChanged = SimCard::findOrFail($simcardID);
            $simcardChanged->fill($simcardData);
            if (!isset($simcardData['ownershiptypeid'])) {
              // Retira as informações de propriedade
              $simcardChanged->ownershiptypeid = null;
              $simcardChanged->supplierid = null;
              $simcardChanged->subsidiaryid = null;
            }

            // Adicionamos as informações do responsável pela modificação
            $simcardChanged->updatedbyuserid = $userID;
            $simcardChanged->save();

            // Efetiva a transação
            $this->DB->commit();

            // Registra o sucesso
            $this->info("O SIM Card '{iccid}' foi modificado com "
              . "sucesso.",
              [ 'iccid' => $simcardData['iccid'] ]
            );

            // Alerta o usuário
            $this->flash("success", "O SIM Card ICCID "
              . "<i>'{iccid}'</i> foi modificado com sucesso.",
              [ 'iccid' => $simcardData['iccid'] ]
            );

            // Registra o evento
            $this->debug("Redirecionando para {routeName}",
              [ 'routeName' => 'ERP\Devices\SimCards' ]
            );

            // Redireciona para a página de gerenciamento de SIM Cards
            return $this->redirect($response, 'ERP\Devices\SimCards');
          } else {
            $this->debug('Os dados do SIM Card são INVÁLIDOS');
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
          $this->error("Não foi possível modificar as informações "
            . "do SIM Card ICCID '{iccid}'. Erro interno no banco de "
            . "dados: {error}",
            [ 'iccid'  => $simcardData['iccid'],
              'error' => $exception->getMessage() ]
          );

          // Alerta o usuário
          $this->flashNow("error", "Não foi possível modificar as "
            . "informações do SIM Card. Erro interno no banco de "
            . "dados."
          );
        }
        catch(Exception $exception)
        {
          // Reverte (desfaz) a transação
          $this->DB->rollBack();

          // Registra o erro
          $this->error("Não foi possível modificar as informações "
            . "do SIM Card ICCID '{iccid}'. Erro interno: {error}",
            [ 'iccid'  => $simcardData['iccid'],
              'error' => $exception->getMessage() ]
          );

          // Alerta o usuário
          $this->flashNow("error", "Não foi possível modificar as "
            . "informações do SIM Card. Erro interno."
          );
        }
      } else {
        $this->debug('Os dados do SIM Card são INVÁLIDOS');
        $messages = $this->validator->getFormatedErrors();
        foreach ($messages AS $message) {
          $this->debug($message);
        }
      }
    } else {
      // Carrega os dados atuais
      $this->validator->setValues($simcard);
    }

    // Exibe um formulário para edição de um SIM Card

    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ERP\Home')
    );
    $this->breadcrumb->push('Dispositivos', '');
    $this->breadcrumb->push('SIM Cards',
      $this->path('ERP\Devices\SimCards')
    );
    $this->breadcrumb->push('Editar',
      $this->path('ERP\Devices\SimCards\Edit', [
        'simcardID' => $simcardID
      ])
    );

    // Registra o acesso
    $this->info("Acesso à edição do SIM Card ICCID '{iccid}'.",
      [ 'iccid' => $simcard['iccid'] ]
    );

    // Renderiza a página
    return $this->render($request, $response,
      'erp/devices/simcards/simcard.twig',
      [ 'formMethod' => 'PUT',
        'mobileOperators' => $mobileOperators,
        'simcardTypes' => $simcardTypes,
        'ownershipTypes' => $ownershipTypes ])
    ;
  }

  /**
   * Remove o SIM Card.
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
    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();

    // Registra o acesso
    $this->debug("Processando à remoção de SIM Card.");

    // Recupera o ID
    $simcardID = $args['simcardID'];

    try
    {
      // Recupera as informações do SIM Card
      $simcard = SimCard::findOrFail($simcardID);

      // Iniciamos a transação
      $this->DB->beginTransaction();

      // Apaga o SIM Card
      $simcard->deleteCascade();

      // Efetiva a transação
      $this->DB->commit();

      // Registra o sucesso
      $this->info("O SIM Card ICCID '{iccid}' foi removido com "
        . "sucesso.",
        [ 'iccid' => $simcard->iccid ]
      );

      // Informa que a remoção foi realizada com sucesso
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getParams(),
            'message' => "Removido o SIM Card ICCID {$simcard->iccid}",
            'data' => "Delete"
          ])
      ;
    }
    catch(ModelNotFoundException $exception)
    {
      // Reverte (desfaz) a transação
      $this->DB->rollBack();

      // Registra o erro
      $this->error("Não foi possível localizar o SIM Card código "
        . "{simcardID} para remoção.",
        [ 'simcardID' => $simcardID ]
      );

      $message = "Não foi possível localizar o SIM Card para remoção.";
    }
    catch(QueryException $exception)
    {
      // Reverte (desfaz) a transação
      $this->DB->rollBack();

      // Registra o erro
      $this->error("Não foi possível remover as informações do Sim "
        . "Card ID {simcardID}. Erro interno no banco de dados: "
        . "{error}",
        [ 'simcardID' => $simcardID,
          'error' => $exception->getMessage() ]
      );

      $message = "Não foi possível remover o SIM Card. Erro interno "
        . "no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Reverte (desfaz) a transação
      $this->DB->rollBack();

      // Registra o erro
      $this->error("Não foi possível remover as informações do Sim "
        . "Card ID {simcardID}. Erro interno: {error}",
        [ 'simcardID' => $simcardID,
          'error' => $exception->getMessage() ]
      );

      $message = "Não foi possível remover o SIM Card. Erro interno.";
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
   * Alterna o estado do bloqueio de um SIM Card e/ou de uma
   * unidade/filial deste SIM Card.
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
      . "SIM Card."
    );

    // Recupera o ID
    $simcardID = $args['simcardID'];

    try
    {
      // Recupera as informações do SIM Card
      // Desbloqueia o SIM Card
      $simcard = SimCard::findOrFail($simcardID);
      $action = $simcard->blocked
        ? "desbloqueado"
        : "bloqueado"
      ;
      $simcard->blocked = !$simcard->blocked;
      $simcard->updatedbyuserid =
        $this->authorization->getUser()->userid
      ;
      $simcard->save();

      $message = "O SIM Card ICCID'{$simcard->iccid}' foi {$action} "
        . "com sucesso."
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
            'data' => "Delete"
          ])
      ;
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar o SIM Card código "
        . "{simcardID} para alternar o estado do bloqueio.",
        [ 'simcardID' => $simcardID ]
      );

      $message = "Não foi possível localizar o SIM Card para alternar "
        . "o estado do bloqueio."
      ;
    }
    catch(QueryException $exception)
    {
      $error_code = $exception->errorInfo[0];
      $this->error($exception->getMessage());

      if ($error_code == 23001) {
        // Informa que não foi possível alterar o estado do bloqueio e
        // indica na mensagem o que ocorreu
        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'result' => 'OK',
              'params' => $request->getParams(),
              'message' => $exception->errorInfo[2],
              'data' => "Delete"
            ])
        ;
      }

      // Registra o erro
      $this->error("Não foi possível alternar o estado do bloqueio "
        . "do SIM Card ICCID '{iccid}'. Erro interno no banco de "
        . "dados: {error}.",
        [ 'iccid'  => $simcard->iccid,
          'error' => $exception->getMessage() ]
      );

      $message = "Não foi possível alternar o estado do bloqueio do "
        . "SIM Card. Erro interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível alternar o estado do bloqueio "
        . "do SIM Card ICCID '{iccid}'. Erro interno: {error}.",
        [ 'iccid'  => $simcard->iccid,
          'error' => $exception->getMessage() ]
      );

      $message = "Não foi possível alternar o estado do bloqueio do "
        . "SIM Card. Erro interno."
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
   * Gera um PDF para impressão das informações de um SIM Card.
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
    if (array_key_exists('simcardID', $args)) {
      $this->debug("Processando à geração de PDF com as informações "
        . "cadastrais de um SIM Card."
      );
    } else {
      $this->debug("Processando à geração de PDF com a relação de Sim "
        . "Cards de um fornecedor."
      );
    }
    
    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();
    
    // Recupera as informações do fornecedor
    $contractorID = $contractor->id;
    $supplierID   = $args['supplierID'];
    $subsidiaryID = $args['subsidiaryID'];
    
    if (array_key_exists('simcardID', $args)) {
      // Recuperamos as informações do SIM Card
      $simcardID = $args['simcardID'];
      $simcard = SimCard::join('mobileoperators',
            'simcards.mobileoperatorid', '=',
            'mobileoperators.mobileoperatorid'
          )
        ->join('simcardtypes', 'simcards.simcardtypeid',
            '=', 'simcardtypes.simcardtypeid'
          )
        ->join('ownershiptypes', 'simcards.ownershiptypeid',
            '=', 'ownershiptypes.ownershiptypeid'
          )
        ->join('entities AS supplier', 'simcards.supplierid',
            '=', 'supplier.entityid'
          )
        ->join('subsidiaries AS subsidiary', 'simcards.subsidiaryid',
            '=', 'subsidiary.subsidiaryid'
          )
        ->join('users AS createduser', 'simcards.createdbyuserid',
            '=', 'createduser.userid'
          )
        ->join('users AS updateduser', 'simcards.updatedbyuserid',
            '=', 'updateduser.userid'
          )
        ->leftJoin('equipments', 'simcards.equipmentid',
            '=', 'equipments.equipmentid'
          )
        ->leftJoin('equipmentmodels', 'equipments.equipmentmodelid',
            '=', 'equipmentmodels.equipmentmodelid'
          )
        ->leftJoin('simcardtypes AS slottype',
            'equipmentmodels.simcardtypeid', '=',
            'slottype.simcardtypeid'
          )
        ->whereRaw("(simcards.contractorid = {$contractor->id} OR simcards.assignedtoid = {$contractor->id})")
        ->where('simcards.simcardid', $simcardID)
        ->get([
            'simcards.*',
            $this->DB->raw('(simcards.storageLocation = \'Installed\') AS attached'),
            'mobileoperators.name AS mobileoperatorname',
            'simcardtypes.name AS simcardtypename',
            'ownershiptypes.name AS ownershiptypename',
            'supplier.name AS suppliername',
            'subsidiary.name AS subsidiaryname',
            'createduser.name AS createdbyusername',
            'updateduser.name AS updatedbyusername',
            'simcards.equipmentid',
            'equipments.serialnumber',
            'equipments.imei',
            'equipments.equipmentmodelid',
            'equipmentmodels.name AS equipmentmodelname',
            'simcards.slotnumber',
            'equipmentmodels.simcardtypeid AS slottypeid',
            'slottype.name AS slottypename'
          ])
        ->toArray()[0]
      ;

      // Renderiza a página para poder converter em PDF
      $title = "Dados cadastrais de SIM Card";
      $PDFFileName = "SimCard_ICCID_{$simcard['iccid']}.pdf";
      $page = $this->renderPDF('erp/devices/simcards/PDFsimcard.twig',
        [ 'simcard' => $simcard ]
      );
    } else {
      if ($subsidiaryID === 'any') {
        // Recuperamos a relação dos SIM Cards deste fornecedor
        // independente da unidade/filial
        $subsidiaryID = 0;
      }

      $sql = "SELECT SC.simcardid AS id,
                     SC.supplierid,
                     SC.suppliername,
                     SC.supplierblocked,
                     SC.juridicalperson,
                     SC.subsidiaryid,
                     SC.subsidiaryname,
                     SC.subsidiaryblocked,
                     SC.iccid,
                     SC.imsi,
                     SC.phonenumber,
                     SC.mobileoperatorid,
                     SC.mobileoperatorname,
                     SC.mobileoperatorlogo,
                     SC.simcardtypename,
                     SC.assetnumber,
                     SC.simcardblocked,
                     SC.blockedlevel,
                     SC.createdat,
                     SC.updatedat,
                     SC.fullcount
                FROM erp.getSimCardsData({$contractorID}, {$supplierID},
                  {$subsidiaryID}, 0, '', 'iccid', 0, 0, '', 0,
                  'subsidiary.name, simcards.iccid', 0, 0) AS SC;"
      ;
      $simcards = (array) $this->DB->select($sql);

      // Renderiza a página para poder converter em PDF
      $page = $this->renderPDF('erp/devices/simcards/PDFsimcards.twig',
        [ 'simcards' => $simcards ])
      ;
      $title = "Relação de SIM Cards de fornecedor";
      $PDFFileName = "SimCardsOfSupplier{$supplierID}.pdf";
    }

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
    $mpdf->SetSubject('Controle de SIM Cards');
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
    if (array_key_exists('simcardID', $args)) {
      $this->info("Acesso ao PDF com as informações cadastrais do "
        . "SIM Card ICCID '{iccid}'.",
        [ 'iccid' => $simcard['iccid'] ]
      );
    } else {
      if ($subsidiaryID === 0) {
        $this->info("Acesso ao PDF com a relação de SIM Cards do "
          . "fornecedor '{supplierName}'.", 
          [ 'supplierName' => $simcards[0]->suppliername ]
        );
      } else {
        $this->info("Acesso ao PDF com a relação de SIM Cards da "
          . "unidade '{subsidiaryName}' do fornecedor "
          . "'{supplierName}'.", 
          [ 'subsidiaryName' => $simcards[0]->subsidiaryname,
            'supplierName' => $simcards[0]->suppliername ]
        );
      }
    }

    return $response
      ->withBody(new Stream($stream))
      ->withHeader('Content-Type', 'application/pdf')
      ->withHeader('Cache-Control', 'no-store, no-cache, '
          . 'must-revalidate'
        )
      ->withHeader('Expires', 'Sun, 1 Jan 2000 12:00:00 GMT')
      ->withHeader('Last-Modified', gmdate('D, d M Y H:i:s') . 'GMT')
    ;
  }

  /**
   * Recupera a relação dos SIM Cards em formato JSON no padrão dos
   * campos de preenchimento automático.
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
    $this->debug("Relação de SIM Cards para preenchimento automático "
      . "despachada."
    );

    // --------------------------[ Recupera os dados requisitados ]-----

    // Recupera os dados da requisição
    $postParams = $request->getParsedBody();

    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();

    // Lida com as informações provenientes do searchbox
    $name   = addslashes($postParams['searchTerm']);

    // Determina os limites e parâmetros da consulta
    $start  = 0;
    $length = $postParams['limit'];
    $ORDER         = 'iccid ASC';
    
    // Registra o acesso
    $this->debug("Acesso aos dados de preenchimento automático dos "
      . "SIM Cards cujo ICCID contenha '{name}'",
      [ 'name' => $name ]
    );
    
    try
    {
      // Localiza os SIM Cards na base de dados
      $message = "SIM Cards cujo ICCID contém '{$name}'";
      $simcards = SimCard::join("mobileoperators",
            "simcards.mobileoperatorid", '=', 
            "mobileoperators.mobileoperatorid"
          )
        ->join("simcardtypes", "simcards.simcardtypeid",
            '=', "simcardtypes.simcardtypeid"
          )
        ->whereRaw("((simcards.contractorid = {$contractor->id} AND simcards.assignedtoid IS NULL) OR (simcards.assignedtoid = {$contractor->id}))")
        ->whereRaw("public.unaccented(simcards.iccid) ILIKE "
            . "public.unaccented(E'%{$name}%')"
          )
        ->whereRaw("simcards.storagelocation IN ('StoredOnDeposit', "
            . "'StoredWithTechnician', 'StoredWithServiceProvider')"
          )
        ->where("simcards.blocked", "false")
        ->skip($start)
        ->take($length)
        ->orderByRaw($ORDER)
        ->get([
          'simcards.simcardid AS id',
          'simcards.iccid AS name',
          $this->DB->raw("CASE WHEN simcards.phonenumber IS NULL THEN 'Telefone não disponível' ELSE simcards.phonenumber END AS phonenumber"),
          'simcards.mobileoperatorid',
          'mobileoperators.name AS mobileoperatorname',
          'simcards.simcardtypeid',
          'simcardtypes.name AS simcardtypename'
        ])
      ;
      
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getQueryParams(),
            'message' => $message,
            'data' => $simcards
          ])
      ;
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module} para preenchimento automático. Erro interno no "
        . "banco de dados: {error}.",
        [ 'module' => 'SIM Cards',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de SIM Cards "
        . "para preenchimento automático. Erro interno no banco de "
        . "dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module} para preenchimento automático. Erro interno: "
        . "{error}.",
        [ 'module' => 'SIM Cards',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de SIM Cards "
        . "para preenchimento automático. Erro interno."
      ;
    }
    
    // Retorna o erro
    return $response
      ->withHeader('Content-type', 'application/json')
      ->withJson([
          'result' => 'NOK',
          'params' => $request->getQueryParams(),
          'message' => "Não foi possível localizar SIM Cards cujo "
            . "ICCID contém '$name'",
          'data' => []
        ])
    ;
  }

  /**
   * Exibe a página de histórico de movimentações em um SIM Card.
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
  public function showHistory(Request $request, Response $response,
    array $args)
  {
    // Recupera o ID do SIM Card
    $simcardID = $args['simcardID'];

    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ERP\Home')
    );
    $this->breadcrumb->push('Dispositivos', '');
    $this->breadcrumb->push('SIM Cards',
      $this->path('ERP\Devices\SimCards')
    );
    $this->breadcrumb->push('Histórico',
      $this->path('ERP\Devices\SimCards\History', [
        'simcardID' => $simcardID
      ])
    );

    // Registra o acesso
    $this->info("Acesso à visualização do histórico de movimentações "
      . "de um SIM Card."
    );

    // Recupera os dados da sessão
    $history = $this->session->get('history',
      [ 'period' => 0 ])
    ;
    $history['simcardID'] = $simcardID;

    // Renderiza a página
    return $this->render($request, $response,
      'erp/devices/simcards/history.twig',
      [ 'history' => $history ])
    ;
  }

  /**
   * Recupera a relação do histórico de movimentações do SIM Card em
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
      . "SIM Card."
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
    $simcardID = $postParams['simcardID'];
    $period = $postParams['period'];

    // Seta os valores da última pesquisa na sessão
    $history = $this->session->set('history',
      [ 'period' => $period ]);
    
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
                FROM erp.getHistoryData({$contractorID}, 'SimCard',
                       '{$simcardID}', '{$period}', {$start},
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
        $error = "Não temos histórico de movimentos para o SIM Card "
          . "cadastrados" . (($period = 0)?".":" no período indicado.")
        ;
      }
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno no banco de dados: {error}",
        [ 'module' => 'histórico de movimentos de um SIM Card',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de histórico "
        . "de movimentos de um SIM Card. Erro interno no banco de "
        . "dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno: {error}",
        [ 'module' => 'histórico de movimentos de um SIM Card',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de histórico "
        . "de movimentos de um SIM Card. Erro interno."
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
