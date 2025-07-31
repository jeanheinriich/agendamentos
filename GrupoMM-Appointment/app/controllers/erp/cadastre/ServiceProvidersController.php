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
 * O controlador do gerenciamento dos prestadores de serviço do sistema.
 * Um prestadores de serviços pode ser uma pessoa física e/ou jurídica, e
 * é responsável pelos serviços técnicos.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Controllers\ERP\Cadastre;

use App\Models\Account;
use App\Models\BillingType;
use App\Models\DocumentType;
use App\Models\Bank;
use App\Models\DisplacementPaid;
use App\Models\Entity as ServiceProvider;
use App\Models\EntityType;
use App\Models\Gender;
use App\Models\GeographicCoordinate;
use App\Models\Mailing;
use App\Models\MailingAddress;
use App\Models\MailingProfile;
use App\Models\MaritalStatus;
use App\Models\MeasureType;
use App\Models\Phone;
use App\Models\PhoneType;
use App\Models\PixKeyType;
use App\Models\ServicePrice;
use App\Models\ServiceProvider as SupplementaryDataOfServiceProvider;
use App\Models\Subsidiary;
use App\Models\Technician;
use App\Models\VehicleColor;
use App\Models\VehicleType;
use App\Providers\StateRegistration;
use Core\Controllers\Controller;
use Core\Controllers\QueryTrait;
use Core\Helpers\FormatterTrait;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use InvalidArgumentException;
use Mpdf\Mpdf;
use Respect\Validation\Validator as V;
use Respect\Validation\Rules\Cpf;
use Respect\Validation\Rules\Cnpj;
use Respect\Validation\Rules\Email;
use RuntimeException;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\Stream;

