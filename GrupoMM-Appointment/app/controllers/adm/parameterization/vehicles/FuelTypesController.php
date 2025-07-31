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
 * O controlador do gerenciamento de tipos de combustíveis utilizados em
 * veículos.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Controllers\ADM\Parameterization\Vehicles;

use App\Models\FuelType;
use Core\Controllers\Controller;
use Core\Controllers\QueryTrait;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Respect\Validation\Validator as V;
use Slim\Http\Request;
use Slim\Http\Response;

class FuelTypesController
  extends Controller
{
  /**
   * Os métodos para manipular queries.
   */
  use QueryTrait;

  /**
   * Exibe a página inicial do gerenciamento de tipos de combustíveis.
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
    $this->breadcrumb->push('Veículos', '');
    $this->breadcrumb->push('Combustíveis',
      $this->path('ADM\Parameterization\Vehicles\Fuels')
    );
    
    // Registra o acesso
    $this->info("Acesso ao gerenciamento de tipos de combustíveis.");
    
    // Recupera os dados da sessão
    $fuelType = $this->session->get('fueltype',
      [ 'name' => '' ]
    );
    
    // Renderiza a página
    return $this->render($request, $response,
      'adm/parameterization/vehicles/fuels/fueltypes.twig',
      [ 'fueltype' => $fuelType ])
    ;
  }
  
  /**
   * Recupera a relação dos tipos de combustíveis em formato JSON.
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
    $this->debug("Acesso à relação de tipos de combustíveis.");
    
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
    $this->session->set('fueltype',
      [ 'name' => $name ]
    );

    // Corrige o escape dos campos
    $name = addslashes($name);
    
    try
    {
      // Formata o ordenamento dos campos
      $ORDER = $this->formatOrderBy($orderBy, $orderDir);

      // Inicializa a query
      $FuelTypeQry = FuelType::whereRaw("1=1");
      
      // Acrescenta os filtros
      if (!empty($name)) {
        $FuelTypeQry
          ->whereRaw("public.unaccented(name) ILIKE "
              . "public.unaccented('%{$name}%')"
            )
        ;
      }

      // Conclui nossa consulta
      $fuelTypes = $FuelTypeQry
        ->skip($start)
        ->take($length)
        ->orderByRaw($ORDER)
        ->get([
            'fueltype AS id',
            'name',
            $this->DB->raw('count(*) OVER() AS fullcount')
          ])
      ;
      
      if (count($fuelTypes) > 0) {
        $rowCount = $fuelTypes[0]->fullcount;
        
        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'draw' => $draw,
              'recordsTotal' => $rowCount,
              'recordsFiltered' => $rowCount,
              'data' => $fuelTypes
            ])
        ;
      } else {
        if (empty($name)) {
          $error = "Não temos tipos de combustíveis cadastrados.";
        } else {
          $error = "Não temos tipos de combustíveis cadastrados cujo "
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
        [ 'module' => 'tipos de combustíveis',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de tipos de "
        . "combustíveis. Erro interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno: {error}.",
        [ 'module' => 'tipos de combustíveis',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de tipos de "
        . "combustíveis. Erro interno."
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
   * Exibe um formulário para adição de um tipo de combustível, quando
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
      $this->debug("Processando à adição de tipo de combustível.");
      
      // Valida os dados
      $this->validator->validate($request, [
        'fueltype' => V::notBlank()
          ->length(1, 1)
          ->setName('Sigla'),
        'name' => V::notBlank()
          ->length(2, 30)
          ->setName('Tipo de combustível')
      ]);

      if ($this->validator->isValid()) {
        // Grava as informações no banco de dados
        try
        {
          // Recupera os dados do tipo de combustível
          $fuelTypeData = $this->validator->getValues();

          // Força para maiúsculas a sigla
          $fuelTypeData['fueltype'] =
            strtoupper($fuelTypeData['fueltype'])
          ;
          
          // Primeiro, verifica se não temos um tipo de combustível com
          // a mesma sigla
          if (FuelType::where("fueltype", $fuelTypeData['fueltype'])
                ->count() === 0) {
            // Verifica se não temos um tipo de combustível com a
            // mesma sigla
            if (FuelType::whereRaw("public.unaccented(name) ILIKE "
                      . "public.unaccented('{$fuelTypeData['name']}')"
                    )
                  ->count() === 0) {
              // Grava o novo tipo de combustível
              $fuelType = new FuelType();
              $fuelType->fill($fuelTypeData);
              $fuelType->save();
              
              // Registra o sucesso
              $this->info("Cadastrado o tipo de combustível '{name}' "
                . "com sucesso.",
                [ 'name'  => $fuelTypeData['name'] ]
              );
              
              // Alerta o usuário
              $this->flash("success", "O tipo de combustível "
                . "<i>'{name}'</i> foi cadastrado com sucesso.",
                [ 'name'  => $fuelTypeData['name'] ]
              );
              
              // Registra o evento
              $this->debug("Redirecionando para {routeName}",
                [ 'routeName' => 'ADM\Parameterization\Vehicles\Fuels' ]
              );
              
              // Redireciona para a página de gerenciamento de tipos de
              // combustíveis
              return $this->redirect($response,
                'ADM\Parameterization\Vehicles\Fuels')
              ;
            } else {
              // Registra o erro
              $this->debug("Não foi possível inserir as informações do "
                . "tipo de combustível '{name}'. Já existe um tipo de "
                . "combustível com o mesmo nome.",
                [ 'name'  => $fuelTypeData['name'] ]
              );
              
              // Alerta o usuário
              $this->flashNow("error", "Já existe um tipo de "
                . "combustível com o nome <i>'{name}'</i>.",
                [ 'name'  => $fuelTypeData['name'] ]
              );
            }
          } else {
            // Registra o erro
            $this->debug("Não foi possível inserir as informações do "
              . "tipo de combustível '{name}'. Já existe um tipo de "
              . "combustível com a mesma sigla.",
              [ 'name'  => $fuelTypeData['name'] ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Já existe um tipo de combustível "
              . " com a sigla <i>'{sigla}'</i>.",
              [ 'name'  => $fuelTypeData['name'],
                'sigla' => $fuelTypeData['fueltype'] ]
            );
          }
        }
        catch(QueryException $exception)
        {
          // Registra o erro
          $this->error("Não foi possível inserir as informações do "
            . "tipo de combustível '{name}'. Erro interno no banco de "
            . "dados: {error}.",
            [ 'name'  => $fuelTypeData['name'],
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações do tipo de combustível. Erro interno no "
            . "banco de dados."
          );
        }
        catch(Exception $exception)
        {
          // Registra o erro
          $this->error("Não foi possível inserir as informações do "
            . "tipo de combustível '{name}'. Erro interno: {error}.",
            [ 'name'  => $fuelTypeData['name'],
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações do tipo de combustível. Erro interno."
          );
        }
      }
    }
    
    // Exibe um formulário para adição de um tipo de combustível
    
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ADM\Home')
    );
    $this->breadcrumb->push('Parametrização', '');
    $this->breadcrumb->push('Veículos', '');
    $this->breadcrumb->push('Combustíveis',
      $this->path('ADM\Parameterization\Vehicles\Fuels')
    );
    $this->breadcrumb->push('Adicionar',
      $this->path('ADM\Parameterization\Vehicles\Fuels\Add')
    );
    
    // Registra o acesso
    $this->info("Acesso à adição de tipo de combustível.");
    
    // Renderiza a página
    return $this->render($request, $response,
      'adm/parameterization/vehicles/fuels/fueltype.twig',
      [ 'formMethod' => 'POST' ]
    );
  }
  
  /**
   * Exibe um formulário para edição de um tipo de combustível, quando
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
      // Recupera as informações do tipo de combustível
      $fuelTypeID = $args['fuelType'];
      $fuelType = FuelType::findOrFail($fuelTypeID);
      
      // Verifica se estamos modificando os dados
      if ($request->isPut()) {
        // Os dados estão sendo modificados
        
        // Registra o acesso
        $this->debug("Processando à edição do tipo de combustível "
          . "'{name}'.",
          [ 'name' => $fuelType['name'] ]
        );
        
        // Valida os dados
        $this->validator->validate($request, [
          'fueltype' => V::notBlank()
            ->length(1, 1)
            ->setName('Sigla'),
          'name' => V::notBlank()
            ->length(2, 30)
            ->setName('Tipo de combustível')
        ]);

        if ($this->validator->isValid()) {
          // Grava as informações no banco de dados
          try
          {
            // Recupera os dados modificados do tipo de combustível
            $fuelTypeData = $this->validator->getValues();

            // Força para maiúsculas a sigla
            $fuelTypeData['fueltype'] =
              strtoupper($fuelTypeData['fueltype'])
            ;
            
            // Primeiro, verifica se não mudamos a sigla do tipo de
            // combustível
            $save = false;
            if ($fuelType->fueltype != $fuelTypeData['fueltype']) {
              // Modificamos a sigla do tipo de combustível, então
              // verifica se temos um tipo de combustível com a mesma
              // sigla antes de prosseguir
              if (FuelType::where("fueltype", $fuelTypeData['fueltype'])
                    ->count() === 0) {
                $save = true;
              } else {
                // Registra o erro
                $this->debug("Não foi possível modificar as "
                  . "informações do tipo de combustível '{name}'. Já "
                  . "existe um tipo de combustível com a mesma sigla.",
                  [ 'name'  => $fuelTypeData['name'] ]
                );
                
                // Alerta o usuário
                $this->flashNow("error", "Já existe um tipo de "
                  . "combustível com a sigla <i>'{name}'</i>.",
                  [ 'name'  => $fuelTypeData['fueltype'] ]
                );
              }
            } else {
              $save = true;
            }
            
            if ($save) {
              $save = false;

              if ($fuelType->name != $fuelTypeData['name']) {
                // Modificamos o nome do tipo de combustível, então
                // verifica se temos um tipo de combustível com o mesmo
                // nome antes de prosseguir
                if (FuelType::whereRaw("public.unaccented(name) ILIKE "
                          . "public.unaccented('{$fuelTypeData['name']}')"
                        )
                      ->count() === 0) {
                  $save = true;
                } else {
                  // Registra o erro
                  $this->debug("Não foi possível modificar as "
                    . "informações do tipo de combustível '{name}'. Já "
                    . "existe um tipo de combustível com o mesmo nome.",
                    [ 'name'  => $fuelTypeData['name'] ]
                  );
                  
                  // Alerta o usuário
                  $this->flashNow("error", "Já existe um tipo de "
                    . "combustível com o nome <i>'{name}'</i>.",
                    [ 'name'  => $fuelTypeData['name'] ]
                  );
                }
              } else {
                $save = true;
              }
            }
              
            if ($save) {
              // Grava as informações do tipo de combustível
              $fuelType->fill($fuelTypeData);
              $fuelType->save();
              
              // Registra o sucesso
              $this->info("Modificado o tipo de combustível '{name}' "
                . "com sucesso.",
                [ 'name'  => $fuelTypeData['name'] ]
              );
              
              // Alerta o usuário
              $this->flash("success", "O tipo de combustível "
                . "<i>'{name}'</i> foi modificado com sucesso.",
                [ 'name'  => $fuelTypeData['name'] ]
              );
              
              // Registra o evento
              $this->debug("Redirecionando para {routeName}",
                [ 'routeName' => 'ADM\Parameterization\Vehicles\Fuels' ]
              );
              
              // Redireciona para a página de gerenciamento de tipos de
              // combustíveis
              return $this->redirect($response,
                'ADM\Parameterization\Vehicles\Fuels')
              ;
            }
          }
          catch(QueryException $exception)
          {
            // Registra o erro
            $this->error("Não foi possível modificar as informações "
              . "do tipo de combustível '{name}'. Erro interno no "
              . "banco de dados: {error}.",
              [ 'name'  => $fuelTypeData['name'],
                'error' => $exception->getMessage() ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Não foi possível modificar as "
              . "informações do tipo de combustível. Erro interno no "
              . "banco de dados."
            );
          }
          catch(Exception $exception)
          {
            // Registra o erro
            $this->error("Não foi possível modificar as informações "
              . "do tipo de combustível '{name}'. Erro interno: "
              . "{error}.",
              [ 'name'  => $fuelTypeData['name'],
                'error' => $exception->getMessage() ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Não foi possível modificar as "
              . "informações do tipo de combustível. Erro interno."
            );
          }
        }
      } else {
        // Carrega os dados atuais
        $this->validator->setValues($fuelType->toArray());
      }
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar o tipo de combustível "
        . "código {fuelTypeID}.",
        [ 'fuelTypeID' => $fuelTypeID ]
      );
      
      // Alerta o usuário
      $this->flash("error", "Não foi possível localizar este tipo de "
        . "combustível."
      );
      
      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [ 'routeName' => 'ADM\Parameterization\Vehicles\Fuels' ]
      );
      
      // Redireciona para a página de gerenciamento de tipos de combustíveis
      return $this->redirect($response,
        'ADM\Parameterization\Vehicles\Fuels')
      ;
    }
    
    // Exibe um formulário para edição de um tipo de combustível
    
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ADM\Home')
    );
    $this->breadcrumb->push('Parametrização', '');
    $this->breadcrumb->push('Veículos', '');
    $this->breadcrumb->push('Combustíveis',
      $this->path('ADM\Parameterization\Vehicles\Fuels')
    );
    $this->breadcrumb->push('Editar',
      $this->path('ADM\Parameterization\Vehicles\Fuels\Edit', [
        'fuelType' => $fuelTypeID
      ])
    );
    
    // Registra o acesso
    $this->info("Acesso à edição do tipo de combustível '{name}'.",
      [ 'name' => $fuelType['name'] ]
    );
    
    // Renderiza a página
    return $this->render($request, $response,
      'adm/parameterization/vehicles/fuels/fueltype.twig',
      [ 'formMethod' => 'PUT' ])
    ;
  }
  
  /**
   * Remove o tipo de combustível.
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
    $this->debug("Processando à remoção de tipo de combustível.");
    
    // Recupera o ID
    $fuelTypeID = $args['fuelType'];

    try
    {
      // Recupera as informações do tipo de combustível
      $fuelType = FuelType::findOrFail($fuelTypeID);
      
      // Agora apaga o tipo de combustível
      $fuelType->delete();
      
      // Registra o sucesso
      $this->info("O tipo de combustível '{name}' foi removido com "
        . "sucesso.",
        [ 'name' => $fuelType->name ]
      );
      
      // Informa que a remoção foi realizada com sucesso
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getParams(),
            'message' => "Removido o tipo de combustível "
              . "{$fuelType->name}",
            'data' => "Delete" ]
          );
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar o tipo de combustível "
        . "código {fuelTypeID} para remoção.",
        [ 'fuelTypeID' => $fuelTypeID ]
      );
      
      $message = "Não foi possível localizar o tipo de combustível "
        . "para remoção."
      ;
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível remover as informações do tipo "
        . "de combustível ID {id}. Erro interno no banco de dados: "
        . "{error}.",
        [ 'id' => $fuelTypeID,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível remover o tipo de combustível. Erro "
        . "interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível remover as informações do tipo "
        . "de combustível ID {id}. Erro interno: {error}.",
        [ 'id' => $fuelTypeID,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível remover o tipo de combustível. Erro "
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
