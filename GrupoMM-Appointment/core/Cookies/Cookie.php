<?php
/*
 * This file is part of Extension Library.
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
 * Um gerenciador de cookies seguro.
 *
 * Navegadores modernos dificultam um pouco o usuário comum de modificar
 * um cookie, mas como os cookies fazem parte da solicitação HTTP (e
 * nada em uma solicitação HTTP pode ser confiável) é útil ter uma
 * estratégia para adicionar uma medida de confiança aos cookies.
 *
 * Essa classe usa a hash sha384 para criar uma chave contra adulteração
 * do valor do cookie. Qualquer mudança no valor do cookie será
 * detectada. Neste caso, o cookie modificado será removido e o método
 * get() retornará um valor nulo. Se o cookie estiver intacto, o valor
 * correto será retornado.
 *
 * O cookie terá a seguinte estrutura:
 *   {valor do cookie}|{hash}
 *
 * À esquerda do pipe está o nosso valor armazenado no cookie e a
 * direita está o hash do valor incluindo uma chave de criptografia
 * (salt). Enquanto a string SALT for desconhecida para o atacante,
 * quase não há chance de que um cookie adulterado seja consumido.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Cookies;

use Core\Hashing\HasherInterface;
use Core\Traits\ApplicationTrait;
use Core\Traits\ContainerTrait;
use Psr\Container\ContainerInterface;

class Cookie
  implements CookieInterface
{
  /**
   * Os métodos para manipulação do container
   */
  use ContainerTrait;

  /**
   * Os métodos para manipulação dos aplicativos
   */
  use ApplicationTrait;

  /**
   * A instância do gerador do número de verificação (Hash)
   * 
   * @var HasherInterface
   */
  protected $hasher;

  /**
   * O caractere separador dos dados
   *
   * @var string
   */
  const SEPARATOR = '|';

  /**
   * O nome do nosso cookie.
   * 
   * @var string
   */
  protected $cookieName;

  /**
   * O endereço do domínio.
   * 
   * @var string
   */
  protected $domain;

  /**
   * O path do nosso cookie
   * 
   * @var string
   */
  protected $path;

  /**
   * O nível de segurança do cookie. Indica que o cookie só poderá ser
   * transmitido sob uma conexão segura HTTPS do cliente. Quando
   * configurado para TRUE, o cookie será enviado somente se uma conexão
   * segura existir. No lado do servidor, fica por conta do programador
   * enviar esse tipo de cookie somente sob uma conexão segura (ex:
   * respeitando $_SERVER["HTTPS"]).
   * 
   * @var bool
   */
  protected $secure;

  /**
   * Restrição para Javascript. Quando for TRUE o cookie será acessível
   * somente sob o protocolo HTTP. Isso significa que o cookie não será
   * acessível por linguagens de script, como JavaScript. É dito que
   * essa configuração pode ajudar a reduzir ou identificar roubos de
   * identidade através de ataques do tipo XSS (entretanto ela não é
   * suportada por todos os browsers), mas essa informação é
   * constantemente discutida.
   * 
   * @var bool
   */
  protected $httponly;

  /**
   * Cria um novo gerenciador de cookies.
   * 
   * @param Psr\Container\ContainerInterface $container
   *   A estrutura que contém os containers da aplicação
   * @param HasherInterface $hasher
   *   Um manipulador de hasher
   * @param string $cookieName
   *   O nome do cookie
   * @param array $options
   *   As configurações do cookie
   */
  public function __construct(
    ContainerInterface $container,
    HasherInterface $hasher,
    string $cookieName='anonymous',
    array $options
  )
  {
    // Armazena as configurações
    $this->container = $container;
    $this->hasher    = $hasher;
    unset($container);
    unset($hasher);

    if (isset($options['prefix'])) {
      // O nome do cookie é um nome composto

      // Recupera a URI
      $uri = $_SERVER['REQUEST_URI'];

      // Determina em qual modo estamos (Aplicativo)
      $app  = trim($this->getApplication($uri), '/');
      $path = '/' . $app;
      $mode = (empty($app))
        ? 'Site'
        : strtoupper($app)
      ;

      // Determina o nome como sendo o prefixo mais o modo do aplicativo
      // para permitir separar os dados do site de outras aplicações
      $this->cookieName = $options['prefix'] . "_{$mode}_{$cookieName}";
      $this->path       = $path;
    } else {
      $this->cookieName = $cookieName;
      $this->path       = $options['path'];
    }
    
    // Retira o domínio da informação do host
    $this->domain     = $_SERVER['HTTP_HOST'];
    
    $this->secure     = $options['secure'];
    $this->httponly   = $options['httponly'];
  }

  /**
   * Separa dos dados as partes que compõe o valor do cookie.
   * 
   * @param string $cookieData
   *   Os dados obtidos do cookie
   * 
   * @return array
   */
  protected function parseData(string $cookieData): array
  {
    $pos = strpos($cookieData, self::SEPARATOR);
    $result = [ null, null ];

    if ( !($pos === false) ) {
      // Divide os dados do cookie em duas partes: o valor e a chave
      // contra adulteração
      $dataArray = explode(self::SEPARATOR, $cookieData);

      if (count($dataArray) == 2) {
        $result = $dataArray;
      }
    }

    return $result;
  }

  /**
   * Determina se o conteúdo de $haystack começa por $needle.
   * 
   * @param string $haystack
   *   O string que estamos analisando
   * @param string $needle
   *   O começo que estamos procurando
   * 
   * @return bool
   */
  protected function startsWith(
    string $haystack,
    string $needle
  ): bool
  {
    $length = strlen($needle);

    return (substr($haystack, 0, $length) === $needle);
  }

  /**
   * Recupera um valor armazenado num cookie.
   * 
   * @return mixed
   *   O conteúdo armazenado do cookie
   */
  public function get()
  {
    $value = null;

    if (isset($_COOKIE[$this->cookieName])) {
      // Recupera os dados do cookie
      $cookieData = $_COOKIE[$this->cookieName];

      // Separa o valor codificado da chave contra adulteração
      list($encodedValue, $hash) = $this->parseData($cookieData);

      // Verifica se temos nossa chave contra adulteração
      if (empty($encodedValue)) {
        // Limpa o cookie pois o mesmo não possui um valor e/ou seus
        // valores foram adulterados
        $this->forget();
      } else {
        if ($this->hasher->checkHashFromValue($encodedValue, $hash)) {
          // Decodifica os valores
          $value = unserialize(base64_decode($encodedValue));
        } else {
          // Limpa o cookie pois o mesmo possui valores adulterados
          $this->forget();
        }
      }
    }

    return $value;
  }

  /**
   * Armazena um valor num cookie por um tempo em minutos.
   * Padrão: 180 dias (6 meses).
   * 
   * @param mixed $value
   *   O valor a ser armazenado
   * @param int $lifetime
   *   O tempo de vida do cookie (em segundos)
   */
  public function set($value, int $lifetime=259200): void
  {
    $this->setCookie($value, $this->minutesToLifetime($lifetime));
  }

  /**
   * Remove (esqueçe) um cookie.
   * 
   * @return void
   */
  public function forget()
  {
    if (isset($_COOKIE[$this->cookieName])) {
      $this->setCookie('', $this->minutesToLifetime(-259200));
    }
  }

  /**
   * Converte um valor em minutos para um tempo de vida (carimbo de
   * data/hora no formato UNIX).
   * 
   * @param int $minutes
   *   O tempo em minutos
   * 
   * @return int
   *   O tempo de vida em segundos
   */
  protected function minutesToLifetime(int $minutes): int
  {
    return time() + ($minutes * 60);
  }

  /**
   * Define um cookie PHP.
   * 
   * @param mixed $value
   *   O valor a ser armazenado
   * @param int $lifetime
   *   O tempo de vida do cookie (em segundos)
   */
  protected function setCookie($value, int $lifetime): void
  {
    // Verifica se temos um valor
    if (empty($value)) {
      $cookieValue = '';
    } else {
      // Primeiramente codifica o valor usando JSON
      $encodedValue = base64_encode(serialize($value));

      // Agora gera um hash de nosso valor
      $hash = $this->hasher->hash($encodedValue);

      // Determina o valor a ser armazenado
      $cookieValue = $encodedValue . self::SEPARATOR . $hash;
    }

    // Grava nosso cookie
    if (PHP_VERSION_ID < 70300) {
      setcookie(
        $this->cookieName,
        $cookieValue,
        $lifetime,
        "{$this->path}; sameSite=Lax",
        $this->domain,
        $this->secure,
        $this->httponly)
      ;
    } else {
      setcookie(
        $this->cookieName,
        $cookieValue,
        [ 'expires' => $lifetime,
          'path' => $this->path,
          'domain' => $this->domain,
          'samesite' => 'Lax',
          'secure' => $this->secure,
          'httponly' => $this->httponly
        ])
      ;
    }
  }
}
