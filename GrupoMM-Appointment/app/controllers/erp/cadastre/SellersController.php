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
 * O controlador do gerenciamento dos vendedores do sistema. Um vendedor
 * pode ser uma pessoa física e/ou jurídica.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Controllers\ERP\Cadastre;

use App\Models\DocumentType;
use App\Models\Entity as Seller;
use App\Models\EntityType;
use App\Models\Gender;
use App\Models\Mailing;
use App\Models\MailingAddress;
use App\Models\MailingProfile;
use App\Models\MaritalStatus;
use App\Models\Phone;
use App\Models\PhoneType;
use App\Models\Subsidiary;
use App\Providers\StateRegistration;
use Core\Controllers\Controller;
use Core\Controllers\HandleFileTrait;
use Core\Controllers\QueryTrait;
use Core\Helpers\FormatterTrait;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use InvalidArgumentException;
use Mpdf\Mpdf;
use Respect\Validation\Validator as V;
use RuntimeException;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\Stream;

class SellersController
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
   * As funções de formatação especiais
   */
  use FormatterTrait;

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
      'entityid' => V::notBlank()
        ->intVal()
        ->setName('ID do Vendedor'),
      'name' => V::notBlank()
        ->length(2, 100)
        ->setName('Vendedor'),
      'tradingname' => V::optional(
            V::notBlank()
              ->length(2, 100)
          )
        ->setName('Nome fantasia/apelido'),
      'entitytypeid' => V::notBlank()
        ->intVal()
        ->setName('Tipo de entidade'),
      'juridicalperson' => V::boolVal()
        ->setName('Tipo jurídico da entidade'),
      'subsidiaries' => [
        'subsidiaryid' => V::intVal()
          ->setName('ID da unidade/filial'),
        'headoffice' => V::boolVal()
          ->setName('Indicador de matriz/titular'),
        'name' => V::notEmpty()
          ->length(2, 100)
          ->setName('Nome da unidade/filial'),
        'regionaldocumenttype' => V::notEmpty()
          ->intVal()
          ->setName('Tipo de documento'),
        'regionaldocumentnumber' => V::optional(
              V::notEmpty()
                ->length(1, 20)
            )
          ->setName('Número do documento'),
        'regionaldocumentstate' => V::oneOf(
              V::not(V::notEmpty()),
              V::notEmpty()->oneState()
            )
          ->setName('UF'),
        'nationalregister' => V::oneOf(
            V::notEmpty()->cpf(),
            V::notEmpty()->cnpj()
          )->setName('CPF/CNPJ'),
        'birthday' => V::optional(
              V::notEmpty()
                ->date('d/m/Y')
            )
          ->setName('Data de nascimento'),
        'maritalstatusid' => V::optional(
              V::notEmpty()
                ->intVal()
            )
          ->setName('Estado civil'),
        'genderid' => V::optional(
              V::notEmpty()
                ->intVal()
            )
          ->setName('Sexo'),
        'address' => V::notEmpty()
          ->length(2, 100)
          ->setName('Endereço'),
        'streetnumber' => V::optional(
              V::notEmpty()
                ->length(1, 10)
            )
          ->setName('Nº'),
        'complement' => V::optional(
              V::notEmpty()
                ->length(2, 30)
            )
          ->setName('Complemento'),
        'district' => V::optional(
            V::notEmpty()
              ->length(2, 50)
          )->setName('Bairro'),
        'postalcode' => V::notEmpty()
          ->postalCode('BR')
          ->setName('CEP'),
        'cityname' => V::notEmpty()
          ->length(2, 50)
          ->setName('Cidade'),
        'cityid' => V::notEmpty()
          ->intVal()
          ->setName('ID da cidade'),
        'state' => V::notBlank()
          ->oneState()
          ->setName('UF'),
        'personname' => V::optional(
              V::notEmpty()
                ->length(2, 50)
            )
          ->setName('Contato'),
        'department' => V::optional(
              V::notEmpty()
                ->length(2, 50)
            )
          ->setName('Departamento'),
        'blocked' => V::boolVal()
          ->setName('Bloquear esta unidade/filial'),
        'phones' => [
          'phoneid' => V::intVal()
            ->setName('ID do telefone'),
          'phonenumber' => V::notBlank()
            ->length(14, 20)
            ->setName('Telefone'),
          'phonetypeid' => V::notBlank()
            ->intval()
            ->setName('Tipo de telefone')
        ],
        'emails' => [
          'mailingid' => V::intVal()
            ->setName('ID do e-mail'),
          'email' => V::optional(
                V::notEmpty()
                  ->length(2, 100)
                  ->email()
              )
            ->setName('E-Mail')
        ],
        'contacts' => [
          'mailingaddressid' => V::intVal()
            ->setName('ID do contato'),
          'name' => V::notBlank()
            ->length(2, 50)
            ->setName('Nome do contato'),
          'attribute' => V::optional(
                V::notBlank()
                  ->length(2, 50)
              )
            ->setName('Departamento/Observação'),
          'mailingprofileid' => V::notBlank()
            ->intval()
            ->setName('Perfil'),
          'email' => V::optional(
                V::notEmpty()
                  ->length(2, 100)
                  ->email()
              )
            ->setName('E-Mail'),
          'phonenumber' => V::optional(
                V::notBlank()
                  ->length(14, 20)
              )
            ->setName('Telefone'),
          'phonetypeid' => V::notBlank()
            ->intval()
            ->setName('Tipo de telefone')
        ]
      ],
      'note' => V::optional(
            V::notBlank()
          )
        ->setName('Observação')
    ];

    if ($addition) {
      // Ajusta as regras para adição
      unset($validationRules['entityid']);
      unset($validationRules['subsidiaries']['subsidiaryid']);
      unset($validationRules['subsidiaries']['phones']['phoneid']);
      unset($validationRules['subsidiaries']['emails']['mailingid']);
      unset($validationRules['subsidiaries']['contacts']['mailingaddressid']);
    } else {
      // Ajusta as regras para edição
      $validationRules['entitytypename'] = V::notBlank()
        ->setName('Tipo de entidade')
      ;
      $validationRules['blocked'] = V::boolVal()
        ->setName('Bloquear este vendedor e todas suas '
            . 'unidades/filiais'
          )
      ;
    }

    return $validationRules;
  }

  /**
   * Recupera as informações de tipos de entidades.
   *
   * @throws RuntimeException
   *   Em caso de não termos tipos de entidades
   *
   * @return Collection
   *   A matriz com as informações de tipos de entidades
   */
  protected function getEntitiesTypes(): Collection
  {
    try {
      // Recupera as informações de tipos de entidades
      $entityTypes = EntityType::orderBy('entitytypeid')
        ->get([
            'entitytypeid as id',
            'name',
            'juridicalperson'
          ])
      ;

      if ( $entityTypes->isEmpty() ) {
        throw new Exception("Não temos nenhum tipo de entidade "
          . "cadastrada"
        );
      }
    }
    catch (Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível obter as informações de tipos de "
        . "entidades. Erro: {error}.",
        [ 'error' => $exception->getMessage() ]
      );

      throw new RuntimeException("Não foi possível obter os tipos de "
        . "entidades"
      );
    }

    return $entityTypes;
  }

  /**
   * Recupera as informações de tipos de documentos.
   *
   * @throws RuntimeException
   *   Em caso de não termos tipos de documentos
   *
   * @return Collection
   *   A matriz com as informações de tipos de documentos
   */
  protected function getDocumentTypes(): Collection
  {
    try {
      // Recupera as informações de tipos de documentos
      $documentTypes = DocumentType::orderBy('documenttypeid')
        ->get([
            'documenttypeid as id',
            'name',
            'juridicalperson'
          ])
      ;

      if ( $documentTypes->isEmpty() ) {
        throw new Exception("Não temos nenhum tipo de documento "
          . "cadastrado"
        );
      }
    }
    catch (Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível obter as informações de tipos de "
        . "documentos. Erro: {error}.",
        [ 'error' => $exception->getMessage() ]
      );

      throw new RuntimeException("Não foi possível obter os tipos de "
        . "documentos"
      );
    }

    return $documentTypes;
  }

  /**
   * Recupera as informações de gêneros.
   *
   * @throws RuntimeException
   *   Em caso de não termos gêneros
   *
   * @return Collection
   *   A matriz com as informações de gêneros
   */
  protected function getGenders(): Collection
  {
    try {
      // Recupera as informações de gêneros
      $genders = Gender::orderBy('genderid')
        ->get([
            'genderid as id',
            'name'
          ])
      ;

      if ( $genders->isEmpty() ) {
        throw new Exception("Não temos nenhum gênero cadastrado");
      }
    }
    catch (Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível obter as informações de gêneros. "
        . "Erro: {error}.",
        [ 'error' => $exception->getMessage() ]
      );

      throw new RuntimeException("Não foi possível obter os gêneros");
    }

    return $genders;
  }

  /**
   * Recupera as informações de estados civis.
   *
   * @throws RuntimeException
   *   Em caso de não termos estados civis
   *
   * @return Collection
   *   A matriz com as informações de estados civis
   */
  protected function getMaritalStatus(): Collection
  {
    try {
      // Recupera as informações de estados civis
      $maritalstatus = MaritalStatus::orderBy('name')
        ->get([
            'maritalstatusid as id',
            'name'
          ])
      ;

      if ( $maritalstatus->isEmpty() ) {
        throw new Exception("Não temos nenhum estado civil cadastrado");
      }
    }
    catch (Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível obter as informações de estados "
        . "civis. Erro: {error}.",
        [ 'error' => $exception->getMessage() ]
      );

      throw new RuntimeException("Não foi possível obter os estados "
        . "civis"
      );
    }

    return $maritalstatus;
  }

  /**
   * Recupera as informações de tipos de telefones.
   *
   * @throws RuntimeException
   *   Em caso de não termos tipos de telefones
   *
   * @return Collection
   *   A matriz com as informações de tipos de telefones
   */
  protected function getPhoneTypes(): Collection
  {
    try {
      // Recupera as informações de tipos de telefones
      $phoneTypes = PhoneType::orderBy('phonetypeid')
        ->get([
            'phonetypeid as id',
            'name'
          ])
      ;

      if ( $phoneTypes->isEmpty() ) {
        throw new Exception("Não temos nenhum tipo de telefone "
          . "cadastrado"
        );
      }
    }
    catch (Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível obter as informações de tipos de "
        . "telefones. Erro: {error}.",
        [ 'error' => $exception->getMessage() ]
      );

      throw new RuntimeException("Não foi possível obter os tipos de "
        . "telefones"
      );
    }

    return $phoneTypes;
  }

  /**
   * Recupera as informações de perfis de notificação.
   *
   * @param int $contractorID
   *   A ID do contratante para o qual desejamos obter os perfis de
   *   notificação disponíveis
   *
   * @throws RuntimeException
   *   Em caso de não termos perfis de notificação
   *
   * @return Collection
   *   A matriz com as informações de perfis de notificação
   */
  protected function getMailingProfiles(
    int $contractorID
  ): Collection
  {
    try {
      // Recupera as informações de perfis de notificação
      $mailingProfiles = MailingProfile::orderBy('name')
        ->where('contractorid', $contractorID)
        ->get([
            'mailingprofileid as id',
            'name'
          ])
      ;

      if ( $mailingProfiles->isEmpty() ) {
        throw new Exception("Não temos nenhum perfil de notificação "
          . "cadastrado"
        );
      }
    }
    catch (Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível obter as informações de perfis de "
        . "notificação. Erro: {error}.",
        [ 'error' => $exception->getMessage() ]
      );

      throw new RuntimeException("Não foi possível obter os perfis de "
        . "notificação"
      );
    }

    return $mailingProfiles;
  }

  /**
   * Recupera o perfil de notificação padrão.
   *
   * @param int $contractorID
   *   A ID do contratante para o qual desejamos obter o perfil de
   *   notificação padrão
   *
   * @return int
   *   O ID do perfil de notificação padrão
   */
  protected function getDefaultMailingProfile(
    int $contractorID
  ): int
  {
    $defaultMailingProfileID = 0;
    try {
      // Recupera as informações de perfis de notificação
      $defaultMailingProfile = MailingProfile::leftJoin('actionsperprofiles',
          function($join) {
            $join->on('mailingprofiles.mailingprofileid',
              '=', 'actionsperprofiles.mailingprofileid'
            );
            $join->on('mailingprofiles.contractorid',
              '=', 'actionsperprofiles.contractorid'
            );
          })
        ->where('mailingprofiles.contractorid', $contractorID)
        ->selectRaw('mailingprofiles.mailingprofileid as id')
        ->groupBy('mailingprofiles.mailingprofileid')
        ->havingRaw("count(actionsperprofiles.*) = 0")
        ->get()
      ;

      // Determina o perfil padrão
      if ( $defaultMailingProfile->isNotEmpty() ) {
        $defaultMailingProfileID = $defaultMailingProfile->first()->id;
      }
    }
    catch (Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível obter as informações do perfil de "
        . "notificação padrão. Erro: {error}.",
        [ 'error' => $exception->getMessage() ]
      );

      return 0;
    }

    return $defaultMailingProfileID;
  }

  /**
   * Recupera as informações de números de telefones de uma
   * unidade/filial/titular/associado.
   *
   * @param int $sellerID
   *   A ID do vendedor para o qual desejamos obter esta informação
   * @param int $subsidiaryID
   *   A ID da unidade/filial deste vendedor para o qual desejamos
   *   obter os dados de telefones disponíveis
   *
   * @throws RuntimeException
   *   Em caso de erros
   *
   * @return Collection
   *   A matriz com as informações de números de telefones
   */
  protected function getPhones(
    int $sellerID,
    int $subsidiaryID
  ): Collection
  {
    // Recupera as informações de números de telefones
    return Phone::join('phonetypes',
          'phones.phonetypeid', '=', 'phonetypes.phonetypeid'
        )
      ->where('entityid', $sellerID)
      ->where('subsidiaryid', $subsidiaryID)
      ->get([
          'phones.entityid',
          'phones.subsidiaryid',
          'phones.phoneid',
          'phones.phonetypeid',
          'phonetypes.name as phonetypename',
          'phones.phonenumber'
        ])
    ;
  }

  /**
   * Recupera as informações de e-mails de uma unidade/filial.
   *
   * @param int $sellerID
   *   A ID do vendedor para o qual desejamos obter esta informação
   * @param int $subsidiaryID
   *   A ID da unidade/filial deste vendedor para o qual desejamos
   *   obter os dados de e-mails disponíveis
   *
   * @throws RuntimeException
   *   Em caso de erros
   *
   * @return Collection
   *   A matriz com as informações de e-mails
   */
  protected function getEmails(
    int $sellerID,
    int $subsidiaryID
  ): Collection
  {
    // Recupera as informações de dados de e-mail
    return Mailing::where('entityid', $sellerID)
      ->where('subsidiaryid', $subsidiaryID)
      ->get([
          'entityid',
          'subsidiaryid',
          'mailingid',
          'email'
        ])
    ;
  }

  /**
   * Recupera as informações de contatos adicionais de uma
   * unidade/filial/titular/associado.
   *
   * @param int $contractorID
   *   A ID do contratante deste vendedor
   * @param int $sellerID
   *   A ID do vendedor para o qual desejamos obter esta informação
   * @param int $subsidiaryID
   *   A ID da unidade/filial deste vendedor para o qual desejamos
   *   obter os dados de contato disponíveis
   *
   * @throws RuntimeException
   *   Em caso de erros
   *
   * @return Collection
   *   A matriz com as informações de dados de contatos adicionais
   */
  protected function getContacts(
    int $contractorID,
    int $sellerID,
    int $subsidiaryID
  ): Collection
  {
    // Recupera as informações de contatos adicionais
    return MailingAddress::join('phonetypes',
          'mailingaddresses.phonetypeid', '=', 'phonetypes.phonetypeid'
        )
      ->join('mailingprofiles', function($join) use ($contractorID) {
          $join
            ->on('mailingaddresses.mailingprofileid',
                '=', 'mailingprofiles.mailingprofileid'
              )
            ->where('mailingprofiles.contractorid',
                '=', $contractorID
              )
          ;
        })
      ->where('entityid', $sellerID)
      ->where('subsidiaryid', $subsidiaryID)
      ->get([
          'mailingaddresses.entityid',
          'mailingaddresses.subsidiaryid',
          'mailingaddresses.mailingaddressid',
          'mailingaddresses.name',
          'mailingaddresses.attribute',
          'mailingaddresses.mailingprofileid',
          'mailingprofiles.name as mailingprofilename',
          'mailingaddresses.email',
          'mailingaddresses.phonetypeid',
          'phonetypes.name as phonetypename',
          'mailingaddresses.phonenumber'
        ])
    ;
  }

  /**
   * Exibe a página inicial do gerenciamento de vendedores.
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
    $this->breadcrumb->push('Cadastro', '');
    $this->breadcrumb->push('Vendedores',
      $this->path('ERP\Cadastre\Sellers')
    );

    // Registra o acesso
    $this->info("Acesso ao gerenciamento de vendedores.");

    // Recupera os dados da sessão
    $seller = $this->session->get('seller',
      [ 'searchField' => 'name',
        'searchValue' => '',
        'displayStart' => 0 ]
    );

    // Renderiza a página
    return $this->render($request, $response,
      'erp/cadastre/sellers/sellers.twig',
      [ 'seller' => $seller ])
    ;
  }

  /**
   * Recupera a relação dos vendedores em formato JSON.
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
    $this->debug("Acesso à relação de vendedores.");

    // --------------------------[ Recupera os dados requisitados ]-----

    // Recupera os dados da requisição
    $postParams = $request->getParsedBody();

    // Recupera a informação do contratante
    $contractor = $this->authorization->getContractor();

    // Lida com as informações provenientes do Datatables
    
    // O número da requisição sequencial
    $draw = $postParams['draw'];

    // A posição inicial (0 = início) da paginação
    $start = $postParams['start'];

    // O comprimento de cada página
    $length = $postParams['length'];

    // Lida com as informações adicionais de filtragem

    // O campo de pesquisa selecionado
    if (array_key_exists('searchField', $postParams)) {
      $searchField = $postParams['searchField'];
      $searchValue = trim($postParams['searchValue']);
    } else {
      $searchField = 'name';
      $searchValue = '';
    }

    // Seta os valores da última pesquisa na sessão
    $this->session->set('seller',
      [
        'searchField' => $searchField,
        'searchValue' => $searchValue,
        'displayStart' => $start
      ]
    );

    // Corrige o escape dos campos
    $searchValue = addslashes($searchValue);

    try
    {
      // Realiza a consulta
      $sql = "SELECT E.entityID as id,
                     E.subsidiaryID,
                     E.juridicalperson,
                     E.headOffice,  
                     E.level,  
                     E.activeRelationship AS active,
                     E.name,
                     E.tradingname,
                     E.blocked,
                     E.cityname,
                     E.nationalregister,
                     E.blockedlevel,
                     0 as delete,
                     E.createdat,
                     E.fullcount
                FROM erp.getEntitiesData({$contractor->id}, 0,
                  'seller', '{$searchValue}', '{$searchField}',
                  NULL, NULL, NULL, {$start}, {$length}) as E;"
      ;
      $sellers = $this->DB->select($sql);

      if (count($sellers) > 0) {
        $rowCount = $sellers[0]->fullcount;

        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'draw' => $draw,
              'recordsTotal' => $rowCount,
              'recordsFiltered' => $rowCount,
              'data' => $sellers
            ])
        ;
      } else {
        if (empty($searchValue)) {
          $error = "Não temos vendedores cadastrados.";
        } else {
          switch ($searchField) {
            case 'subsidiaryname':
              $fieldLabel = 'nome da unidade/filial';

              break;
            case 'nationalregister':
              $fieldLabel = 'CPF/CNPJ da unidade/filial';

              break;
            case 'name':
              $fieldLabel = 'nome';

              break;
            case 'tradingname':
              $fieldLabel = 'apelido/nome fantasia';

              break;
            default:
              $fieldLabel = 'conteúdo';

              break;
          }

          // Define a mensagem de erro
          $error = "Não temos vendedores cadastrados cujo "
            . "{$fieldLabel} contém <i>{$searchValue}</i>."
          ;
        }
      }
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno no banco de dados: {error}.",
        [ 'module' => 'vendedores',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de "
        . "vendedores. Erro interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno: {error}.",
        [ 'module' => 'vendedores',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de "
        . "vendedores. Erro interno."
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
   * Exibe um formulário para adição de um vendedor, quando solicitado,
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

      // Recupera as informações de tipos de entidades
      $entityTypes = $this->getEntitiesTypes();

      // Recupera as informações de tipos de documentos
      $documentTypes = $this->getDocumentTypes();

      // Recupera as informações de gêneros
      $genders = $this->getGenders();

      // Recupera as informações de estados civis
      $maritalStatus = $this->getMaritalStatus();

      // Recupera as informações de tipos de telefones
      $phoneTypes = $this->getPhoneTypes();

      // Recupera as informações de perfis de notificação
      $mailingProfiles = $this->getMailingProfiles($contractor->id);

      // Recupera um perfil padrão para os novos contatos
      $defaultMailingProfileID = $this->getDefaultMailingProfile(
        $contractor->id
      );
    }
    catch (RuntimeException $exception)
    {
      // Alerta o usuário
      $this->flash("error", $exception->getMessage());

      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [ 'routeName' => 'ERP\Cadastre\Sellers' ]
      );

      // Redireciona para a página de gerenciamento de vendedores
      return $this->redirect($response, 'ERP\Cadastre\Sellers');
    }

    // Verifica se estamos postando os dados
    if ($request->isPost()) {
      // Os dados estão sendo postados

      // Registra o acesso
      $this->debug("Processando à adição de vendedor.");

      // Monta uma matriz para validação dos tipos de documentos
      $regionalDocumentTypes = [ ];
      foreach ($documentTypes as $documentType) {
        $regionalDocumentTypes[ $documentType->id ] =
          $documentType->name
        ;
      }

      // Valida os dados
      $this->validator->validate($request,
        $this->getValidationRules(true)
      );

      if ($this->validator->isValid()) {
        $this->debug('Os dados do vendedor são VÁLIDOS');

        // Recupera os dados do vendedor
        $sellerData = $this->validator->getValues();

        // Verifica se as inscrições estaduais são válidas. Inicialmente
        // considera todas válidas e, durante a análise, se alguma não
        // for válida, então registra qual delas está incorreta
        $allHasValid = true;
        foreach($sellerData['subsidiaries']
          as $subsidiaryNumber => $subsidiary) {
          // Recupera o tipo de documento
          $documentTypeID = $subsidiary['regionaldocumenttype'];

          // Se o tipo de documento for 'Inscrição Estadual' precisa
          // verificar se o valor informado é válido
          if (
            $regionalDocumentTypes[ $documentTypeID ]
            === 'Inscrição Estadual'
          ) {
            try {
              if (strlen($subsidiary['regionaldocumentnumber']) > 0) {
                // Verifica se a UF foi informada
                if ( (strtolower($subsidiary['regionaldocumentnumber'])
                      !== 'isento')
                    && (empty($subsidiary['regionaldocumentstate'])) ) {
                  // Invalida o formulário
                  $allHasValid = false;

                  // Seta o erro neste campo
                  $this->validator->setErrors([
                      'regionaldocumentstate' =>
                        'UF precisa ser preenchido(a)'
                    ],
                    "subsidiaries[{$subsidiaryNumber}][regionaldocumentstate]")
                  ;
                } else {
                  // Verifica se a inscrição estadual é válida
                  if ( !(StateRegistration::isValid(
                          $subsidiary['regionaldocumentnumber'],
                          $subsidiary['regionaldocumentstate']
                        )) ) {
                    // Invalida o formulário
                    $allHasValid = false;

                    // Seta o erro neste campo
                    $this->validator->setErrors([
                        'stateRegistration' =>
                          'A inscrição estadual não é válida'
                      ],
                      "subsidiaries[{$subsidiaryNumber}][regionaldocumentnumber]")
                    ;
                  }
                }
              }
            }
            catch(InvalidArgumentException $exception) {
              // Ocorreu uma exceção, então invalida o formulário
              $allHasValid = false;

              // Seta o erro neste campo
              $this->validator->setErrors([
                  'state' => $exception->getMessage()
                ],
                "subsidiaries[{$subsidiaryNumber}][regionaldocumentnumber]")
              ;
            }
          }

          if (array_key_exists('contacts', $subsidiary)) {
            // Verifica se os contatos adicionais contém ao menos uma
            // informação de contato, seja o telefone ou o e-mail
            foreach ($subsidiary['contacts']
              as $contactNumber => $contactData) {
              // Verifica se foi fornecido um telefone ou e-mail
              if ( empty($contactData['email']) &&
                   empty($contactData['phonenumber']) ) {
                // Invalida o formulário
                $allHasValid = false;

                // Seta o erro nestes campos
                $this->validator->setErrors([
                    'email' => "Informe um e-mail ou telefone para "
                      . "contato"
                  ],
                  "subsidiaries[{$subsidiaryNumber}][contacts][{$contactNumber}][email]")
                ;
                $this->validator->setErrors([
                    'phonenumber' => "Informe um e-mail ou telefone "
                      . "para contato"
                  ],
                  "subsidiaries[{$subsidiaryNumber}][contacts][{$contactNumber}][phonenumber]")
                ;
              }
            }
          }
        }

        if ($allHasValid) {
          try
          {
            // Primeiro, verifica se não temos um vendedor com o mesmo
            // nome (razão social no caso de pessoa jurídica)
            if (Seller::where("contractorid", '=', $contractor->id)
                  ->where("seller", "true")
                  ->whereRaw("public.unaccented(name) ILIKE "
                      . "public.unaccented('{$sellerData['name']}')"
                    )
                  ->count() === 0) {
              // Agora verifica se não temos outra unidade/filial com o
              // mesmo cpf e/ou cnpj dos informados neste vendedor
              $save = true;
              foreach($sellerData['subsidiaries'] as $subsidiary)
              {
                if (Seller::join("subsidiaries", "entities.entityid",
                          '=', "subsidiaries.entityid"
                        )
                      ->where("entities.seller", "true")
                      ->where("entities.contractorid", '=',
                          $contractor->id
                        )
                      ->where("subsidiaries.nationalregister",
                          $subsidiary['nationalregister']
                        )
                      ->count() !== 0) {
                  $save = false;

                  // Alerta sobre a existência de outra unidade/filial
                  // de vendedor com o mesmo CPF/CNPJ
                  if (strlen($subsidiary['nationalregister']) === 14) {
                    $person = 'o titular';
                    $documentName = 'CPF';
                  } else {
                    $person = 'a unidade/filial';
                    $documentName = 'CNPJ';
                  }

                  // Registra o erro
                  $this->debug("Não foi possível inserir as "
                    . "informações d{person} '{subsidiaryName}' do "
                    . "vendedor '{name}'. Já existe outr{person} com "
                    . "o {document} {nationalregister}.",
                    [ 'subsidiaryName'  => $subsidiary['name'],
                      'name'  => $sellerData['name'],
                      'person' => $person,
                      'document'  => $documentName,
                      'nationalregister' => $subsidiary['nationalregister'] ]
                  );

                  // Alerta o usuário
                  $this->flashNow("error", "Já existe outr{person} com "
                    . "o mesmo <b>{document}</b> "
                    . "<i>'{nationalregister}'</i>.",
                    [ 'person' => $person,
                      'document'  => $documentName,
                      'nationalregister' => $subsidiary['nationalregister'] ]
                  );

                  break;
                }
              }

              // Verificamos se devemos proceder a gravação dos dados
              if ($save) {
                // Grava o novo vendedor

                // Separamos as informações das unidades/filiais do
                // restante dos dados do vendedor
                $subsidiariesData = $sellerData['subsidiaries'];
                unset($sellerData['subsidiaries']);

                // Iniciamos a transação
                $this->DB->beginTransaction();

                // Incluímos um novo vendedor
                $seller = new Seller();
                $seller->fill($sellerData);
                // Indicamos que é um vendedor
                $seller->seller = true;
                // Adicionamos as demais informações
                $seller->contractorid = $contractor->id;
                $seller->createdbyuserid =
                  $this->authorization->getUser()->userid
                ;
                $seller->updatedbyuserid =
                  $this->authorization->getUser()->userid
                ;
                $seller->save();
                $sellerID = $seller->entityid;

                // Incluímos todas unidades/filiais deste vendedor
                foreach($subsidiariesData as $subsidiaryData) {
                  // Separamos as informações dos dados de telefones dos
                  // demais dados desta unidade/filial
                  $phonesData = $subsidiaryData['phones'];
                  unset($subsidiaryData['phones']);

                  // Separamos as informações dos dados de e-mails dos
                  // demais dados desta unidade/filial
                  $emailsData = $subsidiaryData['emails'];
                  unset($subsidiaryData['emails']);

                  // Separamos as informações dos dados de contatos
                  // adicionais dos demais dados desta unidade/filial
                  if (array_key_exists('contacts', $subsidiaryData)) {
                    $contactsData =
                      $subsidiaryData['contacts']
                    ;
                    unset($subsidiaryData['contacts']);
                  } else {
                    $contactsData = [];
                  }

                  // Sempre mantém a UF do documento em maiúscula
                  $subsidiaryData['regionaldocumentstate'] =
                    strtoupper($subsidiaryData['regionaldocumentstate'])
                  ;

                  // Incluímos a nova unidade/filial
                  $subsidiary = new Subsidiary();
                  $subsidiary->fill($subsidiaryData);
                  $subsidiary->entityid = $sellerID;
                  $subsidiary->createdbyuserid =
                    $this->authorization->getUser()->userid
                  ;
                  $subsidiary->updatedbyuserid =
                    $this->authorization->getUser()->userid
                  ;
                  $subsidiary->save();
                  $subsidiaryID = $subsidiary->subsidiaryid;

                  // Incluímos os dados de telefones para esta
                  // unidade/filial
                  foreach($phonesData as $phoneData)
                  {
                    // Retiramos o campo de ID do telefone, pois os
                    // dados tratam de um novo registro
                    unset($phoneData['phoneid']);

                    // Incluímos um novo telefone desta unidade/filial
                    $phone = new Phone();
                    $phone->fill($phoneData);
                    $phone->entityid = $sellerID;
                    $phone->subsidiaryid = $subsidiaryID;
                    $phone->save();
                  }

                  // Incluímos os dados de emails para esta
                  // unidade/filial
                  foreach($emailsData as $emailData) {
                    // Retiramos o campo de ID do e-mail, pois os dados
                    // tratam de um novo registro
                    unset($emailData['mailingid']);

                    // Como podemos não ter um endereço de e-mail, então
                    // ignora caso ele não tenha sido fornecido
                    if (trim($emailData['email']) !== '') {
                      // Incluímos um novo e-mail desta unidade/filial
                      $mailing = new Mailing();
                      $mailing->fill($emailData);
                      $mailing->entityid     = $sellerID;
                      $mailing->subsidiaryid = $subsidiaryID;
                      $mailing->save();
                    }
                  }

                  // Incluímos os dados de contatos adicionais para esta
                  // unidade/filial
                  foreach($contactsData as $contactData)
                  {
                    // Retiramos o campo de ID do contato, pois os dados
                    // tratam de um novo registro
                    unset($contactData['mailingaddressid']);
                    
                    // Incluímos um novo contato desta unidade/filial
                    $mailingAddress = new MailingAddress();
                    $mailingAddress->fill($contactData);
                    $mailingAddress->entityid        = $sellerID;
                    $mailingAddress->subsidiaryid    = $subsidiaryID;
                    $mailingAddress->createdbyuserid =
                      $this->authorization->getUser()->userid
                    ;
                    $mailingAddress->updatedbyuserid =
                      $this->authorization->getUser()->userid
                    ;
                    $mailingAddress->save();
                  }
                }

                // Efetiva a transação
                $this->DB->commit();

                // Registra o sucesso
                $this->info("Cadastrado o vendedor '{name}' com "
                  . "sucesso.",
                  [ 'name'  => $sellerData['name'] ]
                );

                // Alerta o usuário
                $this->flash("success", "O vendedor <i>'{name}'</i> "
                  . "foi cadastrado com sucesso.",
                  [ 'name'  => $sellerData['name'] ]
                );

                // Registra o evento
                $this->debug("Redirecionando para {routeName}",
                  [ 'routeName' => 'ERP\Cadastre\Sellers' ]
                );

                // Redireciona para a página de gerenciamento de
                // vendedores
                return $this->redirect($response,
                  'ERP\Cadastre\Sellers')
                ;
              }
            } else {
              // Registra o erro
              $this->debug("Não foi possível inserir as informações do "
                . "vendedor '{name}'. Já existe outro vendedor com o "
                . "mesmo nome.",
                [ 'name'  => $sellerData['name'] ]
              );

              // Alerta o usuário
              $this->flashNow("error", "Já existe um vendedor com o "
                . "nome <i>'{name}'</i>.",
                [ 'name' => $sellerData['name'] ]
              );
            }
          }
          catch(QueryException $exception)
          {
            // Reverte (desfaz) a transação
            $this->DB->rollBack();

            // Registra o erro
            $this->error("Não foi possível inserir as informações do "
              . "vendedor '{name}'. Erro interno no banco de dados: "
              . "{error}",
              [ 'name'  => $sellerData['name'],
                'error' => $exception->getMessage() ]
            );

            // Alerta o usuário
            $this->flashNow("error", "Não foi possível inserir as "
              . "informações do vendedor. Erro interno no banco de "
              . "dados."
            );
          }
          catch(Exception $exception)
          {
            // Reverte (desfaz) a transação
            $this->DB->rollBack();

            // Registra o erro
            $this->error("Não foi possível inserir as informações do "
              . "vendedor '{name}'. Erro interno: {error}",
              [ 'name'  => $sellerData['name'],
                'error' => $exception->getMessage() ]
            );

            // Alerta o usuário
            $this->flashNow("error", "Não foi possível inserir as "
              . "informações do vendedor. Erro interno."
            );
          }
        }
      } else {
        $this->debug('Os dados do vendedor são INVÁLIDOS');
        $messages = $this->validator->getFormatedErrors();
        foreach ($messages AS $message) {
          $this->debug($message);
        }
      }
    } else {
      // Carrega os dados iniciais para simplificar a digitação
      $this->validator->setValues([
        'entityid' => 0,
        'entitytypeid' => $entityTypes[0]->toArray()['id'],
        'juridicalperson' => $entityTypes[0]->toArray()['juridicalperson'],
        'subsidiaries' => [
          0 => [
            'subsidiaryid' => 0,
            'headoffice' => true,
            'name' => 'Matriz',
            'regionaldocumenttype' => 4,
            'genderid' => 1,
            'maritalstatusid' => 1,
            'cityid' => 0,
            'cityname' => '',
            'state' => '',
            'phones' => [[
              'phoneid' => 0,
              'phonenumber' => '',
              'phonetypeid' => 1
            ]],
            'emails' => [[
              'mailingid' => 0,
              'email' => ''
            ]],
            'contacts' => [
            ]
          ]
        ]
      ]);
    }

    // Exibe um formulário para adição de um vendedor

    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ERP\Home')
    );
    $this->breadcrumb->push('Cadastro', '');
    $this->breadcrumb->push('Vendedores',
      $this->path('ERP\Cadastre\Sellers')
    );
    $this->breadcrumb->push('Adicionar',
      $this->path('ERP\Cadastre\Sellers\Add')
    );

    // Registra o acesso
    $this->info("Acesso à adição de vendedor.");

    return $this->render($request, $response,
      'erp/cadastre/sellers/seller.twig',
      [ 'formMethod' => 'POST',
        'entityTypes' => $entityTypes,
        'documentTypes' => $documentTypes,
        'genders' => $genders,
        'maritalStatus' => $maritalStatus,
        'phoneTypes' => $phoneTypes,
        'mailingProfiles' => $mailingProfiles,
        'defaultMailingProfileID' => $defaultMailingProfileID ])
    ;
  }

  /**
   * Exibe um formulário para edição de um vendedor, quando solicitado,
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
    try {
      // Recupera os dados do contratante
      $contractor = $this->authorization->getContractor();

      // Recupera as informações de tipos de documentos
      $documentTypes = $this->getDocumentTypes();

      // Recupera as informações de gêneros
      $genders = $this->getGenders();

      // Recupera as informações de estados civis
      $maritalStatus = $this->getMaritalStatus();

      // Recupera as informações de tipos de telefones
      $phoneTypes = $this->getPhoneTypes();

      // Recupera as informações de perfis de notificação
      $mailingProfiles = $this->getMailingProfiles($contractor->id);

      // Recupera um perfil padrão para os novos contatos
      $defaultMailingProfileID = $this->getDefaultMailingProfile(
        $contractor->id
      );
    }
    catch (RuntimeException $exception)
    {
      // Alerta o usuário
      $this->flash("error", $exception->getMessage());

      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [ 'routeName' => 'ERP\Cadastre\Sellers' ]
      );

      // Redireciona para a página de gerenciamento de vendedores
      return $this->redirect($response, 'ERP\Cadastre\Sellers');
    }

    try
    {
      // Recupera as informações do vendedor
      $sellerID = $args['sellerID'];
      $seller = Seller::join("entitiestypes", "entities.entitytypeid",
            '=', "entitiestypes.entitytypeid"
          )
        ->join("users as createduser", "entities.createdbyuserid",
            '=', "createduser.userid"
          )
        ->join("users as updateduser", "entities.updatedbyuserid",
            '=', "updateduser.userid"
          )
        ->where("entities.seller", "true")
        ->where("entities.entityid", $sellerID)
        ->where("entities.contractorid", '=', $contractor->id)
        ->get([
            'entitiestypes.name as entitytypename',
            'entitiestypes.juridicalperson as juridicalperson',
            'entities.*',
            'createduser.name as createdbyusername',
            'updateduser.name as updatedbyusername'
          ])
      ;

      if ( $seller->isEmpty() ) {
        throw new ModelNotFoundException("Não temos nenhum vendedor "
          . "com o código {$sellerID} cadastrado"
        );
      }
      $seller = $seller
        ->first()
        ->toArray()
      ;

      // Agora recupera as informações das suas unidades/filiais
      $seller['subsidiaries'] = Subsidiary::join("cities",
            "subsidiaries.cityid", '=', "cities.cityid"
          )
        ->join("documenttypes", "subsidiaries.regionaldocumenttype",
            '=', "documenttypes.documenttypeid"
          )
        ->where("entityid", $sellerID)
        ->orderBy("subsidiaryid")
        ->get([
            'subsidiaries.*',
            'documenttypes.name as regionaldocumenttypename',
            'cities.name as cityname',
            'cities.state as state'
          ])
        ->toArray()
      ;

      // Por último, para cada unidade/filial, recupera as informações
      // de telefones, e-mails e contatos adicionais
      foreach ($seller['subsidiaries'] as $row => $subsidiary) {
        // Telefones
        $phones = $this
          ->getPhones($sellerID,
              $subsidiary['subsidiaryid']
            )
        ;
        if ( $phones->isEmpty() ) {
          // Criamos os dados de telefone em branco
          $seller['subsidiaries'][$row]['phones'] = [
            [
              'phoneid' => 0,
              'phonetypeid' => 1,
              'phonenumber' => ''
            ]
          ];
        } else {
          $seller['subsidiaries'][$row]['phones'] =
            $phones ->toArray()
          ;
        }

        // E-mails
        $emails = $this
          ->getEmails($sellerID,
              $subsidiary['subsidiaryid']
            )
        ;
        if ( $emails->isEmpty() ) {
          // Criamos os dados de e-mail em branco
          $seller['subsidiaries'][$row]['emails'] = [
            [
              'mailingid' => 0,
              'email' => ''
            ]
          ];
        } else {
          $seller['subsidiaries'][$row]['emails'] =
            $emails ->toArray()
          ;
        }

        // Contatos adicionais
        $contacts = $this
          ->getContacts($contractor->id, $sellerID,
              $subsidiary['subsidiaryid']
            )
        ;
        if ( !$contacts->isEmpty() ) {
          $seller['subsidiaries'][$row]['contacts'] =
            $contacts->toArray()
          ;
        }
      }
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar o vendedor código "
        . "{sellerID}.",
        [ 'sellerID' => $sellerID ]
      );

      // Alerta o usuário
      $this->flash("error", "Não foi possível localizar este "
        . "vendedor."
      );

      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [ 'routeName' => 'ERP\Cadastre\Sellers' ]
      );

      // Redireciona para a página de gerenciamento de vendedores
      return $this->redirect($response, 'ERP\Cadastre\Sellers');
    }

    // Verifica se estamos modificando os dados
    if ($request->isPut()) {
      // Os dados estão sendo modificados

      // Registra o acesso
      $this->debug("Processando à edição do vendedor '{name}'.",
        [ 'name' => $seller['name'] ]
      );

      // Monta uma matriz para validação dos tipos de documentos
      $regionalDocumentTypes = [ ];
      foreach ($documentTypes as $documentType) {
        $regionalDocumentTypes[ $documentType->id ] =
          $documentType->name
        ;
      }

      // Valida os dados
      $this->validator->validate($request,
        $this->getValidationRules(false)
      );

      if ($this->validator->isValid()) {
        $this->debug('Os dados do vendedor são VÁLIDOS');

        // Recupera os dados modificados do vendedor
        $sellerData = $this->validator->getValues();

        // Verifica se as inscrições estaduais são válidas. Inicialmente
        // considera todas válidas e, durante a análise, se alguma não
        // for válida, então registra qual delas está incorreta
        $allHasValid = true;
        foreach($sellerData['subsidiaries']
          as $subsidiaryNumber => $subsidiary) {
          // Recupera o tipo de documento
          $documentTypeID = $subsidiary['regionaldocumenttype'];

          // Se o tipo de documento for 'Inscrição Estadual' precisa
          // verificar se o valor informado é válido
          if (
            $regionalDocumentTypes[ $documentTypeID ]
            === 'Inscrição Estadual'
          ) {
            try {
              if (strlen($subsidiary['regionaldocumentnumber']) > 0) {
                // Verifica se a UF foi informada
                if ( (strtolower($subsidiary['regionaldocumentnumber'])
                      !== 'isento')
                    && (empty($subsidiary['regionaldocumentstate'])) ) {
                  // Invalida o formulário
                  $allHasValid = false;

                  // Seta o erro neste campo
                  $this->validator->setErrors([
                      'regionaldocumentstate' => 'UF precisa ser '
                        . 'preenchido(a)'
                    ],
                    "subsidiaries[{$subsidiaryNumber}][regionaldocumentstate]")
                  ;
                } else {
                  // Verifica se a inscrição estadual é válida
                  if ( !(StateRegistration::isValid(
                          $subsidiary['regionaldocumentnumber'],
                          $subsidiary['regionaldocumentstate']
                        )) ) {
                    // Invalida o formulário
                    $allHasValid = false;

                    // Seta o erro neste campo
                    $this->validator->setErrors([
                        'stateRegistration' =>
                          'A inscrição estadual não é válida'
                      ],
                      "subsidiaries[{$subsidiaryNumber}][regionaldocumentnumber]")
                    ;
                  }
                }
              }
            }
            catch(InvalidArgumentException $exception) {
              // Ocorreu uma exceção, então invalida o formulário
              $allHasValid = false;

              // Seta o erro neste campo
              $this->validator->setErrors([
                  'state' => $exception->getMessage()
                ],
                "subsidiaries[{$subsidiaryNumber}][regionaldocumentnumber]")
              ;
            }
          }

          if (array_key_exists('contacts', $subsidiary)) {
            // Verifica se os contatos adicionais contém ao menos uma
            // informação de contato, seja o telefone ou o e-mail
            foreach ($subsidiary['contacts']
              as $contactNumber => $contactData) {
              // Verifica se foi fornecido um telefone ou e-mail
              if ( empty($contactData['email']) &&
                   empty($contactData['phonenumber']) ) {
                // Invalida o formulário
                $allHasValid = false;

                // Seta o erro nestes campos
                $this->validator->setErrors([
                    'email' => "Informe um e-mail ou telefone para "
                      . "contato"
                  ],
                  "subsidiaries[{$subsidiaryNumber}][contacts][{$contactNumber}][email]")
                ;
                $this->validator->setErrors([
                    'phonenumber' => "Informe um e-mail ou telefone para "
                      . "contato"
                  ],
                  "subsidiaries[{$subsidiaryNumber}][contacts][{$contactNumber}][phonenumber]")
                ;
              }
            }
          }
        }

        if ($allHasValid) {
          try
          {
            // Iniciamos a transação
            $this->DB->beginTransaction();

            // Separamos as informações das unidades/filiais do
            // restante dos dados do vendedor
            $subsidiariesData = $sellerData['subsidiaries'];
            unset($sellerData['subsidiaries']);

            // Não permite modificar o tipo de entidade nem a informação
            // de que o mesmo é vendedor
            unset($sellerData['entitytypeid']);
            unset($sellerData['seller']);

            // ================================[ Unidades/Filiais ]=====
            // Recupera as informações das unidades/filiais e separa os
            // dados para as operações de inserção, atualização e
            // remoção.
            // =========================================================

            // -------------------------------[ Pré-processamento ]-----

            // Analisa as unidades/filiais informadas, de forma a
            // separar quais unidades precisam ser adicionadas,
            // removidas e atualizadas

            // Matrizes que armazenarão os dados das unidades/filiais a
            // serem adicionados, atualizados e removidos
            $newSubsidiaries = [ ];
            $updSubsidiaries = [ ];
            $delSubsidiaries = [ ];

            // Os IDs das unidades/filiais mantidos para permitir
            // determinar as unidades/filiais a serem removidas
            $heldSubsidiaries = [ ];

            // Determina quais unidades serão mantidas (e atualizadas)
            // e as que precisam ser adicionadas (novas)
            foreach ($subsidiariesData as $subsidiary) {
              if (empty($subsidiary['subsidiaryid'])) {
                // Unidade/filial nova
                $newSubsidiaries[] = $subsidiary;
              } else {
                // Unidade/filial existente
                $heldSubsidiaries[] = $subsidiary['subsidiaryid'];
                $updSubsidiaries[]  = $subsidiary;
              }
            }

            // Recupera as unidades/filiais armazenadas atualmente
            $subsidiaries = Subsidiary::where("entityid", $sellerID)
              ->get([
                  'subsidiaryid'
                ])
              ->toArray()
            ;
            $oldSubsidiaries = [ ];
            foreach ($subsidiaries as $subsidiary) {
              $oldSubsidiaries[] = $subsidiary['subsidiaryid'];
            }

            // Verifica quais as unidades/filiais estavam na base de
            // dados e precisam ser removidas
            $delSubsidiaries = array_diff(
              $oldSubsidiaries, $heldSubsidiaries
            );

            // ----------------------------------------[ Gravação ]-----

            // Grava as informações do vendedor
            $sellerChanged = Seller::findOrFail($sellerID);
            $sellerChanged->fill($sellerData);
            $sellerChanged->updatedbyuserid =
              $this->authorization->getUser()->userid
            ;
            $sellerChanged->save();

            // Primeiro apagamos as unidades/filiais removidas pelo
            // usuário durante a edição
            foreach ($delSubsidiaries as $subsidiaryID) {
              // Apaga cada unidade/filial e seus respectivos contatos
              $subsidiary = Subsidiary::findOrFail($subsidiaryID);
              $subsidiary->delete();
            }

            // Agora inserimos as novas unidades/filiais
            foreach ($newSubsidiaries as $subsidiaryData) {
              // Separamos as informações dos dados de telefones dos
              // demais dados desta unidade/filial
              $phonesData =
                $subsidiaryData['phones']
              ;
              unset($subsidiaryData['phones']);

              // Separamos as informações dos dados de emails dos demais
              // dados desta unidade/filial
              $emailsData =
                $subsidiaryData['emails']
              ;
              unset($subsidiaryData['emails']);

              // Separamos as informações dos dados de contatos
              // adicionais dos demais dados desta unidade/filial
              if (array_key_exists('contacts', $subsidiaryData)) {
                $contactsData =
                  $subsidiaryData['contacts']
                ;
                unset($subsidiaryData['contacts']);
              } else {
                $contactsData = [];
              }

              // Sempre mantém a UF do documento em maiúscula
              $subsidiaryData['regionaldocumentstate'] =
                strtoupper($subsidiaryData['regionaldocumentstate'])
              ;

              // Retiramos o campo de ID da unidade/filial, pois os
              // dados tratam de um novo registro
              unset($subsidiaryData['subsidiaryid']);

              // Incluímos a nova unidade/filial
              $subsidiary = new Subsidiary();
              $subsidiary->fill($subsidiaryData);
              $subsidiary->entityid = $sellerID;
              $subsidiary->createdbyuserid =
                $this->authorization->getUser()->userid
              ;
              $subsidiary->updatedbyuserid =
                $this->authorization->getUser()->userid
              ;
              $subsidiary->save();
              $subsidiaryID = $subsidiary->subsidiaryid;

              // Incluímos os dados de telefones para esta
              // unidade/filial
              foreach($phonesData as $phoneData) {
                // Retiramos o campo de ID do telefone, pois os dados
                // tratam de um novo registro
                unset($phoneData['phoneid']);

                // Incluímos um novo telefone desta unidade/filial
                $phone = new Phone();
                $phone->fill($phoneData);
                $phone->entityid     = $sellerID;
                $phone->subsidiaryid = $subsidiaryID;
                $phone->save();
              }

              // Incluímos os dados de emails para esta
              // unidade/filial
              foreach($emailsData as $emailData) {
                // Retiramos o campo de ID do e-mail, pois os dados
                // tratam de um novo registro
                unset($emailData['mailingid']);

                // Como podemos não ter um endereço de e-mail, então
                // ignora caso ele não tenha sido fornecido
                if (trim($emailData['email']) !== '') {
                  // Incluímos um novo e-mail desta unidade/filial
                  $mailing = new Mailing();
                  $mailing->fill($emailData);
                  $mailing->entityid     = $sellerID;
                  $mailing->subsidiaryid = $subsidiaryID;
                  $mailing->save();
                }
              }

              // Incluímos os dados de contatos adicionais para esta
              // unidade/filial
              foreach($contactsData as $contactData) {
                // Retiramos o campo de ID do contato, pois os dados
                // tratam de um novo registro
                unset($contactData['mailingaddressid']);

                // Incluímos um novo contato desta unidade/filial
                $mailingAddress = new MailingAddress();
                $mailingAddress->fill($contactData);
                $mailingAddress->entityid        = $sellerID;
                $mailingAddress->subsidiaryid    = $subsidiaryID;
                $mailingAddress->createdbyuserid =
                  $this->authorization->getUser()->userid
                ;
                $mailingAddress->updatedbyuserid =
                  $this->authorization->getUser()->userid
                ;
                $mailingAddress->save();
              }
            }

            // Por último, modificamos as unidades/filiais mantidas
            foreach($updSubsidiaries as $subsidiaryData) {
              // Retiramos o campo de ID da unidade/filial
              $subsidiaryID = $subsidiaryData['subsidiaryid'];
              unset($subsidiaryData['subsidiaryid']);
              unset($subsidiaryData['entityid']);

              // Sempre mantém a UF do documento em maiúscula
              $subsidiaryData['regionaldocumentstate'] =
                strtoupper($subsidiaryData['regionaldocumentstate'])
              ;

              // Separamos as informações dos dados de telefones dos
              // demais dados desta unidade/filial
              $phonesData = $subsidiaryData['phones'];
              unset($subsidiaryData['phones']);

              // Separamos as informações dos dados de emails dos demais
              // dados desta unidade/filial
              $emailsData =
                $subsidiaryData['emails']
              ;
              unset($subsidiaryData['emails']);

              // Separamos as informações dos dados de contatos
              // adicionais dos demais dados desta unidade/filial
              $contactsData = $subsidiaryData['contacts'];
              unset($subsidiaryData['contacts']);

              // Grava as alterações dos dados da unidade/filial
              $subsidiary = Subsidiary::findOrFail($subsidiaryID);
              $subsidiary->fill($subsidiaryData);
              $subsidiary->updatedbyuserid =
                $this->authorization->getUser()->userid
              ;
              $subsidiary->save();

              // =====================================[ Telefones ]=====
              // Recupera as informações de telefones desta unidade e
              // separa os dados para as operações de inserção,
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
              foreach ($phonesData as $phoneData) {
                if (empty($phoneData['phoneid'])) {
                  // Telefone novo
                  unset($phoneData['phoneid']);
                  $newPhones[] = $phoneData;
                } else {
                  // Telefone existente
                  $heldPhones[] = $phoneData['phoneid'];
                  $updPhones[]  = $phoneData;
                }
              }

              // Recupera os telefones armazenados atualmente
              $currentPhones = Phone::where('subsidiaryid', $subsidiaryID)
                ->get(['phoneid'])
                ->toArray()
              ;
              $actPhones = [ ];
              foreach ($currentPhones as $phoneData) {
                $actPhones[] = $phoneData['phoneid'];
              }

              // Verifica quais os telefones estavam na base de dados e
              // precisam ser removidos
              $delPhones = array_diff($actPhones, $heldPhones);

              // --------------------------------------[ Gravação ]-----

              // Primeiro apagamos os telefones removidos pelo usuário
              // durante a edição
              foreach ($delPhones as $phoneID) {
                // Apaga cada telefone
                $phone = Phone::findOrFail($phoneID);
                $phone->delete();
              }

              // Agora inserimos os novos telefones
              foreach ($newPhones as $phoneData) {
                // Incluímos um novo telefone nesta unidade/filial
                unset($phoneData['phoneid']);
                $phone = new Phone();
                $phone->fill($phoneData);
                $phone->entityid     = $sellerID;
                $phone->subsidiaryid = $subsidiaryID;
                $phone->save();
              }

              // Por último, modificamos os telefones mantidos
              foreach($updPhones as $phoneData) {
                // Retira a ID do contato
                $phoneID = $phoneData['phoneid'];
                unset($phoneData['phoneid']);

                // Por segurança, nunca permite modificar qual a ID da
                // entidade mãe
                unset($phoneData['entityid']);
                unset($phoneData['subsidiaryid']);

                // Grava as informações do telefone
                $phone = Phone::findOrFail($phoneID);
                $phone->fill($phoneData);
                $phone->save();
              }

              // =======================================[ E-mails ]=====
              // Recupera as informações de e-mails desta unidade e
              // separa os dados para as operações de inserção,
              // atualização e remoção dos mesmos.
              // =======================================================

              // -----------------------------[ Pré-processamento ]-----

              // Matrizes que armazenarão os dados dos e-mails a serem
              // adicionados, atualizados e removidos
              $newEmails = [ ];
              $updEmails = [ ];
              $delEmails = [ ];

              // Os IDs dos e-mails mantidos para permitir determinar
              // àqueles a serem removidos
              $heldEmails = [ ];

              // Determina quais e-mails serão mantidos (e atualizados)
              // e os que precisam ser adicionados (novos)
              foreach ($emailsData as $emailData) {
                // Ignora se o e-mail não contiver conteúdo
                if (trim($emailData['email']) === '') {
                  continue;
                }

                if (empty($emailData['mailingid'])) {
                  // E-mail novo
                  unset($emailData['mailingid']);
                  $newEmails[] = $emailData;
                } else {
                  // E-mail existente
                  $heldEmails[] = $emailData['mailingid'];
                  $updEmails[]  = $emailData;
                }
              }

              // Recupera os e-mails armazenados atualmente
              $currentEmails = Mailing::where('subsidiaryid', $subsidiaryID)
                ->get(['mailingid'])
                ->toArray()
              ;
              $actEmails = [ ];
              foreach ($currentEmails as $emailData) {
                $actEmails[] = $emailData['mailingid'];
              }

              // Verifica quais os e-mails estavam na base de dados e
              // precisam ser removidos
              $delEmails = array_diff($actEmails, $heldEmails);

              // --------------------------------------[ Gravação ]-----

              // Primeiro apagamos os e-mails removidos pelo usuário
              // durante a edição
              foreach ($delEmails as $emailID) {
                // Apaga cada e-mail
                $mailing = Mailing::findOrFail($emailID);
                $mailing->delete();
              }

              // Agora inserimos os novos e-mails
              foreach ($newEmails as $emailData) {
                // Incluímos um novo e-mail nesta unidade/filial
                unset($emailData['mailingid']);
                $mailing = new Mailing();
                $mailing->fill($emailData);
                $mailing->entityid     = $sellerID;
                $mailing->subsidiaryid = $subsidiaryID;
                $mailing->save();
              }

              // Por último, modificamos os e-mails mantidos
              foreach($updEmails as $emailData) {
                // Retira a ID do contato
                $emailID = $emailData['mailingid'];
                unset($emailData['mailingid']);

                // Por segurança, nunca permite modificar qual a ID da
                // entidade mãe
                unset($emailData['entityid']);
                unset($emailData['subsidiaryid']);

                // Grava as informações do e-mail
                $mailing = Mailing::findOrFail($emailID);
                $mailing->fill($emailData);
                $mailing->save();
              }

              // ===========================[ Contatos Adicionais ]=====
              // Recupera as informações de contatos adicionais desta
              // unidade e separa-os para as operações de inserção,
              // atualização e remoção dos mesmos.
              // =======================================================

              // -----------------------------[ Pré-processamento ]-----

              // Matrizes que armazenarão os dados dos contatos
              // adicionais a serem adicionados, atualizados e removidos
              $newContacts = [ ];
              $updContacts = [ ];
              $delContacts = [ ];

              // Os IDs dos contatos mantidos para permitir determinar
              // àqueles a serem removidos
              $heldContacts = [ ];

              // Determina quais contatos serão mantidos (e atualizadas)
              // e àqueles que precisam ser adicionados (novos)
              foreach ($contactsData as $contactData) {
                if (empty($contactData['mailingaddressid'])) {
                  // Contato novo
                  unset($contactData['mailingaddressid']);
                  $newContacts[] = $contactData;
                } else {
                  // Contato existente
                  $heldContacts[] = $contactData['mailingaddressid'];
                  $updContacts[]  = $contactData;
                }
              }

              // Recupera os contatos armazenados atualmente
              $currentContacts = MailingAddress::where('subsidiaryid', $subsidiaryID)
                ->get(['mailingaddressid'])
                ->toArray()
              ;
              $actContacts = [ ];
              foreach ($currentContacts as $contactData) {
                $actContacts[] = $contactData['mailingaddressid'];
              }

              // Verifica quais os contatos estavam na base de dados e
              // precisam ser removidos
              $delContacts = array_diff($actContacts, $heldContacts);

              // --------------------------------------[ Gravação ]-----

              // Primeiro apagamos os contatos removidos pelo usuário
              // durante a edição
              foreach ($delContacts as $mailingAddressID) {
                // Apaga cada contato
                $mailingAddress =
                  MailingAddress::findOrFail($mailingAddressID)
                ;
                $mailingAddress->delete();
              }

              // Agora inserimos os novos contatos
              foreach ($newContacts as $contactData) {
                // Incluímos um novo contato nesta unidade/filial
                unset($contactData['mailingaddressid']);
                $mailingAddress = new MailingAddress();
                $mailingAddress->fill($contactData);
                $mailingAddress->entityid     = $sellerID;
                $mailingAddress->subsidiaryid = $subsidiaryID;
                $mailingAddress->createdbyuserid =
                  $this->authorization->getUser()->userid
                ;
                $mailingAddress->updatedbyuserid =
                  $this->authorization->getUser()->userid
                ;
                $mailingAddress->save();
              }

              // Por último, modificamos os contatos mantidos
              foreach($updContacts as $contactData) {
                // Retira a ID do contato
                $mailingAddressID = $contactData['mailingaddressid'];
                unset($contactData['mailingaddressid']);

                // Por segurança, nunca permite modificar qual a ID da
                // entidade mãe
                unset($contactData['entityid']);
                unset($contactData['subsidiaryid']);

                // Grava as informações do contato
                $mailingAddress = MailingAddress::findOrFail(
                  $mailingAddressID
                );
                $mailingAddress->fill($contactData);
                $mailingAddress->updatedbyuserid =
                  $this->authorization->getUser()->userid
                ;
                $mailingAddress->save();
              }
            }

            // =========================================================

            // Efetiva a transação
            $this->DB->commit();

            // Registra o sucesso
            $this->info("O vendedor '{name}' foi modificado com "
              . "sucesso.",
              [ 'name' => $sellerData['name'] ]
            );

            // Alerta o usuário
            $this->flash("success", "O vendedor <i>'{name}'</i> foi "
              . "modificado com sucesso.",
              [ 'name' => $sellerData['name'] ]
            );

            // Registra o evento
            $this->debug("Redirecionando para {routeName}",
              [ 'routeName' => 'ERP\Cadastre\Sellers' ]
            );

            // Redireciona para a página de gerenciamento de
            // vendedores
            return $this->redirect($response,
              'ERP\Cadastre\Sellers')
            ;
          }
          catch(Exception $exception)
          {
            // Reverte (desfaz) a transação
            $this->DB->rollBack();

            // Registra o erro
            $this->error("Não foi possível modificar as informações do "
              . "vendedor '{name}'. Erro interno: {error}",
              [ 'name'  => $sellerData['name'],
                'error' => $exception->getMessage() ]
            );

            // Alerta o usuário
            $this->flashNow("error", "Não foi possível modificar as "
              . "informações do vendedor. Erro interno."
            );
          }
        }
      } else {
        $this->debug('Os dados do vendedor são INVÁLIDOS');
        $messages = $this->validator->getFormatedErrors();
        foreach ($messages AS $message) {
          $this->debug($message);
        }
      }
    } else {
      // Carrega os dados atuais
      $this->validator->setValues($seller);
    }

    // Exibe um formulário para edição de um vendedor

    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ERP\Home')
    );
    $this->breadcrumb->push('Cadastro', '');
    $this->breadcrumb->push('Vendedores',
      $this->path('ERP\Cadastre\Sellers')
    );
    $this->breadcrumb->push('Editar',
      $this->path('ERP\Cadastre\Sellers\Edit', [
        'sellerID' => $sellerID
      ])
    );

    // Registra o acesso
    $this->info("Acesso à edição do vendedor '{name}'.",
      [ 'name' => $seller['name'] ]
    );

    // Renderiza a página
    return $this->render($request, $response,
      'erp/cadastre/sellers/seller.twig',
      [ 'formMethod' => 'PUT',
        'documentTypes' => $documentTypes,
        'genders' => $genders,
        'maritalStatus' => $maritalStatus,
        'phoneTypes' => $phoneTypes,
        'mailingProfiles' => $mailingProfiles,
        'defaultMailingProfileID' => $defaultMailingProfileID ])
    ;
  }

  /**
   * Remove o vendedor.
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
    // Registra o acesso
    $this->debug("Processando à remoção de vendedor.");

    // Recupera o ID
    $sellerID = $args['sellerID'];

    try
    {
      // Recupera as informações do vendedor
      $seller = Seller::findOrFail($sellerID);

      // Recupera o local de armazenamento das logomarcas
      $logoDirectory =
        $this->container['settings']['storage']['images']
      ;

      // Agora apaga o vendedor

      // Iniciamos a transação
      $this->DB->beginTransaction();

      // TODO: Não devemos mais apagar, mas sim marcar como excluído os
      //       registros, facilitando o processamento
      // Remove o cliente e suas unidades/filiais e dados recursivamente
      $seller->deleteCascade($logoDirectory);

      // Efetiva a transação
      $this->DB->commit();

      // Registra o sucesso
      $this->info("O vendedor '{name}' foi removido com sucesso.",
        [ 'name' => $seller->name ]
      );

      // Informa que a remoção foi realizada com sucesso
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getParams(),
            'message' => "Removido o vendedor {$seller->name}",
            'data' => "Delete"
          ])
      ;
    }
    catch(ModelNotFoundException $exception)
    {
      // Reverte (desfaz) a transação
      $this->DB->rollBack();

      // Registra o erro
      $this->error("Não foi possível localizar o vendedor código "
        . "{sellerID} para remoção.",
        [ 'sellerID' => $sellerID ]
      );

      $message = "Não foi possível localizar o vendedor para "
        . "remoção."
      ;
    }
    catch(QueryException $exception)
    {
      // Reverte (desfaz) a transação
      $this->DB->rollBack();

      // Registra o erro
      $this->error("Não foi possível remover as informações do "
        . "vendedor '{name}'. Erro interno no banco de dados: "
        . "{error}",
        [ 'name'  => $seller->name,
          'error' => $exception->getMessage() ]
      );

      $message = "Não foi possível remover o vendedor. Erro interno "
        . "no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Reverte (desfaz) a transação
      $this->DB->rollBack();

      // Registra o erro
      $this->error("Não foi possível remover as informações do "
        . "vendedor '{name}'. Erro interno: {error}",
        [ 'name'  => $seller->name,
          'error' => $exception->getMessage() ]
      );

      $message = "Não foi possível remover o vendedor. Erro interno.";
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
   * Alterna o estado do bloqueio de um vendedor e/ou de uma
   * unidade/filial deste vendedor.
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
    $this->debug("Processando à mudança do estado de bloqueio de "
      . "vendedor."
    );

    // Recupera o ID
    $sellerID = $args['sellerID'];
    $subsidiaryID = $args['subsidiaryID'];

    try
    {
      // Recupera as informações do vendedor
      if (is_null($subsidiaryID)) {
        // Desbloqueia o vendedor
        $seller = Seller::findOrFail($sellerID);
        $action   = $seller->blocked
          ? "desbloqueado"
          : "bloqueado"
        ;
        $seller->blocked = !$seller->blocked;
        $seller->updatedbyuserid = $this
          ->authorization
          ->getUser()
          ->userid
        ;
        $seller->save();

        $message = "O vendedor '{$seller->name}' foi {$action} "
          . "com sucesso."
        ;
      } else {
        // Desbloqueia a unidade/filial
        $seller   = Seller::findOrFail($sellerID);
        $subsidiary = Subsidiary::findOrFail($subsidiaryID);
        $action     = $subsidiary->blocked
          ? "desbloqueada"
          : "bloqueada"
        ;
        $subsidiary->blocked = !$subsidiary->blocked;
        $subsidiary->updatedbyuserid =
          $this->authorization->getUser()->userid
        ;
        $subsidiary->save();

        $message = "A unidade/filial '{$subsidiary->name}' do "
          . "vendedor '{$seller->name}' foi {$action} com sucesso."
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
          ])
      ;
    }
    catch(ModelNotFoundException $exception)
    {
      if (is_null($subsidiaryID)) {
        // Registra o erro
        $this->error("Não foi possível localizar o vendedor código "
          . "{sellerID} para alternar o estado do bloqueio.",
          [ 'sellerID' => $sellerID ])
        ;

        $message = "Não foi possível localizar o vendedor para "
          . "alternar o estado do bloqueio."
        ;
      } else {
        // Registra o erro
        $this->error("Não foi possível localizar a unidade/filial "
          . "código {subsidiaryID} do vendedor código {sellerID} "
          . "para alternar o estado do bloqueio.",
          [ 'sellerID' => $sellerID,
            'subsidiaryID' => $subsidiaryID ])
        ;

        $message = "Não foi possível localizar a unidade/filial do "
          . "vendedor para alternar o estado do bloqueio."
        ;
      }
    }
    catch(QueryException $exception)
    {
      if (is_null($subsidiaryID)) {
        // Registra o erro
        $this->error("Não foi possível alternar o estado do bloqueio "
          . "do vendedor '{name}'. Erro interno no banco de dados: "
          . "{error}.",
          [ 'name'  => $seller->name,
            'error' => $exception->getMessage() ]
        );

        $message = "Não foi possível alternar o estado do bloqueio do "
          . "vendedor. Erro interno no banco de dados."
        ;
      } else {
        // Registra o erro
        $this->error("Não foi possível alternar o estado do bloqueio "
          . "da unidade/filial {subsidiaryName} do vendedor "
          . "'{name}'. Erro interno no banco de dados: {error}.",
          [ 'subsidiaryName'  => $subsidiary->name,
            'name'  => $seller->name,
            'error' => $exception->getMessage() ]
        );

        $message = "Não foi possível alternar o estado do bloqueio da "
          . "unidade/filial do vendedor. Erro interno no banco de "
          . "dados."
        ;
      }
    }
    catch(Exception $exception)
    {
      if (is_null($subsidiaryID)) {
        // Registra o erro
        $this->error("Não foi possível alternar o estado do bloqueio "
          . "do vendedor '{name}'. Erro interno: {error}.",
          [ 'name'  => $seller->name,
            'error' => $exception->getMessage() ]
        );

        $message = "Não foi possível alternar o estado do bloqueio do "
          . "vendedor. Erro interno."
        ;
      } else {
        // Registra o erro
        $this->error("Não foi possível alternar o estado do bloqueio "
          . "da unidade/filial {subsidiaryName} do vendedor "
          . "'{name}'. Erro interno: {error}.",
          [ 'subsidiaryName'  => $subsidiary->name,
            'name'  => $seller->name,
            'error' => $exception->getMessage() ]
        );

        $message = "Não foi possível alternar o estado do bloqueio da "
          . "unidade/filial do vendedor. Erro interno no banco de "
          . "dados."
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
        ])
    ;
  }

  /**
   * Gera um PDF para impressão das informações de um vendedor.
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
    $this->debug("Processando à geração de PDF com as informações "
      . "cadastrais de um vendedor."
    );

    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();

    // Recupera as informações do vendedor
    $sellerID = $args['sellerID'];
    $seller = Seller::join("entitiestypes", "entities.entitytypeid",
          '=', "entitiestypes.entitytypeid"
        )
      ->join("users as createduser", "entities.createdbyuserid",
          '=', "createduser.userid"
        )
      ->join("users as updateduser", "entities.updatedbyuserid",
          '=', "updateduser.userid"
        )
      ->where("entities.seller", "true")
      ->where("entities.entityid", $sellerID)
      ->where("entities.contractorid", '=', $contractor->id)
      ->get([
          'entitiestypes.name as entitytypename',
          'entitiestypes.juridicalperson',
          'entities.*',
          'createduser.name as createdbyusername',
          'updateduser.name as updatedbyusername'
        ])
      ->first()
      ->toArray()
    ;

    // Agora recupera as informações das suas unidades/filiais
    $subsidiaryQry = Subsidiary::join("cities",
          "subsidiaries.cityid", '=', "cities.cityid"
        )
      ->join("documenttypes", "subsidiaries.regionaldocumenttype",
          '=', "documenttypes.documenttypeid"
        )
      ->leftJoin("maritalstatus", "subsidiaries.maritalstatusid",
          '=', "maritalstatus.maritalstatusid"
        )
      ->leftJoin("genders", "subsidiaries.genderid",
          '=', "genders.genderid"
        )
      ->where("entityid", $sellerID)
    ;

    if (array_key_exists('subsidiaryID', $args)) {
      // Recupera apenas a unidade/filial informada
      $subsidiaryID = $args['subsidiaryID'];
      $subsidiaryQry
        ->where('subsidiaryid', $subsidiaryID)
      ;
    }

    $seller['subsidiaries'] = $subsidiaryQry
      ->orderBy('headoffice', 'DESC')
      ->orderBy('name', 'ASC')
      ->get([
          'subsidiaries.*',
          'documenttypes.name as regionaldocumentname',
          'maritalstatus.name as maritalstatusname',
          'genders.name as gendername',
          'cities.name as cityname',
          'cities.state as state'
        ])
      ->toArray()
    ;

    // Para cada unidade/filial, recupera as informações de telefones,
    // e-mails e contatos adicionais
    foreach ($seller['subsidiaries'] as $row => $subsidiary) {
      // Telefones
      $phones = $this
        ->getPhones($sellerID,
            $subsidiary['subsidiaryid']
          )
      ;
      if ( $phones->isEmpty() ) {
        // Criamos os dados de telefone em branco
        $seller['subsidiaries'][$row]['phones'] = [
          [
            'phoneid' => 0,
            'phonetypeid' => 1,
            'phonenumber' => ''
          ]
        ];
      } else {
        $seller['subsidiaries'][$row]['phones'] =
          $phones ->toArray()
        ;
      }

      // E-mails
      $emails = $this
        ->getEmails($sellerID,
            $subsidiary['subsidiaryid']
          )
      ;
      if ( $emails->isEmpty() ) {
        // Criamos os dados de e-mail em branco
        $seller['subsidiaries'][$row]['emails'] = [
          [
            'mailingid' => 0,
            'email' => ''
          ]
        ];
      } else {
        $seller['subsidiaries'][$row]['emails'] =
          $emails ->toArray()
        ;
      }

      // Contatos adicionais
      $contacts = $this
        ->getContacts($contractor->id, $sellerID,
            $subsidiary['subsidiaryid']
          )
      ;
      if ( !$contacts->isEmpty() ) {
        $seller['subsidiaries'][$row]['contacts'] =
          $contacts->toArray()
        ;
      }
    }

    // Renderiza a página para poder converter em PDF
    $title = "Dados cadastrais de vendedor";
    $PDFFileName = "Seller_ID_{$sellerID}.pdf";
    $page = $this->renderPDF(
      'erp/cadastre/sellers/PDFseller.twig',
      [
        'seller' => $seller
      ]
    );

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
    $mpdf->SetSubject('Controle de vendedores');
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

    // Envia o PDF para o browser no modo Inline
    $stream = fopen('php://memory','r+');
    ob_start();
    $mpdf->Output($PDFFileName,'I');
    $pdfData = ob_get_contents();
    ob_end_clean();
    fwrite($stream, $pdfData);
    rewind($stream);

    // Registra o acesso
    $this->info("Acesso ao PDF com as informações cadastrais do "
      . "vendedor '{name}'.",
      [ 'name' => $seller['name'] ]
    );

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
}
