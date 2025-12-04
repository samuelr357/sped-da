<?php

require __DIR__ . '/../../vendor/autoload.php';

use NFePHP\DA\NFe\DanfeEtiqueta;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;

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

/**
 * Processador de etiquetas Shopee + DANFE
 */
class EtiquetaShopeeProcessor
{
    protected string $tempDir;

    public function __construct()
    {
        $this->tempDir = sys_get_temp_dir();
    }

    /**
     * Processa múltiplos arquivos e gera um PDF final único
     *
     * @param array $files
     * @param string $outputFile
     */
    public function renderMultiple(array $files, string $outputFile = '')
    {
        $pdfFinal = new PDF_Rotate();

        foreach ($files as $item) {
            $arquivoEtiquetas = $item['etiquetas'];
            $danfesData = $item['danfes'];

            if (preg_match('/^[A-Za-z0-9+\/=]+$/', $arquivoEtiquetas) === 1) {
                $pdfEtiquetaString = base64_decode($arquivoEtiquetas);
            } else {
                throw new \Exception("Etiqueta enviada não é base64 válido.");
            }

            // Verifica se danfesData é um array ou string
            $danfesArray = is_array($danfesData) ? $danfesData : [$danfesData];

            // ============================
            // 1ª ETAPA: Cortar A4 → A6
            // ============================
            $tempCut = $this->tempDir . '\temp_cut_' . uniqid() . '.pdf';
            $pdfCut = new Fpdi();
            $pageCount = $pdfCut->setSourceFile(StreamReader::createByString($pdfEtiquetaString));

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

            $pdfCut->Output($tempCut, 'F');

            // ============================
            // 2ª ETAPA: Etiqueta + DANFE na mesma página
            // ============================
            $pageCount2 = (new Fpdi())->setSourceFile($tempCut);

            for ($page = 1; $page <= $pageCount2; $page++) {
                // Obtém o DANFE XML para esta página
                $danfeIndex = $page - 1;
                if (!isset($danfesArray[$danfeIndex])) {
                    break;
                }

                $pdfCutTemp = new Fpdi();
                $pdfCutTemp->setSourceFile($tempCut);
                $tplEtiqueta = $pdfCutTemp->importPage($page);
                $sizeEtiqueta = $pdfCutTemp->getTemplateSize($tplEtiqueta);

                $w = $sizeEtiqueta['width'];
                $h = $sizeEtiqueta['height'];

                $pdfDanfe = new DanfeEtiqueta($danfesArray[$danfeIndex]);
                $pdfContent = $pdfDanfe->render();

                $tempDanfe = $this->tempDir . '/temp_danfe_' . uniqid() . '.pdf';
                file_put_contents($tempDanfe, $pdfContent);

                $pdfFinal->setSourceFile($tempCut);
                $tplEtiquetaFinal = $pdfFinal->importPage($page);

                $pdfFinal->setSourceFile($tempDanfe);
                $tplDanfe = $pdfFinal->importPage(1);
                $sizeDanfe = $pdfFinal->getTemplateSize($tplDanfe);

                $pdfFinal->AddPage('P', [$w, $h]);
                $pdfFinal->Rotate(270, $w / 2, $h / 2);

                // Etiqueta
                $scaleEtiqueta = 0.71;
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

                // Remove arquivo temporário do DANFE
                if (file_exists($tempDanfe)) {
                    unlink($tempDanfe);
                }
            }

            // Remove arquivo temporário
            if (file_exists($tempCut)) {
                unlink($tempCut);
            }
        }

        // Salva PDF final
        if (empty($outputFile)) {
            $outputFile = __DIR__ . '/file.pdf';
        }
        $pdfFinal->Output($outputFile, 'F');
        return $outputFile;
    }
}
