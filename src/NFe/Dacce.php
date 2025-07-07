<?php

namespace NFePHP\DA\NFe;

/**
 * Classe para geração do PDF da Carta de Correção Eletrônica (CCe) da NFe.
 * Baseada na estrutura das classes de DACTE/DACTEOS/DAEVENTO.
 *
 * @category  Library
 * @package   nfephp-org/sped-da
 * @copyright 2009-2024 NFePHP
 * @license   http://www.gnu.org/licenses/lesser.html LGPL v3
 * @link      http://github.com/nfephp-org/sped-da for the canonical source repository
 * @author    Adaptado por IA com base no código do projeto
 */

use NFePHP\DA\Common\DaCommon;
use NFePHP\DA\Legacy\Dom;
use NFePHP\DA\Legacy\Pdf;

class Dacce extends DaCommon
{
    protected $xml;
    protected $dom;
    protected $procEventoNFe;
    protected $evento;
    protected $infEvento;
    protected $retEvento;
    protected $rinfEvento;
    protected $tpAmb;
    protected $cOrgao;
    protected $infCorrecao;
    protected $xCondUso;
    protected $dhEvento;
    protected $cStat;
    protected $xMotivo;
    protected $xJust;
    protected $CNPJDest = '';
    protected $CPFDest = '';
    protected $dhRegEvento;
    protected $nProt;
    protected $enderEmit;
    protected $emit;
    protected $tpEvento;
    protected $id;
    protected $chNFe;
    protected $formatoChave = "#### #### #### #### #### #### #### #### #### #### ####";
    protected $creditos;
    protected bool $exibirRodape = true;

    /**
     * __construct
     *
     * @param string $xml Arquivo XML (diretório ou string)
     */
    public function __construct($xml, $notaXml)
    {
        $this->loadDoc($xml);
        $this->loadNotaXml($notaXml);
    }

    protected function loadNotaXml($notaXml)
    {
        if (!empty($notaXml)) {
            $this->dom = new \DomDocument();
            $this->dom->loadXML($notaXml);
            if (empty($this->dom->getElementsByTagName("infNFe")->item(0))) {
                throw new \Exception('Isso não é um NFe.');
            }
            $this->enderEmit = $this->dom->getElementsByTagName("enderEmit")->item(0);
            $this->emit = $this->dom->getElementsByTagName("emit")->item(0);
        }
    }

    protected function loadDoc($xml)
    {
        $this->dom = new \DomDocument;
        $this->dom->loadXML($xml);
        $this->procEventoNFe = $this->dom->getElementsByTagName("procEventoNFe")->item(0);
        $this->evento = $this->dom->getElementsByTagName("evento")->item(0);
        $this->infEvento = $this->evento->getElementsByTagName("infEvento")->item(0);
        $this->retEvento = $this->dom->getElementsByTagName("retEvento")->item(0);
        $this->rinfEvento = $this->retEvento->getElementsByTagName("infEvento")->item(0);
        $this->tpEvento = $this->infEvento->getElementsByTagName("tpEvento")->item(0)->nodeValue;
        if ($this->tpEvento !== '110110') {
            throw new \Exception('Evento não implementado ' . $this->tpEvento . ' !!');
        }
        $this->id = str_replace('ID', '', $this->infEvento->getAttribute("Id"));
        $this->chNFe = $this->infEvento->getElementsByTagName("chNFe")->item(0)->nodeValue;
        $this->tpAmb = $this->infEvento->getElementsByTagName("tpAmb")->item(0)->nodeValue;
        $this->cOrgao = $this->infEvento->getElementsByTagName("cOrgao")->item(0)->nodeValue;
        $this->infCorrecao = $this->infEvento->getElementsByTagName("xCorrecao");
        $this->xCondUso = $this->infEvento->getElementsByTagName("xCondUso")->item(0);
        $this->xCondUso = (empty($this->xCondUso) ? '' : $this->xCondUso->nodeValue);
        $this->dhEvento = $this->infEvento->getElementsByTagName("dhEvento")->item(0)->nodeValue;
        $this->cStat = $this->rinfEvento->getElementsByTagName("cStat")->item(0)->nodeValue;
        $this->xMotivo = $this->rinfEvento->getElementsByTagName("xMotivo")->item(0)->nodeValue;
        $this->CNPJDest = !empty($this->rinfEvento->getElementsByTagName("CNPJDest")->item(0)->nodeValue) ?
            $this->rinfEvento->getElementsByTagName("CNPJDest")->item(0)->nodeValue : '';
        $this->CPFDest = !empty($this->rinfEvento->getElementsByTagName("CPFDest")->item(0)->nodeValue) ?
            $this->rinfEvento->getElementsByTagName("CPFDest")->item(0)->nodeValue : '';
        $this->dhRegEvento = $this->rinfEvento->getElementsByTagName("dhRegEvento")->item(0)->nodeValue;
        $this->nProt = $this->rinfEvento->getElementsByTagName("nProt")->item(0)->nodeValue;
    }

