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
 * O controlador do gerenciamento de cores de veículos.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Controllers\ADM\Parameterization\Vehicles;

use App\Models\VehicleColor;
use Core\Controllers\Controller;
use Core\Controllers\QueryTrait;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Respect\Validation\Validator as V;
use Slim\Http\Request;
use Slim\Http\Response;

class VehicleColorsController
  extends Controller
{
  /**
   * Os métodos para manipular queries.
   */
  use QueryTrait;

  /**
   * Exibe a página inicial do gerenciamento de cores de veículos.
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
    $this->breadcrumb->push('Cores',
      $this->path('ADM\Parameterization\Vehicles\Colors')
    );
    
    // Registra o acesso
    $this->info("Acesso ao gerenciamento de cores de veículos.");
    
    // Recupera os dados da sessão
    $vehicleColor = $this->session->get('vehiclecolor',
      [ 'name' => '' ]
    );
    
    // Renderiza a página
    return $this->render($request, $response,
      'adm/parameterization/vehicles/colors/vehiclecolors.twig',
      [ 'vehiclecolor' => $vehicleColor ])
    ;
  }
  
  /**
   * Recupera a relação dos cores de veículos em formato JSON.
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
    $this->debug("Acesso à relação de cores de veículos.");
    
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
    $this->session->set('vehiclecolor',
      [ 'name' => $name ]
    );

    // Corrige o escape dos campos
    $name = addslashes($name);
    
    try
    {
      // Formata o ordenamento dos campos
      $ORDER = $this->formatOrderBy($orderBy, $orderDir);

      // Inicializa a query
      $VehicleColorQry = VehicleColor::whereRaw("1=1");
      
      // Acrescenta os filtros
      if (!empty($name)) {
        $VehicleColorQry
          ->whereRaw("public.unaccented(name) ILIKE "
              . "public.unaccented('%{$name}%')"
            )
        ;
      }

      // Conclui nossa consulta
      $vehicleColors = $VehicleColorQry
        ->skip($start)
        ->take($length)
        ->orderByRaw($ORDER)
        ->get([
            'vehiclecolorid AS id',
            'name',
            'color',
            $this->DB->raw('count(*) OVER() AS fullcount')
          ])
      ;
      
      if (count($vehicleColors) > 0) {
        $rowCount = $vehicleColors[0]->fullcount;
        
        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'draw' => $draw,
              'recordsTotal' => $rowCount,
              'recordsFiltered' => $rowCount,
              'data' => $vehicleColors
            ])
        ;
      } else {
        if (empty($name)) {
          $error = "Não temos cores de veículos cadastradas.";
        } else {
          $error = "Não temos cores de veículos cadastradas cujo "
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
        [ 'module' => 'cores de veículos',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de cores de "
        . "veículos. Erro interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno: {error}.",
        [ 'module' => 'cores de veículos',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de cores de "
        . "veículos. Erro interno."
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
   * Exibe um formulário para adição de uma cor de veículo, quando
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
      $this->debug("Processando à adição de cor de veículo.");
      
      // Valida os dados
      $this->validator->validate($request, [
        'name' => V::notBlank()
          ->length(2, 30)
          ->setName('Nome da cor'),
        'color' => V::optional(
            V::notEmpty()
              ->length(2, 30)
          )->setName('Cor no CSS')
      ]);

      if ($this->validator->isValid()) {
        // Grava as informações no banco de dados
        try
        {
          // Recupera os dados da cor de veículo
          $vehicleColorData = $this->validator->getValues();
          
          // Primeiro, verifica se não temos uma cor de veículo com o
          // mesmo nome
          if (VehicleColor::whereRaw("public.unaccented(name) ILIKE "
                    . "public.unaccented('{$vehicleColorData['name']}')"
                  )
                ->count() === 0) {
            // Grava o nova cor de veículo
            $vehicleColor = new VehicleColor();
            $vehicleColor->fill($vehicleColorData);
            $vehicleColor->save();
            
            // Registra o sucesso
            $this->info("Cadastrada a cor de veículo '{name}' com "
              . "sucesso.",
              [ 'name'  => $vehicleColorData['name'] ]
            );
            
            // Alerta o usuário
            $this->flash("success", "A cor de veículo <i>'{name}'</i> "
              . "foi cadastrada com sucesso.",
              [ 'name'  => $vehicleColorData['name'] ]
            );
            
            // Registra o evento
            $this->debug("Redirecionando para {routeName}",
              [ 'routeName' => 'ADM\Parameterization\Vehicles\Colors' ]
            );
            
            // Redireciona para a página de gerenciamento de cores de
            // veículos
            return $this->redirect($response,
              'ADM\Parameterization\Vehicles\Colors')
            ;
          } else {
            // Registra o erro
            $this->debug("Não foi possível inserir as informações da "
              . "cor de veículo '{name}'. Já existe uma cor de veículo "
              . "com o mesmo nome.",
              [ 'name'  => $vehicleColorData['name'] ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Já existe uma cor de veículo "
              . " com o nome <i>'{name}'</i>.",
              [ 'name'  => $vehicleColorData['name'] ]
            );
          }
        }
        catch(QueryException $exception)
        {
          // Registra o erro
          $this->error("Não foi possível inserir as informações da "
            . "cor de veículo '{name}'. Erro interno no banco de "
            . "dados: {error}.",
            [ 'name'  => $vehicleColorData['name'],
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações da cor de veículo. Erro interno no banco "
            . "de dados."
          );
        }
        catch(Exception $exception)
        {
          // Registra o erro
          $this->error("Não foi possível inserir as informações da "
            . "cor de veículo '{name}'. Erro interno: {error}.",
            [ 'name'  => $vehicleColorData['name'],
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações da cor de veículo. Erro interno."
          );
        }
      }
    }
    
    // Exibe um formulário para adição de uma cor de veículo
    
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ADM\Home')
    );
    $this->breadcrumb->push('Parametrização', '');
    $this->breadcrumb->push('Veículos', '');
    $this->breadcrumb->push('Cores',
      $this->path('ADM\Parameterization\Vehicles\Colors')
    );
    $this->breadcrumb->push('Adicionar',
      $this->path('ADM\Parameterization\Vehicles\Colors\Add')
    );
    
    // Registra o acesso
    $this->info("Acesso à adição de cor de veículo.");
    
    // Renderiza a página
    return $this->render($request, $response,
      'adm/parameterization/vehicles/colors/vehiclecolor.twig',
      [ 'formMethod' => 'POST' ])
    ;
  }
  
  /**
   * Exibe um formulário para edição de uma cor de veículo, quando
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
      // Recupera as informações da cor de veículo
      $vehicleColorID = $args['vehicleColorID'];
      $vehicleColor = VehicleColor::findOrFail($vehicleColorID);
      
      // Verifica se estamos modificando os dados
      if ($request->isPut()) {
        // Os dados estão sendo modificados

        // Registra o acesso
        $this->debug("Processando à edição da cor de veículo '{name}'.",
          [ 'name' => $vehicleColor['name'] ]
        );
        
        // Valida os dados
        $this->validator->validate($request, [
          'name' => V::notBlank()
            ->length(2, 30)
            ->setName('Nome da cor'),
          'color' => V::optional(
              V::notEmpty()
                ->length(2, 30)
            )->setName('Cor no CSS')
        ]);

        if ($this->validator->isValid()) {
          // Grava as informações no banco de dados
          try
          {
            // Recupera os dados modificados da cor de veículo
            $vehicleColorData = $this->validator->getValues();
            
            // Primeiro, verifica se não mudamos o nome da cor de veículo
            $save = false;
            if ($vehicleColor->name != $vehicleColorData['name']) {
              // Modificamos o nome da cor de veículo, então verifica
              // se temos uma cor de veículo com o mesmo nome antes de
              // prosseguir
              if (VehicleColor::whereRaw("public.unaccented(name) ILIKE "
                        . "public.unaccented('{$vehicleColorData['name']}')"
                      )
                    ->count() === 0) {
                $save = true;
              } else {
                // Registra o erro
                $this->debug("Não foi possível modificar as "
                  . "informações da cor de veículo '{name}'. Já existe "
                  . "uma cor de veículo com o mesmo nome.",
                  [ 'name'  => $vehicleColorData['name'] ]
                );
                
                // Alerta o usuário
                $this->flashNow("error", "Já existe uma cor de veículo "
                  . "com o nome <i>'{name}'</i>.",
                  [ 'name'  => $vehicleColorData['name'] ]
                );
              }
            } else {
              $save = true;
            }
            
            if ($save) {
              // Grava as informações da cor de veículo
              $vehicleColor->fill($vehicleColorData);
              $vehicleColor->save();
              
              // Registra o sucesso
              $this->info("Modificada a cor de veículo '{name}' "
                . "com sucesso.",
                [ 'name'  => $vehicleColorData['name'] ]
              );
              
              // Alerta o usuário
              $this->flash("success", "A cor de veículo "
                . "<i>'{name}'</i> foi modificada com sucesso.",
                [ 'name'  => $vehicleColorData['name'] ]
              );
              
              // Registra o evento
              $this->debug("Redirecionando para {routeName}",
                [ 'routeName' => 'ADM\Parameterization\Vehicles\Colors' ]
              );
              
              // Redireciona para a página de gerenciamento de cores de
              // veículos
              return $this->redirect($response,
                'ADM\Parameterization\Vehicles\Colors')
              ;
            }
          }
          catch(QueryException $exception)
          {
            // Registra o erro
            $this->error("Não foi possível modificar as informações "
              . "da cor de veículo '{name}'. Erro interno no banco de "
              . "dados: {error}.",
              [ 'name'  => $vehicleColorData['name'],
                'error' => $exception->getMessage() ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Não foi possível modificar as "
              . "informações da cor de veículo. Erro interno no banco "
              . "de dados."
            );
          }
          catch(Exception $exception)
          {
            // Registra o erro
            $this->error("Não foi possível modificar as informações "
              . "da cor de veículo '{name}'. Erro interno: {error}.",
              [ 'name'  => $vehicleColorData['name'],
                'error' => $exception->getMessage() ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Não foi possível modificar as "
              . "informações da cor de veículo. Erro interno."
            );
          }
        }
      } else {
        // Carrega os dados atuais
        $this->validator->setValues($vehicleColor->toArray());
      }
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar a cor de veículo "
        . "código {vehicleColorID}.",
        [ 'vehicleColorID' => $vehicleColorID ]
      );
      
      // Alerta o usuário
      $this->flash("error", "Não foi possível localizar esta cor de "
        . "veículo."
      );
      
      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [ 'routeName' => 'ADM\Parameterization\Vehicles\Colors' ]
      );
      
      // Redireciona para a página de gerenciamento de cores de veículos
      return $this->redirect($response,
        'ADM\Parameterization\Vehicles\Colors')
      ;
    }
    
    // Exibe um formulário para edição de uma cor de veículo
    
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ADM\Home')
    );
    $this->breadcrumb->push('Parametrização', '');
    $this->breadcrumb->push('Veículos', '');
    $this->breadcrumb->push('Cores',
      $this->path('ADM\Parameterization\Vehicles\Colors')
    );
    $this->breadcrumb->push('Editar',
      $this->path('ADM\Parameterization\Vehicles\Colors\Edit', [
        'vehicleColorID' => $vehicleColorID
      ])
    );
    
    // Registra o acesso
    $this->info("Acesso à edição da cor de veículo '{name}'.",
      [ 'name' => $vehicleColor['name'] ]
    );
    
    // Renderiza a página
    return $this->render($request, $response,
      'adm/parameterization/vehicles/colors/vehiclecolor.twig',
      [ 'formMethod' => 'PUT' ])
    ;
  }
  
  /**
   * Remove a cor de veículo.
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
    $this->debug("Processando à remoção de cor de veículo.");
    
    // Recupera o ID
    $vehicleColorID = $args['vehicleColorID'];

    try
    {
      // Recupera as informações da cor de veículo
      $vehicleColor = VehicleColor::findOrFail($vehicleColorID);
      
      // Agora apaga a cor de veículo
      $vehicleColor->delete();
      
      // Registra o sucesso
      $this->info("A cor de veículo '{name}' foi removida com "
        . "sucesso.",
        [ 'name' => $vehicleColor->name ]
      );
      
      // Informa que a remoção foi realizada com sucesso
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getParams(),
            'message' => "Removida a cor de veículo "
              . "{$vehicleColor->name}",
            'data' => "Delete"
          ])
      ;
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar a cor de veículo "
        . "código {vehicleColorID} para remoção.",
        [ 'vehicleColorID' => $vehicleColorID ]
      );
      
      $message = "Não foi possível localizar a cor de veículo para "
        . "remoção."
      ;
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível remover as informações da cor "
        . "de veículo ID {id}. Erro interno no banco de dados: "
        . "{error}.",
        [ 'id' => $vehicleColorID,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível remover a cor de veículo. Erro "
        . "interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível remover as informações da cor "
        . "de veículo ID {id}. Erro interno: {error}.",
        [ 'id' => $vehicleColorID,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível remover a cor de veículo. Erro "
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
