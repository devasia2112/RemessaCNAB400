<?php
/**
 * SantanderRemessa.php (Classe Santander Remessa Padrão CNAB400)
 *
 * Gerador de arquivo de remessa para o banco Santander.
 * Com essa classe o usuário não fica obrigado a usar um framework para poder 
 * gerar o arquivo de remessa do banco santander, o objetivo foi criar uma classe 
 * apenas, para facilitar a geração desses arquivos de remessa, supondo que o 
 * usuário já possua a classe para gerar o boleto. Essa classe requer os dados 
 * gerados pelo boleto.
 *
 * PHP Version >=5.6
 *
 * @category    CNAB400
 * @package     Remessa
 * @author      Costa <deepcell@gmail.com>
 * @license     https://www.gnu.org/licenses/gpl-3.0.txt  GNU GENERAL PUBLIC LICENSE Version 3
 * @version     1.0.0
 * @link        https://github.com/deepcell/RemessaCNAB400
 * @see         CNAB400, Remessa, Santander
 * @since       File available since Release 1.0.0
 *
 * @reference
 *              Especificação/referência: Documento do banco Santander
 *              Baseado no `laravel-boleto` (https://github.com/eduardokum/laravel-boleto/)
 * @status      Homologado pelo banco Santander.
 * @file encode UTF-8
 * @date        2017-12-18
 * @update      2018-02-26
 * 
 * @dependency  extensao `php5-intl`
 * @observation Essa classe com um pouco de alteração funciona para outros bancos.
 */
class SantanderRemessa
{
	/**
	* Declaracao das propriedades (property declaration).
	* Campos obrigatorios do arquivo de remessa santander.
	* use `$this->` para chamar a propriedade
	*/
	public $tamanho_linha = 400;          # se for trabalhar com o layout de 240, entao setar para valor `false`.
	public $camposObrigatorios = array(
		'carteira',
		'agencia',
		'conta',
		'beneficiario',
	);
	public $boletos = [];
	public $codigoBanco = '033';          # 033 banco santander
    public $iRegistros = 0;
	public $aRegistros = [
	    self::HEADER  => [],
	    self::DETALHE => [],
	    self::TRAILER => [],
	];
	public $atual;
	public $fimLinha = "\r\n";            # padrao inicial \n
	public $fimArquivo = "\r\n";          # null;
	public $idremessa;
    public $numeroControle;               # numero controle da remessa
    public $agencia = '0123';
    public $agenciaDv = 7;                # digito verificador da agencia
    public $conta_movimento = '01234567'; # Conta movimento Beneficiário 8 posicoes
    public $conta = '9308270';            # Conta cobrança Beneficiário 7 posicoes
	public $contaDv;
    public $carteira = 101;
	public $carteiras = [101];            # se for trabalhar com outras carteiras deixe vazio essa propriedade.
    public $pagador;
    public $beneficiario = array(
        'nome' => 'BAVARIAN ILLUMINATI LTDA - ME',
        'endereco' => 'RUA PIO XI, 23',
        'bairro' => 'LAPA',
        'cep' => '05060001',
        'uf' => 'SP',
        'cidade' => 'SAO PAULO',
        'documento' => '012012012000901',
        'nome_documento' => '',
        'endereco2' => ''
    );
    public $beneficiarioDocumento = '012012012000901';  # nao usar essa propriedade
	public $codigoCliente;
	public $total = 0;                    # valor total dos titulos
    //--   pessoa
    public $nome;                         # nome da pessoa/cliente
    public $endereco;
    public $bairro;
    public $cep;
    public $uf;
    public $cidade;
    public $documento;
    public $dda = false;

    public $campoNossoNumero = 0;
    public $status;
    public $numeroDocumento;


	//--  use self:: para chamar a constante
	const COD_BANCO_SANTANDER = '033';
	const STATUS_REGISTRO = 1;
	const STATUS_ALTERACAO = 2;
	const STATUS_BAIXA = 3;
	const HEADER = 'header';
	const HEADER_LOTE = 'header_lote';
	const DETALHE = 'detalhe';
	const TRAILER_LOTE = 'trailer_lote';
	const TRAILER = 'trailer';
	//--  SANTANDER
    const ESPECIE_DUPLICATA = '01';
    const ESPECIE_NOTA_PROMISSORIA = '02';
    const ESPECIE_NOTA_SEGURO = '03';
    const ESPECIE_RECIBO = '05';
    const ESPECIE_DUPLICATA_SERVICO = '06';
    const ESPECIE_LETRA_CAMBIO = '07';
    const OCORRENCIA_REMESSA = '01';
    const OCORRENCIA_PEDIDO_BAIXA = '02';
    const OCORRENCIA_CONCESSAO_ABATIMENTO = '04';
    const OCORRENCIA_CANC_ABATIMENTO = '05';
    const OCORRENCIA_ALT_VENCIMENTO = '06';
    const OCORRENCIA_ALT_CONTROLE_PARTICIPANTE = '07';
    const OCORRENCIA_ALT_SEUNUMERO = '08';
    const OCORRENCIA_PROTESTAR = '09';
    const OCORRENCIA_SUSTAR_PROTESTO = '18';
    const INSTRUCAO_SEM = '00';
    const INSTRUCAO_BAIXAR_APOS_VENC_15 = '02';
    const INSTRUCAO_BAIXAR_APOS_VENC_30 = '03';
    const INSTRUCAO_NAO_BAIXAR = '04';
    const INSTRUCAO_PROTESTAR = '06';
    const INSTRUCAO_NAO_PROTESTAR = '07';
    const INSTRUCAO_NAO_COBRAR_MORA = '08';



