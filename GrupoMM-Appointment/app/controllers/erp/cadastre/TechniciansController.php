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
 * O controlador do gerenciamento dos técnicos do sistema. Um técnico
 * está vinculado à um prestador de serviços.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Controllers\ERP\Cadastre;

use App\Models\DocumentType;
use App\Models\Entity as ServiceProvider;
use App\Models\Gender;
use App\Models\PhoneType;
use App\Models\Technician;
use App\Models\TechnicianPhone;
use App\Models\TechnicianMailing;
use App\Models\VehicleColor;
use App\Models\VehicleType;
use Core\Controllers\Controller;
use Core\Controllers\QueryTrait;
use Core\Helpers\FormatterTrait;
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

class TechniciansController
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
      'serviceproviderid' => V::notBlank()
        ->intVal()
        ->setName('ID do Prestador de Serviços'),
      'serviceprovidername' => V::notBlank()
        ->setName('Nome do Prestador de Serviços'),
      'technicianid' => V::notBlank()
        ->intVal()
        ->setName('ID do Técnico'),
      'technicianistheprovider' => V::boolVal()
        ->setName('Indicador de que este técnico é o próprio prestador de serviços'),
      'name' => V::notBlank()
        ->length(2, 100)
        ->setName('Técnico'),
      'regionaldocumenttype' => V::optional(
            V::notEmpty()
              ->intVal()
          )
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
      'cpf' => V::notEmpty()
        ->cpf()
        ->setName('CPF'),
      'birthday' => V::optional(
            V::notEmpty()
              ->date('d/m/Y')
          )
        ->setName('Data de nascimento'),
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
      'blocked' => V::boolVal()
        ->setName('Bloquear este técnico'),
      'phones' => [
        'technicianphoneid' => V::intVal()
          ->setName('ID do telefone'),
        'phonenumber' => V::notBlank()
          ->length(14, 20)
          ->setName('Telefone'),
        'phonetypeid' => V::notBlank()
          ->intval()
          ->setName('Tipo de telefone')
      ],
      'emails' => [
        'technicianmailingid' => V::intVal()
          ->setName('ID do e-mail'),
        'email' => V::optional(
              V::notEmpty()
                ->length(2, 100)
                ->email()
            )
          ->setName('E-Mail')
      ],
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
        ->setName('Cor predominante')
    ];

    if ($addition) {
      // Ajusta as regras para adição
      unset($validationRules['technicianid']);
      unset($validationRules['phones']['technicianphoneid']);
      unset($validationRules['emails']['technicianmailingid']);
    } else {
      // Ajusta as regras para edição
      $validationRules['blocked'] = V::boolVal()
        ->setName('Bloquear este técnico')
      ;
    }

    return $validationRules;
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
      // Recupera as informações de tipos de documentos para pessoas
      // físicas
      $documentTypes = DocumentType::where('juridicalperson', 'false')
        ->orderBy('documenttypeid')
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
   * Recupera as informações de números de telefones de um técnico.
   *
   * @param int $technicianID
   *   A ID do técnico para o qual desejamos obter esta informação
   *
   * @throws RuntimeException
   *   Em caso de erros
   *
   * @return Collection
   *   A matriz com as informações de números de telefones
   */
  protected function getPhones(
    int $technicianID
  ): Collection
  {
    // Recupera as informações de números de telefones
    return TechnicianPhone::join('phonetypes',
          'technicianphones.phonetypeid', '=', 'phonetypes.phonetypeid'
        )
      ->where('technicianid', $technicianID)
      ->get([
          'technicianphones.serviceproviderid',
          'technicianphones.technicianid',
          'technicianphones.technicianphoneid',
          'technicianphones.phonetypeid',
          'phonetypes.name as phonetypename',
          'technicianphones.phonenumber'
        ])
    ;
  }

  /**
   * Recupera as informações de e-mails de um técnico.
   *
   * @param int $technicianID
   *   A ID do técnico para o qual desejamos obter esta informação
   *
   * @throws RuntimeException
   *   Em caso de erros
   *
   * @return Collection
   *   A matriz com as informações de e-mails
   */
  protected function getEmails(
    int $technicianID
  ): Collection
  {
    // Recupera as informações de dados de e-mail
    return TechnicianMailing::where('technicianid', $technicianID)
      ->get([
          'serviceproviderid',
          'technicianid',
          'technicianmailingid',
          'email'
        ])
    ;
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
   * Exibe um formulário para adição de um técnico, quando solicitado, e
   * confirma os dados enviados.
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
  public function add(
    Request $request,
    Response $response,
    array $args
  ): Response
  {
    $serviceProviderID = $args['serviceProviderID'];

    try {
      // Recupera os dados do contratante
      $contractor = $this->authorization->getContractor();

      // Recupera as informações de tipos de documentos
      $documentTypes = $this->getDocumentTypes();

      // Recupera as informações de gêneros
      $genders = $this->getGenders();

      // Recupera as informações de tipos de telefones
      $phoneTypes = $this->getPhoneTypes();

      // Recupera as informações de tipos de veículos
      $vehicleTypes = $this->getVehiclesTypes();

      // Recupera as informações de cores predominantes
      $vehicleColors = $this->getVehiclesColors();

      $serviceProvider = ServiceProvider::findOrFail($serviceProviderID);
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
      $this->debug("Processando à adição de técnico.");

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
        $this->debug('Os dados do técnico são VÁLIDOS');

        // Recupera os dados do técnico
        $technicianData = $this->validator->getValues();

        try
        {
          // Primeiro, verifica se não temos um técnico com o mesmo nome
          if (Technician::where("serviceproviderid", '=',
                    $serviceProviderID)
                ->whereRaw("public.unaccented(name) ILIKE "
                    . "public.unaccented('{$technicianData['name']}')"
                  )
                ->count() === 0) {
            // Agora verifica se não temos outro técnico com o mesmo cpf
            // neste prestador de serviços
            $save = true;
            if (Technician::where("serviceproviderid", '=',
                      $serviceProviderID)
                  ->where("cpf",
                      $technicianData['cpf']
                    )
                  ->count() !== 0) {
              $save = false;

              // Registra o erro
              $this->debug("Não foi possível inserir as informações do "
                . "técnico {name}. Já existe outro com o CPF {cpf}.",
                [
                  'name'  => $technicianData['name'],
                  'cpf' => $technicianData['cpf']
                ]
              );

              // Alerta o usuário
              $this->flashNow("error", "Já existe outro técnico com "
                . "o mesmo <b>CPF</b> "
                . "<i>'{cpf}'</i>.",
                [ 'cpf' => $technicianData['cpf'] ]
              );
            }

            if ($save) {
              // Grava o novo técnico

              // Separamos as informações dos dados de telefones dos
              // demais dados deste técnico
              $phonesData = $technicianData['phones'];
              unset($technicianData['phones']);

              // Separamos as informações dos dados de e-mails dos
              // demais dados deste técnico
              $emailsData = $technicianData['emails'];
              unset($technicianData['emails']);

              // Sempre mantém a UF do documento em maiúscula
              $technicianData['regionaldocumentstate'] =
                strtoupper($technicianData['regionaldocumentstate'])
              ;

              // --------------------------------------[ Gravação ]-----

              // Iniciamos a transação
              $this->DB->beginTransaction();

              // Incluímos um novo técnico
              $technician = new Technician();
              $technician->fill($technicianData);
              // Indicamos quem é o prestador de serviços
              $technician->serviceproviderid = $serviceProviderID;
              // Adicionamos as informações do contratante
              $technician->contractorid = $contractor->id;
              // Adicionamos as demais informações
              $technician->createdbyuserid =
                $this->authorization->getUser()->userid
              ;
              $technician->updatedbyuserid =
                $this->authorization->getUser()->userid
              ;
              $technician->save();
              $technicianID = $technician->technicianid;

              // Incluímos os dados de telefones para este técnico
              foreach($phonesData as $phoneData)
              {
                // Retiramos o campo de ID do telefone, pois os dados
                // tratam de um novo registro
                unset($phoneData['technicianphoneid']);

                // Incluímos um novo telefone desta unidade/filial
                $phone = new TechnicianPhone();
                $phone->fill($phoneData);
                $phone->serviceproviderid = $serviceProviderID;
                $phone->technicianid = $technicianID;
                $phone->save();
              }

              // Incluímos os dados de emails para este técnico
              foreach($emailsData as $emailData) {
                // Retiramos o campo de ID do e-mail, pois os dados
                // tratam de um novo registro
                unset($emailData['technicianmailingid']);

                // Como podemos não ter um endereço de e-mail, então
                // ignora caso ele não tenha sido fornecido
                if (trim($emailData['email']) !== '') {
                  // Incluímos um novo e-mail desta unidade/filial
                  $mailing = new TechnicianMailing();
                  $mailing->fill($emailData);
                  $mailing->serviceproviderid = $serviceProviderID;
                  $mailing->technicianid = $technicianID;
                  $mailing->save();
                }
              }

              // -------------------------------------------------------

              // Efetiva a transação
              $this->DB->commit();

              // Registra o sucesso
              $this->info("Cadastrado o técnico '{name}' com sucesso.",
                [ 'name'  => $technicianData['name'] ]
              );

              // Alerta o usuário
              $this->flash("success", "O técnico <i>'{name}'</i> foi "
                . "cadastrado com sucesso.",
                [ 'name'  => $technicianData['name'] ]
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
              . "técnico '{name}'. Já existe outro técnico com o mesmo "
              . "nome.",
              [ 'name'  => $technicianData['name'] ]
            );

            // Alerta o usuário
            $this->flashNow("error", "Já existe um técnico com o nome "
              . "<i>'{name}'</i>.",
              [ 'name' => $technicianData['name'] ]
            );
          }
        }
        catch(QueryException $exception)
        {
          // Reverte (desfaz) a transação
          $this->DB->rollBack();

          // Registra o erro
          $this->error("Não foi possível inserir as informações do "
            . "técnico '{name}'. Erro interno no banco de dados: "
            . "{error}",
            [
              'name'  => $technicianData['name'],
              'error' => $exception->getMessage()
            ]
          );

          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações do técnico. Erro interno no  banco de dados."
          );
        }
        catch(Exception $exception)
        {
          // Reverte (desfaz) a transação
          $this->DB->rollBack();

          // Registra o erro
          $this->error("Não foi possível inserir as informações do "
            . "técnico '{name}'. Erro interno: {error}",
            [
              'name'  => $technicianData['name'],
              'error' => $exception->getMessage()
            ]
          );

          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações do técnico. Erro interno."
          );
        }
      } else {
        $this->debug('Os dados do técnico são INVÁLIDOS');
        $messages = $this->validator->getFormatedErrors();
        foreach ($messages AS $message) {
          $this->debug($message);
        }
      }
    } else {
      // Carrega os dados iniciais para simplificar a digitação
      $this->validator->setValues([
        'serviceproviderid' => $serviceProviderID,
        'serviceprovidername' => $serviceProvider->name,
        'technicianid' => 0,
        'technicianistheprovider' => false,
        'regionaldocumenttype' => 1,
        'genderid' => 1,
        'cityid' => 0,
        'cityname' => '',
        'state' => '',
        'phones' => [
          0 => [
            'technicianphoneid' => 0,
            'phonenumber' => '',
            'phonetypeid' => 1
          ]
        ],
        'emails' => [
          0 => [
            'technicianmailingid' => 0,
            'email' => ''
          ]
        ]
      ]);
    }

    // Exibe um formulário para adição de um técnico

    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ERP\Home')
    );
    $this->breadcrumb->push('Cadastro', '');
    $this->breadcrumb->push('Prestadores de Serviços',
      $this->path('ERP\Cadastre\ServiceProviders')
    );
    $this->breadcrumb->push('Técnico', '');
    $this->breadcrumb->push('Adicionar',
      $this->path('ERP\Cadastre\ServiceProviders\Technicians\Add', [
        'serviceProviderID' => $serviceProviderID
      ])
    );

    // Registra o acesso
    $this->info("Acesso à adição de técnicos.");

    return $this->render($request, $response,
      'erp/cadastre/serviceproviders/technicians/technician.twig',
      [
        'formMethod' => 'POST',
        'documentTypes' => $documentTypes,
        'genders' => $genders,
        'phoneTypes' => $phoneTypes,
        'vehicleTypes' => $vehicleTypes,
        'vehicleColors' => $vehicleColors,
      ]
    );
  }

  /**
   * Exibe um formulário para edição de um técnico, quando solicitado, e
   * confirma os dados enviados.
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
    $technicianID = $args['technicianID'];

    try {
      // Recupera os dados do contratante
      $contractor = $this->authorization->getContractor();

      // Recupera as informações de tipos de documentos
      $documentTypes = $this->getDocumentTypes();

      // Recupera as informações de gêneros
      $genders = $this->getGenders();

      // Recupera as informações de tipos de telefones
      $phoneTypes = $this->getPhoneTypes();

      // Recupera as informações de tipos de veículos
      $vehicleTypes = $this->getVehiclesTypes();

      // Recupera as informações de cores predominantes
      $vehicleColors = $this->getVehiclesColors();
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
      // Recupera as informações do técnico
      $technician = Technician::join("entities AS serviceprovider",
            "technicians.serviceproviderid", '=', "serviceprovider.entityid"
          )
        ->join("cities",
            "technicians.cityid", '=', "cities.cityid"
          )
        ->join("documenttypes", "technicians.regionaldocumenttype",
            '=', "documenttypes.documenttypeid"
          )
        ->leftjoin('vehicletypes', 'technicians.vehicletypeid',
            '=', 'vehicletypes.vehicletypeid'
          )
        ->leftjoin('vehiclebrands', 'technicians.vehiclebrandid',
            '=', 'vehiclebrands.vehiclebrandid'
          )
        ->leftjoin('vehiclemodels', 'technicians.vehiclemodelid',
            '=', 'vehiclemodels.vehiclemodelid'
          )
        ->leftjoin('vehiclecolors', 'technicians.vehiclecolorid',
            '=', 'vehiclecolors.vehiclecolorid'
          )
        ->join("users as createduser", "technicians.createdbyuserid",
            '=', "createduser.userid"
          )
        ->join("users as updateduser", "technicians.updatedbyuserid",
            '=', "updateduser.userid"
          )
        ->where("technicians.serviceproviderid", '=', $serviceProviderID)
        ->where("technicians.technicianid", $technicianID)
        ->get([
            'serviceprovider.name AS serviceprovidername',
            'technicians.*',
            'documenttypes.name as regionaldocumenttypename',
            'cities.name as cityname',
            'cities.state as state',
            'vehiclebrands.name AS vehiclebrandname',
            'vehiclemodels.name AS vehiclemodelname',
            'vehiclecolors.name AS vehiclecolorname',
            'vehicletypes.name AS vehicletypename',
            'createduser.name as createdbyusername',
            'updateduser.name as updatedbyusername'
          ])
      ;

      if ( $technician->isEmpty() ) {
        throw new ModelNotFoundException("Não temos nenhum técnico "
          . "com o código {$technicianID} cadastrado"
        );
      }
      $technician = $technician
        ->first()
        ->toArray()
      ;

      // Recupera as informações de telefones
      $phones = $this
        ->getPhones($technicianID)
      ;
      if ( $phones->isEmpty() ) {
        // Criamos os dados de telefone em branco
        $technician['phones'] = [
          [
            'technicianphoneid' => 0,
            'phonetypeid' => 1,
            'phonenumber' => ''
          ]
        ];
      } else {
        $technician['phones'] =
          $phones ->toArray()
        ;
      }

      // E-mails
      $emails = $this
        ->getEmails($technicianID)
      ;
      if ( $emails->isEmpty() ) {
        // Criamos os dados de e-mail em branco
        $technician['emails'] = [
          [
            'technicianmailingid' => 0,
            'email' => ''
          ]
        ];
      } else {
        $technician['emails'] =
          $emails ->toArray()
        ;
      }
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar o técnico código "
        . "{technicianID}.",
        [ 'technicianID' => $technicianID ]
      );

      // Alerta o usuário
      $this->flash("error", "Não foi possível localizar este "
        . "prestadores de serviços."
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
      $this->debug("Processando à edição do técnico '{name}'.",
        [ 'name' => $technician['name'] ]
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
        $this->debug('Os dados do técnico são VÁLIDOS');

        // Recupera os dados modificados do técnico
        $technicianData = $this->validator->getValues();

        // Atualiza os dados do técnico

        // Separamos as informações dos dados de telefones dos demais
        // dados deste técnico
        $phonesData = $technicianData['phones'];
        unset($technicianData['phones']);

        // Separamos as informações dos dados de e-mails dos demais
        // dados deste técnico
        $emailsData = $technicianData['emails'];
        unset($technicianData['emails']);

        // Sempre mantém a UF do documento em maiúscula
        $technicianData['regionaldocumentstate'] =
          strtoupper($technicianData['regionaldocumentstate'])
        ;

        // Não permite modificar a informação do prestador de serviços
        // ao qual pertence
        unset($technicianData['serviceproviderid']);

        try
        {
          // =========================================[ Telefones ]=====
          // Recupera as informações de telefones deste técnico e separa
          // os dados para as operações de inserção, atualização e
          // remoção dos mesmos.
          // ===========================================================

          // ---------------------------------[ Pré-processamento ]-----

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
            if (empty($phoneData['technicianphoneid'])) {
              // Telefone novo
              unset($phoneData['technicianphoneid']);
              $newPhones[] = $phoneData;
            } else {
              // Telefone existente
              $heldPhones[] = $phoneData['technicianphoneid'];
              $updPhones[]  = $phoneData;
            }
          }

          // Recupera os telefones armazenados atualmente
          $currentPhones = TechnicianPhone::where('technicianid', $technicianID)
            ->get(['technicianphoneid'])
            ->toArray()
          ;
          $actPhones = [ ];
          foreach ($currentPhones as $phoneData) {
            $actPhones[] = $phoneData['technicianphoneid'];
          }

          // Verifica quais os telefones estavam na base de dados e
          // precisam ser removidos
          $delPhones = array_diff($actPhones, $heldPhones);

          // ===========================================[ E-mails ]=====
          // Recupera as informações de e-mails deste técnico e separa
          // os dados para as operações de inserção, atualização e
          // remoção dos mesmos.
          // ===========================================================

          // ---------------------------------[ Pré-processamento ]-----

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

            if (empty($emailData['technicianmailingid'])) {
              // E-mail novo
              unset($emailData['technicianmailingid']);
              $newEmails[] = $emailData;
            } else {
              // E-mail existente
              $heldEmails[] = $emailData['technicianmailingid'];
              $updEmails[]  = $emailData;
            }
          }

          // Recupera os e-mails armazenados atualmente
          $currentEmails = TechnicianMailing::where('technicianid', $technicianID)
            ->get(['technicianmailingid'])
            ->toArray()
          ;
          $actEmails = [ ];
          foreach ($currentEmails as $emailData) {
            $actEmails[] = $emailData['technicianmailingid'];
          }

          // Verifica quais os e-mails estavam na base de dados e
          // precisam ser removidos
          $delEmails = array_diff($actEmails, $heldEmails);

          // ------------------------------------------[ Gravação ]-----

          // Iniciamos a transação
          $this->DB->beginTransaction();

          // Grava as informações do prestador de serviços
          $technicianChanged = Technician::findOrFail($technicianID);
          $technicianChanged->fill($technicianData);
          $technicianChanged->updatedbyuserid =
            $this->authorization->getUser()->userid
          ;
          $technicianChanged->save();

          // -----------------------------------------[ Telefones ]-----

          // Primeiro apagamos os telefones removidos pelo usuário
          // durante a edição
          foreach ($delPhones as $phoneID) {
            // Apaga cada telefone
            $phone = TechnicianPhone::findOrFail($phoneID);
            $phone->delete();
          }

          // Agora inserimos os novos telefones
          foreach ($newPhones as $phoneData) {
            // Incluímos um novo telefone neste técnico
            unset($phoneData['technicianphoneid']);
            $phone = new TechnicianPhone();
            $phone->fill($phoneData);
            $phone->serviceproviderid = $serviceProviderID;
            $phone->technicianid = $technicianID;
            $phone->save();
          }

          // Por último, modificamos os telefones mantidos
          foreach($updPhones as $phoneData) {
            // Retira a ID do contato
            $phoneID = $phoneData['technicianphoneid'];
            unset($phoneData['technicianphoneid']);

            // Por segurança, nunca permite modificar qual a ID da
            // entidade mãe
            unset($phoneData['serviceproviderid']);
            unset($phoneData['technicianid']);

            // Grava as informações do telefone
            $phone = TechnicianPhone::findOrFail($phoneID);
            $phone->fill($phoneData);
            $phone->save();
          }

          // -------------------------------------------[ E-mails ]-----

          // Primeiro apagamos os e-mails removidos pelo usuário durante
          // a edição
          foreach ($delEmails as $emailID) {
            // Apaga cada e-mail
            $mailing = TechnicianMailing::findOrFail($emailID);
            $mailing->delete();
          }

          // Agora inserimos os novos e-mails
          foreach ($newEmails as $emailData) {
            // Incluímos um novo e-mail neste técnico
            unset($emailData['technicianmailingid']);
            $mailing = new TechnicianMailing();
            $mailing->fill($emailData);
            $mailing->serviceproviderid = $serviceProviderID;
            $mailing->technicianid = $technicianID;
            $mailing->save();
          }

          // Por último, modificamos os e-mails mantidos
          foreach($updEmails as $emailData) {
            // Retira a ID do contato
            $emailID = $emailData['technicianmailingid'];
            unset($emailData['technicianmailingid']);

            // Por segurança, nunca permite modificar qual a ID da
            // entidade mãe
            unset($emailData['serviceproviderid']);
            unset($emailData['technicianid']);

            // Grava as informações do e-mail
            $mailing = TechnicianMailing::findOrFail($emailID);
            $mailing->fill($emailData);
            $mailing->save();
          }

          // ===========================================================

          // Efetiva a transação
          $this->DB->commit();

          // Registra o sucesso
          $this->info("O técnico '{name}' foi modificado com sucesso.",
            [ 'name' => $technicianData['name'] ]
          );

          // Alerta o usuário
          $this->flash("success", "O técnico <i>'{name}'</i> foi "
              . "modificado com sucesso.",
            [ 'name' => $technicianData['name'] ]
          );

          // Registra o evento
          $this->debug("Redirecionando para {routeName}",
            [ 'routeName' => 'ERP\Cadastre\ServiceProviders' ]
          );

          // Redireciona para a página de gerenciamento de prestadores
          // de serviços
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
            . "técnico '{name}'. Erro interno: {error}",
            [ 'name'  => $technicianData['name'],
              'error' => $exception->getMessage() ]
          );

          // Alerta o usuário
          $this->flashNow("error", "Não foi possível modificar as "
            . "informações do técnico. Erro interno."
          );
        }
      } else {
        $this->debug('Os dados do técnico são INVÁLIDOS');
        $messages = $this->validator->getFormatedErrors();
        foreach ($messages AS $message) {
          $this->debug($message);
        }
      }
    } else {
      // Carrega os dados atuais
      $this->validator->setValues($technician);
    }

    // Exibe um formulário para edição de um técnico

    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ERP\Home')
    );
    $this->breadcrumb->push('Cadastro', '');
    $this->breadcrumb->push('Prestadores de Serviços',
      $this->path('ERP\Cadastre\ServiceProviders')
    );
    $this->breadcrumb->push('Técnico', '');
    $this->breadcrumb->push('Editar',
      $this->path('ERP\Cadastre\ServiceProviders\Edit', [
        'serviceProviderID' => $technicianID
      ])
    );

    // Registra o acesso
    $this->info("Acesso à edição do técnico '{name}'.",
      [ 'name' => $technician['name'] ]
    );

    // Renderiza a página
    return $this->render($request, $response,
      'erp/cadastre/serviceproviders/technicians/technician.twig',
      [
        'formMethod' => 'PUT',
        'documentTypes' => $documentTypes,
        'genders' => $genders,
        'phoneTypes' => $phoneTypes,
        'vehicleTypes' => $vehicleTypes,
        'vehicleColors' => $vehicleColors
      ]
    );
  }

  /**
   * Alterna o estado do bloqueio de um técnico de um prestador de
   * serviços.
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
      . "técnico de um prestador de serviços."
    );

    // Recupera o ID
    $serviceProviderID = $args['serviceProviderID'];
    $technicianID = $args['technicianID'];

    try
    {
      // Desbloqueia o técnico
      $technician = Technician::findOrFail($technicianID);
      $action = $technician->blocked
        ? "desbloqueado"
        : "bloqueado"
      ;
      $technician->blocked = !$technician->blocked;
      $technician->updatedbyuserid = $this
        ->authorization
        ->getUser()
        ->userid
      ;
      $technician->save();

      $message = "O técnico '{$technician->name}' "
        . "foi {$action} com sucesso."
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
      $this->error("Não foi possível localizar o técnico código "
        . "{technicianID} para alternar o estado do bloqueio.",
        [ 'technicianID' => $technicianID ])
      ;

      $message = "Não foi possível localizar o técnico para alternar "
      . "seu estado de bloqueio."
      ;
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível alternar o estado do bloqueio "
        . "do técncio '{name}'. Erro interno no banco de dados: "
        . "{error}.",
        [
          'name'  => $technician->name,
          'error' => $exception->getMessage()
        ]
      );

      $message = "Não foi possível alternar o estado do bloqueio do "
        . "técnico. Erro interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível alternar o estado do bloqueio do "
        . "técnico '{name}'. Erro interno: {error}.",
        [
          'name'  => $technician->name,
          'error' => $exception->getMessage()
        ]
      );

      $message = "Não foi possível alternar o estado do bloqueio do "
        . "técnico. Erro interno."
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
   * Gera um PDF para impressão das informações de um técnico.
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
    $serviceProviderID = $args['serviceProviderID'];
    $technicianID = $args['technicianID'];
    
    // Registra o acesso
    $this->debug("Processando à geração de PDF com as informações "
      . "cadastrais de um técnico."
    );

    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();

    // Recupera as informações do técnico
    $technicianID = $args['serviceProviderID'];
    $technician = Technician::join("cities",
          "technicians.cityid", '=', "cities.cityid"
        )
      ->join("documenttypes", "technicians.regionaldocumenttype",
          '=', "documenttypes.documenttypeid"
        )
      ->join('vehicletypes', 'technician.vehicletypeid',
          '=', 'vehicletypes.vehicletypeid'
        )
      ->join('vehiclebrands', 'technician.vehiclebrandid',
          '=', 'vehiclebrands.vehiclebrandid'
        )
      ->join('vehiclemodels', 'technician.vehiclemodelid',
          '=', 'vehiclemodels.vehiclemodelid'
        )
      ->join('vehiclecolors', 'technician.vehiclecolorid',
          '=', 'vehiclecolors.vehiclecolorid'
        )
      ->join("users as createduser", "technicians.createdbyuserid",
          '=', "createduser.userid"
        )
      ->join("users as updateduser", "technicians.updatedbyuserid",
          '=', "updateduser.userid"
        )
      ->where("entities.serviceproviderid", '=', $serviceProviderID)
      ->where("entities.technicianid", $technicianID)
      ->get([
          'technicians.*',
          'documenttypes.name as regionaldocumenttypename',
          'cities.name as cityname',
          'cities.state as state',
          'vehiclebrands.name AS vehiclebrandname',
          'vehiclemodels.name AS vehiclemodelname',
          'vehiclecolors.name AS vehiclecolorname',
          'vehicletypes.name AS vehicletypename',
          'createduser.name as createdbyusername',
          'updateduser.name as updatedbyusername'
        ])
    ;

    if ( $technician->isEmpty() ) {
      throw new ModelNotFoundException("Não temos nenhum técnico "
        . "com o código {$technicianID} cadastrado"
      );
    }
    $technician = $technician
      ->first()
      ->toArray()
    ;

    // Recupera as informações de telefones
    $phones = $this
      ->getPhones($technicianID)
    ;
    if ( $phones->isEmpty() ) {
      // Criamos os dados de telefone em branco
      $technician['phones'] = [
        [
          'technicianphoneid' => 0,
          'phonetypeid' => 1,
          'phonenumber' => ''
        ]
      ];
    } else {
      $technician['phones'] =
        $phones ->toArray()
      ;
    }

    // E-mails
    $emails = $this
      ->getEmails($technicianID)
    ;
    if ( $emails->isEmpty() ) {
      // Criamos os dados de e-mail em branco
      $technician['emails'] = [
        [
          'technicianmailingid' => 0,
          'email' => ''
        ]
      ];
    } else {
      $technician['emails'] =
        $emails ->toArray()
      ;
    }

    // Renderiza a página para poder converter em PDF
    $title = "Dados cadastrais de técnico";
    $PDFFileName = "Technician_ID_{$technicianID}.pdf";
    $page = $this->renderPDF(
      'erp/cadastre/serviceproviders/technicians/PDFtechnician.twig',
      [
        'technician' => $technician
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
      . "técnico '{name}'.",
      [ 'name' => $technician['name'] ]
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
