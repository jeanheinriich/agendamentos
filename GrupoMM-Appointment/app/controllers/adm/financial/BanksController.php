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
 * O controlador do gerenciamento de bancos.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Controllers\ADM\Financial;

use App\Models\Bank;
use Core\Controllers\Controller;
use Core\Controllers\QueryTrait;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Respect\Validation\Validator as V;
use Slim\Http\Request;
use Slim\Http\Response;

class BanksController
  extends Controller
{
  /**
   * Os métodos para manipular queries.
   */
  use QueryTrait;

  /**
   * Exibe a página inicial do gerenciamento de bancos.
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
      $this->path('ADM\Home')
    );
    $this->breadcrumb->push('Financeiro', '');
    $this->breadcrumb->push('Bancos',
      $this->path('ADM\Financial\Banks')
    );
    
    // Registra o acesso
    $this->info("Acesso ao gerenciamento de bancos.");
    
    // Recupera os dados da sessão
    $bank = $this->session->get('bank',
      [ 'searchField' => 'name',
        'searchValue' => '' ])
    ;
    
    // Renderiza a página
    return $this->render($request, $response,
      'adm/financial/banks/banks.twig',
      [ 'bank' => $bank ])
    ;
  }

  /**
   * Recupera a relação dos bancos em formato JSON.
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
    $this->debug("Acesso à relação de bancos.");
    
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
    $searchField = $postParams['searchField'];
    $searchValue = $postParams['searchValue'];
    
    // Seta os valores da última pesquisa na sessão
    $this->session->set('bank',
      [ 'searchField' => $searchField,
        'searchValue' => $searchValue ]
    );

    // Corrige o escape dos campos
    $searchValue = addslashes($searchValue);
    
    try
    {
      // Formata o ordenamento dos campos
      $ORDER = $this->formatOrderBy($orderBy, $orderDir);

      // Inicializa a query
      $BankQry = Bank::whereRaw("1=1");
      
      // Acrescenta os filtros
      if (!empty($searchValue)) {
        switch ($searchField) {
          case 'name':
            // Filtra por parte do nome
            $BankQry
              ->whereRaw("public.unaccented(name) ILIKE "
                  . "'%{$searchValue}%'"
                );

            break;
          default:
            // Filtra pelo campo indicado
            $BankQry
              ->where(strtolower($searchField), $searchValue)
            ;
        }
      }

      // Conclui nossa consulta
      $banks = $BankQry
        ->skip($start)
        ->take($length)
        ->orderByRaw($ORDER)
        ->get([
            'bankid AS id',
            'name',
            'shortname',
            $this->DB->raw('count(*) OVER() AS fullcount')
          ])
      ;
      
      if (count($banks) > 0) {
        $rowCount = $banks[0]->fullcount;
        
        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'draw' => $draw,
              'recordsTotal' => $rowCount,
              'recordsFiltered' => $rowCount,
              'data' => $banks
            ])
        ;
      } else {
        if (empty($searchValue)) {
          $error = "Não temos bancos cadastrados.";
        } else {
          switch ($searchField)
          {
            case 'bankID':
              $error = "Não temos bancos cujo código contém "
                . "<i>{$searchValue}</i>"
              ;

              break;
            case 'name':
              $error = "Não temos bancos cujo nome contém "
                . "<i>{$searchValue}</i>"
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
        . "{module}. Erro interno no banco de dados: {error}.",
        [ 'module' => 'bancos',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de bancos. "
        . "Erro interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno: {error}.",
        [ 'module' => 'bancos',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de bancos. "
        . "Erro interno."
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
   * Exibe um formulário para adição de um banco, quando solicitado, e
   * confirma os dados enviados.
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
    // Verifica se estamos postando os dados
    if ($request->isPost()) {
      // Os dados estão sendo postados
      
      // Registra o acesso
      $this->debug("Processando à adição de um banco.");
      
      // Valida os dados
      $this->validator->validate($request, [
        'bankid' => V::notBlank()
          ->numeric()
          ->length(3, 3)
          ->setName('Código do banco'),
        'name' => V::notEmpty()
          ->length(3, 100)
          ->setName('Razão social do banco'),
        'shortname' =>  V::notEmpty()
          ->length(3, 50)
          ->setName('Nome fantasia')
      ]);

      if ($this->validator->isValid()) {
        // Grava as informações no banco de dados
        try
        {
          // Recupera os dados do banco
          $bankData = $this->validator->getValues();
          
          // Primeiro, verifica se não temos um banco com o mesmo código
          if (Bank::where("bankid", $bankData['bankid'])
                  ->count() === 0) {
            // Grava o novo banco
            $bank = new Bank();
            $bank->fill($bankData);
            $bank->save();
            
            // Registra o sucesso
            $this->info("Cadastrado o banco '[{bankID}] {name}' com "
              .  "sucesso.",
              [ 'bankID'  => $bankData['bankid'],
                'name'  => $bankData['name'] ]
            );
            
            // Alerta o usuário
            $this->flash("success", "O banco <i>'[{bankID}] {name}'</i>"
              . " foi cadastrado com sucesso.",
              [ 'bankID'  => $bankData['bankid'],
                'name'  => $bankData['name'] ]
            );
            
            // Registra o evento
            $this->debug("Redirecionando para {routeName}",
              [ 'routeName' => 'ADM\Financial\Banks' ]
            );
            
            // Redireciona para a página de gerenciamento de bancos
            return $this->redirect($response, 'ADM\Financial\Banks');
          } else {
            // Registra o erro
            $this->debug("Não foi possível inserir as informações do "
              . "banco '[{bankID}] {name}'. Já existe um banco com o "
              . "mesmo código.",
              [ 'bankID'  => $bankData['bankid'],
                'name'  => $bankData['name'] ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Já existe um banco com o código "
              . "<i>'{bankID}'</i>.",
              [ 'bankID'  => $bankData['bankid'] ]
            );
          }
        }
        catch(QueryException $exception)
        {
          // Registra o erro
          $this->error("Não foi possível inserir as informações do "
            . "banco '[{bankID}] {name}'. Erro interno no banco de "
            . "dados: {error}.",
            [ 'bankID'  => $bankData['bankid'],
              'name'  => $bankData['name'],
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações do banco. Erro interno no banco de dados."
          );
        }
        catch(Exception $exception)
        {
          // Registra o erro
          $this->error("Não foi possível inserir as informações do "
            . "banco '[{bankID}] {name}'. Erro interno: {error}.",
            [ 'bankID'  => $bankData['bankid'],
              'name'  => $bankData['name'],
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações do banco. Erro interno."
          );
        }
      }
    }
    
    // Exibe um formulário para adição de um banco
    
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ADM\Home')
    );
    $this->breadcrumb->push('Financeiro', '');
    $this->breadcrumb->push('Bancos',
      $this->path('ADM\Financial\Banks')
    );
    $this->breadcrumb->push('Adicionar',
      $this->path('ADM\Financial\Banks\Add')
    );
    
    // Registra o acesso
    $this->info("Acesso à adição de banco.");
    
    // Renderiza a página
    return $this->render($request, $response,
      'adm/financial/banks/bank.twig',
      [ 'formMethod' => 'POST' ])
    ;
  }

  /**
   * Exibe um formulário para edição de um banco, quando solicitado, e
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
  public function edit(Request $request, Response $response,
    array $args)
  {
    try
    {
      // Recupera as informações do banco
      $bankID = $args['bankID'];
      $bank = Bank::findOrFail($bankID);
      
      // Verifica se estamos modificando os dados
      if ($request->isPut()) {
        // Os dados estão sendo modificados

        // Registra o acesso
        $this->debug("Processando à edição do banco '[{bankID}] "
          . "{name}'.",
          [ 'bankID'  => $bank['bankid'],
            'name' => $bank['name'] ]
        );
        
        // Valida os dados
        $this->validator->validate($request, [
          'bankid' => V::notBlank()
            ->numeric()
            ->length(3, 3)
            ->setName('Código do banco'),
          'name' => V::notEmpty()
            ->length(3, 100)
            ->setName('Razão social do banco'),
          'shortname' =>  V::notEmpty()
            ->length(3, 50)
            ->setName('Nome fantasia')
        ]);

        if ($this->validator->isValid()) {
          // Grava as informações no banco de dados
          try
          {
            // Recupera os dados modificados do banco
            $bankData = $this->validator->getValues();
            
            // Primeiro, verifica se não mudamos o código do banco
            $save = false;
            if ($bankID != $bankData['bankid']) {
              // Modificamos o código, então verifica se temos um banco
              // com o mesmo código antes de prosseguir
              if (Bank::where("bankid", $bankData['bankid'])
                    ->count() === 0) {
                $save = true;
              } else {
                // Registra o erro
                $this->debug("Não foi possível modificar as "
                  . "informações do banco '[{bankID}] {name}'. Já "
                  . "existe um banco com o mesmo código.",
                  [ 'bankID'  => $bankData['bankid'],
                    'name'  => $bankData['name'] ]
                );
                
                // Alerta o usuário
                $this->flashNow("error", "Já existe um banco com o "
                  . "código <i>'{bankID}'</i>.",
                  [ 'bankID'  => $bankData['bankid'] ]
                );
              }
            } else {
              $save = true;
            }
            
            if ($save) {
              // Grava as informações do banco
              $bank->fill($bankData);
              $bank->save();
              
              // Registra o sucesso
              $this->info("Modificado o banco '[{bankID}] {name}' "
                . "com sucesso.",
                [ 'bankID'  => $bankData['bankid'],
                  'name'  => $bankData['name'] ]
              );
              
              // Alerta o usuário
              $this->flash("success", "O banco <i>'[{bankID}] "
                . "{name}'</i> foi modificado com sucesso.",
                [ 'bankID'  => $bankData['bankid'],
                  'name'  => $bankData['name'] ]
              );
              
              // Registra o evento
              $this->debug("Redirecionando para {routeName}",
                [ 'routeName' => 'ADM\Financial\Banks' ]
              );

              // Redireciona para a página de gerenciamento de bancos
              return $this->redirect($response, 'ADM\Financial\Banks');
            }
          }
          catch(QueryException $exception)
          {
            // Registra o erro
            $this->error("Não foi possível modificar as informações "
              . "do banco '[{bankID}] {name}'. Erro interno no banco "
              . "de dados: {error}.",
              [ 'bankID'  => $bankData['bankid'],
                'name'  => $bankData['name'],
                'error' => $exception->getMessage() ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Não foi possível modificar as "
              . "informações do banco. Erro interno no banco de dados."
            );
          }
          catch(Exception $exception)
          {
            // Registra o erro
            $this->error("Não foi possível modificar as informações "
              . "do banco '[{bankID}] {name}'. Erro interno: {error}.",
              [ 'bankID'  => $bankData['bankid'],
                'name'  => $bankData['name'],
                'error' => $exception->getMessage() ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Não foi possível modificar as "
              . "informações do banco. Erro interno."
            );
          }
        }
      } else {
        // Carrega os dados atuais
        $this->validator->setValues($bank->toArray());
      }
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar o banco código "
        . "{bankID}.",
        [ 'bankID' => $bankID ]
      );
      
      // Alerta o usuário
      $this->flash("error", "Não foi possível localizar este banco.");
      
      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [ 'routeName' => 'ADM\Financial\Banks' ]
      );
      
      // Redireciona para a página de gerenciamento de bancos
      return $this->redirect($response, 'ADM\Financial\Banks');
    }
    
    // Exibe um formulário para edição de um banco
    
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ADM\Home')
    );
    $this->breadcrumb->push('Financeiro', '');
    $this->breadcrumb->push('Bancos',
      $this->path('ADM\Financial\Banks')
    );
    $this->breadcrumb->push('Editar',
      $this->path('ADM\Financial\Banks\Edit', ['bankID' => $bankID])
    );
    
    // Registra o acesso
    $this->info("Acesso à edição do banco '[{bankID}] {name}'.",
      [ 'bankID' => $bank['bankid'],
        'name' => $bank['name'] ]
    );
    
    // Renderiza a página
    return $this->render($request, $response,
      'adm/financial/banks/bank.twig',
      [ 'formMethod' => 'PUT' ]
    );
  }
  
  /**
   * Remove o banco.
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
    $this->debug("Processando à remoção de banco.");
    
    // Recupera o ID
    $bankID = $args['bankID'];

    try
    {
      // Recupera as informações do banco
      $bank = Bank::findOrFail($bankID);
      
      // Agora apaga o banco
      $bank->delete();
      
      // Registra o sucesso
      $this->info("O banco '[{bankID}] {name}' foi removido com "
        . "sucesso.",
        [ 'bankID' => $bank->bankid,
          'name' => $bank->name ]
      );
      
      // Informa que a remoção foi realizada com sucesso
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getParams(),
            'message' => "Removido o banco {$bank->bankid} - "
              . "{$bank->name}",
            'data' => "Delete"
          ])
      ;
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar o banco código {bankID} "
        . "para remoção.",
        [ 'bankID' => $bankID ]
      );
      
      $message = "Não foi possível localizar o banco para remoção.";
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível remover as informações do banco "
        . "ID {bankID}. Erro interno no banco de dados: "
        . "{error}.",
        [ 'bankID' => $bankID,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível remover o banco. Erro interno no "
        . "banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível remover as informações do banco "
        . "ID {bankID}. Erro interno: {error}.",
        [ 'bankID' => $bankID,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível remover o banco. Erro interno.";
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
