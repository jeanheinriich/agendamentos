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
 * O controlador do fechamento mensal, que determina os valores a serem
 * cobrados de cada cliente, antes de se emitir o faturamento.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Controllers\ERP\Financial;

use App\Models\Billing;
use App\Models\Contract;
use App\Models\Invoice;
use Carbon\Carbon;
use Core\Controllers\Controller;
use Core\Controllers\HandleFileTrait;
use Core\Controllers\QueryTrait;
use Core\Helpers\FormatterTrait;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Mpdf\Mpdf;
use NumberFormatter;
use Respect\Validation\Validator as V;
use RuntimeException;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\Stream;

class MonthlyCalculationsController
  extends Controller
{
  /**
   * Os métodos para manipular queries.
   */
  use QueryTrait;

  /**
   * Os métodos para manipular o recebimento de arquivos. Necessário
   * para se obter a logomarca do contratante no PDF
   */
  use HandleFileTrait;

  /**
   * As funções de formatação especiais
   */
  use FormatterTrait;

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
      'customername' => V::notBlank()
        ->length(2, 100)
        ->setName('Nome do cliente'),
      'customerid' => V::notBlank()
        ->intVal()
        ->setName('ID do cliente'),
      'subsidiaryname' => V::notBlank()
        ->length(2, 100)
        ->setName('Unidade/filial/titular responsável'),
      'subsidiaryid' => V::notBlank()
        ->intVal()
        ->setName('ID da unidade/filial/titular'),
      'contractid' => V::notBlank()
        ->intVal()
        ->setName('Nº do contrato'),
      'installationid' => V::notBlank()
        ->intVal()
        ->setName('Nº da instalação'),
      'name' => V::notBlank()
        ->length(1, 60)
        ->setName('Descrição do lançamento'),
      'contractchargeid' => V::intVal()
        ->setName('Nº do valor cobrado no contrato'),
      'installmenttypeid' => V::intVal()
        ->setName('Nº do parcelamento disponível'),
      'billingdate' => V::notEmpty()
        ->date('d/m/Y')
        ->setName('Data do lançamento'),
      'value' => V::numericValue()
        ->minimumValue('0,00')
        ->setName('Valor cobrado'),
      'numberofinstallments' => V::intVal()
        ->setName('Quantidade de parcelas'),
      'installmentvalue' => V::numericValue()
        ->minimumValue('0,00')
        ->setName('Valor da parcela')
    ];

    if ($addition) {
      // Ajusta as regras para adição de um novo lançamento
      
      // Retiramos as regras para campos que não fazem parte desta parte
      // da edição
      unset($validationRules['billingid']);
    } else {
      unset($validationRules['installmenttypeid']);
      unset($validationRules['installmentvalue']);
      $validationRules['contractnumber'] = V::notEmpty()
        ->setName('Número do contrato');
      $validationRules['installationnumber'] = V::notEmpty()
        ->setName('Número da instalação');
      $validationRules['installmentnumber'] = V::intval()
        ->setName('Número da parcela');
    }

    return $validationRules;
  }

  /**
   * Recupera as informações de contratos.
   *
   * @param int $customerID
   *   A ID do cliente para o qual desejamos obter os contratos
   *   disponíveis
   * @param int $subsidiaryID
   *   A ID da unidade/filial do cliente para o qual desejamos obter os
   *   contratos disponíveis
   *
   * @return Collection
   *   A matriz com as informações de contratos
   *
   * @throws RuntimeException
   *   Em caso de não termos contratos
   */
  protected function getContracts(
    int $customerID,
    int $subsidiaryID
  ): Collection
  {
    try {
      // Recupera as informações de contratos deste cliente
      $contracts = Contract::where('customerid', '=', $customerID)
        ->where('subsidiaryid', '=', $subsidiaryID)
        ->get([
            "contractid AS id",
            $this->DB->raw('getContractNumber(createdat) AS number'),
            $this->DB->raw("trim(to_char(monthprice, '9999999999D99')) AS monthprice"),
            $this->DB->raw(''
              . "CASE"
              . "  WHEN signaturedate IS NULL THEN 'Não assinado'"
              . "  ELSE 'Assinado em ' || to_char(signaturedate, 'DD/MM/YYYY') "
              . "END AS description")
          ])
      ;

      if ( $contracts->isEmpty() ) {
        throw new Exception("Não temos nenhum contrato cadastrado");
      }
    }
    catch (Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível obter as informações de contratos. "
        . "Erro: {error}.",
        [ 'error' => $exception->getMessage() ]
      );

      throw new RuntimeException("Não foi possível obter os contratos");
    }

    return $contracts;
  }

  /**
   * Recupera as informações de instalações de um contrato.
   *
   * @param int $contractID
   *   A ID do contrato para o qual desejamos obter as instalações
   *   disponíveis
   *
   * @return array
   *   A matriz com as informações de instalações
   *
   * @throws RuntimeException
   *   Em caso de não termos instalações
   */
  protected function getInstallations(
    int $contractID
  ): array
  {
    try {
      // Recupera as informações de instalações, incluindo instalações
      // que estejam encerradas e/ou de contratos suspensos
      $sql = "SELECT installationID AS id,
                     installationNumber,
                     plate,
                     to_char(startDate, 'DD/MM/YYYY') AS startDate,
                     noTracker
                FROM erp.getInstallationsData({$contractID}, TRUE, TRUE, 0);"
      ;
      $installationsData = $this->DB->select($sql);
      $installations = [];
      
      foreach ($installationsData AS $installation) {
        if ($installation->startdate) {
          if ($installation->notracker) {
            $description = 'Sem rastreador';
          } else {
            $description = 'Instalado em ' . $installation->startdate;
          }
        } else {
          $description = 'Não instalado';
        }

        $installations[] = [
          'id' => $installation->id,
          'number' => $installation->installationnumber,
          'plate' => $installation->plate,
          'description' => $description
        ];
      }
    }
    catch (Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível obter as informações de "
        . "instalações. Erro: {error}.",
        [ 'error' => $exception->getMessage() ]
      );

      throw new RuntimeException("Não foi possível obter as instalações");
    }

    return $installations;
  }

  /**
   * Exibe a página inicial do gerenciamento dos fechamentos mensais.
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
    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();

    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ERP\Home')
    );
    $this->breadcrumb->push('Financeiro', '');
    $this->breadcrumb->push('Fechamentos',
      $this->path('ERP\Financial\MonthlyCalculations')
    );
    
    // Registra o acesso
    $this->info("Acesso ao gerenciamento dos fechamentos mensais.");
    
    // Recupera os dados da sessão
    $monthlyCalculation = $this->session->get('monthlycalculation',
      [ 'searchField' => 'plate',
        'searchValue' => '',
        'customer' => [
          'id' => 0,
          'name' => '',
          'subsidiaryID' => 0
        ],
        'displayStart' => 0
      ]
    );

    // Determina se temos um fechamento em execução
    $amountOfInvoices = Invoice::where('contractorid', $contractor->id)
      ->where('underanalysis', true)
      ->count()
    ;
    $this->info("Temos {$amountOfInvoices} faturas abertas.");
    $underAnalysis = ($amountOfInvoices > 0);
    $baseDate = Carbon::now()->startOfMonth()->format('d/m/Y');

    // Renderiza a página
    return $this->render($request, $response,
      'erp/financial/monthlycalculations/monthlycalculations.twig',
      [ 'monthlycalculation' => $monthlyCalculation,
        'baseDate' => $baseDate,
        'underAnalysis' => $underAnalysis ])
    ;
  }
  
  /**
   * Recupera a relação dos valores fechados em cada instalação de cada
   * cliente para o mês indicado em formato JSON.
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
    $this->debug("Acesso à relação de fechamentos por instalação.");
    
    // --------------------------[ Recupera os dados requisitados ]-----

    // Recupera os dados da requisição
    $postParams = $request->getParsedBody();
    
    // Recupera o ID do contratante
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
    $searchField  = $postParams['searchField'];
    $searchValue  = $postParams['searchValue'];
    $customerID   = $postParams['customerID'];
    $customerName = $postParams['customerName'];
    $subsidiaryID = array_key_exists('subsidiaryID', $postParams)
      ? intval($postParams['subsidiaryID'])
      : 0
    ;
    
    // Seta os valores da última pesquisa na sessão
    $this->session->set('monthlycalculation',
      [ 'searchField' => $searchField,
        'searchValue' => $searchValue,
        'customer' => [
          'id' => $customerID,
          'name' => $customerName,
          'subsidiaryID' => $subsidiaryID
        ],
        'displayStart' => $start
      ]
    );

    // Corrige o escape dos campos
    $searchValue = addslashes($searchValue);
    
    try
    {
      // Formata o ordenamento dos campos
      $ORDER = $this->formatOrderBy($orderBy, $orderDir);

      // Garante que tenhamos um ID válido dos campos de pesquisa
      $customerID = $customerID?$customerID:0;

      // Realiza a consulta
      $sql = "SELECT B.billingID AS id,
                     B.customerID,
                     B.customerName,
                     B.subsidiaryID,
                     B.subsidiaryName,
                     B.contractID,
                     B.contractNumber,
                     B.planID,
                     B.planName,
                     B.dueDay,
                     B.installationID,
                     B.installationNumber,
                     B.vehicleID,
                     B.plate,
                     to_char(B.billingDate, 'DD/MM/YYYY') AS billingDate,
                     B.name,
                     B.billingValue,
                     B.installmentNumber,
                     B.numberOfInstallments,
                     B.granted,
                     B.reasonforgranting,
                     B.renegotiated,
                     B.renegotiationID,
                     B.fullcount
                FROM erp.getBillingsData({$contractorID}, {$customerID},
                  {$subsidiaryID}, NULL, '{$searchValue}', '{$searchField}',
                  TRUE, '{$ORDER}', {$start}, {$length}) AS B;"
      ;
      $monthlycalculations = $this->DB->select($sql);

      if (count($monthlycalculations) > 0) {
        $rowCount = $monthlycalculations[0]->fullcount;
        
        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'draw' => $draw,
              'recordsTotal' => $rowCount,
              'recordsFiltered' => $rowCount,
              'data' => $monthlycalculations
            ])
        ;
      } else {
        if (empty($searchValue)) {
          $error = "Não temos um fechamento em execução.";
        } else {
          switch ($searchField) {
            case 'contractNumber':
              $error = "Não temos lançamentos cadastrados cujo número do "
                . "contrato contém <i>{$searchValue}</i>."
              ;
              
              break;
            case 'installationNumber':
              $error = "Não temos lançamentos cadastrados que possuam "
                . "uma instalação cujo número contém "
                . "<i>{$searchValue}</i>."
              ;
              
              break;
            default:
              $error = "Não temos lançamentos cadastrados cujas "
                . "instalações consta o veículo placa "
                . "<i>{$searchValue}</i>."
              ;

              break;
          }
        }
      }
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno no banco de dados: {error}.",
        [ 'module' => 'lançamentos',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de "
        . "lançamentos. Erro interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno: {error}.",
        [ 'module' => 'lançamentos',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de "
        . "lançamentos. Erro interno."
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
   * Inicia o processo de fechamento de um período para cobrança.
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
  public function start(
    Request $request,
    Response $response,
    array $args
  ): Response
  {
    // Verifica se estamos modificando os dados
    if ($request->isPut()) {
      // Registra o acesso
      $this->debug("Processando o início do fechamento de valores.");
      
      // Recupera os dados do contratante
      $contractor = $this->authorization->getContractor();

      // Recupera os dados da requisição
      $postParams = $request->getParsedBody();

      // Recupera a data de início e término do período
      $baseDate = $postParams['baseDate'];
      $startDate = Carbon::createFromFormat('d/m/Y', $baseDate)
        ->locale('pt_BR')
      ;
      $endDate = $startDate
        ->copy()
        ->addMonth()
        ->subDay()
      ;

      try
      {
        // Iniciamos a transação
        $this->DB->beginTransaction();

        // Executamos a função responsável por iniciar o acerto
        $userID = $this->authorization->getUser()->userid;
        $sql = "SELECT erp.startMonthlyCalculations('"
          . $startDate->format('Y-m-d')
          . "'::Date, {$contractor->id}, FALSE, {$userID}) AS result;";
        $process = $this->DB->select($sql);

        if ($process[0]->result) {
          // Efetiva a transação
          $this->DB->commit();

          $this->info("Iniciado o fechamento de valores para o período "
            . "de {startDate} à {endDate} dos clientes do contratante "
            . "'{contractor}'.",
            [ 'startDate' => $startDate->format('d/m/Y'),
              'endDate' => $endDate->format('d/m/Y'),
              'contractor' => $contractor->name ]
          );
        
          $message = "Iniciado o fechamento dos valores dos clientes "
            . "para o período de " . $startDate->format('d/m/Y') . " à "
            . $endDate->format('d/m/Y') . "."
          ;

          return $response
            ->withHeader('Content-type', 'application/json')
            ->withJson([
                'result' => 'OK',
                'params' => $request->getQueryParams(),
                'message' => $message,
                'data' => null
              ])
          ;
        } else {
          // Reverte (desfaz) a transação
          $this->DB->rollBack();

          // Registra o erro
          $this->error("Não foi possível iniciar o fechamento de "
            . "valores para o período de {startDate} à {endDate} dos "
            . "clientes do contratante '{contractor}'. Não temos "
            . "instalações habilitadas neste período.",
            [ 'startDate' => $startDate->format('d/m/Y'),
              'endDate' => $endDate->format('d/m/Y'),
              'contractor' => $contractor->name ]
          );
        
          $message = "Não foi possível iniciar o fechamento do período "
            . "de " . $startDate->format('d/m/Y') . " à "
            . $endDate->format('d/m/Y') . ". Não temos instalações "
            . "habilitadas neste período."
          ;
        }
      }
      catch(QueryException $exception)
      {
        // Reverte (desfaz) a transação
        $this->DB->rollBack();

        // Registra o erro
        $this->info("Não foi possível iniciar o fechamento de valores "
          . "para o período de {startDate} à {endDate} dos clientes do "
          . "contratante '{contractor}'. Erro interno no banco de "
          . "dados: {error}.",
          [ 'startDate' => $startDate->format('d/m/Y'),
            'endDate' => $endDate->format('d/m/Y'),
            'contractor' => $contractor->name,
            'error' => $exception->getMessage() ]
        );
        
        $message = "Não foi possível iniciar o fechamento do período "
          . "de " . $startDate->format('d/m/Y') . " à "
          . $endDate->format('d/m/Y') . ". Erro interno no banco de "
          . "dados."
        ;
      }
      catch(Exception $exception)
      {
        // Reverte (desfaz) a transação
        $this->DB->rollBack();

        // Registra o erro
        $this->info("Não foi possível iniciar o fechamento de valores "
          . "para o período de {startDate} à {endDate} dos clientes do "
          . "contratante '{contractor}'. Erro interno: {error}.",
          [ 'startDate' => $startDate->format('d/m/Y'),
            'endDate' => $endDate->format('d/m/Y'),
            'contractor' => $contractor->name,
            'error' => $exception->getMessage() ]
        );
        
        $message = "Não foi possível iniciar o fechamento do período "
          . "de " . $startDate->format('d/m/Y') . " à "
          . $endDate->format('d/m/Y') . ". Erro interno."
        ;
      }
    } else {
      $message = "Não foi encontrado nenhum período a ser apurado.";
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
   * Gera um PDF para impressão do fechamento em análise para
   * conferência.
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
  public function getPDF(
    Request $request,
    Response $response,
    array $args
  ): Response
  {
    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();

    // Registra o acesso
    $this->debug("Processando à geração de PDF com as informações do "
      . "fechamento para conferência."
    );

    try
    {
      // Estamos iniciando aqui a determinação dos tempos de execução de
      // cada parte do processo
      $timeStart = microtime(true);

      $contractorID = $contractor->id;
      $this->info("Iniciando leitura dos dados");
      $sql = "SELECT erp.getmonthlycalculations({$contractorID}) AS data;";
      $invoices = $this->DB->select($sql);

      // Determinamos o tempo de execução desta parte do script e
      // registramos
      $timeEnd = microtime(true);
      $executionTime = $timeEnd - $timeStart;
      $this->info("Tempo total de execução da leitura dos dados: "
        . "{time} seg", [
          'time' => sprintf("%01.15f", $executionTime) ]
      );
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar faturas em processo de "
        . "fechamento. {error}", [ 'error' => $exception->getMessage() ]
      );
      
      // Alerta o usuário
      $this->flash("error", "Não existe nenhum fechamento em andamento.");
      
      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [ 'routeName' => 'ERP\Financial\MonthlyCalculations' ]
      );
      
      // Redireciona para a página de gerenciamento de contratos
      return $this->redirect($response,
        'ERP\Financial\MonthlyCalculations'
      );
    }

    // Estamos iniciando aqui a determinação dos tempos de execução de
    // cada parte do processo
    $timeStart = microtime(true);

    // Renderiza a página para poder converter em PDF
    //var_dump($invoices); exit();
    $firstRow = json_decode($invoices[0]->data);
    $referenceMonthYear = $firstRow->referencemonthyear;
    $parts = explode('/', $referenceMonthYear);
    $monthNames = [
      1 => 'Jan',
      2 => 'Fev',
      3 => 'Mar',
      4 => 'Abr',
      5 => 'Mai',
      6 => 'Jun',
      7 => 'Jul',
      8 => 'Ago',
      9 => 'Set',
      10 => 'Out',
      11 => 'Nov',
      12 => 'Dez'
    ];

    $title = "Simulação do fechamento  - " . $monthNames[ intval($parts[0]) ]
      . ' / ' . $parts[1]
    ;
    $PDFFileName = "Fechamento_{$referenceMonthYear}.pdf";
    $page = $this->renderPDF(
      'erp/financial/monthlycalculations/PDFmonthlycalculations.twig',
      [ 'invoices' => $invoices,
        'title' => $title ]
    );

    // Determinamos o tempo de execução desta parte do script e
    // registramos
    $timeEnd = microtime(true);
    $executionTime = $timeEnd - $timeStart;
    $this->info("Tempo total de execução da conversão para HTML: "
      . "{time} seg", [
        'time' => sprintf("%01.15f", $executionTime) ]
    );

    // Estamos iniciando aqui a determinação dos tempos de execução de
    // cada parte do processo
    $timeStart = microtime(true);

    // Renderizamos em PDF
    $logo   = $this->getContractorLogo($contractor->uuid, 'normal');
    $header = $this->renderPDFHeader($title, $logo);
    $footer = $this->renderPDFFooter();

    // Cria um novo mPDF e define a página no tamanho A4 com orientação
    // portrait
    $mpdf = new Mpdf($this->generatePDFConfig('A4', 'Portrait'));

    // Permite a conversão (opcional)
    $mpdf->allow_charset_conversion=true;

    // Permite a compressão
    $mpdf->SetCompression(true);

    // Para acelerar, usa bordas simples
    $mpdf->simpleTables = false;

    // Define os metadados do documento
    $mpdf->SetTitle($title);
    $mpdf->SetAuthor($this->authorization->getUser()->name);
    $mpdf->SetSubject('Simulação de fechamento ' . $referenceMonthYear);
    $mpdf->SetCreator('TrackerERP');

    // Define os cabeçalhos e rodapés
    $mpdf->SetHTMLHeader($header);
    $mpdf->SetHTMLFooter($footer);

    // Seta modo tela cheia
    $mpdf->SetDisplayMode('fullpage');
    $mpdf->showImageErrors = false;
    $mpdf->debug = false;

    // Inclui o conteúdo
    $mpdf->WriteHTML($page);

    // Determinamos o tempo de execução desta parte do script e
    // registramos
    $timeEnd = microtime(true);
    $executionTime = $timeEnd - $timeStart;
    $this->info("Tempo total de execução da conversão para PDF: "
      . "{time} seg", [
        'time' => sprintf("%01.15f", $executionTime) ]
    );

    // Envia o PDF para o browser no modo Inline
    $stream = fopen('php://memory','r+');
    ob_start();
    $mpdf->Output($PDFFileName,'I');
    $pdfData = ob_get_contents();
    ob_end_clean();
    fwrite($stream, $pdfData);
    rewind($stream);

    // Registra o acesso
    $this->info("Acesso ao PDF com as informações de simulação do "
      . "fechamento de '{referenceMonthYear}'.",
      [ 'referenceMonthYear' => $referenceMonthYear ]
    );

    return $response
      ->withBody(new Stream($stream))
      ->withHeader('Content-Type', 'application/pdf')
      ->withHeader('Cache-Control', 'no-store, no-cache, '
          . 'must-revalidate'
        )
      ->withHeader('Expires', 'Sun, 1 Jan 2000 12:00:00 GMT')
      ->withHeader('Last-Modified', gmdate('D, d M Y H:i:s') . 'GMT');
  }

  /**
   * Descarta os valores do fechamento atual.
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
  public function discard(
    Request $request,
    Response $response,
    array $args
  ): Response
  {
    // Verifica se estamos modificando os dados
    if ($request->isDelete()) {
      // Registra o acesso
      $this->debug("Processando o descarte dos valores do fechamento "
        . "atual.");
      
      // Recupera os dados do contratante
      $contractor = $this->authorization->getContractor();

      try
      {
        // Iniciamos a transação
        $this->DB->beginTransaction();

        // Descartamos os valors do fechamento atual
        $sql = "SELECT erp.discardMonthlyCalculations({$contractor->id});";
        $this->DB->select($sql);

        // Efetiva a transação
        $this->DB->commit();

        $this->info("Descartado os valores do fechamento atual dos "
          . "clientes do contratante '{contractor}'.",
          [ 'contractor' => $contractor->name ]
        );
      
        $message = "Descartado os valores do fechamento.";

        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'result' => 'OK',
              'params' => $request->getQueryParams(),
              'message' => $message,
              'data' => null
            ])
        ;
      }
      catch(QueryException $exception)
      {
        // Reverte (desfaz) a transação
        $this->DB->rollBack();

        // Registra o erro
        $this->error("Não foi possível descartar os valores do "
          . "fechamento dos clientes do contratante '{contractor}'. "
          . "Erro interno no banco de dados: {error}.",
          [ 'contractor' => $contractor->name,
            'error' => $exception->getMessage() ]
        );
        
        $message = "Não foi possível descartar os valores do "
          . "fechamento. Erro interno no banco de dados."
        ;
      }
      catch(Exception $exception)
      {
        // Reverte (desfaz) a transação
        $this->DB->rollBack();

        // Registra o erro
        $this->error("Não foi possível descartar os valores do "
          . "fechamento dos clientes do contratante '{contractor}'. "
          . "Erro interno: {error}.",
          [ 'contractor' => $contractor->name,
            'error' => $exception->getMessage() ]
        );
        
        $message = "Não foi possível descartar os valores do "
          . "fechamento. Erro interno."
        ;
      }
    } else {
      $message = "Não foi encontrado nenhum período a ser apurado.";
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
   * Envia para cobrança os valores do fechamento atual.
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
  public function finish(
    Request $request,
    Response $response,
    array $args
  ): Response
  {
    // Verifica se estamos modificando os dados
    if ($request->isPut()) {
      // Registra o acesso
      $this->debug("Processando o envio dos valores do fechamento "
        . "atual para cobrança.");
      
      // Recupera os dados do contratante
      $contractor = $this->authorization->getContractor();

      try
      {
        // Iniciamos a transação
        $this->DB->beginTransaction();

        // Envia os valors do fechamento atual para cobrança
        $sql = "SELECT erp.finishMonthlyCalculations({$contractor->id});";
        $this->DB->select($sql);

        // Efetiva a transação
        $this->DB->commit();

        $this->info("Enviado os valores do fechamento atual dos "
          . "clientes do contratante '{contractor}' para cobrança.",
          [ 'contractor' => $contractor->name ]
        );
      
        $message = "Enviado os valores do fechamento para cobrança.";

        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'result' => 'OK',
              'params' => $request->getQueryParams(),
              'message' => $message,
              'data' => null
            ])
        ;
      }
      catch(QueryException $exception)
      {
        // Reverte (desfaz) a transação
        $this->DB->rollBack();

        // Registra o erro
        $this->error("Não foi possível enviar os valores do fechamento "
          . "dos clientes do contratante '{contractor}' para cobrança. "
          . "Erro interno no banco de dados: {error}.",
          [ 'contractor' => $contractor->name,
            'error' => $exception->getMessage() ]
        );
        
        $message = "Não foi possível enviar os valores do fechamento "
          . "para cobrança. Erro interno no banco de dados."
        ;
      }
      catch(Exception $exception)
      {
        // Reverte (desfaz) a transação
        $this->DB->rollBack();

        // Registra o erro
        $this->error("Não foi possível enviar os valores do fechamento "
          . "dos clientes do contratante '{contractor}' para cobrança. "
          . "Erro interno: {error}.",
          [ 'contractor' => $contractor->name,
            'error' => $exception->getMessage() ]
        );
        
        $message = "Não foi possível enviar os valores do fechamento "
          . "para cobrança. Erro interno."
        ;
      }
    } else {
      $message = "Não foi encontrado nenhum fechamento em execução.";
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
   * Exibe um formulário para adição de um novo lançamento, quando
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
    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();

    $contracts = [];
    $installations = [];

    // Recupera as informações de parâmetros adicionais informados
    $parms = $request->getQueryParams();
    if (count($parms) > 0) {
      $customerID         = $parms['customerID'];
      $customerName       = $parms['customerName'];
      $subsidiaryID       = $parms['subsidiaryID'];
      $subsidiaryName     = $parms['subsidiaryName'];
      $contractID         = $parms['contractID'];
      $contractNumber     = $parms['contractNumber'];
      $installationID     = $parms['installationID'];
      $installationNumber = $parms['installationNumber'];

      // Recupera as informações dos contratos
      $contracts = $this->getContracts($customerID, $subsidiaryID);

      // Recupera as informações das instalações do contrato
      $installations = $this->getInstallations($contractID);
    } else {
      $customerID = 0;
      $customerName = '';
      $subsidiaryID = 0;
      $subsidiaryName = '';
      $contractID = 0;
      $contractNumber = '';
      $installationID = 0;
      $installationNumber = '';
    }

    // Verifica se estamos postando os dados
    if ($request->isPost()) {
      // Os dados estão sendo postados
      
      // Registra o acesso
      $this->debug("Processando à adição de lançamento.");
      
      // Valida os dados
      $this->validator->validate($request,
        $this->getValidationRules(true)
      );

      if ($this->validator->isValid()) {
        $this->debug('Os dados do lançamento são VÁLIDOS');

        // Recupera os dados do lançamento
        $billingData  = $this->validator->getValues();
        $customerID   = $billingData['customerid'];
        $subsidiaryID = $billingData['subsidiaryid'];
        $contractID   = $billingData['contractid'];

        try
        {
          // Grava a novo lançamento

          // Iniciamos a transação
          $this->DB->beginTransaction();

          // Recuperamos qual o ID da fatura aberta para este cliente
          // nesta instalação
          $invoices = Billing::join('invoices', 'billings.invoiceid',
                '=', 'invoices.invoiceid'
              )
            ->where('invoices.underanalysis', 'true')
            ->where('billings.installationid', '=', $billingData['installationid'])
            ->get([
                'invoices.invoiceid',
                'invoices.referencemonthyear'
              ])
          ;

          if ( $invoices->isEmpty() ) {
            // Não têm nenhum faturamento em processamento
            $invoiceID = 0;
          } else {
            $invoice = $invoices->first();
            $invoiceID = $invoice->invoiceid;
            $periodStart = Carbon::createFromFormat('d/m/Y',
              '01/'. $invoice->referencemonthyear
            );
            $periodEnd = $periodStart
              ->copy()
              ->endOfMonth()
            ;
          }
          
          if ($billingData['numberofinstallments'] > 1) {
            $this->debug("A instalação possui parcelamento");
            // Precisamos adicionar as parcelas deste lançamento
            $numberofinstallments = $billingData['numberofinstallments'];
            $installmentnumber = 1;
            $installmentvalue = $billingData['installmentvalue'];
            $installmentvalue = floatval(
              str_replace(',', '.',
                str_replace('.', '', $installmentvalue)
              )
            );
            
            // Incrementa a data de cada parcela
            $billingDate = Carbon::createFromFormat('d/m/Y',
              $billingData['billingdate']
            );

            do {
              $billing = new Billing();
              $billing->fill($billingData);
              // Substituímos o valor da parcela
              $billing->billingdate = $billingDate->format('Y-m-d');
              $billing->installmentnumber = $installmentnumber;
              $billing->numberofinstallments = $numberofinstallments;
              $billing->value = $installmentvalue;
              if ($invoiceID > 0) {
                // Analisa se a cobrança deve ser lançada na fatura em
                // aberto
                if ($billingDate->lessThanOrEqualTo($periodEnd)) {
                  // Adiciona a fatura da qual faz parte
                  $billing->invoiceid = $invoiceID;
                }
              }

              // Adiciona o contratante e usuário responsável pelo registro
              $billing->contractorid = $contractor->id;
              $billing->createdbyuserid =
                $this->authorization->getUser()->userid
              ;
              $billing->updatedbyuserid =
                $this->authorization->getUser()->userid
              ;
              $billing->save();

              $installmentnumber++;
              $billingDate->addMonths(1);
            } while ($installmentnumber <= $numberofinstallments);
          } else {
            $billing = new Billing();
            unset($billingData['installmentnumber']);
            unset($billingData['numberofinstallments']);
            $billing->fill($billingData);

            if ($invoiceID > 0) {
              // Determina a data da parcela
              $billingDate = Carbon::createFromFormat('d/m/Y',
                $billingData['billingdate']
              );

              // Analisa se a cobrança deve ser lançada na fatura em
              // aberto
              if ($billingDate->lessThanOrEqualTo($periodEnd)) {
                // Adiciona a fatura da qual faz parte
                $billing->invoiceid = $invoiceID;
              }
            }

            // Adiciona o contratante e usuário responsável pelo registro
            $billing->contractorid = $contractor->id;
            $billing->createdbyuserid =
              $this->authorization->getUser()->userid
            ;
            $billing->updatedbyuserid =
              $this->authorization->getUser()->userid
            ;
            $billing->save();
          }

          if ($invoiceID > 0) {
            // Força o recalculamento da fatura
            $sql = "SELECT erp.updateInvoiceValue({$contractor->id},"
              . "{$invoiceID});"
            ;
            $this->DB->select($sql);
          }

          // Efetiva a transação
          $this->DB->commit();

          // Registra o sucesso
          $this->info("Cadastrado o lançamento '{name}' na instalação "
            . "'{installation}' do cliente '{customerName}' no "
            . "contratante '{contractor}' com sucesso.",
            [ 'name' => $billingData['name'],
              'installation' => $installationNumber,
              'customerName' => $customerName,
              'contractor' => $contractor->name ]
          );
          
          // Alerta o usuário
          $this->flash("success", "O lançamento '{name}' foi cadastrado "
            . "com sucesso.",
            [ 'name'  => $billingData['name'] ]
          );
          
          // Registra o evento
          $this->debug("Redirecionando para {routeName}",
            [ 'routeName' => 'ERP\Financial\MonthlyCalculations' ]
          );
          
          // Redireciona para a página de gerenciamento dos fechamentos
          // mensais
          return $this->redirect($response,
            'ERP\Financial\MonthlyCalculations')
          ;
        }
        catch(QueryException $exception)
        {
          // Reverte (desfaz) a transação
          $this->DB->rollBack();

          // Registra o erro
          $this->error("Não foi possível inserir as informações do "
            . "lançamento '{name}' na instalação '{installation}' do "
            . "cliente '{customerName}' no contratante '{contractor}'. "
            . "Erro interno no banco de dados: {error}.",
            [ 'name' => $billingData['name'],
              'installation' => $installationNumber,
              'customerName' => $customerName,
              'contractor' => $contractor->name,
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações do lançamento. Erro interno no banco de dados."
          );
        }
        catch(Exception $exception)
        {
          // Reverte (desfaz) a transação
          $this->DB->rollBack();

          // Registra o erro
          $this->error("Não foi possível inserir as informações da "
            . "lançamento '{name}' na instalação  '{installation}' do "
            . "cliente '{customerName}' no contratante '{contractor}'. "
            . "Erro interno: {error}.",
            [ 'name' => $billingData['name'],
              'installation' => $installationNumber,
              'customerName' => $customerName,
              'contractor' => $contractor->name,
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações do lançamento. Erro interno."
          );
        }
      } else {
        $this->debug('Os dados do lançamento são INVÁLIDOS');
        $messages = $this->validator->getFormatedErrors();
        foreach ($messages AS $message) {
          $this->debug($message);
        }

        // Recupera os dados do lançamento
        $billingData = $this->validator->getValues();

        if ($billingData['customerid'] > 0) {
          $customerID   = $billingData['customerid'];
          $subsidiaryID = $billingData['subsidiaryid'];
          $contractID   = $billingData['contractid'];
        }
      }
    } else {
      // Carrega um conjunto de valores vazios
      $emptyBilling = [
        'customerid' => $customerID,
        'customername' => $customerName,
        'subsidiaryid' => $subsidiaryID,
        'subsidiaryname' => $subsidiaryName,
        'contractid' => $contractID,
        'contractnumber' => $contractNumber,
        'installationid' => $installationID,
        'installationnumber' => $installationNumber,
        'installmenttypeid' => 0,
        'billingdate' => Carbon::now()->format('d/m/Y'),
        'value' => '0,00',
        'numberofinstallments' => 0,
        'installmentvalue' => '0,00'
      ];
      $this->validator->setValues($emptyBilling);
    }

    try {
      if (($customerID > 0) && ($subsidiaryID > 0)) {
        // Recupera as informações dos contratos deste cliente
        $contracts = $this->getContracts($customerID, $subsidiaryID);
        $contracts = $contracts
          ->toArray()
        ;
      }

      if ($contractID > 0) {
        // Recupera as informações das instalações do contrato
        $installations = $this->getInstallations($contractID);
      }
    }
    catch (RuntimeException $exception)
    {
      // Alerta o usuário
      $this->flash("error", $exception->getMessage());

      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [ 'routeName' => 'ERP\Financial\MonthlyCalculations' ]
      );

      // Redireciona para a página de gerenciamento dos fechamentos
      // mensais
      return $this->redirect($response, 'ERP\Financial\MonthlyCalculations');
    }
    
    // Exibe um formulário para adição de um lançamento
    
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ERP\Home')
    );
    $this->breadcrumb->push('Financeiro', '');
    $this->breadcrumb->push('Fechamentos',
      $this->path('ERP\Financial\MonthlyCalculations')
    );
    $this->breadcrumb->push('Adicionar',
      $this->path('ERP\Financial\MonthlyCalculations\Add')
    );
    
    // Registra o acesso
    $this->info("Acesso à adição de lançamento no "
      . "contratante '{contractor}'.",
      [ 'contractor' => $contractor->name ]
    );
    
    // Renderiza a página
    return $this->render($request, $response,
      'erp/financial/monthlycalculations/newbilling.twig',
      [
        'formMethod' => 'POST',
        'contracts' => $contracts,
        'installations' => $installations
      ])
    ;
  }
  
  /**
   * Exibe um formulário para edição de um lançamento, quando
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
    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();

    try
    {
      // Recupera as informações do lançamento
      $billingID = $args['billingID'];
      $billing = Billing::join('contracts',
            'billings.contractid', '=', 'contracts.contractid'
          )
        ->join('entities as customers',
            'contracts.customerid', '=', 'customers.entityid'
          )
        ->join('subsidiaries',
            'contracts.subsidiaryid', '=', 'subsidiaries.subsidiaryid'
          )
        ->join('installations', 'billings.installationid',
            '=', 'installations.installationid'
          )
        ->join('users AS createduser',
            'billings.createdbyuserid', '=', 'createduser.userid'
          )
        ->join('users AS updateduser', 'billings.updatedbyuserid',
            '=', 'updateduser.userid'
          )
        ->where('billings.contractorid', '=', $contractor->id)
        ->where('billings.billingid', '=', $billingID)
        ->get([
            'billings.*',
            $this->DB->raw('CASE WHEN billings.contractchargeid IS NULL THEN 0 ELSE billings.contractchargeid END AS contractchargeid'),
            $this->DB->raw('getContractNumber(contracts.createdat) AS contractnumber'),
            'contracts.customerid',
            'contracts.subsidiaryid',
            'customers.name AS customername',
            'subsidiaries.name AS subsidiaryname',
            'installations.installationnumber',
            'createduser.name AS createdbyusername',
            'updateduser.name AS updatedbyusername'
          ])
      ;

      if ( $billing->isEmpty() ) {
        throw new ModelNotFoundException("Não temos nenhum lançamento "
          . "com o código {$billingID} cadastrado"
        );
      }
      $billing = $billing
        ->first()
        ->toArray()
      ;
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar o lançamento "
        . "código {billingID}.",
        [ 'billingID' => $billingID ]
      );
      
      // Alerta o usuário
      $this->flash("error", "Não foi possível localizar este "
        . "lançamento."
      );
      
      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [ 'routeName' => 'ERP\Financial\MonthlyCalculations' ]
      );
      
      // Redireciona para a página de gerenciamento de lançamentos
      return $this->redirect($response,
        'ERP\Financial\MonthlyCalculations'
      );
    }
      
    // Verifica se estamos modificando os dados
    if ($request->isPut()) {
      // Os dados estão sendo modificados
      
      // Registra o acesso
      $this->debug("Processando à edição do lançamento '{name}' do "
        . "cliente '{customername}' no contratante {contractor}.",
        [ 'name' => $billing['name'],
          'customername' => $billing['customername'],
          'contractor' => $contractor->name ]
      );
      
      // Valida os dados
      $this->validator->validate($request,
        $this->getValidationRules(false)
      );

      if ($this->validator->isValid()) {
        $this->debug('Os dados do lançamento são VÁLIDOS');

        // Grava as informações no banco de dados

        // Recupera os dados modificados do lançamento
        $billingData = $this->validator->getValues();

        try
        {
          // Não permite modificar o contratante
          unset($billingData['contractorid']);

          // Iniciamos a transação
          $this->DB->beginTransaction();

          // Recuperamos qual o ID da fatura aberta para este cliente
          // nesta instalação
          $invoices = Billing::join('invoices', 'billings.invoiceid',
                '=', 'invoices.invoiceid'
              )
            ->where('invoices.underanalysis', 'true')
            ->where('billings.installationid', '=', $billingData['installationid'])
            ->get([
                'invoices.invoiceid',
                'invoices.referencemonthyear'
              ])
          ;

          if ( $invoices->isEmpty() ) {
            // Não têm nenhum faturamento em processamento
            $invoiceID = 0;
          } else {
            $invoice = $invoices->first();
            $invoiceID = $invoice->invoiceid;
            $periodStart = Carbon::createFromFormat('d/m/Y',
              '01/'. $invoice->referencemonthyear
            );
            $periodEnd = $periodStart
              ->copy()
              ->endOfMonth()
            ;
          }

          // Recupera a data da cobrança
          $billingDate = Carbon::createFromFormat('d/m/Y',
            $billingData['billingdate']
          );

          if ($invoiceID > 0) {
            $inInvoice = 'NULL';
            if ($billingDate->lessThanOrEqualTo($periodEnd)) {
              // Adiciona a fatura da qual faz parte
              $inInvoice = (string) $invoiceID;
            }

            $invoiceData = sprintf("invoiceID = %s, ", $inInvoice);
          } else {
            $invoiceData = '';
          }

          // Fazemos a modificação desta fatura
          $sql = sprintf(""
            . "UPDATE erp.billings "
            .    "SET name = '%s', "
            .        "value = %.2f, "
            .        "billingDate = '%s'::Date, "
            .        $invoiceData
            .        "updatedAt = CURRENT_TIMESTAMP, "
            .        "updatedByUserID = %d "
            .  "WHERE billingID = %d;",
            $billingData['name'],
            $this->toFloat($billingData['value']),
            $billingDate->format('Y-m-d'),
            $this->authorization->getUser()->userid,
            $billingID
          );
          $this->DB->select($sql);

          if ($invoiceID > 0) {
            // Força o recalculamento da fatura
            $sql = "SELECT erp.updateInvoiceValue({$contractor->id},"
              . "{$invoiceID});"
            ;
            $this->DB->select($sql);
          }

          // Efetiva a transação
          $this->DB->commit();

          // Registra o sucesso
          $this->info("Modificado o lançamento '{name}' na instalação "
            . "'{installation}' do cliente '{customerName}' no "
            . "contratante '{contractor}' com sucesso.",
            [ 'name' => $billingData['name'],
              'installation' => $billingData['installationnumber'],
              'customerName' => $billingData['customername'],
              'contractor' => $contractor->name ]
          );
          
          // Alerta o usuário
          $this->flash("success", "O lançamento '{name}' foi modificado "
            . "com sucesso.",
            [ 'name'  => $billingData['name'] ]
          );
          
          // Registra o evento
          $this->debug("Redirecionando para {routeName}",
            [ 'routeName' => 'ERP\Financial\MonthlyCalculations' ]
          );
          
          // Redireciona para a página de gerenciamento de lançamentos
          return $this->redirect($response,
            'ERP\Financial\MonthlyCalculations'
          );
        }
        catch(QueryException $exception)
        {
          // Reverte (desfaz) a transação
          $this->DB->rollBack();

          // Registra o erro
          $this->error("Não foi possível modificar as informações do "
            . "lançamento '{name}' na instalação '{installation}' do "
            . "cliente '{customerName}' no contratante '{contractor}'. "
            . "Erro interno no banco de dados: {error}.",
            [ 'name' => $billingData['name'],
              'installation' => $billingData['installationnumber'],
              'customerName'  => $billingData['customername'],
              'contractor' => $contractor->name,
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível modificar as "
            . "informações do lançamento. Erro interno no banco de dados."
          );
        }
        catch(Exception $exception)
        {
          // Reverte (desfaz) a transação
          $this->DB->rollBack();

          // Registra o erro
          $this->error("Não foi possível modificar as informações do "
            . "lançamento '{name}' na instalação '{installation}' do "
            . "cliente '{customerName}' no contratante '{contractor}'. "
            . "Erro interno: {error}.",
            [ 'name' => $billingData['name'],
              'installation' => $billingData['installationnumber'],
              'customerName'  => $billingData['customername'],
              'contractor' => $contractor->name,
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível modificar as "
            . "informações do lançamento. Erro interno."
          );
        }
      } else {
        $this->debug('Os dados do lançamento são INVÁLIDOS');
        $messages = $this->validator->getFormatedErrors();
        foreach ($messages AS $message) {
          $this->debug($message);
        }
      }
    } else {
      // Carrega os dados atuais
      $this->validator->setValues($billing);
    }
    
    // Exibe um formulário para edição de um lançamento
    
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ERP\Home')
    );
    $this->breadcrumb->push('Financeiro', '');
    $this->breadcrumb->push('Fechamentos',
      $this->path('ERP\Financial\MonthlyCalculations')
    );
    $this->breadcrumb->push('Editar',
      $this->path('ERP\Financial\MonthlyCalculations\Edit', [
        'billingID' => $billingID
      ])
    );
    
    // Registra o acesso
    $this->info("Acesso à edição do lançamento '{name}' do cliente "
      . "'{customername}' no contratante {contractor}.",
      [ 'name' => $billing['name'],
        'customername' => $billing['customername'],
        'contractor' => $contractor->name ]
    );
    
    // Renderiza a página
    return $this->render($request, $response,
      'erp/financial/monthlycalculations/billing.twig',
      [ 'formMethod' => 'PUT' ])
    ;
  }
  
  /**
   * Remove o lançamento.
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
    $this->debug("Processando à remoção de lançamento.");
    
    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();

    // Recupera o ID
    $billingID = $args['billingID'];

    try
    {
      // Recupera as informações do lançamento
      $billingData = Billing::join('contracts',
            'billings.contractid', '=', 'contracts.contractid'
          )
        ->join('entities as customer',
            'contracts.customerid', '=', 'customer.entityid'
          )
        ->where('billings.billingid', '=', $billingID)
        ->get([
            'billings.billingid AS id',
            'customer.name AS customername'
          ])
        ->first()
      ;
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar o lançamento "
        . "código {billingID} para remoção.",
        [ 'billingID' => $billingID ]
      );
    
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'NOK',
            'params' => $request->getParams(),
            'message' => 'Não foi possível localizar o lançamento para remoção',
            'data' => null
          ])
      ;
    }

    try
    {
      // Iniciamos a transação
      $this->DB->beginTransaction();

      $billing = Billing::where('contractorid',
            '=', $contractor->id
          )
        ->where('billingid', '=', $billingID)
        ->firstOrFail()
      ;
      
      // Agora apaga o lançamento do valor a ser cobrado
      $billing->delete();

      // Força o recalculamento da fatura
      $sql = "SELECT erp.updateInvoiceValue({$contractor->id},"
        . "{$billingData->invoiceid});"
      ;
      $this->DB->select($sql);

      // Efetiva a transação
      $this->DB->commit();

      // Registra o sucesso
      $this->info("O lançamento '{name}' do cliente '{customername}' "
        . "do contratante '{contractor}' foi removido com sucesso.",
        [ 'name' => $billingData->name,
          'customername' => $billingData->customername,
          'contractor' => $contractor->name ]
      );
      
      // Informa que a remoção foi realizada com sucesso
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getParams(),
            'message' => "Removido o lançamento "
              . "{$billingData->name}",
            'data' => "Delete"
          ])
      ;
    }
    catch(QueryException $exception)
    {
      // Reverte (desfaz) a transação
      $this->DB->rollBack();

      // Registra o erro
      $this->error("Não foi possível remover as informações do "
        . "lançamento '{name}' do cliente 'customername' no "
        . "contratante '{contractor}'. Erro interno no banco de dados: "
        . "{error}.",
        [ 'name'  => $billingData->name,
          'customername'  => $billingData->customername,
          'contractor' => $contractor->name,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível remover o lançamento. Erro "
        . "interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Reverte (desfaz) a transação
      $this->DB->rollBack();

      // Registra o erro
      $this->error("Não foi possível remover as informações do "
        . "lançamento '{name}' do cliente 'customername' no "
        . "contratante '{contractor}'. Erro interno: {error}.",
        [ 'name'  => $billingData->name,
          'customername'  => $billingData->customername,
          'contractor' => $contractor->name,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível remover o lançamento. Erro "
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
  
  /**
   * Abona o lançamento.
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
  public function grante(
    Request $request,
    Response $response,
    array $args
  ): Response
  {
    // Registra o acesso
    $this->debug("Processando o abono de lançamento.");
    
    // Recupera os dados do contratante
    $contractor = $this->authorization->getContractor();

    // Recupera o ID
    $billingID = $args['billingID'];

    // Recupera os dados da requisição
    $postParams = $request->getParsedBody();

    // Recupera o motivo do abono
    $reasonForGranting = $postParams['reasonForGranting'];

    try
    {
      // Recupera as informações do lançamento
      $billingData = Billing::join('contracts',
            'billings.contractid', '=', 'contracts.contractid'
          )
        ->join('entities as customer',
            'contracts.customerid', '=', 'customer.entityid'
          )
        ->where('billings.billingid', '=', $billingID)
        ->get([
            'billings.billingid AS id',
            'customer.name AS customername',
            'billings.invoiceid',
            'billings.granted'
          ])
        ->first()
      ;
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar o lançamento "
        . "código {billingID}.",
        [ 'billingID' => $billingID ]
      );
    
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'NOK',
            'params' => $request->getParams(),
            'message' => 'Não foi possível localizar o lançamento para abonar',
            'data' => null
          ])
      ;
    }

    try
    {
      if ($billingData->granted === false) {
        // Iniciamos a transação
        $this->DB->beginTransaction();

        // Abona o valor cobrado
        $billing = Billing::where('contractorid',
              '=', $contractor->id
            )
          ->where('billingid', '=', $billingID)
          ->firstOrFail()
        ;
        $billing->granted = "true";
        $billing->reasonforgranting = $reasonForGranting;

        // Adicionamos as informações do responsável pelo abono
        $billing->updatedbyuserid =
          $this->authorization->getUser()->userid
        ;
        $billing->save();

        if ($billingData->invoiceid > 0) {
          // Força o recalculamento da fatura
          $invoiceID = $billingData->invoiceid;
          $sql = "SELECT erp.updateInvoiceValue({$contractor->id},"
            . "{$invoiceID});"
          ;
          $this->DB->select($sql);
        }

        // Efetiva a transação
        $this->DB->commit();

        // Registra o sucesso
        $this->info("O lançamento '{name}' do cliente '{customername}' "
          . "do contratante '{contractor}' foi abonado com sucesso.",
          [ 'name' => $billingData->name,
            'customername' => $billingData->customername,
            'contractor' => $contractor->name ]
        );
      } else {
        $this->info("O lançamento '{name}' do cliente '{customername}' "
          . "do contratante '{contractor}' já estava abonado.",
          [ 'name' => $billingData->name,
            'customername' => $billingData->customername,
            'contractor' => $contractor->name ]
        );
      }
      
      // Informa que a remoção foi realizada com sucesso
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getParams(),
            'message' => "Abonado o lançamento "
              . "{$billingData->name}",
            'data' => "Delete"
          ])
      ;
    }
    catch(QueryException $exception)
    {
      // Reverte (desfaz) a transação
      $this->DB->rollBack();

      // Registra o erro
      $this->error("Não foi possível abonar as informações do "
        . "lançamento '{name}' do cliente 'customername' no "
        . "contratante '{contractor}'. Erro interno no banco de dados: "
        . "{error}.",
        [ 'name'  => $billingData->name,
          'customername'  => $billingData->customername,
          'contractor' => $contractor->name,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível abonar o lançamento. Erro "
        . "interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Reverte (desfaz) a transação
      $this->DB->rollBack();

      // Registra o erro
      $this->error("Não foi possível abonar as informações do "
        . "lançamento '{name}' do cliente 'customername' no "
        . "contratante '{contractor}'. Erro interno: {error}.",
        [ 'name'  => $billingData->name,
          'customername'  => $billingData->customername,
          'contractor' => $contractor->name,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível abonar o lançamento. Erro "
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

  /**
   * Totaliza os valores.
   *
   * @param string $total
   *   O valor total
   * @param string $newValue
   *   O valor a ser computado (acrescentado)
   *
   * @return string
   *   O valor total acrescido do valor informado
   */
  protected function addValue(string $total, string $newValue): string
  {
    $total = floatval(
        str_replace(',', '.',
          str_replace('.', '', $total)
        )
      );
    $newValue = floatval(
        str_replace(',', '.',
          str_replace('.', '', $newValue)
        )
      );
    $total += $newValue;

    $money = numfmt_create(
      'pt_BR', NumberFormatter::DECIMAL
    );

    // Define o valor com 2 casas decimais
    $money
      ->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, 2)
    ;
    $money
      ->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, 2)
    ;

    return (string) $this->money->format($total);
  }
}
