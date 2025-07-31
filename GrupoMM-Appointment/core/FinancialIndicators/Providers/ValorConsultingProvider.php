<?php
/*
 * This file is part of Slim Framework 3 skeleton application.
 *
 * (c) Emerson Cavalcanti
 *
 * LICENSE: The MIT License (MIT)
 *
 * Permission is hereby granted, free of charge, to any person obtaining
 * a copy of this software and associated documentation files (the 
 * "Software"), to deal in the Software without restriction, including
 * without limitation the rights to use, copy, modify, merge, publish,
 * distribute, sublicense, and/or sell copies of the Software, and to
 * permit persons to whom the Software is furnished to do so, subject
 * to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.
 * IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY
 * CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT,
 * TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE
 * SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 * 
 * ---------------------------------------------------------------------
 * Descrição:
 * 
 * A classe que obtém os valores atualizados de indicadores financeiros
 * através do provedor Valor Consulting (http://www.debit.com.br).
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\FinancialIndicators\Providers;

use DateTime;
use Core\FinancialIndicators\Indicators\Indicator;
use Core\FinancialIndicators\Indicators\{IGPDI, IGPM, IPC, IPCA, INPC};
use Core\FinancialIndicators\Providers\AbstractProvider;
use Core\FinancialIndicators\Providers\ProviderInterface;
use DOMDocument;
use DOMElement;
use DOMXPath;
use UnexpectedValueException;

class ValorConsultingProvider
  extends AbstractProvider
  implements ProviderInterface
{
  /**
   * O nome do provedor de indicadores financeiros.
   *
   * @var string
   */
  protected $name = 'Valor Consulting';

  /**
   * A URL do provedor
   */
  protected $baseURL = 'https://www.valor.srv.br/indices';

  /**
   * O nome da classe do indicador sendo processada.
   *
   * @var string|null
   */
  protected $currentClass = null;

  /**
   * Os indicadores obtidos.
   *
   * @var array|null
   */
  protected $indicators = null;

  /**
   * Obtém a sigla de um indicador financeiro à partir de sua classe.
   *
   * @param string $className
   *   O nome da classe do indicador
   *
   * @return string
   */
  private function getIndicatorAcronyms(string $className)
  {
    return strtolower($className::getAcronyms());
  }

  /**
   * Determina se o elemento DOM informado é de uma linha que contém um
   * indicador financeiro ainda não divulgado.
   *
   * @param DOMElement $nodes
   *   Os elementos DOM informados
   *
   * @return bool
   */
  private function nodeContainUnknownIndicator(DOMElement $nodes): bool
  {
    if ($nodes->childNodes->length === 5) {
      // Analisamos expressões que possam indicar se o índice não foi
      // ainda publicado
      if ( !(stripos($nodes->childNodes[3]->textContent, "apenas") === false) ) {
        return true;
      } elseif ( !(stripos($nodes->childNodes[3]->textContent, "não") === false) ) {
        return true;
      } elseif ( !(stripos($nodes->childNodes[3]->textContent, "índice") === false) ) {
        return true;
      }
    }

    return false;
  }

  /**
   * Faz o parse do conteúdo deste provedor.
   *
   * @param string $content
   *   O conteúdo obtido à partir da página do provedor.
   *
   * @throws UnexpectedValueException
   *   Em caso de algum problema na estrutura do documento
   *
   * @return array
   *   Os dados obtidos.
   */
  protected function parse(string $content): array
  {
    libxml_use_internal_errors(true);

    // Convertemos todos os carateres especiais para utf-8
    $content = mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8');

    // Criamos um novo documento na especificação HTML 1.0 e codificação
    // UTF-8
    $doc = new DomDocument('1.0', 'UTF-8');

    // Aqui carregamos o conteúdo sem adicionar tags html/body e também
    // a declaração doctype
    $doc->loadHTML($content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

    // Utilizamos DOMXPath para localizar o conteúdo
    $xpath = new DOMXPath($doc);

    // A matriz com os dados obtidos
    $data = [];

    // A página contém um div especial (mdl-grid) com alguns cards os
    // quais contém a informação que desejamos. Separamos eles.
    $cardNodeList = $xpath->query("//div[@id='sectionIndiceTabela']/table[@id='indiceTable']/tbody/tr");
    if ($cardNodeList->length < 2) {
      throw new UnexpectedValueException("Não foi possível obter as "
        . "linhas com os conteúdos dos indicadores. Ocorreu alguma "
        . "mudança na estrutura do documento."
      );
    }

    foreach ($cardNodeList as $columns) {
      if (!$this->nodeContainUnknownIndicator($columns)) {
        $date = $this->getDateFromMonthYearFormat(
          $columns->childNodes[1]->textContent
        );

        if (count($columns->childNodes) < 7) {
          $content = '';
          foreach($columns->childNodes as $node){
            $content .= $node->textContent . ";";
          }

          throw new UnexpectedValueException('Erro ao processar: '
            . $content
          );
        }

        $percentage = $columns->childNodes[7]->textContent;
        $percentage = (float) trim(str_replace(',', '.', $percentage));

        $indicatorObject = new $this->currentClass($date, $percentage);

        $data[$indicatorObject::getCode()][] = $indicatorObject;
      }
    }

    return $data;
  }

  /**
   * Obtém a data à partir do ano e mês.
   *
   * @param string $monthYearString
   *   O texto com o ano e o mês
   *
   * @throws UnexpectedValueException
   *   Em caso de algum problema na estrutura do documento
   *
   * @return DateTime
   */
  private function getDateFromMonthYearFormat(
    string $monthYearString
  ): DateTime
  {
    $monthList = [
      'Jan' => '01', 'Fev' => '02', 'Mar' => '03', 'Abr' => '04',
      'Mai' => '05', 'Jun' => '06', 'Jul' => '07', 'Ago' => '08',
      'Set' => '09', 'Out' => '10', 'Nov' => '11', 'Dez' => '12'
    ];

    if (preg_match('/[ADFJMNOS][a-z]{2}\/[12][0-9]{3}\b/',
      $monthYearString, $matchs)) {
      list($monthName, $year) = explode('/', $matchs[0]);
      $monthName = trim($monthName);
      $year = trim($year);

      if (array_key_exists($monthName, $monthList)) {
        $month = $monthList[$monthName];
      } else {
        throw new UnexpectedValueException("Erro processando mês com "
          . "valor inválido: " . $monthName . " em " . $monthYearString
        );
      }
    } else {
      throw new UnexpectedValueException("Erro processando mês/ano com "
        . "valor inválido: " . $monthYearString
      );
    }

    return
      new DateTime("{$year}-{$month}-01")
    ;
  }

  /**
   * Obtém todos os indicadores.
   *
   * @return void
   */
  protected function getAllIndicators(): void
  {
    if ($this->indicators === NULL) {
      // Vamos mapear os indicadores para nos permitir obter as
      // informações de cada indicador, pois eles se encontram em páginas
      // separadas
      $map = [
        0 => IGPDI::class,
        1 => IGPM::class,
        2 => INPC::class,
        3 => IPC::class,
        4 => IPCA::class
      ];

      $this->indicators = [];

      foreach ($map as $className) {
        // Obtemos a sigla do indicador
        $acronyms = $this->getIndicatorAcronyms($className);

        // Obtemos os dados do provedor
        $uri = $this->getURI($acronyms . '.php?pagina=1');
        $this->currentClass = $className;
        $this->indicators = $this->indicators
          + $this->getProviderContent($uri)
        ;
      }
    }
  }

  /**
   * Obtém os valores mais recentes de cada indicador financeiro.
   *
   * @return array
   */
  public function getLatestIndicators(): array
  {
    // Obtemos os dados do provedor
    $this->getAllIndicators();

    // Percorremos os dados, atribuindo os valores de cada indicador
    $indicators = [];
    foreach ($this->indicators as $code => $indicatorPerMonth) {
      if (count($indicatorPerMonth) > 0) {
        $indicators[$code] = $indicatorPerMonth[0];
      }
    }

    return $indicators;
  }

  /**
   * Obtém os indicadores para o mês atual.
   *
   * @return array
   */
  public function getIndicatorsForCurrentMonth(): array
  {
    // Obtemos os dados do provedor
    $this->getAllIndicators();

    // Percorremos os dados, atribuindo os valores de cada indicador
    $indicators = [];
    foreach ($this->indicators as $code => $indicatorPerMonth) {
      $indicators[$code] = [];

      foreach ($indicatorPerMonth as $indicator) {
        if ( ((new DateTime)->format('m') === $indicator->getDate()->format('m'))
             && ((new DateTime)->format('y') === $indicator->getDate()->format('y')) ) {
          $indicators[$code] = $indicator;

          break;
        }
      }
    }

    return $indicators;
  }

  /**
   * Obtém os índices disponíveis através do código do indicador
   * financeiro.
   *
   * @param int $indicatorCode
   *   O código do indicador financeiro
   *
   * @return array
   *   Os índices deste indicador financeiro
   */
  public function getIndexesFromIndicatorCode(
    int $indicatorCode
  ): array
  {
    // Obtemos os dados do provedor
    $this->getAllIndicators();

    $indexes = array_filter($this->indicators, function ($indexList, $code) use ($indicatorCode) {
      return $code == $indicatorCode;
    }, ARRAY_FILTER_USE_BOTH);

    if (!$indexes) {
      return [];
    }

    return $indexes[$indicatorCode];
  }

  /**
   * Obtém o índice atual através do código do indicador
   * financeiro.
   *
   * @param int $indicatorCode
   *   O código do indicador financeiro
   *
   * @return null|Indicator
   *   O índice atual deste indicador financeiro, ou nulo se o mesmo não
   *   estiver disponível
   */
  public function getCurrentIndexFromIndicatorCode(
    int $indicatorCode
  ): ?Indicator
  {
    // Obtemos os dados do provedor
    $this->getAllIndicators();

    foreach ($this->indicators[$indicatorCode] as $indicator) {
      if ( ((new DateTime)->format('m') === $indicator->getDate()->format('m'))
             && ((new DateTime)->format('y') === $indicator->getDate()->format('y')) ) {
        return $indicator;
      }
    }

    return null;
  }
}
