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
 * O controlador do gerenciamento de tipos de parcelamentos.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Controllers\ERP\Parameterization\Financial;

use App\Providers\InstallmentProvider as InstallmentProvider;
use App\Models\BillingType;
use App\Models\InstallmentType;
use Core\Controllers\Controller;
use Core\Controllers\QueryTrait;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Respect\Validation\Validator as V;
use Slim\Http\Request;
use Slim\Http\Response;

class InstallmentTypesController
  extends Controller
{
  /**
   * Os métodos para manipular queries.
   */
  use QueryTrait;

  /**
   * Exibe a página inicial do gerenciamento de tipos de parcelamentos.
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
    $this->breadcrumb->push('Tipos de parcelamentos',
      $this->path('ERP\Parameterization\Financial\InstallmentTypes')
    );
    
    // Registra o acesso
    $this->info("Acesso ao gerenciamento de tipos de parcelamentos.");
    
    // Recupera os dados da sessão
    $installmentType = $this->session->get('installmenttype',
      [ 'name' => '' ])
    ;
    
    // Renderiza a página
    return $this->render($request, $response,
      'erp/parameterization/financial/installmenttypes/installmenttypes.twig',
      [ 'installmenttype' => $installmentType ])
    ;
  }
  
  /**
   * Recupera a relação das tipos de parcelamentos em formato JSON.
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
    $this->debug("Acesso à relação de tipos de parcelamentos.");
    
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
    $this->session->set('installmenttype',
      [ 'name' => $name ]
    );
    
    try
    {
      // Formata o ordenamento dos campos
      $ORDER = $this->formatOrderBy($orderBy, $orderDir);

      // Inicializa a query
      $InstallmentTypeQry = InstallmentType::where('contractorid',
        '=', $this->authorization->getContractor()->id
      );
      
      // Acrescenta os filtros
      if (!empty($name)) {
        $InstallmentTypeQry
          ->whereRaw("public.unaccented(name) ILIKE "
              . "public.unaccented('%{$name}%')"
            );
      }

      // Conclui nossa consulta
      $installmentTypes = $InstallmentTypeQry
        ->skip($start)
        ->take($length)
        ->orderByRaw($ORDER)
        ->get([
            'installmenttypeid AS id',
            'name',
            'minimuminstallmentvalue',
            'maxnumberofinstallments',
            'interestrate',
            'interestfrom',
            'calculationformula',
            'blocked',
            'createdat',
            $this->DB->raw('count(*) OVER() AS fullcount')
          ])
      ;
      
      if (count($installmentTypes) > 0) {
        $rowCount = $installmentTypes[0]->fullcount;
        
        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'draw' => $draw,
              'recordsTotal' => $rowCount,
              'recordsFiltered' => $rowCount,
              'data' => $installmentTypes
            ])
        ;
      } else {
        if (empty($name)) {
          $error = "Não temos tipos de parcelamentos cadastrados.";
        } else {
          $error = "Não temos tipos de parcelamentos cadastrados "
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
        [ 'module' => 'tipos de parcelamentos',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de tipos de "
        . "parcelamentos. Erro interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno: {error}.",
        [ 'module' => 'tipos de parcelamentos',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de tipos de "
        . "parcelamentos. Erro interno."
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
   * Exibe um formulário para adição de um tipo de parcelamento, quando
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
    // Monta os tipos de cálculos dos juros
    $calculationFormulas = [
      [ 'id' => 1, 'name' => 'Juros simples' ],
      [ 'id' => 2, 'name' => 'Tabela Price' ]
    ];

    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();

    // Verifica se estamos postando os dados
    if ($request->isPost()) {
      // Os dados estão sendo postados
      
      // Registra o acesso
      $this->debug("Processando à adição de tipo de parcelamento.");
      
      // Valida os dados
      $maxNumberOfInstallments = $request->getParam('maxnumberofinstallments');
      $this->validator->validate($request, [
        'name' => V::notBlank()
          ->length(2, 30)
          ->setName('Nome do tipo de parcelamento'),
        'minimuminstallmentvalue' => V::numericValue()
          ->setName('Valor mínimo da parcela'),
        'maxnumberofinstallments' => V::notBlank()
          ->intVal()
          ->positive()
          ->setName('Quantidade máxima de parcelas'),
        'interestrate' => V::numericValue()
          ->setName('Taxa de juros'),
        'interestfrom' => V::intVal()
          ->between(0, $maxNumberOfInstallments)
          ->setName('Cobrar juros a partir da parcela'),
        'calculationformula' => V::intVal()
          ->between(1, 2)
          ->setName('Fórmula de cálculo dos juros'),
        'blocked' => V::boolVal()
          ->setName('Bloquear este tipo de parcelamento para uso no '
              . 'sistema'
            )
      ]);

      if ($this->validator->isValid()) {
        // Grava as informações no banco de dados
        try
        {
          // Recupera os dados do tipo de parcelamento
          $installmentTypeData = $this->validator->getValues();
          
          // Primeiro, verifica se não temos um tipo de parcelamento com
          // o mesmo nome neste contratante
          if (InstallmentType::where("contractorid",
                  '=', $contractor->id)
                ->whereRaw("public.unaccented(name) ILIKE "
                    . "public.unaccented('{$installmentTypeData['name']}')"
                  )
                ->count() === 0) {
            // Grava a novo tipo de parcelamento
            $installmentType = new InstallmentType();
            $installmentType->fill($installmentTypeData);
            // Adiciona o contratante e usuários atuais
            $installmentType->contractorid = $contractor->id;
            $installmentType->createdbyuserid =
              $this->authorization->getUser()->userid
            ;
            $installmentType->updatedbyuserid =
              $this->authorization->getUser()->userid
            ;
            $installmentType->save();
            
            // Registra o sucesso
            $this->info("Cadastrado o tipo de parcelamento '{name}' no "
              . "contratante '{contractor}' com sucesso.",
              [ 'name'  => $installmentTypeData['name'],
                'contractor' => $contractor->name ]
            );
            
            // Alerta o usuário
            $this->flash("success", "O tipo de parcelamento <i>'{name}'"
              . "</i> foi cadastrado com sucesso.",
              [ 'name'  => $installmentTypeData['name'] ]
            );
            
            // Registra o evento
            $this->debug("Redirecionando para {routeName}",
              [ 'routeName' => 'ERP\Parameterization\Financial\InstallmentTypes' ]
            );
            
            // Redireciona para a página de gerenciamento de tipos de parcelamentos
            return $this->redirect($response,
              'ERP\Parameterization\Financial\InstallmentTypes')
            ;
          } else {
            // Registra o erro
            $this->debug("Não foi possível inserir as informações do "
              . "tipo de parcelamento '{name}' do contratante "
              . "'{contractor}'. Já existe um tipo de parcelamento com "
              . "o mesmo nome.",
              [ 'name'  => $installmentTypeData['name'],
                'contractor' => $contractor->name ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Já existe um tipo de "
              . "parcelamento com o nome <i>'{name}'</i>.",
              [ 'name'  => $installmentTypeData['name'] ]
            );
          }
        }
        catch(QueryException $exception)
        {
          // Registra o erro
          $this->error("Não foi possível inserir as informações do "
            . "tipo de parcelamento '{name}' no contratante "
            . "'{contractor}'. Erro interno no banco de dados: "
            . "{error}.",
            [ 'name'  => $installmentTypeData['name'],
              'contractor' => $contractor->name,
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações do tipo de parcelamento. Erro interno no "
            . "banco de dados."
          );
        }
        catch(Exception $exception)
        {
          // Registra o erro
          $this->error("Não foi possível inserir as informações do "
            . "tipo de parcelamento '{name}' no contratante "
            . "'{contractor}'. Erro interno: {error}.",
            [ 'name'  => $installmentTypeData['name'],
              'contractor' => $contractor->name,
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações do tipo de parcelamento. Erro interno."
          );
        }
      }
    } else {
      // Carrega um conjunto de valores vazio
      $emptyInstallmentType = [
        'minimuminstallmentvalue' => '0,00',
        'maxnumberofinstallments' => '1',
        'interestrate'            => '0,000',
        'interestfrom'            => '0',
        'calculationformula'      => 1,
        'blocked'                 => "false"
      ];
      $this->validator->setValues($emptyInstallmentType);
    }
    
    // Exibe um formulário para adição de um tipo de parcelamento
    
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ERP\Home')
    );
    $this->breadcrumb->push('Parametrização', '');
    $this->breadcrumb->push('Financeiro', '');
    $this->breadcrumb->push('Tipos de parcelamentos',
      $this->path('ERP\Parameterization\Financial\InstallmentTypes')
    );
    $this->breadcrumb->push('Adicionar',
      $this->path('ERP\Parameterization\Financial\InstallmentTypes\Add')
    );
    
    // Registra o acesso
    $this->info("Acesso à adição de tipo de parcelamento no "
      . "contratante '{contractor}'.",
      [ 'contractor' => $contractor->name ]
    );
    
    // Renderiza a página
    return $this->render($request, $response,
      'erp/parameterization/financial/installmenttypes/installmenttype.twig',
      [ 'formMethod' => 'POST',
        'calculationFormulas' => $calculationFormulas ])
    ;
  }
  
  /**
   * Exibe um formulário para edição de um tipo de parcelamento, quando
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
    // Monta os tipos de cálculos dos juros
    $calculationFormulas = [
      [ 'id' => 1, 'name' => 'Juros simples' ],
      [ 'id' => 2, 'name' => 'Tabela Price' ]
    ];

    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();
    
    try
    {
      // Recupera as informações do tipo de parcelamento
      $installmentTypeID = $args['installmentTypeID'];
      $installmentType = InstallmentType::join('users AS createduser',
            'installmenttypes.createdbyuserid', '=', 'createduser.userid'
          )
        ->join('users AS updateduser', 'installmenttypes.updatedbyuserid',
            '=', 'updateduser.userid'
          )
        ->where('installmenttypes.contractorid', '=', $contractor->id)
        ->where('installmenttypes.installmenttypeid', '=',
            $installmentTypeID
          )
        ->firstOrFail([
            'installmenttypes.*',
            $this->DB->raw("CASE installmenttypes.calculationformula "
              . "  WHEN 1 THEN 'Juros simples' "
              . "  ELSE 'Tabela Price' "
              . "END AS calculationformulaname"
            ),
            'createduser.name AS createdbyusername',
            'updateduser.name AS updatedbyusername'
          ])
      ;
      
      // Verifica se estamos modificando os dados
      if ($request->isPut()) {
        // Os dados estão sendo modificados
        
        // Registra o acesso
        $this->debug("Processando à edição do tipo de parcelamento "
          . "'{name}' no contratante {contractor}.",
          [ 'name' => $installmentType['name'],
            'contractor' => $contractor->name ]
        );
        
        // Valida os dados
        $maxNumberOfInstallments =
          $request->getParam('maxnumberofinstallments')
        ;
        $this->validator->validate($request, [
          'name' => V::notBlank()
            ->length(2, 30)
            ->setName('Nome do tipo de parcelamento'),
          'minimuminstallmentvalue' => V::numericValue()
            ->setName('Valor mínimo da parcela'),
          'maxnumberofinstallments' => V::notBlank()
            ->intVal()
            ->positive()
            ->setName('Quantidade máxima de parcelas'),
          'interestrate' => V::numericValue()
            ->setName('Taxa de juros'),
          'interestfrom' => V::intVal()
            ->between(0, $maxNumberOfInstallments)
            ->setName('Cobrar juros a partir da parcela'),
          'calculationformula' => V::intVal()
            ->between(1, 2)
            ->setName('Fórmula de cálculo dos juros'),
          'blocked' => V::boolVal()
            ->setName('Bloquear este tipo de parcelamento para uso no '
                . 'sistema'
              )
        ]);

        if ($this->validator->isValid()) {
          // Grava as informações no banco de dados
          try
          {
            // Recupera os dados modificados do tipo de parcelamento
            $installmentTypeData = $this->validator->getValues();
            
            // Primeiro, verifica se não mudamos o nome do tipo de
            // parcelamento
            $save = false;
            if ($installmentType->name != $installmentTypeData['name']) {
              // Modificamos o nome do tipo de parcelamento, então verifica
              // se temos um tipo de parcelamento com o mesmo nome neste
              // contratante antes de prosseguir
              if (InstallmentType::where("contractorid", '=',
                        $contractor->id
                      )
                    ->whereRaw("public.unaccented(name) ILIKE "
                        . "public.unaccented('{$installmentTypeData['name']}')")
                    ->count() === 0) {
                $save = true;
              } else {
                // Registra o erro
                $this->debug("Não foi possível modificar as "
                  . "informações do tipo de parcelamento '{name}' no "
                  . "contratante '{contractor}'. Já existe um tipo de "
                  . "parcelamento com o mesmo nome.",
                  [ 'name'  => $installmentTypeData['name'],
                    'contractor' => $contractor->name ]
                );
                
                // Alerta o usuário
                $this->flashNow("error", "Já existe um tipo de "
                  . "parcelamento com o mesmo nome."
                );
              }
            } else {
              $save = true;
            }
            
            if ($save) {
              // Grava as informações do tipo de parcelamento
              $installmentType->fill($installmentTypeData);
              // Adiciona o usuário responsável pela modificação
              $installmentType->updatedbyuserid =
                $this->authorization->getUser()->userid
              ;
              $installmentType->save();
              
              // Registra o sucesso
              $this->info("Modificado o tipo de parcelamento "
                . "'{name}' no contratante '{contractor}' com sucesso.",
                [ 'name'  => $installmentTypeData['name'],
                  'contractor' => $contractor->name ]
              );
              
              // Alerta o usuário
              $this->flash("success", "O tipo de parcelamento "
                . "<i>'{name}'</i> foi modificado com sucesso.",
                [ 'name'  => $installmentTypeData['name'] ]
              );
              
              // Registra o evento
              $this->debug("Redirecionando para {routeName}",
                [ 'routeName' => 'ERP\Parameterization\Financial\InstallmentTypes' ]
              );
              
              // Redireciona para a página de gerenciamento de tipos de
              // parcelamentos
              return $this->redirect($response,
                'ERP\Parameterization\Financial\InstallmentTypes')
              ;
            }
          }
          catch(QueryException $exception)
          {
            // Registra o erro
            $this->error("Não foi possível modificar as informações "
              . "do tipo de parcelamento '{name}' no contratante "
              . "'{contractor}'. Erro interno no banco de dados: "
              . "{error}",
              [ 'name'  => $installmentTypeData['name'],
                'contractor' => $contractor->name,
                'error' => $exception->getMessage() ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Não foi possível modificar as "
              . "informações do tipo de parcelamento. Erro interno no "
              . "banco de dados."
            );
          }
          catch(Exception $exception)
          {
            // Registra o erro
            $this->error("Não foi possível modificar as informações "
              . "do tipo de parcelamento '{name}' no contratante "
              . "'{contractor}'. Erro interno: {error}",
              [ 'name'  => $installmentTypeData['name'],
                'contractor' => $contractor->name,
                'error' => $exception->getMessage() ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Não foi possível modificar as "
              . "informações do tipo de parcelamento. Erro interno."
            );
          }
        }
      } else {
        // Carrega os dados atuais
        $this->validator->setValues($installmentType->toArray());
      }
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar o tipo de parcelamento "
        . "código {installmenttypeID}.",
        [ 'installmenttypeID' => $installmentTypeID ]
      );
      
      // Alerta o usuário
      $this->flash("error", "Não foi possível localizar este tipo de "
        . "parcelamento."
      );
      
      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [ 'routeName' => 'ERP\Parameterization\Financial\InstallmentTypes' ]
      );
      
      // Redireciona para a página de gerenciamento de tipos de parcelamentos
      return $this->redirect($response,
        'ERP\Parameterization\Financial\InstallmentTypes')
      ;
    }
    
    // Exibe um formulário para edição de um tipo de parcelamento
    
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ERP\Home')
    );
    $this->breadcrumb->push('Parametrização', '');
    $this->breadcrumb->push('Financeiro', '');
    $this->breadcrumb->push('Tipos de parcelamentos',
      $this->path('ERP\Parameterization\Financial\InstallmentTypes')
    );
    $this->breadcrumb->push('Editar',
      $this->path('ERP\Parameterization\Financial\InstallmentTypes\Edit',
        [ 'installmentTypeID' => $installmentTypeID ]
      )
    );
    
    // Registra o acesso
    $this->info("Acesso à edição do tipo de parcelamento '{name}' do "
      . "contratante '{contractor}'.",
      [ 'name' => $installmentType['name'],
        'contractor' => $contractor->name ]
    );
    
    // Renderiza a página
    return $this->render($request, $response,
      'erp/parameterization/financial/installmenttypes/installmenttype.twig',
      [ 'formMethod' => 'PUT',
        'calculationFormulas' => $calculationFormulas ]
    );
  }
  
  /**
   * Remove o tipo de parcelamento.
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
    $this->debug("Processando à remoção de tipo de parcelamento.");
    
    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();

    // Recupera o ID
    $installmentTypeID = $args['installmentTypeID'];

    try
    {
      // Recupera as informações do tipo de parcelamento
      $installmentType = InstallmentType::where('contractorid',
            '=', $contractor->id
          )
        ->where('installmenttypeid', '=', $installmentTypeID)
        ->firstOrFail()
      ;

      // Verifica se o tipo de parcelamento está em uso
      if (BillingType::where("contractorid", '=', $contractor->id)
            ->where("installmenttypeid", '=', $installmentTypeID)
            ->count() === 0) {
        // Agora apaga o tipo de parcelamento
        $installmentType->delete();
        
        // Registra o sucesso
        $this->info("O tipo de parcelamento '{name}' do contratante "
          . "'{contractor}' foi removido com sucesso.",
          [ 'name' => $installmentType->name,
            'contractor' => $contractor->name ]
        );
        
        // Informa que a remoção foi realizada com sucesso
        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'result' => 'OK',
              'params' => $request->getParams(),
              'message' => "Removido o tipo de parcelamento "
                . "{$installmentType->name}",
              'data' => "Delete"
            ])
        ;
      } else {
        // Registra o erro
        $this->error("Não foi possível remover as informações do tipo "
          . "de parcelamento '{name}' no contratante '{contractor}'. O "
          . "tipo de parcelamento está em uso.",
          [ 'name'  => $installmentType->name,
            'contractor' => $contractor->name ]
        );
        
        $message = "Não foi possível remover o tipo de parcelamento, "
          . "pois o mesmo esta em uso."
        ;
      }
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar o tipo de parcelamento "
        . "código {installmentTypeID} para remoção.",
        [ 'installmentTypeID' => $installmentTypeID ]
      );
      
      $message = "Não foi possível localizar o tipo de parcelamento "
        . "para remoção."
      ;
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível remover as informações do tipo "
        . "de parcelamento '{name}' no contratante '{contractor}'. "
        . "Erro interno no banco de dados: {error}.",
        [ 'name'  => $installmentType->name,
          'contractor' => $contractor->name,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível remover o tipo de parcelamento. "
        . "Erro interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível remover as informações do tipo "
        . "de parcelamento '{name}' no contratante '{contractor}'. "
        . "Erro interno: {error}.",
        [ 'name'  => $installmentType->name,
          'contractor' => $contractor->name,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível remover o tipo de parcelamento. "
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
   * Alterna o estado do bloqueio de um tipo de parcelamento de um
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
    $this->debug("Processando à mudança do estado de bloqueio do tipo "
      . "de parcelamento."
    );
    
    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();
    
    // Recupera o ID
    $installmentTypeID = $args['installmentTypeID'];
    
    try
    {
      // Recupera as informações do tipo de parcelamento
      $installmentType = InstallmentType::where('contractorid',
            '=', $contractor->id
          )
        ->where('installmenttypeid', '=', $installmentTypeID)
        ->firstOrFail()
      ;
      
      // Alterna o estado do bloqueio do tipo de parcelamento
      $action = $installmentType->blocked
        ? "desbloqueado"
        : "bloqueado"
      ;
      $installmentType->blocked = !$installmentType->blocked;

      // Adiciona o usuário responsável pela modificação
      $installmentType->updatedbyuserid =
        $this->authorization->getUser()->userid
      ;
      $installmentType->save();
      
      // Registra o sucesso
      $this->info("O tipo de parcelamento '{name}' do contratante "
        . "'{contractor}' foi {action} com sucesso.",
        [ 'name' => $installmentType->name,
          'contractor' => $contractor->name,
          'action' => $action ]
      );
      
      // Informa que a alteração do estado do bloqueio do tipo de
      // parcelamento foi realizado com sucesso
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getParams(),
            'message' => "O tipo de parcelamento "
              . "{$installmentType->name} foi {$action} com sucesso.",
            'data' => "Delete" ])
      ;
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar o tipo de parcelamento "
        . "código {installmentTypeID} no contratante '{contractor}' "
        . "para alternar o estado do bloqueio.",
        [ 'installmentTypeID' => $installmentTypeID,
          'contractor' => $contractor->name ]
      );
      
      $message = "Não foi possível localizar o tipo de parcelamento "
        . "para alternar o estado do bloqueio."
      ;
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível alternar o estado do bloqueio "
        . "do tipo de parcelamento '{name}' no contratante "
        . "'{contractor}'. Erro interno no banco de dados: {error}.",
        [ 'name'  => $installmentType->name,
          'contractor' => $contractor->name,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível alternar o estado do bloqueio do "
        . "tipo de parcelamento. Erro interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível alternar o estado do bloqueio "
        . "do tipo de parcelamento '{name}' no contratante "
        . "'{contractor}'. Erro interno: {error}.",
        [ 'name'  => $installmentType->name,
          'contractor' => $contractor->name,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível alternar o estado do bloqueio do "
        . "tipo de parcelamento. Erro interno."
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
   * Calcula os valores de parcelamento em função dos parâmetros
   * passados.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   *
   * @return Response $response
   */
  public function getInstallmentPlan(Request $request,
    Response $response)
  {
    $this->debug("Acesso ao cálculo de parcelamento");

    // --------------------------[ Recupera os dados requisitados ]-----

    // Recupera os dados da requisição
    $postParams = $request->getParsedBody();

    // Lida com as informações provenientes do cliente
    $value = floatval($postParams['value']);
    $selectable = false;
    if (isset($postParams['installmentTypeID'])) {
      // Estamos passando o código do parcelamento, então lê à partir do
      // banco de dados

      // Indica que podemos selecionar um parcelamento
      $selectable = true;

      // Recupera os dados do contratante
      $contractor = $this->authorization->getContractor();

      // Recupera as informações do tipo de parcelamento
      $installmentTypeID = $postParams['installmentTypeID'];
      $installmentType = InstallmentType::where('installmenttypes.contractorid',
            '=', $contractor->id
          )
        ->where('installmenttypes.installmenttypeid', '=',
            $installmentTypeID
          )
        ->get()
      ;

      if ( $installmentType->isEmpty() ) {
        // Não encontramos o parcelamento ou não está disponível
        $this->debug("Não encontramos o parcelamento ou não está disponível");
        $calculationFormula       = 1;
        $maxNumberOfInstallments  = 1;
        $interestRate             = 0.00;
        $interestFrom             = 0;
        $minimumInstallmentValue  = 0.00;

        $this->debug("O valor não é parcelável.");
      } else {
        // Pegamos o primeiro parcelamento
        $this->debug("Pegamos o primeiro parcelamento");
        $installmentType = $installmentType
          ->first()
        ;
        $calculationFormula       = $installmentType->calculationformula;
        $maxNumberOfInstallments  = $installmentType->maxnumberofinstallments;
        $interestRate             = floatval(
          str_replace (',', '.',
            str_replace ('.', '',
              $installmentType->interestrate
            )
          )
        );
        $interestFrom             = $installmentType->interestfrom;
        $minimumInstallmentValue  = floatval(
          str_replace (',', '.',
            str_replace ('.', '',
              $installmentType->minimuminstallmentvalue
            )
          )
        );

        $this->debug("Recuperado dados do plano de parcelamento "
          . "{name}.",
          [ 'name' => $installmentType->name ]
        );
      }
    } else {
      // Os dados são fornecidos pelo formulário
      $this->debug("Os dados são fornecidos pelo formulário");
      $calculationFormula       = intval($postParams['calculationFormula']);
      $maxNumberOfInstallments  = intval($postParams['maxNumberOfInstallments']);
      $interestRate             = floatval($postParams['interestRate']);
      $interestFrom             = intval($postParams['interestFrom']);
      $minimumInstallmentValue  = floatval($postParams['minimumInstallmentValue']);
    }
    
    $this->debug("A fórmula de cálculo é '{calculationFormula}'", 
      [ 'calculationFormula' => $calculationFormula ]
    );
    $this->debug("O valor fornecido é '{value}'", 
      [ 'value' => $value ]
    );
    
    // Monta a tabela com as opções de parcelamento
    $installmentprovider = new InstallmentProvider();
    $installmentPlan = $installmentprovider->build($value,
      $calculationFormula, $maxNumberOfInstallments, $interestRate,
      $interestFrom, $minimumInstallmentValue, $selectable);
    
    return $response
      ->withHeader('Content-type', 'application/json')
      ->withJson([
          'result' => 'OK',
          'params' => $request->getQueryParams(),
          'message' => 'Plano de parcelamento',
          'data' => $installmentPlan
        ])
    ;
  }
}
