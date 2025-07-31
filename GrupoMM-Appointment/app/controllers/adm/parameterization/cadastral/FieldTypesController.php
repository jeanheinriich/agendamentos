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
 * O controlador do gerenciamento de tipos de campos de entrada
 * disponíveis para os formulários do atendimento.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Controllers\ADM\Parameterization\Cadastral;

use App\Models\FieldType;
use App\Models\ValueType;
use Core\Controllers\Controller;
use Core\Controllers\QueryTrait;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Respect\Validation\Validator as V;
use Slim\Http\Request;
use Slim\Http\Response;

class FieldTypesController
  extends Controller
{
  /**
   * Os métodos para manipular queries.
   */
  use QueryTrait;

  /**
   * Exibe a página inicial do gerenciamento de tipos de campos de
   * entrada para formulários.
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
    $this->breadcrumb->push('Tipos de campos',
      $this->path('ADM\Parameterization\Cadastral\FieldTypes')
    );
    
    // Registra o acesso
    $this->info("Acesso ao gerenciamento de tipos de campos de "
      . "formulário."
    );
    
    // Recupera os dados da sessão
    $fieldType = $this->session->get('fieldtype',
      [ 'name' => '' ]
    );
    
    // Renderiza a página
    return $this->render($request, $response,
      'adm/parameterization/cadastral/fieldtypes/fieldtypes.twig',
      [ 'fieldtype' => $fieldType ])
    ;
  }
  
  /**
   * Recupera a relação dos tipos de campos de formulário em formato
   * JSON.
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
    $this->debug("Acesso à relação de tipos de campos de formulário.");
    
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
    $this->session->set('fieldtype',
      [ 'name' => $name ]
    );

    // Corrige o escape dos campos
    $name = addslashes($name);
    
    try
    {
      // Formata o ordenamento dos campos
      $ORDER = $this->formatOrderBy($orderBy, $orderDir);
      
      // Realiza a consulta
      $FieldTypeQry = FieldType::join('valuetypes',
            'fieldtypes.valuetypeid', '=', 'valuetypes.valuetypeid'
          )
        ->whereRaw("1=1")
      ;
      
      // Acrescenta os filtros
      if (!empty($name)) {
        $FieldTypeQry
          ->whereRaw("public.unaccented(fieldtypes.name) ILIKE "
             . "public.unaccented('%{$name}%')"
            )
        ;
      }

      // Conclui nossa consulta
      $fieldTypes = $FieldTypeQry
        ->skip($start)
        ->take($length)
        ->orderByRaw($ORDER)
        ->get([
            'fieldtypes.fieldtypeid AS id',
            'fieldtypes.name',
            'fieldtypes.comments',
            'fieldtypes.fieldclass',
            'fieldtypes.valuetypeid',
            'valuetypes.name AS valuetypename',
            'fieldtypes.fixedsize',
            'valuetypes.fractional AS hasDecimalPlace',
            'fieldtypes.decimalplaces',
            $this->DB->raw('count(*) OVER() AS fullcount')
          ])
      ;
      
      if (count($fieldTypes) > 0) {
        $rowCount = $fieldTypes[0]->fullcount;
        
        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'draw' => $draw,
              'recordsTotal' => $rowCount,
              'recordsFiltered' => $rowCount,
              'data' => $fieldTypes
            ])
        ;
      } else {
        if (empty($name)) {
          $error = "Não temos tipos de campos de formulário "
            . "cadastrados."
          ;
        } else {
          $error = "Não temos tipos de campos de formulário "
            . "cadastrados cujo nome contém <i>{$name}</i>."
          ;
        }
      }
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno no banco de dados: {error}.",
        [ 'module' => 'tipos de campos de formulário',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de tipos de "
        . "campos de formulário. Erro interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno: {error}.",
        [ 'module' => 'tipos de campos de formulário',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de tipos de "
        . "campos de formulário. Erro interno."
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
   * Exibe um formulário para adição de um tipo de campo de formulário,
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
    // Recupera as informações de tipos de valores
    $valueTypes = ValueType::orderBy('name')
      ->get([
          'valuetypeid AS id',
          'name',
          'fractional'
        ])
    ;

    // Verifica se estamos postando os dados
    if ($request->isPost()) {
      // Os dados estão sendo postados
      
      // Registra o acesso
      $this->debug("Processando à adição de tipo de campo de "
        . "formulário."
      );

      // Valida os dados
      $this->validator->validate($request, [
        'name' => V::notBlank()
          ->length(2, 30)
          ->setName('Tipo de campo'),
        'comments' => V::notBlank()
          ->length(2, 100)
          ->setName('Descrição'),
        'fieldclass' => V::notBlank()
          ->length(2, 20)
          ->setName('Classe'),
        'valuetypeid' => V::notBlank()
          ->intVal()
          ->setName('Tipo de valor'),
        'fixedsize' => V::intVal()
          ->setName('Tamanho'),
        'decimalplaces' => V::intVal()
          ->setName('Casas decimais'),
        'mask' => V::optional(
              V::notBlank()
              ->length(2, 100)
            )
          ->setName('Máscara')
      ]);

      if ($this->validator->isValid()) {
        // Grava as informações no banco de dados
        try
        {
          // Recupera os dados do tipo de campo de formulário
          $fieldTypeData = $this->validator->getValues();
          
          // Primeiro, verifica se não temos um tipo de campo com o
          // mesmo nome
          if (FieldType::whereRaw("public.unaccented(name) ILIKE "
                    . "public.unaccented('{$fieldTypeData['name']}')"
                  )
                ->count() === 0) {
            // Grava o novo tipo de campo de formulário
            $fieldType = new FieldType();
            $fieldType->fill($fieldTypeData);
            $fieldType->save();
            
            // Registra o sucesso
            $this->info("Cadastrado o tipo de campo de formulário "
              . "'{name}' com sucesso.",
              [ 'name'  => $fieldTypeData['name'] ]
            );
            
            // Alerta o usuário
            $this->flash("success", "O tipo de campo de formulário "
              . "<i>'{name}'</i> foi cadastrado com sucesso.",
              [ 'name'  => $fieldTypeData['name'] ]
            );
            
            // Registra o evento
            $this->debug("Redirecionando para {routeName}",
              [ 'routeName' => 'ADM\Parameterization\Cadastral\FieldTypes' ]
            );
            
            // Redireciona para a página de gerenciamento de tipos de
            // campos de formulário
            return $this->redirect($response,
              'ADM\Parameterization\Cadastral\FieldTypes')
            ;
          } else {
            // Registra o erro
            $this->debug("Não foi possível inserir as informações do "
              . "tipo de campo de formulário '{name}'. Já existe um "
              . "tipo de campo com o mesmo nome.",
              [ 'name'  => $fieldTypeData['name'] ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Já existe um tipo de campo com o "
              . "nome <i>'{name}'</i>.",
              [ 'name'  => $fieldTypeData['name'] ]
            );
          }
        }
        catch(QueryException $exception)
        {
          // Registra o erro
          $this->error("Não foi possível inserir as informações do "
            . "tipo de campo de formulário '{name}'. Erro interno no "
            . "banco de dados: {error}.",
            [ 'name'  => $fieldTypeData['name'],
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações do tipo de campo. Erro interno no banco de "
            . "dados."
          );
        }
        catch(Exception $exception)
        {
          // Registra o erro
          $this->error("Não foi possível inserir as informações do "
            . "tipo de campo de formulário '{name}'. Erro interno: "
            . "{error}.",
            [ 'name'  => $fieldTypeData['name'],
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações do tipo de campo. Erro interno."
          );
        }
      }
    } else {
      // Carrega um conjunto de valores vazio
      $emptyFieldType = [
        'name' => '',
        'comments' => '',
        'valuetypeid' => 1,
        'fixedsize' => 0,
        'hasdecimalplaces' => false,
        'decimalplaces' => 0,
        'mask' => ''
      ];
      $this->validator->setValues($emptyFieldType);
    }
    
    // Exibe um formulário para adição de um tipo de campo
    
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ADM\Home')
    );
    $this->breadcrumb->push('Parametrização', '');
    $this->breadcrumb->push('Dados Cadastrais', '');
    $this->breadcrumb->push('Tipos de campos',
      $this->path('ADM\Parameterization\Cadastral\FieldTypes')
    );
    $this->breadcrumb->push('Adicionar',
      $this->path('ADM\Parameterization\Cadastral\FieldTypes\Add')
    );
    
    // Registra o acesso
    $this->info("Acesso à adição de tipo de campo de formulário.");
    
    // Renderiza a página
    return $this->render($request, $response,
      'adm/parameterization/cadastral/fieldtypes/fieldtype.twig',
      [ 'formMethod' => 'POST',
        'valueTypes' => $valueTypes ])
    ;
  }
  
  /**
   * Exibe um formulário para edição de um tipo de campo de formulário,
   * quando solicitado, e confirma os dados enviados.
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
    // Recupera as informações de tipos de valores
    $valueTypes = ValueType::orderBy('name')
      ->get([
          'valuetypeid AS id',
          'name',
          'fractional'
        ])
    ;

    try
    {
      // Recupera as informações do tipo de campo
      $fieldTypeID = $args['fieldTypeID'];
      $fieldType = FieldType::findOrFail($fieldTypeID);
      
      // Verifica se estamos modificando os dados
      if ($request->isPut()) {
        // Os dados estão sendo modificados
        
        // Registra o acesso
        $this->debug("Processando à edição do tipo de campo de "
          . "formulário '{name}'.",
          [ 'name' => $fieldType['name'] ]
        );
        
        // Valida os dados
        $this->validator->validate($request, [
          'fieldtypeid' => V::notBlank()
            ->intVal()
            ->setName('ID do tipo de campo'),
          'name' => V::notBlank()
            ->length(2, 30)
            ->setName('Tipo de campo'),
          'comments' => V::notBlank()
            ->length(2, 100)
            ->setName('Descrição'),
          'fieldclass' => V::notBlank()
            ->length(2, 20)
            ->setName('Classe'),
          'valuetypeid' => V::notBlank()
            ->intVal()
            ->setName('Tipo de valor'),
          'fixedsize' => V::intVal()
            ->setName('Tamanho'),
          'decimalplaces' => V::intVal()
            ->setName('Casas decimais'),
          'mask' => V::optional(
                V::notBlank()
                ->length(2, 100)
              )
            ->setName('Máscara')
        ]);

        if ($this->validator->isValid()) {
          // Grava as informações no banco de dados
          try
          {
            // Recupera os dados modificados do tipo de campo de
            // formulário
            $fieldTypeData = $this->validator->getValues();
            
            // Primeiro, verifica se não mudamos o nome do tipo de campo
            // de formulário
            $save = false;
            if ($fieldType->name != $fieldTypeData['name']) {
              // Modificamos o nome do tipo de campo de formulário,
              // então verifica se temos um tipo de campo com o mesmo
              // nome antes de prosseguir
              if (FieldType::whereRaw("public.unaccented(name) ILIKE "
                        . "public.unaccented('{$fieldTypeData['name']}')"
                      )
                    ->count() === 0) {
                $save = true;
              } else {
                // Registra o erro
                $this->debug("Não foi possível modificar as "
                  . "informações do tipo de campo de formulário "
                  . "'{name}'. Já existe um tipo de campo com o mesmo "
                  . "nome.",
                  [ 'name'  => $fieldTypeData['name'] ]
                );
                
                // Alerta o usuário
                $this->flashNow("error", "Já existe um tipo de campo "
                  . "com o nome <i>'{name}'</i>.",
                  [ 'name'  => $fieldTypeData['name'] ]
                );
              }
            } else {
              $save = true;
            }
            
            if ($save) {
              // Grava as informações do tipo de campo
              $fieldType->fill($fieldTypeData);
              $fieldType->save();
              
              // Registra o sucesso
              $this->info("Modificado o tipo de campo de formulário "
                . "'{name}' com sucesso.",
                [ 'name'  => $fieldTypeData['name'] ]
              );
              
              // Alerta o usuário
              $this->flash("success", "O tipo de campo <i>'{name}'</i> "
                . "foi modificado com sucesso.",
                [ 'name'  => $fieldTypeData['name'] ]
              );
              
              // Registra o evento
              $this->debug("Redirecionando para {routeName}",
                [ 'routeName' => 'ADM\Parameterization\Cadastral\FieldTypes' ]
              );
              
              // Redireciona para a página de gerenciamento de tipos de
              // campos de formulário
              return $this->redirect($response,
                'ADM\Parameterization\Cadastral\FieldTypes')
              ;
            }
          }
          catch(QueryException $exception)
          {
            // Registra o erro
            $this->error("Não foi possível modificar as informações "
              . "do tipo de campo de formulário '{name}'. Erro interno "
              . "no banco de dados: {error}.",
              [ 'name'  => $fieldTypeData['name'],
                'error' => $exception->getMessage() ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Não foi possível modificar as "
              . "informações do tipo de campo. Erro interno no banco "
              . "de dados."
            );
          }
          catch(Exception $exception)
          {
            // Registra o erro
            $this->error("Não foi possível modificar as informações "
              . "do tipo de campo de formulário '{name}'. Erro "
              . "interno: {error}.",
              [ 'name'  => $fieldTypeData['name'],
                'error' => $exception->getMessage() ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Não foi possível modificar as "
              . "informações do tipo de campo. Erro interno."
            );
          }
        }
      } else {
        // Carrega os dados atuais
        $this->validator->setValues($fieldType->toArray());
      }
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar o tipo de campo de "
        . "formulário código {fieldTypeID}.",
        [ 'fieldTypeID' => $fieldTypeID ]
      );
      
      // Alerta o usuário
      $this->flash("error", "Não foi possível localizar este tipo de "
        . "campo."
      );
      
      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [ 'routeName' => 'ADM\Parameterization\Cadastral\FieldTypes' ]
      );
      
      // Redireciona para a página de gerenciamento de tipos de campos
      // de formulário
      return $this->redirect($response,
        'ADM\Parameterization\Cadastral\FieldTypes')
      ;
    }
    
    // Exibe um formulário para edição de um tipo de campo
    
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ADM\Home')
    );
    $this->breadcrumb->push('Parametrização', '');
    $this->breadcrumb->push('Dados Cadastrais', '');
    $this->breadcrumb->push('Tipos de campos',
      $this->path('ADM\Parameterization\Cadastral\FieldTypes')
    );
    $this->breadcrumb->push('Editar',
      $this->path('ADM\Parameterization\Cadastral\FieldTypes\Edit', [
        'fieldTypeID' => $fieldTypeID
      ])
    );
    
    // Registra o acesso
    $this->info("Acesso à edição do tipo de campo de formulário "
      . "'{name}'.",
      [ 'name' => $fieldType['name'] ]
    );
    
    // Renderiza a página
    return $this->render($request, $response,
      'adm/parameterization/cadastral/fieldtypes/fieldtype.twig',
      [ 'formMethod' => 'PUT',
        'valueTypes' => $valueTypes ]
    );
  }
  
  /**
   * Remove o tipo de campo de formulário.
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
    $this->debug("Processando à remoção de tipo de campo de "
      . "formulário."
    );
    
    // Recupera o ID
    $fieldTypeID = $args['fieldTypeID'];

    try
    {
      // Recupera as informações do tipo de campo de formulário
      $fieldType = FieldType::findOrFail($fieldTypeID);
      
      // Agora apaga o tipo de campo
      $fieldType->delete();
      
      // Registra o sucesso
      $this->info("O tipo de campo de formulário '{name}' foi removido "
        . "com sucesso.",
        [ 'name' => $fieldType->name ]
      );
      
      // Informa que a remoção foi realizada com sucesso
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getParams(),
            'message' => "Removido o tipo de campo "
              . "{$fieldType->name}",
            'data' => "Delete" ]
          )
      ;
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar o tipo de campo de "
        . "formulário código {fieldTypeID} para remoção.",
        [ 'fieldTypeID' => $fieldTypeID ]
      );
      
      $message = "Não foi possível localizar o tipo de campo para "
        . "remoção."
      ;
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível remover as informações do tipo "
        . "de campo de formulário ID {id}. Erro interno no banco de "
        . "dados: {error}.",
        [ 'id' => $fieldTypeID,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível remover o tipo de campo. Erro "
        . "interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível remover as informações do tipo "
        . "de campo de formulário ID {id}. Erro interno: {error}.",
        [ 'id' => $fieldTypeID,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível remover o tipo de campo. Erro "
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
