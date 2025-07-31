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
 * O controlador do gerenciamento de modelos de equipamentos.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Controllers\ERP\Parameterization\Equipments;

use App\Models\EquipmentBrand;
use App\Models\EquipmentModel;
use App\Models\Protocol;
use App\Models\ProtocolVariant;
use App\Models\SimCardType;
use Core\Controllers\Controller;
use Core\Controllers\QueryTrait;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Respect\Validation\Validator as V;
use RuntimeException;
use Slim\Http\Request;
use Slim\Http\Response;

class EquipmentModelsController
  extends Controller
{
  /**
   * Os métodos para manipular queries.
   */
  use QueryTrait;

  /**
   * Recupera as regras de validação.
   *
   * @param bool $addition
   *   Se a validação é para um formulário de adição
   *
   * @return array
   */
  protected function getValidationRules(
    bool $addition = false
  ): array
  {
    $validationRules = [
      'equipmentmodelid' => V::intVal()
        ->setName('ID do modelo do equipamento'),
      'equipmentbrandid' => V::intVal()
        ->setName('ID da marca do equipamento'),
      'equipmentbrandname' => V::notBlank()
        ->length(2, 30)
        ->setName('Marca do equipamento'),
      'name' => V::notBlank()
        ->length(2, 50)
        ->setName('Modelo de equipamento'),
      'maxsimcards' => V::intVal()
        ->min(1)
        ->setName('Slots disponíveis'),
      'simcardtypeid' => V::intVal()
        ->setName('Modelo de SIM Card'),
      'serialnumbersize' => V::intVal()
        ->min(1)
        ->setName('Tamanho do número de série'),
      'reducednumbersize' => V::intVal()
        ->min(0)
        ->setName('Tamanho reduzido do número de série'),
      'analoginput' => V::intVal()
        ->min(0)
        ->setName('Número de entradas analógicas'),
      'analogoutput' => V::intVal()
        ->min(0)
        ->setName('Número de saídas analógicas'),
      'digitalinput' => V::intVal()
        ->min(0)
        ->setName('Número de entradas digitais'),
      'digitaloutput' => V::intVal()
        ->min(0)
        ->setName('Número de saídas digitais'),
      'hasrfmodule' => V::boolVal()
        ->setName('Possui módulo de comunicação por RF'),
      'hasonoffbutton' => V::boolVal()
        ->setName('Possui botão liga/desliga'),
      'hasboxopensensor' => V::boolVal()
        ->setName('Possui sensor de abertura da caixa'),
      'hasrs232interface' => V::boolVal()
        ->setName('Possui interface RS232 para conexão de periféricos'),
      'hasibuttoninput' => V::boolVal()
        ->setName('Possui entrada 1-Wire para conexão de leitor de iButton'),
      'ibuttonsmemsize' => V::optional(
            V::intVal()
              ->min(0)
          )
        ->setName('Tamanho da memória para iButtons'),
      'hasantijammer' => V::boolVal()
        ->setName('Possui sensor Anti Jammer'),
      'hasrpminput' => V::boolVal()
        ->setName('Possui entrada física para leitura do RPM'),
      'hasodometerinput' => V::boolVal()
        ->setName('Possui entrada física para leitura do Odômetro'),
      'hasaccelerometer' => V::boolVal()
        ->setName('Possui acelerômetro'),
      'protocolid' => V::intVal()
        ->setName('ID do protocolo'),
      'protocolvariantid' => V::intVal()
        ->setName('ID da variante do protocolo')
    ];

    if ($addition) {
      // Ajusta as regras para adição
      unset($validationRules['equipmentmodelid']);
    }

    return $validationRules;
  }

  /**
   * Recupera as informações de tipos de SIM Cards.
   *
   * @return Collection
   *   A matriz com as informações de tipos de SIM Cards
   *
   * @throws RuntimeException
   *   Em caso de não termos tipos de SIM Cards
   */
  protected function getSimCardTypes(): Collection
  {
    try {
      // Recupera as informações de tipos de SIM Cards
      $simcardTypes = SimCardType::orderBy('simcardtypeid')
        ->get([
            'simcardtypeid AS id',
            'name'
          ])
      ;

      if ( $simcardTypes->isEmpty() ) {
        throw new Exception("Não temos nenhum tipo de SIM Card "
          . "cadastrado"
        );
      }
    }
    catch (Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível obter as informações de tipos de "
        . "SIM Cards. Erro: {error}.",
        [ 'error' => $exception->getMessage() ]
      );

      throw new RuntimeException("Não foi possível obter os tipos de "
        . "SIM Cards"
      );
    }

    return $simcardTypes;
  }

  /**
   * Recupera as informações de protocolos.
   * 
   * @param bool $toAdd
   *   Se é para adicionar um valor "0" -> "Não definido"
   *
   * @return Collection
   *   A matriz com as informações de protocolos
   *
   * @throws RuntimeException
   *   Em caso de não termos protocolos
   */
  protected function getProtocols(
    bool $toAdd = true
  ): Collection
  {
    try {
      // Recupera as informações de protocolos
      $protocols = Protocol::orderBy('name')
        ->get([
            'protocolid AS id',
            'name'
          ])
      ;

      if ( $protocols->isEmpty() ) {
        throw new Exception("Não temos nenhum protocolo cadastrado");
      }

      if ($toAdd) {
        // Adiciona o valor "0" -> "Não definido"
        $protocols->prepend((object)[
          'id' => 0,
          'name' => 'Não definido'
        ]);
      }
    }
    catch (Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível obter as informações de "
        . "protocolos. Erro: {error}.",
        [ 'error' => $exception->getMessage() ]
      );

      throw new RuntimeException(
        "Não foi possível obter os protocolos"
      );
    }

    return $protocols;
  }

  /**
   * Recupera as informações de variantes de protocolo.
   *
   * @return array
   *   A matriz com as informações de variantes de protocolo
   *
   * @throws RuntimeException
   *   Em caso de não termos variantes de protocolo
   */
  protected function getProtocolVariants(): array
  {
    try {
      // Recupera as informações de protocolos
      $protocolvariants = ProtocolVariant::orderBy('protocolid')->orderBy('name')
        ->get([
            'protocolvariantid AS id',
            'protocolid',
            'name'
          ])
      ;

      if ( $protocolvariants->isEmpty() ) {
        throw new Exception(
          "Não temos nenhuma variante de protocolo cadastrada"
        );
      }

      $variants = [];
      // Adiciona o valor "0" -> "Sem variante"
      $variants[0] = [
        [
          'name' => 'Sem variante',
          'value' => 0
        ]
      ];
      foreach ($protocolvariants as $variant) {
        $variants[$variant->protocolid][] = [
          'name' => $variant->name,
          'value' => $variant->id
        ];
      }

      $protocolvariants = $variants;
    }
    catch (Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível obter as informações de "
        . "variantes de protocolos. Erro: {error}.",
        [ 'error' => $exception->getMessage() ]
      );

      throw new RuntimeException(
        "Não foi possível obter as variantes de protocolo"
      );
    }

    return $protocolvariants;
  }

  /**
   * Exibe a página inicial do gerenciamento de modelos de equipamentos.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   *
   * @return Response $response
   */
  public function show(
    Request $request,
    Response $response
  ): Response
  {
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ERP\Home')
    );
    $this->breadcrumb->push('Parametrização', '');
    $this->breadcrumb->push('Equipamentos', '');
    $this->breadcrumb->push('Modelos',
      $this->path('ERP\Parameterization\Equipments\Models')
    );
    
    // Registra o acesso
    $this->info("Acesso ao gerenciamento de modelos de equipamentos.");

    try {
      // Recupera as informações de protocolos
      $protocols = $this->getProtocols(false);
    }
    catch (RuntimeException $exception)
    {
      // Alerta o usuário
      $this->flash("error", $exception->getMessage());

      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [ 'routeName' => 'ERP\Parameterization\Equipments\Models' ]
      );

      // Redireciona para a página de gerenciamento de modelos de
      // equipamentos
      return $this->redirect($response,
        'ERP\Parameterization\Equipments\Models'
      );
    }
    
    // Recupera os dados da sessão
    $equipment = $this->session->get('equipment',
      [ 'brand' => [
          'id' => 0,
          'name' => ''
        ],
        'protocol' => [
          'id' => 0,
          'name' => 'Todos protocolos'
        ],
        'model' => [
          'name' => ''
        ]
      ]
    );
    
    // Renderiza a página
    return $this->render($request, $response,
      'erp/parameterization/equipments/models/equipmentmodels.twig',
      [
        'protocols' => $protocols,
        'equipment' => $equipment,
      ])
    ;
  }
  
  /**
   * Recupera a relação dos modelos de equipamentos em formato JSON.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   *
   * @return Response $response
   */
  public function get(
    Request $request,
    Response $response
  ): Response
  {
    $this->debug("Acesso à relação de modelos de equipamentos.");
    
    // --------------------------[ Recupera os dados requisitados ]-----

    // Recupera os dados da requisição
    $postParams = $request->getParsedBody();

    // O ID do contratante
    $contractorID = $this->authorization->getContractor()->id;

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
    $name         = $postParams['searchValue'];
    $brandID      = $postParams['brandID'];
    $brandName    = $postParams['brandName'];
    $protocolID   = $postParams['protocolID'];
    $protocolName = $postParams['protocolName'];
    
    // Seta os valores da última pesquisa na sessão
    $this->session->set('equipment',
      [ 'brand' => [
          'id' => $brandID,
          'name' => $brandName
        ],
        'protocol' => [
          'id' => $protocolID,
          'name' => $protocolName
        ],
        'model' => [
          'name' => $name
        ]
      ]
    );

    // Corrige o escape dos campos
    $name = addslashes($name);
    
    try
    {
      // Formata o ordenamento dos campos
      $ORDER = $this->formatOrderBy($orderBy, $orderDir);
      
      // Inicializa a query
      $EquipmentModelQry = EquipmentModel::join('equipmentbrands',
            'equipmentmodels.equipmentbrandid', '=',
            'equipmentbrands.equipmentbrandid'
          )
        ->join('simcardtypes', 'equipmentmodels.simcardtypeid',
            '=', 'simcardtypes.simcardtypeid'
          )
        ->leftJoin('protocols', 'equipmentmodels.protocolid',
            '=', 'protocols.protocolid'
          )
        ->where('equipmentmodels.contractorid', '=', $contractorID)
      ;

      // Acrescenta os filtros
      switch ($this->binaryFlags(empty($name), empty($brandID))) {
        case 1:
          // Informado apenas o nome
          $EquipmentModelQry
            ->whereRaw("public.unaccented(equipmentmodels.name) ILIKE "
                . "public.unaccented(E'%{$name}%')"
              )
          ;

          break;
        case 2:
          // Informado apenas a ID da marca
          $EquipmentModelQry
            ->where('equipmentmodels.equipmentbrandid', '=', $brandID)
          ;
          
          break;
        case 3:
          // Informado tanto o nome quanto o ID da marca
          $EquipmentModelQry
            ->where('equipmentmodels.equipmentbrandid', '=', $brandID)
            ->whereRaw("public.unaccented(equipmentmodels.name) ILIKE "
                . "public.unaccented(E'%{$name}%')"
              )
          ;

          break;
        default:
          // Não adiciona nenhum filtro
      }

      if (!empty($protocolID)) {
        // Informado o ID do protocolo
        $EquipmentModelQry
          ->where('equipmentmodels.protocolid', '=', $protocolID)
        ;
      }

      // Conclui nossa consulta
      $equipmentModels = $EquipmentModelQry
        ->skip($start)
        ->take($length)
        ->orderByRaw($ORDER)
        ->get([
            'equipmentmodels.equipmentmodelid AS id',
            'equipmentmodels.name',
            $this->DB->raw(""
              . "CASE "
              . "  WHEN equipmentmodels.protocolID IS NULL THEN 'Não definido' "
              . "  ELSE protocols.name "
              . "END AS protocol"
            ),
            'equipmentmodels.maxsimcards',
            'equipmentmodels.analoginput',
            'equipmentmodels.analogoutput',
            'equipmentmodels.digitalinput',
            'equipmentmodels.digitaloutput',
            'equipmentmodels.hasrfmodule',
            'equipmentmodels.hasonoffbutton',
            'equipmentmodels.hasboxopensensor',
            'equipmentmodels.hasrs232interface',
            'equipmentmodels.hasibuttoninput',
            'equipmentmodels.hasantijammer',
            'equipmentmodels.hasrpminput',
            'equipmentmodels.hasodometerinput',
            'equipmentmodels.hasaccelerometer',
            'simcardtypes.name AS simcardtypename',
            'equipmentbrands.name AS equipmentbrandname',
            $this->DB->raw('count(*) OVER() AS fullcount')
          ])
      ;
      
      if (count($equipmentModels) > 0) {
        $rowCount = $equipmentModels[0]->fullcount;

        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'draw' => $draw,
              'recordsTotal' => $rowCount,
              'recordsFiltered' => $rowCount,
              'data' => $equipmentModels
            ])
        ;
      } else {
        switch ($this->binaryFlags(empty($name), empty($brandID))) {
          case 1:
            // Informado apenas o nome
            $error = "Não temos modelos de equipamentos cadastrados "
              . "cujo nome contém <i>{$name}</i>."
            ;

            break;
          case 2:
            // Informado apenas a ID da marca
            $error = "Não temos modelos de equipamentos cadastrados "
              . "da marca <i>{$brandName}</i>."
            ;

            break;
          case 3:
            // Informado tanto o nome quanto o ID da marca
            $error = "Não temos modelos de equipamentos cadastrados "
              . "da marca <i>{$brandName}</i> cujo nome contém "
              . "<i>{$name}</i>."
            ;

            break;
          default:
            $error = "Não temos modelos de equipamentos cadastrados.";
        }
      }
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno no banco de dados: {error}.",
        [ 'module' => 'modelos de equipamentos',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de modelos "
        . "de equipamentos. Erro interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno: {error}.",
        [ 'module' => 'modelos de equipamentos',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de modelos "
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
   * Exibe um formulário para adição de um modelo de equipamento, quando
   * solicitado, e confirma os dados enviados.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   *
   * @return Response $response
   */
  public function add(
    Request $request,
    Response $response
  ): Response
  {
    try {
      // Recupera os dados do contratante
      $contractor = $this->authorization->getContractor();

      // Recupera as informações de tipos de SIM Cards
      $simcardTypes = $this->getSimCardTypes();

      // Recupera as informações de protocolos
      $protocols = $this->getProtocols(true);

      // Recupera as informações de variantes de protocolos
      $protocolVariants = $this->getProtocolVariants();
    }
    catch (RuntimeException $exception)
    {
      // Alerta o usuário
      $this->flash("error", $exception->getMessage());

      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [ 'routeName' => 'ERP\Parameterization\Equipments\Models' ]
      );

      // Redireciona para a página de gerenciamento de modelos de
      // equipamentos
      return $this->redirect($response,
        'ERP\Parameterization\Equipments\Models'
      );
    }

    // Recupera as informações de filtragem no gerenciamento para
    // simplificar ao usuário a digitação destas informações
    $brandID   = $request->getQueryParams()['brandID'];
    $brandName = $request->getQueryParams()['brandName'];

    // Verifica se estamos postando os dados
    if ($request->isPost()) {
      // Os dados estão sendo postados
      
      // Registra o acesso
      $this->debug("Processando à adição de modelo de equipamento.");

      // Valida os dados
      $this->validator->validate($request,
        $this->getValidationRules(true)
      );

      if ($this->validator->isValid()) {
        // Grava as informações no banco de dados
        try
        {
          // Recupera os dados do modelo de equipamento
          $equipmentModelData = $this->validator->getValues();

          $save = false;
          if (intval($equipmentModelData['equipmentbrandid']) > 0) {
            // Verifica se não temos um modelo de equipamento da marca
            // com o mesmo nome neste contratante
            if (EquipmentModel::where("contractorid",
                      '=', $contractor->id
                    )
                  ->where("equipmentbrandid", '=',
                      $equipmentModelData['equipmentbrandid']
                    )
                  ->whereRaw("public.unaccented(name) ILIKE "
                      . "public.unaccented('{$equipmentModelData['name']}')"
                    )
                  ->count() === 0) {
              // Prossegue normalmente
              $save = true;
            }
          } else {
            // Prossegue normalmente
            $save = true;
          }

          if ($save) {
            // Iniciamos a transação
            $this->DB->beginTransaction();

            if (intval($equipmentModelData['equipmentbrandid']) === 0) {
              // Adicionamos a marca de equipamento
              $equipmentBrand = new EquipmentBrand();
              $equipmentBrand->name =
                $equipmentModelData['equipmentbrandname']
              ;
              $equipmentBrand->contractorid = $contractor->id;
              $equipmentBrand->save();
              $equipmentModelData['equipmentbrandid'] =
                $equipmentBrand->equipmentbrandid
              ;
            }

            // Força que a memória para iButtons seja zero se não
            // tivermos a entrada para iButton
            if ($equipmentModelData['hasibuttoninput'] === "false") {
              $equipmentModelData['ibuttonsmemsize'] = 0;
            }

            // Força que o protocolo seja nulo se não tivermos
            // selecionado um protocolo
            if (intval($equipmentModelData['protocolid']) === 0) {
              $this->info("Nenhum protocolo selecionado.");
              $equipmentModelData['protocolid'] = null;
              $equipmentModelData['protocolvariantid'] = null;
            }

            $this->info("Dados do modelo de equipamento: {data}",
              [ 'data' => json_encode($equipmentModelData) ]
            );

            // Grava o novo modelo de equipamento
            $equipmentModel = new EquipmentModel();
            $equipmentModel->fill($equipmentModelData);

            // Adiciona o contratante e o responsável
            $equipmentModel->contractorid = $contractor->id;
            $equipmentModel->createdbyuserid =
              $this->authorization->getUser()->userid
            ;
            $equipmentModel->updatedbyuserid =
              $this->authorization->getUser()->userid
            ;
            $equipmentModel->save();

            // Efetiva a transação
            $this->DB->commit();

            // Registra o sucesso
            $this->info("Cadastrado o modelo de equipamento '{name}' "
              . "da marca '{brandName}' no contratante '{contractor}' "
              . "com sucesso.",
              [ 'name'       => $equipmentModelData['name'],
                'brandName'  => $equipmentModelData['equipmentbrandname'],
                'contractor' => $contractor->name ]
            );
            
            // Alerta o usuário
            $this->flash("success", "O modelo de equipamento "
              . "<i>'{name}'</i> da marca <i>'{brandName}'</i> foi "
              . "cadastrado com sucesso.",
              [ 'name'  => $equipmentModelData['name'],
                'brandName'  => $equipmentModelData['equipmentbrandname'] ]
            );
            
            // Registra o evento
            $this->debug("Redirecionando para {routeName}",
              [ 'routeName' => 'ERP\Parameterization\Equipments\Models' ]
            );
            
            // Redireciona para a página de gerenciamento de modelos de
            // equipamentos
            return $this->redirect($response,
              'ERP\Parameterization\Equipments\Models')
            ;
          } else {
            // Registra o erro
            $this->debug("Não foi possível inserir as informações do "
              . "modelo de equipamento '{name}' da marca '{brandName}' "
              . "no contratante '{contractor}'. Já existe um modelo de "
              . "equipamento com o mesmo nome.",
              [ 'name'       => $equipmentModelData['name'],
                'brandName'  => $equipmentModelData['equipmentbrandname'],
                'contractor' => $contractor->name ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Já existe um modelo de "
              . "equipamento com o nome <i>'{name}'</i> na marca "
              . "<i>'{brandName}'</i>.",
              [ 'name'  => $equipmentModelData['name'],
                'brandName'  => $equipmentModelData['equipmentbrandname'] ]
            );
          }
        }
        catch(QueryException $exception)
        {
          // Reverte (desfaz) a transação
          $this->DB->rollBack();

          // Registra o erro
          $this->error("Não foi possível inserir as informações do "
            . "modelo de equipamento '{name}' da marca '{brandName}' "
            . "no contratante '{contractor}'. Erro interno no banco de "
            . "dados: {error}.",
            [ 'name'       => $equipmentModelData['name'],
              'brandName'  => $equipmentModelData['equipmentbrandname'],
              'contractor' => $contractor->name,
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações do modelo de equipamento <i>'{name}'</i> no "
            . "fabricante <i>'{brandName}'</i>.Erro interno no banco "
            . "de dados.",
            [ 'name'  => $equipmentModelData['name'],
              'brandName'  => $equipmentModelData['equipmentbrandname'] ]
          );
        }
        catch(Exception $exception)
        {
          // Reverte (desfaz) a transação
          $this->DB->rollBack();

          // Registra o erro
          $this->error("Não foi possível inserir as informações do "
            . "modelo de equipamento '{name}' da marca '{brandName}' "
            . "no contratante '{contractor}'. Erro interno: {error}.",
            [ 'name'       => $equipmentModelData['name'],
              'brandName'  => $equipmentModelData['equipmentbrandname'],
              'contractor' => $contractor->name,
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações do modelo de equipamento <i>'{name}'</i> no "
            . "fabricante <i>'{brandName}'</i>.Erro interno.",
            [ 'name'  => $equipmentModelData['name'],
              'brandName'  => $equipmentModelData['equipmentbrandname'] ]
          );
        }
      }
    } else {
      // Carrega os dados iniciais para simplificar a digitação
      if (!isset($brandID)) {
        $brandID = 0;
        $brandName = '';
      }

      $this->validator->setValues([
        'equipmentbrandid' => $brandID,
        'equipmentbrandname' => $brandName,
        'maxsimcards' => 1,
        'simcardtypeid' => 1,
        'serialnumbersize' => 0,
        'reducednumbersize' => 0,
        'analoginput' => 0,
        'analogoutput' => 0,
        'digitalinput' => 0,
        'digitaloutput' => 0,
        'ibuttonsmemsize' => 0
      ]);
    }
    
    // Exibe um formulário para adição de um modelo de equipamento
    
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ERP\Home')
    );
    $this->breadcrumb->push('Parametrização', '');
    $this->breadcrumb->push('Equipamentos', '');
    $this->breadcrumb->push('Modelos',
      $this->path('ERP\Parameterization\Equipments\Models')
    );
    $this->breadcrumb->push('Adicionar',
      $this->path('ERP\Parameterization\Equipments\Models\Add')
    );
    
    // Registra o acesso
    $this->info("Acesso à adição de modelo de equipamento no "
      . "contratante '{contractor}'.",
      [ 'contractor' => $contractor->name ]
    );
    
    // Renderiza a página
    return $this->render($request, $response,
      'erp/parameterization/equipments/models/equipmentmodel.twig',
      [
        'formMethod' => 'POST',
        'simcardTypes' => $simcardTypes,
        'protocols' => $protocols,
        'protocolVariants' => $protocolVariants
      ])
    ;
  }
  
  /**
   * Exibe um formulário para edição de um modelo de equipamento, quando
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
  public function edit(
    Request $request,
    Response $response,
    array $args
  ): Response
  {
    try {
      // Recupera os dados do contratante
      $contractor = $this->authorization->getContractor();

      // Recupera as informações de tipos de SIM Cards
      $simcardTypes = $this->getSimCardTypes();

      // Recupera as informações de protocolos
      $protocols = $this->getProtocols(true);

      // Recupera as informações de variantes de protocolos
      $protocolVariants = $this->getProtocolVariants();
    }
    catch (RuntimeException $exception)
    {
      // Alerta o usuário
      $this->flash("error", $exception->getMessage());

      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [ 'routeName' => 'ERP\Parameterization\Equipments\Models' ]
      );

      // Redireciona para a página de gerenciamento de modelos de
      // equipamentos
      return $this->redirect($response,
        'ERP\Parameterization\Equipments\Models'
      );
    }
    
    try
    {
      // Recupera as informações do modelo de equipamento
      $equipmentModelID = $args['equipmentModelID'];
      $equipmentModel = EquipmentModel::join('equipmentbrands',
            'equipmentmodels.equipmentbrandid', '=',
            'equipmentbrands.equipmentbrandid'
          )
        ->join('simcardtypes', 'equipmentmodels.simcardtypeid',
            '=', 'simcardtypes.simcardtypeid'
          )
        ->leftJoin('protocols', 'equipmentmodels.protocolid',
            '=', 'protocols.protocolid'
          )
        ->join('users AS createduser', 'equipmentmodels.createdbyuserid',
            '=', 'createduser.userid'
          )
        ->join('users AS updateduser', 'equipmentmodels.updatedbyuserid',
            '=', 'updateduser.userid'
          )
        ->where('equipmentmodels.contractorid', '=', $contractor->id)
        ->where('equipmentmodelid', '=', $equipmentModelID)
        ->get([
            'equipmentmodels.*',
            'equipmentbrands.name AS equipmentbrandname',
            'simcardtypes.name AS simcardtypename',
            'protocols.name AS protocolname',
            'createduser.name AS createdbyusername',
            'updateduser.name AS updatedbyusername'
          ])
      ;

      if ( $equipmentModel->isEmpty() ) {
        throw new ModelNotFoundException("Não temos nenhum modelo de "
          . "equipamento com o código {$equipmentModelID} cadastrado"
        );
      }
      $equipmentModel = $equipmentModel
        ->first()
        ->toArray()
      ;

      if (is_null($equipmentModel['protocolid'])) {
        $equipmentModel['protocolid'] = 0;
        $equipmentModel['protocolvariantid'] = 0;
      }
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar o modelo de equipamento "
        . "código {equipmentModelID}.",
        [ 'equipmentModelID' => $equipmentModelID ]
      );
      
      // Alerta o usuário
      $this->flash("error", "Não foi possível localizar este modelo "
        . "de equipamento."
      );
      
      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [ 'routeName' => 'ERP\Parameterization\Equipments\Models' ]
      );
      
      // Redireciona para a página de gerenciamento de modelos de
      // equipamentos
      return $this->redirect($response,
        'ERP\Parameterization\Equipments\Models')
      ;
    }
      
    // Verifica se estamos modificando os dados
    if ($request->isPut()) {
      // Os dados estão sendo modificados
      
      // Registra o acesso
      $this->debug("Processando à edição do modelo de equipamento "
        . "'{name}' da marca '{brandName}' no contratante "
        . "'{contractor}'.",
        [ 'name'       => $equipmentModel['name'],
          'brandName'  => $equipmentModel->equipmentbrandname,
          'contractor' => $contractor->name ]
      );
      
      // Valida os dados
      $this->validator->validate($request,
        $this->getValidationRules(false)
      );

      if ($this->validator->isValid()) {
        // Grava as informações no banco de dados
        try
        {
          // Recupera os dados modificados do modelo de equipamento
          $equipmentModelData = $this->validator->getValues();
          
          // Primeiro, verifica se não mudamos o nome do modelo de
          // equipamento ou sua marca
          $save = false;
          if ( ((strtolower($equipmentModel['name']) != strtolower($equipmentModelData['name'])) ||
                ($equipmentModel['equipmentbrandid'] != $equipmentModelData['equipmentbrandid'])) &&
                (intval($equipmentModelData['equipmentbrandid']) > 0) ) {
            // Modificamos o nome do modelo de equipamento e/ou sua
            // marca, então verifica se temos um modelo de equipamento
            // com o mesmo nome e marca neste contratante antes de
            // prosseguir
            if (EquipmentModel::where("contractorid", '=',
                      $contractor->id
                    )
                  ->where("equipmentbrandid", '=',
                      $equipmentModelData['equipmentbrandid']
                    )
                  ->whereRaw("public.unaccented(name) ILIKE "
                      . "public.unaccented('{$equipmentModelData['name']}')"
                    )
                  ->count() === 0) {
              $save = true;
            } else {
              // Registra o erro
              $this->debug("Não foi possível modificar as informações "
                . "do modelo de equipamento '{name}' da marca "
                . "'{brandName}' no contratante '{contractor}'. Já "
                . "existe um modelo de equipamento com o mesmo nome.",
                [ 'name'       => $equipmentModelData['name'],
                  'brandName'  => $equipmentModelData['equipmentbrandname'],
                  'contractor' => $contractor->name ]
              );
              
              // Alerta o usuário
              $this->flashNow("error", "Já existe um modelo de "
                . "equipamento com o nome <i>'{name}'</i> na marca "
                . "<i>'{brandName}'</i>.",
                [ 'name'  => $equipmentModelData['name'],
                  'brandName'  => $equipmentModelData['equipmentbrandname'] ]
              );
            }
          } else {
            $save = true;
          }
          
          if ($save) {
            // Iniciamos a transação
            $this->DB->beginTransaction();

            if (intval($equipmentModelData['equipmentbrandid']) === 0) {
              // Adicionamos a marca de equipamento
              $equipmentBrand = new EquipmentBrand();
              $equipmentBrand->name =
                $equipmentModelData['equipmentbrandname']
              ;
              $equipmentBrand->contractorid = $contractor->id;
              $equipmentBrand->save();
              $equipmentModelData['equipmentbrandid'] =
                $equipmentBrand->equipmentbrandid
              ;
            }

            if (intval($equipmentModelData['protocolid']) === 0) {
              $this->info("Nenhum protocolo selecionado.");
              $equipmentModelData['protocolid'] = null;
              $equipmentModelData['protocolvariantid'] = null;
            }

            // Grava as informações do modelo de equipamento
            $updEquipmentModel = EquipmentModel::findOrFail($equipmentModelID);
            $updEquipmentModel->fill($equipmentModelData);

            // Força que a memória para iButtons seja zero se não
            // tivermos a entrada para iButton
            if ($equipmentModelData['hasibuttoninput'] === 'false') {
              $updEquipmentModel->ibuttonsmemsize = 0;
            }

            // Adiciona o responsável
            $updEquipmentModel->updatedbyuserid = $this->authorization->getUser()->userid;
            $updEquipmentModel->save();

            // Efetiva a transação
            $this->DB->commit();
            
            // Registra o sucesso
            $this->info("Modificado o modelo de equipamento '{name}' "
              . "da marca '{brandName}' no contratante "
              . "'{contractor}' com sucesso.",
              [ 'name'       => $equipmentModelData['name'],
                'brandName'  => $equipmentModelData['equipmentbrandname'],
                'contractor' => $contractor->name ]
            );
            
            // Alerta o usuário
            $this->flash("success", "O modelo de equipamento "
              . "<i>'{name}'</i> da marca <i>'{brandName}'</i> foi "
              . "modificado com sucesso.",
              [ 'name'  => $equipmentModelData['name'],
                'brandName'  => $equipmentModelData['equipmentbrandname'] ]
            );
            
            // Registra o evento
            $this->debug("Redirecionando para {routeName}",
              [ 'routeName' => 'ERP\Parameterization\Equipments\Models' ]
            );
            
            // Redireciona para a página de gerenciamento de modelos
            // de equipamentos
            return $this->redirect($response,
              'ERP\Parameterization\Equipments\Models'
            );
          }
        }
        catch(QueryException $exception)
        {
          // Reverte (desfaz) a transação
          $this->DB->rollBack();

          // Registra o erro
          $this->error("Não foi possível modificar as informações do "
            . "modelo de equipamento '{name}' da marca '{brandName}' "
            . "no contratante '{contractor}'. Erro interno no banco de "
            . "dados: {error}.",
            [ 'name'       => $equipmentModel['name'],
              'brandName'  => $equipmentModel['equipmentbrandname'],
              'contractor' => $contractor->name,
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível modificar as "
            . "informações do modelo de equipamento <i>'{name}'</i> no "
            . "fabricante <i>'{brandName}'</i>.Erro interno no banco "
            . "de dados.",
            [ 'name'  => $equipmentModel['name'],
              'brandName'  => $equipmentModel['equipmentbrandname'] ]
          );
        }
        catch(Exception $exception)
        {
          // Reverte (desfaz) a transação
          $this->DB->rollBack();

          // Registra o erro
          $this->error("Não foi possível modificar as informações do "
            . "modelo de equipamento '{name}' da marca '{brandName}' "
            . "no contratante '{contractor}'. Erro interno: {error}.",
            [ 'name'       => $equipmentModel['name'],
              'brandName'  => $equipmentModel['equipmentbrandname'],
              'contractor' => $contractor->name,
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível modificar as "
            . "informações do modelo de equipamento <i>'{name}'</i> no "
            . "fabricante <i>'{brandName}'</i>.Erro interno.",
            [ 'name'  => $equipmentModel['name'],
              'brandName'  => $equipmentModel['equipmentbrandname'] ]
          );
        }
      }
    } else {
      // Carrega os dados atuais
      $this->validator->setValues($equipmentModel);
    }
    
    // Exibe um formulário para edição de um modelo de equipamento
    
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ERP\Home')
    );
    $this->breadcrumb->push('Parametrização', '');
    $this->breadcrumb->push('Equipamentos', '');
    $this->breadcrumb->push('Modelos',
      $this->path('ERP\Parameterization\Equipments\Models')
    );
    $this->breadcrumb->push('Editar',
      $this->path('ERP\Parameterization\Equipments\Models\Edit', ['equipmentModelID' => $equipmentModelID])
    );
    
    // Registra o acesso
    $this->info("Acesso à edição do modelo de equipamento '{name}' da "
      . "marca '{brandName}' no contratante '{contractor}'.",
      [ 'name'       => $equipmentModel['name'],
        'brandName'  => $equipmentModel['equipmentbrandname'],
        'contractor' => $contractor->name ]
    );
    
    // Renderiza a página
    return $this->render($request, $response,
      'erp/parameterization/equipments/models/equipmentmodel.twig',
      [
        'formMethod' => 'PUT',
        'simcardTypes' => $simcardTypes,
        'protocols' => $protocols,
        'protocolVariants' => $protocolVariants
      ])
    ;
  }
  
  /**
   * Remove o modelo de equipamento.
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
  public function delete(
    Request $request,
    Response $response,
    array $args
  ): Response
  {
    // Registra o acesso
    $this->debug("Processando à remoção de modelo de equipamento.");
    
    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();

    // Recupera o ID
    $equipmentModelID = $args['equipmentModelID'];

    try
    {
      // Recupera as informações do modelo de equipamento
      $equipmentModel = EquipmentModel::where('contractorid',
            '=', $contractor->id
          )
        ->where('equipmentmodelid', '=', $equipmentModelID)
        ->firstOrFail()
      ;
      
      // Agora apaga o modelo de equipamento
      $equipmentModel->delete();
      
      // Registra o sucesso
      $this->info("O modelo de equipamento '{name}' do contratante "
        . "'{contractor}' foi removido com sucesso.",
        [ 'name' => $equipmentModel->name,
          'contractor' => $contractor->name ]
      );
      
      // Informa que a remoção foi realizada com sucesso
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getParams(),
            'message' => "Removido o modelo de equipamento "
              . "{$equipmentModel->name}",
            'data' => "Delete"
          ])
      ;
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar o modelo de equipamento "
        . "código {equipmentModelID} para remoção.",
        [ 'equipmentModelID' => $equipmentModelID ]
      );
      
      $message = "Não foi possível localizar o modelo de equipamento "
        . "para remoção."
      ;
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível remover as informações do modelo "
        . "de equipamento ID {id} no contratante '{contractor}'. "
        . "Erro interno no banco de dados: {error}.",
        [ 'id'  => $equipmentModelID,
          'contractor' => $contractor->name,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível remover o modelo de equipamento. "
        . "Erro interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível remover as informações do modelo "
        . "de equipamento ID {id} no contratante '{contractor}'. "
        . "Erro interno: {error}.",
        [ 'id'  => $equipmentModelID,
          'contractor' => $contractor->name,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível remover o modelo de equipamento. "
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
  
  /**
   * Recupera a relação dos modelos de equipamentos em formato JSON no
   * padrão dos campos de preenchimento automático.
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
  public function getAutocompletionData(
    Request $request,
    Response $response
  ): Response
  {
    $this->debug("Relação de modelos de equipamentos para "
      . "preenchimento automático despachada."
    );

    // --------------------------[ Recupera os dados requisitados ]-----

    // Recupera os dados da requisição
    $postParams    = $request->getParsedBody();

    // Recupera os dados do contratante
    $contractor    = $this->authorization->getContractor();
    
    // Lida com as informações provenientes do searchbox
    $name          = addslashes($postParams['searchTerm']);

    // Determina os limites e parâmetros da consulta
    $start         = 0;
    $length        = $postParams['limit'];
    $ORDER         = 'name ASC';
    
    // Registra o acesso
    $this->debug("Acesso aos dados de preenchimento automático dos "
      . "modelos de equipamentos que contenham '{name}'",
      [ 'name' => $name ]
    );
    
    try
    {
      // Localiza os modelos de equipamentos na base de dados
      $message = "Modelos de equipamentos cujo nome contém '{$name}'";
      $equipmentModels = EquipmentModel::join('equipmentbrands',
            'equipmentmodels.equipmentbrandid', '=',
            'equipmentbrands.equipmentbrandid'
          )
        ->join('simcardtypes', 'equipmentmodels.simcardtypeid',
            '=', 'simcardtypes.simcardtypeid'
          )
        ->whereRaw("public.unaccented(equipmentmodels.name) ILIKE "
            . "public.unaccented(E'%{$name}%')"
          )
        ->skip($start)
        ->take($length)
        ->orderByRaw($ORDER)
        ->get([
            'equipmentmodels.equipmentmodelid AS id',
            'equipmentmodels.name',
            'equipmentmodels.maxsimcards',
            'equipmentmodels.simcardtypeid',
            'simcardtypes.name AS simcardtypename',
            'equipmentbrands.equipmentbrandid AS brandid',
            'equipmentbrands.name AS brandname'
          ])
      ;
      
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getQueryParams(),
            'message' => $message,
            'data' => $equipmentModels
          ])
      ;
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module} para preenchimento automático. Erro interno no "
        . "banco de dados: {error}.",
        [ 'module' => 'modelos de equipamentos',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de modelos "
        . "de equipamentos para preenchimento automático. Erro interno "
        . "no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module} para preenchimento automático. Erro interno: "
        . "{error}.",
        [ 'module' => 'modelos de equipamentos',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de modelos "
        . "de equipamentos para preenchimento automático. Erro "
        . "interno.";
    }
    
    // Retorna o erro
    return $response
      ->withHeader('Content-type', 'application/json')
      ->withJson([
          'result' => 'NOK',
          'params' => $request->getQueryParams(),
          'message' => "Não foi possível localizar modelos de "
            . "equipamentos cujo nome contém '$name'",
          'data' => [ ],
        ])
    ;
  }
}
