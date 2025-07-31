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
 * O controlador do gerenciamento de depósitos.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Controllers\ERP\Parameterization;

use App\Models\Deposit;
use App\Models\DeviceType;
use Core\Controllers\Controller;
use Core\Controllers\QueryTrait;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Respect\Validation\Validator as V;
use Slim\Http\Request;
use Slim\Http\Response;

class DepositsController
  extends Controller
{
  /**
   * Os métodos para manipular queries.
   */
  use QueryTrait;

  /**
   * Exibe a página inicial do gerenciamento de depósitos.
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
    $this->breadcrumb->push('Depósitos',
      $this->path('ERP\Parameterization\Deposits')
    );
    
    // Registra o acesso
    $this->info("Acesso ao gerenciamento de depósitos.");
    
    // Recupera os dados da sessão
    $deposit = $this->session->get('deposit',
      [ 'name' => '' ])
    ;

    // Recupera as informações de tipos de dispositivos armazenáveis
    $deviceTypes = DeviceType::get();
    
    // Renderiza a página
    return $this->render($request, $response,
      'erp/parameterization/deposits/deposits.twig',
      [ 'deposit' => $deposit,
        'deviceTypes' => $deviceTypes ])
    ;
  }
  
  /**
   * Recupera a relação das depósitos em formato JSON.
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
    $this->debug("Acesso à relação de depósitos.");
    
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
    $this->session->set('deposit',
      [ 'name' => $name ]
    );
    
    // Corrige o escape dos campos
    $name = addslashes($name);
    
    try
    {
      // Formata o ordenamento dos campos
      $ORDER = $this->formatOrderBy($orderBy, $orderDir);

      // Inicializa a query
      $DepositsQry = Deposit::where('contractorid',
        '=', $this->authorization->getContractor()->id
      );

      // Acrescenta os filtros
      if (!empty($name)) {
        $DepositsQry
          ->whereRaw("public.unaccented(deposits.name) ILIKE "
              . "public.unaccented(E'%{$name}%')"
            )
        ;
      }

      // Conclui nossa consulta
      $deposits = $DepositsQry
        ->skip($start)
        ->take($length)
        ->orderByRaw($ORDER)
        ->get([
            'depositid AS id',
            'name',
            'comments',
            'devicetype',
            'master',
            $this->DB->raw('count(*) OVER() AS fullcount')
          ])
      ;
      
      if (count($deposits) > 0) {
        $rowCount = $deposits[0]->fullcount;
        
        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'draw' => $draw,
              'recordsTotal' => $rowCount,
              'recordsFiltered' => $rowCount,
              'data' => $deposits
            ])
        ;
      } else {
        if (empty($name)) {
          $error = "Não temos depósitos cadastrados.";
        } else {
          $error = "Não temos depósitos cadastrados cujo nome contém "
            . "<i>{$name}</i>."
          ;
        }
      }
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno no banco de dados: {error}.",
        [ 'module' => 'depósitos',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de "
        . "depósitos. Erro interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno: {error}.",
        [ 'module' => 'depósitos',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de "
        . "depósitos. Erro interno."
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
   * Exibe um formulário para adição de um depósito, quando solicitado,
   * e confirma os dados enviados.
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
    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();

    // Recupera as informações de tipos de dispositivos armazenáveis
    $deviceTypes = DeviceType::get();

    // Verifica se estamos postando os dados
    if ($request->isPost()) {
      // Os dados estão sendo postados
      
      // Registra o acesso
      $this->debug("Processando à adição de depósito.");

      // Monta uma matriz para validação dos tipos de dispositivos
      $deviceTypesValues = [ ];
      foreach ($deviceTypes AS $deviceType) {
        $deviceTypesValues[] = $deviceType['id'];
      }
      
      // Valida os dados
      $this->validator->validate($request, [
        'name' => V::notBlank()
          ->length(2, 50)
          ->setName('Nome'),
        'comments' => V::notBlank()
          ->setName('Comentário'),
        'devicetype' => V::notBlank()
          ->in($deviceTypesValues)
          ->setName('Tipo de dispositivo armazenável'),
        'master' => V::boolVal()
          ->setName('É o depósito principal')
      ]);

      if ($this->validator->isValid()) {
        // Grava as informações no banco de dados
        try
        {
          // Recupera os dados do depósito
          $depositData = $this->validator->getValues();
          
          // Primeiro, verifica se não temos um depósito com
          // o mesmo nome neste contratante
          if (Deposit::where("contractorid", '=', $contractor->id)
                ->whereRaw("public.unaccented(name) ILIKE "
                    . "public.unaccented('{$depositData['name']}')"
                  )
                ->count() === 0) {
            // Grava a novo depósito
            $deposit = new Deposit();
            $deposit->fill($depositData);
            // Adiciona o contratante
            $deposit->contractorid = $contractor->id;
            $deposit->save();
            $depositID = $deposit->depositid;

            // Registra o sucesso
            $this->info("Cadastrado o depósito '{name}' no contratante "
              . "'{contractor}' com sucesso.",
              [ 'name'  => $depositData['name'],
                'contractor' => $contractor->name ]
            );
            
            // Alerta o usuário
            $this->flash("success", "O depósito <i>'{name}'</i> foi "
              . "cadastrado com sucesso.",
              [ 'name'  => $depositData['name'] ]
            );
            
            // Registra o evento
            $this->debug("Redirecionando para {routeName}",
              [ 'routeName' => 'ERP\Parameterization\Deposits' ]
            );
            
            // Redireciona para a página de gerenciamento de depósitos
            return $this->redirect($response,
              'ERP\Parameterization\Deposits')
            ;
          } else {
            // Registra o erro
            $this->debug("Não foi possível inserir as informações do "
              . "depósito '{name}' do contratante '{contractor}'. Já "
              . "existe um depósito com o mesmo nome.",
              [ 'name'  => $depositData['name'],
                'contractor' => $contractor->name ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Já existe um depósito com o nome "
              . "<i>'{name}'</i>.",
              [ 'name'  => $depositData['name'] ]
            );
          }
        }
        catch(QueryException $exception)
        {
          // Registra o erro
          $this->error("Não foi possível inserir as informações do "
            . "depósito '{name}' no contratante '{contractor}'. Erro "
            . "interno no banco de dados: {error}.",
            [ 'name'  => $depositData['name'],
              'contractor' => $contractor->name,
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações do depósito. Erro interno no banco de "
            . "dados."
          );
        }
        catch(Exception $exception)
        {
          // Registra o erro
          $this->error("Não foi possível inserir as informações do "
            . "depósito '{name}' no contratante '{contractor}'. Erro "
            . "interno: {error}.",
            [ 'name'  => $depositData['name'],
              'contractor' => $contractor->name,
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações do depósito. Erro interno."
          );
        }
      }
    } else {
      // Carrega os dados iniciais para simplificar a digitação
      $this->validator->setValues(
      [
        'devicetype' => $deviceTypes[0]['id']
      ]);
    }
    
    // Exibe um formulário para adição de um depósito
    
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ERP\Home')
    );
    $this->breadcrumb->push('Parametrização', '');
    $this->breadcrumb->push('Depósitos',
      $this->path('ERP\Parameterization\Deposits')
    );
    $this->breadcrumb->push('Adicionar',
      $this->path('ERP\Parameterization\Deposits\Add')
    );
    
    // Registra o acesso
    $this->info("Acesso à adição de depósito no contratante "
      . "'{contractor}'.",
      [ 'contractor' => $contractor->name ]
    );
    
    // Renderiza a página
    return $this->render($request, $response,
      'erp/parameterization/deposits/deposit.twig',
      [ 'formMethod' => 'POST',
        'deviceTypes' => $deviceTypes ])
    ;
  }
  
  /**
   * Exibe um formulário para edição de um depósito, quando solicitado,
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
  public function edit(Request $request, Response $response,
    array $args)
  {
    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();

    // Recupera as informações de tipos de dispositivos armazenáveis
    $deviceTypes = DeviceType::get();
    
    try
    {
      // Recupera as informações do depósito
      $depositID = $args['depositID'];
      $deposit = Deposit::where('contractorid', '=', $contractor->id)
        ->where('depositid', '=', $depositID)
        ->firstOrFail([
            'deposits.*',
            $this->DB->raw("CASE devicetype "
              . "  WHEN 'Equipment' THEN 'Apenas equipamentos' "
              . "  WHEN 'SimCard' THEN 'Apenas SIM Cards' "
              . "  ELSE 'Qualquer dispositivo' "
              . "END AS devicetypename"
            )
          ])
      ;

      // Verifica se estamos modificando os dados
      if ($request->isPut()) {
        // Os dados estão sendo modificados
        
        // Registra o acesso
        $this->debug("Processando à edição do depósito '{name}' no "
          . "contratante {contractor}.",
          [ 'name' => $deposit['name'],
            'contractor' => $contractor->name ]
        );
        
        // Monta uma matriz para validação dos tipos de dispositivos
        $deviceTypesValues = [ ];
        foreach ($deviceTypes AS $deviceType) {
          $deviceTypesValues[] = $deviceType['id'];
        }
        
        // Valida os dados
        $this->validator->validate($request, [
          'depositid' => V::intVal()
            ->setName('ID do depósito'),
          'name' => V::notBlank()
            ->length(2, 50)
            ->setName('Nome'),
          'comments' => V::notBlank()
            ->setName('Comentário'),
          'devicetype' => V::notBlank()
            ->in($deviceTypesValues)
            ->setName('Tipo de dispositivo armazenável'),
          'master' => V::boolVal()
            ->setName('É o depósito principal')
        ]);

        if ($this->validator->isValid()) {
          // Grava as informações no banco de dados
          try
          {
            // Recupera os dados modificados do depósito
            $depositData = $this->validator->getValues();
            
            // Primeiro, verifica se não mudamos o nome do depósito
            $save = false;
            if ($deposit['name'] != $depositData['name']) {
              // Modificamos o nome do depósito, então verifica
              // se temos um depósito com o mesmo nome neste
              // contratante antes de prosseguir
              if (Deposit::where("contractorid", '=', $contractor->id)
                    ->whereRaw("public.unaccented(name) ILIKE "
                        . "public.unaccented('{$depositData['name']}')"
                      )
                    ->count() === 0) {
                $save = true;
              } else {
                // Registra o erro
                $this->debug("Não foi possível modificar as "
                  . "informações do depósito '{name}' no contratante "
                  . "'{contractor}'. Já existe um depósito com o mesmo "
                  . "nome.",
                  [ 'name'  => $depositData['name'],
                    'contractor' => $contractor->name ]
                );
                
                // Alerta o usuário
                $this->flashNow("error", "Já existe um depósito com "
                  . "o mesmo nome."
                );
              }
            } else {
              $save = true;
            }
            
            if ($save) {
              // Grava as informações do depósito

              // Gravamos os dados
              $deposit = Deposit::findOrFail($depositID);
              $deposit->fill($depositData);
              $deposit->save();
              
              // Registra o sucesso
              $this->info("Modificado o depósito '{name}' no "
                . "contratante '{contractor}' com sucesso.",
                [ 'name'  => $depositData['name'],
                  'contractor' => $contractor->name ]
              );
              
              // Alerta o usuário
              $this->flash("success", "O depósito <i>'{name}'</i> "
                . "foi modificado com sucesso.",
                [ 'name'  => $depositData['name'] ]
              );
              
              // Registra o evento
              $this->debug("Redirecionando para {routeName}",
                [ 'routeName' => 'ERP\Parameterization\Deposits' ]
              );
              
              // Redireciona para a página de gerenciamento de depósitos
              return $this->redirect($response,
                'ERP\Parameterization\Deposits')
              ;
            }
          }
          catch(QueryException $exception)
          {
            // Registra o erro
            $this->error("Não foi possível modificar as informações do "
              . "depósito '{name}' no contratante '{contractor}'. Erro "
              . "interno no banco de dados: {error}",
              [ 'name'  => $depositData['name'],
                'contractor' => $contractor->name,
                'error' => $exception->getMessage() ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Não foi possível modificar as "
              . "informações do depósito. Erro interno no banco de "
              . "dados."
            );
          }
          catch(Exception $exception)
          {
            // Registra o erro
            $this->error("Não foi possível modificar as informações do "
              . "depósito '{name}' no contratante '{contractor}'. Erro "
              . "interno: {error}",
              [ 'name'  => $depositData['name'],
                'contractor' => $contractor->name,
                'error' => $exception->getMessage() ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Não foi possível modificar as "
              . "informações do depósito. Erro interno."
            );
          }
        }
      } else {
        // Carrega os dados atuais
        $this->validator->setValues($deposit->toArray());
      }
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar o depósito código "
        . "{depositID}.",
        [ 'depositID' => $depositID ]
      );
      
      // Alerta o usuário
      $this->flash("error", "Não foi possível localizar este "
        . "depósito."
      );
      
      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [ 'routeName' => 'ERP\Parameterization\Deposits' ]
      );
      
      // Redireciona para a página de gerenciamento de depósitos
      return $this->redirect($response,
        'ERP\Parameterization\Deposits')
      ;
    }
    
    // Exibe um formulário para edição de um depósito
    
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ERP\Home')
    );
    $this->breadcrumb->push('Parametrização', '');
    $this->breadcrumb->push('Depósitos',
      $this->path('ERP\Parameterization\Deposits')
    );
    $this->breadcrumb->push('Editar',
      $this->path('ERP\Parameterization\Deposits\Edit', [
        'depositID' => $depositID
      ])
    );
    
    // Registra o acesso
    $this->info("Acesso à edição do depósito '{name}' do contratante "
      . "'{contractor}'.",
      [ 'name' => $deposit['name'],
        'contractor' => $contractor->name ]
    );
    
    // Renderiza a página
    return $this->render($request, $response,
      'erp/parameterization/deposits/deposit.twig',
      [ 'formMethod' => 'PUT',
        'deviceTypes' => $deviceTypes ])
    ;
  }
  
  /**
   * Remove o depósito.
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
    $this->debug("Processando à remoção de depósito.");
    
    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();

    // Recupera o ID
    $depositID = $args['depositID'];

    try
    {
      // Recupera as informações do depósito
      $deposit = Deposit::findOrFail($depositID);
      
      // Inicia a transação
      $this->DB->beginTransaction();
      
      // Agora apaga o depósito
      $deposit->delete();
      
      // Efetiva a transação
      $this->DB->commit();
      
      // Registra o sucesso
      $this->info("O depósito '{name}' do contratante '{contractor}' "
        . "foi removido com sucesso.",
        [ 'name' => $deposit->name,
          'contractor' => $contractor->name ]
      );
      
      // Informa que a remoção foi realizada com sucesso
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getParams(),
            'message' => "Removido o depósito {$deposit->name}",
            'data' => "Delete"
          ])
      ;
    }
    catch(ModelNotFoundException $exception)
    {
      // Reverte (desfaz) a transação
      $this->DB->rollBack();

      // Registra o erro
      $this->error("Não foi possível localizar o depósito código "
        . "{depositID} para remoção.",
        [ 'depositID' => $depositID ]
      );
      
      $message = "Não foi possível localizar o depósito para remoção.";
    }
    catch(QueryException $exception)
    {
      // Reverte (desfaz) a transação
      $this->DB->rollBack();

      // Registra o erro
      $this->error("Não foi possível remover as informações do "
        . "depósito ID {id} no contratante '{contractor}'. Erro "
        . "interno no banco de dados: {error}.",
        [ 'id'  => $depositID,
          'contractor' => $contractor->name,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível remover o depósito. Erro interno no "
        . "banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Reverte (desfaz) a transação
      $this->DB->rollBack();

      // Registra o erro
      $this->error("Não foi possível remover as informações do "
        . "depósito ID {id} no contratante '{contractor}'. Erro "
        . "interno: {error}.",
        [ 'id'  => $depositID,
          'contractor' => $contractor->name,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível remover o depósito. Erro interno.";
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