    /**
     * Gera o PDF da CCe
     * @param string $logo base64 da logomarca
     * @return string O ID do evento extraido do arquivo XML
     */
    public function monta($logo = '')
    {
        if (!empty($logo)) {
            $this->logomarca = $this->adjustImage($logo);
        }
        if (empty($this->orientacao)) {
            $this->orientacao = 'P';
        }
        // margens do PDF
        $margSup = $this->margsup ?? 4;
        $margEsq = $this->margesq ?? 4;
        $margDir = $this->margesq ?? 4;
        $this->pdf = new Pdf($this->orientacao, 'mm', $this->papel ?? 'A4');
        if ($this->orientacao == 'P') {
            $xInic = 5;
            $yInic = 5;
            $maxW = 207;
            $maxH = 292;
        } else {
            $xInic = 5;
            $yInic = 5;
            $maxH = 210;
            $maxW = 297;
        }
        $this->wPrint = $maxW - ($margEsq + $xInic);
        $this->hPrint = $maxH - ($margSup + $yInic);
        $this->pdf->aliasNbPages();
        $this->pdf->setMargins($margEsq, $margSup, $margDir);
        $this->pdf->setDrawColor(0, 0, 0);
        $this->pdf->setFillColor(255, 255, 255);
        $this->pdf->open();
        $this->pdf->addPage($this->orientacao, $this->papel ?? 'A4');
        $this->pdf->setLineWidth(0.1);
        $this->pdf->setTextColor(0, 0, 0);

        $pag = 1;
        $x = $xInic;
        $y = $yInic;
        $y = $this->header($x, $y, $pag);
        $y = $this->body($x, $y + 15);
        $y = $this->footer($x, $y + $this->hPrint - 5);
        return $this->id;
    }

