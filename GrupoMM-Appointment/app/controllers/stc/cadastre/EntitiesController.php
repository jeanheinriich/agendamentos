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
 * O controlador do gerenciamento das entidades do sistema. Uma entidade
 * pode ser qualquer uma pessoa (física ou jurídica ) do sistema, tais
 * como clientes e fornecedores.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Controllers\STC\Cadastre;

use App\Models\Entity;
use App\Models\EntityType;
use App\Models\Subsidiary;
use Core\Controllers\Controller;
use Core\Controllers\QueryTrait;
use Exception;
use Illuminate\Database\QueryException;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\Stream;

class EntitiesController
  extends Controller
{
  /**
   * Os métodos para manipular queries.
   */
  use QueryTrait;

  /**
   * Recupera a relação das entidades em formato JSON no padrão dos
   * campos de preenchimento automático. As entidades devem ser de um
   * tipo especificado (clientes ou fornecedores).
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
    $this->debug("Relação de entidades para preenchimento automático "
      . "despachada."
    );

    // --------------------------[ Recupera os dados requisitados ]-----

    // Recupera os dados da requisição
    $postParams = $request->getParsedBody();

    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();
    
    // Lida com as informações provenientes do searchbox
    $name       = $postParams['searchTerm'];
    $type       = $postParams['type'];
    if ($type === 'subsidiary') {
      // Recuperamos o ID da empresa à qual pertencem as unidades/filiais
      $entityID = $postParams['entityID'];
    } elseif ($type === 'ownerdata') {
      // Recupera os dados do proprietário
      $customerID   = $postParams['customerID'];
      $subsidiaryID = $postParams['customerID'];
    }
    
    // Determina os limites e parâmetros da consulta
    $start  = 0;
    $length = $postParams['limit'];
    $ORDER  = 'name ASC';

    // Registra o acesso
    $typeNames = [
      'customer'   => 'cliente(s)',
      'supplier'   => 'fornecedor(es)',
      'subsidiary' => 'unidade(s)/filial(is)',
      'ownerdata'  => 'dados do proprietário',
    ];

    if ($type === 'ownerdata') {
      $this->debug("Acesso aos dados de preenchimento automático dos "
        . "dados do proprietário de um veículo"
      );
    } else {
      $this->debug("Acesso aos dados de preenchimento automático de "
        . "{type} que contenha(m) '{name}'",
        [ 'type' => $typeNames[$type],
          'name' => $name ]
      );
    }
    
    try
    {
      if ($type === 'ownerdata') {
        // Localiza os dados do proprietário de um veículo na base de
        // dados

        // Monta a consulta
        $contractorID = $contractor->id;
        $entities = Entity::join('subsidiaries', 'entities.entityid',
              '=', 'subsidiaries.entityid'
            )
          ->join('entitiestypes', 'entities.entitytypeid',
              '=', 'entitiestypes.entitytypeid'
            )
          ->join('documenttypes', 'subsidiaries.regionaldocumenttype',
              '=', 'documenttypes.documenttypeid'
            )
          ->join('cities', 'subsidiaries.cityid', '=', 'cities.cityid')
          ->where('entities.contractorid', $contractorID)
          ->where('entities.entityid', $customerID)
          ->where('subsidiaries.subsidiaryid', $subsidiaryID)
          ->get([
              'entities.name AS entityname',
              'entities.entitytypeid',
              'entitiestypes.name AS entitytypename',
              'entitiestypes.juridicalperson',
              'subsidiaries.subsidiaryid AS subsidiaryid',
              'subsidiaries.name AS subsidiaryname',
              'subsidiaries.regionaldocumenttype',
              'documenttypes.name AS regionaldocumentname',
              'subsidiaries.regionaldocumentnumber',
              'subsidiaries.regionaldocumentstate',
              'subsidiaries.nationalregister',
              'subsidiaries.address',
              'subsidiaries.district',
              'subsidiaries.postalcode',
              'subsidiaries.cityid',
              'cities.name AS cityname',
              'cities.state AS state',
              'subsidiaries.email',
              'subsidiaries.phonenumber'
            ])[0]
          ->toArray()
        ;
      } elseif ($type === 'subsidiary') {
        // Localiza as unidades/filiais de uma entidade na base de dados

        // Monta a consulta
        $contractorID = $contractor->id;
        $entities = Entity::join('subsidiaries', 'entities.entityid',
              '=', 'subsidiaries.entityid'
            )
          ->join('cities', 'subsidiaries.cityid', '=', 'cities.cityid')
          ->where('entities.contractorid', $contractorID)
          ->where('entities.entityid', $entityID)
          ->whereRaw("public.unaccented(subsidiaries.name) "
              . "ILIKE public.unaccented('%{$name}%')"
            )
          ->skip($start)
          ->take($length)
          ->orderByRaw($ORDER)
          ->get([
              'subsidiaries.subsidiaryid AS id',
              'subsidiaries.name',
              'subsidiaries.nationalregister',
              'cities.name AS city',
              'cities.state AS state'
            ])
        ;
      } else {
        // Localiza as entidades na base de dados (clientes e/ou
        // fornecedores) e a sua primeira unidade/filial

        // Determina o ID do contratante
        $contractorID = $contractor->id;

        // Conforme o tipo de entidade que foi requisitada, realiza as
        // devidas filtragens
        switch ($type) {
          case 'customer':
          case 'supplier':
          case 'contractor':
            $WHERE = "entities.{$type} = true";

            break;
          default:
            $WHERE = "((contractor = true) OR (customer = true) "
              . "OR (supplier = true))"
            ;
            $this->debug("Usou o padrão pois type contém {type}",
              [ 'type' => $type ]
            );
        }

        // Conforme o nível de permissão do usuário, realiza as devidas
        // filtragens
        $level = $this->authorization->getUser()->groupid;
        switch ($level) {
          case 1:
          case 2:
            // Administrador ERP e Administrador Contratante lidam com
            // todas as entidades do respectivo contratante, então não
            // precisa aplicar filtros
            break;
          case 3:
          case 4:
            // Atendente e Operador não podem adicionar itens ao próprio
            // contratante, apenas as clientes e fornecedores.
            $WHERE .= " AND contractor = false";

            break;
          case 5:
            // Técnicos podem pertencer ao contratante e/ou a um
            // prestador de serviços. Quando pertencerem à um prestador
            // de serviços então somente podem lidar com a própria
            // empresa para a qual trabalham
            if ( $this->authorization->getUser()->entityid !==
                 $contractorID) {
              $WHERE .= " AND entities.entityid = "
                . $this->authorization->getUser()->entityid
              ;
            }
            break;
          default:
            // Cliente, então precisa aplicar filtro pela empresa do
            // próprio cliente
            $WHERE .= " AND entities.entityid = "
              . $this->authorization->getUser()->entityid
            ;
            
            break;
        }

        // Monta a consulta
        $sql = "SELECT DISTINCT ON (entities.name)
                       entities.entityid AS id,
                       entities.name,
                       entities.tradingname,
                       subsidiaries.subsidiaryid,
                       subsidiaries.name AS subsidiaryname
                  FROM erp.entities
                 INNER JOIN erp.subsidiaries USING (entityid)
                 WHERE entities.contractorid = {$contractorID}
                   AND {$WHERE}
                   AND public.unaccented(entities.name) ILIKE public.unaccented('%{$name}%')
                 ORDER BY entities.name, subsidiaries.subsidiaryid ASC;";
        $entities = $this->DB->select($sql);
      }
      
      if ($type === 'ownerdata') {
        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'result' => 'OK',
              'params' => $request->getQueryParams(),
              'message' => ucfirst($typeNames[$type]) . " cuja ID é "
                . "'{$customerID}'",
              'data' => $entities
            ])
        ;
      } else {
        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'result' => 'OK',
              'params' => $request->getQueryParams(),
              'message' => ucfirst($typeNames[$type]) . " cujo nome "
                . "contém '{$name}'",
              'data' => $entities
            ])
        ;
      }
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module} para preenchimento automático. Erro interno no "
        . "banco de dados: {error}.",
        [ 'module' => $typeNames[$type],
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de "
        . $typeNames[$type] . " para preenchimento automático. "
        . "Erro interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module} para preenchimento automático. Erro interno: "
        . "{error}.",
        [ 'module' => $typeNames[$type],
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de "
        . $typeNames[$type] . " para preenchimento automático. "
        . "Erro interno."
      ;
    }
    
    // Retorna o erro
    if ($type === 'ownerdata') {
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'NOK',
            'params' => $request->getQueryParams(),
            'message' => "Não foi possível localizar os "
              . $typeNames[$type] . " cujo nome contém '{$name}'",
            'data' => $entities
          ])
      ;
    } else {
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'NOK',
            'params' => $request->getQueryParams(),
            'message' => "Não foi possível localizar "
              . $typeNames[$type] . " cuja ID é '{$customerID}'",
            'data' => $entities
          ])
      ;
    }
  }

  /**
   * Obtém a logomarca do contratante para locais onde o fundo é normal,
   * ou seja, fundo claro.
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
  public function getNormalLogo(Request $request, Response $response,
    array $args)
  {
    // Recupera da configuração o UUID do contratante. Verificamos
    // primeiramente se temos uma UUID fornecida com os argumentos
    $contractorUUID = $args['UUID'];
    
    // Recupera o local de armazenamento das imagens
    $logoDirectory = $this->container['settings']['storage']['images'];
    $searchText    = $logoDirectory . DIRECTORY_SEPARATOR
      . "Logo_{$contractorUUID}_N.*"
    ;

    $files = glob($searchText);
    if (count($files) > 0) {
      // Determina informações do arquivo
      $imageFile = $files[0];
      $imageData = getimagesize($imageFile);
      if ( !empty($imageData[2]) ) {
        // Recupera o tipo mime do arquivo
        $mimeType = image_type_to_mime_type($imageData[2]);
      } else {
        // Atribui um tipo mime inválido
        $mimeType = "application/octet-stream";
      }
    } else {
      // O arquivo não foi localizado, então retorna um arquivo vazio
      // Determina informações do arquivo
      $resourcesDir = $this->app->getPublicDir()
        . DIRECTORY_SEPARATOR . 'resources'
      ;
      $imageFile = $resourcesDir . DIRECTORY_SEPARATOR
        . "unknown.png"
      ;
      $imageData = getimagesize($imageFile);
      if ( !empty($imageData[2]) ) {
        // Recupera o tipo mime do arquivo
        $mimeType = image_type_to_mime_type($imageData[2]);
      } else {
        // Atribui um tipo mime inválido
        $mimeType = "application/octet-stream";
      }
    }

    // Retorna o conteúdo do arquivo
    $size = filesize($imageFile);
    $fileHandle = fopen($imageFile, 'rb');
    $stream = new Stream($fileHandle);

    // Determina o tempo de cache para 7 dias
    $maxAge = 60*60*24*7;

    // Retorna a imagem gerada
    return $response
      ->withBody($stream)
      ->withHeader('Content-Type', $mimeType)
      ->withHeader('Content-Length', $size)
      ->withHeader('Content-Disposition', "name='{$imageFile}'")
      ->withHeader('Cache-Control', "max-age={$maxAge}")
      ->withHeader('Expires', gmdate(DATE_RFC1123, time() + $maxAge))
      ->withHeader('Last-Modified', gmdate('D, d M Y H:i:s',
          filemtime($imageFile)) . 'GMT'
        )
    ;
  }

  /**
   * Obtém a logomarca do contratante para locais onde o fundo é
   * invertido, ou seja, fundo escuro.
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
  public function getInvertedLogo(Request $request, Response $response,
    array $args)
  {
    // Recupera da configuração o UUID do contratante. Verificamos
    // primeiramente se temos uma UUID fornecida com os argumentos
    $contractorUUID = $args['UUID'];
    
    // Recupera o local de armazenamento das imagens
    $logoDirectory = $this->container['settings']['storage']['images'];
    $searchText    = $logoDirectory . DIRECTORY_SEPARATOR
      . "Logo_{$contractorUUID}_I.*"
    ;

    $files = glob($searchText);
    if (count($files) > 0) {
      // Determina informações do arquivo
      $imageFile = $files[0];
      $imageData = getimagesize($imageFile);
      if ( !empty($imageData[2]) ) {
        // Recupera o tipo mime do arquivo
        $mimeType = image_type_to_mime_type($imageData[2]);
      } else {
        // Atribui um tipo mime inválido
        $mimeType = "application/octet-stream";
      }
    } else {
      // O arquivo não foi localizado, então retorna um arquivo vazio
      // Determina informações do arquivo
      $resourcesDir = $this->app->getPublicDir()
        . DIRECTORY_SEPARATOR . 'resources'
      ;
      $imageFile = $resourcesDir . DIRECTORY_SEPARATOR
        . "unknown.png"
      ;
      $imageData = getimagesize($imageFile);
      if ( !empty($imageData[2]) ) {
        // Recupera o tipo mime do arquivo
        $mimeType = image_type_to_mime_type($imageData[2]);
      } else {
        // Atribui um tipo mime inválido
        $mimeType = "application/octet-stream";
      }
    }

    // Retorna o conteúdo do arquivo
    $size = filesize($imageFile);
    $fileHandle = fopen($imageFile, 'rb');
    $stream = new Stream($fileHandle);

    // Determina o tempo de cache para 7 dias
    $maxAge = 60*60*24*7;

    // Retorna a imagem gerada
    return $response
      ->withBody($stream)
      ->withHeader('Content-Type', $mimeType)
      ->withHeader('Content-Length', $size)
      ->withHeader('Content-Disposition', "name='{$imageFile}'")
      ->withHeader('Cache-Control', "max-age={$maxAge}")
      ->withHeader('Expires', gmdate(DATE_RFC1123, time() + $maxAge))
      ->withHeader('Last-Modified', gmdate('D, d M Y H:i:s',
          filemtime($imageFile)) . 'GMT'
        );
  }
}
