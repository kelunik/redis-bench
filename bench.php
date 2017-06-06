<?php

use Clue\Redis\Protocol\Parser\ResponseParser;
use Kelunik\StreamingResp\IterativeGeneratorRespParser;
use Kelunik\StreamingResp\IterativeRespParser;
use Kelunik\StreamingResp\RecursiveGeneratorRespParser;
use Kelunik\StreamingResp\RecursiveGeneratorRespParserWithoutInlining;

chdir(__DIR__);
error_reporting(E_ALL);

require "vendor/autoload.php";

function bench(BenchCase $benchCase, array $methods) {
    foreach ($methods as $method) {
        printf("%s: %f @ %s\n", get_class($benchCase->parser), benchSingle($benchCase, $method), $method);
    }
}

function benchSingle(BenchCase $benchCase, string $method): float {
    $start = microtime(true);

    $benchCase->$method();

    return microtime(true) - $start;
}

abstract class BenchCase {
    public $parser;

    public function __construct($parser) {
        $this->parser = $parser;
    }

    abstract protected function push($data);

    public function simpleArray() {
        for ($i = 0; $i < 1000000; $i++) {
            $this->push("*2\r\n$5\r\nHello\r\n:123456789\r\n");
        }
    }

    public function largerArray() {
        for ($i = 0; $i < 100000; $i++) {
            $this->push("*100\r\n" . str_repeat("$5\r\nHello\r\n", 100));
        }
    }

    public function simpleString() {
        for ($i = 0; $i < 1000000; $i++) {
            $this->push("+Hello\r\n");
        }
    }

    public function bulkString() {
        for ($i = 0; $i < 1000000; $i++) {
            $this->push("$5\r\nHello\r\n");
        }
    }

    public function incompleteArray() {
        for ($i = 0; $i < 1000000; $i++) {
            $this->push("*5\r\n$1\r\nH\r\n$1\r\nH\r\n$1\r\nH\r\n$1\r\nH\r\n:123456789\r");
            $this->push("\n");
        }
    }

    public function clueDos() {
        for ($i = 0; $i < 200; $i++) {
            $this->push("*10001\r\n" . str_repeat("$1\r\n.\r\n", 1000));
            $this->push("$");
            $this->push("1");
            $this->push("\r");
            $this->push("\n");
            $this->push(".");
            $this->push("\r");
            $this->push("\n");
        }
    }
}

$allMethods = ["simpleString", "bulkString", "simpleArray", "largerArray", "incompleteArray", /* "clueDos" */];

$argv[1] = $argv[1] ?? implode(",", $allMethods);

$methods = array_filter($allMethods, function ($method) use ($argv) {
    return in_array($method, array_map("trim", explode(",", $argv[1])));
});

bench(new class(new IterativeRespParser(function () {
})) extends BenchCase {
    protected function push($data) {
        $this->parser->push($data);
    }
}, $methods);

bench(new class(new IterativeGeneratorRespParser(function () {
})) extends BenchCase {
    protected function push($data) {
        $this->parser->push($data);
    }
}, $methods);

bench(new class(new RecursiveGeneratorRespParser(function () {
})) extends BenchCase {
    protected function push($data) {
        $this->parser->push($data);
    }
}, $methods);

bench(new class(new RecursiveGeneratorRespParserWithoutInlining(function () {
})) extends BenchCase {
    protected function push($data) {
        $this->parser->push($data);
    }
}, $methods);

bench(new class(new ResponseParser) extends BenchCase {
    protected function push($data) {
        $this->parser->pushIncoming($data);
    }
}, $methods);