    private function header($x, $y, $pag)
    {
        $oldX = $x;
        $oldY = $y;
        $maxW = $this->wPrint;
        $w = round($maxW * 0.41, 0);
        $aFont = array('font' => $this->fontePadrao, 'size' => 6, 'style' => 'I');
        $w1 = $w;
        $h = 32;
        $oldY += $h;
        $this->pdf->textBox($x, $y, $w, $h);
        $texto = 'IDENTIFICAÇÃO DO EMITENTE';
        $this->pdf->textBox($x, $y, $w, 5, $texto, $aFont, 'T', 'C', 0, '');
        if (!empty($this->logomarca)) {
            $logoInfo = getimagesize($this->logomarca);
            $logoWmm = ($logoInfo[0] / 72) * 25.4;
            $logoHmm = ($logoInfo[1] / 72) * 25.4;
            $nImgW = round($w / 3, 0);
            $nImgH = round($logoHmm * ($nImgW / $logoWmm), 0);
            $xImg = $x + ($w - $nImgW) / 2;
            $yImg = $y + 5;
            $this->pdf->image($this->logomarca, $xImg, $yImg, $nImgW, $nImgH, 'jpeg');
            $x1 = $x;
            $y1 = $yImg + $nImgH + 1;
            $tw = $w;
        } else {
            $y1 = round($h / 3 + $y - 3, 0);
            $x1 = $x;
            $tw = $w;
        }
        $aFont = array('font' => $this->fontePadrao, 'size' => 12, 'style' => 'B');
        $texto = $this->emit->getElementsByTagName("xNome")->item(0)->nodeValue;
        $this->pdf->textBox($x1, $y1, $tw, 8, $texto, $aFont, 'T', 'C', 0, '');
        $y1 = $y1 + 6;
        $aFont = array('font' => $this->fontePadrao, 'size' => 8, 'style' => '');
        $lgr = $this->getTagValue($this->enderEmit, "xLgr");
        $nro = $this->getTagValue($this->enderEmit, "nro");
        $cpl = $this->getTagValue($this->enderEmit, "xCpl", " - ");
        $bairro = $this->getTagValue($this->enderEmit, "xBairro");
        $CEP = $this->getTagValue($this->enderEmit, "CEP");
        $CEP = $this->formatField($CEP, "#####-###");
        $mun = $this->getTagValue($this->enderEmit, "xMun");
        $UF = $this->getTagValue($this->enderEmit, "UF");
        $fone = $this->getTagValue($this->enderEmit, "fone");
        $email = 'adsadasdsadas';
        $foneLen = strlen($fone);
        if ($foneLen > 0) {
            $fone2 = substr($fone, 0, $foneLen - 4);
            $fone1 = substr($fone, 0, $foneLen - 8);
            $fone = '(' . $fone1 . ') ' . substr($fone2, -4) . '-' . substr($fone, -4);
        } else {
            $fone = '';
        }
        if ($email != '') {
            $email = 'Email: ' . $email;
        }
        $texto = "";
        $tmp_txt = trim(($lgr != '' ? "$lgr, " : '') . ($nro != 0 ? $nro : "SN") . ($cpl != '' ? " - $cpl" : ''));
        $tmp_txt = ($tmp_txt == 'SN' ? '' : $tmp_txt);
        $texto .= ($texto != '' && $tmp_txt != '' ? "\n" : '') . $tmp_txt;
        $tmp_txt = trim($bairro . ($bairro != '' && $CEP != '' ? " - " : '') . $CEP);
        $texto .= ($texto != '' && $tmp_txt != '' ? "\n" : '') . $tmp_txt;
        $tmp_txt = $mun;
        $tmp_txt .= ($tmp_txt != '' && $UF != '' ? " - " : '') . $UF;
        $tmp_txt .= ($tmp_txt != '' && $fone != '' ? " - " : '') . $fone;
        $texto .= ($texto != '' && $tmp_txt != '' ? "\n" : '') . $tmp_txt;
        $tmp_txt = $email;
        $texto .= ($texto != '' && $tmp_txt != '' ? "\n" : '') . $tmp_txt;
        $this->pdf->textBox($x1, $y1 - 2, $tw, 8, $texto, $aFont, 'T', 'C', 0, '');

        $w2 = round($maxW - $w, 0);
        $x += $w;
        $this->pdf->textBox($x, $y, $w2, $h);
        $y1 = $y + $h;
        $aFont = array('font' => $this->fontePadrao, 'size' => 16, 'style' => 'B');
        $texto = 'Representação Gráfica de CCe';
        $this->pdf->textBox($x, $y + 2, $w2, 8, $texto, $aFont, 'T', 'C', 0, '');
        $aFont = array('font' => $this->fontePadrao, 'size' => 12, 'style' => 'I');
        $texto = '(Carta de Correção Eletrônica)';
        $this->pdf->textBox($x, $y + 8, $w2, 9, $texto, $aFont, 'T', 'C', 0, '');
        $texto = 'ID do Evento: ' . $this->id;
        $aFont = array('font' => $this->fontePadrao, 'size' => 10, 'style' => '');
        $this->pdf->textBox($x, $y + 15, $w2, 8, $texto, $aFont, 'T', 'L', 0, '');
        $tsHora = $this->toTimestamp($this->dhEvento);
        $texto = 'Criado em : ' . date('d/m/Y   H:i:s', $tsHora);
        $this->pdf->textBox($x, $y + 20, $w2, 8, $texto, $aFont, 'T', 'L', 0, '');
        $tsHora = $this->toTimestamp($this->dhRegEvento);
        $texto = 'Protocolo: ' . $this->nProt . '  -  Registrado na SEFAZ em: ' . date('d/m/Y   H:i:s', $tsHora);
        $this->pdf->textBox($x, $y + 25, $w2, 8, $texto, $aFont, 'T', 'L', 0, '');

        $x = $oldX;
        $this->pdf->textBox($x, $y1, $maxW, 40);
        $sY = $y1 + 40;
        $texto = 'De acordo com as determinações legais vigentes, vimos por meio '
            . 'desta comunicar-lhe que a Nota Fiscal Eletrônica, abaixo referenciada, '
            . 'contém irregularidades que estão destacadas e suas respectivas '
            . 'correções, solicitamos que sejam aplicadas essas correções ao '
            . 'executar seus lançamentos fiscais.';
        $aFont = array('font' => $this->fontePadrao, 'size' => 10, 'style' => '');
        $this->pdf->textBox($x + 5, $y1, $maxW - 5, 20, $texto, $aFont, 'T', 'L', 0, '', false);
        $x = $oldX;
        $y = $y1;
        $aFont = array('font' => $this->fontePadrao, 'size' => 12, 'style' => 'B');
        $cnpj = $this->emit->getElementsByTagName("CNPJ")->item(0)->nodeValue;
        $numCnpj = $this->formatField($cnpj, "##.###.###/####-##");
        $texto = "CNPJ do Destintário: " . $numCnpj;
        $this->pdf->textBox($x + 3, $y + 13, $w2, 8, $texto, $aFont, 'T', 'L', 0, '');
        $numNF = substr($this->chNFe, 25, 9);
        $serie = substr($this->chNFe, 22, 3);
        $numNF = $this->formatField($numNF, "###.###.###");
        $texto = "Nota Fiscal: " . $numNF . '  -   Série: ' . $serie;
        $this->pdf->textBox($x + 3, $y + 19, $w2, 8, $texto, $aFont, 'T', 'L', 0, '');
        $bW = 87;
        $bH = 15;
        $x = 55;
        $y = $y1 + 13;
        $w = $maxW;
        $this->pdf->setFillColor(0, 0, 0);
        $this->pdf->code128($x + (($w - $bW) / 2), $y + 2, $this->chNFe, $bW, $bH);
        $this->pdf->setFillColor(255, 255, 255);
        $y1 = $y + 2 + $bH;
        $aFont = array('font' => $this->fontePadrao, 'size' => 10, 'style' => '');
        $texto = $this->formatField($this->chNFe, $this->formatoChave);
        $this->pdf->textBox($x, $y1, $w - 2, $h, $texto, $aFont, 'T', 'C', 0, '');
        $retVal = $sY + 2;
        if ($this->tpAmb != 1) {
            $x = 10;
            $y = round($this->hPrint * 2 / 3, 0);
            $h = 5;
            $w = $maxW - (2 * $x);
            $this->pdf->setTextColor(90, 90, 90);
            $texto = "SEM VALOR FISCAL";
            $aFont = array('font' => $this->fontePadrao, 'size' => 48, 'style' => 'B');
            $this->pdf->textBox($x, $y, $w, $h, $texto, $aFont, 'C', 'C', 0, '');
            $aFont = array('font' => $this->fontePadrao, 'size' => 30, 'style' => 'B');
            $texto = "AMBIENTE DE HOMOLOGAÇÃO";
            $this->pdf->textBox($x, $y + 14, $w, $h, $texto, $aFont, 'C', 'C', 0, '');
            $this->pdf->setTextColor(0, 0, 0);
        }

        $yObs = $y1 + 10;

        $x = 5;
        $textoObs = 'A Carta de Correcao e disciplinada pelo paragrafo 1o-A do art. 7o do Convenio S/N, de 15 de dezembro de 1970 e pode ser utilizada para regularizacao de erro ocorrido na '
            . 'emissao de documento fiscal, desde que o erro nao esteja relacionado com: I - as variaveis que determinam o valor do imposto tais como: base de calculo, aliquota, diferenca de '
            . 'preco, quantidade, valor da operacao ou da prestacao; II - a correcao de dados cadastrais que implique mudanca do remetente ou do destinatario; III - a data de emissao ou de '
            . 'saida.';

        $aFontObs = array('font' => $this->fontePadrao, 'size' => 7, 'style' => 'I');
        $alturaObs = 14;
        $this->pdf->textBox($x, $yObs, $maxW, $alturaObs - 4, $textoObs, $aFontObs, 'T', 'L', 1, '', false);
        return $retVal;
    }

