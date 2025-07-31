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
 * O controlador do gerenciamento de tipos de medidas de um valor.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Controllers\ADM\Parameterization\Cadastral;

use App\Models\MeasureType;
use Core\Controllers\Controller;
use Core\Controllers\QueryTrait;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Respect\Validation\Validator as V;
use Slim\Http\Request;
use Slim\Http\Response;

class MeasureTypesController
  extends Controller
{
  /**
   * Os métodos para manipular queries.
   */
  use QueryTrait;

  /**
   * Exibe a página inicial do gerenciamento de tipos de medidas.
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
    $this->breadcrumb->push('Tipos de medidas',
      $this->path('ADM\Parameterization\Cadastral\MeasureTypes')
    );
    
    // Registra o acesso
    $this->info("Acesso ao gerenciamento de tipos de medidas.");
    
    // Recupera os dados da sessão
    $measureType = $this->session->get('measuretype',
      [ 'name' => '' ]
    );
    
    // Renderiza a página
    return $this->render($request, $response,
      'adm/parameterization/cadastral/measuretypes/measuretypes.twig',
      [ 'measuretype' => $measureType ])
    ;
  }
  
  /**
   * Recupera a relação dos tipos de medidas em formato JSON.
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
    $this->debug("Acesso à relação de tipos de medidas.");
    
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
    $this->session->set('measuretype',
      [ 'name' => $name ]
    );

    // Corrige o escape dos campos
    $name = addslashes($name);
    
    try
    {
      // Formata o ordenamento dos campos
      $ORDER = $this->formatOrderBy($orderBy, $orderDir);
      
      // Realiza a consulta
      $MeasureTypeQry = MeasureType::whereRaw("1=1");
      
      // Acrescenta os filtros
      if (!empty($name)) {
        $MeasureTypeQry
          ->whereRaw("public.unaccented(name) ILIKE "
             . "public.unaccented('%{$name}%')"
            )
        ;
      }

      // Conclui nossa consulta
      $measureTypes = $MeasureTypeQry
        ->skip($start)
        ->take($length)
        ->orderByRaw($ORDER)
        ->get([
            'measuretypeid AS id',
            'name',
            'symbol',
            $this->DB->raw("CASE WHEN position = 'END' THEN '0,00 <span class=\"darkorange\">' || symbol || '</span>' ELSE '<span class=\"darkorange\">' || symbol || '</span> 0,00' END AS position"),
            $this->DB->raw('count(*) OVER() AS fullcount')
          ])
      ;
      
      if (count($measureTypes) > 0) {
        $rowCount = $measureTypes[0]->fullcount;
        
        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'draw' => $draw,
              'recordsTotal' => $rowCount,
              'recordsFiltered' => $rowCount,
              'data' => $measureTypes
            ])
        ;
      } else {
        if (empty($name)) {
          $error = "Não temos tipos de medidas cadastrados.";
        } else {
          $error = "Não temos tipos de medidas cadastrados cujo "
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
        [ 'module' => 'tipos de medidas',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de tipos de "
        . "medidas. Erro interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno: {error}.",
        [ 'module' => 'tipos de medidas',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de tipos de "
        . "medidas. Erro interno."
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
   * Exibe um formulário para adição de um tipo de medida, quando
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
    // Define as posições possíveis para um símbolo em relação ao valor
    $positions = [
      [ 'id' => 'START', 'name' => 'Antes do valor' ],
      [ 'id' => 'END', 'name' => 'Depois do valor' ]
    ];

    // Verifica se estamos postando os dados
    if ($request->isPost()) {
      // Os dados estão sendo postados
      
      // Registra o acesso
      $this->debug("Processando à adição de tipo de medida.");

      // Monta uma matriz para validação das posições
      $positionsValues = [ ];
      foreach ($positions AS $position) {
        $positionsValues[] = $position['id'];
      }

      // Valida os dados
      $this->validator->validate($request, [
        'name' => V::notBlank()
          ->length(2, 30)
          ->setName('Tipo de medida'),
        'symbol' => V::notBlank()
          ->length(1, 3)
          ->setName('Símbolo'),
        'position' => V::notBlank()
          ->in($positionsValues)
          ->setName('Posição do símbolo')
      ]);

      if ($this->validator->isValid()) {
        // Grava as informações no banco de dados
        try
        {
          // Recupera os dados do tipo de medida
          $measureTypeData = $this->validator->getValues();
          
          // Primeiro, verifica se não temos um tipo de medida com o
          // mesmo nome
          if (MeasureType::whereRaw("public.unaccented(name) ILIKE "
                    . "public.unaccented('{$measureTypeData['name']}')"
                  )
                ->count() === 0) {
            // Grava o novo tipo de medida
            $measureType = new MeasureType();
            $measureType->fill($measureTypeData);
            $measureType->save();
            
            // Registra o sucesso
            $this->info("Cadastrado o tipo de medida '{name}' com "
              . "sucesso.",
              [ 'name'  => $measureTypeData['name'] ]
            );
            
            // Alerta o usuário
            $this->flash("success", "O tipo de medida <i>'{name}'</i> "
              . "foi cadastrado com sucesso.",
              [ 'name'  => $measureTypeData['name'] ]
            );
            
            // Registra o evento
            $this->debug("Redirecionando para {routeName}",
              [ 'routeName' => 'ADM\Parameterization\Cadastral\MeasureTypes' ]
            );
            
            // Redireciona para a página de gerenciamento de tipos de
            // medidas
            return $this->redirect($response,
              'ADM\Parameterization\Cadastral\MeasureTypes')
            ;
          } else {
            // Registra o erro
            $this->debug("Não foi possível inserir as informações do "
              . "tipo de medida '{name}'. Já existe um tipo de medida "
              . "com o mesmo nome.",
              [ 'name'  => $measureTypeData['name'] ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Já existe um tipo de medida "
              . " com o nome <i>'{name}'</i>.",
              [ 'name'  => $measureTypeData['name'] ]
            );
          }
        }
        catch(QueryException $exception)
        {
          // Registra o erro
          $this->error("Não foi possível inserir as informações do "
            . "tipo de medida '{name}'. Erro interno no banco de "
            . "dados: {error}.",
            [ 'name'  => $measureTypeData['name'],
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações do tipo de medida. Erro interno no banco "
            . "de dados."
          );
        }
        catch(Exception $exception)
        {
          // Registra o erro
          $this->error("Não foi possível inserir as informações do "
            . "tipo de medida '{name}'. Erro interno: {error}.",
            [ 'name'  => $measureTypeData['name'],
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações do tipo de medida. Erro interno."
          );
        }
      }
    } else {
      // Carrega um conjunto de valores vazio
      $emptyMeasureType = [
        'name'     => '',
        'symbol'   => '',
        'position' => 'START'
      ];
      $this->validator->setValues($emptyMeasureType);
    }
    
    // Exibe um formulário para adição de um tipo de medida
    
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ADM\Home')
    );
    $this->breadcrumb->push('Parametrização', '');
    $this->breadcrumb->push('Dados Cadastrais', '');
    $this->breadcrumb->push('Tipos de medidas',
      $this->path('ADM\Parameterization\Cadastral\MeasureTypes')
    );
    $this->breadcrumb->push('Adicionar',
      $this->path('ADM\Parameterization\Cadastral\MeasureTypes\Add')
    );
    
    // Registra o acesso
    $this->info("Acesso à adição de tipo de medida.");
    
    // Renderiza a página
    return $this->render($request, $response,
      'adm/parameterization/cadastral/measuretypes/measuretype.twig',
      [ 'formMethod' => 'POST',
        'positions' => $positions ])
    ;
  }
  
  /**
   * Exibe um formulário para edição de um tipo de medida, quando
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
    // Define as posições possíveis para um símbolo em relação ao valor
    $positions = [
      [ 'id' => 'START', 'name' => 'Antes do valor' ],
      [ 'id' => 'END', 'name' => 'Depois do valor' ]
    ];

    try
    {
      // Recupera as informações do tipo de medida
      $measureTypeID = $args['measureTypeID'];
      $measureType = MeasureType::findOrFail($measureTypeID);
      
      // Verifica se estamos modificando os dados
      if ($request->isPut()) {
        // Os dados estão sendo modificados
        
        // Registra o acesso
        $this->debug("Processando à edição do tipo de medida '{name}'.",
          [ 'name' => $measureType['name'] ]
        );
        
        // Monta uma matriz para validação das posições
        $positionsValues = [ ];
        foreach ($positions AS $position) {
          $positionsValues[] = $position['id'];
        }
        
        // Valida os dados
        $this->validator->validate($request, [
          'name' => V::notBlank()
            ->length(2, 30)
            ->setName('Tipo de medida'),
          'symbol' => V::notBlank()
            ->length(1, 3)
            ->setName('Símbolo'),
          'position' => V::notBlank()
            ->in($positionsValues)
            ->setName('Posição do símbolo')
        ]);

        if ($this->validator->isValid()) {
          // Grava as informações no banco de dados
          try
          {
            // Recupera os dados modificados do tipo de medida
            $measureTypeData = $this->validator->getValues();
            
            // Primeiro, verifica se não mudamos o nome do tipo de
            // medida
            $save = false;
            if ($measureType->name != $measureTypeData['name']) {
              // Modificamos o nome do tipo de medida, então verifica
              // se temos um tipo de medida com o mesmo nome antes de
              // prosseguir
              if (MeasureType::whereRaw("public.unaccented(name) ILIKE "
                        . "public.unaccented('{$measureTypeData['name']}')"
                      )
                    ->count() === 0) {
                $save = true;
              } else {
                // Registra o erro
                $this->debug("Não foi possível modificar as "
                  . "informações do tipo de medida '{name}'. Já existe "
                  . "um tipo de medida com o mesmo nome.",
                  [ 'name'  => $measureTypeData['name'] ]
                );
                
                // Alerta o usuário
                $this->flashNow("error", "Já existe um tipo de medida "
                  . "com o nome <i>'{name}'</i>.",
                  [ 'name'  => $measureTypeData['name'] ]
                );
              }
            } else {
              $save = true;
            }
            
            if ($save) {
              // Grava as informações do tipo de medida
              $measureType->fill($measureTypeData);
              $measureType->save();
              
              // Registra o sucesso
              $this->info("Modificado o tipo de medida '{name}' com "
                . "sucesso.",
                [ 'name'  => $measureTypeData['name'] ]
              );
              
              // Alerta o usuário
              $this->flash("success", "O tipo de medida "
                . "<i>'{name}'</i> foi modificado com sucesso.",
                [ 'name'  => $measureTypeData['name'] ]
              );
              
              // Registra o evento
              $this->debug("Redirecionando para {routeName}",
                [ 'routeName' => 'ADM\Parameterization\Cadastral\MeasureTypes' ]
              );
              
              // Redireciona para a página de gerenciamento de tipos de
              // medidas
              return $this->redirect($response,
                'ADM\Parameterization\Cadastral\MeasureTypes')
              ;
            }
          }
          catch(QueryException $exception)
          {
            // Registra o erro
            $this->error("Não foi possível modificar as informações "
              . "do tipo de medida '{name}'. Erro interno no banco de "
              . "dados: {error}.",
              [ 'name'  => $measureTypeData['name'],
                'error' => $exception->getMessage() ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Não foi possível modificar as "
              . "informações do tipo de medida. Erro interno no banco "
              . "de dados."
            );
          }
          catch(Exception $exception)
          {
            // Registra o erro
            $this->error("Não foi possível modificar as informações "
              . "do tipo de medida '{name}'. Erro interno: {error}.",
              [ 'name'  => $measureTypeData['name'],
                'error' => $exception->getMessage() ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Não foi possível modificar as "
              . "informações do tipo de medida. Erro interno."
            );
          }
        }
      } else {
        // Carrega os dados atuais
        $this->validator->setValues($measureType->toArray());
      }
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar o tipo de medida "
        . "código {measureTypeID}.",
        [ 'measureTypeID' => $measureTypeID ]
      );
      
      // Alerta o usuário
      $this->flash("error", "Não foi possível localizar este tipo de "
        . "medida."
      );
      
      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [ 'routeName' => 'ADM\Parameterization\Cadastral\MeasureTypes' ]
      );
      
      // Redireciona para a página de gerenciamento de tipos de medidas
      return $this->redirect($response,
        'ADM\Parameterization\Cadastral\MeasureTypes')
      ;
    }
    
    // Exibe um formulário para edição de um tipo de medida
    
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ADM\Home')
    );
    $this->breadcrumb->push('Parametrização', '');
    $this->breadcrumb->push('Dados Cadastrais', '');
    $this->breadcrumb->push('Tipos de medidas',
      $this->path('ADM\Parameterization\Cadastral\MeasureTypes')
    );
    $this->breadcrumb->push('Editar',
      $this->path('ADM\Parameterization\Cadastral\MeasureTypes\Edit', [
        'measureTypeID' => $measureTypeID
      ])
    );
    
    // Registra o acesso
    $this->info("Acesso à edição do tipo de medida '{name}'.",
      [ 'name' => $measureType['name'] ]
    );
    
    // Renderiza a página
    return $this->render($request, $response,
      'adm/parameterization/cadastral/measuretypes/measuretype.twig',
      [ 'formMethod' => 'PUT',
        'positions' => $positions ]
    );
  }
  
  /**
   * Remove o tipo de medida.
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
    $this->debug("Processando à remoção de tipo de medida.");
    
    // Recupera o ID
    $measureTypeID = $args['measureTypeID'];

    try
    {
      // Recupera as informações do tipo de medida
      $measureType = MeasureType::findOrFail($measureTypeID);
      
      // Agora apaga o tipo de medida
      $measureType->delete();
      
      // Registra o sucesso
      $this->info("O tipo de medida '{name}' foi removido com sucesso.",
        [ 'name' => $measureType->name ]
      );
      
      // Informa que a remoção foi realizada com sucesso
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getParams(),
            'message' => "Removido o tipo de medida "
              . "{$measureType->name}",
            'data' => "Delete" ]
          )
      ;
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar o tipo de medida "
        . "código {measureTypeID} para remoção.",
        [ 'measureTypeID' => $measureTypeID ]
      );
      
      $message = "Não foi possível localizar o tipo de medida para "
        . "remoção."
      ;
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível remover as informações do tipo "
        . "de medida ID {id}. Erro interno no banco de dados: "
        . "{error}.",
        [ 'id' => $measureTypeID,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível remover o tipo de medida. Erro "
        . "interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível remover as informações do tipo "
        . "de medida ID {id}. Erro interno: {error}.",
        [ 'id' => $measureTypeID,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível remover o tipo de medida. Erro "
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
