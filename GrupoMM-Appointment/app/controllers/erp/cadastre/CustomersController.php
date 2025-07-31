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
 * O controlador do gerenciamento dos clientes do sistema. Um cliente
 * pode ser uma pessoa física e/ou jurídica.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Controllers\ERP\Cadastre;

use App\Models\Affiliation;
use App\Models\Contract;
use App\Models\DocumentType;
use App\Models\EmergencyPhone;
use App\Models\Entity as Customer;
use App\Models\Entity as MonitoringCompany;
use App\Models\Entity as RapidResponseCompany;
use App\Models\EntityType;
use App\Models\Gender;
use App\Models\Mailing;
use App\Models\MailingAddress;
use App\Models\MailingProfile;
use App\Models\MaritalStatus;
use App\Models\Phone;
use App\Models\PhoneType;
use App\Models\Subsidiary;
use App\Models\User;
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

class CustomersController
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
   * @param bool $step
   *   O passo dentro do formulário
   *
   * @return array
   */
  protected function getValidationRules(
    bool $addition = false,
    int $step = 0
  ): array
  {
    if ($step == 1) {
      $validationRules = [
        'nationalregister' => V::oneOf(
            V::notEmpty()->cpf(),
            V::notEmpty()->cnpj()
          )->setName('CPF/CNPJ'),
        'relationshipid' => V::notEmpty()
          ->intVal()
          ->between(1, 3)
          ->setName('Tipo de relacionamento')
      ];

      return $validationRules;
    }

    $validationRules = [
      'entityid' => V::notBlank()
        ->intVal()
        ->setName('ID do Cliente'),
      'name' => V::notBlank()
        ->length(2, 100)
        ->setName('Cliente'),
      'tradingname' => V::optional(
            V::notBlank()
              ->length(2, 100)
          )
        ->setName('Nome fantasia/apelido'),
      'entitytypeid' => V::notBlank()
        ->intVal()
        ->setName('Tipo de cliente'),
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
            )
          ->setName('CPF/CNPJ'),
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
            )
          ->setName('Bairro'),
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
        ->setName('Observação'),
      'enableatmonitoring' => V::boolVal()
        ->setName('Habilitar os veículos deste cliente na Central de Monitoramento'),
      'monitoringid' => V::optional(
            V::notBlank()
              ->intval()
          )
        ->setName('Empresa de monitoramento'),
      'noteformonitoring' => V::optional(
            V::notBlank()
          )
        ->setName('Avisos/notas para a central de monitoramento'),
      'emergencyinstructions' => V::optional(
            V::notBlank()
          )
        ->setName('Instruções para situação de emergência'),
      'dispatchrapidresponse' => V::optional(
            V::boolVal()
          )
        ->setName('Acionar pronta-resposta'),
      'rapidresponseid' => V::optional(
            V::notBlank()
              ->intval()
          )
        ->setName('Empresa de pronta-resposta'),
      'securitypassword' => V::optional(
            V::notBlank()
              ->length(1, 100)
          )
        ->setName('Senha de segurança'),
      'verificationpassword' => V::optional(
            V::notBlank()
              ->length(1, 100)
          )
        ->setName('Contra-senha'),
    ];

    if ($addition) {
      // Ajusta as regras para adição
      unset($validationRules['entityid']);
      unset($validationRules['subsidiaries']['subsidiaryid']);
      unset($validationRules['subsidiaries']['phones']['phoneid']);
      unset($validationRules['subsidiaries']['emails']['mailingid']);
      unset($validationRules['subsidiaries']['contacts']['mailingaddressid']);
      $validationRules['step'] = V::notBlank()
        ->intVal()
        ->setName('Passo no processo')
      ;
      $validationRules['relationshipid'] = V::notBlank()
        ->intVal()
        ->setName('Tipo de relacionamento')
      ;
    } else {
      // Ajusta as regras para edição
      $validationRules['blocked'] = V::boolVal()
        ->setName('Bloquear este cliente e todas suas '
            . 'unidades/filiais'
          )
      ;
    }

    return $validationRules;
  }

  /**
   * Recupera as informações dos relacionamentos existentes de um
   * cliente, ou seja, se ele é cliente direto e se ele contratou
   * serviços de uma associação.
   *
   * @param int $customerID
   *   O ID do cliente
   * @param int $entityTypeID
   *   O ID do tipo de entidade
   * 
   * @throws RuntimeException
   *   Em caso de não termos tipos de entidades
   * 
   * @return array
   *   A matriz com as informações de relacionamentos.
   */
  protected function getRelationships(
    int $customerID,
    int $entityTypeID
  ): array
  {
    // Result irá conter os tipos de relacionamentos, a saber:
    //   1: Contratos existentes com este cliente
    //   2: Quantidade de associados (se associação)
    //   3: Associações as quais está vinculado
    $relationships = [
      1 => [],
      2 => [],
      3 => []
    ];

    // Recupera as informações de contrato existentes
    $contracts = Contract::where('customerid', '=', $customerID)
      ->get([
          'contractid as id',
          $this->DB->raw('getContractNumber(createdat) AS number'),
          'signaturedate',
          'enddate',
          $this->DB->raw('enddate IS NULL AS active'),
          'monthprice'
        ])
    ;
    if ( !$contracts->isEmpty() ) {
      $contracts = $contracts
        ->toArray()
      ;
      $relationships[1] = $contracts;
    }

    if ($entityTypeID == 3) {
      // É uma associação, então apenas recupera a quantidade de
      // associados ativos
      $numberOfAssociates = Affiliation::where('associationid', '=',
            $customerID
          )
        ->whereNull('unjoinedat')
        ->count()
      ;

      $relationships[2] = [
        'numberOfAssociates' => $numberOfAssociates
      ];
    } else {
      // Recupera as informações de vínculos com associações
      $affiliations = Affiliation::join('entities',
            'affiliations.associationid', '=', 'entities.entityid'
          )
        ->where('affiliations.customerid', '=', $customerID)
        ->get([
            $this->DB->raw(''
              . 'CASE '
              .   'WHEN entities.tradingname IS NULL THEN entities.name '
              .   'ELSE entities.tradingname '
              . 'END AS name'),
            'affiliations.joinedat',
            $this->DB->raw('unjoinedat IS NULL AS active'),
            'affiliations.unjoinedat',
          ])
      ;
      if ( !$affiliations->isEmpty() ) {
        $affiliations = $affiliations
          ->toArray()
        ;
        $relationships[3] = $affiliations;
      }
    }

    return $relationships;
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
    } catch (Exception $exception) {
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
   * @param bool $juridicalPerson
   *   O indicativo se os documentos a serem recuperados é de pessoa
   *   jurídica
   *
   * @throws RuntimeException
   *   Em caso de não termos tipos de documentos
   * 
   * @return Collection
   *   A matriz com as informações de tipos de documentos
   */
  protected function getDocumentTypes(
    bool $juridicalPerson
  ): Collection
  {
    try {
      // Recupera as informações de tipos de documentos
      $juridicalPerson = ($juridicalPerson == true);
      $documentTypes = DocumentType::orderBy('documenttypeid')
        ->where('juridicalperson', '=', $juridicalPerson)
        ->get([
            'documenttypeid as id',
            'name'
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
    } catch (Exception $exception) {
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
    } catch (Exception $exception) {
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
    } catch (Exception $exception) {
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
    } catch (Exception $exception) {
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
      $defaultMailingProfile = MailingProfile::leftJoin(
            'actionsperprofiles',
            function ($join) {
              $join->on('mailingprofiles.mailingprofileid', '=',
                'actionsperprofiles.mailingprofileid'
              );
              $join->on('mailingprofiles.contractorid', '=',
                'actionsperprofiles.contractorid'
              );
            }
          )
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
    } catch (Exception $exception) {
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
   * Recupera as informações de empresas de monitoramento.
   *
   * @throws RuntimeException
   *   Em caso de não termos empresas de monitoramento
   * 
   * @return Collection
   *   A matriz com as informações de empresas de monitoramento
   */
  protected function getMonitoringCompanies(): Collection
  {
    try {
      // Recupera as informações de empresas de monitoramento
      $monitoringCompanies = MonitoringCompany::orderBy('name')
        ->where('monitor', '=', true)
        ->get([
            'entityid as id',
            'name'
          ])
      ;

      if ( $monitoringCompanies->isEmpty() ) {
        throw new Exception("Não temos nenhuma empresa de "
          . "monitoramento cadastrada"
        );
      }
    }
    catch (Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível obter as informações de empresas "
        . "de monitoramento. Erro: {error}.",
        [ 'error' => $exception->getMessage() ]
      );

      throw new RuntimeException("Não foi possível obter as empresas "
        . "de monitoramento"
      );
    }

    return $monitoringCompanies;
  }

  /**
   * Recupera as informações de empresas de pronta-resposta.
   *
   * @throws RuntimeException
   *   Em caso de não termos empresas de pronta-resposta
   * 
   * @return Collection
   *   A matriz com as informações de empresas de pronta-resposta
   */
  protected function getRapidResponseCompanies(): Collection
  {
    try {
      // Recupera as informações de empresas de pronta-resposta
      $rapidResponseCompanies = RapidResponseCompany::orderBy('name')
        ->where('rapidresponse', '=', true)
        ->get([
            'entityid as id',
            'name'
          ])
      ;
    }
    catch (Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível obter as informações de empresas "
        . "de pronta-resposta. Erro: {error}.",
        [ 'error' => $exception->getMessage() ]
      );

      throw new RuntimeException("Não foi possível obter as empresas "
        . "de pronta-resposta"
      );
    }

    return $rapidResponseCompanies;
  }

  /**
   * Recupera as informações de números de telefones de uma
   * unidade/filial/titular/associado.
   *
   * @param int $customerID
   *   A ID do cliente para o qual desejamos obter esta informação
   * @param int $subsidiaryID
   *   A ID da unidade/filial deste cliente para o qual desejamos obter
   *   os dados de telefones disponíveis
   *
   * @throws RuntimeException
   *   Em caso de erros
   *
   * @return Collection
   *   A matriz com as informações de números de telefones
   */
  protected function getPhones(
    int $customerID,
    int $subsidiaryID
  ): Collection
  {
    // Recupera as informações de números de telefones
    return Phone::join('phonetypes',
          'phones.phonetypeid', '=', 'phonetypes.phonetypeid'
        )
      ->where('entityid', $customerID)
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
   * Recupera as informações de números de telefones de emergência de um
   * cliente.
   *
   * @param int $customerID
   *   A ID do cliente para o qual desejamos obter esta informação
   *
   * @throws RuntimeException
   *   Em caso de erros
   *
   * @return Collection
   *   A matriz com as informações de números de telefones
   */
  protected function getEmergencyPhones(
    int $customerID
  ): Collection
  {
    // Recupera as informações de números de telefones
    return EmergencyPhone::join('phonetypes',
          'emergencyphones.phonetypeid', '=', 'phonetypes.phonetypeid'
        )
      ->where('entityid', $customerID)
      ->get([
          'emergencyphones.entityid',
          'emergencyphones.emergencyphoneid',
          'emergencyphones.phonetypeid',
          'phonetypes.name as phonetypename',
          'emergencyphones.phonenumber'
        ])
    ;
  }

  /**
   * Recupera as informações de e-mails de uma unidade/filial/titular ou
   * associado.
   *
   * @param int $customerID
   *   A ID do cliente para o qual desejamos obter esta informação
   * @param int $subsidiaryID
   *   A ID da unidade/filial deste cliente para o qual desejamos obter
   *   os dados de e-mails disponíveis
   *
   * @throws RuntimeException
   *   Em caso de erros
   *
   * @return Collection
   *   A matriz com as informações de e-mails
   */
  protected function getEmails(
    int $customerID,
    int $subsidiaryID
  ): Collection
  {
    // Recupera as informações de dados de e-mail
    return Mailing::where('entityid', $customerID)
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
   *   A ID do contratante deste cliente
   * @param int $customerID
   *   A ID do cliente para o qual desejamos obter esta informação
   * @param int $subsidiaryID
   *   A ID da unidade/filial deste cliente para o qual desejamos obter
   *   os dados de contato disponíveis
   *
   * @throws RuntimeException
   *   Em caso de erros
   *
   * @return Collection
   *   A matriz com as informações de dados de contatos adicionais
   */
  protected function getContacts(
    int $contractorID,
    int $customerID,
    int $subsidiaryID
  ): Collection
  {
    // Recupera as informações de contatos adicionais
    return MailingAddress::join('phonetypes',
          'mailingaddresses.phonetypeid', '=', 'phonetypes.phonetypeid'
        )
      ->join('mailingprofiles',
          function ($join) use ($contractorID) {
            $join
              ->on('mailingaddresses.mailingprofileid', '=',
                  'mailingprofiles.mailingprofileid'
                )
              ->where('mailingprofiles.contractorid', '=',
                  $contractorID
                )
            ;
          }
        )
      ->where('entityid', $customerID)
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
   * Exibe a página inicial do gerenciamento de clientes.
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
    $this->breadcrumb->push('Clientes',
      $this->path('ERP\Cadastre\Customers')
    );

    // Registra o acesso
    $this->info("Acesso ao gerenciamento de clientes.");

    // Recupera os dados da sessão
    $customer = $this->session->get('customer',
      [
        'searchField' => 'name',
        'searchValue' => '',
        'filter' => [
          'type' => 0
        ],
        'displayStart' => 0
      ]
    );

    $filters = [
      [ 'id' => 0,
        'name' => 'Todos clientes (ativos e inativos)'
      ],
      [ 'id' => 1,
        'name' => 'Clientes e associados ativos'
      ],
      [ 'id' => 2,
        'name' => 'Apenas clientes ativos'
      ],
      [ 'id' => 3,
        'name' => 'Apenas associados ativos'
      ],
      [ 'id' => 4,
        'name' => 'Apenas clientes inativos'
      ],
      [ 'id' => 5,
        'name' => 'Apenas associados inativos'
      ]
    ];

    // Renderiza a página
    return $this->render($request, $response,
      'erp/cadastre/customers/customers.twig',
      [
        'customer' => $customer,
        'filters' => $filters
      ]
    );
  }

  /**
   * Recupera a relação dos clientes em formato JSON.
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
    $this->debug("Acesso à relação de clientes.");

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

    $filterType = intval($request->getParam('filterType', 1));

    // Seta os valores da última pesquisa na sessão
    $this->session->set(
      'customer',
      [
        'searchField' => $searchField,
        'searchValue' => $searchValue,
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
        $entityID = $this->authorization->getUser()->entityid;
      } else {
        $entityID = 0;
      }

      // O filtro de elementos
      $Fstatus = 0;
      $Ftype   = 0;
      switch ($filterType) {
        case 1:
          // Clientes e associados ativos
          $Fstatus = 2;

          break;
        case 2:
          // Apenas clientes ativos
          $Fstatus = 2;
          $Ftype   = 1;

          break;
        case 3:
          // Apenas associados ativos
          $Fstatus = 2;
          $Ftype   = 2;

          break;
        case 4:
          // Apenas clientes inativos
          $Fstatus = 1;
          $Ftype   = 1;

          break;
        case 5:
          // Apenas associados inativos
          $Fstatus = 1;
          $Ftype   = 2;

          break;
        default:
          // code...
          break;
      }

      // Realiza a consulta
      $sql = "SELECT E.entityID as id,
                     E.subsidiaryID,
                     E.affiliatedID,
                     E.juridicalperson,
                     E.cooperative,
                     E.headOffice,
                     E.type,
                     E.level,
                     E.hasRelationship,
                     E.activeRelationship AS active,
                     E.activeAssociation,
                     E.name,
                     E.tradingname,
                     E.blocked,
                     E.cityname,
                     E.nationalregister,
                     E.blockedlevel,
                     0 as delete,
                     E.createdat,
                     E.fullcount
                FROM erp.getEntitiesData({$contractor->id}, {$entityID},
                  'customer', '{$searchValue}', '{$searchField}',
                  NULL, {$Fstatus}, {$Ftype}, {$start},
                  {$length}) as E;"
      ;
      $customers = $this->DB->select($sql);

      if (count($customers) > 0) {
        $rowCount = $customers[0]->fullcount;

        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'draw' => $draw,
              'recordsTotal' => $rowCount,
              'recordsFiltered' => $rowCount,
              'data' => $customers
            ])
        ;
      } else {
        if (empty($searchValue)) {
          $error = "Não temos clientes cadastrados.";
        } else {
          switch ($searchField) {
            case 'subsidiaryname':
              $fieldLabel = 'nome da unidade/filial/titular/associado';

              break;
            case 'nationalregister':
              $fieldLabel = 'CPF/CNPJ da unidade/filial/titular/associado';

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
          $error = "Não temos clientes cadastrados cujo {$fieldLabel} "
            . "contém <i>{$searchValue}</i>."
          ;
        }
      }
    } catch(QueryException $exception) {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno no banco de dados: {error}.",
        [
          'module' => 'clientes',
          'error'  => $exception->getMessage()
        ]
      );

      $error = "Não foi possível recuperar as informações de clientes. "
        . "Erro interno no banco de dados."
      ;
    } catch(Exception $exception) {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno: {error}.",
        [
          'module' => 'clientes',
          'error'  => $exception->getMessage()
        ]
      );

      $error = "Não foi possível recuperar as informações de clientes. "
        . "Erro interno."
      ;
    }

    return $response
      ->withHeader('Content-type', 'application/json')
      ->withJson([
          'draw' => $draw,
          'recordsTotal' => 0,
          'recordsFiltered' => 0,
          'data' => [],
          'error' => $error
        ])
    ;
  }

  /**
   * Exibe um formulário para adição de um cliente, quando solicitado,
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
    // Determina o passo em que estamos
    $step = intval($request->getParam('step', 1));

    // Os tipos de documentos, inicialmente, são vazios e são
    // posteriomente determinados em função do tipo de cliente
    $documentTypes = [];

    try {
      // Recupera os dados do contratante
      $contractor = $this->authorization->getContractor();

      // Recupera as informações de tipos de entidades
      $entityTypes = $this->getEntitiesTypes();

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

      // Recupera as informações de empresas de monitoramento
      $monitoringCompanies = $this->getMonitoringCompanies();

      // Recupera as informações de empresas de pronta-resposta
      $rapidResponseCompanies = $this->getRapidResponseCompanies();

      $relationshipTypes = [
        [ 'id' => 1,
          'name' => 'Cliente direto',
          'description' => 'Possui um contrato estabelecido e são '
            . 'emitidas cobranças diretas pelos serviços prestados.'
        ],
        [ 'id' => 2,
          'name' => 'Associação',
          'description' => 'Possui um contrato estabelecido, mas os '
            . 'serviços são prestados aos seus associados. São '
            . 'emitidas cobranças pela prestação do serviço aos '
            . 'respectivos associados.'
        ],
        [ 'id' => 3,
          'name' => 'Associado',
          'description' => 'Cliente para o qual são prestados '
            . 'serviços pelo fato dele possuir vínculo a um cliente '
            . 'que é uma associação e não são emitidas cobranças '
            . 'pelos serviços prestados.' ]
      ];
    }
    catch (RuntimeException $exception)
    {
      // Alerta o usuário
      $this->flash("error", $exception->getMessage());

      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [ 'routeName' => 'ERP\Cadastre\Customers' ]
      );

      // Redireciona para a página de gerenciamento de clientes
      return $this->redirect($response, 'ERP\Cadastre\Customers');
    }

    // Verifica se estamos postando os dados
    if ($request->isPost()) {
      // Os dados estão sendo postados

      // Registra o acesso
      $this->debug("Processando à adição de cliente.");

      // Valida os dados
      $this->validator->validate($request,
        $this->getValidationRules(true, $step)
      );

      if ($this->validator->isValid()) {
        $this->debug('Os dados do cliente são VÁLIDOS');

        // Recupera os dados do cliente
        $customerData = $this->validator->getValues();
        $this->debug("Estamos no {$step}º passo.");

        switch ($step) {
          case 1:
            // Entrada do CPF/CNPJ do cliente e definição do tipo de
            // cliente
            $nationalRegister = $customerData['nationalregister'];
            $juridicalperson = (strlen($nationalRegister)==18)
              ? true
              : false
            ;
            $relationshipID = $customerData['relationshipid'];
            switch ($relationshipID) {
              case 2:
                // Associação
                $entityTypeID = 3;

                break;
              default:
                if ($juridicalperson) {
                  $entityTypeID = 1;
                } else {
                  $entityTypeID = 2;
                }
                
                break;
            }
            $defaultDocumentTypeID = ($juridicalperson)
              ? 4
              : 1
            ;

            // Avança para o próximo passo
            $step++;
            $this->debug("Avançamos para o {$step}º passo.");

            // Verificamos se o cliente já está cadastrado
            $customer = Customer::join("subsidiaries",
                  "entities.entityid", '=', "subsidiaries.entityid"
                )
              ->where("entities.customer", "true")
              ->where("entities.contractorid", '=',
                  $contractor->id
                )
              ->where("subsidiaries.nationalregister",
                  $nationalRegister
                )
              ->get([
                  'entities.entityid AS id'
                ])
            ;

            if ( $customer->isEmpty() ) {
              // É um novo cliente, então inicia um formulário em branco
              // com os valores pré-preenchidos dele

              // Recupera as informações de tipos de documentos
              $documentTypes = $this->getDocumentTypes($juridicalperson);

              // Define o tipo de cliente
              $this->validator->setValues([
                'entityid' => 0,
                'entitytypeid' => $entityTypeID,
                'juridicalperson' => $juridicalperson,
                'relationshipid' => $relationshipID,
                'subsidiaries' => [
                  0 => [
                    'subsidiaryid' => 0,
                    'headoffice' => true,
                    'name' => 'Matriz',
                    'regionaldocumenttype' => $defaultDocumentTypeID,
                    'nationalregister' => $nationalRegister,
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
                ],
                'enableatmonitoring' => false,
                'monitoringid' => '',
                'noteformonitoring' => '',
                'emergencyinstructions' => '',
                'dispatchrapidresponse' => false,
                'rapidresponseid' => '',
                'securitypassword' => '',
                'verificationpassword' => ''
              ]);
            } else {
              // Este cliente já está cadastrado, então transfere para
              // a edição do cliente e informa esta situação

              // Alerta o usuário
              switch ($customerData['relationshipid']) {
                case 1:
                  // Cliente direto
                  $message = "Este cliente já está cadastrado.";
                  $complement = "crie um novo contrato para ele, se "
                    . "necessário, e adicione novos veículos "
                    . "normalmente."
                  ;

                  break;
                case 2:
                  // Associação
                  $message = "Esta associação já está cadastrada.";
                  $complement = "crie um novo contrato para ela, se "
                    . "necessário, e adicione os seus respectivos "
                    . "associados."
                  ;
                  
                  break;
                
                default:
                  // Associação
                  $message = "Este associado já está cadastrado.";
                  $complement = "cadastre o veículo e efetue o vínculo "
                    . "do equipamento de rastreamento."
                  ;

                  break;
              }

              $this->flash(
                "info",
                "{$message} Apenas revise os seus dados cadastrais e "
                . "{$complement}."
              );

              // Registra o evento
              $this->debug("Redirecionando para {routeName}",
                [ 'routeName' => 'ERP\Cadastre\Customers\Edit' ]
              );

              // Redireciona para a página de gerenciamento de clientes
              return $this->redirect(
                $response,
                'ERP\Cadastre\Customers\Edit',
                [ 'customerID' => $customer[0]->id ]
              );
            }
            
            break;
          default:
            // Entrada dos demais dados do cliente

            // Recupera as informações de tipos de documentos
            $documentTypes = $this->getDocumentTypes(
              $customerData['juridicalperson']
            );

            // Monta uma matriz para validação dos tipos de documentos
            $regionalDocumentTypes = [ ];
            foreach ($documentTypes as $documentType) {
              $regionalDocumentTypes[ $documentType->id ] =
                $documentType->name
              ;
            }

            // Verifica os dados de cada unidade (filial ou dependente)
            $allHasValid = true;
            foreach($customerData['subsidiaries']
              as $subsidiaryNumber => $subsidiary) {
              // Recupera o tipo de documento
              $documentTypeID = $subsidiary['regionaldocumenttype'];

              // Se o tipo de documento for 'Inscrição Estadual' precisa
              // verificar se o valor informado é válido
              if ( array_key_exists(
                    $documentTypeID, $regionalDocumentTypes
                   ) ) {
                if ( $regionalDocumentTypes[ $documentTypeID ]
                     === 'Inscrição Estadual') {
                  try {
                    if (strlen($subsidiary['regionaldocumentnumber']) > 0) {
                      // Verifica se a UF foi informada
                      if ((strtolower($subsidiary['regionaldocumentnumber']) !== 'isento')
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

            if ($customerData['enableatmonitoring'] == "true") {
              // Valida as informações de monitoramento
              if (empty($customerData['monitoringid'])) {
                // Invalida o formulário
                $allHasValid = false;
    
                // Seta o erro neste campo
                $this->validator->setErrors(
                  [
                    'monitoringid' => "Informe uma empresa de monitoramento"
                  ],
                  "monitoringid"
                );
              }
    
              if ($customerData['dispatchrapidresponse'] == "true") {
                if (empty($customerData['rapidresponseid'])) {
                  // Invalida o formulário
                  $allHasValid = false;
      
                  // Seta o erro neste campo
                  $this->validator->setErrors(
                    [
                      'rapidresponseid' => "Informe uma empresa de pronta-resposta"
                    ],
                    "rapidresponseid"
                  );
                }
              }

              if (!empty($customerData['securitypassword'])) {
                if (empty($customerData['verificationpassword'])) {
                  // Invalida o formulário
                  $allHasValid = false;
    
                  // Seta o erro neste campo
                  $this->validator->setErrors(
                    [
                      'verificationpassword' => "Informe uma contra-senha para a pergunta da senha de segurança"
                    ],
                    "verificationpassword"
                  );
                }
              }
            }
    
            if ($allHasValid) {
              // Os dados do cliente são considerados válidos

              try
              {
                // Grava o novo cliente

                // Separamos as informações das unidades/filiais do
                // restante dos dados do cliente
                $subsidiariesData = $customerData['subsidiaries'];
                unset($customerData['subsidiaries']);

                // Separamos os telefones de emergência do restante dos
                // dados do cliente
                $emergencyPhonesData = $customerData['emergencyphones'];
                unset($customerData['emergencyphones']);

                // Iniciamos a transação
                $this->DB->beginTransaction();

                $phoneToUserAccount = '';
                $emailToUserAccount = '';

                // Incluímos um novo cliente
                $customer = new Customer();
                $customer->fill($customerData);
                // Indicamos que é um cliente
                $customer->customer = true;
                // Adicionamos as demais informações
                $customer->contractorid = $contractor->id;
                $customer->createdbyuserid =
                  $this->authorization->getUser()->userid
                ;
                $customer->updatedbyuserid =
                  $this->authorization->getUser()->userid
                ;
                $customer->save();
                $customerID = $customer->entityid;

                // Incluímos todas unidades/filiais deste cliente
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
                  $subsidiary->entityid = $customerID;
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
                    // Retiramos o campo de ID do telefone, pois os
                    // dados tratam de um novo registro
                    unset($phoneData['phoneid']);

                    if ($phoneData['phonetypeid'] == 2 && empty($phoneToUserAccount)) {
                      // Guarda o telefone celular para o usuário
                      $phoneToUserAccount = $phoneData['phonenumber'];
                    }

                    // Incluímos um novo telefone desta unidade/filial
                    $phone = new Phone();
                    $phone->fill($phoneData);
                    $phone->entityid = $customerID;
                    $phone->subsidiaryid = $subsidiaryID;
                    $phone->save();
                  }

                  if (empty($phoneToUserAccount) && count($phonesData) > 0) {
                    // Se não foi informado um telefone celular, então
                    // pega o primeiro telefone informado
                    $phoneToUserAccount = $phonesData[0]['phonenumber'];
                  }

                  // Incluímos os dados de emails para esta
                  // unidade/filial
                  foreach($emailsData as $emailData) {
                    // Retiramos o campo de ID do e-mail, pois os dados
                    // tratam de um novo registro
                    unset($emailData['mailingid']);

                    if (empty($emailToUserAccount)) {
                      // Guarda o e-mail para o usuário
                      $emailToUserAccount = $emailData['email'];
                    }

                    // Como podemos não ter um endereço de e-mail, então
                    // ignora caso ele não tenha sido fornecido
                    if (trim($emailData['email']) !== '') {
                      // Incluímos um novo e-mail desta unidade/filial
                      $mailing = new Mailing();
                      $mailing->fill($emailData);
                      $mailing->entityid     = $customerID;
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
                    $mailingAddress->entityid = $customerID;
                    $mailingAddress->subsidiaryid = $subsidiaryID;
                    $mailingAddress->createdbyuserid =
                      $this->authorization->getUser()->userid
                    ;
                    $mailingAddress->updatedbyuserid =
                      $this->authorization->getUser()->userid
                    ;
                    $mailingAddress->save();
                  }
                }

                // Incluímos os dados de telefones de emergência para
                // este cliente
                foreach($emergencyPhonesData as $emergencyPhoneData) {
                  if (empty($emergencyPhoneData['phonenumber'])) {
                    continue;
                  }
                  // Retiramos o campo de ID do telefone, pois os dados
                  // tratam de um novo registro
                  unset($emergencyPhoneData['emergencyphoneid']);

                  // Incluímos um novo telefone desta unidade/filial
                  $emergencyPhone = new EmergencyPhone();
                  $emergencyPhone->fill($emergencyPhoneData);
                  $emergencyPhone->entityid = $customerID;
                  $emergencyPhone->save();
                }

                // Cria um novo usuário para este cliente usando o CPF
                // ou CNPJ como login
                $user = new User();
                $user->contractorid = $contractor->id;
                $user->name = $customerData['name'];
                $user->role = 'Cliente';
                $user->phonenumber = $phoneToUserAccount;
                $user->entityid = $customerID;
                $user->username = preg_replace(
                  "/[^0-9]/", "",
                  $subsidiariesData[0]['nationalregister']
                );
                $user->groupid = 6;
                $user->email = $emailToUserAccount;
                $user->modules = '{commands}';
                $user->forcenewpassword = true;
                $plainPassword = '#rastro23';
                switch ($contractor->id) {
                  case 2530:
                    $plainPassword = '#fat2024';

                    break;
                  case 7:
                    $plainPassword = '#raster23';

                    break;
                  default:
                    // Mantém o padrão
                    break;
                }
                $password = $this
                  ->authorization
                  ->getHashedPassword($plainPassword)
                ;
                $user->password = $password;
                $user->expires = false;
                $user->save();

                // Efetiva a transação
                $this->DB->commit();

                // Registra o sucesso
                $this->info("Cadastrado o cliente '{name}' com "
                  . "sucesso.",
                  [ 'name'  => $customerData['name'] ]
                );

                // Alerta o usuário
                $this->flash("success", "O cliente <i>'{name}'</i> foi "
                  . "cadastrado com sucesso.",
                  [ 'name'  => $customerData['name'] ]
                );

                if ($customerData['relationshipid'] == 3) {
                  // Registra o evento
                  $this->debug("Redirecionando para {routeName}",
                    [ 'routeName' => 'ERP\Cadastre\Customers' ]
                  );

                  // Redireciona para a página de gerenciamento de clientes
                  return $this->redirect($response,
                    'ERP\Cadastre\Customers')
                  ;
                } else {
                  // Registra o evento
                  $this->debug("Redirecionando para {routeName}",
                    [ 'routeName' => 'ERP\Financial\Contracts\Add' ]
                  );

                  // Redireciona para a página de gerenciamento de
                  // clientes
                  return $this->redirect($response,
                    'ERP\Financial\Contracts\Add', [
                    'customerID' => $customerID ])
                  ;
                }
              } catch(QueryException $exception) {
                // Reverte (desfaz) a transação
                $this->DB->rollBack();

                // Registra o erro
                $this->error("Não foi possível inserir as informações do "
                  . "cliente '{name}'. Erro interno no banco de dados: "
                  . "{error}",
                  [ 'name'  => $customerData['name'],
                    'error' => $exception->getMessage() ]
                );

                // Alerta o usuário
                $this->flashNow("error", "Não foi possível inserir as "
                  . "informações do cliente. Erro interno no banco de "
                  . "dados."
                );
              } catch(Exception $exception) {
                // Reverte (desfaz) a transação
                $this->DB->rollBack();

                // Registra o erro
                $this->error("Não foi possível inserir as informações do "
                  . "cliente '{name}'. Erro interno: {error}",
                  [ 'name'  => $customerData['name'],
                    'error' => $exception->getMessage() ]
                );

                // Alerta o usuário
                $this->flashNow("error", "Não foi possível inserir as "
                  . "informações do cliente. Erro interno."
                );
              }

              // Recupera as informações de tipos de documentos em caso
              // de erros
              $juridicalperson =
                $request->getParam('juridicalperson')=="true"
              ;
              $documentTypes = $this->getDocumentTypes(
                $juridicalperson
              );
            }
            
            break;
        }
      } else {
        $this->debug('Os dados do cliente são INVÁLIDOS');
        $messages = $this->validator->getFormatedErrors();
        foreach ($messages AS $message) {
          $this->debug($message);
        }

        // Recupera as informações de tipos de documentos
        $juridicalperson = $request->getParam('juridicalperson')=="true";
        $documentTypes = $this->getDocumentTypes(
          $juridicalperson
        );
      }
    } else {
      // Carrega os dados iniciais para simplificar a digitação
      $this->validator->setValues([
        'relationshipid' => 1
      ]);
    }

    // Exibe um formulário para adição de um cliente

    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ERP\Home')
    );
    $this->breadcrumb->push('Cadastro', '');
    $this->breadcrumb->push('Clientes',
      $this->path('ERP\Cadastre\Customers')
    );
    $this->breadcrumb->push('Adicionar',
      $this->path('ERP\Cadastre\Customers\Add')
    );

    // Registra o acesso
    $this->info("Acesso à adição de cliente.");

    if ($step > 1) {
      $this->debug("Renderizando {$step}º passo.");
      return $this->render($request, $response,
        'erp/cadastre/customers/customer.twig',
        [
          'formMethod' => 'POST',
          'entityTypes' => $entityTypes,
          'documentTypes' => $documentTypes,
          'genders' => $genders,
          'maritalStatus' => $maritalStatus,
          'phoneTypes' => $phoneTypes,
          'mailingProfiles' => $mailingProfiles,
          'defaultMailingProfileID' => $defaultMailingProfileID,
          'monitoringCompanies' => $monitoringCompanies,
          'rapidResponseCompanies' => $rapidResponseCompanies,
        ]
      );
    } else {
      return $this->render($request, $response,
        'erp/cadastre/customers/newcustomer.twig',
        [
          'formMethod' => 'POST',
          'relationshipTypes' => $relationshipTypes
        ]
      );
    }
  }

  /**
   * Exibe um formulário para edição de um cliente, quando solicitado,
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

      // Recupera as informações de empresas de monitoramento
      $monitoringCompanies = $this->getMonitoringCompanies();

      // Recupera as informações de empresas de pronta-resposta
      $rapidResponseCompanies = $this->getRapidResponseCompanies();

    } catch (RuntimeException $exception) {
      // Alerta o usuário
      $this->flash("error", $exception->getMessage());

      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [ 'routeName' => 'ERP\Cadastre\Customers' ]
      );

      // Redireciona para a página de gerenciamento de clientes
      return $this->redirect($response, 'ERP\Cadastre\Customers');
    }

    try {
      // Recupera as informações do cliente
      $customerID = $args['customerID'];
      $customer = Customer::join("entitiestypes", "entities.entitytypeid",
            '=', "entitiestypes.entitytypeid"
          )
        ->join("users as createduser", "entities.createdbyuserid",
            '=', "createduser.userid"
          )
        ->join("users as updateduser", "entities.updatedbyuserid",
            '=', "updateduser.userid"
          )
        ->where("entities.customer", "true")
        ->where("entities.entityid", $customerID)
        ->where("entities.contractorid", '=', $contractor->id)
        ->get([
            'entitiestypes.name as entitytypename',
            'entitiestypes.juridicalperson',
            'entities.*',
            'createduser.name as createdbyusername',
            'updateduser.name as updatedbyusername'
          ])
      ;

      if ( $customer->isEmpty() ) {
        throw new ModelNotFoundException("Não temos nenhum cliente com "
          . "o código {$customerID} cadastrado"
        );
      }
      $customer = $customer
        ->first()
        ->toArray()
      ;

      // Recupera as informações de tipos de documentos em função do
      // tipo de pessoa (jurídica ou física)
      $documentTypes = $this->getDocumentTypes(
        $customer['juridicalperson']
      );

      // Recupera as informações dos relacionamentos existentes deste
      // cliente
      $customer['relationships'] = $this->getRelationships(
        $customerID, $customer['entitytypeid']
      );

      // Agora recupera as informações das suas unidades/filiais
      $customer['subsidiaries'] = Subsidiary::join("cities",
            "subsidiaries.cityid", '=', "cities.cityid"
          )
        ->join("documenttypes", "subsidiaries.regionaldocumenttype",
            '=', "documenttypes.documenttypeid"
          )
        ->where("entityid", $customerID)
        ->orderBy("subsidiaryid")
        ->get([
            'subsidiaries.*',
            'documenttypes.name as regionaldocumenttypename',
            'cities.name as cityname',
            'cities.state as state'
          ])
        ->toArray()
      ;

      // Para cada unidade/filial, recupera as informações de telefones,
      // e-mails e contatos adicionais
      foreach ($customer['subsidiaries'] as $row => $subsidiary) {
        // Telefones
        $phones = $this
          ->getPhones(
              $customerID,
              $subsidiary['subsidiaryid']
            )
        ;
        if ( $phones->isEmpty() ) {
          // Criamos os dados de telefone em branco
          $customer['subsidiaries'][$row]['phones'] = [
            [
              'phoneid' => 0,
              'phonetypeid' => 1,
              'phonenumber' => ''
            ]
          ];
        } else {
          $customer['subsidiaries'][$row]['phones'] =
            $phones ->toArray()
          ;
        }

        // E-mails
        $emails = $this
          ->getEmails(
              $customerID,
              $subsidiary['subsidiaryid']
            )
        ;
        if ( $emails->isEmpty() ) {
          // Criamos os dados de e-mail em branco
          $customer['subsidiaries'][$row]['emails'] = [
            [
              'mailingid' => 0,
              'email' => ''
            ]
          ];
        } else {
          $customer['subsidiaries'][$row]['emails'] =
            $emails ->toArray()
          ;
        }

        // Contatos adicionais
        $contacts = $this
          ->getContacts(
              $contractor->id,
              $customerID,
              $subsidiary['subsidiaryid']
            )
        ;
        if ( !$contacts->isEmpty() ) {
          $customer['subsidiaries'][$row]['contacts'] =
            $contacts->toArray()
          ;
        }
      }

      // Obtém as informações de telefones de emergência
      $customer['emergencyphones'] = $this->getEmergencyPhones($customerID);
      if ( $customer['emergencyphones']->isEmpty() ) {
        // Criamos os dados de telefone em branco
        $customer['emergencyphones'] = [
          [
            'phoneid' => 0,
            'phonetypeid' => 1,
            'phonenumber' => ''
          ]
        ];
      }
    } catch(ModelNotFoundException $exception) {
      // Registra o erro
      $this->error("Não foi possível localizar o cliente código "
        . "{customerID}.",
        [ 'customerID' => $customerID ]
      );

      // Alerta o usuário
      $this->flash("error", "Não foi possível localizar este cliente.");

      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [ 'routeName' => 'ERP\Cadastre\Customers' ]
      );

      // Redireciona para a página de gerenciamento de clientes
      return $this->redirect($response, 'ERP\Cadastre\Customers');
    }

    // Verifica se estamos modificando os dados
    if ($request->isPut()) {
      // Os dados estão sendo modificados

      // Registra o acesso
      $this->debug("Processando à edição do cliente '{name}'.",
        [ 'name' => $customer['name'] ]
      );

      // Monta uma matriz para validação dos tipos de documentos
      $regionalDocumentTypes = [ ];
      foreach ($documentTypes as $documentType) {
        $regionalDocumentTypes[ $documentType->id ] =
          $documentType->name
        ;
      }

      // Valida os dados
      $this->validator->validate(
        $request,
        $this->getValidationRules(false)
      );

      if ($this->validator->isValid()) {
        $this->debug('Os dados do cliente são VÁLIDOS');

        // Recupera os dados modificados do cliente
        $customerData = $this->validator->getValues();

        // Verifica se as inscrições estaduais são válidas. Inicialmente
        // considera todas válidas e, durante a análise, se alguma não
        // for válida, então registra qual delas está incorreta
        $allHasValid = true;
        foreach($customerData['subsidiaries']
          as $subsidiaryNumber => $subsidiary) {
          // Recupera o tipo de documento
          $documentTypeID = $subsidiary['regionaldocumenttype'];

          // Se o tipo de documento for 'Inscrição Estadual' precisa
          // verificar se o valor informado é válido
          if (
            $regionalDocumentTypes[$documentTypeID]
            === 'Inscrição Estadual'
          ) {
            try {
              if (strlen($subsidiary['regionaldocumentnumber']) > 0) {
                // Verifica se a UF foi informada
                if ((strtolower($subsidiary['regionaldocumentnumber'])
                    !== 'isento')
                  && (empty($subsidiary['regionaldocumentstate']))
                ) {
                  // Invalida o formulário
                  $allHasValid = false;

                  // Seta o erro neste campo
                  $this->validator->setErrors(
                    [
                      'regionaldocumentstate' => 'UF precisa ser '
                        . 'preenchido(a)'
                    ],
                    "subsidiaries[{$subsidiaryNumber}][regionaldocumentstate]"
                  );
                } else {
                  // Verifica se a inscrição estadual é válida
                  if (!(StateRegistration::isValid(
                    $subsidiary['regionaldocumentnumber'],
                    $subsidiary['regionaldocumentstate']
                  ))) {
                    // Invalida o formulário
                    $allHasValid = false;

                    // Seta o erro neste campo
                    $this->validator->setErrors(
                      [
                        'stateRegistration' =>
                        'A inscrição estadual não é válida'
                      ],
                      "subsidiaries[{$subsidiaryNumber}][regionaldocumentnumber]"
                    );
                  }
                }
              }
            } catch (InvalidArgumentException $exception) {
              // Ocorreu uma exceção, então invalida o formulário
              $allHasValid = false;

              // Seta o erro neste campo
              $this->validator->setErrors(
                [
                  'state' => $exception->getMessage()
                ],
                "subsidiaries[{$subsidiaryNumber}][regionaldocumentnumber]"
              );
            }
          }

          if (array_key_exists('contacts', $subsidiary)) {
            // Verifica se os contatos adicionais contém ao menos uma
            // informação de contato, seja o telefone ou o e-mail
            foreach ($subsidiary['contacts']
              as $contactNumber => $contactData) {
              // Verifica se foi fornecido um telefone ou e-mail
              if (
                empty($contactData['email']) &&
                empty($contactData['phonenumber'])
              ) {
                // Invalida o formulário
                $allHasValid = false;

                // Seta o erro nestes campos
                $this->validator->setErrors(
                  [
                    'email' => "Informe um e-mail ou telefone para "
                      . "contato"
                  ],
                  "subsidiaries[{$subsidiaryNumber}][contacts][{$contactNumber}][email]"
                );
                $this->validator->setErrors(
                  [
                    'phonenumber' => "Informe um e-mail ou telefone para "
                      . "contato"
                  ],
                  "subsidiaries[{$subsidiaryNumber}][contacts][{$contactNumber}][phonenumber]"
                );
              }
            }
          }
        }

        if ($customerData['enableatmonitoring'] == "true") {
          // Valida as informações de monitoramento
          if (empty($customerData['monitoringid'])) {
            // Invalida o formulário
            $allHasValid = false;

            // Seta o erro neste campo
            $this->validator->setErrors(
              [
                'monitoringid' => "Informe uma empresa de monitoramento"
              ],
              "monitoringid"
            );
          }

          if ($customerData['dispatchrapidresponse'] == "true") {
            if (empty($customerData['rapidresponseid'])) {
              // Invalida o formulário
              $allHasValid = false;

              // Seta o erro neste campo
              $this->validator->setErrors(
                [
                  'rapidresponseid' => "Informe uma empresa de pronta-resposta"
                ],
                "rapidresponseid"
              );
            }
          }

          if (!empty($customerData['securitypassword'])) {
            if (empty($customerData['verificationpassword'])) {
              // Invalida o formulário
              $allHasValid = false;

              // Seta o erro neste campo
              $this->validator->setErrors(
                [
                  'verificationpassword' => "Informe uma contra-senha para a pergunta da senha de segurança"
                ],
                "verificationpassword"
              );
            }
          }
        }

        if ($allHasValid) {
          try {
            // Iniciamos a transação
            $this->DB->beginTransaction();

            // Separamos as informações das unidades/filiais do
            // restante dos dados do cliente
            $subsidiariesData = $customerData['subsidiaries'];
            unset($customerData['subsidiaries']);

            // Separamos os telefones de emergência do restante dos
            // dados do cliente
            $emergencyPhonesData = $customerData['emergencyphones'];
            unset($customerData['emergencyphones']);

            // Não permite modificar o tipo de entidade nem a
            // informação de que o mesmo é cliente
            unset($customerData['entitytypeid']);
            unset($customerData['customer']);

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
            $newSubsidiaries = [];
            $updSubsidiaries = [];
            $delSubsidiaries = [];

            // Os IDs das unidades/filiais mantidos para permitir
            // determinar as unidades/filiais a serem removidas
            $heldSubsidiaries = [];

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
            $subsidiaries = Subsidiary::where("entityid", $customerID)
              ->get([
                  'subsidiaryid'
                ])
              ->toArray()
            ;
            $oldSubsidiaries = [];
            foreach ($subsidiaries as $subsidiary) {
              $oldSubsidiaries[] = $subsidiary['subsidiaryid'];
            }

            // Verifica quais as unidades/filiais estavam na base de
            // dados e precisam ser removidas
            $delSubsidiaries = array_diff(
              $oldSubsidiaries,
              $heldSubsidiaries
            );

            // ----------------------------------------[ Gravação ]-----

            // Grava as informações do cliente
            $customerChanged = Customer::findOrFail($customerID);
            $customerChanged->fill($customerData);
            $customerChanged->updatedbyuserid =
              $this->authorization->getUser()->userid
            ;
            $customerChanged->save();

            // Primeiro apagamos as unidades/filiais removidas pelo
            // usuário durante a edição
            foreach ($delSubsidiaries as $subsidiaryID) {
              // Apaga cada unidade/filial e seus respectivos contatos
              $subsidiary = Subsidiary::findOrFail($subsidiaryID);
              $subsidiary->deleteCascade();
            }

            // Agora inserimos as novas unidades/filiais
            foreach ($newSubsidiaries as $subsidiaryData) {
              // Separamos as informações dos dados de telefones dos
              // demais dados desta unidade/filial
              $phonesData = $subsidiaryData['phones'];
              unset($subsidiaryData['phones']);

              // Separamos as informações dos dados de emails dos demais
              // dados desta unidade/filial
              $emailsData = $subsidiaryData['emails'];
              unset($subsidiaryData['emails']);

              // Separamos as informações dos dados de contatos
              // adicionais dos demais dados desta unidade/filial
              if (array_key_exists('contacts', $subsidiaryData)) {
                $contactsData = $subsidiaryData['contacts'];
                unset($subsidiaryData['contacts']);
              } else {
                $contactsData = [];
              }

              // Sempre mantém a UF do documento em maiúscula
              $subsidiaryData['regionaldocumentstate'] = strtoupper(
                $subsidiaryData['regionaldocumentstate']
              );

              // Retiramos o campo de ID da unidade/filial, pois os
              // dados tratam de um novo registro
              unset($subsidiaryData['subsidiaryid']);

              // Incluímos a nova unidade/filial
              $subsidiary = new Subsidiary();
              if ($customer['entitytypeid'] == 2) {
                unset($subsidiaryData['personname']);
                unset($subsidiaryData['department']);
              } else {
                unset($subsidiaryData['birthday']);
                unset($subsidiaryData['age']);
                unset($subsidiaryData['maritalstatusid']);
                unset($subsidiaryData['genderid']);
              }
              $subsidiary->fill($subsidiaryData);
              $subsidiary->entityid = $customerID;
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
              foreach ($phonesData as $phoneData) {
                // Retiramos o campo de ID do telefone, pois os dados
                // tratam de um novo registro
                unset($phoneData['phoneid']);

                // Incluímos um novo telefone desta unidade/filial
                $phone = new Phone();
                $phone->fill($phoneData);
                $phone->entityid     = $customerID;
                $phone->subsidiaryid = $subsidiaryID;
                $phone->save();
              }

              // Incluímos os dados de emails para esta
              // unidade/filial
              foreach ($emailsData as $emailData) {
                // Retiramos o campo de ID do e-mail, pois os dados
                // tratam de um novo registro
                unset($emailData['mailingid']);

                // Como podemos não ter um endereço de e-mail, então
                // ignora caso ele não tenha sido fornecido
                if (trim($emailData['email']) !== '') {
                  // Incluímos um novo e-mail desta unidade/filial
                  $mailing = new Mailing();
                  $mailing->fill($emailData);
                  $mailing->entityid     = $customerID;
                  $mailing->subsidiaryid = $subsidiaryID;
                  $mailing->save();
                }
              }

              // Incluímos os dados de contatos adicionais para esta
              // unidade/filial
              foreach ($contactsData as $contactData) {
                // Retiramos o campo de ID do contato, pois os dados
                // tratam de um novo registro
                unset($contactData['mailingaddressid']);

                // Incluímos um novo contato desta unidade/filial
                $mailingAddress = new MailingAddress();
                $mailingAddress->fill($contactData);
                $mailingAddress->entityid        = $customerID;
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
            foreach ($updSubsidiaries as $subsidiaryData) {
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

              // Grava as alterações dos dados da unidade/filial
              $subsidiary = Subsidiary::findOrFail($subsidiaryID);
              if ($customer['entitytypeid'] == 2) {
                unset($subsidiaryData['personname']);
                unset($subsidiaryData['department']);
              } else {
                unset($subsidiaryData['birthday']);
                unset($subsidiaryData['age']);
                unset($subsidiaryData['maritalstatusid']);
                unset($subsidiaryData['genderid']);
              }
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
              $newPhones = [];
              $updPhones = [];
              $delPhones = [];

              // Os IDs dos telefones mantidos para permitir determinar
              // àqueles a serem removidos
              $heldPhones = [];

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
              $actPhones = [];
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
                $phone->entityid     = $customerID;
                $phone->subsidiaryid = $subsidiaryID;
                $phone->save();
              }

              // Por último, modificamos os telefones mantidos
              foreach ($updPhones as $phoneData) {
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
              $newEmails = [];
              $updEmails = [];
              $delEmails = [];

              // Os IDs dos e-mails mantidos para permitir determinar
              // àqueles a serem removidos
              $heldEmails = [];

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
              $actEmails = [];
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
                $mailing->entityid     = $customerID;
                $mailing->subsidiaryid = $subsidiaryID;
                $mailing->save();
              }

              // Por último, modificamos os e-mails mantidos
              foreach ($updEmails as $emailData) {
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
              $newContacts = [];
              $updContacts = [];
              $delContacts = [];

              // Os IDs dos contatos mantidos para permitir determinar
              // àqueles a serem removidos
              $heldContacts = [];

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
              $actContacts = [];
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
                $mailingAddress->entityid     = $customerID;
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
              foreach ($updContacts as $contactData) {
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

            // =========================[ Telefones de emergência ]=====
            // Recupera as informações de telefones de emergência deste
            // cliente e separa os dados para as operações de inserção,
            // atualização e remoção dos mesmos.
            // =========================================================

            // -------------------------------[ Pré-processamento ]-----

            // Matrizes que armazenarão os dados dos telefones a serem
            // adicionados, atualizados e removidos
            $newEmergencyPhones = [];
            $updEmergencyPhones = [];
            $delEmergencyPhones = [];

            // Os IDs dos telefones mantidos para permitir determinar
            // àqueles a serem removidos
            $heldEmergencyPhones = [];

            // Determina quais telefones serão mantidos (e atualizados)
            // e os que precisam ser adicionados (novos)
            foreach ($emergencyPhonesData as $emergencyPhoneData) {
              if (empty($emergencyPhoneData['phonenumber'])) {
                continue;
              }
              if (empty($emergencyPhoneData['emergencyphoneid'])) {
                // Telefone novo
                unset($emergencyPhoneData['emergencyphoneid']);
                $newEmergencyPhones[] = $emergencyPhoneData;
              } else {
                // Telefone existente
                $heldEmergencyPhones[] = $emergencyPhoneData['emergencyphoneid'];
                $updEmergencyPhones[]  = $emergencyPhoneData;
              }
            }

            // Recupera os telefones armazenados atualmente
            $currentEmergencyPhones = EmergencyPhone::where('entityid', $customerID)
              ->get(['emergencyphoneid'])
              ->toArray()
            ;
            $actEmergencyPhones = [];
            foreach ($currentEmergencyPhones as $emergencyPhoneData) {
              $actEmergencyPhones[] = $emergencyPhoneData['emergencyphoneid'];
            }

            // Verifica quais os telefones estavam na base de dados e
            // precisam ser removidos
            $delEmergencyPhones = array_diff($actEmergencyPhones, $heldEmergencyPhones);

            // --------------------------------------[ Gravação ]-----

            // Primeiro apagamos os telefones removidos pelo usuário
            // durante a edição
            foreach ($delEmergencyPhones as $emergencyPhoneID) {
              // Apaga cada telefone
              $emergencyPhone = EmergencyPhone::findOrFail($emergencyPhoneID);
              $emergencyPhone->delete();
            }

            // Agora inserimos os novos telefones
            foreach ($newEmergencyPhones as $emergencyPhoneData) {
              // Incluímos um novo telefone de emergência neste cliente
              unset($emergencyPhoneData['emergencyphoneid']);
              $emergencyPhone = new EmergencyPhone();
              $emergencyPhone->fill($emergencyPhoneData);
              $emergencyPhone->entityid = $customerID;
              $emergencyPhone->save();
            }

            // Por último, modificamos os telefones mantidos
            foreach ($updEmergencyPhones as $emergencyPhoneData) {
              // Retira a ID do número de telefone
              $emergencyPhoneID = $emergencyPhoneData['emergencyphoneid'];
              unset($emergencyPhoneData['emergencyphoneid']);

              // Por segurança, nunca permite modificar qual a ID da
              // entidade mãe
              unset($emergencyPhoneData['entityid']);

              // Grava as informações do telefone
              $emergencyPhone = EmergencyPhone::findOrFail($emergencyPhoneID);
              $emergencyPhone->fill($emergencyPhoneData);
              $emergencyPhone->save();
            }

            // =========================================================

            // Efetiva a transação
            $this->DB->commit();

            // Registra o sucesso
            $this->info("O cliente '{name}' foi modificado com "
              . "sucesso.",
              [ 'name' => $customerData['name'] ]
            );

            // Alerta o usuário
            $this->flash("success", "O cliente <i>'{name}'</i> foi "
              . "modificado com sucesso.",
              [ 'name' => $customerData['name'] ]
            );

            // Registra o evento
            $this->debug("Redirecionando para {routeName}",
              [ 'routeName' => 'ERP\Cadastre\Customers' ]
            );

            // Redireciona para a página de gerenciamento de clientes
            return $this->redirect($response,
              'ERP\Cadastre\Customers'
            );
          } catch(Exception $exception) {
            // Reverte (desfaz) a transação
            $this->DB->rollBack();

            // Registra o erro
            $this->error("Não foi possível modificar as informações "
              . "do cliente '{name}'. Erro interno: {error}",
              [ 'name'  => $customerData['name'],
                'error' => $exception->getMessage() ]
            );

            // Alerta o usuário
            $this->flashNow("error", "Não foi possível modificar as "
              . "informações do cliente. Erro interno."
            );
          }
        }
      } else {
        $this->debug('Os dados do cliente são INVÁLIDOS');
        $messages = $this->validator->getFormatedErrors();
        foreach ($messages AS $message) {
          $this->debug($message);
        }

        $this->validator->setValue(
          'relationships',
          $customer['relationships']
        );
      }
    } else {
      // Carrega os dados atuais
      $this->validator->setValues($customer);
    }

    // Exibe um formulário para edição de um cliente

    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ERP\Home')
    );
    $this->breadcrumb->push('Cadastro', '');
    if ($this->authorization->getUser()->groupid < 6) {
      $this->breadcrumb->push('Clientes',
        $this->path('ERP\Cadastre\Customers')
      );
      $this->breadcrumb->push('Editar',
        $this->path('ERP\Cadastre\Customers\Edit', [
          'customerID' => $customerID
        ])
      );
    } else {
      $this->breadcrumb->push('Cliente', '');
      $this->breadcrumb->push('Meus dados cadastrais',
        $this->path('ERP\Cadastre\Customers\Edit', [
          'customerID' => $customerID
        ])
      );
    }

    // Registra o acesso
    $this->info("Acesso à edição do cliente '{name}'.",
      [ 'name' => $customer['name'] ]
    );

    // Renderiza a página
    return $this->render($request, $response,
      'erp/cadastre/customers/customer.twig',
      [
        'formMethod' => 'PUT',
        'documentTypes' => $documentTypes,
        'genders' => $genders,
        'maritalStatus' => $maritalStatus,
        'phoneTypes' => $phoneTypes,
        'mailingProfiles' => $mailingProfiles,
        'defaultMailingProfileID' => $defaultMailingProfileID,
        'monitoringCompanies' => $monitoringCompanies,
        'rapidResponseCompanies' => $rapidResponseCompanies,
      ]
    );
  }

  /**
   * Remove o cliente.
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
    $this->debug("Processando à remoção de cliente.");

    // Recupera o ID
    $customerID = $args['customerID'];

    try {
      // Recupera as informações do cliente
      $customer = Customer::findOrFail($customerID);

      // Recupera o local de armazenamento das logomarcas
      $logoDirectory =
        $this->container['settings']['storage']['images']
      ;

      // Agora apaga o cliente

      // Iniciamos a transação
      $this->DB->beginTransaction();

      // TODO: Não devemos mais apagar, mas sim marcar como excluído os
      //       registros, facilitando o processamento
      // Remove o cliente e suas unidades/filiais e dados recursivamente
      $customer->deleteCascade($logoDirectory);

      // Efetiva a transação
      $this->DB->commit();

      // Registra o sucesso
      $this->info("O cliente '{name}' foi removido com sucesso.",
        [ 'name' => $customer->name ]
      );

      // Informa que a remoção foi realizada com sucesso
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getParams(),
            'message' => "Removido o cliente {$customer->name}",
            'data' => "Delete"
          ])
      ;
    } catch (ModelNotFoundException $exception) {
      // Reverte (desfaz) a transação
      $this->DB->rollBack();

      // Registra o erro
      $this->error("Não foi possível localizar o cliente código "
        . "{customerID} para remoção.",
        ['customerID' => $customerID]
      );

      $message = "Não foi possível localizar o cliente para remoção.";
    } catch(QueryException $exception) {
      // Reverte (desfaz) a transação
      $this->DB->rollBack();

      // Registra o erro
      $this->error("Não foi possível remover as informações do cliente "
        . "'{name}'. Erro interno no banco de dados: {error}",
        [
          'name'  => $customer->name,
          'error' => $exception->getMessage()
        ]
      );

      $message = "Não foi possível remover o cliente. Erro interno no "
        . "banco de dados."
      ;
    } catch(Exception $exception) {
      // Reverte (desfaz) a transação
      $this->DB->rollBack();

      // Registra o erro
      $this->error("Não foi possível remover as informações do cliente "
        . "'{name}'. Erro interno: {error}",
        [ 'name'  => $customer->name,
          'error' => $exception->getMessage() ]
      );

      $message = "Não foi possível remover o cliente. Erro interno.";
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
   * Alterna o estado do bloqueio de um cliente e/ou de uma
   * unidade/filial deste cliente.
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
      . "cliente."
    );

    // Recupera o ID
    $customerID = $args['customerID'];
    if (array_key_exists('subsidiaryID', $args)) {
      $subsidiaryID = $args['subsidiaryID'];
    } else {
      $subsidiaryID = NULL;
    }

    try {
      // Recupera as informações do cliente
      if (is_null($subsidiaryID)) {
        // Desbloqueia o cliente
        $customer = Customer::findOrFail($customerID);
        $action   = $customer->blocked
          ? "desbloqueado"
          : "bloqueado"
        ;
        $customer->blocked = !$customer->blocked;
        $customer->updatedbyuserid = $this
          ->authorization
          ->getUser()
          ->userid
        ;
        $customer->save();

        $message = "O cliente '{$customer->name}' foi {$action} "
          . "com sucesso."
        ;
      } else {
        // Desbloqueia a unidade/filial
        $customer   = Customer::findOrFail($customerID);
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

        $message = "A unidade/filial '{$subsidiary->name}' do cliente "
          . "'{$customer->name}' foi {$action} com sucesso."
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
    } catch (ModelNotFoundException $exception) {
      if (is_null($subsidiaryID)) {
        // Registra o erro
        $this->error("Não foi possível localizar o cliente código "
          . "{customerID} para alternar o estado do bloqueio.",
          [ 'customerID' => $customerID ]
        );

        $message = "Não foi possível localizar o cliente para alternar "
          . "o estado do bloqueio."
        ;
      } else {
        // Registra o erro
        $this->error("Não foi possível localizar a unidade/filial "
          . "código {subsidiaryID} do cliente código {customerID} "
          . "para alternar o estado do bloqueio.",
          [
            'customerID' => $customerID,
            'subsidiaryID' => $subsidiaryID
          ]
        );

        $message = "Não foi possível localizar a unidade/filial do "
          . "cliente para alternar o estado do bloqueio."
        ;
      }
    } catch (QueryException $exception) {
      if (is_null($subsidiaryID)) {
        // Registra o erro
        $this->error("Não foi possível alternar o estado do bloqueio "
          . "do cliente '{name}'. Erro interno no banco de dados: "
          . "{error}.",
          [
            'name'  => $customer->name,
            'error' => $exception->getMessage()
          ]
        );

        $message = "Não foi possível alternar o estado do bloqueio do "
          . "cliente. Erro interno no banco de dados."
        ;
      } else {
        // Registra o erro
        $this->error("Não foi possível alternar o estado do bloqueio "
          . "da unidade/filial {subsidiaryName} do cliente '{name}'. "
          . "Erro interno no banco de dados: {error}.",
          [
            'subsidiaryName'  => $subsidiary->name,
            'name'  => $customer->name,
            'error' => $exception->getMessage()
          ]
        );

        $message = "Não foi possível alternar o estado do bloqueio da "
          . "unidade/filial do cliente. Erro interno no banco de "
          . "dados."
        ;
      }
    } catch (Exception $exception) {
      if (is_null($subsidiaryID)) {
        // Registra o erro
        $this->error("Não foi possível alternar o estado do bloqueio "
          . "do cliente '{name}'. Erro interno: {error}.",
          [
            'name'  => $customer->name,
            'error' => $exception->getMessage()
          ]
        );

        $message = "Não foi possível alternar o estado do bloqueio do "
          . "cliente. Erro interno."
        ;
      } else {
        // Registra o erro
        $this->error("Não foi possível alternar o estado do bloqueio "
          . "da unidade/filial {subsidiaryName} do cliente '{name}'. "
          . "Erro interno: {error}.",
          [
            'subsidiaryName'  => $subsidiary->name,
            'name'  => $customer->name,
            'error' => $exception->getMessage()
          ]
        );

        $message = "Não foi possível alternar o estado do bloqueio da "
          . "unidade/filial do cliente. Erro interno no banco de dados."
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
   * Gera um PDF para impressão das informações de um cliente.
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
      . "cadastrais de um cliente."
    );

    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();

    // Recupera as informações do cliente
    $customerID = $args['customerID'];
    $customer = Customer::join("entitiestypes", "entities.entitytypeid",
          '=', "entitiestypes.entitytypeid"
        )
      ->join("users as createduser", "entities.createdbyuserid",
          '=', "createduser.userid"
        )
      ->join("users as updateduser", "entities.updatedbyuserid",
          '=', "updateduser.userid"
        )
      ->where("entities.customer", "true")
      ->where("entities.entityid", $customerID)
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

    // Recupera as informações dos relacionamentos existentes deste
    // cliente
    $customer['relationships'] = $this->getRelationships(
      $customerID, $customer['entitytypeid']
    );

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
      ->where("entityid", $customerID)
    ;

    if (array_key_exists('subsidiaryID', $args)) {
      // Recupera apenas a unidade/filial informada
      $subsidiaryID = $args['subsidiaryID'];
      $subsidiaryQry
        ->where('subsidiaryid', $subsidiaryID)
      ;
    }

    $customer['subsidiaries'] = $subsidiaryQry
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
    foreach ($customer['subsidiaries'] as $row => $subsidiary) {
      // Telefones
      $phones = $this
        ->getPhones(
            $customerID,
            $subsidiary['subsidiaryid']
          )
      ;

      if ($phones->isEmpty()) {
        // Criamos os dados de telefone em branco
        $customer['subsidiaries'][$row]['phones'] = [
          [
            'phoneid' => 0,
            'phonetypeid' => 1,
            'phonenumber' => ''
          ]
        ];
      } else {
        $customer['subsidiaries'][$row]['phones'] =
          $phones ->toArray()
        ;
      }

      // E-mails
      $emails = $this
        ->getEmails(
            $customerID,
            $subsidiary['subsidiaryid']
          )
      ;

      if ($emails->isEmpty()) {
        // Criamos os dados de e-mail em branco
        $customer['subsidiaries'][$row]['emails'] = [
          [
            'mailingid' => 0,
            'email' => ''
          ]
        ];
      } else {
        $customer['subsidiaries'][$row]['emails'] =
          $emails ->toArray()
        ;
      }

      // Contatos adicionais
      $contacts = $this
        ->getContacts(
            $contractor->id,
            $customerID,
            $subsidiary['subsidiaryid']
          )
      ;
      if (!$contacts->isEmpty()) {
        $customer['subsidiaries'][$row]['contacts'] =
          $contacts->toArray()
        ;
      }
    }

    // Renderiza a página para poder converter em PDF
    $title = "Dados cadastrais de cliente";
    $PDFFileName = "Customer_ID_{$customerID}.pdf";
    $page = $this
      ->renderPDF(
          'erp/cadastre/customers/PDFcustomer.twig',
          [
            'contractor' => $contractor,
            'customer' => $customer
          ]
        )
    ;
    $logo   = $this->getContractorLogo($contractor->uuid, 'normal');
    $header = $this->renderPDFHeader($title, $logo);
    $footer = $this->renderPDFFooter();

    // Cria um novo mPDF e define a página no tamanho A4 com orientação
    // portrait
    $mpdf = new Mpdf($this->generatePDFConfig('A4', 'Portrait'));

    // Permite a conversão (opcional)
    $mpdf->allow_charset_conversion = true;

    // Permite a compressão
    $mpdf->SetCompression(true);

    // Define os metadados do documento
    $mpdf->SetTitle($title);
    $mpdf->SetAuthor($this->authorization->getUser()->name);
    $mpdf->SetSubject('Controle de clientes');
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
    $stream = fopen('php://memory', 'r+');
    ob_start();
    $mpdf->Output($PDFFileName, 'I');
    $pdfData = ob_get_contents();
    ob_end_clean();
    fwrite($stream, $pdfData);
    rewind($stream);

    // Registra o acesso
    $this->info("Acesso ao PDF com as informações cadastrais do "
      . "cliente '{name}'.",
      ['name' => $customer['name']]
    );

    return $response
      ->withBody(new Stream($stream))
      ->withHeader('Content-Type', 'application/pdf')
      ->withHeader(
          'Cache-Control',
          'no-store, no-cache, must-revalidate'
        )
      ->withHeader('Expires', 'Sun, 1 Jan 2000 12:00:00 GMT')
      ->withHeader('Last-Modified', gmdate('D, d M Y H:i:s') . 'GMT')
    ;
  }

  /**
   * Determina se existe ao menos um cliente cadastrado e ativo.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   *
   * @return Response $response
   */
  public function hasOneOrMore(
    Request $request,
    Response $response
  ): Response
  {
    $amount = 0;
    $this->debug("Acesso à verificação de clientes cadastrados.");

    // --------------------------[ Recupera os dados requisitados ]-----

    try {
      // Monta a consulta
      $contractorID = $this->authorization->getContractor()->id;
      $sql = "SELECT count(*) as amount
                FROM erp.entities as customers
               WHERE customers.contractorID = {$contractorID}
                 AND customers.customer = true
                 AND customers.blocked = false;"
      ;
      $customers = $this->DB->select($sql);
      $amount    = $customers[0]->amount;

      // Informa a quantidade de clientes disponíveis
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getParams(),
            'message' => 'Quantidade de clientes cadastrados',
            'data' => $amount
          ])
      ;
    } catch(QueryException $exception) {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno no banco de dados: {error}",
        [
          'module' => 'clientes',
          'error'  => $exception->getMessage()
        ]
      );

      $error = "Não foi possível recuperar as informações de clientes. "
        . "Erro interno no banco de dados."
      ;
    } catch(Exception $exception) {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno: {error}",
        [
          'module' => 'clientes',
          'error'  => $exception->getMessage()
        ]
      );

      $error = "Não foi possível recuperar as informações de clientes. "
        . "Erro interno."
      ;
    }

    // Retorna o erro
    return $response
      ->withHeader('Content-type', 'application/json')
      ->withJson([
          'result' => 'NOK',
          'params' => $request->getParams(),
          'message' => $error,
          'data' => $amount
        ])
    ;
  }
}