    private function body($x, $y)
    {
        $maxW = $this->wPrint;
        $texto = 'CORREÇÕES A SEREM CONSIDERADAS';
        $aFont = array('font' => $this->fontePadrao, 'size' => 10, 'style' => 'B');
        $this->pdf->textBox($x, $y, $maxW, 5, $texto, $aFont, 'T', 'L', 0, '', false);
        $y += 5;
        $this->pdf->textBox($x, $y, $maxW, 190);
        $aFont = array('font' => $this->fontePadrao, 'size' => 9, 'style' => '');
        $i = 0;
        while ($i < $this->infCorrecao->length) {
            $x = 2;
            $maxW = $this->wPrint;
            $valor = $this->infCorrecao->item($i)->nodeValue;
            $lines = $this->pdf->getNumLines($valor, ($this->wPrint - 35), $aFont) ?? '';
            $aFont = array('font' => $this->fontePadrao, 'size' => 9, 'style' => 'B');
            $this->pdf->textBox($x + 4, $y, 30, 5, "Correção", $aFont, 'T', 'L', 0, '', false);
            $aFont = array('font' => $this->fontePadrao, 'size' => 9, 'style' => '');
            $this->pdf->textBox($x + 30, $y, ($this->wPrint - 35), 5, $valor, $aFont, 'T', 'L', 0, '', false);
            $y += (3 * $lines) + 3;
            $i++;
        }
    }