	/***********************************************************************************************
	 * @Util methods 
	 * Reference: https://github.com/eduardokum/laravel-boleto/blob/master/src/Util.php
	 ***********************************************************************************************/
    /**
     * @return string
     */
    public static function dateCheck($date) 
    {
        $tmpDate = explode('-', $date);
        // checkdate(month, day, year)
        return checkdate($tmpDate[1], $tmpDate[2], $tmpDate[0]);
    }

    /**
     * @return string
     */
    public static function appendStrings()
    {
        $strings = func_get_args();
        $appended = null;
        foreach ($strings as $string) {
            $appended .= " $string"; // add a space in between the strings
        }
        return trim($appended);
    }

    /**
     * Retorna a String em MAIUSCULO
     *
     * @param String $string
     *
     * @return String
     */
    public static function upper($string)
    {
        return strtr(mb_strtoupper($string), "àáâãäåæçèéêëìíîïðñòóôõö÷øùüúþÿ", "ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖ×ØÙÜÚÞß");
    }

    /**
     * Retorna a String em minusculo
     *
     * @param String $string
     *
     * @return String
     */
    public static function lower($string)
    {
        return strtr(mb_strtolower($string), "ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖ×ØÙÜÚÞß", "àáâãäåæçèéêëìíîïðñòóôõö÷øùüúþÿ");
    }

    /**
     * Retorna a primeira posição da String em maiusculo e o restante em minusculo.
     *
     * @param String $string
     *
     * @return String
     */
    public static function upFirst($string)
    {
        return ucfirst(self::lower($string));
    }

    /**
     * Retorna somente as letras da string
     *
     * @param String $string
     *
     * @return String
     */
    public static function lettersOnly($string)
    {
        return preg_replace('/[^[:alpha:]]/', '', $string);  // se precisar manter o espaco orginal (se houver) na string inclua-o aqui.
    }

    /**
     * Retorna TUDO oque nao for letras na string
     *
     * @param String $string
     *
     * @return String
     */
    public static function lettersNot($string)
    {
        return preg_replace('/[[:alpha:]]/', '', $string);
    }

    /**
     * Retorna somente os digitos da string e tambem remove espacos em branco
     *
     * @param String $string
     *
     * @return String
     */
    public static function numbersOnly($string)
    {
        return preg_replace('/[^[:digit:]]/', '', $string);
    }

    /**
     * Retorna a string sem os digitos - remove digitos da string
     *
     * @param String $string
     *
     * @return String
     */
    public static function numbersNot($string)
    {
        return preg_replace('/[[:digit:]]/', '', $string);
    }

    /**
     * Retorna somente alfanumericos (remove caracteres especiais)
     * Obs.: Tambem remove acentos, para limpar acentos  numa string 
     *       substituindo o caractere pelo seu correspondente sem acento 
     *       use o metodo `normalizeChars($string)`.
     *
     * @param String $string
     *
     * @return String
     */
    public static function alphanumberOnly($string)
    {
        return preg_replace('/[^[:alnum:]]/', '', $string);
    }

    /**
     * Função para limpar acentos de uma string
     *
     * @param  string $string
     * @return string
     */
    public static function normalizeChars($string)
    {
        $normalizeChars = array(
            'Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Å' => 'A', 'Ä' => 'A', 'Æ' => 'AE', 'Ç' => 'C',
            'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E', 'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I', 'Ð' => 'Eth',
            'Ñ' => 'N', 'Ó' => 'O', 'Ò' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ø' => 'O',
            'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ý' => 'Y', 'Ŕ' => 'R',
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'å' => 'a', 'ä' => 'a', 'æ' => 'ae', 'ç' => 'c',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i', 'ð' => 'eth',
            'ñ' => 'n', 'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ø' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ý' => 'y', 'ŕ' => 'r', 'ÿ' => 'y',
            'ß' => 'sz', 'þ' => 'thorn',
        );
        return strtr($string, $normalizeChars);
    }

    /**
     * Mostra o Valor no float Formatado
     *
     * @param  string  $number
     * @param  integer $decimals
     * @param  boolean $showThousands
     * @return string
     */
    public static function nFloat($number, $decimals = 2, $showThousands = false)
    {
        if (is_null($number) || empty($number)) {
            return '';
        }
        $pontuacao = preg_replace('/[0-9]/', '', $number);
        $locale = (mb_substr($pontuacao, -1, 1) == ',') ? "pt-BR" : "en-US";
        $formater = new \NumberFormatter($locale, \NumberFormatter::DECIMAL);
        if ($decimals === false) {
            $decimals = 2;
            preg_match_all('/[0-9][^0-9]([0-9]+)/', $number, $matches);
            if (!empty($matches[1])) {
                $decimals = mb_strlen(rtrim($matches[1][0], 0));
            }
        }
        return number_format($formater->parse($number, \NumberFormatter::TYPE_DOUBLE), $decimals, '.', ($showThousands ? ',' : ''));
    }

