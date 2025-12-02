<?php

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../src/Shopee/EtiquetaShopeeProcessor.php';

$files = [
    [
        "etiquetas" => __DIR__ . "/etiquetas.pdf",
        "rodape"    => __DIR__ . "/rodape.pdf"
    ],
    [
        "etiquetas" => __DIR__ . "/etiquetas.pdf",
        "rodape"    => __DIR__ . "/rodape.pdf"
    ]
];

try {
    $processor = new EtiquetaShopeeProcessor();
    $processor->renderMultiple($files, __DIR__ . "/saida_final.pdf");

    echo ">>> PDF final gerado com sucesso!\n";
} catch (\Exception $e) {
    die("ERRO: " . $e->getMessage() . "\n");
}
