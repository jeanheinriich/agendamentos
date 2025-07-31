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
 * O controlador do gerenciamento de tipos de telefones.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Controllers\ADM\Parameterization\Telephony;

use App\Models\PhoneType;
use Core\Controllers\Controller;
use Core\Controllers\QueryTrait;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Respect\Validation\Validator as V;
use Slim\Http\Request;
use Slim\Http\Response;

class PhoneTypesController
  extends Controller
{
  /**
   * Os métodos para manipular queries.
   */
  use QueryTrait;

  /**
   * Exibe a página inicial do gerenciamento de tipos de telefones.
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
    $this->breadcrumb->push('Parametrização', '');
    $this->breadcrumb->push('Telefonia', '');
    $this->breadcrumb->push('Tipos de telefones',
      $this->path('ADM\Parameterization\Telephony\PhoneTypes')
    );
    
    // Registra o acesso
    $this->info("Acesso ao gerenciamento de tipos de telefones.");
    
    // Recupera os dados da sessão
    $phoneType = $this->session->get('phonetype',
      [ 'name' => '' ]
    );
    
    // Renderiza a página
    return $this->render($request, $response,
      'adm/parameterization/telephony/phonetypes/phonetypes.twig',
      [ 'phonetype' => $phoneType ])
    ;
  }
  
  // Recupera a relação dos tipos de telefones em formato JSON
  public function get(Request $request, Response $response)
  {
    $this->debug("Acesso à relação de tipos de telefones.");
    
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
    $this->session->set('phonetype',
      [ 'name' => $name ]
    );

    // Corrige o escape dos campos
    $name = addslashes($name);
    
    try
    {
      // Formata o ordenamento dos campos
      $ORDER = $this->formatOrderBy($orderBy, $orderDir);
      
      // Inicializa a query
      $phoneTypeQry = PhoneType::whereRaw("1=1");
      
      // Acrescenta os filtros
      if (!empty($name)) {
        $phoneTypeQry
          ->whereRaw("public.unaccented(name) ILIKE "
              . "public.unaccented('%{$name}%')"
            )
        ;
      }

      // Conclui nossa consulta
      $phoneTypes = $phoneTypeQry
        ->skip($start)
        ->take($length)
        ->orderByRaw($ORDER)
        ->get([
            'phonetypeid AS id',
            'name',
            $this->DB->raw('count(*) OVER() AS fullcount')
          ])
      ;
      
      if (count($phoneTypes) > 0) {
        $rowCount = $phoneTypes[0]->fullcount;
        
        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'draw' => $draw,
              'recordsTotal' => $rowCount,
              'recordsFiltered' => $rowCount,
              'data' => $phoneTypes
            ])
        ;
      } else {
        if (empty($name)) {
          $error = "Não temos tipos de telefones cadastrados.";
        } else {
          $error = "Não temos tipos de telefones cadastrados cujo "
            . "nome contém <i>{$name}</i>."
          ;
        }
      }
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno no banco de dados: {error}.",
        [ 'module' => 'tipos de telefones',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de tipos de "
        . "telefones. Erro interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno: {error}.",
        [ 'module' => 'tipos de telefones',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de tipos de "
        . "telefones. Erro interno."
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
   * Exibe um formulário para adição de um tipo de telefone, quando
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
    // Verifica se estamos postando os dados
    if ($request->isPost()) {
      // Os dados estão sendo postados
      
      // Registra o acesso
      $this->debug("Processando à adição de tipo de telefone.");
      
      // Valida os dados
      $this->validator->validate($request, [
        'name' => V::notBlank()
          ->length(2, 20)
          ->setName('Tipo de telefone')
      ]);

      if ($this->validator->isValid()) {
        // Grava as informações no banco de dados
        try
        {
          // Recupera os dados do tipo de telefone
          $phoneTypeData = $this->validator->getValues();
          
          // Primeiro, verifica se não temos um tipo de telefone com o
          // mesmo nome
          if (PhoneType::whereRaw("public.unaccented(name) ILIKE "
                    . "public.unaccented('{$phoneTypeData['name']}')"
                  )
                ->count() === 0) {
            // Grava o novo tipo de telefone
            $phoneType = new PhoneType();
            $phoneType->fill($phoneTypeData);
            $phoneType->save();
            
            // Registra o sucesso
            $this->info("Cadastrado o tipo de telefone '{name}' "
              . "com sucesso.",
              [ 'name'  => $phoneTypeData['name'] ]
            );
            
            // Alerta o usuário
            $this->flash("success", "O tipo de telefone "
              . "<i>'{name}'</i> foi cadastrado com sucesso.",
              [ 'name'  => $phoneTypeData['name'] ]
            );
            
            // Registra o evento
            $this->debug("Redirecionando para {routeName}",
              [ 'routeName' => 'ADM\Parameterization\Telephony\PhoneTypes' ]
            );
            
            // Redireciona para a página de gerenciamento de tipos de
            // telefones
            return $this->redirect($response,
              'ADM\Parameterization\Telephony\PhoneTypes')
            ;
          } else {
            // Registra o erro
            $this->debug("Não foi possível inserir as informações do "
              . "tipo de telefone '{name}'. Já existe um tipo de "
              . "telefone com o mesmo nome.",
              [ 'name'  => $phoneTypeData['name'] ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Já existe um tipo de telefone "
              . " com o nome <i>'{name}'</i>.",
              [ 'name'  => $phoneTypeData['name'] ]
            );
          }
        }
        catch(QueryException $exception)
        {
          // Registra o erro
          $this->error("Não foi possível inserir as informações do "
            . "tipo de telefone '{name}'. Erro interno no banco de "
            . "dados: {error}.",
            [ 'name'  => $phoneTypeData['name'],
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações do tipo de telefone. Erro interno no banco "
            . "de dados."
          );
        }
        catch(Exception $exception)
        {
          // Registra o erro
          $this->error("Não foi possível inserir as informações do "
            . "tipo de telefone '{name}'. Erro interno: {error}.",
            [ 'name'  => $phoneTypeData['name'],
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações do tipo de telefone. Erro interno."
          );
        }
      }
    }
    
    // Exibe um formulário para adição de um tipo de telefone
    
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ADM\Home')
    );
    $this->breadcrumb->push('Parametrização', '');
    $this->breadcrumb->push('Dados Cadastrais', '');
    $this->breadcrumb->push('Tipos de telefones',
      $this->path('ADM\Parameterization\Telephony\PhoneTypes')
    );
    $this->breadcrumb->push('Adicionar',
      $this->path('ADM\Parameterization\Telephony\PhoneTypes\Add')
    );
    
    // Registra o acesso
    $this->info("Acesso à adição de tipo de telefone.");
    
    // Renderiza a página
    return $this->render($request, $response,
      'adm/parameterization/telephony/phonetypes/phonetype.twig',
      [ 'formMethod' => 'POST' ])
    ;
  }
  
  /**
   * Exibe um formulário para edição de um tipo de telefone, quando
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
    try
    {
      // Recupera as informações do tipo de telefone
      $phoneTypeID = $args['phoneTypeID'];
      $phoneType = PhoneType::findOrFail($phoneTypeID);
      
      // Verifica se estamos modificando os dados
      if ($request->isPut()) {
        // Os dados estão sendo modificados
        
        // Registra o acesso
        $this->debug("Processando à edição do tipo de telefone "
          . "'{name}'.",
          [ 'name' => $phoneType['name'] ]
        );
        
        // Valida os dados
        $this->validator->validate($request, [
          'name' => V::notBlank()
            ->length(2, 20)
            ->setName('Tipo de telefone')
        ]);

        if ($this->validator->isValid()) {
          // Grava as informações no banco de dados
          try
          {
            // Recupera os dados modificados do tipo de telefone
            $phoneTypeData = $this->validator->getValues();
            
            // Primeiro, verifica se não mudamos o nome do tipo de
            // telefone
            $save = false;
            if ($phoneType->name != $phoneTypeData['name']) {
              // Modificamos o nome do tipo de telefone, então verifica
              // se temos um tipo de telefone com o mesmo nome antes de
              // prosseguir
              if (PhoneType::whereRaw("public.unaccented(name) ILIKE "
                        . "public.unaccented('{$phoneTypeData['name']}')"
                      )
                    ->count() === 0) {
                $save = true;
              } else {
                // Registra o erro
                $this->debug("Não foi possível modificar as "
                  . "informações do tipo de telefone '{name}'. Já "
                  . "existe um tipo de telefone com o mesmo nome.",
                  [ 'name'  => $phoneTypeData['name'] ]
                );
                
                // Alerta o usuário
                $this->flashNow("error", "Já existe um tipo de "
                  . "telefone com o nome <i>'{name}'</i>.",
                  [ 'name'  => $phoneTypeData['name'] ]
                );
              }
            } else {
              $save = true;
            }
            
            if ($save) {
              // Grava as informações do tipo de telefone
              $phoneType->fill($phoneTypeData);
              $phoneType->save();
              
              // Registra o sucesso
              $this->info("Modificado o tipo de telefone '{name}' "
                . "com sucesso.",
                [ 'name'  => $phoneTypeData['name'] ]
              );
              
              // Alerta o usuário
              $this->flash("success", "O tipo de telefone "
                . "<i>'{name}'</i> foi modificado com sucesso.",
                [ 'name'  => $phoneTypeData['name'] ]
              );
              
              // Registra o evento
              $this->debug("Redirecionando para {routeName}",
                [ 'routeName' => 'ADM\Parameterization\Telephony\PhoneTypes' ]
              );
              
              // Redireciona para a página de gerenciamento de tipos de
              // telefones
              return $this->redirect($response,
                'ADM\Parameterization\Telephony\PhoneTypes')
              ;
            }
          }
          catch(QueryException $exception)
          {
            // Registra o erro
            $this->error("Não foi possível modificar as informações "
              . "do tipo de telefone '{name}'. Erro interno no banco "
              . "de dados: {error}.",
              [ 'name'  => $phoneTypeData['name'],
                'error' => $exception->getMessage() ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Não foi possível modificar as "
              . "informações do tipo de telefone. Erro interno no "
              . "banco de dados."
            );
          }
          catch(Exception $exception)
          {
            // Registra o erro
            $this->error("Não foi possível modificar as informações "
              . "do tipo de telefone '{name}'. Erro interno: {error}.",
              [ 'name'  => $phoneTypeData['name'],
                'error' => $exception->getMessage() ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Não foi possível modificar as "
              . "informações do tipo de telefone. Erro interno."
            );
          }
        }
      } else {
        // Carrega os dados atuais
        $this->validator->setValues($phoneType->toArray());
      }
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar o tipo de telefone "
        . "código {phoneTypeID}.",
        [ 'phoneTypeID' => $phoneTypeID ]
      );
      
      // Alerta o usuário
      $this->flash("error", "Não foi possível localizar este tipo de "
        . "telefone."
      );
      
      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [ 'routeName' => 'ADM\Parameterization\Telephony\PhoneTypes' ]
      );
      
      // Redireciona para a página de gerenciamento de tipos de telefones
      return $this->redirect($response,
        'ADM\Parameterization\Telephony\PhoneTypes')
      ;
    }
    
    // Exibe um formulário para edição de um tipo de telefone
    
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ADM\Home')
    );
    $this->breadcrumb->push('Parametrização', '');
    $this->breadcrumb->push('Dados Cadastrais', '');
    $this->breadcrumb->push('Tipos de telefones',
      $this->path('ADM\Parameterization\Telephony\PhoneTypes')
    );
    $this->breadcrumb->push('Editar',
      $this->path('ADM\Parameterization\Telephony\PhoneTypes\Edit', [
        'phoneTypeID' => $phoneTypeID
      ])
    );
    
    // Registra o acesso
    $this->info("Acesso à edição do tipo de telefone '{name}'.",
      [ 'name' => $phoneType['name'] ]
    );
    
    // Renderiza a página
    return $this->render($request, $response,
      'adm/parameterization/telephony/phonetypes/phonetype.twig',
      [ 'formMethod' => 'PUT' ])
    ;
  }
  
  /**
   * Remove o tipo de telefone.
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
    $this->debug("Processando à remoção de tipo de telefone.");
    
    // Recupera o ID
    $phoneTypeID = $args['phoneTypeID'];

    try
    {
      // Recupera as informações do tipo de telefone
      $phoneType = PhoneType::findOrFail($phoneTypeID);
      
      // Agora apaga o tipo de telefone
      $phoneType->delete();
      
      // Registra o sucesso
      $this->info("O tipo de telefone '{name}' foi removido com "
        . "sucesso.",
        [ 'name' => $phoneType->name ]
      );
      
      // Informa que a remoção foi realizada com sucesso
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getParams(),
            'message' => "Removido o tipo de telefone "
              . "{$phoneType->name}",
            'data' => "Delete"
          ])
      ;
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar o tipo de telefone "
        . "código {phoneTypeID} para remoção.",
        [ 'phoneTypeID' => $phoneTypeID ]);
      
      $message = "Não foi possível localizar o tipo de telefone para "
        . "remoção."
      ;
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível remover as informações do tipo "
        . "de telefone ID {id}. Erro interno no banco de dados: "
        . "{error}.",
        [ 'id' => $phoneTypeID,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível remover o tipo de telefone. Erro "
        . "interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível remover as informações do tipo "
        . "de telefone ID {id}. Erro interno: {error}.",
        [ 'id' => $phoneTypeID,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível remover o tipo de telefone. Erro "
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