    /**
     * Mostra o Valor no real Formatado
     *
     * @param  float   $number
     * @param  boolean $fixed
     * @param  boolean $symbol
     * @param  integer $decimals
     * @return string
     */
    public static function nReal($number, $decimals = 2, $symbol = true, $fixed = true)
    {
        if (is_null($number) || empty($number)) {
            return '';
        }
        $formater = new \NumberFormatter("pt-BR", \NumberFormatter::CURRENCY);
        $formater->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, ($fixed ? $decimals : 1));
        if ($decimals === false) {
            $decimals = 2;
            preg_match_all('/[0-9][^0-9]([0-9]+)/', $number, $matches);
            if (!empty($matches[1])) {
                $decimals = mb_strlen(rtrim($matches[1][0], 0));
            }
        }
        $formater->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, $decimals);
        if (!$symbol) {
            $pattern = preg_replace("/[¤]/", '', $formater->getPattern());
            $formater->setPattern($pattern);
        } else {
            // ESPAÇO DEPOIS DO SIMBOLO
            $pattern = str_replace("¤", "¤ ", $formater->getPattern());
            $formater->setPattern($pattern);
        }
        return $formater->formatCurrency($number, $formater->getTextAttribute(\NumberFormatter::CURRENCY_CODE));
    }

    /**
     * Retorna a percentagem de um valor
     *
     * @param $big
     * @param $percent
     *
     * @return string
     */
    public static function percent($big, $percent)
    {
        if ($percent < 0.01) {
            return 0;
        }
        return self::nFloat($big*($percent/100));
    }

    /**
     * Função para mascarar uma string, mascara tipo $mask="###.###.###-####/##"
     *
     * @param string $val
     * @param string $mask
     *
     * @return string
     */
    public static function maskString($val, $mask)
    {
        if (empty($val)) {
            return $val;
        }
        $maskared = '';
        $k = 0;
        if (is_numeric($val)) {
            $val = sprintf('%0' . mb_strlen(preg_replace('/[^#]/', '', $mask)) . 's', $val);
        }
        for ($i = 0; $i <= mb_strlen($mask) - 1; $i++) {
            if ($mask[$i] == '#') {
                if (isset($val[$k])) {
                    $maskared .= $val[$k++];
                }
            } else {
                if (isset($mask[$i])) {
                    $maskared .= $mask[$i];
                }
            }
        }
        return $maskared;
    }

    /**
     * @param $n
     * @param integer $loop
     * @param $insert
     *
     * @return string
     * Not working with long numbers (15 figures for instance)
     * solution: make this number a string wraping it around double quotes
     */
    public static function numberFormatGeneral($n, $loop, $insert = 0)
    {
        // Removo os caracteras a mais do que o pad solicitado caso a string seja maior
        $n = mb_substr(self::numbersOnly($n), 0, $loop);
        return str_pad($n, $loop, $insert, STR_PAD_RIGHT); // params 1=input, 2=pad length, 3=pad string, 4=pad type (STR_PAD_RIGHT, STR_PAD_LEFT, or STR_PAD_BOTH)
    }

    /**
     * @param         $tipo               (9 numeric or X string)
     * @param         $valor
     * @param         integer $tamanho
     * @param int     $dec               decimal size
     * @param string  $sFill
     *
     * @return string
     * @throws \Exception
     */
    public static function formatCnab($tipo, $valor, $tamanho, $dec = 0, $sFill = '')
    {
        $tipo = self::upper($tipo);
        if (in_array($tipo, array('9', 9, 'N', '9L', 'NL'))) {
            if ($tipo == '9L' || $tipo == 'NL') {
                $valor = self::numbersOnly($valor);
            }
            $left = '';
            $sFill = 0;
            $type = 's';
            $valor = ($dec > 0) ? sprintf("%.{$dec}f", $valor) : $valor;
            $valor = str_replace(array(',', '.'), '', $valor);
        } elseif (in_array($tipo, array('A', 'X'))) {
            $left = '-';
            $type = 's';
            $valor = self::upper(self::normalizeChars($valor));
        } else {
            throw new \Exception('Tipo inválido');
        }
        return sprintf("%{$left}{$sFill}{$tamanho}{$type}", mb_substr($valor, 0, $tamanho));
    }

    /**
     * @param     $n
     * @param int $factor
     * @param int $base
     * @param int $x10
     * @param int $resto10
     *
     * @return int
     *
     */
    public static function modulo11($n, $factor = 2, $base = 9, $x10 = 0, $resto10 = 0)
    {
        $sum = 0;
        for ($i = mb_strlen($n); $i > 0; $i--) {
            $sum += mb_substr($n, $i - 1, 1)*$factor;
            if ($factor == $base) {
                $factor = 1;
            }
            $factor++;
        }
        if ($x10 == 0) {
            $sum *= 10;
            $digito = $sum%11;
            if ($digito == 10) {
                $digito = $resto10;
            }
            return $digito;
        }
        return $sum%11;
    }
    /**
     * @param $n
     *
     * @return int
     */
    public static function modulo10($n)
    {
        $chars = array_reverse(str_split($n, 1));
        $odd = array_intersect_key($chars, array_fill_keys(range(1, count($chars), 2), null));
        $even = array_intersect_key($chars, array_fill_keys(range(0, count($chars), 2), null));
        $even = array_map(
            function ($n) {
                return ($n >= 5) ? 2*$n - 9 : 2*$n;
            }, $even
        );
        $total = array_sum($odd) + array_sum($even);
        return ((floor($total/10) + 1)*10 - $total)%10;
    }

    /**
     * @param array $a
     *
     * @return string
     * @throws \Exception
     *
     * Esse metodo pode ser usado para validar chaves de moeda digital, alterar preg_match nesse caso.
     */
    public static function array2Controle($a)
    {
        if (preg_match('/[0-9]/', implode('', array_keys($a)))) {
            throw new \Exception('Somente chave alfanumérica no array, para separar o controle pela chave');
        }
        $controle = '';
        foreach ($a as $key => $value) {
            $controle .= sprintf('%s%s', $key, $value);
        }
        if (mb_strlen($controle) > 25) {
            throw new \Exception('Controle muito grande, máximo permitido de 25 caracteres');
        }
        return $controle;
    }

    /**
     * @param $controle
     *
     * @return null|string
     */
    public static function controle2array($controle)
    {
        $matches = '';
        $matches_founded = [];
        preg_match_all('/(([A-Za-zÀ-Úà-ú]+)([0-9]*))/', $controle, $matches, PREG_SET_ORDER);
        if ($matches) {
            foreach ($matches as $match) {
                $matches_founded[$match[2]] = (int) $match[3];
            }
            return $matches_founded;
        }
        return [$controle];
    }

    /**
     * Remove trecho do array.
     *
     * @param $i
     * @param $f
     * @param $array
     *
     * @return string
     * @throws \Exception
     */
    public static function remove($i, $f, &$array)
    {
        if (is_string($array)) {
            $array = str_split(rtrim($array, chr(10) . chr(13) . "\n" . "\r"), 1);
        }
        $i--;
        if ($i > 398 || $f > 400) {
            throw new \Exception('$ini ou $fim ultrapassam o limite máximo de 400');
        }
        if ($f < $i) {
            throw new \Exception('$ini é maior que o $fim');
        }
        $t = $f - $i;
        $toSplice = $array;
        
        if($toSplice != null)
            return trim(implode('', array_splice($toSplice, $i, $t)));
        else
            return;
    }

    /**
     * Função para add valor a linha nas posições informadas.
     *
     * @param $line
     * @param integer $i
     * @param integer $f
     * @param $value
     *
     * @return array
     * @throws \Exception
     */
    public static function adiciona(&$line, $i, $f, $value)
    {
        $i--;
        if ($i > 398 || $f > 400) {
            throw new \Exception('$ini ou $fim ultrapassam o limite máximo de 400');
        }
        if ($f < $i) {
            throw new \Exception('$ini é maior que o $fim');
        }
        $t = $f - $i;
        if (mb_strlen($value) > $t) {
            throw new \Exception(sprintf('String $valor maior que o tamanho definido em $ini e $fim: $valor=%s e tamanho é de: %s', mb_strlen($value), $t));
        }
        $value = sprintf("%{$t}s", $value);
        $value = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);
        return array_splice($line, $i, $t, $value);
    }

    /**
     * Validação para o tipo de cnab 240
     *
     * @param  $content
     * @return bool
     */
    public static function isCnab240($content)
    {
        $content = is_array($content) ? $content[0] : $content;
        $content = Encoding::toUTF8($content);
        return mb_strlen(rtrim($content, "\r\n")) == 240 ? true : false;
    }
    /**
     * Validação para o tipo de cnab 400
     *
     * @param  $content
     * @return bool
     */
    public static function isCnab400($content)
    {
        $content = is_array($content) ? $content[0] : $content;
        $content = Encoding::toUTF8($content);
        return mb_strlen(rtrim($content, "\r\n")) == 400 ? true : false;
    }

    /**
     * Valida se o header é de um arquivo retorno valido, 240 ou 400 posicoes
     *
     * @param $header
     *
     * @return bool
     */
    public static function isHeaderRetorno($header)
    {
        if (!self::isCnab240($header) && !self::isCnab400($header)) {
            return false;
        }
        if (self::isCnab400($header) && mb_substr($header, 0, 9) != '02RETORNO') {
            return false;
        }
        if (self::isCnab240($header) && mb_substr($header, 142, 1) != '2') {
            return false;
        }
        return true;
    }



	/***********************************************************************************************
	 * @CNAB REMESSA abstract methods
	 * Reference: https://github.com/eduardokum/laravel-boleto/blob/master/src/Cnab/Remessa/AbstractRemessa.php
	 ***********************************************************************************************/
    /**
     * Retorna o Nosso Número calculado.
     *
     * @return string
     */
    public function getNossoNumero()
    {
        if (empty($this->campoNossoNumero)) {
            return $this->campoNossoNumero += 1;
        }
        return $this->campoNossoNumero;
    }

    /**
     * Gera o Nosso Número.
     *
     * @return string
     */
    public function gerarNossoNumero()
    {
        // nao usado
    }

    /**
     * Retorna o código do banco
     *
     * @return string
     */
    public function getCodigoBanco()
    {
        return $this->codigoBanco;
    }

    /**
     * @return mixed
     */
    public function getIdremessa()
    {
        return $this->idremessa;
    }

    /**
     * @param mixed $idremessa
     *
     * @return AbstractRemessa
     */
    public function setIdremessa($idremessa)
    {
        $this->idremessa = $idremessa;
        return $this;
    }

    /**
     * @return PessoaContract
     */
    public function getBeneficiario()
    {
        return $this->beneficiario;
    }

    /**
     * @param $beneficiario
     *
     * @return AbstractRemessa
     * @throws \Exception
     */
    public function setBeneficiario($beneficiario)
    {
        Util::addPessoa($this->beneficiario, $beneficiario);
        return $this;
    }

    /**
     * Retorna o campo Número do documento da remessa
     *
     * @return string
     */
    public function getNumeroDocumento()
    {
        return $this->numeroDocumento;
    }

    /**
     * @return get document beneficiario
     */
    public function getDocumento()
    {
        return $this->beneficiarioDocumento;
    }

    /**
     * Retorna o número definido pelo cliente para controle da remessa
     *
     * @return int
     */
    public function getNumeroControle()
    {
        return $this->numeroControle;
    }

    /**
     * Define a agência
     *
     * @param  int $agencia
     *
     * @return AbstractRemessa
     */
    public function setAgencia($agencia)
    {
        $this->agencia = (string) $agencia;
        return $this;
    }

    /**
     * Retorna a agência
     *
     * @return int
     */
    public function getAgencia()
    {
        return $this->agencia;
    }

    /**
     * Define o número da conta
     *
     * @param  int $conta
     *
     * @return AbstractRemessa
     */
    public function setConta($conta)
    {
        $this->conta = (string) $conta;
        return $this;
    }

    /**
     * Retorna o número da conta
     *
     * @return int
     */
    public function getConta()
    {
        return $this->conta;
    }

    /**
     * Define o dígito verificador da conta
     *
     * @param  int $contaDv
     *
     * @return AbstractRemessa
     */
    public function setContaDv($contaDv)
    {
        $this->contaDv = substr($contaDv, - 1);
        return $this;
    }

    /**
     * Retorna o dígito verificador da conta
     *
     * @return int
     */
    public function getContaDv()
    {
        return $this->contaDv;
    }

    /**
     * Define o código da carteira (Com ou sem registro)
     *
     * @param  string $carteira
     *
     * @return AbstractRemessa
     * @throws \Exception
     */
    public function setCarteira($carteira)
    {
        if (! in_array($carteira, $this->getCarteiras())) {
            throw new \Exception("Carteira não disponível!");
        }
        $this->carteira = $carteira;
        return $this;
    }

    /**
     * Retorna o código da carteira (Com ou sem registro)
     *
     * @return string
     */
    public function getCarteira()
    {
        return $this->carteira;
    }

    /**
     * Retorna o código da carteira (Com ou sem registro)
     *
     * @return string
     */
    public function getCarteiraNumero()
    {
        return $this->carteira;
    }

    /**
     * Retorna as carteiras disponíveis para este banco
     *
     * @return array
     */
    public function getCarteiras()
    {
        return $this->carteiras;
    }

    /**
     * Método que valida se o banco tem todos os campos obrigadotorios preenchidos
     *
     * @return boolean
     */
    public function isValid(&$messages)
    {
        foreach ($this->camposObrigatorios as $campo) {
            $test = call_user_func([$this, 'get' . ucwords($campo)]);
            if ($test === '' || is_null($test)) {
                $messages .= "Campo $campo está em branco";
                return false;
            }
        }
        return true;
    }

    /**
     * @return int
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Get Count used for:
     * > Número sequencial do registro no arquivo $add=2
     * > Quantidade de documentos no arquivo $add=0
     *
     * @return int
     */
    public function getCount($add)
    {
        return count($this->aRegistros[self::DETALHE]) + $add;
    }

    /**
     * Função para adicionar multiplos boletos.
     *
     * @param array $boletos
     *
     * @return $this
     */
    public function addBoletos(array $boletos)
    {
        foreach ($boletos as $boleto) {
            $this->addBoleto($boleto);
        }
        return $this;
    }

    /**
     * Função para add valor a linha nas posições informadas.
     *
     * @param integer $i
     * @param integer $f
     * @param         $value
     *
     * @return array
     * @throws \Exception
     */
    public function add($i, $f, $value)
    {
        return $this->adiciona($this->atual, $i, $f, $value);
    }

    /**
     * Retorna o header do arquivo.
     *
     * @return mixed
     */
    public function getHeader()
    {
        return $this->aRegistros[self::HEADER];
    }

    /**
     * Retorna os detalhes do arquivo
     *
     * @return \Illuminate\Support\Collection
     */
    public function getDetalhes()
    {
        # !IMPORTANT collecttion will work only with PHP7+
        //return collect($this->aRegistros[self::DETALHE]);
        return $this->aRegistros[self::DETALHE];
    }

    /**
     * Retorna o trailer do arquivo.
     *
     * @return mixed
     */
    public function getTrailer()
    {
        return $this->aRegistros[self::TRAILER];
    }

    /**
     * Valida se a linha esta correta.
     *
     * @param array $a
     *
     * @return string
     * @throws \Exception
     */
    public function valida(array $a)
    {
        if ($this->tamanho_linha === false) {
            throw new \Exception('Classe remessa deve informar o tamanho da linha');
        }
        $a = array_filter($a, 'strlen');
        if (count($a) != $this->tamanho_linha) {
            throw new \Exception(sprintf('$a não possui %s posições, possui: %s', $this->tamanho_linha, count($a)));
        }
        return implode('', $a);
    }

    /**
     * Salva o arquivo no path informado
     *
     * @param $path
     *
     * @return mixed
     * @throws \Exception
     */
    public function save($path)
    {
        $folder = dirname($path);
        if (! is_dir($folder)) {
            mkdir($folder, 0777, true);
        }
        if (! is_writable(dirname($path))) {
            throw new \Exception('Path ' . $folder . ' não possui permissao de escrita');
        }
        $string = $this->gerar();
        file_put_contents($path, $string);
        return $path;
    }

    /**
     * Realiza o download da string retornada do metodo gerar
     *
     * @param null $filename
     *
     * @throws \Exception
     */
    public function download($filename = null)
    {
        if ($filename === null) {
            $filename = 'remessa.txt';
        }
        header('Content-type: text/plain');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $this->gerar();
    }



	/***********************************************************************************************
	 * @CNAB400 REMESSA abstract methods 
	 * Reference: https://github.com/eduardokum/laravel-boleto/blob/master/src/Cnab/Remessa/Cnab400/AbstractRemessa.php
	 ***********************************************************************************************/
    /**
     * Inicia a edição do header
     */
    public function iniciaHeader()
    {
        $this->aRegistros[self::HEADER] = array_fill(0, $this->tamanho_linha, ' ');
        $this->atual = &$this->aRegistros[self::HEADER];
    }	

    /**
     * Inicia a edição do trailer (footer).
     */
    public function iniciaTrailer()
    {
        $this->aRegistros[self::TRAILER] = array_fill(0, $this->tamanho_linha, ' ');
        $this->atual = &$this->aRegistros[self::TRAILER];
    }

    /**
     * Inicia uma nova linha de detalhe e marca com a atual de edição
     */
    public function iniciaDetalhe()
    {
        $this->iRegistros++;
        $this->aRegistros[self::DETALHE][$this->iRegistros] = array_fill(0, $this->tamanho_linha, ' ');
        $this->atual = &$this->aRegistros[self::DETALHE][$this->iRegistros];
    }

    /**
     * Gera o arquivo, retorna a string.
     *
     * @return string
     * @throws \Exception
     */
    public function gerar()
    {
        if (!$this->isValid($messages)) {
            throw new \Exception('Campos requeridos pelo banco, aparentam estar ausentes ' . $messages);
        }
        $stringRemessa = '';
        if ($this->iRegistros < 1) {
            throw new \Exception('Nenhuma linha detalhe foi adicionada');
        }
        $this->header();
        $stringRemessa .= $this->valida($this->getHeader()) . $this->fimLinha;
        foreach ($this->getDetalhes() as $i => $detalhe) {
            $stringRemessa .= $this->valida($detalhe) . $this->fimLinha;
        }
        $this->trailer();
        $stringRemessa .= $this->valida($this->getTrailer()) . $this->fimArquivo;
        return $stringRemessa;
    }



	/***********************************************************************************************
	 * @CNAB400 REMESSA métodos Santander
	 * Reference: https://github.com/eduardokum/laravel-boleto/blob/master/src/Cnab/Remessa/Cnab400/Banco/Santander.php
	 ***********************************************************************************************/
    /**
     * Define o nome
     *
     * @param string $nome
     */
    public function setNome($nome)
    {
        $this->nome = $nome;
    }

    /**
     * Retorna o nome
     *
     * @return string
     */
    public function getNome()
    {
        return $this->nome;
    }

    /**
     * Retorna o codigo do cliente.
     *
     * @return string
     */
    public function getCodigoCliente()
    {
        return $this->codigoCliente;
    }

    /**
     * Seta o codigo do cliente.
     *
     * @param mixed $codigoCliente
     *
     * @return Santander
     */
    public function setCodigoCliente($codigoCliente)
    {
        $this->codigoCliente = $codigoCliente;
        return $this;
    }

    /**
     * Retorna o codigo de transmissão.
     * Código de Transmissão (400 posições):
     * @return string
     * @throws \Exception
     */
    public function getCodigoTransmissaoHeader()
    {
        $conta = $this->getConta();
        if (strlen($conta) == 9) {
            $conta = substr($conta, 0, 7);
        }
        // cod. de transmissão fornecido pelo banco + numero da conta
        // string precisa conter 20 caracteres
        // se cod. de transmissão + numero da conta não tiver 20 caracteres, 
        // preencher o numero da conta com zero(s) a esquerda como especificado nos docs do banco.
        $codTransmissao = '080809308270';
        $codTransmissao = $codTransmissao + $conta;  // ex.: 01230123456701234567

        return $this->formatCnab('9', $codTransmissao, 20);
    }
    /**
     * Usar apenas com o metodo AddBoleto (detalhe)
     * @return string
     * @throws \Exception
     */
    public function getCodigoTransmissaoAddBoleto()
    {
        // 18 - 37
        // 18 - 21
        // 22 - 29
        // 30 - 37
        $contaCobranca = '01234567';
        return $this->formatCnab('9', $this->getAgencia(), 4)
             . $this->formatCnab('9', $contaCobranca, 8)  // usar a conta cobranca aqui
             . $this->formatCnab('9', $this->conta_movimento, 8);
    }

    public function header()
    {
        $this->iniciaHeader();
        $this->add(1, 1, '0');
        $this->add(2, 2, '1');
        $this->add(3, 9, 'REMESSA');
        $this->add(10, 11, '01');
        $this->add(12, 26, $this->formatCnab('X', 'COBRANCA', 15));
        $this->add(27, 46, $this->formatCnab('9', $this->getCodigoTransmissaoHeader(), 20));
        $this->add(47, 76, $this->formatCnab('X', 'BAVARIAN ILLUMINATI LTDA', 30));
        $this->add(77, 79, $this->getCodigoBanco());
        $this->add(80, 94, $this->formatCnab('X', 'SANTANDER', 15));
        $this->add(95, 100, date('dmy'));  # data da gravacao  formato -> dmy
        $this->add(101, 116, $this->formatCnab('9', '0', 16));
        $this->add(117, 391, '');
        $this->add(392, 394, '000');
        $this->add(395, 400, $this->formatCnab('9', 1, 6));
        return $this;
    }

    // formatCnab($tipo, $valor, $tamanho, $dec = 0, $sFill = '')
    public function addBoleto($boleto)
    {
        $this->boletos[] = $boleto;
        $this->iniciaDetalhe();
        $this->total += $boleto['valor_documento'];
        $this->add(1, 1, '1');
        $this->add(2, 3, '02');
        $this->add(4, 17, $this->formatCnab('9L', substr($this->getDocumento(),1), 14));
        $this->add(18, 37, $this->formatCnab('9', $this->getCodigoTransmissaoAddBoleto(), 20)); // inclui Código da agência, Conta Movimento e COnta cobranca do Beneficiário.
        $this->add(38, 62, $this->formatCnab('X', $boleto['numeroDocumento'], 25));  // numero de controle da remessa
        $this->add(63, 70, substr($this->numbersOnly($boleto['numero']), -8));
        $this->add(71, 76, '000000');
        $this->add(77, 77, '');
        $this->add(78, 78, ($boleto['multa'] > 0 ? '4' : '0'));
        $this->add(79, 82, $this->formatCnab('9', '0200', 4)); // 2% conforme oque foi escrito no boleto -- $this->percent($boleto['valor_cobrado'], $percent=2)
        $this->add(83, 84, '00');
        $this->add(85, 97, $this->formatCnab('9', 0, 13, 2));
        $this->add(98, 101, '');
        $this->add(102, 107, $boleto['juros'] === false ? '000000' : date('dmy', strtotime('+6 days')));  # Data para cobrança de multa
        $this->add(108, 108, $this->getCarteiraNumero() > 200 ? '1' : '5');
        $this->add(109, 110, self::OCORRENCIA_REMESSA); // REGISTRO
        if ($this->getStatus() == self::STATUS_BAIXA) {
            $this->add(109, 110, self::OCORRENCIA_PEDIDO_BAIXA); // BAIXA
        }
        if ($this->getStatus() == self::STATUS_ALTERACAO) {
            $this->add(109, 110, self::OCORRENCIA_ALT_VENCIMENTO); // ALTERAR VENCIMENTO
        }
        $this->add(111, 120, $this->formatCnab('X', $boleto['numeroDocumento'], 10));
        $this->add(121, 126, $boleto['dataVencimento']);
        $this->add(127, 139, $this->formatCnab('9', $boleto['valor_documento'], 13, 2));
        $this->add(140, 142, $this->getCodigoBanco());
        $this->add(143, 147, $this->formatCnab('9', $this->getAgencia() . $this->agenciaDv, 5)); // if != carteira 5 then '00000'
        $this->add(148, 149, $boleto['especieDoc']);
        $this->add(150, 150, $boleto['aceite']);
        $this->add(151, 156, date('dmy'));   # Data da emissão do título , no formato -> date('dmy')
        $this->add(157, 158, $this->formatCnab('9', self::INSTRUCAO_SEM, 2));
        $this->add(159, 160, $this->formatCnab('9', self::INSTRUCAO_SEM, 2));
        if ($boleto['diasProtesto'] > 0) {
            $this->add(157, 158, self::INSTRUCAO_PROTESTAR);
        } elseif ($boleto['diasBaixaAutomatica'] == 15) {
            $this->add(157, 158, self::INSTRUCAO_BAIXAR_APOS_VENC_15);
        } elseif ($boleto['diasBaixaAutomatica'] == 30) {
            $this->add(157, 158, self::INSTRUCAO_BAIXAR_APOS_VENC_30);
        }
        $this->add(161, 173, $this->formatCnab('9', $this->percent($boleto['valor'], $percent=1), 13, 2)); // Valor de mora a ser cobrado por dia de atraso
        $this->add(174, 179, $boleto['desconto'] > 0 ? $boleto['data_desconto'] : '000000');
        $this->add(180, 192, $this->formatCnab('9', $boleto['desconto'], 13, 2));
        $this->add(193, 205, $this->formatCnab('9', 0, 13, 2));
        $this->add(206, 218, $this->formatCnab('9', 0, 13, 2));
        //-- pagador
        $this->add(219, 220, strlen($this::numbersOnly($boleto['pagador']['documento'])) == 14 ? '02' : '01');
        $this->add(221, 234, $this->formatCnab('9L', $boleto['pagador']['documento'], 14));
        $this->add(235, 274, $this->formatCnab('X', $boleto['pagador']['nome'], 40));
        $this->add(275, 314, $this->formatCnab('X', $boleto['pagador']['endereco'], 40));
        $this->add(315, 326, $this->formatCnab('X', $boleto['pagador']['bairro'], 12));
        $this->add(327, 334, $this->formatCnab('9L', $boleto['pagador']['cep'], 8));
        $this->add(335, 349, $this->formatCnab('X', $boleto['pagador']['cidade'], 15));
        $this->add(350, 351, $this->formatCnab('X', $boleto['pagador']['uf'], 2));
        $this->add(352, 381, $this->formatCnab('X', '', 30)); // 30 brancos
        $this->add(382, 382, '');
        $this->add(383, 383, 'i');
        $this->add(384, 385, substr($boleto['conta'], -2));
        /*
        if (strlen($this->getConta()) == 9) {
            $this->add(384, 385, substr($this->getConta(), -2));
        }
        */
        $this->add(386, 391, '');
        $this->add(392, 393, $this->formatCnab('9', $boleto['diasProtesto'], 2));
        $this->add(394, 394, '');
        $this->add(395, 400, $this->formatCnab('9', $this->iRegistros + 1, 6));
        return $this;
    }

    public function trailer()
    {
        $this->iniciaTrailer();
        $this->add(1, 1, '9');
        $this->add(2, 7, $this->formatCnab('9', $this->getCount($add=0), 6));
        $this->add(8, 20, $this->formatCnab('9', $this->total, 13, 2));
        $this->add(21, 394, $this->formatCnab('9', 0, 374));
        $this->add(395, 400, $this->formatCnab('9', $this->getCount($add=2), 6));
        return $this;
    }
}
$banco = new SantanderRemessa();



