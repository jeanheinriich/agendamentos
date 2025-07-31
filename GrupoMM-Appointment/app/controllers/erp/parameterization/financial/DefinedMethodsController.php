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
 * O controlador do gerenciamento dos meios de pagamentos configurados.
 * Alguns meios de pagamento, como os boletos, necessitam de
 * configurações adicionais para que o pagamento seja devidamente
 * processado.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Controllers\ERP\Parameterization\Financial;

use App\Models\Account;
use App\Models\DefinedMethod;
use App\Models\DefinedMethodTariff;
use App\Models\PaymentMethod;
use Core\Controllers\Controller;
use Core\Controllers\QueryTrait;
use Core\Helpers\FormatterTrait;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Respect\Validation\Validator as V;
use Slim\Http\Request;
use Slim\Http\Response;
use RuntimeException;

class DefinedMethodsController
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
  protected function getValidationRules(bool $addition = false): array
  {
    $validationRules = [
      'definedmethodid' => V::notBlank()
        ->intVal()
        ->setName('ID do meio de pagamento configurado'),
      'name' => V::notBlank()
        ->length(2, 50)
        ->setName('Nome da configuração'),
      'accountid' => V::notBlank()
        ->intVal()
        ->setName('Conta bancária para recebimento'),
      'paymentmethodid' => V::notBlank()
        ->intVal()
        ->setName('Meio de pagamento'),
      'parameters' => V::json()
        ->setName('Parâmetros'),
      'tariffs' => [
        'definedmethodtariffid' => V::intVal()
          ->setName('ID da tarifa cobrada'),
        'basicfare' => V::numericValue()
          ->setName('Tarifa cobrada'),
        'validfrom' => V::notEmpty()
          ->date('d/m/Y')
          ->setName('Vigente desde')
      ],
      'billingcounter' => V::intVal()
        ->setName('Títulos emitidos'),
      'shippingcounter' => V::intVal()
        ->setName('Arquivos de remessa enviados '),
      'blocked' => V::boolVal()
        ->setName('Bloquear esta configuração')
    ];

    if ($addition) {
      // Ajusta as regras para adição
      unset($validationRules['definedmethodid']);
      unset($validationRules['tariffs']['definedmethodtariffid']);
    }

    return $validationRules;
  }

  /**
   * Recupera as informações dos meios de pagamento.
   *
   * @return Collection
   *   A matriz com as informações de meios de pagamento
   *
   * @throws RuntimeException
   *   Em caso de não termos meios de pagamento cadastrados
   */
  protected function getPaymentMethods(): Collection
  {
    try {
      // Recupera as informações de meios de pagamento
      $paymentMethods = PaymentMethod::where("requiressettings", "=", "true")
        ->orderBy("paymentmethodid")
        ->get([
            'paymentmethodid as id',
            'name'
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
   * Recupera as informações das contas disponíveis.
   *
   * @param int $contractorID
   *   A ID do cliente para o qual desejamos obter os contratos
   *   disponíveis
   * 
   * @return Collection
   *   A matriz com as informações de contas bancárias
   *
   * @throws RuntimeException
   *   Em caso de não termos contas bancárias cadastradas
   */
  protected function getAccounts(int $contractorID): Collection
  {
    try {
      // Recupera as informações de contas bancárias do contratante
      $accounts = Account::where("contractorid", "=", $contractorID)
        ->where("entityid", "=", $contractorID)
        ->orderBy("bankid")
        ->get([
            'accountid as id',
            'bankid',
            'agencynumber',
            'accountnumber',
            'wallet'
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
   * Exibe a página inicial do gerenciamento de configurações dos meios
   * de pagamentos.
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
    $this->breadcrumb->push('Meios de pagamento',
      $this->path('ERP\Parameterization\Financial\DefinedMethods')
    );
    
    // Registra o acesso
    $this->info("Acesso ao gerenciamento de configurações dos meios de "
      . "pagamento.");
    
    // Recupera os dados da sessão
    $definedMethod = $this->session->get('definedMethod',
      [ 'name' => '' ])
    ;
    
    // Renderiza a página
    return $this->render($request, $response,
      'erp/parameterization/financial/definedmethods/definedmethods.twig',
      [ 'definedMethod' => $definedMethod ])
    ;
  }
  
  /**
   * Recupera a relação das configurações dos meios de pagamento em
   * formato JSON.
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
    $this->debug("Acesso à relação de configurações dos meios de "
      . "pagamento.");
    
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
    $this->session->set('definedMethod',
      [ 'name' => $name ]
    );
    
    try
    {
      // Formata o ordenamento dos campos
      $ORDER = $this->formatOrderBy($orderBy, $orderDir);

      // Inicializa a query
      $DefinedMethodQry = DefinedMethod::join('paymentmethods',
            'definedmethods.paymentmethodid', '=',
            'paymentmethods.paymentmethodid'
          )
        ->where('contractorid', '=',
            $this->authorization->getContractor()->id
          )
      ;
      
      // Acrescenta os filtros
      if (!empty($name)) {
        $DefinedMethodQry
          ->whereRaw("public.unaccented(definedmethods.name) ILIKE "
              . "public.unaccented('%{$name}%')"
            );
      }

      // Conclui nossa consulta
      $definedMethods = $DefinedMethodQry
        ->skip($start)
        ->take($length)
        ->orderByRaw($ORDER)
        ->get([
            'definedmethods.definedmethodid AS id',
            'definedmethods.name',
            'definedmethods.paymentmethodid',
            'paymentmethods.name AS paymentmethodname',
            'blocked',
            $this->DB->raw('count(*) OVER() AS fullcount')
          ])
      ;
      
      if (count($definedMethods) > 0) {
        $rowCount = $definedMethods[0]->fullcount;
        
        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'draw' => $draw,
              'recordsTotal' => $rowCount,
              'recordsFiltered' => $rowCount,
              'data' => $definedMethods
            ])
        ;
      } else {
        if (empty($name)) {
          $error = "Não temos configurações dos meios de pagamento "
          . "cadastradas.";
        } else {
          $error = "Não temos configurações de meios de pagamento "
            . "cadastradas cujo nome contém <i>{$name}</i>."
          ;
        }
      }
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno no banco de dados: {error}.",
        [ 'module' => 'configurações dos meios de pagamento',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de "
        . "configurações dos meios de pagamento. Erro interno no banco "
        . "de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno: {error}.",
        [ 'module' => 'configurações dos meios de pagamento',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de "
        . "configurações dos meios de pagamento. Erro interno."
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
   * Exibe um formulário para adição de uma configuração de meio de
   * pagamento, quando solicitado, e confirma os dados enviados.
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

      // Recupera as informações das contas bancárias
      $accounts = $this->getAccounts($contractor->id);
    }
    catch (RuntimeException $exception)
    {
      // Alerta o usuário
      $this->flash("error", $exception->getMessage());

      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [ 'routeName' => 'ERP\Parameterization\Financial\DefinedMethods' ]
      );

      // Redireciona para a página de gerenciamento de configurações de
      // meios de pagamento
      return $this->redirect($response,
        'ERP\Parameterization\Financial\DefinedMethods');
    }

    // Verifica se estamos postando os dados
    if ($request->isPost()) {
      // Os dados estão sendo postados
      
      // Registra o acesso
      $this->debug("Processando à adição de configuração de meio de "
        . "pagamento."
      );
      
      // Valida os dados
      $this->validator->validate($request,
        $this->getValidationRules(true)
      );

      if ($this->validator->isValid()) {
        // Grava as informações no banco de dados
        try
        {
          // Recupera os dados da configuração de meio de pagamento
          $definedMethodData = $this->validator->getValues();
          
          // Primeiro, verifica se não temos uma configuração de meio de
          // pagamento com o mesmo nome neste contratante
          if (DefinedMethod::where("contractorid",
                  '=', $contractor->id)
                ->whereRaw("public.unaccented(name) ILIKE "
                    . "public.unaccented('{$definedMethodData['name']}')"
                  )
                ->count() === 0) {
            // Precisa retirar dos parâmetros as informações referentes
            // as tarifas
            if (array_key_exists('tariffs', $definedMethodData)) {
              $tariffsData = $definedMethodData['tariffs'];
              unset($definedMethodData['tariffs']);
            } else {
              $tariffsData = [];
            }
            
            // Iniciamos a transação
            $this->DB->beginTransaction();

            // Grava a nova configuração de meio de pagamento
            $definedMethod = new DefinedMethod();
            $definedMethod->fill($definedMethodData);
            // Adiciona o contratante e usuários atuais
            $definedMethod->contractorid = $contractor->id;
            $definedMethod->save();
            $definedMethodID = $definedMethod->definedmethodid;

            // Incluímos todas as tarifas definidas
            foreach($tariffsData AS $tariffData) {
              // Incluímos uma nova tarifa
              $tariff = new DefinedMethodTariff();
              $tariff->fill($tariffData);
              $tariff->definedmethodid = $definedMethodID;
              $tariff->save();
            }

            // Efetiva a transação
            $this->DB->commit();
            
            // Registra o sucesso
            $this->info("Cadastrada a configuração do meio de "
              . "pagamento '{name}' no contratante '{contractor}' com "
              . "sucesso.",
              [ 'name'  => $definedMethodData['name'],
                'contractor' => $contractor->name ]
            );
            
            // Alerta o usuário
            $this->flash("success", "A configuração do meio de "
              . "pagamento <i>'{name}'</i> foi cadastrada com sucesso.",
              [ 'name'  => $definedMethodData['name'] ]
            );
            
            // Registra o evento
            $this->debug("Redirecionando para {routeName}",
              [ 'routeName' => 'ERP\Parameterization\Financial\DefinedMethods' ]
            );
            
            // Redireciona para a página de gerenciamento de
            // configurações de meios de pagamento
            return $this->redirect($response,
              'ERP\Parameterization\Financial\DefinedMethods'
            );
          } else {
            // Registra o erro
            $this->debug("Não foi possível inserir as informações da "
              . "configuração do meio de pagamento '{name}' do "
              . "contratante '{contractor}'. Já existe uma configuração "
              . "com o mesmo nome.",
              [ 'name'  => $definedMethodData['name'],
                'contractor' => $contractor->name ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Já existe uma configuração de "
              . "meio de pagamento com o nome <i>'{name}'</i>.",
              [ 'name'  => $definedMethodData['name'] ]
            );
          }
        }
        catch(QueryException $exception)
        {
          // Reverte (desfaz) a transação
          $this->DB->rollBack();

          // Registra o erro
          $this->error("Não foi possível inserir as informações da "
            . "configuração do meio de pagamento '{name}' no "
            . "contratante '{contractor}'. Erro interno no banco de "
            . "dados: {error}.",
            [ 'name'  => $definedMethodData['name'],
              'contractor' => $contractor->name,
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações da configuração do meio de pagamento. Erro "
            . "interno no banco de dados."
          );
        }
        catch(Exception $exception)
        {
          // Reverte (desfaz) a transação
          $this->DB->rollBack();

          // Registra o erro
          $this->error("Não foi possível inserir as informações da "
            . "configuração do meio de pagamento '{name}' no "
            . "contratante '{contractor}'. Erro interno: {error}.",
            [ 'name'  => $definedMethodData['name'],
              'contractor' => $contractor->name,
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações da configuração do meio de pagamento. Erro "
            . "interno."
          );
        }
      }
    } else {
      // Carrega um conjunto de valores vazio
      $emptyDefinedMethod = [
        'paymentmethodid' => $paymentMethods[0]['id'],
        'parameters' => '{ }',
        'billingcounter' => 0,
        'shippingcounter' => 0,
        'counterdate' => '',
        'daycounter' => 0
      ];
      $this->validator->setValues($emptyDefinedMethod);
    }
    
    // Exibe um formulário para adição de uma configuração de meio de
    // pagamento
    
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ERP\Home')
    );
    $this->breadcrumb->push('Parametrização', '');
    $this->breadcrumb->push('Financeiro', '');
    $this->breadcrumb->push('Meios de pagamento',
      $this->path('ERP\Parameterization\Financial\DefinedMethods')
    );
    $this->breadcrumb->push('Adicionar',
      $this->path('ERP\Parameterization\Financial\DefinedMethods\Add')
    );
    
    // Registra o acesso
    $this->info("Acesso à adição de configuração de meio de pagamento "
      . "no contratante '{contractor}'.",
      [ 'contractor' => $contractor->name ]
    );
    
    // Renderiza a página
    return $this->render($request, $response,
      'erp/parameterization/financial/definedmethods/definedmethod.twig',
      [ 'formMethod' => 'POST',
        'paymentMethods' => $paymentMethods,
        'accounts' => $accounts ])
    ;
  }
  
  /**
   * Exibe um formulário para edição de uma configuração de meio de
   * pagamento, quando solicitado, e confirma os dados enviados.
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

      // Recupera as informações das contas bancárias
      $accounts = $this->getAccounts($contractor->id);
    }
    catch (RuntimeException $exception)
    {
      // Alerta o usuário
      $this->flash("error", $exception->getMessage());

      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [ 'routeName' => 'ERP\Parameterization\Financial\DefinedMethods' ]
      );

      // Redireciona para a página de gerenciamento de configurações de
      // meios de pagamento
      return $this->redirect($response,
        'ERP\Parameterization\Financial\DefinedMethods');
    }
    
    try
    {
      // Recupera as informações da configuração do meio de pagamento
      $definedMethodID = $args['definedMethodID'];
      $definedMethod = DefinedMethod::join('paymentmethods',
            'definedmethods.paymentmethodid', '=',
            'paymentmethods.paymentmethodid'
          )
        ->where('definedmethods.contractorid', '=', $contractor->id)
        ->where('definedmethods.definedmethodid', '=',
            $definedMethodID
          )
        ->get([
            'definedmethods.*',
            'definedmethods.name AS definedmethodname'
          ])
      ;

      if ( $definedMethod->isEmpty() ) {
        throw new ModelNotFoundException("Não temos nenhuma "
          . "configuração de meio de pagamento com o código "
          . "{$definedMethodID} cadastrada"
        );
      }
      $definedMethod = $definedMethod
        ->first()
        ->toArray()
      ;

      // Recupera as informações das tarifas
      $tariffs = DefinedMethodTariff::where('definedmethodid', $definedMethodID)
        ->orderBy('validfrom')
        ->get([
          'definedmethodtariffid',
          'basicfare',
          'validfrom'
        ])
      ;

      // Invertemos a data de início da vigência em todas as tarifas
      foreach ($tariffs as $key => $tariff) {
        $tariffs[$key]['validfrom'] =
          $this->formatSQLDate($tariff['validfrom'])
        ;
      }

      if ( $tariffs->isEmpty() ) {
        // Criamos uma relação de tarifas vazias
        $definedMethod['tariffs'] = [ ];
      } else {
        $definedMethod['tariffs'] = $tariffs
          ->toArray()
        ;
      }

    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar a configuração do meio "
        . "de pagamento código {definedmethodID}.",
        [ 'definedmethodID' => $definedMethodID ]
      );
      
      // Alerta o usuário
      $this->flash("error", "Não foi possível localizar esta "
        . "configuração de meio de pagamento."
      );
      
      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [ 'routeName' => 'ERP\Parameterization\Financial\DefinedMethods' ]
      );
      
      // Redireciona para a página de gerenciamento de configurações de
      // meios de pagamento
      return $this->redirect($response,
        'ERP\Parameterization\Financial\DefinedMethods'
      );
    }
      
    // Verifica se estamos modificando os dados
    if ($request->isPut()) {
      // Os dados estão sendo modificados
      
      // Registra o acesso
      $this->debug("Processando à edição da configuração de meio de "
        . "pagamento '{name}' no contratante {contractor}.",
        [ 'name' => $definedMethod['name'],
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
          // Recupera os dados modificados da configuração de meio de
          // pagamento
          $definedMethodData = $this->validator->getValues();
          
          // Primeiro, verifica se não mudamos o nome da configuração
          $save = false;
          if (strtolower($definedMethod['name']) != strtolower($definedMethodData['name'])) {
            // Modificamos o nome da configuração de meio de pagamento,
            // então verifica se temos uma configuração de meio de
            // pagamento com o mesmo nome neste contratante antes de
            // prosseguir
            if (DefinedMethod::where("contractorid", '=',
                      $contractor->id
                    )
                  ->whereRaw("public.unaccented(name) ILIKE "
                      . "public.unaccented('{$definedMethodData['name']}')")
                  ->count() === 0) {
              $save = true;
            } else {
              // Registra o erro
              $this->debug("Não foi possível modificar as informações "
                . "da configuração do meio de pagamento '{name}' no "
                . "contratante '{contractor}'. Já existe uma "
                . "configuração do meio de pagamento com o mesmo nome.",
                [ 'name'  => $definedMethodData['name'],
                  'contractor' => $contractor->name ]
              );
              
              // Alerta o usuário
              $this->flashNow("error", "Já existe uma configuração de "
                . "meio de pagamento com o mesmo nome."
              );
            }
          } else {
            $save = true;
          }
          
          if ($save) {
            // Precisa retirar dos parâmetros as informações referentes
            // as tarifas
            if (array_key_exists('tariffs', $definedMethodData)) {
              $tariffsData = $definedMethodData['tariffs'];
              unset($definedMethodData['tariffs']);
            } else {
              $tariffsData = [];
            }
            
            // =========================================[ Tarifas ]=====
            // Recupera as informações das tarifas e separa os dados
            // para as operações de inserção, atualização e remoção.
            // =========================================================
            
            // Analisa as tarifas informadas, de forma a separar quais
            // valores precisam ser adicionados, removidos e atualizados
            
            // Matrizes que armazenarão os dados das tarifas a serem
            // adicionadas, atualizadas e removidas
            $newTariffs = [ ];
            $updTariffs = [ ];
            $delTariffs = [ ];

            // Os IDs das tarifas mantidas para permitir determinar as
            // tarifas a serem removidas
            $heldTariffs = [ ];

            // Determina quais tarifas serão mantidas (e atualizadas) e
            // as que precisam ser adicionadas (novas)
            foreach ($tariffsData AS $tariff) {
              if (empty($tariff['definedmethodtariffid'])) {
                // Tarifa nova
                $newTariffs[] = $tariff;
              } else {
                // Tarifa existente
                $heldTariffs[] = $tariff['definedmethodtariffid'];
                $updTariffs[]  = $tariff;
              }
            }
            
            // Recupera as tarifas armazenadas atualmente
            $tariffs = DefinedMethodTariff::where('definedmethodid',
                  $definedMethodID
                )
              ->get(['definedmethodtariffid'])
              ->toArray()
            ;
            $oldTariffs = [ ];
            foreach ($tariffs as $tariff) {
              $oldTariffs[] = $tariff['definedmethodtariffid'];
            }

            // Verifica quais as tarifas estavam na base de dados e
            // precisam ser removidas
            $delTariffs = array_diff($oldTariffs, $heldTariffs);

            // ========================================[ Gravação ]=====
            
            // Iniciamos a transação
            $this->DB->beginTransaction();

            // Grava as informações da condição de pagamento
            $definedMethodChanged = DefinedMethod::findOrFail($definedMethodID);
            $definedMethodChanged->fill($definedMethodData);
            $definedMethodChanged->save();
            
            // -----------------------------------------[ Tarifas ]-----

            // Primeiro apagamos as tarifas removidas pelo usuário
            // durante a edição
            foreach ($delTariffs as $tariffID) {
              // Apaga cada tarifa
              $tariff = DefinedMethodTariff::findOrFail($tariffID);
              $tariff->delete();
            }

            // Agora inserimos as novas tarifas
            foreach ($newTariffs as $tariffData) {
              // Incluímos uma nova tarifa nesta configuração
              unset($tariffData['definedmethodtariffid']);
              $tariff = new DefinedMethodTariff();
              $tariff->fill($tariffData);
              $tariff->definedmethodid = $definedMethodID;
              $tariff->save();
            }

            // Por último, modificamos as tarifas mantidas
            foreach($updTariffs AS $tariffData) {
              // Retira a ID da tarifa
              $tariffID = $tariffData['definedmethodtariffid'];
              unset($tariffData['definedmethodtariffid']);
              
              // Grava as informações da tarifa
              $tariff = DefinedMethodTariff::findOrFail($tariffID);
              $tariff->fill($tariffData);
              $tariff->definedmethodid = $definedMethodID;
              $tariff->save();
            }

            // ---------------------------------------------------------

            // Efetiva a transação
            $this->DB->commit();
            
            // Registra o sucesso
            $this->info("Modificada a configuração do meio de "
              . "pagamento '{name}' no contratante '{contractor}' com "
              . "sucesso.",
              [ 'name'  => $definedMethodData['name'],
                'contractor' => $contractor->name ]
            );
            
            // Alerta o usuário
            $this->flash("success", "A configuração do meio de "
              . "pagamento <i>'{name}'</i> foi modificado com sucesso.",
              [ 'name'  => $definedMethodData['name'] ]
            );
            
            // Registra o evento
            $this->debug("Redirecionando para {routeName}",
              [ 'routeName' => 'ERP\Parameterization\Financial\DefinedMethods' ]
            );
            
            // Redireciona para a página de gerenciamento de
            // configurações de meios de pagamento
            return $this->redirect($response,
              'ERP\Parameterization\Financial\DefinedMethods')
            ;
          }
        }
        catch(QueryException $exception)
        {
          // Reverte (desfaz) a transação
          $this->DB->rollBack();

          // Registra o erro
          $this->error("Não foi possível modificar as informações da "
            . "configuração do meio de pagamento '{name}' no "
            . "contratante '{contractor}'. Erro interno no banco de "
            . "dados: {error}",
            [ 'name'  => $definedMethodData['name'],
              'contractor' => $contractor->name,
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível modificar as "
            . "informações da configuração do meio de pagamento. Erro "
            . "interno no banco de dados."
          );
        }
        catch(Exception $exception)
        {
          // Reverte (desfaz) a transação
          $this->DB->rollBack();

          // Registra o erro
          $this->error("Não foi possível modificar as informações da "
            . "configuração do meio de pagamento '{name}' no "
            . "contratante '{contractor}'. Erro interno: {error}",
            [ 'name'  => $definedMethodData['name'],
              'contractor' => $contractor->name,
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível modificar as "
            . "informações da configuração do meio de pagamento. Erro "
            . "interno."
          );
        }
      }
    } else {
      // Carrega os dados atuais
      $this->validator->setValues($definedMethod);
    }
    
    // Exibe um formulário para edição de uma condição de pagamento
    
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ERP\Home')
    );
    $this->breadcrumb->push('Parametrização', '');
    $this->breadcrumb->push('Financeiro', '');
    $this->breadcrumb->push('Meios de pagamento',
      $this->path('ERP\Parameterization\Financial\DefinedMethods')
    );
    $this->breadcrumb->push('Editar',
      $this->path('ERP\Parameterization\Financial\DefinedMethods\Edit',
        [ 'definedMethodID' => $definedMethodID ]
      )
    );
    
    // Registra o acesso
    $this->info("Acesso à edição da configuração do meio de pagamento "
      . "'{name}' do contratante '{contractor}'.",
      [ 'name' => $definedMethod['name'],
        'contractor' => $contractor->name ]
    );
    
    // Renderiza a página
    return $this->render($request, $response,
      'erp/parameterization/financial/definedmethods/definedmethod.twig',
      [ 'formMethod' => 'PUT',
        'paymentMethods' => $paymentMethods,
        'accounts' => $accounts ]
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
    $this->debug("Processando à remoção de configuração do meio de "
      . "pagamento."
    );
    
    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();

    // Recupera o ID
    $definedMethodID = $args['definedMethodID'];

    try
    {
      // Recupera as informações da configuração do meio de pagamento
      $definedMethod = DefinedMethod::where('contractorid',
            '=', $contractor->id
          )
        ->where('definedmethodid', '=', $definedMethodID)
        ->firstOrFail()
      ;

      // Agora apaga a condição de pagamento
      $definedMethod->delete();
      // Registra o sucesso
      $this->info("A configuração do meio de pagamento '{name}' do "
        . "contratante '{contractor}' foi removido com sucesso.",
        [ 'name' => $definedMethod->name,
          'contractor' => $contractor->name ]
      );
      
      // Informa que a remoção foi realizada com sucesso
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getParams(),
            'message' => "Removido a configuração do meio de pagamento "
              . "{$definedMethod->name}",
            'data' => "Delete"
          ])
      ;

      //// Verifica se a configuração do meio de pagamento está em uso
      //if (BillingType::where("contractorid", '=', $contractor->id)
      //      ->where("definedmethodid", '=', $definedMethodID)
      //      ->count() === 0) {
      //  // Agora apaga a configuração do meio de pagamento
      //  $definedMethod->delete();
      //  
      //  // Registra o sucesso
      //  $this->info("A configuração do meio de pagamento '{name}' do "
      //    . "contratante '{contractor}' foi removido com sucesso.",
      //    [ 'name' => $definedMethod->name,
      //      'contractor' => $contractor->name ]
      //  );
      //  
      //  // Informa que a remoção foi realizada com sucesso
      //  return $response
      //    ->withHeader('Content-type', 'application/json')
      //    ->withJson([
      //        'result' => 'OK',
      //        'params' => $request->getParams(),
      //        'message' => "Removido a configuração do meio de "
      //          . "pagamento {$definedMethod->name}",
      //        'data' => "Delete"
      //      ])
      //  ;
      //} else {
      //  // Registra o erro
      //  $this->error("Não foi possível remover as informações da "
      //    . "configuração do meio de pagamento '{name}' no contratante "
      //    . "'{contractor}'. A configuração do meio de pagamento está "
      //    . "em uso.",
      //    [ 'name'  => $definedMethod->name,
      //      'contractor' => $contractor->name ]
      //  );
      //  
      //  $message = "Não foi possível remover a configuração do meio "
      //    . "de pagamento, pois a mesma esta em uso."
      //  ;
      //}
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar a configuração do meio "
        . "de pagamento código {definedMethodID} para remoção.",
        [ 'definedMethodID' => $definedMethodID ]
      );
      
      $message = "Não foi possível localizar a configuração do meio de "
        . "pagamento para remoção."
      ;
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível remover as informações da "
        . "configuração do meio de pagamento ID {id} no contratante "
        . "'{contractor}'. Erro interno no banco de dados: {error}.",
        [ 'id'  => $definedMethodID,
          'contractor' => $contractor->name,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível remover a configuração do meio de "
        . "pagamento. Erro interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível remover as informações da "
        . "configuração do meio de pagamento ID {id} no contratante "
        . "'{contractor}'. Erro interno: {error}.",
        [ 'id'  => $definedMethodID,
          'contractor' => $contractor->name,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível remover a configuração do meio de "
        . "pagamento. Erro interno."
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
   * Alterna o estado do bloqueio de uma configuração do meio de
   * pagamento de um contratante.
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
      . "configuração do meio de pagamento."
    );
    
    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();
    
    // Recupera o ID
    $definedMethodID = $args['definedMethodID'];
    
    try
    {
      // Recupera as informações da configuração do meio de pagamento
      $definedMethod = DefinedMethod::where('contractorid',
            '=', $contractor->id
          )
        ->where('definedmethodid', '=', $definedMethodID)
        ->firstOrFail()
      ;
      
      // Alterna o estado do bloqueio da configuração do meio de
      // pagamento
      $action = $definedMethod->blocked
        ? "desbloqueada"
        : "bloqueada"
      ;
      $definedMethod->blocked = !$definedMethod->blocked;
      $definedMethod->save();
      
      // Registra o sucesso
      $this->info("A configuração do meio de pagamento '{name}' do "
        . "contratante '{contractor}' foi {action} com sucesso.",
        [ 'name' => $definedMethod->name,
          'contractor' => $contractor->name,
          'action' => $action ]
      );
      
      // Informa que a alteração do estado do bloqueio da configuração
      // do meio de pagamento foi realizada com sucesso
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getParams(),
            'message' => "A configuração do meio de pagamento "
              . "{$definedMethod->name} foi {$action} com sucesso.",
            'data' => "Delete" ])
      ;
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar a configuração do meio "
        . "de pagamento código {definedMethodID} no contratante "
        . "'{contractor}' para alternar o estado do bloqueio.",
        [ 'definedMethodID' => $definedMethodID,
          'contractor' => $contractor->name ]
      );
      
      $message = "Não foi possível localizar a configuração do meio de "
        . "pagamento para alternar o estado do bloqueio."
      ;
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível alternar o estado do bloqueio "
        . "da configuração do meio de pagamento '{name}' no "
        . "contratante '{contractor}'. Erro interno no banco de dados: "
        . "{error}.",
        [ 'name'  => $definedMethod->name,
          'contractor' => $contractor->name,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível alternar o estado do bloqueio da "
        . "configuração do meio de pagamento. Erro interno no banco de "
        . "dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível alternar o estado do bloqueio "
        . "da configuração do meio de pagamento '{name}' no "
        . "contratante '{contractor}'. Erro interno: {error}.",
        [ 'name'  => $definedMethod->name,
          'contractor' => $contractor->name,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível alternar o estado do bloqueio da "
        . "configuração do meio de pagamento. Erro interno."
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
