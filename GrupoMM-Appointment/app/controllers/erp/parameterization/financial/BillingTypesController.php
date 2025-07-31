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
 * O controlador do gerenciamento de tipos de cobranças.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Controllers\ERP\Parameterization\Financial;

use App\Models\BillingMoment;
use App\Models\BillingType;
use App\Models\InstallmentType;
use Core\Controllers\Controller;
use Core\Controllers\QueryTrait;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Respect\Validation\Validator as V;
use RuntimeException;
use Slim\Http\Request;
use Slim\Http\Response;

class BillingTypesController
  extends Controller
{
  /**
   * Os métodos para manipular queries.
   */
  use QueryTrait;

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
      'billingtypeid' => V::notBlank()
        ->intVal()
        ->setName('ID do tipo de cobrança'),
      'name' => V::notBlank()
        ->length(2, 60)
        ->setName('Nome do tipo de cobrança'),
      'description' => V::optional(
            V::notBlank()
          )
        ->setName('Descrição'),
      'rateperequipment' => V::boolVal()
        ->setName('Cobrar por equipamento instalado'),
      'preapproved' => V::boolVal()
        ->setName('Pré-aprovado'),
      'inattendance' => V::boolVal()
        ->setName('Cobrança realizada por ordem de serviço'),
      'billingmoments' => V::notBlank()
        ->setName('Momento em que a cobrança pode ser realizada'),
      'executiontime' => V::time('H:i')
        ->setName('Tempo de execução do serviço'),
      'installmenttypeid' => V::intVal()
        ->min(0, true)
        ->setName('Parcelamento disponível')
    ];

    if ($addition) {
      // Ajusta as regras para adição
      unset($validationRules['billingtypeid']);
    }

    return $validationRules;
  }

  /**
   * Recupera as informações dos momentos da cobrança.
   *
   * @return Collection
   *   A matriz com as informações dos momentos da cobrança
   *
   * @throws RuntimeException
   *   Em caso de não termos momentos da cobrança
   */
  protected function getBillingMoments(): Collection
  {
    try {
      // Recupera as informações de formatos de cobrança
      $billingMoments = BillingMoment::orderBy('billingmomentid')
        ->get([
            'billingmomentid AS id',
            'name'
          ])
      ;

      if ( $billingMoments->isEmpty() ) {
        throw new Exception("Não temos nenhum momento da cobrança "
          . "cadastrado"
        );
      }
    }
    catch (Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível obter as informações de momentos "
        . "da cobrança. Erro: {error}.",
        [ 'error' => $exception->getMessage() ]
      );

      throw new RuntimeException("Não foi possível obter os momentos "
        . "da cobrança"
      );
    }

    return $billingMoments;
  }


  /**
   * Recupera as informações de tipos de parcelamento.
   *
   * @param int $contractorID
   *   A ID do contratante para o qual desejamos obter os tipos de
   *   parcelamento disponíveis
   *
   * @return Collection
   *   A matriz com as informações de tipos de parcelamento
   *
   * @throws RuntimeException
   *   Em caso de não termos tipos de parcelamento
   */
  protected function getInstallmentTypes(int $contractorID): Collection
  {
    try {
      // Recupera as informações de tipos de parcelamento (modelos) que
      // sejam deste contratante e que estejam ativos
      $installmentTypes = InstallmentType::where('contractorid',
            '=', $contractorID
          )
        ->orderBy('name')
        ->get([
            'installmenttypeid AS id',
            'name'
          ])
      ;

      if ( $installmentTypes->isEmpty() ) {
        throw new Exception("Não temos nenhum tipo de parcelamento "
          . "cadastrado"
        );
      }
    }
    catch (Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível obter as informações de tipos de "
        . "parcelamentos. Erro: {error}.",
        [ 'error' => $exception->getMessage() ]
      );

      throw new RuntimeException("Não foi possível obter os tipos de "
        . "parcelamento"
      );
    }

    return $installmentTypes;
  }


  /**
   * Exibe a página inicial do gerenciamento de tipos de cobranças.
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
    $this->breadcrumb->push('Parametrização', '');
    $this->breadcrumb->push('Financeiro', '');
    $this->breadcrumb->push('Tipos de cobranças',
      $this->path('ERP\Parameterization\Financial\BillingTypes')
    );

    // Registra o acesso
    $this->info("Acesso ao gerenciamento de tipos de cobranças.");

    // Recupera os dados da sessão
    $billingType = $this->session->get('billingtype',
      [ 'name' => '' ])
    ;

    // Renderiza a página
    return $this->render($request, $response,
      'erp/parameterization/financial/billingtypes/billingtypes.twig',
      [ 'billingtype' => $billingType ])
    ;
  }

  /**
   * Recupera a relação das tipos de cobranças em formato JSON.
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
    $this->debug("Acesso à relação de tipos de cobranças.");

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
    $name = $postParams['searchValue'];

    // Seta os valores da última pesquisa na sessão
    $this->session->set('billingtype',
      [ 'name' => $name ]
    );

    // Corrige o escape dos campos
    $name = addslashes($name);

    try
    {
      // Formata o ordenamento dos campos
      $ORDER = $this->formatOrderBy($orderBy, $orderDir);

      // Inicializa a query
      $BillingTypeQry = BillingType::leftJoin('installmenttypes', 'billingtypes.installmenttypeid',
            '=', 'installmenttypes.installmenttypeid'
          )
      ;

      // Acrescenta os filtros
      if (!empty($name)) {
        $BillingTypeQry
          ->whereRaw("public.unaccented(billingtypes.name) ILIKE "
              . "public.unaccented('%{$name}%')"
            )
        ;
      }

      // Conclui nossa consulta
      $billingTypes = $BillingTypeQry
        ->where('billingtypes.contractorid', '=', $this->authorization->getContractor()->id)
        ->skip($start)
        ->take($length)
        ->orderByRaw($ORDER)
        ->get([
            'billingtypes.billingtypeid AS id',
            'billingtypes.createdat',
            'billingtypes.name',
            'billingtypes.inattendance',
            'billingtypes.rateperequipment',
            'billingtypes.preapproved',
            $this->DB->raw('erp.getBillingMoments(billingtypes.billingmoments) AS moments'),
            'billingtypes.executiontime',
            'billingtypes.installmenttypeid',
            $this->DB->raw('CASE'
              . '  WHEN billingtypes.installmenttypeid > 0 THEN installmenttypes.name'
              . '  ELSE \'Não pode ser parcelado\' '
              . 'END AS installmenttypename'),
            $this->DB->raw('count(*) OVER() AS fullcount')
          ])
      ;

      if (count($billingTypes) > 0) {
        $rowCount = $billingTypes[0]->fullcount;

        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'draw' => $draw,
              'recordsTotal' => $rowCount,
              'recordsFiltered' => $rowCount,
              'data' => $billingTypes
            ])
        ;
      } else {
        if (empty($name)) {
          $error = "Não temos tipos de cobranças cadastrados.";
        } else {
          $error = "Não temos tipos de cobranças cadastradas cujo nome "
            . "contém <i>{$name}</i>."
          ;
        }
      }
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno no banco de dados: {error}.",
        [ 'module' => 'tipos de cobranças',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de tipos de "
        . "cobranças. Erro interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno: {error}.",
        [ 'module' => 'tipos de cobranças',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de tipos de "
        . "cobranças. Erro interno."
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
   * Exibe um formulário para adição de um tipo de cobrança, quando
   * solicitado, e confirma os dados enviados.
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

      // Recupera as informações dos momentos das cobranças
      $billingMoments = $this->getBillingMoments();

      // Recupera as informações dos tipos de parcelamentos
      $installmentTypes = $this->getInstallmentTypes($contractor->id);
    }
    catch (RuntimeException $exception)
    {
      // Alerta o usuário
      $this->flash("error", $exception->getMessage());

      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [ 'routeName' => 'ERP\Parameterization\Financial\BillingTypes' ]
      );

      // Redireciona para a página de gerenciamento de tipos de cobrança
      return $this->redirect($response,
        'ERP\Parameterization\Financial\BillingTypes'
      );
    }

    // Verifica se estamos postando os dados
    if ($request->isPost()) {
      // Os dados estão sendo postados

      // Registra o acesso
      $this->debug("Processando à adição de tipo de cobrança.");

      // Valida os dados
      $this->validator->validate($request,
        $this->getValidationRules(true)
      );

      if ($this->validator->isValid()) {
        // Grava as informações no banco de dados
        try
        {
          // Recupera os dados do tipo de cobrança
          $billingTypeData = $this->validator->getValues();

          // Verifica se é um produto ou serviço
          if ($billingTypeData['inattendance'] === "true") {
            // É um serviço, então força alguns valores
            $toRemove = [5];
            $billingTypeData['billingmoments'] = array_diff(
              $billingTypeData['billingmoments'], $toRemove
            );
            $billingTypeData['rateperequipment'] = "true";
          } else {
            // É um produto, então força alguns valores
            $billingTypeData['preapproved'] = "false";
            $billingTypeData['executiontime'] = "00:00";
          }

          // Primeiro, verifica se não temos um tipo de cobrança com
          // o mesmo nome neste contratante
          if (BillingType::where("contractorid", '=', $contractor->id)
                ->whereRaw("public.unaccented(name) ILIKE "
                    . "public.unaccented('{$billingTypeData['name']}')"
                  )
                ->count() === 0) {
            // Grava a novo tipo de cobrança
            $billingType = new BillingType();
            $billingType->fill($billingTypeData);
            // Adiciona o contratante e usuários atuais
            $billingType->contractorid = $contractor->id;
            $billingType->createdbyuserid =
              $this->authorization->getUser()->userid
            ;
            $billingType->updatedbyuserid =
              $this->authorization->getUser()->userid
            ;
            $billingType->save();

            // Registra o sucesso
            $this->info("Cadastrado o tipo de cobrança '{name}' no "
              . "contratante '{contractor}' com sucesso.",
              [ 'name'  => $billingTypeData['name'],
                'contractor' => $contractor->name ]
            );

            // Alerta o usuário
            $this->flash("success", "O tipo de cobrança <i>'{name}'</i> "
              . "foi cadastrado com sucesso.",
              [ 'name'  => $billingTypeData['name'] ]
            );

            // Registra o evento
            $this->debug("Redirecionando para {routeName}",
              [ 'routeName' => 'ERP\Parameterization\Financial\BillingTypes' ]
            );

            // Redireciona para a página de gerenciamento de tipos de cobranças
            return $this->redirect($response,
              'ERP\Parameterization\Financial\BillingTypes'
            );
          } else {
            // Registra o erro
            $this->debug("Não foi possível inserir as informações do "
              . "tipo de cobrança '{name}' do contratante "
              . "'{contractor}'. Já existe um tipo de cobrança com o "
              . "mesmo nome.",
              [ 'name'  => $billingTypeData['name'],
                'contractor' => $contractor->name ]
            );

            // Alerta o usuário
            $this->flashNow("error", "Já existe um tipo de cobrança "
              . "com o nome <i>'{name}'</i>.",
              [ 'name'  => $billingTypeData['name'] ]
            );
          }
        }
        catch(QueryException $exception)
        {
          // Registra o erro
          $this->error("Não foi possível inserir as informações do "
            . "tipo de cobrança '{name}' no contratante "
            . "'{contractor}'. Erro interno no banco de dados: "
            . "{error}.",
            [ 'name'  => $billingTypeData['name'],
              'contractor' => $contractor->name,
              'error' => $exception->getMessage() ]
          );

          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações do tipo de cobrança. Erro interno no banco "
            . "de dados."
          );
        }
        catch(Exception $exception)
        {
          // Registra o erro
          $this->error("Não foi possível inserir as informações do "
            . "tipo de cobrança '{name}' no contratante "
            . "'{contractor}'. Erro interno: {error}.",
            [ 'name'  => $billingTypeData['name'],
              'contractor' => $contractor->name,
              'error' => $exception->getMessage() ]
          );

          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações do tipo de cobrança. Erro interno."
          );
        }
      }
    } else {
      // Carrega um conjunto de valores vazio
      $emptyBillingType = [
        'rateperequipment'   => "true",
        'executiontime'      => "00:00",
        'billingmoments'     => [ 0 => 1 ],
        'installmenttypeid'  => 0
      ];
      $this->validator->setValues($emptyBillingType);
    }

    // Exibe um formulário para adição de um tipo de cobrança

    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ERP\Home')
    );
    $this->breadcrumb->push('Parametrização', '');
    $this->breadcrumb->push('Financeiro', '');
    $this->breadcrumb->push('Tipos de cobranças',
      $this->path('ERP\Parameterization\Financial\BillingTypes')
    );
    $this->breadcrumb->push('Adicionar',
      $this->path('ERP\Parameterization\Financial\BillingTypes\Add')
    );

    // Registra o acesso
    $this->info("Acesso à adição de tipo de cobrança no contratante "
      . "'{contractor}'.",
      [ 'contractor' => $contractor->name ]
    );

    // Renderiza a página
    return $this->render($request, $response,
      'erp/parameterization/financial/billingtypes/billingtype.twig',
      [ 'formMethod' => 'POST',
        'billingMoments' => $billingMoments,
        'installmentTypes' => $installmentTypes ])
    ;
  }

  /**
   * Exibe um formulário para edição de um tipo de cobrança, quando
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
  public function edit(Request $request, Response $response,
    array $args)
  {
    try {
      // Recupera os dados do contratante
      $contractor = $this->authorization->getContractor();

      // Recupera as informações dos momentos das cobranças
      $billingMoments = $this->getBillingMoments();

      // Recupera as informações dos tipos de parcelamentos
      $installmentTypes = $this->getInstallmentTypes($contractor->id);
    }
    catch (RuntimeException $exception)
    {
      // Alerta o usuário
      $this->flash("error", $exception->getMessage());

      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [ 'routeName' => 'ERP\Parameterization\Financial\BillingTypes' ]
      );

      // Redireciona para a página de gerenciamento de tipos de cobrança
      return $this->redirect($response,
        'ERP\Parameterization\Financial\BillingTypes'
      );
    }

    try
    {
      // Recupera as informações do tipo de cobrança
      $billingTypeID = $args['billingTypeID'];
      $billingType = BillingType::leftJoin('installmenttypes', 'billingtypes.installmenttypeid',
            '=', 'installmenttypes.installmenttypeid'
          )
        ->join('users AS createduser', 'billingtypes.createdbyuserid',
            '=', 'createduser.userid'
          )
        ->join('users AS updateduser', 'billingtypes.updatedbyuserid',
            '=', 'updateduser.userid'
          )
        ->where('billingtypes.contractorid', '=', $contractor->id)
        ->where('billingtypes.billingtypeid', '=', $billingTypeID)
        ->get([
            'billingtypes.*',
            $this->DB->raw(""
              . "CASE"
              . "  WHEN billingtypes.installmenttypeid IS NULL THEN 0 "
              . "  ELSE billingtypes.installmenttypeid "
              . "END AS installmenttypeid"),
            $this->DB->raw(""
              . "CASE"
              . "  WHEN billingtypes.installmenttypeid IS NULL THEN 'Não permitir parcelamento' "
              . "  ELSE installmenttypes.name "
              . "END AS installmenttypename"),
            'createduser.name AS createdbyusername',
            'updateduser.name AS updatedbyusername'
          ])
      ;

      if ( $billingType->isEmpty() ) {
        throw new ModelNotFoundException("Não temos nenhum tipo de "
          . "cobrança com o código {$billingTypeID} cadastrado"
        );
      }
      $billingType = $billingType
        ->first()
        ->toArray()
      ;
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar o tipo de cobrança "
        . "código {billingTypeID}.",
        [ 'billingTypeID' => $billingTypeID ]
      );

      // Alerta o usuário
      $this->flash("error", "Não foi possível localizar este tipo de "
        . "cobrança."
      );

      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [ 'routeName' => 'ERP\Parameterization\Financial\BillingTypes' ]
      );

      // Redireciona para a página de gerenciamento de tipos de cobrança
      return $this->redirect($response,
        'ERP\Parameterization\Financial\BillingTypes'
      );
    }

    // Verifica se estamos modificando os dados
    if ($request->isPut()) {
      // Os dados estão sendo modificados

      // Registra o acesso
      $this->debug("Processando à edição do tipo de cobrança '{name}' "
        . "no contratante {contractor}.",
        [ 'name' => $billingType['name'],
          'contractor' => $contractor->name ]
      );

      // Valida os dados
      $this->validator->validate($request,
        $this->getValidationRules(false)
      );

      if ($this->validator->isValid()) {
        // Grava as informações no banco de dados
        try
        {
          // Recupera os dados modificados do tipo de cobrança
          $billingTypeData = $this->validator->getValues();

          // Verifica se é um produto ou serviço
          if ($billingTypeData['inattendance'] === "true") {
            // É um serviço, então força alguns valores
            $toRemove = [5];
            $billingTypeData['billingmoments'] = array_diff(
              $billingTypeData['billingmoments'], $toRemove
            );
            $billingTypeData['rateperequipment'] = "true";
          } else {
            // É um produto, então força alguns valores
            $billingTypeData['preapproved'] = "false";
            $billingTypeData['executiontime'] = "00:00";
          }

          // Primeiro, verifica se não mudamos o nome do tipo de
          // cobrança
          $save = false;
          if (strtolower($billingType['name']) !== strtolower($billingTypeData['name'])) {
            // Modificamos o nome do tipo de cobrança, então verifica
            // se temos um tipo de cobrança com o mesmo nome neste
            // contratante antes de prosseguir
            if (BillingType::where("contractorid", '=',
                      $contractor->id
                    )
                  ->whereRaw("public.unaccented(name) ILIKE "
                      . "public.unaccented('{$billingTypeData['name']}')"
                    )
                  ->count() === 0) {
              $save = true;
            } else {
              // Registra o erro
              $this->debug("Não foi possível modificar as informações "
                . "do tipo de cobrança '{name}' no contratante "
                . "'{contractor}'. Já existe um tipo de cobrança com o "
                . "mesmo nome.",
                [ 'name'  => $billingTypeData['name'],
                  'contractor' => $contractor->name ]
              );

              // Alerta o usuário
              $this->flashNow("error", "Já existe um tipo de cobrança "
                . "com o mesmo nome."
              );
            }
          } else {
            $save = true;
          }

          if ($save) {
            // Grava as informações do tipo de cobrança
            $sql = ""
              . "UPDATE erp.billingTypes"
              . "   SET name = '{$billingTypeData['name']}',"
              . "       description = '{$billingTypeData['description']}',"
              . "       inattendance = {$billingTypeData['inattendance']},"
              . "       rateperequipment = {$billingTypeData['rateperequipment']},"
              . "       preapproved = {$billingTypeData['preapproved']},"
              . "       billingmoments = '{" . implode(',', $billingTypeData['billingmoments']) . "}',"
              . "       executiontime = '{$billingTypeData['executiontime']}'::time,"
              . "       installmenttypeid = {$billingTypeData['installmenttypeid']},"
              . "       updatedat = CURRENT_TIMESTAMP,"
              . "       updatedbyuserid = " . $this->authorization->getUser()->userid
              . " WHERE billingtypeID = {$billingTypeID};"
            ;
            $this->DB->select($sql);
            //$billingTypeChanged = BillingType::findOrFail($billingTypeID);

            //$billingTypeChanged->fill($billingTypeData);
            //// Adiciona o usuário responsável pela modificação
            //$billingTypeChanged->updatedbyuserid =
            //  $this->authorization->getUser()->userid
            //;
            ////var_dump($billingTypeChanged->billingmoments); exit();
            ////var_dump($billingTypeChanged); exit();
            //$billingTypeChanged->save();

            // Registra o sucesso
            $this->info("Modificado o tipo de cobrança '{name}' no "
              . "contratante '{contractor}' com sucesso.",
              [ 'name'  => $billingTypeData['name'],
                'contractor' => $contractor->name ]
            );

            // Alerta o usuário
            $this->flash("success", "O tipo de cobrança <i>'{name}'"
              . "</i> foi modificado com sucesso.",
              [ 'name'  => $billingTypeData['name'] ]
            );

            // Registra o evento
            $this->debug("Redirecionando para {routeName}",
              [ 'routeName' => 'ERP\Parameterization\Financial\BillingTypes' ]
            );

            // Redireciona para a página de gerenciamento de tipos de cobranças
            return $this->redirect($response,
              'ERP\Parameterization\Financial\BillingTypes')
            ;
          }
        }
        catch(QueryException $exception)
        {
          // Registra o erro
          $this->error("Não foi possível modificar as informações do "
            . "tipo de cobrança '{name}' no contratante "
            . "'{contractor}'. Erro interno no banco de dados: {error}",
            [ 'name'  => $billingTypeData['name'],
              'contractor' => $contractor->name,
              'error' => $exception->getMessage() ]
          );

          // Alerta o usuário
          $this->flashNow("error", "Não foi possível modificar as "
            . "informações do tipo de cobrança. Erro interno no banco "
            . "de dados."
          );
        }
        catch(Exception $exception)
        {
          // Registra o erro
          $this->error("Não foi possível modificar as informações do "
            . "tipo de cobrança '{name}' no contratante "
            . "'{contractor}'. Erro interno: {error}",
            [ 'name'  => $billingTypeData['name'],
              'contractor' => $contractor->name,
              'error' => $exception->getMessage() ]
          );

          // Alerta o usuário
          $this->flashNow("error", "Não foi possível modificar as "
            . "informações do tipo de cobrança. Erro interno."
          );
        }
      }
    } else {
      // Carrega os dados atuais
      $this->validator->setValues($billingType);
    }

    // Exibe um formulário para edição de um tipo de cobrança

    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ERP\Home')
    );
    $this->breadcrumb->push('Parametrização', '');
    $this->breadcrumb->push('Financeiro', '');
    $this->breadcrumb->push('Tipos de cobranças',
      $this->path('ERP\Parameterization\Financial\BillingTypes')
    );
    $this->breadcrumb->push('Editar',
      $this->path('ERP\Parameterization\Financial\BillingTypes\Edit', [
        'billingTypeID' => $billingTypeID
      ])
    );

    // Registra o acesso
    $this->info("Acesso à edição do tipo de cobrança '{name}' do "
      . "contratante '{contractor}'.",
      [ 'name' => $billingType['name'],
        'contractor' => $contractor->name ]
    );

    // Renderiza a página
    return $this->render($request, $response,
      'erp/parameterization/financial/billingtypes/billingtype.twig',
      [ 'formMethod' => 'PUT',
        'billingMoments' => $billingMoments,
        'installmentTypes' => $installmentTypes ])
    ;
  }

  /**
   * Remove o tipo de cobrança.
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
  public function delete(Request $request, Response $response,
    array $args)
  {
    // Registra o acesso
    $this->debug("Processando à remoção de tipo de cobrança.");

    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();

    // Recupera o ID
    $billingTypeID = $args['billingTypeID'];

    try
    {
      // Recupera as informações do tipo de cobrança
      $billingType = BillingType::where('contractorid',
            '=', $contractor->id
          )
        ->where('billingtypeid', '=', $billingTypeID)
        ->firstOrFail()
      ;

      // Agora apaga o tipo de cobrança
      $billingType->delete();

      // Registra o sucesso
      $this->info("O tipo de cobrança '{name}' do contratante "
        . "'{contractor}' foi removido com sucesso.",
        [ 'name' => $billingType->name,
          'contractor' => $contractor->name ]
      );

      // Informa que a remoção foi realizada com sucesso
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getParams(),
            'message' => "Removido o tipo de cobrança "
              . "{$billingType->name}",
            'data' => "Delete"
          ])
      ;
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar o tipo de cobrança "
        . "código {billingTypeID} para remoção.",
        [ 'billingTypeID' => $billingTypeID ]
      );

      $message = "Não foi possível localizar o tipo de cobrança para "
        . "remoção."
      ;
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível remover as informações do tipo "
        . "de cobrança ID {id} no contratante '{contractor}'. Erro "
        . "interno no banco de dados: {error}.",
        [ 'id'  => $billingTypeID,
          'contractor' => $contractor->name,
          'error' => $exception->getMessage() ]
      );

      $message = "Não foi possível remover o tipo de cobrança. Erro "
        . "interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível remover as informações do tipo "
        . "de cobrança ID {id} no contratante '{contractor}'. Erro "
        . "interno: {error}.",
        [ 'id'  => $billingTypeID,
          'contractor' => $contractor->name,
          'error' => $exception->getMessage() ]
      );

      $message = "Não foi possível remover o tipo de cobrança. Erro "
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
}
