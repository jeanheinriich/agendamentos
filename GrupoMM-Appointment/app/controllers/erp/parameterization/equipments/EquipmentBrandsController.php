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
 * O controlador do gerenciamento de marcas de equipamentos.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Controllers\ERP\Parameterization\Equipments;

use App\Models\EquipmentBrand;
use Core\Controllers\Controller;
use Core\Controllers\QueryTrait;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Respect\Validation\Validator as V;
use Slim\Http\Request;
use Slim\Http\Response;

class EquipmentBrandsController
  extends Controller
{
  /**
   * Os métodos para manipular queries.
   */
  use QueryTrait;

  /**
   * Exibe a página inicial do gerenciamento de marcas de equipamentos.
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
    $this->breadcrumb->push('Equipamentos', '');
    $this->breadcrumb->push('Marcas',
      $this->path('ERP\Parameterization\Equipments\Brands')
    );
    
    // Registra o acesso
    $this->info("Acesso ao gerenciamento de marcas de equipamentos.");
    
    // Recupera os dados da sessão
    $equipmentBrand = $this->session->get('equipmentBrand',
      [ 'name' => '' ])
    ;
    
    // Renderiza a página
    return $this->render($request, $response,
      'erp/parameterization/equipments/brands/equipmentbrands.twig',
      [ 'equipmentBrand' => $equipmentBrand ])
    ;
  }
  
  /**
   * Recupera a relação das marcas de equipamentos em formato JSON.
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
    $this->debug("Acesso à relação de marcas de equipamentos.");
    
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
    $this->session->set('equipmentBrand',
      [ 'name' => $name ]
    );
    
    // Corrige o escape dos campos
    $name = addslashes($name);
    
    try
    {
      // Formata o ordenamento dos campos
      $ORDER = $this->formatOrderBy($orderBy, $orderDir);

      // Inicializa a query
      $EquipmentBrandQry = EquipmentBrand::where('contractorid',
        '=', $this->authorization->getContractor()->id
      );
      
      // Acrescenta os filtros
      if (!empty($name)) {
        $EquipmentBrandQry
          ->whereRaw("public.unaccented(equipmentbrands.name) ILIKE "
              . "public.unaccented(E'%{$name}%')"
            )
        ;
      }

      // Conclui nossa consulta
      $equipmentBrands = $EquipmentBrandQry
        ->skip($start)
        ->take($length)
        ->orderByRaw($ORDER)
        ->get([
            'equipmentbrandid AS id',
            'name',
            $this->DB->raw('count(*) OVER() AS fullcount')
          ])
      ;
      
      if (count($equipmentBrands) > 0) {
        $rowCount = $equipmentBrands[0]->fullcount;
        
        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'draw' => $draw,
              'recordsTotal' => $rowCount,
              'recordsFiltered' => $rowCount,
              'data' => $equipmentBrands
            ])
        ;
      } else {
        if (empty($name)) {
          $error = "Não temos marcas de equipamentos cadastradas.";
        } else {
          $error = "Não temos marcas de equipamentos cadastradas cujo "
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
        [ 'module' => 'marcas de equipamentos',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de marcas "
        . "de equipamentos. Erro interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno: {error}.",
        [ 'module' => 'marcas de equipamentos',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de marcas "
        . "de equipamentos. Erro interno."
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
   * Exibe um formulário para adição de uma marca de equipamento, quando
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
    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();

    // Verifica se estamos postando os dados
    if ($request->isPost()) {
      // Os dados estão sendo postados
      
      // Registra o acesso
      $this->debug("Processando à adição de marca de equipamento.");
      
      // Valida os dados
      $this->validator->validate($request, [
        'name' => V::notBlank()
          ->length(2, 30)
          ->setName('Marca de equipamento'),
        'madetracker' => V::boolVal()
          ->setName('Fabrica rastreadores'),
        'madeaccessory' => V::boolVal()
          ->setName('Fabrica acessórios')
      ]);

      if ($this->validator->isValid()) {
        // Grava as informações no banco de dados
        try
        {
          // Recupera os dados da marca de equipamento
          $equipmentBrandData = $this->validator->getValues();
          
          // Primeiro, verifica se não temos uma marca de equipamento com
          // o mesmo nome neste contratante
          if (EquipmentBrand::where("contractorid", '=', $contractor->id)
                ->whereRaw("public.unaccented(name) ILIKE public.unaccented('{$equipmentBrandData['name']}')")
                ->count() === 0) {
            // Grava a nova marca de equipamento
            
            $equipmentBrand = new EquipmentBrand();
            $equipmentBrand->fill($equipmentBrandData);
            // Adiciona o contratante
            $equipmentBrand->contractorid = $contractor->id;
            $equipmentBrand->save();
            $equipmentBrandID = $equipmentBrand->equipmentbrandid;

            // Registra o sucesso
            $this->info("Cadastrado a marca de equipamento '{name}' "
              . "no contratante '{contractor}' com sucesso.",
              [ 'name'  => $equipmentBrandData['name'],
                'contractor' => $contractor->name ]
            );
            
            // Alerta o usuário
            $this->flash("success", "A marca de equipamento <i>'{name}'"
              . "</i> foi cadastrada com sucesso.",
              [ 'name'  => $equipmentBrandData['name'] ]
            );
            
            // Registra o evento
            $this->debug("Redirecionando para {routeName}",
              [ 'routeName' => 'ERP\Parameterization\Equipments\Brands' ]
            );
            
            // Redireciona para a página de gerenciamento de marcas de
            // equipamentos
            return $this->redirect($response,
              'ERP\Parameterization\Equipments\Brands')
            ;
          } else {
            // Registra o erro
            $this->debug("Não foi possível inserir as informações da "
              . "marca de equipamento '{name}' do contratante "
              . "'{contractor}'. Já existe uma marca de equipamento "
              . "com o mesmo nome.",
              [ 'name'  => $equipmentBrandData['name'],
                'contractor' => $contractor->name ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Já existe uma marca de "
              . "equipamento com o nome <i>'{name}'</i>.",
              [ 'name'  => $equipmentBrandData['name'] ]
            );
          }
        }
        catch(QueryException $exception)
        {
          // Registra o erro
          $this->error("Não foi possível inserir as informações da "
            . "marca de equipamento '{name}' no contratante "
            . "'{contractor}'. Erro interno no banco de dados: "
            . "{error}.",
            [ 'name'  => $equipmentBrandData['name'],
              'contractor' => $contractor->name,
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações da marca de equipamento. Erro interno no "
            . "banco de dados."
          );
        }
        catch(Exception $exception)
        {
          // Registra o erro
          $this->error("Não foi possível inserir as informações da "
            . "marca de equipamento '{name}' no contratante "
            . "'{contractor}'. Erro interno: {error}.",
            [ 'name'  => $equipmentBrandData['name'],
              'contractor' => $contractor->name,
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações da marca de equipamento. Erro interno."
          );
        }
      }
    }
    
    // Exibe um formulário para adição de uma marca de equipamento
    
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ERP\Home')
    );
    $this->breadcrumb->push('Parametrização', '');
    $this->breadcrumb->push('Equipamentos', '');
    $this->breadcrumb->push('Marcas',
      $this->path('ERP\Parameterization\Equipments\Brands')
    );
    $this->breadcrumb->push('Adicionar',
      $this->path('ERP\Parameterization\Equipments\Brands\Add')
    );
    
    // Registra o acesso
    $this->info("Acesso à adição de marca de equipamento no "
      . "contratante '{contractor}'.",
      [ 'contractor' => $contractor->name ]
    );
    
    // Renderiza a página
    return $this->render($request, $response,
      'erp/parameterization/equipments/brands/equipmentbrand.twig',
      [ 'formMethod' => 'POST' ])
    ;
  }
  
  /**
   * Exibe um formulário para edição de uma marca de equipamento, quando
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
    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();
    
    try
    {
      // Recupera as informações da marca de equipamento
      $equipmentBrandID = $args['equipmentBrandID'];
      $equipmentBrand = EquipmentBrand::where('contractorid',
            '=', $contractor->id
          )
        ->where('equipmentbrandid', '=', $equipmentBrandID)
        ->firstOrFail()
      ;

      // Verifica se estamos modificando os dados
      if ($request->isPut()) {
        // Os dados estão sendo modificados
        
        // Registra o acesso
        $this->debug("Processando à edição da marca de equipamento "
          . "'{name}' no contratante {contractor}.",
          [ 'name' => $equipmentBrand['name'],
            'contractor' => $contractor->name ]
        );
        
        // Valida os dados
        $this->validator->validate($request, [
          'equipmentbrandid' => V::notBlank()
            ->intVal()
            ->setName('ID da marca'),
          'name' => V::notBlank()
            ->length(2, 30)
            ->setName('Marca de equipamento'),
          'madetracker' => V::boolVal()
            ->setName('Fabrica rastreadores'),
          'madeaccessory' => V::boolVal()
            ->setName('Fabrica acessórios')
        ]);

        if ($this->validator->isValid()) {
          // Grava as informações no banco de dados
          try
          {
            // Recupera os dados modificados da marca de equipamento
            $equipmentBrandData = $this->validator->getValues();
            
            // Primeiro, verifica se não mudamos o nome da marca de
            // equipamento
            $save = false;
            if (strtolower($equipmentBrand['name']) != strtolower($equipmentBrandData['name'])) {
              // Modificamos o nome da marca de equipamento, então verifica
              // se temos uma marca de equipamento com o mesmo nome neste
              // contratante antes de prosseguir
              if (EquipmentBrand::where("contractorid", '=', $contractor->id)
                    ->whereRaw("public.unaccented(name) ILIKE "
                        . "public.unaccented('{$equipmentBrandData['name']}')"
                      )
                    ->count() === 0) {
                $save = true;
              } else {
                // Registra o erro
                $this->debug("Não foi possível modificar as "
                  . "informações da marca de equipamento '{name}' no "
                  . "contratante '{contractor}'. Já existe uma marca "
                  . "de equipamento com o mesmo nome.",
                  [ 'name'  => $equipmentBrandData['name'],
                    'contractor' => $contractor->name ]
                );
                
                // Alerta o usuário
                $this->flashNow("error", "Já existe uma marca de "
                  . "equipamento com o mesmo nome."
                );
              }
            } else {
              $save = true;
            }
            
            if ($save) {
              // Grava as informações da marca de equipamento

              // Gravamos os dados da marca
              $equipmentbrand =
                EquipmentBrand::findOrFail($equipmentBrandID)
              ;
              $equipmentbrand->fill($equipmentBrandData);
              $equipmentbrand->save();
              
              // Registra o sucesso
              $this->info("Modificada a marca de equipamento '{name}' "
                . "no contratante '{contractor}' com sucesso.",
                [ 'name'  => $equipmentBrandData['name'],
                  'contractor' => $contractor->name ]
              );
              
              // Alerta o usuário
              $this->flash("success", "A marca de equipamento "
                . "<i>'{name}'</i> foi modificada com sucesso.",
                [ 'name'  => $equipmentBrandData['name'] ]
              );
              
              // Registra o evento
              $this->debug("Redirecionando para {routeName}",
                [ 'routeName' => 'ERP\Parameterization\Equipments\Brands' ]
              );
              
              // Redireciona para a página de gerenciamento de marcas de
              // equipamentos
              return $this->redirect($response,
                'ERP\Parameterization\Equipments\Brands'
              );
            }
          }
          catch(QueryException $exception)
          {
            // Registra o erro
            $this->error("Não foi possível modificar as informações da "
              . "marca de equipamento '{name}' no contratante "
              . "'{contractor}'. Erro interno no banco de dados: "
              . "{error}",
              [ 'name'  => $equipmentBrandData['name'],
                'contractor' => $contractor->name,
                'error' => $exception->getMessage() ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Não foi possível modificar as "
              . "informações da marca de equipamento. Erro interno no "
              . "banco de dados."
            );
          }
          catch(Exception $exception)
          {
            // Registra o erro
            $this->error("Não foi possível modificar as informações da "
              . "marca de equipamento '{name}' no contratante "
              . "'{contractor}'. Erro interno: {error}",
              [ 'name'  => $equipmentBrandData['name'],
                'contractor' => $contractor->name,
                'error' => $exception->getMessage() ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Não foi possível modificar as "
              . "informações da marca de equipamento. Erro interno."
            );
          }
        }
      } else {
        // Carrega os dados atuais
        $this->validator->setValues($equipmentBrand->toArray());
      }
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar a marca de equipamento "
        . "código {equipmentBrandID}.",
        [ 'equipmentBrandID' => $equipmentBrandID ]
      );
      
      // Alerta o usuário
      $this->flash("error", "Não foi possível localizar esta marca de "
        . "equipamento."
      );
      
      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [ 'routeName' => 'ERP\Parameterization\Equipments\Brands' ]
      );
      
      // Redireciona para a página de gerenciamento de marcas de
      // equipamentos
      return $this->redirect($response,
        'ERP\Parameterization\Equipments\Brands')
      ;
    }
    
    // Exibe um formulário para edição de uma marca de equipamento
    
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ERP\Home')
    );
    $this->breadcrumb->push('Parametrização', '');
    $this->breadcrumb->push('Equipamentos', '');
    $this->breadcrumb->push('Marcas',
      $this->path('ERP\Parameterization\Equipments\Brands')
    );
    $this->breadcrumb->push('Editar',
      $this->path('ERP\Parameterization\Equipments\Brands\Edit', [
        'equipmentBrandID' => $equipmentBrandID
      ])
    );
    
    // Registra o acesso
    $this->info("Acesso à edição da marca de equipamento '{name}' "
      . "do contratante '{contractor}'.",
      [ 'name' => $equipmentBrand['name'],
        'contractor' => $contractor->name ]
    );
    
    // Renderiza a página
    return $this->render($request, $response,
      'erp/parameterization/equipments/brands/equipmentbrand.twig',
      [ 'formMethod' => 'PUT' ])
    ;
  }
  
  /**
   * Remove a marca de equipamento.
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
    $this->debug("Processando à remoção de marca de equipamento.");
    
    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();

    // Recupera o ID
    $equipmentBrandID = $args['equipmentBrandID'];

    try
    {
      // Recupera as informações da marca de equipamento
      $equipmentBrand = EquipmentBrand::findOrFail($equipmentBrandID);
      
      // Inicia a transação
      $this->DB->beginTransaction();
      
      // Agora apaga a marca de equipamento e todos os modelos de
      // equipamentos desta marca
      $equipmentBrand->deleteCascade();
      
      // Efetiva a transação
      $this->DB->commit();
      
      // Registra o sucesso
      $this->info("A marca de equipamento '{name}' do contratante "
        . "'{contractor}' foi removida com sucesso.",
        [ 'name' => $equipmentBrand->name,
          'contractor' => $contractor->name ]
      );
      
      // Informa que a remoção foi realizada com sucesso
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getParams(),
            'message' => "Removida a marca de equipamento "
              . "{$equipmentBrand->name}",
            'data' => "Delete"
          ])
      ;
    }
    catch(ModelNotFoundException $exception)
    {
      // Reverte (desfaz) a transação
      $this->DB->rollBack();

      // Registra o erro
      $this->error("Não foi possível localizar a marca de equipamento "
        . "código {equipmentBrandID} para remoção.",
        [ 'equipmentBrandID' => $equipmentBrandID ]
      );
      
      $message = "Não foi possível localizar a marca de equipamento "
        . "para remoção."
      ;
    }
    catch(QueryException $exception)
    {
      // Reverte (desfaz) a transação
      $this->DB->rollBack();

      // Registra o erro
      $this->error("Não foi possível remover as informações da marca "
        . "de equipamento ID {id} no contratante '{contractor}'. Erro "
        . "interno no banco de dados: {error}.",
        [ 'id'  => $equipmentBrandID,
          'contractor' => $contractor->name,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível remover a marca de equipamento. "
        . "Erro interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Reverte (desfaz) a transação
      $this->DB->rollBack();

      // Registra o erro
      $this->error("Não foi possível remover as informações da marca "
        . "de equipamento ID {id} no contratante '{contractor}'. "
        . "Erro interno: {error}.",
        [ 'id'  => $equipmentBrandID,
          'contractor' => $contractor->name,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível remover a marca de equipamento. "
        . "Erro interno."
      ;
    }
    
    return $response
      ->withHeader('Content-type', 'application/json')
      ->withJson([
          'result' => 'NOK',
          'params' => $request->getParams(),
          'message' => $message,
          'data' => null ]
        )
    ;
  }
  
  /**
   * Recupera a relação das marcas de equipamentos em formato JSON no
   * padrão dos campos de preenchimento automático.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   *
   * @return Response $response
   */
  public function getAutocompletionData(Request $request,
    Response $response)
  {
    $this->debug("Relação de marcas de equipamentos para preenchimento "
      . "automático despachada."
    );

    // --------------------------[ Recupera os dados requisitados ]-----

    // Recupera os dados da requisição
    $postParams = $request->getParsedBody();

    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();
    
    // Lida com as informações provenientes do searchbox
    $name       = addslashes($postParams['searchTerm']);

    // Determina os limites e parâmetros da consulta
    $start      = 0;
    $length     = $postParams['limit'];
    $ORDER      = 'name ASC';
    
    // Registra o acesso
    $this->debug("Acesso aos dados de preenchimento automático das "
      . "marcas de equipamentos que contenham '{name}'",
      [ 'name' => $name ]
    );
    
    try
    {
      // Localiza as marcas de equipamentos na base de dados
      $message = "Marcas de equipamentos cujo nome contém '{$name}'";
      $equipmentBrands =
        EquipmentBrand::whereRaw("public.unaccented(equipmentbrands.name) "
              . "ILIKE public.unaccented('%{$name}%')"
            )
          ->where("equipmentbrands.contractorid", '=', $contractor->id)
          ->skip($start)
          ->take($length)
          ->orderByRaw($ORDER)
          ->get([
              'equipmentbrands.equipmentbrandid AS id',
              'equipmentbrands.name'
            ])
      ;
      
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getQueryParams(),
            'message' => $message,
            'data' => $equipmentBrands
          ])
      ;
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module} para preenchimento automático. Erro interno no "
        . "banco de dados: {error}.",
        [ 'module' => 'marcas de equipamentos',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de marcas de "
        . "equipamentos para preenchimento automático. Erro interno no "
        . "banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module} para preenchimento automático. Erro interno: "
        . "{error}.",
        [ 'module' => 'marcas de equipamentos',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de marcas de "
        . "equipamentos para preenchimento automático. Erro interno."
      ;
    }
    
    // Retorna o erro
    return $response
      ->withHeader('Content-type', 'application/json')
      ->withJson([
          'result' => 'NOK',
          'params' => $request->getQueryParams(),
          'message' => "Não foi possível localizar marcas de "
            . "equipamentos cujo nome contém '$name'",
          'data' => [ ],
        ])
    ;
  }
}