class ServiceProvidersController
  extends Controller
{
  /**
   * Os métodos para manipular queries.
   */
  use QueryTrait;

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
        ->setName('ID do Prestador de Serviços'),
      'name' => V::notBlank()
        ->length(2, 100)
        ->setName('Prestador de serviços'),
      'tradingname' => V::optional(
            V::notBlank()
              ->length(2, 100)
          )
        ->setName('Nome fantasia/apelido'),
      'occupationarea' => V::optional(
            V::notBlank()
          )
        ->setName('Área de atuação'),
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
        ->setName('Observação'),
      'plate' => V::optional(
            V::notEmpty()
              ->vehiclePlate()
          )
        ->setName('Placa'),
      'vehiclemodelname' => V::optional(
            V::notEmpty()
              ->length(2, 50)
          )
        ->setName('Modelo do veículo'),
      'vehiclemodelid' => V::optional(
            V::intVal()
          )
        ->setName('ID do modelo do veículo'),
      'vehicletypeid' => V::optional(
            V::intVal()
          )
        ->setName('Tipo de veículo'),
      'vehiclebrandid' => V::optional(
            V::intVal()
          )
        ->setName('ID da marca do veículo'),
      'vehiclebrandname' => V::optional(
            V::notBlank()
              ->length(2, 30)
          )
        ->setName('Marca do veículo'),
      'vehiclecolorid' => V::optional(
            V::intVal()
              ->min(1)
          )
        ->setName('Cor predominante'),
      'accounts' => [
        'accountid' => V::intVal()
          ->setName('ID da conta'),
        'bankid' => V::optional(
              V::notBlank()
                ->numeric()
                ->length(3, 3)
            )
          ->setName('Banco'),
        'agencynumber' => V::optional(
              V::notBlank()
                ->length(1, 10)
            )
          ->setName('Agência'),
        'accountnumber' => V::optional(
              V::notBlank()
                ->length(1, 15)
            )
          ->setName('Número da conta'),
        'pixkeytypeid' => V::notEmpty()
          ->intVal()
          ->setName('Tipo da chave PIX'),
        'pixkey' => V::optional(
              V::notBlank()
                ->length(1, 72)
            )
          ->setName('Chave PIX')
      ],
      'services' => [
        'added' => V::boolVal()
          ->setName('Serviço prestado adicionado'),
        'servicepriceid' => V::intVal()
          ->setName('ID do tipo de serviço prestado'),
        'billingtypeid' => V::intVal()
          ->min(1)
          ->setName('Tipo de serviço prestado'),
        'pricevalue' => V::numericValue()
          ->setName('Valor pago')
      ],
      'unproductivevisittype' => V::intVal()
        ->setName('Valor pago por visita improdutiva'),
      'unproductivevisit' => V::numericValue()
        ->setName('Tipo do valor pago por visita improdutiva'),
      'frustratedvisittype' => V::intVal()
        ->setName('Valor pago por visita frustrada'),
      'frustratedvisit' => V::numericValue()
        ->setName('Tipo do valor pago por visita frustrada'),
      'unrealizedvisittype' => V::intVal()
        ->setName('Valor cobrado por visita não realizada'),
      'unrealizedvisit' => V::numericValue()
        ->setName('Tipo do valor cobrado por visita não realizada'),
      'displacements' => [
        'displacementpaidid' => V::intVal()
          ->setName('ID da faixa de cobrança'),
        'distance' => V::notBlank()
          ->intVal()
          ->setName('Distância'),
        'value' => V::numericValue()
          ->setName('Valor cobrado'),
      ],
      'geographiccoordinateid' => V::intVal()
        ->setName('Ponto de referência para cálculo'),
      'referencename' => V::optional(
            V::notBlank()
              ->length(1, 100)
          )
        ->setName('Nome da referência'),
      'latitude' => V::optional(
            V::numericValue()
          )
        ->setName('Latitude'),
      'longitude' => V::optional(
            V::numericValue()
          )
        ->setName('Longitude')
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
        ->setName('Bloquear este prestador de serviços e todas suas '
            . 'unidades/filiais'
          )
      ;
    }

    return $validationRules;
  }

  /**
   * Recupera as informações de bancos.
   *
   * @throws RuntimeException
   *   Em caso de não termos bancos
   *
   * @return Collection
   *   A matriz com as informações de bancos
   */
  protected function getBanks(): Collection
  {
    try {
      // Recupera as informações de bancos
      $banks = Bank::orderBy('name')
        ->get([
            'bankid as id',
            'shortname',
            'name'
          ])
      ;

      if ( $banks->isEmpty() ) {
        throw new Exception("Não temos nenhum banco cadastrado");
      }
    }
    catch (Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível obter as informações de bancos. "
        . "Erro: {error}.",
        [ 'error' => $exception->getMessage() ]
      );

      throw new RuntimeException("Não foi possível obter os bancos");
    }

    return $banks;
  }

  /**
   * Recupera as informações de serviços.
   *
   * @param int $contractorID
   *   A ID do contratante para o qual desejamos obter os serviços
   *   disponíveis
   *
   * @throws RuntimeException
   *   Em caso de não termos serviços
   *
   * @return Collection
   *   A matriz com as informações de serviços
   */
  protected function getServices(
    int $contractorID
  ): Collection
  {
    try {
      // Recupera as informações de serviços deste contratante
      $services = BillingType::where("contractorid",
            '=', $contractorID
          )
        ->where("inattendance",
            'true'
          )
        ->orderBy('name', 'ASC')
        ->get([
            'billingtypeid as id',
            'name',
          ])
      ;

      if ( $services->isEmpty() ) {
        throw new Exception("Não temos nenhum tipo de cobrança "
          . "cadastrado"
        );
      }
    }
    catch (Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível obter as informações de tipos de "
        . "cobranças. Erro: {error}.",
        [ 'error' => $exception->getMessage() ]
      );

      throw new RuntimeException("Não foi possível obter os tipos de "
        . "cobranças"
      );
    }

    return $services;
  }

  /**
   * Recupera as informações de tipos de chaves PIXs.
   *
   * @throws RuntimeException
   *   Em caso de não termos tipos de chaves
   *
   * @return Collection
   *   A matriz com as informações de tipos de chaves PIXs
   */
  protected function getPixKeyTypes(): Collection
  {
    try {
      // Recupera as informações de bancos
      $pixKeyTypes = PixKeyType::orderBy('pixkeytypeid')
        ->get([
            'pixkeytypeid as id',
            'name'
          ])
      ;

      if ( $pixKeyTypes->isEmpty() ) {
        throw new Exception("Não temos nenhum tipo de chave PIX "
          . "cadastrada"
        );
      }
    }
    catch (Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível obter as informações de tipos de "
        . "chaves PIXs. Erro: {error}.",
        [ 'error' => $exception->getMessage() ]
      );

      throw new RuntimeException("Não foi possível obter os tipos de "
        . "chaves PIXs"
      );
    }

    return $pixKeyTypes;
  }

  /**
   * Recupera as informações das contas disponíveis.
   *
   * @param int $contractorID
   *   A ID do cliente para o qual desejamos obter os contratos
   *   disponíveis
   * @param int $entityID
   *   A ID da entidade à qual a conta pertence
   *
   * @throws RuntimeException
   *   Em caso de não termos contas bancárias cadastradas
   * 
   * @return Collection
   *   A matriz com as informações de contas bancárias
   */
  protected function getAccounts(
    int $contractorID,
    int $entityID
  ): Collection
  {
    try {
      // Recupera as informações de contas bancárias do contratante
      $accounts = Account::where("contractorid", "=", $contractorID)
        ->where("entityid", "=", $entityID)
        ->orderBy("bankid")
        ->get([
            'accountid',
            'bankid',
            'agencynumber',
            'accountnumber',
            'pixkeytypeid',
            'pixkey'
          ])
      ;

      if ( $accounts->isEmpty() ) {
        throw new Exception("Não temos nenhuma conta bancária "
          . "cadastrada"
        );
      }
    }
    catch (Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível obter as informações de contas "
        . "bancárias. Erro: {error}.",
        [ 'error' => $exception->getMessage() ]
      );

      throw new RuntimeException("Não foi possível obter as contas "
        . "bancárias"
      );
    }

    return $accounts;
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
   * Recupera as informações de estados civís.
   *
   * @throws RuntimeException
   *   Em caso de não termos estados civís
   *
   * @return Collection
   *   A matriz com as informações de estados civís
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
   * Recupera as informações de números de telefones de um prestador de
   * serviços.
   *
   * @param int $serviceProviderID
   *   A ID do prestador de serviços para o qual desejamos obter esta
   *   informação
   * @param int $subsidiaryID
   *   A ID da unidade/filial deste prestador de serviços para o qual
   *   desejamos obter os dados de telefones disponíveis
   *
   * @throws RuntimeException
   *   Em caso de erros
   *
   * @return Collection
   *   A matriz com as informações de números de telefones
   */
  protected function getPhones(
    int $serviceProviderID,
    int $subsidiaryID
  ): Collection
  {
    // Recupera as informações de números de telefones
    return Phone::join('phonetypes',
          'phones.phonetypeid', '=', 'phonetypes.phonetypeid'
        )
      ->where('entityid', $serviceProviderID)
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
   * @param int $serviceProviderID
   *   A ID do prestador de serviços para o qual desejamos obter esta
   *   informação
   * @param int $subsidiaryID
   *   A ID da unidade/filial deste prestador de serviços para o qual
   *   desejamos obter os dados de e-mails disponíveis
   *
   * @throws RuntimeException
   *   Em caso de erros
   *
   * @return Collection
   *   A matriz com as informações de e-mails
   */
  protected function getEmails(
    int $serviceProviderID,
    int $subsidiaryID
  ): Collection
  {
    // Recupera as informações de dados de e-mail
    return Mailing::where('entityid', $serviceProviderID)
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
   * Recupera as informações de contatos adicionais de um prestador de
   * serviços.
   *
   * @param int $contractorID
   *   A ID do contratante deste prestador de serviços
   * @param int $serviceProviderID
   *   A ID do prestador de serviços para o qual desejamos obter esta informação
   * @param int $subsidiaryID
   *   A ID da unidade/filial deste prestador de serviços para o qual desejamos
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
    int $serviceProviderID,
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
      ->where('entityid', $serviceProviderID)
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
   * Recupera as informações de unidades de medidas.
   *
   * @throws RuntimeException
   *   Em caso de não termos unidades de medida
   *
   * @return Collection
   *   A matriz com as informações de unidades de medidas
   */
  protected function getMeasureTypes(): Collection
  {
    try {
      // Recupera as informações de unidades de medidas
      $measureTypes = MeasureType::orderBy('measuretypeid')
        ->get([
            'measuretypeid AS id',
            'name',
            'symbol'
          ])
      ;

      if ( $measureTypes->isEmpty() ) {
        throw new Exception("Não temos nenhuma unidade de medida "
          . "cadastrada"
        );
      }
    }
    catch (Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível obter as informações de unidades "
        . "de medidas. Erro: {error}.",
        [ 'error' => $exception->getMessage() ]
      );

      throw new RuntimeException("Não foi possível obter as unidades "
        . "de medidas"
      );
    }

    return $measureTypes;
  }

  /**
   * Recupera as informações de coordenadas geográficas.
   *
   * @param int $contractorID
   *   A ID do contratante para o qual desejamos obter as coordenadas
   *   geográficas disponíveis
   *
   * @throws RuntimeException
   *   Em caso de não termos coordenadas geográficas definidas
   *
   * @return Collection
   *   A matriz com as informações de coordenadas geográficas
   */
  protected function getGeographicCoordinates(
    int $contractorID
  ): Collection
  {
    try {
      // Recupera as informações de planos deste contratante
      $geographicCoordinates = GeographicCoordinate::where("contractorid", '=', $contractorID)
        ->get([
            'geographiccoordinateid as id',
            'name',
            $this->DB->raw("location[0] AS latitude"),
            $this->DB->raw("location[1] AS longitude")
          ])
      ;

      if ( $geographicCoordinates->isEmpty() ) {
        throw new Exception("Não temos nenhuma coordenada geográfica "
          . "cadastrada"
        );
      }
    }
    catch (Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível obter as informações de "
        . "coordenadas geográficas. Erro: {error}.",
        [ 'error' => $exception->getMessage() ]
      );

      throw new RuntimeException("Não foi possível obter as "
        . "coordenadas geográficas"
      );
    }

    return $geographicCoordinates;
  }

  /**
   * Recupera as informações de tipos de veículos.
   *
   * @throws RuntimeException
   *   Em caso de não termos tipos de veículos
   *
   * @return Collection
   *   A matriz com as informações de tipos de veículos
   */
  protected function getVehiclesTypes(): Collection
  {
    try {
      // Recupera as informações de tipos de veículos
      $vehicleTypes = VehicleType::orderBy('vehicletypeid')
        ->get([
            'vehicletypeid AS id',
            'name'
          ])
      ;

      if ( $vehicleTypes->isEmpty() ) {
        throw new Exception("Não temos nenhum tipo de veículo "
          . "cadastrado"
        );
      }
    }
    catch (Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível obter as informações de tipos de "
        . "veículos. Erro: {error}.",
        [
          'error' => $exception->getMessage()
        ]
      );

      throw new RuntimeException("Não foi possível obter os tipos de "
        . "veículos"
      );
    }

    return $vehicleTypes;
  }

  /**
   * Recupera as informações de cores de veículos.
   *
   * @throws RuntimeException
   *   Em caso de não termos cores de veículos
   *
   * @return Collection
   *   A matriz com as informações de cores de veículos
   */
  protected function getVehiclesColors(): Collection
  {
    try {
      // Recupera as informações de cores de veículos
      $vehicleColors = VehicleColor::orderBy('name')
        ->get([
            'vehiclecolorid AS id',
            'name',
            'color'
          ])
      ;

      if ( $vehicleColors->isEmpty() ) {
        throw new Exception("Não temos nenhuma cor de veículo "
          . "cadastrada"
        );
      }
    }
    catch (Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível obter as informações de cores de "
        . "veículos. Erro: {error}.",
        [
          'error' => $exception->getMessage()
        ]
      );

      throw new RuntimeException("Não foi possível obter as cores de "
        . "veículos"
      );
    }

    return $vehicleColors;
  }

  /**
   * Exibe a página inicial do gerenciamento de prestadores de serviços.
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
    $this->breadcrumb->push('Prestadores de Serviços',
      $this->path('ERP\Cadastre\ServiceProviders')
    );

    // Registra o acesso
    $this->info("Acesso ao gerenciamento de prestadores de serviços.");

    // Recupera os dados da sessão
    $serviceProvider = $this->session->get('serviceProvider',
      [ 'searchField' => 'name',
        'searchValue' => '',
        'filter' => [
          'type' => 0
        ],
        'displayStart' => 0
      ]
    );

    $filters = [
      [ 'id' => 0,
        'name' => 'Todos (ativos e inativos)'
      ],
      [ 'id' => 1,
        'name' => 'Apenas ativos'
      ],
      [ 'id' => 2,
        'name' => 'Apenas inativos'
      ]
    ];

    // Renderiza a página
    return $this->render($request, $response,
      'erp/cadastre/serviceproviders/serviceproviders.twig',
      [
        'serviceProvider' => $serviceProvider,
        'filters' => $filters
      ]
    );
  }

  /**
   * Recupera a relação dos prestadores de serviços em formato JSON.
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
    $this->debug("Acesso à relação de prestadores de serviços.");

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

    $filterType = intval($request->getParam('filterType', 0));

    // Seta os valores da última pesquisa na sessão
    $this->session->set('serviceProvider',
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
      // Realiza a consulta
      $sql = "SELECT E.entityID as id,
                     E.subsidiaryID,
                     E.technicianID,
                     E.juridicalperson,
                     E.technicianIsTheProvider,
                     E.level,  
                     E.active,  
                     E.name,
                     E.tradingname,
                     E.blocked,
                     E.cityname,
                     E.occupationArea,
                     E.nationalregister,
                     CASE
                       WHEN E.level = 1 THEN erp.getTechnicianPhones(E.technicianID)
                       ELSE array_to_string(erp.getPhones({$contractor->id}, E.entityID, E.subsidiaryID, NULL), ' / ')
                     END AS phones,
                     E.blockedlevel,
                     0 as delete,
                     E.createdat,
                     E.fullcount
                FROM erp.getServiceProvidersData({$contractor->id}, 0,
                  '{$searchValue}', '{$searchField}', NULL,
                  {$filterType}, {$start}, {$length}) as E;"
      ;
      $serviceProviders = $this->DB->select($sql);

      if (count($serviceProviders) > 0) {
        $rowCount = $serviceProviders[0]->fullcount;

        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'draw' => $draw,
              'recordsTotal' => $rowCount,
              'recordsFiltered' => $rowCount,
              'data' => $serviceProviders
            ])
        ;
      } else {
        if (empty($searchValue)) {
          $error = "Não temos prestadores de serviços cadastrados.";
        } else {
          switch ($searchField) {
            case 'nationalregister':
              $fieldLabel = 'CPF/CNPJ da unidade/filial ou técnico';

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
          $error = "Não temos prestadores de serviços cadastrados cujo "
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
        [ 'module' => 'prestadores de serviços',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de "
        . "prestadores de serviços. Erro interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno: {error}.",
        [ 'module' => 'prestadores de serviços',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de "
        . "prestadores de serviços. Erro interno."
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
   * Exibe um formulário para adição de um prestador de serviços, quando
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

      // Recupera as informações de bancos
      $banks = $this->getBanks();

      // Recupera as informações de tipos de chaves PIXs
      $pixKeyTypes = $this->getPixKeyTypes();

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

      // Recupera as informações de tipos de veículos
      $vehicleTypes = $this->getVehiclesTypes();

      // Recupera as informações de cores predominantes
      $vehicleColors = $this->getVehiclesColors();

      // Recupera as informações de serviços
      $services = $this->getServices($contractor->id);

      // Recupera as informações de coordenadas geográficas
      $geographicCoordinates = $this->getGeographicCoordinates(
        $contractor->id
      );

      // Recupera as informações de unidades de medidas
      $measureTypes = $this->getMeasureTypes();
    }
    catch (RuntimeException $exception)
    {
      // Alerta o usuário
      $this->flash("error", $exception->getMessage());

      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [ 'routeName' => 'ERP\Cadastre\ServiceProviders' ]
      );

      // Redireciona para a página de gerenciamento de prestadores de
      // serviços
      return $this->redirect($response, 'ERP\Cadastre\ServiceProviders');
    }

    // Verifica se estamos postando os dados
    if ($request->isPost()) {
      // Os dados estão sendo postados

      // Registra o acesso
      $this->debug("Processando à adição de prestador de serviços.");

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
        $this->debug('Os dados do prestador de serviços são VÁLIDOS');

        // Recupera os dados do prestador de serviços
        $serviceProviderData = $this->validator->getValues();

        // Verifica se as inscrições estaduais são válidas. Inicialmente
        // considera todas válidas e, durante a análise, se alguma não
        // for válida, então registra qual delas está incorreta
        $allHasValid = true;
        foreach($serviceProviderData['subsidiaries']
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

        // Verifica se os dados do técnico estão corretos
        // Verifica se os valores de contas bancárias estão corretos
        foreach($serviceProviderData['accounts']
          as $accountNumber => $account) {
          // Recupera o tipo de chave PIX
          $pixKeyTypeID = $account['pixkeytypeid'];

          // Conforme o tipo de chave PIX, fazemos as avaliações da
          // chave PIX
          $pixKey = trim($account['pixkey']);
          switch ($pixKeyTypeID) {
            case 2:
              // CPF/CNPJ
              switch (strlen($pixKey)) {
                case 14:
                  // CPF
                  $v = new Cpf();
                  if (!$v->validate($pixKey)) {
                    // Invalida o formulário
                    $allHasValid = false;

                    // Seta o erro neste campo
                    $this->validator->setErrors([
                        'pixkey' => "Informe um CPF válido"
                      ],
                      "accounts[{$accountNumber}][pixkey]")
                    ;
                  }

                  break;
                case 18:
                  // CNPJ
                  $v = new Cnpj();
                  if (!$v->validate($pixKey)) {
                    // Invalida o formulário
                    $allHasValid = false;

                    // Seta o erro neste campo
                    $this->validator->setErrors([
                        'pixkey' => "Informe um CNPJ válido"
                      ],
                      "accounts[{$accountNumber}][pixkey]")
                    ;
                  }

                  break;
                default:
                  // Invalida o formulário
                  $allHasValid = false;

                  // Seta o erro neste campo
                  $this->validator->setErrors([
                      'pixkey' => "Informe um CPF ou CNPJ válido"
                    ],
                    "accounts[{$accountNumber}][pixkey]")
                  ;
              }

              break;
            case 3:
              // E-mail
              $v = new Email();
              if (!$v->validate($pixKey)) {
                // Invalida o formulário
                $allHasValid = false;

                // Seta o erro neste campo
                $this->validator->setErrors([
                    'pixkey' => "Informe um email válido"
                  ],
                  "accounts[{$accountNumber}][pixkey]")
                ;
              }

              break;
            case 4:
              // Celular
              $size = strlen($pixKey);
              if (! (($size >=14) && ($size <= 20)) ) {
                // Invalida o formulário
                $allHasValid = false;

                // Seta o erro neste campo
                $this->validator->setErrors([
                    'pixkey' => "Informe um celular válido"
                  ],
                  "accounts[{$accountNumber}][pixkey]")
                ;
              }

              break;
            case 5:
              // Aleatória
              $size = strlen($pixKey);
              if (! (($size >=1) && ($size <= 72)) ) {
                // Invalida o formulário
                $allHasValid = false;

                // Seta o erro neste campo
                $this->validator->setErrors([
                    'pixkey' => "Informe uma chave PIX válida"
                  ],
                  "accounts[{$accountNumber}][pixkey]")
                ;
              }

              break;
            default:
              // Não faz qualquer verificação
              break;
          }
        }

        if ($allHasValid) {
          try
          {
            // Primeiro, verifica se não temos um prestador de serviços
            // com o mesmo nome (razão social no caso de pessoa
            // jurídica)
            if (ServiceProvider::where("contractorid", '=', $contractor->id)
                  ->where("serviceprovider", "true")
                  ->whereRaw("public.unaccented(name) ILIKE "
                      . "public.unaccented('{$serviceProviderData['name']}')"
                    )
                  ->count() === 0) {
              // Agora verifica se não temos outra unidade/filial com o
              // mesmo cpf e/ou cnpj dos informados neste prestador de
              // serviços
              $save = true;
              foreach($serviceProviderData['subsidiaries'] as $subsidiary)
              {
                if (ServiceProvider::join("subsidiaries", "entities.entityid",
                          '=', "subsidiaries.entityid"
                        )
                      ->where("entities.serviceprovider", "true")
                      ->where("entities.contractorid", '=',
                          $contractor->id
                        )
                      ->where("subsidiaries.nationalregister",
                          $subsidiary['nationalregister']
                        )
                      ->count() !== 0) {
                  $save = false;

                  // Alerta sobre a existência de outra unidade/filial
                  // de prestadores de serviços com o mesmo CPF/CNPJ
                  if (strlen($subsidiary['nationalregister']) === 14) {
                    $person = 'o técnico';
                    $documentName = 'CPF';
                  } else {
                    $person = 'a unidade/filial';
                    $documentName = 'CNPJ';
                  }

                  // Registra o erro
                  $this->debug("Não foi possível inserir as "
                    . "informações d{person} '{subsidiaryName}' do "
                    . "prestador de serviços '{name}'. Já existe "
                    . "outr{person} com o {document} {nationalregister}.",
                    [ 'subsidiaryName'  => $subsidiary['name'],
                      'name'  => $serviceProviderData['name'],
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
                // Grava o novo prestador de serviços

                // Separamos as informações das unidades/filiais do
                // restante dos dados do prestador de serviços
                $subsidiariesData = $serviceProviderData['subsidiaries'];
                unset($serviceProviderData['subsidiaries']);

                // Separamos os campos complementares da ficha deste
                // prestador de serviços dos demais dados cadastrais
                $onlyKeys = [
                  'occupationarea',
                  'unproductivevisit',
                  'unproductivevisittype',
                  'frustratedvisit',
                  'frustratedvisittype',
                  'unrealizedvisit',
                  'geographiccoordinateid',
                  'referencename',
                  'latitude',
                  'longitude'
                ];
                $supplementaryData = array_filter(
                  $serviceProviderData,
                  function($v) use ($onlyKeys) {
                    return in_array($v, $onlyKeys);
                  },
                  ARRAY_FILTER_USE_KEY
                );
                $serviceProviderData = array_filter(
                  $serviceProviderData,
                  function($v) use ($onlyKeys) {
                    return !in_array($v, $onlyKeys);
                }, ARRAY_FILTER_USE_KEY);

                // Separamos os campos de dados do veículo do prestador
                // de serviços dos demais dados cadastrais
                $onlyKeys = [
                  'plate',
                  'vehicletypeid',
                  'vehicletypename',
                  'vehiclebrandid',
                  'vehiclebrandname',
                  'vehiclemodelid',
                  'vehiclemodelname',
                  'vehiclecolorid',
                  'vehiclecolorname'
                ];
                $vehicleData = array_filter(
                  $serviceProviderData,
                  function($v) use ($onlyKeys) {
                    return in_array($v, $onlyKeys);
                  },
                  ARRAY_FILTER_USE_KEY
                );
                $serviceProviderData = array_filter(
                  $serviceProviderData,
                  function($v) use ($onlyKeys) {
                    return !in_array($v, $onlyKeys);
                }, ARRAY_FILTER_USE_KEY);

                // Precisa retirar dos parâmetros as informações
                // correspondentes às outras tabelas
                // 1. Contas bancárias
                $accountsData = $serviceProviderData['accounts'];
                unset($serviceProviderData['accounts']);
                // 2. Serviços
                $servicesData = $serviceProviderData['services'];
                unset($serviceProviderData['services']);
                // 3. Valores por deslocamento
                $displacementsData = $serviceProviderData['displacements'];
                unset($serviceProviderData['displacements']);

                // ------------------------------------[ Gravação ]-----

                // Iniciamos a transação
                $this->DB->beginTransaction();

                // Incluímos um novo prestador de serviços
                $serviceProvider = new ServiceProvider();
                $serviceProvider->fill($serviceProviderData);
                // Indicamos que é um prestador de serviços
                $serviceProvider->serviceprovider = true;
                // Adicionamos as demais informações
                $serviceProvider->contractorid = $contractor->id;
                $serviceProvider->createdbyuserid =
                  $this->authorization->getUser()->userid
                ;
                $serviceProvider->updatedbyuserid =
                  $this->authorization->getUser()->userid
                ;
                $serviceProvider->save();
                $serviceProviderID = $serviceProvider->entityid;

                // Incluímos todas unidades/filiais deste prestador de serviços
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
                  $subsidiary->entityid = $serviceProviderID;
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
                    $phone->entityid = $serviceProviderID;
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
                      $mailing->entityid     = $serviceProviderID;
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
                    $mailingAddress->entityid        = $serviceProviderID;
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

                if ($supplementaryData['geographiccoordinateid'] == 0) {
                  // Precisamos acrescentar a nova coordenada geográfica
                  $referenceName = $supplementaryData['referencename'];
                  $latitude      = $this->toFloat($supplementaryData['latitude']);
                  $longitude     = $this->toFloat($supplementaryData['longitude']);

                  $sql = ""
                    . "INSERT INTO erp.geographicCoordinates"
                    . "       (contractorID, entityID, name, location) VALUES"
                    . "       ({$contractor->id}, {$serviceProviderID}, "
                    . "        '{$referenceName}', "
                    . "        point({$latitude}, {$longitude}))"
                    . "RETURNING geographicCoordinateID;"
                  ;
                  $coordinate = $this->DB->select($sql);
                  $newCoordinateID = $coordinate[0]->geographiccoordinateid;
                  $supplementaryData['geographiccoordinateid'] = $newCoordinateID;
                  unset($sql);
                }
                unset($supplementaryData['referencename']);
                unset($supplementaryData['latitude']);
                unset($supplementaryData['longitude']);

                // Cadastramos os dados complementares do prestador de
                // serviços
                $supplementary = new SupplementaryDataOfServiceProvider();
                $supplementary->fill($supplementaryData);
                $supplementary->serviceproviderid = $serviceProviderID;
                $supplementary->save();

                // Incluímos todas as contas bancárias deste prestador
                // de serviços
                foreach($accountsData AS $accountData) {
                  // Incluímos uma nova conta bancária
                  $account = new Account();
                  $account->fill($accountData);
                  $account->wallet = '';
                  $account->contractorid = $contractor->id;
                  $account->entityid = $serviceProviderID;
                  $account->save();
                }

                // Incluímos todos os serviços e valores pagos à este
                // prestador de serviços
                foreach($servicesData AS $serviceData) {
                  if ($serviceData['added'] === 'true') {
                    // Incluímos um novo valor cobrado deste plano
                    $service = new ServicePrice();
                    $service->fill($serviceData);
                    $service->serviceproviderid = $serviceProviderID;
                    $service->createdbyuserid =
                      $this->authorization->getUser()->userid
                    ;
                    $service->updatedbyuserid =
                      $this->authorization->getUser()->userid
                    ;

                    $service->save();
                  }
                }

                // Modifica a distância da primeira faixa de pagamento
                // para nula pois ela é considerada a faixa padrão, ou
                // seja, caso não sejam definidas outras faixas, é ela
                // quem prevalece. Caso sejam definidas outras faixas,
                // ela é a última faixa.
                $displacementsData[0]['distance'] = null;

                // Agora inserimos as novas faixas de pagamento
                // definidas
                foreach ($displacementsData as $displacementData) {
                  // Incluímos uma nova faixa de cobrança de deslocamento
                  // neste contrato
                  unset($displacementData['displacementpaidid']);

                  $displacement = new DisplacementPaid();
                  $displacement->fill($displacementData);
                  $displacement->serviceproviderid = $serviceProviderID;
                  $displacement->save();
                }

                // Se o prestador de serviços for uma pessoa física,
                // então considera ele como o primeiro técnico deste
                // prestador, preenchendo suas informações
                if ($serviceProviderData['entitytypeid'] == 2) {
                  // Incluímos o prestador de serviços também como um
                  // técnico, já que eles são a mesma pessoa
                  
                  // Recuperamos os dados da primeira unidade/filial, já
                  // que ele corresponde ao próprio técnico
                  $subsidiaryData = $subsidiariesData[0];

                  // Separamos as informações dos dados de telefones dos
                  // demais dados desta unidade/filial
                  $phonesData = $subsidiaryData['phones'];
                  unset($subsidiaryData['phones']);

                  // Sempre mantém a UF do documento em maiúscula
                  $subsidiaryData['regionaldocumentstate'] =
                    strtoupper($subsidiaryData['regionaldocumentstate'])
                  ;

                  // Remomeamos a coluna com o registro nacional para
                  // CPF
                  $subsidiaryData['cpf'] = $subsidiaryData['nationalregister'];
                  unset($subsidiaryData['nationalregister']);

                  // Separamos as informações dos dados de e-mails dos
                  // demais dados desta unidade/filial
                  $emailsData = $subsidiaryData['emails'];
                  unset($subsidiaryData['emails']);

                  // Preenchemos os dados do técnico
                  $technician = new Technician();
                  $technician->serviceproviderid = $serviceProviderID;
                  $technician->fill($subsidiaryData);
                  $technician->name = $serviceProviderData['name'];
                  // Informamos que ele é o próprio prestador de
                  // serviços
                  $technician->technicianistheprovider = true;
                  // Preenchemos os dados do veículo
                  $technician->fill($vehicleData);
                  $technician->contractorid = $contractor->id;
                  $technician->updatedbyuserid =
                    $this->authorization->getUser()->userid
                  ;
                  $technician->createdbyuserid =
                    $this->authorization->getUser()->userid
                  ;
                  $technician->save();
                }

                // Efetiva a transação
                $this->DB->commit();

                // Registra o sucesso
                $this->info("Cadastrado o prestador de serviços '{name}' com "
                  . "sucesso.",
                  [ 'name'  => $serviceProviderData['name'] ]
                );

                // Alerta o usuário
                $this->flash("success", "O prestador de serviços "
                  . "<i>'{name}'</i> foi cadastrado com sucesso.",
                  [ 'name'  => $serviceProviderData['name'] ]
                );

                // Registra o evento
                $this->debug("Redirecionando para {routeName}",
                  [ 'routeName' => 'ERP\Cadastre\ServiceProviders' ]
                );

                // Redireciona para a página de gerenciamento de
                // prestadores de serviços
                return $this->redirect($response,
                  'ERP\Cadastre\ServiceProviders')
                ;
              }
            } else {
              // Registra o erro
              $this->debug("Não foi possível inserir as informações do "
                . "prestador de serviços '{name}'. Já existe outro "
                . "prestador de serviços com o mesmo nome.",
                [ 'name'  => $serviceProviderData['name'] ]
              );

              // Alerta o usuário
              $this->flashNow("error", "Já existe um prestador de "
                . "serviços com o nome <i>'{name}'</i>.",
                [ 'name' => $serviceProviderData['name'] ]
              );
            }
          }
          catch(QueryException $exception)
          {
            // Reverte (desfaz) a transação
            $this->DB->rollBack();

            // Registra o erro
            $this->error("Não foi possível inserir as informações do "
              . "prestador de serviços '{name}'. Erro interno no banco "
              . "de dados: {error}",
              [ 'name'  => $serviceProviderData['name'],
                'error' => $exception->getMessage() ]
            );

            // Alerta o usuário
            $this->flashNow("error", "Não foi possível inserir as "
              . "informações do prestador de serviços. Erro interno no "
              . "banco de dados."
            );
          }
          catch(Exception $exception)
          {
            // Reverte (desfaz) a transação
            $this->DB->rollBack();

            // Registra o erro
            $this->error("Não foi possível inserir as informações do "
              . "prestador de serviços '{name}'. Erro interno: {error}",
              [ 'name'  => $serviceProviderData['name'],
                'error' => $exception->getMessage() ]
            );

            // Alerta o usuário
            $this->flashNow("error", "Não foi possível inserir as "
              . "informações do prestador de serviços. Erro interno."
            );
          }
        }
      } else {
        $this->debug('Os dados do prestador de serviços são INVÁLIDOS');
        $messages = $this->validator->getFormatedErrors();
        foreach ($messages AS $message) {
          $this->debug($message);
        }

        // Recupera os dados do prestador de serviços
        $serviceProviderData = $this->validator->getValues();

        if (!array_key_exists('accounts', $serviceProviderData)) {
          // Readicionamos as informações de contas
          $this->validator->setValue('accounts', [
            0 => [
              'accountid' => 0,
              'pixkeytypeid' => 1
            ]
          ]);
        }
        if (!array_key_exists('services', $serviceProviderData)) {
          // Readicionamos as informações de contas
          $this->validator->setValue('services', []);
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
        ],
        'accounts' => [
          0 => [
            'accountid' => 0,
            'pixkeytypeid' => 1
          ]
        ],
        'services' => [
        ],
        'unproductivevisittype' => 1,
        'unproductivevisit' => '0,00',
        'frustratedvisittype' => 1,
        'frustratedvisit' => '0,00',
        'unrealizedvisittype' => 1,
        'unrealizedvisit' => '0,00',
        'displacements' => [
          0 => [
            'displacementpaidid' => 999999,
            'distance' => 999999,
            'value' => '0,00'
          ]
        ],
        'geographiccoordinateid' => 0,
        'name' => '',
        'latitude' => '0,0000000',
        'longitude' => '0,0000000'
      ]);
    }

    // Exibe um formulário para adição de um prestador de serviços

    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ERP\Home')
    );
    $this->breadcrumb->push('Cadastro', '');
    $this->breadcrumb->push('Prestadores de Serviços',
      $this->path('ERP\Cadastre\ServiceProviders')
    );
    $this->breadcrumb->push('Adicionar',
      $this->path('ERP\Cadastre\ServiceProviders\Add')
    );

    // Registra o acesso
    $this->info("Acesso à adição de prestadores de serviços.");

    return $this->render($request, $response,
      'erp/cadastre/serviceproviders/serviceprovider.twig',
      [ 'formMethod' => 'POST',
        'banks' => $banks,
        'pixKeyTypes' => $pixKeyTypes,
        'entityTypes' => $entityTypes,
        'documentTypes' => $documentTypes,
        'genders' => $genders,
        'maritalStatus' => $maritalStatus,
        'phoneTypes' => $phoneTypes,
        'mailingProfiles' => $mailingProfiles,
        'defaultMailingProfileID' => $defaultMailingProfileID,
        'vehicleTypes' => $vehicleTypes,
        'vehicleColors' => $vehicleColors,
        'billingTypes' => $services,
        'measureTypes' => $measureTypes,
        'geographicCoordinates' => $geographicCoordinates ])
    ;
  }

  /**
   * Exibe um formulário para edição de um prestador de serviços, quando
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
    $serviceProviderID = $args['serviceProviderID'];

    try {
      // Recupera os dados do contratante
      $contractor = $this->authorization->getContractor();

      // Recupera as informações de bancos
      $banks = $this->getBanks();

      // Recupera as informações de tipos de chaves PIXs
      $pixKeyTypes = $this->getPixKeyTypes();

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

      // Recupera as informações de tipos de veículos
      $vehicleTypes = $this->getVehiclesTypes();

      // Recupera as informações de cores predominantes
      $vehicleColors = $this->getVehiclesColors();

      // Recupera as informações de serviços
      $services = $this->getServices($contractor->id);

      // Recupera as informações de coordenadas geográficas
      $geographicCoordinates = $this->getGeographicCoordinates(
        $contractor->id
      );

      // Recupera as informações de unidades de medidas
      $measureTypes = $this->getMeasureTypes();
    }
    catch (RuntimeException $exception)
    {
      // Alerta o usuário
      $this->flash("error", $exception->getMessage());

      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [ 'routeName' => 'ERP\Cadastre\ServiceProviders' ]
      );

      // Redireciona para a página de gerenciamento de prestadores de serviços
      return $this->redirect($response, 'ERP\Cadastre\ServiceProviders');
    }

    try
    {
      // Recupera as informações do prestador de serviços
      $serviceProvider = ServiceProvider::join("entitiestypes", "entities.entitytypeid",
            '=', "entitiestypes.entitytypeid"
          )
        ->join("serviceproviders", "entities.entityid",
            '=', "serviceproviders.serviceproviderid"
          )
        ->join("users as createduser", "entities.createdbyuserid",
            '=', "createduser.userid"
          )
        ->join("users as updateduser", "entities.updatedbyuserid",
            '=', "updateduser.userid"
          )
        ->where("entities.serviceprovider", "true")
        ->where("entities.entityid", $serviceProviderID)
        ->where("entities.contractorid", '=', $contractor->id)
        ->get([
            'entitiestypes.name as entitytypename',
            'entitiestypes.juridicalperson as juridicalperson',
            'entities.*',
            'serviceproviders.*',
            'createduser.name as createdbyusername',
            'updateduser.name as updatedbyusername'
          ])
      ;

      if ( $serviceProvider->isEmpty() ) {
        throw new ModelNotFoundException("Não temos nenhum prestador "
          . "de serviços com o código {$serviceProviderID} cadastrado"
        );
      }
      $serviceProvider = $serviceProvider
        ->first()
        ->toArray()
      ;

      // Agora recupera as informações das suas unidades/filiais
      $serviceProvider['subsidiaries'] = Subsidiary::join("cities",
            "subsidiaries.cityid", '=', "cities.cityid"
          )
        ->join("documenttypes", "subsidiaries.regionaldocumenttype",
            '=', "documenttypes.documenttypeid"
          )
        ->where("entityid", $serviceProviderID)
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
      foreach ($serviceProvider['subsidiaries'] as $row => $subsidiary) {
        // Telefones
        $phones = $this
          ->getPhones($serviceProviderID,
              $subsidiary['subsidiaryid']
            )
        ;
        if ( $phones->isEmpty() ) {
          // Criamos os dados de telefone em branco
          $serviceProvider['subsidiaries'][$row]['phones'] = [
            [
              'phoneid' => 0,
              'phonetypeid' => 1,
              'phonenumber' => ''
            ]
          ];
        } else {
          $serviceProvider['subsidiaries'][$row]['phones'] =
            $phones ->toArray()
          ;
        }

        // E-mails
        $emails = $this
          ->getEmails($serviceProviderID,
              $subsidiary['subsidiaryid']
            )
        ;
        if ( $emails->isEmpty() ) {
          // Criamos os dados de e-mail em branco
          $serviceProvider['subsidiaries'][$row]['emails'] = [
            [
              'mailingid' => 0,
              'email' => ''
            ]
          ];
        } else {
          $serviceProvider['subsidiaries'][$row]['emails'] =
            $emails ->toArray()
          ;
        }

        // Contatos adicionais
        $contacts = $this
          ->getContacts($contractor->id, $serviceProviderID,
              $subsidiary['subsidiaryid']
            )
        ;
        if ( !$contacts->isEmpty() ) {
          $serviceProvider['subsidiaries'][$row]['contacts'] =
            $contacts->toArray()
          ;
        }
      }

      // Carrega as informações de contas bancárias
      $accounts = $this->getAccounts($contractor->id, $serviceProviderID);
      if ( !$accounts->isEmpty() ) {
        $serviceProvider['accounts'] =
          $accounts->toArray()
        ;
      } else {
        $serviceProvider['accounts'] = [
          0 => [
            'accountid' => 0,
            'pixkeytypeid' => 1
          ]
        ];
      }

      // Carrega as informações de serviços habilitados neste prestador
      // de serviços e os respectivos valores a serem pagos

      // Recupera as informações dos valores cobrados
      // Agora recupera as informações dos valores cobrados
      $prices = ServicePrice::join('billingtypes',
            'serviceprices.billingtypeid', '=',
            'billingtypes.billingtypeid'
          )
        ->where('serviceprices.serviceproviderid', $serviceProviderID)
        ->get([
          'serviceprices.*',
          'billingtypes.name AS billingtypename'
        ])
      ;

      // Precisamos organizar os tipos de serviços pelo código do tipo
      // de forma a permitir renderizar corretamente na página
      $serviceProvider['services'] = [ ];
      if ( !$prices->isEmpty() ) {
        foreach ($prices->toArray() as $price) {
          $price['added'] = 'true';
          $serviceProvider['services'][$price['billingtypeid']] = $price;
        }
      }

      // Se o prestador for pessoa física, então carregamos os dados do
      // "técnico" para edição em conjunto
      if ($serviceProvider['entitytypeid'] == 2) {
        $technician = Technician::join('vehicletypes',
              'technicians.vehicletypeid', '=', 'vehicletypes.vehicletypeid'
            )
          ->join('vehiclebrands', 'technicians.vehiclebrandid',
              '=', 'vehiclebrands.vehiclebrandid'
            )
          ->join('vehiclemodels', 'technicians.vehiclemodelid',
              '=', 'vehiclemodels.vehiclemodelid'
            )
          ->join('vehiclecolors', 'technicians.vehiclecolorid',
              '=', 'vehiclecolors.vehiclecolorid'
            )
          ->where('serviceproviderid', '=', $serviceProviderID)
          ->where('technicianistheprovider', 'true')
          ->get([
              'technicians.plate',
              'technicians.vehicletypeid',
              'vehicletypes.name AS vehicletypename',
              'technicians.vehiclebrandid',
              'vehiclebrands.name AS vehiclebrandname',
              'technicians.vehiclemodelid',
              'vehiclemodels.name AS vehiclemodelname',
              'technicians.vehiclecolorid',
              'vehiclecolors.name AS vehiclecolorname'
            ])
        ;
        if ( !$technician->isEmpty() ) {
          $serviceProvider = array_merge(
            $serviceProvider, $technician->first()->toArray()
          );
        }
      }

      // Carregamos as informações de valores pagos por deslocamento dos
      // técnicos para atendimento
      $serviceProvider['displacements'] = DisplacementPaid::where(
            'serviceproviderid', $serviceProviderID
          )
        ->orderByRaw('distance NULLS FIRST')
        ->get()
        ->toArray()
      ;
      $serviceProvider['displacements'][0]['distance'] = 999999;

      // Sempre adiciona uma latitude e longitude zeradas
      $contract['referencename'] = '';
      $contract['latitude'] = '0,0000000';
      $contract['longitude'] = '0,0000000';
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar o prestador de serviços "
        . "código {serviceProviderID}.",
        [ 'serviceProviderID' => $serviceProviderID ]
      );

      // Alerta o usuário
      $this->flash("error", "Não foi possível localizar este prestador "
        . "de serviços."
      );

      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [ 'routeName' => 'ERP\Cadastre\ServiceProviders' ]
      );

      // Redireciona para a página de gerenciamento de prestadores de
      // serviços
      return $this->redirect($response, 'ERP\Cadastre\ServiceProviders');
    }

    // Verifica se estamos modificando os dados
    if ($request->isPut()) {
      // Os dados estão sendo modificados

      // Registra o acesso
      $this->debug("Processando à edição do prestador de serviços "
        . "'{name}'.",
        [ 'name' => $serviceProvider['name'] ]
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
        $this->debug('Os dados do prestador de serviços são VÁLIDOS');

        // Recupera os dados modificados do prestador de serviços
        $serviceProviderData = $this->validator->getValues();

        // Verifica se as inscrições estaduais são válidas. Inicialmente
        // considera todas válidas e, durante a análise, se alguma não
        // for válida, então registra qual delas está incorreta
        $allHasValid = true;
        foreach($serviceProviderData['subsidiaries']
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

        // Verifica se os valores de contas bancárias estão corretos
        foreach($serviceProviderData['accounts']
          as $accountNumber => $account) {
          // Recupera o tipo de chave PIX
          $pixKeyTypeID = $account['pixkeytypeid'];

          // Conforme o tipo de chave PIX, fazemos as avaliações da
          // chave PIX
          $pixKey = trim($account['pixkey']);
          switch ($pixKeyTypeID) {
            case 2:
              // CPF/CNPJ
              switch (strlen($pixKey)) {
                case 14:
                  // CPF
                  $v = new Cpf();
                  if (!$v->validate($pixKey)) {
                    // Invalida o formulário
                    $allHasValid = false;

                    // Seta o erro neste campo
                    $this->validator->setErrors([
                        'pixkey' => "Informe um CPF válido"
                      ],
                      "accounts[{$accountNumber}][pixkey]")
                    ;
                  }

                  break;
                case 18:
                  // CNPJ
                  $v = new Cnpj();
                  if (!$v->validate($pixKey)) {
                    // Invalida o formulário
                    $allHasValid = false;

                    // Seta o erro neste campo
                    $this->validator->setErrors([
                        'pixkey' => "Informe um CNPJ válido"
                      ],
                      "accounts[{$accountNumber}][pixkey]")
                    ;
                  }

                  break;
                default:
                  // Invalida o formulário
                  $allHasValid = false;

                  // Seta o erro neste campo
                  $this->validator->setErrors([
                      'pixkey' => "Informe um CPF ou CNPJ válido"
                    ],
                    "accounts[{$accountNumber}][pixkey]")
                  ;
              }

              break;
            case 3:
              // E-mail
              $v = new Email();
              if (!$v->validate($pixKey)) {
                // Invalida o formulário
                $allHasValid = false;

                // Seta o erro neste campo
                $this->validator->setErrors([
                    'pixkey' => "Informe um email válido"
                  ],
                  "accounts[{$accountNumber}][pixkey]")
                ;
              }

              break;
            case 4:
              // Celular
              $size = strlen($pixKey);
              if (! (($size >=14) && ($size <= 20)) ) {
                // Invalida o formulário
                $allHasValid = false;

                // Seta o erro neste campo
                $this->validator->setErrors([
                    'pixkey' => "Informe um celular válido"
                  ],
                  "accounts[{$accountNumber}][pixkey]")
                ;
              }

              break;
            case 5:
              // Aleatória
              $size = strlen($pixKey);
              if (! (($size >=1) && ($size <= 72)) ) {
                // Invalida o formulário
                $allHasValid = false;

                // Seta o erro neste campo
                $this->validator->setErrors([
                    'pixkey' => "Informe uma chave PIX válida"
                  ],
                  "accounts[{$accountNumber}][pixkey]")
                ;
              }

              break;
            default:
              // Não faz qualquer verificação
              break;
          }
        }

        if ($allHasValid) {
          // Atualiza os dados do prestador de serviços

          // Separamos as informações das unidades/filiais do restante
          // dos dados do prestador de serviços
          $subsidiariesData = $serviceProviderData['subsidiaries'];
          unset($serviceProviderData['subsidiaries']);

          // Não permite modificar o tipo de entidade nem a informação
          // de que o mesmo é prestadores de serviços
          unset($serviceProviderData['entitytypeid']);
          unset($serviceProviderData['serviceprovider']);

          // Separamos os campos complementares da ficha deste prestador
          // de serviços dos demais dados cadastrais
          $onlyKeys = [
            'occupationarea',
            'unproductivevisit',
            'unproductivevisittype',
            'frustratedvisit',
            'frustratedvisittype',
            'unrealizedvisit',
            'geographiccoordinateid',
            'referencename',
            'latitude',
            'longitude'
          ];
          $supplementaryData = array_filter(
            $serviceProviderData,
            function($v) use ($onlyKeys) {
              return in_array($v, $onlyKeys);
            },
            ARRAY_FILTER_USE_KEY
          );
          $serviceProviderData = array_filter(
            $serviceProviderData,
            function($v) use ($onlyKeys) {
              return !in_array($v, $onlyKeys);
          }, ARRAY_FILTER_USE_KEY);

          // Separamos os campos de dados do veículo do prestador de
          // serviços dos demais dados cadastrais
          $onlyKeys = [
            'plate',
            'vehicletypeid',
            'vehicletypename',
            'vehiclebrandid',
            'vehiclebrandname',
            'vehiclemodelid',
            'vehiclemodelname',
            'vehiclecolorid',
            'vehiclecolorname'
          ];
          $vehicleData = array_filter(
            $serviceProviderData,
            function($v) use ($onlyKeys) {
              return in_array($v, $onlyKeys);
            },
            ARRAY_FILTER_USE_KEY
          );
          $serviceProviderData = array_filter(
            $serviceProviderData,
            function($v) use ($onlyKeys) {
              return !in_array($v, $onlyKeys);
          }, ARRAY_FILTER_USE_KEY);

          // Precisa retirar dos parâmetros as informações
          // correspondentes às outras tabelas
          // 1. Contas bancárias
          $accountsData = $serviceProviderData['accounts'];
          unset($serviceProviderData['accounts']);
          // 2. Serviços
          $servicesData = $serviceProviderData['services'];
          unset($serviceProviderData['services']);
          // 3. Valores por deslocamento
          $displacementsData = $serviceProviderData['displacements'];
          unset($serviceProviderData['displacements']);

          try
          {
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
            $subsidiaries = Subsidiary::where("entityid", $serviceProviderID)
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

            // ================================[ Contas Bancárias ]=====
            // Recupera as informações das contas bancárias e separa os
            // dados para as operações de inserção, atualização e
            // remoção.
            // =========================================================

            // -------------------------------[ Pré-processamento ]-----

            // Analisa as contas bancárias informadas, de forma a
            // separar quais unidades precisam ser adicionadas,
            // removidas e atualizadas

            // Matrizes que armazenarão os dados das contas bancárias a
            // serem adicionados, atualizados e removidos
            $newAccounts = [ ];
            $updAccounts = [ ];
            $delAccounts = [ ];

            // Os IDs das contas bancárias mantidos para permitir
            // determinar as contas bancárias a serem removidas
            $heldAccounts = [ ];

            // Determina quais contas serão mantidas (e atualizadas)
            // e as que precisam ser adicionadas (novas)
            foreach ($accountsData as $account) {
              if (empty($account['accountid'])) {
                // Conta bancária nova
                $newAccounts[] = $account;
              } else {
                // Conta bancária existente
                $heldAccounts[] = $account['accountid'];
                $updAccounts[]  = $account;
              }
            }

            // Recupera as contas bancárias armazenadas atualmente
            $accounts = Account::where("entityid", $serviceProviderID)
              ->get([
                  'accountid'
                ])
              ->toArray()
            ;
            $oldAccounts = [ ];
            foreach ($accounts as $account) {
              $oldAccounts[] = $account['accountid'];
            }

            // Verifica quais as contas bancárias estavam na base de
            // dados e precisam ser removidas
            $delAccounts = array_diff(
              $oldAccounts, $heldAccounts
            );
            
            // ========================================[ Serviços ]=====
            // Recupera as informações dos serviços habilitados e dos
            // respectivos valores pagos e separa os dados para as
            // operações de inserção, atualização e remoção.
            // =========================================================
            
            // -------------------------------[ Pré-processamento ]-----
            
            // Analisa os serviços habilitados informados, de forma a
            // separar quais valores precisam ser adicionados, removidos
            // e atualizados
            
            // Matrizes que armazenarão os dados dos serviços
            // habilitados a serem adicionados, atualizados e removidos
            $newServices = [ ];
            $updServices = [ ];
            $delServices = [ ];

            // Separa os itens que precisam ser adicionados, modificados
            // e removidos respectivamente
            foreach ($servicesData AS $service) {
              if ($service['added'] === 'true') {
                // O serviço está selecionado
                if (empty($service['servicepriceid'])) {
                  // Adiciona o serviço
                  $newServices[] = $service;
                } else {
                  // Atualiza o serviço
                  $updServices[]  = $service;
                }
              } else {
                if (!empty($service['servicepriceid'])) {
                  // Remove o serviço
                  $delServices[] = $service['servicepriceid'];
                }
              }
            }
            
            // ============[ Faixas de pagamento por deslocamento ]=====
            // Recupera as informações das faixas de pagamento por
            // deslocamento e separa os dados para as operações de
            // inserção, atualização e remoção.
            // =========================================================
            
            // -------------------------------[ Pré-processamento ]-----

            // Modifica a distância da primeira faixa de cobrança para
            // nula pois ela é considerada a faixa padrão, ou seja, caso
            // não sejam definidas outras faixas, é ela quem prevalece.
            // Caso sejam definidas outras faixas, ela é a última faixa.
            $displacementsData[0]['distance'] = null;

            // Analisa as faixas de cobrança informadas, de forma a
            // separar quais valores precisam ser adicionados, removidos
            // e atualizados
            
            // Matrizes que armazenarão os dados das faixas de cobrança
            // a serem adicionadas, atualizadas e removidas
            $newDisplacements = [ ];
            $updDisplacements = [ ];
            $delDisplacements = [ ];

            // Os IDs das faixas de pagamento mantidas para permitir
            // determinar as faixas a serem removidas
            $heldDisplacements = [ ];

            // Determina quais faixas de pagamento serão mantidas (e
            // atualizadas) e as que precisam ser adicionadas (novas)
            foreach ($displacementsData AS $displacement) {
              if (empty($displacement['displacementpaidid'])) {
                // Faixa de pagamento novo
                $newDisplacements[] = $displacement;
              } else {
                // Faixa de pagamento existente
                $heldDisplacements[] = $displacement['displacementpaidid'];
                $updDisplacements[]  = $displacement;
              }
            }
            
            // Recupera as faixas de pagamento armazenadas atualmente
            $displacements = DisplacementPaid::where(
                  'serviceproviderid', $serviceProviderID
                )
              ->orderByRaw('distance NULLS FIRST')
              ->get(['displacementpaidid'])
              ->toArray()
            ;

            $oldDisplacements = [ ];
            foreach ($displacements as $displacement) {
              $oldDisplacements[] = $displacement['displacementpaidid'];
            }

            // Verifica quais as faixas de pagamento estavam na base de
            // dados e precisam ser removidas
            $delDisplacements = array_diff(
              $oldDisplacements, $heldDisplacements
            );

            // ----------------------------------------[ Gravação ]-----

            // Iniciamos a transação
            $this->DB->beginTransaction();

            // Verifica se foi adicionada uma nova coordenada geográfica
            if ($supplementaryData['geographiccoordinateid'] == 0) {
              // Precisamos acrescentar a nova coordenada geográfica
              $referenceName = $supplementaryData['referencename'];
              $latitude      = $this->toFloat($supplementaryData['latitude']);
              $longitude     = $this->toFloat($supplementaryData['longitude']);

              $sql = ""
                . "INSERT INTO erp.geographicCoordinates"
                . "       (contractorID, entityID, name, location) VALUES"
                . "       ({$contractor->id}, {$serviceProviderID}, "
                . "        '{$referenceName}', "
                . "        point({$latitude}, {$longitude}))"
                . "RETURNING geographicCoordinateID;"
              ;
              $coordinate = $this->DB->select($sql);
              $newCoordinateID = $coordinate[0]->geographiccoordinateid;
              $supplementaryData['geographiccoordinateid'] = $newCoordinateID;
              unset($sql);
            }
            unset($supplementaryData['referencename']);
            unset($supplementaryData['latitude']);
            unset($supplementaryData['longitude']);

            // Grava as informações do prestador de serviços
            $serviceProviderChanged = ServiceProvider::findOrFail($serviceProviderID);
            $serviceProviderChanged->fill($serviceProviderData);
            $serviceProviderChanged->updatedbyuserid =
              $this->authorization->getUser()->userid
            ;
            $serviceProviderChanged->save();

            // Cadastramos os dados complementares do prestador de
            // serviços
            $supplementaryChanged =
                SupplementaryDataOfServiceProvider::where('serviceproviderid',
                  '=', $serviceProviderID
                )
              ->first()
            ;
            $supplementaryChanged->fill($supplementaryData);
            $supplementaryChanged->serviceproviderid = $serviceProviderID;
            $supplementaryChanged->save();

            // --------------------------------[ Unidades/Filiais ]-----

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
              $subsidiary->entityid = $serviceProviderID;
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
                $phone->entityid     = $serviceProviderID;
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
                  $mailing->entityid     = $serviceProviderID;
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
                $mailingAddress->entityid        = $serviceProviderID;
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
              if (array_key_exists('contacts', $subsidiaryData)) {
                $contactsData = $subsidiaryData['contacts'];
                unset($subsidiaryData['contacts']);
              }

              // Grava as alterações dos dados da unidade/filial
              $subsidiary = Subsidiary::findOrFail($subsidiaryID);
              $subsidiary->fill($subsidiaryData);
              $subsidiary->updatedbyuserid =
                $this->authorization->getUser()->userid
              ;
              $subsidiary->save();

              if ($serviceProviderData['juridicalperson'] === 'false') {
                // Replica as informações no primeiro técnico, já que
                // ele é a mesma pessoa que o prestador de serviços
                $technician = $technician = Technician::where('serviceproviderid', '=', $serviceProviderID)
                  ->where('technicianistheprovider', 'true')
                  ->first()
                ;
                $technician->fill($subsidiaryData);
                $technician->name = $serviceProviderData['name'];
                // Preenchemos os dados do veículo
                $technician->fill($vehicleData);
                $technician->updatedbyuserid =
                  $this->authorization->getUser()->userid
                ;
                $technician->save();
              }

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
                $phone->entityid     = $serviceProviderID;
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
                $mailing->entityid     = $serviceProviderID;
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
                $mailingAddress->entityid     = $serviceProviderID;
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

            // --------------------------------[ Contas Bancárias ]-----

            // Primeiro apagamos as contas bancárias removidas pelo
            // usuário durante a edição
            foreach ($delAccounts as $accountID) {
              // Apaga cada conta
              $account = Account::findOrFail($accountID);
              $account->delete();
            }

            // Agora inserimos as novas contas bancárias
            foreach ($newAccounts as $accountData) {
              // Retiramos o campo de ID da conta, pois os dados tratam
              // de um novo registro
              unset($accountData['accountid']);

              // Incluímos a nova conta bancária
              $account = new Account();
              $account->fill($accountData);
              $account->contractorid = $contractor->id;
              $account->entityid = $serviceProviderID;
              $account->save();
            }

            // Por último, modificamos as contas bancárias mantidas
            foreach($updAccounts as $accountData) {
              // Retiramos o campo de ID da conta
              $accountID = $accountData['accountid'];
              unset($accountData['contractorid']);
              unset($accountData['accountid']);

              // Grava as alterações dos dados da conta bancária
              $account = Account::findOrFail($accountID);
              $account->fill($accountData);
              $account->save();
            }

            // ----------------------------------------[ Serviços ]-----
            
            // Primeiro apagamos os serviços removidos pelo usuário
            // durante a edição
            foreach ($delServices as $serviceID) {
              // Apaga cada valor cobrado
              $service = ServicePrice::findOrFail($serviceID);
              $service->delete();
            }

            // Agora inserimos os novos serviços habilitados
            foreach ($newServices as $serviceData) {
              // Incluímos um novo serviço neste prestador
              unset($serviceData['contractchargeid']);
              $service = new ServicePrice();
              $service->fill($serviceData);
              $service->serviceproviderid = $serviceProviderID;
              $service->updatedbyuserid =
                $this->authorization->getUser()->userid
              ;
              $service->createdbyuserid =
                $this->authorization->getUser()->userid
              ;

              $service->save();
            }

            // Por último, modificamos os serviços habilitados mantidos
            foreach($updServices AS $serviceData) {
              // Retira a ID do serviço
              $serviceID = $serviceData['servicepriceid'];
              unset($serviceData['servicepriceid']);
              
              // Por segurança, nunca permite modificar qual a ID da
              // entidade mãe
              unset($serviceData['serviceproviderid']);
              
              // Grava as informações do valor cobrado
              $service = ServicePrice::findOrFail($serviceID);
              $service->fill($serviceData);
              $service->updatedbyuserid =
                $this->authorization->getUser()->userid
              ;
              $service->save();
            }
            
            // ------------[ Faixas de pagamento por deslocamento ]-----

            // Primeiro apagamos as faixas de pagamento por deslocamento
            // removidas pelo usuário durante a edição
            foreach ($delDisplacements as $displacementPaidID) {
              // Apaga cada faixa definida
              $displacement = DisplacementPaid::findOrFail($displacementPaidID);
              $displacement->delete();
            }

            // Agora inserimos as novas faixas de pagamento definidas
            foreach ($newDisplacements as $displacementData) {
              // Incluímos uma nova faixa de pagamento por deslocamento
              // neste prestador
              unset($displacementData['displacementpaidid']);

              $displacement = new DisplacementPaid();
              $displacement->fill($displacementData);
              $displacement->serviceproviderid = $serviceProviderID;
              $displacement->save();
            }

            // Por último, modificamos as faixas de pagamento por
            // deslocamento mantidas
            foreach($updDisplacements AS $displacementData) {
              // Retira a ID da faixa
              $displacementPaidID = $displacementData['displacementpaidid'];
              unset($displacementData['displacementpaidid']);
              
              // Por segurança, nunca permite modificar qual a ID do
              // prestador de serviços
              unset($displacementData['serviceproviderid']);
              
              // Grava as informações da faixa de cobrança
              $displacement = DisplacementPaid::findOrFail($displacementPaidID);
              $displacement->fill($displacementData);
              $displacement->save();
            }

            // =========================================================

            // Efetiva a transação
            $this->DB->commit();

            // Registra o sucesso
            $this->info("O prestador de serviços '{name}' foi modificado com "
              . "sucesso.",
              [ 'name' => $serviceProviderData['name'] ]
            );

            // Alerta o usuário
            $this->flash("success", "O prestador de serviços "
              . "<i>'{name}'</i> foi modificado com sucesso.",
              [ 'name' => $serviceProviderData['name'] ]
            );

            // Registra o evento
            $this->debug("Redirecionando para {routeName}",
              [ 'routeName' => 'ERP\Cadastre\ServiceProviders' ]
            );

            // Redireciona para a página de gerenciamento de
            // prestadores de serviços
            return $this->redirect($response,
              'ERP\Cadastre\ServiceProviders')
            ;
          }
          catch(Exception $exception)
          {
            // Reverte (desfaz) a transação
            $this->DB->rollBack();

            // Registra o erro
            $this->error("Não foi possível modificar as informações do "
              . "prestadores de serviços '{name}'. Erro interno: {error}",
              [ 'name'  => $serviceProviderData['name'],
                'error' => $exception->getMessage() ]
            );

            // Alerta o usuário
            $this->flashNow("error", "Não foi possível modificar as "
              . "informações do prestador de serviços. Erro interno."
            );
          }
        }
      } else {
        $this->debug('Os dados do prestador de serviços são INVÁLIDOS');
        $messages = $this->validator->getFormatedErrors();
        foreach ($messages AS $message) {
          $this->debug($message);
        }
      }
    } else {
      // Carrega os dados atuais
      $this->validator->setValues($serviceProvider);
    }

    // Exibe um formulário para edição de um prestador de serviços

    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ERP\Home')
    );
    $this->breadcrumb->push('Cadastro', '');
    $this->breadcrumb->push('Prestadores de Serviços',
      $this->path('ERP\Cadastre\ServiceProviders')
    );
    $this->breadcrumb->push('Editar',
      $this->path('ERP\Cadastre\ServiceProviders\Edit', [
        'serviceProviderID' => $serviceProviderID
      ])
    );

    // Registra o acesso
    $this->info("Acesso à edição do prestador de serviços '{name}'.",
      [ 'name' => $serviceProvider['name'] ]
    );

    // Renderiza a página
    return $this->render($request, $response,
      'erp/cadastre/serviceproviders/serviceprovider.twig',
      [
        'formMethod' => 'PUT',
        'banks' => $banks,
        'pixKeyTypes' => $pixKeyTypes,
        'documentTypes' => $documentTypes,
        'genders' => $genders,
        'maritalStatus' => $maritalStatus,
        'phoneTypes' => $phoneTypes,
        'mailingProfiles' => $mailingProfiles,
        'defaultMailingProfileID' => $defaultMailingProfileID,
        'vehicleTypes' => $vehicleTypes,
        'vehicleColors' => $vehicleColors,
        'billingTypes' => $services,
        'measureTypes' => $measureTypes,
        'geographicCoordinates' => $geographicCoordinates
      ]
    );
  }

  /**
   * Alterna o estado do bloqueio de um prestador de serviços e/ou de uma
   * unidade/filial deste prestador de serviços.
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
      . "prestador de serviços."
    );

    // Recupera o ID
    $serviceProviderID = $args['serviceProviderID'];
    $subsidiaryID = $args['subsidiaryID'];

    try
    {
      // Recupera as informações do prestador de serviços
      if (is_null($subsidiaryID)) {
        // Desbloqueia o prestador de serviços
        $serviceProvider = ServiceProvider::findOrFail($serviceProviderID);
        $action   = $serviceProvider->blocked
          ? "desbloqueado"
          : "bloqueado"
        ;
        $serviceProvider->blocked = !$serviceProvider->blocked;
        $serviceProvider->updatedbyuserid = $this
          ->authorization
          ->getUser()
          ->userid
        ;
        $serviceProvider->save();

        $message = "O prestador de serviços '{$serviceProvider->name}' "
          . "foi {$action} com sucesso."
        ;
      } else {
        // Desbloqueia a unidade/filial
        $serviceProvider   = ServiceProvider::findOrFail($serviceProviderID);
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
          . "prestador de serviços '{$serviceProvider->name}' foi "
          . "{$action} com sucesso."
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
        $this->error("Não foi possível localizar o prestador de "
          . "serviços código {serviceProviderID} para alternar o estado do "
          . "bloqueio.",
          [ 'serviceProviderID' => $serviceProviderID ])
        ;

        $message = "Não foi possível localizar o prestador de serviços "
          . "para alternar o estado do bloqueio."
        ;
      } else {
        // Registra o erro
        $this->error("Não foi possível localizar a unidade/filial "
          . "código {subsidiaryID} do prestador de serviços código "
          . "{serviceProviderID} para alternar o estado do bloqueio.",
          [ 'serviceProviderID' => $serviceProviderID,
            'subsidiaryID' => $subsidiaryID ])
        ;

        $message = "Não foi possível localizar a unidade/filial do "
          . "prestadores de serviços para alternar o estado do bloqueio."
        ;
      }
    }
    catch(QueryException $exception)
    {
      if (is_null($subsidiaryID)) {
        // Registra o erro
        $this->error("Não foi possível alternar o estado do bloqueio "
          . "do prestador de serviços '{name}'. Erro interno no banco "
          . "de dados: {error}.",
          [ 'name'  => $serviceProvider->name,
            'error' => $exception->getMessage() ]
        );

        $message = "Não foi possível alternar o estado do bloqueio do "
          . "prestadores de serviços. Erro interno no banco de dados."
        ;
      } else {
        // Registra o erro
        $this->error("Não foi possível alternar o estado do bloqueio "
          . "da unidade/filial {subsidiaryName} do prestador de "
          . "serviços '{name}'. Erro interno no banco de dados: {error}.",
          [ 'subsidiaryName'  => $subsidiary->name,
            'name'  => $serviceProvider->name,
            'error' => $exception->getMessage() ]
        );

        $message = "Não foi possível alternar o estado do bloqueio da "
          . "unidade/filial do prestador de serviços. Erro interno no "
          . "banco de dados."
        ;
      }
    }
    catch(Exception $exception)
    {
      if (is_null($subsidiaryID)) {
        // Registra o erro
        $this->error("Não foi possível alternar o estado do bloqueio "
          . "do prestador de serviços '{name}'. Erro interno: {error}.",
          [ 'name'  => $serviceProvider->name,
            'error' => $exception->getMessage() ]
        );

        $message = "Não foi possível alternar o estado do bloqueio do "
          . "prestadores de serviços. Erro interno."
        ;
      } else {
        // Registra o erro
        $this->error("Não foi possível alternar o estado do bloqueio "
          . "da unidade/filial {subsidiaryName} do prestador de "
          . "serviços '{name}'. Erro interno: {error}.",
          [ 'subsidiaryName'  => $subsidiary->name,
            'name'  => $serviceProvider->name,
            'error' => $exception->getMessage() ]
        );

        $message = "Não foi possível alternar o estado do bloqueio da "
          . "unidade/filial do prestador de serviços. Erro interno no "
          . "banco de dados."
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
   * Gera um PDF para impressão das informações de um prestador de serviços.
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
      . "cadastrais de um prestador de serviços."
    );

    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();

    // Recupera as informações do prestador de serviços
    $serviceProviderID = $args['serviceProviderID'];
    $serviceProvider = ServiceProvider::join("entitiestypes", "entities.entitytypeid",
          '=', "entitiestypes.entitytypeid"
        )
      ->join("users as createduser", "entities.createdbyuserid",
          '=', "createduser.userid"
        )
      ->join("users as updateduser", "entities.updatedbyuserid",
          '=', "updateduser.userid"
        )
      ->where("entities.serviceprovider", "true")
      ->where("entities.entityid", $serviceProviderID)
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
      ->where("entityid", $serviceProviderID)
    ;

    if (array_key_exists('subsidiaryID', $args)) {
      // Recupera apenas a unidade/filial informada
      $subsidiaryID = $args['subsidiaryID'];
      $subsidiaryQry
        ->where('subsidiaryid', $subsidiaryID)
      ;
    }

    $serviceProvider['subsidiaries'] = $subsidiaryQry
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
    foreach ($serviceProvider['subsidiaries'] as $row => $subsidiary) {
      // Telefones
      $phones = $this
        ->getPhones($serviceProviderID,
            $subsidiary['subsidiaryid']
          )
      ;
      if ( $phones->isEmpty() ) {
        // Criamos os dados de telefone em branco
        $serviceProvider['subsidiaries'][$row]['phones'] = [
          [
            'phoneid' => 0,
            'phonetypeid' => 1,
            'phonenumber' => ''
          ]
        ];
      } else {
        $serviceProvider['subsidiaries'][$row]['phones'] =
          $phones ->toArray()
        ;
      }

      // E-mails
      $emails = $this
        ->getEmails($serviceProviderID,
            $subsidiary['subsidiaryid']
          )
      ;
      if ( $emails->isEmpty() ) {
        // Criamos os dados de e-mail em branco
        $serviceProvider['subsidiaries'][$row]['emails'] = [
          [
            'mailingid' => 0,
            'email' => ''
          ]
        ];
      } else {
        $serviceProvider['subsidiaries'][$row]['emails'] =
          $emails ->toArray()
        ;
      }

      // Contatos adicionais
      $contacts = $this
        ->getContacts($contractor->id, $serviceProviderID,
            $subsidiary['subsidiaryid']
          )
      ;
      if ( !$contacts->isEmpty() ) {
        $serviceProvider['subsidiaries'][$row]['contacts'] =
          $contacts->toArray()
        ;
      }
    }

    // Renderiza a página para poder converter em PDF
    $title = "Dados cadastrais de prestadores de serviços";
    $PDFFileName = "ServiceProvider_ID_{$serviceProviderID}.pdf";
    $page = $this->renderPDF(
      'erp/cadastre/serviceproviders/PDFserviceprovider.twig',
      [
        'serviceProvider' => $serviceProvider
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
    $mpdf->SetSubject('Controle de prestadores de serviços');
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
      . "prestador de serviços '{name}'.",
      [ 'name' => $serviceProvider['name'] ]
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