# HEADER
$ret_header = $banco->header();
$atual = ''; # iniciar vazio para detalhe



    /**
     * Recebemos a data do processamento via parametro, caso contrario usamos a data do dia
     */
    if (isset($_GET['data_processamento']) and !empty($_GET['data_processamento']) and $banco->dateCheck($_GET['data_processamento']) == 1)
        $data_processamento = $_GET['data_processamento'];
    else
        $data_processamento = date('Y-m-d');



    /**
     * Consulta os boletos do dia
     * Se a $data_processamento nao for passado no parametro entao usaremos a data do dia.
     */
    // $data_boleto -> sua consulta com os dados dos boletos gerados no dia



/**
 * # DETAIL
 * passamos os dados dos boletos gerados num Array
 */
$mycnt = 0;
foreach ($data_boleto as $key => $value) 
{
    $expz = explode('-', $value['data_vencimento']);
    $dmy = $expz['2'] . $expz['1'] . substr($expz['0'], -2);

    # display output
    $tr    .= "<tr><td>".$value['data_processamento']."</td><td>".$value['valor_documento']."</td><td>".$value['nosso_numero']."</td><td>".$value['numero_documento']."</td></tr>";
    $mycnt +=1;

    $boleto = array(
        //'logo' => realpath(__DIR__ . '/../logos/') . DIRECTORY_SEPARATOR . '033.png',
        'dataVencimento' => $dmy, //date($dmy, strtotime('+3 days')),  # data do vencimento do boleto
        'data_processamento' => $value['data_processamento'],  # data que o boleto foi processado
        'valor_documento' => $value['valor_documento'],        # ex.: 100.23
        'valor' => $value['valor_cobrado'],                    # ex.: 100.23
        'multa' => true,
        'juros' => false,
        'moraDia' => false,                                    # cobrar 2% do valor do boleto apos o vencimento (tratado acima).
        'desconto' => $value['desconto'],                      # Se quiser dar desconto usar -> $value['desconto'] -- se valor do desconto maior que 0 entao precisa ter uma data para a concessao do desconto.
        'data_desconto' => $dmy,                               # $dmy, //date('dmy', strtotime('+3 days')),  # usar apenas se o desconto for maior que zero.
        'numero' => $value['nosso_numero'],                    # nosso numero do boleto.
        'numeroDocumento' => $value['numero_documento'],       # seu numero (invoice number)

        'pagador' => [
            'nome' => $value['sacado'],                   # nome do cliente
            'endereco' => 'RUA DA LAPA, 23',
            'bairro' => 'LAPA',
            'cep' => '05080101',
            'uf' => 'SP',
            'cidade' => 'SAO PAULO',
            'documento' => $value['@CPF'],
            'nome_documento' => '',
            'endereco2' => '',
        ],

        'beneficiario' => [
            'nome' => 'BAVARIAN ILLUMINATI LTDA - ME',
            'endereco' => 'RUA PIO XI, 23',
            'bairro' => 'LAPA',
            'cep' => '05060001',
            'uf' => 'SP',
            'cidade' => 'SAO PAULO',
            'documento' => '012012012000901',
            'nome_documento' => '',
            'endereco2' => '', 
        ],
        'sacadorAvalista' => [
            'nome' => 'BAVARIAN ILLUMINATI LTDA - ME',
            'endereco' => 'RUA PIO XI, 23',
            'bairro' => 'LAPA',
            'cep' => '05060001',
            'uf' => 'SP',
            'cidade' => 'SAO PAULO',
            'documento' => '012012012000901',
            'nome_documento' => '',
            'endereco2' => '', 
        ],

        'diasProtesto' => 0, 
        'diasBaixaAutomatica' => 0,
        'carteira' => $value['carteira'],                 # 101
        'agencia' => $value['agencia'],                   # tamanho 4 digitos
        'conta' => $value['conta'],                       # tamanho 8 digitos
        'descricaoDemonstrativo' => array('demonstrativo 1', 'demonstrativo 2', 'demonstrativo 3'),
        'instrucoes' =>  array('instrucao 1', 'instrucao 2', 'instrucao 3'),
        'aceite' => 'N',
        'especieDoc' => '01'                              # DM
    );

    // Cria o registro
    $ret_detail = $banco->addBoleto($boleto);
    $atual = '';                                          # iniciar vazio para
    $boleto = array();                                    # esvaziar boleto para prox. iteracao.
}



