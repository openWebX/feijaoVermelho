<?php

use openWebX\feijaoVermelho\feijaoVermelho;

require_once 'vendor/autoload.php';


class Test {

    use feijaoVermelho;

    public int $intValue;
    public string $stringValue;
    public int $random;
    public int $random2;

    public function __construct() {
        $this->intValue = 666;
        $this->stringValue = 'test';
        $this->random = rand(1,100);
        $this->random2 = rand(1,1000);
        $this
            ->upsertByIntValue_and_StringValue()
            ->prepare()
            ->save();
    }


}

$myTest = new Test();