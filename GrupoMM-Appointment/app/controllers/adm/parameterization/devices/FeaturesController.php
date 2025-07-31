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
 * O controlador do gerenciamento de características técnicas que um
 * equipamento de rastreamento deve ter para que o mesmo atenda aos
 * requisitos de um contrato.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Controllers\ADM\Parameterization\Devices;

use App\Models\AccessoryType;
use App\Models\Feature;
use Core\Controllers\Controller;
use Core\Controllers\QueryTrait;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Respect\Validation\Validator as V;
use Slim\Http\Request;
use Slim\Http\Response;

class FeaturesController
  extends Controller
{
  /**
   * Os métodos para manipular queries.
   */
  use QueryTrait;

  /**
   * Exibe a página inicial do gerenciamento de tipos de acessórios.
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
    $this->breadcrumb->push('Dispositivos', '');
    $this->breadcrumb->push('Características técnicas',
      $this->path('ADM\Parameterization\Devices\Features')
    );
    
    // Registra o acesso
    $this->info("Acesso ao gerenciamento de características técnicas.");
    
    // Recupera os dados da sessão
    $feature = $this->session->get('feature',
      [ 'name' => '' ]
    );
    
    // Renderiza a página
    return $this->render($request, $response,
      'adm/parameterization/devices/features/features.twig',
      [ 'feature' => $feature ])
    ;
  }
  
  /**
   * Recupera a relação das características técnicas em formato JSON.
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
    $this->debug("Acesso à relação de características técnicas.");
    
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
    $this->session->set('feature',
      [ 'name' => $name ]
    );

    // Corrige o escape dos campos
    $name = addslashes($name);
    
    try
    {
      // Formata o ordenamento dos campos
      $ORDER = $this->formatOrderBy($orderBy, $orderDir);

      // Inicializa a query
      $FeatureQry = Feature::leftJoin('accessorytypes',
            'features.accessorytypeid', '=',
            'accessorytypes.accessorytypeid'
          )
      ;
      
      // Acrescenta os filtros
      if (!empty($name)) {
        $FeatureQry
          ->whereRaw("public.unaccented(features.name) ILIKE "
              . "public.unaccented('%{$name}%')"
            )
        ;
      }

      // Conclui nossa consulta
      $features = $FeatureQry
        ->skip($start)
        ->take($length)
        ->orderByRaw($ORDER)
        ->get([
            'features.featureid AS id',
            'features.name',
            'features.needanaloginput',
            'features.needanalogoutput',
            'features.needdigitalinput',
            'features.needdigitaloutput',
            'features.needrfmodule',
            'features.needonoffbutton',
            'features.needboxopensensor',
            'features.needrs232interface',
            'features.needibuttoninput',
            'features.needantijammer',
            'features.needrpminput',
            'features.needodometerinput',
            'features.needaccelerometer',
            'features.needaccessory',
            'accessorytypes.name AS accessorytypename',
            $this->DB->raw('count(*) OVER() AS fullcount')
          ])
      ;
      
      if (count($features) > 0) {
        $rowCount = $features[0]->fullcount;
        
        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'draw' => $draw,
              'recordsTotal' => $rowCount,
              'recordsFiltered' => $rowCount,
              'data' => $features
            ])
        ;
      } else {
        if (empty($name)) {
          $error = "Não temos características técnicas cadastradas.";
        } else {
          $error = "Não temos características técnicas cadastradas "
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
        [ 'module' => 'características técnicas',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de "
        . "características técnicas. Erro interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno: {error}.",
        [ 'module' => 'características técnicas',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de "
        . "características técnicas. Erro interno."
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
   * Exibe um formulário para adição de uma característica técnica,
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
    // Recupera as informações de tipos de acessórios
    $accessoryTypes = AccessoryType::orderBy('name')
      ->get([
          'accessorytypeid AS id',
          'name'
        ])
    ;

    // Verifica se estamos postando os dados
    if ($request->isPost()) {
      // Os dados estão sendo postados
      
      // Registra o acesso
      $this->debug("Processando à adição de características técnica.");
      
      // Valida os dados
      $needaccessory = $request->getParam('needaccessory');
      $this->validator->validate($request, [
        'name' => V::notBlank()
          ->length(2, 50)
          ->setName('Característica técnica'),
        'needanaloginput' => V::boolVal()
          ->setName('Necessita de entrada analógica'),
        'needanalogoutput' => V::boolVal()
          ->setName('Necessita de saída analógica'),
        'needdigitalinput' => V::boolVal()
          ->setName('Necessita de entrada digital'),
        'needdigitaloutput' => V::boolVal()
          ->setName('Necessita de saída digital'),
        'needrfmodule' => V::boolVal()
          ->setName('Necessita de módulo de comunicação por RF'),
        'needonoffbutton' => V::boolVal()
          ->setName('Necessita de botão liga/desliga'),
        'needboxopensensor' => V::boolVal()
          ->setName('Necessita de sensor de abertura da caixa'),
        'needrs232interface' => V::boolVal()
          ->setName('Necessita de interface RS232 para conexão de periféricos'),
        'needibuttoninput' => V::boolVal()
          ->setName('Necessita de entrada 1-Wire para conexão de leitor de iButton'),
        'needantijammer' => V::boolVal()
          ->setName('Necessita de sensor Anti Jammer'),
        'needrpminput' => V::boolVal()
          ->setName('Necessita de entrada física para leitura do RPM'),
        'needodometerinput' => V::boolVal()
          ->setName('Necessita de entrada física para leitura do Odômetro'),
        'needaccelerometer' => V::boolVal()
          ->setName('Necessita de acelerômetro'),
        'needaccessory' => V::boolVal()
          ->setName('Necessita de um acessório'),
        'accessorytypeid' => V::ifThis(
              $needaccessory === 'true',
              V::notEmpty()
                ->intVal(),
              V::optional(V::intVal())
            )
          ->setName('Tipo do acessório')
      ]);

      if ($this->validator->isValid()) {
        // Grava as informações no banco de dados
        try
        {
          // Recupera os dados da característica técnica
          $featureData = $this->validator->getValues();
          
          // Primeiro, verifica se não temos uma característica técnica
          // com o mesmo nome
          if (Feature::whereRaw("public.unaccented(name) ILIKE "
                    . "public.unaccented('{$featureData['name']}')"
                  )
                ->count() === 0) {
            // Grava a nova característica técnica
            $feature = new Feature();
            $feature->fill($featureData);
            $feature->save();
            
            // Registra o sucesso
            $this->info("Cadastrado a característica técnica '{name}' "
              . "com sucesso.",
              [ 'name'  => $featureData['name'] ]
            );
            
            // Alerta o usuário
            $this->flash("success", "A característica técnica "
              . "<i>'{name}'</i> foi cadastrada com sucesso.",
              [ 'name'  => $featureData['name'] ]
            );
            
            // Registra o evento
            $this->debug("Redirecionando para {routeName}",
              [ 'routeName' => 'ADM\Parameterization\Devices\Features' ]
            );
            
            // Redireciona para a página de gerenciamento de
            // características técnicas
            return $this->redirect($response,
              'ADM\Parameterization\Devices\Features'
            );
          } else {
            // Registra o erro
            $this->debug("Não foi possível inserir as informações da "
              . "característica técnica '{name}'. Já existe uma "
              . "característica com o mesmo nome.",
              [ 'name'  => $featureData['name'] ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Já existe uma característica "
              . "técnica com o nome <i>'{name}'</i>.",
              [ 'name'  => $featureData['name'] ]
            );
          }
        }
        catch(QueryException $exception)
        {
          // Registra o erro
          $this->error("Não foi possível inserir as informações da "
            . "característica técnica '{name}'. Erro interno no banco "
            . "de dados: {error}.",
            [ 'name'  => $featureData['name'],
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações da característica técnica. Erro interno no "
            . "banco de dados."
          );
        }
        catch(Exception $exception)
        {
          // Registra o erro
          $this->error("Não foi possível inserir as informações da "
            . "característica técnica '{name}'. Erro interno: {error}.",
            [ 'name'  => $featureData['name'],
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações da característica técnica. Erro interno."
          );
        }
      }
    }
    
    // Exibe um formulário para adição de uma característica técnica
    
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ADM\Home')
    );
    $this->breadcrumb->push('Parametrização', '');
    $this->breadcrumb->push('Dispositivos', '');
    $this->breadcrumb->push('Características técnicas',
      $this->path('ADM\Parameterization\Devices\Features')
    );
    $this->breadcrumb->push('Adicionar',
      $this->path('ADM\Parameterization\Devices\Features\Add')
    );
    
    // Registra o acesso
    $this->info("Acesso à adição de característica técnica.");
    
    // Renderiza a página
    return $this->render($request, $response,
      'adm/parameterization/devices/features/feature.twig',
      [ 'formMethod' => 'POST',
        'accessoryTypes' => $accessoryTypes ])
    ;
  }
  
  /**
   * Exibe um formulário para edição de uma característica técnica,
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
    try
    {
      // Recupera as informações da característica técnica
      $featureID = $args['featureID'];
      $feature = Feature::findOrFail($featureID);
      
      // Recupera as informações de tipos de acessórios
      $accessoryTypes = AccessoryType::orderBy('name')
        ->get([
            'accessorytypeid AS id',
            'name'
          ])
      ;
      
      // Verifica se estamos modificando os dados
      if ($request->isPut()) {
        // Os dados estão sendo modificados

        // Registra o acesso
        $this->debug("Processando à edição da característica técnica "
          . "'{name}'.",
          [ 'name' => $feature['name'] ]
        );
        
        // Valida os dados
        $needaccessory = $request->getParam('needaccessory');
        $this->validator->validate($request, [
          'featureid' => V::intVal()
            ->setName('ID da característica técnica'),
          'name' => V::notBlank()
            ->length(2, 30)
            ->setName('Característica técnica'),
          'needanaloginput' => V::boolVal()
            ->setName('Necessita de entrada analógica'),
          'needanalogoutput' => V::boolVal()
            ->setName('Necessita de saída analógica'),
          'needdigitalinput' => V::boolVal()
            ->setName('Necessita de entrada digital'),
          'needdigitaloutput' => V::boolVal()
            ->setName('Necessita de saída digital'),
          'needrfmodule' => V::boolVal()
            ->setName('Necessita de módulo de comunicação por RF'),
          'needonoffbutton' => V::boolVal()
            ->setName('Necessita de botão liga/desliga'),
          'needboxopensensor' => V::boolVal()
            ->setName('Necessita de sensor de abertura da caixa'),
          'needrs232interface' => V::boolVal()
            ->setName('Necessita de interface RS232 para conexão de periféricos'),
          'needibuttoninput' => V::boolVal()
            ->setName('Necessita de entrada 1-Wire para conexão de leitor de iButton'),
          'needantijammer' => V::boolVal()
            ->setName('Necessita de sensor Anti Jammer'),
          'needrpminput' => V::boolVal()
            ->setName('Necessita de entrada física para leitura do RPM'),
          'needodometerinput' => V::boolVal()
            ->setName('Necessita de entrada física para leitura do Odômetro'),
          'needaccelerometer' => V::boolVal()
            ->setName('Necessita de acelerômetro'),
          'needaccessory' => V::boolVal()
            ->setName('Necessita de um acessório'),
          'accessorytypeid' => V::ifThis(
                $needaccessory === 'true',
                V::notEmpty()
                  ->intVal(),
                V::optional(V::intVal())
              )
            ->setName('Tipo do acessório')
        ]);

        if ($this->validator->isValid()) {
          // Grava as informações no banco de dados
          try
          {
            // Recupera os dados modificados da característica técnica
            $featureData = $this->validator->getValues();
            
            // Primeiro, verifica se não mudamos o nome do tipo de
            // acessório
            $save = false;
            if ($feature->name != $featureData['name']) {
              // Modificamos o nome da característica técnica, então
              // verifica se temos uma característica técnica com o
              // mesmo nome antes de prosseguir
              if (Feature::whereRaw("public.unaccented(name) ILIKE "
                        . "public.unaccented('{$featureData['name']}')"
                      )
                    ->count() === 0) {
                $save = true;
              } else {
                // Registra o erro
                $this->debug("Não foi possível modificar as "
                  . "informações da característica técnica '{name}'. "
                  . "Já existe uma característica técnica com o mesmo "
                  . "nome.",
                  [ 'name'  => $featureData['name'] ]
                );
                
                // Alerta o usuário
                $this->flashNow("error", "Já existe uma característica "
                  . "técnica com o nome <i>'{name}'</i>.",
                  [ 'name'  => $featureData['name'] ]
                );
              }
            } else {
              $save = true;
            }
            
            if ($save) {
              // Grava as informações da característica técnica
              $feature->fill($featureData);
              $feature->save();
              
              // Registra o sucesso
              $this->info("Modificada a característica técnica "
                . "'{name}' com sucesso.",
                [ 'name'  => $featureData['name'] ]
              );
              
              // Alerta o usuário
              $this->flash("success", "A característica técnica "
                . "<i>'{name}'</i> foi modificada com sucesso.",
                [ 'name'  => $featureData['name'] ]
              );
              
              // Registra o evento
              $this->debug("Redirecionando para {routeName}",
                [ 'routeName' => 'ADM\Parameterization\Devices\Features' ]
              );
              
              // Redireciona para a página de gerenciamento de
              // características técnicas
              return $this->redirect($response,
                'ADM\Parameterization\Devices\Features')
              ;
            }
          }
          catch(QueryException $exception)
          {
            // Registra o erro
            $this->error("Não foi possível modificar as informações "
              . "da característica técnica '{name}'. Erro interno no "
              . "banco de dados: {error}.",
              [ 'name'  => $featureData['name'],
                'error' => $exception->getMessage() ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Não foi possível modificar as "
              . "informações da característica técnica. Erro interno "
              . "no banco de dados."
            );
          }
          catch(Exception $exception)
          {
            // Registra o erro
            $this->error("Não foi possível modificar as informações "
              . "da característica técnica '{name}'. Erro interno: "
              . "{error}.",
              [ 'name'  => $featureData['name'],
                'error' => $exception->getMessage() ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Não foi possível modificar as "
              . "informações da característica técnica. Erro interno."
            );
          }
        }
      } else {
        // Carrega os dados atuais
        $this->validator->setValues($feature->toArray());
      }
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar a característica "
        . "técnica código {featureID}.",
        [ 'featureID' => $featureID ]
      );
      
      // Alerta o usuário
      $this->flash("error", "Não foi possível localizar esta "
        . "característica técnica."
      );
      
      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [ 'routeName' => 'ADM\Parameterization\Devices\Features' ]
      );
      
      // Redireciona para a página de gerenciamento de características
      // técnicas
      return $this->redirect($response,
        'ADM\Parameterization\Devices\Features')
      ;
    }
    
    // Exibe um formulário para edição de uma característica técnica
    
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ADM\Home')
    );
    $this->breadcrumb->push('Parametrização', '');
    $this->breadcrumb->push('Dispositivos', '');
    $this->breadcrumb->push('Características técnicas',
      $this->path('ADM\Parameterization\Devices\Features')
    );
    $this->breadcrumb->push('Editar',
      $this->path('ADM\Parameterization\Devices\Features\Edit', [
        'featureID' => $featureID
      ])
    );
    
    // Registra o acesso
    $this->info("Acesso à edição da característica técnica '{name}'.",
      [ 'name' => $feature['name'] ]
    );
    
    // Renderiza a página
    return $this->render($request, $response,
      'adm/parameterization/devices/features/feature.twig',
      [ 'formMethod' => 'PUT',
        'accessoryTypes' => $accessoryTypes ])
    ;
  }
  
  /**
   * Remove uma característica técnica.
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
    $this->debug("Processando à remoção da característica técnica.");
    
    // Recupera o ID
    $featureID = $args['featureID'];

    try
    {
      // Recupera as informações da característica técnica
      $feature = Feature::findOrFail($featureID);
      
      // Agora apaga a característica técnica
      $feature->delete();
      
      // Registra o sucesso
      $this->info("A característica técnica '{name}' foi removida com "
        . "sucesso.",
        [ 'name' => $feature->name ]
      );
      
      // Informa que a remoção foi realizada com sucesso
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getParams(),
            'message' => "Removida a característica técnica "
              . "{$feature->name}",
            'data' => "Delete"
          ])
      ;
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar a característica "
        . "técnica código {featureID} para remoção.",
        [ 'featureID' => $featureID ]
      );
      
      $message = "Não foi possível localizar a característica técnica "
        . "para remoção."
      ;
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível remover as informações da "
        . "característica técnica ID {id}. Erro interno no banco de "
        . "dados: {error}.",
        [ 'id' => $featureID,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível remover a característica técnica. "
        . "Erro interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível remover as informações da "
        . "característica técnica ID {id}. Erro interno: {error}.",
        [ 'id' => $featureID,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível remover a característica técnica. "
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
}
