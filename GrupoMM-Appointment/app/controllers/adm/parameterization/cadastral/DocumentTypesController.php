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
 * O controlador do gerenciamento de tipos de documentos.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Controllers\ADM\Parameterization\Cadastral;

use App\Models\DocumentType;
use Core\Controllers\Controller;
use Core\Controllers\QueryTrait;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Respect\Validation\Validator as V;
use Slim\Http\Request;
use Slim\Http\Response;

class DocumentTypesController
  extends Controller
{
  /**
   * Os métodos para manipular queries.
   */
  use QueryTrait;

  /**
   * Exibe a página inicial do gerenciamento de tipos de documentos.
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
    $this->breadcrumb->push('Dados Cadastrais', '');
    $this->breadcrumb->push('Tipos de documentos',
      $this->path('ADM\Parameterization\Cadastral\DocumentTypes')
    );
    
    // Registra o acesso
    $this->info("Acesso ao gerenciamento de tipos de documentos.");
    
    // Recupera os dados da sessão
    $documentType = $this->session->get('documenttype',
      [ 'name' => '' ]
    );
    
    // Renderiza a página
    return $this->render($request, $response,
      'adm/parameterization/cadastral/documenttypes/documenttypes.twig',
      [ 'documenttype' => $documentType ])
    ;
  }
  
  /**
   * Recupera a relação dos tipos de documentos em formato JSON.
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
    $this->debug("Acesso à relação de tipos de documentos.");
    
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
    $this->session->set('documenttype',
      [ 'name' => $name ]
    );

    // Corrige o escape dos campos
    $name = addslashes($name);
    
    try
    {
      // Formata o ordenamento dos campos
      $ORDER = $this->formatOrderBy($orderBy, $orderDir);

      // Inicializa a query
      $DocumentTypeQry = DocumentType::whereRaw("1=1");
      
      // Acrescenta os filtros
      if (!empty($name)) {
        $DocumentTypeQry
          ->whereRaw("public.unaccented(name) ILIKE "
              . "public.unaccented('%{$name}%')"
            )
        ;
      }

      // Conclui nossa consulta
      $documentTypes = $DocumentTypeQry
        ->skip($start)
        ->take($length)
        ->orderByRaw($ORDER)
        ->get([
            'documenttypeid AS id',
            'name',
            'juridicalperson',
            $this->DB->raw('count(*) OVER() AS fullcount')
          ])
      ;
      
      if (count($documentTypes) > 0) {
        $rowCount = $documentTypes[0]->fullcount;
        
        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'draw' => $draw,
              'recordsTotal' => $rowCount,
              'recordsFiltered' => $rowCount,
              'data' => $documentTypes
            ])
        ;
      } else {
        if (empty($name)) {
          $error = "Não temos tipos de documentos cadastrados.";
        } else {
          $error = "Não temos tipos de documentos cadastrados cujo "
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
        [ 'module' => 'tipos de documentos',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de tipos de "
        . "documentos. Erro interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno: {error}.",
        [ 'module' => 'tipos de documentos',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de tipos de "
        . "documentos. Erro interno."
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
   * Exibe um formulário para adição de um tipo de documento, quando
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
      $this->debug("Processando à adição de tipo de documento.");
      
      // Valida os dados
      $this->validator->validate($request, [
        'name' => V::notBlank()
          ->length(2, 30)
          ->setName('Tipo de documento'),
        'juridicalperson' => V::boolVal()
          ->setName('Uso exclusivo de pessoa jurídica')
      ]);

      if ($this->validator->isValid()) {
        // Grava as informações no banco de dados
        try
        {
          // Recupera os dados do tipo de documento
          $documentTypeData = $this->validator->getValues();
          
          // Primeiro, verifica se não temos um tipo de documento com o
          // mesmo nome
          if (DocumentType::whereRaw("public.unaccented(name) ILIKE "
                    . "public.unaccented('{$documentTypeData['name']}')"
                  )
                ->count() === 0) {
            // Grava o novo tipo de documento
            $documentType = new DocumentType();
            $documentType->fill($documentTypeData);
            $documentType->save();
            
            // Registra o sucesso
            $this->info("Cadastrado o tipo de documento '{name}' "
              . "com sucesso.",
              [ 'name'  => $documentTypeData['name'] ]
            );
            
            // Alerta o usuário
            $this->flash("success", "O tipo de documento "
              . "<i>'{name}'</i> foi cadastrado com sucesso.",
              [ 'name'  => $documentTypeData['name'] ]
            );
            
            // Registra o evento
            $this->debug("Redirecionando para {routeName}",
              [ 'routeName' =>
                  'ADM\Parameterization\Cadastral\DocumentTypes' ]
            );
            
            // Redireciona para a página de gerenciamento de tipos de
            // documentos
            return $this->redirect($response,
              'ADM\Parameterization\Cadastral\DocumentTypes'
            );
          } else {
            // Registra o erro
            $this->debug("Não foi possível inserir as informações do "
              . "tipo de documento '{name}'. Já existe um tipo de "
              . "documento com o mesmo nome.",
              [ 'name'  => $documentTypeData['name'] ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Já existe um tipo de documento "
              . " com o nome <i>'{name}'</i>.",
              [ 'name'  => $documentTypeData['name'] ]
            );
          }
        }
        catch(QueryException $exception)
        {
          // Registra o erro
          $this->error("Não foi possível inserir as informações do "
            . "tipo de documento '{name}'. Erro interno no banco de "
            . "dados: {error}.",
            [ 'name'  => $documentTypeData['name'],
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações do tipo de documento. Erro interno no banco "
            . "de dados."
          );
        }
        catch(Exception $exception)
        {
          // Registra o erro
          $this->error("Não foi possível inserir as informações do "
            . "tipo de documento '{name}'. Erro interno: {error}.",
            [ 'name'  => $documentTypeData['name'],
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações do tipo de documento. Erro interno."
          );
        }
      }
    }
    
    // Exibe um formulário para adição de um tipo de documento
    
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ADM\Home')
    );
    $this->breadcrumb->push('Parametrização', '');
    $this->breadcrumb->push('Dados Cadastrais', '');
    $this->breadcrumb->push('Tipos de documentos',
      $this->path('ADM\Parameterization\Cadastral\DocumentTypes')
    );
    $this->breadcrumb->push('Adicionar',
      $this->path('ADM\Parameterization\Cadastral\DocumentTypes\Add')
    );
    
    // Registra o acesso
    $this->info("Acesso à adição de tipo de documento.");
    
    // Renderiza a página
    return $this->render($request, $response,
      'adm/parameterization/cadastral/documenttypes/documenttype.twig',
      [ 'formMethod' => 'POST' ])
    ;
  }
  
  /**
   * Exibe um formulário para edição de um tipo de documento, quando
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
      // Recupera as informações do tipo de documento
      $documentTypeID = $args['documentTypeID'];
      $documentType = DocumentType::findOrFail($documentTypeID);
      
      // Verifica se estamos modificando os dados
      if ($request->isPut()) {
        // Os dados estão sendo modificados
        
        // Registra o acesso
        $this->debug("Processando à edição do tipo de documento "
          . "'{name}'.",
          [ 'name' => $documentType['name'] ]
        );
        
        // Valida os dados
        $this->validator->validate($request, [
          'name' => V::notBlank()
            ->length(2, 30)
            ->setName('Tipo de documento'),
          'juridicalperson' => V::boolVal()
            ->setName('Uso exclusivo de pessoa jurídica')
        ]);

        if ($this->validator->isValid()) {
          // Grava as informações no banco de dados
          try
          {
            // Recupera os dados modificados do tipo de documento
            $documentTypeData = $this->validator->getValues();
            
            // Primeiro, verifica se não mudamos o nome do tipo de
            // documento
            $save = false;
            if ($documentType->name != $documentTypeData['name']) {
              // Modificamos o nome do tipo de documento, então verifica
              // se temos um tipo de documento com o mesmo nome antes de
              // prosseguir
              if (DocumentType::whereRaw("public.unaccented(name) ILIKE "
                      . "public.unaccented('{$documentTypeData['name']}')"
                    )
                   ->count() === 0) {
                $save = true;
              } else {
                // Registra o erro
                $this->debug("Não foi possível modificar as "
                  . "informações do tipo de documento '{name}'. Já "
                  . "existe um tipo de documento com o mesmo nome.",
                  [ 'name'  => $documentTypeData['name'] ]
                );
                
                // Alerta o usuário
                $this->flashNow("error", "Já existe um tipo de "
                  . "documento com o nome <i>'{name}'</i>.",
                  [ 'name'  => $documentTypeData['name'] ]
                );
              }
            } else {
              $save = true;
            }
            
            if ($save) {
              // Grava as informações do tipo de documento
              $documentType->fill($documentTypeData);
              $documentType->save();
              
              // Registra o sucesso
              $this->info("Modificado o tipo de documento '{name}' "
                . "com sucesso.",
                [ 'name'  => $documentTypeData['name'] ]
              );
              
              // Alerta o usuário
              $this->flash("success", "O tipo de documento "
                . "<i>'{name}'</i> foi modificado com sucesso.",
                [ 'name'  => $documentTypeData['name'] ]
              );
              
              // Registra o evento
              $this->debug("Redirecionando para {routeName}",
                [ 'routeName' => 'ADM\Parameterization\Cadastral\DocumentTypes' ]
              );
              
              // Redireciona para a página de gerenciamento de tipos de
              // documentos
              return $this->redirect($response,
                'ADM\Parameterization\Cadastral\DocumentTypes'
              );
            }
          }
          catch(QueryException $exception)
          {
            // Registra o erro
            $this->error("Não foi possível modificar as informações "
              . "do tipo de documento '{name}'. Erro interno no banco "
              . "de dados: {error}.",
              [ 'name'  => $documentTypeData['name'],
                'error' => $exception->getMessage() ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Não foi possível modificar as "
              . "informações do tipo de documento. Erro interno no "
              . "banco de dados."
            );
          }
          catch(Exception $exception)
          {
            // Registra o erro
            $this->error("Não foi possível modificar as informações "
              . "do tipo de documento '{name}'. Erro interno: {error}.",
              [ 'name'  => $documentTypeData['name'],
                'error' => $exception->getMessage() ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Não foi possível modificar as "
              . "informações do tipo de documento. Erro interno."
            );
          }
        }
      } else {
        // Carrega os dados atuais
        $this->validator->setValues($documentType->toArray());
      }
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar o tipo de documento "
        . "código {documentTypeID}.",
        [ 'documentTypeID' => $documentTypeID ]
      );
      
      // Alerta o usuário
      $this->flash("error", "Não foi possível localizar este tipo de "
        . "documento."
      );
      
      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [ 'routeName' => 'ADM\Parameterization\Cadastral\DocumentTypes' ]
      );
      
      // Redireciona para a página de gerenciamento de tipos de documentos
      return $this->redirect($response,
        'ADM\Parameterization\Cadastral\DocumentTypes'
      );
    }
    
    // Exibe um formulário para edição de um tipo de documento
    
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ADM\Home')
    );
    $this->breadcrumb->push('Parametrização', '');
    $this->breadcrumb->push('Dados Cadastrais', '');
    $this->breadcrumb->push('Tipos de documentos',
      $this->path('ADM\Parameterization\Cadastral\DocumentTypes')
    );
    $this->breadcrumb->push('Editar',
      $this->path('ADM\Parameterization\Cadastral\DocumentTypes\Edit',
        [ 'documentTypeID' => $documentTypeID ]
      )
    );
    
    // Registra o acesso
    $this->info("Acesso à edição do tipo de documento '{name}'.",
      [ 'name' => $documentType['name'] ]
    );
    
    // Renderiza a página
    return $this->render($request, $response,
      'adm/parameterization/cadastral/documenttypes/documenttype.twig',
      [ 'formMethod' => 'PUT' ]
    );
  }
  
  /**
   * Remove o tipo de documento.
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
    $this->debug("Processando à remoção de tipo de documento.");
    
    // Recupera o ID
    $documentTypeID = $args['documentTypeID'];

    try
    {
      // Recupera as informações do tipo de documento
      $documentType = DocumentType::findOrFail($documentTypeID);
      
      // Agora apaga o tipo de documento
      $documentType->delete();
      
      // Registra o sucesso
      $this->info("O tipo de documento '{name}' foi removido com "
        . "sucesso.",
        [ 'name' => $documentType->name ]
      );
      
      // Informa que a remoção foi realizada com sucesso
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getParams(),
            'message' => "Removido o tipo de documento "
              . "{$documentType->name}",
            'data' => "Delete"
          ])
      ;
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar o tipo de documento "
        . "código {documentTypeID} para remoção.",
        [ 'documentTypeID' => $documentTypeID ]
      );
      
      $message = "Não foi possível localizar o tipo de documento para "
        . "remoção."
      ;
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível remover as informações do tipo "
        . "de documento ID {id}. Erro interno no banco de dados: "
        . "{error}.",
        [ 'id' => $documentTypeID,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível remover o tipo de documento. Erro "
        . "interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível remover as informações do tipo "
        . "de documento ID {id}. Erro interno: {error}.",
        [ 'id' => $documentTypeID,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível remover o tipo de documento. Erro "
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