/** 
 * # TRAILER
 */
$ret_trailer = $banco->trailer();



# display output
print "<table class='data'><thead><tr><th>Processamento</th><th>Valor Boleto</th><th>Nosso Numero</th><th>Fatura</th></tr></thead><tbody>";
print $tr . "</tbody></table>";
print "Total de items na remessa <b>" . $mycnt . "</b><br><br>";



/**
 * Save 
 */
$fn = 'remessa-' . date('dmYHis') . '.txt';
$linha_remessa = $banco->save($path='remessa/' . $fn);
print "<pre>Arquivo gravado no local: `".$linha_remessa."`</pre>";



/*
 * Instrucoes de Uso
 * Executar esse script todos os dias uteis, preferencia entre 20:00:00 e 23:59:59
 * O script vai consultar todos os boletos gerados no dia, e entao gerar o 
 * arquivo de remessa, que deve ser enviado ao banco via internet banking.
 * Recomendado automatizar o uso do script via cronjobs. 
 * No caso do arquivo de remessa nao ter sido gerado, ainda e possivel gerar 
 * manualmente entrando com o parametro data na URL. 
 * Exemplo: https://domain.tld/script_remessa.php?data_processamento=YYYY-MM-DD
 *
 * TESTES
 *
    $string   = $banco->appendStrings($str1 = "c3z@r", $str2 = "@ugust0", $str3 = "Rhiádi");
    $string   = $banco->normalizeChars($string);
    $number   = 1287925; //12.879,25
    $bnk      = $banco->percent($big=5525, $percent=10);
    $cnpj     = "0123123123100012";
    $maskared = $banco->maskString($cnpj, $mask="###.###.###-####/##");
    $banco->numberFormatGeneral($n=$cnpj, $loop=13, $insert = 0);
    $testpad  = $banco->formatCnab($tipo="X", $valor=$string, $tamanho=15, $dec = 0, $sFill = '');
    $modulo11 = $banco->modulo11($n=1245, $factor = 2, $base = 9, $x10 = 0, $resto10 = 0);
    $modulo10 = $banco->modulo10($n=1200);
    $myarr    = array("Key"=>"3c1AtaD3bFa53c1At4e8");
    $keyz     = $banco->array2Controle($a=$myarr);
    $key      = $banco->controle2array($controle="3c1AtaD3bFa53c1At4e8"); // aceita dec e hex
 *
 */
?>