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
 * O controlador do gerenciamento de condições de pagamento.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Controllers\ERP\Parameterization\Financial;

use App\Models\DefinedMethod;
use App\Models\PaymentCondition;
use App\Models\PaymentForm;
use App\Models\PaymentMethod;
use Core\Controllers\Controller;
use Core\Controllers\QueryTrait;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Respect\Validation\Validator as V;
use Slim\Http\Request;
use Slim\Http\Response;
use RuntimeException;

class PaymentConditionsController
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
  protected function getValidationRules(bool $addition = false): array
  {
    $validationRules = [
      'paymentconditionid' => V::notBlank()
        ->intVal()
        ->setName('ID da condição de pagamento'),
      'name' => V::notBlank()
        ->length(2, 50)
        ->setName('Nome da condição de pagamento'),
      'paymentmethodid' => V::notBlank()
        ->intVal()
        ->setName('Meio de pagamento'),
      'definedmethodid' => V::intVal()
        ->setName('Configuração'),
      'paymentformid' => V::notBlank()
        ->intVal()
        ->setName('Forma de pagamento'),
      'paymentinterval' => V::length(1, 50)
        ->setName('Intervalo entre pagamentos'),
      'timeunit' => V::notBlank()
        ->in(['DAY', 'MONTH'])
        ->setName('Intervalo expresso em'),
      'usepaymentgateway' => V::boolVal()
        ->setName('Utiliza gateway de pagamentos'),
      'formatasbooklet' => V::boolVal()
        ->setName('Formatar como carnê de pagamentos'),
      'blocked' => V::boolVal()
        ->setName('Bloquear esta condição de pagamento')
    ];

    if ($addition) {
      // Ajusta as regras para adição
      unset($validationRules['paymentconditionid']);
    }

    return $validationRules;
  }

  /**
   * Recupera as informações de meios de pagamento.
   *
   * @return Collection
   *   A matriz com as informações de meios de pagamento
   *
   * @throws RuntimeException
   *   Em caso de não termos meios de pagamento
   */
  protected function getPaymentMethods(): Collection
  {
    try {
      // Recupera as informações de meios de pagamento
      $paymentMethods = PaymentMethod::orderBy("paymentmethodid")
        ->get([
            'paymentmethodid as id',
            'name',
            'requiressettings'
          ])
      ;

      if ( $paymentMethods->isEmpty() ) {
        throw new Exception("Não temos nenhum meio de pagamento "
          . "cadastrado"
        );
      }
    }
    catch (Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível obter as informações de meios de "
        . "pagamento. Erro: {error}.",
        [ 'error' => $exception->getMessage() ]
      );

      throw new RuntimeException("Não foi possível obter os meios de "
        . "pagamento"
      );
    }

    return $paymentMethods;
  }

  /**
   * Recupera as informações de configurações de meios de pagamento.
   *
   * @return Collection
   *   A matriz com as informações de meios de pagamento configurados
   *
   * @throws RuntimeException
   *   Em caso de não termos meios de pagamento configurados
   */
  protected function getDefinedMethods(): Collection
  {
    try {
      // Recupera as informações de meios de pagamento
      $definedMethods = DefinedMethod::where('blocked', 'false')
        ->orderBy("name")
        ->get([
            'definedmethodid as id',
            'name',
            'paymentmethodid'
          ])
      ;

      if ( $definedMethods->isEmpty() ) {
        throw new Exception("Não temos nenhum meio de pagamento "
          . "configurado cadastrado"
        );
      }
    }
    catch (Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível obter as informações de meios de "
        . "pagamento configurados. Erro: {error}.",
        [ 'error' => $exception->getMessage() ]
      );

      throw new RuntimeException("Não foi possível obter os meios de "
        . "pagamento configurados"
      );
    }

    return $definedMethods;
  }

  /**
   * Recupera as informações de formas de pagamento.
   *
   * @return Collection
   *   A matriz com as informações de formas de pagamento
   *
   * @throws RuntimeException
   *   Em caso de não termos formas de pagamento
   */
  protected function getPaymentForms(): Collection
  {
    try {
      // Recupera as informações de formas de pagamento
      $paymentForms = PaymentForm::orderBy("paymentformid")
        ->get([
            'paymentformid as id',
            'name'
          ])
      ;

      if ( $paymentForms->isEmpty() ) {
        throw new Exception("Não temos nenhuma forma de pagamento "
          . "cadastrado"
        );
      }
    }
    catch (Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível obter as informações de formas de "
        . "pagamento. Erro: {error}.",
        [ 'error' => $exception->getMessage() ]
      );

      throw new RuntimeException("Não foi possível obter as formas de "
        . "pagamento"
      );
    }

    return $paymentForms;
  }

  /**
   * Exibe a página inicial do gerenciamento de condições de pagamento.
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
    $this->breadcrumb->push('Condições de pagamento',
      $this->path('ERP\Parameterization\Financial\PaymentConditions')
    );
    
    // Registra o acesso
    $this->info("Acesso ao gerenciamento de condições de pagamento.");
    
    // Recupera os dados da sessão
    $paymentCondition = $this->session->get('paymentCondition',
      [ 'name' => '' ])
    ;
    
    // Renderiza a página
    return $this->render($request, $response,
      'erp/parameterization/financial/paymentconditions/paymentconditions.twig',
      [ 'paymentCondition' => $paymentCondition ])
    ;
  }
  
  /**
   * Recupera a relação das condições de pagamento em formato JSON.
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
    $this->debug("Acesso à relação de condições de pagamento.");
    
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
    $this->session->set('paymentCondition',
      [ 'name' => $name ]
    );
    
    try
    {
      // Formata o ordenamento dos campos
      $ORDER = $this->formatOrderBy($orderBy, $orderDir);

      // Inicializa a query
      $PaymentConditionQry = PaymentCondition::join('paymentmethods',
            'paymentconditions.paymentmethodid', '=',
            'paymentmethods.paymentmethodid'
          )
        ->join('paymentforms',
            'paymentconditions.paymentformid', '=',
            'paymentforms.paymentformid'
          )
        ->leftJoin('definedmethods',
            'paymentconditions.definedmethodid', '=',
            'definedmethods.definedmethodid'
          )
        ->where('paymentconditions.contractorid', '=',
            $this->authorization->getContractor()->id
          )
      ;
      
      // Acrescenta os filtros
      if (!empty($name)) {
        $PaymentConditionQry
          ->whereRaw("public.unaccented(paymentconditions.name) ILIKE "
              . "public.unaccented('%{$name}%')"
            );
      }

      // Conclui nossa consulta
      $paymentConditions = $PaymentConditionQry
        ->skip($start)
        ->take($length)
        ->orderByRaw($ORDER)
        ->get([
            'paymentconditions.paymentconditionid AS id',
            'paymentconditions.name',
            'paymentconditions.paymentmethodid',
            'paymentmethods.name AS paymentmethodname',
            $this->DB->raw(""
              . "CASE"
              . "  WHEN paymentconditions.definedmethodid > 0 THEN definedmethods.name"
              . "  ELSE 'Não necessária' "
              . "END AS definedmethodname"
            ),
            'paymentconditions.paymentformid',
            'paymentforms.name AS paymentformname',
            'paymentconditions.paymentinterval',
            'paymentconditions.timeunit',
            'paymentconditions.usepaymentgateway',
            'paymentconditions.formatasbooklet',
            'paymentconditions.blocked',
            $this->DB->raw('count(*) OVER() AS fullcount')
          ])
      ;
      
      if (count($paymentConditions) > 0) {
        $rowCount = $paymentConditions[0]->fullcount;
        
        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'draw' => $draw,
              'recordsTotal' => $rowCount,
              'recordsFiltered' => $rowCount,
              'data' => $paymentConditions
            ])
        ;
      } else {
        if (empty($name)) {
          $error = "Não temos condições de pagamento cadastradas.";
        } else {
          $error = "Não temos condições de pagamento cadastradas "
            . "cujo nome contém <i>{$name}</i>."
          ;
        }
      }
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno no banco de dados: {error}.",
        [ 'module' => 'condições de pagamento',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de condições "
        . "de pagamento. Erro interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno: {error}.",
        [ 'module' => 'condições de pagamento',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de condições "
        . "de pagamento. Erro interno."
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
   * Exibe um formulário para adição de uma condição de pagamento,
   * quando solicitado, e confirma os dados enviados.
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

      // Recupera as informações dos meios de pagamento
      $paymentMethods = $this->getPaymentMethods();

      // Recupera as informações dos meios de pagamento configurados
      $definedMethods = $this->getDefinedMethods();

      // Recupera as informações das formas de pagamento
      $paymentForms = $this->getPaymentForms();

      // Monta as unidades de tempo do intervalo
      $timeUnits = [
        [ 'id' => 'DAY', 'name' => 'Dias' ],
        [ 'id' => 'MONTH', 'name' => 'Meses' ]
      ];
    }
    catch (RuntimeException $exception)
    {
      // Alerta o usuário
      $this->flash("error", $exception->getMessage());

      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [ 'routeName' => 'ERP\Parameterization\Financial\PaymentConditions' ]
      );

      // Redireciona para a página de gerenciamento de condições de
      // pagamento
      return $this->redirect($response,
        'ERP\Parameterization\Financial\PaymentConditions');
    }

    // Verifica se estamos postando os dados
    if ($request->isPost()) {
      // Os dados estão sendo postados
      
      // Registra o acesso
      $this->debug("Processando à adição de condição de pagamento.");
      
      // Valida os dados
      $this->validator->validate($request,
        $this->getValidationRules(true)
      );

      if ($this->validator->isValid()) {
        // Grava as informações no banco de dados
        try
        {
          // Recupera os dados da condição de pagamento
          $paymentConditionData = $this->validator->getValues();
          
          // Primeiro, verifica se não temos uma condição de pagamento
          // com o mesmo nome neste contratante
          if (PaymentCondition::where("contractorid",
                  '=', $contractor->id)
                ->whereRaw("public.unaccented(name) ILIKE "
                    . "public.unaccented('{$paymentConditionData['name']}')"
                  )
                ->count() === 0) {
            // Se o meio de pagamento não for boleto, sempre força que a
            // formatação como boleto está inativa
            if ($paymentConditionData['paymentmethodid'] !== 5) {
              $paymentConditionData['formatasbooklet'] = 'false';
            }

            // Grava a nova condição de pagamento
            $paymentCondition = new PaymentCondition();
            $paymentCondition->fill($paymentConditionData);
            if ($paymentConditionData['definedmethodid'] == 0) {
              // Forçamos o valor como nulo
              $paymentCondition->definedmethodid = null;
            }
            // Adiciona o contratante e usuários atuais
            $paymentCondition->contractorid = $contractor->id;
            $paymentCondition->save();
            
            // Registra o sucesso
            $this->info("Cadastrada a condição de pagamento '{name}' "
              . "no contratante '{contractor}' com sucesso.",
              [ 'name'  => $paymentConditionData['name'],
                'contractor' => $contractor->name ]
            );
            
            // Alerta o usuário
            $this->flash("success", "A condição de pagamento "
              . "<i>'{name}'</i> foi cadastrado com sucesso.",
              [ 'name'  => $paymentConditionData['name'] ]
            );
            
            // Registra o evento
            $this->debug("Redirecionando para {routeName}",
              [ 'routeName' => 'ERP\Parameterization\Financial\PaymentConditions' ]
            );
            
            // Redireciona para a página de gerenciamento de condições
            // de pagamento
            return $this->redirect($response,
              'ERP\Parameterization\Financial\PaymentConditions'
            );
          } else {
            // Registra o erro
            $this->debug("Não foi possível inserir as informações da "
              . "condição de pagamento '{name}' do contratante "
              . "'{contractor}'. Já existe uma condição de pagamento "
              . "com o mesmo nome.",
              [ 'name'  => $paymentConditionData['name'],
                'contractor' => $contractor->name ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Já existe uma condição de "
              . "pagamento com o nome <i>'{name}'</i>.",
              [ 'name'  => $paymentConditionData['name'] ]
            );
          }
        }
        catch(QueryException $exception)
        {
          // Registra o erro
          $this->error("Não foi possível inserir as informações da "
            . "condição de pagamento '{name}' no contratante "
            . "'{contractor}'. Erro interno no banco de dados: "
            . "{error}.",
            [ 'name'  => $paymentConditionData['name'],
              'contractor' => $contractor->name,
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações da condição de pagamento. Erro interno no "
            . "banco de dados."
          );
        }
        catch(Exception $exception)
        {
          // Registra o erro
          $this->error("Não foi possível inserir as informações da "
            . "condição de pagamento '{name}' no contratante "
            . "'{contractor}'. Erro interno: {error}.",
            [ 'name'  => $paymentConditionData['name'],
              'contractor' => $contractor->name,
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações da condição de pagamento. Erro interno."
          );
        }
      }
    } else {
      // Carrega um conjunto de valores vazio
      $emptyPaymentCondition = [
        'paymentmethodid' => 1,
        'paymentformid' => 1,
        'paymentinterval' => '',
        'timeunit' => 'dias',
        'parameters' => '{ }'
      ];
      $this->validator->setValues($emptyPaymentCondition);
    }
    
    // Exibe um formulário para adição de uma condição de pagamento
    
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ERP\Home')
    );
    $this->breadcrumb->push('Parametrização', '');
    $this->breadcrumb->push('Financeiro', '');
    $this->breadcrumb->push('Condições de pagamento',
      $this->path('ERP\Parameterization\Financial\PaymentConditions')
    );
    $this->breadcrumb->push('Adicionar',
      $this->path('ERP\Parameterization\Financial\PaymentConditions\Add')
    );
    
    // Registra o acesso
    $this->info("Acesso à adição de condição de pagamento no "
      . "contratante '{contractor}'.",
      [ 'contractor' => $contractor->name ]
    );
    
    // Renderiza a página
    return $this->render($request, $response,
      'erp/parameterization/financial/paymentconditions/paymentcondition.twig',
      [ 'formMethod' => 'POST',
        'paymentMethods' => $paymentMethods,
        'definedMethods' => $definedMethods,
        'paymentForms' => $paymentForms,
        'timeUnits' => $timeUnits ])
    ;
  }
  
  /**
   * Exibe um formulário para edição de uma condição de pagamento, quando
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

      // Recupera as informações dos meios de pagamento
      $paymentMethods = $this->getPaymentMethods();

      // Recupera as informações dos meios de pagamento configurados
      $definedMethods = $this->getDefinedMethods();

      // Recupera as informações das formas de pagamento
      $paymentForms = $this->getPaymentForms();

      // Monta as unidades de tempo do intervalo
      $timeUnits = [
        [ 'id' => 'DAY', 'name' => 'Dias' ],
        [ 'id' => 'MONTH', 'name' => 'Meses' ]
      ];
    }
    catch (RuntimeException $exception)
    {
      // Alerta o usuário
      $this->flash("error", $exception->getMessage());

      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [ 'routeName' => 'ERP\Parameterization\Financial\PaymentConditions' ]
      );

      // Redireciona para a página de gerenciamento de condições de
      // pagamento
      return $this->redirect($response,
        'ERP\Parameterization\Financial\PaymentConditions');
    }
    
    try
    {
      // Recupera as informações da condição de pagamento
      $paymentConditionID = $args['paymentConditionID'];
      $paymentCondition = PaymentCondition::join('paymentmethods',
            'paymentconditions.paymentmethodid', '=',
            'paymentmethods.paymentmethodid'
          )
        ->leftjoin('definedmethods',
            'paymentconditions.definedmethodid', '=',
            'definedmethods.definedmethodid'
          )
        ->join('paymentforms',
            'paymentconditions.paymentformid', '=',
            'paymentforms.paymentformid'
          )
        ->where('paymentconditions.contractorid', '=', $contractor->id)
        ->where('paymentconditions.paymentconditionid', '=',
            $paymentConditionID
          )
        ->get([
            'paymentconditions.*',
            'paymentmethods.name AS paymentmethodname',
            $this->DB->raw(""
              . "CASE"
              . "  WHEN paymentconditions.definedmethodid IS NULL THEN 0"
              . "  ELSE paymentconditions.definedmethodid "
              . "END AS definedmethodid"
            ),
            $this->DB->raw(""
              . "CASE"
              . "  WHEN paymentconditions.definedmethodid > 0 THEN definedmethods.name"
              . "  ELSE 'Não necessária' "
              . "END AS definedmethodname"
            ),
            'paymentforms.name AS paymentformname'
          ])
      ;

      if ( $paymentCondition->isEmpty() ) {
        throw new ModelNotFoundException("Não temos nenhuma condição "
          . "de pagamento com o código {$paymentConditionID} cadastrada"
        );
      }

      $paymentCondition = $paymentCondition
        ->first()
        ->toArray()
      ;
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar a condição de pagamento "
        . "código {paymentconditionID}.",
        [ 'paymentconditionID' => $paymentConditionID ]
      );
      
      // Alerta o usuário
      $this->flash("error", "Não foi possível localizar esta condição "
        . "de pagamento."
      );
      
      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [ 'routeName' => 'ERP\Parameterization\Financial\PaymentConditions' ]
      );
      
      // Redireciona para a página de gerenciamento de condições de pagamento
      return $this->redirect($response,
        'ERP\Parameterization\Financial\PaymentConditions'
      );
    }
      
    // Verifica se estamos modificando os dados
    if ($request->isPut()) {
      // Os dados estão sendo modificados
      
      // Registra o acesso
      $this->debug("Processando à edição da condição de pagamento "
        . "'{name}' no contratante {contractor}.",
        [ 'name' => $paymentCondition['name'],
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
          // Recupera os dados modificados da condição de pagamento
          $paymentConditionData = $this->validator->getValues();
          
          // Primeiro, verifica se não mudamos o nome da condição
          $save = false;
          if (strtolower($paymentCondition['name']) != strtolower($paymentConditionData['name'])) {
            // Modificamos o nome da condição de pagamento, então
            // verifica se temos uma condição de pagamento com o mesmo
            // nome neste contratante antes de prosseguir
            if (PaymentCondition::where("contractorid", '=',
                      $contractor->id
                    )
                  ->whereRaw("public.unaccented(name) ILIKE "
                      . "public.unaccented('{$paymentConditionData['name']}')")
                  ->count() === 0) {
              $save = true;
            } else {
              // Registra o erro
              $this->debug("Não foi possível modificar as informações "
                . "da condição de pagamento '{name}' no contratante "
                . "'{contractor}'. Já existe uma condição de pagamento "
                . "com o mesmo nome.",
                [ 'name'  => $paymentConditionData['name'],
                  'contractor' => $contractor->name ]
              );
              
              // Alerta o usuário
              $this->flashNow("error", "Já existe uma condição de "
                . "pagamento com o mesmo nome."
              );
            }
          } else {
            $save = true;
          }
          
          if ($save) {
            // Se o meio de pagamento não for boleto, sempre força que a
            // formatação como boleto está inativa
            if ($paymentConditionData['paymentmethodid'] !== 5) {
              $paymentConditionData['formatasbooklet'] = 'false';
            }

            // Grava as informações da condição de pagamento
            $paymentConditionChanged = PaymentCondition::findOrFail($paymentConditionID);
            $paymentConditionChanged->fill($paymentConditionData);
            if ($paymentConditionData['definedmethodid'] == 0) {
              // Forçamos o valor como nulo
              $paymentConditionChanged->definedmethodid = null;
            }
            $paymentConditionChanged->save();
            
            // Registra o sucesso
            $this->info("Modificada a condição de pagamento '{name}' "
              . "no contratante '{contractor}' com sucesso.",
              [ 'name'  => $paymentConditionData['name'],
                'contractor' => $contractor->name ]
            );
            
            // Alerta o usuário
            $this->flash("success", "A condição de pagamento "
              . "<i>'{name}'</i> foi modificado com sucesso.",
              [ 'name'  => $paymentConditionData['name'] ]
            );
            
            // Registra o evento
            $this->debug("Redirecionando para {routeName}",
              [ 'routeName' => 'ERP\Parameterization\Financial\PaymentConditions' ]
            );
            
            // Redireciona para a página de gerenciamento de condições
            // de pagamento
            return $this->redirect($response,
              'ERP\Parameterization\Financial\PaymentConditions')
            ;
          }
        }
        catch(QueryException $exception)
        {
          // Registra o erro
          $this->error("Não foi possível modificar as informações da "
            . "condição de pagamento '{name}' no contratante "
            . "'{contractor}'. Erro interno no banco de dados: {error}",
            [ 'name'  => $paymentConditionData['name'],
              'contractor' => $contractor->name,
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível modificar as "
            . "informações da condição de pagamento. Erro interno no "
            . "banco de dados."
          );
        }
        catch(Exception $exception)
        {
          // Registra o erro
          $this->error("Não foi possível modificar as informações da "
            . "condição de pagamento '{name}' no contratante "
            . "'{contractor}'. Erro interno: {error}",
            [ 'name'  => $paymentConditionData['name'],
              'contractor' => $contractor->name,
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível modificar as "
            . "informações da condição de pagamento. Erro interno."
          );
        }
      }
    } else {
      // Carrega os dados atuais
      $this->validator->setValues($paymentCondition);
    }
    
    // Exibe um formulário para edição de uma condição de pagamento
    
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ERP\Home')
    );
    $this->breadcrumb->push('Parametrização', '');
    $this->breadcrumb->push('Financeiro', '');
    $this->breadcrumb->push('Condições de pagamento',
      $this->path('ERP\Parameterization\Financial\PaymentConditions')
    );
    $this->breadcrumb->push('Editar',
      $this->path('ERP\Parameterization\Financial\PaymentConditions\Edit',
        [ 'paymentConditionID' => $paymentConditionID ]
      )
    );
    
    // Registra o acesso
    $this->info("Acesso à edição da condição de pagamento '{name}' do "
      . "contratante '{contractor}'.",
      [ 'name' => $paymentCondition['name'],
        'contractor' => $contractor->name ]
    );
    
    // Renderiza a página
    return $this->render($request, $response,
      'erp/parameterization/financial/paymentconditions/paymentcondition.twig',
      [ 'formMethod' => 'PUT',
        'paymentMethods' => $paymentMethods,
        'definedMethods' => $definedMethods,
        'paymentForms' => $paymentForms,
        'timeUnits' => $timeUnits ]
    );
  }
  
  /**
   * Remove a condição de pagamento.
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
    $this->debug("Processando à remoção de condição de pagamento.");
    
    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();

    // Recupera o ID
    $paymentConditionID = $args['paymentConditionID'];

    try
    {
      // Recupera as informações da condição de pagamento
      $paymentCondition = PaymentCondition::where('contractorid',
            '=', $contractor->id
          )
        ->where('paymentconditionid', '=', $paymentConditionID)
        ->firstOrFail()
      ;

      // Agora apaga a condição de pagamento
      $paymentCondition->delete();
      // Registra o sucesso
      $this->info("A condição de pagamento '{name}' do contratante "
        . "'{contractor}' foi removido com sucesso.",
        [ 'name' => $paymentCondition->name,
          'contractor' => $contractor->name ]
      );
      
      // Informa que a remoção foi realizada com sucesso
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getParams(),
            'message' => "Removido a condição de pagamento "
              . "{$paymentCondition->name}",
            'data' => "Delete"
          ])
      ;

      //// Verifica se a condição de pagamento está em uso
      //if (BillingType::where("contractorid", '=', $contractor->id)
      //      ->where("paymentconditionid", '=', $paymentConditionID)
      //      ->count() === 0) {
      //  // Agora apaga a condição de pagamento
      //  $paymentCondition->delete();
      //  
      //  // Registra o sucesso
      //  $this->info("A condição de pagamento '{name}' do contratante "
      //    . "'{contractor}' foi removido com sucesso.",
      //    [ 'name' => $paymentCondition->name,
      //      'contractor' => $contractor->name ]
      //  );
      //  
      //  // Informa que a remoção foi realizada com sucesso
      //  return $response
      //    ->withHeader('Content-type', 'application/json')
      //    ->withJson([
      //        'result' => 'OK',
      //        'params' => $request->getParams(),
      //        'message' => "Removido a condição de pagamento "
      //          . "{$paymentCondition->name}",
      //        'data' => "Delete"
      //      ])
      //  ;
      //} else {
      //  // Registra o erro
      //  $this->error("Não foi possível remover as informações da "
      //    . "condição de pagamento '{name}' no contratante "
      //    . "'{contractor}'. A condição de pagamento está em uso.",
      //    [ 'name'  => $paymentCondition->name,
      //      'contractor' => $contractor->name ]
      //  );
      //  
      //  $message = "Não foi possível remover a condição de pagamento, "
      //    . "pois a mesma esta em uso."
      //  ;
      //}
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar a condição de pagamento "
        . "código {paymentConditionID} para remoção.",
        [ 'paymentConditionID' => $paymentConditionID ]
      );
      
      $message = "Não foi possível localizar a condição de pagamento "
        . "para remoção."
      ;
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível remover as informações da "
        . "condição de pagamento ID {id} no contratante "
        . "'{contractor}'. Erro interno no banco de dados: {error}.",
        [ 'id'  => $paymentConditionID,
          'contractor' => $contractor->name,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível remover a condição de pagamento. "
        . "Erro interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível remover as informações da "
        . "condição de pagamento ID {id} no contratante "
        . "'{contractor}'. Erro interno: {error}.",
        [ 'id'  => $paymentConditionID,
          'contractor' => $contractor->name,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível remover a condição de pagamento. "
        . "Erro interno."
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
   * Alterna o estado do bloqueio de uma condição de pagamento de um
   * contratante.
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
  public function toggleBlocked(Request $request, Response $response,
    array $args)
  {
    // Registra o acesso
    $this->debug("Processando à mudança do estado de bloqueio da "
      . "condição de pagamento."
    );
    
    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();
    
    // Recupera o ID
    $paymentConditionID = $args['paymentConditionID'];
    
    try
    {
      // Recupera as informações da condição de pagamento
      $paymentCondition = PaymentCondition::where('contractorid',
            '=', $contractor->id
          )
        ->where('paymentconditionid', '=', $paymentConditionID)
        ->firstOrFail()
      ;
      
      // Alterna o estado do bloqueio da condição de pagamento
      $action = $paymentCondition->blocked
        ? "desbloqueada"
        : "bloqueada"
      ;
      $paymentCondition->blocked = !$paymentCondition->blocked;

      // Adiciona o usuário responsável pela modificação
      $paymentCondition->updatedbyuserid =
        $this->authorization->getUser()->userid
      ;
      $paymentCondition->save();
      
      // Registra o sucesso
      $this->info("A condição de pagamento '{name}' do contratante "
        . "'{contractor}' foi {action} com sucesso.",
        [ 'name' => $paymentCondition->name,
          'contractor' => $contractor->name,
          'action' => $action ]
      );
      
      // Informa que a alteração do estado do bloqueio da condição de
      // pagamento foi realizada com sucesso
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getParams(),
            'message' => "A condição de pagamento "
              . "{$paymentCondition->name} foi {$action} com sucesso.",
            'data' => "Delete" ])
      ;
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar a condição de pagamento "
        . "código {paymentConditionID} no contratante '{contractor}' "
        . "para alternar o estado do bloqueio.",
        [ 'paymentConditionID' => $paymentConditionID,
          'contractor' => $contractor->name ]
      );
      
      $message = "Não foi possível localizar a condição de pagamento "
        . "para alternar o estado do bloqueio."
      ;
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível alternar o estado do bloqueio "
        . "da condição de pagamento '{name}' no contratante "
        . "'{contractor}'. Erro interno no banco de dados: {error}.",
        [ 'name'  => $paymentCondition->name,
          'contractor' => $contractor->name,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível alternar o estado do bloqueio da "
        . "condição de pagamento. Erro interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível alternar o estado do bloqueio "
        . "da condição de pagamento '{name}' no contratante "
        . "'{contractor}'. Erro interno: {error}.",
        [ 'name'  => $paymentCondition->name,
          'contractor' => $contractor->name,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível alternar o estado do bloqueio da "
        . "condição de pagamento. Erro interno."
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
