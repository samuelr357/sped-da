<?php

namespace NFePHP\DA\Shopee;

use NFePHP\DA\NFe\DanfeEtiqueta;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;

/**
 * Processador de etiquetas Shopee + DANFE
 */
class EtiquetaShopeeProcessor
{
    protected string $tempDir;

    public function __construct()
    {
        $this->tempDir = __DIR__ . '/temp';

        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0777, true);
        }
    }

    protected function decodeAndUncompressPdf(string $base64): string
    {
        $rawPdf = base64_decode($base64);

        $tempIn = $this->tempDir . '/sho_temp_in_' . uniqid() . '.pdf';
        $tempOut = $this->tempDir . '/sho_temp_out_' . uniqid() . '.pdf';

        file_put_contents($tempIn, $rawPdf);

        // Rodar Ghostscript removendo compressão
        $gs = stripos(PHP_OS, 'WIN') === 0
            ? '"gswin64c.exe"'
            : 'gs';

        $cmd = sprintf(
            '%s -dNOPAUSE -dBATCH -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dPDFSETTINGS=/prepress -dCompressPages=false -dCompressFonts=false -dEncodeColorImages=false -dEncodeGrayImages=false -dEncodeMonoImages=false -sOutputFile="%s" "%s"',
            $gs,
            $tempOut,
            $tempIn
        );

        exec($cmd, $output, $return);

        if ($return !== 0 || !file_exists($tempOut)) {
            throw new \Exception("Ghostscript falhou ao descomprimir o PDF.");
        }

        $uncompressed = file_get_contents($tempOut);

        unlink($tempIn);
        unlink($tempOut);

        return $uncompressed;
    }


    /**
     * Processa múltiplos arquivos e gera um PDF final único
     *
     * @param array $files
     * @param string $outputFile
     */
    public function renderMultiple(array $files)
    {
        $pdfFinal = new PDF_Rotate();

        foreach ($files as $item) {
            $arquivoEtiquetas = $item['etiquetas'];
            $danfesData = $item['danfes'];

            if (preg_match('/^[A-Za-z0-9+\/=]+$/', $arquivoEtiquetas) === 1) {
                $pdfEtiquetaString = $this->decodeAndUncompressPdf($arquivoEtiquetas);
            } else {
                throw new \Exception("Etiqueta enviada não é base64 válido.");
            }

            // Verifica se danfesData é um array ou string
            $danfesArray = is_array($danfesData) ? $danfesData : [$danfesData];

            // ============================
            // 1ª ETAPA: Cortar A4 → A6
            // ============================
            $pdfCut = new Fpdi();
            $reader = StreamReader::createByString($pdfEtiquetaString);
            $pageCount = $pdfCut->setSourceFile($reader);

            for ($page = 1; $page <= $pageCount; $page++) {
                $tpl = $pdfCut->importPage($page);
                $size = $pdfCut->getTemplateSize($tpl);

                $fullWidth = $size['width'];
                $fullHeight = $size['height'];
                $halfWidth = $fullWidth / 2;
                $halfHeight = $fullHeight / 2;

                // Quadrante 1 (superior esquerdo)
                $pdfCut->AddPage('P', [$halfWidth, $halfHeight]);
                $pdfCut->useTemplate($tpl, 0, 0, $fullWidth, $fullHeight);

                // Quadrante 2 (inferior esquerdo)
                $pdfCut->AddPage('P', [$halfWidth, $halfHeight]);
                $pdfCut->useTemplate($tpl, 0, -$halfHeight, $fullWidth, $fullHeight);

                // Quadrante 3 (superior direito)
                $pdfCut->AddPage('P', [$halfWidth, $halfHeight]);
                $pdfCut->useTemplate($tpl, -$halfWidth, 0, $fullWidth, $fullHeight);

                // Quadrante 4 (inferior direito)
                $pdfCut->AddPage('P', [$halfWidth, $halfHeight]);
                $pdfCut->useTemplate($tpl, -$halfWidth, -$halfHeight, $fullWidth, $fullHeight);
            }

            $tempCut = $pdfCut->Output('S');

            // ============================
            // 2ª ETAPA: Etiqueta + DANFE na mesma página
            // ============================
            $readerCut = StreamReader::createByString($tempCut);
            $pageCount2 = (new Fpdi())->setSourceFile($readerCut);

            for ($page = 1; $page <= $pageCount2; $page++) {
                // Obtém o DANFE XML para esta página
                $danfeIndex = $page - 1;
                if (!isset($danfesArray[$danfeIndex])) {
                    break;
                }

                $pdfCutTemp = new Fpdi();
                $pdfCutTemp->setSourceFile(StreamReader::createByString($tempCut));
                $tplEtiqueta = $pdfCutTemp->importPage($page);
                $sizeEtiqueta = $pdfCutTemp->getTemplateSize($tplEtiqueta);

                $w = $sizeEtiqueta['width'];
                $h = $sizeEtiqueta['height'];

                $pdfDanfe = new DanfeEtiqueta($danfesArray[$danfeIndex]);
                $pdfContent = $pdfDanfe->render();

                $pdfFinal->setSourceFile(StreamReader::createByString($tempCut));
                $tplEtiquetaFinal = $pdfFinal->importPage($page);

                $pdfFinal->setSourceFile(StreamReader::createByString($pdfContent));
                $tplDanfe = $pdfFinal->importPage(1);
                $sizeDanfe = $pdfFinal->getTemplateSize($tplDanfe);

                $pdfFinal->AddPage('P', [$w, $h]);
                $pdfFinal->Rotate(270, $w / 2, $h / 2);

                // Etiqueta
                $scaleEtiqueta = 0.72;
                $scaledWE = $w * $scaleEtiqueta;
                $scaledHE = $h * $scaleEtiqueta;
                $xEtiqueta = -20;
                $yEtiqueta = ($h - $scaledHE) / 2;
                $pdfFinal->useTemplate($tplEtiquetaFinal, $xEtiqueta, $yEtiqueta, $scaledWE, $scaledHE);

                // DANFE
                $boxW = $scaledWE;
                $boxH = $scaledHE;
                $scaleDanfe = min($boxW / $sizeDanfe['width'], $boxH / $sizeDanfe['height']);
                $scaledWD = $sizeDanfe['width'] * $scaleDanfe;
                $scaledHD = $sizeDanfe['height'] * $scaleDanfe;
                $xDanfe = $w - $scaledWD + 20;
                $yDanfe = ($h - $scaledHD) / 2;
                $pdfFinal->useTemplate($tplDanfe, $xDanfe, $yDanfe, $scaledWD, $scaledHD);

                $pdfFinal->Rotate(0);

            }
        }

        $pdfBinary = $pdfFinal->Output('S');

        return base64_encode($pdfBinary);

    }
}

/**
 * Classe para permitir rotação (extensão do FPDI/FPDF)
 */
class PDF_Rotate extends Fpdi
{
    protected $angle = 0;

    public function Rotate($angle, $x = -1, $y = -1)
    {
        if ($x == -1)
            $x = $this->x;
        if ($y == -1)
            $y = $this->y;

        if ($this->angle != 0) {
            $this->_out('Q');
        }

        $this->angle = $angle;

        if ($angle != 0) {
            $angle *= M_PI / 180;
            $c = cos($angle);
            $s = sin($angle);
            $cx = $x * $this->k;
            $cy = ($this->h - $y) * $this->k;

            $this->_out(sprintf(
                'q %.5F %.5F %.5F %.5F %.5F %.5F cm 1 0 0 1 %.5F %.5F cm',
                $c,
                $s,
                -$s,
                $c,
                $cx,
                $cy,
                -$cx,
                -$cy
            ));
        }
    }

    protected function _endpage()
    {
        if ($this->angle != 0) {
            $this->angle = 0;
            $this->_out('Q');
        }
        parent::_endpage();
    }
}
