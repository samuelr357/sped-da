<?php

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../src/Shopee/EtiquetaShopeeProcessor.php';

$files = [
    [
        "etiquetas" => base64_encode(file_get_contents(__DIR__ . "/etiquetas.pdf")),
        "danfes" => [
            'xml'
        ]
    ]
];

try {
    $processor = new EtiquetaShopeeProcessor();
    $processor->renderMultiple($files);

    echo ">>> PDF final gerado com sucesso!\n";
} catch (\Exception $e) {
    die("ERRO: " . $e->getMessage() . "\n");
}
