<?php

use Clue\Redis\Protocol\Parser\ResponseParser;
use Kelunik\StreamingResp\IterativeRespParser;
use Kelunik\StreamingResp\RecursiveRespParser;

chdir(__DIR__);
error_reporting(E_ALL);

require "vendor/autoload.php";

function bench(BenchCase $benchCase) {
    foreach (["simpleString", "bulkString", "simpleArray", "incompleteArray", "clueDos"] as $method) {
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

bench(new class(new IterativeRespParser(function () {})) extends BenchCase {
    protected function push($data) {
        $this->parser->push($data);
    }
});

bench(new class(new RecursiveRespParser(function () {})) extends BenchCase {
    protected function push($data) {
        $this->parser->push($data);
    }
});

bench(new class(new ResponseParser) extends BenchCase {
    protected function push($data) {
        $this->parser->pushIncoming($data);
    }
});