    private function footer($x, $y)
    {
        $w = $this->wPrint;
        $texto = "Este documento é uma representação gráfica da CCe e foi "
            . "impresso apenas para sua informação e não possui validade fiscal."
            . "\n A CCe deve ser recebida e mantida em arquivo eletrônico XML e "
            . "pode ser consultada através dos Portais das SEFAZ.";
        $aFont = array('font' => $this->fontePadrao, 'size' => 10, 'style' => 'I');
        $this->pdf->textBox($x, $y, $w, 20, $texto, $aFont, 'T', 'C', 0, '', false);
        $y = $this->hPrint - 4;
        if ($this->exibirRodape) {
            $w = $this->wPrint - 4;
            $aFont = array('font' => $this->fontePadrao, 'size' => 6, 'style' => 'I');
            $texto = "Impresso em " . date('d/m/Y') . " as " . date('H:i:s')
                . '  ' . $this->creditos;
            $this->pdf->textBox($x, $y + 10, $w, 4, $texto, $aFont, 'T', 'L', 0, '');
        }
        $texto = $this->powered ? "Powered by MicroSistemas®" : '';
        $aFont = array('font' => $this->fontePadrao, 'size' => 6, 'style' => 'I');
        $this->pdf->textBox($x, $y + 10, $w, 4, $texto, $aFont, 'T', 'R', 0, 'http://www.nfephp.org');
    }

    public function setExibirRodape(bool $exibirRotape): void
    {
        $this->exibirRodape = $exibirRotape;
    }
}
